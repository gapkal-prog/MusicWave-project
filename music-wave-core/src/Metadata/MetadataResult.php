<?php
/**
 * One normalized music metadata suggestion.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Metadata;

final class MetadataResult {
	/** @var string */
	public $provider = '';

	/** @var string */
	public $reference_id = '';

	/** @var string Provider entity kind (track, album, playlist, show, or episode). */
	public $entity_type = '';

	/** @var string */
	public $title = '';

	/** @var string */
	public $artist = '';

	/** @var string */
	public $album = '';

	/** @var string 4-digit release year. */
	public $year = '';

	/** @var string ISO release date (YYYY-MM-DD) when the provider gives one. */
	public $release_date = '';

	/** @var string */
	public $genre = '';

	/** @var int Duration in seconds (0 when unknown). */
	public $duration = 0;

	/** @var string International Standard Recording Code when known. */
	public $isrc = '';

	/** @var string */
	public $cover_url = '';

	/** @var string */
	public $cover_mime = '';

	/** @var string */
	public $source_url = '';

	/** @var string Sanitized release description or factual annotation. */
	public $description = '';

	/** @var array<int, array<string, mixed>> Structured artist identities. */
	public $artists = array();

	/** @var array<int, array<string, mixed>> Structured genre identities. */
	public $genres = array();

	/** @var array<int, array<string, mixed>> Structured label identities. */
	public $labels = array();

	/** @var array<int, array<string, mixed>> Explicit or confidently classified moods. */
	public $moods = array();

	/** @var array<int, array<string, mixed>> Unclassified provider tags; never auto-created as terms. */
	public $tags = array();

	/**
	 * Normalize provider-specific payloads into one result.
	 *
	 * @param array<string, mixed> $data Provider payload.
	 */
	public static function from_array( array $data ): self {
		$result               = new self();
		$result->provider     = isset( $data['provider'] ) ? sanitize_key( (string) $data['provider'] ) : '';
		$result->reference_id = isset( $data['reference_id'] ) ? sanitize_text_field( (string) $data['reference_id'] ) : '';
		$result->entity_type  = isset( $data['entity_type'] ) ? sanitize_key( (string) $data['entity_type'] ) : '';
		$result->title        = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		$result->artist       = isset( $data['artist'] ) ? sanitize_text_field( (string) $data['artist'] ) : '';
		$result->album        = isset( $data['album'] ) ? sanitize_text_field( (string) $data['album'] ) : '';
		$raw_release_date     = isset( $data['release_date'] ) ? (string) $data['release_date'] : '';
		$result->release_date = self::normalize_date( $raw_release_date );
		$result->genre        = isset( $data['genre'] ) ? sanitize_text_field( (string) $data['genre'] ) : '';
		$result->duration     = isset( $data['duration'] ) ? self::normalize_duration( $data['duration'] ) : 0;
		$result->isrc         = isset( $data['isrc'] ) ? strtoupper( self::clean_alnum( (string) $data['isrc'] ) ) : '';
		$result->cover_url    = isset( $data['cover_url'] ) ? self::normalize_cover_url( (string) $data['cover_url'] ) : '';
		$result->cover_mime   = isset( $data['cover_mime'] ) ? sanitize_text_field( (string) $data['cover_mime'] ) : '';
		$result->source_url   = isset( $data['source_url'] ) ? (string) esc_url_raw( (string) $data['source_url'] ) : '';
		$result->description  = isset( $data['description'] ) ? self::normalize_description( $data['description'] ) : '';
		$result->artists      = isset( $data['artists'] ) ? self::normalize_entities( $data['artists'] ) : array();
		$result->genres       = isset( $data['genres'] ) ? self::normalize_entities( $data['genres'] ) : array();
		$result->labels       = isset( $data['labels'] ) ? self::normalize_entities( $data['labels'] ) : array();
		$result->moods        = isset( $data['moods'] ) ? self::normalize_entities( $data['moods'] ) : array();
		$result->tags         = isset( $data['tags'] ) ? self::normalize_entities( $data['tags'] ) : array();

		if ( empty( $result->artists ) && '' !== $result->artist ) {
			$result->artists = self::entities_from_names( $result->artist );
		}
		if ( empty( $result->genres ) && '' !== $result->genre ) {
			$result->genres = self::entities_from_names( $result->genre );
		}
		if ( '' === $result->artist && ! empty( $result->artists ) ) {
			$result->artist = implode( ', ', array_column( $result->artists, 'name' ) );
		}
		if ( '' === $result->genre && ! empty( $result->genres ) ) {
			$result->genre = implode( ', ', array_column( $result->genres, 'name' ) );
		}

		// Prefer an explicit year, else derive one from the release date.
		$year         = isset( $data['year'] ) ? self::normalize_year( (string) $data['year'] ) : '';
		$result->year = '' !== $year ? $year : self::normalize_year( $raw_release_date );

		return $result;
	}

	/**
	 * Whether this result exposes the minimum metadata the editor can use.
	 */
	public function is_usable(): bool {
		return '' !== $this->title && ( '' !== $this->artist || '' !== $this->album );
	}

	/**
	 * Full ISO date to write into release meta.
	 */
	public function date_for_meta(): string {
		if ( '' !== $this->release_date ) {
			return $this->release_date;
		}

		return '';
	}

	/**
	 * Return a REST-safe response payload.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'provider'     => $this->provider,
			'reference_id' => $this->reference_id,
			'entity_type'  => $this->entity_type,
			'title'        => $this->title,
			'artist'       => $this->artist,
			'album'        => $this->album,
			'year'         => $this->year,
			'release_date' => $this->release_date,
			'genre'        => $this->genre,
			'duration'     => $this->duration,
			'isrc'         => $this->isrc,
			'cover_url'    => $this->cover_url,
			'cover_mime'   => $this->cover_mime,
			'source_url'   => $this->source_url,
			'description'  => $this->description,
			'artists'      => $this->artists,
			'genres'       => $this->genres,
			'labels'       => $this->labels,
			'moods'        => $this->moods,
			'tags'         => $this->tags,
		);
	}

	private static function normalize_year( string $value ): string {
		if ( preg_match( '/(\d{4})/', $value, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	private static function normalize_date( string $value ): string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
		}

		return '';
	}

	/**
	 * Normalize a provider duration that is already expressed in seconds.
	 *
	 * @param mixed $value Duration value.
	 */
	private static function normalize_duration( $value ): int {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return 0;
		}
		return max( 0, (int) $value );
	}

	private static function clean_alnum( string $value ): string {
		return (string) preg_replace( '/[^A-Za-z0-9]/', '', $value );
	}

	/**
	 * @param mixed $value Description value.
	 */
	private static function normalize_description( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( substr( wp_kses_post( (string) $value ), 0, 12000 ) );
	}

	/**
	 * @param mixed $value Provider entity list.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_entities( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$entities = array();
		$seen     = array();
		foreach ( array_slice( $value, 0, 20 ) as $entity ) {
			if ( is_scalar( $entity ) ) {
				$entity = array( 'name' => (string) $entity );
			}
			if ( ! is_array( $entity ) || empty( $entity['name'] ) || ! is_scalar( $entity['name'] ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) $entity['name'] );
			if ( '' === $name ) {
				continue;
			}
			$key = strtolower( $name );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$aliases      = isset( $entity['aliases'] ) && is_array( $entity['aliases'] )
				? array_values( array_filter( array_map( 'sanitize_text_field', $entity['aliases'] ) ) )
				: array();
			$entities[]   = array(
				'name'        => $name,
				'external_id' => isset( $entity['external_id'] ) && is_scalar( $entity['external_id'] ) ? sanitize_text_field( (string) $entity['external_id'] ) : '',
				'aliases'     => array_values( array_unique( $aliases ) ),
			);
			$seen[ $key ] = true;
		}

		return $entities;
	}

	/**
	 * Convert a legacy comma-separated field into structured entities.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function entities_from_names( string $value ): array {
		$names = preg_split( '/\s*,\s*/', $value );

		return self::normalize_entities( is_array( $names ) ? $names : array( $value ) );
	}

	/**
	 * Cover providers sometimes return HTTP links even though HTTPS is available.
	 */
	private static function normalize_cover_url( string $value ): string {
		$url = (string) esc_url_raw( $value );
		if ( 0 === stripos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		} elseif ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		return (string) esc_url_raw( $url, array( 'https' ) );
	}
}
