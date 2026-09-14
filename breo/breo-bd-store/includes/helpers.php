<?php
/**
 * Settings, product/media lookups, icons and small shared helpers.
 */
defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------------
 * Settings (Tools → Breo BD Setup). Policy numbers default to the group's
 * existing practice on aun-projector.com.bd — the owner confirms them.
 * --------------------------------------------------------------------- */

function breo_bd_defaults() {
	return array(
		'company'               => '',
		'address'               => '',
		'phone'                 => '',
		'whatsapp'              => '',
		'email'                 => '',
		'hours'                 => 'Saturday to Thursday, except public holidays',
		'facebook'              => '',
		'instagram'             => '',
		'youtube'               => '',
		'tiktok'                => '',
		'warranty_months'       => '12',
		'return_unopened_hours' => '24',
		'return_faulty_days'    => '3',
		'refund_days'           => '7',
		'dhaka_days'            => '2',
		'outside_days'          => '4',
		'dhaka_fee'             => '',
		'outside_fee'           => '',
		'cutoff'                => '6:00 PM',
		'payments'              => 'Cash on Delivery',
	);
}

function breo_bd_opt( $key ) {
	$o = wp_parse_args( (array) get_option( 'breo_bd_settings', array() ), breo_bd_defaults() );
	return isset( $o[ $key ] ) ? trim( (string) $o[ $key ] ) : '';
}

function breo_bd_company() {
	$c = breo_bd_opt( 'company' );
	return $c ? $c : 'Breo Bangladesh';
}

function breo_bd_warranty_period() {
	$m = (int) breo_bd_opt( 'warranty_months' );
	if ( $m <= 0 ) {
		return '';
	}
	if ( 0 === $m % 12 ) {
		$y = $m / 12;
		return $y . '-year';
	}
	return $m . '-month';
}

function breo_bd_trust_items() {
	$items = array( array( 'shield', '100% genuine, officially imported' ) );
	if ( breo_bd_warranty_period() ) {
		$items[] = array( 'badge', breo_bd_warranty_period() . ' official warranty' );
	}
	$items[] = array( 'truck', 'Delivery across Bangladesh' );
	if ( false !== stripos( breo_bd_opt( 'payments' ), 'cash' ) ) {
		$items[] = array( 'cash', 'Cash on Delivery available' );
	}
	return $items;
}

function breo_bd_categories() {
	return array(
		'neck-shoulder-massagers' => array( 'Neck & Shoulder Massagers', 'Neck & Shoulders', 'Stiff neck, desk shoulders' ),
		'eye-massagers'           => array( 'Eye Massagers', 'Tired Eyes', 'Screen strain, dry eyes' ),
		'back-waist-massagers'    => array( 'Back & Waist Massagers', 'Back & Waist', 'Lower back after long sitting' ),
		'massage-guns'            => array( 'Massage Guns', 'Muscle Recovery', 'Post-workout knots, sore legs' ),
	);
}

function breo_bd_data() {
	static $data = null;
	if ( null === $data ) {
		$data = require BREO_BD_DIR . 'includes/products-data.php';
	}
	return $data;
}

/** Content for a WooCommerce product, from the plugin's data file (by SKU). */
function breo_bd_product_data( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return null;
	}
	$key  = $product->get_meta( '_breo_sku_key' );
	$key  = $key ? $key : $product->get_sku();
	$data = breo_bd_data();
	return isset( $data[ $key ] ) ? $data[ $key ] : null;
}

/** Published Breo products in display order: sku => WC_Product. */
function breo_bd_products() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		return $out;
	}
	foreach ( array_keys( breo_bd_data() ) as $sku ) {
		$id = wc_get_product_id_by_sku( $sku );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$out[ $sku ] = wc_get_product( $id );
		}
	}
	return $out;
}

/* ------------------------------------------------------------------------
 * Media (attachment ids saved on the product by the importer)
 * --------------------------------------------------------------------- */

function breo_bd_media_id( $product, $key ) {
	if ( ! $product instanceof WC_Product || ! $key ) {
		return 0;
	}
	$m = $product->get_meta( '_breo_media' );
	return ( is_array( $m ) && ! empty( $m[ $key ] ) ) ? (int) $m[ $key ] : 0;
}

/**
 * <img> or <video> for a product media key.
 * $args: size, class, loading (lazy|eager), sizes, focus.
 */
function breo_bd_media( $product, $key, $args = array() ) {
	$args = wp_parse_args( $args, array( 'size' => 'large', 'class' => '', 'loading' => 'lazy', 'sizes' => '', 'focus' => '' ) );
	$id   = breo_bd_media_id( $product, $key );
	if ( ! $id ) {
		return '';
	}
	$d     = breo_bd_product_data( $product );
	$spec  = ( $d && isset( $d['media'][ $key ] ) ) ? $d['media'][ $key ] : array();
	$focus = $args['focus'] ? $args['focus'] : ( isset( $spec['focus'] ) ? $spec['focus'] : '' );

	if ( isset( $spec['type'] ) && 'video' === $spec['type'] ) {
		return '<video class="breo-video ' . esc_attr( $args['class'] ) . '" src="' . esc_url( wp_get_attachment_url( $id ) ) . '" muted loop playsinline preload="none" data-breo-video aria-hidden="true"></video>';
	}
	$attr = array(
		'class'    => trim( 'breo-img ' . $args['class'] ),
		'loading'  => $args['loading'],
		'decoding' => 'async',
		'alt'      => isset( $spec['alt'] ) ? $spec['alt'] : ( $d ? $d['name'] : '' ),
	);
	if ( $focus ) {
		$attr['style'] = 'object-position:' . $focus;
	}
	if ( $args['sizes'] ) {
		$attr['sizes'] = $args['sizes'];
	}
	if ( 'eager' === $args['loading'] ) {
		$attr['fetchpriority'] = 'high';
	}
	return wp_get_attachment_image( $id, $args['size'], false, $attr );
}

/* ------------------------------------------------------------------------
 * URLs
 * --------------------------------------------------------------------- */

function breo_bd_page_url( $slug ) {
	$p = get_page_by_path( $slug );
	return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : home_url( '/' . $slug . '/' );
}

function breo_bd_shop_url() {
	return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
}

function breo_bd_whatsapp_url( $text = '' ) {
	$num = preg_replace( '/\D+/', '', breo_bd_opt( 'whatsapp' ) );
	if ( ! $num ) {
		return '';
	}
	return 'https://wa.me/' . $num . ( $text ? '?text=' . rawurlencode( $text ) : '' );
}

function breo_bd_tel( $phone ) {
	return 'tel:' . preg_replace( '/[^\d+]/', '', $phone );
}

function breo_bd_price_html( $p ) {
	$h = $p ? $p->get_price_html() : '';
	return $h ? $h : '<span class="breo-price-na">Price coming soon</span>';
}

/** Current price as plain text ("৳11,990"), for buttons and labels. */
function breo_bd_plain_price( $p ) {
	if ( ! $p || '' === $p->get_price() ) {
		return '';
	}
	return html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $p ) ) ), ENT_QUOTES, 'UTF-8' );
}

/* ------------------------------------------------------------------------
 * Icons: inline SVG, stroke = currentColor
 * --------------------------------------------------------------------- */

function breo_bd_icon( $name, $size = 22 ) {
	$p = array(
		'shield'  => '<path d="M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'truck'   => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
		'badge'   => '<circle cx="12" cy="9" r="5"/><path d="M9 13.5L8 21l4-2 4 2-1-7.5"/>',
		'cash'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/>',
		'chat'    => '<path d="M4 5h16v11H9l-5 4z"/>',
		'check'   => '<path d="M5 12l5 5L19 7"/>',
		'spark'   => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M6 18l2.5-2.5M15.5 8.5L18 6"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'chev'    => '<path d="M6 9l6 6 6-6"/>',
		'chevr'   => '<path d="M9 6l6 6-6 6"/>',
		'chevl'   => '<path d="M15 6l-6 6 6 6"/>',
		'cart'    => '<path d="M3 4h2l2.2 11h10.6L20 7H6.2"/><circle cx="9" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'pin'     => '<path d="M12 21s-7-6.5-7-12a7 7 0 0114 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'box'     => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
		'return'  => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>',
		'play'    => '<path d="M8 5v14l11-7z"/>',
		'pause'   => '<path d="M8 5v14M16 5v14"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
	);
	return '<svg class="breo-ico" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $p[ $name ] ) ? $p[ $name ] : '' ) . '</svg>';
}

/** Brand glyphs (filled). */
function breo_bd_social_icon( $name ) {
	$p = array(
		'whatsapp'  => '<path d="M16 3a13 13 0 00-11.2 19.6L3 29l6.6-1.7A13 13 0 1016 3zm0 23.7c-2 0-3.9-.5-5.6-1.5l-.4-.2-3.9 1 1-3.8-.3-.4A10.7 10.7 0 1116 26.7zm5.9-8c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.8 8.8 0 01-4.4-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 00-.9.4 3.7 3.7 0 00-1.1 2.7 6.4 6.4 0 001.3 3.4 14.7 14.7 0 005.7 5c2.1.9 2.9 1 4 .8a3.4 3.4 0 002.2-1.6 2.8 2.8 0 00.2-1.6c-.1-.1-.3-.2-.6-.4z"/>',
		'facebook'  => '<path d="M18.5 29V17.4h3.9l.6-4.5h-4.5V10c0-1.3.4-2.2 2.2-2.2H23v-4a32 32 0 00-3.5-.2c-3.5 0-5.9 2.1-5.9 6v3.3H9.7v4.5h3.9V29z"/>',
		'instagram' => '<path d="M16 5.9c3.3 0 3.7 0 5 .1 3.3.1 4.9 1.7 5 5 .1 1.3.1 1.7.1 5s0 3.7-.1 5c-.1 3.3-1.7 4.9-5 5-1.3.1-1.7.1-5 .1s-3.7 0-5-.1c-3.3-.1-4.9-1.7-5-5-.1-1.3-.1-1.7-.1-5s0-3.7.1-5c.1-3.3 1.7-4.9 5-5 1.3-.1 1.7-.1 5-.1zM16 3.6c-3.4 0-3.8 0-5.1.1-4.5.2-7 2.7-7.2 7.2-.1 1.3-.1 1.7-.1 5.1s0 3.8.1 5.1c.2 4.5 2.7 7 7.2 7.2 1.3.1 1.7.1 5.1.1s3.8 0 5.1-.1c4.5-.2 7-2.7 7.2-7.2.1-1.3.1-1.7.1-5.1s0-3.8-.1-5.1c-.2-4.5-2.7-7-7.2-7.2-1.3-.1-1.7-.1-5.1-.1zm0 6a6.4 6.4 0 100 12.8 6.4 6.4 0 000-12.8zm0 10.5a4.1 4.1 0 110-8.2 4.1 4.1 0 010 8.2zm6.6-12.2a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>',
		'youtube'   => '<path d="M29.4 9.5a3.5 3.5 0 00-2.5-2.5C24.7 6.4 16 6.4 16 6.4s-8.7 0-10.9.6a3.5 3.5 0 00-2.5 2.5A36 36 0 002 16a36 36 0 00.6 6.5 3.5 3.5 0 002.5 2.4c2.2.6 10.9.6 10.9.6s8.7 0 10.9-.6a3.5 3.5 0 002.5-2.4A36 36 0 0030 16a36 36 0 00-.6-6.5zM13.2 20V12l7.3 4z"/>',
		'tiktok'    => '<path d="M22.5 4h-4.3v16.3a3.6 3.6 0 11-3.6-3.6c.4 0 .7 0 1 .1v-4.4a8 8 0 108 7.9v-8a10 10 0 005.7 1.8V9.8A5.8 5.8 0 0122.5 4z"/>',
	);
	return '<svg class="breo-ico breo-ico--fill" viewBox="0 0 32 32" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">' . ( isset( $p[ $name ] ) ? $p[ $name ] : '' ) . '</svg>';
}

function breo_bd_logo( $class = '' ) {
	static $svg = null;
	if ( null === $svg ) {
		$svg = (string) file_get_contents( BREO_BD_DIR . 'assets/breo-logo.svg' );
	}
	return '<span class="breo-logo ' . esc_attr( $class ) . '">' . $svg . '</span>';
}

/* ------------------------------------------------------------------------
 * Shortcodes used inside the policy pages
 * [breo_info key="phone"]  ·  [breo_if key="dhaka_fee"]…[/breo_if]
 * --------------------------------------------------------------------- */

add_shortcode( 'breo_info', function ( $atts ) {
	$atts = shortcode_atts( array( 'key' => '', 'fallback' => '' ), $atts );
	switch ( $atts['key'] ) {
		case 'company':
			return esc_html( breo_bd_company() );
		case 'warranty':
			return esc_html( breo_bd_warranty_period() );
		case 'site':
			return esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) );
		case 'updated':
			return esc_html( date_i18n( get_option( 'date_format' ), (int) get_option( 'breo_bd_pages_built', time() ) ) );
	}
	$v = breo_bd_opt( $atts['key'] );
	return esc_html( '' !== $v ? $v : $atts['fallback'] );
} );

add_shortcode( 'breo_if', function ( $atts, $content = '' ) {
	$atts = shortcode_atts( array( 'key' => '', 'not' => '' ), $atts );
	$has  = '' !== breo_bd_opt( $atts['key'] );
	if ( $atts['not'] ) {
		$has = ! $has;
	}
	return $has ? do_shortcode( $content ) : '';
} );
