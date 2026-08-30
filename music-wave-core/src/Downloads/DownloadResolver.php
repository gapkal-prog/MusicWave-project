<?php
declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Downloads;

use ManaCore\MusicWave\Core\Access\AccessPolicyEngine;
use ManaCore\MusicWave\Core\Access\AccessSubject;
use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class DownloadResolver {
	/** @var ReleaseRepository */ private $releases;
	/** @var AccessPolicyEngine */ private $policy;
	/** @var DownloadTokenService */ private $tokens;
	/** @var ReplayStore */ private $replays;
	/** @var DownloadProvider */ private $provider;
	/** @var OpaqueTicketStore */ private $tickets;
	/** @var string Last deny reason for richer REST errors. */
	private $last_deny_reason = '';

	public function last_deny_reason(): string {
		return $this->last_deny_reason;
	}

	public function __construct( ReleaseRepository $releases, AccessPolicyEngine $policy, DownloadTokenService $tokens, ReplayStore $replays, DownloadProvider $provider, ?OpaqueTicketStore $tickets = null ) {
		$this->releases = $releases;
		$this->policy   = $policy;
		$this->tokens   = $tokens;
		$this->replays  = $replays;
		$this->provider = $provider;
		$this->tickets  = null !== $tickets ? $tickets : new OpaqueTicketStore(); }

	/**
	 * Whether the current visitor may reach the delivery endpoints at all.
	 *
	 * Logged-in visitors always pass; guests pass exactly when the access
	 * policy itself allows them (public releases or a policy-level open gate
	 * such as the VIP "everyone" delivery mode).
	 */
	public function can_request( int $release_id ): bool {
		if ( $release_id < 1 ) {
			return false;
		}

		return $this->policy->decide( $release_id, AccessSubject::current() )->is_allowed();
	}

	public function issue( int $release_id, AccessSubject $subject, string $binding, string $asset_key = '', string $purpose = 'download' ): ?string {
		$this->last_deny_reason = '';
		// Policy-driven issuance: the deny-by-default engine stays the single
		// source of truth. Guests receive anonymous (user 0) tokens only when
		// the decision itself allows them — public releases or an explicit
		// open gate — while purchase and restricted modes keep denying.
		if ( ! $this->policy->decide( $release_id, $subject )->is_allowed() ) {
			$this->last_deny_reason = 'entitlement';
			$this->audit( 'token_denied', $release_id, $subject->user_id(), array( 'reason' => 'entitlement' ) );
			return null; }
		$asset = $this->asset( $release_id, $asset_key );
		if ( null === $asset ) {
			$this->last_deny_reason = 'unknown_asset';
			$this->audit( 'token_denied', $release_id, $subject->user_id(), array( 'reason' => 'unknown_asset' ) );
			return null; }
		// Graceful degradation: when VIP is inactive the Null provider cannot
		// serve protected assets. Fail fast with a distinct audit reason so the
		// REST layer can surface a friendly message instead of a generic 403.
		if ( $this->provider instanceof NullDownloadProvider && ( 0 === strpos( $asset['asset_id'], 'vip:' ) || 0 === strpos( $asset['asset_id'], 'local:' ) ) ) {
			$this->last_deny_reason = 'provider_unavailable';
			$this->audit( 'token_denied', $release_id, $subject->user_id(), array( 'reason' => 'provider_unavailable' ) );
			return null;
		}
		$purpose = in_array( $purpose, array( 'download', 'stream' ), true ) ? $purpose : 'download';
		if ( 'stream' === $purpose && ! $this->provider instanceof StreamableDownloadProvider ) {
			$this->last_deny_reason = 'stream_unsupported';
			$this->audit( 'token_denied', $release_id, $subject->user_id(), array( 'reason' => 'stream_unsupported' ) );
			return null; }
		$this->audit(
			'token_issued',
			$release_id,
			$subject->user_id(),
			array(
				'asset_key' => $asset['key'],
				'purpose'   => $purpose,
			)
		);
		$ttl = 'stream' === $purpose ? 900 : 300;

		// Browsers only ever receive an opaque random ticket; the signed token
		// with its structured claims never leaves the server
		// (PROJECT_PLAN.md Stage 2 deliverable 5).
		return $this->tickets->wrap( $this->tokens->issue( $release_id, $subject->user_id(), $ttl, $binding, $asset['key'], $purpose ), $ttl );
	}

	/**
	 * Accept an opaque browser ticket or (legacy, short-lived) signed token.
	 */
	private function exchange( string $token ): string {
		if ( ! OpaqueTicketStore::looks_like_ticket( $token ) ) {
			return $token;
		}
		$stored = $this->tickets->unwrap( $token );

		return null !== $stored ? $stored : '';
	}

	public function deliver( int $release_id, int $user_id, string $token, string $binding ): bool {
		$claims = $this->tokens->verify( $this->exchange( $token ), $binding );
		if ( null === $claims || 'download' !== $claims->purpose() || $claims->release_id() !== $release_id || $claims->user_id() !== $user_id ) {
			$this->audit( 'download_denied', $release_id, $user_id, array( 'reason' => null === $claims ? 'invalid_token' : 'claim_mismatch' ) );
			return false; }
		$subject  = AccessSubject::for_user( $user_id );
		$decision = $this->policy->decide( $release_id, $subject );
		if ( ! $decision->is_allowed() ) {
			$this->audit( 'download_denied', $release_id, $user_id, array( 'reason' => $decision->reason() ) );
			return false; }
		// Quota runs before the one-time replay token is consumed so a
		// quota-blocked delivery does not burn the user's link.
		if ( ! $this->within_daily_quota( $user_id ) ) {
			$this->audit( 'download_denied', $release_id, $user_id, array( 'reason' => 'quota_exceeded' ) );
			return false; }
		if ( ! $this->replays->consume( $claims->token_id(), $claims->expires_at() ) ) {
			$this->audit( 'download_denied', $release_id, $user_id, array( 'reason' => 'replay' ) );
			return false; }
		$asset     = $this->asset( $release_id, $claims->asset_key() );
		$delivered = null !== $asset && $this->provider->deliver( $asset['asset_id'], $claims );
		if ( $delivered ) {
			$this->count_delivery( $user_id );
		}
		$this->audit( $delivered ? 'download_delivered' : 'download_denied', $release_id, $user_id );
		return $delivered;
	}

	/**
	 * Optional per-user daily delivery quota (disabled by default).
	 *
	 * Sites can bound bulk exfiltration through a single compromised account
	 * via the `music_wave_download_daily_quota` filter
	 * (PROJECT_PLAN.md Stage 2 deliverable 4).
	 */
	private function within_daily_quota( int $user_id ): bool {
		$quota = (int) apply_filters( 'music_wave_download_daily_quota', 0, $user_id );
		if ( $quota < 1 ) {
			return true;
		}
		$count = get_transient( $this->quota_key( $user_id ) );

		return ( false === $count ? 0 : (int) $count ) < $quota;
	}

	private function count_delivery( int $user_id ): void {
		$key   = $this->quota_key( $user_id );
		$count = get_transient( $key );
		set_transient( $key, ( false === $count ? 0 : (int) $count ) + 1, defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 );
	}

	private function quota_key( int $user_id ): string {
		if ( $user_id > 0 ) {
			return 'mw_dl_quota_' . $user_id . '_' . gmdate( 'Ymd' );
		}

		// Guests get a per-IP bucket (mirroring DownloadRateLimiter) so one
		// visitor cannot exhaust — or hide inside — a shared global counter.
		return 'mw_dl_quota_g_' . hash( 'sha256', $this->guest_ip_identity() ) . '_' . gmdate( 'Ymd' );
	}

	/**
	 * Stable identity for guest quota buckets: a validated REMOTE_ADDR or a
	 * shared fallback when the address is unavailable.
	 */
	private function guest_ip_identity(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ip  = function_exists( 'filter_var' ) && false !== filter_var( $raw, FILTER_VALIDATE_IP ) ? $raw : '';

		return '' !== $ip ? $ip : 'unknown';
	}

	public function stream( int $release_id, int $user_id, string $token, string $binding ): bool {
		$claims = $this->tokens->verify( $this->exchange( $token ), $binding );
		if ( null === $claims || 'stream' !== $claims->purpose() || $claims->release_id() !== $release_id || $claims->user_id() !== $user_id ) {
			$this->audit( 'stream_denied', $release_id, $user_id, array( 'reason' => null === $claims ? 'invalid_token' : 'claim_mismatch' ) );
			return false;
		}
		if ( ! $this->provider instanceof StreamableDownloadProvider ) {
			$this->audit( 'stream_denied', $release_id, $user_id, array( 'reason' => 'stream_unsupported' ) );
			return false;
		}
		$decision = $this->policy->decide( $release_id, AccessSubject::for_user( $user_id ) );
		if ( ! $decision->is_allowed() ) {
			$this->audit( 'stream_denied', $release_id, $user_id, array( 'reason' => $decision->reason() ) );
			return false;
		}
		$asset    = $this->asset( $release_id, $claims->asset_key() );
		$streamed = null !== $asset && $this->provider->stream( $asset['asset_id'], $claims );
		$this->audit( $streamed ? 'stream_delivered' : 'stream_denied', $release_id, $user_id );

		return $streamed;
	}

	/**
	 * Return the selected private asset with legacy single-file compatibility.
	 *
	 * @return array<string, string>|null
	 */
	private function asset( int $release_id, string $requested_key ) {
		try {
			$variants = $this->releases->get( $release_id, 'mw_download_assets' );
			$legacy   = $this->releases->get( $release_id, 'mw_download_asset_id' );
		} catch ( \InvalidArgumentException $exception ) {
			return null;
		}

		$assets = is_array( $variants ) ? $variants : array();
		if ( empty( $assets ) && is_string( $legacy ) && '' !== $legacy ) {
			$assets[] = array(
				'key'      => 'standard',
				'label'    => 'Standard download',
				'asset_id' => $legacy,
			);
		}
		if ( empty( $assets ) ) {
			return null;
		}

		$key = sanitize_key( $requested_key );
		if ( '' === $key ) {
			return isset( $assets[0] ) && is_array( $assets[0] ) ? $this->valid_asset( $assets[0] ) : null;
		}
		foreach ( $assets as $asset ) {
			if ( is_array( $asset ) && isset( $asset['key'] ) && sanitize_key( (string) $asset['key'] ) === $key ) {
				return $this->valid_asset( $asset );
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $asset Stored asset variant.
	 * @return array<string, string>|null
	 */
	private function valid_asset( array $asset ) {
		$key      = isset( $asset['key'] ) ? sanitize_key( (string) $asset['key'] ) : '';
		$label    = isset( $asset['label'] ) ? sanitize_text_field( (string) $asset['label'] ) : '';
		$asset_id = isset( $asset['asset_id'] ) ? sanitize_text_field( (string) $asset['asset_id'] ) : '';

		return '' !== $key && '' !== $label && '' !== $asset_id ? array(
			'key'      => $key,
			'label'    => $label,
			'asset_id' => $asset_id,
		) : null;
	}

	/**
	 * Emit one structured, PII-free delivery audit event.
	 *
	 * @param string               $event      Event key.
	 * @param int                  $release_id Release ID.
	 * @param int                  $user_id    Acting user ID.
	 * @param array<string, mixed> $context    Structured event context
	 *                                         (reason, asset_key, purpose).
	 */
	private function audit( string $event, int $release_id, int $user_id, array $context = array() ): void {
		$context = array_merge(
			array(
				'timestamp' => time(),
			),
			$context
		);
		do_action( 'music_wave_download_event', $event, $release_id, $user_id, $context );
	}
}
