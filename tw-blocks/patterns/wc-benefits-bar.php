<?php
/**
 * Pattern: WooCommerce Store Benefits Bar (`tw-blocks/wc-benefits-bar`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'WooCommerce Store Benefits Bar (نوار مزایای فروشگاه)', 'tw-blocks' ),
	'description'   => __( '4-column trust and benefits bar for WooCommerce shop and single product templates.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'woocommerce' ),
	'keywords'      => array( 'woocommerce', 'benefits', 'trust', 'shipping', 'مزایا', 'گارانتی' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-6","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200 shadow-2xs my-6"} -->
<!-- wp:tw-blocks/grid {"layoutMode":"grid","colsBase":1,"colsMd":2,"colsLg":4,"gap":"gap-6"} -->
<!-- wp:tw-blocks/icon-box {"iconName":"bolt","layout":"horizontal","title":"ارسال سریع و اکسپرس","description":"ارسال ۳ ساعته در تهران و پیشتاز به سراسر ایران"} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"shield","layout":"horizontal","title":"ضمانت اصالت و سلامت کالا","description":"گارانتی معتبر ۱۸ ماهه و تضمین ۱۰۰٪ اصالت"} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"check","layout":"horizontal","title":"۷ روز ضمانت بازگشت وجه","description":"مرجوعی آسان و بی‌قیدوشرط در صورت عدم رضایت"} /-->
<!-- wp:tw-blocks/icon-box {"iconName":"sparkles","layout":"horizontal","title":"پرداخت امن شتاب و اقساطی","description":"پشتیبانی از کلیهٔ درگاه‌های بانکی و خرید اقساطی"} /-->
<!-- /wp:tw-blocks/grid -->
<!-- /wp:tw-blocks/section -->',
);
