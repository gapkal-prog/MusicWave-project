<?php
/**
 * Read-only MusicWave mapping panel on WooCommerce products.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Commerce\ProductMapper;
use WP_Post;

final class ProductReleasePanel {
	/** @var ProductMapper */
	private $mapper;

	public function __construct( ProductMapper $mapper ) {
		$this->mapper = $mapper;
	}

	public function register(): void {
		if ( ! post_type_exists( 'product' ) ) {
			return;
		}

		add_meta_box( 'music-wave-product-releases', __( 'MusicWave releases', 'music-wave-core' ), array( $this, 'render' ), 'product', 'side', 'default' );
	}

	public function render( WP_Post $post ): void {
		$release_ids = $this->mapper->release_ids( $post->ID );
		if ( empty( $release_ids ) ) {
			echo '<p>' . esc_html__( 'No releases are mapped to this product.', 'music-wave-core' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $release_ids as $release_id ) {
			$link = get_edit_post_link( $release_id );
			if ( null === $link ) {
				continue;
			}
			echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $release_id ) ) . '</a></li>';
		}
		echo '</ul>';
	}
}
