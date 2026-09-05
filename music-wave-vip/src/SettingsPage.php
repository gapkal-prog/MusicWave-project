<?php
/**
 * MusicWave VIP settings – professional admin UI with integrated WooCommerce workflow.
 *
 * @package ManaCore\MusicWave\Vip
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Vip;

final class SettingsPage {
	public const PAGE_SLUG = 'music-wave-vip';

	/**
	 * Canonical admin URL of the VIP settings screen.
	 *
	 * The page lives inside the MusicWave releases menu so every MusicWave
	 * admin screen sits in one place.
	 */
	public static function page_url(): string {
		return admin_url( 'edit.php?post_type=mw_release&page=' . self::PAGE_SLUG );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=mw_release',
			__( 'تنظیمات MusicWave VIP', 'music-wave-vip' ),
			__( 'MusicWave VIP', 'music-wave-vip' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'mw_release_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		// Inline styles are printed in render() to avoid external asset coupling.
	}

	public function register(): void {
		register_setting(
			'music_wave_vip',
			VipSettings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
				'default'           => VipSettings::defaults(),
			)
		);
		add_settings_section( 'music_wave_vip_delivery', __( 'تحویل حفاظت‌شده', 'music-wave-vip' ), array( $this, 'delivery_section' ), 'music-wave-vip' );
		$this->field( 'delivery_provider', __( 'ارائه‌دهندهٔ تحویل', 'music-wave-vip' ), 'delivery_provider_field' );
		$this->field( 'protected_root', __( 'پوشهٔ فایل‌های حفاظت‌شده', 'music-wave-vip' ), 'root_field' );
		$this->field( 'remote_base_url', __( 'نشانی پایهٔ میزبان راه‌دور', 'music-wave-vip' ), 'remote_base_url_field' );
		$this->field( 'remote_path_prefix', __( 'پیشوند مسیر راه‌دور', 'music-wave-vip' ), 'remote_path_prefix_field' );
		$this->field( 'remote_signing_secret', __( 'راز امضای راه‌دور', 'music-wave-vip' ), 'remote_signing_secret_field' );
		$this->field( 'remote_signature_param', __( 'پارامتر query امضا', 'music-wave-vip' ), 'remote_signature_param_field' );
		$this->field( 'remote_expires_param', __( 'پارامتر query انقضا', 'music-wave-vip' ), 'remote_expires_param_field' );
		$this->field( 'remote_ttl', __( 'طول عمر نشانی راه‌دور', 'music-wave-vip' ), 'remote_ttl_field' );
		$this->field( 'remote_allowed_hosts', __( 'میزبان‌های مجاز اضافی برای تغییرمسیر', 'music-wave-vip' ), 'remote_allowed_hosts_field' );
		$this->field( 'remote_key_id', __( 'شناسهٔ کلید امضا', 'music-wave-vip' ), 'remote_key_id_field' );
		$this->field( 'sendfile_mode', __( 'شتاب‌دهی سرور', 'music-wave-vip' ), 'sendfile_mode_field' );
		$this->field( 'xaccel_prefix', __( 'پیشوند داخلی X-Accel', 'music-wave-vip' ), 'xaccel_prefix_field' );

		add_settings_section( 'music_wave_vip_membership', __( 'آداپترهای عضویت و مجوز دسترسی', 'music-wave-vip' ), array( $this, 'membership_section' ), 'music-wave-vip' );
		$this->field( 'membership_sources', __( 'منابع عضویت', 'music-wave-vip' ), 'membership_sources_field', 'music_wave_vip_membership' );
		$this->field( 'plan_rows', __( 'محصولات طرح VIP', 'music-wave-vip' ), 'plan_rows_field', 'music_wave_vip_membership' );
		$this->field( 'promote_vip_role', __( 'ارتقای نقش VIP', 'music-wave-vip' ), 'promote_vip_role_field', 'music_wave_vip_membership' );
	}

	private function field( string $id, string $label, string $callback, string $section = 'music_wave_vip_delivery' ): void {
		add_settings_field( $id, $label, array( $this, $callback ), 'music-wave-vip', $section );
	}

	/** @param mixed $value @return array<string, mixed> */
	public static function sanitize_settings( $value ): array {
		$value = is_array( $value ) ? $value : array();
		// The full settings page marks its submission with a hidden field so
		// checkbox semantics (absent field = unchecked = disabled) apply only
		// to full-form submissions — otherwise a partial save would silently
		// flip the master switch and role promotion off.
		$full_form = ! empty( $value['mwvip_full_form'] );
		unset( $value['mwvip_full_form'] );
		if ( $full_form ) {
			// The master switch is the only control that stays active while the
			// module is off, so an unchecked submission always means "disabled".
			$module_was_enabled      = 'disabled' !== (string) VipSettings::all()['module_enabled'];
			$module_is_enabled       = isset( $value['module_enabled'] );
			$value['module_enabled'] = $module_is_enabled ? 'enabled' : 'disabled';
			if ( $module_was_enabled && $module_is_enabled ) {
				// Membership controls were editable and submitted: an absent
				// checkbox means the admin unchecked it.
				$value['membership_sources'] = isset( $value['membership_sources'] ) && is_array( $value['membership_sources'] ) ? $value['membership_sources'] : array();
				$value['promote_vip_role']   = isset( $value['promote_vip_role'] ) ? $value['promote_vip_role'] : 'disabled';
			}
			// While the module is switched off the membership and access
			// controls are disabled in the UI and not submitted; the merge in
			// VipSettings::sanitize() then preserves the stored configuration
			// so enforcement resumes unchanged when it is switched back on.
		}
		$settings = VipSettings::sanitize( $value );
		$root     = (string) $settings['protected_root'];
		if ( '' !== $root ) {
			$resolved      = realpath( $root );
			$public_root   = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;
			$inside_public = false !== $public_root && 0 === stripos( $resolved ? $resolved . DIRECTORY_SEPARATOR : '', rtrim( $public_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR );
			if ( false === $resolved || ! is_dir( $resolved ) || ! is_readable( $resolved ) || $inside_public ) {
				add_settings_error( VipSettings::OPTION, 'invalid_root', __( 'پوشهٔ حفاظت‌شده باید وجود داشته باشد، خواندنی باشد و خارج از پوشهٔ عمومی وردپرس بماند. مقدار قبلی حفظ شد.', 'music-wave-vip' ) );
				$settings['protected_root'] = VipSettings::all()['protected_root'];
			} else {
				$settings['protected_root'] = $resolved;
			}
		}
		if ( 'remote_redirect' === $settings['delivery_provider'] && ( '' === $settings['remote_base_url'] || '' === $settings['remote_signing_secret'] ) ) {
			add_settings_error( VipSettings::OPTION, 'remote_incomplete', __( 'حالت تغییرمسیر راه‌دور هم به نشانی پایهٔ HTTPS و هم به راز امضا نیاز دارد. تا پیکربندی هر دو، تحویل با رد امن پیش‌فرض باقی می‌ماند.', 'music-wave-vip' ), 'warning' );
		}
		if ( '' !== (string) $settings['remote_signing_secret'] && strlen( (string) $settings['remote_signing_secret'] ) < 32 ) {
			add_settings_error( VipSettings::OPTION, 'weak_secret', __( 'راز امضای راه‌دور کوتاه‌تر از ۳۲ نویسه است. تا ذخیرهٔ راز قوی‌تر، نشانی امضاشده صادر نمی‌شود.', 'music-wave-vip' ), 'warning' );
		}

		return $settings;
	}

	// ------------------------------------------------------------------
	// Helpers: tooltip, example box, WooCommerce integration
	// ------------------------------------------------------------------
	private function tooltip( string $text ): string {
		return ' <span class="mwvip-tooltip" title="' . esc_attr( $text ) . '" aria-label="' . esc_attr( $text ) . '"><span class="dashicons dashicons-editor-help" style="font-size:16px;vertical-align:middle;color:#646970;"></span></span>';
	}

	private function example_box( string $title, string $code, string $note = '' ): string {
		$html  = '<div class="mwvip-example"><strong>' . esc_html( $title ) . '</strong>';
		$html .= '<code style="display:block;background:#f6f7f7;padding:6px 8px;border-radius:4px;margin:6px 0;font-size:12px;white-space:pre-wrap;">' . esc_html( $code ) . '</code>';
		if ( '' !== $note ) {
			$html .= '<em style="font-size:12px;color:#50575e;">' . esc_html( $note ) . '</em>';
		}
		$html .= '</div>';
		return $html;
	}

	private function wc_products_url(): string {
		return admin_url( 'edit.php?post_type=product' );
	}

	private function wc_add_product_url(): string {
		return admin_url( 'post-new.php?post_type=product' );
	}

	private function render_product_reference(): string {
		$html  = '<div class="mwvip-product-ref">';
		$html .= '<p><a href="' . esc_url( $this->wc_products_url() ) . '" class="button" target="_blank" rel="noopener">' . esc_html__( 'باز کردن ووکامرس → محصولات', 'music-wave-vip' ) . ' <span class="dashicons dashicons-external" style="font-size:16px;vertical-align:middle;"></span></a> ';
		$html .= '<a href="' . esc_url( $this->wc_add_product_url() ) . '" class="button button-primary" target="_blank" rel="noopener" style="margin-left:6px;">' . esc_html__( '+ ایجاد محصول طرح جدید', 'music-wave-vip' ) . '</a></p>';
		$html .= '<p class="description">' . esc_html__( 'نکته: یک محصول عادی ووکامرس بسازید (محصول ساده، قیمت و غیره). شناسهٔ آن را از فهرست محصولات یادداشت کنید (روی آن بروید → ID). سپس شناسه را پایین وارد کنید.', 'music-wave-vip' ) . '</p>';

		// Lightweight product table if WooCommerce is active.
		if ( function_exists( 'wc_get_products' ) || post_type_exists( 'product' ) ) {
			$products = get_posts(
				array(
					'post_type'      => 'product',
					'posts_per_page' => 8,
					'post_status'    => array( 'publish', 'draft' ),
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( ! empty( $products ) ) {
				$html .= '<table class="widefat striped" style="margin-top:10px;max-width:780px;"><thead><tr><th>' . esc_html__( 'شناسه', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'محصول', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'وضعیت', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'عملیات', 'music-wave-vip' ) . '</th></tr></thead><tbody>';
				foreach ( $products as $p ) {
					$edit  = get_edit_post_link( $p->ID );
					$html .= '<tr><td><code>' . esc_html( (string) $p->ID ) . '</code></td><td>' . esc_html( get_the_title( $p->ID ) ) . '</td><td>' . esc_html( get_post_status( $p->ID ) ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'ویرایش', 'music-wave-vip' ) . '</a></td></tr>';
				}
				$html .= '</tbody></table>';
				$html .= '<p class="description">' . esc_html__( 'شناسه را در فیلد طرح پایین وارد کنید. اگر یک سطح چند محصول می‌پذیرد، شناسه‌ها را با کاما جدا کنید.', 'music-wave-vip' ) . '</p>';
			} else {
				$html .= '<p class="description">' . esc_html__( 'محصولی پیدا نشد. برای شروع، نخستین محصول طرح VIP خود را ایجاد کنید.', 'music-wave-vip' ) . '</p>';
			}
		} else {
			$html .= '<div class="notice notice-warning inline" style="margin:10px 0;"><p>' . esc_html__( 'ووکامرس فعال نیست. برای مدیریت محصولات طرح VIP آن را فعال کنید. پس از فعال‌شدن ووکامرس، شناسه‌های محصول در دسترس خواهند بود.', 'music-wave-vip' ) . '</p></div>';
		}
		$html .= '</div>';
		return $html;
	}

	public function delivery_section(): void {
		echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #2271b1;padding:12px 16px;margin-bottom:16px;">';
		echo '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'فایل‌های حفاظت‌شده کجا قرار می‌گیرند و چگونه تحویل داده می‌شوند.', 'music-wave-vip' ) . '</strong></p>';
		echo '<p style="margin:0;color:#50575e;">' . esc_html__( 'حالت محلی فایل‌ها را خارج از ریشهٔ عمومی وب نگه می‌دارد و با PHP یا شتاب‌دهی سرور (X-Sendfile / X-Accel) پخش می‌کند. حالت تغییرمسیر راه‌دور، نشانی‌های کوتاه‌عمر HTTPS با HMAC برای S3، R2، CDN یا میزبان امضاشدهٔ خودتان می‌سازد.', 'music-wave-vip' ) . '</p>';
		echo '<p style="margin:8px 0 0;"><span class="dashicons dashicons-lightbulb" style="color:#d63638;"></span> <strong>' . esc_html__( 'راهنمای شروع:', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'با ذخیره‌سازی محلی حفاظت‌شده شروع کنید. فقط وقتی از تغییرمسیر راه‌دور استفاده کنید که میزبان شما پارامترهای expires، signature و mode زیر را بپذیرد یا توسعه‌دهنده فیلتر music_wave_vip_remote_download_url را متصل کرده باشد.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function general_section(): void {
		$enabled = 'enabled' === (string) VipSettings::all()['module_enabled'];
		if ( $enabled ) {
			echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #00a32a;padding:12px 16px;margin-bottom:16px;">';
			echo '<p style="margin:0;"><strong>' . esc_html__( 'اعمال عضویت VIP روشن است.', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'انتشارهای مشروط به عضویت به سطح عضویت معتبر نیاز دارند، اعطای طرح‌ها خودکار انجام می‌شود و دانلودهای حفاظت‌شده از قوانین دسترسی زیر پیروی می‌کنند.', 'music-wave-vip' ) . '</p>';
			echo '</div>';
			return;
		}
		echo '<div class="notice notice-warning inline" style="margin:0 0 16px;"><p><span class="dashicons dashicons-unlock" style="color:#d63638;"></span> <strong>' . esc_html__( 'اعمال عضویت VIP خاموش است — همهٔ انتشارهای مشروط به عضویت فعلاً برای همه رایگان‌اند (حتی مهمان‌ها).', 'music-wave-vip' ) . '</strong></p><p style="margin:6px 0 0;">' . esc_html__( 'وقتی کلید خاموش است، خرید طرح اعطا یا لغو دسترسی را متوقف می‌کند؛ اما مجوزهای ذخیره‌شده حفظ می‌شوند و با روشن‌کردن دوبارهٔ کلید همه‌چیز از سر گرفته می‌شود. تحویل امن فایل ادامه دارد؛ خریدهای ووکامرس برای هر انتشار هرگز تحت تأثیر این کلید نیستند.', 'music-wave-vip' ) . '</p></div>';
	}

	public function module_enabled_field(): void {
		$value = (string) VipSettings::all()['module_enabled'];
		echo '<label style="display:inline-flex;align-items:flex-start;gap:10px;padding:14px;border:2px solid ' . ( 'enabled' === $value ? '#00a32a' : '#d63638' ) . ';border-radius:6px;background:' . ( 'enabled' === $value ? '#f0f9f1' : '#fcf0f1' ) . ';max-width:760px;box-sizing:border-box;"><input type="checkbox" id="mwvip-module-enabled" name="' . esc_attr( VipSettings::OPTION ) . '[module_enabled]" value="enabled" ' . checked( $value, 'enabled', false ) . ' style="margin-top:4px;"> <span><strong style="font-size:14px;">' . esc_html__( 'فعال‌سازی اعمال عضویت VIP (کلید اصلی)', 'music-wave-vip' ) . '</strong><br><span class="description">' . esc_html__( 'روشن: سطح‌های عضویت، خرید طرح و قوانین انقضا اعمال می‌شوند. خاموش: همهٔ محتوای مشروط به عضویت برای همه رایگان می‌شود و تحویل امن ادامه پیدا می‌کند — درست مانند برداشتن paywall برای یک تبلیغ. خریدهای ووکامرس برای هر انتشار هرگز تحت تأثیر این کلید نیستند.', 'music-wave-vip' ) . '</span></span></label>';
	}

	/**
	 * Whether the membership and access controls render disabled.
	 *
	 * While enforcement is switched off those settings have no effect, so the
	 * UI keeps them visible but blocks edits — the stored configuration
	 * survives and resumes unchanged when enforcement is switched back on.
	 * Delivery settings stay editable because protected delivery keeps
	 * running regardless of the master switch.
	 */
	private function membership_controls_disabled(): bool {
		return 'disabled' === (string) VipSettings::all()['module_enabled'];
	}

	public function delivery_access_field(): void {
		$value    = (string) VipSettings::all()['delivery_access'];
		$disabled = $this->membership_controls_disabled();
		foreach ( array(
			'logged_in' => array(
				__( 'فقط کاربران ثبت‌نام‌شده', 'music-wave-vip' ),
				__( 'بازدیدکنندگان برای پخش یا دانلود صدای حفاظت‌شده باید ثبت‌نام و وارد شوند (پیشنهادشده).', 'music-wave-vip' ),
			),
			'everyone'  => array(
				__( 'همه، حتی مهمان‌ها', 'music-wave-vip' ),
				__( 'حالت تحویل امن: ثبت‌نام اختیاری است. هر بازدیدکننده می‌تواند پخش و دانلود کند، اما پیوندها امضاشده، کوتاه‌عمر و دارای محدودیت نرخ باقی می‌مانند. آن را با یک میزبان راه‌دور امضاشده ترکیب کنید تا بدون فروش عضویت در این سایت، ساختار «فقط میزبان دانلود» داشته باشید.', 'music-wave-vip' ),
			),
		) as $key => $info ) {
			echo '<label style="display:flex;align-items:flex-start;gap:8px;margin:0.6em 0;padding:10px;border:1px solid ' . ( $key === $value ? '#2271b1' : '#ccd0d4' ) . ';border-radius:4px;background:' . ( $key === $value ? '#f0f6fc' : '#fff' ) . ';max-width:760px;"><input type="radio" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[delivery_access]" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . disabled( $disabled, true, false ) . ' style="margin-top:3px;"> <span><strong>' . esc_html( $info[0] ) . '</strong><br><span class="description" style="font-size:12px;">' . esc_html( $info[1] ) . '</span></span></label>';
		}
	}

	/**
	 * Plans management table plus the one-click default-plan button.
	 */
	private function render_plans_manager(): void {
		$config = VipSettings::all();
		$plans  = isset( $config['vip_plans'] ) && is_array( $config['vip_plans'] ) ? $config['vip_plans'] : array();
		$woo    = post_type_exists( 'product' );

		echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:860px;margin-bottom:16px;">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'مدیریت طرح', 'music-wave-vip' ) . '</h3>';
		if ( $woo ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . PlanProducts::ACTION_CREATE_DEFAULTS ), PlanProducts::ACTION_CREATE_DEFAULTS );
			echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary"><span class="dashicons dashicons-superhero" style="vertical-align:middle;font-size:16px;"></span> ' . esc_html__( 'ایجاد طرح‌های پیش‌فرض (۱، ۶ و ۱۲ ماهه)', 'music-wave-vip' ) . '</a> <span class="description" style="margin-left:8px;">' . esc_html__( 'سه محصول مجازی ووکامرس را ایجاد و به‌عنوان طرح VIP نگاشت می‌کند. فشردن چندبارهٔ آن بی‌خطر است؛ نگاشت‌های موجود حفظ می‌شوند. بعداً قیمت هر محصول را در ووکامرس تعیین کنید.', 'music-wave-vip' ) . '</span></p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'برای ایجاد و فروش محصولات طرح، ووکامرس را فعال کنید.', 'music-wave-vip' ) . '</p></div>';
		}

		if ( empty( $plans ) ) {
			echo '<p class="description" style="color:#d63638;">' . esc_html__( 'هنوز نگاشتی برای طرح وجود ندارد. از دکمهٔ بالا استفاده کنید یا در کادر نگاشت پایین خط اضافه کنید.', 'music-wave-vip' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:860px;"><thead><tr><th>' . esc_html__( 'سطح', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'محصول', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'مدت', 'music-wave-vip' ) . '</th><th>' . esc_html__( 'عملیات', 'music-wave-vip' ) . '</th></tr></thead><tbody>';
			foreach ( $plans as $plan ) {
				if ( ! is_array( $plan ) || empty( $plan['level'] ) ) {
					continue;
				}
				$level    = sanitize_key( (string) $plan['level'] );
				$days     = isset( $plan['duration_days'] ) ? absint( $plan['duration_days'] ) : 0;
				$ids      = isset( $plan['product_ids'] ) && is_array( $plan['product_ids'] ) ? array_map( 'absint', $plan['product_ids'] ) : array();
				$products = array();
				foreach ( $ids as $id ) {
					$edit       = get_edit_post_link( $id );
					$products[] = $edit ? '<a href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">#' . esc_html( (string) $id ) . ' ' . esc_html( get_the_title( $id ) ) . '</a>' : '#' . esc_html( (string) $id );
				}
				$remove = wp_nonce_url(
					admin_url( 'admin-post.php?action=' . PlanProducts::ACTION_REMOVE_PLAN . '&level=' . rawurlencode( $level ) ),
					PlanProducts::ACTION_REMOVE_PLAN
				);
				echo '<tr><td><code>' . esc_html( $level ) . '</code></td><td>' . implode( ', ', $products ) . '</td><td>' . esc_html( PlanProducts::duration_label( $days ) ) . '</td><td>';
				if ( $woo ) {
					// A link, not a nested form: this table renders inside the main
					// settings form and a nested <form> would break its nonce.
					echo '<a class="button button-small" href="' . esc_url( $remove ) . '" onclick="return confirm(\'' . esc_js( __( 'این نگاشت طرح حذف شود؟ خود محصول ووکامرس حذف نمی‌شود.', 'music-wave-vip' ) ) . '\');">' . esc_html__( 'حذف نگاشت', 'music-wave-vip' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'حذف نگاشت، اعطای بعدی آن سطح را متوقف می‌کند؛ کاربرانی که قبلاً مجوز گرفته‌اند تا انقضا یا لغو آن دسترسی را حفظ می‌کنند. برای تغییر قیمت، محصول را مستقیماً در ووکامرس ویرایش کنید.', 'music-wave-vip' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * One-shot notice after a plan admin action redirected back.
	 */
	private function render_plan_action_notice(): void {
		$notice = get_transient( 'mwvip_plan_action_notice' );
		if ( ! is_string( $notice ) || '' === $notice ) {
			return;
		}
		delete_transient( 'mwvip_plan_action_notice' );
		$messages = array(
			'mwvip_defaults_created' => __( 'محصولات طرح پیش‌فرض ایجاد شدند. قیمت آن‌ها را در ووکامرس → محصولات تعیین کنید.', 'music-wave-vip' ),
			'mwvip_defaults_skipped' => __( 'طرح‌های پیش‌فرض از قبل پیکربندی‌شده‌اند؛ مورد جدیدی برای ایجاد نیست.', 'music-wave-vip' ),
			'mwvip_defaults_failed'  => __( 'ایجاد محصولات طرح پیش‌فرض ناموفق بود. آیا ووکامرس فعال است؟', 'music-wave-vip' ),
			'mwvip_plan_removed'     => __( 'نگاشت طرح حذف شد. خود محصول حذف نشده است.', 'music-wave-vip' ),
		);
		$class    = 'mwvip_defaults_failed' === $notice ? 'notice-error' : 'notice-success';
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	public function membership_section(): void {
		echo '<div class="mwvip-section-intro" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #00a32a;padding:12px 16px;margin-bottom:16px;">';
		echo '<p style="margin:0 0 8px;">' . esc_html__( '«سیاست دسترسی» تنها مرجع معتبر باقی می‌ماند. یک یا چند منبع عضویت را انتخاب کنید؛ کاربر برای دسترسی به انتشارهای محدودشده باید دست‌کم یک سطح منطبق داشته باشد. سطح‌ها نامک‌های ساده‌ای هستند که برای هر انتشار تعریف می‌کنید (مثلاً gold یا vipgold).', 'music-wave-vip' ) . ' ' . esc_html__( 'هرگز ایمیل، گذرواژه، نشانی مستقیم فایل یا token خصوصی API را در سطح‌های عضویت انتشار وارد نکنید.', 'music-wave-vip' ) . '</p>';
		echo '<p style="margin:8px 0 0;background:#f6f7f7;padding:8px;border-radius:4px;"><strong>' . esc_html__( 'طرح‌های ووکامرس:', 'music-wave-vip' ) . '</strong> ' . esc_html__( 'یک محصول عادی ووکامرس را به‌عنوان طرح VIP بسازید، آن را پایین فهرست کنید و انتشارهای حفاظت‌شده را روی حالت «عضویت» با همان کلید سطح تنظیم کنید. با خرید طرح، آن سطح و نقش VIP فوراً به حساب مشتری افزوده می‌شود؛ بازپرداخت یا لغو سفارش آن را بلافاصله حذف می‌کند. طرح‌های زمان‌دار (با :days) خودکار منقضی می‌شوند و کاربر را به سطح استاندارد برمی‌گردانند.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function plan_rows_field(): void {
		$value    = (string) VipSettings::all()['plan_rows'];
		$disabled = $this->membership_controls_disabled();
		$plans    = VipPlans::from_settings()->active_levels( 0 ); // Dummy to ensure class loaded.
		$all      = VipSettings::all();
		$parsed   = isset( $all['vip_plans'] ) && is_array( $all['vip_plans'] ) ? $all['vip_plans'] : array();
		$count    = count( $parsed );
		echo $this->render_product_reference();
		echo '<div style="margin-top:16px;background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:780px;">';
		echo '<label for="mwvip-plan-rows" style="font-weight:600;">' . esc_html__( 'نگاشت طرح VIP', 'music-wave-vip' ) . $this->tooltip( __( 'در هر خط یک طرح. قالب برای جلوگیری از پیکربندی نادرست سخت‌گیرانه است.', 'music-wave-vip' ) ) . '</label>';
		echo '<textarea id="mwvip-plan-rows" class="large-text code mwvip-module-control" rows="5" placeholder="vipgold:123&#10;silver:45,46:365&#10;bronze:78:30" name="' . esc_attr( VipSettings::OPTION ) . '[plan_rows]" spellcheck="false" ' . disabled( $disabled, true, false ) . ' style="font-family:Consolas,Monaco,monospace;margin-top:8px;">' . esc_textarea( $value ) . '</textarea>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">';
		echo '<div><strong>' . esc_html__( 'نحو', 'music-wave-vip' ) . '</strong><br><code>level:product_id[:days]</code><br><span class="description">' . esc_html__( 'level — نامک سطح عضویت (a-z، 0-9، _ و -) که با فیلد «سطح‌های عضویت» انتشار مطابقت دارد.', 'music-wave-vip' ) . '</span></div>';
		echo '<div><strong>' . esc_html__( 'نمونه‌ها', 'music-wave-vip' ) . '</strong><br><code>vipgold:123</code> — ' . esc_html__( 'مادام‌العمر با محصول ۱۲۳', 'music-wave-vip' ) . '<br><code>silver:45,46:365</code> — ' . esc_html__( 'یک‌ساله با محصول ۴۵ یا ۴۶', 'music-wave-vip' ) . '</div>';
		echo '</div>';
		echo $this->example_box( __( 'نمونهٔ نگاشت کامل', 'music-wave-vip' ), "vipgold:101\nsilver:102,103:365\nbronze:104:30  # 30-day trial\n# lines starting with # are comments", __( 'شناسه‌های محصول، شناسهٔ محصولات ووکامرس هستند. مدت به‌روز است (۱ تا ۳۶۵۰)؛ برای مادام‌العمر خالی بگذارید.', 'music-wave-vip' ) );
		if ( $count > 0 ) {
			/* translators: %d: number of configured VIP plans. */
			echo '<p class="description" style="margin-top:8px;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> ' . sprintf( esc_html__( 'در حال حاضر %d طرح فعال پیکربندی‌شده است.', 'music-wave-vip' ), $count ) . '</p>';
		} else {
			echo '<p class="description" style="margin-top:8px;color:#d63638;"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'هنوز طرحی پیکربندی‌نشده است. دست‌کم یک خط اضافه و ذخیره کنید.', 'music-wave-vip' ) . '</p>';
		}
		echo '<p class="description"><a href="' . esc_url( $this->wc_products_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'مدیریت محصولات در ووکامرس →', 'music-wave-vip' ) . '</a> ' . esc_html__( '→ شناسهٔ عددی را کپی و اینجا وارد کنید.', 'music-wave-vip' ) . '</p>';
		echo '</div>';
	}

	public function promote_vip_role_field(): void {
		$value    = (string) VipSettings::all()['promote_vip_role'];
		$disabled = $this->membership_controls_disabled();
		echo '<label style="display:inline-flex;align-items:center;gap:8px;padding:10px;border:1px solid #ccd0d4;border-radius:4px;background:#fff;"><input type="checkbox" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[promote_vip_role]" value="enabled" ' . checked( $value, 'enabled', false ) . disabled( $disabled, true, false ) . '> <span><strong>' . esc_html__( 'اعطای نقش VIP MusicWave (mw_vip)', 'music-wave-vip' ) . '</strong><br><span class="description">' . esc_html__( 'این نقش فقط قابلیت خواندن دارد تا حساب‌های VIP در کاربران → همهٔ کاربران قابل شناسایی باشند. تصمیم‌های دسترسی هرگز فقط به نقش وابسته نیستند؛ اعطا و انقضا به‌صورت زنده بررسی می‌شوند. فعال بگذارید.', 'music-wave-vip' ) . '</span></span></label>';
	}

	public function delivery_provider_field(): void {
		$value = VipSettings::all()['delivery_provider'];
		echo '<select name="' . esc_attr( VipSettings::OPTION ) . '[delivery_provider]" id="mwvip-delivery-provider" style="min-width:280px;">';
		foreach ( array(
			'local'           => __( 'پوشهٔ حفاظت‌شدهٔ محلی (پیشنهادشده)', 'music-wave-vip' ),
			'remote_redirect' => __( 'میزبان HTTPS راه‌دور با تغییرمسیر HMAC', 'music-wave-vip' ),
		) as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>' . $this->tooltip( __( 'حالت محلی هرگز رازها را در معرض مرورگر نمی‌گذارد. حالت راه‌دور یک نشانی کوتاه‌عمر را امضا می‌کند (HMAC-SHA256) و تغییرمسیر می‌دهد.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'محلی = پخش جریانی با PHP یا X-Sendfile/X-Accel از خارج public_html. راه‌دور = نشانی امضاشدهٔ S3/R2/CDN.', 'music-wave-vip' ) . '</p>';
		echo $this->example_box( __( 'هرکدام را چه زمانی استفاده کنیم', 'music-wave-vip' ), "Local: small catalogs, low traffic, or when you control the server.\nRemote: high traffic / large FLAC/ZIP, or object storage + CDN.", __( 'هر دو مورد، مجوز دسترسی، انقضا و نشانی‌های امضاشده را اعمال می‌کنند.', 'music-wave-vip' ) );
	}

	public function root_field(): void {
		$settings = VipSettings::all();
		$value    = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) ? (string) constant( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' ) : (string) $settings['protected_root'];
		$default  = dirname( rtrim( ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR . 'musicwave-private';
		$locked   = defined( 'MUSIC_WAVE_VIP_PROTECTED_ROOT' );
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[protected_root]" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $default ) . '" ' . disabled( $locked, true, false ) . ' style="min-width:420px;">' . $this->tooltip( __( 'مسیر مطلق خارج از public_html/public؛ باید وجود داشته باشد، خواندنی و نوشتنی باشد و فایل بازدارندهٔ .htaccess را به‌عنوان پشتیبان داشته باشد.', 'music-wave-vip' ) );
		if ( $locked ) {
			echo '<p class="description" style="color:#d63638;"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'با ثابت MUSIC_WAVE_VIP_PROTECTED_ROOT در wp-config.php جایگزین می‌شود. برای تغییر، wp-config.php را ویرایش کنید.', 'music-wave-vip' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'برای استفاده از پوشهٔ خودکار کنار وردپرس (وقتی قطعی است که خارج از ریشهٔ وب است) خالی بگذارید.', 'music-wave-vip' ) . '<br>' . esc_html__( 'برای محیط عملیاتی اکیداً پیشنهاد می‌شود:', 'music-wave-vip' ) . ' <code>/var/private/musicwave</code> ' . esc_html__( 'یا', 'music-wave-vip' ) . ' <code>/home/user/musicwave-private</code> ' . esc_html__( '(داخل public_html نباشد).', 'music-wave-vip' ) . '</p>';
		}
		echo $this->example_box( __( 'سخت‌سازی Nginx / Apache (اطلاعات)', 'music-wave-vip' ), "# nginx: location /musicwave-protected/ { internal; alias /var/private/musicwave/; }\n# apache .htaccess in protected dir: Require all denied", __( 'امنیت اصلی با نگه‌داشتن پوشه خارج از ریشهٔ وب تأمین می‌شود؛ .htaccess/web.config فقط لایهٔ دفاعی مضاعف‌اند.', 'music-wave-vip' ) );
	}

	public function remote_base_url_field(): void {
		$value = (string) VipSettings::all()['remote_base_url'];
		echo '<input class="regular-text code" type="url" inputmode="url" placeholder="https://downloads.example.com/files" name="' . esc_attr( VipSettings::OPTION ) . '[remote_base_url]" value="' . esc_attr( $value ) . '" style="min-width:420px;">' . $this->tooltip( __( 'باید HTTPS باشد. نشانی نهایی = base / prefix / asset-id. میزبان در فهرست مجاز است.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'فقط HTTPS. نمونه:', 'music-wave-vip' ) . ' <code>https://cdn.example.com/musicwave</code> — ' . esc_html__( 'نشانی ایجادشده به‌صورت base + «/» + مسیر رمزگذاری‌شدهٔ asset خواهد بود.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_path_prefix_field(): void {
		$value = (string) VipSettings::all()['remote_path_prefix'];
		echo '<input class="regular-text code" type="text" placeholder="musicwave" name="' . esc_attr( VipSettings::OPTION ) . '[remote_path_prefix]" value="' . esc_attr( $value ) . '">' . $this->tooltip( __( 'پیشوند/پوشهٔ اختیاری bucket. فقط A-Z، 0-9، .، _، - و / نگه داشته می‌شوند و traversal حذف می‌شود.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'نمونه:', 'music-wave-vip' ) . ' <code>vip-assets</code> → <code>https://cdn.example.com/vip-assets/album/track.flac</code></p>';
	}

	public function remote_signing_secret_field(): void {
		$value = (string) VipSettings::all()['remote_signing_secret'];
		$has   = '' !== $value;
		echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
		echo '<input class="regular-text code" type="password" autocomplete="new-password" id="mwvip-remote-secret" name="' . esc_attr( VipSettings::OPTION ) . '[remote_signing_secret]" value="" placeholder="' . esc_attr( $has ? __( 'راز ذخیره‌شده — برای حفظ آن خالی بگذارید', 'music-wave-vip' ) : __( 'ایجاد با: openssl rand -hex 32', 'music-wave-vip' ) ) . '" style="min-width:360px;">';
		echo '<button type="button" class="button" id="mwvip-toggle-secret" aria-label="' . esc_attr__( 'نمایش/پنهان‌کردن راز', 'music-wave-vip' ) . '"><span class="dashicons dashicons-visibility"></span></button>';
		echo '<button type="button" class="button" id="mwvip-gen-secret">' . esc_html__( 'ایجاد', 'music-wave-vip' ) . '</button>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'دست‌کم ۳۲ نویسهٔ تصادفی؛ در سمت سرور ذخیره می‌شود و هرگز به مرورگر فرستاده نمی‌شود. با این دستور بسازید:', 'music-wave-vip' ) . ' <code>openssl rand -hex 32</code> ' . esc_html__( 'یا دکمهٔ «ایجاد».', 'music-wave-vip' ) . '</p>';
		if ( $has && strlen( $value ) < 32 ) {
			echo '<p style="color:#d63638;"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'رمز فعلی کوتاه است (کمتر از ۳۲ نویسه). تا ذخیرهٔ رمز قوی‌تر، نشانی امضاشده صادر نمی‌شود.', 'music-wave-vip' ) . '</p>';
		}
	}

	public function remote_signature_param_field(): void {
		$this->text_field( 'remote_signature_param', 'signature', __( 'کلید query که امضای base64url با HMAC-SHA256 را دریافت می‌کند.', 'music-wave-vip' ) );
		echo $this->example_box( __( 'نمونه نشانی راه‌دور', 'music-wave-vip' ), 'https://cdn.example.com/files/album/track.flac?expires=...&signature=...&mode=download&kid=k2026', __( 'HMAC همهٔ پارامترها (path، expiry، mode و kid) را پوشش می‌دهد تا امکان دستکاری هیچ‌کدام نباشد.', 'music-wave-vip' ) );
	}

	public function remote_expires_param_field(): void {
		$this->text_field( 'remote_expires_param', 'expires', __( 'کلید query که timestamp انقضای Unix را دریافت می‌کند.', 'music-wave-vip' ) );
	}

	public function remote_ttl_field(): void {
		$value = absint( VipSettings::all()['remote_ttl'] );
		echo '<input class="small-text" type="number" min="30" max="900" step="1" name="' . esc_attr( VipSettings::OPTION ) . '[remote_ttl]" value="' . esc_attr( (string) $value ) . '"> <span>' . esc_html__( 'ثانیه (۳۰ تا ۹۰۰)', 'music-wave-vip' ) . '</span>' . $this->tooltip( __( 'TTL کوتاه بازپخش را محدود می‌کند. token هسته (۵ تا ۱۵ دقیقه) و TTL نشانی راه‌دور هر دو اعمال می‌شوند؛ مجوز دسترسی در هر تحویل دوباره بررسی می‌شود.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'پیشنهادشده: ۱۲۰ تا ۳۰۰ ثانیه. کوتاه‌تر امن‌تر است، اما به همگام‌سازی دقیق ساعت نیاز دارد.', 'music-wave-vip' ) . '</p>';
	}

	public function remote_allowed_hosts_field(): void {
		$hosts = (array) VipSettings::all()['remote_allowed_hosts'];
		echo '<textarea class="regular-text code" rows="3" name="' . esc_attr( VipSettings::OPTION ) . '[remote_allowed_hosts]" placeholder="mirror.example.net&#10;cdn2.example.com" style="max-width:420px;">' . esc_textarea( implode( "\n", array_map( 'strval', $hosts ) ) ) . '</textarea>' . $this->tooltip( __( 'در هر خط یک میزبان. میزبان نشانی پایهٔ راه‌دور همیشه مجاز است؛ تغییرمسیر به هر میزبان دیگری رد می‌شود.', 'music-wave-vip' ) );
	}

	public function remote_key_id_field(): void {
		$value = (string) VipSettings::all()['remote_key_id'];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[remote_key_id]" value="' . esc_attr( $value ) . '" placeholder="k2026">' . $this->tooltip( __( 'شناسهٔ چرخش اختیاری که با kid= افزوده می‌شود — به میزبان راه‌دور اجازه می‌دهد بدون قطعی ابتدا راز جدید و سپس راز قدیمی را امتحان کند.', 'music-wave-vip' ) );
	}

	public function sendfile_mode_field(): void {
		$value = (string) VipSettings::all()['sendfile_mode'];
		$modes = array(
			'none'      => __( 'پخش جریانی با PHP (پیش‌فرض)', 'music-wave-vip' ),
			'xsendfile' => __( 'Apache/LiteSpeed X-Sendfile', 'music-wave-vip' ),
			'xaccel'    => __( 'nginx X-Accel-Redirect', 'music-wave-vip' ),
		);
		echo '<select name="' . esc_attr( VipSettings::OPTION ) . '[sendfile_mode]" id="mwvip-sendfile-mode">';
		foreach ( $modes as $mode => $label ) {
			echo '<option value="' . esc_attr( $mode ) . '" ' . selected( $value, $mode, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>' . $this->tooltip( __( 'فایل‌های بزرگ را به وب‌سرور می‌سپارد تا workerهای PHP آزاد بمانند. به تنظیمات سرور نیاز دارد.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'هیچ‌کدام = PHP فایل را می‌خواند و پخش می‌کند (ساده‌تر، اما برای FLAC/ZIP بزرگ کندتر). X-Sendfile/X-Accel را فقط وقتی انتخاب کنید که میزبان شما آن را فعال کرده باشد.', 'music-wave-vip' ) . '</p>';
	}

	public function xaccel_prefix_field(): void {
		$value = (string) VipSettings::all()['xaccel_prefix'];
		echo '<input class="regular-text code" type="text" placeholder="/musicwave-protected" name="' . esc_attr( VipSettings::OPTION ) . '[xaccel_prefix]" value="' . esc_attr( $value ) . '">' . $this->tooltip( __( 'مکان داخلی Nginx که به پوشهٔ حفاظت‌شده alias می‌شود. باید با / شروع شود.', 'music-wave-vip' ) );
		echo '<p class="description">' . esc_html__( 'نمونه قانون nginx:', 'music-wave-vip' ) . ' <code>location /musicwave-protected/ { internal; alias /var/private/musicwave/; }</code></p>';
	}

	public function membership_sources_field(): void {
		$selected = (array) VipSettings::all()['membership_sources'];
		$disabled = $this->membership_controls_disabled();
		$options  = array(
			'role'                      => array( __( 'نقش‌های وردپرس (سطح برابر با نامک نقش)', 'music-wave-vip' ), __( 'ساده: یک نقش WP را به‌عنوان سطح اختصاص دهید. نمونه: subscriber.', 'music-wave-vip' ) ),
			'filter'                    => array( __( 'فیلتر عضویت خارجی (آداپتر توسعه‌دهنده)', 'music-wave-vip' ), __( 'برای یکپارچه‌سازی سفارشی از طریق فیلتر music_wave_vip_membership_access.', 'music-wave-vip' ) ),
			'woocommerce_plans'         => array( __( 'محصولات طرح ووکامرس — پیشنهادشده', 'music-wave-vip' ), __( 'یک محصول ووکامرس را به‌عنوان طرح VIP بفروشید؛ خرید سطح را فوراً اعطا می‌کند و بازپرداخت آن را پس می‌گیرد.', 'music-wave-vip' ) ),
			'woocommerce_memberships'   => array( __( 'WooCommerce Memberships (در صورت نیاز با پیشوند plan-)', 'music-wave-vip' ), __( 'به افزونهٔ WooCommerce Memberships نیاز دارد.', 'music-wave-vip' ) ),
			'woocommerce_subscriptions' => array( __( 'WooCommerce Subscriptions (subscription-123 یا شناسهٔ محصول)', 'music-wave-vip' ), __( 'به افزونهٔ WooCommerce Subscriptions نیاز دارد.', 'music-wave-vip' ) ),
		);
		foreach ( $options as $key => $info ) {
			list( $label, $help ) = $info;
			$checked              = in_array( $key, $selected, true );
			echo '<label style="display:flex;align-items:center;gap:8px;margin:0.6em 0;padding:8px;border:1px solid ' . ( $checked ? '#00a32a' : '#ccd0d4' ) . ';border-radius:4px;background:' . ( $checked ? '#f0f6fc' : '#fff' ) . '"><input type="checkbox" class="mwvip-module-control" name="' . esc_attr( VipSettings::OPTION ) . '[membership_sources][]" value="' . esc_attr( $key ) . '" ' . checked( $checked, true, false ) . disabled( $disabled, true, false ) . '> <span><strong>' . esc_html( $label ) . '</strong><br><span class="description" style="font-size:12px;">' . esc_html( $help ) . '</span></span></label>';
		}
		echo '<p class="description">' . esc_html__( 'نکته: از سطح‌های خنثی انتشار مانند gold، vipgold یا plan-pro استفاده کنید. همین رشتهٔ سطح باید در انتشار → دسترسی → فیلد «سطح‌های عضویت» وارد شود.', 'music-wave-vip' ) . '</p>';
	}

	private function text_field( string $key, string $placeholder, string $description ): void {
		$value = (string) VipSettings::all()[ $key ];
		echo '<input class="regular-text code" type="text" name="' . esc_attr( VipSettings::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" style="min-width:260px;">' . $this->tooltip( $description ) . '<p class="description">' . esc_html( $description ) . '</p>';
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->register_help_tabs();
		$settings      = VipSettings::all();
		$remote_active = 'remote_redirect' === (string) $settings['delivery_provider'];
		$module_on     = 'enabled' === (string) $settings['module_enabled'];
		?>
		<style>
			.mwvip-header{background:#fff;border:1px solid #ccd0d4;padding:18px 20px;border-radius:6px;margin-bottom:16px;}
			.mwvip-header h1{margin:0;font-size:22px;display:flex;align-items:center;gap:10px;}
			.mwvip-badge{background:#2271b1;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;letter-spacing:.04em;}
			.mwvip-header p{margin:4px 0 0;color:#50575e;max-width:560px;}
			.mwvip-tabs{border-bottom:1px solid #ccd0d4;margin-bottom:0;display:flex;gap:0;}
			.mwvip-tabs button{background:none;border:1px solid transparent;border-bottom:none;padding:10px 16px;cursor:pointer;font-weight:600;color:#50575e;border-radius:4px 4px 0 0;}
			.mwvip-tabs button.active{background:#fff;border-color:#ccd0d4;color:#1d2327;}
			.mwvip-panel{display:none;background:#fff;border:1px solid #ccd0d4;border-top:none;padding:20px;border-radius:0 0 6px 6px;}
			.mwvip-panel.active{display:block;}
				.mwvip-example{margin-top:8px;}
				.mwvip-tooltip{cursor:help;}
				.mwvip-module-control:disabled{opacity:.65;}
				.mwvip-related{display:flex;flex-direction:column;gap:8px;}
				.mwvip-related a.button{align-self:flex-start;}
				.form-table th{vertical-align:top;padding-top:18px;width:220px;}
				.mwvip-quicklinks a.button{margin-right:6px;}
		</style>
		<div class="wrap" id="mwvip-settings">
			<div class="mwvip-header">
				<div>
					<h1><span class="dashicons dashicons-shield-alt" style="font-size:26px;color:#2271b1;"></span> <?php esc_html_e( 'MusicWave VIP', 'music-wave-vip' ); ?> <span class="mwvip-badge"><?php echo esc_html( defined( 'MUSIC_WAVE_VIP_VERSION' ) ? MUSIC_WAVE_VIP_VERSION : 'VIP' ); ?></span> <span class="mwvip-badge" style="background:<?php echo $module_on ? '#00a32a' : '#d63638'; ?>;"><?php echo esc_html( $module_on ? __( 'اعمال قوانین روشن است', 'music-wave-vip' ) : __( 'همهٔ محتوا رایگان است', 'music-wave-vip' ) ); ?></span></h1>
					<p><?php esc_html_e( 'تحویل فایل حفاظت‌شده، نشانی‌های راه‌دور امضاشده و عضویت طرح‌های ووکامرس — همه با رد امن پیش‌فرض و بررسی مجوز دسترسی.', 'music-wave-vip' ); ?></p>
				</div>
				<div class="mwvip-quicklinks">
					<a href="<?php echo esc_url( $this->wc_products_url() ); ?>" class="button" target="_blank" rel="noopener"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'محصولات ووکامرس', 'music-wave-vip' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release' ) ); ?>" class="button"><span class="dashicons dashicons-album"></span> <?php esc_html_e( 'انتشارها', 'music-wave-vip' ); ?></a>
					<a href="https://manacore.dev/musicwave/docs/vip" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'مستندات', 'music-wave-vip' ); ?> <span class="dashicons dashicons-external"></span></a>
				</div>
			</div>
			<?php
			settings_errors( VipSettings::OPTION );
			$this->render_preflight_notice();
			$this->render_plan_action_notice();
			?>
			<div class="mwvip-tabs" role="tablist">
				<button type="button" role="tab" aria-selected="true" data-tab="general" class="active"><span class="dashicons dashicons-admin-settings" style="vertical-align:middle;"></span> <?php esc_html_e( 'عمومی', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="delivery"><span class="dashicons dashicons-cloud" style="vertical-align:middle;"></span> <?php esc_html_e( 'تحویل فایل', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="membership"><span class="dashicons dashicons-groups" style="vertical-align:middle;"></span> <?php esc_html_e( 'عضویت و طرح‌ها', 'music-wave-vip' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="advanced"><span class="dashicons dashicons-admin-generic" style="vertical-align:middle;"></span> <?php esc_html_e( 'پیشرفته', 'music-wave-vip' ); ?></button>
			</div>
			<form action="options.php" method="post" id="mwvip-form">
				<?php
				settings_fields( 'music_wave_vip' );
				// Identifies full-form submissions so sanitize_settings() can
				// apply unchecked-checkbox semantics to the master switch.
				echo '<input type="hidden" name="' . esc_attr( VipSettings::OPTION ) . '[mwvip_full_form]" value="1">';
				?>
				<div class="mwvip-panel active" data-panel="general">
					<h2 style="margin-top:0;"><?php esc_html_e( 'عمومی — کلید اصلی و دسترسی', 'music-wave-vip' ); ?></h2>
					<?php $this->general_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'اعمال قوانین VIP', 'music-wave-vip' ); ?></th><td><?php $this->module_enabled_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'چه کسی می‌تواند صدای حفاظت‌شده را پخش و دانلود کند', 'music-wave-vip' ); ?></th><td><?php $this->delivery_access_field(); ?></td></tr>
					</table>
				</div>
				<div class="mwvip-panel" data-panel="delivery">
					<h2 style="margin-top:0;"><?php esc_html_e( 'تحویل حفاظت‌شده', 'music-wave-vip' ); ?></h2>
					<?php $this->delivery_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'ارائه‌دهندهٔ تحویل', 'music-wave-vip' ); ?></th><td><?php $this->delivery_provider_field(); ?></td></tr>
						<tr class="mwvip-local-row"><th><?php esc_html_e( 'پوشهٔ فایل‌های حفاظت‌شده', 'music-wave-vip' ); ?></th><td><?php $this->root_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'نشانی پایهٔ میزبان راه‌دور', 'music-wave-vip' ); ?></th><td><?php $this->remote_base_url_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'پیشوند مسیر راه‌دور', 'music-wave-vip' ); ?></th><td><?php $this->remote_path_prefix_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'راز امضای راه‌دور', 'music-wave-vip' ); ?></th><td><?php $this->remote_signing_secret_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'پارامتر query امضا', 'music-wave-vip' ); ?></th><td><?php $this->remote_signature_param_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'پارامتر query انقضا', 'music-wave-vip' ); ?></th><td><?php $this->remote_expires_param_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'طول عمر نشانی راه‌دور', 'music-wave-vip' ); ?></th><td><?php $this->remote_ttl_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'میزبان‌های مجاز اضافی برای تغییرمسیر', 'music-wave-vip' ); ?></th><td><?php $this->remote_allowed_hosts_field(); ?></td></tr>
						<tr class="mwvip-remote-row" <?php echo $remote_active ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'شناسهٔ کلید امضا', 'music-wave-vip' ); ?></th><td><?php $this->remote_key_id_field(); ?></td></tr>
					</table>
					<h3><?php esc_html_e( 'شتاب‌دهی سرور', 'music-wave-vip' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'شتاب‌دهی سرور', 'music-wave-vip' ); ?></th><td><?php $this->sendfile_mode_field(); ?></td></tr>
						<tr class="mwvip-xaccel-row" <?php echo 'xaccel' === (string) $settings['sendfile_mode'] ? '' : 'style="display:none;"'; ?>><th><?php esc_html_e( 'پیشوند داخلی X-Accel', 'music-wave-vip' ); ?></th><td><?php $this->xaccel_prefix_field(); ?></td></tr>
					</table>
				</div>
				<div class="mwvip-panel" data-panel="membership">
					<h2 style="margin-top:0;"><?php esc_html_e( 'عضویت و مجوز دسترسی', 'music-wave-vip' ); ?></h2>
					<?php if ( ! $module_on ) : ?>
						<div class="notice notice-info inline" id="mwvip-membership-note" style="margin:0 0 16px;"><p><span class="dashicons dashicons-hidden" style="color:#2271b1;vertical-align:middle;"></span> <?php esc_html_e( 'اعمال قوانین خاموش است؛ بنابراین این گزینه‌ها فقط خواندنی‌اند و اعمال نمی‌شوند. پیکربندی شما حفظ می‌شود و به‌محض روشن‌کردن دوبارهٔ کلید اصلی ادامه پیدا می‌کند.', 'music-wave-vip' ); ?></p></div>
					<?php endif; ?>
					<?php $this->render_plans_manager(); ?>
					<?php $this->membership_section(); ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'منابع عضویت', 'music-wave-vip' ); ?></th><td><?php $this->membership_sources_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'محصولات طرح VIP', 'music-wave-vip' ); ?></th><td><?php $this->plan_rows_field(); ?></td></tr>
						<tr><th><?php esc_html_e( 'ارتقای نقش VIP', 'music-wave-vip' ); ?></th><td><?php $this->promote_vip_role_field(); ?></td></tr>
					</table>
					<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:4px;padding:12px;margin-top:16px;">
						<h4 style="margin:0 0 8px;"><span class="dashicons dashicons-info" style="color:#2271b1;"></span> <?php esc_html_e( 'چگونه یک سطح VIP به انتشار اختصاص دهیم', 'music-wave-vip' ); ?></h4>
						<ol style="margin:0 0 0 18px;">
							<li><?php esc_html_e( 'در ووکامرس → محصولات، یک محصول ایجاد یا ویرایش کنید (مثلاً «VIP Gold — سالانه»).', 'music-wave-vip' ); ?></li>
							<li><?php /* translators: %s: example plan mapping. */ printf( esc_html__( 'یک خط اضافه کنید؛ مثلاً %s که در آن level نامک سطح عضویت شماست.', 'music-wave-vip' ), '<code>vipgold:123:365</code>' ); ?></li>
							<li><?php esc_html_e( 'به انتشار → دسترسی بروید، «عضویت» را انتخاب کنید و همان سطح (vipgold) را در «سطح‌های عضویت» وارد کنید.', 'music-wave-vip' ); ?></li>
							<li><?php esc_html_e( 'منتشر کنید. مشتریانی که آن محصول را می‌خرند فوراً سطح را می‌گیرند؛ انقضا یا بازپرداخت آن را خودکار پس می‌گیرد.', 'music-wave-vip' ); ?></li>
						</ol>
					</div>
				</div>
				<div class="mwvip-panel" data-panel="advanced">
					<h2 style="margin-top:0;"><?php esc_html_e( 'پیشرفته و عیب‌یابی', 'music-wave-vip' ); ?></h2>
					<p class="description"><?php esc_html_e( 'یادداشت‌های امنیت عملیاتی و محل مدیریت بخش‌های دیگر MusicWave. همهٔ گزینه‌های VIP در این صفحه هستند — پیوندهای زیر به صفحه‌های صاحب تنظیمات غیر VIP می‌روند.', 'music-wave-vip' ); ?></p>
					<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;max-width:1000px;margin-top:12px;">
						<div style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;">
							<h4 style="margin:0 0 8px;"><?php esc_html_e( 'یادداشت‌های امنیتی', 'music-wave-vip' ); ?></h4>
							<ul style="margin:0 0 0 18px;list-style:disc;">
								<li><?php esc_html_e( 'رازهای راه‌دور هرگز در معرض مرورگر قرار نمی‌گیرند؛ فقط نشانی‌های کوتاه‌عمر امضاشده تغییرمسیر داده می‌شوند (با Referrer-Policy: no-referrer).', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'هر تصمیم دسترسی به‌صورت زنده دوباره بررسی می‌شود و هیچ مجوزی در حافظهٔ نهان ذخیره نمی‌شود. نشانی‌های راه‌دور شامل path، expiry، mode و kid در HMAC هستند.', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'پوشهٔ حفاظت‌شده را خارج از همهٔ مسیرهای ارائه‌شده توسط وب نگه دارید. .htaccess/web.config فقط لایهٔ دفاعی مضاعف‌اند.', 'music-wave-vip' ); ?></li>
								<li><?php esc_html_e( 'طرح‌های زمان‌دار با cron ساعتی و هنگام ورود خودکار منقضی می‌شوند؛ پاک‌سازی دستی لازم نیست.', 'music-wave-vip' ); ?></li>
							</ul>
						</div>
						<div style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;">
							<h4 style="margin:0 0 8px;"><?php esc_html_e( 'تنظیمات مرتبط', 'music-wave-vip' ); ?></h4>
							<div class="mwvip-related">
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings&tab=access' ) ); ?>"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'پیام‌های دسترسی و مسیر جایگزین عضویت (هسته)', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-settings&tab=delivery' ) ); ?>"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'محدودیت دانلود، سهمیه و نگه‌داری (هسته)', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release' ) ); ?>"><span class="dashicons dashicons-album"></span> <?php esc_html_e( 'حالت دسترسی هر انتشار و سطح‌های عضویت', 'music-wave-vip' ); ?></a>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'تنظیمات ووکامرس', 'music-wave-vip' ); ?></a>
								<?php endif; ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ); ?>"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'راه‌اندازی و عیب‌یابی', 'music-wave-vip' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'سلامت سایت', 'music-wave-vip' ); ?></a>
							</div>
						</div>
					</div>
				</div>
				<p style="background:#fff;border:1px solid #ccd0d4;padding:12px;border-radius:4px;display:flex;align-items:center;gap:12px;">
					<?php submit_button( __( 'ذخیرهٔ تنظیمات VIP', 'music-wave-vip' ), 'primary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'تنظیمات پاک‌سازی و اعتبارسنجی می‌شوند؛ رازهای ضعیف یا مسیرهای نادرست هشدار نشان می‌دهند و مقدار قبلی را حفظ می‌کنند.', 'music-wave-vip' ); ?></span>
				</p>
			</form>
		</div>
		<script>
			(function(){
				var tabs=document.querySelectorAll('.mwvip-tabs button');
				var panels=document.querySelectorAll('.mwvip-panel');
				tabs.forEach(function(btn){
					btn.addEventListener('click',function(){
						var tab=btn.getAttribute('data-tab');
						tabs.forEach(function(b){b.classList.remove('active');b.setAttribute('aria-selected','false');});
						btn.classList.add('active');btn.setAttribute('aria-selected','true');
						panels.forEach(function(p){
							p.classList.toggle('active', p.getAttribute('data-panel')===tab);
						});
					});
				});
				// Delivery provider show/hide
				var sel=document.getElementById('mwvip-delivery-provider');
				if(sel){
					sel.addEventListener('change',function(){
						var isRemote=sel.value==='remote_redirect';
						document.querySelectorAll('.mwvip-remote-row').forEach(function(r){r.style.display=isRemote?'':'none';});
						document.querySelectorAll('.mwvip-local-row').forEach(function(r){r.style.display=isRemote?'none':'';});
					});
				}
				// X-Accel prefix is only relevant in xaccel mode
				var sf=document.getElementById('mwvip-sendfile-mode');
				if(sf){
					sf.addEventListener('change',function(){
						var isXaccel=sf.value==='xaccel';
						document.querySelectorAll('.mwvip-xaccel-row').forEach(function(r){r.style.display=isXaccel?'':'none';});
					});
				}
				// Master switch: membership and access controls are only
				// editable while enforcement is on; delivery stays editable.
				var master=document.getElementById('mwvip-module-enabled');
				if(master){
					master.addEventListener('change',function(){
						var on=master.checked;
						document.querySelectorAll('.mwvip-module-control').forEach(function(el){el.disabled=!on;});
						var note=document.getElementById('mwvip-membership-note');
						if(note){note.style.display=on?'none':'';}
					});
				}
				// Secret toggle/generate
				var tog=document.getElementById('mwvip-toggle-secret');
				var inp=document.getElementById('mwvip-remote-secret');
				if(tog&&inp){tog.addEventListener('click',function(){inp.type=inp.type==='password'?'text':'password';});}
				var gen=document.getElementById('mwvip-gen-secret');
				if(gen&&inp){
					gen.addEventListener('click',function(){
						var chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.~';
						var s='';for(var i=0;i<48;i++) s+=chars.charAt(Math.floor(Math.random()*chars.length));
						inp.type='text';inp.value=s;inp.focus();
					});
				}
			})();
		</script>
		<?php
	}

	/**
	 * Register contextual help tabs for the VIP settings screen.
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
				'id'      => 'music-wave-vip-guide',
				'title'   => __( 'تنظیمات VIP', 'music-wave-vip' ),
				'content' =>
					'<p>' . esc_html__( 'این صفحه تنها محل پیکربندی همهٔ گزینه‌های MusicWave VIP است:', 'music-wave-vip' ) . '</p>' .
					'<ul>' .
					'<li>' . esc_html__( 'عمومی — کلید اصلی اعمال قوانین و اینکه چه کسی می‌تواند صدای حفاظت‌شده را پخش یا دانلود کند.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'تحویل — ذخیره‌سازی محلی حفاظت‌شده یا میزبان HTTPS امضاشدهٔ راه‌دور، به‌همراه شتاب‌دهی سرور.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'عضویت و طرح‌ها — منابع مجوز دسترسی و محصولات طرح ووکامرس.', 'music-wave-vip' ) . '</li>' .
					'<li>' . esc_html__( 'پیشرفته — یادداشت‌های امنیتی و پیوند به صفحه‌هایی که تنظیمات مرتبطِ غیر VIP را مدیریت می‌کنند.', 'music-wave-vip' ) . '</li>' .
					'</ul>' .
					'<p>' . esc_html__( 'تا وقتی کلید اصلی خاموش است، گزینه‌های عضویت فقط خواندنی‌اند و پیکربندی شما حفظ می‌شود.', 'music-wave-vip' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'music-wave-vip-diagnostics',
				'title'   => __( 'راهنما و عیب‌یابی', 'music-wave-vip' ),
				'content' =>
					'<p>' . esc_html__( 'اگر دانلودهای حفاظت‌شده یا عضویت درست کار نمی‌کنند، از بررسی‌های محیط MusicWave و صفحهٔ سلامت سایت وردپرس شروع کنید.', 'music-wave-vip' ) . '</p>' .
					'<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mw_release&page=music-wave-setup' ) ) . '">' . esc_html__( 'باز کردن راه‌اندازی و عیب‌یابی', 'music-wave-vip' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'باز کردن سلامت سایت', 'music-wave-vip' ) . '</a></p>',
			)
		);
	}

	/**
	 * Show why local protected delivery is disabled instead of failing silently.
	 *
	 * @return void
	 */
	private function render_preflight_notice(): void {
		if ( 'local' !== (string) VipSettings::all()['delivery_provider'] ) {
			return;
		}

		$checks = ( new ProtectedAssetStorage() )->preflight();
		$labels = array(
			'root_resolved'    => __( 'مسیر پوشهٔ حفاظت‌شده تنظیم شده یا قابل تشخیص است.', 'music-wave-vip' ),
			'directory_exists' => __( 'پوشهٔ حفاظت‌شده وجود دارد.', 'music-wave-vip' ),
			'readable'         => __( 'وب‌سرور می‌تواند پوشهٔ حفاظت‌شده را بخواند.', 'music-wave-vip' ),
			'writable'         => __( 'وب‌سرور می‌تواند بارگذاری‌ها را در پوشهٔ حفاظت‌شده بنویسد.', 'music-wave-vip' ),
			'outside_web_root' => __( 'پوشه خارج از همهٔ ریشه‌های وب عمومی است.', 'music-wave-vip' ),
			'deny_files'       => __( 'فایل‌های بازدارندهٔ لایهٔ دفاعی (.htaccess) وجود دارند.', 'music-wave-vip' ),
		);

		$failed = array_keys(
			array_filter(
				$checks,
				static function ( bool $passed ): bool {
					return ! $passed;
				}
			)
		);
		if ( array() === $failed ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'پیش‌آزمایش تحویل حفاظت‌شده ناموفق بود. دانلودهای محلی تا موفق‌شدن همهٔ بررسی‌ها غیرفعال می‌مانند:', 'music-wave-vip' ) . '</strong></p><ul style="list-style:disc;padding-left:20px">';
		foreach ( $failed as $check ) {
			if ( isset( $labels[ $check ] ) ) {
				echo '<li>' . esc_html( $labels[ $check ] ) . '</li>';
			}
		}
		echo '</ul><p>' . esc_html__( 'در نصب‌های زیرپوشه‌ای، مقدار خودکار کنار وردپرس رد می‌شود چون همچنان از وب قابل دسترسی است. یک مسیر مطلق خارج از ریشهٔ اسناد سرور تنظیم کنید (مثلاً /var/private/musicwave) و فایل‌های موجود را به آنجا منتقل کنید.', 'music-wave-vip' ) . '</p></div>';
	}

	/** @param array<int, array<string, mixed>> $cards @return array<int, array<string, mixed>> */
	public function integration_card( array $cards ): array {
		$configured   = VipSettings::all();
		$remote_ready = 'remote_redirect' === $configured['delivery_provider'] && '' !== $configured['remote_base_url'] && '' !== $configured['remote_signing_secret'];
		$active       = 'local' === $configured['delivery_provider'] || $remote_ready;
		$plans        = isset( $configured['vip_plans'] ) && is_array( $configured['vip_plans'] ) ? count( $configured['vip_plans'] ) : 0;
		$free_mode    = 'disabled' === (string) $configured['module_enabled'];
		$card         = array(
			'id'          => 'music-wave-vip',
			'name'        => __( 'تحویل حفاظت‌شده و عضویت‌های VIP', 'music-wave-vip' ),
			'active'      => $active,
			'description' => $free_mode
				? __( 'کلید اصلی خاموش است: همهٔ انتشارهای مشروط به عضویت فعلاً برای همه رایگان‌اند. تحویل حفاظت‌شده همچنان فعال است.', 'music-wave-vip' )
				: ( $active
					? sprintf(
						/* translators: 1: delivery type, 2: number of VIP plans. */
						__( 'متصل شد: تحویل %1$s فعال است. %2$d طرح VIP پیکربندی‌شده است.', 'music-wave-vip' ),
						'local' === $configured['delivery_provider'] ? __( 'محلی', 'music-wave-vip' ) : __( 'راه‌دورِ امضاشده', 'music-wave-vip' ),
						$plans
					)
					: __( 'ذخیره‌سازی محلی حفاظت‌شده، میزبان دانلود HTTPS امضاشده، نقش‌های وردپرس، افزونه‌های عضویت و اشتراک‌های ووکامرس را به هم متصل می‌کند.', 'music-wave-vip' ) ),
			'url'         => self::page_url(),
			'action'      => __( 'باز کردن تنظیمات VIP', 'music-wave-vip' ),
		);
		foreach ( $cards as $index => $existing ) {
			if ( is_array( $existing ) && isset( $existing['id'] ) && 'music-wave-vip' === $existing['id'] ) {
				$cards[ $index ] = $card;
				return $cards;
			}
		}
		$cards[] = $card;
		return $cards;
	}
}
