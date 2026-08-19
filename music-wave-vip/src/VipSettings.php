<?php
/**
 * Typed, backwards-compatible VIP configuration.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class VipSettings {
	public const OPTION             = 'music_wave_vip_settings';
	public const LEGACY_ROOT_OPTION = 'music_wave_vip_protected_root';

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'delivery_provider'      => 'local',
			'protected_root'         => '',
			'remote_base_url'        => '',
			'remote_path_prefix'     => '',
			'remote_signing_secret'  => '',
			'remote_signature_param' => 'signature',
			'remote_expires_param'   => 'expires',
			'remote_ttl'             => 300,
			'remote_allowed_hosts'   => array(),
			'remote_key_id'          => '',
			'sendfile_mode'          => 'none',
			'xaccel_prefix'          => '',
			'membership_sources'     => array( 'role', 'filter' ),
		);
	}

	/** @return array<string, mixed> */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		if ( ! isset( $stored['protected_root'] ) ) {
			$stored['protected_root'] = (string) get_option( self::LEGACY_ROOT_OPTION, '' );
		}

		return self::normalize( array_merge( self::defaults(), $stored ) );
	}

	/** @param mixed $value @return array<string, mixed> */
	public static function sanitize( $value ): array {
		$current = self::all();
		$value   = is_array( $value ) ? $value : array();
		$value   = array_merge( $current, $value );

		if ( ! isset( $value['remote_signing_secret'] ) || '' === trim( (string) $value['remote_signing_secret'] ) ) {
			$value['remote_signing_secret'] = $current['remote_signing_secret'];
		}

		return self::normalize( $value );
	}

	/** @param array<string, mixed> $value @return array<string, mixed> */
	private static function normalize( array $value ): array {
		$defaults = self::defaults();
		$provider = isset( $value['delivery_provider'] ) ? sanitize_key( (string) $value['delivery_provider'] ) : '';
		$provider = in_array( $provider, array( 'local', 'remote_redirect' ), true ) ? $provider : $defaults['delivery_provider'];

		$root            = isset( $value['protected_root'] ) && is_scalar( $value['protected_root'] ) ? trim( wp_unslash( (string) $value['protected_root'] ) ) : '';
		$base            = isset( $value['remote_base_url'] ) && is_scalar( $value['remote_base_url'] ) ? esc_url_raw( trim( (string) $value['remote_base_url'] ), array( 'https' ) ) : '';
		$prefix          = isset( $value['remote_path_prefix'] ) && is_scalar( $value['remote_path_prefix'] ) ? trim( sanitize_text_field( (string) $value['remote_path_prefix'] ) ) : '';
		$prefix          = trim( preg_replace( '/[^A-Za-z0-9._\/-]/', '', $prefix ), '/' );
		$secret          = isset( $value['remote_signing_secret'] ) && is_scalar( $value['remote_signing_secret'] ) ? trim( (string) $value['remote_signing_secret'] ) : '';
		$signature_param = isset( $value['remote_signature_param'] ) ? sanitize_key( (string) $value['remote_signature_param'] ) : '';
		$expires_param   = isset( $value['remote_expires_param'] ) ? sanitize_key( (string) $value['remote_expires_param'] ) : '';
		$ttl             = isset( $value['remote_ttl'] ) ? absint( $value['remote_ttl'] ) : 0;
		$allowed_hosts   = isset( $value['remote_allowed_hosts'] ) ? $value['remote_allowed_hosts'] : array();
		$allowed_hosts   = is_array( $allowed_hosts ) ? $allowed_hosts : explode( "\n", (string) $allowed_hosts );
		$allowed_hosts   = array_values(
			array_filter(
				array_unique(
					array_map(
						static function ( $host ): string {
							$host = strtolower( trim( (string) $host ) );
							return 1 === preg_match( '/^[a-z0-9.-]+$/', $host ) ? $host : '';
						},
						$allowed_hosts
					)
				)
			)
		);
		$key_id          = isset( $value['remote_key_id'] ) ? sanitize_key( (string) $value['remote_key_id'] ) : '';
		$sendfile_mode   = isset( $value['sendfile_mode'] ) ? sanitize_key( (string) $value['sendfile_mode'] ) : '';
		$sendfile_mode   = in_array( $sendfile_mode, array( 'none', 'xsendfile', 'xaccel' ), true ) ? $sendfile_mode : 'none';
		$xaccel_prefix   = isset( $value['xaccel_prefix'] ) && is_scalar( $value['xaccel_prefix'] ) ? trim( sanitize_text_field( (string) $value['xaccel_prefix'] ) ) : '';
		$xaccel_prefix   = '' !== $xaccel_prefix ? '/' . trim( preg_replace( '/[^A-Za-z0-9._\/-]/', '', $xaccel_prefix ), '/' ) : '';
		$sources         = isset( $value['membership_sources'] ) && is_array( $value['membership_sources'] ) ? $value['membership_sources'] : array();
		$sources         = array_values( array_unique( array_filter( array_map( 'sanitize_key', $sources ) ) ) );
		$sources         = array_values( array_intersect( $sources, array( 'role', 'filter', 'woocommerce_memberships', 'woocommerce_subscriptions' ) ) );
		if ( empty( $sources ) ) {
			$sources = $defaults['membership_sources'];
		}

		return array(
			'delivery_provider'      => $provider,
			'protected_root'         => $root,
			'remote_base_url'        => is_string( $base ) ? untrailingslashit( $base ) : '',
			'remote_path_prefix'     => $prefix,
			'remote_signing_secret'  => $secret,
			'remote_signature_param' => '' !== $signature_param ? $signature_param : $defaults['remote_signature_param'],
			'remote_expires_param'   => '' !== $expires_param ? $expires_param : $defaults['remote_expires_param'],
			'remote_ttl'             => $ttl >= 30 && $ttl <= 900 ? $ttl : $defaults['remote_ttl'],
			'remote_allowed_hosts'   => $allowed_hosts,
			'remote_key_id'          => $key_id,
			'sendfile_mode'          => $sendfile_mode,
			'xaccel_prefix'          => $xaccel_prefix,
			'membership_sources'     => $sources,
		);
	}
}
