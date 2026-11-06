import React, { useState, useEffect } from 'react';
import {
  Code2,
  AlertCircle,
  CheckCircle2,
  KeyRound,
  Layers,
  Copy,
  Check,
} from 'lucide-react';
import {
  DesignTokens,
  PluginSettings,
  extractAndValidateClasses,
  compileCssBundle,
  generateThemeAndFontCss,
  twbLocalizeDigits,
} from '../engine/twBlocksEngine';

interface CssEngineLabTabProps {
  tokens: DesignTokens;
  settings: PluginSettings;
  sampleMarkup: string;
  onChangeSampleMarkup: (markup: string) => void;
  extraClasses: string;
  onChangeExtraClasses: (cls: string) => void;
}

export const CssEngineLabTab: React.FC<CssEngineLabTabProps> = ({
  tokens,
  settings,
  sampleMarkup,
  onChangeSampleMarkup,
  extraClasses,
  onChangeExtraClasses,
}) => {
  const [hmacSignature, setHmacSignature] = useState<string>('sha256=e9f4b21a8d7c013f...');
  const [hmacTimestamp, setHmacTimestamp] = useState<number>(1793966400);
  const [copiedTheme, setCopiedTheme] = useState(false);
  const [copiedCss, setCopiedCss] = useState(false);

  const { classes, rejected } = extractAndValidateClasses(sampleMarkup, [
    extraClasses,
    settings.safelist,
  ]);

  const bundle = compileCssBundle(classes, tokens, settings.inlineThresholdKb);
  const themeBlock = generateThemeAndFontCss(tokens);

  const fmt = (val: string | number) => twbLocalizeDigits(val, settings.enablePersianDigits);

  useEffect(() => {
    let active = true;
    fetch('/api/hmac-preview', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        classes,
        themeCss: themeBlock,
        secretKey: settings.remoteApiKey || 'twb-secret-production-key-2026',
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (active && data.signatureHeader) {
          setHmacSignature(data.signatureHeader);
          setHmacTimestamp(data.timestamp);
        }
      })
      .catch(() => {
        // Keep local preview fallback if offline
      });
    return () => {
      active = false;
    };
  }, [classes.join(' '), themeBlock, settings.remoteApiKey]);

  const LogicalReplacements: Record<string, string> = {
    'ml-': 'ms-',
    'mr-': 'me-',
    'pl-': 'ps-',
    'pr-': 'pe-',
    'left-': 'start-',
    'right-': 'end-',
    'text-left': 'text-start',
    'text-right': 'text-end',
    'border-l': 'border-s',
    'border-r': 'border-e',
    'rounded-l': 'rounded-s',
    'rounded-r': 'rounded-e',
  };

  const replacePhysicalWithLogical = () => {
    let updatedMarkup = sampleMarkup;
    let updatedExtra = extraClasses;
    for (const [phys, log] of Object.entries(LogicalReplacements)) {
      updatedMarkup = updatedMarkup.replaceAll(phys, log);
      updatedExtra = updatedExtra.replaceAll(phys, log);
    }
    onChangeSampleMarkup(updatedMarkup);
    onChangeExtraClasses(updatedExtra);
  };

  return (
    <div className="space-y-6">
      {/* Top 3-Scope Architecture Summary */}
      <section className="bg-white border border-slate-200 rounded-lg p-6">
        <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-200">
          <div>
            <h2 className="text-lg font-bold text-slate-900">
              آزمایشگاه زندهٔ استخراج کلاس (ClassExtractor) و معماری ۳ سطحی کش CSS
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">
              هنگام ذخیرهٔ پست (<code dir="ltr">save_post</code>) یا تغییر توکن‌ها، کلاس‌ها از مارک‌آپ بلوک‌ها استخراج، با Regex اعتبارسنجی، مرتب و هش می‌شوند.
            </p>
          </div>
          <div className="flex items-center gap-3 text-xs">
            <span className="text-slate-600">
              هش SHA-256 فعلی:{' '}
              <code className="font-mono font-bold text-teal-700" dir="ltr">
                {bundle.hash}
              </code>
            </span>
            <span className="text-slate-300" aria-hidden="true">|</span>
            <span className="text-slate-600">
              حجم نهایی:{' '}
              <strong className="tabular-nums text-slate-900">{fmt(bundle.sizeKb)} کیلوبایت</strong>
            </span>
          </div>
        </div>

        {/* 3 Scopes Visual Pipeline */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/70">
            <div className="flex items-center justify-between text-xs font-semibold text-slate-800 mb-1">
              <span>۱. سطح سراسری (Base Scope)</span>
              <code className="font-mono text-teal-700" dir="ltr">
                base-{bundle.hash.slice(0, 8)}.css
              </code>
            </div>
            <p className="text-xs text-slate-500">
              شامل متغیرهای <code dir="ltr">@theme</code>، فونت محلی Vazirmatn، ریست <code dir="ltr">.twb-root</code>، کلاس‌های هدر/فوتر و Safelist.
            </p>
          </div>

          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/70">
            <div className="flex items-center justify-between text-xs font-semibold text-slate-800 mb-1">
              <span>۲. سطح قالب (Per-Template Scope)</span>
              <code className="font-mono text-teal-700" dir="ltr">
                tpl-single-{bundle.hash.slice(4, 12)}.css
              </code>
            </div>
            <p className="text-xs text-slate-500">
              کلاس‌های استخراج‌شده از قالب بلوکی فعال صفحه (<code dir="ltr">single</code>, <code dir="ltr">archive</code>, <code dir="ltr">page</code>).
            </p>
          </div>

          <div className="p-4 rounded-lg border border-slate-200 bg-slate-50/70">
            <div className="flex items-center justify-between text-xs font-semibold text-slate-800 mb-1">
              <span>۳. سطح نوشته/برگه (Per-Post Scope)</span>
              <code className="font-mono text-teal-700" dir="ltr">
                post-42-{bundle.hash.slice(8, 16)}.css
              </code>
            </div>
            <p className="text-xs text-slate-500">
              کلاس‌های اختصاصی بلوک‌های همان نوشته در <code dir="ltr">post_meta</code> (<code dir="ltr">_twb_css_meta</code>) با نوشتن اتمیک.
            </p>
          </div>
        </div>
      </section>

      {/* Main Two-Column Extractor & Output */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
        {/* Left/Start Column: Block Markup & Extra Classes Input */}
        <section className="bg-white border border-slate-200 rounded-lg p-6 flex flex-col justify-between">
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <label
                htmlFor="lab-block-markup"
                className="text-sm font-bold text-slate-900 flex items-center gap-2"
              >
                <Code2 className="w-4 h-4 text-teal-600" />
                <span>مارک‌آپ بلوک‌های گوتنبرگ (post_content نمونه)</span>
              </label>
              <span className="text-xs text-slate-500">parse_blocks() + render_block()</span>
            </div>

            <textarea
              id="lab-block-markup"
              rows={8}
              dir="ltr"
              value={sampleMarkup}
              onChange={(e) => onChangeSampleMarkup(e.target.value)}
              className="w-full rounded-lg border border-slate-300 p-3 text-xs twb-ltr-input bg-slate-950 text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            />

            <div>
              <label
                htmlFor="lab-extra-classes"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                ورودی کنترل «Tailwind Class» بلوک (دارای dir="ltr" اجباری و بررسی Regex Whitelist):
              </label>
              <input
                id="lab-extra-classes"
                type="text"
                dir="ltr"
                value={extraClasses}
                onChange={(e) => onChangeExtraClasses(e.target.value)}
                placeholder="ps-6 pe-6 ms-auto text-start md:grid-cols-3 rtl:-scale-x-100"
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs twb-ltr-input text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              />
            </div>

            {/* Physical Direction Warning Banner */}
            {bundle.physicalDirectionWarnings.length > 0 && (
              <div className="bg-amber-50 border border-amber-300 rounded-lg p-3.5 text-xs text-amber-950 flex items-start justify-between gap-3">
                <div className="flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                  <div>
                    <strong className="font-bold block">
                      هشدار قانون RTL (استفاده از کلاس‌های جهتی فیزیکی ممنوع است):
                    </strong>
                    <span className="text-amber-900">
                      کلاس‌های فیزیکی شناسایی‌شده:{' '}
                      <code className="font-mono font-bold" dir="ltr">
                        {bundle.physicalDirectionWarnings.join(', ')}
                      </code>
                      . در TW Blocks فقط باید از Logical Properties (<code dir="ltr">ms-</code>, <code dir="ltr">me-</code>, <code dir="ltr">ps-</code>, <code dir="ltr">pe-</code>, <code dir="ltr">text-start</code>) استفاده شود.
                    </span>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={replacePhysicalWithLogical}
                  className="px-2.5 py-1.5 rounded bg-amber-800 hover:bg-amber-700 text-white font-medium shrink-0 cursor-pointer"
                >
                  تبدیل خودکار به Logical
                </button>
              </div>
            )}

            {/* Rejected Malicious/Invalid Tokens */}
            {rejected.length > 0 && (
              <div className="bg-rose-50 border border-rose-200 rounded-lg p-3 text-xs text-rose-900">
                <strong>توکن‌های نامعتبر مسدودشده توسط Regex Whitelist امنیتی:</strong>{' '}
                <code className="font-mono" dir="ltr">
                  {rejected.join(' , ')}
                </code>
              </div>
            )}
          </div>

          {/* Extracted Validated Classes List */}
          <div className="mt-5 pt-4 border-t border-slate-200">
            <div className="flex items-center justify-between text-xs mb-2">
              <span className="font-semibold text-slate-800">
                کلاس‌های معتبر، یکتا و مرتب‌شده ({fmt(classes.length)} کلاس):
              </span>
              <span className="text-teal-700 flex items-center gap-1">
                <CheckCircle2 className="w-3.5 h-3.5" />
                <span>تأییدشده با CLASS_REGEX</span>
              </span>
            </div>
            <div
              dir="ltr"
              className="p-3 rounded-lg bg-slate-100 border border-slate-200 font-mono text-xs text-slate-800 max-h-28 overflow-y-auto leading-relaxed"
            >
              {classes.join('  ')}
            </div>
          </div>
        </section>

        {/* Right/End Column: Generated @theme + Minified CSS + HMAC Signature */}
        <section className="bg-white border border-slate-200 rounded-lg p-6 flex flex-col justify-between space-y-4">
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Layers className="w-4 h-4 text-teal-600" />
                <span>خروجی توکن‌های @theme و متغیرهای همگام با theme.json</span>
              </span>
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(themeBlock);
                  setCopiedTheme(true);
                  setTimeout(() => setCopiedTheme(false), 1500);
                }}
                className="flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 cursor-pointer"
              >
                {copiedTheme ? <Check className="w-3.5 h-3.5 text-teal-600" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedTheme ? 'کپی شد' : 'کپی @theme'}</span>
              </button>
            </div>
            <pre
              dir="ltr"
              className="p-3 rounded-lg bg-slate-900 text-teal-300 font-mono text-[11px] max-h-44 overflow-y-auto leading-relaxed"
            >
              {themeBlock}
            </pre>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-bold text-slate-900">
                خروجی نهایی CSS مینیفای‌شده (
                {bundle.deliveryMode === 'inline'
                  ? `درون‌خطی <style id="tw-blocks-inline-css">`
                  : `فایل استاتیک <link href=".../merged-${bundle.hash}.css">`}
                )
              </span>
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(bundle.minifiedCss);
                  setCopiedCss(true);
                  setTimeout(() => setCopiedCss(false), 1500);
                }}
                className="flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 cursor-pointer"
              >
                {copiedCss ? <Check className="w-3.5 h-3.5 text-teal-600" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedCss ? 'کپی شد' : 'کپی CSS نهایی'}</span>
              </button>
            </div>
            <pre
              dir="ltr"
              className="p-3 rounded-lg bg-slate-950 text-slate-200 font-mono text-[11px] max-h-36 overflow-y-auto whitespace-pre-wrap break-all leading-relaxed"
            >
              {bundle.minifiedCss}
            </pre>
          </div>

          {/* Driver B HMAC Request Header Inspector */}
          <div className="p-3.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
            <div className="flex items-center gap-1.5 font-bold text-slate-800 mb-1.5">
              <KeyRound className="w-3.5 h-3.5 text-teal-600" />
              <span>هدرهای امنیتی امضاشده برای Driver B (Remote Build API):</span>
            </div>
            <div className="font-mono text-[11px] text-slate-700 space-y-1" dir="ltr">
              <div>POST /api/v1/compile HTTP/1.1</div>
              <div>X-TWB-Timestamp: {hmacTimestamp}</div>
              <div className="truncate text-teal-700">X-TWB-Signature: {hmacSignature}</div>
            </div>
          </div>
        </section>
      </div>
    </div>
  );
};
