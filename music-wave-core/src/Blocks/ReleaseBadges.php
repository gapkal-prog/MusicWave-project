<?php
/**
 * Real-data quality badges for release cards, rows and heroes.
 *
 * Every badge is derived from canonical release meta — preview URL,
 * protected download assets, explicit flag and access mode — so the
 * storefront never shows a decorative badge without underlying data.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Contracts\ReleaseRepository;

final class ReleaseBadges {
	/**
	 * Collect badge descriptors for one release.
	 *
	 * @param int               $release_id Release post ID.
	 * @param ReleaseRepository $repository Canonical meta repository.
	 * @param array<string, mixed> $options Display options (max_qualities).
	 * @return array<int, array{key: string, label: string, modifier: string}>
	 */
	public static function for_release( int $release_id, ReleaseRepository $repository, array $options = array() ): array {
		if ( $release_id < 1 ) {
			return array();
		}

		$badges        = array();
		$max_qualities = isset( $options['max_qualities'] ) ? max( 1, min( 3, absint( $options['max_qualities'] ) ) ) : 2;

		// Preview availability — real HTTPS preview URL only.
		$preview_url = $repository->get( $release_id, 'mw_preview_url' );
		if ( is_string( $preview_url ) && 'https' === wp_parse_url( $preview_url, PHP_URL_SCHEME ) ) {
			$badges[] = array(
				'key'      => 'preview',
				'label'    => __( 'پیش‌نمایش', 'music-wave-core' ),
				'modifier' => 'preview',
			);
		}

		// Protected quality variants — real labels from mw_download_assets.
		$assets = $repository->get( $release_id, 'mw_download_assets' );
		if ( is_array( $assets ) ) {
			$seen   = array();
			$count  = 0;
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['label'] ) ) {
					continue;
				}
				$label = sanitize_text_field( (string) $asset['label'] );
				if ( '' === $label || isset( $seen[ $label ] ) ) {
					continue;
				}
				$seen[ $label ] = true;
				$badges[]       = array(
					'key'      => 'quality-' . sanitize_key( (string) ( $asset['key'] ?? ( 'q' . $count ) ) ),
					'label'    => $label,
					'modifier' => self::quality_modifier( $label ),
				);
				++$count;
				if ( $count >= $max_qualities ) {
					break;
				}
			}
		}

		// Explicit content — real mw_explicit flag.
		$explicit = $repository->get( $release_id, 'mw_explicit' );
		if ( '1' === (string) $explicit || 'yes' === strtolower( (string) $explicit ) || true === $explicit ) {
			$badges[] = array(
				'key'      => 'explicit',
				'label'    => 'E',
				'modifier' => 'explicit',
			);
		}

		// Access mode — purchase / membership tiers are real store signals.
		$access_mode = $repository->get( $release_id, 'mw_access_mode' );
		if ( is_string( $access_mode ) && '' !== $access_mode && 'public' !== $access_mode ) {
			$labels = array(
				'purchase'               => __( 'فروش', 'music-wave-core' ),
				'membership'             => __( 'اعضا', 'music-wave-core' ),
				'purchase_or_membership' => __( 'فروش / اعضا', 'music-wave-core' ),
				'restricted'             => __( 'محدود', 'music-wave-core' ),
			);
			if ( isset( $labels[ $access_mode ] ) ) {
				$badges[] = array(
					'key'      => 'access-' . $access_mode,
					'label'    => $labels[ $access_mode ],
					'modifier' => 'access',
				);
			}
		}

		return $badges;
	}

	/**
	 * Render badge descriptors as accessible markup.
	 *
	 * @param array<int, array{key: string, label: string, modifier: string}> $badges Badge descriptors.
	 * @return string
	 */
	public static function markup( array $badges ): string {
		if ( empty( $badges ) ) {
			return '';
		}

		$items = array();
		foreach ( $badges as $badge ) {
			if ( ! is_array( $badge ) || empty( $badge['label'] ) ) {
				continue;
			}
			$modifier = isset( $badge['modifier'] ) ? sanitize_html_class( (string) $badge['modifier'] ) : 'neutral';
			$label    = (string) $badge['label'];
			$aria     = 'E' === $label
				? ' aria-label="' . esc_attr__( 'محتوای صریح', 'music-wave-core' ) . '" title="' . esc_attr__( 'محتوای صریح', 'music-wave-core' ) . '"'
				: ' title="' . esc_attr( $label ) . '"';
			$items[]  = '<span class="mw-badge mw-badge--' . esc_attr( $modifier ) . '"' . $aria . '>' . esc_html( $label ) . '</span>';
		}

		return '' === implode( '', $items ) ? '' : '<span class="mw-badges" aria-hidden="false">' . implode( '', $items ) . '</span>';
	}

	/**
	 * Map a real quality label to a visual modifier.
	 *
	 * Lossless / Hi-Res labels get the gold treatment; MP3/OGG stay neutral.
	 *
	 * @param string $label Real asset label, e.g. "FLAC 24-bit" or "MP3 320 kbps".
	 */
	private static function quality_modifier( string $label ): string {
		$haystack = strtolower( $label );
		foreach ( array( 'flac', 'wav', 'alac', 'lossless', 'hi-res', 'hires', '24-bit', '24bit', '96khz', '192khz', 'mqa', 'atmos', 'spatial' ) as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return 'hires';
			}
		}
		foreach ( array( '320', '256', 'vip', 'master', 'studio' ) as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return 'quality';
			}
		}

		return 'neutral';
	}
}
