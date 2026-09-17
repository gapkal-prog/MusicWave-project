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
				'message' => __( 'عنوان قطعه یا نام هنرمند را برای جست‌وجو وارد کنید.', 'music-wave-core' ),
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
		$errors   = array();
		foreach ( $this->enabled() as $provider ) {
			$attempts[] = $provider->name();
			try {
				$results = $provider->search( $query );
			} catch ( RateLimitException $e ) {
				$errors[ $provider->name() ] = 'rate_limited';
				continue; // Fall through to the next provider in the chain.
			} catch ( \Throwable $e ) {
				$errors[ $provider->name() ] = 'unavailable';
				continue;
			}

			if ( empty( $results ) ) {
				continue;
			}

			$results = array_slice( array_values( $results ), 0, $limit );
			// Cover Art Archive is resolved only for the selected result, not N times per search.

			$payload = array(
				'success'  => true,
				'cached'   => false,
				'provider' => $provider->name(),
				'label'    => $this->provider_label( $provider->name() ),
				'attempts' => $attempts,
				'errors'   => $errors,
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

		if ( ! empty( $errors ) ) {
			return array(
				'success'  => false,
				'cached'   => false,
				'code'     => 'provider_unavailable',
				'attempts' => $attempts,
				'errors'   => $errors,
				'message'  => __( 'سرویس فراداده در دسترس نیست یا درخواست‌ها محدود شده‌اند. اتصال خروجی HTTPS و تنظیمات سرویس را بررسی کنید و کمی بعد دوباره امتحان کنید.', 'music-wave-core' ),
				'results'  => array(),
			);
		}

		return array(
			'success'  => false,
			'cached'   => false,
			'code'     => 'no_results',
			'attempts' => $attempts,
			'message'  => __( 'هیچ منطبقی یافت نشد. عنوان یا املای هنرمند دیگری را امتحان کنید.', 'music-wave-core' ),
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

		$cache_key = 'mw_meta_detail_v3_' . md5( wp_json_encode( array( $result->to_array(), $release_types ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $this->with_factual_description( MetadataResult::from_array( array_merge( $result->to_array(), $cached ) ), $release_types );
		}

		foreach ( $this->enabled() as $provider ) {
			if ( $provider->name() !== $result->provider ) {
				continue;
			}
			// Artwork failure must not discard usable descriptive metadata.
			if ( '' === $result->cover_url ) {
				try {
					$result->cover_url = $provider->cover_for( $result );
				} catch ( \Throwable $e ) {
					$result->cover_url = '';
				}
			}
			try {
				$enriched = $provider instanceof MetadataEnrichmentProvider ? $provider->enrich( $result ) : $result;
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
			return new \WP_Error( 'invalid_cover', __( 'جلد URL نامعتبر است.', 'music-wave-core' ) );
		}

		/**
		 * Filter the download budgets for imported covers.
		 *
		 * Bounded import: explicit HTTP timeout, byte cap, and pixel cap so a
		 * hostile or misconfigured metadata source cannot exhaust the worker
		 * (PROJECT_PLAN.md Stage 3 deliverable 2).
		 *
		 * @param array<string, int> $budgets timeout (s), max_bytes, max_pixels (per side).
		 */
		$budgets = apply_filters(
			'music_wave_cover_import_budgets',
			array(
				'timeout'    => 15,
				'max_bytes'  => 10 * 1024 * 1024,
				'max_pixels' => 5000,
			)
		);

		$budgets   = is_array( $budgets ) ? $budgets : array();
		$max_bytes = isset( $budgets['max_bytes'] ) ? max( 1, min( 20 * 1024 * 1024, (int) $budgets['max_bytes'] ) ) : 10 * 1024 * 1024;
		$tmp       = wp_tempnam( 'musicwave-cover' );
		if ( ! $tmp ) {
			return new \WP_Error( 'cover_temp_failed', __( 'ایجاد فایل موقت جلد ممکن نیست.', 'music-wave-core' ) );
		}

		// Enforce the byte budget DURING streaming, not after an unlimited download.
		// WordPress safe HTTP also validates the destination and redirect targets.
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => isset( $budgets['timeout'] ) ? max( 5, min( 30, (int) $budgets['timeout'] ) ) : 15,
				'redirection'         => 3,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => $max_bytes + 1,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'cover_download_failed', __( 'دریافت جلد از سرویس خارجی ممکن نیست؛ اتصال HTTPS یا وجود تصویر را بررسی کنید.', 'music-wave-core' ) );
		}

		$bytes = filesize( $tmp );
		if ( false === $bytes || $bytes < 1 || $bytes > $max_bytes ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'cover_too_large', __( 'اندازهٔ جلد دانلودشده از حد تعیین‌شده بیشتر است.', 'music-wave-core' ) );
		}

		$dimensions = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- probing untrusted bytes; failures handled below.
		$max_pixels = isset( $budgets['max_pixels'] ) ? max( 1, min( 5000, (int) $budgets['max_pixels'] ) ) : 5000;
		if ( ! is_array( $dimensions ) || ! isset( $dimensions[0], $dimensions[1] ) || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > $max_pixels || $dimensions[1] > $max_pixels ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'invalid_cover_dimensions', __( 'جلد دانلودشده تصویری قابل رمزگشایی در محدودهٔ پیکسلی تعیین‌شده نیست.', 'music-wave-core' ) );
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
			wp_delete_file( $tmp );
			return new \WP_Error( 'invalid_cover_type', __( 'جلد دانلود شده یک تصویر پشتیبانی نمی‌شود.', 'music-wave-core' ) );
		}

		$title    = '' !== $title ? $title : __( 'جلد وارداتی', 'music-wave-core' );
		$basename = 'music-cover-' . substr( md5( $url ), 0, 12 ) . '.' . $extensions[ $mime ];
		$file     = array(
			'name'     => sanitize_file_name( $basename ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
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
			'discogs'     => __( 'دیسکاگ', 'music-wave-core' ),
			'musicbrainz' => __( 'MusicBrainz + آرشیو Cover Art', 'music-wave-core' ),
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
		return 'mw_meta_v5_' . md5( wp_json_encode( array( array_map( static function ( MetadataProvider $provider ): string { return $provider->name(); }, $this->enabled() ), $query->free_text, $query->track, $query->artist, $query->album, $query->year, $query->release_types, $limit ) ) );
	}

	/**
	 * Supply a localized factual description when the provider has no annotation.
	 */
	private function with_factual_description( MetadataResult $result, array $release_types = array() ): MetadataResult {
		if ( '' !== trim( wp_strip_all_tags( $result->description ) ) ) {
			return $result;
		}

		$type      = isset( $release_types[0] ) ? sanitize_key( (string) $release_types[0] ) : 'track';
		$artist    = '' !== $result->artist ? $result->artist : __( 'هنرمند ناشناس', 'music-wave-core' );
		$templates = array(
			'track'           => /* translators: 1: release title, 2: artist name. */ __( '%1$s قطعه‌ای از %2$s است.', 'music-wave-core' ),
			'single'          => /* translators: 1: release title, 2: artist name. */ __( '%1$s تک آهنگی از %2$s است.', 'music-wave-core' ),
			'ep'              => /* translators: 1: release title, 2: artist name. */ __( '%1$s یک EP توسط %2$s است.', 'music-wave-core' ),
			'album'           => /* translators: 1: release title, 2: artist name. */ __( '%1$s آلبومی از %2$s است.', 'music-wave-core' ),
			'mix'             => /* translators: 1: release title, 2: artist name. */ __( '%1$s ترکیبی از %2$s است.', 'music-wave-core' ),
			'playlist'        => /* translators: 1: release title, 2: artist name. */ __( '%1$s یک فهرست پخش است که توسط %2$s تنظیم شده است.', 'music-wave-core' ),
			'podcast_show'    => /* translators: 1: release title, 2: artist name. */ __( '%1$s یک نمایش پادکست از %2$s است.', 'music-wave-core' ),
			'podcast_episode' => /* translators: 1: release title, 2: artist name. */ __( '%1$s یک قسمت پادکست از %2$s است.', 'music-wave-core' ),
		);
		$parts     = array( sprintf( isset( $templates[ $type ] ) ? $templates[ $type ] : $templates['track'], $result->title, $artist ) );
		if ( '' !== $result->album && $result->album !== $result->title ) {
			$parts[] = sprintf(
				/* translators: %s: album title */
				__( 'در آلبوم %s ظاهر می‌شود.', 'music-wave-core' ),
				$result->album
			);
		}
		if ( '' !== $result->year ) {
			$parts[] = sprintf(
				/* translators: %s: four-digit release year */
				__( 'سال انتشار: %s.', 'music-wave-core' ),
				$result->year
			);
		}
		if ( ! empty( $result->genres ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated genres */
				__( 'ژانرها: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->genres, 'name' ) )
			);
		}
		if ( ! empty( $result->labels ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated record labels */
				__( 'برچسب‌ها: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->labels, 'name' ) )
			);
		}
		if ( ! empty( $result->moods ) ) {
			$parts[] = sprintf(
				/* translators: %s: comma-separated moods */
				__( 'حال‌وهواها: %s.', 'music-wave-core' ),
				implode( ', ', array_column( $result->moods, 'name' ) )
			);
		}

		return MetadataResult::from_array( array_merge( $result->to_array(), array( 'description' => implode( ' ', $parts ) ) ) );
	}
}
