<?php
/**
 * Entitlement-aware MusicWave customer library.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Commerce;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Library\LibraryRepository;

final class AccountLibrary {
	public const ENDPOINT = 'music-library';

	/** @var AccessPolicyEngine */
	private $policy;

	/** @var ReleaseRepository */
	private $releases;

	/** @var LibraryRepository|null */
	private $library;

	public function __construct( AccessPolicyEngine $policy, ReleaseRepository $releases, ?LibraryRepository $library = null ) {
		$this->policy   = $policy;
		$this->releases = $releases;
		$this->library  = $library;
	}

	/**
	 * Register the account endpoint and its WooCommerce view hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_endpoint' ) );
		add_action( 'init', array( $this, 'register_dashboard_block' ), 20 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 40 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_shortcode( 'musicwave_dashboard', array( $this, 'dashboard_shortcode' ) );
	}

	/**
	 * Register the portable account dashboard block.
	 */
	public function register_dashboard_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		\ManaCore\MusicWave\Core\Blocks\BlockSupport::register_dynamic(
			'music-wave/account-dashboard',
			array( $this, 'render_dashboard_block' ),
			array(
				'api_version' => 3,
				'attributes'  => array(
					'showLibrary'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showQuickLinks' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStats'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'supports'    => \ManaCore\MusicWave\Core\Blocks\BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the dashboard block without leaking output buffering.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_dashboard_block( array $attributes ): string {
		ob_start();
		$this->render_dashboard( $attributes );

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode counterpart for classic builders and account pages.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 */
	public function dashboard_shortcode( $attributes = array() ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$attributes = shortcode_atts(
			array(
				'show_library'     => 'yes',
				'show_quick_links' => 'yes',
				'show_stats'       => 'yes',
			),
			$attributes,
			'musicwave_dashboard'
		);

		return $this->render_dashboard_block(
			array(
				'showLibrary'    => 'yes' === $attributes['show_library'],
				'showQuickLinks' => 'yes' === $attributes['show_quick_links'],
				'showStats'      => 'yes' === $attributes['show_stats'],
			)
		);
	}

	/**
	 * Render a complete customer dashboard with expandable account sections.
	 *
	 * The quick links act as tabs revealing their panel in place: the personal
	 * and entitled music library, WooCommerce orders and account details (only
	 * while WooCommerce is active), and the VIP membership area (only while
	 * the MusicWave VIP plugin is active).
	 *
	 * @param array<string, mixed> $attributes Dashboard display attributes.
	 */
	private function render_dashboard( array $attributes ): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			echo '<section class="mw-user-dashboard mw-user-dashboard--guest"><div class="mw-user-dashboard__welcome"><span class="mw-user-dashboard__eyebrow">' . esc_html__( 'Your MusicWave', 'music-wave-core' ) . '</span><h2>' . esc_html__( 'Sign in to open your music dashboard', 'music-wave-core' ) . '</h2><p>' . esc_html__( 'Access purchases, protected downloads, account details, and your personal music library.', 'music-wave-core' ) . '</p><a class="wp-element-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Sign in', 'music-wave-core' ) . '</a></div></section>';
			return;
		}

		$user = wp_get_current_user();
		$name = '' !== $user->display_name ? $user->display_name : $user->user_login;

		echo '<section class="mw-user-dashboard" data-mw-dashboard><header class="mw-user-dashboard__welcome">' . get_avatar( $user_id, 88, '', '', array( 'class' => 'mw-user-dashboard__avatar' ) ) . '<div><span class="mw-user-dashboard__eyebrow">' . esc_html__( 'Welcome back', 'music-wave-core' ) . '</span><h2>' . esc_html( $name ) . '</h2><p>' . esc_html__( 'Your account, music access, downloads, and listening shortcuts in one place.', 'music-wave-core' ) . '</p></div></header>';

		if ( ! isset( $attributes['showStats'] ) || false !== $attributes['showStats'] ) {
			$this->render_stats( $user_id );
		}

		$show_entitled = ! isset( $attributes['showLibrary'] ) || false !== $attributes['showLibrary'];
		$show_sections = ! isset( $attributes['showQuickLinks'] ) || false !== $attributes['showQuickLinks'];

		if ( ! $show_sections ) {
			if ( $show_entitled ) {
				$this->render();
			}
			echo '</section>';
			return;
		}

		$panels     = $this->dashboard_panels( $user_id, $show_entitled );
		$logout_url = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url( home_url( '/' ) );

		echo '<nav class="mw-user-dashboard__tabs" aria-label="' . esc_attr__( 'Account sections', 'music-wave-core' ) . '">';
		foreach ( $panels as $key => $panel ) {
			// Server markup keeps every panel expanded so the dashboard remains
			// readable when JavaScript fails; the tab controller collapses panels
			// after it loads (progressive enhancement, PROJECT_PLAN.md §14).
			echo '<button type="button" class="mw-user-dashboard__tab" data-mw-dashboard-tab="' . esc_attr( $key ) . '" aria-controls="mw-dashboard-panel-' . esc_attr( $key ) . '" aria-expanded="true"><span aria-hidden="true">' . esc_html( (string) $panel['icon'] ) . '</span><strong>' . esc_html( (string) $panel['label'] ) . '</strong><small>' . esc_html( (string) $panel['description'] ) . '</small></button>';
		}
		echo '<a class="mw-user-dashboard__tab mw-user-dashboard__tab--logout" href="' . esc_url( $logout_url ) . '"><span aria-hidden="true">&rarr;</span><strong>' . esc_html__( 'Sign out', 'music-wave-core' ) . '</strong><small>' . esc_html__( 'Securely close this account session.', 'music-wave-core' ) . '</small></a>';
		echo '</nav>';

		echo '<div class="mw-user-dashboard__panels">';
		foreach ( $panels as $key => $panel ) {
			// Panel content is composed above from fully escaped internal markup only.
			echo '<section class="mw-user-dashboard__panel is-open" id="mw-dashboard-panel-' . esc_attr( $key ) . '" data-mw-dashboard-panel="' . esc_attr( $key ) . '"><h3 class="mw-user-dashboard__panel-title">' . esc_html( (string) $panel['label'] ) . '</h3><div class="mw-user-dashboard__panel-content">' . $panel['content'] . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></section>';

		$this->enqueue_dashboard_script();
	}

	/**
	 * Build the visible dashboard panels for the current plugin context.
	 *
	 * WooCommerce panels only appear while WooCommerce is active, and the
	 * membership panel only while the MusicWave VIP plugin is active.
	 *
	 * @param int  $user_id       Dashboard owner.
	 * @param bool $show_entitled Whether the entitled secure library renders.
	 * @return array<string, array<string, string>>
	 */
	private function dashboard_panels( int $user_id, bool $show_entitled ): array {
		$panels = array(
			'library' => array(
				'icon'        => '♫',
				'label'       => __( 'Music library', 'music-wave-core' ),
				'description' => __( 'Saved songs, albums, podcasts, and artists.', 'music-wave-core' ),
				'content'     => $this->library_panel_content( $show_entitled ),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$panels['orders']  = array(
				'icon'        => '◎',
				'label'       => __( 'Orders', 'music-wave-core' ),
				'description' => __( 'Review purchases and order status.', 'music-wave-core' ),
				'content'     => $this->orders_panel_content(),
			);
			$panels['account'] = array(
				'icon'        => '●',
				'label'       => __( 'Account details', 'music-wave-core' ),
				'description' => __( 'Your name, email, and password.', 'music-wave-core' ),
				'content'     => $this->account_panel_content( $user_id ),
			);
		}

		if ( defined( 'MUSIC_WAVE_VIP_FILE' ) ) {
			$panels['membership'] = array(
				'icon'        => '★',
				'label'       => __( 'Membership', 'music-wave-core' ),
				'description' => __( 'Your VIP access levels and perks.', 'music-wave-core' ),
				'content'     => $this->membership_panel_content( $user_id ),
			);
		}

		/**
		 * Filter the dashboard panels rendered for a customer.
		 *
		 * @param array $panels  Panels keyed by panel identifier.
		 * @param int   $user_id Dashboard owner.
		 */
		$filtered = apply_filters( 'music_wave_dashboard_panels', $panels, $user_id );

		return is_array( $filtered ) ? $filtered : $panels;
	}

	/**
	 * Compose the library panel from the personal and entitled collections.
	 */
	private function library_panel_content( bool $show_entitled ): string {
		$personal = function_exists( 'do_blocks' )
			? do_blocks( '<!-- wp:music-wave/music-library {"showHeading":false} /-->' )
			: '';

		$entitled = '';
		if ( $show_entitled ) {
			ob_start();
			$this->render();
			$entitled = (string) ob_get_clean();
		}

		$content = $personal . $entitled;
		if ( '' === trim( $content ) ) {
			return '<p class="mw-user-dashboard__empty-panel">' . esc_html__( 'Nothing is available in your library yet. Save releases with the “Add to library” button or unlock protected music.', 'music-wave-core' ) . '</p>';
		}

		return $content;
	}

	/**
	 * Render the WooCommerce orders table inside the dashboard panel.
	 */
	private function orders_panel_content(): string {
		if ( ! function_exists( 'woocommerce_account_orders' ) ) {
			return '<p class="mw-user-dashboard__empty-panel">' . esc_html__( 'Your order history is not available right now.', 'music-wave-core' ) . '</p>';
		}

		ob_start();
		woocommerce_account_orders( 1 );

		return (string) ob_get_clean();
	}

	/**
	 * Render the account details card with an edit shortcut.
	 */
	private function account_panel_content( int $user_id ): string {
		$user     = wp_get_current_user();
		$name     = '' !== $user->display_name ? $user->display_name : $user->user_login;
		$date     = function_exists( 'date_i18n' ) ? date_i18n( (string) get_option( 'date_format' ), strtotime( (string) $user->user_registered ) ) : '';
		$edit_url = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( 'edit-account' )
			: get_edit_profile_url( $user_id );

		$rows = array(
			array( __( 'Name', 'music-wave-core' ), $name ),
			array( __( 'Email', 'music-wave-core' ), (string) $user->user_email ),
		);
		if ( '' !== $date ) {
			$rows[] = array( __( 'Member since', 'music-wave-core' ), $date );
		}

		$markup = '<dl class="mw-user-dashboard__details">';
		foreach ( $rows as $row ) {
			$markup .= '<div><dt>' . esc_html( $row[0] ) . '</dt><dd>' . esc_html( $row[1] ) . '</dd></div>';
		}
		$markup .= '</dl>';

		return $markup . '<a class="wp-element-button" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit account details', 'music-wave-core' ) . '</a>';
	}

	/**
	 * Render the VIP membership panel from the user's mapped levels.
	 */
	private function membership_panel_content( int $user_id ): string {
		$user   = wp_get_current_user();
		$roles  = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
		$levels = apply_filters( 'music_wave_vip_membership_levels_for_user', $roles, $user_id );
		$levels = is_array( $levels ) ? array_values( array_filter( array_unique( array_map( 'sanitize_key', array_map( 'strval', $levels ) ) ) ) ) : array();

		if ( empty( $levels ) ) {
			$markup = '<p class="mw-user-dashboard__empty-panel">' . esc_html__( 'You do not have an active VIP membership yet. Unlock protected releases with a membership level.', 'music-wave-core' ) . '</p>';
		} else {
			$markup = '<p>' . esc_html__( 'Your active VIP membership levels:', 'music-wave-core' ) . '</p><ul class="mw-user-dashboard__levels">';
			foreach ( $levels as $level ) {
				$markup .= '<li>' . esc_html( $level ) . '</li>';
			}
			$markup .= '</ul>';
		}

		/**
		 * Filter the membership call-to-action URL shown on the dashboard.
		 *
		 * @param string $url     Destination URL. Empty hides the button.
		 * @param int    $user_id Dashboard owner.
		 */
		$cta_url = apply_filters( 'music_wave_dashboard_membership_cta_url', '', $user_id );
		if ( is_string( $cta_url ) && '' !== $cta_url ) {
			$markup .= '<a class="wp-element-button" href="' . esc_url( $cta_url ) . '">' . esc_html__( 'Manage membership', 'music-wave-core' ) . '</a>';
		}

		return $markup;
	}

	/**
	 * Load the dashboard tab controller on public pages.
	 *
	 * @return void
	 */
	private function enqueue_dashboard_script(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'music-wave-dashboard',
			MUSIC_WAVE_CORE_URL . 'assets/dashboard.js',
			array(),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
	}

	/**
	 * Register the endpoint early enough for WooCommerce account routing.
	 *
	 * @return void
	 */
	public static function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<string, string> $items Existing account menu items.
	 * @return array<string, string>
	 */
	public function add_menu_item( array $items ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $items;
		}

		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : '';
		unset( $items['customer-logout'] );
		$items[ self::ENDPOINT ] = __( 'My music library', 'music-wave-core' );
		if ( '' !== $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Render releases with a current access decision and a protected asset.
	 *
	 * @return void
	 */
	public function render(): void {
		$subject = AccessSubject::current();
		if ( $subject->user_id() < 1 ) {
			return;
		}

		$items = array();
		foreach ( $this->entitled_release_ids( $subject ) as $release_id ) {
			if ( empty( $this->download_assets( $release_id ) ) ) {
				continue;
			}

			$items[] = $this->item_markup( $release_id );
		}

		echo '<section class="mw-account-library"><h2>' . esc_html__( 'My music library', 'music-wave-core' ) . '</h2><p class="mw-account-library__intro">' . esc_html__( 'Your currently available protected releases appear here. Access is checked again when each download starts.', 'music-wave-core' ) . '</p>';
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No protected releases are currently available in your library.', 'music-wave-core' ) . '</p></section>';
			return;
		}

		$this->enqueue_download_script();
		// Each item is built by item_markup() from fully escaped fragments.
		echo '<ul class="mw-account-library__items">' . implode( '', $items ) . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Resolve releases the current subject may access and download.
	 *
	 * @return array<int, int>
	 */
	public function entitled_release_ids( ?AccessSubject $subject = null ): array {
		$subject = null === $subject ? AccessSubject::current() : $subject;
		if ( $subject->user_id() < 1 ) {
			return array();
		}

		$release_ids = apply_filters( 'music_wave_account_library_release_ids', $this->candidate_release_ids(), $subject->user_id() );
		$release_ids = is_array( $release_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $release_ids ) ) ) ) : array();

		return array_values(
			array_filter(
				$release_ids,
				function ( int $release_id ) use ( $subject ): bool {
					return ReleasePostType::KEY === get_post_type( $release_id ) && $this->policy->decide( $release_id, $subject )->is_allowed();
				}
			)
		);
	}

	/**
	 * Render the dashboard stat counters for account and library activity.
	 *
	 * @return void
	 */
	private function render_stats( int $user_id ): void {
		$entitled = count( $this->entitled_release_ids() );
		$saved    = null !== $this->library ? count( $this->library->ids( $user_id, LibraryRepository::TYPE_RELEASE ) ) : 0;
		$artists  = null !== $this->library ? count( $this->library->ids( $user_id, LibraryRepository::TYPE_ARTIST ) ) : 0;

		echo '<ul class="mw-user-dashboard__stats">'
			. '<li><strong>' . esc_html( (string) $entitled ) . '</strong><span>' . esc_html__( 'Available downloads', 'music-wave-core' ) . '</span></li>'
			. '<li><strong>' . esc_html( (string) $saved ) . '</strong><span>' . esc_html__( 'Saved releases', 'music-wave-core' ) . '</span></li>'
			. '<li><strong>' . esc_html( (string) $artists ) . '</strong><span>' . esc_html__( 'Followed artists', 'music-wave-core' ) . '</span></li>'
			. '</ul>';
	}

	/**
	 * Get a bounded candidate set; access policy remains authoritative.
	 *
	 * @return array<int, int>
	 */
	private function candidate_release_ids(): array {
		$posts = get_posts(
			array(
				'post_type'      => ReleasePostType::KEY,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'mw_access_mode',
						'value'   => array( 'purchase', 'membership', 'purchase_or_membership' ),
						'compare' => 'IN',
					),
				),
			)
		);

		return is_array( $posts ) ? array_map( 'absint', $posts ) : array();
	}

	private function item_markup( int $release_id ): string {
		$title = get_the_title( $release_id );
		$link  = get_permalink( $release_id );
		$image = get_the_post_thumbnail(
			$release_id,
			'thumbnail',
			array(
				'class' => 'mw-account-library__image',
				'alt'   => '',
			)
		);
		$type  = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'names' ) );
		$label = is_array( $type ) && ! empty( $type ) ? implode( ', ', $type ) : __( 'Release', 'music-wave-core' );
		$title = '' !== $title ? $title : __( 'Untitled release', 'music-wave-core' );

		$assets = $this->download_assets( $release_id );
		$files  = $this->download_files( $assets );
		$first  = reset( $files );
		if ( ! is_array( $first ) || empty( $first['qualities'] ) || ! is_array( $first['qualities'] ) ) {
			return '';
		}
		$quality = $first['qualities'][0];

		return '<li class="mw-account-library__item">' . $image . '<div class="mw-account-library__details"><span>' . esc_html( $label ) . '</span><strong>' . ( is_string( $link ) ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</strong></div><div class="mw-account-library__actions mw-download-action" data-download-files="' . esc_attr( wp_json_encode( array_values( $files ) ) ) . '">' . $this->download_controls( $files ) . '<button class="button mw-download-button" type="button" data-release-id="' . esc_attr( (string) $release_id ) . '" data-download-quality="' . esc_attr( $quality['key'] ) . '">' . esc_html__( 'Secure download', 'music-wave-core' ) . '</button><span class="mw-download-status" role="status" aria-live="polite"></span></div></li>';
	}

	/**
	 * Reuse the Core token issuance client used by the public download block.
	 *
	 * @return void
	 */
	private function enqueue_download_script(): void {
		wp_enqueue_script( 'music-wave-download', MUSIC_WAVE_CORE_URL . 'assets/download.js', array( 'wp-api-fetch' ), MUSIC_WAVE_CORE_VERSION, true );
		wp_localize_script(
			'music-wave-download',
			'musicWaveDownload',
			array(
				'restUrl'      => esc_url_raw( rest_url() ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'errorMessage' => __( 'The download could not be started.', 'music-wave-core' ),
				'sessionError' => __( 'Your session has expired. Refresh the page or sign in again.', 'music-wave-core' ),
			)
		);
	}

	/**
	 * Return customer-facing labels only; provider IDs remain private.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function download_assets( int $release_id ): array {
		$variants = $this->releases->get( $release_id, 'mw_download_assets' );
		$legacy   = $this->releases->get( $release_id, 'mw_download_asset_id' );
		$assets   = is_array( $variants ) ? $variants : array();
		if ( empty( $assets ) && is_string( $legacy ) && '' !== $legacy ) {
			$assets[] = array(
				'key'      => 'standard',
				'label'    => __( 'Standard download', 'music-wave-core' ),
				'asset_id' => $legacy,
			);
		}

		$result = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['key'] ) || empty( $asset['label'] ) || empty( $asset['asset_id'] ) ) {
				continue;
			}
			$result[] = array(
				'key'        => sanitize_key( (string) $asset['key'] ),
				'label'      => sanitize_text_field( (string) $asset['label'] ),
				'file_key'   => isset( $asset['file_key'] ) ? sanitize_key( (string) $asset['file_key'] ) : '',
				'file_label' => isset( $asset['file_label'] ) ? sanitize_text_field( (string) $asset['file_label'] ) : '',
			);
		}

		return array_values(
			array_filter(
				$result,
				static function ( array $asset ): bool {
					return '' !== $asset['key'] && '' !== $asset['label'];
				}
			)
		);
	}

	/**
	 * @param array<int, array<string, string>> $assets Download variants.
	 * @return array<string, array<string, mixed>>
	 */
	private function download_files( array $assets ): array {
		$files = array();
		foreach ( $assets as $asset ) {
			$file_key   = '' !== $asset['file_key'] ? $asset['file_key'] : 'main-download';
			$file_label = '' !== $asset['file_label'] ? $asset['file_label'] : __( 'Main download', 'music-wave-core' );
			if ( ! isset( $files[ $file_key ] ) ) {
				$files[ $file_key ] = array(
					'key'       => $file_key,
					'label'     => $file_label,
					'qualities' => array(),
				);
			}
			$files[ $file_key ]['qualities'][] = array(
				'key'   => $asset['key'],
				'label' => $asset['label'],
			);
		}

		return $files;
	}

	/**
	 * @param array<string, array<string, mixed>> $files Downloadable files.
	 * @return string
	 */
	private function download_controls( array $files ): string {
		$first = reset( $files );
		if ( ! is_array( $first ) || empty( $first['qualities'] ) || ! is_array( $first['qualities'] ) ) {
			return '';
		}

		$markup = '<div class="mw-download-action__controls">';
		if ( count( $files ) > 1 ) {
			$options = array();
			foreach ( $files as $file ) {
				$options[] = '<option value="' . esc_attr( (string) $file['key'] ) . '">' . esc_html( (string) $file['label'] ) . '</option>';
			}
			$markup .= '<label class="mw-download-action__file"><span>' . esc_html__( 'Download file', 'music-wave-core' ) . '</span><select class="mw-download-file">' . implode( '', $options ) . '</select></label>';
		}

		$options = array();
		foreach ( $first['qualities'] as $quality ) {
			$options[] = '<option value="' . esc_attr( (string) $quality['key'] ) . '">' . esc_html( (string) $quality['label'] ) . '</option>';
		}

		return $markup . '<label class="mw-download-action__quality"><span>' . esc_html__( 'Download quality', 'music-wave-core' ) . '</span><select class="mw-download-quality">' . implode( '', $options ) . '</select></label></div>';
	}
}
