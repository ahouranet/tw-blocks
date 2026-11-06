<?php
/**
 * Core Query Loop Extensions, Block Variations, and Custom Loop REST Handler.
 *
 * Extends WordPress core `core/query` via `query_loop_block_query_vars` without
 * creating a separate query engine, registers 4 RTL-first Tailwind Block Variations,
 * caches expensive meta/featured queries with transients, and powers the
 * `tw-blocks/custom-loop` "Load More" pagination endpoint.
 *
 * @package TWBlocks\Blocks
 */

declare(strict_types=1);

namespace TWBlocks\Blocks;

use TWBlocks\Security\Sanitizer;
use TWBlocks\Security\SecurityHelper;
use WP_Block;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QueryLoopExtension
 */
final class QueryLoopExtension {

	/**
	 * Strictly whitelisted `meta_key` values allowed for sorting in Query Loop
	 * to prevent arbitrary SQL meta joins or sensitive meta disclosure.
	 *
	 * @var array<string, string>
	 */
	public const ALLOWED_META_SORT_KEYS = array(
		'views'       => '_twb_views_count',
		'post_views'  => 'post_views_count',
		'total_sales' => 'total_sales',
		'price'       => '_price',
		'rating'      => '_wc_average_rating',
	);

	/**
	 * Initialize hooks for `core/query` variations, `query_loop_block_query_vars`, and REST API.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'get_block_type_variations', array( $this, 'register_core_query_variations' ), 10, 2 );
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_loop_vars' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_loop_rest_routes' ) );
	}

	/**
	 * Register 4 Persian RTL Tailwind Block Variations on `core/query`:
	 * 1. آخرین مقالات (`twb-latest-posts`)
	 * 2. آخرین محصولات (`twb-latest-products` — when WooCommerce is active)
	 * 3. مقالات مرتبط (`twb-related-posts`)
	 * 4. کارت وبلاگ ۳ ستونه (`twb-blog-cards-3col`)
	 *
	 * @param array<int, array<string, mixed>> $variations Existing variations.
	 * @param \WP_Block_Type                   $block_type Block type instance.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_core_query_variations( array $variations, $block_type ): array {
		if ( ! isset( $block_type->name ) || 'core/query' !== $block_type->name ) {
			return $variations;
		}

		$card_post_template = array(
			'core/post-template',
			array(
				'className' => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 list-none p-0 m-0',
			),
			array(
				array(
					'tw-blocks/section',
					array(
						'tagName'   => 'article',
						'paddingY'  => 'py-0',
						'paddingX'  => 'px-0',
						'bgVariant' => 'white',
						'twClasses' => 'rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs hover:shadow-md transition flex flex-col h-full',
					),
					array(
						array(
							'core/post-featured-image',
							array(
								'isLink'    => true,
								'className' => 'aspect-video w-full object-cover m-0',
							),
						),
						array(
							'tw-blocks/section',
							array(
								'tagName'   => 'div',
								'paddingY'  => 'py-5',
								'paddingX'  => 'px-5',
								'bgVariant' => 'transparent',
								'twClasses' => 'flex flex-col flex-1 justify-between gap-3 text-start',
							),
							array(
								array(
									'core/post-date',
									array(
										'className' => 'text-xs font-medium text-teal-700',
									),
								),
								array(
									'core/post-title',
									array(
										'level'     => 3,
										'isLink'    => true,
										'className' => 'text-base font-bold text-slate-900 leading-snug hover:text-teal-700 transition m-0',
									),
								),
								array(
									'core/post-excerpt',
									array(
										'excerptLength' => 18,
										'moreText'      => __( 'Read article →', 'tw-blocks' ),
										'className'     => 'text-sm text-slate-600 leading-relaxed m-0',
									),
								),
							),
						),
					),
				),
			),
		);

		// 1. آخرین مقالات (Latest Articles)
		$variations[] = array(
			'name'        => 'twb-latest-posts',
			'title'       => __( 'TW Blocks: Latest Articles', 'tw-blocks' ),
			'description' => __( 'Display the latest blog posts with RTL Tailwind cards and Jalali dates.', 'tw-blocks' ),
			'icon'        => 'admin-post',
			'scope'       => array( 'inserter', 'block' ),
			'attributes'  => array(
				'namespace' => 'tw-blocks/latest-posts',
				'className' => 'twb-root twb-query-latest-posts',
				'query'     => array(
					'perPage'           => 3,
					'pages'             => 0,
					'offset'            => 0,
					'postType'          => 'post',
					'order'             => 'desc',
					'orderBy'           => 'date',
					'inherit'           => false,
					'twbExcludeCurrent' => false,
					'twbFeaturedOnly'   => false,
					'twbOrderByMeta'    => '',
				),
			),
			'innerBlocks' => array( $card_post_template ),
		);

		// 2. مقالات مرتبط (Related Articles — excludes current post & matches categories)
		$variations[] = array(
			'name'        => 'twb-related-posts',
			'title'       => __( 'TW Blocks: Related Articles', 'tw-blocks' ),
			'description' => __( 'Automatically displays related posts from the same category while excluding the current post.', 'tw-blocks' ),
			'icon'        => 'networking',
			'scope'       => array( 'inserter', 'block' ),
			'attributes'  => array(
				'namespace' => 'tw-blocks/related-posts',
				'className' => 'twb-root twb-query-related-posts',
				'query'     => array(
					'perPage'           => 3,
					'pages'             => 0,
					'offset'            => 0,
					'postType'          => 'post',
					'order'             => 'desc',
					'orderBy'           => 'date',
					'inherit'           => false,
					'twbExcludeCurrent' => true,
					'twbRelatedTax'     => true,
					'twbFeaturedOnly'   => false,
					'twbOrderByMeta'    => '',
				),
			),
			'innerBlocks' => array( $card_post_template ),
		);

		// 3. کارت وبلاگ ۳ ستونه (3-Column Blog Cards with Pagination)
		$variations[] = array(
			'name'        => 'twb-blog-cards-3col',
			'title'       => __( 'TW Blocks: 3-Column Blog Card Grid', 'tw-blocks' ),
			'description' => __( 'Responsive 3-column blog archive grid with Tailwind pagination.', 'tw-blocks' ),
			'icon'        => 'grid-view',
			'scope'       => array( 'inserter', 'block' ),
			'attributes'  => array(
				'namespace' => 'tw-blocks/blog-cards-3col',
				'className' => 'twb-root twb-query-blog-3col space-y-8',
				'query'     => array(
					'perPage'           => 6,
					'pages'             => 0,
					'offset'            => 0,
					'postType'          => 'post',
					'order'             => 'desc',
					'orderBy'           => 'date',
					'inherit'           => false,
					'twbExcludeCurrent' => false,
					'twbFeaturedOnly'   => false,
					'twbOrderByMeta'    => '',
				),
			),
			'innerBlocks' => array(
				$card_post_template,
				array(
					'core/query-pagination',
					array(
						'className' => 'flex items-center justify-center gap-2 pt-6 text-sm font-medium',
					),
				),
			),
		);

		// 4. آخرین محصولات (Latest Products — registered when WooCommerce is active or `product` CPT exists)
		if ( class_exists( 'WooCommerce' ) || post_type_exists( 'product' ) ) {
			$variations[] = array(
				'name'        => 'twb-latest-products',
				'title'       => __( 'TW Blocks: Latest WooCommerce Products', 'tw-blocks' ),
				'description' => __( 'Display the latest WooCommerce products using RTL Tailwind product cards.', 'tw-blocks' ),
				'icon'        => 'cart',
				'scope'       => array( 'inserter', 'block' ),
				'attributes'  => array(
					'namespace' => 'tw-blocks/latest-products',
					'className' => 'twb-root twb-query-latest-products',
					'query'     => array(
						'perPage'           => 4,
						'pages'             => 0,
						'offset'            => 0,
						'postType'          => 'product',
						'order'             => 'desc',
						'orderBy'           => 'date',
						'inherit'           => false,
						'twbExcludeCurrent' => false,
						'twbFeaturedOnly'   => false,
						'twbOrderByMeta'    => '',
					),
				),
				'innerBlocks' => array( $card_post_template ),
			);
		}

		return $variations;
	}

	/**
	 * Filter `core/query` WP_Query arguments via `query_loop_block_query_vars`.
	 *
	 * Supports:
	 * - `twbFeaturedOnly`   : Filter sticky posts or WooCommerce featured products (`product_visibility` = `featured`).
	 * - `twbOrderByMeta`    : Sort by whitelisted meta key (`views`, `post_views`, `total_sales`, `price`, `rating`).
	 * - `twbExcludeCurrent` : Exclude the currently viewed singular post ID (`post__not_in`).
	 * - `twbRelatedTax`     : Match categories of the current singular post.
	 * - `offset`            : Apply sanitized integer offset while preserving pagination.
	 * - Transient cache     : Caches matching post IDs (`fields => 'ids'`) for heavy meta/featured queries for 15 minutes.
	 *
	 * @param array<string, mixed> $query_vars WP_Query arguments.
	 * @param WP_Block             $block      Current `core/post-template` block instance.
	 * @param int                  $page       Current pagination page number.
	 * @return array<string, mixed> Sanitized and enhanced WP_Query arguments.
	 */
	public function filter_query_loop_vars( array $query_vars, $block, int $page ): array {
		$context_query = isset( $block->context['query'] ) && is_array( $block->context['query'] )
			? Sanitizer::sanitize_block_attributes( $block->context['query'] )
			: array();

		if ( empty( $context_query ) ) {
			return $query_vars;
		}

		$is_heavy_query = false;

		// 1. Offset support (preserving pagination calculation).
		if ( isset( $context_query['offset'] ) ) {
			$base_offset = max( 0, min( 500, (int) $context_query['offset'] ) );
			$per_page    = isset( $query_vars['posts_per_page'] ) ? max( 1, (int) $query_vars['posts_per_page'] ) : 10;
			if ( $base_offset > 0 ) {
				$query_vars['offset'] = $base_offset + ( max( 0, $page - 1 ) * $per_page );
			}
		}

		// 2. Exclude current singular post (`twbExcludeCurrent`).
		if ( ! empty( $context_query['twbExcludeCurrent'] ) ) {
			$current_id = (int) get_queried_object_id();
			if ( $current_id > 0 ) {
				$existing_not_in            = isset( $query_vars['post__not_in'] ) && is_array( $query_vars['post__not_in'] )
					? array_map( 'absint', $query_vars['post__not_in'] )
					: array();
				$existing_not_in[]          = $current_id;
				$query_vars['post__not_in'] = array_values( array_unique( $existing_not_in ) );
			}
		}

		// 3. Related taxonomy matching (`twbRelatedTax`).
		if ( ! empty( $context_query['twbRelatedTax'] ) ) {
			$current_id = (int) get_queried_object_id();
			if ( $current_id > 0 ) {
				$cat_ids = wp_get_post_categories( $current_id, array( 'fields' => 'ids' ) );
				if ( is_array( $cat_ids ) && ! empty( $cat_ids ) ) {
					$query_vars['category__in'] = array_map( 'absint', $cat_ids );
				}
			}
		}

		// 4. Featured posts / products only (`twbFeaturedOnly`).
		if ( ! empty( $context_query['twbFeaturedOnly'] ) ) {
			$is_heavy_query = true;
			$post_type      = isset( $query_vars['post_type'] ) ? (string) $query_vars['post_type'] : 'post';

			if ( 'product' === $post_type ) {
				$tax_query   = isset( $query_vars['tax_query'] ) && is_array( $query_vars['tax_query'] ) ? $query_vars['tax_query'] : array();
				$tax_query[] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => array( 'featured' ),
					'operator' => 'IN',
				);
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				$query_vars['tax_query'] = $tax_query;
			} else {
				$sticky = get_option( 'sticky_posts', array() );
				if ( is_array( $sticky ) && ! empty( $sticky ) ) {
					$query_vars['post__in']            = array_map( 'absint', $sticky );
					$query_vars['ignore_sticky_posts'] = 1;
				}
			}
		}

		// 5. Sort by whitelisted meta key (`twbOrderByMeta`: views, total_sales, price, rating).
		if ( ! empty( $context_query['twbOrderByMeta'] ) && is_string( $context_query['twbOrderByMeta'] ) ) {
			$meta_alias = sanitize_key( $context_query['twbOrderByMeta'] );
			if ( isset( self::ALLOWED_META_SORT_KEYS[ $meta_alias ] ) ) {
				$is_heavy_query         = true;
				$meta_key               = self::ALLOWED_META_SORT_KEYS[ $meta_alias ];
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_vars['meta_key'] = $meta_key;
				$query_vars['orderby']  = 'meta_value_num date';
				$query_vars['order']    = isset( $context_query['order'] ) && 'asc' === strtolower( (string) $context_query['order'] ) ? 'ASC' : 'DESC';
			}
		}

		// 6. Transient caching for heavy meta/featured queries (15-minute TTL).
		if ( $is_heavy_query ) {
			$cache_key  = 'twb_ql_' . substr( md5( (string) wp_json_encode( $query_vars ) ), 0, 20 );
			$cached_ids = get_transient( $cache_key );

			if ( false === $cached_ids ) {
				$id_query_args                   = $query_vars;
				$id_query_args['fields']         = 'ids';
				$id_query_args['no_found_rows']  = true;
				$id_query_page                   = new WP_Query( $id_query_args );
				$cached_ids                      = is_array( $id_query_page->posts ) ? array_map( 'absint', $id_query_page->posts ) : array();
				set_transient( $cache_key, $cached_ids, 15 * MINUTE_IN_SECONDS );
			}

			if ( is_array( $cached_ids ) && ! empty( $cached_ids ) ) {
				$query_vars['post__in'] = $cached_ids;
				$query_vars['orderby']  = 'post__in';
				unset( $query_vars['meta_key'], $query_vars['offset'] );
			}
		}

		return $query_vars;
	}

	/**
	 * Register REST API endpoint `/tw-blocks/v1/loop-more` for `tw-blocks/custom-loop` AJAX "Load More".
	 *
	 * @return void
	 */
	public function register_loop_rest_routes(): void {
		register_rest_route(
			'tw-blocks/v1',
			'/loop-more',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'handle_loop_more_request' ),
				'args'                => array(
					'page'          => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'      => array(
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
					'post_type'     => array(
						'type'              => 'string',
						'default'           => 'post',
						'sanitize_callback' => 'sanitize_key',
					),
					'order_by_meta' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Handle `GET /wp-json/tw-blocks/v1/loop-more` with rate limiting and sanitized WP_Query.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_loop_more_request( WP_REST_Request $request ): WP_REST_Response {
		if ( ! SecurityHelper::check_rest_rate_limit( 'loop_more', 60, 60 ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Too many requests. Please wait a moment.', 'tw-blocks' ) ),
				429
			);
		}

		$page      = max( 1, min( 100, (int) $request->get_param( 'page' ) ) );
		$per_page  = max( 1, min( 24, (int) $request->get_param( 'per_page' ) ) );
		$post_type = (string) $request->get_param( 'post_type' );
		if ( ! in_array( $post_type, array( 'post', 'product', 'page' ), true ) ) {
			$post_type = 'post';
		}

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
		);

		$meta_alias = (string) $request->get_param( 'order_by_meta' );
		if ( '' !== $meta_alias && isset( self::ALLOWED_META_SORT_KEYS[ $meta_alias ] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['meta_key'] = self::ALLOWED_META_SORT_KEYS[ $meta_alias ];
			$query_args['orderby']  = 'meta_value_num date';
			$query_args['order']    = 'DESC';
		}

		$query = new WP_Query( $query_args );
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_obj ) {
				if ( ! $post_obj instanceof \WP_Post ) {
					continue;
				}
				$items[] = array(
					'id'      => $post_obj->ID,
					'title'   => esc_html( get_the_title( $post_obj ) ),
					'link'    => esc_url( (string) get_permalink( $post_obj ) ),
					'excerpt' => wp_kses_post( wp_trim_words( get_the_excerpt( $post_obj ), 18 ) ),
					'date'    => esc_html( get_the_date( '', $post_obj ) ),
				);
			}
		}

		return new WP_REST_Response(
			array(
				'items'    => $items,
				'has_more' => $page < (int) $query->max_num_pages,
			),
			200
		);
	}
}
