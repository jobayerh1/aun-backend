<?php
/**
 * Builds a REAL variable product shaped like the AUN A005 (Grey/White with
 * variation SKUs, none on the parent) and proves the catalogue and the
 * device→product matcher both see the variation SKUs.
 */
if ( ! class_exists( 'WC_Product_Variable' ) ) { echo "WooCommerce not active\n"; return; }

// Clean any previous run.
foreach ( get_posts( array( 'post_type' => array('product','product_variation'), 'numberposts' => -1, 'post_status' => 'any', 's' => 'BENCH A005' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}
$term = term_exists( 'projector-price', 'product_cat' );
if ( ! $term ) { $term = wp_insert_term( 'Projector Price', 'product_cat', array( 'slug' => 'projector-price' ) ); }
$term_id = (int) ( $term['term_id'] ?? 0 );

// Parent: NO sku, exactly like the real A005.
$parent = new WC_Product_Variable();
$parent->set_name( 'BENCH A005 Short Throw' );
$parent->set_status( 'publish' );
$parent->set_category_ids( array( $term_id ) );
$parent_id = $parent->save();
wp_set_object_terms( $parent_id, array( $term_id ), 'product_cat' );
update_post_meta( $parent_id, '_aun_throw_ratio', '1.10' );

foreach ( array( 'Grey' => 'APB-A005-GRY', 'White' => 'APB-A005-WHT' ) as $colour => $sku ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $parent_id );
	$v->set_sku( $sku );
	$v->set_status( 'publish' );
	$v->save();
}
// Also a same-prefix sibling, the one name matching confuses it with.
$pro = new WC_Product_Simple();
$pro->set_name( 'BENCH A005 Pro Short Throw' );
$pro->set_sku( 'APB-A005-Pro-WHT' );
$pro->set_status( 'publish' );
$pro->set_category_ids( array( $term_id ) );
$pro_id = $pro->save();
wp_set_object_terms( $pro_id, array( $term_id ), 'product_cat' );

AUN_App_Projectors::flush();
$cat = AUN_App_Projectors::catalogue( true );

$row = null; $prorow = null;
foreach ( $cat as $c ) {
	if ( $c['id'] === $parent_id ) { $row = $c; }
	if ( $c['id'] === $pro_id )    { $prorow = $c; }
}

$pass = 0; $fail = 0;
$check = function ( $label, $got, $want ) use ( &$pass, &$fail ) {
	$ok = ( $got === $want );
	$ok ? $pass++ : $fail++;
	printf( "%-5s %-52s %s\n", $ok ? 'PASS' : 'FAIL', $label,
		$ok ? '' : 'got ' . var_export( $got, true ) . ' want ' . var_export( $want, true ) );
};

echo "=== the variable parent as the planner sees it ===\n";
$check( "parent's own sku is empty (correct WooCommerce)", $row['sku'], '' );
$check( 'both variation SKUs collected',  $row['skus'], array( 'APB-A005-GRY', 'APB-A005-WHT' ) );
$check( 'simple sibling still has its own sku', $prorow['skus'], array( 'APB-A005-Pro-WHT' ) );
$check( 'no longer counted as unmapped', empty( $row['skus'] ) && (int) $row['erp_id'] < 1, false );

echo "\n=== device -> product, by the SKU the ERP recorded ===\n";
// Simulate what product_for_device() does with a serial whose ERP SKU is the
// GREY variation — the case that used to fall through to name matching.
$resolved = null;
foreach ( $cat as $p ) {
	foreach ( (array) ( $p['skus'] ?? array() ) as $s ) {
		if ( 0 === strcasecmp( $s, 'APB-A005-GRY' ) ) { $resolved = (int) $p['id']; break 2; }
	}
}
$check( 'APB-A005-GRY resolves to the A005 parent', $resolved, $parent_id );

$resolved2 = null;
foreach ( $cat as $p ) {
	foreach ( (array) ( $p['skus'] ?? array() ) as $s ) {
		if ( 0 === strcasecmp( $s, 'APB-A005-Pro-WHT' ) ) { $resolved2 = (int) $p['id']; break 2; }
	}
}
$check( 'and the Pro SKU still resolves to the Pro', $resolved2, $pro_id );
$check( 'the two are different products', $resolved === $resolved2, false );

printf( "\n%d passed, %d failed\n", $pass, $fail );

// Tidy up.
foreach ( array( $parent_id, $pro_id ) as $id ) {
	foreach ( get_children( array( 'post_parent' => $id, 'post_type' => 'product_variation' ) ) as $ch ) { wp_delete_post( $ch->ID, true ); }
	wp_delete_post( $id, true );
}
AUN_App_Projectors::flush();
