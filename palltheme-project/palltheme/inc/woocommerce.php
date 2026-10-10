<?php
/**
 * WooCommerce integration (layout + presentation only).
 *
 * WooCommerce's own styles, templates and features stay in charge; this file
 * re-arranges markup via hooks and adds theme UI (toolbar, card layout, mini
 * cart drawer, info boxes). Product data features (specs, downloads, video,
 * quick view, buy now, filters, recently viewed) live in Palltheme Core.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme support.
 */
function palltheme_woocommerce_setup(): void {
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 480,
			'single_image_width'    => 900,
			'product_grid'          => array(
				'default_rows'    => 4,
				'min_rows'        => 1,
				'default_columns' => 3,
				'min_columns'     => 2,
				'max_columns'     => 5,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'palltheme_woocommerce_setup' );

/**
 * Columns + products per page from the Customizer.
 */
add_filter( 'loop_shop_columns', static fn() => (int) palltheme_mod( 'shop_columns' ) );
add_filter( 'loop_shop_per_page', static fn() => (int) palltheme_mod( 'shop_per_page' ) );

/**
 * Whether the shop sidebar is shown.
 */
function palltheme_shop_has_sidebar(): bool {
	return palltheme_mod( 'shop_sidebar' ) && ( is_shop() || is_product_taxonomy() ) && is_active_sidebar( 'sidebar-shop' );
}

/*
 * ---------------------------------------------------------------------------
 * Page wrappers
 * ---------------------------------------------------------------------------
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Open wrappers. Archives get a header band; product pages a slim crumb bar.
 */
function palltheme_woo_wrapper_start(): void {
	if ( is_product() ) {
		echo '<main id="primary" class="pt-main pt-shop-single"><div class="pt-container"><div class="pt-crumb-bar">';
		palltheme_breadcrumbs();
		echo '</div>';
		return;
	}

	$desc = '';
	if ( is_product_taxonomy() ) {
		$desc = term_description();
	} elseif ( is_shop() ) {
		$shop_id = wc_get_page_id( 'shop' );
		$desc    = $shop_id > 0 ? get_the_excerpt( $shop_id ) : '';
	}
	palltheme_page_header( woocommerce_page_title( false ), (string) $desc, __( 'Store', 'palltheme' ) );

	$sidebar = palltheme_shop_has_sidebar();
	echo '<main id="primary" class="pt-main pt-section pt-section--tight pt-shop"><div class="pt-container ' . ( $sidebar ? 'pt-layout-shop' : '' ) . '">';
	if ( $sidebar ) {
		echo '<aside id="pt-shop-filters" class="pt-shop-filters" aria-label="' . esc_attr__( 'Product filters', 'palltheme' ) . '">';
		echo '<div class="pt-shop-filters__head"><h2>' . esc_html__( 'Filters', 'palltheme' ) . '</h2><button type="button" class="pt-icon-btn" data-pt-filters-close>' . palltheme_icon( 'close' ) . '<span class="screen-reader-text">' . esc_html__( 'Close filters', 'palltheme' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		dynamic_sidebar( 'sidebar-shop' );
		echo '</aside>';
	}
	echo '<div class="pt-shop-results" id="pt-shop-results" data-pt-shop-results>';
}
add_action( 'woocommerce_before_main_content', 'palltheme_woo_wrapper_start', 10 );

/**
 * Close wrappers.
 */
function palltheme_woo_wrapper_end(): void {
	if ( is_product() ) {
		echo '</div></main>';
		return;
	}
	echo '</div></div></main>';
}
add_action( 'woocommerce_after_main_content', 'palltheme_woo_wrapper_end', 10 );

/**
 * The page header band prints the title; hide WooCommerce's duplicate.
 */
add_filter( 'woocommerce_show_page_title', '__return_false' );
remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );

/*
 * ---------------------------------------------------------------------------
 * Shop toolbar: filters button, result count, view switch, sorting
 * ---------------------------------------------------------------------------
 */
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

/**
 * Toolbar.
 */
function palltheme_shop_toolbar(): void {
	echo '<div class="pt-shop-toolbar">';
	if ( palltheme_shop_has_sidebar() ) {
		echo '<button type="button" class="pt-btn pt-btn--ghost pt-btn--sm pt-shop-toolbar__filters" data-pt-filters-open aria-controls="pt-shop-filters" aria-expanded="false">' . palltheme_icon( 'filter' ) . esc_html__( 'Filters', 'palltheme' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	woocommerce_result_count();
	echo '<div class="pt-shop-toolbar__end">';
	echo '<div class="pt-view-switch" role="group" aria-label="' . esc_attr__( 'Product view', 'palltheme' ) . '">';
	echo '<button type="button" class="pt-icon-btn is-active" data-pt-view="grid" aria-pressed="true">' . palltheme_icon( 'grid' ) . '<span class="screen-reader-text">' . esc_html__( 'Grid view', 'palltheme' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<button type="button" class="pt-icon-btn" data-pt-view="list" aria-pressed="false">' . palltheme_icon( 'list' ) . '<span class="screen-reader-text">' . esc_html__( 'List view', 'palltheme' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
	woocommerce_catalog_ordering();
	echo '</div></div>';

	// Active filter chips (removable).
	palltheme_active_filters();
}
add_action( 'woocommerce_before_shop_loop', 'palltheme_shop_toolbar', 20 );

/**
 * Active filters as removable chips (reads WooCommerce's own query vars).
 */
function palltheme_active_filters(): void {
	$chips = array();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter params.
	foreach ( $_GET as $key => $value ) {
		$key = sanitize_key( $key );
		if ( str_starts_with( $key, 'filter_' ) ) {
			$tax = 'pa_' . substr( $key, 7 );
			foreach ( explode( ',', sanitize_text_field( wp_unslash( (string) $value ) ) ) as $slug ) {
				$term = get_term_by( 'slug', $slug, $tax );
				if ( $term ) {
					$remaining = array_diff( explode( ',', sanitize_text_field( wp_unslash( (string) $value ) ) ), array( $slug ) );
					$url       = $remaining ? add_query_arg( $key, implode( ',', $remaining ) ) : remove_query_arg( $key );
					$chips[]   = array( $term->name, $url );
				}
			}
		}
	}
	if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) {
		$min     = isset( $_GET['min_price'] ) ? wc_price( (float) $_GET['min_price'] ) : '';
		$max     = isset( $_GET['max_price'] ) ? wc_price( (float) $_GET['max_price'] ) : '';
		$chips[] = array( wp_strip_all_tags( trim( $min . ' – ' . $max, ' –' ) ), remove_query_arg( array( 'min_price', 'max_price' ) ) );
	}
	if ( isset( $_GET['rating_filter'] ) ) {
		/* translators: %s: rating. */
		$chips[] = array( sprintf( __( 'Rated %s+', 'palltheme' ), sanitize_text_field( wp_unslash( $_GET['rating_filter'] ) ) ), remove_query_arg( 'rating_filter' ) );
	}
	if ( isset( $_GET['stock_status'] ) ) {
		$chips[] = array( __( 'In stock', 'palltheme' ), remove_query_arg( 'stock_status' ) );
	}
	if ( isset( $_GET['product_brand'] ) ) {
		$brand   = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET['product_brand'] ) ), 'product_brand' );
		$chips[] = array( $brand ? $brand->name : __( 'Brand', 'palltheme' ), remove_query_arg( 'product_brand' ) );
	}
	// phpcs:enable
	if ( ! $chips ) {
		return;
	}
	echo '<ul class="pt-chips pt-active-filters">';
	foreach ( $chips as $chip ) {
		printf(
			'<li><a class="pt-chip pt-chip--remove" href="%1$s" data-pt-filter-link>%2$s%3$s<span class="screen-reader-text">%4$s</span></a></li>',
			esc_url( remove_query_arg( 'paged', $chip[1] ) ),
			esc_html( $chip[0] ),
			palltheme_icon( 'close' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Remove filter', 'palltheme' )
		);
	}
	$shop = is_product_taxonomy() ? get_term_link( get_queried_object() ) : wc_get_page_permalink( 'shop' );
	printf( '<li><a class="pt-link-arrow" href="%1$s" data-pt-filter-link>%2$s</a></li>', esc_url( is_wp_error( $shop ) ? wc_get_page_permalink( 'shop' ) : $shop ), esc_html__( 'Clear all', 'palltheme' ) );
	echo '</ul>';
}

/*
 * ---------------------------------------------------------------------------
 * Product cards
 * ---------------------------------------------------------------------------
 */
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );

/**
 * Card media (image + badges + hover actions).
 */
function palltheme_loop_media(): void {
	global $product;
	echo '<div class="pt-product-card__media">';
	echo '<a href="' . esc_url( get_permalink() ) . '" class="pt-product-card__img" tabindex="-1" aria-hidden="true">';
	echo woocommerce_get_product_thumbnail( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</a>';

	echo '<div class="pt-product-card__badges">';
	if ( $product->is_on_sale() ) {
		$pct = palltheme_sale_percent( $product );
		/* translators: %d: discount percent. */
		echo '<span class="pt-badge-label pt-badge-label--sale">' . esc_html( $pct ? sprintf( __( '-%d%%', 'palltheme' ), $pct ) : __( 'Sale', 'palltheme' ) ) . '</span>';
	}
	if ( $product->is_featured() ) {
		echo '<span class="pt-badge-label">' . esc_html__( 'Featured', 'palltheme' ) . '</span>';
	}
	if ( ! $product->is_in_stock() ) {
		echo '<span class="pt-badge-label pt-badge-label--muted">' . esc_html__( 'Out of stock', 'palltheme' ) . '</span>';
	}
	echo '</div>';

	echo '<div class="pt-product-card__actions">';
	do_action( 'palltheme_product_card_actions', $product );
	echo '</div>';
	echo '</div>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'palltheme_loop_media', 10 );

/**
 * Wishlist button in card actions (YITH, when active).
 */
function palltheme_card_wishlist(): void {
	if ( defined( 'YITH_WCWL' ) && shortcode_exists( 'yith_wcwl_add_to_wishlist' ) ) {
		echo '<div class="pt-card-action pt-card-action--wishlist">' . do_shortcode( '[yith_wcwl_add_to_wishlist]' ) . '</div>';
	}
}
add_action( 'palltheme_product_card_actions', 'palltheme_card_wishlist', 20 );

/**
 * Sale percentage for simple and variable products.
 */
function palltheme_sale_percent( WC_Product $product ): int {
	if ( $product->is_type( 'variable' ) ) {
		$regular = (float) $product->get_variation_regular_price( 'max' );
		$sale    = (float) $product->get_variation_sale_price( 'min' );
	} else {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
	}
	return ( $regular > 0 && $sale > 0 && $sale < $regular ) ? (int) round( ( 1 - $sale / $regular ) * 100 ) : 0;
}

/**
 * Card body: category label, linked title, short spec line.
 */
function palltheme_loop_title(): void {
	global $product;
	$terms = get_the_terms( $product->get_id(), 'product_brand' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
	}
	echo '<div class="pt-product-card__body">';
	if ( $terms && ! is_wp_error( $terms ) ) {
		echo '<span class="pt-product-card__cat">' . esc_html( $terms[0]->name ) . '</span>';
	}
	echo '<h2 class="woocommerce-loop-product__title pt-product-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>';
	if ( $product->get_sku() ) {
		/* translators: %s: SKU. */
		echo '<span class="pt-product-card__sku">' . esc_html( sprintf( __( 'SKU: %s', 'palltheme' ), $product->get_sku() ) ) . '</span>';
	}
	$excerpt = $product->get_short_description();
	if ( $excerpt ) {
		echo '<div class="pt-product-card__excerpt">' . esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 24 ) ) . '</div>';
	}
}
add_action( 'woocommerce_shop_loop_item_title', 'palltheme_loop_title', 10 );

/**
 * Close card body after price/rating, then show stock + add to cart in a footer row.
 */
function palltheme_loop_body_close(): void {
	echo '</div>';
}
add_action( 'woocommerce_after_shop_loop_item_title', 'palltheme_loop_body_close', 99 );

/**
 * Card footer wrapper around add-to-cart.
 */
add_action( 'woocommerce_after_shop_loop_item', static function () {
	echo '<div class="pt-product-card__foot">';
}, 1 );
add_action( 'woocommerce_after_shop_loop_item', static function () {
	echo '</div>';
}, 99 );

/**
 * Card class for every product in a loop (not the main single product).
 */
add_filter( 'woocommerce_post_class', static function ( $classes, $product ) {
	if ( ! is_product() || get_queried_object_id() !== $product->get_id() ) {
		$classes[] = 'pt-product-card';
	}
	return $classes;
}, 10, 2 );

/*
 * ---------------------------------------------------------------------------
 * Load-more pagination
 * ---------------------------------------------------------------------------
 */
function palltheme_loadmore_button(): void {
	if ( 'loadmore' !== palltheme_mod( 'shop_pagination' ) || ! wc_get_loop_prop( 'is_paginated' ) ) {
		return;
	}
	$total   = (int) wc_get_loop_prop( 'total_pages' );
	$current = max( 1, (int) wc_get_loop_prop( 'current_page' ) );
	if ( $current >= $total ) {
		return;
	}
	printf(
		'<div class="pt-loadmore"><a class="pt-btn pt-btn--ghost" href="%1$s" data-pt-loadmore>%2$s</a></div>',
		esc_url( get_pagenum_link( $current + 1 ) ),
		esc_html__( 'Load more products', 'palltheme' )
	);
}
add_action( 'woocommerce_after_shop_loop', 'palltheme_loadmore_button', 9 );
add_filter(
	'woocommerce_pagination_args',
	static function ( $args ) {
		if ( 'loadmore' === palltheme_mod( 'shop_pagination' ) ) {
			$args['type'] = 'list';
		}
		return $args;
	}
);

/*
 * ---------------------------------------------------------------------------
 * Header cart count + mini cart drawer
 * ---------------------------------------------------------------------------
 */

/**
 * Cart count badge (refreshed through WooCommerce fragments).
 */
function palltheme_cart_count_html(): void {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	printf(
		'<span class="pt-badge pt-cart-count" %1$s>%2$s</span>',
		$count ? '' : 'hidden',
		esc_html( (string) $count )
	);
}

/**
 * Fragments.
 *
 * @param array $fragments Fragments.
 */
function palltheme_cart_fragments( array $fragments ): array {
	ob_start();
	palltheme_cart_count_html();
	$fragments['span.pt-cart-count'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'palltheme_cart_fragments' );

/**
 * Mini cart drawer (content kept current by WooCommerce cart fragments).
 */
function palltheme_minicart_drawer(): void {
	if ( ! palltheme_mod( 'header_cart' ) || is_cart() || is_checkout() ) {
		return;
	}
	?>
	<div id="pt-minicart" class="pt-drawer" role="dialog" aria-modal="true" aria-labelledby="pt-minicart-title" hidden>
		<div class="pt-drawer__backdrop" data-pt-minicart-close></div>
		<div class="pt-drawer__panel">
			<div class="pt-drawer__head">
				<h2 id="pt-minicart-title"><?php esc_html_e( 'Your cart', 'palltheme' ); ?></h2>
				<button type="button" class="pt-icon-btn" data-pt-minicart-close><?php palltheme_the_icon( 'close' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Close cart', 'palltheme' ); ?></span></button>
			</div>
			<div class="pt-drawer__body">
				<div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'palltheme_minicart_drawer', 5 );

/*
 * ---------------------------------------------------------------------------
 * Single product
 * ---------------------------------------------------------------------------
 */

/**
 * Shipping / warranty / support info under the add-to-cart button.
 * Per-product overrides come from Palltheme Core fields.
 */
function palltheme_product_assurance(): void {
	global $product;
	$id       = $product->get_id();
	$shipping = (string) palltheme_meta( $id, 'shipping_info' ) ?: (string) palltheme_mod( 'shop_shipping_note' );
	$warranty = (string) palltheme_meta( $id, 'warranty' ) ?: (string) palltheme_mod( 'shop_warranty_note' );
	$phone    = palltheme_biz( 'phone' );
	$rows     = array_filter(
		array(
			array( 'truck', __( 'Shipping', 'palltheme' ), $shipping ),
			array( 'shield', __( 'Warranty', 'palltheme' ), $warranty ),
			$phone ? array( 'support', __( 'Expert advice', 'palltheme' ), sprintf( /* translators: %s: phone. */ __( 'Questions about compatibility? Call %s', 'palltheme' ), $phone ) ) : null,
		)
	);
	if ( ! $rows ) {
		return;
	}
	echo '<ul class="pt-assurance">';
	foreach ( $rows as $row ) {
		printf(
			'<li>%1$s<div><strong>%2$s</strong><span>%3$s</span></div></li>',
			palltheme_icon( $row[0] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $row[1] ),
			esc_html( $row[2] )
		);
	}
	echo '</ul>';
}
add_action( 'woocommerce_single_product_summary', 'palltheme_product_assurance', 38 ); // After wishlist/compare buttons (31–35).

/**
 * "Frequently bought together" from the product's cross-sells.
 */
function palltheme_bought_together(): void {
	global $product;
	$ids = $product->get_cross_sell_ids();
	if ( ! $ids ) {
		return;
	}
	echo '<section class="pt-section pt-section--tight pt-bought-together"><h2 class="pt-section-title">' . esc_html__( 'Frequently bought together', 'palltheme' ) . '</h2>';
	echo do_shortcode( '[products ids="' . esc_attr( implode( ',', array_map( 'absint', $ids ) ) ) . '" columns="4" limit="4"]' );
	echo '</section>';
}
add_action( 'woocommerce_after_single_product_summary', 'palltheme_bought_together', 16 );

/**
 * Related/upsell columns.
 */
add_filter(
	'woocommerce_output_related_products_args',
	static function ( $args ) {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}
);
add_filter( 'woocommerce_upsell_display_args', static fn( $args ) => array_merge( $args, array( 'columns' => 4, 'posts_per_page' => 4 ) ) );
add_filter( 'woocommerce_cross_sells_columns', static fn() => 4 );

/*
 * ---------------------------------------------------------------------------
 * Cart & checkout
 * ---------------------------------------------------------------------------
 */

/**
 * Checkout trust strip under the place-order button (classic checkout).
 */
function palltheme_checkout_trust(): void {
	echo '<p class="pt-checkout-trust">' . palltheme_icon( 'shield' ) . esc_html__( 'Secure checkout — your payment details are encrypted and never stored on this site.', 'palltheme' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'woocommerce_review_order_after_submit', 'palltheme_checkout_trust' );
