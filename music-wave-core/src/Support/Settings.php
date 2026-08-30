<?php
/**
 * Typed MusicWave site settings.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Support;

final class Settings {
	public const OPTION = 'music_wave_settings';

	/**
	 * Return canonical defaults for every Core setting.
	 *
	 * @return array<string, int|string>
	 */
	public static function defaults(): array {
		return array(
			'default_access_mode'        => 'public',
			'default_preview_duration'   => 30,
			'archive_per_page'           => 12,
			'archive_default_sort'       => 'latest',
			'show_same_artist_releases'  => 'enabled',
			'show_similar_releases'      => 'enabled',
			'related_items_per_section'  => 4,
			'slider_enabled'             => 'enabled',
			'slider_autoplay'            => 'enabled',
			'slider_loop'                => 'enabled',
			'slider_pause_on_hover'      => 'enabled',
			'slider_show_arrows'         => 'enabled',
			'slider_show_dots'           => 'enabled',
			'slider_interval'            => 5000,
			'slider_items'               => 6,
			'persistent_navigation'      => 'enabled',
			'json_ld_mode'               => 'auto',
			'membership_absent_behavior' => 'allow',
			'purchase_message'           => '',
			'purchase_cta_label'         => '',
			'membership_message'         => '',
			'membership_cta_label'       => '',
			'membership_cta_url'         => '',
			'restricted_message'         => '',
			'access_granted_message'     => '',
			'spotify_client_id'          => '',
			'spotify_client_secret'      => '',
			'discogs_token'              => '',
			'download_rate_limit'        => 30,
			'download_rate_window'       => 60,
			'download_daily_quota'       => 0,
			'discovery_rate_limit'       => 60,
			'discovery_rate_window'      => 60,
			'discovery_cache_ttl'        => 300,
			'listening_retention_days'   => 180,
		);
	}

	/**
	 * Return sanitized settings merged with defaults.
	 *
	 * @return array<string, int|string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? self::normalize( $stored ) : array();

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Read one known setting.
	 *
	 * @return int|string
	 */
	public static function get( string $key ) {
		$settings = self::all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : '';
	}

	/**
	 * Sanitize a complete settings payload.
	 *
	 * @param mixed $value Raw Settings API value.
	 * @return array<string, int|string>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::all();
		}

		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();

		return self::normalize( array_merge( $current, $value ) );
	}

	/**
	 * Normalize a complete settings array without reading from WordPress.
	 *
	 * @param array<string, mixed> $value Candidate settings.
	 * @return array<string, int|string>
	 */
	private static function normalize( array $value ): array {
		$defaults = self::defaults();
		$result   = array();

		$access_mode                   = isset( $value['default_access_mode'] ) && is_scalar( $value['default_access_mode'] ) ? sanitize_key( (string) $value['default_access_mode'] ) : '';
		$result['default_access_mode'] = in_array( $access_mode, self::access_modes(), true ) ? $access_mode : $defaults['default_access_mode'];

		$preview_duration                   = isset( $value['default_preview_duration'] ) ? absint( $value['default_preview_duration'] ) : 0;
		$result['default_preview_duration'] = $preview_duration >= 10 && $preview_duration <= 120 ? $preview_duration : $defaults['default_preview_duration'];

		$per_page                   = isset( $value['archive_per_page'] ) ? absint( $value['archive_per_page'] ) : 0;
		$result['archive_per_page'] = $per_page >= 1 && $per_page <= 100 ? $per_page : $defaults['archive_per_page'];

		$sort                           = isset( $value['archive_default_sort'] ) && is_scalar( $value['archive_default_sort'] ) ? sanitize_key( (string) $value['archive_default_sort'] ) : '';
		$result['archive_default_sort'] = in_array( $sort, array( 'latest', 'oldest', 'title_asc', 'title_desc' ), true ) ? $sort : $defaults['archive_default_sort'];

		foreach ( array( 'show_same_artist_releases', 'show_similar_releases', 'slider_enabled', 'slider_autoplay', 'slider_loop', 'slider_pause_on_hover', 'slider_show_arrows', 'slider_show_dots', 'persistent_navigation' ) as $key ) {
			$toggle         = isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? sanitize_key( (string) $value[ $key ] ) : '';
			$result[ $key ] = in_array( $toggle, array( 'enabled', 'disabled' ), true ) ? $toggle : $defaults[ $key ];
		}

		$related_count                       = isset( $value['related_items_per_section'] ) ? absint( $value['related_items_per_section'] ) : 0;
		$result['related_items_per_section'] = $related_count >= 2 && $related_count <= 12 ? $related_count : $defaults['related_items_per_section'];

		$slider_interval           = isset( $value['slider_interval'] ) ? absint( $value['slider_interval'] ) : 0;
		$result['slider_interval'] = $slider_interval >= 2000 && $slider_interval <= 20000 ? $slider_interval : $defaults['slider_interval'];

		$slider_items           = isset( $value['slider_items'] ) ? absint( $value['slider_items'] ) : 0;
		$result['slider_items'] = $slider_items >= 3 && $slider_items <= 12 ? $slider_items : $defaults['slider_items'];

		$json_ld_mode           = isset( $value['json_ld_mode'] ) && is_scalar( $value['json_ld_mode'] ) ? sanitize_key( (string) $value['json_ld_mode'] ) : '';
		$result['json_ld_mode'] = in_array( $json_ld_mode, array( 'auto', 'enabled', 'disabled' ), true ) ? $json_ld_mode : $defaults['json_ld_mode'];

		$absent_behavior                      = isset( $value['membership_absent_behavior'] ) && is_scalar( $value['membership_absent_behavior'] ) ? sanitize_key( (string) $value['membership_absent_behavior'] ) : '';
		$result['membership_absent_behavior'] = in_array( $absent_behavior, array( 'allow', 'deny' ), true ) ? $absent_behavior : $defaults['membership_absent_behavior'];

		foreach ( array( 'purchase_message', 'purchase_cta_label', 'membership_message', 'membership_cta_label', 'restricted_message', 'access_granted_message' ) as $key ) {
			$result[ $key ] = isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? sanitize_text_field( (string) $value[ $key ] ) : '';
		}

		$membership_url               = isset( $value['membership_cta_url'] ) && is_scalar( $value['membership_cta_url'] ) ? esc_url_raw( (string) $value['membership_cta_url'], array( 'http', 'https' ) ) : '';
		$result['membership_cta_url'] = is_string( $membership_url ) ? $membership_url : '';

		foreach ( array( 'spotify_client_id', 'spotify_client_secret', 'discogs_token' ) as $key ) {
			$result[ $key ] = isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? sanitize_text_field( (string) $value[ $key ] ) : '';
		}

		$download_rate_limit           = isset( $value['download_rate_limit'] ) ? absint( $value['download_rate_limit'] ) : 0;
		$result['download_rate_limit'] = $download_rate_limit >= 1 && $download_rate_limit <= 1000 ? $download_rate_limit : $defaults['download_rate_limit'];

		$download_rate_window           = isset( $value['download_rate_window'] ) ? absint( $value['download_rate_window'] ) : 0;
		$result['download_rate_window'] = $download_rate_window >= 10 && $download_rate_window <= 3600 ? $download_rate_window : $defaults['download_rate_window'];

		$download_daily_quota           = isset( $value['download_daily_quota'] ) ? absint( $value['download_daily_quota'] ) : 0;
		$result['download_daily_quota'] = $download_daily_quota <= 10000 ? $download_daily_quota : $defaults['download_daily_quota'];

		$discovery_rate_limit           = isset( $value['discovery_rate_limit'] ) ? absint( $value['discovery_rate_limit'] ) : 0;
		$result['discovery_rate_limit'] = $discovery_rate_limit >= 1 && $discovery_rate_limit <= 1000 ? $discovery_rate_limit : $defaults['discovery_rate_limit'];

		$discovery_rate_window           = isset( $value['discovery_rate_window'] ) ? absint( $value['discovery_rate_window'] ) : 0;
		$result['discovery_rate_window'] = $discovery_rate_window >= 10 && $discovery_rate_window <= 3600 ? $discovery_rate_window : $defaults['discovery_rate_window'];

		$discovery_cache_ttl           = isset( $value['discovery_cache_ttl'] ) ? absint( $value['discovery_cache_ttl'] ) : 0;
		$result['discovery_cache_ttl'] = $discovery_cache_ttl >= 30 && $discovery_cache_ttl <= 86400 ? $discovery_cache_ttl : $defaults['discovery_cache_ttl'];

		$listening_retention_days           = isset( $value['listening_retention_days'] ) ? absint( $value['listening_retention_days'] ) : 0;
		$result['listening_retention_days'] = $listening_retention_days >= 1 && $listening_retention_days <= 3650 ? $listening_retention_days : $defaults['listening_retention_days'];

		return $result;
	}

	/**
	 * Return all canonical access mode keys.
	 *
	 * @return array<int, string>
	 */
	public static function access_modes(): array {
		return array( 'public', 'purchase', 'membership', 'purchase_or_membership', 'restricted' );
	}
}
