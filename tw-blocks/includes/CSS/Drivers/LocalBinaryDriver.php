<?php
/**
 * Driver A: Standalone Local Tailwind CSS v4 Binary Driver.
 * Runs the standalone binary in `wp-content/uploads/tw-blocks/bin/tailwindcss` via `proc_open`.
 *
 * @package TWBlocks\CSS\Drivers
 */

declare(strict_types=1);

namespace TWBlocks\CSS\Drivers;

use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\DriverInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalBinaryDriver
 */
final class LocalBinaryDriver implements DriverInterface {

	/**
	 * CacheManager instance.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * Constructor.
	 *
	 * @param CacheManager $cache_manager Cache manager service.
	 */
	public function __construct( CacheManager $cache_manager ) {
		$this->cache_manager = $cache_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'local_binary';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'Driver A — Local Tailwind v4 Binary', 'tw-blocks' );
	}

	/**
	 * Locate the standalone Tailwind v4 binary inside `wp-content/uploads/tw-blocks/bin/`.
	 *
	 * @return string|null Full path to binary or null if not found.
	 */
	public function get_binary_path(): ?string {
		$bin_dir    = $this->cache_manager->get_bin_dir();
		$candidates = array(
			$bin_dir . '/tailwindcss',
			$bin_dir . '/tailwindcss-linux-x64',
			$bin_dir . '/tailwindcss-linux-arm64',
			$bin_dir . '/tailwindcss-macos-arm64',
			$bin_dir . '/tailwindcss-macos-x64',
			$bin_dir . '/tailwindcss.exe',
		);

		foreach ( $candidates as $candidate ) {
			if ( is_file( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Check if `proc_open` is available and not disabled in php.ini.
	 *
	 * @return bool
	 */
	public function is_proc_open_enabled(): bool {
		if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_close' ) ) {
			return false;
		}

		$disabled = (string) ini_get( 'disable_functions' );
		if ( '' !== $disabled ) {
			$disabled_list = array_map( 'trim', explode( ',', strtolower( $disabled ) ) );
			if ( in_array( 'proc_open', $disabled_list, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		if ( ! $this->is_proc_open_enabled() ) {
			return false;
		}

		$binary = $this->get_binary_path();
		if ( null === $binary || ! is_executable( $binary ) ) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function diagnose(): array {
		$checks    = array();
		$proc_ok   = $this->is_proc_open_enabled();
		$bin_dir   = $this->cache_manager->get_bin_dir();
		$bin_path  = $this->get_binary_path();
		$bin_found = null !== $bin_path;
		$bin_exec  = $bin_found && is_executable( $bin_path );

		$checks[] = array(
			'name'    => __( 'PHP proc_open() Function', 'tw-blocks' ),
			'passed'  => $proc_ok,
			'message' => $proc_ok
				? __( 'proc_open() is enabled and available in PHP runtime.', 'tw-blocks' )
				: __( 'proc_open() is disabled in php.ini (disable_functions) on this host.', 'tw-blocks' ),
		);

		$checks[] = array(
			'name'    => __( 'Standalone Binary File', 'tw-blocks' ),
			'passed'  => $bin_found,
			'message' => $bin_found
				/* translators: %s: binary path */
				? sprintf( __( 'Binary found at: %s', 'tw-blocks' ), $bin_path )
				/* translators: %s: directory path */
				: sprintf( __( 'No Tailwind v4 binary found in %s', 'tw-blocks' ), $bin_dir ),
		);

		$checks[] = array(
			'name'    => __( 'Executable Permission (chmod +x)', 'tw-blocks' ),
			'passed'  => $bin_exec,
			'message' => $bin_exec
				? __( 'Binary has executable permissions.', 'tw-blocks' )
				: __( 'Binary is missing or does not have executable (+x) permission.', 'tw-blocks' ),
		);

		$version_ok  = false;
		$version_msg = __( 'Skipped because binary is not executable.', 'tw-blocks' );

		if ( $proc_ok && $bin_exec && null !== $bin_path ) {
			$run = $this->run_process( array( $bin_path, '--help' ), 5 );
			if ( 0 === $run['exit_code'] && str_contains( strtolower( $run['stdout'] ), 'tailwindcss' ) ) {
				$version_ok  = true;
				$first_line  = strtok( trim( $run['stdout'] ), "\n" );
				$version_msg = sprintf(
					/* translators: %s: CLI output header */
					__( 'Binary executed successfully (%s).', 'tw-blocks' ),
					(string) $first_line
				);
			} else {
				$version_msg = sprintf(
					/* translators: %s: stderr message */
					__( 'Binary failed to execute: %s', 'tw-blocks' ),
					trim( $run['stderr'] ?: 'Exit code ' . $run['exit_code'] )
				);
			}
		}

		$checks[] = array(
			'name'    => __( 'CLI Execution Smoke Test', 'tw-blocks' ),
			'passed'  => $version_ok,
			'message' => $version_msg,
		);

		return array(
			'available' => $proc_ok && $bin_found && $bin_exec && $version_ok,
			'driver_id' => $this->get_id(),
			'label'     => $this->get_label(),
			'checks'    => $checks,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function compile( array $classes, string $theme_css, string $preflight_css ): array {
		if ( ! $this->is_available() ) {
			return array(
				'success' => false,
				'css'     => '',
				'driver'  => $this->get_id(),
				'error'   => __( 'Local binary driver is not available on this server.', 'tw-blocks' ),
			);
		}

		$binary  = (string) $this->get_binary_path();
		$tmp_dir = get_temp_dir() . 'twb_build_' . wp_generate_password( 10, false, false );

		if ( ! wp_mkdir_p( $tmp_dir ) ) {
			return array(
				'success' => false,
				'css'     => '',
				'driver'  => $this->get_id(),
				'error'   => __( 'Unable to create temporary build directory.', 'tw-blocks' ),
			);
		}

		$content_file = $tmp_dir . '/content.html';
		$input_file   = $tmp_dir . '/input.css';
		$output_file  = $tmp_dir . '/output.css';

		$safe_class_attr = esc_attr( implode( ' ', $classes ) );
		$html_stub       = sprintf( "<div class=\"%s\"></div>\n", $safe_class_attr );

		// Tailwind v4 CSS-first configuration with @source pointing to the extracted content stub.
		$input_css  = "@import \"tailwindcss\";\n";
		$input_css .= sprintf( "@source \"%s\";\n\n", $content_file );
		$input_css .= $theme_css . "\n\n";
		$input_css .= $preflight_css . "\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $content_file, $html_stub );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $input_file, $input_css );

		$command = array(
			$binary,
			'--input',
			$input_file,
			'--output',
			$output_file,
			'--minify',
		);

		$result = $this->run_process( $command, 15, $tmp_dir );

		$compiled_css = '';
		if ( 0 === $result['exit_code'] && is_readable( $output_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$compiled_css = (string) file_get_contents( $output_file );
		}

		// Clean up temporary files.
		foreach ( array( $content_file, $input_file, $output_file ) as $f ) {
			if ( is_file( $f ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $f );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $tmp_dir );

		if ( '' === trim( $compiled_css ) ) {
			return array(
				'success' => false,
				'css'     => '',
				'driver'  => $this->get_id(),
				'error'   => trim( $result['stderr'] ?: __( 'Tailwind binary produced empty CSS output.', 'tw-blocks' ) ),
			);
		}

		return array(
			'success' => true,
			'css'     => $compiled_css,
			'driver'  => $this->get_id(),
		);
	}

	/**
	 * Execute a command array safely via `proc_open` without shell interpolation.
	 *
	 * @param array<int, string> $command Command and arguments array.
	 * @param int                $timeout Timeout in seconds.
	 * @param string|null        $cwd     Working directory.
	 * @return array{exit_code: int, stdout: string, stderr: string}
	 */
	private function run_process( array $command, int $timeout = 10, ?string $cwd = null ): array {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$pipes   = array();
		$process = @proc_open( $command, $descriptors, $pipes, $cwd );

		if ( ! is_resource( $process ) ) {
			return array(
				'exit_code' => -1,
				'stdout'    => '',
				'stderr'    => 'Unable to spawn process via proc_open.',
			);
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$stdout = '';
		$stderr = '';
		$start  = microtime( true );

		while ( true ) {
			$status = proc_get_status( $process );
			$stdout .= (string) stream_get_contents( $pipes[1] );
			$stderr .= (string) stream_get_contents( $pipes[2] );

			if ( ! $status['running'] ) {
				$exit_code = (int) $status['exitcode'];
				break;
			}

			if ( ( microtime( true ) - $start ) > $timeout ) {
				proc_terminate( $process, 9 );
				$exit_code = 124;
				$stderr   .= ' Process timed out.';
				break;
			}

			usleep( 25000 );
		}

		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );

		return array(
			'exit_code' => $exit_code,
			'stdout'    => $stdout,
			'stderr'    => $stderr,
		);
	}
}
