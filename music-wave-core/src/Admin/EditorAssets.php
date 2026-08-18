<?php
/**
 * MusicWave release meta-box assets.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;

final class EditorAssets {
	public function enqueue(): void {
		$screen = get_current_screen();
		if ( null === $screen || ReleasePostType::KEY !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'music-wave-editor',
			MUSIC_WAVE_CORE_URL . 'assets/editor.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ),
			MUSIC_WAVE_CORE_VERSION,
			true
		);
		wp_enqueue_style(
			'music-wave-editor',
			MUSIC_WAVE_CORE_URL . 'assets/editor.css',
			array( 'wp-components' ),
			MUSIC_WAVE_CORE_VERSION
		);
	}
}
