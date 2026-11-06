<?php
/**
 * Plugin Name:       TW Blocks
 * Plugin URI:        https://example.com/tw-blocks
 * Description:       Lightweight, SEO-first Gutenberg block library and page builder powered by Tailwind CSS v4 with first-class Persian, RTL, and Jalali calendar support.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            TW Blocks Engineering
 * Author URI:        https://example.com
 * Text Domain:       tw-blocks
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package TWBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TWB_VERSION', '1.0.0' );
define( 'TWB_SLUG', 'tw-blocks' );
define( 'TWB_FILE', __FILE__ );
define( 'TWB_PATH', plugin_dir_path( __FILE__ ) );
define( 'TWB_URL', plugin_dir_url( __FILE__ ) );

require_once TWB_PATH . 'includes/Autoloader.php';

\TWBlocks\Autoloader::register();

/**
 * Global helper: Convert Latin/Arabic digits to Persian digits (۰-۹) when enabled in settings.
 *
 * @param string|int|float $input Input text or number.
 * @param bool             $force Force conversion regardless of global option.
 * @return string
 */
function twb_localize_digits( string|int|float $input, bool $force = false ): string {
	return \TWBlocks\Support\PersianHelper::localize_digits( $input, $force );
}

/**
 * Global helper: Normalize Persian (۰-۹) and Arabic (٠-٩) digits to ASCII (0-9) before saving.
 *
 * @param string|int|float $input Raw user input.
 * @return string
 */
function twb_normalize_digits( string|int|float $input ): string {
	return \TWBlocks\Support\PersianHelper::normalize_digits( (string) $input );
}

/**
 * Global helper: Format timestamp or date string into Jalali (Shamsi) or site default.
 *
 * @param int|string $timestamp Unix timestamp or date string.
 * @param string     $format    Date format string.
 * @return string
 */
function twb_format_date( int|string $timestamp, string $format = 'j F Y' ): string {
	return \TWBlocks\Support\PersianHelper::format_date( $timestamp, $format );
}

/**
 * Global helper: Register dynamic Tailwind utility classes at runtime from block render templates.
 * Avoids string concatenation pitfalls by registering complete static class tokens via a map.
 *
 * @param array<int, string>|string $classes Array or space-separated string of complete Tailwind classes.
 * @return void
 */
function twb_register_dynamic_classes( array|string $classes ): void {
	\TWBlocks\CSS\ClassExtractor::register_runtime_classes( $classes );
}

/**
 * Global helper: Generate an ASCII-only deterministic slug/ID safe for Persian titles.
 *
 * @param string $text   Source text (may contain Persian characters).
 * @param string $prefix Prefix for the generated ID.
 * @return string
 */
function twb_ascii_id( string $text, string $prefix = 'twb-' ): string {
	return \TWBlocks\Support\PersianHelper::sanitize_ascii_id( $text, $prefix );
}

register_activation_hook(
	__FILE__,
	static function (): void {
		\TWBlocks\Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		\TWBlocks\Plugin::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\TWBlocks\Plugin::instance()->init();
	},
	5
);
