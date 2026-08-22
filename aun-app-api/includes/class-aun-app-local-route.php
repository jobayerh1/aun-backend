<?php
/**
 * Keep server-to-server calls ON the server.
 *
 * The ERP (portal.smartliving.com.bd) and the osTicket bridge
 * (support.smartliving.com.bd) live on the SAME machine as this WordPress. But
 * their DNS is on Cloudflare, so a call to them left the building, crossed to a
 * Cloudflare edge, and came back to the machine it started on.
 *
 * Measured on the live server (2026-08-19, three runs each, best/worst):
 *
 *              via Cloudflare        pinned to the machine
 *   ERP        132 ms (worst 187)    33 ms (worst 38)
 *   bridge     103 ms (worst 302)    17 ms (worst 26)
 *
 * ~90 ms off the typical call — but the WORST case is the real prize: the
 * Cloudflare route swung between 103 ms and 302 ms, the local route between
 * 17 ms and 26 ms. A customer notices the bad day, not the median.
 *
 * ── How ──────────────────────────────────────────────────────────────────
 *
 * CURLOPT_RESOLVE, which changes only the IP curl dials. The URL, the Host
 * header and the TLS SNI stay exactly as they were, so the vhost still routes
 * to the right site and the request is byte-identical at the other end.
 *
 * ⚠️ NOT 127.0.0.1. Loopback answers on :443 but with the WRONG vhost — it
 * returned 404 for both hostnames when measured. The machine's own public
 * address routes correctly. That is why this pins to SERVER_ADDR and not to
 * localhost, and why a "surely loopback is even faster" edit would break both
 * services.
 *
 * ⚠️ Certificate verification is turned OFF for pinned calls, and only for
 * them. The origin serves a Cloudflare Origin Certificate, which is
 * deliberately not publicly trusted, so verification cannot succeed. It exists
 * to prove you reached the intended machine; here the intended machine is the
 * one running this code, and the packets never reach a network where anyone
 * could sit in the middle.
 *
 * ── When it backs off ────────────────────────────────────────────────────
 *
 * The IP is READ FROM THE SERVER, never hardcoded, so a host migration cannot
 * strand us pointing at somebody else's machine. And any failure on a pinned
 * call disables pinning for 10 minutes and retries the normal way, so the
 * worst case is one slow call rather than a broken repair screen.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_App_Local_Route {

	/** Where the last-known server IP is kept. */
	const OPT_IP = 'aun_app_local_ip';

	/** Set after a pinned call fails; pinning stays off while it lives. */
	const TR_OFF = 'aun_app_local_route_off';

	/** How long to stay on the normal route after a failure. */
	const BACKOFF = 600;

	/** Hosts currently armed, lower-cased. Empty when disarmed. */
	private static $hosts = array();

	/**
	 * Remember the machine's own address whenever a web request tells us.
	 *
	 * SERVER_ADDR is absent under WP-CLI and cron, which is exactly when the
	 * repair poll runs — so it is captured during normal traffic and read from
	 * the option later.
	 */
	public static function remember_ip() {
		$ip = isset( $_SERVER['SERVER_ADDR'] ) ? trim( (string) $_SERVER['SERVER_ADDR'] ) : '';
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return;
		}
		if ( (string) get_option( self::OPT_IP, '' ) !== $ip ) {
			update_option( self::OPT_IP, $ip, false );
		}
	}

	/** The machine's own address, or '' if we have never seen one. */
	public static function ip() {
		$ip = isset( $_SERVER['SERVER_ADDR'] ) ? trim( (string) $_SERVER['SERVER_ADDR'] ) : '';
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = (string) get_option( self::OPT_IP, '' );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** Master switch: a constant wins, then the option, default ON. */
	public static function enabled() {
		if ( defined( 'AUN_APP_NO_LOCAL_ROUTE' ) && AUN_APP_NO_LOCAL_ROUTE ) {
			return false;
		}
		$opts = function_exists( 'aun_app_api_get_options' ) ? aun_app_api_get_options() : array();
		if ( isset( $opts['local_route'] ) && ! $opts['local_route'] ) {
			return false;
		}
		return ! get_transient( self::TR_OFF );
	}

	/**
	 * Pin these hostnames to this machine for the duration of one call.
	 *
	 * @param string $url The URL about to be requested.
	 * @return bool Whether pinning was actually armed (so the caller knows
	 *              whether a failure is worth retrying unpinned).
	 */
	public static function arm( $url ) {
		if ( ! self::enabled() ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$ip   = self::ip();
		if ( '' === $host || '' === $ip ) {
			return false;
		}
		// Never pin this site's own hostname: a loopback request to ourselves
		// while we are mid-request is how you deadlock a single PHP worker.
		if ( $host === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return false;
		}
		self::$hosts = array( $host );
		add_action( 'http_api_curl', array( __CLASS__, 'apply' ), 10, 3 );
		return true;
	}

	/** Take the pin off again. Always call this, including on failure. */
	public static function disarm() {
		self::$hosts = array();
		remove_action( 'http_api_curl', array( __CLASS__, 'apply' ), 10 );
	}

	/**
	 * The actual cURL tweak.
	 *
	 * @param resource|object $handle cURL handle (by reference from WP).
	 * @param array           $args   Request args.
	 * @param string          $url    Request URL.
	 */
	public static function apply( $handle, $args, $url ) {
		if ( empty( self::$hosts ) ) {
			return;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! in_array( $host, self::$hosts, true ) ) {
			return; // a different request slipped through while we were armed
		}
		$ip = self::ip();
		if ( '' === $ip ) {
			return;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$port   = (int) wp_parse_url( $url, PHP_URL_PORT );
		if ( ! $port ) {
			$port = 'https' === $scheme ? 443 : 80;
		}
		curl_setopt( $handle, CURLOPT_RESOLVE, array( "$host:$port:$ip" ) );
		if ( 'https' === $scheme ) {
			// See the class note: the origin's certificate is a Cloudflare
			// Origin Certificate and cannot validate publicly.
			curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 0 );
		}
	}

	/**
	 * A pinned call failed. Go back to the normal route for a while.
	 *
	 * Ten minutes, not for ever: the usual cause is a transient server-side
	 * change (a vhost reload, an IP migration mid-flight), and a permanent
	 * switch-off would mean nobody ever notices it recovered.
	 */
	public static function note_failure( $why = '' ) {
		set_transient( self::TR_OFF, current_time( 'mysql' ) . ' ' . $why, self::BACKOFF );
	}

	/** For the admin screen: is it on, off, or backed off right now? */
	public static function status() {
		if ( defined( 'AUN_APP_NO_LOCAL_ROUTE' ) && AUN_APP_NO_LOCAL_ROUTE ) {
			return 'off (AUN_APP_NO_LOCAL_ROUTE)';
		}
		$backoff = get_transient( self::TR_OFF );
		if ( $backoff ) {
			return 'paused after a failure — ' . (string) $backoff;
		}
		$ip = self::ip();
		return '' === $ip ? 'waiting for a web request to learn the server IP' : 'on, pinned to ' . $ip;
	}
}
