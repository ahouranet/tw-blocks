<?php
/**
 * Administrator-Only Front-End Debug Panel in WordPress Admin Bar.
 * All user-facing strings use English source strings with `tw-blocks` text domain and translate to Persian (`fa_IR`).
 *
 * @package TWBlocks\Admin
 */

declare(strict_types=1);

namespace TWBlocks\Admin;

use TWBlocks\CSS\CssEngine;
use TWBlocks\Frontend\AssetLoader;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DebugBar
 */
final class DebugBar {

	/**
	 * CssEngine instance.
	 *
	 * @var CssEngine
	 */
	private CssEngine $css_engine;

	/**
	 * AssetLoader instance.
	 *
	 * @var AssetLoader
	 */
	private AssetLoader $asset_loader;

	/**
	 * Constructor.
	 *
	 * @param CssEngine   $css_engine   CSS Engine service.
	 * @param AssetLoader $asset_loader Frontend Asset Loader service.
	 */
	public function __construct( CssEngine $css_engine, AssetLoader $asset_loader ) {
		$this->css_engine   = $css_engine;
		$this->asset_loader = $asset_loader;
	}

	/**
	 * Register Admin Bar hook for administrators.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_node' ), 999 );
	}

	/**
	 * Add TW Blocks telemetry node and child items to the WordPress Admin Bar on the front-end.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 * @return void
	 */
	public function register_admin_bar_node( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( is_admin() || ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$metrics = $this->asset_loader->get_current_page_metrics();
		$kb      = round( ( (int) ( $metrics['total_bytes'] ?? 0 ) ) / 1024, 2 );
		$kb_fa   = PersianHelper::localize_digits( (string) $kb, true );
		$status  = ( 'HIT' === ( $metrics['cache_status'] ?? 'HIT' ) )
			? __( 'Cache HIT', 'tw-blocks' )
			: __( 'Cache MISS (Rebuilt)', 'tw-blocks' );

		$title = sprintf(
			/* translators: 1: size in KB, 2: cache status */
			__( 'TW Blocks: %1$s KB · %2$s', 'tw-blocks' ),
			$kb_fa,
			$status
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'tw-blocks-debug',
				'title' => esc_html( $title ),
				'href'  => admin_url( 'admin.php?page=tw-blocks-settings' ),
			)
		);

		$files_list = ! empty( $metrics['files'] ) ? implode( ' , ', $metrics['files'] ) : __( 'None', 'tw-blocks' );
		$mode_label = ( 'inline' === ( $metrics['delivery_mode'] ?? 'inline' ) )
			? __( 'Inline (<style> under 12KB threshold)', 'tw-blocks' )
			: __( 'Static File (<link>)', 'tw-blocks' );

		$items = array(
			'files'   => sprintf(
				/* translators: %s: CSS file names */
				__( 'Loaded CSS File(s): %s', 'tw-blocks' ),
				$files_list
			),
			'mode'    => sprintf(
				/* translators: %s: delivery mode */
				__( 'Delivery Mode: %s', 'tw-blocks' ),
				$mode_label
			),
			'size'    => sprintf(
				/* translators: %s: size in KB */
				__( 'Total CSS Size: %s KB', 'tw-blocks' ),
				$kb_fa
			),
			'classes' => sprintf(
				/* translators: %s: number of classes */
				__( 'Extracted Classes: %s', 'tw-blocks' ),
				PersianHelper::localize_digits( (int) ( $metrics['class_count'] ?? 0 ), true )
			),
			'driver'  => sprintf(
				/* translators: %s: driver ID */
				__( 'Build Driver: %s', 'tw-blocks' ),
				(string) ( $metrics['driver'] ?? $this->css_engine->select_driver()->get_id() )
			),
			'time'    => sprintf(
				/* translators: %s: build time in ms */
				__( 'Build Time: %s ms', 'tw-blocks' ),
				PersianHelper::localize_digits( (string) ( $metrics['build_time_ms'] ?? 0 ), true )
			),
			'cache'   => sprintf(
				/* translators: %s: cache status */
				__( 'Cache Status: %s', 'tw-blocks' ),
				$status
			),
		);

		foreach ( $items as $key => $label ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'tw-blocks-debug-' . $key,
					'parent' => 'tw-blocks-debug',
					'title'  => esc_html( $label ),
					'href'   => admin_url( 'admin.php?page=tw-blocks-settings' ),
				)
			);
		}
	}
}
