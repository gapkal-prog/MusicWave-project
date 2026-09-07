<?php
/**
 * Plugin Name:       MusicWave VIP Integration
 * Description:       Configurable protected-file delivery, remote-host signing, WooCommerce plan membership, and membership adapters for MusicWave Core.
 * Version:           0.5.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            ManaCore
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       music-wave-vip
 * Domain Path:       /languages
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
define( 'MUSIC_WAVE_VIP_FILE', __FILE__ );
define( 'MUSIC_WAVE_VIP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MUSIC_WAVE_VIP_VERSION', '0.5.1' );
require_once MUSIC_WAVE_VIP_PATH . 'src/Autoloader.php';
ManaCore\MusicWave\Vip\Autoloader::register();

/**
 * Load translations from the plugin language directory.
 *
 * @return void
 */
function music_wave_vip_load_textdomain(): void {
	load_plugin_textdomain(
		'music-wave-vip',
		false,
		dirname( plugin_basename( MUSIC_WAVE_VIP_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'music_wave_vip_load_textdomain', 1 );

$music_wave_vip_storage = new ManaCore\MusicWave\Vip\ProtectedAssetStorage();
add_filter(
	'music_wave_membership_provider',
	static function () {
		return ManaCore\MusicWave\Vip\ProviderFactory::membership_provider();
	}
);
add_filter(
	'music_wave_download_provider',
	static function () use ( $music_wave_vip_storage ) {
		return ManaCore\MusicWave\Vip\ProviderFactory::download_provider( $music_wave_vip_storage );
	}
);

/**
 * Whether VIP entitlement enforcement is switched on.
 *
 * The master switch only stops membership enforcement: the membership gate
 * opens for everyone (guests included) while secure delivery keeps running.
 * Purchase gating (WooCommerce per-release sales) is never touched.
 */
function music_wave_vip_module_enabled(): bool {
	$config = ManaCore\MusicWave\Vip\VipSettings::all();

	return 'disabled' !== (string) $config['module_enabled'];
}

/**
 * Master switch and delivery-access override.
 *
 * - module disabled: every membership gate opens for everyone (reason
 *   `vip_module_disabled`); the site behaves as all-VIP content.
 * - delivery_access = everyone: the secure-delivery mode where registration
 *   is optional; membership gates open for guests but signed, expiring
 *   download links stay in force.
 * Both overrides are limited to `membership_required` denials so purchase
 * gating, restricted mode, and administrator decisions stay authoritative.
 */
add_filter(
	'music_wave_access_decision',
	static function ( $decision ) {
		if ( ! $decision instanceof ManaCore\MusicWave\Core\Access\AccessDecision || $decision->is_allowed() ) {
			return $decision;
		}
		if ( 'membership_required' !== $decision->reason() ) {
			return $decision;
		}
		$config = ManaCore\MusicWave\Vip\VipSettings::all();
		$module = (string) $config['module_enabled'];
		if ( 'disabled' === $module || 'everyone' === (string) $config['delivery_access'] ) {
			return ManaCore\MusicWave\Core\Access\AccessDecision::allow(
				'disabled' === $module ? 'vip_module_disabled' : 'vip_delivery_open',
				$decision->mode()
			);
		}

		return $decision;
	},
	20,
	1
);

/**
 * VIP plans: WooCommerce products as membership plans.
 *
 * The order-status hooks grant or revoke stored plan grants live; the access
 * policy re-checks those grants on every decision, so refunds revoke access
 * immediately without cached entitlements (ADR 0004 parity).
 *
 * While the master switch is off the granting engine, expiry automation, and
 * level filters stay parked so a disabled module changes nothing; stored
 * grants survive and enforcement resumes the moment it is switched back on.
 */
$music_wave_vip_plans  = ManaCore\MusicWave\Vip\VipPlans::from_settings();
$music_wave_vip_active = music_wave_vip_module_enabled();
add_action( 'init', array( ManaCore\MusicWave\Vip\VipPlans::class, 'ensure_role' ), 5 );
if ( $music_wave_vip_active ) {
	add_action( 'plugins_loaded', array( $music_wave_vip_plans, 'register_hooks' ), 20 );
	if ( did_action( 'plugins_loaded' ) ) {
		$music_wave_vip_plans->register_hooks();
	}

	// Time-based expiry: ensure VIP role automatically reverts to Standard User
	// when all timed grants have elapsed, without requiring a new store purchase.
	add_action( 'init', array( ManaCore\MusicWave\Vip\VipPlans::class, 'schedule_expiry_checks' ), 20 );
	add_action( ManaCore\MusicWave\Vip\VipPlans::EXPIRY_CRON, array( $music_wave_vip_plans, 'handle_expiry_check' ) );
	// Opportunistic prune on admin and front-end bootstrap; throttled by a
	// cooldown transient so the sweep stays off the per-request hot path.
	add_action( 'admin_init', array( $music_wave_vip_plans, 'maybe_handle_expiry_check' ), 30 );
	add_action(
		'wp_login',
		static function ( $user_login, $user ) use ( $music_wave_vip_plans ): void {
			unset( $user_login );
			if ( is_object( $user ) && isset( $user->ID ) ) {
				$music_wave_vip_plans->maybe_handle_expiry_check();
			}
		},
		10,
		2
	);
}

register_activation_hook( MUSIC_WAVE_VIP_FILE, array( ManaCore\MusicWave\Vip\VipPlans::class, 'schedule_expiry_checks' ) );
register_deactivation_hook( MUSIC_WAVE_VIP_FILE, array( ManaCore\MusicWave\Vip\VipPlans::class, 'clear_expiry_checks' ) );

/**
 * Surface active plan levels alongside roles in dashboard and level displays.
 *
 * Plan grants are only exposed when the `woocommerce_plans` source is
 * enabled, so disabling the source removes the levels everywhere at once.
 *
 * @param mixed $levels  Existing level slugs for the user.
 * @param mixed $user_id Requested user.
 */
add_filter(
	'music_wave_vip_membership_levels_for_user',
	static function ( $levels, $user_id ) {
		if ( ! music_wave_vip_module_enabled() ) {
			return $levels;
		}
		$config = ManaCore\MusicWave\Vip\VipSettings::all();
		if ( ! in_array( 'woocommerce_plans', (array) $config['membership_sources'], true ) ) {
			return $levels;
		}
		$existing = is_array( $levels ) ? array_map( 'strval', $levels ) : array();
		$plans    = ManaCore\MusicWave\Vip\VipPlans::from_settings( $config );
		return array_values( array_unique( array_merge( $existing, $plans->active_levels( absint( $user_id ) ) ) ) );
	},
	20,
	2
);
add_action( 'admin_notices', array( ManaCore\MusicWave\Vip\Plugin::class, 'core_notice' ) );
$music_wave_vip_plan_products = new ManaCore\MusicWave\Vip\PlanProducts();
$music_wave_vip_plan_products->register();
add_filter(
	'music_wave_vip_membership_panel',
	static function ( $data, $user_id ) {
		return ManaCore\MusicWave\Vip\PlanProducts::products_data( absint( $user_id ) );
	},
	10,
	2
);
$music_wave_vip_settings = new ManaCore\MusicWave\Vip\SettingsPage();
add_action( 'admin_menu', array( $music_wave_vip_settings, 'add_page' ) );
add_action( 'admin_init', array( $music_wave_vip_settings, 'register' ) );
add_filter( 'music_wave_admin_integrations', array( $music_wave_vip_settings, 'integration_card' ) );
$music_wave_vip_asset_routes = new ManaCore\MusicWave\Vip\ProtectedAssetRoutes( $music_wave_vip_storage );
$music_wave_vip_asset_routes->register();

/**
 * Provider-side authorization for assigning local protected assets.
 *
 * VIP only judges its own `local:` namespace: the asset must exist in the
 * protected inventory and the acting user must hold the dedicated asset
 * capability. Other providers' identifiers pass through untouched
 * (PROJECT_PLAN.md Stage 1 deliverable 5).
 */
add_filter(
	'music_wave_can_assign_download_asset',
	static function ( $authorized, $asset_id ) use ( $music_wave_vip_storage ) {
		$asset_id = (string) $asset_id;
		$is_vip   = 0 === strpos( $asset_id, ManaCore\MusicWave\Vip\ProtectedAssetRegistry::PREFIX );
		if ( null !== $authorized || ( ! $is_vip && 0 !== strpos( $asset_id, 'local:' ) ) ) {
			return $authorized;
		}
		if ( ! current_user_can( 'manage_mw_protected_assets' ) ) {
			return false;
		}
		if ( $is_vip ) {
			return $music_wave_vip_storage->registry()->exists( $asset_id );
		}

		return false !== $music_wave_vip_storage->resolve( $asset_id );
	},
	10,
	2
);
