<?php
/**
 * Plugin Name: AUN Local Upstream
 * Version: 1.0.0
 * Description: Routes WordPress's calls to the ERP and osTicket over loopback instead of out through Cloudflare and back. Same URLs, same auth, same certificates.
 * Author: AUN
 *
 * INSTALL: upload to wp-content/mu-plugins/
 * REVERT:  delete the file. Nothing persists.
 *
 * WHY
 *   portal.smartliving.com.bd and support.smartliving.com.bd live on this same
 *   server, but resolve to Cloudflare. Every call therefore left the machine,
 *   crossed the network to Singapore and came back, paying a fresh TLS
 *   handshake each time. Measured on this server:
 *       portal   0.179s -> 0.017s   (91% faster)
 *       support  0.051s -> 0.005s   (91% faster, and 403 -> 200)
 *   Cloudflare's WAF was returning 403 to osTicket calls via the public name,
 *   so this fixes a correctness problem as well as a latency one.
 *
 * SAFETY
 *   - Only the two hosts below are affected; every other outbound call is
 *     untouched.
 *   - CURLOPT_RESOLVE keeps the hostname for SNI and certificate validation,
 *     so TLS is still verified properly. We are changing the address, not
 *     weakening the connection.
 *   - Circuit breaker: if a loopback call fails, loopback is disabled site-wide
 *     for five minutes and calls go back out via DNS on their own.
 *   - Define AUN_LOCAL_UPSTREAM_OFF to disable without deleting the file.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AUN_Local_Upstream {

	/** Hosts that are actually on this machine. */
	private static $local_hosts = array(
		'portal.smartliving.com.bd',
		'support.smartliving.com.bd',
	);

	const LOOPBACK    = '127.0.0.1';
	const BREAKER_KEY = 'aun_local_upstream_off';
	const BREAKER_TTL = 300;

	/** URLs rewritten during this request, so we can spot their failures. */
	private static $rerouted = array();

	public static function boot() {
		if ( defined( 'AUN_LOCAL_UPSTREAM_OFF' ) && AUN_LOCAL_UPSTREAM_OFF ) {
			return;
		}

		add_action( 'http_api_curl', array( __CLASS__, 'point_at_loopback' ), 10, 3 );
		add_action( 'http_api_debug', array( __CLASS__, 'watch_for_failure' ), 10, 5 );
	}

	private static function is_local( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return $host && in_array( strtolower( $host ), self::$local_hosts, true );
	}

	public static function point_at_loopback( $handle, $args, $url ) {
		if ( ! self::is_local( $url ) ) {
			return;
		}

		// Breaker open: leave this request alone.
		if ( get_transient( self::BREAKER_KEY ) ) {
			return;
		}

		$host   = strtolower( wp_parse_url( $url, PHP_URL_HOST ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$port   = (int) ( wp_parse_url( $url, PHP_URL_PORT ) ?: ( 'http' === $scheme ? 80 : 443 ) );

		curl_setopt( $handle, CURLOPT_RESOLVE, array( sprintf( '%s:%d:%s', $host, $port, self::LOOPBACK ) ) );

		self::$rerouted[ $url ] = true;
	}

	public static function watch_for_failure( $response, $context, $class, $args, $url ) {
		if ( empty( self::$rerouted[ $url ] ) ) {
			return;
		}

		if ( ! is_wp_error( $response ) ) {
			return;
		}

		/*
		 * A loopback call failed. Stop using loopback for a few minutes so the
		 * site keeps working on its own, and leave a note for the log.
		 */
		set_transient( self::BREAKER_KEY, 1, self::BREAKER_TTL );

		error_log( sprintf(
			'AUN Local Upstream: loopback failed for %s (%s) - reverting to DNS for %d seconds.',
			$url,
			$response->get_error_message(),
			self::BREAKER_TTL
		) );
	}
}

AUN_Local_Upstream::boot();
