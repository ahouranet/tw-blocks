import React, { useState } from 'react';
import {
  CheckCircle2,
  XCircle,
  AlertTriangle,
  RefreshCw,
  Save,
  ShieldCheck,
  Upload,
} from 'lucide-react';
import {
  DesignTokens,
  PluginSettings,
  twbLocalizeDigits,
  twbNormalizeDigits,
  extractAndValidateClasses,
} from '../engine/twBlocksEngine';

export type HostEnvironmentPreset = 'cpanel_restricted' | 'remote_api_ready' | 'local_binary_ready';

interface SettingsSimulatorTabProps {
  tokens: DesignTokens;
  settings: PluginSettings;
  hostPreset: HostEnvironmentPreset;
  onChangeHostPreset: (preset: HostEnvironmentPreset) => void;
  onSaveAll: (newTokens: DesignTokens, newSettings: PluginSettings) => void;
  onPurgeCache: () => void;
}

export const SettingsSimulatorTab: React.FC<SettingsSimulatorTabProps> = ({
  tokens,
  settings,
  hostPreset,
  onChangeHostPreset,
  onSaveAll,
  onPurgeCache,
}) => {
  const [draftTokens, setDraftTokens] = useState<DesignTokens>(tokens);
  const [draftSettings, setDraftSettings] = useState<PluginSettings>(settings);
  const [inlineThresholdInput, setInlineThresholdInput] = useState<string>(
    twbLocalizeDigits(settings.inlineThresholdKb, settings.enablePersianDigits)
  );
  const [lineHeightInput, setLineHeightInput] = useState<string>(
    twbLocalizeDigits(tokens.typography.lineHeightBody, settings.enablePersianDigits)
  );
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [isTestingEnv, setIsTestingEnv] = useState(false);
  const [lastEnvTestTime, setLastEnvTestTime] = useState<string>('هم‌اکنون');

  const fmt = (val: string | number) => twbLocalizeDigits(val, draftSettings.enablePersianDigits);

  // Compute driver availability based on host preset & settings
  const driverAAvailable = hostPreset === 'local_binary_ready';
  const driverBAvailable =
    hostPreset === 'remote_api_ready' ||
    (draftSettings.remoteApiUrl.trim().startsWith('http') && draftSettings.remoteApiKey.trim().length >= 16);
  const driverCAvailable = true;

  const resolveActiveDriver = () => {
    if (draftSettings.preferredDriver === 'local_binary' && driverAAvailable) {
      return { id: 'local_binary', label: 'Driver A — باینری محلی Tailwind v4' };
    }
    if (draftSettings.preferredDriver === 'remote_api' && driverBAvailable) {
      return { id: 'remote_api', label: 'Driver B — سرور بیلد از راه دور (HMAC API)' };
    }
    if (draftSettings.preferredDriver === 'fallback_safelist') {
      return { id: 'fallback_safelist', label: 'Driver C — فایل آمادهٔ Safelist (جایگزین)' };
    }
    if (driverAAvailable) {
      return { id: 'local_binary', label: 'Driver A — باینری محلی Tailwind v4' };
    }
    if (driverBAvailable) {
      return { id: 'remote_api', label: 'Driver B — سرور بیلد از راه دور (HMAC API)' };
    }
    return { id: 'fallback_safelist', label: 'Driver C — فایل آمادهٔ Safelist (جایگزین)' };
  };

  const activeDriver = resolveActiveDriver();

  const handleRunEnvTest = () => {
    setIsTestingEnv(true);
    setTimeout(() => {
      setIsTestingEnv(false);
      setLastEnvTestTime('چند لحظه پیش (REST /wp-json/tw-blocks/v1/test-env)');
      setStatusMessage('گزارش «تست محیط» با موفقیت بروزرسانی شد.');
    }, 180);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const normalizedInlineKb = Number(twbNormalizeDigits(inlineThresholdInput)) || 12;
    const normalizedLineHeight = Number(twbNormalizeDigits(lineHeightInput)) || 1.8;
    const clampedLineHeight = Math.min(2.4, Math.max(1.3, normalizedLineHeight));

    const { classes: validatedSafelist } = extractAndValidateClasses('', [draftSettings.safelist]);

    const updatedTokens: DesignTokens = {
      ...draftTokens,
      typography: {
        ...draftTokens.typography,
        lineHeightBody: String(clampedLineHeight),
        letterSpacingFa: '0em',
      },
    };

    const updatedSettings: PluginSettings = {
      ...draftSettings,
      inlineThresholdKb: Math.min(50, Math.max(0, normalizedInlineKb)),
      safelist: validatedSafelist.join(' '),
    };

    setDraftTokens(updatedTokens);
    setDraftSettings(updatedSettings);
    onSaveAll(updatedTokens, updatedSettings);
    setStatusMessage(
      'تنظیمات و توکن‌های طراحی با موفقیت ذخیره شدند (ارقام فارسی به لاتین نرمال‌سازی شدند و کش CSS بازسازی گردید).'
    );
  };

  const handleSimulateFontUpload = () => {
    setDraftTokens((prev) => ({
      ...prev,
      typography: {
        ...prev.typography,
        customFontName: 'IRANSansX',
        customWoff2Url: '/wp-content/uploads/2026/11/IRANSansX-Variable.woff2',
        fontFamilyBase: '"IRANSansX", Vazirmatn, Tahoma, system-ui, sans-serif',
        fontFamilyHeading: '"IRANSansX", Vazirmatn, Tahoma, system-ui, sans-serif',
      },
    }));
    setStatusMessage('فایل فونت محلی IRANSansX-Variable.woff2 انتخاب شد. برای اعمال نهایی دکمهٔ ذخیره را بزنید.');
  };

  return (
    <div className="space-y-6">
      {/* Admin Notice when Driver C (Fallback Safelist) is active */}
      {activeDriver.id === 'fallback_safelist' && (
        <div
          role="alert"
          className="bg-amber-50 border border-amber-300 rounded-lg p-4 flex items-start gap-3 text-amber-950"
        >
          <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
          <div className="text-sm">
            <p className="font-bold">
              TW Blocks — حالت جایگزین (Driver C: Safelist) فعال است:
            </p>
            <p className="mt-1 text-amber-900">
              باینری محلی Tailwind v4 (Driver A) یا سرور بیلد از راه دور (Driver B) روی این محیط هاست فعال نیست. افزونه هم‌اکنون از فایل CSS آمادهٔ{' '}
              <code className="font-mono text-xs bg-amber-100 px-1.5 py-0.5 rounded" dir="ltr">
                safelist-fallback.css
              </code>{' '}
              به همراه سینتسایزر داخلی کلاس‌های Logical استفاده می‌کند تا سایت بدون هیچ خطایی روی هاست اشتراکی cPanel کار کند.
            </p>
          </div>
        </div>
      )}

      {statusMessage && (
        <div
          role="status"
          className="bg-teal-50 border border-teal-300 rounded-lg p-3.5 flex items-center justify-between gap-3 text-teal-950 text-sm"
        >
          <div className="flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-teal-600 shrink-0" />
            <span>{statusMessage}</span>
          </div>
          <button
            type="button"
            onClick={() => setStatusMessage(null)}
            className="text-xs text-teal-700 hover:text-teal-900 font-medium cursor-pointer"
          >
            بستن پیام
          </button>
        </div>
      )}

      {/* Host Environment Preset Bar + Test Environment Button */}
      <section className="bg-white border border-slate-200 rounded-lg p-6">
        <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-200">
          <div>
            <h2 className="text-lg font-bold text-slate-900">
              وضعیت موتور بیلد CSS و گزارش «تست محیط»
            </h2>
            <p className="text-xs text-slate-500 mt-0.5">
              درایور انتخاب‌شدهٔ فعلی:{' '}
              <strong className="text-teal-700 font-semibold">{activeDriver.label}</strong>
              <span className="mx-2" aria-hidden="true">·</span>
              <span>آخرین بررسی: {lastEnvTestTime}</span>
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={handleRunEnvTest}
              disabled={isTestingEnv}
              className="flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition-colors cursor-pointer whitespace-nowrap"
            >
              <ShieldCheck className="w-4 h-4 text-teal-400" />
              <span>{isTestingEnv ? 'در حال بررسی محیط هاست...' : 'تست محیط (بررسی درایورها)'}</span>
            </button>

            <button
              type="button"
              onClick={() => {
                onPurgeCache();
                setStatusMessage('کش CSS پاک‌سازی شد و فایل پایه (base) با موفقیت بازسازی گردید.');
              }}
              className="flex items-center gap-1.5 px-3.5 py-2 text-xs font-medium rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer whitespace-nowrap"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>پاک‌سازی و بازسازی کش CSS</span>
            </button>
          </div>
        </div>

        {/* Interactive Host Simulator Selector */}
        <div className="mt-4 pt-1 flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
          <span className="text-xs font-semibold text-slate-700">
            شبیه‌سازی شرایط سرور هاست (برای تست رفتار خودکار درایورها):
          </span>
          <div className="flex flex-wrap items-center gap-1.5">
            <button
              type="button"
              onClick={() => onChangeHostPreset('cpanel_restricted')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer ${
                hostPreset === 'cpanel_restricted'
                  ? 'bg-teal-700 text-white'
                  : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              هاست اشتراکی cPanel محدود (Driver C)
            </button>
            <button
              type="button"
              onClick={() => {
                onChangeHostPreset('remote_api_ready');
                setDraftSettings((prev) => ({
                  ...prev,
                  remoteApiUrl: prev.remoteApiUrl || 'https://build.twblocks.ir/api/v1/compile',
                  remoteApiKey: prev.remoteApiKey || 'twb_hmac_live_secret_9876543210abcdef',
                }));
              }}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer ${
                hostPreset === 'remote_api_ready'
                  ? 'bg-teal-700 text-white'
                  : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              متصل به سرور بیلد راه دور (Driver B)
            </button>
            <button
              type="button"
              onClick={() => onChangeHostPreset('local_binary_ready')}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer ${
                hostPreset === 'local_binary_ready'
                  ? 'bg-teal-700 text-white'
                  : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              سرور دارای باینری محلی Tailwind v4 (Driver A)
            </button>
          </div>
        </div>

        {/* 3 Drivers Diagnostic Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          {/* Driver A */}
          <div
            className={`p-4 rounded-lg border ${
              driverAAvailable ? 'border-teal-600 bg-teal-50/40' : 'border-slate-200 bg-slate-50/60'
            }`}
          >
            <div className="flex items-center justify-between mb-2.5">
              <h3 className="text-sm font-bold text-slate-900">Driver A — باینری محلی</h3>
              <span
                className={`text-xs font-semibold ${
                  driverAAvailable ? 'text-teal-700' : 'text-slate-500'
                }`}
              >
                {driverAAvailable ? 'آماده' : 'غیرفعال'}
              </span>
            </div>
            <ul className="space-y-1.5 text-xs text-slate-600">
              <li className="flex items-start gap-1.5">
                {driverAAvailable ? (
                  <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                )}
                <span>
                  <strong>تابع proc_open():</strong>{' '}
                  {driverAAvailable
                    ? 'فعال در زمان اجرای PHP'
                    : 'غیرفعال در disable_functions هاست اشتراکی'}
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                {driverAAvailable ? (
                  <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                )}
                <span>
                  <strong>مسیر باینری:</strong>{' '}
                  <code className="font-mono text-[11px]" dir="ltr">
                    uploads/tw-blocks/bin/tailwindcss
                  </code>
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                {driverAAvailable ? (
                  <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                )}
                <span>
                  <strong>تست اجرایی CLI:</strong>{' '}
                  {driverAAvailable ? 'tailwindcss v4.1.0 (OK)' : 'رد شد (عدم دسترسی شل)'}
                </span>
              </li>
            </ul>
          </div>

          {/* Driver B */}
          <div
            className={`p-4 rounded-lg border ${
              driverBAvailable ? 'border-teal-600 bg-teal-50/40' : 'border-slate-200 bg-slate-50/60'
            }`}
          >
            <div className="flex items-center justify-between mb-2.5">
              <h3 className="text-sm font-bold text-slate-900">Driver B — API بیلد از راه دور</h3>
              <span
                className={`text-xs font-semibold ${
                  driverBAvailable ? 'text-teal-700' : 'text-slate-500'
                }`}
              >
                {driverBAvailable ? 'آماده' : 'غیرفعال'}
              </span>
            </div>
            <ul className="space-y-1.5 text-xs text-slate-600">
              <li className="flex items-start gap-1.5">
                {driverBAvailable ? (
                  <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                )}
                <span>
                  <strong>Endpoint URL:</strong>{' '}
                  {driverBAvailable ? 'معتبر و در دسترس (HTTPS)' : 'تنظیم نشده'}
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                {driverBAvailable ? (
                  <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                )}
                <span>
                  <strong>امضای درخواست HMAC-SHA256:</strong>{' '}
                  {driverBAvailable ? 'کلید امنیتی ۳۲ کاراکتری معتبر' : 'کلید امنیتی تنظیم نشده'}
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                <span>
                  <strong>حریم خصوصی:</strong> فقط آرایهٔ کلاس‌ها و <code dir="ltr">@theme</code> ارسال می‌شود (بدون HTML).
                </span>
              </li>
            </ul>
          </div>

          {/* Driver C */}
          <div className="p-4 rounded-lg border border-teal-600 bg-teal-50/40">
            <div className="flex items-center justify-between mb-2.5">
              <h3 className="text-sm font-bold text-slate-900">Driver C — Safelist آماده</h3>
              <span className="text-xs font-semibold text-teal-700">
                {driverCAvailable ? 'آماده (همیشه فعال)' : 'غیرفعال'}
              </span>
            </div>
            <ul className="space-y-1.5 text-xs text-slate-600">
              <li className="flex items-start gap-1.5">
                <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                <span>
                  <strong>فایل Safelist همراه افزونه:</strong>{' '}
                  <code className="font-mono text-[11px]" dir="ltr">
                    assets/css/safelist-fallback.css
                  </code>
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                <span>
                  <strong>سینتسایزر داخلی PHP:</strong> تولید خودکار کلاس‌های Logical (<code dir="ltr">ms-*</code>, <code dir="ltr">ps-*</code>, <code dir="ltr">gap-*</code>)
                </span>
              </li>
              <li className="flex items-start gap-1.5">
                <CheckCircle2 className="w-3.5 h-3.5 text-teal-600 shrink-0 mt-0.5" />
                <span>
                  <strong>تضمین پایداری:</strong> ۱۰۰٪ سازگار با محدودترین هاست‌های اشتراکی cPanel
                </span>
              </li>
            </ul>
          </div>
        </div>
      </section>

      {/* Main Settings & Tokens Form */}
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Section 1: CSS Engine & Safelist */}
        <section className="bg-white border border-slate-200 rounded-lg p-6">
          <h2 className="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-200">
            ۱. تنظیمات موتور بیلد CSS، آستانهٔ Inline و Safelist
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label
                htmlFor="sim-preferred-driver"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                انتخاب درایور بیلد
              </label>
              <select
                id="sim-preferred-driver"
                value={draftSettings.preferredDriver}
                onChange={(e) =>
                  setDraftSettings((prev) => ({
                    ...prev,
                    preferredDriver: e.target.value as PluginSettings['preferredDriver'],
                  }))
                }
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              >
                <option value="auto">خودکار (پیشنهادی: Driver A ← Driver B ← Driver C)</option>
                <option value="local_binary">Driver A — باینری محلی Tailwind v4</option>
                <option value="remote_api">Driver B — سرور بیلد از راه دور (HMAC API)</option>
                <option value="fallback_safelist">Driver C — فایل آمادهٔ Safelist</option>
              </select>
            </div>

            <div>
              <label
                htmlFor="sim-inline-threshold"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                آستانهٔ Inline شدن CSS در تگ {'<style>'} (کیلوبایت — پشتیبانی از ارقام فارسی و لاتین)
              </label>
              <input
                id="sim-inline-threshold"
                type="text"
                dir="auto"
                value={inlineThresholdInput}
                onChange={(e) => setInlineThresholdInput(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              />
              <p className="text-[11px] text-slate-500 mt-1">
                مقدار نرمال‌سازی‌شده در زمان ذخیره:{' '}
                <code className="font-mono text-teal-700" dir="ltr">
                  {twbNormalizeDigits(inlineThresholdInput) || '12'} KB
                </code>
              </p>
            </div>

            <div>
              <label
                htmlFor="sim-remote-url"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                آدرس API بیلد از راه دور (Driver B — اجباراً LTR)
              </label>
              <input
                id="sim-remote-url"
                type="url"
                dir="ltr"
                placeholder="https://build.example.com/api/v1/compile"
                value={draftSettings.remoteApiUrl}
                onChange={(e) =>
                  setDraftSettings((prev) => ({ ...prev, remoteApiUrl: e.target.value }))
                }
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs twb-ltr-input text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              />
            </div>

            <div>
              <label
                htmlFor="sim-remote-key"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                کلید امنیتی امضای HMAC-SHA256 (Driver B — اجباراً LTR)
              </label>
              <input
                id="sim-remote-key"
                type="text"
                dir="ltr"
                placeholder="twb_hmac_secret_min_16_chars..."
                value={draftSettings.remoteApiKey}
                onChange={(e) =>
                  setDraftSettings((prev) => ({ ...prev, remoteApiKey: e.target.value }))
                }
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs twb-ltr-input text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              />
            </div>

            <div className="md:col-span-2">
              <label
                htmlFor="sim-safelist"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                کلاس‌های Safelist (کلاس‌های داینامیک — دارای کیبورد و جهت اجباری dir="ltr")
              </label>
              <textarea
                id="sim-safelist"
                rows={2}
                dir="ltr"
                value={draftSettings.safelist}
                onChange={(e) =>
                  setDraftSettings((prev) => ({ ...prev, safelist: e.target.value }))
                }
                className="w-full rounded-lg border border-slate-300 p-3 text-xs twb-ltr-input text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
              />
            </div>
          </div>
        </section>

        {/* Section 2: Design Tokens (@theme & theme.json Bridge) */}
        <section className="bg-white border border-slate-200 rounded-lg p-6">
          <h2 className="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-200">
            ۲. توکن‌های طراحی (@theme و همگام‌سازی با متغیرهای theme.json)
          </h2>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            {(
              [
                ['primary', 'رنگ اصلی (Primary)'],
                ['secondary', 'رنگ ثانویه (Secondary)'],
                ['accent', 'رنگ تأکیدی (Accent)'],
                ['surface', 'پس‌زمینه کارت (Surface)'],
                ['foreground', 'رنگ متن (Foreground)'],
                ['border', 'خطوط جداکننده (Border)'],
              ] as const
            ).map(([key, labelFa]) => (
              <div key={key} className="border border-slate-200 rounded-lg p-3 bg-slate-50/50">
                <label
                  htmlFor={`token-color-${key}`}
                  className="block text-xs font-semibold text-slate-700 mb-1.5"
                >
                  {labelFa}
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    aria-label={labelFa}
                    value={draftTokens.colors[key]}
                    onChange={(e) =>
                      setDraftTokens((prev) => ({
                        ...prev,
                        colors: { ...prev.colors, [key]: e.target.value },
                      }))
                    }
                    className="w-7 h-7 rounded border border-slate-300 cursor-pointer shrink-0"
                  />
                  <input
                    id={`token-color-${key}`}
                    type="text"
                    dir="ltr"
                    value={draftTokens.colors[key]}
                    onChange={(e) =>
                      setDraftTokens((prev) => ({
                        ...prev,
                        colors: { ...prev.colors, [key]: e.target.value },
                      }))
                    }
                    className="w-full rounded border border-slate-300 px-2 py-1 text-xs twb-ltr-input bg-white"
                  />
                </div>
                <div className="mt-1.5 text-[10px] font-mono text-slate-400" dir="ltr">
                  --wp--preset--color--{key}
                </div>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-5 pt-4 border-t border-slate-200">
            <div>
              <label
                htmlFor="sim-line-height"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                فاصلهٔ خطوط متن فارسی (Line-Height پیش‌فرض: {fmt('1.8')})
              </label>
              <input
                id="sim-line-height"
                type="text"
                dir="auto"
                value={lineHeightInput}
                onChange={(e) => setLineHeightInput(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
              <p className="text-[11px] text-slate-500 mt-1">
                فاصلهٔ حروف (<code dir="ltr">letter-spacing</code>) در متن فارسی روی <code dir="ltr">0em</code> قفل است.
              </p>
            </div>

            <div>
              <label
                htmlFor="sim-container-width"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                حداکثر عرض Container اصلی
              </label>
              <input
                id="sim-container-width"
                type="text"
                dir="ltr"
                value={draftTokens.layout.containerWidth}
                onChange={(e) =>
                  setDraftTokens((prev) => ({
                    ...prev,
                    layout: { ...prev.layout, containerWidth: e.target.value },
                  }))
                }
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs twb-ltr-input text-slate-900"
              />
            </div>

            <div>
              <label
                htmlFor="sim-woff2-url"
                className="block text-xs font-semibold text-slate-800 mb-1.5"
              >
                فونت فارسی محلی (Vazirmatn یا آپلود WOFF2 سفارشی)
              </label>
              <div className="flex items-center gap-2">
                <input
                  id="sim-woff2-url"
                  type="text"
                  dir="ltr"
                  placeholder="Vazirmatn-Variable.woff2 (Self-Hosted)"
                  value={draftTokens.typography.customWoff2Url}
                  onChange={(e) =>
                    setDraftTokens((prev) => ({
                      ...prev,
                      typography: { ...prev.typography, customWoff2Url: e.target.value },
                    }))
                  }
                  className="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs twb-ltr-input text-slate-900"
                />
                <button
                  type="button"
                  onClick={handleSimulateFontUpload}
                  className="flex items-center gap-1 px-3 py-2 text-xs font-medium rounded-lg border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-800 shrink-0 cursor-pointer"
                >
                  <Upload className="w-3.5 h-3.5" />
                  <span>آپلود WOFF2</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        {/* Section 3: Persian Localization, Jalali Date & Core Asset Pruning */}
        <section className="bg-white border border-slate-200 rounded-lg p-6">
          <h2 className="text-base font-bold text-slate-900 pb-3 mb-4 border-b border-slate-200">
            ۳. بومی‌سازی فارسی، تاریخ شمسی (Jalali) و حذف استایل‌های اضافی هستهٔ وردپرس
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50/50 cursor-pointer">
              <input
                type="checkbox"
                checked={draftSettings.enablePersianDigits}
                onChange={(e) =>
                  setDraftSettings((prev) => ({
                    ...prev,
                    enablePersianDigits: e.target.checked,
                  }))
                }
                className="mt-1 rounded text-teal-600 focus:ring-teal-500"
              />
              <div>
                <span className="font-semibold text-slate-900 block">
                  نمایش ارقام فارسی (۰-۹) در خروجی محتوای دینامیک
                </span>
                <span className="text-xs text-slate-500">
                  تبدیل امن اعداد با تابع <code dir="ltr">twb_localize_digits()</code> بدون دستکاری تگ‌ها و ویژگی‌های HTML.
                </span>
              </div>
            </label>

            <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50/50 cursor-pointer">
              <input
                type="checkbox"
                checked={draftSettings.enableJalaliDate}
                onChange={(e) =>
                  setDraftSettings((prev) => ({
                    ...prev,
                    enableJalaliDate: e.target.checked,
                  }))
                }
                className="mt-1 rounded text-teal-600 focus:ring-teal-500"
              />
              <div>
                <span className="font-semibold text-slate-900 block">
                  فعال‌سازی تاریخ شمسی (جلالی) در بلوک‌های دینامیک
                </span>
                <span className="text-xs text-slate-500">
                  استفادهٔ خودکار از WP-Parsidate در صورت نصب بودن، یا مبدل داخلی سبک بدون تغییر تاریخ میلادی دیتابیس.
                </span>
              </div>
            </label>

            <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50/50 cursor-pointer">
              <input
                type="checkbox"
                checked={draftSettings.disableWpGlobalStyles}
                onChange={(e) =>
                  setDraftSettings((prev) => ({
                    ...prev,
                    disableWpGlobalStyles: e.target.checked,
                  }))
                }
                className="mt-1 rounded text-teal-600 focus:ring-teal-500"
              />
              <div>
                <span className="font-semibold text-slate-900 block">
                  غیرفعال‌سازی global-styles هستهٔ وردپرس
                </span>
                <span className="text-xs text-slate-500">
                  مخصوص سایت‌هایی که فقط از بلوک‌های TW Blocks استفاده می‌کنند (کاهش حجم HTML و CSS صفحه).
                </span>
              </div>
            </label>

            <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50/50 cursor-pointer">
              <input
                type="checkbox"
                checked={draftTokens.preflight.enabled}
                onChange={(e) =>
                  setDraftTokens((prev) => ({
                    ...prev,
                    preflight: { ...prev.preflight, enabled: e.target.checked },
                  }))
                }
                className="mt-1 rounded text-teal-600 focus:ring-teal-500"
              />
              <div>
                <span className="font-semibold text-slate-900 block">
                  اعمال Preflight-Lite محدودشده به کلاس <code dir="ltr">.twb-root</code>
                </span>
                <span className="text-xs text-slate-500">
                  ریست حداقلی بدون بارگذاری Preflight کامل Tailwind تا قالب فعال سایت آسیب نبیند.
                </span>
              </div>
            </label>
          </div>
        </section>

        <div className="flex items-center justify-end gap-3">
          <button
            type="submit"
            className="flex items-center gap-2 px-6 py-2.5 rounded-lg bg-teal-700 hover:bg-teal-600 text-white text-sm font-semibold transition-colors cursor-pointer"
          >
            <Save className="w-4 h-4" />
            <span>ذخیرهٔ تنظیمات و بازسازی CSS</span>
          </button>
        </div>
      </form>
    </div>
  );
};
