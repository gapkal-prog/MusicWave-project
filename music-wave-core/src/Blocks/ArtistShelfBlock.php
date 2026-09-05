<?php
/**
 * Public artists shelf block: multi-artist discovery surfaces.
 *
 * Renders the catalog's artists as a responsive grid, horizontal scroll
 * shelf, or compact list. Sources cover every editorial need: all artists,
 * artists behind one genre or mood, or a hand-picked ordered list. Cards
 * reuse the shared MusicWave release-shelf chrome so every shelf on the
 * site shares one visual system, and the follow control reuses the personal
 * library button so follows stay consistent everywhere.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryRepository;
use WP_Term;

final class ArtistShelfBlock {
	/** Hard ceiling for one render; keeps queries and DOM bounded. */
	private const MAX_ITEMS = 24;

	/**
	 * Register the server-rendered artists shelf block.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/artists-shelf',
			array( $this, 'render' ),
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the artists shelf.
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
				: __( 'هنوز هنرمندی برای نمایش وجود ندارد.', 'music-wave-core' );

			return '<section '
				. BlockSupport::wrapper_attributes( 'mw-artists-shelf mw-artists-shelf--empty' )
				. '><p class="mw-artists-shelf__empty">' . esc_html( $message ) . '</p></section>';
		}

		$options = $this->resolve_options( $attributes );

		$cards = array();
		foreach ( $terms as $term ) {
			$card = $this->card( $term, $options );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}

		if ( array() === $cards ) {
			return '';
		}

		$header = '';
		if ( $options['show_heading'] ) {
			$header = $this->header( $attributes, $options );
		}

		$shelf_class = 'mw-artists-shelf mw-release-shelf mw-release-shelf--' . $options['layout'];
		if ( 'grid' === $options['layout'] ) {
			$shelf_class .= ' mw-release-shelf--columns-' . $options['columns'];
		}

		return '<section '
			. BlockSupport::wrapper_attributes( $shelf_class )
			. '>'
			. $header
			. '<div class="mw-release-shelf__items">' . implode( '', $cards ) . '</div>'
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

		$image_shape = isset( $attributes['imageShape'] ) ? sanitize_key( (string) $attributes['imageShape'] ) : 'circle';
		$image_shape = in_array( $image_shape, array( 'circle', 'rounded', 'square' ), true ) ? $image_shape : 'circle';

		$image_size = isset( $attributes['imageSize'] ) ? sanitize_key( (string) $attributes['imageSize'] ) : 'medium';
		$image_size = in_array( $image_size, array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $image_size : 'medium';

		$items = isset( $attributes['itemsToShow'] ) ? absint( $attributes['itemsToShow'] ) : 0;
		$items = min( self::MAX_ITEMS, max( 1, $items > 0 ? $items : 8 ) );

		$columns = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 4;
		$columns = min( 6, max( 2, $columns > 0 ? $columns : 4 ) );

		$bio_length = isset( $attributes['bioLength'] ) ? absint( $attributes['bioLength'] ) : 20;
		$bio_length = min( 80, $bio_length );

		$order_by = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'count';
		$order_by = in_array( $order_by, array( 'name', 'count', 'rand' ), true ) ? $order_by : 'count';

		$order = isset( $attributes['order'] ) && 'ASC' === strtoupper( (string) $attributes['order'] ) ? 'ASC' : 'DESC';
		if ( 'name' === $order_by ) {
			// Alphabetical shelves read naturally in one direction.
			$order = 'ASC';
		}

		return array(
			'layout'        => $layout,
			'image_shape'   => $image_shape,
			'image_size'    => $image_size,
			'items'         => $items,
			'columns'       => $columns,
			'bio_length'    => $bio_length,
			'order_by'      => $order_by,
			'order'         => $order,
			'show_heading'  => ! isset( $attributes['showHeading'] ) || (bool) $attributes['showHeading'],
			'show_image'    => ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'],
			'show_name'     => ! isset( $attributes['showName'] ) || (bool) $attributes['showName'],
			'show_bio'      => ! isset( $attributes['showBio'] ) || (bool) $attributes['showBio'],
			'show_count'    => ! isset( $attributes['showReleaseCount'] ) || (bool) $attributes['showReleaseCount'],
			'show_follow'   => ! isset( $attributes['showFollowButton'] ) || (bool) $attributes['showFollowButton'],
			'eyebrow'       => isset( $attributes['eyebrow'] ) ? sanitize_text_field( (string) $attributes['eyebrow'] ) : '',
			'heading'       => isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : '',
			'description'   => isset( $attributes['description'] ) ? sanitize_text_field( (string) $attributes['description'] ) : '',
			'section_url'   => isset( $attributes['sectionUrl'] ) ? esc_url_raw( (string) $attributes['sectionUrl'] ) : '',
			'section_label' => isset( $attributes['sectionLinkLabel'] ) ? sanitize_text_field( (string) $attributes['sectionLinkLabel'] ) : '',
			'empty_message' => isset( $attributes['emptyMessage'] ) ? sanitize_text_field( (string) $attributes['emptyMessage'] ) : '',
		);
	}

	/**
	 * Resolve the artist terms for this render according to the source.
	 *
	 * Term counts on the non-hierarchical `mw_artist` taxonomy track only
	 * published releases (WordPress core term counting), so `hide_empty`
	 * filtering costs no extra queries.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, WP_Term>
	 */
	private function resolve_terms( array $attributes ): array {
		$source = isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : 'all';
		$source = in_array( $source, array( 'all', 'genre', 'mood', 'manual' ), true ) ? $source : 'all';

		$terms = array();

		if ( 'manual' === $source ) {
			$ids = isset( $attributes['artistIds'] ) ? (string) $attributes['artistIds'] : '';
			$ids = preg_split( '/[\s,]+/', $ids );
			$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
			$ids = array_slice( $ids, 0, self::MAX_ITEMS );

			if ( array() !== $ids ) {
				$found = get_terms(
					array(
						'taxonomy'   => 'mw_artist',
						'include'    => $ids,
						'orderby'    => 'include',
						'hide_empty' => false,
						'number'     => self::MAX_ITEMS,
					)
				);
				$terms = is_array( $found ) ? $found : array();
			}
		} elseif ( 'genre' === $source || 'mood' === $source ) {
			$terms = $this->terms_behind_taxonomy( $source, isset( $attributes['termSlug'] ) ? sanitize_title( (string) $attributes['termSlug'] ) : '' );
		} else {
			$order_by = isset( $attributes['orderBy'] ) ? sanitize_key( (string) $attributes['orderBy'] ) : 'count';
			$order_by = in_array( $order_by, array( 'name', 'count', 'rand' ), true ) ? $order_by : 'count';

			$query = array(
				'taxonomy'   => 'mw_artist',
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
		 * Filter the resolved artists before rendering the shelf.
		 *
		 * Terms must still belong to the `mw_artist` taxonomy; the renderer
		 * re-validates every term it receives.
		 *
		 * @param array<int, WP_Term>  $terms      Resolved artist terms.
		 * @param array<string, mixed> $attributes Block attributes.
		 */
		$filtered = apply_filters( 'music_wave_artists_shelf_terms', $terms, $attributes );
		if ( ! is_array( $filtered ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$filtered,
				static function ( $term ): bool {
					return $term instanceof WP_Term && 'mw_artist' === $term->taxonomy;
				}
			)
		);
	}

	/**
	 * Resolve artists that have at least one release in a genre or mood.
	 *
	 * Two bounded queries: the matching release IDs, then one term lookup
	 * across all of them via `object_ids`.
	 *
	 * @param string $taxonomy Taxonomy key (mw_genre|mood).
	 * @param string $slug     Term slug filter; empty means the whole taxonomy.
	 * @return array<int, WP_Term>
	 */
	private function terms_behind_taxonomy( string $taxonomy, string $slug ): array {
		$taxonomy = 'mood' === $taxonomy ? 'mw_mood' : 'mw_genre';
		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$release_ids = get_posts(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded release sample used to resolve visible artist terms.
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded indexed taxonomy filter.
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => array( $slug ),
					),
				),
			)
		);
		if ( ! is_array( $release_ids ) || array() === $release_ids ) {
			return array();
		}

		$found = get_terms(
			array(
				'taxonomy'   => 'mw_artist',
				'object_ids' => $release_ids,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 100,
			)
		);

		return is_array( $found ) ? $found : array();
	}

	/**
	 * Build one artist card.
	 *
	 * @param WP_Term              $term    Artist term.
	 * @param array<string, mixed> $options Resolved options.
	 * @return string
	 */
	private function card( WP_Term $term, array $options ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) {
			return '';
		}

		$name = '' !== $term->name ? $term->name : __( 'هنرمند بی نام', 'music-wave-core' );
		/* translators: %s: artist name. */
		$open_label = sprintf( __( 'باز کردن %s', 'music-wave-core' ), $name );

		$avatar = '';
		if ( $options['show_image'] ) {
			$image_id   = absint( get_term_meta( $term->term_id, 'mw_artist_image_id', true ) );
			$image_html = $image_id > 0
				? wp_get_attachment_image(
					$image_id,
					$options['image_size'],
					false,
					array(
						'class'    => 'mw-artists-shelf__image',
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				)
				: '';
			$initial    = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
			$avatar     = '<a class="mw-artists-shelf__avatar mw-artists-shelf__avatar--' . esc_attr( (string) $options['image_shape'] ) . '" href="' . esc_url( $link ) . '" aria-label="' . esc_attr( $open_label ) . '">'
				. ( '' !== $image_html ? $image_html : '<span class="mw-artists-shelf__initial" aria-hidden="true">' . esc_html( $initial ) . '</span>' )
				. '</a>';
		}

		$name_html = '';
		if ( $options['show_name'] ) {
			$name_html = '<h3 class="mw-artists-shelf__name"><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></h3>';
		}

		$count_html = '';
		if ( $options['show_count'] && $term->count > 0 ) {
			$count_html = '<p class="mw-artists-shelf__count">' . esc_html(
				sprintf(
					/* translators: %s: number of published releases. */
					_n( 'انتشار %s', 'انتشار %s', (int) $term->count, 'music-wave-core' ),
					number_format_i18n( (int) $term->count )
				)
			) . '</p>';
		}

		$bio_html = '';
		if ( $options['show_bio'] && $options['bio_length'] >= 5 ) {
			$biography = (string) get_term_meta( $term->term_id, 'mw_artist_biography', true );
			if ( '' !== $biography ) {
				$bio_html = '<p class="mw-artists-shelf__bio">' . esc_html( wp_trim_words( wp_strip_all_tags( $biography ), $options['bio_length'] ) ) . '</p>';
			}
		}

		$follow_html = '';
		if ( $options['show_follow'] ) {
			$button = LibraryButton::markup(
				LibraryRepository::TYPE_ARTIST,
				(int) $term->term_id,
				array(
					'style'   => 'ghost',
					'compact' => true,
				)
			);
			if ( '' !== $button ) {
				$follow_html = '<div class="mw-artists-shelf__follow">' . $button . '</div>';
			}
		}

		return '<article class="mw-artists-shelf__item">'
			. $avatar
			. '<div class="mw-artists-shelf__body">' . $name_html . $count_html . $bio_html . $follow_html . '</div>'
			. '</article>';
	}

	/**
	 * Build the shared shelf header chrome.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string, mixed> $options    Resolved options.
	 * @return string
	 */
	private function header( array $attributes, array $options ): string {
		$heading = '' !== $options['heading']
			? $options['heading']
			: __( 'هنرمندان محبوب', 'music-wave-core' );

		$more = '';
		if ( '' !== $options['section_url'] ) {
			$label = '' !== $options['section_label']
				? $options['section_label']
				: __( 'دیدن همه هنرمندان', 'music-wave-core' );
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
		$label = __( 'ویترین هنرمندان', 'music-wave-core' );
		$help  = isset( $attributes['source'] ) && 'manual' === $attributes['source']
			? __( 'هیچ هنرمند منطبقی پیدا نشد. شناسه‌های هنرمند انتخاب‌شده را بررسی کنید.', 'music-wave-core' )
			: __( 'هنوز هیچ هنرمندی با انتشارهای منتشرشده یافت نشد. هنرمندان را به انتشارات اختصاص دهید و آن‌ها به‌طور خودکار در اینجا ظاهر می‌شوند.', 'music-wave-core' );

		return '<div class="mw-artists-shelf mw-artists-shelf--placeholder" style="border:1px dashed currentColor;border-radius:12px;padding:2.5rem 1.5rem;text-align:center;opacity:.8;">'
			. '<span class="dashicons dashicons-admin-users" aria-hidden="true" style="font-size:2rem;width:2rem;height:2rem;"></span>'
			. '<p style="margin:.5rem 0 0;"><strong>' . esc_html( $label ) . '</strong></p>'
			. '<p style="margin:.25rem 0 0;font-size:.875em;">' . esc_html( $help ) . '</p>'
			. '</div>';
	}
}
