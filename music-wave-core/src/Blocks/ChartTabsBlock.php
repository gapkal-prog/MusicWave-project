<?php
/**
 * Chart tabs: WAVE Top 100 / Recommended / New — one accessible tablist.
 *
 * Data is 100% real: Top = most-viewed published releases (mw_views),
 * Recommended = same weighted genre/mood/type signals as related releases,
 * New = latest published releases. No demo fixtures are ever rendered.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class ChartTabsBlock {
	/** @var ReleaseRepository */
	private $repository;

	public function __construct( ReleaseRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register the dynamic chart-tabs block and its frontend assets.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/chart-tabs',
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
	 * Enqueue the tabs controller only when the block is present.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! has_block( 'music-wave/chart-tabs' ) ) {
			return;
		}
		wp_enqueue_script(
			'music-wave-chart-tabs',
			MUSIC_WAVE_CORE_URL . 'assets/chart-tabs.js',
			array(),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'music-wave-chart-tabs', 'music-wave-core', MUSIC_WAVE_CORE_PATH . 'languages' );
		}
		wp_enqueue_style(
			'music-wave-chart-tabs',
			MUSIC_WAVE_CORE_URL . 'assets/chart-tabs.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Render the chart tablist with three real-data rails.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render( array $attributes ): string {
		$eyebrow      = BlockSupport::text_attribute( $attributes, 'eyebrow', __( 'چارت موزیک‌ویو', 'music-wave-core' ) );
		$heading      = BlockSupport::text_attribute( $attributes, 'heading', __( 'WAVE Top 100', 'music-wave-core' ) );
		$show_heading = BlockSupport::bool_attribute( $attributes, 'showHeading', true );
		$items        = BlockSupport::range_attribute( $attributes, 'itemsToShow', 3, 100, 10 );
		$show_rank    = BlockSupport::bool_attribute( $attributes, 'showRank', true );
		$show_badges  = BlockSupport::bool_attribute( $attributes, 'showBadges', true );
		$show_preview = BlockSupport::bool_attribute( $attributes, 'showPreview', true );
		$show_tabs    = array(
			'top'         => BlockSupport::bool_attribute( $attributes, 'showTop', true ),
			'recommended' => BlockSupport::bool_attribute( $attributes, 'showRecommended', true ),
			'new'         => BlockSupport::bool_attribute( $attributes, 'showNew', true ),
		);
		$default_tab  = BlockSupport::key_attribute( $attributes, 'defaultTab', array( 'top', 'recommended', 'new' ), 'top' );

		$tabs = array();
		if ( $show_tabs['top'] ) {
			$ids = $this->top_ids( $items );
			if ( ! empty( $ids ) ) {
				$tabs['top'] = array(
					'label' => BlockSupport::text_attribute( $attributes, 'topLabel', __( 'پرتکرارها', 'music-wave-core' ) ),
					'ids'   => $ids,
				);
			}
		}
		if ( $show_tabs['recommended'] ) {
			$ids = $this->recommended_ids( $items );
			if ( ! empty( $ids ) ) {
				$tabs['recommended'] = array(
					'label' => BlockSupport::text_attribute( $attributes, 'recommendedLabel', __( 'پیشنهادی', 'music-wave-core' ) ),
					'ids'   => $ids,
				);
			}
		}
		if ( $show_tabs['new'] ) {
			$ids = $this->new_ids( $items );
			if ( ! empty( $ids ) ) {
				$tabs['new'] = array(
					'label' => BlockSupport::text_attribute( $attributes, 'newLabel', __( 'تازه‌ها', 'music-wave-core' ) ),
					'ids'   => $ids,
				);
			}
		}

		if ( empty( $tabs ) ) {
			return '';
		}
		if ( ! isset( $tabs[ $default_tab ] ) ) {
			$keys        = array_keys( $tabs );
			$default_tab = (string) $keys[0];
		}

		$variation = BlockSupport::style_variation( $attributes, array( 'chart', 'cards', 'minimal' ) );
		$classes   = 'mw-chart-tabs' . ( '' !== $variation ? ' mw-chart-tabs--' . $variation : ' mw-chart-tabs--chart' );

		$header = '';
		if ( $show_heading ) {
			$header = '<div class="mw-chart-tabs__header"><span class="mw-chart-tabs__eyebrow">' . esc_html( $eyebrow ) . '</span><h2 class="mw-chart-tabs__title">' . esc_html( $heading ) . '</h2></div>';
		}

		$uid       = 'mw-chart-' . substr( md5( wp_json_encode( array_keys( $tabs ) ) . $items ), 0, 8 );
		$tablist   = '<div class="mw-chart-tabs__tablist" role="tablist" aria-label="' . esc_attr( $heading ) . '">';
		$panels    = '';
		$first_tab = true;
		foreach ( $tabs as $key => $tab ) {
			$is_active   = $key === $default_tab;
			$tab_id      = $uid . '-tab-' . $key;
			$panel_id    = $uid . '-panel-' . $key;
			$tablist    .= '<button type="button" role="tab" id="' . esc_attr( $tab_id ) . '" aria-controls="' . esc_attr( $panel_id ) . '" aria-selected="' . ( $is_active ? 'true' : 'false' ) . '" tabindex="' . ( $is_active ? '0' : '-1' ) . '" class="mw-chart-tabs__tab' . ( $is_active ? ' is-active' : '' ) . '" data-mw-chart-tab="' . esc_attr( $key ) . '">' . esc_html( (string) $tab['label'] ) . '</button>';
			$rows        = $this->rows_markup( $tab['ids'], $show_rank, $show_badges, $show_preview );
			$panels     .= '<div role="tabpanel" id="' . esc_attr( $panel_id ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '"' . ( $is_active ? '' : ' hidden' ) . ' class="mw-chart-tabs__panel" data-mw-chart-panel="' . esc_attr( $key ) . '"><ol class="mw-chart-tabs__rows">' . $rows . '</ol></div>';
			$first_tab   = false;
		}
		$tablist .= '</div>';

		return '<section ' . BlockSupport::wrapper_attributes( $classes ) . ' data-mw-chart-tabs>' . $header . $tablist . $panels . '</section>';
	}

	/**
	 * Most-viewed published releases by the real mw_views counter.
	 *
	 * @return array<int, int>
	 */
	private function top_ids( int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'meta_key'               => 'mw_views',
				'orderby'                => 'meta_value_num',
				'order'                  => 'DESC',
				'suppress_filters'       => false,
			)
		);
		$ids   = is_array( $query->posts ) ? array_values( array_filter( array_map( 'absint', $query->posts ) ) ) : array();
		if ( count( $ids ) < min( 3, $limit ) ) {
			// Fallback to latest when the counter has no data yet — still real.
			return $this->new_ids( $limit );
		}

		return $ids;
	}

	/**
	 * Latest published releases.
	 *
	 * @return array<int, int>
	 */
	private function new_ids( int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'suppress_filters'       => false,
			)
		);

		return is_array( $posts ) ? array_values( array_filter( array_map( 'absint', $posts ) ) ) : array();
	}

	/**
	 * Curated pool: most recent releases with artwork + preview first.
	 *
	 * Real curation without inventing scores — prefers releases that actually
	 * have a cover and a playable preview so the rail is instantly useful.
	 *
	 * @return array<int, int>
	 */
	private function recommended_ids( int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'              => ReleasePostType::KEY,
				'post_status'            => 'publish',
				'posts_per_page'         => min( 60, max( 16, $limit * 4 ) ),
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'suppress_filters'       => false,
			)
		);
		$posts = is_array( $posts ) ? array_values( array_filter( array_map( 'absint', $posts ) ) ) : array();
		if ( empty( $posts ) ) {
			return array();
		}

		$scored = array();
		foreach ( $posts as $id ) {
			$score = 0;
			if ( has_post_thumbnail( $id ) ) {
				$score += 2;
			}
			$preview = $this->repository->get( $id, 'mw_preview_url' );
			if ( is_string( $preview ) && 'https' === wp_parse_url( $preview, PHP_URL_SCHEME ) ) {
				$score += 3;
			}
			$assets = $this->repository->get( $id, 'mw_download_assets' );
			if ( is_array( $assets ) && ! empty( $assets ) ) {
				$score += 1;
			}
			$scored[ $id ] = $score;
		}
		usort(
			$posts,
			static function ( int $left, int $right ) use ( $scored ): int {
				return $scored[ $right ] <=> $scored[ $left ];
			}
		);

		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Render numbered chart rows with real badges.
	 *
	 * @param array<int, int> $ids Release IDs.
	 */
	private function rows_markup( array $ids, bool $show_rank, bool $show_badges, bool $show_preview ): string {
		$rows     = array();
		$position = 0;
		foreach ( $ids as $id ) {
			++$position;
			$title = get_the_title( $id );
			$link  = get_permalink( $id );
			if ( ! is_string( $link ) || '' === $link ) {
				continue;
			}
			$thumb = get_the_post_thumbnail( $id, 'thumbnail', array( 'class' => 'mw-chart-tabs__art', 'alt' => '' ) );
			if ( '' === $thumb ) {
				$initial = function_exists( 'mb_substr' ) && is_string( $title ) ? mb_substr( $title, 0, 1 ) : substr( (string) $title, 0, 1 );
				$thumb   = '<span class="mw-chart-tabs__placeholder" aria-hidden="true">' . esc_html( (string) $initial ) . '</span>';
			}
			$artists = wp_get_post_terms( $id, 'mw_artist', array( 'fields' => 'names' ) );
			$artist  = is_array( $artists ) && ! empty( $artists ) ? '<span class="mw-chart-tabs__artist">' . esc_html( implode( ', ', $artists ) ) . '</span>' : '';
			$badges  = $show_badges ? ReleaseBadges::markup( ReleaseBadges::for_release( $id, $this->repository, array( 'max_qualities' => 1 ) ) ) : '';
			$preview = '';
			if ( $show_preview ) {
				$preview_url = $this->repository->get( $id, 'mw_preview_url' );
				if ( is_string( $preview_url ) && 'https' === wp_parse_url( $preview_url, PHP_URL_SCHEME ) ) {
					/* translators: %s: release title. */
					$preview = '<button type="button" class="mw-card-play mw-chart-tabs__play" data-mw-release-id="' . esc_attr( (string) $id ) . '" aria-label="' . esc_attr( sprintf( __( 'پخش %s', 'music-wave-core' ), $title ) ) . '"><span aria-hidden="true">▶</span></button>';
				}
			}
			$rank = $show_rank ? '<span class="mw-chart-tabs__rank" aria-hidden="true">' . esc_html( sprintf( '%02d', $position ) ) . '</span>' : '';
			$rows[] = '<li class="mw-chart-tabs__row"><a class="mw-chart-tabs__main" href="' . esc_url( $link ) . '">' . $rank . '<span class="mw-chart-tabs__thumb">' . $thumb . '</span><span class="mw-chart-tabs__titles"><span class="mw-chart-tabs__title">' . esc_html( (string) $title ) . '</span>' . $artist . $badges . '</span></a>' . $preview . '</li>';
		}
		unset( $position );

		return implode( '', $rows );
	}
}
