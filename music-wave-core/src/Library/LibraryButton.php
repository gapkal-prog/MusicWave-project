<?php
/**
 * Shared add-to-library button markup and frontend behavior.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Library;

use ManaCore\MusicWave\Core\Blocks\BlockSupport;

final class LibraryButton {
	public const SCRIPT = 'music-wave-library';

	/**
	 * Inline heart glyph for the icon-only variant; the saved state is
	 * communicated through CSS fill and aria-pressed, never color alone.
	 */
	private const HEART_ICON = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';

	/** @var LibraryRepository|null */
	private static $repository = null;

	/**
	 * Bind the shared library repository used for state lookups.
	 *
	 * @return void
	 */
	public static function bind( LibraryRepository $repository ): void {
		self::$repository = $repository;
	}

	/**
	 * Return the current repository binding, when available.
	 */
	public static function repository(): ?LibraryRepository {
		return self::$repository;
	}

	/**
	 * Render one library toggle button for a release or artist.
	 *
	 * Signed-out visitors receive a sign-in link styled as the same button so
	 * the call to action remains visible without exposing a broken toggle.
	 *
	 * @param string               $type     Library item type (release|artist).
	 * @param int                  $item_id  Release post ID or artist term ID.
	 * @param array<string, mixed> $settings Optional label, style, and size overrides.
	 * @return string
	 */
	public static function markup( string $type, int $item_id, array $settings = array() ): string {
		$supported = array(
			LibraryRepository::TYPE_RELEASE,
			LibraryRepository::TYPE_ARTIST,
			LibraryRepository::TYPE_WISHLIST,
			LibraryRepository::TYPE_PRESAVE,
		);
		if ( $item_id < 1 || ! in_array( $type, $supported, true ) ) {
			return '';
		}
		// A pre-save only exists while the release is still upcoming; once it is
		// out the button would be a dead end, so it is not rendered.
		if ( LibraryRepository::TYPE_PRESAVE === $type && null !== self::$repository && ! self::$repository->is_upcoming( $item_id ) ) {
			return '';
		}

		self::enqueue_assets();

		$style     = isset( $settings['style'] ) ? sanitize_key( (string) $settings['style'] ) : 'solid';
		$style     = in_array( $style, array( 'solid', 'outline', 'ghost', 'heart' ), true ) ? $style : 'solid';
		$compact   = ! empty( $settings['compact'] );
		$is_heart  = 'heart' === $style;
		$show_icon = $is_heart || ! isset( $settings['showIcon'] ) || false !== $settings['showIcon'];

		$in_library = null !== self::$repository && self::$repository->has( get_current_user_id(), $type, $item_id );
		$label      = self::label( $type, $in_library, $settings );
		$class      = 'mw-library-button mw-library-button--' . $style . ( $compact ? ' mw-library-button--compact' : '' );
		$icon       = '';
		if ( $show_icon ) {
			$icon = $is_heart
				? '<span class="mw-library-button__icon" aria-hidden="true">' . self::HEART_ICON . '</span>'
				: '<span class="mw-library-button__icon" aria-hidden="true">' . ( $in_library ? '&#10003;' : '+' ) . '</span>';
		}

		if ( get_current_user_id() < 1 ) {
			return '<a class="' . esc_attr( $class ) . ' mw-library-button--guest" href="' . esc_url( wp_login_url( BlockSupport::current_url() ) ) . '">' . $icon . '<span class="mw-library-button__label">' . esc_html( $label ) . '</span></a>';
		}

		return '<button type="button" class="' . esc_attr( $class ) . '" data-mw-library-type="' . esc_attr( $type ) . '" data-mw-library-id="' . esc_attr( (string) $item_id ) . '" data-mw-library-state="' . ( $in_library ? 'in' : 'out' ) . '" data-mw-library-label-add="' . esc_attr( self::label( $type, false, $settings ) ) . '" data-mw-library-label-added="' . esc_attr( self::label( $type, true, $settings ) ) . '" aria-pressed="' . ( $in_library ? 'true' : 'false' ) . '">' . $icon . '<span class="mw-library-button__label">' . esc_html( $label ) . '</span><span class="mw-library-button__status" role="status" aria-live="polite"></span></button>';
	}

	/**
	 * Load the library controller script once on public pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT,
			MUSIC_WAVE_CORE_URL . 'assets/library.js',
			array( 'wp-api-fetch' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT,
			'musicWaveLibrary',
			array(
				'restUrl'      => esc_url_raw( rest_url() ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'errorMessage' => __( 'Your library could not be updated. Try again.', 'music-wave-core' ),
				'sessionError' => __( 'Your session has expired. Refresh the page or sign in again.', 'music-wave-core' ),
			)
		);
	}

	/**
	 * Resolve the visible button label for a state.
	 *
	 * @param string               $type      Library item type.
	 * @param bool                 $in_library Whether the item is saved.
	 * @param array<string, mixed> $settings  Label overrides.
	 */
	private static function label( string $type, bool $in_library, array $settings ): string {
		$override_key = $in_library ? 'addedLabel' : 'label';
		if ( isset( $settings[ $override_key ] ) && is_scalar( $settings[ $override_key ] ) ) {
			$override = sanitize_text_field( (string) $settings[ $override_key ] );
			if ( '' !== $override ) {
				return $override;
			}
		}

		if ( LibraryRepository::TYPE_ARTIST === $type ) {
			return $in_library ? __( 'Following', 'music-wave-core' ) : __( 'Follow artist', 'music-wave-core' );
		}
		if ( LibraryRepository::TYPE_WISHLIST === $type ) {
			return $in_library ? __( 'On your wishlist', 'music-wave-core' ) : __( 'Add to wishlist', 'music-wave-core' );
		}
		if ( LibraryRepository::TYPE_PRESAVE === $type ) {
			return $in_library ? __( 'Pre-saved', 'music-wave-core' ) : __( 'Pre-save', 'music-wave-core' );
		}

		return $in_library ? __( 'In your library', 'music-wave-core' ) : __( 'Add to library', 'music-wave-core' );
	}
}
