<?php
/**
 * Playlist REST surface.
 *
 * Mutations require authentication and ownership; reads are permitted for
 * anyone but every response is filtered through the playlist privacy rules,
 * so a denied viewer receives the same 404-style error whether the playlist is
 * missing or merely private (PROJECT_PLAN.md Stage 5 deliverable 4).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Playlists;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PlaylistRoutes {
	/** @var PlaylistRepository */
	private $repository;

	public function __construct( PlaylistRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/playlists',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'store' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/playlists/(?P<id>[0-9]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/playlists/(?P<id>[0-9]+)/items',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/playlists/(?P<id>[0-9]+)/order',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
	}

	/** @return true|WP_Error */
	public function authenticated() {
		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return new WP_Error( 'mw_authentication_required', __( 'Sign in to manage playlists.', 'music-wave-core' ), array( 'status' => 401 ) );
	}

	/** @return WP_REST_Response */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$page  = max( 1, absint( $request->get_param( 'page' ) ) );
		$limit = min( PlaylistRepository::MAX_PLAYLISTS, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

		return new WP_REST_Response(
			array(
				'items' => $this->repository->for_user( get_current_user_id(), $limit, ( $page - 1 ) * $limit ),
				'page'  => $page,
			),
			200
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public function store( WP_REST_Request $request ) {
		$playlist_id = $this->repository->create(
			get_current_user_id(),
			(string) $request->get_param( 'title' ),
			(string) $request->get_param( 'visibility' )
		);

		if ( $playlist_id < 1 ) {
			return new WP_Error( 'mw_playlist_create_failed', __( 'The playlist could not be created. Check the name and your playlist limit.', 'music-wave-core' ), array( 'status' => 422 ) );
		}

		return new WP_REST_Response( $this->repository->view( $playlist_id, get_current_user_id() ), 201 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function show( WP_REST_Request $request ) {
		$view = $this->repository->view(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id(),
			(string) $request->get_param( 'share' )
		);

		if ( null === $view ) {
			return $this->not_found();
		}

		$response = new WP_REST_Response( $view, 200 );
		if ( method_exists( $response, 'header' ) ) {
			// Never let a shared or personal playlist land in a shared cache.
			$response->header( 'Cache-Control', PlaylistRepository::VISIBILITY_PUBLIC === $view['visibility'] && false === $view['owner'] ? 'public, max-age=300' : 'no-store, private' );
		}

		return $response;
	}

	/** @return WP_REST_Response|WP_Error */
	public function update( WP_REST_Request $request ) {
		$playlist_id = absint( $request->get_param( 'id' ) );
		$changes     = array();
		if ( null !== $request->get_param( 'title' ) ) {
			$changes['title'] = (string) $request->get_param( 'title' );
		}
		if ( null !== $request->get_param( 'visibility' ) ) {
			$changes['visibility'] = (string) $request->get_param( 'visibility' );
		}

		if ( ! $this->repository->update( get_current_user_id(), $playlist_id, $changes ) ) {
			return $this->not_found();
		}

		return new WP_REST_Response( $this->repository->view( $playlist_id, get_current_user_id() ), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function destroy( WP_REST_Request $request ) {
		if ( ! $this->repository->delete( get_current_user_id(), absint( $request->get_param( 'id' ) ) ) ) {
			return $this->not_found();
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function add_item( WP_REST_Request $request ) {
		$playlist_id = absint( $request->get_param( 'id' ) );
		$release_id  = absint( $request->get_param( 'release_id' ) );

		if ( ! $this->repository->add_item( get_current_user_id(), $playlist_id, $release_id ) ) {
			return $this->rejected( __( 'That release could not be added to the playlist.', 'music-wave-core' ) );
		}

		return new WP_REST_Response( $this->repository->view( $playlist_id, get_current_user_id() ), 201 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function remove_item( WP_REST_Request $request ) {
		$playlist_id = absint( $request->get_param( 'id' ) );
		$release_id  = absint( $request->get_param( 'release_id' ) );

		if ( ! $this->repository->remove_item( get_current_user_id(), $playlist_id, $release_id ) ) {
			return $this->rejected( __( 'That release is not in this playlist.', 'music-wave-core' ) );
		}

		return new WP_REST_Response( $this->repository->view( $playlist_id, get_current_user_id() ), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function reorder( WP_REST_Request $request ) {
		$playlist_id = absint( $request->get_param( 'id' ) );
		$ids         = $request->get_param( 'release_ids' );
		if ( ! is_array( $ids ) ) {
			return $this->rejected( __( 'Send the playlist order as a list of release IDs.', 'music-wave-core' ) );
		}

		if ( ! $this->repository->reorder( get_current_user_id(), $playlist_id, $ids ) ) {
			return $this->not_found();
		}

		return new WP_REST_Response( $this->repository->view( $playlist_id, get_current_user_id() ), 200 );
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'mw_playlist_not_found', __( 'That playlist is not available.', 'music-wave-core' ), array( 'status' => 404 ) );
	}

	private function rejected( string $message ): WP_Error {
		return new WP_Error( 'mw_playlist_rejected', $message, array( 'status' => 422 ) );
	}
}
