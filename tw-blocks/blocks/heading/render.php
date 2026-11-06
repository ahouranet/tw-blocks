<?php
/**
 * Server-side render for `tw-blocks/heading`.
 * Enforces strict h1-h6 tag whitelist and Persian-safe ASCII anchor IDs.
 *
 * @var array<string, mixed> $attributes Block attributes.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tag_name = SecurityHelper::sanitize_heading_tag( (string) ( $attributes['level'] ?? 'h2' ), 'h2' );
$raw_text = (string) ( $attributes['content'] ?? '' );

$align_map = array(
	'text-start'  => 'text-start',
	'text-center' => 'text-center',
	'text-end'    => 'text-end',
);
$align_cls = $align_map[ (string) ( $attributes['textAlign'] ?? 'text-start' ) ] ?? 'text-start';

$anchor_id = '';
if ( ! empty( $attributes['anchor'] ) ) {
	$anchor_id = PersianHelper::generate_ascii_id( (string) $attributes['anchor'], 'heading' );
} elseif ( ! empty( $attributes['autoAnchor'] ) && '' !== trim( $raw_text ) ) {
	$anchor_id = PersianHelper::generate_ascii_id( wp_strip_all_tags( $raw_text ), 'h' );
}

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-heading font-heading tracking-normal ' . $align_cls
);

printf(
	'<%1$s%2$s class="%3$s" dir="auto">%4$s</%1$s>',
	esc_attr( $tag_name ),
	'' !== $anchor_id ? ' id="' . esc_attr( $anchor_id ) . '"' : '',
	esc_attr( $final_classes ),
	wp_kses_post( $raw_text )
);
