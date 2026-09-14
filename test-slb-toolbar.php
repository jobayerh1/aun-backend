<?php
/**
 * SLB Warranty v2.7.0 — the toolbar badge for registrations needing a human.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * This badge renders on EVERY page load for logged-in staff (front end too), so
 * the things that matter are: it must not fatal, it must not query on every
 * request, and it must show NOTHING when there is nothing to decide.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

$plugin = 'C:/Users/Jobayer Hossain/Downloads/Claude session/AUN Warranty & Registration.php';
if ( ! is_file( $plugin ) ) { echo "plugin file not found\n"; exit( 1 ); }

// Load the real plugin file (defines the functions + registers its hooks).
require_once $plugin;
ok( function_exists( 'slb_manual_action_counts' ), 'plugin file loads without a fatal' );
ok( function_exists( 'slb_admin_bar_node' ), 'toolbar callbacks defined' );

global $wpdb;
$t = $wpdb->prefix . 'slb_registrations';

// Make sure the table exists on the bench.
if ( function_exists( 'slb_create_tables' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	slb_create_tables();
}
$exists = ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t );
echo "  (bench: registrations table detected via SHOW TABLES = " . ( $exists ? 'yes' : 'NO — SQLite may not translate it' ) . ")\n";

$made = array();
function mk_reg( $status ) {
	global $wpdb;
	// wp eval-file runs this file inside a FUNCTION, so the file-level $t and
	// $made are NOT globals. Recompute the table name here (a 'global $t' bound
	// to nothing, so every insert went to table '' and silently did nothing).
	$t = $wpdb->prefix . 'slb_registrations';
	$wpdb->insert( $t, array(
		'serial'        => (string) wp_rand( 100000000000, 999999999999 ),
		'customer_name' => 'Toolbar Test',
		'phone'         => '01799000555',
		'email'         => 'toolbar@example.test',
		'status'        => $status,
		'created_at'    => current_time( 'mysql' ),
	) );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) { echo '  !! insert failed: ' . $wpdb->last_error . "
"; }
	if ( $id ) { $GLOBALS['made'][] = $id; }
	return $id;
}

/* ── clear the decks so the counts are ours alone ── */
$snapshot = $wpdb->get_results( "SELECT id, status FROM $t" );
$wpdb->query( "UPDATE $t SET status = 'approved'" );
delete_transient( 'slb_manual_counts' );

echo "\n=== 1. Nothing to decide -> the badge is completely absent ===\n";
$c = slb_manual_action_counts();
ok( 0 === $c['total'], 'count is zero when every registration is settled' );

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
add_filter( 'show_admin_bar', '__return_true' );

$bar = new WP_Admin_Bar();
slb_admin_bar_node( $bar );
ok( null === $bar->get_node( 'slb-warranty-alert' ), 'no toolbar node at all (not even a zero badge)' );
ob_start(); slb_admin_bar_styles(); $css = ob_get_clean();
ok( '' === trim( $css ), 'no CSS emitted either' );

echo "\n=== 2. Something needs a human -> the badge appears ===\n";
mk_reg( 'pending' );
mk_reg( 'not_found' );
mk_reg( 'not_found' );
mk_reg( 'mismatch' );
mk_reg( 'approved' );   // settled: must NOT be counted
mk_reg( 'rejected' );   // settled: must NOT be counted
mk_reg( 'duplicate' );  // settled: must NOT be counted
delete_transient( 'slb_manual_counts' );

$c = slb_manual_action_counts();
ok( 1 === $c['pending'],   'pending counted (' . $c['pending'] . ')' );
ok( 2 === $c['not_found'], 'not_found counted (' . $c['not_found'] . ')' );
ok( 1 === $c['mismatch'],  'mismatch counted (' . $c['mismatch'] . ')' );
ok( 4 === $c['total'],     'total is 4 — approved/rejected/duplicate excluded (got ' . $c['total'] . ')' );

$bar  = new WP_Admin_Bar();
slb_admin_bar_node( $bar );
$node = $bar->get_node( 'slb-warranty-alert' );
ok( null !== $node, 'toolbar node added' );
ok( $node && false !== strpos( $node->title, '>4<' ), 'bubble shows the count' );
ok( $node && false !== strpos( (string) $node->href, 'slb-warranty-registrations' ), 'links to the Registrations screen' );

foreach ( array( 'pending', 'not_found', 'mismatch' ) as $st ) {
	$sub = $bar->get_node( 'slb-warranty-alert-' . $st );
	ok( null !== $sub, "sub-item for '$st' present" );
	ok( $sub && false !== strpos( (string) $sub->href, 'status_filter=' . $st ), "'$st' links to its filtered view" );
}
ob_start(); slb_admin_bar_styles(); $css = ob_get_clean();
ok( false !== strpos( $css, 'slb-ab-bubble' ), 'bubble CSS emitted when the node exists' );
ok( false !== strpos( $css, 'data-no-optimize' ), 'CSS carries the WP Rocket guard' );

echo "\n=== 3. A kind with nothing in it is not listed ===\n";
$wpdb->query( "UPDATE $t SET status='approved' WHERE status='mismatch'" );
delete_transient( 'slb_manual_counts' );
$bar = new WP_Admin_Bar();
slb_admin_bar_node( $bar );
ok( null === $bar->get_node( 'slb-warranty-alert-mismatch' ), 'empty "mismatch" row is omitted' );
ok( null !== $bar->get_node( 'slb-warranty-alert-pending' ), 'the kinds that DO have items remain' );

echo "\n=== 4. It must not query on every page load ===\n";
delete_transient( 'slb_manual_counts' );
slb_manual_action_counts();             // primes the cache
$q0 = $wpdb->num_queries;
for ( $i = 0; $i < 5; $i++ ) { slb_manual_action_counts(); }
$spent = $wpdb->num_queries - $q0;
ok( 0 === $spent, "5 further calls cost $spent queries (cached for a minute)" );

echo "\n=== 5. Non-admins never see it ===\n";
wp_set_current_user( 0 );
$bar = new WP_Admin_Bar();
slb_admin_bar_node( $bar );
ok( null === $bar->get_node( 'slb-warranty-alert' ), 'logged-out visitor gets no node' );
wp_set_current_user( 1 );

/* ── cleanup ── */
foreach ( (array) ( $GLOBALS['made'] ?? array() ) as $id ) { $wpdb->delete( $t, array( 'id' => $id ) ); }
foreach ( (array) $snapshot as $r ) {
	$wpdb->update( $t, array( 'status' => $r->status ), array( 'id' => (int) $r->id ) );
}
delete_transient( 'slb_manual_counts' );

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
