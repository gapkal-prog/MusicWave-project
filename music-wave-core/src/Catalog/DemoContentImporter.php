<?php
/**
 * Safe, idempotent MusicWave demo catalog importer.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Catalog;

use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class DemoContentImporter {
	private const DEMO_META_KEY = '_mw_demo_content';

	/** @var ReleaseRepository */
	private $releases;

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	/**
	 * Import a public sample catalog without modifying merchant content.
	 *
	 * @return array<string, int|array<int, string>>
	 */
	public function import(): array {
		$result = array(
			'created'  => 0,
			'existing' => 0,
			'skipped'  => 0,
			'removed'  => 0,
			'messages' => array(),
		);
		$this->ensure_terms( $result );

		$definitions = array(
			'glass-current'         => array(
				'title'   => __( 'Glass Current', 'music-wave-core' ),
				'excerpt' => __( 'A focused electronic demo track for testing catalog cards, metadata, and previews.', 'music-wave-core' ),
				'content' => __( 'Glass Current is a short public demo track included with MusicWave. Replace it with licensed catalog content before launch.', 'music-wave-core' ),
				'types'   => array( 'track' ),
				'artists' => array( 'sora-vale' ),
				'genres'  => array( 'electronic' ),
				'moods'   => array( 'focus' ),
				'meta'    => array(
					'mw_catalog_number' => 'MW-DEMO-001',
					'mw_release_date'   => '2026-01-16',
					'mw_duration'       => 224,
					'mw_bpm'            => 122,
					'mw_musical_key'    => 'A minor',
					'mw_track_number'   => 1,
					'mw_access_mode'    => 'public',
				),
			),
			'horizon-parade'        => array(
				'title'   => __( 'Horizon Parade', 'music-wave-core' ),
				'excerpt' => __( 'A sample album that demonstrates ordered track relationships and album rendering.', 'music-wave-core' ),
				'content' => __( 'Horizon Parade is a public sample album. It connects to a sample track through MusicWave collection relationships.', 'music-wave-core' ),
				'types'   => array( 'album' ),
				'artists' => array( 'sora-vale' ),
				'genres'  => array( 'electronic' ),
				'moods'   => array( 'focus' ),
				'meta'    => array(
					'mw_catalog_number' => 'MW-DEMO-002',
					'mw_release_date'   => '2026-02-07',
					'mw_duration'       => 224,
					'mw_access_mode'    => 'public',
				),
			),
			'signal-after-midnight' => array(
				'title'   => __( 'Signal After Midnight', 'music-wave-core' ),
				'excerpt' => __( 'A sample podcast show for testing episode collections and podcast schema.', 'music-wave-core' ),
				'content' => __( 'Signal After Midnight is a public sample podcast show. It demonstrates how MusicWave groups episodes into an ordered collection.', 'music-wave-core' ),
				'types'   => array( 'podcast_show' ),
				'artists' => array( 'midnight-signal' ),
				'genres'  => array( 'spoken-word' ),
				'moods'   => array( 'focus' ),
				'meta'    => array(
					'mw_catalog_number' => 'MW-DEMO-003',
					'mw_release_date'   => '2026-03-04',
					'mw_duration'       => 642,
					'mw_access_mode'    => 'public',
				),
			),
			'first-transmission'    => array(
				'title'   => __( 'First Transmission', 'music-wave-core' ),
				'excerpt' => __( 'Episode one of the public sample podcast.', 'music-wave-core' ),
				'content' => __( 'This public sample episode is included to demonstrate podcast episode metadata and collection ordering.', 'music-wave-core' ),
				'types'   => array( 'podcast_episode' ),
				'artists' => array( 'midnight-signal' ),
				'genres'  => array( 'spoken-word' ),
				'moods'   => array( 'focus' ),
				'meta'    => array(
					'mw_catalog_number' => 'MW-DEMO-004',
					'mw_release_date'   => '2026-03-04',
					'mw_duration'       => 642,
					'mw_episode_number' => 1,
					'mw_season_number'  => 1,
					'mw_access_mode'    => 'public',
				),
			),
		);
		$ids         = array();

		foreach ( $definitions as $slug => $definition ) {
			$id = $this->upsert_release( $slug, $definition, $result );
			if ( $id > 0 ) {
				$ids[ $slug ] = $id;
			}
		}

		$this->sync_collection( $ids, 'horizon-parade', 'glass-current', 'track', $result );
		$this->sync_collection( $ids, 'signal-after-midnight', 'first-transmission', 'episode', $result );

		return $result;
	}

	/**
	 * Delete only posts created by this importer. Terms remain for safety.
	 *
	 * @return array<string, int|array<int, string>>
	 */
	public function remove(): array {
		$result = array(
			'created'  => 0,
			'existing' => 0,
			'skipped'  => 0,
			'removed'  => 0,
			'messages' => array(),
		);
		$posts  = get_posts(
			array(
				'post_type'      => ReleasePostType::KEY,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META_KEY,
				'meta_value'     => '1',
			)
		);

		foreach ( is_array( $posts ) ? $posts : array() as $post_id ) {
			if ( false !== wp_delete_post( absint( $post_id ), true ) ) {
				++$result['removed'];
			}
		}
		$result['messages'][] = __( 'Only MusicWave sample releases were removed. Existing taxonomy terms were intentionally preserved.', 'music-wave-core' );

		return $result;
	}

	public function has_demo_content(): bool {
		$posts = get_posts(
			array(
				'post_type'      => ReleasePostType::KEY,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META_KEY,
				'meta_value'     => '1',
			)
		);

		return ! empty( $posts );
	}

	/**
	 * Ensure the demo taxonomy terms exist before creating releases.
	 *
	 * @param array{created: int, existing: int, skipped: int, removed: int, messages: array<int, string>} $result Import result.
	 */
	private function ensure_terms( array &$result ): void {
		$terms = array(
			'mw_artist' => array(
				'sora-vale'       => __( 'Sora Vale', 'music-wave-core' ),
				'midnight-signal' => __( 'Midnight Signal', 'music-wave-core' ),
			),
			'mw_genre'  => array(
				'electronic'  => __( 'Electronic', 'music-wave-core' ),
				'spoken-word' => __( 'Spoken word', 'music-wave-core' ),
			),
			'mw_mood'   => array(
				'focus' => __( 'Focus', 'music-wave-core' ),
			),
		);

		foreach ( $terms as $taxonomy => $values ) {
			foreach ( $values as $slug => $name ) {
				if ( ! term_exists( $slug, $taxonomy ) ) {
					$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
					if ( is_wp_error( $created ) ) {
						$result['messages'][] = sprintf(
							/* translators: %s: demo taxonomy term name. */
							__( 'Could not create the demo term %s.', 'music-wave-core' ),
							$name
						);
					}
				}
			}
		}
	}

	/**
	 * Create or refresh a single demo release.
	 *
	 * @param string                                                                                       $slug       Release URL slug.
	 * @param array<string, mixed>                                                                         $definition Release seed data.
	 * @param array{created: int, existing: int, skipped: int, removed: int, messages: array<int, string>} $result     Import result.
	 */
	private function upsert_release( string $slug, array $definition, array &$result ): int {
		$existing = get_page_by_path( $slug, OBJECT, ReleasePostType::KEY );
		if ( null !== $existing && ! get_post_meta( $existing->ID, self::DEMO_META_KEY, true ) ) {
			++$result['skipped'];
			$result['messages'][] = sprintf(
				/* translators: %s: demo release URL slug. */
				__( 'Skipped demo release %s because a merchant release already uses its URL slug.', 'music-wave-core' ),
				$slug
			);

			return 0;
		}

		if ( null === $existing ) {
			$release_id = wp_insert_post(
				array(
					'post_type'    => ReleasePostType::KEY,
					'post_status'  => 'publish',
					'post_title'   => $definition['title'],
					'post_name'    => $slug,
					'post_excerpt' => $definition['excerpt'],
					'post_content' => $definition['content'],
				),
				true
			);
			if ( is_wp_error( $release_id ) || $release_id < 1 ) {
				++$result['skipped'];
				$result['messages'][] = sprintf(
					/* translators: %s: demo release URL slug. */
					__( 'Could not create demo release %s.', 'music-wave-core' ),
					$slug
				);

				return 0;
			}
			update_post_meta( $release_id, self::DEMO_META_KEY, '1' );
			++$result['created'];
		} else {
			$release_id = $existing->ID;
			++$result['existing'];
		}

		$this->assign_terms( $release_id, 'mw_release_type', $definition['types'] );
		$this->assign_terms( $release_id, 'mw_artist', $definition['artists'] );
		$this->assign_terms( $release_id, 'mw_genre', $definition['genres'] );
		$this->assign_terms( $release_id, 'mw_mood', $definition['moods'] );
		foreach ( $definition['meta'] as $key => $value ) {
			$this->releases->update( $release_id, $key, $value );
		}

		return $release_id;
	}

	/**
	 * Assign the demo taxonomy terms to a release.
	 *
	 * @param int                $release_id Release post ID.
	 * @param string             $taxonomy   Taxonomy key.
	 * @param array<int, string> $slugs      Term slugs.
	 */
	private function assign_terms( int $release_id, string $taxonomy, array $slugs ): void {
		wp_set_object_terms( $release_id, $slugs, $taxonomy, false );
	}

	/**
	 * Persist the ordered child relationship of a demo collection.
	 *
	 * @param array<string, int>                                                                           $ids           Imported release IDs keyed by slug.
	 * @param string                                                                                       $collection_slug Collection slug.
	 * @param string                                                                                       $child_slug      Child release slug.
	 * @param string                                                                                       $role            Collection role for the child.
	 * @param array{created: int, existing: int, skipped: int, removed: int, messages: array<int, string>} $result         Import result.
	 */
	private function sync_collection( array $ids, string $collection_slug, string $child_slug, string $role, array &$result ): void {
		if ( ! isset( $ids[ $collection_slug ], $ids[ $child_slug ] ) ) {
			return;
		}

		try {
			$this->releases->replace_collection_items(
				$ids[ $collection_slug ],
				array(
					array(
						'release_id' => $ids[ $child_slug ],
						'position'   => 1,
						'role'       => $role,
					),
				)
			);
		} catch ( \InvalidArgumentException $exception ) {
			$result['messages'][] = $exception->getMessage();
		}
	}
}
