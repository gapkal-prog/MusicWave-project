<?php
/**
 * Account chip: avatar photo + display name linking to the account page.
 *
 * Guests see a login button. The avatar renders through `get_avatar`, so the
 * MusicWave profile-image setting (upload / default / off) applies here with
 * zero duplicated logic.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

final class AccountChipBlock {
	/**
	 * Register the dynamic account-chip block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/account-chip',
			function ( $attributes ): string {
				return $this->render( is_array( $attributes ) ? $attributes : array() );
			},
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the chip stylesheet only when the block is present.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! has_block( 'music-wave/account-chip' ) ) {
			return;
		}
		wp_enqueue_style(
			'music-wave-account-chip',
			MUSIC_WAVE_CORE_URL . 'assets/account-chip.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Render the chip for the current visitor.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$show_avatar = BlockSupport::bool_attribute( $attributes, 'showAvatar', true );
		$show_name   = BlockSupport::bool_attribute( $attributes, 'showName', true );
		$avatar_size = BlockSupport::range_attribute( $attributes, 'avatarSize', 24, 96, 40 );

		if ( ! is_user_logged_in() ) {
			$label = BlockSupport::text_attribute( $attributes, 'loginLabel', __( 'ورود', 'music-wave-core' ) );
			$url   = function_exists( 'wp_login_url' ) ? wp_login_url( BlockSupport::current_url() ) : wp_login_url();

			return '<div ' . BlockSupport::wrapper_attributes( 'mw-account-chip-wrap' ) . '><a class="mw-account-chip mw-account-chip--guest" href="' . esc_url( $url ) . '"><span class="mw-account-chip__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="8.5" r="3.75"/><path d="M4.75 20a7.25 7.25 0 0 1 14.5 0"/></svg></span><span class="mw-account-chip__name">' . esc_html( $label ) . '</span></a></div>';
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User || (int) $user->ID < 1 ) {
			return '';
		}
		/**
		 * Account page URL for the chip link.
		 *
		 * @param string $url Default `/account/` URL.
		 */
		$account_url = (string) apply_filters( 'musicwave_account_url', home_url( '/account/' ) );
		$name        = '' !== (string) $user->display_name ? (string) $user->display_name : (string) $user->user_login;

		$avatar = '';
		if ( $show_avatar ) {
			$avatar_img = get_avatar( (int) $user->ID, $avatar_size, '', $name, array( 'class' => 'mw-account-chip__img' ) );
			$avatar     = '<span class="mw-account-chip__avatar">' . $avatar_img . '</span>';
		}
		$name_html = $show_name ? '<span class="mw-account-chip__name">' . esc_html( $name ) . '</span>' : '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';

		/* translators: %s: user display name. */
		$aria = sprintf( __( 'حساب %s', 'music-wave-core' ), $name );

		return '<div ' . BlockSupport::wrapper_attributes( 'mw-account-chip-wrap' ) . '><a class="mw-account-chip" href="' . esc_url( $account_url ) . '" aria-label="' . esc_attr( $aria ) . '">' . $avatar . $name_html . '</a></div>';
	}
}
