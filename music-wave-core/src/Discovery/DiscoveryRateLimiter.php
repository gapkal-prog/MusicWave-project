<?php
/**
 * Fixed-window rate limit for the public discovery routes.
 *
 * Autocomplete and facet requests are cheap but public, so they are bounded
 * per actor (user ID when signed in, hashed client address otherwise) before
 * any query runs (PROJECT_PLAN.md Stage 5 deliverable 6).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Discovery;

final class DiscoveryRateLimiter {
	public const DEFAULT_LIMIT  = 60;
	public const DEFAULT_WINDOW = 60;

	/** @var int|null Explicit limit; otherwise resolved by filter. */
	private $limit;

	/** @var int|null Explicit window; otherwise resolved by filter. */
	private $window;

	public function __construct( ?int $limit = null, ?int $window = null ) {
		$this->limit  = $limit;
		$this->window = $window;
	}

	/**
	 * Whether the current actor may run one more discovery query.
	 */
	public function allow( string $bucket = 'suggest' ): bool {
		/**
		 * Filter the maximum public discovery requests per actor per window.
		 *
		 * @param int    $limit  Maximum requests per window.
		 * @param string $bucket Route bucket (suggest|facets).
		 */
		$limit = null !== $this->limit ? $this->limit : (int) apply_filters( 'music_wave_discovery_rate_limit', self::DEFAULT_LIMIT, $bucket );
		if ( $limit < 1 ) {
			return true; // A non-positive limit disables rate limiting explicitly.
		}

		/**
		 * Filter the discovery rate-limit window in seconds.
		 *
		 * @param int    $window Window length in seconds.
		 * @param string $bucket Route bucket (suggest|facets).
		 */
		$window = max( 10, null !== $this->window ? $this->window : (int) apply_filters( 'music_wave_discovery_rate_window', self::DEFAULT_WINDOW, $bucket ) );
		$key    = 'mw_disc_rate_' . sanitize_key( $bucket ) . '_' . $this->actor() . '_' . (int) floor( time() / $window );
		$count  = get_transient( $key );
		$count  = false === $count ? 0 : (int) $count;
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window * 2 );

		return true;
	}

	/**
	 * Stable, non-reversible actor key for the current request.
	 */
	private function actor(): string {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return 'u' . $user_id;
		}

		$address = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
			$address = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}

		// Only a salted digest is stored, never the visitor's address.
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'nonce' ) : 'music-wave';

		return 'a' . substr( hash( 'sha256', $salt . '|' . $address ), 0, 16 );
	}
}
