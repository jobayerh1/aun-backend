<?php
/**
 * Plugin Name:       AUN Smart Delivery Plugin
 * Description:       Handles intelligent, context-aware delivery estimates and backorder notices with cart splitting. Includes holiday date skipping and a configurable backorder dispatch lead time (global default + per-product override via a dedicated product meta box) with an optional per-product live countdown. A per-product ETA is a one-time setting: once the product restocks it reverts to the global default until set again. Modern card-based settings UI with live preview.
 * Version:           20.3.1
 * Author:            Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Smart_Delivery {

    /** Cached delivery rules — fetched once per request. */
    private static ?array $rules_cache = null;

    /** Prevents the product badge <style> from printing more than once. */
    private static bool $badge_styles_printed = false;

    public function __construct() {
        add_filter( 'woocommerce_cart_shipping_packages',       [ $this, 'split_cart_by_stock_status' ] );
        add_filter( 'woocommerce_shipping_package_name',        [ $this, 'rename_shipping_packages' ], 10, 3 );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );

        add_action( 'woocommerce_single_product_summary',       [ $this, 'unified_product_page_notice_display' ], 35 );
        add_filter( 'woocommerce_get_item_data',                [ $this, 'backorder_notice_cart_checkout' ], 10, 2 );
        add_action( 'woocommerce_order_item_meta_end',          [ $this, 'backorder_notice_orders_emails' ], 10, 4 );
        add_action( 'woocommerce_after_shipping_rate',          [ $this, 'display_estimate_after_shipping_rate' ], 20, 2 );

        add_action( 'woocommerce_checkout_create_order_shipping_item', [ $this, 'save_detailed_estimate_to_shipping_item' ], 10, 4 );
        add_action( 'woocommerce_order_details_after_order_table',     [ $this, 'display_detailed_estimate_on_order_pages' ], 20, 1 );
        add_action( 'woocommerce_email_after_order_table',             [ $this, 'display_detailed_estimate_in_emails' ], 20, 4 );

        add_action( 'wp_ajax_get_product_page_delivery_estimate',        [ $this, 'ajax_get_product_page_estimate' ] );
        add_action( 'wp_ajax_nopriv_get_product_page_delivery_estimate', [ $this, 'ajax_get_product_page_estimate' ] );

        // Fresh-nonce endpoint — see ajax_mint_nonce().
        add_action( 'wp_ajax_aun_delivery_nonce',        [ $this, 'ajax_mint_nonce' ] );
        add_action( 'wp_ajax_nopriv_aun_delivery_nonce', [ $this, 'ajax_mint_nonce' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        // Per-product backorder lead-time override (dedicated meta box on the product editor).
        add_action( 'add_meta_boxes',                   [ $this, 'register_backorder_metabox' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_backorder_fields' ] );

        // When a countdown product leaves backorder (restocked), clear its per-product ETA so it
        // reverts to the GLOBAL default until a new per-product time is set again.
        add_action( 'woocommerce_product_set_stock_status',    [ $this, 'on_stock_status_changed' ], 10, 1 );
        add_action( 'woocommerce_variation_set_stock_status',  [ $this, 'on_stock_status_changed' ], 10, 1 );
        add_action( 'woocommerce_updated_product_stock',       [ $this, 'on_stock_status_changed' ], 10, 1 );
        add_action( 'woocommerce_product_object_updated_props', [ $this, 'on_product_props_updated' ], 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Options — cached
    // -------------------------------------------------------------------------

    private function get_rules(): array {
        if ( self::$rules_cache === null ) {
            $opt = get_option( 'aun_delivery_rules', [] );
            self::$rules_cache = is_array( $opt ) ? $opt : [];
        }
        return self::$rules_cache;
    }

    // -------------------------------------------------------------------------
    // Backorder lead time — global default + per-product override
    // -------------------------------------------------------------------------

    /** Built-in fallback dispatch lead time, used only if nothing is configured. */
    private const BACKORDER_DEFAULT_MIN = 7;
    private const BACKORDER_DEFAULT_MAX = 14;

    /**
     * Resolves the backorder dispatch lead time for a product (or the global default when $product is null).
     * A product's own days/label win; otherwise the global settings apply; otherwise the built-in default.
     * If the product has "live countdown" ticked, the remaining days are counted down from its anchor date;
     * once the window has fully elapsed it returns an "imminent" state ("arriving any day now").
     * @return array{min:int,max:int,label:string,imminent:bool}
     */
    private function resolve_leadtime( $product = null ): array {
        $rules = $this->get_rules();
        $min   = ( ( $rules['backorder_min_days'] ?? '' ) !== '' ) ? max( 0, (int) $rules['backorder_min_days'] ) : self::BACKORDER_DEFAULT_MIN;
        $max   = ( ( $rules['backorder_max_days'] ?? '' ) !== '' ) ? max( 0, (int) $rules['backorder_max_days'] ) : self::BACKORDER_DEFAULT_MAX;
        if ( $max < $min ) $max = $min;
        $label    = trim( (string) ( $rules['backorder_label'] ?? '' ) );
        $imminent = false;

        if ( $product instanceof WC_Product ) {
            $pid     = $product->get_id();
            $pmin    = get_post_meta( $pid, '_aun_backorder_min_days', true );
            $pmax    = get_post_meta( $pid, '_aun_backorder_max_days', true );
            $plabel  = get_post_meta( $pid, '_aun_backorder_label', true );
            $pcd     = get_post_meta( $pid, '_aun_backorder_countdown', true );
            $panchor = get_post_meta( $pid, '_aun_backorder_anchor', true );

            // Variations inherit the parent's override when their own field is blank.
            if ( $product->is_type( 'variation' ) ) {
                $parent = $product->get_parent_id();
                if ( $pmin === '' )   $pmin   = get_post_meta( $parent, '_aun_backorder_min_days', true );
                if ( $pmax === '' )   $pmax   = get_post_meta( $parent, '_aun_backorder_max_days', true );
                if ( $plabel === '' ) $plabel = get_post_meta( $parent, '_aun_backorder_label', true );
                if ( $pcd === '' )  { $pcd    = get_post_meta( $parent, '_aun_backorder_countdown', true );
                                      $panchor = get_post_meta( $parent, '_aun_backorder_anchor', true ); }
            }

            $days_overridden = false;
            if ( $pmin !== '' && is_numeric( $pmin ) ) { $min = max( 0, (int) $pmin ); $days_overridden = true; }
            if ( $pmax !== '' && is_numeric( $pmax ) ) { $max = max( 0, (int) $pmax ); $days_overridden = true; }
            if ( $max < $min ) $max = $min;

            if ( is_string( $plabel ) && trim( $plabel ) !== '' ) {
                $label = trim( $plabel );   // explicit per-product wording wins
            } elseif ( $days_overridden ) {
                $label = '';                // product set its own days → regenerate, ignore the global label
            }

            // Live countdown (per-product opt-in): subtract the days elapsed since the anchor date.
            if ( $pcd === '1' && $panchor !== '' ) {
                $elapsed = $this->days_since( (string) $panchor );
                $min     = max( 0, $min - $elapsed );
                $max     = max( 0, $max - $elapsed );
                if ( $max <= 0 ) {
                    $imminent = true;          // window fully elapsed → graceful "arriving" state
                    $label    = 'any day now';
                } else {
                    $label = $this->format_leadtime_label( $min, $max ); // countdown drives the wording
                }
            }
        }

        if ( $label === '' ) $label = $this->format_leadtime_label( $min, $max );
        return [ 'min' => $min, 'max' => $max, 'label' => $label, 'imminent' => $imminent ];
    }

    /** Whole days elapsed since a stored Y-m-d anchor (store timezone), floored at 0. */
    private function days_since( string $ymd ): int {
        $tz     = wp_timezone();
        $anchor = \DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, $tz );
        if ( ! $anchor ) return 0;
        $today  = ( new \DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0, 0 );
        if ( $today <= $anchor ) return 0;
        return (int) $anchor->diff( $today )->days;
    }

    /** Picks the slowest lead time among the backordered products in a shipping package. */
    private function package_backorder_leadtime( $package ): array {
        $best = null;
        if ( ! empty( $package['contents'] ) ) {
            foreach ( $package['contents'] as $ci ) {
                if ( empty( $ci['data'] ) || ! $ci['data'] instanceof WC_Product || ! $ci['data']->is_on_backorder() ) continue;
                $lt = $this->resolve_leadtime( $ci['data'] );
                if ( $best === null
                     || $lt['max'] > $best['max']
                     || ( $lt['max'] === $best['max'] && $lt['min'] > $best['min'] ) ) {
                    $best = $lt;
                }
            }
        }
        return $best ?? $this->resolve_leadtime( null );
    }

    /** Human label from a day range: "1-2 weeks", "3-5 days", "1 week", "5 days". */
    private function format_leadtime_label( int $min, int $max ): string {
        if ( $max <= 0 ) return '1-2 weeks';
        if ( $min % 7 === 0 && $max % 7 === 0 ) {           // clean multiples of a week
            $wmin = intdiv( $min, 7 );
            $wmax = intdiv( $max, 7 );
            if ( $wmin === $wmax || $wmin === 0 ) return $wmax . ' ' . _n( 'week', 'weeks', $wmax, 'woocommerce' );
            return $wmin . '-' . $wmax . ' weeks';
        }
        if ( $min === $max || $min === 0 ) return $max . ' ' . _n( 'day', 'days', $max, 'woocommerce' );
        return $min . '-' . $max . ' days';
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
        $rules      = $this->get_rules();
        $method_key = $zone_id . ':' . $rate->get_instance_id();
        $rule = $rules[$method_key] ?? null;

        if (empty($rule)) return;
        $is_pickup      = isset($rule['pickup']) && $rule['pickup'] === '1';
        $pickup_message = !empty($rule['pickup_message']) ? esc_html($rule['pickup_message']) : 'Ready for pickup';

        if ($package_type === 'backorder') {
            $leadtime = $this->package_backorder_leadtime($package);
            if ($is_pickup) {
                $estimate_text = $leadtime['imminent'] ? ($pickup_message . ' — available any day now') : ($pickup_message . ' in ' . $leadtime['label']);
            } else {
                $estimate_text = ucfirst( $this->calculate_backorder_delivery_text($rule, $leadtime) );
            }
        } else {
            $estimate_date = $this->calculate_estimate_text($rule);
            $estimate_text = $is_pickup ? $pickup_message . ' ' . $estimate_date : 'Get it ' . $estimate_date;
        }

        if ($estimate_text && !empty($product_names)) {
            $item->add_meta_data('_aun_delivery_estimate', $estimate_text, true);
            $item->add_meta_data('_aun_package_contents', $product_names, true);
            $item->add_meta_data('_aun_package_type', $package_type, true);
            $item->save();
        }
    }

    private function display_detailed_estimate_html($order) {
        $shipping_items = $order->get_items('shipping');
        if (empty($shipping_items) || count($shipping_items) < 1) return;

        $output = '';
        foreach ($shipping_items as $item_id => $item) {
            $estimate = $item->get_meta('_aun_delivery_estimate');
            $contents = $item->get_meta('_aun_package_contents');
            $package_type = $item->get_meta('_aun_package_type');

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
            echo '<div class="aun-order-delivery-estimate" style="margin-top:20px; margin-bottom: 40px;">
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
            $estimate = $item->get_meta('_aun_delivery_estimate');
            $contents = $item->get_meta('_aun_package_contents');
            $package_type = $item->get_meta('_aun_package_type');
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
    
    // -------------------------------------------------------------------------
    // Product page badge
    // -------------------------------------------------------------------------

    /**
     * Outputs the delivery badge <style> once per request (static flag).
     * Inline in the shortcode output — immune to Flatsome UX Builder and
     * WP Rocket's Remove Unused CSS. data-no-optimize tells WP Rocket to skip it.
     */
    private function maybe_print_badge_styles(): string {
        if ( self::$badge_styles_printed ) return '';
        self::$badge_styles_printed = true;

        return '
        <style id="aun-delivery-badge-css" data-no-optimize="1" data-no-minify="1">
            .aun-db-wrap      { margin-top:12px; margin-bottom:0; }
            .aun-db-label     { font-size:12px; font-weight:600; text-transform:uppercase;
                                letter-spacing:.5px; color:#64748b; margin:0 0 8px; }
            .aun-db-pill      { display:inline-flex; border:1px solid #e2e8f0;
                                border-radius:8px; overflow:hidden; background:#fff; }
            .aun-db-cell      { display:flex; align-items:center; gap:7px; padding:10px 16px;
                                font-size:14px; font-weight:600; line-height:1; }
            .aun-db-cell i    { font-size:16px; }
            .aun-db-divider   { width:1px; background:#e2e8f0; align-self:stretch; flex-shrink:0; }
            .aun-db-pill--backorder .aun-db-cell { color:#b91c1c; border-color:#fecaca; }
            .aun-db-pill--backorder { border-color:#fecaca; background:#fff5f5; }
            /* Delivery options for the chosen area: name + price + when.
               The price is the point of the row, so it holds the right edge and
               never wraps; the name/estimate column is what shrinks. */
            .aun-db-opts      { list-style:none; margin:0; padding:0; display:flex;
                                flex-direction:column; gap:6px; }
            .aun-db-opt       { display:flex; align-items:flex-start; gap:10px;
                                padding:10px 14px; background:#fff;
                                border:1px solid #e2e8f0; border-radius:8px;
                                transition:border-color .15s ease, box-shadow .15s ease; }
            .aun-db-opt:hover { border-color:#bfdbfe; box-shadow:0 1px 4px rgba(1,136,254,.10); }
            .aun-db-opt-icon  { font-size:16px; color:#0188fe; flex:none; margin-top:2px; }
            .aun-db-opt-main  { display:flex; flex-direction:column; gap:2px;
                                flex:1 1 auto; min-width:0; }
            /* Name on the left, price pinned right, both on the same line. */
            .aun-db-opt-top   { display:flex; align-items:baseline; gap:10px;
                                justify-content:space-between; }
            .aun-db-opt-name  { font-size:14px; font-weight:600; line-height:1.25;
                                color:#0f172a; overflow-wrap:anywhere; }
            .aun-db-opt-when  { font-size:12.5px; line-height:1.35; color:#64748b;
                                overflow-wrap:anywhere; }
            .aun-db-opt-when strong { color:#334155; font-weight:600; }
            .aun-db-opt-cost  { flex:none; font-size:14px; font-weight:700;
                                color:#0f172a; white-space:nowrap; }
            .aun-db-opt-cost--free { color:#15803d; }
            .aun-db-opt-cost .amount,
            .aun-db-opt-cost bdi { color:inherit; font-weight:inherit; }
            @media (max-width:420px){
                .aun-db-opt      { padding:9px 11px; gap:8px; }
                .aun-db-opt-name { font-size:13.5px; }
                .aun-db-opt-when { font-size:12px; }
            }
            /* Location selector below the badge */
            .aun-db-location  { margin-top:8px; font-size:12px; color:#64748b; }
            .aun-db-location a { color:#0188fe; text-decoration:underline; cursor:pointer; }
            #aun-location-options-product { margin-top:8px; }
            #aun-location-options-product select { font-size:13px; padding:6px 10px; border:1px solid #e2e8f0; border-radius:7px; }
        </style>';
    }

    public function unified_product_page_notice_display() {
        $product = wc_get_product( get_the_ID() );
        if ( ! $product ) return;

        if ( has_term( 'dp-discontinued', 'product_discontinued', $product->get_id() ) ) return;
        if ( ! $product->is_in_stock() ) return;

        echo $this->maybe_print_badge_styles();

        if ( $product->is_on_backorder() ) {
            $leadtime = $this->resolve_leadtime( $product );
            ?>
            <div class="aun-db-wrap">
                <p class="aun-db-label">Estimated delivery</p>
                <div class="aun-db-pill aun-db-pill--backorder">
                    <div class="aun-db-cell">
                        <i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i>
                        <span><?php echo esc_html( $leadtime['imminent']
                            ? __( 'Backorder — arriving any day now', 'woocommerce' )
                            : sprintf( __( 'Backorder — ships in %s', 'woocommerce' ), $leadtime['label'] ) ); ?></span>
                    </div>
                </div>
            </div>
            <?php
        } else {
            $rules           = $this->get_rules();
            $shipping_zones  = WC_Shipping_Zones::get_zones();
            $default_zone    = new WC_Shipping_Zone( 0 );
            $default_message = esc_html( $rules['default_message'] ?? 'Delivery in 2-4 business days.' );
            ?>
            <div class="aun-db-wrap" id="aun-delivery-container-product">
                <p class="aun-db-label">Estimated delivery</p>
                <div id="aun-delivery-estimate-text-product">
                    <div class="aun-db-pill">
                        <div class="aun-db-cell">
                            <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                            <span><?php echo $default_message; ?></span>
                        </div>
                    </div>
                </div>
                <div class="aun-db-location">
                    Not your location?
                    <a id="aun-change-location-link-product">Select your area for a more accurate estimate.</a>
                    <div id="aun-location-options-product" style="display:none;">
                        <select id="aun-location-select-product">
                            <option value="">Select area...</option>
                            <?php foreach ( $shipping_zones as $zone_id => $zone_data ) : ?>
                                <option value="<?php echo esc_attr( $zone_id ); ?>"><?php echo esc_html( $zone_data['zone_name'] ); ?></option>
                            <?php endforeach; ?>
                            <option value="0"><?php echo esc_html( $default_zone->get_zone_name() ); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    public function enqueue_frontend_assets() {
        // Styles and scripts are only needed on product pages, cart, and checkout.
        if ( ! is_product() && ! is_cart() && ! is_checkout() ) return;

        // Register a no-src handle so we can attach inline styles cleanly.
        wp_register_style( 'aun-delivery', false );
        wp_enqueue_style( 'aun-delivery' );

        $css = '
            .aun-delivery-estimate-container { margin-top:1.5em; margin-bottom:1em; padding:12px; background:#f7f7f7; border:1px solid #e0e0e0; border-radius:5px; }
            .aun-delivery-estimate { margin:0; display:flex; align-items:center; }
            .aun-delivery-estimate i { font-size:1.8em; margin-right:12px; }
            .aun-delivery-estimate-container.aun-backorder-notice { color:#b30000; background:#fff0f0; border-color:#ffdddd; }
            .aun-backorder-notice .aun-delivery-estimate i { color:#b30000; }
            .aun-delivery-estimate.in-stock i { color:#1e85be; }
            .aun-location-selector a { cursor:pointer; text-decoration:underline; }
            .aun-shipping-rate-estimate { font-size:.9em; margin-top:5px; color:#005a8d; font-weight:600; }
            .aun-shipping-rate-estimate i { margin-right:6px; }
            .aun-shipping-rate-estimate.backorder-package-notice { color:#b30000; }
            .woocommerce-shipping-packages h2 { font-size:1.5em; margin-top:2em; padding-bottom:.5em; border-bottom:2px solid #ddd; }
        ';
        wp_add_inline_style( 'aun-delivery', $css );

        if ( is_product() ) {
            // Pass ajax URL and nonce via wp_localize_script — never inline in HTML.
            wp_register_script( 'aun-delivery-js', false, [ 'jquery' ] );
            wp_enqueue_script( 'aun-delivery-js' );
            // The product id matters: a flat rate can price per shipping class or
            // by [qty], and free shipping can have a minimum spend. Pricing an empty
            // package would quote the wrong number, or wrongly show "Free".
            wp_localize_script( 'aun-delivery-js', 'AUN_DELIVERY', [
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'aun_delivery_nonce' ),
                'product_id' => (int) get_queried_object_id(),
            ] );
            wp_add_inline_script( 'aun-delivery-js', '
                jQuery(document).ready(function($){
                    var $estPanel = $("#aun-delivery-estimate-text-product");

                    /* The generic estimate the page was rendered with. Kept so that
                       clearing the area restores exactly that, instead of leaving the
                       options and prices for the previous area on screen under a
                       dropdown that says nothing is selected. */
                    var aunDefaultPanel = $estPanel.html();

                    $("#aun-change-location-link-product").on("click", function(e){
                        e.preventDefault();
                        $("#aun-location-options-product").slideToggle();
                    });
                    $("#aun-location-select-product").on("change", function(){
                        var zoneId = $(this).val();
                        var $est   = $("#aun-delivery-estimate-text-product");

                        /* "Select area..." is value="" -- the only falsy option.
                           The default zone is "0", which is a non-empty string and
                           therefore truthy, so it still asks the server. */
                        if (!zoneId) {
                            $est.html(aunDefaultPanel);
                            return;
                        }
                        $est.html("<p>Calculating...</p>");

                        /* WP ROCKET: the localized nonce is baked into cached
                           product-page HTML and goes stale within a day. A
                           bad_nonce refusal means exactly that, so mint a fresh
                           one and replay the request once. Previously there was
                           no error branch at all, so a stale nonce left this
                           stuck on "Calculating..." forever. */
                        function aunEstimate(retried){
                            $.ajax({
                                url:  AUN_DELIVERY.ajax_url,
                                type: "POST",
                                dataType: "json",
                                data: {
                                    action:     "get_product_page_delivery_estimate",
                                    zone_id:    zoneId,
                                    product_id: AUN_DELIVERY.product_id,
                                    nonce:      AUN_DELIVERY.nonce
                                },
                                success: function(res){
                                    $est.html(res && res.success ? res.data : "<p>Could not get estimate.</p>");
                                },
                                error: function(xhr){
                                    var d = xhr && xhr.responseJSON && xhr.responseJSON.data;
                                    var stale = d && d.code === "bad_nonce";

                                    if (!stale || retried) {
                                        $est.html("<p>Could not get estimate.</p>");
                                        return;
                                    }

                                    $.post(AUN_DELIVERY.ajax_url, { action: "aun_delivery_nonce" }, null, "json")
                                        .done(function(n){
                                            if (n && n.success && n.data && n.data.nonce) {
                                                AUN_DELIVERY.nonce = n.data.nonce;
                                                aunEstimate(true);
                                            } else {
                                                $est.html("<p>Could not get estimate.</p>");
                                            }
                                        })
                                        .fail(function(){
                                            $est.html("<p>Could not get estimate.</p>");
                                        });
                                }
                            });
                        }

                        aunEstimate(false);
                    });
                });
            ' );
        }
    }
    
    public function display_estimate_after_shipping_rate( $method, $index ) {
        if ( ! is_object( $method ) || ! method_exists( $method, 'get_instance_id' ) ) return;

        $packages = WC()->cart->get_shipping_packages();
        if ( ! isset( $packages[ $index ] ) || ! isset( $packages[ $index ]['package_type'] ) ) return;

        $package_type = $packages[ $index ]['package_type'];
        $zone         = WC_Shipping_Zones::get_zone_matching_package( $packages[ $index ] );
        $zone_id      = is_object( $zone ) ? $zone->get_id() : 0;
        $rules        = $this->get_rules();
        $method_key   = $zone_id . ':' . $method->get_instance_id();
        $rule         = $rules[ $method_key ] ?? null;
        if ( empty( $rule ) ) return;

        $is_pickup      = isset( $rule['pickup'] ) && $rule['pickup'] === '1';
        $pickup_message = ! empty( $rule['pickup_message'] ) ? esc_html( $rule['pickup_message'] ) : 'Ready for pickup';

        if ( $package_type === 'backorder' ) {
            $leadtime = $this->package_backorder_leadtime( $packages[ $index ] );
            if ( $is_pickup ) {
                $pk = $leadtime['imminent'] ? ( $pickup_message . ' — available any day now' ) : ( $pickup_message . ' in ' . $leadtime['label'] );
                echo '<div class="aun-shipping-rate-estimate backorder-package-notice"><i class="fa-solid fa-store"></i> '
                     . esc_html( $pk ) . '</div>';
            } else {
                $est = esc_html( $this->calculate_backorder_delivery_text( $rule, $leadtime ) );
                echo '<div class="aun-shipping-rate-estimate backorder-package-notice"><i class="fa-solid fa-truck-ramp-box"></i> Estimated delivery: ' . $est . '</div>';
            }
            return;
        }

        if ( $package_type === 'in_stock' ) {
            $estimate_date    = $this->calculate_estimate_text( $rule );
            $estimate_message = $is_pickup
                ? $pickup_message . ' ' . $estimate_date
                : 'Estimated delivery: ' . $estimate_date;
            if ( $estimate_message ) {
                $icon = $is_pickup ? 'fa-store' : 'fa-truck-fast';
                echo '<div class="aun-shipping-rate-estimate"><i class="fa-solid ' . esc_attr( $icon ) . '"></i> '
                     . wp_kses_post( $estimate_message ) . '</div>';
            }
        }
    }
    
    private function calculate_backorder_delivery_text($rule, $leadtime = null) {
        $lt = is_array($leadtime) ? $leadtime : $this->resolve_leadtime(null);

        if (empty($rule) || !is_array($rule) || !isset($rule['min_days']) || !isset($rule['max_days'])) {
            return ! empty( $lt['imminent'] ) ? 'arriving any day now' : 'in ' . $lt['label'];
        }

        // Dispatch lead time (configurable) + the method's own shipping transit days.
        $total_min_days = $lt['min'] + (int) $rule['min_days'];
        $total_max_days = $lt['max'] + (int) $rule['max_days'];

        $date_rule = [
            'min_days' => $total_min_days,
            'max_days' => $total_max_days,
            'cutoff'   => '',
        ];
        $full_phrase = $this->calculate_estimate_text($date_rule);
        // Keep the natural "between …/by …" wording; just drop the <strong> tags so callers can esc_html() it.
        return trim( wp_strip_all_tags( $full_phrase ) );
    }

    /**
     * Ship backordered items as their own package.
     *
     * WooCommerce charges shipping PER PACKAGE, so a cart holding one in-stock and
     * one backordered item is quoted the delivery fee TWICE (measured: 60 + 60 = 120
     * on a flat-rate zone). That is correct if the two parcels really do travel
     * separately, and wrong if they are held and sent together -- which is why it is
     * now a setting rather than a permanent assumption. Default 'yes' keeps the
     * behaviour this site has always had.
     */
    public function split_cart_by_stock_status($packages) { $r = $this->get_rules(); if ( isset( $r['split_backorders'] ) && 'no' === $r['split_backorders'] ) return $packages; $instock_items = []; $backorder_items = []; if ( empty( WC()->cart->get_cart() ) ) return $packages; foreach ( WC()->cart->get_cart() as $item_key => $item ) { if ( ! isset( $item['data'] ) ) continue; $product = $item['data']; if ( $product->is_on_backorder() ) { $backorder_items[ $item_key ] = $item; } else { $instock_items[ $item_key ] = $item; } } $new_packages = []; if ( ! empty( $instock_items ) ) { $new_packages[] = [ 'contents' => $instock_items, 'contents_cost' => array_sum( wp_list_pluck( $instock_items, 'line_total' ) ), 'applied_coupons'=> WC()->cart->get_applied_coupons(), 'user' => [ 'ID' => get_current_user_id() ], 'destination' => WC()->customer->get_shipping(), 'package_type' => 'in_stock', ]; } if ( ! empty( $backorder_items ) ) { $new_packages[] = [ 'contents' => $backorder_items, 'contents_cost' => array_sum( wp_list_pluck( $backorder_items, 'line_total' ) ), 'applied_coupons'=> WC()->cart->get_applied_coupons(), 'user' => [ 'ID' => get_current_user_id() ], 'destination' => WC()->customer->get_shipping(), 'package_type' => 'backorder', ]; } return $new_packages; }
    
    public function rename_shipping_packages($name, $index, $package) { if (isset($package['package_type'])) { if ($package['package_type'] === 'in_stock') { return __('Items Available Now', 'woocommerce'); } if ($package['package_type'] === 'backorder') { return __('Items on Backorder', 'woocommerce'); } } return $name; }
    
    public function add_admin_menu() {
        add_options_page( 'Integrated Delivery', 'Integrated Delivery', 'manage_options', 'aun_integrated_delivery', [ $this, 'options_page_html' ] );
    }

    public function settings_init() {
        register_setting(
            'aun_integrated_delivery_page',
            'aun_delivery_rules',
            [ 'sanitize_callback' => [ $this, 'sanitize_delivery_rules' ] ]
        );
    }

    public function sanitize_delivery_rules( $input ): array {
        $out = [];
        if ( ! is_array( $input ) ) return $out;

        // An unticked checkbox posts NOTHING. The hidden companion field proves the
        // form was submitted, so an absent checkbox means "off" rather than "leave
        // it alone" -- without this the toggle could be switched on but never off.
        if ( ! empty( $input['split_backorders_present'] ) ) {
            $input['split_backorders'] = ! empty( $input['split_backorders'] ) ? 'yes' : 'no';
        }
        unset( $input['split_backorders_present'] );

        foreach ( $input as $key => $value ) {
            if ( $key === 'default_message' ) {
                $out['default_message'] = sanitize_text_field( $value );
                continue;
            }
            if ( $key === 'backorder_min_days' ) {
                $out['backorder_min_days'] = ( $value === '' || $value === null ) ? '' : absint( $value );
                continue;
            }
            if ( $key === 'backorder_max_days' ) {
                $out['backorder_max_days'] = ( $value === '' || $value === null ) ? '' : absint( $value );
                continue;
            }
            if ( $key === 'backorder_label' ) {
                $out['backorder_label'] = sanitize_text_field( $value );
                continue;
            }
            if ( $key === 'split_backorders' ) {
                $out['split_backorders'] = ( 'no' === $value ) ? 'no' : 'yes';
                continue;
            }
            if ( $key === 'holidays' ) {
                $out['holidays'] = [];
                if ( is_array( $value ) ) {
                    foreach ( $value as $h ) {
                        $start = sanitize_text_field( $h['start'] ?? '' );
                        $end   = sanitize_text_field( $h['end']   ?? '' );
                        // Validate date format Y-m-d
                        $start_valid = $start && \DateTime::createFromFormat( 'Y-m-d', $start ) !== false;
                        $end_valid   = $end   && \DateTime::createFromFormat( 'Y-m-d', $end   ) !== false;
                        if ( $start_valid ) {
                            $out['holidays'][] = [
                                'start' => $start,
                                'end'   => $end_valid ? $end : $start,
                            ];
                        }
                    }
                }
                continue;
            }
            // Per-method rule: key is "zone_id:instance_id"
            if ( is_array( $value ) ) {
                $rule = [];
                $rule['min_days']       = absint( $value['min_days']       ?? 0 );
                $rule['max_days']       = absint( $value['max_days']       ?? 0 );
                $rule['pickup']         = ! empty( $value['pickup'] ) ? '1' : '0';
                $rule['pickup_message'] = sanitize_text_field( $value['pickup_message'] ?? '' );
                // Validate cutoff HH:MM format
                $cutoff = sanitize_text_field( $value['cutoff'] ?? '' );
                $rule['cutoff'] = preg_match( '/^\d{1,2}:\d{2}$/', $cutoff ) ? $cutoff : '';
                $out[ sanitize_text_field( $key ) ] = $rule;
            }
        }
        // Invalidate the in-memory cache so next read fetches the new value.
        self::$rules_cache = null;
        return $out;
    }
    
    public function options_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $shipping_zones    = WC_Shipping_Zones::get_zones();
        $default_zone      = new WC_Shipping_Zone( 0 );
        $shipping_zones[0] = $default_zone->get_data();
        $shipping_zones[0]['formatted_zone_location'] = $default_zone->get_formatted_location();
        $shipping_zones[0]['shipping_methods']        = $default_zone->get_shipping_methods( true );
        $rules = $this->get_rules();
        ?>
        <style>
        .aun-dlv{max-width:1000px;margin:16px 0}
        .aun-dlv *{box-sizing:border-box}
        .aun-dlv-hero{background:linear-gradient(120deg,#0b3d91,#0188fe);color:#fff;border-radius:14px;padding:22px 26px;box-shadow:0 12px 30px rgba(1,82,168,.22)}
        .aun-dlv-hero h1{color:#fff;margin:0 0 6px;font-size:22px;line-height:1.2;display:flex;align-items:center;gap:10px}
        .aun-dlv-hero p{margin:0;opacity:.93;font-size:13.5px;max-width:720px}
        .aun-dlv-card{background:#fff;border:1px solid #e4e8ef;border-radius:14px;padding:20px 22px;margin-top:18px;box-shadow:0 2px 10px rgba(20,40,80,.05)}
        .aun-dlv-card>h2{margin:0 0 3px;font-size:16px;display:flex;align-items:center;gap:8px;color:#0c2a4a;padding:0}
        .aun-dlv-card>.sub{margin:0 0 16px;color:#6b7280;font-size:13px}
        .aun-dlv-accent{border-color:#bcd9f8;background:linear-gradient(180deg,#f7fbff,#fff)}
        .aun-dlv-row{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end}
        .aun-dlv-field{display:flex;flex-direction:column;gap:5px}
        .aun-dlv-field>label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
        .aun-dlv input[type=number],.aun-dlv input[type=text],.aun-dlv input[type=date]{padding:9px 12px;border:1px solid #cfd8e3;border-radius:9px;font-size:14px;background:#fff;line-height:1.2;height:auto;box-shadow:none}
        .aun-dlv input[type=number]{width:92px}
        .aun-dlv input[type=text]{min-width:220px}
        .aun-dlv input:focus{border-color:#0188fe;box-shadow:0 0 0 3px rgba(1,136,254,.15);outline:none}
        .aun-dlv .dash{align-self:center;color:#94a3b8;font-weight:700;padding-bottom:9px;font-size:16px}
        .aun-dlv-preview{margin-top:15px;font-size:13px;color:#475569;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .aun-dlv-chip{display:inline-flex;align-items:center;gap:7px;background:#eef6ff;border:1px solid #bcd9f8;color:#0b5cad;font-weight:700;padding:7px 14px;border-radius:999px;font-size:13.5px}
        .aun-dlv-zone{border:1px solid #e4e8ef;border-radius:12px;margin-bottom:14px;overflow:hidden}
        .aun-dlv-zone[open]{box-shadow:0 1px 6px rgba(20,40,80,.05)}
        .aun-dlv-zone>summary{background:#f5f8fc;padding:12px 16px;font-weight:700;color:#0c2a4a;font-size:14px;display:flex;align-items:center;gap:8px;cursor:pointer;list-style:none}
        .aun-dlv-zone>summary::-webkit-details-marker{display:none}
        .aun-dlv-zone>summary::before{content:"\25B8";color:#94a3b8;transition:transform .15s}
        .aun-dlv-zone[open]>summary::before{transform:rotate(90deg)}
        .aun-dlv-zone .loc{font-weight:400;color:#7b8794;font-size:12.5px}
        .aun-dlv-method{padding:14px 16px;border-top:1px solid #eef1f6}
        .aun-dlv-method .mname{font-weight:600;color:#0c2a4a;margin:0 0 10px;font-size:13.5px}
        .aun-dlv-pickup{margin-top:12px;display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;cursor:pointer}
        .aun-dlv-help{color:#6b7280;font-size:12.5px;margin:12px 0 0;line-height:1.55}
        .aun-dlv-help code{background:#eef2f7;padding:2px 6px;border-radius:5px}
        .aun-dlv-holiday{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap}
        .aun-dlv-btn-add{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px dashed #9bbce0;color:#0b5cad;padding:8px 14px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600}
        .aun-dlv-btn-add:hover{background:#f3f9ff}
        .aun-dlv-remove{background:#fff;border:1px solid #e2c4c4;color:#b3261e;border-radius:8px;padding:6px 11px;cursor:pointer;font-size:12.5px}
        .aun-dlv-save{margin-top:20px}
        .aun-dlv-save .button-primary{height:auto;padding:11px 28px;font-size:14px;border-radius:10px}
        @media(max-width:600px){.aun-dlv input[type=text]{min-width:0;width:100%}}
        </style>
        <div class="wrap aun-dlv">
            <div class="aun-dlv-hero">
                <h1>🚚 Integrated Delivery Estimates</h1>
                <p>Delivery timelines for your shipping zones &amp; methods, plus a configurable backorder lead time. Estimates appear on product pages, cart, checkout, order pages and emails.</p>
            </div>
            <form action="options.php" method="post">
            <?php settings_fields( 'aun_integrated_delivery_page' ); ?>

            <div class="aun-dlv-card aun-dlv-accent">
                <h2>📦 Backorder delivery time</h2>
                <p class="sub">How long backordered items take to be ready to ship. This is the <strong>global default</strong> — override it for any specific product in its &ldquo;Backorder Delivery&rdquo; box on the product editor.</p>
                <div class="aun-dlv-row">
                    <div class="aun-dlv-field"><label>Min days</label>
                        <input type="number" min="0" step="1" id="aun-bo-min" name="aun_delivery_rules[backorder_min_days]" value="<?php echo esc_attr( $rules['backorder_min_days'] ?? '' ); ?>" placeholder="7"></div>
                    <span class="dash">–</span>
                    <div class="aun-dlv-field"><label>Max days</label>
                        <input type="number" min="0" step="1" id="aun-bo-max" name="aun_delivery_rules[backorder_max_days]" value="<?php echo esc_attr( $rules['backorder_max_days'] ?? '' ); ?>" placeholder="14"></div>
                    <div class="aun-dlv-field" style="flex:1;min-width:220px"><label>Custom display text (optional)</label>
                        <input type="text" id="aun-bo-label" name="aun_delivery_rules[backorder_label]" value="<?php echo esc_attr( $rules['backorder_label'] ?? '' ); ?>" placeholder="auto from days — e.g. 1-2 weeks"></div>
                </div>
                <div class="aun-dlv-preview">Customers will see: <span class="aun-dlv-chip" id="aun-bo-preview">…</span></div>
                <p class="aun-dlv-help">Leave days blank for the built-in default (7–14). Leave text blank to auto-format from the days — <code>3</code>–<code>5</code> → &ldquo;3-5 days&rdquo;, <code>7</code>–<code>14</code> → &ldquo;1-2 weeks&rdquo;. At checkout these days are added to each method's transit time to compute a real delivery date.</p>

                <?php $split = ( $rules['split_backorders'] ?? 'yes' ) !== 'no'; ?>
                <hr style="border:0;border-top:1px solid #eef1f6;margin:16px 0 12px">
                <label style="display:flex;gap:9px;align-items:flex-start;font-size:13.5px;cursor:pointer">
                    <input type="checkbox" name="aun_delivery_rules[split_backorders]" value="yes" <?php checked( $split ); ?> style="margin-top:3px">
                    <span><strong>Ship backordered items separately</strong><br>
                    <span style="color:#6b7280">When a cart mixes in-stock and backordered items they become two parcels, so WooCommerce quotes the delivery charge <strong>twice</strong> — the customer pays for both. Untick this to hold the whole order and charge delivery once.</span></span>
                </label>
                <input type="hidden" name="aun_delivery_rules[split_backorders_present]" value="1">
            </div>

            <div class="aun-dlv-card">
                <h2>💬 Default product-page message</h2>
                <p class="sub">Shown on in-stock product pages before a customer selects their area.</p>
                <input type="text" name="aun_delivery_rules[default_message]" value="<?php echo esc_attr( $rules['default_message'] ?? 'Delivery in 2-4 business days.' ); ?>" style="width:100%;max-width:460px">
            </div>

            <div class="aun-dlv-card">
                <h2>🗺️ Shipping zones &amp; methods</h2>
                <p class="sub">Set the in-stock transit time (and optional order cutoff) per method. Tick store-pickup methods to show a pickup message instead.</p>
            <?php foreach ( $shipping_zones as $zone_id => $zone_data ) : ?>
                <details class="aun-dlv-zone" open>
                    <summary><?php echo esc_html( $zone_data['zone_name'] ); ?> <span class="loc"><?php echo esc_html( $zone_data['formatted_zone_location'] ); ?></span></summary>
                    <?php if ( ! empty( $zone_data['shipping_methods'] ) ) :
                        foreach ( $zone_data['shipping_methods'] as $instance_id => $method ) :
                            $method_key   = $zone_id . ':' . $instance_id;
                            $current_rule = $rules[ $method_key ] ?? [];
                            ?>
                    <div class="aun-dlv-method">
                        <p class="mname"><?php echo esc_html( $method->get_title() ); ?></p>
                        <div class="aun-dlv-row">
                            <div class="aun-dlv-field"><label>Min days</label>
                                <input type="number" min="0" name="aun_delivery_rules[<?php echo esc_attr( $method_key ); ?>][min_days]" value="<?php echo esc_attr( $current_rule['min_days'] ?? '' ); ?>" placeholder="1"></div>
                            <span class="dash">–</span>
                            <div class="aun-dlv-field"><label>Max days</label>
                                <input type="number" min="0" name="aun_delivery_rules[<?php echo esc_attr( $method_key ); ?>][max_days]" value="<?php echo esc_attr( $current_rule['max_days'] ?? '' ); ?>" placeholder="3"></div>
                            <div class="aun-dlv-field"><label>Order cutoff (24h)</label>
                                <input type="text" style="width:96px;min-width:0" name="aun_delivery_rules[<?php echo esc_attr( $method_key ); ?>][cutoff]" value="<?php echo esc_attr( $current_rule['cutoff'] ?? '' ); ?>" placeholder="17:00"></div>
                        </div>
                        <label class="aun-dlv-pickup"><input type="checkbox" class="is-pickup-checkbox" name="aun_delivery_rules[<?php echo esc_attr( $method_key ); ?>][pickup]" value="1" <?php checked( $current_rule['pickup'] ?? 0, 1 ); ?>> This is a store pickup location</label>
                        <p class="pickup-message-field" style="margin:10px 0 0;<?php echo ( $current_rule['pickup'] ?? 0 ) ? '' : 'display:none;'; ?>">
                            <span class="aun-dlv-field" style="max-width:340px"><label>Pickup message</label>
                                <input type="text" name="aun_delivery_rules[<?php echo esc_attr( $method_key ); ?>][pickup_message]" value="<?php echo esc_attr( $current_rule['pickup_message'] ?? 'Ready for pickup' ); ?>" placeholder="Ready for pickup"></span>
                        </p>
                    </div>
                        <?php endforeach;
                    else : ?>
                    <div class="aun-dlv-method" style="color:#7b8794">No shipping methods in this zone.</div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
            </div>

            <div class="aun-dlv-card">
                <h2>📅 Holidays / non-shipping periods</h2>
                <p class="sub">Date ranges when dispatch is paused. Weekends (Friday) are skipped automatically.</p>
                <div id="aun-holidays-wrapper">
                <?php
                $holidays = isset( $rules['holidays'] ) && is_array( $rules['holidays'] )
                    ? array_values( $rules['holidays'] )
                    : [ [ 'start' => '', 'end' => '' ] ];
                foreach ( $holidays as $index => $holiday ) :
                    $start = esc_attr( $holiday['start'] ?? '' );
                    $end   = esc_attr( $holiday['end']   ?? '' );
                ?>
                    <div class="aun-dlv-holiday">
                        <input type="date" name="aun_delivery_rules[holidays][<?php echo $index; ?>][start]" value="<?php echo $start; ?>">
                        <span class="dash">→</span>
                        <input type="date" name="aun_delivery_rules[holidays][<?php echo $index; ?>][end]" value="<?php echo $end; ?>">
                        <button type="button" class="aun-dlv-remove aun-remove-holiday">Remove</button>
                    </div>
                <?php endforeach; ?>
                </div>
                <button type="button" class="aun-dlv-btn-add" id="aun-add-holiday">＋ Add holiday period</button>
            </div>

            <div class="aun-dlv-save"><?php submit_button( 'Save delivery settings', 'primary', 'submit', false ); ?></div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // pickup message toggle
            $('.is-pickup-checkbox').each(function() {
                var $cb  = $(this);
                var $msg = $cb.closest('.aun-dlv-method').find('.pickup-message-field');
                $cb.on('change', function() { $msg.toggle(this.checked); });
            });
            // add / remove holiday rows
            $('#aun-add-holiday').on('click', function() {
                var idx = Date.now();
                $('#aun-holidays-wrapper').append(
                    '<div class="aun-dlv-holiday">' +
                    '<input type="date" name="aun_delivery_rules[holidays][' + idx + '][start]" value="">' +
                    '<span class="dash">→</span>' +
                    '<input type="date" name="aun_delivery_rules[holidays][' + idx + '][end]" value="">' +
                    '<button type="button" class="aun-dlv-remove aun-remove-holiday">Remove</button>' +
                    '</div>'
                );
            });
            $(document).on('click', '.aun-remove-holiday', function() {
                $(this).closest('.aun-dlv-holiday').remove();
            });
            // live backorder label preview — mirrors PHP format_leadtime_label()
            function fmt(mn, mx) {
                if (mx <= 0) return '';
                if (mn % 7 === 0 && mx % 7 === 0) {
                    var a = mn / 7, b = mx / 7;
                    if (a === b || a === 0) return b + ' ' + (b === 1 ? 'week' : 'weeks');
                    return a + '-' + b + ' weeks';
                }
                if (mn === mx || mn === 0) return mx + ' ' + (mx === 1 ? 'day' : 'days');
                return mn + '-' + mx + ' days';
            }
            function boPrev() {
                var c = ($('#aun-bo-label').val() || '').trim();
                if (c) { $('#aun-bo-preview').text(c); return; }
                var mnR = $('#aun-bo-min').val(), mxR = $('#aun-bo-max').val();
                var mn = mnR === '' ? 7 : parseInt(mnR, 10), mx = mxR === '' ? 14 : parseInt(mxR, 10);
                if (isNaN(mn)) mn = 7; if (isNaN(mx)) mx = 14; if (mx < mn) mx = mn;
                $('#aun-bo-preview').text(fmt(mn, mx) || '—');
            }
            $('#aun-bo-min, #aun-bo-max, #aun-bo-label').on('input', boPrev);
            boPrev();
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Per-product backorder override (WooCommerce → Product data → Inventory)
    // -------------------------------------------------------------------------

    public function register_backorder_metabox() {
        add_meta_box(
            'aun_backorder_box',
            '🚚 ' . __( 'Backorder Delivery', 'woocommerce' ),
            [ $this, 'render_backorder_metabox' ],
            'product',
            'side',
            'high'
        );
    }

    public function render_backorder_metabox( $post ) {
        $min       = get_post_meta( $post->ID, '_aun_backorder_min_days', true );
        $max       = get_post_meta( $post->ID, '_aun_backorder_max_days', true );
        $label     = get_post_meta( $post->ID, '_aun_backorder_label', true );
        $countdown = get_post_meta( $post->ID, '_aun_backorder_countdown', true ) === '1';
        $anchor    = (string) get_post_meta( $post->ID, '_aun_backorder_anchor', true );
        $g         = $this->resolve_leadtime( null );                       // global default (placeholders)
        $product   = wc_get_product( $post->ID );
        $current   = $product instanceof WC_Product ? $this->resolve_leadtime( $product ) : $g; // live value now
        $init_text = $current['imminent'] ? 'arriving any day now' : $current['label'];
        wp_nonce_field( 'aun_save_backorder', 'aun_backorder_nonce' );
        ?>
        <style>
        #aun_backorder_box .aun-bo-note{margin:0 0 12px;color:#50575e;line-height:1.5;font-size:12.5px}
        #aun_backorder_box .aun-bo-days{display:flex;align-items:flex-end;gap:8px;margin-bottom:12px}
        #aun_backorder_box label.f{display:flex;flex-direction:column;gap:4px;font-weight:700;color:#646970;font-size:10.5px;text-transform:uppercase;letter-spacing:.3px}
        #aun_backorder_box input[type=number],#aun_backorder_box input[type=text]{padding:7px 9px;border:1px solid #c3c8d2;border-radius:8px;font-size:13px;width:100%}
        #aun_backorder_box .aun-bo-days input[type=number]{width:72px}
        #aun_backorder_box .aun-bo-dash{padding-bottom:8px;color:#8c8f94;font-weight:700}
        #aun_backorder_box .aun-bo-full{display:block;margin-bottom:12px}
        #aun_backorder_box .aun-bo-cd{display:flex;gap:8px;align-items:flex-start;background:#fffdf3;border:1px solid #efe2bb;border-radius:9px;padding:9px 11px;margin-bottom:10px;font-size:12px;line-height:1.45;color:#5b5226;cursor:pointer}
        #aun_backorder_box .aun-bo-cd input{margin-top:2px}
        #aun_backorder_box .aun-bo-cd strong{color:#8a6d18}
        #aun_backorder_box .aun-bo-anchor{margin:0 0 12px;font-size:12px;color:#50575e;background:#f6f7f7;border-radius:8px;padding:9px 11px}
        #aun_backorder_box .aun-bo-anchor label{display:flex;gap:6px;align-items:center;margin-top:8px;color:#1d2327;font-weight:500;text-transform:none;letter-spacing:0;font-size:12px;cursor:pointer}
        #aun_backorder_box .aun-bo-prev{background:#f6fafe;border:1px solid #cfe4fb;border-radius:9px;padding:10px 12px;color:#0b5cad}
        #aun_backorder_box .aun-bo-prev .l{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;color:#5b7290;margin-bottom:3px}
        #aun_backorder_box .aun-bo-chip{font-weight:700;font-size:14px}
        #aun_backorder_box .aun-bo-link{margin:11px 0 0;font-size:12px}
        </style>
        <div class="aun-bo-box">
            <p class="aun-bo-note">Only shown when this product is <strong>on backorder</strong>. Leave blank to use the global default.</p>
            <div class="aun-bo-days">
                <label class="f">Min days
                    <input type="number" min="0" step="1" id="aun-bo-min" name="_aun_backorder_min_days" value="<?php echo esc_attr( $min ); ?>" placeholder="<?php echo esc_attr( $g['min'] ); ?>"></label>
                <span class="aun-bo-dash">–</span>
                <label class="f">Max days
                    <input type="number" min="0" step="1" id="aun-bo-max" name="_aun_backorder_max_days" value="<?php echo esc_attr( $max ); ?>" placeholder="<?php echo esc_attr( $g['max'] ); ?>"></label>
            </div>
            <label class="f aun-bo-full">Custom text (optional)
                <input type="text" id="aun-bo-label" name="_aun_backorder_label" value="<?php echo esc_attr( $label ); ?>" placeholder="auto from days"></label>
            <label class="aun-bo-cd"><input type="checkbox" id="aun-bo-cd" name="_aun_backorder_countdown" value="1" <?php checked( $countdown ); ?>>
                <span>⏱ <strong>Live countdown</strong> — the time decreases each day, then shows &ldquo;arriving any day now&rdquo; once it runs out. Leave off for a fixed estimate that never changes.</span></label>
            <?php if ( $countdown && $anchor ) : ?>
            <div class="aun-bo-anchor">
                Counting down since <strong><?php echo esc_html( $anchor ); ?></strong>.
                <label><input type="checkbox" name="_aun_backorder_restart" value="1"> Restart countdown from today on save</label>
            </div>
            <?php endif; ?>
            <div class="aun-bo-prev"><span class="l">Customers will see</span><span class="aun-bo-chip" id="aun-bo-prev">…</span></div>
            <p class="aun-bo-link"><a href="<?php echo esc_url( admin_url( 'options-general.php?page=aun_integrated_delivery' ) ); ?>" target="_blank">Edit global default →</a></p>
        </div>
        <script>
        (function(){
            var G = { min: <?php echo (int) $g['min']; ?>, max: <?php echo (int) $g['max']; ?>, label: <?php echo wp_json_encode( $g['label'] ); ?> };
            var INIT = <?php echo wp_json_encode( $init_text ); ?>;   // live value the customer sees right now
            var dirty = false;
            function fmt(mn, mx){ if(mx<=0) return ''; if(mn%7===0&&mx%7===0){var a=mn/7,b=mx/7; if(a===b||a===0) return b+' '+(b===1?'week':'weeks'); return a+'-'+b+' weeks';} if(mn===mx||mn===0) return mx+' '+(mx===1?'day':'days'); return mn+'-'+mx+' days'; }
            function el(id){ return document.getElementById(id); }
            function cdOn(){ var c = el('aun-bo-cd'); return !!(c && c.checked); }
            function suffix(){ return cdOn() ? ' · counts down daily' : ''; }
            function upd(){
                var prev = el('aun-bo-prev');
                if (!dirty) { prev.textContent = INIT + suffix(); return; }   // untouched → show the real current value
                var c = (el('aun-bo-label').value || '').trim();
                if (c) { prev.textContent = c; return; }
                var mnR = el('aun-bo-min').value, mxR = el('aun-bo-max').value;
                if (mnR === '' && mxR === '') { prev.textContent = G.label + ' (global default)' + suffix(); return; }
                var mn = mnR === '' ? G.min : parseInt(mnR, 10), mx = mxR === '' ? G.max : parseInt(mxR, 10);
                if (isNaN(mn)) mn = G.min; if (isNaN(mx)) mx = G.max; if (mx < mn) mx = mn;
                prev.textContent = (fmt(mn, mx) || '—') + suffix();
            }
            ['aun-bo-min','aun-bo-max','aun-bo-label'].forEach(function(id){ var e = el(id); if (e) e.addEventListener('input', function(){ dirty = true; upd(); }); });
            var cd = el('aun-bo-cd'); if (cd) cd.addEventListener('change', function(){ dirty = true; upd(); });
            upd();
        })();
        </script>
        <?php
    }

    public function save_product_backorder_fields( $post_id ) {
        // Only act when OUR meta box was actually submitted — prevents wiping the values on
        // unrelated/programmatic saves (quick-edit, REST, etc.) where our fields aren't present.
        if ( ! isset( $_POST['aun_backorder_nonce'] )
             || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aun_backorder_nonce'] ) ), 'aun_save_backorder' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $min   = isset( $_POST['_aun_backorder_min_days'] ) ? wc_clean( wp_unslash( $_POST['_aun_backorder_min_days'] ) ) : '';
        $max   = isset( $_POST['_aun_backorder_max_days'] ) ? wc_clean( wp_unslash( $_POST['_aun_backorder_max_days'] ) ) : '';
        $label = isset( $_POST['_aun_backorder_label'] )    ? sanitize_text_field( wp_unslash( $_POST['_aun_backorder_label'] ) ) : '';

        // Capture previous values BEFORE writing — needed to decide whether to re-anchor the countdown.
        $prev_min    = (string) get_post_meta( $post_id, '_aun_backorder_min_days', true );
        $prev_max    = (string) get_post_meta( $post_id, '_aun_backorder_max_days', true );
        $prev_cd     = (string) get_post_meta( $post_id, '_aun_backorder_countdown', true );
        $prev_anchor = (string) get_post_meta( $post_id, '_aun_backorder_anchor', true );

        if ( $min === '' )   { delete_post_meta( $post_id, '_aun_backorder_min_days' ); } else { update_post_meta( $post_id, '_aun_backorder_min_days', max( 0, (int) $min ) ); }
        if ( $max === '' )   { delete_post_meta( $post_id, '_aun_backorder_max_days' ); } else { update_post_meta( $post_id, '_aun_backorder_max_days', max( 0, (int) $max ) ); }
        if ( $label === '' ) { delete_post_meta( $post_id, '_aun_backorder_label' );    } else { update_post_meta( $post_id, '_aun_backorder_label', $label ); }

        // Live countdown: store the flag + an anchor date. Re-anchor to today when it's freshly enabled,
        // when the day range changed, or when "restart" is ticked — otherwise keep counting from the old anchor.
        if ( isset( $_POST['_aun_backorder_countdown'] ) ) {
            $changed  = ( (string) $min !== $prev_min ) || ( (string) $max !== $prev_max );
            $restart  = isset( $_POST['_aun_backorder_restart'] );
            $reanchor = $restart || $prev_cd !== '1' || $changed || $prev_anchor === '';
            update_post_meta( $post_id, '_aun_backorder_countdown', '1' );
            update_post_meta( $post_id, '_aun_backorder_anchor', $reanchor ? current_time( 'Y-m-d' ) : $prev_anchor );
            // Seed the stock-status tracker so a later restock clears this per-product ETA (revert to global).
            $prod = wc_get_product( $post_id );
            if ( $prod ) update_post_meta( $post_id, '_aun_backorder_last_status', $prod->get_stock_status() );
        } else {
            delete_post_meta( $post_id, '_aun_backorder_countdown' );
            delete_post_meta( $post_id, '_aun_backorder_anchor' );
            delete_post_meta( $post_id, '_aun_backorder_last_status' );
        }
    }

    // -------------------------------------------------------------------------
    // Countdown auto-restart on stock-status transitions
    // -------------------------------------------------------------------------

    /** WC fires this on direct stock status / quantity changes (e.g. an order selling the last unit). */
    public function on_stock_status_changed( $product_id ): void {
        $this->sync_backorder_spell( (int) $product_id );
    }

    /** WC fires this after a product is saved; act only when its stock status actually changed. */
    public function on_product_props_updated( $product, $updated_props ): void {
        if ( $product instanceof WC_Product && is_array( $updated_props ) && in_array( 'stock_status', $updated_props, true ) ) {
            $this->sync_backorder_spell( $product->get_id() );
        }
    }

    /** The full set of per-product backorder ETA meta keys (cleared when a product leaves backorder). */
    private const PER_PRODUCT_BACKORDER_META = [
        '_aun_backorder_countdown', '_aun_backorder_anchor', '_aun_backorder_min_days',
        '_aun_backorder_max_days', '_aun_backorder_label', '_aun_backorder_last_status',
    ];

    /**
     * A per-product countdown is a one-time ETA for ONE backorder spell. When the product LEAVES
     * backorder (the shipment arrived / it's back in stock), that per-product ETA is finished, so we
     * clear ALL of its per-product backorder settings — the product then falls back to the GLOBAL
     * default until the merchant sets a new per-product time again. Guarded by _aun_backorder_last_status
     * so it acts only on a genuine stock-status transition.
     */
    private function sync_backorder_spell( int $product_id ): void {
        if ( $product_id <= 0 ) return;
        if ( get_post_meta( $product_id, '_aun_backorder_countdown', true ) !== '1' ) return; // countdown products only

        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        $now  = $product->get_stock_status();
        $last = (string) get_post_meta( $product_id, '_aun_backorder_last_status', true );
        if ( $now === $last ) return;                                       // no transition → nothing to do

        if ( $now !== 'onbackorder' ) {
            // Left backorder → clear the per-product ETA so it reverts to the global default.
            foreach ( self::PER_PRODUCT_BACKORDER_META as $key ) {
                delete_post_meta( $product_id, $key );
            }
            return;
        }

        // Still / freshly on backorder → just remember the status; the countdown keeps running.
        update_post_meta( $product_id, '_aun_backorder_last_status', $now );
    }

    public function backorder_notice_cart_checkout( $item_data, $cart_item ) {
        if ( isset( $cart_item['data'] )
             && is_a( $cart_item['data'], 'WC_Product' )
             && $cart_item['data']->is_on_backorder() ) {
            $leadtime = $this->resolve_leadtime( $cart_item['data'] );
            $item_data[] = [
                'key'   => 'Delivery note',
                'value' => $leadtime['imminent'] ? 'This item is arriving any day now' : sprintf( 'This item ships in %s', $leadtime['label'] ),
            ];
        }
        return $item_data;
    }
    public function backorder_notice_orders_emails( $item_id, $item, $order, $plain_text ) {
        // Runs per line item. Only annotate backordered products.
        // (Previously this checked '_aun_package_type', which lives on the SHIPPING item, not the
        //  product line item — so the note never appeared. Check the product's backorder status instead.)
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) return;
        $product = $item->get_product();
        if ( ! $product instanceof WC_Product || ! $product->is_on_backorder() ) return;

        $leadtime = $this->resolve_leadtime( $product );
        $message  = $leadtime['imminent']
            ? 'This is a backorder item — arriving any day now.'
            : sprintf( 'This is a backorder item. Estimated delivery time is %s.', $leadtime['label'] );
        if ( $plain_text ) {
            echo "\n" . esc_html( $message );
        } else {
            echo '<div style="display:block;margin-top:8px"><small>' . esc_html( $message ) . '</small></div>';
        }
    }
    
    // -------------------------------------------------------------------------
    // Cutoff time parser — extracted to avoid triple duplication
    // -------------------------------------------------------------------------

    /**
     * A cutoff as hours+minutes, or null when the stored value is not a real time.
     *
     * The settings screen validates the FORMAT (\d{1,2}:\d{2}) but not the range, so
     * "25:99" saves happily -- and setTime(25,99) rolls into tomorrow, quietly making
     * the cutoff unreachable. Anything that fails to parse now returns null and is
     * treated as "no cutoff", instead of becoming 00:00 and pushing every estimate a
     * day later for ever.
     */
    private function parse_cutoff_time( string $cutoff ): ?array {
        if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $cutoff ), $mm ) ) {
            return null;
        }
        $h = (int) $mm[1];
        $m = (int) $mm[2];
        if ( $h > 23 || $m > 59 ) {
            return null;
        }
        return [ 'h' => $h, 'm' => $m ];
    }

    private function calculate_estimate_text( $rule ) {
        if ( empty( $rule ) || ! is_array( $rule )
             || ! isset( $rule['min_days'] ) || ! isset( $rule['max_days'] ) ) {
            return '';
        }

        $min_days = (int) $rule['min_days'];
        $max_days = (int) $rule['max_days'];
        $cutoff   = isset( $rule['cutoff'] ) ? trim( $rule['cutoff'] ) : '';

        // Fetch holidays from cached rules.
        $global_rules = $this->get_rules();
        $holidays = isset( $global_rules['holidays'] ) && is_array( $global_rules['holidays'] )
            ? $global_rules['holidays']
            : [];

        $tz  = wp_timezone();
        $now = new \DateTime( 'now', $tz );

        $start_day_offset = 0;
        $ct = ( $cutoff !== '' ) ? $this->parse_cutoff_time( $cutoff ) : null;
        if ( $ct !== null ) {
            $cutoff_dt = ( clone $now )->setTime( $ct['h'], $ct['m'], 0 );
            if ( $now > $cutoff_dt ) $start_day_offset = 1;
        }

        $weekend_days = [ 5 ]; // Friday in Bangladesh

        $is_day_off = function ( \DateTime $date ) use ( $weekend_days, $holidays ) {
            if ( in_array( (int) $date->format( 'w' ), $weekend_days, true ) ) return true;
            $ymd = $date->format( 'Y-m-d' );
            foreach ( $holidays as $period ) {
                $start = ! empty( $period['start'] ) ? $period['start'] : null;
                $end   = ! empty( $period['end'] )   ? $period['end']   : $start;
                if ( $start && $end ) {
                    if ( $ymd >= $start && $ymd <= $end ) return true;
                } elseif ( $start && $ymd === $start ) {
                    return true;
                }
            }
            return false;
        };

        // Every walk forward is capped. These loops are driven by admin-entered
        // holiday rows; one careless range (or a start date later than its end) would
        // otherwise spin a customer-facing page for as long as PHP allows.
        $MAX_STEPS = 400; // > a year of skipping, and still bounded

        $add_business_days = function ( \DateTime $date, $days_to_add ) use ( $is_day_off, $MAX_STEPS ) {
            $d     = clone $date;
            $steps = 0;
            if ( $days_to_add === 0 ) {
                while ( $is_day_off( $d ) && $steps++ < $MAX_STEPS ) $d->modify( '+1 day' );
                return $d;
            }
            $added = 0;
            while ( $added < $days_to_add && $steps++ < $MAX_STEPS ) {
                $d->modify( '+1 day' );
                if ( ! $is_day_off( $d ) ) $added++;
            }
            return $d;
        };

        $delivery_start = clone $now;
        if ( $start_day_offset > 0 ) $delivery_start->modify( '+1 day' );
        $steps = 0;
        while ( $is_day_off( $delivery_start ) && $steps++ < $MAX_STEPS ) $delivery_start->modify( '+1 day' );

        $start_date = $add_business_days( $delivery_start, $min_days );
        $end_date   = $add_business_days( $start_date, max( 0, $max_days - $min_days ) );

        $locale = $this->current_request_locale();

        $cutoff_str = '';
        if ( $ct !== null ) {
            $cutoff_dt2 = ( clone $now )->setTime( $ct['h'], $ct['m'], 0 );
            $cutoff_str = $this->with_locale( $locale, function () use ( $cutoff_dt2 ) {
                return $this->wp_format_dt( get_option( 'time_format' ) ?: 'g:ia', $cutoff_dt2 );
            } );
        }

        // "Today" is only true when the day we actually deliver IS today. This used
        // to test the RULE (0-0 days, before cutoff) and never look at the computed
        // date -- so on a Friday, or on any declared holiday, the shop promised
        // same-day delivery on a day it was closed. The weekend/holiday skip above
        // had already moved $start_date forward; nothing read it.
        if ( $min_days === 0 && $max_days === 0
             && $start_date->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
            return '<strong>Today</strong>'
                . ( $cutoff_str ? ' (if you order before ' . esc_html( $cutoff_str ) . ')' : '' );
        }

        $start_full  = $this->with_locale( $locale, fn() => $this->wp_format_dt( 'l, F j', $start_date ) );
        $start_short = $this->with_locale( $locale, fn() => $this->wp_format_dt( 'F j',    $start_date ) );
        $end_short   = $this->with_locale( $locale, fn() => $this->wp_format_dt( 'F j',    $end_date   ) );

        if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) {
            return 'by <strong>' . esc_html( $start_full ) . '</strong>';
        }
        return 'between <strong>' . esc_html( $start_short ) . '</strong> and <strong>' . esc_html( $end_short ) . '</strong>';
    }

    /**
     * Hand out a freshly minted nonce.
     *
     * WP ROCKET: this nonce is localized into product-page HTML, which is
     * cached. A nonce lives ~12-24h but the cached page is served far longer,
     * after which every shopper sends a dead token and the delivery estimate
     * sticks on "Calculating..." forever. This lets the browser mint a fresh
     * one at the moment of use and retry.
     *
     * Not nonce-guarded itself: requiring a nonce to obtain a nonce would defeat
     * the purpose, and it reveals nothing a page load does not already reveal.
     */
    public function ajax_mint_nonce() {
        wp_send_json_success( [ 'nonce' => wp_create_nonce( 'aun_delivery_nonce' ) ] );
    }

    /**
     * The rates a shipping method would actually offer for this package.
     *
     * Asking the METHOD is the whole point. The widget used to list every enabled
     * method in the zone, which advertised Free Shipping to everyone even when it is
     * set to "requires a valid free shipping coupon" -- the customer saw a free
     * option they could not have. WC_Shipping_Free_Shipping::get_rates_for_package()
     * returns nothing until its condition is met, so an unavailable method simply
     * stops being listed.
     *
     * Reading the method's own "cost" field instead would be wrong: a flat rate can
     * price per shipping class or with a [qty] formula, and that raw field then reads
     * 0 -- which would show "Free" while checkout charged the real amount.
     *
     * @return array list of [ 'label' => string, 'cost' => float|null ]
     *               empty  = not available here. cost null = method could not price
     *               it without a real cart (a courier plugin, say) -> show no price
     *               rather than a wrong one.
     */
    private function method_rates( $method, array $package ): array {
        if ( ! is_callable( [ $method, 'get_rates_for_package' ] ) ) {
            return [ [ 'label' => $method->get_title(), 'cost' => null ] ];
        }
        try {
            $rates = (array) $method->get_rates_for_package( $package );
        } catch ( \Throwable $e ) {
            return [ [ 'label' => $method->get_title(), 'cost' => null ] ];
        }

        $out = [];
        foreach ( $rates as $rate ) {
            if ( ! $rate instanceof \WC_Shipping_Rate ) continue;
            $cost = (float) $rate->get_cost();
            if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
                $cost += array_sum( (array) $rate->get_taxes() );
            }
            $label = $rate->get_label() ?: $method->get_title();
            $out[] = [ 'label' => $label, 'cost' => $cost ];
        }
        return $out;
    }

    /** A one-item package for this product, addressed inside the chosen zone. */
    private function pseudo_package( $product, $zone ): array {
        $price    = ( $product instanceof \WC_Product ) ? (float) $product->get_price() : 0.0;
        $contents = [];
        if ( $product instanceof \WC_Product ) {
            $contents[ 'aun_' . $product->get_id() ] = [
                'key'               => 'aun_' . $product->get_id(),
                'product_id'        => $product->get_id(),
                'variation_id'      => 0,
                'variation'         => [],
                'quantity'          => 1,
                'data'              => $product,
                'line_total'        => $price,
                'line_subtotal'     => $price,
                'line_tax'          => 0,
                'line_subtotal_tax' => 0,
            ];
        }
        // Free shipping asks the cart about coupons and totals; on a product page
        // there may not be one yet.
        if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }
        return [
            'contents'        => $contents,
            'contents_cost'   => $price,
            'applied_coupons' => ( WC()->cart ) ? WC()->cart->get_applied_coupons() : [],
            'user'            => [ 'ID' => get_current_user_id() ],
            'destination'     => $this->zone_destination( $zone ),
            'cart_subtotal'   => $price,
        ];
    }

    /** An address inside the zone, so each method prices it the way checkout will. */
    private function zone_destination( $zone ): array {
        $dest = [ 'country' => '', 'state' => '', 'postcode' => '', 'city' => '',
                  'address' => '', 'address_1' => '', 'address_2' => '' ];

        foreach ( (array) $zone->get_zone_locations() as $loc ) {
            if ( 'country' === $loc->type && ! $dest['country'] ) {
                $dest['country'] = $loc->code;
            } elseif ( 'state' === $loc->type ) {
                $parts           = explode( ':', $loc->code );
                $dest['country'] = $parts[0];
                $dest['state']   = $parts[1] ?? '';
            } elseif ( 'city' === $loc->type && ! $dest['city'] ) {
                $dest['city'] = $loc->code;
            } elseif ( 'postcode' === $loc->type && ! $dest['postcode'] && ! preg_match( '/[^0-9]/', $loc->code ) ) {
                $dest['postcode'] = $loc->code;
            }
        }
        if ( ! $dest['country'] ) {
            // "Locations not covered by your other zones" has no locations of its own.
            $dest['country'] = WC()->countries->get_base_country();
        }
        return $dest;
    }

    /** One option row: name, price, and when it arrives. */
    private function render_option_row( string $label, $cost, string $estimate, bool $is_pickup ): string {
        $icon = $is_pickup ? 'fa-store' : 'fa-truck-fast';

        $price_html = '';
        if ( null !== $cost ) {
            $price_html = ( (float) $cost > 0 )
                ? '<span class="aun-db-opt-cost">' . wp_kses_post( wc_price( $cost ) ) . '</span>'
                : '<span class="aun-db-opt-cost aun-db-opt-cost--free">Free</span>';
        }

        // The price belongs beside the NAME, not beside the middle of a two-line
        // estimate -- which is where a row-level vertical centre puts it once the
        // text wraps on a phone.
        return '<li class="aun-db-opt">'
             . '<i class="fa-solid ' . esc_attr( $icon ) . ' aun-db-opt-icon" aria-hidden="true"></i>'
             . '<span class="aun-db-opt-main">'
             . '<span class="aun-db-opt-top">'
             . '<span class="aun-db-opt-name">' . esc_html( $label ) . '</span>'
             . $price_html
             . '</span>'
             . ( $estimate ? '<span class="aun-db-opt-when">' . wp_kses_post( $estimate ) . '</span>' : '' )
             . '</span>'
             . '</li>';
    }

    public function ajax_get_product_page_estimate() {
        // Soft check: a hard check_ajax_referer() would wp_die('-1'), which this
        // widget cannot distinguish from a network failure. See ajax_mint_nonce().
        if ( ! check_ajax_referer( 'aun_delivery_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'code' => 'bad_nonce' ], 403 );
        }

        $zone_id = isset( $_POST['zone_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_id'] ) ) : null;
        if ( $zone_id === null ) {
            wp_send_json_error( 'No zone selected.' );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        $product    = $product_id ? wc_get_product( $product_id ) : null;

        $zone    = new WC_Shipping_Zone( $zone_id );
        $methods = $zone->get_shipping_methods( true );
        $rules   = $this->get_rules();
        $package = $this->pseudo_package( $product instanceof WC_Product ? $product : null, $zone );

        $rows = '';
        foreach ( $methods as $instance_id => $method ) {
            $method_key = $zone_id . ':' . $instance_id;
            $rule       = $rules[ $method_key ] ?? null;
            if ( empty( $rule ) ) continue;

            $is_pickup = isset( $rule['pickup'] ) && $rule['pickup'] === '1';
            $estimate  = $is_pickup
                ? ( ( ! empty( $rule['pickup_message'] ) ? esc_html( $rule['pickup_message'] ) : 'Ready for pickup' )
                    . ' ' . $this->calculate_estimate_text( $rule ) )
                : 'Get it ' . $this->calculate_estimate_text( $rule );

            // An empty list means the method is not available for this package --
            // free shipping still waiting on its coupon, most often.
            foreach ( $this->method_rates( $method, $package ) as $r ) {
                $rows .= $this->render_option_row( $r['label'], $r['cost'], $estimate, $is_pickup );
            }
        }

        if ( '' === $rows ) {
            $html = '<div class="aun-db-pill"><div class="aun-db-cell"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i>'
                  . '<span>No delivery options for this area yet &mdash; we will confirm at checkout.</span></div></div>';
        } else {
            $html = '<p class="aun-db-label" style="margin-top:2px">Delivery options for ' . esc_html( $zone->get_zone_name() ) . '</p>'
                  . '<ul class="aun-db-opts">' . $rows . '</ul>';
        }

        wp_send_json_success( $html );
    }
}

new AUN_Smart_Delivery();