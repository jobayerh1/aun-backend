<?php
/**
 * Plugin Name: AUN Edge Cache Helper
 * Version: 1.1.0
 * Description: Makes the app's public REST routes edge-cacheable, and stops the Facebook Conversions API from blocking page render.
 * Author: AUN
 *
 * INSTALL: upload to wp-content/mu-plugins/ (auto-runs, nothing to activate).
 * REVERT:  delete the file. Nothing persists.
 *
 * TWO INDEPENDENT JOBS
 *
 *  A) Public app routes -> cacheable.
 *     ping/config/models/dealers/content/watch return identical bytes to every
 *     caller (verified: no user context in those handlers). They currently send
 *     Cache-Control: max-age=0 plus two irrelevant cookies, which makes
 *     Cloudflare skip them. This sends a real Cache-Control and drops the
 *     cookies so the edge can cache them.
 *     User-specific routes (/me/*, /auth/*, warranty, devices) are NOT touched.
 *
 *  B) Facebook CAPI -> non-blocking.
 *     The plugin POSTs to graph.facebook.com during page render with a 60s
 *     timeout, so a slow Facebook response stalls checkout. The event is still
 *     sent; we just stop waiting for the reply. Ad tracking is unaffected.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AUN_Edge_Cache {

	/** Seconds the edge and browser may reuse a public app response. */
	const TTL = 300;

	/** Set false to disable the Facebook change while keeping the cache change. */
	const FIX_FACEBOOK = true;

	/**
	 * Public, user-independent app routes.
	 * Anything not matched here keeps WordPress's default no-cache behaviour.
	 */
	private static $public_routes = array(
		'#^/wp-json/aun-app/v1/ping/?$#',
		'#^/wp-json/aun-app/v1/config/?$#',
		'#^/wp-json/aun-app/v1/models/?$#',
		'#^/wp-json/aun-app/v1/dealers/?$#',
		'#^/wp-json/aun-app/v1/content/?$#',
		'#^/wp-json/aun-app/v1/watch/?$#',
		'#^/wp-json/aun-app/v1/content/\d+/?$#',
		'#^/wp-json/aun-app/v1/models/\d+/content/?$#',
	);

	/** Set once dispatch decides this route is public. */
	private static $cacheable = false;

	public static function boot() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'maybe_make_cacheable' ), 20, 3 );

		/*
		 * Headers set on the response object were being overwritten further down
		 * the stack, so send them directly, as late as possible.
		 */
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'send_headers' ), PHP_INT_MAX, 4 );

		if ( self::FIX_FACEBOOK ) {
			add_filter( 'http_request_args', array( __CLASS__, 'unblock_facebook' ), 10, 2 );
		}
	}

	/**
	 * Send cache headers and drop cookies on public app routes.
	 */
	public static function maybe_make_cacheable( $response, $server, $request ) {
		if ( 'GET' !== $request->get_method() ) {
			return $response;
		}

		// Never cache a response produced for a signed-in user.
		if ( is_user_logged_in() ) {
			return $response;
		}

		if ( $response->is_error() ) {
			return $response;
		}

		$path = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$hit  = false;

		foreach ( self::$public_routes as $pattern ) {
			if ( preg_match( $pattern, $path ) ) {
				$hit = true;
				break;
			}
		}

		if ( ! $hit ) {
			return $response;
		}

		/*
		 * These cookies are set by the Facebook and Download Manager plugins on
		 * every request. On an API call from the app there is no browser to read
		 * them, and their presence alone stops Cloudflare caching the response.
		 */
		if ( ! headers_sent() ) {
			header_remove( 'Set-Cookie' );
			header_remove( 'Expires' );
			header_remove( 'Pragma' );
		}

		self::$cacheable = true;

		return $response;
	}

	/**
	 * Emit the cache headers last, overriding anything set earlier.
	 */
	public static function send_headers( $served, $result, $request, $server ) {
		if ( ! self::$cacheable || headers_sent() ) {
			return $served;
		}

		header( sprintf( 'Cache-Control: public, max-age=%d, s-maxage=%d', self::TTL, self::TTL ), true );
		header( 'Vary: Accept-Encoding', true );
		header( 'X-AUN-Edge: cacheable', true );
		header_remove( 'Set-Cookie' );
		header_remove( 'Expires' );
		header_remove( 'Pragma' );

		return $served;
	}

	/**
	 * Stop waiting for Facebook. The event is still dispatched.
	 */
	public static function unblock_facebook( $args, $url ) {
		if ( false === stripos( $url, 'graph.facebook.com' ) ) {
			return $args;
		}

		// Only fire-and-forget the event pixel calls, not product catalogue sync.
		if ( false === stripos( $url, '/events' ) ) {
			$args['timeout'] = min( (int) ( $args['timeout'] ?? 60 ), 5 );
			return $args;
		}

		$args['blocking'] = false;
		$args['timeout']  = 5;

		return $args;
	}
}

AUN_Edge_Cache::boot();
