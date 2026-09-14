<?php
/**
 * Kohthai — sync WooCommerce product dimensions from each product's own
 * [kt_details] block.
 *
 * WHY THIS EXISTS
 * ---------------
 * The visible size text on the product page is correct. The WooCommerce
 * length/width/height fields are not: on most products the numbers were typed
 * in the order the page prints them (Length, HEIGHT, Width) into WooCommerce's
 * boxes, which are ordered (Length, WIDTH, Height). So depth and height are
 * transposed, and at least one product carries another product's numbers.
 *
 * Nobody sees those fields on the page, but they feed schema.org / Google
 * Shopping, and they would feed the size-preview feature. This makes
 * WooCommerce agree with the page.
 *
 * The page copy is the single source of truth. This script never invents a
 * number: a product with no [kt_details] block is reported, not guessed.
 *
 * HOW TO USE
 * ----------
 *   1. Change $KEY below to something only you know.
 *   2. Log in to WordPress as administrator in your browser.
 *   3. Upload this file to the WordPress root (the folder holding wp-load.php).
 *   4. Visit  https://kohthaibd.com/kt-sync-dimensions.php?key=YOURKEY
 *      -> DRY RUN. Nothing is written. Read the report.
 *   5. If it looks right, add &apply=1 :
 *      https://kohthaibd.com/kt-sync-dimensions.php?key=YOURKEY&apply=1
 *   6. DELETE THE FILE.
 *
 * Weight is reported but never changed — weights affect courier charges and
 * are not part of this problem.
 */

/* ======================================================================
 * Helpers (pure — the test harness loads this file with KTSD_TEST defined
 * and calls these directly, so the shipped code is the tested code)
 * ==================================================================== */

/**
 * Pull a number out of a [kt_details] value like "27 cm", "6.5 cm", "31-37 cm".
 *
 * A range means the bag is slouchy and its width genuinely varies. WooCommerce
 * holds one number, so we take the LARGER end — the widest the bag actually
 * gets — and say so in the report, because how the size preview should draw a
 * slouchy bag is a real decision someone has to make.
 */
function ktsd_num( $raw, &$note = null ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}
	$s = str_replace( array( "\xe2\x80\x93", "\xe2\x80\x94" ), '-', $raw ); // en/em dash
	$s = preg_replace( '/[^0-9.\-]/', '', $s );
	if ( '' === $s || '.' === $s || '-' === $s ) {
		return null;
	}
	if ( false !== strpos( $s, '-' ) ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( '-', $s ) ), 'strlen' ) );
		if ( count( $parts ) >= 2 ) {
			$max  = max( array_map( 'floatval', $parts ) );
			$note = 'RANGE "' . $raw . '" — stored the larger end (' . $max . ')';
			return $max;
		}
		$s = str_replace( '-', '', $s );
	}
	if ( ! is_numeric( $s ) ) {
		return null;
	}
	return (float) $s;
}

/** Format a stored WooCommerce dimension for display. */
function ktsd_show( $v ) {
	if ( '' === $v || null === $v ) {
		return '-';
	}
	return rtrim( rtrim( sprintf( '%.2f', (float) $v ), '0' ), '.' );
}

/** Find the first [kt_details] block anywhere this product stores content. */
function ktsd_find_details( $post_id ) {
	$post = get_post( $post_id );
	$hay  = array();
	if ( $post ) {
		$hay['post_content'] = $post->post_content;
		$hay['post_excerpt'] = $post->post_excerpt;
	}
	foreach ( (array) get_post_meta( $post_id ) as $mk => $mv ) {
		foreach ( (array) $mv as $val ) {
			if ( is_string( $val ) && false !== strpos( $val, '[kt_details' ) ) {
				$hay[ 'meta:' . $mk ] = $val;
			}
		}
	}

	$regex = get_shortcode_regex( array( 'kt_details' ) );
	foreach ( $hay as $where => $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '[kt_details' ) ) {
			continue;
		}
		if ( preg_match( '/' . $regex . '/s', $content, $m ) ) {
			$atts = shortcode_parse_atts( $m[3] );
			if ( is_array( $atts ) ) {
				return array( 'atts' => $atts, 'where' => $where );
			}
		}
	}
	return null;
}

/**
 * Walk every product, compare Woo's stored dimensions with the page copy, and
 * optionally write the page copy's numbers into WooCommerce.
 *
 * @param bool $apply Write the changes (false = dry run).
 * @param bool $echo  Print the human report.
 * @return array Tallies + lists, so a test can assert on them.
 */
function ktsd_run( $apply = false, $echo = true ) {
	$out = array(
		'changed' => 0,
		'same'    => 0,
		'missing' => array(),
		'notes'   => array(),
		'weights' => array(),
		'varover' => array(),
		'skipped' => array(),
		'rows'    => array(),
	);

	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	if ( $echo ) {
		echo 'Found ' . count( $ids ) . " products.\n\n";
		echo str_pad( 'PRODUCT', 46 ) . ' | ' . str_pad( 'WOO NOW  L/W/H', 18 ) . ' | ' . str_pad( 'FROM PAGE  L/W/H', 18 ) . " | ACTION\n";
		echo str_repeat( '-', 112 ) . "\n";
	}

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		$name  = get_the_title( $id );
		$short = mb_substr( $name, 0, 46 );
		$cur   = ktsd_show( $product->get_length() ) . '/' . ktsd_show( $product->get_width() ) . '/' . ktsd_show( $product->get_height() );

		$found = ktsd_find_details( $id );
		if ( ! $found ) {
			$out['missing'][] = $name . '  (Woo currently: ' . str_replace( '/', ' / ', $cur ) . ')';
			if ( $echo ) {
				echo str_pad( $short, 46 ) . ' | ' . str_pad( $cur, 18 ) . ' | ' . str_pad( 'no [kt_details]', 18 ) . " | SKIP — needs measuring\n";
			}
			continue;
		}

		$atts = $found['atts'];

		// The page labels map to WooCommerce like this, and this mapping is the
		// entire point of the script:
		//   page "Length" -> across -> woo length
		//   page "Width"  -> depth  -> woo width
		//   page "Height" -> tall   -> woo height
		$note  = null;
		$n_len = ktsd_num( isset( $atts['length'] ) ? $atts['length'] : '', $note );
		if ( $note ) { $out['notes'][] = $name . ': length ' . $note; $note = null; }
		$n_wid = ktsd_num( isset( $atts['width'] ) ? $atts['width'] : '', $note );
		if ( $note ) { $out['notes'][] = $name . ': width ' . $note; $note = null; }
		$n_hei = ktsd_num( isset( $atts['height'] ) ? $atts['height'] : '', $note );
		if ( $note ) { $out['notes'][] = $name . ': height ' . $note; $note = null; }

		// Sanity: a handbag is not 300 cm and not 0 cm.
		$bad = false;
		foreach ( array( 'length' => $n_len, 'width' => $n_wid, 'height' => $n_hei ) as $lbl => $v ) {
			if ( null !== $v && ( $v <= 0 || $v > 200 ) ) {
				$out['skipped'][] = $name . ': ' . $lbl . ' = ' . $v . ' cm is out of range, product left untouched';
				$bad = true;
				break;
			}
		}
		if ( $bad || ( null === $n_len && null === $n_wid && null === $n_hei ) ) {
			if ( ! $bad ) {
				$out['skipped'][] = $name . ': [kt_details] carries no readable size';
			}
			if ( $echo ) {
				echo str_pad( $short, 46 ) . ' | ' . str_pad( $cur, 18 ) . ' | ' . str_pad( 'unreadable', 18 ) . " | SKIP\n";
			}
			continue;
		}

		$old = array(
			'length' => ktsd_show( $product->get_length() ),
			'width'  => ktsd_show( $product->get_width() ),
			'height' => ktsd_show( $product->get_height() ),
		);
		$new = array(
			'length' => null === $n_len ? $old['length'] : ktsd_show( $n_len ),
			'width'  => null === $n_wid ? $old['width'] : ktsd_show( $n_wid ),
			'height' => null === $n_hei ? $old['height'] : ktsd_show( $n_hei ),
		);

		// Weight: reported only, never written.
		if ( isset( $atts['weight'] ) && '' !== trim( $atts['weight'] ) ) {
			$raw_w = trim( $atts['weight'] );
			$wnum  = ktsd_num( $raw_w );
			if ( null !== $wnum ) {
				$in_kg = ( false !== stripos( $raw_w, 'kg' ) ) ? $wnum : $wnum / 1000;
				$woo_w = (float) $product->get_weight();
				if ( abs( $in_kg - $woo_w ) > 0.02 ) {
					$out['weights'][] = sprintf( '%s — page %s (= %.2f kg) vs Woo %.2f kg', $name, $raw_w, $in_kg, $woo_w );
				}
			}
		}

		// A variation carrying its own dimensions silently beats the parent.
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				if ( '' !== $v->get_length( 'edit' ) || '' !== $v->get_width( 'edit' ) || '' !== $v->get_height( 'edit' ) ) {
					$out['varover'][] = $name . ' -> variation #' . $vid . ' has its own '
						. ktsd_show( $v->get_length() ) . '/' . ktsd_show( $v->get_width() ) . '/' . ktsd_show( $v->get_height() );
				}
			}
		}

		$is_same = ( $old === $new );
		$action  = $is_same ? 'already correct' : ( $apply ? 'UPDATED' : 'would update' );

		$out['rows'][ $id ] = array( 'name' => $name, 'old' => $old, 'new' => $new, 'same' => $is_same );

		if ( $echo ) {
			echo str_pad( $short, 46 ) . ' | '
				. str_pad( $old['length'] . '/' . $old['width'] . '/' . $old['height'], 18 ) . ' | '
				. str_pad( $new['length'] . '/' . $new['width'] . '/' . $new['height'], 18 ) . ' | '
				. $action . "\n";
		}

		if ( $is_same ) {
			$out['same']++;
			continue;
		}
		$out['changed']++;

		if ( $apply ) {
			// CRUD setters rather than update_post_meta — these also refresh the
			// product lookup tables and bust the object cache.
			if ( null !== $n_len ) { $product->set_length( $n_len ); }
			if ( null !== $n_wid ) { $product->set_width( $n_wid ); }
			if ( null !== $n_hei ) { $product->set_height( $n_hei ); }
			$product->save();
		}
	}

	if ( $echo ) {
		ktsd_report( $out, $apply );
	}
	return $out;
}

/** Print everything the run found that a human has to act on. */
function ktsd_report( $out, $apply ) {
	echo "\n" . str_repeat( '=', 112 ) . "\n";
	echo sprintf( "SUMMARY: %d product(s) %s · %d already correct · %d with no [kt_details].\n",
		$out['changed'], $apply ? 'updated' : 'would change', $out['same'], count( $out['missing'] ) );

	if ( $out['missing'] ) {
		echo "\n--- NEEDS A TAPE MEASURE (no size block on the page, so there is nothing to trust) ---\n";
		echo "Measure across x tall x deep in cm, add a [kt_details] block to the page,\nthen run this again.\n\n";
		foreach ( $out['missing'] as $m ) {
			echo '  * ' . $m . "\n";
		}
	}
	if ( $out['notes'] ) {
		echo "\n--- RANGES (a slouchy bag; one number cannot describe it) ---\n";
		foreach ( $out['notes'] as $n ) {
			echo '  * ' . $n . "\n";
		}
	}
	if ( $out['varover'] ) {
		echo "\n--- WARNING: variations carrying their own dimensions ---\n";
		echo "These BEAT the parent, so fixing the parent does not fix them.\n\n";
		foreach ( $out['varover'] as $v ) {
			echo '  * ' . $v . "\n";
		}
	}
	if ( $out['weights'] ) {
		echo "\n--- WEIGHT DIFFERENCES (reported only, nothing written) ---\n";
		echo "Weights affect courier charges, so they are yours to decide.\n\n";
		foreach ( $out['weights'] as $w ) {
			echo '  * ' . $w . "\n";
		}
	}
	if ( $out['skipped'] ) {
		echo "\n--- SKIPPED, VALUE LOOKED WRONG ---\n";
		foreach ( $out['skipped'] as $s ) {
			echo '  * ' . $s . "\n";
		}
	}
}

/* ======================================================================
 * Web entry point — skipped entirely when the test harness loads this file
 * ==================================================================== */

if ( ! defined( 'KTSD_TEST' ) ) {

	$KEY = 'kt-dim-sync-2026';   // <<< CHANGE ME before uploading

	if ( ! isset( $_GET['key'] ) || ! hash_equals( $KEY, (string) $_GET['key'] ) ) {
		http_response_code( 404 );
		exit;
	}

	require_once __DIR__ . '/wp-load.php';
	header( 'Content-Type: text/plain; charset=utf-8' );

	if ( ! function_exists( 'wc_get_product' ) ) {
		exit( "WooCommerce is not active. Nothing done.\n" );
	}

	$apply = isset( $_GET['apply'] ) && '1' === $_GET['apply'];

	echo "=========================================================\n";
	echo " KOHTHAI — dimension sync from [kt_details]\n";
	echo ' MODE: ' . ( $apply ? '*** APPLYING CHANGES ***' : 'DRY RUN (nothing written)' ) . "\n";
	echo ' TIME: ' . current_time( 'mysql' ) . "\n";
	echo "=========================================================\n\n";

	// The key lets you READ the report. Writing also wants a logged-in shop
	// manager, so a leaked URL cannot rewrite the catalogue.
	if ( $apply && ! current_user_can( 'manage_woocommerce' ) ) {
		exit( "REFUSED: log in to WordPress as an administrator in this same browser,\nthen reload this URL. The key alone is not enough to write.\n" );
	}

	// The [kt_details] values are in centimetres. If the store measures in
	// anything else, writing them would silently store cm numbers as inches.
	$dim_unit = get_option( 'woocommerce_dimension_unit' );
	if ( 'cm' !== $dim_unit ) {
		exit( "REFUSED: store dimension unit is '{$dim_unit}', not 'cm'.\nFix the unit in WooCommerce → Settings → Products first.\n" );
	}
	echo 'Store units: dimensions in ' . $dim_unit . ', weight in ' . get_option( 'woocommerce_weight_unit' ) . ".\n\n";

	$result = ktsd_run( $apply, true );

	if ( $apply && $result['changed'] ) {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			echo "\nWP Rocket cache cleared — the pages carry this data in their schema markup.\n";
		} else {
			echo "\nNOTE: clear the WP Rocket cache by hand — the pages carry this data in\ntheir schema markup.\n";
		}
	}

	echo "\n";
	echo $apply ? "Done.\n" : "Nothing was written. Add &apply=1 to the URL to make these changes.\n";
	echo ">>> NOW DELETE THIS FILE FROM THE SERVER. <<<\n";
}
