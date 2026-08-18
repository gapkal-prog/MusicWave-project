<?php
/**
 * WooCommerce purchase ownership adapter.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Commerce;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class PurchaseChecker {
	/** @var ReleaseRepository */
	private $repository;

	public function __construct( ReleaseRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Determine whether a user owns any mapped product.
	 *
	 * WooCommerce's ownership API accepts paid processing/completed orders and
	 * stops accepting refunded/cancelled orders according to Woo order state.
	 */
	public function user_owns_release( int $user_id, int $release_id ): bool {
		if ( $user_id <= 0 || ReleasePostType::KEY !== get_post_type( $release_id ) || ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return false;
		}

		foreach ( $this->repository->product_ids( $release_id ) as $product_id ) {
			if ( wc_customer_bought_product( $user->user_email, $user_id, $product_id ) ) {
				return true;
			}
		}

		return false;
	}
}
