<?php
/**
 * Deny-by-default access decision service.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Access;

use InvalidArgumentException;
use ManaCore\MusicWave\Core\Commerce\PurchaseChecker;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class AccessPolicyEngine {
	/** @var ReleaseRepository */
	private $repository;

	/** @var PurchaseChecker */
	private $purchase_checker;

	/** @var MembershipProvider */
	private $membership_provider;

	/** @var ManualAccessProvider */
	private $manual_provider;

	public function __construct(
		ReleaseRepository $repository,
		PurchaseChecker $purchase_checker,
		MembershipProvider $membership_provider = null,
		ManualAccessProvider $manual_provider = null
	) {
		$this->repository          = $repository;
		$this->purchase_checker    = $purchase_checker;
		$this->membership_provider = null !== $membership_provider ? $membership_provider : new NullMembershipProvider();
		$this->manual_provider     = null !== $manual_provider ? $manual_provider : new NullManualAccessProvider();
	}

	public function decide( int $release_id, AccessSubject $subject ): AccessDecision {
		$decision = $this->evaluate( $release_id, $subject );
		$filtered = apply_filters( 'music_wave_access_decision', $decision, $release_id, $subject );

		return $filtered instanceof AccessDecision ? $filtered : $decision;
	}

	private function evaluate( int $release_id, AccessSubject $subject ): AccessDecision {
		try {
			$mode = (string) $this->repository->get( $release_id, 'mw_access_mode' );
		} catch ( InvalidArgumentException $exception ) {
			return AccessDecision::deny( 'invalid_release' );
		}

		$mode = sanitize_key( $mode );
		if ( $subject->can( 'manage_options' ) ) {
			return AccessDecision::allow( 'administrator_override', $mode );
		}

		if ( 'public' === $mode ) {
			return AccessDecision::allow( 'public', $mode );
		}

		if ( $subject->user_id() > 0 && $this->manual_provider->has_access( $subject->user_id(), $release_id ) ) {
			return AccessDecision::allow( 'manual_grant', $mode );
		}

		switch ( $mode ) {
			case 'purchase':
				return $this->purchase_checker->user_owns_release( $subject->user_id(), $release_id )
					? AccessDecision::allow( 'purchase', $mode )
					: AccessDecision::deny( 'purchase_required', $mode );
			case 'membership':
				return $this->membership_access( $release_id, $subject, $mode );
			case 'purchase_or_membership':
				if ( $this->purchase_checker->user_owns_release( $subject->user_id(), $release_id ) ) {
					return AccessDecision::allow( 'purchase', $mode );
				}

				return $this->membership_access( $release_id, $subject, $mode );
			case 'restricted':
				return AccessDecision::deny( 'restricted', $mode );
			default:
				return AccessDecision::deny( 'unknown_mode', '' !== $mode ? $mode : 'restricted' );
		}
	}

	private function membership_access( int $release_id, AccessSubject $subject, string $mode ): AccessDecision {
		$levels = $this->repository->get( $release_id, 'mw_membership_levels' );
		$levels = is_array( $levels ) ? array_values( array_filter( array_map( 'sanitize_key', $levels ) ) ) : array();
		if ( $subject->user_id() > 0 && ! empty( $levels ) && $this->membership_provider->has_access( $subject->user_id(), $levels ) ) {
			return AccessDecision::allow( 'membership', $mode );
		}

		return AccessDecision::deny( 'membership_required', $mode );
	}
}
