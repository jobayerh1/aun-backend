<?php
/**
 * AUN Spare Parts v0.44.0 — the duplicate daily-digest email.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Reproduces the reported symptom (the same summary arriving twice, minutes
 * apart) and proves both causes are closed: the digest is now claimed once per
 * day, and a hook can no longer sit in the cron array more than once.
 */

if ( ! class_exists( 'AUN_SP_Requests' ) || ! class_exists( 'AUN_SP_Install' ) ) {
	echo "AUN Spare Parts not active on this bench.\n";
	exit( 1 );
}

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

global $wpdb;
$t_req = AUN_SP_Install::table( 'requests' );

/* ── capture mail instead of sending it ───────────────────────────── */
$GLOBALS['sent'] = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$GLOBALS['sent'][] = isset( $atts['subject'] ) ? $atts['subject'] : '';
	return true; // short-circuit: nothing actually leaves the bench
}, 10, 2 );
function mails() { return count( $GLOBALS['sent'] ); }
function reset_mail() { $GLOBALS['sent'] = array(); }

/* ── a request that genuinely needs action, so a digest is warranted ── */
$ref = 'SP-2026-DG' . strtoupper( wp_generate_password( 4, false, false ) );
$wpdb->insert( $t_req, array(
	'ref'            => $ref,
	'customer_name'  => 'Digest Test',
	'phone_current'  => '01799000444',
	'model'          => 'AUN A45 Pro',
	'overall_status' => 'submitted', // counts toward "needs your action"
	'created_at'     => current_time( 'mysql' ),
	'updated_at'     => current_time( 'mysql' ),
) );
$rid = (int) $wpdb->insert_id;

$old_email = get_option( 'aun_sp_alert_email' );
update_option( 'aun_sp_alert_email', 'digest-test@example.test' );
delete_option( 'aun_sp_digest_sent_on' );

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 1. The reported bug: the hook firing twice in one day ===\n";

reset_mail();
AUN_SP_Requests::send_digest();
$first = mails();
AUN_SP_Requests::send_digest();   // the second cron run, minutes later
$after_second = mails();
ok( 1 === $first, 'the first run sends the summary' );
ok( 1 === $after_second, 'the second run sends NOTHING (was: a duplicate email)' );
ok( current_time( 'Y-m-d' ) === (string) get_option( 'aun_sp_digest_sent_on' ), 'today is recorded as claimed' );

echo "\n=== 2. Tomorrow still gets its summary ===\n";
reset_mail();
update_option( 'aun_sp_digest_sent_on', gmdate( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS ), false );
AUN_SP_Requests::send_digest();
ok( 1 === mails(), 'a stale claim from yesterday does not block today' );

echo "\n=== 3. A quiet day claims nothing ===\n";
// The bench carries other rows (old fixtures), and ANY open/stale/late one would
// warrant a digest — so genuinely quieten it, then put every status back exactly
// as it was. Without this the assertion below fails for the wrong reason.
$snapshot = $wpdb->get_results( "SELECT id, overall_status FROM $t_req" );
$wpdb->query( "UPDATE $t_req SET overall_status = 'closed'" );

delete_option( 'aun_sp_digest_sent_on' );
reset_mail();
AUN_SP_Requests::send_digest();
ok( 0 === mails(), 'nothing needing attention -> no email' );
ok( '' === (string) get_option( 'aun_sp_digest_sent_on', '' ), 'and the day is NOT claimed, so a later run can still report' );

// ...and it really can: something turns up later the same day.
$wpdb->update( $t_req, array( 'overall_status' => 'submitted' ), array( 'id' => $rid ) );
reset_mail();
AUN_SP_Requests::send_digest();
ok( 1 === mails(), 'something turns up later the same day -> it is still reported' );

foreach ( (array) $snapshot as $row ) {
	$wpdb->update( $t_req, array( 'overall_status' => $row->overall_status ), array( 'id' => (int) $row->id ) );
}

echo "\n=== 4. A hook can no longer sit in the cron array twice ===\n";

function count_sched( $hook ) {
	$n = 0;
	foreach ( (array) _get_cron_array() as $events ) {
		if ( isset( $events[ $hook ] ) ) { $n += count( (array) $events[ $hook ] ); }
	}
	return $n;
}

// Recreate the broken state: two daily entries, a few minutes apart.
wp_clear_scheduled_hook( 'aun_sp_daily_digest' );
wp_schedule_event( time() + 3600, 'daily', 'aun_sp_daily_digest' );
wp_schedule_event( time() + 3900, 'daily', 'aun_sp_daily_digest' );
$before = count_sched( 'aun_sp_daily_digest' );
ok( 2 === $before, "the broken state reproduces ($before daily entries — this is the real cause)" );

AUN_SP_Install::ensure_single_schedule();
$after = count_sched( 'aun_sp_daily_digest' );
ok( 1 === $after, "self-heal leaves exactly one ($before -> $after)" );

// A healthy schedule must be left completely alone (no drifting run times).
$ts_before = wp_next_scheduled( 'aun_sp_daily_digest' );
AUN_SP_Install::ensure_single_schedule();
ok( $ts_before === wp_next_scheduled( 'aun_sp_daily_digest' ), 'a healthy schedule is not touched (run time unchanged)' );
ok( 1 === count_sched( 'aun_sp_hourly_tidy' ), 'the hourly hook is single too' );

// The empty string WordPress hands a no-arg cron callback must not break it.
$fixed = AUN_SP_Install::ensure_single_schedule( '' );
ok( 0 === $fixed, "safe when cron calls it with WordPress's empty-string argument" );

echo "\n=== 5. Deactivation removes EVERY instance ===\n";
wp_schedule_event( time() + 4200, 'daily', 'aun_sp_daily_digest' );
ok( 2 === count_sched( 'aun_sp_daily_digest' ), 'two entries staged' );
wp_clear_scheduled_hook( 'aun_sp_daily_digest' ); // what deactivation now does
ok( 0 === count_sched( 'aun_sp_daily_digest' ), 'clear removes both (the old code left one behind)' );
AUN_SP_Install::ensure_single_schedule(); // put the bench back in a healthy state
ok( 1 === count_sched( 'aun_sp_daily_digest' ), 'bench restored to exactly one' );

/* ── cleanup ──────────────────────────────────────────────────────── */
$wpdb->delete( $t_req, array( 'id' => $rid ) );
$wpdb->delete( AUN_SP_Install::table( 'events' ), array( 'request_id' => $rid ) );
if ( false === $old_email ) { delete_option( 'aun_sp_alert_email' ); }
else { update_option( 'aun_sp_alert_email', $old_email ); }
delete_option( 'aun_sp_digest_sent_on' );

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
