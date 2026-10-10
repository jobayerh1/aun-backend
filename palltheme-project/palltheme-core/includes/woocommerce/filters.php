<?php
/**
 * "Palltheme: Product Filters" widget.
 *
 * Filters are generated from your catalog — product categories, brands,
 * every global product attribute (Processor, RAM, Storage, Interface,
 * Port Speed, Form Factor, Capacity, Warranty…), price, availability and
 * rating. They use WooCommerce's own query parameters (filter_*, min_price,
 * max_price, rating_filter), so filtering is done by WooCommerce's indexed
 * queries and every filter is a normal, crawlable link. The theme upgrades
 * the links to AJAX.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget.
 */
class Pallcore_Filters_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'pallcore_filters',
			__( 'Palltheme: Product Filters', 'palltheme-core' ),
			array(
				'description'                 => __( 'All shop filters in one widget, built automatically from your categories, brands and product attributes.', 'palltheme-core' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Defaults.
	 */
	private function defaults(): array {
		return array(
			'categories'   => 1,
			'brands'       => 1,
			'price'        => 1,
			'availability' => 1,
			'rating'       => 1,
			'attributes'   => 1,
			'exclude'      => '',
		);
	}

	/**
	 * Output.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Settings.
	 */
	public function widget( $args, $instance ): void {
		if ( ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}
		$o    = wp_parse_args( $instance, $this->defaults() );
		$html = '';

		if ( $o['categories'] ) {
			$html .= $this->categories();
		}
		if ( $o['brands'] && taxonomy_exists( 'product_brand' ) ) {
			$html .= $this->taxonomy_links( 'product_brand', __( 'Brand', 'palltheme-core' ) );
		}
		if ( $o['price'] ) {
			$html .= $this->price();
		}
		if ( $o['attributes'] ) {
			$exclude = array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $o['exclude'] ) ) );
			foreach ( wc_get_attribute_taxonomies() as $attr ) {
				if ( in_array( $attr->attribute_name, $exclude, true ) ) {
					continue;
				}
				$html .= $this->attribute( $attr );
			}
		}
		if ( $o['availability'] ) {
			$html .= $this->availability();
		}
		if ( $o['rating'] && wc_review_ratings_enabled() ) {
			$html .= $this->rating();
		}

		if ( '' === $html ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in builders.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Current archive URL with current filter args (without pagination).
	 */
	private function base_url(): string {
		if ( is_product_taxonomy() ) {
			$link = get_term_link( get_queried_object() );
			$base = is_wp_error( $link ) ? wc_get_page_permalink( 'shop' ) : $link;
		} else {
			$base = wc_get_page_permalink( 'shop' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter args.
		foreach ( $_GET as $key => $value ) {
			$key = sanitize_key( $key );
			if ( 'paged' === $key || is_array( $value ) ) {
				continue;
			}
			$base = add_query_arg( $key, rawurlencode( sanitize_text_field( wp_unslash( $value ) ) ), $base );
		}
		return $base;
	}

	/**
	 * Open a filter group.
	 */
	private function group( string $id, string $title, string $body, bool $open = true ): string {
		return '<details class="pt-filter" id="pt-filter-' . esc_attr( $id ) . '"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html( $title ) . pallcore_ui_icon( 'chevron' ) . '</summary>' . $body . '</details>';
	}

	/**
	 * Option link.
	 */
	private function option( string $url, string $label, bool $active, int $count = -1 ): string {
		return sprintf(
			'<li><a class="pt-filter__opt%1$s" href="%2$s" rel="nofollow" aria-pressed="%3$s"><span class="pt-filter__box" aria-hidden="true"></span>%4$s%5$s</a></li>',
			$active ? ' is-active' : '',
			esc_url( $url ),
			$active ? 'true' : 'false',
			esc_html( $label ),
			$count >= 0 ? '<span class="pt-filter__count">' . esc_html( number_format_i18n( $count ) ) . '</span>' : ''
		);
	}

	/**
	 * Category links (navigate to category archives, keeping filters).
	 */
	private function categories(): string {
		$current = is_product_category() ? get_queried_object_id() : 0;
		$parent  = 0;
		if ( $current ) {
			$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $current, 'hide_empty' => true, 'fields' => 'ids' ) );
			$parent   = $children ? $current : (int) get_term( $current )->parent;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => true,
				'orderby'    => 'menu_order',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			)
		);
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$query = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $k => $v ) {
			if ( 'paged' !== $k && ! is_array( $v ) ) {
				$query[ sanitize_key( $k ) ] = rawurlencode( sanitize_text_field( wp_unslash( $v ) ) );
			}
		}
		$list = '';
		if ( $parent ) {
			$up    = get_term( $parent );
			$list .= $this->option( add_query_arg( $query, (string) get_term_link( $up ) ), sprintf( /* translators: %s: plural name. */ __( 'All %s', 'palltheme-core' ), $up->name ), $current === $parent );
		}
		foreach ( $terms as $term ) {
			$list .= $this->option( add_query_arg( $query, (string) get_term_link( $term ) ), $term->name, $current === $term->term_id, (int) $term->count );
		}
		return $this->group( 'cat', __( 'Category', 'palltheme-core' ), '<ul class="pt-filter__list">' . $list . '</ul>' );
	}

	/**
	 * Single-select taxonomy filter via query var (e.g. product_brand).
	 */
	private function taxonomy_links( string $taxonomy, string $title ): string {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 60 ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
		$base   = $this->base_url();
		$list   = '';
		foreach ( $terms as $term ) {
			$on    = $active === $term->slug;
			$url   = $on ? remove_query_arg( $taxonomy, $base ) : add_query_arg( $taxonomy, $term->slug, $base );
			$list .= $this->option( $url, $term->name, $on, (int) $term->count );
		}
		return $this->group( $taxonomy, $title, '<ul class="pt-filter__list">' . $list . '</ul>' );
	}

	/**
	 * Attribute filter (multi-select, OR within the attribute).
	 *
	 * @param object $attr Attribute taxonomy row.
	 */
	private function attribute( $attr ): string {
		$taxonomy = wc_attribute_taxonomy_name( $attr->attribute_name );
		$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 80 ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$param = 'filter_' . $attr->attribute_name;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active = isset( $_GET[ $param ] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) ) ) : array();
		$base   = $this->base_url();
		$list   = '';
		foreach ( $terms as $term ) {
			$on  = in_array( $term->slug, $active, true );
			$new = $on ? array_diff( $active, array( $term->slug ) ) : array_merge( $active, array( $term->slug ) );
			$url = $new
				? add_query_arg( array( $param => implode( ',', $new ), 'query_type_' . $attr->attribute_name => 'or' ), $base )
				: remove_query_arg( array( $param, 'query_type_' . $attr->attribute_name ), $base );
			$list .= $this->option( $url, $term->name, $on, (int) $term->count );
		}
		return $this->group( $attr->attribute_name, $attr->attribute_label, '<ul class="pt-filter__list">' . $list . '</ul>', (bool) $active || count( $terms ) <= 8 );
	}

	/**
	 * Price range form (GET, keeps other filters as hidden fields).
	 */
	private function price(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$min    = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : '';
		$max    = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : '';
		$hidden = '';
		foreach ( $_GET as $k => $v ) {
			$k = sanitize_key( $k );
			if ( in_array( $k, array( 'min_price', 'max_price', 'paged' ), true ) || is_array( $v ) ) {
				continue;
			}
			$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $v ) ) ) . '">';
		}
		// phpcs:enable
		$action = is_product_taxonomy() ? get_term_link( get_queried_object() ) : wc_get_page_permalink( 'shop' );
		$cur    = get_woocommerce_currency_symbol();
		$form   = '<form method="get" action="' . esc_url( is_wp_error( $action ) ? wc_get_page_permalink( 'shop' ) : $action ) . '"><div class="pt-filter__price">'
			. '<label class="screen-reader-text" for="pt-min-price">' . esc_html__( 'Minimum price', 'palltheme-core' ) . '</label><input id="pt-min-price" type="number" inputmode="decimal" min="0" step="any" name="min_price" value="' . esc_attr( (string) $min ) . '" placeholder="' . esc_attr( $cur . ' ' . __( 'Min', 'palltheme-core' ) ) . '">'
			. '<label class="screen-reader-text" for="pt-max-price">' . esc_html__( 'Maximum price', 'palltheme-core' ) . '</label><input id="pt-max-price" type="number" inputmode="decimal" min="0" step="any" name="max_price" value="' . esc_attr( (string) $max ) . '" placeholder="' . esc_attr( $cur . ' ' . __( 'Max', 'palltheme-core' ) ) . '">'
			. '<button type="submit" class="pt-btn pt-btn--primary">' . esc_html__( 'Go', 'palltheme-core' ) . '</button></div>' . $hidden . '</form>';
		return $this->group( 'price', __( 'Price', 'palltheme-core' ), $form );
	}

	/**
	 * In-stock toggle.
	 */
	private function availability(): string {
		$base = $this->base_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$on   = isset( $_GET['stock_status'] ) && 'instock' === $_GET['stock_status'];
		$url  = $on ? remove_query_arg( 'stock_status', $base ) : add_query_arg( 'stock_status', 'instock', $base );
		return $this->group( 'stock', __( 'Availability', 'palltheme-core' ), '<ul class="pt-filter__list">' . $this->option( $url, __( 'In stock only', 'palltheme-core' ), $on ) . '</ul>' );
	}

	/**
	 * Rating filter ("4 stars & up").
	 */
	private function rating(): string {
		$base = $this->base_url();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cur  = isset( $_GET['rating_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['rating_filter'] ) ) : '';
		$list = '';
		foreach ( array( 4 => '4,5', 3 => '3,4,5' ) as $stars => $value ) {
			$on = $cur === $value;
			/* translators: %d: stars. */
			$list .= $this->option( $on ? remove_query_arg( 'rating_filter', $base ) : add_query_arg( 'rating_filter', $value, $base ), sprintf( __( '%d stars & up', 'palltheme-core' ), $stars ), $on );
		}
		return $this->group( 'rating', __( 'Rating', 'palltheme-core' ), '<ul class="pt-filter__list">' . $list . '</ul>', false );
	}

	/**
	 * Admin form.
	 *
	 * @param array $instance Settings.
	 */
	public function form( $instance ): string {
		$o      = wp_parse_args( $instance, $this->defaults() );
		$labels = array(
			'categories'   => __( 'Categories', 'palltheme-core' ),
			'brands'       => __( 'Brands (WooCommerce Brands)', 'palltheme-core' ),
			'price'        => __( 'Price range', 'palltheme-core' ),
			'attributes'   => __( 'Product attributes (Processor, RAM, Storage…)', 'palltheme-core' ),
			'availability' => __( 'Availability', 'palltheme-core' ),
			'rating'       => __( 'Rating', 'palltheme-core' ),
		);
		foreach ( $labels as $key => $label ) {
			printf(
				'<p><label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label></p>',
				esc_attr( $this->get_field_name( $key ) ),
				checked( (bool) $o[ $key ], true, false ),
				esc_html( $label )
			);
		}
		printf(
			'<p><label>%1$s<br><input class="widefat" type="text" name="%2$s" value="%3$s"></label><small>%4$s</small></p>',
			esc_html__( 'Hide these attributes (slugs, comma separated)', 'palltheme-core' ),
			esc_attr( $this->get_field_name( 'exclude' ) ),
			esc_attr( (string) $o['exclude'] ),
			esc_html__( 'Attributes are managed in Products → Attributes.', 'palltheme-core' )
		);
		return 'noform';
	}

	/**
	 * Save.
	 *
	 * @param array $new New.
	 * @param array $old Old.
	 */
	public function update( $new, $old ): array {
		$out = array();
		foreach ( array( 'categories', 'brands', 'price', 'availability', 'rating', 'attributes' ) as $k ) {
			$out[ $k ] = empty( $new[ $k ] ) ? 0 : 1;
		}
		$out['exclude'] = sanitize_text_field( $new['exclude'] ?? '' );
		return $out;
	}
}

add_action( 'widgets_init', static fn() => register_widget( 'Pallcore_Filters_Widget' ) );

/**
 * Apply the in-stock filter to the main shop query.
 *
 * @param WP_Query $q Query.
 */
function pallcore_stock_filter( WP_Query $q ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['stock_status'] ) && 'instock' === $_GET['stock_status'] ) {
		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = array(
			'key'   => '_stock_status',
			'value' => 'instock',
		);
		$q->set( 'meta_query', $meta );
	}
}
add_action( 'woocommerce_product_query', 'pallcore_stock_filter' );
