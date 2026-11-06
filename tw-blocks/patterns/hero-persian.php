<?php
/**
 * Pattern: Persian Hero Section with Dual CTA & Metrics (`tw-blocks/hero-persian`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'Persian Hero Section with Dual CTA', 'tw-blocks' ),
	'description'   => __( 'RTL-first hero section featuring a badge, H1 Persian headline, lead paragraph, primary/secondary action buttons, and localized performance statistics.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'featured', 'banner' ),
	'keywords'      => array( 'hero', 'banner', 'cta', 'persian', 'rtl' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-24","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200 shadow-sm"} -->
<!-- wp:tw-blocks/heading {"level":"h1","content":"طراحی سایت‌های وردپرسی سریع، سئو‌محور و بومی با «TW Blocks»","textAlign":"text-start","twClasses":"text-3xl font-bold text-slate-900 mb-4","twResponsive":{"md":"text-4xl"}} /-->
<!-- wp:tw-blocks/text {"content":"بدون بارگذاری استایل‌های سنگین و بدون وابستگی به CDN خارجی؛ تنها کلاس‌های واقعاً استفاده‌شده در هر برگه روی سرور کامپایل و به‌صورت استاتیک کش می‌شوند تا امتیاز ۱۰۰ در Core Web Vitals تضمین گردد.","textAlign":"text-start","twClasses":"text-lg leading-relaxed text-slate-600 mb-8 max-w-5xl"} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"flex","gap":"gap-4","alignItems":"items-center","justifyContent":"justify-start","twClasses":"mb-8"} -->
<!-- wp:tw-blocks/button {"label":"شروع ساخت برگه","url":"#get-started","variant":"primary","showArrowIcon":true,"twClasses":"px-6 py-3 rounded-lg font-semibold shadow-sm"} /-->
<!-- wp:tw-blocks/button {"label":"مشاهدهٔ مستندات فنی","url":"#docs","variant":"outline","showArrowIcon":false,"twClasses":"px-6 py-3 rounded-lg font-semibold"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- wp:tw-blocks/divider {"twClasses":"my-8 border-t border-slate-200"} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":3,"colsLg":3,"gap":"gap-6"} -->
<!-- wp:tw-blocks/card {"badge":"حجم خروجی CSS","title":"کمتر از ۸ کیلوبایت در هر برگه","description":"حذف کامل کلاس‌های استفاده‌نشده و ادغام سه سطح Base، Template و Post در یک فایل استاتیک.","linkLabel":"","linkUrl":""} /-->
<!-- wp:tw-blocks/card {"badge":"فونت متغیر محلی","title":"وزیرمتن (WOFF2) بدون CDN","description":"بارگذاری مستقیم از هاست خودتان با font-display: swap و تفکیک بازهٔ یونیکد فارسی و لاتین.","linkLabel":"","linkUrl":""} /-->
<!-- wp:tw-blocks/card {"badge":"سازگاری با هاست ایران","title":"معماری هوشمند ۳ درایوره","description":"سوییچ خودکار بین باینری محلی، سرور بیلد HMAC و فایل آمادهٔ Safelist روی هاست اشتراکی cPanel.","linkLabel":"","linkUrl":""} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->',
);
