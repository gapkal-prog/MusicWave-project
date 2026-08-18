<?php
/**
 * Plugin deactivation lifecycle.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Lifecycle;

final class Deactivator {
	/**
	 * Keep user data intact and clear only runtime rewrite state.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}
}
