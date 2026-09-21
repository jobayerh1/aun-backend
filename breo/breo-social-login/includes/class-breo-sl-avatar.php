<?php
/**
 * Social profile pictures used as the WordPress avatar.
 *
 * The URL is stored as user meta and refreshed on every social sign-in (Google and
 * Facebook CDN links rotate and can expire).
 *
 * SECURITY: a profile picture URL is data that came from a third party, so it is
 * host-checked before it is ever stored — only https URLs on the providers' own
 * CDNs are accepted. That stops a hostile/altered profile payload turning into an
 * arbitrary off-site request (or a javascript:/data: URL) rendered on our pages.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BREO_SL_Avatar {

	const META = 'breo_sl_avatar';

	/** Hosts (or host suffixes) the providers actually serve avatars from. */
	private static $allowed = array(
		'googleusercontent.com',   // lh3.googleusercontent.com
		'fbcdn.net',               // scontent-*.xx.fbcdn.net
		'fbsbx.com',               // platform-lookaside.fbsbx.com
		'graph.facebook.com',
	);

	public static function init() {
		if ( ! BREO_SL_Options::get( 'use_avatar' ) ) {
			return;
		}
		add_filter( 'get_avatar_data', array( __CLASS__, 'filter_avatar_data' ), 10, 2 );
	}

	/**
	 * Accept a provider picture URL, or return '' if it is not one we trust.
	 * Rejects anything that is not https on an allow-listed provider host.
	 */
	public static function sanitize_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}
		// A real CDN link never contains a quote, an angle bracket, a backslash,
		// whitespace or a control character. Refusing them outright means the value
		// cannot depend on every future output site remembering to escape it.
		if ( preg_match( '/[\x00-\x20\x7f"\'<>\\\\`]/', $url ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( $parts['host'] );
		foreach ( self::$allowed as $ok ) {
			// exact host, or a subdomain of an allowed domain
			if ( $host === $ok || substr( $host, - ( strlen( $ok ) + 1 ) ) === '.' . $ok ) {
				return esc_url_raw( $url );
			}
		}
		return '';
	}

	/** Called after a successful sign-in. Stores or clears the avatar. */
	public static function store( $user_id, $url ) {
		$clean = self::sanitize_url( $url );
		if ( $clean === '' ) {
			return false;
		}
		update_user_meta( $user_id, self::META, $clean );
		return true;
	}

	/**
	 * Swap in the stored picture wherever WordPress/WooCommerce asks for an avatar.
	 * Falls straight through to Gravatar when we have nothing for this user.
	 */
	public static function filter_avatar_data( $args, $id_or_email ) {
		if ( ! empty( $args['force_default'] ) ) {
			return $args;
		}
		$user_id = self::resolve_user_id( $id_or_email );
		if ( ! $user_id ) {
			return $args;
		}
		$url = get_user_meta( $user_id, self::META, true );
		$url = self::sanitize_url( $url ); // re-checked on the way out, not just on the way in
		if ( $url === '' ) {
			return $args;
		}
		$args['url']          = $url;
		$args['found_avatar'] = true;
		return $args;
	}

	/** WordPress hands avatars a user id, email, WP_User, WP_Post or WP_Comment. */
	private static function resolve_user_id( $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			return (int) $id_or_email;
		}
		if ( $id_or_email instanceof WP_User ) {
			return (int) $id_or_email->ID;
		}
		if ( $id_or_email instanceof WP_Post ) {
			return (int) $id_or_email->post_author;
		}
		if ( $id_or_email instanceof WP_Comment ) {
			if ( ! empty( $id_or_email->user_id ) ) {
				return (int) $id_or_email->user_id;
			}
			if ( ! empty( $id_or_email->comment_author_email ) ) {
				$u = get_user_by( 'email', $id_or_email->comment_author_email );
				return $u ? (int) $u->ID : 0;
			}
			return 0;
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$u = get_user_by( 'email', $id_or_email );
			return $u ? (int) $u->ID : 0;
		}
		return 0;
	}
}
