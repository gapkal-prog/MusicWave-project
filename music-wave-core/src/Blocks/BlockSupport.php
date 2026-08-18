<?php
/**
 * Shared registration and wrapper helpers for dynamic MusicWave blocks.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

final class BlockSupport {
	/**
	 * Return the inspector controls supported by presentational dynamic blocks.
	 *
	 * @return array<string, mixed>
	 */
	public static function appearance_tools(): array {
		return array(
			'anchor'     => true,
			'align'      => array( 'wide', 'full' ),
			'border'     => array(
				'color'  => true,
				'radius' => true,
				'style'  => true,
				'width'  => true,
			),
			'color'      => array(
				'background' => true,
				'link'       => true,
				'text'       => true,
			),
			'spacing'    => array(
				'blockGap' => true,
				'margin'   => true,
				'padding'  => true,
			),
			'typography' => array(
				'fontSize'   => true,
				'lineHeight' => true,
			),
		);
	}

	/**
	 * Build an attribute string that includes WordPress block support styles.
	 *
	 * @param string $class_names Theme component classes for the root element.
	 * @return string
	 */
	public static function wrapper_attributes( string $class_names ): string {
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( array( 'class' => $class_names ) );
		}

		return 'class="' . esc_attr( $class_names ) . '"';
	}

	/**
	 * Read the active editor style variation from the block className.
	 *
	 * Returns the first allow-listed variation present as an is-style-* class,
	 * or an empty string when the default style is active.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<int, string>   $allowed Allowed variation names.
	 */
	public static function style_variation( array $attributes, array $allowed ): string {
		$class_name = isset( $attributes['className'] ) && is_scalar( $attributes['className'] ) ? (string) $attributes['className'] : '';
		if ( '' === $class_name ) {
			return '';
		}

		foreach ( $allowed as $style_name ) {
			if ( false !== strpos( $class_name, 'is-style-' . $style_name ) ) {
				return $style_name;
			}
		}

		return '';
	}

	/**
	 * Register one dynamic block from its block.json folder when present.
	 *
	 * The bundled block.json file is the marketplace-grade source of truth for
	 * metadata, attributes, and supports; the PHP attribute map is kept as a
	 * compatibility fallback for installs missing the blocks/ directory.
	 *
	 * @param string               $block_name Full block name, e.g. music-wave/release-meta.
	 * @param callable             $render_callback Server-side renderer.
	 * @param array<string, mixed> $fallback_args Arguments used when block.json is unavailable.
	 * @return void
	 */
	public static function register_dynamic( string $block_name, callable $render_callback, array $fallback_args = array() ): void {
		$metadata = BlockMetadata::load( $block_name );
		if ( null !== $metadata ) {
			// Only the render callback is merged over block.json so the metadata
			// file stays the single source of truth for attributes and supports.
			register_block_type(
				BlockMetadata::directory( $block_name ),
				array( 'render_callback' => $render_callback )
			);

			return;
		}

		$fallback_args['render_callback'] = $render_callback;
		register_block_type( $block_name, $fallback_args );
	}
}
