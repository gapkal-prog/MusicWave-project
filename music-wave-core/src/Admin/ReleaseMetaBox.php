<?php
/**
 * Schema-driven release metadata editor.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Commerce\ProductMapper;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use ManaCore\MusicWave\Core\Schema\MetaDefinition;
use ManaCore\MusicWave\Core\Schema\ReleaseMetaSchema;
use WP_Post;

final class ReleaseMetaBox {
	private const NONCE_ACTION = 'music_wave_save_release';
	private const NONCE_NAME   = 'music_wave_release_nonce';
	private const NOTICE_KEY   = 'music_wave_release_notice_';

	/** @var ReleaseMetaSchema */
	private $schema;

	/** @var ReleaseRepository */
	private $repository;

	/** @var ProductMapper */
	private $product_mapper;

	public function __construct( ReleaseMetaSchema $schema, ReleaseRepository $repository, ProductMapper $product_mapper ) {
		$this->schema         = $schema;
		$this->repository     = $repository;
		$this->product_mapper = $product_mapper;
	}

	public function register(): void {
		add_meta_box( 'music-wave-release-details', __( 'MusicWave release details', 'music-wave-core' ), array( $this, 'render' ), ReleasePostType::KEY, 'normal', 'high' );
	}

	public function enqueue_assets(): void {
		$screen = get_current_screen();
		if ( null === $screen || ReleasePostType::KEY !== $screen->post_type || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}

	public function render_notice(): void {
		$user_id = get_current_user_id();
		$notice  = get_transient( self::NOTICE_KEY . $user_id );
		if ( ! is_string( $notice ) || '' === $notice ) {
			return;
		}

		delete_transient( self::NOTICE_KEY . $user_id );
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<div class="mw-admin-fields">';
		echo '<p class="description">' . esc_html__( 'Catalog data is independent from WooCommerce. Products only sell or grant access to this release.', 'music-wave-core' ) . '</p>';

		foreach ( $this->schema->admin_fields() as $definition ) {
			if ( in_array( $definition->admin()['type'] ?? '', array( 'protected_asset', 'protected_assets' ), true ) ) {
				continue;
			}
			$this->render_field( $definition, $this->repository->get( $post->ID, $definition->key() ) );
		}

		if ( $post->ID > 0 ) {
			$types         = wp_get_post_terms( $post->ID, 'mw_release_type', array( 'fields' => 'slugs' ) );
			$types         = is_array( $types ) ? array_map( 'sanitize_key', $types ) : array();
			$is_collection = ! empty( array_intersect( array( 'album', 'ep', 'mix', 'playlist', 'podcast_show' ), $types ) );
			echo '<hr class="mw-release-details-divider">';
			echo '<div id="music-wave-delivery-manager" data-release-id="' . esc_attr( (string) $post->ID ) . '" data-is-collection="' . esc_attr( $is_collection ? '1' : '0' ) . '"></div>';
			if ( $is_collection ) {
				$role = in_array( 'podcast_show', $types, true ) ? 'episode' : 'track';
				echo '<div id="music-wave-collection-manager" data-release-id="' . esc_attr( (string) $post->ID ) . '" data-role="' . esc_attr( $role ) . '"></div>';
			}
		} else {
			echo '<p class="description">' . esc_html__( 'Save this release once to add protected files and collection items.', 'music-wave-core' ) . '</p>';
		}

		echo '</div>';
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( ReleasePostType::KEY !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_string( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$old_product_ids = $this->repository->product_ids( $post_id );
		foreach ( $this->schema->admin_fields() as $definition ) {
			$key = $definition->key();
			if ( ! array_key_exists( $key, $_POST ) ) {
				if ( 'mw_explicit' === $key ) {
					$this->repository->update( $post_id, $key, false );
				}
				continue;
			}

			// Nonce and capability verified above; each field is sanitized per schema inside repository->update().
			$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( 'mw_download_assets' === $key ) {
				$value = $this->download_assets_from_submission( $value );
			}
			$this->repository->update( $post_id, $key, $value );
		}

		$this->enforce_access_invariants( $post_id );
		$this->product_mapper->sync_reverse_index( $post_id, $old_product_ids, $this->repository->product_ids( $post_id ) );
	}

	private function enforce_access_invariants( int $post_id ): void {
		$mode              = (string) $this->repository->get( $post_id, 'mw_access_mode' );
		$product_ids       = $this->repository->product_ids( $post_id );
		$membership_levels = $this->repository->get( $post_id, 'mw_membership_levels' );
		$has_membership    = is_array( $membership_levels ) && ! empty( $membership_levels );

		if ( 'purchase' === $mode && empty( $product_ids ) ) {
			$this->restrict( $post_id, __( 'MusicWave changed access to Restricted because purchase access requires a mapped product.', 'music-wave-core' ) );
		} elseif ( 'membership' === $mode && ! $has_membership ) {
			$this->restrict( $post_id, __( 'MusicWave changed access to Restricted because membership access requires a membership level.', 'music-wave-core' ) );
		} elseif ( 'purchase_or_membership' === $mode ) {
			if ( empty( $product_ids ) && ! $has_membership ) {
				$this->restrict( $post_id, __( 'MusicWave changed access to Restricted because no purchase product or membership level was configured.', 'music-wave-core' ) );
			} elseif ( empty( $product_ids ) ) {
				$this->repository->update( $post_id, 'mw_access_mode', 'membership' );
			} elseif ( ! $has_membership ) {
				$this->repository->update( $post_id, 'mw_access_mode', 'purchase' );
			}
		}
	}

	private function restrict( int $post_id, string $notice ): void {
		$this->repository->update( $post_id, 'mw_access_mode', 'restricted' );
		set_transient( self::NOTICE_KEY . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
	}

	/** @param mixed $value */
	private function render_field( MetaDefinition $definition, $value ): void {
		$config = $definition->admin();
		$key    = $definition->key();
		$type   = (string) $config['type'];

		echo '<p class="mw-admin-field mw-admin-field--' . esc_attr( $type ) . '">';
		echo '<label for="' . esc_attr( $key ) . '"><strong>' . esc_html( (string) $config['label'] ) . '</strong></label><br>';

		if ( 'select' === $type ) {
			echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
			foreach ( $config['options'] as $option_value => $option_label ) {
				echo '<option value="' . esc_attr( (string) $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( (string) $option_label ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkbox' === $type ) {
			echo '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1" ' . checked( (bool) $value, true, false ) . '>';
		} elseif ( 'products' === $type ) {
			$this->render_products( $key, is_array( $value ) ? $value : array() );
		} elseif ( 'protected_asset' === $type ) {
			$this->render_protected_asset( $key, $value );
		} elseif ( 'protected_assets' === $type ) {
			$this->render_protected_assets( $key, $value );
		} else {
			$input_type = in_array( $type, array( 'date', 'number', 'url' ), true ) ? $type : 'text';
			$display    = 'key_list' === $type && is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			echo '<input class="widefat" type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $display ) . '"';
			foreach ( array( 'min', 'max' ) as $attribute ) {
				if ( isset( $config[ $attribute ] ) ) {
					echo ' ' . esc_attr( $attribute ) . '="' . esc_attr( (string) $config[ $attribute ] ) . '"';
				}
			}
			echo '>';
		}

		echo '</p>';
	}

	/**
	 * Render an opaque provider asset identifier, never a direct file URL.
	 *
	 * @param mixed $value Stored asset identifier.
	 * @return void
	 */
	private function render_protected_asset( string $key, $value ): void {
		echo '<input class="widefat" type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" autocomplete="off">';
		echo '<span class="description">' . esc_html__( 'Store a provider asset identifier such as local:album/track.zip, never a public download URL.', 'music-wave-core' ) . '</span>';
	}

	/**
	 * Render private quality variants as one line per opaque provider asset.
	 *
	 * @param mixed $value Stored variants.
	 * @return void
	 */
	private function render_protected_assets( string $key, $value ): void {
		$lines = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['key'] ) || empty( $asset['label'] ) || empty( $asset['asset_id'] ) ) {
					continue;
				}
				$lines[] = (string) $asset['key'] . ' | ' . (string) $asset['label'] . ' | ' . (string) $asset['asset_id'];
			}
		}

		echo '<textarea class="widefat" rows="5" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<span class="description">' . esc_html__( 'One quality per line: quality-key | customer label | opaque provider asset ID. Example: mp3-320 | MP3 320 kbps | local:downloads/track-320.mp3', 'music-wave-core' ) . '</span>';
	}

	/**
	 * Convert the no-JavaScript quality editor into canonical array input.
	 *
	 * @param mixed $value Posted textarea value.
	 * @return array<int, array<string, string>>
	 */
	private function download_assets_from_submission( $value ): array {
		if ( ! is_scalar( $value ) ) {
			return array();
		}

		$assets = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 3 ) );
			if ( 3 !== count( $parts ) ) {
				continue;
			}
			$assets[] = array(
				'key'      => $parts[0],
				'label'    => $parts[1],
				'asset_id' => $parts[2],
			);
		}

		return $assets;
	}

	/** @param array<int, int> $selected_ids */
	private function render_products( string $key, array $selected_ids ): void {
		if ( ! post_type_exists( 'product' ) ) {
			echo '<span class="description">' . esc_html__( 'Activate WooCommerce to map products.', 'music-wave-core' ) . '</span>';
			return;
		}

		echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="0">';
		echo '<select class="wc-product-search widefat" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '[]" multiple="multiple" data-action="woocommerce_json_search_products" data-placeholder="' . esc_attr__( 'Search for a product…', 'music-wave-core' ) . '">';
		foreach ( $selected_ids as $product_id ) {
			if ( 'product' !== get_post_type( $product_id ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $product_id ) . '" ' . selected( in_array( (int) $product_id, $selected_ids, true ), true, false ) . '>' . esc_html( get_the_title( $product_id ) ) . '</option>';
		}
		echo '</select>';
	}
}
