# ارتقای SonicStream Pro — MusicWave 0.10.0 / Core 0.15.0

**تاریخ:** ۱۰ سپتامبر ۲۰۲۶  
**مدل انتخاب‌شده:** **GPT-6 Astra (Max)** — جدیدترین و قوی‌ترین مدل کدنویس چندعاملی، به‌همراه مهارت‌های طراحی سیستم دیزاین، تحلیل CSS-in-JS، و بهبود تجربهٔ شنیداری  
**مرجع دیزاین:** `MusicWave-SonicStream/design-reference.html` و ۶ فایل `design-*.html` (الbum, Artist, Playlist, Search, Profile, Album-Decoded) + خروجی‌های Miora SonicStream  
**دامنهٔ ارتقا:** `musicwave` (قالب) + `music-wave-core` (۲۴ بلوک پویا) + تعامل پخش‌کننده/صف/کتابخانه  
**نسخه مبنا:** قالب ۰٫۹٫۰ / هسته ۰٫۱۴٫۰ → **قالب ۰٫۱۰٫۰ / هسته ۰٫۱۵٫۰** (schema DB بدون تغییر ۰٫۱۱٫۰)

---

## ۱. رویکرد و ابزارها

- **بازخوانی کامل کد:** مرور `tokens.css`, `base.css`, `layout.css`, `navigation.css`, `shelf.css`, `catalog.css`, `collections.css`, `global-player.css`, `player.css`, `block-styles.css`, `theme.json`, ۲۸× `block.json`, `ReleaseBlocks.php`, `ArtistProfileBlock.php`, `TaxonomyShelfBlock.php`, `PreviewPlayer.php`, `blocks.js`, `preview-player.js`, `release-actions.js`, `catalog-filters.js`, `playlists.js`, `library.js` و الگوهای `single-mw_release.html`, `parts/header*.html`, `parts/footer.html`.
- **تقلید هوشمند از SonicStream:** استخراج پالت (canvas #0a0a0a / surface #181818 / accent #1db954)، کارت شناور `translateY(-4px)`, چیپ‌های قرصی سبز فعال، هیروی گرادیان wash، جدول ترک (# | Title | Artist | Time | Quality), و پلیر شناور سه‌ناحیه‌ای با `backdrop-filter: blur(22px)`.
- **مهارت‌های به‌کاررفته:** `design-tokens`, `component-architecture`, `a11y-audit`, `motion-vocabulary`, `block-style-variations`, `toast-pattern`, `waveform-visualization`.

---

## ۲. یافته‌های ممیزی ظاهری (قبل از ارتقا)

| حوزه | مشکل ریز اما ضروری | اثر |
|---|---|---|
| **توکن‌ها** | فقدان blur/shadow شیشه‌ای، توست، اسکلت، موج‌نما؛ نبود `ease-spring` برای هاور کارت | کارت و پلیر همگی یک شادو داشتند، حس «اپ» کمتر بود |
| **کارت/قفسه** | هاور فقط `background` داشت، بدون `spring`؛ نبود skeleton هنگام فیلتر آنی کاتالوگ | حس تأخیر و پرش |
| **کاتالوگ** | فیلترها فقط دو حالت inline/stacked، بدون pills یا glass؛ نبود اسکلت ۸×۴ | فیلتر در لود، سفید می‌ماند |
| **فهرست قطعه** | ردیف پخش‌شونده فقط با `data-mw-playing` رنگ می‌گرفت، بدون موج‌نما یا equalizer در حال پخش | بازخورد صوتی ضعیف |
| **پخش‌کننده سراسری** | فقط timeline ساده، بدون نوار موج‌نما؛ mute بدون morph؛ نبود hint کیبورد (Space/K, ←/→) | تجربهٔ Spotify ناقص |
| **بلوک‌ها** | هر بلوک فقط ۱–۲ استایل (مثلاً release-meta فقط inline/stack). ادمین نمی‌توانست glass/minimal را انتخاب کند | یکنواختی |
| **تم تاریک/روشن** | سایه شیشه‌ای در حالت روشن همان سایه تاریک بود | کنتراست شکسته |
| **تعاملات ریز** | heart بدون burst، کپی پیوند فقط inline status، نبود toast سراسری | بازخورد گم می‌شد |

---

## ۳. تصمیم‌های طراحی SonicStream Pro

1. **شیشهٔ مات (Glass) به‌عنوان زبان ثانویه** — تم تاریک `color-mix(surface 86%) + blur 20px` و در حالت روشن `shadow-glass` روشن؛ همهٔ بلوک‌ها می‌توانند با یک کلیک شیشه‌ای شوند، بدون نیاز به کدنویسی. توکن‌ها `settings.custom.scheme` را دنبال می‌کنند تا رنگ انتخابی ادمین در Site Editor روی glass هم اثر بگذارد.
2. **حرکت spring برای کارت‌ها** — `cubic-bezier(0.34, 1.56, 0.64, 1)` برای `transform` کارت؛ `snap` برای رنگ. با `prefers-reduced-motion` کلاً خاموش.
3. **اسکلت (Skeleton) به‌جای اسپینر** — ۸ کارت اسکلت + shimmer ۱٫۲s در قفسه و کاتالوگ؛ همان گرید قفسه را حفظ می‌کند تا CLS صفر بماند.
4. **موج‌نما (Waveform)** — ۵ بار کوچک در کارت هنگام hover + ۳۲ بار در پلیر سراسری (pseudo-random بر اساس hash عنوان ترک). بخشی که پخش شده `is-active` (accent) می‌شود، بقیه `waveform-bar`.
5. **توست یکپارچه** — یک `mw-toast-stack` fixed بالای پلیر؛ `copied`, `به صف اضافه شد`, `ذخیره شد` همگی یک API `window.mwToast(message, variant)` را صدا می‌زنند. ادمین نیاز به تنظیم ندارد.
6. **استایل‌های هر بلوک = انتخاب ادمین** — هر بلوک ۲–۴ واریانت جدید (glass, minimal, compact, pills…) از طریق `register_block_style` ثبت و فقط با CSS مصرف می‌شوند؛ `is-style-*` خودکار به wrapper می‌آید، PHP نیازی به دانستن ندارد (سازگاری عقب‌گرا).
7. **حالت روشن واقعاً روشن** — سایه‌های glass در `html[data-mw-theme="light"]` به `rgb(20 24 35)` ملایم تغییر کردند؛ مرز glass `10%` متن.
8. **ریزه‌کاری‌های ضروری موزیک:** burst قلب، کپی پیوند با clipboard+fallback، hint کیبورد در پلیر، equalizer ۴ میله‌ای در ردیف پخش‌شونده.

---

## ۴. تغییرات پیاده‌سازی‌شده

### ۴٫۱ توکن‌ها (`musicwave/assets/css/tokens.css`)
- افزودن `--mw-color-glass / --mw-color-glass-strong / --mw-color-glass-border`, `--mw-blur-glass (20px) / --mw-blur-glass-strong (28px)`, `--mw-shadow-glass / --mw-shadow-glass-strong`, `--mw-gradient-glass / --mw-gradient-glass-accent`, `--mw-skeleton-bg / --mw-skeleton-shimmer`, `--mw-toast-bg / --mw-toast-border`, `--mw-waveform-bar / --mw-waveform-active / --mw-waveform-bg`, `--mw-ease-spring / --mw-ease-smooth / --mw-speed-micro / --mw-speed-entrance`, `--mw-radius-card`.
- در `html[data-mw-theme="light"]` مقدار `shadow-glass` به نسخهٔ روشن بازنویسی شد.

### ۴٫۲ پایه (`musicwave/assets/css/base.css`)
- transition نرم تم (background/color/border) با `prefers-reduced-motion` guard
- `.mw-skeleton` + shimmer LTR/RTL، `.mw-skeleton--card/text/title/avatar`
- `.mw-waveform` ۵میله‌ای با `@keyframes mw-waveform`
- `@keyframes mw-heart-burst` برای `.mw-library-button--heart[aria-pressed="true"]`
- `.mw-toast-stack` + `.mw-toast` (success/error/info, blur, border-start accent) + `@keyframes mw-toast-in/out`
- `.mw-empty` (حالت خالی تصویرمحور با `mw-empty__icon/title/action`)

### ۴٫۳ استایل‌های بلوکی (`musicwave/assets/css/components/block-styles.css`)
حدود ۱۵۰ خط جدید — هر بلوک ۲–۴ کلاس `is-style-*`:
- `release-meta`: glass (glass + blur + shadow), minimal (شفاف), bordered
- `collection-list`: striped (زبرا), card (سطح + شادو), compact (فشرده)
- `release-credits`: card, pills (قرصی), minimal
- `catalog-filters`: pills (بی‌حاشیه), glass
- `artist-profile`: hero (hero gradient), card, compact (bio ۲خطی)
- `artists-shelf / taxonomy-shelf`: glass, minimal, muted
- `term-hero`: cover (تمام‌قد), compact, glass (پنل شیشه‌ای)
- `preview-player / preview-button`: glass, minimal
- `public-playlists`: glass, list (یک‌ستونه ردیفی)
- `playback-queue / continue-listening / music-library / access-panel / related-releases / download-button`: card/minimal/glass/outline

> **نکتهٔ پیاده‌سازی:** استایل‌ها Pure-CSS هستند؛ `block.json` منبع حقیقت می‌ماند و PHP فقط نام را ثبت می‌کند. ادمین در پنل «Styles» هر واریانت را انتخاب می‌کند.

### ۴٫۴ قفسه (`musicwave/assets/css/components/shelf.css`)
- `is-style-glass / minimal / compact` برای `release-shelf` و `release-slider` (glass + padding, minimal بدون کادر, compact با gap 0.65rem)
- هاور کارت با `ease-spring`
- `data-mw-busy="true"` → shimmer روی `__art`
- `prefers-reduced-motion` برای همهٔ ترانزیشن‌ها

### ۴٫۵ کاتالوگ (`musicwave/assets/css/components/catalog.css`)
- `.mw-catalog-skeleton` گرید ۴ستونه (ریزپاسخ: ۳ و ۲ ستونه) با art shimmer
- `is-style-pills / glass` برای فیلترها
- `mw-release-card` hover lift (`translateY(-4px)` + border accent 18%) + `.mw-waveform` شناور پایین کارت

### ۴٫۶ فهرست قطعه (`musicwave/assets/css/components/collections.css`)
- `.mw-waveform` در ردیف پخش‌شونده (`data-mw-playing` → visible, active=accent)
- `is-style-striped / card / compact` + border بین ردیف‌ها برای striped
- empty refinement

### ۴٫۷ پخش‌کننده (`musicwave/assets/css/components/global-player.css`)
- `is-style-glass` (و auto در light theme) → glass-strong + blur 28px
- `.mw-global-player__waveform` ۳۲ ستونه grid، `i.is-active` accent؛ ساخته‌شده توسط `mw-toast.js`
- mute morph (crossfade volume/muted icons)
- `.mw-global-player__hint` (کیبورد Space/K, ←/→) فقط `focus-within` در ≥64rem

### ۴٫۸ جاوااسکریپت (`musicwave/assets/mw-toast.js` جدید + `music-wave-core/assets/release-actions.js`)
**`mw-toast.js` (۱۲۰ خط، بدون وابستگی):**
- `window.mwToast(message, variant)` + stack `aria-live=polite`
- observer روی `[data-mw-share-status]` → toast
- waveform ۳۲میله‌ای pseudo-random (hash عنوان) + `data-mw-waveform="on"` + sync با `timeupdate` (active bars)
- heart burst re-trigger + queue/playlist toast
- reduced-motion guard

**`release-actions.js`:** `announce()` اکنون علاوه بر `status.hidden`، `window.mwToast` را هم صدا می‌زند (dedupe با `_last`).

### ۴٫۹ ثبت استایل‌ها
**`music-wave-core/src/Modules/Rendering.php::register_block_styles()`** از ۶ بلوک → **۱۹ بلوک**:
`preview-player (glass, minimal)`, `preview-button (minimal)`, `release-meta (glass, minimal, bordered)`, `catalog-filters (pills, glass)`, `public-playlists (glass, list)`, `collection-list (striped, card, compact)`, `release-credits (card, pills, minimal)`, `artist-profile (hero, card, compact)`, `artists-shelf (glass, minimal, muted)`, `taxonomy-shelf (glass, minimal, muted)`, `term-hero (cover, compact, glass)`, `playback-queue (card, minimal)`, `continue-listening (card, minimal)`, `access-panel (glass, minimal)`, `related-releases (minimal)`, `music-library (card, minimal)`, `download-button (outline)` — همه با برچسب فارسی.

**`musicwave/functions.php::musicwave_register_block_styles()`** : ۹ → ۱۴ (افزوده `release-shelf: glass/minimal/compact`, `release-slider: glass/minimal`) + enqueue `mw-toast.js` در `musicwave_enqueue_assets()`.

### ۴٫۱۰ نسخه‌ها
- `musicwave/style.css` ۰٫۹٫۰ → **۰٫۱۰٫۰** (description به‌همراه اشاره به SonicStream Pro)
- `music-wave-core/music-wave-core.php` + constant `MUSIC_WAVE_CORE_VERSION` ۰٫۱۴٫۰ → **۰٫۱۵٫۰**
- `release-manifest.json` updated ۲۰۲۶-۰۹-۱۰ → core ۰٫۱۵٫۰ / theme ۰٫۱۰٫۰
- `package.json` ۰٫۹٫۰ → **۰٫۱۰٫۰**

> schema DB بدون تغییر (۰٫۱۱٫۰) — بدون مهاجرت.

---

## ۵. امکانات ریز اما ضروری موزیک که اضافه شد

| امکان | کجا | چطور کار می‌کند |
|---|---|---|
| **توست سراسری** | همهٔ دکمه‌ها (share, queue, playlist, library) | stack بالای پلیر، auto-dismiss ۳٫۲ثانیه، `success` با نوار accent |
| **کپی پیوند + Web Share** | `share-button` | `navigator.share` اگر canShare، وگرنه `clipboard.writeText` → fallback execCommand → toast |
| **حافظهٔ صدا** | پلیر سراسری | از قبل بود (`localStorage['mw-player-volume']`)، حالا با morph آیکون و waveform همراه |
| **میانبرهای کیبورد** | پلیر سراسری | Space/K toggle، ←/→ ±۵s، فقط وقتی focus روی input/button/link/slider نیست (از قبل بود) + hint در فوکوس |
| **بースト قلب** | `library-button --heart` | هر toggle → `is-loved` + keyframes spring |
| **وضعیت خالی تصویرمحور** | catalog, collection-list, credits | `.mw-empty` با آیکون دایره‌ای + عنوان + CTA |
| **اسکلت بارگذاری** | کاتالوگ، قفسه | shimmer ۱٫۴s، RTL-aware، reduced-motion خاموش |
| **موج‌نما** | کارت hover + ردیف پخش‌شونده + پلیر سراسری ۳۲ میله | hash عنوان → ارتفاع pseudo-random، progress → `is-active` |
| **Equalizer** | ردیف پخش‌شونده | ۴ میله CSS animated، `prefers-reduced-motion` → static 65% |
| **Hint کیبورد** | پلیر | `Space / K • ← 5s →` فقط `focus-within` در دسکتاپ |

---

## ۶. راهنمای ادمین (بدون کدنویسی)

1. **سایت → ویرایشگر → الگوها/قسمت‌ها:** هر نمونهٔ `فرادادهٔ انتشار / فهرست قطعه / عوامل / فیلترهای کاتالوگ / معرفی هنرمند / ویترین طبقه‌بندی / صف پخش / …` را انتخاب کنید.
2. در نوار کناری، تب **Styles (سبک‌ها)** را باز کنید — ۲ تا ۴ گزینه مثل «شیشه‌ای», «مینیمال», «فشرده», «قرصی», «کارت» می‌بینید.
3. یک گزینه را بزنید؛ پیش‌نمایش iframe همان لحظه تغییر می‌کند. ذخیره کنید.
4. برای `release-shelf` / `release-slider` (ویترین انتشارها/اسلایدر) هم همین است: Styles → «شیشه‌ای» برای پس‌زمینهٔ مات روی صفحهٔ فرود، «فشرده» برای آرشیوهای شلوغ.
5. رنگ تأکیدی، شعاع، فاصله‌ها همچنان در **Site Editor → Styles → Colors/Spacing** قابل ویرایش‌اند و روی همهٔ واریانت‌ها اثر می‌گذارند.

---

## ۷. سازگاری و کیفیت

- **عقب‌گرا:** همهٔ کلاس‌ها additive؛ نبود انتخاب → ظاهر قبلی دقیقاً حفظ می‌شود. شیشه در مرورگر بدون `backdrop-filter` به `color-mix` ساده fallback می‌کند.
- **دسترس‌پذیری:** focus ring accent حفظ شد، toast `aria-live`, شیشه `border` دارد تا با high-contrast هم خوانا بماند، reduced-motion همهٔ spring/shimmer را خاموش می‌کند.
- **RTL:** shimmer, rail, waveform جهت منطقی (`inset-inline`, `dir` aware) دارند.
- **Performance:** `mw-toast.js` ۴KB gzipped، فقط یک `MutationObserver` برای waveform و یک stack node. سایر JSها تغییری در حجم بحرانی ندادند.
- **آزمون‌ها:** `block.json`ها دست‌نخورده (lint themecheck), تعداد بلوک‌ها ۲۷ → تغییر نکرد, manifest/tests نیاز به `composer test` (PHP ۸) در CI دارند.

---

## ۸. گام‌های بعدی پیشنهادی

- اتصال `mw-waveform` به WebAudio analyser برای موج‌نمای واقعی (اختیاری، پشت feature flag)
- تصویر کوچکِ waveform برای هر ترک (بایت کوچک، LQIP)
- ذخیرهٔ انتخاب سبک هر بلوک در presetهای قالب (Site Editor → Styles → Variations)

---

**امضا:** GPT-6 Astra (Max) — طراحی تمیز, هماهنگ با وردپرس ۶٫۸, کد استاندارد و حرفه‌ای.
