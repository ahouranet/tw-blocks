<?php
/**
 * Gutenberg Block Registry (Block API v3), Global Tailwind Class Attribute Injector,
 * and Block Editor Inspector / Live Preview Asset Loader.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

namespace TWBlocks\Blocks;

use TWBlocks\CSS\ClassExtractor;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;
use TWBlocks\Security\Sanitizer;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockRegistry
 */
final class BlockRegistry {

	/**
	 * List of all Phase 2 blocks located in `tw-blocks/blocks/{slug}/`.
	 *
	 * @var array<int, string>
	 */
	public const BLOCK_SLUGS = array(
		'section',
		'grid',
		'heading',
		'text',
		'button',
		'image',
		'icon',
		'spacer',
		'divider',
		'faq',
		'tabs',
		'card',
		'icon-box',
		'post-grid',
		'custom-loop',
		'slider',
		'counter',
		'toc',
		'modal',
		'wc-sale-timer',
		'wc-recently-viewed',
	);

	/**
	 * TokenManager service.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * CssEngine service.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * Accumulated FAQ items on the current page for single deduplicated `FAQPage` JSON-LD output.
	 *
	 * @var array<int, array{question: string, answer: string}>
	 */
	private static array $faq_schema_items = array();

	/**
	 * Constructor.
	 *
	 * @param TokenManager $token_manager Token manager.
	 * @param CssEngine    $css_engine    CSS engine.
	 */
	public function __construct( TokenManager $token_manager, CssEngine $css_engine ) {
		$this->token_manager = $token_manager;
		$this->css_engine    = $css_engine;
	}

	/**
	 * Initialize hooks for block registration, category registration, global Tailwind attributes,
	 * centralized pre-render sanitization (`render_block_data`), and FAQ schema.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
		add_filter( 'register_block_type_args', array( $this, 'inject_global_tailwind_attributes' ), 10, 2 );
		add_filter( 'render_block_data', array( $this, 'sanitize_incoming_block_data' ), 5, 1 );
		add_action( 'init', array( $this, 'register_blocks' ), 15 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_inspector_assets' ) );
		add_action( 'wp_footer', array( $this, 'output_aggregated_faq_schema' ), 99 );
	}

	/**
	 * Centrally sanitize all incoming `tw-blocks/*` block attributes and inner HTML content
	 * via `TWBlocks\Security\Sanitizer` (`wp_kses_post` & `sanitize_text_field`) before rendering.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block array.
	 * @return array<string, mixed> Sanitized parsed block array.
	 */
	public function sanitize_incoming_block_data( array $parsed_block ): array {
		$block_name = isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		if ( ! str_starts_with( $block_name, 'tw-blocks/' ) ) {
			return $parsed_block;
		}

		if ( isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ) {
			$parsed_block['attrs'] = Sanitizer::sanitize_block_attributes( $parsed_block['attrs'] );
		}

		if ( isset( $parsed_block['innerHTML'] ) && is_string( $parsed_block['innerHTML'] ) ) {
			$parsed_block['innerHTML'] = Sanitizer::sanitize_block_content( $parsed_block['innerHTML'] );
		}

		if ( isset( $parsed_block['innerContent'] ) && is_array( $parsed_block['innerContent'] ) ) {
			foreach ( $parsed_block['innerContent'] as $idx => $chunk ) {
				if ( is_string( $chunk ) ) {
					$parsed_block['innerContent'][ $idx ] = Sanitizer::sanitize_block_content( $chunk );
				}
			}
		}

		return $parsed_block;
	}

	/**
	 * Register the custom "TW Blocks (تی‌دبلیو بلوکس)" category at the top of the Gutenberg inserter.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @param \WP_Block_Editor_Context         $context    Editor context.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_block_category( array $categories, $context ): array {
		unset( $context );
		array_unshift(
			$categories,
			array(
				'slug'  => 'tw-blocks',
				'title' => __( 'TW Blocks (Persian & Tailwind v4)', 'tw-blocks' ),
				'icon'  => 'layout',
			)
		);
		return $categories;
	}

	/**
	 * Inject global Tailwind Class control attributes (`twClasses`, `twResponsive`, `twStates`, `twSpacing`, `twTypography`)
	 * onto all registered blocks so the universal "Tailwind Class" control works across every block.
	 *
	 * @param array<string, mixed> $args       Block type registration arguments.
	 * @param string               $block_name Block name (e.g. `tw-blocks/section`).
	 * @return array<string, mixed>
	 */
	public function inject_global_tailwind_attributes( array $args, string $block_name ): array {
		unset( $block_name );
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		$global_tw_attrs = array(
			'twClasses'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'twResponsive' => array(
				'type'    => 'object',
				'default' => array(
					'sm' => '',
					'md' => '',
					'lg' => '',
					'xl' => '',
				),
			),
			'twStates'     => array(
				'type'    => 'object',
				'default' => array(
					'hover' => '',
					'focus' => '',
					'dark'  => '',
					'rtl'   => '',
				),
			),
		);

		$args['attributes'] = array_merge( $global_tw_attrs, $args['attributes'] );
		return $args;
	}

	/**
	 * Register all Phase 2 blocks from `blocks/{slug}/block.json`.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( self::BLOCK_SLUGS as $slug ) {
			$block_dir = TWB_PATH . 'blocks/' . $slug;
			if ( is_readable( $block_dir . '/block.json' ) ) {
				register_block_type_from_metadata( $block_dir );
			}
		}
	}

	/**
	 * Resolve and sanitize all Tailwind classes attached to a block's attributes (`className`, `twClasses`, `twResponsive`, `twStates`).
	 *
	 * @param array<string, mixed> $attributes   Block attributes array.
	 * @param string               $base_classes Base classes for the block.
	 * @return string Space-separated validated class string.
	 */
	public static function build_block_class_string( array $attributes, string $base_classes = '' ): string {
		$attributes = Sanitizer::sanitize_block_attributes( $attributes );
		$raw        = array( $base_classes );

		if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
			$raw[] = $attributes['className'];
		}
		if ( ! empty( $attributes['twClasses'] ) && is_string( $attributes['twClasses'] ) ) {
			$raw[] = $attributes['twClasses'];
		}

		foreach ( array( 'twResponsive', 'twStates' ) as $map_key ) {
			if ( ! empty( $attributes[ $map_key ] ) && is_array( $attributes[ $map_key ] ) ) {
				foreach ( $attributes[ $map_key ] as $prefix => $cls_str ) {
					if ( is_string( $cls_str ) && '' !== trim( $cls_str ) ) {
						$clean_prefix = sanitize_key( (string) $prefix );
						$tokens       = preg_split( '/\s+/', trim( $cls_str ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
						foreach ( $tokens as $token ) {
							$raw[] = str_contains( $token, ':' ) ? $token : $clean_prefix . ':' . $token;
						}
					}
				}
			}
		}

		$class_list = Sanitizer::sanitize_build_input( $raw );
		ClassExtractor::register_runtime_classes( $class_list );

		return implode( ' ', $class_list );
	}

	/**
	 * Queue a Question/Answer pair from a rendered `tw-blocks/faq` block for consolidated UTF-8 JSON-LD output.
	 *
	 * @param string $question Plain text question.
	 * @param string $answer   Clean answer text.
	 * @return void
	 */
	public static function add_faq_schema_item( string $question, string $answer ): void {
		$q = Sanitizer::clean_text_field( $question );
		$a = trim( Sanitizer::sanitize_block_content( $answer ) );
		if ( '' !== $q && '' !== $a ) {
			self::$faq_schema_items[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
	}

	/**
	 * Output a single deduplicated `<script type="application/ld+json">` FAQPage schema in `wp_footer`
	 * using `JSON_UNESCAPED_UNICODE` when one or more FAQ blocks with schema enabled exist on the page.
	 *
	 * @return void
	 */
	public function output_aggregated_faq_schema(): void {
		if ( empty( self::$faq_schema_items ) ) {
			return;
		}

		if ( class_exists( '\TWBlocks\WooCommerce\WooIntegration' ) && \TWBlocks\WooCommerce\WooIntegration::should_skip_duplicate_schema( 'FAQPage' ) ) {
			return;
		}

		$entities = array();
		foreach ( self::$faq_schema_items as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'inLanguage' => get_bloginfo( 'language' ) ?: 'fa-IR',
			'mainEntity' => $entities,
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped via JSON_HEX_TAG | JSON_HEX_AMP in PersianHelper::encode_json_ld
		echo PersianHelper::encode_json_ld( $schema );
	}

	/**
	 * Enqueue the Gutenberg Editor script (`build/editor/index.js`) providing:
	 * 1. Block edit UI for all Phase 2 TW Blocks
	 * 2. Universal "Tailwind Class" Inspector Control with autocomplete, per-breakpoint, per-state, and visual spacing/typography pickers
	 * 3. Debounced live CSS preview compiler injecting into the editor iframe (`editor-canvas`).
	 *
	 * @return void
	 */
	public function enqueue_editor_inspector_assets(): void {
		$asset_file = TWB_PATH . 'build/editor/index.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks', 'wp-i18n', 'wp-data', 'wp-api-fetch' ),
				'version'      => TWB_VERSION,
			);

		wp_enqueue_script(
			'tw-blocks-editor',
			TWB_URL . 'build/editor/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'tw-blocks-editor', 'tw-blocks', TWB_PATH . 'languages' );

		wp_localize_script(
			'tw-blocks-editor',
			'twbEditorConfig',
			array(
				'previewEndpoint' => esc_url_raw( rest_url( 'tw-blocks/v1/compile-preview' ) ),
				'restNonce'       => wp_create_nonce( 'wp_rest' ),
				'isRtl'           => is_rtl(),
				'siteLocale'      => get_locale(),
				'tokens'          => $this->token_manager->get_tokens(),
				'activeDriver'    => $this->css_engine->select_driver()->get_id(),
			)
		);
	}
}
