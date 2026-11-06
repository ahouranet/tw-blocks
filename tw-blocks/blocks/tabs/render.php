<?php
/**
 * Server-side render for `tw-blocks/tabs`.
 * Outputs full WAI-ARIA `tablist`, `tab`, and `tabpanel` roles.
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

$tabs = isset( $attributes['tabs'] ) && is_array( $attributes['tabs'] ) ? array_values( $attributes['tabs'] ) : array();
if ( empty( $tabs ) ) {
	return;
}

$active_idx      = max( 0, min( count( $tabs ) - 1, (int) ( $attributes['defaultTab'] ?? 0 ) ) );
$locale_meta     = PersianHelper::get_site_locale_attrs();
$instance_prefix = PersianHelper::generate_ascii_id( wp_json_encode( $tabs ) ?: 'tabs', 'twb-tabs' );

$wrapper_classes = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-tabs w-full'
);
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>" data-twb-tabs="true" dir="<?php echo esc_attr( $locale_meta['dir'] ); ?>" lang="<?php echo esc_attr( $locale_meta['lang'] ); ?>">
	<div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-4" role="tablist" aria-label="<?php echo esc_attr__( 'برگه‌های محتوا', 'tw-blocks' ); ?>">
		<?php foreach ( $tabs as $idx => $tab ) : ?>
			<?php
			$tab_title   = isset( $tab['title'] ) ? (string) $tab['title'] : '';
			$is_selected = ( $idx === $active_idx );
			$tab_id      = $instance_prefix . '-tab-' . $idx;
			$panel_id    = $instance_prefix . '-panel-' . $idx;
			$btn_cls     = $is_selected
				? 'px-4 py-2 rounded-lg text-sm font-semibold bg-primary text-white transition duration-200'
				: 'px-4 py-2 rounded-lg text-sm font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition duration-200';
			?>
			<button
				type="button"
				role="tab"
				id="<?php echo esc_attr( $tab_id ); ?>"
				aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				tabindex="<?php echo $is_selected ? '0' : '-1'; ?>"
				data-twb-tab-index="<?php echo esc_attr( (string) $idx ); ?>"
				class="<?php echo esc_attr( $btn_cls ); ?>"
			>
				<?php echo esc_html( $tab_title ); ?>
			</button>
		<?php endforeach; ?>
	</div>

	<?php foreach ( $tabs as $idx => $tab ) : ?>
		<?php
		$tab_content = isset( $tab['content'] ) ? (string) $tab['content'] : '';
		$is_selected = ( $idx === $active_idx );
		$tab_id      = $instance_prefix . '-tab-' . $idx;
		$panel_id    = $instance_prefix . '-panel-' . $idx;
		?>
		<div
			role="tabpanel"
			id="<?php echo esc_attr( $panel_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $tab_id ); ?>"
			tabindex="0"
			data-twb-panel-index="<?php echo esc_attr( (string) $idx ); ?>"
			class="text-slate-600 leading-relaxed text-start<?php echo $is_selected ? '' : ' hidden'; ?>"
		>
			<?php echo wp_kses_post( $tab_content ); ?>
		</div>
	<?php endforeach; ?>
</div>
