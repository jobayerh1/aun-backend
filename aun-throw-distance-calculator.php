<?php
/**
 * Plugin Name: AUN Throw Distance Calculator
 * Description: Interactive physics-based projection calculator with 3D room visualisation,
 *              real-world screen-size physics, human-scale reference, living-room decor,
 *              and Smart Optimal Projection Limiter. (v3.3.4 — slider hint fires on scroll-into-view;
 *              smooth glide w/ clean 0.5 ft steps; thumb ring pulses until the visitor interacts.)
 * Version:     3.3.4
 * Author:      Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Throw_Calculator {

    /** Prevents CSS from being output more than once per page load. */
    private static bool $css_done = false;

    public static function init() {
        add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'add_calculator_fields' ] );
        add_action( 'woocommerce_process_product_meta',                  [ __CLASS__, 'save_calculator_fields' ] );
        add_shortcode( 'aun_throw_calculator', [ __CLASS__, 'render_calculator' ] );
    }

    /* ═══════════════════════════════════════════════════════════
     *  ADMIN FIELDS
     * ═══════════════════════════════════════════════════════════ */

    public static function add_calculator_fields() {
        echo '<div class="options_group" style="background:#f0f9ff;border-left:4px solid #0188fe;">';

        woocommerce_wp_text_input( [
            'id'                => '_aun_throw_ratio',
            'label'             => 'Lens Throw Ratio',
            'placeholder'       => 'e.g. 1.35',
            'description'       => 'Required. Standard ≈ 1.2–1.5. Short-throw ≈ 0.5. Auto-extracted from specs if blank.',
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [ 'step' => '0.01', 'min' => '0.1', 'max' => '4.9' ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => '_aun_min_screen_size',
            'label'             => 'Optimal Min Screen (inches)',
            'placeholder'       => 'e.g. 40',
            'description'       => 'Optional: minimum screen for clean focus. Locks slider lower bound.',
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '10', 'max' => '499' ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => '_aun_max_screen_size',
            'label'             => 'Optimal Max Screen (inches)',
            'placeholder'       => 'e.g. 120',
            'description'       => 'Optional: AUN recommended maximum for peak clarity. Caps the slider.',
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [ 'step' => '1', 'min' => '30', 'max' => '500' ],
        ] );

        echo '</div>';
    }

    public static function save_calculator_fields( $post_id ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $throw_ratio = isset( $_POST['_aun_throw_ratio'] ) ? (float) $_POST['_aun_throw_ratio'] : 0;
        update_post_meta( $post_id, '_aun_throw_ratio',
            ( $throw_ratio >= 0.1 && $throw_ratio <= 4.9 ) ? $throw_ratio : '' );

        $min_size = isset( $_POST['_aun_min_screen_size'] ) ? absint( $_POST['_aun_min_screen_size'] ) : 0;
        $max_size = isset( $_POST['_aun_max_screen_size'] ) ? absint( $_POST['_aun_max_screen_size'] ) : 0;
        update_post_meta( $post_id, '_aun_min_screen_size', ( $min_size >= 10 && $min_size <= 499 ) ? $min_size : '' );
        update_post_meta( $post_id, '_aun_max_screen_size', ( $max_size >= 30 && $max_size <= 500 ) ? $max_size : '' );
    }

    /* ═══════════════════════════════════════════════════════════
     *  SPEC EXTRACTORS  ($product_id removed — was never used)
     * ═══════════════════════════════════════════════════════════ */

    private static function extract_throw_ratio( string $meta_string, string $desc ): float|false {
        $pattern = '/(?:Projection|Throw)\s*Ratio.*?([0-9\.]+):1/i';
        foreach ( [ $meta_string, $desc ] as $text ) {
            if ( preg_match( $pattern, $text, $m ) ) {
                $v = (float) $m[1];
                if ( $v > 0.1 && $v < 5.0 ) return $v;
            }
        }
        return false;
    }

    private static function extract_min_projection_distance( string $meta_string, string $desc ): float|false {
        $pattern = '/(?:Projection|Throw)\s*Distance[^\d]*([\d\.]+)/iu';
        foreach ( [ $meta_string, $desc ] as $text ) {
            if ( preg_match( $pattern, $text, $m ) ) {
                $v = (float) $m[1];
                if ( $v >= 0.1 && $v <= 10 ) return $v;
            }
        }
        return false;
    }

    private static function extract_max_screen_size( string $meta_string, string $desc ): int|false {
        $pattern = '/(?:Max Projection|Projection Size|Screen Size).*?(?:[\d\.]+\s*(?:-|to|~|–|—)\s*)?(\d{2,3})\s*(?:″|inch|\"|\')/i';
        foreach ( [ $meta_string, $desc ] as $text ) {
            if ( preg_match( $pattern, $text, $m ) ) {
                $v = (int) $m[1];
                if ( $v >= 30 && $v <= 500 ) return $v;
            }
        }
        return false;
    }

    /* ═══════════════════════════════════════════════════════════
     *  SHORTCODE
     * ═══════════════════════════════════════════════════════════ */

    public static function render_calculator() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return '';

        $product_id = $product->get_id();

        $all_meta    = get_post_meta( $product_id );
        $meta_string = html_entity_decode( wp_strip_all_tags(
            wp_json_encode( $all_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        ) );
        $desc = html_entity_decode( wp_strip_all_tags(
            $product->get_description() . ' ' . $product->get_short_description()
        ) );

        $throw_ratio = (float) get_post_meta( $product_id, '_aun_throw_ratio', true );
        if ( $throw_ratio < 0.1 ) {
            $throw_ratio = self::extract_throw_ratio( $meta_string, $desc ) ?: 1.35;
        }

        $min_size_override  = (int) get_post_meta( $product_id, '_aun_min_screen_size', true );
        $extracted_min_dist = 0;
        if ( $min_size_override <= 0 ) {
            $extracted_min_dist = self::extract_min_projection_distance( $meta_string, $desc ) ?: 0;
        }

        $max_size = (int) get_post_meta( $product_id, '_aun_max_screen_size', true );
        if ( $max_size <= 0 ) {
            $max_size = self::extract_max_screen_size( $meta_string, $desc ) ?: 0;
        }

        $default_inches = 80;
        $default_feet   = round( ( ( $default_inches / 1.1473 ) * $throw_ratio / 12 ) * 2 ) / 2;

        $uid = 'tc_' . $product_id;

        ob_start();

        /* ─── CSS (once per page) ─── */
        if ( ! self::$css_done ) {
            self::$css_done = true;
            ?>
<style data-no-optimize="1" data-no-minify="1">
/* ══ AUN Throw Distance Calculator v3.1 ══════════════════════════════════ */
.aun-tc {
    --brand:     #0188fe;
    --brand-glo: rgba(1,136,254,0.5);
    --beam-col:  rgba(56,189,248,0.35);
    font-family: inherit;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 28px 24px 22px;
    box-shadow: 0 8px 30px rgba(0,0,0,.07);
    margin: 24px 0;
}

.aun-tc-hd { text-align: center; margin-bottom: 18px; }
.aun-tc-hd h4 {
    font-size: 18px; font-weight: 800; color: #0f172a;
    margin: 0 0 4px;
    display: flex; align-items: center; justify-content: center; gap: 9px;
}
.aun-tc-hd h4 i { color: var(--brand); font-size: 19px; }
.aun-tc-hd p    { font-size: 13px; color: #64748b; margin: 0; }

/* ── Room container ──────────────────────────────────────────────────── */
.aun-tc-room {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    min-height: 240px;
    max-height: 450px;
    border-radius: 14px;
    overflow: hidden;
    /*
     * Layered background:
     *  1. Radial spotlight — warm glow near the screen area (top-centre)
     *  2. Subtle grid on back wall for texture
     * Together these create the impression of a wall the projector shines onto.
     */
    background-color: #09101e;
    background-image:
        radial-gradient(ellipse at 50% 10%, #1c3050 0%, transparent 60%),
        linear-gradient(rgba(255,255,255,.016) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.016) 1px, transparent 1px);
    background-size: 100% 100%, 28px 28px, 28px 28px;
    border: 1px solid #1c2d42;
    /*
     * Inset side-wall shadows: far cheaper than separate wall divs and looks
     * more realistic — the room "corners" are darker, giving 3-D depth.
     */
    box-shadow:
        inset 50px 0 70px rgba(0,0,0,.45),
        inset -50px 0 70px rgba(0,0,0,.45),
        inset 0 0 60px rgba(0,0,0,.4),
        0 6px 20px rgba(0,0,0,.18);
}

/* Projection-wall strip: a slightly lighter zone at the top where light hits */
.aun-tc-wall {
    position: absolute; top: 0; left: 0; right: 0; height: 24%;
    background: linear-gradient(180deg, rgba(32,52,82,0.7) 0%, transparent 100%);
    z-index: 0; pointer-events: none;
    /* Horizontal panel lines suggest a real painted wall */
    background-image:
        linear-gradient(180deg, rgba(32,52,82,0.7) 0%, transparent 100%),
        repeating-linear-gradient(180deg, transparent 0, transparent 14px,
            rgba(255,255,255,.018) 14px, rgba(255,255,255,.018) 15px);
}

/* ── Screen ─────────────────────────────────────────────────────────── */
/*
 * Width and height are set via CSS custom props (--vw / --vh) on the
 * ROOM element; both .aun-tc-screen and .aun-tc-beam inherit them.
 */
.aun-tc-screen {
    position: absolute;
    top: 8%;
    /* FIX: explicit centering — without this the screen defaults to left:0 */
    left: 50%;
    transform: translateX(-50%);
    width:  var(--vw, 55%);
    height: var(--vh, auto);
    background: linear-gradient(150deg, #ffffff 0%, #eef5ff 100%);
    border: 3px solid #2d4a6e;
    border-radius: 3px;
    z-index: 3;
    display: flex; align-items: center; justify-content: center;
    box-shadow:
        0 0 0 1px #1a3354,
        0 0 22px var(--brand-glo),
        0 0 55px rgba(1,136,254,.16),
        inset 0 0 20px rgba(255,255,255,.9);
    transition: width .14s ease-out, height .14s ease-out;
}
.aun-tc-screen-inner { text-align: center; color: #0f172a; }
.aun-tc-size { display: block; font-weight: 900; line-height: 1;
    transition: font-size .14s ease-out, color .14s;
    text-shadow: 0 1px 3px rgba(255,255,255,.7); }
.aun-tc-lbl  { font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
    color: #475569; transition: font-size .14s ease-out; margin-bottom: 2px; }
/* .aun-tc-dims deliberately REMOVED from screen — stats row is the single source of truth */

/* ── Projection beam ────────────────────────────────────────────────── */
.aun-tc-beam {
    position: absolute;
    top: 8%;
    /*
     * bottom aligns with the top of the (scaled) projector body.
     * Table 11px + projector 24px = 35px, scaled by --furn so the beam
     * always starts at the lens regardless of device size.
     */
    bottom: calc(20% + 35px * var(--furn, 1));
    left: 50%; transform: translateX(-50%);
    width: var(--vw, 55%);
    background: linear-gradient(to top, var(--beam-col) 0%, rgba(56,189,248,.01) 100%);
    clip-path: polygon(0 0, 100% 0, calc(50% + 5px) 100%, calc(50% - 5px) 100%);
    z-index: 2;
    transition: width .14s ease-out;
    pointer-events: none;
}

/* ── Floor — flat warm walnut, NO perspective (perspective causes positioning confusion) ── */
/*
 * Using a flat floor at 28% of room height.
 * ALL furniture uses bottom: 28% (or calc(28% + Npx) for stacked items).
 * JS screen cap also uses 0.28 — both stay in sync.
 */
.aun-tc-floor {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 28%;
    background:
        /* Top shadow — where floor meets wall */
        linear-gradient(180deg, rgba(0,0,0,.45) 0%, transparent 22%),
        /* Plank dividers */
        repeating-linear-gradient(90deg,
            rgba(0,0,0,.12) 0, rgba(0,0,0,.12) 1px,
            transparent 1px, transparent 46px),
        /* Subtle horizontal grain */
        repeating-linear-gradient(0deg,
            rgba(0,0,0,.05) 0, rgba(0,0,0,.05) 1px,
            transparent 1px, transparent 9px),
        /* Edge-darkening for wall corners */
        linear-gradient(to right,
            rgba(0,0,0,.25) 0%, transparent 12%,
            transparent 88%, rgba(0,0,0,.25) 100%),
        /* Base wood colour — darker near viewer (bottom), lighter at distance (top) */
        linear-gradient(0deg, #2a1205 0%, #7d4a20 100%);
    z-index: 1;
    border-top: 3px solid #1a0800;
    box-shadow: inset 0 12px 18px rgba(0,0,0,.35);
}

/* ── Coffee table (under projector) ─────────────────────────────────── */
/*
 * bottom: 20% — sits on the floor surface mid-room (floor occupies 0–28%).
 * This places the table visibly on the floor, well away from the wall edge.
 * The table is between the sofa (bottom: 2%) and the screen wall.
 */
.aun-tc-stand {
    position: absolute;
    bottom: 20%;
    left: 50%;
    transform: translateX(-50%) scale(var(--furn, 1));
    transform-origin: bottom center;
    width: 56px; height: 11px;
    background: linear-gradient(180deg, #5a6a7a 0%, #3a4a5a 100%);
    border-radius: 3px;
    z-index: 3;
    box-shadow: 0 3px 6px rgba(0,0,0,.4), inset 0 1px 1px rgba(255,255,255,.1);
}
.aun-tc-stand::before { /* legs */
    content: ''; position: absolute; bottom: -7px; left: 4px; right: 4px;
    height: 7px;
    background: linear-gradient(180deg, #3a4a5a 0%, #1e2a38 100%);
    border-radius: 0 0 3px 3px;
}

/* ── Projector body — horizontal, sits on table ─────────────────────── */
.aun-tc-proj {
    position: absolute;
    bottom: calc(20% + 11px * var(--furn, 1)); /* sits on scaled table top */
    left: 50%;
    transform: translateX(-50%) scale(var(--furn, 1));
    transform-origin: bottom center;
    width: 56px; height: 24px;
    background: linear-gradient(180deg, #8fa0b4 0%, #5a7088 50%, #3a5065 100%);
    border-radius: 4px;
    z-index: 4;
    border-top: 4px solid #1a3050;
    box-shadow: 0 4px 10px rgba(0,0,0,.55), inset 0 1px 2px rgba(255,255,255,.18);
}
/* Rear vents */
.aun-tc-proj::before {
    content: '';
    position: absolute; bottom: 4px; left: 7px; right: 7px;
    height: 7px;
    background: repeating-linear-gradient(90deg,
        rgba(0,0,0,.28) 0, rgba(0,0,0,.28) 2px,
        transparent 2px, transparent 5px);
    border-radius: 1px;
}
/* Status LED */
.aun-tc-proj::after {
    content: '';
    position: absolute; top: -3px; right: 7px;
    width: 4px; height: 4px;
    background: #4ade80;
    border-radius: 50%;
    box-shadow: 0 0 5px #4ade80, 0 0 10px rgba(74,222,128,.45);
}

/* ══════════════════════════════════════════════════════════════════════
 * SOFA — realistic rear view, drawn as a single SVG silhouette
 *
 * Rear view of a 3-seat sofa = one continuous "U" shape:
 *   a long padded back, two arm bumps rising at each end, short legs below.
 * Drawing it as ONE SVG (instead of stacked divs) keeps the silhouette
 * coherent and unmistakably sofa-shaped at any size.
 * ══════════════════════════════════════════════════════════════════════ */
.aun-tc-sofa {
    position: absolute;
    bottom: 2%;
    left: 50%;
    transform: translateX(-50%) scale(var(--furn, 1));
    transform-origin: bottom center;
    width: 46%; min-width: 130px; max-width: 240px;
    z-index: 6;
    line-height: 0;            /* kill inline-SVG whitespace gap */
    filter: drop-shadow(0 6px 10px rgba(0,0,0,.45));
}
.aun-tc-sofa svg { width: 100%; height: auto; display: block; }

/* ── Speaker (right side, sitting on floor) ─────────────────────────── */
.aun-tc-speaker {
    position: absolute;
    bottom: 2%;           /* on the floor, near the front of the room */
    right: 7%;
    transform: scale(var(--furn, 1));
    transform-origin: bottom center;
    width: 18px; height: 66px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    border-radius: 3px; z-index: 5;
    box-shadow: 2px 0 8px rgba(0,0,0,.5);
    display: flex; flex-direction: column;
    align-items: center; padding-top: 7px; gap: 5px;
}
.aun-tc-dot {
    width: 7px; height: 7px;
    background: #334155; border-radius: 50%;
    box-shadow: inset 0 1px 2px rgba(0,0,0,.5);
}

/* ── Plant (left side, sitting on floor) ────────────────────────────── */
.aun-tc-plant {
    position: absolute;
    bottom: 2%;           /* on the floor, near the front of the room */
    left: 5%; z-index: 5; width: 26px;
    transform: scale(var(--furn, 1));
    transform-origin: bottom center;
}
.aun-tc-pot {
    width: 22px; height: 14px;
    background: linear-gradient(180deg, #c2410c 0%, #9a3412 100%);
    border-radius: 2px 2px 5px 5px;
    margin: 0 auto; position: relative;
}
.aun-tc-pot::before {
    content: ''; position: absolute; top: -3px; left: -2px;
    width: 26px; height: 5px;
    background: #b45309; border-radius: 3px;
}
.aun-tc-stem {
    width: 2px; height: 40px;
    background: #15803d; margin: 0 auto; position: relative;
}
.aun-tc-stem::before {
    content: ''; position: absolute; top: 5px; left: -9px;
    width: 12px; height: 22px;
    background: linear-gradient(135deg, #16a34a, #166534);
    border-radius: 50% 0 50% 0; transform: rotate(-20deg);
}
.aun-tc-stem::after {
    content: ''; position: absolute; top: 9px; right: -10px;
    width: 12px; height: 20px;
    background: linear-gradient(135deg, #22c55e, #15803d);
    border-radius: 0 50% 0 50%; transform: rotate(18deg);
}

/* ── Stats row ──────────────────────────────────────────────────────── */
.aun-tc-stats {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 8px;
    margin: 12px 0 18px;
}
.aun-tc-stat {
    background: #f8fafc; border: 1px solid #e9eef5;
    border-radius: 10px; padding: 10px 8px 8px; text-align: center;
}
.aun-tc-stat i       { font-size: 13px; color: var(--brand); margin-bottom: 5px; display: block; }
.aun-tc-stat-val     { display: block; font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 3px; }
.aun-tc-stat-lbl     { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }

/* ── Distance display ───────────────────────────────────────────────── */
.aun-tc-dist-box  { text-align: center; margin-bottom: 10px; }
.aun-tc-dist-num  { font-size: 32px; font-weight: 900; color: var(--brand); line-height: 1; }
.aun-tc-dist-unit { font-size: 15px; font-weight: 600; color: #475569; }
.aun-tc-dist-m    { font-size: 13px; color: #94a3b8; font-weight: 500; margin-left: 5px; }
.aun-tc-dist-room { display: block; font-size: 13px; color: #64748b; font-weight: 600; margin-top: 3px; }

/* ── Slider ─────────────────────────────────────────────────────────── */
.aun-tc-ctrl { padding: 0 6px; }
.aun-tc-slider {
    -webkit-appearance: none;
    width: 100%; height: 8px; border-radius: 6px;
    outline: none; cursor: pointer; margin: 12px 0 8px;
    background: linear-gradient(to right, var(--brand) var(--fill,40%), #e2e8f0 var(--fill,40%));
}
.aun-tc-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--brand); cursor: pointer;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(1,136,254,.45);
    transition: transform .1s, box-shadow .1s;
}
.aun-tc-slider::-webkit-slider-thumb:hover { transform: scale(1.12); box-shadow: 0 3px 14px rgba(1,136,254,.6); }
.aun-tc-slider::-moz-range-thumb {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--brand); cursor: pointer;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(1,136,254,.45);
}

/* ── "It's a slider!" attention hint ─────────────────────────────────────
   A pulsing radar-ring on the thumb so non-expert visitors notice it can be
   dragged. The .is-hint class is added on load and removed on first touch
   (handled in JS); fully disabled for prefers-reduced-motion users. */
@keyframes aunTcPing {
    0%   { box-shadow: 0 2px 8px rgba(1,136,254,.45), 0 0 0 0   rgba(1,136,254,.55); }
    70%  { box-shadow: 0 2px 8px rgba(1,136,254,.45), 0 0 0 13px rgba(1,136,254,0); }
    100% { box-shadow: 0 2px 8px rgba(1,136,254,.45), 0 0 0 0   rgba(1,136,254,0); }
}
.aun-tc-slider.is-hint::-webkit-slider-thumb { animation: aunTcPing 1.6s ease-out infinite; }
.aun-tc-slider.is-hint::-moz-range-thumb     { animation: aunTcPing 1.6s ease-out infinite; }
@media (prefers-reduced-motion: reduce) {
    .aun-tc-slider.is-hint::-webkit-slider-thumb,
    .aun-tc-slider.is-hint::-moz-range-thumb { animation: none; }
}

.aun-tc-ep {
    display: flex; justify-content: space-between;
    font-size: 11.5px; color: #94a3b8; font-weight: 700;
}

/* ── Limit notice ───────────────────────────────────────────────────── */
.aun-tc-notice {
    display: none;
    align-items: flex-start; gap: 10px;
    background: #f0f9ff; border: 1px solid #bae6fd;
    border-left: 3px solid #0284c7;
    border-radius: 8px; padding: 11px 14px;
    font-size: 13px; font-weight: 500; color: #0369a1;
    margin-top: 14px; line-height: 1.55;
}
.aun-tc-notice.visible   { display: flex; }
.aun-tc-notice i         { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.aun-tc-notice strong    { color: #075985; }

.aun-tc-disc { margin-top: 16px; font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.5; font-style: italic; }

@media (max-width: 560px) {
    .aun-tc          { padding: 16px 14px 14px; border-radius: 12px; }
    .aun-tc-dist-num { font-size: 26px; }
    .aun-tc-stat-val { font-size: 15px; }
    /* sofa is NO LONGER hidden on mobile — rear-view design works at all widths */
    /* Pre-JS fallback scale so furniture isn't oversized on first paint;
       JS refines --furn to the exact room width as soon as it runs. */
    .aun-tc-room { --furn: 0.6; }
}
@media (min-width: 561px) and (max-width: 760px) {
    .aun-tc-room { --furn: 0.82; }
}
</style>
            <?php
        } // end !css_done
        ?>

<div class="aun-tc" id="<?php echo esc_attr( $uid ); ?>">

    <div class="aun-tc-hd">
        <h4><i class="fa-solid fa-ruler-combined"></i> Will it fit your room?</h4>
        <p>Drag the slider to see the exact screen size at any projector distance.</p>
    </div>

    <!-- ── 3D Room ── -->
    <div class="aun-tc-room" id="<?php echo esc_attr( "{$uid}_room" ); ?>">

        <!-- Architecture -->
        <div class="aun-tc-wall"></div>
        <!-- aun-tc-ceiling removed: was rendering as a visible dome/arch -->

        <!-- Projection beam (wide at screen top, narrow at lens) -->
        <div class="aun-tc-beam" id="<?php echo esc_attr( "{$uid}_beam" ); ?>"></div>

        <!-- Screen — diagonal and label only; width × height live in stats row -->
        <div class="aun-tc-screen" id="<?php echo esc_attr( "{$uid}_screen" ); ?>">
            <div class="aun-tc-screen-inner">
                <span class="aun-tc-size" id="<?php echo esc_attr( "{$uid}_size" ); ?>"><?php echo esc_html( (int) $default_inches ); ?>"</span>
                <span class="aun-tc-lbl"  id="<?php echo esc_attr( "{$uid}_lbl" ); ?>">Screen</span>
            </div>
        </div>

        <!-- Floor -->
        <div class="aun-tc-floor"></div>

        <!-- Projector: sits on coffee table, no lens visible from audience side -->
        <div class="aun-tc-stand"></div>
        <div class="aun-tc-proj"></div>

        <!-- Sofa: rear view drawn as a single coherent SVG silhouette -->
        <div class="aun-tc-sofa">
            <svg viewBox="0 0 240 96" xmlns="http://www.w3.org/2000/svg" aria-label="Sofa, rear view">
                <defs>
                    <linearGradient id="<?php echo esc_attr( "{$uid}_sofaFab" ); ?>" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0"   stop-color="#6f90b2"/>
                        <stop offset="0.45" stop-color="#436384"/>
                        <stop offset="1"   stop-color="#243f5c"/>
                    </linearGradient>
                    <linearGradient id="<?php echo esc_attr( "{$uid}_sofaArm" ); ?>" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0"   stop-color="#7fa0c0"/>
                        <stop offset="0.5" stop-color="#4a6a8c"/>
                        <stop offset="1"   stop-color="#284663"/>
                    </linearGradient>
                </defs>

                <!-- Legs -->
                <rect x="46"  y="82" width="10" height="12" rx="2" fill="#16222f"/>
                <rect x="184" y="82" width="10" height="12" rx="2" fill="#16222f"/>

                <!-- Seat / lower body -->
                <rect x="24" y="54" width="192" height="32" rx="10" fill="#2b486a"/>

                <!-- Arm rests (tops sit above the back) -->
                <rect x="8"   y="22" width="36" height="62" rx="16" fill="url(#<?php echo esc_attr( "{$uid}_sofaArm" ); ?>)"/>
                <rect x="196" y="22" width="36" height="62" rx="16" fill="url(#<?php echo esc_attr( "{$uid}_sofaArm" ); ?>)"/>

                <!-- Main back cushion panel -->
                <rect x="36" y="18" width="168" height="50" rx="12" fill="url(#<?php echo esc_attr( "{$uid}_sofaFab" ); ?>)"/>

                <!-- Two seams dividing the back into three cushions -->
                <line x1="92"  y1="26" x2="92"  y2="62" stroke="#1d3450" stroke-width="2" stroke-linecap="round" opacity="0.55"/>
                <line x1="148" y1="26" x2="148" y2="62" stroke="#1d3450" stroke-width="2" stroke-linecap="round" opacity="0.55"/>

                <!-- Top highlight along the back edge -->
                <rect x="38" y="19" width="164" height="3" rx="1.5" fill="#86a6c6" opacity="0.5"/>
            </svg>
        </div>

        <!-- Plant: left corner, on floor -->
        <div class="aun-tc-plant">
            <div class="aun-tc-stem"></div>
            <div class="aun-tc-pot"></div>
        </div>

        <!-- Speaker: right corner, on floor -->
        <div class="aun-tc-speaker">
            <div class="aun-tc-dot"></div>
            <div class="aun-tc-dot"></div>
            <div class="aun-tc-dot"></div>
        </div>

    </div><!-- /.aun-tc-room -->

    <!-- Stats row — single source of truth for measurements (removed from screen) -->
    <div class="aun-tc-stats">
        <div class="aun-tc-stat">
            <i class="fa-solid fa-display"></i>
            <span class="aun-tc-stat-val" id="<?php echo esc_attr( "{$uid}_s_diag" ); ?>">—</span>
            <span class="aun-tc-stat-lbl">Diagonal</span>
        </div>
        <div class="aun-tc-stat">
            <i class="fa-solid fa-arrows-left-right"></i>
            <span class="aun-tc-stat-val" id="<?php echo esc_attr( "{$uid}_s_w" ); ?>">—</span>
            <span class="aun-tc-stat-lbl">Width</span>
        </div>
        <div class="aun-tc-stat">
            <i class="fa-solid fa-arrows-up-down"></i>
            <span class="aun-tc-stat-val" id="<?php echo esc_attr( "{$uid}_s_h" ); ?>">—</span>
            <span class="aun-tc-stat-lbl">Height</span>
        </div>
    </div>

    <!-- Controls -->
    <div class="aun-tc-ctrl">
        <div class="aun-tc-dist-box">
            <span class="aun-tc-dist-num"  id="<?php echo esc_attr( "{$uid}_ft" ); ?>"><?php echo esc_html( $default_feet ); ?></span>
            <span class="aun-tc-dist-unit"> ft</span>
            <span class="aun-tc-dist-m"    id="<?php echo esc_attr( "{$uid}_m" ); ?>"></span>
            <span class="aun-tc-dist-room" id="<?php echo esc_attr( "{$uid}_rlbl" ); ?>"></span>
        </div>

        <input type="range" class="aun-tc-slider"
            id="<?php echo esc_attr( "{$uid}_slider" ); ?>"
            min="3" max="15" step="0.5"
            value="<?php echo esc_attr( $default_feet ); ?>">

        <div class="aun-tc-ep">
            <span id="<?php echo esc_attr( "{$uid}_ep_min" ); ?>"><i class="fa-solid fa-bed"></i> 3 ft</span>
            <span id="<?php echo esc_attr( "{$uid}_ep_max" ); ?>">15 ft <i class="fa-solid fa-couch"></i></span>
        </div>

        <div class="aun-tc-notice" id="<?php echo esc_attr( "{$uid}_notice" ); ?>">
            <i class="fa-solid fa-circle-info"></i>
            <div id="<?php echo esc_attr( "{$uid}_ntxt" ); ?>"></div>
        </div>
    </div>

    <p class="aun-tc-disc">
        * Estimated calculation. Actual screen size may vary slightly depending on lens focus, keystone correction, and physical placement.
    </p>
</div>

<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
(function () {
    'use strict';

    /* ── PHP → JS ──────────────────────────────────────────────────── */
    var THROW      = <?php echo floatval( $throw_ratio ); ?>;
    var MIN_OVR    = <?php echo intval( $min_size_override ); ?>;
    var MIN_DIST_M = <?php echo floatval( $extracted_min_dist ); ?>;
    var MAX_SCREEN = <?php echo intval( $max_size ); ?>;

    /*
     * ROOM_REAL_H — calculated dynamically per product so the screen NEVER stalls.
     *
     * Strategy: the room height = max_screen_height / 0.82, so the largest
     * screen this projector can produce fills 82 % of the room. Minimum 3.0 m.
     *
     * Why this fixes the stalling bug:
     *   Old code: ROOM_REAL_H = 3.0 fixed → at ~197" the screen exceeded the cap.
     *   New code: ROOM_REAL_H scales up with the product's maximum → the cap is
     *   always above the product's own maximum, so the screen can grow all the way.
     *
     * Scale comparison still works because the PERSON (1.7 m) is also computed
     * against the same ROOM_REAL_H — so for a 300" product the person is smaller
     * relative to the room than for a 100" product. This makes the size difference
     * between projectors immediately visible.
     */
    /*
     * ROOM_REAL_H is computed live inside update() from the current diagIn,
     * so the room always scales to whatever distance the user drags to.
     * No static pre-calculation here — moved into the update loop.
     */

    /* ── Scoped DOM refs ───────────────────────────────────────────── */
    var P  = '<?php echo esc_js( $uid ); ?>';
    var el = function(id) { return document.getElementById(P + '_' + id); };

    var slider   = el('slider');
    var roomEl   = el('room');
    var beamEl   = el('beam');
    var sizeEl   = el('size');
    var lblEl    = el('lbl');
    var ftEl     = el('ft');
    var mEl      = el('m');
    var rlblEl   = el('rlbl');
    var epMin    = el('ep_min');
    var epMax    = el('ep_max');
    var noticeEl = el('notice');
    var ntxtEl   = el('ntxt');
    var sDiag    = el('s_diag');
    var sW       = el('s_w');
    var sH       = el('s_h');

    if (!slider || !roomEl) return;

    /* ── Smart slider boundaries ───────────────────────────────────── */
    var minFt = 3, maxFt = 15, activeMinScreen = 0;

    if (MIN_OVR > 0) {
        activeMinScreen = MIN_OVR;
        var f = Math.ceil((((MIN_OVR / 1.1473) * THROW) / 12) * 2) / 2;
        if (f > 1) minFt = f;
    } else if (MIN_DIST_M > 0) {
        var f = Math.ceil((MIN_DIST_M * 3.28084) * 2) / 2;
        if (f > 1) {
            minFt = f;
            activeMinScreen = ((minFt * 12) / THROW) * 1.1473;
        }
    }

    if (MAX_SCREEN > 0) {
        var f = Math.floor((((MAX_SCREEN / 1.1473) * THROW) / 12) * 2) / 2;
        if (f >= minFt) maxFt = f;
    }

    slider.min = minFt;
    slider.max = maxFt;
    var cv = parseFloat(slider.value);
    if (cv < minFt) slider.value = minFt;
    if (cv > maxFt) slider.value = maxFt;

    /* ── Room label helper ─────────────────────────────────────────── */
    function roomFor(ft) {
        if (ft >= 20) return ['Large Hall',      'fa-building'];
        if (ft >= 15) return ['Conference Room', 'fa-users-viewfinder'];
        if (ft >= 10) return ['Living Room',     'fa-couch'];
        if (ft >= 6)  return ['Medium Room',     'fa-tv'];
        if (ft >= 4)  return ['Small Bedroom',   'fa-bed'];
        return             ['Desk Setup',        'fa-laptop'];
    }

    var rMin = roomFor(minFt), rMax = roomFor(maxFt);
    epMin.innerHTML = '<i class="fa-solid ' + rMin[1] + '"></i> ' + minFt + ' ft (' + rMin[0] + ')';
    epMax.innerHTML = maxFt + ' ft (' + rMax[0] + ') <i class="fa-solid ' + rMax[1] + '"></i>';

    /* ── Main update ───────────────────────────────────────────────── */
    function update() {
        var ft  = parseFloat(slider.value);
        var cH  = roomEl.clientHeight || 240;
        var cW  = roomEl.clientWidth  || 400;

        /*
         * ── FURNITURE SCALE ───────────────────────────────────────────
         * All decor (projector, table, sofa, plant, speaker) is authored at
         * a reference room width of 600px. On narrower rooms (mobile) we
         * shrink everything by the same factor so the furniture keeps the
         * same PROPORTION of the room on every device. Clamped to [0.55, 1]
         * so it never gets unreadably tiny or oversized.
         */
        var furn = Math.max(0.55, Math.min(1, cW / 600));
        roomEl.style.setProperty('--furn', furn.toFixed(3));

        /* Slider fill */
        var pct = ((ft - minFt) / (maxFt - minFt) * 100).toFixed(1);
        slider.style.setProperty('--fill', pct + '%');

        /* Distance display — round the feet to a clean 0.5 step so the demo glide
           (which uses fine sub-steps) never shows ugly values like "8.3333". */
        ftEl.textContent   = Math.round(ft * 2) / 2;
        mEl.textContent    = '(' + (ft * 0.3048).toFixed(1) + ' m)';
        rlblEl.textContent = roomFor(ft)[0];

        /* Physics */
        var diagIn = (ft * 12 / THROW) * 1.1473;

        /* Limit logic */
        var atMax = MAX_SCREEN > 0 && ft >= maxFt;
        var atMin = activeMinScreen > 0 && ft <= minFt;

        if (atMax) {
            diagIn = MAX_SCREEN;
            sizeEl.style.color = '#0369a1';
            ntxtEl.innerHTML = '<strong>Optimal Viewing Limit:</strong> AUN recommends a maximum of ' + MAX_SCREEN + '" for this model to guarantee crisp focus and peak brightness.';
            noticeEl.classList.add('visible');
        } else if (atMin) {
            diagIn = activeMinScreen;
            sizeEl.style.color = '#0f172a';
            ntxtEl.innerHTML = '<strong>Minimum Focus Limit:</strong> This is the closest distance this projector lens can cleanly focus.';
            noticeEl.classList.add('visible');
        } else {
            sizeEl.style.color = '#0f172a';
            noticeEl.classList.remove('visible');
        }

        var rounded = Math.round(diagIn);
        sizeEl.textContent = rounded + '"';

        /* Dimensions for stats row */
        var wIn = diagIn / 1.1473;
        var hIn = wIn / (16 / 9);
        var wFt = (wIn / 12).toFixed(1);
        var hFt = (hIn / 12).toFixed(1);
        sDiag.textContent = rounded + '"';
        sW.textContent    = wFt + ' ft';
        sH.textContent    = hFt + ' ft';

        /*
         * ── VISUAL SCALING — FIXED SHARED REFERENCE ───────────────────
         *
         * To make a 300" screen render exactly twice the linear size of a
         * 150" screen (and consistently across tabs / products), every
         * screen is measured against ONE shared reference diagonal that is
         * the same constant in every instance of this calculator.
         *
         *   REF_DIAG_IN  = 300"  — the largest screen we visualise; it fills
         *                  ~95% of the usable wall height.
         *   screenHpx    = (screenHm / refHm) × (usableHpx × 0.95)
         *
         * Because refHm and the 0.95 factor are identical everywhere, the
         * pixel size is a pure linear function of the screen's REAL height.
         * 150" has half the real height of 300", so it renders at half the
         * pixels — exactly what we want. This is independent of the room's
         * pixel dimensions, so it behaves identically on desktop and mobile
         * (the room just scales as a whole).
         */
        var REF_DIAG_IN = 300;                                   // shared constant, all products
        var screenHm = (diagIn      * 0.0254 / 1.1473) * (9 / 16); // this screen, real height (m)
        var refHm    = (REF_DIAG_IN  * 0.0254 / 1.1473) * (9 / 16); // reference, real height (m)

        var usableHpx = cH * (1 - 0.08 - 0.28) - 14;             // px between top margin & floor
        var usableWpx = cW * 0.92;

        var screenHpx = (screenHm / refHm) * (usableHpx * 0.95);
        var screenWpx = screenHpx * (16 / 9);

        /* Clamp to the usable wall box (real geometry can exceed it on short rooms) */
        if (screenHpx > usableHpx) { screenHpx = usableHpx; screenWpx = screenHpx * (16/9); }
        if (screenWpx > usableWpx) { screenWpx = usableWpx; screenHpx = screenWpx / (16/9); }

        /* Enforce a readable minimum */
        if (screenWpx < 46) { screenWpx = 46; screenHpx = screenWpx / (16/9); }

        /* Apply to room element (both .aun-tc-screen and .aun-tc-beam inherit) */
        roomEl.style.setProperty('--vw', screenWpx + 'px');
        roomEl.style.setProperty('--vh', screenHpx + 'px');
        beamEl.style.width = screenWpx + 'px';

        /* Responsive font sizes inside screen */
        sizeEl.style.fontSize = Math.max(14, screenWpx / 6.5) + 'px';
        lblEl.style.fontSize  = Math.max(9,  screenWpx / 20)  + 'px';
        lblEl.style.display   = screenWpx < 75  ? 'none' : 'block';

    }

    /* ── Events ────────────────────────────────────────────────────── */
    slider.addEventListener('input', update);

    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(update, 80);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update);
    } else {
        update();
    }

    /* ── First-visit "this is a slider" hint ──────────────────────────────
       Pulsing ring (CSS .is-hint) + a gentle one-time settling nudge so the
       thumb visibly slides — showing non-expert visitors the control is
       draggable AND that it changes the screen. Stops on first interaction;
       skipped entirely for prefers-reduced-motion. */
    var hintDone = false, hintOrigStep = null;
    function stopHint() {
        if (hintDone) return;
        hintDone = true;
        slider.classList.remove('is-hint');                    // stop the pulsing ring
        if (hintOrigStep !== null) slider.step = hintOrigStep; // restore normal 0.5 ft stepping
    }
    function startHint() {
        if (hintDone) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        slider.classList.add('is-hint');                       // pulsing ring

        var lo = parseFloat(slider.min), hi = parseFloat(slider.max);
        var base = parseFloat(slider.value), range = hi - lo;
        if (!(range > 0)) { setTimeout(stopHint, 9000); return; }

        /* Glide toward whichever side has more room, by a clearly-visible amount. */
        var dir  = ((hi - base) >= (base - lo)) ? 1 : -1;
        var room = (dir === 1) ? (hi - base) : (base - lo);
        var amp  = Math.min(room, range * 0.35);
        if (amp <= 0) { setTimeout(stopHint, 9000); return; }

        /* Remove 0.5 ft step-snapping during the demo so the thumb glides
           smoothly instead of jumping (or, on narrow ranges, not moving at all). */
        hintOrigStep = slider.step;
        slider.step  = 'any';

        var t0 = null, DUR = 2600;
        function frame(ts) {
            if (hintDone) return;                              // user took over — stopHint cleans up
            if (t0 === null) t0 = ts;
            var p = (ts - t0) / DUR;
            if (p >= 1) {                                      // settle back to the start value
                slider.value = base; update();
                slider.step = hintOrigStep;
                return;
            }
            /* Two decaying "pushes" toward dir — a smooth, professional settle. */
            var env = Math.abs(Math.sin(p * Math.PI * 2)) * Math.pow(1 - p, 1.3);
            slider.value = base + dir * amp * env;
            update();
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
        // The pulsing ring keeps going until the visitor actually engages the slider —
        // stopHint is bound to pointerdown/touchstart/keydown/focus/input below.
    }
    ['pointerdown', 'touchstart', 'keydown', 'focus', 'input'].forEach(function (ev) {
        slider.addEventListener(ev, stopHint, { once: true });
    });

    /* Fire the hint when the calculator SCROLLS INTO VIEW — not on page load —
       so a visitor who scrolls down to it actually sees the glide instead of
       missing it because it already played above the fold. */
    function armHint() {
        if (hintDone) return;
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    io.disconnect();
                    setTimeout(startHint, 250);                 // a beat after it enters view
                }
            }, { threshold: 0.85 });
            io.observe(slider);
        } else {
            setTimeout(startHint, 900);                         // fallback for old browsers
        }
    }
    armHint();
})();
</script>
        <?php
        return ob_get_clean();
    }
}

AUN_Throw_Calculator::init();