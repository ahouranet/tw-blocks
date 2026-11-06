import test from 'node:test';
import assert from 'node:assert/strict';
import {
  twbLocalizeDigits,
  twbNormalizeDigits,
  gregorianToJalali,
  formatJalaliDate,
  sanitizeAsciiId,
  isValidTailwindClass,
  extractAndValidateClasses,
  compileCssBundle,
  getDefaultDesignTokens,
  sanitizeContainerTag,
  sanitizeHeadingTag,
  sanitizeInlineCss,
  sanitizeSvg,
  hasValidWoff2MagicBytes,
  sanitizeRemoteEndpointUrl,
  buildBlockClassString,
  generateFaqSchemaJsonLd,
  createExportBundle,
  validateAndImportBundle,
  sanitizeBuildInput,
  sanitizeBlockContent,
  sanitizeBlockAttributes,
} from './twBlocksEngine';

test('twbLocalizeDigits converts Latin and Arabic digits to Persian without corrupting HTML attributes', () => {
  const html = '<div class="grid-cols-3 p-4" data-id="123">قیمت: 2500 تومان و ٤٥ ریال</div>';
  const localized = twbLocalizeDigits(html, true);
  assert.equal(localized, '<div class="grid-cols-3 p-4" data-id="123">قیمت: ۲۵۰۰ تومان و ۴۵ ریال</div>');
});

test('twbNormalizeDigits converts Persian and Arabic digits to ASCII for saving settings', () => {
  assert.equal(twbNormalizeDigits('۱۲٫۵'), '12.5');
  assert.equal(twbNormalizeDigits('٠٩١٢٣٤٥٦٧٨٩'), '09123456789');
});

test('gregorianToJalali and formatJalaliDate accurately convert Gregorian dates to Shamsi', () => {
  const [jy, jm, jd] = gregorianToJalali(2026, 3, 21);
  assert.equal(jy, 1405);
  assert.equal(jm, 1);
  assert.equal(jd, 1);

  const formatted = formatJalaliDate('2026-03-21', 'j F Y', true);
  assert.equal(formatted, '۱ فروردین ۱۴۰۵');
});

test('sanitizeAsciiId generates deterministic ASCII-only IDs for Persian headings', () => {
  const id1 = sanitizeAsciiId('سوالات متداول درباره افزونه', 'twb-faq-');
  const id2 = sanitizeAsciiId('سوالات متداول درباره افزونه', 'twb-faq-');
  assert.equal(id1, id2);
  assert.match(id1, /^twb-faq-[a-z0-9-]+$/);
});

test('ClassExtractor whitelist validates legitimate Tailwind v4 classes and blocks injection attempts', () => {
  assert.equal(isValidTailwindClass('ps-4'), true);
  assert.equal(isValidTailwindClass('md:grid-cols-3'), true);
  assert.equal(isValidTailwindClass('rtl:-scale-x-100'), true);
  assert.equal(isValidTailwindClass('bg-primary/90'), true);
  assert.equal(isValidTailwindClass('w-[320px]'), true);

  // Malicious or invalid inputs
  assert.equal(isValidTailwindClass('bad";color:red;'), false);
  assert.equal(isValidTailwindClass('<script>'), false);
  assert.equal(isValidTailwindClass('https://evil.com'), false);
});

test('extractAndValidateClasses deduplicates, sorts, and flags physical direction classes', () => {
  const sampleHtml = `
    <!-- wp:tw-blocks/section {"className":"py-12 ps-6 pe-6","twClasses":"bg-surface text-start"} -->
    <section class="twb-root py-12 ps-6 pe-6 bg-surface rounded-lg rounded-l-lg text-start ml-4">محتوای آزمایشی</section>
    <!-- /wp:tw-blocks/section -->
  `;
  const { classes, rejected } = extractAndValidateClasses(sampleHtml, ['gap-6', 'bad;inject']);
  assert.deepEqual(rejected, ['bad;inject']);
  assert.ok(classes.includes('ps-6'));
  assert.ok(classes.includes('pe-6'));
  assert.ok(classes.includes('gap-6'));

  const tokens = getDefaultDesignTokens();
  const bundle = compileCssBundle(classes, tokens, 12);
  assert.equal(bundle.deliveryMode, 'inline');
  assert.deepEqual(bundle.physicalDirectionWarnings, ['ml-4', 'rounded-l-lg']);
  assert.ok(bundle.minifiedCss.includes('--color-primary:#0d9488'));
  assert.ok(bundle.minifiedCss.includes('padding-inline-start:1.5rem'));
});

test('Phase 2 SecurityHelper: tag whitelists, style breakout prevention, SVG XSS sanitizer, WOFF2 magic bytes, and SSRF blocker', () => {
  assert.equal(sanitizeContainerTag('section'), 'section');
  assert.equal(sanitizeContainerTag('script onload=alert(1)'), 'section');
  assert.equal(sanitizeHeadingTag('h3'), 'h3');
  assert.equal(sanitizeHeadingTag('iframe'), 'h2');

  const safeCss = sanitizeInlineCss('.box { color: red; }</style><script>alert(1)</script>');
  assert.equal(safeCss.includes('</style>'), false);
  assert.equal(safeCss.includes('<script>'), false);

  const cleanSvg = sanitizeSvg(
    '<svg viewBox="0 0 24 24" onload="alert(1)"><script>evil()</script><path d="M5 12h14"/></svg>'
  );
  assert.equal(cleanSvg.includes('onload'), false);
  assert.equal(cleanSvg.includes('<script>'), false);
  assert.equal(cleanSvg.includes('<path d="M5 12h14"/>'), true);

  // WOFF2 binary header check (`wOF2` = 0x77, 0x4F, 0x46, 0x32)
  assert.equal(hasValidWoff2MagicBytes([0x77, 0x4f, 0x46, 0x32, 0x00]), true);
  assert.equal(hasValidWoff2MagicBytes([0x3c, 0x3f, 0x70, 0x68]), false); // `<?ph` spoofed upload

  // SSRF URL validation for Driver B
  assert.equal(sanitizeRemoteEndpointUrl('https://build.twblocks.ir/api/v1/compile'), 'https://build.twblocks.ir/api/v1/compile');
  assert.equal(sanitizeRemoteEndpointUrl('http://127.0.0.1:8080/compile'), '');
  assert.equal(sanitizeRemoteEndpointUrl('http://169.254.169.254/latest/meta-data/'), '');
  assert.equal(sanitizeRemoteEndpointUrl('http://192.168.1.10/api'), '');
});

test('Phase 2 BlockRegistry: buildBlockClassString and deduplicated UTF-8 FAQPage JSON-LD schema', () => {
  const { classString, rejected } = buildBlockClassString(
    {
      twClasses: 'ps-6 pe-6 rounded-xl',
      twResponsive: { md: 'grid-cols-3', lg: 'gap-8' },
      twStates: { hover: 'shadow-md', rtl: '-scale-x-100' },
      className: 'evil";inject',
    },
    'twb-card bg-white'
  );

  assert.deepEqual(rejected, ['evil";inject']);
  assert.ok(classString.includes('md:grid-cols-3'));
  assert.ok(classString.includes('hover:shadow-md'));
  assert.ok(classString.includes('rtl:-scale-x-100'));

  const schemaJson = generateFaqSchemaJsonLd([
    { question: 'آیا فونت وزیرمتن محلی است؟', answer: 'بله، بدون وابستگی به CDN.' },
    { question: 'آیا فونت وزیرمتن محلی است؟', answer: 'تکراری که باید حذف شود.' },
  ]);
  assert.ok(schemaJson.includes('آیا فونت وزیرمتن محلی است؟'));
  const parsed = JSON.parse(schemaJson);
  assert.equal(parsed['@type'], 'FAQPage');
  assert.equal(parsed.mainEntity.length, 1);
});

test('Phase 3 PortabilityManager: strips remoteApiKey on export and sanitizes SSRF/safelist on import', () => {
  const tokens = getDefaultDesignTokens();
  const settings = {
    preferredDriver: 'auto' as const,
    remoteApiUrl: 'https://build.twblocks.ir/api/v1/compile',
    remoteApiKey: 'SUPER_SECRET_HMAC_KEY_DO_NOT_EXPORT',
    remoteApiTimeout: 8,
    inlineThresholdKb: 12,
    safelist: 'ps-4 pe-4 text-start',
    enablePersianDigits: true,
    enableJalaliDate: true,
    disableWpGlobalStyles: false,
    disableCoreBlockLibrary: false,
  };

  const exportedJson = createExportBundle(tokens, settings, 6);
  assert.equal(exportedJson.includes('SUPER_SECRET_HMAC_KEY_DO_NOT_EXPORT'), false);

  // Tamper with imported JSON: inject SSRF URL, malicious safelist class, and attempt to overwrite secret
  const tamperedPayload = JSON.stringify({
    plugin: 'tw-blocks',
    schema_version: '1.0.0',
    tokens: {
      colors: {
        primary: '#0284c7',
        secondary: 'javascript:alert(1)',
      },
    },
    settings: {
      safelist: 'ms-auto evil";inject gap-6',
      remoteApiUrl: 'http://169.254.169.254/latest/meta-data/',
      remoteApiKey: 'HACKED_KEY',
      inlineThresholdKb: '۱۶',
    },
  });

  const result = validateAndImportBundle(tamperedPayload, tokens, settings);
  assert.equal(result.success, true);
  assert.equal(result.tokens?.colors.primary, '#0284c7');
  assert.equal(result.tokens?.colors.secondary, '#0f172a'); // Rejected invalid hex
  assert.equal(result.settings?.remoteApiUrl, ''); // Blocked SSRF metadata IP
  assert.equal(result.settings?.remoteApiKey, 'SUPER_SECRET_HMAC_KEY_DO_NOT_EXPORT'); // Preserved original secret
  assert.equal(result.settings?.inlineThresholdKb, 16); // Normalized Persian numerals
  assert.deepEqual(result.rejectedClasses, ['evil";inject']);
});

test('TWBlocks\\Security\\Sanitizer: centrally sanitizes CSS build inputs and block attributes/content', () => {
  const cleanedClasses = sanitizeBuildInput('ps-4 pe-4 <script>alert(1)</script> evil;color:red rtl:-scale-x-100');
  assert.deepEqual(cleanedClasses, ['pe-4', 'ps-4', 'rtl:-scale-x-100']);

  const cleanedHtml = sanitizeBlockContent('<p onclick="evil()">متن <strong>فارسی</strong> امن</p><script>alert(1)</script>');
  assert.equal(cleanedHtml.includes('<script>'), false);
  assert.equal(cleanedHtml.includes('onclick'), false);
  assert.equal(cleanedHtml.includes('<strong>فارسی</strong>'), true);

  const cleanedAttrs = sanitizeBlockAttributes({
    label: 'شروع رایگان <script>evil()</script>\n',
    content: '<p>متن <strong>فارسی</strong> امن</p><script>alert(1)</script>',
    twClasses: 'px-6 py-3 <img src=x onerror=alert(1)>',
  });
  assert.equal(cleanedAttrs.label, 'شروع رایگان evil()');
  assert.equal(String(cleanedAttrs.content).includes('<script>'), false);
  assert.equal(String(cleanedAttrs.content).includes('<strong>فارسی</strong>'), true);
});



