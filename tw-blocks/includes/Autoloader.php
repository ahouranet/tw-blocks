<?php
/**
 * PSR-4 style Autoloader for TWBlocks namespace without requiring Composer at runtime.
 *
 * @package TWBlocks
 */

declare(strict_types=1);

namespace TWBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Namespace prefix.
	 */
	private const PREFIX = 'TWBlocks\\';

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Autoload a class under the TWBlocks namespace.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		$len = strlen( self::PREFIX );
		if ( strncmp( self::PREFIX, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = TWB_PATH . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
