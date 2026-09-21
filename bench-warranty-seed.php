<?php
/** A few representative registrations so the admin table can be looked at. */
global $wpdb;
$t = $wpdb->prefix . 'slb_registrations';
if ( function_exists( 'slb_create_tables' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	slb_create_tables();
}
$wpdb->query( "DELETE FROM $t WHERE customer_name LIKE 'Demo %'" );

$rows = array(
	array( 'Demo Mismatch',  'mismatch',  'AUN A45 Pro', 'Star Technology', 'Dealer on the form (Star Technology) is not the shop this unit was shipped to.' ),
	array( 'Demo Pending',   'pending',   'AUN U002',    'online website',  '' ),
	array( 'Demo NotFound',  'not_found', 'AUN A32 Pro', 'Computer Village','' ),
	array( 'Demo Approved',  'approved',  'AUN A45 Pro', 'Star Tech Ltd.',  '' ),
	array( 'Demo Rejected',  'rejected',  'AUN A005',    'Islam Computers', '' ),
	array( 'Demo Duplicate', 'duplicate', 'AUN U002',    'Product Home',    '' ),
	array( 'Demo Released',  'released',  'AUN A45 Pro', 'Star Tech Ltd.',  '' ),
);
foreach ( $rows as $i => $r ) {
	list( $name, $status, $model, $dealer, $note ) = $r;
	$wpdb->insert( $t, array(
		'serial'        => '90000000000' . $i,
		'customer_name' => $name,
		'phone'         => '0171122334' . $i,
		'email'         => 'demo' . $i . '@example.test',
		'product_model' => $model,
		'dealer_name'   => $dealer,
		'invoice_no'    => 'INV-DEMO-' . ( 1000 + $i ),
		'purchase_date' => '2026-0' . ( ( $i % 8 ) + 1 ) . '-12',
		'status'        => $status,
		'notes'         => $note ? '[HELD FOR REVIEW] ' . $note : '',
		'created_at'    => current_time( 'mysql' ),
	) );
}
delete_transient( 'slb_manual_counts' );
echo "seeded " . count( $rows ) . " demo registrations\n";
echo "badge: " . wp_json_encode( slb_manual_action_counts() ) . "\n";
echo "OPEN: " . admin_url( 'admin.php?page=slb-warranty-registrations' ) . "\n";
