<?php
/**
 * Immutable identity used by the access policy engine.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Access;

final class AccessSubject {
	/** @var int */
	private $user_id;

	/** @var array<int, string> */
	private $roles;

	/** @var array<int, string> */
	private $capabilities;

	/**
	 * @param array<int, string> $roles
	 * @param array<int, string> $capabilities
	 */
	public function __construct( int $user_id = 0, array $roles = array(), array $capabilities = array() ) {
		$this->user_id      = max( 0, $user_id );
		$this->roles        = array_values( array_unique( array_map( 'sanitize_key', $roles ) ) );
		$this->capabilities = array_values( array_unique( array_map( 'sanitize_key', $capabilities ) ) );
	}

	public function user_id(): int {
		return $this->user_id;
	}

	public function can( string $capability ): bool {
		return in_array( sanitize_key( $capability ), $this->capabilities, true );
	}

	/** @return self */
	public static function current(): self {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new self();
		}

		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return new self();
		}

		$capabilities = array();
		foreach ( array( 'manage_options', 'edit_posts', 'edit_mw_releases' ) as $capability ) {
			if ( user_can( $user_id, $capability ) ) {
				$capabilities[] = $capability;
			}
		}

		return new self( $user_id, (array) $user->roles, $capabilities );
	}
}
