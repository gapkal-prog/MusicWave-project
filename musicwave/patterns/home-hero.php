<?php
/**
 * Title: Music discovery hero
 * Slug: musicwave/home-hero
 * Categories: musicwave, featured
 * Inserter: true
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"wide","className":"mw-hero mw-surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|40","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mw-hero mw-surface" style="margin-top:var(--wp--preset--spacing--40);padding:var(--wp--preset--spacing--50) var(--wp--preset--spacing--40)"><!-- wp:paragraph {"style":{"typography":{"fontWeight":"700","textTransform":"uppercase","letterSpacing":"0.08em"}},"textColor":"accent-strong","fontSize":"small"} -->
<p class="has-accent-strong-color has-text-color has-small-font-size" style="font-weight:700;letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Your sound, beautifully organized', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:heading {"level":1,"fontSize":"x-large"} -->
<h1 class="wp-block-heading has-x-large-font-size"><?php echo esc_html__( 'Discover the next track you will love.', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted","fontSize":"large"} -->
<p class="mw-muted has-large-font-size"><?php echo esc_html__( 'Explore releases, artists, albums, and exclusive downloads in one focused experience.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#latest"><?php echo esc_html__( 'Explore music', 'musicwave' ); ?></a></div><!-- /wp:button --></div><!-- /wp:buttons --></div>
<!-- /wp:group -->
