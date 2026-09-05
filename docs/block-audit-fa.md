# گزارش ممیزی کامل بلوک‌های اختصاصی MusicWave

**تاریخ:** ۳۰ اوت ۲۰۲۶  
**محدوده:** `music-wave-core`، قالب `musicwave`، Site Editor، Template/Patternها، CSS و ترجمه‌های JavaScript

## نتیجهٔ اجرایی

در پروژه ۲۸ بلوک اختصاصی وجود دارد:

- ۲۴ بلوک Core با namespace رسمی `music-wave/*`
- ۴ بلوک Theme با namespace رسمی `music-wave/*`
- category همهٔ آن‌ها: `music-wave`
- API همهٔ metadataها: نسخهٔ ۳
- زبان پایهٔ metadata و متن‌های قابل‌نمایش: فارسی
- textdomain بلوک‌های Core: `music-wave-core`
- textdomain بلوک‌های Theme: `musicwave`

چهار بلوک Theme از namespace قدیمی `musicwave/*` به namespace canonical منتقل شدند:

| بلوک | namespace رسمی | PHP renderer | کنترل‌های Editor | وضعیت fallback/نمایش |
|---|---|---|---|---|
| ویترین انتشارها | `music-wave/release-shelf` | `musicwave_render_release_shelf` | پرس‌وجو، منبع، چیدمان، کارت، فراخوان اقدام و ظاهر | محدودسازی امن query، placeholder تصویر، حالت‌های grid/scroll/list/feature |
| اسلایدر انتشارها | `music-wave/release-slider` | `musicwave_render_release_slider` | محتوا، تعداد، رفتار اسلایدر و نمایش metadata | خروجی خالی برای نبود داده، placeholder تصویر، scroll-snap و reduced-motion |
| متن قالب | `music-wave/theme-text` | `musicwave_render_theme_text` | داخلی و مخفی از inserter؛ مناسب متن‌های پیش‌فرض قالب | کلید allow-list، tag allow-list و پاک‌سازی class |
| ترجیح نمایش | `music-wave/theme-toggle` | `musicwave_render_theme_toggle` | داخلی و مخفی از inserter | دکمهٔ semantic با `type`، `aria-label` و عنوان ترجمه‌شده |

## ماتریس کنترل و registration

### Core — ۲۴ بلوک

metadata هر بلوک در `music-wave-core/blocks/*/block.json` شامل `$schema`، `apiVersion`، نام canonical، عنوان، توضیح، category، icon، keyword فارسی، textdomain، attributes، supports و example ویرایشگر است. نام هر ۲۴ بلوک در هر دو registry زیر تطبیق داده شد:

1. `music-wave-core/src/Modules/Rendering.php` برای registration و metadata سمت PHP
2. `music-wave-core/assets/blocks.js` برای registration و Inspector سمت Editor

فهرست بلوک‌های بررسی‌شده:

- `access-panel`
- `account-dashboard`
- `add-to-playlist`
- `add-to-queue`
- `artist-profile`
- `artists-shelf`
- `catalog-filters`
- `catalog-results`
- `collection-list`
- `continue-listening`
- `download-button`
- `library-button`
- `membership-panel`
- `music-library`
- `playback-queue`
- `playlists`
- `preview-button`
- `preview-player`
- `public-playlists`
- `related-releases`
- `release-credits`
- `release-meta`
- `taxonomy-shelf`
- `term-hero`

attributeهای حساس بین renderer و editor نیز در بررسی‌های قبلی به‌صورت source-level تطبیق داده شده‌اند؛ از جمله گزینه‌های access/VIP، secure download، query/filter، card display، taxonomy، artist، credits، queue و preview.

### Theme — ۴ بلوک

`block.json` منبع واحد metadata، attributes و supports است. PHP فقط render callback را اضافه می‌کند و JS metadata را از PHP localization دریافت می‌کند؛ نقشهٔ موازی title/description/attribute وجود ندارد.

- `release-shelf` تمام گزینه‌های query و presentation خود را در metadata دارد و Inspector شامل منبع انتشار/فهرست‌های پخش عمومی، order، taxonomy، تعداد، layout، shape، کارت، CTA و feature settings است.
- `release-slider` با همان query contract مشترک shelf کار می‌کند و Inspector برای order، taxonomy، release ID، تعداد، autoplay، loop، pause، arrows، dots و metadata دارد.
- `theme-text` و `theme-toggle` بلوک‌های زیرساختی هستند و عمداً از inserter مخفی‌اند تا کاربر غیرفنی نمونهٔ اشتباه درج نکند.

## backward compatibility و Site Editor

تغییر نام مستقیم و حذف namespace قدیمی انجام نشده است. هنگام `init` برای هر چهار بلوک این aliasها ثبت می‌شوند:

- `musicwave/release-shelf`
- `musicwave/release-slider`
- `musicwave/theme-text`
- `musicwave/theme-toggle`

این aliasها:

- همان attributes، supports، title، description، keyword و render callback بلوک canonical را دارند؛
- با `supports.inserter = false` از درج محتوای جدید مخفی می‌شوند؛
- در `enqueue_block_editor_assets` نیز به‌صورت metadata در دسترس Editor هستند تا محتوای قدیمی unsupported نشود؛
- title، description و keyword آن‌ها با textdomain فعال ترجمه می‌شود.

`theme.json` هم settings/styles canonical را دارد و هم کلیدهای legacy لازم برای حفظ تنظیمات ظاهری محتوای قدیمی. Templateها و Patternهای bundled فقط از `wp:music-wave/*` استفاده می‌کنند. نامک Patternها مانند `musicwave/release-chart` تغییر نکرده، چون namespace Pattern است نه namespace Block.

## accessibility، RTL و responsive

- ساختار خروجی shelf/slider بر پایهٔ `section`، `header`، heading و لینک‌های واقعی است.
- کنترل‌های slider دکمهٔ واقعی، `aria-label`، وضعیت disabled و live status دارند.
- تصاویر decorative با `alt` خالی و لینک/heading قابل‌فهم همراه‌اند.
- نبود تصویر در shelf و slider به initial حرفیِ امن و decorative fallback تبدیل می‌شود.
- CSS shelf از container query، grid/rail/list و logical properties استفاده می‌کند.
- CSS slider در RTL جهت محتوای کارت را RTL نگه می‌دارد و scroll viewport را برای محاسبهٔ پایدار LTR می‌کند؛ محل دکمهٔ پخش با `inset-inline-end` تعریف شده است.
- `prefers-reduced-motion` برای shelf و slider پوشش داده شده است.
- اندازه‌ها و ستون‌ها bounded هستند تا attribute دست‌کاری‌شده layout را از بین نبرد.

## localization

- رشته‌های جدید و metadata قابل‌نمایش فارسی هستند؛ identifier، slug، taxonomy، option value و نامک‌های persistent تغییر نکرده‌اند.
- textdomain قالب و Core از هم جدا باقی مانده است.
- فایل‌های واقعی `en_US` برای PHP و JavaScript در مسیر languages وجود دارند و نام فایل‌های JSON بر اساس hash مسیر نسبی script تولید شده است.
- ابزار `tools/check-script-translations.php` وجود فایل، source path، JED catalog، locale header، نمونه ترجمه و plural را بررسی می‌کند.

## بازبینی و ارتقای Header و Navigation

- `navigation.css` بازنویسی شد و `position: sticky` اجباری از حالت پایهٔ هدر حذف شد.
- sticky شدن هدر اکنون فقط با `is-position-sticky` یا style معادل تولیدشده توسط WordPress فعال می‌شود؛ در نتیجه گزینهٔ Position در Site Editor واقعاً روشن/خاموش عمل می‌کند.
- `overflow: clip` از `.wp-site-blocks` حذف شد تا ancestor مانع sticky positioning نشود.
- منوی موبایل در حالت باز یک overlay تمام‌صفحه با `100dvh`، safe-area، قفل اسکرول، focus state، Escape/focus-trap داخلی WordPress، RTL و reduced-motion دارد.
- submenu visibility به منطق داخلی Navigation وردپرس واگذار شد و CSS دیگر همهٔ submenuها را به‌صورت اجباری باز نمی‌کند.
- قواعد تکراری overlay، close button و responsive navigation یکپارچه شدند.
- هدرهای معمولی، centered و minimal از همین قرارداد مشترک استفاده می‌کنند.

## تست‌های انجام‌شده در این محیط

موفق:

- parse همهٔ ۲۸ `block.json` و `theme.json`
- inventory namespace: ۲۴ Core + ۴ Theme، بدون duplicate
- parity استاتیک نام بلوک‌های Core در metadata/PHP/JS
- عدم وجود `wp:musicwave/*` در Template/Part/Patternهای bundled
- `npm run lint:js`
- `node --check` برای فایل‌های JavaScript مرتبط
- `git diff --check`

قابل اجرا نبود:

- PHP lint، `tests/template-integrity.php`، `tools/check-site-editor.php` و تست runtime WordPress؛ چون PHP CLI در sandbox موجود نیست.
- تست واقعی Gutenberg/Site Editor، ذخیره و reload overrideهای database، RTL بصری، WooCommerce، VIP، secure download و browser accessibility به WordPress runtime نیاز دارند.

در CI/محیط توسعهٔ دارای PHP این gateها باید اجرا شوند:

```bash
composer check:site-editor
php tests/template-integrity.php
composer test
composer check:script-translations
npm run lint:js
```
