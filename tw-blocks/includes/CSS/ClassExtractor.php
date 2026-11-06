<?php
/**
 * Tailwind Utility Class Extractor & Validator.
 * Extracts classes from block attributes, rendered markup, templates, block definitions, and safelist.
 *
 * @package TWBlocks\CSS
 */

declare(strict_types=1);

namespace TWBlocks\CSS;

use TWBlocks\Security\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClassExtractor
 */
final class ClassExtractor {

	/**
	 * Strict whitelist regex for valid Tailwind CSS v4 class names.
	 * Allows standard utilities, responsive/state prefixes (md:, hover:, rtl:), arbitrary values (w-[120px]),
	 * opacity modifiers (bg-teal-600/80), and negative/important prefixes.
	 * Blocks quotes, semicolons, braces, and HTML tags to prevent CSS/command injection.
	 */
	public const CLASS_REGEX = '/^!?-?[a-zA-Z0-9_]+(?:[:\-][a-zA-Z0-9_\-\.\/%\[\]#,\(\)\+\*]+)*$/';

	/**
	 * Maximum length of a single Tailwind class token.
	 */
	private const MAX_CLASS_LENGTH = 120;

	/**
	 * Runtime-registered dynamic classes via `twb_register_dynamic_classes()`.
	 *
	 * @var array<string, bool>
	 */
	private static array $runtime_classes = array();

	/**
	 * TokenManager instance.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * Constructor.
	 *
	 * @param TokenManager $token_manager TokenManager service.
	 */
	public function __construct( TokenManager $token_manager ) {
		$this->token_manager = $token_manager;
	}

	/**
	 * Register dynamic classes at runtime from block render scripts.
	 *
	 * @param array<int, string>|string $classes Classes to register.
	 * @return void
	 */
	public static function register_runtime_classes( array|string $classes ): void {
		$sanitized = Sanitizer::sanitize_build_input( $classes );
		foreach ( $sanitized as $clean ) {
			self::$runtime_classes[ $clean ] = true;
		}
	}

	/**
	 * Validate a single candidate class name against the strict regex whitelist.
	 *
	 * @param string $candidate Class token.
	 * @return bool
	 */
	public static function is_valid_class( string $candidate ): bool {
		$len = strlen( $candidate );
		if ( $len < 1 || $len > self::MAX_CLASS_LENGTH ) {
			return false;
		}
		// Reject pure numbers or URLs/protocols.
		if ( is_numeric( $candidate ) || str_contains( $candidate, '://' ) || str_contains( $candidate, '..' ) ) {
			return false;
		}
		return (bool) preg_match( self::CLASS_REGEX, $candidate );
	}

	/**
	 * Normalize, validate, deduplicate, and alphabetically sort an array or string of classes
	 * via centralized `TWBlocks\Security\Sanitizer::sanitize_build_input()`.
	 *
	 * @param array<int, string>|string $raw_classes Raw classes list.
	 * @return array<int, string> Sorted unique valid classes.
	 */
	public function normalize_and_validate( array|string $raw_classes ): array {
		return Sanitizer::sanitize_build_input( $raw_classes );
	}

	/**
	 * Compute deterministic hash for a sorted list of classes + current design tokens hash.
	 *
	 * @param array<int, string> $classes Sorted unique classes.
	 * @param string             $scope   Scope identifier (base, template, post).
	 * @return string 16-char SHA-256 hash.
	 */
	public function compute_hash( array $classes, string $scope = 'post' ): string {
		$payload = $scope . '|' . $this->token_manager->get_tokens_hash() . '|' . implode( ' ', $classes );
		return substr( hash( 'sha256', $payload ), 0, 16 );
	}

	/**
	 * Extract all Tailwind classes for the global `base` scope:
	 * - Global header/footer template parts
	 * - Plugin's own block templates (`blocks/* /render.php`, `block.json`)
	 * - User-configured safelist in settings
	 *
	 * @return array{classes: array<int, string>, hash: string}
	 */
	public function extract_for_base_scope(): array {
		$collected = array(
			'twb-root',
			'rtl:space-x-reverse',
			'ms-auto',
			'me-auto',
			'ps-4',
			'pe-4',
			'text-start',
			'text-end',
		);

		// 1. User safelist from settings.
		$settings = get_option( 'twb_settings', array() );
		if ( ! empty( $settings['safelist'] ) && is_string( $settings['safelist'] ) ) {
			$collected = array_merge( $collected, preg_split( '/[\s,]+/', $settings['safelist'], -1, PREG_SPLIT_NO_EMPTY ) ?: array() );
		}

		// 2. Global template parts (header, footer) if block theme is active.
		if ( function_exists( 'get_block_templates' ) ) {
			$parts = get_block_templates( array( 'area' => array( 'header', 'footer' ) ), 'wp_template_part' );
			foreach ( $parts as $part ) {
				if ( ! empty( $part->content ) ) {
					$collected = array_merge( $collected, $this->extract_from_block_content( (string) $part->content ) );
				}
			}
		}

		// 3. Scan plugin's own block templates in `blocks/` directory if present.
		$collected = array_merge( $collected, $this->extract_from_plugin_blocks_dir() );

		$normalized = $this->normalize_and_validate( $collected );
		return array(
			'classes' => $normalized,
			'hash'    => $this->compute_hash( $normalized, 'base' ),
		);
	}

	/**
	 * Extract all Tailwind classes for a specific WordPress block template (e.g., `single`, `archive`, `page`).
	 *
	 * @param string $template_slug Template slug.
	 * @return array{classes: array<int, string>, hash: string}
	 */
	public function extract_for_template_scope( string $template_slug ): array {
		$collected = array();

		if ( function_exists( 'get_block_templates' ) ) {
			$templates = get_block_templates( array( 'slug__in' => array( $template_slug ) ), 'wp_template' );
			foreach ( $templates as $template ) {
				if ( ! empty( $template->content ) ) {
					$collected = array_merge( $collected, $this->extract_from_block_content( (string) $template->content ) );
				}
			}
		}

		$normalized = $this->normalize_and_validate( $collected );
		return array(
			'classes' => $normalized,
			'hash'    => $this->compute_hash( $normalized, 'tpl-' . $template_slug ),
		);
	}

	/**
	 * Extract all Tailwind classes for a specific Post/Page:
	 * - Recursive block attribute inspection (`className`, `twClasses`, `twResponsive`, `twStates`)
	 * - Rendered block HTML (`parse_blocks` + `render_block`)
	 * - Runtime dynamic classes registered during render
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return array{classes: array<int, string>, hash: string}
	 */
	public function extract_for_post_scope( int|\WP_Post $post ): array {
		$post_obj = is_int( $post ) ? get_post( $post ) : $post;
		if ( ! $post_obj instanceof \WP_Post ) {
			return array(
				'classes' => array(),
				'hash'    => $this->compute_hash( array(), 'post-0' ),
			);
		}

		self::$runtime_classes = array();
		$content               = (string) $post_obj->post_content;
		$collected             = $this->extract_from_block_content( $content );

		if ( ! empty( self::$runtime_classes ) ) {
			$collected = array_merge( $collected, array_keys( self::$runtime_classes ) );
		}

		$normalized = $this->normalize_and_validate( $collected );
		return array(
			'classes' => $normalized,
			'hash'    => $this->compute_hash( $normalized, 'post-' . $post_obj->ID ),
		);
	}

	/**
	 * Parse Gutenberg block content, inspect block attributes recursively, and extract classes from rendered HTML.
	 *
	 * @param string $content Raw post_content or template content.
	 * @return array<int, string>
	 */
	public function extract_from_block_content( string $content ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$classes = array();

		if ( function_exists( 'parse_blocks' ) ) {
			$blocks  = parse_blocks( $content );
			$classes = array_merge( $classes, $this->extract_from_parsed_blocks( $blocks ) );

			// Also render blocks to capture classes generated by dynamic server-side `render.php`.
			if ( function_exists( 'render_block' ) ) {
				foreach ( $blocks as $block ) {
					if ( ! empty( $block['blockName'] ) ) {
						$rendered = render_block( $block );
						$classes  = array_merge( $classes, $this->extract_from_html_markup( Sanitizer::sanitize_block_content( $rendered ) ) );
					}
				}
			}
		}

		// Also extract directly from sanitized HTML in content.
		$classes = array_merge( $classes, $this->extract_from_html_markup( Sanitizer::sanitize_block_content( $content ) ) );

		return $classes;
	}

	/**
	 * Recursively extract Tailwind class attributes from parsed Gutenberg blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return array<int, string>
	 */
	private function extract_from_parsed_blocks( array $blocks ): array {
		$found = array();

		foreach ( $blocks as $block ) {
			$raw_attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$attrs     = Sanitizer::sanitize_block_attributes( $raw_attrs );

			// Standard and TW Blocks custom class attributes.
			$attr_keys = array(
				'className',
				'twClasses',
				'tw SpacingClasses',
				'twTypographyClasses',
				'containerClass',
				'gridClass',
			);

			foreach ( $attr_keys as $key ) {
				if ( ! empty( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
					$found[] = $attrs[ $key ];
				}
			}

			// Structured per-breakpoint or per-state class maps (e.g. { sm: "grid-cols-2", md: "grid-cols-3", hover: "bg-teal-700" }).
			foreach ( array( 'twResponsive', 'twStates' ) as $map_key ) {
				if ( ! empty( $attrs[ $map_key ] ) && is_array( $attrs[ $map_key ] ) ) {
					foreach ( $attrs[ $map_key ] as $prefix => $cls_list ) {
						if ( is_string( $cls_list ) && '' !== trim( $cls_list ) ) {
							$tokens = preg_split( '/\s+/', trim( $cls_list ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
							foreach ( $tokens as $t ) {
								$found[] = str_contains( $t, ':' ) ? $t : sanitize_key( (string) $prefix ) . ':' . $t;
							}
						}
					}
				}
			}

			// Resolve synced patterns (core/block with ref).
			if ( 'core/block' === ( $block['blockName'] ?? '' ) && ! empty( $attrs['ref'] ) ) {
				$reusable_post = get_post( (int) $attrs['ref'] );
				if ( $reusable_post instanceof \WP_Post ) {
					$found = array_merge( $found, $this->extract_from_block_content( (string) $reusable_post->post_content ) );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, $this->extract_from_parsed_blocks( $block['innerBlocks'] ) );
			}
		}

		return $found;
	}

	/**
	 * Extract all `class="..."` attribute values from HTML markup.
	 *
	 * @param string $html HTML string.
	 * @return array<int, string>
	 */
	public function extract_from_html_markup( string $html ): array {
		if ( '' === $html ) {
			return array();
		}

		$found = array();
		if ( preg_match_all( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
			foreach ( $matches[2] as $class_string ) {
				$tokens = preg_split( '/\s+/', trim( $class_string ), -1, PREG_SPLIT_NO_EMPTY );
				if ( is_array( $tokens ) ) {
					$found = array_merge( $found, $tokens );
				}
			}
		}

		return $found;
	}

	/**
	 * Scan the plugin's `blocks/` (`render.php` and `block.json`) and `patterns/` (`*.php`) directories
	 * for static class names without relying on non-portable `GLOB_BRACE`.
	 *
	 * @return array<int, string>
	 */
	private function extract_from_plugin_blocks_dir(): array {
		$found = array();

		$render_files   = glob( TWB_PATH . 'blocks/*/render.php' ) ?: array();
		$json_files     = glob( TWB_PATH . 'blocks/*/block.json' ) ?: array();
		$pattern_files  = glob( TWB_PATH . 'patterns/*.php' ) ?: array();
		$template_files = glob( TWB_PATH . 'templates/*.html' ) ?: array();

		$files = array_merge( $render_files, $json_files, $pattern_files, $template_files );

		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || ! is_readable( $file ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = (string) file_get_contents( $file );
			$found    = array_merge( $found, $this->extract_from_html_markup( $contents ) );

			// Also capture classes inside block.json defaults or Gutenberg block comment JSON (`"twClasses":"..."`).
			if ( preg_match_all( '/"(?:twClasses|className|default)"\s*:\s*"([^"]+)"/', $contents, $json_matches ) ) {
				foreach ( $json_matches[1] as $raw_str ) {
					$tokens = preg_split( '/\s+/', trim( $raw_str ), -1, PREG_SPLIT_NO_EMPTY );
					if ( is_array( $tokens ) ) {
						$found = array_merge( $found, $tokens );
					}
				}
			}
		}

		return $found;
	}
}
