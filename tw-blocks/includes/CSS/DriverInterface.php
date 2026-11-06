<?php
/**
 * Contract for Tailwind CSS v4 Compilation Drivers (Local Binary, Remote API, Fallback Safelist).
 *
 * @package TWBlocks\CSS
 */

declare(strict_types=1);

namespace TWBlocks\CSS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface DriverInterface
 */
interface DriverInterface {

	/**
	 * Get machine identifier for the driver (`local_binary`, `remote_api`, `fallback_safelist`).
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get localized display label for the driver.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Check whether the driver can currently operate in this hosting environment.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Run detailed environment diagnostics for the "تست محیط" button in settings.
	 *
	 * @return array{
	 *   available: bool,
	 *   driver_id: string,
	 *   label: string,
	 *   checks: array<int, array{name: string, passed: bool, message: string}>
	 * }
	 */
	public function diagnose(): array;

	/**
	 * Compile a validated list of Tailwind utility classes + @theme tokens + preflight CSS into minified CSS.
	 *
	 * @param array<int, string> $classes       Sorted, validated Tailwind class names.
	 * @param string             $theme_css     Generated `@theme` and `:root` CSS block.
	 * @param string             $preflight_css Scoped `.twb-root` preflight CSS.
	 * @return array{
	 *   success: bool,
	 *   css: string,
	 *   driver: string,
	 *   error?: string
	 * }
	 */
	public function compile( array $classes, string $theme_css, string $preflight_css ): array;
}
