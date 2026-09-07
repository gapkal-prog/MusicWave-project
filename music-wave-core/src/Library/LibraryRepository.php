<?php
/**
 * Personal music library persistence for signed-in customers.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Library;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Catalog\ReleaseVisibility;
use WP_Term;

final class LibraryRepository {
	public const META_KEY  = 'mw_music_library';
	public const MAX_ITEMS = 500;

	public const TYPE_RELEASE  = 'release';
	public const TYPE_ARTIST   = 'artist';
	public const TYPE_WISHLIST = 'wishlist';
	public const TYPE_PRESAVE  = 'presave';

	public const RELEASE_DATE_META = 'mw_release_date';

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
		return array( self::TYPE_RELEASE, self::TYPE_ARTIST, self::TYPE_WISHLIST, self::TYPE_PRESAVE );
	}

	/**
	 * Item types that reference a release post.
	 *
	 * @return array<int, string>
	 */
	public function release_types(): array {
		return array( self::TYPE_RELEASE, self::TYPE_WISHLIST, self::TYPE_PRESAVE );
	}

	/**
	 * Users who stored one specific item, used for pre-save fulfillment.
	 *
	 * Bounded by the number of accounts that own a library; callers pass a
	 * concrete target, so no unbounded catalog scan is required.
	 *
	 * @return array<int, int>
	 */
	public function users_with( string $type, int $item_id ): array {
		global $wpdb;

		if ( $item_id < 1 || ! in_array( $type, $this->types(), true ) ) {
			return array();
		}
		if ( ! $wpdb instanceof \wpdb ) {
			return array();
		}

		// Serialized items always store `type` as a string field and `id` as
		// an int or numeric-string field, so exact field patterns narrow the
		// candidates in SQL instead of deserializing every library. False
		// positives are possible across items of one library; has() below
		// stays the authoritative check.
		$type_pattern = '%' . $wpdb->esc_like( 's:4:"type";s:' . strlen( $type ) . ':"' . $type . '";' ) . '%';
		$int_pattern  = '%' . $wpdb->esc_like( 's:2:"id";i:' . $item_id . ';' ) . '%';
		$str_pattern  = '%' . $wpdb->esc_like( 's:2:"id";s:' . strlen( (string) $item_id ) . ':"' . $item_id . '";' ) . '%';
		$sql          = "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s AND ( meta_value LIKE %s OR meta_value LIKE %s )";
		$params       = array( self::META_KEY, $type_pattern, $int_pattern, $str_pattern );

		$candidates = $wpdb->get_col( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		$matched = array();
		foreach ( is_array( $candidates ) ? $candidates : array() as $candidate ) {
			$user_id = absint( $candidate );
			if ( $user_id > 0 && $this->has( $user_id, $type, $item_id ) ) {
				$matched[] = $user_id;
			}
		}

		return $matched;
	}

	/**
	 * Release date stored for one release as a UTC timestamp, or 0.
	 */
	public function release_timestamp( int $release_id ): int {
		$stored = get_post_meta( $release_id, self::RELEASE_DATE_META, true );
		if ( ! is_scalar( $stored ) || '' === (string) $stored ) {
			return 0;
		}

		$timestamp = strtotime( (string) $stored . ' 00:00:00 UTC' );

		return is_int( $timestamp ) && $timestamp > 0 ? $timestamp : 0;
	}

	/**
	 * Whether a release is announced but not yet available.
	 *
	 * Pre-saves only apply to upcoming releases: a readable release page with a
	 * release date still in the future. Unpublished records stay out of reach,
	 * so pre-save cannot be used to probe unreleased catalog data
	 * (PROJECT_PLAN.md Stage 5 deliverable 5).
	 */
	public function is_upcoming( int $release_id ): bool {
		$timestamp = $this->release_timestamp( $release_id );

		return $timestamp > time();
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
		// Library targets only ever reference releases; without this guard
		// every unrelated post deletion (pages, revisions, ...) would trigger
		// three full library sweeps.
		if ( $post_id < 1 || ReleasePostType::KEY !== get_post_type( $post_id ) ) {
			return;
		}
		foreach ( $this->release_types() as $type ) {
			$this->purge_target( $type, $post_id );
		}
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
		if ( self::TYPE_PRESAVE === $type ) {
			return $this->visibility->can_read( $item_id ) && $this->is_upcoming( $item_id );
		}
		if ( in_array( $type, array( self::TYPE_RELEASE, self::TYPE_WISHLIST ), true ) ) {
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
		$this->cache = array();
		foreach ( $this->users_with( $type, $item_id ) as $user_id ) {
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
