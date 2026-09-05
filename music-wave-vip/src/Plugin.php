<?php
declare(strict_types=1);
namespace ManaCore\MusicWave\Vip;

final class Plugin {
	public static function core_notice(): void {
		if ( defined( 'MUSIC_WAVE_CORE_VERSION' ) ) {
			return; }
		echo '<div class="notice notice-error"><p>' . esc_html__( 'MusicWave VIP به افزونهٔ MusicWave Core نیاز دارد.', 'music-wave-vip' ) . '</p></div>';
	}
}
