<?php
/**
 * Plugin Name: AUN Social Login
 * Description: Lightweight, security-first "Sign in with Google / Facebook" for WooCommerce, including Google One Tap. Renders brand icon buttons under the WooCommerce login, register and checkout forms. Replaces the abandoned Super Socializer plugin — social login only, no sharing/comments.
 * Version:     1.5.0
 * Author:      Smart Living Bangladesh
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 *
 * SECURITY MODEL (read before changing anything in class-aun-sl-oauth.php):
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
 *  - Failure messages travel as short CODES in ?aun_sl_error=, never as sentences.
 *    Escaping stops markup but not a convincing sentence, and a free-text notice
 *    would let anyone hand a customer a link to OUR domain, in OUR error styling,
 *    saying whatever they liked. An unknown code renders nothing.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AUN_SL_VERSION', '1.5.0' );
define( 'AUN_SL_FILE', __FILE__ );
define( 'AUN_SL_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUN_SL_URL', plugin_dir_url( __FILE__ ) );

/*
 * Credentials may live in wp-config.php instead of the database (recommended):
 *   define( 'AUN_SL_GOOGLE_CLIENT_SECRET', '...' );
 *   define( 'AUN_SL_FACEBOOK_APP_SECRET',  '...' );
 * A constant always wins over the stored value.
 */

require_once AUN_SL_DIR . 'includes/class-aun-sl-options.php';
require_once AUN_SL_DIR . 'includes/class-aun-sl-oauth.php';
require_once AUN_SL_DIR . 'includes/class-aun-sl-avatar.php';
require_once AUN_SL_DIR . 'includes/class-aun-sl-onetap.php';
require_once AUN_SL_DIR . 'includes/class-aun-sl-ui.php';

AUN_SL_OAuth::init();
AUN_SL_Avatar::init();
AUN_SL_OneTap::init();
AUN_SL_UI::init();

if ( is_admin() ) {
	require_once AUN_SL_DIR . 'includes/class-aun-sl-settings.php';
	AUN_SL_Settings::init();
}
