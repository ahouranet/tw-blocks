<?php
/**
 * Block Pattern Registry (Phase 3).
 * Registers the `tw-blocks-patterns` category and loads all Persian RTL-first block patterns from `patterns/*.php`.
 *
 * @package TWBlocks\Patterns
 */

declare(strict_types=1);

namespace TWBlocks\Patterns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PatternRegistry
 */
final class PatternRegistry {

	/**
	 * Ordered list of pattern slugs under `patterns/`.
	 */
	public const PATTERN_SLUGS = array(
		'hero-persian',
		'features-grid',
		'pricing-jalali',
		'faq-section',
		'cta-banner',
		'blog-showcase',
		'testimonial-grid',
		'wc-benefits-bar',
		'wc-product-slider',
	);

	/**
	 * Initialize block pattern registration on `init`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_patterns' ), 20 );
	}

	/**
	 * Register pattern categories and all bundled Persian block patterns.
	 *
	 * @return void
	 */
	public function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'tw-blocks-patterns',
			array(
				'label'       => __( 'TW Blocks — Persian Patterns (RTL)', 'tw-blocks' ),
				'description' => __( 'Ready-to-use, SEO-first Persian block patterns styled with Tailwind CSS v4 logical properties.', 'tw-blocks' ),
			)
		);

		foreach ( self::PATTERN_SLUGS as $slug ) {
			$file = TWB_PATH . 'patterns/' . $slug . '.php';
			if ( ! is_readable( $file ) ) {
				continue;
			}

			$pattern_data = require $file;
			if ( is_array( $pattern_data ) && ! empty( $pattern_data['title'] ) && ! empty( $pattern_data['content'] ) ) {
				register_block_pattern( 'tw-blocks/' . $slug, $pattern_data );
			}
		}
	}

	/**
	 * Retrieve all pattern definitions for export or inspection.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_patterns(): array {
		$patterns = array();
		foreach ( self::PATTERN_SLUGS as $slug ) {
			$file = TWB_PATH . 'patterns/' . $slug . '.php';
			if ( is_readable( $file ) ) {
				$data = require $file;
				if ( is_array( $data ) ) {
					$patterns[ 'tw-blocks/' . $slug ] = $data;
				}
			}
		}
		return $patterns;
	}
}
