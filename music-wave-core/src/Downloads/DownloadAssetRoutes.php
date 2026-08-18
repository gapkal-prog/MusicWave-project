<?php
/**
 * Editor-only routes for private release download qualities.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DownloadAssetRoutes {
	/** @var ReleaseRepository */
	private $releases;

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	/**
	 * Register editor-only quality management routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register authenticated asset-variant routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/releases/(?P<id>\d+)/download-assets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	/**
	 * Check that the requested release is editable by the current user.
	 *
	 * @param WP_REST_Request $request Route request.
	 * @return bool
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		$release_id = absint( $request->get_param( 'id' ) );

		return $release_id > 0 && ReleasePostType::KEY === get_post_type( $release_id ) && current_user_can( 'edit_post', $release_id );
	}

	/**
	 * Return private variants only to authorized editors.
	 *
	 * @param WP_REST_Request $request Route request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'items' => $this->assets( absint( $request->get_param( 'id' ) ) ) ), 200 );
	}

	/**
	 * Validate and persist opaque provider variants.
	 *
	 * @param WP_REST_Request $request Route request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$assets = $request->get_param( 'assets' );
		if ( ! is_array( $assets ) ) {
			return new WP_Error( 'mw_download_assets_invalid', __( 'Download qualities must be an array.', 'music-wave-core' ), array( 'status' => 400 ) );
		}

		$release_id = absint( $request->get_param( 'id' ) );
		$this->releases->update( $release_id, 'mw_download_assets', $assets );
		$this->releases->delete( $release_id, 'mw_download_asset_id' );

		return new WP_REST_Response( array( 'items' => $this->assets( $release_id ) ), 200 );
	}

	/**
	 * Read only complete variants for the editor response.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function assets( int $release_id ): array {
		$value  = $this->releases->get( $release_id, 'mw_download_assets' );
		$assets = is_array( $value ) ? $value : array();
		if ( empty( $assets ) ) {
			$legacy = $this->releases->get( $release_id, 'mw_download_asset_id' );
			if ( is_string( $legacy ) && '' !== $legacy ) {
				$assets[] = array(
					'key'      => 'standard',
					'label'    => __( 'Standard download', 'music-wave-core' ),
					'asset_id' => $legacy,
				);
			}
		}
		$result = array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['key'] ) || empty( $asset['label'] ) || empty( $asset['asset_id'] ) ) {
				continue;
			}
			$normalized = array(
				'key'      => sanitize_key( (string) $asset['key'] ),
				'label'    => sanitize_text_field( (string) $asset['label'] ),
				'asset_id' => sanitize_text_field( (string) $asset['asset_id'] ),
			);
			$format     = isset( $asset['format'] ) ? sanitize_key( strtolower( (string) $asset['format'] ) ) : '';
			$file_name  = isset( $asset['file_name'] ) ? sanitize_file_name( (string) $asset['file_name'] ) : '';
			$file_key   = isset( $asset['file_key'] ) ? sanitize_key( (string) $asset['file_key'] ) : '';
			$file_label = isset( $asset['file_label'] ) ? sanitize_text_field( (string) $asset['file_label'] ) : '';
			$bitrate    = isset( $asset['bitrate'] ) ? absint( $asset['bitrate'] ) : 0;
			$duration   = isset( $asset['duration'] ) ? absint( $asset['duration'] ) : 0;
			$file_size  = isset( $asset['file_size'] ) ? absint( $asset['file_size'] ) : 0;

			if ( '' !== $format ) {
				$normalized['format'] = $format;
			}
			if ( '' !== $file_name ) {
				$normalized['file_name'] = $file_name;
			}
			if ( '' !== $file_key && '' !== $file_label ) {
				$normalized['file_key']   = $file_key;
				$normalized['file_label'] = $file_label;
			}
			if ( $bitrate > 0 ) {
				$normalized['bitrate'] = $bitrate;
			}
			if ( $duration > 0 ) {
				$normalized['duration'] = $duration;
			}
			if ( $file_size > 0 ) {
				$normalized['file_size'] = $file_size;
			}

			$result[] = $normalized;
		}

		return $result;
	}
}
