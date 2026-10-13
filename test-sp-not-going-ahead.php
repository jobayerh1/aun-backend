<?php
/**
 * AUN Spare Parts 0.48.0 — "Not going ahead" set by hand, whole-Taka money, and the
 * redrawn request page.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * The bug (owner, 2026-10-12): setting a part to "Not going ahead (declined / expired)"
 * did not end the request. A request awaiting approval stayed "awaiting approval", so
 * the customer still had Approve / Decline, kept getting quote reminders, and was texted
 * an ordinary "update on your request" message.
 */
$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}
if ( ! class_exists( 'AUN_SP_Requests' ) ) { echo "plugin not active\n"; exit( 1 ); }
global $wpdb;
wp_set_current_user( 1 );

/* ---- SMS: capture what would be sent (priority 20: the bench's SMS stub sits at 10) ---- */
$GLOBALS['sms'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( (string) $url, 'sms.net.bd' ) ) {
		$body = is_array( $args['body'] ?? null ) ? $args['body'] : array();
		parse_str( is_string( $args['body'] ?? null ) ? $args['body'] : '', $parsed );
		$GLOBALS['sms'][] = (string) ( $body['msg'] ?? $parsed['msg'] ?? '' );
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => '{"error":0,"msg":"Request successfully submitted","data":{"request_id":1}}', 'headers' => array(), 'cookies' => array() );
	}
	return $pre;
}, 20, 3 );
if ( ! AUN_SP_SMS::is_configured() ) {
	update_option( AUN_SP_SMS::ALPHA_OPTION, array( 'api_key' => 'BENCH' ) );
}
add_filter( 'pre_wp_mail', '__return_true', 1 );
$GLOBALS['status_hooks'] = array();
add_action( 'aun_sp_status_changed', function ( $id, $to, $from ) { $GLOBALS['status_hooks'][] = $to; }, 10, 3 );

$reqs = new AUN_SP_Requests();
$hp   = new ReflectionMethod( 'AUN_SP_Requests', 'handle_post' ); $hp->setAccessible( true );

function mk( $status, $parts ) {
	global $wpdb;
	$t_req  = AUN_SP_Install::table( 'requests' );
	$t_item = AUN_SP_Install::table( 'request_items' );
	$wpdb->insert( $t_req, array(
		'ref' => 'SP-NGA' . wp_rand( 10000, 99999 ), 'model' => 'AUN U002', 'customer_name' => 'NGA Test',
		'phone_current' => '01799000777', 'overall_status' => $status, 'address_current' => 'Mirpur, Dhaka',
		'quoted_at' => 'quote_sent' === $status ? current_time( 'mysql' ) : null,
		'quote_expires_at' => 'quote_sent' === $status ? date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 5 * DAY_IN_SECONDS ) : null,
		'created_at' => current_time( 'mysql' ),
	) );
	$rid = (int) $wpdb->insert_id;
	$ids = array();
	foreach ( $parts as $label => $p ) {
		$wpdb->insert( $t_item, array( 'request_id' => $rid, 'part_label' => $label, 'part_type' => sanitize_key( $label ), 'qty' => 1,
			'line_status' => $p[0], 'unit_price' => $p[1], 'created_at' => current_time( 'mysql' ) ) );
		$ids[] = (int) $wpdb->insert_id;
	}
	return array( $rid, $ids );
}
function save( $rid, $statuses, $extra = array() ) {
	global $wpdb;
	$t_item = AUN_SP_Install::table( 'request_items' );
	$post = array( 'aun_sp_update_nonce' => wp_create_nonce( 'aun_sp_update' ), 'save_updates' => '1', 'notify' => '1', 'quote_note' => '' );
	foreach ( $statuses as $iid => $st ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_item WHERE id = %d", $iid ) );
		$post['item_status'][ $iid ] = $st;
		$post['item_price'][ $iid ]  = $row->unit_price;
		$post['item_qty'][ $iid ]    = $row->qty;
	}
	$_POST = array_merge( $post, $extra );
	$GLOBALS['sms'] = array(); $GLOBALS['status_hooks'] = array();
	return $GLOBALS['hp']->invoke( $GLOBALS['reqs'], $rid );
}
$GLOBALS['hp'] = $hp; $GLOBALS['reqs'] = $reqs;
function overall( $rid ) { global $wpdb; return $wpdb->get_var( $wpdb->prepare( "SELECT overall_status FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE id = %d", $rid ) ); }

echo "\n=== 1. The rule (compute_overall) ===\n";
$c = function ( $lines, $cur ) { return AUN_SP_Requests::compute_overall( $lines, $cur, false ); };
ok( 'declined' === $c( array( 'cancelled', 'cancelled' ), 'quote_sent' ), 'awaiting approval + every part Not going ahead -> Quote declined (was: stuck awaiting approval)' );
ok( 'declined' === $c( array( 'cancelled' ), 'submitted' ), 'no quote yet + Not going ahead -> Quote declined (was: "Rejected", i.e. WE could not supply)' );
ok( 'declined' === $c( array( 'cancelled', 'unavailable' ), 'approved' ), 'Not going ahead + Unavailable -> Quote declined' );
ok( 'rejected' === $c( array( 'unavailable', 'unavailable' ), 'submitted' ), 'all Unavailable is still Rejected (our side)' );
ok( 'closed' === $c( array( 'cancelled', 'delivered' ), 'in_progress' ), 'one delivered + one Not going ahead -> Completed' );
ok( 'quote_sent' === $c( array( 'cancelled', 'quoted' ), 'quote_sent' ), 'only SOME parts Not going ahead -> still awaiting approval for the rest' );
ok( 'expired' === $c( array( 'cancelled' ), 'expired' ) && 'declined' === $c( array( 'cancelled' ), 'declined' ), 'expired / declined requests are left as they are' );

echo "\n=== 2. Awaiting approval: you set every part to Not going ahead ===\n";
list( $rid, $ids ) = mk( 'quote_sent', array( 'LCD screen' => array( 'quoted', 1500 ), 'Remote' => array( 'quoted', 600 ) ) );
$order_id = AUN_SP_Woo::is_active() ? AUN_SP_Woo::create_order( $rid ) : 0;
$notice = save( $rid, array( $ids[0] => 'cancelled', $ids[1] => 'cancelled' ) );
ok( 'declined' === overall( $rid ), 'the request is now Quote declined (' . overall( $rid ) . ')' );
$t = AUN_SP_Install::table( 'requests' );
$payload_status = $wpdb->get_var( $wpdb->prepare( "SELECT overall_status FROM $t WHERE id = %d", $rid ) );
ok( 'quote_sent' !== $payload_status, 'so the tracking page no longer offers Approve / Decline (awaiting = false)' );
ok( 1 === count( $GLOBALS['sms'] ) && false !== stripos( $GLOBALS['sms'][0], 'cancelled your spare-parts quote' ), 'the customer gets the DECLINE confirmation: "' . substr( $GLOBALS['sms'][0] ?? '-', 0, 70 ) . '…"' );
ok( false === stripos( implode( ' ', $GLOBALS['sms'] ), 'update on your' ), '…not an ordinary "update on your request" message' );
ok( in_array( 'declined', $GLOBALS['status_hooks'], true ), 'the app is told (aun_sp_status_changed -> declined)' );
ok( false !== strpos( $notice, 'counts as the customer declining' ), 'your notice explains what happened' );
$ev = $wpdb->get_var( $wpdb->prepare( "SELECT message FROM " . AUN_SP_Install::table( 'events' ) . " WHERE request_id = %d AND type = 'declined' ORDER BY id DESC", $rid ) );
ok( $ev && false !== strpos( $ev, 'Not going ahead' ), 'the activity log records it' );
$r1 = AUN_SP_Requests::process_quotes();
$rem = $wpdb->get_var( $wpdb->prepare( "SELECT quote_reminders FROM $t WHERE id = %d", $rid ) );
ok( 0 === (int) $rem, 'no quote reminders go out any more' );
if ( $order_id ) {
	// By design an UNPAID order is deleted, not cancelled (AUN_SP_Woo::discard_unpaid):
	// no payable link survives, and the Orders list doesn't fill with dead rows.
	$o = wc_get_order( $order_id );
	ok( ! $o || ! $o->needs_payment(), 'its unpaid payment order is removed, so no pay link survives (' . ( $o ? $o->get_status() : 'deleted' ) . ')' );
}
$rv = AUN_SP_Requests::revive_quote( $rid, 'web' );
ok( ! empty( $rv['ok'] ) && 'submitted' === overall( $rid ), 'and the customer can still ask for a new quote' );

echo "\n=== 3. Without the SMS box ticked ===\n";
list( $rid, $ids ) = mk( 'quote_sent', array( 'Fan' => array( 'quoted', 400 ) ) );
save( $rid, array( $ids[0] => 'cancelled' ), array( 'notify' => '' ) );
ok( 'declined' === overall( $rid ) && 0 === count( $GLOBALS['sms'] ), 'still declined, and nobody is texted' );

echo "\n=== 4. Only ONE of two parts Not going ahead ===\n";
list( $rid, $ids ) = mk( 'quote_sent', array( 'LCD screen' => array( 'quoted', 1500 ), 'Remote' => array( 'quoted', 600 ) ) );
save( $rid, array( $ids[0] => 'quoted', $ids[1] => 'cancelled' ) );
ok( 'quote_sent' === overall( $rid ), 'the request still awaits approval for the LCD screen' );
ok( 1500.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT quote_total FROM $t WHERE id = %d", $rid ) ), 'and the amount to approve drops to ৳1500' );
ok( 1 === count( $GLOBALS['sms'] ) && false !== stripos( $GLOBALS['sms'][0], 'Remote' ), 'the customer is told about the Remote only' );

echo "\n=== 5. Before the quote: the customer says no on WhatsApp ===\n";
list( $rid, $ids ) = mk( 'submitted', array( 'Lens' => array( 'pending', 0 ) ) );
save( $rid, array( $ids[0] => 'cancelled' ) );
ok( 'declined' === overall( $rid ), 'Quote declined, not Rejected (' . overall( $rid ) . ')' );

echo "\n=== 6. A request already stuck by the old bug heals itself ===\n";
list( $rid, $ids ) = mk( 'quote_sent', array( 'Board' => array( 'cancelled', 900 ) ) ); // what 0.47 left behind
$sr = new ReflectionMethod( 'AUN_SP_Requests', 'sync_rows' ); $sr->setAccessible( true );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $rid ) );
$sr->invoke( $reqs, array( $row ) );
ok( 'declined' === overall( $rid ), 'opening it (or the list) settles it as Quote declined' );

echo "\n=== 7. Money is whole Taka ===\n";
list( $rid, $ids ) = mk( 'submitted', array( 'LCD screen' => array( 'pending', 0 ) ) );
$_POST = array();
save( $rid, array( $ids[0] => 'pending' ), array( 'item_price' => array( $ids[0] => '1499.6' ), 'delivery_charge' => '99.4' ) );
$ti = AUN_SP_Install::table( 'request_items' );
ok( 1500.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT unit_price FROM $ti WHERE id = %d", $ids[0] ) ), 'unit price 1499.6 is saved as ৳1500' );
if ( AUN_SP_Woo::is_active() ) {
	ok( 99.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT delivery_charge FROM $t WHERE id = %d", $rid ) ), 'delivery 99.4 is saved as ৳99' );
}

echo "\n=== 8. The request page ===\n";
$ta = AUN_SP_Install::table( 'attachments' );
$wpdb->insert( $ta, array( 'request_id' => $rid, 'item_id' => $ids[0], 'file_url' => 'http://127.0.0.1:8080/wp-content/uploads/test-a.webp', 'created_at' => current_time( 'mysql' ) ) );
$wpdb->insert( $ta, array( 'request_id' => $rid, 'item_id' => 0, 'file_url' => 'http://127.0.0.1:8080/wp-content/uploads/test-b.webp', 'created_at' => current_time( 'mysql' ) ) );
$wpdb->update( $ti, array( 'tracking_no' => 'DA091026WVKG', 'unit_price' => 1500 ), array( 'id' => $ids[0] ) );
$dt = new ReflectionMethod( 'AUN_SP_Requests', 'detail' ); $dt->setAccessible( true );
ob_start(); $dt->invoke( $reqs, $rid, '' ); $page = ob_get_clean();
ok( 2 === substr_count( $page, 'class="aun-sp-thumb"' ), 'photos are thumbnails (one under the part, one under Customer-added photos)' );
ok( false === strpos( $page, '>view<' ) && false === strpos( $page, '>view photo<' ), 'no more "view" text links' );
ok( false !== strpos( $page, "aun-sp-lbx" ) && false !== strpos( $page, "aria-label', 'Close'" ), 'the lightbox (with a Close button) is on the page' );
ok( 1 === preg_match( '/<div class="aun-sp-track"><input[^>]+DA091026WVKG[^>]*><a class="aun-sp-track-go"/', $page ), 'the tracking link is a button right beside the field' );
ok( false === strpos( $page, '&#8599;' ), 'the little arrow under the field is gone' );
ok( false === strpos( $page, 'max-width:820px' ), 'nothing is boxed into 820px any more' );
ok( 7 === preg_match_all( '/<th class="c-/', $page ), 'the parts table has 7 columns (was 10)' );
ok( 1 === preg_match( '/name="item_price\[\d+\]" value="1500"/', $page ) && 1 === preg_match( '/<input type="number" step="1" min="0" inputmode="numeric" name="item_price/', $page ), 'unit price: whole Taka (value 1500, step 1)' );
if ( AUN_SP_Woo::is_active() ) {
	ok( 1 === preg_match( '/step="1" min="0" inputmode="numeric" name="delivery_charge" value="99"/', $page ), 'delivery charge: whole Taka (value 99, step 1)' );
}
ok( false === strpos( $page, 'step="0.01"' ), 'no 0.01 steps left on the page' );
ok( false !== strpos( $page, 'Not going ahead (customer said no)' ), 'the dropdown now says what it means' );

// tidy
$wpdb->query( "DELETE FROM $ta WHERE file_url LIKE '%/uploads/test-%'" );
$_POST = array();
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
