<?php
/**
 * Authenticated listening REST surface: consent, progress, continue, queue.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Listening;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ListeningRoutes {
	/** @var ListeningRepository */
	private $repository;

	public function __construct( ListeningRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'music-wave/v1',
			'/listening/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'consent' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/listening/progress',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'progress' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/listening/continue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'continue_listening' ),
				'permission_callback' => array( $this, 'authenticated' ),
			)
		);
		register_rest_route(
			'music-wave/v1',
			'/listening/queue',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_queue' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_queue' ),
					'permission_callback' => array( $this, 'authenticated' ),
				),
			)
		);
	}

	/** @return true|WP_Error */
	public function authenticated() {
		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return new WP_Error( 'mw_authentication_required', __( 'Sign in to use listening features.', 'music-wave-core' ), array( 'status' => 401 ) );
	}

	/** @return WP_REST_Response|WP_Error */
	public function consent( WP_REST_Request $request ) {
		// The flag is required and parsed explicitly: withdrawing consent
		// erases the listening history, so neither a missing parameter nor a
		// form-encoded "false" string may silently flip the decision.
		$raw = $request->get_param( 'consent' );
		if ( null === $raw ) {
			return new WP_Error( 'mw_consent_flag_required', __( 'The consent flag is required.', 'music-wave-core' ), array( 'status' => 400 ) );
		}
		$consent = function_exists( 'rest_sanitize_boolean' ) ? rest_sanitize_boolean( $raw ) : filter_var( $raw, FILTER_VALIDATE_BOOLEAN );
		$this->repository->set_consent( get_current_user_id(), (bool) $consent );

		return new WP_REST_Response( array( 'consent' => $this->repository->has_consent( get_current_user_id() ) ), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function progress( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$release_id = absint( $request->get_param( 'release_id' ) );
		$event      = 'played' === sanitize_key( (string) $request->get_param( 'event' ) ) ? ListeningRepository::EVENT_PLAYED : ListeningRepository::EVENT_PROGRESS;
		$position   = absint( $request->get_param( 'position' ) );

		if ( ! $this->repository->has_consent( $user_id ) ) {
			return new WP_Error( 'mw_listening_consent_required', __( 'Enable listening history in your account to save playback progress.', 'music-wave-core' ), array( 'status' => 403 ) );
		}
		if ( ! $this->repository->record( $user_id, $release_id, $event, $position ) ) {
			return new WP_Error( 'mw_listening_unavailable', __( 'Playback progress could not be saved.', 'music-wave-core' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'saved' => true ), 200 );
	}

	/** @return WP_REST_Response */
	public function continue_listening( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$limit   = absint( $request->get_param( 'per_page' ) );
		$items   = array();
		foreach ( $this->repository->recent( $user_id, ListeningRepository::EVENT_PROGRESS, $limit > 0 ? $limit : 12 ) as $row ) {
			$items[] = array(
				'release_id' => $row['release_id'],
				'position'   => $row['position'],
				'title'      => get_the_title( $row['release_id'] ),
				'url'        => (string) get_permalink( $row['release_id'] ),
			);
		}

		return new WP_REST_Response(
			array(
				'consent' => $this->repository->has_consent( $user_id ),
				'items'   => $items,
			),
			200
		);
	}

	/** @return WP_REST_Response */
	public function get_queue(): WP_REST_Response {
		return new WP_REST_Response( $this->repository->queue( get_current_user_id() ), 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public function save_queue( WP_REST_Request $request ) {
		$queue = $request->get_param( 'queue' );
		if ( ! is_array( $queue ) ) {
			return new WP_Error( 'mw_queue_invalid', __( 'The playback queue payload must be an object.', 'music-wave-core' ), array( 'status' => 400 ) );
		}
		if ( ! $this->repository->save_queue( get_current_user_id(), $queue ) ) {
			return new WP_Error( 'mw_queue_unavailable', __( 'The playback queue could not be saved.', 'music-wave-core' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $this->repository->queue( get_current_user_id() ), 200 );
	}
}
