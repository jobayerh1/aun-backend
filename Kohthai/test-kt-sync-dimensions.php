<?php
/**
 * Harness for kt-sync-dimensions.php — run on the local WP+WooCommerce bench:
 *
 *   cd ~/wp-local && php -c php.ini wp-cli.phar --path=site \
 *     eval-file "C:/Users/Jobayer Hossain/Downloads/Claude session/Kohthai/test-kt-sync-dimensions.php" --skip-themes
 *
 * Builds products that reproduce every real case found on kohthaibd.com, runs
 * the sync in dry-run then apply, and asserts the stored dimensions.
 *
 * NOTE: wp eval-file runs this inside a function, so file-level variables are
 * NOT globals — tallies go through a static.
 */

define( 'KTSD_TEST', true );
require_once __DIR__ . '/kt-sync-dimensions.php';

if ( ! function_exists( 'wc_get_product' ) ) {
	echo "WooCommerce is not active on the bench. Activate it and re-run.\n";
	return;
}

function ktst_tally( $ok = null ) {
	static $pass = 0, $fail = 0;
	if ( null === $ok ) {
		return array( $pass, $fail );
	}
	$ok ? $pass++ : $fail++;
	return null;
}

function ktst_is( $label, $actual, $expected ) {
	$ok = ( (string) $actual === (string) $expected );
	ktst_tally( $ok );
	printf( "%-4s %-58s got %-14s want %s\n", $ok ? 'PASS' : 'FAIL', $label, '"' . $actual . '"', '"' . $expected . '"' );
}

function ktst_true( $label, $cond ) {
	ktst_tally( (bool) $cond );
	printf( "%-4s %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** Build the [kt_details] block exactly as the live pages carry it. */
function ktst_details( $len, $hei, $wid, $weight = '' ) {
	$s = '[kt_details length="' . $len . '" height="' . $hei . '" width="' . $wid . '"';
	if ( '' !== $weight ) {
		$s .= ' weight="' . $weight . '"';
	}
	$s .= ' fits="phone" notes="Fits a phone, wallet and keys | Size may vary slightly" material="PU leather" care="Wipe with a damp cloth"]';
	return $s;
}

/**
 * @param string $desc  post_content (may hold a [kt_details] block, or not)
 * @param array  $dims  starting Woo dimensions: length, width, height
 */
function ktst_make( $name, $desc, $dims, $weight = '' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_description( $desc );
	$p->set_status( 'publish' );
	if ( isset( $dims[0] ) ) { $p->set_length( $dims[0] ); }
	if ( isset( $dims[1] ) ) { $p->set_width( $dims[1] ); }
	if ( isset( $dims[2] ) ) { $p->set_height( $dims[2] ); }
	if ( '' !== $weight ) { $p->set_weight( $weight ); }
	return $p->save();
}

echo "=== kt-sync-dimensions harness ===\n\n";

/* ---- clear any products already on the bench so tallies are clean ------- */
$existing = get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
foreach ( $existing as $eid ) {
	wp_delete_post( $eid, true );
}

/* ---------------------------- unit: ktsd_num ---------------------------- */
echo "--- ktsd_num() ---\n";
$n = null;
ktst_is( 'plain "27 cm"', ktsd_num( '27 cm', $n ), 27 );
ktst_is( 'decimal "6.5 cm"', ktsd_num( '6.5 cm', $n ), 6.5 );
ktst_is( 'no unit "18"', ktsd_num( '18', $n ), 18 );
$n = null;
ktst_is( 'range "31-37 cm" takes larger', ktsd_num( '31-37 cm', $n ), 37 );
ktst_true( 'range sets a note', ! empty( $n ) );
$n = null;
ktst_is( 'en-dash range "22–27 cm"', ktsd_num( "22\xe2\x80\x9327 cm", $n ), 27 );
ktst_true( 'en-dash range sets a note', ! empty( $n ) );
ktst_is( 'empty string', var_export( ktsd_num( '', $n ), true ), 'NULL' );
ktst_is( 'unit only "cm"', var_export( ktsd_num( 'cm', $n ), true ), 'NULL' );
ktst_is( 'grams "490 g"', ktsd_num( '490 g', $n ), 490 );

echo "\n--- ktsd_show() ---\n";
ktst_is( 'whole number', ktsd_show( 27 ), '27' );
ktst_is( 'decimal kept', ktsd_show( 6.5 ), '6.5' );
ktst_is( 'trailing zero trimmed', ktsd_show( '12.50' ), '12.5' );
ktst_is( 'round number not eaten', ktsd_show( 100 ), '100' );
ktst_is( 'empty becomes dash', ktsd_show( '' ), '-' );

/* ------------------------- the real-world cases ------------------------- */
echo "\n--- building products that mirror kohthaibd.com ---\n";

// A: transposed — the majority case (Versatile Soft PU: page 27 across / 22 tall / 8 deep)
// Its Woo weight is deliberately wrong too, to prove weight is reported but never written.
$a = ktst_make( 'A Transposed Shoulder Bag', ktst_details( '27 cm', '22 cm', '8 cm', '490 g' ), array( 27, 22, 8 ), 0.75 );
// B: already correct (Retro Artistic)
$b = ktst_make( 'B Already Correct Crossbody', ktst_details( '21 cm', '16 cm', '9 cm', '500 g' ), array( 21, 9, 16 ), 0.50 );
// C: decimal depth (Round Pleated Clutch)
$c = ktst_make( 'C Decimal Depth Clutch', ktst_details( '18 cm', '18 cm', '6.5 cm' ), array( 18, 18, 6.5 ) );
// D: width stated as a range (Trendy Tote)
$d = ktst_make( 'D Range Width Tote', ktst_details( '31-37 cm', '23 cm', '15 cm' ), array( 37, 23, 15 ) );
// E: Woo carries a DIFFERENT product's numbers (Soft PU Shoulder)
$e = ktst_make( 'E Wrong Record Shoulder', ktst_details( '28 cm', '18 cm', '10 cm' ), array( 30, 21, 13 ) );
// F: no size block at all — must be reported, never guessed
$f = ktst_make( 'F No Details Block', '<p>Just a description, no size block.</p>', array( 20, 12, 5 ) );
// G: absurd number — must be refused
$g = ktst_make( 'G Absurd Value', ktst_details( '2700 cm', '22 cm', '8 cm' ), array( 27, 22, 8 ) );
// H: shortcode living in postmeta (a page-builder field) rather than post_content
$h = ktst_make( 'H Details In Meta', '<p>No shortcode here.</p>', array( 26, 16, 9 ) );
update_post_meta( $h, '_some_builder_field', 'intro ' . ktst_details( '26 cm', '16 cm', '9 cm' ) . ' outro' );

// I: variable product whose variation carries its own dimensions
$vp = new WC_Product_Variable();
$vp->set_name( 'I Variable With Override' );
$vp->set_description( ktst_details( '24 cm', '17 cm', '10 cm' ) );
$vp->set_status( 'publish' );
$vp->set_length( 24 ); $vp->set_width( 17 ); $vp->set_height( 10 );
$attr = new WC_Product_Attribute();
$attr->set_name( 'Colour' );
$attr->set_options( array( 'Black', 'Tan' ) );
$attr->set_visible( true );
$attr->set_variation( true );
$vp->set_attributes( array( $attr ) );
$i = $vp->save();
$var = new WC_Product_Variation();
$var->set_parent_id( $i );
$var->set_attributes( array( 'colour' => 'Black' ) );
$var->set_length( 99 );   // the override that would silently beat the parent
$var->save();

echo "created 9 products\n";

/* ------------------------------ dry run --------------------------------- */
echo "\n--- DRY RUN (must write nothing) ---\n";
$dry = ktsd_run( false, false );

ktst_is( 'dry: would change 6', $dry['changed'], 6 );   // A C D E G? no — G is skipped. A,C,D,E,H + I = 6
ktst_is( 'dry: already correct 1', $dry['same'], 1 );   // B
ktst_is( 'dry: missing block 1', count( $dry['missing'] ), 1 );
ktst_is( 'dry: skipped 1', count( $dry['skipped'] ), 1 );
ktst_is( 'dry: range noted 1', count( $dry['notes'] ), 1 );
ktst_is( 'dry: variation override 1', count( $dry['varover'] ), 1 );

$pa = wc_get_product( $a );
ktst_is( 'dry left A length alone', ktsd_show( $pa->get_length() ), '27' );
ktst_is( 'dry left A width alone', ktsd_show( $pa->get_width() ), '22' );
ktst_is( 'dry left A height alone', ktsd_show( $pa->get_height() ), '8' );

/* -------------------------------- apply --------------------------------- */
echo "\n--- APPLY ---\n";
$run = ktsd_run( true, false );
ktst_is( 'apply: changed 6', $run['changed'], 6 );

$check = function ( $label, $id, $l, $w, $hh ) {
	$p = wc_get_product( $id );
	ktst_is( $label . ' length (across)', ktsd_show( $p->get_length() ), $l );
	ktst_is( $label . ' width (depth)', ktsd_show( $p->get_width() ), $w );
	ktst_is( $label . ' height (tall)', ktsd_show( $p->get_height() ), $hh );
};

$check( 'A transposed ->', $a, '27', '8', '22' );
$check( 'B untouched ->', $b, '21', '9', '16' );
$check( 'C decimal ->', $c, '18', '6.5', '18' );
$check( 'D range ->', $d, '37', '15', '23' );
$check( 'E wrong record ->', $e, '28', '10', '18' );
$check( 'F no block, untouched ->', $f, '20', '12', '5' );
$check( 'G absurd, untouched ->', $g, '27', '22', '8' );
$check( 'H from postmeta ->', $h, '26', '9', '16' );
$check( 'I variable parent ->', $i, '24', '10', '17' );

$pa = wc_get_product( $a );
ktst_is( 'A weight NOT changed', ktsd_show( $pa->get_weight() ), '0.75' );
ktst_true( 'weight difference was reported', count( $run['weights'] ) >= 1 );

/* ------------------------- idempotence: run again ----------------------- */
echo "\n--- RE-RUN (must be a no-op) ---\n";
$again = ktsd_run( true, false );
ktst_is( 'second run changes nothing', $again['changed'], 0 );
// A B C D E H I are counted; F has no block and G is refused, so neither counts.
ktst_is( 'second run sees 7 already correct', $again['same'], 7 );

/* -------------------------------- cleanup -------------------------------- */
foreach ( array( $a, $b, $c, $d, $e, $f, $g, $h, $i ) as $id ) {
	$p = wc_get_product( $id );
	if ( $p && $p->is_type( 'variable' ) ) {
		foreach ( $p->get_children() as $cid ) {
			wp_delete_post( $cid, true );
		}
	}
	wp_delete_post( $id, true );
}

list( $pass, $fail ) = ktst_tally();
echo "\n================================\n";
echo "  {$pass} passed / {$fail} failed\n";
echo "================================\n";
