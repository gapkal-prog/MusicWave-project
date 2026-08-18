<?php
/**
 * Public artist profile block.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use WP_Term;

final class ArtistProfileBlock {
	/**
	 * Register the server-rendered public artist profile block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/artist-profile',
			array( $this, 'render' ),
			array(
				'api_version' => 3,
				'attributes'  => array(
					'termId'            => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'layout'            => array(
						'type'    => 'string',
						'default' => 'card',
					),
					'showImage'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showTitle'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBio'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showLink'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showLibraryButton' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'imageSize'         => array(
						'type'    => 'string',
						'default' => 'medium',
					),
					'imageShape'        => array(
						'type'    => 'string',
						'default' => 'rounded',
					),
					'ctaLabel'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'bioLength'         => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'showReleaseCount'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'accentColor'       => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the artist profile block.
	 *
	 * When `termId` is set to a positive integer the block renders that artist
	 * on any template (not just the taxonomy archive). Otherwise it falls back
	 * to the queried `mw_artist` term on archive pages.
	 *
	 * @param array<string, mixed> $attributes Block attributes from the editor.
	 *
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		$term = $this->resolve_term( $attributes );
		if ( ! $term instanceof WP_Term ) {
			if ( $this->is_editor_request() ) {
				return $this->render_editor_placeholder( $attributes );
			}

			return '';
		}

		$biography           = (string) get_term_meta( $term->term_id, 'mw_artist_biography', true );
		$image_id            = absint( get_term_meta( $term->term_id, 'mw_artist_image_id', true ) );
		$url                 = (string) get_term_meta( $term->term_id, 'mw_artist_canonical_url', true );
		$show_image          = ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'];
		$show_title          = ! isset( $attributes['showTitle'] ) || (bool) $attributes['showTitle'];
		$show_bio            = ! isset( $attributes['showBio'] ) || (bool) $attributes['showBio'];
		$show_link           = ! isset( $attributes['showLink'] ) || (bool) $attributes['showLink'];
		$show_library_button = ! isset( $attributes['showLibraryButton'] ) || (bool) $attributes['showLibraryButton'];
		$show_release_count  = ! empty( $attributes['showReleaseCount'] );
		$bio_length          = isset( $attributes['bioLength'] ) ? absint( $attributes['bioLength'] ) : 0;
		$bio_length          = $bio_length <= 200 ? $bio_length : 200;

		$layout = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'card';
		$layout = in_array( $layout, array( 'card', 'list', 'slider' ), true ) ? $layout : 'card';

		$image_shape = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'rounded';
		$image_shape = in_array( $image_shape, array( 'rounded', 'square', 'circle' ), true ) ? $image_shape : 'rounded';

		$image_size = isset( $attributes['imageSize'] ) ? sanitize_key( (string) $attributes['imageSize'] ) : 'medium';
		$image_size = in_array( $image_size, array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $image_size : 'medium';

		$cta_label = isset( $attributes['ctaLabel'] ) && '' !== (string) $attributes['ctaLabel']
			? sanitize_text_field( (string) $attributes['ctaLabel'] )
			: __( 'Official artist page', 'music-wave-core' );

		$accent = isset( $attributes['accentColor'] ) ? sanitize_hex_color( (string) $attributes['accentColor'] ) : '';
		$accent = is_string( $accent ) ? $accent : '';

		$wrapper_style = '';
		if ( '' !== $accent ) {
			$wrapper_style = '--mw-artist-accent:' . esc_attr( $accent ) . ';';
		}

		$wrapper_classes = 'mw-artist-profile mw-surface'
			. ' mw-artist-profile--' . $layout
			. ' mw-artist-profile--shape-' . $image_shape;

		$image_html = '';
		if ( $show_image && $image_id > 0 ) {
			$image_html = wp_get_attachment_image(
				$image_id,
				$image_size,
				false,
				array(
					'class' => 'mw-artist-profile__image mw-artist-profile__image--' . $image_shape,
					'alt'   => $show_title ? $term->name : '',
				)
			);
		}

		$title_html = '';
		if ( $show_title ) {
			$permalink  = get_term_link( $term );
			$title_html = '<h2 class="mw-artist-profile__name">'
				. ( ! is_wp_error( $permalink ) ? '<a href="' . esc_url( (string) $permalink ) . '">' . esc_html( $term->name ) . '</a>' : esc_html( $term->name ) )
				. '</h2>';
		}

		$bio_html = '';
		if ( $show_bio && '' !== $biography ) {
			if ( $bio_length >= 10 ) {
				$bio_html = '<p class="mw-artist-profile__bio">' . esc_html( wp_trim_words( wp_strip_all_tags( $biography ), $bio_length ) ) . '</p>';
			} else {
				$bio_html = '<div class="mw-artist-profile__bio">' . wpautop( wp_kses_post( $biography ) ) . '</div>';
			}
		}

		$count_html = '';
		if ( $show_release_count ) {
			$count      = $this->release_count( $term->term_id );
			$count_html = $count > 0
				? '<p class="mw-artist-profile__count">' . esc_html(
					sprintf(
						/* translators: %s: number of published releases. */
						_n( '%s release', '%s releases', $count, 'music-wave-core' ),
						number_format_i18n( $count )
					)
				) . '</p>'
				: '';
		}

		$link_html = '';
		if ( $show_link && '' !== $url ) {
			$link_html = '<p class="mw-artist-profile__link"><a href="' . esc_url( $url ) . '" rel="external nofollow noopener" target="_blank">'
				. esc_html( $cta_label )
				. '<span aria-hidden="true">&rarr;</span></a></p>';
		}

		$library_html = '';
		if ( $show_library_button ) {
			$button = LibraryButton::markup( LibraryRepository::TYPE_ARTIST, $term->term_id, array( 'style' => 'outline' ) );
			if ( '' !== $button ) {
				$library_html = '<div class="mw-artist-profile__library">' . $button . '</div>';
			}
		}

		if ( '' === $image_html && '' === $title_html && '' === $bio_html && '' === $link_html && '' === $library_html ) {
			if ( $this->is_editor_request() ) {
				return $this->render_editor_placeholder( $attributes );
			}

			return '';
		}

		$inner = '';

		if ( 'slider' === $layout ) {
			$inner = '<div class="mw-artist-profile__slider">'
				. $image_html
				. '<div class="mw-artist-profile__body">' . $title_html . $count_html . $bio_html . $library_html . $link_html . '</div>'
				. '</div>';
		} elseif ( 'list' === $layout ) {
			$inner = '<div class="mw-artist-profile__row">'
				. ( '' !== $image_html ? '<div class="mw-artist-profile__media">' . $image_html . '</div>' : '' )
				. '<div class="mw-artist-profile__body">' . $title_html . $count_html . $bio_html . $library_html . $link_html . '</div>'
				. '</div>';
		} else {
			// Default presentation uses the card layout.
			$inner = $image_html
				. '<div class="mw-artist-profile__body">' . $title_html . $count_html . $bio_html . $library_html . $link_html . '</div>';
		}

		return '<section '
			. BlockSupport::wrapper_attributes( $wrapper_classes )
			. ( '' !== $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : '' )
			. '>' . $inner . '</section>';
	}

	/**
	 * Count published releases assigned to an artist term.
	 */
	private function release_count( int $term_id ): int {
		$release_ids = get_posts(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'mw_artist',
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		return is_array( $release_ids ) ? count( $release_ids ) : 0;
	}

	/**
	 * Determine whether the current request comes from a block editor context.
	 *
	 * Covers the Site Editor screen, the REST block-renderer endpoint and the
	 * post editor so the preview never falls back to an empty string.
	 *
	 * @return bool
	 */
	private function is_editor_request(): bool {
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}

		if ( is_admin() ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor context check.
		return isset( $_GET['context'] ) && 'edit' === sanitize_key( (string) wp_unslash( (string) $_GET['context'] ) );
	}

	/**
	 * Render an editor-only placeholder card when no artist can be resolved.
	 *
	 * Keeps the Site Editor preview non-empty so ServerSideRender does not
	 * surface the generic error notice.
	 *
	 * @param array<string, mixed> $attributes Block attributes from the editor.
	 *
	 * @return string
	 */
	private function render_editor_placeholder( array $attributes ): string {
		$label = __( 'Artist profile', 'music-wave-core' );
		$help  = isset( $attributes['termId'] ) && absint( $attributes['termId'] ) > 0
			? __( 'The selected artist was not found or has no public profile content yet. Add an artist term ID in the block sidebar.', 'music-wave-core' )
			: __( 'No artist selected yet. Enter an artist term ID in the block sidebar to pin an artist, or preview this block on an artist archive page.', 'music-wave-core' );

		return '<div class="mw-artist-profile mw-artist-profile--placeholder" style="border:1px dashed currentColor;border-radius:12px;padding:2.5rem 1.5rem;text-align:center;opacity:.8;">'
			. '<span class="dashicons dashicons-format-audio" aria-hidden="true" style="font-size:2rem;width:2rem;height:2rem;"></span>'
			. '<p style="margin:.5rem 0 0;"><strong>' . esc_html( $label ) . '</strong></p>'
			. '<p style="margin:.25rem 0 0;font-size:.875em;">' . esc_html( $help ) . '</p>'
			. '</div>';
	}

	/**
	 * Resolve the artist WP_Term object from attributes or the current query.
	 *
	 * @param array<string, mixed> $attributes
	 *
	 * @return WP_Term|null
	 */
	private function resolve_term( array $attributes ): ?WP_Term {
		$term_id = isset( $attributes['termId'] ) ? absint( $attributes['termId'] ) : 0;

		if ( $term_id > 0 ) {
			$term = get_term( $term_id, 'mw_artist' );
			return $term instanceof WP_Term ? $term : null;
		}

		// Fall back to the currently queried mw_artist taxonomy term.
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term && 'mw_artist' === $queried->taxonomy ) {
			return $queried;
		}

		return null;
	}
}
