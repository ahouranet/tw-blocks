<?php
/**
 * Conditional Per-Page Asset Loader, Core Bloat Remover, and Block Editor Iframe CSS Synchronizer.
 *
 * @package TWBlocks\Frontend
 */

declare(strict_types=1);

namespace TWBlocks\Frontend;

use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;
use TWBlocks\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AssetLoader
 */
final class AssetLoader {

	/**
	 * CssEngine instance.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * TokenManager instance.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * CacheManager instance.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * Diagnostics payload for the current front-end page request (consumed by DebugBar).
	 *
	 * @var array<string, mixed>
	 */
	private array $current_page_metrics = array(
		'files'         => array(),
		'delivery_mode' => 'none',
		'total_bytes'   => 0,
		'class_count'   => 0,
		'driver'        => 'none',
		'build_time_ms' => 0.0,
		'cache_status'  => 'HIT',
	);

	/**
	 * Constructor.
	 *
	 * @param CssEngine    $css_engine    CSS Engine service.
	 * @param TokenManager $token_manager Token Manager service.
	 * @param CacheManager $cache_manager Cache Manager service.
	 */
	public function __construct(
		CssEngine $css_engine,
		TokenManager $token_manager,
		CacheManager $cache_manager
	) {
		$this->css_engine    = $css_engine;
		$this->token_manager = $token_manager;
		$this->cache_manager = $cache_manager;
	}

	/**
	 * Initialize frontend and block editor asset hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Force WordPress core to only load CSS for core blocks actually rendered on the page.
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		// Preload self-hosted Vazirmatn Variable woff2 font in <head>.
		add_action( 'wp_head', array( $this, 'preload_primary_font' ), 2 );

		// Enqueue or inline merged page CSS on the front-end.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_css' ), 20 );

		// Optionally strip unused WordPress global-styles and block-library if "Pure TW Blocks Mode" is active.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_dequeue_core_global_styles' ), 100 );

		// Inject identical tokens, Vazirmatn font, RTL direction, and compiled CSS into Gutenberg Editor iframe.
		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_iframe_styles' ), 20, 2 );

		// Register REST endpoints for live debounced editor CSS preview and environment testing.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Output `<link rel="preload">` for the primary local Persian variable font (`Vazirmatn-Variable.woff2`).
	 * Zero external CDN calls.
	 *
	 * @return void
	 */
	public function preload_primary_font(): void {
		$tokens = $this->token_manager->get_tokens();
		if ( empty( $tokens['typography']['preload_main_font'] ) ) {
			return;
		}

		$font_url = $this->token_manager->get_primary_font_url();
		if ( '' !== $font_url ) {
			printf(
				"<link rel=\"preload\" href=\"%s\" as=\"font\" type=\"font/woff2\" crossorigin=\"anonymous\" />\n",
				esc_url( $font_url )
			);
		}
	}

	/**
	 * Resolve `base` + `template` + `post` scopes, merge into a single stylesheet,
	 * and inline as `<style id="tw-blocks-inline-css">` if size <= threshold (default 12KB),
	 * or enqueue as a single cached `<link>` file otherwise.
	 *
	 * @return void
	 */
	public function enqueue_frontend_css(): void {
		if ( is_admin() ) {
			return;
		}

		$settings       = get_option( 'twb_settings', array() );
		$inline_kb      = isset( $settings['inline_threshold_kb'] ) ? max( 0, (float) $settings['inline_threshold_kb'] ) : 12.0;
		$inline_limit   = (int) round( $inline_kb * 1024 );

		$scopes_meta    = array();
		$css_chunks     = array();
		$total_classes  = array();
		$total_build_ms = 0.0;
		$any_miss       = false;
		$active_driver  = $this->css_engine->select_driver()->get_id();

		// 1. Base Scope (Tokens + Preflight-lite + Global Header/Footer + Safelist).
		$base_meta     = $this->css_engine->compile_base_scope();
		$scopes_meta[] = $base_meta;
		$base_css      = $this->cache_manager->get_contents( 'base', (string) ( $base_meta['hash'] ?? '' ) );
		if ( null !== $base_css ) {
			$css_chunks[] = $base_css;
		}

		// 2. Template Scope (e.g. single, page, archive, home).
		$tpl_slug      = $this->detect_current_template_slug();
		$tpl_meta      = $this->css_engine->compile_template_scope( $tpl_slug );
		$scopes_meta[] = $tpl_meta;
		$tpl_css       = $this->cache_manager->get_contents( 'tpl-' . $tpl_slug, (string) ( $tpl_meta['hash'] ?? '' ) );
		if ( null !== $tpl_css && '' !== trim( $tpl_css ) ) {
			$css_chunks[] = $tpl_css;
		}

		// 3. Per-Post/Page Scope (if viewing a singular post/page).
		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				$post_meta = $this->css_engine->compile_post_scope( $post_id );
				if ( ! empty( $post_meta ) ) {
					$scopes_meta[] = $post_meta;
					$post_css      = $this->cache_manager->get_contents( 'post-' . $post_id, (string) ( $post_meta['hash'] ?? '' ) );
					if ( null !== $post_css && '' !== trim( $post_css ) ) {
						$css_chunks[] = $post_css;
					}
				}
			}
		}

		$loaded_files = array();
		foreach ( $scopes_meta as $m ) {
			if ( ! empty( $m['filename'] ) ) {
				$loaded_files[] = (string) $m['filename'];
			}
			if ( ! empty( $m['classes'] ) && is_array( $m['classes'] ) ) {
				$total_classes = array_merge( $total_classes, $m['classes'] );
			}
			$total_build_ms += (float) ( $m['build_time_ms'] ?? 0.0 );
			if ( ( $m['cache_status'] ?? 'HIT' ) === 'MISS' ) {
				$any_miss = true;
			}
			if ( ! empty( $m['driver'] ) ) {
				$active_driver = (string) $m['driver'];
			}
		}

		$unique_classes = array_values( array_unique( $total_classes ) );
		$merged_css     = SecurityHelper::sanitize_inline_css( $this->deduplicate_merged_css( $css_chunks ) );
		$total_bytes    = strlen( $merged_css );

		// Decide: Inline <style> (< 12KB default) vs Single Merged <link> file.
		if ( $total_bytes <= $inline_limit ) {
			wp_register_style( 'tw-blocks-frontend', false, array(), TWB_VERSION );
			wp_enqueue_style( 'tw-blocks-frontend' );
			wp_add_inline_style( 'tw-blocks-frontend', $merged_css );
			$delivery_mode = 'inline';
		} else {
			$merged_hash = substr( hash( 'sha256', implode( '|', $loaded_files ) ), 0, 16 );
			if ( ! $this->cache_manager->has( 'merged', $merged_hash ) ) {
				$this->cache_manager->write_atomic( 'merged', $merged_hash, $merged_css );
			}
			$merged_filename = $this->cache_manager->get_filename( 'merged', $merged_hash );
			$merged_url      = $this->cache_manager->get_css_url() . '/' . $merged_filename;

			wp_enqueue_style(
				'tw-blocks-frontend',
				$merged_url,
				array(),
				null // Filename already contains content hash for immutable caching.
			);
			$loaded_files  = array( $merged_filename );
			$delivery_mode = 'file';
		}

		$this->current_page_metrics = array(
			'files'         => $loaded_files,
			'delivery_mode' => $delivery_mode,
			'total_bytes'   => $total_bytes,
			'class_count'   => count( $unique_classes ),
			'driver'        => $active_driver,
			'build_time_ms' => round( $total_build_ms, 2 ),
			'cache_status'  => $any_miss ? 'MISS' : 'HIT',
		);
	}

	/**
	 * Optionally dequeue WordPress `global-styles` and `wp-block-library` when site uses only TW Blocks.
	 *
	 * @return void
	 */
	public function maybe_dequeue_core_global_styles(): void {
		$settings = get_option( 'twb_settings', array() );

		if ( ! empty( $settings['disable_wp_global_styles'] ) ) {
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}

		if ( ! empty( $settings['disable_core_block_library'] ) ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
		}
	}

	/**
	 * Inject compiled CSS, Vazirmatn @font-face, and RTL rules directly into the Block Editor iframe
	 * so the editor WYSIWYG matches the frontend pixel-for-pixel.
	 *
	 * @param array<string, mixed>    $editor_settings Existing block editor settings.
	 * @param \WP_Block_Editor_Context $context         Block editor context.
	 * @return array<string, mixed>
	 */
	public function inject_editor_iframe_styles( array $editor_settings, $context ): array {
		$base_meta = $this->css_engine->compile_base_scope();
		$base_css  = (string) $this->cache_manager->get_contents( 'base', (string) ( $base_meta['hash'] ?? '' ) );

		$post_css = '';
		if ( isset( $context->post ) && $context->post instanceof \WP_Post ) {
			$post_meta = $this->css_engine->compile_post_scope( $context->post );
			$post_css  = (string) $this->cache_manager->get_contents( 'post-' . $context->post->ID, (string) ( $post_meta['hash'] ?? '' ) );
		}

		$is_rtl  = is_rtl();
		$dir_css = sprintf(
			'.editor-styles-wrapper { direction: %s; font-family: var(--twb-font-sans, Vazirmatn, Tahoma, system-ui, sans-serif); line-height: var(--twb-line-height-body, 1.8); letter-spacing: 0em; }',
			$is_rtl ? 'rtl' : 'ltr'
		);

		if ( ! isset( $editor_settings['styles'] ) || ! is_array( $editor_settings['styles'] ) ) {
			$editor_settings['styles'] = array();
		}

		$editor_settings['styles'][] = array(
			'css'            => SecurityHelper::sanitize_inline_css( $base_css . "\n" . $post_css . "\n" . $dir_css ),
			'__unstableType' => 'plugin',
			'isGlobalStyles' => false,
		);

		return $editor_settings;
	}

	/**
	 * Register REST API routes for debounced Editor Live Preview compilation and Admin Environment Testing.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'tw-blocks/v1',
			'/compile-preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => array( $this, 'rest_compile_preview' ),
				'args'                => array(
					'classes' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ): bool {
							return is_array( $param ) || is_string( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'tw-blocks/v1',
			'/test-env',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' ) || current_user_can( 'edit_theme_options' );
				},
				'callback'            => array( $this, 'rest_test_environment' ),
			)
		);
	}

	/**
	 * REST Callback: Compile utility classes for live Gutenberg editor preview (debounced on client).
	 * Enforces transient rate-limiting (max 45 requests/min per user) and caps class payload at 1,000 tokens.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_compile_preview( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! SecurityHelper::check_rest_rate_limit( 'compile_preview', 45, 60 ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => 'rate_limit_exceeded',
					'message' => __( 'Too many preview compile requests. Please wait a moment.', 'tw-blocks' ),
				),
				429
			);
		}

		$raw_classes = $request->get_param( 'classes' );
		if ( is_array( $raw_classes ) && count( $raw_classes ) > 1000 ) {
			$raw_classes = array_slice( $raw_classes, 0, 1000 );
		} elseif ( is_string( $raw_classes ) && strlen( $raw_classes ) > 32768 ) {
			$raw_classes = substr( $raw_classes, 0, 32768 );
		}

		$result        = $this->css_engine->compile_editor_preview( is_array( $raw_classes ) || is_string( $raw_classes ) ? $raw_classes : array() );
		$result['css'] = SecurityHelper::sanitize_inline_css( (string) ( $result['css'] ?? '' ) );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * REST Callback: Run full environment diagnostics for Driver A / B / C.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_test_environment( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		if ( ! SecurityHelper::check_rest_rate_limit( 'test_env', 15, 60 ) ) {
			return new \WP_REST_Response(
				array(
					'error'   => 'rate_limit_exceeded',
					'message' => __( 'Too many environment test requests. Please wait a moment.', 'tw-blocks' ),
				),
				429
			);
		}
		return new \WP_REST_Response( $this->css_engine->test_environment(), 200 );
	}

	/**
	 * Get current page CSS diagnostics for DebugBar.
	 *
	 * @return array<string, mixed>
	 */
	public function get_current_page_metrics(): array {
		return $this->current_page_metrics;
	}

	/**
	 * Detect active template slug for the current query.
	 *
	 * @return string
	 */
	private function detect_current_template_slug(): string {
		if ( is_front_page() ) {
			return 'front-page';
		}
		if ( is_home() ) {
			return 'home';
		}
		if ( is_page() ) {
			return 'page';
		}
		if ( is_single() ) {
			return 'single';
		}
		if ( is_archive() ) {
			return 'archive';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_404() ) {
			return '404';
		}
		return 'index';
	}

	/**
	 * Merge CSS chunks from `base`, `template`, and `post` scopes without duplicating `@font-face` or `@theme` blocks.
	 *
	 * @param array<int, string> $chunks Array of CSS strings.
	 * @return string
	 */
	private function deduplicate_merged_css( array $chunks ): string {
		if ( empty( $chunks ) ) {
			return '';
		}

		$primary = array_shift( $chunks );
		foreach ( $chunks as $chunk ) {
			// Strip repeated :root and @font-face declarations already present in the base chunk.
			$cleaned  = (string) preg_replace( '/@font-face\s*\{[^}]*\}/s', '', $chunk );
			$cleaned  = (string) preg_replace( '/:root\s*\{[^}]*\}/s', '', $cleaned );
			$primary .= "\n" . trim( $cleaned );
		}

		return trim( $primary );
	}
}
