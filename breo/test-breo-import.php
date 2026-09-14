<?php
// Bench harness (wp eval-file): import all products + media, build pages, set front page.
require_once WP_PLUGIN_DIR . '/breo-bd-store/includes/admin.php';

update_option( 'breo_bd_settings', array(
	'whatsapp' => '8801700000000',
	'phone'    => '+880 1700 000000',
	'email'    => 'hello@breo.bd',
	'address'  => 'Test address, Dhaka',
) + breo_bd_defaults() );
update_option( 'woocommerce_currency', 'BDT' );
update_option( 'woocommerce_price_num_decimals', '0' );
update_option( 'woocommerce_coming_soon', 'no' );

$prices = array(
	'N990000631' => array( '14500', '11990' ),
	'N910200212' => array( '6500', '5490' ),
	'N990000981' => array( '12500', '' ),
	'N990000360' => array( '9500', '7990' ),
);
$cats = breo_bd_ensure_categories();
foreach ( breo_bd_data() as $sku => $d ) {
	$t   = microtime( true );
	$pid = breo_bd_save_product( $sku, $d, $prices[ $sku ][0], $prices[ $sku ][1], $cats );
	$p   = wc_get_product( $pid );
	$map = $p->get_meta( '_breo_media' );
	$map = is_array( $map ) ? $map : array();
	$err = array();
	foreach ( $d['media'] as $k => $spec ) {
		if ( ! empty( $map[ $k ] ) && get_post( $map[ $k ] ) ) {
			continue;
		}
		$r = breo_bd_import_media_item( $pid, $sku, $k, $spec );
		if ( is_wp_error( $r ) ) {
			$err[] = "$k: " . $r->get_error_message();
		} else {
			$map[ $k ] = $r;
		}
	}
	$p->update_meta_data( '_breo_media', $map );
	$p->save();
	breo_bd_apply_media( $pid, $d );
	$p = wc_get_product( $pid );
	printf( "%s #%d media %d/%d img=%d gallery=%d %.0fs %s\n", $sku, $pid, count( $map ), count( $d['media'] ), $p->get_image_id(), count( $p->get_gallery_image_ids() ), microtime( true ) - $t, $err ? 'ERR ' . implode( ' | ', $err ) : '' );
}

list( $ids, $msgs ) = breo_bd_build_pages();
foreach ( $msgs as $m ) {
	echo 'page: ' . wp_strip_all_tags( $m[1] ) . "\n";
}
update_option( 'wp_page_for_privacy_policy', (int) $ids['privacy-policy'] );
update_option( 'woocommerce_terms_page_id', (int) $ids['terms-conditions'] );
echo 'home: ' . breo_bd_ensure_home_page() . "\n";

// Crop sanity: dimensions of the cropped attachments.
foreach ( array( 'N990000631' => 'dims', 'N910200212' => 'airbag', 'N990000981' => 'rug' ) as $sku => $k ) {
	$p  = wc_get_product( wc_get_product_id_by_sku( $sku ) );
	$id = breo_bd_media_id( $p, $k );
	$m  = wp_get_attachment_metadata( $id );
	echo "crop $sku/$k -> #$id " . ( $m ? $m['width'] . 'x' . $m['height'] : 'no meta' ) . "\n";
}
