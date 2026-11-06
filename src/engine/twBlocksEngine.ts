/**
 * Pure TypeScript Engine mirroring TW Blocks Phase 1 PHP & Node Architecture.
 * Decoupled for deterministic unit testing (`node:test`) and interactive live simulation.
 */

export interface DesignTokens {
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    surface: string;
    foreground: string;
    muted: string;
    border: string;
    success: string;
    warning: string;
    danger: string;
  };
  typography: {
    fontFamilyBase: string;
    fontFamilyHeading: string;
    fontFamilyMono: string;
    customWoff2Url: string;
    customFontName: string;
    preloadMainFont: boolean;
    lineHeightBody: string;
    lineHeightHeading: string;
    letterSpacingFa: '0em';
    baseFontSize: string;
  };
  layout: {
    containerWidth: string;
    contentWidth: string;
    spacingUnit: string;
    radiusSm: string;
    radiusMd: string;
    radiusLg: string;
    radiusXl: string;
  };
  breakpoints: {
    sm: string;
    md: string;
    lg: string;
    xl: string;
    '2xl': string;
  };
  preflight: {
    enabled: boolean;
    scopedWrapper: '.twb-root';
  };
}

export interface PluginSettings {
  preferredDriver: 'auto' | 'local_binary' | 'remote_api' | 'fallback_safelist';
  remoteApiUrl: string;
  remoteApiKey: string;
  remoteApiTimeout: number;
  inlineThresholdKb: number;
  safelist: string;
  enablePersianDigits: boolean;
  enableJalaliDate: boolean;
  disableWpGlobalStyles: boolean;
  disableCoreBlockLibrary: boolean;
}

const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

export const JALALI_MONTHS: Record<number, string> = {
  1: 'فروردین',
  2: 'اردیبهشت',
  3: 'خرداد',
  4: 'تیر',
  5: 'مرداد',
  6: 'شهریور',
  7: 'مهر',
  8: 'آبان',
  9: 'آذر',
  10: 'دی',
  11: 'بهمن',
  12: 'اسفند',
};

/**
 * Strict whitelist regex for valid Tailwind CSS v4 class tokens (matches PHP ClassExtractor::CLASS_REGEX).
 */
export const CLASS_WHITELIST_REGEX =
  /^!?-?[a-zA-Z0-9_]+(?:[:\-][a-zA-Z0-9_\-\.\/%\[\]#,\(\)\+\*]+)*$/;

/**
 * Convert Latin and Arabic-Indic digits to Persian digits (۰-۹) without corrupting HTML tags, attributes, or code/script blocks.
 */
export function twbLocalizeDigits(input: string | number, enabled = true): string {
  const text = String(input);
  if (!enabled || text === '') {
    return text;
  }

  const parts = text.split(/(<[^>]+>)/g);
  let inCodeOrScript = false;
  let result = '';

  for (const part of parts) {
    if (part.startsWith('<')) {
      if (/^<(script|style|code|pre)\b/i.test(part)) {
        inCodeOrScript = true;
      } else if (/^<\/(script|style|code|pre)>/i.test(part)) {
        inCodeOrScript = false;
      }
      result += part;
    } else if (inCodeOrScript) {
      result += part;
    } else {
      let chunk = part;
      for (let i = 0; i < 10; i++) {
        chunk = chunk.replaceAll(LATIN_DIGITS[i], PERSIAN_DIGITS[i]);
        chunk = chunk.replaceAll(ARABIC_DIGITS[i], PERSIAN_DIGITS[i]);
      }
      result += chunk;
    }
  }

  return result;
}

/**
 * Normalize Persian (۰-۹) and Arabic (٠-٩) digits to ASCII Latin digits (0-9) for saving settings.
 */
export function twbNormalizeDigits(input: string | number): string {
  let normalized = String(input);
  for (let i = 0; i < 10; i++) {
    normalized = normalized.replaceAll(PERSIAN_DIGITS[i], LATIN_DIGITS[i]);
    normalized = normalized.replaceAll(ARABIC_DIGITS[i], LATIN_DIGITS[i]);
  }
  normalized = normalized.replace(/[٫،]/g, '.');
  return normalized.trim();
}

/**
 * Lightweight, dependency-free Gregorian to Jalali (Hijri Shamsi) calendar converter.
 */
export function gregorianToJalali(gy: number, gm: number, gd: number): [number, number, number] {
  const gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  const gy2 = gm > 2 ? gy + 1 : gy;
  let days =
    355666 +
    365 * gy +
    Math.trunc((gy2 + 3) / 4) -
    Math.trunc((gy2 + 99) / 100) +
    Math.trunc((gy2 + 399) / 400) +
    gd +
    (gDaysInMonth[gm - 1] ?? 0);

  let jy = -1595 + 33 * Math.trunc(days / 12053);
  days %= 12053;
  jy += 4 * Math.trunc(days / 1461);
  days %= 1461;

  if (days > 365) {
    jy += Math.trunc((days - 1) / 365);
    days = (days - 1) % 365;
  }

  const jm = days < 186 ? 1 + Math.trunc(days / 31) : 7 + Math.trunc((days - 186) / 30);
  const jd = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);

  return [jy, jm, jd];
}

/**
 * Format a Gregorian YYYY-MM-DD date string into Jalali Persian string.
 */
export function formatJalaliDate(
  isoDate: string,
  format: 'j F Y' | 'Y/m/d' = 'j F Y',
  localize = true
): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(isoDate.trim());
  const gy = match ? Number(match[1]) : 2026;
  const gm = match ? Number(match[2]) : 11;
  const gd = match ? Number(match[3]) : 6;

  const [jy, jm, jd] = gregorianToJalali(gy, gm, gd);
  const monthName = JALALI_MONTHS[jm] || '';

  const raw =
    format === 'Y/m/d'
      ? `${jy}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`
      : `${jd} ${monthName} ${jy}`;

  return twbLocalizeDigits(raw, localize);
}

/**
 * Deterministic fast FNV-1a / hex hash helper for browser & Node environments.
 */
export function computeDeterministicHash(payload: string): string {
  let h1 = 0xdeadbeef ^ payload.length;
  let h2 = 0x41c6ce57 ^ payload.length;
  for (let i = 0, ch; i < payload.length; i++) {
    ch = payload.charCodeAt(i);
    h1 = Math.imul(h1 ^ ch, 2654435761);
    h2 = Math.imul(h2 ^ ch, 1597334677);
  }
  h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
  h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);
  const hex1 = (h1 >>> 0).toString(16).padStart(8, '0');
  const hex2 = (h2 >>> 0).toString(16).padStart(8, '0');
  return `${hex1}${hex2}`;
}

/**
 * Sanitize Persian/arbitrary text into a strictly ASCII CSS ID or slug.
 */
export function sanitizeAsciiId(input: string, prefix = 'twb-'): string {
  const normalized = twbNormalizeDigits(input.trim());
  const asciiOnly = normalized
    .toLowerCase()
    .replace(/[^a-z0-9\-_]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');

  if (asciiOnly.length >= 3 && asciiOnly === normalized.toLowerCase()) {
    return `${prefix}${asciiOnly}`;
  }

  const shortHash = computeDeterministicHash(normalized).slice(0, 8);
  if (asciiOnly.length > 0) {
    return `${prefix}${asciiOnly}-${shortHash}`;
  }
  return `${prefix}${shortHash}`;
}

/**
 * Validate a candidate Tailwind class token against the strict regex whitelist.
 */
export function isValidTailwindClass(candidate: string): boolean {
  if (!candidate || candidate.length < 1 || candidate.length > 120) {
    return false;
  }
  if (/^\d+$/.test(candidate) || candidate.includes('://') || candidate.includes('..')) {
    return false;
  }
  return CLASS_WHITELIST_REGEX.test(candidate);
}

/**
 * Extract, normalize, validate, deduplicate, and sort Tailwind classes from HTML markup + class lists.
 */
export function extractAndValidateClasses(
  htmlMarkup: string,
  extraClassLists: string[] = []
): { classes: string[]; rejected: string[] } {
  const rawTokens: string[] = [];

  // Extract from class="..." attributes in HTML
  const classAttrRegex = /\bclass\s*=\s*(["'])(.*?)\1/gis;
  let match: RegExpExecArray | null;
  while ((match = classAttrRegex.exec(htmlMarkup)) !== null) {
    const tokens = match[2].trim().split(/\s+/).filter(Boolean);
    rawTokens.push(...tokens);
  }

  // Extract from Gutenberg comment JSON attributes (`"className":"..."`, `"twClasses":"..."`)
  const blockJsonRegex = /"(?:className|twClasses)"\s*:\s*"([^"]+)"/g;
  while ((match = blockJsonRegex.exec(htmlMarkup)) !== null) {
    const tokens = match[1].trim().split(/\s+/).filter(Boolean);
    rawTokens.push(...tokens);
  }

  for (const list of extraClassLists) {
    const tokens = list.trim().split(/[\s,]+/).filter(Boolean);
    rawTokens.push(...tokens);
  }

  const validSet = new Set<string>();
  const rejectedSet = new Set<string>();

  for (const token of rawTokens) {
    if (isValidTailwindClass(token)) {
      validSet.add(token);
    } else if (token.trim() !== '') {
      rejectedSet.add(token);
    }
  }

  const classes = Array.from(validSet).sort();
  const rejected = Array.from(rejectedSet);

  return { classes, rejected };
}

/**
 * Generate Tailwind v4 `@theme` CSS + WordPress `theme.json` `--wp--preset--*` bridge + Vazirmatn `@font-face`.
 */
export function generateThemeAndFontCss(tokens: DesignTokens): string {
  const lines: string[] = [
    '/* Self-hosted Vazirmatn Variable Font (Zero CDN, Persian/Arabic & Latin unicode-range) */',
    '@font-face {',
    "  font-family: 'Vazirmatn';",
    "  src: url('/wp-content/plugins/tw-blocks/assets/fonts/Vazirmatn-Variable.woff2') format('woff2');",
    '  font-weight: 100 900;',
    '  font-style: normal;',
    '  font-display: swap;',
    '  unicode-range: U+0600-06FF, U+200C-200E, U+FB50-FDFF, U+FE70-FEFF, U+0000-00FF;',
    '}',
    '',
    '@theme {',
  ];

  for (const [key, val] of Object.entries(tokens.colors)) {
    lines.push(`  --color-${key}: ${val};`);
  }
  lines.push(`  --font-sans: ${tokens.typography.fontFamilyBase};`);
  lines.push(`  --font-heading: ${tokens.typography.fontFamilyHeading};`);
  lines.push(`  --spacing: ${tokens.layout.spacingUnit};`);
  lines.push(`  --radius-sm: ${tokens.layout.radiusSm};`);
  lines.push(`  --radius-md: ${tokens.layout.radiusMd};`);
  lines.push(`  --radius-lg: ${tokens.layout.radiusLg};`);
  lines.push(`  --radius-xl: ${tokens.layout.radiusXl};`);
  lines.push(`  --container-main: ${tokens.layout.containerWidth};`);
  for (const [bp, val] of Object.entries(tokens.breakpoints)) {
    lines.push(`  --breakpoint-${bp}: ${val};`);
  }
  lines.push('}', '', ':root {');

  for (const [key, val] of Object.entries(tokens.colors)) {
    lines.push(`  --twb-color-${key}: ${val};`);
    lines.push(`  --wp--preset--color--${key}: var(--twb-color-${key});`);
  }
  lines.push(`  --twb-font-sans: ${tokens.typography.fontFamilyBase};`);
  lines.push(`  --wp--preset--font-family--vazirmatn: var(--twb-font-sans);`);
  lines.push(`  --twb-line-height-body: ${tokens.typography.lineHeightBody};`);
  lines.push('  --twb-letter-spacing-fa: 0em;');
  lines.push('}');

  return lines.join('\n');
}

/**
 * Compile extracted classes + tokens into minified CSS and compute delivery metrics (`inline` < threshold vs `file`).
 */
export function compileCssBundle(
  classes: string[],
  tokens: DesignTokens,
  inlineThresholdKb = 12
): {
  hash: string;
  minifiedCss: string;
  bytes: number;
  sizeKb: number;
  deliveryMode: 'inline' | 'file';
  physicalDirectionWarnings: string[];
} {
  const themeCss = generateThemeAndFontCss(tokens);
  const physicalDirectionWarnings: string[] = [];

  const utilityRules: string[] = [];
  const spacingScale: Record<string, string> = {
    '0': '0px',
    '1': '0.25rem',
    '2': '0.5rem',
    '3': '0.75rem',
    '4': '1rem',
    '5': '1.25rem',
    '6': '1.5rem',
    '8': '2rem',
    '10': '2.5rem',
    '12': '3rem',
    '16': '4rem',
  };

  for (const cls of classes) {
    if (
      /^(ml-|mr-|pl-|pr-|left-|right-|text-left$|text-right$|border-l(?:$|-)|border-r(?:$|-)|rounded-l(?:$|-)|rounded-r(?:$|-))/.test(
        cls
      )
    ) {
      physicalDirectionWarnings.push(cls);
    }

    const escaped = cls.replace(/([^a-zA-Z0-9_-])/g, '\\$1');

    if (cls === 'flex') utilityRules.push('.flex{display:flex}');
    else if (cls === 'grid') utilityRules.push('.grid{display:grid}');
    else if (cls === 'items-center') utilityRules.push('.items-center{align-items:center}');
    else if (cls === 'justify-between') utilityRules.push('.justify-between{justify-content:space-between}');
    else if (cls === 'text-start') utilityRules.push('.text-start{text-align:start}');
    else if (cls === 'text-end') utilityRules.push('.text-end{text-align:end}');
    else if (cls === 'ms-auto') utilityRules.push('.ms-auto{margin-inline-start:auto}');
    else if (cls === 'me-auto') utilityRules.push('.me-auto{margin-inline-end:auto}');
    else if (cls === 'rtl:-scale-x-100') utilityRules.push('[dir="rtl"] .rtl\\:-scale-x-100{transform:scaleX(-1)}');
    else if (/^(m|p)(s|e|x|y|t|b)?-(\d+)$/.test(cls)) {
      const m = /^(m|p)(s|e|x|y|t|b)?-(\d+)$/.exec(cls)!;
      const prop = m[1] === 'm' ? 'margin' : 'padding';
      const dir = m[2] || '';
      const val = spacingScale[m[3]] || `${Number(m[3]) * 0.25}rem`;
      if (dir === 's') utilityRules.push(`.${escaped}{${prop}-inline-start:${val}}`);
      else if (dir === 'e') utilityRules.push(`.${escaped}{${prop}-inline-end:${val}}`);
      else if (dir === 'x') utilityRules.push(`.${escaped}{${prop}-inline:${val}}`);
      else if (dir === 'y') utilityRules.push(`.${escaped}{${prop}-block:${val}}`);
      else utilityRules.push(`.${escaped}{${prop}:${val}}`);
    } else if (/^gap-(\d+)$/.test(cls)) {
      const n = /^gap-(\d+)$/.exec(cls)![1];
      const val = spacingScale[n] || `${Number(n) * 0.25}rem`;
      utilityRules.push(`.${escaped}{gap:${val}}`);
    } else {
      utilityRules.push(`.${escaped}{/* tw-v4:${cls} */}`);
    }
  }

  const preflight = tokens.preflight.enabled
    ? `.twb-root,.twb-root *,.twb-root *::before,.twb-root *::after{box-sizing:border-box;border-width:0;border-style:solid;border-color:var(--twb-color-border)}.twb-root{font-family:var(--twb-font-sans);line-height:var(--twb-line-height-body);letter-spacing:0em}`
    : '';

  const rawCombined = `${themeCss}\n${preflight}\n${utilityRules.join('\n')}`;
  const minifiedCss = rawCombined
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([\{\}:;,])\s*/g, '$1')
    .replace(/;\}/g, '}')
    .trim();

  const bytes = new TextEncoder().encode(minifiedCss).length;
  const sizeKb = Number((bytes / 1024).toFixed(2));
  const hash = computeDeterministicHash(`${minifiedCss}|${classes.join(' ')}`);
  const deliveryMode = bytes <= inlineThresholdKb * 1024 ? 'inline' : 'file';

  return {
    hash,
    minifiedCss,
    bytes,
    sizeKb,
    deliveryMode,
    physicalDirectionWarnings,
  };
}

export function getDefaultDesignTokens(): DesignTokens {
  return {
    colors: {
      primary: '#0d9488',
      secondary: '#0f172a',
      accent: '#0284c7',
      background: '#ffffff',
      surface: '#f8fafc',
      foreground: '#0f172a',
      muted: '#475569',
      border: '#e2e8f0',
      success: '#16a34a',
      warning: '#d97706',
      danger: '#dc2626',
    },
    typography: {
      fontFamilyBase: 'Vazirmatn, Tahoma, system-ui, sans-serif',
      fontFamilyHeading: 'Vazirmatn, Tahoma, system-ui, sans-serif',
      fontFamilyMono: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
      customWoff2Url: '',
      customFontName: '',
      preloadMainFont: true,
      lineHeightBody: '1.8',
      lineHeightHeading: '1.4',
      letterSpacingFa: '0em',
      baseFontSize: '16px',
    },
    layout: {
      containerWidth: '1280px',
      contentWidth: '768px',
      spacingUnit: '0.25rem',
      radiusSm: '0.375rem',
      radiusMd: '0.5rem',
      radiusLg: '0.75rem',
      radiusXl: '1rem',
    },
    breakpoints: {
      sm: '640px',
      md: '768px',
      lg: '1024px',
      xl: '1280px',
      '2xl': '1536px',
    },
    preflight: {
      enabled: true,
      scopedWrapper: '.twb-root',
    },
  };
}

/**
 * Phase 2 Security Helper functions mirroring `TWBlocks\Security\SecurityHelper`.
 */
const ALLOWED_CONTAINER_TAGS = new Set([
  'section',
  'div',
  'article',
  'aside',
  'header',
  'footer',
  'main',
  'nav',
]);

const ALLOWED_HEADING_TAGS = new Set(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);

export function sanitizeContainerTag(tag: string, fallback = 'section'): string {
  const clean = tag.trim().toLowerCase();
  return ALLOWED_CONTAINER_TAGS.has(clean) ? clean : fallback;
}

export function sanitizeHeadingTag(tag: string, fallback = 'h2'): string {
  const clean = tag.trim().toLowerCase();
  return ALLOWED_HEADING_TAGS.has(clean) ? clean : fallback;
}

/**
 * Prevent `</style>` breakout XSS and legacy CSS script expressions in inline CSS.
 */
export function sanitizeInlineCss(css: string): string {
  if (!css) return '';
  let clean = css.replace(/\0/g, '');
  clean = clean.replace(/<\s*\/?\s*(?:style|script)[^>]*>/gi, '');
  clean = clean.replace(/expression\s*\(|javascript\s*:/gi, '');
  return clean.trim();
}

/**
 * Sanitize inline SVG markup by stripping `<script>`, `<foreignObject>`, `on*` event handlers, and `javascript:` URIs.
 */
export function sanitizeSvg(svg: string): string {
  if (!svg || !svg.includes('<svg')) return '';
  let clean = svg.replace(/<\s*(script|foreignObject|iframe|object|embed)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '');
  clean = clean.replace(/\son[a-z]+\s*=\s*(["']).*?\1/gi, '');
  clean = clean.replace(/\b(?:href|xlink:href)\s*=\s*(["'])\s*javascript:.*?\1/gi, '');
  return clean.trim();
}

/**
 * Verify WOFF2 binary magic signature (`wOF2` = 0x77 0x4F 0x46 0x32).
 */
export function hasValidWoff2MagicBytes(headerBytes: Uint8Array | number[]): boolean {
  if (!headerBytes || headerBytes.length < 4) return false;
  return (
    headerBytes[0] === 0x77 &&
    headerBytes[1] === 0x4f &&
    headerBytes[2] === 0x46 &&
    headerBytes[3] === 0x32
  );
}

/**
 * Validate Driver B remote endpoint URL and block SSRF targets (loopback, private RFC1918, cloud metadata IP).
 */
export function sanitizeRemoteEndpointUrl(url: string): string {
  const raw = url.trim();
  if (!raw) return '';
  try {
    const parsed = new URL(raw);
    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
      return '';
    }
    const host = parsed.hostname.toLowerCase();
    if (
      host === 'localhost' ||
      host === '127.0.0.1' ||
      host === '0.0.0.0' ||
      host === '::1' ||
      host === '169.254.169.254' ||
      host.endsWith('.internal') ||
      host.endsWith('.local') ||
      /^10\./.test(host) ||
      /^192\.168\./.test(host) ||
      /^172\.(1[6-9]|2\d|3[0-1])\./.test(host)
    ) {
      return '';
    }
    return parsed.toString();
  } catch {
    return '';
  }
}

/**
 * Build a validated, deduplicated Tailwind class string from block attributes (`twClasses`, `twResponsive`, `twStates`).
 */
export function buildBlockClassString(
  attributes: {
    twClasses?: string;
    twResponsive?: Record<string, string>;
    twStates?: Record<string, string>;
    className?: string;
  },
  baseClasses = ''
): { classString: string; rejected: string[] } {
  const candidates: string[] = [];
  if (baseClasses.trim()) candidates.push(baseClasses.trim());
  if (attributes.twClasses?.trim()) candidates.push(attributes.twClasses.trim());

  const allowedBps = ['sm', 'md', 'lg', 'xl', '2xl'];
  if (attributes.twResponsive) {
    for (const bp of allowedBps) {
      const val = attributes.twResponsive[bp];
      if (val?.trim()) {
        for (const token of val.trim().split(/\s+/)) {
          candidates.push(token.startsWith(`${bp}:`) ? token : `${bp}:${token}`);
        }
      }
    }
  }

  const allowedStates = ['hover', 'focus', 'dark', 'rtl'];
  if (attributes.twStates) {
    for (const st of allowedStates) {
      const val = attributes.twStates[st];
      if (val?.trim()) {
        for (const token of val.trim().split(/\s+/)) {
          candidates.push(token.startsWith(`${st}:`) ? token : `${st}:${token}`);
        }
      }
    }
  }

  if (attributes.className?.trim()) candidates.push(attributes.className.trim());

  const { classes, rejected } = extractAndValidateClasses('', candidates);
  return { classString: classes.join(' '), rejected };
}

/**
 * Generate deduplicated UTF-8 `FAQPage` JSON-LD structured data without Unicode escaping (`JSON_UNESCAPED_UNICODE`).
 */
export function generateFaqSchemaJsonLd(
  items: Array<{ question: string; answer: string }>
): string {
  const seen = new Set<string>();
  const entities: Array<Record<string, unknown>> = [];

  for (const item of items) {
    const q = item.question.replace(/<[^>]+>/g, '').trim();
    const a = item.answer.replace(/<script[\s\S]*?<\/script>/gi, '').trim();
    if (!q || !a || seen.has(q)) continue;
    seen.add(q);
    entities.push({
      '@type': 'Question',
      name: q,
      acceptedAnswer: {
        '@type': 'Answer',
        text: a,
      },
    });
  }

  return JSON.stringify(
    {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: entities,
    },
    null,
    2
  );
}

/**
 * Phase 3: Portability (Export / Import) JSON Bundle Generator & Security Validator.
 * Strips sensitive `remoteApiKey` on export, enforces a 256 KB size cap on import,
 * validates safelist classes and SSRF URLs, and returns sanitized tokens & settings.
 */
export const MAX_IMPORT_BYTES = 262144;

export function createExportBundle(
  tokens: DesignTokens,
  settings: PluginSettings,
  patternsCount = 6
): string {
  const safeSettings: Partial<PluginSettings> = { ...settings };
  // Never export HMAC secret key in plain text
  delete safeSettings.remoteApiKey;

  return JSON.stringify(
    {
      plugin: 'tw-blocks',
      schema_version: '1.0.0',
      exported_at: '2026-11-06T12:00:00Z',
      site_locale: { lang: 'fa-IR', dir: 'rtl' },
      tokens,
      settings: safeSettings,
      patterns_count: patternsCount,
    },
    null,
    2
  );
}

export function validateAndImportBundle(
  rawJson: string,
  currentTokens: DesignTokens,
  currentSettings: PluginSettings
): {
  success: boolean;
  messageFa: string;
  tokens?: DesignTokens;
  settings?: PluginSettings;
  rejectedClasses?: string[];
} {
  const byteLen = new TextEncoder().encode(rawJson).length;
  if (byteLen > MAX_IMPORT_BYTES) {
    return {
      success: false,
      messageFa: 'حجم فایل ورودی بیش از سقف امنیتی ۲۵۶ کیلوبایت است.',
    };
  }

  let parsed: Record<string, unknown>;
  try {
    parsed = JSON.parse(rawJson);
  } catch {
    return {
      success: false,
      messageFa: 'ساختار JSON معتبر نیست (خطای نحوی در فایل ورودی).',
    };
  }

  if (!parsed || typeof parsed !== 'object' || parsed.plugin !== 'tw-blocks') {
    return {
      success: false,
      messageFa: 'فرمت فایل JSON ورودی متعلق به افزونهٔ TW Blocks نیست.',
    };
  }

  const nextTokens: DesignTokens = structuredClone(currentTokens);
  if (parsed.tokens && typeof parsed.tokens === 'object') {
    const inTokens = parsed.tokens as Partial<DesignTokens>;
    if (inTokens.colors && typeof inTokens.colors === 'object') {
      for (const [k, v] of Object.entries(inTokens.colors)) {
        if (typeof v === 'string' && /^#[0-9a-fA-F]{3,6}$/.test(v.trim()) && k in nextTokens.colors) {
          (nextTokens.colors as Record<string, string>)[k] = v.trim();
        }
      }
    }
  }

  const nextSettings: PluginSettings = { ...currentSettings };
  let rejectedClasses: string[] = [];

  if (parsed.settings && typeof parsed.settings === 'object') {
    const inSettings = parsed.settings as Record<string, unknown>;
    if (typeof inSettings.safelist === 'string') {
      const { classes, rejected } = extractAndValidateClasses('', [inSettings.safelist]);
      nextSettings.safelist = classes.join(' ');
      rejectedClasses = rejected;
    }
    if (typeof inSettings.remoteApiUrl === 'string') {
      nextSettings.remoteApiUrl = sanitizeRemoteEndpointUrl(inSettings.remoteApiUrl);
    }
    if (typeof inSettings.inlineThresholdKb === 'number' || typeof inSettings.inlineThresholdKb === 'string') {
      const normalized = Number(twbNormalizeDigits(String(inSettings.inlineThresholdKb)));
      if (!Number.isNaN(normalized)) {
        nextSettings.inlineThresholdKb = Math.max(0, Math.min(50, normalized));
      }
    }
    // Never overwrite existing secret key from untrusted JSON
    nextSettings.remoteApiKey = currentSettings.remoteApiKey;
  }

  return {
    success: true,
    messageFa: 'توکن‌های طراحی و تنظیمات با موفقیت اعتبارسنجی و درون‌ریزی شدند و کش CSS بازسازی گردید.',
    tokens: nextTokens,
    settings: nextSettings,
    rejectedClasses,
  };
}

/**
 * Centralized Sanitizer (`TWBlocks\Security\Sanitizer`) using `sanitize_text_field` and `wp_kses_post`
 * semantics to sanitize all CSS build inputs and block attributes/content before processing.
 */
const RICH_TEXT_ATTRIBUTE_KEYS = new Set([
  'content',
  'text',
  'description',
  'answer',
  'caption',
  'subtitle',
  'body',
  'excerpt',
]);

export function cleanTextField(text: string): string {
  return text
    .replace(/\0/g, '')
    .replace(/<[^>]*>/g, '')
    .replace(/[\r\n\t ]+/g, ' ')
    .trim();
}

export function sanitizeBuildInput(rawInput: string | string[]): string[] {
  const items = Array.isArray(rawInput) ? rawInput : [rawInput];
  const unique = new Set<string>();

  for (const item of items) {
    if (typeof item !== 'string') continue;
    const cleanedLine = cleanTextField(item);
    if (!cleanedLine) continue;

    for (const token of cleanedLine.split(/[\s,]+/)) {
      const candidate = cleanTextField(token);
      if (candidate && isValidTailwindClass(candidate)) {
        unique.add(candidate);
      }
    }
  }

  return Array.from(unique).sort();
}

export function sanitizeBlockContent(content: string): string {
  if (!content || !content.trim()) return '';
  return content
    .replace(/\0/g, '')
    .replace(/<\s*(script|iframe|object|embed|style|meta|link|base)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(/<\/?\s*(script|iframe|object|embed|style|meta|link|base)\b[^>]*>/gi, '')
    .replace(/\son[a-z]+\s*=\s*(["']).*?\1/gi, '')
    .replace(/\son[a-z]+\s*=\s*[^\s>]+/gi, '');
}

export function sanitizeBlockAttributes(attributes: Record<string, unknown>): Record<string, unknown> {
  const sanitized: Record<string, unknown> = {};

  for (const [rawKey, value] of Object.entries(attributes)) {
    const cleanKey = cleanTextField(rawKey);
    if (!cleanKey) continue;

    if (typeof value === 'string') {
      if (cleanKey === 'svgMarkup') {
        sanitized[cleanKey] = sanitizeSvg(value);
      } else if (RICH_TEXT_ATTRIBUTE_KEYS.has(cleanKey)) {
        sanitized[cleanKey] = sanitizeBlockContent(value);
      } else {
        sanitized[cleanKey] = cleanTextField(value);
      }
    } else if (Array.isArray(value)) {
      sanitized[cleanKey] = value.map((item) =>
        item && typeof item === 'object' && !Array.isArray(item)
          ? sanitizeBlockAttributes(item as Record<string, unknown>)
          : typeof item === 'string'
            ? cleanTextField(item)
            : item
      );
    } else if (value && typeof value === 'object') {
      sanitized[cleanKey] = sanitizeBlockAttributes(value as Record<string, unknown>);
    } else if (typeof value === 'boolean' || typeof value === 'number' || value === null) {
      sanitized[cleanKey] = value;
    }
  }

  return sanitized;
}



