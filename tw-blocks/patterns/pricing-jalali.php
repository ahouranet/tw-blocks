<?php
/**
 * Pattern: Toman Pricing Plans Table (`tw-blocks/pricing-jalali`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'Persian Pricing Plans (Toman)', 'tw-blocks' ),
	'description'   => __( 'Three-tier Persian subscription pricing table with localized numerals, highlighted middle tier, and logical borders.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'call-to-action' ),
	'keywords'      => array( 'pricing', 'plans', 'toman', 'cards' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"تعرفهٔ شفاف پلن‌های اشتراک سالانه","textAlign":"text-center","twClasses":"text-2xl font-bold text-slate-900 mb-3","twResponsive":{"md":"text-3xl"}} /-->
<!-- wp:tw-blocks/text {"content":"تمامی پلن‌ها شامل به‌روزرسانی رایگان، فونت متغیر وزیرمتن و پشتیبانی از هاست‌های اشتراکی ایران هستند.","textAlign":"text-center","twClasses":"text-base leading-relaxed text-slate-600 mb-8"} /-->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":3,"colsLg":3,"gap":"gap-6","alignItems":"items-stretch"} -->
<!-- wp:tw-blocks/card {"badge":"پلن استارتر (۱ سایت)","title":"۹۸۰٬۰۰۰ تومان / سالانه","description":"مناسب برای وبلاگ‌های تخصصی و سایت‌های شخصی با دسترسی کامل به ۱۴ بلوک پایه و فونت محلی وزیرمتن.","linkLabel":"انتخاب پلن استارتر","linkUrl":"#plan-starter","twClasses":"rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"} /-->
<!-- wp:tw-blocks/card {"badge":"پرفروش‌ترین — پلن آژانسی (۱۰ سایت)","title":"۲٬۴۵۰٬۰۰۰ تومان / سالانه","description":"مناسب برای طراحان سایت و آژانس‌های دیجیتال؛ شامل سرور بیلد اختصاصی Driver B، الگوهای آماده و خروجی/ورودی JSON.","linkLabel":"سفارش پلن آژانسی","linkUrl":"#plan-agency","twClasses":"rounded-2xl border-2 border-primary bg-white p-6 shadow-md"} /-->
<!-- wp:tw-blocks/card {"badge":"پلن سازمانی (نامحدود)","title":"۴٬۹۰۰٬۰۰۰ تومان / سالانه","description":"مناسب برای سازمان‌ها و پورتال‌های پرترافیک؛ شامل دستورات WP-CLI، باینری محلی Driver A و پشتیبانی ویژه.","linkLabel":"تماس با واحد فروش","linkUrl":"#plan-enterprise","twClasses":"rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->',
);
