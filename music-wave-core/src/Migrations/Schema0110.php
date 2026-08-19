<?php
/**
 * Indexed playlist and playlist-item tables.
 *
 * Playlists are per-user, ordered, and shareable, so they need row-level
 * indexes and a unique share-token lookup that serialized meta cannot provide
 * (PROJECT_PLAN.md Stage 5 deliverable 4).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Migrations;

use ManaCore\MusicWave\Core\Contracts\Migration;
use ManaCore\MusicWave\Core\Playlists\DatabasePlaylistStore;

final class Schema0110 implements Migration {
	public function version(): string {
		return '0.11.0';
	}

	/**
	 * Create the playlist tables.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			if ( ! defined( 'ABSPATH' ) ) {
				return;
			}
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( ! is_readable( $upgrade_file ) ) {
				return;
			}
			require_once $upgrade_file;
		}

		$playlists = $wpdb->prefix . DatabasePlaylistStore::TABLE;
		$items     = $wpdb->prefix . DatabasePlaylistStore::ITEMS_TABLE;
		$charset   = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

		dbDelta(
			"CREATE TABLE {$playlists} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				title varchar(191) NOT NULL DEFAULT '',
				visibility varchar(20) NOT NULL DEFAULT 'private',
				share_token varchar(64) DEFAULT NULL,
				created_at bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY share_token (share_token),
				KEY user_updated (user_id, updated_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$items} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				playlist_id bigint(20) unsigned NOT NULL,
				release_id bigint(20) unsigned NOT NULL,
				position int(10) unsigned NOT NULL DEFAULT 0,
				added_at bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY playlist_release (playlist_id, release_id),
				KEY playlist_position (playlist_id, position),
				KEY release_id (release_id)
			) {$charset};"
		);

		update_option( 'music_wave_core_version', MUSIC_WAVE_CORE_VERSION, false );
	}
}
