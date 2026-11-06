import React, { useState, useMemo } from 'react';
import {
  LayoutTemplate,
  Download,
  Upload,
  Terminal,
  Copy,
  Check,
  CheckCircle2,
  AlertTriangle,
} from 'lucide-react';
import {
  DesignTokens,
  PluginSettings,
  createExportBundle,
  validateAndImportBundle,
  extractAndValidateClasses,
  twbLocalizeDigits,
} from '../engine/twBlocksEngine';

interface PatternItem {
  slug: string;
  name: string;
  titleFa: string;
  categoryFa: string;
  descriptionFa: string;
  blockMarkup: string;
}

const BUNDLED_PATTERNS: PatternItem[] = [
  {
    slug: 'hero-persian',
    name: 'tw-blocks/hero-persian',
    titleFa: 'بخش معرفی محصول (Hero فارسی) با دو دکمه و آمار',
    categoryFa: 'هدر و بنر',
    descriptionFa:
      'الگوی راست‌به‌چپ شامل تیتر اصلی H1 فارسی با نیم‌فاصله، پاراگراف معرفی، دکمه‌های دوگانه و ۳ کارت آمار عملکرد.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-24","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200 shadow-sm"} -->
<!-- wp:tw-blocks/heading {"level":"h1","content":"طراحی سایت‌های وردپرسی سریع، سئو‌محور و بومی با «TW Blocks»","textAlign":"text-start","twClasses":"text-3xl font-bold text-slate-900 mb-4","twResponsive":{"md":"text-4xl"}} /-->
<!-- wp:tw-blocks/text {"content":"بدون بارگذاری استایل‌های سنگین و بدون وابستگی به CDN خارجی؛ تنها کلاس‌های واقعاً استفاده‌شده در هر برگه روی سرور کامپایل و به‌صورت استاتیک کش می‌شوند.","textAlign":"text-start","twClasses":"text-lg leading-relaxed text-slate-600 mb-8 max-w-5xl"} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"flex","gap":"gap-4","alignItems":"items-center","justifyContent":"justify-start","twClasses":"mb-8"} -->
<!-- wp:tw-blocks/button {"label":"شروع ساخت برگه","url":"#get-started","variant":"primary","showArrowIcon":true,"twClasses":"px-6 py-3 rounded-lg font-semibold shadow-sm"} /-->
<!-- wp:tw-blocks/button {"label":"مشاهدهٔ مستندات فنی","url":"#docs","variant":"outline","showArrowIcon":false,"twClasses":"px-6 py-3 rounded-lg font-semibold"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'features-grid',
    name: 'tw-blocks/features-grid',
    titleFa: 'شبکهٔ ۳ ستونهٔ ویژگی‌ها با آیکون SVG',
    categoryFa: 'خدمات و ویژگی‌ها',
    descriptionFa:
      'نمایش ۳ ستونه و واکنش‌گرای ویژگی‌ها با بلوک‌های Section، Grid و Icon Box ایمن بدون فونت آیکون.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":2,"colsLg":3,"gap":"gap-6","alignItems":"items-stretch"} -->
<!-- wp:tw-blocks/icon-box {"iconName":"bolt","title":"عملکرد بدون جی‌کوئری","description":"هیچ کتابخانهٔ سنگینی در فرانت‌اند بارگذاری نمی‌شود."} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"shield","title":"امنیت چندلایه در سطح هسته","description":"لیست سفید Regex برای کلاس‌های تیلویند و بررسی بایت جادویی WOFF2."} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"sparkles","title":"تقویم شمسی و ارقام فارسی","description":"تبدیل خودکار تاریخ نوشته‌ها به هجری شمسی و نمایش ارقام فارسی."} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'pricing-jalali',
    name: 'tw-blocks/pricing-jalali',
    titleFa: 'جدول تعرفه و پلن‌های اشتراک (تومان)',
    categoryFa: 'فروش و تعرفه',
    descriptionFa:
      'جدول سه سطحی قیمت‌گذاری سالانه به تومان با ارقام فارسی و برجسته‌سازی پلن پیشنهادی آژانسی.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":3,"colsLg":3,"gap":"gap-6","alignItems":"items-stretch"} -->
<!-- wp:tw-blocks/card {"badge":"پلن استارتر (۱ سایت)","title":"۹۸۰٬۰۰۰ تومان / سالانه","description":"مناسب برای وبلاگ‌های تخصصی و سایت‌های شخصی.","linkLabel":"انتخاب پلن استارتر","linkUrl":"#plan-starter"} /-->
<!-- wp:tw-blocks/card {"badge":"پرفروش‌ترین — پلن آژانسی (۱۰ سایت)","title":"۲٬۴۵۰٬۰۰۰ تومان / سالانه","description":"مناسب برای طراحان سایت و آژانس‌های دیجیتال.","linkLabel":"سفارش پلن آژانسی","linkUrl":"#plan-agency","twClasses":"rounded-2xl border-2 border-primary bg-white p-6 shadow-md"} /-->
<!-- wp:tw-blocks/card {"badge":"پلن سازمانی (نامحدود)","title":"۴٬۹۰۰٬۰۰۰ تومان / سالانه","description":"مناسب برای سازمان‌ها و پورتال‌های پرترافیک.","linkLabel":"تماس با واحد فروش","linkUrl":"#plan-enterprise"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'faq-section',
    name: 'tw-blocks/faq-section',
    titleFa: 'بخش سوالات متداول سئومحور با اسکیمای JSON-LD',
    categoryFa: 'محتوا و سئو',
    descriptionFa:
      'بخش سوالات متداول بدون جاوااسکریپت با تگ‌های <details>/<summary> و تولید خودکار اسکیمای FAQPage.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-5xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/faq {"enableSchema":true} /-->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'cta-banner',
    name: 'tw-blocks/cta-banner',
    titleFa: 'بنر فراخوان اقدام (CTA راست‌به‌چپ)',
    categoryFa: 'فراخوان اقدام',
    descriptionFa:
      'بنر فراخوان اقدام با کنتراست بالا، تایپوگرافی فارسی و آیکون فلش آینه‌ای در RTL.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"aside","maxWidth":"max-w-7xl","paddingY":"py-12","paddingX":"px-8","bgVariant":"secondary","twClasses":"rounded-2xl shadow-md"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"آمادهٔ جایگزینی صفحه‌سازهای سنگین با گوتنبرگ و Tailwind CSS v4 هستید؟","textAlign":"text-start","twClasses":"text-2xl font-bold text-white mb-3"} /-->
<!-- wp:tw-blocks/button {"label":"ورود به تنظیمات توکن‌ها","url":"#settings","variant":"primary","showArrowIcon":true} /-->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'blog-showcase',
    name: 'tw-blocks/blog-showcase',
    titleFa: 'ویترین آخرین نوشته‌ها با تاریخ شمسی',
    categoryFa: 'وبلاگ و پویا',
    descriptionFa:
      'نمایش آخرین مقاله‌های وبلاگ با تاریخ انتشار شمسی (جلالی)، ارقام فارسی و کوئری بهینه.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/post-grid {"postsPerPage":3,"columns":3,"showJalaliDate":true,"showExcerpt":true,"showPagination":false,"readMoreLabel":"ادامهٔ مطلب","twClasses":"gap-6"} /-->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'testimonial-grid',
    name: 'tw-blocks/testimonial-grid',
    titleFa: 'شبکهٔ ۳ ستونهٔ نظرات مشتریان (Testimonial)',
    categoryFa: 'اعتمادسازی',
    descriptionFa:
      'ویجت سادهٔ نظرات مشتریان پیاده‌سازی‌شده به‌صورت Pattern سبک بدون سربار جاوااسکریپت.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":3,"colsLg":3,"gap":"gap-6"} -->
<!-- wp:tw-blocks/card {"badge":"★★★★★ — فروشگاه مد و پوشاک","title":"کاهش حجم CSS از ۴۲۰ کیلوبایت به ۶ کیلوبایت","description":"امتیاز موبایل گوگل ما از ۵۴ به ۹۸ رسید و سرعت بارگذاری صفحهٔ محصول دو برابر شد."} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'wc-benefits-bar',
    name: 'tw-blocks/wc-benefits-bar',
    titleFa: 'نوار مزایای فروشگاه ووکامرس (ارسال، گارانتی، پرداخت)',
    categoryFa: 'ووکامرس',
    descriptionFa:
      'نوار ۴ ستونهٔ مزایای خرید برای استفاده در آرشیو فروشگاه و صفحهٔ تک‌محصول ووکامرس.',
    blockMarkup: `<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-6","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200 shadow-2xs my-6"} -->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":2,"colsLg":4,"gap":"gap-6"} -->
<!-- wp:tw-blocks/icon-box {"iconName":"bolt","layout":"horizontal","title":"ارسال سریع و اکسپرس","description":"ارسال ۳ ساعته در تهران و پیشتاز به سراسر ایران"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->`,
  },
  {
    slug: 'wc-product-slider',
    name: 'tw-blocks/wc-product-slider',
    titleFa: 'اسلایدر محصولات ووکامرس (CSS Scroll-Snap)',
    categoryFa: 'ووکامرس',
    descriptionFa:
      'اسلایدر محصولات تخفیف‌دار و پرفروش بر پایهٔ CSS scroll-snap بدون Swiper و سازگار با RTL.',
    blockMarkup: `<!-- wp:tw-blocks/slider {"slidesPerViewMd":2,"slidesPerViewLg":4,"gap":"gap-6","showArrows":true,"ariaLabel":"اسلایدر محصولات شگفت‌انگیز"} -->
<!-- wp:tw-blocks/card {"badge":"۲۵٪ تخفیف ویژه","title":"هدفون بی‌سیم نویزکنسلینگ پرو","description":"۴٬۸۵۰٬۰۰۰ تومان — ارسال رایگان امروز","linkLabel":"مشاهده و خرید","linkUrl":"#product-1","twClasses":"min-w-[260px] snap-start p-5 rounded-2xl border border-slate-200 bg-white"} /-->
<!-- /wp:tw-blocks/slider -->`,
  },
];

interface PatternsAndPortabilityTabProps {
  tokens: DesignTokens;
  settings: PluginSettings;
  onImportSuccess: (nextTokens: DesignTokens, nextSettings: PluginSettings) => void;
}

export function PatternsAndPortabilityTab({
  tokens,
  settings,
  onImportSuccess,
}: PatternsAndPortabilityTabProps) {
  const [selectedPatternSlug, setSelectedPatternSlug] = useState<string>('hero-persian');
  const [copiedSlug, setCopiedSlug] = useState<string | null>(null);
  const [cliSubcommand, setCliSubcommand] = useState<'build-all' | 'purge-corrupted' | 'status' | 'export'>(
    'build-all'
  );

  const selectedPattern = useMemo(
    () => BUNDLED_PATTERNS.find((p) => p.slug === selectedPatternSlug) || BUNDLED_PATTERNS[0],
    [selectedPatternSlug]
  );

  const exportedJson = useMemo(
    () => createExportBundle(tokens, settings, BUNDLED_PATTERNS.length),
    [tokens, settings]
  );

  const [importText, setImportText] = useState<string>(exportedJson);
  const [importFeedback, setImportFeedback] = useState<{
    success: boolean;
    messageFa: string;
    rejectedClasses?: string[];
  } | null>(null);

  const extractedPatternClasses = useMemo(
    () => extractAndValidateClasses(selectedPattern.blockMarkup).classes,
    [selectedPattern.blockMarkup]
  );

  const handleCopyMarkup = (slug: string, markup: string) => {
    navigator.clipboard?.writeText(markup);
    setCopiedSlug(slug);
    setTimeout(() => setCopiedSlug(null), 1800);
  };

  const handleRunImport = () => {
    const result = validateAndImportBundle(importText, tokens, settings);
    setImportFeedback(result);
    if (result.success && result.tokens && result.settings) {
      onImportSuccess(result.tokens, result.settings);
    }
  };

  return (
    <div className="space-y-6">
      {/* Section 1: Bundled Persian Block Patterns Library */}
      <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <div>
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <LayoutTemplate className="w-5 h-5 text-teal-600" />
              <span>
                کتابخانهٔ الگوهای آمادهٔ فارسی (Block Patterns —{' '}
                {twbLocalizeDigits(BUNDLED_PATTERNS.length)} الگو)
              </span>
            </h2>
            <p className="text-xs text-slate-500 mt-1">
              ثبت‌شده در دستهٔ{' '}
              <code dir="ltr" className="font-mono text-teal-700">
                tw-blocks-patterns
              </code>{' '}
              با رعایت کامل نیم‌فاصله، نشانه‌گذاری فارسی و ویژگی‌های منطقی راست‌به‌چپ
            </p>
          </div>
          <span className="text-xs font-mono text-slate-600 bg-slate-100 px-3 py-1 rounded-md" dir="ltr">
            includes/Patterns/PatternRegistry.php
          </span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          {/* Pattern Selector List */}
          <div className="lg:col-span-4 space-y-2">
            {BUNDLED_PATTERNS.map((pat) => {
              const active = pat.slug === selectedPatternSlug;
              return (
                <button
                  key={pat.slug}
                  type="button"
                  onClick={() => setSelectedPatternSlug(pat.slug)}
                  className={`w-full text-start p-3.5 rounded-xl border transition cursor-pointer space-y-1 ${
                    active
                      ? 'bg-teal-50/80 border-teal-300 text-slate-900 shadow-2xs'
                      : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700'
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-bold">{pat.titleFa}</span>
                    <span className="text-[11px] px-2 py-0.5 rounded bg-slate-100 text-slate-600 shrink-0">
                      {pat.categoryFa}
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-500 leading-relaxed">{pat.descriptionFa}</p>
                </button>
              );
            })}
          </div>

          {/* Selected Pattern Live Preview & Gutenberg Block Markup */}
          <div className="lg:col-span-8 space-y-4">
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-6 space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                <div>
                  <h3 className="text-sm font-bold text-slate-900">{selectedPattern.titleFa}</h3>
                  <code className="text-xs font-mono text-teal-700" dir="ltr">
                    {selectedPattern.name}
                  </code>
                </div>
                <button
                  type="button"
                  onClick={() =>
                    handleCopyMarkup(selectedPattern.slug, selectedPattern.blockMarkup)
                  }
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-teal-700 text-white hover:bg-teal-600 transition cursor-pointer"
                >
                  {copiedSlug === selectedPattern.slug ? (
                    <>
                      <Check className="w-3.5 h-3.5" />
                      <span>کپی شد!</span>
                    </>
                  ) : (
                    <>
                      <Copy className="w-3.5 h-3.5" />
                      <span>کپی مارک‌آپ گوتنبرگ</span>
                    </>
                  )}
                </button>
              </div>

              {/* Visual RTL Preview of Selected Pattern */}
              {selectedPattern.slug === 'hero-persian' && (
                <div className="rounded-xl bg-white border border-slate-200 p-6 text-start space-y-4">
                  <h4 className="text-xl font-bold text-slate-900">
                    طراحی سایت‌های وردپرسی سریع، سئو‌محور و بومی با «TW Blocks»
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    بدون بارگذاری استایل‌های سنگین و بدون وابستگی به CDN خارجی؛ تنها کلاس‌های واقعاً
                    استفاده‌شده در هر برگه روی سرور کامپایل و به‌صورت استاتیک کش می‌شوند تا امتیاز
                    ۱۰۰ در Core Web Vitals تضمین گردد.
                  </p>
                  <div className="flex flex-wrap gap-3">
                    <span className="px-4 py-2 rounded-lg bg-teal-700 text-white text-xs font-semibold">
                      شروع ساخت برگه ←
                    </span>
                    <span className="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold">
                      مشاهدهٔ مستندات فنی
                    </span>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                    <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                      <span className="text-[11px] text-teal-700 font-semibold block">
                        حجم خروجی CSS
                      </span>
                      <strong className="text-xs text-slate-900">کمتر از ۸ کیلوبایت در هر برگه</strong>
                    </div>
                    <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                      <span className="text-[11px] text-teal-700 font-semibold block">
                        فونت متغیر محلی
                      </span>
                      <strong className="text-xs text-slate-900">وزیرمتن (WOFF2) بدون CDN</strong>
                    </div>
                    <div className="p-3 rounded-lg bg-slate-50 border border-slate-200">
                      <span className="text-[11px] text-teal-700 font-semibold block">
                        سازگاری با هاست ایران
                      </span>
                      <strong className="text-xs text-slate-900">معماری هوشمند ۳ درایوره</strong>
                    </div>
                  </div>
                </div>
              )}

              {selectedPattern.slug === 'pricing-jalali' && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-start">
                  <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
                    <span className="text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                      پلن استارتر (۱ سایت)
                    </span>
                    <div className="text-sm font-bold text-slate-900">۹۸۰٬۰۰۰ تومان / سالانه</div>
                    <p className="text-xs text-slate-500">مناسب برای وبلاگ‌ها و سایت‌های شخصی.</p>
                  </div>
                  <div className="rounded-xl border-2 border-teal-600 bg-white p-4 space-y-2 shadow-xs">
                    <span className="text-[11px] font-semibold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-full">
                      پرفروش‌ترین — پلن آژانسی (۱۰ سایت)
                    </span>
                    <div className="text-sm font-bold text-slate-900">۲٬۴۵۰٬۰۰۰ تومان / سالانه</div>
                    <p className="text-xs text-slate-500">
                      شامل سرور بیلد Driver B و الگوهای آماده.
                    </p>
                  </div>
                  <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
                    <span className="text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                      پلن سازمانی (نامحدود)
                    </span>
                    <div className="text-sm font-bold text-slate-900">۴٬۹۰۰٬۰۰۰ تومان / سالانه</div>
                    <p className="text-xs text-slate-500">شامل دستورات WP-CLI و باینری محلی.</p>
                  </div>
                </div>
              )}

              {selectedPattern.slug !== 'hero-persian' &&
                selectedPattern.slug !== 'pricing-jalali' && (
                  <div className="rounded-xl bg-white border border-slate-200 p-5 text-start space-y-2">
                    <span className="text-xs font-semibold text-teal-700">
                      پیش‌نمایش ساختار الگوی «{selectedPattern.titleFa}»
                    </span>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      تمامی کلاس‌های استفاده‌شده در این الگو ({twbLocalizeDigits(extractedPatternClasses.length)}{' '}
                      کلاس یکتا) به‌صورت خودکار توسط{' '}
                      <code dir="ltr" className="font-mono">
                        ClassExtractor
                      </code>{' '}
                      اسکن و در استایل پایه کش می‌شوند.
                    </p>
                  </div>
                )}

              <div className="space-y-1.5">
                <span className="text-xs font-semibold text-slate-700 block">
                  کد بلوک گوتنبرگ (`patterns/{selectedPattern.slug}.php`):
                </span>
                <pre
                  dir="ltr"
                  className="p-3.5 bg-slate-900 text-teal-300 rounded-xl text-[11px] font-mono overflow-x-auto max-h-48"
                >
                  {selectedPattern.blockMarkup}
                </pre>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Section 2: Portability Manager (JSON Export / Import) & WP-CLI Simulator */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* JSON Export / Import (7 cols) */}
        <section className="lg:col-span-7 bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
              <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Download className="w-4 h-4 text-teal-600" />
                <span>برون‌ریزی و درون‌ریزی ایمن تنظیمات و توکن‌ها (PortabilityManager)</span>
              </h2>
              <p className="text-xs text-slate-500 mt-0.5">
                کلید محرمانهٔ HMAC (`remote_api_key`) هرگز در فایل JSON خروجی قرار نمی‌گیرد و ورودی‌ها با
                سقف ۲۵۶KB و محافظ SSRF اعتبارسنجی می‌شوند.
              </p>
            </div>
            <button
              type="button"
              onClick={() => setImportText(exportedJson)}
              className="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 hover:bg-slate-50 text-slate-700 cursor-pointer"
            >
              بارگذاری JSON فعلی
            </button>
          </div>

          <div className="space-y-2">
            <label
              htmlFor="twb-portability-json"
              className="block text-xs font-semibold text-slate-700"
            >
              بستهٔ JSON توکن‌های طراحی و تنظیمات (قابل ویرایش برای تست درون‌ریزی):
            </label>
            <textarea
              id="twb-portability-json"
              dir="ltr"
              rows={9}
              value={importText}
              onChange={(e) => setImportText(e.target.value)}
              className="w-full p-3 rounded-lg border border-slate-300 bg-slate-900 text-teal-300 font-mono text-xs text-left"
            />
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3">
            <button
              type="button"
              onClick={handleRunImport}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-teal-700 text-white hover:bg-teal-600 transition cursor-pointer"
            >
              <Upload className="w-4 h-4" />
              <span>اعتبارسنجی و درون‌ریزی بستهٔ JSON</span>
            </button>

            {importFeedback && (
              <div
                className={`flex items-center gap-2 text-xs px-3 py-2 rounded-lg border ${
                  importFeedback.success
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                    : 'bg-red-50 border-red-200 text-red-900'
                }`}
              >
                {importFeedback.success ? (
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                ) : (
                  <AlertTriangle className="w-4 h-4 text-red-600 shrink-0" />
                )}
                <span>{importFeedback.messageFa}</span>
              </div>
            )}
          </div>
        </section>

        {/* WP-CLI Command Simulator (5 cols) */}
        <section className="lg:col-span-5 bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <div className="border-b border-slate-100 pb-3">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Terminal className="w-4 h-4 text-teal-600" />
              <span>دستورات خط فرمان WP-CLI (`includes/CLI/CliCommand.php`)</span>
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">
              مخصوص اتوماسیون استقرار (CI/CD) و مدیریت کش در سرورهای پرترافیک
            </p>
          </div>

          <div className="flex flex-wrap gap-1.5" dir="ltr">
            {(
              [
                { id: 'build-all', label: 'wp twb build --all' },
                { id: 'purge-corrupted', label: 'wp twb purge --corrupted' },
                { id: 'status', label: 'wp twb status' },
                { id: 'export', label: 'wp twb export' },
              ] as const
            ).map((cmd) => (
              <button
                key={cmd.id}
                type="button"
                onClick={() => setCliSubcommand(cmd.id)}
                className={`px-3 py-1.5 rounded-lg text-xs font-mono cursor-pointer transition ${
                  cliSubcommand === cmd.id
                    ? 'bg-slate-900 text-teal-300 font-semibold'
                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                }`}
              >
                {cmd.label}
              </button>
            ))}
          </div>

          <pre
            dir="ltr"
            className="p-4 rounded-xl bg-slate-950 text-slate-200 font-mono text-xs overflow-x-auto leading-relaxed"
          >
            {cliSubcommand === 'build-all' &&
              `$ wp twb build --all
Cleaned 1 incomplete cache file(s) and 1 dangling metadata record(s) prior to build.
Compiled base scope -> base-a94f2c81b03e4410.css (3840 bytes, 68 classes, 44.20 ms)
Success: Built CSS for base scope, 6 template scopes, and 14 published posts/pages.`}
            {cliSubcommand === 'purge-corrupted' &&
              `$ wp twb purge --corrupted
 - Removed leftover temp file: twb_tmp_8f91a2
 - Removed corrupted CSS file post-19-0000000000000000.css (empty or unreadable file)
 - Cleaned dangling post meta for post #19
Success: Cache integrity check complete: removed 2 incomplete/corrupted file(s) and cleaned 1 dangling metadata entry(ies).`}
            {cliSubcommand === 'status' &&
              `$ wp twb status
TW Blocks Version : 1.0.0
Active Driver     : Driver B — Remote Build API (HMAC Signed) (remote_api)
Design Token Hash : a94f2c81b03e4410
Cache Directory   : /var/www/html/wp-content/uploads/tw-blocks/css
Success: Environment diagnostics completed.`}
            {cliSubcommand === 'export' &&
              `$ wp twb export tw-blocks-backup.json
Success: Exported configuration to tw-blocks-backup.json`}
          </pre>
        </section>
      </div>

      {/* Section 3: Query Loop Extensions & WooCommerce Integration Architecture */}
      <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 className="text-base font-bold text-slate-900">
              بلوک‌های لوپ و کوئری (فاز ۲) + یکپارچه‌سازی عمیق ووکامرس (فاز ۳)
            </h2>
            <p className="text-xs text-slate-500 mt-1">
              مبتنی بر هستهٔ <code dir="ltr">core/query</code> و{' '}
              <code dir="ltr">woocommerce/product-collection</code> بدون موتور موازی، همراه با لود شرطی
              CSS و حذف اسکیمای تکراری
            </p>
          </div>
          <div className="flex flex-wrap gap-2" dir="ltr">
            <span className="text-xs font-mono bg-teal-50 text-teal-800 border border-teal-200 px-2.5 py-1 rounded-md">
              QueryLoopExtension.php
            </span>
            <span className="text-xs font-mono bg-teal-50 text-teal-800 border border-teal-200 px-2.5 py-1 rounded-md">
              WooIntegration.php
            </span>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-start">
            <span className="text-xs font-bold text-teal-700 block">
              ۱. واریانت‌های core/query و بلوک Custom Loop
            </span>
            <ul className="text-xs text-slate-600 space-y-1.5 leading-relaxed list-disc ps-4">
              <li>واریانت‌های «آخرین مقالات»، «آخرین محصولات»، «مقالات مرتبط» و «کارت وبلاگ ۳ ستونه»</li>
              <li>
                فیلتر <code dir="ltr">query_loop_block_query_vars</code> (پست‌های ویژه، مرتب‌سازی بازدید/متا،
                حذف پست جاری، آفست + کش Transient)
              </li>
              <li>
                بلوک <code dir="ltr">tw-blocks/custom-loop</code> با InnerBlocks و بارگذاری بیشتر شرطی
              </li>
            </ul>
          </div>

          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-start">
            <span className="text-xs font-bold text-teal-700 block">
              ۲. ویجت‌های سبک (بدون Swiper)
            </span>
            <ul className="text-xs text-slate-600 space-y-1.5 leading-relaxed list-disc ps-4">
              <li>
                <code dir="ltr">tw-blocks/slider</code>: مبتنی بر CSS scroll-snap (زیر ۲KB JS، جهت درست RTL)
              </li>
              <li>
                <code dir="ltr">tw-blocks/toc</code>: فهرست مطالب پویا با لنگر ASCII فارسی بدون JS
              </li>
              <li>
                <code dir="ltr">tw-blocks/modal</code> (با تگ بومی <code dir="ltr">&lt;dialog&gt;</code>) و{' '}
                <code dir="ltr">tw-blocks/counter</code>
              </li>
            </ul>
          </div>

          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-start">
            <span className="text-xs font-bold text-teal-700 block">
              ۳. ووکامرس، قالب‌های بلوکی و لود شرطی
            </span>
            <ul className="text-xs text-slate-600 space-y-1.5 leading-relaxed list-disc ps-4">
              <li>واریانت‌های «پرفروش‌ها»، «تخفیف‌دارها»، «جدیدترین‌ها» و «محصولات مرتبط»</li>
              <li>
                ۴ قالب بلوکی (<code dir="ltr">single-product</code> با نوار خرید چسبنده موبایل،{' '}
                <code dir="ltr">archive-product</code> و ...)
              </li>
              <li>
                لود شرطی <code dir="ltr">is_woocommerce()</code> و <code dir="ltr">is_cart()/is_checkout()</code>{' '}
                + عدم تکرار اسکیمای محصول
              </li>
            </ul>
          </div>
        </div>
      </section>
    </div>
  );
}
