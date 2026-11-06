<?php
/**
 * Core CSS Build Engine orchestrating Driver A, Driver B, and Driver C.
 * Triggers automatic hash-checked builds on `save_post`, template updates, and token saves.
 *
 * @package TWBlocks\CSS
 */

declare(strict_types=1);

namespace TWBlocks\CSS;

use TWBlocks\CSS\Drivers\FallbackSafelistDriver;
use TWBlocks\CSS\Drivers\LocalBinaryDriver;
use TWBlocks\CSS\Drivers\RemoteApiDriver;
use TWBlocks\Security\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CssEngine
 */
final class CssEngine {

	/**
	 * TokenManager instance.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * CacheManager instance.
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache_manager;

	/**
	 * ClassExtractor instance.
	 *
	 * @var ClassExtractor
	 */
	private ClassExtractor $class_extractor;

	/**
	 * Registered drivers in priority order (A -> B -> C).
	 *
	 * @var array<string, DriverInterface>
	 */
	private array $drivers = array();

	/**
	 * Constructor.
	 *
	 * @param TokenManager   $token_manager   Token manager.
	 * @param CacheManager   $cache_manager   Cache manager.
	 * @param ClassExtractor $class_extractor Class extractor.
	 */
	public function __construct(
		TokenManager $token_manager,
		CacheManager $cache_manager,
		ClassExtractor $class_extractor
	) {
		$this->token_manager   = $token_manager;
		$this->cache_manager   = $cache_manager;
		$this->class_extractor = $class_extractor;

		$driver_a = new LocalBinaryDriver( $this->cache_manager );
		$driver_b = new RemoteApiDriver();
		$driver_c = new FallbackSafelistDriver();

		$this->drivers = array(
			$driver_a->get_id() => $driver_a,
			$driver_b->get_id() => $driver_b,
			$driver_c->get_id() => $driver_c,
		);
	}

	/**
	 * Initialize WordPress hooks for automatic compilation on `save_post` and admin notices.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
		add_action( 'admin_notices', array( $this, 'maybe_render_fallback_notice' ) );
	}

	/**
	 * Select the best available driver automatically (A -> B -> C) or respect explicit admin preference if available.
	 *
	 * @return DriverInterface
	 */
	public function select_driver(): DriverInterface {
		$settings  = get_option( 'twb_settings', array() );
		$preferred = isset( $settings['preferred_driver'] ) ? (string) $settings['preferred_driver'] : 'auto';

		if ( 'auto' !== $preferred && isset( $this->drivers[ $preferred ] ) ) {
			if ( $this->drivers[ $preferred ]->is_available() ) {
				return $this->drivers[ $preferred ];
			}
		}

		foreach ( $this->drivers as $driver ) {
			if ( $driver->is_available() ) {
				return $driver;
			}
		}

		return $this->drivers['fallback_safelist'];
	}

	/**
	 * Hook: Compile post/template CSS upon `save_post`.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}

		// If a Block Template or Template Part was edited in Site Editor, rebuild base/template scopes.
		if ( 'wp_template_part' === $post->post_type ) {
			$this->compile_base_scope( true );
			return;
		}

		if ( 'wp_template' === $post->post_type ) {
			$this->compile_template_scope( $post->post_name, true );
			return;
		}

		$this->compile_post_scope( $post );
	}

	/**
	 * Compile the global `base` scope (tokens + preflight-lite + header/footer parts + safelist).
	 * Only compiles when the computed hash has changed or `$force` is true.
	 *
	 * @param bool $force Force recompilation even on cache hit.
	 * @return array<string, mixed> Build metadata.
	 */
	public function compile_base_scope( bool $force = false ): array {
		$extracted = $this->class_extractor->extract_for_base_scope();
		$classes   = $extracted['classes'];
		$hash      = $extracted['hash'];
		$scope     = 'base';

		$existing = $this->cache_manager->get_scope_meta( $scope );
		if ( ! $force && null !== $existing && ( $existing['hash'] ?? '' ) === $hash && $this->cache_manager->has( $scope, $hash ) ) {
			$existing['cache_status'] = 'HIT';
			return $existing;
		}

		$build = $this->execute_build_with_fallback(
			$classes,
			$this->token_manager->generate_theme_css( false ),
			$this->token_manager->get_preflight_css()
		);

		$written = $this->cache_manager->write_atomic( $scope, $hash, $build['css'] );

		$meta = array(
			'scope'         => $scope,
			'hash'          => $hash,
			'filename'      => $written['filename'] ?? '',
			'bytes'         => $written['bytes'] ?? strlen( $build['css'] ),
			'class_count'   => count( $classes ),
			'classes'       => $classes,
			'driver'        => $build['driver'],
			'build_time_ms' => $build['build_time_ms'],
			'cache_status'  => 'MISS',
			'updated_at'    => time(),
		);

		$this->cache_manager->save_scope_meta( $scope, $meta );
		return $meta;
	}

	/**
	 * Compile a specific `template` scope (`single`, `archive`, `page`, etc.).
	 *
	 * @param string $template_slug Template slug.
	 * @param bool   $force         Force recompilation.
	 * @return array<string, mixed>
	 */
	public function compile_template_scope( string $template_slug, bool $force = false ): array {
		$clean_slug = sanitize_key( $template_slug );
		if ( '' === $clean_slug ) {
			$clean_slug = 'index';
		}

		$scope     = 'tpl-' . $clean_slug;
		$extracted = $this->class_extractor->extract_for_template_scope( $clean_slug );
		$classes   = $extracted['classes'];
		$hash      = $extracted['hash'];

		$existing = $this->cache_manager->get_scope_meta( $scope );
		if ( ! $force && null !== $existing && ( $existing['hash'] ?? '' ) === $hash && $this->cache_manager->has( $scope, $hash ) ) {
			$existing['cache_status'] = 'HIT';
			return $existing;
		}

		$build = $this->execute_build_with_fallback(
			$classes,
			$this->token_manager->generate_theme_css( false ),
			''
		);

		$written = $this->cache_manager->write_atomic( $scope, $hash, $build['css'] );

		$meta = array(
			'scope'         => $scope,
			'hash'          => $hash,
			'filename'      => $written['filename'] ?? '',
			'bytes'         => $written['bytes'] ?? strlen( $build['css'] ),
			'class_count'   => count( $classes ),
			'classes'       => $classes,
			'driver'        => $build['driver'],
			'build_time_ms' => $build['build_time_ms'],
			'cache_status'  => 'MISS',
			'updated_at'    => time(),
		);

		$this->cache_manager->save_scope_meta( $scope, $meta );
		return $meta;
	}

	/**
	 * Compile a specific `post` scope (`post-{ID}`).
	 * Only triggers a build when the post's extracted class hash or token hash has changed.
	 *
	 * @param int|\WP_Post $post  Post ID or object.
	 * @param bool         $force Force recompilation.
	 * @return array<string, mixed>
	 */
	public function compile_post_scope( int|\WP_Post $post, bool $force = false ): array {
		$post_obj = is_int( $post ) ? get_post( $post ) : $post;
		if ( ! $post_obj instanceof \WP_Post ) {
			return array();
		}

		$post_id   = (int) $post_obj->ID;
		$scope     = 'post-' . $post_id;
		$extracted = $this->class_extractor->extract_for_post_scope( $post_obj );
		$classes   = $extracted['classes'];
		$hash      = $extracted['hash'];

		$existing = $this->cache_manager->get_post_meta( $post_id );
		if ( ! $force && null !== $existing && ( $existing['hash'] ?? '' ) === $hash && $this->cache_manager->has( $scope, $hash ) ) {
			$existing['cache_status'] = 'HIT';
			return $existing;
		}

		$build = $this->execute_build_with_fallback(
			$classes,
			$this->token_manager->generate_theme_css( false ),
			''
		);

		$written = $this->cache_manager->write_atomic( $scope, $hash, $build['css'] );

		$meta = array(
			'scope'         => $scope,
			'hash'          => $hash,
			'filename'      => $written['filename'] ?? '',
			'bytes'         => $written['bytes'] ?? strlen( $build['css'] ),
			'class_count'   => count( $classes ),
			'classes'       => $classes,
			'driver'        => $build['driver'],
			'build_time_ms' => $build['build_time_ms'],
			'cache_status'  => 'MISS',
			'updated_at'    => time(),
		);

		$this->cache_manager->save_post_meta( $post_id, $meta );
		return $meta;
	}

	/**
	 * Compile an on-the-fly preview CSS string for the Gutenberg Editor live preview REST endpoint.
	 *
	 * @param array<int, string>|string $raw_classes Raw classes from editor state.
	 * @return array{css: string, hash: string, driver: string, class_count: int, build_time_ms: float}
	 */
	public function compile_editor_preview( array|string $raw_classes ): array {
		$classes = Sanitizer::sanitize_build_input( $raw_classes );
		$hash    = $this->class_extractor->compute_hash( $classes, 'editor-preview' );
		$scope   = 'preview';

		if ( $this->cache_manager->has( $scope, $hash ) ) {
			$cached_css = (string) $this->cache_manager->get_contents( $scope, $hash );
			return array(
				'css'           => $cached_css,
				'hash'          => $hash,
				'driver'        => 'cache_hit',
				'class_count'   => count( $classes ),
				'build_time_ms' => 0.2,
			);
		}

		$build = $this->execute_build_with_fallback(
			$classes,
			$this->token_manager->generate_theme_css( false ),
			$this->token_manager->get_preflight_css()
		);

		$this->cache_manager->write_atomic( $scope, $hash, $build['css'] );

		return array(
			'css'           => $build['css'],
			'hash'          => $hash,
			'driver'        => $build['driver'],
			'class_count'   => count( $classes ),
			'build_time_ms' => $build['build_time_ms'],
		);
	}

	/**
	 * Execute build with automatic graceful fallback (Driver A -> Driver B -> Driver C).
	 * Centrally sanitizes all CSS build inputs via `TWBlocks\Security\Sanitizer` prior to driver execution.
	 *
	 * @param array<int, string> $classes       Validated classes.
	 * @param string             $theme_css     Theme CSS.
	 * @param string             $preflight_css Preflight CSS.
	 * @return array{css: string, driver: string, build_time_ms: float}
	 */
	private function execute_build_with_fallback( array $classes, string $theme_css, string $preflight_css ): array {
		$start     = microtime( true );
		$sanitized = Sanitizer::sanitize_css_build_payload(
			array(
				'classes'       => $classes,
				'theme_css'     => $theme_css,
				'preflight_css' => $preflight_css,
			)
		);

		$clean_classes   = $sanitized['classes'];
		$clean_theme     = $sanitized['theme_css'];
		$clean_preflight = $sanitized['preflight_css'];

		$primary = $this->select_driver();
		$result  = $primary->compile( $clean_classes, $clean_theme, $clean_preflight );

		if ( $result['success'] && '' !== trim( $result['css'] ) ) {
			if ( 'fallback_safelist' !== $primary->get_id() ) {
				delete_option( 'twb_using_fallback_driver' );
			}
			return array(
				'css'           => $result['css'],
				'driver'        => $result['driver'],
				'build_time_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ),
			);
		}

		// If Driver A failed at runtime, try Driver B if available, otherwise fall back to Driver C.
		if ( 'local_binary' === $primary->get_id() && $this->drivers['remote_api']->is_available() ) {
			$retry_b = $this->drivers['remote_api']->compile( $clean_classes, $clean_theme, $clean_preflight );
			if ( $retry_b['success'] && '' !== trim( $retry_b['css'] ) ) {
				delete_option( 'twb_using_fallback_driver' );
				return array(
					'css'           => $retry_b['css'],
					'driver'        => $retry_b['driver'],
					'build_time_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ),
				);
			}
		}

		$fallback = $this->drivers['fallback_safelist']->compile( $clean_classes, $clean_theme, $clean_preflight );

		return array(
			'css'           => $fallback['css'],
			'driver'        => $fallback['driver'],
			'build_time_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ),
		);
	}

	/**
	 * Run full environment diagnostic report across all 3 drivers for the "تست محیط" button in Settings.
	 *
	 * @return array<string, mixed>
	 */
	public function test_environment(): array {
		$report = array();
		foreach ( $this->drivers as $id => $driver ) {
			$report[ $id ] = $driver->diagnose();
		}

		$active = $this->select_driver();

		return array(
			'active_driver_id'    => $active->get_id(),
			'active_driver_label' => $active->get_label(),
			'php_version'         => PHP_VERSION,
			'wp_version'          => get_bloginfo( 'version' ),
			'upload_writable'     => wp_is_writable( $this->cache_manager->get_base_dir() ) || wp_mkdir_p( $this->cache_manager->get_css_dir() ),
			'drivers'             => $report,
		);
	}

	/**
	 * Display a Persian admin notice if Driver C (Fallback Safelist) is currently active.
	 *
	 * @return void
	 */
	public function maybe_render_fallback_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active = $this->select_driver();
		if ( 'fallback_safelist' !== $active->get_id() && ! get_option( 'twb_using_fallback_driver', false ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=tw-blocks-settings' );
		?>
		<div class="notice notice-warning is-dismissible" dir="rtl">
			<p>
				<strong><?php echo esc_html__( 'TW Blocks — Fallback Mode (Driver C: Safelist) is active:', 'tw-blocks' ); ?></strong>
				<?php
				echo esc_html__(
					'Neither the local Tailwind v4 binary (Driver A) nor the remote build API (Driver B) is available on this host. Serving pre-built Safelist CSS. Run "Test Environment" in settings for details.',
					'tw-blocks'
				);
				?>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-small" style="margin-inline-start: 8px;">
					<?php echo esc_html__( 'Open Settings & Test Environment', 'tw-blocks' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
