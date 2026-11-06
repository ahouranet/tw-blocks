<?php
/**
 * Persian & RTL-First Admin Settings Page for TW Blocks.
 * All user-facing strings use English source strings with `tw-blocks` text domain and translate to Persian (`fa_IR`).
 *
 * @package TWBlocks\Admin
 */

declare(strict_types=1);

namespace TWBlocks\Admin;

use TWBlocks\CSS\CacheManager;
use TWBlocks\CSS\ClassExtractor;
use TWBlocks\CSS\CssEngine;
use TWBlocks\CSS\TokenManager;
use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 */
final class SettingsPage {

	/**
	 * Option key for general plugin settings.
	 */
	public const OPTION_KEY = 'twb_settings';

	/**
	 * Nonce action for saving settings.
	 */
	private const NONCE_ACTION = 'twb_save_settings_nonce';

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
	 * Constructor.
	 *
	 * @param TokenManager $token_manager Token manager service.
	 * @param CssEngine    $css_engine    CSS engine service.
	 * @param CacheManager $cache_manager Cache manager service.
	 */
	public function __construct(
		TokenManager $token_manager,
		CssEngine $css_engine,
		CacheManager $cache_manager
	) {
		$this->token_manager = $token_manager;
		$this->css_engine    = $css_engine;
		$this->cache_manager = $cache_manager;
	}

	/**
	 * Initialize admin menu, assets, and form submission handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Default general settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'preferred_driver'           => 'auto',
			'remote_api_url'             => '',
			'remote_api_key'             => '',
			'remote_api_timeout'         => 8,
			'inline_threshold_kb'        => 12,
			'safelist'                   => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ps-4 pe-4 ms-auto me-auto text-start text-end rtl:-scale-x-100',
			'enable_persian_digits'      => true,
			'enable_jalali_date'         => true,
			'disable_wp_global_styles'   => false,
			'disable_core_block_library' => false,
			'wc_cart_checkout_mode'      => 'classic',
			'delete_on_uninstall'        => false,
		);
	}

	/**
	 * Get merged settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_default_settings();
		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	/**
	 * Register settings menu page in WordPress Admin.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'TW Blocks — Settings & Design Tokens', 'tw-blocks' ),
			__( 'TW Blocks', 'tw-blocks' ),
			'edit_theme_options',
			'tw-blocks-settings',
			array( $this, 'render_page' ),
			'dashicons-layout',
			58
		);
	}

	/**
	 * Enqueue RTL-aware admin styles and environment test script on the settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_tw-blocks-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'tw-blocks-admin-rtl',
			TWB_URL . 'assets/css/admin-rtl.css',
			array(),
			TWB_VERSION
		);

		wp_enqueue_script(
			'tw-blocks-admin',
			TWB_URL . 'assets/js/admin-settings.js',
			array( 'wp-i18n' ),
			TWB_VERSION,
			true
		);

		wp_set_script_translations( 'tw-blocks-admin', 'tw-blocks', TWB_PATH . 'languages' );

		wp_localize_script(
			'tw-blocks-admin',
			'twbAdminConfig',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'tw-blocks/v1/test-env' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Handle settings & design tokens form submission with capability check, nonce verification,
	 * Persian digit normalization, and strict sanitization.
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! isset( $_POST['twb_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to modify TW Blocks settings.', 'tw-blocks' ) );
		}

		check_admin_referer( self::NONCE_ACTION, 'twb_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['twb_action'] ) );

		if ( 'purge_cache' === $action ) {
			$deleted = $this->cache_manager->purge_all();
			$this->css_engine->compile_base_scope( true );
			add_settings_error(
				'twb_messages',
				'twb_cache_purged',
				sprintf(
					/* translators: %s: localized count of purged files */
					__( 'CSS cache purged (%s files removed) and base stylesheet rebuilt successfully.', 'tw-blocks' ),
					PersianHelper::localize_digits( $deleted, true )
				),
				'updated'
			);
			return;
		}

		if ( 'save_all' !== $action ) {
			return;
		}

		// 1. Sanitize and save Design Tokens.
		$raw_tokens = isset( $_POST['twb_tokens'] ) && is_array( $_POST['twb_tokens'] )
			? wp_unslash( $_POST['twb_tokens'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		$this->token_manager->save_tokens( $raw_tokens );

		// 2. Sanitize and save General Settings (normalizing Persian/Arabic digits on numeric fields).
		$raw_settings = isset( $_POST['twb_settings'] ) && is_array( $_POST['twb_settings'] )
			? wp_unslash( $_POST['twb_settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$allowed_drivers = array( 'auto', 'local_binary', 'remote_api', 'fallback_safelist' );
		$pref_driver     = isset( $raw_settings['preferred_driver'] ) ? sanitize_key( (string) $raw_settings['preferred_driver'] ) : 'auto';
		if ( ! in_array( $pref_driver, $allowed_drivers, true ) ) {
			$pref_driver = 'auto';
		}

		$timeout_raw = isset( $raw_settings['remote_api_timeout'] ) ? PersianHelper::normalize_digits( (string) $raw_settings['remote_api_timeout'] ) : '8';
		$inline_raw  = isset( $raw_settings['inline_threshold_kb'] ) ? PersianHelper::normalize_digits( (string) $raw_settings['inline_threshold_kb'] ) : '12';

		// Validate safelist classes strictly against ClassExtractor whitelist.
		$safelist_input = isset( $raw_settings['safelist'] ) ? (string) $raw_settings['safelist'] : '';
		$extractor      = new ClassExtractor( $this->token_manager );
		$valid_safelist = implode( ' ', $extractor->normalize_and_validate( $safelist_input ) );

		$sanitized_settings = array(
			'preferred_driver'           => $pref_driver,
			'remote_api_url'             => isset( $raw_settings['remote_api_url'] ) ? SecurityHelper::sanitize_remote_endpoint_url( (string) $raw_settings['remote_api_url'] ) : '',
			'remote_api_key'             => isset( $raw_settings['remote_api_key'] ) ? sanitize_text_field( trim( (string) $raw_settings['remote_api_key'] ) ) : '',
			'remote_api_timeout'         => max( 3, min( 25, (int) $timeout_raw ) ),
			'inline_threshold_kb'        => max( 0, min( 50, (float) $inline_raw ) ),
			'safelist'                   => $valid_safelist,
			'enable_persian_digits'      => ! empty( $raw_settings['enable_persian_digits'] ),
			'enable_jalali_date'         => ! empty( $raw_settings['enable_jalali_date'] ),
			'disable_wp_global_styles'   => ! empty( $raw_settings['disable_wp_global_styles'] ),
			'disable_core_block_library' => ! empty( $raw_settings['disable_core_block_library'] ),
			'wc_cart_checkout_mode'      => isset( $raw_settings['wc_cart_checkout_mode'] ) && 'block' === $raw_settings['wc_cart_checkout_mode'] ? 'block' : 'classic',
			'delete_on_uninstall'        => ! empty( $raw_settings['delete_on_uninstall'] ),
		);

		update_option( self::OPTION_KEY, $sanitized_settings, false );

		// Rebuild base CSS immediately after saving settings/tokens.
		$this->cache_manager->purge_all();
		$this->css_engine->compile_base_scope( true );

		add_settings_error(
			'twb_messages',
			'twb_saved',
			__( 'Settings and design tokens saved. CSS cache was regenerated.', 'tw-blocks' ),
			'updated'
		);
	}

	/**
	 * Render the RTL Persian Settings Page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$settings = $this->get_settings();
		$tokens   = $this->token_manager->get_tokens();
		$env      = $this->css_engine->test_environment();
		?>
		<div class="wrap twb-admin-wrap" dir="rtl">
			<header class="twb-admin-header">
				<div>
					<h1><?php echo esc_html__( 'TW Blocks — Settings & Design Tokens', 'tw-blocks' ); ?></h1>
					<p class="description">
						<?php echo esc_html__( 'Lightweight, SEO-first Gutenberg block builder powered by Tailwind CSS v4 with native Persian, RTL, and Jalali calendar support.', 'tw-blocks' ); ?>
					</p>
				</div>
				<div class="twb-admin-actions">
					<button type="button" id="twb-run-env-test" class="button button-secondary">
						<?php echo esc_html__( 'Test Environment', 'tw-blocks' ); ?>
					</button>
					<form method="post" style="display:inline-block; margin-inline-start: 8px;">
						<?php wp_nonce_field( self::NONCE_ACTION, 'twb_nonce' ); ?>
						<input type="hidden" name="twb_action" value="purge_cache" />
						<button type="submit" class="button button-secondary">
							<?php echo esc_html__( 'Purge & Rebuild CSS Cache', 'tw-blocks' ); ?>
						</button>
					</form>
				</div>
			</header>

			<?php settings_errors( 'twb_messages' ); ?>

			<!-- Environment Diagnostics Panel -->
			<section id="twb-env-report" class="twb-card">
				<h2><?php echo esc_html__( 'CSS Build Engine & Active Driver Status', 'tw-blocks' ); ?></h2>
				<p>
					<?php echo esc_html__( 'Currently Active Driver:', 'tw-blocks' ); ?>
					<strong id="twb-active-driver-label"><?php echo esc_html( (string) $env['active_driver_label'] ); ?></strong>
				</p>
				<div class="twb-driver-grid" id="twb-driver-diagnostics">
					<?php foreach ( $env['drivers'] as $driver_diag ) : ?>
						<div class="twb-driver-box <?php echo $driver_diag['available'] ? 'is-available' : 'is-unavailable'; ?>">
							<h3>
								<?php echo esc_html( (string) $driver_diag['label'] ); ?>
								<span class="twb-status-text">
									<?php echo $driver_diag['available'] ? esc_html__( 'Ready', 'tw-blocks' ) : esc_html__( 'Unavailable', 'tw-blocks' ); ?>
								</span>
							</h3>
							<ul>
								<?php foreach ( $driver_diag['checks'] as $chk ) : ?>
									<li>
										<strong><?php echo $chk['passed'] ? '✓' : '✗'; ?> <?php echo esc_html( (string) $chk['name'] ); ?>:</strong>
										<span><?php echo esc_html( (string) $chk['message'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, 'twb_nonce' ); ?>
				<input type="hidden" name="twb_action" value="save_all" />

				<!-- 1. CSS Engine & Driver Configuration -->
				<section class="twb-card">
					<h2><?php echo esc_html__( '1. CSS Build Engine & Safelist Configuration', 'tw-blocks' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="twb-preferred-driver"><?php echo esc_html__( 'Preferred Build Driver', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<select id="twb-preferred-driver" name="twb_settings[preferred_driver]">
									<option value="auto" <?php selected( $settings['preferred_driver'], 'auto' ); ?>>
										<?php echo esc_html__( 'Automatic (Recommended: Driver A -> Driver B -> Driver C)', 'tw-blocks' ); ?>
									</option>
									<option value="local_binary" <?php selected( $settings['preferred_driver'], 'local_binary' ); ?>>
										<?php echo esc_html__( 'Driver A — Local Tailwind v4 Binary', 'tw-blocks' ); ?>
									</option>
									<option value="remote_api" <?php selected( $settings['preferred_driver'], 'remote_api' ); ?>>
										<?php echo esc_html__( 'Driver B — Remote Build API (HMAC Signed)', 'tw-blocks' ); ?>
									</option>
									<option value="fallback_safelist" <?php selected( $settings['preferred_driver'], 'fallback_safelist' ); ?>>
										<?php echo esc_html__( 'Driver C — Bundled Safelist Fallback', 'tw-blocks' ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-remote-url"><?php echo esc_html__( 'Remote Build API URL (Driver B)', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="url" id="twb-remote-url" name="twb_settings[remote_api_url]"
									value="<?php echo esc_attr( (string) $settings['remote_api_url'] ); ?>"
									class="regular-text twb-code-input" dir="ltr" placeholder="https://build.example.com/api/v1/compile" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-remote-key"><?php echo esc_html__( 'HMAC Secret Key (Driver B)', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="password" id="twb-remote-key" name="twb_settings[remote_api_key]"
									value="<?php echo esc_attr( (string) $settings['remote_api_key'] ); ?>"
									class="regular-text twb-code-input" dir="ltr" autocomplete="off" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-inline-threshold"><?php echo esc_html__( 'Inline CSS Threshold (KB)', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="text" id="twb-inline-threshold" name="twb_settings[inline_threshold_kb]"
									value="<?php echo esc_attr( (string) $settings['inline_threshold_kb'] ); ?>"
									class="small-text" dir="auto" />
								<p class="description">
									<?php echo esc_html__( 'Accepts both Persian and Latin digits (Default: 12 KB). If merged page CSS is under this threshold, it is inlined as <style> instead of <link>.', 'tw-blocks' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-safelist"><?php echo esc_html__( 'Safelist Classes (Dynamic Utilities)', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<textarea id="twb-safelist" name="twb_settings[safelist]" rows="3"
									class="large-text code twb-code-input" dir="ltr"><?php echo esc_textarea( (string) $settings['safelist'] ); ?></textarea>
								<p class="description">
									<?php echo esc_html__( 'Enter space-separated Tailwind utility classes that are conditionally toggled at runtime. Direction is strictly LTR.', 'tw-blocks' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</section>

				<!-- 2. Design Tokens (@theme & theme.json Bridge) -->
				<section class="twb-card">
					<h2><?php echo esc_html__( '2. Design Tokens (@theme & theme.json Synchronization)', 'tw-blocks' ); ?></h2>
					<div class="twb-tokens-grid">
						<?php foreach ( $tokens['colors'] as $color_key => $color_val ) : ?>
							<div class="twb-token-field">
								<label for="twb-color-<?php echo esc_attr( $color_key ); ?>">
									<?php echo esc_html( ucfirst( $color_key ) ); ?>
									<code dir="ltr">--color-<?php echo esc_html( $color_key ); ?></code>
								</label>
								<input type="text" id="twb-color-<?php echo esc_attr( $color_key ); ?>"
									name="twb_tokens[colors][<?php echo esc_attr( $color_key ); ?>]"
									value="<?php echo esc_attr( (string) $color_val ); ?>"
									class="twb-code-input" dir="ltr" />
							</div>
						<?php endforeach; ?>
					</div>

					<hr />

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="twb-line-height-body"><?php echo esc_html__( 'Persian Body Line-Height', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="text" id="twb-line-height-body" name="twb_tokens[typography][line_height_body]"
									value="<?php echo esc_attr( (string) $tokens['typography']['line_height_body'] ); ?>"
									class="small-text" dir="auto" />
								<p class="description">
									<?php echo esc_html__( 'Recommended range for Persian script is 1.7 to 1.9 (Default: 1.8). Letter-spacing is locked to 0em for Persian text.', 'tw-blocks' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-custom-woff2"><?php echo esc_html__( 'Custom Persian Font (Local .woff2 Upload)', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="text" id="twb-custom-font-name" name="twb_tokens[typography][custom_font_name]"
									value="<?php echo esc_attr( (string) $tokens['typography']['custom_font_name'] ); ?>"
									placeholder="<?php echo esc_attr__( 'Font Family Name (e.g. IRANSansX)', 'tw-blocks' ); ?>"
									dir="auto" class="regular-text" />
								<input type="url" id="twb-custom-woff2" name="twb_tokens[typography][custom_woff2_url]"
									value="<?php echo esc_attr( (string) $tokens['typography']['custom_woff2_url'] ); ?>"
									placeholder="https://example.com/wp-content/uploads/font.woff2"
									dir="ltr" class="regular-text twb-code-input" />
								<button type="button" id="twb-upload-woff2-btn" class="button button-secondary">
									<?php echo esc_html__( 'Select / Upload WOFF2', 'tw-blocks' ); ?>
								</button>
								<p class="description">
									<?php echo esc_html__( 'When empty, self-hosted Vazirmatn Variable (woff2) is loaded locally with font-display: swap and zero external CDN requests.', 'tw-blocks' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="twb-container-width"><?php echo esc_html__( 'Primary Container Max-Width', 'tw-blocks' ); ?></label>
							</th>
							<td>
								<input type="text" id="twb-container-width" name="twb_tokens[layout][container_width]"
									value="<?php echo esc_attr( (string) $tokens['layout']['container_width'] ); ?>"
									class="small-text twb-code-input" dir="ltr" />
							</td>
						</tr>
					</table>
				</section>

				<!-- 3. Persian Localization, Jalali Calendar & Core Optimization -->
				<section class="twb-card">
					<h2><?php echo esc_html__( '3. Persian Localization, Jalali Calendar & Core Optimization', 'tw-blocks' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Persian Digits & Jalali Date', 'tw-blocks' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="twb_settings[enable_persian_digits]" value="1"
											<?php checked( ! empty( $settings['enable_persian_digits'] ) ); ?> />
										<?php echo esc_html__( 'Convert dynamic block numerals to Persian digits (۰-۹) via twb_localize_digits()', 'tw-blocks' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="twb_settings[enable_jalali_date]" value="1"
											<?php checked( ! empty( $settings['enable_jalali_date'] ) ); ?> />
										<?php echo esc_html__( 'Display Jalali (Shamsi) dates in dynamic blocks (uses WP-Parsidate if active, or built-in converter)', 'tw-blocks' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WordPress Core CSS Optimization', 'tw-blocks' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="twb_settings[disable_wp_global_styles]" value="1"
											<?php checked( ! empty( $settings['disable_wp_global_styles'] ) ); ?> />
										<?php echo esc_html__( 'Disable WordPress global-styles inline CSS (for sites built exclusively with TW Blocks)', 'tw-blocks' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="twb_settings[disable_core_block_library]" value="1"
											<?php checked( ! empty( $settings['disable_core_block_library'] ) ); ?> />
										<?php echo esc_html__( 'Dequeue core wp-block-library.css on the front-end', 'tw-blocks' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</section>

				<?php submit_button( __( 'Save Settings & Rebuild CSS', 'tw-blocks' ) ); ?>
			</form>
		</div>
		<?php
	}
}
