<?php
/**
 * Server-side render for `tw-blocks/icon-box`.
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

$icons = array(
	'shield'   => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
	'bolt'     => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
	'sparkles' => '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16 2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
);

$icon_key    = sanitize_key( (string) ( $attributes['iconName'] ?? 'shield' ) );
$svg         = $icons[ $icon_key ] ?? $icons['shield'];
$title       = (string) ( $attributes['title'] ?? '' );
$description = (string) ( $attributes['description'] ?? '' );
$locale_meta = PersianHelper::get_site_locale_attrs();

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-icon-box text-start'
);
?>
<div class="<?php echo esc_attr( $final_classes ); ?>" dir="<?php echo esc_attr( $locale_meta['dir'] ); ?>" lang="<?php echo esc_attr( $locale_meta['lang'] ); ?>">
	<span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-teal-50 text-primary shrink-0" aria-hidden="true">
		<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static hardcoded SVG. ?>
	</span>
	<div class="flex-1">
		<?php if ( '' !== trim( $title ) ) : ?>
			<h3 class="text-lg font-bold text-slate-900 mb-1 tracking-normal"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== trim( $description ) ) : ?>
			<p class="text-sm text-slate-600 leading-relaxed"><?php echo wp_kses_post( $description ); ?></p>
		<?php endif; ?>
	</div>
</div>
