<?php
/**
 * Title: فراخوان درخواست آهنگ و همکاری
 * Slug: musicwave/request-cta
 * Categories: musicwave, musicwave-cta, featured
 * Keywords: request, collaboration, سفارش, همکاری, cta
 * Inserter: true
 * Block Types: core/group, music-wave/request-form
 * Viewport Width: 1440
 *
 * Editorial chrome (heading, highlights, steps, privacy) is core blocks on
 * the same `.mw-request-form__*` classes the PHP renderer uses. The form
 * itself stays a self-closing `music-wave/request-form` with heading/side
 * chrome off, so POST, nonce, honeypot and kinds never move to JavaScript.
 *
 * @package MusicWave
 */

?>
<!-- wp:group {"align":"wide","className":"mw-request-cta","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mw-request-cta" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)"><!-- wp:group {"className":"mw-request-form mw-request-form--split","layout":{"type":"default"}} -->
<div class="wp-block-group mw-request-form mw-request-form--split"><!-- wp:group {"className":"mw-request-form__header","layout":{"type":"default"}} -->
<div class="wp-block-group mw-request-form__header"><!-- wp:paragraph {"className":"mw-request-form__eyebrow"} -->
<p class="mw-request-form__eyebrow"><?php echo esc_html__( 'سفارش و همکاری', 'musicwave' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"mw-request-form__heading"} -->
<h2 class="wp-block-heading mw-request-form__heading"><?php echo esc_html__( 'آهنگ اختصاصی می‌خواهید یا ایدهٔ همکاری دارید؟', 'musicwave' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"mw-request-form__intro"} -->
<p class="mw-request-form__intro"><?php echo esc_html__( 'خواننده‌ها، تهیه‌کننده‌ها، برندها و حتی شنونده‌ها می‌توانند از همین‌جا درخواست بدهند. فرم را پر کنید؛ ما آن را می‌خوانیم و با پیشنهاد و زمان‌بندی پاسخ می‌دهیم.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:group {"className":"mw-request-form__layout","layout":{"type":"default"}} -->
<div class="wp-block-group mw-request-form__layout"><!-- wp:group {"className":"mw-request-form__side","layout":{"type":"default"}} -->
<div class="wp-block-group mw-request-form__side"><!-- wp:list {"className":"mw-request-form__highlights"} -->
<ul class="wp-block-list mw-request-form__highlights"><li><strong><?php echo esc_html__( '♪ آهنگ اختصاصی', 'musicwave' ); ?></strong> <?php echo esc_html__( 'ترانه، ملودی و تنظیم برای شما یا برندتان؛ از ایده تا مسترینگ.', 'musicwave' ); ?></li><li><strong><?php echo esc_html__( '✦ همکاری هنری', 'musicwave' ); ?></strong> <?php echo esc_html__( 'فیچرینگ، تولید مشترک، ریمیکس یا اجرای زنده با هنرمندان ما.', 'musicwave' ); ?></li><li><strong><?php echo esc_html__( '◎ تبلیغات و رویداد', 'musicwave' ); ?></strong> <?php echo esc_html__( 'موسیقی تبلیغاتی، جینگل، ساند‌برندینگ و برنامهٔ رویدادها.', 'musicwave' ); ?></li></ul>
<!-- /wp:list -->
<!-- wp:list {"ordered":true,"className":"mw-request-form__steps"} -->
<ol class="wp-block-list mw-request-form__steps"><li><?php echo esc_html__( 'فرم را پر کنید؛ کمتر از دو دقیقه طول می‌کشد.', 'musicwave' ); ?></li><li><?php echo esc_html__( 'ایمیل تأیید با شمارهٔ پیگیری دریافت می‌کنید.', 'musicwave' ); ?></li><li><?php echo esc_html__( 'تیم ما بررسی می‌کند و با پیشنهاد و زمان‌بندی پاسخ می‌دهد.', 'musicwave' ); ?></li></ol>
<!-- /wp:list -->
<!-- wp:paragraph {"className":"mw-request-form__privacy"} -->
<p class="mw-request-form__privacy"><?php echo esc_html__( 'اطلاعات تماس شما فقط برای پاسخ به همین درخواست استفاده می‌شود.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:music-wave/request-form {"layout":"split","showHeading":false,"showSteps":false,"showHighlights":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
