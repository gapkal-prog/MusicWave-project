<?php
/**
 * Opaque browser tickets for signed delivery tokens.
 *
 * The signed token embeds structured claims (user, release, expiry, asset)
 * in a browser-readable base64 payload. Browsers must only ever see a random
 * ticket identifier; the signed token stays server-side with a matching TTL
 * (PROJECT_PLAN.md Stage 2 deliverable 5).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

final class OpaqueTicketStore {
	public const PREFIX = 'mwt_';

	/**
	 * Store a signed token and return the opaque browser ticket.
	 */
	public function wrap( string $signed_token, int $ttl ): string {
		$ticket = self::PREFIX . bin2hex( random_bytes( 20 ) );
		set_transient( $this->key( $ticket ), $signed_token, max( 30, min( $ttl, 900 ) ) );

		return $ticket;
	}

	/**
	 * Exchange an opaque ticket for its stored signed token.
	 *
	 * Tickets are not deleted on read: stream tickets stay valid for byte-range
	 * request sequences, and download replay protection is enforced by the
	 * one-time replay store on the inner token id.
	 */
	public function unwrap( string $ticket ): ?string {
		if ( 1 !== preg_match( '/^' . self::PREFIX . '[a-f0-9]{40}$/', $ticket ) ) {
			return null;
		}

		$stored = get_transient( $this->key( $ticket ) );

		return is_string( $stored ) && '' !== $stored ? $stored : null;
	}

	/**
	 * Whether a value is shaped like an opaque browser ticket.
	 */
	public static function looks_like_ticket( string $value ): bool {
		return 0 === strpos( $value, self::PREFIX );
	}

	private function key( string $ticket ): string {
		return 'mw_ticket_' . hash( 'sha256', $ticket );
	}
}
