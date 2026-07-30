<?php
/**
 * Plugin Name: AUN Live Tracking (Pathao)
 * Description: Live order tracking via API. Features public tracking links, smart phone search, robust security, and a beautiful timeline. Fully responsive (stacked form + full-width button on mobile).
 * Version: 2.9.10
 * Author: Smart Living Bangladesh
 */

if (!defined('ABSPATH')) exit;

class AUN_Pathao_Live_Tracking {

    public static function init() {
        add_shortcode('aun_live_tracking', [__CLASS__, 'render_tracking_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        
        // AJAX Endpoints
        add_action('wp_ajax_aun_track_order', [__CLASS__, 'handle_ajax_tracking']);
        add_action('wp_ajax_nopriv_aun_track_order', [__CLASS__, 'handle_ajax_tracking']);
    }

    public static function enqueue_scripts() {
        global $post;
        // Only enqueue on pages that actually contain the shortcode — avoids
        // leaking the nonce and loading assets on unrelated pages.
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'aun_live_tracking' ) ) return;

        wp_register_script( 'aun-tracking-js', false, [ 'jquery' ] );
        wp_localize_script( 'aun-tracking-js', 'aun_tracking_obj', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'aun_tracking_nonce' ),
        ] );
        wp_enqueue_script( 'aun-tracking-js' );
    }

    public static function render_tracking_form() {
        ob_start();
        ?>
        <div class="aun-tracking-wrapper">
            <h3 style="margin-top:0; margin-bottom: 20px; font-size: 20px; color:#333; text-align:center;">Track Your Order</h3>
            <div class="aun-tracking-form">
                <input type="text" id="aun-tracking-search" placeholder="Enter Order ID or Phone Number" />
                <button id="aun-track-btn">Track Order</button>
            </div>
            <div id="aun-tracking-result"></div>
        </div>

        <style id="aun-tracking-css" data-no-optimize="1" data-no-minify="1">
            .aun-tracking-wrapper { max-width: 650px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e9eef5; font-family: inherit; }
            .aun-tracking-form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: stretch; }
            .aun-tracking-form input { flex: 1; min-width: 200px; padding: 0 16px; height: 48px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
            .aun-tracking-form input:focus { border-color: #0188fe; }
            .aun-tracking-form button { padding: 0 24px; height: 48px; background: #0188fe; color: white; border: 1px solid #0188fe; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; white-space: nowrap; box-sizing: border-box; display: flex; align-items: center; justify-content: center; }
            .aun-tracking-form button:hover { background: #0070d6; border-color: #0070d6; }
            .aun-tracking-form button:disabled { background: #99c9fb; border-color: #99c9fb; cursor: not-allowed; }

            .aun-order-meta-header { background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-bottom: 24px; text-align: center; font-size: 15px; color: #475569; }
            .aun-order-meta-header strong { color: #0f172a; }

            .aun-tracking-card { border-top: 1px solid #e9eef5; padding-top: 10px; margin-top: 10px; }
            .aun-tracking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 10px; }
            .aun-tracking-header h4 { margin: 0; font-size: 16px; color: #333; font-weight: 700; }
            .aun-badge { background: #e0f2fe; color: #0284c7; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; }
            
            .aun-timeline { padding: 0 0 0 20px; margin: 0; list-style: none; position: relative; }
            .aun-timeline-step { position: relative; padding-bottom: 35px; }
            .aun-timeline-step:last-child { padding-bottom: 0; }
            
            .aun-timeline-step::before { content: ''; position: absolute; left: 14px; top: 32px; bottom: 0; width: 3px; background: #e2e8f0; border-radius: 3px; }
            .aun-timeline-step:last-child::before { display: none; }
            .aun-timeline-step.completed::before { background: #0188fe; }

            .aun-timeline-icon { position: absolute; left: 0; top: 0; width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; z-index: 2; font-size: 14px; border: 3px solid #fff; box-shadow: 0 0 0 1px #e2e8f0;}
            .aun-timeline-step.completed .aun-timeline-icon { background: #0188fe; color: #fff; box-shadow: 0 0 0 1px #0188fe; border-color: #fff; }
            
            .aun-timeline-content { padding-left: 50px; padding-top: 0; font-size: 15px; color: #64748b; font-weight: 500; }
            .aun-timeline-step.completed .aun-timeline-content { color: #0f172a; font-weight: 700; }
            .aun-timeline-title { display: flex; align-items: center; min-height: 32px; font-size: 16px; font-weight: 700; margin-bottom: 2px; }
            .aun-timeline-desc { font-size: 13px; color: #64748b; font-weight: normal; margin-top: 0; }
            .aun-timeline-date { font-size: 12px; color: #94a3b8; margin-top: 5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; padding: 2px 8px; border-radius: 10px;}

            /* Professional Order Details Grid */
            .aun-tracking-details-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 30px; }
            @media(min-width: 500px) { .aun-tracking-details-grid { grid-template-columns: 1fr 1fr; } }
            .aun-detail-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; text-align: left; }
            .aun-detail-box h5 { margin: 0 0 12px 0; font-size: 15px; color: #334155; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; font-weight: 700; }
            .aun-detail-box p { margin: 6px 0; color: #475569; line-height: 1.4; }
            .aun-detail-box p strong { color: #1e293b; font-weight: 600; }
            .aun-product-list { list-style: none; padding: 0; margin: 10px 0 0 0; }
            .aun-product-list li { padding: 6px 0; border-top: 1px dashed #cbd5e1; color: #475569; display: flex; align-items: flex-start; gap: 6px; }
            .aun-product-list li:first-child { border-top: none; padding-top: 0; }
            .aun-product-qty { font-weight: 700; color: #0188fe; min-width: 20px; }
            .aun-product-link { color: #475569; text-decoration: none; border-bottom: 1px dotted #cbd5e1; transition: color 0.2s, border-color 0.2s; }
            .aun-product-link:hover { color: #0188fe; border-bottom-color: #0188fe; }

            .aun-tracking-footer { margin-top: 30px; text-align: center; }
            .aun-external-btn { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: #0188fe; text-decoration: none; font-weight: 700; background: #f0f7ff; padding: 10px 20px; border-radius: 8px; transition: background 0.2s; border: 1px solid #dbebff; }
            .aun-external-btn:hover { background: #e0f0ff; }

            .aun-notice { padding: 14px; border-radius: 8px; margin-top: 20px; font-size: 14px; line-height: 1.5; }
            .aun-info { background: #f6f8fb; color: #555; border-left: 4px solid #0188fe; }
            .aun-error { background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; }

            /* ── RESPONSIVE (mobile) ── */
            @media(max-width:480px){
                .aun-tracking-wrapper { padding: 16px; }
                /* Stack the search field + button so the button is full-width, not stranded on the left */
                .aun-tracking-form { flex-direction: column; gap: 10px; }
                /* flex:none resets the input's "flex:1 1 0%" — in a column its 0% basis was collapsing
                   the HEIGHT (main axis is now vertical); height:48px is re-asserted so it stays full-size */
                .aun-tracking-form input,
                .aun-tracking-form button { width: 100%; min-width: 0; flex: none; height: 48px; }
                .aun-tracking-header h4 { font-size: 15px; word-break: break-word; }
                .aun-external-btn { width: 100%; justify-content: center; box-sizing: border-box; }
            }
        </style>

        <script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
            jQuery(document).ready(function($) {

                // Allow pressing Enter in the search field to trigger tracking
                $('#aun-tracking-search').on('keydown', function(e) {
                    if (e.key === 'Enter') $('#aun-track-btn').trigger('click');
                });

                $('#aun-track-btn').click(function(e) {
                    e.preventDefault();
                    var search = $('#aun-tracking-search').val().trim();
                    if (!search) {
                        // Inline error — never use alert() on a public-facing page
                        $('#aun-tracking-result').html('<div class="aun-notice aun-error"><i class="fa-solid fa-circle-exclamation"></i> Please enter your Order ID or Phone Number.</div>');
                        return;
                    }

                    var btn = $(this);
                    btn.text('Tracking...').prop('disabled', true);
                    $('#aun-tracking-result').html('<div class="aun-notice aun-info"><i class="fa-solid fa-spinner fa-spin"></i> Fetching your delivery updates...</div>');
                    
                    $.ajax({
                        url: aun_tracking_obj.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'aun_track_order',
                            search: search,
                            nonce: aun_tracking_obj.nonce
                        },
                        success: function(res) {
                            btn.text('Track Order').prop('disabled', false);
                            if (res.success) {
                                $('#aun-tracking-result').html(res.data.html);
                            } else {
                                $('#aun-tracking-result').html('<div class="aun-notice aun-error"><i class="fa-solid fa-circle-exclamation"></i> ' + res.data + '</div>');
                            }
                        },
                        error: function() {
                            btn.text('Track Order').prop('disabled', false);
                            $('#aun-tracking-result').html('<div class="aun-notice aun-error">Connection error. Please try again.</div>');
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Smartly gets the Pathao token strictly from the official Pathao plugin.
     */
    private static function get_pathao_token() {
        if (function_exists('pt_hms_get_token')) {
            $official_token = pt_hms_get_token(true);
            if (!empty($official_token)) {
                return $official_token;
            }
        }
        return new WP_Error('plugin_missing', 'The official Pathao Courier plugin is either inactive or not configured properly.');
    }

    /**
     * Extracts a Pathao consignment / tracking ID from an order, trying
     * multiple known meta-key locations for compatibility with different
     * Pathao plugin versions and shipment-tracking plugins.
     */
    private static function get_tracking_id( WC_Order $order ): string {
        $keys = [
            'pathao_consignment_id',
            '_pathao_consignment_id',
            '_tracking_number',
            'tracking_number',
        ];
        foreach ( $keys as $k ) {
            $v = $order->get_meta( $k );
            if ( ! empty( $v ) ) return (string) $v;
        }
        $ast = $order->get_meta( '_wc_shipment_tracking_items' );
        if ( ! empty( $ast ) && is_array( $ast ) && ! empty( $ast[0]['tracking_number'] ) ) {
            return (string) $ast[0]['tracking_number'];
        }
        return '';
    }

    /**
     * Forces WordPress HTTP API to use IPv4 to bypass strict server firewalls.
     */
    public static function force_ipv4_curl($handle) {
        curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    /**
     * Handle the AJAX Request
     */
    public static function handle_ajax_tracking() {
        check_ajax_referer('aun_tracking_nonce', 'nonce');
        
        // --- SECURITY: IP Rate Limiting (fixed 5-minute window) ---
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $bucket   = floor( time() / ( MINUTE_IN_SECONDS * 5 ) ); // changes every 5 min
        $rate_key = 'aun_trk_limit_' . md5( $ip . '_' . $bucket );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 20 ) {
            wp_send_json_error( 'Security limit reached. Please wait a few minutes before trying again.' );
        }
        // Increment counter; set TTL slightly longer than window so it doesn't
        // expire mid-window and reset the count prematurely.
        set_transient( $rate_key, $attempts + 1, MINUTE_IN_SECONDS * 6 );
        
        $search = sanitize_text_field($_POST['search']);
        if (empty($search)) {
            wp_send_json_error('Please enter an Order ID or Phone Number.');
        }

        $order = null;

        // Try searching strictly as an Order ID first (Allows longer IDs now)
        if (is_numeric($search) && strlen($search) < 15) {
            $order = wc_get_order($search);
            // Verify it's actually an order and not just a random post
            if ($order && !is_a($order, 'WC_Order')) {
                $order = null;
            }
        }

        // --- SMART PHONE NUMBER SEARCH (HPOS & Legacy Compatible) ---
        if (!$order) {
            $clean_phone = preg_replace('/[^0-9]/', '', $search);
            
            if (strlen($clean_phone) >= 10) {
                // Grab strictly the last 10 digits (e.g. 1712345678)
                $last_10 = substr($clean_phone, -10);

                global $wpdb;
                $found_order_ids = [];
                
                // Check if HPOS is active
                $hpos_enabled = false;
                if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $hpos_enabled = true;
                }

                // ADVANCED DIRECT DATABASE QUERY:
                // This strips spaces, dashes, plus signs, and parentheses directly from the database row so any format perfectly matches.
                if ($hpos_enabled) {
                    $table = $wpdb->prefix . 'wc_order_addresses';
                    $sql = $wpdb->prepare("SELECT order_id FROM {$table} WHERE address_type = 'billing' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE %s ORDER BY order_id DESC LIMIT 10", '%' . $wpdb->esc_like($last_10) . '%');
                    $found_order_ids = $wpdb->get_col($sql);
                } else {
                    $sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_billing_phone' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(meta_value, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE %s ORDER BY post_id DESC LIMIT 10", '%' . $wpdb->esc_like($last_10) . '%');
                    $found_order_ids = $wpdb->get_col($sql);
                }

                if (!empty($found_order_ids)) {
                    $orders = [];
                    
                    // Directly fetch exactly these specific orders found in the database
                    foreach ($found_order_ids as $fid) {
                        $fetched_order = wc_get_order($fid);
                        if ($fetched_order && is_a($fetched_order, 'WC_Order')) {
                            $orders[] = $fetched_order;
                        }
                    }

                    if (!empty($orders)) {
                        // Smart Logic: Iterate through their orders and grab the MOST RECENT one that ACTUALLY has a tracking number
                        $found_tracked_order = null;
                        foreach ($orders as $potential_order) {
                            if (!empty(self::get_tracking_id($potential_order))) {
                                $found_tracked_order = $potential_order;
                                break;
                            }
                        }
                        // Fallback to the newest order if no tracking numbers exist yet
                        $order = $found_tracked_order ? $found_tracked_order : reset($orders);
                    }
                }
            }
        }

        if (!$order) {
            wp_send_json_error('We could not find any order with that ID or Phone Number. Please double-check and try again.');
        }

        // --- GET CUSTOMER & ORDER DETAILS FROM WOOCOMMERCE ---
        $customer_name = $order->get_formatted_billing_full_name();
        $raw_billing_phone = $order->get_billing_phone();
        $customer_city = $order->get_shipping_city() ?: $order->get_billing_city();
        
        $order_total_html = $order->get_formatted_order_total();
        $payment_method = $order->get_payment_method_title();
        
        // Smart Payment Status handling for Failed / Cancelled orders
        $order_status_raw = $order->get_status();
        $payment_method_id = $order->get_payment_method(); // e.g., 'cod', 'bacs', 'sslcommerz'
        
        if (in_array($order_status_raw, ['cancelled', 'failed'])) {
            if ($payment_method_id === 'cod') {
                $payment_method = ($payment_method ?: 'Cash on Delivery') . ' <strong style="color:#ef4444;">(Cancelled)</strong>';
            } else {
                $payment_method = ($payment_method ?: 'Online Payment') . ' <strong style="color:#ef4444;">(Unpaid / Cancelled)</strong>';
            }
        } elseif ($order_status_raw === 'refunded') {
            $payment_method = ($payment_method ?: 'Online Payment') . ' <strong style="color:#f59e0b;">(Refunded)</strong>';
        }

        $order_items = $order->get_items();
        
        $created_date_obj = $order->get_date_created();
        $created_ts       = $created_date_obj ? $created_date_obj->getOffsetTimestamp() : time();

        $order_num  = $order->get_order_number();
        $order_date = wp_date( 'F j, Y', $created_ts );

        // Privacy: Safely mask the phone number and address for the frontend display
        $masked_phone = 'N/A';
        if (!empty($raw_billing_phone)) {
            $cl_phone = preg_replace('/[^0-9]/', '', $raw_billing_phone);
            if (strlen($cl_phone) >= 11) {
                $masked_phone = substr($cl_phone, 0, 3) . str_repeat('*', strlen($cl_phone) - 6) . substr($cl_phone, -3);
            } else {
                $masked_phone = '***';
            }
        }
        
        // Ensure Address is professionally hidden
        $masked_address = '*** (Hidden for privacy), ' . ($customer_city ?: 'City not specified');

        // Extract tracking ID
        $tracking_id = self::get_tracking_id( $order );

        // --- CLEANUP TRACKING ID ---
        if (!empty($tracking_id) && is_string($tracking_id)) {
            if (strpos($tracking_id, '&') !== false) {
                $tracking_id = explode('&', $tracking_id)[0];
            }
            if (strpos($tracking_id, '?') !== false) {
                $tracking_id = explode('?', $tracking_id)[0];
            }
            $tracking_id = trim($tracking_id);
        }

        // --- SMART IN-HOUSE / ALTERNATIVE COURIER FALLBACK ---
        if (empty($tracking_id)) {
            $status = $order->get_status();
            $status_name = wc_get_order_status_name($status);
            $is_completed = in_array($status, ['completed', 'shipped', 'delivered']);
            $is_cancelled = in_array($status, ['cancelled', 'failed', 'refunded']);
            
            if ($is_completed) {
                $msg_title = 'Delivered Successfully';
                $msg_desc = 'This order was delivered securely by our in-house team or a partner courier.';
                $icon = 'fa-circle-check';
                $color = '#10b981';
            } elseif ($is_cancelled) {
                $msg_title = 'Order ' . esc_html($status_name);
                $msg_desc = 'This order has been ' . strtolower($status_name) . '. If you believe this is a mistake, please contact our support team.';
                $icon = 'fa-circle-xmark';
                $color = '#ef4444';
            } else {
                $msg_title = 'Processing Order';
                $msg_desc = 'Your order is currently being prepared. A tracking link will be available once it ships.';
                $icon = 'fa-box-open';
                $color = '#0188fe';
            }

            ob_start();
            ?>
            <div class="aun-order-meta-header">
                Order <strong>#<?php echo esc_html($order_num); ?></strong> placed on <strong><?php echo esc_html($order_date); ?></strong>
            </div>
            <div class="aun-tracking-card">
                <div style="text-align:center; padding: 20px 10px 40px;">
                    <i class="fa-solid <?php echo esc_attr($icon); ?>" style="font-size: 40px; color: <?php echo esc_attr($color); ?>; margin-bottom: 15px;"></i>
                    <h4 style="margin:0 0 10px 0; font-size: 18px; color:#334155;"><?php echo esc_html($msg_title); ?></h4>
                    <p style="margin:0; color:#64748b; font-size:14px;"><?php echo esc_html($msg_desc); ?></p>
                </div>
                <?php echo self::generate_details_grid_html($order_num, $order_date, $customer_name, $masked_phone, $masked_address, $order_total_html, $payment_method, $order_items, ''); ?>
            </div>
            <?php
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        }

        $token = self::get_pathao_token();
        if (is_wp_error($token)) {
            wp_send_json_error('Auth Error: ' . $token->get_error_message());
        }

        // Dynamically grab the API base URL
        $pathao_options = get_option('pt_hms_settings');
        $is_staging = (is_array($pathao_options) && isset($pathao_options['environment']) && $pathao_options['environment'] === 'staging');
        $base_url = $is_staging ? 'https://api-hermes-staging.pathao.com' : 'https://api-hermes.pathao.com';

        $url = $base_url . '/aladdin/api/v1/orders/' . rawurlencode( $tracking_id );

        // Check transient cache first — Pathao status updates at most hourly,
        // so a 20-minute cache eliminates redundant API calls and protects against
        // Pathao's rate limits.
        $cache_key    = 'aun_pathao_' . md5( $tracking_id );
        $cached_body  = get_transient( $cache_key );

        if ( $cached_body !== false ) {
            $body = $cached_body;
        } else {
            // Hook in to force IPv4 connection securely
            add_action( 'http_api_curl', [ __CLASS__, 'force_ipv4_curl' ] );

            $response = wp_remote_get( $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 30,
            ] );

            remove_action( 'http_api_curl', [ __CLASS__, 'force_ipv4_curl' ] );

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( '<div style="text-align:left;"><strong>API Connection Failed:</strong> ' . esc_html( $response->get_error_message() ) . '</div>' );
            }

            if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
                wp_send_json_error( 'Pathao returned an unexpected response. Please try again in a few moments.' );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // Cache successful (non-error) responses only
            if ( $body && ( empty( $body['type'] ) || $body['type'] !== 'error' ) && ! empty( $body['data'] ) ) {
                set_transient( $cache_key, $body, 20 * MINUTE_IN_SECONDS );
            }
        }

        // --- GRACEFUL ERROR HANDLING (Orders with empty data or archived by Pathao) ---
        if (!$body || (isset($body['type']) && $body['type'] === 'error') || empty($body['data'])) {
            $html = '<div class="aun-notice aun-info" style="text-align:center;">';
            $html .= '<p><i class="fa-solid fa-clock" style="font-size:24px; color:#0188fe; margin-bottom:10px;"></i></p>';
            $html .= '<strong>Tracking ID Assigned: ' . esc_html($tracking_id) . '</strong><br><br>';
            $html .= 'Your tracking number has been generated, but live tracking data is not currently available from the Pathao hub.<br><small>(Note: Pathao automatically archives tracking data for old/completed orders from this live system.)</small>';
            $html .= '</div>';
            
            // Still show the order details grid beneath the notice for a great user experience
            $html .= self::generate_details_grid_html($order_num, $order_date, $customer_name, $masked_phone, $masked_address, $order_total_html, $payment_method, $order_items, $tracking_id);
            
            wp_send_json_success(['html' => $html]);
        }

        $pathao_data = $body['data'];

                // --- SMART REGEX CASCADE ENGINE ---
        $order_status = trim($pathao_data['order_status'] ?? 'Pending');
        $p_status = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($order_status)), '_');

        $placed_date = wp_date( 'M d, Y h:i A', $created_ts );

        // Grabbing EXACT timestamps provided natively by Pathao
        $api_created_at = !empty($pathao_data['order_created_at']) ? wp_date('M d, Y h:i A', strtotime($pathao_data['order_created_at'])) : $placed_date;
        $api_updated_at = !empty($pathao_data['order_status_updated_at']) ? wp_date('M d, Y h:i A', strtotime($pathao_data['order_status_updated_at'])) : '';

        // Determine Level via Smart Keywords
        $status_level = 0;
        if (preg_match('/pending|accept|assign/i', $p_status)) $status_level = 1;
        if (preg_match('/pick/i', $p_status)) $status_level = 2;
        if (preg_match('/transit/i', $p_status)) $status_level = 3;
        if (preg_match('/hub|ready|out_for|delivery/i', $p_status)) $status_level = 4;
        if (preg_match('/delivered|success|payment/i', $p_status)) $status_level = 5;

        // Base Timeline Definition
        $timeline = [
            ['label' => 'Accepted', 'desc' => 'Order confirmed and pickup requested', 'active' => false, 'icon' => 'fa-clipboard-check', 'date' => $api_created_at],
            ['label' => 'Picked Up', 'desc' => 'Courier has collected the package', 'active' => false, 'icon' => 'fa-truck-ramp-box', 'date' => ($status_level === 2) ? $api_updated_at : ''],
            ['label' => 'In Transit', 'desc' => 'Package is on the way to the destination', 'active' => false, 'icon' => 'fa-truck-fast', 'date' => ($status_level === 3) ? $api_updated_at : ''],
            ['label' => 'Ready For Delivery', 'desc' => 'Package is preparing for final delivery', 'active' => false, 'icon' => 'fa-building', 'date' => ($status_level === 4) ? $api_updated_at : ''],
            ['label' => 'Delivered', 'desc' => 'Package handed over to the customer', 'active' => false, 'icon' => 'fa-circle-check', 'date' => ($status_level === 5) ? $api_updated_at : '']
        ];

        // Apply Cascade Activation mathematically
        for ($i = 0; $i < $status_level; $i++) {
            $timeline[$i]['active'] = true;
        }

        // Overrides for Cancelled / Returned using Regex
        if (preg_match('/cancel|fail/i', $p_status)) {
            $timeline = [
                ['label' => 'Accepted', 'desc' => 'Order was placed', 'active' => true, 'icon' => 'fa-clipboard-check', 'date' => $api_created_at],
                ['label' => 'Cancelled', 'desc' => 'This shipment has been cancelled', 'active' => true, 'icon' => 'fa-circle-xmark', 'color' => '#ef4444', 'date' => $api_updated_at]
            ];
        } elseif (preg_match('/return/i', $p_status)) {
            $timeline[4] = ['label' => 'Returned', 'desc' => 'Package has been returned to sender', 'active' => true, 'icon' => 'fa-rotate-left', 'color' => '#ef4444', 'date' => $api_updated_at];
        }

        $badge_text = ucwords(str_replace('_', ' ', preg_replace('/[^a-zA-Z0-9]+/', '_', trim($order_status))));
		
        ob_start();
        ?>
        
        <!-- Beautiful Informative Header -->
        <div class="aun-order-meta-header">
            Order <strong>#<?php echo esc_html($order_num); ?></strong> placed on <strong><?php echo esc_html($order_date); ?></strong>
        </div>

        <div class="aun-tracking-card">
            
            <div class="aun-tracking-header">
                <h4>Tracking ID: <?php echo esc_html($tracking_id); ?></h4>
                <span class="aun-badge" <?php echo (preg_match('/cancel|fail|return/i', $p_status)) ? 'style="background:#fef2f2; color:#ef4444;"' : ''; ?>><?php echo esc_html($badge_text); ?></span>
            </div>
            
            <div class="aun-tracking-body">
                <ul class="aun-timeline">
                    <?php foreach($timeline as $index => $step): 
                        $color_style = !empty($step['color']) && $step['active'] ? 'background-color:'.$step['color'].'; border-color:'.$step['color'].'; box-shadow: 0 0 0 1px '.$step['color'].';' : '';
                        $text_color = !empty($step['color']) && $step['active'] ? 'color:'.$step['color'].';' : '';
                    ?>
                    <li class="aun-timeline-step <?php echo $step['active'] ? 'completed' : ''; ?>">
                        <div class="aun-timeline-icon" style="<?php echo $color_style; ?>">
                            <i class="fa-solid <?php echo esc_attr($step['icon']); ?>"></i>
                        </div>
                        <div class="aun-timeline-content" style="<?php echo $text_color; ?>">
                            <div class="aun-timeline-title"><?php echo esc_html($step['label']); ?></div>
                            
                            <?php if ($step['active']): ?>
                                <div class="aun-timeline-desc"><?php echo esc_html($step['desc']); ?></div>
                                <?php if (!empty($step['date'])): ?>
                                    <div class="aun-timeline-date"><i class="fa-regular fa-clock"></i> <?php echo esc_html($step['date']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php echo self::generate_details_grid_html($order_num, $order_date, $customer_name, $masked_phone, $masked_address, $order_total_html, $payment_method, $order_items, $tracking_id); ?>

        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Helper Function to keep HTML clean and reusable
     */
    private static function generate_details_grid_html($order_num, $order_date, $customer_name, $masked_phone, $masked_address, $order_total_html, $payment_method, $order_items, $tracking_id) {
        ob_start();
        ?>
        <!-- Professional Order Details Grid -->
        <div class="aun-tracking-details-grid">
            
            <div class="aun-detail-box">
                <h5><i class="fa-regular fa-address-card"></i> Customer Info</h5>
                <p><strong>Name:</strong> <?php echo esc_html($customer_name ?: 'N/A'); ?></p>
                <p><strong>Phone:</strong> <span title="Hidden for privacy"><?php echo esc_html($masked_phone); ?></span></p>
                <p><strong>Address:</strong> <span title="Hidden for privacy"><?php echo esc_html($masked_address); ?></span></p>
            </div>

            <div class="aun-detail-box">
                <h5><i class="fa-solid fa-box-open"></i> Order Summary</h5>
                <p><strong>Order ID:</strong> #<?php echo esc_html($order_num); ?></p>
                <p><strong>Total:</strong> <?php echo wp_kses_post($order_total_html); ?></p>
                <p><strong>Payment:</strong> <?php echo wp_kses_post($payment_method ?: 'N/A'); ?></p>
                
                <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid #e2e8f0;">
                    <ul class="aun-product-list">
                        <?php foreach ($order_items as $item): 
                            $product = $item->get_product();
                            
                            // Clean the variant text by removing ONLY HTML tags (like <span>), keeping the variant string (- White)
                            $product_name = $item->get_name();
                            $product_name = wp_strip_all_tags(str_replace(['<span>', '</span>'], '', html_entity_decode($product_name)));
                            
                            $product_url = $product ? $product->get_permalink() : '';
                        ?>
                            <li>
                                <span class="aun-product-qty"><?php echo esc_html($item->get_quantity()); ?>x</span>
                                <span style="flex:1;">
                                    <?php if ($product_url): ?>
                                        <a href="<?php echo esc_url($product_url); ?>" target="_blank" class="aun-product-link"><?php echo esc_html($product_name); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($product_name); ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>
        
        <?php if (!empty($tracking_id)): ?>
        <div class="aun-tracking-footer">
            <!-- Switched to Public Tracking Link for Privacy -->
            <a href="https://merchant.pathao.com/public-tracking?consignment_id=<?php echo esc_attr($tracking_id); ?>" target="_blank" class="aun-external-btn">
                View Detailed History <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php
        return ob_get_clean();
    }
}

AUN_Pathao_Live_Tracking::init();