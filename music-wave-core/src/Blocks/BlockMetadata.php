<?php
/**
 * Block metadata bridge for the bundled block.json definitions.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

final class BlockMetadata {
	public const PREFIX = 'music-wave/';

	/** @var array<string, array<string, mixed>> */
	private static $cache = array();

	/**
	 * Return the directory holding the block.json for a MusicWave block.
	 *
	 * @param string $block_name Full block name, e.g. music-wave/release-meta.
	 */
	public static function directory( string $block_name ): string {
		$relative = 0 === strpos( $block_name, self::PREFIX ) ? substr( $block_name, strlen( self::PREFIX ) ) : $block_name;

		return dirname( __DIR__, 2 ) . '/blocks/' . $relative;
	}

	/**
	 * Load and cache the decoded block.json metadata for one block.
	 *
	 * @param string $block_name Full block name, e.g. music-wave/release-meta.
	 * @return array<string, mixed>|null Null when the metadata file is missing or invalid.
	 */
	public static function load( string $block_name ): ?array {
		if ( isset( self::$cache[ $block_name ] ) ) {
			return self::$cache[ $block_name ];
		}

		$file = self::directory( $block_name ) . '/block.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		// Local bundled metadata file, never a remote URL.
		$metadata = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $metadata ) || empty( $metadata['name'] ) ) {
			return null;
		}

		self::$cache[ $block_name ] = $metadata;

		return $metadata;
	}

	/**
	 * List the full names of every bundled MusicWave block.
	 *
	 * @return array<int, string>
	 */
	public static function block_names(): array {
		$directories = glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' );

		return array_values(
			array_map(
				static function ( string $file ): string {
					return self::PREFIX . basename( dirname( $file ) );
				},
				is_array( $directories ) ? $directories : array()
			)
		);
	}

	/**
	 * Convert block.json metadata into register_block_type() arguments.
	 *
	 * @param array<string, mixed> $metadata Decoded block.json contents.
	 * @return array<string, mixed>
	 */
	public static function registration_args( array $metadata ): array {
		$args = array();
		if ( isset( $metadata['apiVersion'] ) ) {
			$args['api_version'] = (int) $metadata['apiVersion'];
		}
		if ( isset( $metadata['attributes'] ) && is_array( $metadata['attributes'] ) ) {
			$args['attributes'] = $metadata['attributes'];
		}
		if ( isset( $metadata['supports'] ) && is_array( $metadata['supports'] ) ) {
			$args['supports'] = $metadata['supports'];
		}
		if ( isset( $metadata['usesContext'] ) && is_array( $metadata['usesContext'] ) ) {
			$args['uses_context'] = array_values( $metadata['usesContext'] );
		}

		return $args;
	}
}
