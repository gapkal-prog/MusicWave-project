<?php
/**
 * Fixed-window rate limit for download/stream token issuance.
 *
 * Bounds token-issue abuse per user before entitlement work runs
 * (PROJECT_PLAN.md Stage 2 deliverable 4).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

final class DownloadRateLimiter {
	public const DEFAULT_LIMIT  = 30;
	public const DEFAULT_WINDOW = 60;

	/**
	 * Whether the user may request one more token in the current window.
	 *
	 * Uses a fixed-window transient counter: cheap, host-cache friendly, and
	 * bounded. The limit and window are filterable per site. Anonymous
	 * visitors (user 0, allowed by policy-level open gates) get their own
	 * per-IP bucket so guests cannot share or exhaust a single counter.
	 */
	public function allow( int $user_id ): bool {
		/**
		 * Filter the maximum token issues per user per window.
		 *
		 * @param int $limit   Maximum requests per window.
		 * @param int $user_id Acting user.
		 */
		$limit = (int) apply_filters( 'music_wave_download_rate_limit', self::DEFAULT_LIMIT, $user_id );
		if ( $limit < 1 ) {
			return true; // A non-positive limit disables rate limiting explicitly.
		}

		/**
		 * Filter the rate-limit window length in seconds.
		 *
		 * @param int $window  Window length in seconds.
		 * @param int $user_id Acting user.
		 */
		$window = max( 10, (int) apply_filters( 'music_wave_download_rate_window', self::DEFAULT_WINDOW, $user_id ) );

		$key   = $this->bucket_key( $user_id ) . '_' . (int) floor( time() / $window );
		$count = $this->increment( $key, $window * 2 );

		return $count <= $limit;
	}

	/**
	 * Increment one window counter as atomically as the environment allows.
	 *
	 * Persistent object caches get a backend increment; database-backed
	 * transients get a single UPDATE statement; everything else falls back to
	 * a read-then-write. Increment-first means concurrent requests receive
	 * distinct counts and can never all pass a check against the same value.
	 *
	 * @return int Counter value after the increment.
	 */
	private function increment( string $key, int $ttl ): int {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_incr' ) ) {
			$cache_key = '_transient_' . $key;
			$count     = wp_cache_incr( $cache_key, 1, '' );
			if ( false !== $count && null !== $count && (int) $count > 0 ) {
				return (int) $count;
			}
			if ( function_exists( 'wp_cache_add' ) ) {
				wp_cache_add( $cache_key, 1, '', $ttl );
			}

			return 1;
		}

		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && property_exists( $wpdb, 'options' ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'query' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s", '_transient_' . $key ) );
			if ( $updated > 0 ) {
				$count = get_transient( $key );

				return false === $count ? 1 : (int) $count;
			}
		}

		$count = get_transient( $key );
		$count = false === $count ? 0 : (int) $count;
		set_transient( $key, $count + 1, $ttl );

		return $count + 1;
	}

	/**
	 * Stable per-visitor bucket identity: user ID, or a hashed IP for guests.
	 */
	private function bucket_key( int $user_id ): string {
		if ( $user_id > 0 ) {
			return 'mw_dl_rate_' . $user_id;
		}

		$raw = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ip  = function_exists( 'filter_var' ) && false !== filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '';

		return 'mw_dl_rate_g_' . hash( 'sha256', '' !== $ip ? $ip : 'unknown' );
	}
}
