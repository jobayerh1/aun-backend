<?php
/**
 * Plugin Name: Kohthai Social Login
 * Description: Lightweight, security-first "Sign in with Google / Facebook" for WooCommerce, including Google One Tap. Renders brand icon buttons under the WooCommerce login, register and checkout forms. Replaces the abandoned Super Socializer plugin — social login only, no sharing/comments.
 * Version:     1.9.1
 * Author:      Smart Living Bangladesh
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 *
 * KOHTHAI PORT. This file tracks aun-social-login upstream and deliberately keeps
 * its VERSION NUMBER in step (this is AUN 1.9.1), so "is Kohthai in sync?" is
 * answerable at a glance. It is otherwise identical except for:
 *   - namespace: KT_SL_* / kt_sl_* / .kt-sl-* / [kohthai_social_login]
 *   - the legacy Client ID adopted from Kohthai's old One Tap plugin
 *     (option 'kohthai_google_client_id')
 *   - Kohthai palette: chocolate #654321 focus ring + settings callout on cream
 *     #faf7f3; tan #a08565 divider caption on #e6ded5 rules
 *   - two copy edits that referenced AUN's phone-OTP plugin, which Kohthai
 *     does not run
 * Keep it that way: structural parity is what makes the next port a rename.
 *
 * SECURITY MODEL (read before changing anything in class-kt-sl-oauth.php):
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
 *  - Failure messages travel as short CODES in ?kt_sl_error=, never as sentences.
 *    Escaping stops markup but not a convincing sentence, and a free-text notice
 *    would let anyone hand a customer a link to OUR domain, in OUR error styling,
 *    saying whatever they liked. An unknown code renders nothing.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'KT_SL_VERSION', '1.9.1' );
define( 'KT_SL_FILE', __FILE__ );
define( 'KT_SL_DIR', plugin_dir_path( __FILE__ ) );
define( 'KT_SL_URL', plugin_dir_url( __FILE__ ) );

/*
 * Credentials may live in wp-config.php instead of the database (recommended):
 *   define( 'KT_SL_GOOGLE_CLIENT_SECRET', '...' );
 *   define( 'KT_SL_FACEBOOK_APP_SECRET',  '...' );
 * A constant always wins over the stored value.
 */

require_once KT_SL_DIR . 'includes/class-kt-sl-options.php';
require_once KT_SL_DIR . 'includes/class-kt-sl-oauth.php';
require_once KT_SL_DIR . 'includes/class-kt-sl-avatar.php';
require_once KT_SL_DIR . 'includes/class-kt-sl-popup.php';
require_once KT_SL_DIR . 'includes/class-kt-sl-onetap.php';
require_once KT_SL_DIR . 'includes/class-kt-sl-ui.php';

KT_SL_OAuth::init();
KT_SL_Avatar::init();
KT_SL_OneTap::init();
KT_SL_Popup::init();
KT_SL_UI::init();

if ( is_admin() ) {
	require_once KT_SL_DIR . 'includes/class-kt-sl-settings.php';
	KT_SL_Settings::init();
}
