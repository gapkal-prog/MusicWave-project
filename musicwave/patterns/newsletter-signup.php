<?php
/**
 * Title: عضویت در خبرنامه
 * Slug: musicwave/newsletter-signup
 * Categories: musicwave, musicwave-widgets, musicwave-cta
 * Keywords: newsletter, subscribe, form, email
 * Inserter: true
 * Block Types: core/group, core/heading
 * Viewport Width: 800
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"wide","className":"mw-newsletter","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"gradient":"midnight-radio","layout":{"type":"constrained","contentSize":"640px","justifyContent":"center"}} -->
<div class="wp-block-group alignwide mw-newsletter has-midnight-radio-gradient-background has-background" style="padding:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'در جریان بمانید', 'musicwave' ); ?></h2>
<!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"mw-muted"} -->
<p class="has-text-align-center mw-muted"><?php echo esc_html__( 'انتشارهای جدید و انتخاب‌های سرمقاله‌ای را دریافت کنید. این بخش را با بلوک خبرنامه، فرم یا ابزارک قدیمی خود جایگزین کنید؛ بدون نیاز به کدنویسی.', 'musicwave' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:search {"label":"ایمیل","showLabel":false,"placeholder":"ایمیل خود را وارد کنید","buttonText":"عضویت"} /-->
<!-- wp:paragraph {"align":"center","fontSize":"x-small","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color has-x-small-font-size"><?php echo esc_html__( 'نکته: بلوک جست‌وجوی بالا را با بلوک Mailchimp، Jetpack یا HTML جایگزین کنید. رنگ‌ها از سبک‌های کلی پیروی می‌کنند.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
