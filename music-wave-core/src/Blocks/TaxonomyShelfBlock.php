<?php
/**
 * Public taxonomy shelf block: genre, mood, and label browse surfaces.
 *
 * Renders catalog taxonomies as tappable browse tiles — the discovery layer
 * global music platforms put on their home page. Tiles cycle through a fixed
 * set of theme-token hue classes so every shelf looks curated without any
 * configuration, reuse the shared MusicWave release-shelf chrome for grids
 * and rails, and always link to real term archives.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use WP_Term;

final class TaxonomyShelfBlock {
	/** Hard ceiling for one render; keeps queries and DOM bounded. */
	private const MAX_ITEMS = 24;

	/** Taxonomies this block may browse. */
	private const ALLOWED_TAXONOMIES = array( 'mw_genre', 'mw_mood', 'mw_label' );

	/**
	 * Register the server-rendered taxonomy shelf block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/taxonomy-shelf',
			array( $this, 'render' ),
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the taxonomy shelf.
	 *
	 * @param array<string, mixed> $attributes Block attributes from the editor.
	 *
	 * @return string
	 */
	public function render( array $attributes = array() ): string {
		$terms = $this->resolve_terms( $attributes );
		if ( array() === $terms ) {
			if ( $this->is_editor_request() ) {
				return $this->render_editor_placeholder( $attributes );
			}

			$options = $this->resolve_options( $attributes );
			$message = '' !== $options['empty_message']
				? $options['empty_message']
				: __( 'هنوز چیزی برای مرور وجود ندارد.', 'music-wave-core' );

			return '<section '
				. BlockSupport::wrapper_attributes( 'mw-terms-shelf mw-terms-shelf--empty' )
				. '><p class="mw-terms-shelf__empty">' . esc_html( $message ) . '</p></section>';
		}

		$options = $this->resolve_options( $attributes );

		$tiles = array();
		foreach ( array_values( $terms ) as $index => $term ) {
			$tile = $this->tile( $term, $options, $index );
			if ( '' !== $tile ) {
				$tiles[] = $tile;
			}
		}

		if ( array() === $tiles ) {
			return '';
		}

		$header = '';
		if ( $options['show_heading'] ) {
			$header = $this->header( $options );
		}

		$shelf_class = 'mw-terms-shelf mw-release-shelf mw-release-shelf--' . $options['layout'];
		if ( 'grid' === $options['layout'] ) {
			$shelf_class .= ' mw-release-shelf--columns-' . $options['columns'];
		}

		return '<section '
			. BlockSupport::wrapper_attributes( $shelf_class )
			. '>'
			. $header
			. '<div class="mw-release-shelf__items">' . implode( '', $tiles ) . '</div>'
			. '</section>';
	}

	/**
	 * Resolve sanitized display options once per render.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function resolve_options( array $attributes ): array {
		$layout = isset( $attributes['layout'] ) ? sanitize_key( (string) $attributes['layout'] ) : 'grid';
		$layout = in_array( $layout, array( 'grid', 'scroll', 'list' ), true ) ? $layout : 'grid';

		$card_style = isset( $attributes['cardStyle'] ) ? sanitize_key( (string) $attributes['cardStyle'] ) : 'colorful';
		// `mood` is the reference "Moods & Sanctuaries" tile: a tall card with
		// a photographic/hue backdrop, a pill on top and the description below.
		$card_style = in_array( $card_style, array( 'colorful', 'plain', 'mood' ), true ) ? $card_style : 'colorful';

		$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 0;
		$items = min( self::MAX_ITEMS, max( 1, $items > 0 ? $items : 8 ) );

		$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 4;
		$columns = min( 6, max( 2, $columns > 0 ? $columns : 4 ) );

		$order_by = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'count';
		$order_by = in_array( $order_by, array( 'name', 'count', 'rand' ), true ) ? $order_by : 'count';

		return array(
			'layout'        => $layout,
			'card_style'    => $card_style,
			'items'         => $items,
			'columns'       => $columns,
			'order_by'      => $order_by,
			'show_heading'  => ! isset( $attributes['showHeading'] ) || (bool) $attributes['showHeading'],
			'show_count'    => ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'],
			'eyebrow'       => isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '',
			'heading'       => isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : '',
			'description'   => isset( $attributes['description'] ) ? sanitize_text_field( (string) $attributes['description'] ) : '',
			'section_url'   => isset( $attributes['sectionUrl'] ) ? esc_url_raw( (string) $attributes['sectionUrl'] ) : '',
			'section_label' => isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '',
			'empty_message' => isset( $attributes['emptyMessage'] ) ? sanitize_text_field( (string) $attributes['emptyMessage'] ) : '',
		);
	}

	/**
	 * Resolve the terms for this render according to the source.
	 *
	 * Term counts track only published releases for these non-hierarchical
	 * taxonomies, so `hide_empty` filtering costs no extra queries.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, WP_Term>
	 */
	private function resolve_terms( array $attributes ): array {
		$taxonomy = isset( $attributes['taxonomy'] ) ? sanitize_key( (string) $attributes['taxonomy'] ) : 'mw_genre';
		if ( ! in_array( $taxonomy, self::ALLOWED_TAXONOMIES, true ) || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$source = isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : 'all';
		$source = in_array( $source, array( 'all', 'manual' ), true ) ? $source : 'all';

		$terms = array();

		if ( 'manual' === $source ) {
			$ids = isset( $attributes['termIds'] ) ? (string) $attributes['termIds'] : '';
			$ids = preg_split( '/[\s,]+/', $ids );
			$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
			$ids = array_slice( $ids, 0, self::MAX_ITEMS );

			if ( array() !== $ids ) {
				$found = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'include'    => $ids,
						'orderby'    => 'include',
						'hide_empty' => false,
						'number'     => self::MAX_ITEMS,
					)
				);
				$terms = is_array( $found ) ? $found : array();
			}
		} else {
			$order_by = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'count';
			$order_by = in_array( $order_by, array( 'name', 'count', 'rand' ), true ) ? $order_by : 'count';

			$query = array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => self::MAX_ITEMS,
			);

			if ( 'rand' === $order_by ) {
				// Fetch a wider pool, then shuffle in PHP; term queries do not
				// support randomized SQL ordering.
				$query['number'] = 100;
			} elseif ( 'name' === $order_by ) {
				$query['orderby'] = 'name';
				$query['order']   = 'ASC';
			} else {
				$query['orderby'] = 'count';
				$query['order']   = 'DESC';
			}

			$found = get_terms( $query );
			$terms = is_array( $found ) ? $found : array();

			if ( 'rand' === $order_by ) {
				shuffle( $terms );
			}
		}

		$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 0;
		$items = min( self::MAX_ITEMS, max( 1, $items > 0 ? $items : 8 ) );
		$terms = array_slice( $terms, 0, $items );

		/**
		 * Filter the resolved terms before rendering the shelf.
		 *
		 * Terms must still belong to the configured taxonomy; the renderer
		 * re-validates every term it receives.
		 *
		 * @param array<int, WP_Term>  $terms      Resolved terms.
		 * @param array<string, mixed> $attributes Block attributes.
		 */
		$filtered = apply_filters( 'music_wave_terms_shelf_terms', $terms, $attributes );
		if ( ! is_array( $filtered ) ) {
			return array();
		}

		$taxonomy_guard = $taxonomy;

		return array_values(
			array_filter(
				$filtered,
				static function ( $term ) use ( $taxonomy_guard ): bool {
					return $term instanceof WP_Term && $taxonomy_guard === $term->taxonomy;
				}
			)
		);
	}

	/**
	 * Build one browse tile.
	 *
	 * @param WP_Term              $term    Term.
	 * @param array<string, mixed> $options Resolved options.
	 * @param int                  $index   Position used for deterministic hues.
	 * @return string
	 */
	private function tile( WP_Term $term, array $options, int $index ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			return '';
		}

		$name = '' !== $term->name ? $term->name : __( 'بدون عنوان', 'music-wave-core' );

		$count_html = '';
		if ( $options['show_count'] && (int) $term->count > 0 ) {
			$count_html = '<span class="mw-terms-shelf__count">' . esc_html(
				sprintf(
					/* translators: %s: number of published releases. */
					_n( 'انتشار %s', 'انتشار %s', (int) $term->count, 'music-wave-core' ),
					number_format_i18n( (int) $term->count )
				)
			) . '</span>';
		}
		/* translators: %s: taxonomy term name. */
		$open_label = sprintf( __( 'مرور %s', 'music-wave-core' ), $name );

		$hue = ( $index % 6 ) + 1;

		if ( 'mood' === $options['card_style'] ) {
			return $this->mood_tile( $term, $options, $link, $name, $count_html, $open_label, $hue );
		}

		return '<a class="mw-terms-shelf__tile'
			. ' mw-terms-shelf__tile--hue-' . absint( $hue )
			. ' mw-terms-shelf__tile--style-' . esc_attr( (string) $options['card_style'] )
			. '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">'
			. '<span class="mw-terms-shelf__name">' . esc_html( $name ) . '</span>'
			. $count_html
			. '</a>';
	}

	/**
	 * The WAVE mood tile: backdrop (term image when the taxonomy carries one,
	 * curated hue otherwise), a taxonomy pill on top, name + description and
	 * the release count at the bottom.
	 *
	 * @param WP_Term              $term       Term.
	 * @param array<string, mixed> $options    Resolved options.
	 * @param string               $link       Term archive URL.
	 * @param string               $name       Display name.
	 * @param string               $count_html Count markup, possibly empty.
	 * @param string               $open_label Accessible label.
	 * @param int                  $hue        Deterministic hue index.
	 */
	private function mood_tile( WP_Term $term, array $options, string $link, string $name, string $count_html, string $open_label, int $hue ): string {
		$image_id = absint( get_term_meta( $term->term_id, 'mw_artist_image_id', true ) );
		$backdrop = $image_id > 0
			? wp_get_attachment_image(
				$image_id,
				'medium_large',
				false,
				array(
					'class'    => 'mw-terms-shelf__backdrop',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			)
			: '';

		$taxonomy = get_taxonomy( $term->taxonomy );
		$pill     = is_object( $taxonomy ) && isset( $taxonomy->labels->singular_name ) ? (string) $taxonomy->labels->singular_name : '';

		$description = trim( wp_strip_all_tags( (string) $term->description ) );
		$description = '' !== $description ? wp_trim_words( $description, 12 ) : '';

		return '<a class="mw-terms-shelf__tile mw-terms-shelf__tile--style-mood mw-terms-shelf__tile--hue-' . absint( $hue ) . ( '' !== $backdrop ? ' has-backdrop' : '' )
			. '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">'
			. $backdrop
			. '<span class="mw-terms-shelf__scrim" aria-hidden="true"></span>'
			. ( '' !== $pill ? '<span class="mw-terms-shelf__pill mw-pill">' . esc_html( $pill ) . '</span>' : '' )
			. '<span class="mw-terms-shelf__body">'
			. '<span class="mw-terms-shelf__name">' . esc_html( $name ) . '</span>'
			. ( '' !== $description ? '<span class="mw-terms-shelf__description">' . esc_html( $description ) . '</span>' : '' )
			. $count_html
			. '</span>'
			. '</a>';
	}

	/**
	 * Build the shared shelf header chrome.
	 *
	 * @param array<string, mixed> $options Resolved options.
	 * @return string
	 */
	private function header( array $options ): string {
		$heading = '' !== $options['heading']
			? $options['heading']
			: __( 'فهرست کاتالوگ را مرور کنید', 'music-wave-core' );

		$more = '';
		if ( '' !== $options['section_url'] ) {
			$label = '' !== $options['section_label']
				? $options['section_label']
				: __( 'همه چیز را ببینید', 'music-wave-core' );
			$more  = '<a class="mw-release-shelf__more" href="' . esc_url( $options['section_url'] ) . '">' . esc_html( $label ) . '<span aria-hidden="true">&rarr;</span></a>';
		}

		return '<header class="mw-release-shelf__header"><div>'
			. ( '' !== $options['eyebrow'] ? '<span>' . esc_html( $options['eyebrow'] ) . '</span>' : '' )
			. '<h2>' . esc_html( $heading ) . '</h2>'
			. ( '' !== $options['description'] ? '<p>' . esc_html( $options['description'] ) . '</p>' : '' )
			. '</div>' . $more . '</header>';
	}

	/**
	 * Determine whether the current request comes from a block editor context.
	 *
	 * @return bool
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
	 * @return string
	 */
	private function render_editor_placeholder( array $attributes ): string {
		$label = __( 'ویترین طبقه‌بندی', 'music-wave-core' );
		$help  = isset( $attributes['source'] ) && 'manual' === $attributes['source']
			? __( 'هیچ اصطلاح منطبقی پیدا نشد. شناسه‌های اصطلاح انتخاب‌شده را بررسی کنید.', 'music-wave-core' )
			: __( 'هنوز هیچ اصطلاحی با انتشارهای منتشرشده پیدا نشده است. این طبقه‌بندی را به انتشارها اختصاص دهید تا خودکار اینجا ظاهر شوند.', 'music-wave-core' );

		return '<div class="mw-terms-shelf mw-terms-shelf--placeholder" style="border:1px dashed currentColor;border-radius:12px;padding:2.5rem 1.5rem;text-align:center;opacity:.8;">'
			. '<span class="dashicons dashicons-tag" aria-hidden="true" style="font-size:2rem;width:2rem;height:2rem;"></span>'
			. '<p style="margin:.5rem 0 0;"><strong>' . esc_html( $label ) . '</strong></p>'
			. '<p style="margin:.25rem 0 0;font-size:.875em;">' . esc_html( $help ) . '</p>'
			. '</div>';
	}
}
