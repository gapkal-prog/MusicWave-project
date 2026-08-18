<?php
/**
 * Shared release publication and read-visibility policy.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Catalog;

final class ReleaseVisibility {
	/**
	 * Whether an ID belongs to a MusicWave release.
	 */
	public function is_release( int $release_id ): bool {
		return $release_id > 0 && ReleasePostType::KEY === get_post_type( $release_id );
	}

	/**
	 * Whether a release may appear in public, cacheable projections.
	 */
	public function is_public( int $release_id ): bool {
		return $this->is_release( $release_id ) && 'publish' === get_post_status( $release_id );
	}

	/**
	 * Whether the current request actor may read the release record.
	 *
	 * Published releases are publicly readable. Non-published releases require
	 * WordPress' object-level read capability so drafts/private releases are not
	 * enumerated through MusicWave library, SEO, or REST helper paths.
	 */
	public function can_read( int $release_id ): bool {
		if ( ! $this->is_release( $release_id ) ) {
			return false;
		}
		if ( $this->is_public( $release_id ) ) {
			return true;
		}

		return current_user_can( 'read_post', $release_id );
	}
}
