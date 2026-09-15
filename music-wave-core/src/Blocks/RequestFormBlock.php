<?php
/**
 * Public "custom song & collaboration request" block.
 *
 * Renders an editorial split layout: a pitch column (what visitors can ask
 * for, how the process works) and a progressive form that posts to
 * `admin-post.php` without JavaScript. Validation errors and the previous
 * values come back through the form handler's one-shot stash, so the page
 * never re-renders user input from the URL. A tiny enhancement script adds
 * a live character counter; everything works without it.
 *
 * The block has three modes so one site can run a combined page or two
 * dedicated pages ("custom song" / "collaboration"):
 *
 * - `both`   every request kind the manager enabled, chosen with chips;
 * - `song`   only the custom-song kind (chips hidden, kind fixed);
 * - `collab` only collaboration / partnership kinds.
 *
 * The mode is enforced twice: the form renders only the allowed kinds, and
 * the submission handler refuses any kind outside the list the block sent,
 * so a crafted POST cannot file a "collaboration" through a song-only page.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Requests\RequestFormHandler;
use ManaCore\MusicWave\Core\Requests\RequestPostType;
use ManaCore\MusicWave\Core\Requests\RequestSettings;
use ManaCore\MusicWave\Core\Requests\RequestSubmission;

final class RequestFormBlock {
	public const NAME = 'music-wave/request-form';

	/** Shared handle for the public stylesheet and the enhancement script. */
	public const ASSET_HANDLE = 'music-wave-request-form';

	/** Block modes and the request kinds each one accepts. */
	private const MODES = array(
		'both'   => array(),
		'song'   => array( 'song' ),
		'collab' => array( 'collab', 'advertising', 'event' ),
	);

	/** Default role chip per mode. */
	private const DEFAULT_ROLES = array(
		'both'   => 'singer',
		'song'   => 'singer',
		'collab' => 'producer',
	);

	/** @var RequestFormHandler */
	private $forms;

	/** @var RequestSettings */
	private $settings;

	public function __construct( RequestFormHandler $forms, ?RequestSettings $settings = null ) {
		$this->forms    = $forms;
		$this->settings = null !== $settings ? $settings : new RequestSettings();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$this->register_assets();

		BlockSupport::register_dynamic(
			self::NAME,
			function ( $attributes ): string {
				return $this->render( is_array( $attributes ) ? $attributes : array() );
			},
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Supported block modes.
	 *
	 * @return array<int, string>
	 */
	public static function modes(): array {
		return array_keys( self::MODES );
	}

	/**
	 * Request kinds a mode may offer, ordered like the manager sees them.
	 *
	 * `both` returns every enabled kind; a restricted mode returns the
	 * intersection of its list and the enabled kinds. An empty result means
	 * the manager switched this kind off, and the block shows the closed
	 * notice instead of a form that could never be submitted.
	 *
	 * @return array<string, string> Kind => label.
	 */
	public function kinds_for_mode( string $mode ): array {
		$enabled = $this->settings->enabled_types();
		$allowed = isset( self::MODES[ $mode ] ) ? self::MODES[ $mode ] : array();
		if ( array() === $allowed ) {
			return $enabled;
		}

		return array_intersect_key( $enabled, array_flip( $allowed ) );
	}

	/**
	 * Render the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$mode     = BlockSupport::key_attribute( $attributes, 'mode', self::modes(), 'both' );
		$layout   = BlockSupport::key_attribute( $attributes, 'layout', array( 'split', 'stacked' ), 'split' );
		$copy     = $this->copy( $mode );
		$eyebrow  = BlockSupport::text_attribute( $attributes, 'eyebrow', $copy['eyebrow'] );
		$heading  = BlockSupport::text_attribute( $attributes, 'heading', $copy['heading'] );
		$intro    = BlockSupport::text_attribute( $attributes, 'intro', $copy['intro'] );
		$notice   = $this->requested_notice();
		$stash    = $this->forms->stashed( $this->requested_token() );
		$errors   = $stash['errors'];
		$values   = $stash['values'];
		$open     = $this->settings->form_enabled() && array() !== $this->kinds_for_mode( $mode );
		$has_side = BlockSupport::bool_attribute( $attributes, 'showSteps', true ) || BlockSupport::bool_attribute( $attributes, 'showHighlights', true );

		$class = 'mw-request-form mw-request-form--' . $layout . ' mw-request-form--mode-' . $mode . ( $has_side ? '' : ' mw-request-form--form-only' );

		$html  = '<section ' . BlockSupport::wrapper_attributes( $class ) . ' id="mw-request-form" data-mw-request-mode="' . esc_attr( $mode ) . '">';
		$html .= '<div class="mw-request-form__glow" aria-hidden="true"></div>';

		// Pairing: templates/patterns turn this off and put heading, intro,
		// highlights and steps in core blocks with the same __* classes so
		// each line is canvas-selectable. POST/nonce/honeypot stay here.
		if ( BlockSupport::bool_attribute( $attributes, 'showHeading', true ) ) {
			$html .= '<header class="mw-request-form__header">';
			$html .= '<span class="mw-request-form__eyebrow"><span class="mw-request-form__eyebrow-dot" aria-hidden="true"></span>' . esc_html( $eyebrow ) . '</span>';
			$html .= '<h2 class="mw-request-form__heading">' . esc_html( $heading ) . '</h2>';
			$html .= '<p class="mw-request-form__intro">' . esc_html( $intro ) . '</p>';
			$html .= '</header>';
		}

		$html .= '<div class="mw-request-form__layout">';
		if ( $has_side ) {
			$html .= $this->side_markup( $attributes, $mode );
		}
		$html .= '<div class="mw-request-form__panel">';
		$html .= $this->notice_markup( $notice );
		if ( 'sent' === $notice ) {
			$html .= $this->success_markup();
		} elseif ( ! $open ) {
			$html .= '<p class="mw-request-form__closed" role="status">' . esc_html( $this->forms->notice_message( 'closed' ) ) . '</p>';
		} else {
			$html .= $this->form_markup( $attributes, $mode, $errors, $values );
		}
		$html .= '</div></div></section>';

		$this->enqueue_assets();

		return $html;
	}

	/**
	 * Translated default copy per mode; block attributes override each piece.
	 *
	 * @return array{eyebrow: string, heading: string, intro: string, submit: string, highlights: array<int, array{0: string, 1: string, 2: string}>, steps: array<int, string>}
	 */
	private function copy( string $mode ): array {
		switch ( $mode ) {
			case 'song':
				return array(
					'eyebrow'    => __( 'سفارش آهنگ اختصاصی', 'music-wave-core' ),
					'heading'    => __( 'آهنگی که فقط برای شما ساخته می‌شود', 'music-wave-core' ),
					'intro'      => __( 'برای خودتان، برندتان یا یک مناسبت خاص قطعه‌ای اختصاصی سفارش دهید. حال‌وهوا، مدت و محل استفاده را بنویسید؛ ما با پیشنهاد، زمان‌بندی و برآورد هزینه پاسخ می‌دهیم.', 'music-wave-core' ),
					'submit'     => __( 'ثبت سفارش آهنگ', 'music-wave-core' ),
					'highlights' => array(
						array( '♪', __( 'ترانه و ملودی', 'music-wave-core' ), __( 'از ایدهٔ اولیه تا ترانه، ملودی و تنظیم نهایی.', 'music-wave-core' ) ),
						array( '◎', __( 'ضبط و مسترینگ', 'music-wave-core' ), __( 'اجرای استودیویی، میکس و مستر آمادهٔ انتشار.', 'music-wave-core' ) ),
						array( '✦', __( 'حقوق استفاده', 'music-wave-core' ), __( 'قرارداد شفاف برای استفادهٔ شخصی، تجاری یا تبلیغاتی.', 'music-wave-core' ) ),
					),
					'steps'      => array(
						__( 'حال‌وهوا، سبک و محل استفادهٔ قطعه را بنویسید.', 'music-wave-core' ),
						__( 'ایمیل تأیید با شمارهٔ پیگیری دریافت می‌کنید.', 'music-wave-core' ),
						__( 'پیشنهاد، زمان‌بندی و برآورد هزینه برایتان ارسال می‌شود.', 'music-wave-core' ),
					),
				);
			case 'collab':
				return array(
					'eyebrow'    => __( 'همکاری', 'music-wave-core' ),
					'heading'    => __( 'بیایید با هم چیزی تازه بسازیم', 'music-wave-core' ),
					'intro'      => __( 'خواننده‌ها، تهیه‌کننده‌ها، برندها و برگزارکنندگان رویداد می‌توانند از همین‌جا پیشنهاد همکاری بدهند: فیچرینگ، تولید مشترک، موسیقی تبلیغاتی یا اجرای زنده.', 'music-wave-core' ),
					'submit'     => __( 'ارسال پیشنهاد همکاری', 'music-wave-core' ),
					'highlights' => array(
						array( '✦', __( 'همکاری هنری', 'music-wave-core' ), __( 'فیچرینگ، تولید مشترک، ریمیکس یا اجرای زنده با هنرمندان ما.', 'music-wave-core' ) ),
						array( '◎', __( 'برند و تبلیغات', 'music-wave-core' ), __( 'موسیقی تبلیغاتی، جینگل و ساند‌برندینگ برای کمپین‌ها.', 'music-wave-core' ) ),
						array( '♪', __( 'اجرا و رویداد', 'music-wave-core' ), __( 'برنامهٔ اجرای زنده، جشنواره‌ها و رویدادهای خصوصی.', 'music-wave-core' ) ),
					),
					'steps'      => array(
						__( 'ایده، نقش خودتان و نمونه‌کارهایتان را معرفی کنید.', 'music-wave-core' ),
						__( 'ایمیل تأیید با شمارهٔ پیگیری دریافت می‌کنید.', 'music-wave-core' ),
						__( 'تیم ما بررسی می‌کند و برای گفت‌وگو با شما تماس می‌گیرد.', 'music-wave-core' ),
					),
				);
		}

		return array(
			'eyebrow'    => __( 'سفارش و همکاری', 'music-wave-core' ),
			'heading'    => __( 'آهنگ اختصاصی می‌خواهید یا ایدهٔ همکاری دارید؟', 'music-wave-core' ),
			'intro'      => __( 'خواننده‌ها، تهیه‌کننده‌ها، برندها و حتی شنونده‌ها می‌توانند از همین‌جا درخواست بدهند. فرم را پر کنید؛ ما آن را می‌خوانیم و با پیشنهاد و زمان‌بندی پاسخ می‌دهیم.', 'music-wave-core' ),
			'submit'     => __( 'ارسال درخواست', 'music-wave-core' ),
			'highlights' => array(
				array( '♪', __( 'آهنگ اختصاصی', 'music-wave-core' ), __( 'ترانه، ملودی و تنظیم برای شما یا برندتان؛ از ایده تا مسترینگ.', 'music-wave-core' ) ),
				array( '✦', __( 'همکاری هنری', 'music-wave-core' ), __( 'فیچرینگ، تولید مشترک، ریمیکس یا اجرای زنده با هنرمندان ما.', 'music-wave-core' ) ),
				array( '◎', __( 'تبلیغات و رویداد', 'music-wave-core' ), __( 'موسیقی تبلیغاتی، جینگل، ساند‌برندینگ و برنامهٔ رویدادها.', 'music-wave-core' ) ),
			),
			'steps'      => array(
				__( 'فرم را پر کنید؛ کمتر از دو دقیقه طول می‌کشد.', 'music-wave-core' ),
				__( 'ایمیل تأیید با شمارهٔ پیگیری دریافت می‌کنید.', 'music-wave-core' ),
				__( 'تیم ما بررسی می‌کند و با پیشنهاد و زمان‌بندی پاسخ می‌دهد.', 'music-wave-core' ),
			),
		);
	}

	/**
	 * Pitch column: what can be requested and how the process works.
	 *
	 * Editors may replace the highlight cards and the steps line by line
	 * (`highlightN` / `stepN` attributes); blank attributes keep the
	 * translated defaults of the active mode.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function side_markup( array $attributes, string $mode ): string {
		$copy = $this->copy( $mode );
		$html = '<aside class="mw-request-form__side">';
		if ( BlockSupport::bool_attribute( $attributes, 'showHighlights', true ) ) {
			$html .= '<ul class="mw-request-form__highlights">';
			foreach ( $copy['highlights'] as $index => $item ) {
				$title = BlockSupport::text_attribute( $attributes, 'highlight' . ( $index + 1 ) . 'Title', $item[1] );
				$text  = BlockSupport::text_attribute( $attributes, 'highlight' . ( $index + 1 ) . 'Text', $item[2] );
				$html .= '<li class="mw-request-form__highlight"><span class="mw-request-form__highlight-icon" aria-hidden="true">' . esc_html( $item[0] ) . '</span><div><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $text ) . '</span></div></li>';
			}
			$html .= '</ul>';
		}
		if ( BlockSupport::bool_attribute( $attributes, 'showSteps', true ) ) {
			$html .= '<ol class="mw-request-form__steps">';
			foreach ( $copy['steps'] as $index => $step ) {
				$text  = BlockSupport::text_attribute( $attributes, 'step' . ( $index + 1 ), $step );
				$html .= '<li><span class="mw-request-form__step-number" aria-hidden="true">' . esc_html( number_format_i18n( $index + 1 ) ) . '</span><span>' . esc_html( $text ) . '</span></li>';
			}
			$html .= '</ol>';
		}
		$privacy = BlockSupport::text_attribute( $attributes, 'privacyNote', __( 'اطلاعات تماس شما فقط برای پاسخ به همین درخواست استفاده می‌شود.', 'music-wave-core' ) );
		$html   .= '<p class="mw-request-form__privacy">' . esc_html( $privacy ) . '</p>';

		return $html . '</aside>';
	}

	/**
	 * The form itself.
	 *
	 * @param array<string, mixed>  $attributes Block attributes.
	 * @param array<string, string> $errors     Field errors.
	 * @param array<string, mixed>  $values     Previous values.
	 */
	private function form_markup( array $attributes, string $mode, array $errors, array $values ): string {
		$value = static function ( string $key ) use ( $values ): string {
			if ( ! isset( $values[ $key ] ) ) {
				return '';
			}

			return is_array( $values[ $key ] ) ? implode( "\n", array_map( 'strval', $values[ $key ] ) ) : (string) $values[ $key ];
		};

		$copy       = $this->copy( $mode );
		$submit     = BlockSupport::text_attribute( $attributes, 'submitLabel', $copy['submit'] );
		$types      = $this->kinds_for_mode( $mode );
		$type_keys  = array_map( 'strval', array_keys( $types ) );
		$default    = BlockSupport::key_attribute( $attributes, 'defaultType', $type_keys, (string) $type_keys[0] );
		$type       = in_array( $value( 'type' ), $type_keys, true ) ? $value( 'type' ) : $default;
		$show_chips = count( $types ) > 1 && BlockSupport::bool_attribute( $attributes, 'showTypeChips', true );
		$role_keys  = array_map( 'strval', array_keys( RequestPostType::role_labels() ) );
		$role_start = BlockSupport::key_attribute( $attributes, 'defaultRole', $role_keys, self::DEFAULT_ROLES[ $mode ] );
		$role       = in_array( $value( 'role' ), $role_keys, true ) ? $value( 'role' ) : $role_start;

		$html = '<form class="mw-request-form__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate data-mw-request-form>';
		// The kinds this form offers are part of the nonce action, so a
		// crafted POST can neither widen the list nor file a kind this page
		// never offered (see RequestFormHandler::allowed_types()).
		$html .= wp_nonce_field( RequestFormHandler::nonce_action( $type_keys ), '_wpnonce', true, false );
		$html .= '<input type="hidden" name="action" value="' . esc_attr( RequestFormHandler::ACTION ) . '">';
		$html .= '<input type="hidden" name="mw_redirect" value="' . esc_attr( BlockSupport::current_url() ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( RequestFormHandler::TIMER ) . '" value="' . esc_attr( (string) time() ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( RequestFormHandler::KINDS ) . '" value="' . esc_attr( implode( ',', $type_keys ) ) . '">';
		// Honeypot: visually hidden, ignored by assistive tech, must stay empty.
		$html .= '<div class="mw-request-form__hp" aria-hidden="true"><label for="mw-request-website">' . esc_html__( 'وب‌سایت', 'music-wave-core' ) . '</label><input type="text" id="mw-request-website" name="' . esc_attr( RequestFormHandler::HONEYPOT ) . '" value="" tabindex="-1" autocomplete="off"></div>';

		// Step 1: what.
		$legend = 'song' === $mode ? __( 'چه آهنگی می‌خواهید؟', 'music-wave-core' ) : ( 'collab' === $mode ? __( 'پیشنهاد شما چیست؟', 'music-wave-core' ) : __( 'چه چیزی می‌خواهید؟', 'music-wave-core' ) );
		$html  .= '<fieldset class="mw-request-form__group"><legend class="mw-request-form__legend"><span class="mw-request-form__legend-index" aria-hidden="true">۱</span>' . esc_html( $legend ) . '</legend>';
		if ( $show_chips ) {
			$html .= '<div class="mw-request-form__chips" role="radiogroup" aria-label="' . esc_attr__( 'نوع درخواست', 'music-wave-core' ) . '">';
			foreach ( $types as $key => $label ) {
				$html .= '<label class="mw-request-form__chip"><input type="radio" name="type" value="' . esc_attr( (string) $key ) . '"' . checked( $type, (string) $key, false ) . '><span>' . esc_html( $label ) . '</span></label>';
			}
			$html .= '</div>';
		} else {
			$html .= '<input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
			if ( count( $types ) > 1 ) {
				$html .= '<p class="mw-request-form__kind">' . esc_html( $types[ $type ] ) . '</p>';
			}
		}
		if ( isset( $errors['type'] ) ) {
			$html .= '<span class="mw-request-form__error" id="mw-request-type-error">' . esc_html( $errors['type'] ) . '</span>';
		}
		$subject_hint = 'song' === $mode ? __( 'مثلاً: قطعهٔ پاپ برای تیزر تبلیغاتی', 'music-wave-core' ) : ( 'collab' === $mode ? __( 'مثلاً: فیچرینگ برای تک‌آهنگ بعدی', 'music-wave-core' ) : __( 'مثلاً: قطعهٔ پاپ برای تیزر تبلیغاتی', 'music-wave-core' ) );
		$html        .= $this->field( 'subject', __( 'عنوان درخواست', 'music-wave-core' ), '<input type="text" id="mw-request-subject" name="subject" value="' . esc_attr( $value( 'subject' ) ) . '" maxlength="' . (int) RequestSubmission::MAX_SUBJECT . '" required placeholder="' . esc_attr( $subject_hint ) . '"' . $this->described( 'subject', $errors ) . '>', $errors );
		$html        .= $this->field( 'message', __( 'توضیحات', 'music-wave-core' ), '<textarea id="mw-request-message" name="message" rows="6" minlength="' . (int) RequestSubmission::MIN_MESSAGE . '" maxlength="' . (int) RequestSubmission::MAX_MESSAGE . '" required placeholder="' . esc_attr__( 'سبک و حال‌وهوا، مدت زمان، نمونه‌های الهام‌بخش، محل استفاده…', 'music-wave-core' ) . '" data-mw-counter' . $this->described( 'message', $errors ) . '>' . esc_textarea( $value( 'message' ) ) . '</textarea><span class="mw-request-form__counter" data-mw-counter-output aria-live="polite"></span>', $errors, __( 'هرچه دقیق‌تر بنویسید، پاسخ سریع‌تر و دقیق‌تری می‌گیرید.', 'music-wave-core' ) );
		$optional     = array();
		if ( BlockSupport::bool_attribute( $attributes, 'showBudget', true ) ) {
			$options = '';
			foreach ( RequestPostType::budget_labels() as $key => $label ) {
				$options .= '<option value="' . esc_attr( (string) $key ) . '"' . selected( $value( 'budget' ), (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$optional[] = $this->field( 'budget', __( 'بودجهٔ تقریبی', 'music-wave-core' ), '<select id="mw-request-budget" name="budget">' . $options . '</select>', $errors );
		}
		if ( BlockSupport::bool_attribute( $attributes, 'showDeadline', true ) ) {
			$optional[] = $this->field( 'deadline', __( 'زمان مورد نظر', 'music-wave-core' ), '<input type="date" id="mw-request-deadline" name="deadline" value="' . esc_attr( $value( 'deadline' ) ) . '" dir="ltr"' . $this->described( 'deadline', $errors ) . '>', $errors );
		}
		if ( array() !== $optional ) {
			$html .= '<div class="mw-request-form__row">' . implode( '', $optional ) . '</div>';
		}
		if ( BlockSupport::bool_attribute( $attributes, 'showLinks', true ) ) {
			$html .= $this->field( 'links', __( 'نمونه‌کار یا پیوندهای مرتبط', 'music-wave-core' ), '<textarea id="mw-request-links" name="links" rows="2" dir="ltr" placeholder="https://…"' . $this->described( 'links', $errors ) . '>' . esc_textarea( $value( 'links' ) ) . '</textarea>', $errors, __( 'اختیاری؛ هر پیوند در یک خط (حداکثر ۵).', 'music-wave-core' ) );
		}
		$html .= '</fieldset>';

		// Step 2: who.
		$html .= '<fieldset class="mw-request-form__group"><legend class="mw-request-form__legend"><span class="mw-request-form__legend-index" aria-hidden="true">۲</span>' . esc_html__( 'شما که هستید؟', 'music-wave-core' ) . '</legend>';
		if ( BlockSupport::bool_attribute( $attributes, 'showRoles', true ) ) {
			$html .= '<div class="mw-request-form__chips mw-request-form__chips--soft" role="radiogroup" aria-label="' . esc_attr__( 'نقش شما', 'music-wave-core' ) . '">';
			foreach ( RequestPostType::role_labels() as $key => $label ) {
				$html .= '<label class="mw-request-form__chip"><input type="radio" name="role" value="' . esc_attr( (string) $key ) . '"' . checked( $role, (string) $key, false ) . '><span>' . esc_html( $label ) . '</span></label>';
			}
			$html .= '</div>';
		} else {
			$html .= '<input type="hidden" name="role" value="' . esc_attr( $role ) . '">';
		}
		$html .= '<div class="mw-request-form__row">';
		$html .= $this->field( 'name', __( 'نام و نام خانوادگی', 'music-wave-core' ), '<input type="text" id="mw-request-name" name="name" value="' . esc_attr( $value( 'name' ) ) . '" maxlength="' . (int) RequestSubmission::MAX_NAME . '" autocomplete="name" required' . $this->described( 'name', $errors ) . '>', $errors );
		$html .= $this->field( 'email', __( 'ایمیل', 'music-wave-core' ), '<input type="email" id="mw-request-email" name="email" value="' . esc_attr( $value( 'email' ) ) . '" autocomplete="email" inputmode="email" dir="ltr" required' . $this->described( 'email', $errors ) . '>', $errors );
		if ( BlockSupport::bool_attribute( $attributes, 'showPhone', true ) ) {
			$html .= $this->field( 'phone', __( 'شمارهٔ تماس (اختیاری)', 'music-wave-core' ), '<input type="tel" id="mw-request-phone" name="phone" value="' . esc_attr( $value( 'phone' ) ) . '" autocomplete="tel" inputmode="tel" dir="ltr"' . $this->described( 'phone', $errors ) . '>', $errors );
		}
		$html .= '</div></fieldset>';

		// Consent + submit.
		$html .= '<div class="mw-request-form__footer">';
		$html .= '<label class="mw-request-form__consent' . ( isset( $errors['consent'] ) ? ' is-invalid' : '' ) . '"><input type="checkbox" name="consent" value="1" required' . $this->described( 'consent', $errors ) . '><span>' . esc_html__( 'موافقم که نام و راه تماس من برای پاسخ به این درخواست ذخیره شود.', 'music-wave-core' ) . '</span></label>';
		if ( isset( $errors['consent'] ) ) {
			$html .= '<span class="mw-request-form__error" id="mw-request-consent-error">' . esc_html( $errors['consent'] ) . '</span>';
		}
		$html .= '<button type="submit" class="wp-element-button mw-request-form__submit"><span>' . esc_html( $submit ) . '</span><span class="mw-request-form__submit-icon" aria-hidden="true">➜</span></button>';
		$html .= '</div>';

		return $html . '</form>';
	}

	/**
	 * One labelled field with its error and hint.
	 *
	 * @param array<string, string> $errors Field errors.
	 */
	private function field( string $key, string $label, string $control, array $errors, string $hint = '' ): string {
		$html = '<div class="mw-request-form__field mw-request-form__field--' . esc_attr( $key ) . ( isset( $errors[ $key ] ) ? ' is-invalid' : '' ) . '"><label for="mw-request-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>' . $control;
		if ( isset( $errors[ $key ] ) ) {
			$html .= '<span class="mw-request-form__error" id="mw-request-' . esc_attr( $key ) . '-error">' . esc_html( $errors[ $key ] ) . '</span>';
		} elseif ( '' !== $hint ) {
			$html .= '<span class="mw-request-form__hint" id="mw-request-' . esc_attr( $key ) . '-hint">' . esc_html( $hint ) . '</span>';
		}

		return $html . '</div>';
	}

	/**
	 * aria-describedby / aria-invalid attributes for a field.
	 *
	 * @param array<string, string> $errors Field errors.
	 */
	private function described( string $key, array $errors ): string {
		if ( isset( $errors[ $key ] ) ) {
			return ' aria-invalid="true" aria-describedby="mw-request-' . esc_attr( $key ) . '-error"';
		}
		if ( in_array( $key, array( 'message', 'links' ), true ) ) {
			return ' aria-describedby="mw-request-' . esc_attr( $key ) . '-hint"';
		}

		return '';
	}

	private function notice_markup( string $notice ): string {
		if ( '' === $notice || 'sent' === $notice ) {
			return '';
		}
		$message = $this->forms->notice_message( $notice );
		if ( '' === $message ) {
			return '';
		}

		return '<p class="mw-request-form__notice' . ( $this->forms->notice_is_error( $notice ) ? ' mw-request-form__notice--error' : '' ) . '" role="alert" id="mw-request-notice">' . esc_html( $message ) . '</p>';
	}

	private function success_markup(): string {
		return '<div class="mw-request-form__success" role="status" id="mw-request-notice"><span class="mw-request-form__success-icon" aria-hidden="true">✓</span><h3>' . esc_html__( 'درخواست شما رسید', 'music-wave-core' ) . '</h3><p>' . esc_html( $this->forms->notice_message( 'sent' ) ) . '</p><a class="mw-request-form__again" href="' . esc_url( remove_query_arg( array( RequestFormHandler::NOTICE_ARG, RequestFormHandler::TOKEN_ARG ), BlockSupport::current_url() ) ) . '">' . esc_html__( 'ثبت درخواست دیگر', 'music-wave-core' ) . '</a></div>';
	}

	private function requested_notice(): string {
		return isset( $_GET[ RequestFormHandler::NOTICE_ARG ] ) && is_scalar( $_GET[ RequestFormHandler::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ RequestFormHandler::NOTICE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- post/redirect/get notice code.
	}

	private function requested_token(): string {
		return isset( $_GET[ RequestFormHandler::TOKEN_ARG ] ) && is_scalar( $_GET[ RequestFormHandler::TOKEN_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ RequestFormHandler::TOKEN_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- random one-shot token.
	}

	/**
	 * Register the public stylesheet and script once.
	 *
	 * block.json names the same handle as `style`/`editorStyle`, so WordPress
	 * enqueues the stylesheet wherever the block renders — including the
	 * Site Editor canvas (an iframe that never receives plain admin styles).
	 *
	 * @return void
	 */
	private function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_register_script' ) ) {
			return;
		}
		wp_register_style( self::ASSET_HANDLE, MUSIC_WAVE_CORE_URL . 'assets/request-form.css', array(), MUSIC_WAVE_CORE_VERSION );
		wp_register_script( self::ASSET_HANDLE, MUSIC_WAVE_CORE_URL . 'assets/request-form.js', array(), MUSIC_WAVE_CORE_VERSION, true );
		wp_localize_script(
			self::ASSET_HANDLE,
			'musicWaveRequestForm',
			array(
				/* translators: 1: characters typed, 2: maximum characters. */
				'counter' => __( '%1$s از %2$s نویسه', 'music-wave-core' ),
				'minimum' => sprintf(
					/* translators: %s: minimum number of characters. */
					__( 'حداقل %s نویسه بنویسید', 'music-wave-core' ),
					number_format_i18n( RequestSubmission::MIN_MESSAGE )
				),
			)
		);
	}

	/**
	 * Front-end style + enhancement script, enqueued on use only.
	 *
	 * Editor previews render through the REST API (an admin context): the
	 * stylesheet reaches the canvas via block.json, and the script is not
	 * needed there.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		if ( is_admin() || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		wp_enqueue_style( self::ASSET_HANDLE );
		wp_enqueue_script( self::ASSET_HANDLE );
	}
}
