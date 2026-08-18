<?php
/**
 * Title: Browse music categories
 * Slug: musicwave/browse-categories
 * Categories: musicwave
 * Inserter: true
 *
 * @package MusicWave
 */
?>
<!-- wp:group {"align":"wide","className":"mw-browse-categories","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mw-browse-categories"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'Browse by mood and genre', 'musicwave' ); ?></h2>
<!-- /wp:heading --><!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|20"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"gradient":"editorial-rose","className":"mw-category-tile","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|30","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-category-tile has-editorial-rose-gradient-background has-background" style="padding:var(--wp--preset--spacing--40) var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html__( 'New music', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:paragraph --><p><?php echo esc_html__( 'Fresh releases and emerging sounds.', 'musicwave' ); ?></p><!-- /wp:paragraph --></div>
<!-- /wp:group --></div><!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"gradient":"midnight-radio","className":"mw-category-tile","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|30","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-category-tile has-midnight-radio-gradient-background has-background" style="padding:var(--wp--preset--spacing--40) var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html__( 'Radio and mixes', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:paragraph --><p><?php echo esc_html__( 'Continuous listening for every moment.', 'musicwave' ); ?></p><!-- /wp:paragraph --></div>
<!-- /wp:group --></div><!-- /wp:column --><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"backgroundColor":"surface-raised","className":"mw-category-tile","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|30","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mw-category-tile has-surface-raised-background-color has-background" style="padding:var(--wp--preset--spacing--40) var(--wp--preset--spacing--30)"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php echo esc_html__( 'Focus and chill', 'musicwave' ); ?></h3>
<!-- /wp:heading --><!-- wp:paragraph --><p><?php echo esc_html__( 'Calm selections for work and rest.', 'musicwave' ); ?></p><!-- /wp:paragraph --></div>
<!-- /wp:group --></div><!-- /wp:column --></div><!-- /wp:columns --></div>
<!-- /wp:group -->
