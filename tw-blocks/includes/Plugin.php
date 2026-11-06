<?php
/**
 * Core Plugin Bootstrap & Service Container.
 *
 * @package TWBlocks
 */

declare(strict_types=1);

namespace TWBlocks;

use TWBlocks\Admin\DebugBar;
use TWBlocks\Admin\PortabilityManager;
use TWBlocks\Admin\SettingsPage;
use TWBlocks\Blocks\BlockRegistry;
use TWBlocks\Blocks\QueryLoopExtension;
use TWBlocks\CLI\CliCommand;
use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\ClassExtractor;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;
use TWBlocks\Frontend\AssetLoader;
use TWBlocks\Patterns\PatternRegistry;
use TWBlocks\Support\PersianHelper;
use TWBlocks\WooCommerce\WooIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Token Manager service.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * Cache Manager service.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * Class Extractor service.
	 *
	 * @var ClassExtractor
	 */
	private ClassExtractor $class_extractor;

	/**
	 * CSS Engine service.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * Frontend Asset Loader service.
	 *
	 * @var AssetLoader
	 */
	private AssetLoader $asset_loader;

	/**
	 * Admin Settings Page service.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Admin Bar Debug Panel service.
	 *
	 * @var DebugBar
	 */
	private DebugBar $debug_bar;

	/**
	 * Block Registry service (Phase 2).
	 *
	 * @var BlockRegistry
	 */
	private BlockRegistry $block_registry;

	/**
	 * Query Loop Extension service (Phase 2).
	 *
	 * @var QueryLoopExtension
	 */
	private QueryLoopExtension $query_loop_extension;

	/**
	 * Pattern Registry service (Phase 3).
	 *
	 * @var PatternRegistry
	 */
	private PatternRegistry $pattern_registry;

	/**
	 * WooCommerce Integration service (Phase 3).
	 *
	 * @var WooIntegration
	 */
	private WooIntegration $woo_integration;

	/**
	 * Portability (Export/Import) Manager service (Phase 3).
	 *
	 * @var PortabilityManager
	 */
	private PortabilityManager $portability_manager;

	/**
	 * WP-CLI Command service (Phase 3).
	 *
	 * @var CliCommand
	 */
	private CliCommand $cli_command;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->token_manager        = new TokenManager();
		$this->cache_manager        = new CacheManager();
		$this->class_extractor      = new ClassExtractor( $this->token_manager );
		$this->css_engine           = new CssEngine( $this->token_manager, $this->cache_manager, $this->class_extractor );
		$this->asset_loader         = new AssetLoader( $this->css_engine, $this->token_manager, $this->cache_manager );
		$this->settings_page        = new SettingsPage( $this->token_manager, $this->css_engine, $this->cache_manager );
		$this->debug_bar            = new DebugBar( $this->css_engine, $this->asset_loader );
		$this->block_registry       = new BlockRegistry( $this->token_manager, $this->css_engine );
		$this->query_loop_extension = new QueryLoopExtension();
		$this->pattern_registry     = new PatternRegistry();
		$this->woo_integration      = new WooIntegration( $this->token_manager );
		$this->portability_manager  = new PortabilityManager(
			$this->token_manager,
			$this->css_engine,
			$this->cache_manager,
			$this->pattern_registry
		);
		$this->cli_command          = new CliCommand(
			$this->css_engine,
			$this->cache_manager,
			$this->token_manager,
			$this->portability_manager
		);
	}

	/**
	 * Initialize plugin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();

		PersianHelper::init();
		$this->token_manager->init();
		$this->css_engine->init();
		$this->block_registry->init();
		$this->query_loop_extension->init();
		$this->pattern_registry->init();
		$this->woo_integration->init();
		$this->portability_manager->init();
		$this->cli_command->init();
		$this->asset_loader->init();
		$this->settings_page->init();
		$this->debug_bar->init();
	}

	/**
	 * Load plugin translations for PHP and JS.
	 * Defaults to `fa_IR` Persian translation bundle when present so the interface defaults to Persian.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		add_filter(
			'plugin_locale',
			static function ( string $locale, string $domain ): string {
				if ( 'tw-blocks' === $domain && ( '' === $locale || 'en_US' === $locale ) ) {
					return 'fa_IR';
				}
				return $locale;
			},
			10,
			2
		);

		$loaded = load_plugin_textdomain(
			'tw-blocks',
			false,
			dirname( plugin_basename( TWB_FILE ) ) . '/languages'
		);

		if ( ! $loaded ) {
			$fa_mo = TWB_PATH . 'languages/tw-blocks-fa_IR.mo';
			if ( is_readable( $fa_mo ) ) {
				load_textdomain( 'tw-blocks', $fa_mo );
			}
		}
	}

	/**
	 * Plugin activation hook: create required upload directories and compile initial base CSS.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$cache = new CacheManager();
		$cache->ensure_directories();

		$tokens = new TokenManager();
		if ( false === get_option( 'twb_design_tokens', false ) ) {
			update_option( 'twb_design_tokens', $tokens->get_default_tokens(), false );
		}
		if ( false === get_option( 'twb_settings', false ) ) {
			update_option( 'twb_settings', SettingsPage::get_default_settings(), false );
		}

		// Trigger initial base CSS build.
		$extractor = new ClassExtractor( $tokens );
		$engine    = new CssEngine( $tokens, $cache, $extractor );
		$engine->compile_base_scope();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Keep cached CSS files intact to avoid front-end flash on temporary deactivation.
	}

	/**
	 * Access TokenManager.
	 *
	 * @return TokenManager
	 */
	public function tokens(): TokenManager {
		return $this->token_manager;
	}

	/**
	 * Access CssEngine.
	 *
	 * @return CssEngine
	 */
	public function engine(): CssEngine {
		return $this->css_engine;
	}

	/**
	 * Access CacheManager.
	 *
	 * @return CacheManager
	 */
	public function cache(): CacheManager {
		return $this->cache_manager;
	}

	/**
	 * Access BlockRegistry.
	 *
	 * @return BlockRegistry
	 */
	public function blocks(): BlockRegistry {
		return $this->block_registry;
	}

	/**
	 * Access PatternRegistry.
	 *
	 * @return PatternRegistry
	 */
	public function patterns(): PatternRegistry {
		return $this->pattern_registry;
	}

	/**
	 * Access PortabilityManager.
	 *
	 * @return PortabilityManager
	 */
	public function portability(): PortabilityManager {
		return $this->portability_manager;
	}
}
