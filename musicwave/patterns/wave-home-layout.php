<?php
/**
 * Title: صفحهٔ خانهٔ WAVE (سرمقالهٔ استریم)
 * Slug: musicwave/wave-home-layout
 * Categories: musicwave, featured
 * Inserter: true
 * Description: بازسازی صفحهٔ خانهٔ مرجع WAVE با بلوک‌های موجود — هیرو سرمقاله با وینیل، «ادامهٔ پخش» با کارت‌های WAVE، چارت برترین‌ها، انتشارهای تازه، هنرمندان و حال‌وهواها. همهٔ سبک‌ها از تنظیمات هر بلوک قابل تغییر هستند.
 *
 * @package MusicWave
 */
?>
<!-- wp:music-wave/release-shelf {"eyebrow":"منتخب سرمقاله","title":"","orderBy":"date","itemsToShow":4,"layout":"slider","heroStyle":"editorial","showExcerpt":true,"showBadge":true,"showMeta":true,"actionLabel":"افزودن به کتابخانه","autoplay":true,"interval":7000} /-->
<!-- wp:music-wave/continue-listening {"heading":"ادامهٔ پخش","layout":"grid","columns":6,"cardStyle":"wave","showBadge":true,"showMeta":true,"showWhen":false} /-->
<!-- wp:columns {"align":"wide","className":"mw-wave-home__chart-row","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-columns alignwide mw-wave-home__chart-row"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:music-wave/release-shelf {"eyebrow":"پرشنونده","title":"۱۰۰ برتر WAVE","description":"برترین‌های این هفته بر پایهٔ شنیده‌ها.","orderBy":"views","itemsToShow":8,"layout":"chart","showArtist":true,"showBadge":true,"showAction":false} /--></div>
<!-- /wp:column -->
<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:pattern {"slug":"musicwave/music-service-callout"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:music-wave/release-shelf {"eyebrow":"تازه‌ها","title":"انتشارهای جدید","orderBy":"date","itemsToShow":4,"columns":4,"layout":"grid","cardStyle":"wave","showBadge":true,"showMeta":true,"showArtist":true,"showAction":false} /-->
<!-- wp:music-wave/artists-shelf {"align":"wide","eyebrow":"صداها","heading":"هنرمندان برگزیده","description":"صداهای پشت کاتالوگ را دنبال کنید.","layout":"grid","columns":6,"itemsToShow":6,"imageShape":"circle","cardStyle":"wave","showBio":false} /-->
<!-- wp:music-wave/taxonomy-shelf {"align":"wide","eyebrow":"ایستگاه‌های اتمسفریک","heading":"حال‌وهواها و پناهگاه‌ها","taxonomy":"mw_mood","orderBy":"count","layout":"grid","columns":4,"itemsToShow":4,"cardStyle":"mood","showCount":true} /-->
