<?php
/**
 * Title: User music dashboard
 * Slug: musicwave/user-dashboard
 * Categories: musicwave, featured
 * Inserter: true
 *
 * @package MusicWave
 */

?>
<!-- wp:group {"align":"wide","className":"mw-dashboard-pattern","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mw-dashboard-pattern" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:group {"className":"mw-page-heading","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-page-heading"><!-- wp:paragraph {"textColor":"accent-strong","fontSize":"small"} -->
<p class="has-accent-strong-color has-text-color has-small-font-size"><?php echo esc_html__( 'YOUR MUSIC', 'musicwave' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size"><?php echo esc_html__( 'Everything you own, ready to play.', 'musicwave' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->
<!-- wp:music-wave/account-dashboard /--></div>
<!-- /wp:group -->
