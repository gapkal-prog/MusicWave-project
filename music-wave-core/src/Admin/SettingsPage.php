<?php
/**
 * MusicWave administration hub and settings page.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Listening\ListeningRepository;
use ManaCore\MusicWave\Core\Migrations\MigrationRunner;
use ManaCore\MusicWave\Core\Playlists\PlaylistRepository;
use ManaCore\MusicWave\Core\Support\Settings;

final class SettingsPage {
	private const PAGE = 'music-wave-settings';

	/** @var BulkAccessManager */
	private $bulk_access;

	/** @var PlaylistRepository|null */
	private $playlists;

	/** @var ListeningRepository|null */
	private $listening;

	public function __construct( BulkAccessManager $bulk_access, ?PlaylistRepository $playlists = null, ?ListeningRepository $listening = null ) {
		$this->bulk_access = $bulk_access;
		$this->playlists   = $playlists;
		$this->listening   = $listening;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->bulk_access->register();
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . ReleasePostType::KEY,
			__( 'MusicWave settings', 'music-wave-core' ),
			__( 'Settings & overview', 'music-wave-core' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'music_wave_core',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'mw_release_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'music-wave-admin-settings',
			MUSIC_WAVE_CORE_URL . 'assets/admin-settings.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation tab, sanitized and allow-listed below.
		if ( ! in_array( $tab, array( 'overview', 'content', 'access', 'delivery', 'integrations', 'manage' ), true ) ) {
			$tab = 'overview';
		}

		$this->register_help_tabs();

		echo '<div class="wrap mw-settings"><h1>' . esc_html__( 'MusicWave control center', 'music-wave-core' ) . '</h1>';
		echo '<p class="mw-settings__lead">' . esc_html__( 'Manage catalog defaults, access behavior, delivery limits, integrations, and the WordPress screens that control presentation.', 'music-wave-core' ) . '</p>';
		$this->render_tabs( $tab );
		settings_errors();

		switch ( $tab ) {
			case 'content':
				$this->render_content_settings();
				break;
			case 'access':
				$this->render_access();
				break;
			case 'delivery':
				$this->render_delivery_settings();
				break;
			case 'integrations':
				$this->render_integrations();
				break;
			case 'manage':
				$this->render_management_links();
				break;
			case 'overview':
			default:
				$this->render_overview();
				break;
		}

		echo '</div>';
	}

	private function render_tabs( string $active ): void {
		$tabs = array(
			'overview'     => __( 'Overview', 'music-wave-core' ),
			'content'      => __( 'Content & display', 'music-wave-core' ),
			'access'       => __( 'Access', 'music-wave-core' ),
			'delivery'     => __( 'Delivery & privacy', 'music-wave-core' ),
			'integrations' => __( 'Integrations', 'music-wave-core' ),
			'manage'       => __( 'Management links', 'music-wave-core' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'MusicWave settings', 'music-wave-core' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$url   = add_query_arg(
				array(
					'post_type' => ReleasePostType::KEY,
					'page'      => self::PAGE,
					'tab'       => $key,
				),
				admin_url( 'edit.php' )
			);
			$class = $active === $key ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	private function render_overview(): void {
		$counts    = wp_count_posts( ReleasePostType::KEY );
		$published = isset( $counts->publish ) ? absint( $counts->publish ) : 0;
		$drafts    = isset( $counts->draft ) ? absint( $counts->draft ) : 0;
		$theme     = wp_get_theme();

		echo '<div class="mw-settings__grid mw-settings__grid--stats">';
		$this->stat( __( 'Published releases', 'music-wave-core' ), number_format_i18n( $published ), 'dashicons-album' );
		$this->stat( __( 'Draft releases', 'music-wave-core' ), number_format_i18n( $drafts ), 'dashicons-edit-page' );
		if ( null !== $this->playlists ) {
			$this->stat( __( 'Public playlists', 'music-wave-core' ), number_format_i18n( $this->playlists->count_public() ), 'dashicons-playlist' );
		}
		if ( null !== $this->listening ) {
			$this->stat( __( 'Listening events', 'music-wave-core' ), number_format_i18n( $this->listening->count_all() ), 'dashicons-controls-play' );
		}
		$this->stat( __( 'Active theme', 'music-wave-core' ), (string) $theme->get( 'Name' ), 'dashicons-admin-appearance' );
		$this->stat( __( 'Core version', 'music-wave-core' ), MUSIC_WAVE_CORE_VERSION, 'dashicons-update' );
		echo '</div>';

		echo '<div class="mw-settings__columns"><section class="mw-settings__panel"><h2>' . esc_html__( 'Quick actions', 'music-wave-core' ) . '</h2>';
		$this->action_link( admin_url( 'post-new.php?post_type=' . ReleasePostType::KEY ), __( 'Add a release', 'music-wave-core' ), __( 'Create and configure catalog content.', 'music-wave-core' ) );
		$this->action_link( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY ), __( 'Review all releases', 'music-wave-core' ), __( 'Check readiness and access status.', 'music-wave-core' ) );
		$this->action_link( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY . '&page=music-wave-setup' ), __( 'Setup & diagnostics', 'music-wave-core' ), __( 'Inspect schema, delivery, SEO, and environment health.', 'music-wave-core' ) );
		echo '</section><section class="mw-settings__panel"><h2>' . esc_html__( 'System status', 'music-wave-core' ) . '</h2>';
		$this->status_row( __( 'WooCommerce', 'music-wave-core' ), class_exists( 'WooCommerce' ), __( 'Product mapping and purchase access', 'music-wave-core' ) );
		$schema_version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$this->status_row(
			__( 'Catalog schema', 'music-wave-core' ),
			version_compare( $schema_version, MigrationRunner::LATEST_VERSION, '>=' ),
			sprintf(
				/* translators: 1: installed schema version, 2: required schema version. */
				__( 'Installed %1$s; required %2$s', 'music-wave-core' ),
				$schema_version,
				MigrationRunner::LATEST_VERSION
			)
		);
		$this->status_row( __( 'Protected delivery', 'music-wave-core' ), has_filter( 'music_wave_download_provider' ), __( 'Private files and secure downloads', 'music-wave-core' ) );
		$this->status_row( __( 'Membership provider', 'music-wave-core' ), has_filter( 'music_wave_membership_provider' ), __( 'Membership-based access decisions', 'music-wave-core' ) );
		$this->status_row( __( 'Companion block theme', 'music-wave-core' ), 'musicwave' === $theme->get_stylesheet(), __( 'MusicWave templates and styles', 'music-wave-core' ) );
		echo '</section></div>';
	}

	private function render_content_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'New release defaults', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'These values are stored when a new release is created. Existing releases are never silently rewritten.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->select_row( 'default_access_mode', __( 'Default access mode', 'music-wave-core' ), (string) $settings['default_access_mode'], $this->access_labels(), __( 'Use Restricted when new releases should never become public accidentally.', 'music-wave-core' ) );
		$this->number_row( 'default_preview_duration', __( 'Default preview duration', 'music-wave-core' ), (int) $settings['default_preview_duration'], 10, 120, __( 'Seconds; each release can override this value.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Catalog archive', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->number_row( 'archive_per_page', __( 'Releases per page', 'music-wave-core' ), (int) $settings['archive_per_page'], 1, 100, __( 'Controls the public MusicWave archive only.', 'music-wave-core' ) );
		$this->select_row( 'archive_default_sort', __( 'Default sorting', 'music-wave-core' ), (string) $settings['archive_default_sort'], ReleaseArchiveQuery::sort_options(), __( 'Visitors can still choose another sorting mode.', 'music-wave-core' ) );
		$toggles = array(
			'enabled'  => __( 'Enabled', 'music-wave-core' ),
			'disabled' => __( 'Disabled', 'music-wave-core' ),
		);
		$this->select_row( 'show_same_artist_releases', __( 'More from the same artist', 'music-wave-core' ), (string) $settings['show_same_artist_releases'], $toggles, __( 'Shows other published releases sharing an artist on single release pages.', 'music-wave-core' ) );
		$this->select_row( 'show_similar_releases', __( 'Similar releases', 'music-wave-core' ), (string) $settings['show_similar_releases'], $toggles, __( 'Uses shared genre, mood, and release type terms without exposing private access metadata.', 'music-wave-core' ) );
		$this->number_row( 'related_items_per_section', __( 'Related items per section', 'music-wave-core' ), (int) $settings['related_items_per_section'], 2, 12, __( 'Limits each related section to a predictable, low-cost query.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Featured release slider', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row( 'slider_enabled', __( 'Slider', 'music-wave-core' ), (string) $settings['slider_enabled'], $toggles, __( 'Globally enables or disables MusicWave release sliders. Individual blocks can still inherit or override this setting.', 'music-wave-core' ) );
		$this->select_row( 'slider_autoplay', __( 'Autoplay', 'music-wave-core' ), (string) $settings['slider_autoplay'], $toggles, __( 'Autoplay is automatically disabled for visitors who prefer reduced motion.', 'music-wave-core' ) );
		$this->select_row( 'slider_loop', __( 'Loop', 'music-wave-core' ), (string) $settings['slider_loop'], $toggles, __( 'Returns to the beginning after the final group of releases.', 'music-wave-core' ) );
		$this->select_row( 'slider_pause_on_hover', __( 'Pause on hover or focus', 'music-wave-core' ), (string) $settings['slider_pause_on_hover'], $toggles, __( 'Prevents automatic movement while a visitor is interacting with the slider.', 'music-wave-core' ) );
		$this->select_row( 'slider_show_arrows', __( 'Navigation arrows', 'music-wave-core' ), (string) $settings['slider_show_arrows'], $toggles, __( 'Shows previous and next controls.', 'music-wave-core' ) );
		$this->select_row( 'slider_show_dots', __( 'Pagination dots', 'music-wave-core' ), (string) $settings['slider_show_dots'], $toggles, __( 'Shows the current slide group and direct navigation controls.', 'music-wave-core' ) );
		$this->number_row( 'slider_interval', __( 'Autoplay interval', 'music-wave-core' ), (int) $settings['slider_interval'], 2000, 20000, __( 'Milliseconds between automatic movements.', 'music-wave-core' ) );
		$this->number_row( 'slider_items', __( 'Releases loaded', 'music-wave-core' ), (int) $settings['slider_items'], 3, 12, __( 'Maximum number of release cards queried by each slider.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Persistent playback', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row( 'persistent_navigation', __( 'Keep playing across pages', 'music-wave-core' ), (string) $settings['persistent_navigation'], $toggles, __( 'Swaps page content in the background so the music preview player keeps playing while visitors browse. Cart, checkout, and admin screens are never intercepted.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'SEO and structured data', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row(
			'json_ld_mode',
			__( 'Music structured data', 'music-wave-core' ),
			(string) $settings['json_ld_mode'],
			array(
				'auto'     => __( 'Automatic conflict detection', 'music-wave-core' ),
				'enabled'  => __( 'Always enabled', 'music-wave-core' ),
				'disabled' => __( 'Disabled', 'music-wave-core' ),
			),
			__( 'Automatic mode disables Core JSON-LD when a recognized SEO plugin is active.', 'music-wave-core' )
		);
		echo '</table></section>';
		submit_button( __( 'Save content settings', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_access(): void {
		$notice = $this->bulk_access->consume_notice();
		if ( is_array( $notice ) ) {
			$type = isset( $notice['type'] ) ? sanitize_html_class( (string) $notice['type'] ) : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}
		$job = $this->bulk_access->active_job();
		if ( is_array( $job ) ) {
			echo '<section class="mw-settings__panel mw-settings__panel--job"><h2>' . esc_html__( 'Bulk access job in progress', 'music-wave-core' ) . '</h2>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: number of releases updated, 2: number of releases skipped. */
					__( '%1$d updated, %2$d skipped. Continue to process the next secure batch.', 'music-wave-core' ),
					(int) $job['updated'],
					(int) $job['skipped']
				)
			) . '</p>';
			echo '<div class="mw-settings__actions">';
			$this->job_form( 'music_wave_bulk_access_continue', __( 'Continue next batch', 'music-wave-core' ), 'button button-primary' );
			$this->job_form( 'music_wave_bulk_access_cancel', __( 'Cancel job', 'music-wave-core' ), 'button button-secondary' );
			echo '</div></section>';
		}

		$settings = Settings::all();
		$this->settings_form_start();
		$absent = (string) $settings['membership_absent_behavior'];
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Membership module behavior', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'When no membership module (such as MusicWave VIP) is active, membership-gated releases can stay usable for everyone or deny access until a module returns. Switching the VIP master switch off opens the gate the same way while secure delivery keeps running.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		echo '<tr><th>' . esc_html__( 'While no membership module is active', 'music-wave-core' ) . '</th><td><label style="display:block;margin-bottom:6px;"><input type="radio" name="' . esc_attr( Settings::OPTION ) . '[membership_absent_behavior]" value="allow" ' . checked( $absent, 'allow', false ) . '> ' . esc_html__( 'Open access — membership releases stay free (recommended)', 'music-wave-core' ) . '</label><label style="display:block;"><input type="radio" name="' . esc_attr( Settings::OPTION ) . '[membership_absent_behavior]" value="deny" ' . checked( $absent, 'deny', false ) . '> ' . esc_html__( 'Restrict — deny access until a membership module is active', 'music-wave-core' ) . '</label></td></tr>';
		echo '</table></section>';
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Customer-facing access messages', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Leave a field blank to use the translated MusicWave default. Custom text is escaped before output.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->text_row( 'purchase_message', __( 'Purchase message', 'music-wave-core' ), (string) $settings['purchase_message'] );
		$this->text_row( 'purchase_cta_label', __( 'Purchase button label', 'music-wave-core' ), (string) $settings['purchase_cta_label'] );
		$this->text_row( 'membership_message', __( 'Membership message', 'music-wave-core' ), (string) $settings['membership_message'] );
		$this->text_row( 'membership_cta_label', __( 'Membership button label', 'music-wave-core' ), (string) $settings['membership_cta_label'] );
		$this->url_row( 'membership_cta_url', __( 'Membership page URL', 'music-wave-core' ), (string) $settings['membership_cta_url'] );
		$this->text_row( 'restricted_message', __( 'Restricted message', 'music-wave-core' ), (string) $settings['restricted_message'] );
		$this->text_row( 'access_granted_message', __( 'Access granted message', 'music-wave-core' ), (string) $settings['access_granted_message'] );
		echo '</table></section>';
		submit_button( __( 'Save access messages', 'music-wave-core' ) );
		echo '</form>';

		if ( null === $job ) {
			$this->render_bulk_form();
		}
	}

	private function render_delivery_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Secure downloads', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Bound token issuance and file delivery per visitor. Download links stay signed, short-lived, and single-use regardless of these limits.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'download_rate_limit', __( 'Token requests per window', 'music-wave-core' ), (int) $settings['download_rate_limit'], 1, 1000, __( 'Maximum download or stream tokens one visitor can request before being throttled.', 'music-wave-core' ) );
		$this->number_row( 'download_rate_window', __( 'Rate limit window (seconds)', 'music-wave-core' ), (int) $settings['download_rate_window'], 10, 3600, __( 'Length of the fixed window used by the token rate limit.', 'music-wave-core' ) );
		$this->number_row( 'download_daily_quota', __( 'Daily delivery quota', 'music-wave-core' ), (int) $settings['download_daily_quota'], 0, 10000, __( 'Completed file deliveries allowed per visitor per day. Set 0 for no daily cap.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Catalog search & discovery', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Autocomplete, filters, and recommendations are public endpoints. These limits keep them fast and resistant to abuse.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'discovery_rate_limit', __( 'Requests per window', 'music-wave-core' ), (int) $settings['discovery_rate_limit'], 1, 1000, __( 'Maximum discovery requests one visitor can make before being throttled.', 'music-wave-core' ) );
		$this->number_row( 'discovery_rate_window', __( 'Rate limit window (seconds)', 'music-wave-core' ), (int) $settings['discovery_rate_window'], 10, 3600, __( 'Length of the fixed window used by the discovery rate limit.', 'music-wave-core' ) );
		$this->number_row( 'discovery_cache_ttl', __( 'Search cache lifetime (seconds)', 'music-wave-core' ), (int) $settings['discovery_cache_ttl'], 30, 86400, __( 'How long suggestion and filter results are cached. Longer values reduce database load; catalog changes appear later.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Listening history', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Playback activity is stored only for visitors who consented to listening history. Older entries are pruned during the daily cleanup, and users can erase their own history from their account.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'listening_retention_days', __( 'Retention period (days)', 'music-wave-core' ), (int) $settings['listening_retention_days'], 1, 3650, __( 'Activity older than this window is deleted automatically.', 'music-wave-core' ) );
		echo '</table></section>';

		submit_button( __( 'Save delivery & privacy settings', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_bulk_form(): void {
		$types = get_terms(
			array(
				'taxonomy'   => 'mw_release_type',
				'hide_empty' => false,
			)
		);
		echo '<section class="mw-settings__panel mw-settings__panel--danger"><h2>' . esc_html__( 'Bulk access mode', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Update releases in batches of 200. Purchase and membership modes are applied only when each release already has the required product or level mapping; incomplete releases are skipped.', 'music-wave-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'music_wave_bulk_access_start' );
		echo '<input type="hidden" name="action" value="music_wave_bulk_access_start"><div class="mw-settings__bulk-grid">';
		$this->plain_select( 'target_mode', __( 'New access mode', 'music-wave-core' ), $this->access_labels(), 'restricted' );
		$this->plain_select( 'current_mode', __( 'Current mode', 'music-wave-core' ), array_merge( array( 'any' => __( 'Any mode', 'music-wave-core' ) ), $this->access_labels() ), 'any' );
		$this->plain_select(
			'post_status',
			__( 'Post status', 'music-wave-core' ),
			array(
				'any'     => __( 'Any active status', 'music-wave-core' ),
				'publish' => __( 'Published', 'music-wave-core' ),
				'draft'   => __( 'Draft', 'music-wave-core' ),
				'pending' => __( 'Pending review', 'music-wave-core' ),
				'private' => __( 'Private', 'music-wave-core' ),
				'future'  => __( 'Scheduled', 'music-wave-core' ),
			),
			'any'
		);
		$options = array( 'any' => __( 'Any release type', 'music-wave-core' ) );
		if ( is_array( $types ) ) {
			foreach ( $types as $type ) {
				$options[ $type->slug ] = $type->name;
			}
		}
		$this->plain_select( 'release_type', __( 'Release type', 'music-wave-core' ), $options, 'any' );
		echo '</div><label class="mw-settings__confirm"><input type="checkbox" required> ' . esc_html__( 'I understand this changes access policy for every matching release.', 'music-wave-core' ) . '</label>';
		submit_button( __( 'Start bulk update', 'music-wave-core' ), 'primary', 'submit', false );
		echo '</form></section>';
	}

	private function render_integrations(): void {
		$theme = wp_get_theme();
		$cards = array(
			array(
				'id'          => 'woocommerce',
				'name'        => __( 'WooCommerce', 'music-wave-core' ),
				'active'      => class_exists( 'WooCommerce' ),
				'description' => __( 'Products, purchase ownership, checkout, and customer library.', 'music-wave-core' ),
				'url'         => class_exists( 'WooCommerce' ) ? admin_url( 'admin.php?page=wc-settings' ) : admin_url( 'plugin-install.php?s=WooCommerce&tab=search&type=term' ),
				'action'      => class_exists( 'WooCommerce' ) ? __( 'Open WooCommerce settings', 'music-wave-core' ) : __( 'Install WooCommerce', 'music-wave-core' ),
			),
			array(
				'id'          => 'music-wave-vip',
				'name'        => __( 'MusicWave VIP', 'music-wave-core' ),
				'active'      => defined( 'MUSIC_WAVE_VIP_FILE' ),
				'description' => __( 'Protected local files and role-based membership adapter.', 'music-wave-core' ),
				'url'         => defined( 'MUSIC_WAVE_VIP_FILE' ) ? admin_url( 'edit.php?post_type=mw_release&page=music-wave-vip' ) : admin_url( 'plugins.php' ),
				'action'      => defined( 'MUSIC_WAVE_VIP_FILE' ) ? __( 'Open standalone settings', 'music-wave-core' ) : __( 'Review plugins', 'music-wave-core' ),
			),
			array(
				'id'          => 'musicwave-theme',
				'name'        => __( 'MusicWave theme', 'music-wave-core' ),
				'active'      => 'musicwave' === $theme->get_stylesheet(),
				'description' => __( 'Block templates, styles, patterns, and visual editing.', 'music-wave-core' ),
				'url'         => admin_url( 'site-editor.php' ),
				'action'      => __( 'Open Site Editor', 'music-wave-core' ),
			),
		);

		/**
		 * Filter integration cards shown in the MusicWave control center.
		 *
		 * @param array<int, array<string, mixed>> $cards Integration definitions.
		 */
		$cards = apply_filters( 'music_wave_admin_integrations', $cards );
		echo '<div class="mw-settings__grid mw-settings__grid--cards">';
		if ( is_array( $cards ) ) {
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) || empty( $card['name'] ) ) {
					continue;
				}
				$active = ! empty( $card['active'] );
				echo '<section class="mw-settings__panel mw-integration"><div class="mw-integration__heading"><h2>' . esc_html( (string) $card['name'] ) . '</h2><span class="mw-status mw-status--' . esc_attr( $active ? 'active' : 'inactive' ) . '">' . esc_html( $active ? __( 'Active', 'music-wave-core' ) : __( 'Inactive', 'music-wave-core' ) ) . '</span></div>';
				echo '<p>' . esc_html( isset( $card['description'] ) ? (string) $card['description'] : '' ) . '</p>';
				if ( ! empty( $card['url'] ) ) {
					echo '<a class="button button-secondary" href="' . esc_url( (string) $card['url'] ) . '">' . esc_html( isset( $card['action'] ) ? (string) $card['action'] : __( 'Manage', 'music-wave-core' ) ) . '</a>';
				}
				echo '</section>';
			}
		}
		echo '</div>';

		$this->render_metadata_provider_settings();

		/**
		 * Render settings supplied by active MusicWave integration plugins.
		 */
		do_action( 'music_wave_admin_integration_settings' );
	}

	/**
	 * Render optional music metadata provider API keys. Leave blank to use the
	 * built-in keyless MusicBrainz + Cover Art Archive lookup.
	 *
	 * @return void
	 */
	private function render_metadata_provider_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'Music metadata auto-fill', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Release editors can auto-fill title, official description, artist, album, year, genres, moods, labels, and cover art. Searches adapt to tracks, albums, playlists, and podcasts. No key is required: the built-in MusicBrainz and Cover Art Archive lookup works out of the box. Optional Spotify or Discogs credentials can provide additional catalog details; rate limits fall back automatically.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->text_row( 'spotify_client_id', __( 'Spotify client ID', 'music-wave-core' ), (string) $settings['spotify_client_id'] );
		$this->text_row( 'spotify_client_secret', __( 'Spotify client secret', 'music-wave-core' ), (string) $settings['spotify_client_secret'] );
		$this->text_row( 'discogs_token', __( 'Discogs personal access token', 'music-wave-core' ), (string) $settings['discogs_token'] );
		echo '</table></section>';
		submit_button( __( 'Save metadata provider keys', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_management_links(): void {
		$links = array(
			array( __( 'Site Editor', 'music-wave-core' ), __( 'Edit templates, template parts, header, footer, and global styles.', 'music-wave-core' ), admin_url( 'site-editor.php' ), 'dashicons-edit-site' ),
			array( __( 'Widgets', 'music-wave-core' ), __( 'Manage widget areas when the active theme or plugin registers them.', 'music-wave-core' ), admin_url( 'widgets.php' ), 'dashicons-screenoptions' ),
			array( __( 'Navigation', 'music-wave-core' ), __( 'Manage navigation blocks and menus.', 'music-wave-core' ), admin_url( 'site-editor.php?path=%2Fnavigation' ), 'dashicons-menu' ),
			array( __( 'Media Library', 'music-wave-core' ), __( 'Manage cover artwork and public preview media.', 'music-wave-core' ), admin_url( 'upload.php' ), 'dashicons-format-audio' ),
			array( __( 'Artists', 'music-wave-core' ), __( 'Edit artist biographies, images, and canonical URLs.', 'music-wave-core' ), admin_url( 'edit-tags.php?taxonomy=mw_artist&post_type=' . ReleasePostType::KEY ), 'dashicons-groups' ),
			array( __( 'Release types', 'music-wave-core' ), __( 'Manage the classification used by tracks, albums, and podcasts.', 'music-wave-core' ), admin_url( 'edit-tags.php?taxonomy=mw_release_type&post_type=' . ReleasePostType::KEY ), 'dashicons-category' ),
			array( __( 'Permalinks', 'music-wave-core' ), __( 'Configure canonical catalog and taxonomy URL structure.', 'music-wave-core' ), admin_url( 'options-permalink.php' ), 'dashicons-admin-links' ),
			array( __( 'Users', 'music-wave-core' ), __( 'Manage administrators, editors, customers, and membership roles.', 'music-wave-core' ), admin_url( 'users.php' ), 'dashicons-admin-users' ),
		);
		echo '<div class="mw-settings__grid mw-settings__grid--links">';
		foreach ( $links as $link ) {
			echo '<a class="mw-management-link" href="' . esc_url( $link[2] ) . '"><span class="dashicons ' . esc_attr( $link[3] ) . '" aria-hidden="true"></span><span><strong>' . esc_html( $link[0] ) . '</strong><small>' . esc_html( $link[1] ) . '</small></span></a>';
		}
		echo '</div>';
	}

	/**
	 * Register contextual help tabs for the settings screen.
	 *
	 * @return void
	 */
	private function register_help_tabs(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! method_exists( $screen, 'add_help_tab' ) ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-settings-guide',
				'title'   => __( 'MusicWave settings', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'The control center groups MusicWave configuration by concern:', 'music-wave-core' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'Content & display — new release defaults, archive behavior, sliders, playback, and structured data.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'Access — membership fallback behavior, customer-facing messages, and bulk access tools.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'Delivery & privacy — download and search rate limits, delivery quotas, caching, and listening-history retention.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'Integrations — WooCommerce, MusicWave VIP, the block theme, and metadata provider keys.', 'music-wave-core' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html__( 'Each tab saves independently; settings on the other tabs are preserved.', 'music-wave-core' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-settings-diagnostics',
				'title'   => __( 'Help & diagnostics', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'If something does not behave as expected, start with the environment checks and the quick-start guide, then review the WordPress Site Health screen.', 'music-wave-core' ) . '</p>' .
					'<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY . '&page=music-wave-setup' ) ) . '">' . esc_html__( 'Open Setup & diagnostics', 'music-wave-core' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Open Site Health', 'music-wave-core' ) . '</a></p>',
			)
		);
	}

	private function settings_form_start(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'music_wave_core' );
	}

	/** @param array<string, string> $options */
	private function select_row( string $key, string $label, string $value, array $options, string $description ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><select id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $options as $option => $option_label ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html( $description ) . '</p></td></tr>';
	}

	private function number_row( string $key, string $label, int $value, int $min, int $max, string $description ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="number" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) $value ) . '"><p class="description">' . esc_html( $description ) . '</p></td></tr>';
	}

	private function text_row( string $key, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="text" value="' . esc_attr( $value ) . '"></td></tr>';
	}

	private function url_row( string $key, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text code" id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="url" value="' . esc_attr( $value ) . '" placeholder="https://"></td></tr>';
	}

	/** @param array<string, string> $options */
	private function plain_select( string $name, string $label, array $options, string $selected_value ): void {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected_value, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private function stat( string $label, string $value, string $icon ): void {
		echo '<section class="mw-stat"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span><div><strong>' . esc_html( $value ) . '</strong><span>' . esc_html( $label ) . '</span></div></section>';
	}

	private function status_row( string $label, bool $active, string $description ): void {
		echo '<div class="mw-status-row"><span class="mw-status mw-status--' . esc_attr( $active ? 'active' : 'inactive' ) . '">' . esc_html( $active ? __( 'Ready', 'music-wave-core' ) : __( 'Unavailable', 'music-wave-core' ) ) . '</span><div><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $description ) . '</small></div></div>';
	}

	private function action_link( string $url, string $label, string $description ): void {
		echo '<a class="mw-action-link" href="' . esc_url( $url ) . '"><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $description ) . '</small><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>';
	}

	private function job_form( string $action, string $label, string $class_name ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><button class="' . esc_attr( $class_name ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * @return array<string, string>
	 */
	private function access_labels(): array {
		return array(
			'public'                 => __( 'Public', 'music-wave-core' ),
			'purchase'               => __( 'Purchase required', 'music-wave-core' ),
			'membership'             => __( 'Membership required', 'music-wave-core' ),
			'purchase_or_membership' => __( 'Purchase or membership', 'music-wave-core' ),
			'restricted'             => __( 'Restricted / unavailable', 'music-wave-core' ),
		);
	}
}
