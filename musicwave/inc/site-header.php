<?php
/**
 * Site-wide header chrome switch.
 *
 * Templates keep `<!-- wp:template-part {"slug":"header"} -->`. One theme mod
 * remaps that slug (and any other header-* part) to the layout the merchant
 * picked in Customizer or Appearance → سربرگ MusicWave.
 *
 * @package MusicWave
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed header template-part slugs.
 *
 * @return array<string, string> slug => label.
 */
function musicwave_site_header_choices(): array {
	return array(
		'header'            => __( 'سربرگ ساده (افقی)', 'musicwave' ),
		'header-centered'   => __( 'سربرگ وسط‌چین', 'musicwave' ),
		'header-minimal'    => __( 'سربرگ کمینه', 'musicwave' ),
		'header-stream'     => __( 'سربرگ استریم (ریل شروع اینلاین)', 'musicwave' ),
		'header-stream-end' => __( 'سربرگ استریم (ریل انتها)', 'musicwave' ),
	);
}

/**
 * Sanitize the stored header slug.
 *
 * @param mixed $value Raw value.
 */
function musicwave_sanitize_site_header( $value ): string {
	$slug = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

	return isset( musicwave_site_header_choices()[ $slug ] ) ? $slug : 'header-stream';
}

/**
 * Active site-wide header slug.
 */
function musicwave_site_header_slug(): string {
	$slug = get_theme_mod( 'musicwave_site_header', 'header-stream' );

	return musicwave_sanitize_site_header( $slug );
}

/**
 * Mobile chrome for the stream header.
 *
 * @return array<string, string>
 */
function musicwave_mobile_header_choices(): array {
	return array(
		'drawer' => __( 'موبایل: سربرگ ساده + منوی کناری', 'musicwave' ),
		'tabs'   => __( 'موبایل: سربرگ + نوار پایین', 'musicwave' ),
	);
}

/**
 * Sanitize the stored mobile header slug.
 *
 * @param mixed $value Raw value.
 */
function musicwave_sanitize_mobile_header( $value ): string {
	$slug = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

	return isset( musicwave_mobile_header_choices()[ $slug ] ) ? $slug : 'drawer';
}

/**
 * Active mobile chrome slug.
 */
function musicwave_mobile_header_slug(): string {
	$slug = get_theme_mod( 'musicwave_site_header_mobile', 'drawer' );

	return musicwave_sanitize_mobile_header( $slug );
}

/**
 * How the brand appears in the stream top bar.
 *
 * @return array<string, string>
 */
function musicwave_topbar_brand_choices(): array {
	return array(
		'full' => __( 'لوگو و نام سایت', 'musicwave' ),
		'logo' => __( 'فقط لوگو', 'musicwave' ),
		'hide' => __( 'مخفی', 'musicwave' ),
	);
}

/**
 * @param mixed $value Raw value.
 */
function musicwave_sanitize_topbar_brand( $value ): string {
	$slug = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

	return isset( musicwave_topbar_brand_choices()[ $slug ] ) ? $slug : 'full';
}

/**
 * @param mixed $value Raw value.
 */
function musicwave_sanitize_onoff( $value ): string {
	if ( true === $value || 1 === $value || '1' === $value || 'on' === $value ) {
		return '1';
	}

	return '0';
}

/**
 * @param mixed $value Raw value.
 */
function musicwave_sanitize_tabs_count( $value ): string {
	$count = is_scalar( $value ) ? absint( $value ) : 5;

	return in_array( $count, array( 4, 5 ), true ) ? (string) $count : '5';
}

/**
 * Chrome flags consumed by rail.css.
 *
 * @return array<string, string>
 */
function musicwave_header_chrome_attrs(): array {
	return array(
		'data-mw-mobile-chrome' => musicwave_mobile_header_slug(),
		'data-mw-topbar-brand'  => musicwave_sanitize_topbar_brand( get_theme_mod( 'musicwave_topbar_brand', 'full' ) ),
		'data-mw-rail-brand'    => musicwave_sanitize_onoff( get_theme_mod( 'musicwave_rail_brand', '1' ) ),
		'data-mw-show-search'   => musicwave_sanitize_onoff( get_theme_mod( 'musicwave_show_search', '1' ) ),
		'data-mw-show-theme'    => musicwave_sanitize_onoff( get_theme_mod( 'musicwave_show_theme', '1' ) ),
		'data-mw-show-account'  => musicwave_sanitize_onoff( get_theme_mod( 'musicwave_show_account', '1' ) ),
		'data-mw-tabs-count'    => musicwave_sanitize_tabs_count( get_theme_mod( 'musicwave_tabs_count', '5' ) ),
	);
}

/**
 * Expose chrome choices on <html> for rail.css.
 *
 * @param string $output Language attributes markup.
 */
function musicwave_mobile_header_language_attributes( string $output ): string {
	$bits = array();
	foreach ( musicwave_header_chrome_attrs() as $name => $value ) {
		$bits[] = $name . '="' . esc_attr( $value ) . '"';
	}

	return trim( $output . ' ' . implode( ' ', $bits ) );
}
add_filter( 'language_attributes', 'musicwave_mobile_header_language_attributes', 20 );

/**
 * Remap every header template part to the chosen chrome.
 *
 * @param string               $block_content Rendered HTML.
 * @param array<string, mixed> $block         Parsed block.
 */
function musicwave_remap_header_template_part( string $block_content, array $block ): string {
	if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$slug  = isset( $attrs['slug'] ) ? sanitize_key( (string) $attrs['slug'] ) : '';
	if ( ! isset( musicwave_site_header_choices()[ $slug ] ) ) {
		return $block_content;
	}

	$wanted = musicwave_site_header_slug();
	if ( $wanted === $slug ) {
		return $block_content;
	}

	static $remapping = false;
	if ( $remapping ) {
		return $block_content;
	}

	$remapping = true;
	$html      = do_blocks( '<!-- wp:template-part {"slug":"' . $wanted . '","tagName":"header"} /-->' );
	$remapping = false;

	return is_string( $html ) ? $html : $block_content;
}
add_filter( 'render_block', 'musicwave_remap_header_template_part', 9, 2 );

/**
 * Customizer: one control applies the header to the whole site.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 * @return void
 */
function musicwave_register_header_customizer( $wp_customize ): void {
	if ( ! is_object( $wp_customize ) || ! method_exists( $wp_customize, 'add_section' ) ) {
		return;
	}

	$wp_customize->add_section(
		'musicwave_chrome',
		array(
			'title'       => __( 'سربرگ و پوسته استریم', 'musicwave' ),
			'priority'    => 30,
			'description' => __( 'چیدمان سراسری و موبایل. لینک‌های نوار را در ویرایشگر سایت → بخش‌های قالب → سربرگ (استریم) ویرایش کنید.', 'musicwave' ),
		)
	);

	$controls = array(
		'musicwave_site_header'        => array(
			'default'  => 'header-stream',
			'sanitize' => 'musicwave_sanitize_site_header',
			'label'    => __( 'سربرگ سراسری', 'musicwave' ),
			'type'     => 'select',
			'choices'  => musicwave_site_header_choices(),
		),
		'musicwave_site_header_mobile' => array(
			'default'     => 'drawer',
			'sanitize'    => 'musicwave_sanitize_mobile_header',
			'label'       => __( 'سربرگ موبایل', 'musicwave' ),
			'description' => __( 'منوی کناری همان mw-rail قابل ویرایش است. نوار پایین لوگو و نام سایت ندارد.', 'musicwave' ),
			'type'        => 'select',
			'choices'     => musicwave_mobile_header_choices(),
		),
		'musicwave_topbar_brand'       => array(
			'default'  => 'full',
			'sanitize' => 'musicwave_sanitize_topbar_brand',
			'label'    => __( 'برند نوار بالا', 'musicwave' ),
			'type'     => 'select',
			'choices'  => musicwave_topbar_brand_choices(),
		),
		'musicwave_rail_brand'         => array(
			'default'  => '1',
			'sanitize' => 'musicwave_sanitize_onoff',
			'label'    => __( 'لوگو و نام در نوار کناری دسکتاپ / منوی کشویی', 'musicwave' ),
			'type'     => 'checkbox',
		),
		'musicwave_show_search'        => array(
			'default'  => '1',
			'sanitize' => 'musicwave_sanitize_onoff',
			'label'    => __( 'نمایش جستجو', 'musicwave' ),
			'type'     => 'checkbox',
		),
		'musicwave_show_theme'         => array(
			'default'  => '1',
			'sanitize' => 'musicwave_sanitize_onoff',
			'label'    => __( 'نمایش تغییر پوسته', 'musicwave' ),
			'type'     => 'checkbox',
		),
		'musicwave_show_account'       => array(
			'default'  => '1',
			'sanitize' => 'musicwave_sanitize_onoff',
			'label'    => __( 'نمایش حساب کاربری', 'musicwave' ),
			'type'     => 'checkbox',
		),
		'musicwave_tabs_count'         => array(
			'default'  => '5',
			'sanitize' => 'musicwave_sanitize_tabs_count',
			'label'    => __( 'تعداد آیکون نوار پایین', 'musicwave' ),
			'type'     => 'select',
			'choices'  => array(
				'4' => '4',
				'5' => '5',
			),
		),
	);

	foreach ( $controls as $id => $control ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $control['default'],
				'sanitize_callback' => $control['sanitize'],
				'transport'         => 'refresh',
			)
		);

		$args = array(
			'type'    => $control['type'],
			'section' => 'musicwave_chrome',
			'label'   => $control['label'],
		);
		if ( isset( $control['choices'] ) ) {
			$args['choices'] = $control['choices'];
		}
		if ( isset( $control['description'] ) ) {
			$args['description'] = $control['description'];
		}
		$wp_customize->add_control( $id, $args );
	}
}
add_action( 'customize_register', 'musicwave_register_header_customizer' );

/**
 * Appearance submenu for merchants who skip Customizer.
 *
 * @return void
 */
function musicwave_register_header_admin_page(): void {
	add_theme_page(
		__( 'سربرگ MusicWave', 'musicwave' ),
		__( 'سربرگ MusicWave', 'musicwave' ),
		'edit_theme_options',
		'musicwave-site-header',
		'musicwave_render_header_admin_page'
	);
}
add_action( 'admin_menu', 'musicwave_register_header_admin_page' );

/**
 * Mark the singular release with its type so CSS can follow album / track / podcast mockups.
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function musicwave_release_body_class( array $classes ): array {
	if ( ! function_exists( 'is_singular' ) || ! is_singular( 'mw_release' ) ) {
		return $classes;
	}

	$classes[] = 'mw-is-release';
	$terms     = get_the_terms( (int) get_the_ID(), 'mw_release_type' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( is_object( $term ) && isset( $term->slug ) ) {
				$classes[] = 'mw-release-type-' . sanitize_html_class( (string) $term->slug );
			}
		}
	}

	return $classes;
}
add_filter( 'body_class', 'musicwave_release_body_class' );

/**
 * Persist chrome theme mods from the Appearance screen.
 *
 * @return void
 */
function musicwave_save_header_admin_mods(): void {
	if ( ! current_user_can( 'edit_theme_options' ) || ! check_admin_referer( 'musicwave_site_header' ) ) {
		return;
	}
	set_theme_mod( 'musicwave_site_header', musicwave_sanitize_site_header( isset( $_POST['musicwave_site_header'] ) && is_string( $_POST['musicwave_site_header'] ) ? sanitize_text_field( wp_unslash( $_POST['musicwave_site_header'] ) ) : '' ) );
	set_theme_mod( 'musicwave_site_header_mobile', musicwave_sanitize_mobile_header( isset( $_POST['musicwave_site_header_mobile'] ) && is_string( $_POST['musicwave_site_header_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['musicwave_site_header_mobile'] ) ) : 'drawer' ) );
	set_theme_mod( 'musicwave_topbar_brand', musicwave_sanitize_topbar_brand( isset( $_POST['musicwave_topbar_brand'] ) && is_string( $_POST['musicwave_topbar_brand'] ) ? sanitize_text_field( wp_unslash( $_POST['musicwave_topbar_brand'] ) ) : 'full' ) );
	set_theme_mod( 'musicwave_rail_brand', isset( $_POST['musicwave_rail_brand'] ) ? '1' : '0' );
	set_theme_mod( 'musicwave_show_search', isset( $_POST['musicwave_show_search'] ) ? '1' : '0' );
	set_theme_mod( 'musicwave_show_theme', isset( $_POST['musicwave_show_theme'] ) ? '1' : '0' );
	set_theme_mod( 'musicwave_show_account', isset( $_POST['musicwave_show_account'] ) ? '1' : '0' );
	set_theme_mod( 'musicwave_tabs_count', musicwave_sanitize_tabs_count( isset( $_POST['musicwave_tabs_count'] ) && is_string( $_POST['musicwave_tabs_count'] ) ? sanitize_text_field( wp_unslash( $_POST['musicwave_tabs_count'] ) ) : '5' ) );
}

/**
 * @return void
 */
function musicwave_render_header_admin_page(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	if ( isset( $_POST['musicwave_site_header'] ) && check_admin_referer( 'musicwave_site_header' ) ) {
		musicwave_save_header_admin_mods();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'سربرگ سراسری ذخیره شد.', 'musicwave' ) . '</p></div>';
	}

	$chrome = musicwave_header_chrome_attrs();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'سربرگ سراسری MusicWave', 'musicwave' ); ?></h1>
		<p><?php esc_html_e( 'لینک‌های نوار کناری را در نمایش → ویرایشگر → بخش‌های قالب → سربرگ (استریم) ویرایش کنید. این صفحه فقط چیدمان و نمایش برند/ابزارها را عوض می‌کند.', 'musicwave' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'musicwave_site_header' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="musicwave_site_header"><?php esc_html_e( 'مدل سربرگ', 'musicwave' ); ?></label></th>
					<td>
						<select name="musicwave_site_header" id="musicwave_site_header">
							<?php foreach ( musicwave_site_header_choices() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( musicwave_site_header_slug(), $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="musicwave_site_header_mobile"><?php esc_html_e( 'سربرگ موبایل', 'musicwave' ); ?></label></th>
					<td>
						<select name="musicwave_site_header_mobile" id="musicwave_site_header_mobile">
							<?php foreach ( musicwave_mobile_header_choices() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $chrome['data-mw-mobile-chrome'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="musicwave_topbar_brand"><?php esc_html_e( 'برند نوار بالا', 'musicwave' ); ?></label></th>
					<td>
						<select name="musicwave_topbar_brand" id="musicwave_topbar_brand">
							<?php foreach ( musicwave_topbar_brand_choices() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $chrome['data-mw-topbar-brand'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'نمایش', 'musicwave' ); ?></th>
					<td>
						<label><input type="checkbox" name="musicwave_rail_brand" value="1" <?php checked( $chrome['data-mw-rail-brand'], '1' ); ?>> <?php esc_html_e( 'لوگو و نام در نوار کناری (نه در نوار پایین موبایل)', 'musicwave' ); ?></label><br>
						<label><input type="checkbox" name="musicwave_show_search" value="1" <?php checked( $chrome['data-mw-show-search'], '1' ); ?>> <?php esc_html_e( 'جستجو', 'musicwave' ); ?></label><br>
						<label><input type="checkbox" name="musicwave_show_theme" value="1" <?php checked( $chrome['data-mw-show-theme'], '1' ); ?>> <?php esc_html_e( 'تغییر پوسته', 'musicwave' ); ?></label><br>
						<label><input type="checkbox" name="musicwave_show_account" value="1" <?php checked( $chrome['data-mw-show-account'], '1' ); ?>> <?php esc_html_e( 'حساب کاربری', 'musicwave' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="musicwave_tabs_count"><?php esc_html_e( 'آیکون‌های نوار پایین', 'musicwave' ); ?></label></th>
					<td>
						<select name="musicwave_tabs_count" id="musicwave_tabs_count">
							<option value="4" <?php selected( $chrome['data-mw-tabs-count'], '4' ); ?>>4</option>
							<option value="5" <?php selected( $chrome['data-mw-tabs-count'], '5' ); ?>>5</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'ذخیره سربرگ', 'musicwave' ) ); ?>
		</form>
	</div>
	<?php
}
