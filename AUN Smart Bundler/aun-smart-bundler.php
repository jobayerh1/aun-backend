<?php
/**
 * Plugin Name: AUN Smart Bundler
 * Description: Smart accessory bundles that sync with WooCommerce Add to Cart & Buy Now.
 *              Features split discounts per accessory type, a live bundle-total bar, and savings badges.
 *              Screens are presented as a single "pick one" selector (with a View link; click the circle to clear); bags are optional add-ons.
 *              (v2.5.0 — sold-out variations are now listed and greyed out instead of hidden, so the size
 *              selector always appears and the shopper can always see which size/colour they are buying.)
 * Version:     2.5.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Smart_Bundler {

    /** Prevents the inline <style> from printing more than once per request. */
    private static bool $styles_printed = false;

    public static function init(): void {
        add_action( 'add_meta_boxes',                   [ __CLASS__, 'add_bundle_meta_box' ] );
        add_action( 'save_post_product',                [ __CLASS__, 'save_bundle_meta_box' ] );
        // Priority 36 = after the AUN Smart Delivery badge (35), before the product meta (40).
        add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'render_bundle_box' ], 36 );
        add_action( 'woocommerce_add_to_cart',          [ __CLASS__, 'add_native_bundle_items' ], 10, 6 );
        add_action( 'woocommerce_cart_calculate_fees',  [ __CLASS__, 'apply_bundle_discount' ] );
    }

    // -------------------------------------------------------------------------
    // Admin Meta Box
    // -------------------------------------------------------------------------

    public static function add_bundle_meta_box(): void {
        add_meta_box(
            'aun_bundle_options',
            '🎁 AUN Smart Bundle Settings',
            [ __CLASS__, 'render_meta_box' ],
            'product', 'side', 'default'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        $screen_ids = get_post_meta( $post->ID, '_aun_bundle_screens', true );
        if ( empty( $screen_ids ) || ! is_array( $screen_ids ) ) {
            $old = get_post_meta( $post->ID, '_aun_bundle_screen_id', true );
            $screen_ids = $old ? [ $old ] : [];
        }

        $bag_ids = get_post_meta( $post->ID, '_aun_bundle_bags', true );
        if ( empty( $bag_ids ) || ! is_array( $bag_ids ) ) {
            $old = get_post_meta( $post->ID, '_aun_bundle_bag_id', true );
            $bag_ids = $old ? [ $old ] : [];
        }

        $old_global      = get_post_meta( $post->ID, '_aun_bundle_discount', true );
        $screen_discount = get_post_meta( $post->ID, '_aun_bundle_screen_discount', true );
        $bag_discount    = get_post_meta( $post->ID, '_aun_bundle_bag_discount', true );
        if ( $screen_discount === '' ) $screen_discount = $old_global;
        if ( $bag_discount    === '' ) $bag_discount    = $old_global;

        wp_nonce_field( 'aun_bundle_save', 'aun_bundle_nonce' );
        ?>
        <div style="background:#f0f9ff;padding:10px;border-radius:5px;border:1px solid #bae6fd;margin-bottom:15px;">
            <strong>Split Discounts (%)</strong>
            <p style="margin:5px 0 0;"><small>Set different discount percentages per accessory type.</small></p>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <div style="flex:1;">
                    <label>Screen discount</label>
                    <input type="number" step="0.1" min="0" max="100"
                           name="_aun_bundle_screen_discount"
                           value="<?php echo esc_attr( $screen_discount ); ?>"
                           style="width:100%;" placeholder="e.g. 10">
                </div>
                <div style="flex:1;">
                    <label>Bag discount</label>
                    <input type="number" step="0.1" min="0" max="100"
                           name="_aun_bundle_bag_discount"
                           value="<?php echo esc_attr( $bag_discount ); ?>"
                           style="width:100%;" placeholder="e.g. 5">
                </div>
            </div>
        </div>

        <p><strong>Projector Screens</strong><br>
        <select class="wc-product-search" multiple="multiple" style="width:100%;"
                name="_aun_bundle_screens[]"
                data-placeholder="Search for screen products…"
                data-action="woocommerce_json_search_products">
            <?php foreach ( $screen_ids as $id ) :
                $p = wc_get_product( $id );
                if ( $p ) : ?>
                <option value="<?php echo esc_attr( $id ); ?>" selected="selected">
                    <?php echo wp_kses_post( $p->get_formatted_name() ); ?>
                </option>
            <?php endif; endforeach; ?>
        </select>
        <small>Select multiple screens to offer options.</small></p>

        <p><strong>Compatible Bags</strong><br>
        <select class="wc-product-search" multiple="multiple" style="width:100%;"
                name="_aun_bundle_bags[]"
                data-placeholder="Search for bag products…"
                data-action="woocommerce_json_search_products">
            <?php foreach ( $bag_ids as $id ) :
                $p = wc_get_product( $id );
                if ( $p ) : ?>
                <option value="<?php echo esc_attr( $id ); ?>" selected="selected">
                    <?php echo wp_kses_post( $p->get_formatted_name() ); ?>
                </option>
            <?php endif; endforeach; ?>
        </select>
        <small>Select multiple bags to offer options.</small></p>
        <?php
    }

    public static function save_bundle_meta_box( int $post_id ): void {
        if ( ! isset( $_POST['aun_bundle_nonce'] )
             || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aun_bundle_nonce'] ) ), 'aun_bundle_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        // Verify the current user can edit this specific post
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Clamp discounts to 0–100 and store as float
        $screen_d = isset( $_POST['_aun_bundle_screen_discount'] )
            ? max( 0.0, min( 100.0, (float) $_POST['_aun_bundle_screen_discount'] ) ) : 0.0;
        $bag_d    = isset( $_POST['_aun_bundle_bag_discount'] )
            ? max( 0.0, min( 100.0, (float) $_POST['_aun_bundle_bag_discount'] ) ) : 0.0;

        update_post_meta( $post_id, '_aun_bundle_screen_discount', $screen_d );
        update_post_meta( $post_id, '_aun_bundle_bag_discount',    $bag_d );

        $screens = isset( $_POST['_aun_bundle_screens'] ) && is_array( $_POST['_aun_bundle_screens'] )
            ? array_filter( array_map( 'absint', $_POST['_aun_bundle_screens'] ) ) : [];
        $bags    = isset( $_POST['_aun_bundle_bags'] ) && is_array( $_POST['_aun_bundle_bags'] )
            ? array_filter( array_map( 'absint', $_POST['_aun_bundle_bags'] ) ) : [];

        update_post_meta( $post_id, '_aun_bundle_screens', array_values( $screens ) );
        update_post_meta( $post_id, '_aun_bundle_bags',    array_values( $bags ) );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the WC_Product object if the product exists, is in stock,
     * and is not discontinued. Returns false otherwise.
     *
     * @return WC_Product|false  (no union return type so the plugin also parses on PHP 7.4)
     */
    private static function is_product_available( int $product_id ) {
        if ( ! $product_id ) return false;
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_in_stock() ) return false;
        if ( taxonomy_exists( 'product_discontinued' )
             && has_term( 'dp-discontinued', 'product_discontinued', $product_id ) ) return false;
        return $product;
    }

    /**
     * Returns a deduplicated, validated list of accessory IDs
     * for a given projector, with backward-compat for old single-ID meta.
     *
     * @return array{screens: int[], bags: int[]}
     */
    private static function get_bundle_ids( int $projector_id ): array {
        $screen_ids = get_post_meta( $projector_id, '_aun_bundle_screens', true );
        if ( empty( $screen_ids ) || ! is_array( $screen_ids ) ) {
            $old = get_post_meta( $projector_id, '_aun_bundle_screen_id', true );
            $screen_ids = $old ? [ (int) $old ] : [];
        }

        $bag_ids = get_post_meta( $projector_id, '_aun_bundle_bags', true );
        if ( empty( $bag_ids ) || ! is_array( $bag_ids ) ) {
            $old = get_post_meta( $projector_id, '_aun_bundle_bag_id', true );
            $bag_ids = $old ? [ (int) $old ] : [];
        }

        return [
            'screens' => array_values( array_unique( array_filter( array_map( 'intval', $screen_ids ) ) ) ),
            'bags'    => array_values( array_unique( array_filter( array_map( 'intval', $bag_ids ) ) ) ),
        ];
    }

    /**
     * Returns the split discount percentages for a projector.
     *
     * @return array{screen: float, bag: float}
     */
    private static function get_discounts( int $projector_id ): array {
        $old_global = (float) get_post_meta( $projector_id, '_aun_bundle_discount', true );
        $screen_raw = get_post_meta( $projector_id, '_aun_bundle_screen_discount', true );
        $bag_raw    = get_post_meta( $projector_id, '_aun_bundle_bag_discount', true );

        return [
            'screen' => $screen_raw !== '' ? (float) $screen_raw : $old_global,
            'bag'    => $bag_raw    !== '' ? (float) $bag_raw    : $old_global,
        ];
    }

    /** Human-readable label for a variation, e.g. "84 inch" or "Red – Large". */
    private static function variation_label( $var ): string {
        if ( ! $var instanceof WC_Product ) return '';
        $attrs = [];
        foreach ( $var->get_variation_attributes() as $tax => $val ) {
            if ( $val === '' ) continue;
            $tax_name = str_replace( 'attribute_', '', $tax );
            $term     = get_term_by( 'slug', $val, $tax_name );
            $attrs[]  = $term ? $term->name : ucwords( str_replace( '-', ' ', $val ) );
        }
        return implode( ' – ', $attrs ) ?: ( '#' . $var->get_id() );
    }

    // -------------------------------------------------------------------------
    // Frontend display
    // -------------------------------------------------------------------------

    public static function render_bundle_box(): void {
        // wc_get_product(get_the_ID()) is reliable inside and outside the WC loop,
        // in Flatsome UX Builder and REST contexts — unlike global $product.
        $product = wc_get_product( get_the_ID() );
        if ( ! $product || ! $product->is_in_stock() ) return;

        $projector_id = $product->get_id();

        if ( taxonomy_exists( 'product_discontinued' )
             && has_term( 'dp-discontinued', 'product_discontinued', $projector_id ) ) return;

        $discounts  = self::get_discounts( $projector_id );
        $bundle_ids = self::get_bundle_ids( $projector_id );

        // Screens are ALTERNATIVES → a single "pick one" selector (type + size).
        // EVERY variation is listed, sold-out ones flagged (stock=0) rather than dropped:
        // filtering them out meant a screen with only one surviving size showed NO size
        // control at all, so the shopper never saw which size they were buying.
        $screens = [];
        foreach ( $bundle_ids['screens'] as $sid ) {
            $s = self::is_product_available( $sid );
            if ( ! $s ) continue;
            $opts      = [];
            $is_var    = $s->is_type( 'variable' );
            $has_stock = false;
            if ( $is_var ) {
                foreach ( $s->get_children() as $vid ) {
                    $v = wc_get_product( $vid );
                    if ( ! $v ) continue;
                    $in = ( $v->is_in_stock() && $v->is_purchasable() );
                    if ( $in ) $has_stock = true;
                    $opts[] = [
                        'vid'   => (int) $vid,
                        'label' => self::variation_label( $v ),
                        'price' => (float) $v->get_price(),
                        'stock' => $in ? 1 : 0,
                    ];
                }
            } else {
                $opts[]    = [ 'vid' => 0, 'label' => '', 'price' => (float) $s->get_price(), 'stock' => 1 ];
                $has_stock = true;
            }
            // Nothing buyable in any size → don't offer the screen at all.
            if ( empty( $opts ) || ! $has_stock ) continue;
            $img_id    = $s->get_image_id();
            $screens[] = [
                'id'   => $s->get_id(),
                'name' => $s->get_name(),
                'url'  => $s->get_permalink(),
                'pct'  => (float) $discounts['screen'],
                'img'  => $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_gallery_thumbnail' ) : wc_placeholder_img_src(),
                'var'  => $is_var ? 1 : 0,
                'opts' => $opts,
            ];
        }

        // Bags stay as optional add-on checkboxes.
        $bags = [];
        foreach ( $bundle_ids['bags'] as $bid ) {
            $b = self::is_product_available( $bid );
            if ( $b ) $bags[] = $b;
        }

        if ( empty( $screens ) && empty( $bags ) ) return;

        $proj_price = (float) $product->get_price();

        // CSS — static flag prevents duplication if block is placed twice
        if ( ! self::$styles_printed ) {
            self::$styles_printed = true;
            ?>
            <style id="aun-bundler-css" data-no-optimize="1" data-no-minify="1">
            /* width:100% + max-width:100% keep the box from ever widening a content-sized
               summary column (Flatsome's .product-info), which would collapse the 2-col layout. */
            .aun-bundle-wrapper{margin:18px 0;border:1px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;width:100%;max-width:100%;box-sizing:border-box;}
            .aun-bundle-header{display:flex;align-items:center;gap:8px;padding:13px 16px;border-bottom:1px solid #f1f5f9;background:#f8fafc;}
            .aun-bundle-header h3{margin:0;font-size:15px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:7px;}
            .aun-bundle-header h3 i{color:#0188fe;}
            .aun-bundle-items{display:flex;flex-direction:column;gap:0;}

            /* Row */
            .aun-bundle-row{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background .15s,border-color .15s;position:relative;min-width:0;}
            .aun-bundle-row:last-child{border-bottom:none;}
            .aun-bundle-row:hover{background:#f8fafc;}
            /* Selected state — clear visual feedback on both desktop and mobile */
            .aun-bundle-row:has(input:checked){background:#eff6ff;border-left:3px solid #0188fe;}
            .aun-bundle-row:has(input:checked) .aun-bundle-title{color:#0188fe;}

            /* Checkbox */
            .aun-bundle-checkbox{width:17px;height:17px;flex-shrink:0;accent-color:#0188fe;cursor:pointer;}

            /* Thumbnail */
            .aun-bundle-thumb{flex:0 0 40px;height:40px;border-radius:6px;overflow:hidden;background:#fff;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;}
            .aun-bundle-thumb img{width:100%;height:100%;object-fit:contain;}

            /* Details */
            .aun-bundle-details{flex:1;min-width:0;}
            /* MUST wrap (not nowrap): a long one-line title would force a content-sized Flatsome
               column wide and break the 2-col layout. Clamp to 2 lines + break long words so the
               title's minimum width stays tiny and can never widen the column. */
            .aun-bundle-title{font-weight:600;font-size:13px;color:#334155;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;overflow-wrap:anywhere;line-height:1.3;text-decoration:none;}
            .aun-bundle-title:hover{color:#0188fe;text-decoration:underline;}
            .aun-bundle-type-badge{display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-top:3px;background:#e0e7ff;color:#3730a3;}
            .aun-bundle-type-badge.type-bag{background:#fef3c7;color:#92400e;}

            /* Variation select */
            /* Clear "this is a dropdown" affordance: custom blue chevron + pill styling (appearance:none
               so it looks identical across browsers/themes, since some themes hide the native arrow). */
            .aun-bundle-var-select{margin-top:6px;padding:7px 30px 7px 11px;border-radius:8px;font-size:12.5px;font-weight:600;color:#0f172a;width:100%;max-width:210px;border:1px solid #bcd0e8;background:#f7fbff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 4.5 6 7.5 9 4.5' fill='none' stroke='%230188fe' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 10px center;background-size:12px;-webkit-appearance:none;-moz-appearance:none;appearance:none;cursor:pointer;transition:border-color .15s,box-shadow .15s;}
            .aun-bundle-var-select:hover{border-color:#0188fe;}
            .aun-bundle-var-select:focus{outline:none;border-color:#0188fe;box-shadow:0 0 0 3px rgba(1,136,254,.15);}

            /* Screen picker (pick ONE) — radio indicator + type/size dropdowns */
            .aun-screen-row{cursor:default;}
            .aun-bundle-row.is-selected{background:#eff6ff;border-left:3px solid #0188fe;}
            /* The radio doubles as a "remove" control once a screen is chosen, so make it clearly clickable. */
            .aun-bundle-radio{width:18px;height:18px;flex-shrink:0;border:2px solid #cbd5e1;border-radius:50%;position:relative;box-sizing:border-box;cursor:pointer;transition:border-color .15s;}
            .aun-bundle-radio:hover{border-color:#0188fe;}
            .aun-bundle-row.is-selected .aun-bundle-radio{border-color:#0188fe;}
            .aun-bundle-row.is-selected .aun-bundle-radio::after{content:"";position:absolute;inset:3px;border-radius:50%;background:#0188fe;}
            .aun-screen-label{display:block;color:#334155;font-weight:600;font-size:13px;}
            .aun-opt{font-weight:500;color:#94a3b8;font-size:11px;}
            .aun-screen-selects{display:flex;flex-wrap:wrap;gap:7px;margin-top:6px;}
            .aun-screen-selects .aun-bundle-var-select{margin-top:0;}
            #aun-screen-type{flex:1 1 150px;max-width:none;min-width:0;}
            #aun-screen-size{flex:0 0 auto;max-width:130px;}
            /* Sold-out sizes/colours stay listed but unselectable — browsers grey disabled
               options natively; this just makes the "unavailable" read clearer. */
            .aun-bundle-var-select option:disabled{color:#b6c2cf;}
            /* View / Remove actions (shown once a screen is selected) */
            .aun-screen-actions{display:flex;gap:16px;align-items:center;margin-top:8px;flex-wrap:wrap;}
            .aun-screen-link{font-size:12px;font-weight:600;color:#0188fe;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:none;border:0;padding:0;cursor:pointer;font-family:inherit;line-height:1;}
            .aun-screen-link:hover{text-decoration:underline;}
            .aun-screen-link i{font-size:11px;}

            /* Price column */
            .aun-bundle-price-col{text-align:right;display:flex;flex-direction:column;align-items:flex-end;justify-content:center;min-width:90px;gap:3px;}
            .aun-acc-price-display{font-weight:700;color:#334155;font-size:14px;}
            .aun-discount-badge{background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;white-space:nowrap;}

            /* Summary bar */
            .aun-bundle-summary{padding:12px 16px;background:#f0fdf4;border-top:1px solid #bbf7d0;display:none;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
            .aun-bundle-summary.is-active{display:flex;}
            .aun-bundle-summary-text{font-size:13px;color:#166534;font-weight:600;display:flex;align-items:center;gap:6px;}
            .aun-bundle-summary-text i{font-size:14px;}
            .aun-bundle-summary-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
            .aun-bundle-new-total{font-size:15px;font-weight:800;color:#0f172a;}
            .aun-bundle-total-saving{font-size:13px;font-weight:800;color:#166534;background:#dcfce7;padding:3px 10px;border-radius:20px;}
            </style>
            <?php
        }
        ?>

        <div class="aun-bundle-wrapper" id="aun-bundle-ui">
            <div class="aun-bundle-header">
                <h3>
                    <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    Add Accessories &amp; Save
                </h3>
            </div>

            <div class="aun-bundle-items">

                <?php if ( ! empty( $screens ) ) : ?>
                <!-- Screen picker — the 3 screens are alternatives, so the customer chooses ONE. -->
                <div class="aun-bundle-row aun-screen-row" id="aun-screen-row">
                    <span class="aun-bundle-radio" aria-hidden="true"></span>
                    <div class="aun-bundle-thumb" id="aun-screen-thumb">
                        <i class="fa-solid fa-display" style="color:#94a3b8;font-size:17px" aria-hidden="true"></i>
                    </div>
                    <div class="aun-bundle-details">
                        <span class="aun-screen-label">Choose your screen <span class="aun-opt">(optional)</span></span>
                        <div class="aun-screen-selects">
                            <select id="aun-screen-type" class="aun-bundle-var-select" aria-label="Choose a screen">
                                <option value="">— Select a screen —</option>
                                <?php foreach ( $screens as $i => $s ) : ?>
                                    <option value="<?php echo (int) $i; ?>"><?php echo esc_html( $s['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="aun-screen-size" class="aun-bundle-var-select" aria-label="Choose size" style="display:none"></select>
                        </div>
                        <div class="aun-screen-actions" id="aun-screen-actions" style="display:none">
                            <a id="aun-screen-view" class="aun-screen-link" target="_blank" rel="noopener noreferrer" href="#"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> View this screen</a>
                        </div>
                    </div>
                    <div class="aun-bundle-price-col">
                        <span class="aun-acc-price-display" id="aun-screen-price"></span>
                        <span class="aun-discount-badge" id="aun-screen-badge" style="display:none"></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php foreach ( $bags as $bag ) :
                    $bid    = $bag->get_id();
                    $bpct   = (float) $discounts['bag'];
                    $bprice = (float) $bag->get_price();
                    $bsaved = $bprice * ( $bpct / 100 );
                ?>
                <label class="aun-bundle-row">
                    <input type="checkbox" class="aun-bundle-checkbox"
                           data-acc-id="<?php echo esc_attr( $bid ); ?>"
                           data-discount-pct="<?php echo esc_attr( $bpct ); ?>"
                           data-price="<?php echo esc_attr( $bprice ); ?>">
                    <div class="aun-bundle-thumb"><?php echo wp_kses_post( $bag->get_image( [ 40, 40 ] ) ); ?></div>
                    <div class="aun-bundle-details">
                        <a href="<?php echo esc_url( $bag->get_permalink() ); ?>" target="_blank" rel="noopener noreferrer"
                           class="aun-bundle-title" onclick="event.stopPropagation()"><?php echo esc_html( $bag->get_name() ); ?></a>
                        <span class="aun-bundle-type-badge type-bag">Bag</span>
                        <?php if ( $bag->is_type( 'variable' ) ) : ?>
                            <?php // Same rule as screens: list every variation, disable the sold-out
                                  // ones (so the shopper sees the full range) and pre-select the first
                                  // one that is actually buyable. ?>
                            <select id="var-select-<?php echo esc_attr( $bid ); ?>" class="aun-bundle-var-select">
                                <?php $picked = false;
                                foreach ( $bag->get_children() as $vid ) :
                                    $v = wc_get_product( $vid );
                                    if ( ! $v ) continue;
                                    $in  = ( $v->is_in_stock() && $v->is_purchasable() );
                                    $sel = ( $in && ! $picked );
                                    if ( $sel ) { $picked = true; } ?>
                                    <option value="<?php echo esc_attr( $vid ); ?>" data-price="<?php echo esc_attr( (float) $v->get_price() ); ?>" <?php disabled( ! $in ); selected( $sel ); ?>><?php echo esc_html( self::variation_label( $v ) . ( $in ? '' : ' — Sold out' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="aun-bundle-price-col">
                        <span class="aun-acc-price-display" id="price-display-<?php echo esc_attr( $bid ); ?>">+<?php echo wp_kses_post( wc_price( $bprice ) ); ?></span>
                        <?php if ( $bpct > 0 ) : ?>
                            <span class="aun-discount-badge" id="badge-<?php echo esc_attr( $bid ); ?>">Save <?php echo wp_kses_post( wc_price( round( $bsaved ) ) ); ?></span>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>

            </div>

            <!-- Summary bar — shown only when at least one accessory is checked -->
            <div class="aun-bundle-summary" id="aun-bundle-summary">
                <span class="aun-bundle-summary-text">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span id="aun-bundle-count-text">Bundle total</span>
                </span>
                <span class="aun-bundle-summary-right">
                    <span class="aun-bundle-new-total" id="aun-bundle-new-total"></span>
                    <span class="aun-bundle-total-saving" id="aun-bundle-total-saving"></span>
                </span>
            </div>
        </div>

        <script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
        document.addEventListener('DOMContentLoaded', function () {
            var basePrice = <?php echo json_encode( $proj_price ); ?>;
            var SCREENS   = <?php echo wp_json_encode( array_values( array_map( function ( $s ) {
                return [ 'id' => $s['id'], 'url' => $s['url'], 'pct' => $s['pct'], 'img' => $s['img'], 'var' => $s['var'], 'opts' => $s['opts'] ];
            }, $screens ) ) ); ?>;

            function formatMoney(a){ return '৳' + Math.round(a).toLocaleString('en-IN', { minimumFractionDigits:0, maximumFractionDigits:0 }); }

            var summaryBar    = document.getElementById('aun-bundle-summary');
            var totalSavingEl = document.getElementById('aun-bundle-total-saving');
            var bagCbs        = document.querySelectorAll('.aun-bundle-checkbox');

            var typeSel     = document.getElementById('aun-screen-type');
            var sizeSel     = document.getElementById('aun-screen-size');
            var screenRow   = document.getElementById('aun-screen-row');
            var screenThumb = document.getElementById('aun-screen-thumb');
            var screenPrice = document.getElementById('aun-screen-price');
            var screenBadge = document.getElementById('aun-screen-badge');
            var screenIcon  = '<i class="fa-solid fa-display" style="color:#94a3b8;font-size:17px" aria-hidden="true"></i>';
            var screenActions = document.getElementById('aun-screen-actions');
            var screenView    = document.getElementById('aun-screen-view');
            var screenRadio   = screenRow ? screenRow.querySelector('.aun-bundle-radio') : null;

            // Returns the currently chosen screen {s, opt} or null (nothing selected / size not picked yet).
            function currentScreen(){
                if (!typeSel || typeSel.value === '') return null;
                var s = SCREENS[parseInt(typeSel.value, 10)];
                if (!s) return null;
                var oi = 0;
                if (s.var){
                    if (!sizeSel || sizeSel.value === '') return null;
                    oi = parseInt(sizeSel.value, 10);
                }
                var opt = s.opts[oi];
                // A sold-out size can never be priced or added (belt-and-braces: the
                // option is disabled in the dropdown, so it shouldn't be selectable).
                if (!opt || !opt.stock) return null;
                return { s: s, opt: opt };
            }

            // When the screen TYPE changes: swap the thumbnail, (re)build the size dropdown, recalc.
            function onScreenType(){
                if (!typeSel) return;
                if (typeSel.value === ''){
                    if (sizeSel){ sizeSel.style.display = 'none'; sizeSel.innerHTML = ''; }
                    if (screenThumb) screenThumb.innerHTML = screenIcon;
                    if (screenRow) screenRow.classList.remove('is-selected');
                    if (screenActions) screenActions.style.display = 'none';
                } else {
                    var s = SCREENS[parseInt(typeSel.value, 10)];
                    if (s && screenThumb) screenThumb.innerHTML = '<img src="' + s.img + '" alt="" style="width:100%;height:100%;object-fit:contain;">';
                    if (s && screenView) screenView.href = s.url;
                    if (screenActions) screenActions.style.display = '';
                    // Show the size dropdown for ANY variable screen — even when only one
                    // size is left in stock — so the shopper always sees which size they
                    // are buying. Sold-out sizes stay listed but disabled (greyed by the
                    // browser) so the range is visible and the choice is honest.
                    if (s && s.var && sizeSel){
                        sizeSel.innerHTML = '';
                        var firstInStock = -1;
                        s.opts.forEach(function(o, idx){
                            if (o.stock && firstInStock < 0) firstInStock = idx;
                            var op = document.createElement('option');
                            op.value = String(idx);
                            op.textContent = o.label + (o.stock ? '' : ' — Sold out');
                            if (!o.stock) op.disabled = true;
                            sizeSel.appendChild(op);
                        });
                        // Auto-pick the first AVAILABLE size (index 0 may be sold out).
                        sizeSel.value = String(firstInStock < 0 ? 0 : firstInStock);
                        sizeSel.style.display = '';
                    } else if (sizeSel){
                        sizeSel.style.display = 'none'; sizeSel.innerHTML = '';
                    }
                    if (screenRow) screenRow.classList.add('is-selected');
                }
                recalc();
            }

            function inject(accId, varId){
                document.querySelectorAll('form.cart').forEach(function(form){
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = 'aun_bundle_acc[' + accId + ']'; i.value = '1'; i.className = 'aun-sync-input';
                    form.appendChild(i);
                    if (varId){
                        var v = document.createElement('input');
                        v.type = 'hidden'; v.name = 'aun_bundle_acc_var[' + accId + ']'; v.value = varId; v.className = 'aun-sync-input';
                        form.appendChild(v);
                    }
                });
            }

            function recalc(){
                var total = basePrice, saving = 0, count = 0;
                document.querySelectorAll('form.cart .aun-sync-input').forEach(function(el){ el.remove(); });

                // Chosen screen (pick one)
                var cs = currentScreen();
                if (cs){
                    var sp = cs.opt.price, sd = sp * (cs.s.pct / 100);
                    if (screenPrice) screenPrice.textContent = '+' + formatMoney(sp);
                    if (screenBadge){ if (sd > 0){ screenBadge.style.display = ''; screenBadge.textContent = 'Save ' + formatMoney(sd); } else screenBadge.style.display = 'none'; }
                    total += sp - sd; saving += sd; count++;
                    inject(cs.s.id, cs.opt.vid);
                } else {
                    if (screenPrice) screenPrice.textContent = '';
                    if (screenBadge) screenBadge.style.display = 'none';
                }

                // Optional add-on bags
                bagCbs.forEach(function(cb){
                    var id  = cb.getAttribute('data-acc-id');
                    var pct = parseFloat(cb.getAttribute('data-discount-pct')) || 0;
                    var vs  = document.getElementById('var-select-' + id);
                    var p   = (vs && vs.options.length) ? (parseFloat(vs.options[vs.selectedIndex].getAttribute('data-price')) || 0) : (parseFloat(cb.getAttribute('data-price')) || 0);
                    var d   = p * (pct / 100);
                    var pd  = document.getElementById('price-display-' + id); if (pd) pd.textContent = '+' + formatMoney(p);
                    var bg  = document.getElementById('badge-' + id); if (bg && d > 0) bg.textContent = 'Save ' + formatMoney(d);
                    if (cb.checked){ total += p - d; saving += d; count++; inject(id, vs && vs.value ? vs.value : 0); }
                });

                if (count > 0){
                    document.querySelectorAll('form.cart').forEach(function(form){
                        var inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = 'aun_add_bundle_native'; inp.value = '1'; inp.className = 'aun-sync-input';
                        form.appendChild(inp);
                    });
                }

                // Our own summary bar (we never touch the theme price element).
                if (summaryBar){
                    if (count > 0){
                        summaryBar.classList.add('is-active');
                        var ce = document.getElementById('aun-bundle-count-text'); if (ce) ce.textContent = (count === 1 ? '1 item added' : count + ' items added');
                        var nt = document.getElementById('aun-bundle-new-total'); if (nt) nt.textContent = 'Total ' + formatMoney(total);
                        if (totalSavingEl){ if (saving > 0){ totalSavingEl.style.display = ''; totalSavingEl.textContent = 'Save ' + formatMoney(saving); } else totalSavingEl.style.display = 'none'; }
                    } else {
                        summaryBar.classList.remove('is-active');
                    }
                }
            }

            // Deselect / remove the chosen screen (reset to "— Select a screen —").
            function clearScreen(){ if (typeSel){ typeSel.value = ''; onScreenType(); } }

            if (typeSel) typeSel.addEventListener('change', onScreenType);
            if (sizeSel) sizeSel.addEventListener('change', recalc);
            // Clicking the radio circle clears the chosen screen.
            if (screenRadio) screenRadio.addEventListener('click', function(){ if (typeSel && typeSel.value !== '') clearScreen(); });
            bagCbs.forEach(function(cb){ cb.addEventListener('change', recalc); });
            document.querySelectorAll('.aun-bundle-items .aun-bundle-var-select').forEach(function(sel){
                if (sel.id !== 'aun-screen-type' && sel.id !== 'aun-screen-size') sel.addEventListener('change', recalc);
            });

            recalc();
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Native Cart Integration
    // -------------------------------------------------------------------------

    public static function add_native_bundle_items(
        string $cart_item_key, int $product_id, int $quantity,
        int $variation_id, array $variation, array $cart_item_data
    ): void {
        // Use $_REQUEST to handle both standard forms and AJAX / Buy Now
        if ( empty( $_REQUEST['aun_add_bundle_native'] ) || $_REQUEST['aun_add_bundle_native'] !== '1' ) return;
        if ( empty( $_REQUEST['aun_bundle_acc'] ) || ! is_array( $_REQUEST['aun_bundle_acc'] ) ) return;

        // Unhook temporarily to prevent infinite recursion while adding accessories
        remove_action( 'woocommerce_add_to_cart', [ __CLASS__, 'add_native_bundle_items' ], 10 );

        foreach ( $_REQUEST['aun_bundle_acc'] as $raw_id => $is_checked ) {
            if ( $is_checked !== '1' ) continue;

            $acc_id = absint( $raw_id );
            if ( $acc_id <= 0 ) continue;

            // Validate the accessory is a real, available product before adding
            if ( ! self::is_product_available( $acc_id ) ) continue;

            // Tag this cart item so we can apply the discount only to bundled items
            $extra_data = [ '_aun_bundled_with' => $product_id ];

            $var_id = 0;
            if ( ! empty( $_REQUEST['aun_bundle_acc_var'][ $acc_id ] ) ) {
                $var_id = absint( $_REQUEST['aun_bundle_acc_var'][ $acc_id ] );
            }

            // Add 1 of each accessory — independent of projector quantity
            WC()->cart->add_to_cart( $acc_id, 1, $var_id, [], $extra_data );
        }

        // Re-hook for future add-to-cart events
        add_action( 'woocommerce_add_to_cart', [ __CLASS__, 'add_native_bundle_items' ], 10, 6 );
    }

    // -------------------------------------------------------------------------
    // Discount Logic
    // -------------------------------------------------------------------------

    public static function apply_bundle_discount( WC_Cart $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        $discount_total  = 0.0;
        $discounted_keys = [];
        $cart_items      = $cart->get_cart();

        foreach ( $cart_items as $cart_item_key => $cart_item ) {
            $projector_id = $cart_item['product_id'];
            $discounts    = self::get_discounts( $projector_id );

            if ( $discounts['screen'] <= 0 && $discounts['bag'] <= 0 ) continue;

            $bundle_ids = self::get_bundle_ids( $projector_id );

            foreach ( $cart_items as $acc_key => $acc_item ) {
                // Skip already-discounted items (prevents double-discount)
                if ( in_array( $acc_key, $discounted_keys, true ) ) continue;

                // Only discount items that were explicitly bundled with this projector
                if ( empty( $acc_item['_aun_bundled_with'] )
                     || (int) $acc_item['_aun_bundled_with'] !== $projector_id ) continue;

                $acc_id          = $acc_item['product_id'];
                $discount_amount = 0.0;

                $base = isset( $acc_item['line_subtotal'] ) ? (float) $acc_item['line_subtotal'] : (float) $acc_item['line_total'];
                if ( in_array( $acc_id, $bundle_ids['screens'], true ) && $discounts['screen'] > 0 ) {
                    $discount_amount = $base * ( $discounts['screen'] / 100 );
                } elseif ( in_array( $acc_id, $bundle_ids['bags'], true ) && $discounts['bag'] > 0 ) {
                    $discount_amount = $base * ( $discounts['bag'] / 100 );
                }

                if ( $discount_amount > 0 ) {
                    $discount_total  += $discount_amount;
                    $discounted_keys[] = $acc_key;
                }
            }
        }

        if ( $discount_total > 0 ) {
            $cart->add_fee( 'Bundle Saving', -$discount_total, true );
        }
    }
}

AUN_Smart_Bundler::init();