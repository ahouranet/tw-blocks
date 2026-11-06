<?php
/**
 * Server-side render for `tw-blocks/button`.
 * Automatically mirrors directional arrow icons in RTL (`rtl:-scale-x-100`) and enforces safe URLs.
 *
 * @var array<string, mixed> $attributes Block attributes.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant_map = array(
	'primary'   => 'bg-primary text-white hover:opacity-90',
	'secondary' => 'bg-secondary text-white hover:opacity-90',
	'outline'   => 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50',
);
$variant_cls = $variant_map[ (string) ( $attributes['variant'] ?? 'primary' ) ] ?? $variant_map['primary'];

$base_classes  = 'twb-button inline-flex items-center justify-center gap-2 transition duration-200 ' . $variant_cls;
$final_classes = Plugin::instance()->blocks()->build_block_class_string( $attributes, $base_classes );

$url        = esc_url( (string) ( $attributes['url'] ?? '#' ) );
$label      = (string) ( $attributes['label'] ?? '' );
$new_tab    = ! empty( $attributes['openInNewTab'] );
$show_arrow = ! empty( $attributes['showArrowIcon'] );

$arrow_svg = '';
if ( $show_arrow ) {
	// Directional arrow uses rtl:-scale-x-100 so it points left in RTL and right in LTR automatically.
	$arrow_svg = '<svg class="w-4 h-4 shrink-0 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>';
}

printf(
	'<a href="%1$s" class="%2$s"%3$s><span>%4$s</span>%5$s</a>',
	'' !== $url ? $url : '#',
	esc_attr( $final_classes ),
	$new_tab ? ' target="_blank" rel="noopener noreferrer"' : '',
	esc_html( $label ),
	$arrow_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static hardcoded SVG.
);
