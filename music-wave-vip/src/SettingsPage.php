<?php
/**
 * MusicWave VIP settings – professional admin UI with integrated WooCommerce workflow.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class SettingsPage {
	public const PAGE_SLUG = 'music-wave-vip';

	/**
	 * Canonical admin URL of the VIP settings screen.
	 *
	 * The page lives inside the MusicWave releases menu so every MusicWave
	 * admin screen sits in one place.
	 */
	public static function page_url(): string {
		return admin_url( 'edit.php?post_type=mw_release&page=' . self::PAGE_SLUG );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=mw_release',
			__( 'MusicWave VIP settings', 'music-wave-vip' ),
			__( 'MusicWave VIP', 'music-wave-vip' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'mw_release_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		// Inline styles are printed in render() to avoid external asset coupling.
	}

	public function register(): void {
		register_setting(
			'music_wave_vip',
			VipSettings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
				'default'           => VipSettings::defaults(),
			)
		);
		add_settings_section( 'music_wave_vip_delivery', __( 'Protected delivery', 'music-wave-vip' ), array( $this, 'delivery_section' ), 'music-wave-vip' );
		$this->field( 'delivery_provider', __( 'Delivery provider', 'music-wave-vip' ), 'delivery_provider_field' );
		$this->field( 'protected_root', __( 'Protected files directory', 'music-wave-vip' ), 'root_field' );
		$this->field( 'remote_base_url', __( 'Remote host base URL', 'music-wave-vip' ), 'remote_base_url_field' );
		$this->field( 'remote_path_prefix', __( 'Remote path prefix', 'music-wave-vip' ), 'remote_path_prefix_field' );
		$this->field( 'remote_signing_secret', __( 'Remote signing secret', 'music-wave-vip' ), 'remote_signing_secret_field' );
		$this->field( 'remote_signature_param', __( 'Signature query parameter', 'music-wave-vip' ), 'remote_signature_param_field' );
		$this->field( 'remote_expires_param', __( 'Expiry query parameter', 'music-wave-vip' ), 'remote_expires_param_field' );
		$this->field( 'remote_ttl', __( 'Remote URL lifetime', 'music-wave-vip' ), 'remote_ttl_field' );
		$this->field( 'remote_allowed_hosts', __( 'Additional allowed redirect hosts', 'music-wave-vip' ), 'remote_allowed_hosts_field' );
		$this->field( 'remote_key_id', __( 'Signing key ID', 'music-wave-vip' ), 'remote_key_id_field' );
		$this->field( 'sendfile_mode', __( 'Server acceleration', 'music-wave-vip' ), 'sendfile_mode_field' );
		$this->field( 'xaccel_prefix', __( 'X-Accel internal prefix', 'music-wave-vip' ), 'xaccel_prefix_field' );

		add_settings_section( 'music_wave_vip_membership', __( 'Membership and entitlement adapters', 'music-wave-vip' ), array( $this, 'membership_section' ), 'music-wave-vip' );
		$this->field( 'membership_sources', __( 'Membership sources', 'music-wave-vip' ), 'membership_sources_field', 'music_wave_vip_membership' );
		$this->field( 'plan_rows', __( 'VIP plan products', 'music-wave-vip' ), 'plan_rows_field', 'music_wave_vip_membership' );
		$this->field( 'promote_vip_role', __( 'VIP role promotion', 'music-wave-vip' ), 'promote_vip_role_field', 'music_wave_vip_membership' );
	}

	private function field( string $id, string $label, string $callback, string $section = 'music_wave_vip_delivery' ): void {
		add_settings_field( $id, $label, array( $this, $callback ), 'music-wave-vip', $section );
	}

	/** @param mixed $value @return array<string, mixed> */
	public static function sanitize_settings( $value ): array {
		$value = is_array( $value ) ? $value : array();
		// The full settings page marks its submission with a hidden field so
		// checkbox semantics (absent field = unchecked = disabled) apply only
		// to full-form submissions — otherwise a partial save would silently
		// flip the master switch and role promotion off.
		$full_form = ! empty( $value['mwvip_full_form'] );
		unset( $value['mwvip_full_form'] );
		if ( $full_form ) {
			// The master switch is the only control that stays active while the
			// module is off, so an unchecked submission always means "disabled".
			$module_was_enabled      = 'disabled' !== (string) VipSettings::all()['module_enabled'];
			$module_is_enabled       = isset( $value['module_enabled'] );
			$value['module_enabled'] = $module_is_enabled ? 'enabled' : 'disabled';
			if ( $module_was_enabled && $module_is_enabled ) {
				// Membership controls were editable and submitted: an absent
				// checkbox means the admin unchecked it.
				$value['membership_sources'] = isset( $value['membership_sources'] ) && is_array( $value['membership_sources'] ) ? $value['membership_sources'] : array();
				$value['promote_vip_role']   = isset( $value['promote_vip_role'] ) ? $value['promote_vip_role'] : 'disabled';
			}
			// While the module is switched off the membership and access
			// controls are disabled in the UI and not submitted; the merge in
			// VipSettings::sanitize() then preserves the stored configuration
			// so enforcement resumes unchanged when it is switched back on.
		}
		$settings = VipSettings::sanitize( $value );
		$root     = (string) $settings['protected_root'];
		if ( '' !== $root ) {
			$resolved      = realpath( $root );
			$public_root   = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;
			$inside_public = false !== $public_root && 0 === stripos( $resolved ? $resolved . DIRECTORY_SEPARATOR : '', rtrim( $public_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR );
			if ( false === $resolved || ! is_dir( $resolved ) || ! is_readable( $resolved ) || $inside_public ) {
				add_settings_error( VipSettings::OPTION, 'invalid_root', __( 'The protected directory must exist, be readable, and remain outside the public WordPress directory. The previous value was kept.', 'music-wave-vip' ) );
				$settings['protected_root'] = VipSettings::all()['protected_root'];
			} else {
				$settings['protected_root'] = $resolved;
			}
		}
		if ( 'remote_redirect' === $settings['delivery_provider'] && ( '' === $settings['remote_base_url'] || '' === $settings['remote_signing_secret'] ) ) {
			add_settings_error( VipSettings::OPTION, 'remote_incomplete', __( 'Remote redirect mode needs both an HTTPS base URL and a signing secret. Delivery remains fail-closed until both are configured.', 'music-wave-vip' ), 'warning' );
		}
		if ( '' !== (string) $settings['remote_signing_secret'] && strlen( (string) $settings['remote_signing_secret'] ) < 32 ) {
			add_settings_error( VipSettings::OPTION, 'weak_secret', __( 'The remote signing secret is shorter than 32 characters. Signed URLs will not be issued until a stronger secret is saved.', 'music-wave-vip' ), 'warning' );
		}

		return $settings;
	}

	// ------------------------------------------------------------------
	// Helpers: tooltip, example box, WooCommerce integration
	// ------------------------------------------------------------------
	private function tooltip( string $text ): string {
		return ' <span class="mwvip-tooltip" title="' . esc_attr( $text ) . '" aria-label="' . esc_attr( $text ) . '"><span class="dashicons dashicons-editor-help" style="font-size:16px;vertical-align:middle;color:#646970;"></span></span>';
	}

	private function example_box( string $title, string $code, string $note = '' ): string {
		$html  = '<div class="mwvip-example"><strong>' . esc_html( $title ) . '</strong>';
		$html .= '<code style="display:block;background:#f6f7f7;padding:6px 8px;border-radius:4px;margin:6px 0;font-size:12px;white-space:pre-wrap;">' . esc_html( $code ) . '</code>';
		if ( '' !== $note ) {
			$html .= '<em style="font-size:12px;color:#50575e;">' . esc_html( $note ) . '</em>';
		}
		$html .= '</div>';
		return $html;
	}

	private function wc_products_url(): string {
		return admin_url( 'edit.php?post_type=product' );
	}

	private function wc_add_product_url(): string {
		return admin_url( 'post-new.php?post_type=product' );
	}

	private function render_product_reference(): string {
		$html  = '<div class="mwvip-product-ref">';
		$html .= '<p><a href="' . esc_url( $this->wc_products_url() ) . '" class="button" target="_blank" rel="noopener">' . esc_html__( 'Open WooCommerce → Products', 'music-wave-vip' ) . ' <span class="dashicons dashicons-external" style="font-size:16px;vertical-align:middle;"></span></a> ';
		$html .= '<a href="' . esc_url( $this->wc_add_product_url() ) . '" class="button button-primary" target="_blank" rel="noopener" style="margin-left:6px;">' . esc_html__( '+ Create new plan product', 'music-wave-vip' ) . '</a></p>';
		$html .= '<p class="description">' . esc_html__( 'Tip: Create a normal WooCommerce product (Simple product, price, etc.). Note its ID from the Products list (hover → ID). Then reference that ID below.', 'music-wave-vip' ) . '</p>';

		// Lightweight product table if WooCommerce is active.
		if ( function_exists( 'wc_get_products' ) || post_type_exists( 'product' ) ) {
			$products = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => 8,
					'post_status'    => array( 'publish', 'draft' ),
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( ! empty( $products ) ) {
				$html .= '<table class="widefat striped" style="margin-top:10px;max-width:780px;"><thead><tr><th>' . esc_html__( 'ID', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Product', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Status', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Actions', 'music-wave-vip' ) . '</th></tr></thead><tbody>';
				foreach ( $products as $p ) {
					$edit  = get_edit_post_link( $p->ID );
					$html .= '<tr><td><code>' . esc_html( (string) $p->ID ) . '</code></td><td>' . esc_html( get_the_title( $p->ID ) ) . '</td><td>' . esc_html( get_post_status( $p->ID ) ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'music-wave-vip' ) . '</a></td></tr>';
				}
				$html .= '</tbody></table>';
				$html .= '<p class="description">' . esc_html__( 'Copy an ID into the plan field below. Use comma-separated IDs if one level accepts multiple products.', 'music-wave-vip' ) . '</p>';
			} else {
				$html .= '<p class="description">' . esc_html__( 'No products found. Create your first VIP plan product to get started.', 'music-wave-vip' ) . '</p>';
			}
		} else {
			$html .= '<div class="notice notice-warning inline" style="margin:10px 0;"><p>' . esc_html__( 'WooCommerce is not active. Activate it to manage VIP plan products. Product IDs will be available once WooCommerce is enabled.', 'music-wave-vip' ) . '</p></div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function delivery_section(): void {
		echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #2271b1;padding:12px 16px;margin-bottom:16px;">';
		echo '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'Where protected files live and how they are delivered.', 'music-wave-vip' ) . '</strong></p>';
		echo '<p style="margin:0;color:#50575e;">' . esc_html__( 'Local mode keeps files outside the public web root and streams them via PHP or server acceleration (X-Sendfile / X-Accel). Remote redirect mode creates short-lived HTTPS HMAC URLs for S3, R2, CDN or your own signed host.', 'music-wave-vip' ) . '</p>';
		echo '<p style="margin:8px 0 0;"><span class="dashicons dashicons-lightbulb" style="color:#d63638;"></span> <strong>' . esc_html__( 'Beginner guide:', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'Start with Local protected storage. Use Remote redirect only when your host accepts the expires, signature, and mode query parameters described below, or when a developer connects the music_wave_vip_remote_download_url filter.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function general_section(): void {
		$enabled = 'enabled' === (string) VipSettings::all()['module_enabled'];
		if ( $enabled ) {
			echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #00a32a;padding:12px 16px;margin-bottom:16px;">';
			echo '<p style="margin:0;"><strong>' . esc_html__( 'VIP membership enforcement is ON.', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'Membership-gated releases require a valid membership level, plan grants are processed automatically, and protected downloads follow the access rules below.', 'music-wave-vip' ) . '</p>';
			echo '</div>';
			return;
		}
		echo '<div class="notice notice-warning inline" style="margin:0 0 16px;"><p><span class="dashicons dashicons-unlock" style="color:#d63638;"></span> <strong>' . esc_html__( 'VIP membership enforcement is OFF — every membership-gated release is currently free for everyone (guests included).', 'music-wave-vip' ) . '</strong></p><p style="margin:6px 0 0;">' . esc_html__( 'Plan purchases stop granting or revoking access while the switch is off, but stored grants are preserved and everything resumes when you switch it back on. Secure file delivery keeps running; WooCommerce per-release purchases are never affected by this switch.', 'music-wave-vip' ) . '</p></div>';
	}

	public function module_enabled_field(): void {
		$value = (string) VipSettings::all()['module_enabled'];
		echo '<label style="display:inline-flex;align-items:flex-start;gap:10px;padding:14px;border:2px solid ' . ( 'enabled' === $value ? '#00a32a' : '#d63638' ) . ';border-radius:6px;background:' . ( 'enabled' === $value ? '#f0f9f1' : '#fcf0f1' ) . ';max-width:760px;box-sizing:border-box;"><input type="checkbox" id="mwvip-module-enabled" name="' . esc_attr( VipSettings::OPTION ) . '[module_enabled]" value="enabled" ' . checked( $value, 'enabled', false ) . ' style="margin-top:4px;"> <span><strong style="font-size:14px;">' . esc_html__( 'Enable VIP membership enforcement (master switch)', 'music-wave-vip' ) . '</strong><br><span class="description">' . esc_html__( 'On: membership levels, plan purchases, and expiry rules are enforced. Off: all membership-gated content becomes free for everyone while secure delivery keeps working — exactly as if the paywall was lifted for a promotion. WooCommerce per-release purchases are never touched by this switch.', 'music-wave-vip' ) . '</span></span></label>';
	}

	/**
	 * Whether the membership and access controls render disabled.
	 *
	 * While enforcement is switched off those settings have no effect, so the
	 * UI keeps them visible but blocks edits — the stored configuration
	 * survives and resumes unchanged when enforcement is switched back on.
	 * Delivery settings stay editable because protected delivery keeps
	 * running regardless of the master switch.
	 */
	private function membership_controls_disabled(): bool {
		return 'disabled' === (string) VipSettings::all()['module_enabled'];
	}

	public function delivery_access_field(): void {
		$value    = (string) VipSettings::all()['delivery_access'];
		$disabled = $this->membership_controls_disabled();
		foreach ( array(
			'logged_in' => array(
				__( 'Registered users only', 'music-wave-vip' ),
				__( 'Visitors must register and sign in to stream or download protected audio (recommended).', 'music-wave-vip' ),
			),
			'everyone'  => array(
				__( 'Everyone, including guests', 'music-wave-vip' ),
				__( 'Secure-delivery mode: registration is optional. Every visitor may stream and download, but links stay signed, short-lived, and rate-limited. Combine with a remote signed host to run a “download host only” setup without selling memberships on this site.', 'music-wave-vip' ),
			),
		) as $key => $info ) {
			echo '<label style="display:flex;align-items:flex-start;gap:8px;margin:0.6em 0;padding:10px;border:1px solid ' . ( $key === $value ? '#2271b1' : '#ccd0d4' ) . ';border-radius:4px;background:' . ( $key === $value ? '#f0f6fc' : '#fff' ) . ';max-width:760px;"><input type="radio" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[delivery_access]" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . disabled( $disabled, true, false ) . ' style="margin-top:3px;"> <span><strong>' . esc_html( $info[0] ) . '</strong><br><span class="description" style="font-size:12px;">' . esc_html( $info[1] ) . '</span></span></label>';
		}
	}

	/**
	 * Plans management table plus the one-click default-plan button.
	 */
	private function render_plans_manager(): void {
		$config = VipSettings::all();
		$plans  = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();
		$woo    = post_type_exists( 'product' );

		echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:860px;margin-bottom:16px;">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Plan management', 'music-wave-vip' ) . '</h3>';
		if ( $woo ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . PlanProducts::ACTION_CREATE_DEFAULTS ), PlanProducts::ACTION_CREATE_DEFAULTS );
			echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary"><span class="dashicons dashicons-superhero" style="vertical-align:middle;font-size:16px;"></span> ' . esc_html__( 'Create default plans (1 / 6 / 12 months)', 'music-wave-vip' ) . '</a> <span class="description" style="margin-left:8px;">' . esc_html__( 'Creates three virtual WooCommerce products and maps them as VIP plans. Safe to press repeatedly — existing mappings are kept. Set each product price afterwards in WooCommerce.', 'music-wave-vip' ) . '</span></p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Activate WooCommerce to create and sell plan products.', 'music-wave-vip' ) . '</p></div>';
		}

		if ( empty( $plans ) ) {
			echo '<p class="description" style="color:#d63638;">' . esc_html__( 'No plan mappings yet. Use the button above or add lines in the mapping box below.', 'music-wave-vip' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:860px;"><thead><tr><th>' . esc_html__( 'Level', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Product', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Duration', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'Actions', 'music-wave-vip' ) . '</th></tr></thead><tbody>';
			foreach ( $plans as $plan ) {
				if ( ! is_array( $plan ) || empty( $plan['level'] ) ) {
					continue;
				}
				$level    = sanitize_key( (string) $plan['level'] );
				$days     = isset( $plan['duration_days'] ) ? absint( $plan['duration_days'] ) : 0;
				$ids      = isset( $plan['product_ids'] ) && is_array( $plan['product_ids'] ) ? array_map( 'absint', $plan['product_ids'] ) : array();
				$products = array();
				foreach ( $ids as $id ) {
					$edit       = get_edit_post_link( $id );
					$products[] = $edit ? '<a href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">#' . esc_html( (string) $id ) . ' ' . esc_html( get_the_title( $id ) ) . '</a>' : '#' . esc_html( (string) $id );
				}
				$remove = wp_nonce_url(
					admin_url( 'admin-post.php?action=' . PlanProducts::ACTION_REMOVE_PLAN . '&level=' . rawurlencode( $level ) ),
					PlanProducts::ACTION_REMOVE_PLAN
				);
				echo '<tr><td><code>' . esc_html( $level ) . '</code></td><td>' . implode( ', ', $products ) . '</td><td>' . esc_html( PlanProducts::duration_label( $days ) ) . '</td><td>';
				if ( $woo ) {
					// A link, not a nested form: this table renders inside the main
					// settings form and a nested <form> would break its nonce.
					echo '<a class="button button-small" href="' . esc_url( $remove ) . '" onclick="return confirm(\'' . esc_js( __( 'Remove this plan mapping? The WooCommerce product itself is not deleted.', 'music-wave-vip' ) ) . '\');">' . esc_html__( 'Remove mapping', 'music-wave-vip' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Removing a mapping stops future grants for that level; already-granted users keep their access until it expires or is revoked. Edit a product directly in WooCommerce to change its price.', 'music-wave-vip' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * One-shot notice after a plan admin action redirected back.
	 */
	private function render_plan_action_notice(): void {
		$notice = get_transient( 'mwvip_plan_action_notice' );
		if ( ! is_string( $notice ) || '' === $notice ) {
			return;
		}
		delete_transient( 'mwvip_plan_action_notice' );
		$messages = array(
			'mwvip_defaults_created' => __( 'Default plan products created. Set their prices in WooCommerce → Products.', 'music-wave-vip' ),
			'mwvip_defaults_skipped' => __( 'Default plans were already configured — nothing new to create.', 'music-wave-vip' ),
			'mwvip_defaults_failed'  => __( 'Creating the default plan products failed. Is WooCommerce active?', 'music-wave-vip' ),
			'mwvip_plan_removed'     => __( 'Plan mapping removed. The product itself was not deleted.', 'music-wave-vip' ),
		);
		$class    = 'mwvip_defaults_failed' === $notice ? 'notice-error' : 'notice-success';
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	public function membership_section(): void {
		echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #00a32a;padding:12px 16px;margin-bottom:16px;">';
		echo '<p style="margin:0 0 8px;">' . esc_html__( 'Access Policy remains the single source of truth. Select one or more membership sources; a user needs at least one matching level to access restricted releases. Levels are plain slugs you define per release (e.g. gold, vipgold).', 'music-wave-vip' ) . ' ' . esc_html__( 'Never put emails, passwords, direct file URLs, or private API tokens into release membership levels.', 'music-wave-vip' ) . '</p>';
		echo '<p style="margin:8px 0 0;background:#f6f7f7;padding:8px;border-radius:4px;"><strong>' . esc_html__( 'WooCommerce plans:', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'Create a normal WooCommerce product as your VIP plan, list it below, and set protected releases to Membership mode with the matching level key. When a customer purchases the plan, their account instantly gains that level and the VIP role; refunds or cancelled orders remove it immediately. Timed plans (with :days) auto-expire and demote the user to Standard.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function plan_rows_field(): void {
		$value    = (string) VipSettings::all()['plan_rows'];
		$disabled = $this->membership_controls_disabled();
		$plans    = VipPlans::from_settings()->active_levels( 0 ); // Dummy to ensure class loaded.
		$all      = VipSettings::all();
		$parsed   = isset( $all['vip_plans'] ) && is_array( $all['vip_plans'] ) ? $all['vip_plans'] : array();
		$count    = count( $parsed );
		echo $this->render_product_reference();
		echo '<div style="margin-top:16px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:780px;">';
		echo '<label for="mwvip-plan-rows" style="font-weight:600;">' . esc_html__( 'VIP plan mapping', 'music-wave-vip' ) . $this->tooltip( __( 'One plan per line. Format is strict to prevent misconfiguration.', 'music-wave-vip' ) ) . '</label>';
		echo '<textarea id="mwvip-plan-rows" class="large-text code mwvip-module-control" rows="5" placeholder="vipgold:123&#10;silver:45,46:365&#10;bronze:78:30" name="' . esc_attr( VipSettings::OPTION ) . '[plan_rows]" spellcheck="false" ' . disabled( $disabled, true, false ) . ' style="font-family:Consolas,Monaco,monospace;margin-top:8px;">' . esc_textarea( $value ) . '</textarea>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">';
		echo '<div><strong>' . esc_html__( 'Syntax', 'music-wave-vip' ) . '</strong><br><code>level:product_id[:days]</code><br><span class="description">' . esc_html__( 'level — membership level slug (a-z, 0-9, _, -) matching the release “Membership levels” field.', 'music-wave-vip' ) . '</span></div>';
		echo '<div><strong>' . esc_html__( 'Examples', 'music-wave-vip' ) . '</strong><br><code>vipgold:123</code> — ' . esc_html__( 'lifetime via product 123', 'music-wave-vip' ) . '<br><code>silver:45,46:365</code> — ' . esc_html__( '1-year via product 45 or 46', 'music-wave-vip' ) . '</div>';
		echo '</div>';
		echo $this->example_box( __( 'Example complete mapping', 'music-wave-vip' ), "vipgold:101\nsilver:102,103:365\nbronze:104:30  # 30-day trial\n# lines starting with # are comments", __( 'Product IDs are WooCommerce product IDs. Duration is in days (1–3650); omit for lifetime.', 'music-wave-vip' ) );
		if ( $count > 0 ) {
			/* translators: %d: number of configured VIP plans. */
			echo '<p class="description" style="margin-top:8px;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> ' . sprintf( esc_html__( '%d plan(s) currently configured and active.', 'music-wave-vip' ), $count ) . '</p>';
		} else {
			echo '<p class="description" style="margin-top:8px;color:#d63638;"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'No plans configured yet. Add at least one line and save.', 'music-wave-vip' ) . '</p>';
		}
		echo '<p class="description"><a href="' . esc_url( $this->wc_products_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Manage products in WooCommerce →', 'music-wave-vip' ) . '</a> ' . esc_html__( '→ copy the numeric ID and paste it here.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function promote_vip_role_field(): void {
		$value    = (string) VipSettings::all()['promote_vip_role'];
		$disabled = $this->membership_controls_disabled();
		echo '<label style="display:inline-flex;align-items:center;gap:8px;padding:10px;border:1px solid #ccd0d4;border-radius:4px;background:#fff;"><input type="checkbox" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[promote_vip_role]" value="enabled" ' . checked( $value, 'enabled', false ) . disabled( $disabled, true, false ) . '> <span><strong>' . esc_html__( 'Grant the MusicWave VIP role (mw_vip)', 'music-wave-vip' ) . '</strong><br><span class="description">' . esc_html__( 'The role has only the read capability so VIP accounts are identifiable in Users → All Users. Access decisions never depend on the role alone; they re-check grants and expiry live. Leave enabled.', 'music-wave-vip' ) . '</span></span></label>';
	}

	public function delivery_provider_field(): void {
		$value = VipSettings::all()['delivery_provider'];
		echo '<select name="' . esc_attr( VipSettings::OPTION ) . '[delivery_provider]" id="mwvip-delivery-provider" style="min-width:280px;">';
		foreach ( array(
			'local'           => __( 'Local protected directory (recommended)', 'music-wave-vip' ),
			'remote_redirect' => __( 'Remote HTTPS host with HMAC redirect', 'music-wave-vip' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>' . $this->tooltip( __( 'Local never exposes secrets to the browser. Remote signs a short-lived URL (HMAC-SHA256) and redirects.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'Local = PHP or X-Sendfile/X-Accel streaming from outside public_html. Remote = S3/R2/CDN signed URL.', 'music-wave-vip' ) . '</p>';
		echo $this->example_box( __( 'When to use which', 'music-wave-vip' ), "Local: small catalogs, low traffic, or when you control the server.\nRemote: high traffic / large FLAC/ZIP, or object storage + CDN.", __( 'Both enforce entitlement, expiry, and signed URLs.', 'music-wave-vip' ) );
	}

	public function root_field(): void {
		$settings = VipSettings::all();
		$value    = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) constant( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) : (string) $settings['protected_root'];
		$default  = dirname( rtrim( ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR . 'musicwave-private';
		$locked   = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' );
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[protected_root]" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $default ) . '" ' . disabled( $locked, true, false ) . ' style="min-width:420px;">' . $this->tooltip( __( 'Absolute path outside public_html/public. Must exist, be readable/writable, and have .htaccess deny fallback.', 'music-wave-vip' ) );
		if ( $locked ) {
			echo '<p class="description" style="color:#d63638;"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Overridden by wp-config.php constant MUSIC_WAVE_VIP_PROTECTED_ROOT. Edit wp-config.php to change.', 'music-wave-vip' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Leave blank to use the auto-provisioned folder beside WordPress (when provably outside the web root).', 'music-wave-vip' ) . '<br>' . esc_html__( 'Strongly recommended for production:', 'music-wave-vip' ) . ' <code>/var/private/musicwave</code> ' . esc_html__( 'or', 'music-wave-vip' ) . ' <code>/home/user/musicwave-private</code> ' . esc_html__( '(not inside public_html).', 'music-wave-vip' ) . '</p>';
		}
		echo $this->example_box( __( 'Nginx / Apache hardening (info)', 'music-wave-vip' ), "# nginx: location /musicwave-protected/ { internal; alias /var/private/musicwave/; }\n# apache .htaccess in protected dir: Require all denied", __( 'Primary security is keeping the folder outside the web root; .htaccess/web.config are defense-in-depth only.', 'music-wave-vip' ) );
	}

	public function remote_base_url_field(): void {
		$value = (string) VipSettings::all()['remote_base_url'];
		echo '<input class="regular-text code" type="url" inputmode="url" placeholder="https://downloads.example.com/files" name="' . esc_attr( VipSettings::OPTION ) . '[remote_base_url]" value="' . esc_attr( $value ) . '" style="min-width:420px;">' . $this->tooltip( __( 'Must be HTTPS. Final URL = base / prefix / asset-id. Host is allowlisted.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'HTTPS only. Example:', 'music-wave-vip' ) . ' <code>https://cdn.example.com/musicwave</code> — ' . esc_html__( 'the generated URL becomes base + “/” + encoded asset path.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_path_prefix_field(): void {
		$value = (string) VipSettings::all()['remote_path_prefix'];
		echo '<input class="regular-text code" type="text" placeholder="musicwave" name="' . esc_attr( VipSettings::OPTION ) . '[remote_path_prefix]" value="' . esc_attr( $value ) . '">' . $this->tooltip( __( 'Optional bucket prefix / folder. Only A-Z, 0-9, ., _, -, / are kept; traversal stripped.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'Example:', 'music-wave-vip' ) . ' <code>vip-assets</code> → <code>https://cdn.example.com/vip-assets/album/track.flac</code></p>';
	}

	public function remote_signing_secret_field(): void {
		$value = (string) VipSettings::all()['remote_signing_secret'];
		$has   = '' !== $value;
		echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
		echo '<input class="regular-text code" type="password" autocomplete="new-password" id="mwvip-remote-secret" name="' . esc_attr( VipSettings::OPTION ) . '[remote_signing_secret]" value="" placeholder="' . esc_attr( $has ? __( 'Stored secret — leave blank to keep', 'music-wave-vip' ) : __( 'Generate: openssl rand -hex 32', 'music-wave-vip' ) ) . '" style="min-width:360px;">';
		echo '<button type="button" class="button" id="mwvip-toggle-secret" aria-label="' . esc_attr__( 'Show/hide secret', 'music-wave-vip' ) . '"><span class="dashicons dashicons-visibility"></span></button>';
		echo '<button type="button" class="button" id="mwvip-gen-secret">' . esc_html__( 'Generate', 'music-wave-vip' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'At least 32 random characters. Stored server-side, never sent to the browser. Generate with:', 'music-wave-vip' ) . ' <code>openssl rand -hex 32</code> ' . esc_html__( 'or the Generate button.', 'music-wave-vip' ) . '</p>';
		if ( $has && strlen( $value ) < 32 ) {
			echo '<p style="color:#d63638;"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Current secret is too short (<32 chars). Signed URLs will not be issued until you save a stronger secret.', 'music-wave-vip' ) . '</p>';
		}
	}

	public function remote_signature_param_field(): void {
		$this->text_field( 'remote_signature_param', 'signature', __( 'Query key receiving the base64url HMAC-SHA256 signature.', 'music-wave-vip' ) );
		echo $this->example_box( __( 'Example remote URL', 'music-wave-vip' ), 'https://cdn.example.com/files/album/track.flac?expires=...&signature=...&mode=download&kid=k2026', __( 'Every param (path, expiry, mode, kid) is covered by the HMAC so none can be tampered with.', 'music-wave-vip' ) );
	}

	public function remote_expires_param_field(): void {
		$this->text_field( 'remote_expires_param', 'expires', __( 'Query key receiving the Unix expiry timestamp.', 'music-wave-vip' ) );
	}

	public function remote_ttl_field(): void {
		$value = absint( VipSettings::all()['remote_ttl'] );
		echo '<input class="small-text" type="number" min="30" max="900" step="1" name="' . esc_attr( VipSettings::OPTION ) . '[remote_ttl]" value="' . esc_attr( (string) $value ) . '"> <span>' . esc_html__( 'seconds (30–900)', 'music-wave-vip' ) . '</span>' . $this->tooltip( __( 'Short TTL limits replay. Core token (5–15 min) + remote URL TTL both apply; entitlement is re-checked on every delivery.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'Recommended: 120–300 seconds. Shorter = safer but requires good clock sync.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_allowed_hosts_field(): void {
		$hosts = (array) VipSettings::all()['remote_allowed_hosts'];
		echo '<textarea class="regular-text code" rows="3" name="' . esc_attr( VipSettings::OPTION ) . '[remote_allowed_hosts]" placeholder="mirror.example.net&#10;cdn2.example.com" style="max-width:420px;">' . esc_textarea( implode( "\n", array_map( 'strval', $hosts ) ) ) . '</textarea>' . $this->tooltip( __( 'One host per line. The remote base URL host is always allowed; redirects to any other host are refused.', 'music-wave-vip' ) );
	}

	public function remote_key_id_field(): void {
		$value = (string) VipSettings::all()['remote_key_id'];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[remote_key_id]" value="' . esc_attr( $value ) . '" placeholder="k2026">' . $this->tooltip( __( 'Optional rotation identifier appended as kid= — lets the remote host try the new secret then the old without downtime.', 'music-wave-vip' ) );
	}

	public function sendfile_mode_field(): void {
		$value = (string) VipSettings::all()['sendfile_mode'];
		$modes = array(
			'none'      => __( 'PHP streaming (default)', 'music-wave-vip' ),
			'xsendfile' => __( 'Apache/LiteSpeed X-Sendfile', 'music-wave-vip' ),
			'xaccel'    => __( 'nginx X-Accel-Redirect', 'music-wave-vip' ),
		);
		echo '<select name="' . esc_attr( VipSettings::OPTION ) . '[sendfile_mode]" id="mwvip-sendfile-mode">';
		foreach ( $modes as $mode => $label ) {
			echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $value, $mode, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>' . $this->tooltip( __( 'Offloads large files to the web server so PHP workers stay free. Requires server setup.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'None = PHP reads and streams (simple, slower for large FLAC/ZIP). Choose X-Sendfile/X-Accel only if your host has it enabled.', 'music-wave-vip' ) . '</p>';
	}

	public function xaccel_prefix_field(): void {
		$value = (string) VipSettings::all()['xaccel_prefix'];
		echo '<input class="regular-text code" type="text" placeholder="/musicwave-protected" name="' . esc_attr( VipSettings::OPTION ) . '[xaccel_prefix]" value="' . esc_attr( $value ) . '">' . $this->tooltip( __( 'Nginx internal location that aliases to the protected directory. Must start with /.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'Example nginx rule:', 'music-wave-vip' ) . ' <code>location /musicwave-protected/ { internal; alias /var/private/musicwave/; }</code></p>';
	}

	public function membership_sources_field(): void {
		$selected = (array) VipSettings::all()['membership_sources'];
		$disabled = $this->membership_controls_disabled();
		$options  = array(
			'role'                      => array( __( 'WordPress roles (level equals role slug)', 'music-wave-vip' ), __( 'Simple: assign a WP role as level. Example: subscriber.', 'music-wave-vip' ) ),
			'filter'                    => array( __( 'External membership filter (developer adapter)', 'music-wave-vip' ), __( 'For custom integrations via music_wave_vip_membership_access filter.', 'music-wave-vip' ) ),
			'woocommerce_plans'         => array( __( 'WooCommerce plan products — Recommended', 'music-wave-vip' ), __( 'Sell a Woo product as a VIP plan; purchase grants level instantly, refund revokes.', 'music-wave-vip' ) ),
			'woocommerce_memberships'   => array( __( 'WooCommerce Memberships (plan- prefix when needed)', 'music-wave-vip' ), __( 'Requires WooCommerce Memberships extension.', 'music-wave-vip' ) ),
			'woocommerce_subscriptions' => array( __( 'WooCommerce Subscriptions (subscription-123 or product ID)', 'music-wave-vip' ), __( 'Requires WooCommerce Subscriptions extension.', 'music-wave-vip' ) ),
		);
		foreach ( $options as $key => $info ) {
			list( $label, $help ) = $info;
			$checked              = in_array( $key, $selected, true );
			echo '<label style="display:flex;align-items:center;gap:8px;margin:0.6em 0;padding:8px;border:1px solid ' . ( $checked ? '#00a32a' : '#ccd0d4' ) . ';border-radius:4px;background:' . ( $checked ? '#f0f6fc' : '#fff' ) . '"><input type="checkbox" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[membership_sources][]" value="' . esc_attr( $key ) . '" ' . checked( $checked, true, false ) . disabled( $disabled, true, false ) . '> <span><strong>' . esc_html( $label ) . '</strong><br><span class="description" style="font-size:12px;">' . esc_html( $help ) . '</span></span></label>';
		}
		echo '<p class="description">' . esc_html__( 'Tip: Use neutral release levels like gold, vipgold, or plan-pro. The same level string must be entered in the Release → Access → Membership levels field.', 'music-wave-vip' ) . '</p>';
	}

	private function text_field( string $key, string $placeholder, string $description ): void {
		$value = (string) VipSettings::all()[ $key ];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" style="min-width:260px;">' . $this->tooltip( $description ) . '<p class="description">' . esc_html( $description ) . '</p>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->register_help_tabs();
		$settings      = VipSettings::all();
		$remote_active = 'remote_redirect' === (string) $settings['delivery_provider'];
		$module_on     = 'enabled' === (string) $settings['module_enabled'];
		?>
		<style>
			.mwvip-header{background:#fff;border:1px solid #ccd0d4;padding:18px 20px;border-radius:6px;margin-bottom:16px;}
			.mwvip-header h1{margin:0;font-size:22px;display:flex;align-items:center;gap:10px;}
			.mwvip-badge{background:#2271b1;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;letter-spacing:.04em;}
			.mwvip-header p{margin:4px 0 0;color:#50575e;max-width:560px;}
			.mwvip-tabs{border-bottom:1px solid #ccd0d4;margin-bottom:0;display:flex;gap:0;}
			.mwvip-tabs button{background:none;border:1px solid transparent;border-bottom:none;padding:10px 16px;cursor:pointer;font-weight:600;color:#50575e;border-radius:4px 4px 0 0;}
			.mwvip-tabs button.active{background:#fff;border-color:#ccd0d4;color:#1d2327;}
			.mwvip-panel{display:none;background:#fff;border:1px solid #ccd0d4;border-top:none;padding:20px;border-radius:0 0 6px 6px;}
			.mwvip-panel.active{display:block;}
				.mwvip-example{margin-top:8px;}
				.mwvip-tooltip{cursor:help;}
				.mwvip-module-control:disabled{opacity:.65;}
				.mwvip-related{display:flex;flex-direction:column;gap:8px;}
				.mwvip-related a.button{align-self:flex-start;}
				.form-table th{vertical-align:top;padding-top:18px;width:220px;}
				.mwvip-quicklinks a.button{margin-right:6px;}
		</style>
		<div class="wrap" id="mwvip-settings">
			<div class="mwvip-header">
				<div>
					<h1><span class="dashicons dashicons-shield-alt" style="font-size:26px;color:#2271b1;"></span> <?php esc_html_e( 'MusicWave VIP', 'music-wave-vip' ); ?> <span class="mwvip-badge"><?php echo esc_html( defined( 'MUSIC_WAVE_VIP_VERSION' ) ? MUSIC_WAVE_VIP_VERSION : 'VIP' ); ?></span> <span class="mwvip-badge" style="background:<?php echo $module_on ? '#00a32a' : '#d63638'; ?>;"><?php echo esc_html( $module_on ? __( 'Enforcement on', 'music-wave-vip' ) : __( 'All content free', 'music-wave-vip' ) ); ?></span></h1>
					<p><?php esc_html_e( 'Protected file delivery, signed remote URLs, and WooCommerce plan memberships — all fail-closed and entitlement-aware.', 'music-wave-vip' ); ?></p>
				</div>
				<div class="mwvip-quicklinks">
					<a href="<?php echo esc_url( $this->wc_products_url() ); ?>" class="button" target="_blank" rel="noopener"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce Products', 'music-wave-vip' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release' ) ); ?>" class="button"><span class="dashicons dashicons-album"></span> <?php esc_html_e( 'Releases', 'music-wave-vip' ); ?></a>
					<a href="https://manacore.dev/musicwave/docs/vip" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Docs', 'music-wave-vip' ); ?> <span class="dashicons dashicons-external"></span></a>
				</div>
			</div>
			<?php
			settings_errors( VipSettings::OPTION );
			$this->render_preflight_notice();
			$this->render_plan_action_notice();
			?>
			<div class="mwvip-tabs" role="tablist">
				<button type="button" role="tab" aria-selected="true" data-tab="general" class="active"><span class="dashicons dashicons-admin-settings" style="vertical-align:middle;"></span> <?php esc_html_e( 'General', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="delivery"><span class="dashicons dashicons-cloud" style="vertical-align:middle;"></span> <?php esc_html_e( 'Delivery', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="membership"><span class="dashicons dashicons-groups" style="vertical-align:middle;"></span> <?php esc_html_e( 'Membership & Plans', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="advanced"><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;"></span> <?php esc_html_e( 'Advanced', 'music-wave-vip' ); ?></button>
			</div>
			<form action="options.php" method="post" id="mwvip-form">
				<?php
				settings_fields( 'music_wave_vip' );
				// Identifies full-form submissions so sanitize_settings() can
				// apply unchecked-checkbox semantics to the master switch.
				echo '<input type="hidden" name="' . esc_attr( VipSettings::OPTION ) . '[mwvip_full_form]" value="1">';
				?>
				<div class="mwvip-panel active" data-panel="general">
					<h2 style="margin-top:0;"><?php esc_html_e( 'General — master switch and access', 'music-wave-vip' ); ?></h2>
					<?php $this->general_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'VIP enforcement', 'music-wave-vip' ); ?></th><td><?php $this->module_enabled_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'Who may stream & download protected audio', 'music-wave-vip' ); ?></th><td><?php $this->delivery_access_field(); ?></td></tr>
					</table>
				</div>
				<div class="mwvip-panel" data-panel="delivery">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Protected delivery', 'music-wave-vip' ); ?></h2>
					<?php $this->delivery_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Delivery provider', 'music-wave-vip' ); ?></th><td><?php $this->delivery_provider_field(); ?></td></tr>
						<tr class="mwvip-local-row"><th><?php esc_html_e( 'Protected files directory', 'music-wave-vip' ); ?></th><td><?php $this->root_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Remote host base URL', 'music-wave-vip' ); ?></th><td><?php $this->remote_base_url_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Remote path prefix', 'music-wave-vip' ); ?></th><td><?php $this->remote_path_prefix_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Remote signing secret', 'music-wave-vip' ); ?></th><td><?php $this->remote_signing_secret_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Signature query parameter', 'music-wave-vip' ); ?></th><td><?php $this->remote_signature_param_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Expiry query parameter', 'music-wave-vip' ); ?></th><td><?php $this->remote_expires_param_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Remote URL lifetime', 'music-wave-vip' ); ?></th><td><?php $this->remote_ttl_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Additional allowed redirect hosts', 'music-wave-vip' ); ?></th><td><?php $this->remote_allowed_hosts_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'Signing key ID', 'music-wave-vip' ); ?></th><td><?php $this->remote_key_id_field(); ?></td></tr>
					</table>
					<h3><?php esc_html_e( 'Server acceleration', 'music-wave-vip' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Server acceleration', 'music-wave-vip' ); ?></th><td><?php $this->sendfile_mode_field(); ?></td></tr>
						<tr class="mwvip-xaccel-row" <?php echo 'xaccel' === (string) $settings['sendfile_mode'] ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'X-Accel internal prefix', 'music-wave-vip' ); ?></th><td><?php $this->xaccel_prefix_field(); ?></td></tr>
					</table>
				</div>
				<div class="mwvip-panel" data-panel="membership">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Membership & entitlement', 'music-wave-vip' ); ?></h2>
					<?php if ( ! $module_on ) : ?>
						<div class="notice notice-info inline" id="mwvip-membership-note" style="margin:0 0 16px;"><p><span class="dashicons dashicons-hidden" style="color:#2271b1;vertical-align:middle;"></span> <?php esc_html_e( 'Enforcement is switched off, so these options are read-only and not applied. Your configuration is preserved and resumes the moment the master switch is turned back on.', 'music-wave-vip' ); ?></p></div>
					<?php endif; ?>
					<?php $this->render_plans_manager(); ?>
					<?php $this->membership_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Membership sources', 'music-wave-vip' ); ?></th><td><?php $this->membership_sources_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'VIP plan products', 'music-wave-vip' ); ?></th><td><?php $this->plan_rows_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'VIP role promotion', 'music-wave-vip' ); ?></th><td><?php $this->promote_vip_role_field(); ?></td></tr>
					</table>
					<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:4px;padding:12px;margin-top:16px;">
						<h4 style="margin:0 0 8px;"><span class="dashicons dashicons-info" style="color:#2271b1;"></span> <?php esc_html_e( 'How to assign a VIP level to a release', 'music-wave-vip' ); ?></h4>
						<ol style="margin:0 0 0 18px;">
							<li><?php esc_html_e( 'Create/edit a Product in WooCommerce → Products (e.g., “VIP Gold — Yearly”).', 'music-wave-vip' ); ?></li>
							<li><?php /* translators: %s: example plan mapping. */ printf( esc_html__( 'Add a line below, e.g. %s, where level is your membership level slug.', 'music-wave-vip' ), '<code>vipgold:123:365</code>' ); ?></li>
							<li><?php esc_html_e( 'Edit the Release → Access → pick “Membership” and enter the same level (vipgold) in Membership levels.', 'music-wave-vip' ); ?></li>
							<li><?php esc_html_e( 'Publish. Customers who purchase that product instantly gain the level; expiry or refunds revoke it automatically.', 'music-wave-vip' ); ?></li>
						</ol>
					</div>
				</div>
				<div class="mwvip-panel" data-panel="advanced">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Advanced & diagnostics', 'music-wave-vip' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Operational security notes, and where the rest of MusicWave is managed. Every VIP option lives on this page — the links below point to the screens that own non-VIP settings.', 'music-wave-vip' ); ?></p>
					<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1000px;margin-top:12px;">
						<div style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;">
							<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Security notes', 'music-wave-vip' ); ?></h4>
							<ul style="margin:0 0 0 18px;list-style:disc;">
								<li><?php esc_html_e( 'Remote secrets are never exposed to the browser; only short-lived signed URLs are redirected (with Referrer-Policy: no-referrer).', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'Every access decision is re-checked live; no entitlement is cached. Remote URLs include path, expiry, mode, and kid in the HMAC.', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'Keep the protected directory outside every web-served tree. .htaccess/web.config are defense-in-depth only.', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'Timed plans auto-expire via hourly cron and on login; no manual cleanup needed.', 'music-wave-vip' ); ?></li>
							</ul>
						</div>
						<div style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;">
							<h4 style="margin:0 0 8px;"><?php esc_html_e( 'Related settings', 'music-wave-vip' ); ?></h4>
							<div class="mwvip-related">
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings&tab=access' ) ); ?>"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Access messages & membership fallback (Core)', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings&tab=delivery' ) ); ?>"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Download limits, quotas & retention (Core)', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release' ) ); ?>"><span class="dashicons dashicons-album"></span> <?php esc_html_e( 'Per-release access mode & membership levels', 'music-wave-vip' ); ?></a>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce settings', 'music-wave-vip' ); ?></a>
								<?php endif; ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ); ?>"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Setup & diagnostics', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Site Health', 'music-wave-vip' ); ?></a>
							</div>
						</div>
					</div>
				</div>
				<p style="background:#fff;border:1px solid #ccd0d4;padding:12px;border-radius:4px;display:flex;align-items:center;gap:12px;">
					<?php submit_button( __( 'Save VIP settings', 'music-wave-vip' ), 'primary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'Settings are sanitized and validated; weak secrets or bad paths will show a warning and keep the previous value.', 'music-wave-vip' ); ?></span>
				</p>
			</form>
		</div>
		<script>
			(function(){
				var tabs=document.querySelectorAll('.mwvip-tabs button');
				var panels=document.querySelectorAll('.mwvip-panel');
				tabs.forEach(function(btn){
					btn.addEventListener('click',function(){
						var tab=btn.getAttribute('data-tab');
						tabs.forEach(function(b){b.classList.remove('active');b.setAttribute('aria-selected','false');});
						btn.classList.add('active');btn.setAttribute('aria-selected','true');
						panels.forEach(function(p){
							p.classList.toggle('active', p.getAttribute('data-panel')===tab);
						});
					});
				});
				// Delivery provider show/hide
				var sel=document.getElementById('mwvip-delivery-provider');
				if(sel){
					sel.addEventListener('change',function(){
						var isRemote=sel.value==='remote_redirect';
						document.querySelectorAll('.mwvip-remote-row').forEach(function(r){r.style.display=isRemote?'':'none';});
						document.querySelectorAll('.mwvip-local-row').forEach(function(r){r.style.display=isRemote?'none':'';});
					});
				}
				// X-Accel prefix is only relevant in xaccel mode
				var sf=document.getElementById('mwvip-sendfile-mode');
				if(sf){
					sf.addEventListener('change',function(){
						var isXaccel=sf.value==='xaccel';
						document.querySelectorAll('.mwvip-xaccel-row').forEach(function(r){r.style.display=isXaccel?'':'none';});
					});
				}
				// Master switch: membership and access controls are only
				// editable while enforcement is on; delivery stays editable.
				var master=document.getElementById('mwvip-module-enabled');
				if(master){
					master.addEventListener('change',function(){
						var on=master.checked;
						document.querySelectorAll('.mwvip-module-control').forEach(function(el){el.disabled=!on;});
						var note=document.getElementById('mwvip-membership-note');
						if(note){note.style.display=on?'none':'';}
					});
				}
				// Secret toggle/generate
				var tog=document.getElementById('mwvip-toggle-secret');
				var inp=document.getElementById('mwvip-remote-secret');
				if(tog&&inp){tog.addEventListener('click',function(){inp.type=inp.type==='password'?'text':'password';});}
				var gen=document.getElementById('mwvip-gen-secret');
				if(gen&&inp){
					gen.addEventListener('click',function(){
						var chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.~';
						var s='';for(var i=0;i<48;i++) s+=chars.charAt(Math.floor(Math.random()*chars.length));
						inp.type='text';inp.value=s;inp.focus();
					});
				}
			})();
		</script>
		<?php
	}

	/**
	 * Register contextual help tabs for the VIP settings screen.
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
				'id'      => 'music-wave-vip-guide',
				'title'   => __( 'VIP settings', 'music-wave-vip' ),
				'content' =>
					'<p>' . esc_html__( 'This page is the single place where every MusicWave VIP option is configured:', 'music-wave-vip' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'General — the enforcement master switch and who may stream or download protected audio.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'Delivery — local protected storage or a remote signed HTTPS host, plus server acceleration.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'Membership & Plans — entitlement sources and WooCommerce plan products.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'Advanced — security notes and links to the screens that own related non-VIP settings.', 'music-wave-vip' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html__( 'While the master switch is off, membership options are read-only and your configuration is preserved.', 'music-wave-vip' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-vip-diagnostics',
				'title'   => __( 'Help & diagnostics', 'music-wave-vip' ),
				'content' =>
					'<p>' . esc_html__( 'If protected downloads or memberships misbehave, start with the MusicWave environment checks and the WordPress Site Health screen.', 'music-wave-vip' ) . '</p>' .
					'<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ) . '">' . esc_html__( 'Open Setup & diagnostics', 'music-wave-vip' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Open Site Health', 'music-wave-vip' ) . '</a></p>',
			)
		);
	}

	/**
	 * Show why local protected delivery is disabled instead of failing silently.
	 *
	 * @return void
	 */
	private function render_preflight_notice(): void {
		if ( 'local' !== (string) VipSettings::all()['delivery_provider'] ) {
			return;
		}

		$checks = ( new ProtectedAssetStorage() )->preflight();
		$labels = array(
			'root_resolved'    => __( 'A protected directory path is configured or derivable.', 'music-wave-vip' ),
			'directory_exists' => __( 'The protected directory exists.', 'music-wave-vip' ),
			'readable'         => __( 'The web server can read the protected directory.', 'music-wave-vip' ),
			'writable'         => __( 'The web server can write uploads into the protected directory.', 'music-wave-vip' ),
			'outside_web_root' => __( 'The directory is outside every publicly served web root.', 'music-wave-vip' ),
			'deny_files'       => __( 'Defense-in-depth deny files (.htaccess) are present.', 'music-wave-vip' ),
		);

		$failed = array_keys(
			array_filter(
				$checks,
				static function ( bool $passed ): bool {
					return ! $passed;
				}
			)
		);
		if ( array() === $failed ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Protected delivery preflight failed. Local downloads stay disabled until every check passes:', 'music-wave-vip' ) . '</strong></p><ul style="list-style:disc;padding-left:20px">';
		foreach ( $failed as $check ) {
			if ( isset( $labels[ $check ] ) ) {
				echo '<li>' . esc_html( $labels[ $check ] ) . '</li>';
			}
		}
		echo '</ul><p>' . esc_html__( 'On subdirectory installations the automatic default beside WordPress is refused because it is still web-reachable. Configure an absolute path outside the server document root (for example /var/private/musicwave) and move existing files there.', 'music-wave-vip' ) . '</p></div>';
	}

	/** @param array<int, array<string, mixed>> $cards @return array<int, array<string, mixed>> */
	public function integration_card( array $cards ): array {
		$configured   = VipSettings::all();
		$remote_ready = 'remote_redirect' === $configured['delivery_provider'] && '' !== $configured['remote_base_url'] && '' !== $configured['remote_signing_secret'];
		$active       = 'local' === $configured['delivery_provider'] || $remote_ready;
		$plans        = isset( $configured['vip_plans'] ) && is_array( $configured['vip_plans'] ) ? count( $configured['vip_plans'] ) : 0;
		$free_mode    = 'disabled' === (string) $configured['module_enabled'];
		$card         = array(
			'id'          => 'music-wave-vip',
			'name'        => __( 'VIP protected delivery and memberships', 'music-wave-vip' ),
			'active'      => $active,
			'description' => $free_mode
				? __( 'Master switch is OFF: every membership-gated release is currently free for everyone. Protected delivery keeps running.', 'music-wave-vip' )
				: ( $active
					? sprintf(
						/* translators: 1: delivery type, 2: number of VIP plans. */
						__( 'Connected: %1$s delivery active. %2$d VIP plan(s) configured.', 'music-wave-vip' ),
						'local' === $configured['delivery_provider'] ? __( 'Local', 'music-wave-vip' ) : __( 'Remote signed', 'music-wave-vip' ),
						$plans
					)
					: __( 'Connect local protected storage, an HTTPS signed download host, WordPress roles, membership extensions, and WooCommerce subscriptions.', 'music-wave-vip' ) ),
			'url'         => self::page_url(),
			'action'      => __( 'Open VIP settings', 'music-wave-vip' ),
		);
		foreach ( $cards as $index => $existing ) {
			if ( is_array( $existing ) && isset( $existing['id'] ) && 'music-wave-vip' === $existing['id'] ) {
				$cards[ $index ] = $card;
				return $cards;
			}
		}
		$cards[] = $card;
		return $cards;
	}
}
