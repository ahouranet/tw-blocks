<?php
/**
 * Server-side render for `tw-blocks/slider`.
 *
 * Uses native CSS `scroll-snap-type: x mandatory` with RTL-aware prev/next buttons
 * and zero heavy carousel libraries (no Swiper).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Rendered InnerBlocks slides.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Security\Sanitizer;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gap_map   = array(
	'gap-4' => 'gap-4',
	'gap-6' => 'gap-6',
	'gap-8' => 'gap-8',
);
$gap_cls     = $gap_map[ (string) ( $attributes['gap'] ?? 'gap-6' ) ] ?? 'gap-6';
$show_arrows = ! isset( $attributes['showArrows'] ) || ! empty( $attributes['showArrows'] );
$aria_label  = (string) ( $attributes['ariaLabel'] ?? __( 'Content Slider', 'tw-blocks' ) );

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-slider relative group'
);

$locale_attrs = PersianHelper::get_wrapper_locale_attributes();
?>
<section
	class="<?php echo esc_attr( $wrapper_class ); ?>"
	aria-label="<?php echo esc_attr( $aria_label ); ?>"
	lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>"
	dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>"
	data-twb-slider
>
	<?php if ( $show_arrows ) : ?>
		<div class="flex items-center justify-end gap-2 mb-4">
			<button
				type="button"
				class="twb-slider-prev inline-flex items-center justify-center w-9 h-9 rounded-full border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition cursor-pointer"
				aria-label="<?php echo esc_attr__( 'Previous slide', 'tw-blocks' ); ?>"
				data-twb-slider-dir="prev"
			>
				<span aria-hidden="true" class="rtl:-scale-x-100 inline-block">←</span>
			</button>
			<button
				type="button"
				class="twb-slider-next inline-flex items-center justify-center w-9 h-9 rounded-full border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition cursor-pointer"
				aria-label="<?php echo esc_attr__( 'Next slide', 'tw-blocks' ); ?>"
				data-twb-slider-dir="next"
			>
				<span aria-hidden="true" class="rtl:-scale-x-100 inline-block">→</span>
			</button>
		</div>
	<?php endif; ?>

	<div
		class="twb-slider-track flex overflow-x-auto snap-x snap-mandatory scroll-smooth pb-3 <?php echo esc_attr( $gap_cls ); ?>"
		data-twb-slider-track
		tabindex="0"
	>
		<?php
		if ( '' !== trim( $content ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via Sanitizer::sanitize_block_content
			echo Sanitizer::sanitize_block_content( $content );
		}
		?>
	</div>
</section>
