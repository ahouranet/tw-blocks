<?php
/**
 * Pattern: Latest Blog Posts Showcase with Jalali Date (`tw-blocks/blog-showcase`).
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'title'         => __( 'Latest Blog Posts with Jalali Date', 'tw-blocks' ),
	'description'   => __( 'Server-side rendered blog post showcase displaying Jalali (Shamsi) publication dates and localized Persian numerals.', 'tw-blocks' ),
	'categories'    => array( 'tw-blocks-patterns', 'posts', 'query' ),
	'keywords'      => array( 'blog', 'posts', 'jalali', 'grid' ),
	'viewportWidth' => 1280,
	'content'       => '<!-- wp:tw-blocks/section {"tagName":"section","maxWidth":"max-w-7xl","paddingY":"py-16","paddingX":"px-6","bgVariant":"surface","twClasses":"rounded-2xl border border-slate-200"} -->
<!-- wp:tw-blocks/heading {"level":"h2","content":"آخرین مقاله‌ها و آموزش‌های تخصصی","textAlign":"text-start","twClasses":"text-2xl font-bold text-slate-900 mb-2","twResponsive":{"md":"text-3xl"}} /-->
<!-- wp:tw-blocks/text {"content":"مقالات تازه منتشرشده با تاریخ شمسی (جلالی) و رندر سمت سرور:","textAlign":"text-start","twClasses":"text-base leading-relaxed text-slate-600 mb-8"} /-->
<!-- wp:tw-blocks/post-grid {"postsPerPage":3,"columns":3,"showJalaliDate":true,"showExcerpt":true,"showPagination":false,"readMoreLabel":"ادامهٔ مطلب","twClasses":"gap-6"} /-->
<!-- /wp:tw-blocks/section -->',
);
