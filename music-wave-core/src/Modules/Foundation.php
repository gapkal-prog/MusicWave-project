<?php
/**
 * Foundation module.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Modules;

use ManaCore\MusicWave\Core\Contracts\Module;

final class Foundation implements Module {
	/**
	 * Dedicated capability for managing protected download assets.
	 *
	 * Assigning new protected asset identifiers to releases requires this
	 * capability (or an explicit provider-side authorization), so ordinary
	 * release editors cannot attach guessed asset IDs (PROJECT_PLAN.md
	 * Stage 1 deliverable 5).
	 */
	public const MANAGE_ASSETS_CAP = 'manage_mw_protected_assets';

	/**
	 * Register hooks owned by the foundation module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_filter( 'map_meta_cap', array( $this, 'map_asset_capability' ), 10, 2 );
	}

	/**
	 * Map the dedicated asset capability to concrete site capabilities.
	 *
	 * Defaults to administrators (`manage_options`); sites can broaden or
	 * narrow this through the `music_wave_manage_asset_caps` filter.
	 *
	 * @param mixed  $caps Primitive capabilities required so far.
	 * @param string $cap  Requested meta capability.
	 * @return array<int, string>|mixed
	 */
	public function map_asset_capability( $caps, $cap ) {
		if ( self::MANAGE_ASSETS_CAP !== $cap ) {
			return $caps;
		}

		/**
		 * Filter the primitive capabilities required to manage protected assets.
		 *
		 * @param array<int, string> $required Primitive capabilities.
		 */
		$required = apply_filters( 'music_wave_manage_asset_caps', array( 'manage_options' ) );

		return is_array( $required ) && array() !== $required ? $required : array( 'manage_options' );
	}

	/**
	 * Load translations from the plugin language directory.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'music-wave-core',
			false,
			dirname( plugin_basename( MUSIC_WAVE_CORE_FILE ) ) . '/languages'
		);
	}
}
