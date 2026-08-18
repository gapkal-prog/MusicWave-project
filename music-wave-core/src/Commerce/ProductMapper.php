<?php
/**
 * Bidirectional release/product mapping service.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Commerce;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;

final class ProductMapper {
	public const REVERSE_META_KEY = '_mw_release_ids';

	/**
	 * Keep the private product-side reverse index synchronized.
	 *
	 * @param array<int, int> $old_product_ids Previous mapping.
	 * @param array<int, int> $new_product_ids New mapping.
	 * @return void
	 */
	public function sync_reverse_index( int $release_id, array $old_product_ids, array $new_product_ids ): void {
		$old_product_ids = array_values( array_unique( array_map( 'absint', $old_product_ids ) ) );
		$new_product_ids = array_values( array_unique( array_map( 'absint', $new_product_ids ) ) );

		foreach ( array_diff( $old_product_ids, $new_product_ids ) as $product_id ) {
			$this->remove_release( $product_id, $release_id );
		}

		foreach ( $new_product_ids as $product_id ) {
			if ( 'product' === get_post_type( $product_id ) ) {
				$this->add_release( $product_id, $release_id );
			}
		}
	}

	/** @return array<int, int> */
	public function release_ids( int $product_id ): array {
		$value = get_post_meta( $product_id, self::REVERSE_META_KEY, true );

		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array();
	}

	/**
	 * Remove a deleted product from all release mappings.
	 *
	 * @param int $product_id Deleted post ID.
	 * @return void
	 */
	public function handle_deleted_post( int $product_id ): void {
		if ( 'product' !== get_post_type( $product_id ) && empty( $this->release_ids( $product_id ) ) ) {
			return;
		}

		foreach ( $this->release_ids( $product_id ) as $release_id ) {
			$product_ids = get_post_meta( $release_id, 'mw_product_ids', true );
			$product_ids = is_array( $product_ids ) ? array_map( 'absint', $product_ids ) : array();
			$product_ids = array_values( array_diff( $product_ids, array( $product_id ) ) );
			update_post_meta( $release_id, 'mw_product_ids', $product_ids );
		}
	}

	private function add_release( int $product_id, int $release_id ): void {
		if ( ReleasePostType::KEY !== get_post_type( $release_id ) ) {
			return;
		}

		$release_ids   = $this->release_ids( $product_id );
		$release_ids[] = $release_id;
		update_post_meta( $product_id, self::REVERSE_META_KEY, array_values( array_unique( $release_ids ) ) );
	}

	private function remove_release( int $product_id, int $release_id ): void {
		$release_ids = array_values( array_diff( $this->release_ids( $product_id ), array( $release_id ) ) );
		if ( empty( $release_ids ) ) {
			delete_post_meta( $product_id, self::REVERSE_META_KEY );
			return;
		}

		update_post_meta( $product_id, self::REVERSE_META_KEY, $release_ids );
	}
}
