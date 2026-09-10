<?php
/**
 * Public quality badge for release cards ("FLAC", "Hi-Res", "24-bit").
 *
 * The WAVE card styles stamp a tiny format badge on the artwork corner.
 * The signal is derived from the protected download variants without
 * exposing any asset identifier, and it is the only place that turns
 * variant metadata into a marketing label, so every surface (theme
 * shelves, related rails, continue listening, chart rows) agrees.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class ReleaseBadge {
	/** Filter name themes call to obtain the badge for a release card. */
	public const FILTER = 'music_wave_release_badge';

	/** Lossless bitrate threshold (kbps) that qualifies a stream as Hi-Res. */
	private const HI_RES_BITRATE = 2000;

	/** @var ReleaseRepository */
	private $releases;

	/** @var array<int, array<string, string>> */
	private $cache = array();

	public function __construct( ReleaseRepository $releases ) {
		$this->releases = $releases;
	}

	public function register(): void {
		add_filter( self::FILTER, array( $this, 'filter_badge' ), 10, 2 );
		add_filter( 'music_wave_release_meta_line', array( $this, 'filter_meta_line' ), 10, 2 );
	}

	/**
	 * Supply the theme's card meta line so theme and plugin cards agree.
	 *
	 * @param mixed $line       Existing line or null.
	 * @param mixed $release_id Release post ID.
	 * @return string|mixed
	 */
	public function filter_meta_line( $line, $release_id = 0 ) {
		if ( is_string( $line ) ) {
			return $line;
		}

		return self::meta_line( absint( $release_id ) );
	}

	/**
	 * Provide the badge through the shared filter.
	 *
	 * @param mixed $badge      Existing badge (array with label/tone) or empty.
	 * @param mixed $release_id Release post ID.
	 * @return array<string, string> Empty array when the release has no signal.
	 */
	public function filter_badge( $badge, $release_id = 0 ): array {
		if ( is_array( $badge ) && ! empty( $badge['label'] ) ) {
			return $badge;
		}

		return $this->badge( absint( $release_id ) );
	}

	/**
	 * Resolve the badge for a release.
	 *
	 * Priority: Hi-Res (lossless above the bitrate threshold or an explicit
	 * `hi-res` variant key) → lossless format name (FLAC / WAV) → nothing.
	 * Lossy formats never earn a badge; the reference design reserves the
	 * stamp for audiophile signals.
	 *
	 * @return array<string, string> `label` and `tone` (accent|premium|neutral).
	 */
	public function badge( int $release_id ): array {
		if ( $release_id < 1 ) {
			return array();
		}
		if ( isset( $this->cache[ $release_id ] ) ) {
			return $this->cache[ $release_id ];
		}

		$badge  = array();
		$assets = $this->releases->get( $release_id, 'mw_download_assets' );
		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				$format  = isset( $asset['format'] ) && is_scalar( $asset['format'] ) ? sanitize_key( strtolower( (string) $asset['format'] ) ) : '';
				$key     = isset( $asset['key'] ) && is_scalar( $asset['key'] ) ? sanitize_key( (string) $asset['key'] ) : '';
				$bitrate = isset( $asset['bitrate'] ) && is_scalar( $asset['bitrate'] ) ? absint( $asset['bitrate'] ) : 0;

				$lossless = in_array( $format, array( 'flac', 'wav', 'aiff', 'alac' ), true );
				if ( $lossless && ( $bitrate >= self::HI_RES_BITRATE || false !== strpos( $key, 'hi-res' ) || false !== strpos( $key, 'hires' ) ) ) {
					$badge = array(
						'label' => __( 'Hi-Res', 'music-wave-core' ),
						'tone'  => 'premium',
					);
					break;
				}
				if ( $lossless && array() === $badge ) {
					$badge = array(
						'label' => strtoupper( $format ),
						'tone'  => 'accent',
					);
				}
			}
		}

		/**
		 * Filter the resolved quality badge before it is cached for the request.
		 *
		 * @param array<string, string> $badge      Badge with `label` and `tone`, or empty.
		 * @param int                   $release_id Release post ID.
		 */
		$filtered = apply_filters( 'music_wave_release_badge_resolved', $badge, $release_id );
		$badge    = is_array( $filtered ) && isset( $filtered['label'] ) && is_scalar( $filtered['label'] ) && '' !== (string) $filtered['label']
			? array(
				'label' => sanitize_text_field( (string) $filtered['label'] ),
				'tone'  => isset( $filtered['tone'] ) && in_array( $filtered['tone'], array( 'accent', 'premium', 'neutral' ), true ) ? (string) $filtered['tone'] : 'neutral',
			)
			: array();

		$this->cache[ $release_id ] = $badge;

		return $badge;
	}

	/**
	 * Render the badge markup used by every WAVE card surface.
	 *
	 * @param array<string, string> $badge Badge from {@see badge()}.
	 */
	public static function markup( array $badge ): string {
		if ( empty( $badge['label'] ) ) {
			return '';
		}
		$tone = isset( $badge['tone'] ) && in_array( $badge['tone'], array( 'accent', 'premium' ), true ) ? ' mw-badge--' . $badge['tone'] : '';

		return '<span class="mw-badge' . $tone . ' mw-release-shelf__badge">' . esc_html( (string) $badge['label'] ) . '</span>';
	}

	/**
	 * Resolve and render in one call for renderers holding only a release ID.
	 */
	public static function for_release( int $release_id ): string {
		$badge = apply_filters( self::FILTER, array(), $release_id );

		return is_array( $badge ) ? self::markup( $badge ) : '';
	}

	/**
	 * Compact factual line for WAVE cards: "Album · 2025", falling back to
	 * the primary genre when the release has no type. Shared by every core
	 * card renderer so the meta line reads the same across surfaces.
	 */
	public static function meta_line( int $release_id ): string {
		$parts = array();
		$types = wp_get_post_terms( $release_id, 'mw_release_type', array( 'fields' => 'names' ) );
		if ( is_array( $types ) && ! empty( $types[0] ) ) {
			$parts[] = (string) $types[0];
		} else {
			$genres = wp_get_post_terms( $release_id, 'mw_genre', array( 'fields' => 'names' ) );
			if ( is_array( $genres ) && ! empty( $genres[0] ) ) {
				$parts[] = (string) $genres[0];
			}
		}
		$year = (string) get_the_date( 'Y', $release_id );
		if ( '' !== $year ) {
			$parts[] = $year;
		}

		return implode( " \u{00B7} ", $parts );
	}

	/**
	 * Meta line markup, or an empty string when there is nothing to say.
	 */
	public static function meta_markup( int $release_id ): string {
		$line = self::meta_line( $release_id );

		return '' !== $line ? '<span class="mw-release-shelf__meta">' . esc_html( $line ) . '</span>' : '';
	}
}
