<?php
// Bench helper (wp eval-file): fetch any still-missing No.7 media, warming up
// a connection to Breo's CDN first (its first connection often fails here).
require_once WP_PLUGIN_DIR . '/breo-bd-store/includes/admin.php';
$sku = 'N990000360';
$d   = breo_bd_data()[ $sku ];
$pid = wc_get_product_id_by_sku( $sku );
$p   = wc_get_product( $pid );
$map = $p->get_meta( '_breo_media' );
$map = is_array( $map ) ? $map : array();
foreach ( $d['media'] as $k => $spec ) {
	if ( ! empty( $map[ $k ] ) ) {
		continue;
	}
	$w = download_url( 'https://cchimg.breo.com/images/jmq7/12_6.png', 60 );
	echo 'warm-up: ' . ( is_wp_error( $w ) ? $w->get_error_message() : 'ok' ) . "\n";
	if ( ! is_wp_error( $w ) ) {
		@unlink( $w ); // phpcs:ignore
	}
	$r = breo_bd_import_media_item( $pid, $sku, $k, $spec );
	echo "$k: " . ( is_wp_error( $r ) ? $r->get_error_message() : "#$r" ) . "\n";
	if ( ! is_wp_error( $r ) ) {
		$map[ $k ] = $r;
	}
}
$p->update_meta_data( '_breo_media', $map );
$p->save();
breo_bd_apply_media( $pid, $d );
echo 'No.7 media: ' . count( $map ) . '/' . count( $d['media'] ) . "\n";
