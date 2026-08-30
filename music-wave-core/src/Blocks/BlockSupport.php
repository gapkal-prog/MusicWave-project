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

	/**
	 * Read a sanitized plain-text attribute with a translated fallback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param string               $fallback   Translated default text.
	 */
	public static function text_attribute( array $attributes, string $key, string $fallback ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? sanitize_text_field( (string) $attributes[ $key ] ) : '';

		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Read an allow-listed key attribute, falling back when the value is unknown.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param array<int, string>   $allowed    Allowed key values.
	 * @param string               $fallback   Value used for unknown keys.
	 */
	public static function key_attribute( array $attributes, string $key, array $allowed, string $fallback ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? sanitize_key( (string) $attributes[ $key ] ) : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Read a bounded integer attribute; out-of-range values use the fallback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param int                  $min        Minimum allowed value.
	 * @param int                  $max        Maximum allowed value.
	 * @param int                  $fallback   Value used when out of range.
	 */
	public static function range_attribute( array $attributes, string $key, int $min, int $max, int $fallback ): int {
		$value = isset( $attributes[ $key ] ) ? absint( $attributes[ $key ] ) : 0;

		return $value >= $min && $value <= $max ? $value : $fallback;
	}

	/**
	 * Read a boolean block attribute with an explicit default.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key        Attribute name.
	 * @param bool                 $fallback   Value used when the attribute is absent.
	 */
	public static function bool_attribute( array $attributes, string $key, bool $fallback ): bool {
		if ( ! isset( $attributes[ $key ] ) ) {
			return $fallback;
		}

		return (bool) $attributes[ $key ];
	}

	/**
	 * Best-effort current URL for sign-in redirects and self-referencing forms.
	 *
	 * Prefers the singular permalink and falls back to the raw request URI so
	 * archive and search views still return to where the visitor was.
	 */
	public static function current_url(): string {
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$permalink = get_permalink();
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $request ) {
			return home_url( '/' );
		}

		// On subdirectory installs REQUEST_URI already contains the folder the
		// site lives in (e.g. /store/…), while home_url() prepends it again.
		// Strip the prefix so the two are never combined into a doubled path.
		$subdirectory = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$prefix       = '' !== $subdirectory ? rtrim( $subdirectory, '/' ) : '';
		if ( '' !== $prefix && 0 === strpos( $request, $prefix ) ) {
			$remainder = substr( $request, strlen( $prefix ) );
			if ( '' === $remainder || 0 === strpos( $remainder, '/' ) || 0 === strpos( $remainder, '?' ) ) {
				$request = '' === $remainder ? '/' : $remainder;
			}
		}

		return home_url( $request );
	}
}
