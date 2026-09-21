<?php
/**
 * Bench test for the PDF manuals (includes/manuals.php). Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-manuals.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}

// a stand-in for the factory PDF, named the way Breo names them
$up  = wp_upload_dir();
$pdf = $up['path'] . '/N6-mini_Manual_EN_TH_N990000631_20250820.pdf';
file_put_contents( $pdf, "%PDF-1.4\n" . str_repeat( 'x', 120000 ) . "\n%%EOF" );
$att = wp_insert_attachment( array(
	'post_mime_type' => 'application/pdf',
	'post_title'     => 'N6 mini_Manual_EN_TH_N990000631_20250820',
	'post_status'    => 'inherit',
), $pdf );

$n6  = wc_get_product( wc_get_product_id_by_sku( 'N990000631' ) );
$ke  = wc_get_product( wc_get_product_id_by_sku( 'N910200212' ) );
$n6->delete_meta_data( '_breo_manual_id' );
$ke->delete_meta_data( '_breo_manual_id' );
$n6->save_meta_data();
$ke->save_meta_data();

$m = breo_bd_manual( $n6 );
t( $m && (int) $m['id'] === (int) $att, 'manual matched to the product by SKU in the filename' );
t( $m && false !== strpos( $m['size'], 'KB' ), 'file size read: ' . ( $m ? $m['size'] : '-' ) );
t( (int) $n6->get_meta( '_breo_manual_id' ) === (int) $att || (int) wc_get_product( $n6->get_id() )->get_meta( '_breo_manual_id' ) === (int) $att, 'match remembered on the product' );

$none = breo_bd_manual( $ke );
t( null === $none, 'product without a manual returns nothing' );

$html = breo_bd_support_cards_html( $n6 );
t( false !== strpos( $html, 'breo-supp__card' ) && false !== strpos( $html, '.pdf' ), 'manual card renders' );
t( false !== strpos( $html, 'PDF · ' ), 'card states the format and size' );

$specs = breo_bd_sec_specs( $n6, breo_bd_product_data( $n6 ), array() );
t( false !== strpos( $specs, 'breo-supp' ) && false !== strpos( $specs, '.pdf' ), 'manual card appears under the specifications table' );
t( false !== strpos( $specs, 'Warranty' ), 'support cards include the warranty' );
$specs_ke = breo_bd_sec_specs( $ke, breo_bd_product_data( $ke ), array() );
t( false === strpos( $specs_ke, 'User manual' ), 'no manual card when there is no manual' );
t( false !== strpos( $specs_ke, 'breo-supp' ), 'other support cards still render' );

$short = do_shortcode( '[breo_manuals]' );
t( false !== strpos( $short, 'N6-mini_Manual' ), 'shortcode lists the manual' );
t( false !== strpos( $short, 'SKU N990000631' ), 'shortcode shows the SKU' );

// owner override: 0 hides it, an ID forces it
$n6->update_meta_data( '_breo_manual_id', 0 );
$n6->save_meta_data();
t( null === breo_bd_manual( wc_get_product( $n6->get_id() ) ), 'meta 0 hides the download' );
$ke->update_meta_data( '_breo_manual_id', (int) $att );
$ke->save_meta_data();
$forced = breo_bd_manual( wc_get_product( $ke->get_id() ) );
t( $forced && (int) $forced['id'] === (int) $att, 'explicit attachment ID wins' );

// tidy up
wp_delete_attachment( $att, true );
foreach ( array( $n6, $ke ) as $p ) {
	$p = wc_get_product( $p->get_id() );
	$p->delete_meta_data( '_breo_manual_id' );
	$p->save_meta_data();
}
t( ! file_exists( $pdf ), 'test file removed' );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
