<?php
/**
 * Optional keyed Discogs provider.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Metadata;

final class DiscogsProvider implements MetadataProvider, MetadataEnrichmentProvider {
	private const SEARCH_ENDPOINT  = 'https://api.discogs.com/database/search';
	private const RELEASE_ENDPOINT = 'https://api.discogs.com/releases/';

	/** @var string */
	private $token;

	public function __construct( string $token = '' ) {
		$this->token = $token;
	}

	public function name(): string {
		return 'discogs';
	}

	public function is_enabled(): bool {
		return '' !== $this->token;
	}

	public function priority(): int {
		return 20;
	}

	public function search( MetadataQuery $query ): array {
		if ( ! $this->is_enabled() || ! $query->is_valid() || $query->is_podcast() || 'playlist' === $query->primary_release_type() ) {
			return array();
		}

		$args = array(
			'type'     => 'release',
			'per_page' => 8,
			'query'    => trim( implode( ' ', array_filter( array( $query->track, $query->artist, $query->album ) ) ) ),
		);
		if ( '' !== $query->artist ) {
			$args['artist'] = $query->artist;
		}
		if ( '' !== $query->track ) {
			$args['release_title'] = $query->track;
		}
		if ( '' !== $query->year ) {
			$args['year'] = $query->year;
		}
		$format = $this->format_for_release_type( $query->primary_release_type() );
		if ( '' !== $format ) {
			$args['format'] = $format;
		}

		$response = wp_remote_get(
			add_query_arg( $args, self::SEARCH_ENDPOINT ),
			array(
				'timeout'    => 10,
				'user-agent' => 'MusicWave/' . ( defined( 'MUSIC_WAVE_CORE_VERSION' ) ? MUSIC_WAVE_CORE_VERSION : '1.0' ),
				'headers'    => array(
					'Authorization' => 'Discogs token=' . $this->token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			throw new RateLimitException( 'Discogs rate limit reached.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$results = is_array( $data ) && ! empty( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();

		$out = array();
		foreach ( $results as $result ) {
			$item = $this->normalize_result( is_array( $result ) ? $result : array() );
			if ( $item->is_usable() ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	private function format_for_release_type( string $release_type ): string {
		$formats = array(
			'single' => 'Single',
			'ep'     => 'EP',
			'album'  => 'Album',
			'mix'    => 'Mixed',
		);
		return isset( $formats[ $release_type ] ) ? $formats[ $release_type ] : '';
	}

	public function cover_for( MetadataResult $result ): string {
		return $result->cover_url; // Discogs returns cover art inline.
	}

	/**
	 * Fetch Discogs release notes, artists, genres/styles, and labels.
	 *
	 * @throws RateLimitException When the Discogs API reports a rate limit.
	 */
	public function enrich( MetadataResult $result ): MetadataResult {
		if ( '' === $result->reference_id ) {
			return $result;
		}

		$response = $this->request( trailingslashit( self::RELEASE_ENDPOINT ) . rawurlencode( $result->reference_id ) );
		if ( is_wp_error( $response ) ) {
			return $result;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			throw new RateLimitException( 'Discogs rate limit reached.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return $result;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return $result;
		}

		$artists = $this->entities( isset( $data['artists'] ) && is_array( $data['artists'] ) ? $data['artists'] : array() );
		$labels  = $this->entities( isset( $data['labels'] ) && is_array( $data['labels'] ) ? $data['labels'] : array() );
		$genres  = array_merge(
			$this->entities( isset( $data['genres'] ) && is_array( $data['genres'] ) ? $data['genres'] : array() ),
			$this->entities( isset( $data['styles'] ) && is_array( $data['styles'] ) ? $data['styles'] : array() )
		);

		return MetadataResult::from_array(
			array_merge(
				$result->to_array(),
				array(
					'description' => isset( $data['notes'] ) && is_scalar( $data['notes'] ) ? $this->clean_notes( (string) $data['notes'] ) : $result->description,
					'artists'     => ! empty( $artists ) ? $artists : $result->artists,
					'genres'      => ! empty( $genres ) ? $genres : $result->genres,
					'labels'      => ! empty( $labels ) ? $labels : $result->labels,
				)
			)
		);
	}

	/**
	 * Normalize one Discogs search result.
	 *
	 * @param array<string, mixed> $result Result payload.
	 */
	private function normalize_result( array $result ): MetadataResult {
		$title  = isset( $result['title'] ) ? (string) $result['title'] : '';
		$artist = '';
		$album  = $title;
		if ( false !== strpos( $title, ' - ' ) ) {
			list( $artist, $album ) = array_map( 'trim', explode( ' - ', $title, 2 ) );
		}

		$cover = '';
		if ( ! empty( $result['cover_image'] ) ) {
			$cover = (string) $result['cover_image'];
		} elseif ( ! empty( $result['thumb'] ) ) {
			$cover = (string) $result['thumb'];
		}
		$source_url = isset( $result['uri'] ) ? (string) $result['uri'] : '';
		if ( 0 === strpos( $source_url, '/' ) ) {
			$source_url = 'https://www.discogs.com' . $source_url;
		}

		return MetadataResult::from_array(
			array(
				'provider'     => $this->name(),
				'reference_id' => isset( $result['id'] ) ? (string) $result['id'] : '',
				'title'        => $album,
				'artist'       => $artist,
				'album'        => $album,
				'year'         => isset( $result['year'] ) ? (string) $result['year'] : '',
				'genres'       => array_merge(
					$this->entities( isset( $result['genre'] ) && is_array( $result['genre'] ) ? $result['genre'] : array() ),
					$this->entities( isset( $result['style'] ) && is_array( $result['style'] ) ? $result['style'] : array() )
				),
				'labels'       => $this->entities( isset( $result['label'] ) && is_array( $result['label'] ) ? $result['label'] : array() ),
				'cover_url'    => $cover,
				'cover_mime'   => '' !== $cover ? 'image/jpeg' : '',
				'source_url'   => $source_url,
			)
		);
	}

	/**
	 * @return array<int|string, mixed>|\WP_Error
	 */
	private function request( string $url ) {
		return wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'MusicWave/' . ( defined( 'MUSIC_WAVE_CORE_VERSION' ) ? MUSIC_WAVE_CORE_VERSION : '1.0' ),
				'headers'    => array(
					'Authorization' => 'Discogs token=' . $this->token,
				),
			)
		);
	}

	/**
	 * @param array<int, mixed> $items Discogs entity objects or names.
	 * @return array<int, array<string, string>>
	 */
	private function entities( array $items ): array {
		$entities = array();
		foreach ( $items as $item ) {
			if ( is_scalar( $item ) ) {
				$item = array( 'name' => (string) $item );
			}
			if ( ! is_array( $item ) || empty( $item['name'] ) ) {
				continue;
			}
			$entities[] = array(
				'name'        => (string) $item['name'],
				'external_id' => isset( $item['id'] ) ? (string) $item['id'] : '',
			);
		}

		return $entities;
	}

	private function clean_notes( string $notes ): string {
		$notes = (string) preg_replace( '/\[url=[^\]]+\](.*?)\[\/url\]/is', '$1', $notes );
		$notes = (string) preg_replace( '/\[(?:a|l|r)=([^\]]+)\]/i', '$1', $notes );

		return (string) preg_replace( '/\[\/?[a-z][^\]]*\]/i', '', $notes );
	}
}
