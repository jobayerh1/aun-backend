<?php
/**
 * Reproduces the live report: the planner offering models we no longer sell.
 *
 * mu-aun-api-lean.php drops the Discontinued Products plugin on every
 * /wp-json/aun-app/ request, so on the app's own endpoint the taxonomy is not
 * registered — taxonomy_exists() returns false and is_discontinued() answered
 * false for everything.
 */
if ( ! function_exists( 'wc_get_products' ) ) { echo "WooCommerce not active\n"; return; }

$pass = 0; $fail = 0;
$check = function ( $label, $got, $want ) use ( &$pass, &$fail ) {
	$ok = ( $got === $want ); $ok ? $pass++ : $fail++;
	printf( "%-5s %s%s\n", $ok ? 'PASS' : 'FAIL', $label,
		$ok ? '' : '   got ' . var_export( $got, true ) . ' want ' . var_export( $want, true ) );
};

foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'any', 's' => 'BENCH DISC' ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}
$cat = term_exists( 'projector-price', 'product_cat' ) ?: wp_insert_term( 'Projector Price', 'product_cat', array( 'slug' => 'projector-price' ) );
$cat_id = (int) ( $cat['term_id'] ?? 0 );

// The plugin's taxonomy, registered exactly as it would be on the website.
register_taxonomy( 'product_discontinued', 'product', array( 'public' => false, 'hierarchical' => false ) );
if ( ! term_exists( 'dp-discontinued', 'product_discontinued' ) ) {
	wp_insert_term( 'Discontinued', 'product_discontinued', array( 'slug' => 'dp-discontinued' ) );
}

$live = new WC_Product_Simple();
$live->set_name( 'BENCH DISC Live Model' ); $live->set_sku( 'BENCH-LIVE' );
$live->set_status( 'publish' ); $live->set_category_ids( array( $cat_id ) );
$live_id = $live->save(); wp_set_object_terms( $live_id, array( $cat_id ), 'product_cat' );

$dead = new WC_Product_Simple();
$dead->set_name( 'BENCH DISC Retired Model' ); $dead->set_sku( 'BENCH-DEAD' );
$dead->set_status( 'publish' ); $dead->set_category_ids( array( $cat_id ) );
$dead_id = $dead->save(); wp_set_object_terms( $dead_id, array( $cat_id ), 'product_cat' );
// The owner marks it discontinued in wp-admin.
wp_set_object_terms( $dead_id, array( 'dp-discontinued' ), 'product_discontinued' );

echo "=== A. website request: the plugin IS loaded ===\n";
$check( 'taxonomy is registered here', taxonomy_exists( 'product_discontinued' ), true );
$check( 'retired model is discontinued', AUN_App_Projectors::is_discontinued( $dead_id ), true );
$check( 'live model is not', AUN_App_Projectors::is_discontinued( $live_id ), false );

echo "\n=== B. app request: mu-plugin has DROPPED the plugin ===\n";
// Exactly what the app endpoint sees — term rows still in the DB, taxonomy gone.
unregister_taxonomy( 'product_discontinued' );
$r = new ReflectionClass( 'AUN_App_Projectors' );
$prop = $r->getProperty( 'discontinued_ids' ); $prop->setAccessible( true ); $prop->setValue( null, null );

$check( 'taxonomy is NOT registered', taxonomy_exists( 'product_discontinued' ), false );
// The OLD implementation, for contrast.
$old_answer = taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $dead_id );
$check( 'OLD code says "not discontinued"  <-- the bug', $old_answer, false );
$check( 'NEW code still says discontinued  <-- the fix', AUN_App_Projectors::is_discontinued( $dead_id ), true );
$check( 'and the live model is still fine', AUN_App_Projectors::is_discontinued( $live_id ), false );

echo "\n=== C. the catalogue the app is actually served ===\n";
AUN_App_Projectors::flush();
$names = array();
foreach ( AUN_App_Projectors::catalogue( true ) as $p ) { $names[] = $p['name']; }
$check( 'retired model is NOT offered', in_array( 'BENCH DISC Retired Model', $names, true ), false );
$check( 'live model IS offered', in_array( 'BENCH DISC Live Model', $names, true ), true );

wp_delete_post( $live_id, true ); wp_delete_post( $dead_id, true );
AUN_App_Projectors::flush();
printf( "\n%d passed, %d failed\n", $pass, $fail );
