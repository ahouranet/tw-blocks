<?php
/**
 * Server-side render for `tw-blocks/section`.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    InnerBlocks HTML content.
 * @var \WP_Block            $block      Block instance.
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

$tag_name = SecurityHelper::sanitize_container_tag( (string) ( $attributes['tagName'] ?? 'section' ), 'section' );

$max_width_map = array(
	'max-w-5xl'  => 'max-w-5xl',
	'max-w-7xl'  => 'max-w-7xl',
	'max-w-full' => 'max-w-full',
);
$max_width_cls = $max_width_map[ (string) ( $attributes['maxWidth'] ?? 'max-w-7xl' ) ] ?? 'max-w-7xl';

$padding_y_map = array(
	'py-0'  => 'py-0',
	'py-8'  => 'py-8',
	'py-12' => 'py-12',
	'py-16' => 'py-16',
	'py-24' => 'py-24',
);
$padding_y_cls = $padding_y_map[ (string) ( $attributes['paddingY'] ?? 'py-16' ) ] ?? 'py-16';

$padding_x_map = array(
	'px-0' => 'px-0',
	'px-4' => 'px-4',
	'px-6' => 'px-6',
	'px-8' => 'px-8',
);
$padding_x_cls = $padding_x_map[ (string) ( $attributes['paddingX'] ?? 'px-6' ) ] ?? 'px-6';

$bg_map = array(
	'transparent' => '',
	'white'       => 'bg-white text-slate-900',
	'surface'     => 'bg-surface text-foreground',
	'primary'     => 'bg-primary text-white',
	'secondary'   => 'bg-secondary text-white',
);
$bg_cls = $bg_map[ (string) ( $attributes['bgVariant'] ?? 'surface' ) ] ?? 'bg-surface text-foreground';

$visibility_cls = '';
if ( ! empty( $attributes['hideOnMobile'] ) ) {
	$visibility_cls .= ' hidden md:block';
}
if ( ! empty( $attributes['hideOnDesktop'] ) ) {
	$visibility_cls .= ' md:hidden';
}

$base_classes = trim(
	sprintf(
		'twb-root twb-section mx-auto w-full %s %s %s %s %s',
		$max_width_cls,
		$padding_y_cls,
		$padding_x_cls,
		$bg_cls,
		trim( $visibility_cls )
	)
);

$final_classes = Plugin::instance()->blocks()->build_block_class_string( $attributes, $base_classes );
$locale_meta   = PersianHelper::get_site_locale_attrs();
$anchor_id     = ! empty( $attributes['anchor'] ) ? PersianHelper::generate_ascii_id( (string) $attributes['anchor'], 'section' ) : '';

$inner_html = '' !== trim( $content )
	? $content
	: '<div class="text-start text-slate-600">' . esc_html__( 'کانتینر بخش آمادهٔ افزودن بلوک‌های داخلی است.', 'tw-blocks' ) . '</div>';

printf(
	'<%1$s%2$s class="%3$s" dir="%4$s" lang="%5$s">%6$s</%1$s>',
	esc_attr( $tag_name ),
	'' !== $anchor_id ? ' id="' . esc_attr( $anchor_id ) . '"' : '',
	esc_attr( $final_classes ),
	esc_attr( $locale_meta['dir'] ),
	esc_attr( $locale_meta['lang'] ),
	$inner_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- InnerBlocks rendered by WP core.
);
