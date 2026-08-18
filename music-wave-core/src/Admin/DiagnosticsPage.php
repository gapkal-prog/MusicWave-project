<?php
/**
 * Guided setup and diagnostics administration page.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Migrations\MigrationRunner;
use ManaCore\MusicWave\Core\Catalog\DemoContentImporter;

final class DiagnosticsPage {
	private const DEMO_NOTICE_KEY = 'music_wave_demo_notice_';

	/** @var DemoContentImporter */
	private $demo_importer;

	public function __construct( DemoContentImporter $demo_importer ) {
		$this->demo_importer = $demo_importer;
	}

	/**
	 * Register the MusicWave setup submenu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_music_wave_import_demo', array( $this, 'import_demo' ) );
		add_action( 'admin_post_music_wave_remove_demo', array( $this, 'remove_demo' ) );
	}

	/**
	 * Add the page beneath the MusicWave release menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=mw_release',
			__( 'MusicWave setup', 'music-wave-core' ),
			__( 'Setup & diagnostics', 'music-wave-core' ),
			'manage_options',
			'music-wave-setup',
			array( $this, 'render' )
		);
	}

	/**
	 * Render a concise merchant-focused installation guide and health summary.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checks = $this->checks();
		echo '<div class="wrap"><h1>' . esc_html__( 'MusicWave setup & diagnostics', 'music-wave-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Complete these checks before importing catalog data or opening the store to customers.', 'music-wave-core' ) . '</p>';
		echo '<h2>' . esc_html__( 'Quick start', 'music-wave-core' ) . '</h2><ol>';
		echo '<li><a href="' . esc_url( admin_url( 'post-new.php?post_type=mw_release' ) ) . '">' . esc_html__( 'Create the first release', 'music-wave-core' ) . '</a></li>';
		echo '<li><a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=mw_release_type&post_type=mw_release' ) ) . '">' . esc_html__( 'Review release types and artists', 'music-wave-core' ) . '</a></li>';
		echo '<li><a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Save permalink settings', 'music-wave-core' ) . '</a></li>';
		echo '<li>' . esc_html__( 'Activate MusicWave VIP and configure a protected directory before enabling private downloads.', 'music-wave-core' ) . '</li>';
		echo '</ol><h2>' . esc_html__( 'Environment checks', 'music-wave-core' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'music-wave-core' ) . '</th><th>' . esc_html__( 'Status', 'music-wave-core' ) . '</th><th>' . esc_html__( 'Details', 'music-wave-core' ) . '</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			$status = $check['passed'] ? __( 'Ready', 'music-wave-core' ) : __( 'Needs attention', 'music-wave-core' );
			echo '<tr><td><strong>' . esc_html( $check['label'] ) . '</strong></td><td>' . esc_html( $status ) . '</td><td>' . esc_html( $check['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table><p><a class="button button-secondary" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Open WordPress Site Health', 'music-wave-core' ) . '</a></p>';
		$this->render_demo_section();
		echo '</div>';
	}

	/**
	 * Import only explicit demo content after a privileged nonce-protected request.
	 *
	 * @return void
	 */
	public function import_demo(): void {
		$this->handle_demo_action( 'music_wave_import_demo', __( 'Demo catalog imported.', 'music-wave-core' ) );
	}

	/**
	 * Remove only importer-marked demo content after explicit confirmation.
	 *
	 * @return void
	 */
	public function remove_demo(): void {
		$this->handle_demo_action( 'music_wave_remove_demo', __( 'Demo catalog removed.', 'music-wave-core' ) );
	}

	private function render_demo_section(): void {
		$user_id = get_current_user_id();
		$notice  = get_transient( self::DEMO_NOTICE_KEY . $user_id );
		if ( is_array( $notice ) ) {
			delete_transient( self::DEMO_NOTICE_KEY . $user_id );
			$class = ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}

		echo '<hr><h2>' . esc_html__( 'Demo catalog', 'music-wave-core' ) . '</h2><p>' . esc_html__( 'Create four public sample releases: a track, album, podcast show, and podcast episode. The importer never modifies existing merchant releases and skips any conflicting URL slug.', 'music-wave-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'music_wave_import_demo' );
		echo '<input type="hidden" name="action" value="music_wave_import_demo"><button class="button button-primary" type="submit">' . esc_html__( 'Import demo catalog', 'music-wave-core' ) . '</button></form>';
		if ( $this->demo_importer->has_demo_content() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
			wp_nonce_field( 'music_wave_remove_demo' );
			echo '<input type="hidden" name="action" value="music_wave_remove_demo"><button class="button-link-delete" type="submit">' . esc_html__( 'Remove MusicWave demo releases', 'music-wave-core' ) . '</button></form>';
		}
		echo '<p class="description">' . esc_html__( 'Demo releases contain no protected asset, product, customer, or membership data. Removing them preserves any terms that you may already be using.', 'music-wave-core' ) . '</p>';
	}

	private function handle_demo_action( string $nonce_action, string $success_message ): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'You are not allowed to manage MusicWave demo content.', 'music-wave-core' ) );
		}

		$result  = 'music_wave_import_demo' === $nonce_action ? $this->demo_importer->import() : $this->demo_importer->remove();
		$message = $success_message;
		if ( ! empty( $result['messages'] ) && is_array( $result['messages'] ) ) {
			$message .= ' ' . implode( ' ', $result['messages'] );
		}
		set_transient(
			self::DEMO_NOTICE_KEY . get_current_user_id(),
			array(
				'message' => $message,
				'error'   => false,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) );
		exit;
	}

	/**
	 * @return array<int, array<string, bool|string>>
	 */
	private function checks(): array {
		$schema_version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$theme          = wp_get_theme();

		return array(
			array(
				'label'  => __( 'MusicWave schema', 'music-wave-core' ),
				'passed' => version_compare( $schema_version, MigrationRunner::LATEST_VERSION, '>=' ),
				'detail' => sprintf(
					/* translators: 1: installed schema version, 2: required schema version. */
					__( 'Installed schema: %1$s; required: %2$s.', 'music-wave-core' ),
					$schema_version,
					MigrationRunner::LATEST_VERSION
				),
			),
			array(
				'label'  => __( 'Pretty permalinks', 'music-wave-core' ),
				'passed' => '' !== (string) get_option( 'permalink_structure', '' ),
				'detail' => __( 'Release, artist, and catalog URLs need a non-default permalink structure.', 'music-wave-core' ),
			),
			array(
				'label'  => __( 'WooCommerce', 'music-wave-core' ),
				'passed' => class_exists( 'WooCommerce' ),
				'detail' => __( 'Required for product mapping, purchase ownership, and paid releases.', 'music-wave-core' ),
			),
			array(
				'label'  => __( 'Protected delivery', 'music-wave-core' ),
				'passed' => defined( 'MUSIC_WAVE_VIP_FILE' ) && has_filter( 'music_wave_download_provider' ),
				'detail' => __( 'MusicWave VIP is required when any release uses private files.', 'music-wave-core' ),
			),
			array(
				'label'  => __( 'MusicWave block theme', 'music-wave-core' ),
				'passed' => 'musicwave' === $theme->get_stylesheet(),
				'detail' => __( 'The companion theme provides the catalog, artist, and commerce presentation.', 'music-wave-core' ),
			),
			array(
				'label'  => __( 'Music structured data', 'music-wave-core' ),
				'passed' => ! $this->has_competing_seo_plugin(),
				'detail' => $this->has_competing_seo_plugin() ? __( 'An SEO plugin is active, so MusicWave JSON-LD is disabled by default to prevent duplicate schema.', 'music-wave-core' ) : __( 'MusicWave can emit public release JSON-LD. Enable it through the documented filter only when no other plugin emits equivalent Music schema.', 'music-wave-core' ),
			),
		);
	}

	private function has_competing_seo_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}
}
