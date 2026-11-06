<?php
/**
 * Design Token Manager: Generates Tailwind v4 @theme block, theme.json CSS variables, and local Vazirmatn @font-face.
 *
 * @package TWBlocks\CSS
 */

declare(strict_types=1);

namespace TWBlocks\CSS;

use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TokenManager
 */
final class TokenManager {

	/**
	 * Option name for stored design tokens.
	 */
	public const OPTION_KEY = 'twb_design_tokens';

	/**
	 * In-memory per-request cache of merged tokens to eliminate redundant get_option() calls.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cached_tokens = null;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'upload_mimes', array( $this, 'allow_woff2_uploads' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'verify_woff2_filetype' ), 10, 4 );
	}

	/**
	 * Get default design tokens tailored for Persian typography and RTL layouts.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_tokens(): array {
		return array(
			'colors'      => array(
				'primary'    => '#0d9488',
				'secondary'  => '#0f172a',
				'accent'     => '#0284c7',
				'background' => '#ffffff',
				'surface'    => '#f8fafc',
				'foreground' => '#0f172a',
				'muted'      => '#475569',
				'border'     => '#e2e8f0',
				'success'    => '#16a34a',
				'warning'    => '#d97706',
				'danger'     => '#dc2626',
			),
			'typography'  => array(
				'font_family_base'    => 'Vazirmatn, Tahoma, system-ui, sans-serif',
				'font_family_heading' => 'Vazirmatn, Tahoma, system-ui, sans-serif',
				'font_family_mono'    => 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
				'custom_woff2_url'    => '',
				'custom_font_name'    => '',
				'preload_main_font'   => true,
				'line_height_body'    => '1.8',
				'line_height_heading' => '1.4',
				'letter_spacing_fa'   => '0em',
				'base_font_size'      => '16px',
			),
			'layout'      => array(
				'container_width' => '1280px',
				'content_width'   => '768px',
				'spacing_unit'    => '0.25rem',
				'radius_sm'       => '0.375rem',
				'radius_md'       => '0.5rem',
				'radius_lg'       => '0.75rem',
				'radius_xl'       => '1rem',
			),
			'breakpoints' => array(
				'sm'  => '640px',
				'md'  => '768px',
				'lg'  => '1024px',
				'xl'  => '1280px',
				'2xl' => '1536px',
			),
			'preflight'   => array(
				'enabled'        => true,
				'scoped_wrapper' => '.twb-root',
			),
		);
	}

	/**
	 * Retrieve active design tokens merged with defaults (cached in memory per request).
	 *
	 * @return array<string, mixed>
	 */
	public function get_tokens(): array {
		if ( null !== $this->cached_tokens ) {
			return $this->cached_tokens;
		}

		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = $this->get_default_tokens();

		if ( ! is_array( $saved ) ) {
			$this->cached_tokens = $defaults;
			return $this->cached_tokens;
		}

		$this->cached_tokens = array(
			'colors'      => array_merge( $defaults['colors'], is_array( $saved['colors'] ?? null ) ? $saved['colors'] : array() ),
			'typography'  => array_merge( $defaults['typography'], is_array( $saved['typography'] ?? null ) ? $saved['typography'] : array() ),
			'layout'      => array_merge( $defaults['layout'], is_array( $saved['layout'] ?? null ) ? $saved['layout'] : array() ),
			'breakpoints' => array_merge( $defaults['breakpoints'], is_array( $saved['breakpoints'] ?? null ) ? $saved['breakpoints'] : array() ),
			'preflight'   => array_merge( $defaults['preflight'], is_array( $saved['preflight'] ?? null ) ? $saved['preflight'] : array() ),
		);

		return $this->cached_tokens;
	}

	/**
	 * Sanitize and save design tokens.
	 * Normalizes Persian/Arabic digits in numeric dimensions before persisting.
	 *
	 * @param array<string, mixed> $raw Raw input array.
	 * @return array<string, mixed> Sanitized tokens.
	 */
	public function save_tokens( array $raw ): array {
		$defaults  = $this->get_default_tokens();
		$sanitized = $defaults;

		if ( isset( $raw['colors'] ) && is_array( $raw['colors'] ) ) {
			foreach ( $defaults['colors'] as $key => $default_color ) {
				$val = isset( $raw['colors'][ $key ] ) ? sanitize_hex_color( (string) $raw['colors'][ $key ] ) : '';
				$sanitized['colors'][ $key ] = $val ? $val : $default_color;
			}
		}

		if ( isset( $raw['typography'] ) && is_array( $raw['typography'] ) ) {
			$typo = $raw['typography'];

			$line_body = isset( $typo['line_height_body'] ) ? PersianHelper::normalize_digits( (string) $typo['line_height_body'] ) : '1.8';
			$line_float = (float) $line_body;
			if ( $line_float < 1.2 || $line_float > 2.5 ) {
				$line_float = 1.8;
			}
			$sanitized['typography']['line_height_body'] = (string) round( $line_float, 2 );

			$line_head = isset( $typo['line_height_heading'] ) ? PersianHelper::normalize_digits( (string) $typo['line_height_heading'] ) : '1.4';
			$head_float = (float) $line_head;
			if ( $head_float < 1.0 || $head_float > 2.2 ) {
				$head_float = 1.4;
			}
			$sanitized['typography']['line_height_heading'] = (string) round( $head_float, 2 );

			// Persian letter-spacing must remain 0 to avoid disconnecting cursive Persian glyphs.
			$sanitized['typography']['letter_spacing_fa'] = '0em';

			$custom_url = isset( $typo['custom_woff2_url'] ) ? esc_url_raw( (string) $typo['custom_woff2_url'] ) : '';
			if ( '' !== $custom_url && ! str_ends_with( strtolower( (string) wp_parse_url( $custom_url, PHP_URL_PATH ) ), '.woff2' ) ) {
				$custom_url = '';
			}
			$sanitized['typography']['custom_woff2_url']  = $custom_url;
			$sanitized['typography']['custom_font_name']  = isset( $typo['custom_font_name'] ) ? sanitize_text_field( (string) $typo['custom_font_name'] ) : '';
			$sanitized['typography']['preload_main_font'] = ! empty( $typo['preload_main_font'] );

			if ( '' !== $sanitized['typography']['custom_font_name'] && '' !== $custom_url ) {
				$clean_name = preg_replace( '/[^a-zA-Z0-9\-\s_]/', '', $sanitized['typography']['custom_font_name'] );
				$stack      = sprintf( '"%s", Vazirmatn, Tahoma, system-ui, sans-serif', trim( (string) $clean_name ) );
				$sanitized['typography']['font_family_base']    = $stack;
				$sanitized['typography']['font_family_heading'] = $stack;
			}
		}

		if ( isset( $raw['layout'] ) && is_array( $raw['layout'] ) ) {
			foreach ( array( 'container_width', 'content_width', 'spacing_unit', 'radius_sm', 'radius_md', 'radius_lg', 'radius_xl' ) as $lkey ) {
				if ( isset( $raw['layout'][ $lkey ] ) ) {
					$val = PersianHelper::normalize_digits( (string) $raw['layout'][ $lkey ] );
					if ( preg_match( '/^\d+(\.\d+)?(px|rem|em|vw|%)$/', $val ) ) {
						$sanitized['layout'][ $lkey ] = $val;
					}
				}
			}
		}

		if ( isset( $raw['breakpoints'] ) && is_array( $raw['breakpoints'] ) ) {
			foreach ( array( 'sm', 'md', 'lg', 'xl', '2xl' ) as $bp ) {
				if ( isset( $raw['breakpoints'][ $bp ] ) ) {
					$val = PersianHelper::normalize_digits( (string) $raw['breakpoints'][ $bp ] );
					if ( preg_match( '/^\d+px$/', $val ) ) {
						$sanitized['breakpoints'][ $bp ] = $val;
					}
				}
			}
		}

		if ( isset( $raw['preflight'] ) && is_array( $raw['preflight'] ) ) {
			$sanitized['preflight']['enabled'] = ! empty( $raw['preflight']['enabled'] );
		}

		update_option( self::OPTION_KEY, $sanitized, false );
		$this->cached_tokens = $sanitized;
		return $sanitized;
	}

	/**
	 * Generate self-hosted @font-face rules for Vazirmatn Variable (woff2) and optional custom font.
	 * Uses font-display: swap and unicode-range for Persian/Arabic and Latin subsets.
	 * Zero external CDN dependencies.
	 *
	 * @return string CSS @font-face declarations.
	 */
	public function generate_font_face_css(): string {
		$tokens   = $this->get_tokens();
		$typo     = $tokens['typography'];
		$font_url = $this->get_primary_font_url();

		$css  = "/* Self-hosted Vazirmatn Variable Font (Persian/Arabic & Latin subsets, zero CDN) */\n";
		$css .= "@font-face {\n";
		$css .= "  font-family: 'Vazirmatn';\n";
		$css .= "  src: url('" . esc_url( TWB_URL . 'assets/fonts/Vazirmatn-Variable.woff2' ) . "') format('woff2-variations'),\n";
		$css .= "       url('" . esc_url( TWB_URL . 'assets/fonts/Vazirmatn-Variable.woff2' ) . "') format('woff2');\n";
		$css .= "  font-weight: 100 900;\n";
		$css .= "  font-style: normal;\n";
		$css .= "  font-display: swap;\n";
		$css .= "  unicode-range: U+0600-06FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FEFF, U+0000-00FF;\n";
		$css .= "}\n";

		if ( ! empty( $typo['custom_woff2_url'] ) && ! empty( $typo['custom_font_name'] ) ) {
			$clean_name = preg_replace( '/[^a-zA-Z0-9\-\s_]/', '', (string) $typo['custom_font_name'] );
			$css       .= "@font-face {\n";
			$css       .= "  font-family: '" . esc_attr( trim( (string) $clean_name ) ) . "';\n";
			$css       .= "  src: url('" . esc_url( (string) $typo['custom_woff2_url'] ) . "') format('woff2');\n";
			$css       .= "  font-weight: 100 900;\n";
			$css       .= "  font-style: normal;\n";
			$css       .= "  font-display: swap;\n";
			$css       .= "  unicode-range: U+0600-06FF, U+200C-200E, U+FB50-FDFF, U+FE70-FEFF, U+0000-00FF;\n";
			$css       .= "}\n";
		}

		unset( $font_url );
		return $css;
	}

	/**
	 * Get the URL of the primary woff2 font file to preload in <head>.
	 *
	 * @return string
	 */
	public function get_primary_font_url(): string {
		$tokens = $this->get_tokens();
		if ( ! empty( $tokens['typography']['custom_woff2_url'] ) ) {
			return (string) $tokens['typography']['custom_woff2_url'];
		}
		return TWB_URL . 'assets/fonts/Vazirmatn-Variable.woff2';
	}

	/**
	 * Generate the Tailwind CSS v4 CSS-first `@theme` block and `:root` bridge for WordPress `theme.json`.
	 *
	 * @param bool $include_tailwind_import Whether to prepend `@import "tailwindcss";` for Driver A/B.
	 * @return string
	 */
	public function generate_theme_css( bool $include_tailwind_import = false ): string {
		$tokens = $this->get_tokens();
		$colors = $tokens['colors'];
		$typo   = $tokens['typography'];
		$layout = $tokens['layout'];
		$bps    = $tokens['breakpoints'];

		$out = '';
		if ( $include_tailwind_import ) {
			$out .= "@import \"tailwindcss\";\n\n";
		}

		$out .= $this->generate_font_face_css() . "\n";

		// Tailwind CSS v4 @theme directive (CSS-first configuration, no tailwind.config.js).
		$out .= "@theme {\n";
		foreach ( $colors as $name => $hex ) {
			$out .= sprintf( "  --color-%s: %s;\n", sanitize_key( $name ), $hex );
		}
		$out .= sprintf( "  --font-sans: %s;\n", $typo['font_family_base'] );
		$out .= sprintf( "  --font-heading: %s;\n", $typo['font_family_heading'] );
		$out .= sprintf( "  --font-mono: %s;\n", $typo['font_family_mono'] );
		$out .= sprintf( "  --spacing: %s;\n", $layout['spacing_unit'] );
		$out .= sprintf( "  --radius-sm: %s;\n", $layout['radius_sm'] );
		$out .= sprintf( "  --radius-md: %s;\n", $layout['radius_md'] );
		$out .= sprintf( "  --radius-lg: %s;\n", $layout['radius_lg'] );
		$out .= sprintf( "  --radius-xl: %s;\n", $layout['radius_xl'] );
		$out .= sprintf( "  --container-main: %s;\n", $layout['container_width'] );
		$out .= sprintf( "  --container-content: %s;\n", $layout['content_width'] );
		foreach ( $bps as $bp_name => $bp_val ) {
			$out .= sprintf( "  --breakpoint-%s: %s;\n", $bp_name, $bp_val );
		}
		$out .= "}\n\n";

		// WordPress theme.json single source-of-truth CSS variable bridge.
		$out .= ":root {\n";
		foreach ( $colors as $name => $hex ) {
			$slug = sanitize_key( $name );
			$out .= sprintf( "  --twb-color-%s: %s;\n", $slug, $hex );
			$out .= sprintf( "  --wp--preset--color--%s: var(--twb-color-%s);\n", $slug, $slug );
		}
		$out .= sprintf( "  --twb-font-sans: %s;\n", $typo['font_family_base'] );
		$out .= sprintf( "  --twb-font-heading: %s;\n", $typo['font_family_heading'] );
		$out .= "  --wp--preset--font-family--vazirmatn: var(--twb-font-sans);\n";
		$out .= sprintf( "  --twb-line-height-body: %s;\n", $typo['line_height_body'] );
		$out .= sprintf( "  --twb-line-height-heading: %s;\n", $typo['line_height_heading'] );
		$out .= "  --twb-letter-spacing-fa: 0em;\n";
		$out .= sprintf( "  --twb-container-width: %s;\n", $layout['container_width'] );
		$out .= sprintf( "  --twb-content-width: %s;\n", $layout['content_width'] );
		$out .= sprintf( "  --twb-radius-sm: %s;\n", $layout['radius_sm'] );
		$out .= sprintf( "  --twb-radius-md: %s;\n", $layout['radius_md'] );
		$out .= sprintf( "  --twb-radius-lg: %s;\n", $layout['radius_lg'] );
		$out .= sprintf( "  --twb-radius-xl: %s;\n", $layout['radius_xl'] );
		$out .= "}\n";

		return $out;
	}

	/**
	 * Load the scoped Preflight-Lite reset CSS (`.twb-root`) so active themes are never broken.
	 *
	 * @return string
	 */
	public function get_preflight_css(): string {
		$tokens = $this->get_tokens();
		if ( empty( $tokens['preflight']['enabled'] ) ) {
			return '';
		}

		$file = TWB_PATH . 'assets/css/preflight-lite.css';
		if ( is_readable( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $file );
		}

		return '';
	}

	/**
	 * Compute a hash of current design tokens so any token update invalidates cached CSS.
	 *
	 * @return string 12-character hex hash.
	 */
	public function get_tokens_hash(): string {
		$tokens = $this->get_tokens();
		return substr( hash( 'sha256', (string) wp_json_encode( $tokens ) ), 0, 12 );
	}

	/**
	 * Allow .woff2 font uploads in WordPress media library for administrators.
	 *
	 * @param array<string, string> $mimes Existing MIME types.
	 * @return array<string, string>
	 */
	public function allow_woff2_uploads( array $mimes ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['woff2'] = 'font/woff2';
		}
		return $mimes;
	}

	/**
	 * Ensure WordPress file type verification accepts valid .woff2 files only after checking binary magic bytes (`wOF2`).
	 * Prevents spoofed PHP/HTML uploads disguised with a `.woff2` extension.
	 *
	 * @param array<string, mixed> $data     File data.
	 * @param string               $file     Full path to the file.
	 * @param string               $filename The name of the file.
	 * @param string[]|null        $mimes    Array of mime types.
	 * @return array<string, mixed>
	 */
	public function verify_woff2_filetype( array $data, string $file, string $filename, ?array $mimes ): array {
		unset( $mimes );
		if ( str_ends_with( strtolower( $filename ), '.woff2' ) && current_user_can( 'manage_options' ) ) {
			if ( SecurityHelper::has_valid_woff2_signature( $file ) ) {
				$data['ext']  = 'woff2';
				$data['type'] = 'font/woff2';
			} else {
				$data['ext']             = false;
				$data['type']            = false;
				$data['proper_filename'] = false;
			}
		}
		return $data;
	}
}
