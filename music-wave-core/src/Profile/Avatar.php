<?php
/**
 * MusicWave profile avatars: optional user-chosen image via the media library.
 *
 * Storage is a single user-meta attachment ID (`musicwave_avatar_id`), edited
 * from the standard WordPress profile screen with the core media frame — no
 * custom upload handler, no duplicated media code. Display plugs into the
 * standard `get_avatar` filter, so core/avatar, comments, the dashboard, and
 * the account-chip block all pick it up automatically.
 *
 * Behaviour is governed by the theme setting `musicwave_avatar_mode`:
 * `custom` (default) enables upload + uses the image when set, `default`
 * shows the bundled theme silhouette for everyone, `off` disables the whole
 * system and leaves WordPress avatars untouched.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Profile;

final class Avatar {
	/**
	 * User-meta key holding the avatar attachment ID.
	 */
	public const META_KEY = 'musicwave_avatar_id';

	/**
	 * Theme setting name controlling the avatar behaviour.
	 */
	public const MODE_SETTING = 'musicwave_avatar_mode';

	/**
	 * Allowed mode values.
	 *
	 * @var array<int, string>
	 */
	private const MODES = array( 'custom', 'default', 'off' );

	/**
	 * Register profile UI, saving, and display hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'get_avatar', array( $this, 'filter_avatar' ), 10, 5 );
	}

	/**
	 * Current avatar mode from the theme setting.
	 *
	 * Falls back to `custom` when the theme does not register the setting,
	 * so the feature works standalone and degrades gracefully.
	 */
	public static function mode(): string {
		$mode = function_exists( 'get_theme_mod' ) ? (string) get_theme_mod( self::MODE_SETTING, 'custom' ) : 'custom';

		return in_array( $mode, self::MODES, true ) ? $mode : 'custom';
	}

	/**
	 * Validated custom avatar attachment ID for a user, or 0.
	 *
	 * @param int $user_id User ID.
	 */
	public static function user_avatar_id( int $user_id ): int {
		if ( $user_id < 1 ) {
			return 0;
		}
		$attachment_id = absint( get_user_meta( $user_id, self::META_KEY, true ) );
		if ( $attachment_id < 1 || ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * Load the media frame only on profile screens and only when uploads are enabled.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'custom' !== self::mode() || ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'wp_enqueue_media' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'musicwave-profile-avatar',
			MUSIC_WAVE_CORE_URL . 'assets/user-profile-avatar.js',
			array( 'jquery' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			'musicwave-profile-avatar',
			'musicWaveAvatar',
			array(
				'title'  => __( 'انتخاب تصویر پروفایل', 'music-wave-core' ),
				'button' => __( 'استفاده از این تصویر', 'music-wave-core' ),
			)
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'musicwave-profile-avatar', 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
	}

	/**
	 * Render the avatar picker row on the profile screen.
	 *
	 * Hidden entirely unless the `custom` mode is active.
	 *
	 * @param \WP_User $user Profile owner.
	 * @return void
	 */
	public function render_field( $user ): void {
		if ( 'custom' !== self::mode() || ! $user instanceof \WP_User ) {
			return;
		}
		$attachment_id = self::user_avatar_id( (int) $user->ID );
		$preview       = $attachment_id > 0 && function_exists( 'wp_get_attachment_image' )
			? wp_get_attachment_image( $attachment_id, array( 96, 96 ), false, array( 'class' => 'musicwave-avatar-preview__img' ) )
			: get_avatar( (int) $user->ID, 96, '', '', array( 'class' => 'musicwave-avatar-preview__img' ) );
		wp_nonce_field( 'musicwave_avatar_' . (int) $user->ID, 'musicwave_avatar_nonce' );
		?>
		<h2><?php esc_html_e( 'تصویر پروفایل MusicWave', 'music-wave-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr class="musicwave-avatar-row">
				<th scope="row"><?php esc_html_e( 'تصویر پروفایل', 'music-wave-core' ); ?></th>
				<td>
					<div class="musicwave-avatar-preview" data-mw-avatar-preview><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<input type="hidden" id="musicwave_avatar_id" name="musicwave_avatar_id" value="<?php echo esc_attr( $attachment_id > 0 ? (string) $attachment_id : '' ); ?>" data-mw-avatar-input />
					<p>
						<button type="button" class="button" data-mw-avatar-select><?php esc_html_e( 'انتخاب تصویر', 'music-wave-core' ); ?></button>
						<button type="button" class="button button-link-delete" data-mw-avatar-remove<?php echo $attachment_id > 0 ? '' : ' hidden'; ?>><?php esc_html_e( 'حذف تصویر', 'music-wave-core' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'از کتابخانه رسانه یک تصویر مربعی انتخاب کنید. در صورت حذف، آواتار پیش‌فرض وردپرس نمایش داده می‌شود.', 'music-wave-core' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Persist or clear the avatar attachment ID.
	 *
	 * @param int $user_id Profile owner ID.
	 * @return void
	 */
	public function save_field( int $user_id ): void {
		if ( 'custom' !== self::mode() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$nonce = isset( $_POST['musicwave_avatar_nonce'] ) && is_string( $_POST['musicwave_avatar_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['musicwave_avatar_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'musicwave_avatar_' . $user_id ) ) {
			return;
		}
		$raw = isset( $_POST['musicwave_avatar_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['musicwave_avatar_id'] ) ) : '';
		if ( '' === $raw ) {
			delete_user_meta( $user_id, self::META_KEY );

			return;
		}
		$attachment_id = absint( $raw );
		if ( $attachment_id < 1 || ! $this->is_assignable( $attachment_id, $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, self::META_KEY, $attachment_id );
	}

	/**
	 * Whether an attachment may serve as this user's avatar.
	 *
	 * Must be a real image; either owned by the user or assignable by someone
	 * who can manage other users' media.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id       Profile owner ID.
	 */
	private function is_assignable( int $attachment_id, int $user_id ): bool {
		if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type ) {
			return false;
		}
		if ( 0 === strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
			if ( (int) $attachment->post_author === $user_id || current_user_can( 'edit_users' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace the avatar image according to the active mode.
	 *
	 * @param string       $avatar    Default avatar HTML.
	 * @param mixed        $id_or_email User identifier.
	 * @param int|string   $size      Requested size.
	 * @param string       $default   Default avatar URL.
	 * @param string       $alt       Alt text.
	 * @return string
	 */
	public function filter_avatar( string $avatar, $id_or_email, $size, string $default, string $alt ): string {
		$mode = self::mode();
		if ( 'off' === $mode ) {
			return $avatar;
		}
		$size = absint( $size ) > 0 ? absint( $size ) : 96;

		if ( 'default' === $mode ) {
			return $this->default_avatar_markup( $size, $alt );
		}

		$user_id = $this->resolve_user_id( $id_or_email );
		$attachment_id = self::user_avatar_id( $user_id );
		if ( $attachment_id < 1 || ! function_exists( 'wp_get_attachment_image' ) ) {
			return $avatar;
		}
		$custom = wp_get_attachment_image(
			$attachment_id,
			array( $size, $size ),
			false,
			array(
				'class' => 'avatar avatar-' . $size . ' photo musicwave-avatar',
				'alt'   => '' !== $alt ? $alt : $this->user_display_name( $user_id ),
			)
		);

		return is_string( $custom ) && '' !== $custom ? $custom : $avatar;
	}

	/**
	 * Bundled silhouette avatar markup for `default` mode.
	 *
	 * Uses an inline SVG so it works without extra HTTP requests and inherits
	 * the surrounding color scheme.
	 *
	 * @param int    $size Requested size.
	 * @param string $alt  Alt text.
	 */
	private function default_avatar_markup( int $size, string $alt ): string {
		$label = '' !== $alt ? $alt : __( 'تصویر پروفایل پیش‌فرض', 'music-wave-core' );
		$svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle cx="32" cy="32" r="32" fill="currentColor" opacity="0.14"/><circle cx="32" cy="24" r="10" fill="currentColor" opacity="0.65"/><path d="M12 54c4-11 12-16 20-16s16 5 20 16" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" opacity="0.65"/></svg>';

		return '<span class="avatar avatar-' . esc_attr( (string) $size ) . ' photo musicwave-avatar musicwave-avatar--default" style="width:' . esc_attr( (string) $size ) . 'px;height:' . esc_attr( (string) $size ) . 'px" role="img" aria-label="' . esc_attr( $label ) . '">' . $svg . '</span>';
	}

	/**
	 * Resolve a user ID from any `get_avatar` identifier.
	 *
	 * @param mixed $id_or_email Identifier passed to `get_avatar`.
	 */
	private function resolve_user_id( $id_or_email ): int {
		if ( is_numeric( $id_or_email ) ) {
			return absint( $id_or_email );
		}
		if ( $id_or_email instanceof \WP_User ) {
			return (int) $id_or_email->ID;
		}
		if ( $id_or_email instanceof \WP_Comment ) {
			if ( (int) $id_or_email->user_id > 0 ) {
				return (int) $id_or_email->user_id;
			}
			$user = get_user_by( 'email', (string) $id_or_email->comment_author_email );
			return $user instanceof \WP_User ? (int) $user->ID : 0;
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			return $user instanceof \WP_User ? (int) $user->ID : 0;
		}

		return 0;
	}

	/**
	 * Display name for avatar alt fallback.
	 *
	 * @param int $user_id User ID.
	 */
	private function user_display_name( int $user_id ): string {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && '' !== (string) $user->display_name ? (string) $user->display_name : __( 'تصویر پروفایل کاربر', 'music-wave-core' );
	}
}
