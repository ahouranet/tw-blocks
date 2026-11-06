import React, { useState } from 'react';
import {
  FolderTree,
  FileCode,
  Copy,
  Check,
  Download,
  CheckSquare,
  Terminal,
} from 'lucide-react';
import { twbLocalizeDigits } from '../engine/twBlocksEngine';

export interface PluginFileEntry {
  path: string;
  category: string;
  descriptionFa: string;
  language: string;
  content: string;
  sizeBytes: number;
  lineCount: number;
}

interface SourceFilesExplorerTabProps {
  files: PluginFileEntry[];
  enablePersianDigits: boolean;
}

export const SourceFilesExplorerTab: React.FC<SourceFilesExplorerTabProps> = ({
  files,
  enablePersianDigits,
}) => {
  const [selectedPath, setSelectedPath] = useState<string>(
    files[0]?.path || 'tw-blocks/tw-blocks.php'
  );
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [copiedFile, setCopiedFile] = useState<boolean>(false);

  const fmt = (val: string | number) => twbLocalizeDigits(val, enablePersianDigits);

  const filteredFiles = files.filter(
    (f) =>
      f.path.toLowerCase().includes(searchQuery.toLowerCase()) ||
      f.descriptionFa.includes(searchQuery) ||
      f.category.includes(searchQuery)
  );

  const currentFile =
    files.find((f) => f.path === selectedPath) || filteredFiles[0] || files[0];

  const handleDownloadFile = (file: PluginFileEntry) => {
    const blob = new Blob([file.content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = file.path.split('/').pop() || 'file.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6">
      {/* File Explorer Split View */}
      <section className="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div className="p-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2">
            <FolderTree className="w-4 h-4 text-teal-600" />
            <h2 className="text-base font-bold text-slate-900">
              مرورگر فایل‌های اجرایی فاز ۱ افزونه (<code dir="ltr">/tw-blocks/*</code> — {fmt(files.length)} فایل کامل)
            </h2>
          </div>

          <div className="w-full sm:w-72">
            <input
              type="search"
              dir="auto"
              aria-label="جستجو در فایل‌های افزونه"
              placeholder="جستجوی نام فایل یا عملکرد (مثلاً CssEngine)..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600"
            />
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 min-h-[540px]">
          {/* Sidebar File List */}
          <div className="lg:col-span-4 border-b lg:border-b-0 lg:border-e border-slate-200 max-h-[560px] overflow-y-auto divide-y divide-slate-100">
            {filteredFiles.map((file) => {
              const isSelected = currentFile?.path === file.path;
              return (
                <button
                  key={file.path}
                  type="button"
                  onClick={() => setSelectedPath(file.path)}
                  className={`w-full text-start p-3.5 transition-colors cursor-pointer flex flex-col gap-1 ${
                    isSelected
                      ? 'bg-teal-50/90 text-teal-950 font-semibold'
                      : 'hover:bg-slate-50 text-slate-700'
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-mono text-xs text-teal-800 truncate" dir="ltr">
                      {file.path}
                    </span>
                    <span className="text-[11px] text-slate-400 tabular-nums shrink-0">
                      {fmt(file.lineCount)} خط
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 line-clamp-2 font-normal leading-relaxed">
                    {file.descriptionFa}
                  </p>
                </button>
              );
            })}
          </div>

          {/* Code Viewer */}
          <div className="lg:col-span-8 flex flex-col bg-slate-950 text-slate-100">
            {currentFile && (
              <>
                <div className="px-4 py-3 bg-slate-900 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <FileCode className="w-4 h-4 text-teal-400" />
                      <code className="font-mono text-xs font-bold text-white" dir="ltr">
                        {currentFile.path}
                      </code>
                      <span className="text-slate-500" aria-hidden="true">·</span>
                      <span className="text-xs text-slate-400 tabular-nums">
                        {fmt(currentFile.lineCount)} خط ({fmt(currentFile.sizeBytes)} بایت)
                      </span>
                    </div>
                    <p className="text-xs text-slate-300 mt-1">{currentFile.descriptionFa}</p>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => {
                        navigator.clipboard.writeText(currentFile.content);
                        setCopiedFile(true);
                        setTimeout(() => setCopiedFile(false), 1500);
                      }}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-xs text-slate-100 transition-colors cursor-pointer"
                    >
                      {copiedFile ? (
                        <Check className="w-3.5 h-3.5 text-teal-400" />
                      ) : (
                        <Copy className="w-3.5 h-3.5" />
                      )}
                      <span>{copiedFile ? 'کپی شد' : 'کپی کد کامل'}</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDownloadFile(currentFile)}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded bg-teal-700 hover:bg-teal-600 text-xs text-white transition-colors cursor-pointer"
                    >
                      <Download className="w-3.5 h-3.5" />
                      <span>دانلود فایل</span>
                    </button>
                  </div>
                </div>

                <pre
                  dir="ltr"
                  className="p-4 font-mono text-xs text-slate-200 overflow-auto max-h-[480px] leading-relaxed flex-1"
                >
                  {currentFile.content}
                </pre>
              </>
            )}
          </div>
        </div>
      </section>

      {/* Build Commands & Phase 1 Verification Checklist */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-3">
          <div className="flex items-center gap-2 pb-2 border-b border-slate-200">
            <Terminal className="w-4 h-4 text-teal-600" />
            <h3 className="text-sm font-bold text-slate-900">
              دستورهای ساخت فایل‌های ترجمه (i18n) و بسته‌بندی ZIP افزونه
            </h3>
          </div>
          <p className="text-xs text-slate-600">
            بیلد دارایی‌ها روی سیستم توسعه‌دهنده انجام می‌شود تا فایل ZIP نهایی روی هر هاست اشتراکی cPanel بدون نیاز به Node یا Composer نصب شود:
          </p>
          <pre
            dir="ltr"
            className="p-3.5 rounded-lg bg-slate-900 text-teal-300 font-mono text-xs overflow-x-auto leading-relaxed"
          >
{`# 1. Generate POT template from PHP & JS sources
wp i18n make-pot ./tw-blocks ./tw-blocks/languages/tw-blocks.pot --domain=tw-blocks

# 2. Compile Persian PO to binary MO
msgfmt ./tw-blocks/languages/tw-blocks-fa_IR.po -o ./tw-blocks/languages/tw-blocks-fa_IR.mo

# 3. Generate Jed JSON translation files for @wordpress/i18n scripts
wp i18n make-json ./tw-blocks/languages/tw-blocks-fa_IR.po --no-purge

# 4. Package production-ready WordPress plugin ZIP (no Node needed on host)
zip -r tw-blocks-v1.0.0.zip tw-blocks -x "tw-blocks/server-reference/node_modules/*"`}
          </pre>
        </section>

        <section className="bg-white border border-slate-200 rounded-lg p-6 space-y-3">
          <div className="flex items-center gap-2 pb-2 border-b border-slate-200">
            <CheckSquare className="w-4 h-4 text-teal-600" />
            <h3 className="text-sm font-bold text-slate-900">
              چک‌لیست تست فاز ۱ (RTL، فونت فارسی، ارقام فارسی، تاریخ شمسی و امنیت)
            </h3>
          </div>
          <ul className="space-y-2 text-xs text-slate-700">
            <li>
              <strong>۱. تست RTL و Logical Properties:</strong> بررسی عدم وجود <code dir="ltr">ml-*</code> / <code dir="ltr">mr-*</code> / <code dir="ltr">text-left</code> در استایل‌ها و عملکرد صحیح در <code dir="ltr">dir="rtl"</code> و <code dir="ltr">dir="ltr"</code>.
            </li>
            <li>
              <strong>۲. تست فونت متغیر محلی Vazirmatn:</strong> بررسی تگ <code dir="ltr">&lt;link rel="preload" as="font" type="font/woff2"&gt;</code> و عدم ارسال هیچ درخواستی به Google Fonts یا CDN خارجی.
            </li>
            <li>
              <strong>۳. تست ارقام فارسی و نرمال‌سازی:</strong> وارد کردن عدد فارسی <code dir="ltr">۱۲</code> در فیلد آستانهٔ Inline و اطمینان از ذخیرهٔ <code dir="ltr">12</code> در دیتابیس، و نمایش ارقام فارسی در خروجی <code dir="ltr">twb_localize_digits()</code> بدون خرابی تگ‌های HTML.
            </li>
            <li>
              <strong>۴. تست تاریخ شمسی (Jalali):</strong> بررسی خروجی <code dir="ltr">twb_format_date()</code> با حضور و بدون حضور افزونهٔ WP-Parsidate.
            </li>
            <li>
              <strong>۵. تست امنیت و کش اتمیک:</strong> بررسی Nonce، سطح دسترسی <code dir="ltr">edit_theme_options</code>، نوشتن اتمیک فایل‌ها در <code dir="ltr">uploads/tw-blocks/css/</code> و امضای HMAC در Driver B.
            </li>
          </ul>
        </section>
      </div>
    </div>
  );
};
