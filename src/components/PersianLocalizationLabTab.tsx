import React, { useState } from 'react';
import { Calendar, Hash, Globe, FileCode2 } from 'lucide-react';
import {
  twbLocalizeDigits,
  twbNormalizeDigits,
  formatJalaliDate,
  gregorianToJalali,
  sanitizeAsciiId,
} from '../engine/twBlocksEngine';

export const PersianLocalizationLabTab: React.FC = () => {
  const [sampleHtmlDigits, setSampleHtmlDigits] = useState<string>(
    '<div class="grid-cols-3 gap-6 p-4" data-post-id="409">تعداد ۱۲۴ دیدگاه ثبت شده در 24 ساعت گذشته (کد پیگیری: 98412 و ٤٥٠)</div>'
  );
  const [numericPanelInput, setNumericPanelInput] = useState<string>('۱۲٫۷۵');
  const [gregorianInput, setGregorianInput] = useState<string>('2026-11-06');
  const [persianHeadingInput, setPersianHeadingInput] = useState<string>(
    'سوالات متداول درباره طراحی سایت با گوتنبرگ و تیلویند ۴'
  );
  const [faqQuestion, setFaqQuestion] = useState<string>(
    'آیا افزونهٔ TW Blocks روی هاست اشتراکی سی‌پنل بدون Node.js کار می‌کند؟'
  );
  const [faqAnswer, setFaqAnswer] = useState<string>(
    'بله؛ هستهٔ افزونه در زمان اجرا صرفاً به PHP 8.1+ نیاز دارد و با ۳ درایور هوشمند (باینری محلی، API بیلد از راه دور و Safelist آماده) روی تمام هاست‌های اشتراکی کار می‌کند.'
  );

  const matchDate = /^(\d{4})-(\d{2})-(\d{2})/.exec(gregorianInput);
  const [jy, jm, jd] = gregorianToJalali(
    matchDate ? Number(matchDate[1]) : 2026,
    matchDate ? Number(matchDate[2]) : 11,
    matchDate ? Number(matchDate[3]) : 6
  );

  const faqSchema = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    inLanguage: 'fa-IR',
    mainEntity: [
      {
        '@type': 'Question',
        name: faqQuestion,
        acceptedAnswer: {
          '@type': 'Answer',
          text: faqAnswer,
        },
      },
    ],
  };

  const jsonLdOutput = `<script type="application/ld+json">\n${JSON.stringify(
    faqSchema,
    null,
    2
  )}\n</script>`;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* 1. Persian Digit Localization & Normalization */}
      <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div className="flex items-center gap-2 pb-3 border-b border-slate-200">
          <Hash className="w-4 h-4 text-teal-600" />
          <h2 className="text-base font-bold text-slate-900">
            ۱. تست تابع <code dir="ltr">twb_localize_digits()</code> و نرمال‌سازی ورودی‌ها
          </h2>
        </div>

        <div>
          <label
            htmlFor="test-html-digits"
            className="block text-xs font-semibold text-slate-800 mb-1.5"
          >
            ورودی HTML بلوک دینامیک (توجه کنید اعداد داخل تگ‌ها مثل <code dir="ltr">grid-cols-3</code> و <code dir="ltr">data-post-id="409"</code> دست‌نخورده می‌مانند):
          </label>
          <textarea
            id="test-html-digits"
            rows={3}
            dir="auto"
            value={sampleHtmlDigits}
            onChange={(e) => setSampleHtmlDigits(e.target.value)}
            className="w-full rounded-lg border border-slate-300 p-3 text-xs font-mono text-slate-900"
          />
        </div>

        <div className="p-3.5 rounded-lg bg-teal-50/60 border border-teal-200 text-xs space-y-1.5">
          <span className="font-bold text-teal-900 block">
            خروجی ایمن <code dir="ltr">twb_localize_digits($html)</code>:
          </span>
          <div className="font-mono text-slate-900 break-all" dir="auto">
            {twbLocalizeDigits(sampleHtmlDigits, true)}
          </div>
        </div>

        <div className="pt-3 border-t border-slate-200">
          <label
            htmlFor="test-normalize-digits"
            className="block text-xs font-semibold text-slate-800 mb-1.5"
          >
            تست نرمال‌سازی ورودی عددی پنل مدیریت (<code dir="ltr">twb_normalize_digits()</code> با پشتیبانی از ارقام فارسی/عربی):
          </label>
          <div className="flex items-center gap-3">
            <input
              id="test-normalize-digits"
              type="text"
              dir="auto"
              value={numericPanelInput}
              onChange={(e) => setNumericPanelInput(e.target.value)}
              className="w-44 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-900"
            />
            <span className="text-xs text-slate-600">
              مقدار ذخیره‌شده در دیتابیس (ASCII):{' '}
              <code className="font-mono font-bold text-teal-700 bg-slate-100 px-2 py-1 rounded" dir="ltr">
                {twbNormalizeDigits(numericPanelInput)}
              </code>
            </span>
          </div>
        </div>
      </section>

      {/* 2. Jalali (Shamsi) Calendar Converter */}
      <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div className="flex items-center gap-2 pb-3 border-b border-slate-200">
          <Calendar className="w-4 h-4 text-teal-600" />
          <h2 className="text-base font-bold text-slate-900">
            ۲. مبدل تاریخ میلادی به شمسی (Jalali) برای بلوک‌های دینامیک (Post Grid)
          </h2>
        </div>

        <p className="text-xs text-slate-600">
          منطق ذخیره‌سازی در وردپرس همیشه میلادی است. در زمان رندر، اگر افزونهٔ <code dir="ltr">WP-Parsidate</code> فعال باشد از آن استفاده می‌شود و در غیر این صورت مبدل داخلی بدون وابستگی <code dir="ltr">PersianHelper::gregorian_to_jalali()</code> اجرا می‌گردد.
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label
              htmlFor="test-gregorian-date"
              className="block text-xs font-semibold text-slate-800 mb-1.5"
            >
              تاریخ میلادی ذخیره‌شده در دیتابیس (post_date):
            </label>
            <input
              id="test-gregorian-date"
              type="date"
              dir="ltr"
              value={gregorianInput}
              onChange={(e) => setGregorianInput(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm twb-ltr-input text-slate-900"
            />
          </div>

          <div className="p-3.5 rounded-lg bg-slate-50 border border-slate-200 space-y-1 text-xs">
            <div className="flex items-center justify-between">
              <span className="text-slate-500">فرمت کامل فارسی (j F Y):</span>
              <strong className="text-sm text-teal-700">
                {formatJalaliDate(gregorianInput, 'j F Y', true)}
              </strong>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-500">فرمت عددی (Y/m/d):</span>
              <strong className="font-mono text-slate-900">
                {formatJalaliDate(gregorianInput, 'Y/m/d', true)}
              </strong>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-500">خروجی آرایهٔ خام:</span>
              <code className="font-mono text-slate-700" dir="ltr">
                [{jy}, {jm}, {jd}]
              </code>
            </div>
          </div>
        </div>

        {/* 3. ASCII ID Sanitizer for Persian Anchor Links */}
        <div className="pt-4 border-t border-slate-200 space-y-2">
          <label
            htmlFor="test-ascii-slug"
            className="block text-xs font-semibold text-slate-800"
          >
            ۳. تولید ID و کلید ASCII پایدار از عناوین فارسی (<code dir="ltr">twb_ascii_id()</code>):
          </label>
          <input
            id="test-ascii-slug"
            type="text"
            dir="auto"
            value={persianHeadingInput}
            onChange={(e) => setPersianHeadingInput(e.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
          />
          <div className="flex items-center justify-between text-xs bg-slate-100 p-2.5 rounded-lg">
            <span className="text-slate-600">شناسهٔ HTML / CSS امن (فقط ASCII بدون شکستن لینک):</span>
            <code className="font-mono font-bold text-teal-700" dir="ltr">
              #{sanitizeAsciiId(persianHeadingInput, 'twb-heading-')}
            </code>
          </div>
        </div>
      </section>

      {/* 4. UTF-8 Unescaped JSON-LD Schema Generator */}
      <section className="lg:col-span-2 bg-white border border-slate-200 rounded-lg p-6 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-200">
          <div className="flex items-center gap-2">
            <Globe className="w-4 h-4 text-teal-600" />
            <h2 className="text-base font-bold text-slate-900">
              ۴. خروجی Schema.org (JSON-LD) با UTF-8 خالص و <code dir="ltr">JSON_UNESCAPED_UNICODE</code>
            </h2>
          </div>
          <span className="text-xs text-slate-500 flex items-center gap-1">
            <FileCode2 className="w-3.5 h-3.5" />
            <span>بدون تبدیل کاراکترهای فارسی به \u06xx (سازگار با Yoast و RankMath)</span>
          </span>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-3">
            <div>
              <label htmlFor="faq-q" className="block text-xs font-semibold text-slate-800 mb-1">
                پرسش نمونه (با رعایت نیم‌فاصله و نشانه‌گذاری فارسی):
              </label>
              <input
                id="faq-q"
                type="text"
                dir="auto"
                value={faqQuestion}
                onChange={(e) => setFaqQuestion(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </div>
            <div>
              <label htmlFor="faq-a" className="block text-xs font-semibold text-slate-800 mb-1">
                پاسخ نمونه:
              </label>
              <textarea
                id="faq-a"
                rows={3}
                dir="auto"
                value={faqAnswer}
                onChange={(e) => setFaqAnswer(e.target.value)}
                className="w-full rounded-lg border border-slate-300 p-3 text-sm text-slate-900"
              />
            </div>
          </div>

          <div>
            <span className="block text-xs font-semibold text-slate-800 mb-1">
              خروجی تولیدشده توسط <code dir="ltr">PersianHelper::encode_json_ld()</code>:
            </span>
            <pre
              dir="ltr"
              className="p-3.5 rounded-lg bg-slate-950 text-teal-300 font-mono text-xs overflow-x-auto leading-relaxed"
            >
              {jsonLdOutput}
            </pre>
          </div>
        </div>
      </section>
    </div>
  );
};
