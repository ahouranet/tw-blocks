<?php
/**
 * Server-side render for `tw-blocks/text`.
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

$align_map = array(
	'text-start'  => 'text-start',
	'text-center' => 'text-center',
	'text-end'    => 'text-end',
);
$align_cls = $align_map[ (string) ( $attributes['textAlign'] ?? 'text-start' ) ] ?? 'text-start';

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-text tracking-normal ' . $align_cls
);

printf(
	'<p class="%1$s" dir="auto">%2$s</p>',
	esc_attr( $final_classes ),
	wp_kses_post( (string) ( $attributes['content'] ?? '' ) )
);
