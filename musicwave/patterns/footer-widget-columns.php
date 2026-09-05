<?php
/**
 * Title: ستون‌های ابزارک پابرگ
 * Slug: musicwave/footer-widget-columns
 * Categories: musicwave, musicwave-widgets, musicwave-footers
 * Keywords: footer, widgets, columns, navigation, newsletter
 * Inserter: true
 * Block Types: core/template-part, core/group
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"full","className":"mw-footer-widgets-demo","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull mw-footer-widgets-demo" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|30"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":48} /-->
<!-- wp:site-title {"level":0,"fontSize":"large"} /-->
<!-- wp:paragraph {"className":"mw-muted","fontSize":"small"} -->
<p class="mw-muted has-small-font-size"><?php echo esc_html__( 'صدای شما، به‌زیبایی سازمان‌یافته. این پابرگ را از نمایش → ویرایشگر → بخش‌های قالب → ابزارک‌های پابرگ ویرایش کنید؛ بدون نیاز به کدنویسی.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:social-links {"iconColor":"text","iconColorValue":"var(--wp--preset--color--text)","size":"has-normal-icon-size","className":"is-style-logos-only"} -->
<ul class="wp-block-social-links has-normal-icon-size has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"#","service":"instagram"} /--><!-- wp:social-link {"url":"#","service":"spotify"} /--><!-- wp:social-link {"url":"#","service":"youtube"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:group --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'مرور', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:list -->
<ul class="wp-block-list"><li><a href="#"><?php echo esc_html__( 'همهٔ انتشارها', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'موسیقی جدید', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'فهرست‌های پخش', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'هنرمندان', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'سبک‌ها و حال‌وهواها', 'musicwave' ); ?></a></li></ul>
<!-- /wp:list --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'پشتیبانی', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:list -->
<ul class="wp-block-list"><li><a href="#"><?php echo esc_html__( 'حساب کاربری من', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'سبد خرید و تسویه‌حساب', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'راهنما و پرسش‌های متداول', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'تماس', 'musicwave' ); ?></a></li></ul>
<!-- /wp:list --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'همراه بمانید', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php echo esc_html__( 'هر بلوکی را اینجا بکشید و رها کنید؛ جست‌وجو، خبرنامه، انتشارهای جدید، دسته‌بندی‌ها، ابزارک قدیمی یا HTML سفارشی، همه به‌سادگی کار می‌کنند.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:search {"label":"جست‌وجو","showLabel":false,"placeholder":"جست‌وجوی انتشارها","buttonText":"جست‌وجو"} /-->
<!-- wp:separator {"opacity":"css","className":"is-style-wide"} -->
<hr class="wp-block-separator has-css-opacity is-style-wide"/>
<!-- /wp:separator --><!-- wp:paragraph {"className":"mw-muted","fontSize":"small"} -->
<p class="mw-muted has-small-font-size"><?php echo esc_html__( 'نکته: رنگ‌ها، فونت‌ها و فاصله‌ها را در سبک‌ها تغییر دهید؛ بدون نیاز به کدنویسی.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
