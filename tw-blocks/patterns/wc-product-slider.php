<?php
/**
 * Pattern: WooCommerce Product Slider with CSS Scroll-Snap (`tw-blocks/wc-product-slider`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'WooCommerce Scroll-Snap Product Slider (اسلایدر محصولات)', 'tw-blocks' ),
	'description'   => __( 'Zero-Swiper RTL product carousel using CSS scroll-snap and Persian Toman prices.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'woocommerce' ),
	'keywords'      => array( 'woocommerce', 'slider', 'products', 'carousel', 'اسلایدر محصولات' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-12","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200 space-y-6"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"پیشنهادهای شگفت‌انگیز و پرفروش‌ترین محصولات هفته","textAlign":"text-start","twClasses":"text-2xl font-bold text-slate-900 m-0"} /-->
<!-- wp:tw-blocks/slider {"slidesPerViewMd":2,"slidesPerViewLg":4,"gap":"gap-6","showArrows":true,"ariaLabel":"اسلایدر محصولات شگفت‌انگیز"} -->
<!-- wp:tw-blocks/card {"badge":"۲۵٪ تخفیف ویژه","title":"هدفون بی‌سیم نویزکنسلینگ پرو","description":"۴٬۸۵۰٬۰۰۰ تومان — ارسال رایگان امروز","linkLabel":"مشاهده و خرید","linkUrl":"#product-1","twClasses":"min-w-[260px] snap-start p-5 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- wp:tw-blocks/card {"badge":"پرفروش‌ترین","title":"ساعت هوشمند ورزشی نسخهٔ تیتانیوم","description":"۷٬۲۰۰٬۰۰۰ تومان — گارانتی ۱۸ ماههٔ شرکتی","linkLabel":"مشاهده و خرید","linkUrl":"#product-2","twClasses":"min-w-[260px] snap-start p-5 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- wp:tw-blocks/card {"badge":"۱۵٪ تخفیف","title":"کیبورد مکانیکال بی‌سیم فارسی/انگلیسی","description":"۳٬۴۹۰٬۰۰۰ تومان — سوییچ‌های بی‌صدا","linkLabel":"مشاهده و خرید","linkUrl":"#product-3","twClasses":"min-w-[260px] snap-start p-5 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- wp:tw-blocks/card {"badge":"موجودی محدود","title":"مانیتور ۲۷ اینچ 4K مخصوص طراحی","description":"۱۸٬۹۰۰٬۰۰۰ تومان — پنل IPS کالیبره‌شده","linkLabel":"مشاهده و خرید","linkUrl":"#product-4","twClasses":"min-w-[260px] snap-start p-5 rounded-2xl border border-slate-200 bg-white shadow-xs"} /-->
<!-- /wp:tw-blocks/slider -->
<!-- /wp:tw-blocks/section -->',
);
