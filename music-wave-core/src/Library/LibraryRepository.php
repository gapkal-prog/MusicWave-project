<?php
/**
 * Personal music library persistence for signed-in customers.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Library;

use ManaCore\MusicWave\Core\Catalog\ReleaseVisibility;
use WP_Term;

final class LibraryRepository {
	public const META_KEY  = 'mw_music_library';
	public const MAX_ITEMS = 500;

	public const TYPE_RELEASE = 'release';
	public const TYPE_ARTIST  = 'artist';

	/** @var array<int, array<int, array<string, mixed>>> */
	private $cache = array();

	/** @var ReleaseVisibility */
	private $visibility;

	public function __construct( ?ReleaseVisibility $visibility = null ) {
		$this->visibility = null !== $visibility ? $visibility : new ReleaseVisibility();
	}

	/**
	 * Register the library hooks used to keep stored items consistent.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( $this, 'handle_deleted_post' ) );
		add_action( 'delete_term', array( $this, 'handle_deleted_term' ), 10, 4 );
	}

	/**
	 * Return every supported library item type.
	 *
	 * @return array<int, string>
	 */
	public function types(): array {
		return array( self::TYPE_RELEASE, self::TYPE_ARTIST );
	}

	/**
	 * Return the normalized library for a user, most recently added first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all( int $user_id ): array {
		if ( $user_id < 1 ) {
			return array();
		}

		if ( isset( $this->cache[ $user_id ] ) ) {
			$items = $this->cache[ $user_id ];
		} else {
			$stored = get_user_meta( $user_id, self::META_KEY, true );
			$stored = is_array( $stored ) ? $stored : array();
			$items  = array();

			foreach ( $stored as $item ) {
				$normalized = $this->normalize_item( $item );
				if ( null !== $normalized ) {
					$items[] = $normalized;
				}
			}

			usort(
				$items,
				static function ( array $left, array $right ): int {
					return (int) $right['added'] <=> (int) $left['added'];
				}
			);

			$this->cache[ $user_id ] = $items;
		}

		/**
		 * Filter a customer's personal library items.
		 *
		 * @param array $items   Normalized library items.
		 * @param int   $user_id Library owner.
		 */
		$filtered = apply_filters( 'music_wave_library_items', $items, $user_id );

		return is_array( $filtered ) ? $filtered : $items;
	}

	/**
	 * Check whether one item is already stored in a user's library.
	 */
	public function has( int $user_id, string $type, int $item_id ): bool {
		foreach ( $this->all( $user_id ) as $item ) {
			if ( $item['type'] === $type && (int) $item['id'] === $item_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add one validated item to a user's library.
	 *
	 * @return bool True when the item was stored, false when it was
	 *              invalid, duplicated, or the library is full.
	 */
	public function add( int $user_id, string $type, int $item_id ): bool {
		if ( $user_id < 1 || $item_id < 1 || ! in_array( $type, $this->types(), true ) ) {
			return false;
		}

		if ( $this->has( $user_id, $type, $item_id ) || ! $this->target_exists( $type, $item_id ) ) {
			return false;
		}

		$items = $this->all( $user_id );
		if ( count( $items ) >= self::MAX_ITEMS ) {
			return false;
		}

		$items[] = array(
			'type'  => $type,
			'id'    => $item_id,
			'added' => time(),
		);

		return $this->persist( $user_id, $items );
	}

	/**
	 * Remove one item from a user's library.
	 */
	public function remove( int $user_id, string $type, int $item_id ): bool {
		if ( $user_id < 1 || ! $this->has( $user_id, $type, $item_id ) ) {
			return false;
		}

		$items = array_values(
			array_filter(
				$this->all( $user_id ),
				static function ( array $item ) use ( $type, $item_id ): bool {
					return $item['type'] !== $type || (int) $item['id'] !== $item_id;
				}
			)
		);

		return $this->persist( $user_id, $items );
	}

	/**
	 * Return stored item IDs of one type, most recently added first.
	 *
	 * @return array<int, int>
	 */
	public function ids( int $user_id, string $type ): array {
		$ids = array();
		foreach ( $this->all( $user_id ) as $item ) {
			if ( $item['type'] === $type ) {
				$ids[] = (int) $item['id'];
			}
		}

		return $ids;
	}

	/**
	 * Count the total number of stored library items.
	 */
	public function count( int $user_id ): int {
		return count( $this->all( $user_id ) );
	}

	/**
	 * Drop a deleted release from every library that still stores it.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public function handle_deleted_post( int $post_id ): void {
		$this->purge_target( self::TYPE_RELEASE, $post_id );
	}

	/**
	 * Drop a deleted artist term from every library that still stores it.
	 *
	 * @param int    $term_id  Deleted term ID.
	 * @param int    $unused   Term taxonomy ID (unused).
	 * @param string $taxonomy Deleted term taxonomy.
	 * @param mixed  $deleted  Deleted term object (unused).
	 * @return void
	 */
	public function handle_deleted_term( int $term_id, int $unused, string $taxonomy, $deleted = null ): void {
		unset( $unused, $deleted );
		if ( 'mw_artist' === $taxonomy ) {
			$this->purge_target( self::TYPE_ARTIST, $term_id );
		}
	}

	/**
	 * Verify that the referenced catalog object exists.
	 */
	private function target_exists( string $type, int $item_id ): bool {
		if ( self::TYPE_RELEASE === $type ) {
			return $this->visibility->can_read( $item_id );
		}

		$term = get_term( $item_id, 'mw_artist' );

		return $term instanceof WP_Term;
	}

	/**
	 * Remove one target from every user library that contains it.
	 *
	 * @return void
	 */
	private function purge_target( string $type, int $item_id ): void {
		global $wpdb;

		$this->cache = array();
		if ( ! isset( $wpdb ) || ! property_exists( $wpdb, 'usermeta' ) ) {
			return;
		}

		$user_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", self::META_KEY ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);

		foreach ( is_array( $user_ids ) ? $user_ids : array() as $user_id ) {
			$user_id = absint( $user_id );
			if ( $user_id < 1 || ! $this->has( $user_id, $type, $item_id ) ) {
				continue;
			}
			$this->remove( $user_id, $type, $item_id );
		}
	}

	/**
	 * Persist and cache a complete library list.
	 */
	private function persist( int $user_id, array $items ): bool {
		$result                  = update_user_meta( $user_id, self::META_KEY, array_values( $items ) );
		$this->cache[ $user_id ] = array_values( $items );

		return false !== $result;
	}

	/**
	 * Normalize one stored item, rejecting malformed rows.
	 *
	 * @param mixed $item Raw stored value.
	 * @return array<string, mixed>|null
	 */
	private function normalize_item( $item ): ?array {
		if ( ! is_array( $item ) ) {
			return null;
		}

		$type = isset( $item['type'] ) && is_scalar( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
		$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		if ( ! in_array( $type, $this->types(), true ) || $id < 1 ) {
			return null;
		}

		return array(
			'type'  => $type,
			'id'    => $id,
			'added' => isset( $item['added'] ) ? absint( $item['added'] ) : 0,
		);
	}
}
