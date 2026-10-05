<?php
/**
 * Bench test for the replacement-warranty wording (breo-bd-store 2.17) and the
 * self-refreshing policy pages. Snapshots every Breo page it touches and puts
 * the bench back as it found it. Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-warranty.php --skip-themes
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
if ( ! function_exists( 'breo_bd_refresh_pages' ) ) {
	require_once BREO_BD_DIR . 'includes/admin.php';
}
$settings = get_option( 'breo_bd_settings', null );
$set_months = function ( $m ) {
	$o = (array) get_option( 'breo_bd_settings', array() );
	$o['warranty_months'] = $m;
	update_option( 'breo_bd_settings', $o );
};

// ---------- the label ----------
$set_months( '12' );
t( '1-year replacement warranty' === breo_bd_warranty_label(), 'label: 12 months -> "1-year replacement warranty"' );
$set_months( '18' );
t( '18-month replacement warranty' === breo_bd_warranty_label(), 'label: 18 months -> "18-month replacement warranty"' );
$set_months( '' );
t( 'replacement warranty' === breo_bd_warranty_label(), 'label: no period -> "replacement warranty"' );
$set_months( '12' );
$trust = wp_list_pluck( breo_bd_trust_items(), 1 );
t( in_array( '1-year replacement warranty', $trust, true ), 'trust badge says replacement' );
$faq = breo_bd_product_faq( array( 'faq' => array(), 'short_name' => 'Test' ) );
$ans = '';
foreach ( $faq as $f ) {
	if ( 'What warranty do I get?' === $f[0] ) {
		$ans = $f[1];
	}
}
t( false !== strpos( $ans, '1-year replacement warranty' ) && false !== strpos( $ans, 'new one' ), 'product FAQ: replacement answer' );
$defs = require BREO_BD_DIR . 'includes/pages-content.php';
t( false !== strpos( $defs['warranty-policy']['content'], 'new unit of the same model' ) && false === strpos( $defs['warranty-policy']['content'], 'We will repair the device' ), 'policy text: replacement, no repair promise' );
t( false !== strpos( do_shortcode( '[breo_info key="warranty"] replacement warranty' ), '1-year replacement warranty' ), 'policy shortcode renders the period' );
$wp_text = $defs['warranty-policy']['content'];
t( false !== strpos( $wp_text, '<strong>original box</strong>' ) && false !== strpos( $wp_text, 'serial number is printed on the box' ), 'policy: claim needs the original box (serial number)' );
t( false !== strpos( $wp_text, 'Claims without the original box' ), 'policy: no box = not covered' );
t( false !== strpos( $wp_text, 'We replace what you send back' ) && false !== strpos( $wp_text, 'missing accessory is not included' ), 'policy: accessories replaced as returned, claim not refused' );
t( false !== strpos( $defs['faq']['content'], 'Do I need to keep the box?' ), 'FAQ page: keep-the-box question' );
t( false !== strpos( $ans, 'Keep the box' ), 'product FAQ: keep the box' );

// ---------- self-refreshing pages ----------
$slugs = array( 'warranty-policy', 'faq', 'returns-refunds', 'about-breo' );
$snap  = array();
foreach ( $slugs as $s ) {
	$p          = get_page_by_path( $s, OBJECT, 'page' );
	$snap[ $s ] = $p ? array( 'id' => $p->ID, 'content' => $p->post_content, 'excerpt' => $p->post_excerpt, 'hash' => get_post_meta( $p->ID, '_breo_hash', true ), 'ours' => get_post_meta( $p->ID, '_breo_page', true ), 'status' => $p->post_status ) : null;
}
$built_before = get_option( 'breo_bd_pages_built', null );
$made         = array();
foreach ( array( 'warranty-policy', 'faq', 'returns-refunds' ) as $s ) { // fixtures, created only if missing
	if ( ! $snap[ $s ] ) {
		$made[ $s ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $s, 'post_title' => $s, 'post_content' => 'x' ) );
		update_post_meta( $made[ $s ], '_breo_page', $s );
	}
}
$id = function ( $s ) use ( $snap, $made ) {
	return $snap[ $s ] ? $snap[ $s ]['id'] : $made[ $s ];
};
$old = function ( $s, $content ) use ( $id ) { // an untouched page still holding the previous version's text
	wp_update_post( array( 'ID' => $id( $s ), 'post_content' => $content, 'post_excerpt' => 'old intro' ) );
	update_post_meta( $id( $s ), '_breo_page', $s );
	update_post_meta( $id( $s ), '_breo_hash', md5( get_post_field( 'post_content', $id( $s ), 'raw' ) ) );
};
$old( 'warranty-policy', '<p>We will repair the device or, if a repair is not possible, replace it.</p>' );
$old( 'returns-refunds', '<p>Faults that appear later are handled under our Warranty Policy.</p>' );
$old( 'faq', '<p>official warranty</p>' );
wp_update_post( array( 'ID' => $id( 'faq' ), 'post_content' => '<p>official warranty, plus my own note</p>' ) ); // hand edit: hash no longer matches

update_option( 'breo_bd_pages_built', 1 );
$kept = breo_bd_refresh_pages();
$w    = get_post( $id( 'warranty-policy' ) );
t( false !== strpos( $w->post_content, 'new unit of the same model' ), 'unedited Warranty Policy refreshed to the new text' );
t( 0 === strpos( $w->post_excerpt, 'Every Breo device from Breo Bangladesh comes with a replacement warranty' ), '...and its intro' );
t( get_post_meta( $w->ID, '_breo_hash', true ) === md5( $w->post_content ), '...and it still counts as unedited (hash updated)' );
t( false !== strpos( get_post_field( 'post_content', $id( 'returns-refunds' ) ), 'covered by our replacement warranty' ), 'unedited Returns & Refunds refreshed' );
t( '<p>official warranty, plus my own note</p>' === get_post_field( 'post_content', $id( 'faq' ) ), 'hand-edited FAQ left exactly as it was' );
t( in_array( 'Frequently Asked Questions', $kept, true ) && ! in_array( 'Warranty Policy', $kept, true ), 'notice lists the edited page only' );
t( 1 !== (int) get_option( 'breo_bd_pages_built' ), '"Last updated" date moved forward' );
t( get_post_field( 'post_content', $id( 'warranty-policy' ), 'raw' ) === trim( $defs['warranty-policy']['content'] ), 'stored exactly as written (so later runs leave it alone)' );
$mod = get_post_field( 'post_modified_gmt', $id( 'warranty-policy' ) );
sleep( 1 );
t( breo_bd_refresh_pages() === $kept && get_post_field( 'post_modified_gmt', $id( 'warranty-policy' ) ) === $mod, 'second run: same notice, refreshed page not touched again' );

// the admin_init trigger: once per version, never on AJAX
delete_option( 'breo_bd_pages_ver' );
add_filter( 'wp_doing_ajax', '__return_true' );
do_action( 'admin_init' );
remove_filter( 'wp_doing_ajax', '__return_true' );
t( false === get_option( 'breo_bd_pages_ver' ), 'AJAX request: refresh not triggered' );
wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
do_action( 'admin_init' );
t( BREO_BD_PAGES_VER === get_option( 'breo_bd_pages_ver' ), 'admin dashboard load: refresh ran and version recorded' );
t( in_array( 'Frequently Asked Questions', (array) get_transient( 'breo_bd_pages_kept' ), true ), 'edited-page notice queued' );
ob_start();
do_action( 'admin_notices' );
t( false !== strpos( ob_get_clean(), 'Frequently Asked Questions' ), 'notice shows in the dashboard' );

// ---------- restore ----------
delete_transient( 'breo_bd_pages_kept' );
foreach ( $made as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( $snap as $s => $v ) {
	if ( ! $v ) {
		continue;
	}
	wp_update_post( array( 'ID' => $v['id'], 'post_content' => $v['content'], 'post_excerpt' => $v['excerpt'], 'post_status' => $v['status'] ) );
	'' === $v['hash'] ? delete_post_meta( $v['id'], '_breo_hash' ) : update_post_meta( $v['id'], '_breo_hash', $v['hash'] );
	'' === $v['ours'] ? delete_post_meta( $v['id'], '_breo_page' ) : update_post_meta( $v['id'], '_breo_page', $v['ours'] );
}
null === $built_before ? delete_option( 'breo_bd_pages_built' ) : update_option( 'breo_bd_pages_built', $built_before );
null === $settings ? delete_option( 'breo_bd_settings' ) : update_option( 'breo_bd_settings', $settings );
$ok = true;
foreach ( $snap as $s => $v ) {
	if ( $v && get_post_field( 'post_content', $v['id'] ) !== $v['content'] ) {
		$ok = false;
	}
}
t( $ok && ! array_filter( array_map( 'get_post', $made ) ), 'bench pages restored' );
echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
