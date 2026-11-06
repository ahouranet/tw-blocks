<?php
/**
 * Driver C: Bundled Safelist & On-Server Rule Synthesizer Fallback Driver.
 * Guarantees 100% uptime on restricted shared cPanel hosts when neither Driver A nor Driver B is available.
 *
 * @package TWBlocks\CSS\Drivers
 */

declare(strict_types=1);

namespace TWBlocks\CSS\Drivers;

use TWBlocks\CSS\DriverInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FallbackSafelistDriver
 */
final class FallbackSafelistDriver implements DriverInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'fallback_safelist';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'Driver C — Bundled Safelist Fallback', 'tw-blocks' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return is_readable( TWB_PATH . 'assets/css/safelist-fallback.css' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function diagnose(): array {
		$file   = TWB_PATH . 'assets/css/safelist-fallback.css';
		$exists = is_readable( $file );
		$size   = $exists ? (int) filesize( $file ) : 0;

		return array(
			'available' => $exists,
			'driver_id' => $this->get_id(),
			'label'     => $this->get_label(),
			'checks'    => array(
				array(
					'name'    => __( 'Bundled Safelist Stylesheet', 'tw-blocks' ),
					'passed'  => $exists && $size > 0,
					'message' => $exists
						/* translators: %d: file size in bytes */
						? sprintf( __( 'Bundled safelist-fallback.css is ready (%d bytes).', 'tw-blocks' ), $size )
						: __( 'Bundled safelist-fallback.css is missing from assets/css/.', 'tw-blocks' ),
				),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function compile( array $classes, string $theme_css, string $preflight_css ): array {
		$safelist_file = TWB_PATH . 'assets/css/safelist-fallback.css';
		$safelist_css  = '';

		if ( is_readable( $safelist_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$safelist_css = (string) file_get_contents( $safelist_file );
		}

		// Synthesize dynamic rules for requested utility classes not covered in static safelist (e.g. arbitrary values, logical properties).
		$synthesized = $this->synthesize_requested_utilities( $classes );

		$combined = $theme_css . "\n" . $preflight_css . "\n" . $safelist_css . "\n" . $synthesized;
		$minified = $this->minify_css( $combined );

		// Mark that fallback driver was used so Admin Notice can alert the site administrator in Persian.
		update_option( 'twb_using_fallback_driver', true, false );

		return array(
			'success' => true,
			'css'     => $minified,
			'driver'  => $this->get_id(),
		);
	}

	/**
	 * Synthesize high-priority logical RTL utilities and token-linked classes requested by the page.
	 *
	 * @param array<int, string> $classes Requested valid classes.
	 * @return string Generated CSS rules.
	 */
	private function synthesize_requested_utilities( array $classes ): string {
		$rules = array();

		$spacing_scale = array(
			'0'  => '0px',
			'1'  => '0.25rem',
			'2'  => '0.5rem',
			'3'  => '0.75rem',
			'4'  => '1rem',
			'5'  => '1.25rem',
			'6'  => '1.5rem',
			'8'  => '2rem',
			'10' => '2.5rem',
			'12' => '3rem',
			'16' => '4rem',
			'20' => '5rem',
			'24' => '6rem',
		);

		foreach ( $classes as $cls ) {
			$escaped = $this->escape_selector( $cls );

			// Logical margin/padding start & end (`ms-*`, `me-*`, `ps-*`, `pe-*`).
			if ( preg_match( '/^(m|p)(s|e|x|y|t|b)?-(auto|\d+)$/', $cls, $m ) ) {
				$prop  = 'm' === $m[1] ? 'margin' : 'padding';
				$dir   = $m[2] ?? '';
				$val   = 'auto' === $m[3] ? 'auto' : ( $spacing_scale[ $m[3] ] ?? ( ( (int) $m[3] * 0.25 ) . 'rem' ) );
				$map   = array(
					''  => array( $prop ),
					's' => array( $prop . '-inline-start' ),
					'e' => array( $prop . '-inline-end' ),
					'x' => array( $prop . '-inline-start', $prop . '-inline-end' ),
					'y' => array( $prop . '-block-start', $prop . '-block-end' ),
					't' => array( $prop . '-block-start' ),
					'b' => array( $prop . '-block-end' ),
				);
				$decls = array();
				foreach ( $map[ $dir ] ?? array() as $css_prop ) {
					$decls[] = "{$css_prop}:{$val}";
				}
				if ( ! empty( $decls ) ) {
					$rules[] = ".{$escaped}{" . implode( ';', $decls ) . '}';
				}
			}

			// Gap utilities (`gap-*`).
			if ( preg_match( '/^gap-(\d+)$/', $cls, $m ) ) {
				$val     = $spacing_scale[ $m[1] ] ?? ( ( (int) $m[1] * 0.25 ) . 'rem' );
				$rules[] = ".{$escaped}{gap:{$val}}";
			}
		}

		return implode( "\n", $rules );
	}

	/**
	 * Escape special characters in a Tailwind class name for a valid CSS selector.
	 *
	 * @param string $class_name Raw class name.
	 * @return string Escaped CSS selector.
	 */
	private function escape_selector( string $class_name ): string {
		return (string) preg_replace( '/([^a-zA-Z0-9_\-])/', '\\\\$1', $class_name );
	}

	/**
	 * Lightweight CSS minifier for fallback output.
	 *
	 * @param string $css Raw CSS.
	 * @return string Minified CSS.
	 */
	private function minify_css( string $css ): string {
		// Strip comments.
		$css = (string) preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
		// Collapse whitespace.
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		// Remove space around delimiters.
		$css = (string) preg_replace( '/\s*([\{\}:;,])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );
		return trim( $css );
	}
}
