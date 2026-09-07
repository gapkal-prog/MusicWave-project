<?php
/**
 * Shuffle (random play) button for the release action bar.
 *
 * Delegates to the global preview queue player, which already resolves collection
 * children via /playback-queue. Shuffle is applied client-side before playback.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;

final class ShuffleButtonBlock {
	/** @var object|null */
	private $render_context = null;

	/**
	 * Register the dynamic shuffle block and its frontend assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/shuffle-button',
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
	 * Ensure the release-actions controller is available for shuffle.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! has_block( 'music-wave/shuffle-button' ) && ! is_singular( ReleasePostType::KEY ) ) {
			return;
		}

		$handle = 'music-wave-release-actions';
		if ( ! wp_script_is( $handle, 'enqueued' ) && ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, MUSIC_WAVE_CORE_URL . 'assets/release-actions.js', array(), MUSIC_WAVE_CORE_VERSION, true );
		}
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			wp_enqueue_script( $handle, MUSIC_WAVE_CORE_URL . 'assets/release-actions.js', array(), MUSIC_WAVE_CORE_VERSION, true );
		}

		$data = wp_scripts()->get_data( $handle, 'data' );
		if ( false === $data || false === strpos( (string) $data, 'musicWaveReleaseActions' ) ) {
			wp_localize_script(
				$handle,
				'musicWaveReleaseActions',
				array(
					'copied'      => __( 'پیوند کپی شد', 'music-wave-core' ),
					'copyFailed'  => __( 'کپی انجام نشد. پیوند را به صورت دستی کپی کنید.', 'music-wave-core' ),
					'shareFailed' => __( 'اشتراک‌گذاری انجام نشد.', 'music-wave-core' ),
				)
			);
		}
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
	}

	/**
	 * Render the shuffle trigger for one release.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$label = isset( $attributes['label'] ) && is_scalar( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
		$label = '' !== $label ? $label : __( 'پخش تصادفی', 'music-wave-core' );
		/* translators: %s: release title. */
		$aria_label = sprintf( __( 'پخش تصادفی %s', 'music-wave-core' ), get_the_title( $release_id ) );

		$icon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="m18 14 4 4-4 4"></path><path d="m18 2 4 4-4 4"></path><path d="M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22"></path><path d="M2 6h1.972a4 4 0 0 1 3.6 2.2"></path><path d="M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45"></path></svg>';

		return '<div ' . BlockSupport::wrapper_attributes( 'mw-shuffle-button-wrap' ) . '>'
			. '<button type="button" class="mw-shuffle-button" data-mw-shuffle-button data-mw-release-id="' . esc_attr( (string) $release_id ) . '" aria-label="' . esc_attr( $aria_label ) . '">'
			. '<span class="mw-shuffle-button__icon" aria-hidden="true">' . $icon . '</span>'
			. '<span class="mw-shuffle-button__label">' . esc_html( $label ) . '</span>'
			. '</button>'
			. '</div>';
	}

	/**
	 * Resolve the release the shuffle action acts on.
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
