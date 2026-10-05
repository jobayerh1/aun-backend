<?php
/** aun-social-login 1.10.1: One Tap only on the pages chosen in Settings; Google G centred. */
$p = 0; $f = 0;
function ok( $c, $l ) { global $p, $f; if ( $c ) { $p++; echo "  PASS  $l\n"; } else { $f++; echo "  FAIL  $l\n"; } }
if ( ! class_exists( 'AUN_SL_OneTap' ) ) { echo "aun-social-login not active\n"; exit( 1 ); }

// Existing sites have a saved option without the new keys: defaults must fill them.
$saved = get_option( 'aun_sl_options', array() );
$tmp = $saved; unset( $tmp['onetap_on_checkout'], $tmp['onetap_on_account'], $tmp['onetap_on_cart'], $tmp['onetap_on_other'] );
update_option( 'aun_sl_options', $tmp );
ok( 1 === (int) AUN_SL_Options::get( 'onetap_on_checkout' ), 'existing site without the new keys: checkout defaults ON' );
ok( 0 === (int) AUN_SL_Options::get( 'onetap_on_other' ), 'and every other page defaults OFF' );

function goto_page( $id ) { global $wp_query; $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $id ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; }
goto_page( (int) wc_get_page_id( 'checkout' ) );
ok( true === AUN_SL_OneTap::prompt_here(), 'checkout: card allowed' );
goto_page( (int) wc_get_page_id( 'myaccount' ) );
ok( true === AUN_SL_OneTap::prompt_here(), 'my account: card allowed' );
goto_page( (int) wc_get_page_id( 'cart' ) );
ok( false === AUN_SL_OneTap::prompt_here(), 'cart: not shown by default' );
goto_page( (int) wc_get_page_id( 'shop' ) );
ok( false === AUN_SL_OneTap::prompt_here(), 'shop / other pages: not shown by default' );

$src = file_get_contents( WP_PLUGIN_DIR . '/aun-social-login/includes/class-aun-sl-popup.php' );
ok( false !== strpos( $src, "'logo_alignment' => 'center'" ), 'Google button: G centred next to the text' );

update_option( 'aun_sl_options', $saved );
echo "\n$p passed, $f failed\n";
