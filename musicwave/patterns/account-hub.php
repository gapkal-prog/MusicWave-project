<?php
/**
 * Title: Account hub
 * Slug: musicwave/account-hub
 * Categories: musicwave
 * Inserter: true
 *
 * @package MusicWave
 */

?>
<!-- wp:group {"className":"mw-dashboard-page","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-dashboard-page"><!-- wp:group {"className":"mw-page-heading","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-page-heading"><!-- wp:paragraph {"textColor":"accent-strong","fontSize":"small"} -->
<p class="has-accent-strong-color has-text-color has-small-font-size"><?php echo esc_html__( 'YOUR MUSIC', 'musicwave' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:post-title {"level":1} /-->
<!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'Your purchases, personal music library, and account tools in one place.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:music-wave/account-dashboard /-->
<!-- wp:music-wave/music-library /-->
<!-- wp:post-content /-->
<!-- wp:separator {"align":"wide","className":"is-style-wide"} -->
<hr class="wp-block-separator alignwide has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->
<!-- wp:shortcode -->
<div class="wp-block-shortcode">[woocommerce_my_account]</div>
<!-- /wp:shortcode -->
<!-- wp:musicwave/release-shelf {"eyebrow":"DISCOVER","title":"Recommended next","orderBy":"rand","itemsToShow":8,"columns":4,"layout":"scroll","showAction":false} /--></div>
<!-- /wp:group -->
