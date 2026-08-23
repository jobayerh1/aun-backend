<?php
if ( ! function_exists( 'wc_get_products' ) ) { echo "no WC\n"; return; }
foreach ( get_posts( array( 'post_type'=>'product','numberposts'=>-1,'post_status'=>'any','s'=>'BENCH PRICE' ) ) as $o ) { wp_delete_post($o->ID,true); }
$cat = term_exists( 'projector-price', 'product_cat' ) ?: wp_insert_term( 'Projector Price','product_cat',array('slug'=>'projector-price') );
$cid = (int)($cat['term_id'] ?? 0);
$p = new WC_Product_Simple();
$p->set_name('BENCH PRICE Model'); $p->set_sku('BENCH-PRICE'); $p->set_regular_price('14500');
$p->set_status('publish'); $p->set_category_ids(array($cid));
$id = $p->save(); wp_set_object_terms($id,array($cid),'product_cat');

// Simulate a plugin that appends to price HTML, exactly as the EMI table does.
add_filter( 'woocommerce_get_price_html', function( $html ){ return $html . ' <small>0% EMIs from ৳2,417/month</small>'; }, 99 );

$prod = wc_get_product($id);
echo "raw get_price_html():\n  " . $prod->get_price_html() . "\n\n";
echo "what the planner sends today (wp_strip_all_tags):\n  " . wp_strip_all_tags( $prod->get_price_html() ) . "\n\n";

AUN_App_Projectors::flush();
foreach ( AUN_App_Projectors::catalogue(true) as $row ) {
    if ( (int)$row['id'] === $id ) { echo "catalogue['price'] as the APP receives it:\n  " . $row['price'] . "\n"; }
}
wp_delete_post($id,true); AUN_App_Projectors::flush();
