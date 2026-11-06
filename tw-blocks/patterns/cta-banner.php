<?php
/**
 * Pattern: High-Contrast RTL Call-to-Action Banner (`tw-blocks/cta-banner`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'RTL Call-to-Action Banner', 'tw-blocks' ),
	'description'   => __( 'High-contrast call-to-action banner with Persian typography and mirrored directional button icon.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'call-to-action' ),
	'keywords'      => array( 'cta', 'banner', 'action' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"aside","maxWidth":"max-w-7xl","paddingY":"py-12","paddingX":"px-8","bgVariant":"secondary","twClasses":"rounded-2xl shadow-md"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"آمادهٔ جایگزینی صفحه‌سازهای سنگین با گوتنبرگ و Tailwind CSS v4 هستید؟","textAlign":"text-start","twClasses":"text-2xl font-bold text-white mb-3"} /-->
<!-- wp:tw-blocks/text {"content":"همین حالا توکن‌های رنگ و تایپوگرافی برند خود را تنظیم کنید و اولین برگهٔ فوق‌سریع خود را بسازید.","textAlign":"text-start","twClasses":"text-base leading-relaxed text-slate-200 mb-6"} /-->
<!-- wp:tw-blocks/button {"label":"ورود به تنظیمات توکن‌ها","url":"#settings","variant":"primary","showArrowIcon":true,"twClasses":"px-6 py-3 rounded-lg font-semibold"} /-->
<!-- /wp:tw-blocks/section -->',
);
