<?php
/**
 * Server-side render for `tw-blocks/wc-recently-viewed`.
 *
 * Reads the sanitized `woocommerce_recently_viewed` cookie, queries matching published
 * products, and renders RTL Tailwind product cards with Toman/Rial Persian prices.
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

$raw_cookie = isset( $_COOKIE['woocommerce_recently_viewed'] )
	? sanitize_text_field( wp_unslash( (string) $_COOKIE['woocommerce_recently_viewed'] ) )
	: '';

$viewed_ids = array_values( array_filter( array_map( 'absint', explode( '|', $raw_cookie ) ) ) );

// Exclude currently viewed product on single product page.
if ( function_exists( 'is_product' ) && is_product() ) {
	$viewed_ids = array_values( array_diff( $viewed_ids, array( (int) get_queried_object_id() ) ) );
}

$limit      = max( 1, min( 12, (int) ( $attributes['postsToShow'] ?? 4 ) ) );
$viewed_ids = array_slice( $viewed_ids, 0, $limit );

if ( empty( $viewed_ids ) ) {
	return;
}

$cols_map = array(
	2 => 'lg:grid-cols-2',
	3 => 'lg:grid-cols-3',
	4 => 'lg:grid-cols-4',
);
$cols_cls = $cols_map[ (int) ( $attributes['columns'] ?? 4 ) ] ?? 'lg:grid-cols-4';
$title    = (string) ( $attributes['title'] ?? __( 'Recently Viewed Products', 'tw-blocks' ) );

$query = new WP_Query(
	array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'post__in'            => $viewed_ids,
		'orderby'             => 'post__in',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	)
);

if ( ! $query->have_posts() ) {
	return;
}

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-wc-recently-viewed space-y-5 my-8'
);
$locale_attrs  = PersianHelper::get_wrapper_locale_attributes();
?>
<section
	class="<?php echo esc_attr( $wrapper_class ); ?>"
	lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>"
	dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>"
>
	<?php if ( '' !== $title ) : ?>
		<h2 class="text-xl font-bold text-slate-900 text-start m-0"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<div class="grid grid-cols-1 sm:grid-cols-2 <?php echo esc_attr( $cols_cls ); ?> gap-6">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			$prod_id    = (int) get_the_ID();
			$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $prod_id ) : null;
			$price_html = $product ? PersianHelper::localize_digits( (string) $product->get_price_html() ) : '';
			?>
			<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs hover:shadow-md transition flex flex-col justify-between gap-3 text-start">
				<a href="<?php echo esc_url( (string) get_permalink() ); ?>" class="block space-y-3">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'woocommerce_thumbnail', array( 'class' => 'w-full aspect-square object-cover rounded-xl' ) ); ?>
					<?php endif; ?>
					<h3 class="text-sm font-bold text-slate-900 leading-snug hover:text-teal-700 transition m-0">
						<?php echo esc_html( get_the_title() ); ?>
					</h3>
				</a>
				<?php if ( '' !== $price_html ) : ?>
					<div class="text-sm font-extrabold text-teal-700">
						<?php echo wp_kses_post( $price_html ); ?>
					</div>
				<?php endif; ?>
			</article>
			<?php
		endwhile;
		wp_reset_postdata();
		?>
	</div>
</section>
