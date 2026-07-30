<?php
/**
 * Plugin Name: AUN Projector Compare
 * Description: GSMArena-style comparison for projectors (AUN). (v2.7.0 — brand-color pass
 *              (#0188fe), price shown in product headers, richer empty state with CTAs,
 *              Share/copy-link button, toolbar shown wherever the table renders, WP Rocket
 *              guards on inline CSS, unpublished products excluded from URL ids, removed
 *              hardcoded page header/intro (page content owns the hero now), safe redirect,
 *              spec-cache version bump.)
 * Version:     2.7.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

namespace AUN\Compare;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Plugin {

    const LIMIT                = 3;
    const TAB_HINT             = 'spec';
    const SLUG                 = 'projector-compare';
    const REQUIRE_SPEC         = true;
    const RESTRICT_TO_CATEGORY = false;
    const CATEGORY_SLUG        = 'projector';
    const CACHE_VERSION        = 'v2'; // v2: abandons caches parsed from the old ANSI-era spec tabs

    private static $rendered = false;

    public static function init() {
        \add_action( 'wp_enqueue_scripts',   [__CLASS__, 'enqueue_assets'] );
        \add_filter( 'script_loader_tag',    [__CLASS__, 'no_defer_script'], 10, 2 );
        \add_action( 'woocommerce_product_meta_end',     [__CLASS__, 'button_under_excerpt'], 10 );
        \add_action( 'woocommerce_after_shop_loop_item', [__CLASS__, 'button_on_shop_page'],  11 );
        \add_shortcode( 'aun_compare_table', [__CLASS__, 'shortcode'] );
        \add_filter( 'the_content',          [__CLASS__, 'content_replacer'], 20 );
        \add_action( 'template_redirect',    [__CLASS__, 'redirect_old_slug'] );
        \add_filter( 'plugin_action_links_' . \plugin_basename( __FILE__ ), [__CLASS__, 'action_links'] );
        \add_action( 'save_post',            [__CLASS__, 'clear_spec_cache'] );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets() {
        $css = '
        .aun-compare-link-container{margin-top:15px;margin-bottom:15px}
        .aun-add-to-compare{color:#0188fe;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;font-size:1em!important;transition:all 0.2s ease}
        .aun-add-to-compare:hover{text-decoration:none}
        .aun-add-to-compare:hover span{text-decoration:underline}
        .aun-add-to-compare i{margin-right:8px;font-size:1.2em}
        .aun-add-to-compare.added{background-color:#0188fe;color:#fff;text-decoration:none;padding:4px 10px;border-radius:4px}
        .aun-add-to-compare.added:hover{text-decoration:none}
        .aun-add-to-compare.added i{margin-right:6px}

        .aun-compare--shop{display:flex!important;align-items:center;justify-content:center;text-align:center;margin-top:8px;padding:8px 10px!important;border:1px solid #e5e7eb;color:#374151!important;background-color:#f9fafb!important;font-weight:600;font-size:0.9em!important;border-radius:4px;text-decoration:none!important;line-height:1.2;width:100%;box-shadow:none!important;transition:all 0.2s ease}
        .aun-compare--shop i{margin-right:6px;font-size:1.1em}
        .aun-compare--shop:hover{background-color:#f3f4f6!important;color:#111827!important;border-color:#d1d5db!important;text-decoration:none!important}
        .aun-compare--shop.added{background-color:#374151!important;color:#fff!important;border-color:#374151!important}
        .aun-compare--shop.added:hover{background-color:#1f2937!important;border-color:#1f2937!important}

        .aun-compare-modal-backdrop{position:fixed;top:0;left:0;right:0;bottom:0;background-color:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity 0.2s ease}
        .aun-compare-modal{background-color:#fff;border-radius:8px;padding:24px;box-shadow:0 5px 15px rgba(0,0,0,.1);max-width:400px;width:100%;text-align:center;transform:scale(0.95);transition:transform 0.2s ease}
        .aun-compare-modal-backdrop.aun-modal-visible{opacity:1}
        .aun-compare-modal-backdrop.aun-modal-visible .aun-compare-modal{transform:scale(1)}
        .aun-compare-modal p{margin-top:0;margin-bottom:20px;font-size:16px;color:#111827}
        .aun-compare-modal-close{width:100px}
        .aun-compare-modal-close:hover{background:#e5e7eb}

        .aun-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:6px;font-weight:700;text-decoration:none;cursor:pointer;border:1px solid transparent;padding:0 12px;font-size:14px;height:36px;line-height:1!important;box-sizing:border-box;-webkit-appearance:none;appearance:none;margin:0}
        .aun-btn--primary{background:#0188fe;color:#fff;border-color:#0188fe}
        .aun-btn--ghost{background:#111827;color:#e5e7eb;border-color:#3f3f46}
        .aun-btn--soft{background:#f3f4f6;color:#111827;border-color:#e5e7eb}
        .aun-btn[aria-disabled="true"],.aun-btn.disabled{opacity:.65;pointer-events:none}

        .aun-compare-dock{position:fixed;right:16px;bottom:24px;z-index:99999;display:none}
        .aun-compare-dock .dock-inner{display:flex;align-items:center;gap:8px;background:#111827;color:#fff;border-radius:9999px;padding:8px 10px;box-shadow:0 10px 30px rgba(0,0,0,.2)}
        .aun-compare-dock .dock-inner>*{align-self:center}
        .aun-compare-count{display:flex;align-items:center;justify-content:center;background:#0188fe;min-width:22px;height:22px;line-height:22px;border-radius:12px;text-align:center;font-size:12px;font-weight:700;padding:0 6px}

        @media(max-width:768px){.aun-compare-dock{bottom:84px}}

        .aun-compare-wrap{margin-top:18px}
        .aun-compare-top{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin:8px 0 12px}
        .aun-compare-top .title{font-size:20px;font-weight:800;margin:0}
        .aun-compare-top .tools{display:flex;gap:8px;align-items:center}

        .aun-compare-table-wrapper{overflow:auto;border-radius:12px;border:1px solid #e2e8f0;background:#fff}

        .aun-toc{display:flex;gap:7px;margin:0;padding:14px 16px 0;flex-wrap:wrap}
        .aun-chip{display:inline-flex;align-items:center;gap:5px;border-radius:9999px;border:1px solid #e2e8f0;background:#f8fafc;padding:5px 11px;font-size:12px;font-weight:600;text-decoration:none;color:#334155;white-space:nowrap;transition:background .15s,border-color .15s}
        .aun-chip:hover{background:#eff6ff;border-color:#bfdbfe;color:#1e40af}
        .aun-chip i{font-size:11px;color:#0188fe}

        table.aun-compare-table{width:100%;border-collapse:separate;border-spacing:0}
        .aun-compare-table th,.aun-compare-table td{border-bottom:1px solid #f1f5f9;padding:10px 14px;vertical-align:top;word-break:break-word}

        /* Sticky spec-label column — left */
        .aun-compare-table .spec-label{position:sticky;left:0;background:#fff;z-index:1;min-width:180px;font-size:13px;font-weight:600;color:#374151}

        /* FIX 4: "Spec" header cell — vertically and horizontally centered */
        .aun-compare-table thead th.spec-label{
            font-size:11px;font-weight:700;color:#64748b;
            text-transform:uppercase;letter-spacing:.5px;
            padding:12px 16px;
            text-align:center;
            vertical-align:middle;
        }

        /* Sticky product header row — top */
        .aun-compare-table thead th{position:sticky;top:0;background:#f8fafc;z-index:2;text-align:center;border-bottom:2px solid #e2e8f0;padding:0;vertical-align:top}
        .aun-prod-hd{display:flex;flex-direction:column;align-items:center;padding:18px 14px}
        .aun-compare-table .product-header img{width:100px;height:80px;object-fit:contain;display:block;margin:0 auto 10px;border-radius:8px;background:#f1f5f9;padding:4px;transition:opacity .2s}
        .aun-compare-table .product-header img:hover{opacity:.85}
        .aun-prod-name{font-size:13px;font-weight:700;color:#0f172a;margin:0 0 6px;line-height:1.3;text-align:center;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .aun-prod-link{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#0188fe;text-decoration:none;font-weight:600}
        .aun-prod-link:hover{text-decoration:underline}
        .aun-prod-link i{font-size:9px}

        .aun-compare-table th.product-header,.aun-compare-table td:not(.spec-label){min-width:220px}

        /* Section header rows */
        .aun-compare-table td.section{background:#eff6ff;color:#1e3a5f;font-weight:700;font-size:12px;letter-spacing:.4px;white-space:nowrap;border-top:2px solid #bfdbfe;border-bottom:1px solid #bfdbfe;border-left:3px solid #0188fe;padding-left:14px;border-radius:0}
        .aun-compare-table td.section i{color:#0188fe;margin-right:7px;font-size:13px;vertical-align:-1px}

        /* FIX 2: Diff rows — amber left bar on spec-label only, NO ≠ symbol */
        .aun-diff td{background:#fffbeb!important}
        .aun-diff .spec-label{border-left:3px solid #f59e0b;padding-left:11px}

        /* Row hover */
        .aun-compare-table tbody tr:not([class]):hover td,
        .aun-compare-table tbody tr.aun-diff:hover td{background:#f8fafc!important}

        /* Spec value cell */
        .aun-spec-value{display:block;font-size:13px;color:#1e293b;text-align:center}

        .aun-empty{padding:32px 24px;text-align:center;color:#6b7280;font-size:14px;line-height:1.6}
        .aun-empty .aun-empty-icon{font-size:40px;color:#cbd5e1;display:block;margin-bottom:12px}
        .aun-empty-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:14px}

        .aun-prod-price{font-size:14px;font-weight:800;color:#0188fe;margin:0 0 6px}
        .aun-prod-price del{color:#94a3b8;font-weight:600;font-size:12px;margin-right:5px}
        .aun-prod-price ins{text-decoration:none}

        /* Anchor jumps from the TOC land below the sticky product header */
        .aun-section-row{scroll-margin-top:90px}

        .aun-diff-toggle{display:inline-flex;align-items:center;gap:8px;border:1px solid #e5e7eb;border-radius:6px;background:#f3f4f6;padding:8px 12px;font-weight:600;cursor:pointer}
        .aun-diff-toggle input{margin:0;position:relative;top:0}

        .aun-compare-table-wrapper:after{content:"";position:sticky;right:0;top:0;bottom:0;width:24px;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,1));display:block;pointer-events:none}

        /* FIX 3: Mobile — keep spec-label column visible (narrower), no repeated spec keys inside cells */
        @media(max-width:640px){
            .aun-toc{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:8px}
            .aun-compare-table{border-spacing:0}

            /* Keep thead but remove sticky so it appears only once at the very top,
               not appearing to "float" over every section as the user scrolls */
            .aun-compare-table thead th{position:static}

            /* Spec-label column stays VISIBLE on mobile — it is the only place the
               spec name appears per row (GSMArena style). Never hide it. */
            .aun-compare-table .spec-label{min-width:90px;font-size:11px;padding:8px 8px;position:static}

            /* Product columns narrower on mobile */
            .aun-compare-table th.product-header,.aun-compare-table td:not(.spec-label){min-width:130px;scroll-snap-align:start}

            .aun-compare-table th,.aun-compare-table td{font-size:12px;padding:8px 8px;vertical-align:top}
            .aun-compare-table .product-header img{width:64px;height:52px}
            .aun-prod-hd{padding:10px 8px}
            .aun-prod-name{font-size:12px}
            .aun-compare-table td.section{padding:8px 8px 8px 10px;font-size:11px}
            .aun-compare-table-wrapper{overflow-x:auto;-webkit-overflow-scrolling:touch;scroll-snap-type:x proximity}
            .aun-spec-value{text-align:left}
        }
        ';

        // Printed directly with WP Rocket guards — wp_add_inline_style() output has no
        // data-no-optimize attribute, so Remove Unused CSS / minify could strip it.
        \add_action( 'wp_head', function () use ( $css ) {
            echo '<style id="aun-compare-css" data-no-optimize="1" data-no-minify="1">' . $css . '</style>' . "\n";
        }, 20 );

        $src = \plugins_url( 'assets/aun-compare.js', __FILE__ );
        \wp_enqueue_script( 'aun-compare-js', $src, ['jquery'], '2.7.0', true );
        \wp_localize_script( 'aun-compare-js', 'AUN_COMPARE', [
            'ajax_url'     => \admin_url( 'admin-ajax.php' ),
            'nonce'        => \wp_create_nonce( 'aun_compare_nonce' ),
            'compare_page' => \home_url( '/' . self::SLUG . '/' ),
            'limit'        => self::LIMIT,
        ] );
    }

    public static function no_defer_script( $tag, $handle ) {
        if ( $handle === 'aun-compare-js' ) {
            $tag = \str_replace( '<script ', '<script data-no-defer data-no-minify data-cfasync="false" ', $tag );
            if ( \strpos( $tag, 'id=' ) === false ) {
                $tag = \str_replace( '<script ', '<script id="AUN_COMPARE_DO_NOT_DELAY" ', $tag );
            }
        }
        return $tag;
    }

    // -------------------------------------------------------------------------
    // Buttons
    // -------------------------------------------------------------------------

    public static function button_under_excerpt() {
        if ( ! \function_exists( 'is_product' ) || ! \is_product() ) return;
        global $product;
        if ( ! $product ) return;
        $id = (int) $product->get_id();
        if ( ! self::should_show_button( $id ) ) return;

        echo '<div class="aun-compare-link-container">';
        echo '<a href="javascript:void(0);" role="button" class="aun-add-to-compare" data-product-id="' . \esc_attr( $id ) . '" aria-label="Add to Compare">';
        echo '<i class="fa-solid fa-code-compare" aria-hidden="true"></i>';
        echo '<span>Add to Compare</span>';
        echo '</a>';
        echo '</div>';
    }

    public static function button_on_shop_page() {
        global $product;
        if ( ! $product ) return;
        $id = (int) $product->get_id();
        if ( ! self::should_show_button( $id ) ) return;

        echo '<a href="javascript:void(0);" role="button" class="aun-add-to-compare aun-compare--shop" data-product-id="' . \esc_attr( $id ) . '" aria-label="Add ' . \esc_attr( $product->get_name() ) . ' to Compare">';
        echo '<i class="fa-solid fa-code-compare" aria-hidden="true"></i>';
        echo '<span>Compare</span>';
        echo '</a>';
    }

    private static function should_show_button( $product_id ) {
        if ( self::REQUIRE_SPEC && ! self::product_has_spec( $product_id ) ) return false;
        if ( self::RESTRICT_TO_CATEGORY && ! \has_term( self::CATEGORY_SLUG, 'product_cat', $product_id ) ) return false;
        return true;
    }

    // -------------------------------------------------------------------------
    // Shortcode / content injection
    // -------------------------------------------------------------------------

    public static function shortcode() { return self::render_table(); }

    public static function content_replacer( $content ) {
        if ( \is_admin() || self::$rendered ) return $content;
        if ( ! \is_page() ) return $content;

        global $post;
        if ( ! $post ) return $content;

        $is_compare_page = ( $post->post_name === self::SLUG );

        if ( \strpos( $content, '[aun_compare_table]' ) !== false ) {
            return \str_replace( '[aun_compare_table]', self::render_table(), $content );
        }

        if ( $is_compare_page && ! self::$rendered ) {
            $content .= "\n" . self::render_table();
        }

        return $content;
    }

    public static function redirect_old_slug() {
        if ( ! \is_page() ) return;
        global $post;
        if ( $post && $post->post_name === 'product-compare' ) {
            $target = \home_url( '/' . self::SLUG . '/' );
            if ( ! empty( $_GET['ids'] ) ) {
                $target = \add_query_arg( 'ids', \sanitize_text_field( \wp_unslash( $_GET['ids'] ) ), $target );
            }
            \wp_safe_redirect( $target, 301 );
            exit;
        }
    }

    private static function mark_rendered() { self::$rendered = true; }

    // -------------------------------------------------------------------------
    // Table renderer
    // -------------------------------------------------------------------------

    public static function render_table() {
        self::mark_rendered();

        $ids = [];
        if ( isset( $_GET['ids'] ) ) {
            $ids = \array_filter( \array_map( 'absint', \explode( ',', \sanitize_text_field( \wp_unslash( $_GET['ids'] ) ) ) ) );
            $ids = \array_slice( \array_unique( $ids ), 0, self::LIMIT );
        }

        \ob_start();

        // The compare page hero/intro lives in the page content now (design-system
        // template) — the plugin renders only the toolbar + table, so the shortcode
        // stays clean on any page it's used.

        $products       = [];
        $sections       = [];
        $section_labels = [];

        if ( \count( $ids ) >= 2 ) {

            foreach ( $ids as $id ) {
                $p = \wc_get_product( $id );
                // Status gate: ids come straight from the URL, so without this a visitor
                // could render draft/private/pending products into the table.
                if ( ! $p || $p->get_status() !== 'publish' ) continue;

                $cache_key = 'aun_spec_' . $id . '_' . self::CACHE_VERSION;
                $parsed    = \get_transient( $cache_key );

                if ( $parsed === false ) {
                    $spec_html = self::get_custom_tab_spec_content( $id );
                    if ( empty( $spec_html ) ) $spec_html = self::capture_spec_via_tabs_filter( $id );
                    if ( empty( $spec_html ) ) $spec_html = self::render_tabs_and_extract_spec_panel( $id );
                    $parsed = self::parse_spec_table( $spec_html );
                    \set_transient( $cache_key, $parsed, DAY_IN_SECONDS );
                }

                $products[$id] = [
                    'id'          => $id,
                    'title'       => \wp_strip_all_tags( $p->get_name() ),
                    'link'        => \get_permalink( $id ),
                    'img'         => ( $img_id = $p->get_image_id() ) ? \wp_get_attachment_image_url( $img_id, 'medium' ) : \wc_placeholder_img_src( 'medium' ),
                    'price'       => $p->get_price_html(),
                    'spec'        => $parsed['data'],
                    'spec_labels' => $parsed['labels'],
                ];
            }

            foreach ( $products as $prod ) {
                foreach ( $prod['spec'] as $section => $rows ) {
                    if ( ! isset( $sections[$section] ) ) $sections[$section] = [];
                    $sections[$section] = \array_unique( \array_merge( $sections[$section], \array_keys( $rows ) ) );
                }
            }

            foreach ( $products as $prod ) {
                foreach ( $prod['spec_labels'] as $sec_key => $sec_html ) {
                    if ( ! isset( $section_labels[$sec_key] ) ) {
                        $section_labels[$sec_key] = $sec_html;
                    }
                }
            }

            // Toolbar renders wherever the table does (previously compare-page only,
            // so the shortcode on other pages had no "Differences only" control).
            if ( \count( $products ) >= 2 ) {
                echo '<div class="aun-compare-top">';
                echo '<label class="aun-diff-toggle"><input type="checkbox" id="aun-diff-only" /> <span>Differences only</span></label>';
                echo '<button type="button" class="aun-btn aun-btn--primary" id="aun-share-compare"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> <span>Share</span></button>';
                echo '</div>';
            }

            if ( ! empty( $sections ) ) {
                echo '<nav class="aun-toc" aria-label="Jump to section">';
                foreach ( \array_keys( $sections ) as $sec ) {
                    $id_attr    = \sanitize_title( $sec );
                    $chip_label = isset( $section_labels[$sec] )
                        ? \wp_kses_post( $section_labels[$sec] )
                        : \esc_html( $sec );
                    echo '<a class="aun-chip" href="#sec-' . \esc_attr( $id_attr ) . '">' . $chip_label . '</a>';
                }
                echo '</nav>';
            }
        }

        echo '<div class="aun-compare-table-wrapper">';
        echo '<div id="aun-compare-container">';

        if ( \count( $ids ) < 2 ) {
            echo '<div class="aun-empty">';
            echo '<i class="fa-solid fa-code-compare aun-empty-icon" aria-hidden="true"></i>';
            echo '<strong style="display:block;font-size:16px;color:#0f172a;margin-bottom:6px;">Your comparison list is empty</strong>';
            echo 'Tap the &ldquo;Compare&rdquo; button on any two or three projectors, then come back here to see them side-by-side.';
            echo '<div class="aun-empty-actions">';
            echo '<a class="aun-btn aun-btn--primary" href="' . \esc_url( \home_url( '/projector-price/' ) ) . '"><i class="fa-solid fa-tags" aria-hidden="true"></i> Browse Projectors</a>';
            echo '<a class="aun-btn aun-btn--soft" href="' . \esc_url( \home_url( '/projector-finder/' ) ) . '"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Try the Smart Finder</a>';
            echo '</div>';
            echo '</div>';
        } else {
            $product_count = \count( $products );

            echo '<table class="aun-compare-table aun-compare-count-' . $product_count . '"><thead><tr>';
            echo '<th class="spec-label">Spec</th>';

            foreach ( $products as $prod ) {
                echo '<th class="product-header">';
                echo '<div class="aun-prod-hd">';
                echo '<a href="' . \esc_url( $prod['link'] ) . '">';
                echo '<img src="' . \esc_url( $prod['img'] ) . '" alt="' . \esc_attr( $prod['title'] ) . '">';
                echo '</a>';
                echo '<p class="aun-prod-name">' . \esc_html( $prod['title'] ) . '</p>';
                if ( ! empty( $prod['price'] ) ) {
                    echo '<p class="aun-prod-price">' . \wp_kses_post( $prod['price'] ) . '</p>';
                }
                echo '<a class="aun-prod-link" href="' . \esc_url( $prod['link'] ) . '" target="_blank" rel="noopener noreferrer">';
                echo 'View product&nbsp;<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>';
                echo '</a>';
                echo '</div>';
                echo '</th>';
            }

            echo '</tr></thead><tbody>';

            if ( empty( $sections ) ) {
                echo '<tr><td colspan="' . ( 1 + $product_count ) . '" class="aun-empty">';
                echo 'We couldn\'t find a tab titled like "Specification/Specs" for these products.<br>';
                echo 'Please ensure the tab title contains "Spec" and that the tab content is a two-column table.';
                echo '</td></tr>';
            } else {
                foreach ( $sections as $section => $keys ) {
                    $sec_id      = \sanitize_title( $section );
                    $sec_display = isset( $section_labels[$section] )
                        ? \wp_kses_post( $section_labels[$section] )
                        : \esc_html( $section );

                    echo '<tr class="aun-section-row" id="sec-' . \esc_attr( $sec_id ) . '">';
                    echo '<td class="section" colspan="' . ( 1 + $product_count ) . '">' . $sec_display . '</td>';
                    echo '</tr>';

                    foreach ( $keys as $key ) {
                        $vals = [];
                        foreach ( $products as $prod ) {
                            $vals[] = isset( $prod['spec'][$section][$key] )
                                ? \wp_kses_post( $prod['spec'][$section][$key] )
                                : '';
                        }

                        $diff = \count( \array_unique( \array_map( 'wp_strip_all_tags', $vals ) ) ) > 1;

                        echo '<tr' . ( $diff ? ' class="aun-diff"' : '' ) . '>';
                        echo '<td class="spec-label">' . \esc_html( $key ) . '</td>';

                        foreach ( $products as $prod ) {
                            $value = isset( $prod['spec'][$section][$key] )
                                ? \wp_kses_post( $prod['spec'][$section][$key] )
                                : '&mdash;';
                            echo '<td><div class="aun-spec-value">' . $value . '</div></td>';
                        }
                        echo '</tr>';
                    }
                }
            }

            echo '</tbody></table>';
        }

        echo '</div>';
        echo '</div>';

        // Share button: native share sheet on mobile (WhatsApp etc.), copy-link fallback
        // on desktop. The whole comparison state lives in the URL, so the link is enough.
        if ( \count( $products ) >= 2 ) {
            echo '<script data-no-optimize="1" data-no-minify="1" data-no-defer>'
                . '(function(){'
                . 'var b=document.getElementById("aun-share-compare");if(!b)return;'
                . 'var l=b.querySelector("span");'
                . 'b.addEventListener("click",function(){'
                . 'var u=window.location.href;'
                . 'if(navigator.share){navigator.share({title:document.title,url:u}).catch(function(){});return;}'
                . 'function done(){if(!l)return;var t=l.textContent;l.textContent="Link copied!";setTimeout(function(){l.textContent=t;},1800);}'
                . 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(done);}'
                . 'else{var i=document.createElement("input");i.value=u;document.body.appendChild(i);i.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(i);done();}'
                . '});'
                . '})();'
                . '</script>';
        }

        return \ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Spec retrieval — three-tier fallback
    // -------------------------------------------------------------------------

    private static function product_has_spec( $product_id ) {
        $html = self::get_custom_tab_spec_content( $product_id );
        if ( empty( $html ) ) return false;
        return ( \stripos( $html, '<table' ) !== false );
    }

    /**
     * Primary: read spec content from the wb_custom_tabs post-meta field.
     */
    private static function get_custom_tab_spec_content( $product_id ) {
        $raw = \get_post_meta( $product_id, 'wb_custom_tabs', true );
        if ( empty( $raw ) ) return '';

        $tabs  = [];
        $stack = [$raw];
        while ( $stack ) {
            $node = \array_pop( $stack );
            if ( \is_array( $node ) ) {
                $title   = isset( $node['title'] )   ? $node['title']   : ( isset( $node['tab_title'] )   ? $node['tab_title']   : null );
                $content = isset( $node['content'] ) ? $node['content'] : ( isset( $node['tab_content'] ) ? $node['tab_content'] : null );
                $slug    = isset( $node['slug'] )    ? $node['slug']    : '';
                if ( $title !== null && $content !== null ) {
                    $tabs[] = ['title' => (string) $title, 'content' => (string) $content, 'slug' => (string) $slug];
                } else {
                    foreach ( $node as $v ) { $stack[] = $v; }
                }
            }
        }

        if ( ! $tabs ) return '';

        foreach ( $tabs as $tab ) {
            $title = \mb_strtolower( $tab['title'] );
            $slug  = \mb_strtolower( $tab['slug'] );
            if ( ( $title && \strpos( $title, self::TAB_HINT ) !== false ) ||
                 ( $slug  && \strpos( $slug,  self::TAB_HINT ) !== false ) ) {
                return $tab['content'];
            }
        }
        return '';
    }

    /**
     * Secondary: apply woocommerce_product_tabs and capture the matching tab callback.
     * Globals are always restored via finally.
     */
    private static function capture_spec_via_tabs_filter( $product_id ) {
        if ( ! \function_exists( 'is_product' ) ) return '';

        global $wp_query, $post, $product;

        $old_wp_query = $wp_query;
        $old_post     = isset( $post )    ? $post    : null;
        $old_product  = isset( $product ) ? $product : null;
        $panel        = '';

        try {
            $args     = ['p' => $product_id, 'post_type' => 'product', 'post_status' => 'publish'];
            $wp_query = new \WP_Query( $args );

            if ( ! $wp_query->have_posts() ) return '';

            $wp_query->the_post();
            $post    = \get_post( $product_id );
            $product = \wc_get_product( $product_id );

            $tabs = \apply_filters( 'woocommerce_product_tabs', [] );

            if ( \is_array( $tabs ) ) {
                foreach ( $tabs as $key => $tab ) {
                    $title = isset( $tab['title'] ) ? (string) $tab['title'] : '';
                    $slug  = \is_string( $key ) ? (string) $key : '';
                    if ( self::is_spec_tab_match( $title, $slug ) ) {
                        if ( isset( $tab['callback'] ) && \is_callable( $tab['callback'] ) ) {
                            \ob_start();
                            \call_user_func( $tab['callback'], $key, $tab );
                            $panel = \ob_get_clean();
                        } elseif ( isset( $tab['content'] ) && \is_string( $tab['content'] ) ) {
                            $panel = $tab['content'];
                        }
                        break;
                    }
                }
            }
        } finally {
            \wp_reset_postdata();
            $wp_query = $old_wp_query;
            $post     = $old_post;
            $product  = $old_product;
        }

        return $panel;
    }

    /**
     * Tertiary: render all product tabs and extract the spec panel via regex.
     */
    private static function render_tabs_and_extract_spec_panel( $product_id ) {
        if ( ! \function_exists( 'woocommerce_output_product_data_tabs' ) ) return '';

        global $wp_query, $post, $product;

        $old_wp_query = $wp_query;
        $old_post     = isset( $post )    ? $post    : null;
        $old_product  = isset( $product ) ? $product : null;
        $tabs_html    = '';

        try {
            $args     = ['p' => $product_id, 'post_type' => 'product', 'post_status' => 'publish'];
            $wp_query = new \WP_Query( $args );

            if ( $wp_query->have_posts() ) {
                $wp_query->the_post();
                $post    = \get_post( $product_id );
                $product = \wc_get_product( $product_id );

                \ob_start();
                \woocommerce_output_product_data_tabs();
                $tabs_html = \ob_get_clean();
            }
        } finally {
            \wp_reset_postdata();
            $wp_query = $old_wp_query;
            $post     = $old_post;
            $product  = $old_product;
        }

        if ( empty( $tabs_html ) ) return '';

        if ( \preg_match_all( '~<a[^>]+href="#tab-([^"]+)"[^>]*>(.*?)</a>~is', $tabs_html, $m, \PREG_SET_ORDER ) ) {
            foreach ( $m as $match ) {
                $slug  = $match[1];
                $label = \trim( \wp_strip_all_tags( $match[2] ) );
                if ( \stripos( $label, self::TAB_HINT ) !== false || \stripos( $slug, self::TAB_HINT ) !== false ) {
                    if ( \preg_match( '~<div[^>]*id="tab-' . \preg_quote( $slug, '~' ) . '"[^>]*>(.*?)</div>~is', $tabs_html, $panel ) ) {
                        return $panel[1];
                    }
                }
            }
        }

        if ( \preg_match( '~<table\b[^>]*>.*?</table>~is', $tabs_html, $tbl ) ) {
            return $tbl[0];
        }

        return '';
    }

    private static function is_spec_tab_match( $title, $key ) {
        $needle = self::TAB_HINT;
        $title  = \is_string( $title ) ? \mb_strtolower( $title ) : '';
        $key    = \is_string( $key )   ? \mb_strtolower( $key )   : '';
        if ( $title && \strpos( $title, $needle ) !== false ) return true;
        if ( $key   && \strpos( $key,   $needle ) !== false ) return true;
        return false;
    }

    // -------------------------------------------------------------------------
    // Spec parser
    // -------------------------------------------------------------------------

    /**
     * Parse a WooCommerce spec table into a structured array.
     *
     * Returns:
     *   [
     *     'data'   => [ section_text => [ label => value_html, ... ], ... ],
     *     'labels' => [ section_text => section_inner_html, ... ],
     *   ]
     */
    private static function parse_spec_table( $html ) {
        $out    = [];
        $labels = [];

        if ( empty( $html ) ) return ['data' => $out, 'labels' => $labels];

        $doc = new \DOMDocument();
        \libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
        \libxml_clear_errors();
        $xpath = new \DOMXPath( $doc );

        $tables = $xpath->query( '//table' );
        if ( ! $tables || ! $tables->length ) return ['data' => $out, 'labels' => $labels];
        $table = $tables->item( 0 );

        $current_section       = 'General';
        $out[$current_section] = [];

        foreach ( $xpath->query( './/tr', $table ) as $tr ) {
            $tds = $xpath->query( './td', $tr );
            if ( ! $tds || ! $tds->length ) continue;

            if ( $tds->length === 1 || ( $tds->item(0)->hasAttribute('colspan') && (int) $tds->item(0)->getAttribute('colspan') >= 2 ) ) {
                $section_text = \trim( \preg_replace( '/\s+/', ' ', $tds->item(0)->textContent ) );
                $section_html = \trim( $tds->item(0)->C14N() );
                $section_html = \preg_replace( '~^<td[^>]*>|</td>$~i', '', $section_html );

                if ( $section_text ) {
                    $current_section = $section_text;
                    if ( ! isset( $out[$current_section] ) ) $out[$current_section] = [];
                    $labels[$current_section] = $section_html;
                }
                continue;
            }

            $label = \trim( \preg_replace( '/\s+/', ' ', $tds->item(0)->textContent ) );
            $value = \trim( $tds->item(1)->C14N() );
            $value = \preg_replace( '~^<td[^>]*>|</td>$~i', '', $value );

            if ( $label !== '' ) {
                $out[$current_section][$label] = $value;
            }
        }

        if ( isset( $out['General'] ) && empty( $out['General'] ) ) unset( $out['General'] );

        return ['data' => $out, 'labels' => $labels];
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    public static function clear_spec_cache( $post_id ) {
        \delete_transient( 'aun_spec_' . $post_id . '_' . self::CACHE_VERSION );
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public static function action_links( $links ) {
        $links[] = '<a href="' . \esc_url( \admin_url( 'post-new.php?post_type=page' ) ) . '">Create Compare Page</a>';
        return $links;
    }
}

Plugin::init();
