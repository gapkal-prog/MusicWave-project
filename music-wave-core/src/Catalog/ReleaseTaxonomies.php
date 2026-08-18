<?php
/**
 * Release taxonomy registration.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Catalog;

final class ReleaseTaxonomies {
	private const RELEASE_TYPES_VERSION = '1';

	/**
	 * Taxonomy definitions keyed by taxonomy slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		return array(
			'mw_artist'       => array(
				'singular'     => __( 'Artist', 'music-wave-core' ),
				'plural'       => __( 'Artists', 'music-wave-core' ),
				'hierarchical' => false,
				'slug'         => 'artist',
			),
			'mw_genre'        => array(
				'singular'     => __( 'Genre', 'music-wave-core' ),
				'plural'       => __( 'Genres', 'music-wave-core' ),
				'hierarchical' => true,
				'slug'         => 'genre',
			),
			'mw_mood'         => array(
				'singular'     => __( 'Mood', 'music-wave-core' ),
				'plural'       => __( 'Moods', 'music-wave-core' ),
				'hierarchical' => false,
				'slug'         => 'mood',
			),
			'mw_label'        => array(
				'singular'     => __( 'Label', 'music-wave-core' ),
				'plural'       => __( 'Labels', 'music-wave-core' ),
				'hierarchical' => false,
				'slug'         => 'label',
			),
			'mw_release_type' => array(
				'singular'     => __( 'Release type', 'music-wave-core' ),
				'plural'       => __( 'Release types', 'music-wave-core' ),
				'hierarchical' => true,
				'slug'         => 'release-type',
			),
		);
	}

	/**
	 * Register all catalog taxonomies.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->definitions() as $key => $definition ) {
			register_taxonomy(
				$key,
				ReleasePostType::KEY,
				array(
					'labels'            => array(
						'name'          => $definition['plural'],
						'singular_name' => $definition['singular'],
						'search_items'  => sprintf( /* translators: %s: taxonomy plural name. */ __( 'Search %s', 'music-wave-core' ), $definition['plural'] ),
						'all_items'     => sprintf( /* translators: %s: taxonomy plural name. */ __( 'All %s', 'music-wave-core' ), $definition['plural'] ),
						'edit_item'     => sprintf( /* translators: %s: taxonomy singular name. */ __( 'Edit %s', 'music-wave-core' ), $definition['singular'] ),
						'add_new_item'  => sprintf( /* translators: %s: taxonomy singular name. */ __( 'Add new %s', 'music-wave-core' ), $definition['singular'] ),
					),
					'public'            => true,
					'hierarchical'      => $definition['hierarchical'],
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array( 'slug' => $definition['slug'] ),
				)
			);
		}

		$this->ensure_default_release_types();
	}

	/**
	 * Seed the canonical release classifications once without overwriting
	 * merchant-managed terms.
	 *
	 * @return void
	 */
	private function ensure_default_release_types(): void {
		if ( self::RELEASE_TYPES_VERSION === (string) get_option( 'music_wave_release_types_version', '' ) ) {
			return;
		}

		$types = array(
			'track'           => __( 'Track', 'music-wave-core' ),
			'single'          => __( 'Single', 'music-wave-core' ),
			'ep'              => __( 'EP', 'music-wave-core' ),
			'album'           => __( 'Album', 'music-wave-core' ),
			'mix'             => __( 'Mix', 'music-wave-core' ),
			'playlist'        => __( 'Playlist', 'music-wave-core' ),
			'podcast_show'    => __( 'Podcast show', 'music-wave-core' ),
			'podcast_episode' => __( 'Podcast episode', 'music-wave-core' ),
		);

		foreach ( $types as $slug => $name ) {
			if ( null === term_exists( $slug, 'mw_release_type' ) ) {
				wp_insert_term( $name, 'mw_release_type', array( 'slug' => $slug ) );
			}
		}

		update_option( 'music_wave_release_types_version', self::RELEASE_TYPES_VERSION, false );
	}
}
