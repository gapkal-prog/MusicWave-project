<?php
/**
 * MusicWave checks surfaced through WordPress Site Health.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Support;

use ManaCore\MusicWave\Core\Migrations\MigrationRunner;

final class SiteHealth {
	/**
	 * Register direct, low-cost Site Health tests.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $tests Existing Site Health tests.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tests( array $tests ): array {
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}

		$tests['direct']['music_wave_schema']            = array(
			'label' => __( 'MusicWave database schema is current', 'music-wave-core' ),
			'test'  => array( $this, 'test_schema' ),
		);
		$tests['direct']['music_wave_permalinks']        = array(
			'label' => __( 'MusicWave uses pretty permalinks', 'music-wave-core' ),
			'test'  => array( $this, 'test_permalinks' ),
		);
		$tests['direct']['music_wave_download_provider'] = array(
			'label' => __( 'MusicWave protected delivery is configured', 'music-wave-core' ),
			'test'  => array( $this, 'test_download_provider' ),
		);
		$tests['direct']['music_wave_structured_data']   = array(
			'label' => __( 'MusicWave structured data avoids duplicate SEO schema', 'music-wave-core' ),
			'test'  => array( $this, 'test_structured_data' ),
		);

		return $tests;
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_schema(): array {
		$version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$ready   = version_compare( $version, MigrationRunner::LATEST_VERSION, '>=' );

		return $this->result(
			$ready ? __( 'MusicWave database schema is current', 'music-wave-core' ) : __( 'MusicWave database schema needs an update', 'music-wave-core' ),
			$ready ? 'good' : 'critical',
			$ready ? __( 'MusicWave content metadata is ready for the installed plugin version.', 'music-wave-core' ) : __( 'Visit the MusicWave setup page as an administrator to run the pending schema migration.', 'music-wave-core' ),
			'music_wave_schema'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_permalinks(): array {
		$ready = '' !== (string) get_option( 'permalink_structure', '' );

		return $this->result(
			$ready ? __( 'MusicWave pretty permalinks are enabled', 'music-wave-core' ) : __( 'MusicWave needs pretty permalinks', 'music-wave-core' ),
			$ready ? 'good' : 'recommended',
			$ready ? __( 'Release, artist, and taxonomy archives can use readable canonical URLs.', 'music-wave-core' ) : __( 'Save any non-default permalink structure to support predictable catalog and artist URLs.', 'music-wave-core' ),
			'music_wave_permalinks'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_download_provider(): array {
		$ready = has_filter( 'music_wave_download_provider' );

		return $this->result(
			$ready ? __( 'MusicWave protected delivery provider is available', 'music-wave-core' ) : __( 'MusicWave protected delivery provider is optional but unavailable', 'music-wave-core' ),
			$ready ? 'good' : 'recommended',
			$ready ? __( 'A provider can stream opaque protected assets after access checks pass.', 'music-wave-core' ) : __( 'Activate MusicWave VIP before assigning protected download assets to releases.', 'music-wave-core' ),
			'music_wave_download_provider'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_structured_data(): array {
		$has_competitor = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );

		return $this->result(
			$has_competitor ? __( 'MusicWave structured data is protected from duplicates', 'music-wave-core' ) : __( 'MusicWave public structured data is available', 'music-wave-core' ),
			'good',
			$has_competitor ? __( 'A recognized SEO plugin is active, so MusicWave JSON-LD stays disabled by default.', 'music-wave-core' ) : __( 'MusicWave can emit MusicRecording, MusicAlbum, and Podcast schema from public catalog fields.', 'music-wave-core' ),
			'music_wave_structured_data'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	private function result( string $label, string $status, string $description, string $test ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'MusicWave', 'music-wave-core' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ) . '">' . esc_html__( 'Open MusicWave setup', 'music-wave-core' ) . '</a></p>',
			'test'        => $test,
		);
	}
}
