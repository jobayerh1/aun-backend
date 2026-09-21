<?php
/**
 * Plugin Name: Breo Social Login
 * Description: Lightweight, security-first "Sign in with Google / Facebook" for WooCommerce, including Google One Tap. Renders brand icon buttons under the WooCommerce login, register and checkout forms. Replaces the abandoned Super Socializer plugin — social login only, no sharing/comments.
 * Version:     1.9.3
 * Author:      Breo Bangladesh
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 *
 * SECURITY MODEL (read before changing anything in class-breo-sl-oauth.php):
 *  - Authorization-code flow, handled entirely server-to-server. The browser never
 *    sees a client secret and never carries a token we trust.
 *  - CSRF: a single-use, server-generated `state` (32 random bytes) stored in a
 *    10-minute transient and compared with hash_equals(). Deleted on first use.
 *  - The login BUTTONS carry no nonce on purpose: this site runs WP Rocket, and a
 *    nonce baked into cached HTML goes stale and breaks the flow for real visitors.
 *    `state` is minted at click time (uncached request), which is the correct place.
 *  - An existing account is only ever matched by (a) a previously linked provider
 *    ID, or (b) a PROVIDER-VERIFIED email. An unverified email can never take over
 *    an existing account — that is the classic social-login account-takeover bug.
 *  - Administrator-capable accounts are refused by default (Settings toggle).
 *  - The OAuth URLs are built from get_option('home'), NOT home_url(). TranslatePress
 *    filters home_url() to add /bn/, which silently gave Bangla visitors a redirect
 *    URI that was not registered with Google — English worked, Bangla did not.
 *  - Failure messages travel as short CODES in ?breo_sl_error=, never as sentences.
 *    Escaping stops markup but not a convincing sentence, and a free-text notice
 *    would let anyone hand a customer a link to OUR domain, in OUR error styling,
 *    saying whatever they liked. An unknown code renders nothing.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BREO_SL_VERSION', '1.9.3' );
define( 'BREO_SL_FILE', __FILE__ );
define( 'BREO_SL_DIR', plugin_dir_path( __FILE__ ) );
define( 'BREO_SL_URL', plugin_dir_url( __FILE__ ) );

/*
 * Credentials may live in wp-config.php instead of the database (recommended):
 *   define( 'BREO_SL_GOOGLE_CLIENT_SECRET', '...' );
 *   define( 'BREO_SL_FACEBOOK_APP_SECRET',  '...' );
 * A constant always wins over the stored value.
 */

require_once BREO_SL_DIR . 'includes/class-breo-sl-options.php';
require_once BREO_SL_DIR . 'includes/class-breo-sl-oauth.php';
require_once BREO_SL_DIR . 'includes/class-breo-sl-avatar.php';
require_once BREO_SL_DIR . 'includes/class-breo-sl-popup.php';
require_once BREO_SL_DIR . 'includes/class-breo-sl-onetap.php';
require_once BREO_SL_DIR . 'includes/class-breo-sl-ui.php';

BREO_SL_OAuth::init();
BREO_SL_Avatar::init();
BREO_SL_OneTap::init();
BREO_SL_Popup::init();
BREO_SL_UI::init();

if ( is_admin() ) {
	require_once BREO_SL_DIR . 'includes/class-breo-sl-settings.php';
	BREO_SL_Settings::init();
}
