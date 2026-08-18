<?php
/**
 * Initial catalog schema migration marker.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Migrations;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\Migration;

final class Schema020 implements Migration {
	public function version(): string {
		return '0.2.0';
	}

	public function up(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			foreach ( ReleasePostType::primitive_capabilities() as $capability ) {
				$role->add_cap( $capability );
			}
		}

		update_option( 'music_wave_core_version', MUSIC_WAVE_CORE_VERSION, false );
		flush_rewrite_rules();
	}
}
