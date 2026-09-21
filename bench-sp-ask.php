<?php
/** Drive the REAL admin "send the photo back" action on the bench fixture, so the
 *  customer-facing timeline line can be inspected in a browser. */
global $wpdb;
$t_req = AUN_SP_Install::table( 'requests' );
$ref   = 'SP-BENCH-01';
$rid   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t_req WHERE ref = %s", $ref ) );
if ( ! $rid ) { echo "fixture missing - run bench-sp-seed.php first\n"; return; }

$item = (int) $wpdb->get_var( $wpdb->prepare(
	'SELECT id FROM ' . AUN_SP_Install::table( 'request_items' ) . " WHERE request_id = %d AND part_label = 'LCD panel'", $rid ) );

wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $u ) { wp_set_current_user( $u[0]->ID ); }
}

$wpdb->update( $t_req, array( 'overall_status' => 'in_progress' ), array( 'id' => $rid ) );

$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_ask'          => '1',
	'photo_reason'       => 'wrong_part',
	'photo_item_id'      => $item,
	'photo_note'         => 'The photo shows the power board, but we need the LCD panel behind it.',
);
$reqs = new AUN_SP_Requests();
$m    = new ReflectionMethod( 'AUN_SP_Requests', 'handle_post' );
$m->setAccessible( true );
echo "notice: " . wp_strip_all_tags( (string) $m->invoke( $reqs, $rid ) ) . "\n";

$ev = $wpdb->get_row( $wpdb->prepare(
	'SELECT message, new_value FROM ' . AUN_SP_Install::table( 'events' ) . " WHERE request_id = %d AND type='photo_request' ORDER BY id DESC LIMIT 1", $rid ) );
echo "admin log : " . ( $ev->message ?? '-' ) . "\n";
echo "reason key: " . ( $ev->new_value ?? '-' ) . "\n";
