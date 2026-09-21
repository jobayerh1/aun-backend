<?php
/**
 * Bench fixture: one spare-parts request parked in "waiting on customer" with a
 * reason, so the tracking page can be looked at the way a customer sees it.
 * Prints the URL to open. Re-running it refreshes the same request.
 */

global $wpdb;
$t_req  = AUN_SP_Install::table( 'requests' );
$t_item = AUN_SP_Install::table( 'request_items' );
$t_att  = AUN_SP_Install::table( 'attachments' );

$ref = 'SP-BENCH-01';

// Page with the shortcode.
$page = get_page_by_path( 'sp-tracking' );
if ( ! $page ) {
	$pid = wp_insert_post( array(
		'post_title'   => 'Spare parts tracking',
		'post_name'    => 'sp-tracking',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => '[aun_spare_parts_tracking]',
	) );
} else {
	$pid = $page->ID;
	wp_update_post( array( 'ID' => $pid, 'post_content' => '[aun_spare_parts_tracking]' ) );
}

// Clean slate for this ref.
$old = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t_req WHERE ref = %s", $ref ) );
if ( $old ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_item WHERE request_id = %d", $old ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_att WHERE request_id = %d", $old ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req WHERE id = %d", $old ) );
}

$wpdb->insert( $t_req, array(
	'ref'             => $ref,
	'model'           => 'AUN M18 Pro',
	'customer_name'   => 'Bench Customer',
	'phone_current'   => '01799000777',
	'overall_status'  => 'waiting_customer',
	'photo_reason'    => 'wrong_part',
	'photo_note'      => 'The photo shows the power board, but we need the LCD panel behind it.',
	'created_at'      => current_time( 'mysql' ),
) );
$rid = (int) $wpdb->insert_id;

$wpdb->insert( $t_item, array( 'request_id' => $rid, 'part_type' => 'lcd', 'part_label' => 'LCD panel', 'qty' => 1, 'line_status' => 'pending', 'created_at' => current_time( 'mysql' ) ) );
$lcd = (int) $wpdb->insert_id;
$wpdb->insert( $t_item, array( 'request_id' => $rid, 'part_type' => 'psu', 'part_label' => 'Power board', 'qty' => 1, 'line_status' => 'pending', 'created_at' => current_time( 'mysql' ) ) );

$wpdb->update( $t_req, array( 'photo_item_id' => $lcd ), array( 'id' => $rid ) );

// A stand-in for "the photo they sent". Any real file inside uploads/aun-spare-parts
// will do — customer_photos() only returns URLs that resolve to a real file there.
$up  = wp_upload_dir();
$dir = $up['basedir'] . '/aun-spare-parts';
wp_mkdir_p( $dir );
$file = $dir . '/bench-wrong.png';
if ( ! is_file( $file ) ) {
	$im = imagecreatetruecolor( 480, 360 );
	imagefilledrectangle( $im, 0, 0, 480, 360, imagecolorallocate( $im, 40, 60, 90 ) );
	imagestring( $im, 5, 120, 170, 'WRONG PART PHOTO', imagecolorallocate( $im, 255, 255, 255 ) );
	imagepng( $im, $file );
	imagedestroy( $im );
}
$wpdb->insert( $t_att, array(
	'request_id' => $rid, 'item_id' => $lcd, 'kind' => 'proof',
	'file_url'   => $up['baseurl'] . '/aun-spare-parts/bench-wrong.png',
	'created_at' => current_time( 'mysql' ),
) );

// Give the LCD part a reference image so the "what we need instead" side has one.
$parts = get_option( 'aun_sp_parts', array() );
foreach ( $parts as $i => $p ) {
	if ( isset( $p['key'] ) && 'lcd' === $p['key'] ) {
		$parts[ $i ]['ref_image'] = $up['baseurl'] . '/aun-spare-parts/bench-wrong.png';
	}
}
update_option( 'aun_sp_parts', $parts );

echo "request id: $rid  ref: $ref  target item: $lcd\n";
echo "OPEN: " . get_permalink( $pid ) . "?ref=" . rawurlencode( $ref ) . "\n";
