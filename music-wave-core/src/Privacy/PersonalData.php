<?php
/**
 * WordPress personal-data exporter and eraser for MusicWave user data.
 *
 * MusicWave stores one user-owned dataset: the personal music library
 * (`mw_music_library` user meta). Delivery audit events are emitted as hooks
 * and only persisted by integrations, which own their retention; rate and
 * quota counters are ephemeral transients (PROJECT_PLAN.md Stage 3
 * deliverable 6).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Privacy;

use ManaCore\MusicWave\Core\Library\LibraryRepository;

final class PersonalData {
	/** @var LibraryRepository */
	private $library;

	public function __construct( LibraryRepository $library ) {
		$this->library = $library;
	}

	/**
	 * Register with the WordPress privacy tooling.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * @param mixed $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( $exporters ): array {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters['music-wave-library'] = array(
			'exporter_friendly_name' => __( 'MusicWave personal library', 'music-wave-core' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * @param mixed $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( $erasers ): array {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['music-wave-library'] = array(
			'eraser_friendly_name' => __( 'MusicWave personal library', 'music-wave-core' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export the personal library for one email address.
	 *
	 * @param string $email Email address under review.
	 * @param int    $page  Export page (single-page dataset).
	 * @return array<string, mixed>
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		if ( false === $user || ! isset( $user->ID ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$items = array();
		foreach ( $this->library->all( (int) $user->ID ) as $item ) {
			$items[] = array(
				'group_id'    => 'music-wave-library',
				'group_label' => __( 'Personal music library', 'music-wave-core' ),
				'item_id'     => 'music-wave-library-' . (string) $item['type'] . '-' . (string) $item['id'],
				'data'        => array(
					array(
						'name'  => __( 'Item type', 'music-wave-core' ),
						'value' => (string) $item['type'],
					),
					array(
						'name'  => __( 'Catalog ID', 'music-wave-core' ),
						'value' => (string) $item['id'],
					),
					array(
						'name'  => __( 'Saved on', 'music-wave-core' ),
						'value' => (int) $item['added'] > 0 ? gmdate( 'Y-m-d H:i:s', (int) $item['added'] ) : '',
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Erase the personal library for one email address.
	 *
	 * @param string $email Email address under erasure.
	 * @param int    $page  Erasure page (single-page dataset).
	 * @return array<string, mixed>
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( false !== $user && isset( $user->ID ) && $this->library->count( (int) $user->ID ) > 0 ) {
			$removed = delete_user_meta( (int) $user->ID, LibraryRepository::META_KEY );
		}

		return array(
			'items_removed'  => (bool) $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
