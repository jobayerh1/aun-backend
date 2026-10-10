<?php
/**
 * Asset loading. Everything is conditional: WooCommerce styles only on
 * shop pages, comment-reply only where comments are open, etc.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset version: file mtime in debug mode, theme version otherwise.
 */
function palltheme_asset_ver( string $rel ): string {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( PALLTHEME_DIR . $rel ) ) {
		return (string) filemtime( PALLTHEME_DIR . $rel );
	}
	return PALLTHEME_VERSION;
}

/**
 * Google Fonts URL for the selected heading/body fonts.
 */
function palltheme_fonts_url(): string {
	if ( 'system' === palltheme_mod( 'font_source' ) ) {
		return '';
	}
	$families = array_unique( array( palltheme_mod( 'font_heading' ), palltheme_mod( 'font_body' ) ) );
	$query    = array();
	foreach ( $families as $family ) {
		$query[] = 'family=' . str_replace( ' ', '+', $family ) . ':wght@400;500;600;700;800';
	}
	return 'https://fonts.googleapis.com/css2?' . implode( '&', $query ) . '&display=swap';
}

/**
 * Front-end assets.
 */
function palltheme_enqueue_assets(): void {
	$fonts = palltheme_fonts_url();
	if ( $fonts ) {
		wp_enqueue_style( 'palltheme-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	wp_enqueue_style( 'palltheme-main', PALLTHEME_URI . '/assets/css/main.css', array(), palltheme_asset_ver( '/assets/css/main.css' ) );
	wp_add_inline_style( 'palltheme-main', palltheme_dynamic_css() );
	// RTL: main.css uses logical properties (inline-start/end), so no separate RTL file is needed.

	if ( class_exists( 'WooCommerce' ) && palltheme_is_woo_context() ) {
		wp_enqueue_style( 'palltheme-woo', PALLTHEME_URI . '/assets/css/woocommerce.css', array( 'palltheme-main' ), palltheme_asset_ver( '/assets/css/woocommerce.css' ) );
		wp_enqueue_script( 'palltheme-woo', PALLTHEME_URI . '/assets/js/woocommerce.js', array(), palltheme_asset_ver( '/assets/js/woocommerce.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_localize_script(
			'palltheme-woo',
			'pallthemeWoo',
			array(
				'checkoutUrl' => wc_get_checkout_url(),
				'i18n'        => array(
					'loading'  => __( 'Loading…', 'palltheme' ),
					'loadMore' => __( 'Load more products', 'palltheme' ),
					'noMore'   => __( 'All products loaded', 'palltheme' ),
					'error'    => __( 'Could not load products. Please try again.', 'palltheme' ),
					'results'  => __( 'Products updated', 'palltheme' ),
				),
			)
		);
	}

	wp_enqueue_script( 'palltheme-main', PALLTHEME_URI . '/assets/js/main.js', array(), palltheme_asset_ver( '/assets/js/main.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script(
		'palltheme-main',
		'pallthemeData',
		array(
			'searchEndpoint' => palltheme_has_core() ? esc_url_raw( rest_url( 'palltheme-core/v1/search' ) ) : '',
			'searchUrl'      => esc_url_raw( home_url( '/' ) ),
			'ajaxUrl'        => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'animations'     => (bool) palltheme_mod( 'perf_animations' ),
			'videos'         => (bool) palltheme_mod( 'perf_videos' ),
			'i18n'           => array(
				'searching'  => __( 'Searching…', 'palltheme' ),
				'noResults'  => __( 'No results found', 'palltheme' ),
				/* translators: %d: number of search results. */
				'viewAll'    => __( 'View all %d results', 'palltheme' ),
				'light'      => __( 'Light mode', 'palltheme' ),
				'dark'       => __( 'Dark mode', 'palltheme' ),
				'system'     => __( 'System theme', 'palltheme' ),
				'openMenu'   => __( 'Open menu', 'palltheme' ),
				'closeMenu'  => __( 'Close menu', 'palltheme' ),
				'subMenu'    => __( 'Show submenu', 'palltheme' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'palltheme_enqueue_assets' );

/**
 * Whether the current request needs WooCommerce styling.
 */
function palltheme_is_woo_context(): bool {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() || is_search() ) {
		return true;
	}
	if ( is_front_page() && ! is_singular() ) {
		return true; // The built-in homepage includes a product section.
	}
	if ( is_singular() ) {
		// Product sections (shortcodes, blocks or Elementor widgets) in the content or the Elementor
		// layout, or related products on services, solutions and posts.
		$id      = (int) get_queried_object_id();
		$content = (string) get_post_field( 'post_content', $id );
		if ( palltheme_is_elementor_page() ) {
			$content .= (string) get_post_meta( $id, '_elementor_data', true );
		}
		if ( preg_match( '/pall_products|pall_home|yith_wcwl|yith_woocompare|\[(?:products|product|product_page|product_category|add_to_cart|recent_products|sale_products|featured_products|best_selling_products)[\s\]]|wp:woocommerce\/|"widgetType":"(?:woocommerce|wc)-/', $content ) ) {
			return true;
		}
		return (bool) palltheme_meta( $id, 'related_products' );
	}
	return false;
}

/**
 * Leave store scripts and styles out of pages that show no products.
 *
 * WooCommerce, YITH Wishlist and YITH Compare load their assets on every page
 * (around 20 files, including React, lodash and moment). Order attribution is
 * kept everywhere because it records where a visitor first arrived.
 * Disable with: add_filter( 'palltheme_trim_store_assets', '__return_false' );
 */
function palltheme_trim_store_assets(): void {
	if ( ! class_exists( 'WooCommerce' ) || palltheme_is_woo_context() || ! apply_filters( 'palltheme_trim_store_assets', true ) ) {
		return;
	}
	$assets = (array) apply_filters(
		'palltheme_store_asset_handles',
		array(
			'scripts' => array( 'wc-add-to-cart', 'woocommerce', 'wc-jquery-blockui', 'jquery-blockui', 'wc-cart-fragments', 'wc-prettyPhoto', 'wc-prettyPhoto-init', 'prettyPhoto', 'prettyPhoto-init', 'jquery-selectBox', 'jquery-yith-wcwl', 'yith-wcwl-add-to-wishlist', 'yith-wcwl-add-to-wishlist-gutenberg', 'yith-woocompare-main' ),
			'styles'  => array( 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'wc-blocks-style', 'yith-wcwl-main', 'yith-wcwl-add-to-wishlist', 'jquery-selectBox', 'woocommerce_prettyPhoto_css', 'jquery-fixedheadertable-style', 'yith_woocompare_page', 'yith-woocompare-widget' ),
		)
	);
	foreach ( (array) ( $assets['scripts'] ?? array() ) as $handle ) {
		wp_dequeue_script( $handle );
	}
	foreach ( (array) ( $assets['styles'] ?? array() ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'palltheme_trim_store_assets', 100 );
add_action( 'wp_print_footer_scripts', 'palltheme_trim_store_assets', 1 );

/**
 * Elementor loads its own copy of the Google Fonts the theme already loads
 * (once its global typography mirrors the theme fonts): drop the duplicates.
 */
function palltheme_dedupe_elementor_fonts(): void {
	if ( ! palltheme_fonts_url() ) {
		return;
	}
	foreach ( array_unique( array( (string) palltheme_mod( 'font_heading' ), (string) palltheme_mod( 'font_body' ) ) ) as $family ) {
		$slug = strtolower( str_replace( ' ', '', $family ) );
		wp_dequeue_style( 'elementor-gf-' . $slug );
		wp_dequeue_style( 'elementor-gf-local-' . $slug );
	}
}
add_action( 'wp_print_styles', 'palltheme_dedupe_elementor_fonts', 1 );
add_action( 'wp_print_footer_scripts', 'palltheme_dedupe_elementor_fonts', 1 );

/**
 * Resource hints for Google Fonts.
 *
 * @param array  $urls Hints.
 * @param string $relation Relation type.
 */
function palltheme_resource_hints( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation && palltheme_fonts_url() && palltheme_mod( 'perf_preload_fonts' ) ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'palltheme_resource_hints', 10, 2 );

/**
 * Apply the color scheme before first paint (prevents a light flash in
 * dark mode). Tiny, blocking by design.
 */
function palltheme_color_scheme_script(): void {
	$default = esc_js( (string) palltheme_mod( 'dark_mode_default' ) );
	$allow   = palltheme_mod( 'dark_mode_toggle' ) ? 'true' : 'false';
	?>
	<script id="palltheme-scheme">(function(){var d=document.documentElement,p="<?php echo $default; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>";try{if(<?php echo $allow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>){p=localStorage.getItem("pt-scheme")||p;}}catch(e){}var r=p==="system"?(matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):p;d.setAttribute("data-scheme",r);d.setAttribute("data-scheme-pref",p);d.classList.add("js");})();</script>
	<?php
}
add_action( 'wp_head', 'palltheme_color_scheme_script', 1 );

/**
 * Native lazy-loading switch.
 */
add_filter( 'wp_lazy_loading_enabled', static fn( $default ) => palltheme_mod( 'perf_lazyload' ) ? $default : false );

// Classic themes get the whole block-library stylesheet on every page; load per-block styles instead.
add_filter( 'should_load_separate_core_block_assets', static fn( $load ) => palltheme_mod( 'perf_block_css' ) ? true : $load );

/**
 * Remove the emoji detection script and styles (all current browsers render emoji natively).
 */
function palltheme_disable_emoji(): void {
	if ( ! palltheme_mod( 'perf_no_emoji' ) ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'palltheme_disable_emoji' );

/**
 * Block editor styles + fonts so the editor matches the front end.
 */
function palltheme_editor_assets(): void {
	$fonts = palltheme_fonts_url();
	if ( $fonts ) {
		wp_enqueue_style( 'palltheme-editor-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	wp_add_inline_style( 'wp-block-library', palltheme_dynamic_css() );
}
add_action( 'enqueue_block_editor_assets', 'palltheme_editor_assets' );
