<?php
/**
 * Notification preference surface.
 *
 * Renders the account panel that lets a listener turn each notification channel
 * on or off, and handles the plain `admin-post.php` submission so preferences
 * are manageable without JavaScript. Everything defaults to off; saving is the
 * only way a channel ever becomes active
 * (PROJECT_PLAN.md Stage 6 deliverable 2).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Notifications;

final class NotificationSettings {
	public const ACTION     = 'mw_notifications';
	public const NONCE      = 'mw_notifications_save';
	public const NOTICE_ARG = 'mw-notifications-notice';

	/** @var NotificationPreferences */
	private $preferences;

	public function __construct( NotificationPreferences $preferences ) {
		$this->preferences = $preferences;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_filter( 'music_wave_dashboard_panels', array( $this, 'add_dashboard_panel' ), 20, 2 );
	}

	/**
	 * Add the notifications panel to the single account dashboard shell.
	 *
	 * @param mixed $panels  Registered dashboard panels.
	 * @param int   $user_id Dashboard owner.
	 * @return array<string, array<string, string>>
	 */
	public function add_dashboard_panel( $panels, $user_id = 0 ): array {
		$panels = is_array( $panels ) ? $panels : array();
		if ( (int) $user_id < 1 ) {
			return $panels;
		}

		$panels['notifications'] = array(
			'icon'        => '✉',
			'label'       => __( 'اطلاعیه‌ها', 'music-wave-core' ),
			'description' => __( 'ایمیل‌های انتشاری را که می‌خواهید انتخاب کنید.', 'music-wave-core' ),
			'content'     => $this->markup( (int) $user_id ),
		);

		return $panels;
	}

	/**
	 * Server-rendered preference form.
	 */
	public function markup( int $user_id ): string {
		if ( $user_id < 1 ) {
			return '<p>' . esc_html__( 'برای مدیریت اعلان‌ها وارد سیستم شوید.', 'music-wave-core' ) . '</p>';
		}

		$preferences = $this->preferences->all( $user_id );
		$fields      = '';
		foreach ( $this->preferences->channels() as $channel ) {
			$field_id = 'mw-notify-' . $channel;
			$fields  .= '<label class="mw-notifications__option" for="' . esc_attr( $field_id ) . '">'
				. '<input id="' . esc_attr( $field_id ) . '" type="checkbox" name="mw_channels[]" value="' . esc_attr( $channel ) . '"'
				. ( ! empty( $preferences[ $channel ] ) ? ' checked="checked"' : '' ) . '>'
				. '<span>' . esc_html( $this->preferences->label( $channel ) ) . '</span>'
				. '</label>';
		}

		return '<div class="mw-notifications">'
			. $this->notice_markup()
			. '<form class="mw-notifications__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. wp_nonce_field( self::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<fieldset><legend>' . esc_html__( 'درباره', 'music-wave-core' ) . '</legend>' . $fields . '</fieldset>'
			. '<p class="mw-notifications__hint">' . esc_html__( 'تا زمانی که آن‌ها را روشن نکنید، همه اعلان‌ها خاموش هستند. هر ایمیل شامل یک پیوند لغو اشتراک با یک کلیک است.', 'music-wave-core' ) . '</p>'
			. '<button type="submit">' . esc_html__( 'تنظیمات اعلان را ذخیره کنید', 'music-wave-core' ) . '</button>'
			. '</form></div>';
	}

	/**
	 * Handle the preference form submission.
	 *
	 * @return void
	 */
	public function handle(): void {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			if ( function_exists( 'wp_safe_redirect' ) ) {
				wp_safe_redirect( wp_login_url( (string) wp_get_referer() ), 302 );
			}
			$this->finish();

			return;
		}

		check_admin_referer( self::NONCE );

		$submitted = isset( $_POST['mw_channels'] ) && is_array( $_POST['mw_channels'] ) ? wp_unslash( $_POST['mw_channels'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$saved     = $this->save( $user_id, $submitted );

		$target = (string) wp_get_referer();
		$target = '' !== $target ? $target : home_url( '/' );
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( add_query_arg( array( self::NOTICE_ARG => $saved ? 'saved' : 'failed' ), $target ), 303 );
		}

		$this->finish();
	}

	/**
	 * Apply one submitted channel list. Exposed for tests: no superglobals.
	 *
	 * @param array<int, mixed> $channels Submitted channel keys.
	 */
	public function save( int $user_id, array $channels ): bool {
		$preferences = array();
		foreach ( $channels as $channel ) {
			$key = is_scalar( $channel ) ? sanitize_key( (string) $channel ) : '';
			if ( in_array( $key, $this->preferences->channels(), true ) ) {
				$preferences[ $key ] = true;
			}
		}

		return $this->preferences->save( $user_id, $preferences );
	}

	/**
	 * Post/redirect/get notice rendered in a polite live region.
	 */
	private function notice_markup(): string {
		// Read-only presentation of a redirect marker.
		$notice = isset( $_GET[ self::NOTICE_ARG ] ) && is_scalar( $_GET[ self::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::NOTICE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'saved' !== $notice && 'failed' !== $notice ) {
			return '';
		}

		$message = 'saved' === $notice
			? __( 'تنظیمات اعلان ذخیره شد.', 'music-wave-core' )
			: __( 'تنظیمات اعلان ذخیره نشد.', 'music-wave-core' );
		$class   = 'mw-notifications__notice' . ( 'failed' === $notice ? ' mw-notifications__notice--error' : '' );

		return '<p class="' . esc_attr( $class ) . '" role="status" aria-live="polite">' . esc_html( $message ) . '</p>';
	}

	/**
	 * @return void
	 */
	private function finish(): void {
		if ( ! defined( 'MUSIC_WAVE_TESTING' ) ) {
			exit;
		}
	}
}
