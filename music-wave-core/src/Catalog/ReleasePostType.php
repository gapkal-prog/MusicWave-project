<?php
/**
 * Release post type registration.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Catalog;

final class ReleasePostType {
	public const KEY = 'mw_release';

	/** @return array<int, string> */
	public static function primitive_capabilities(): array {
		return array(
			'edit_mw_release',
			'read_mw_release',
			'delete_mw_release',
			'edit_mw_releases',
			'edit_others_mw_releases',
			'publish_mw_releases',
			'read_private_mw_releases',
			'delete_mw_releases',
			'delete_private_mw_releases',
			'delete_published_mw_releases',
			'delete_others_mw_releases',
			'edit_private_mw_releases',
			'edit_published_mw_releases',
		);
	}

	/**
	 * Register the canonical release post type.
	 *
	 * @return void
	 */
	public function register(): void {
		$labels = array(
			'name'                  => _x( 'Releases', 'post type general name', 'music-wave-core' ),
			'singular_name'         => _x( 'Release', 'post type singular name', 'music-wave-core' ),
			'add_new_item'          => __( 'Add new release', 'music-wave-core' ),
			'edit_item'             => __( 'Edit release', 'music-wave-core' ),
			'new_item'              => __( 'New release', 'music-wave-core' ),
			'view_item'             => __( 'View release', 'music-wave-core' ),
			'search_items'          => __( 'Search releases', 'music-wave-core' ),
			'not_found'             => __( 'No releases found.', 'music-wave-core' ),
			'not_found_in_trash'    => __( 'No releases found in Trash.', 'music-wave-core' ),
			'all_items'             => __( 'All releases', 'music-wave-core' ),
			'archives'              => __( 'Release archives', 'music-wave-core' ),
			'featured_image'        => __( 'Cover artwork', 'music-wave-core' ),
			'set_featured_image'    => __( 'Set cover artwork', 'music-wave-core' ),
			'remove_featured_image' => __( 'Remove cover artwork', 'music-wave-core' ),
			'menu_name'             => __( 'MusicWave', 'music-wave-core' ),
		);

		register_post_type(
			self::KEY,
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'music' ),
				'menu_icon'           => 'dashicons-album',
				'menu_position'       => 25,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
				'template_lock'       => false,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => false,
				'map_meta_cap'        => true,
				'capability_type'     => array( 'mw_release', 'mw_releases' ),
				'capabilities'        => array( 'create_posts' => 'edit_mw_releases' ),
			)
		);
	}
}
