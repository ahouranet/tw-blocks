<?php
/**
 * Server-side render for `tw-blocks/faq`.
 * Uses native HTML5 `<details>` and `<summary>` (0 KB JavaScript) and registers UTF-8 FAQPage JSON-LD schema.
 *
 * @var array<string, mixed> $attributes Block attributes.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Blocks\BlockRegistry;
use TWBlocks\Plugin;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items         = isset( $attributes['items'] ) && is_array( $attributes['items'] ) ? $attributes['items'] : array();
$enable_schema = ! isset( $attributes['enableSchema'] ) || ! empty( $attributes['enableSchema'] );
$locale_meta   = PersianHelper::get_site_locale_attrs();

$wrapper_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-faq w-full'
);

if ( empty( $items ) ) {
	return;
}

echo '<div class="' . esc_attr( $wrapper_classes ) . '" dir="' . esc_attr( $locale_meta['dir'] ) . '" lang="' . esc_attr( $locale_meta['lang'] ) . '">';

foreach ( $items as $index => $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$question = isset( $item['question'] ) ? (string) $item['question'] : '';
	$answer   = isset( $item['answer'] ) ? (string) $item['answer'] : '';
	$is_open  = ! empty( $item['openByDefault'] );

	if ( '' === trim( $question ) ) {
		continue;
	}

	if ( $enable_schema && '' !== trim( $answer ) ) {
		BlockRegistry::add_faq_schema_item( $question, $answer );
	}

	$item_id = PersianHelper::generate_ascii_id( $question . '-' . (string) $index, 'faq' );
	?>
	<details id="<?php echo esc_attr( $item_id ); ?>" class="twb-faq-item rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200"<?php echo $is_open ? ' open' : ''; ?>>
		<summary class="flex cursor-pointer items-center justify-between gap-4 font-semibold text-slate-900 text-start">
			<span><?php echo esc_html( $question ); ?></span>
			<svg class="w-5 h-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
			</svg>
		</summary>
		<div class="mt-3 pt-3 border-t border-slate-100 text-slate-600 leading-relaxed text-start" dir="auto">
			<?php echo wp_kses_post( $answer ); ?>
		</div>
	</details>
	<?php
}

echo '</div>';
