<?php
/**
 * Server-side render for `tw-blocks/post-grid`.
 * Features:
 * - Bounded `posts_per_page` (1..24) to prevent DB DoS.
 * - Sets `no_found_rows => true` when pagination is disabled to skip expensive SQL_CALC_FOUND_ROWS.
 * - Native Jalali date formatting via `twb_format_date()` (uses WP-Parsidate if active or built-in Gregorian->Jalali converter).
 * - Localized Persian numerals (`۰-۹`) via `twb_localize_digits()`.
 *
 * @var array<string, mixed> $attributes Block attributes.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Normalize and bound numeric query parameters.
$posts_per_page  = max( 1, min( 24, (int) PersianHelper::normalize_digits( (string) ( $attributes['postsPerPage'] ?? 3 ) ) ) );
$columns         = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$category_id     = max( 0, (int) ( $attributes['categoryId'] ?? 0 ) );
$show_jalali     = ! isset( $attributes['showJalaliDate'] ) || ! empty( $attributes['showJalaliDate'] );
$show_excerpt    = ! isset( $attributes['showExcerpt'] ) || ! empty( $attributes['showExcerpt'] );
$show_pagination = ! empty( $attributes['showPagination'] );
$read_more_label = (string) ( $attributes['readMoreLabel'] ?? __( 'ادامهٔ مطلب', 'tw-blocks' ) );

// Static column lookup map (never concatenate `lg:grid-cols-` dynamically).
$col_class_map = array(
	1 => 'grid-cols-1',
	2 => 'grid-cols-1 md:grid-cols-2',
	3 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
	4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
);
$grid_cols_cls = $col_class_map[ $columns ] ?? $col_class_map[3];

$paged = 1;
if ( $show_pagination ) {
	$paged = max( 1, (int) get_query_var( 'paged', (int) get_query_var( 'page', 1 ) ) );
}

$query_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => $posts_per_page,
	'paged'               => $paged,
	'ignore_sticky_posts' => true,
	// Performance optimization: skip SQL_CALC_FOUND_ROWS when pagination is disabled.
	'no_found_rows'       => ! $show_pagination,
);

if ( $category_id > 0 ) {
	$query_args['cat'] = $category_id;
}

$query       = new \WP_Query( $query_args );
$locale_meta = PersianHelper::get_site_locale_attrs();

$grid_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-post-grid grid ' . $grid_cols_cls
);

if ( ! $query->have_posts() ) {
	printf(
		'<div class="rounded-xl border border-slate-200 bg-surface p-6 text-center text-slate-600" dir="%1$s" lang="%2$s">%3$s</div>',
		esc_attr( $locale_meta['dir'] ),
		esc_attr( $locale_meta['lang'] ),
		esc_html__( 'هیچ نوشته‌ای برای نمایش یافت نشد.', 'tw-blocks' )
	);
	return;
}
?>
<div class="twb-post-grid-wrapper w-full" dir="<?php echo esc_attr( $locale_meta['dir'] ); ?>" lang="<?php echo esc_attr( $locale_meta['lang'] ); ?>">
	<div class="<?php echo esc_attr( $grid_classes ); ?>">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			$post_id        = (int) get_the_ID();
			$unix_timestamp = (int) get_post_time( 'U', true, $post_id );
			$iso_date       = (string) get_the_date( 'c', $post_id );
			$display_date   = $show_jalali
				? twb_format_date( $unix_timestamp, 'j F Y' )
				: twb_localize_digits( (string) get_the_date( '', $post_id ) );
			?>
			<article class="twb-post-card flex flex-col justify-between rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition duration-200 text-start">
				<?php if ( has_post_thumbnail( $post_id ) ) : ?>
					<a href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>" class="block overflow-hidden aspect-video bg-slate-100">
						<?php
						echo get_the_post_thumbnail(
							$post_id,
							'medium_large',
							array(
								'class'    => 'w-full h-full object-cover',
								'loading'  => 'lazy',
								'decoding' => 'async',
							)
						);
						?>
					</a>
				<?php endif; ?>

				<div class="p-6 flex flex-col flex-1 justify-between">
					<div>
						<div class="flex items-center gap-2 text-xs text-slate-500 mb-3">
							<time datetime="<?php echo esc_attr( $iso_date ); ?>">
								<?php echo esc_html( $display_date ); ?>
							</time>
						</div>

						<h3 class="text-xl font-bold text-slate-900 mb-3 tracking-normal">
							<a href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>" class="hover:text-primary transition duration-200">
								<?php echo esc_html( get_the_title( $post_id ) ); ?>
							</a>
						</h3>

						<?php if ( $show_excerpt ) : ?>
							<p class="text-sm text-slate-600 leading-relaxed mb-4">
								<?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 22, '…' ) ); ?>
							</p>
						<?php endif; ?>
					</div>

					<a href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:opacity-80 mt-2">
						<span><?php echo esc_html( $read_more_label ); ?></span>
						<svg class="w-4 h-4 shrink-0 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
						</svg>
					</a>
				</div>
			</article>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	</div>

	<?php if ( $show_pagination && $query->max_num_pages > 1 ) : ?>
		<?php
		$pagination_links = paginate_links(
			array(
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'prev_text' => esc_html__( 'قبلی', 'tw-blocks' ),
				'next_text' => esc_html__( 'بعدی', 'tw-blocks' ),
				'type'      => 'array',
			)
		);
		?>
		<?php if ( is_array( $pagination_links ) && ! empty( $pagination_links ) ) : ?>
			<nav class="mt-8 flex items-center justify-center gap-2" aria-label="<?php echo esc_attr__( 'صفحه‌بندی نوشته‌ها', 'tw-blocks' ); ?>">
				<?php foreach ( $pagination_links as $link_html ) : ?>
					<span class="inline-flex items-center text-sm font-semibold">
						<?php echo wp_kses_post( twb_localize_digits( (string) $link_html ) ); ?>
					</span>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
