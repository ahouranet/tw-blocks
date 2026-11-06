<?php
/**
 * Server-side render for `tw-blocks/image`.
 * Enforces explicit width/height attributes for zero CLS, lazy loading, and WP srcset when attachmentId is provided.
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

$attachment_id = max( 0, (int) ( $attributes['attachmentId'] ?? 0 ) );
$width         = max( 1, min( 4096, (int) ( $attributes['width'] ?? 1200 ) ) );
$height        = max( 1, min( 4096, (int) ( $attributes['height'] ?? 675 ) ) );
$alt           = sanitize_text_field( (string) ( $attributes['alt'] ?? '' ) );
$caption       = (string) ( $attributes['caption'] ?? '' );
$loading       = ( 'eager' === ( $attributes['loading'] ?? 'lazy' ) ) ? 'eager' : 'lazy';

$wrapper_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-image relative w-full'
);

$img_html = '';
if ( $attachment_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
	$img_html = wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'class'    => 'w-full h-auto object-cover',
			'loading'  => $loading,
			'decoding' => 'async',
			'alt'      => $alt,
		)
	);
}

if ( '' === $img_html ) {
	$src = ! empty( $attributes['src'] ) ? esc_url( (string) $attributes['src'] ) : '';
	if ( '' !== $src ) {
		$img_html = sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" loading="%5$s" decoding="async" class="w-full h-auto object-cover" />',
			$src,
			esc_attr( $alt ),
			$width,
			$height,
			esc_attr( $loading )
		);
	} else {
		// Local SVG placeholder with explicit width & height so zero external request is made.
		$img_html = sprintf(
			'<svg viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" class="w-full h-auto bg-slate-100 text-slate-400" role="img" aria-label="%3$s"><rect width="100%%" height="100%%" fill="currentColor" fill-opacity="0.12"/><text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" fill="currentColor" font-size="24">%3$s (%1$dx%2$d)</text></svg>',
			$width,
			$height,
			esc_html( '' !== $alt ? $alt : __( 'تصویر نمونه', 'tw-blocks' ) )
		);
	}
}

printf(
	'<figure class="%1$s">%2$s%3$s</figure>',
	esc_attr( $wrapper_classes ),
	$img_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized above.
	'' !== trim( $caption )
		? '<figcaption class="mt-2 text-sm text-slate-500 text-start" dir="auto">' . wp_kses_post( $caption ) . '</figcaption>'
		: ''
);
