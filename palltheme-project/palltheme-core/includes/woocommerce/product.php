<?php
/**
 * WooCommerce product extras (data features; styling is in the theme).
 *
 * - Specifications tab (responsive, scrollable table, group headings)
 * - Downloads tab (datasheets, manuals)
 * - Video tab (lazy embed)
 * - Buy Now button (adds to cart → checkout)
 * - Quick view button on product cards
 * - Recently viewed products
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Specification table HTML.
 *
 * @param array<int,array{0:string,1:string}> $rows    Rows; a label starting with "#" is a group heading.
 * @param string                              $caption Optional caption.
 */
function pallcore_spec_table( array $rows, string $caption = '' ): string {
	if ( ! $rows ) {
		return '';
	}
	$html = '<div class="pt-spec-wrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Specifications (scrolls horizontally on small screens)', 'palltheme-core' ) . '"><table class="pt-spec-table">';
	if ( $caption ) {
		$html .= '<caption>' . esc_html( $caption ) . '</caption>';
	}
	$html .= '<tbody>';
	foreach ( $rows as $row ) {
		$label = (string) ( $row[0] ?? '' );
		if ( str_starts_with( $label, '#' ) ) {
			$html .= '<tr class="pt-spec-group"><th colspan="2" scope="colgroup">' . esc_html( ltrim( $label, '# ' ) ) . '</th></tr>';
			continue;
		}
		$html .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) ( $row[1] ?? '' ) ) ) . '</td></tr>';
	}
	return $html . '</tbody></table></div>';
}

/**
 * Add tabs.
 *
 * @param array $tabs Tabs.
 */
function pallcore_product_tabs( array $tabs ): array {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return $tabs;
	}
	$id = $product->get_id();

	if ( pallcore_meta( $id, 'specs' ) ) {
		$tabs['pall_specs'] = array(
			'title'    => __( 'Specifications', 'palltheme-core' ),
			'priority' => 5,
			'callback' => static function () use ( $id ) {
				echo '<h2>' . esc_html__( 'Technical specifications', 'palltheme-core' ) . '</h2>';
				echo pallcore_spec_table( (array) pallcore_meta( $id, 'specs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			},
		);
	}

	$downloads = (array) ( pallcore_meta( $id, 'downloads' ) ?: array() );
	if ( $downloads ) {
		$tabs['pall_downloads'] = array(
			'title'    => __( 'Downloads', 'palltheme-core' ),
			'priority' => 40,
			'callback' => static function () use ( $downloads ) {
				echo '<h2>' . esc_html__( 'Datasheets & downloads', 'palltheme-core' ) . '</h2><ul class="pt-downloads">';
				foreach ( $downloads as $row ) {
					$url = esc_url( (string) ( $row[1] ?? '' ) );
					if ( ! $url ) {
						continue;
					}
					$ext = strtoupper( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
					printf(
						'<li><a href="%1$s" target="_blank" rel="noopener">%2$s%3$s<small>%4$s</small></a></li>',
						$url, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above.
						pallcore_ui_icon( 'download' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html( (string) $row[0] ),
						esc_html( $ext )
					);
				}
				echo '</ul>';
			},
		);
	}

	$video = (string) pallcore_meta( $id, 'video' );
	if ( $video ) {
		$tabs['pall_video'] = array(
			'title'    => __( 'Video', 'palltheme-core' ),
			'priority' => 45,
			'callback' => static function () use ( $video ) {
				$embed = wp_oembed_get( $video );
				if ( $embed ) {
					$embed = str_replace( '<iframe ', '<iframe loading="lazy" ', $embed );
				} else {
					$embed = wp_video_shortcode( array( 'src' => $video, 'preload' => 'none' ) );
				}
				echo '<div class="pt-embed">' . $embed . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oEmbed / core video markup.
			},
		);
	}

	// Rename "Additional information" — WooCommerce attributes stay there.
	if ( isset( $tabs['additional_information'] ) ) {
		$tabs['additional_information']['title'] = __( 'Attributes', 'palltheme-core' );
	}
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'pallcore_product_tabs', 20 );

/*
 * --------------------------------------------------------------------
 * Buy Now
 * --------------------------------------------------------------------
 */

/**
 * Button next to Add to cart (simple + variable products).
 */
function pallcore_buy_now_button(): void {
	global $product;
	if ( ! $product || $product->is_type( 'external' ) || $product->is_type( 'grouped' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}
	if ( ! apply_filters( 'pallcore_show_buy_now', true, $product ) ) {
		return;
	}
	printf(
		'<button type="submit" name="pt_buy_now" value="1" class="single_add_to_cart_button button alt pt-buy-now">%s</button>',
		esc_html__( 'Buy now', 'palltheme-core' )
	);
}
add_action( 'woocommerce_after_add_to_cart_button', 'pallcore_buy_now_button' );

/**
 * Redirect to checkout after a Buy Now add.
 *
 * @param string $url Redirect URL.
 */
function pallcore_buy_now_redirect( $url ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies the add-to-cart request itself.
	if ( ! empty( $_REQUEST['pt_buy_now'] ) ) {
		return wc_get_checkout_url();
	}
	return $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'pallcore_buy_now_redirect', 99 );

/*
 * --------------------------------------------------------------------
 * Quick view
 * --------------------------------------------------------------------
 */

/**
 * Quick view button in the theme's card action slot.
 *
 * @param WC_Product $product Product.
 */
function pallcore_quickview_button( $product ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	printf(
		'<span class="pt-card-action"><button type="button" data-pt-quickview="%1$s" aria-label="%2$s">%3$s</button></span>',
		esc_url( rest_url( 'palltheme-core/v1/quickview/' . $product->get_id() ) ),
		/* translators: %s: product name. */
		esc_attr( sprintf( __( 'Quick view: %s', 'palltheme-core' ), $product->get_name() ) ),
		pallcore_ui_icon( 'eye' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
add_action( 'palltheme_product_card_actions', 'pallcore_quickview_button', 10 );

/**
 * Quick view may show variable product forms → load the variation script on catalog pages.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_shop() || is_product_taxonomy() || is_front_page() || is_search() ) {
			wp_enqueue_script( 'wc-add-to-cart-variation' );
		}
	}
);

/*
 * --------------------------------------------------------------------
 * Recently viewed — tracked in the visitor's browser (localStorage) and
 * fetched over REST, so it stays correct behind full-page caches.
 * --------------------------------------------------------------------
 */

/**
 * Placeholder filled by assets/js/front.js.
 */
function pallcore_recent_products(): void {
	printf(
		'<section class="pt-recent pt-full" data-pall-recent="%1$d" data-endpoint="%2$s" hidden><h2>%3$s</h2><div data-pall-recent-list></div></section>',
		(int) get_the_ID(),
		esc_url( rest_url( 'palltheme-core/v1/products' ) ),
		esc_html__( 'Recently viewed', 'palltheme-core' )
	);
}
add_action( 'woocommerce_after_single_product_summary', 'pallcore_recent_products', 30 );

/**
 * REST: product grid HTML for a list of IDs (public, published products only).
 */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'palltheme-core/v1',
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => implode( ',', array_slice( array_filter( array_map( 'absint', explode( ',', (string) $v ) ) ), 0, 8 ) ),
					),
				),
				'callback'            => static function ( WP_REST_Request $r ) {
					$ids = (string) $r->get_param( 'ids' );
					if ( ! array_key_exists( 'post', $GLOBALS ) ) {
						$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's [products] reads it unchecked.
					}
					$html = $ids ? do_shortcode( '[products ids="' . esc_attr( $ids ) . '" columns="4" orderby="post__in"]' ) : '';
					return new WP_REST_Response( array( 'html' => str_contains( $html, '<li' ) ? $html : '' ) );
				},
			)
		);
	}
);
