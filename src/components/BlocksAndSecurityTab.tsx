import React, { useState, useMemo } from 'react';
import {
  Blocks,
  ShieldCheck,
  AlertTriangle,
  Smartphone,
  Tablet,
  Monitor,
  CheckCircle2,
  Code2,
} from 'lucide-react';
import {
  buildBlockClassString,
  formatJalaliDate,
  generateFaqSchemaJsonLd,
  sanitizeAsciiId,
  sanitizeContainerTag,
  sanitizeInlineCss,
  sanitizeRemoteEndpointUrl,
  sanitizeSvg,
  twbLocalizeDigits,
} from '../engine/twBlocksEngine';

interface Phase2BlockSpec {
  slug: string;
  name: string;
  titleFa: string;
  categoryFa: string;
  jsFootprint: string;
  baseClasses: string;
  defaultTwClasses: string;
  defaultResponsive: Record<string, string>;
  defaultStates: Record<string, string>;
  descriptionFa: string;
}

const PHASE2_BLOCKS: Phase2BlockSpec[] = [
  {
    slug: 'section',
    name: 'tw-blocks/section',
    titleFa: 'بخش و کانتینر (Section)',
    categoryFa: 'ساختاری و چیدمان',
    jsFootprint: '0 KB',
    baseClasses: 'twb-root twb-section mx-auto w-full max-w-7xl py-16 px-6 bg-surface text-foreground',
    defaultTwClasses: 'rounded-2xl border border-slate-200',
    defaultResponsive: { md: 'px-8' },
    defaultStates: {},
    descriptionFa: 'تگ معنایی با لیست سفید (section/div/article/main)، عرض، فاصله‌گذاری منطقی و ویژگی‌های dir/lang بر اساس زبان سایت.',
  },
  {
    slug: 'grid',
    name: 'tw-blocks/grid',
    titleFa: 'شبکه و چیدمان (Grid / Flex)',
    categoryFa: 'ساختاری و چیدمان',
    jsFootprint: '0 KB',
    baseClasses: 'twb-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch',
    defaultTwClasses: 'w-full',
    defaultResponsive: { lg: 'gap-8' },
    defaultStates: {},
    descriptionFa: 'جدول نگاشت ایستا (Static Lookup Map) برای ستون‌های واکنش‌گرا بدون الحاق داینامیک رشته.',
  },
  {
    slug: 'heading',
    name: 'tw-blocks/heading',
    titleFa: 'تیتر سئومحور (H1–H6)',
    categoryFa: 'تایپوگرافی و محتوا',
    jsFootprint: '0 KB',
    baseClasses: 'twb-heading font-heading tracking-normal text-start',
    defaultTwClasses: 'text-3xl font-bold text-slate-900 mb-4',
    defaultResponsive: { md: 'text-4xl' },
    defaultStates: {},
    descriptionFa: 'لیست سفید تگ‌های h1 تا h6، تولید خودکار ID لنگر ASCII از تیتر فارسی با twb_ascii_id() و letter-spacing صفر.',
  },
  {
    slug: 'text',
    name: 'tw-blocks/text',
    titleFa: 'پاراگراف متنی فارسی',
    categoryFa: 'تایپوگرافی و محتوا',
    jsFootprint: '0 KB',
    baseClasses: 'twb-text tracking-normal text-start',
    defaultTwClasses: 'text-base leading-relaxed text-slate-600 mb-6',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'پاراگراف بهینه‌شده برای خط فارسی با line-height: 1.8، تراز منطقی (text-start) و dir="auto".',
  },
  {
    slug: 'button',
    name: 'tw-blocks/button',
    titleFa: 'دکمهٔ فراخوان (Button)',
    categoryFa: 'تعاملی و رسانه',
    jsFootprint: '0 KB',
    baseClasses: 'twb-button inline-flex items-center justify-center gap-2 bg-primary text-white',
    defaultTwClasses: 'px-6 py-3 rounded-lg font-semibold shadow-sm',
    defaultResponsive: {},
    defaultStates: { hover: 'opacity-90', focus: 'ring-2' },
    descriptionFa: 'آینه‌شدن خودکار آیکون فلش در راست‌به‌چپ با کلاس rtl:-scale-x-100 و اعتبارسنجی امن لینک با esc_url().',
  },
  {
    slug: 'image',
    name: 'tw-blocks/image',
    titleFa: 'تصویر سئومحور (بدون CLS)',
    categoryFa: 'تعاملی و رسانه',
    jsFootprint: '0 KB',
    baseClasses: 'twb-image relative w-full',
    defaultTwClasses: 'rounded-xl overflow-hidden border border-slate-200 shadow-sm',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'ابعاد اجباری width و height برای حذف کامل پرش چیدمان (CLS)، بارگذاری تنبل (loading="lazy") و srcset.',
  },
  {
    slug: 'icon',
    name: 'tw-blocks/icon',
    titleFa: 'آیکون SVG درون‌خطی',
    categoryFa: 'تعاملی و رسانه',
    jsFootprint: '0 KB',
    baseClasses: 'twb-icon inline-flex items-center justify-center shrink-0',
    defaultTwClasses: 'w-10 h-10 p-2 rounded-lg bg-teal-50 text-primary',
    defaultResponsive: {},
    defaultStates: { rtl: '-scale-x-100' },
    descriptionFa: 'بدون فونت آیکون سنگین؛ رندر مستقیم SVG پاک‌سازی‌شده با لیست سفید wp_kses در SecurityHelper::sanitize_svg().',
  },
  {
    slug: 'spacer',
    name: 'tw-blocks/spacer',
    titleFa: 'فاصله‌انداز عمودی (Spacer)',
    categoryFa: 'ساختاری و چیدمان',
    jsFootprint: '0 KB',
    baseClasses: 'twb-spacer w-full h-12',
    defaultTwClasses: '',
    defaultResponsive: { md: 'h-16' },
    defaultStates: {},
    descriptionFa: 'فاصله‌انداز معنایی با aria-hidden="true" و توکن‌های ارتفاع استاندارد تیلویند.',
  },
  {
    slug: 'divider',
    name: 'tw-blocks/divider',
    titleFa: 'خط جداکننده (Divider)',
    categoryFa: 'ساختاری و چیدمان',
    jsFootprint: '0 KB',
    baseClasses: 'twb-divider w-full border-0',
    defaultTwClasses: 'my-8 border-t border-slate-200',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'تگ معنایی <hr> با قابلیت سفارشی‌سازی ضخامت و رنگ خط از طریق توکن‌های تیلویند.',
  },
  {
    slug: 'faq',
    name: 'tw-blocks/faq',
    titleFa: 'سوالات متداول (Zero-JS + Schema)',
    categoryFa: 'پیشرفته و سئو',
    jsFootprint: '0 KB (Native HTML5)',
    baseClasses: 'twb-faq w-full',
    defaultTwClasses: 'flex flex-col gap-4',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'مبتنی بر <details> و <summary> بدون جاوااسکریپت + تولید خودکار اسکیمای یکتای FAQPage با JSON_UNESCAPED_UNICODE.',
  },
  {
    slug: 'tabs',
    name: 'tw-blocks/tabs',
    titleFa: 'زبانه‌های دسترس‌پذیر (Tabs)',
    categoryFa: 'پیشرفته و سئو',
    jsFootprint: '0.9 KB (Conditional viewScript)',
    baseClasses: 'twb-tabs w-full',
    defaultTwClasses: 'rounded-xl border border-slate-200 bg-white p-6 shadow-sm',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'نقش‌های کامل WAI-ARIA (tablist/tab/tabpanel)، پشتیبانی کیبورد در RTL و بارگذاری شرطی view.js فقط در صورت وجود بلوک.',
  },
  {
    slug: 'card',
    name: 'tw-blocks/card',
    titleFa: 'کارت ویژگی (Card)',
    categoryFa: 'پیشرفته و سئو',
    jsFootprint: '0 KB',
    baseClasses: 'twb-card flex flex-col justify-between text-start',
    defaultTwClasses: 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200',
    defaultResponsive: {},
    defaultStates: { hover: 'shadow-md -translate-y-1' },
    descriptionFa: 'کارت محتوایی شامل نشان (Badge)، تیتر فارسی، توضیحات و لینک فراخوان با آیکون جهت‌دار آینه‌ای در RTL.',
  },
  {
    slug: 'icon-box',
    name: 'tw-blocks/icon-box',
    titleFa: 'جعبهٔ آیکون‌دار (Icon Box)',
    categoryFa: 'پیشرفته و سئو',
    jsFootprint: '0 KB',
    baseClasses: 'twb-icon-box text-start',
    defaultTwClasses: 'flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'ترکیب آیکون SVG ایمن، تیتر و توضیح با چیدمان منطقی راست‌به‌چپ.',
  },
  {
    slug: 'post-grid',
    name: 'tw-blocks/post-grid',
    titleFa: 'شبکهٔ پویای نوشته‌ها (Post Grid)',
    categoryFa: 'پویا (SSR)',
    jsFootprint: '0 KB (Server-Side WP_Query)',
    baseClasses: 'twb-post-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    defaultTwClasses: 'gap-6',
    defaultResponsive: {},
    defaultStates: {},
    descriptionFa: 'رندر سمت سرور با WP_Query بهینه (no_found_rows)، تاریخ شمسی (twb_format_date) و صفحه‌بندی با ارقام فارسی.',
  },
];

const PHYSICAL_DIR_REGEX =
  /(?:^|:)(?:ml-|mr-|pl-|pr-|left-|right-|text-left|text-right|border-l(?:$|-)|border-r(?:$|-)|rounded-l(?:$|-)|rounded-r(?:$|-))/;

export function BlocksAndSecurityTab() {
  const [selectedSlug, setSelectedSlug] = useState<string>('faq');
  const [activeBp, setActiveBp] = useState<'base' | 'sm' | 'md' | 'lg' | 'xl'>('base');
  const [activeState, setActiveState] = useState<'hover' | 'focus' | 'dark' | 'rtl'>('hover');
  const [devicePreview, setDevicePreview] = useState<'Desktop' | 'Tablet' | 'Mobile'>('Desktop');
  const [activeTabIndex, setActiveTabIndex] = useState<number>(0);

  const selectedBlock = useMemo(
    () => PHASE2_BLOCKS.find((b) => b.slug === selectedSlug) || PHASE2_BLOCKS[0],
    [selectedSlug]
  );

  const [twClasses, setTwClasses] = useState<string>(selectedBlock.defaultTwClasses);
  const [twResponsive, setTwResponsive] = useState<Record<string, string>>(
    selectedBlock.defaultResponsive
  );
  const [twStates, setTwStates] = useState<Record<string, string>>(selectedBlock.defaultStates);

  // Security Audit Sandbox state
  const [svgTestInput, setSvgTestInput] = useState<string>(
    '<svg viewBox="0 0 24 24" onload="alert(1)"><script>stealCookies()</script><path d="M5 12h14"/></svg>'
  );
  const [ssrfUrlInput, setSsrfUrlInput] = useState<string>('http://169.254.169.254/latest/meta-data/');
  const [tagTestInput, setTagTestInput] = useState<string>('script onload=alert(1)');
  const [cssBreakoutInput, setCssBreakoutInput] = useState<string>(
    '.twb-card { color: #0d9488; }</style><script>alert("xss")</script>'
  );

  const handleSelectBlock = (block: Phase2BlockSpec) => {
    setSelectedSlug(block.slug);
    setTwClasses(block.defaultTwClasses);
    setTwResponsive(block.defaultResponsive);
    setTwStates(block.defaultStates);
  };

  const activeBpValue = activeBp === 'base' ? twClasses : twResponsive[activeBp] || '';

  const updateActiveBpValue = (val: string) => {
    if (activeBp === 'base') {
      setTwClasses(val);
    } else {
      setTwResponsive((prev) => ({ ...prev, [activeBp]: val }));
    }
  };

  const toggleTokenInActiveBp = (token: string) => {
    const current = activeBpValue.trim().split(/\s+/).filter(Boolean);
    const next = current.includes(token)
      ? current.filter((c) => c !== token)
      : [...current, token];
    updateActiveBpValue(next.join(' '));
  };

  const builtClasses = useMemo(
    () =>
      buildBlockClassString(
        {
          twClasses,
          twResponsive,
          twStates,
        },
        selectedBlock.baseClasses
      ),
    [selectedBlock.baseClasses, twClasses, twResponsive, twStates]
  );

  const physicalWarnings = useMemo(() => {
    const allTokens = builtClasses.classString.split(/\s+/).filter(Boolean);
    return allTokens.filter((t) => PHYSICAL_DIR_REGEX.test(t));
  }, [builtClasses.classString]);

  const faqSchemaOutput = useMemo(
    () =>
      generateFaqSchemaJsonLd([
        {
          question: 'چرا افزونهٔ TW Blocks هیچ فایل جاوااسکریپتی در فرانت‌اند برای آکاردئون بارگذاری نمی‌کند؟',
          answer:
            'بلوک سوالات متداول از تگ‌های استاندارد و بومی <details> و <summary> در HTML5 استفاده می‌کند که بدون حتی یک بایت جاوااسکریپت، دسترس‌پذیری کیبورد و سرعت کامل را فراهم می‌سازد.',
        },
        {
          question: 'آیا استایل‌های Tailwind روی سرورهای اشتراکی ایران بدون Node.js کار می‌کنند؟',
          answer:
            'بله؛ معماری ۳ درایورهٔ افزونه در صورت نبود باینری محلی یا سرویس ابری، به‌طور خودکار روی Driver C (Safelist بهینه‌شده) سوییچ می‌کند.',
        },
      ]),
    []
  );

  return (
    <div className="space-y-6">
      {/* Phase 2 Block Library & Universal Tailwind Class Inspector */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Column 1: 14 Phase 2 Blocks List (3 cols on lg) */}
        <div className="lg:col-span-3 bg-white border border-slate-200 rounded-lg p-4 space-y-3">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Blocks className="w-4 h-4 text-teal-600" />
              <span>بلوک‌های فاز ۲ ({twbLocalizeDigits(PHASE2_BLOCKS.length)})</span>
            </h2>
            <span className="text-xs font-mono text-teal-700 bg-teal-50 px-2 py-0.5 rounded" dir="ltr">
              apiVersion: 3
            </span>
          </div>

          <div className="space-y-1 max-h-[540px] overflow-y-auto pe-1">
            {PHASE2_BLOCKS.map((blk) => {
              const active = blk.slug === selectedSlug;
              return (
                <button
                  key={blk.slug}
                  type="button"
                  onClick={() => handleSelectBlock(blk)}
                  className={`w-full text-start px-3 py-2.5 rounded-lg text-xs transition-colors cursor-pointer flex items-center justify-between gap-2 ${
                    active
                      ? 'bg-teal-50 text-teal-950 font-semibold border border-teal-200'
                      : 'text-slate-700 hover:bg-slate-50 border border-transparent'
                  }`}
                >
                  <span className="truncate">{blk.titleFa}</span>
                  <code className="text-[11px] font-mono text-slate-500 shrink-0" dir="ltr">
                    {blk.slug}
                  </code>
                </button>
              );
            })}
          </div>
        </div>

        {/* Column 2: Live Block Canvas & SSR Output Preview (5 cols on lg) */}
        <div className="lg:col-span-5 bg-white border border-slate-200 rounded-lg p-5 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
              <h3 className="text-base font-bold text-slate-900">{selectedBlock.titleFa}</h3>
              <p className="text-xs text-slate-500 mt-0.5">{selectedBlock.descriptionFa}</p>
            </div>
            <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-lg" dir="ltr">
              <button
                type="button"
                onClick={() => setDevicePreview('Desktop')}
                className={`p-1.5 rounded text-xs cursor-pointer ${
                  devicePreview === 'Desktop' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'
                }`}
                title="Desktop Preview"
              >
                <Monitor className="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                onClick={() => setDevicePreview('Tablet')}
                className={`p-1.5 rounded text-xs cursor-pointer ${
                  devicePreview === 'Tablet' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'
                }`}
                title="Tablet Preview (md:)"
              >
                <Tablet className="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                onClick={() => setDevicePreview('Mobile')}
                className={`p-1.5 rounded text-xs cursor-pointer ${
                  devicePreview === 'Mobile' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'
                }`}
                title="Mobile Preview (sm:)"
              >
                <Smartphone className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          {/* Metadata Strip */}
          <div className="grid grid-cols-3 gap-2 bg-slate-50 p-3 rounded-lg border border-slate-200/80 text-xs">
            <div>
              <span className="text-slate-500 block">نام بلوک:</span>
              <code className="font-mono text-slate-900 font-semibold" dir="ltr">
                {selectedBlock.name}
              </code>
            </div>
            <div>
              <span className="text-slate-500 block">حجم JS فرانت‌اند:</span>
              <span className="font-semibold text-teal-700" dir="ltr">
                {selectedBlock.jsFootprint}
              </span>
            </div>
            <div>
              <span className="text-slate-500 block">پیش‌نمایش دستگاه:</span>
              <span className="font-semibold text-slate-800">{devicePreview}</span>
            </div>
          </div>

          {/* Interactive Rendered Preview of Selected Block */}
          <div className="border border-dashed border-slate-300 rounded-xl p-5 bg-slate-50/50">
            {selectedSlug === 'faq' && (
              <div className="space-y-3">
                <details
                  open
                  className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs text-start"
                >
                  <summary className="cursor-pointer font-semibold text-slate-900 text-sm flex items-center justify-between">
                    <span>
                      چرا افزونهٔ TW Blocks هیچ فایل جاوااسکریپتی برای آکاردئون بارگذاری نمی‌کند؟
                    </span>
                    <span className="text-teal-600 text-xs">▼</span>
                  </summary>
                  <p className="mt-2 pt-2 border-t border-slate-100 text-xs text-slate-600 leading-relaxed">
                    بلوک سوالات متداول از تگ‌های بومی &lt;details&gt; و &lt;summary&gt; در HTML5
                    استفاده می‌کند و هم‌زمان اسکیمای یکتای FAQPage را با JSON_UNESCAPED_UNICODE در
                    فوتر صفحه چاپ می‌نماید.
                  </p>
                </details>
                <details className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs text-start">
                  <summary className="cursor-pointer font-semibold text-slate-900 text-sm flex items-center justify-between">
                    <span>آیا استایل‌های Tailwind روی سرورهای اشتراکی ایران بدون Node کار می‌کنند؟</span>
                    <span className="text-teal-600 text-xs">▼</span>
                  </summary>
                  <p className="mt-2 pt-2 border-t border-slate-100 text-xs text-slate-600 leading-relaxed">
                    بله؛ در صورت نبود باینری محلی یا سرویس ابری، افزونه به‌طور خودکار روی Driver C
                    (Safelist بهینه) سوییچ می‌کند.
                  </p>
                </details>
              </div>
            )}

            {selectedSlug === 'tabs' && (
              <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-3" role="tablist">
                  {['کامپایل استاتیک CSS', 'بومی‌سازی فارسی و RTL', 'امنیت چندلایه'].map(
                    (tabTitle, idx) => (
                      <button
                        key={tabTitle}
                        type="button"
                        role="tab"
                        aria-selected={activeTabIndex === idx}
                        onClick={() => setActiveTabIndex(idx)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer ${
                          activeTabIndex === idx
                            ? 'bg-teal-700 text-white'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        }`}
                      >
                        {tabTitle}
                      </button>
                    )
                  )}
                </div>
                <div role="tabpanel" className="text-xs text-slate-600 leading-relaxed text-start">
                  {activeTabIndex === 0 &&
                    'تنها کلاس‌های واقعاً استفاده‌شده در هر برگه استخراج و در یک فایل استاتیک کش می‌شوند.'}
                  {activeTabIndex === 1 &&
                    'پشتیبانی پیش‌فرض از ویژگی‌های منطقی (Logical Properties)، فونت وزیرمتن محلی و تقویم جلالی.'}
                  {activeTabIndex === 2 &&
                    'تمامی کلاس‌های Tailwind، آیکون‌های SVG و فایل‌های فونت با لیست سفید و امضای باینری اعتبارسنجی می‌شوند.'}
                </div>
              </div>
            )}

            {selectedSlug === 'post-grid' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {[
                  {
                    title: 'راهنمای مهاجرت از المنتور به گوتنبرگ و تیلویند ۴',
                    iso: '2026-03-21',
                  },
                  {
                    title: 'بهینه‌سازی Core Web Vitals در سایت‌های وردپرسی فارسی',
                    iso: '2026-11-06',
                  },
                ].map((post) => (
                  <article
                    key={post.title}
                    className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs text-start space-y-2"
                  >
                    <time className="text-[11px] text-slate-500 block">
                      {formatJalaliDate(post.iso, 'j F Y', true)}
                    </time>
                    <h4 className="text-xs font-bold text-slate-900">{post.title}</h4>
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-teal-700">
                      <span>ادامهٔ مطلب</span>
                      <span className="rtl:-scale-x-100 inline-block">→</span>
                    </span>
                  </article>
                ))}
              </div>
            )}

            {selectedSlug !== 'faq' && selectedSlug !== 'tabs' && selectedSlug !== 'post-grid' && (
              <div className="rounded-xl border border-slate-200 bg-white p-4 text-start space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full">
                    پیش‌نمایش زندهٔ {selectedBlock.titleFa}
                  </span>
                  <code className="text-[11px] font-mono text-slate-500" dir="ltr">
                    id=&quot;{sanitizeAsciiId(selectedBlock.titleFa, 'twb-')}&quot;
                  </code>
                </div>
                <p className="text-xs text-slate-600 leading-relaxed">
                  خروجی HTML این بلوک بدون هیچ‌گونه استایل درون‌خطی (inline style) و صرفاً با کلاس‌های
                  اعتبارسنجی‌شدهٔ Tailwind CSS v4 در سمت سرور رندر می‌شود.
                </p>
              </div>
            )}
          </div>

          {/* Validated Class Output & JSON-LD Schema */}
          <div className="space-y-2">
            <label className="block text-xs font-semibold text-slate-700">
              رشتهٔ نهایی کلاس‌های بلوک (پس از اعتبارسنجی لیست سفید در BlockRegistry):
            </label>
            <pre
              dir="ltr"
              className="p-3 bg-slate-900 text-teal-300 rounded-lg text-xs font-mono overflow-x-auto"
            >
              {builtClasses.classString}
            </pre>
            {builtClasses.rejected.length > 0 && (
              <div className="p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-800 flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0 text-red-600" />
                <span>
                  توکن‌های غیرمجاز مسدود شدند:{' '}
                  <code dir="ltr" className="font-mono font-bold">
                    {builtClasses.rejected.join(', ')}
                  </code>
                </span>
              </div>
            )}
            {selectedSlug === 'faq' && (
              <div className="space-y-1 pt-2">
                <span className="text-xs font-semibold text-slate-700 block">
                  خروجی خودکار اسکیمای JSON-LD (بدون Escape شدن حروف فارسی — JSON_UNESCAPED_UNICODE):
                </span>
                <pre
                  dir="ltr"
                  className="p-3 bg-slate-900 text-amber-200 rounded-lg text-[11px] font-mono overflow-x-auto max-h-40"
                >
                  {faqSchemaOutput}
                </pre>
              </div>
            )}
          </div>
        </div>

        {/* Column 3: Universal "Tailwind Class" Inspector Control Simulator (4 cols on lg) */}
        <div className="lg:col-span-4 bg-white border border-slate-200 rounded-lg p-5 space-y-4">
          <div className="border-b border-slate-100 pb-3">
            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Code2 className="w-4 h-4 text-teal-600" />
              <span>کنترل «Tailwind Class» در پنل کناری گوتنبرگ</span>
            </h3>
            <p className="text-xs text-slate-500 mt-0.5">
              تزریق‌شده به تمامی بلوک‌ها از طریق فیلتر{' '}
              <code dir="ltr" className="font-mono">
                editor.BlockEdit
              </code>{' '}
              با جهت اجباری LTR
            </p>
          </div>

          {/* Breakpoint Tabs */}
          <div className="space-y-1.5">
            <span className="text-xs font-semibold text-slate-700 block">
              ۱. تفکیک کلاس‌ها بر اساس Breakpoint:
            </span>
            <div className="flex flex-wrap gap-1" dir="ltr">
              {(['base', 'sm', 'md', 'lg', 'xl'] as const).map((bp) => (
                <button
                  key={bp}
                  type="button"
                  onClick={() => {
                    setActiveBp(bp);
                    if (bp === 'sm') setDevicePreview('Mobile');
                    else if (bp === 'md') setDevicePreview('Tablet');
                    else setDevicePreview('Desktop');
                  }}
                  className={`px-2.5 py-1 rounded text-xs font-mono cursor-pointer transition ${
                    activeBp === bp
                      ? 'bg-teal-700 text-white font-semibold'
                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                  }`}
                >
                  {bp === 'base' ? 'base (All)' : `${bp}:`}
                </button>
              ))}
            </div>
          </div>

          {/* Strictly LTR Input */}
          <div className="space-y-1">
            <label
              htmlFor="twb-inspector-class-input"
              className="block text-xs font-medium text-slate-700"
            >
              کلاس‌های تیلویند برای حالت{' '}
              <code dir="ltr" className="font-mono text-teal-700">
                {activeBp}
              </code>{' '}
              (اجباراً LTR):
            </label>
            <textarea
              id="twb-inspector-class-input"
              dir="ltr"
              rows={2}
              value={activeBpValue}
              onChange={(e) => updateActiveBpValue(e.target.value)}
              className="w-full p-2.5 rounded-lg border border-slate-300 font-mono text-xs text-left bg-slate-50 focus:bg-white focus:border-teal-600 outline-none"
              placeholder="ps-6 pe-6 rounded-xl text-start..."
            />
          </div>

          {/* Physical Direction Warning */}
          {physicalWarnings.length > 0 && (
            <div className="p-3 rounded-lg bg-amber-50 border border-amber-300 text-xs text-amber-900 flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <div>
                <strong className="block mb-0.5">هشدار جهت فیزیکی (Physical Utility):</strong>
                <span>
                  کلاس‌های{' '}
                  <code dir="ltr" className="font-mono font-bold">
                    {physicalWarnings.join(', ')}
                  </code>{' '}
                  شناسایی شدند. برای سازگاری با RTL از معادل‌های منطقی (
                  <code dir="ltr" className="font-mono">
                    ms-, me-, ps-, pe-, text-start, rounded-s
                  </code>
                  ) استفاده کنید.
                </span>
              </div>
            </div>
          )}

          {/* State Variants */}
          <div className="space-y-1.5">
            <span className="text-xs font-semibold text-slate-700 block">
              ۲. حالت‌های تعاملی (hover / focus / dark / rtl):
            </span>
            <div className="flex flex-wrap gap-1" dir="ltr">
              {(['hover', 'focus', 'dark', 'rtl'] as const).map((st) => (
                <button
                  key={st}
                  type="button"
                  onClick={() => setActiveState(st)}
                  className={`px-2.5 py-1 rounded text-xs font-mono cursor-pointer transition ${
                    activeState === st
                      ? 'bg-slate-900 text-white font-semibold'
                      : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                  }`}
                >
                  {st}:
                </button>
              ))}
            </div>
            <input
              type="text"
              dir="ltr"
              value={twStates[activeState] || ''}
              onChange={(e) =>
                setTwStates((prev) => ({ ...prev, [activeState]: e.target.value }))
              }
              placeholder="shadow-md -scale-x-100 opacity-90"
              className="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono text-xs text-left bg-slate-50"
            />
          </div>

          {/* Visual Logical Pickers (write classes, never inline styles) */}
          <div className="space-y-2 pt-1 border-t border-slate-100">
            <span className="text-xs font-semibold text-slate-700 block">
              ۳. انتخاب‌گرهای بصری Logical (تولید مستقیم کلاس بدون استایل درون‌خطی):
            </span>
            <div className="flex flex-wrap gap-1.5" dir="ltr">
              {[
                'ps-4',
                'pe-4',
                'ms-auto',
                'me-auto',
                'text-start',
                'text-center',
                'leading-relaxed',
                'rtl:-scale-x-100',
                'border-s-4',
                'rounded-xl',
              ].map((preset) => (
                <button
                  key={preset}
                  type="button"
                  onClick={() => toggleTokenInActiveBp(preset)}
                  className="px-2 py-1 text-[11px] font-mono rounded border border-slate-200 bg-slate-50 hover:bg-teal-50 hover:border-teal-300 text-slate-800 cursor-pointer"
                >
                  + {preset}
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Security & Performance Hardening Audit Lab */}
      <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <div>
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <ShieldCheck className="w-5 h-5 text-teal-600" />
              <span>آزمایشگاه امنیت و بهینه‌سازی کد (SecurityHelper & Performance Audit)</span>
            </h2>
            <p className="text-xs text-slate-500 mt-1">
              بررسی لایه‌های حفاظتی در برابر تزریق کد (XSS)، جعل فایل فونت، حملات SSRF، خروج از تگ
              &lt;style&gt; و بهینه‌سازی کوئری‌های دیتابیس
            </p>
          </div>
          <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-800 bg-teal-50 border border-teal-200 px-3 py-1 rounded-md">
            <CheckCircle2 className="w-4 h-4 text-teal-600" />
            <span>تمامی بررسی‌های امنیتی فعال هستند</span>
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {/* 1. SVG XSS Sanitizer */}
          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/60 space-y-2">
            <h3 className="text-xs font-bold text-slate-900">
              ۱. پاک‌سازی آیکون‌های SVG درون‌خطی (SecurityHelper::sanitize_svg)
            </h3>
            <input
              type="text"
              dir="ltr"
              value={svgTestInput}
              onChange={(e) => setSvgTestInput(e.target.value)}
              className="w-full px-2.5 py-1.5 rounded border border-slate-300 bg-white font-mono text-xs text-left"
            />
            <div className="text-[11px] font-mono bg-slate-900 text-teal-300 p-2.5 rounded overflow-x-auto" dir="ltr">
              {sanitizeSvg(svgTestInput) || '/* Blocked invalid SVG */'}
            </div>
          </div>

          {/* 2. SSRF Endpoint Validator */}
          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/60 space-y-2">
            <h3 className="text-xs font-bold text-slate-900">
              ۲. محافظت SSRF روی آدرس سرور بیلد راه دور (Driver B)
            </h3>
            <input
              type="text"
              dir="ltr"
              value={ssrfUrlInput}
              onChange={(e) => setSsrfUrlInput(e.target.value)}
              className="w-full px-2.5 py-1.5 rounded border border-slate-300 bg-white font-mono text-xs text-left"
            />
            <div className="text-xs">
              {sanitizeRemoteEndpointUrl(ssrfUrlInput) ? (
                <span className="text-emerald-700 font-semibold">
                  ✓ آدرس خارجی مجاز: {sanitizeRemoteEndpointUrl(ssrfUrlInput)}
                </span>
              ) : (
                <span className="text-red-700 font-semibold">
                  ✗ مسدود شد (آدرس داخلی / لوپ‌بک / متادیتای ابری 169.254.169.254 غیرمجاز است)
                </span>
              )}
            </div>
          </div>

          {/* 3. Semantic Tag Whitelist */}
          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/60 space-y-2">
            <h3 className="text-xs font-bold text-slate-900">
              ۳. لیست سفید تگ‌های HTML (جلوگیری از تزریق تگ در بلوک Section / Heading)
            </h3>
            <input
              type="text"
              dir="ltr"
              value={tagTestInput}
              onChange={(e) => setTagTestInput(e.target.value)}
              className="w-full px-2.5 py-1.5 rounded border border-slate-300 bg-white font-mono text-xs text-left"
            />
            <div className="text-xs text-slate-700">
              تگ خروجی ایمن‌شده:{' '}
              <code dir="ltr" className="font-mono font-bold text-teal-800">
                &lt;{sanitizeContainerTag(tagTestInput)}&gt;
              </code>
            </div>
          </div>

          {/* 4. Inline Style Breakout Prevention */}
          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/60 space-y-2">
            <h3 className="text-xs font-bold text-slate-900">
              ۴. جلوگیری از خروج از تگ &lt;style&gt; در CSS درون‌خطی
            </h3>
            <input
              type="text"
              dir="ltr"
              value={cssBreakoutInput}
              onChange={(e) => setCssBreakoutInput(e.target.value)}
              className="w-full px-2.5 py-1.5 rounded border border-slate-300 bg-white font-mono text-xs text-left"
            />
            <div className="text-[11px] font-mono bg-slate-900 text-teal-300 p-2.5 rounded overflow-x-auto" dir="ltr">
              {sanitizeInlineCss(cssBreakoutInput)}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
