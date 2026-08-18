<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Vip;
final class Plugin {
	public static function core_notice(): void {
		if ( defined( 'MUSIC_WAVE_CORE_VERSION' ) ) { return; }
		echo '<div class="notice notice-error"><p>' . esc_html__( 'MusicWave VIP requires the MusicWave Core plugin.', 'music-wave-vip' ) . '</p></div>';
	}
}
