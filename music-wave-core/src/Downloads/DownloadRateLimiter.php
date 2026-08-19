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
	 * bounded. The limit and window are filterable per site.
	 */
	public function allow( int $user_id ): bool {
		if ( $user_id < 1 ) {
			return false;
		}

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

		$key   = 'mw_dl_rate_' . $user_id . '_' . (int) floor( time() / $window );
		$count = get_transient( $key );
		$count = false === $count ? 0 : (int) $count;
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window * 2 );

		return true;
	}
}
