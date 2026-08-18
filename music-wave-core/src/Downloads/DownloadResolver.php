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
	public function __construct( ReleaseRepository $releases, AccessPolicyEngine $policy, DownloadTokenService $tokens, ReplayStore $replays, DownloadProvider $provider ) {
		$this->releases = $releases;
		$this->policy   = $policy;
		$this->tokens   = $tokens;
		$this->replays  = $replays;
		$this->provider = $provider; }

	public function issue( int $release_id, AccessSubject $subject, string $binding, string $asset_key = '', string $purpose = 'download' ): ?string {
		if ( $subject->user_id() < 1 || ! $this->policy->decide( $release_id, $subject )->is_allowed() ) {
			$this->audit( 'token_denied', $release_id, $subject->user_id() );
			return null; }
		$asset = $this->asset( $release_id, $asset_key );
		if ( null === $asset ) {
			$this->audit( 'token_denied', $release_id, $subject->user_id() );
			return null; }
		$purpose = in_array( $purpose, array( 'download', 'stream' ), true ) ? $purpose : 'download';
		if ( 'stream' === $purpose && ! $this->provider instanceof StreamableDownloadProvider ) {
			$this->audit( 'token_denied', $release_id, $subject->user_id() );
			return null; }
		$this->audit( 'token_issued', $release_id, $subject->user_id() );
		return $this->tokens->issue( $release_id, $subject->user_id(), 'stream' === $purpose ? 900 : 300, $binding, $asset['key'], $purpose );
	}

	public function deliver( int $release_id, int $user_id, string $token, string $binding ): bool {
		$claims = $this->tokens->verify( $token, $binding );
		if ( null === $claims || 'download' !== $claims->purpose() || $claims->release_id() !== $release_id || $claims->user_id() !== $user_id ) {
			$this->audit( 'download_denied', $release_id, $user_id );
			return false; }
		$subject = new AccessSubject( $user_id );
		if ( ! $this->policy->decide( $release_id, $subject )->is_allowed() || ! $this->replays->consume( $claims->token_id(), $claims->expires_at() ) ) {
			$this->audit( 'download_denied', $release_id, $user_id );
			return false; }
		$asset     = $this->asset( $release_id, $claims->asset_key() );
		$delivered = null !== $asset && $this->provider->deliver( $asset['asset_id'], $claims );
		$this->audit( $delivered ? 'download_delivered' : 'download_denied', $release_id, $user_id );
		return $delivered;
	}

	public function stream( int $release_id, int $user_id, string $token, string $binding ): bool {
		$claims = $this->tokens->verify( $token, $binding );
		if ( null === $claims || 'stream' !== $claims->purpose() || $claims->release_id() !== $release_id || $claims->user_id() !== $user_id || ! $this->provider instanceof StreamableDownloadProvider ) {
			$this->audit( 'stream_denied', $release_id, $user_id );
			return false;
		}
		if ( ! $this->policy->decide( $release_id, new AccessSubject( $user_id ) )->is_allowed() ) {
			$this->audit( 'stream_denied', $release_id, $user_id );
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

	private function audit( string $event, int $release_id, int $user_id ): void {
		do_action( 'music_wave_download_event', $event, $release_id, $user_id );
	}
}
