<?php
/**
 * Persian, RTL, Digit Localization, and Jalali Calendar Helper.
 *
 * @package TWBlocks\Support
 */

declare(strict_types=1);

namespace TWBlocks\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PersianHelper
 */
final class PersianHelper {

	/**
	 * Latin digits.
	 *
	 * @var array<int, string>
	 */
	private const LATIN_DIGITS = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	/**
	 * Persian digits.
	 *
	 * @var array<int, string>
	 */
	private const PERSIAN_DIGITS = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	/**
	 * Arabic-Indic digits.
	 *
	 * @var array<int, string>
	 */
	private const ARABIC_DIGITS = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );

	/**
	 * Persian Jalali month names.
	 *
	 * @var array<int, string>
	 */
	private const JALALI_MONTHS = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * Register filters if automatic dynamic digit localization is enabled.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'render_block', array( self::class, 'filter_block_output' ), 20, 2 );
	}

	/**
	 * Convert Latin and Arabic-Indic digits to Persian digits (۰-۹) safely without breaking HTML attributes or tags.
	 *
	 * @param string|int|float $input Input string or number.
	 * @param bool             $force Whether to bypass the settings check.
	 * @return string
	 */
	public static function localize_digits( string|int|float $input, bool $force = false ): string {
		$text = (string) $input;
		if ( '' === $text ) {
			return '';
		}

		if ( ! $force ) {
			$settings = get_option( 'twb_settings', array() );
			if ( empty( $settings['enable_persian_digits'] ) ) {
				return $text;
			}
		}

		// Split by HTML tags so we never corrupt attributes, URLs, or style properties.
		$parts = preg_split( '/(<[^>]+>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return str_replace(
				array_merge( self::LATIN_DIGITS, self::ARABIC_DIGITS ),
				array_merge( self::PERSIAN_DIGITS, self::PERSIAN_DIGITS ),
				$text
			);
		}

		$result = '';
		$in_code_or_script = false;

		foreach ( $parts as $part ) {
			if ( str_starts_with( $part, '<' ) ) {
				if ( preg_match( '/^<(script|style|code|pre)\b/i', $part ) ) {
					$in_code_or_script = true;
				} elseif ( preg_match( '/^<\/(script|style|code|pre)>/i', $part ) ) {
					$in_code_or_script = false;
				}
				$result .= $part;
			} elseif ( $in_code_or_script ) {
				$result .= $part;
			} else {
				$converted = str_replace( self::LATIN_DIGITS, self::PERSIAN_DIGITS, $part );
				$converted = str_replace( self::ARABIC_DIGITS, self::PERSIAN_DIGITS, $converted );
				$result   .= $converted;
			}
		}

		return $result;
	}

	/**
	 * Normalize Persian (۰-۹) and Arabic (٠-٩) digits to standard ASCII Latin digits (0-9).
	 * Used when sanitizing numeric inputs in admin settings and block controls.
	 *
	 * @param string $input Raw input string.
	 * @return string
	 */
	public static function normalize_digits( string $input ): string {
		$normalized = str_replace( self::PERSIAN_DIGITS, self::LATIN_DIGITS, $input );
		$normalized = str_replace( self::ARABIC_DIGITS, self::LATIN_DIGITS, $normalized );
		// Also normalize Arabic/Persian decimal separator to dot.
		$normalized = str_replace( array( '٫', '،' ), '.', $normalized );
		return trim( $normalized );
	}

	/**
	 * Filter dynamic TW Blocks output for Persian digit localization when enabled.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block array.
	 * @return string
	 */
	public static function filter_block_output( string $block_content, array $block ): string {
		$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( ! str_starts_with( $block_name, 'tw-blocks/' ) ) {
			return $block_content;
		}

		$settings = get_option( 'twb_settings', array() );
		if ( ! empty( $settings['enable_persian_digits'] ) ) {
			return self::localize_digits( $block_content, true );
		}

		return $block_content;
	}

	/**
	 * Format a date/timestamp into Jalali (Shamsi) or Gregorian based on plugin settings.
	 * Uses WP-Parsidate if active; falls back to the built-in lightweight converter.
	 *
	 * @param int|string $timestamp Unix timestamp or ISO date string.
	 * @param string     $format    Supported tokens: Y, m, d, j, F.
	 * @return string
	 */
	public static function format_date( int|string $timestamp, string $format = 'j F Y' ): string {
		$ts = is_numeric( $timestamp ) ? (int) $timestamp : strtotime( (string) $timestamp );
		if ( ! $ts ) {
			$ts = time();
		}

		$settings   = get_option( 'twb_settings', array() );
		$use_jalali = ! isset( $settings['enable_jalali_date'] ) || ! empty( $settings['enable_jalali_date'] );

		if ( ! $use_jalali ) {
			return wp_date( $format, $ts ) ?: gmdate( $format, $ts );
		}

		// Use WP-Parsidate if installed and active.
		if ( function_exists( 'parsidate' ) ) {
			$formatted = (string) parsidate( $format, $ts, 'per' );
			return self::localize_digits( $formatted );
		}

		$gy = (int) gmdate( 'Y', $ts );
		$gm = (int) gmdate( 'n', $ts );
		$gd = (int) gmdate( 'j', $ts );

		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );

		$month_name = self::JALALI_MONTHS[ $jm ] ?? '';

		$replacements = array(
			'Y' => (string) $jy,
			'y' => substr( (string) $jy, -2 ),
			'm' => str_pad( (string) $jm, 2, '0', STR_PAD_LEFT ),
			'n' => (string) $jm,
			'd' => str_pad( (string) $jd, 2, '0', STR_PAD_LEFT ),
			'j' => (string) $jd,
			'F' => $month_name,
			'M' => $month_name,
		);

		$output = strtr( $format, $replacements );
		return self::localize_digits( $output );
	}

	/**
	 * Lightweight, dependency-free Gregorian to Jalali (Hijri Shamsi) converter.
	 * Storage always remains Gregorian in the database.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month (1-12).
	 * @param int $gd Gregorian day (1-31).
	 * @return array{0: int, 1: int, 2: int} [jalali_year, jalali_month, jalali_day]
	 */
	public static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + ( $g_d_m[ $gm - 1 ] ?? 0 );

		$jy   = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy  += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Generate an ASCII-only ID or CSS key from arbitrary text (including Persian text).
	 * Preserves Latin alphanumeric characters and appends a short deterministic hash if non-ASCII characters exist.
	 *
	 * @param string $input  Source string (e.g., Persian heading or anchor).
	 * @param string $prefix Prefix for ID.
	 * @return string
	 */
	public static function sanitize_ascii_id( string $input, string $prefix = 'twb-' ): string {
		$normalized = self::normalize_digits( trim( $input ) );
		$ascii_only = strtolower( (string) preg_replace( '/[^a-zA-Z0-9\-_]+/', '-', $normalized ) );
		$ascii_only = trim( (string) preg_replace( '/-+/', '-', $ascii_only ), '-' );

		if ( '' !== $ascii_only && strlen( $ascii_only ) >= 3 && $ascii_only === strtolower( $normalized ) ) {
			return $prefix . $ascii_only;
		}

		$short_hash = substr( hash( 'sha256', $normalized ), 0, 8 );
		if ( '' !== $ascii_only ) {
			return $prefix . $ascii_only . '-' . $short_hash;
		}

		return $prefix . $short_hash;
	}

	/**
	 * Encode Schema.org JSON-LD without escaping Persian UTF-8 characters.
	 *
	 * @param array<string, mixed> $schema Schema data array.
	 * @return string Complete <script type="application/ld+json"> block.
	 */
	public static function encode_json_ld( array $schema ): string {
		$json = wp_json_encode(
			$schema,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);

		if ( ! $json ) {
			return '';
		}

		return sprintf(
			"<script type=\"application/ld+json\">%s</script>\n",
			$json
		);
	}

	/**
	 * Get locale-aware wrapper attributes (`dir` and `lang`) for TW Blocks root elements.
	 *
	 * @return array{dir: string, lang: string}
	 */
	public static function get_locale_attributes(): array {
		$is_rtl = function_exists( 'is_rtl' ) ? is_rtl() : true;
		$locale = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'language' ) : 'fa-IR';

		return array(
			'dir'  => $is_rtl ? 'rtl' : 'ltr',
			'lang' => '' !== $locale ? $locale : 'fa-IR',
		);
	}
}
