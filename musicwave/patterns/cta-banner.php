<?php
/**
 * Title: بنر فراخوان اقدام
 * Slug: musicwave/cta-banner
 * Categories: musicwave, musicwave-cta, featured
 * Keywords: cta, banner, button, call to action
 * Inserter: true
 * Block Types: core/group, core/buttons
 * Viewport Width: 1440
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"wide","className":"mw-cta-banner mw-surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group alignwide mw-cta-banner mw-surface" style="padding:var(--wp--preset--spacing--40)"><!-- wp:group {"style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"constrained","contentSize":"480px","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'امروز شنیدن را شروع کنید', 'musicwave' ); ?></h2>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'کاتالوگ خود را مدیریت کنید، انتشارها را به‌اشتراک بگذارید و با اطمینان بفروشید؛ همه‌چیز از رابط بصری قابل ویرایش است.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/browse/"><?php echo esc_html__( 'مرور کاتالوگ', 'musicwave' ); ?></a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/my-account/"><?php echo esc_html__( 'حساب کاربری من', 'musicwave' ); ?></a></div><!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
