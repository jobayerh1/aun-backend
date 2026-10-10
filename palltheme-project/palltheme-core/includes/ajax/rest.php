<?php
/**
 * REST endpoints (public, read-only):
 *   GET /wp-json/palltheme-core/v1/search?q=…      live search
 *   GET /wp-json/palltheme-core/v1/quickview/{id}  product quick view
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'palltheme-core/v1',
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'pallcore_rest_search',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $v ) => is_string( $v ) && mb_strlen( trim( $v ) ) >= 2 && mb_strlen( $v ) <= 80,
					),
				),
			)
		);
		register_rest_route(
			'palltheme-core/v1',
			'/quickview/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'pallcore_rest_quickview',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}
);

/**
 * Live search across products, services, solutions, posts and case studies.
 *
 * @param WP_REST_Request $request Request.
 */
function pallcore_rest_search( WP_REST_Request $request ): WP_REST_Response {
	$q     = trim( (string) $request->get_param( 'q' ) );
	$cache = 'pallcore_s_' . md5( strtolower( $q ) . get_locale() );
	$hit   = get_transient( $cache );
	if ( false !== $hit ) {
		return pallcore_rest_cacheable( $hit );
	}

	$types = array_filter( array_map( 'trim', explode( ',', (string) pallcore_setting( 'search_types' ) ) ) );
	$types = array_values( array_filter( $types, 'post_type_exists' ) );

	$groups = array();
	$total  = 0;

	// Product categories that match.
	if ( in_array( 'product', $types, true ) && taxonomy_exists( 'product_cat' ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name__like' => $q,
				'number'     => 3,
				'hide_empty' => true,
			)
		);
		if ( $terms && ! is_wp_error( $terms ) ) {
			$items = array();
			foreach ( $terms as $term ) {
				$items[] = array(
					'title' => $term->name,
					/* translators: %d: product count. */
					'sub'   => sprintf( _n( '%d product', '%d products', $term->count, 'palltheme-core' ), $term->count ),
					'url'   => get_term_link( $term ),
					'thumb' => '',
				);
			}
			$groups[] = array(
				'label' => __( 'Categories', 'palltheme-core' ),
				'items' => $items,
			);
		}
	}

	foreach ( $types as $type ) {
		$limit = 'product' === $type ? 5 : 3;
		$ids   = array();

		// Exact SKU match first.
		if ( 'product' === $type && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = wc_get_product_id_by_sku( $q );
			if ( $sku_id ) {
				$ids[] = wp_get_post_parent_id( $sku_id ) ?: $sku_id;
			}
		}

		$query = new WP_Query(
			array(
				'post_type'              => $type,
				'post_status'            => 'publish',
				's'                      => $q,
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
			)
		);
		$ids    = array_unique( array_merge( $ids, $query->posts ) );
		$total += max( (int) $query->found_posts, count( $ids ) );

		if ( ! $ids ) {
			continue;
		}

		$items = array();
		foreach ( array_slice( $ids, 0, $limit ) as $id ) {
			$item = array(
				'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
				'url'   => get_permalink( $id ),
				'thumb' => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: '',
				'sub'   => '',
				'price' => '',
			);
			if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$item['price'] = html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES );
					$cats          = get_the_terms( $id, 'product_cat' );
					$item['sub']   = trim( ( $cats && ! is_wp_error( $cats ) ? $cats[0]->name : '' ) . ( $product->get_sku() ? ' · SKU ' . $product->get_sku() : '' ), ' ·' );
					if ( ! $item['thumb'] ) {
						$item['thumb'] = (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' );
					}
				}
			} else {
				$tax         = array( 'post' => 'category', 'pall_service' => 'pall_service_cat', 'pall_solution' => 'pall_industry', 'pall_case_study' => 'pall_industry' );
				$term        = isset( $tax[ $type ] ) ? get_the_terms( $id, $tax[ $type ] ) : false;
				$item['sub'] = ( $term && ! is_wp_error( $term ) ? $term[0]->name . ' · ' : '' ) . wp_trim_words( get_the_excerpt( $id ), 9 );
			}
			$items[] = $item;
		}

		$pto      = get_post_type_object( $type );
		$groups[] = array(
			'label' => 'product' === $type ? __( 'Products', 'palltheme-core' ) : ( 'post' === $type ? __( 'Articles', 'palltheme-core' ) : $pto->labels->name ),
			'items' => $items,
		);
	}

	$data = array(
		'query'  => $q,
		'total'  => $total,
		'groups' => $groups,
	);
	set_transient( $cache, $data, 10 * MINUTE_IN_SECONDS );
	return pallcore_rest_cacheable( $data );
}

/**
 * Response with short public cache headers (CDN-friendly).
 *
 * @param array $data Data.
 */
function pallcore_rest_cacheable( array $data ): WP_REST_Response {
	$response = new WP_REST_Response( $data );
	$response->header( 'Cache-Control', 'public, max-age=300' );
	return $response;
}

/**
 * Bust the search cache when content changes.
 */
add_action(
	'save_post',
	static function () {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted transient cleanup.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_pallcore_s_' ) . '%', $wpdb->esc_like( '_transient_timeout_pallcore_s_' ) . '%' ) );
	}
);

/**
 * Quick view HTML for a product.
 *
 * @param WP_REST_Request $request Request.
 */
function pallcore_rest_quickview( WP_REST_Request $request ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return new WP_Error( 'pallcore_no_wc', 'WooCommerce inactive', array( 'status' => 404 ) );
	}
	$id      = (int) $request->get_param( 'id' );
	$product = wc_get_product( $id );
	if ( ! $product || 'publish' !== get_post_status( $id ) || ! $product->is_visible() ) {
		return new WP_Error( 'pallcore_not_found', __( 'Product not found.', 'palltheme-core' ), array( 'status' => 404 ) );
	}

	global $post;
	$post = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	$GLOBALS['product'] = $product;

	ob_start();
	?>
	<div class="pt-qv product">
		<div class="pt-qv__img"><?php echo $product->get_image( 'woocommerce_single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="pt-qv__summary summary">
			<h2><?php echo esc_html( $product->get_name() ); ?></h2>
			<?php if ( wc_review_ratings_enabled() && $product->get_rating_count() ) : ?>
				<?php echo wc_get_rating_html( $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<p class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
			<div class="woocommerce-product-details__short-description"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<?php
			$specs = array_slice( (array) ( pallcore_meta( $id, 'specs' ) ?: array() ), 0, 5 );
			if ( $specs ) {
				echo pallcore_spec_table( $specs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			woocommerce_template_single_add_to_cart();
			?>
			<p><a class="pt-link-arrow" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php esc_html_e( 'View full details', 'palltheme-core' ); ?><?php echo pallcore_ui_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
		</div>
	</div>
	<?php
	$html = (string) ob_get_clean();
	wp_reset_postdata();

	return new WP_REST_Response( array( 'html' => $html ) );
}
