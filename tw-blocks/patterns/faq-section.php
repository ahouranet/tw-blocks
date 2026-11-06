<?php
/**
 * Pattern: SEO FAQ Section with JSON-LD Schema (`tw-blocks/faq-section`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'SEO FAQ Section with JSON-LD Schema', 'tw-blocks' ),
	'description'   => __( 'Zero-JS Persian Frequently Asked Questions section with native <details>/<summary> and automatic UTF-8 FAQPage structured data.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'text' ),
	'keywords'      => array( 'faq', 'questions', 'schema', 'seo' ),
	'viewportWidth' => 1024,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-5xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"white","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"سوالات متداول دربارهٔ معماری و سئوی TW Blocks","textAlign":"text-start","twClasses":"text-2xl font-bold text-slate-900 mb-3","twResponsive":{"md":"text-3xl"}} /-->
<!-- wp:tw-blocks/text {"content":"پاسخ به پرسش‌های فنی دربارهٔ نحوهٔ کامپایل استاتیک CSS، سازگاری با قالب‌های فعلی و اسکیمای JSON-LD:","textAlign":"text-start","twClasses":"text-base leading-relaxed text-slate-600 mb-6"} /-->
<!-- wp:tw-blocks/faq {"enableSchema":true,"items":[{"question":"آیا فعال‌سازی TW Blocks استایل قالب فعلی سایت من را به هم می‌ریزد؟","answer":"خیر؛ ریست Preflight-Lite افزونه صرفاً روی کلاس .twb-root محدود (Scoped) شده است و هیچ تداخلی با استایل‌های قالب یا ووکامرس ایجاد نمی‌کند.","openByDefault":true},{"question":"چگونه اسکیمای FAQPage بدون کدنویسی در گوگل ثبت می‌شود؟","answer":"بلوک سوالات متداول به‌طور خودکار تمام پرسش‌ها و پاسخ‌های موجود در برگه را جمع‌آوری کرده و یک اسکیمای واحد و بدون تکرار را با فرمت UTF-8 (JSON_UNESCAPED_UNICODE) در فوتر صفحه درج می‌نماید.","openByDefault":false},{"question":"اگر هاست اشتراکی من اجازهٔ اجرای باینری (proc_open) را ندهد چه اتفاقی می‌افتد؟","answer":"موتور CSS افزونه به‌صورت خودکار وضعیت محیط را تشخیص داده و روی Driver B (سرور راه دور) یا Driver C (فایل Safelist بهینه‌شده) سوییچ می‌کند تا سایت بدون لحظه‌ای قطعی کار کند.","openByDefault":false}]} /-->
<!-- /wp:tw-blocks/section -->',
);
