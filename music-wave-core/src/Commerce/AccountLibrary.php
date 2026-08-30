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
	public const ENDPOINT            = 'music-library';
	public const MEMBERSHIP_ENDPOINT = 'membership';

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
		add_action( 'woocommerce_account_' . self::MEMBERSHIP_ENDPOINT . '_endpoint', array( $this, 'render_membership_endpoint' ) );
		add_filter( 'pre_do_shortcode_tag', array( $this, 'skip_my_account_shortcode' ), 10, 2 );
		add_shortcode( 'musicwave_dashboard', array( $this, 'dashboard_shortcode' ) );
		add_shortcode( 'musicwave_membership', array( $this, 'membership_shortcode' ) );
	}

	/**
	 * Neutralize a pasted [woocommerce_my_account] shortcode on account pages.
	 *
	 * The unified dashboard renders every account section itself; the
	 * shortcode — whether inside page content, a pattern, or a wp:shortcode
	 * block — would draw WooCommerce's second navigation and duplicate every
	 * panel. pre_do_shortcode_tag covers every rendering path.
	 *
	 * @param string|null $output Shortcode output, null by default.
	 * @param string      $tag    Shortcode tag.
	 * @return string|null
	 */
	public function skip_my_account_shortcode( $output, string $tag ) {
		if ( 'woocommerce_my_account' === $tag && function_exists( 'is_account_page' ) && is_account_page() ) {
			return '';
		}

		return $output;
	}

	/**
	 * Register the portable account dashboard and membership blocks.
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
					'showLibrary'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showQuickLinks'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStats'            => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMembershipPanel'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showOrders'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDownloads'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAddresses'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPaymentMethods'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAccountDetails'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showSignOut'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'introText'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'membershipHeading'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'libraryHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'ordersHeading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'downloadsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'addressesHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'paymentHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'accountHeading'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'playlistsHeading'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'notificationsHeading' => array(
						'type'    => 'string',
						'default' => '',
					),
					'panelOrder'           => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
				'supports'    => \ManaCore\MusicWave\Core\Blocks\BlockSupport::appearance_tools(),
			)
		);

		\ManaCore\MusicWave\Core\Blocks\BlockSupport::register_dynamic(
			'music-wave/membership-panel',
			array( $this, 'render_membership_block' ),
			array(
				'api_version' => 3,
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'showActive'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPlans'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBuyButtons' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'emptyText'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => \ManaCore\MusicWave\Core\Blocks\BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the membership panel block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_membership_block( array $attributes ): string {
		$defaults = array(
			'heading'        => '',
			'showActive'     => true,
			'showPlans'      => true,
			'showBuyButtons' => true,
			'emptyText'      => '',
		);
		$args     = array_merge( $defaults, $attributes );

		return $this->membership_markup( get_current_user_id(), $args );
	}

	/**
	 * Shortcode counterpart for the membership panel.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 */
	public function membership_shortcode( $attributes = array() ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$attributes = shortcode_atts(
			array(
				'heading'     => '',
				'show_active' => 'yes',
				'show_plans'  => 'yes',
				'show_buy'    => 'yes',
				'empty_text'  => '',
			),
			$attributes,
			'musicwave_membership'
		);

		return $this->render_membership_block(
			array(
				'heading'        => $attributes['heading'],
				'showActive'     => 'yes' === $attributes['show_active'],
				'showPlans'      => 'yes' === $attributes['show_plans'],
				'showBuyButtons' => 'yes' === $attributes['show_buy'],
				'emptyText'      => $attributes['empty_text'],
			)
		);
	}

	/**
	 * WooCommerce my-account membership endpoint content.
	 *
	 * @return void
	 */
	public function render_membership_endpoint(): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			echo '<p>' . esc_html__( 'Sign in to view your membership.', 'music-wave-core' ) . '</p>';
			return;
		}

		echo $this->membership_markup( $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed below from fully escaped fragments.
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
				'show_library'       => 'yes',
				'show_quick_links'   => 'yes',
				'show_stats'         => 'yes',
				'show_membership'    => 'yes',
				'show_orders'        => 'yes',
				'show_downloads'     => 'yes',
				'show_addresses'     => 'yes',
				'show_payment'       => 'yes',
				'show_account'       => 'yes',
				'show_sign_out'      => 'yes',
				'intro_text'         => '',
				'membership_heading' => '',
				'library_heading'    => '',
				'orders_heading'     => '',
				'panel_order'        => 'default',
			),
			$attributes,
			'musicwave_dashboard'
		);

		return $this->render_dashboard_block(
			array(
				'showLibrary'         => 'yes' === $attributes['show_library'],
				'showQuickLinks'      => 'yes' === $attributes['show_quick_links'],
				'showStats'           => 'yes' === $attributes['show_stats'],
				'showMembershipPanel' => 'yes' === $attributes['show_membership'],
				'showOrders'          => 'yes' === $attributes['show_orders'],
				'showDownloads'       => 'yes' === $attributes['show_downloads'],
				'showAddresses'       => 'yes' === $attributes['show_addresses'],
				'showPaymentMethods'  => 'yes' === $attributes['show_payment'],
				'showAccountDetails'  => 'yes' === $attributes['show_account'],
				'showSignOut'         => 'yes' === $attributes['show_sign_out'],
				'introText'           => sanitize_text_field( (string) $attributes['intro_text'] ),
				'membershipHeading'   => sanitize_text_field( (string) $attributes['membership_heading'] ),
				'libraryHeading'      => sanitize_text_field( (string) $attributes['library_heading'] ),
				'ordersHeading'       => sanitize_text_field( (string) $attributes['orders_heading'] ),
				'panelOrder'          => sanitize_key( (string) $attributes['panel_order'] ),
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

		$user  = wp_get_current_user();
		$name  = '' !== $user->display_name ? $user->display_name : $user->user_login;
		$intro = isset( $attributes['introText'] ) && is_string( $attributes['introText'] ) && '' !== trim( (string) $attributes['introText'] ) ? sanitize_text_field( (string) $attributes['introText'] ) : __( 'Your account, music access, downloads, and listening shortcuts in one place.', 'music-wave-core' );
		$style = \ManaCore\MusicWave\Core\Blocks\BlockSupport::style_variation( $attributes, array( 'tabs', 'stacked' ) );
		$class = 'mw-user-dashboard' . ( '' !== $style ? ' ' . $style : '' );

		echo '<section class="' . esc_attr( $class ) . '" data-mw-dashboard><header class="mw-user-dashboard__welcome">' . get_avatar( $user_id, 88, '', '', array( 'class' => 'mw-user-dashboard__avatar' ) ) . '<div><span class="mw-user-dashboard__eyebrow">' . esc_html__( 'Welcome back', 'music-wave-core' ) . '</span><h2>' . esc_html( $name ) . '</h2><p>' . esc_html( $intro ) . '</p></div></header>';

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

		$panels     = $this->dashboard_panels( $user_id, $show_entitled, $attributes );
		$logout_url = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url( home_url( '/' ) );

		echo '<nav class="mw-user-dashboard__tabs" aria-label="' . esc_attr__( 'Account sections', 'music-wave-core' ) . '">';
		foreach ( $panels as $key => $panel ) {
			// Server markup keeps every panel expanded so the dashboard remains
			// readable when JavaScript fails; the tab controller collapses panels
			// after it loads (progressive enhancement, PROJECT_PLAN.md §14).
			echo '<button type="button" class="mw-user-dashboard__tab" data-mw-dashboard-tab="' . esc_attr( $key ) . '" aria-controls="mw-dashboard-panel-' . esc_attr( $key ) . '" aria-expanded="true"><span aria-hidden="true">' . esc_html( (string) $panel['icon'] ) . '</span><strong>' . esc_html( (string) $panel['label'] ) . '</strong><small>' . esc_html( (string) $panel['description'] ) . '</small></button>';
		}
		$show_sign_out = ! isset( $attributes['showSignOut'] ) || false !== $attributes['showSignOut'];
		if ( $show_sign_out ) {
			echo '<a class="mw-user-dashboard__tab mw-user-dashboard__tab--logout" href="' . esc_url( $logout_url ) . '"><span aria-hidden="true">&rarr;</span><strong>' . esc_html__( 'Sign out', 'music-wave-core' ) . '</strong><small>' . esc_html__( 'Securely close this account session.', 'music-wave-core' ) . '</small></a>';
		}
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
	 * Every WooCommerce account section renders inside the dashboard while
	 * WooCommerce is active; the membership panel appears while the MusicWave
	 * VIP plugin is active. Block attributes gate each panel and override its
	 * heading, and panelOrder re-sequences the tab bar.
	 *
	 * @param int                  $user_id       Dashboard owner.
	 * @param bool                 $show_entitled Whether the entitled secure library renders.
	 * @param array<string, mixed> $attributes    Dashboard display attributes.
	 * @return array<string, array<string, string>>
	 */
	private function dashboard_panels( int $user_id, bool $show_entitled, array $attributes = array() ): array {
		$on      = static function ( string $key ) use ( $attributes ): bool {
			return ! isset( $attributes[ $key ] ) || false !== $attributes[ $key ];
		};
		$heading = static function ( string $key, string $default ) use ( $attributes ): string {
			$custom = isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ? sanitize_text_field( trim( (string) $attributes[ $key ] ) ) : '';

			return '' !== $custom ? $custom : $default;
		};

		$panels = array();
		if ( $on( 'showLibrary' ) || $show_entitled ) {
			$panels['library'] = array(
				'icon'        => '♫',
				'label'       => $heading( 'libraryHeading', __( 'Music library', 'music-wave-core' ) ),
				'description' => __( 'Saved songs, albums, podcasts, and artists.', 'music-wave-core' ),
				'content'     => $this->library_panel_content( $show_entitled ),
			);
		}

		if ( class_exists( 'WooCommerce' ) ) {
			if ( $on( 'showOrders' ) ) {
				$panels['orders'] = array(
					'icon'        => '◎',
					'label'       => $heading( 'ordersHeading', __( 'Orders', 'music-wave-core' ) ),
					'description' => __( 'Review purchases and order status.', 'music-wave-core' ),
					'content'     => $this->orders_panel_content(),
				);
			}
			if ( $on( 'showDownloads' ) && function_exists( 'woocommerce_account_downloads' ) ) {
				$panels['downloads'] = array(
					'icon'        => '↓',
					'label'       => $heading( 'downloadsHeading', __( 'Downloads', 'music-wave-core' ) ),
					'description' => __( 'Files from your WooCommerce purchases.', 'music-wave-core' ),
					'content'     => $this->downloads_panel_content(),
				);
			}
			if ( $on( 'showAddresses' ) && function_exists( 'woocommerce_account_edit_address' ) ) {
				$panels['addresses'] = array(
					'icon'        => '⌂',
					'label'       => $heading( 'addressesHeading', __( 'Addresses', 'music-wave-core' ) ),
					'description' => __( 'Billing and shipping details.', 'music-wave-core' ),
					'content'     => $this->addresses_panel_content(),
				);
			}
			if ( $on( 'showPaymentMethods' ) && function_exists( 'woocommerce_account_payment_methods' ) ) {
				$panels['payment'] = array(
					'icon'        => '₪',
					'label'       => $heading( 'paymentHeading', __( 'Payment methods', 'music-wave-core' ) ),
					'description' => __( 'Saved cards and gateways.', 'music-wave-core' ),
					'content'     => $this->payment_panel_content(),
				);
			}
			if ( $on( 'showAccountDetails' ) && function_exists( 'woocommerce_account_edit_account' ) ) {
				$panels['account'] = array(
					'icon'        => '●',
					'label'       => $heading( 'accountHeading', __( 'Account details', 'music-wave-core' ) ),
					'description' => __( 'Edit your name, email, and password.', 'music-wave-core' ),
					'content'     => $this->account_panel_content(),
				);
			}
		}

		if ( defined( 'MUSIC_WAVE_VIP_FILE' ) && $on( 'showMembershipPanel' ) ) {
			$panels['membership'] = array(
				'icon'        => '★',
				'label'       => $heading( 'membershipHeading', __( 'Membership', 'music-wave-core' ) ),
				'description' => __( 'Your VIP access levels and purchasable plans.', 'music-wave-core' ),
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
		$panels   = is_array( $filtered ) ? $filtered : $panels;

		// Filter-added panels (playlists, notifications, …) honor the same
		// per-panel heading overrides.
		foreach ( array( 'playlists', 'notifications' ) as $extra ) {
			$key = $extra . 'Heading';
			if ( isset( $panels[ $extra ] ) && isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) && '' !== trim( (string) $attributes[ $key ] ) ) {
				$panels[ $extra ]['label'] = sanitize_text_field( trim( (string) $attributes[ $key ] ) );
			}
		}

		return $this->order_panels( $panels, isset( $attributes['panelOrder'] ) ? sanitize_key( (string) $attributes['panelOrder'] ) : '' );
	}

	/**
	 * Re-sequence panels by the selected order preset.
	 *
	 * @param array<string, array<string, string>> $panels Panels keyed by id.
	 * @param string                               $preset default|commerce_first|membership_first.
	 * @return array<string, array<string, string>>
	 */
	private function order_panels( array $panels, string $preset ): array {
		$order = array(
			'library',
			'orders',
			'downloads',
			'addresses',
			'payment',
			'account',
			'membership',
		);
		if ( 'commerce_first' === $preset ) {
			$order = array(
				'orders',
				'downloads',
				'addresses',
				'payment',
				'account',
				'library',
				'membership',
			);
		} elseif ( 'membership_first' === $preset ) {
			$order = array(
				'membership',
				'library',
				'orders',
				'downloads',
				'addresses',
				'payment',
				'account',
			);
		}

		$ordered = array();
		foreach ( $order as $key ) {
			if ( isset( $panels[ $key ] ) ) {
				$ordered[ $key ] = $panels[ $key ];
				unset( $panels[ $key ] );
			}
		}

		return $ordered + $panels;
	}

	/**
	 * Whether the WooCommerce frontend session state is initialized.
	 *
	 * Block-renderer REST requests (the editor live preview) never load the
	 * cart session, so WC()->customer stays null there while the my-account
	 * templates read it. Panels depending on that state render an
	 * editor-safe placeholder instead of fatalling the preview.
	 */
	private function woo_customer_ready(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && null !== WC()->customer;
	}

	/**
	 * Placeholder shown where live WooCommerce customer data only exists on
	 * the frontend (editor previews, REST renders).
	 */
	private function woo_preview_placeholder( string $section ): string {
		return '<p class="mw-user-dashboard__empty-panel">' . esc_html( sprintf( /* translators: %s: section name. */ __( 'Your live %s appear here on the site.', 'music-wave-core' ), $section ) ) . '</p>';
	}

	/**
	 * Render the WooCommerce downloads table inside the dashboard panel.
	 */
	private function downloads_panel_content(): string {
		if ( ! $this->woo_customer_ready() ) {
			return $this->woo_preview_placeholder( __( 'downloads', 'music-wave-core' ) );
		}
		$endpoint = $this->capture_woo_endpoint( 'downloads' );
		if ( null !== $endpoint ) {
			return $endpoint;
		}
		ob_start();
		woocommerce_account_downloads();

		return (string) ob_get_clean();
	}

	/**
	 * Render the billing and shipping address forms side by side.
	 */
	private function addresses_panel_content(): string {
		if ( ! $this->woo_customer_ready() ) {
			return $this->woo_preview_placeholder( __( 'billing and shipping addresses', 'music-wave-core' ) );
		}
		$endpoint = $this->capture_woo_endpoint( 'addresses' );
		if ( null !== $endpoint ) {
			return $endpoint;
		}

		$markup = '<div class="mw-user-dashboard__addresses">';
		ob_start();
		echo '<section class="mw-user-dashboard__address"><h4>' . esc_html__( 'Billing address', 'music-wave-core' ) . '</h4>';
		woocommerce_account_edit_address( 'billing' );
		echo '</section>';
		$markup .= (string) ob_get_clean();

		ob_start();
		echo '<section class="mw-user-dashboard__address"><h4>' . esc_html__( 'Shipping address', 'music-wave-core' ) . '</h4>';
		woocommerce_account_edit_address( 'shipping' );
		echo '</section>';
		$markup .= (string) ob_get_clean();

		return $markup . '</div>';
	}

	/**
	 * Render the saved payment methods (or the add-method form on its endpoint).
	 */
	private function payment_panel_content(): string {
		if ( ! $this->woo_customer_ready() ) {
			return $this->woo_preview_placeholder( __( 'saved payment methods', 'music-wave-core' ) );
		}
		$endpoint = $this->capture_woo_endpoint( 'payment' );
		if ( null !== $endpoint ) {
			return $endpoint;
		}
		ob_start();
		woocommerce_account_payment_methods();

		return (string) ob_get_clean();
	}

	/**
	 * Render the real editable account details form.
	 */
	private function account_panel_content(): string {
		$endpoint = $this->capture_woo_endpoint( 'account' );
		if ( null !== $endpoint ) {
			return $endpoint;
		}
		ob_start();
		woocommerce_account_edit_account();

		return (string) ob_get_clean();
	}

	/**
	 * Dispatch the current WooCommerce account endpoint, if any.
	 *
	 * Without the classic [woocommerce_my_account] shortcode the endpoint
	 * actions never fire on direct URL visits; replaying them here keeps the
	 * processing hooks (saving an address, adding a payment method, viewing a
	 * single order) and captures their rendered output for the matching panel.
	 *
	 * @param string $panel Panel expecting endpoint content.
	 * @return string|null Rendered endpoint output, or null when the current
	 *                     URL carries no endpoint for this panel.
	 */
	private function capture_woo_endpoint( string $panel ): ?string {
		global $wp;
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! isset( $wp->query_vars ) || ! is_array( $wp->query_vars ) ) {
			return null;
		}

		$panels = array(
			'orders'    => array( 'orders', 'view-order' ),
			'downloads' => array( 'downloads' ),
			'addresses' => array( 'edit-address' ),
			'payment'   => array( 'payment-methods', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method' ),
			'account'   => array( 'edit-account' ),
		);
		if ( ! isset( $panels[ $panel ] ) ) {
			return null;
		}

		foreach ( $wp->query_vars as $key => $value ) {
			if ( in_array( $key, $panels[ $panel ], true ) && has_action( 'woocommerce_account_' . $key . '_endpoint' ) ) {
				ob_start();
				do_action( 'woocommerce_account_' . $key . '_endpoint', $value );
				$captured = (string) ob_get_clean();

				return '' !== trim( $captured ) ? $captured : null;
			}
		}

		return null;
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
	 * Render the WooCommerce orders table (or a single order view) inside the
	 * dashboard panel.
	 */
	private function orders_panel_content(): string {
		$endpoint = $this->capture_woo_endpoint( 'orders' );
		if ( null !== $endpoint ) {
			return $endpoint;
		}
		if ( ! function_exists( 'woocommerce_account_orders' ) ) {
			return '<p class="mw-user-dashboard__empty-panel">' . esc_html__( 'Your order history is not available right now.', 'music-wave-core' ) . '</p>';
		}

		ob_start();
		woocommerce_account_orders( 1 );

		return (string) ob_get_clean();
	}

	/**
	 * Render the VIP membership panel from the user's mapped levels.
	 */
	private function membership_panel_content( int $user_id ): string {
		return $this->membership_markup( $user_id );
	}

	/**
	 * Build the shared membership markup used by the dashboard panel, the
	 * WooCommerce my-account endpoint, the shortcode, and the block.
	 *
	 * @param int                  $user_id Account owner.
	 * @param array<string, mixed> $args    Display switches (heading, showActive, showPlans, showBuyButtons, emptyText).
	 * @return string Fully escaped markup.
	 */
	private function membership_markup( int $user_id, array $args = array() ): string {
		$args = shortcode_atts(
			array(
				'heading'        => '',
				'showActive'     => true,
				'showPlans'      => true,
				'showBuyButtons' => true,
				'emptyText'      => '',
			),
			$args,
			'membership_markup'
		);

		/**
		 * Filter the structured membership data rendered for a customer.
		 *
		 * MusicWave VIP supplies: module_enabled (bool), active[] items with
		 * level/expires labels, and plans[] items with id, level, title,
		 * price, duration, url, and cart_url. An empty array means no
		 * membership module is present.
		 *
		 * @param array<string, mixed> $data    Structured membership data.
		 * @param int                  $user_id Account owner.
		 */
		$data = apply_filters( 'music_wave_vip_membership_panel', array(), $user_id );
		$data = is_array( $data ) ? $data : array();

		$classes = 'mw-membership' . ( '' !== (string) $args['heading'] ? ' mw-membership--standalone' : '' );
		$markup  = '<div class="' . esc_attr( $classes ) . '">';

		if ( '' !== (string) $args['heading'] ) {
			$markup .= '<h3 class="mw-membership__heading">' . esc_html( (string) $args['heading'] ) . '</h3>';
		}

		if ( empty( $data ) ) {
			$empty   = '' !== (string) $args['emptyText'] ? (string) $args['emptyText'] : __( 'No membership module is active on this site. All membership content stays available.', 'music-wave-core' );
			$markup .= '<p class="mw-membership__notice">' . esc_html( $empty ) . '</p></div>';
			return $markup;
		}

		$module_enabled = ! isset( $data['module_enabled'] ) || (bool) $data['module_enabled'];
		if ( ! $module_enabled ) {
			$markup .= '<p class="mw-membership__notice mw-membership__notice--free">' . esc_html__( 'All membership content is currently free for everyone — enjoy!', 'music-wave-core' ) . '</p>';
		}

		if ( $args['showActive'] ) {
			$active = isset( $data['active'] ) && is_array( $data['active'] ) ? $data['active'] : array();
			if ( empty( $active ) ) {
				$empty   = '' !== (string) $args['emptyText'] ? (string) $args['emptyText'] : __( 'You do not have an active membership yet. Pick a plan below to unlock protected releases.', 'music-wave-core' );
				$markup .= '<p class="mw-membership__empty">' . esc_html( $empty ) . '</p>';
			} else {
				$markup .= '<ul class="mw-membership__levels">';
				foreach ( $active as $entry ) {
					if ( ! is_array( $entry ) || empty( $entry['level'] ) ) {
						continue;
					}
					$expires = isset( $entry['expires'] ) && '' !== (string) $entry['expires'] ? ' · ' . (string) $entry['expires'] : '';
					$markup .= '<li class="mw-membership__level"><span class="mw-membership__level-name">' . esc_html( sanitize_key( (string) $entry['level'] ) ) . '</span>' . esc_html( $expires ) . '</li>';
				}
				$markup .= '</ul>';
			}
		}

		if ( $args['showPlans'] ) {
			$plans = isset( $data['plans'] ) && is_array( $data['plans'] ) ? $data['plans'] : array();
			if ( ! empty( $plans ) ) {
				$markup .= '<ul class="mw-membership__plans">';
				foreach ( $plans as $plan ) {
					if ( ! is_array( $plan ) || empty( $plan['title'] ) ) {
						continue;
					}
					$duration = isset( $plan['duration'] ) && '' !== (string) $plan['duration'] ? '<span class="mw-membership__plan-duration">' . esc_html( (string) $plan['duration'] ) . '</span>' : '';
					$price    = isset( $plan['price'] ) && '' !== (string) $plan['price'] ? '<span class="mw-membership__plan-price">' . esc_html( (string) $plan['price'] ) . '</span>' : '';
					$markup  .= '<li class="mw-membership__plan"><div class="mw-membership__plan-details"><strong>' . esc_html( (string) $plan['title'] ) . '</strong>' . $duration . $price . '</div>';
					if ( $args['showBuyButtons'] ) {
						$cart = isset( $plan['cart_url'] ) && is_string( $plan['cart_url'] ) && '' !== $plan['cart_url'] ? $plan['cart_url'] : ( isset( $plan['url'] ) ? (string) $plan['url'] : '' );
						if ( '' !== $cart ) {
							$markup .= '<a class="wp-element-button mw-membership__buy" href="' . esc_url( $cart ) . '">' . esc_html__( 'Get this plan', 'music-wave-core' ) . '</a>';
						}
					}
					$markup .= '</li>';
				}
				$markup .= '</ul>';
			}
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

		return $markup . '</div>';
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
		add_rewrite_endpoint( self::MEMBERSHIP_ENDPOINT, EP_ROOT | EP_PAGES );
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
		$items[ self::ENDPOINT ]            = __( 'My music library', 'music-wave-core' );
		$items[ self::MEMBERSHIP_ENDPOINT ] = __( 'Membership', 'music-wave-core' );
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
