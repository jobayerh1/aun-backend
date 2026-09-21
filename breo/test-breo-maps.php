<?php
/**
 * Bench test for the Google Maps setting (includes/maps.php). Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-maps.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
$saved = get_option( 'breo_bd_settings', array() );
$set   = function ( $url ) use ( $saved ) {
	$s         = is_array( $saved ) ? $saved : array();
	$s['maps'] = $url;
	update_option( 'breo_bd_settings', $s );
};
$long = 'https://www.google.com/maps/place/Breo+Bangladesh/@23.7914212,90.3402505,17z/data=!3m1!4b1!4m6!3m5!1s0x3755c155527711c7:0x1c27fd8c2d5c0705!8m2!3d23.7914212!4d90.3428254!16s%2Fg%2F11zytkvj4p';

// only Google's own map links are accepted
t( '' === breo_bd_clean_maps_url( 'https://example.com/maps/place/Breo' ), 'a non-Google link is rejected' );
t( '' === breo_bd_clean_maps_url( 'https://www.google.com/search?q=breo' ), 'a Google link that is not /maps is rejected' );
t( '' === breo_bd_clean_maps_url( '' ), 'empty stays empty' );
t( $long === breo_bd_clean_maps_url( $long ), 'a full Maps URL is kept' );
t( 'https://maps.app.goo.gl/yvfHHNzmPuXjw7Yj9' === breo_bd_clean_maps_url( 'https://maps.app.goo.gl/yvfHHNzmPuXjw7Yj9' ), 'the short link the owner sent is accepted' );

// coordinates: the place (!3d/!4d), not the map centre (@lat,lng)
update_option( 'breo_bd_maps_full', $long, false );
$set( $long );
$geo = breo_bd_maps_geo();
t( $geo && '23.7914212' === $geo['lat'] && '90.3428254' === $geo['lng'], 'coordinates read from the place, not the map centre (' . ( $geo ? $geo['lat'] . ',' . $geo['lng'] : 'none' ) . ')' );

// the footer and Contact page use it
$footer = breo_bd_footer_html();
t( false !== strpos( $footer, esc_url( $long ) ), 'footer address links to the profile' );
$contact = do_shortcode( '[breo_contact]' );
t( false !== strpos( $contact, esc_url( $long ) ) && false !== strpos( $contact, 'Open in Maps' ), 'Contact page Visit card links to the profile' );

// schema
$data = apply_filters( 'rank_math/json_ld', array( 'org' => array( '@type' => 'Organization', 'name' => 'Breo Bangladesh' ) ), null );
t( isset( $data['org']['hasMap'] ) && $long === $data['org']['hasMap'], 'Organization schema gains hasMap' );
t( isset( $data['org']['geo']['latitude'] ) && '23.7914212' === $data['org']['geo']['latitude'], 'Organization schema gains geo coordinates' );
$data2 = apply_filters( 'rank_math/json_ld', array( 'page' => array( '@type' => 'WebPage' ) ), null );
t( ! isset( $data2['page']['hasMap'] ), 'other schema nodes are left alone' );

// with no link set, the address still opens a Maps search
$set( '' );
delete_option( 'breo_bd_maps_full' );
$link = breo_bd_maps_link();
t( false !== strpos( $link, 'google.com/maps/search' ), 'without a profile it falls back to an address search' );
t( null === breo_bd_maps_geo(), 'no link, no coordinates' );
$data3 = apply_filters( 'rank_math/json_ld', array( 'org' => array( '@type' => 'Organization' ) ), null );
t( ! isset( $data3['org']['hasMap'] ), 'no link, no hasMap in schema' );

update_option( 'breo_bd_settings', $saved );
delete_option( 'breo_bd_maps_full' );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
