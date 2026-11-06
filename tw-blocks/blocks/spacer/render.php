<?php
/**
 * Server-side render for `tw-blocks/spacer`.
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

$height_map = array(
	'h-4'  => 'h-4',
	'h-8'  => 'h-8',
	'h-12' => 'h-12',
	'h-16' => 'h-16',
	'h-24' => 'h-24',
);
$height_cls = $height_map[ (string) ( $attributes['heightClass'] ?? 'h-12' ) ] ?? 'h-12';

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-spacer w-full ' . $height_cls
);

printf(
	'<div class="%s" aria-hidden="true"></div>',
	esc_attr( $final_classes )
);
