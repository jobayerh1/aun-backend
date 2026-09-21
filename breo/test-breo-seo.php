<?php
/**
 * Bench test for breo-bd-store SEO (includes/seo.php). Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-seo.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}

t( defined( 'RANK_MATH_VERSION' ), 'RankMath active' );
delete_option( 'breo_bd_seo_ver' );
$map = breo_bd_seo_map();

// clean slate
$n6 = wc_get_product_id_by_sku( 'N990000631' );
foreach ( array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', '_breo_seo' ) as $k ) {
	delete_post_meta( $n6, $k );
}
t( breo_bd_seo_sync(), 'sync reports products found' );
t( get_post_meta( $n6, 'rank_math_title', true ) === $map['products']['N990000631']['title'], 'N6 title written' );
t( get_post_meta( $n6, 'rank_math_focus_keyword', true ) === $map['products']['N990000631']['focus'], 'N6 focus keyword written' );

$cat = get_term_by( 'slug', 'massage-guns', 'product_cat' );
t( get_term_meta( $cat->term_id, 'rank_math_title', true ) === $map['cats']['massage-guns']['title'], 'category title written (term meta)' );
$front = (int) get_option( 'page_on_front' );
t( ! $front || get_post_meta( $front, 'rank_math_title', true ) === $map['front']['title'], 'front page title written' );
t( in_array( 'noindex', (array) get_post_meta( wc_get_page_id( 'cart' ), 'rank_math_robots', true ), true ), 'cart noindex in RankMath' );

// owner edit wins over a later sync
update_post_meta( $n6, 'rank_math_title', 'Owner wrote this' );
breo_bd_seo_sync();
t( 'Owner wrote this' === get_post_meta( $n6, 'rank_math_title', true ), 'owner edit kept after re-sync' );
t( get_post_meta( $n6, 'rank_math_description', true ) === $map['products']['N990000631']['desc'], 'untouched field still ours' );
// our own value is refreshed when the map changes
update_post_meta( $n6, 'rank_math_description', $map['products']['N990000631']['desc'] ); // unchanged
delete_post_meta( $n6, 'rank_math_title' );
breo_bd_seo_sync();
t( get_post_meta( $n6, 'rank_math_title', true ) === $map['products']['N990000631']['title'], 'emptied field refilled' );

// schema filter on a product page
$GLOBALS['wp_the_query']->query( array( 'p' => $n6, 'post_type' => 'product' ) );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$data = apply_filters( 'rank_math/json_ld', array(
	'richSnippet' => array( '@type' => 'Product', 'name' => 'Breo N6 Mini Neck Massager Price in Bangladesh', 'offers' => array( '@type' => 'Offer', 'price' => '8290', 'seller' => array( '@type' => 'Organization', 'logo' => '' ) ) ),
), null );
t( 'Breo N6 Mini Neck & Shoulder Massager' === $data['richSnippet']['name'], 'Product schema name = real product name' );
t( isset( $data['richSnippet']['brand']['name'] ) && 'Breo' === $data['richSnippet']['brand']['name'], 'Product schema brand = Breo' );
t( ! isset( $data['richSnippet']['offers']['seller']['logo'] ), 'empty seller logo removed' );

// category guide + auto FAQ
$html = breo_bd_category_guide_html( $cat );
t( false !== strpos( $html, 'How to choose a massage gun' ), 'guide heading rendered' );
t( false !== strpos( $html, 'What is the price of a massage gun in Bangladesh?' ), 'price FAQ uses the keyword noun' );
t( false === strpos( $html, '%warranty%' ), 'warranty placeholder replaced' );
$shop = breo_bd_category_guide_html( null );
t( substr_count( $shop, '/product-category/' ) >= 4, 'shop guide links to the 4 categories' );

// product FAQ answers the "price in bangladesh" query
$p   = wc_get_product( $n6 );
$faq = breo_bd_product_faq( breo_bd_product_data( $p ), $p );
t( 0 === strpos( $faq[0][0], 'What is the price of the Breo N6 mini in Bangladesh?' ), 'product FAQ leads with price question' );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
