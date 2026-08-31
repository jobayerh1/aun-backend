<?php
/**
 * Plugin Name: Kohthai Chat & Order Notice
 * Description: Displays a dynamic Chat & Order block (WhatsApp & Messenger) via shortcode. Automatically pulls product names and hides on discontinued products.
 * Version: 1.0.0
 * Author: Smart Living Bangladesh
 */

if (!defined('ABSPATH')) exit;

class Kohthai_Chat_Order_Notice {

    const OPTION_NAME = 'kohthai_chat_order_settings';

    public static function init() {
        // Register Shortcode
        add_shortcode('kohthai_chat_order', [__CLASS__, 'render_shortcode']);

        // Admin Settings
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    // --- ADMIN SETTINGS ---
    public static function admin_menu() {
        add_options_page(
            'Chat & Order Settings',
            'Chat & Order',
            'manage_options',
            'kohthai-chat-order',
            [__CLASS__, 'settings_page']
        );
    }

    public static function register_settings() {
        register_setting('kohthai_chat_order_group', self::OPTION_NAME);
    }

    public static function get_settings() {
        $defaults = [
            'wa_number'    => '8801993333496',
            'messenger_id' => 'kohthaibd',
        ];
        $settings = get_option(self::OPTION_NAME, []);
        return wp_parse_args($settings, $defaults);
    }

    public static function settings_page() {
        $opts = self::get_settings();
        ?>
        <div class="wrap">
            <h1>Chat & Order Notice Settings</h1>
            <p>Configure the contact details for your <code>[kohthai_chat_order]</code> shortcode.</p>
            <form method="post" action="options.php">
                <?php settings_fields('kohthai_chat_order_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">WhatsApp Number</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_NAME; ?>[wa_number]" value="<?php echo esc_attr($opts['wa_number']); ?>" class="regular-text" />
                            <p class="description">Include the country code (e.g., 8801993333496). No '+', spaces, or dashes.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Facebook Messenger ID</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_NAME; ?>[messenger_id]" value="<?php echo esc_attr($opts['messenger_id']); ?>" class="regular-text" />
                            <p class="description">Your page username (e.g., kohthaibd).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // --- SHORTCODE RENDER ---
    public static function render_shortcode() {
        global $product;
        
        // Only run on single product pages
        if ( ! is_product() || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $product_id = $product->get_id();

        // Security check: Hide if the product is discontinued
        if ( taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $product_id ) ) {
            return ''; 
        }

        $opts = self::get_settings();
        $wa_number = $opts['wa_number'];
        $messenger_id = $opts['messenger_id'];

        ob_start();
        ?>
        <div class="kohthai-chat-order" style="margin-top:12px; margin-bottom: 0px; padding:12px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
            <strong style="font-size:16px;">Chat & Order</strong>
            
            <div style="margin-top:8px; display:flex; gap:12px; align-items:center;">
                <!-- WhatsApp -->
                <a id="kohthai-whatsapp-btn" 
                   href="#" 
                   target="_blank"
                   style="display:flex; align-items:center; gap:6px; color:#25D366; font-weight:600; font-size:15px; text-decoration:none;"
                   onmouseover="this.style.opacity='0.7'"
                   onmouseout="this.style.opacity='1'">
                    <i class="fa-brands fa-whatsapp" style="font-size:18px;"></i> WhatsApp
                </a>

                <span style="color:#555;">|</span>

                <!-- Messenger -->
                <a id="kohthai-messenger-btn"
                   href="#" 
                   target="_blank"
                   style="display:flex; align-items:center; gap:6px; color:#0084FF; font-weight:600; font-size:15px; text-decoration:none;"
                   onmouseover="this.style.opacity='0.7'"
                   onmouseout="this.style.opacity='1'">
                    <i class="fa-brands fa-facebook-messenger" style="font-size:18px;"></i> Messenger
                </a>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get product title securely from the DOM
            var productTitle = document.querySelector('.product-title')?.innerText.trim() || 'this product';

            // WordPress PHP variables passed to JS
            var phone = "<?php echo esc_js($wa_number); ?>";
            var pageId = "<?php echo esc_js($messenger_id); ?>";

            // Dynamic WhatsApp Message
            var message = encodeURIComponent("Hi, I want to order the " + productTitle + ". Is Cash on Delivery available?");
            var waBtn = document.getElementById('kohthai-whatsapp-btn');
            if(waBtn) {
                waBtn.href = "https://wa.me/" + phone + "?text=" + message;
            }

            // Dynamic Messenger Link
            var msngr = document.getElementById('kohthai-messenger-btn');
            if(msngr) {
                var baseURL = "https://m.me/" + pageId + "?ref=" + encodeURIComponent(productTitle);
                msngr.href = baseURL;
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

Kohthai_Chat_Order_Notice::init();