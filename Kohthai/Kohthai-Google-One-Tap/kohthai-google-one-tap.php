<?php
/**
 * Plugin Name: Kohthai Google One Tap Login
 * Description: A lightning-fast, seamless Google One Tap sign-in integration for WordPress and WooCommerce.
 * Version: 1.2.0
 * Author: Smart Living Bangladesh
 */

if (!defined('ABSPATH')) exit;

class AUN_Google_One_Tap {

    public static function init() {
        // Admin Settings
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Frontend Display
        add_action('wp_footer', [__CLASS__, 'render_one_tap_ui']);

        // AJAX Handler for Login
        add_action('wp_ajax_nopriv_kohthai_verify_google_login', [__CLASS__, 'process_login']);

        // Fresh-nonce endpoint — see mint_nonce().
        add_action('wp_ajax_nopriv_kohthai_google_nonce', [__CLASS__, 'mint_nonce']);
        add_action('wp_ajax_kohthai_google_nonce',        [__CLASS__, 'mint_nonce']);
    }

    /**
     * Create the Admin Menu Item
     */
    public static function add_settings_page() {
        add_options_page(
            'Google One Tap Settings',
            'Google One Tap',
            'manage_options',
            'kohthai-google-one-tap',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register the database option for the Client ID
     */
    public static function register_settings() {
        register_setting('kohthai_google_one_tap_group', 'kohthai_google_client_id');
    }

    /**
     * Render the Admin Settings Page UI
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AUN Google One Tap Login</h1>
            <p>Enter your Google OAuth 2.0 Client ID below to activate the One Tap login prompt on your website.</p>
            <form method="post" action="options.php">
                <?php settings_fields('kohthai_google_one_tap_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Client ID</th>
                        <td>
                            <input type="text" name="kohthai_google_client_id" value="<?php echo esc_attr(get_option('kohthai_google_client_id')); ?>" style="width: 100%; max-width: 600px;" placeholder="e.g., 123456789-abcde.apps.googleusercontent.com" />
                            <p class="description">Make sure your website domain is added to the "Authorized JavaScript origins" in your Google Cloud Console.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display the Google One Tap UI on the frontend
     */
    public static function render_one_tap_ui() {
        // Don't show if the user is already logged in
        if (is_user_logged_in()) return;

        // ONLY show on WooCommerce Account and Checkout pages
        if (function_exists('is_account_page') && !is_account_page() && !is_checkout()) return;

        $client_id = get_option('kohthai_google_client_id');
        if (empty($client_id)) return; // Don't show if no ID is set

        ?>
        <!-- Load Google's official Identity Services library -->
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        
        <!-- Configure the One Tap Prompt -->
        <div id="g_id_onload"
             data-client_id="<?php echo esc_attr($client_id); ?>"
             data-context="signin"
             data-ux_mode="popup"
             data-callback="kohthaiHandleGoogleCredential"
             data-auto_prompt="true">
        </div>

        <script>
            function kohthaiHandleGoogleCredential(response) {
                /* WP ROCKET: the nonce is fetched at the moment of use rather
                   than printed here. A nonce baked into cached HTML goes stale
                   within a day, after which One Tap sign-in fails for every
                   visitor until the cache is cleared. */
                var ktAjax = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';

                fetch(ktAjax + '?action=kohthai_google_nonce', { credentials: 'same-origin' })
                .then(res => res.json())
                .then(n => fetch(ktAjax, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'kohthai_verify_google_login',
                        credential: response.credential,
                        security: (n && n.data && n.data.nonce) ? n.data.nonce : ''
                    })
                }))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the page so the user is instantly logged in visually
                        window.location.reload(); 
                    } else {
                        console.error('Google One Tap Error:', data.data);
                    }
                })
                .catch(err => console.error('Network Error:', err));
            }
        </script>
        <?php
    }

    /**
     * Hand out a freshly minted nonce.
     *
     * Not nonce-guarded itself: requiring a nonce to obtain a nonce would defeat
     * the purpose. It reveals nothing a page load does not already reveal, and
     * the login it protects still verifies the Google credential with Google.
     */
    public static function mint_nonce() {
        wp_send_json_success(['nonce' => wp_create_nonce('kohthai_google_nonce')]);
    }

    /**
     * Process the Login/Registration securely on the Backend
     */
    public static function process_login() {
        // Soft check: a hard check_ajax_referer() would wp_die('-1'), which the
        // JS can only surface as a generic network error.
        if ( ! check_ajax_referer('kohthai_google_nonce', 'security', false) ) {
            wp_send_json_error(['message' => 'Your session expired. Please reload the page.'], 403);
        }

        $credential = isset($_POST['credential']) ? sanitize_text_field($_POST['credential']) : '';
        if (empty($credential)) {
            wp_send_json_error('No credential provided.');
        }

        // Verify the JWT token directly with Google's servers for maximum security
        $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $credential;
        $response = wp_remote_get($verify_url);

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to Google validation servers.');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Ensure the token is valid and contains an email address
        if (empty($body['email']) || empty($body['sub'])) {
            wp_send_json_error('Invalid or expired Google Token.');
        }

        $email = sanitize_email($body['email']);
        $user = get_user_by('email', $email);

        // If the user doesn't exist, register them silently
        if (!$user) {
            $password = wp_generate_password(16, false);
            
            // Safely create a WooCommerce customer if WC is active, otherwise standard WP user
            if (function_exists('wc_create_new_customer')) {
                $user_id = wc_create_new_customer($email, $email, $password);
            } else {
                $user_id = wp_create_user($email, $password, $email);
            }

            if (is_wp_error($user_id)) {
                wp_send_json_error('Could not register account: ' . $user_id->get_error_message());
            }
            
            // Update their first and last name from their Google profile
            wp_update_user([
                'ID' => $user_id,
                'first_name' => sanitize_text_field($body['given_name'] ?? ''),
                'last_name'  => sanitize_text_field($body['family_name'] ?? '')
            ]);

            $user = get_user_by('id', $user_id);
        }

        // Securely log the user into WordPress
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        
        wp_send_json_success('Successfully logged in.');
    }
}

AUN_Google_One_Tap::init();