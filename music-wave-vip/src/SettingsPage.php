<?php
/**
 * MusicWave VIP settings and onboarding guidance.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class SettingsPage {
	public function add_page(): void {
		add_options_page( __( 'MusicWave VIP', 'music-wave-vip' ), __( 'MusicWave VIP', 'music-wave-vip' ), 'manage_options', 'music-wave-vip', array( $this, 'render' ) );
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

		add_settings_section( 'music_wave_vip_membership', __( 'Membership and entitlement adapters', 'music-wave-vip' ), array( $this, 'membership_section' ), 'music-wave-vip' );
		$this->field( 'membership_sources', __( 'Membership sources', 'music-wave-vip' ), 'membership_sources_field', 'music_wave_vip_membership' );
	}

	private function field( string $id, string $label, string $callback, string $section = 'music_wave_vip_delivery' ): void {
		add_settings_field( $id, $label, array( $this, $callback ), 'music-wave-vip', $section );
	}

	/** @param mixed $value @return array<string, mixed> */
	public static function sanitize_settings( $value ): array {
		$value                       = is_array( $value ) ? $value : array();
		$value['membership_sources'] = isset( $value['membership_sources'] ) && is_array( $value['membership_sources'] ) ? $value['membership_sources'] : array();
		$settings                    = VipSettings::sanitize( $value );
		$root                        = (string) $settings['protected_root'];
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

	public function delivery_section(): void {
		echo '<p>' . esc_html__( 'Choose where protected files are delivered. Local mode keeps files outside the public web root. Remote redirect mode creates a short-lived HTTPS HMAC URL for an S3-compatible bucket, CDN, download host, or your own signing endpoint.', 'music-wave-vip' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Beginner guide:', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'Start with Local protected storage. Use Remote redirect only when your host accepts the expires, signature, and mode query parameters described below, or when a developer connects the URL filter.', 'music-wave-vip' ) . '</p>';
	}

	public function membership_section(): void {
		echo '<p>' . esc_html__( 'Access Policy remains the only authority. Select one or more sources; a user needs at least one matching level. Never put emails, passwords, direct file URLs, or private API tokens into release membership levels.', 'music-wave-vip' ) . '</p>';
	}

	public function delivery_provider_field(): void {
		$value = VipSettings::all()['delivery_provider'];
		echo '<select name="' . esc_attr( VipSettings::OPTION ) . '[delivery_provider]">';
		foreach ( array(
			'local'           => __( 'Local protected directory (recommended)', 'music-wave-vip' ),
			'remote_redirect' => __( 'Remote HTTPS host with HMAC redirect', 'music-wave-vip' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Remote mode never sends the remote secret to the browser; only a short-lived signed URL is redirected.', 'music-wave-vip' ) . '</p>';
	}

	public function root_field(): void {
		$settings = VipSettings::all();
		$value    = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) MUSIC_WAVE_VIP_PROTECTED_ROOT : (string) $settings['protected_root'];
		$default  = dirname( rtrim( ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR . 'musicwave-private';
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[protected_root]" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $default ) . '" ' . disabled( defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ), true, false ) . '>';
		echo '<p class="description">' . esc_html__( 'Absolute path outside public_html/public. Leave blank to use musicwave-private beside the WordPress directory. The wp-config.php constant overrides this field.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_base_url_field(): void {
		$value = (string) VipSettings::all()['remote_base_url'];
		echo '<input class="regular-text code" type="url" inputmode="url" placeholder="https://downloads.example.com/files" name="' . esc_attr( VipSettings::OPTION ) . '[remote_base_url]" value="' . esc_attr( $value ) . '"><p class="description">' . esc_html__( 'HTTPS only. The generated URL is base/path-prefix/asset-id.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_path_prefix_field(): void {
		$value = (string) VipSettings::all()['remote_path_prefix'];
		echo '<input class="regular-text code" type="text" placeholder="musicwave" name="' . esc_attr( VipSettings::OPTION ) . '[remote_path_prefix]" value="' . esc_attr( $value ) . '"><p class="description">' . esc_html__( 'Optional path only; traversal and special characters are removed.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_signing_secret_field(): void {
		$value = (string) VipSettings::all()['remote_signing_secret'];
		echo '<input class="regular-text code" type="password" autocomplete="new-password" name="' . esc_attr( VipSettings::OPTION ) . '[remote_signing_secret]" value="" placeholder="' . esc_attr( '' !== $value ? __( 'Stored secret; leave blank to keep it', 'music-wave-vip' ) : __( 'Generate a long random secret', 'music-wave-vip' ) ) . '"><p class="description">' . esc_html__( 'Use at least 32 random characters. It is stored server-side and never exposed in editor responses.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_signature_param_field(): void {
		$this->text_field( 'remote_signature_param', 'signature', __( 'Query key receiving the base64url HMAC-SHA256 signature.', 'music-wave-vip' ) );
	}

	public function remote_expires_param_field(): void {
		$this->text_field( 'remote_expires_param', 'expires', __( 'Query key receiving the Unix expiry timestamp.', 'music-wave-vip' ) );
	}

	public function remote_ttl_field(): void {
		$value = absint( VipSettings::all()['remote_ttl'] );
		echo '<input class="small-text" type="number" min="30" max="900" step="1" name="' . esc_attr( VipSettings::OPTION ) . '[remote_ttl]" value="' . esc_attr( (string) $value ) . '"> <span>' . esc_html__( 'seconds (30–900)', 'music-wave-vip' ) . '</span><p class="description">' . esc_html__( 'Keep this short. The Core token and remote URL both expire; entitlement is checked again before every delivery.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_allowed_hosts_field(): void {
		$hosts = (array) VipSettings::all()['remote_allowed_hosts'];
		echo '<textarea class="regular-text code" rows="3" name="' . esc_attr( VipSettings::OPTION ) . '[remote_allowed_hosts]">' . esc_textarea( implode( "\n", array_map( 'strval', $hosts ) ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One host per line. The remote base URL host is always allowed; redirects to any other host are refused.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_key_id_field(): void {
		$value = (string) VipSettings::all()['remote_key_id'];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[remote_key_id]" value="' . esc_attr( $value ) . '">';
		echo '<p class="description">' . esc_html__( 'Optional key identifier appended as the kid parameter so the remote host can rotate signing secrets without downtime.', 'music-wave-vip' ) . '</p>';
	}

	public function membership_sources_field(): void {
		$selected = (array) VipSettings::all()['membership_sources'];
		$options  = array(
			'role'                      => __( 'WordPress roles (level equals role slug)', 'music-wave-vip' ),
			'filter'                    => __( 'External membership filter (developer adapter)', 'music-wave-vip' ),
			'woocommerce_memberships'   => __( 'WooCommerce Memberships (level or plan-slug; use plan- prefix when needed)', 'music-wave-vip' ),
			'woocommerce_subscriptions' => __( 'WooCommerce Subscriptions (level is product ID or subscription-123)', 'music-wave-vip' ),
		);
		foreach ( $options as $key => $label ) {
			echo '<label style="display:block;margin:0.35em 0"><input type="checkbox" name="' . esc_attr( VipSettings::OPTION ) . '[membership_sources][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $selected, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Use neutral release levels such as gold, plan-pro, or subscription-123. If a selected Woo extension is inactive, it fails closed and does not grant access.', 'music-wave-vip' ) . '</p>';
	}

	private function text_field( string $key, string $default, string $description ): void {
		$value = (string) VipSettings::all()[ $key ];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $default ) . '"><p class="description">' . esc_html( $description ) . '</p>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'MusicWave VIP', 'music-wave-vip' ) . '</h1>';
		settings_errors( VipSettings::OPTION );
		$this->render_preflight_notice();
		echo '<form action="options.php" method="post">';
		settings_fields( 'music_wave_vip' );
		do_settings_sections( 'music-wave-vip' );
		submit_button();
		echo '</form></div>';
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
		$card         = array(
			'id'          => 'music-wave-vip',
			'name'        => __( 'VIP protected delivery and memberships', 'music-wave-vip' ),
			'active'      => 'local' === $configured['delivery_provider'] || $remote_ready,
			'description' => __( 'Connect local protected storage, an HTTPS signed download host, WordPress roles, membership extensions, and WooCommerce subscriptions.', 'music-wave-vip' ),
			'url'         => admin_url( 'options-general.php?page=music-wave-vip' ),
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

	public function render_embedded(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'VIP delivery and membership settings', 'music-wave-vip' ) . '</h2><form action="options.php" method="post">';
		settings_fields( 'music_wave_vip' );
		do_settings_sections( 'music-wave-vip' );
		submit_button( __( 'Save VIP settings', 'music-wave-vip' ) );
		echo '</form></section>';
	}
}
