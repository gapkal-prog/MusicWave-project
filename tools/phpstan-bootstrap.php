<?php
/**
 * PHPStan bootstrap: define runtime constants that WordPress and the plugin
 * normally provide, so static analysis does not flag "constant not found".
 *
 * @package ManaCore\MusicWave\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/stubs/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'MUSIC_WAVE_CORE_URL' ) ) {
	define( 'MUSIC_WAVE_CORE_URL', 'http://example.com/wp-content/plugins/music-wave-core/' );
}
if ( ! defined( 'MUSIC_WAVE_CORE_PATH' ) ) {
	define( 'MUSIC_WAVE_CORE_PATH', __DIR__ . '/../music-wave-core/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! defined( 'EP_ROOT' ) ) { define( 'EP_ROOT', 1 ); }
if ( ! defined( 'EP_PAGES' ) ) { define( 'EP_PAGES', 1 ); }

