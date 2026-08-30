<?php
/**
 * Title: Rich widget sidebar
 * Slug: musicwave/widget-rich-sidebar
 * Categories: musicwave, musicwave-widgets
 * Keywords: sidebar, widgets, recent, categories, archive
 * Inserter: true
 * Block Types: core/columns
 * Viewport Width: 1440
 *
 * @package MusicWave
 */
?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html__( 'Your page content', 'musicwave' ); ?></h1>
<!-- /wp:heading --><!-- wp:paragraph {"className":"mw-muted"} -->
<p class="mw-muted"><?php echo esc_html__( 'Main column — add paragraphs, images, releases, or any block. The sidebar on the right is fully editable piece-by-piece.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --><!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:paragraph -->
<p><?php echo esc_html__( 'Tip for buyers: open Site Editor → Template Parts → Sidebar to change widgets globally, or detach this columns block to edit the sidebar only on this page.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->
<!-- wp:column {"width":"33.33%","className":"mw-sidebar-column"} -->
<div class="wp-block-column mw-sidebar-column" style="flex-basis:33.33%"><!-- wp:group {"className":"mw-sidebar mw-sidebar--pattern","style":{"spacing":{"blockGap":"var:preset|spacing|30","padding":{"top":"var:preset|spacing|30","right":"var:preset|spacing|20","bottom":"var:preset|spacing|30","left":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-sidebar mw-sidebar--pattern" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--20)"><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Popular', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:musicwave/release-shelf {"eyebrow":"","title":"","itemsToShow":4,"columns":1,"layout":"list","showArtist":true,"showAction":false} /-->
<!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Categories', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:categories {"showHierarchy":true,"showPostCounts":true} /-->
<!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:heading {"level":3,"fontSize":"medium","style":{"typography":{"fontWeight":"800","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h3 class="wp-block-heading has-medium-font-size" style="letter-spacing:0.08em;text-transform:uppercase"><?php echo esc_html__( 'Archives', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:archives {"showPostCount":true} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
