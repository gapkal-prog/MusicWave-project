<?php
/**
 * Public term hero block: archive headers for artists, genres, moods, labels.
 *
 * The landing surface global music platforms put at the top of every artist
 * and category page: a full-width media banner or a compact row carrying the
 * term name, a translated taxonomy eyebrow, description excerpt, release
 * count, and the shared follow control for artists. Resolves the currently
 * queried term automatically and stays silent on non-taxonomy routes so any
 * template can include it safely.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use WP_Term;

final class TermHeroBlock {
	/** Taxonomies this block may present. */
	private const ALLOWED_TAXONOMIES = array( 'mw_artist', 'mw_genre', 'mw_mood', 'mw_label' );

	/**
	 * Register the server-rendered term hero block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/term-hero',
			array( $this, 'render' ),
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the term hero for the queried or pinned term.
	 *
	 * @param array<string, mixed> $attributes Block attributes from the editor.
	 *
	 * @return string Empty string off-editor when no supported term resolves,
	 *                so unrelated archives keep their templates intact.
	 */
	public function render( array $attributes = array() ): string {
		$term = $this->resolve_term( $attributes );
		if ( ! $term instanceof WP_Term ) {
			return $this->is_editor_request() ? $this->render_editor_placeholder( $attributes ) : '';
		}

		$options = $this->resolve_options( $attributes );
		$parts   = array(
			$this->media( $term, $options ),
			'<div class="mw-term-hero__content">' . implode( '', $this->content_parts( $term, $options ) ) . '</div>',
		);

		$classes = array_filter(
			array(
				'mw-term-hero',
				'mw-term-hero--' . $options['layout'],
				'banner' === $options['layout'] ? 'mw-term-hero--size-' . $options['size'] : '',
				'' === $options['accent'] && ! $this->has_media( $term ) ? 'mw-term-hero--hue-' . ( ( absint( $term->term_id ) % 6 ) + 1 ) : '',
				$this->has_media( $term ) && $options['show_image'] ? '' : 'mw-term-hero--no-media',
				'compact' === $options['layout'] ? 'mw-term-hero--shape-' . $options['compact_shape'] : '',
			)
		);

		$style = '' !== $options['accent'] ? ' style="--mw-term-accent:' . esc_attr( $options['accent'] ) . '"' : '';

		return '<section '
			. BlockSupport::wrapper_attributes( implode( ' ', $classes ) )
			. $style
			. '>' . implode( '', $parts ) . '</section>';
	}

	/**
	 * Resolve sanitized display options once per render.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function resolve_options( array $attributes ): array {
		$layout = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'banner';
		$layout = in_array( $layout, array( 'banner', 'compact' ), true ) ? $layout : 'banner';

		$size = isset( $attributes['size'] ) ? sanitize_key( (string) $attributes['size'] ) : 'medium';
		$size = in_array( $size, array( 'short', 'medium', 'tall' ), true ) ? $size : 'medium';

		$compact_shape = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'rounded';
		$compact_shape = in_array( $compact_shape, array( 'rounded', 'square', 'circle' ), true ) ? $compact_shape : 'rounded';

		$description_length = isset( $attributes['descriptionLength'] ) ? absint( $attributes['descriptionLength'] ) : 0;
		$description_length = min( 120, $description_length );

		$accent = isset( $attributes['accentColor'] ) ? sanitize_hex_color( (string) $attributes['accentColor'] ) : '';
		$accent = is_string( $accent ) ? $accent : '';

		return array(
			'layout'             => $layout,
			'size'               => $size,
			'compact_shape'      => $compact_shape,
			'description_length' => $description_length,
			'accent'             => $accent,
			'show_image'         => ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'],
			'show_eyebrow'       => ! isset( $attributes['showEyebrow'] ) || (bool) $attributes['showEyebrow'],
			'show_name'          => ! isset( $attributes['showName'] ) || (bool) $attributes['showName'],
			'show_description'   => ! isset( $attributes['showDescription'] ) || (bool) $attributes['showDescription'],
			'show_count'         => ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'],
			'show_follow'        => ! isset( $attributes['showFollowButton'] ) || (bool) $attributes['showFollowButton'],
		);
	}

	/**
	 * Resolve the hero term from pinned attributes or the current query.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return WP_Term|null
	 */
	private function resolve_term( array $attributes ): ?WP_Term {
		$term_id = isset( $attributes['termId'] ) ? absint( $attributes['termId'] ) : 0;

		if ( $term_id > 0 ) {
			$taxonomy   = isset( $attributes['taxonomy'] ) ? sanitize_key( (string) $attributes['taxonomy'] ) : '';
			$candidates = '' !== $taxonomy && in_array( $taxonomy, self::ALLOWED_TAXONOMIES, true )
				? array( $taxonomy )
				: self::ALLOWED_TAXONOMIES;

			foreach ( $candidates as $candidate ) {
				$term = get_term( $term_id, $candidate );
				if ( $term instanceof WP_Term ) {
					return $term;
				}
			}

			return null;
		}

		$queried = function_exists( 'get_queried_object' ) ? get_queried_object() : null;

		return $queried instanceof WP_Term && in_array( $queried->taxonomy, self::ALLOWED_TAXONOMIES, true ) ? $queried : null;
	}

	/**
	 * Media layer: cover image when available, curated hue otherwise.
	 *
	 * @param WP_Term              $term    Resolved term.
	 * @param array<string, mixed> $options Resolved options.
	 * @return string
	 */
	private function media( WP_Term $term, array $options ): string {
		if ( ! $options['show_image'] ) {
			return '<div class="mw-term-hero__media" aria-hidden="true"></div>';
		}

		$image_id = $this->image_id( $term );
		if ( '' === $image_id ) {
			return '<div class="mw-term-hero__media" aria-hidden="true"></div>';
		}

		return '<div class="mw-term-hero__media">'
			. wp_get_attachment_image(
				absint( $image_id ),
				'full',
				false,
				array(
					'class'    => 'mw-term-hero__image',
					'alt'      => '',
					'loading'  => 'eager',
					'decoding' => 'async',
				)
			)
			. '</div><div class="mw-term-hero__scrim" aria-hidden="true"></div>';
	}

	/**
	 * Whether the term carries usable cover media.
	 */
	private function has_media( WP_Term $term ): bool {
		return '' !== $this->image_id( $term );
	}

	/**
	 * Cover attachment ID for a term (artists carry one today).
	 */
	private function image_id( WP_Term $term ): string {
		if ( 'mw_artist' !== $term->taxonomy ) {
			return '';
		}

		return (string) absint( (string) get_term_meta( $term->term_id, 'mw_artist_image_id', true ) );
	}

	/**
	 * Translated eyebrow label for a taxonomy.
	 *
	 * Class constants cannot hold `__()` results, so translation happens at
	 * the point of use instead of in a lookup table.
	 */
	private function taxonomy_eyebrow( string $taxonomy ): string {
		switch ( $taxonomy ) {
			case 'mw_artist':
				return __( 'Artist', 'music-wave-core' );
			case 'mw_genre':
				return __( 'Genre', 'music-wave-core' );
			case 'mw_mood':
				return __( 'Mood', 'music-wave-core' );
			case 'mw_label':
				return __( 'Label', 'music-wave-core' );
		}

		return __( 'Browse', 'music-wave-core' );
	}

	/**
	 * Content parts: eyebrow, name, count, description, follow.
	 *
	 * @param WP_Term              $term    Resolved term.
	 * @param array<string, mixed> $options Resolved options.
	 * @return array<int, string>
	 */
	private function content_parts( WP_Term $term, array $options ): array {
		$parts = array();

		if ( $options['show_eyebrow'] ) {
			$eyebrow = $this->taxonomy_eyebrow( $term->taxonomy );

			$parts[] = '<p class="mw-term-hero__eyebrow">' . esc_html( $eyebrow ) . '</p>';
		}

		if ( $options['show_name'] ) {
			$name = '' !== $term->name ? $term->name : __( 'Untitled', 'music-wave-core' );

			$parts[] = '<h1 class="mw-term-hero__name">' . esc_html( $name ) . '</h1>';
		}

		if ( $options['show_count'] && (int) $term->count > 0 ) {
			$parts[] = '<p class="mw-term-hero__count">' . esc_html(
				sprintf(
					/* translators: %s: number of published releases. */
					_n( '%s release', '%s releases', (int) $term->count, 'music-wave-core' ),
					number_format_i18n( (int) $term->count )
				)
			) . '</p>';
		}

		$description = $this->description( $term );
		if ( $options['show_description'] && '' !== $description ) {
			if ( $options['description_length'] >= 5 ) {
				$description = wp_trim_words( wp_strip_all_tags( $description ), $options['description_length'] );
				$parts[]     = '<p class="mw-term-hero__description">' . esc_html( $description ) . '</p>';
			} else {
				$parts[] = '<div class="mw-term-hero__description">' . wpautop( wp_kses_post( $description ) ) . '</div>';
			}
		}

		if ( $options['show_follow'] && 'mw_artist' === $term->taxonomy ) {
			$button = LibraryButton::markup(
				LibraryRepository::TYPE_ARTIST,
				(int) $term->term_id,
				array( 'style' => 'outline' )
			);
			if ( '' !== $button ) {
				$parts[] = '<div class="mw-term-hero__follow">' . $button . '</div>';
			}
		}

		return $parts;
	}

	/**
	 * Description source: artist biography meta first, then the term description.
	 */
	private function description( WP_Term $term ): string {
		if ( 'mw_artist' === $term->taxonomy ) {
			$biography = (string) get_term_meta( $term->term_id, 'mw_artist_biography', true );
			if ( '' !== $biography ) {
				return $biography;
			}
		}

		$description = function_exists( 'term_description' ) ? (string) term_description( $term->term_id ) : '';

		return trim( wp_strip_all_tags( $description ) );
	}

	/**
	 * Determine whether the current request comes from a block editor context.
	 */
	private function is_editor_request(): bool {
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor context check.
		return isset( $_GET['context'] ) && 'edit' === sanitize_key( (string) wp_unslash( (string) $_GET['context'] ) );
	}

	/**
	 * Editor-only placeholder so ServerSideRender never shows an error notice.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render_editor_placeholder( array $attributes ): string {
		$label = __( 'Term hero', 'music-wave-core' );
		$help  = isset( $attributes['termId'] ) && absint( $attributes['termId'] ) > 0
			? __( 'The selected term was not found. Check the taxonomy and term ID in the block sidebar.', 'music-wave-core' )
			: __( 'This header renders automatically on artist, genre, mood, and label archives.', 'music-wave-core' );

		return '<div class="mw-term-hero mw-term-hero--placeholder" style="border:1px dashed currentColor;border-radius:12px;padding:2.5rem 1.5rem;text-align:center;opacity:.8;">'
			. '<span class="dashicons dashicons-format-audio" aria-hidden="true" style="font-size:2rem;width:2rem;height:2rem;"></span>'
			. '<p style="margin:.5rem 0 0;"><strong>' . esc_html( $label ) . '</strong></p>'
			. '<p style="margin:.25rem 0 0;font-size:.875em;">' . esc_html( $help ) . '</p>'
			. '</div>';
	}
}
