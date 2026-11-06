<?php
/**
 * Atomic Hash-Based CSS File Cache Manager.
 * Stores compiled CSS in `wp-content/uploads/tw-blocks/css/{scope}-{hash}.css`.
 *
 * @package TWBlocks\CSS
 */

declare(strict_types=1);

namespace TWBlocks\CSS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CacheManager
 */
final class CacheManager {

	/**
	 * Option key storing global scope & template scope cache metadata.
	 */
	public const INDEX_OPTION = 'twb_css_cache_index';

	/**
	 * Post meta key storing per-post CSS metadata.
	 */
	public const POST_META_KEY = '_twb_css_meta';

	/**
	 * Get base upload directory path for TW Blocks (`wp-content/uploads/tw-blocks`).
	 *
	 * @return string
	 */
	public function get_base_dir(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'tw-blocks';
	}

	/**
	 * Get base upload URL for TW Blocks (`wp-content/uploads/tw-blocks`).
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . 'tw-blocks';
	}

	/**
	 * Get CSS cache directory path (`wp-content/uploads/tw-blocks/css`).
	 *
	 * @return string
	 */
	public function get_css_dir(): string {
		return $this->get_base_dir() . '/css';
	}

	/**
	 * Get CSS cache directory URL (`wp-content/uploads/tw-blocks/css`).
	 *
	 * @return string
	 */
	public function get_css_url(): string {
		return $this->get_base_url() . '/css';
	}

	/**
	 * Get local binary directory path (`wp-content/uploads/tw-blocks/bin`).
	 *
	 * @return string
	 */
	public function get_bin_dir(): string {
		return $this->get_base_dir() . '/bin';
	}

	/**
	 * Ensure required directories exist and are protected where appropriate.
	 *
	 * @return bool
	 */
	public function ensure_directories(): bool {
		$css_dir = $this->get_css_dir();
		$bin_dir = $this->get_bin_dir();

		$ok_css = wp_mkdir_p( $css_dir );
		$ok_bin = wp_mkdir_p( $bin_dir );

		// Prevent directory listing in css and bin directories.
		$index_file = $this->get_base_dir() . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		// Protect bin directory from direct web execution.
		$htaccess = $bin_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "Deny from all\n" );
		}

		return $ok_css && $ok_bin;
	}

	/**
	 * Build deterministic filename `{scope}-{hash}.css`.
	 *
	 * @param string $scope Scope identifier (e.g. `base`, `tpl-single`, `post-42`, `merged`).
	 * @param string $hash  16-character content/token hash.
	 * @return string
	 */
	public function get_filename( string $scope, string $hash ): string {
		$clean_scope = (string) preg_replace( '/[^a-zA-Z0-9\-_]/', '', $scope );
		$clean_hash  = (string) preg_replace( '/[^a-f0-9]/i', '', $hash );
		return sprintf( '%s-%s.css', $clean_scope, $clean_hash );
	}

	/**
	 * Check if a compiled CSS file for the given scope and hash already exists on disk.
	 *
	 * @param string $scope Scope identifier.
	 * @param string $hash  Hash string.
	 * @return bool
	 */
	public function has( string $scope, string $hash ): bool {
		$path = $this->get_css_dir() . '/' . $this->get_filename( $scope, $hash );
		return is_file( $path ) && is_readable( $path ) && filesize( $path ) > 0;
	}

	/**
	 * Read cached CSS content for a scope and hash.
	 *
	 * @param string $scope Scope identifier.
	 * @param string $hash  Hash string.
	 * @return string|null CSS string or null if not found.
	 */
	public function get_contents( string $scope, string $hash ): ?string {
		$path = $this->get_css_dir() . '/' . $this->get_filename( $scope, $hash );
		if ( ! is_readable( $path ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $path );
		return false !== $content ? $content : null;
	}

	/**
	 * Atomically write compiled CSS to `wp-content/uploads/tw-blocks/css/{scope}-{hash}.css`
	 * using a temporary file + `rename()` to prevent race conditions under concurrent requests.
	 *
	 * @param string $scope Scope identifier.
	 * @param string $hash  Hash string.
	 * @param string $css   Minified CSS content.
	 * @return array{filename: string, path: string, url: string, bytes: int}|null
	 */
	public function write_atomic( string $scope, string $hash, string $css ): ?array {
		if ( ! $this->ensure_directories() ) {
			return null;
		}

		$filename    = $this->get_filename( $scope, $hash );
		$css_dir     = $this->get_css_dir();
		$target_path = $css_dir . '/' . $filename;

		$tmp_file = tempnam( $css_dir, 'twb_tmp_' );
		if ( false === $tmp_file ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$bytes = file_put_contents( $tmp_file, $css, LOCK_EX );
		if ( false === $bytes ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp_file );
			return null;
		}

		// Atomic move into place.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! @rename( $tmp_file, $target_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $tmp_file );
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		@chmod( $target_path, 0644 );

		// Remove stale files for the same scope with older hashes.
		$this->cleanup_old_scope_files( $scope, $filename );

		return array(
			'filename' => $filename,
			'path'     => $target_path,
			'url'      => $this->get_css_url() . '/' . $filename,
			'bytes'    => (int) $bytes,
		);
	}

	/**
	 * Store post-level CSS build metadata in `post_meta`.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta    Metadata (hash, filename, bytes, class_count, driver, build_time_ms, updated_at).
	 * @return void
	 */
	public function save_post_meta( int $post_id, array $meta ): void {
		update_post_meta( $post_id, self::POST_META_KEY, $meta );
	}

	/**
	 * Get post-level CSS build metadata from `post_meta`.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function get_post_meta( int $post_id ): ?array {
		$meta = get_post_meta( $post_id, self::POST_META_KEY, true );
		return is_array( $meta ) && ! empty( $meta['hash'] ) ? $meta : null;
	}

	/**
	 * Store scope metadata (`base` or `tpl-*`) in options index.
	 *
	 * @param string               $scope Scope key.
	 * @param array<string, mixed> $meta  Metadata array.
	 * @return void
	 */
	public function save_scope_meta( string $scope, array $meta ): void {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$index[ $scope ] = $meta;
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Get scope metadata (`base` or `tpl-*`) from options index.
	 *
	 * @param string $scope Scope key.
	 * @return array<string, mixed>|null
	 */
	public function get_scope_meta( string $scope ): ?array {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) || empty( $index[ $scope ] ) || ! is_array( $index[ $scope ] ) ) {
			return null;
		}
		return $index[ $scope ];
	}

	/**
	 * Delete older `{scope}-*.css` files when a scope hash updates.
	 *
	 * @param string $scope            Scope prefix.
	 * @param string $current_filename Active filename to keep.
	 * @return void
	 */
	private function cleanup_old_scope_files( string $scope, string $current_filename ): void {
		$clean_scope = (string) preg_replace( '/[^a-zA-Z0-9\-_]/', '', $scope );
		$pattern     = $this->get_css_dir() . '/' . $clean_scope . '-*.css';
		$files       = glob( $pattern );

		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( basename( $file ) !== $current_filename && is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $file );
			}
		}
	}

	/**
	 * Purge all cached CSS files and metadata (used when global tokens change or user clicks "پاکسازی کش").
	 *
	 * @return int Number of deleted CSS files.
	 */
	public function purge_all(): int {
		$count = 0;
		$files = glob( $this->get_css_dir() . '/*.css' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					if ( @unlink( $file ) ) {
						++$count;
					}
				}
			}
		}

		// Also remove any leftover atomic write temporary files (`twb_tmp_*`).
		$tmp_files = glob( $this->get_css_dir() . '/twb_tmp_*' );
		if ( is_array( $tmp_files ) ) {
			foreach ( $tmp_files as $tmp ) {
				if ( is_file( $tmp ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $tmp );
					++$count;
				}
			}
		}

		delete_option( self::INDEX_OPTION );
		delete_post_meta_by_key( self::POST_META_KEY );

		return $count;
	}

	/**
	 * Detect and purge only incomplete, zero-byte, syntactically truncated, or orphaned CSS cache files
	 * and clean dangling metadata entries in `twb_css_cache_index` and `_twb_css_meta`.
	 *
	 * @return array{removed_files: int, cleaned_meta: int, details: array<int, string>}
	 */
	public function purge_corrupted(): array {
		$removed_files = 0;
		$cleaned_meta  = 0;
		$details       = array();
		$css_dir       = $this->get_css_dir();

		if ( ! is_dir( $css_dir ) ) {
			return array(
				'removed_files' => 0,
				'cleaned_meta'  => 0,
				'details'       => array(),
			);
		}

		// 1. Remove leftover atomic temporary files (`twb_tmp_*`).
		$tmp_files = glob( $css_dir . '/twb_tmp_*' );
		if ( is_array( $tmp_files ) ) {
			foreach ( $tmp_files as $tmp_path ) {
				if ( is_file( $tmp_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					if ( @unlink( $tmp_path ) ) {
						++$removed_files;
						$details[] = 'Removed leftover temp file: ' . basename( $tmp_path );
					}
				}
			}
		}

		// 2. Inspect `.css` files for 0-byte size, invalid naming, or unbalanced braces `{` vs `}`.
		$css_files = glob( $css_dir . '/*.css' );
		if ( is_array( $css_files ) ) {
			foreach ( $css_files as $css_path ) {
				if ( ! is_file( $css_path ) ) {
					continue;
				}

				$basename  = basename( $css_path );
				$size      = (int) @filesize( $css_path );
				$corrupted = false;
				$reason    = '';

				if ( $size <= 0 || ! is_readable( $css_path ) ) {
					$corrupted = true;
					$reason    = 'empty or unreadable file';
				} elseif ( ! preg_match( '/^[a-zA-Z0-9\-_]+-[a-f0-9]{16}\.css$/i', $basename ) ) {
					$corrupted = true;
					$reason    = 'invalid cache filename format';
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$contents = (string) file_get_contents( $css_path );
					if ( '' === trim( $contents ) || substr_count( $contents, '{' ) !== substr_count( $contents, '}' ) ) {
						$corrupted = true;
						$reason    = 'incomplete CSS content (unbalanced braces)';
					}
				}

				if ( $corrupted ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					if ( @unlink( $css_path ) ) {
						++$removed_files;
						$details[] = sprintf( 'Removed corrupted CSS file %s (%s)', $basename, $reason );
					}
				}
			}
		}

		// 3. Clean dangling scope index entries where the physical CSS file is missing or corrupted.
		$index = get_option( self::INDEX_OPTION, array() );
		if ( is_array( $index ) && ! empty( $index ) ) {
			$updated = false;
			foreach ( $index as $scope => $meta ) {
				$hash = is_array( $meta ) && isset( $meta['hash'] ) ? (string) $meta['hash'] : '';
				if ( '' === $hash || ! $this->has( (string) $scope, $hash ) ) {
					unset( $index[ $scope ] );
					++$cleaned_meta;
					$updated   = true;
					$details[] = sprintf( 'Cleaned dangling scope index entry: %s', (string) $scope );
				}
			}
			if ( $updated ) {
				update_option( self::INDEX_OPTION, $index, false );
			}
		}

		// 4. Clean dangling per-post `_twb_css_meta` entries where the physical CSS file is missing.
		if ( function_exists( 'get_posts' ) ) {
			$post_ids = get_posts(
				array(
					'post_type'              => 'any',
					'post_status'            => 'any',
					'posts_per_page'         => 500,
					'fields'                 => 'ids',
					'meta_key'               => self::POST_META_KEY,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			if ( is_array( $post_ids ) ) {
				foreach ( $post_ids as $pid ) {
					$post_id = (int) $pid;
					$meta    = $this->get_post_meta( $post_id );
					$hash    = is_array( $meta ) && isset( $meta['hash'] ) ? (string) $meta['hash'] : '';
					if ( '' === $hash || ! $this->has( 'post-' . $post_id, $hash ) ) {
						delete_post_meta( $post_id, self::POST_META_KEY );
						++$cleaned_meta;
						$details[] = sprintf( 'Cleaned dangling post meta for post #%d', $post_id );
					}
				}
			}
		}

		return array(
			'removed_files' => $removed_files,
			'cleaned_meta'  => $cleaned_meta,
			'details'       => $details,
		);
	}
}
