<?php
/**
 * Plugin Name:       Kohthai Smart Delivery Plugin
 * Description:       Handles intelligent, context-aware delivery estimates and backorder notices with cart splitting. Now includes holiday date skipping.
 * Version:           14.0 - Smart Holiday Pickers
 * Author:            Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Kohthai_Smart_Delivery_Plugin_V14_0 {

    public function __construct() {
        // --- CORE LOGIC ---
        add_filter('woocommerce_cart_shipping_packages', array($this, 'split_cart_by_stock_status'));
        add_filter('woocommerce_shipping_package_name', array($this, 'rename_shipping_packages'), 10, 3);

        // --- ADMIN SETTINGS ---
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // --- DISPLAY LOGIC ---
        add_action('woocommerce_single_product_summary', array($this, 'unified_product_page_notice_display'), 35);
        add_filter('woocommerce_get_item_data', array($this, 'backorder_notice_cart_checkout'), 10, 2);
        add_action('woocommerce_order_item_meta_end', array($this, 'backorder_notice_orders_emails'), 10, 4);
        add_action('woocommerce_after_shipping_rate', array($this, 'display_estimate_after_shipping_rate'), 20, 2);
        
        // --- Using the final, stable functions for saving and displaying order/email data ---
        add_action('woocommerce_checkout_create_order_shipping_item', array($this, 'save_detailed_estimate_to_shipping_item'), 10, 4);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_detailed_estimate_on_order_pages'), 20, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_detailed_estimate_in_emails'), 20, 4);
        
        // AJAX & ASSETS
        add_action('wp_ajax_get_product_page_delivery_estimate', array($this, 'ajax_get_product_page_estimate'));
        add_action('wp_ajax_nopriv_get_product_page_delivery_estimate', array($this, 'ajax_get_product_page_estimate'));
        add_action('wp_footer', array($this, 'add_frontend_assets'));
    }

    // === Locale & date helpers (native WP) ===
    private function current_request_locale() {
        $locale = get_locale();

        // TranslatePress
        if ( function_exists('trp_get_current_language') ) {
            $slug = trp_get_current_language(); // e.g. 'en' or 'en_US'
            if ( $slug && strpos($slug, '_') !== false ) {
                return $slug;
            }
            $settings = get_option('trp_settings', array());
            $map = array();
            $entries = array();
            if ( !empty($settings['translation-languages']) && is_array($settings['translation-languages']) ) {
                $entries = $settings['translation-languages'];
            } elseif ( !empty($settings['publish-languages']) && is_array($settings['publish-languages']) ) {
                $entries = $settings['publish-languages'];
            }
            foreach ( $entries as $row ) {
                $row_slug   = isset($row['slug']) ? $row['slug'] : ( isset($row['url-slug']) ? $row['url-slug'] : ( isset($row['code']) ? $row['code'] : null ) );
                $row_locale = isset($row['language']) ? $row['language'] : ( isset($row['locale']) ? $row['locale'] : ( isset($row['wp-locale']) ? $row['wp-locale'] : null ) );
                if ( $row_slug && $row_locale ) {
                    $map[ $row_slug ] = $row_locale;
                }
            }
            if ( isset($map[$slug]) ) {
                return $map[$slug];
            }
            $fallback = array(
                'en'=>'en_US','bn'=>'bn_BD','ru'=>'ru_RU','hi'=>'hi_IN','ar'=>'ar','tr'=>'tr_TR','fr'=>'fr_FR','de'=>'de_DE',
                'es'=>'es_ES','it'=>'it_IT','pt'=>'pt_PT','pt-br'=>'pt_BR','zh'=>'zh_CN','ja'=>'ja','ko'=>'ko_KR'
            );
            if ( isset($fallback[$slug]) ) {
                return $fallback[$slug];
            }
        }

        // Polylang
        if ( function_exists('pll_current_language') ) {
            $pll_locale = pll_current_language('locale');
            if ( $pll_locale ) return $pll_locale;
        }

        // WPML
        if ( defined('ICL_LANGUAGE_CODE') && function_exists('apply_filters') ) {
            $code = ICL_LANGUAGE_CODE;
            $langs = apply_filters('wpml_active_languages', null, array('skip_missing'=>0));
            if ( is_array($langs) && isset($langs[$code]['default_locale']) ) {
                return $langs[$code]['default_locale'];
            }
        }

        return $locale;
    }

    private function with_locale($locale, $callback) {
        if ( function_exists('switch_to_locale') && function_exists('restore_previous_locale') ) {
            switch_to_locale($locale);
            try { return $callback(); }
            finally { restore_previous_locale(); }
        }
        return $callback();
    }

    private function wp_format_dt($format, \DateTime $dt) {
        return wp_date($format, $dt->getTimestamp(), wp_timezone());
    }

    // --- STABLE CHECKOUT AND PROFESSIONAL EMAIL FUNCTIONS ---
    public function save_detailed_estimate_to_shipping_item($item, $package_key, $package, $order) {
        if (!is_a($item, 'WC_Order_Item_Shipping')) return;

        $rate_id = $item->get_method_id() . ':' . $item->get_instance_id();
        if (empty($package['rates'][$rate_id])) return;
        $rate = $package['rates'][$rate_id];
        
        $product_names = [];
		foreach ($package['contents'] as $cart_item) {
		    $raw_name   = $cart_item['data']->get_name();
		    $clean_name = trim( wp_strip_all_tags( html_entity_decode( $raw_name ) ) );
		    $product_names[] = $clean_name . ' (x' . $cart_item['quantity'] . ')';
		}
        
        $package_type = $package['package_type'] ?? 'in_stock';
        $estimate_text = '';
        $zone = WC_Shipping_Zones::get_zone_matching_package($package);
        $zone_id = is_object($zone) ? $zone->get_id() : 0;
        $rules = get_option('kohthai_delivery_rules', []);
        $method_key = $zone_id . ':' . $rate->get_instance_id();
        $rule = $rules[$method_key] ?? null;

        if (empty($rule)) return;
        $is_pickup = isset($rule['pickup']) && $rule['pickup'] === '1';

        if ($package_type === 'backorder') {
            $estimate_text = $is_pickup ? 'Ready for Pickup in 1-2 weeks' : 'Ships in 1-2 weeks';
        } else {
            $pickup_message = !empty($rule['pickup_message']) ? esc_html($rule['pickup_message']) : 'Ready for pickup';
            $estimate_date = $this->calculate_estimate_text($rule);
            $estimate_text = $is_pickup ? $pickup_message . ' ' . $estimate_date : 'Get it ' . $estimate_date;
        }

        if ($estimate_text && !empty($product_names)) {
            $item->add_meta_data('_kohthai_delivery_estimate', $estimate_text, true);
            $item->add_meta_data('_kohthai_package_contents', $product_names, true);
            $item->add_meta_data('_kohthai_package_type', $package_type, true);
            $item->save();
        }
    }

    private function display_detailed_estimate_html($order) {
        $shipping_items = $order->get_items('shipping');
        if (empty($shipping_items) || count($shipping_items) < 1) return;

        $output = '';
        foreach ($shipping_items as $item_id => $item) {
            $estimate = $item->get_meta('_kohthai_delivery_estimate');
            $contents = $item->get_meta('_kohthai_package_contents');
            $package_type = $item->get_meta('_kohthai_package_type');

            if ($estimate && is_array($contents)) {
                $package_name = ($package_type === 'backorder') ? __('Items on Backorder', 'woocommerce') : __('Items Available Now', 'woocommerce');
                
                $product_list = '<small style="display: block; margin-top: 5px; color: #777;">&bull; ' . implode('<br>&bull; ', array_map('esc_html', $contents)) . '</small>';
                
                $output .= '<tr>
                                <td style="text-align:left; border: 1px solid #eee; padding: 12px; vertical-align: top;"><strong>' . esc_html($package_name) . '</strong>' . $product_list . '</td>
                                <td style="text-align:left; border: 1px solid #eee; padding: 12px; vertical-align: top;">' . esc_html($item->get_name()) . '</td>
                                <td style="text-align:left; border: 1px solid #eee; padding: 12px; vertical-align: top;">' . wp_kses_post($estimate) . '</td>
                            </tr>';
            }
        }

        if ($output) {
            echo '<div class="kohthai-order-delivery-estimate" style="margin-top:20px; margin-bottom: 40px;">
                    <h2>Shipping Details</h2>
                    <table cellspacing="0" cellpadding="6" style="width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th scope="col" style="text-align:left; border: 1px solid #eee; padding: 12px;">Shipment / Products</th>
                                <th scope="col" style="text-align:left; border: 1px solid #eee; padding: 12px;">Shipping Method</th>
                                <th scope="col" style="text-align:left; border: 1px solid #eee; padding: 12px;">Estimated Delivery</th>
                            </tr>
                        </thead>
                        <tbody>' . $output . '</tbody>
                    </table>
                  </div>';
        }
    }
    
    private function display_detailed_estimate_plain_text($order) {
        $shipping_items = $order->get_items('shipping');
        if (empty($shipping_items)) return;

        $output = '';
        foreach ($shipping_items as $item_id => $item) {
            $estimate = $item->get_meta('_kohthai_delivery_estimate');
            $contents = $item->get_meta('_kohthai_package_contents');
            $package_type = $item->get_meta('_kohthai_package_type');
            if ($estimate && is_array($contents)) {
                 $package_name = ($package_type === 'backorder') ? __('Items on Backorder', 'woocommerce') : __('Items Available Now', 'woocommerce');
                 $output .= "\n--- " . esc_html($package_name) . " ---\n";
                 $output .= "Products: " . implode(', ', array_map('esc_html', $contents)) . "\n";
                 $output .= "Shipping Method: " . esc_html($item->get_name()) . "\n";
                 $output .= "Estimated Delivery: " . strip_tags($estimate) . "\n";
            }
        }
        if ($output) {
             echo "\n\nSHIPPING DETAILS\n" . "----------------------------------------" . $output . "----------------------------------------\n";
        }
    }

    public function display_detailed_estimate_on_order_pages($order) { $this->display_detailed_estimate_html($order); }
    public function display_detailed_estimate_in_emails($order, $sent_to_admin, $plain_text, $email) { if ($plain_text) { $this->display_detailed_estimate_plain_text($order); } else { $this->display_detailed_estimate_html($order); } }
    
    // --- UNIFIED PRODUCT PAGE DISPLAY ---
    public function unified_product_page_notice_display() {
        global $product;
        if (!is_a($product, 'WC_Product')) return;
        
        $is_discontinued = has_term( 'dp-discontinued', 'product_discontinued', $product->get_id() );

        if ( $is_discontinued || ! $product->is_in_stock() ) {
            return;
        }
        
        if ($product->is_on_backorder()) {
            $backorder_message = 'This is a backorder item. Estimated delivery time is 1-2 weeks.';
            ?>
            <div class="kohthai-delivery-estimate-container kohthai-backorder-notice">
                <div class="kohthai-delivery-estimate">
                    <i class="fa-solid fa-truck-ramp-box" style="margin-right:.4em"></i>
                    <div class="kohthai-delivery-text"><?php echo $backorder_message; ?></div>
                </div>
            </div>
            <?php
        } else {
            $rules = get_option('kohthai_delivery_rules', []);
            $shipping_zones = WC_Shipping_Zones::get_zones();
            $default_zone = new WC_Shipping_Zone(0);
            ?>
            <div id="kohthai-delivery-container-product" class="kohthai-delivery-estimate-container">
                <div id="kohthai-delivery-estimate-text-product">
                    <div class="kohthai-delivery-estimate in-stock">
                        <i class="fa-solid fa-truck-fast" style="margin-right:.4em"></i>
                        <div class="kohthai-delivery-text">
                            <strong>Estimated Delivery:</strong> 
                            <?php echo esc_html($rules['default_message'] ?? '2-4 business days.'); ?>
                        </div>
                    </div>
                </div>
                <div class="kohthai-location-selector">
                    <small>Not your location? <a href="#" id="kohthai-change-location-link-product">Select your area for a more accurate estimate.</a></small>
                    <div id="kohthai-location-options-product" style="display:none; margin-top:10px;">
                        <select id="kohthai-location-select-product">
                            <option value="">Select Area...</option>
                            <?php
                            foreach ($shipping_zones as $zone_id => $zone_data) {
                                echo '<option value="' . esc_attr($zone_id) . '">' . esc_html($zone_data['zone_name']) . '</option>';
                            }
                            echo '<option value="0">' . esc_html($default_zone->get_zone_name()) . '</option>';
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public function add_frontend_assets() { ?> <style> .kohthai-delivery-estimate-container { margin-top: 1.5em; margin-bottom: 1em; padding: 12px; background-color: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 5px; } .kohthai-delivery-estimate { margin: 0; display: flex; align-items: center; } .kohthai-delivery-estimate i { font-size: 1.8em; margin-right: 12px; } .kohthai-delivery-estimate-container.kohthai-backorder-notice { color: #b30000; background-color: #fff0f0; border-color: #ffdddd; } .kohthai-backorder-notice .kohthai-delivery-estimate i { color: #b30000; } .woocommerce-cart .cart_item .variation-DeliveryNote p, .woocommerce-cart .cart_item .variation-Delivery-Note .key { color: #b30000; font-weight: 600; } .woocommerce-cart .cart_item .variation-Delivery-Note i { margin-right: 5px; } .kohthai-delivery-estimate.in-stock i { color: #1e85be; } .kohthai-location-selector a { cursor: pointer; text-decoration: underline; } .kohthai-shipping-rate-estimate { font-size: 0.9em; margin-top: 5px; color: #005a8d; font-weight: 600; } .kohthai-shipping-rate-estimate i { margin-right: 6px; } .kohthai-shipping-rate-estimate.backorder-package-notice { color: #b30000; } .woocommerce-shipping-packages h2 { font-size: 1.5em; margin-top: 2em; padding-bottom: 0.5em; border-bottom: 2px solid #ddd; } </style> <?php if (is_product()): ?> <script> jQuery(document).ready(function($){ $('#kohthai-change-location-link-product').click(function(e){ e.preventDefault(); $('#kohthai-location-options-product').slideToggle(); }); $('#kohthai-location-select-product').change(function(){ var zoneId = $(this).val(); var estimateTextDiv = $('#kohthai-delivery-estimate-text-product'); if (!zoneId) return; estimateTextDiv.html('<p>Calculating...</p>'); $.ajax({ url: '<?php echo admin_url('admin-ajax.php'); ?>', type: 'POST', data: { action: 'get_product_page_delivery_estimate', zone_id: zoneId }, success: function(response){ if(response.success) { estimateTextDiv.html(response.data); } else { estimateTextDiv.html('<p>Could not get estimate.</p>'); }}}); }); }); </script> <?php endif; }
    
    public function display_estimate_after_shipping_rate($method, $index) { if (!is_object($method) || !method_exists($method, 'get_instance_id')) return; $packages = WC()->cart->get_shipping_packages(); if (!isset($packages[$index]) || !isset($packages[$index]['package_type'])) { return; } $package_type = $packages[$index]['package_type']; $zone = WC_Shipping_Zones::get_zone_matching_package($packages[$index]); $zone_id = is_object($zone) ? $zone->get_id() : 0; $rules = get_option('kohthai_delivery_rules', []); $method_key = $zone_id . ':' . $method->get_instance_id(); $rule = $rules[$method_key] ?? null; if (empty($rule)) return; $is_pickup = isset($rule['pickup']) && $rule['pickup'] === '1'; if ($package_type === 'backorder') { if ($is_pickup) { echo '<div class="kohthai-shipping-rate-estimate backorder-package-notice"><i class="fa-solid fa-store"></i> Ready for Pickup in 1-2 weeks</div>'; } else { $estimate_text = $this->calculate_backorder_delivery_text($rule); echo '<div class="kohthai-shipping-rate-estimate backorder-package-notice"><i class="fa-solid fa-truck-ramp-box"></i> Estimated Delivery: ' . $estimate_text . '</div>'; } return; } if ($package_type === 'in_stock') { $pickup_message = !empty($rule['pickup_message']) ? esc_html($rule['pickup_message']) : 'Ready for pickup'; $estimate_date = $this->calculate_estimate_text($rule); $estimate_message = $is_pickup ? $pickup_message . ' ' . $estimate_date : 'Estimated delivery: ' . $estimate_date; if ($estimate_message) { $message = '<div class="kohthai-shipping-rate-estimate">'; $message .= '<i class="fa-solid ' . ($is_pickup ? 'fa-store' : 'fa-truck-fast') . '"></i> '; $message .= $estimate_message; $message .= '</div>'; echo $message; } } }
    
    private function calculate_backorder_delivery_text($rule) {
        if (empty($rule) || !is_array($rule) || !isset($rule['min_days']) || !isset($rule['max_days'])) {
            return 'in 1-2 weeks';
        }
        $backorder_dispatch_min_days = 7;
        $backorder_dispatch_max_days = 14;
        $shipping_min_days = (int)$rule['min_days'];
        $shipping_max_days = (int)$rule['max_days'];

        $total_min_days = $backorder_dispatch_min_days + $shipping_min_days;
        $total_max_days = $backorder_dispatch_max_days + $shipping_max_days;

        $date_rule = [
            'min_days' => $total_min_days,
            'max_days' => $total_max_days,
            'cutoff'   => '',
        ];
        $full_phrase = $this->calculate_estimate_text($date_rule);
        $clean = trim( preg_replace('#^(by|between)\s+#i', '', wp_strip_all_tags($full_phrase) ) );
        return $clean;
    }

    public function split_cart_by_stock_status($packages) { $instock_items = []; $backorder_items = []; if ( empty( WC()->cart->get_cart() ) ) return []; foreach ( WC()->cart->get_cart() as $item_key => $item ) { if ( ! isset( $item['data'] ) ) continue; $product = $item['data']; if ( $product->is_on_backorder() ) { $backorder_items[ $item_key ] = $item; } else { $instock_items[ $item_key ] = $item; } } $new_packages = []; if ( ! empty( $instock_items ) ) { $new_packages[] = [ 'contents' => $instock_items, 'contents_cost' => array_sum( wp_list_pluck( $instock_items, 'line_total' ) ), 'applied_coupons'=> WC()->cart->get_applied_coupons(), 'user' => [ 'ID' => get_current_user_id() ], 'destination' => WC()->customer->get_shipping(), 'package_type' => 'in_stock', ]; } if ( ! empty( $backorder_items ) ) { $new_packages[] = [ 'contents' => $backorder_items, 'contents_cost' => array_sum( wp_list_pluck( $backorder_items, 'line_total' ) ), 'applied_coupons'=> WC()->cart->get_applied_coupons(), 'user' => [ 'ID' => get_current_user_id() ], 'destination' => WC()->customer->get_shipping(), 'package_type' => 'backorder', ]; } return $new_packages; }
    
    public function rename_shipping_packages($name, $index, $package) { if (isset($package['package_type'])) { if ($package['package_type'] === 'in_stock') { return __('Items Available Now', 'woocommerce'); } if ($package['package_type'] === 'backorder') { return __('Items on Backorder', 'woocommerce'); } } return $name; }
    
    public function add_admin_menu() { add_options_page('Integrated Delivery', 'Integrated Delivery', 'manage_options', 'kohthai_integrated_delivery', array($this, 'options_page_html')); }
    public function settings_init() { register_setting('kohthai_integrated_delivery_page', 'kohthai_delivery_rules'); }
    
    public function options_page_html() { 
        $shipping_zones = WC_Shipping_Zones::get_zones(); 
        $default_zone = new WC_Shipping_Zone(0); 
        $shipping_zones[0] = $default_zone->get_data(); 
        $shipping_zones[0]['formatted_zone_location'] = $default_zone->get_formatted_location(); 
        $shipping_zones[0]['shipping_methods'] = $default_zone->get_shipping_methods(true); 
        $rules = get_option('kohthai_delivery_rules', []); 
        ?> 
        <div class="wrap"> 
            <h1>Integrated Delivery Estimates</h1> 
            <p>Configure delivery rules for your existing WooCommerce Shipping Zones and Methods.</p> 
            <form action="options.php" method="post"> 
            <?php settings_fields('kohthai_integrated_delivery_page'); ?> 
            <table class="form-table"><tbody> 
            <?php foreach ($shipping_zones as $zone_id => $zone_data) : ?> 
                <tr valign="top"><th scope="row" colspan="2"><h3><?php echo esc_html($zone_data['zone_name']); ?> (<?php echo esc_html($zone_data['formatted_zone_location']); ?>)</h3></th></tr> 
                <?php if (!empty($zone_data['shipping_methods'])) : foreach ($zone_data['shipping_methods'] as $instance_id => $method) : $method_key = $zone_id . ':' . $instance_id; $current_rule = isset($rules[$method_key]) ? $rules[$method_key] : []; ?> 
                <tr style="background:#f9f9f9;"> 
                    <th scope="row"><?php echo esc_html($method->get_title()); ?></th> 
                    <td> 
                        <p> 
                            <label>Min Days: <input type="number" name="kohthai_delivery_rules[<?php echo $method_key; ?>][min_days]" value="<?php echo esc_attr($current_rule['min_days'] ?? ''); ?>" placeholder="e.g., 1"></label> 
                            <label>Max Days: <input type="number" name="kohthai_delivery_rules[<?php echo $method_key; ?>][max_days]" value="<?php echo esc_attr($current_rule['max_days'] ?? ''); ?>" placeholder="e.g., 3"></label> 
                        </p> 
                        <p><label>Cutoff Time (24h): <input type="text" name="kohthai_delivery_rules[<?php echo $method_key; ?>][cutoff]" value="<?php echo esc_attr($current_rule['cutoff'] ?? ''); ?>" placeholder="e.g., 17:00"></label></p> 
                        <p><label><input type="checkbox" class="is-pickup-checkbox" name="kohthai_delivery_rules[<?php echo $method_key; ?>][pickup]" value="1" <?php checked($current_rule['pickup'] ?? 0, 1); ?>> Is this a store pickup location?</label></p> 
                        <p class="pickup-message-field" style="<?php echo ($current_rule['pickup'] ?? 0) ? '' : 'display:none;'; ?>"><label>Pickup Message: <input type="text" name="kohthai_delivery_rules[<?php echo $method_key; ?>][pickup_message]" value="<?php echo esc_attr($current_rule['pickup_message'] ?? 'Ready for pickup'); ?>" placeholder="e.g., Ready for pickup"></label></p> 
                    </td> 
                </tr> 
                <?php endforeach; else: ?> 
                <tr><td colspan="2">No shipping methods found for this zone.</td></tr> 
                <?php endif; endforeach; ?> 
                <tr valign="top"> 
                    <th scope="row">Default Fallback Message <p><small>Shown on product pages before location is selected.</small></p> </th> 
                    <td><input type="text" name="kohthai_delivery_rules[default_message]" value="<?php echo esc_attr($rules['default_message'] ?? 'Delivery in 2-4 business days.'); ?>" class="regular-text"></td> 
                </tr> 

                <!-- NEW HOLIDAYS FIELD START -->
                <tr valign="top">
                    <th scope="row">
                        Holidays / Non-Shipping Periods
                        <p><small>Add start and end dates for periods when shipping is paused.</small></p>
                    </th>
                    <td>
                        <div id="kohthai-holidays-wrapper">
                            <?php
                            $holidays = isset($rules['holidays']) && is_array($rules['holidays']) ? array_values($rules['holidays']) : [];
                            if (empty($holidays)) {
                                $holidays[] = ['start' => '', 'end' => ''];
                            }
                            foreach ($holidays as $index => $holiday) {
                                $start = esc_attr($holiday['start'] ?? '');
                                $end = esc_attr($holiday['end'] ?? '');
                                echo '<div class="kohthai-holiday-row" style="margin-bottom: 10px;">';
                                echo '<input type="date" name="kohthai_delivery_rules[holidays]['.$index.'][start]" value="'.$start.'" /> to ';
                                echo '<input type="date" name="kohthai_delivery_rules[holidays]['.$index.'][end]" value="'.$end.'" /> ';
                                echo '<button type="button" class="button kohthai-remove-holiday">Remove</button>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <button type="button" class="button" id="kohthai-add-holiday" style="margin-top: 10px;">Add Holiday Period</button>
                    </td>
                </tr>
                <!-- NEW HOLIDAYS FIELD END -->

            </tbody></table> 
            <?php submit_button(); ?> 
            </form> 
        </div> 
        <script> 
            jQuery(document).ready(function($) { 
                $('.is-pickup-checkbox').each(function() { 
                    var checkbox = $(this); 
                    var messageField = checkbox.closest('td').find('.pickup-message-field'); 
                    checkbox.on('change', function() { 
                        if (this.checked) { messageField.show(); } else { messageField.hide(); } 
                    }); 
                }); 
                
                // Holiday Dynamic Add/Remove Scripts
                $('#kohthai-add-holiday').click(function() {
                    var wrapper = $('#kohthai-holidays-wrapper');
                    var index = Date.now(); // Using timestamp ensures unique keys
                    var html = '<div class="kohthai-holiday-row" style="margin-bottom: 10px;">' +
                               '<input type="date" name="kohthai_delivery_rules[holidays]['+index+'][start]" value="" /> to ' +
                               '<input type="date" name="kohthai_delivery_rules[holidays]['+index+'][end]" value="" /> ' +
                               '<button type="button" class="button kohthai-remove-holiday">Remove</button>' +
                               '</div>';
                    wrapper.append(html);
                });

                $(document).on('click', '.kohthai-remove-holiday', function() {
                    $(this).closest('.kohthai-holiday-row').remove();
                });
            }); 
        </script> 
        <?php 
    }
    
    public function backorder_notice_cart_checkout($item_data, $cart_item) { if (isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product') && $cart_item['data']->is_on_backorder()) { $item_data[] = ['key' => '<i class="fa-solid fa-truck-ramp-box"></i> Delivery Note', 'value' => 'This item ships in 1-2 weeks']; } return $item_data; }
    public function backorder_notice_orders_emails($item_id, $item, $order, $plain_text) { if ($item->get_meta('backordered')) { $message = 'This is a backorder item. Estimated delivery time is 1-2 weeks.'; if ($plain_text) { echo "\n" . $message; } else { echo '<div style="display:block; margin-top:8px;"><small>' . $message . '</small></div>'; } } }
    
    private function calculate_estimate_text($rule) {
        if (empty($rule) || !is_array($rule) || !isset($rule['min_days']) || !isset($rule['max_days'])) {
            return '';
        }

        $min_days = (int)$rule['min_days'];
        $max_days = (int)$rule['max_days'];
        $cutoff   = isset($rule['cutoff']) ? trim($rule['cutoff']) : '';

        // Fetch global rules to get the holiday dates
        $global_rules = get_option('kohthai_delivery_rules', []);
        $holidays = isset($global_rules['holidays']) && is_array($global_rules['holidays']) ? $global_rules['holidays'] : [];

        // Use site timezone (native WP)
        $tz  = wp_timezone();
        $now = new \DateTime('now', $tz);

        // If current time is after cutoff, start from the next business day
        $start_day_offset = 0;
        if ($cutoff !== '') {
            $h = 0; $m = 0;
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $cutoff, $mm)) { $h = (int)$mm[1]; $m = (int)$mm[2]; }
            $cutoff_dt = (clone $now)->setTime($h, $m, 0);
            if ($now > $cutoff_dt) $start_day_offset = 1;
        }

        // Weekend days (Bangladesh Friday = 5).
        $weekend_days = array(5);

        // Smart checking: Is the day a weekend OR a holiday?
        $is_day_off = function(\DateTime $date) use ($weekend_days, $holidays) {
            if (in_array((int)$date->format('w'), $weekend_days, true)) {
                return true;
            }
            
            $current_ymd = $date->format('Y-m-d');
            foreach ($holidays as $period) {
                $start = !empty($period['start']) ? $period['start'] : null;
                $end   = !empty($period['end']) ? $period['end'] : $start; // fallback to start if no end is set
                
                if ($start && $end) {
                    if ($current_ymd >= $start && $current_ymd <= $end) {
                        return true;
                    }
                } elseif ($start) {
                    if ($current_ymd === $start) {
                        return true;
                    }
                }
            }
            return false;
        };

        $add_business_days = function(\DateTime $date, $days_to_add) use ($is_day_off) {
            $current_date = clone $date;
            $added = 0;
            if ($days_to_add === 0) {
                while ($is_day_off($current_date)) {
                    $current_date->modify('+1 day');
                }
                return $current_date;
            }
            while ($added < $days_to_add) {
                $current_date->modify('+1 day');
                if (!$is_day_off($current_date)) {
                    $added++;
                }
            }
            return $current_date;
        };

        // Compute start and end business dates
        $delivery_start_date = clone $now;
        if ($start_day_offset > 0) $delivery_start_date->modify('+1 day');
        
        // Loop until we find a working day for start
        while ($is_day_off($delivery_start_date)) {
            $delivery_start_date->modify('+1 day');
        }
        
        $start_date = $add_business_days($delivery_start_date, $min_days);
        $end_date   = $add_business_days($start_date, max(0, $max_days - $min_days));

        // Locale from active language
        $locale = $this->current_request_locale();

        // Localized cutoff string using WP time format
        $cutoff_str = '';
        if (!empty($cutoff)) {
            $h = 0; $m = 0;
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $cutoff, $mm2)) { $h = (int)$mm2[1]; $m = (int)$mm2[2]; }
            $cutoff_dt = (clone $now)->setTime($h, $m, 0);
            $cutoff_str = $this->with_locale($locale, function() use ($cutoff_dt) {
                $tf = get_option('time_format') ?: 'g:ia';
                return $this->wp_format_dt($tf, $cutoff_dt);
            });
        }

        // If it's an immediate delivery (0-0 days) and before cutoff
        if ($min_days === 0 && $max_days === 0 && $start_day_offset === 0) {
            return '<strong>Today</strong>' . ($cutoff_str ? ' (if you order before ' . esc_html($cutoff_str) . ')' : '');
        }

        // Localized date strings
        $start_str_full = $this->with_locale($locale, function() use ($start_date) {
            return $this->wp_format_dt('l, F j', $start_date); 
        });
        $start_str_short = $this->with_locale($locale, function() use ($start_date) {
            return $this->wp_format_dt('F j', $start_date);    
        });
        $end_str_short = $this->with_locale($locale, function() use ($end_date) {
            return $this->wp_format_dt('F j', $end_date);
        });

        if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
            return 'by <strong>' . esc_html($start_str_full) . '</strong>';
        } else {
            return 'between <strong>' . esc_html($start_str_short) . '</strong> and <strong>' . esc_html($end_str_short) . '</strong>';
        }
    }

    public function ajax_get_product_page_estimate() { $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : null; if ($zone_id === null) { wp_send_json_error('No zone selected.'); } $zone = new WC_Shipping_Zone($zone_id); $methods = $zone->get_shipping_methods(true); $rules = get_option('kohthai_delivery_rules', []); $html = ''; if (empty($methods)) { $html = '<div class="kohthai-delivery-estimate in-stock"><i class="fa-solid fa-truck-fast"></i><div>No shipping options found for this area.</div></div>'; } else { $html .= '<p><strong>Delivery options for ' . esc_html($zone->get_zone_name()) . ':</strong></p><ul>'; foreach ($methods as $instance_id => $method) { $method_key = $zone_id . ':' . $instance_id; $rule = $rules[$method_key] ?? null; if (empty($rule)) continue; $is_pickup = isset($rule['pickup']) && $rule['pickup'] === '1'; $pickup_message = !empty($rule['pickup_message']) ? esc_html($rule['pickup_message']) : 'Ready for pickup'; $estimate = $is_pickup ? $pickup_message . ' ' . $this->calculate_estimate_text($rule) : 'Get it ' . $this->calculate_estimate_text($rule); if ($estimate) { $html .= '<li>' . esc_html($method->get_title()) . ': ' . $estimate . '</li>'; }} $html .= '</ul>'; } wp_send_json_success($html); }
}

new Kohthai_Smart_Delivery_Plugin_V14_0();