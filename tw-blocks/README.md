# افزونهٔ وردپرس TW Blocks — مستندات کامل (فاز ۱، ۲ و ۳)

**TW Blocks** (`slug: tw-blocks`) یک کتابخانه و بیلدر بلوکی سبک، سئومحور، ایمن و بدون وابستگی به jQuery بر پایهٔ **Gutenberg (Block API v3)** و **Tailwind CSS v4** است که با اولویت اول برای سایت‌های فارسی‌زبان، چیدمان راست‌به‌چپ (`dir="rtl"`) و شرایط سرورهای اشتراکی ایران طراحی و مهندسی شده است.

---

## ۱. خلاصهٔ معماری در سه فاز

### فاز ۱: هستهٔ افزونه، موتور ۳ درایورهٔ CSS و بومی‌سازی فارسی/شمسی
1. **سازگاری کامل با هاست اشتراکی (cPanel):**
   - بدون نیاز به Node.js، دسترسی Shell یا Composer در زمان اجرا (Runtime).
   - دارای **Autoloader اختصاصی به سبک PSR-4** برای فضای نام `TWBlocks\`.
2. **معماری ۳ درایورهٔ CSS Engine (انتخاب هوشمند خودکار):**
   - **Driver A (`LocalBinaryDriver`):** اجرای باینری مستقل Tailwind CSS v4 در مسیر `wp-content/uploads/tw-blocks/bin/tailwindcss` از طریق `proc_open` (بدون الحاق شل برای امنیت کامل).
   - **Driver B (`RemoteApiDriver`):** ارسال آرایهٔ کلاس‌های اعتبارسنجی‌شده و بلوک `@theme` به میکروسرویس بیلد راه دور با امضای رمزنگاری `HMAC-SHA256`، بررسی Timestamp، تلاش مجدد (Retry) و Timeout.
   - **Driver C (`FallbackSafelistDriver`):** در صورت در دسترس نبودن Driver A و B، استایل آمادهٔ `safelist-fallback.css` به همراه سینتسایزر داخلی کلاس‌های Logical سرو می‌شود.
3. **بومی‌سازی عمیق فارسی و تقویم شمسی (Jalali):**
   - بارگذاری محلی فونت متغیر **Vazirmatn** (`woff2`) با `font-display: swap`، `unicode-range` تفکیک‌شده و `preload` بدون هیچ درخواستی به CDN خارجی.
   - توابع کمکی سراسری `twb_localize_digits()`، `twb_normalize_digits()`، `twb_format_date()` (با تشخیص خودکار `WP-Parsidate` و مبدل داخلی میلادی به شمسی) و `twb_ascii_id()`.

### فاز ۲: کتابخانهٔ ۱۴ بلوک گوتنبرگ (Block API v3)، کنترل Tailwind Class و سخت‌سازی امنیتی
1. **۱۴ بلوک استاندارد و سئومحور:**
   - `section`, `grid`, `heading`, `text`, `button`, `image` (بدون CLS با ابعاد اجباری), `icon` (پاک‌سازی‌شدهٔ SVG بدون فونت آیکون), `spacer`, `divider`, `faq` (مبتنی بر `<details>` با ۰ بایت JS + اسکیمای یکتای `FAQPage`), `tabs` (اسکریپت شرطی زیر ۱KB با ناوبری کیبورد RTL), `card`, `icon-box`, و `post-grid` (رندر سمت سرور با `WP_Query` بهینه و تاریخ شمسی).
2. **کنترل سراسری «Tailwind Class» در پنل کناری گوتنبرگ:**
   - تزریق به تمامی بلوک‌ها از طریق فیلتر `editor.BlockEdit` با `dir="ltr"` اجباری، تکمیل خودکار، تفکیک Breakpoint (`sm`/`md`/`lg`/`xl`) و State (`hover`/`focus`/`dark`/`rtl`)، هشدار استفاده از کلاس‌های جهت‌دار فیزیکی و کامپایل پیش‌نمایش زنده (Debounced 400ms) در `iframe` ویرایشگر.
3. **سخت‌سازی امنیتی (`SecurityHelper.php`):**
   - لیست سفید تگ‌های معنایی HTML، پاک‌سازی آیکون‌های SVG با `wp_kses`، بررسی بایت جادویی فایل فونت WOFF2 (`wOF2` = `0x774F4632`)، محافظت در برابر خروج از تگ `</style>`، محافظت SSRF روی آدرس سرور بیلد راه دور، و محدودکنندهٔ نرخ درخواست (Rate Limiting) روی REST API.

### فاز ۳: کتابخانهٔ الگوهای آماده (Block Patterns)، سیستم Export/Import و دستورات WP-CLI
1. **۶ الگوی آمادهٔ فارسی و راست‌به‌چپ (`patterns/*.php`):**
   - `tw-blocks/hero-persian` (بخش معرفی محصول با دو دکمه و آمار)
   - `tw-blocks/features-grid` (شبکهٔ ۳ ستونهٔ ویژگی‌ها با آیکون SVG)
   - `tw-blocks/pricing-jalali` (جدول تعرفه و پلن‌های اشتراک سالانه به تومان)
   - `tw-blocks/faq-section` (بخش سوالات متداول سئومحور با اسکیمای JSON-LD)
   - `tw-blocks/cta-banner` (بنر فراخوان اقدام راست‌به‌چپ)
   - `tw-blocks/blog-showcase` (ویترین آخرین نوشته‌ها با تاریخ شمسی)
2. **مدیریت برون‌ریزی و درون‌ریزی (`PortabilityManager.php`):**
   - خروجی و ورودی JSON برای توکن‌های طراحی، تنظیمات و Safelist با حذف خودکار کلید محرمانهٔ `remote_api_key`، سقف حجم ۲۵۶ کیلوبایت، اعتبارسنجی کامل و بازسازی خودکار کش CSS.
3. **دستورات خط فرمان (`CliCommand.php`):**
   - پشتیبانی از `wp tw-blocks status`، `wp tw-blocks build [--force]`، `wp tw-blocks purge`، `wp tw-blocks export` و `wp tw-blocks import`.

### فاز ۲ (بخش دوم): بلوک‌های لوپ و کوئری (`QueryLoopExtension`) و ویجت‌های تعاملی
1. **گسترش بومی `core/query` (بدون موتور مجزا):**
   - ثبت ۴ واریانت آماده (`Block Variations`) روی `core/query`: **«آخرین مقالات»** (`twb-latest-posts`)، **«آخرین محصولات»** (`twb-latest-products`)، **«مقالات مرتبط»** (`twb-related-posts`) و **«کارت وبلاگ ۳ ستونه»** (`twb-blog-cards-3col`).
   - فیلتر `query_loop_block_query_vars` به همراه کنترل‌های سایدبار فارسی در گوتنبرگ برای: **فقط پست‌های ویژه** (`twbFeaturedOnly`)، **مرتب‌سازی بر اساس بازدید یا متا** (`twbOrderByMeta` با لیست سفید کلیدهای متا)، **حذف پست جاری** (`twbExcludeCurrent`) و **آفست** (`offset`) با کش Transient پانزده‌دقیقه‌ای برای کوئری‌های سنگین.
2. **بلوک حلقهٔ سفارشی با `InnerBlocks` (`tw-blocks/custom-loop`):**
   - امکان طراحی قالب آیتم دلخواه توسط کاربر با `InnerBlocks`، رندر سمت سرور در `render.php` با تزریق `postId`/`postType` به زمینهٔ بلوک، چیدمان `grid`/`list`، ستون‌های واکنش‌گرا، و صفحه‌بندی عددی یا دکمهٔ **«بارگذاری بیشتر»** (با اسکریپت شرطی زیر ۱.۵KB).
3. **ویجت‌های تعاملی سبک و الگوها:**
   - **بلوک اسلایدر (`tw-blocks/slider`):** مبتنی بر CSS `scroll-snap` بدون Swiper، زیر ۲ کیلوبایت JS و تشخیص هوشمند جهت اسکرول منفی در RTL.
   - **بلوک شمارنده (`tw-blocks/counter`):** انیمیشن عدد با `IntersectionObserver` و ارقام فارسی.
   - **بلوک فهرست مطالب پویا (`tw-blocks/toc`):** استخراج خودکار تیترهای `H2` تا `H4`، تزریق شناسه‌های لنگر ASCII ایمن برای فارسی و رندر بدون جاوااسکریپت با `<details>`.
   - **بلوک پنجرهٔ مودال (`tw-blocks/modal`):** مبتنی بر تگ استاندارد `<dialog>` با جاوااسکریپت زیر ۱ کیلوبایت.
   - **ویجت‌های ساده به‌صورت الگو (`Pattern`):** الگوی نظرات مشتریان (`tw-blocks/testimonial-grid`) و جدول تعرفه (`tw-blocks/pricing-jalali`).

### فاز ۳ (بخش دوم): یکپارچه‌سازی عمیق ووکامرس (`WooIntegration`)
1. **واریانت‌های `woocommerce/product-collection`:**
   - ۴ واریانت آماده با کارت محصول Tailwind: **«پرفروش‌ها»** (`twb-wc-best-sellers`)، **«تخفیف‌دارها»** (`twb-wc-on-sale`)، **«جدیدترین‌ها»** (`twb-wc-newest`) و **«محصولات مرتبط»** (`twb-wc-related`).
2. **۴ قالب بلوکی آماده (`templates/*.html`):**
   - `archive-product.html`، `taxonomy-product_cat.html`، `single-product.html` (دارای گالری، تب‌ها، سوالات متداول و **نوار خرید چسبنده در موبایل**) و `product-search-results.html`.
3. **بلوک‌ها و الگوهای تکمیلی فروشگاهی:**
   - **بج تخفیف و شمارندهٔ جشنواره (`tw-blocks/wc-sale-timer`):** محاسبهٔ خودکار درصد تخفیف و تایمر معکوس `_sale_price_dates_to` با ارقام فارسی و تاریخ پایان شمسی.
   - **محصولات اخیراً دیده‌شده (`tw-blocks/wc-recently-viewed`):** خواندن ایمن کوکی `woocommerce_recently_viewed` و رندر کارت‌های محصول.
   - **الگوی نوار مزایای فروشگاه (`tw-blocks/wc-benefits-bar`)** و **الگوی اسلایدر محصولات (`tw-blocks/wc-product-slider`)**.
4. **سبد خرید، تسویه‌حساب و بارگذاری شرطی (`Conditional Loading`):**
   - پیش‌فرض بر پایهٔ شورت‌کدهای کلاسیک ووکامرس با استایل اختصاصی RTL (`woocommerce-cart-checkout.css`) جهت سازگاری ۱۰۰٪ با افزونه‌های حمل‌ونقل و درگاه‌های ایرانی؛ به همراه گزینهٔ حالت بلوکی در تنظیمات که صرفاً با متغیرهای CSS (`--wc-blocks-*`) رنگ و فونت را هماهنگ می‌کند.
   - بارگذاری شرطی `woocommerce-shop.css` فقط در `is_woocommerce()` و `woocommerce-cart-checkout.css` فقط در `is_cart() || is_checkout() || is_account_page()`.
   - جلوگیری از تکرار اسکیمای `Product` و `FAQPage` در صورت فعال بودن ووکامرس، Yoast SEO یا RankMath.

---

## ۲. دستورات بیلد، WP-CLI، تست خودکار و ساخت فایل نصبی (`tw-blocks.zip`)

```bash
cd tw-blocks

# ۱. اجرای تست‌های خودکار امنیت (Sanitizer + SecurityHelper) و توابع فارسی/شمسی
php tests/SecurityAndPersianTest.php

# ۲. دستورات WP-CLI برای تولید CSS در زمان بیلد و پاک‌سازی کش‌های ناقص
wp twb build --all
wp twb purge --corrupted
wp twb status

# ۳. کامپایل فایل باینری ترجمهٔ فارسی (.po -> .mo) و تولید فایل‌های JSON برای JS
node bin/compile-mo.mjs
wp i18n make-json languages/tw-blocks-fa_IR.po --no-purge

# ۴. ساخت بستهٔ نهایی ZIP برای نصب در وردپرس
zip -r ../tw-blocks-v1.0.0.zip . \
  -x "node_modules/*" \
  -x "server-reference/node_modules/*" \
  -x ".git/*"
```

---

## ۳. چک‌لیست تست سازگاری با درگاه‌ها و افزونه‌های رایج ایرانی (Iranian Ecosystem Compatibility Checklist)

- [x] **درگاه‌های پرداخت مستقیم و واسط (زرین‌پال، به‌پرداخت ملت، سامان کیش، پارسیان، زیبال، پی‌پینگ، اسنپ‌پی / اسنپ‌پی اقساطی):**
  - به دلیل استفادهٔ پیش‌فرض از شورت‌کد کلاسیک `[woocommerce_checkout]` و عدم دستکاری DOM فرم پرداخت توسط کلاس‌های مخرب Tailwind، تمامی هوک‌های `woocommerce_review_order_before_payment` و بازگشت از بانک (`wc-api`) بدون تداخل کار می‌کنند.
- [x] **افزونهٔ ووکامرس فارسی (`Persian WooCommerce`):**
  - سازگاری کامل با واحدهای پولی `IRT` (تومان)، `IRR` (ریال) و `IRHT` (هزار تومان)، انتخابگر شهر و استان در تسویه‌حساب کلاسیک و جایگزینی اعداد فارسی.
- [x] **افزونه‌های حمل‌ونقل و لجستیک ایرانی (حمل‌ونقل ووکامرس فارسی، تاپین `Tapin`، پستکس `Postex`، الوپیک):**
  - حفظ کامل ساختار استاندارد فیلدهای `billing_state`، `billing_city` و جدول محاسبهٔ هزینهٔ ارسال در سبد خرید و تسویه‌حساب.
- [x] **موتورهای مقایسهٔ قیمت (ترب `Torob`، ایمالز `Emalls`، ذره‌بین):**
  - عدم تکرار یا تخریب متاتگ‌های قیمت (`product:price:amount`) و JSON-LD استاندارد ووکامرس (`WooIntegration::should_skip_duplicate_schema('Product')`) که ربات‌های ترب و ایمالز برای استخراج قیمت و موجودی به آن وابسته‌اند.
- [x] **افزونه‌های پیامکی و ورود با موبایل (دیجیتس `Digits`، ملی‌پیامک، کاوه‌نگار، فراز اس‌ام‌اس):**
  - ایزوله‌سازی ریست استایل‌ها درون `.twb-root` باعث می‌شود پاپ‌آپ‌ها و فرم‌های OTP افزونهٔ Digits دچار به‌هم‌ریختگی فونت یا جهت ورودی نشوند.
- [x] **افزونه‌های سئو و تاریخ شمسی (`Yoast SEO`، `RankMath`، `WP-Parsidate`):**
  - تشخیص خودکار `WP-Parsidate` در `PersianHelper::format_jalali_date()` و جلوگیری از تولید اسکیمای تکراری در حضور Yoast و RankMath.

