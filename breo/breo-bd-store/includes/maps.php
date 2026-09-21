<?php
/**
 * The Google Business Profile / Maps link.
 *
 * Only Google's own map hosts are accepted, so a mistyped or pasted tracking
 * link can never end up as a link on every page. A "maps.app.goo.gl" short
 * link is resolved once when it is saved, because the long URL carries the
 * coordinates that search engines want.
 */
defined( 'ABSPATH' ) || exit;

/** Hosts we will link to. */
function breo_bd_maps_hosts() {
	return array( 'maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'www.google.com', 'google.com', 'g.page', 'maps.google.com.bd', 'www.google.com.bd' );
}

/**
 * Validates the pasted link and remembers the expanded version.
 *
 * @return string the link to store, or '' when it is not a Google Maps URL.
 */
function breo_bd_clean_maps_url( $raw ) {
	$url = esc_url_raw( trim( (string) $raw ) );
	if ( ! $url ) {
		delete_option( 'breo_bd_maps_full' );
		return '';
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( ! in_array( $host, breo_bd_maps_hosts(), true ) ) {
		return '';
	}
	// on google.com only /maps links qualify
	if ( in_array( $host, array( 'www.google.com', 'google.com', 'www.google.com.bd' ), true ) && 0 !== strpos( $path, '/maps' ) ) {
		return '';
	}
	$full = breo_bd_maps_expand( $url );
	if ( $full && $full !== $url ) {
		update_option( 'breo_bd_maps_full', $full, false );
	} elseif ( preg_match( '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url ) || preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url ) ) {
		update_option( 'breo_bd_maps_full', $url, false ); // already a long link
	} else {
		delete_option( 'breo_bd_maps_full' );
	}
	return $url;
}

/** Follows a short link one hop to the full Maps URL. Returns '' if it cannot. */
function breo_bd_maps_expand( $url ) {
	$res = wp_remote_head( $url, array(
		'timeout'     => 8,
		'redirection' => 0,
		'user-agent'  => 'Mozilla/5.0 (compatible; BreoBangladesh/1.0; +' . home_url( '/' ) . ')',
	) );
	if ( is_wp_error( $res ) ) {
		return '';
	}
	$to = wp_remote_retrieve_header( $res, 'location' );
	$to = is_array( $to ) ? reset( $to ) : (string) $to;
	if ( ! $to ) {
		return '';
	}
	$host = strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) );
	return in_array( $host, breo_bd_maps_hosts(), true ) ? esc_url_raw( $to ) : '';
}

/**
 * Tell search engines where the shop is: hasMap on the store, plus geo
 * coordinates when the link gave us any.
 */
add_filter( 'rank_math/json_ld', function ( $data ) {
	$map = breo_bd_maps_url( true );
	if ( ! is_array( $data ) || ! $map ) {
		return $data;
	}
	$geo = breo_bd_maps_geo();
	foreach ( $data as $k => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];
		$is_org = (bool) array_intersect( $types, array( 'Organization', 'LocalBusiness', 'Store', 'HealthAndBeautyBusiness', 'OnlineStore' ) );
		if ( ! $is_org ) {
			continue;
		}
		$data[ $k ]['hasMap'] = $map;
		if ( $geo && empty( $node['geo'] ) ) {
			$data[ $k ]['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $geo['lat'],
				'longitude' => $geo['lng'],
			);
		}
	}
	return $data;
}, 98 );
