<?php
/**
 * Pattern: Persian Customer Testimonials 3-Column Grid (`tw-blocks/testimonial-grid`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( '3-Column Customer Testimonials (نظرات مشتریان)', 'tw-blocks' ),
	'description'   => __( 'Responsive 3-column customer testimonials section styled with Tailwind CSS v4 logical properties.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'testimonials' ),
	'keywords'      => array( 'testimonial', 'reviews', 'customers', 'نظرات', 'مشتریان' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"دیدگاه مدیران فنی و طراحان دربارهٔ «TW Blocks»","textAlign":"text-center","twClasses":"text-2xl font-bold text-slate-900 mb-8","twResponsive":{"md":"text-3xl"}} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":3,"colsLg":3,"gap":"gap-6"} -->
<!-- wp:tw-blocks/card {"badge":"★★★★★ — فروشگاه مد و پوشاک","title":"کاهش حجم CSS از ۴۲۰ کیلوبایت به ۶ کیلوبایت","description":"پس از مهاجرت از صفحه‌ساز قدیمی به TW Blocks، امتیاز موبایل گوگل ما از ۵۴ به ۹۸ رسید و سرعت بارگذاری صفحهٔ محصول دو برابر شد.","linkLabel":"","linkUrl":"","twClasses":"p-6 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- wp:tw-blocks/card {"badge":"★★★★★ — آژانس دیجیتال مارکتینگ","title":"سازگاری بی‌نقص با راست‌به‌چپ و فونت وزیرمتن","description":"تایپوگرافی فارسی، نیم‌فاصله‌ها، ارقام فارسی و تقویم شمسی بدون نیاز به هیچ افزونهٔ جانبی به شکل بومی در همهٔ بلوک‌ها کار می‌کنند.","linkLabel":"","linkUrl":"","twClasses":"p-6 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- wp:tw-blocks/card {"badge":"★★★★★ — تیم توسعهٔ سازمانی","title":"استقرار خودکار با wp twb build --all","description":"دستورات WP-CLI و برون‌ریزی JSON توکن‌ها به ما اجازه داد پایپ‌لاین CI/CD خود را بدون کوچک‌ترین خطای کش راه‌اندازی کنیم.","linkLabel":"","linkUrl":"","twClasses":"p-6 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->',
);
