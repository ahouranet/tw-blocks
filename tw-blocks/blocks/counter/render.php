<?php
/**
 * Server-side render for `tw-blocks/counter`.
 *
 * Outputs SEO-friendly initial Persian numerals on the server and animates
 * smoothly via `IntersectionObserver` on the client.
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

$target_number = max( 0, (int) PersianHelper::normalize_digits( (string) ( $attributes['targetNumber'] ?? 1250 ) ) );
$prefix        = (string) ( $attributes['prefix'] ?? '+' );
$suffix        = (string) ( $attributes['suffix'] ?? '' );
$label         = (string) ( $attributes['label'] ?? '' );
$duration_ms   = max( 300, min( 5000, (int) ( $attributes['durationMs'] ?? 1200 ) ) );

$formatted_initial = PersianHelper::localize_digits( number_format_i18n( $target_number ) );

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-counter rounded-2xl border border-slate-200 bg-white p-6 text-center space-y-2 shadow-xs'
);
?>
<div
	class="<?php echo esc_attr( $wrapper_class ); ?>"
	data-twb-counter
	data-target="<?php echo esc_attr( (string) $target_number ); ?>"
	data-duration="<?php echo esc_attr( (string) $duration_ms ); ?>"
>
	<div class="text-3xl font-extrabold text-teal-700 tracking-normal">
		<?php if ( '' !== $prefix ) : ?>
			<span><?php echo esc_html( PersianHelper::localize_digits( $prefix ) ); ?></span>
		<?php endif; ?>
		<span data-twb-counter-value><?php echo esc_html( $formatted_initial ); ?></span>
		<?php if ( '' !== $suffix ) : ?>
			<span class="text-lg font-bold text-slate-700"><?php echo esc_html( PersianHelper::localize_digits( $suffix ) ); ?></span>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $label ) : ?>
		<p class="text-sm font-medium text-slate-600 m-0"><?php echo esc_html( PersianHelper::localize_digits( $label ) ); ?></p>
	<?php endif; ?>
</div>
