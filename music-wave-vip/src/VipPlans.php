<?php
/**
 * WooCommerce plan-product membership engine.
 *
 * A VIP plan is a plain WooCommerce product. When a paid order containing a
 * plan product completes, the buyer is granted the plan's membership level
 * (and, by default, the dedicated `mw_vip` WordPress role). Refunded,
 * cancelled, or failed orders revoke the grant immediately, matching the
 * Core entitlement lifecycle policy (ADR 0004): no cached entitlement, every
 * access decision re-checks the stored grants live.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class VipPlans {
	public const GRANTS_META_KEY = 'mw_vip_plan_grants';
	public const VIP_ROLE        = 'mw_vip';

	private const MAX_PLANS_PER_LEVEL = 25;
	private const MAX_PRODUCT_IDS     = 20;
	private const MAX_DURATION_DAYS   = 3650;
	private const SECONDS_PER_DAY     = 86400;

	/**
	 * Upper bound on compare-and-set retry rounds for one grant mutation.
	 *
	 * Webhooks for two orders of the same customer can fire concurrently;
	 * each losing round re-reads the stored grants and retries, so grants
	 * are never lost to a last-write-wins meta update.
	 */
	private const MAX_WRITE_ATTEMPTS = 5;

	/**
	 * Plan configuration: level slug => { product_ids, duration_days }.
	 *
	 * @var array<string, array{product_ids: array<int, int>, duration_days: int}>
	 */
	private $plans;

	/** @var bool */
	private $promote_role;

	/** @var callable|null Injectable clock for deterministic tests. */
	private $now_source;

	/**
	 * @param array<string, array<string, mixed>> $plans        Level-keyed plan configuration.
	 * @param bool                                $promote_role Grant the VIP role on activation.
	 * @param callable|null                       $now_source   Optional time source (tests).
	 */
	public function __construct( array $plans = array(), bool $promote_role = true, ?callable $now_source = null ) {
		$this->plans        = $this->normalize_plans( $plans );
		$this->promote_role = $promote_role;
		$this->now_source   = $now_source;
	}

	/**
	 * Build a grants engine from VIP settings without touching WordPress twice.
	 *
	 * @param array<string, mixed>|null $config     Optional pre-loaded settings.
	 * @param callable|null             $now_source Optional time source (tests).
	 */
	public static function from_settings( ?array $config = null, ?callable $now_source = null ): self {
		$config  = is_array( $config ) ? $config : VipSettings::all();
		$plans   = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();
		$promote = ! isset( $config['promote_vip_role'] ) || 'disabled' !== sanitize_key( (string) $config['promote_vip_role'] );

		return new self( self::index_plans( $plans ), $promote, $now_source );
	}

	/**
	 * Register the WooCommerce order-status lifecycle hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		/**
		 * Filter the order statuses that grant VIP plan levels.
		 *
		 * Matches the Core paid-order policy (ADR 0004): `processing` and
		 * `completed` grant access.
		 *
		 * @param array<int, string> $statuses WooCommerce status names without the `wc-` prefix.
		 */
		$granting = apply_filters( 'music_wave_vip_granting_statuses', array( 'processing', 'completed' ) );
		foreach ( is_array( $granting ) ? $granting : array() as $status ) {
			$status = sanitize_key( (string) $status );
			if ( '' !== $status ) {
				add_action( 'woocommerce_order_status_' . $status, array( $this, 'on_granting_status' ), 10, 1 );
			}
		}

		foreach ( array( 'cancelled', 'refunded', 'failed' ) as $void_status ) {
			add_action( 'woocommerce_order_status_' . $void_status, array( $this, 'on_void_status' ), 10, 1 );
		}
	}

	/**
	 * Register the dedicated VIP role once; it carries no elevated capabilities.
	 *
	 * @return void
	 */
	public static function ensure_role(): void {
		if ( ! function_exists( 'add_role' ) || ! function_exists( 'get_role' ) ) {
			return;
		}
		if ( null !== get_role( self::VIP_ROLE ) ) {
			return;
		}

		add_role( self::VIP_ROLE, __( 'MusicWave VIP', 'music-wave-vip' ), array( 'read' => true ) );
	}

	/** @param mixed $order_id Order identifier from the status hook. */
	public function on_granting_status( $order_id ): void {
		$this->grant_for_order( absint( $order_id ) );
	}

	/** @param mixed $order_id Order identifier from the status hook. */
	public function on_void_status( $order_id ): void {
		$this->revoke_for_order( absint( $order_id ) );
	}

	/**
	 * Grant every configured plan level purchased in one order.
	 *
	 * Idempotent: re-processing an order renews the expiry of its existing
	 * grant instead of stacking duplicate rows. Guests (orders without a
	 * customer account) fail closed — they must register to receive VIP.
	 *
	 * The stored meta write is compare-and-set: when two order status hooks
	 * for the same customer run concurrently, both grants are preserved by
	 * the retry loop instead of one clobbering the other.
	 */
	public function grant_for_order( int $order_id ): int {
		if ( $order_id < 1 || empty( $this->plans ) ) {
			return 0;
		}

		$user_id     = self::order_customer_id( $order_id );
		$product_ids = self::order_product_ids( $order_id );
		if ( $user_id < 1 || empty( $product_ids ) ) {
			return 0;
		}

		$now      = $this->now();
		$matching = array();
		foreach ( $this->plans as $level => $plan ) {
			if ( ! empty( array_intersect( $plan['product_ids'], $product_ids ) ) ) {
				$matching[ $level ] = $plan;
			}
		}
		if ( empty( $matching ) ) {
			return 0;
		}

		$mutated = $this->mutate_grants(
			$user_id,
			function ( array $grants ) use ( $order_id, $now, $matching ): array {
				foreach ( $matching as $level => $plan ) {
					$expires_at = $plan['duration_days'] > 0 ? $now + ( $plan['duration_days'] * self::SECONDS_PER_DAY ) : 0;
					$entries    = isset( $grants[ $level ] ) && is_array( $grants[ $level ] ) ? $grants[ $level ] : array();
					$renewed    = false;

					foreach ( $entries as $index => $entry ) {
						if ( isset( $entry['order_id'] ) && (int) $entry['order_id'] === $order_id ) {
							$entries[ $index ]['expires_at'] = max( isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : 0, $expires_at );
							$renewed                         = true;
							break;
						}
					}

					if ( ! $renewed ) {
						$entries[] = array(
							'order_id'   => $order_id,
							'expires_at' => $expires_at,
						);
						if ( count( $entries ) > self::MAX_PLANS_PER_LEVEL ) {
							$entries = array_values( array_slice( $entries, count( $entries ) - self::MAX_PLANS_PER_LEVEL ) );
						}
					}

					$grants[ $level ] = $entries;
				}

				return $grants;
			}
		);
		if ( ! $mutated ) {
			$this->audit( 'grant_contention_failed', $order_id, $user_id );
			return 0;
		}

		$grant_levels = array_keys( $matching );
		$this->sync_user_role( $user_id );

		/**
		 * Fires after a WooCommerce plan purchase granted VIP levels.
		 *
		 * @param int                  $user_id  Buyer account.
		 * @param int                  $order_id Granting WooCommerce order.
		 * @param array<int, string>   $levels   Granted plan level slugs.
		 */
		do_action( 'music_wave_vip_plan_granted', $user_id, $order_id, $grant_levels );

		return count( $matching );
	}

	/**
	 * Revoke every stored grant that came from one order.
	 *
	 * Called on refunded/cancelled/failed transitions so access stops the
	 * moment WooCommerce reports the order void (ADR 0004 lifecycle parity).
	 * The meta write shares the grant compare-and-set path so a refund
	 * landing between another order hook's read and write never erases that
	 * other payment's level.
	 */
	public function revoke_for_order( int $order_id ): int {
		if ( $order_id < 1 ) {
			return 0;
		}

		$user_id = self::order_customer_id( $order_id );
		if ( $user_id < 1 ) {
			return 0;
		}

		$removed = 0;
		$mutated = $this->mutate_grants(
			$user_id,
			function ( array $grants ) use ( $order_id, &$removed ): ?array {
				$removed = 0;
				$next    = array();
				foreach ( $grants as $level => $entries ) {
					$kept = array();
					foreach ( $entries as $entry ) {
						if ( isset( $entry['order_id'] ) && (int) $entry['order_id'] === $order_id ) {
							++$removed;
							continue;
						}
						$kept[] = $entry;
					}
					if ( ! empty( $kept ) ) {
						$next[ $level ] = $kept;
					}
				}

				if ( $removed < 1 ) {
					return null;
				}

				return $next;
			}
		);
		if ( $removed < 1 || ! $mutated ) {
			if ( $removed > 0 && ! $mutated ) {
				$this->audit( 'revoke_contention_failed', $order_id, $user_id );
			}
			return 0;
		}

		$this->sync_user_role( $user_id );

		/**
		 * Fires after a voided WooCommerce order revoked VIP levels.
		 *
		 * @param int $user_id  Buyer account.
		 * @param int $order_id Voided WooCommerce order.
		 */
		do_action( 'music_wave_vip_plan_revoked', $user_id, $order_id );

		return $removed;
	}

	/**
	 * Whether a user currently holds a grant for one plan level.
	 *
	 * Lifetime grants carry `expires_at = 0`; timed grants expire the moment
	 * the clock passes them — no scheduled sweep is required for safety.
	 */
	public function user_has_access( int $user_id, string $level ): bool {
		if ( $user_id < 1 ) {
			return false;
		}

		$level = sanitize_key( $level );
		if ( '' === $level ) {
			return false;
		}

		$grants = $this->grants_for_user( $user_id );
		if ( empty( $grants[ $level ] ) ) {
			return false;
		}

		$now = $this->now();
		foreach ( $grants[ $level ] as $entry ) {
			$expires_at = isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : 0;
			if ( 0 === $expires_at || $expires_at > $now ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * All active plan level slugs for a user, sorted for stable display.
	 *
	 * @return array<int, string>
	 */
	public function active_levels( int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}

		$now    = $this->now();
		$levels = array();
		foreach ( $this->grants_for_user( $user_id ) as $level => $entries ) {
			foreach ( $entries as $entry ) {
				$expires_at = isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : 0;
				if ( 0 === $expires_at || $expires_at > $now ) {
					$levels[] = (string) $level;
					break;
				}
			}
		}

		sort( $levels );

		return $levels;
	}

	/**
	 * Read and normalize stored grants, discarding expired rows in place.
	 *
	 * The pruning write itself uses the same compare-and-set path as grant
	 * mutations, so a concurrent order hook cannot be clobbered by a prune.
	 * When the compare fails, the pruned rows simply remain and disappear
	 * during the next read — correctness never depends on the sweep.
	 *
	 * @return array<string, array<int, array<string, int>>>
	 */
	public function grants_for_user( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::GRANTS_META_KEY, true );
		if ( '' !== $raw && false !== $raw && ! is_array( $raw ) ) {
			// Corrupted payloads are discarded immediately: reads fail closed
			// from now on and a later agreeing CAS write removes the rows.
			delete_user_meta( $user_id, self::GRANTS_META_KEY );
			$raw = array();
		}
		$stored = is_array( $raw ) ? $raw : array();

		$normalized = $this->normalize_grants( $stored, $this->now() );
		if ( ! hash_equals( self::grants_signature( $normalized ), self::grants_signature( $stored ) ) ) {
			$this->cas_write_grants( $user_id, self::grants_signature( $stored ), $normalized );
		}

		return $normalized;
	}

	/**
	 * Apply one mutation to the stored grants with compare-and-set retry.
	 *
	 * The callable receives the normalized grant map and returns the next
	 * normalized map, or null when no write is needed. On a compare failure
	 * (a concurrent order hook wrote in between) the mutation is recomputed
	 * from the fresh state, so no payment grant is ever lost to a
	 * last-write-wins user meta update.
	 *
	 * @param int                                             $user_id Buyer account.
	 * @param callable(array<string, array<int, array<string, int>>>):?array<string, array<int, array<string, int>>> $mutate
	 * @return bool Whether the mutation stored (or already matched) a final state.
	 */
	private function mutate_grants( int $user_id, callable $mutate ): bool {
		for ( $attempt = 0; $attempt < self::MAX_WRITE_ATTEMPTS; ++$attempt ) {
			$stored = get_user_meta( $user_id, self::GRANTS_META_KEY, true );
			if ( '' !== $stored && false !== $stored && ! is_array( $stored ) ) {
				// Corrupted payloads hold no trustworthy grants; start fresh.
				delete_user_meta( $user_id, self::GRANTS_META_KEY );
				$stored = array();
			}
			$stored   = is_array( $stored ) ? $stored : array();
			$expected = self::grants_signature( $stored );
			$current  = $this->normalize_grants( $stored, $this->now() );

			$next = $mutate( $current );
			if ( null === $next ) {
				return true;
			}

			if ( $this->cas_write_grants( $user_id, $expected, $next ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Write grants only when the stored meta still matches the read snapshot.
	 *
	 * @param int                                            $user_id  Buyer account.
	 * @param string                                         $expected Signature of the meta read at attempt start.
	 * @param array<string, array<int, array<string, int>>>  $grants   Normalized next state; empty removes the key.
	 */
	private function cas_write_grants( int $user_id, string $expected, array $grants ): bool {
		$stored = get_user_meta( $user_id, self::GRANTS_META_KEY, true );
		if ( '' !== $stored && false !== $stored && ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored = is_array( $stored ) ? $stored : array();
		if ( ! hash_equals( $expected, self::grants_signature( $stored ) ) ) {
			return false;
		}

		if ( empty( $grants ) ) {
			return delete_user_meta( $user_id, self::GRANTS_META_KEY );
		}
		if ( empty( $stored ) ) {
			return false !== add_user_meta( $user_id, self::GRANTS_META_KEY, $grants, true );
		}

		return false !== update_user_meta( $user_id, self::GRANTS_META_KEY, $grants );
	}

	/**
	 * Canonical signature of one grants snapshot for compare-and-set.
	 *
	 * @param mixed $stored Raw meta payload.
	 */
	private static function grants_signature( $stored ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Internal signature, not a REST payload.
		return (string) json_encode( $stored, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Surface grant-write contention exhaustion for monitoring integrations.
	 *
	 * @param string $event    Event key.
	 * @param int    $order_id WooCommerce order behind the write.
	 * @param int    $user_id  Buyer account.
	 */
	private function audit( string $event, int $order_id, int $user_id ): void {
		/**
		 * Fires when a grants write exhausts its compare-and-set retries.
		 *
		 * @param string $event   Event key.
		 * @param int    $user_id Buyer account.
		 * @param int    $order_id WooCommerce order behind the write.
		 */
		do_action( 'music_wave_vip_grants_write_failed', $event, $user_id, $order_id );
	}

	/**
	 * Normalize a raw grants payload, pruning malformed or expired entries.
	 *
	 * @param array<string, mixed> $stored Raw grants payload.
	 * @return array<string, array<int, array<string, int>>>
	 */
	private function normalize_grants( array $stored, int $now ): array {
		$grants = array();

		foreach ( $stored as $level => $entries ) {
			$level = sanitize_key( (string) $level );
			if ( '' === $level || ! is_array( $entries ) ) {
				continue;
			}

			$kept = array();
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['order_id'] ) ) {
					continue;
				}
				$expires_at = isset( $entry['expires_at'] ) ? (int) $entry['expires_at'] : 0;
				if ( $expires_at > 0 && $expires_at <= $now ) {
					continue;
				}
				$kept[] = array(
					'order_id'   => (int) $entry['order_id'],
					'expires_at' => $expires_at,
				);
			}

			if ( count( $kept ) > self::MAX_PLANS_PER_LEVEL ) {
				$kept = array_values( array_slice( $kept, count( $kept ) - self::MAX_PLANS_PER_LEVEL ) );
			}
			if ( ! empty( $kept ) ) {
				$grants[ $level ] = $kept;
			}
		}

		return $grants;
	}

	/**
	 * Keep the dedicated VIP role in step with the remaining grants.
	 */
	public function sync_user_role( int $user_id ): void {
		if ( ! $this->promote_role || $user_id < 1 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! is_object( $user ) || ! method_exists( $user, 'add_role' ) || ! method_exists( $user, 'remove_role' ) ) {
			return;
		}

		if ( ! empty( $this->active_levels( $user_id ) ) ) {
			$user->add_role( self::VIP_ROLE );
		} else {
			$user->remove_role( self::VIP_ROLE );
		}
	}

	/**
	 * Parse the admin textarea representation into normalized plan rows.
	 *
	 * One plan per line: `level:product-id[,product-id][:days]`. A missing
	 * duration means a lifetime grant. Malformed lines are dropped silently;
	 * the settings screen documents the format beside the field.
	 *
	 * @return array<int, array{level: string, product_ids: array<int, int>, duration_days: int}>
	 */
	public static function parse_plan_rows( string $rows ): array {
		$plans = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $rows ) as $row ) {
			$row = trim( wp_unslash( (string) $row ) );
			if ( '' === $row || 0 === strpos( $row, '#' ) ) {
				continue;
			}
			if ( 1 !== preg_match( '/^([A-Za-z0-9_\-]{1,32})\s*:\s*([0-9,\s]+)(?:\s*:\s*(\d{1,4}))?$/', $row, $matches ) ) {
				continue;
			}

			$product_ids = array_values(
				array_slice(
					array_values(
						array_filter(
							array_unique( array_map( 'absint', explode( ',', $matches[2] ) ) )
						)
					),
					0,
					self::MAX_PRODUCT_IDS
				)
			);

			$plans[] = array(
				'level'         => sanitize_key( $matches[1] ),
				'product_ids'   => $product_ids,
				'duration_days' => isset( $matches[3] ) ? min( self::MAX_DURATION_DAYS, absint( $matches[3] ) ) : 0,
			);

			if ( count( $plans ) >= 10 ) {
				break;
			}
		}

		return $plans;
	}

	/**
	 * Render normalized plans back into the textarea representation.
	 *
	 * @param array<int, array<string, mixed>> $plans Normalized plan rows.
	 * @return array<int, string>
	 */
	public static function plan_rows_for_display( array $plans ): array {
		$rows = array();
		foreach ( is_array( $plans ) ? $plans : array() as $plan ) {
			if ( ! is_array( $plan ) || empty( $plan['level'] ) || empty( $plan['product_ids'] ) ) {
				continue;
			}
			$ids  = array_values( array_filter( array_map( 'absint', (array) $plan['product_ids'] ) ) );
			$days = isset( $plan['duration_days'] ) ? (int) $plan['duration_days'] : 0;
			if ( empty( $ids ) || '' === sanitize_key( (string) $plan['level'] ) ) {
				continue;
			}
			$rows[] = sprintf( '%s:%s%s', sanitize_key( (string) $plan['level'] ), implode( ',', $ids ), $days > 0 ? ':' . $days : '' );
		}

		return $rows;
	}

	/**
	 * Convert plan rows (list form) into the level-keyed engine form.
	 *
	 * @param array<int, mixed> $rows Normalized plan rows from settings.
	 * @return array<string, array{product_ids: array<int, int>, duration_days: int}>
	 */
	public static function index_plans( array $rows ): array {
		$plans = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['level'] ) ) {
				continue;
			}
			$level = sanitize_key( (string) $row['level'] );
			$ids   = isset( $row['product_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $row['product_ids'] ) ) ) : array();
			if ( '' === $level || empty( $ids ) ) {
				continue;
			}
			$plans[ $level ] = array(
				'product_ids'   => $ids,
				'duration_days' => isset( $row['duration_days'] ) ? (int) $row['duration_days'] : 0,
			);
		}

		return $plans;
	}

	/**
	 * @param array<string, array<string, mixed>> $plans Candidate configuration.
	 * @return array<string, array{product_ids: array<int, int>, duration_days: int}>
	 */
	private function normalize_plans( array $plans ): array {
		$result = array();
		foreach ( $plans as $level => $plan ) {
			$level = sanitize_key( (string) $level );
			if ( '' === $level || ! is_array( $plan ) ) {
				continue;
			}
			$ids = isset( $plan['product_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $plan['product_ids'] ) ) ) : array();
			$ids = array_slice( $ids, 0, self::MAX_PRODUCT_IDS );
			if ( empty( $ids ) ) {
				continue;
			}
			$duration = isset( $plan['duration_days'] ) ? (int) $plan['duration_days'] : 0;

			$result[ $level ] = array(
				'product_ids'   => $ids,
				'duration_days' => $duration >= 0 ? min( $duration, self::MAX_DURATION_DAYS ) : 0,
			);
		}

		return $result;
	}

	/**
	 * The customer account behind a WooCommerce order, fail-closed.
	 */
	private static function order_customer_id( int $order_id ): int {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}

		$order = wc_get_order( $order_id );

		return is_object( $order ) && method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
	}

	/**
	 * Every product (and parent) ID purchased in a WooCommerce order.
	 *
	 * @return array<int, int>
	 */
	private static function order_product_ids( int $order_id ): array {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return array();
		}

		$items = $order->get_items();
		$ids   = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			$product_id = (int) $item->get_product_id();
			if ( $product_id > 0 ) {
				$ids[] = $product_id;
			}
			if ( method_exists( $item, 'get_variation_id' ) ) {
				$variation_id = (int) $item->get_variation_id();
				if ( $variation_id > 0 ) {
					$ids[] = $variation_id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private function now(): int {
		return null !== $this->now_source ? (int) call_user_func( $this->now_source ) : time();
	}

	// =================================================================
	// Expiry automation: time-based reversion to Standard User
	// =================================================================
	public const EXPIRY_CRON = 'music_wave_vip_expiry_check';

	/** Transient key that throttles opportunistic expiry sweeps. */
	public const SWEEP_TRANSIENT = 'mwvip_expiry_sweep_cooldown';

	/**
	 * Schedule the hourly expiry sweep (idempotent).
	 *
	 * @return void
	 */
	public static function schedule_expiry_checks(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( false === wp_next_scheduled( self::EXPIRY_CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::EXPIRY_CRON );
		}
	}

	/**
	 * Remove the scheduled sweep.
	 *
	 * @return void
	 */
	public static function clear_expiry_checks(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::EXPIRY_CRON );
		}
	}

	/**
	 * Cron handler: prune expired grants and sync VIP role for every user
	 * that still holds the mw_vip role or a grants meta row.
	 *
	 * Expired timed grants (duration_days > 0) are removed; lifetime grants
	 * (duration_days = 0) are never expired. Users left with zero active
	 * levels lose the VIP role and fire `music_wave_vip_expired`.
	 *
	 * @return array<string, int> Counts for observability / tests.
	 */
	public function handle_expiry_check(): array {
		$pruned_users = 0;
		$demoted      = 0;
		$scanned      = 0;

		$user_ids = $this->collect_vip_user_ids();
		foreach ( $user_ids as $user_id ) {
			++$scanned;
			$before = $this->active_levels( $user_id );
			// grants_for_user() already normalizes and CAS-prunes expired rows.
			$this->grants_for_user( $user_id );
			$after = $this->active_levels( $user_id );

			// Detect that something expired.
			if ( count( $after ) < count( $before ) || ( empty( $after ) && ! empty( $before ) ) ) {
				++$pruned_users;
			}
			// Sync role; grants_for_user prune may have left user with no levels
			// but sync_user_role is only called on grant/revoke normally.
			$had_role = $this->user_has_role( $user_id );
			$this->sync_user_role( $user_id );
			$has_role = $this->user_has_role( $user_id );

			if ( $had_role && ! $has_role ) {
				++$demoted;
				/**
				 * Fires when a timed VIP plan expires for a user.
				 *
				 * @param int   $user_id       User whose plan expired.
				 * @param array $expired_levels List of levels that are no longer active.
				 */
				do_action( 'music_wave_vip_expired', $user_id, array_values( array_diff( $before, $after ) ) );
			}
		}

		return array(
			'scanned'      => $scanned,
			'pruned_users' => $pruned_users,
			'demoted'      => $demoted,
		);
	}

	/**
	 * Throttled sweep for the opportunistic admin_init / wp_login hooks.
	 *
	 * Access decisions already check grant expiry live, so these sweeps only
	 * keep role cosmetics fresh. The cooldown keeps an O(users) scan off the
	 * hot path of every admin page load and customer login; the hourly cron
	 * remains the authoritative sweep.
	 *
	 * @return array<string, int> Counts for observability / tests.
	 */
	public function maybe_handle_expiry_check(): array {
		$cooldown = 15 * ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 );
		if ( function_exists( 'get_transient' ) && false !== get_transient( self::SWEEP_TRANSIENT ) ) {
			return array(
				'scanned'      => 0,
				'pruned_users' => 0,
				'demoted'      => 0,
			);
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::SWEEP_TRANSIENT, 1, $cooldown );
		}

		return $this->handle_expiry_check();
	}

	/**
	 * Collect user IDs that may need expiry processing.
	 *
	 * Combines users with the VIP role and users that still have the grants
	 * meta key, de-duplicated and capped to avoid unbounded queries.
	 *
	 * @return array<int, int>
	 */
	private function collect_vip_user_ids(): array {
		$ids = array();

		if ( function_exists( 'get_users' ) ) {
			$role_users = get_users(
				array(
					'role'   => self::VIP_ROLE,
					'fields' => 'ID',
					'number' => 500,
				)
			);
			foreach ( is_array( $role_users ) ? $role_users : array() as $uid ) {
				$ids[ (int) $uid ] = true;
			}

			global $wpdb;
			if ( $wpdb instanceof \wpdb ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$meta_users = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s LIMIT %d",
						self::GRANTS_META_KEY,
						500
					)
				);
				foreach ( is_array( $meta_users ) ? $meta_users : array() as $uid ) {
					$ids[ (int) $uid ] = true;
				}
			}
		} else {
			// Fallback for unit-test / WP-stub environments: scan stub globals.
			if ( isset( $GLOBALS['mw_test_user_meta'] ) && is_array( $GLOBALS['mw_test_user_meta'] ) ) {
				foreach ( $GLOBALS['mw_test_user_meta'] as $uid => $meta ) {
					if ( isset( $meta[ self::GRANTS_META_KEY ] ) ) {
						$ids[ (int) $uid ] = true;
					}
				}
			}
			if ( isset( $GLOBALS['mw_test_users'] ) && is_array( $GLOBALS['mw_test_users'] ) ) {
				foreach ( $GLOBALS['mw_test_users'] as $uid => $user ) {
					if ( is_object( $user ) && isset( $user->roles ) && is_array( $user->roles ) && in_array( self::VIP_ROLE, $user->roles, true ) ) {
						$ids[ (int) $uid ] = true;
					}
				}
			}
		}

		return array_values( array_map( 'intval', array_keys( $ids ) ) );
	}

	private function user_has_role( int $user_id ): bool {
		$user = get_userdata( $user_id );
		return false !== $user && isset( $user->roles ) && is_array( $user->roles ) && in_array( self::VIP_ROLE, $user->roles, true );
	}
}
