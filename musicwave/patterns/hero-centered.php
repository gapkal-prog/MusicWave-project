<?php
/**
 * Title: معرفی مرکزی
 * Slug: musicwave/hero-centered
 * Categories: musicwave, musicwave-hero, featured
 * Keywords: hero, header, intro, centered, banner
 * Inserter: true
 * Block Types: core/group
 * Viewport Width: 1440
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"full","className":"mw-hero-centered","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","right":"var:preset|spacing|30","bottom":"var:preset|spacing|60","left":"var:preset|spacing|30"},"blockGap":"var:preset|spacing|40"}},"gradient":"accent-wash","layout":{"type":"constrained","contentSize":"840px","justifyContent":"center"}} -->
<div class="wp-block-group alignfull mw-hero-centered has-accent-wash-gradient-background has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"align":"center","style":{"typography":{"fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.1em"}},"textColor":"accent-strong","fontSize":"small"} -->
<p class="has-text-align-center has-accent-strong-color has-text-color has-small-font-size" style="font-weight:700;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'فروشگاهی با حال‌وهوای اتاق صفحه‌های موسیقی', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:heading {"textAlign":"center","level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'موسیقی با فضایی برای نفس کشیدن.', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"mw-muted","fontSize":"medium"} -->
<p class="has-text-align-center mw-muted has-medium-font-size"><?php echo esc_html__( 'این بخش معرفی را هرجا می‌خواهید قرار دهید؛ صفحهٔ اصلی، صفحهٔ فرود یا صفحه عریض. هر رنگ، فونت و فاصله‌ای در بخش سبک‌ها قابل ویرایش است.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/releases/"><?php echo esc_html__( 'ورود به کاتالوگ', 'musicwave' ); ?></a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/browse/"><?php echo esc_html__( 'مرور حال‌وهواها', 'musicwave' ); ?></a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- wp:search {"label":"جست‌وجو","showLabel":false,"placeholder":"یک هنرمند، آلبوم یا حال‌وهوا را جست‌وجو کنید…","buttonText":"جست‌وجو","buttonUseIcon":true} /--></div>
<!-- /wp:group -->
