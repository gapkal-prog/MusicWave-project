<?php
/**
 * Protected-file storage operations for the local VIP provider.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use FilesystemIterator;
use WP_Error;

final class ProtectedAssetStorage {
	/**
	 * Get the configured protected root when it is safe to use.
	 *
	 * @return string|false
	 */
	public function root() {
		$settings        = VipSettings::all();
		$configured_root = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) MUSIC_WAVE_VIP_PROTECTED_ROOT : (string) $settings['protected_root'];
		$root            = '' === $configured_root ? $this->default_root() : realpath( $configured_root );
		$public_root     = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;

		if ( false === $root || ! is_dir( $root ) || ! is_readable( $root ) || ( false !== $public_root && ( $root === $public_root || $this->is_within( $root, $public_root ) ) ) ) {
			return false;
		}

		return $root;
	}

	/**
	 * Provision the default private directory beside the WordPress web root.
	 *
	 * A production site can still override this path with the constant or
	 * settings field. The default removes setup friction while keeping files
	 * outside the publicly served WordPress directory.
	 *
	 * @return string|false
	 */
	private function default_root() {
		if ( ! defined( 'ABSPATH' ) ) {
			return false;
		}

		$public_root = rtrim( ABSPATH, '/\\' );
		$parent      = dirname( $public_root );
		$root        = $parent . DIRECTORY_SEPARATOR . 'musicwave-private';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return false;
		}

		return realpath( $root );
	}

	/**
	 * Resolve a provider asset ID to a protected local file.
	 *
	 * @return string|false
	 */
	public function resolve( string $asset_id ) {
		if ( 0 !== strpos( $asset_id, 'local:' ) ) {
			return false;
		}

		$root     = $this->root();
		$relative = substr( $asset_id, 6 );
		if ( false === $root || '' === $relative || false !== strpos( $relative, '..' ) ) {
			return false;
		}

		$file = realpath( $root . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $relative ) );
		if ( false === $file || ! $this->is_within( $file, $root ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Return a paginated list of protected files for authorized editors.
	 *
	 * @return array<string, array<int, array<string, int|string>>|bool>
	 */
	public function list_assets( string $search = '', int $page = 1, int $per_page = 50 ): array {
		$root = $this->root();
		if ( false === $root ) {
			return array(
				'items'    => array(),
				'has_more' => false,
			);
		}

		$needle      = strtolower( trim( $search ) );
		$page        = max( 1, $page );
		$per_page    = min( 100, max( 1, $per_page ) );
		$offset      = ( $page - 1 ) * $per_page;
		$matches     = 0;
		$assets      = array();
		$has_more    = false;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		/** @var SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}

			$path = $file->getRealPath();
			if ( false === $path || ! $this->is_within( $path, $root ) ) {
				continue;
			}

			$relative = ltrim( substr( $path, strlen( rtrim( $root, DIRECTORY_SEPARATOR ) ) ), DIRECTORY_SEPARATOR );
			$identifier = 'local:' . str_replace( DIRECTORY_SEPARATOR, '/', $relative );
			if ( '' !== $needle && false === strpos( strtolower( $identifier ), $needle ) ) {
				continue;
			}

			if ( $matches++ < $offset ) {
				continue;
			}
			if ( count( $assets ) >= $per_page ) {
				$has_more = true;
				break;
			}

			$assets[] = $this->asset( $identifier, $relative, $path, (int) $file->getSize() );
		}

		return array(
			'items'    => $assets,
			'has_more' => $has_more,
		);
	}

	/**
	 * Store an uploaded file directly outside the public web root.
	 *
	 * @param array<string, mixed> $file PHP upload payload.
	 * @return array<string, int|string>|WP_Error
	 */
	public function upload( array $file ) {
		$root = $this->root();
		if ( false === $root || ! is_writable( $root ) ) {
			return new WP_Error( 'mw_protected_asset_root', __( 'The protected asset directory is unavailable or not writable.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'mw_protected_asset_upload', __( 'The protected asset upload was invalid.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$original_name = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$extension     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		$allowed        = $this->allowed_extensions();
		$size           = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( '' === $original_name || ! in_array( $extension, $allowed, true ) || $size < 1 || $size > wp_max_upload_size() ) {
			return new WP_Error( 'mw_protected_asset_type', __( 'The asset type or size is not allowed.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$filename    = wp_unique_filename( $root, $original_name );
		$destination = $root . DIRECTORY_SEPARATOR . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return new WP_Error( 'mw_protected_asset_move', __( 'The protected asset could not be stored.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		return $this->asset( 'local:' . $filename, $filename, $destination, $size );
	}

	/**
	 * Copy one WordPress Media Library file to protected storage.
	 *
	 * The original attachment remains in the WordPress uploads directory. The
	 * protected copy is the only file referenced by the secure download flow.
	 *
	 * @return array<string, int|string>|WP_Error
	 */
	public function import_attachment( int $attachment_id ) {
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'mw_protected_asset_attachment', __( 'Choose a valid Media Library file.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$source = get_attached_file( $attachment_id );
		$source = is_string( $source ) ? realpath( $source ) : false;
		$uploads = wp_get_upload_dir();
		$uploads_root = isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( false === $source || false === $uploads_root || ! $this->is_within( $source, $uploads_root ) || ! is_file( $source ) || ! is_readable( $source ) ) {
			return new WP_Error( 'mw_protected_asset_attachment_file', __( 'The selected Media Library file is unavailable.', 'music-wave-vip' ), array( 'status' => 404 ) );
		}

		$root = $this->root();
		if ( false === $root || ! is_writable( $root ) ) {
			return new WP_Error( 'mw_protected_asset_root', __( 'The protected asset directory is unavailable or not writable.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		$filename  = sanitize_file_name( basename( $source ) );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$size      = filesize( $source );
		if ( '' === $filename || ! in_array( $extension, $this->allowed_extensions(), true ) || false === $size || $size < 1 || $size > wp_max_upload_size() ) {
			return new WP_Error( 'mw_protected_asset_type', __( 'The asset type or size is not allowed.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$filename    = wp_unique_filename( $root, $filename );
		$destination = $root . DIRECTORY_SEPARATOR . $filename;
		if ( ! copy( $source, $destination ) ) {
			return new WP_Error( 'mw_protected_asset_copy', __( 'The Media Library file could not be copied to protected storage.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		return $this->asset( 'local:' . $filename, $filename, $destination, (int) $size );
	}

	/**
	 * Build editor-safe file details without exposing filesystem paths.
	 *
	 * @return array<string, int|string>
	 */
	private function asset( string $identifier, string $name, string $file, int $size ): array {
		$metadata = $this->audio_metadata( $file, $size );

		return array_merge(
			array(
				'id'   => $identifier,
				'name' => $name,
				'size' => $size,
			),
			$metadata
		);
	}

	/**
	 * Read and cache safe technical metadata for audio files.
	 *
	 * The media reader is loaded only on demand, after the file is already
	 * stored outside the public web root. Its output is reduced to editor-safe
	 * values; paths and embedded URLs are never returned.
	 *
	 * @return array<string, int|string>
	 */
	private function audio_metadata( string $file, int $size ): array {
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$modified  = filemtime( $file );
		$cache_key = 'music_wave_vip_asset_' . md5( $file . '|' . (string) $size . '|' . (string) $modified );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$metadata = array(
			'format'   => $extension,
			'bitrate'  => 0,
			'duration' => 0,
		);
		if ( ! in_array( $extension, array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac' ), true ) || ! $this->load_audio_metadata_reader() ) {
			set_transient( $cache_key, $metadata, 12 * HOUR_IN_SECONDS );
			return $metadata;
		}

		$audio = wp_read_audio_metadata( $file );
		if ( is_array( $audio ) ) {
			$format = isset( $audio['fileformat'] ) && is_scalar( $audio['fileformat'] ) ? sanitize_key( strtolower( (string) $audio['fileformat'] ) ) : '';
			$bitrate = isset( $audio['bitrate'] ) && is_scalar( $audio['bitrate'] ) ? absint( $audio['bitrate'] ) : 0;
			$duration = isset( $audio['length'] ) && is_scalar( $audio['length'] ) ? absint( $audio['length'] ) : 0;

			if ( '' !== $format ) {
				$metadata['format'] = $format;
			}
			if ( $bitrate > 0 ) {
				$metadata['bitrate'] = (int) round( $bitrate / 1000 );
			}
			if ( $duration > 0 ) {
				$metadata['duration'] = $duration;
			}
		}

		set_transient( $cache_key, $metadata, 12 * HOUR_IN_SECONDS );
		return $metadata;
	}

	private function load_audio_metadata_reader(): bool {
		if ( function_exists( 'wp_read_audio_metadata' ) ) {
			return true;
		}
		if ( ! defined( 'ABSPATH' ) ) {
			return false;
		}

		$media_file = ABSPATH . 'wp-admin/includes/media.php';
		if ( ! is_readable( $media_file ) ) {
			return false;
		}

		require_once $media_file;

		return function_exists( 'wp_read_audio_metadata' );
	}

	/**
	 * @return array<int, string>
	 */
	private function allowed_extensions(): array {
		$allowed = apply_filters(
			'music_wave_vip_allowed_asset_extensions',
			array( 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac', 'zip' )
		);

		return is_array( $allowed ) ? array_values( array_filter( array_map( 'sanitize_key', $allowed ) ) ) : array();
	}

	private function is_within( string $path, string $root ): bool {
		$prefix = rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		return 0 === strpos( $path, $prefix );
	}
}
