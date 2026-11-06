<?php
/**
 * Server-side render for `tw-blocks/toc` (Dynamic Table of Contents).
 *
 * Scans the current post's `post_content` for `<h2>`, `<h3>`, and `<h4>` headings,
 * generates deterministic ASCII anchor IDs via `PersianHelper::generate_ascii_id()`,
 * ensures headings in `the_content` receive matching `id` attributes, and outputs a
 * zero-JS `<details>` / `<nav>` Table of Contents.
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

$toc_title    = (string) ( $attributes['title'] ?? __( 'Table of Contents', 'tw-blocks' ) );
$max_depth    = max( 2, min( 4, (int) ( $attributes['maxDepth'] ?? 3 ) ) );
$collapsible  = ! isset( $attributes['collapsible'] ) || ! empty( $attributes['collapsible'] );
$default_open = ! isset( $attributes['defaultOpen'] ) || ! empty( $attributes['defaultOpen'] );

$post_obj     = get_post();
$raw_content  = $post_obj instanceof \WP_Post ? (string) $post_obj->post_content : '';
$headings     = array();
$tag_pattern  = 2 === $max_depth ? 'h2' : ( 3 === $max_depth ? 'h[23]' : 'h[234]' );

if ( '' !== $raw_content && preg_match_all( '/<(' . $tag_pattern . ')([^>]*)>(.*?)<\/\1>/is', $raw_content, $matches, PREG_SET_ORDER ) ) {
	foreach ( $matches as $match ) {
		$level_tag = strtolower( $match[1] );
		$attr_str  = $match[2];
		$inner_txt = trim( wp_strip_all_tags( $match[3] ) );
		if ( '' === $inner_txt ) {
			continue;
		}

		$anchor_id = '';
		if ( preg_match( '/\bid\s*=\s*(["\'])([^"\']+)\1/i', $attr_str, $id_match ) ) {
			$anchor_id = PersianHelper::generate_ascii_id( $id_match[2], 'h' );
		} else {
			$anchor_id = PersianHelper::generate_ascii_id( $inner_txt, 'h' );
		}

		$headings[] = array(
			'level' => (int) substr( $level_tag, 1 ),
			'text'  => $inner_txt,
			'id'    => $anchor_id,
		);
	}
}

// Also inspect Gutenberg `tw-blocks/heading` block comments if raw HTML tags were not rendered yet.
if ( empty( $headings ) && '' !== $raw_content && preg_match_all( '/<!--\s+wp:tw-blocks\/heading\s+(\{.*?\})\s+\/-->/s', $raw_content, $block_matches ) ) {
	foreach ( $block_matches[1] as $json_str ) {
		$decoded = json_decode( $json_str, true );
		if ( ! is_array( $decoded ) || empty( $decoded['content'] ) ) {
			continue;
		}
		$lvl_str   = strtolower( (string) ( $decoded['level'] ?? 'h2' ) );
		$lvl_num   = (int) substr( $lvl_str, 1 );
		$clean_txt = trim( wp_strip_all_tags( (string) $decoded['content'] ) );
		if ( $lvl_num >= 2 && $lvl_num <= $max_depth && '' !== $clean_txt ) {
			$headings[] = array(
				'level' => $lvl_num,
				'text'  => $clean_txt,
				'id'    => ! empty( $decoded['anchor'] )
					? PersianHelper::generate_ascii_id( (string) $decoded['anchor'], 'heading' )
					: PersianHelper::generate_ascii_id( $clean_txt, 'h' ),
			);
		}
	}
}

if ( empty( $headings ) ) {
	return;
}

// Register a one-time filter on `the_content` to guarantee headings without an `id` receive their deterministic ASCII `id`.
if ( ! has_filter( 'the_content', 'twb_inject_toc_heading_ids' ) ) {
	/**
	 * Inject deterministic ASCII `id` attributes into `h2`-`h4` tags lacking an `id`.
	 *
	 * @param string $html Rendered post content.
	 * @return string
	 */
	function twb_inject_toc_heading_ids( string $html ): string {
		return (string) preg_replace_callback(
			'/<(h[234])([^>]*)>(.*?)<\/\1>/is',
			static function ( array $m ): string {
				if ( preg_match( '/\bid\s*=/i', $m[2] ) ) {
					return $m[0];
				}
				$plain = trim( wp_strip_all_tags( $m[3] ) );
				if ( '' === $plain ) {
					return $m[0];
				}
				$id = PersianHelper::generate_ascii_id( $plain, 'h' );
				return sprintf( '<%1$s id="%2$s"%3$s>%4$s</%1$s>', $m[1], esc_attr( $id ), $m[2], $m[3] );
			},
			$html
		);
	}
	add_filter( 'the_content', 'twb_inject_toc_heading_ids', 12 );
}

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-toc rounded-2xl border border-slate-200 bg-slate-50 p-5 text-start my-6'
);
$locale_attrs  = PersianHelper::get_wrapper_locale_attributes();
?>
<nav
	class="<?php echo esc_attr( $wrapper_class ); ?>"
	aria-label="<?php echo esc_attr( $toc_title ); ?>"
	lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>"
	dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>"
>
	<?php if ( $collapsible ) : ?>
		<details <?php echo $default_open ? 'open' : ''; ?> class="group">
			<summary class="flex items-center justify-between gap-3 cursor-pointer list-none font-bold text-sm text-slate-900">
				<span><?php echo esc_html( $toc_title ); ?></span>
				<span class="text-xs text-teal-700 group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
			</summary>
			<ol class="mt-3 pt-3 border-t border-slate-200 space-y-2 text-sm text-slate-700 list-decimal ps-5 m-0">
				<?php foreach ( $headings as $idx => $item ) : ?>
					<li class="<?php echo 3 === $item['level'] ? 'ms-4' : ( 4 === $item['level'] ? 'ms-8' : '' ); ?>">
						<a href="#<?php echo esc_attr( $item['id'] ); ?>" class="hover:text-teal-700 transition leading-relaxed">
							<?php echo esc_html( PersianHelper::localize_digits( $item['text'] ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</details>
	<?php else : ?>
		<div class="font-bold text-sm text-slate-900 mb-3"><?php echo esc_html( $toc_title ); ?></div>
		<ol class="space-y-2 text-sm text-slate-700 list-decimal ps-5 m-0">
			<?php foreach ( $headings as $item ) : ?>
				<li class="<?php echo 3 === $item['level'] ? 'ms-4' : ( 4 === $item['level'] ? 'ms-8' : '' ); ?>">
					<a href="#<?php echo esc_attr( $item['id'] ); ?>" class="hover:text-teal-700 transition leading-relaxed">
						<?php echo esc_html( PersianHelper::localize_digits( $item['text'] ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</nav>
