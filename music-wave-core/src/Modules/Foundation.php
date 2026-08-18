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
	 * Register hooks owned by the foundation module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
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
