# تحلیل و برنامهٔ ارتقای رابط کاربری MusicWave (سربرگ، حالت روشن، پخش‌کنندهٔ سراسری)

**تاریخ:** ۵ سپتامبر ۲۰۲۶  
**commit مبنا:** `5681995` (`main`)  
**دامنه:** قالب `musicwave` و افزونهٔ `music-wave-core` (بخش پخش‌کنندهٔ سراسری)  
**مراجع فنی بررسی‌شده:** سورس بلوک‌های `core/navigation` و `core/search` (Gutenberg trunk)، پشتیبانی `position` در `wordpress-develop`، `design-reference.html` طرح SonicStream، تست‌های `tests/template-integrity.php` و ابزارهای `tools/check-*.php`.

---

## ۱. تحلیل وضعیت موجود (یافته‌های تأییدشده از کد)

### ۱.۱ حالت روشن — چرا متن سیاه نیست؟

| مورد | یافته |
|---|---|
| منبع رنگ‌ها | همهٔ توکن‌ها در `assets/css/tokens.css` از `--wp--preset--color--*` (پالت `theme.json`) مشتق می‌شوند. پالت پیش‌فرض تیره است (`text: #f7f8fb`). |
| ریشهٔ باگ | در حالت روشن، متن با `color-mix(in srgb, #f7f8fb 88%, black)` ساخته می‌شود که تقریباً `#dadbdd` (خاکستری روشن) است، نه سیاه. رنگ کم‌رنگ هم `#737373` می‌شود. |
| اثر جانبی | چون `--wp--preset--color--*` هرگز در حالت روشن بازنویسی نمی‌شد، هر بلوکی که با کلاس `has-*-color` یا استایل‌های `theme.json` (دکمه، پیوند، کپشن، جداکننده) رنگ می‌گرفت، همچنان مقدار تیره را داشت. |
| کنتراست | هاور دکمهٔ تغییر پوسته (`theme-toggle.css`) از `--mw-color-canvas` روی پس‌زمینهٔ تأکیدی استفاده می‌کرد؛ در حالت روشن کنتراست از بین می‌رفت. رنگ پیوند (`accent-strong = #1ed760`) روی سفید کنتراست ۱٫۹:۱ دارد. |
| تنوع‌های سبک | `cassette` و `sunrise` ذاتاً روشن‌اند؛ بقیه تیره. هیچ‌کدام `settings.custom` ندارند، پس نمی‌توانند نسخهٔ مقابل (روشن/تیره) خود را اعلام کنند. |

### ۱.۲ سربرگ و منوی موبایل

| # | مشکل | شواهد |
|---|---|---|
| H1 | `header-stream` ناوبری را زیر `64rem` مخفی می‌کند و هیچ همبرگری جایگزین آن نمی‌شود. | `navigation.css` خطوط ۸۰–۸۸ |
| H2 | نقطهٔ شکست همبرگر هسته (`overlayMenu: "mobile"`) **۶۰۰px** است، ولی استایل اورلی قالب تا `47.99rem` (۷۶۷px) نوشته شده بود. بین ۶۰۰ تا ۷۶۷px هسته منو را «درون‌خطی» نشان می‌دهد ولی قاعدهٔ پایهٔ قالب روی `.wp-block-navigation__responsive-container` مقدار `visibility: hidden; opacity: 0` می‌گذاشت ⇒ **منو در این بازه نامرئی بود.** | `navigation.css` خطوط ۱۹۸–۲۱۶ |
| H3 | سربرگ پیش‌فرض با `flexWrap: wrap` و جست‌وجوی ۱۸۰px در عرض‌های کوچک به دو خط می‌شکست. | `parts/header.html` |
| H4 | دکمهٔ همبرگر و بستن بدون سطح/کادر بودند، شورون زیرمنو استایل نداشت، و زیرمنوی دسکتاپ با رنگ پیش‌فرض هسته (پس‌زمینهٔ سفید/متن سیاه) در پوستهٔ تیره ظاهر می‌شد. | CSS هسته: `.wp-block-navigation:not(.has-background) .wp-block-navigation__submenu-container { background:#fff }` |
| H5 | آفست نوار مدیریت به‌صورت دستی (۳۲/۴۶px) نوشته شده بود؛ در `≤600px` که نوار مدیریت `absolute` است اشتباه می‌شود. هسته متغیر `--wp-admin--admin-bar--position-offset` را دقیقاً برای همین می‌سازد. | `navigation.css` ۴۳۳–۴۴۷، `layout.css` `.mw-sidebar { top: 6.25rem }` |
| H6 | `scroll-padding-top` برای لنگرها زیر سربرگ چسبان تعریف نشده بود. | grep در کل CSS |
| H7 | `theme-preference.js` فقط اولین `.mw-theme-toggle` را فعال می‌کرد (سربرگ + پابرگ همزمان کار نمی‌کردند). | `theme-preference.js` خط ۶۶ |
| H8 | `header-centered` و `header-minimal` فاقد `openSubmenusOnClick` بودند (رفتار لمسی زیرمنو ناسازگار). | `parts/header-*.html` |
| H9 | `z-index` پشتیبانی `position: sticky` هسته `10` است و بعد از CSS قالب چاپ می‌شود؛ قاعدهٔ `z-index: 60` قالب با همان ویژگی خنثی می‌شد. | `wp-includes/block-supports/position.php` |

### ۱.۳ پخش‌کنندهٔ سراسری `mw-global-player`

| # | مشکل | شواهد |
|---|---|---|
| P1 | شبکهٔ ۶ ستونی در `≤52rem` به ۳ ستون می‌رفت؛ دکمهٔ بستن و صدا به ردیف‌های بعدی می‌ریختند و چیدمان می‌شکست. | `global-player.css` ۵۱۵–۵۲۹ |
| P2 | آیکون‌ها کاراکتر متنی بودند (`▶ ❚❚ ⏮ ⏭ ♫ ×`) و بسته به قلم فارسی/سیستم ناهماهنگ رندر می‌شدند؛ حالت «در حال بارگذاری» با `…` نشان داده می‌شد. | `PreviewPlayer.php::render_global_player`, `preview-player.js::playGlyph/setLoading` |
| P3 | زمان (`__time`) در `≤52rem` مخفی می‌شد؛ در موبایل هیچ بازخورد زمانی نبود. | `global-player.css` ۵۲۶ |
| P4 | ضخامت دستگیرهٔ نوار پیشرفت روی لمس هم فقط با hover ظاهر می‌شد؛ `aria-valuetext` نداشت. | `global-player.css` ۲۱۶–۲۳۵ |
| P5 | وقتی پخش‌کننده باز بود، هیچ فاصلهٔ پایینی به `body` اضافه نمی‌شد و محتوای انتهای صفحه/پابرگ زیر آن می‌ماند. | grep `has-player` |
| P6 | از `left/right` فیزیکی به‌جای ویژگی‌های منطقی استفاده شده بود (RTL). | `global-player.css` ۸۴–۸۶ |
| P7 | قاعدهٔ `.mw-global-player__toggle[aria-pressed="true"]` هرگز فعال نمی‌شد؛ JS این ویژگی را روی دکمهٔ پخش نمی‌گذارد. | `preview-player.js` |
| P8 | قطع/وصل صدا (mute) وجود نداشت. | — |

### ۱.۴ سایر باگ‌ها

- CSS کلاس‌های `.mw-release-credits__list--grid/--inline` وجود نداشت (تست `template-integrity` قرمز بود) — **رفع شد** (`collections.css`).
- رنگ آیکون‌های اجتماعی پابرگ با هگز قدیمی (`#a9b0c0`) هاردکد شده بود.

---

## ۲. تصمیم‌های طراحی

1. **رنگ‌ها داده‌محور و قابل‌ویرایش در Site Editor می‌مانند.** به‌جای مشتق‌کردن حالت روشن با `color-mix`، هر تنوع سبک نسخهٔ روشن/تیرهٔ خود را در `settings.custom.scheme.{light,dark}` اعلام می‌کند؛ `tokens.css` در حالت روشن خودِ `--wp--preset--color--*` را بازنویسی می‌کند تا بلوک‌های هسته، کلاس‌های `has-*-color` و توکن‌های `--mw-*` همگی یک‌جا سوئیچ شوند. متن روشن: `#0f1115`.
2. **در حالت روشن رنگ تأکیدی تیره‌تر می‌شود** (`#15803d` به‌جای `#1db954`) تا متن پیوند و دکمه‌ها به کنتراست AA برسند؛ `on-accent` سفید می‌شود.
3. **یک نقطهٔ شکست واحد برای سربرگ: `64rem`.** ناوبری سربرگ با `overlayMenu: "always"` رندر می‌شود و قالب در `≥64rem` آن را درون‌خطی نشان می‌دهد (کلاس‌های پایدار `hidden-by-default`/`always-shown` هسته). نتیجه: همبرگر برای هر ۴ سربرگ زیر `64rem` و منوی کامل بالای آن؛ بازهٔ نامرئی ۶۰۰–۷۶۷px حذف می‌شود.
4. **استایل اورلی فقط روی `.is-menu-open` اعمال می‌شود؛** حالت بسته دست‌نخورده به هسته سپرده می‌شود تا ناوبری‌های دیگر (پابرگ، الگوها، منوهای کاربر) هرگز ناپدید نشوند.
5. **آفست نوار مدیریت با متغیرهای هسته** (`--wp-admin--admin-bar--position-offset` / `--wp-admin--admin-bar--height`) محاسبه می‌شود.
6. **جست‌وجوی سربرگ به حالت `button-only` هسته** می‌رود: آیکون ۴۴px که با کلیک باز می‌شود (Interactivity API هسته، Escape/focusout آن را می‌بندد). ورودی بازشده به‌صورت لایه روی سربرگ می‌نشیند تا layout shift ایجاد نشود.
7. **پخش‌کننده: آیکون SVG درون‌خطی، سه ناحیهٔ منطقی (قطعه / کنترل‌ها + خط زمان / ابزارها)، حالت‌ها با `data-*`** به‌جای تعویض متن. در `<48rem` نوار به لبهٔ پایین می‌چسبد، زمان‌ها همیشه دیده می‌شوند، نشانگر بارگذاری SVG است، `aria-valuetext` تنظیم می‌شود و `html.mw-has-player` فاصلهٔ پایینی صفحه را تضمین می‌کند.
8. **همهٔ selectorهایی که `preview-player.js` می‌شناسد حفظ می‌شوند** تا وابستگی‌های `playlists.js` / `download.js` نشکنند.

---

## ۳. برنامهٔ اجرا (ترتیب انجام)

| گام | فایل‌ها | خروجی |
|---|---|---|
| ۱ | `tokens.css`, `theme.json`, `styles/*.json`, `theme-toggle.css` | حالت روشن با متن تیره، پالت روشن/تیرهٔ اختصاصی برای هر ۶ تنوع، هاور دکمهٔ پوسته |
| ۲ | `parts/header*.html`, `components/navigation.css`, `catalog.css` (انتقال استایل جست‌وجوی سربرگ), `layout.css` | سربرگ درست در همهٔ ابعاد، منوی کامل موبایل، زیرمنوی توکن‌محور، جست‌وجوی بازشونده |
| ۳ | `functions.php` (`musicwave_render_theme_toggle`), `theme-preference.js`, `theme-toggle.css` | آیکون SVG سه‌حالته، فعال‌سازی همهٔ دکمه‌ها |
| ۴ | `PreviewPlayer.php`, `preview-player.js`, `components/global-player.css` | پخش‌کنندهٔ واکنش‌گرا با آیکون SVG، mute، زمان دوگانه، `aria-valuetext`، فاصلهٔ پایین صفحه |
| ۵ | `parts/footer*.html`, `patterns/footer-widget-columns.php` | مقدار آیکون‌های اجتماعی هم‌راستا با پالت |
| ۶ | `style.css`, `readme.txt`, `music-wave-core.php`, `release-manifest.json`, `PROJECT_PLAN.md` | نسخهٔ قالب `0.8.0`، هسته `0.11.3` |
| ۷ | `php tests/run.php`, `php tools/check-site-editor.php`, `php tools/check-templates.php`, `php tools/check-script-translations.php`, `php -l` | سبز |

## ۴. معیارهای پذیرش

- در `html[data-mw-theme="light"]` و در حالت سیستم روشن، `--mw-color-text` برابر `#0f1115` (یا مقدار روشن تنوع) است و `.has-text-color`/`.has-muted-color` هم روشن‌محور رندر می‌شوند.
- در ۳۶۰، ۶۰۰، ۷۶۸، ۱۰۲۴ و ۱۴۴۰px هر ۴ سربرگ یک ردیفه می‌مانند؛ زیر `64rem` همبرگر دیده می‌شود و اورلی تمام‌صفحه با دکمهٔ بستن قابل‌دسترس (زیر نوار مدیریت) باز می‌شود؛ زیرمنوها در اورلی باز و در دسکتاپ با کلیک باز می‌شوند.
- پخش‌کننده در همهٔ عرض‌ها بدون هم‌پوشانی کنترل‌ها رندر می‌شود؛ زمان جاری/کل همیشه دیده می‌شود؛ محتوای صفحه زیر نوار پنهان نمی‌شود.
- تست‌ها و ابزارهای یکپارچگی سبز هستند.

---

## ۵. گزارش اجرا (۶ سپتامبر ۲۰۲۶)

همهٔ گام‌های جدول بخش ۳ اجرا شد. نسخه‌ها: قالب `0.8.0`، هسته `0.11.3` (`release-manifest.json`، `style.css`، `readme.txt`ها، `PROJECT_PLAN.md`).

### ۵.۱ فایل‌های تغییریافته

| حوزه | فایل‌ها | خلاصه |
|---|---|---|
| رنگ / حالت روشن | `tokens.css`, `theme.json`, `styles/*.json` | نگاشت `--wp--preset--color--*` در حالت روشن؛ `settings.custom.scheme.{light,dark}` برای ۶ تنوع؛ `data-mw-scheme` روی `<html>` از `musicwave_language_attributes()` |
| سربرگ | `parts/header*.html`, `components/navigation.css`, `catalog.css`, `layout.css` | `overlayMenu:"always"` + نقطهٔ شکست واحد `64rem`، اورلی کامل (اسکرول، safe-area، آفست نوار مدیریت، `100dvh`)، زیرمنوی توکن‌محور، جست‌وجوی `button-only`، sticky با `:has()` روی wrapper و `z-index` بالاتر از پشتیبانی هسته |
| تغییر پوسته | `functions.php`, `theme-preference.js`, `theme-toggle.css` | سه آیکون SVG، همگام‌سازی همهٔ نمونه‌ها و تب‌ها (`storage`) |
| پخش‌کننده | `PreviewPlayer.php`, `preview-player.js`, `components/global-player.css` | نشانه‌گذاری سه‌ناحیه‌ای با SVG، `data-state` روی دکمهٔ پخش (`paused/playing/loading`)، `data-mw-volume`، mute، زمان جاری/کل، `aria-valuetext`، `html.mw-has-player` + `--mw-player-offset`، چیدمان داک‌شده در `≤48rem` |
| پابرگ | `parts/footer*.html`, `patterns/footer-widget-columns.php` | `iconColorValue` از پیش‌تنظیم پالت |
| باگ‌های جانبی | `collections.css`, `playlists.css` | کلاس‌های `__list--grid/--inline` (تست قرمز)؛ selector خراب `input[type=""search""]` |

### ۵.۲ قراردادهای جدید برای توسعه‌دهندگان

- **`html.mw-has-player`** و **`--mw-player-offset`** (تعریف در `global-player.css`) — هر عنصر چسبان/ثابت پایینی باید این آفست را لحاظ کند (`layout.css` برای `.wp-site-blocks` و `.mw-sidebar` انجام می‌دهد).
- **`--mw-sticky-offset`** — فاصلهٔ امن از بالای صفحه (نوار مدیریت + سربرگ چسبان در صورت فعال‌بودن).
- **وضعیت پخش‌کننده فقط با ویژگی‌ها** (`.mw-global-player[data-mw-state|data-mw-volume]`, `.mw-global-player__toggle[data-state]`) استایل می‌گیرد؛ هیچ کدی نباید `textContent` دکمه‌های پخش‌کننده را بازنویسی کند. قرارداد گلیف دکمه‌های بیرونی (`.mw-preview-button__icon`, `.mw-card-play__icon`, …) بدون تغییر مانده است.
- **حالت روشن:** رنگ جدید را فقط در `theme.json`/`styles/*.json` (پالت + `settings.custom.scheme`) اضافه کنید؛ CSS نیازی به ویرایش ندارد.

### ۵.۳ نتیجهٔ دروازه‌های کیفیت

- `php tests/run.php` → سبز (شامل `template-integrity.php`)
- `php tools/check-site-editor.php` / `check-templates.php` / `check-script-translations.php` → سبز
- `php -l` روی PHPهای ویرایش‌شده، `node --check` + ESLint (قواعد هسته) + wp-prettier روی JS ویرایش‌شده → بدون خطای جدید
- تجزیهٔ همهٔ CSS قالب با `css-tree` → فقط هشدار قدیمی `editor.css:29` (`@supports not (selector(:has(> *)))` که مرورگرها می‌پذیرند و پیش از این تغییر وجود داشت)
- تست دود jsdom روی `preview-player.js` با نشانه‌گذاری واقعی PHP (۲۵ بررسی) و `theme-preference.js` (۸ بررسی) → همه سبز

### ۵.۴ محدودیت‌های شناخته‌شده

- بوم ویرایشگر (`editor-styles-wrapper`) ویژگی‌های `<html>` را ندارد؛ بنابراین همیشه پالت **بومی** تنوع فعال را نمایش می‌دهد (رفتار مطلوب برای ویرایش رنگ‌ها). پیش‌نمایش حالت مقابل فقط در سایت عمومی است.
- ناوبری نرم پخش‌کنندهٔ سراسری (`persistentNav`) اکنون سربرگ hydrate‌شده را بین صفحه‌ها حمل می‌کند (بخش ۵.۵)؛ تنها محدودیت باقی‌مانده این است که اگر مدیر سربرگ را در صفحه‌ای متفاوت بسازد (منوی متفاوت)، همان کلیک به بارگذاری بومی تبدیل می‌شود — رفتار امن و قابل‌مشاهده، نه منوی خراب.
- بررسی بصری با مرورگر واقعی در این محیط ممکن نبود (بدون Chromium)؛ چیدمان‌ها بر اساس قراردادهای CSS هسته که در بخش ۱ مستند شده‌اند نوشته شده‌اند و باید در محیط staging در ۳۶۰/۶۰۰/۷۶۸/۱۰۲۴/۱۴۴۰px بازبینی شوند.

### ۵.۵ تکمیل: ناوبری نرم و بلوک‌های تعاملی هسته (افزودهٔ ۶ سپتامبر ۲۰۲۶)

**مسئله (ریشه‌یابی‌شده در کد هسته):** `@wordpress/interactivity` ناحیه‌های `data-wp-interactive` را فقط یک بار و در `DOMContentLoaded` hydrate می‌کند (`packages/interactivity/src/index.ts` → `onDOMReady(hydrateRegions)`). `navRenderPage` کل `<body>` را جایگزین می‌کرد؛ بنابراین سربرگ تازه (منوی همبرگر `core/navigation` و جست‌وجوی بازشوندهٔ `core/search`) پس از اولین ناوبری نرم بی‌اثر می‌شد. همچنین ورودی‌های تاریخچهٔ ما `wpInteractivityId` نداشتند و هسته در `popstate` صفحه را اجباراً reload می‌کرد.

**راه‌حل پیاده‌شده در `music-wave-core/assets/preview-player.js`:**

1. **حمل سربرگ زنده:** اگر سربرگ صفحهٔ مقصد همان منو را داشته باشد (امضای `navChromeSignature`: کلاس‌ها + برچسب آیتم‌ها)، گرهٔ سربرگ hydrate‌شدهٔ فعلی به‌جای نسخهٔ تازه در جای خود می‌ماند و فقط `href`ها، `aria-current` و کلاس‌های `current-menu-*` از صفحهٔ تازه همگام می‌شوند (`navSyncChromeState`). اگر overlay باز باشد، از طریق دکمهٔ بستن خودِ بلوک بسته می‌شود تا وضعیت داخلی هسته (`aria-expanded`، بازگشت فوکوس، `html.has-modal-open`) سازگار بماند.
2. **قرارداد ایمنی:** اگر صفحهٔ مقصد ناحیهٔ تعاملی دیگری خارج از سربرگ داشته باشد (لایت‌باکس تصویر، صفحه‌بندی پیشرفتهٔ Query، بلوک‌های ووکامرس) یا سربرگ متفاوتی داشته باشد، `navHydrationSafe` ناوبری را به مرورگر برمی‌گرداند (بارگذاری بومی؛ پخش‌کننده از `sessionStorage` بازیابی می‌شود). صفحاتی که خودشان `data-wp-router-region` دارند اصلاً رهگیری نمی‌شوند.
3. **استایل‌ها:** قالب‌های بلوکی CSS هر بلوک/چیدمان را فقط برای همان مسیر چاپ می‌کنند؛ `navMergeStyles` شیت‌های جدید را قبل از نقاشی اضافه می‌کند (بدون حذف قبلی‌ها) و `<style id="…">`های درون‌خطی (مثل `core-block-supports-inline-css`) را بر اساس id ادغام می‌کند.
4. **اسکریپت‌ها:** `navRunScripts` اسکریپت‌های تازه را به ترتیب سند و با انتظار برای بارگذاری هر فایل اجرا می‌کند (حفظ ترتیب وابستگی WordPress) و رویداد `mw-page-rendered` فقط پس از اتمام همهٔ آن‌ها ارسال می‌شود.
5. **`<html>`:** `lang`، `dir` و `data-mw-scheme` از صفحهٔ تازه همگام می‌شوند تا تشخیص حالت روشن/تیره در صفحات با تنوع سبک متفاوت درست بماند.
6. **تاریخچه:** ورودی‌های `pushState` مقدار `wpInteractivityId` هسته را حمل می‌کنند؛ بازگشت/جلو دوباره ناوبری نرم است، نه reload اجباری.

**آزمون:** اسکریپت دود jsdom با ۳۳ بررسی (حمل سربرگ، همگام‌سازی آیتم جاری، بستن overlay، ادغام استایل، اجرای اسکریپت جدید و حذف تکراری، ترتیب رویداد، fallback برای لایت‌باکس/سربرگ متفاوت/router-region، بازگشت با popstate) + دود پخش‌کننده (۲۷) + دود سوییچ پوسته (۸) همه سبز؛ `npm run lint:js` (ESLint + wp-prettier واقعی پروژه) بدون خطا.


---

## ۶. دور سوم — نشانی‌ها، صفحهٔ تک‌انتشار، قفسه و گالری فهرست‌های پخش (۶ سپتامبر ۲۰۲۶)

### ۶.۱ تحلیل (یافته‌های تأییدشده از کد)

| موضوع | ریشهٔ مشکل (فایل:خط) |
| --- | --- |
| همهٔ انتشارها زیر `/music/…` | `ReleasePostType.php:64` فقط `rewrite.slug = music` را ثبت می‌کند؛ هیچ فیلتر `post_type_link`، `request` یا `add_rewrite_rule` سفارشی در هسته وجود ندارد. نوع انتشار یک طبقه‌بندی سلسله‌مراتبی (`mw_release_type`) با اسلاگ‌های `track single ep album mix playlist podcast_show podcast_episode` است (`ReleaseTaxonomies.php:98–105`). |
| صفحهٔ تک‌انتشار در موبایل/تبلت | `collections.css:104–120` در ≤۴۸rem ردیف را به ستون تبدیل می‌کند و `__main` را wrap می‌کند؛ نتیجه: نام هنرمند و مدت‌زمان به‌صورت نامنظم زیر هم می‌افتند و اکشن‌ها (پیش‌نمایش + گروه دانلود) با فاصلهٔ اضافه در خط جدا می‌نشینند. سربرگ جدول فقط در ≥۴۸rem نمایش داده می‌شود و ستون‌های `22% / 4.2rem / 6.5rem` برای تبلت (۴۸–۶۴rem) تنگ است. |
| بخش دیدگاه‌ها | `ReleasePostType.php:68` ویژگی `comments` را پشتیبانی نمی‌کند؛ `single-mw_release.html` هیچ بلوک `core/comments` ندارد؛ در کل پوسته هیچ CSS برای `.wp-block-comments`، `.comment-form` و `.wp-block-post-comments-form` وجود ندارد. |
| دکمه‌های `.mw-release-shelf__nav-button` | `slider.js:292–313` با فرض LTR کار می‌کند: `atStart = scrollLeft <= 2` و `move()` مقصد را در `[0, max]` قفل می‌کند. در RTL مقدار `scrollLeft` از `0` (ابتدا) به سمت **منفی** می‌رود؛ پس «قبلی» همیشه غیرفعال و «بعدی» بی‌اثر است. اسلایدر کلاسیک به‌دلیل `direction:ltr` روی `.mw-release-slider__viewport` (`slider.css:67`) از این باگ در امان مانده؛ قفسه چنین override‌ای ندارد. |
| گالری `.mw-playlists__art-grid` | یک شبکهٔ ۲×۲ ساده (`playlists.css:247–295`) که در سه جا استفاده می‌شود (فهرست حساب کاربری، بلوک `public-playlists` با چهار شکل تصویر، و بازسازی سمت کلاینت). باگ: `playlists.js:503–505` کلاس‌های ناموجود `mw-public-playlists__art-grid(--empty)` تولید می‌کند → پس از جست‌وجو/صفحه‌بندی کارت‌ها بدون استایل می‌شوند. |

### ۶.۲ تصمیم‌های طراحی

1. **نشانی‌های نوع‌محور (استاندارد و قابل پیش‌بینی):** نگاشت اسلاگ نوع → پیشوند نشانی به‌صورت فیلترپذیر (`music_wave_release_permalink_bases`):

   | نوع انتشار | پیشوند نشانی | نمونه |
   | --- | --- | --- |
   | `track`, `single` | `/track/` | `/track/midnight-drive/` |
   | `album` | `/album/` | `/album/night-signals/` |
   | `ep` | `/ep/` | |
   | `mix` | `/mix/` | |
   | `playlist` | `/playlist/` | |
   | `podcast_show` | `/podcast/` | |
   | `podcast_episode` | `/episode/` | |
   | بدون نوع / نوع ناشناخته | `/music/` (پیشوند قبلی) | سازگاری کامل با گذشته |

   انتخاب نوع: اولین اصطلاح شناخته‌شده در ترتیب اولویت بالا (اگر یک قطعه هم `single` و هم `track` داشته باشد، نتیجه یکسان است). قوانین بازنویسی برای هر پیشوند دقیقاً مثل قوانین هستهٔ وردپرس (`/base/slug/`, `/page/N`, `/embed`, `/feed`, `/trackback`, `attachment`) با `add_rewrite_rule(...,'top')` ثبت می‌شود و مسیرهای قبلی `/music/<slug>/` همچنان resolve می‌شوند و با **۳۰۱** به نشانی کانونیک منتقل می‌گردند (الگوی `ReleaseArchiveQuery::redirect_release_search`؛ فقط برای `is_singular('mw_release')` و نه برای feed/embed/preview/صفحه‌بندی داخلی). آرشیو نوع (`/release-type/<slug>/`) دست‌نخورده می‌ماند.
   **ثبات نشانی:** به‌جای مهاجرت پایگاه داده (طرح‌واره تغییری نمی‌کند)، `ReleasePermalinks` اثر انگشت نقشهٔ پایه‌ها را در گزینهٔ `music_wave_release_permalink_rules` نگه می‌دارد و روی `wp_loaded` در صورت تغییر یک بار `flush_rewrite_rules(false)` می‌زند؛ پس نصب‌های موجود در همان درخواست اول پس از به‌روزرسانی بدون «ذخیرهٔ دستی پیوندهای یکتا» به‌روز می‌شوند. `Activator` هم قوانین را پیش از flush ثبت می‌کند و اثر انگشت را می‌نویسد. تغییر نوع یک انتشار در ویرایشگر خودبه‌خود نشانی جدید می‌گیرد و نشانی قدیمی ۳۰۱ می‌شود.
2. **صفحهٔ تک‌انتشار:** طراحی موبایل‌محور برای لیست قطعه با سه حالت — موبایل (≤۴۸rem): کارت دو خطی «شماره/جلد | عنوان + هنرمند · مدت» با اکشن‌ها در انتهای همان ردیف؛ تبلت (۴۸–۶۴rem): ستون کیفیت پنهان، ستون هنرمند انعطاف‌پذیر؛ دسکتاپ: جدول کامل. فقط CSS تغییر می‌کند (مارک‌آپ PHP و قلاب‌های `data-mw-playing`، `.mw-equalizer`، `__position-play` حفظ می‌شوند). دیدگاه‌ها با بلوک‌های استاندارد `core/comments` (قالب دیدگاه، صفحه‌بندی، فرم) به `single-mw_release.html` اضافه و در ماژول جدید `components/comments.css` با توکن‌های پوسته (دو حالت روشن/تیره) استایل می‌شوند؛ پشتیبانی `comments` به CPT اضافه می‌شود (مدیر می‌تواند از «تنظیمات > گفت‌وگو» یا هر انتشار، دیدگاه را ببندد).
3. **قفسهٔ اسکرولی:** محاسبات منطقی جهت‌آگاه در `slider.js` (`Math.abs(scrollLeft)` به‌عنوان موقعیت منطقی؛ `scrollTo({left: rtl ? -x : x})`) برای هر دو مسیر اسلایدر و قفسه؛ آیکون‌های SVG شِورون (مانند هیرو اسلایدر) به‌جای گلیف متنی و آینه‌سازی در RTL؛ در دستگاه‌های لمسی دکمه‌های فعال دیده می‌شوند.
4. **گالری فهرست پخش:** طرح «پشتهٔ جلدهای بادبزنی» تماماً با CSS — چهار جلد به‌صورت کارت‌های چیده‌شده روی هم با چرخش/جابه‌جایی ظریف، جلد اول در جلو و بزرگ‌تر، سایهٔ عمقی، انیمیشن باز شدن بادبزن در hover/focus (با `prefers-reduced-motion`)، حالت خالی با نُت و گرادیان آکسنت. برای شکل‌های `landscape`/`portrait` و حالت فهرستی ۵rem همان پشته با پارامترهای کوچک‌تر، برای `circle` پشتهٔ دایره‌ای. باگ کلاس‌های `playlists.js` رفع می‌شود و REST `GET /playlists/public` فیلد `covers` (حداکثر ۴ نشانی بندانگشتی منتشرشده) را برمی‌گرداند تا کارت‌های بازسازی‌شده هم جلد واقعی داشته باشند.

### ۶.۳ برنامهٔ اجرا

| # | فایل‌ها | کار |
| --- | --- | --- |
| ۱ | `src/Catalog/ReleasePermalinks.php` (جدید)، `Modules/Catalog.php`، `Lifecycle/Activator.php`، `Plugin.php`، `tests/run.php` | نشانی‌های نوع‌محور + قوانین + ۳۰۱ + flush خودکار (بدون مهاجرت طرح‌واره؛ CI دست‌نخورده می‌ماند) |
| ۲ | `ReleasePostType.php`، `templates/single-mw_release.html`، `assets/css/components/comments.css` (جدید)، `functions.php` (ثبت ماژول) | دیدگاه‌ها |
| ۳ | `collections.css`، `catalog.css` | لیست قطعه و هیرو/اکشن‌ها در موبایل و تبلت |
| ۴ | `slider.js`، `shelf.css`، `functions.php` (آیکون‌ها) | قفسهٔ اسکرولی RTL |
| ۵ | `playlists.css`، `playlists.js`، `PlaylistRoutes.php` | گالری بادبزنی + رفع باگ کلاینت + `covers` |
| ۶ | نسخه‌ها (`music-wave-core` → `0.12.0`، `musicwave` → `0.8.1`)، `release-manifest.json`، readmeها، `PROJECT_PLAN.md`، `docs/local-testing-fa.md`، PO/MO/POT | مستندسازی و انتشار |

### ۶.۴ معیارهای پذیرش

- `get_permalink()` یک آلبوم → `/album/<slug>/`، قطعه/تک‌قطعه → `/track/<slug>/`، بدون نوع → `/music/<slug>/`؛ درخواست `/music/<slug>/` برای آلبوم با ۳۰۱ به `/album/<slug>/` می‌رود؛ آرشیو `/music/` و `/release-type/album/` بدون تغییر.
- در عرض ۳۶۰px هیچ سرریز افقی در صفحهٔ تک‌انتشار نیست؛ هر ردیف قطعه حداکثر دو خط متن + ردیف اکشن‌ها با هدف لمسی ≥۴۰px.
- بخش دیدگاه‌ها زیر محتوای انتشار، با فرم استایل‌شده، دکمهٔ ارسال پیل‌شکل، آواتار، پاسخ‌ها و صفحه‌بندی؛ در حالت روشن متن تیره.
- در قفسهٔ اسکرولی RTL «بعدی» به سمت قطعه‌های بعدی می‌رود، «قبلی» پس از حرکت فعال می‌شود و در انتها «بعدی» غیرفعال می‌شود.
- گالری فهرست پخش در حساب کاربری، بلوک عمومی (چهار شکل) و کارت‌های بازسازی‌شده با JS یکسان و استایل‌دار است.
- همهٔ دروازه‌ها سبز: `tests/run.php`، `template-integrity.php`، `check-templates.php`، `check-site-editor.php`، `check-script-translations.php`، `npm run lint:js`، بررسی نحو PHP.
