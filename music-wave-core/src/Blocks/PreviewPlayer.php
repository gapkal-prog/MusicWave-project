<?php
/**
 * Accessible global queue player for public release previews.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use WP_Post;

final class PreviewPlayer {
	private const STREAMABLE_FORMATS = array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac' );

	/** @var ReleaseRepository */
	private $releases;

	/** @var AccessPolicyEngine|null */
	private $policy;

	public function __construct( ReleaseRepository $releases, ?AccessPolicyEngine $policy = null ) {
		$this->releases = $releases;
		$this->policy   = $policy;
	}

	/**
	 * Register the preview block, public script, and global player markup.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_global_player' ), 5 );
		add_filter( 'music_wave_card_play_button', array( $this, 'card_play_button' ), 10, 3 );
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/preview-button',
			array( $this, 'render_block' ),
			array(
				'api_version'  => 3,
				'attributes'   => array(
					'releaseId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'label'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'compact'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'style'     => array(
						'type'    => 'string',
						'default' => 'solid',
					),
					'showIcon'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'fullWidth' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Load a small standalone player controller on public pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'music-wave-preview-player',
			MUSIC_WAVE_CORE_URL . 'assets/preview-player.js',
			array( 'wp-api-fetch' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			'music-wave-preview-player',
			'musicWavePreviewPlayer',
			array(
				'restUrl'       => esc_url_raw( rest_url() ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'loggedIn'      => get_current_user_id() > 0,
				/**
				 * Persistent navigation is on by default: soft body swaps keep
				 * the global player (and its audio) mounted while visitors
				 * browse, the way Spotify or Apple Music keep their bar.
				 * Commerce, admin, and auth routes are always excluded, and
				 * sites can still opt out here or via the setting.
				 *
				 * @param bool $enabled Whether persistent navigation is active.
				 */
				'persistentNav' => (bool) apply_filters( 'music_wave_persistent_navigation', 'disabled' !== (string) \ManaCore\MusicWave\Core\Support\Settings::get( 'persistent_navigation' ) ),
				'labels'        => array(
					'play'         => __( 'پخش پیش‌نمایش', 'music-wave-core' ),
					'pause'        => __( 'توقف پیش‌نمایش', 'music-wave-core' ),
					'previous'     => __( 'پیش‌نمایش قبلی', 'music-wave-core' ),
					'next'         => __( 'پیش‌نمایش بعدی', 'music-wave-core' ),
					'close'        => __( 'بستن پخش‌کننده', 'music-wave-core' ),
					'queue'        => __( 'صف پیش‌نمایش', 'music-wave-core' ),
					'queueHeading' => __( 'بعدی', 'music-wave-core' ),
					'previewBadge' => __( 'پیش‌نمایش', 'music-wave-core' ),
					'loading'      => __( 'در حال بارگذاری…', 'music-wave-core' ),
					'error'        => __( 'پخش شروع نشد.', 'music-wave-core' ),
					'streamError'  => __( 'پخش امن شروع نشد.', 'music-wave-core' ),
					'sessionError' => __( 'جلسه شما تمام شده است. صفحه را تازه کنید یا دوباره وارد شوید.', 'music-wave-core' ),
					'volume'       => __( 'حجم', 'music-wave-core' ),
					'mute'         => __( 'بی‌صدا', 'music-wave-core' ),
					'unmute'       => __( 'باصدا', 'music-wave-core' ),
					'closeNotice'  => __( 'رد اطلاعیه', 'music-wave-core' ),
					/* translators: %s: release title. */
					'playRelease'  => __( 'پخش %s', 'music-wave-core' ),
				),
			)
		);
	}

	/**
	 * Render the shared artwork play button for release cards.
	 *
	 * Themes and blocks request the button through the
	 * `music_wave_card_play_button` filter so every card surface — slider,
	 * shelf, related rails — shares one accessible trigger wired to the
	 * global queue player.
	 *
	 * @param mixed  $button       Existing markup; anything non-empty is kept.
	 * @param mixed  $release_id   Release behind the card.
	 * @param mixed  $button_class Contextual presentation class.
	 */
	public function card_play_button( $button, $release_id = 0, $button_class = 'mw-release-shelf__play' ): string {
		if ( ! is_string( $button ) || '' !== $button ) {
			return is_string( $button ) ? $button : '';
		}

		$release_id = absint( $release_id );
		if ( $release_id < 1 || ReleasePostType::KEY !== get_post_type( $release_id ) || 'publish' !== get_post_status( $release_id ) ) {
			return '';
		}
		if ( ! $this->release_playable( $release_id ) ) {
			return '';
		}

		$title_class = is_string( $button_class ) && '' !== $button_class ? sanitize_html_class( $button_class ) : 'mw-release-shelf__play';
		/* translators: %s: music release title. */
		$label = sprintf( __( 'پخش %s', 'music-wave-core' ), get_the_title( $release_id ) );

		return '<button type="button" class="' . esc_attr( $title_class ) . ' mw-card-play" data-mw-release-id="' . esc_attr( (string) $release_id ) . '" aria-label="' . esc_attr( $label ) . '" aria-pressed="false"><span class="mw-card-play__icon" aria-hidden="true">&#9654;</span></button>';
	}

	/**
	 * Whether a release can produce any playable audio for the visitor.
	 */
	private function release_playable( int $release_id ): bool {
		if ( $this->has_preview( $release_id ) ) {
			return true;
		}

		if ( null === $this->policy || ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return false;
		}

		$assets = $this->releases->get( $release_id, 'mw_download_assets' );
		if ( ! is_array( $assets ) ) {
			return false;
		}
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || ! isset( $asset['key'], $asset['asset_id'] ) ) {
				continue;
			}
			$format = isset( $asset['format'] ) && is_scalar( $asset['format'] ) ? sanitize_key( strtolower( (string) $asset['format'] ) ) : '';
			if ( in_array( $format, self::STREAMABLE_FORMATS, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a release exposes a usable HTTPS preview URL.
	 */
	private function has_preview( int $release_id ): bool {
		$url = $this->releases->get( $release_id, 'mw_preview_url' );

		return is_string( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
	}

	/**
	 * Render a contextual release-preview button.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_block( array $attributes, string $content = '', $block = null ): string {
		unset( $content );
		$release_id = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if (
			$release_id < 1
			&& is_object( $block )
			&& isset( $block->context )
			&& is_array( $block->context )
			&& isset( $block->context['postId'], $block->context['postType'] )
			&& ReleasePostType::KEY === (string) $block->context['postType']
		) {
			$release_id = absint( $block->context['postId'] );
		}
		if ( $release_id < 1 ) {
			$post       = get_post();
			$release_id = $post instanceof WP_Post && ReleasePostType::KEY === $post->post_type ? $post->ID : 0;
		}

		$compact   = ! empty( $attributes['compact'] );
		$label     = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
		$style     = isset( $attributes['style'] ) && is_scalar( $attributes['style'] ) ? sanitize_key( (string) $attributes['style'] ) : 'solid';
		$style     = in_array( $style, array( 'solid', 'outline', 'ghost', 'glow' ), true ) ? $style : 'solid';
		$variation = BlockSupport::style_variation( $attributes, array( 'outline', 'ghost', 'glow' ) );
		if ( '' !== $variation ) {
			$style = $variation;
		}
		$show_icon  = ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'];
		$full_width = ! empty( $attributes['fullWidth'] );

		return $release_id > 0 ? $this->button_markup( $release_id, $compact, true, $label, $style, $show_icon, $full_width ) : '';
	}

	/**
	 * Return a small or full preview trigger when the release has a public URL.
	 */
	public function button_markup( int $release_id, bool $compact = false, bool $is_block_root = false, string $custom_label = '', string $style = 'solid', bool $show_icon = true, bool $full_width = false ): string {
		if ( ReleasePostType::KEY !== get_post_type( $release_id ) ) {
			return '';
		}

		$url = $this->releases->get( $release_id, 'mw_preview_url' );
		if ( ! is_string( $url ) || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return '';
		}

		$title   = get_the_title( $release_id );
		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist  = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', $artists ) : '';
		$image   = get_the_post_thumbnail_url( $release_id, 'thumbnail' );
		$link    = get_permalink( $release_id );
		$limit   = absint( $this->releases->get( $release_id, 'mw_preview_duration' ) );
		$limit   = $limit >= 10 && $limit <= 120 ? $limit : 30;
		$style   = in_array( $style, array( 'solid', 'outline', 'ghost', 'glow' ), true ) ? $style : 'solid';
		$class   = 'mw-preview-button'
			. ( $compact ? ' mw-preview-button--compact' : '' )
			. ( 'solid' !== $style ? ' mw-preview-button--' . $style : '' )
			. ( $full_width ? ' mw-preview-button--block' : '' );
		$label   = '' !== $custom_label ? $custom_label : ( $compact ? __( 'پیش‌نمایش', 'music-wave-core' ) : __( 'پخش پیش‌نمایش', 'music-wave-core' ) );
		$icon    = $show_icon ? '<span class="mw-preview-button__icon" aria-hidden="true">▶</span>' : '';
		$wrapper = $is_block_root ? BlockSupport::wrapper_attributes( $class ) : 'class="' . esc_attr( $class ) . '"';

		return '<button ' . $wrapper . ' type="button" aria-pressed="false" data-preview-url="' . esc_url( $url ) . '" data-preview-title="' . esc_attr( $title ) . '" data-preview-artist="' . esc_attr( $artist ) . '" data-preview-image="' . esc_url( is_string( $image ) ? $image : '' ) . '" data-preview-link="' . esc_url( is_string( $link ) ? $link : '' ) . '" data-preview-limit="' . esc_attr( (string) $limit ) . '">' . $icon . '<span>' . esc_html( $label ) . '</span></button>';
	}

	/**
	 * Inline SVG icon used by the persistent player controls.
	 *
	 * Icons are decorative (labels live on the buttons) and inherit
	 * `currentColor`, so the theme recolours them with tokens alone.
	 *
	 * @param string $name  Icon key.
	 * @param string $extra_class Extra class names for the <svg> element.
	 * @return string
	 */
	private function icon( string $name, string $extra_class = '' ): string {
		$paths = array(
			'play'       => '<path d="M8 5.14v13.72c0 .79.87 1.27 1.54.84l10.63-6.86a1 1 0 0 0 0-1.68L9.54 4.3C8.87 3.87 8 4.35 8 5.14Z"/>',
			'pause'      => '<path d="M7 5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5Zm6 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V5Z"/>',
			'previous'   => '<path d="M6 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1Zm12.53.21A1 1 0 0 1 20 6.06v11.88a1 1 0 0 1-1.53.85L9.2 12.85a1 1 0 0 1 0-1.7l9.27-5.94a1 1 0 0 1 .06 0Z"/>',
			'next'       => '<path d="M18 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1ZM5.47 5.21a1 1 0 0 1 1.06 0l9.27 5.94a1 1 0 0 1 0 1.7l-9.27 5.94A1 1 0 0 1 5 17.94V6.06a1 1 0 0 1 .47-.85Z"/>',
			'queue'      => '<path d="M4 6a1 1 0 0 1 1-1h14a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Zm0 5a1 1 0 0 1 1-1h14a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Zm1 4a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H5Zm12.5-1a1 1 0 0 1 1 1v1.5H20a1 1 0 1 1 0 2h-1.5V20a1 1 0 1 1-2 0v-1.5H15a1 1 0 1 1 0-2h1.5V15a1 1 0 0 1 1-1Z"/>',
			'close'      => '<path d="M6.22 6.22a1 1 0 0 1 1.41 0L12 10.59l4.36-4.37a1 1 0 1 1 1.42 1.42L13.41 12l4.37 4.36a1 1 0 0 1-1.42 1.42L12 13.41l-4.37 4.37a1 1 0 0 1-1.41-1.42L10.59 12 6.22 7.63a1 1 0 0 1 0-1.41Z"/>',
			'volume'     => '<path d="M4 9.5A1.5 1.5 0 0 1 5.5 8H8l4.35-3.48A1 1 0 0 1 14 5.3v13.4a1 1 0 0 1-1.65.78L8 16H5.5A1.5 1.5 0 0 1 4 14.5v-5Zm12.7-1.03a1 1 0 0 1 1.4.13A5.98 5.98 0 0 1 19.5 12c0 1.3-.42 2.5-1.4 3.4a1 1 0 0 1-1.53-1.27c.62-.52.93-1.28.93-2.13 0-.85-.31-1.6-.93-2.13a1 1 0 0 1 .13-1.4Zm2.7-2.6a1 1 0 0 1 1.4.16A9.96 9.96 0 0 1 22.5 12a9.96 9.96 0 0 1-1.7 5.97 1 1 0 0 1-1.56-1.24A7.96 7.96 0 0 0 20.5 12c0-1.7-.5-3.28-1.26-4.73a1 1 0 0 1 .16-1.4Z"/>',
			'volume-off' => '<path d="M4 9.5A1.5 1.5 0 0 1 5.5 8H8l4.35-3.48A1 1 0 0 1 14 5.3v13.4a1 1 0 0 1-1.65.78L8 16H5.5A1.5 1.5 0 0 1 4 14.5v-5Zm12.3-.2a1 1 0 0 1 1.4 0L19 10.6l1.3-1.3a1 1 0 1 1 1.4 1.4L20.4 12l1.3 1.3a1 1 0 0 1-1.4 1.4L19 13.4l-1.3 1.3a1 1 0 0 1-1.4-1.4l1.3-1.3-1.3-1.3a1 1 0 0 1 0-1.4Z"/>',
			'spinner'    => '<path d="M12 3a1 1 0 0 1 1 1v2.5a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 13.5a1 1 0 0 1 1 1V20a1 1 0 1 1-2 0v-2.5a1 1 0 0 1 1-1ZM3 12a1 1 0 0 1 1-1h2.5a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Zm13.5 0a1 1 0 0 1 1-1H20a1 1 0 1 1 0 2h-2.5a1 1 0 0 1-1-1ZM5.64 5.64a1 1 0 0 1 1.41 0l1.77 1.77a1 1 0 1 1-1.41 1.41L5.64 7.05a1 1 0 0 1 0-1.41Zm9.54 9.54a1 1 0 0 1 1.41 0l1.77 1.77a1 1 0 0 1-1.41 1.41l-1.77-1.77a1 1 0 0 1 0-1.41Zm3.18-9.54a1 1 0 0 1 0 1.41l-1.77 1.77a1 1 0 1 1-1.41-1.41l1.77-1.77a1 1 0 0 1 1.41 0ZM8.82 15.18a1 1 0 0 1 0 1.41l-1.77 1.77a1 1 0 0 1-1.41-1.41l1.77-1.77a1 1 0 0 1 1.41 0Z"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		$classes = trim( 'mw-global-player__icon mw-global-player__icon--' . $name . ' ' . $extra_class );

		return '<svg class="' . esc_attr( $classes ) . '" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Render the persistent player; it stays hidden until a preview starts.
	 *
	 * Class names are a contract with assets/preview-player.js (and the
	 * theme's global-player.css): the script resolves every control by class,
	 * so new elements are added but existing hooks are never renamed.
	 *
	 * @return void
	 */
	public function render_global_player(): void {
		if ( is_admin() ) {
			return;
		}

		$queue = '<div class="mw-global-player__queue" data-mw-queue hidden>'
			. '<div class="mw-global-player__queue-header"><h3>' . esc_html__( 'بعدی', 'music-wave-core' ) . '</h3>'
			. '<button type="button" class="mw-global-player__queue-close" aria-label="' . esc_attr__( 'بستن صف', 'music-wave-core' ) . '">' . $this->icon( 'close' ) . '</button></div>'
			. '<ol class="mw-global-player__queue-list"></ol></div>';

		$notice = '<div class="mw-global-player__notice" role="status" hidden>'
			. '<span class="mw-global-player__notice-message"></span>'
			. '<a class="mw-global-player__notice-cta wp-element-button" href="#" hidden></a>'
			. '<a class="mw-global-player__notice-login" href="#" hidden></a>'
			. '<button type="button" class="mw-global-player__notice-close" aria-label="' . esc_attr__( 'رد اطلاعیه', 'music-wave-core' ) . '">' . $this->icon( 'close' ) . '</button></div>';

		$track = '<div class="mw-global-player__track">'
			. '<img class="mw-global-player__art" alt="" hidden width="48" height="48" decoding="async">'
			. '<span class="mw-global-player__art-fallback" aria-hidden="true">' . $this->icon( 'play' ) . '</span>'
			. '<div class="mw-global-player__meta"><strong class="mw-global-player__title"></strong><span class="mw-global-player__artist"></span></div></div>';

		$controls = '<div class="mw-global-player__controls">'
			. '<button type="button" class="mw-global-player__previous" aria-label="' . esc_attr__( 'پیش‌نمایش قبلی', 'music-wave-core' ) . '">' . $this->icon( 'previous' ) . '</button>'
			. '<button type="button" class="mw-global-player__toggle" aria-label="' . esc_attr__( 'پخش پیش‌نمایش', 'music-wave-core' ) . '">' . $this->icon( 'play' ) . $this->icon( 'pause' ) . $this->icon( 'spinner' ) . '</button>'
			. '<button type="button" class="mw-global-player__next" aria-label="' . esc_attr__( 'پیش‌نمایش بعدی', 'music-wave-core' ) . '">' . $this->icon( 'next' ) . '</button>'
			. '</div>';

		$timeline = '<div class="mw-global-player__timeline">'
			. '<span class="mw-global-player__time mw-global-player__time--current" aria-hidden="true">0:00</span>'
			. '<label class="mw-global-player__progress"><span class="screen-reader-text">' . esc_html__( 'میزان پیشرفت پیش‌نمایش', 'music-wave-core' ) . '</span>'
			. '<input type="range" min="0" max="100" value="0" step="0.1" aria-valuetext="0:00"></label>'
			. '<span class="mw-global-player__duration" aria-hidden="true">0:00</span>'
			. '</div>';

		$tools = '<div class="mw-global-player__tools">'
			. '<button type="button" class="mw-global-player__queue-toggle" aria-expanded="false" aria-label="' . esc_attr__( 'صف پیش‌نمایش', 'music-wave-core' ) . '" hidden>' . $this->icon( 'queue' ) . '</button>'
			. '<div class="mw-global-player__volume-group">'
			. '<button type="button" class="mw-global-player__mute" aria-pressed="false" aria-label="' . esc_attr__( 'بی‌صدا', 'music-wave-core' ) . '">' . $this->icon( 'volume' ) . $this->icon( 'volume-off' ) . '</button>'
			. '<input type="range" class="mw-global-player__volume" min="0" max="100" value="100" step="1" aria-label="' . esc_attr__( 'حجم', 'music-wave-core' ) . '">'
			. '</div>'
			. '<button type="button" class="mw-global-player__close" aria-label="' . esc_attr__( 'بستن پخش‌کننده', 'music-wave-core' ) . '">' . $this->icon( 'close' ) . '</button>'
			. '</div>';

		$markup = '<aside class="mw-global-player" data-mw-preview-player hidden aria-label="' . esc_attr__( 'پخش‌کننده پیش‌نمایش موسیقی', 'music-wave-core' ) . '">'
			. $queue . $notice
			. '<audio preload="metadata"></audio>'
			. '<div class="mw-global-player__bar">' . $track . '<div class="mw-global-player__center">' . $controls . $timeline . '</div>' . $tools . '</div>'
			. '</aside>';

		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Assembled above from escaped fragments and static SVG markup.
	}
}
