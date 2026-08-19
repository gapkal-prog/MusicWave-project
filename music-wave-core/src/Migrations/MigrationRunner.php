<?php
/**
 * Versioned schema migration runner.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Migrations;

use ManaCore\MusicWave\Core\Contracts\Migration;
use RuntimeException;

final class MigrationRunner {
	public const OPTION         = 'music_wave_schema_version';
	public const LATEST_VERSION = '0.9.0';

	/** @var array<int, Migration> */
	private $migrations;

	/** @param array<int, Migration> $migrations Ordered migrations. */
	public function __construct( array $migrations ) {
		usort(
			$migrations,
			static function ( Migration $left, Migration $right ): int {
				return version_compare( $left->version(), $right->version() );
			}
		);
		$this->migrations = $migrations;
	}

	/** @return array<int, Migration> */
	public function pending( string $current_version ): array {
		return array_values(
			array_filter(
				$this->migrations,
				static function ( Migration $migration ) use ( $current_version ): bool {
					return version_compare( $migration->version(), $current_version, '>' );
				}
			)
		);
	}

	/**
	 * Run pending migrations for administrators only.
	 *
	 * @return void
	 * @throws RuntimeException When the schema version cannot be persisted.
	 */
	public function maybe_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = (string) get_option( self::OPTION, '0.0.0' );
		foreach ( $this->pending( $current ) as $migration ) {
			$migration->up();
			if ( ! update_option( self::OPTION, $migration->version(), false ) ) {
				$stored = (string) get_option( self::OPTION, '0.0.0' );
				if ( $stored !== $migration->version() ) {
					throw new RuntimeException( 'MusicWave could not persist its schema version.' );
				}
			}
			$current = $migration->version();
		}
	}
}
