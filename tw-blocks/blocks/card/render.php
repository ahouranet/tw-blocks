<?php
/**
 * Server-side render for `tw-blocks/card`.
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

$badge       = (string) ( $attributes['badge'] ?? '' );
$title       = (string) ( $attributes['title'] ?? '' );
$description = (string) ( $attributes['description'] ?? '' );
$link_label  = (string) ( $attributes['linkLabel'] ?? '' );
$link_url    = esc_url( (string) ( $attributes['linkUrl'] ?? '' ) );
$locale_meta = PersianHelper::get_site_locale_attrs();

$final_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-card flex flex-col justify-between text-start'
);
?>
<article class="<?php echo esc_attr( $final_classes ); ?>" dir="<?php echo esc_attr( $locale_meta['dir'] ); ?>" lang="<?php echo esc_attr( $locale_meta['lang'] ); ?>">
	<div>
		<?php if ( '' !== trim( $badge ) ) : ?>
			<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-teal-50 text-primary mb-4">
				<?php echo esc_html( $badge ); ?>
			</span>
		<?php endif; ?>
		<?php if ( '' !== trim( $title ) ) : ?>
			<h3 class="text-xl font-bold text-slate-900 mb-3 tracking-normal">
				<?php echo esc_html( $title ); ?>
			</h3>
		<?php endif; ?>
		<?php if ( '' !== trim( $description ) ) : ?>
			<p class="text-slate-600 leading-relaxed mb-4">
				<?php echo wp_kses_post( $description ); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( '' !== trim( $link_label ) && '' !== $link_url ) : ?>
		<a href="<?php echo $link_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:opacity-80 mt-2">
			<span><?php echo esc_html( $link_label ); ?></span>
			<svg class="w-4 h-4 shrink-0 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
			</svg>
		</a>
	<?php endif; ?>
</article>
