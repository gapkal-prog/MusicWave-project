<?php
/**
 * Release lyrics panel: real plain + synced (LRC) lyrics from release meta.
 *
 * Lyrics render only for public-readable releases and only when the editor
 * actually filled mw_lyrics / mw_lyrics_synced. No decorative placeholder
 * lyrics are ever invented.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class LyricsBlock {
	/** @var AccessPolicyEngine */
	private $policy;

	/** @var ReleaseRepository */
	private $repository;

	/** @var object|null */
	private $render_context = null;

	public function __construct( AccessPolicyEngine $policy, ReleaseRepository $repository ) {
		$this->policy     = $policy;
		$this->repository = $repository;
	}

	/**
	 * Register the dynamic lyrics block and its frontend assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/lyrics-panel',
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
	 * Enqueue the synced-lyrics controller only when the block is present.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! has_block( 'music-wave/lyrics-panel' ) && ! is_singular( ReleasePostType::KEY ) ) {
			return;
		}
		wp_enqueue_script(
			'music-wave-lyrics',
			MUSIC_WAVE_CORE_URL . 'assets/lyrics.js',
			array(),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'music-wave-lyrics', 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
		wp_enqueue_style(
			'music-wave-lyrics',
			MUSIC_WAVE_CORE_URL . 'assets/lyrics.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Render the lyrics panel for one release.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		if ( ! $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed() ) {
			return '';
		}

		$plain  = $this->repository->get( $release_id, 'mw_lyrics' );
		$synced = $this->repository->get( $release_id, 'mw_lyrics_synced' );
		$plain  = is_string( $plain ) ? trim( $plain ) : '';
		$synced = is_string( $synced ) ? trim( $synced ) : '';

		if ( '' === $plain && '' === $synced ) {
			$empty = BlockSupport::text_attribute( $attributes, 'emptyMessage', __( 'متن ترانه به‌زودی منتشر می‌شود.', 'music-wave-core' ) );
			if ( ! BlockSupport::bool_attribute( $attributes, 'showEmpty', false ) ) {
				return '';
			}
			return '<section ' . BlockSupport::wrapper_attributes( 'mw-lyrics mw-lyrics--empty' ) . ' aria-label="' . esc_attr__( 'متن ترانه', 'music-wave-core' ) . '"><p class="mw-lyrics__empty">' . esc_html( $empty ) . '</p></section>';
		}

		$heading      = BlockSupport::text_attribute( $attributes, 'heading', __( 'متن ترانه', 'music-wave-core' ) );
		$show_heading = BlockSupport::bool_attribute( $attributes, 'showHeading', true );
		$show_synced  = BlockSupport::bool_attribute( $attributes, 'showSynced', true );
		$show_plain   = BlockSupport::bool_attribute( $attributes, 'showPlain', true );
		$max_lines    = BlockSupport::range_attribute( $attributes, 'maxLines', 0, 500, 0 );

		$variation = BlockSupport::style_variation( $attributes, array( 'card', 'minimal', 'cinematic' ) );
		$classes   = 'mw-lyrics' . ( '' !== $variation ? ' mw-lyrics--' . $variation : '' );

		$heading_html = $show_heading ? '<h2 class="mw-lyrics__heading">' . esc_html( $heading ) . '</h2>' : '';

		$body = '';
		if ( '' !== $synced && $show_synced ) {
			$body .= $this->synced_markup( $synced, $max_lines );
		}
		if ( '' !== $plain && $show_plain && '' === $body ) {
			$body .= $this->plain_markup( $plain, $max_lines );
		} elseif ( '' !== $plain && $show_plain && '' !== $synced && $show_synced ) {
			// Both exist: synced is primary, plain is the no-JS fallback.
			$body .= '<noscript>' . $this->plain_markup( $plain, $max_lines ) . '</noscript>';
		}

		if ( '' === $body ) {
			return '';
		}

		return '<section ' . BlockSupport::wrapper_attributes( $classes ) . ' aria-label="' . esc_attr( $heading ) . '" data-mw-lyrics>' . $heading_html . $body . '</section>';
	}

	/**
	 * Render plain lyrics with preserved line breaks.
	 *
	 * @param string $plain Plain lyrics text.
	 * @param int    $max_lines 0 = unlimited.
	 */
	private function plain_markup( string $plain, int $max_lines ): string {
		$lines = preg_split( "/\r\n|\r|\n/", $plain );
		$lines = is_array( $lines ) ? $lines : array( $plain );
		if ( $max_lines > 0 ) {
			$lines = array_slice( $lines, 0, $max_lines );
		}
		$items = array();
		foreach ( $lines as $line ) {
			$line    = trim( (string) $line );
			$items[] = '' === $line ? '<span class="mw-lyrics__blank" aria-hidden="true">&nbsp;</span>' : esc_html( $line );
		}

		return '<div class="mw-lyrics__plain" lang="auto">' . implode( '<br>', $items ) . '</div>';
	}

	/**
	 * Render synced LRC lines as time-tagged rows for lyrics.js.
	 *
	 * @param string $lrc LRC source with [mm:ss.xx] prefixes.
	 * @param int    $max_lines 0 = unlimited.
	 */
	private function synced_markup( string $lrc, int $max_lines ): string {
		$raw_lines = preg_split( "/\r\n|\r|\n/", $lrc );
		$raw_lines = is_array( $raw_lines ) ? $raw_lines : array();
		if ( $max_lines > 0 ) {
			$raw_lines = array_slice( $raw_lines, 0, $max_lines );
		}

		$rows = array();
		foreach ( $raw_lines as $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$seconds = $this->lrc_seconds( $raw );
			$text    = (string) preg_replace( '/^\s*(\[[^\]]+\])+\s*/', '', $raw );
			$text    = trim( wp_strip_all_tags( $text ) );
			if ( '' === $text ) {
				continue;
			}
			if ( null === $seconds ) {
				$rows[] = '<p class="mw-lyrics__line" data-mw-lyrics-time="">' . esc_html( $text ) . '</p>';
				continue;
			}
			$rows[] = '<p class="mw-lyrics__line" data-mw-lyrics-time="' . esc_attr( (string) $seconds ) . '">' . esc_html( $text ) . '</p>';
		}

		if ( empty( $rows ) ) {
			return '';
		}

		return '<div class="mw-lyrics__synced" data-mw-lyrics-synced role="log" aria-live="off" aria-label="' . esc_attr__( 'متن همگام ترانه', 'music-wave-core' ) . '">' . implode( '', $rows ) . '</div>';
	}

	/**
	 * Parse the first LRC timestamp in a line to seconds.
	 *
	 * @param string $line Raw LRC line.
	 */
	private function lrc_seconds( string $line ): ?float {
		if ( 1 !== preg_match( '/\[(\d{1,3}):(\d{1,2})(?:[.:](\d{1,3}))?\]/', $line, $matches ) ) {
			return null;
		}
		$minutes = absint( $matches[1] );
		$seconds = absint( $matches[2] );
		$frac    = isset( $matches[3] ) ? (string) $matches[3] : '0';
		$divisor = strlen( $frac ) >= 3 ? 1000.0 : ( strlen( $frac ) === 2 ? 100.0 : 10.0 );

		return (float) $minutes * 60.0 + (float) $seconds + ( (float) absint( $frac ) / $divisor );
	}

	/**
	 * Resolve the release the panel acts on.
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

		return 0;
	}
}
