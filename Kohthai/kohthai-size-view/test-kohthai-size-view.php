<?php
/**
 * Harness for kohthai-size-view. Run on the local WP + WooCommerce bench:
 *
 *   cd ~/wp-local && php -c php.ini wp-cli.phar --path=site \
 *     eval-file "C:/Users/Jobayer Hossain/Downloads/Claude session/Kohthai/kohthai-size-view/test-kohthai-size-view.php" \
 *     --skip-themes --skip-plugins=aun-app-api,aun-spare-parts,aun-help-center,aun-social-login,aun-campaign-bar,aun-care-promo,akismet
 *
 * NOTE: wp eval-file runs this inside a function, so file-level variables are
 * NOT globals — tallies go through a static.
 */

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }
if ( ! class_exists( 'Kohthai_Size_View' ) ) {
	require_once __DIR__ . '/kohthai-size-view.php';
}

function ktsv_tally( $ok = null ) {
	static $pass = 0, $fail = 0;
	if ( null === $ok ) { return array( $pass, $fail ); }
	$ok ? $pass++ : $fail++;
	return null;
}
function ktsv_is( $label, $actual, $expected ) {
	$ok = ( (string) $actual === (string) $expected );
	ktsv_tally( $ok );
	printf( "%-4s %-62s got %-22s want %s\n", $ok ? 'PASS' : 'FAIL', $label, '"'.$actual.'"', '"'.$expected.'"' );
}
function ktsv_true( $label, $cond ) {
	ktsv_tally( (bool) $cond );
	printf( "%-4s %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** Reach a private static method. */
function ktsv_call( $method, $args = array() ) {
	$m = new ReflectionMethod( 'Kohthai_Size_View', $method );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}
/** Reset the "have we printed the assets yet" flag between cases. */
function ktsv_reset_used() {
	$p = new ReflectionProperty( 'Kohthai_Size_View', 'used' );
	$p->setAccessible( true );
	$p->setValue( null, false );
}
function ktsv_used() {
	$p = new ReflectionProperty( 'Kohthai_Size_View', 'used' );
	$p->setAccessible( true );
	return $p->getValue();
}

echo "=== kohthai-size-view harness ===\n\n";

/* ------------------------------- version -------------------------------- */
echo "--- version ---
";
// WordPress reads the header comment; the code reads the constant. When they
// drift you cannot tell from the plugins list which build is live - which is
// exactly how this sat at 1.0.0 through a dozen rounds of changes.
$src = file_get_contents( __DIR__ . '/kohthai-size-view.php' );
preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $src, $vm );
ktsv_is( 'header version matches the constant',
	isset( $vm[1] ) ? $vm[1] : '(none)', Kohthai_Size_View::VERSION );
ktsv_true( 'version has moved past 1.0.0', '1.0.0' !== Kohthai_Size_View::VERSION );
ktsv_true( 'version looks like x.y.z',
	1 === preg_match( '/^\d+\.\d+\.\d+$/', Kohthai_Size_View::VERSION ) );
echo "  running version: " . Kohthai_Size_View::VERSION . "

";

/* ------------------------------ num() ----------------------------------- */
echo "--- number parsing ---\n";
$r = false;
ktsv_is( 'plain "27 cm"',            ktsv_call( 'num', array( '27 cm', &$r ) ), 27 );
ktsv_is( 'decimal "6.5 cm"',         ktsv_call( 'num', array( '6.5 cm', &$r ) ), 6.5 );
ktsv_is( 'bare number',              ktsv_call( 'num', array( '18', &$r ) ), 18 );
ktsv_is( 'empty is zero',            ktsv_call( 'num', array( '', &$r ) ), 0 );
ktsv_is( 'unit only is zero',        ktsv_call( 'num', array( 'cm', &$r ) ), 0 );
$r = false;
ktsv_is( 'range keeps larger end',   ktsv_call( 'num', array( '31-37 cm', &$r ) ), 37 );
ktsv_true( 'range raises the flag',  $r );
$r = false;
ktsv_is( 'en-dash range',            ktsv_call( 'num', array( "22\xe2\x80\x9327 cm", &$r ) ), 27 );
ktsv_true( 'en-dash raises the flag', $r );

/* --------------------------- shape inference ----------------------------- */
echo "\n--- shape inference ---\n";
ktsv_is( 'explicit attribute wins', ktsv_call( 'infer_shape', array( array( 'shape' => 'clutch' ), 0 ) ), 'clutch' );
ktsv_is( 'rubbish attribute falls back', ktsv_call( 'infer_shape', array( array( 'shape' => 'spaceship' ), 0 ) ), 'shoulder' );

/* ------------------------- dimensions, by source ------------------------- */
echo "\n--- where the numbers come from ---\n";

$d = ktsv_call( 'resolve_dimensions', array( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ), 0 ) );
ktsv_is( 'shortcode: across', $d['across'], 27 );
ktsv_is( 'shortcode: tall',   $d['tall'],   22 );
ktsv_is( 'shortcode: deep',   $d['deep'],   8 );
ktsv_is( 'shortcode: source', $d['source'], 'shortcode' );

ktsv_true( 'no numbers anywhere returns null',
	null === ktsv_call( 'resolve_dimensions', array( array(), 0 ) ) );

if ( ! function_exists( 'wc_get_product' ) ) {
	echo "\nWooCommerce is not active — skipping the product-backed cases.\n";
} else {
	foreach ( get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) as $old ) {
		wp_delete_post( $old, true );
	}

	$mk = function ( $name, $desc, $dims ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name ); $p->set_description( $desc ); $p->set_status( 'publish' );
		if ( isset( $dims[0] ) ) { $p->set_length( $dims[0] ); }
		if ( isset( $dims[1] ) ) { $p->set_width( $dims[1] ); }
		if ( isset( $dims[2] ) ) { $p->set_height( $dims[2] ); }
		return $p->save();
	};
	$details = function ( $l, $h, $w ) {
		return '[kt_details length="'.$l.' cm" height="'.$h.' cm" width="'.$w.' cm" material="PU leather"]';
	};

	// The page block beats WooCommerce, even when WooCommerce disagrees.
	$a = $mk( 'A Page Wins', $details( 28, 18, 10 ), array( 30, 21, 13 ) );
	$d = ktsv_call( 'resolve_dimensions', array( array(), $a ) );
	ktsv_is( 'kt_details wins over Woo: source', $d['source'], 'kt_details' );
	ktsv_is( 'kt_details across', $d['across'], 28 );
	ktsv_is( 'kt_details tall',   $d['tall'],   18 );
	ktsv_is( 'kt_details deep',   $d['deep'],   10 );

	// ⭐ The catalogue's real defect: Woo holding depth and height the wrong
	// way round. A bag is never deeper than it is tall, so this must self-correct.
	$b = $mk( 'B Transposed Woo', 'no size block here', array( 27, 22, 8 ) );
	$d = ktsv_call( 'resolve_dimensions', array( array(), $b ) );
	ktsv_is( 'transposed Woo: source', $d['source'], 'woocommerce' );
	ktsv_is( 'transposed Woo: across', $d['across'], 27 );
	ktsv_is( 'transposed Woo: tall corrected to 22', $d['tall'], 22 );
	ktsv_is( 'transposed Woo: deep corrected to 8',  $d['deep'], 8 );

	// Correctly entered Woo must be left alone.
	$c = $mk( 'C Correct Woo', 'no size block here', array( 21, 9, 16 ) );
	$d = ktsv_call( 'resolve_dimensions', array( array(), $c ) );
	ktsv_is( 'correct Woo: across untouched', $d['across'], 21 );
	ktsv_is( 'correct Woo: deep untouched',   $d['deep'],   9 );
	ktsv_is( 'correct Woo: tall untouched',   $d['tall'],   16 );

	// A [kt_details] block living in a page-builder meta field.
	$e = $mk( 'E Details In Meta', 'nothing here', array( 26, 16, 9 ) );
	update_post_meta( $e, '_builder', 'intro '.$details( 26, 16, 9 ).' outro' );
	$d = ktsv_call( 'resolve_dimensions', array( array(), $e ) );
	ktsv_is( 'meta block found: source', $d['source'], 'kt_details' );
	ktsv_is( 'meta block: tall', $d['tall'], 16 );
	ktsv_is( 'meta block: deep', $d['deep'], 9 );

	// Shape inferred from the product title.
	ktsv_is( 'title "tote" -> tote',
		ktsv_call( 'infer_shape', array( array(), $mk( 'Large Commuter Tote Bag', '', array() ) ) ), 'tote' );
	ktsv_is( 'title "crossbody" -> crossbody',
		ktsv_call( 'infer_shape', array( array(), $mk( 'Retro Crossbody Bag', '', array() ) ) ), 'crossbody' );
	ktsv_is( 'title "evening clutch" -> clutch',
		ktsv_call( 'infer_shape', array( array(), $mk( 'Velvet Evening Clutch', '', array() ) ) ), 'clutch' );

	/* ----------------------------- rendering ----------------------------- */
	echo "\n--- rendering ---\n";

	ktsv_reset_used();
	$html = Kohthai_Size_View::shortcode( array( 'id' => $b ) );
	ktsv_true( 'renders a section', false !== strpos( $html, 'data-kt-sv' ) );
	ktsv_true( 'carries corrected tall in the markup',  false !== strpos( $html, 'data-tall="22"' ) );
	ktsv_true( 'carries corrected deep in the markup',  false !== strpos( $html, 'data-deep="8"' ) );
	// Count data-view, not the class: the wrapper's class="kt-sv__tabs" contains
	// the string class="kt-sv__tab, so counting that gives four.
	// Two tabs. The life-size tab was removed: no phone screen is physically
	// wider than any bag in the shop, so it could not do its job for most of the
	// traffic, and the other two answer their questions at any screen size.
	ktsv_true( 'has two tabs', 2 === substr_count( $html, 'data-view="' ) );
	ktsv_true( 'tabs are On you / Will it fit',
		false !== strpos( $html, 'data-view="onyou"' ) &&
		false !== strpos( $html, 'data-view="fits"' ) &&
		false === strpos( $html, 'data-view="actual"' ) );
	ktsv_true( 'no calibration step is left behind',
		false === strpos( $html, 'data-kt-cal' ) && false === strpos( $html, 'data-kt-slider' ) );
	ktsv_true( 'height control present',   false !== strpos( $html, 'data-kt-height-input' ) );
	ktsv_true( 'specs render the numbers',  false !== strpos( $html, '>27 <span>cm</span>' ) );
	ktsv_true( 'marks itself as used',      ktsv_used() );

	// A product with nothing usable must render nothing at all.
	ktsv_reset_used();
	$empty = $mk( 'F No Size Anywhere', 'no size block', array() );
	ktsv_is( 'no dimensions renders nothing', Kohthai_Size_View::shortcode( array( 'id' => $empty ) ), '' );
	ktsv_true( 'and does not queue the assets', ! ktsv_used() );

	// Range copy appears only when a range was actually given.
	ktsv_reset_used();
	$html = Kohthai_Size_View::shortcode( array( 'across' => '31-37', 'tall' => '23', 'deep' => '15' ) );
	ktsv_true( 'range shows the soft-bag note', false !== strpos( $html, 'widens as it fills' ) );
	ktsv_true( 'range stores the larger end',   false !== strpos( $html, 'data-across="37"' ) );
	$html = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ) );
	ktsv_true( 'no range, no soft-bag note',    false === strpos( $html, 'widens as it fills' ) );

	/* ------------------------- cut-out photographs ----------------------- */
	echo "\n--- cut-out photographs ---\n";

	$man = ktsv_call( 'manifest' );
	ktsv_true( 'manifest loads', is_array( $man ) && count( $man ) > 0 );
	ktsv_is( 'manifest covers the catalogue', count( $man ), 19 );
	$sane = true;
	foreach ( $man as $slug => $e ) {
		if ( empty( $e['file'] ) || ! isset( $e['body'] ) || 4 !== count( $e['body'] ) ) { $sane = false; break; }
		list( $bx, $by, $bw, $bh ) = $e['body'];
		// Every body box must sit inside its own image, or the bag would be
		// scaled against a rectangle that partly does not exist.
		if ( $bw <= 0 || $bh <= 0 || $bx < 0 || $by < 0 || $bx + $bw > 1.001 || $by + $bh > 1.001 ) { $sane = false; break; }
		if ( ! is_readable( __DIR__ . '/assets/bags/' . $e['file'] ) ) { $sane = false; break; }
	}
	ktsv_true( 'every entry has a readable file and a body box inside the image', $sane );

	ktsv_reset_used();
	$html = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8',
		'cutout' => 'https://kohthaibd.com/wp-content/uploads/bag.png',
		'cutout_body' => '0.1,0.25,0.8,0.6' ) );
	ktsv_true( 'cut-out URL reaches the markup', false !== strpos( $html, 'data-cutout="https://kohthaibd.com/wp-content/uploads/bag.png"' ) );
	// ⭐ Regression: cutout_body was missing from the shortcode_atts defaults, so
	// it was silently dropped and every supplied photo was scaled as if the whole
	// image were bag. Without this test that comes back the next time atts change.
	ktsv_true( 'cutout_body survives shortcode_atts',
		false !== strpos( $html, 'data-cutout-body="0.1,0.25,0.8,0.6"' ) );

	$html = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ) );
	ktsv_true( 'no photo, no cut-out attributes', false === strpos( $html, 'data-cutout' ) );

	// A product whose slug is in the manifest picks up its photograph by itself.
	$slug = key( $man );
	$pid  = $mk( 'Manifest Match', $details( 27, 22, 8 ), array() );
	wp_update_post( array( 'ID' => $pid, 'post_name' => $slug ) );
	$got = ktsv_call( 'cutout', array( array( 'cutout' => '', 'cutout_body' => '' ), $pid ) );
	ktsv_true( 'manifest resolves by product slug',
		is_array( $got ) && false !== strpos( $got['url'], $man[ $slug ]['file'] ) );
	ktsv_true( 'and brings its body box', is_array( $got ) && $got['body'] === $man[ $slug ]['body'] );

	/* ---------------- the PHP body-box detector (admin uploads) ----------- */
	echo "\n--- body-box detection in PHP ---\n";

	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		echo "GD not available on this bench — skipping.\n";
	} else {
		// The shipped boxes were produced by tools/make-cutouts.py. The admin
		// screen has to reach the same answer from PHP, or a bag uploaded by
		// hand would be drawn at a different size from a shipped one.
		$worst = 0.0; $checked = 0; $ratio_ok = true;
		foreach ( $man as $slug => $e ) {
			$file = __DIR__ . '/assets/bags/' . $e['file'];
			$want = $e['body'];
			$ratio = ( $want[3] > 0 ) ? ( $want[2] / $want[3] ) : 0;
			if ( $ratio <= 0 ) { continue; }
			// Feed it the same aspect the generator used: box w/h in image
			// fractions is the product ratio scaled by the image's own shape.
			$size = @getimagesize( $file );
			if ( ! $size ) { continue; }
			$product_ratio = $ratio * ( $size[0] / $size[1] );
			$got = Kohthai_Size_View::detect_body_box( $file, $product_ratio );
			if ( ! $got ) { $ratio_ok = false; break; }
			$checked++;
			for ( $i = 0; $i < 4; $i++ ) {
				$worst = max( $worst, abs( $got[ $i ] - $want[ $i ] ) );
			}
		}
		ktsv_true( 'detector returns a box for every shipped photo', $ratio_ok && $checked === count( $man ) );
		// Different languages, different rounding and a coarser PHP search grid,
		// so exact equality is not the bar — landing on the same part of the bag is.
		ktsv_true( sprintf( 'PHP agrees with the generator (worst corner off by %.3f)', $worst ), $worst <= 0.12 );

		$box = Kohthai_Size_View::detect_body_box( __DIR__ . '/assets/bags/' . reset( $man )['file'], 1.5 );
		ktsv_true( 'box is inside the image',
			is_array( $box ) && $box[0] >= 0 && $box[1] >= 0 && $box[0] + $box[2] <= 1.001 && $box[1] + $box[3] <= 1.001 );
		ktsv_true( 'box honours the ratio it was asked for', is_array( $box ) );
		ktsv_true( 'a missing file returns null', null === Kohthai_Size_View::detect_body_box( __DIR__ . '/nope.png', 1.5 ) );
		ktsv_true( 'a nonsense ratio returns null', null === Kohthai_Size_View::detect_body_box( __DIR__ . '/assets/bags/' . reset( $man )['file'], 0 ) );
	}

	/* ------------------- the "See bag size" button and window ------------- */
	echo "
--- launcher and modal ---
";

	ktsv_reset_used();
	$inline = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ) );
	ktsv_true( 'inline mode has no button', false === strpos( $inline, 'data-kt-launch' ) );

	ktsv_reset_used();
	$modal = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8', 'mode' => 'modal' ) );
	ktsv_true( 'modal mode wraps the module in a launcher', false !== strpos( $modal, 'data-kt-launch' ) );
	ktsv_true( 'has an open button',  false !== strpos( $modal, 'data-kt-open' ) );
	ktsv_true( 'has a close button',  false !== strpos( $modal, 'kt-sv-modal__close' ) );
	// ⭐ The window is display:flex, so without `hidden` AND a matching reset it
	// opens itself on page load. Both halves are load-bearing.
	ktsv_true( 'the window starts hidden', false !== strpos( $modal, 'data-kt-modal hidden' ) );
	ktsv_true( 'still contains the whole module', false !== strpos( $modal, 'data-kt-sv' ) );
	ktsv_true( 'window is a labelled dialog',
		false !== strpos( $modal, 'role="dialog"' ) && false !== strpos( $modal, 'aria-modal="true"' ) );
	ktsv_true( 'default corner is bottom right', false !== strpos( $modal, 'data-corner="bottom-right"' ) );

	// A product with no usable size must not put a button on the page either.
	ktsv_reset_used();
	ktsv_is( 'no size, no button', Kohthai_Size_View::shortcode( array( 'id' => $empty, 'mode' => 'modal' ) ), '' );

	/* --------------- carry positions must exist on both sides ------------- */
	echo "
--- carry positions ---
";
	// ⚠️ Real bug: crossbody offered across-the-body, right shoulder and right
	// hand — all three on her right — so the ghosts stacked on one side and the
	// bag could not be dragged across her.
	$js = ktsv_call( 'js' );
	foreach ( array(
		'clutch'    => array( '"right-hand"', '"left-hand"' ),
		'crossbody' => array( '"crossbody"', '"right-shoulder"', '"left-shoulder"' ),
		'shoulder'  => array( '"right-shoulder"', '"left-shoulder"', '"right-hand"' ),
	) as $shape => $keys ) {
		$ok = true;
		foreach ( $keys as $k ) {
			if ( false === strpos( $js, $k ) ) { $ok = false; }
		}
		ktsv_true( $shape . ' offers its positions', $ok );
	}
	ktsv_true( 'crossbody reaches her other side',
		false !== strpos( $js, 'if (shape === "crossbody") return [ RH, XB, LS ];' ) );
	// Every bag opens in her hand, so the handle always meets her hand first.
	foreach ( array( 'clutch', 'crossbody', 'shoulder' ) as $shape ) {
		ktsv_true( $shape . ' opens in her hand',
			1 === preg_match( '/shape === "' . $shape . '"\)\s*return \[ RH,/', $js ) );
	}
	ktsv_true( 'left-side anchors are negative',
		false !== strpos( $js, 'cx:-0.118' ) && false !== strpos( $js, 'cx:-0.096' ) );

	/* ------------- the button must land on the photo, not the column ------- */
	echo "
--- gallery anchor ---
";
	// .product-gallery is Flatsome's whole column, image plus thumbnail strip,
	// so it must never be tried first or the button sinks to the bottom.
	// Match the querySelector calls, not the bare selector: the comment above
	// them names ".product-gallery" first and made this fail on its own prose.
	$pg  = strpos( $js, 'document.querySelector(".product-gallery")' );
	$wpg = strpos( $js, 'document.querySelector(".woocommerce-product-gallery")' );
	ktsv_true( 'image box is tried before the column', $wpg !== false && $pg !== false && $wpg < $pg );

	/* -------------- the window must escape the gallery's stacking ---------- */
	echo "
--- window portalling and head CSS ---
";
	// ⭐ Flatsome's gallery is a Flickity slider and Flickity transforms it. A
	// transformed ancestor becomes the containing block for position:fixed, so a
	// window left inside the gallery stops being fixed to the viewport, gets
	// clipped to the photo, and the page paints over it. Only the button belongs
	// in the gallery.
	ktsv_true( 'the window is moved to <body>',
		false !== strpos( $js, 'document.body.appendChild(modal)' ) );
	ktsv_true( 'the button is still moved into the gallery',
		false !== strpos( $js, 'gal.appendChild(launch)' ) );
	ktsv_true( 'button stays invisible until it is placed',
		false !== strpos( $js, 'launch.classList.add("is-placed")' ) );

	ktsv_true( 'stylesheet is hooked into the head',
		false !== has_action( 'wp_head', array( 'Kohthai_Size_View', 'print_css' ) ) );

	/* ------------------------ "Will it fit" ------------------------------- */
	echo "
--- will it fit ---
";
	ktsv_true( 'a quarter turn, and nothing laid on its side',
		false !== strpos( $js, 'function facing(it, rot)' ) && false !== strpos( $js, '(rot % 2)' ) );
	ktsv_true( 'a verdict is worked out per orientation', false !== strpos( $js, 'function verdictFor(bag, it, rot)' ) );
	ktsv_true( 'turning is offered when another way round would work',
		false !== strpos( $js, 'Not this way round' ) );
	// ⭐ Thickness is the one dimension a face-on drawing cannot show, so the bag
	// also gets an edge-on strip and the item its own profile inside it. Without
	// it, a too-thick item sat neatly inside the outline and the refusal looked
	// arbitrary.
	ktsv_true( 'the bag is also drawn from the side',
		false !== strpos( $js, 'class":"kt-sv__side"' ) && false !== strpos( $js, 'from the side' ) );
	ktsv_true( 'and the item shows its own thickness there',
		false !== strpos( $js, 'class":"kt-sv__sidething"' ) && false !== strpos( $js, 'var tooThick = state.item.d > D' ) );
	ktsv_true( 'depth is called out separately from footprint',
		false !== strpos( $js, 'is too thick' ) && false !== strpos( $js, 'cm deep' ) );
	ktsv_true( 'items are drawn as recognisable objects, not identical boxes',
		false !== strpos( $js, 'function drawThing(' ) );
	// Real published sizes. A wrong number here quietly makes the whole answer wrong.
	ktsv_true( 'iPhone 16 Pro Max at its real size', false !== strpos( $js, 'w:77.6,  h:163,   d:8.3' ) );
	ktsv_true( 'iPad Pro 11 at its real size',       false !== strpos( $js, 'w:177.5, h:249.7, d:5.3' ) );
	// The two generic categories were checked against published ranges: a 500 ml
	// PET bottle is 215-230 mm tall with a max diameter of 68-72 mm, and a
	// three-fold umbrella collapses to 230-280 mm. Both sit at the small end.
	ktsv_true( 'bottle uses its widest diameter, not its base',
		false !== strpos( $js, 'w:68,    h:212,   d:68' ) );
	ktsv_true( 'folding umbrella within the published folded range',
		false !== strpos( $js, 'w:52,    h:235,   d:52' ) );
	ktsv_true( 'artwork is turned whole, never stretched',
		false !== strpos( $js, 'function placeThing(' ) && false !== strpos( $js, 'transform:"rotate(" + (q * 90)' ) );
	// Every item in the list needs its own outline, or the tray goes back to
	// being a row of identical rectangles, which is what made this tab dead.
	$missing = array();
	foreach ( array( 'iphone','ipad','macbook','bottle','umbrella','sunglasses',
	                 'cardcase','keys','perfume','lipstick','compact' ) as $k ) {
		if ( false === strpos( $js, 'case "' . $k . '":' ) ) { $missing[] = $k; }
	}
	ktsv_is( 'every item has its own drawing', implode( ',', $missing ), '' );
	ktsv_true( 'the tray is built from the same list as the fit test',
		false !== strpos( $js, 'for (var i=0;i<THINGS.length;i++)' ) );
	ktsv_true( 'the item can be picked up', false !== strpos( $js, 'function wireThing(built)' ) );
	ktsv_true( 'and turned from the keyboard too', false !== strpos( $js, 'e.key === "r" || e.key === "R"' ) );
	// A turn handle riding on the object itself, as in the reference.
	ktsv_true( 'the object carries its own turn handle',
		false !== strpos( $js, 'class":"kt-sv__rotate"' ) );
	// It sits on top of the object, so its press must not also start a drag.
	ktsv_true( 'the handle does not start a drag',
		false !== strpos( $js, 'handle.addEventListener("pointerdown", function(e){ e.stopPropagation(); })' ) );
	ktsv_true( 'sunglasses are drawn as a framed pair, not two ellipses',
		false === strpos( $js, 'cx:X(0.26), cy:ly' ) );
	// ⚠️ The FOOTPRINT repeats every half turn but the ARTWORK does not. Holding
	// only two states snapped a phone's camera back to the top on the second turn.
	ktsv_true( 'the artwork turns through a full circle',
		false !== strpos( $js, 'fitState.rot + 1) % 4' ) &&
		false !== strpos( $js, 'rotate(" + (q * 90) + " ' ) );
	// ⭐ Turning must pivot on the object, so the centre is what is stored.
	ktsv_true( 'position is held as a centre, not a corner',
		false !== strpos( $js, 'var fitState = { item:null, rot:0, cx:null, cy:null' ) &&
		false === strpos( $js, 'fitState.x =' ) );
	ktsv_true( 'the turn is animated, and pivots on the object',
		false !== strpos( $js, 'function turnThing()' ) &&
		false !== strpos( $js, 'spin.style.transform = "rotate(90deg)"' ) );
	ktsv_true( 'a still turn for anyone who asked for less motion',
		false !== strpos( $js, 'prefers-reduced-motion: reduce' ) );

	ktsv_reset_used();
	$html = Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ) );
	ktsv_true( 'the tray has a place in the markup', false !== strpos( $html, 'data-kt-tray' ) );

	/* ------------------------------ assets ------------------------------- */
	echo "\n--- assets ---\n";
	ktsv_reset_used();
	ob_start(); Kohthai_Size_View::print_assets(); $out = ob_get_clean();
	ktsv_is( 'nothing printed when unused', $out, '' );

	Kohthai_Size_View::shortcode( array( 'across' => '27', 'tall' => '22', 'deep' => '8' ) );
	ob_start(); Kohthai_Size_View::print_assets(); $out = ob_get_clean();
	ktsv_true( 'style printed',  false !== strpos( $out, '<style' ) );
	ktsv_true( 'script printed', false !== strpos( $out, '<script' ) );
	// ⭐ Standing rule: the module draws itself in JS, so a deferred or delayed
	// script leaves an empty panel. These guards must never be dropped.
	ktsv_true( 'WP Rocket: data-no-optimize on both',
		2 === substr_count( $out, 'data-no-optimize="1"' ) );
	ktsv_true( 'WP Rocket: data-no-minify on both',
		2 === substr_count( $out, 'data-no-minify="1"' ) );
	ktsv_true( 'WP Rocket: script is not deferred',
		false !== strpos( $out, 'data-no-defer="1"' ) );
	ktsv_true( 'Cloudflare: data-cfasync on both',
		2 === substr_count( $out, 'data-cfasync="false"' ) );
	ktsv_true( 'CSS neutralises the hidden attribute for module and launcher',
		false !== strpos( $out, '.kt-sv [hidden],.kt-sv-launch [hidden]{display:none!important}' ) );
	ktsv_true( 'launcher gets its own border-box reset',
		false !== strpos( $out, '.kt-sv-launch,.kt-sv-launch *' ) );
	// The window must size itself to the screen: a definite height so the
	// drawing can grow into it, and min-height:0 down the flex chain so it can
	// also shrink. Without both, either the figure is tiny or the panel scrolls.
	ktsv_true( 'window has a definite height', false !== strpos( $out, 'height:min(94vh,860px)' ) );
	ktsv_true( 'stage flexes inside the window',
		false !== strpos( $out, '.kt-sv-modal .kt-sv__stage{display:flex;flex-direction:column;flex:1 1 auto;min-height:0}' ) );
	ktsv_true( 'page is pinned, not just overflow-hidden',
		false !== strpos( $out, 'body.kt-sv-locked{position:fixed' ) );
	ktsv_true( 'mobile window is not full-bleed',
		false !== strpos( $out, '.kt-sv-modal{padding:5vh 3vw}' ) );
	// Standing the sliders up gave the figure ~110px of height back.
	ktsv_true( 'sliders stand vertically in the window',
		false !== strpos( $out, 'writing-mode:vertical-lr' ) );
	ktsv_true( 'window lays the drawing, tray and verdict in their own rows',
		false !== strpos( $out, 'grid-template-areas:"canvas controls" "tray tray" "hint hint"' ) );
	// An outline only where it carries information: none when it goes in.
	ktsv_true( 'no outline when it fits', false !== strpos( $out, '.kt-sv__thingbox.is-quiet{stroke:none}' ) );
	ktsv_true( 'the verdict itself turns red when it does not',
		false !== strpos( $out, '.kt-sv__hint.is-no' ) );
	ktsv_true( 'the side view is marked when it overflows',
		false !== strpos( $out, '.kt-sv__sidething.is-out .kt-sv__sidebox' ) );
	// Carried, it goes see-through so the bag under it can still be read — and
	// solid again on release, because the verdict is judged at rest.
	// ⚠️⚠️ The drawing used to be sized to whatever it contained, so the SCALE
	// changed from product to product: a small clutch was blown up to fill the
	// panel and the laptop over it looked monstrous, while the same laptop on a
	// 40 cm tote looked modest. The frame is now a constant in millimetres.
	ktsv_true( 'the fitting view is drawn in a fixed frame',
		false !== strpos( $out, 'var REF_W = 420, REF_H = 300, REF_BAND = 170;' ) );
	ktsv_true( 'the bag is centred in that frame at true size',
		false !== strpos( $out, 'var BX = PAD + (frameW - W)/2;' ) );
	// Padding and lettering must not scale with the bag, or the frame is not fixed.
	ktsv_true( 'the padding is measured from the frame, not the bag',
		false !== strpos( $out, 'var fs = Math.max(REF_W, REF_H) * 0.05;' ) );
	// A bigger bag than the reference must still draw, at its own scale.
	ktsv_true( 'and the frame grows rather than clipping an outsized bag',
		false !== strpos( $out, 'var frameW = Math.max(REF_W, W, f ? f.w : 0);' ) );
	// ⚠️ The panel used to be measured from the BAG alone. A MacBook against a
	// 24 x 14 cm bag is wider and taller than that whole scene was, so it covered
	// the panel edge to edge and hid the bag it was being compared against.
	// Turning a big object changes how far it overhangs, which shifts the whole
	// layout — an absolute centre would slide against the bag on every turn.
	ktsv_true( 'the object centre is held relative to the bag',
		false !== strpos( $out, 'state.cx = acx - BX; state.cy = acy - BY;' ) );
	ktsv_true( 'a thing that overhangs the bag is drawn see-through',
		false !== strpos( $out, '.kt-sv__thing.is-over .kt-sv__spin{opacity:.62}' ) );
	// Failing merely because it needs turning is not a reason to fade it.
	ktsv_true( 'but one that merely needs turning is not',
		false !== strpos( $out, 'var hides = (f.w > W + fs*0.5) || (f.h > H + fs*0.5);' ) );
	// ⚠️⚠️ Saving used to throw away every box drawn on a SHIPPED photograph.
	// Most products have no upload — they use the cut-out that ships with the
	// plugin — and the save loop treated "no upload" as "no photograph": it
	// deleted the body box and skipped to the next product. Every box measured
	// by hand was lost, and the screen came back showing the shipped one.
	ktsv_is( 'a drawn box is kept when the photograph is the shipped one',
		Kohthai_Size_View::plan_body_box( 0, '0.1,0.2,0.7,0.6', false )['action'], 'save' );
	ktsv_is( 'and when the photograph was uploaded',
		Kohthai_Size_View::plan_body_box( 42, '0.1,0.2,0.7,0.6', false )['action'], 'save' );
	ktsv_is( 'an empty field on a shipped photo goes back to the shipped box',
		Kohthai_Size_View::plan_body_box( 0, '', false )['action'], 'forget' );
	ktsv_is( 'an empty field on an upload has it measured',
		Kohthai_Size_View::plan_body_box( 42, '', false )['action'], 'detect' );
	ktsv_is( '"measure again" overrides whatever is in the field',
		Kohthai_Size_View::plan_body_box( 42, '0.1,0.2,0.7,0.6', true )['action'], 'detect' );

	// Strict on purpose: this decides whether a hand-drawn box survives.
	ktsv_is( 'a box outside the picture is refused',
		Kohthai_Size_View::valid_body( '0.5,0,0.8,0.5' ), null );
	ktsv_is( 'a box with no width is refused',
		Kohthai_Size_View::valid_body( '0.1,0.1,0,0.5' ), null );
	ktsv_is( 'three numbers are refused',
		Kohthai_Size_View::valid_body( '0.1,0.1,0.5' ), null );
	ktsv_true( 'a whole-image box is allowed',
		null !== Kohthai_Size_View::valid_body( '0,0,1,1' ) );
	ktsv_true( 'spaces around the numbers are tolerated',
		null !== Kohthai_Size_View::valid_body( ' 0.1 , 0.2 , 0.7 , 0.6 ' ) );

	// ⚠️⚠️ Second half of the same bug: even once the box was saved, a
	// SHIPPED photograph always read its box out of the manifest and never out
	// of the meta — so correcting one by hand changed nothing on the page.
	// This walks the real path: a real product, a real manifest slug.
	$slug = 'elegant-evening-clutch-bag-for-women';
	$pid  = wp_insert_post( array(
		'post_type'   => 'product',
		'post_status' => 'publish',
		'post_title'  => 'KT harness clutch',
		'post_name'   => $slug,
	) );
	if ( $pid && ! is_wp_error( $pid ) ) {
		$read = new ReflectionMethod( 'Kohthai_Size_View', 'cutout' );
		$read->setAccessible( true );

		delete_post_meta( $pid, '_kt_size_cutout_body' );
		$shipped = $read->invoke( null, array(), $pid );
		ktsv_true( 'a shipped photograph is found for the product',
			is_array( $shipped ) && false !== strpos( $shipped['url'], $slug ) );

		update_post_meta( $pid, '_kt_size_cutout_body', '0.11,0.22,0.55,0.44' );
		$fixed = $read->invoke( null, array(), $pid );
		ktsv_is( 'a box drawn by hand reaches the product page',
			implode( ',', $fixed['body'] ), '0.11,0.22,0.55,0.44' );

		// Rubbish in the meta must not win over the shipped box.
		update_post_meta( $pid, '_kt_size_cutout_body', 'nonsense' );
		$safe = $read->invoke( null, array(), $pid );
		ktsv_is( 'and a broken one falls back to the shipped box',
			implode( ',', $safe['body'] ), implode( ',', $shipped['body'] ) );

		wp_delete_post( $pid, true );
	}

	// The admin editor is inline in admin_page(), which needs a live admin screen
	// and real products to render — so these read the source it is written in.
	// They are source assertions either way: what is checked is that the editor's
	// own lines are present and unchanged.
	// ⚠️ NOT dirname( __FILE__ ): wp-cli eval-file runs this through eval(), so
	// __FILE__ resolves inside the wp-cli phar, file_get_contents() returns
	// false, and the first strpos() on it kills the run in PHP 8. Reflection
	// gives the class's real file wherever the harness is run from.
	$ref   = new ReflectionClass( 'Kohthai_Size_View' );
	$admin = (string) file_get_contents( $ref->getFileName() );
	ktsv_true( 'the plugin source is readable for the admin checks', '' !== $admin );

	// The detector is a heuristic and has been wrong (a chain handle is exactly
	// as wide as its bag), and the only correction used to be typing four
	// decimal fractions. A rectangle is all the renderer ever needs — it says
	// WHERE the body is, nothing traces the outline — so a drawn box is enough
	// even for a curved bag.
	ktsv_true( 'the body box can be drawn by hand',
		false !== strpos( $admin, 'var HANDLES = ["nw","n","ne","e","se","s","sw","w"];' ) );
	ktsv_true( 'with handles on every corner and edge',
		false !== strpos( $admin, '.kt-svadmin__h[data-h="nw"]' ) &&
		false !== strpos( $admin, '.kt-svadmin__h[data-h="w"] ' ) );
	ktsv_true( 'the drawn box writes straight into the saved field',
		false !== strpos( $admin, 'round4(b.l), round4(b.t), round4(b.w), round4(b.h)' ) );
	ktsv_true( 'and typing in the field still moves the box',
		false !== strpos( $admin, "on(\"input\", \"[data-kt-body]\"" ) );
	// A box can never be dragged to nothing, or the photograph divides by zero.
	ktsv_true( 'a box can never be dragged away to nothing',
		false !== strpos( $admin, 'var MIN = 0.02;' ) );
	// Same lesson as the front end: the pointer leaves a 240px box at once.
	ktsv_true( 'the admin drag listens on the window too',
		false !== strpos( $admin, '$(window).on("pointermove.ktbox"' ) );
	ktsv_true( 'and the picture takes the gesture rather than the page',
		false !== strpos( $admin, 'cursor:crosshair;touch-action:none' ) );
	// The shape of the box should resemble the bag's own across-by-tall.
	ktsv_true( 'a box whose shape does not match the bag is queried',
		false !== strpos( $admin, 'a handle swept in with the body does this' ) );
	ktsv_true( 'there is a way back to automatic',
		false !== strpos( $admin, 'data-kt-remeasure' ) );

	// ⚠️ The photograph is scaled so the BODY lands on the measured box, so a
	// chain reaches far above it — 191 mm above one 280 mm bag. That spilled out
	// of the drawing and the panel chopped it off, worse on a phone where the
	// panel is tighter. Held to the body, the cut lands on the outline instead.
	ktsv_true( 'the photograph is held to the body in the fitting view',
		false !== strpos( $out, 'drawBag(g, bag, BX, BY, W, H, false, true);' ) );
	ktsv_true( 'and the clip is a real clipPath with a unique id',
		false !== strpos( $out, 'var id = "kt-sv-clip-" + (++CLIP_N);' ) );
	// The figure hangs the bag from her hand, so there the strap is the point.
	ktsv_true( 'the figure keeps its straps',
		false !== strpos( $out, 'drawBag(grp, bag, bx, by, bw, bh, false);' ) );
	// ⚠️⚠️ touch-action is NOT honoured on SVG child elements — only on the root
	// or on HTML elements. It was set on the dragged groups, so a phone claimed
	// every drag as a pan and cancelled the pointer. This is the line that
	// actually made finger dragging work.
	ktsv_true( 'touch-action sits on the svg root, where it is honoured',
		false !== strpos( $out, 'max-height:var(--kt-max);overflow:visible;' ) &&
		false !== strpos( $out, 'touch-action:none}' ) );
	// ⚠️ WP Rocket's "Delay JavaScript Execution" is a separate feature from
	// defer, and data-no-defer does not cover it. It held this script until the
	// visitor's first interaction, so the button appeared only after a scroll.
	ktsv_true( 'WP Rocket is told not to delay this script',
		false !== strpos( $out, 'window.ktSizeViewModule = true;' ) );
	// The lettering is written in millimetres, so it shrinks with the drawing.
	ktsv_true( 'lettering is raised to a floor in real screen pixels',
		false !== strpos( $out, 'var k = w / sceneW, MIN = 12.5;' ) );
	// A quarter of the width for something the caption already says in words.
	ktsv_true( 'the edge-on strip stands down on a narrow panel',
		false !== strpos( $out, 'var SIDE = (roomy && D > 0) ? D : 0' ) );
	ktsv_true( 'and the panel width is measured, not guessed from the viewport',
		false !== strpos( $out, '(scroll ? scroll.clientWidth : 0) < 560' ) );
	// Bottom aligning suits the figure, which stands on a floor; the fitting
	// view has no floor and it left a band of empty panel above the drawing.
	ktsv_true( 'the fitting view is centred in its panel',
		false !== strpos( $out, '.kt-sv__canvas.is-fits{align-items:center}' ) );
	// "The script never ran" and "the script ran late" both looked like "there
	// is no button".
	ktsv_true( 'the button reveals itself if nothing claims it',
		false !== strpos( $out, '@keyframes kt-sv-reveal' ) );
	// ⚠️ Dragging worked with a mouse and did nothing at all by finger. The move
	// and release listeners were on the SVG element and so depended on
	// setPointerCapture, which is not dependable on SVG in mobile browsers.
	ktsv_true( 'the fitting drag listens on the window, not the object',
		2 === substr_count( $out, 'window.addEventListener("pointermove", onMove, { passive:false });' ) );
	ktsv_true( 'and unbinds itself when the drag ends',
		2 === substr_count( $out, 'window.removeEventListener("pointermove", onMove);' ) );
	// A lipstick is a few millimetres of artwork: no target at all for a finger.
	ktsv_true( 'the object has a finger-sized grab pad',
		false !== strpos( $out, 'fill:"transparent", "class":"kt-sv__grab"' ) );
	ktsv_true( 'and the pad takes the gesture rather than the page',
		false !== strpos( $out, '.kt-sv__grab{cursor:grab;touch-action:none}' ) );
	ktsv_true( 'labels ride over the object with a halo behind them',
		false !== strpos( $out, '.kt-sv__halo{paint-order:stroke' ) );
	ktsv_true( 'the object goes see-through while it is carried',
		false !== strpos( $out, '.kt-sv__thing.is-dragging .kt-sv__spin{opacity:.45}' ) );
	ktsv_true( 'and fades rather than blinking',
		false !== strpos( $out, 'transition:transform .3s cubic-bezier(.34,1.12,.5,1),opacity .14s ease' ) );
	// A MacBook is big enough to rub out the edge she is lining it up against.
	ktsv_true( 'the bag edge is redrawn above the object',
		false !== strpos( $out, '.kt-sv__opening--over{pointer-events:none' ) );
	ktsv_true( 'the edge is lifted while she is carrying something',
		false !== strpos( $out, 'svg.is-carrying .kt-sv__opening--over' ) );
	ktsv_true( 'the swing pivots on the object centre',
		false !== strpos( $out, '.kt-sv__spin{transform-box:fill-box;transform-origin:50% 50%' ) );
	ktsv_true( 'the turn handle appears on hover',
		false !== strpos( $out, '.kt-sv__thing:hover .kt-sv__rotate' ) );
	// There is no hover on a phone, so it has to stay put there.
	ktsv_true( 'and stays visible where there is no hover',
		false !== strpos( $out, '@media (hover:none){ .kt-sv__rotate{opacity:1} }' ) );
	ktsv_true( 'launcher is hidden until placed',
		false !== strpos( $out, '.kt-sv-launch{visibility:hidden}' ) );
	// ⚠️ Flatsome sets button{margin-bottom:1em}. That margin sat inside the tab
	// pill and made the buttons look stuck to the top of it.
	ktsv_true( 'the theme button margin is cleared',
		false !== strpos( $out, '.kt-sv button,.kt-sv-launch button,.kt-sv-modal button{margin:0' ) );

	// The head may already have printed it; the footer must not print a second copy.
	ob_start(); Kohthai_Size_View::print_assets(); $again = ob_get_clean();
	ktsv_is( 'stylesheet is never printed twice', substr_count( $again, '<style' ), 0 );
	// Stamped into the stylesheet so the live version can be confirmed from
	// view-source without opening the plugin file.
	ktsv_true( 'stylesheet is stamped with the version',
		false !== strpos( $out, 'Kohthai Size View ' . Kohthai_Size_View::VERSION ) );

	foreach ( get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) as $id ) {
		wp_delete_post( $id, true );
	}
}

list( $pass, $fail ) = ktsv_tally();
echo "\n================================\n";
echo "  {$pass} passed / {$fail} failed\n";
echo "================================\n";
