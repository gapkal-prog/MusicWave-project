<?php
/**
 * Title: Footer widget columns
 * Slug: musicwave/footer-widget-columns
 * Categories: musicwave, musicwave-widgets, musicwave-footers
 * Keywords: footer, widgets, columns, navigation, newsletter
 * Inserter: true
 * Block Types: core/template-part, core/group
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"full","className":"mw-footer-widgets-demo","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull mw-footer-widgets-demo" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|30"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":48} /-->
<!-- wp:site-title {"level":0,"fontSize":"large"} /-->
<!-- wp:paragraph {"className":"mw-muted","fontSize":"small"} -->
<p class="mw-muted has-small-font-size"><?php echo esc_html__( 'Your sound, beautifully organized. Edit this footer in Appearance → Editor → Template Parts → Footer widgets — no code needed.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:social-links {"iconColor":"text","iconColorValue":"#f7f8fb","size":"has-normal-icon-size","className":"is-style-logos-only"} -->
<ul class="wp-block-social-links has-normal-icon-size has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"#","service":"instagram"} /--><!-- wp:social-link {"url":"#","service":"spotify"} /--><!-- wp:social-link {"url":"#","service":"youtube"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:group --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Browse', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:list -->
<ul class="wp-block-list"><li><a href="#"><?php echo esc_html__( 'All releases', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'New music', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Playlists', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Artists', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Genres & moods', 'musicwave' ); ?></a></li></ul>
<!-- /wp:list --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Support', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:list -->
<ul class="wp-block-list"><li><a href="#"><?php echo esc_html__( 'My account', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Cart & checkout', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Help & FAQs', 'musicwave' ); ?></a></li><li><a href="#"><?php echo esc_html__( 'Contact', 'musicwave' ); ?></a></li></ul>
<!-- /wp:list --></div>
<!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Stay tuned', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php echo esc_html__( 'Drag any block here — search, newsletter, recent releases, categories, legacy widget, or custom HTML — it just works.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search releases","buttonText":"Search"} /-->
<!-- wp:separator {"opacity":"css","className":"is-style-wide"} -->
<hr class="wp-block-separator has-css-opacity is-style-wide"/>
<!-- /wp:separator --><!-- wp:paragraph {"className":"mw-muted","fontSize":"small"} -->
<p class="mw-muted has-small-font-size"><?php echo esc_html__( 'Tip: change colors, fonts, and spacing in Styles → no code required.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
