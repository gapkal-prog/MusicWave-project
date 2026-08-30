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
					'play'         => __( 'Play preview', 'music-wave-core' ),
					'pause'        => __( 'Pause preview', 'music-wave-core' ),
					'previous'     => __( 'Previous preview', 'music-wave-core' ),
					'next'         => __( 'Next preview', 'music-wave-core' ),
					'close'        => __( 'Close player', 'music-wave-core' ),
					'queue'        => __( 'Preview queue', 'music-wave-core' ),
					'queueHeading' => __( 'Up next', 'music-wave-core' ),
					'previewBadge' => __( 'Preview', 'music-wave-core' ),
					'loading'      => __( 'Loading…', 'music-wave-core' ),
					'error'        => __( 'Playback could not be started.', 'music-wave-core' ),
					'streamError'  => __( 'Secure playback could not be started.', 'music-wave-core' ),
					'sessionError' => __( 'Your session has expired. Refresh the page or sign in again.', 'music-wave-core' ),
					'volume'       => __( 'Volume', 'music-wave-core' ),
					'closeNotice'  => __( 'Dismiss notice', 'music-wave-core' ),
					/* translators: %s: release title. */
					'playRelease'  => __( 'Play %s', 'music-wave-core' ),
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
		$label = sprintf( __( 'Play %s', 'music-wave-core' ), get_the_title( $release_id ) );

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
		$style     = in_array( $style, array( 'solid', 'outline', 'ghost' ), true ) ? $style : 'solid';
		$variation = BlockSupport::style_variation( $attributes, array( 'outline', 'ghost' ) );
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
		$style   = in_array( $style, array( 'solid', 'outline', 'ghost' ), true ) ? $style : 'solid';
		$class   = 'mw-preview-button'
			. ( $compact ? ' mw-preview-button--compact' : '' )
			. ( 'solid' !== $style ? ' mw-preview-button--' . $style : '' )
			. ( $full_width ? ' mw-preview-button--block' : '' );
		$label   = '' !== $custom_label ? $custom_label : ( $compact ? __( 'Preview', 'music-wave-core' ) : __( 'Play preview', 'music-wave-core' ) );
		$icon    = $show_icon ? '<span class="mw-preview-button__icon" aria-hidden="true">▶</span>' : '';
		$wrapper = $is_block_root ? BlockSupport::wrapper_attributes( $class ) : 'class="' . esc_attr( $class ) . '"';

		return '<button ' . $wrapper . ' type="button" aria-pressed="false" data-preview-url="' . esc_url( $url ) . '" data-preview-title="' . esc_attr( $title ) . '" data-preview-artist="' . esc_attr( $artist ) . '" data-preview-image="' . esc_url( is_string( $image ) ? $image : '' ) . '" data-preview-link="' . esc_url( is_string( $link ) ? $link : '' ) . '" data-preview-limit="' . esc_attr( (string) $limit ) . '">' . $icon . '<span>' . esc_html( $label ) . '</span></button>';
	}

	/**
	 * Render the persistent player; it stays hidden until a preview starts.
	 *
	 * @return void
	 */
	public function render_global_player(): void {
		if ( is_admin() ) {
			return;
		}

		$queue  = '<div class="mw-global-player__queue" data-mw-queue hidden><div class="mw-global-player__queue-header"><h3>' . esc_html__( 'Up next', 'music-wave-core' ) . '</h3><button type="button" class="mw-global-player__queue-close" aria-label="' . esc_attr__( 'Close queue', 'music-wave-core' ) . '">×</button></div><ol class="mw-global-player__queue-list"></ol></div>';
		$notice = '<div class="mw-global-player__notice" role="status" hidden><span class="mw-global-player__notice-message"></span><a class="mw-global-player__notice-cta wp-element-button" href="#" hidden></a><a class="mw-global-player__notice-login" href="#" hidden></a><button type="button" class="mw-global-player__notice-close" aria-label="' . esc_attr__( 'Dismiss notice', 'music-wave-core' ) . '">×</button></div>';

		echo '<aside class="mw-global-player" data-mw-preview-player hidden aria-label="' . esc_attr__( 'Music preview player', 'music-wave-core' ) . '">' . $queue . $notice . '<audio preload="metadata"></audio><div class="mw-global-player__track"><img class="mw-global-player__art" alt="" hidden><div><strong class="mw-global-player__title"></strong><span class="mw-global-player__artist"></span></div></div><div class="mw-global-player__controls"><button type="button" class="mw-global-player__previous" aria-label="' . esc_attr__( 'Previous preview', 'music-wave-core' ) . '">⏮</button><button type="button" class="mw-global-player__toggle" aria-label="' . esc_attr__( 'Play preview', 'music-wave-core' ) . '">▶</button><button type="button" class="mw-global-player__next" aria-label="' . esc_attr__( 'Next preview', 'music-wave-core' ) . '">⏭</button><button type="button" class="mw-global-player__queue-toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Preview queue', 'music-wave-core' ) . '" hidden>♫</button></div><label class="mw-global-player__progress"><span class="screen-reader-text">' . esc_html__( 'Preview progress', 'music-wave-core' ) . '</span><input type="range" min="0" max="100" value="0" step="0.1"></label><span class="mw-global-player__time">0:00</span><input type="range" class="mw-global-player__volume" min="0" max="100" value="100" step="1" aria-label="' . esc_attr__( 'Volume', 'music-wave-core' ) . '"><button type="button" class="mw-global-player__close" aria-label="' . esc_attr__( 'Close player', 'music-wave-core' ) . '">×</button></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $queue and $notice are assembled from fully escaped fragments above.
	}
}
