<?php
/**
 * Provider fallback chain with transient caching.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Metadata;

final class MetadataResolver {
	/** @var MetadataProvider[] */
	private $providers = array();

	/**
	 * @param MetadataProvider[] $providers Registered providers.
	 */
	public function __construct( array $providers = array() ) {
		foreach ( $providers as $provider ) {
			if ( $provider instanceof MetadataProvider ) {
				$this->providers[] = $provider;
			}
		}
		$this->sort();
	}

	/**
	 * Lazily register a provider.
	 */
	public function add( MetadataProvider $provider ): void {
		$this->providers[] = $provider;
		$this->sort();
	}

	/**
	 * Search down the priority chain. Keyed providers run first; on rate-limit
	 * or empty results we fall back to the keyless MusicBrainz provider.
	 *
	 * @return array<string, mixed> Response payload for REST.
	 */
	public function search( MetadataQuery $query, int $limit = 6 ): array {
		if ( ! $query->is_valid() ) {
			return array(
				'success' => false,
				'code'    => 'invalid_query',
				'message' => __( 'Enter a track title or artist name to search.', 'music-wave-core' ),
				'results' => array(),
			);
		}

		$limit  = max( 1, min( 15, $limit ) );
		$cached = $this->cache_get( $query, $limit );
		if ( is_array( $cached ) ) {
			$cached['cached'] = true;
			return $cached;
		}

		$attempts = array();
		foreach ( $this->enabled() as $provider ) {
			$attempts[] = $provider->name();
			try {
				$results = $provider->search( $query );
			} catch ( RateLimitException $e ) {
				continue; // Fall through to the next provider in the chain.
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( empty( $results ) ) {
				continue;
			}

			$results = array_slice( array_values( $results ), 0, $limit );
			foreach ( $results as $result ) {
				if ( '' === $result->cover_url ) {
					try {
						$result->cover_url = $provider->cover_for( $result );
					} catch ( \Throwable $e ) {
						$result->cover_url = '';
					}
				}
			}

			$payload = array(
				'success'  => true,
				'cached'   => false,
				'provider' => $provider->name(),
				'label'    => $this->provider_label( $provider->name() ),
				'attempts' => $attempts,
				'results'  => array_map(
					static function ( MetadataResult $result ): array {
						return $result->to_array();
					},
					$results
				),
			);
			$this->cache_set( $query, $limit, $payload );

			return $payload;
		}

		return array(
			'success'  => false,
			'cached'   => false,
			'code'     => 'no_results',
			'attempts' => $attempts,
			'message'  => __( 'No matches found. Try a different title or artist spelling.', 'music-wave-core' ),
			'results'  => array(),
		);
	}

	/**
	 * Fetch detailed metadata only after an editor selects a result.
	 */
	public function enrich( MetadataResult $result, array $release_types = array() ): MetadataResult {
		if ( '' === $result->provider || '' === $result->reference_id ) {
			return $this->with_factual_description( $result, $release_types );
		}

		$cache_key = 'mw_meta_detail_v2_' . md5( $result->provider . ':' . $result->reference_id . ':' . implode( ',', $release_types ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $this->with_factual_description( MetadataResult::from_array( array_merge( $result->to_array(), $cached ) ), $release_types );
		}

		foreach ( $this->enabled() as $provider ) {
			if ( $provider->name() !== $result->provider || ! $provider instanceof MetadataEnrichmentProvider ) {
				continue;
			}
			try {
				$enriched = $provider->enrich( $result );
				set_transient( $cache_key, $enriched->to_array(), 12 * HOUR_IN_SECONDS );
				return $this->with_factual_description( $enriched, $release_types );
			} catch ( \Throwable $e ) {
				break;
			}
		}

		return $this->with_factual_description( $result, $release_types );
	}

	/**
	 * Download a remote cover into the media library.
	 *
	 * @return array<string, int|string>|\WP_Error Attachment info or error.
	 */
	public function import_cover( string $url, string $title = '', int $post_id = 0 ) {
		$url = (string) esc_url_raw( $url, array( 'https' ) );
		if ( '' === $url ) {
			return new \WP_Error( 'invalid_cover', __( 'The cover URL is invalid.', 'music-wave-core' ) );
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$mime       = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : false;
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);
		if ( ! is_string( $mime ) || ! isset( $extensions[ $mime ] ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temp cleanup.
			return new \WP_Error( 'invalid_cover_type', __( 'The downloaded cover is not a supported image.', 'music-wave-core' ) );
		}

		$title    = '' !== $title ? $title : __( 'Imported cover', 'music-wave-core' );
		$basename = 'music-cover-' . substr( md5( $url ), 0, 12 ) . '.' . $extensions[ $mime ];
		$file     = array(
			'name'     => sanitize_file_name( $basename ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temp cleanup.
			return $attachment_id;
		}
		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );

		return array(
			'id'  => (int) $attachment_id,
			'url' => (string) wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * @return MetadataProvider[]
	 */
	private function enabled(): array {
		$out = array();
		foreach ( $this->providers as $provider ) {
			if ( $provider->is_enabled() ) {
				$out[] = $provider;
			}
		}

		return $out;
	}

	private function sort(): void {
		usort(
			$this->providers,
			static function ( MetadataProvider $a, MetadataProvider $b ): int {
				return $a->priority() <=> $b->priority();
			}
		);
	}

	private function provider_label( string $name ): string {
		$labels = array(
			'spotify'     => __( 'Spotify', 'music-wave-core' ),
			'discogs'     => __( 'Discogs', 'music-wave-core' ),
			'musicbrainz' => __( 'MusicBrainz + Cover Art Archive', 'music-wave-core' ),
		);

		return isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
	}

	/** @return array<string, mixed>|null */
	private function cache_get( MetadataQuery $query, int $limit ): ?array {
		$value = get_transient( $this->cache_key( $query, $limit ) );

		return is_array( $value ) ? $value : null;
	}

	/** @param array<string, mixed> $payload */
	private function cache_set( MetadataQuery $query, int $limit, array $payload ): void {
		set_transient( $this->cache_key( $query, $limit ), $payload, 30 * MINUTE_IN_SECONDS );
	}

	private function cache_key( MetadataQuery $query, int $limit ): string {
		return 'mw_meta_v4_' . md5( wp_json_encode( array( $query->track, $query->artist, $query->album, $query->year, $query->release_types, $limit ) ) );
	}

	/**
	 * Supply a localized factual description when the provider has no annotation.
	 */
	private function with_factual_description( MetadataResult $result, array $release_types = array() ): MetadataResult {
		if ( '' !== trim( wp_strip_all_tags( $result->description ) ) ) {
			return $result;
		}

		$type      = isset( $release_types[0] ) ? sanitize_key( (string) $release_types[0] ) : 'track';
		$artist    = '' !== $result->artist ? $result->artist : __( 'Unknown artist', 'music-wave-core' );
		$templates = array(
			'track'           => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a track by %2$s.', 'music-wave-core' ),
			'single'          => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a single by %2$s.', 'music-wave-core' ),
			'ep'              => /* translators: 1: release title, 2: artist name. */ __( '%1$s is an EP by %2$s.', 'music-wave-core' ),
			'album'           => /* translators: 1: release title, 2: artist name. */ __( '%1$s is an album by %2$s.', 'music-wave-core' ),
			'mix'             => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a mix by %2$s.', 'music-wave-core' ),
			'playlist'        => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a playlist curated by %2$s.', 'music-wave-core' ),
			'podcast_show'    => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a podcast show from %2$s.', 'music-wave-core' ),
			'podcast_episode' => /* translators: 1: release title, 2: artist name. */ __( '%1$s is a podcast episode from %2$s.', 'music-wave-core' ),
		);
		$parts     = array( sprintf( isset( $templates[ $type ] ) ? $templates[ $type ] : $templates['track'], $result->title, $artist ) );
		if ( '' !== $result->album && $result->album !== $result->title ) {
			$parts[] = sprintf(
				/* translators: %s: album title */
				__( 'It appears on the album %s.', 'music-wave-core' ),
				$result->album
			);
		}
		if ( '' !== $result->year ) {
			$parts[] = sprintf(
				/* translators: %s: four-digit release year */
				__( 'Release year: %s.', 'music-wave-core' ),
				$result->year
			);
		}
		if ( ! empty( $result->genres ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated genres */
				__( 'Genres: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->genres, 'name' ) )
			);
		}
		if ( ! empty( $result->labels ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated record labels */
				__( 'Labels: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->labels, 'name' ) )
			);
		}
		if ( ! empty( $result->moods ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated moods */
				__( 'Moods: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->moods, 'name' ) )
			);
		}

		return MetadataResult::from_array( array_merge( $result->to_array(), array( 'description' => implode( ' ', $parts ) ) ) );
	}
}
