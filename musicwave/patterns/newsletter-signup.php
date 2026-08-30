<?php
/**
 * Title: Newsletter signup
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
<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Stay in the loop', 'musicwave' ); ?></h2>
<!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"mw-muted"} -->
<p class="has-text-align-center mw-muted"><?php echo esc_html__( 'Get new releases and editorial picks. Replace this with your newsletter block, form, or legacy widget — no code needed.', 'musicwave' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:search {"label":"Email","showLabel":false,"placeholder":"Enter your email","buttonText":"Subscribe"} /-->
<!-- wp:paragraph {"align":"center","fontSize":"x-small","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color has-x-small-font-size"><?php echo esc_html__( 'Tip: Swap the Search block above with a Mailchimp, Jetpack, or HTML block. Colors follow global Styles.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
