import React, { useState } from 'react';
import { ChevronDown, Terminal, CheckCircle2, RefreshCw } from 'lucide-react';
import { twbLocalizeDigits } from '../engine/twBlocksEngine';

export interface DebugBarMetrics {
  files: string[];
  deliveryMode: 'inline' | 'file';
  totalBytes: number;
  sizeKb: number;
  classCount: number;
  driverId: string;
  driverLabel: string;
  buildTimeMs: number;
  cacheStatus: 'HIT' | 'MISS';
}

interface WpAdminDebugBarProps {
  metrics: DebugBarMetrics;
  enablePersianDigits: boolean;
  onNavigateSettings: () => void;
  onRebuildCache: () => void;
}

export const WpAdminDebugBar: React.FC<WpAdminDebugBarProps> = ({
  metrics,
  enablePersianDigits,
  onNavigateSettings,
  onRebuildCache,
}) => {
  const [open, setOpen] = useState(false);
  const containerRef = React.useRef<HTMLDivElement>(null);

  React.useEffect(() => {
    if (!open) return;
    const handleOutsideClick = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, [open]);

  const fmt = (val: string | number) => twbLocalizeDigits(val, enablePersianDigits);

  return (
    <div className="bg-slate-900 text-slate-100 border-b border-slate-800 text-xs select-none relative z-30">
      <div className="max-w-[1440px] mx-auto px-6 h-10 flex items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <span className="font-semibold text-slate-200 whitespace-nowrap">
            میزکار وردپرس (پیش‌نمایش مدیر)
          </span>
          <span className="text-slate-600" aria-hidden="true">·</span>
          <span className="text-slate-400 hidden sm:inline whitespace-nowrap">
            زبان فعال: فارسی (fa_IR) · چیدمان: راست‌به‌چپ (RTL)
          </span>
        </div>

        <div className="relative" ref={containerRef}>
          <button
            type="button"
            onClick={() => setOpen((prev) => !prev)}
            aria-expanded={open}
            aria-label="پنل دیباگ TW Blocks در نوار مدیریت"
            className="flex items-center gap-2 px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-100 transition-colors cursor-pointer whitespace-nowrap"
          >
            <Terminal className="w-3.5 h-3.5 text-teal-400 shrink-0" />
            <span className="font-medium tabular-nums">
              TW Blocks: {fmt(metrics.sizeKb)} کیلوبایت
            </span>
            <span className="text-slate-500" aria-hidden="true">·</span>
            <span className={metrics.cacheStatus === 'HIT' ? 'text-teal-300' : 'text-amber-300'}>
              {metrics.cacheStatus === 'HIT' ? 'کش فعال (HIT)' : 'بیلد جدید (MISS)'}
            </span>
            <ChevronDown className={`w-3.5 h-3.5 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
          </button>

          {open && (
            <div
              role="region"
              aria-label="جزئیات پنل دیباگ TW Blocks"
              className="absolute end-0 mt-1.5 w-96 bg-slate-900 border border-slate-700 rounded-lg shadow-xl p-4 text-slate-200 z-40"
            >
              <div className="flex items-center justify-between pb-2.5 mb-3 border-b border-slate-800">
                <span className="font-bold text-slate-100">پنل دیباگ صفحهٔ فعلی (مخصوص مدیر)</span>
                <span className="text-[11px] text-slate-400 font-mono" dir="ltr">
                  #wp-admin-bar-tw-blocks-debug
                </span>
              </div>

              <dl className="space-y-2 text-xs">
                <div className="flex items-start justify-between gap-3">
                  <dt className="text-slate-400">فایل(های) CSS لودشده:</dt>
                  <dd className="font-mono text-teal-300 text-end" dir="ltr">
                    {metrics.files.join(', ')}
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">روش تحویل در فرانت‌اند:</dt>
                  <dd className="text-slate-100 font-medium">
                    {metrics.deliveryMode === 'inline'
                      ? `درون‌خطی (<style> زیر ۱۲KB)`
                      : `فایل استاتیک (<link>)`}
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">حجم کل CSS مینیفای‌شده:</dt>
                  <dd className="tabular-nums font-semibold text-slate-100">
                    {fmt(metrics.sizeKb)} کیلوبایت ({fmt(metrics.totalBytes)} بایت)
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">تعداد کلاس‌های استخراج‌شده:</dt>
                  <dd className="tabular-nums font-semibold text-slate-100">
                    {fmt(metrics.classCount)} کلاس یکتا
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">درایور استفادهشده:</dt>
                  <dd className="text-slate-100 font-medium">{metrics.driverLabel}</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">زمان بیلد:</dt>
                  <dd className="tabular-nums text-slate-100">{fmt(metrics.buildTimeMs)} میلی‌ثانیه</dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <dt className="text-slate-400">وضعیت کش:</dt>
                  <dd className="flex items-center gap-1 font-medium text-teal-300">
                    <CheckCircle2 className="w-3.5 h-3.5" />
                    <span>{metrics.cacheStatus === 'HIT' ? 'HIT (خوانده‌شده از دیسک)' : 'MISS (کامپایل اتمیک جدید)'}</span>
                  </dd>
                </div>
              </dl>

              <div className="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                <button
                  type="button"
                  onClick={() => {
                    onRebuildCache();
                  }}
                  className="flex items-center gap-1.5 px-2.5 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors cursor-pointer"
                >
                  <RefreshCw className="w-3.5 h-3.5" />
                  <span>بازسازی کش صفحه</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setOpen(false);
                    onNavigateSettings();
                  }}
                  className="px-2.5 py-1.5 rounded bg-teal-600 hover:bg-teal-500 text-white font-medium transition-colors cursor-pointer"
                >
                  تنظیمات TW Blocks
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
