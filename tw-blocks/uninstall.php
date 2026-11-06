<?php
/**
 * Uninstall routine for TW Blocks.
 *
 * @package TWBlocks
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'twb_settings', array() );
$delete_data = ! empty( $settings['delete_on_uninstall'] );

if ( $delete_data ) {
	delete_option( 'twb_settings' );
	delete_option( 'twb_design_tokens' );
	delete_option( 'twb_css_cache_index' );
	delete_option( 'twb_fallback_notice_dismissed' );

	delete_post_meta_by_key( '_twb_css_meta' );
	delete_post_meta_by_key( '_twb_extracted_classes' );

	$upload_dir = wp_upload_dir();
	$twb_dir    = trailingslashit( $upload_dir['basedir'] ) . 'tw-blocks/css';

	if ( is_dir( $twb_dir ) ) {
		$files = glob( $twb_dir . '/*.css' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $file );
				}
			}
		}
	}
}
