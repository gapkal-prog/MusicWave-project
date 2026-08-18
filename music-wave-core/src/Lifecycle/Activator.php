<?php
/**
 * Plugin activation lifecycle.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Lifecycle;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseTaxonomies;
use ManaCore\MusicWave\Core\Commerce\AccountLibrary;

final class Activator {
	/**
	 * Persist versions used by future idempotent migrations.
	 *
	 * @return void
	 */
	public static function activate(): void {
		update_option( 'music_wave_core_version', MUSIC_WAVE_CORE_VERSION, false );
		add_option( 'music_wave_schema_version', '0.0.0', '', false );

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			foreach ( ReleasePostType::primitive_capabilities() as $capability ) {
				$role->add_cap( $capability );
			}
		}

		( new ReleasePostType() )->register();
		( new ReleaseTaxonomies() )->register();
		AccountLibrary::register_endpoint();
		flush_rewrite_rules();
	}
}
