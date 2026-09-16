<?php
/**
 * Title: عنوان بخش
 * Slug: musicwave/section-heading
 * Categories: musicwave, featured, musicwave-widgets
 * Keywords: heading, section, title, eyebrow
 * Inserter: true
 * Block Types: core/group, core/heading, core/paragraph
 * Viewport Width: 1440
 *
 * A plain, ready-to-type heading: one wide Group holding an eyebrow paragraph,
 * an h2 and a muted paragraph, styled entirely through Global Styles (palette
 * colours plus inline typography). It inserts copy an editor replaces
 * immediately and leaves no component behind.
 *
 * Deliberately not the same thing as the `music-wave/section-head` block, which
 * wraps its children in the theme's editorial `.mw-section-head` shell and
 * exposes the centered / stacked / inverted looks in the Styles panel. Use this
 * pattern for a one-off text heading inside content; use the block when a
 * section needs the magazine header vocabulary. Both ship on purpose and
 * neither replaces the other.
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"constrained","contentSize":"760px","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.08em"}},"textColor":"accent-strong","fontSize":"small"} -->
<p class="has-accent-strong-color has-text-color has-small-font-size" style="font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'منتخب', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:heading {"level":2,"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size"><?php echo esc_html__( 'عنوان بخش اینجا قرار می‌گیرد', 'musicwave' ); ?></h2>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'توضیح کوتاهی برای معرفی بخش. برچسب بالایی، عنوان، رنگ متن، فاصله‌ها و تایپوگرافی را بدون کدنویسی در ویرایشگر سایت تغییر دهید.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
