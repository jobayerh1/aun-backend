<?php
/**
 * Plugin Name: AUN Smart Projector Wizard
 * Description: AI-guided projector finder. Physics-First scoring with throw-distance analysis,
 *              RAM-quality detection, short-throw name extraction, personalised match explanations,
 *              and a contextual animated loading experience. (v3.4.0 — brightness now reads
 *              a per-product internal field (Product → General → "Finder Brightness") first,
 *              falling back to description text; result cards no longer display numeric
 *              brightness or the term "ANSI" — matching stays physics-accurate internally
 *              while public copy stays qualitative. v3.3.0 — currency-agnostic:
 *              budget thresholds, admin labels and the premium tie-breaker adapt to the
 *              store's WooCommerce currency. v3.2.0 — recommends Backorder products
 *              (admin-toggleable), in-stock preferred, backorder items labelled.)
 * Version:     3.5.0
 * Author:      Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Projector_Wizard {

    const OPTION_NAME = 'aun_wizard_settings';
    const NONCE_KEY   = 'aun_wizard_nonce';

    /** Prevents the finder notice <style> from printing more than once per request. */
    private static bool $finder_badge_styles_printed = false;

    // ═══════════════════════════════════════════════════════════
    //  BOOT
    // ═══════════════════════════════════════════════════════════
    public static function init() {
        add_shortcode( 'aun_projector_finder',    [ __CLASS__, 'render_wizard' ] );
        add_action( 'wp_ajax_aun_get_projector_recommendation',        [ __CLASS__, 'process_recommendation' ] );
        add_action( 'wp_ajax_nopriv_aun_get_projector_recommendation', [ __CLASS__, 'process_recommendation' ] );
        add_shortcode( 'aun_finder_notice',            [ __CLASS__, 'render_finder_notice_shortcode' ] );
        add_shortcode( 'aun_hide_if_discontinued',     [ __CLASS__, 'render_hide_if_discontinued_shortcode' ] );
        add_action( 'admin_menu',  [ __CLASS__, 'admin_menu' ] );
        add_action( 'admin_init',  [ __CLASS__, 'register_settings' ] );
        // Internal brightness field on the product edit screen (General tab).
        add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'render_brightness_field' ] );
        add_action( 'woocommerce_process_product_meta',                 [ __CLASS__, 'save_brightness_field' ] );
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN
    // ═══════════════════════════════════════════════════════════
    public static function admin_menu() {
        add_options_page( 'Projector Wizard Rules', 'Projector Wizard', 'manage_options',
            'aun-projector-wizard', [ __CLASS__, 'settings_page' ] );
    }

    public static function register_settings() {
        register_setting( 'aun_wizard_options_group', self::OPTION_NAME );
    }

    public static function get_settings() {
        $budget = self::default_budget_thresholds();
        return wp_parse_args( get_option( self::OPTION_NAME, [] ), [
            'ansi_dim'        => '350',
            'ansi_bright'     => '800',
            'budget_entry'    => $budget['entry'],
            'budget_mid'      => $budget['mid'],
            'cat_main'         => 'projector-price',
            'portable_weight'  => '1.5',
            'include_backorder'=> '1',
        ]);
    }

    /**
     * Currency-aware default budget breakpoints.
     *
     * The recommendation engine compares raw product prices against these thresholds,
     * so they MUST match the store's currency scale. A BDT store keeps the original
     * Bangladeshi thresholds; every other currency falls back to a USD-scale default
     * suited to a typical projector catalog. These are only defaults — admins can
     * override both under Settings → Projector Wizard → Budget Ranges, and any saved
     * value always takes precedence over these.
     */
    private static function default_budget_thresholds() {
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        if ( $currency === 'BDT' ) {
            return [ 'entry' => '10000', 'mid' => '23000' ]; // original Bangladesh store values
        }
        return [ 'entry' => '150', 'mid' => '400' ];          // USD-scale default (e.g. international store)
    }

    public static function settings_page() {
        $o   = self::get_settings();
        // Show the store's actual currency symbol on the budget fields so the admin
        // always enters values in the correct currency ($ on the international store,
        // ৳ on the Bangladesh store, etc.) instead of a hardcoded one.
        $sym = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
        ?>
        <div class="wrap">
            <h1>Smart Projector Wizard — Settings</h1>
            <p style="color:#555;">These thresholds power the recommendation engine's physics rules.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'aun_wizard_options_group' ); ?>
                <table class="form-table">
                    <tr><th colspan="2"><h3>1. ANSI Brightness Thresholds</h3>
                        <p class="description">The engine reads each product's <strong>Finder Brightness (internal ANSI)</strong> field
                        (Product edit &rarr; General tab). If that field is empty it falls back to scanning the title/description text —
                        note the fallback may pick up manufacturer-rated (marketing) lumens, so fill the field for every projector.</p>
                    </th></tr>
                    <tr valign="top">
                        <th scope="row">"Dimly Lit" Minimum (ANSI)</th>
                        <td>
                            <input type="number" name="<?php echo self::OPTION_NAME; ?>[ansi_dim]" value="<?php echo esc_attr( $o['ansi_dim'] ); ?>" />
                            <p class="description">Products below this ANSI value only suit "Pitch Black" rooms.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">"Bright Daylight" Minimum (ANSI)</th>
                        <td>
                            <input type="number" name="<?php echo self::OPTION_NAME; ?>[ansi_bright]" value="<?php echo esc_attr( $o['ansi_bright'] ); ?>" />
                            <p class="description">Above this value is suitable for rooms with lots of light.</p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h3>2. Boundary &amp; Physical Limits</h3></th></tr>
                    <tr valign="top">
                        <th scope="row">Main Category Slug (Boundary)</th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo self::OPTION_NAME; ?>[cat_main]" value="<?php echo esc_attr( $o['cat_main'] ); ?>" />
                            <p class="description" style="color:#0188fe;font-weight:600;">Only products in this category are scanned. Prevents accessories from appearing.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Max "Portable" Weight (kg)</th>
                        <td>
                            <input type="number" step="0.1" name="<?php echo self::OPTION_NAME; ?>[portable_weight]" value="<?php echo esc_attr( $o['portable_weight'] ); ?>" />
                            <p class="description">
                                Projectors heavier than this receive a portability penalty.<br>
                                <strong>Weight source priority:</strong> The engine reads the product's <strong>Net Weight</strong> from WooCommerce product attributes 
                                (checks attribute slugs: <code>weight</code>, <code>net-weight</code>, <code>nw</code>). 
                                If not found it parses the description text, then falls back to WooCommerce's shipping weight field (which is the G.W.).<br>
                                To get accurate portability scoring, add a product attribute named <code>weight</code> with the net weight value (e.g. <em>1.2 kg</em>) to each projector.
                            </p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h3>3. Stock Availability</h3></th></tr>
                    <tr valign="top">
                        <th scope="row">Include Backorder Products</th>
                        <td>
                            <input type="hidden" name="<?php echo self::OPTION_NAME; ?>[include_backorder]" value="0" />
                            <label>
                                <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[include_backorder]" value="1" <?php checked( $o['include_backorder'] ?? '1', '1' ); ?> />
                                Also recommend products marked &ldquo;On Backorder&rdquo;
                            </label>
                            <p class="description">
                                Out-of-stock products are <strong>always excluded</strong>. In-stock products are <strong>always preferred</strong> —
                                backorder items rank slightly lower on close matches and are clearly labelled &ldquo;On Backorder&rdquo; on the result card.
                            </p>
                        </td>
                    </tr>

                    <tr><th colspan="2"><h3>4. Budget Ranges</h3></th></tr>
                    <tr valign="top">
                        <th scope="row">Entry Level Max Price (<?php echo esc_html( $sym ); ?>)</th>
                        <td><input type="number" name="<?php echo self::OPTION_NAME; ?>[budget_entry]" value="<?php echo esc_attr( $o['budget_entry'] ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mid-Range Max Price (<?php echo esc_html( $sym ); ?>)</th>
                        <td>
                            <input type="number" name="<?php echo self::OPTION_NAME; ?>[budget_mid]" value="<?php echo esc_attr( $o['budget_mid'] ); ?>" />
                            <p class="description">Prices above this are categorised as "Premium".</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // ═══════════════════════════════════════════════════════════
    //  NOTICE SHORTCODES
    // ═══════════════════════════════════════════════════════════

    /**
     * Outputs the finder badge <style> once per request (static flag).
     *
     * WHY inline in shortcode output — not wp_enqueue_scripts:
     *   Flatsome's UX Builder stores shortcode content outside $post->post_content,
     *   so has_shortcode() returns false and enqueue hooks never fire.
     *   WP Rocket's Remove Unused CSS also strips dynamically enqueued inline styles.
     *   Outputting the <style> directly in the shortcode return value is immune to both.
     *   data-no-optimize / data-no-minify tell WP Rocket to leave this tag alone.
     */
    private static function maybe_print_finder_badge_styles(): string {
        if ( self::$finder_badge_styles_printed ) return '';
        self::$finder_badge_styles_printed = true;

        return '
        <style id="aun-finder-badge-css" data-no-optimize="1" data-no-minify="1">
            .aun-fb-wrap     { margin-top:12px; margin-bottom:0; }
            .aun-fb-label    { font-size:12px; font-weight:600; text-transform:uppercase;
                               letter-spacing:.5px; color:#64748b; margin:0 0 8px; }
            .aun-fb-pill     { display:inline-flex; border:1px solid #e2e8f0;
                               border-radius:8px; overflow:hidden; background:#fff; }
            .aun-fb-btn      { display:flex; align-items:center; gap:7px; padding:10px 18px;
                               font-size:14px; font-weight:600; color:#0188fe;
                               text-decoration:none !important; transition:background .15s; line-height:1; }
            .aun-fb-btn:hover { background:#f0f7ff; color:#0070d6; }
            .aun-fb-btn i    { font-size:15px; }
            .aun-fb-arrow    { font-size:13px; opacity:.7; }
        </style>';
    }

    public static function render_finder_notice_shortcode(): string {
        // wc_get_product( get_the_ID() ) is reliable in Flatsome UX Builder and
        // REST contexts — unlike global $product which can be null outside the loop.
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;

        if ( ! $product || ! function_exists( 'is_product' ) || ! is_product() ) {
            return '';
        }

        $opts = self::get_settings();
        $cat  = sanitize_text_field( $opts['cat_main'] ?? '' );
        $pid  = $product->get_id();

        // Only show on products in the configured main category.
        if ( empty( $cat ) || ! has_term( $cat, 'product_cat', $pid ) ) {
            return '';
        }

        // Hide on discontinued products.
        if ( taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $pid ) ) {
            return '';
        }

        // Resolve the finder page URL via WordPress — if the page is ever renamed
        // or moved, this updates automatically without touching plugin code.
        $finder_page = get_page_by_path( 'projector-finder' );
        $finder_url  = $finder_page ? get_permalink( $finder_page ) : home_url( '/projector-finder/' );

        ob_start();
        echo self::maybe_print_finder_badge_styles();
        ?>
        <div class="aun-fb-wrap">
			<p class="aun-fb-label">Need help deciding?</p>
           <!-- <p class="aun-fb-label">Not sure about this model?</p> -->
            <div class="aun-fb-pill">
                <a class="aun-fb-btn"
                   href="<?php echo esc_url( $finder_url ); ?>"
                   rel="noopener noreferrer">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span>Use our 60-Second Smart Finder</span>
                    <span class="aun-fb-arrow" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_hide_if_discontinued_shortcode( $atts, $content = null ) {
        global $product;
        if ( ! is_product() || ! is_a( $product, 'WC_Product' ) ) return do_shortcode( $content );
        if ( taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $product->get_id() ) ) return '';
        return do_shortcode( $content );
    }

    // ═══════════════════════════════════════════════════════════
    //  INTERNAL BRIGHTNESS FIELD (product edit screen)
    // ═══════════════════════════════════════════════════════════

    const BRIGHTNESS_META = '_aun_internal_brightness';

    public static function render_brightness_field() {
        woocommerce_wp_text_input( [
            'id'                => self::BRIGHTNESS_META,
            'label'             => 'Finder Brightness (internal ANSI)',
            'type'              => 'number',
            'desc_tip'          => true,
            'description'       => 'Real-world ANSI brightness used ONLY by the Smart Projector Finder to match room lighting. Never shown to customers — public pages display the manufacturer-rated lumens instead. Leave empty to fall back to scanning the description text.',
            'custom_attributes' => [ 'step' => '1', 'min' => '0' ],
        ] );
    }

    public static function save_brightness_field( $post_id ) {
        if ( isset( $_POST[ self::BRIGHTNESS_META ] ) ) {
            $v = absint( wp_unslash( $_POST[ self::BRIGHTNESS_META ] ) );
            if ( $v > 0 ) update_post_meta( $post_id, self::BRIGHTNESS_META, $v );
            else          delete_post_meta( $post_id, self::BRIGHTNESS_META );
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Brightness for the scoring engine, meta-field-first:
     *   PASS 1 — the internal Finder Brightness meta (real-world ANSI, set per product).
     *   PASS 2 — regex scan of title + description text (legacy fallback).
     *
     * The public product pages show the manufacturer-rated lumens, which is a
     * different (marketing-scale) figure — so the fallback can overstate real
     * brightness and every projector should have the meta field filled in.
     */
    private static function get_brightness_for_product( $product, $text ) {
        $meta = (int) get_post_meta( $product->get_id(), self::BRIGHTNESS_META, true );
        if ( $meta >= 50 && $meta <= 20000 ) return $meta;
        return self::get_ansi( $text );
    }

    /**
     * Fallback text extractor — handles all formats seen in AUN product descriptions:
     *   "1000 ANSI Lumens"  |  "ANSI: 400"  |  "Brightness: 400 ANSI Lumens"  |  "Brightness: 1380 Lumens"
     */
    private static function get_ansi( $text ) {
        $patterns = [
            '/(\d{2,4})\s*ANSI(?:\s*Lumens?)?/i',
            '/ANSI[^\d]{0,8}(\d{2,4})/i',
            '/Brightness[^\d]{0,15}(\d{2,4})/i',
        ];
        foreach ( $patterns as $p ) {
            if ( preg_match( $p, $text, $m ) ) {
                $v = (int) $m[1];
                if ( $v >= 50 && $v <= 20000 ) return $v;
            }
        }
        return 0;
    }

    /** Extracts RAM amount in GB from product description (e.g. "2GB RAM / 32GB ROM") */
    private static function get_ram( $text ) {
        if ( preg_match( '/(\d+)\s*GB\s*RAM/i', $text, $m ) ) {
            $v = (int) $m[1];
            if ( $v >= 1 && $v <= 32 ) return $v;
        }
        return 0;
    }

    /**
     * PASS 1 — Try known meta keys used by popular "Custom Product Tabs" plugins.
     * PASS 2 — Scan ALL product meta for any entry whose HTML contains weight data.
     *           This catches the "Custom Product Tabs for WooCommerce by Web Builder 143"
     *           plugin regardless of which meta key it uses.
     * PASS 3 — Parse product description plain text.
     * PASS 4 — WooCommerce shipping weight (G.W.) as final fallback.
     *
     * Handles the user's exact HTML format:
     *   <div><i class="fa fa-weight-hanging"></i></div>
     *   <div><strong>0.9–1.35kg</strong></div>
     *   <div>Weight</div>
     * Including ranges ("0.9–1.35kg" → returns 1.35, the heavier/conservative value).
     */
    private static function get_product_weight( $product ) {
        $pid = $product->get_id();

        // ── PASS 1: known tab-plugin meta keys ───────────────────────
        $known_keys = [
            '_product_tabs',
            'yikes_woo_products_tabs',
            '_woo_custom_product_tabs',
            'webbuilder_product_tabs',
            '_custom_product_tabs',
            'wcpt_tabs',
            'tab_content',
        ];
        foreach ( $known_keys as $key ) {
            $raw = get_post_meta( $pid, $key, true );
            if ( ! empty( $raw ) ) {
                $html = self::meta_value_to_string( $raw );
                $w    = self::extract_weight_from_html( $html );
                if ( $w > 0 ) return $w;
            }
        }

        // ── PASS 2: scan all meta (catches any tab plugin) ───────────
        // Skip large / known-irrelevant WooCommerce internals for performance.
        $skip = [ '_price', '_regular_price', '_sale_price', '_stock', '_sku', '_thumbnail_id',
                  '_product_image_gallery', '_wp_attached_file', '_edit_lock' ];
        $all_meta = get_post_meta( $pid );
        foreach ( $all_meta as $key => $values ) {
            if ( in_array( $key, $skip, true ) ) continue;
            foreach ( (array) $values as $raw ) {
                $text = self::meta_value_to_string( $raw );
                // Only run regex on entries that look like they contain weight data
                if ( stripos( $text, 'weight' ) !== false && stripos( $text, 'kg' ) !== false ) {
                    $w = self::extract_weight_from_html( $text );
                    if ( $w > 0 ) return $w;
                }
            }
        }

        // ── PASS 3: product description plain text ───────────────────
        $text = $product->get_short_description() . ' ' . $product->get_description();
        if ( preg_match( '/(?:Net\s+)?Weight\s*[:\-]\s*([\d.]+)\s*(?:kg|KG)?/i', $text, $m ) ) {
            $w = (float) $m[1];
            if ( $w > 0 && $w < 50 ) return $w;
        }

        // ── PASS 4: WooCommerce shipping weight (G.W.) ───────────────
        return (float) $product->get_weight();
    }

    /**
     * Normalises any meta value — serialised PHP, JSON, nested array, or plain string —
     * into a single concatenated string that regex can search across.
     */
    private static function meta_value_to_string( $raw ) {
        if ( is_string( $raw ) ) {
            $u = @unserialize( $raw );
            if ( $u !== false )  $raw = $u;
            else {
                $j = @json_decode( $raw, true );
                if ( $j !== null ) $raw = $j;
            }
        }
        return is_array( $raw ) ? self::flatten_array_to_string( $raw ) : (string) $raw;
    }

    /** Recursively flattens a nested array to a single string (for tab content arrays). */
    private static function flatten_array_to_string( $arr ) {
        $out = '';
        foreach ( $arr as $val ) {
            $out .= is_array( $val ) ? ' ' . self::flatten_array_to_string( $val ) : ' ' . (string) $val;
        }
        return $out;
    }

    /**
     * Extracts a weight in kg from an HTML string.
     * Handles three patterns from the AUN Specification tab HTML:
     *   1. <strong>0.9–1.35kg</strong>          (user's exact format)
     *   2. fa-weight-hanging icon followed by value
     *   3. "Weight" label near a kg value (table rows, plain text)
     */
    private static function extract_weight_from_html( $html ) {
        if ( empty( $html ) ) return 0;

        // Pattern 1 — <strong> tag containing a weight value (user's exact HTML structure)
        if ( preg_match( '/<strong[^>]*>([\d.,]+(?:\s*[–\-]\s*[\d.,]+)?\s*k[gG])<\/strong>/u', $html, $m ) ) {
            return self::parse_weight_range( $m[1] );
        }
        // Pattern 2 — Near the fa-weight-hanging icon
        if ( preg_match( '/fa-weight-hanging.{1,400}?([\d.,]+(?:\s*[–\-]\s*[\d.,]+)?\s*k[gG])/is', $html, $m ) ) {
            return self::parse_weight_range( $m[1] );
        }
        // Pattern 3 — "Weight" label with nearby kg value (covers table-based spec layouts)
        if ( preg_match( '/Weight.{0,200}?([\d.,]+(?:\s*[–\-]\s*[\d.,]+)?\s*k[gG])/is', $html, $m ) ) {
            return self::parse_weight_range( $m[1] );
        }
        return 0;
    }

    /**
     * Parses a weight string that may be a range, e.g. "0.9–1.35kg".
     * Returns the HIGHER value — conservative for portability scoring
     * (the product is as heavy as its heaviest configuration).
     */
    private static function parse_weight_range( $raw ) {
        $raw = strip_tags( $raw );
        $raw = preg_replace( '/\s*k[gG]$/i', '', trim( $raw ) );
        // Range: "0.9–1.35" or "0.9-1.35"
        if ( preg_match( '/([\d.,]+)\s*[–\-]\s*([\d.,]+)/', $raw, $m ) ) {
            $w = max( (float) str_replace( ',', '.', $m[1] ), (float) str_replace( ',', '.', $m[2] ) );
            return ( $w > 0 && $w < 50 ) ? $w : 0;
        }
        // Single value
        if ( preg_match( '/([\d.,]+)/', $raw, $m ) ) {
            $w = (float) str_replace( ',', '.', $m[1] );
            return ( $w > 0 && $w < 50 ) ? $w : 0;
        }
        return 0;
    }

    /** Converts raw algorithmic score to a 35–99 user-facing match percentage */
    /** Public since 3.5.0: the app's finder renders the same figure. */
    public static function score_to_pct( $score ) {
        if ( $score < 0 )   return max( 35, 45 + intval( $score / 20 ) );
        if ( $score < 60 )  return intval( 50 + ( $score / 60 ) * 24 );
        if ( $score < 120 ) return intval( 74 + ( ( $score - 60  ) / 60 ) * 15 );
        if ( $score < 200 ) return intval( 89 + ( ( $score - 120 ) / 80 ) * 8 );
        return min( 99, intval( 97 + ( ( $score - 200 ) / 100 ) * 2 ) );
    }

    /**
     * Generates a natural-language summary sentence for the result card.
     * This is what makes the output feel AI-generated rather than templated.
     */
    /** Public since 3.5.0 — see recommend(). */
    public static function generate_ai_summary( $usage, $lighting, $size, $throw, $ansi, $has_4k, $is_short_throw, $is_android_tv, $ram, $weight, $port_wt ) {
        $u_map = [ 'coaching' => 'classroom teaching', 'cinema' => 'home cinema', 'office' => 'office presentations', 'portable' => 'portable outdoor use' ];
        $l_map = [ 'dark' => 'dark rooms', 'dim' => 'dimly lit spaces', 'bright' => 'bright daylight' ];
        $s_map = [ 'small' => 'smaller screens under 100"', 'medium' => '100–150" screens', 'large' => 'large screens over 150"' ];

        $u = $u_map[ $usage ]    ?? $usage;
        $l = $l_map[ $lighting ] ?? $lighting;
        $s = $s_map[ $size ]     ?? $size;

        $sentence = "Your best match for {$u} in {$l}, optimised for {$s}.";

        $hi = [];
        // Qualitative only — numeric brightness and the term "ANSI" are internal (see v3.4.0 note)
        if ( $ansi >= 800 )     $hi[] = 'a bright, vivid picture';
        if ( $is_short_throw )  $hi[] = 'short-throw lens';
        // 4K is only a meaningful highlight for large screens where it adds visible value
        if ( $has_4k && $size === 'large' ) $hi[] = '4K support for large-screen projection';
        if ( $is_android_tv )   $hi[] = 'Genuine Android TV OS';
        if ( $ram >= 3 )        $hi[] = '3 GB RAM';
        if ( $weight > 0 && $weight <= $port_wt && $usage === 'portable' ) $hi[] = "ultra-light {$weight} kg body";

        if ( count( $hi ) >= 2 ) {
            $sentence .= ' Key strengths: ' . implode( ' and ', array_slice( $hi, 0, 2 ) ) . '.';
        } elseif ( count( $hi ) === 1 ) {
            $sentence .= " Highlighted by its {$hi[0]}.";
        }

        return $sentence;
    }

    /**
     * Builds a personalised list of up to 5 pass/warn reasons shown on the result card.
     * Each reason tells the customer exactly WHY this projector suits their answers.
     */
    /** Public since 3.5.0 — see recommend(). */
    public static function generate_reasons( $usage, $lighting, $size, $throw, $ansi, $has_4k, $is_short_throw, $is_android_tv, $ram, $weight, $port_wt, $ansi_dim, $ansi_bright, $price, $budget, $bgt_entry, $bgt_mid ) {
        $r = [];

        // 1 — Budget (three separate cases so the caption is always accurate)
        if ( $budget === 'budget' ) {
            if ( $price <= $bgt_entry ) {
                $r[] = [ 'pass', 'fa-wallet', 'Fits your entry-level budget' ];
            } else {
                $r[] = [ 'warn', 'fa-wallet', 'Over your entry-level budget' ];
            }
        } elseif ( $budget === 'mid' ) {
            if ( $price <= $bgt_entry ) {
                $r[] = [ 'pass', 'fa-wallet', 'Great value — well under your budget' ];
            } elseif ( $price <= $bgt_mid ) {
                $r[] = [ 'pass', 'fa-wallet', 'Fits your mid-range budget' ];
            } else {
                $r[] = [ 'warn', 'fa-wallet', 'Slightly over your mid-range budget' ];
            }
        } elseif ( $budget === 'premium' ) {
            if ( $price > $bgt_mid ) {
                $r[] = [ 'pass', 'fa-wallet', 'In your premium budget range' ];
            } else {
                $r[] = [ 'pass', 'fa-wallet', 'Well within your premium budget' ];
            }
        }

        // 2 — Lighting physics
        if ( $ansi > 0 ) {
            if ( $lighting === 'bright' && $ansi >= $ansi_bright )
                $r[] = [ 'pass', 'fa-sun',       'Bright enough for daylight rooms' ];
            elseif ( $lighting === 'dim' && $ansi >= $ansi_dim )
                $r[] = [ 'pass', 'fa-lightbulb', 'Handles ambient light well' ];
            elseif ( $lighting === 'dark' )
                $r[] = [ 'pass', 'fa-moon',      'Sharp & vivid in dark rooms' ];
        }

        // 3 — Throw distance
        if ( $throw === 'close' ) {
            if ( $is_short_throw )
                $r[] = [ 'pass', 'fa-compress', 'Short-throw lens — perfect for close placement' ];
            else
                $r[] = [ 'warn', 'fa-compress', 'Standard throw — ideally needs 2 m+ distance' ];
        } elseif ( $throw === 'faraway' ) {
            if ( ! $is_short_throw && $ansi >= $ansi_bright )
                $r[] = [ 'pass', 'fa-warehouse', 'High brightness suited for large rooms' ];
            elseif ( $is_short_throw )
                $r[] = [ 'warn', 'fa-warehouse', 'Short-throw lens gives a smaller image at distance' ];
        }

        // 4 — Portability
        if ( $usage === 'portable' ) {
            if ( $weight > 0 && $weight <= $port_wt )
                $r[] = [ 'pass', 'fa-feather',        "Lightweight at {$weight} kg — easy to carry" ];
            elseif ( $weight > $port_wt )
                $r[] = [ 'warn', 'fa-weight-hanging', "Heavier than ideal for portable use ({$weight} kg)" ];
        }

        // 5 — 4K (screen-size contextual — only highlighted where it genuinely matters)
        if ( $has_4k ) {
            if ( $size === 'large' )
                $r[] = [ 'pass', 'fa-film', '4K support — valuable for your large screen' ];
            else
                $r[] = [ 'pass', 'fa-film', '4K support included' ];
        }

        // 6 — Android TV (only shown for Genuine Android TV models — it is a differentiator)
        if ( $is_android_tv )
            $r[] = [ 'pass', 'fa-tv', 'Genuine Android TV — full Google app ecosystem' ];

        // 7 — RAM quality
        if ( $ram >= 3 )
            $r[] = [ 'pass', 'fa-memory', '3 GB RAM — smooth Android & multitasking' ];
        elseif ( $ram >= 2 && ( $budget === 'mid' || $budget === 'premium' ) )
            $r[] = [ 'pass', 'fa-memory', '2 GB RAM — solid performance' ];

        return array_slice( $r, 0, 5 );
    }

    /**
     * Catalog-empty fallback ONLY. The matcher now always recommends the closest
     * available product (see the sort + the "we stretched" note), so this panel appears
     * only when the live catalog has no eligible projector at all. It contains NO
     * hardcoded model names or prices, so discontinuing any model can never make it stale.
     */
    private static function get_no_results_html( $usage = '', $lighting = '', $size = '', $throw = '', $budget = '', $ansi_bright = 0, $ansi_dim = 0 ) {
        $shop = ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 )
            ? get_permalink( wc_get_page_id( 'shop' ) )
            : home_url( '/projector-price/' );

        return '
        <div style="text-align:center;padding:40px 20px;">
            <i class="fa-solid fa-box-open" style="font-size:48px;color:#cbd5e1;margin-bottom:16px;display:block;"></i>
            <h4 style="color:#0f172a;margin:0 0 8px;font-size:18px;">No matching projector available right now</h4>
            <p style="color:#64748b;font-size:15px;margin:0 auto 18px;max-width:420px;">Try adjusting your answers, or browse our full range and our team will help you pick the right model.</p>
            <a href="' . esc_url( $shop ) . '" style="display:inline-block;background:#0188fe;color:#fff;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;margin:4px;">Browse All Projectors</a>
            <a href="' . esc_url( home_url( '/contact-us/' ) ) . '" style="display:inline-block;background:#f1f5f9;color:#0f172a;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;margin:4px;">Contact Us</a>
        </div>';
    }

    // ═══════════════════════════════════════════════════════════
    //  FRONTEND WIZARD SHORTCODE
    // ═══════════════════════════════════════════════════════════
    public static function render_wizard() {
        $nonce    = wp_create_nonce( self::NONCE_KEY );
        $ajax_url = admin_url( 'admin-ajax.php' );
        ob_start();
        ?>
        <div class="aun-wizard-wrapper" id="aun-wizard-app">

            <!-- ── HEADER ── -->
            <div class="aun-wizard-header">
                <div class="aun-wiz-brand">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>AUN Smart Projector Finder</span>
                </div>
                <div class="aun-progress-track">
                    <div class="aun-progress-fill" id="aun-progress" style="width:5%;"></div>
                </div>
                <p class="aun-step-counter" id="aun-step-text">Step 1 of 5</p>
            </div>

            <!-- ── STEP 1 : USAGE ── -->
            <div class="aun-wizard-step active" data-step="1">
                <div class="aun-step-icon"><i class="fa-solid fa-compass"></i></div>
                <h3>Where will you use the projector?</h3>
                <p class="aun-step-desc">Your primary use case shapes every recommendation we make.</p>
                <div class="aun-grid-options">
                    <div class="aun-card" data-val="coaching" onclick="aunSelectCard(this,1)">
                        <i class="fa-solid fa-chalkboard-user"></i><h4>Coaching / Class</h4><span>Clear text for large audiences</span>
                    </div>
                    <div class="aun-card" data-val="cinema" onclick="aunSelectCard(this,1)">
                        <i class="fa-solid fa-film"></i><h4>Home Cinema</h4><span>Movies, sports &amp; streaming</span>
                    </div>
                    <div class="aun-card" data-val="office" onclick="aunSelectCard(this,1)">
                        <i class="fa-solid fa-briefcase"></i><h4>Office / Meeting</h4><span>Presentations &amp; spreadsheets</span>
                    </div>
                    <div class="aun-card" data-val="portable" onclick="aunSelectCard(this,1)">
                        <i class="fa-solid fa-campground"></i><h4>Portable / Outdoor</h4><span>Lightweight, on the go</span>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2 : LIGHTING ── -->
            <div class="aun-wizard-step" data-step="2">
                <div class="aun-step-icon"><i class="fa-solid fa-lightbulb"></i></div>
                <h3>What is the lighting like in your room?</h3>
                <p class="aun-step-desc">The single most critical factor for picking the right brightness.</p>
                <div class="aun-grid-options aun-grid-3">
                    <div class="aun-card" data-val="dark" onclick="aunSelectCard(this,2)">
                        <i class="fa-solid fa-moon"></i><h4>Pitch Black</h4><span>Lights off, curtains closed</span>
                    </div>
                    <div class="aun-card" data-val="dim" onclick="aunSelectCard(this,2)">
                        <i class="fa-solid fa-lightbulb"></i><h4>Dimly Lit</h4><span>Some ambient light</span>
                    </div>
                    <div class="aun-card" data-val="bright" onclick="aunSelectCard(this,2)">
                        <i class="fa-solid fa-sun"></i><h4>Bright Daylight</h4><span>Windows open, lights on</span>
                    </div>
                </div>
            </div>

            <!-- ── STEP 3 : SCREEN SIZE ── -->
            <div class="aun-wizard-step" data-step="3">
                <div class="aun-step-icon"><i class="fa-solid fa-expand"></i></div>
                <h3>How big do you want the screen?</h3>
                <p class="aun-step-desc">Bigger screens demand more brightness to stay vivid and sharp.</p>
                <div class="aun-grid-options aun-grid-3">
                    <div class="aun-card" data-val="small" onclick="aunSelectCard(this,3)">
                        <i class="fa-solid fa-tv"></i><h4>Under 100"</h4><span>Standard screen or wall</span>
                    </div>
                    <div class="aun-card" data-val="medium" onclick="aunSelectCard(this,3)">
                        <i class="fa-solid fa-expand"></i><h4>100" – 150"</h4><span>Large projection screen</span>
                    </div>
                    <div class="aun-card" data-val="large" onclick="aunSelectCard(this,3)">
                        <i class="fa-solid fa-panorama"></i><h4>Over 150"</h4><span>Hall or massive wall</span>
                    </div>
                </div>
            </div>

            <!-- ── STEP 4 : THROW DISTANCE (NEW) ── -->
            <div class="aun-wizard-step" data-step="4">
                <div class="aun-step-icon"><i class="fa-solid fa-ruler-horizontal"></i></div>
                <h3>Where will the projector sit in the room?</h3>
                <p class="aun-step-desc">This determines the throw distance and which lens type suits you.</p>
                <div class="aun-grid-options aun-grid-3">
                    <div class="aun-card" data-val="close" onclick="aunSelectCard(this,4)">
                        <i class="fa-solid fa-compress"></i><h4>Very Close</h4><span>Under 1.5 m from screen</span>
                    </div>
                    <div class="aun-card" data-val="normal" onclick="aunSelectCard(this,4)">
                        <i class="fa-solid fa-house"></i><h4>Normal Room</h4><span>1.5 m – 4 m away</span>
                    </div>
                    <div class="aun-card" data-val="faraway" onclick="aunSelectCard(this,4)">
                        <i class="fa-solid fa-warehouse"></i><h4>Large Room / Hall</h4><span>4 m+ from the screen</span>
                    </div>
                </div>
            </div>

            <!-- ── STEP 5 : BUDGET ── -->
            <div class="aun-wizard-step" data-step="5">
                <div class="aun-step-icon"><i class="fa-solid fa-wallet"></i></div>
                <h3>What is your estimated budget?</h3>
                <p class="aun-step-desc">We find the best-value projector within your price range.</p>
                <div class="aun-grid-options aun-grid-3">
                    <div class="aun-card" data-val="budget" onclick="aunSelectCard(this,5)">
                        <i class="fa-solid fa-wallet"></i><h4>Entry Level</h4><span>Affordable &amp; reliable</span>
                    </div>
                    <div class="aun-card" data-val="mid" onclick="aunSelectCard(this,5)">
                        <i class="fa-solid fa-money-bill-wave"></i><h4>Mid-Range</h4><span>Best balance of features</span>
                    </div>
                    <div class="aun-card" data-val="premium" onclick="aunSelectCard(this,5)">
                        <i class="fa-solid fa-gem"></i><h4>Premium</h4><span>Top-tier performance</span>
                    </div>
                </div>
            </div>

            <!-- ── LOADING ── -->
            <div class="aun-wizard-step" data-step="loading">
                <div class="aun-loading-box">
                    <div class="aun-loading-ring">
                        <i class="fa-solid fa-microchip" id="aun-loading-icon"></i>
                    </div>
                    <h3 id="aun-loading-title">Analysing your requirements</h3>
                    <div class="aun-loading-track">
                        <div class="aun-loading-bar" id="aun-loading-bar" style="width:5%;"></div>
                    </div>
                    <p class="aun-loading-detail" id="aun-loading-detail">Initialising smart matching engine...</p>
                </div>
            </div>

            <!-- ── RESULTS ── -->
            <div class="aun-wizard-step" data-step="result">
                <div class="aun-result-header">
                    <div class="aun-result-header-icon"><i class="fa-solid fa-bullseye"></i></div>
                    <div>
                        <h3>Your Perfect Matches</h3>
                        <p>Based on your 5 answers — here are our top picks.</p>
                    </div>
                </div>
                <div id="aun-recommendation-content"></div>
                <div style="text-align:center;margin-top:12px;">
                    <button class="aun-btn-reset" onclick="aunResetWizard()">
                        <i class="fa-solid fa-rotate-right"></i> Start Over
                    </button>
                </div>
            </div>

            <!-- ── NAVIGATION ── -->
            <div class="aun-wizard-footer" id="aun-nav-buttons">
                <button class="aun-btn-outline" id="aun-prev-btn" onclick="aunChangeStep(-1)" style="display:none;">
                    <i class="fa-solid fa-arrow-left"></i> <span class="aun-back-label">Back</span>
                </button>
                <button class="aun-btn-primary" id="aun-next-btn" onclick="aunChangeStep(1)" disabled>
                    Next Step <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

        </div><!-- /.aun-wizard-wrapper -->

        <!-- ══════════════ CSS ══════════════ -->
        <style id="aun-wizard-css" data-no-optimize="1" data-no-minify="1">
        /* WRAPPER */
        .aun-wizard-wrapper{max-width:800px;margin:40px auto;background:#fff;border-radius:20px;padding:32px;box-shadow:0 12px 40px rgba(0,0,0,.08);border:1px solid #e9eef5;font-family:inherit}

        /* HEADER */
        .aun-wizard-header{margin-bottom:26px}
        .aun-wiz-brand{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:13px;font-weight:700;color:#0188fe;text-transform:uppercase;letter-spacing:.5px}
        .aun-wiz-brand i{font-size:15px}
        .aun-progress-track{width:100%;height:8px;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-bottom:8px}
        .aun-progress-fill{height:100%;background:linear-gradient(90deg,#0188fe,#06b6d4);border-radius:10px;transition:width .5s cubic-bezier(.4,0,.2,1)}
        .aun-step-counter{font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin:0;text-align:right}

        /* STEPS */
        .aun-wizard-step{display:none}
        .aun-wizard-step.active{display:block;animation:aunFadeUp .4s ease both}
        .aun-step-icon{width:56px;height:56px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
        .aun-step-icon i{font-size:24px;color:#0188fe}
        .aun-wizard-step > h3{font-size:22px;color:#0f172a;margin:0 0 8px;font-weight:700;text-align:center}
        .aun-step-desc{color:#64748b;font-size:14px;text-align:center;margin:0 0 24px}

        /* OPTION CARDS */
        .aun-grid-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px}
        .aun-grid-2{grid-template-columns:repeat(2,1fr);max-width:480px;margin:0 auto}
        .aun-grid-3{grid-template-columns:repeat(3,1fr)}
        .aun-card{background:#fff;border:2px solid #e2e8f0;border-radius:14px;padding:20px 14px;text-align:center;cursor:pointer;transition:all .2s ease;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:135px;position:relative}
        .aun-card:hover{border-color:#93c5fd;background:#f0f9ff;transform:translateY(-3px);box-shadow:0 6px 16px rgba(1,136,254,.1)}
        .aun-card.selected{border-color:#0188fe;background:#eff6ff;box-shadow:0 4px 14px rgba(1,136,254,.2)}
        .aun-card > i{font-size:28px;color:#cbd5e1;margin-bottom:10px;transition:color .2s,transform .2s}
        .aun-card:hover > i,.aun-card.selected > i{color:#0188fe;transform:scale(1.1)}
        .aun-card > h4{margin:0 0 4px;font-size:14px;color:#1e293b;font-weight:700}
        .aun-card > span{font-size:12px;color:#94a3b8;line-height:1.3}

        /* LOADING */
        .aun-loading-box{text-align:center;padding:50px 20px}
        .aun-loading-ring{width:76px;height:76px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px}
        .aun-loading-ring i{font-size:34px;color:#0188fe;animation:aunSpin 1.2s linear infinite}
        .aun-loading-box h3{font-size:19px;font-weight:700;color:#0f172a;margin:0 0 16px;transition:opacity .25s}
        .aun-loading-track{height:6px;background:#f1f5f9;border-radius:10px;overflow:hidden;max-width:300px;margin:0 auto 12px}
        .aun-loading-bar{height:100%;background:linear-gradient(90deg,#0188fe,#06b6d4);border-radius:10px;transition:width .7s ease}
        .aun-loading-detail{font-size:13px;color:#94a3b8;margin:0;transition:opacity .25s;min-height:20px}

        /* RESULT HEADER */
        .aun-result-header{display:flex;align-items:center;gap:14px;margin-bottom:22px;padding:16px 20px;background:linear-gradient(135deg,#eff6ff,#f0fdfa);border:1px solid #bae6fd;border-radius:14px}
        .aun-result-header-icon{width:48px;height:48px;background:#dbeafe;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .aun-result-header-icon i{font-size:22px;color:#0188fe}
        .aun-result-header h3{margin:0 0 2px;font-size:20px;font-weight:700;color:#0f172a;text-align:left}
        .aun-result-header p{margin:0;font-size:13px;color:#64748b}

        /* RESULT MATCH CARDS */
        .aun-match-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:18px;box-shadow:0 4px 18px rgba(0,0,0,.06);opacity:0;animation:aunCardIn .5s ease forwards}
        .aun-card-best{border-left:4px solid #f59e0b !important}
        .aun-card-alt{border-left:4px solid #94a3b8 !important}
        .aun-card-topbar{display:flex;justify-content:space-between;align-items:center;padding:10px 18px;background:#f8fafc;border-bottom:1px solid #e9eef5;flex-wrap:wrap;gap:6px}
        .aun-badge-best{background:#fef9c3;color:#92400e;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
        /* Alt badge uses blue — feels like a solid recommendation, not a grey fallback */
        .aun-badge-alt{background:#eff6ff;color:#1d4ed8;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
        .aun-match-pct{font-size:14px;font-weight:800;padding:4px 14px;border-radius:20px}
        .aun-pct-best{background:#dcfce7;color:#166534}
        .aun-pct-alt{background:#dbeafe;color:#1e40af}
        .aun-card-body{display:flex;gap:18px;padding:18px;align-items:flex-start}
        .aun-match-img-wrap{flex:0 0 120px;height:120px;background:#f8fafc;border-radius:10px;border:1px solid #e9eef5;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .aun-match-img-wrap img{max-width:100%;max-height:100%;object-fit:contain}
        .aun-match-info{flex:1;min-width:0}
        .aun-match-title{margin:0 0 8px;font-size:16px;font-weight:700;color:#0f172a;line-height:1.3}
        .aun-ai-summary{font-size:13px;color:#334155;margin:0 0 12px;line-height:1.55;background:#f0f9ff;padding:9px 11px;border-radius:8px;border-left:3px solid #0188fe}
        .aun-ai-summary i{color:#0188fe;margin-right:4px}
        .aun-reasons-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px 12px;margin-bottom:12px}
        .aun-reason-item{font-size:12px;font-weight:600;display:flex;align-items:flex-start;gap:5px;line-height:1.35}
        .aun-reason-pass{color:#15803d}
        .aun-reason-warn{color:#c2410c}
        .aun-chip-row{display:flex;flex-wrap:wrap;gap:5px}
        .aun-chip{background:#f1f5f9;color:#475569;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px}
        .aun-chip i{color:#94a3b8;font-size:10px}
        /* Android TV chip — mint green so it reads as "certified OS", not confused with the gold best-match badge */
        .aun-chip-tv{background:#d1fae5 !important;color:#065f46 !important;border:1px solid #a7f3d0 !important}
        .aun-chip-tv i{color:#059669 !important}
        /* Backorder chip — amber, signals "available to order" without alarming the customer */
        .aun-chip-backorder{background:#fff7ed !important;color:#9a3412 !important;border:1px solid #fed7aa !important}
        .aun-chip-backorder i{color:#ea580c !important}
        .aun-card-footer{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#f8fafc;border-top:1px solid #e9eef5;flex-wrap:wrap;gap:10px}
        .aun-match-price{font-size:22px;font-weight:800;color:#0188fe}
        .aun-match-btn{background:#0188fe;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:700;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap;transition:background .2s,transform .15s}
        .aun-match-btn:hover{background:#0070d6;transform:translateY(-1px);color:#fff}
        .aun-physics-warn{background:#fffbeb;color:#b45309;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;border:1px solid #fde68a;line-height:1.5;display:flex;gap:8px;align-items:flex-start}

        /* NAVIGATION */
        .aun-wizard-footer{display:flex;justify-content:space-between;gap:10px;margin-top:28px;padding-top:18px;border-top:1px solid #e9eef5}
        .aun-btn-primary{background:#0188fe;color:#fff;border:none;padding:13px 26px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;justify-content:center;gap:8px;margin-left:auto;white-space:nowrap}
        .aun-btn-primary:hover:not(:disabled){background:#0070d6;transform:translateY(-1px)}
        .aun-btn-primary:disabled{background:#cbd5e1;cursor:not-allowed}
        .aun-btn-outline{background:transparent;color:#64748b;border:1.5px solid #cbd5e1;padding:13px 20px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap}
        .aun-btn-outline:hover{background:#f8fafc;color:#334155;border-color:#94a3b8}
        .aun-btn-reset{background:#f1f5f9;color:#475569;border:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:background .2s}
        .aun-btn-reset:hover{background:#e2e8f0}

        /* ANIMATIONS */
        @keyframes aunFadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
        @keyframes aunCardIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        @keyframes aunSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}

        /* RESPONSIVE */
        @media(max-width:640px){
            .aun-wizard-wrapper{padding:16px 13px;border-radius:14px;margin:14px 6px}
            .aun-grid-options,.aun-grid-3{grid-template-columns:1fr 1fr;gap:10px}
            .aun-card{padding:14px 10px;min-height:108px}
            .aun-card > i{font-size:22px}
            .aun-card > h4{font-size:13px}
            .aun-card-body{flex-direction:column;align-items:center}
            .aun-match-img-wrap{flex:0 0 auto;width:100px;height:100px}
            .aun-match-info{width:100%}
            .aun-reasons-grid{grid-template-columns:1fr}
            .aun-card-footer{flex-direction:column;align-items:stretch}
            .aun-match-btn{justify-content:center}
            .aun-result-header h3{font-size:17px}
            /* footer: keep BOTH buttons on one line. Back collapses to a compact arrow-only
               button so the primary always has room for its full label without wrapping. */
            .aun-wizard-footer{gap:8px;margin-top:22px;flex-wrap:nowrap}
            .aun-btn-primary{flex:1 1 auto;margin-left:0;padding:12px 12px;font-size:14px}
            .aun-btn-outline{flex:0 0 auto;padding:12px 15px;font-size:14px;gap:0}
            .aun-btn-outline .aun-back-label{display:none}
        }
        /* very small phones — give the wizard nearly the full screen width */
        @media(max-width:380px){
            .aun-wizard-wrapper{padding:14px 11px;margin:12px 4px;border-radius:12px}
            .aun-wizard-step > h3{font-size:19px}
            .aun-btn-primary{padding:11px 13px;font-size:13px;gap:6px}
            .aun-btn-outline{padding:11px 12px;font-size:13px;gap:5px}
            .aun-card > span{font-size:11px}
        }
        </style>

        <!-- ══════════════ JAVASCRIPT ══════════════ -->
        <script id="aun-wizard-js" data-no-optimize="1" data-no-minify="1" data-no-defer="1" data-cfasync="false">
        (function () {
            let aunStep  = 1;
            const TOTAL  = 5;
            const ans    = { usage: '', lighting: '', size: '', throw: '', budget: '' };
            const KEYS   = { 1: 'usage', 2: 'lighting', 3: 'size', 4: 'throw', 5: 'budget' };
            let loadTimer = null, loadStart = 0;

            /* ── SELECT A CARD ── */
            window.aunSelectCard = function (el, s) {
                document.querySelectorAll('.aun-wizard-step[data-step="' + s + '"] .aun-card').forEach(function (c) {
                    c.classList.remove('selected');
                });
                el.classList.add('selected');
                ans[KEYS[s]] = el.getAttribute('data-val');
                document.getElementById('aun-next-btn').disabled = false;
                setTimeout(function () { window.aunChangeStep(1); }, 350);
            };

            /* ── NAVIGATE STEPS ── */
            window.aunChangeStep = function (d) {
                if (d === 1 && document.getElementById('aun-next-btn').disabled) return;
                var t = aunStep + d;
                if (t > TOTAL) { aunSubmit(); return; }
                document.querySelector('.aun-wizard-step[data-step="' + aunStep + '"]').classList.remove('active');
                document.querySelector('.aun-wizard-step[data-step="' + t + '"]').classList.add('active');
                aunStep = t;
                aunUpdateUI();
            };

            /* ── UPDATE BUTTON / PROGRESS STATE ── */
            function aunUpdateUI() {
                var pct = Math.max(5, ((aunStep - 1) / TOTAL) * 100);
                document.getElementById('aun-progress').style.width = pct + '%';
                document.getElementById('aun-step-text').innerText = 'Step ' + aunStep + ' of ' + TOTAL;
                var has = !!ans[KEYS[aunStep]];
                var nb  = document.getElementById('aun-next-btn');
                nb.disabled = !has;
                nb.innerHTML = aunStep === TOTAL
                    ? 'Find My Projector <i class="fa-solid fa-wand-magic-sparkles"></i>'
                    : 'Next Step <i class="fa-solid fa-arrow-right"></i>';
                document.getElementById('aun-prev-btn').style.display = aunStep > 1 ? 'flex' : 'none';
            }

            /* ── CONTEXTUAL LOADING MESSAGES (references the user's actual answers) ── */
            function getLoadingSteps() {
                var uM = { coaching: 'classroom use', cinema: 'home cinema', office: 'office work', portable: 'outdoor use' };
                var lM = { dark: 'pitch black room', dim: 'ambient lighting', bright: 'bright daylight' };
                var tM = { close: 'close placement (under 1.5 m)', normal: 'normal room (1.5–4 m)', faraway: 'large room / hall (4 m+)' };
                return [
                    { t: 'Analysing your requirements',        d: 'Noted: ' + (uM[ans.usage] || 'your use case') + ' in ' + (lM[ans.lighting] || 'your lighting') },
                    { t: 'Running brightness physics',         d: 'Calculating the brightness needed for ' + (lM[ans.lighting] || 'your room') + '...' },
                    { t: 'Checking throw compatibility',       d: 'Matching lens type for ' + (tM[ans.throw] || 'your room setup') + '...' },
                    { t: 'Scanning our AUN lineup',            d: 'Scoring all available models against your requirements...' },
                    { t: 'Preparing your results',             d: 'Building personalised match summary...' }
                ];
            }

            function startLoadingAnimation() {
                var steps = getLoadingSteps();
                var i     = 0;
                var tEl   = document.getElementById('aun-loading-title');
                var dEl   = document.getElementById('aun-loading-detail');
                var bEl   = document.getElementById('aun-loading-bar');

                function showStep(idx) {
                    tEl.style.opacity = dEl.style.opacity = '0';
                    setTimeout(function () {
                        tEl.textContent = steps[idx].t;
                        dEl.textContent = steps[idx].d;
                        tEl.style.opacity = dEl.style.opacity = '1';
                        bEl.style.width   = ((idx + 1) / steps.length * 88) + '%';
                    }, 200);
                }

                showStep(i);
                loadTimer = setInterval(function () {
                    i++;
                    if (i < steps.length) showStep(i);
                    else clearInterval(loadTimer);
                }, 560);
            }

            function aunShowResults(resp) {
                clearInterval(loadTimer);
                document.getElementById('aun-loading-bar').style.width = '100%';
                setTimeout(function () {
                    document.querySelector('[data-step="loading"]').classList.remove('active');
                    document.querySelector('[data-step="result"]').classList.add('active');
                    document.getElementById('aun-recommendation-content').innerHTML = resp;
                    document.getElementById('aun-step-text').innerText = 'Complete!';
                }, 350);
            }

            /* ── SUBMIT ── */
            function aunSubmit() {
                document.querySelector('.aun-wizard-step[data-step="' + aunStep + '"]').classList.remove('active');
                document.getElementById('aun-nav-buttons').style.display = 'none';
                document.querySelector('[data-step="loading"]').classList.add('active');
                document.getElementById('aun-progress').style.width = '100%';
                document.getElementById('aun-step-text').innerText   = 'Analysing...';
                loadStart = Date.now();
                startLoadingAnimation();

                jQuery.ajax({
                    url:  '<?php echo esc_url( $ajax_url ); ?>',
                    type: 'POST',
                    data: {
                        action:  'aun_get_projector_recommendation',
                        nonce:   '<?php echo $nonce; ?>',
                        answers: ans
                    },
                    success: function (r) {
                        var elapsed   = Date.now() - loadStart;
                        var remaining = Math.max(0, 2800 - elapsed); // minimum 2.8 s of "AI thinking"
                        setTimeout(function () { aunShowResults(r); }, remaining);
                    },
                    error: function () {
                        aunShowResults('<p style="text-align:center;color:#ef4444;padding:30px;">Something went wrong. Please try again.</p>');
                    }
                });
            }

            /* ── RESET ── */
            window.aunResetWizard = function () {
                aunStep = 1;
                Object.keys(ans).forEach(function (k) { ans[k] = ''; });
                document.querySelectorAll('.aun-card').forEach(function (c) { c.classList.remove('selected'); });
                document.querySelector('[data-step="result"]').classList.remove('active');
                document.querySelector('[data-step="1"]').classList.add('active');
                document.getElementById('aun-nav-buttons').style.display = 'flex';
                aunUpdateUI();
            };
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════
    //  AJAX — SMART SCORING ENGINE V3.0
    // ═══════════════════════════════════════════════════════════
    /**
     * Score the catalogue against one set of answers and return the top matches
     * as DATA — no markup, no echo, no wp_die().
     *
     * Split out of process_recommendation() so the website and the AUN Care app
     * share ONE engine. The alternative — reimplementing this scoring in the app
     * — would have the two recommending different projectors to the same
     * customer the first time a threshold moved in wp-admin, and nobody would
     * notice for months. The tuning here (the physics bailout, the 4K/screen-size
     * rule, the tie-breaks) is the product; the HTML below is just one way to
     * show it.
     *
     * @param array $answers usage / lighting / size / throw / budget
     * @return array{ok:bool,physics_warn:bool,matches:array}
     */
    public static function recommend( array $answers ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [ 'ok' => false, 'physics_warn' => false, 'matches' => [] ];
        }

        $opts = self::get_settings();
        $a    = $answers;

        $usage   = sanitize_text_field( $a['usage']      ?? '' );
        $lighting= sanitize_text_field( $a['lighting']   ?? '' );
        $size    = sanitize_text_field( $a['size']       ?? '' );
        $throw   = sanitize_text_field( $a['throw']      ?? '' );
        $budget  = sanitize_text_field( $a['budget']     ?? '' );

        $ansi_dim    = (int)(float) $opts['ansi_dim'];
        $ansi_bright = (int)(float) $opts['ansi_bright'];
        $bgt_entry   = (float) $opts['budget_entry'];
        $bgt_mid     = (float) $opts['budget_mid'];
        $cat_main    = sanitize_text_field( $opts['cat_main']    ?? '' );
        $port_wt     = (float) $opts['portable_weight'];
        $include_backorder = ( ( $opts['include_backorder'] ?? '1' ) === '1' );

        // Stock is filtered per-product below (see STOCK GATE) so that "instock" plus
        // optionally "onbackorder" are allowed while "outofstock" is always excluded.
        // Filtering in PHP is more reliable across WooCommerce versions than passing an
        // array to the query's stock_status argument.
        $args = [ 'status' => 'publish', 'limit' => 150 ];
        if ( ! empty( $cat_main ) ) $args['category'] = [ $cat_main ];
        $products = wc_get_products( $args );

        $scored = [];

        foreach ( $products as $product ) {
            $pid    = $product->get_id();
            if ( taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $pid ) ) continue;

            // ── STOCK GATE ──────────────────────────────────────────
            // Always exclude out-of-stock. Include backorder only when enabled.
            $stock_status = $product->get_stock_status();
            if ( $stock_status === 'outofstock' ) continue;
            if ( $stock_status === 'onbackorder' && ! $include_backorder ) continue;

            $score  = 0;
            $price  = (float) $product->get_price();
            $weight = self::get_product_weight( $product ); // Net weight from spec tab, not G.W.
            $name   = $product->get_name();
            $desc   = $product->get_short_description() . ' ' . $product->get_description();
            $full   = $name . ' ' . $desc; // combined for feature detection

            // ── FEATURE EXTRACTION ──────────────────────────────────
            $ansi          = self::get_brightness_for_product( $product, $full );
            $ram           = self::get_ram( $full );
            $has_4k        = (bool) preg_match( '/\b4K\b/i', $full );
            $is_pro        = (bool) preg_match( '/\bPro\b|\bMax\b|\bPlus\b/i', $name );
            $is_smart      = (bool) preg_match( '/Android|Smart\s*TV|Wi-?Fi|Bluetooth/i', $full );

            // Genuine Android TV detection — matches "Genuine Android TV 14", "Android TV 11", etc.
            // Does NOT match plain "Android 12" or "Android 9" (regular Android)
            $is_android_tv = (bool) preg_match( '/Genuine\s+Android|Android\s*TV\s*\d{1,2}/i', $full );

            // Short-throw: check product name AND description AND WooCommerce tags
            $terms  = get_the_terms( $pid, 'product_tag' );
            $tags   = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'slug' ) : [];
            $is_short_throw = (bool)(
                preg_match( '/Short[\s-]?Throw/i', $name ) ||
                preg_match( '/Short[\s-]?Throw/i', $desc ) ||
                in_array( 'short-throw', $tags )
            );

            // Portable model detection (for A32 Pro which says "Portable" in its name)
            $is_portable_model = (bool) preg_match( '/\bPortable\b|\bMini\b/i', $name . ' ' . $desc );

            // ── PHYSICS BAILOUT ─────────────────────────────────────
            // Forgives budget penalty when the product is the ONLY one
            // with enough raw power for extreme lighting / screen requirements.
            $bailout = 0;
            if ( $lighting === 'bright' && $ansi >= $ansi_bright ) $bailout += 400;
            if ( $size     === 'large'  && $ansi >= $ansi_bright ) $bailout += 400;

            // ── A. BUDGET SCORING ───────────────────────────────────
            if ( $budget === 'budget' ) {
                if ( $price <= $bgt_entry ) {
                    $score += 30;
                    if ( $bgt_entry > 0 ) $score += intval( ( ( $bgt_entry - $price ) / $bgt_entry ) * 25 );
                } else {
                    $pen = min( 500, 150 + intval( ( ( $price - $bgt_entry ) / $bgt_entry ) * 400 ) );
                    $score -= max( 150, $pen - $bailout );
                }
            } elseif ( $budget === 'mid' ) {
                if ( $price > $bgt_entry && $price <= $bgt_mid ) {
                    $score += 40 + intval( ( $price / $bgt_mid ) * 20 );
                } elseif ( $price <= $bgt_entry ) {
                    $score += 10; // Entry product for mid user — shows but lower priority
                } else {
                    $pen = min( 500, 150 + intval( ( ( $price - $bgt_mid ) / $bgt_mid ) * 400 ) );
                    $score -= max( 150, $pen - $bailout );
                }
            } elseif ( $budget === 'premium' ) {
                $score += ( $price > $bgt_mid ) ? 40 : 10;
                // Value-for-money bonus within premium tier.
                // Rewards the most affordable premium product over the most expensive one.
                // The step is proportional to the mid-range ceiling (2.5% of it) so it scales
                // with ANY currency instead of a hardcoded BDT amount: on a BDT store
                // (bgt_mid≈23,000) the step is ≈575 — practically the old ৳500; on a USD store
                // (bgt_mid≈400) the step is ≈10, giving real separation between premium models.
                // Max +20, min 0.
                if ( $price > $bgt_mid ) {
                    $overage = $price - $bgt_mid;
                    $step    = max( 1, $bgt_mid * 0.025 ); // currency-relative; never divide by zero
                    $score  += max( 0, 20 - intval( $overage / $step ) );
                }
                // NOTE: 4K bonus is NO LONGER applied here.
                // It is handled in section F (Resolution Scoring) below, gated on the user's
                // explicit resolution preference — this is what allows AKEY9S to compete when
                // a premium user selects "Full HD 1080p".
            }

            // ── B. FEATURES & USAGE SCORING ─────────────────────────
            if ( $budget === 'mid' || $budget === 'premium' ) {
                if ( $is_pro )   $score += 10;
                if ( $ram >= 2 ) $score += 8;   // 2 GB RAM bonus
                if ( $ram >= 3 ) $score += 7;   // stacks: 3 GB total = +15
            }

            if ( $usage === 'portable' ) {
                if ( $is_portable_model ) $score += 15; // Explicit portable model bonus
                if ( $weight > 0 && $weight <= $port_wt ) {
                    $score += 30 + intval( ( ( $port_wt - $weight ) / $port_wt ) * 15 );
                } elseif ( $weight > $port_wt ) {
                    $score -= 100;
                } else {
                    $score -= 20; // Weight not set — slight uncertainty penalty
                }
            } elseif ( $usage === 'coaching' || $usage === 'office' ) {
                $score += 10;
            } elseif ( $usage === 'cinema' ) {
                $score += 10;
            }

            // ── C. THROW DISTANCE SCORING (NEW) ─────────────────────
            // This dimension identifies the A005 Short Throw series for close-room
            // setups, and routes high-lumen projectors to large hall users.
            if ( $throw === 'close' ) {
                if ( $is_short_throw ) {
                    $score += 60; // Perfect lens type for close placement
                } else {
                    $score -= 120; // Standard projector too close = tiny image
                }
                // Hard physics wall: 150"+ from under 1.5 m without short throw = impossible
                if ( $size === 'large' && ! $is_short_throw ) $score -= 1000;

            } elseif ( $throw === 'normal' ) {
                if ( ! $is_short_throw ) {
                    $score += 10; // Standard throw ideal in normal rooms
                } else {
                    $score -= 10; // Short throw works but not optimal in larger rooms
                }
            } elseif ( $throw === 'faraway' ) {
                if ( ! $is_short_throw ) {
                    $score += 15;
                    if ( $ansi >= $ansi_bright ) $score += 10; // High-lumen bonus for large halls
                } else {
                    $score -= 30; // Short throw gives a small image from far away
                }
            }

            // ── D. LIGHTING PHYSICS (strict walls preserved) ─────────
            if ( $ansi >= $ansi_bright ) $score += 25; // Raw power bonus
            if ( $ansi > 0 ) {
                if ( $lighting === 'bright' ) {
                    $score += ( $ansi >= $ansi_bright ) ? 20 : -1000; // STRICT PHYSICS WALL
                } elseif ( $lighting === 'dim' ) {
                    $score += ( $ansi >= $ansi_dim ) ? 20 : -250;
                } elseif ( $lighting === 'dark' ) {
                    $score += 10;
                    if ( $ansi >= $ansi_dim )    $score += 5;
                    if ( $ansi >= $ansi_bright ) $score += 5;
                }
                if ( in_array( $usage, [ 'coaching', 'office' ] ) && $ansi >= $ansi_dim ) $score += 10;
            } else {
                $score -= 100; // ANSI not found in description — penalise
            }

            // ── E. SIZE SCORING ──────────────────────────────────────
            if ( $size === 'small' ) {
                $score += 5;
                if ( $is_short_throw ) $score += 10; // Short throw excels for small rooms
            } elseif ( $size === 'medium' ) {
                if ( $ansi >= $ansi_dim )                       $score += 10;
                elseif ( $lighting === 'dark' && $ansi >= 200 ) $score += 5;
                else                                             $score -= 200;
            } elseif ( $size === 'large' ) {
                if ( $ansi >= $ansi_bright )                              $score += 15;
                elseif ( $lighting === 'dark' && $ansi >= $ansi_dim )     $score += 5;
                else                                                       $score -= 1000; // STRICT PHYSICS WALL

                // Extra ANSI bonus for large screens: U001 Pro's 1000 ANSI meaningfully
                // outperforms 850 ANSI when filling a 150"+ screen.
                if ( $ansi >= 1000 )         $score += 15; // U001 Pro (1000 ANSI) only
                elseif ( $ansi >= $ansi_bright ) $score += 5;  // AKEY9S, U002 (850 ANSI)
            }

            // ── F. 4K SCORING — SCREEN-SIZE PHYSICS ─────────────────
            // "4K support" in this price range means the projector can accept a 4K signal
            // (upscaling, not a native 4K panel). The visible benefit depends entirely on
            // how large the screen is — the bigger the screen, the more you gain from
            // higher resolution content.
            //
            // Verified scoring for dim + 100–150" + premium:
            //   medium screen → 4K bonus = 0 → AKEY9S(138) vs U002(139) → both appear ✓
            //   large screen  → 4K bonus = +35 → U001 Pro(188) > U002(184) >> AKEY9S ✓
            if ( $has_4k ) {
                if ( $size === 'large' )  $score += 35; // Very beneficial at 150"+ projection
                // small / medium: no bonus — 4K upscaling imperceptible vs 1080p at <150"
                //   This is the key rule that allows AKEY9S to compete for medium screens.
            }

            // ── G. STOCK PREFERENCE ─────────────────────────────────
            // Prefer immediately-available stock. A backorder item still appears when it is
            // clearly the better match — it just sits behind an equally-good in-stock one.
            if ( $stock_status === 'onbackorder' ) $score -= 15;

            // Store every in-stock, non-discontinued projector as a candidate. The sort
            // below surfaces the closest match, so the finder always recommends a real,
            // currently-available product from the live catalog instead of dead-ending on
            // a hardcoded panel.
            $scored[] = compact( 'product', 'score', 'ansi', 'price', 'weight', 'has_4k', 'is_smart', 'is_android_tv', 'is_short_throw', 'ram', 'stock_status' );
        }

        // ── ADVANCED SORT ───────────────────────────────────────────
        usort( $scored, function ( $a, $b ) use ( $budget ) {
            if ( $a['score'] !== $b['score'] ) return $b['score'] <=> $a['score'];
            if ( $a['ansi']  !== $b['ansi']  ) return $b['ansi']  <=> $a['ansi'];
            return ( $budget === 'budget' )
                ? ( $a['price'] <=> $b['price'] )  // Budget: cheaper wins ties
                : ( $b['price'] <=> $a['price'] );  // Mid/Premium: higher price = better features
        });

        $top = array_slice( $scored, 0, 2 );

        return [
            'ok'           => ! empty( $top ),
            // A negative top score means nothing actually fits and we stretched
            // to answer at all — the customer is told, on both front ends.
            'physics_warn' => ! empty( $top ) && $top[0]['score'] < 0,
            'matches'      => $top,
            'context'      => compact(
                'usage', 'lighting', 'size', 'throw', 'budget',
                'ansi_dim', 'ansi_bright', 'bgt_entry', 'bgt_mid', 'port_wt'
            ),
        ];
    }

    public static function process_recommendation() {

        // Security: verify nonce before processing anything
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::NONCE_KEY ) ) {
            wp_die();
        }
        if ( ! function_exists( 'wc_get_products' ) ) wp_die();

        $a   = isset( $_POST['answers'] ) ? (array) $_POST['answers'] : [];
        $res = self::recommend( $a );
        $c   = $res['context'] ?? [];

        $usage       = $c['usage']       ?? '';
        $lighting    = $c['lighting']    ?? '';
        $size        = $c['size']        ?? '';
        $throw       = $c['throw']       ?? '';
        $budget      = $c['budget']      ?? '';
        $ansi_dim    = $c['ansi_dim']    ?? 0;
        $ansi_bright = $c['ansi_bright'] ?? 0;
        $bgt_entry   = $c['bgt_entry']   ?? 0;
        $bgt_mid     = $c['bgt_mid']     ?? 0;
        $port_wt     = $c['port_wt']     ?? 0;
        $top         = $res['matches'];

        if ( empty( $top ) ) {
            echo self::get_no_results_html( $usage, $lighting, $size, $throw, $budget, $ansi_bright, $ansi_dim );
            wp_die();
        }

        // ── RENDER RESULT CARDS ──────────────────────────────────────
        $html = '';

        if ( ! empty( $res['physics_warn'] ) ) {
            $html .= '<div class="aun-physics-warn">
                <i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;margin-top:2px;"></i>
                <div><strong>Note:</strong> We stretched slightly to find a projector that meets your physical needs. Adjusting your budget or room setup would give a perfect fit.</div>
            </div>';
        }

        foreach ( $top as $idx => $m ) {
            $p              = $m['product'];
            $ansi           = $m['ansi'];
            $price          = $m['price'];
            $weight         = $m['weight'];
            $has_4k         = $m['has_4k'];
            $is_smart       = $m['is_smart'];
            $is_android_tv  = $m['is_android_tv'];
            $is_short_throw = $m['is_short_throw'];
            $ram            = $m['ram'];
            $score          = $m['score'];
            $pct            = self::score_to_pct( $score );
            $delay          = $idx === 0 ? '0.05s' : '0.22s';

            $img        = $p->get_image( 'woocommerce_thumbnail' );
            $title      = esc_html( $p->get_name() );
            $price_html = wp_kses_post( $p->get_price_html() );
            $link       = esc_url( $p->get_permalink() );

            // AI summary sentence (now includes Android TV awareness)
            $summary = esc_html( self::generate_ai_summary(
                $usage, $lighting, $size, $throw,
                $ansi, $has_4k, $is_short_throw, $is_android_tv, $ram, $weight, $port_wt
            ) );

            // Personalised reasons list
            $reasons = self::generate_reasons(
                $usage, $lighting, $size, $throw,
                $ansi, $has_4k, $is_short_throw, $is_android_tv, $ram, $weight, $port_wt,
                $ansi_dim, $ansi_bright,
                $price, $budget, $bgt_entry, $bgt_mid
            );

            // Spec chips — Android TV gets a distinct amber chip; regular Android gets blue.
            // Brightness chip is qualitative: numeric values / "ANSI" are internal-only (v3.4.0).
            $chips = [];
            if ( $ansi >= $ansi_bright ) $chips[] = [ 'fa-sun', 'High Brightness', '' ];
            if ( $has_4k )         $chips[] = [ 'fa-film',           '4K UHD',          '' ];
            if ( $is_short_throw ) $chips[] = [ 'fa-compress',       'Short Throw',     '' ];
            if ( $is_android_tv )  $chips[] = [ 'fa-tv',             'Android TV',      'aun-chip-tv' ];
            elseif ( $is_smart )   $chips[] = [ 'fa-wifi',           'Android Smart',   '' ];
            if ( $ram > 0 )        $chips[] = [ 'fa-memory',         $ram . 'GB RAM',   '' ];
            if ( $weight > 0 )     $chips[] = [ 'fa-scale-balanced', $weight . ' kg',   '' ];
            if ( ( $m['stock_status'] ?? '' ) === 'onbackorder' ) $chips[] = [ 'fa-clock', 'On Backorder', 'aun-chip-backorder' ];

            // Badge & percentage colour
            if ( $idx === 0 ) {
                $badge    = '<span class="aun-badge-best"><i class="fa-solid fa-star"></i> #1 Best Match</span>';
                $pct_tag  = '<span class="aun-match-pct aun-pct-best">' . $pct . '% Match</span>';
                $card_cls = 'aun-match-card aun-card-best';
            } else {
                $badge    = '<span class="aun-badge-alt"><i class="fa-solid fa-code-compare"></i> Great Alternative</span>';
                $pct_tag  = '<span class="aun-match-pct aun-pct-alt">' . $pct . '% Match</span>';
                $card_cls = 'aun-match-card aun-card-alt';
            }

            // Build reasons HTML
            $rhtml = '';
            foreach ( $reasons as $reason ) {
                list( $type, $icon, $label ) = $reason;
                $ic     = ( $type === 'pass' ) ? 'fa-circle-check' : 'fa-circle-exclamation';
                $rhtml .= '<div class="aun-reason-item aun-reason-' . $type . '">'
                        . '<i class="fa-solid ' . $ic . '"></i> '
                        . esc_html( $label )
                        . '</div>';
            }

            // Build spec chips HTML (Android TV chip uses special amber styling)
            $chtml = '';
            foreach ( $chips as $chip ) {
                $ci       = $chip[0];
                $cl       = $chip[1];
                $extra    = ! empty( $chip[2] ) ? ' ' . $chip[2] : '';
                $chtml   .= '<span class="aun-chip' . $extra . '"><i class="fa-solid ' . $ci . '"></i> ' . esc_html( $cl ) . '</span>';
            }

            $html .= '
            <div class="' . $card_cls . '" style="animation-delay:' . $delay . ';">
                <div class="aun-card-topbar">' . $badge . $pct_tag . '</div>
                <div class="aun-card-body">
                    <div class="aun-match-img-wrap">' . $img . '</div>
                    <div class="aun-match-info">
                        <h4 class="aun-match-title">' . $title . '</h4>
                        <p class="aun-ai-summary"><i class="fa-solid fa-robot"></i>' . $summary . '</p>
                        <div class="aun-reasons-grid">' . $rhtml . '</div>
                        <div class="aun-chip-row">' . $chtml . '</div>
                    </div>
                </div>
                <div class="aun-card-footer">
                    <div class="aun-match-price">' . $price_html . '</div>
                    <a href="' . $link . '" class="aun-match-btn">View Product <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>';
        }

        echo $html;
        wp_die();
    }

} // end class

AUN_Projector_Wizard::init();