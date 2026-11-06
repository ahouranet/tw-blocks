<?php
/**
 * Server-side render for `tw-blocks/grid`.
 * Uses static lookup tables for responsive columns (no dynamic string concatenation).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    InnerBlocks HTML content.
 * @var \WP_Block            $block      Block instance.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$layout_mode = ( 'flex' === ( $attributes['layoutMode'] ?? 'grid' ) ) ? 'flex' : 'grid';

// Static Tailwind class maps (never concatenate `grid-cols-` + $n dynamically).
$base_cols_map = array(
	1 => 'grid-cols-1',
	2 => 'grid-cols-2',
	3 => 'grid-cols-3',
	4 => 'grid-cols-4',
);
$md_cols_map   = array(
	1 => 'md:grid-cols-1',
	2 => 'md:grid-cols-2',
	3 => 'md:grid-cols-3',
	4 => 'md:grid-cols-4',
);
$lg_cols_map   = array(
	1 => 'lg:grid-cols-1',
	2 => 'lg:grid-cols-2',
	3 => 'lg:grid-cols-3',
	4 => 'lg:grid-cols-4',
);

$gap_map = array(
	'gap-2' => 'gap-2',
	'gap-4' => 'gap-4',
	'gap-6' => 'gap-6',
	'gap-8' => 'gap-8',
);

$align_map = array(
	'items-start'   => 'items-start',
	'items-center'  => 'items-center',
	'items-stretch' => 'items-stretch',
);

$justify_map = array(
	'justify-start'   => 'justify-start',
	'justify-center'  => 'justify-center',
	'justify-between' => 'justify-between',
);

$gap_cls     = $gap_map[ (string) ( $attributes['gap'] ?? 'gap-6' ) ] ?? 'gap-6';
$align_cls   = $align_map[ (string) ( $attributes['alignItems'] ?? 'items-stretch' ) ] ?? 'items-stretch';
$justify_cls = $justify_map[ (string) ( $attributes['justifyContent'] ?? 'justify-between' ) ] ?? 'justify-between';

if ( 'flex' === $layout_mode ) {
	$base_classes = sprintf( 'twb-grid flex flex-wrap %s %s %s', $gap_cls, $align_cls, $justify_cls );
} else {
	$c_base       = $base_cols_map[ (int) ( $attributes['colsBase'] ?? 1 ) ] ?? 'grid-cols-1';
	$c_md         = $md_cols_map[ (int) ( $attributes['colsMd'] ?? 2 ) ] ?? 'md:grid-cols-2';
	$c_lg         = $lg_cols_map[ (int) ( $attributes['colsLg'] ?? 3 ) ] ?? 'lg:grid-cols-3';
	$base_classes = sprintf( 'twb-grid grid %s %s %s %s %s', $c_base, $c_md, $c_lg, $gap_cls, $align_cls );
}

$final_classes = Plugin::instance()->blocks()->build_block_class_string( $attributes, $base_classes );

printf(
	'<div class="%1$s">%2$s</div>',
	esc_attr( $final_classes ),
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- InnerBlocks rendered by WP core.
);
