<?php
/**
 * Title: ویترین وینیل با سرصفحهٔ سرمقاله‌ای
 * Slug: musicwave/vinyl-record-shelf
 * Categories: musicwave, musicwave-shelves, featured
 * Keywords: vinyl, record, editorial, shelf, spotlight
 * Inserter: true
 *
 * The editorial header uses the theme's magazine vocabulary
 * (`mw-section-head` + `mw-eyebrow`), and the shelf ships the vinyl look so
 * the record-store presentation is one click away. Every colour comes from
 * the Site Editor palette, so the pattern follows the active style variation
 * and both colour schemes.
 *
 * @package MusicWave
 */

?>
<!-- wp:music-wave/section-head {"align":"wide"} -->
<div class="wp-block-music-wave-section-head alignwide mw-section-head">
	<!-- wp:group {"className":"mw-section-head__text","layout":{"type":"default"}} -->
	<div class="wp-block-group mw-section-head__text">
		<!-- wp:paragraph {"className":"mw-eyebrow"} -->
		<p class="mw-eyebrow"><?php echo esc_html__( 'قفسهٔ صفحه‌ها', 'musicwave' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'وینیل‌هایی که این هفته گذاشته‌ایم', 'musicwave' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'غلاف را ورق بزنید: روی هر کارت، صفحه با هاور از داخل غلاف بیرون می‌آید.', 'musicwave' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:music-wave/section-head -->

<!-- wp:music-wave/release-shelf {"className":"is-style-vinyl","align":"wide","eyebrow":"","title":"","orderBy":"rand","itemsToShow":8,"columns":4,"layout":"grid","showAction":false} /-->
