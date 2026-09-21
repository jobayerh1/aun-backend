<?php
/**
 * Bench test: the product-page delivery panel must show the same fee the
 * checkout charges, including flat rates priced by shipping class or formula.
 * Run: php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-delivery-fee.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
function call( $obj, $m, $args = array() ) {
	$r = new ReflectionMethod( $obj, $m );
	$r->setAccessible( true );
	return $r->invokeArgs( $obj, $args );
}

$d = null;
foreach ( $GLOBALS['wp_filter']['woocommerce_package_rates']->callbacks as $cbs ) {
	foreach ( $cbs as $cb ) {
		if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof Breo_Smart_Delivery ) {
			$d = $cb['function'][0];
		}
	}
}
t( (bool) $d, 'Smart Delivery instance found' );
$p  = wc_get_product( wc_get_product_id_by_sku( 'N990000631' ) );
$z1 = new WC_Shipping_Zone( 1 );
$m  = current( $z1->get_shipping_methods( true ) );
t( $m && 'flat_rate' === $m->id, 'bench zone 1 has a flat rate' );

// 1. a plain cost
$m->instance_settings['cost'] = '60';
$m->update_option( 'cost', '60' );
$pkg   = call( $d, 'pseudo_package', array( $p, $z1 ) );
$rates = call( $d, 'method_rates', array( $m, $pkg ) );
t( 1 === count( $rates ) && 60.0 === (float) $rates[0]['cost'], 'plain cost 60 reported as 60 (got ' . ( isset( $rates[0] ) ? $rates[0]['cost'] : '-' ) . ')' );

// 2. the live-site shape: base cost 0, real price on the shipping class
$class = get_term_by( 'slug', 'breo-test-class', 'product_shipping_class' );
if ( ! $class ) {
	$ins   = wp_insert_term( 'Breo test class', 'product_shipping_class', array( 'slug' => 'breo-test-class' ) );
	$class = get_term( $ins['term_id'], 'product_shipping_class' );
}
$p->set_shipping_class_id( $class->term_id );
$p->save();
$p = wc_get_product( $p->get_id() );
$m->update_option( 'cost', '0' );
$m->update_option( 'class_cost_' . $class->term_id, '60' );
$m->update_option( 'no_class_cost', '0' );
$m->instance_settings['cost'] = '0';
$m->instance_settings[ 'class_cost_' . $class->term_id ] = '60';
$m->instance_settings['no_class_cost'] = '0';

$old_way = $m->get_option( 'cost' ); // what the panel used to read
t( '0' === (string) $old_way, 'the raw cost field reads 0 here - this is why it said "Free"' );

$pkg   = call( $d, 'pseudo_package', array( $p, $z1 ) );
$rates = call( $d, 'method_rates', array( $m, $pkg ) );
$shown = isset( $rates[0] ) ? (float) $rates[0]['cost'] : -1;
t( 60.0 === $shown, 'class-priced rate now reported as 60 (got ' . $shown . ')' );

// 3. a [qty] formula
$m->update_option( 'cost', '50 * [qty]' );
$m->instance_settings['cost'] = '50 * [qty]';
$m->update_option( 'class_cost_' . $class->term_id, '' );
$m->instance_settings[ 'class_cost_' . $class->term_id ] = '';
$rates = call( $d, 'method_rates', array( $m, call( $d, 'pseudo_package', array( $p, $z1 ) ) ) );
t( isset( $rates[0] ) && 50.0 === (float) $rates[0]['cost'], 'formula "50 * [qty]" reported as 50 (got ' . ( isset( $rates[0] ) ? $rates[0]['cost'] : '-' ) . ')' );

// 4. the destination really lands inside the zone
$dest = call( $d, 'zone_destination', array( $z1 ) );
t( 'BD' === $dest['country'], 'zone destination country BD (got ' . $dest['country'] . ')' );
$z0   = new WC_Shipping_Zone( 0 );
$dest0 = call( $d, 'zone_destination', array( $z0 ) );
t( '' !== $dest0['country'], '"Everywhere else" falls back to the store country (' . $dest0['country'] . ')' );

// 5. free shipping below its minimum must not be advertised
$fs_id = $z1->add_shipping_method( 'free_shipping' );
// instance settings live in their own option; update_option() on the object does not persist them
update_option( 'woocommerce_free_shipping_' . $fs_id . '_settings', array( 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '999999' ) );
$fs = WC_Shipping_Zones::get_shipping_method( $fs_id );
t( 'min_amount' === $fs->requires && '999999' === (string) $fs->min_amount, 'free shipping set to need a minimum' );
$rates = call( $d, 'method_rates', array( $fs, call( $d, 'pseudo_package', array( $p, $z1 ) ) ) );
t( array() === $rates, 'free shipping under its minimum is left out (got ' . count( $rates ) . ' rate/s)' );

// tidy up
$z1->delete_shipping_method( $fs_id );
$m->update_option( 'cost', '60' );
$m->update_option( 'class_cost_' . $class->term_id, '' );
$p->set_shipping_class_id( 0 );
$p->save();
wp_delete_term( $class->term_id, 'product_shipping_class' );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
