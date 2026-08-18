<?php
/**
 * Membership adapter combining roles, custom integrations and Woo extensions.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

use ManaCore\MusicWave\Core\Access\MembershipProvider;

final class ConfigurableMembershipProvider implements MembershipProvider {
	/** @var array<int, string> */
	private $sources;

	/** @param array<int, string> $sources */
	public function __construct( array $sources ) {
		$this->sources = array_values( array_unique( array_map( 'sanitize_key', $sources ) ) );
	}

	public function has_access( int $user_id, array $levels ): bool {
		if ( $user_id < 1 || empty( $levels ) ) {
			return false;
		}

		$levels = array_values( array_filter( array_map( 'sanitize_key', $levels ) ) );
		if ( empty( $levels ) ) {
			return false;
		}

		foreach ( $this->sources as $source ) {
			if ( $this->source_has_access( $source, $user_id, $levels ) ) {
				return true;
			}
		}

		return false;
	}

	/** @param array<int, string> $levels */
	private function source_has_access( string $source, int $user_id, array $levels ): bool {
		foreach ( $levels as $level ) {
			$filtered = apply_filters( 'music_wave_vip_membership_access', null, $user_id, $level, $source );
			if ( true === $filtered ) {
				return true;
			}

			if ( 'role' === $source && $this->role_access( $user_id, $level ) ) {
				return true;
			}
			if ( 'filter' === $source && $this->filter_access( $user_id, $level ) ) {
				return true;
			}
			if ( 'woocommerce_memberships' === $source && $this->woocommerce_membership_access( $user_id, $level ) ) {
				return true;
			}
			if ( 'woocommerce_subscriptions' === $source && $this->subscription_access( $user_id, $level ) ) {
				return true;
			}
		}

		return false;
	}

	private function role_access( int $user_id, string $level ): bool {
		$user = get_userdata( $user_id );
		return false !== $user && is_array( $user->roles ) && in_array( $level, array_map( 'sanitize_key', $user->roles ), true );
	}

	private function filter_access( int $user_id, string $level ): bool {
		$user_levels = get_userdata( $user_id );
		$roles = false !== $user_levels && is_array( $user_levels->roles ) ? $user_levels->roles : array();
		$mapped = apply_filters( 'music_wave_vip_membership_levels_for_user', $roles, $user_id );

		return is_array( $mapped ) && in_array( $level, array_map( 'sanitize_key', $mapped ), true );
	}

	private function woocommerce_membership_access( int $user_id, string $level ): bool {
		if ( ! function_exists( 'wc_memberships_is_user_active_member' ) ) {
			return false;
		}

		$plan = 0 === strpos( $level, 'plan-' ) ? substr( $level, 5 ) : $level;
		return '' !== $plan && (bool) wc_memberships_is_user_active_member( $user_id, $plan );
	}

	private function subscription_access( int $user_id, string $level ): bool {
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			return false;
		}

		$product_id = 0 === strpos( $level, 'subscription-' ) ? absint( substr( $level, 13 ) ) : absint( $level );
		return $product_id > 0 && (bool) wcs_user_has_subscription( $user_id, $product_id, 'active' );
	}
}
