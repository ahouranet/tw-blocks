<?php
/**
 * Server-side render for `tw-blocks/icon`.
 * Renders sanitized inline SVG (zero icon-font bloat) with optional RTL mirroring (`rtl:-scale-x-100`).
 *
 * @var array<string, mixed> $attributes Block attributes.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$presets = array(
	'sparkles' => '<svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16 2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
	'shield'   => '<svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
	'bolt'     => '<svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
	'arrow'    => '<svg class="w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>',
);

$icon_name  = sanitize_key( (string) ( $attributes['iconName'] ?? 'sparkles' ) );
$custom_svg = trim( (string) ( $attributes['customSvg'] ?? '' ) );

if ( '' !== $custom_svg ) {
	$svg_markup = SecurityHelper::sanitize_svg( $custom_svg );
} else {
	$svg_markup = $presets[ $icon_name ] ?? $presets['sparkles'];
}

$mirror_cls   = ! empty( $attributes['mirrorInRtl'] ) ? ' rtl:-scale-x-100' : '';
$base_classes = 'twb-icon inline-flex items-center justify-center shrink-0' . $mirror_cls;
$final_cls    = Plugin::instance()->blocks()->build_block_class_string( $attributes, $base_classes );
$aria_label   = sanitize_text_field( (string) ( $attributes['ariaLabel'] ?? '' ) );

printf(
	'<span class="%1$s"%2$s>%3$s</span>',
	esc_attr( $final_cls ),
	'' !== $aria_label ? ' role="img" aria-label="' . esc_attr( $aria_label ) . '"' : ' aria-hidden="true"',
	$svg_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Strictly sanitized via SecurityHelper::sanitize_svg().
);
