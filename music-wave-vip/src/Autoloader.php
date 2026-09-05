<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Vip;

final class Autoloader {
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) ); }
	private static function load( string $class_name ): void {
		$prefix = 'ManaCore\\MusicWave\\Vip\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return; }
		$file = __DIR__ . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file; }
	}
}
