<?php
/**
 * Personal music library blocks: the full library view and the toggle button.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Library\LibraryButton;
use ManaCore\MusicWave\Core\Library\LibraryCatalog;
use ManaCore\MusicWave\Core\Library\LibraryRepository;

final class LibraryBlocks {
	public const FILTER_QUERY_ARG = 'mw-library';
	public const PAGE_QUERY_ARG   = 'mw-library-page';

	/** @var LibraryRepository */
	private $repository;

	/** @var LibraryCatalog */
	private $catalog;

	/** @var object|null */
	private $render_context;

	public function __construct( LibraryRepository $repository, LibraryCatalog $catalog ) {
		$this->repository = $repository;
		$this->catalog    = $catalog;
	}

	/**
	 * Register both library blocks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/music-library',
			array( $this, 'render_library' ),
			array(
				'api_version' => 3,
				'attributes'  => $this->library_attributes(),
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
		BlockSupport::register_dynamic(
			'music-wave/library-button',
			function ( $attributes, $content, $block ): string {
				unset( $content );
				$previous_context     = $this->render_context;
				$this->render_context = is_object( $block ) ? $block : null;

				try {
					return $this->render_button( is_array( $attributes ) ? $attributes : array() );
				} finally {
					$this->render_context = $previous_context;
				}
			},
			array(
				'api_version'  => 3,
				'attributes'   => $this->button_attributes(),
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Attribute schema for the personal library block.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function library_attributes(): array {
		return array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'showHeading'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'intro'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'showFilters'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showCounts'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'layout'       => array(
				'type'    => 'string',
				'default' => 'list',
			),
			'columns'      => array(
				'type'    => 'integer',
				'default' => 4,
			),
			'itemsToShow'  => array(
				'type'    => 'integer',
				'default' => 24,
			),
			'showArtist'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showType'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showYear'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showRemove'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyMessage' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Attribute schema for the add-to-library button block.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function button_attributes(): array {
		return array(
			'releaseId'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'itemType'   => array(
				'type'    => 'string',
				'default' => 'release',
			),
			'termId'     => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'label'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'addedLabel' => array(
				'type'    => 'string',
				'default' => '',
			),
			'style'      => array(
				'type'    => 'string',
				'default' => 'solid',
			),
			'compact'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Render the complete personal music library.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_library( array $attributes ): string {
		/**
		 * Allow sites to disable the personal library feature entirely.
		 *
		 * @param bool $enabled Whether the personal library renders.
		 */
		if ( ! apply_filters( 'music_wave_library_enabled', true ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$heading = $this->text_attribute( $attributes, 'heading', __( 'My music library', 'music-wave-core' ) );
		$intro   = $this->text_attribute( $attributes, 'intro', __( 'Every song, album, podcast, and artist you save appears here. Add items with the “Add to library” button on any release or artist page.', 'music-wave-core' ) );

		if ( $user_id < 1 ) {
			return '<section ' . BlockSupport::wrapper_attributes( 'mw-music-library mw-music-library--guest' ) . '><div class="mw-music-library__guest"><span class="mw-music-library__eyebrow">' . esc_html__( 'Your collection', 'music-wave-core' ) . '</span><h2>' . esc_html( $heading ) . '</h2><p>' . esc_html__( 'Sign in to build your personal library: save songs, albums, podcasts, and follow your favorite artists.', 'music-wave-core' ) . '</p><a class="wp-element-button" href="' . esc_url( wp_login_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign in', 'music-wave-core' ) . '</a></div></section>';
		}

		$filter  = $this->active_filter();
		$counts  = $this->catalog->counts( $user_id );
		$page    = $this->active_page();
		$paged   = $this->catalog->paged_summaries( $user_id, $filter, $this->range_attribute( $attributes, 'itemsToShow', 1, 100, 24 ), $page );
		$items   = $paged['items'];
		$layout  = $this->key_attribute( $attributes, 'layout', array( 'list', 'grid' ), 'list' );
		$columns = $this->range_attribute( $attributes, 'columns', 2, 6, 4 );

		LibraryButton::enqueue_assets();

		$header = '';
		if ( ! isset( $attributes['showHeading'] ) || false !== $attributes['showHeading'] ) {
			$total  = isset( $counts[ LibraryCatalog::FILTER_ALL ] ) ? (int) $counts[ LibraryCatalog::FILTER_ALL ] : 0;
			$badge  = ( ! isset( $attributes['showCounts'] ) || false !== $attributes['showCounts'] ) && $total > 0
				? '<span class="mw-music-library__total" data-mw-library-count="all">' . esc_html( (string) $total ) . '</span>'
				: '';
			$header = '<header class="mw-music-library__header"><div><h2>' . esc_html( $heading ) . '</h2><p class="mw-music-library__intro">' . esc_html( $intro ) . '</p></div>' . $badge . '</header>';
		}

		$tabs = '';
		if ( ( ! isset( $attributes['showFilters'] ) || false !== $attributes['showFilters'] ) && count( $counts ) > 1 ) {
			$tabs = $this->filter_tabs( $counts, $filter, ! isset( $attributes['showCounts'] ) || false !== $attributes['showCounts'] );
		}

		$empty = $this->text_attribute(
			$attributes,
			'emptyMessage',
			LibraryCatalog::FILTER_ALL === $filter
			? __( 'Your library is empty. Open any release or artist page and use the “Add to library” button to save it here.', 'music-wave-core' )
			: __( 'Nothing saved in this collection yet.', 'music-wave-core' )
		);

		$body = '';
		if ( empty( $items ) ) {
			$body = '<p class="mw-music-library__empty" data-mw-library-empty>' . esc_html( $empty ) . '</p>';
		} else {
			$rows = array();
			foreach ( $items as $summary ) {
				$rows[] = $this->item_markup( $summary, $attributes );
			}
			$class = 'mw-music-library__items mw-music-library__items--' . $layout
				. ( 'grid' === $layout ? ' mw-music-library__items--columns-' . $columns : '' );
			$body  = '<ul class="' . esc_attr( $class ) . '" data-mw-library-items>' . implode( '', $rows ) . '</ul>'
				. '<p class="mw-music-library__empty" data-mw-library-empty hidden>' . esc_html( $empty ) . '</p>'
				. $this->pagination_markup( $filter, $page, (bool) $paged['has_more'] );
		}

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-music-library' ) . ' data-mw-library-panel>' . $header . $tabs . $body . '</section>';
	}

	/**
	 * Render a standalone add-to-library button.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_button( array $attributes ): string {
		$term_id = isset( $attributes['termId'] ) ? absint( $attributes['termId'] ) : 0;
		if ( $term_id > 0 ) {
			return '<div ' . BlockSupport::wrapper_attributes( 'mw-library-button-wrap' ) . '>'
				. LibraryButton::markup( LibraryRepository::TYPE_ARTIST, $term_id, $this->button_settings( $attributes ) )
				. '</div>';
		}

		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$item_type = $this->key_attribute(
			$attributes,
			'itemType',
			array( LibraryRepository::TYPE_RELEASE, LibraryRepository::TYPE_WISHLIST, LibraryRepository::TYPE_PRESAVE ),
			LibraryRepository::TYPE_RELEASE
		);
		$markup    = LibraryButton::markup( $item_type, $release_id, $this->button_settings( $attributes ) );
		if ( '' === $markup ) {
			return '';
		}

		return '<div ' . BlockSupport::wrapper_attributes( 'mw-library-button-wrap mw-library-button-wrap--' . $item_type ) . '>' . $markup . '</div>';
	}

	/**
	 * Build one list or grid row for a stored library item.
	 *
	 * @param array<string, mixed> $summary    Item summary from the catalog.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	private function item_markup( array $summary, array $attributes ): string {
		$type       = (string) $summary['type'];
		$item_id    = (int) $summary['id'];
		$title      = (string) $summary['title'];
		$url        = (string) $summary['url'];
		$artist     = (string) $summary['subtitle'];
		$type_label = (string) $summary['type_label'];
		$year       = (string) $summary['year'];

		$image = (string) $summary['image'];
		if ( '' === $image ) {
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
			$image   = '<span class="mw-music-library__placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>';
		}

		$title_markup = '' !== $url
			? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>'
			: esc_html( $title );

		$meta = array();
		if ( ! isset( $attributes['showType'] ) || false !== $attributes['showType'] ) {
			$meta[] = '<span class="mw-music-library__badge">' . esc_html( $type_label ) . '</span>';
		}
		if ( LibraryRepository::TYPE_RELEASE === $type && '' !== $artist && ( ! isset( $attributes['showArtist'] ) || false !== $attributes['showArtist'] ) ) {
			$meta[] = '<span class="mw-music-library__artist">' . esc_html( $artist ) . '</span>';
		}
		if ( LibraryRepository::TYPE_RELEASE === $type && '' !== $year && ! empty( $attributes['showYear'] ) ) {
			$meta[] = '<span class="mw-music-library__year">' . esc_html( $year ) . '</span>';
		}

		$remove = '';
		if ( ! isset( $attributes['showRemove'] ) || false !== $attributes['showRemove'] ) {
			/* translators: %s: library item title. */
			$remove_label = sprintf( __( 'Remove %s from library', 'music-wave-core' ), $title );
			$remove       = '<button type="button" class="mw-library-remove" data-mw-library-type="' . esc_attr( $type ) . '" data-mw-library-id="' . esc_attr( (string) $item_id ) . '" aria-label="' . esc_attr( $remove_label ) . '" title="' . esc_attr( $remove_label ) . '">&times;</button>';
		}

		$item_class = 'mw-music-library__item mw-music-library__item--' . esc_attr( $type );

		return '<li class="' . $item_class . '" data-mw-library-item="' . esc_attr( $type . '-' . $item_id ) . '">'
			. '<span class="mw-music-library__art">' . $image . '</span>'
			. '<div class="mw-music-library__details"><strong>' . $title_markup . '</strong>'
			. ( empty( $meta ) ? '' : '<div class="mw-music-library__meta">' . implode( '', $meta ) . '</div>' )
			. '</div>' . $remove . '</li>';
	}

	/**
	 * Render the filter tab navigation for the active library.
	 *
	 * @param array<string, int> $counts     Filter counts.
	 * @param string             $active     Active filter key.
	 * @param bool               $show_counts Whether tabs display their count.
	 * @return string
	 */
	private function filter_tabs( array $counts, string $active, bool $show_counts ): string {
		$labels = $this->catalog->filter_labels( $counts );
		$tabs   = array();

		// Keep "all" first, release types next, then the grouped tabs in a
		// predictable order: wishlist, coming soon, artists.
		$trailing = array( LibraryCatalog::FILTER_WISHLIST, LibraryCatalog::FILTER_PRESAVES, LibraryCatalog::FILTER_ARTISTS );
		$ordered  = array( LibraryCatalog::FILTER_ALL );
		foreach ( array_keys( $counts ) as $key ) {
			if ( LibraryCatalog::FILTER_ALL !== $key && ! in_array( (string) $key, $trailing, true ) ) {
				$ordered[] = (string) $key;
			}
		}
		foreach ( $trailing as $key ) {
			if ( isset( $counts[ $key ] ) ) {
				$ordered[] = $key;
			}
		}

		foreach ( $ordered as $key ) {
			if ( ! isset( $counts[ $key ] ) || $counts[ $key ] < 1 ) {
				continue;
			}
			$label  = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
			$count  = $show_counts ? '<span data-mw-library-count="' . esc_attr( $key ) . '">' . esc_html( (string) $counts[ $key ] ) . '</span>' : '';
			$class  = 'mw-music-library__tab' . ( $key === $active ? ' is-active' : '' );
			$tabs[] = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $this->filter_url( $key ) ) . '" aria-current="' . ( $key === $active ? 'true' : 'false' ) . '">' . esc_html( $label ) . $count . '</a>';
		}

		if ( count( $tabs ) < 2 ) {
			return '';
		}

		return '<nav class="mw-music-library__tabs" aria-label="' . esc_attr__( 'Library filters', 'music-wave-core' ) . '">' . implode( '', $tabs ) . '</nav>';
	}

	/**
	 * Build the current page URL narrowed to one library filter.
	 */
	private function filter_url( string $filter ): string {
		$base = is_singular() ? (string) get_permalink() : (string) home_url( '/' );
		if ( LibraryCatalog::FILTER_ALL === $filter ) {
			$cleaned = remove_query_arg( self::FILTER_QUERY_ARG, $base );

			return is_string( $cleaned ) ? $cleaned : $base;
		}

		return add_query_arg( self::FILTER_QUERY_ARG, rawurlencode( $filter ), $base );
	}

	/**
	 * Accessible previous/next pagination preserving the active filter.
	 */
	private function pagination_markup( string $filter, int $page, bool $has_more ): string {
		if ( $page <= 1 && ! $has_more ) {
			return '';
		}

		$links = array();
		if ( $page > 1 ) {
			$links[] = '<a class="mw-music-library__page-link mw-music-library__page-link--previous" href="' . esc_url( $this->page_url( $filter, $page - 1 ) ) . '">' . esc_html__( 'Newer items', 'music-wave-core' ) . '</a>';
		}
		if ( $has_more ) {
			$links[] = '<a class="mw-music-library__page-link mw-music-library__page-link--next" href="' . esc_url( $this->page_url( $filter, $page + 1 ) ) . '">' . esc_html__( 'Older items', 'music-wave-core' ) . '</a>';
		}

		/* translators: %d: current library page number. */
		$status = sprintf( __( 'Library page %d', 'music-wave-core' ), $page );

		return '<nav class="mw-music-library__pagination" aria-label="' . esc_attr__( 'Library pages', 'music-wave-core' ) . '"><span class="mw-music-library__page-status">' . esc_html( $status ) . '</span>' . implode( '', $links ) . '</nav>';
	}

	/**
	 * Build the current page URL for one library page, preserving the filter.
	 */
	private function page_url( string $filter, int $page ): string {
		$base = $this->filter_url( $filter );
		if ( $page <= 1 ) {
			$cleaned = remove_query_arg( self::PAGE_QUERY_ARG, $base );

			return is_string( $cleaned ) ? $cleaned : $base;
		}

		return add_query_arg( self::PAGE_QUERY_ARG, (string) $page, $base );
	}

	/**
	 * Read the active library page from the public query string.
	 */
	private function active_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display pagination.
		$page = isset( $_GET[ self::PAGE_QUERY_ARG ] ) && is_scalar( $_GET[ self::PAGE_QUERY_ARG ] ) ? absint( wp_unslash( (string) $_GET[ self::PAGE_QUERY_ARG ] ) ) : 0;

		return max( 1, $page );
	}

	/**
	 * Read the active filter from the public query string.
	 */
	private function active_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter.
		$filter = isset( $_GET[ self::FILTER_QUERY_ARG ] ) && is_scalar( $_GET[ self::FILTER_QUERY_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::FILTER_QUERY_ARG ] ) ) : '';

		return '' !== $filter ? $filter : LibraryCatalog::FILTER_ALL;
	}

	/**
	 * Convert block attributes into LibraryButton settings.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private function button_settings( array $attributes ): array {
		return array(
			'label'      => isset( $attributes['label'] ) && is_scalar( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '',
			'addedLabel' => isset( $attributes['addedLabel'] ) && is_scalar( $attributes['addedLabel'] ) ? sanitize_text_field( (string) $attributes['addedLabel'] ) : '',
			'style'      => isset( $attributes['style'] ) && is_scalar( $attributes['style'] ) ? sanitize_key( (string) $attributes['style'] ) : 'solid',
			'compact'    => ! empty( $attributes['compact'] ),
		);
	}

	/**
	 * Resolve the target release from attributes or the current block context.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function release_id( array $attributes ): int {
		$release_id = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if ( $release_id > 0 ) {
			return $release_id;
		}

		if (
			null !== $this->render_context
			&& isset( $this->render_context->context['postType'], $this->render_context->context['postId'] )
			&& \ManaCore\MusicWave\Core\Catalog\ReleasePostType::KEY === $this->render_context->context['postType']
		) {
			return absint( $this->render_context->context['postId'] );
		}

		$post = get_post();
		if ( null !== $post && \ManaCore\MusicWave\Core\Catalog\ReleasePostType::KEY === get_post_type( $post ) ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Read a sanitized plain-text attribute with a translated fallback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param string               $fallback   Translated default text.
	 */
	private function text_attribute( array $attributes, string $key, string $fallback ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? sanitize_text_field( (string) $attributes[ $key ] ) : '';

		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Read an allow-listed key attribute, falling back when unknown.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param array<int, string>   $allowed    Allowed values.
	 * @param string               $fallback   Fallback value.
	 */
	private function key_attribute( array $attributes, string $key, array $allowed, string $fallback ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? sanitize_key( (string) $attributes[ $key ] ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Read a bounded integer attribute; out-of-range values use the fallback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param int                  $min        Minimum allowed value.
	 * @param int                  $max        Maximum allowed value.
	 * @param int                  $fallback   Fallback value.
	 */
	private function range_attribute( array $attributes, string $key, int $min, int $max, int $fallback ): int {
		$value = isset( $attributes[ $key ] ) ? absint( $attributes[ $key ] ) : 0;

		return $value >= $min && $value <= $max ? $value : $fallback;
	}
}
