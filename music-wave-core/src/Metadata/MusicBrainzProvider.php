<?php
/**
 * Keyless MusicBrainz + Cover Art Archive provider (built-in default).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Metadata;

final class MusicBrainzProvider implements MetadataProvider, MetadataEnrichmentProvider {
	private const SEARCH_ENDPOINT         = 'https://musicbrainz.org/ws/2/recording/';
	private const RELEASE_SEARCH_ENDPOINT = 'https://musicbrainz.org/ws/2/release/';
	private const RELEASE_ENDPOINT        = 'https://musicbrainz.org/ws/2/release/';
	private const COVER_ENDPOINT          = 'https://coverartarchive.org/release/';

	/**
	 * Shared HTTP helper.
	 *
	 * @param string               $url     Absolute URL.
	 * @param array<string, mixed> $options Overrides.
	 * @return array<int|string, mixed>|\WP_Error
	 */
	private function request( string $url, array $options = array() ) {
		$defaults = array(
			'timeout'             => 10,
			'limit_response_size' => 1024 * 1024,
			'user-agent'          => 'MusicWave/' . ( defined( 'MUSIC_WAVE_CORE_VERSION' ) ? MUSIC_WAVE_CORE_VERSION : '1.0' ) . ' ( ' . home_url( '/' ) . ' )',
			'headers'             => array( 'Accept' => 'application/json' ),
		);

		return wp_remote_get( $url, array_merge( $defaults, $options ) );
	}

	/**
	 * Decode JSON, allowing a missing cover only when explicitly requested.
	 *
	 * @param array<int|string, mixed>|\WP_Error $response HTTP response.
	 * @return array<string, mixed>|null
	 * @throws RateLimitException When the MusicBrainz API reports a rate limit.
	 * @throws \RuntimeException When the request or payload is invalid.
	 */
	private function decode_json( $response, bool $allow_not_found = false ): ?array {
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'MusicBrainz connection failed.' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code || 503 === $code ) {
			throw new RateLimitException( 'MusicBrainz rate limit reached.' );
		}
		if ( $allow_not_found && 404 === $code ) {
			return null;
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'MusicBrainz HTTP request failed.' );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'MusicBrainz returned invalid JSON.' );
		}

		return $data;
	}

	public function name(): string {
		return 'musicbrainz';
	}

	public function is_enabled(): bool {
		return true; // Keyless: always available as the default fallback.
	}

	public function priority(): int {
		return 100;
	}

	public function search( MetadataQuery $query ): array {
		if ( ! $query->is_valid() || $query->is_podcast() || 'playlist' === $query->primary_release_type() ) {
			return array();
		}

		$release_search = in_array( $query->primary_release_type(), array( 'album', 'ep', 'mix' ), true );
		$lucene         = $release_search ? $this->release_lucene( $query ) : $this->lucene( $query );
		if ( '' === $lucene ) {
			return array();
		}

		$data = $this->decode_json(
			$this->request(
				add_query_arg(
					array(
						'query' => rawurlencode( $lucene ),
						'fmt'   => 'json',
						'limit' => 10,
					),
					$release_search ? self::RELEASE_SEARCH_ENDPOINT : self::SEARCH_ENDPOINT
				)
			)
		);

		$bucket = $release_search ? 'releases' : 'recordings';
		if ( null === $data || ! isset( $data[ $bucket ] ) || ! is_array( $data[ $bucket ] ) ) {
			throw new \RuntimeException( 'MusicBrainz returned an invalid search payload.' );
		}

		$results = array();
		foreach ( $data[ $bucket ] as $item ) {
			$result = $release_search
				? $this->normalize_release( is_array( $item ) ? $item : array() )
				: $this->normalize_recording( is_array( $item ) ? $item : array() );
			if ( $result->is_usable() ) {
				$results[] = $result;
			}
		}

		return $results;
	}

	private function release_lucene( MetadataQuery $query ): string {
		$parts = array();
		$title = '' !== $query->album ? $query->album : $query->track;
		if ( '' !== $title ) {
			$title_term = 'release:"' . self::escape_lucene( $title ) . '"';
			$parts[]    = '' !== $query->free_text && '' === $query->artist && '' === $query->album
				? '(' . $title_term . ' OR artist:"' . self::escape_lucene( $title ) . '")'
				: $title_term;
		}
		if ( '' !== $query->artist ) {
			$parts[] = 'artist:"' . self::escape_lucene( $query->artist ) . '"';
		}
		if ( '' !== $query->year ) {
			$parts[] = 'date:' . self::escape_lucene( $query->year ) . '*';
		}
		return implode( ' AND ', $parts );
	}

	/**
	 * Normalize a MusicBrainz release search result for album-like content.
	 *
	 * @param array<string, mixed> $release Release payload.
	 */
	private function normalize_release( array $release ): MetadataResult {
		$artists = $this->artist_entities( isset( $release['artist-credit'] ) && is_array( $release['artist-credit'] ) ? $release['artist-credit'] : array() );
		$genres  = array();
		if ( isset( $release['release-group']['genres'] ) && is_array( $release['release-group']['genres'] ) ) {
			$genres = $this->named_entities( $release['release-group']['genres'] );
		}
		return MetadataResult::from_array(
			array(
				'provider'     => $this->name(),
				'entity_type'  => 'album',
				'reference_id' => isset( $release['id'] ) ? (string) $release['id'] : '',
				'title'        => isset( $release['title'] ) ? (string) $release['title'] : '',
				'album'        => isset( $release['title'] ) ? (string) $release['title'] : '',
				'artist'       => implode( ', ', array_column( $artists, 'name' ) ),
				'artists'      => $artists,
				'genres'       => $genres,
				'release_date' => isset( $release['date'] ) ? (string) $release['date'] : '',
				'source_url'   => isset( $release['id'] ) ? 'https://musicbrainz.org/release/' . rawurlencode( (string) $release['id'] ) : '',
			)
		);
	}

	public function cover_for( MetadataResult $result ): string {
		if ( '' === $result->reference_id ) {
			return '';
		}

		$data = $this->decode_json( $this->request( trailingslashit( self::COVER_ENDPOINT ) . rawurlencode( $result->reference_id ) ), true );
		if ( null === $data || empty( $data['images'] ) || ! is_array( $data['images'] ) ) {
			return '';
		}

		$images = array_values(
			array_filter(
				$data['images'],
				static function ( $image ): bool {
					return is_array( $image );
				}
			)
		);
		usort(
			$images,
			static function ( array $left, array $right ): int {
				return (int) ! empty( $right['front'] ) <=> (int) ! empty( $left['front'] );
			}
		);

		foreach ( $images as $image ) {
			$thumbnails = isset( $image['thumbnails'] ) && is_array( $image['thumbnails'] ) ? $image['thumbnails'] : array();
			foreach ( array( '1200', '500', 'large' ) as $size ) {
				if ( ! empty( $thumbnails[ $size ] ) && is_string( $thumbnails[ $size ] ) ) {
					return self::secure_cover_url( $thumbnails[ $size ] );
				}
			}
			if ( ! empty( $image['image'] ) && is_string( $image['image'] ) ) {
				return self::secure_cover_url( $image['image'] );
			}
		}

		return '';
	}

	/**
	 * Fetch release annotations, stable artist IDs, genres, and labels.
	 */
	public function enrich( MetadataResult $result ): MetadataResult {
		if ( '' === $result->reference_id ) {
			return $result;
		}

		$data = $this->decode_json(
			$this->request(
				add_query_arg(
					array(
						'inc' => rawurlencode( 'artist-credits+labels+release-groups+genres+annotation' ),
						'fmt' => 'json',
					),
					trailingslashit( self::RELEASE_ENDPOINT ) . rawurlencode( $result->reference_id )
				)
			)
		);
		if ( null === $data ) {
			return $result;
		}

		$artists = $this->artist_entities( isset( $data['artist-credit'] ) && is_array( $data['artist-credit'] ) ? $data['artist-credit'] : array() );
		$genres  = $this->named_entities( isset( $data['genres'] ) && is_array( $data['genres'] ) ? $data['genres'] : array() );
		if ( empty( $genres ) && isset( $data['release-group']['genres'] ) && is_array( $data['release-group']['genres'] ) ) {
			$genres = $this->named_entities( $data['release-group']['genres'] );
		}

		$labels = array();
		if ( isset( $data['label-info'] ) && is_array( $data['label-info'] ) ) {
			foreach ( $data['label-info'] as $label_info ) {
				$label = is_array( $label_info ) && isset( $label_info['label'] ) && is_array( $label_info['label'] ) ? $label_info['label'] : array();
				if ( empty( $label['name'] ) ) {
					continue;
				}
				$labels[] = array(
					'name'        => (string) $label['name'],
					'external_id' => isset( $label['id'] ) ? (string) $label['id'] : '',
				);
			}
		}

		return MetadataResult::from_array(
			array_merge(
				$result->to_array(),
				array(
					'description' => isset( $data['annotation'] ) && is_scalar( $data['annotation'] ) ? (string) $data['annotation'] : $result->description,
					'artists'     => ! empty( $artists ) ? $artists : $result->artists,
					'genres'      => ! empty( $genres ) ? $genres : $result->genres,
					'labels'      => ! empty( $labels ) ? $labels : $result->labels,
				)
			)
		);
	}

	/**
	 * Build a Lucene query from the sanitized request fields.
	 *
	 * Empty artist terms are ignored so title-only searches still match. Adding
	 * a year filters on first release or track release date in MusicBrainz.
	 */
	private function lucene( MetadataQuery $query ): string {
		$parts = array();
		if ( '' !== $query->track ) {
			$title_term = 'recording:"' . self::escape_lucene( $query->track ) . '"';
			$parts[]    = '' !== $query->free_text && '' === $query->artist
				? '(' . $title_term . ' OR artist:"' . self::escape_lucene( $query->track ) . '")'
				: $title_term;
		}
		if ( '' !== $query->artist ) {
			$parts[] = 'artistname:"' . self::escape_lucene( $query->artist ) . '"';
		}
		if ( '' !== $query->album ) {
			$parts[] = 'release:"' . self::escape_lucene( $query->album ) . '"';
		}
		if ( '' !== $query->year ) {
			// MusicBrainz exposes firstreleasedate as YYYY; combine sensibly.
			$parts[] = 'firstreleasedate:' . self::escape_lucene( $query->year ) . '*';
		}

		return implode( ' AND ', $parts );
	}

	private static function escape_lucene( string $value ): string {
		return (string) preg_replace( '/(["\\\\])/', '\\\\$1', $value );
	}

	/**
	 * Normalize one MusicBrainz recording into a MetadataResult.
	 *
	 * @param array<string, mixed> $recording Recording payload.
	 */
	private function normalize_recording( array $recording ): MetadataResult {
		$artist  = '';
		$artists = array();
		if ( ! empty( $recording['artist-credit'] ) && is_array( $recording['artist-credit'] ) ) {
			$artists = $this->artist_entities( $recording['artist-credit'] );
			$artist  = implode( ', ', array_column( $artists, 'name' ) );
		}

		$album      = '';
		$date       = '';
		$release_id = '';
		if ( ! empty( $recording['releases'] ) && is_array( $recording['releases'] ) ) {
			$releases = array_values(
				array_filter(
					$recording['releases'],
					static function ( $release ): bool {
						return is_array( $release ) && ! empty( $release['id'] ) && ! empty( $release['title'] );
					}
				)
			);
			usort(
				$releases,
				static function ( array $left, array $right ): int {
					return self::release_score( $right ) <=> self::release_score( $left );
				}
			);
			if ( ! empty( $releases ) ) {
				$release    = $releases[0];
				$album      = (string) $release['title'];
				$release_id = (string) $release['id'];
				$date       = ! empty( $release['date'] ) ? (string) $release['date'] : '';
			}
		}

		$genres = $this->named_entities( isset( $recording['genres'] ) && is_array( $recording['genres'] ) ? $recording['genres'] : array() );
		$genre  = implode( ', ', array_column( $genres, 'name' ) );
		$moods  = array();
		$tags   = array();
		if ( ! empty( $recording['tags'] ) && is_array( $recording['tags'] ) ) {
			usort(
				$recording['tags'],
				static function ( $a, $b ): int {
					$ac = is_array( $a ) && isset( $a['count'] ) ? (int) $a['count'] : 0;
					$bc = is_array( $b ) && isset( $b['count'] ) ? (int) $b['count'] : 0;

					return $bc <=> $ac;
				}
			);
			$tags = $this->named_entities( array_slice( $recording['tags'], 0, 10 ) );
			foreach ( $tags as $tag ) {
				if ( $this->is_known_mood( (string) $tag['name'] ) ) {
					$moods[] = $tag;
				}
			}
		}

		$duration = 0;
		if ( ! empty( $recording['length'] ) && is_numeric( $recording['length'] ) ) {
			$duration = (int) round( ( (int) $recording['length'] ) / 1000 ); // MusicBrainz length is ms.
		}

		$isrc = '';
		if ( ! empty( $recording['isrcs'] ) && is_array( $recording['isrcs'] ) && ! empty( $recording['isrcs'][0] ) ) {
			$isrc = (string) $recording['isrcs'][0];
		}

		return MetadataResult::from_array(
			array(
				'provider'     => $this->name(),
				'entity_type'  => 'track',
				'reference_id' => $release_id,
				'title'        => isset( $recording['title'] ) ? (string) $recording['title'] : '',
				'artist'       => $artist,
				'album'        => $album,
				'release_date' => $date,
				'genre'        => $genre,
				'artists'      => $artists,
				'genres'       => $genres,
				'moods'        => $moods,
				'tags'         => $tags,
				'duration'     => $duration,
				'isrc'         => $isrc,
				'source_url'   => ! empty( $recording['id'] ) ? 'https://musicbrainz.org/recording/' . rawurlencode( (string) $recording['id'] ) : '',
			)
		);
	}

	/**
	 * Only promote a conservative vocabulary to Mood. Every other tag remains
	 * resolve-only, so translated/aliased existing moods can still match.
	 */
	private function is_known_mood( string $name ): bool {
		$key = strtolower( trim( $name ) );
		$key = (string) preg_replace( '/[\s_-]+/', ' ', $key );
		return in_array(
			$key,
			array(
				'aggressive',
				'angry',
				'atmospheric',
				'calm',
				'chill',
				'dark',
				'dreamy',
				'emotional',
				'energetic',
				'epic',
				'euphoric',
				'happy',
				'hopeful',
				'melancholic',
				'melancholy',
				'mellow',
				'peaceful',
				'relaxing',
				'romantic',
				'sad',
				'sentimental',
				'uplifting',
			),
			true
		);
	}

	/**
	 * Prefer releases that can actually provide a front cover, then official dated releases.
	 *
	 * @param array<string, mixed> $release MusicBrainz release payload.
	 */
	private static function release_score( array $release ): int {
		$score     = 0;
		$cover_art = isset( $release['cover-art-archive'] ) && is_array( $release['cover-art-archive'] ) ? $release['cover-art-archive'] : array();
		if ( ! empty( $cover_art['front'] ) ) {
			$score += 1000;
		} elseif ( ! empty( $cover_art['artwork'] ) || ! empty( $cover_art['count'] ) ) {
			$score += 500;
		}
		if ( isset( $release['status'] ) && 'official' === strtolower( (string) $release['status'] ) ) {
			$score += 100;
		}
		if ( ! empty( $release['date'] ) ) {
			$score += 20;
		}

		return $score;
	}

	/**
	 * Normalize legacy HTTP URLs returned by Cover Art Archive metadata.
	 */
	private static function secure_cover_url( string $url ): string {
		$url = (string) esc_url_raw( $url );
		if ( 0 === stripos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		} elseif ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		return (string) esc_url_raw( $url, array( 'https' ) );
	}

	/**
	 * @param array<int, mixed> $credits MusicBrainz artist credits.
	 * @return array<int, array<string, string>>
	 */
	private function artist_entities( array $credits ): array {
		$artists = array();
		foreach ( $credits as $credit ) {
			if ( ! is_array( $credit ) ) {
				continue;
			}
			$artist = isset( $credit['artist'] ) && is_array( $credit['artist'] ) ? $credit['artist'] : array();
			$name   = isset( $credit['name'] ) && is_scalar( $credit['name'] ) ? (string) $credit['name'] : ( isset( $artist['name'] ) ? (string) $artist['name'] : '' );
			if ( '' === $name ) {
				continue;
			}
			$artists[] = array(
				'name'        => $name,
				'external_id' => isset( $artist['id'] ) ? (string) $artist['id'] : '',
			);
		}

		return $artists;
	}

	/**
	 * @param array<int, mixed> $items MusicBrainz named entities or tags.
	 * @return array<int, array<string, string>>
	 */
	private function named_entities( array $items ): array {
		$entities = array();
		foreach ( $items as $item ) {
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
}
