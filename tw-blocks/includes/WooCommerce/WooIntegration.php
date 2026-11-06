<?php
/**
 * WooCommerce Integration, Block Variations (`woocommerce/product-collection`),
 * Conditional Shop/Cart/Checkout Asset Loader, Block Templates Provider,
 * and Schema Deduplication Guard (Phase 3).
 *
 * @package TWBlocks\WooCommerce
 */

declare(strict_types=1);

namespace TWBlocks\WooCommerce;

use TWBlocks\CSS\TokenManager;
use TWBlocks\Security\SecurityHelper;
use TWBlocks\Support\PersianHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooIntegration
 */
final class WooIntegration {

	/**
	 * Bundled WooCommerce block templates in `tw-blocks/templates/`.
	 *
	 * @var array<string, array{title: string, description: string}>
	 */
	public const WC_BLOCK_TEMPLATES = array(
		'archive-product'        => array(
			'title'       => 'آرشیو فروشگاه ووکامرس (TW Blocks)',
			'description' => 'قالب ریسپانسیو راست‌به‌چپ برای صفحهٔ اصلی فروشگاه با نوار مزایا و شبکهٔ محصولات.',
		),
		'taxonomy-product_cat'   => array(
			'title'       => 'دسته‌بندی محصولات ووکامرس (TW Blocks)',
			'description' => 'قالب دسته‌بندی محصولات با هدر توضیح دسته، فیلتر و کارت‌های محصول Tailwind.',
		),
		'single-product'         => array(
			'title'       => 'صفحهٔ تکی محصول با نوار خرید چسبنده موبایل (TW Blocks)',
			'description' => 'قالب تک‌محصول شامل گالری، بج و شمارندهٔ تخفیف، تب‌های مشخصات، سوالات متداول، محصولات مرتبط و نوار خرید چسبنده در موبایل.',
		),
		'product-search-results' => array(
			'title'       => 'نتایج جستجوی محصولات ووکامرس (TW Blocks)',
			'description' => 'قالب نمایش نتایج جستجوی محصولات با چیدمان راست‌به‌چپ و ارقام فارسی.',
		),
	);

	/**
	 * TokenManager service.
	 *
	 * @var TokenManager
	 */
	private TokenManager $token_manager;

	/**
	 * Constructor.
	 *
	 * @param TokenManager $token_manager TokenManager instance.
	 */
	public function __construct( TokenManager $token_manager ) {
		$this->token_manager = $token_manager;
	}

	/**
	 * Initialize WooCommerce hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'get_block_type_variations', array( $this, 'register_product_collection_variations' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_conditional_woocommerce_assets' ), 25 );
		add_action( 'template_redirect', array( $this, 'track_recently_viewed_product' ), 20 );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'localize_iranian_currency_symbol' ), 20, 2 );
		add_filter( 'woocommerce_format_sale_price', array( $this, 'localize_sale_price_digits' ), 20, 3 );
		add_filter( 'get_block_templates', array( $this, 'inject_woocommerce_block_templates' ), 10, 3 );
	}

	/**
	 * Check whether WooCommerce or third-party SEO plugins (Yoast SEO / RankMath) are already
	 * handling structured data on the current page so TW Blocks never outputs duplicate Product/FAQ schema.
	 *
	 * @param string $schema_type Schema type (`Product` or `FAQPage`).
	 * @return bool True if schema output should be skipped to prevent duplication.
	 */
	public static function should_skip_duplicate_schema( string $schema_type = 'Product' ): bool {
		$seo_plugin_active = defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| class_exists( '\RankMath' )
			|| defined( 'SEOPRESS_VERSION' );

		if ( 'Product' === $schema_type ) {
			// WooCommerce natively outputs Product JSON-LD via WC_Structured_Data; never duplicate it.
			if ( class_exists( 'WooCommerce' ) || $seo_plugin_active ) {
				return true;
			}
		}

		if ( 'FAQPage' === $schema_type && function_exists( 'is_product' ) && is_product() && $seo_plugin_active ) {
			// On single product pages when Yoast/RankMath is active and managing the @graph, avoid duplicate schema collisions.
			return (bool) apply_filters( 'twb_skip_faq_schema_when_seo_active', false );
		}

		return false;
	}

	/**
	 * Register 4 Persian RTL Tailwind Block Variations on `woocommerce/product-collection`:
	 * 1. پرفروش‌ها (`twb-wc-best-sellers`)
	 * 2. تخفیف‌دارها (`twb-wc-on-sale`)
	 * 3. جدیدترین‌ها (`twb-wc-newest`)
	 * 4. محصولات مرتبط (`twb-wc-related`)
	 *
	 * @param array<int, array<string, mixed>> $variations Existing variations.
	 * @param \WP_Block_Type                   $block_type Block type object.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_product_collection_variations( array $variations, $block_type ): array {
		if ( ! isset( $block_type->name ) || 'woocommerce/product-collection' !== $block_type->name ) {
			return $variations;
		}

		$tailwind_product_template = array(
			'woocommerce/product-template',
			array(
				'className' => 'twb-wc-product-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 list-none p-0 m-0',
			),
			array(
				array(
					'tw-blocks/section',
					array(
						'tagName'   => 'article',
						'paddingY'  => 'py-4',
						'paddingX'  => 'px-4',
						'bgVariant' => 'white',
						'twClasses' => 'twb-wc-card rounded-2xl border border-slate-200 bg-white shadow-xs hover:shadow-md transition flex flex-col justify-between gap-3 h-full text-start relative',
					),
					array(
						array(
							'woocommerce/product-image',
							array(
								'showSaleBadge' => false,
								'imageSizing'   => 'thumbnail',
								'className'     => 'rounded-xl overflow-hidden aspect-square w-full object-cover m-0',
							),
						),
						array(
							'tw-blocks/wc-sale-timer',
							array(
								'showCountdown' => false,
								'twClasses'     => 'absolute top-6 start-6 z-10',
							),
						),
						array(
							'core/post-title',
							array(
								'level'     => 3,
								'isLink'    => true,
								'className' => 'text-sm font-bold text-slate-900 leading-snug line-clamp-2 hover:text-teal-700 transition m-0',
							),
						),
						array(
							'woocommerce/product-price',
							array(
								'className' => 'text-base font-extrabold text-teal-700 m-0',
							),
						),
						array(
							'woocommerce/product-button',
							array(
								'className' => 'w-full pt-2',
							),
						),
					),
				),
			),
		);

		$defs = array(
			array(
				'name'        => 'twb-wc-best-sellers',
				'title'       => __( 'TW Blocks: Best-Selling Products (پرفروش‌ها)', 'tw-blocks' ),
				'description' => __( 'Display top-selling WooCommerce products with RTL Tailwind product cards.', 'tw-blocks' ),
				'query'       => array(
					'perPage'           => 4,
					'pages'             => 1,
					'offset'            => 0,
					'postType'          => 'product',
					'order'             => 'desc',
					'orderBy'           => 'popularity',
					'woocommerceOnSale' => false,
				),
			),
			array(
				'name'        => 'twb-wc-on-sale',
				'title'       => __( 'TW Blocks: On-Sale Products (تخفیف‌دارها)', 'tw-blocks' ),
				'description' => __( 'Showcase discounted WooCommerce products with Persian percentage badges.', 'tw-blocks' ),
				'query'       => array(
					'perPage'           => 4,
					'pages'             => 1,
					'offset'            => 0,
					'postType'          => 'product',
					'order'             => 'desc',
					'orderBy'           => 'date',
					'woocommerceOnSale' => true,
				),
			),
			array(
				'name'        => 'twb-wc-newest',
				'title'       => __( 'TW Blocks: Newest Products (جدیدترین‌ها)', 'tw-blocks' ),
				'description' => __( 'Display newly published WooCommerce products with RTL Tailwind styling.', 'tw-blocks' ),
				'query'       => array(
					'perPage'           => 4,
					'pages'             => 1,
					'offset'            => 0,
					'postType'          => 'product',
					'order'             => 'desc',
					'orderBy'           => 'date',
					'woocommerceOnSale' => false,
				),
			),
			array(
				'name'        => 'twb-wc-related',
				'title'       => __( 'TW Blocks: Related Products (محصولات مرتبط)', 'tw-blocks' ),
				'description' => __( 'Automatically display related products on single product templates.', 'tw-blocks' ),
				'query'       => array(
					'perPage'           => 4,
					'pages'             => 1,
					'offset'            => 0,
					'postType'          => 'product',
					'order'             => 'desc',
					'orderBy'           => 'rand',
					'woocommerceOnSale' => false,
				),
			),
		);

		foreach ( $defs as $def ) {
			$variations[] = array(
				'name'        => $def['name'],
				'title'       => $def['title'],
				'description' => $def['description'],
				'icon'        => 'products',
				'scope'       => array( 'inserter', 'block' ),
				'attributes'  => array(
					'collection'    => 'woocommerce/product-collection/' . str_replace( 'twb-wc-', '', $def['name'] ),
					'query'         => $def['query'],
					'displayLayout' => array(
						'type'    => 'flex',
						'columns' => 4,
					),
				),
				'innerBlocks' => array( $tailwind_product_template ),
			);
		}

		return $variations;
	}

	/**
	 * Conditional Asset Loading:
	 * - Enqueues `assets/css/woocommerce-shop.css` ONLY on `is_woocommerce()` or when WC product blocks are present.
	 * - Enqueues `assets/css/woocommerce-cart-checkout.css` ONLY on `is_cart()`, `is_checkout()`, or `is_account_page()`.
	 * - In `block` Cart/Checkout mode, injects CSS Variables mapping `--wc-blocks-*` to `--twb-*` tokens without Tailwind class collisions.
	 *
	 * @return void
	 */
	public function enqueue_conditional_woocommerce_assets(): void {
		$is_shop_context = ( function_exists( 'is_woocommerce' ) && is_woocommerce() )
			|| has_block( 'woocommerce/product-collection' )
			|| has_block( 'tw-blocks/wc-sale-timer' )
			|| has_block( 'tw-blocks/wc-recently-viewed' );

		$is_cart_checkout_context = ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() )
			|| has_block( 'woocommerce/cart' )
			|| has_block( 'woocommerce/checkout' );

		if ( $is_shop_context ) {
			wp_enqueue_style(
				'tw-blocks-wc-shop',
				TWB_URL . 'assets/css/woocommerce-shop.css',
				array(),
				TWB_VERSION
			);
		}

		if ( $is_cart_checkout_context ) {
			$settings = get_option( 'twb_settings', array() );
			$mode     = isset( $settings['wc_cart_checkout_mode'] ) && 'block' === $settings['wc_cart_checkout_mode']
				? 'block'
				: 'classic';

			wp_enqueue_style(
				'tw-blocks-wc-cart-checkout',
				TWB_URL . 'assets/css/woocommerce-cart-checkout.css',
				array(),
				TWB_VERSION
			);

			if ( 'block' === $mode ) {
				// Synchronize WooCommerce Cart/Checkout Blocks strictly via CSS variables (no Tailwind utility overrides).
				$tokens      = $this->token_manager->get_tokens();
				$primary     = $tokens['colors']['primary'] ?? '#0d9488';
				$surface     = $tokens['colors']['surface'] ?? '#f8fafc';
				$foreground  = $tokens['colors']['foreground'] ?? '#0f172a';
				$border      = $tokens['colors']['border'] ?? '#e2e8f0';
				$radius_lg   = $tokens['layout']['radiusLg'] ?? '0.75rem';
				$font_family = $tokens['typography']['fontFamilyBase'] ?? 'Vazirmatn, Tahoma, sans-serif';

				$vars_css = sprintf(
					':root, .wc-block-cart, .wc-block-checkout { --wc-blocks-theme-color: %1$s; --wc-blocks-surface-color: %2$s; --wc-blocks-text-color: %3$s; --wc-blocks-border-color: %4$s; --wc-blocks-border-radius: %5$s; --wc-blocks-font-family: %6$s; font-family: var(--wc-blocks-font-family); }',
					$primary,
					$surface,
					$foreground,
					$border,
					$radius_lg,
					$font_family
				);

				wp_add_inline_style(
					'tw-blocks-wc-cart-checkout',
					SecurityHelper::sanitize_inline_css( $vars_css )
				);
			}
		}
	}

	/**
	 * Track recently viewed product IDs in the standard `woocommerce_recently_viewed` cookie
	 * on single product views so `tw-blocks/wc-recently-viewed` works even when WC widget is inactive.
	 *
	 * @return void
	 */
	public function track_recently_viewed_product(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_queried_object_id();
		if ( $product_id <= 0 ) {
			return;
		}

		$raw_cookie = isset( $_COOKIE['woocommerce_recently_viewed'] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE['woocommerce_recently_viewed'] ) )
			: '';

		$viewed_ids = array_filter( array_map( 'absint', explode( '|', $raw_cookie ) ) );
		$viewed_ids = array_diff( $viewed_ids, array( $product_id ) );
		array_unshift( $viewed_ids, $product_id );
		$viewed_ids = array_slice( $viewed_ids, 0, 15 );

		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed_ids ) );
		}
	}

	/**
	 * Ensure Iranian currencies (`IRT` = تومان, `IRR` = ریال, `IRHT` = هزار تومان) display
	 * proper Persian typography with non-breaking spacing.
	 *
	 * @param string $currency_symbol Existing currency symbol.
	 * @param string $currency        Currency code.
	 * @return string
	 */
	public function localize_iranian_currency_symbol( string $currency_symbol, string $currency ): string {
		switch ( strtoupper( $currency ) ) {
			case 'IRT':
				return 'تومان';
			case 'IRR':
				return 'ریال';
			case 'IRHT':
				return 'هزار تومان';
			default:
				return $currency_symbol;
		}
	}

	/**
	 * Localize numerals in formatted WooCommerce sale prices when Persian digits are enabled.
	 *
	 * @param string $price         Formatted HTML sale price.
	 * @param string $regular_price Regular price.
	 * @param string $sale_price    Sale price.
	 * @return string
	 */
	public function localize_sale_price_digits( string $price, $regular_price, $sale_price ): string {
		unset( $regular_price, $sale_price );
		return PersianHelper::localize_digits( $price );
	}

	/**
	 * Provide bundled WooCommerce Block Templates (`archive-product`, `taxonomy-product_cat`,
	 * `single-product`, `product-search-results`) when queried by WordPress / WooCommerce.
	 *
	 * @param array<int, \WP_Block_Template> $query_result   Existing templates.
	 * @param array<string, mixed>           $query          Query arguments.
	 * @param string                         $template_type  Template type (`wp_template`).
	 * @return array<int, \WP_Block_Template>
	 */
	public function inject_woocommerce_block_templates( array $query_result, array $query, string $template_type ): array {
		if ( 'wp_template' !== $template_type || ! class_exists( '\WP_Block_Template' ) ) {
			return $query_result;
		}

		$slugs_requested = isset( $query['slug__in'] ) && is_array( $query['slug__in'] ) ? $query['slug__in'] : array();
		$existing_slugs  = array();
		foreach ( $query_result as $tpl_obj ) {
			if ( isset( $tpl_obj->slug ) ) {
				$existing_slugs[ $tpl_obj->slug ] = true;
			}
		}

		foreach ( self::WC_BLOCK_TEMPLATES as $slug => $meta ) {
			if ( isset( $existing_slugs[ $slug ] ) ) {
				continue;
			}
			if ( ! empty( $slugs_requested ) && ! in_array( $slug, $slugs_requested, true ) ) {
				continue;
			}

			$file = TWB_PATH . 'templates/' . $slug . '.html';
			if ( ! is_readable( $file ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = (string) file_get_contents( $file );

			$template                 = new \WP_Block_Template();
			$template->id             = 'tw-blocks//' . $slug;
			$template->theme          = 'tw-blocks';
			$template->slug           = $slug;
			$template->source         = 'plugin';
			$template->type           = 'wp_template';
			$template->title          = $meta['title'];
			$template->description    = $meta['description'];
			$template->status         = 'publish';
			$template->has_theme_file = true;
			$template->is_custom      = false;
			$template->content        = $content;

			$query_result[] = $template;
		}

		return $query_result;
	}
}
