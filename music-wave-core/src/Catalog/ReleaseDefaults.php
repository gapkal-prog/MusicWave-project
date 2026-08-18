<?php
/**
 * Persist administrator defaults on newly created releases.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Catalog;

use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Support\Settings;
use WP_Post;

final class ReleaseDefaults {
	/** @var ReleaseRepository */
	private $releases;

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	public function register(): void {
		add_action( 'wp_after_insert_post', array( $this, 'apply' ), 10, 3 );
	}

	/**
	 * Store defaults once so future settings changes never rewrite old releases.
	 */
	public function apply( int $post_id, WP_Post $post, bool $update ): void {
		if ( $update || ReleasePostType::KEY !== $post->post_type ) {
			return;
		}

		if ( ! metadata_exists( 'post', $post_id, 'mw_access_mode' ) ) {
			$this->releases->update( $post_id, 'mw_access_mode', Settings::get( 'default_access_mode' ) );
		}
		if ( ! metadata_exists( 'post', $post_id, 'mw_preview_duration' ) ) {
			$this->releases->update( $post_id, 'mw_preview_duration', Settings::get( 'default_preview_duration' ) );
		}
	}
}
