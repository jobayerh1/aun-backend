<?php
/**
 * Plugin Name: AUN Shorts Showcase
 * Description: Premium vertical video showcase. Supports YouTube IDs, direct MP4 URLs, and WP Media IDs.
 *              Features a Side-by-Side Feature Block, Smart Mobile Transformation, Desktop Drag-to-Scroll
 *              with Momentum Physics, and Seamless Looping. Also provides [aun_video] for a single
 *              horizontal 16:9 video with an optional title and description.
 *              (v3.8.3 — mobile "Swipe" hint no longer sits behind the title (was absolutely
 *              positioned, out of flow); and title=""/icon="false" now keeps the swipe hint,
 *              so pages with their own [aun_head] heading can drop the duplicate title.)
 * Version:     3.8.3
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AUN_Shorts_Showcase {

    /** Prevents the <script> block from being output more than once per request. */
    private static bool $scripts_printed = false;

    /** Cache for wp_get_attachment_url() — avoids a DB query per video per render. */
    private static array $url_cache = [];

    public static function init(): void {
        add_shortcode( 'aun_shorts', [ __CLASS__, 'render_shorts_shortcode' ] );
        add_shortcode( 'aun_video',  [ __CLASS__, 'render_video_shortcode' ] );  // single horizontal 16:9 video
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        // Emit the CSS via the stylesheet pipeline so WordPress prints it exactly
        // once in <head> on every front-end page. This is immune to themes (Flatsome)
        // that render the product description more than once per request — the old
        // body-inline <style> was gated by a static flag that a discarded render
        // could consume, leaving the visible page with no styling at all.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Registers a virtual (src-less) handle and attaches the plugin CSS to it.
     * wp_add_inline_style guarantees single, head-level output regardless of how
     * many times — or in what discarded context — the shortcode itself runs.
     */
    public static function enqueue_assets(): void {
        wp_register_style( 'aun-shorts-showcase', false, [], '3.8.3' );
        wp_enqueue_style( 'aun-shorts-showcase' );
        wp_add_inline_style( 'aun-shorts-showcase', self::get_css() );
    }

    /** Returns the full plugin stylesheet (no <style> wrapper). Single source of truth. */
    public static function get_css(): string {
        return <<<'CSS'
            /* Center the entire block dynamically on the page */
            .aun-shorts-wrapper { margin: 40px auto; max-width: 100%; width: max-content; }
            .aun-shorts-header { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; position: relative; }
            .aun-shorts-title-wrap { display: flex; align-items: center; justify-content: center; gap: 12px; }

            /* Mobile Swipe Hint Animation (For Gallery)
               The hint used to be position:absolute (right:20px). Being out of flow it
               reserved no space, so on narrow screens the centred nowrap title ran
               straight underneath it. It now sits IN FLOW on its own line below the
               title (header stacks), which guarantees a real gap at every width.
               !important is required on the header rules because the markup carries
               inline flex styles. */
            /* Header printed ONLY to carry the swipe hint (title="" icon="false", because the
               page supplies its own [aun_head] heading): no title, so no bottom spacing on
               desktop where the hint is hidden. */
            .aun-shorts-wrapper .aun-shorts-header.aun-header-hint-only { margin-bottom: 0 !important; }

            @media (max-width: 768px) {
                .aun-shorts-wrapper .aun-shorts-header {
                    flex-direction: column !important;
                    flex-wrap: wrap !important;
                    gap: 10px;
                }
                .aun-shorts-wrapper .aun-shorts-header.aun-header-hint-only { margin-bottom: 12px !important; }
                .aun-shorts-wrapper .aun-swipe-hint {
                    display: flex !important; position: static !important; right: auto !important;
                    align-items: center; justify-content: center; gap: 6px;
                    font-size: 13px; color: #64748b;
                    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
                    animation: aunSwipePulse 2s infinite ease-in-out !important;
                }
            }
            @keyframes aunSwipePulse {
                0%, 100% { transform: translateX(0); }
                50% { transform: translateX(6px); color: #0188fe; }
            }

            /* Smooth Scroll Container (For Gallery) */
            .aun-shorts-scroll-container {
                display: flex !important; flex-direction: row !important; gap: 20px;
                overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 20px;
                -webkit-overflow-scrolling: touch; scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent; cursor: grab; scroll-behavior: smooth;
                align-items: flex-start; pointer-events: auto !important; touch-action: pan-y;
                /* Stop the blue text/element selection highlight while dragging the slider */
                -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
                -webkit-tap-highlight-color: transparent;
            }
            .aun-shorts-scroll-container .aun-short-card,
            .aun-shorts-scroll-container .aun-short-card * {
                -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
                -webkit-tap-highlight-color: transparent;
            }
            .aun-shorts-scroll-container.aun-is-dragging {
                cursor: grabbing; scroll-snap-type: none !important; scroll-behavior: auto !important;
            }
            .aun-shorts-scroll-container.aun-is-dragging .aun-short-card { pointer-events: none; }
            .aun-shorts-scroll-container::-webkit-scrollbar { height: 8px; }
            .aun-shorts-scroll-container::-webkit-scrollbar-track { background: transparent; border-radius: 10px; }
            .aun-shorts-scroll-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; border: 2px solid #fff; }

            /* Premium Card Sizing (Standard Gallery) */
            .aun-short-card {
                flex: 0 0 auto !important; width: 280px !important; aspect-ratio: 9 / 16;
                min-height: 498px; height: 498px; scroll-snap-align: center; border-radius: 16px;
                overflow: hidden; background-color: transparent !important; box-shadow: none !important;
                border: none !important; position: relative !important; transition: transform 0.3s ease;
                box-sizing: border-box;
                /* Contain inner z-indexes (overlay, mute button) inside the card so they can't
                   paint over the theme's fixed/sticky add-to-cart bar when the page scrolls. */
                isolation: isolate;
            }

            /* Harden against wp-block-library iframe rules (load when a YouTube oEmbed is present). */
            .aun-shorts-wrapper .aun-yt-player-wrap {
                position: absolute !important; top: -1% !important; left: -1% !important;
                width: 102% !important; height: 102% !important; z-index: 1 !important;
                pointer-events: none !important;
            }
            .aun-shorts-wrapper .aun-yt-player {
                width: 100% !important; height: 100% !important; aspect-ratio: unset !important;
                pointer-events: none !important;
            }
            /* Force the YouTube-generated iframe to fill the wrapper, and CLOAK it with opacity:0
               until our onReady handler reveals it — this kills the "small black box then stretch"
               flash because the thumbnail background stays visible until the video is truly ready. */
            .aun-shorts-wrapper .aun-yt-player-wrap iframe,
            .aun-shorts-wrapper iframe.aun-yt-player {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; max-width: none !important;
                aspect-ratio: unset !important; border: 0 !important; pointer-events: none !important;
                opacity: 0; transition: opacity 0.4s ease;
            }
            .aun-shorts-wrapper .aun-yt-player-wrap iframe.aun-is-playing,
            .aun-shorts-wrapper iframe.aun-yt-player.aun-is-playing { opacity: 1 !important; }
            .aun-shorts-wrapper .aun-native-video {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; aspect-ratio: unset !important; z-index: 1 !important;
            }
            /* Overlay sits ABOVE the iframe (also placed after it in DOM order). */
            .aun-shorts-wrapper .aun-yt-overlay {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; z-index: 10 !important;
                cursor: grab !important; pointer-events: auto !important;
            }
            /* Mute button must always sit on top of everything, including the overlay. */
            .aun-shorts-wrapper .aun-mute-btn {
                z-index: 30 !important; pointer-events: auto !important; display: flex !important;
            }

            /* Native Video Styling */
            .aun-native-video {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                object-fit: cover; z-index: 1; pointer-events: none;
            }

            /* Iframe Wrappers & Overlays */
            .aun-yt-player-wrap {
                position: absolute; top: -1%; left: -1%; width: 102%; height: 102%;
                pointer-events: none; z-index: 2;
            }
            /* Opacity Cloak: Seamlessly fades video in over the thumbnail when ready */
            .aun-yt-player { width: 100%; height: 100%; border: none; opacity: 0; transition: opacity 0.4s ease; }
            .aun-yt-player.aun-is-playing { opacity: 1; }
            .aun-yt-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5; }

            /* Custom Glassmorphism Audio Button */
            .aun-mute-btn {
                position: absolute; bottom: 15px; right: 15px; width: 44px; height: 44px;
                border-radius: 50%; background: rgba(0,0,0,0.3); backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2);
                color: #ffffff; cursor: pointer; z-index: 10; display: flex; align-items: center;
                justify-content: center; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s, border-color 0.2s;
                padding: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.2); outline: none;
            }
            .aun-mute-btn .aun-vol-unmuted { display: none; }
            .aun-mute-btn.aun-unmuted .aun-vol-unmuted { display: block; }
            .aun-mute-btn.aun-unmuted .aun-vol-muted { display: none; }
            .aun-mute-btn.aun-unmuted { background: rgba(1, 136, 254, 0.85); border-color: rgba(1, 136, 254, 1); }
            .aun-mute-btn:active { transform: scale(0.92); }
            @media (hover: hover) {
                .aun-mute-btn:hover { background: rgba(0,0,0,0.6); transform: scale(1.05); }
                .aun-mute-btn.aun-unmuted:hover { background: rgba(1, 136, 254, 1); }
            }

            /* SINGLE MODE & MOBILE TRANSFORMATIONS */
            .aun-shorts-wrapper.aun-is-feature-block { width: 100%; max-width: 1000px; margin: 40px auto; }
            .aun-feat-title { margin: 0 0 15px 0; line-height: 1.2; }
            .aun-feat-desc { line-height: 1.6; margin: 0 0 20px 0; }
            .aun-feat-list { list-style: none; padding: 0; margin: 0; text-align: left; display: inline-block; }
            .aun-feat-list li { margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px; line-height: 1.4; }
            .aun-feat-list li i { color: #10b981; font-size: 18px; margin-top: 2px; }
            .aun-feat-btn { display: inline-flex !important; align-items: center; gap: 8px; }

            @media (max-width: 768px) {
                .aun-shorts-scroll-container { gap: 12px; padding-bottom: 15px; }
                .aun-shorts-wrapper:not(.aun-is-feature-block) .aun-short-card {
                    flex: 0 0 auto; width: 68vw; max-width: calc(70vh * (9/16));
                    aspect-ratio: 9 / 16; height: auto; min-height: 60vh; max-height: 70vh;
                }
                .aun-shorts-wrapper.aun-is-feature-block .aun-feature-container {
                    background: transparent; border: none; padding: 0; box-shadow: none; display: block;
                }
                .aun-shorts-wrapper.aun-is-feature-block .aun-feature-text { display: none !important; }
                .aun-shorts-wrapper.aun-is-feature-block .aun-feature-video { margin-bottom: 0; display: flex; justify-content: center; }
                .aun-shorts-wrapper.aun-is-feature-block .aun-short-card {
                    flex: 0 0 auto; width: 85vw; max-width: calc(70vh * (9/16)); margin: 0 auto;
                    border: none !important; box-shadow: none !important; height: auto; max-height: 70vh;
                }
                .aun-shorts-wrapper.aun-is-feature-block .aun-shorts-header { display: flex !important; }
            }

            @media (min-width: 769px) {
                .aun-feature-container {
                    display: flex; flex-direction: row; align-items: center; background: #f8fafc;
                    border: 1px solid #e2e8f0; border-radius: 24px; padding: 40px; gap: 50px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
                }
                .aun-feature-video { flex: 0 0 320px; display: flex; justify-content: center; margin-bottom: 0; }
                .aun-feature-video .aun-short-card {
                    width: 100%; max-width: 320px; height: auto; aspect-ratio: 9/16;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important; border: 4px solid #fff !important;
                }
                .aun-feature-text { flex: 1; text-align: left; padding: 0; }
                .aun-feat-list { display: block; }
                .aun-shorts-wrapper.aun-is-feature-block .aun-shorts-header { display: none !important; }
                /* Feature-block video now behaves like a gallery card: no YouTube controls, the
                   overlay covers the player, and the glass mute button stays visible. Give the
                   overlay a normal cursor since it isn't drag-to-scroll here. */
                .aun-shorts-wrapper.aun-is-feature-block .aun-yt-overlay { cursor: default !important; }
            }

            /* =====================================================================
               SINGLE HORIZONTAL 16:9 VIDEO — [aun_video]
               All rules are scoped under .aun-is-wide so they never touch the
               vertical gallery / feature-block markup above.
               ===================================================================== */
            .aun-shorts-wrapper.aun-is-wide { width: 100%; max-width: 920px; margin: 40px auto; }
            .aun-wide-head { max-width: 820px; margin: 0 auto 20px; text-align: center; }
            .aun-wide-title { margin: 0 0 10px; line-height: 1.25; }
            .aun-wide-desc { margin: 0; line-height: 1.65; color: #475569; }
            .aun-wide-stage { width: 100%; display: flex; justify-content: center; }
            /* Override the vertical card geometry for a centred 16:9 frame. */
            .aun-shorts-wrapper.aun-is-wide .aun-short-card {
                flex: 0 0 auto !important; width: 100% !important; max-width: 920px !important;
                height: auto !important; min-height: 0 !important; aspect-ratio: 16 / 9 !important;
                margin: 0 auto; border-radius: 16px; box-shadow: 0 18px 40px rgba(0,0,0,0.12) !important;
            }
            /* The horizontal video uses the gallery system: no YouTube controls, the overlay
               covers the player, and only the glass mute button is shown. Normal cursor (not drag). */
            .aun-shorts-wrapper.aun-is-wide .aun-yt-overlay { cursor: default !important; }
            @media (max-width: 768px) {
                .aun-shorts-wrapper.aun-is-wide { margin: 26px auto; }
                .aun-shorts-wrapper.aun-is-wide .aun-short-card { border-radius: 12px; }
            }
CSS;
    }

    public static function add_admin_menu(): void {
        add_options_page(
            'AUN Shorts Showcase Help',
            'Shorts Showcase',
            'manage_options',
            'aun-shorts-showcase',
            [ __CLASS__, 'render_help_page' ]
        );
    }

    public static function render_help_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>
                <i class="fa-brands fa-youtube" style="color:#ff0000;font-size:24px;vertical-align:middle;margin-right:8px"></i>
                AUN Shorts Showcase
            </h1>
            <p>Configure and copy shortcodes for your video showcases.</p>

            <div class="card" style="max-width:800px;margin-bottom:20px;padding:20px">
                <h2 style="margin-top:0;color:#16a34a">Method 1: Standard gallery (multiple videos)</h2>
                <p>Add multiple video IDs separated by commas. Creates a side-scrolling gallery.</p>
                <code>[aun_shorts ids="8538, 8539, 8540"]</code>
            </div>

            <div class="card" style="max-width:800px;margin-bottom:20px;padding:20px;border-left:4px solid #0188fe;background:#f6fbff">
                <h2 style="margin-top:0;color:#0188fe">⭐ Recommended on product pages (no duplicate heading)</h2>
                <p>Our product pages already print a section header with
                   <code>[aun_head eyebrow="See it live" title="Watch It In Action" …]</code>.
                   If the shortcode also prints its own title you get <strong>the same heading twice</strong>.
                   So on those pages hide the shortcode's title and icon:</p>
                <textarea readonly onclick="this.select()" rows="2"
                    style="width:100%;font-family:monospace;font-size:13px;padding:10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff">[aun_shorts ids="8538, 8539, 8540" title="" icon="false"]</textarea>
                <p style="margin:12px 0 0;color:#1e5a8a">
                    <strong>The mobile “Swipe →” hint still appears</strong> even with the title hidden —
                    it is kept on purpose so the swipe affordance is never lost (v3.8.3).
                </p>
                <p style="margin:8px 0 0;color:#64748b">
                    Use the full <code>title</code> only on a page that has <em>no</em> <code>[aun_head]</code> above the videos.
                </p>
            </div>

            <div class="card" style="max-width:800px;margin-bottom:20px;padding:20px">
                <h2 style="margin-top:0;color:#0188fe">Method 2: Side-by-side feature block (single video)</h2>
                <p>One video ID automatically becomes a premium side-by-side layout on desktop.</p>
                <ul>
                    <li><code>feature_title</code> — the bold heading next to the video.</li>
                    <li><code>feature_desc</code> — the paragraph explaining the video.</li>
                    <li><code>features</code> — comma-separated list; renders as a checkmark list.</li>
                    <li><code>btn_text</code> &amp; <code>btn_link</code> — optional call-to-action button.</li>
                </ul>
                <code style="display:block;white-space:pre-wrap;line-height:1.6">[aun_shorts ids="8538"
feature_title="Experience the Clarity"
feature_desc="Watch our detailed hands-on test."
features="Native 1080p Resolution, Bright &amp; Vivid Picture, Seamless Android TV"
btn_text="Buy Now" btn_link="#"]</code>
            </div>

            <div class="card" style="max-width:800px;margin-bottom:20px;padding:20px">
                <h2 style="margin-top:0;color:#8b5cf6">Method 3: Single horizontal 16:9 video</h2>
                <p>For products with a <strong>landscape (16:9)</strong> video instead of a vertical short. This uses a
                   <strong>separate shortcode</strong> — <code>[aun_video]</code> — and shows just an optional title and
                   description above a centred wide video that plays with the player's own controls.</p>
                <ul>
                    <li><code>id</code> — one source only: a WP Media ID, an MP4/WebM/MOV URL, or an 11-character YouTube ID.</li>
                    <li><code>title</code> — optional heading shown above the video.</li>
                    <li><code>desc</code> — optional description shown under the heading.</li>
                </ul>
                <code style="display:block;white-space:pre-wrap;line-height:1.6">[aun_video id="dQw4w9WgXcQ"
title="Hands-On Walkthrough"
desc="See the full setup and picture quality in our 4K hands-on review."]</code>
                <p style="margin:12px 0 0;color:#64748b"><strong>Tip:</strong> both texts are optional — <code>[aun_video id="8541"]</code> on its own simply shows the video. Use this for horizontal videos; use <code>[aun_shorts]</code> above for vertical ones.</p>
            </div>

            <div class="card" style="max-width:800px;padding:20px">
                <h2 style="margin-top:0;color:#f59e0b">Global customisation attributes</h2>
                <ul>
                    <li><code>title</code> — gallery heading (default <code>WATCH IT IN ACTION</code>). Set <code>title=""</code> to hide it — do this whenever an <code>[aun_head]</code> heading sits above the videos.</li>
                    <li><code>icon</code> — set <code>icon="false"</code> to hide the YouTube icon.</li>
                    <li><code>audio</code> — set <code>audio="false"</code> to hide the mute button.</li>
                </ul>
                <p><strong>Example — silent loop with no title or icons:</strong></p>
                <code>[aun_shorts ids="8538, 8539" title="" icon="false" audio="false"]</code>
                <p style="margin:12px 0 0;color:#64748b">Hiding the title and icon never removes the mobile
                   <strong>“Swipe →”</strong> hint on a multi-video gallery — it is always kept.</p>
            </div>
        </div>
        <?php
    }

    public static function render_shorts_shortcode($atts) {
        $atts = shortcode_atts( [
            'ids'           => '',
            'title'         => 'WATCH IT IN ACTION',
            'icon'          => 'true',
            'audio'         => 'true',
            'feature_title' => 'Why Choose This Model?',
            'feature_desc'  => 'Watch our raw, unedited footage to see exactly how this projector transforms any room into a cinematic experience.',
            'features'      => 'Crystal Clear Focus, Ultra-Bright Lamp, Seamless Smartphone Casting',
            'btn_text'      => '',
            'btn_link'      => '#',
        ], $atts, 'aun_shorts' );

        if (empty($atts['ids'])) return '';

        // Clean up IDs and remove any empty array elements
        $video_ids = array_filter(array_map('trim', explode(',', $atts['ids'])));
        $video_count = count($video_ids);
        if ($video_count === 0) return '';

        $unique_instance = uniqid('aun_shorts_');
        
        $show_title = !empty($atts['title']);
        $show_icon = ($atts['icon'] === 'true');
        $show_audio = ($atts['audio'] === 'true');
        $show_header = ($show_title || $show_icon);
        
        // Smart Detection: Checks if there is exactly 1 video to apply the side-by-side feature block
        $is_single = ($video_count === 1);
        $single_class = $is_single ? 'aun-is-feature-block' : '';

        ob_start();
        ?>
        <div class="aun-shorts-wrapper <?php echo esc_attr($single_class); ?>" id="<?php echo esc_attr($unique_instance); ?>">
            
            <?php
            // The gallery's mobile swipe hint lives in this header. When the page already
            // supplies its own section heading (e.g. [aun_head]) the shortcode is used with
            // title="" icon="false" — so still print the header for the hint alone, otherwise
            // hiding the title would silently remove the swipe affordance too.
            $hint_only = ( ! $show_header && ! $is_single );
            ?>
            <?php if ($show_header || $hint_only): ?>
            <!-- Standard Header: Always printed, but hidden via CSS on Desktop Single Mode -->
            <div class="aun-shorts-header<?php echo $hint_only ? ' aun-header-hint-only' : ''; ?>" style="display:flex;align-items:center;justify-content:center;margin-bottom:20px;position:relative;flex-wrap:nowrap;">
                <?php if ($show_header): ?>
                <div class="aun-shorts-title-wrap" style="display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:nowrap;">
                    <?php if ($show_icon): ?>
                        <i class="fa-brands fa-youtube" style="color:#ff0000;font-size:32px;line-height:1;flex-shrink:0;"></i>
                    <?php endif; ?>

                    <?php if ($show_title): ?>
                        <h3 style="margin:0;padding:0;white-space:nowrap;line-height:1.2;"><?php echo esc_html($atts['title']); ?></h3>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!$is_single): ?>
                <!-- Swipe hint: hidden on desktop via inline style (beats any theme CSS),
                     shown on mobile by media query override. -->
                <div class="aun-swipe-hint" style="display:none">
                    <span>Swipe</span>
                    <i class="fa-solid fa-arrow-right-long"></i>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($is_single): ?>
                <!-- START: SIDE-BY-SIDE FEATURE BLOCK -->
                <div class="aun-feature-container">
            <?php endif; ?>

            <div class="<?php echo $is_single ? 'aun-feature-video' : 'aun-shorts-scroll-container'; ?>"<?php if(!$is_single): ?> style="display:flex !important;flex-direction:row !important;flex-wrap:nowrap !important;gap:20px;overflow-x:auto;overflow-y:hidden;align-items:flex-start;max-width:100%;"<?php endif; ?>>
                <?php foreach ($video_ids as $index => $id): 
                    if (empty($id)) continue;
                    $player_id = $unique_instance . '_vid_' . $index;
                    $card_id   = 'card_' . $player_id;

                    // Smart detection: WP Media ID (numeric), direct MP4/WebM URL, or YouTube ID.
                    $is_wp_media      = is_numeric( $id );
                    $final_video_url  = '';
                    $is_native        = false;

                    if ( $is_wp_media ) {
                        // Cache attachment URL to avoid one DB query per video per render.
                        $int_id = intval( $id );
                        if ( ! isset( self::$url_cache[ $int_id ] ) ) {
                            self::$url_cache[ $int_id ] = wp_get_attachment_url( $int_id );
                        }
                        $final_video_url = self::$url_cache[ $int_id ];
                        if ( $final_video_url ) {
                            $is_native = true;
                        } else {
                            continue; // Invalid media ID — skip silently
                        }
                    } elseif ( strpos( $id, 'http' ) === 0 ) {
                        // Only treat external URLs as native if they are MP4/WebM/MOV files.
                        // This prevents Vimeo or other non-video URLs from being embedded in <video>.
                        $ext = strtolower( pathinfo( wp_parse_url( $id, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
                        if ( in_array( $ext, [ 'mp4', 'webm', 'mov' ], true ) ) {
                            $final_video_url = esc_url_raw( $id );
                            $is_native       = true;
                        } else {
                            continue; // Unsupported URL type — skip
                        }
                    } else {
                        // Treat as YouTube ID — validate format to prevent CSS injection via bg-image.
                        if ( ! preg_match( '/^[A-Za-z0-9_\-]{11}$/', $id ) ) {
                            continue; // Invalid YouTube ID — skip
                        }
                        $is_native = false;
                    }

                    // YouTube thumbnail background (only for validated YouTube IDs)
                    $bg_style = '';
                    if ( ! $is_native ) {
                        $bg_style = 'background-image:url(\'https://i.ytimg.com/vi/' . esc_attr( $id ) . '/hqdefault.jpg\');background-size:cover;background-position:center 25%;';
                    }
                ?>
                    <div class="aun-short-card"
                         style="width:280px;height:498px;min-height:498px;position:relative;flex:0 0 auto;overflow:hidden;border-radius:16px;box-sizing:border-box;<?php echo $bg_style; ?>"
                         id="<?php echo esc_attr($card_id); ?>">

                        <?php if ( $show_audio ) : ?>
                        <button class="aun-mute-btn"
                                data-pid="<?php echo esc_attr( $player_id ); ?>"
                                data-native="<?php echo $is_native ? '1' : '0'; ?>"
                                aria-label="Toggle sound">
                            <i class="aun-vol-muted fa-solid fa-volume-xmark" style="font-size:18px"></i>
                            <i class="aun-vol-unmuted fa-solid fa-volume-high" style="font-size:18px"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($is_native): ?>
                            <!-- NATIVE HTML5 VIDEO -->
                            <video id="<?php echo esc_attr($player_id); ?>" class="aun-native-video" src="<?php echo esc_url($final_video_url); ?>" loop muted playsinline preload="metadata"></video>
                        <?php else: ?>
                            <!-- YOUTUBE IFRAME FALLBACK -->
                            <!-- Player wrap FIRST, overlay AFTER so overlay is always on top in DOM order -->
                            <div class="aun-yt-player-wrap">
                                <div class="aun-yt-player" data-vid="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($player_id); ?>"></div>
                            </div>
                            <div class="aun-yt-overlay"></div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div> <!-- End video container -->

            <?php if ($is_single): ?>
                    <!-- Text Side of the Feature Block -->
                    <div class="aun-feature-text">
                        <?php if (!empty($atts['feature_title'])): ?>
                            <h3 class="aun-feat-title"><?php echo esc_html($atts['feature_title']); ?></h3>
                        <?php endif; ?>
                        
                        <?php if (!empty($atts['feature_desc'])): ?>
                            <p class="aun-feat-desc"><?php echo wp_kses_post($atts['feature_desc']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($atts['features'])): ?>
                            <ul class="aun-feat-list">
                                <?php 
                                $feature_array = explode(',', $atts['features']);
                                foreach ($feature_array as $feat) {
                                    $feat = trim($feat);
                                    if (!empty($feat)) {
                                        echo '<li><i class="fa-solid fa-circle-check"></i> <span>' . esc_html($feat) . '</span></li>';
                                    }
                                }
                                ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($atts['btn_text'])): ?>
                            <div style="margin-top: 25px;">
                                <!-- Uses Flatsome Native Button Classes -->
                                <a href="<?php echo esc_url($atts['btn_link']); ?>" class="button primary aun-feat-btn" style="border-radius: 8px; margin-bottom: 0;">
                                    <span><?php echo esc_html($atts['btn_text']); ?> <i class="fa-solid fa-arrow-right"></i></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div> <!-- END aun-feature-container -->
            <?php endif; ?>

        </div> <!-- END wrapper -->


        <script>
            if (typeof window.aunYtPlayers === 'undefined') {
                window.aunYtPlayers = {};
                window.aunYtApiLoaded = false;
            }

            // Wrap in IIFE + readyState check so init runs even if a JS optimizer
            // defers this script past the DOMContentLoaded event firing.
            (function() {
                function _aunInit() {
                var wrapper = document.getElementById('<?php echo esc_js($unique_instance); ?>');
                if (!wrapper) return;

                // --- JS REINFORCEMENT: force visibility of elements that CSS alone may not reach ---

                // 1. Mute buttons: force display regardless of theme/block-library button resets
                wrapper.querySelectorAll('.aun-mute-btn').forEach(function(btn) {
                    btn.style.setProperty('display',     'flex',    'important');
                    btn.style.setProperty('visibility',  'visible', 'important');
                    btn.style.setProperty('opacity',     '1',       'important');
                });

                // 2. Swipe hint: show on mobile, hide on desktop — JS fallback for when the
                //    media-query CSS is stripped or reordered by an optimizer plugin.
                (function() {
                    var hint = wrapper.querySelector('.aun-swipe-hint');
                    if (!hint) return;
                    var header = wrapper.querySelector('.aun-shorts-header');
                    function _checkHint() {
                        if (window.innerWidth <= 768) {
                            // Keep the hint IN FLOW below the title (it used to be absolute,
                            // which let the centred title overlap it). Must mirror the CSS —
                            // these inline !important rules outrank the stylesheet.
                            if (header) {
                                header.style.setProperty('flex-direction', 'column', 'important');
                                header.style.setProperty('flex-wrap',      'wrap',   'important');
                                header.style.setProperty('gap',            '10px',   'important');
                            }
                            hint.style.setProperty('display',         'flex',    'important');
                            hint.style.setProperty('position',        'static',  'important');
                            hint.style.setProperty('right',           'auto',    'important');
                            hint.style.setProperty('align-items',     'center',  'important');
                            hint.style.setProperty('justify-content', 'center',  'important');
                            hint.style.setProperty('gap',             '6px',     'important');
                            hint.style.setProperty('font-size',       '13px',    'important');
                            hint.style.setProperty('color',           '#64748b', 'important');
                            hint.style.setProperty('font-weight',     '700',     'important');
                        } else {
                            if (header) {
                                // Restore the desktop row layout explicitly (the markup's own
                                // inline flex-wrap:nowrap would be lost by removeProperty).
                                header.style.setProperty('flex-direction', 'row',    'important');
                                header.style.setProperty('flex-wrap',      'nowrap', 'important');
                                header.style.removeProperty('gap');
                            }
                            hint.style.setProperty('display', 'none', 'important');
                        }
                    }
                    _checkHint();
                    window.addEventListener('resize', _checkHint);
                })();

                // Smart Detection for Desktop Single Layout — rechecked on resize
                var isSingleDesktop = wrapper.classList.contains('aun-is-feature-block') && window.innerWidth >= 769;

                // Recompute on resize so rotating from mobile → desktop activates controls
                var resizeTimer;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        isSingleDesktop = wrapper.classList.contains('aun-is-feature-block') && window.innerWidth >= 769;
                    }, 150);
                });

                // 1. Play/Pause Observer (Handles both Native Video and YT API)
                var playPauseObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        
                        // Native Video
                        var nativeVid = entry.target.querySelector('video.aun-native-video');
                        if (nativeVid) {
                            if (entry.isIntersecting) {
                                nativeVid.play().catch(function(err){ console.log("Autoplay prevented", err); });
                            } else {
                                nativeVid.pause();
                            }
                            return; // Skip YT Logic
                        }

                        // YouTube Iframe
                        var playerEl = entry.target.querySelector('iframe'); 
                        if (!playerEl) return;
                        
                        var pid = playerEl.id;
                        var player = window.aunYtPlayers[pid];
                        
                        if (player && typeof player.playVideo === 'function') {
                            if (entry.isIntersecting) {
                                // Only autoplay if it wasn't manually paused by the user (desktop controls check)
                                player.playVideo();
                            } else {
                                player.pauseVideo();
                            }
                        }
                    });
                }, { 
                    root: null,
                    threshold: 0.6
                });

                // Immediately observe any Native HTML5 videos. (No native controls are added —
                // the feature block now matches the gallery: clean looping video + glass mute button.)
                var nativeCards = wrapper.querySelectorAll('.aun-short-card:has(.aun-native-video), .aun-native-video');
                nativeCards.forEach(function(card) {
                    var actualCard = card.classList.contains('aun-short-card') ? card : card.closest('.aun-short-card');
                    if (actualCard) playPauseObserver.observe(actualCard);
                });

                // Desktop Drag-to-Scroll Logic (Modern Pointer Events with Momentum Physics)
                var scrollContainer = wrapper.querySelector('.aun-shorts-scroll-container');
                if (scrollContainer && !isSingleDesktop) {
                    // Reinforce key styles via JS — overrides any theme/block-library rule that
                    // strips pointer-events or cursor from our scroll container.
                    scrollContainer.style.setProperty('pointer-events', 'auto',  'important');
                    scrollContainer.style.setProperty('touch-action',   'pan-y', '');
                    scrollContainer.style.cursor = 'grab';
                    var isDown = false;
                    var startX;
                    var scrollLeft;
                    var isActualDrag = false;
                    var velX = 0;
                    var momentumID;
                    var lastX;
                    var lastTime;

                    scrollContainer.addEventListener('pointerdown', function(e) {
                        // Prevent drag logic if the user is explicitly clicking the volume button
                        if (e.target.closest('.aun-mute-btn')) return;

                        isDown = true;
                        isActualDrag = false;
                        startX = e.pageX - scrollContainer.offsetLeft;
                        scrollLeft = scrollContainer.scrollLeft;
                        
                        // Stop any existing momentum
                        cancelAnimationFrame(momentumID);
                        velX = 0;
                        lastX = e.pageX;
                        lastTime = Date.now();
                        
                        // Force capture pointer so releasing mouse outside container still stops the drag reliably
                        try { scrollContainer.setPointerCapture(e.pointerId); } catch(err) {}
                    });
                    
                    scrollContainer.addEventListener('pointermove', function(e) {
                        if (!isDown) return;
                        
                        var x = e.pageX - scrollContainer.offsetLeft;
                        var walk = (x - startX) * 1.5; // Drag speed multiplier
                        
                        // Calculate velocity for momentum
                        var now = Date.now();
                        var dt = now - lastTime;
                        if (dt > 0) {
                            velX = (lastX - e.pageX) * 1.5;
                            lastX = e.pageX;
                            lastTime = now;
                        }
                        
                        // Only engage drag mode if moved more than 5 pixels (distinguishes clicks from drags)
                        if (Math.abs(walk) > 5) {
                            if (!isActualDrag) {
                                isActualDrag = true;
                                scrollContainer.classList.add('aun-is-dragging');
                                scrollContainer.style.cursor = 'grabbing';
                            }
                            e.preventDefault();
                            scrollContainer.scrollLeft = scrollLeft - walk;
                        }
                    });

                    // Momentum Physics Engine
                    function applyMomentum() {
                        if (!isDown) {
                            scrollContainer.scrollLeft += velX;
                            velX *= 0.92; // Friction (adjusts the butteriness of the glide)

                            if (Math.abs(velX) > 0.5) {
                                momentumID = requestAnimationFrame(applyMomentum);
                            } else {
                                // Once almost stopped, let CSS native smooth snapping take over the final pixel alignment
                                scrollContainer.classList.remove('aun-is-dragging');
                                scrollContainer.style.cursor = 'grab';
                            }
                        }
                    }

                    var endDrag = function(e) {
                        if (!isDown) return;
                        isDown = false;
                        try { scrollContainer.releasePointerCapture(e.pointerId); } catch(err) {}
                        
                        if (isActualDrag) {
                            // If the user held the mouse still for 50ms before releasing, kill the momentum
                            if (Date.now() - lastTime > 50) {
                                velX = 0;
                            }
                            momentumID = requestAnimationFrame(applyMomentum);
                        } else {
                            scrollContainer.classList.remove('aun-is-dragging');
                            scrollContainer.style.cursor = 'grab';
                        }
                    };
                    
                    scrollContainer.addEventListener('pointerup', endDrag);
                    scrollContainer.addEventListener('pointercancel', endDrag);
                }

                // 2. Setup the initialization function for YouTube
                var aunPlayersInitialized = false;
                function initLocalPlayers() {
                    // Guard: never run the full init more than once per instance.
                    if (aunPlayersInitialized) return;
                    if (typeof YT === 'undefined' || !YT || typeof YT.Player !== 'function') return;
                    aunPlayersInitialized = true;

                    var playerEls = wrapper.querySelectorAll('.aun-yt-player');
                    playerEls.forEach(function(el) {
                        var vid = el.getAttribute('data-vid');
                        var pid = el.id;
                        // Skip if this element was already turned into a player.
                        if (window.aunYtPlayers[pid]) return;
                        
                        // Never show YouTube's own controls — the feature block and gallery both use
                        // the clean looping video + custom glass mute button (no timeline flash on start).
                        var showControls = 0;
                        
                        window.aunYtPlayers[pid] = new YT.Player(pid, {
                            videoId: vid,
                            playerVars: {
                                autoplay: 0,        
                                controls: showControls,        
                                disablekb: showControls ? 0 : 1,       
                                fs: showControls ? 1 : 0,              
                                iv_load_policy: 3,  
                                modestbranding: 1,  
                                rel: 0,             
                                playsinline: 1,
                                mute: 1,
                                loop: 0,
                            },
                            events: {
                                onReady: function(e) {
                                    e.target.mute();
                                    var iframe = e.target.getIframe();
                                    if (iframe) {
                                        // Re-add class (YouTube drops it when replacing the div).
                                        iframe.classList.add('aun-yt-player');
                                        iframe.classList.add('aun-is-playing');
                                        // Force fill the card via inline styles — this beats any
                                        // wp-block-library or theme CSS that constrains iframes.
                                        iframe.style.setProperty('position',   'absolute',  'important');
                                        iframe.style.setProperty('top',        '0',         'important');
                                        iframe.style.setProperty('left',       '0',         'important');
                                        iframe.style.setProperty('width',      '100%',      'important');
                                        iframe.style.setProperty('height',     '100%',      'important');
                                        iframe.style.setProperty('max-width',  'none',      'important');
                                        iframe.style.setProperty('aspect-ratio','unset',    'important');
                                        iframe.style.setProperty('border',     '0',         'important');
                                        iframe.style.setProperty('opacity',    '1',         'important');
                                        iframe.style.setProperty('pointer-events', 'none',  'important');
                                    }
                                    var card = document.getElementById(pid) ? document.getElementById(pid).closest('.aun-short-card') : null;
                                    if (!card && iframe) card = iframe.closest('.aun-short-card');
                                    if (card) playPauseObserver.observe(card);
                                },
                                onStateChange: function(e) {
                                    var iframe = e.target.getIframe();
                                    if (!iframe) return;

                                    if (e.data === YT.PlayerState.PLAYING) {
                                        iframe.classList.add('aun-is-playing');
                                        iframe.style.opacity = '1';
                                    }
                                    
                                    // Seamless loop: seek back to 0 the moment it ends,
                                    // preventing YouTube's end-screen overlay from flashing.
                                    if (e.data === YT.PlayerState.ENDED) {
                                        e.target.seekTo(0);
                                        e.target.playVideo();
                                    }
                                }
                            }
                        });
                    });
                }

                // 3. Robust Global YouTube Loading System
                function loadYoutubeApi() {
                    if (wrapper.querySelectorAll('.aun-yt-player').length === 0) return; // Don't load API if only using MP4s

                    // Case A: API fully ready right now → init immediately.
                    if (typeof YT !== 'undefined' && YT && typeof YT.Player === 'function') {
                        initLocalPlayers();
                        return;
                    }

                    // Case B: The YT object exists but Player isn't ready yet (this happens
                    // when WordPress's own YouTube oEmbed loaded the API script before us).
                    // YT.ready() is YouTube's official callback queue — it fires for every
                    // caller regardless of who loaded the script or how many times.
                    if (typeof YT !== 'undefined' && YT && typeof YT.ready === 'function') {
                        YT.ready(initLocalPlayers);
                        return;
                    }

                    // Case C: API not present at all. Listen for our bridged event, and
                    // also poll as a safety net in case another plugin's oEmbed consumed
                    // the onYouTubeIframeAPIReady callback before we attached to it.
                    document.addEventListener('aunYtApiReady', initLocalPlayers);

                    if (!window.aunYtApiLoaded) {
                        window.aunYtApiLoaded = true;

                        var oldCb = window.onYouTubeIframeAPIReady;
                        window.onYouTubeIframeAPIReady = function() {
                            if (typeof oldCb === 'function') { oldCb(); }
                            document.dispatchEvent(new Event('aunYtApiReady'));
                        };

                        // Only inject the script if no one else already has.
                        if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
                            var tag = document.createElement('script');
                            tag.src = "https://www.youtube.com/iframe_api";
                            tag.async = true;
                            var firstScriptTag = document.getElementsByTagName('script')[0];
                            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                        }
                    }

                    // Safety-net poll: if the API becomes ready but our event never fired
                    // (because another plugin's oEmbed overwrote the callback before us),
                    // detect it directly. Gives up after ~10 seconds.
                    var tries = 0;
                    var poll = setInterval(function() {
                        tries++;
                        if (typeof YT !== 'undefined' && YT && typeof YT.Player === 'function') {
                            clearInterval(poll);
                            initLocalPlayers();
                        } else if (tries > 50) {
                            clearInterval(poll);
                        }
                    }, 200);
                }

                // 4. Lazy-Load Observer for YouTube
                var lazyObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            loadYoutubeApi();
                            lazyObserver.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '400px' }); 

                lazyObserver.observe(wrapper);
                } // end _aunInit

                // Run immediately if DOM is already ready (handles deferred/async script injection);
                // otherwise wait for DOMContentLoaded as normal.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', _aunInit);
                } else {
                    _aunInit();
                }
            })();

            // Mute button handler — uses data-pid/data-native attributes instead of inline onclick.
            // This avoids inline JS event handlers which are an XSS risk if pid is ever tainted.
            if (typeof window.aunToggleMute !== 'function') {
                window.aunToggleMute = function(btn, pid, isNative) {
                    if (isNative) {
                        var video = document.getElementById(pid);
                        if (video) {
                            video.muted = !video.muted;
                            btn.classList.toggle('aun-unmuted', !video.muted);
                        }
                    } else {
                        var player = window.aunYtPlayers[pid];
                        if (player && typeof player.isMuted === 'function') {
                            if (player.isMuted()) {
                                player.unMute();
                                btn.classList.add('aun-unmuted');
                            } else {
                                player.mute();
                                btn.classList.remove('aun-unmuted');
                            }
                        }
                    }
                };

                // Delegated listener — handles all mute buttons via data attributes,
                // no inline onclick needed on any element.
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.aun-mute-btn');
                    if (!btn) return;
                    var pid      = btn.getAttribute('data-pid');
                    var isNative = btn.getAttribute('data-native') === '1';
                    window.aunToggleMute(btn, pid, isNative);
                });
            }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * [aun_video] — a single HORIZONTAL 16:9 video with an optional title + description.
     * Deliberately minimal (no feature list / button): just the heading and the video, centred.
     * Accepts the same sources as the gallery: WP media ID, MP4/WebM/MOV URL, or 11-char YouTube ID.
     * Uses the SAME system as the gallery: YouTube's own controls are hidden and only the glass
     * mute button is shown; the video autoplays muted on scroll-in and loops. audio="false" hides it.
     */
    public static function render_video_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id'    => '',
            'title' => '',
            'desc'  => '',
            'audio' => 'true',
        ], $atts, 'aun_video' );

        $id = trim( (string) $atts['id'] );
        if ( $id === '' ) return '';

        // Source detection — identical rules to the gallery card.
        $is_native = false;
        $final_url = '';
        if ( is_numeric( $id ) ) {
            $int = intval( $id );
            if ( ! isset( self::$url_cache[ $int ] ) ) {
                self::$url_cache[ $int ] = wp_get_attachment_url( $int );
            }
            $final_url = self::$url_cache[ $int ];
            if ( ! $final_url ) return '';      // invalid media ID
            $is_native = true;
        } elseif ( strpos( $id, 'http' ) === 0 ) {
            $ext = strtolower( pathinfo( wp_parse_url( $id, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, [ 'mp4', 'webm', 'mov' ], true ) ) return '';
            $final_url = esc_url_raw( $id );
            $is_native = true;
        } elseif ( ! preg_match( '/^[A-Za-z0-9_\-]{11}$/', $id ) ) {
            return '';                          // not a valid YouTube ID
        }

        $instance   = uniqid( 'aun_video_' );
        $player_id  = $instance . '_vid';
        $card_id    = 'card_' . $player_id;
        $show_audio = ( $atts['audio'] === 'true' );

        $bg_style = '';
        if ( ! $is_native ) {
            $bg_style = "background-image:url('https://i.ytimg.com/vi/" . esc_attr( $id ) . "/hqdefault.jpg');background-size:cover;background-position:center;";
        }

        ob_start();
        ?>
        <div class="aun-shorts-wrapper aun-is-wide" id="<?php echo esc_attr( $instance ); ?>">
            <?php if ( ! empty( $atts['title'] ) || ! empty( $atts['desc'] ) ) : ?>
            <div class="aun-wide-head">
                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <h3 class="aun-wide-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                <?php endif; ?>
                <?php if ( ! empty( $atts['desc'] ) ) : ?>
                    <p class="aun-wide-desc"><?php echo wp_kses_post( $atts['desc'] ); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="aun-wide-stage">
                <div class="aun-short-card aun-wide-card" id="<?php echo esc_attr( $card_id ); ?>"
                     style="position:relative;overflow:hidden;border-radius:16px;box-sizing:border-box;<?php echo $bg_style; ?>">
                    <?php if ( $show_audio ) : ?>
                    <button class="aun-mute-btn" data-pid="<?php echo esc_attr( $player_id ); ?>" data-native="<?php echo $is_native ? '1' : '0'; ?>" aria-label="Toggle sound">
                        <i class="aun-vol-muted fa-solid fa-volume-xmark" style="font-size:18px"></i>
                        <i class="aun-vol-unmuted fa-solid fa-volume-high" style="font-size:18px"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ( $is_native ) : ?>
                        <video id="<?php echo esc_attr( $player_id ); ?>" class="aun-native-video"
                               src="<?php echo esc_url( $final_url ); ?>"
                               loop muted playsinline preload="metadata"></video>
                    <?php else : ?>
                        <!-- Player wrap FIRST, overlay AFTER so the overlay is always on top (blocks YT clicks). -->
                        <div class="aun-yt-player-wrap">
                            <div class="aun-yt-player" data-vid="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $player_id ); ?>"></div>
                        </div>
                        <div class="aun-yt-overlay"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php echo self::get_wide_engine_script( $instance ); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Compact player engine for [aun_video] — YouTube API init (controls hidden, glass mute
     * button only), native-video autoplay-on-scroll, and the robust lazy YouTube-API loader.
     * Reuses the same global registries as [aun_shorts] (idempotent), so both can coexist.
     * WP Rocket guards keep the optimizer from deferring/combining it.
     */
    private static function get_wide_engine_script( string $instance ): string {
        $js = <<<'JS'
        if (typeof window.aunYtPlayers === 'undefined') { window.aunYtPlayers = {}; window.aunYtApiLoaded = false; }
        (function () {
            function _aunInit() {
                var wrapper = document.getElementById('__AUN_INSTANCE__');
                if (!wrapper) return;

                // Force the glass mute button visible regardless of theme/button resets.
                wrapper.querySelectorAll('.aun-mute-btn').forEach(function (btn) {
                    btn.style.setProperty('display', 'flex', 'important');
                    btn.style.setProperty('visibility', 'visible', 'important');
                    btn.style.setProperty('opacity', '1', 'important');
                });

                // Autoplay (muted) when scrolled into view; pause when out of view.
                var playPauseObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var nativeVid = entry.target.querySelector('video.aun-native-video');
                        if (nativeVid) {
                            if (entry.isIntersecting) { nativeVid.play().catch(function () {}); } else { nativeVid.pause(); }
                            return;
                        }
                        var iframe = entry.target.querySelector('iframe');
                        if (!iframe) return;
                        var player = window.aunYtPlayers[iframe.id];
                        if (player && typeof player.playVideo === 'function') {
                            if (entry.isIntersecting) { player.playVideo(); } else { player.pauseVideo(); }
                        }
                    });
                }, { threshold: 0.4 });

                wrapper.querySelectorAll('.aun-short-card').forEach(function (card) {
                    if (card.querySelector('video.aun-native-video')) { playPauseObserver.observe(card); }
                });

                var initialized = false;
                function initLocalPlayers() {
                    if (initialized) return;
                    if (typeof YT === 'undefined' || !YT || typeof YT.Player !== 'function') return;
                    initialized = true;
                    wrapper.querySelectorAll('.aun-yt-player').forEach(function (el) {
                        var vid = el.getAttribute('data-vid');
                        var pid = el.id;
                        if (window.aunYtPlayers[pid]) return;
                        window.aunYtPlayers[pid] = new YT.Player(pid, {
                            videoId: vid,
                            playerVars: { autoplay: 0, controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3, modestbranding: 1, rel: 0, playsinline: 1, mute: 1, loop: 0 },
                            events: {
                                onReady: function (e) {
                                    e.target.mute();
                                    var iframe = e.target.getIframe();
                                    if (iframe) {
                                        iframe.classList.add('aun-yt-player');
                                        iframe.classList.add('aun-is-playing');
                                        iframe.style.setProperty('position', 'absolute', 'important');
                                        iframe.style.setProperty('top', '0', 'important');
                                        iframe.style.setProperty('left', '0', 'important');
                                        iframe.style.setProperty('width', '100%', 'important');
                                        iframe.style.setProperty('height', '100%', 'important');
                                        iframe.style.setProperty('max-width', 'none', 'important');
                                        iframe.style.setProperty('aspect-ratio', 'unset', 'important');
                                        iframe.style.setProperty('border', '0', 'important');
                                        iframe.style.setProperty('opacity', '1', 'important');
                                    }
                                    var card = iframe ? iframe.closest('.aun-short-card') : null;
                                    if (card) playPauseObserver.observe(card);
                                },
                                onStateChange: function (e) {
                                    var iframe = e.target.getIframe();
                                    if (!iframe) return;
                                    if (e.data === YT.PlayerState.PLAYING) { iframe.classList.add('aun-is-playing'); iframe.style.opacity = '1'; }
                                    if (e.data === YT.PlayerState.ENDED) { e.target.seekTo(0); e.target.playVideo(); }
                                }
                            }
                        });
                    });
                }

                function loadYoutubeApi() {
                    if (wrapper.querySelectorAll('.aun-yt-player').length === 0) return;
                    if (typeof YT !== 'undefined' && YT && typeof YT.Player === 'function') { initLocalPlayers(); return; }
                    if (typeof YT !== 'undefined' && YT && typeof YT.ready === 'function') { YT.ready(initLocalPlayers); return; }
                    document.addEventListener('aunYtApiReady', initLocalPlayers);
                    if (!window.aunYtApiLoaded) {
                        window.aunYtApiLoaded = true;
                        var oldCb = window.onYouTubeIframeAPIReady;
                        window.onYouTubeIframeAPIReady = function () { if (typeof oldCb === 'function') { oldCb(); } document.dispatchEvent(new Event('aunYtApiReady')); };
                        if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
                            var tag = document.createElement('script');
                            tag.src = "https://www.youtube.com/iframe_api"; tag.async = true;
                            var first = document.getElementsByTagName('script')[0];
                            first.parentNode.insertBefore(tag, first);
                        }
                    }
                    var tries = 0;
                    var poll = setInterval(function () {
                        tries++;
                        if (typeof YT !== 'undefined' && YT && typeof YT.Player === 'function') { clearInterval(poll); initLocalPlayers(); }
                        else if (tries > 50) { clearInterval(poll); }
                    }, 200);
                }

                var lazyObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) { loadYoutubeApi(); lazyObserver.unobserve(entry.target); }
                    });
                }, { rootMargin: '400px' });
                lazyObserver.observe(wrapper);
            }

            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', _aunInit); } else { _aunInit(); }
        })();

        // Glass mute button handler — shared, idempotent (same registry as [aun_shorts]).
        if (typeof window.aunToggleMute !== 'function') {
            window.aunToggleMute = function (btn, pid, isNative) {
                if (isNative) {
                    var video = document.getElementById(pid);
                    if (video) { video.muted = !video.muted; btn.classList.toggle('aun-unmuted', !video.muted); }
                } else {
                    var player = window.aunYtPlayers[pid];
                    if (player && typeof player.isMuted === 'function') {
                        if (player.isMuted()) { player.unMute(); btn.classList.add('aun-unmuted'); }
                        else { player.mute(); btn.classList.remove('aun-unmuted'); }
                    }
                }
            };
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.aun-mute-btn');
                if (!btn) return;
                window.aunToggleMute(btn, btn.getAttribute('data-pid'), btn.getAttribute('data-native') === '1');
            });
        }
        JS;

        return '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
            . str_replace( '__AUN_INSTANCE__', esc_js( $instance ), $js )
            . '</script>';
    }
}

AUN_Shorts_Showcase::init();
