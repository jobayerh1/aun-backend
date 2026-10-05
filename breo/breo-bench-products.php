<?php
/**
 * Bench: recreate the four Breo products (text only, no media) for tests, or
 * remove them again. The shared bench is used by other projects, so leave it
 * as you found it.
 *   eval-file Breo/breo-bench-products.php          -> create
 *   eval-file Breo/breo-bench-products.php remove   -> delete what this created
 */
if ( ! function_exists( 'breo_bd_save_product' ) ) {
	require_once BREO_BD_DIR . 'includes/admin.php';
}
$mode = isset( $args[0] ) ? $args[0] : 'create';

if ( 'remove' === $mode ) {
	foreach ( (array) get_option( 'breo_bench_temp_products', array() ) as $id ) {
		wp_delete_post( $id, true );
		echo "deleted product $id\n";
	}
	delete_option( 'breo_bench_temp_products' );
	return;
}

$prices  = array( 'N990000631' => '8290', 'N910200212' => '4750', 'N990000981' => '9000', 'N990000360' => '7990' );
$created = array();
$cats    = breo_bd_ensure_categories();
foreach ( breo_bd_data() as $sku => $d ) {
	if ( wc_get_product_id_by_sku( $sku ) ) {
		echo "$sku exists\n";
		continue;
	}
	$id        = breo_bd_save_product( $sku, $d, isset( $prices[ $sku ] ) ? $prices[ $sku ] : '5000', '', $cats );
	$created[] = $id;
	echo "$sku -> product $id\n";
}
update_option( 'breo_bench_temp_products', array_merge( (array) get_option( 'breo_bench_temp_products', array() ), $created ), false );
