<?php
/**
 * Server-side render for `tw-blocks/modal` using native HTML5 `<dialog>`.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    InnerBlocks modal body content.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

use TWBlocks\Plugin;
use TWBlocks\Security\Sanitizer;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trigger_label = (string) ( $attributes['triggerLabel'] ?? __( 'Open Modal', 'tw-blocks' ) );
$modal_title   = (string) ( $attributes['modalTitle'] ?? '' );
$width_map     = array(
	'max-w-md'  => 'max-w-md',
	'max-w-lg'  => 'max-w-lg',
	'max-w-2xl' => 'max-w-2xl',
);
$width_cls     = $width_map[ (string) ( $attributes['modalWidth'] ?? 'max-w-lg' ) ] ?? 'max-w-lg';
$dialog_id     = PersianHelper::generate_ascii_id( $modal_title . '-' . wp_rand( 100, 9999 ), 'twb-modal' );

$wrapper_class = Plugin::instance()->blocks()->build_block_class_string(
	$attributes,
	'twb-modal-wrapper inline-block'
);
$locale_attrs  = PersianHelper::get_wrapper_locale_attributes();
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>" lang="<?php echo esc_attr( $locale_attrs['lang'] ); ?>" dir="<?php echo esc_attr( $locale_attrs['dir'] ); ?>">
	<button
		type="button"
		class="twb-modal-open px-5 py-2.5 rounded-xl bg-teal-700 text-white text-sm font-semibold hover:bg-teal-600 transition cursor-pointer"
		data-twb-modal-open="<?php echo esc_attr( $dialog_id ); ?>"
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $dialog_id ); ?>"
	>
		<?php echo esc_html( $trigger_label ); ?>
	</button>

	<dialog
		id="<?php echo esc_attr( $dialog_id ); ?>"
		class="twb-modal-dialog w-full <?php echo esc_attr( $width_cls ); ?> rounded-2xl border border-slate-200 bg-white p-6 text-start shadow-xl backdrop:bg-slate-900/50 m-auto"
		aria-labelledby="<?php echo esc_attr( $dialog_id . '-title' ); ?>"
	>
		<div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 mb-4">
			<h3 id="<?php echo esc_attr( $dialog_id . '-title' ); ?>" class="text-base font-bold text-slate-900 m-0">
				<?php echo esc_html( $modal_title ); ?>
			</h3>
			<button
				type="button"
				class="twb-modal-close inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
				data-twb-modal-close
				aria-label="<?php echo esc_attr__( 'Close modal', 'tw-blocks' ); ?>"
			>
				✕
			</button>
		</div>
		<div class="twb-modal-body text-sm text-slate-600 leading-relaxed space-y-3">
			<?php
			if ( '' !== trim( $content ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via Sanitizer::sanitize_block_content
				echo Sanitizer::sanitize_block_content( $content );
			}
			?>
		</div>
	</dialog>
</div>
