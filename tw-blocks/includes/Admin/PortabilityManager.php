<?php
/**
 * JSON Export / Import Manager for Design Tokens, Safelist, and Plugin Settings (Phase 3).
 *
 * Security Guarantees:
 * - Requires `manage_options` capability and valid admin/REST nonces.
 * - Never exports sensitive HMAC keys (`remote_api_key` is stripped from exports).
 * - Enforces a 256 KB payload size cap on imports to prevent memory exhaustion DoS.
 * - Sanitizes all imported tokens via `TokenManager::save_tokens()`, safelist classes via
 *   `ClassExtractor::normalize_and_validate()`, and URLs via `SecurityHelper::sanitize_remote_endpoint_url()`.
 *
 * @package TWBlocks\Admin
 */

declare(strict_types=1);

namespace TWBlocks\Admin;

use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\ClassExtractor;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;
use TWBlocks\Patterns\PatternRegistry;
use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PortabilityManager
 */
final class PortabilityManager {

	/**
	 * Maximum allowed JSON import payload size (256 KB).
	 */
	public const MAX_IMPORT_BYTES = 262144;

	/**
	 * Schema version identifier for export bundles.
	 */
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * TokenManager instance.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * CssEngine instance.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * CacheManager instance.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * PatternRegistry instance.
	 *
	 * @var PatternRegistry
	 */
	private PatternRegistry $pattern_registry;

	/**
	 * Constructor.
	 *
	 * @param TokenManager    $token_manager    Token manager service.
	 * @param CssEngine       $css_engine       CSS engine service.
	 * @param CacheManager    $cache_manager    Cache manager service.
	 * @param PatternRegistry $pattern_registry Pattern registry service.
	 */
	public function __construct(
		TokenManager $token_manager,
		CssEngine $css_engine,
		CacheManager $cache_manager,
		PatternRegistry $pattern_registry
	) {
		$this->token_manager    = $token_manager;
		$this->css_engine       = $css_engine;
		$this->cache_manager    = $cache_manager;
		$this->pattern_registry = $pattern_registry;
	}

	/**
	 * Initialize REST routes for exporting and importing configuration bundles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API endpoints for JSON export and import.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'tw-blocks/v1',
			'/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'rest_export_bundle' ),
			)
		);

		register_rest_route(
			'tw-blocks/v1',
			'/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'callback'            => array( $this, 'rest_import_bundle' ),
			)
		);
	}

	/**
	 * Generate a clean, UTF-8 JSON export bundle (strips `remote_api_key` secret).
	 *
	 * @return array<string, mixed>
	 */
	public function create_export_payload(): array {
		$settings = get_option( SettingsPage::OPTION_KEY, SettingsPage::get_default_settings() );
		if ( ! is_array( $settings ) ) {
			$settings = SettingsPage::get_default_settings();
		}

		// Never include HMAC secret keys in exported JSON bundles.
		unset( $settings['remote_api_key'] );

		return array(
			'plugin'         => 'tw-blocks',
			'schema_version' => self::SCHEMA_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site_locale'    => PersianHelper::get_site_locale_attrs(),
			'tokens'         => $this->token_manager->get_tokens(),
			'settings'       => $settings,
			'patterns_count' => count( $this->pattern_registry->get_all_patterns() ),
		);
	}

	/**
	 * Validate and import a JSON configuration bundle, then purge and rebuild the CSS cache.
	 *
	 * @param string|array<string, mixed> $raw_input Raw JSON string or decoded associative array.
	 * @return array{success: bool, message: string, tokens_hash?: string}
	 */
	public function import_payload( string|array $raw_input ): array {
		if ( is_string( $raw_input ) ) {
			if ( strlen( $raw_input ) > self::MAX_IMPORT_BYTES ) {
				return array(
					'success' => false,
					'message' => __( 'Import file exceeds the 256 KB security limit.', 'tw-blocks' ),
				);
			}
			$decoded = json_decode( $raw_input, true );
		} else {
			$decoded = $raw_input;
		}

		if ( ! is_array( $decoded ) || ( $decoded['plugin'] ?? '' ) !== 'tw-blocks' ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid TW Blocks export JSON format.', 'tw-blocks' ),
			);
		}

		if ( isset( $decoded['tokens'] ) && is_array( $decoded['tokens'] ) ) {
			$this->token_manager->save_tokens( $decoded['tokens'] );
		}

		if ( isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) {
			$in_settings = $decoded['settings'];
			$existing    = get_option( SettingsPage::OPTION_KEY, SettingsPage::get_default_settings() );
			if ( ! is_array( $existing ) ) {
				$existing = SettingsPage::get_default_settings();
			}

			$extractor      = new ClassExtractor( $this->token_manager );
			$valid_safelist = isset( $in_settings['safelist'] )
				? implode( ' ', $extractor->normalize_and_validate( (string) $in_settings['safelist'] ) )
				: (string) ( $existing['safelist'] ?? '' );

			$allowed_drivers = array( 'auto', 'local_binary', 'remote_api', 'fallback_safelist' );
			$pref_driver     = isset( $in_settings['preferred_driver'] ) ? sanitize_key( (string) $in_settings['preferred_driver'] ) : 'auto';
			if ( ! in_array( $pref_driver, $allowed_drivers, true ) ) {
				$pref_driver = 'auto';
			}

			$inline_raw = isset( $in_settings['inline_threshold_kb'] )
				? PersianHelper::normalize_digits( (string) $in_settings['inline_threshold_kb'] )
				: '12';

			$merged_settings = array(
				'preferred_driver'           => $pref_driver,
				'remote_api_url'             => isset( $in_settings['remote_api_url'] )
					? SecurityHelper::sanitize_remote_endpoint_url( (string) $in_settings['remote_api_url'] )
					: (string) ( $existing['remote_api_url'] ?? '' ),
				// Preserve existing secret key; never overwrite with untrusted JSON import.
				'remote_api_key'             => (string) ( $existing['remote_api_key'] ?? '' ),
				'remote_api_timeout'         => max( 3, min( 25, (int) ( $in_settings['remote_api_timeout'] ?? 8 ) ) ),
				'inline_threshold_kb'        => max( 0, min( 50, (float) $inline_raw ) ),
				'safelist'                   => $valid_safelist,
				'enable_persian_digits'      => ! empty( $in_settings['enable_persian_digits'] ),
				'enable_jalali_date'         => ! empty( $in_settings['enable_jalali_date'] ),
				'disable_wp_global_styles'   => ! empty( $in_settings['disable_wp_global_styles'] ),
				'disable_core_block_library' => ! empty( $in_settings['disable_core_block_library'] ),
				'delete_on_uninstall'        => ! empty( $in_settings['delete_on_uninstall'] ),
			);

			update_option( SettingsPage::OPTION_KEY, $merged_settings, false );
		}

		// Purge old CSS files and rebuild base scope immediately.
		$this->cache_manager->purge_all();
		$this->css_engine->compile_base_scope( true );

		return array(
			'success'     => true,
			'message'     => __( 'Design tokens and settings imported successfully. CSS cache was regenerated.', 'tw-blocks' ),
			'tokens_hash' => $this->token_manager->get_tokens_hash(),
		);
	}

	/**
	 * REST Callback: Export configuration bundle.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_export_bundle( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		return new \WP_REST_Response( $this->create_export_payload(), 200 );
	}

	/**
	 * REST Callback: Import configuration bundle with rate limiting.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_import_bundle( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! SecurityHelper::check_rest_rate_limit( 'import_bundle', 10, 60 ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many import attempts. Please wait a moment.', 'tw-blocks' ),
				),
				429
			);
		}

		$body   = (string) $request->get_body();
		$result = $this->import_payload( $body );

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}
}
