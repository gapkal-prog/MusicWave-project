<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Vip;
use ManaCore\MusicWave\Core\Access\MembershipProvider;
final class RoleMembershipProvider implements MembershipProvider {
	public function has_access( int $user_id, array $levels ): bool {
		if ( $user_id < 1 || empty( $levels ) ) { return false; }
		$user = get_userdata( $user_id );
		if ( ! $user ) { return false; }
		$user_levels = apply_filters( 'music_wave_vip_membership_levels_for_user', isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array(), $user_id );
		if ( ! is_array( $user_levels ) ) { return false; }
		return (bool) array_intersect( array_map( 'sanitize_key', $levels ), array_map( 'sanitize_key', $user_levels ) );
	}
}
