<?php
/**
 * WP-CLI Commands for TW Blocks (`wp twb <subcommand>` and `wp tw-blocks <subcommand>`).
 *
 * Supported commands:
 * - `wp twb status`                        : Display active build driver (A/B/C), environment checks, and token hash.
 * - `wp twb build [--all] [--force]`       : Compile base scope, template scopes, and all published posts/pages (`--all`).
 * - `wp twb purge [--corrupted]`           : Purge all cached CSS files or only incomplete/corrupted caches (`--corrupted`).
 * - `wp twb clean-corrupted`               : Manually scan and purge incomplete, zero-byte, or orphaned CSS cache files.
 * - `wp twb export [<file>]`               : Export design tokens and settings to a UTF-8 JSON file or stdout.
 * - `wp twb import <file>`                 : Validate and import a JSON bundle from disk.
 *
 * @package TWBlocks\CLI
 */

declare(strict_types=1);

namespace TWBlocks\CLI;

use TWBlocks\Admin\PortabilityManager;
use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CliCommand
 */
final class CliCommand {

	/**
	 * Default WordPress template slugs compiled when `--all` is passed.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_TEMPLATE_SLUGS = array(
		'index',
		'single',
		'page',
		'archive',
		'search',
		'404',
	);

	/**
	 * CssEngine service.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * CacheManager service.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * TokenManager service.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * PortabilityManager service.
	 *
	 * @var PortabilityManager
	 */
	private PortabilityManager $portability;

	/**
	 * Constructor.
	 *
	 * @param CssEngine          $css_engine    CSS Engine service.
	 * @param CacheManager       $cache_manager Cache Manager service.
	 * @param TokenManager       $token_manager Token Manager service.
	 * @param PortabilityManager $portability   Portability Manager service.
	 */
	public function __construct(
		CssEngine $css_engine,
		CacheManager $cache_manager,
		TokenManager $token_manager,
		PortabilityManager $portability
	) {
		$this->css_engine    = $css_engine;
		$this->cache_manager = $cache_manager;
		$this->token_manager = $token_manager;
		$this->portability   = $portability;
	}

	/**
	 * Register WP-CLI commands (`wp twb` and `wp tw-blocks`) if `WP_CLI` is active.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'twb', $this );
			\WP_CLI::add_command( 'tw-blocks', $this );
		}
	}

	/**
	 * Display CSS engine status, driver diagnostics, and token hash.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb status
	 *     wp tw-blocks status
	 *
	 * @return void
	 */
	public function status(): void {
		$env = $this->css_engine->test_environment();
		\WP_CLI::line( 'TW Blocks Version : ' . TWB_VERSION );
		\WP_CLI::line( 'Active Driver     : ' . (string) $env['active_driver_label'] . ' (' . (string) $env['active_driver_id'] . ')' );
		\WP_CLI::line( 'Design Token Hash : ' . $this->token_manager->get_tokens_hash() );
		\WP_CLI::line( 'Cache Directory   : ' . $this->cache_manager->get_css_dir() );
		\WP_CLI::success( 'Environment diagnostics completed.' );
	}

	/**
	 * Compile CSS files at build/deploy time.
	 * With `--all`, automatically cleans incomplete/corrupted caches and compiles `base`, all `template` scopes, and all published posts/pages.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Clean corrupted caches and compile base scope, all block templates, and all published posts/pages.
	 *
	 * [--force]
	 * : Force recompilation even if cached hash already exists.
	 *
	 * [--post=<id>]
	 * : Compile CSS for a specific post ID only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb build --all
	 *     wp twb build --all --force
	 *     wp twb build --post=42
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative flags.
	 * @return void
	 */
	public function build( array $args, array $assoc_args ): void {
		unset( $args );

		$build_all = isset( $assoc_args['all'] ) || isset( $assoc_args['all-posts'] );
		$force     = ! empty( $assoc_args['force'] );
		$post_id   = isset( $assoc_args['post'] ) ? (int) $assoc_args['post'] : 0;

		// 1. Clean any incomplete or corrupted cache files before building.
		$repaired = $this->cache_manager->purge_corrupted();
		if ( $repaired['removed_files'] > 0 || $repaired['cleaned_meta'] > 0 ) {
			\WP_CLI::log(
				sprintf(
					'Cleaned %d incomplete cache file(s) and %d dangling metadata record(s) prior to build.',
					$repaired['removed_files'],
					$repaired['cleaned_meta']
				)
			);
		}

		// Single post build mode.
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				\WP_CLI::error( sprintf( 'Post #%d not found.', $post_id ) );
				return;
			}
			$meta = $this->css_engine->compile_post_scope( $post, true );
			\WP_CLI::success(
				sprintf(
					'Compiled post #%d -> %s (%d bytes, %d classes, %.2f ms)',
					$post_id,
					(string) ( $meta['filename'] ?? '' ),
					(int) ( $meta['bytes'] ?? 0 ),
					(int) ( $meta['class_count'] ?? 0 ),
					(float) ( $meta['build_time_ms'] ?? 0.0 )
				)
			);
			return;
		}

		// 2. Always compile base scope (`base-{hash}.css`).
		$base_meta = $this->css_engine->compile_base_scope( $force || $build_all );
		\WP_CLI::line(
			sprintf(
				'Compiled base scope -> %s (%d bytes, %d classes, %.2f ms)',
				(string) ( $base_meta['filename'] ?? '' ),
				(int) ( $base_meta['bytes'] ?? 0 ),
				(int) ( $base_meta['class_count'] ?? count( (array) ( $base_meta['classes'] ?? array() ) ) ),
				(float) ( $base_meta['build_time_ms'] ?? 0.0 )
			)
		);

		if ( ! $build_all ) {
			\WP_CLI::success( 'Built base scope CSS. Pass --all (`wp twb build --all`) to compile all templates and published posts/pages.' );
			return;
		}

		// 3. Compile all template scopes (`tpl-{slug}-{hash}.css`).
		$template_slugs = self::DEFAULT_TEMPLATE_SLUGS;
		if ( function_exists( 'get_block_templates' ) ) {
			$block_templates = get_block_templates( array(), 'wp_template' );
			if ( is_array( $block_templates ) ) {
				foreach ( $block_templates as $tpl ) {
					if ( ! empty( $tpl->slug ) ) {
						$template_slugs[] = (string) $tpl->slug;
					}
				}
			}
		}
		$template_slugs = array_values( array_unique( $template_slugs ) );

		$tpl_count = 0;
		foreach ( $template_slugs as $slug ) {
			$this->css_engine->compile_template_scope( $slug, $force );
			++$tpl_count;
		}

		// 4. Compile all published posts and pages (`post-{id}-{hash}.css`).
		$public_post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $public_post_types['attachment'] );

		$posts = get_posts(
			array(
				'post_type'              => ! empty( $public_post_types ) ? array_values( $public_post_types ) : array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$post_count = 0;
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				if ( $post instanceof \WP_Post ) {
					$this->css_engine->compile_post_scope( $post, $force );
					++$post_count;
				}
			}
		}

		\WP_CLI::success(
			sprintf(
				'Built CSS for base scope, %d template scopes, and %d published posts/pages.',
				$tpl_count,
				$post_count
			)
		);
	}

	/**
	 * Purge cached CSS files.
	 * Pass `--corrupted` (or `--incomplete`) to manually remove only corrupted/incomplete cache files and dangling metadata.
	 *
	 * ## OPTIONS
	 *
	 * [--corrupted]
	 * : Only remove incomplete, zero-byte, or corrupted CSS cache files and dangling metadata without deleting valid caches.
	 *
	 * [--incomplete]
	 * : Alias for `--corrupted`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb purge --corrupted
	 *     wp twb purge
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative flags.
	 * @return void
	 */
	public function purge( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		if ( ! empty( $assoc_args['corrupted'] ) || ! empty( $assoc_args['incomplete'] ) ) {
			$this->clean_corrupted();
			return;
		}

		$removed = $this->cache_manager->purge_all();
		$this->css_engine->compile_base_scope( true );
		\WP_CLI::success( sprintf( 'Purged %d cached CSS files and rebuilt base stylesheet.', $removed ) );
	}

	/**
	 * Manually scan and purge incomplete, zero-byte, syntactically truncated, or orphaned CSS cache files.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb clean-corrupted
	 *
	 * @subcommand clean-corrupted
	 * @return void
	 */
	public function clean_corrupted(): void {
		$report = $this->cache_manager->purge_corrupted();

		foreach ( $report['details'] as $line ) {
			\WP_CLI::line( ' - ' . $line );
		}

		// Ensure base scope is valid after cleanup.
		$this->css_engine->compile_base_scope( false );

		\WP_CLI::success(
			sprintf(
				'Cache integrity check complete: removed %d incomplete/corrupted file(s) and cleaned %d dangling metadata entry(ies).',
				$report['removed_files'],
				$report['cleaned_meta']
			)
		);
	}

	/**
	 * Export design tokens and settings to a UTF-8 JSON file or stdout.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Optional file path to write JSON export.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb export tw-blocks-tokens.json
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function export( array $args ): void {
		$payload = $this->portability->create_export_payload();
		$json    = (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( ! empty( $args[0] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $args[0], $json );
			\WP_CLI::success( sprintf( 'Exported configuration to %s', $args[0] ) );
			return;
		}

		\WP_CLI::line( $json );
	}

	/**
	 * Import design tokens and settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file to import.
	 *
	 * ## EXAMPLES
	 *
	 *     wp twb import tw-blocks-tokens.json
	 *
	 * @param array<int, string> $args Positional args.
	 * @return void
	 */
	public function import( array $args ): void {
		$file = $args[0] ?? '';
		if ( '' === $file || ! is_readable( $file ) ) {
			\WP_CLI::error( 'Specified JSON file does not exist or is not readable.' );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw    = (string) file_get_contents( $file );
		$result = $this->portability->import_payload( $raw );

		if ( ! empty( $result['success'] ) ) {
			\WP_CLI::success( (string) $result['message'] );
		} else {
			\WP_CLI::error( (string) $result['message'] );
		}
	}
}
