<?php
/**
 * Pattern: 3-Column Persian Features Grid (`tw-blocks/features-grid`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( '3-Column Persian Features Grid', 'tw-blocks' ),
	'description'   => __( 'Three-column responsive feature showcase built with semantic Section, Grid, and sanitized SVG Icon Box blocks.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'services' ),
	'keywords'      => array( 'features', 'services', 'grid', 'icon-box' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"چرا توسعه‌دهندگان حرفه‌ای وردپرس «TW Blocks» را انتخاب می‌کنند؟","textAlign":"text-start","twClasses":"text-2xl font-bold text-slate-900 mb-3","twResponsive":{"md":"text-3xl"}} /-->
<!-- wp:tw-blocks/text {"content":"تمامی بلوک‌ها با استاندارد Block API نسخهٔ ۳ گوتنبرگ و ویژگی‌های منطقی راست‌به‌چپ (Logical Properties) مهندسی شده‌اند.","textAlign":"text-start","twClasses":"text-base leading-relaxed text-slate-600 mb-8"} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":2,"colsLg":3,"gap":"gap-6","alignItems":"items-stretch"} -->
<!-- wp:tw-blocks/icon-box {"iconName":"bolt","title":"عملکرد بدون جی‌کوئری","description":"هیچ کتابخانهٔ سنگینی در فرانت‌اند بارگذاری نمی‌شود؛ آکاردئون با HTML5 بومی و تب‌ها با اسکریپت شرطی زیر ۱ کیلوبایت کار می‌کنند."} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"shield","title":"امنیت چندلایه در سطح هسته","description":"لیست سفید Regex برای کلاس‌های تیلویند، بررسی بایت جادویی فایل فونت WOFF2 و محافظت کامل در برابر XSS و SSRF."} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"sparkles","title":"تقویم شمسی و ارقام فارسی","description":"تبدیل خودکار تاریخ نوشته‌ها به هجری شمسی و نمایش ارقام فارسی (۰ تا ۹) بدون تخریب ویژگی‌های HTML."} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->',
);
