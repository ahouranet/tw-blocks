import React, { useState, useEffect, useMemo } from 'react';
import {
  Settings,
  Cpu,
  Languages,
  FolderCode,
  ArrowLeftRight,
  Blocks,
  LayoutTemplate,
} from 'lucide-react';
import {
  DesignTokens,
  PluginSettings,
  getDefaultDesignTokens,
  extractAndValidateClasses,
  compileCssBundle,
  twbLocalizeDigits,
} from './engine/twBlocksEngine';
import { WpAdminDebugBar, DebugBarMetrics } from './components/WpAdminDebugBar';
import {
  SettingsSimulatorTab,
  HostEnvironmentPreset,
} from './components/SettingsSimulatorTab';
import { CssEngineLabTab } from './components/CssEngineLabTab';
import { PersianLocalizationLabTab } from './components/PersianLocalizationLabTab';
import {
  SourceFilesExplorerTab,
  PluginFileEntry,
} from './components/SourceFilesExplorerTab';
import { BlocksAndSecurityTab } from './components/BlocksAndSecurityTab';
import { PatternsAndPortabilityTab } from './components/PatternsAndPortabilityTab';

type ActiveTab =
  | 'patterns_phase3'
  | 'blocks_phase2'
  | 'settings'
  | 'css_lab'
  | 'persian_lab'
  | 'source_files';

const INITIAL_SAMPLE_MARKUP = `<!-- wp:tw-blocks/section {"tagName":"section","className":"py-12 px-6 bg-surface rounded-xl border border-border"} -->
<section class="twb-root py-12 px-6 bg-surface rounded-xl border border-border">
  <!-- wp:tw-blocks/grid {"twClasses":"grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch"} -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">
    <article class="p-6 bg-background rounded-lg border border-border text-start">
      <h2 class="text-xl font-bold text-foreground mb-2">سرعت بارگذاری فوق‌العاده</h2>
      <p class="text-sm text-muted leading-relaxed">فقط کلاس‌های واقعاً استفاده‌شده در هر برگه استخراج و در یک فایل استاتیک کش می‌شوند.</p>
    </article>
  </div>
  <!-- /wp:tw-blocks/grid -->
</section>
<!-- /wp:tw-blocks/section -->`;

export default function App() {
  const [activeTab, setActiveTab] = useState<ActiveTab>('patterns_phase3');
  const [dirMode, setDirMode] = useState<'rtl' | 'ltr'>('rtl');
  const [tokens, setTokens] = useState<DesignTokens>(getDefaultDesignTokens);
  const [settings, setSettings] = useState<PluginSettings>({
    preferredDriver: 'auto',
    remoteApiUrl: 'https://build.twblocks.ir/api/v1/compile',
    remoteApiKey: 'twb_hmac_live_secret_9876543210abcdef',
    remoteApiTimeout: 8,
    inlineThresholdKb: 12,
    safelist:
      'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ps-4 pe-4 ms-auto me-auto text-start text-end rtl:-scale-x-100',
    enablePersianDigits: true,
    enableJalaliDate: true,
    disableWpGlobalStyles: false,
    disableCoreBlockLibrary: false,
  });

  const [hostPreset, setHostPreset] =
    useState<HostEnvironmentPreset>('remote_api_ready');
  const [sampleMarkup, setSampleMarkup] = useState<string>(INITIAL_SAMPLE_MARKUP);
  const [extraClasses, setExtraClasses] = useState<string>(
    'ps-6 pe-6 ms-auto text-start md:grid-cols-3 rtl:-scale-x-100'
  );
  const [cacheStatus, setCacheStatus] = useState<'HIT' | 'MISS'>('HIT');
  const [pluginFiles, setPluginFiles] = useState<PluginFileEntry[]>([]);

  useEffect(() => {
    document.documentElement.dir = dirMode;
    document.documentElement.lang = dirMode === 'rtl' ? 'fa' : 'en';
  }, [dirMode]);

  useEffect(() => {
    fetch('/api/plugin-files')
      .then((r) => r.json())
      .then((data) => {
        if (Array.isArray(data.files)) {
          setPluginFiles(data.files);
        }
      })
      .catch(() => {
        // Ignore if offline
      });
  }, []);

  const debugMetrics: DebugBarMetrics = useMemo(() => {
    const { classes } = extractAndValidateClasses(sampleMarkup, [
      extraClasses,
      settings.safelist,
    ]);
    const compiled = compileCssBundle(
      classes,
      tokens,
      settings.inlineThresholdKb
    );

    let driverId = 'fallback_safelist';
    let driverLabel = 'Driver C — Safelist آماده';
    let buildTimeMs = 4.2;

    if (hostPreset === 'local_binary_ready' && settings.preferredDriver !== 'fallback_safelist') {
      driverId = 'local_binary';
      driverLabel = 'Driver A — باینری محلی Tailwind v4';
      buildTimeMs = cacheStatus === 'HIT' ? 0.4 : 48.5;
    } else if (
      hostPreset === 'remote_api_ready' &&
      settings.preferredDriver !== 'fallback_safelist'
    ) {
      driverId = 'remote_api';
      driverLabel = 'Driver B — سرور بیلد راه دور (HMAC)';
      buildTimeMs = cacheStatus === 'HIT' ? 0.5 : 134.0;
    } else {
      buildTimeMs = cacheStatus === 'HIT' ? 0.3 : 6.8;
    }

    const shortHash = compiled.hash.slice(0, 10);
    const files =
      compiled.deliveryMode === 'inline'
        ? [`base-${shortHash}.css`, `post-42-${shortHash}.css (درون‌خطی)`]
        : [`merged-${shortHash}.css`];

    return {
      files,
      deliveryMode: compiled.deliveryMode,
      totalBytes: compiled.bytes,
      sizeKb: compiled.sizeKb,
      classCount: classes.length,
      driverId,
      driverLabel,
      buildTimeMs,
      cacheStatus,
    };
  }, [sampleMarkup, extraClasses, settings, tokens, hostPreset, cacheStatus]);

  const fmt = (val: string | number) =>
    twbLocalizeDigits(val, settings.enablePersianDigits);

  return (
    <div className="min-h-screen flex flex-col bg-slate-50 text-slate-900">
      {/* Administrator Front-End / Admin Bar Debug Panel */}
      <WpAdminDebugBar
        metrics={debugMetrics}
        enablePersianDigits={settings.enablePersianDigits}
        onNavigateSettings={() => setActiveTab('settings')}
        onRebuildCache={() => setCacheStatus('MISS')}
      />

      {/* Top Bar Contract: 3 Zones separated by gap-8 */}
      <header className="bg-white border-b border-slate-200">
        <div className="max-w-[1440px] mx-auto px-6 py-4 flex items-center justify-between gap-8">
          {/* Zone 1: Single text wordmark */}
          <a
            href="#top"
            onClick={(e) => {
              e.preventDefault();
              setActiveTab('patterns_phase3');
            }}
            className="text-lg font-bold tracking-tight text-slate-900 whitespace-nowrap shrink-0"
          >
            TW Blocks — نسخهٔ کامل (فاز ۱، ۲ و ۳)
          </a>

          {/* Zone 2: 4 concise single-line navigation links */}
          <nav
            aria-label="ناوبری اصلی"
            className="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600"
          >
            <button
              type="button"
              onClick={() => setActiveTab('patterns_phase3')}
              className={`hover:text-slate-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 border-b-2 ${
                activeTab === 'patterns_phase3'
                  ? 'border-teal-600 text-slate-900 font-semibold'
                  : 'border-transparent'
              }`}
            >
              الگوها و برون‌ریزی (فاز ۳)
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('blocks_phase2')}
              className={`hover:text-slate-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 border-b-2 ${
                activeTab === 'blocks_phase2'
                  ? 'border-teal-600 text-slate-900 font-semibold'
                  : 'border-transparent'
              }`}
            >
              بلوک‌ها و امنیت (فاز ۲)
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('settings')}
              className={`hover:text-slate-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 border-b-2 ${
                activeTab === 'settings'
                  ? 'border-teal-600 text-slate-900 font-semibold'
                  : 'border-transparent'
              }`}
            >
              تنظیمات و موتور CSS (فاز ۱)
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('source_files')}
              className={`hover:text-slate-900 transition-colors whitespace-nowrap shrink-0 cursor-pointer pb-1 border-b-2 ${
                activeTab === 'source_files'
                  ? 'border-teal-600 text-slate-900 font-semibold'
                  : 'border-transparent'
              }`}
            >
              سورس کامل فایل‌ها ({fmt(pluginFiles.length || 60)})
            </button>
          </nav>

          {/* Zone 3: 1 Primary Action */}
          <div className="flex items-center gap-3 shrink-0">
            <button
              type="button"
              onClick={() => setActiveTab('source_files')}
              className="px-4 py-2 text-xs font-semibold text-white bg-teal-700 rounded-lg hover:bg-teal-600 transition-colors whitespace-nowrap shrink-0 cursor-pointer"
            >
              مشاهدهٔ تمام فایل‌های افزونه
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Container */}
      <main className="max-w-[1440px] w-full mx-auto px-6 py-6 flex-1 space-y-6">
        {/* Workspace Toolbar & Architecture Summary Strip */}
        <div className="bg-white border border-slate-200 rounded-lg p-5 flex flex-wrap items-center justify-between gap-4">
          <div className="space-y-1">
            <h1 className="text-xl font-bold text-slate-900">
              افزونهٔ وردپرس TW Blocks — تحویل نهایی (فاز ۱، ۲ و ۳): الگوهای فارسی، برون‌ریزی JSON و WP-CLI
            </h1>
            <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
              <span>۶ الگوی آمادهٔ فارسی (RTL)</span>
              <span aria-hidden="true">·</span>
              <span>۱۴ بلوک Block API v3</span>
              <span aria-hidden="true">·</span>
              <span>Export / Import ایمن</span>
              <span aria-hidden="true">·</span>
              <span>دستورات WP-CLI</span>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* Secondary Segmented Switcher */}
            <div className="flex flex-wrap items-center gap-1 p-1 bg-slate-100 rounded-lg">
              <button
                type="button"
                onClick={() => setActiveTab('patterns_phase3')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'patterns_phase3'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <LayoutTemplate className="w-3.5 h-3.5" />
                <span>الگوها (فاز ۳)</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('blocks_phase2')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'blocks_phase2'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Blocks className="w-3.5 h-3.5" />
                <span>بلوک‌ها (فاز ۲)</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('settings')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'settings'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Settings className="w-3.5 h-3.5" />
                <span>تنظیمات</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('css_lab')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'css_lab'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Cpu className="w-3.5 h-3.5" />
                <span>موتور CSS</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('persian_lab')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'persian_lab'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <Languages className="w-3.5 h-3.5" />
                <span>فارسی و شمسی</span>
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('source_files')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors cursor-pointer whitespace-nowrap ${
                  activeTab === 'source_files'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <FolderCode className="w-3.5 h-3.5" />
                <span>سورس کامل</span>
              </button>
            </div>

            {/* RTL / LTR Live Direction Test Switch */}
            <button
              type="button"
              onClick={() => setDirMode((d) => (d === 'rtl' ? 'ltr' : 'rtl'))}
              className="flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 transition-colors cursor-pointer whitespace-nowrap"
            >
              <ArrowLeftRight className="w-3.5 h-3.5 text-teal-600" />
              <span>تست جهت صفحه: {dirMode.toUpperCase()}</span>
            </button>
          </div>
        </div>

        {/* Active Tab View */}
        {activeTab === 'patterns_phase3' && (
          <PatternsAndPortabilityTab
            tokens={tokens}
            settings={settings}
            onImportSuccess={(nextTokens, nextSettings) => {
              setTokens(nextTokens);
              setSettings(nextSettings);
              setCacheStatus('MISS');
            }}
          />
        )}

        {activeTab === 'blocks_phase2' && <BlocksAndSecurityTab />}

        {activeTab === 'settings' && (
          <SettingsSimulatorTab
            tokens={tokens}
            settings={settings}
            hostPreset={hostPreset}
            onChangeHostPreset={(preset) => {
              setHostPreset(preset);
              setCacheStatus('MISS');
            }}
            onSaveAll={(newTokens, newSettings) => {
              setTokens(newTokens);
              setSettings(newSettings);
              setCacheStatus('MISS');
            }}
            onPurgeCache={() => setCacheStatus('MISS')}
          />
        )}

        {activeTab === 'css_lab' && (
          <CssEngineLabTab
            tokens={tokens}
            settings={settings}
            sampleMarkup={sampleMarkup}
            onChangeSampleMarkup={(m) => {
              setSampleMarkup(m);
              setCacheStatus('MISS');
            }}
            extraClasses={extraClasses}
            onChangeExtraClasses={(c) => {
              setExtraClasses(c);
              setCacheStatus('MISS');
            }}
          />
        )}

        {activeTab === 'persian_lab' && <PersianLocalizationLabTab />}

        {activeTab === 'source_files' && (
          <SourceFilesExplorerTab
            files={pluginFiles}
            enablePersianDigits={settings.enablePersianDigits}
          />
        )}
      </main>

      {/* Quiet Footer */}
      <footer className="bg-white border-t border-slate-200 mt-12">
        <div className="max-w-[1440px] mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4 text-xs text-slate-500">
          <span>
            افزونهٔ وردپرس TW Blocks — نسخهٔ کامل (فاز ۱، ۲ و ۳: هستهٔ CSS، ۱۴ بلوک گوتنبرگ، ۶ الگوی فارسی، برون‌ریزی JSON و WP-CLI)
          </span>
          <span className="font-mono" dir="ltr">
            namespace TWBlocks\ · Block API v3 · Tailwind CSS v4
          </span>
        </div>
      </footer>
    </div>
  );
}
