<?php
/**
 * aun-campaign-bar 1.10.0 — the campaign popup banner (server side).
 *
 *   cd ~/wp-local
 *   php -c php.ini wp-cli.phar --path=site eval-file "<path>/test-campaign-popup.php" --skip-themes
 *
 * Needs three images in the bench media library titled aun-pop-test-firstorder,
 * aun-pop-test-pop-phone and aun-pop-test-pop-bn (wp media import ... --title=...).
 * The browser half (timing, once-per-visit, focus, copy) is tested in a real browser.
 */
if ( ! class_exists( 'AUN_CB_Popup' ) ) { fwrite( STDERR, "aun-campaign-bar 1.10+ is not active\n" ); exit( 1 ); }

$GLOBALS['g_pass'] = 0; $GLOBALS['g_fail'] = 0;
function t( $label, $got, $want ) {
	if ( $got === $want ) { $GLOBALS['g_pass']++; echo "  ok    $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n"; }
}
function img_id( $title ) {
	$q = get_posts( array( 'post_type' => 'attachment', 'title' => $title, 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	return $q ? (int) $q[0] : 0;
}
$GLOBALS['IMG']   = img_id( 'aun-pop-test-firstorder' );
$GLOBALS['IMG_M'] = img_id( 'aun-pop-test-pop-phone' );
$GLOBALS['IMG_B'] = img_id( 'aun-pop-test-pop-bn' );
t( 'test images exist in the media library', $GLOBALS['IMG'] > 0 && $GLOBALS['IMG_M'] > 0 && $GLOBALS['IMG_B'] > 0, true );

/** Campaign window from offsets (seconds from now; null = blank) + popup settings. */
function setup( $start_off, $end_off, $extra = array() ) {
	$tz  = new DateTimeZone( 'Asia/Dhaka' );
	$fmt = function ( $off ) use ( $tz ) {
		if ( $off === null ) return '';
		$d = new DateTime( '@' . ( time() + $off ) ); $d->setTimezone( $tz ); return $d->format( 'Y-m-d H:i' );
	};
	update_option( AUN_Campaign_Notice_Bar::OPTION_KEY, array_merge(
		AUN_Campaign_Notice_Bar::defaults(),
		array(
			'enabled' => '1', 'smart_sale_enabled' => '0', 'timezone' => 'Asia/Dhaka',
			'start' => $fmt( $start_off ), 'end' => $fmt( $end_off ), 'message' => 'CAMPAIGN',
			'popup_enabled' => '1', 'popup_image' => $GLOBALS['IMG'], 'popup_link' => '/projector-price/',
			'popup_code' => 'WELCOME5', 'popup_button' => 'Shop projectors',
		),
		$extra
	) );
}
/** Render the footer popup as a visitor at $uri would get it. */
function render_at( $uri = '/', $admin = false ) {
	$_SERVER['REQUEST_URI'] = $uri;
	unset( $GLOBALS['TRP_LANGUAGE'] );
	wp_set_current_user( $admin ? 1 : 0 );
	$p = new ReflectionProperty( 'AUN_CB_Popup', 'printed' ); $p->setAccessible( true ); $p->setValue( null, false );
	ob_start(); AUN_CB_Popup::render(); return ob_get_clean();
}
function mk_at( $uri = '/', $admin = false ) { return markup( render_at( $uri, $admin ) ); }
/** The popup's HTML without its <style> and <script>, which mention every class name. */
function markup( $html ) { return preg_replace( '#<(style|script)\b[^>]*>.*?</\1>#s', '', $html ); }
function cfg_of( $html ) {
	if ( ! preg_match( '/data-cfg="([^"]+)"/', $html, $m ) ) return null;
	return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
}

echo "\nA. off unless switched on\n";
$d = AUN_Campaign_Notice_Bar::defaults();
t( 'a fresh install has the popup OFF', $d['popup_enabled'], '0' );
setup( -3600, 3600, array( 'popup_enabled' => '0' ) );
t( 'switched off: nothing printed', render_at(), '' );

echo "\nB. a live campaign with a banner\n";
setup( -3600, 7200 );
$h = render_at();
$c = cfg_of( $h );
t( 'the popup is printed', false !== strpos( $h, 'id="aun-cb-pop"' ), true );
t( 'it starts hidden (the browser decides when)', 1, preg_match( '/id="aun-cb-pop" class="aun-pop" hidden/', $h ) );
t( 'its settings reach the browser as valid JSON', is_array( $c ), true );
$w = AUN_Campaign_Notice_Bar::window_ts();
t( '  with the campaign\'s exact start and end', array( $c['start'], $c['end'] ), array( $w['start'], $w['end'] ) );
t( '  and the timing defaults (8 s / 40% / exit / 1st page / 3 days)', array( $c['delay'], $c['scroll'], $c['exit'], $c['pv'], $c['snooze'] ), array( 8, 40, 1, 1, 3 ) );
t( '  and the image proportions for fitting short screens', (float) $c['ar'], 1.0 );
t( 'the image is NOT downloaded with the page (data-src only)', false === strpos( $h, ' src="http' ) && false !== strpos( $h, 'data-src="http' ), true );
t( 'the image has width/height and its alt text', 1, preg_match( '/<img[^>]+alt="First order discount[^"]*"[^>]+width="800" height="800"/', $h ) );
t( 'WP Rocket / Flatsome lazy-load are told to keep off it', false !== strpos( $h, 'data-no-lazy="1"' ) && false !== strpos( $h, 'skip-lazy' ), true );
t( 'the banner links to the landing page', false !== strpos( $h, 'class="aun-pop__media" href="/projector-price/"' ), true );
t( 'the coupon has a copy button carrying the code', false !== strpos( $h, 'class="aun-pop__copy" data-code="WELCOME5"' ), true );
t( 'the call-to-action button is there', 1, preg_match( '/class="aun-pop__cta"[^>]*>Shop projectors/', $h ) );
t( 'the countdown is there (an end date is set)', false !== strpos( $h, 'aun-pop__cd' ), true );
t( 'it is an accessible modal dialog', false !== strpos( $h, 'role="dialog" aria-modal="true"' ), true );
t( 'the close button is labelled', false !== strpos( $h, 'class="aun-pop__x" data-aun-pop-close aria-label="Close"' ), true );
t( 'style + script printed once each', array( substr_count( $h, 'id="aun-cb-pop-css"' ), substr_count( $h, "getElementById('aun-cb-pop')" ) ), array( 1, 1 ) );
t( 'printed only once per page even if the footer hook runs twice', ( function () { ob_start(); AUN_CB_Popup::render(); return ob_get_clean(); } )(), '' );

echo "\nC. when it must NOT be on the page\n";
setup( -7200, -3600 );
t( 'campaign ended: nothing', render_at(), '' );
setup( -3600, 3600, array( 'enabled' => '0' ) );
t( 'campaign switched off: nothing (one switch for both)', render_at(), '' );
setup( -3600, 3600, array( 'popup_image' => 0 ) );
t( 'no image chosen: nothing', render_at(), '' );
setup( -3600, 3600, array( 'popup_exclude' => "/spare-parts/\n/warranty" ) );
t( 'an excluded page: nothing', render_at( '/spare-parts/track/' ), '' );
t( '  ...the বাংলা copy of it too', render_at( '/bn/spare-parts/' ), '' );
t( '  ...a page that is not excluded still gets it', false !== strpos( render_at( '/shop/' ), 'aun-cb-pop' ), true );

echo "\nD. scheduled for later\n";
setup( 3600, 7200 );
$h = render_at();
t( 'not started yet: printed anyway (the cached page may outlive the start)', false !== strpos( $h, 'id="aun-cb-pop"' ), true );
t( '  ...and the browser is told when it may open', cfg_of( $h )['start'], AUN_Campaign_Notice_Bar::window_ts()['start'] );
setup( -3600, null );
$h = mk_at();
t( 'no end date: no countdown', false === strpos( $h, 'aun-pop__cd' ), true );
t( '  ...and end = 0 for the browser', cfg_of( $h )['end'], 0 );

echo "\nE. the footer strip adapts to what is set\n";
setup( -3600, 3600, array( 'popup_code' => '' ) );
$h = mk_at();
t( 'no code: no code box', false === strpos( $h, 'aun-pop__code' ), true );
setup( -3600, 3600, array( 'popup_link' => '' ) );
$h = mk_at();
t( 'no link: the banner is a picture, not a link', false !== strpos( $h, '<div class="aun-pop__media">' ), true );
t( '  ...and there is no button to nowhere', false === strpos( $h, 'aun-pop__cta' ), true );
setup( -3600, 3600, array( 'popup_button' => '' ) );
$h = mk_at();
t( 'empty button text: no button, banner still a link', false === strpos( $h, 'aun-pop__cta' ) && false !== strpos( $h, 'a class="aun-pop__media"' ), true );
setup( -3600, 3600, array( 'popup_countdown' => '0' ) );
t( 'countdown switched off: none', false === strpos( mk_at(), 'aun-pop__cd' ), true );
setup( -3600, 3600, array( 'popup_code' => '', 'popup_link' => '', 'popup_countdown' => '0' ) );
t( 'nothing but the image: the "bare" layout', false !== strpos( render_at(), 'class="aun-pop aun-pop--bare"' ), true );

echo "\nF. language and screen\n";
setup( -3600, 3600, array( 'popup_image_m' => $GLOBALS['IMG_M'], 'popup_image_bn' => $GLOBALS['IMG_B'], 'popup_button_bn' => 'প্রজেক্টর দেখুন' ) );
$en = render_at( '/' );
$bn = render_at( '/bn/' );
$src_main = wp_get_attachment_image_src( $GLOBALS['IMG'], 'full' )[0];
$src_ph   = wp_get_attachment_image_src( $GLOBALS['IMG_M'], 'full' )[0];
$src_bn   = wp_get_attachment_image_src( $GLOBALS['IMG_B'], 'full' )[0];
t( 'English: the main image', false !== strpos( $en, 'data-src="' . $src_main . '"' ), true );
t( 'English phones: the phone image via <source media>', 1, preg_match( '/<source media="\(max-width: 849px\)" data-srcset="[^"]*' . preg_quote( basename( $src_ph, '.png' ), '/' ) . '/', $en ) );
t( 'বাংলা: the Bangla image', false !== strpos( $bn, 'data-src="' . $src_bn . '"' ), true );
t( 'বাংলা phones: the Bangla image beats the English phone art', false === strpos( $bn, basename( $src_ph, '.png' ) ), true );
t( 'বাংলা: the Bangla button text', false !== strpos( $bn, 'প্রজেক্টর দেখুন' ), true );
t( 'বাংলা: Bangla labels ("কপি", "বন্ধ করুন")', false !== strpos( $bn, '>কপি<' ) && false !== strpos( $bn, 'aria-label="বন্ধ করুন"' ), true );
t( 'বাংলা: the countdown is told to use Bangla numerals', cfg_of( $bn )['bn'], 1 );
setup( -3600, 3600, array( 'popup_image_bn' => 0, 'popup_button_bn' => '' ) );
t( 'no Bangla image: the main one is reused on /bn/', false !== strpos( render_at( '/bn/' ), 'data-src="' . $src_main . '"' ), true );
t( 'no Bangla button text: the English one is reused', false !== strpos( render_at( '/bn/' ), 'Shop projectors' ), true );

echo "\nG. admin preview\n";
setup( -7200, -3600, array( 'popup_enabled' => '0' ) );
$_GET[ AUN_CB_Popup::PREVIEW_ARG ] = '1';
$h = render_at( '/', false );
t( 'a VISITOR adding ?aun_cb_popup_preview=1 gets nothing', $h, '' );
$h = render_at( '/', true );
t( 'an ADMIN gets it, even switched off and ended', false !== strpos( $h, 'id="aun-cb-pop"' ), true );
t( '  ...flagged as a preview, with the ribbon', cfg_of( $h )['preview'] === 1 && false !== strpos( $h, 'aun-pop__tag' ), true );
unset( $_GET[ AUN_CB_Popup::PREVIEW_ARG ] );
wp_set_current_user( 0 );

echo "\nH. saving the settings\n";
$s = AUN_Campaign_Notice_Bar::sanitize_options( array( '_form' => '1',
	'popup_image' => (string) $GLOBALS['IMG'], 'popup_link' => '/projector-price/', 'popup_code' => ' WEL COME5 ',
	'popup_delay' => '-4', 'popup_scroll' => '250', 'popup_width' => '5000', 'popup_pageview' => '9', 'popup_snooze' => '0',
	'popup_devices' => 'tv', 'popup_exclude' => "https://aun-projector.com.bd/spare-parts/\n\n /warranty/ \n/warranty/" ) );
t( 'a real image id is kept', $s['popup_image'], $GLOBALS['IMG'] );
t( 'unticked boxes on a form save mean off', array( $s['popup_enabled'], $s['popup_countdown'], $s['popup_exit'] ), array( '0', '0', '0' ) );
t( 'a site path stays a relative link', $s['popup_link'], '/projector-price/' );
t( 'spaces are taken out of the code', $s['popup_code'], 'WELCOME5' );
t( 'numbers are clamped (delay, scroll, width, page, snooze)', array( $s['popup_delay'], $s['popup_scroll'], $s['popup_width'], $s['popup_pageview'], $s['popup_snooze'] ), array( '0', '100', '900', '5', '1' ) );
t( 'an unknown device choice falls back to all', $s['popup_devices'], 'all' );
t( 'excluded pages: full address -> path, blanks + duplicates dropped', $s['popup_exclude'], "/spare-parts/\n/warranty/" );
$s = AUN_Campaign_Notice_Bar::sanitize_options( array( 'popup_link' => 'javascript:alert(1)', 'popup_image' => '999999' ) );
t( 'a javascript: link is refused', $s['popup_link'], '' );
t( 'a missing / non-image attachment is refused', $s['popup_image'], 0 );
$page = wp_insert_post( array( 'post_title' => 'not an image', 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ) );
$s = AUN_Campaign_Notice_Bar::sanitize_options( array( 'popup_image' => (string) $page ) );
t( 'a PDF is not accepted as the banner', $s['popup_image'], 0 );
wp_delete_post( $page, true );
setup( -3600, 3600 );
$before = get_option( AUN_Campaign_Notice_Bar::OPTION_KEY );
update_option( AUN_Campaign_Notice_Bar::OPTION_KEY, array_merge( $before, array( 'message' => 'CHANGED' ) ) );
$after = get_option( AUN_Campaign_Notice_Bar::OPTION_KEY );
t( 'a partial update elsewhere leaves the popup settings alone', array( $after['popup_image'], $after['popup_code'], $after['popup_enabled'] ), array( $GLOBALS['IMG'], 'WELCOME5', '1' ) );

echo "\nI. one campaign's choices never silence the next\n";
setup( -3600, 3600 );
$o   = AUN_Campaign_Notice_Bar::get_options();
$sig = AUN_CB_Popup::signature( $o );
t( 'the signature is stable', AUN_CB_Popup::signature( $o ), $sig );
foreach ( array( 'popup_image' => $GLOBALS['IMG_B'], 'popup_link' => '/eid/', 'popup_code' => 'EID10' ) as $k => $v ) {
	$o2 = $o; $o2[ $k ] = $v;
	t( "  ...and changes with the $k", AUN_CB_Popup::signature( $o2 ) !== $sig, true );
}
setup( -3600, 7200 );
t( '  ...and with new dates', AUN_CB_Popup::signature( AUN_Campaign_Notice_Bar::get_options() ) !== $sig, true );

echo "\nJ. shown / tapped / copied / closed counts\n";
class Pop_Done extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new Pop_Done(); }; }, 99 );
function beacon( $ev, $sig, $ua = 'Mozilla/5.0 (Linux; Android 13) Chrome/129 Mobile' ) {
	$_POST = array( 'action' => 'aun_cb_pop_stat', 'ev' => $ev, 'sig' => $sig ); $_REQUEST = $_POST;
	$_SERVER['HTTP_USER_AGENT'] = $ua;
	ob_start();
	try { AUN_CB_Popup::ajax_stat(); } catch ( Pop_Done $e ) {}
	return json_decode( ob_get_clean(), true );
}
delete_option( AUN_CB_Popup::STATS );
setup( -3600, 3600 );
$sig = AUN_CB_Popup::signature( AUN_Campaign_Notice_Bar::get_options() );
beacon( 'view', $sig ); beacon( 'view', $sig ); beacon( 'click', $sig ); beacon( 'copy', $sig ); beacon( 'close', $sig );
$st = AUN_CB_Popup::stats_for( $sig );
t( 'counted: 2 shown, 1 tapped, 1 copied, 1 closed', array( $st['view'], $st['click'], $st['copy'], $st['close'] ), array( 2, 1, 1, 1 ) );
beacon( 'view', 'abcdef123456' );
t( 'a made-up campaign id is ignored', AUN_CB_Popup::stats_for( 'abcdef123456' ), null );
beacon( 'view', $sig, 'Googlebot/2.1 (+http://www.google.com/bot.html)' );
t( 'search-engine bots are not counted', AUN_CB_Popup::stats_for( $sig )['view'], 2 );
$j = beacon( 'delete_everything', $sig );
t( 'an unknown event is refused', isset( $j['success'] ) && false === $j['success'], true );
$all = get_option( AUN_CB_Popup::STATS );
for ( $i = 0; $i < 8; $i++ ) { $all[ 'old' . $i ] = array( 'view' => 1, 'click' => 0, 'copy' => 0, 'close' => 0, 'since' => time() - 86400 * ( 30 + $i ) ); }
update_option( AUN_CB_Popup::STATS, $all, false );
beacon( 'view', $sig );
$all = get_option( AUN_CB_Popup::STATS );
t( 'only the 6 most recent campaigns are kept', count( $all ), 6 );
t( '  ...and the current one is among them', isset( $all[ $sig ] ), true );
global $wpdb;
t( 'the counts are not autoloaded on every page', $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", AUN_CB_Popup::STATS ) ) !== 'yes', true );
// Stop pretending to be an AJAX request: render() rightly prints nothing during AJAX.
remove_all_filters( 'wp_doing_ajax' );
$_POST = array(); $_REQUEST = array();

echo "\nK. an order placed means \"used\"\n";
$m = new ReflectionMethod( 'AUN_CB_Popup', 'mark_converted' ); $m->setAccessible( true );
ob_start(); $m->invoke( null ); $mk = ob_get_clean();
t( 'the thank-you page marks this campaign as used', false !== strpos( $mk, 'aunCbPopDone:' . $sig ), true );
t( '  ...in a script WP Rocket will not delay', false !== strpos( $mk, 'aun-cb-pop' ) && false !== strpos( $mk, 'data-no-optimize' ), true );

echo "\nL. WP Rocket\n";
$h  = render_at();
$js = preg_match( '/<script data-no-optimize[^>]*>(.*?)<\/script>/s', $h, $mm ) ? $mm[1] : '';
$ex = apply_filters( 'rocket_delay_js_exclusions', array() );
t( 'the popup script is excluded from "Delay JS" (marker in list)', in_array( 'aun-cb-pop', $ex, true ), true );
t( '  ...and the marker really is inside the script', false !== strpos( $js, 'aun-cb-pop' ), true );
$bar = do_shortcode( '[aun_campaign_notice]' );
$bjs = preg_match( '/<script data-no-optimize[^>]*>(.*?)<\/script>/s', $bar, $bm ) ? $bm[1] : '';
t( 'FIX: the TOP BAR timer now carries its own marker too', false !== strpos( $bjs, 'aun-cb-top' ), true );
// WP Rocket's loader replaces addEventListener and parks click/key/touch listeners until
// its delayed scripts load (= after the visitor's first tap). isRocket:true is let through.
t( 'popup listeners go through the isRocket helper', false !== strpos( $js, 'opts.isRocket = true' ) && 0 === preg_match( '/\b(?:card|copyBtn|document|window|go\[g\]|closers\[c\])\.addEventListener\(/', $js ), true );
t( 'the bar\'s x is attached past WP Rocket too', false !== strpos( $bjs, '{ isRocket: true }' ), true );
t( 'the popup CSS is kept out of Remove Unused CSS', in_array( '.aun-pop', apply_filters( 'rocket_rucss_safelist', array() ), true ), true );
t( 'braces balance in the popup script', substr_count( $js, '{' ), substr_count( $js, '}' ) );
t( 'parens balance in the popup script', substr_count( $js, '(' ), substr_count( $js, ')' ) );

echo "\nM. admin\n";
set_theme_mod( 'html_scripts_footer', '[lightbox auto_open="true" auto_timer="3000" id="newsletter-signup-link"][block id="discount"][/lightbox]' );
wp_set_current_user( 1 );
ob_start(); AUN_CB_Popup::settings_rows( AUN_Campaign_Notice_Bar::get_options() ); $rows = ob_get_clean();
t( 'the old Flatsome lightbox code is spotted and flagged', false !== strpos( $rows, 'The old Flatsome popup is still in place' ), true );
remove_theme_mod( 'html_scripts_footer' );
ob_start(); AUN_CB_Popup::settings_rows( AUN_Campaign_Notice_Bar::get_options() ); $rows = ob_get_clean();
t( '  ...and not flagged once removed', false === strpos( $rows, 'The old Flatsome popup' ), true );
t( 'the stats line shows on the settings page', false !== strpos( $rows, 'This campaign so far' ), true );
t( 'a preview button links to the homepage with the preview flag', false !== strpos( $rows, AUN_CB_Popup::PREVIEW_ARG . '=1' ), true );
t( 'status line: on', AUN_CB_Popup::status_line( AUN_Campaign_Notice_Bar::get_options() ), 'Popup banner: on. Opens after 8 seconds, or at 40% scrolled, or when leaving (computers), whichever comes first.' );
ob_start(); AUN_Campaign_Notice_Bar::settings_page(); $page = ob_get_clean();
$at_popup = strpos( $page, 'Campaign Popup Banner' );
$at_form  = strpos( $page, '</form>' );
t( 'the whole settings page renders with the popup section inside the form',
	array( false !== $at_popup, false !== $at_form && $at_popup < $at_form ), array( true, true ) );
wp_set_current_user( 0 );

delete_option( AUN_Campaign_Notice_Bar::OPTION_KEY );
delete_option( AUN_CB_Popup::STATS );
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
