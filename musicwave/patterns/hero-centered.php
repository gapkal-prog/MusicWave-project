<?php
/**
 * Title: Centered hero
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
<p class="has-text-align-center has-accent-strong-color has-text-color has-small-font-size" style="font-weight:700;letter-spacing:0.1em;text-transform:uppercase"><?php echo esc_html__( 'A store that feels like a record room', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:heading {"textAlign":"center","level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size"><?php echo esc_html__( 'Music with room to breathe.', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"align":"center","className":"mw-muted","fontSize":"medium"} -->
<p class="has-text-align-center mw-muted has-medium-font-size"><?php echo esc_html__( 'Drop this hero anywhere — front page, landing, or page-wide. Every color, font, and spacing is editable in Styles.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/releases/"><?php echo esc_html__( 'Enter catalog', 'musicwave' ); ?></a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/browse/"><?php echo esc_html__( 'Browse moods', 'musicwave' ); ?></a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Try an artist, album, or mood…","buttonText":"Search","buttonUseIcon":true} /--></div>
<!-- /wp:group -->
