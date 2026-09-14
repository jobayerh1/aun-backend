<?php
/**
 * Plugin Name: AUN Google One Tap Login
 * Description: A lightning-fast, seamless Google One Tap sign-in integration for WordPress and WooCommerce.
 * Version:     1.3.0
 * Author:      Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Google_One_Tap {

    public static function init() {
        add_action( 'admin_menu',                          [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_init',                          [ __CLASS__, 'register_settings' ] );
        add_action( 'wp_footer',                           [ __CLASS__, 'render_one_tap_ui' ] );
        add_action( 'wp_ajax_nopriv_aun_verify_google_login', [ __CLASS__, 'process_login' ] );
        // Logged-in handler — returns a graceful "already logged in" response
        // rather than WordPress's default -1 / 400 for unregistered actions.
        add_action( 'wp_ajax_aun_verify_google_login',    [ __CLASS__, 'process_login' ] );

        // Fresh-nonce endpoint — see mint_nonce().
        add_action( 'wp_ajax_nopriv_aun_google_nonce', [ __CLASS__, 'mint_nonce' ] );
        add_action( 'wp_ajax_aun_google_nonce',        [ __CLASS__, 'mint_nonce' ] );
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public static function add_settings_page() {
        add_options_page(
            'Google One Tap Settings',
            'Google One Tap',
            'manage_options',
            'aun-google-one-tap',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function register_settings() {
        register_setting(
            'aun_google_one_tap_group',
            'aun_google_client_id',
            [ 'sanitize_callback' => 'sanitize_text_field' ]
        );
    }

    public static function render_settings_page() {
        // Re-verify capability — add_options_page() checks it for menu display
        // but the callback itself must check too, as anyone can hit the URL directly.
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>AUN Google One Tap Login</h1>
            <p>Enter your Google OAuth 2.0 Client ID to activate the One Tap login prompt on account and checkout pages.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'aun_google_one_tap_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Client ID</th>
                        <td>
                            <input type="text"
                                   name="aun_google_client_id"
                                   value="<?php echo esc_attr( get_option( 'aun_google_client_id' ) ); ?>"
                                   style="width:100%;max-width:600px;"
                                   placeholder="e.g. 123456789-abcde.apps.googleusercontent.com">
                            <p class="description">
                                Make sure your domain is listed in <strong>Authorised JavaScript origins</strong>
                                in your Google Cloud Console project.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Front-end
    // -------------------------------------------------------------------------

    public static function render_one_tap_ui() {
        if ( is_user_logged_in() ) return;

        // Only show on WooCommerce account and checkout pages.
        if ( function_exists( 'is_account_page' ) && ! is_account_page() && ! is_checkout() ) return;

        $client_id = get_option( 'aun_google_client_id' );
        if ( empty( $client_id ) ) return;

        // Enqueue the Google Identity Services library through WordPress so it
        // can be optimised, deduplicated, and cache-busted by the enqueue system.
        // The async + defer attributes are added via the script_loader_tag filter below.
        wp_enqueue_script( 'google-gsi', 'https://accounts.google.com/gsi/client', [], null, true );
        add_filter( 'script_loader_tag', [ __CLASS__, 'add_async_defer_to_gsi' ], 10, 2 );
        ?>

        <div id="g_id_onload"
             data-client_id="<?php echo esc_attr( $client_id ); ?>"
             data-context="signin"
             data-ux_mode="popup"
             data-callback="aunHandleGoogleCredential"
             data-auto_prompt="true">
        </div>

        <script>
        /* WP ROCKET: the nonce is fetched at the moment of use rather than
           printed here. A nonce baked into cached HTML goes stale within a day,
           after which One Tap sign-in fails for every visitor until the cache is
           cleared. Same approach as the AUN Social Login One Tap. */
        function aunHandleGoogleCredential(response) {
            var aunAjax = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

            fetch(aunAjax + '?action=aun_google_nonce', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(n) {
                return fetch(aunAjax, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action:     'aun_verify_google_login',
                        credential: response.credential,
                        security:   (n && n.data && n.data.nonce) ? n.data.nonce : ''
                    })
                });
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    // Use the server-supplied redirect URL so WooCommerce-aware
                    // redirects (e.g. back to checkout) work correctly.
                    window.location.href = (data.data && data.data.redirect)
                        ? data.data.redirect
                        : window.location.href;
                } else {
                    // Show a visible notice — console.error is invisible to customers.
                    var msg = (data.data && data.data.message)
                        ? data.data.message
                        : 'Google sign-in failed. Please try again or use a different method.';
                    alert(msg);
                    console.error('AUN Google One Tap error:', data.data);
                }
            })
            .catch(function(err) {
                alert('A network error occurred. Please check your connection and try again.');
                console.error('AUN Google One Tap network error:', err);
            });
        }
        </script>
        <?php
    }

    /** Add async + defer to the Google GSI script tag only. */
    public static function add_async_defer_to_gsi( string $tag, string $handle ): string {
        if ( $handle !== 'google-gsi' ) return $tag;
        return str_replace( '<script ', '<script async defer ', $tag );
    }

    // -------------------------------------------------------------------------
    // AJAX — token verification and login
    // -------------------------------------------------------------------------

    /**
     * Hand out a freshly minted nonce.
     *
     * Not nonce-guarded itself: requiring a nonce to obtain a nonce would defeat
     * the purpose. It reveals nothing a page load does not already reveal, and
     * the login it protects still verifies the Google credential with Google.
     */
    public static function mint_nonce() {
        wp_send_json_success( [ 'nonce' => wp_create_nonce( 'aun_google_nonce' ) ] );
    }

    public static function process_login() {
        // If the user is already logged in (e.g. from a cached page), exit gracefully.
        if ( is_user_logged_in() ) {
            wp_send_json_success( [ 'redirect' => self::get_redirect_url(), 'message' => 'Already logged in.' ] );
        }

        // Soft check: a hard check_ajax_referer() would wp_die('-1'), which the
        // JS can only surface as a generic network error.
        if ( ! check_ajax_referer( 'aun_google_nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => 'Your session expired. Please reload the page.' ], 403 );
        }

        // Validate JWT format: three base64url-encoded segments separated by dots.
        // This rejects obviously malformed payloads before sending anything to Google.
        $credential = isset( $_POST['credential'] ) ? wp_unslash( $_POST['credential'] ) : '';
        if ( empty( $credential ) || ! preg_match( '/^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$/', $credential ) ) {
            wp_send_json_error( [ 'message' => 'Invalid credential format.' ] );
        }

        // Verify the token with Google.
        // NOTE: tokeninfo is Google's debugging endpoint and should ideally be
        // replaced with local JWT verification using Google's public keys from
        // https://www.googleapis.com/oauth2/v3/certs — but since this plugin has
        // no Composer dependencies, we use tokeninfo with full claim validation
        // as the practical alternative.
        $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $credential );
        $response   = wp_remote_get( $verify_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Could not reach Google verification servers.' ] );
        }

        // A non-200 status means the token is invalid or expired.
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            wp_send_json_error( [ 'message' => 'Google rejected the token. Please try signing in again.' ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // ── Claim validation ──────────────────────────────────────────────────
        // All four checks below are defence-in-depth on top of tokeninfo's own
        // validation. Without them, a valid token from ANY Google OAuth app or
        // an expired token could create/log in an account on this site.

        // 1. Audience — the token must have been issued for THIS site's Client ID.
        $client_id = get_option( 'aun_google_client_id' );
        if ( empty( $body['aud'] ) || $body['aud'] !== $client_id ) {
            wp_send_json_error( [ 'message' => 'Token audience mismatch.' ] );
        }

        // 2. Issuer — must be Google, not some other provider.
        $valid_issuers = [ 'accounts.google.com', 'https://accounts.google.com' ];
        if ( empty( $body['iss'] ) || ! in_array( $body['iss'], $valid_issuers, true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid token issuer.' ] );
        }

        // 3. Expiry — token must not be expired.
        if ( empty( $body['exp'] ) || (int) $body['exp'] < time() ) {
            wp_send_json_error( [ 'message' => 'Token has expired. Please try again.' ] );
        }

        // 4. Email verified — Google may issue tokens for unverified email addresses.
        if ( empty( $body['email_verified'] ) || $body['email_verified'] !== 'true' ) {
            wp_send_json_error( [ 'message' => 'Your Google account email address is not verified.' ] );
        }

        // ── Payload ───────────────────────────────────────────────────────────
        if ( empty( $body['email'] ) || empty( $body['sub'] ) ) {
            wp_send_json_error( [ 'message' => 'Incomplete token payload.' ] );
        }

        $email = sanitize_email( $body['email'] );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Invalid email address in token.' ] );
        }

        $user = get_user_by( 'email', $email );

        // ── Register if new ───────────────────────────────────────────────────
        if ( ! $user ) {
            $password = wp_generate_password( 16, false );

            if ( function_exists( 'wc_create_new_customer' ) ) {
                $user_id = wc_create_new_customer( $email, $email, $password );
            } else {
                $user_id = wp_create_user( $email, $password, $email );
            }

            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( [ 'message' => 'Could not create account: ' . $user_id->get_error_message() ] );
            }

            wp_update_user( [
                'ID'         => $user_id,
                'first_name' => sanitize_text_field( $body['given_name']  ?? '' ),
                'last_name'  => sanitize_text_field( $body['family_name'] ?? '' ),
            ] );

            $user = get_user_by( 'ID', $user_id ); // capital 'ID' is correct
        }

        // ── Log in ────────────────────────────────────────────────────────────
        wp_set_current_user( $user->ID );
        // false = session cookie only; the user never opted into "remember me".
        wp_set_auth_cookie( $user->ID, false );

        wp_send_json_success( [ 'redirect' => self::get_redirect_url() ] );
    }

    /**
     * Returns the appropriate post-login redirect URL.
     * Checkout stays on checkout so the customer can complete their order.
     * Account pages redirect to the My Account dashboard.
     * Everything else reloads the current page.
     */
    private static function get_redirect_url(): string {
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return wc_get_checkout_url();
        }
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        return home_url();
    }
}

AUN_Google_One_Tap::init();