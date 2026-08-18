<?php
/**
 * Minimal dependency-free PSR-4 autoloader.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Support;

final class Autoloader {
	private const PREFIX = 'ManaCore\\MusicWave\\Core\\';

	/**
	 * Register the project autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class within the MusicWave Core namespace.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	private static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::PREFIX ) );
		$file           = dirname( __DIR__ ) . DIRECTORY_SEPARATOR
			. str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
