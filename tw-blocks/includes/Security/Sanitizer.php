<?php
/**
 * Centralized Input & Content Sanitizer for TW Blocks.
 *
 * Uses `wp_kses_post()` and `sanitize_text_field()` centrally to sanitize
 * all CSS build inputs, class lists, and Gutenberg block attributes/content
 * prior to extraction, compilation, or server-side rendering.
 *
 * @package TWBlocks\Security
 */

declare(strict_types=1);

namespace TWBlocks\Security;

use TWBlocks\CSS\ClassExtractor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitizer
 */
final class Sanitizer {

	/**
	 * Block attribute keys that contain rich HTML content and must be sanitized with `wp_kses_post()`.
	 * All other string attributes are sanitized via `sanitize_text_field()`.
	 *
	 * @var array<int, string>
	 */
	private const RICH_TEXT_ATTRIBUTE_KEYS = array(
		'content',
		'text',
		'description',
		'answer',
		'caption',
		'subtitle',
		'body',
		'excerpt',
	);

	/**
	 * Centrally sanitize all CSS build inputs (class tokens, safelist strings, or arrays of classes)
	 * using `sanitize_text_field()` and strict token validation before CSS extraction/compilation.
	 *
	 * @param array<int, mixed>|string $raw_input Raw class string or array of class tokens.
	 * @return array<int, string> Sanitized, deduplicated, and validated Tailwind class tokens.
	 */
	public static function sanitize_build_input( array|string $raw_input ): array {
		$items = is_array( $raw_input ) ? $raw_input : array( $raw_input );
		$clean = array();

		foreach ( $items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			// Strip HTML tags, octets, line breaks, and extra whitespace via sanitize_text_field().
			$sanitized_line = self::clean_text_field( (string) $item );
			if ( '' === $sanitized_line ) {
				continue;
			}

			$tokens = preg_split( '/[\s,]+/', $sanitized_line, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $tokens ) ) {
				continue;
			}

			foreach ( $tokens as $token ) {
				$candidate = self::clean_text_field( $token );
				if ( '' !== $candidate && ClassExtractor::is_valid_class( $candidate ) ) {
					$clean[ $candidate ] = true;
				}
			}
		}

		$result = array_keys( $clean );
		sort( $result, SORT_STRING );

		return $result;
	}

	/**
	 * Centrally sanitize raw block HTML content using `wp_kses_post()` before processing or rendering.
	 *
	 * @param string $content Raw block HTML or RichText string.
	 * @return string Sanitized HTML safe for post output and class extraction.
	 */
	public static function sanitize_block_content( string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}

		// Remove null bytes before running wp_kses_post.
		$no_nulls = str_replace( "\0", '', $content );

		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $no_nulls );
		}

		// Standalone CLI fallback when full WordPress kses environment is not loaded.
		return (string) preg_replace( '/<\/?\s*(script|iframe|object|embed|style|meta|link|base)[^>]*>/i', '', $no_nulls );
	}

	/**
	 * Centrally sanitize a Gutenberg block's attributes array recursively before processing.
	 * Uses `wp_kses_post()` for rich-text content fields and `sanitize_text_field()` for plain text and class fields.
	 *
	 * @param array<string, mixed> $attributes     Raw block attributes.
	 * @param array<int, string>   $rich_text_keys Optional override of attribute keys treated as rich HTML.
	 * @return array<string, mixed> Sanitized block attributes.
	 */
	public static function sanitize_block_attributes( array $attributes, array $rich_text_keys = self::RICH_TEXT_ATTRIBUTE_KEYS ): array {
		$sanitized = array();

		foreach ( $attributes as $key => $value ) {
			$clean_key = is_string( $key ) ? self::clean_text_field( $key ) : $key;

			if ( is_string( $value ) ) {
				if ( 'svgMarkup' === $clean_key ) {
					$sanitized[ $clean_key ] = SecurityHelper::sanitize_svg( $value );
				} elseif ( in_array( (string) $clean_key, $rich_text_keys, true ) ) {
					$sanitized[ $clean_key ] = self::sanitize_block_content( $value );
				} else {
					$sanitized[ $clean_key ] = self::clean_text_field( $value );
				}
			} elseif ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = self::sanitize_block_attributes( $value, $rich_text_keys );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$sanitized[ $clean_key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a complete CSS build payload (scope, classes, theme CSS, and preflight CSS)
	 * before passing it to Driver A, Driver B, or Driver C.
	 *
	 * @param array{scope?: string, classes?: array<int, mixed>|string, theme_css?: string, preflight_css?: string} $payload Raw build payload.
	 * @return array{scope: string, classes: array<int, string>, theme_css: string, preflight_css: string}
	 */
	public static function sanitize_css_build_payload( array $payload ): array {
		$raw_scope = isset( $payload['scope'] ) && is_string( $payload['scope'] ) ? $payload['scope'] : 'base';
		$scope     = (string) preg_replace( '/[^a-zA-Z0-9\-_]/', '', self::clean_text_field( $raw_scope ) );
		if ( '' === $scope ) {
			$scope = 'base';
		}

		$raw_classes   = $payload['classes'] ?? array();
		$clean_classes = self::sanitize_build_input( is_array( $raw_classes ) || is_string( $raw_classes ) ? $raw_classes : array() );

		$theme_css     = isset( $payload['theme_css'] ) && is_string( $payload['theme_css'] )
			? SecurityHelper::sanitize_inline_css( $payload['theme_css'] )
			: '';
		$preflight_css = isset( $payload['preflight_css'] ) && is_string( $payload['preflight_css'] )
			? SecurityHelper::sanitize_inline_css( $payload['preflight_css'] )
			: '';

		return array(
			'scope'         => $scope,
			'classes'       => $clean_classes,
			'theme_css'     => $theme_css,
			'preflight_css' => $preflight_css,
		);
	}

	/**
	 * Wrapper around WordPress `sanitize_text_field()` with safe standalone fallback for CLI unit tests.
	 *
	 * @param string $text Raw string input.
	 * @return string Sanitized single-line plain text.
	 */
	public static function clean_text_field( string $text ): string {
		$no_nulls = str_replace( "\0", '', $text );

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $no_nulls );
		}

		// Standalone CLI fallback matching WordPress sanitize_text_field() behavior.
		$filtered = strip_tags( $no_nulls );
		$filtered = (string) preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
		return trim( $filtered );
	}
}
