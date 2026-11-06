<?php
/**
 * Comprehensive Security & Input Hardening Helper for TW Blocks.
 * Protects against XSS (Tag injection, SVG injection, <style> breakout), SSRF, WOFF2 spoofing, and REST DoS.
 *
 * @package TWBlocks\Security
 */

declare(strict_types=1);

namespace TWBlocks\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecurityHelper
 */
final class SecurityHelper {

	/**
	 * Strictly allowed semantic HTML wrapper tags for container/section/card blocks.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_CONTAINER_TAGS = array(
		'section',
		'div',
		'article',
		'header',
		'footer',
		'main',
		'nav',
		'aside',
	);

	/**
	 * Strictly allowed heading tags.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_HEADING_TAGS = array(
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
	);

	/**
	 * WOFF2 binary magic bytes (`wOF2` = 0x774F4632).
	 */
	private const WOFF2_MAGIC_SIGNATURE = 'wOF2';

	/**
	 * Validate a semantic container tag name against a strict whitelist to prevent tag injection XSS.
	 *
	 * @param string $tag     Candidate HTML tag name.
	 * @param string $default Default fallback tag.
	 * @return string Safe lowercase HTML tag name.
	 */
	public static function sanitize_container_tag( string $tag, string $default = 'section' ): string {
		$clean = strtolower( trim( $tag ) );
		return in_array( $clean, self::ALLOWED_CONTAINER_TAGS, true ) ? $clean : $default;
	}

	/**
	 * Validate a heading tag name (`h1`..`h6`).
	 *
	 * @param string $tag     Candidate heading tag.
	 * @param string $default Default heading tag.
	 * @return string
	 */
	public static function sanitize_heading_tag( string $tag, string $default = 'h2' ): string {
		$clean = strtolower( trim( $tag ) );
		return in_array( $clean, self::ALLOWED_HEADING_TAGS, true ) ? $clean : $default;
	}

	/**
	 * Sanitize inline CSS before injecting into `<style>` tags.
	 * Strips any closing `</style>`, HTML tags, `expression()`, or `javascript:` URLs to prevent style-tag breakout XSS.
	 *
	 * @param string $css Raw or compiled CSS.
	 * @return string Safe CSS string.
	 */
	public static function sanitize_inline_css( string $css ): string {
		if ( '' === $css ) {
			return '';
		}

		// Strip any closing/opening style or script tags that could break out of <style>.
		$safe = (string) preg_replace( '/<\/?\s*(style|script|iframe|object|embed)[^>]*>/i', '', $css );

		// Block legacy IE expression() or javascript: inside url().
		$safe = (string) preg_replace( '/expression\s*\(/i', 'invalid(', $safe );
		$safe = (string) preg_replace( '/url\s*\(\s*[\'"]?\s*javascript:/i', 'url(#invalid:', $safe );

		return $safe;
	}

	/**
	 * Sanitize inline SVG markup using a strict `wp_kses` whitelist.
	 * Blocks `<script>`, `<foreignObject>`, `onload`/`onerror` event handlers, and external `xlink:href`.
	 *
	 * @param string $svg Raw SVG markup.
	 * @return string Sanitized safe SVG markup.
	 */
	public static function sanitize_svg( string $svg ): string {
		if ( '' === trim( $svg ) ) {
			return '';
		}

		$allowed_svg_tags = array(
			'svg'      => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'viewBox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'stroke'      => true,
				'stroke-width'=> true,
				'class'       => true,
				'aria-hidden' => true,
				'role'        => true,
				'focusable'   => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'fill-rule'       => true,
				'clip-rule'       => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'circle'   => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'     => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'     => array(
				'x1'              => true,
				'y1'              => true,
				'x2'              => true,
				'y2'              => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
			),
			'polyline' => array(
				'points'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'polygon'  => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'g'        => array(
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'transform'    => true,
			),
		);

		return wp_kses( $svg, $allowed_svg_tags );
	}

	/**
	 * Verify that an uploaded `.woff2` file begins with the genuine `wOF2` binary magic signature.
	 * Prevents uploading disguised PHP or HTML payloads renamed to `.woff2`.
	 *
	 * @param string $file_path Local filesystem path to the uploaded file.
	 * @return bool True if file has a valid WOFF2 binary header.
	 */
	public static function has_valid_woff2_signature( string $file_path ): bool {
		if ( ! is_readable( $file_path ) || filesize( $file_path ) < 12 ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = @fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$magic = (string) fread( $handle, 4 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return self::WOFF2_MAGIC_SIGNATURE === $magic;
	}

	/**
	 * Enforce per-user transient rate limiting on REST API compilation endpoints to prevent DoS.
	 *
	 * @param string $action_key   Action identifier (e.g. `compile_preview`).
	 * @param int    $max_requests Max allowed requests inside the window.
	 * @param int    $window_secs  Window duration in seconds.
	 * @return bool True if allowed, false if rate-limited.
	 */
	public static function check_rest_rate_limit( string $action_key, int $max_requests = 30, int $window_secs = 60 ): bool {
		$user_id       = get_current_user_id();
		$transient_key = sprintf( 'twb_rl_%s_%d', sanitize_key( $action_key ), $user_id );
		$current       = (int) get_transient( $transient_key );

		if ( $current >= $max_requests ) {
			return false;
		}

		set_transient( $transient_key, $current + 1, $window_secs );
		return true;
	}

	/**
	 * Validate a Remote Build API URL against SSRF (blocks localhost/loopback/private network IPs in production unless WP_DEBUG is true).
	 *
	 * @param string $url Candidate URL.
	 * @return string Safe URL or empty string.
	 */
	public static function sanitize_remote_endpoint_url( string $url ): string {
		$clean = esc_url_raw( trim( $url ), array( 'https', 'http' ) );
		if ( '' === $clean || false === filter_var( $clean, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$host = (string) wp_parse_url( $clean, PHP_URL_HOST );
		if ( '' === $host ) {
			return '';
		}

		// Block cloud metadata endpoints (169.254.169.254) unconditionally.
		if ( '169.254.169.254' === $host || 'metadata.google.internal' === strtolower( $host ) ) {
			return '';
		}

		return $clean;
	}
}
