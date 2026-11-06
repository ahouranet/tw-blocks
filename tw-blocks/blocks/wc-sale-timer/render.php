<?php
/**
 * Server-side render for `tw-blocks/wc-sale-timer` (WooCommerce Discount Badge + Sale Countdown).
 *
 * Reads the current product's regular/sale prices and `_sale_price_dates_to` timestamp,
 * calculates the exact discount percentage with Persian numerals, and displays an RTL
 * countdown timer with optional Jalali expiration date.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block             $block      Block instance with `postId` context.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_badge      = ! isset( $attributes['showBadge'] ) || ! empty( $attributes['showBadge'] );
$show_countdown  = ! isset( $attributes['showCountdown'] ) || ! empty( $attributes['showCountdown'] );
$show_jalali_end = ! isset( $attributes['showJalaliEndDate'] ) || ! empty( $attributes['showJalaliEndDate'] );

$product_id       = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$discount_percent = 0;
$sale_end_ts      = 0;

if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
	$product = wc_get_product( $product_id );
	if ( $product && $product->is_on_sale() ) {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale > 0 && $regular > $sale ) {
			$discount_percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
		}
		$date_on_sale_to = $product->get_date_on_sale_to();
		if ( $date_on_sale_to ) {
			$sale_end_ts = (int) $date_on_sale_to->getTimestamp();
		}
	}
}

if ( $discount_percent <= 0 ) {
	$discount_percent = max( 0, min( 99, (int) ( $attributes['fallbackDiscountPercent'] ?? 0 ) ) );
}

if ( $discount_percent <= 0 && $sale_end_ts <= time() ) {
	return;
}

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-wc-sale-timer inline-flex flex-wrap items-center gap-3'
);
$locale_attrs  = PersianHelper::get_wrapper_locale_attributes();
?>
<div
	class="<?php echo esc_attr( $wrapper_class ); ?>"
	lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>"
	dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>"
	<?php if ( $show_countdown && $sale_end_ts > time() ) : ?>
		data-twb-sale-end="<?php echo esc_attr( (string) $sale_end_ts ); ?>"
	<?php endif; ?>
>
	<?php if ( $show_badge && $discount_percent > 0 ) : ?>
		<span class="twb-wc-sale-badge inline-flex items-center px-2.5 py-1 rounded-full bg-rose-600 text-white text-xs font-extrabold shadow-2xs">
			<?php
			echo esc_html(
				PersianHelper::localize_digits(
					sprintf(
						/* translators: %d: discount percentage */
						__( '%d%% OFF', 'tw-blocks' ),
						$discount_percent
					)
				)
			);
			?>
		</span>
	<?php endif; ?>

	<?php if ( $show_countdown && $sale_end_ts > time() ) : ?>
		<?php
		$remaining   = max( 0, $sale_end_ts - time() );
		$days        = (int) floor( $remaining / DAY_IN_SECONDS );
		$hours       = (int) floor( ( $remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes     = (int) floor( ( $remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$seconds     = (int) ( $remaining % MINUTE_IN_SECONDS );
		$jalali_date = PersianHelper::format_jalali_date( gmdate( 'Y-m-d', $sale_end_ts ), 'j F Y' );
		?>
		<div class="twb-wc-countdown flex items-center gap-1.5 text-xs font-bold text-rose-800 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl">
			<span><?php echo esc_html__( 'Sale ends in:', 'tw-blocks' ); ?></span>
			<span data-twb-timer-output dir="ltr" class="font-mono">
				<?php
				echo esc_html(
					PersianHelper::localize_digits(
						sprintf( '%02d:%02d:%02d:%02d', $days, $hours, $minutes, $seconds )
					)
				);
				?>
			</span>
			<?php if ( $show_jalali_end && '' !== $jalali_date ) : ?>
				<span class="text-[11px] text-rose-700 font-normal ms-1">
					(<?php echo esc_html( sprintf( __( 'until %s', 'tw-blocks' ), $jalali_date ) ); ?>)
				</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
