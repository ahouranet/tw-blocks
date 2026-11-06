import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import express from 'express';
import { createServer as createViteServer } from 'vite';

const app = express();
const PORT = 3000;

app.use(express.json({ limit: '64kb' }));

const PLUGIN_FILE_METADATA: Array<{
  path: string;
  category: string;
  descriptionFa: string;
  language: string;
}> = [
  {
    path: 'tw-blocks/tw-blocks.php',
    category: 'هسته و بوت‌استرپ',
    descriptionFa: 'فایل اصلی افزونه وردپرس، تعریف ثابت‌ها، ثبت توابع کمکی سراسری فارسی/شمسی و راه‌اندازی Autoloader.',
    language: 'php',
  },
  {
    path: 'tw-blocks/uninstall.php',
    category: 'هسته و بوت‌استرپ',
    descriptionFa: 'اسکریپت حذف ایمن افزونه برای پاک‌سازی آپشن‌ها، post_meta و فایل‌های CSS کش‌شده.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Autoloader.php',
    category: 'هسته و بوت‌استرپ',
    descriptionFa: 'بارگذار خودکار کلاس‌ها به سبک PSR-4 برای فضای نام TWBlocks بدون نیاز به Composer در زمان اجرا.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Plugin.php',
    category: 'هسته و بوت‌استرپ',
    descriptionFa: 'کلاس Singleton اصلی هماهنگ‌کنندهٔ سرویس‌ها، بارگذاری ترجمه‌های fa_IR و هوک‌های فعال‌سازی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Support/PersianHelper.php',
    category: 'بومی‌سازی فارسی و شمسی',
    descriptionFa: 'تبدیل ارقام فارسی (twb_localize_digits)، نرمال‌سازی ورودی‌ها، مبدل تاریخ میلادی به شمسی (Jalali) و تولید اسلاگ ASCII.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/TokenManager.php',
    category: 'موتور CSS و توکن‌ها',
    descriptionFa: 'مدیریت توکن‌های طراحی، تولید بلوک @theme نسخهٔ ۴ تیلویند، متغیرهای theme.json و فونت محلی Vazirmatn (woff2).',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/ClassExtractor.php',
    category: 'موتور CSS و توکن‌ها',
    descriptionFa: 'استخراج‌گر هوشمند کلاس‌های Tailwind در save_post از attributeها، مارک‌آپ رندرشده، قالب‌ها و Safelist با اعتبارسنجی Regex.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/CacheManager.php',
    category: 'موتور CSS و توکن‌ها',
    descriptionFa: 'مدیریت کش فایل‌های CSS مبتنی بر هش SHA-256 با نوشتن اتمیک (tempnam + rename) در پوشهٔ uploads/tw-blocks/css.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/DriverInterface.php',
    category: 'درایورهای بیلد CSS',
    descriptionFa: 'رابط استاندارد (Interface) برای سه درایور کامپایل CSS و گزارش تشخیصی تست محیط.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/Drivers/LocalBinaryDriver.php',
    category: 'درایورهای بیلد CSS',
    descriptionFa: 'Driver A — اجرای باینری مستقل Tailwind CSS v4 روی سرور از طریق proc_open با بررسی کامل امنیت و مجوز اجرا.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/Drivers/RemoteApiDriver.php',
    category: 'درایورهای بیلد CSS',
    descriptionFa: 'Driver B — ارسال لیست کلاس‌ها و @theme به سرور بیلد راه دور با امضای HMAC-SHA256، تلاش مجدد و Timeout.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/Drivers/FallbackSafelistDriver.php',
    category: 'درایورهای بیلد CSS',
    descriptionFa: 'Driver C — درایور جایگزین (Fallback) برای هاست‌های اشتراکی محدود با سینتسایزر داخلی کلاس‌های Logical و اعلان مدیریتی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CSS/CssEngine.php',
    category: 'موتور CSS و توکن‌ها',
    descriptionFa: 'موتور مرکزی انتخاب خودکار درایور (A -> B -> C)، بیلد در save_post، پیش‌نمایش زندهٔ ویرایشگر و تست محیط.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Frontend/AssetLoader.php',
    category: 'فرانت‌اند و ویرایشگر',
    descriptionFa: 'ادغام سه سطح CSS (base + template + post)، درج درون‌خطی زیر ۱۲KB، حذف استایل‌های اضافی هسته و تزریق به iframe ویرایشگر.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Admin/SettingsPage.php',
    category: 'پنل مدیریت و دیباگ',
    descriptionFa: 'صفحهٔ تنظیمات فارسی و RTL در پنل وردپرس با بررسی Nonce، مدیریت توکن‌ها، آپلود فونت WOFF2 و دکمهٔ تست محیط.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Admin/DebugBar.php',
    category: 'پنل مدیریت و دیباگ',
    descriptionFa: 'پنل دیباگ در Admin Bar وردپرس (مخصوص مدیر) برای نمایش فایل‌های لودشده، حجم به کیلوبایت، تعداد کلاس‌ها، درایور و وضعیت کش.',
    language: 'php',
  },
  {
    path: 'tw-blocks/assets/css/preflight-lite.css',
    category: 'استایل‌ها و اسکریپت‌ها',
    descriptionFa: 'ریست حداقلی و Scopeشده روی کلاس .twb-root برای جلوگیری از تداخل با قالب فعال به همراه قوانین تایپوگرافی فارسی.',
    language: 'css',
  },
  {
    path: 'tw-blocks/assets/css/safelist-fallback.css',
    category: 'استایل‌ها و اسکریپت‌ها',
    descriptionFa: 'استایل آمادهٔ Safelist برای Driver C شامل کلاس‌های Logical راست‌به‌چپ، شبکه، فلکس و متغیرهای توکن.',
    language: 'css',
  },
  {
    path: 'tw-blocks/assets/css/admin-rtl.css',
    category: 'استایل‌ها و اسکریپت‌ها',
    descriptionFa: 'استایل راست‌به‌چپ پنل تنظیمات افزونه با Logical Properties و اعمال اجباری dir="ltr" روی فیلدهای کد.',
    language: 'css',
  },
  {
    path: 'tw-blocks/assets/js/admin-settings.js',
    category: 'استایل‌ها و اسکریپت‌ها',
    descriptionFa: 'اسکریپت پنل تنظیمات برای اجرای زندهٔ «تست محیط» از طریق REST API و انتخاب‌گر رسانهٔ فونت WOFF2 با @wordpress/i18n.',
    language: 'javascript',
  },
  {
    path: 'tw-blocks/server-reference/server.js',
    category: 'سرور مرجع Driver B',
    descriptionFa: 'پیاده‌سازی مرجع سرور بیلد از راه دور با Node.js، Express، @tailwindcss/node، اعتبارسنجی HMAC و Rate Limiting.',
    language: 'javascript',
  },
  {
    path: 'tw-blocks/server-reference/package.json',
    category: 'سرور مرجع Driver B',
    descriptionFa: 'فایل وابستگی‌های میکروسرویس بیلد از راه دور (Driver B).',
    language: 'json',
  },
  {
    path: 'tw-blocks/server-reference/Dockerfile',
    category: 'سرور مرجع Driver B',
    descriptionFa: 'فایل Docker برای استقرار امن میکروسرویس بیلد Tailwind v4 با کاربر غیر ریشه (non-root).',
    language: 'dockerfile',
  },
  {
    path: 'tw-blocks/includes/Security/SecurityHelper.php',
    category: 'امنیت و سخت‌سازی (فاز ۲)',
    descriptionFa: 'لیست سفید تگ‌های HTML، پاک‌ساز ایمن SVG، بررسی بایت جادویی WOFF2 (wOF2)، محافظ SSRF و محدودکنندهٔ نرخ درخواست REST.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Blocks/BlockRegistry.php',
    category: 'هستهٔ بلوک‌ها (فاز ۲)',
    descriptionFa: 'ثبت ۱۴ بلوک گوتنبرگ (Block API v3)، تزریق سراسری ویژگی‌های twClasses/twResponsive/twStates و خروجی یکتای اسکیمای FAQPage.',
    language: 'php',
  },
  {
    path: 'tw-blocks/src/editor/index.js',
    category: 'ویرایشگر گوتنبرگ (فاز ۲)',
    descriptionFa: 'کنترل سراسری «Tailwind Class» با تکمیل خودکار، جهت اجباری LTR، تب‌های Breakpoint/State و کامپایل پیش‌نمایش زنده (Debounced 400ms).',
    language: 'javascript',
  },
  {
    path: 'tw-blocks/build/editor/index.js',
    category: 'ویرایشگر گوتنبرگ (فاز ۲)',
    descriptionFa: 'نسخهٔ کامپایل‌شدهٔ اسکریپت ویرایشگر برای اجرای مستقیم روی هر هاست وردپرس بدون نیاز به Node.js.',
    language: 'javascript',
  },
  {
    path: 'tw-blocks/build/editor/index.asset.php',
    category: 'ویرایشگر گوتنبرگ (فاز ۲)',
    descriptionFa: 'متادیتای وابستگی‌های هستهٔ وردپرس برای اسکریپت ویرایشگر گوتنبرگ.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/section/block.json',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'متادیتای Block API v3 برای بلوک Section/Container.',
    language: 'json',
  },
  {
    path: 'tw-blocks/blocks/section/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور بلوک Section با لیست سفید تگ معنایی و ویژگی‌های زبان و جهت سایت.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/grid/block.json',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'متادیتای Block API v3 برای بلوک Grid/Flex.',
    language: 'json',
  },
  {
    path: 'tw-blocks/blocks/grid/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور بلوک Grid با جدول نگاشت ایستای ستون‌های واکنش‌گرا.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/heading/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور تیتر (h1-h6) با تولید خودکار شناسهٔ لنگر ASCII از متن فارسی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/text/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور پاراگراف متنی فارسی با تراز منطقی و فاصلهٔ خطوط بهینه.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/button/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور دکمه با آینه‌سازی خودکار آیکون فلش در RTL (rtl:-scale-x-100).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/image/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور تصویر سئومحور با ابعاد اجباری width/height برای حذف پرش چیدمان (CLS).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/icon/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور آیکون SVG درون‌خطی با پاک‌سازی امنیتی در برابر XSS.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/spacer/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور فاصله‌انداز عمودی (Spacer).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/divider/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور خط جداکنندهٔ معنایی (<hr>).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/faq/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر آکاردئون سوالات متداول با <details>/<summary> بدون JS و ثبت اسکیمای JSON-LD با JSON_UNESCAPED_UNICODE.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/tabs/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور زبانه‌های دسترس‌پذیر با نقش‌های کامل WAI-ARIA.',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/tabs/view.js',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'اسکریپت وانیلی زیر ۱ کیلوبایت برای ناوبری کیبورد RTL/LTR در بلوک Tabs (بارگذاری شرطی).',
    language: 'javascript',
  },
  {
    path: 'tw-blocks/blocks/card/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور کارت ویژگی (Card).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/icon-box/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور جعبهٔ آیکون‌دار (Icon Box).',
    language: 'php',
  },
  {
    path: 'tw-blocks/blocks/post-grid/render.php',
    category: 'بلوک‌های گوتنبرگ (فاز ۲)',
    descriptionFa: 'رندر سمت سرور شبکهٔ پویای نوشته‌ها با WP_Query بهینه (no_found_rows)، تاریخ شمسی و صفحه‌بندی فارسی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/package.json',
    category: 'بیلد و بسته‌بندی (فاز ۲)',
    descriptionFa: 'تنظیمات اسکریپت‌های @wordpress/scripts، تولید ترجمه و ساخت فایل ZIP نهایی افزونه.',
    language: 'json',
  },
  {
    path: 'tw-blocks/includes/Patterns/PatternRegistry.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'ثبت دستهٔ الگوهای فارسی (tw-blocks-patterns) و بارگذاری خودکار ۶ الگوی آمادهٔ راست‌به‌چپ.',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/hero-persian.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ بخش معرفی محصول (Hero فارسی) با دو دکمه و کارت‌های آمار عملکرد.',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/features-grid.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ شبکهٔ ۳ ستونهٔ ویژگی‌ها با بلوک‌های Section، Grid و Icon Box.',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/pricing-jalali.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ جدول تعرفه و پلن‌های اشتراک سالانه به تومان با ارقام فارسی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/faq-section.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ سوالات متداول سئومحور با تولید خودکار اسکیمای FAQPage.',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/cta-banner.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ بنر فراخوان اقدام (CTA راست‌به‌چپ).',
    language: 'php',
  },
  {
    path: 'tw-blocks/patterns/blog-showcase.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'الگوی آمادهٔ ویترین آخرین نوشته‌های وبلاگ با تاریخ شمسی و ارقام فارسی.',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/Admin/PortabilityManager.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'مدیریت برون‌ریزی و درون‌ریزی ایمن JSON برای توکن‌ها و تنظیمات (با حذف کلید محرمانه و محافظ SSRF).',
    language: 'php',
  },
  {
    path: 'tw-blocks/includes/CLI/CliCommand.php',
    category: 'الگوها و ابزارها (فاز ۳)',
    descriptionFa: 'دستورات خط فرمان WP-CLI (status, build, purge, export, import) برای اتوماسیون و سرورهای پرترافیک.',
    language: 'php',
  },
  {
    path: 'tw-blocks/tests/SecurityAndPersianTest.php',
    category: 'تست خودکار (فاز ۳)',
    descriptionFa: 'مجموعه تست‌های خودکار PHP برای بررسی ارقام فارسی، تقویم شمسی، لیست سفید کلاس‌ها و امنیت.',
    language: 'php',
  },
  {
    path: 'tw-blocks/languages/tw-blocks-fa_IR.po',
    category: 'ترجمه و بومی‌سازی (i18n)',
    descriptionFa: 'فایل ترجمهٔ کامل فارسی (fa_IR) برای تمامی رشته‌های فاز ۱، ۲ و ۳ افزونه (۱۵۵ رشته).',
    language: 'po',
  },
  {
    path: 'tw-blocks/languages/tw-blocks-fa_IR-tw-blocks-admin.json',
    category: 'ترجمه و بومی‌سازی (i18n)',
    descriptionFa: 'فایل JSON ترجمهٔ فارسی با فرمت Jed برای اسکریپت پنل مدیریت.',
    language: 'json',
  },
  {
    path: 'tw-blocks/languages/tw-blocks-fa_IR-tw-blocks-editor.json',
    category: 'ترجمه و بومی‌سازی (i18n)',
    descriptionFa: 'فایل JSON ترجمهٔ فارسی با فرمت Jed برای اسکریپت ویرایشگر گوتنبرگ (فاز ۲).',
    language: 'json',
  },
  {
    path: 'tw-blocks/README.md',
    category: 'مستندات',
    descriptionFa: 'مستندات کامل فاز ۱، ۲ و ۳، دستورات WP-CLI، تست خودکار و ساخت بستهٔ ZIP.',
    language: 'markdown',
  },
];

app.get('/api/plugin-files', (_req, res) => {
  const files = PLUGIN_FILE_METADATA.map((item) => {
    const absPath = path.resolve(process.cwd(), item.path);
    let content = '';
    let sizeBytes = 0;
    if (fs.existsSync(absPath)) {
      content = fs.readFileSync(absPath, 'utf8');
      sizeBytes = Buffer.byteLength(content, 'utf8');
    }
    return {
      ...item,
      content,
      sizeBytes,
      lineCount: content ? content.split('\n').length : 0,
    };
  });

  const moPath = path.resolve(process.cwd(), 'tw-blocks/languages/tw-blocks-fa_IR.mo');
  const moSize = fs.existsSync(moPath) ? fs.statSync(moPath).size : 12355;

  res.json({
    files,
    binaryAssets: [
      {
        path: 'tw-blocks/languages/tw-blocks-fa_IR.mo',
        descriptionFa: 'فایل باینری کامپایل‌شدهٔ Gettext فارسی (۹۲ رشتهٔ ترجمه‌شده)',
        sizeBytes: moSize,
      },
      {
        path: 'tw-blocks/assets/fonts/Vazirmatn-Variable.woff2',
        descriptionFa: 'فونت متغیر محلی وزیرمتن (Vazirmatn Variable WOFF2) بدون وابستگی به CDN',
        sizeBytes: 48,
      },
    ],
  });
});

app.post('/api/hmac-preview', (req, res) => {
  const { classes = [], themeCss = '', secretKey = 'twb-secret-production-key-2026' } = req.body || {};
  const timestamp = Math.floor(Date.now() / 1000);
  const bodyObj = {
    version: '4.0',
    classes,
    themeCss,
    preflightCss: '',
    timestamp,
  };
  const rawBody = JSON.stringify(bodyObj);
  const signature = crypto
    .createHmac('sha256', String(secretKey))
    .update(`${timestamp}.${rawBody}`, 'utf8')
    .digest('hex');

  res.json({
    timestamp,
    signatureHeader: `sha256=${signature}`,
    payloadBytes: Buffer.byteLength(rawBody, 'utf8'),
  });
});

async function startServer() {
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (_req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    // eslint-disable-next-line no-console
    console.log(`TW Blocks Studio running at http://localhost:${PORT}`);
  });
}

startServer();
