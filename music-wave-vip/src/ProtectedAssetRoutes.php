<?php
/**
 * Authorized protected-asset browsing and upload endpoints.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ProtectedAssetRoutes {
	/** @var ProtectedAssetStorage */
	private $storage;

	public function __construct( ProtectedAssetStorage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Register provider-specific editor routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register protected asset routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/protected-assets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_manage_assets' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => array( $this, 'can_manage_assets' ),
				),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/protected-assets/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_attachment' ),
				'permission_callback' => array( $this, 'can_manage_assets' ),
			)
		);
	}

	/**
	 * Require the dedicated MusicWave asset capability for inventory access.
	 *
	 * `upload_files` was too broad: any author-level user could enumerate the
	 * protected inventory. The dedicated capability maps to administrators by
	 * default and is filterable through `music_wave_manage_asset_caps`
	 * (PROJECT_PLAN.md Stage 1 deliverable 5).
	 */
	public function can_manage_assets(): bool {
		return current_user_can( 'manage_mw_protected_assets' );
	}

	/**
	 * List private asset identifiers without exposing their filesystem paths.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$search   = $request->get_param( 'search' );
		$search   = is_scalar( $search ) ? sanitize_text_field( (string) $search ) : '';
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		$per_page = min( 100, max( 1, $per_page > 0 ? $per_page : 50 ) );
		$assets   = $this->storage->list_assets( $search, $page, $per_page );

		return new WP_REST_Response(
			array(
				'items'    => $assets['items'],
				'page'     => $page,
				'per_page' => $per_page,
				'has_more' => $assets['has_more'],
			),
			200
		);
	}

	/**
	 * Accept one file and persist it outside the public WordPress root.
	 */
	public function upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : array();
		$asset = $this->storage->upload( $file );
		if ( $asset instanceof WP_Error ) {
			return $asset;
		}

		return new WP_REST_Response( array( 'asset' => $asset ), 201 );
	}

	/**
	 * Copy a selected WordPress attachment into protected storage.
	 */
	public function import_attachment( WP_REST_Request $request ) {
		$asset = $this->storage->import_attachment( absint( $request->get_param( 'attachment_id' ) ) );
		if ( $asset instanceof WP_Error ) {
			return $asset;
		}

		return new WP_REST_Response( array( 'asset' => $asset ), 201 );
	}
}
