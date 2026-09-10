# برنامهٔ ارتقای «WAVE» — طراحی تجاری استریم موسیقی

این سند نتیجهٔ تحلیل کامل کد پروژه (پوستهٔ بلوکی `musicwave`، افزونه‌های
`music-wave-core` و `music-wave-vip`) و پنج فایل مرجع طراحی در ریشهٔ مخزن است:

| فایل مرجع | صفحه | بخش‌هایی که به بلوک‌های پروژه نگاشت شدند |
| --- | --- | --- |
| `home_editorial_music_streaming.html` | خانه | هیروی سرمقاله‌ای (وینیل + جلد + بج طلایی)، «Jump Back In»، جدول «WAVE Top 100»، «New Releases»، «Pioneering Artists»، «Moods» |
| `wave_neo_seoul_soundscapes_single_album_vinyl_tracklist.html` | آلبوم | هیروی کارتی با وینیل، فهرست قطعات کارتی با ستون‌های #/عنوان/کیفیت/زمان و ردیف در حال پخش |
| `wave_midnight_city_lights_single_track_live_lyrics.html` | تک‌آهنگ | نوار اقدام (پخش درخشان + کپسول‌ها)، بج «24-bit / 96kHz» |
| `wave_search_browse_mobile.html` | جست‌وجو (موبایل) | بج‌های فرمت روی کارت، ردیف‌های قطعه با اکولایزر |
| `wave_neo_seoul_soundscapes_single_album_mobile.html` | آلبوم (موبایل) | چیدمان عمودی هیرو، ریل کارت‌های مرتبط |

## ۱. اصول اجرا

* **هر سبک جدید یک گزینهٔ قابل انتخاب است.** سبک‌های قدیمی (SonicStream) دست‌نخورده
  باقی می‌مانند و سبک‌های WAVE به‌صورت مقدار جدید یک attribute (`cardStyle`،
  `heroStyle`، `layout`) یا یک *block style* ثبت‌شده (`is-style-*`) اضافه می‌شوند.
* **قرارداد parity رعایت می‌شود** (`tests/template-integrity.php`): هر attribute جدید
  هم‌زمان در `block.json`، رندرکنندهٔ PHP، `Rendering.php::editor_blocks()` و
  `fieldConfig` در `assets/blocks.js` (یا `editor-blocks.js` در پوسته) تعریف می‌شود.
* **بدون منطق تجاری در پوسته.** داده‌هایی مثل برچسب کیفیت فایل (`FLAC / Hi-Res`) از
  افزونه و از طریق فیلتر `music_wave_release_badge` به پوسته می‌رسند؛ پوسته فقط
  نمایش می‌دهد.
* **یک پیاده‌سازی، چند مصرف‌کننده.** کارت WAVE یک modifier روی کرومِ مشترک
  `.mw-release-shelf` است؛ بنابراین قفسهٔ انتشارها (پوسته)، «انتشارهای مرتبط» و
  «ادامهٔ پخش» (افزونه) همگی از یک CSS استفاده می‌کنند.
* **توکن‌محور.** رنگ‌ها فقط از `--mw-color-*` خوانده می‌شوند تا هر سبک WAVE در
  همهٔ واریانت‌های رنگی (Obsidian، Aurora، …) و در حالت روشن/تاریک درست باشد.
* **RTL، دسترس‌پذیری و `prefers-reduced-motion`** در همهٔ افزوده‌ها رعایت شده است.

## ۲. توکن‌ها و واریانت رنگی

| مورد | مسیر | توضیح |
| --- | --- | --- |
| واریانت `WAVE Editorial` | `musicwave/styles/wave.json` | پالت مرجع: بوم `#0E0F12`، سطح `#16181D`، سطح برجسته `#1F222A`، متن `#F4F5F7`، متن کم‌رنگ `#9BA1B0`، تأکید سرخ `#FA2D65`، طلایی VIP `#E5B868`، بنفش `#6C5CE7` + نگاشت حالت روشن |
| توکن‌های جدید | `assets/css/tokens.css` | `--mw-color-premium` (طلایی) و `--mw-color-accent-alt` (بنفش) با override از `settings.custom` |
| primitives مشترک | `assets/css/utilities.css` | `.mw-badge` (بج فرمت)، `.mw-vinyl` (دیسک وینیل CSS-only)، `.mw-eyebrow`، `.mw-pill` |

## ۳. سبک‌های جدید به تفکیک بلوک

| بلوک | گزینهٔ جدید | مرجع | فایل‌های تغییر |
| --- | --- | --- | --- |
| `music-wave/release-shelf` (پوسته) | `cardStyle: wave` — کارت با سطح، بج فرمت، دکمهٔ پخش لغزنده، خط متای «نوع · سال» | Jump Back In / New Releases | `functions.php`, `blocks/release-shelf/block.json`, `editor-blocks.js`, `shelf.css` |
| `music-wave/release-shelf` | `layout: chart` — جدول رتبه‌بندی (رتبه/اکولایزر، جلد ۴۰px، عنوان+هنرمند، ژانر، زمان) | WAVE Top 100 | همان‌ها |
| `music-wave/release-shelf` | `heroStyle: editorial` برای چیدمان اسلایدر — کپسول «Editorial Spotlight»، عنوان دومخطی گرادیانی، نوار متا، وینیل پشت جلد با بج طلایی | Editorial Master Hero | `functions.php`, `hero-slider.css` |
| `music-wave/continue-listening` | `cardStyle: wave` | Jump Back In | `block.json`, `ListeningBlocks.php`, `Rendering.php`, `blocks.js` |
| `music-wave/related-releases` | `cardStyle: wave` | Related Albums | `block.json`, `ReleaseBlocks.php`, `Rendering.php`, `blocks.js` |
| `music-wave/artists-shelf` | `cardStyle: wave` — آواتار ۱۲۸px با حلقهٔ تأکیدی، «دنبال‌کردن» کپسولی | Pioneering Artists | `block.json`, `ArtistShelfBlock.php`, `Rendering.php`, `blocks.js`, `artists-shelf.css` |
| `music-wave/taxonomy-shelf` | `cardStyle: mood` — کارت ۱۲rem با گرادیان، اسکریم و بج نام | Moods & Sanctuaries | `block.json`, `TaxonomyShelfBlock.php`, `Rendering.php`, `blocks.js`, `taxonomy-shelf.css` |
| `music-wave/collection-list` | block style `wave` — فهرست قطعات کارتی، سرستون‌های #/عنوان/کیفیت/زمان، ردیف فعال با پس‌زمینهٔ تأکیدی | Tracklist آلبوم | `Rendering.php::register_block_styles`, `collections.css` |
| `music-wave/preview-player` | block style `glow` — دکمهٔ پخش درخشان | Play Album | `Rendering.php`, `catalog.css` |
| `core/group` | block styles `mw-hero-card` و `mw-hero-vinyl` — هیروی کارتی با هالهٔ رنگی و وینیل CSS پشت جلد | هیروی آلبوم | `functions.php::musicwave_register_block_styles`, `catalog.css` |

## ۴. قالب و الگو

* `templates/single-mw_release.html` — هیرو به سبک کارتی WAVE با وینیل، فهرست قطعات
  با سبک `wave`، انتشارهای مرتبط با کارت WAVE. همهٔ این‌ها با تغییر سبک از پنل
  «سبک‌ها» در ویرایشگر سایت قابل بازگشت‌اند.
* الگوی جدید `patterns/wave-home-layout.php` («صفحهٔ اصلی WAVE») که تمام بخش‌های
  مرجع خانه را با گزینه‌های جدید می‌چیند.

## ۵. کیفیت کد و تست

* فیکس تست پایهٔ شکسته در `tests/run.php` (کلاس چیدمان فرم درخواست).
* افزودن assertهای جدید به `tests/template-integrity.php` برای attributeها، سبک‌ها
  و selectorهای CSS جدید.
* رندر واقعی سبک‌های WAVE در `tests/run.php` (قفسهٔ هنرمندان، قفسهٔ حال‌وهوا).
* به‌روزرسانی کاتالوگ‌های ترجمهٔ اسکریپت (`.po` + JSON هش‌دار) برای رشته‌های تازه.
* دروازه‌ها: `php tests/run.php`، `php tools/check-site-editor.php`،
  `php tools/check-templates.php`، `php tools/check-script-translations.php`،
  `npx eslint`.

## ۶. وضعیت اجرا

| گام | وضعیت | خلاصه |
| --- | --- | --- |
| ۱. توکن‌ها و واریانت | ✅ | `--mw-color-premium/on-premium/accent-alt/accent-dim` در `tokens.css`، primitiveهای `.mw-badge/.mw-eyebrow/.mw-pill/.mw-ping/.mw-vinyl` در `utilities.css`، واریانت `styles/wave.json` |
| ۲. قفسهٔ انتشار (پوسته) | ✅ | چیدمان `chart`، سبک کارت `wave`، سبک هیرو `editorial`، سوییچ‌های `showBadge/showMeta`؛ همه در Inspector |
| ۳. بلوک‌های هسته | ✅ | `ReleaseBadge` مشترک (Hi-Res/FLAC + «نوع · سال»)، کارت WAVE در `related-releases` و `continue-listening`، کاشی WAVE در `artists-shelf`، کاشی `mood` در `taxonomy-shelf` |
| ۴. سبک‌های بلوک | ✅ | `preview-player` → «درخشان (WAVE)»، `collection-list` → «WAVE (کارت جدولی)»، `core/group` → «هیرو کارتی» و «هیرو کارتی با وینیل» |
| ۵. قالب و الگو | ✅ | `single-mw_release.html` روی سبک‌های WAVE؛ الگوی `wave-home-layout` |
| ۶. تست و ترجمه | ✅ | assertهای جدید در `tests/run.php` و `tests/template-integrity.php`؛ رشته‌های تازه در `.po/.mo` و JSONهای هش‌دار |

رفع باگ جانبی: نقطه‌های ناوبری اسلایدر هیرو یک `"` اضافه در attribute تولید می‌کردند
(`aria-current="true""`) که اصلاح شد.
