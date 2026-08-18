<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Vip;

use ManaCore\MusicWave\Core\Downloads\DownloadProvider;
use ManaCore\MusicWave\Core\Downloads\DownloadTokenClaims;
use ManaCore\MusicWave\Core\Downloads\StreamableDownloadProvider;

final class ProtectedFileProvider implements DownloadProvider, StreamableDownloadProvider {
	/** @var ProtectedAssetStorage */
	private $storage;

	public function __construct( ?ProtectedAssetStorage $storage = null ) {
		$this->storage = null === $storage ? new ProtectedAssetStorage() : $storage;
	}

	public function deliver( string $asset_id, DownloadTokenClaims $claims ): bool {
		unset( $claims );
		return $this->output( $asset_id, false );
	}

	public function stream( string $asset_id, DownloadTokenClaims $claims ): bool {
		unset( $claims );
		return $this->output( $asset_id, true );
	}

	private function output( string $asset_id, bool $inline ): bool {
		$file = $this->storage->resolve( $asset_id );
		if ( false === $file ) {
			return false;
		}

		$size = filesize( $file );
		if ( false === $size || $size < 1 ) {
			return false;
		}

		$range = $this->requested_range( (int) $size );
		nocache_headers();
		header( 'Accept-Ranges: bytes' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Accel-Buffering: no' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . ( $inline ? $this->content_type( $file ) : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . $this->content_disposition( $file, $inline ) );
		if ( false === $range ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . (string) $size );
			exit;
		}

		$start  = $range[0];
		$length = $range[1];
		if ( 0 !== $start || $length !== (int) $size ) {
			status_header( 206 );
			header( 'Content-Range: bytes ' . (string) $start . '-' . (string) ( $start + $length - 1 ) . '/' . (string) $size );
		} else {
			status_header( 200 );
		}
		header( 'Content-Length: ' . (string) $length );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		$this->stream_file( $file, $start, $length );
		exit;
	}

	private function content_disposition( string $file, bool $inline ): string {
		$name     = basename( $file );
		$fallback = preg_replace( '/[^A-Za-z0-9._-]/', '_', $name );
		$fallback = is_string( $fallback ) && '' !== $fallback ? $fallback : 'musicwave-audio';
		$mode     = $inline ? 'inline' : 'attachment';

		return $mode . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode( $name );
	}

	private function content_type( string $file ): string {
		$types     = array(
			'mp3'  => 'audio/mpeg',
			'm4a'  => 'audio/mp4',
			'aac'  => 'audio/aac',
			'ogg'  => 'audio/ogg',
			'wav'  => 'audio/wav',
			'flac' => 'audio/flac',
		);
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

		return isset( $types[ $extension ] ) ? $types[ $extension ] : 'application/octet-stream';
	}

	/**
	 * @return array<int, int>|false
	 */
	private function requested_range( int $size ) {
		$header = isset( $_SERVER['HTTP_RANGE'] ) && is_string( $_SERVER['HTTP_RANGE'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) ) : '';
		if ( '' === $header ) {
			return array( 0, $size );
		}
		if ( 1 !== preg_match( '/^bytes=(\d*)-(\d*)$/', $header, $matches ) ) {
			return false;
		}

		$start = '' === $matches[1] ? null : (int) $matches[1];
		$end   = '' === $matches[2] ? null : (int) $matches[2];
		if ( null === $start && null === $end ) {
			return false;
		}
		if ( null === $start ) {
			$length = min( $end, $size );
			return $length < 1 ? false : array( $size - $length, $length );
		}
		if ( $start >= $size ) {
			return false;
		}
		$end = null === $end ? $size - 1 : min( $end, $size - 1 );
		if ( $end < $start ) {
			return false;
		}

		return array( $start, $end - $start + 1 );
	}

	private function stream_file( string $file, int $start, int $length ): void {
		$handle = fopen( $file, 'rb' );
		if ( false === $handle ) {
			return;
		}

		fseek( $handle, $start );
		while ( $length > 0 && ! feof( $handle ) ) {
			$chunk = fread( $handle, min( 1024 * 1024, $length ) );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary file stream; escaping would corrupt audio bytes.
			$length -= strlen( $chunk );
			flush();
		}
		fclose( $handle );
	}
}
