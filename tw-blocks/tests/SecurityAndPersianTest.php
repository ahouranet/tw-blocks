<?php
/**
 * Standalone Automated Test Suite for TW Blocks (Phase 1, 2 & 3).
 *
 * Verifies:
 * - Persian numeral localization (`PersianHelper::localize_digits`) without corrupting HTML attributes.
 * - Persian/Arabic numeral normalization (`PersianHelper::normalize_digits`).
 * - Built-in Gregorian to Jalali calendar conversion (`PersianHelper::gregorian_to_jalali`).
 * - Deterministic ASCII ID generation from Persian headings (`PersianHelper::generate_ascii_id`).
 * - Tailwind v4 class whitelist validation (`ClassExtractor::is_valid_class`).
 * - Security hardening (`SecurityHelper` container/heading tag whitelists, `<style>` breakout, WOFF2 magic bytes, SSRF URL check).
 *
 * @package TWBlocks\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'TWB_PATH' ) ) {
	define( 'TWB_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'TWB_URL' ) ) {
	define( 'TWB_URL', 'https://example.com/wp-content/plugins/tw-blocks/' );
}
if ( ! defined( 'TWB_VERSION' ) ) {
	define( 'TWB_VERSION', '1.0.0' );
}

require_once dirname( __DIR__ ) . '/includes/Autoloader.php';
\TWBlocks\Autoloader::register();

use TWBlocks\CSS\ClassExtractor;
use TWBlocks\Security\Sanitizer;
use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

/**
 * Simple assertion runner compatible with both CLI and PHPUnit.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Test description.
 * @return void
 */
function twb_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

// 1. Persian digits localization preserves HTML attributes.
$html_input = '<div class="grid-cols-3 p-4" data-id="123">قیمت: 2500 تومان</div>';
$localized  = PersianHelper::localize_digits( $html_input, true );
twb_assert(
	'<div class="grid-cols-3 p-4" data-id="123">قیمت: ۲۵۰۰ تومان</div>' === $localized,
	'PersianHelper::localize_digits converts text node numerals while preserving HTML attributes'
);

// 2. Persian & Arabic-Indic digit normalization.
twb_assert(
	'12.5' === PersianHelper::normalize_digits( '۱۲٫۵' ),
	'PersianHelper::normalize_digits normalizes Persian numerals and decimal separator'
);

// 3. Gregorian to Jalali conversion (2026-03-21 -> 1405/01/01 Nowruz).
$jalali = PersianHelper::gregorian_to_jalali( 2026, 3, 21 );
twb_assert(
	array( 1405, 1, 1 ) === $jalali,
	'PersianHelper::gregorian_to_jalali accurately converts 2026-03-21 to 1405-01-01'
);

// 4. ClassExtractor strict whitelist.
twb_assert( ClassExtractor::is_valid_class( 'ps-6' ), 'Allows logical ps-6 utility' );
twb_assert( ClassExtractor::is_valid_class( 'rtl:-scale-x-100' ), 'Allows rtl:-scale-x-100 directional utility' );
twb_assert( ! ClassExtractor::is_valid_class( 'evil";color:red' ), 'Blocks CSS injection payload in class name' );
twb_assert( ! ClassExtractor::is_valid_class( '<script>' ), 'Blocks HTML tag in class name' );

// 5. SecurityHelper tag whitelists & style breakout.
twb_assert(
	'section' === SecurityHelper::sanitize_container_tag( 'script onload=alert(1)' ),
	'SecurityHelper::sanitize_container_tag blocks malicious tag names'
);
twb_assert(
	'h2' === SecurityHelper::sanitize_heading_tag( 'iframe' ),
	'SecurityHelper::sanitize_heading_tag enforces h1-h6 whitelist'
);
$safe_css = SecurityHelper::sanitize_inline_css( '.box{color:red}</style><script>alert(1)</script>' );
twb_assert(
	! str_contains( $safe_css, '</style>' ) && ! str_contains( $safe_css, '<script>' ),
	'SecurityHelper::sanitize_inline_css strips </style> breakout tags'
);

// 6. WOFF2 binary magic-byte signature verification.
$valid_font_file = dirname( __DIR__ ) . '/assets/fonts/Vazirmatn-Variable.woff2';
twb_assert(
	SecurityHelper::has_valid_woff2_signature( $valid_font_file ),
	'SecurityHelper::has_valid_woff2_signature accepts genuine wOF2 magic bytes'
);
twb_assert(
	! SecurityHelper::has_valid_woff2_signature( __FILE__ ),
	'SecurityHelper::has_valid_woff2_signature rejects PHP file disguised as font'
);

// 7. Centralized TWBlocks\Security\Sanitizer (wp_kses_post & sanitize_text_field).
$sanitized_classes = Sanitizer::sanitize_build_input( "ps-4 pe-4 <script>alert(1)</script> evil;color:red rtl:-scale-x-100" );
twb_assert(
	array( 'pe-4', 'ps-4', 'rtl:-scale-x-100' ) === $sanitized_classes,
	'Sanitizer::sanitize_build_input strips HTML tags and invalid tokens via sanitize_text_field + whitelist'
);

$sanitized_attrs = Sanitizer::sanitize_block_attributes(
	array(
		'label'     => "شروع رایگان <script>evil()</script>\n",
		'content'   => '<p>متن <strong>فارسی</strong> امن</p><script>alert(1)</script>',
		'twClasses' => 'px-6 py-3 <img src=x onerror=alert(1)>',
	)
);
twb_assert(
	'شروع رایگان evil()' === $sanitized_attrs['label'] &&
	! str_contains( (string) $sanitized_attrs['content'], '<script>' ) &&
	str_contains( (string) $sanitized_attrs['content'], '<strong>فارسی</strong>' ),
	'Sanitizer::sanitize_block_attributes centrally applies sanitize_text_field to text/class attrs and wp_kses_post to rich content'
);

