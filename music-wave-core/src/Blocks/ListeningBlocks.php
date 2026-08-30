<?php
/**
 * Continue-listening block: consent-gated recently played surface.
 *
 * Three viewer states render server-side with zero JavaScript required:
 * guests see a sign-in prompt, opted-out listeners see a one-click consent
 * panel, and consenting listeners see their recent releases rendered with
 * the shared MusicWave release-shelf card system so the rail matches every
 * other shelf on the site.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Listening\ListeningRepository;

final class ListeningBlocks {
	/** @var ListeningRepository */
	private $repository;

	public function __construct( ListeningRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register the block and its progressive-enhancement assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/continue-listening',
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
	 * Enqueue the tiny consent handler for signed-in visitors.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || get_current_user_id() < 1 ) {
			return;
		}

		wp_enqueue_script(
			'music-wave-listening',
			MUSIC_WAVE_CORE_URL . 'assets/listening.js',
			array( 'wp-api-fetch' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			'music-wave-listening',
			'musicWaveListening',
			array(
				'restUrl'   => esc_url_raw( rest_url() ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'labels'    => array(
					'saving'  => __( 'Enabling listening history…', 'music-wave-core' ),
					'enabled' => __( 'Listening history is on. Play something to fill this rail.', 'music-wave-core' ),
					'error'   => __( 'Listening history could not be enabled. Try again.', 'music-wave-core' ),
				),
			)
		);
	}

	/**
	 * Render the continue-listening surface for the current viewer.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return $this->render_guest( $attributes );
		}

		if ( ! $this->repository->has_consent( $user_id ) ) {
			return $this->render_consent( $attributes );
		}

		return $this->render_history( $attributes, $user_id );
	}

	/**
	 * Guest state: a compact sign-in prompt keeps the layout reserved.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_guest( array $attributes ): string {
		$message = BlockSupport::text_attribute( $attributes, 'guestMessage', __( 'Sign in to keep track of what you play and pick up right where you left off.', 'music-wave-core' ) );

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-continue-listening mw-continue-listening--guest' ) . '>'
			. '<div class="mw-continue-listening__panel"><span class="mw-continue-listening__panel-icon" aria-hidden="true">▶</span>'
			. '<p class="mw-continue-listening__panel-text">' . esc_html( $message ) . '</p>'
			. '<a class="wp-element-button mw-continue-listening__panel-cta" href="' . esc_url( wp_login_url( BlockSupport::current_url() ) ) . '">' . esc_html__( 'Sign in', 'music-wave-core' ) . '</a></div>'
			. '</section>';
	}

	/**
	 * Opt-in state: explain the feature and offer one-click consent.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_consent( array $attributes ): string {
		$message = BlockSupport::text_attribute( $attributes, 'consentMessage', __( 'Turn on listening history to see your recently played releases here. You stay in control — history is private and can be erased anytime.', 'music-wave-core' ) );
		$label   = BlockSupport::text_attribute( $attributes, 'consentButtonLabel', __( 'Turn on listening history', 'music-wave-core' ) );

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-continue-listening mw-continue-listening--consent' ) . '>'
			. '<div class="mw-continue-listening__panel"><span class="mw-continue-listening__panel-icon" aria-hidden="true">◎</span>'
			. '<p class="mw-continue-listening__panel-text">' . esc_html( $message ) . '</p>'
			. '<button type="button" class="wp-element-button mw-continue-listening__panel-cta" data-mw-listening-consent>' . esc_html( $label ) . '</button>'
			. '<p class="mw-continue-listening__status" role="status" aria-live="polite" data-mw-listening-status hidden></p></div>'
			. '</section>';
	}

	/**
	 * History state: header plus a release-shelf rail of recent items.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int                  $user_id    Consenting viewer.
	 */
	private function render_history( array $attributes, int $user_id ): string {
		$heading = BlockSupport::text_attribute( $attributes, 'heading', $this->default_heading( $attributes ) );
		$intro   = isset( $attributes['intro'] ) && is_scalar( $attributes['intro'] ) ? sanitize_text_field( (string) $attributes['intro'] ) : '';
		$event   = 'played' === BlockSupport::key_attribute( $attributes, 'source', array( 'continue', 'played' ), 'continue' )
			? ListeningRepository::EVENT_PLAYED
			: ListeningRepository::EVENT_PROGRESS;

		$items = $this->repository->recent( $user_id, $event, BlockSupport::range_attribute( $attributes, 'itemsToShow', 2, 24, 8 ) );

		if ( array() === $items ) {
			$empty = BlockSupport::text_attribute( $attributes, 'emptyMessage', $this->default_empty_message( $attributes ) );

			return '<section ' . BlockSupport::wrapper_attributes( 'mw-continue-listening mw-continue-listening--empty' ) . '>'
				. '<p class="mw-continue-listening__empty">' . esc_html( $empty ) . '</p>'
				. '</section>';
		}

		$options = array(
			'layout'       => BlockSupport::key_attribute( $attributes, 'layout', array( 'grid', 'scroll', 'list' ), 'scroll' ),
			'columns'      => BlockSupport::range_attribute( $attributes, 'columns', 2, 6, 4 ),
			'shape'        => BlockSupport::key_attribute( $attributes, 'imageShape', array( 'square', 'circle', 'landscape', 'portrait' ), 'square' ),
			'show_artwork' => ! isset( $attributes['showArtwork'] ) || false !== $attributes['showArtwork'],
			'show_artist'  => ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'],
			'show_when'    => ! isset( $attributes['showWhen'] ) || false !== $attributes['showWhen'],
			'show_preview' => ! isset( $attributes['showPreview'] ) || false !== $attributes['showPreview'],
		);

		$cards = array();
		foreach ( $items as $item ) {
			$card = $this->history_card( (int) $item['release_id'], (int) $item['updated_at'], $options );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		if ( array() === $cards ) {
			return '';
		}

		$header = '';
		if ( ! isset( $attributes['showHeading'] ) || false !== $attributes['showHeading'] ) {
			$section_url = isset( $attributes['sectionUrl'] ) && is_scalar( $attributes['sectionUrl'] ) ? esc_url_raw( (string) $attributes['sectionUrl'] ) : '';
			$more        = '';
			if ( '' !== $section_url ) {
				$label = BlockSupport::text_attribute( $attributes, 'sectionLinkLabel', __( 'See all', 'music-wave-core' ) );
				$more  = '<a class="mw-continue-listening__more" href="' . esc_url( $section_url ) . '">' . esc_html( $label ) . '<span aria-hidden="true">&rarr;</span></a>';
			}

			$header = '<header class="mw-continue-listening__header"><div><h2>' . esc_html( $heading ) . '</h2>'
				. ( '' !== $intro ? '<p class="mw-continue-listening__intro">' . esc_html( $intro ) . '</p>' : '' )
				. '</div>' . $more . '</header>';
		}

		// The items wrapper reuses the theme's release-shelf chrome so grid,
		// scroll, and list rails match every other shelf on the site.
		$shelf_class = 'mw-release-shelf mw-release-shelf--' . $options['layout'];
		if ( 'grid' === $options['layout'] ) {
			$shelf_class .= ' mw-release-shelf--columns-' . $options['columns'];
		}

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-continue-listening' ) . '>'
			. $header
			. '<div class="' . esc_attr( $shelf_class ) . '"><div class="mw-release-shelf__items">' . implode( '', $cards ) . '</div></div>'
			. '</section>';
	}

	/**
	 * One history card using the shared release-shelf markup, extended with
	 * the "played ago" meta line.
	 *
	 * @param int                  $release_id Readable release.
	 * @param int                  $updated_at Last activity timestamp.
	 * @param array<string, mixed> $options    Resolved display options.
	 */
	private function history_card( int $release_id, int $updated_at, array $options ): string {
		$title = get_the_title( $release_id );
		$link  = get_permalink( $release_id );
		if ( $release_id < 1 || ! is_string( $link ) || '' === $link ) {
			return '';
		}
		$title = '' !== $title ? $title : __( 'Untitled release', 'music-wave-core' );

		$art = '';
		if ( $options['show_artwork'] ) {
			$image   = get_the_post_thumbnail(
				$release_id,
				'medium_large',
				array(
					'class'         => 'mw-release-shelf__image',
					'alt'           => '',
					'loading'       => 'lazy',
					'fetchpriority' => 'low',
					'decoding'      => 'async',
				)
			);
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			/* translators: %s: music release title. */
			$open_label = sprintf( __( 'Open %s', 'music-wave-core' ), $title );
			$overlay    = '';
			if ( $options['show_preview'] ) {
				$filtered = apply_filters( 'music_wave_card_play_button', '', $release_id, 'mw-release-shelf__play' );
				$overlay  = is_string( $filtered ) ? $filtered : '';
			}
			$art = '<div class="mw-release-shelf__artwrap"><a class="mw-release-shelf__art mw-release-shelf__art--' . esc_attr( (string) $options['shape'] ) . '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">' . ( '' !== $image ? $image : '<span class="mw-release-shelf__placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>' ) . '</a>' . $overlay . '</div>';
		}

		$artist = '';
		if ( $options['show_artist'] ) {
			$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
			if ( is_array( $artists ) && ! empty( $artists ) ) {
				$artist = '<span class="mw-release-shelf__artist">' . esc_html( implode( ', ', $artists ) ) . '</span>';
			}
		}

		$when = '';
		if ( $options['show_when'] && $updated_at > 0 ) {
			$when = '<time datetime="' . esc_attr( gmdate( 'c', $updated_at ) ) . '">' . esc_html( $this->human_time_diff( $updated_at ) ) . '</time>';
		}

		return '<article class="mw-release-shelf__item mw-continue-listening__item">' . $art
			. '<div class="mw-release-shelf__body"><h3><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $artist . $when . '</div>'
			. '</article>';
	}

	/**
	 * State-aware translated heading.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function default_heading( array $attributes ): string {
		return 'played' === BlockSupport::key_attribute( $attributes, 'source', array( 'continue', 'played' ), 'continue' )
			? __( 'Recently played', 'music-wave-core' )
			: __( 'Continue listening', 'music-wave-core' );
	}

	/**
	 * State-aware translated empty message.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function default_empty_message( array $attributes ): string {
		return 'played' === BlockSupport::key_attribute( $attributes, 'source', array( 'continue', 'played' ), 'continue' )
			? __( 'Nothing played yet. Press play on any release and it shows up here.', 'music-wave-core' )
			: __( 'Nothing in progress. Start any release and resume it here later.', 'music-wave-core' );
	}

	/**
	 * Compact relative time for the played-ago meta line.
	 */
	private function human_time_diff( int $timestamp ): string {
		$diff = time() - $timestamp;
		if ( $diff < 60 ) {
			return __( 'Just now', 'music-wave-core' );
		}
		if ( $diff < HOUR_IN_SECONDS ) {
			return sprintf( /* translators: %d: minutes. */ _n( '%d minute ago', '%d minutes ago', (int) floor( $diff / 60 ), 'music-wave-core' ), (int) floor( $diff / 60 ) );
		}
		if ( $diff < DAY_IN_SECONDS ) {
			return sprintf( /* translators: %d: hours. */ _n( '%d hour ago', '%d hours ago', (int) floor( $diff / 3600 ), 'music-wave-core' ), (int) floor( $diff / 3600 ) );
		}
		if ( $diff < 30 * DAY_IN_SECONDS ) {
			return sprintf( /* translators: %d: days. */ _n( '%d day ago', '%d days ago', (int) floor( $diff / 86400 ), 'music-wave-core' ), (int) floor( $diff / 86400 ) );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}
