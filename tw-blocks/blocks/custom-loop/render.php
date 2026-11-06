<?php
/**
 * Server-side render for `tw-blocks/custom-loop`.
 *
 * Executes a sanitized WP_Query (with transient cache for heavy queries) and renders
 * the user's InnerBlocks item template for each post in the loop with dynamic post context.
 * Enqueues `view.js` (< 1.5KB) ONLY when `paginationMode === 'load-more'`.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Rendered inner blocks fallback.
 * @var WP_Block             $block      Block instance containing parsed inner_blocks.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Blocks\QueryLoopExtension;
use TWBlocks\CSS\ClassExtractor;
use TWBlocks\Plugin;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_type = sanitize_key( (string) ( $attributes['postType'] ?? 'post' ) );
if ( ! in_array( $post_type, array( 'post', 'product', 'page' ), true ) ) {
	$post_type = 'post';
}

$per_page        = max( 1, min( 24, (int) ( $attributes['postsPerPage'] ?? 6 ) ) );
$offset          = max( 0, min( 200, (int) ( $attributes['offset'] ?? 0 ) ) );
$order_by        = sanitize_key( (string) ( $attributes['orderBy'] ?? 'date' ) );
$order_by_meta   = sanitize_key( (string) ( $attributes['orderByMeta'] ?? '' ) );
$featured_only   = ! empty( $attributes['featuredOnly'] );
$exclude_current = ! empty( $attributes['excludeCurrent'] );
$layout_mode     = 'list' === ( $attributes['layoutMode'] ?? 'grid' ) ? 'list' : 'grid';
$pagination_mode = (string) ( $attributes['paginationMode'] ?? 'numbers' );
$load_more_label = (string) ( $attributes['loadMoreLabel'] ?? __( 'Load More', 'tw-blocks' ) );

$cols_sm_map = array( 1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3' );
$cols_md_map = array( 1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4' );
$cols_lg_map = array( 1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4' );
$gap_map     = array( 'gap-4' => 'gap-4', 'gap-6' => 'gap-6', 'gap-8' => 'gap-8' );

$cols_sm_cls = $cols_sm_map[ (int) ( $attributes['colsSm'] ?? 1 ) ] ?? 'sm:grid-cols-1';
$cols_md_cls = $cols_md_map[ (int) ( $attributes['colsMd'] ?? 2 ) ] ?? 'md:grid-cols-2';
$cols_lg_cls = $cols_lg_map[ (int) ( $attributes['colsLg'] ?? 3 ) ] ?? 'lg:grid-cols-3';
$gap_cls     = $gap_map[ (string) ( $attributes['gap'] ?? 'gap-6' ) ] ?? 'gap-6';

$grid_classes = 'list' === $layout_mode
	? 'flex flex-col ' . $gap_cls
	: sprintf( 'grid grid-cols-1 %s %s %s %s', $cols_sm_cls, $cols_md_cls, $cols_lg_cls, $gap_cls );

$paged = max( 1, (int) get_query_var( 'paged', 1 ) );

$query_args = array(
	'post_type'           => $post_type,
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'orderby'             => in_array( $order_by, array( 'date', 'title', 'comment_count', 'rand' ), true ) ? $order_by : 'date',
	'order'               => 'DESC',
	'ignore_sticky_posts' => true,
);

if ( $offset > 0 ) {
	$query_args['offset'] = $offset + ( ( $paged - 1 ) * $per_page );
}

if ( $exclude_current && is_singular() ) {
	$current_id = (int) get_queried_object_id();
	if ( $current_id > 0 ) {
		$query_args['post__not_in'] = array( $current_id );
	}
}

if ( $featured_only ) {
	if ( 'product' === $post_type ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'featured' ),
				'operator' => 'IN',
			),
		);
	} else {
		$sticky = get_option( 'sticky_posts', array() );
		if ( is_array( $sticky ) && ! empty( $sticky ) ) {
			$query_args['post__in'] = array_map( 'absint', $sticky );
		}
	}
}

if ( '' !== $order_by_meta && isset( QueryLoopExtension::ALLOWED_META_SORT_KEYS[ $order_by_meta ] ) ) {
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$query_args['meta_key'] = QueryLoopExtension::ALLOWED_META_SORT_KEYS[ $order_by_meta ];
	$query_args['orderby']  = 'meta_value_num date';
}

if ( 'none' === $pagination_mode ) {
	$query_args['no_found_rows'] = true;
}

// Only enqueue minimal Load More JS (< 1.5KB) when paginationMode === 'load-more'.
if ( 'load-more' === $pagination_mode ) {
	wp_enqueue_script(
		'tw-blocks-custom-loop-view',
		TWB_URL . 'blocks/custom-loop/view.js',
		array(),
		TWB_VERSION,
		true
	);
}

$loop_query    = new WP_Query( $query_args );
$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-custom-loop space-y-8'
);

$locale_attrs = PersianHelper::get_wrapper_locale_attributes();
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>" lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>" dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>">
	<?php if ( $loop_query->have_posts() ) : ?>
		<div class="twb-custom-loop-items <?php echo esc_attr( $grid_classes ); ?>" data-twb-loop-container>
			<?php
			while ( $loop_query->have_posts() ) :
				$loop_query->the_post();
				$current_post_id   = (int) get_the_ID();
				$current_post_type = (string) get_post_type();
				$item_html         = '';

				if ( isset( $block ) && $block instanceof WP_Block && ! empty( $block->parsed_block['innerBlocks'] ) ) {
					foreach ( $block->parsed_block['innerBlocks'] as $inner_block ) {
						$block_instance = new WP_Block(
							$inner_block,
							array(
								'postType' => $current_post_type,
								'postId'   => $current_post_id,
							)
						);
						$item_html     .= $block_instance->render();
					}
					// Ensure any Tailwind classes inside the user's InnerBlocks loop item template are registered for CSS compilation.
					$extracted_loop_classes = Plugin::instance()->engine() ? ( new ClassExtractor( Plugin::instance()->tokens() ) )->extract_from_html_markup( $item_html ) : array();
					ClassExtractor::register_runtime_classes( $extracted_loop_classes );
				} else {
					// Default RTL card template when no InnerBlocks are provided.
					$date_str = PersianHelper::format_jalali_date( (string) get_the_date( 'Y-m-d' ), 'j F Y' );
					ob_start();
					?>
					<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between gap-3 text-start">
						<div class="space-y-2">
							<time class="text-xs font-medium text-teal-700 block"><?php echo esc_html( $date_str ); ?></time>
							<h3 class="text-base font-bold text-slate-900 leading-snug">
								<a href="<?php echo esc_url( (string) get_permalink() ); ?>" class="hover:text-teal-700 transition">
									<?php echo esc_html( get_the_title() ); ?>
								</a>
							</h3>
							<p class="text-sm text-slate-600 leading-relaxed">
								<?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?>
							</p>
						</div>
						<a href="<?php echo esc_url( (string) get_permalink() ); ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 hover:text-teal-800">
							<span><?php echo esc_html__( 'Read More', 'tw-blocks' ); ?></span>
							<span aria-hidden="true" class="rtl:-scale-x-100 inline-block">→</span>
						</a>
					</article>
					<?php
					$item_html = (string) ob_get_clean();
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered through WP_Block and sanitized via render_block_data
				echo PersianHelper::localize_digits( $item_html );
			endwhile;
			wp_reset_postdata();
			?>
		</div>

		<?php if ( 'numbers' === $pagination_mode && $loop_query->max_num_pages > 1 ) : ?>
			<nav class="twb-loop-pagination flex items-center justify-center gap-2 pt-4" aria-label="<?php echo esc_attr__( 'Pagination', 'tw-blocks' ); ?>">
				<?php
				$links = paginate_links(
					array(
						'total'     => (int) $loop_query->max_num_pages,
						'current'   => $paged,
						'prev_text' => esc_html__( '← Previous', 'tw-blocks' ),
						'next_text' => esc_html__( 'Next →', 'tw-blocks' ),
						'type'      => 'array',
					)
				);
				if ( is_array( $links ) ) {
					foreach ( $links as $link_html ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo '<span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700">' . PersianHelper::localize_digits( $link_html ) . '</span>';
					}
				}
				?>
			</nav>
		<?php elseif ( 'load-more' === $pagination_mode && $loop_query->max_num_pages > $paged ) : ?>
			<div class="flex justify-center pt-4">
				<button
					type="button"
					class="twb-loop-load-more px-5 py-2.5 rounded-xl bg-teal-700 text-white text-xs font-semibold hover:bg-teal-600 transition cursor-pointer"
					data-endpoint="<?php echo esc_url( rest_url( 'tw-blocks/v1/loop-more' ) ); ?>"
					data-page="<?php echo esc_attr( (string) $paged ); ?>"
					data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
					data-post-type="<?php echo esc_attr( $post_type ); ?>"
					data-order-by-meta="<?php echo esc_attr( $order_by_meta ); ?>"
				>
					<?php echo esc_html( $load_more_label ); ?>
				</button>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p class="text-sm text-slate-500 text-center py-8"><?php echo esc_html__( 'No items found matching your query.', 'tw-blocks' ); ?></p>
	<?php endif; ?>
</div>
