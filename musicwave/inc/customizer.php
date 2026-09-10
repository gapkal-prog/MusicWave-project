<?php
/**
 * MusicWave theme customizer: header switcher and profile-image mode.
 *
 * Both settings use core theme_mod storage with allow-list sanitizers, so no
 * custom tables or options are introduced. The header switch only replaces
 * the generic `header` template part; templates with an explicit header
 * (stream, side-rail, landing) keep their own by design — this is explained
 * in the control description inside the Customizer itself.
 *
 * @package MusicWave
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Header parts the admin may switch between.
 *
 * Keys are template-part slugs, values are translated labels.
 *
 * @return array<string, string>
 */
function musicwave_header_part_choices(): array {
	return array(
		'header'          => __( 'استاندارد', 'musicwave' ),
		'header-centered' => __( 'وسط‌چین', 'musicwave' ),
		'header-minimal'  => __( 'ساده', 'musicwave' ),
		'header-stream'   => __( 'استریم (ریل کناری + نوار بالا)', 'musicwave' ),
		'header-side-rail' => __( 'ریل کناری مستقل', 'musicwave' ),
	);
}

/**
 * Sanitize the header-part choice against the allow-list.
 *
 * @param mixed $value Raw setting value.
 */
function musicwave_sanitize_header_part( $value ): string {
	$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

	return array_key_exists( $value, musicwave_header_part_choices() ) ? $value : 'header';
}

/**
 * Profile-image modes.
 *
 * @return array<string, string>
 */
function musicwave_avatar_mode_choices(): array {
	return array(
		'custom'  => __( 'آپلود توسط کاربر (پیش‌فرض)', 'musicwave' ),
		'default' => __( 'تصویر پیش‌فرض برای همه', 'musicwave' ),
		'off'     => __( 'غیرفعال (آواتار استاندارد وردپرس)', 'musicwave' ),
	);
}

/**
 * Sanitize the avatar-mode choice against the allow-list.
 *
 * @param mixed $value Raw setting value.
 */
function musicwave_sanitize_avatar_mode( $value ): string {
	$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

	return array_key_exists( $value, musicwave_avatar_mode_choices() ) ? $value : 'custom';
}

/**
 * Register MusicWave sections, settings, and controls.
 *
 * @param \WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function musicwave_customize_register( \WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'musicwave_header',
		array(
			'title'       => __( 'سربرگ MusicWave', 'musicwave' ),
			'description' => __( 'انتخاب کنید کدام سربرگ در صفحات نمایش داده شود. قالب‌های استریم و حساب، سربرگ مخصوص خود را دارند و تغییر نمی‌کنند؛ برای تغییر آن‌ها، همان قالب را در «نمایش ← ویرایشگر ← قالب‌ها» ویرایش کنید.', 'musicwave' ),
			'priority'    => 30,
		)
	);
	$wp_customize->add_setting(
		'musicwave_header_part',
		array(
			'default'           => 'header',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'musicwave_sanitize_header_part',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'musicwave_header_part',
		array(
			'label'       => __( 'سربرگ فعال', 'musicwave' ),
			'description' => __( 'نمایش ← سفارشی‌سازی ← سربرگ MusicWave. پیش‌نمایش زنده در همین صفحه دیده می‌شود.', 'musicwave' ),
			'section'     => 'musicwave_header',
			'settings'    => 'musicwave_header_part',
			'type'        => 'select',
			'choices'     => musicwave_header_part_choices(),
		)
	);

	$wp_customize->add_section(
		'musicwave_profile',
		array(
			'title'       => __( 'پروفایل تصویری MusicWave', 'musicwave' ),
			'description' => __( 'تعیین کنید کاربران چگونه تصویر پروفایل داشته باشند. در حالت آپلود، هر کاربر از «کاربران ← پروفایل شما ← تصویر پروفایل MusicWave» عکس انتخاب می‌کند.', 'musicwave' ),
			'priority'    => 31,
		)
	);
	$wp_customize->add_setting(
		'musicwave_avatar_mode',
		array(
			'default'           => 'custom',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'sanitize_callback' => 'musicwave_sanitize_avatar_mode',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'musicwave_avatar_mode',
		array(
			'label'       => __( 'حالت تصویر پروفایل', 'musicwave' ),
			'description' => __( 'آپلود: کاربر از ویرایش پروفایل عکس انتخاب/آپلود می‌کند. پیش‌فرض: تصویر پیش‌فرض قالب برای همه. غیرفعال: مانند حالت فعلی وردپرس باقی می‌ماند.', 'musicwave' ),
			'section'     => 'musicwave_profile',
			'settings'    => 'musicwave_avatar_mode',
			'type'        => 'select',
			'choices'     => musicwave_avatar_mode_choices(),
		)
	);
}
add_action( 'customize_register', 'musicwave_customize_register' );

/**
 * Swap the generic header template part with the admin-chosen one.
 *
 * Only the `header` slug in the `header` area is replaced, so explicit
 * template choices (header-stream, header-side-rail, landing without header)
 * are never overridden behind the admin's back.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function musicwave_swap_header_part( string $block_content, array $block ): string {
	if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}
	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	if ( 'header' !== ( $attrs['slug'] ?? '' ) || 'header' !== ( $attrs['area'] ?? 'header' ) ) {
		return $block_content;
	}
	$chosen = musicwave_sanitize_header_part( get_theme_mod( 'musicwave_header_part', 'header' ) );
	if ( 'header' === $chosen ) {
		return $block_content;
	}

	$swapped = do_blocks( '<!-- wp:template-part {"slug":"' . $chosen . '","tagName":"header"} /-->' );

	return '' !== trim( $swapped ) ? $swapped : $block_content;
}
add_filter( 'render_block', 'musicwave_swap_header_part', 10, 2 );
