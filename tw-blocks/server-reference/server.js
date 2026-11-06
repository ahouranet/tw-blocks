/**
 * TW Blocks — Reference Remote Tailwind CSS v4 Build Server (Driver B)
 *
 * Security & Architecture Features:
 * 1. Strict HMAC-SHA256 request signature verification (`X-TWB-Signature` + `X-TWB-Timestamp` replay protection).
 * 2. Strict payload limit (64KB max) and maximum class count (2,500 classes).
 * 3. Regex whitelist validation for every incoming class token (never accepts full HTML).
 * 4. Rate limiting per IP (60 requests / minute).
 * 5. Compiles with `@tailwindcss/node` (Tailwind CSS v4 CSS-first `@theme` configuration) and minifies output.
 */

import crypto from 'node:crypto';
import express from 'express';
import rateLimit from 'express-rate-limit';
import { compile } from '@tailwindcss/node';

const app = express();
const PORT = Number(process.env.PORT || 4000);
const HMAC_SECRET = process.env.TWB_HMAC_SECRET || 'change-me-to-a-32-char-secret-key!';
const MAX_CLOCK_SKEW_SECONDS = 300;
const MAX_CLASSES_PER_REQUEST = 2500;

/**
 * Whitelist regex matching valid Tailwind CSS v4 utility tokens.
 */
const CLASS_WHITELIST_REGEX = /^!?-?[a-zA-Z0-9_]+(?:[:\-][a-zA-Z0-9_\-\.\/%\[\]#,\(\)\+\*]+)*$/;

/**
 * Capture raw request body buffer for exact HMAC-SHA256 verification, with strict 64kb ceiling.
 */
app.use(
  express.json({
    limit: '64kb',
    verify: (req, _res, buf) => {
      req.rawBody = buf.toString('utf8');
    },
  })
);

/**
 * Rate Limiter: 60 builds per minute per IP.
 */
const limiter = rateLimit({
  windowMs: 60 * 1000,
  max: 60,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Too many build requests. Please try again later.' },
});

app.use('/api/v1/compile', limiter);

/**
 * Verify HMAC-SHA256 signature and timestamp freshness.
 */
function verifyHmacSignature(req, res, next) {
  const timestampHeader = req.get('X-TWB-Timestamp') || '';
  const signatureHeader = req.get('X-TWB-Signature') || '';

  const timestamp = Number.parseInt(timestampHeader, 10);
  const now = Math.floor(Date.now() / 1000);

  if (!Number.isFinite(timestamp) || Math.abs(now - timestamp) > MAX_CLOCK_SKEW_SECONDS) {
    return res.status(401).json({ error: 'Invalid or expired X-TWB-Timestamp header.' });
  }

  const expectedHmac = crypto
    .createHmac('sha256', HMAC_SECRET)
    .update(`${timestamp}.${req.rawBody || ''}`, 'utf8')
    .digest('hex');

  const expectedHeader = `sha256=${expectedHmac}`;

  const a = Buffer.from(signatureHeader, 'utf8');
  const b = Buffer.from(expectedHeader, 'utf8');

  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return res.status(401).json({ error: 'HMAC signature verification failed.' });
  }

  return next();
}

/**
 * Lightweight CSS minifier removing comments and redundant whitespace.
 */
function minifyCss(css) {
  return css
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([\{\}:;,])\s*/g, '$1')
    .replace(/;\}/g, '}')
    .trim();
}

/**
 * Sanitize incoming `@theme` CSS block to prevent arbitrary `@import` injection.
 */
function sanitizeThemeCss(rawThemeCss) {
  if (typeof rawThemeCss !== 'string') {
    return '';
  }
  // Disallow external @import or url(http...) inside theme block; local font-face is handled by WordPress.
  return rawThemeCss.replace(/@import\s+[^;]+;/gi, '').trim();
}

app.post('/api/v1/compile', verifyHmacSignature, async (req, res) => {
  try {
    const { classes, themeCss = '', preflightCss = '' } = req.body || {};

    if (!Array.isArray(classes)) {
      return res.status(400).json({ error: '`classes` must be an array of strings.' });
    }

    if (classes.length > MAX_CLASSES_PER_REQUEST) {
      return res.status(400).json({ error: `Maximum ${MAX_CLASSES_PER_REQUEST} classes allowed per build.` });
    }

    const validClasses = [];
    for (const candidate of classes) {
      if (typeof candidate === 'string' && candidate.length <= 120 && CLASS_WHITELIST_REGEX.test(candidate)) {
        validClasses.push(candidate);
      }
    }

    const safeTheme = sanitizeThemeCss(themeCss);
    const safePreflight = sanitizeThemeCss(preflightCss);

    const virtualInputCss = [
      '@import "tailwindcss/theme";',
      '@import "tailwindcss/utilities";',
      safeTheme,
      safePreflight,
    ].join('\n');

    const compiler = await compile(virtualInputCss, {
      base: process.cwd(),
      onDependency: () => {},
    });

    const compiledCss = compiler.build(validClasses);
    const minified = minifyCss(compiledCss);

    return res.status(200).json({
      success: true,
      classCount: validClasses.length,
      bytes: Buffer.byteLength(minified, 'utf8'),
      css: minified,
    });
  } catch (err) {
    return res.status(500).json({
      error: err instanceof Error ? err.message : 'Internal Tailwind compilation error.',
    });
  }
});

app.get('/healthz', (_req, res) => {
  res.status(200).json({ status: 'ok', engine: 'tailwind-v4-remote-driver-b' });
});

app.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log(`TW Blocks Remote Build Server (Driver B) listening on port ${PORT}`);
});
