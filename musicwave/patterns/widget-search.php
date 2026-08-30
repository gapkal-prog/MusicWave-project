<?php
/**
 * Title: Widget — Search and categories
 * Slug: musicwave/widget-search
 * Categories: musicwave-widgets, musicwave
 * Keywords: widget, search, categories, sidebar
 * Inserter: true
 * Block Types: core/search, core/categories
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"className":"mw-widget mw-widget--search","style":{"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"var:preset|spacing|30","right":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30"}},"border":{"radius":"1rem","width":"1px"}},"borderColor":"muted","backgroundColor":"surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-widget mw-widget--search has-border-color has-muted-border-color has-surface-background-color has-background" style="border-radius:1rem;border-width:1px;padding:var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Explore the catalog', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search releases, artists…","buttonText":"Search"} /-->
<!-- wp:separator {"opacity":"css"} -->
<hr class="wp-block-separator has-css-opacity"/>
<!-- /wp:separator --><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Categories', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:categories {"showPostCounts":true} /-->
<!-- wp:paragraph {"className":"mw-muted","fontSize":"small"} -->
<p class="mw-muted has-small-font-size"><?php echo esc_html__( 'Works with any block — replace categories with tag cloud, latest releases, or a legacy widget. No code needed.', 'musicwave' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
