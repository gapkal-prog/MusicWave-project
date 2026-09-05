<?php
/**
 * Title: محتوا با نوار کناری
 * Slug: musicwave/sidebar-content
 * Categories: musicwave, musicwave-widgets
 * Keywords: sidebar, widgets, layout, two columns
 * Inserter: true
 * Block Types: core/columns, core/template-part
 * Viewport Width: 1440
 *
 * @package MusicWave
 */
?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"68%"} -->
<div class="wp-block-column" style="flex-basis:68%"><!-- wp:group {"tagName":"main","className":"mw-content-area","layout":{"type":"constrained"}} -->
<main class="wp-block-group mw-content-area"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'عنوان صفحه', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'نوشتن را شروع کنید. در ویرایشگر سایت می‌توانید هر بلوکی را اینجا اضافه، جابه‌جا یا حذف کنید و نوار کناری را جداگانه از بخش‌های قالب → نوار کناری ویرایش کنید.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:paragraph -->
<p><?php echo esc_html__( 'این چیدمان دو ستونه از بخش قالب قابل استفادهٔ مجدد «نوار کناری» استفاده می‌کند. آن را یک‌بار به‌روزرسانی کنید تا تغییرات فوراً در همه صفحات این الگو دیده شود؛ مناسب برای صاحبان فروشگاه بدون دانش فنی.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></main>
<!-- /wp:group --></div>
<!-- /wp:column --><!-- wp:column {"width":"32%","className":"mw-sidebar-column"} -->
<div class="wp-block-column mw-sidebar-column" style="flex-basis:32%"><!-- wp:template-part {"slug":"sidebar"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
