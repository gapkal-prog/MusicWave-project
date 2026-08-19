<?php
/**
 * Plugin Name:       MusicWave VIP Integration
 * Description:       Configurable protected-file delivery, remote-host signing, and membership adapters for MusicWave Core.
 * Version:           0.3.3
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
add_action( 'admin_notices', array( ManaCore\MusicWave\Vip\Plugin::class, 'core_notice' ) );
$music_wave_vip_settings = new ManaCore\MusicWave\Vip\SettingsPage();
add_action( 'admin_menu', array( $music_wave_vip_settings, 'add_page' ) );
add_action( 'admin_init', array( $music_wave_vip_settings, 'register' ) );
add_filter( 'music_wave_admin_integrations', array( $music_wave_vip_settings, 'integration_card' ) );
add_action( 'music_wave_admin_integration_settings', array( $music_wave_vip_settings, 'render_embedded' ) );
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
