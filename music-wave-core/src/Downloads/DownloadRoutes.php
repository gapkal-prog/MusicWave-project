<?php
/**
 * Authenticated token issuance and protected delivery routes.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

use ManaCore\MusicWave\Core\Access\AccessSubject;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DownloadRoutes {
	/** @var DownloadResolver */
	private $resolver;

	public function __construct( DownloadResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/releases/(?P<id>\d+)/download-token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'issue' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/downloads/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/streams/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stream' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
	}

	/**
	 * Return an actionable authentication error instead of REST's generic 401.
	 *
	 * WordPress cookie authentication validates the REST nonce before this
	 * callback runs. The frontend sends that nonce explicitly for every step.
	 *
	 * @return true|WP_Error
	 */
	public function authenticated() {
		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return new WP_Error(
			'mw_authentication_required',
			__( 'Your session has expired. Sign in again to download this release.', 'music-wave-core' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Issue a short-lived token bound to the current user, release and asset.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function issue( WP_REST_Request $request ) {
		$release_id = absint( $request->get_param( 'id' ) );
		$quality    = $request->get_param( 'quality' );
		$quality    = is_scalar( $quality ) ? sanitize_key( (string) $quality ) : '';
		$purpose    = $request->get_param( 'purpose' );
		$purpose    = is_scalar( $purpose ) && 'stream' === sanitize_key( (string) $purpose ) ? 'stream' : 'download';
		$nonce      = wp_create_nonce( 'music_wave_download_' . $release_id );
		$token      = $this->resolver->issue( $release_id, AccessSubject::current(), $nonce, $quality, $purpose );

		if ( null === $token ) {
			return new WP_Error(
				'mw_download_denied',
				__( 'This file is unavailable or your account does not have access to it.', 'music-wave-core' ),
				array( 'status' => 403 )
			);
		}

		$response = new WP_REST_Response(
			array(
				'token'      => $token,
				'nonce'      => $nonce,
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'purpose'    => $purpose,
				'expires_in' => 'stream' === $purpose ? 900 : 300,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Referrer-Policy', 'no-referrer' );

		return $response;
	}

	/** @return WP_REST_Response|WP_Error */
	public function download( WP_REST_Request $request ) {
		$release_id = absint( $request->get_param( 'id' ) );
		$token      = $request->get_param( 'token' );
		$nonce      = $request->get_param( 'nonce' );
		$valid      = is_string( $token )
			&& is_string( $nonce )
			&& wp_verify_nonce( $nonce, 'music_wave_download_' . $release_id )
			&& $this->resolver->deliver( $release_id, get_current_user_id(), $token, $nonce );

		return $valid
			? new WP_REST_Response( null, 204 )
			: new WP_Error( 'mw_download_denied', __( 'The secure download link is invalid or has expired.', 'music-wave-core' ), array( 'status' => 403 ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function stream( WP_REST_Request $request ) {
		$release_id = absint( $request->get_param( 'id' ) );
		$token      = $request->get_param( 'token' );
		$nonce      = $request->get_param( 'nonce' );
		$valid      = is_string( $token )
			&& is_string( $nonce )
			&& wp_verify_nonce( $nonce, 'music_wave_download_' . $release_id )
			&& $this->resolver->stream( $release_id, get_current_user_id(), $token, $nonce );

		return $valid
			? new WP_REST_Response( null, 204 )
			: new WP_Error( 'mw_stream_denied', __( 'The secure playback link is invalid or has expired.', 'music-wave-core' ), array( 'status' => 403 ) );
	}
}
