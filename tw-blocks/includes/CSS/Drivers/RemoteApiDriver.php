<?php
/**
 * Driver B: Remote Build API Driver.
 * Sends validated class list (never full HTML) + @theme CSS to a remote endpoint signed with HMAC-SHA256.
 * Includes timeout, retry with backoff, and graceful failure.
 *
 * @package TWBlocks\CSS\Drivers
 */

declare(strict_types=1);

namespace TWBlocks\CSS\Drivers;

use TWBlocks\CSS\DriverInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RemoteApiDriver
 */
final class RemoteApiDriver implements DriverInterface {

	/**
	 * Maximum retry attempts on transient network failure.
	 */
	private const MAX_RETRIES = 2;

	/**
	 * Default HTTP request timeout in seconds.
	 */
	private const TIMEOUT_SECONDS = 8;

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'remote_api';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'Driver B — Remote Build API (HMAC Signed)', 'tw-blocks' );
	}

	/**
	 * Retrieve configured remote endpoint URL and API secret key from settings.
	 *
	 * @return array{endpoint: string, api_key: string, timeout: int}
	 */
	private function get_config(): array {
		$settings = get_option( 'twb_settings', array() );
		$endpoint = isset( $settings['remote_api_url'] ) ? trim( (string) $settings['remote_api_url'] ) : '';
		$api_key  = isset( $settings['remote_api_key'] ) ? trim( (string) $settings['remote_api_key'] ) : '';
		$timeout  = isset( $settings['remote_api_timeout'] ) ? max( 3, min( 25, (int) $settings['remote_api_timeout'] ) ) : self::TIMEOUT_SECONDS;

		return array(
			'endpoint' => $endpoint,
			'api_key'  => $api_key,
			'timeout'  => $timeout,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		$cfg = $this->get_config();
		return '' !== $cfg['endpoint'] && '' !== $cfg['api_key'] && filter_var( $cfg['endpoint'], FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function diagnose(): array {
		$cfg      = $this->get_config();
		$checks   = array();
		$url_valid = '' !== $cfg['endpoint'] && false !== filter_var( $cfg['endpoint'], FILTER_VALIDATE_URL );
		$key_valid = strlen( $cfg['api_key'] ) >= 16;

		$checks[] = array(
			'name'    => __( 'Remote Build Endpoint URL', 'tw-blocks' ),
			'passed'  => $url_valid,
			'message' => $url_valid
				/* translators: %s: Endpoint URL */
				? sprintf( __( 'Configured endpoint: %s', 'tw-blocks' ), $cfg['endpoint'] )
				: __( 'Remote API URL is not configured or is invalid.', 'tw-blocks' ),
		);

		$checks[] = array(
			'name'    => __( 'HMAC Secret Key', 'tw-blocks' ),
			'passed'  => $key_valid,
			'message' => $key_valid
				? __( 'Secret key is configured (16+ characters).', 'tw-blocks' )
				: __( 'Secret key is missing or shorter than 16 characters.', 'tw-blocks' ),
		);

		$ping_ok  = false;
		$ping_msg = __( 'Skipped because URL or HMAC key is not configured.', 'tw-blocks' );

		if ( $url_valid && $key_valid ) {
			$test_build = $this->compile(
				array( 'flex', 'items-center', 'ps-4', 'pe-4', 'text-start' ),
				"@theme {\n  --color-primary: #0d9488;\n}",
				''
			);

			if ( $test_build['success'] ) {
				$ping_ok  = true;
				$ping_msg = sprintf(
					/* translators: %d: byte length */
					__( 'Remote API responded with valid minified CSS (%d bytes).', 'tw-blocks' ),
					strlen( $test_build['css'] )
				);
			} else {
				$ping_msg = sprintf(
					/* translators: %s: error message */
					__( 'Remote API test failed: %s', 'tw-blocks' ),
					$test_build['error'] ?? __( 'Unknown error', 'tw-blocks' )
				);
			}
		}

		$checks[] = array(
			'name'    => __( 'Signed Build Handshake', 'tw-blocks' ),
			'passed'  => $ping_ok,
			'message' => $ping_msg,
		);

		return array(
			'available' => $url_valid && $key_valid && $ping_ok,
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
				'error'   => __( 'Remote API URL or HMAC key is not configured.', 'tw-blocks' ),
			);
		}

		$cfg       = $this->get_config();
		$timestamp = (string) time();

		$payload_array = array(
			'version'      => '4.0',
			'classes'      => array_values( $classes ),
			'themeCss'     => $theme_css,
			'preflightCss' => $preflight_css,
			'timestamp'    => (int) $timestamp,
		);

		$body = (string) wp_json_encode( $payload_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		// Compute HMAC-SHA256 over `<timestamp>.<json_body>`.
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $cfg['api_key'] );

		$args = array(
			'timeout'     => $cfg['timeout'],
			'redirection' => 2,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type'      => 'application/json; charset=utf-8',
				'Accept'            => 'application/json',
				'X-TWB-Timestamp'   => $timestamp,
				'X-TWB-Signature'   => 'sha256=' . $signature,
				'X-TWB-Site-Locale' => function_exists( 'get_locale' ) ? get_locale() : 'fa_IR',
			),
			'body'        => $body,
		);

		$last_error = '';

		for ( $attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				// Exponential backoff: 250ms, 500ms.
				usleep( $attempt * 250000 );
			}

			$response = wp_remote_post( $cfg['endpoint'], $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				continue;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$raw_body = (string) wp_remote_retrieve_body( $response );

			if ( 200 === $code ) {
				$decoded = json_decode( $raw_body, true );
				if ( is_array( $decoded ) && ! empty( $decoded['css'] ) && is_string( $decoded['css'] ) ) {
					return array(
						'success' => true,
						'css'     => $decoded['css'],
						'driver'  => $this->get_id(),
					);
				}
				$last_error = __( 'Remote server returned invalid JSON payload.', 'tw-blocks' );
				break;
			}

			// Do not retry on 4xx client/validation/signature errors (except 429 rate limit).
			if ( $code >= 400 && $code < 500 && 429 !== $code ) {
				$decoded    = json_decode( $raw_body, true );
				$server_err = is_array( $decoded ) && isset( $decoded['error'] ) ? (string) $decoded['error'] : 'HTTP ' . $code;
				$last_error = $server_err;
				break;
			}

			$last_error = 'HTTP ' . $code;
		}

		return array(
			'success' => false,
			'css'     => '',
			'driver'  => $this->get_id(),
			'error'   => $last_error,
		);
	}
}
