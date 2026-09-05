<?php
/**
 * Guided setup and diagnostics administration page.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Migrations\MigrationRunner;
use ManaCore\MusicWave\Core\Catalog\DemoContentImporter;

final class DiagnosticsPage {
	private const DEMO_NOTICE_KEY = 'music_wave_demo_notice_';

	private const PAGE = 'music-wave-setup';

	/** @var DemoContentImporter */
	private $demo_importer;

	public function __construct( DemoContentImporter $demo_importer ) {
		$this->demo_importer = $demo_importer;
	}

	/**
	 * Register the MusicWave setup submenu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_music_wave_import_demo', array( $this, 'import_demo' ) );
		add_action( 'admin_post_music_wave_remove_demo', array( $this, 'remove_demo' ) );
	}

	/**
	 * Add the page beneath the MusicWave release menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=mw_release',
			__( 'راه‌اندازی MusicWave', 'music-wave-core' ),
			__( 'راه‌اندازی و عیب‌یابی', 'music-wave-core' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Load the shared MusicWave admin design system for this screen only.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'mw_release_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'music-wave-admin-settings',
			MUSIC_WAVE_CORE_URL . 'assets/admin-settings.css',
			array(),
			MUSIC_WAVE_CORE_VERSION
		);
	}

	/**
	 * Render a merchant-focused readiness dashboard.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->register_help_tabs();

		$checks  = $this->checks();
		$summary = $this->summarize( $checks );

		echo '<div class="wrap mw-settings"><h1>' . esc_html__( 'راه‌اندازی و عیب‌یابی MusicWave', 'music-wave-core' ) . '</h1>';
		echo '<p class="mw-settings__lead">' . esc_html__( 'قبل از وارد کردن اطلاعات کاتالوگ یا باز کردن فروشگاه برای مشتریان، اطمینان حاصل کنید که محیط آماده است.', 'music-wave-core' ) . '</p>';

		$this->render_summary_banner( $summary );
		$this->render_quick_start();
		$this->render_environment_checks( $checks );
		$this->render_system_info();
		$this->render_demo_section();

		echo '<div class="mw-settings__actions">';
		echo '<a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'باز کردن سلامت سایت WordPress', 'music-wave-core' ) . '</a>';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings' ) ) . '">' . esc_html__( 'مرکز کنترل MusicWave را باز کنید', 'music-wave-core' ) . '</a>';
		echo '</div></div>';
	}

	/**
	 * Import only explicit demo content after a privileged nonce-protected request.
	 *
	 * @return void
	 */
	public function import_demo(): void {
		$this->handle_demo_action( 'music_wave_import_demo', __( 'کاتالوگ نسخهٔ نمایشی درون‌ریزی شد.', 'music-wave-core' ) );
	}

	/**
	 * Remove only importer-marked demo content after explicit confirmation.
	 *
	 * @return void
	 */
	public function remove_demo(): void {
		$this->handle_demo_action( 'music_wave_remove_demo', __( 'کاتالوگ نسخهٔ نمایشی حذف شد.', 'music-wave-core' ) );
	}

	/**
	 * @param array<int, array<string, bool|string>> $checks Environment checks.
	 * @return array<string, int|bool>
	 */
	private function summarize( array $checks ): array {
		$passed             = 0;
		$required_failed    = 0;
		$recommended_failed = 0;

		foreach ( $checks as $check ) {
			if ( $check['passed'] ) {
				++$passed;
				continue;
			}
			if ( 'required' === $check['severity'] ) {
				++$required_failed;
			} else {
				++$recommended_failed;
			}
		}

		return array(
			'total'              => count( $checks ),
			'passed'             => $passed,
			'required_failed'    => $required_failed,
			'recommended_failed' => $recommended_failed,
			'ready'              => 0 === $required_failed,
		);
	}

	/** @param array<string, int|bool> $summary */
	private function render_summary_banner( array $summary ): void {
		$ready              = (bool) $summary['ready'];
		$total              = (int) $summary['total'];
		$passed             = (int) $summary['passed'];
		$required_failed    = (int) $summary['required_failed'];
		$recommended_failed = (int) $summary['recommended_failed'];

		echo '<div class="mw-setup-banner' . ( $ready ? '' : ' mw-setup-banner--attention' ) . '">';
		echo '<span class="dashicons ' . esc_attr( $ready ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '" aria-hidden="true"></span><div>';
		if ( $ready ) {
			echo '<strong>' . esc_html__( 'الزامات هسته MusicWave برآورده شده است', 'music-wave-core' ) . '</strong>';
			/* translators: 1: number of passed checks, 2: total number of checks. */
			echo '<small>' . esc_html( sprintf( __( 'بررسی‌های محیطی %1$d از %2$d انجام شد.', 'music-wave-core' ), $passed, $total ) );
			if ( $recommended_failed > 0 ) {
				/* translators: %d: number of advisory checks still open. */
				echo ' ' . esc_html( sprintf( __( 'توصیه اختیاری %d هنوز نیاز به بررسی دارد.', 'music-wave-core' ), $recommended_failed ) );
			}
			echo '</small>';
		} else {
			/* translators: %d: number of required checks still failing. */
			echo '<strong>' . esc_html( sprintf( __( 'بررسی‌های لازم: %d مورد به توجه نیاز دارند.', 'music-wave-core' ), $required_failed ) ) . '</strong>';
			/* translators: 1: number of passed checks, 2: total number of checks. */
			echo '<small>' . esc_html( sprintf( __( 'بررسی‌های محیطی %1$d از %2$d انجام شد. قبل از راه‌اندازی فروشگاه، مواردی را که در زیر «لازم است» مشخص شده‌اند حل کنید.', 'music-wave-core' ), $passed, $total ) ) . '</small>';
		}
		echo '</div><div class="mw-setup-banner__actions">';
		if ( $ready ) {
			echo '<a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=mw_release' ) ) . '">' . esc_html__( 'ایجاد اولین انتشار', 'music-wave-core' ) . '</a>';
		} else {
			echo '<a class="button button-primary" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'بررسی در سلامت سایت', 'music-wave-core' ) . '</a>';
		}
		echo '</div></div>';
	}

	private function render_quick_start(): void {
		$steps = array(
			array(
				__( 'ایجاد اولین انتشار', 'music-wave-core' ),
				__( 'قطعه، آلبوم یا پادکست را با تصویر جلد، فایل پیش‌نمایش و قیمت اضافه کنید.', 'music-wave-core' ),
				admin_url( 'post-new.php?post_type=mw_release' ),
				'dashicons-format-audio',
			),
			array(
				__( 'بررسی انواع انتشار و هنرمندان', 'music-wave-core' ),
				__( 'طبقه‌بندی‌ها و نمایه‌های هنرمند مورد استفاده در کاتالوگ را تأیید کنید.', 'music-wave-core' ),
				admin_url( 'edit-tags.php?taxonomy=mw_release_type&post_type=mw_release' ),
				'dashicons-category',
			),
			array(
				__( 'ذخیره تنظیمات پیوندهای یکتا', 'music-wave-core' ),
				__( 'برای URLهای کاتالوگ، هنرمند و بایگانی قابل خواندن از یک ساختار غیر پیش‌فرض استفاده کنید.', 'music-wave-core' ),
				admin_url( 'options-permalink.php' ),
				'dashicons-admin-links',
			),
			array(
				__( 'پیکربندی تحویل حفاظت‌شده', 'music-wave-core' ),
				__( 'MusicWave VIP را فعال کنید و قبل از فعال کردن دانلودهای خصوصی، یک پوشهٔ حفاظت‌شده تنظیم کنید.', 'music-wave-core' ),
				admin_url( 'edit.php?post_type=mw_release&page=music-wave-vip' ),
				'dashicons-lock',
			),
		);

		echo '<div class="mw-settings__panel"><h2>' . esc_html__( 'شروع سریع', 'music-wave-core' ) . '</h2>';
		echo '<div class="mw-settings__grid mw-settings__grid--steps">';
		foreach ( $steps as $step ) {
			echo '<a class="mw-management-link" href="' . esc_url( (string) $step[2] ) . '"><span class="dashicons ' . esc_attr( (string) $step[3] ) . '" aria-hidden="true"></span><span><strong>' . esc_html( (string) $step[0] ) . '</strong><small>' . esc_html( (string) $step[1] ) . '</small></span></a>';
		}
		echo '</div></div>';
	}

	/** @param array<int, array<string, bool|string>> $checks */
	private function render_environment_checks( array $checks ): void {
		$groups = array(
			'required'    => __( 'بررسی‌های لازم', 'music-wave-core' ),
			'recommended' => __( 'بررسی‌های پیشنهادی', 'music-wave-core' ),
		);

		foreach ( $groups as $severity => $heading ) {
			echo '<div class="mw-settings__panel"><h2>' . esc_html( (string) $heading ) . '</h2>';
			foreach ( $checks as $check ) {
				if ( $check['severity'] !== $severity ) {
					continue;
				}
				$this->render_check_row( $check );
			}
			echo '</div>';
		}
	}

	/** @param array<string, bool|string> $check */
	private function render_check_row( array $check ): void {
		$passed   = (bool) $check['passed'];
		$required = 'required' === $check['severity'];

		if ( $passed ) {
			$state = 'active';
			$label = __( 'آماده', 'music-wave-core' );
		} elseif ( $required ) {
			$state = 'attention';
			$label = __( 'نیاز به توجه دارد', 'music-wave-core' );
		} else {
			$state = 'warn';
			$label = __( 'بررسی', 'music-wave-core' );
		}

		echo '<div class="mw-status-row"><span class="mw-status mw-status--' . esc_attr( $state ) . '">' . esc_html( $label ) . '</span>';
		echo '<div><strong>' . esc_html( (string) $check['label'] ) . '</strong><small>' . esc_html( (string) $check['detail'] ) . '</small></div>';
		if ( ! $passed && '' !== (string) $check['action_url'] ) {
			echo '<a class="button button-small mw-check-action" href="' . esc_url( (string) $check['action_url'] ) . '">' . esc_html( (string) $check['action_label'] ) . '</a>';
		}
		echo '</div>';
	}

	private function render_system_info(): void {
		$stats = array(
			array( MUSIC_WAVE_CORE_VERSION, __( 'هسته MusicWave', 'music-wave-core' ), 'dashicons-controls-play' ),
			array( (string) get_option( MigrationRunner::OPTION, '0.0.0' ), __( 'طرح‌وارهٔ پایگاه داده', 'music-wave-core' ), 'dashicons-database' ),
			array( get_bloginfo( 'version' ), __( 'WordPress', 'music-wave-core' ), 'dashicons-wordpress' ),
			array( PHP_VERSION, __( 'PHP', 'music-wave-core' ), 'dashicons-performance' ),
		);

		echo '<div class="mw-settings__panel"><h2>' . esc_html__( 'عکس فوری سیستم', 'music-wave-core' ) . '</h2>';
		echo '<div class="mw-settings__grid mw-settings__grid--stats">';
		foreach ( $stats as $stat ) {
			echo '<div class="mw-stat"><span class="dashicons ' . esc_attr( (string) $stat[2] ) . '" aria-hidden="true"></span><div><strong>' . esc_html( (string) $stat[0] ) . '</strong><span>' . esc_html( (string) $stat[1] ) . '</span></div></div>';
		}
		echo '</div></div>';
	}

	private function render_demo_section(): void {
		$user_id = get_current_user_id();
		$notice  = get_transient( self::DEMO_NOTICE_KEY . $user_id );

		echo '<div class="mw-settings__panel"><h2>' . esc_html__( 'کاتالوگ نسخهٔ نمایشی', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'چهار انتشار نمونهٔ عمومی بسازید: یک قطعه، آلبوم، برنامهٔ پادکست و قسمت پادکست. درون‌ریز هرگز انتشارهای تجاری موجود را تغییر نمی‌دهد و هر نامک نشانیِ تکراری را رد می‌کند.', 'music-wave-core' ) . '</p>';

		if ( is_array( $notice ) ) {
			delete_transient( self::DEMO_NOTICE_KEY . $user_id );
			$class = ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}

		echo '<div class="mw-settings__actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'music_wave_import_demo' );
		echo '<input type="hidden" name="action" value="music_wave_import_demo"><button class="button button-primary" type="submit">' . esc_html__( 'درون‌ریزی کاتالوگ نمایشی', 'music-wave-core' ) . '</button></form>';
		if ( $this->demo_importer->has_demo_content() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'music_wave_remove_demo' );
			echo '<input type="hidden" name="action" value="music_wave_remove_demo"><button class="button-link-delete" type="submit">' . esc_html__( 'حذف انتشارهای نمایشی MusicWave', 'music-wave-core' ) . '</button></form>';
		}
		echo '</div><p class="description">' . esc_html__( 'انتشارهای آزمایشی حاوی داده‌های دارایی، محصول، مشتری یا عضویت حفاظت‌شده نیستند. با حذف آن‌ها، اصطلاحاتی که ممکن است قبلاً استفاده می‌کنید حفظ شود.', 'music-wave-core' ) . '</p></div>';
	}

	private function handle_demo_action( string $nonce_action, string $success_message ): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'شما مجاز به مدیریت محتوای آزمایشی MusicWave نیستید.', 'music-wave-core' ) );
		}

		$result  = 'music_wave_import_demo' === $nonce_action ? $this->demo_importer->import() : $this->demo_importer->remove();
		$message = $success_message;
		if ( ! empty( $result['messages'] ) && is_array( $result['messages'] ) ) {
			$message .= ' ' . implode( ' ', $result['messages'] );
		}
		set_transient(
			self::DEMO_NOTICE_KEY . get_current_user_id(),
			array(
				'message' => $message,
				'error'   => false,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'edit.php?post_type=mw_release&page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Build the environment checklist with severity and follow-up actions.
	 *
	 * @return array<int, array<string, bool|string>>
	 */
	private function checks(): array {
		$schema_version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$schema_ready   = version_compare( $schema_version, MigrationRunner::LATEST_VERSION, '>=' );
		$theme          = wp_get_theme();
		$theme_ready    = 'musicwave' === $theme->get_stylesheet();
		$theme_name     = (string) $theme->get( 'Name' );
		$woo_ready      = class_exists( 'WooCommerce' );
		$vip_active     = defined( 'MUSIC_WAVE_VIP_FILE' );
		$provider_ready = (bool) has_filter( 'music_wave_download_provider' );
		$permalinks     = '' !== (string) get_option( 'permalink_structure', '' );
		$cron_disabled  = defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' );

		return array(
			array(
				'label'        => __( 'شمای پایگاه داده MusicWave', 'music-wave-core' ),
				'passed'       => $schema_ready,
				'severity'     => 'required',
				'detail'       => $schema_ready
					? sprintf(
						/* translators: 1: installed schema version, 2: required schema version. */
						__( 'نسخهٔ نصب‌شدهٔ طرح‌واره (%1$s) با افزونهٔ فعلی سازگار است (نسخهٔ %2$s لازم است).', 'music-wave-core' ),
						$schema_version,
						MigrationRunner::LATEST_VERSION
					)
					: sprintf(
						/* translators: 1: installed schema version, 2: required schema version. */
						__( 'نسخهٔ نصب‌شدهٔ طرح‌واره: %1$s؛ نسخهٔ موردنیاز: %2$s. دفعهٔ بعد که مدیر داشبورد را بارگیری کند، به‌روزرسانی‌ها به‌طور خودکار اجرا می‌شوند.', 'music-wave-core' ),
						$schema_version,
						MigrationRunner::LATEST_VERSION
					),
				'action_url'   => '',
				'action_label' => '',
			),
			array(
				'label'        => __( 'پیوندهای ثابت زیبا', 'music-wave-core' ),
				'passed'       => $permalinks,
				'severity'     => 'required',
				'detail'       => __( 'URLهای انتشار، هنرمند و کاتالوگ به ساختار پیوند ثابت غیر پیش‌فرض نیاز دارند.', 'music-wave-core' ),
				'action_url'   => $permalinks ? '' : admin_url( 'options-permalink.php' ),
				'action_label' => __( 'باز کردن تنظیمات پیوندهای یکتا', 'music-wave-core' ),
			),
			array(
				'label'        => __( 'تم بلوک MusicWave', 'music-wave-core' ),
				'passed'       => $theme_ready,
				'severity'     => 'required',
				'detail'       => $theme_ready
					? __( 'قالب بلوکی همراه، کاتالوگ، هنرمند و قابلیت‌های تجاری را ارائه می‌دهد.', 'music-wave-core' )
					: sprintf(
						/* translators: %s: active theme name. */
						__( 'قالب فعال: %s. برای نمایش مطلوب کاتالوگ، قالب همراه MusicWave را فعال کنید.', 'music-wave-core' ),
						'' !== $theme_name ? $theme_name : get_stylesheet()
					),
				'action_url'   => $theme_ready ? '' : admin_url( 'themes.php' ),
				'action_label' => __( 'باز کردن مرورگر تم', 'music-wave-core' ),
			),
			array(
				'label'        => __( 'WooCommerce', 'music-wave-core' ),
				'passed'       => $woo_ready,
				'severity'     => 'required',
				'detail'       => $woo_ready
					? __( 'نگاشت محصول، مالکیت خرید و پرداخت در دسترس است.', 'music-wave-core' )
					: __( 'برای فروش انتشارهای منتشرشده لازم است. محتوای کاتالوگ عمومی رایگان بدون آن کار می‌کند.', 'music-wave-core' ),
				'action_url'   => $woo_ready ? '' : admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ),
				'action_label' => __( 'نصب WooCommerce', 'music-wave-core' ),
			),
			array(
				'label'        => __( 'تحویل حفاظت‌شده', 'music-wave-core' ),
				'passed'       => $provider_ready,
				'severity'     => 'recommended',
				'detail'       => $provider_ready
					? __( 'یک ارائه‌دهنده دارایی‌های حفاظت‌شده غیرشفاف را پس از تصویب بررسی‌های دسترسی، پخش می‌کند.', 'music-wave-core' )
					: ( $vip_active
						? __( 'MusicWave VIP فعال است اما هنوز ارائه‌دهنده تحویل ثبت نشده است. برای فعال کردن دانلودهای حفاظت‌شده، تنظیمات تحویل VIP را ذخیره کنید.', 'music-wave-core' )
						: __( 'پیش از اختصاص دارایی‌های دانلود حفاظت‌شده به انتشارها، MusicWave VIP را فعال کنید.', 'music-wave-core' ) ),
				'action_url'   => $provider_ready ? '' : ( $vip_active ? admin_url( 'edit.php?post_type=mw_release&page=music-wave-vip' ) : admin_url( 'plugins.php' ) ),
				'action_label' => $vip_active ? __( 'تنظیمات MusicWave VIP', 'music-wave-core' ) : __( 'باز کردن صفحهٔ افزونه‌ها', 'music-wave-core' ),
			),
			array(
				'label'        => __( 'داده‌های ساختاریافتهٔ موسیقی', 'music-wave-core' ),
				'passed'       => true,
				'severity'     => 'recommended',
				'detail'       => $this->has_competing_seo_plugin()
					? __( 'یک افزونهٔ SEO شناخته‌شده فعال است، بنابراین MusicWave JSON-LD به‌طور پیش‌فرض غیرفعال می‌ماند تا از طرح‌های تکراری جلوگیری شود.', 'music-wave-core' )
					: __( 'MusicWave می‌تواند ضبط موسیقی، آلبوم موسیقی و طرح‌وارهٔ پادکست را منتشر کند. هنگامی‌که هیچ افزونهٔ دیگری طرح موسیقی معادل را منتشر نمی‌کند، آن را از طریق فیلتر مستندشده فعال کنید.', 'music-wave-core' ),
				'action_url'   => '',
				'action_label' => '',
			),
			array(
				'label'        => __( 'نسخه PHP', 'music-wave-core' ),
				'passed'       => version_compare( PHP_VERSION, '8.0', '>=' ),
				'severity'     => 'recommended',
				'detail'       => sprintf(
					/* translators: %s: running PHP version. */
					__( 'در حال اجرا PHP %s. MusicWave به 7.4 یا جدیدتر نیاز دارد. 8.0 یا جدیدتر برای به‌روزرسانی‌های امنیتی و عملکرد توصیه می‌شود.', 'music-wave-core' ),
					PHP_VERSION
				),
				'action_url'   => '',
				'action_label' => '',
			),
			array(
				'label'        => __( 'پردازش پس زمینه', 'music-wave-core' ),
				'passed'       => ! $cron_disabled,
				'severity'     => 'recommended',
				'detail'       => $cron_disabled
					? __( 'WP-Cron غیرفعال است. MusicWave پاک‌سازی دانلود، اعلان انتشار و نگه‌داری عضویت را زمان‌بندی می‌کند؛ مطمئن شوید یک کار cron واقعی، wp-cron.php را اجرا می‌کند.', 'music-wave-core' )
					: __( 'رویدادهای پاک‌سازی، اعلان و نگه‌داری زمان‌بندی‌شده می‌توانند اجرا شوند.', 'music-wave-core' ),
				'action_url'   => '',
				'action_label' => '',
			),
		);
	}

	private function has_competing_seo_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Register contextual help tabs for the setup screen.
	 *
	 * @return void
	 */
	private function register_help_tabs(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! method_exists( $screen, 'add_help_tab' ) ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-setup-guide',
				'title'   => __( 'راهنمای راه‌اندازی', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'این صفحه شما را در راه‌اندازی فروشگاه MusicWave راهنمایی می‌کند:', 'music-wave-core' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'خلاصهٔ آمادگی — آیا همهٔ بررسی‌های محیطی لازم انجام شده‌اند یا خیر.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'شروع سریع — چهار گام نخست: ایجاد انتشار، بررسی طبقه‌بندی، ذخیرهٔ پیوندهای یکتا و پیکربندی تحویل حفاظت‌شده.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'بررسی‌های محیطی — موارد لازم برای راه‌اندازی را مسدود می‌کنند. موارد توصیه‌شده صرفاً جنبهٔ راهنمایی دارند.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'عکس فوری سیستم و کاتالوگ نمایشی — نسخه‌ها در یک نگاه و محتوای نمونهٔ امن برای آزمایش.', 'music-wave-core' ) . '</li>' .
					'</ul>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-setup-diagnostics',
				'title'   => __( 'راهنما و تشخیص', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'همین بررسی‌های محیطی در صفحه سلامت سایت WordPress همراه با گزارش‌های سلامت ارائه‌دهنده یکپارچه‌سازی MusicWave نیز اجرا می‌شوند.', 'music-wave-core' ) . '</p>' .
					'<p><a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'باز کردن سلامت سایت', 'music-wave-core' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings' ) ) . '">' . esc_html__( 'باز کردن مرکز کنترل', 'music-wave-core' ) . '</a></p>',
			)
		);
	}
}
