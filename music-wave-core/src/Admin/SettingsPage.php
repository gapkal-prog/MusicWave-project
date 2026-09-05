<?php
/**
 * MusicWave administration hub and settings page.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Admin;

use ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery;
use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Listening\ListeningRepository;
use ManaCore\MusicWave\Core\Migrations\MigrationRunner;
use ManaCore\MusicWave\Core\Playlists\PlaylistRepository;
use ManaCore\MusicWave\Core\Support\Settings;

final class SettingsPage {
	private const PAGE = 'music-wave-settings';

	/** @var BulkAccessManager */
	private $bulk_access;

	/** @var PlaylistRepository|null */
	private $playlists;

	/** @var ListeningRepository|null */
	private $listening;

	public function __construct( BulkAccessManager $bulk_access, ?PlaylistRepository $playlists = null, ?ListeningRepository $listening = null ) {
		$this->bulk_access = $bulk_access;
		$this->playlists   = $playlists;
		$this->listening   = $listening;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->bulk_access->register();
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . ReleasePostType::KEY,
			__( 'تنظیمات MusicWave', 'music-wave-core' ),
			__( 'تنظیمات و نمای کلی', 'music-wave-core' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'music_wave_core',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

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

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) && is_scalar( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation tab, sanitized and allow-listed below.
		if ( ! in_array( $tab, array( 'overview', 'content', 'access', 'delivery', 'integrations', 'manage' ), true ) ) {
			$tab = 'overview';
		}

		$this->register_help_tabs();

		echo '<div class="wrap mw-settings"><h1>' . esc_html__( 'مرکز کنترل MusicWave', 'music-wave-core' ) . '</h1>';
		echo '<p class="mw-settings__lead">' . esc_html__( 'پیش‌فرض‌های کاتالوگ، رفتار دسترسی، محدودیت‌های تحویل، ادغام‌ها و صفحه‌های WordPress را که نمایش را کنترل می‌کنند، مدیریت کنید.', 'music-wave-core' ) . '</p>';
		$this->render_tabs( $tab );
		settings_errors();

		switch ( $tab ) {
			case 'content':
				$this->render_content_settings();
				break;
			case 'access':
				$this->render_access();
				break;
			case 'delivery':
				$this->render_delivery_settings();
				break;
			case 'integrations':
				$this->render_integrations();
				break;
			case 'manage':
				$this->render_management_links();
				break;
			case 'overview':
			default:
				$this->render_overview();
				break;
		}

		echo '</div>';
	}

	private function render_tabs( string $active ): void {
		$tabs = array(
			'overview'     => __( 'نمای کلی', 'music-wave-core' ),
			'content'      => __( 'محتوا و نمایش', 'music-wave-core' ),
			'access'       => __( 'دسترسی', 'music-wave-core' ),
			'delivery'     => __( 'تحویل و حریم خصوصی', 'music-wave-core' ),
			'integrations' => __( 'ادغام', 'music-wave-core' ),
			'manage'       => __( 'پیوندهای مدیریت', 'music-wave-core' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'تنظیمات MusicWave', 'music-wave-core' ) . '">';
		foreach ( $tabs as $key => $label ) {
			$url   = add_query_arg(
				array(
					'post_type' => ReleasePostType::KEY,
					'page'      => self::PAGE,
					'tab'       => $key,
				),
				admin_url( 'edit.php' )
			);
			$class = $active === $key ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	private function render_overview(): void {
		$counts    = wp_count_posts( ReleasePostType::KEY );
		$published = isset( $counts->publish ) ? absint( $counts->publish ) : 0;
		$drafts    = isset( $counts->draft ) ? absint( $counts->draft ) : 0;
		$theme     = wp_get_theme();

		echo '<div class="mw-settings__grid mw-settings__grid--stats">';
		$this->stat( __( 'انتشارات منتشرشده', 'music-wave-core' ), number_format_i18n( $published ), 'dashicons-album' );
		$this->stat( __( 'انتشارهای پیش‌نویس', 'music-wave-core' ), number_format_i18n( $drafts ), 'dashicons-edit-page' );
		if ( null !== $this->playlists ) {
			$this->stat( __( 'فهرست پخش عمومی', 'music-wave-core' ), number_format_i18n( $this->playlists->count_public() ), 'dashicons-playlist' );
		}
		if ( null !== $this->listening ) {
			$this->stat( __( 'رویدادهای شنیداری', 'music-wave-core' ), number_format_i18n( $this->listening->count_all() ), 'dashicons-controls-play' );
		}
		$this->stat( __( 'قالب فعال', 'music-wave-core' ), (string) $theme->get( 'Name' ), 'dashicons-admin-appearance' );
		$this->stat( __( 'نسخهٔ اصلی', 'music-wave-core' ), MUSIC_WAVE_CORE_VERSION, 'dashicons-update' );
		echo '</div>';

		echo '<div class="mw-settings__columns"><section class="mw-settings__panel"><h2>' . esc_html__( 'اقدامات سریع', 'music-wave-core' ) . '</h2>';
		$this->action_link( admin_url( 'post-new.php?post_type=' . ReleasePostType::KEY ), __( 'افزودن انتشار', 'music-wave-core' ), __( 'محتوای کاتالوگ را ایجاد و پیکربندی کنید.', 'music-wave-core' ) );
		$this->action_link( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY ), __( 'مرور همهٔ انتشارها', 'music-wave-core' ), __( 'وضعیت آمادگی و دسترسی را بررسی کنید.', 'music-wave-core' ) );
		$this->action_link( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY . '&page=music-wave-setup' ), __( 'راه‌اندازی و عیب‌یابی', 'music-wave-core' ), __( 'طرح‌واره، تحویل، سئو و سلامت محیط را بررسی کنید.', 'music-wave-core' ) );
		echo '</section><section class="mw-settings__panel"><h2>' . esc_html__( 'وضعیت سیستم', 'music-wave-core' ) . '</h2>';
		$this->status_row( __( 'WooCommerce', 'music-wave-core' ), class_exists( 'WooCommerce' ), __( 'نگاشت محصول و دسترسی خرید', 'music-wave-core' ) );
		$schema_version = (string) get_option( MigrationRunner::OPTION, '0.0.0' );
		$this->status_row(
			__( 'طرح کاتالوگ', 'music-wave-core' ),
			version_compare( $schema_version, MigrationRunner::LATEST_VERSION, '>=' ),
			sprintf(
				/* translators: 1: installed schema version, 2: required schema version. */
				__( '%1$s نصب‌شده است. %2$s موردنیاز است', 'music-wave-core' ),
				$schema_version,
				MigrationRunner::LATEST_VERSION
			)
		);
		$this->status_row( __( 'تحویل حفاظت‌شده', 'music-wave-core' ), has_filter( 'music_wave_download_provider' ), __( 'فایل‌های خصوصی و دانلود ایمن', 'music-wave-core' ) );
		$this->status_row( __( 'ارائه‌دهنده عضویت', 'music-wave-core' ), has_filter( 'music_wave_membership_provider' ), __( 'تصمیمات دسترسی مبتنی بر عضویت', 'music-wave-core' ) );
		$this->status_row( __( 'تم بلوک همراه', 'music-wave-core' ), 'musicwave' === $theme->get_stylesheet(), __( 'قالب‌ها و سبک‌های MusicWave', 'music-wave-core' ) );
		echo '</section></div>';
	}

	private function render_content_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'پیش‌فرض‌های انتشار جدید', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'این مقادیر هنگام ایجاد انتشار جدید ذخیره می‌شوند. انتشارهای موجود هرگز بی‌سروصدا بازنویسی نمی‌شوند.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->select_row( 'default_access_mode', __( 'حالت دسترسی پیش‌فرض', 'music-wave-core' ), (string) $settings['default_access_mode'], $this->access_labels(), __( 'زمانی که انتشارهای جدید نباید به‌طور تصادفی عمومی شوند، از «محدود» استفاده کنید.', 'music-wave-core' ) );
		$this->number_row( 'default_preview_duration', __( 'مدت پیش‌نمایش پیش‌فرض', 'music-wave-core' ), (int) $settings['default_preview_duration'], 10, 120, __( 'ثانیه؛ هر انتشار می‌تواند این مقدار را لغو کند.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'آرشیو کاتالوگ', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->number_row( 'archive_per_page', __( 'انتشار در هر صفحه', 'music-wave-core' ), (int) $settings['archive_per_page'], 1, 100, __( 'فقط آرشیو عمومی MusicWave را کنترل می‌کند.', 'music-wave-core' ) );
		$this->select_row( 'archive_default_sort', __( 'مرتب‌سازی پیش‌فرض', 'music-wave-core' ), (string) $settings['archive_default_sort'], ReleaseArchiveQuery::sort_options(), __( 'بازدیدکنندگان همچنان می‌توانند حالت مرتب‌سازی دیگری را انتخاب کنند.', 'music-wave-core' ) );
		$toggles = array(
			'enabled'  => __( 'فعال شد', 'music-wave-core' ),
			'disabled' => __( 'غیرفعال', 'music-wave-core' ),
		);
		$this->select_row( 'show_same_artist_releases', __( 'انتشارهای بیشتر از همین هنرمند', 'music-wave-core' ), (string) $settings['show_same_artist_releases'], $toggles, __( 'انتشارهای دیگری را نشان می‌دهد که در صفحات انتشار، هنرمند مشترکی دارند.', 'music-wave-core' ) );
		$this->select_row( 'show_similar_releases', __( 'انتشارهای مشابه', 'music-wave-core' ), (string) $settings['show_similar_releases'], $toggles, __( 'از اصطلاحات ژانر، حال‌وهوا، و نوع انتشار مشترک بدون افشای فرادادهٔ دسترسی خصوصی استفاده می‌کند.', 'music-wave-core' ) );
		$this->number_row( 'related_items_per_section', __( 'موارد مرتبط در هر بخش', 'music-wave-core' ), (int) $settings['related_items_per_section'], 2, 12, __( 'هر بخش مرتبط را به جست‌وجویی قابل‌پیش‌بینی و کم‌هزینه محدود می‌کند.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'نوار لغزنده انتشار ویژه', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row( 'slider_enabled', __( 'اسلایدر', 'music-wave-core' ), (string) $settings['slider_enabled'], $toggles, __( 'لغزنده‌های انتشار MusicWave را به‌صورت جهانی فعال یا غیرفعال می‌کند. بلوک‌های منفرد همچنان می‌توانند این تنظیم را به ارث ببرند یا لغو کنند.', 'music-wave-core' ) );
		$this->select_row( 'slider_autoplay', __( 'پخش خودکار', 'music-wave-core' ), (string) $settings['slider_autoplay'], $toggles, __( 'پخش خودکار برای بازدیدکنندگانی که حرکت کمتر را ترجیح می‌دهند، به‌طور خودکار غیرفعال می‌شود.', 'music-wave-core' ) );
		$this->select_row( 'slider_loop', __( 'حلقه', 'music-wave-core' ), (string) $settings['slider_loop'], $toggles, __( 'پس از آخرین گروه از انتشارها به ابتدا بازمی‌گردد.', 'music-wave-core' ) );
		$this->select_row( 'slider_pause_on_hover', __( 'مکث هنگام قرارگیری نشانگر یا فوکوس', 'music-wave-core' ), (string) $settings['slider_pause_on_hover'], $toggles, __( 'از حرکت خودکار در زمانی که بازدیدکننده در حال تعامل با لغزنده است جلوگیری می‌کند.', 'music-wave-core' ) );
		$this->select_row( 'slider_show_arrows', __( 'فلش‌های ناوبری', 'music-wave-core' ), (string) $settings['slider_show_arrows'], $toggles, __( 'کنترل‌های قبلی و بعدی را نشان می‌دهد.', 'music-wave-core' ) );
		$this->select_row( 'slider_show_dots', __( 'نقاط صفحه‌بندی', 'music-wave-core' ), (string) $settings['slider_show_dots'], $toggles, __( 'گروه اسلاید فعلی و کنترل‌های پیمایش مستقیم را نشان می‌دهد.', 'music-wave-core' ) );
		$this->number_row( 'slider_interval', __( 'فاصلهٔ پخش خودکار', 'music-wave-core' ), (int) $settings['slider_interval'], 2000, 20000, __( 'میلی ثانیه بین حرکات خودکار.', 'music-wave-core' ) );
		$this->number_row( 'slider_items', __( 'تعداد انتشارهای لغزنده', 'music-wave-core' ), (int) $settings['slider_items'], 3, 12, __( 'حداکثر تعداد کارت‌های انتشار که توسط هر لغزنده درخواست شده است.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'پخش مداوم', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row( 'persistent_navigation', __( 'به پخش در صفحات ادامه دهید', 'music-wave-core' ), (string) $settings['persistent_navigation'], $toggles, __( 'محتوای صفحه را در پس‌زمینه تغییر می‌دهد تا پخش‌کننده پیش‌نمایش موسیقی در حین مرور بازدیدکنندگان به پخش ادامه دهد. صفحه‌های سبد خرید، تسویه‌حساب و مدیریت هرگز رهگیری نمی‌شوند.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'سئو و داده‌های ساختاریافته', 'music-wave-core' ) . '</h2><table class="form-table" role="presentation">';
		$this->select_row(
			'json_ld_mode',
			__( 'داده‌های ساختاریافتهٔ موسیقی', 'music-wave-core' ),
			(string) $settings['json_ld_mode'],
			array(
				'auto'     => __( 'تشخیص خودکار تعارض', 'music-wave-core' ),
				'enabled'  => __( 'همیشه فعال', 'music-wave-core' ),
				'disabled' => __( 'غیرفعال', 'music-wave-core' ),
			),
			__( 'حالت خودکار وقتی یک افزونهٔ SEO شناخته‌شده فعال باشد، JSON-LD هسته را غیرفعال می‌کند.', 'music-wave-core' )
		);
		echo '</table></section>';
		submit_button( __( 'ذخیره تنظیمات محتوا', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_access(): void {
		$notice = $this->bulk_access->consume_notice();
		if ( is_array( $notice ) ) {
			$type = isset( $notice['type'] ) ? sanitize_html_class( (string) $notice['type'] ) : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}
		$job = $this->bulk_access->active_job();
		if ( is_array( $job ) ) {
			echo '<section class="mw-settings__panel mw-settings__panel--job"><h2>' . esc_html__( 'عملیات دسترسی گروهی در حال انجام است', 'music-wave-core' ) . '</h2>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: number of releases updated, 2: number of releases skipped. */
					__( '%1$d مورد به‌روزرسانی شد و %2$d مورد رد شد. پردازش بستهٔ امن بعدی را ادامه دهید.', 'music-wave-core' ),
					(int) $job['updated'],
					(int) $job['skipped']
				)
			) . '</p>';
			echo '<div class="mw-settings__actions">';
			$this->job_form( 'music_wave_bulk_access_continue', __( 'ادامهٔ مرحلهٔ بعد', 'music-wave-core' ), 'button button-primary' );
			$this->job_form( 'music_wave_bulk_access_cancel', __( 'کار را لغو کنید', 'music-wave-core' ), 'button button-secondary' );
			echo '</div></section>';
		}

		$settings = Settings::all();
		$this->settings_form_start();
		$absent = (string) $settings['membership_absent_behavior'];
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'رفتار ماژول عضویت', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'وقتی هیچ ماژول عضویتی مانند MusicWave VIP فعال نیست، انتشارهای دارای محدودیت عضویت می‌توانند برای همه در دسترس بمانند یا تا زمان فعال‌شدن یک ماژول، دسترسی‌شان مسدود شود. خاموش‌کردن کلید اصلی VIP نیز همین محدودیت را غیرفعال می‌کند، درحالی‌که تحویل امن ادامه دارد.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		echo '<tr><th>' . esc_html__( 'در حالی که هیچ ماژول عضویت فعال نیست', 'music-wave-core' ) . '</th><td><label style="display:block;margin-bottom:6px;"><input type="radio" name="' . esc_attr( Settings::OPTION ) . '[membership_absent_behavior]" value="allow" ' . checked( $absent, 'allow', false ) . '> ' . esc_html__( 'دسترسی آزاد — انتشارهای عضویت رایگان باقی می‌مانند (توصیه می‌شود)', 'music-wave-core' ) . '</label><label style="display:block;"><input type="radio" name="' . esc_attr( Settings::OPTION ) . '[membership_absent_behavior]" value="deny" ' . checked( $absent, 'deny', false ) . '> ' . esc_html__( 'محدودکردن — تا زمان فعال‌شدن یک ماژول عضویت، دسترسی به انتشارهای عضویتی را مسدود کنید', 'music-wave-core' ) . '</label></td></tr>';
		echo '</table></section>';
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'پیام‌های دسترسی مشتری', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'برای استفاده از پیش‌فرض MusicWave ترجمه‌شده، یک فیلد خالی بگذارید. متن سفارشی قبل از خروجی حذف می‌شود.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->text_row( 'purchase_message', __( 'پیام خرید', 'music-wave-core' ), (string) $settings['purchase_message'] );
		$this->text_row( 'purchase_cta_label', __( 'برچسب دکمه خرید', 'music-wave-core' ), (string) $settings['purchase_cta_label'] );
		$this->text_row( 'membership_message', __( 'پیام عضویت', 'music-wave-core' ), (string) $settings['membership_message'] );
		$this->text_row( 'membership_cta_label', __( 'برچسب دکمه عضویت', 'music-wave-core' ), (string) $settings['membership_cta_label'] );
		$this->url_row( 'membership_cta_url', __( 'نشانی صفحهٔ عضویت', 'music-wave-core' ), (string) $settings['membership_cta_url'] );
		$this->text_row( 'restricted_message', __( 'پیام دسترسی محدودشده', 'music-wave-core' ), (string) $settings['restricted_message'] );
		$this->text_row( 'access_granted_message', __( 'پیام دسترسی اعطاشده', 'music-wave-core' ), (string) $settings['access_granted_message'] );
		echo '</table></section>';
		submit_button( __( 'ذخیره پیام‌های دسترسی', 'music-wave-core' ) );
		echo '</form>';

		if ( null === $job ) {
			$this->render_bulk_form();
		}
	}

	private function render_delivery_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'دانلودهای ایمن', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'صدور توکن محدود و تحویل فایل به ازای هر بازدیدکننده. پیوندهای دانلود بدون در نظر گرفتن این محدودیت‌ها دارای امضا، کوتاه‌مدت و یک‌بار استفاده می‌مانند.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'download_rate_limit', __( 'درخواست توکن در هر پنجره', 'music-wave-core' ), (int) $settings['download_rate_limit'], 1, 1000, __( 'حداکثر تعداد توکن‌های دانلود یا پخش جریانی که یک بازدیدکننده می‌تواند پیش از اعمال محدودیت درخواست کند.', 'music-wave-core' ) );
		$this->number_row( 'download_rate_window', __( 'پنجرهٔ محدودیت نرخ (ثانیه)', 'music-wave-core' ), (int) $settings['download_rate_window'], 10, 3600, __( 'طول پنجرهٔ ثابت استفاده‌شده توسط محدودیت نرخ رمز.', 'music-wave-core' ) );
		$this->number_row( 'download_daily_quota', __( 'سهمیهٔ تحویل روزانه', 'music-wave-core' ), (int) $settings['download_daily_quota'], 0, 10000, __( 'تحویل فایل تکمیل‌شده برای هر بازدیدکننده در روز مجاز است. 0 را برای بدون سقف روزانه تنظیم کنید.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'جست‌وجو و کشف کاتالوگ', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'تکمیل خودکار، فیلترها و توصیه‌ها نقاط پایانی عمومی هستند. این محدودیت‌ها آن‌ها را سریع و در برابر سوء استفاده مقاوم می‌کند.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'discovery_rate_limit', __( 'درخواست در هر پنجره', 'music-wave-core' ), (int) $settings['discovery_rate_limit'], 1, 1000, __( 'حداکثر درخواست‌های کشف که یک بازدیدکننده می‌تواند قبل از محدودشدن انجام دهد.', 'music-wave-core' ) );
		$this->number_row( 'discovery_rate_window', __( 'پنجرهٔ محدودیت نرخ (ثانیه)', 'music-wave-core' ), (int) $settings['discovery_rate_window'], 10, 3600, __( 'طول پنجرهٔ ثابت استفاده‌شده توسط محدودیت نرخ کشف.', 'music-wave-core' ) );
		$this->number_row( 'discovery_cache_ttl', __( 'مدت نگه‌داری حافظهٔ نهان جست‌وجو (ثانیه)', 'music-wave-core' ), (int) $settings['discovery_cache_ttl'], 30, 86400, __( 'چه مدت پیشنهادها و نتایج فیلتر در حافظهٔ نهان ذخیره می‌شوند. مقادیر طولانی‌تر بار پایگاه داده را کاهش می‌دهد. تغییرات کاتالوگ بعداً اعمال می‌شوند.', 'music-wave-core' ) );
		echo '</table></section>';

		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'تاریخچه گوش‌دادن', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'فعالیت پخش فقط برای بازدیدکنندگانی که با ذخیرهٔ سابقهٔ گوش‌دادن موافقت کرده‌اند، ثبت می‌شود. ورودی‌های قدیمی‌تر در پاک‌سازی روزانه حذف می‌شوند و کاربران می‌توانند سابقهٔ شخصی خود را از حسابشان پاک کنند.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->number_row( 'listening_retention_days', __( 'مدت نگهداری (روز)', 'music-wave-core' ), (int) $settings['listening_retention_days'], 1, 3650, __( 'فعالیت قدیمی‌تر از این پنجره به‌طور خودکار حذف می‌شود.', 'music-wave-core' ) );
		echo '</table></section>';

		submit_button( __( 'ذخیره تنظیمات تحویل و حریم خصوصی', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_bulk_form(): void {
		$types = get_terms(
			array(
				'taxonomy'   => 'mw_release_type',
				'hide_empty' => false,
			)
		);
		echo '<section class="mw-settings__panel mw-settings__panel--danger"><h2>' . esc_html__( 'حالت دسترسی انبوه', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'انتشارها را در دسته‌های 200 به‌روزرسانی کنید. حال‌وهواهای خرید و عضویت فقط زمانی اعمال می‌شوند که هر انتشار قبلاً محصول یا نگاشت سطح موردنیاز را داشته باشد. انتشارهای ناقص نادیده گرفته می‌شوند.', 'music-wave-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'music_wave_bulk_access_start' );
		echo '<input type="hidden" name="action" value="music_wave_bulk_access_start"><div class="mw-settings__bulk-grid">';
		$this->plain_select( 'target_mode', __( 'حالت دسترسی جدید', 'music-wave-core' ), $this->access_labels(), 'restricted' );
		$this->plain_select( 'current_mode', __( 'حالت فعلی', 'music-wave-core' ), array_merge( array( 'any' => __( 'هر حالت', 'music-wave-core' ) ), $this->access_labels() ), 'any' );
		$this->plain_select(
			'post_status',
			__( 'وضعیت پست', 'music-wave-core' ),
			array(
				'any'     => __( 'هر وضعیت فعال', 'music-wave-core' ),
				'publish' => __( 'منتشر شد', 'music-wave-core' ),
				'draft'   => __( 'پیش‌نویس', 'music-wave-core' ),
				'pending' => __( 'در انتظار بررسی', 'music-wave-core' ),
				'private' => __( 'خصوصی', 'music-wave-core' ),
				'future'  => __( 'زمان‌بندی‌شده', 'music-wave-core' ),
			),
			'any'
		);
		$options = array( 'any' => __( 'هر نوع انتشار', 'music-wave-core' ) );
		if ( is_array( $types ) ) {
			foreach ( $types as $type ) {
				$options[ $type->slug ] = $type->name;
			}
		}
		$this->plain_select( 'release_type', __( 'نوع انتشار', 'music-wave-core' ), $options, 'any' );
		echo '</div><label class="mw-settings__confirm"><input type="checkbox" required> ' . esc_html__( 'من متوجه هستم که این سیاست دسترسی به تغییرات برای هر انتشار منطبق است.', 'music-wave-core' ) . '</label>';
		submit_button( __( 'شروع به‌روزرسانی انبوه', 'music-wave-core' ), 'primary', 'submit', false );
		echo '</form></section>';
	}

	private function render_integrations(): void {
		$theme = wp_get_theme();
		$cards = array(
			array(
				'id'          => 'woocommerce',
				'name'        => __( 'WooCommerce', 'music-wave-core' ),
				'active'      => class_exists( 'WooCommerce' ),
				'description' => __( 'محصولات، مالکیت خرید، پرداخت، و کتابخانه مشتری.', 'music-wave-core' ),
				'url'         => class_exists( 'WooCommerce' ) ? admin_url( 'admin.php?page=wc-settings' ) : admin_url( 'plugin-install.php?s=WooCommerce&tab=search&type=term' ),
				'action'      => class_exists( 'WooCommerce' ) ? __( 'تنظیمات WooCommerce را باز کنید', 'music-wave-core' ) : __( 'نصب WooCommerce', 'music-wave-core' ),
			),
			array(
				'id'          => 'music-wave-vip',
				'name'        => __( 'MusicWave VIP', 'music-wave-core' ),
				'active'      => defined( 'MUSIC_WAVE_VIP_FILE' ),
				'description' => __( 'فایل‌های محلی حفاظت‌شده و آداپتور عضویت مبتنی بر نقش.', 'music-wave-core' ),
				'url'         => defined( 'MUSIC_WAVE_VIP_FILE' ) ? admin_url( 'edit.php?post_type=mw_release&page=music-wave-vip' ) : admin_url( 'plugins.php' ),
				'action'      => defined( 'MUSIC_WAVE_VIP_FILE' ) ? __( 'باز کردن تنظیمات مستقل', 'music-wave-core' ) : __( 'بررسی افزونه‌ها', 'music-wave-core' ),
			),
			array(
				'id'          => 'musicwave-theme',
				'name'        => __( 'تم MusicWave', 'music-wave-core' ),
				'active'      => 'musicwave' === $theme->get_stylesheet(),
				'description' => __( 'قالب‌ها، سبک‌ها، الگوها و ویرایش بصری را مسدود کنید.', 'music-wave-core' ),
				'url'         => admin_url( 'site-editor.php' ),
				'action'      => __( 'باز کردن ویرایشگر سایت', 'music-wave-core' ),
			),
		);

		/**
		 * Filter integration cards shown in the MusicWave control center.
		 *
		 * @param array<int, array<string, mixed>> $cards Integration definitions.
		 */
		$cards = apply_filters( 'music_wave_admin_integrations', $cards );
		echo '<div class="mw-settings__grid mw-settings__grid--cards">';
		if ( is_array( $cards ) ) {
			foreach ( $cards as $card ) {
				if ( ! is_array( $card ) || empty( $card['name'] ) ) {
					continue;
				}
				$active = ! empty( $card['active'] );
				echo '<section class="mw-settings__panel mw-integration"><div class="mw-integration__heading"><h2>' . esc_html( (string) $card['name'] ) . '</h2><span class="mw-status mw-status--' . esc_attr( $active ? 'active' : 'inactive' ) . '">' . esc_html( $active ? __( 'فعال', 'music-wave-core' ) : __( 'غیرفعال', 'music-wave-core' ) ) . '</span></div>';
				echo '<p>' . esc_html( isset( $card['description'] ) ? (string) $card['description'] : '' ) . '</p>';
				if ( ! empty( $card['url'] ) ) {
					echo '<a class="button button-secondary" href="' . esc_url( (string) $card['url'] ) . '">' . esc_html( isset( $card['action'] ) ? (string) $card['action'] : __( 'مدیریت کنید', 'music-wave-core' ) ) . '</a>';
				}
				echo '</section>';
			}
		}
		echo '</div>';

		$this->render_metadata_provider_settings();

		/**
		 * Render settings supplied by active MusicWave integration plugins.
		 */
		do_action( 'music_wave_admin_integration_settings' );
	}

	/**
	 * Render optional music metadata provider API keys. Leave blank to use the
	 * built-in keyless MusicBrainz + Cover Art Archive lookup.
	 *
	 * @return void
	 */
	private function render_metadata_provider_settings(): void {
		$settings = Settings::all();
		$this->settings_form_start();
		echo '<section class="mw-settings__panel"><h2>' . esc_html__( 'تکمیل خودکار فرادادهٔ موسیقی', 'music-wave-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'ویرایشگران انتشار می‌توانند عنوان، توضیح رسمی، هنرمند، آلبوم، سال، سبک‌ها، حال‌وهواها، برچسب‌ها و تصویر جلد را خودکار تکمیل کنند. جست‌وجو با قطعه‌ها، آلبوم‌ها، فهرست‌های پخش و پادکست‌ها سازگار می‌شود. کلیدی لازم نیست: جست‌وجوی داخلی MusicBrainz و Cover Art Archive بدون تنظیمات اضافی کار می‌کند. اطلاعات اختیاری Spotify یا Discogs جزئیات بیشتری از کاتالوگ می‌دهد و محدودیت نرخ به‌صورت خودکار مدیریت می‌شود.', 'music-wave-core' ) . '</p><table class="form-table" role="presentation">';
		$this->text_row( 'spotify_client_id', __( 'شناسهٔ کلاینت Spotify', 'music-wave-core' ), (string) $settings['spotify_client_id'] );
		$this->text_row( 'spotify_client_secret', __( 'راز کلاینت Spotify', 'music-wave-core' ), (string) $settings['spotify_client_secret'] );
		$this->text_row( 'discogs_token', __( 'توکن دسترسی شخصی Discogs', 'music-wave-core' ), (string) $settings['discogs_token'] );
		echo '</table></section>';
		submit_button( __( 'ذخیره کلیدهای ارائه‌دهنده فراداده', 'music-wave-core' ) );
		echo '</form>';
	}

	private function render_management_links(): void {
		$links = array(
			array( __( 'ویرایشگر سایت', 'music-wave-core' ), __( 'قالب‌ها، قطعات قالب، سربرگ، پابرگ و سبک‌های جهانی را ویرایش کنید.', 'music-wave-core' ), admin_url( 'site-editor.php' ), 'dashicons-edit-site' ),
			array( __( 'ابزارک‌ها', 'music-wave-core' ), __( 'هنگامی‌که قالب یا افزونهٔ فعال آن‌ها را ثبت می‌کند، ناحیه‌های ابزارک را مدیریت کنید.', 'music-wave-core' ), admin_url( 'widgets.php' ), 'dashicons-screenoptions' ),
			array( __( 'ناوبری', 'music-wave-core' ), __( 'بلوک‌های ناوبری و منوها را مدیریت کنید.', 'music-wave-core' ), admin_url( 'site-editor.php?path=%2Fnavigation' ), 'dashicons-menu' ),
			array( __( 'کتابخانهٔ رسانه', 'music-wave-core' ), __( 'آثار هنری روی جلد و رسانه پیش‌نمایش عمومی را مدیریت کنید.', 'music-wave-core' ), admin_url( 'upload.php' ), 'dashicons-format-audio' ),
			array( __( 'هنرمندان', 'music-wave-core' ), __( 'بیوگرافی‌ها، تصاویر و URLهای متعارف هنرمند را ویرایش کنید.', 'music-wave-core' ), admin_url( 'edit-tags.php?taxonomy=mw_artist&post_type=' . ReleasePostType::KEY ), 'dashicons-groups' ),
			array( __( 'انواع انتشار', 'music-wave-core' ), __( 'طبقه‌بندی‌های مورد استفاده برای قطعه‌ها، آلبوم‌ها و پادکست‌ها را مدیریت کنید.', 'music-wave-core' ), admin_url( 'edit-tags.php?taxonomy=mw_release_type&post_type=' . ReleasePostType::KEY ), 'dashicons-category' ),
			array( __( 'پیوندهای ثابت', 'music-wave-core' ), __( 'ساختار URL کاتالوگ و طبقه‌بندی‌های متعارف را پیکربندی کنید.', 'music-wave-core' ), admin_url( 'options-permalink.php' ), 'dashicons-admin-links' ),
			array( __( 'کاربران', 'music-wave-core' ), __( 'مدیران، ویراستاران، مشتریان و نقش‌های عضویت را مدیریت کنید.', 'music-wave-core' ), admin_url( 'users.php' ), 'dashicons-admin-users' ),
		);
		echo '<div class="mw-settings__grid mw-settings__grid--links">';
		foreach ( $links as $link ) {
			echo '<a class="mw-management-link" href="' . esc_url( $link[2] ) . '"><span class="dashicons ' . esc_attr( $link[3] ) . '" aria-hidden="true"></span><span><strong>' . esc_html( $link[0] ) . '</strong><small>' . esc_html( $link[1] ) . '</small></span></a>';
		}
		echo '</div>';
	}

	/**
	 * Register contextual help tabs for the settings screen.
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
				'id'      => 'music-wave-settings-guide',
				'title'   => __( 'تنظیمات MusicWave', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'مرکز کنترل پیکربندی MusicWave را بر اساس موضوع گروه‌بندی می‌کند:', 'music-wave-core' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'محتوا و نمایش - پیش‌فرض‌های انتشار جدید، رفتار بایگانی، لغزنده، پخش، و داده‌های ساختاریافته.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'دسترسی - رفتار بازگشتی عضویت، پیام‌های مشتری و ابزارهای دسترسی انبوه.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'تحویل و حریم خصوصی — محدودیت سرعت دانلود و جست‌وجو، سهمیه‌های تحویل، حافظهٔ نهان و نگه‌داری سابقهٔ شنیداری.', 'music-wave-core' ) . '</li>' .
					'<li>' . esc_html__( 'ادغام - WooCommerce، MusicWave VIP، تم بلوک، و کلیدهای ارائه‌دهنده فراداده.', 'music-wave-core' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html__( 'هر برگه به‌طور مستقل ذخیره می‌شود. تنظیمات برگه‌های دیگر نیز حفظ می‌شوند.', 'music-wave-core' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-settings-diagnostics',
				'title'   => __( 'راهنما و تشخیص', 'music-wave-core' ),
				'content' =>
					'<p>' . esc_html__( 'اگر چیزی مطابق انتظار عمل نکرد، با بررسی محیط و راهنمای شروع سریع شروع کنید، سپس صفحه سلامت سایت WordPress را مرور کنید.', 'music-wave-core' ) . '</p>' .
					'<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . ReleasePostType::KEY . '&page=music-wave-setup' ) ) . '">' . esc_html__( 'راه‌اندازی و عیب‌یابی را باز کنید', 'music-wave-core' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'باز کردن سلامت سایت', 'music-wave-core' ) . '</a></p>',
			)
		);
	}

	private function settings_form_start(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'music_wave_core' );
	}

	/** @param array<string, string> $options */
	private function select_row( string $key, string $label, string $value, array $options, string $description ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><select id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $options as $option => $option_label ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $value, $option, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html( $description ) . '</p></td></tr>';
	}

	private function number_row( string $key, string $label, int $value, int $min, int $max, string $description ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="number" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) $value ) . '"><p class="description">' . esc_html( $description ) . '</p></td></tr>';
	}

	private function text_row( string $key, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="text" value="' . esc_attr( $value ) . '"></td></tr>';
	}

	private function url_row( string $key, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="mw-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text code" id="mw-' . esc_attr( $key ) . '" name="' . esc_attr( Settings::OPTION ) . '[' . esc_attr( $key ) . ']" type="url" value="' . esc_attr( $value ) . '" placeholder="https://"></td></tr>';
	}

	/** @param array<string, string> $options */
	private function plain_select( string $name, string $label, array $options, string $selected_value ): void {
		echo '<label><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected_value, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></label>';
	}

	private function stat( string $label, string $value, string $icon ): void {
		echo '<section class="mw-stat"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span><div><strong>' . esc_html( $value ) . '</strong><span>' . esc_html( $label ) . '</span></div></section>';
	}

	private function status_row( string $label, bool $active, string $description ): void {
		echo '<div class="mw-status-row"><span class="mw-status mw-status--' . esc_attr( $active ? 'active' : 'inactive' ) . '">' . esc_html( $active ? __( 'آماده', 'music-wave-core' ) : __( 'در دسترس نیست', 'music-wave-core' ) ) . '</span><div><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $description ) . '</small></div></div>';
	}

	private function action_link( string $url, string $label, string $description ): void {
		echo '<a class="mw-action-link" href="' . esc_url( $url ) . '"><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $description ) . '</small><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>';
	}

	private function job_form( string $action, string $label, string $class_name ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><button class="' . esc_attr( $class_name ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * @return array<string, string>
	 */
	private function access_labels(): array {
		return array(
			'public'                 => __( 'عمومی', 'music-wave-core' ),
			'purchase'               => __( 'خرید الزامی است', 'music-wave-core' ),
			'membership'             => __( 'عضویت الزامی است', 'music-wave-core' ),
			'purchase_or_membership' => __( 'خرید یا عضویت', 'music-wave-core' ),
			'restricted'             => __( 'محدود / در دسترس نیست', 'music-wave-core' ),
		);
	}
}
