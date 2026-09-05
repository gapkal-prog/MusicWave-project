<?php
/**
 * MusicWave checks surfaced through WordPress Site Health.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Support;

use ManaCore\MusicWave\Core\Migrations\MigrationRunner;

final class SiteHealth {
	/**
	 * Register direct, low-cost Site Health tests.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $tests Existing Site Health tests.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tests( array $tests ): array {
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}

		$tests['direct']['music_wave_schema']            = array(
			'label' => __( 'طرح پایگاه داده MusicWave فعلی است', 'music-wave-core' ),
			'test'  => array( $this, 'test_schema' ),
		);
		$tests['direct']['music_wave_permalinks']        = array(
			'label' => __( 'MusicWave از پیوندهای ثابت زیبا استفاده می‌کند', 'music-wave-core' ),
			'test'  => array( $this, 'test_permalinks' ),
		);
		$tests['direct']['music_wave_download_provider'] = array(
			'label' => __( 'تحویل حفاظت‌شدهٔ MusicWave پیکربندی‌شده است', 'music-wave-core' ),
			'test'  => array( $this, 'test_download_provider' ),
		);
		$tests['direct']['music_wave_structured_data']   = array(
			'label' => __( 'داده‌های ساختاریافتهٔ MusicWave از طرح‌وارهٔ سئوی تکراری جلوگیری می‌کند', 'music-wave-core' ),
			'test'  => array( $this, 'test_structured_data' ),
		);
		$tests['direct']['music_wave_provider_health']   = array(
			'label' => __( 'ارائه دهندگان ادغام MusicWave سالم هستند', 'music-wave-core' ),
			'test'  => array( $this, 'test_provider_health' ),
		);

		return $tests;
	}

	/**
	 * Aggregate structured provider health reports.
	 *
	 * Providers push reports through `music_wave_provider_health` using the
	 * shared error taxonomy: `ok`, `misconfigured`, `unreachable`,
	 * `rate_limited`, or `failed`, plus a safe human summary that must not
	 * contain secrets (PROJECT_PLAN.md Stage 3 deliverable 4).
	 *
	 * @return array<string, string|array<string, string>>
	 */
	public function test_provider_health(): array {
		/**
		 * Filter structured provider health reports.
		 *
		 * @param array<string, array<string, string>> $reports provider-slug => [status, summary].
		 */
		$reports  = apply_filters( 'music_wave_provider_health', array() );
		$reports  = is_array( $reports ) ? $reports : array();
		$statuses = array( 'ok', 'misconfigured', 'unreachable', 'rate_limited', 'failed' );
		$broken   = array();
		foreach ( $reports as $slug => $report ) {
			$status = isset( $report['status'] ) && in_array( (string) $report['status'], $statuses, true ) ? (string) $report['status'] : 'failed';
			if ( 'ok' !== $status ) {
				$broken[] = sanitize_key( (string) $slug ) . ' (' . $status . ')';
			}
		}

		$healthy = array() === $broken;

		return $this->result(
			$healthy ? __( 'ارائه دهندگان ادغام MusicWave سالم هستند', 'music-wave-core' ) : __( 'یک ارائه‌دهنده یکپارچه سازی MusicWave یک مشکل را گزارش می‌کند', 'music-wave-core' ),
			$healthy ? 'good' : 'critical',
			$healthy
				? __( 'هیچ عضویت، فراداده، یا ارائه‌دهنده تحویلی مشکلی را گزارش نکرد.', 'music-wave-core' )
				/* translators: %s: comma-separated list of failing providers. */
				: sprintf( __( 'ارائه دهندگان ناموفق: %s. قطعی ارائه‌دهنده و اعتبارنامه‌های بد در اینجا گزارش می‌شوند به‌جای اینکه در سکوت به‌عنوان «بدون نتیجه» تلقی شوند.', 'music-wave-core' ), implode( ', ', $broken ) ),
			'music_wave_provider_health'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_schema(): array {
		$version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$ready   = version_compare( $version, MigrationRunner::LATEST_VERSION, '>=' );

		return $this->result(
			$ready ? __( 'طرح پایگاه داده MusicWave فعلی است', 'music-wave-core' ) : __( 'طرح پایگاه داده MusicWave نیاز به به‌روز رسانی دارد', 'music-wave-core' ),
			$ready ? 'good' : 'critical',
			$ready ? __( 'فراداده محتوای MusicWave برای نسخهٔ نصب‌شدهٔ افزونه آماده است.', 'music-wave-core' ) : __( 'برای اجرای انتقال طرح‌وارهٔ معلق، به‌عنوان مدیر از صفحهٔ راه‌اندازی MusicWave دیدن کنید.', 'music-wave-core' ),
			'music_wave_schema'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_permalinks(): array {
		$ready = '' !== (string) get_option( 'permalink_structure', '' );

		return $this->result(
			$ready ? __( 'پیوندهای دائمی‌زیبا MusicWave فعال هستند', 'music-wave-core' ) : __( 'MusicWave به پیوندهای ثابت زیبا نیاز دارد', 'music-wave-core' ),
			$ready ? 'good' : 'recommended',
			$ready ? __( 'بایگانی‌های انتشار، هنرمند و طبقه‌بندی می‌توانند از URL‌های متعارف قابل خواندن استفاده کنند.', 'music-wave-core' ) : __( 'برای پشتیبانی از کاتالوگ قابل پیش‌بینی و هنرمندان URL، هر ساختار پیوند ثابت غیر پیش‌فرض را ذخیره کنید.', 'music-wave-core' ),
			'music_wave_permalinks'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_download_provider(): array {
		$ready = has_filter( 'music_wave_download_provider' );

		return $this->result(
			$ready ? __( 'ارائه‌دهنده تحویل حفاظت‌شده MusicWave در دسترس است', 'music-wave-core' ) : __( 'ارائه‌دهنده تحویل حفاظت‌شده MusicWave اختیاری است اما در دسترس نیست', 'music-wave-core' ),
			$ready ? 'good' : 'recommended',
			$ready ? __( 'ارائه‌دهنده می‌تواند دارایی‌های حفاظت‌شده غیرشفاف را پس از تصویب بررسی‌های دسترسی، پخش کند.', 'music-wave-core' ) : __( 'پیش از اختصاص دارایی‌های دانلود حفاظت‌شده به انتشارها، MusicWave VIP را فعال کنید.', 'music-wave-core' ),
			'music_wave_download_provider'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	public function test_structured_data(): array {
		$has_competitor = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );

		return $this->result(
			$has_competitor ? __( 'داده‌های ساختاریافتهٔ MusicWave از موارد تکراری محافظت می‌کند', 'music-wave-core' ) : __( 'داده‌های ساختاریافتهٔ عمومی MusicWave موجود است', 'music-wave-core' ),
			'good',
			$has_competitor ? __( 'یک افزونهٔ SEO شناخته‌شده فعال است، بنابراین MusicWave JSON-LD به‌طور پیش‌فرض غیرفعال می‌ماند.', 'music-wave-core' ) : __( 'MusicWave می‌تواند ضبط موسیقی، آلبوم موسیقی و طرح‌وارهٔ پادکست را از فیلدهای کاتالوگ عمومی منتشر کند.', 'music-wave-core' ),
			'music_wave_structured_data'
		);
	}

	/**
	 * @return array<string, string|array<string, string>>
	 */
	private function result( string $label, string $status, string $description, string $test ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'MusicWave', 'music-wave-core' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ) . '">' . esc_html__( 'راه‌اندازی MusicWave را باز کنید', 'music-wave-core' ) . '</a></p>',
			'test'        => $test,
		);
	}
}
