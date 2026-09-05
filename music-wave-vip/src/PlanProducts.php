<?php
/**
 * WooCommerce plan-product management helpers.
 *
 * Plans are ordinary WooCommerce products; this class only provisions the
 * well-known monthly/semi-annual/annual presets on demand and builds the
 * customer-facing product data used by the Membership surfaces. All mutating
 * entry points are nonce- and capability-guarded admin actions.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class PlanProducts {
	public const ACTION_CREATE_DEFAULTS = 'music_wave_vip_create_default_plans';
	public const ACTION_REMOVE_PLAN     = 'music_wave_vip_remove_plan';
	public const DURATION_META          = '_mw_vip_plan_duration_days';
	public const LEVEL_META             = '_mw_vip_plan_level';

	/**
	 * Preset plans created by the one-click button: 1, 6, and 12 months.
	 *
	 * @return array<int, array{level: string, title: string, days: int}>
	 */
	public static function presets(): array {
		return array(
			array(
				'level' => 'vip-1m',
				'title' => __( 'عضویت VIP — ۱ ماه', 'music-wave-vip' ),
				'days'  => 30,
			),
			array(
				'level' => 'vip-6m',
				'title' => __( 'عضویت VIP — ۶ ماه', 'music-wave-vip' ),
				'days'  => 180,
			),
			array(
				'level' => 'vip-12m',
				'title' => __( 'عضویت VIP — ۱۲ ماه', 'music-wave-vip' ),
				'days'  => 365,
			),
		);
	}

	/**
	 * Register the guarded admin actions used by the settings screen.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_CREATE_DEFAULTS, array( $this, 'handle_create_defaults' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE_PLAN, array( $this, 'handle_remove_plan' ) );
	}

	/**
	 * One-click provisioning of the 1/6/12-month plan products.
	 *
	 * Idempotent: levels that already have a mapping are left untouched, so
	 * the button is safe to press repeatedly. Prices default to 0 (free) so
	 * the products are immediately purchasable; operators set the real price
	 * on the product screen like any other WooCommerce product.
	 *
	 * @return array{created: int, levels: array<int, string>, skipped: array<int, string>, failed: array<int, string>}
	 */
	public static function create_defaults(): array {
		$result = array(
			'created' => 0,
			'levels'  => array(),
			'skipped' => array(),
			'failed'  => array(),
		);
		if ( ! post_type_exists( 'product' ) ) {
			$result['failed'][] = 'woocommerce';
			return $result;
		}

		$config = VipSettings::all();
		$plans  = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();
		$mapped = array();
		foreach ( $plans as $plan ) {
			if ( is_array( $plan ) && ! empty( $plan['level'] ) ) {
				$mapped[] = sanitize_key( (string) $plan['level'] );
			}
		}

		$rows = (string) $config['plan_rows'];
		foreach ( self::presets() as $preset ) {
			if ( in_array( $preset['level'], $mapped, true ) ) {
				$result['skipped'][] = $preset['level'];
				continue;
			}
			$product_id = wp_insert_post(
				array(
					'post_title'   => $preset['title'],
					'post_status'  => 'publish',
					'post_type'    => 'product',
					'post_content' => __( 'طرح عضویت VIP MusicWave. خرید این طرح سطح عضویت منطبق را برای مدت طرح فوراً اعطا می‌کند؛ بازپرداخت یا لغو، آن را بلافاصله پس می‌گیرد.', 'music-wave-vip' ),
				),
				true
			);
			if ( is_wp_error( $product_id ) || $product_id < 1 ) {
				$result['failed'][] = $preset['level'];
				continue;
			}
			update_post_meta( $product_id, '_virtual', 'yes' );
			update_post_meta( $product_id, '_sold_individually', 'yes' );
			update_post_meta( $product_id, '_regular_price', '0' );
			update_post_meta( $product_id, '_price', '0' );
			update_post_meta( $product_id, self::DURATION_META, (string) $preset['days'] );
			update_post_meta( $product_id, self::LEVEL_META, $preset['level'] );

			$rows = trim( $rows ) . "\n" . $preset['level'] . ':' . (int) $product_id . ':' . $preset['days'];
			++$result['created'];
			$result['levels'][] = $preset['level'] . ':' . (int) $product_id;
		}

		if ( $result['created'] > 0 ) {
			update_option( VipSettings::OPTION, VipSettings::sanitize( array( 'plan_rows' => trim( $rows ) ) ) );
		}

		return $result;
	}

	/**
	 * Guarded admin action: create the default plan products.
	 *
	 * @return void
	 */
	public function handle_create_defaults(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ مدیریت طرح‌های VIP را ندارید.', 'music-wave-vip' ) );
		}
		check_admin_referer( self::ACTION_CREATE_DEFAULTS );

		$result  = self::create_defaults();
		$created = $result['created'];
		$notice  = 'mwvip_defaults_failed';
		if ( $created > 0 ) {
			$notice = 'mwvip_defaults_created';
		} elseif ( ! empty( $result['skipped'] ) && empty( $result['failed'] ) ) {
			$notice = 'mwvip_defaults_skipped';
		}
		set_transient( 'mwvip_plan_action_notice', $notice, 60 );

		wp_safe_redirect( SettingsPage::page_url() );
		exit;
	}

	/**
	 * Guarded admin action: remove one plan mapping row (not the product).
	 *
	 * Invoked as a nonce-protected GET link because the plans table renders
	 * inside the main settings form, where a nested form element is invalid
	 * HTML and would break the settings nonce.
	 *
	 * @return void
	 */
	public function handle_remove_plan(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ مدیریت طرح‌های VIP را ندارید.', 'music-wave-vip' ) );
		}
		check_admin_referer( self::ACTION_REMOVE_PLAN );

		$level   = isset( $_REQUEST['level'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['level'] ) ) : '';
		$config  = VipSettings::all();
		$plans   = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();
		$removed = false;
		$kept    = array();
		foreach ( $plans as $plan ) {
			if ( is_array( $plan ) && isset( $plan['level'] ) && sanitize_key( (string) $plan['level'] ) === $level ) {
				$removed = true;
				continue;
			}
			if ( is_array( $plan ) ) {
				$kept[] = $plan;
			}
		}
		if ( $removed ) {
			update_option(
				VipSettings::OPTION,
				VipSettings::sanitize(
					array(
						'vip_plans' => $kept,
						'plan_rows' => implode( "\n", VipPlans::plan_rows_for_display( $kept ) ),
					)
				)
			);
			set_transient( 'mwvip_plan_action_notice', 'mwvip_plan_removed', 60 );
		}

		wp_safe_redirect( SettingsPage::page_url() );
		exit;
	}

	/**
	 * Customer-facing plan/product data for the Membership surfaces.
	 *
	 * @param int $user_id Account owner (0 for guests).
	 * @return array<string, mixed>
	 */
	public static function products_data( int $user_id ): array {
		$config = VipSettings::all();
		$plans  = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();

		$active = array();
		if ( $user_id > 0 ) {
			$grants = VipPlans::from_settings( $config )->grants_for_user( $user_id );
			foreach ( $grants as $level => $rows ) {
				if ( ! is_array( $rows ) || empty( $rows ) ) {
					continue;
				}
				$expires  = 0;
				$lifetime = false;
				foreach ( $rows as $row ) {
					$at = is_array( $row ) && isset( $row['expires_at'] ) ? (int) $row['expires_at'] : 0;
					if ( 0 === $at ) {
						$lifetime = true;
						continue;
					}
					$expires = max( $expires, $at );
				}
				$active[] = array(
					'level'   => sanitize_key( (string) $level ),
					'expires' => $lifetime ? __( 'مادام‌العمر', 'music-wave-vip' ) : ( $expires > 0 && function_exists( 'date_i18n' ) ? date_i18n( (string) get_option( 'date_format' ), $expires ) : '' ),
				);
			}
		}

		$products = array();
		foreach ( $plans as $plan ) {
			if ( ! is_array( $plan ) || empty( $plan['level'] ) || empty( $plan['product_ids'] ) || ! is_array( $plan['product_ids'] ) ) {
				continue;
			}
			$level = sanitize_key( (string) $plan['level'] );
			$days  = isset( $plan['duration_days'] ) ? absint( $plan['duration_days'] ) : 0;
			foreach ( $plan['product_ids'] as $product_id ) {
				$product_id = absint( $product_id );
				if ( $product_id < 1 || 'product' !== get_post_type( $product_id ) ) {
					continue;
				}
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
				$title   = get_the_title( $product_id );
				$url     = (string) get_permalink( $product_id );
				$cart    = '';
				$price   = '';
				if ( $product instanceof \WC_Product ) {
					$title = $product->get_name();
					$price = $product->get_price_html();
					$cart  = $product->is_purchasable() && $product->is_in_stock() ? $product->add_to_cart_url() : '';
				}
				$products[] = array(
					'id'       => $product_id,
					'level'    => $level,
					'title'    => $title,
					'price'    => wp_strip_all_tags( $price ),
					'duration' => self::duration_label( $days ),
					'url'      => $url,
					'cart_url' => $cart,
				);
			}
		}

		return array(
			'module_enabled' => 'disabled' !== (string) $config['module_enabled'],
			'active'         => $active,
			'plans'          => $products,
		);
	}

	/**
	 * Human label for a plan duration in days.
	 */
	public static function duration_label( int $days ): string {
		if ( $days < 1 ) {
			return __( 'مادام‌العمر', 'music-wave-vip' );
		}
		if ( 0 === $days % 365 ) {
			/* translators: %d: number of years. */
			return sprintf( _n( '%d سال', '%d سال', intdiv( $days, 365 ), 'music-wave-vip' ), intdiv( $days, 365 ) );
		}
		if ( 0 === $days % 30 ) {
			/* translators: %d: number of months. */
			return sprintf( _n( '%d ماه', '%d ماه', intdiv( $days, 30 ), 'music-wave-vip' ), intdiv( $days, 30 ) );
		}
		/* translators: %d: number of days. */
		return sprintf( _n( '%d روز', '%d روز', $days, 'music-wave-vip' ), $days );
	}
}
