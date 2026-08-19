<?php
/**
 * Public discovery REST surface: explainable recommendations.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Discovery;

use WP_REST_Request;
use WP_REST_Response;

final class DiscoveryRoutes {
	/** @var Recommendations */
	private $recommendations;

	public function __construct( Recommendations $recommendations ) {
		$this->recommendations = $recommendations;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/recommendations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				// Anonymous visitors receive the editorial list only.
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return WP_REST_Response */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$limit = absint( $request->get_param( 'per_page' ) );
		$items = array();
		foreach ( $this->recommendations->recommend( get_current_user_id(), $limit > 0 ? $limit : 8 ) as $item ) {
			$release_id = isset( $item['release_id'] ) ? absint( $item['release_id'] ) : 0;
			if ( $release_id < 1 ) {
				continue;
			}
			$items[] = array(
				'release_id'  => $release_id,
				'title'       => get_the_title( $release_id ),
				'url'         => (string) get_permalink( $release_id ),
				'reason'      => isset( $item['reason'] ) ? (string) $item['reason'] : '',
				'explanation' => isset( $item['explanation'] ) ? (string) $item['explanation'] : '',
			);
		}

		$response = new WP_REST_Response( array( 'items' => $items ), 200 );
		$response->header( 'Cache-Control', get_current_user_id() > 0 ? 'no-store, private' : 'public, max-age=300' );

		return $response;
	}
}
