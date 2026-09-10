<?php
/**
 * Title: نمایش پادکست (فصل‌بندی قسمت‌ها)
 * Slug: musicwave/podcast-show
 * Categories: musicwave
 * Inserter: true
 *
 * @package MusicWave
 */

?>
<!-- wp:group {"align":"wide","className":"mw-podcast-show","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mw-podcast-show"><!-- wp:music-wave/collection-list {"align":"wide","heading":"<?php echo esc_attr__( 'قسمت‌ها', 'musicwave' ); ?>","showHeading":true,"showPosition":true,"showArtwork":true,"showDuration":true,"showTotalDuration":true,"showPreview":true,"showDownload":true,"groupByDisc":true,"showQuality":true,"showBadges":true,"className":"is-style-cinematic"} /-->
<!-- wp:music-wave/release-credits {"layout":"list","showRole":true,"groupByRole":true} /-->
<!-- wp:music-wave/related-releases {"similarHeading":"<?php echo esc_attr__( 'پادکست‌های مشابه', 'musicwave' ); ?>","layout":"scroll","showBadges":true,"className":"is-style-editorial"} /--></div>
<!-- /wp:group -->
