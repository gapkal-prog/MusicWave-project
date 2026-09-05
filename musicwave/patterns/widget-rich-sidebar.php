<?php
/**
 * Title: نوار کناری ابزارک‌های کامل
 * Slug: musicwave/widget-rich-sidebar
 * Categories: musicwave, musicwave-widgets
 * Keywords: sidebar, widgets, recent, categories, archive
 * Inserter: true
 * Block Types: core/columns
 * Viewport Width: 1440
 *
 * @package MusicWave
 */
?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'محتوای صفحه شما', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'ستون اصلی؛ پاراگراف، تصویر، انتشار یا هر بلوکی را اضافه کنید. نوار کناری سمت راست به‌صورت جداگانه کاملاً قابل ویرایش است.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:paragraph -->
<p><?php echo esc_html__( 'نکته برای خریداران: برای تغییر کلی ابزارک‌ها به ویرایشگر سایت → بخش‌های قالب → نوار کناری بروید، یا اتصال این بلوک ستون‌ها را جدا کنید تا نوار کناری فقط در همین صفحه ویرایش شود.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<!-- wp:column {"width":"33.33%","className":"mw-sidebar-column"} -->
<div class="wp-block-column mw-sidebar-column" style="flex-basis:33.33%"><!-- wp:group {"className":"mw-sidebar mw-sidebar--pattern","style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"var:preset|spacing|30","right":"var:preset|spacing|20","bottom":"var:preset|spacing|30","left":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-sidebar mw-sidebar--pattern" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--20)"><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'محبوب', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:music-wave/release-shelf {"eyebrow":"","title":"","itemsToShow":4,"columns":1,"layout":"list","showArtist":true,"showAction":false} /-->
<!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'دسته‌بندی‌ها', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:categories {"showHierarchy":true,"showPostCounts":true} /-->
<!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'بایگانی', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:archives {"showPostCount":true} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
