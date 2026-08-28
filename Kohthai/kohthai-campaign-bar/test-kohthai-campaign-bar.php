<?php
/**
 * Regression harness for kohthai-campaign-bar.
 *
 *   cd ~/wp-local
 *   php -c php.ini wp-cli.phar --path=site eval-file "<path>/test-campaign-bar.php" --skip-themes
 *
 * The point of interest is the campaign WINDOW: it is now enforced in three places
 * (server render, boundary cron, browser timer) and they must agree to the second.
 */

if ( ! class_exists( 'Kohthai_Campaign_Notice_Bar' ) ) {
	fwrite( STDERR, "kohthai-campaign-bar is not active on this bench.\n" );
	exit( 1 );
}

function tally( $p = 0, $f = 0 ) { static $P = 0, $F = 0; $P += $p; $F += $f; return array( $P, $F ); }
function t( $label, $got, $want ) {
	if ( $got === $want ) { tally( 1, 0 ); echo "  ok    $label\n"; }
	else { tally( 0, 1 ); echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n"; }
}
function priv( $method, $args = array() ) {
	$m = new ReflectionMethod( 'Kohthai_Campaign_Notice_Bar', $method );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

/** Set the campaign window from offsets in seconds relative to now (null = blank). */
function set_window( $start_off, $end_off, $extra = array() ) {
	$tz = new DateTimeZone( 'Asia/Dhaka' );
	$fmt = function ( $off ) use ( $tz ) {
		if ( $off === null ) return '';
		$d = new DateTime( '@' . ( time() + $off ) );
		$d->setTimezone( $tz );
		return $d->format( 'Y-m-d H:i' );
	};
	update_option( Kohthai_Campaign_Notice_Bar::OPTION_KEY, array_merge(
		Kohthai_Campaign_Notice_Bar::defaults(),
		array(
			'enabled'            => '1',
			'smart_sale_enabled' => '0',   // isolate the campaign path from sale detection
			'timezone'           => 'Asia/Dhaka',
			'start'              => $fmt( $start_off ),
			'end'                => $fmt( $end_off ),
			'message'            => 'CAMPAIGN-MARKER',
		),
		$extra
	) );
}

/* --------------------------------------------------------------- A. window */

echo "\nA. the campaign window\n";

// Minute granularity: build the window a few minutes out so rounding to 'Y-m-d H:i'
// cannot land us on the wrong side of "now".
set_window( -3600, 3600 );
$w = Kohthai_Campaign_Notice_Bar::window_ts();
t( 'start parses to a timestamp', $w['start'] > 0, true );
t( 'end parses to a timestamp', $w['end'] > 0, true );
t( 'start is before end', $w['start'] < $w['end'], true );
t( 'window is ~2 hours wide', abs( ( $w['end'] - $w['start'] ) - 7200 ) <= 60, true );

// The stored strings are wall-clock Asia/Dhaka; confirm the offset is really applied
// rather than being read as UTC (a 6-hour error would be invisible in a width check).
$back = new DateTime( '@' . $w['start'] );
$back->setTimezone( new DateTimeZone( 'Asia/Dhaka' ) );
$opts = get_option( Kohthai_Campaign_Notice_Bar::OPTION_KEY );
t( 'timestamp round-trips to the stored Dhaka wall-clock time', $back->format( 'Y-m-d H:i' ), $opts['start'] );

set_window( null, null );
$w = Kohthai_Campaign_Notice_Bar::window_ts();
t( 'blank dates give an open-ended window', array( $w['start'], $w['end'] ), array( 0, 0 ) );

/* --------------------------------------------------------------- B. active */

echo "\nB. is_active() across the boundaries\n";

set_window( -3600, 3600 );  t( 'inside the window -> active', priv( 'is_active' ), true );
set_window( 3600, 7200 );   t( 'before it opens -> inactive', priv( 'is_active' ), false );
set_window( -7200, -3600 ); t( 'after it closes -> inactive', priv( 'is_active' ), false );
set_window( null, null );   t( 'no dates at all -> active', priv( 'is_active' ), true );

set_window( -3600, 3600, array( 'enabled' => '0' ) );
t( 'master switch off beats the window', priv( 'is_active' ), false );

/* ------------------------------------------------------------ C. shortcode */

echo "\nC. what the shortcode emits\n";

set_window( -3600, 3600 );
$in = do_shortcode( '[kohthai_campaign_notice]' );
t( 'inside the window: message present', false !== strpos( $in, 'CAMPAIGN-MARKER' ), true );
t( 'inside the window: NOT hidden', false === strpos( $in, 'class="kt-cb-top" data-start' ) ? null : ( false === strpos( $in, ' hidden>' ) ), true );
t( 'inside the window: carries the timer script', false !== strpos( $in, 'kt-cb-top' ), true );

set_window( 3600, 7200 );
$pre = do_shortcode( '[kohthai_campaign_notice]' );
t( 'before it opens: message IS pre-rendered', false !== strpos( $pre, 'CAMPAIGN-MARKER' ), true );
t( 'before it opens: rendered hidden', false !== strpos( $pre, ' hidden>' ), true );

// After the end / when disabled, no message is rendered. What IS emitted is the
// hidden empty-bar controller — see section L, which is the whole reason it exists.
set_window( -7200, -3600 );
$post = do_shortcode( '[kohthai_campaign_notice]' );
t( 'after it closes: no message', false === strpos( $post, 'CAMPAIGN-MARKER' ), true );
t( 'after it closes: nothing visible', false === strpos( $post, 'kt-cb-msg' ), true );

set_window( -3600, 3600, array( 'enabled' => '0' ) );
$off = do_shortcode( '[kohthai_campaign_notice]' );
t( 'disabled: no message', false === strpos( $off, 'CAMPAIGN-MARKER' ), true );
t( 'disabled: nothing visible', false === strpos( $off, 'kt-cb-msg' ), true );

set_window( null, null );
$open = do_shortcode( '[kohthai_campaign_notice]' );
t( 'open-ended campaign: message shown', false !== strpos( $open, 'CAMPAIGN-MARKER' ), true );
t( 'open-ended campaign: still wrapped, for the dismiss button', false !== strpos( $open, 'class="kt-cb-x"' ), true );

// Nothing to time and nothing to close: emit plain markup, no wrapper, no script.
set_window( null, null, array( 'allow_dismiss' => '0' ) );
$plain = do_shortcode( '[kohthai_campaign_notice]' );
t( 'open-ended + not dismissible: no wrapper at all', false === strpos( $plain, 'kt-cb-top' ), true );
t( '  ...but the message still renders', false !== strpos( $plain, 'CAMPAIGN-MARKER' ), true );

/* ----------------------------------------------------- D. the browser timer */

echo "\nD. the inline timer\n";

set_window( 3600, 7200 );
$html = do_shortcode( '[kohthai_campaign_notice]' );
$w    = Kohthai_Campaign_Notice_Bar::window_ts();

t( 'data-start matches window_ts()', false !== strpos( $html, 'data-start="' . $w['start'] . '"' ), true );
t( 'data-end matches window_ts()', false !== strpos( $html, 'data-end="' . $w['end'] . '"' ), true );
t( 'inline script carries data-no-optimize', false !== strpos( $html, 'data-no-optimize="1"' ), true );
t( 'inline script carries data-no-minify', false !== strpos( $html, 'data-no-minify="1"' ), true );
t( 'the bar mode reaches the script', false !== strpos( $html, "BAR_MODE" ), true );

if ( preg_match( '/<script data-no-optimize[^>]*>(.*?)<\/script>/s', $html, $m ) ) {
	$js = $m[1];
	t( 'braces balance in the timer', substr_count( $js, '{' ), substr_count( $js, '}' ) );
	t( 'parens balance in the timer', substr_count( $js, '(' ), substr_count( $js, ')' ) );
	t( 'the timer re-checks at the boundary', false !== strpos( $js, 'setTimeout(tick' ), true );
	t( 'the top-bar sync is present', false !== strpos( $js, 'function syncBar' ), true );
} else {
	t( 'timer script found', false, true );
}

// Two shortcodes on one page must not print the stylesheet twice. The "printed"
// flag is per-request and earlier cases in this file already tripped it, so reset
// it first — otherwise this passes for the wrong reason.
$flag = new ReflectionProperty( 'Kohthai_Campaign_Notice_Bar', 'top_printed' );
$flag->setAccessible( true );
$flag->setValue( null, false );

set_window( -3600, 3600 );
$twice = do_shortcode( '[kohthai_campaign_notice][kohthai_campaign_notice]' );
t( 'message rendered by both shortcodes', substr_count( $twice, 'CAMPAIGN-MARKER' ), 2 );
t( 'style block printed only once', substr_count( $twice, 'kt-cb-bar-empty{display:none' ), 1 );

// Each notice gets its own id, and the timer finds it by id rather than by walking
// siblings — wpautop can insert a <p> between the span and the script.
preg_match_all( '/id="(kt-cb-\d+)"/', $twice, $ids );
t( 'two notices rendered', count( $ids[1] ), 2 );
t( 'their ids are distinct', count( array_unique( $ids[1] ) ), 2 );
// Each id should appear exactly twice: once on the span, once in its own
// getElementById call. Anything else means a timer is pointing at the wrong notice.
t( 'each timer targets its own notice',
	array( substr_count( $twice, wp_json_encode( $ids[1][0] ) ), substr_count( $twice, wp_json_encode( $ids[1][1] ) ) ),
	array( 2, 2 ) );
t( 'timer looks the element up by id', false !== strpos( $twice, 'getElementById' ), true );
t( 'timer does NOT rely on previousElementSibling', false === strpos( $twice, 'previousElementSibling' ), true );

// The realistic failure: the widget content goes through wpautop before output.
$flag->setValue( null, false );
$autop = wpautop( do_shortcode( '[kohthai_campaign_notice]' ) );
t( 'survives wpautop: the id is still on the notice', 1, preg_match( '/id="kt-cb-\d+"/', $autop ) );

/* ------------------------------------------------------------ E. cache/cron */

echo "\nE. boundary scheduling\n";

set_window( 3600, 7200 );
Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
$w = Kohthai_Campaign_Notice_Bar::window_ts();
t( 'a purge is scheduled at the start', wp_next_scheduled( 'kt_cb_boundary', array( 'start' ) ), $w['start'] );
t( 'a purge is scheduled at the end', wp_next_scheduled( 'kt_cb_boundary', array( 'end' ) ), $w['end'] );

// Distinct args are what let two events survive inside WP's 10-minute dedupe window.
set_window( 120, 300 );
Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
$w = Kohthai_Campaign_Notice_Bar::window_ts();
t( 'a 3-minute campaign still gets BOTH purges',
	array( wp_next_scheduled( 'kt_cb_boundary', array( 'start' ) ), wp_next_scheduled( 'kt_cb_boundary', array( 'end' ) ) ),
	array( $w['start'], $w['end'] ) );

// Past boundaries are not scheduled.
set_window( -7200, -3600 );
Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
t( 'nothing scheduled for a campaign already over',
	array( wp_next_scheduled( 'kt_cb_boundary', array( 'start' ) ), wp_next_scheduled( 'kt_cb_boundary', array( 'end' ) ) ),
	array( false, false ) );

// The self-healing catch-up: fires once for a passed boundary, then stays quiet.
$fired = 0;
add_action( 'kt_cb_cache_purged', function () use ( &$fired ) { $fired++; } );

set_window( -7200, -3600 );
Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
$fired = 0;
Kohthai_Campaign_Notice_Bar::catch_up_boundary();
t( 'catch-up purges once for a missed boundary', $fired, 1 );
Kohthai_Campaign_Notice_Bar::catch_up_boundary();
t( '  ...and does not purge again on the next request', $fired, 1 );

// The ordinary case: the campaign has opened but not yet closed. The start is
// settled now; the end must still be able to fire its own purge later.
set_window( -3600, 3600 );
Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
$fired = 0;
Kohthai_Campaign_Notice_Bar::catch_up_boundary();
t( 'a passed start purges', $fired, 1 );
Kohthai_Campaign_Notice_Bar::catch_up_boundary();
t( '  ...and stays quiet while the end is still ahead', $fired, 1 );
$state = get_option( 'kt_cb_boundaries' );
t( '  ...with the end left unsettled for later', (int) $state['done'] < (int) $state['end'], true );

/* -------------------------------------------------------------- F. settings */

echo "\nF. settings\n";

$d = Kohthai_Campaign_Notice_Bar::defaults();
t( 'hide_empty_topbar defaults to mobile', $d['hide_empty_topbar'], 'mobile' );

foreach ( array( 'off', 'mobile', 'always' ) as $mode ) {
	$saved = Kohthai_Campaign_Notice_Bar::sanitize_options( array( '_form' => '1', 'hide_empty_topbar' => $mode, 'enabled' => '1' ) );
	t( "hide_empty_topbar survives a Save as '$mode'", $saved['hide_empty_topbar'], $mode );
}
// It is a select, so it is always posted — a junk value must not become a mode.
$saved = Kohthai_Campaign_Notice_Bar::sanitize_options( array( '_form' => '1', 'hide_empty_topbar' => '<script>', 'enabled' => '1' ) );
t( 'a junk mode falls back to mobile', $saved['hide_empty_topbar'], 'mobile' );

/* ------------------------------------------------ G. sanitiser can't wipe data */

echo "\nG. sanitize_options() on partial input\n";

// It runs on EVERY update_option() for our key, not just a form save. A missing
// field must mean "leave alone", or a programmatic update blanks the campaign.
set_window( -3600, 3600 );
$before = get_option( Kohthai_Campaign_Notice_Bar::OPTION_KEY );

$after = Kohthai_Campaign_Notice_Bar::sanitize_options( array( 'message' => 'ONLY THIS CHANGED' ) );
t( 'the edited field is applied', $after['message'], 'ONLY THIS CHANGED' );
t( 'other messages are preserved', $after['product_message'], $before['product_message'] );
t( 'the schedule is preserved', array( $after['start'], $after['end'] ), array( $before['start'], $before['end'] ) );
t( 'checkboxes are preserved without the form marker', $after['enabled'], $before['enabled'] );

// A real form save: an unticked box sends nothing and must read as off.
$form = Kohthai_Campaign_Notice_Bar::sanitize_options( array( '_form' => '1', 'message' => 'x' ) );
t( 'form save: unticked checkbox turns off', $form['enabled'], '0' );
t( 'form save: still keeps untouched messages', $form['product_message'], $before['product_message'] );
t( 'the _form marker is not stored', isset( $form['_form'] ), false );

$tzbad = Kohthai_Campaign_Notice_Bar::sanitize_options( array( 'timezone' => 'Mars/Olympus' ) );
t( 'an unknown timezone falls back to Asia/Dhaka', $tzbad['timezone'], 'Asia/Dhaka' );
$tzok = Kohthai_Campaign_Notice_Bar::sanitize_options( array( 'timezone' => 'Asia/Kolkata' ) );
t( 'a real timezone is accepted', $tzok['timezone'], 'Asia/Kolkata' );

t( 'non-array input is ignored', Kohthai_Campaign_Notice_Bar::sanitize_options( 'nonsense' ), Kohthai_Campaign_Notice_Bar::get_options() );

/* ------------------------------------------------ H. the "Save ৳X" figure */

echo "\nH. saving_for() on a variable product\n";

$vid = wc_get_products( array( 'limit' => 1, 'return' => 'ids', 'type' => 'variable', 'name' => 'harness-variable-bag' ) );
$vid = $vid ? $vid[0] : 0;
if ( ! $vid ) {
	$parent = new WC_Product_Variable();
	$parent->set_name( 'Harness Variable Projector' );
	$parent->set_slug( 'harness-variable-bag' );
	$parent->set_status( 'publish' );
	$attr = new WC_Product_Attribute();
	$attr->set_name( 'Size' );
	$attr->set_options( array( 'S', 'L' ) );
	$attr->set_visible( true );
	$attr->set_variation( true );
	$parent->set_attributes( array( $attr ) );
	$vid = $parent->save();

	// The expensive variation is the discounted one; the cheap one is not on sale.
	// This is precisely the shape the old formula got wrong.
	$v1 = new WC_Product_Variation();
	$v1->set_parent_id( $vid );
	$v1->set_attributes( array( 'size' => 'S' ) );
	$v1->set_regular_price( '50000' );
	$v1->set_sale_price( '45000' );
	$v1->save();

	$v2 = new WC_Product_Variation();
	$v2->set_parent_id( $vid );
	$v2->set_attributes( array( 'size' => 'L' ) );
	$v2->set_regular_price( '10000' );
	$v2->save();

	WC_Product_Variable::sync( $vid );
}

$vp = wc_get_product( $vid );
t( 'the test product is variable and on sale', $vp->is_type( 'variable' ) && $vp->is_on_sale(), true );

$saving = priv( 'saving_for', array( $vp ) );
t( 'reports the real 5,000 saving', (float) $saving, 5000.0 );

// What the previous code computed, kept here so the regression stays visible:
// min regular price across variations minus min "sale" price across variations.
$old = (float) $vp->get_variation_regular_price( 'min', true ) - (float) $vp->get_variation_sale_price( 'min', true );
t( '  ...where the old min-vs-min formula reported nothing', $old, 0.0 );

$sp = wc_get_products( array( 'limit' => 1, 'return' => 'ids', 'type' => 'simple', 'status' => 'publish' ) );
if ( $sp ) {
	$s = wc_get_product( $sp[0] );
	$s->set_regular_price( '20000' );
	$s->set_sale_price( '17500' );
	$s->save();
	t( 'simple products still measured directly', (float) priv( 'saving_for', array( wc_get_product( $sp[0] ) ) ), 2500.0 );
}

t( 'a non-product is worth nothing, not a warning', priv( 'saving_for', array( null ) ), 0.0 );

/* ----------------------------------------------------------- I. countdown */

echo "\nI. the {countdown} tag\n";

set_window( -60, 2 * DAY_IN_SECONDS + 4 * HOUR_IN_SECONDS, array( 'message' => 'Hurry! Ends in {countdown}.' ) );
$cd = do_shortcode( '[kohthai_campaign_notice]' );
$w  = Kohthai_Campaign_Notice_Bar::window_ts();

t( 'the tag is replaced', false === strpos( $cd, '{countdown}' ), true );
t( 'a countdown element is rendered', false !== strpos( $cd, 'class="kt-cb-cd"' ), true );
t( 'it carries the campaign end as an absolute deadline',
	false !== strpos( $cd, 'data-until="' . $w['end'] . '"' ), true );
t( 'the server pre-renders a value for no-JS visitors',
	1, preg_match( '/class="kt-cb-cd"[^>]*>\d+d \d\d:\d\d:\d\d</', $cd ) );

// The text the PHP produces and the text the JS produces must agree, or the
// number visibly jumps the moment the script runs.
t( 'PHP formats days as "2d HH:MM:SS"',
	1, preg_match( '/^2d \d\d:\d\d:\d\d$/', priv( 'countdown_text', array( time() + 2 * DAY_IN_SECONDS + 3661, false ) ) ) );
t( 'under a day it is just the clock',
	1, preg_match( '/^\d\d:\d\d:\d\d$/', priv( 'countdown_text', array( time() + 3661, false ) ) ) );
t( 'Bangla uses Bangla numerals',
	1, preg_match( '/^[০-৯]+ দিন [০-৯]{2}:[০-৯]{2}:[০-৯]{2}$/u', priv( 'countdown_text', array( time() + 2 * DAY_IN_SECONDS + 3661, true ) ) ) );
t( 'an elapsed deadline reads as zero', priv( 'countdown_text', array( time() - 500, false ) ), '00:00:00' );

// No end date: the tag AND its sentence go, rather than leaving "Ends in ."
set_window( null, null, array( 'message' => 'Big sale on now. Hurry! Ends in {countdown}.' ) );
$nocd = do_shortcode( '[kohthai_campaign_notice]' );
t( 'no end date: the tag is gone', false === strpos( $nocd, '{countdown}' ), true );
t( 'no end date: no dangling "Ends in" sentence', false === strpos( $nocd, 'Ends in' ), true );
t( 'no end date: the rest of the message survives', false !== strpos( $nocd, 'Big sale on now.' ), true );

/* ------------------------------------------------------------- J. dismiss */

echo "\nJ. dismissal\n";

set_window( -60, 3600 );
$d1 = do_shortcode( '[kohthai_campaign_notice]' );
// Match the BUTTON, not the .kt-cb-x rule that also lives in the style block.
t( 'a close button is rendered', false !== strpos( $d1, 'class="kt-cb-x"' ), true );
t( 'the mute length is passed through', false !== strpos( $d1, 'data-mute="3"' ), true );
t( 'a campaign signature is present', 1, preg_match( '/data-sig="[0-9a-f]{12}"/', $d1 ) );

set_window( -60, 3600, array( 'allow_dismiss' => '0' ) );
$d2 = do_shortcode( '[kohthai_campaign_notice]' );
t( 'the button disappears when switched off', false === strpos( $d2, 'class="kt-cb-x"' ), true );
t( '  ...and the mute length reads zero', false !== strpos( $d2, 'data-mute="0"' ), true );

// The signature is what stops a closed campaign muting the NEXT one.
preg_match( '/data-sig="([0-9a-f]+)"/', $d1, $s1 );
set_window( -60, 3600, array( 'message' => 'A COMPLETELY DIFFERENT OFFER' ) );
preg_match( '/data-sig="([0-9a-f]+)"/', do_shortcode( '[kohthai_campaign_notice]' ), $s2 );
t( 'a new message means a new signature', $s1[1] !== $s2[1], true );

set_window( -60, 7200, array( 'message' => 'CAMPAIGN-MARKER' ) );
preg_match( '/data-sig="([0-9a-f]+)"/', do_shortcode( '[kohthai_campaign_notice]' ), $s3 );
set_window( -60, 3600, array( 'message' => 'CAMPAIGN-MARKER' ) );
preg_match( '/data-sig="([0-9a-f]+)"/', do_shortcode( '[kohthai_campaign_notice]' ), $s4 );
t( 'new dates mean a new signature', $s3[1] !== $s4[1], true );

$sanit = Kohthai_Campaign_Notice_Bar::sanitize_options( array( 'dismiss_days' => '-5' ) );
t( 'dismiss_days -5 clamps to 1, not 5', $sanit['dismiss_days'], '1' );
$sanit = Kohthai_Campaign_Notice_Bar::sanitize_options( array( 'dismiss_days' => '9999' ) );
t( 'dismiss_days is capped at 90', $sanit['dismiss_days'], '90' );

/* -------------------------------------------------------- K. status card */

echo "\nK. settings status\n";

set_window( 3600, 7200 );
t( 'before the start reads as scheduled', Kohthai_Campaign_Notice_Bar::campaign_state()['key'], 'scheduled' );
set_window( -3600, 3600 );
t( 'inside the window reads as live', Kohthai_Campaign_Notice_Bar::campaign_state()['key'], 'live' );
set_window( -7200, -3600 );
$ended = Kohthai_Campaign_Notice_Bar::campaign_state();
t( 'after the end reads as ended', $ended['key'], 'ended' );
t( '  ...and says visitors see nothing', false !== strpos( $ended['detail'], 'visitors see nothing' ), true );
set_window( -3600, 3600, array( 'enabled' => '0' ) );
t( 'disabled reads as off', Kohthai_Campaign_Notice_Bar::campaign_state()['key'], 'off' );
set_window( -3600, 3600, array( 'message' => '' ) );
$empty = Kohthai_Campaign_Notice_Bar::campaign_state();
t( 'enabled with an empty message is not reported as live', $empty['key'], 'off' );
t( '  ...and says why', false !== strpos( $empty['detail'], 'message box is empty' ), true );
set_window( null, null );
t( 'open-ended is live', Kohthai_Campaign_Notice_Bar::campaign_state()['key'], 'live' );
t( '  ...and says there is no end date', false !== strpos( Kohthai_Campaign_Notice_Bar::campaign_state()['detail'], 'No end date' ), true );

/* -------------------------------------------- L. collapsing an empty top bar */

echo "
L. the empty top bar
";

/** Capture whatever maybe_hide_topbar_css() prints into the <head>. */
function head_css() {
	ob_start();
	Kohthai_Campaign_Notice_Bar::maybe_hide_topbar_css();
	return ob_get_clean();
}

// Nothing to show -> the bar is hidden in the head, before it can be painted.
foreach ( array(
	'campaign already ended' => array( -7200, -3600, array() ),
	'campaign switched off'  => array( -3600, 3600, array( 'enabled' => '0' ) ),
	'no message set'         => array( -3600, 3600, array( 'message' => '', 'message_bn' => '' ) ),
) as $label => $case ) {
	set_window( $case[0], $case[1], $case[2] );
	t( "$label: top_is_showing() is false", Kohthai_Campaign_Notice_Bar::top_is_showing(), false );
	$css = head_css();
	t( "  $label: the bar is hidden in the head", false !== strpos( $css, 'display:none' ), true );
	t( "  $label: scoped to mobile by default", false !== strpos( $css, '@media (max-width:849px)' ), true );
	// The whole point of moving this server-side: no element, no script, no flash.
	t( "  $label: the shortcode emits nothing at all", trim( do_shortcode( '[kohthai_campaign_notice]' ) ), '' );
}

// A live campaign fills the bar, so it must NOT be hidden.
set_window( -3600, 3600 );
t( 'live campaign: top_is_showing() is true', Kohthai_Campaign_Notice_Bar::top_is_showing(), true );
t( '  ...and no hiding css is printed', trim( head_css() ), '' );

// A campaign that has not opened yet is showing NOTHING, so the bar must be
// collapsed until it does — otherwise the empty strip is back for the whole
// wait. The pre-rendered message still goes out, and the browser un-collapses
// the bar at the same moment it reveals the notice.
set_window( 3600, 7200 );
t( 'scheduled campaign: nothing is showing yet', Kohthai_Campaign_Notice_Bar::top_is_showing(), false );
t( '  ...so the bar IS collapsed meanwhile', false !== strpos( head_css(), 'display:none' ), true );
$sched = do_shortcode( '[kohthai_campaign_notice]' );
t( '  ...but the message is pre-rendered hidden', false !== strpos( $sched, ' hidden>' ), true );
t( '  ...with the script that will bring the bar back',
	false !== strpos( $sched, "getElementById('kt-cb-hidebar')" ), true );

// Modes.
set_window( -7200, -3600, array( 'hide_empty_topbar' => 'always' ) );
$css = head_css();
t( 'always: hides at every width', false === strpos( $css, '@media' ), true );
t( '  ...and still hides', false !== strpos( $css, 'display:none' ), true );

set_window( -7200, -3600, array( 'hide_empty_topbar' => 'off' ) );
t( 'off: prints nothing', trim( head_css() ), '' );

// Legacy values from before this became a three-way choice must keep working.
t( "legacy '1' migrates to mobile", Kohthai_Campaign_Notice_Bar::hide_mode( '1' ), 'mobile' );
t( "legacy '0' migrates to off", Kohthai_Campaign_Notice_Bar::hide_mode( '0' ), 'off' );
t( 'a nonsense value falls back to mobile', Kohthai_Campaign_Notice_Bar::hide_mode( 'banana' ), 'mobile' );

update_option( Kohthai_Campaign_Notice_Bar::OPTION_KEY, array_merge(
	Kohthai_Campaign_Notice_Bar::defaults(), array( 'hide_empty_topbar' => '1' ) ) );
t( 'a stored legacy value reads back as a mode',
	Kohthai_Campaign_Notice_Bar::get_options()['hide_empty_topbar'], 'mobile' );

// The selector and breakpoint are filterable so a theme update is a one-liner.
add_filter( 'kt_cb_topbar_selector', function () { return '.my-custom-bar'; } );
add_filter( 'kt_cb_topbar_breakpoint', function () { return 600; } );
set_window( -7200, -3600 );
$css = head_css();
t( 'the selector filter is honoured', false !== strpos( $css, '.my-custom-bar' ), true );
t( 'the breakpoint filter is honoured', false !== strpos( $css, 'max-width:600px' ), true );
remove_all_filters( 'kt_cb_topbar_selector' );
remove_all_filters( 'kt_cb_topbar_breakpoint' );

// The notice script only ever flips that one style element now.
set_window( -3600, 3600 );
$live = do_shortcode( '[kohthai_campaign_notice]' );
t( 'the script targets the head style element', false !== strpos( $live, "getElementById('kt-cb-hidebar')" ), true );
t( 'no DOM measuring remains', false === strpos( $live, 'getClientRects' ), true );

/* ------------------------------------------------------------ M. templates */

echo "\nM. campaign templates\n";

$tpls = Kohthai_Campaign_Notice_Bar::templates();
foreach ( array( 'countdown', 'lastchance', 'flash_countdown' ) as $key ) {
	t( "the '$key' template exists", isset( $tpls[ $key ] ), true );
}

// Every template must only write fields that actually exist, or the Apply button
// silently drops the text into nowhere.
$valid = array_keys( Kohthai_Campaign_Notice_Bar::defaults() );
foreach ( $tpls as $key => $tpl ) {
	$unknown = array_values( array_diff( array_keys( $tpl['fields'] ), $valid ) );
	t( "'$key' writes only real fields", $unknown, array() );
	$empty = array_keys( array_filter( $tpl['fields'], function ( $v ) { return trim( $v ) === ''; } ) );
	t( "  '$key' has no blank fields", $empty, array() );
}

// Both countdown templates must survive a campaign WITH an end date...
foreach ( array( 'countdown', 'lastchance' ) as $key ) {
	set_window( -60, 2 * DAY_IN_SECONDS, $tpls[ $key ]['fields'] );
	$out = do_shortcode( '[kohthai_campaign_notice]' );
	t( "'$key' with an end date: renders a live timer", false !== strpos( $out, 'class="kt-cb-cd"' ), true );
	t( "  '$key': no raw tag left behind", false === strpos( $out, '{countdown}' ), true );

	// ...and one WITHOUT, where the whole "only … left" clause must disappear
	// rather than leaving a stranded sentence.
	set_window( null, null, $tpls[ $key ]['fields'] );
	$none = do_shortcode( '[kohthai_campaign_notice]' );
	// Look at the MESSAGE only: the inline timer script contains "var left", which
	// would otherwise make this assertion pass or fail for the wrong reason.
	preg_match( '/<span class="kt-cb-msg">(.*?)<\/span><button/s', $none, $mm );
	$msg_only = $mm ? $mm[1] : $none;
	t( "'$key' with no end date: tag removed", false === strpos( $msg_only, '{countdown}' ), true );
	t( "  '$key': no dangling 'only … left'", false === stripos( $msg_only, ' left' ), true );
	t( "  '$key': the call to action survives", false !== stripos( $msg_only, 'href="/shop/"' ), true );
}

// The product-page notice supports the tag too — it did not until now.
set_window( -60, 2 * DAY_IN_SECONDS, $tpls['countdown']['fields'] );
$prod = do_shortcode( '[kohthai_campaign_product_notice]' );
t( 'the product notice renders a timer', false !== strpos( $prod, 'class="kt-cb-cd"' ), true );
t( '  ...with no raw tag', false === strpos( $prod, '{countdown}' ), true );

// NOTE: the upstream AUN harness checks the Bangla halves here. Kohthai is an
// English-only site, so the templates carry no *_bn fields and there is nothing
// to assert. The Bangla ENGINE is still present and still works -- it is simply
// never fed on this site. See bn_available() in the plugin.

/* -------------------------------------------------------------- teardown */

delete_option( Kohthai_Campaign_Notice_Bar::OPTION_KEY );
delete_option( 'kt_cb_boundaries' );
wp_clear_scheduled_hook( 'kt_cb_boundary', array( 'start' ) );
wp_clear_scheduled_hook( 'kt_cb_boundary', array( 'end' ) );

list( $pass, $fail ) = tally();
echo "\n----------------------------------------\n";
echo "$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
