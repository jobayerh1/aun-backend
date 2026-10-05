<?php
/**
 * Bench test for breo-bd-store tracking signals (includes/tracking.php and the
 * hook calls in the product / shop renders). Makes its own throwaway category
 * and products and deletes them at the end. Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-tracking.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}

// ---------- breo_bd_capture_hook ----------
t( '' === breo_bd_capture_hook( 'breo_test_nobody_listens' ), 'hook with no listeners returns empty' );
add_action( 'breo_test_prints', function () { echo '<i>x</i>'; } );
t( '<i>x</i>' === breo_bd_capture_hook( 'breo_test_prints' ), 'listener output is captured, not echoed' );

// ---------- the redirect option is only "yes" while Facebook's handler runs ----------
$seen = array();
$spy  = function () use ( &$seen ) {
	$seen[] = get_option( 'woocommerce_cart_redirect_after_add', 'no' );
};
add_action( 'woocommerce_add_to_cart', $spy, 40 );
update_option( 'woocommerce_cart_redirect_after_add', 'no' );

do_action( 'woocommerce_add_to_cart', 'k', 1, 1, 0, array(), array() );
t( array( 'yes' ) === $seen, 'form / Buy-now add: option reads yes at priority 40' );
t( 'no' === get_option( 'woocommerce_cart_redirect_after_add' ), 'option is back to no straight after' );

$seen = array();
add_filter( 'wp_doing_ajax', '__return_true' );
do_action( 'woocommerce_add_to_cart', 'k', 1, 1, 0, array(), array() );
remove_filter( 'wp_doing_ajax', '__return_true' );
t( array( 'no' ) === $seen, 'AJAX add: option left alone (the plugin tracks those itself)' );
remove_action( 'woocommerce_add_to_cart', $spy, 40 );
t( 'no' === get_option( 'woocommerce_cart_redirect_after_add' ), 'shopper-facing setting never changed' );

// ---------- shop: the hook is fired, WooCommerce's own page links are not ----------
$term = wp_insert_term( 'Breo test cat ' . wp_generate_password( 6, false ), 'product_cat' );
t( ! is_wp_error( $term ), 'fixture: category created' );
$tid = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
$ids = array();
for ( $i = 0; $i < 3; $i++ ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Breo tracking test ' . $i );
	$p->set_status( 'publish' );
	$p->set_regular_price( '100' );
	$p->set_category_ids( array( $tid ) );
	$ids[] = $p->save();
}
t( 3 === count( array_filter( $ids ) ), 'fixture: 3 products created' );

$fired = 0;
$count = function () use ( &$fired ) { $fired++; };
add_action( 'woocommerce_after_shop_loop', $count, 5 );

$slug = get_term( $tid )->slug;
$q    = new WP_Query( array( 'post_type' => 'product', 'product_cat' => $slug, 'posts_per_page' => 1, 'wc_query' => 'product_query' ) );
$GLOBALS['wp_query']     = $q; // phpcs:ignore
$GLOBALS['wp_the_query'] = $q; // phpcs:ignore
$q->queried_object       = get_term( $tid );
$q->queried_object_id    = $tid;
t( 3 === (int) $q->max_num_pages, 'fixture: category spans 3 pages' );

// control: with WooCommerce's pagination still hooked, the hook prints its own page links
ob_start();
do_action( 'woocommerce_after_shop_loop' );
$control = ob_get_clean();
t( false !== strpos( $control, 'woocommerce-pagination' ), 'control: WooCommerce pagination rides on this hook' );

$fired = 0;
$html  = breo_bd_render_shop();
t( 1 === $fired, 'shop render fires woocommerce_after_shop_loop once' );
t( false === strpos( $html, 'woocommerce-pagination' ), 'no second (WooCommerce) pagination in the shop page' );
t( 1 === substr_count( $html, 'class="navigation pagination"' ), 'our own pagination printed once' );
remove_action( 'woocommerce_after_shop_loop', $count, 5 );
add_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 ); // put core back for anything after us

// ---------- clean up ----------
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
if ( $tid ) {
	wp_delete_term( $tid, 'product_cat' );
}
wp_reset_query();
t( ! get_post( $ids[0] ) && ! term_exists( $tid, 'product_cat' ), 'fixtures removed' );

echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
