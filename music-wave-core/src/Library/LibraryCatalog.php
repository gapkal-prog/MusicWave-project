<?php
/**
 * Resolves stored personal library items into display-ready summaries.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Library;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseVisibility;
use WP_Term;

final class LibraryCatalog {
	/** @var LibraryRepository */
	private $repository;

	/** @var ReleaseVisibility */
	private $visibility;

	public function __construct( LibraryRepository $repository, ?ReleaseVisibility $visibility = null ) {
		$this->repository = $repository;
		$this->visibility = null !== $visibility ? $visibility : new ReleaseVisibility();
	}

	/**
	 * Return the library filter key used for the full collection.
	 */
	public const FILTER_ALL = 'all';

	/**
	 * Return the library filter key grouping followed artists.
	 */
	public const FILTER_ARTISTS = 'artists';

	/**
	 * Summarize library items for display, optionally narrowed to one filter.
	 *
	 * Filters are "all", "artists", or any release-type term slug.
	 *
	 * @param int    $user_id Library owner.
	 * @param string $filter  Active library filter key.
	 * @param int    $limit   Maximum number of items returned.
	 * @return array<int, array<string, mixed>>
	 */
	public function summaries( int $user_id, string $filter = self::FILTER_ALL, int $limit = 24 ): array {
		$summaries = array();
		$limit     = min( 100, max( 1, $limit ) );

		foreach ( $this->repository->all( $user_id ) as $item ) {
			if ( count( $summaries ) >= $limit ) {
				break;
			}

			$summary = LibraryRepository::TYPE_RELEASE === $item['type']
				? $this->release_summary( (int) $item['id'], (int) $item['added'] )
				: $this->artist_summary( (int) $item['id'], (int) $item['added'] );

			if ( null === $summary || ! $this->matches_filter( $summary, $filter ) ) {
				continue;
			}

			$summaries[] = $summary;
		}

		return $summaries;
	}

	/**
	 * Count library items per filter key for tab rendering.
	 *
	 * Only filters with at least one item are included, plus "all".
	 *
	 * @return array<string, int>
	 */
	public function counts( int $user_id ): array {
		$counts = array( self::FILTER_ALL => 0 );

		foreach ( $this->repository->all( $user_id ) as $item ) {
			++$counts[ self::FILTER_ALL ];

			if ( LibraryRepository::TYPE_ARTIST === $item['type'] ) {
				$counts[ self::FILTER_ARTISTS ] = isset( $counts[ self::FILTER_ARTISTS ] ) ? $counts[ self::FILTER_ARTISTS ] + 1 : 1;
				continue;
			}

			foreach ( $this->release_type_slugs( (int) $item['id'] ) as $slug ) {
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}

		return $counts;
	}

	/**
	 * Return translated labels for known filter keys.
	 *
	 * @param array<string, int> $counts Filter counts keyed by filter key.
	 * @return array<string, string>
	 */
	public function filter_labels( array $counts ): array {
		$labels = array(
			self::FILTER_ALL     => __( 'All', 'music-wave-core' ),
			self::FILTER_ARTISTS => __( 'Artists', 'music-wave-core' ),
		);

		foreach ( array_keys( $counts ) as $key ) {
			if ( isset( $labels[ $key ] ) || self::FILTER_ALL === $key ) {
				continue;
			}

			$term = get_term_by( 'slug', (string) $key, 'mw_release_type' );
			if ( $term instanceof WP_Term ) {
				$labels[ $key ] = $term->name;
			}
		}

		return $labels;
	}

	/**
	 * Build one summary for a stored release item.
	 *
	 * @return array<string, mixed>|null
	 */
	private function release_summary( int $release_id, int $added ): ?array {
		if ( ReleasePostType::KEY !== get_post_type( $release_id ) || ! $this->visibility->can_read( $release_id ) ) {
			return null;
		}

		$artists = wp_get_post_terms( $release_id, 'mw_artist', array( 'fields' => 'names' ) );
		$artist  = is_array( $artists ) && ! empty( $artists ) ? implode( ', ', array_map( 'strval', $artists ) ) : '';
		$types   = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'names' ) );
		$type    = is_array( $types ) && ! empty( $types ) ? (string) $types[0] : __( 'Release', 'music-wave-core' );
		$title   = get_the_title( $release_id );
		$link    = get_permalink( $release_id );
		$year    = get_post_meta( $release_id, 'mw_release_year', true );

		return array(
			'type'       => LibraryRepository::TYPE_RELEASE,
			'id'         => $release_id,
			'title'      => '' !== $title ? $title : __( 'Untitled release', 'music-wave-core' ),
			'url'        => is_string( $link ) ? $link : '',
			'image'      => get_the_post_thumbnail(
				$release_id,
				'thumbnail',
				array(
					'class' => 'mw-music-library__image',
					'alt'   => '',
				)
			),
			'subtitle'   => $artist,
			'type_label' => $type,
			'year'       => is_scalar( $year ) && (int) $year > 0 ? (string) (int) $year : '',
			'added'      => $added,
			'type_slugs' => $this->release_type_slugs( $release_id ),
		);
	}

	/**
	 * Build one summary for a stored artist item.
	 *
	 * @return array<string, mixed>|null
	 */
	private function artist_summary( int $term_id, int $added ): ?array {
		$term = get_term( $term_id, 'mw_artist' );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$image_id = absint( get_term_meta( $term_id, 'mw_artist_image_id', true ) );
		$image    = $image_id > 0
			? wp_get_attachment_image(
				$image_id,
				'thumbnail',
				false,
				array(
					'class' => 'mw-music-library__image mw-music-library__image--artist',
					'alt'   => '',
				)
			)
			: '';
		$link     = get_term_link( $term );

		return array(
			'type'       => LibraryRepository::TYPE_ARTIST,
			'id'         => $term_id,
			'title'      => $term->name,
			'url'        => ! is_wp_error( $link ) && is_string( $link ) ? $link : '',
			'image'      => $image,
			'subtitle'   => '',
			'type_label' => __( 'Artist', 'music-wave-core' ),
			'year'       => '',
			'added'      => $added,
			'type_slugs' => array( self::FILTER_ARTISTS ),
		);
	}

	/**
	 * Release type slugs bound to one release, used for tab filtering.
	 *
	 * @return array<int, string>
	 */
	private function release_type_slugs( int $release_id ): array {
		$terms = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'slugs' ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'strval', $terms ),
				static function ( string $slug ): bool {
					return '' !== $slug;
				}
			)
		);
	}

	/**
	 * Decide whether one summary belongs to the active filter.
	 *
	 * @param array<string, mixed> $summary Item summary.
	 * @param string               $filter  Active filter key.
	 */
	private function matches_filter( array $summary, string $filter ): bool {
		if ( self::FILTER_ALL === $filter || '' === $filter ) {
			return true;
		}

		$slugs = isset( $summary['type_slugs'] ) && is_array( $summary['type_slugs'] ) ? $summary['type_slugs'] : array();

		return in_array( $filter, $slugs, true );
	}
}
