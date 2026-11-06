<?php
/**
 * Server-side render for `tw-blocks/divider`.
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

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-divider w-full border-0'
);

printf(
	'<hr class="%s" />',
	esc_attr( $final_classes )
);
