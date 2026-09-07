<?php
/**
 * Release share button: native Web Share API with clipboard fallback.
 *
 * Server output is always a real button with data attributes so the action
 * works consistently across devices without layout shift or JS-required markup.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;

final class ShareButtonBlock {
	/** @var object|null */
	private $render_context = null;

	/**
	 * Register the dynamic share block and its frontend assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/share-button',
			function ( $attributes, $content, $block ): string {
				unset( $content );
				$previous             = $this->render_context;
				$this->render_context = is_object( $block ) ? $block : null;
				try {
					return $this->render( is_array( $attributes ) ? $attributes : array() );
				} finally {
					$this->render_context = $previous;
				}
			},
			array(
				'api_version'  => 3,
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the share controller on public pages only when the block is present.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! has_block( 'music-wave/share-button' ) && ! is_singular( ReleasePostType::KEY ) ) {
			return;
		}

		wp_enqueue_script(
			'music-wave-release-actions',
			MUSIC_WAVE_CORE_URL . 'assets/release-actions.js',
			array(),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			'music-wave-release-actions',
			'musicWaveReleaseActions',
			array(
				'copied'      => __( 'پیوند کپی شد', 'music-wave-core' ),
				'copyFailed'  => __( 'کپی انجام نشد. پیوند را به صورت دستی کپی کنید.', 'music-wave-core' ),
				'shareFailed' => __( 'اشتراک‌گذاری انجام نشد.', 'music-wave-core' ),
			)
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'music-wave-release-actions', 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
	}

	/**
	 * Render the share trigger for one release.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$url = get_permalink( $release_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$title       = get_the_title( $release_id );
		$title       = is_string( $title ) && '' !== $title ? $title : __( 'انتشار', 'music-wave-core' );
		$artists     = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist_text = is_array( $artists ) && ! empty( $artists ) ? implode( '، ', $artists ) : '';
		$share_text  = '' !== $artist_text ? $title . ' — ' . $artist_text : $title;
		$label       = isset( $attributes['label'] ) && is_scalar( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
		$label       = '' !== $label ? $label : __( 'اشتراک‌گذاری', 'music-wave-core' );
		/* translators: %s: release title. */
		$aria_label = sprintf( __( 'اشتراک‌گذاری %s', 'music-wave-core' ), $title );

		$icon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="M8.59 13.51l6.83 3.98"></path><path d="M15.41 6.51l-6.82 3.98"></path></svg>';

		return '<div ' . BlockSupport::wrapper_attributes( 'mw-share-button-wrap' ) . '>'
			. '<button type="button" class="mw-share-button" data-mw-share-button data-mw-share-url="' . esc_attr( $url ) . '" data-mw-share-title="' . esc_attr( $title ) . '" data-mw-share-text="' . esc_attr( $share_text ) . '" aria-label="' . esc_attr( $aria_label ) . '">'
			. '<span class="mw-share-button__icon" aria-hidden="true">' . $icon . '</span>'
			. '<span class="mw-share-button__label">' . esc_html( $label ) . '</span>'
			. '</button>'
			. '<span class="mw-share-button__status" role="status" aria-live="polite" hidden data-mw-share-status></span>'
			. '</div>';
	}

	/**
	 * Resolve the release the button acts on.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function release_id( array $attributes ): int {
		$explicit = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if ( $explicit > 0 && ReleasePostType::KEY === get_post_type( $explicit ) ) {
			return $explicit;
		}

		$context = $this->render_context;
		if ( is_object( $context ) && isset( $context->context ) && is_array( $context->context ) ) {
			$post_type = isset( $context->context['postType'] ) ? (string) $context->context['postType'] : '';
			$post_id   = isset( $context->context['postId'] ) ? absint( $context->context['postId'] ) : 0;
			if ( ReleasePostType::KEY === $post_type && $post_id > 0 ) {
				return $post_id;
			}
		}

		$current = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
		if ( $current > 0 && ReleasePostType::KEY === get_post_type( $current ) ) {
			return $current;
		}

		if ( function_exists( 'get_queried_object_id' ) ) {
			$queried = absint( get_queried_object_id() );
			if ( $queried > 0 && ReleasePostType::KEY === get_post_type( $queried ) ) {
				return $queried;
			}
		}

		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$global_id = absint( $GLOBALS['post']->ID );
			if ( $global_id > 0 && ReleasePostType::KEY === get_post_type( $global_id ) ) {
				return $global_id;
			}
		}

		return 0;
	}
}
