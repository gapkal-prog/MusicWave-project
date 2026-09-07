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
	/** @var ProtectedAssetRegistry */
	private $registry;

	public function __construct( ?ProtectedAssetRegistry $registry = null ) {
		$this->registry = null !== $registry ? $registry : new ProtectedAssetRegistry();
	}

	/**
	 * Expose the opaque inventory used for assignment validation.
	 */
	public function registry(): ProtectedAssetRegistry {
		return $this->registry;
	}
	/**
	 * Get the configured protected root when it is safe to use.
	 *
	 * @return string|false
	 */
	public function root() {
		$settings        = VipSettings::all();
		$configured_root = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) constant( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) : (string) $settings['protected_root'];
		$root            = '' === $configured_root ? $this->default_root() : realpath( $configured_root );

		if ( false === $root || ! is_dir( $root ) || ! is_readable( $root ) || $this->is_web_reachable( $root ) ) {
			return false;
		}

		return $root;
	}

	/**
	 * Whether a directory sits inside a tree the web server publicly serves.
	 *
	 * Checks both ABSPATH and the server document root: on subdirectory
	 * installations the WordPress parent directory is often still inside the
	 * served tree, which made the previous “beside ABSPATH” default unsafe
	 * (PROJECT_PLAN.md Stage 2 deliverable 1).
	 */
	public function is_web_reachable( string $directory ): bool {
		$public_root = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;
		if ( false !== $public_root && ( $directory === $public_root || $this->is_within( $directory, $public_root ) ) ) {
			return true;
		}

		$doc_root_raw       = isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? (string) $_SERVER['DOCUMENT_ROOT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$doc_root_unslashed = function_exists( 'wp_unslash' ) ? wp_unslash( $doc_root_raw ) : $doc_root_raw; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $doc_root_raw && false !== strpos( $doc_root_raw, '\\' ) && false === strpos( $doc_root_unslashed, '\\' ) && false === strpos( $doc_root_unslashed, '/' ) ) {
			$doc_root_unslashed = $doc_root_raw;
		}
		$doc_root_sanitized = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $doc_root_unslashed ) : $doc_root_unslashed;
		if ( function_exists( 'wp_normalize_path' ) ) {
			$doc_root_sanitized = wp_normalize_path( $doc_root_sanitized );
		} else {
			$doc_root_sanitized = str_replace( '\\', '/', $doc_root_sanitized );
		}
		$document_root = '' !== $doc_root_sanitized ? realpath( $doc_root_sanitized ) : false;
		if ( false !== $document_root && ( $directory === $document_root || $this->is_within( $directory, $document_root ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Structured provisioning checks for operators.
	 *
	 * Returns check => passed pairs used by the settings screen so operators
	 * see exactly why delivery is disabled instead of a silent failure.
	 *
	 * @return array<string, bool>
	 */
	public function preflight(): array {
		$settings        = VipSettings::all();
		$configured_root = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) constant( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) : (string) $settings['protected_root'];
		$candidate       = '' === $configured_root ? $this->default_root() : realpath( $configured_root );

		$exists      = false !== $candidate && is_dir( $candidate );
		$readable    = $exists && is_readable( $candidate );
		$writable    = $exists && wp_is_writable( $candidate );
		$outside_web = $exists && ! $this->is_web_reachable( (string) $candidate );
		$deny_files  = $exists && file_exists( $candidate . DIRECTORY_SEPARATOR . '.htaccess' );

		return array(
			'root_resolved'    => false !== $candidate,
			'directory_exists' => $exists,
			'readable'         => $readable,
			'writable'         => $writable,
			'outside_web_root' => $outside_web,
			'deny_files'       => $deny_files,
		);
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

		// No unsafe automatic guarantee: when the computed default would still
		// be publicly served (subdirectory installations), refuse to provision
		// it and require an explicit, operator-verified path instead
		// (PROJECT_PLAN.md Stage 2 deliverable 1).
		$parent_real = realpath( $parent );
		if ( false !== $parent_real && $this->is_web_reachable( $parent_real ) ) {
			return false;
		}

		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return false;
		}

		$resolved = realpath( $root );
		if ( false !== $resolved ) {
			$this->harden_directory( $resolved );
		}

		return $resolved;
	}

	/**
	 * Write defense-in-depth deny files into a protected directory.
	 *
	 * These are a second layer only; the primary control remains keeping the
	 * directory outside every publicly served tree.
	 *
	 * @return void
	 */
	public function harden_directory( string $root ): void {
		$deny_files = array(
			'.htaccess'  => "Require all denied\nDeny from all\n",
			'web.config' => "<?xml version=\"1.0\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.html' => '',
		);

		foreach ( $deny_files as $name => $contents ) {
			$path = $root . DIRECTORY_SEPARATOR . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing hardening stubs into the private (non-WP) directory.
			}
		}
	}

	/**
	 * Resolve a provider asset ID to a protected local file.
	 *
	 * @return string|false
	 */
	public function resolve( string $asset_id ) {
		if ( 0 === strpos( $asset_id, ProtectedAssetRegistry::PREFIX ) ) {
			return $this->resolve_registered( $asset_id );
		}
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
	 * Resolve an opaque registry identifier with a delivery-time integrity check.
	 *
	 * The stored size fingerprint must still match the file on disk, so a
	 * replaced or truncated master fails closed instead of being served
	 * (PROJECT_PLAN.md Stage 2 deliverable 6).
	 *
	 * @return string|false
	 */
	private function resolve_registered( string $asset_id ) {
		$row  = $this->registry->find( $asset_id );
		$root = $this->root();
		if ( null === $row || false === $root || ! isset( $row['relative_path'] ) ) {
			return false;
		}

		$relative = (string) $row['relative_path'];
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return false;
		}

		$file = realpath( $root . DIRECTORY_SEPARATOR . str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $relative ) );
		if ( false === $file || ! $this->is_within( $file, $root ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		$size = filesize( $file );
		if ( false === $size || (int) $size !== (int) $row['file_size'] ) {
			return false;
		}

		// A same-size swap still fails closed when a checksum was recorded.
		// Rows without one predate checksum registration and keep size-only
		// verification until they are re-registered.
		$checksum = isset( $row['checksum'] ) ? (string) $row['checksum'] : '';
		if ( '' !== $checksum ) {
			$actual = hash_file( 'sha256', $file );
			if ( ! is_string( $actual ) || ! hash_equals( $checksum, $actual ) ) {
				return false;
			}
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

		$needle   = strtolower( trim( $search ) );
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$matches  = 0;
		$assets   = array();
		$has_more = false;
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

			$relative   = ltrim( substr( $path, strlen( rtrim( $root, DIRECTORY_SEPARATOR ) ) ), DIRECTORY_SEPARATOR );
			$normalized = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
			// Skip hardening stubs and non-allowed types — editors should only
			// see assignable audio/ZIP assets, not defense-in-depth files.
			$filename = $file->getFilename();
			if ( in_array( $filename, array( '.htaccess', 'web.config', 'index.html' ), true ) ) {
				continue;
			}
			$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( '' !== $extension && ! in_array( $extension, $this->allowed_extensions(), true ) ) {
				continue;
			}
			if ( '' !== $needle && false === strpos( strtolower( $normalized ), $needle ) ) {
				continue;
			}

			if ( $matches++ < $offset ) {
				continue;
			}
			if ( count( $assets ) >= $per_page ) {
				$has_more = true;
				break;
			}

			// Prefer opaque registry identifiers; unregistered files are
			// registered on first listing so editors only ever see and assign
			// non-guessable IDs (PROJECT_PLAN.md Stage 2 deliverable 2).
			$opaque     = $this->registry->register( $normalized, $path );
			$identifier = false !== $opaque ? $opaque : 'local:' . $normalized;
			$assets[]   = $this->asset( $identifier, $relative, $path, (int) $file->getSize() );
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
		if ( false === $root || ! wp_is_writable( $root ) ) {
			return new WP_Error( 'mw_protected_asset_root', __( 'پوشهٔ asset حفاظت‌شده در دسترس نیست یا قابل نوشتن نیست.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'mw_protected_asset_upload', __( 'بارگذاری asset حفاظت‌شده معتبر نیست.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$original_name = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$extension     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		$allowed       = $this->allowed_extensions();
		$size          = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( '' === $original_name || ! in_array( $extension, $allowed, true ) || $size < 1 || $size > wp_max_upload_size() ) {
			return new WP_Error( 'mw_protected_asset_type', __( 'نوع یا اندازهٔ asset مجاز نیست.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		// MIME hardening: verify actual file content via finfo, not just extension.
		// Prevents spoofed extensions (e.g. .mp3 containing PHP) and limits ZIP bombs.
		if ( function_exists( 'finfo_open' ) ) {
			$mime = self::detect_mime_type( (string) $file['tmp_name'] );
			if ( null !== $mime ) {
				$allowed_mimes = array(
					'mp3'  => array( 'audio/mpeg', 'audio/mp3' ),
					'm4a'  => array( 'audio/mp4', 'audio/m4a', 'audio/x-m4a' ),
					'aac'  => array( 'audio/aac' ),
					'ogg'  => array( 'audio/ogg', 'audio/x-ogg' ),
					'wav'  => array( 'audio/wav', 'audio/x-wav' ),
					'flac' => array( 'audio/flac', 'audio/x-flac' ),
					'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
				);
				if ( isset( $allowed_mimes[ $extension ] ) && '' !== $mime && ! in_array( $mime, $allowed_mimes[ $extension ], true ) && 0 !== strpos( $mime, 'audio/' ) && 'application/octet-stream' !== $mime ) {
					// Strict for ZIP, permissive for audio (some hosts report generic types).
					if ( 'zip' === $extension ) {
						return new WP_Error( 'mw_protected_asset_mime', __( 'محتوای فایل با پسوند آن مطابقت ندارد.', 'music-wave-vip' ), array( 'status' => 400 ) );
					}
				}
			}
		}
		// Reject files with double extensions or null bytes.
		if ( false !== strpos( $original_name, "\0" ) || preg_match( '/\.(php|phtml|phar|exe|sh)\./i', $original_name ) ) {
			return new WP_Error( 'mw_protected_asset_type', __( 'نام فایل مجاز نیست.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$filename    = wp_unique_filename( $root, $original_name );
		$destination = $root . DIRECTORY_SEPARATOR . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return new WP_Error( 'mw_protected_asset_move', __( 'asset حفاظت‌شده ذخیره نشد.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}
		// Post-move MIME re-check to prevent race / tmp spoof.
		if ( function_exists( 'finfo_open' ) && 'zip' === $extension ) {
			$dest_mime = self::detect_mime_type( $destination );
			if ( null !== $dest_mime && 0 === stripos( $dest_mime, 'text/' ) ) {
				unlink( $destination ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				return new WP_Error( 'mw_protected_asset_mime', __( 'محتوای فایل یک بایگانی ZIP معتبر نیست.', 'music-wave-vip' ), array( 'status' => 400 ) );
			}
		}

		$opaque = $this->registry->register( $filename, $destination );

		return $this->asset( false !== $opaque ? $opaque : 'local:' . $filename, $filename, $destination, $size );
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
			return new WP_Error( 'mw_protected_asset_attachment', __( 'یک فایل معتبر از رسانه‌ها انتخاب کنید.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$source       = get_attached_file( $attachment_id );
		$source       = is_string( $source ) ? realpath( $source ) : false;
		$uploads      = wp_get_upload_dir();
		$uploads_root = isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( false === $source || false === $uploads_root || ! $this->is_within( $source, $uploads_root ) || ! is_file( $source ) || ! is_readable( $source ) ) {
			return new WP_Error( 'mw_protected_asset_attachment_file', __( 'فایل انتخاب‌شده از رسانه‌ها در دسترس نیست.', 'music-wave-vip' ), array( 'status' => 404 ) );
		}

		$root = $this->root();
		if ( false === $root || ! wp_is_writable( $root ) ) {
			return new WP_Error( 'mw_protected_asset_root', __( 'پوشهٔ asset حفاظت‌شده در دسترس نیست یا قابل نوشتن نیست.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		$filename  = sanitize_file_name( basename( $source ) );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$size      = filesize( $source );
		if ( '' === $filename || ! in_array( $extension, $this->allowed_extensions(), true ) || false === $size || $size < 1 || $size > wp_max_upload_size() ) {
			return new WP_Error( 'mw_protected_asset_type', __( 'نوع یا اندازهٔ asset مجاز نیست.', 'music-wave-vip' ), array( 'status' => 400 ) );
		}

		$filename    = wp_unique_filename( $root, $filename );
		$destination = $root . DIRECTORY_SEPARATOR . $filename;
		if ( ! copy( $source, $destination ) ) {
			return new WP_Error( 'mw_protected_asset_copy', __( 'فایل رسانه‌ها در ذخیره‌سازی حفاظت‌شده کپی نشد.', 'music-wave-vip' ), array( 'status' => 500 ) );
		}

		$opaque = $this->registry->register( $filename, $destination );

		return $this->asset( false !== $opaque ? $opaque : 'local:' . $filename, $filename, $destination, (int) $size );
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
			$format   = isset( $audio['fileformat'] ) && is_scalar( $audio['fileformat'] ) ? sanitize_key( strtolower( (string) $audio['fileformat'] ) ) : '';
			$bitrate  = isset( $audio['bitrate'] ) && is_scalar( $audio['bitrate'] ) ? absint( $audio['bitrate'] ) : 0;
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

	/**
	 * Detect the MIME type of a file from its content.
	 *
	 * Uses the `finfo` resource API without `finfo_close()`: the handle is
	 * released when it goes out of scope on every supported PHP version and
	 * the explicit close call is deprecated as of PHP 8.5.
	 *
	 * @param string $path Absolute file path.
	 * @return string|null Lower-case MIME type, or null when detection is unavailable or fails.
	 */
	private static function detect_mime_type( string $path ): ?string {
		if ( ! function_exists( 'finfo_open' ) || '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return null;
		}

		$mime  = finfo_file( $finfo, $path );
		$finfo = null;

		return is_string( $mime ) && '' !== $mime ? strtolower( $mime ) : null;
	}
}
