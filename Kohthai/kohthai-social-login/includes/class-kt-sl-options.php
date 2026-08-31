<?php
/**
 * Settings storage, defaults and sanitisation.
 *
 * Secrets can be supplied by wp-config constants; get() transparently prefers them
 * so a site can keep credentials out of the database entirely.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class KT_SL_Options {

	const OPTION = 'kt_sl_options';

	public static function defaults() {
		return array(
			// Providers
			'google_enabled'       => 0,
			'onetap_enabled'       => 0,
			// In-page sign-in: Google's account chooser + a Facebook popup,
			// each falling back to the redirect flow if it cannot run.
			'js_flow'              => 1,
			/*
			 * How the Google button is drawn, and it is a genuine either/or:
			 *   'native' — Google renders it. Gets the browser's FedCM account
			 *              dialog, but Google controls the wording and styling.
			 *   'custom' — we render it. Any label we like (e.g. just "Google"),
			 *              opens our popup window, but NO FedCM dialog.
			 * Sites showing a plain "Google" label AND the dialog are using the
			 * legacy gapi.auth2 platform library, which Google retired in March
			 * 2023 and keeps alive only through a migration shim. Not something to
			 * build on.
			 */
			'google_button'        => 'native', // native | custom
			'google_client_id'     => '',
			'google_client_secret' => '',
			'facebook_enabled'     => 0,
			'facebook_app_id'      => '',
			'facebook_app_secret'  => '',

			// Appearance (mirrors the old Super Socializer look)
			'title'      => 'Or login with',
			'shape'      => 'round',    // round | rounded | square
			'size'       => 44,         // px
			'align'      => 'center',   // left | center | right
			'show_label' => 0,          // 1 = wide buttons with text
			// Google allows exactly four phrasings and no bare "Google" — see
			// GsiButtonConfiguration. Facebook's label mirrors whichever is chosen
			// so the two buttons read as a pair.
			'label_style' => 'continue_with', // continue_with | signin_with | signup_with | signin

			// Placement
			'at_wc_login'        => 1,
			'at_wc_login_before' => 0,
			'at_wc_register'     => 1,
			'at_wc_checkout' => 1,
			'at_wc_cart'     => 1,
			'at_wp_login'    => 0,

			// Behaviour
			'allow_register' => 1,
			'link_by_email'  => 1,
			'use_avatar'     => 1,
			'block_admins'   => 1,
			'notify_admin'   => 1,
		);
	}

	/** Raw stored options merged over defaults. */
	public static function all() {
		$o = get_option( self::OPTION, array() );
		if ( ! is_array( $o ) ) { $o = array(); }
		$o = array_merge( self::defaults(), $o );

		// Seamless upgrade from the standalone kohthai-google-one-tap plugin: adopt the
		// Client ID it already stored so nothing has to be re-typed.
		if ( $o['google_client_id'] === '' ) {
			$legacy = get_option( 'kohthai_google_client_id', '' );
			if ( is_string( $legacy ) && $legacy !== '' ) {
				$o['google_client_id'] = $legacy;
			}
		}
		return $o;
	}

	/**
	 * One option. Secrets defined in wp-config.php take priority over the DB copy.
	 */
	public static function get( $key ) {
		$map = array(
			'google_client_secret' => 'KT_SL_GOOGLE_CLIENT_SECRET',
			'facebook_app_secret'  => 'KT_SL_FACEBOOK_APP_SECRET',
			'google_client_id'     => 'KT_SL_GOOGLE_CLIENT_ID',
			'facebook_app_id'      => 'KT_SL_FACEBOOK_APP_ID',
		);
		if ( isset( $map[ $key ] ) && defined( $map[ $key ] ) && constant( $map[ $key ] ) !== '' ) {
			return (string) constant( $map[ $key ] );
		}
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** True when the provider is switched on AND fully configured. */
	public static function provider_ready( $provider ) {
		if ( 'google' === $provider ) {
			return self::get( 'google_enabled' ) && self::get( 'google_client_id' ) !== '' && self::get( 'google_client_secret' ) !== '';
		}
		if ( 'facebook' === $provider ) {
			return self::get( 'facebook_enabled' ) && self::get( 'facebook_app_id' ) !== '' && self::get( 'facebook_app_secret' ) !== '';
		}
		return false;
	}

	/** Providers ready to be shown, in display order. */
	public static function active_providers() {
		$out = array();
		foreach ( array( 'google', 'facebook' ) as $p ) {
			if ( self::provider_ready( $p ) ) { $out[] = $p; }
		}
		return $out;
	}

	/**
	 * Whitelist + sanitise every field. Anything not listed here is discarded,
	 * so a crafted POST cannot inject unexpected keys into the option array.
	 */
	public static function sanitize( $input ) {
		$d   = self::defaults();
		$old = self::all();
		$out = array();

		foreach ( array( 'google_enabled', 'onetap_enabled', 'js_flow', 'facebook_enabled', 'show_label', 'at_wc_login', 'at_wc_login_before', 'at_wc_register', 'at_wc_checkout', 'at_wc_cart', 'at_wp_login', 'allow_register', 'link_by_email', 'block_admins', 'notify_admin', 'use_avatar' ) as $k ) {
			$out[ $k ] = ( ! empty( $input[ $k ] ) ) ? 1 : 0;
		}

		foreach ( array( 'google_client_id', 'facebook_app_id' ) as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( trim( (string) $input[ $k ] ) ) : '';
		}

		// Secrets: an empty box means "keep what is stored" so the value never has
		// to be re-typed (and is never printed back into the page).
		foreach ( array( 'google_client_secret', 'facebook_app_secret' ) as $k ) {
			$typed = isset( $input[ $k ] ) ? trim( (string) $input[ $k ] ) : '';
			$out[ $k ] = ( $typed === '' ) ? (string) ( $old[ $k ] ?? '' ) : sanitize_text_field( $typed );
		}

		$out['title'] = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $d['title'];

		$shape        = isset( $input['shape'] ) ? $input['shape'] : $d['shape'];
		$out['shape'] = in_array( $shape, array( 'round', 'rounded', 'square' ), true ) ? $shape : $d['shape'];

		$gb = isset( $input['google_button'] ) ? $input['google_button'] : $d['google_button'];
		$out['google_button'] = ( 'custom' === $gb ) ? 'custom' : 'native';

		$ls = isset( $input['label_style'] ) ? $input['label_style'] : $d['label_style'];
		$out['label_style'] = in_array( $ls, array( 'continue_with', 'signin_with', 'signup_with', 'signin', 'brand' ), true ) ? $ls : $d['label_style'];

		$align        = isset( $input['align'] ) ? $input['align'] : $d['align'];
		$out['align'] = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : $d['align'];

		$size        = isset( $input['size'] ) ? absint( $input['size'] ) : $d['size'];
		$out['size'] = max( 28, min( 72, $size ) );

		return $out;
	}
}
