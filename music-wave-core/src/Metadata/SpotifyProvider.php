<?php
/**
 * Optional keyed Spotify Web API provider.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Metadata;

final class SpotifyProvider implements MetadataProvider, MetadataEnrichmentProvider {
	private const TOKEN_ENDPOINT  = 'https://accounts.spotify.com/api/token';
	private const SEARCH_ENDPOINT = 'https://api.spotify.com/v1/search';
	private const API_ENDPOINT    = 'https://api.spotify.com/v1/';
	private const TOKEN_TRANSIENT = 'mw_meta_spotify_token';

	/** @var string */
	private $client_id;

	/** @var string */
	private $client_secret;

	public function __construct( string $client_id = '', string $client_secret = '' ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
	}

	public function name(): string {
		return 'spotify';
	}

	public function is_enabled(): bool {
		return '' !== $this->client_id && '' !== $this->client_secret;
	}

	public function priority(): int {
		return 10;
	}

	public function search( MetadataQuery $query ): array {
		$token = $this->access_token();
		if ( '' === $token ) {
			throw new \RuntimeException( 'Spotify authentication failed.' );
		}

		$entity_type = $this->search_type( $query );
		$q           = $this->build_query( $query, $entity_type );
		if ( '' === $q ) {
			return array();
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'q'     => rawurlencode( $q ),
					'type'  => $entity_type,
					'limit' => 10,
				),
				self::SEARCH_ENDPOINT
			),
			array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Spotify connection failed.' );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			throw new RateLimitException( 'Spotify rate limit reached.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( 'Spotify search failed.' );
		}

		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$bucket = $entity_type . 's';
		if ( ! is_array( $data ) || ! isset( $data[ $bucket ]['items'] ) || ! is_array( $data[ $bucket ]['items'] ) ) {
			throw new \RuntimeException( 'Spotify returned an invalid search payload.' );
		}
		$items = is_array( $data ) && isset( $data[ $bucket ]['items'] ) && is_array( $data[ $bucket ]['items'] ) ? $data[ $bucket ]['items'] : array();

		$results = array();
		foreach ( $items as $item ) {
			$result = $this->normalize_item( is_array( $item ) ? $item : array(), $entity_type );
			if ( $result->is_usable() ) {
				$results[] = $result;
			}
		}

		return $results;
	}

	public function cover_for( MetadataResult $result ): string {
		return $result->cover_url; // Spotify returns cover art inline.
	}

	/**
	 * Fetch stable artist identities, artist genres, and the album label.
	 */
	public function enrich( MetadataResult $result ): MetadataResult {
		$token = $this->access_token();
		if ( '' === $token || '' === $result->reference_id ) {
			return $result;
		}

		$entity_type = in_array( $result->entity_type, array( 'track', 'album', 'playlist', 'show', 'episode' ), true ) ? $result->entity_type : 'track';
		$detail      = $this->get_json( self::API_ENDPOINT . $entity_type . 's/' . rawurlencode( $result->reference_id ), $token );
		if ( null === $detail ) {
			return $result;
		}
		if ( in_array( $entity_type, array( 'show', 'episode', 'playlist' ), true ) ) {
			return $this->enrich_spoken_or_playlist( $result, $detail, $entity_type );
		}

		$artists      = array();
		$genres       = array();
		$artist_items = isset( $detail['artists'] ) && is_array( $detail['artists'] ) ? array_slice( $detail['artists'], 0, 5 ) : array();
		foreach ( $artist_items as $artist ) {
			if ( ! is_array( $artist ) || empty( $artist['name'] ) ) {
				continue;
			}
			$artist_id = isset( $artist['id'] ) ? (string) $artist['id'] : '';
			$artists[] = array(
				'name'        => (string) $artist['name'],
				'external_id' => $artist_id,
			);
			if ( '' === $artist_id ) {
				continue;
			}
			$artist_detail = $this->get_json( self::API_ENDPOINT . 'artists/' . rawurlencode( $artist_id ), $token );
			if ( null !== $artist_detail && isset( $artist_detail['genres'] ) && is_array( $artist_detail['genres'] ) ) {
				foreach ( $artist_detail['genres'] as $genre ) {
					if ( is_scalar( $genre ) ) {
						$genres[] = array(
							'name'        => (string) $genre,
							'external_id' => '',
						);
					}
				}
			}
		}

		$labels   = array();
		$album_id = 'album' === $entity_type && isset( $detail['id'] ) ? (string) $detail['id'] : ( isset( $detail['album']['id'] ) ? (string) $detail['album']['id'] : '' );
		if ( '' !== $album_id && 'album' !== $entity_type ) {
			$album = $this->get_json( self::API_ENDPOINT . 'albums/' . rawurlencode( $album_id ), $token );
			if ( null !== $album && ! empty( $album['label'] ) && is_scalar( $album['label'] ) ) {
				$labels[] = array(
					'name'        => (string) $album['label'],
					'external_id' => '',
				);
			}
		} elseif ( 'album' === $entity_type && ! empty( $detail['label'] ) && is_scalar( $detail['label'] ) ) {
			$labels[] = array(
				'name'        => (string) $detail['label'],
				'external_id' => '',
			);
		}

		return MetadataResult::from_array(
			array_merge(
				$result->to_array(),
				array(
					'artists' => ! empty( $artists ) ? $artists : $result->artists,
					'genres'  => ! empty( $genres ) ? $genres : $result->genres,
					'labels'  => ! empty( $labels ) ? $labels : $result->labels,
				)
			)
		);
	}

	private function search_type( MetadataQuery $query ): string {
		$types        = array(
			'album'           => 'album',
			'ep'              => 'album',
			'mix'             => 'album',
			'playlist'        => 'playlist',
			'podcast_show'    => 'show',
			'podcast_episode' => 'episode',
		);
		$release_type = $query->primary_release_type();
		return isset( $types[ $release_type ] ) ? $types[ $release_type ] : 'track';
	}

	/**
	 * Normalize a result according to the Spotify search entity.
	 *
	 * @param array<string, mixed> $item Spotify payload.
	 */
	private function normalize_item( array $item, string $entity_type ): MetadataResult {
		if ( 'track' === $entity_type ) {
			return $this->normalize_track( $item );
		}

		$images    = isset( $item['images'] ) && is_array( $item['images'] ) ? $item['images'] : array();
		$cover_url = $this->first_image( $images );
		$artists   = isset( $item['artists'] ) && is_array( $item['artists'] ) ? $item['artists'] : array();
		$owner     = '';
		if ( 'playlist' === $entity_type && isset( $item['owner']['display_name'] ) ) {
			$owner = (string) $item['owner']['display_name'];
		} elseif ( in_array( $entity_type, array( 'show', 'episode' ), true ) && isset( $item['publisher'] ) ) {
			$owner = (string) $item['publisher'];
		}
		if ( 'episode' === $entity_type && isset( $item['show']['publisher'] ) ) {
			$owner = (string) $item['show']['publisher'];
		}

		return MetadataResult::from_array(
			array(
				'provider'     => $this->name(),
				'entity_type'  => $entity_type,
				'reference_id' => isset( $item['id'] ) ? (string) $item['id'] : '',
				'title'        => isset( $item['name'] ) ? (string) $item['name'] : '',
				'artist'       => '' !== $owner ? $owner : implode( ', ', array_column( $artists, 'name' ) ),
				'artists'      => 'album' === $entity_type ? $artists : array(),
				'album'        => 'episode' === $entity_type && isset( $item['show']['name'] ) ? (string) $item['show']['name'] : ( isset( $item['name'] ) ? (string) $item['name'] : '' ),
				'release_date' => isset( $item['release_date'] ) ? (string) $item['release_date'] : '',
				'duration'     => isset( $item['duration_ms'] ) && is_numeric( $item['duration_ms'] ) ? (int) round( (int) $item['duration_ms'] / 1000 ) : 0,
				'cover_url'    => $cover_url,
				'description'  => isset( $item['description'] ) ? (string) $item['description'] : '',
				'source_url'   => isset( $item['external_urls']['spotify'] ) ? (string) $item['external_urls']['spotify'] : '',
			)
		);
	}

	/**
	 * Preserve official Spotify descriptions for podcasts/playlists.
	 *
	 * @param array<string, mixed> $detail Spotify detail payload.
	 */
	private function enrich_spoken_or_playlist( MetadataResult $result, array $detail, string $entity_type ): MetadataResult {
		$description = isset( $detail['html_description'] ) && is_scalar( $detail['html_description'] )
			? (string) $detail['html_description']
			: ( isset( $detail['description'] ) && is_scalar( $detail['description'] ) ? (string) $detail['description'] : $result->description );
		$publisher   = '';
		if ( isset( $detail['publisher'] ) ) {
			$publisher = (string) $detail['publisher'];
		} elseif ( isset( $detail['show']['publisher'] ) ) {
			$publisher = (string) $detail['show']['publisher'];
		} elseif ( isset( $detail['owner']['display_name'] ) ) {
			$publisher = (string) $detail['owner']['display_name'];
		}
		$artists = '' !== $publisher ? array(
			array(
				'name'        => $publisher,
				'external_id' => '',
			),
		) : $result->artists;
		return MetadataResult::from_array(
			array_merge(
				$result->to_array(),
				array(
					'entity_type' => $entity_type,
					'description' => $description,
					'artists'     => $artists,
				)
			)
		);
	}

	/** @param array<int, mixed> $images */
	private function first_image( array $images ): string {
		foreach ( $images as $image ) {
			if ( is_array( $image ) && ! empty( $image['url'] ) ) {
				return (string) $image['url'];
			}
		}
		return '';
	}

	/**
	 * Build a Spotify search query using field qualifiers when present.
	 *
	 * Spotify supports track:, artist:, album:, year: qualifiers, which are far
	 * more precise than a bare keyword string. Falls back to free text.
	 */
	private function build_query( MetadataQuery $query, string $entity_type = 'track' ): string {
		$parts = array();
		if ( 'track' === $entity_type && '' !== $query->track ) {
			$parts[] = 'track:' . $query->track;
		} elseif ( 'album' === $entity_type && '' !== $query->track && '' === $query->album ) {
			$parts[] = 'album:' . $query->track;
		} elseif ( '' !== $query->track ) {
			$parts[] = $query->track;
		}
		if ( in_array( $entity_type, array( 'track', 'album' ), true ) && '' !== $query->artist ) {
			$parts[] = 'artist:' . $query->artist;
		} elseif ( '' !== $query->artist ) {
			$parts[] = $query->artist;
		}
		if ( in_array( $entity_type, array( 'track', 'album' ), true ) && '' !== $query->album ) {
			$parts[] = 'album:' . $query->album;
		} elseif ( '' !== $query->album ) {
			$parts[] = $query->album;
		}
		if ( in_array( $entity_type, array( 'track', 'album' ), true ) && '' !== $query->year ) {
			$parts[] = 'year:' . $query->year;
		}

		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Fetch + cache a client-credentials access token.
	 */
	private function access_token(): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$token_key = self::TOKEN_TRANSIENT . '_' . md5( $this->client_id . ':' . $this->client_secret );
		$cached    = get_transient( $token_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array(
					// Spotify's client-credentials flow requires a Basic header with base64-encoded credentials.
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return '';
		}

		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) && ! empty( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' !== $token ) {
			$ttl = is_array( $data ) && ! empty( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 60 ) : 3300;
			set_transient( $token_key, $token, $ttl );
		}

		return $token;
	}

	/**
	 * Normalize one Spotify track payload.
	 *
	 * @param array<string, mixed> $track Track payload.
	 */
	private function normalize_track( array $track ): MetadataResult {
		$artists = array();
		if ( ! empty( $track['artists'] ) && is_array( $track['artists'] ) ) {
			foreach ( $track['artists'] as $artist ) {
				if ( is_array( $artist ) && ! empty( $artist['name'] ) ) {
					$artists[] = (string) $artist['name'];
				}
			}
		}

		$cover_url = '';
		if ( ! empty( $track['album']['images'] ) && is_array( $track['album']['images'] ) ) {
			$first = reset( $track['album']['images'] );
			if ( is_array( $first ) && ! empty( $first['url'] ) ) {
				$cover_url = (string) $first['url'];
			}
		}

		return MetadataResult::from_array(
			array(
				'provider'     => $this->name(),
				'entity_type'  => 'track',
				'reference_id' => isset( $track['id'] ) ? (string) $track['id'] : '',
				'title'        => isset( $track['name'] ) ? (string) $track['name'] : '',
				'artist'       => implode( ', ', $artists ),
				'artists'      => array_map(
					static function ( array $artist ): array {
						return array(
							'name'        => isset( $artist['name'] ) ? (string) $artist['name'] : '',
							'external_id' => isset( $artist['id'] ) ? (string) $artist['id'] : '',
						);
					},
					isset( $track['artists'] ) && is_array( $track['artists'] ) ? $track['artists'] : array()
				),
				'album'        => isset( $track['album']['name'] ) ? (string) $track['album']['name'] : '',
				'release_date' => isset( $track['album']['release_date'] ) ? (string) $track['album']['release_date'] : '',
				'duration'     => isset( $track['duration_ms'] ) && is_numeric( $track['duration_ms'] ) ? (int) round( (int) $track['duration_ms'] / 1000 ) : 0,
				'isrc'         => isset( $track['external_ids']['isrc'] ) ? (string) $track['external_ids']['isrc'] : '',
				'cover_url'    => $cover_url,
				'cover_mime'   => '' !== $cover_url ? 'image/jpeg' : '',
				'source_url'   => isset( $track['external_urls']['spotify'] ) ? (string) $track['external_urls']['spotify'] : '',
			)
		);
	}

	/**
	 * @return array<string, mixed>|null
	 * @throws RateLimitException When the Spotify API reports a rate limit.
	 */
	private function get_json( string $url, string $token ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			throw new RateLimitException( 'Spotify rate limit reached.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}
}
