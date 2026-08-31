<?php
/**
 * Plugin Name: Kohthai Shorts Showcase
 * Description: Plays a product video in its real shape - square, portrait or tall - instead of
 *              letterboxing it into a 16:9 player. Autoplays muted when scrolled into view, loops
 *              seamlessly, and renders a side-by-side block (video plus selling points) for one
 *              video or a swipeable gallery for several. Settings and the full shortcode
 *              reference are under Settings -> Shorts Showcase.
 * Version:     1.2.1
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 *
 * WHY THIS EXISTS
 *   Every Kohthai product video is a YouTube Short. Flatsome's [ux_video] forces one into a 16:9
 *   player, so a square or vertical clip gets black bars down both sides and the bag renders at
 *   roughly 40% of the frame. No CSS fixes that - the letterboxing happens inside the player.
 *   This gives the video a card that matches the footage.
 *
 * THE SHAPE IS A SETTING, NOT AN ASSUMPTION
 *   Measured on two live product videos via YouTube's original-aspect still
 *   (i.ytimg.com/vi/<id>/oar2.jpg), BOTH came back 720x720 - square, not 9:16. Forcing 9:16 on a
 *   square clip pillarboxes it just as badly as the player this replaces. So the card shape is
 *   chosen per shortcode via ratio="9:16|4:5|1:1", falling back to the site-wide default on the
 *   settings screen. Settings -> Shorts Showcase has a checker that reads any video's true shape.
 *
 * POSTER FRAME
 *   background-image lists oar2.jpg (original aspect) above hqdefault.jpg. hqdefault is always a
 *   4:3 crop and is wrong for every square or vertical video; because CSS silently skips a
 *   background layer that 404s, hqdefault only shows for videos with no original-aspect still.
 *
 * SECURITY - DO NOT RELAX
 *   A video id is validated against /^[A-Za-z0-9_-]{11}$/ before use, because it is interpolated
 *   into a CSS url(). An unvalidated value is a style-injection vector. The bundled test harness
 *   fires seven malformed ids and asserts zero cards rendered and nothing leaked for each.
 *
 * REQUIRES FontAwesome for the mute and YouTube glyphs. Kohthai loads the full FontAwesome
 * (all.css), so there is no icon-subset trap here.
 *
 * v1.2.1: Cap the feature-block card at max-width:100% on phones. The card gets its width
 *         from an INLINE style (the ratio map), and an inline style beats the stylesheet's
 *         width:85vw, so a 380px square card was overflowing a 286px column. Measured on a
 *         390px viewport: card 380px in a 286px parent, now 286x286 and still exactly square.
 * v1.2.0: Two fixes that only mattered once this ran on a mobile-first shop.
 *         (a) The feature text beside the video was display:none below 768px in the
 *             upstream build. On a site where nearly all traffic is mobile that hid the
 *             selling points from nearly everyone. It now stacks under the video.
 *         (b) The desktop feature card was cool slate (#f8fafc / #e2e8f0) and looked
 *             foreign against a chocolate-and-tan shop. Repalletted to warm neutrals.
 * v1.1.0: Settings screen added (site-wide default shape) and the inherited help page replaced
 *         with a complete Kohthai shortcode reference plus a video-shape checker.
 * v1.0.0: First release. Player engine, drag physics, autoplay observer and id validation are
 *         carried over unchanged from the Shorts engine already proven on the owner's other
 *         WooCommerce site - proven code, renamed. The ratio attribute and the original-aspect
 *         poster are new here; that site did not need them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Kohthai_Shorts_Showcase {

    /** Prevents the <script> block from being output more than once per request. */
    private static bool $scripts_printed = false;

    /** Cache for wp_get_attachment_url() — avoids a DB query per video per render. */
    private static array $url_cache = [];

    public static function init(): void {
        add_shortcode( 'kohthai_shorts', [ __CLASS__, 'render_shorts_shortcode' ] );
        add_shortcode( 'kohthai_video',  [ __CLASS__, 'render_video_shortcode' ] );  // single horizontal 16:9 video
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
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
        wp_register_style( 'kts-shorts-showcase', false, [], '1.2.1' );
        wp_enqueue_style( 'kts-shorts-showcase' );
        wp_add_inline_style( 'kts-shorts-showcase', self::get_css() );
    }

    /** Returns the full plugin stylesheet (no <style> wrapper). Single source of truth. */
    public static function get_css(): string {
        return <<<'CSS'
            /* Center the entire block dynamically on the page */
            .kts-shorts-wrapper { margin: 40px auto; max-width: 100%; width: max-content; }
            .kts-shorts-header { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; position: relative; }
            .kts-shorts-title-wrap { display: flex; align-items: center; justify-content: center; gap: 12px; }

            /* Mobile Swipe Hint Animation (For Gallery)
               The hint used to be position:absolute (right:20px). Being out of flow it
               reserved no space, so on narrow screens the centred nowrap title ran
               straight underneath it. It now sits IN FLOW on its own line below the
               title (header stacks), which guarantees a real gap at every width.
               !important is required on the header rules because the markup carries
               inline flex styles. */
            /* Header printed ONLY to carry the swipe hint (title="" icon="false", because the
               surrounding page already supplies a heading): no title, so no bottom spacing on
               desktop where the hint is hidden. */
            .kts-shorts-wrapper .kts-shorts-header.kts-header-hint-only { margin-bottom: 0 !important; }

            @media (max-width: 768px) {
                .kts-shorts-wrapper .kts-shorts-header {
                    flex-direction: column !important;
                    flex-wrap: wrap !important;
                    gap: 10px;
                }
                .kts-shorts-wrapper .kts-shorts-header.kts-header-hint-only { margin-bottom: 12px !important; }
                .kts-shorts-wrapper .kts-swipe-hint {
                    display: flex !important; position: static !important; right: auto !important;
                    align-items: center; justify-content: center; gap: 6px;
                    font-size: 13px; color: #6f6459;
                    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
                    animation: ktsSwipePulse 2s infinite ease-in-out !important;
                }
            }
            @keyframes ktsSwipePulse {
                0%, 100% { transform: translateX(0); }
                50% { transform: translateX(6px); color: #654321; }
            }

            /* Smooth Scroll Container (For Gallery) */
            .kts-shorts-scroll-container {
                display: flex !important; flex-direction: row !important; gap: 20px;
                overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 20px;
                -webkit-overflow-scrolling: touch; scrollbar-width: thin;
                scrollbar-color: #d9cfc2 transparent; cursor: grab; scroll-behavior: smooth;
                align-items: flex-start; pointer-events: auto !important; touch-action: pan-y;
                /* Stop the blue text/element selection highlight while dragging the slider */
                -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
                -webkit-tap-highlight-color: transparent;
            }
            .kts-shorts-scroll-container .kts-short-card,
            .kts-shorts-scroll-container .kts-short-card * {
                -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
                -webkit-tap-highlight-color: transparent;
            }
            .kts-shorts-scroll-container.kts-is-dragging {
                cursor: grabbing; scroll-snap-type: none !important; scroll-behavior: auto !important;
            }
            .kts-shorts-scroll-container.kts-is-dragging .kts-short-card { pointer-events: none; }
            .kts-shorts-scroll-container::-webkit-scrollbar { height: 8px; }
            .kts-shorts-scroll-container::-webkit-scrollbar-track { background: transparent; border-radius: 10px; }
            .kts-shorts-scroll-container::-webkit-scrollbar-thumb { background-color: #d9cfc2; border-radius: 10px; border: 2px solid #fff; }

            /* Premium Card Sizing (Standard Gallery) */
            .kts-short-card {
                flex: 0 0 auto !important; width: var(--kts-w, 280px) !important; aspect-ratio: var(--kts-ar, 9 / 16);
                min-height: 0; height: auto; scroll-snap-align: center; border-radius: 16px;
                overflow: hidden; background-color: transparent !important; box-shadow: none !important;
                border: none !important; position: relative !important; transition: transform 0.3s ease;
                box-sizing: border-box;
                /* Contain inner z-indexes (overlay, mute button) inside the card so they can't
                   paint over the theme's fixed/sticky add-to-cart bar when the page scrolls. */
                isolation: isolate;
            }

            /* Harden against wp-block-library iframe rules (load when a YouTube oEmbed is present). */
            .kts-shorts-wrapper .kts-yt-player-wrap {
                position: absolute !important; top: -1% !important; left: -1% !important;
                width: 102% !important; height: 102% !important; z-index: 1 !important;
                pointer-events: none !important;
            }
            .kts-shorts-wrapper .kts-yt-player {
                width: 100% !important; height: 100% !important; aspect-ratio: unset !important;
                pointer-events: none !important;
            }
            /* Force the YouTube-generated iframe to fill the wrapper, and CLOAK it with opacity:0
               until our onReady handler reveals it — this kills the "small black box then stretch"
               flash because the thumbnail background stays visible until the video is truly ready. */
            .kts-shorts-wrapper .kts-yt-player-wrap iframe,
            .kts-shorts-wrapper iframe.kts-yt-player {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; max-width: none !important;
                aspect-ratio: unset !important; border: 0 !important; pointer-events: none !important;
                opacity: 0; transition: opacity 0.4s ease;
            }
            .kts-shorts-wrapper .kts-yt-player-wrap iframe.kts-is-playing,
            .kts-shorts-wrapper iframe.kts-yt-player.kts-is-playing { opacity: 1 !important; }
            .kts-shorts-wrapper .kts-native-video {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; aspect-ratio: unset !important; z-index: 1 !important;
            }
            /* Overlay sits ABOVE the iframe (also placed after it in DOM order). */
            .kts-shorts-wrapper .kts-yt-overlay {
                position: absolute !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; z-index: 10 !important;
                cursor: grab !important; pointer-events: auto !important;
            }
            /* Mute button must always sit on top of everything, including the overlay. */
            .kts-shorts-wrapper .kts-mute-btn {
                z-index: 30 !important; pointer-events: auto !important; display: flex !important;
            }

            /* Native Video Styling */
            .kts-native-video {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                object-fit: cover; z-index: 1; pointer-events: none;
            }

            /* Iframe Wrappers & Overlays */
            .kts-yt-player-wrap {
                position: absolute; top: -1%; left: -1%; width: 102%; height: 102%;
                pointer-events: none; z-index: 2;
            }
            /* Opacity Cloak: Seamlessly fades video in over the thumbnail when ready */
            .kts-yt-player { width: 100%; height: 100%; border: none; opacity: 0; transition: opacity 0.4s ease; }
            .kts-yt-player.kts-is-playing { opacity: 1; }
            .kts-yt-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5; }

            /* Custom Glassmorphism Audio Button */
            .kts-mute-btn {
                position: absolute; bottom: 15px; right: 15px; width: 44px; height: 44px;
                border-radius: 50%; background: rgba(0,0,0,0.3); backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2);
                color: #ffffff; cursor: pointer; z-index: 10; display: flex; align-items: center;
                justify-content: center; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), background 0.2s, border-color 0.2s;
                padding: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.2); outline: none;
            }
            .kts-mute-btn .kts-vol-unmuted { display: none; }
            .kts-mute-btn.kts-unmuted .kts-vol-unmuted { display: block; }
            .kts-mute-btn.kts-unmuted .kts-vol-muted { display: none; }
            .kts-mute-btn.kts-unmuted { background: rgba(1, 136, 254, 0.85); border-color: rgba(1, 136, 254, 1); }
            .kts-mute-btn:active { transform: scale(0.92); }
            @media (hover: hover) {
                .kts-mute-btn:hover { background: rgba(0,0,0,0.6); transform: scale(1.05); }
                .kts-mute-btn.kts-unmuted:hover { background: rgba(1, 136, 254, 1); }
            }

            /* SINGLE MODE & MOBILE TRANSFORMATIONS */
            .kts-shorts-wrapper.kts-is-feature-block { width: 100%; max-width: 1000px; margin: 40px auto; }
            .kts-feat-title { margin: 0 0 15px 0; line-height: 1.2; }
            .kts-feat-desc { line-height: 1.6; margin: 0 0 20px 0; }
            .kts-feat-list { list-style: none; padding: 0; margin: 0; text-align: left; display: inline-block; }
            .kts-feat-list li { margin-bottom: 12px; display: flex; align-items: flex-start; gap: 10px; line-height: 1.4; }
            .kts-feat-list li i { color: #2f6b42; font-size: 18px; margin-top: 2px; }
            .kts-feat-btn { display: inline-flex !important; align-items: center; gap: 8px; }

            @media (max-width: 768px) {
                .kts-shorts-scroll-container { gap: 12px; padding-bottom: 15px; }
                .kts-shorts-wrapper:not(.kts-is-feature-block) .kts-short-card {
                    flex: 0 0 auto; width: 68vw; max-width: calc(70vh * var(--kts-arn, 0.5625));
                    aspect-ratio: var(--kts-ar, 9 / 16); height: auto; min-height: 0; max-height: 70vh;
                }
                .kts-shorts-wrapper.kts-is-feature-block .kts-feature-container {
                    background: transparent; border: none; padding: 0; box-shadow: none; display: block;
                }
                /* Upstream hid this below 768px. Kohthai's traffic is overwhelmingly
                   mobile, so hiding the selling points would hide them from nearly
                   everyone. Stack them under the video instead. */
                .kts-shorts-wrapper.kts-is-feature-block .kts-feature-text {
                    display: block !important; margin-top: 24px; text-align: left;
                }
                .kts-shorts-wrapper.kts-is-feature-block .kts-feature-video { margin-bottom: 0; display: flex; justify-content: center; }
                .kts-shorts-wrapper.kts-is-feature-block .kts-short-card {
                    flex: 0 0 auto; width: 85vw; max-width: calc(70vh * var(--kts-arn, 0.5625)); margin: 0 auto;
                    border: none !important; box-shadow: none !important; height: auto; max-height: 70vh;
                }
                .kts-shorts-wrapper.kts-is-feature-block .kts-shorts-header { display: flex !important; }
                /* The card carries an INLINE width from the ratio map, and an inline style beats
                   the width:85vw rule above. Without this cap a 380px 1:1 card overflows a 286px
                   column on a phone. aspect-ratio keeps it square as it shrinks. */
                .kts-shorts-wrapper.kts-is-feature-block .kts-short-card { max-width: 100% !important; }
            }

            @media (min-width: 769px) {
                .kts-feature-container {
                    display: flex; flex-direction: row; align-items: center; background: #faf7f3;
                    border: 1px solid #e8e2d9; border-radius: 24px; padding: 40px; gap: 50px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
                }
                /* Basis must follow --kts-w, or a 380px square card gets squeezed into a
                   320px column left over from when every card was 9:16. */
                .kts-feature-video { flex: 0 0 var(--kts-w, 320px); display: flex; justify-content: center; margin-bottom: 0; }
                .kts-feature-video .kts-short-card {
                    width: 100%; max-width: var(--kts-w, 320px); height: auto; aspect-ratio: var(--kts-ar, 9 / 16);
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important; border: 4px solid #fff !important;
                }
                .kts-feature-text { flex: 1; text-align: left; padding: 0; }
                .kts-feature-text .kts-feat-title { color: #1f1a15; }
                .kts-feat-list { display: block; }
                .kts-shorts-wrapper.kts-is-feature-block .kts-shorts-header { display: none !important; }
                /* Feature-block video now behaves like a gallery card: no YouTube controls, the
                   overlay covers the player, and the glass mute button stays visible. Give the
                   overlay a normal cursor since it isn't drag-to-scroll here. */
                .kts-shorts-wrapper.kts-is-feature-block .kts-yt-overlay { cursor: default !important; }
            }

            /* =====================================================================
               SINGLE HORIZONTAL 16:9 VIDEO — [kohthai_video]
               All rules are scoped under .kts-is-wide so they never touch the
               vertical gallery / feature-block markup above.
               ===================================================================== */
            .kts-shorts-wrapper.kts-is-wide { width: 100%; max-width: 920px; margin: 40px auto; }
            .kts-wide-head { max-width: 820px; margin: 0 auto 20px; text-align: center; }
            .kts-wide-title { margin: 0 0 10px; line-height: 1.25; }
            .kts-wide-desc { margin: 0; line-height: 1.65; color: #4a4038; }
            .kts-wide-stage { width: 100%; display: flex; justify-content: center; }
            /* Override the vertical card geometry for a centred 16:9 frame. */
            .kts-shorts-wrapper.kts-is-wide .kts-short-card {
                flex: 0 0 auto !important; width: 100% !important; max-width: 920px !important;
                height: auto !important; min-height: 0 !important; aspect-ratio: 16 / 9 !important;
                margin: 0 auto; border-radius: 16px; box-shadow: 0 18px 40px rgba(0,0,0,0.12) !important;
            }
            /* The horizontal video uses the gallery system: no YouTube controls, the overlay
               covers the player, and only the glass mute button is shown. Normal cursor (not drag). */
            .kts-shorts-wrapper.kts-is-wide .kts-yt-overlay { cursor: default !important; }
            @media (max-width: 768px) {
                .kts-shorts-wrapper.kts-is-wide { margin: 26px auto; }
                .kts-shorts-wrapper.kts-is-wide .kts-short-card { border-radius: 12px; }
            }
CSS;
    }


    /**
     * Background style for a video card's poster frame.
     *
     * hqdefault.jpg is ALWAYS a 4:3 letterboxed crop, which is wrong for every vertical or
     * square Short -- it is the same mistake as the 16:9 player. oar2.jpg is YouTube's
     * original-aspect still (measured: 720x720 for Kohthai's square Shorts). Because CSS paints
     * layered backgrounds top-first and simply skips a layer that 404s, listing oar2 above
     * hqdefault gives a real fallback for videos that have no original-aspect still.
     *
     * $id is already validated as /^[A-Za-z0-9_-]{11}$/ by the caller. Do not call this with an
     * unvalidated id: the value lands inside a CSS url(), so it is a style-injection vector.
     */
    private static function poster_style( string $id, string $position ): string {
        $base = 'https://i.ytimg.com/vi/' . esc_attr( $id ) . '/';
        return "background-image:url('{$base}oar2.jpg'),url('{$base}hqdefault.jpg');"
             . 'background-size:cover;background-position:' . esc_attr( $position ) . ';';
    }

    /* =====================================================================
     * Settings + shortcode reference  (Settings -> Shorts Showcase)
     * ===================================================================== */

    const OPTION = 'kts_options';

    public static function defaults(): array {
        return [ 'default_ratio' => '9:16' ];
    }

    /** Valid card shapes. Single source of truth for the settings screen and the shortcode. */
    public static function ratio_map(): array {
        return [
            '9:16' => [ 'w' => 280, 'ar' => '9 / 16', 'n' => '0.5625', 'label' => '9:16 - tall Short' ],
            '4:5'  => [ 'w' => 340, 'ar' => '4 / 5',  'n' => '0.8',    'label' => '4:5 - portrait'    ],
            '1:1'  => [ 'w' => 380, 'ar' => '1 / 1',  'n' => '1',      'label' => '1:1 - square'      ],
        ];
    }

    public static function options(): array {
        $saved = get_option( self::OPTION, [] );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
    }

    /** The shape used when a shortcode does not state its own ratio. */
    public static function default_ratio(): string {
        $o = self::options();
        return isset( self::ratio_map()[ $o['default_ratio'] ] ) ? $o['default_ratio'] : '9:16';
    }

    public static function register_settings(): void {
        register_setting( 'kts_group', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default'           => self::defaults(),
        ] );
    }

    public static function sanitize( $input ): array {
        $clean = self::defaults();
        if ( ! is_array( $input ) ) {
            return $clean;
        }
        if ( isset( $input['default_ratio'] ) && isset( self::ratio_map()[ $input['default_ratio'] ] ) ) {
            $clean['default_ratio'] = $input['default_ratio'];
        }
        return $clean;
    }

    public static function add_admin_menu(): void {
        add_options_page(
            'Kohthai Shorts Showcase',
            'Shorts Showcase',
            'manage_options',
            'kts-shorts-showcase',
            [ __CLASS__, 'render_help_page' ]
        );
    }

    public static function render_help_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $o   = self::options();
        $map = self::ratio_map();
        ?>
        <div class="wrap">
            <h1>Kohthai Shorts Showcase</h1>
            <p style="max-width:60em;font-size:14px">
                Plays a YouTube Short in its <strong>real shape</strong>. Flatsome&rsquo;s
                <code>[ux_video]</code> forces every video into a 16:9 player, so a square or vertical
                clip gets black bars down both sides and the bag ends up filling less than half the
                frame. This gives the video a card that matches the footage.
            </p>

            <div class="card" style="max-width:820px;padding:20px;border-left:4px solid #654321">
                <h2 style="margin-top:0">Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'kts_group' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="kts_default_ratio">Default video shape</label></th>
                            <td>
                                <select id="kts_default_ratio" name="<?php echo esc_attr( self::OPTION ); ?>[default_ratio]">
                                    <?php foreach ( $map as $key => $cfg ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $o['default_ratio'], $key ); ?>>
                                            <?php echo esc_html( $cfg['label'] ); ?> &mdash; <?php echo (int) $cfg['w']; ?>px wide
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" style="max-width:46em">
                                    Used whenever a shortcode does not state its own <code>ratio</code>.
                                    <strong>Kohthai&rsquo;s product videos are currently 1:1</strong> &mdash; both of the
                                    ones measured came back 720&times;720 &mdash; so setting this to <strong>1:1</strong>
                                    means you never have to type <code>ratio</code> again.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save' ); ?>
                </form>
                <p style="margin:0;color:#6f6459">
                    Clear the WP Rocket cache after saving &mdash; product pages are cached.
                </p>
            </div>

            <div class="card" style="max-width:820px;padding:20px;margin-top:20px">
                <h2 style="margin-top:0">Which shape is my video?</h2>
                <p style="max-width:52em">
                    Paste a YouTube video ID (the 11 characters after <code>/shorts/</code> or
                    <code>?v=</code>). This reads YouTube&rsquo;s original-aspect still and tells you the
                    true shape. Getting this wrong is the one thing that stops the plugin working.
                </p>
                <p>
                    <input type="text" id="kts-check-id" class="regular-text" placeholder="bUSme6zuVwQ" maxlength="11">
                    <button type="button" class="button" id="kts-check-go">Check</button>
                </p>
                <div id="kts-check-out" style="font-size:14px"></div>
                <script>
                (function () {
                    var btn = document.getElementById('kts-check-go');
                    if (!btn) { return; }
                    btn.addEventListener('click', function () {
                        var id  = (document.getElementById('kts-check-id').value || '').trim();
                        var out = document.getElementById('kts-check-out');
                        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
                            out.innerHTML = '<p style="color:#9a3b30">That is not an 11-character YouTube ID.</p>';
                            return;
                        }
                        out.innerHTML = '<p>Reading&hellip;</p>';
                        var img = new Image();
                        img.onload = function () {
                            var r = img.naturalWidth / img.naturalHeight;
                            var best = Math.abs(r - 0.5625) < 0.08 ? '9:16'
                                     : Math.abs(r - 0.8) < 0.08 ? '4:5'
                                     : Math.abs(r - 1) < 0.08 ? '1:1'
                                     : null;
                            out.innerHTML =
                                '<p>Original still is <strong>' + img.naturalWidth + ' &times; ' +
                                img.naturalHeight + '</strong> (ratio ' + r.toFixed(2) + ').</p>' +
                                (best
                                    ? '<p style="color:#2f6b42;font-weight:600">Use ratio="' + best + '"</p>'
                                    : '<p style="color:#9a3b30">This is a landscape video &mdash; use the ' +
                                      '<code>[kohthai_video]</code> shortcode instead.</p>') +
                                '<img src="' + img.src + '" style="max-height:220px;border:1px solid #ddd">';
                        };
                        img.onerror = function () {
                            out.innerHTML = '<p style="color:#9a3b30">No original-aspect still for that ID. ' +
                                'It is probably a normal landscape video &mdash; use <code>[kohthai_video]</code>.</p>';
                        };
                        img.src = 'https://i.ytimg.com/vi/' + id + '/oar2.jpg';
                    });
                })();
                </script>
            </div>

            <div class="card" style="max-width:820px;padding:20px;margin-top:20px;border-left:4px solid #a08565">
                <h2 style="margin-top:0">Ready to paste &mdash; a product page</h2>
                <p>Put this in the product&rsquo;s <strong>Description</strong> field, replacing <code>[ux_video]</code>.</p>
                <textarea readonly onclick="this.select()" rows="4"
                    style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #c3c4c7;border-radius:4px">[kohthai_shorts ids="bUSme6zuVwQ" ratio="1:1" title="" icon="false" feature_title="Why you will love it" feature_desc="Watch it worn in real life - see how it hangs, how big it really is, and how much it actually holds." features="Fits your phone, wallet and makeup, Adjustable strap for shoulder or crossbody, Light enough to carry all day"]</textarea>
                <p style="margin:12px 0 0;color:#6f6459">
                    <strong>Why <code>title="" icon="false"</code>:</strong> the accordion already prints
                    &ldquo;Description&rdquo; above it, so the shortcode&rsquo;s own heading would be a duplicate.
                </p>
                <p style="margin:8px 0 0;color:#9a3b30">
                    <strong>Always fill in <code>feature_title</code> and <code>features</code>.</strong>
                    With a single video the right-hand column always renders, so leaving them empty gives
                    you an empty column beside the video.
                </p>
            </div>

            <div class="card" style="max-width:820px;padding:20px;margin-top:20px">
                <h2 style="margin-top:0">[kohthai_shorts] &mdash; every attribute</h2>
                <table class="widefat striped" style="margin-bottom:18px">
                    <thead><tr><th style="width:130px">Attribute</th><th style="width:120px">Default</th><th>What it does</th></tr></thead>
                    <tbody>
                        <tr><td><code>ids</code></td><td><em>required</em></td>
                            <td>One or more sources, comma separated. Each may be an <strong>11-character YouTube ID</strong>,
                                a <strong>WordPress Media ID</strong> (a number), or a direct
                                <strong>.mp4 / .webm / .mov</strong> URL. One source renders the side-by-side
                                block; several render a swipeable gallery. Anything that is not a valid source
                                is skipped silently.</td></tr>
                        <tr><td><code>ratio</code></td><td><code><?php echo esc_html( self::default_ratio() ); ?></code> (from Settings)</td>
                            <td>Card shape: <code>9:16</code>, <code>4:5</code> or <code>1:1</code>. Falls back to the
                                setting above when omitted. <strong>This matters most</strong> &mdash; a mismatch puts
                                the black bars straight back.</td></tr>
                        <tr><td><code>title</code></td><td><code>SEE IT WORN</code></td>
                            <td>Heading above the videos. Use <code>title=""</code> to hide it &mdash; do this on
                                product pages, where the accordion already supplies a heading.</td></tr>
                        <tr><td><code>icon</code></td><td><code>true</code></td>
                            <td><code>icon="false"</code> hides the small YouTube mark beside the heading.</td></tr>
                        <tr><td><code>audio</code></td><td><code>true</code></td>
                            <td><code>audio="false"</code> removes the speaker button, so the clip is a silent loop.
                                Video never plays with sound unless the viewer taps that button.</td></tr>
                        <tr><td><code>feature_title</code></td><td>Why You will Love It</td>
                            <td>Bold heading in the column beside the video. <em>Single video only.</em></td></tr>
                        <tr><td><code>feature_desc</code></td><td><em>bag copy</em></td>
                            <td>Paragraph under that heading. <em>Single video only.</em></td></tr>
                        <tr><td><code>features</code></td><td><em>three bag points</em></td>
                            <td>Comma-separated points, rendered as a ticked list. <em>Single video only.</em></td></tr>
                        <tr><td><code>btn_text</code></td><td><em>empty</em></td>
                            <td>Optional button label. Left empty, no button is drawn.</td></tr>
                        <tr><td><code>btn_link</code></td><td><code>#</code></td>
                            <td>Where that button goes.</td></tr>
                    </tbody>
                </table>

                <h3>A gallery of several videos</h3>
                <p>Useful on a category page or a lookbook. Drag on desktop, swipe on mobile.</p>
                <code style="display:block;padding:10px;background:#f6f7f7">[kohthai_shorts ids="bUSme6zuVwQ, tUnExtJfYdQ" ratio="1:1"]</code>

                <h3 style="margin-top:22px">A silent loop with no heading</h3>
                <code style="display:block;padding:10px;background:#f6f7f7">[kohthai_shorts ids="bUSme6zuVwQ" ratio="1:1" title="" icon="false" audio="false"]</code>

                <h3 style="margin-top:22px">A video you uploaded to WordPress</h3>
                <p>Use the Media ID from the media library instead of a YouTube ID. MP4, WebM and MOV all work.</p>
                <code style="display:block;padding:10px;background:#f6f7f7">[kohthai_shorts ids="1482" ratio="1:1"]</code>
            </div>

            <div class="card" style="max-width:820px;padding:20px;margin-top:20px">
                <h2 style="margin-top:0">[kohthai_video] &mdash; for landscape video</h2>
                <p style="max-width:52em">
                    A separate shortcode for a normal <strong>16:9</strong> video. It shows an optional
                    heading and description above a centred wide player with its own controls. Use
                    <code>[kohthai_shorts]</code> for anything square or vertical.
                </p>
                <table class="widefat striped" style="margin-bottom:18px">
                    <thead><tr><th style="width:130px">Attribute</th><th style="width:120px">Default</th><th>What it does</th></tr></thead>
                    <tbody>
                        <tr><td><code>id</code></td><td><em>required</em></td>
                            <td>One source only &mdash; a YouTube ID, a WordPress Media ID, or an
                                .mp4 / .webm / .mov URL.</td></tr>
                        <tr><td><code>title</code></td><td><em>empty</em></td><td>Optional heading above the video.</td></tr>
                        <tr><td><code>desc</code></td><td><em>empty</em></td><td>Optional line under the heading.</td></tr>
                        <tr><td><code>audio</code></td><td><code>true</code></td><td><code>audio="false"</code> removes the speaker button.</td></tr>
                    </tbody>
                </table>
                <code style="display:block;padding:10px;background:#f6f7f7;white-space:pre-wrap">[kohthai_video id="bUSme6zuVwQ" title="How we pack your order" desc="From our Golartek store to your door."]</code>
                <p style="margin:12px 0 0;color:#6f6459">Both texts are optional &mdash; <code>[kohthai_video id="1482"]</code> on its own just shows the video.</p>
            </div>

            <div class="card" style="max-width:820px;padding:20px;margin-top:20px">
                <h2 style="margin-top:0">Good to know</h2>
                <ul style="list-style:disc;padding-left:20px;max-width:56em">
                    <li>Videos <strong>autoplay muted</strong> when scrolled into view and pause when they leave.
                        Nothing makes sound unless the viewer asks for it.</li>
                    <li>They <strong>loop seamlessly</strong> &mdash; no end screen and no &ldquo;watch next&rdquo;
                        suggestions pulling people off your page.</li>
                    <li>The poster frame uses YouTube&rsquo;s original-aspect still, so it matches the card even
                        before the video starts.</li>
                    <li>A video ID that is not exactly 11 valid characters is <strong>ignored</strong>. That is a
                        deliberate security check, not a bug &mdash; the ID is used inside a CSS rule.</li>
                    <li>After changing a shortcode, <strong>clear the WP Rocket cache</strong> or the old markup
                        keeps being served.</li>
                </ul>
            </div>
        </div>
        <?php
    }

    public static function render_shorts_shortcode($atts) {
        $atts = shortcode_atts( [
            'ids'           => '',
            'title'         => 'SEE IT WORN',
            'icon'          => 'true',
            'audio'         => 'true',
            'feature_title' => 'Why You will Love It',
            'feature_desc'  => 'Watch it worn in real life — see how it hangs, how big it really is, and how much it actually holds.',
            'features'      => 'Roomy Inner Pocket, Adjustable Strap, Everyday Lightweight',
            'btn_text'      => '',
            'btn_link'      => '#',
            'ratio'         => '',
        ], $atts, 'kohthai_shorts' );

        // Kohthai's Shorts are not all the same shape. Measured on two live product videos,
        // i.ytimg.com/vi/<id>/oar2.jpg came back 720x720 for BOTH -- they are square, not 9:16.
        // Forcing 9:16 on a square clip pillarboxes it just as badly as the 16:9 player this
        // plugin replaces, so the shape has to be stated per product, not assumed.
        $ratio_map = self::ratio_map();
        $ratio_key = isset( $ratio_map[ $atts['ratio'] ] ) ? $atts['ratio'] : self::default_ratio();
        $card_w    = $ratio_map[ $ratio_key ]['w'];
        $card_ar   = $ratio_map[ $ratio_key ]['ar'];
        $card_arn  = $ratio_map[ $ratio_key ]['n'];

        if (empty($atts['ids'])) return '';

        // Clean up IDs and remove any empty array elements
        $video_ids = array_filter(array_map('trim', explode(',', $atts['ids'])));
        $video_count = count($video_ids);
        if ($video_count === 0) return '';

        $unique_instance = uniqid('kts_shorts_');
        
        $show_title = !empty($atts['title']);
        $show_icon = ($atts['icon'] === 'true');
        $show_audio = ($atts['audio'] === 'true');
        $show_header = ($show_title || $show_icon);
        
        // Smart Detection: Checks if there is exactly 1 video to apply the side-by-side feature block
        $is_single = ($video_count === 1);
        $single_class = $is_single ? 'kts-is-feature-block' : '';

        ob_start();
        ?>
        <div class="kts-shorts-wrapper <?php echo esc_attr($single_class); ?>" id="<?php echo esc_attr($unique_instance); ?>" style="--kts-w:<?php echo (int) $card_w; ?>px;--kts-ar:<?php echo esc_attr( $card_ar ); ?>;--kts-arn:<?php echo esc_attr( $card_arn ); ?>;">
            
            <?php
            // The gallery's mobile swipe hint lives in this header. When the page already
            // supplies its own section heading (the product accordion prints "Description"),
            // the shortcode is used with
            // title="" icon="false" — so still print the header for the hint alone, otherwise
            // hiding the title would silently remove the swipe affordance too.
            $hint_only = ( ! $show_header && ! $is_single );
            ?>
            <?php if ($show_header || $hint_only): ?>
            <!-- Standard Header: Always printed, but hidden via CSS on Desktop Single Mode -->
            <div class="kts-shorts-header<?php echo $hint_only ? ' kts-header-hint-only' : ''; ?>" style="display:flex;align-items:center;justify-content:center;margin-bottom:20px;position:relative;flex-wrap:nowrap;">
                <?php if ($show_header): ?>
                <div class="kts-shorts-title-wrap" style="display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:nowrap;">
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
                <div class="kts-swipe-hint" style="display:none">
                    <span>Swipe</span>
                    <i class="fa-solid fa-arrow-right-long"></i>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($is_single): ?>
                <!-- START: SIDE-BY-SIDE FEATURE BLOCK -->
                <div class="kts-feature-container">
            <?php endif; ?>

            <div class="<?php echo $is_single ? 'kts-feature-video' : 'kts-shorts-scroll-container'; ?>"<?php if(!$is_single): ?> style="display:flex !important;flex-direction:row !important;flex-wrap:nowrap !important;gap:20px;overflow-x:auto;overflow-y:hidden;align-items:flex-start;max-width:100%;"<?php endif; ?>>
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
                        $bg_style = self::poster_style( $id, 'center center' );
                    }
                ?>
                    <div class="kts-short-card"
                         style="width:<?php echo (int) $card_w; ?>px;aspect-ratio:<?php echo esc_attr( $card_ar ); ?>;position:relative;flex:0 0 auto;overflow:hidden;border-radius:16px;box-sizing:border-box;<?php echo $bg_style; ?>"
                         id="<?php echo esc_attr($card_id); ?>">

                        <?php if ( $show_audio ) : ?>
                        <button class="kts-mute-btn"
                                data-pid="<?php echo esc_attr( $player_id ); ?>"
                                data-native="<?php echo $is_native ? '1' : '0'; ?>"
                                aria-label="Toggle sound">
                            <i class="kts-vol-muted fa-solid fa-volume-xmark" style="font-size:18px"></i>
                            <i class="kts-vol-unmuted fa-solid fa-volume-high" style="font-size:18px"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($is_native): ?>
                            <!-- NATIVE HTML5 VIDEO -->
                            <video id="<?php echo esc_attr($player_id); ?>" class="kts-native-video" src="<?php echo esc_url($final_video_url); ?>" loop muted playsinline preload="metadata"></video>
                        <?php else: ?>
                            <!-- YOUTUBE IFRAME FALLBACK -->
                            <!-- Player wrap FIRST, overlay AFTER so overlay is always on top in DOM order -->
                            <div class="kts-yt-player-wrap">
                                <div class="kts-yt-player" data-vid="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($player_id); ?>"></div>
                            </div>
                            <div class="kts-yt-overlay"></div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div> <!-- End video container -->

            <?php if ($is_single): ?>
                    <!-- Text Side of the Feature Block -->
                    <div class="kts-feature-text">
                        <?php if (!empty($atts['feature_title'])): ?>
                            <h3 class="kts-feat-title"><?php echo esc_html($atts['feature_title']); ?></h3>
                        <?php endif; ?>
                        
                        <?php if (!empty($atts['feature_desc'])): ?>
                            <p class="kts-feat-desc"><?php echo wp_kses_post($atts['feature_desc']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($atts['features'])): ?>
                            <ul class="kts-feat-list">
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
                                <a href="<?php echo esc_url($atts['btn_link']); ?>" class="button primary kts-feat-btn" style="border-radius: 8px; margin-bottom: 0;">
                                    <span><?php echo esc_html($atts['btn_text']); ?> <i class="fa-solid fa-arrow-right"></i></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div> <!-- END kts-feature-container -->
            <?php endif; ?>

        </div> <!-- END wrapper -->


        <script>
            if (typeof window.ktsYtPlayers === 'undefined') {
                window.ktsYtPlayers = {};
                window.ktsYtApiLoaded = false;
            }

            // Wrap in IIFE + readyState check so init runs even if a JS optimizer
            // defers this script past the DOMContentLoaded event firing.
            (function() {
                function _ktsInit() {
                var wrapper = document.getElementById('<?php echo esc_js($unique_instance); ?>');
                if (!wrapper) return;

                // --- JS REINFORCEMENT: force visibility of elements that CSS alone may not reach ---

                // 1. Mute buttons: force display regardless of theme/block-library button resets
                wrapper.querySelectorAll('.kts-mute-btn').forEach(function(btn) {
                    btn.style.setProperty('display',     'flex',    'important');
                    btn.style.setProperty('visibility',  'visible', 'important');
                    btn.style.setProperty('opacity',     '1',       'important');
                });

                // 2. Swipe hint: show on mobile, hide on desktop — JS fallback for when the
                //    media-query CSS is stripped or reordered by an optimizer plugin.
                (function() {
                    var hint = wrapper.querySelector('.kts-swipe-hint');
                    if (!hint) return;
                    var header = wrapper.querySelector('.kts-shorts-header');
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
                            hint.style.setProperty('color',           '#6f6459', 'important');
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
                var isSingleDesktop = wrapper.classList.contains('kts-is-feature-block') && window.innerWidth >= 769;

                // Recompute on resize so rotating from mobile → desktop activates controls
                var resizeTimer;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function() {
                        isSingleDesktop = wrapper.classList.contains('kts-is-feature-block') && window.innerWidth >= 769;
                    }, 150);
                });

                // 1. Play/Pause Observer (Handles both Native Video and YT API)
                var playPauseObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        
                        // Native Video
                        var nativeVid = entry.target.querySelector('video.kts-native-video');
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
                        var player = window.ktsYtPlayers[pid];
                        
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
                var nativeCards = wrapper.querySelectorAll('.kts-short-card:has(.kts-native-video), .kts-native-video');
                nativeCards.forEach(function(card) {
                    var actualCard = card.classList.contains('kts-short-card') ? card : card.closest('.kts-short-card');
                    if (actualCard) playPauseObserver.observe(actualCard);
                });

                // Desktop Drag-to-Scroll Logic (Modern Pointer Events with Momentum Physics)
                var scrollContainer = wrapper.querySelector('.kts-shorts-scroll-container');
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
                        if (e.target.closest('.kts-mute-btn')) return;

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
                                scrollContainer.classList.add('kts-is-dragging');
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
                                scrollContainer.classList.remove('kts-is-dragging');
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
                            scrollContainer.classList.remove('kts-is-dragging');
                            scrollContainer.style.cursor = 'grab';
                        }
                    };
                    
                    scrollContainer.addEventListener('pointerup', endDrag);
                    scrollContainer.addEventListener('pointercancel', endDrag);
                }

                // 2. Setup the initialization function for YouTube
                var ktsPlayersInitialized = false;
                function initLocalPlayers() {
                    // Guard: never run the full init more than once per instance.
                    if (ktsPlayersInitialized) return;
                    if (typeof YT === 'undefined' || !YT || typeof YT.Player !== 'function') return;
                    ktsPlayersInitialized = true;

                    var playerEls = wrapper.querySelectorAll('.kts-yt-player');
                    playerEls.forEach(function(el) {
                        var vid = el.getAttribute('data-vid');
                        var pid = el.id;
                        // Skip if this element was already turned into a player.
                        if (window.ktsYtPlayers[pid]) return;
                        
                        // Never show YouTube's own controls — the feature block and gallery both use
                        // the clean looping video + custom glass mute button (no timeline flash on start).
                        var showControls = 0;
                        
                        window.ktsYtPlayers[pid] = new YT.Player(pid, {
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
                                        iframe.classList.add('kts-yt-player');
                                        iframe.classList.add('kts-is-playing');
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
                                    var card = document.getElementById(pid) ? document.getElementById(pid).closest('.kts-short-card') : null;
                                    if (!card && iframe) card = iframe.closest('.kts-short-card');
                                    if (card) playPauseObserver.observe(card);
                                },
                                onStateChange: function(e) {
                                    var iframe = e.target.getIframe();
                                    if (!iframe) return;

                                    if (e.data === YT.PlayerState.PLAYING) {
                                        iframe.classList.add('kts-is-playing');
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
                    if (wrapper.querySelectorAll('.kts-yt-player').length === 0) return; // Don't load API if only using MP4s

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
                    document.addEventListener('ktsYtApiReady', initLocalPlayers);

                    if (!window.ktsYtApiLoaded) {
                        window.ktsYtApiLoaded = true;

                        var oldCb = window.onYouTubeIframeAPIReady;
                        window.onYouTubeIframeAPIReady = function() {
                            if (typeof oldCb === 'function') { oldCb(); }
                            document.dispatchEvent(new Event('ktsYtApiReady'));
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
                } // end _ktsInit

                // Run immediately if DOM is already ready (handles deferred/async script injection);
                // otherwise wait for DOMContentLoaded as normal.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', _ktsInit);
                } else {
                    _ktsInit();
                }
            })();

            // Mute button handler — uses data-pid/data-native attributes instead of inline onclick.
            // This avoids inline JS event handlers which are an XSS risk if pid is ever tainted.
            if (typeof window.ktsToggleMute !== 'function') {
                window.ktsToggleMute = function(btn, pid, isNative) {
                    if (isNative) {
                        var video = document.getElementById(pid);
                        if (video) {
                            video.muted = !video.muted;
                            btn.classList.toggle('kts-unmuted', !video.muted);
                        }
                    } else {
                        var player = window.ktsYtPlayers[pid];
                        if (player && typeof player.isMuted === 'function') {
                            if (player.isMuted()) {
                                player.unMute();
                                btn.classList.add('kts-unmuted');
                            } else {
                                player.mute();
                                btn.classList.remove('kts-unmuted');
                            }
                        }
                    }
                };

                // Delegated listener — handles all mute buttons via data attributes,
                // no inline onclick needed on any element.
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('.kts-mute-btn');
                    if (!btn) return;
                    var pid      = btn.getAttribute('data-pid');
                    var isNative = btn.getAttribute('data-native') === '1';
                    window.ktsToggleMute(btn, pid, isNative);
                });
            }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * [kohthai_video] — a single HORIZONTAL 16:9 video with an optional title + description.
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
        ], $atts, 'kohthai_video' );

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

        $instance   = uniqid( 'kts_video_' );
        $player_id  = $instance . '_vid';
        $card_id    = 'card_' . $player_id;
        $show_audio = ( $atts['audio'] === 'true' );

        $bg_style = '';
        if ( ! $is_native ) {
            $bg_style = self::poster_style( $id, 'center' );
        }

        ob_start();
        ?>
        <div class="kts-shorts-wrapper kts-is-wide" id="<?php echo esc_attr( $instance ); ?>">
            <?php if ( ! empty( $atts['title'] ) || ! empty( $atts['desc'] ) ) : ?>
            <div class="kts-wide-head">
                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <h3 class="kts-wide-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                <?php endif; ?>
                <?php if ( ! empty( $atts['desc'] ) ) : ?>
                    <p class="kts-wide-desc"><?php echo wp_kses_post( $atts['desc'] ); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="kts-wide-stage">
                <div class="kts-short-card kts-wide-card" id="<?php echo esc_attr( $card_id ); ?>"
                     style="position:relative;overflow:hidden;border-radius:16px;box-sizing:border-box;<?php echo $bg_style; ?>">
                    <?php if ( $show_audio ) : ?>
                    <button class="kts-mute-btn" data-pid="<?php echo esc_attr( $player_id ); ?>" data-native="<?php echo $is_native ? '1' : '0'; ?>" aria-label="Toggle sound">
                        <i class="kts-vol-muted fa-solid fa-volume-xmark" style="font-size:18px"></i>
                        <i class="kts-vol-unmuted fa-solid fa-volume-high" style="font-size:18px"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ( $is_native ) : ?>
                        <video id="<?php echo esc_attr( $player_id ); ?>" class="kts-native-video"
                               src="<?php echo esc_url( $final_url ); ?>"
                               loop muted playsinline preload="metadata"></video>
                    <?php else : ?>
                        <!-- Player wrap FIRST, overlay AFTER so the overlay is always on top (blocks YT clicks). -->
                        <div class="kts-yt-player-wrap">
                            <div class="kts-yt-player" data-vid="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $player_id ); ?>"></div>
                        </div>
                        <div class="kts-yt-overlay"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php echo self::get_wide_engine_script( $instance ); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Compact player engine for [kohthai_video] — YouTube API init (controls hidden, glass mute
     * button only), native-video autoplay-on-scroll, and the robust lazy YouTube-API loader.
     * Reuses the same global registries as [kohthai_shorts] (idempotent), so both can coexist.
     * WP Rocket guards keep the optimizer from deferring/combining it.
     */
    private static function get_wide_engine_script( string $instance ): string {
        $js = <<<'JS'
        if (typeof window.ktsYtPlayers === 'undefined') { window.ktsYtPlayers = {}; window.ktsYtApiLoaded = false; }
        (function () {
            function _ktsInit() {
                var wrapper = document.getElementById('__KTS_INSTANCE__');
                if (!wrapper) return;

                // Force the glass mute button visible regardless of theme/button resets.
                wrapper.querySelectorAll('.kts-mute-btn').forEach(function (btn) {
                    btn.style.setProperty('display', 'flex', 'important');
                    btn.style.setProperty('visibility', 'visible', 'important');
                    btn.style.setProperty('opacity', '1', 'important');
                });

                // Autoplay (muted) when scrolled into view; pause when out of view.
                var playPauseObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var nativeVid = entry.target.querySelector('video.kts-native-video');
                        if (nativeVid) {
                            if (entry.isIntersecting) { nativeVid.play().catch(function () {}); } else { nativeVid.pause(); }
                            return;
                        }
                        var iframe = entry.target.querySelector('iframe');
                        if (!iframe) return;
                        var player = window.ktsYtPlayers[iframe.id];
                        if (player && typeof player.playVideo === 'function') {
                            if (entry.isIntersecting) { player.playVideo(); } else { player.pauseVideo(); }
                        }
                    });
                }, { threshold: 0.4 });

                wrapper.querySelectorAll('.kts-short-card').forEach(function (card) {
                    if (card.querySelector('video.kts-native-video')) { playPauseObserver.observe(card); }
                });

                var initialized = false;
                function initLocalPlayers() {
                    if (initialized) return;
                    if (typeof YT === 'undefined' || !YT || typeof YT.Player !== 'function') return;
                    initialized = true;
                    wrapper.querySelectorAll('.kts-yt-player').forEach(function (el) {
                        var vid = el.getAttribute('data-vid');
                        var pid = el.id;
                        if (window.ktsYtPlayers[pid]) return;
                        window.ktsYtPlayers[pid] = new YT.Player(pid, {
                            videoId: vid,
                            playerVars: { autoplay: 0, controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3, modestbranding: 1, rel: 0, playsinline: 1, mute: 1, loop: 0 },
                            events: {
                                onReady: function (e) {
                                    e.target.mute();
                                    var iframe = e.target.getIframe();
                                    if (iframe) {
                                        iframe.classList.add('kts-yt-player');
                                        iframe.classList.add('kts-is-playing');
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
                                    var card = iframe ? iframe.closest('.kts-short-card') : null;
                                    if (card) playPauseObserver.observe(card);
                                },
                                onStateChange: function (e) {
                                    var iframe = e.target.getIframe();
                                    if (!iframe) return;
                                    if (e.data === YT.PlayerState.PLAYING) { iframe.classList.add('kts-is-playing'); iframe.style.opacity = '1'; }
                                    if (e.data === YT.PlayerState.ENDED) { e.target.seekTo(0); e.target.playVideo(); }
                                }
                            }
                        });
                    });
                }

                function loadYoutubeApi() {
                    if (wrapper.querySelectorAll('.kts-yt-player').length === 0) return;
                    if (typeof YT !== 'undefined' && YT && typeof YT.Player === 'function') { initLocalPlayers(); return; }
                    if (typeof YT !== 'undefined' && YT && typeof YT.ready === 'function') { YT.ready(initLocalPlayers); return; }
                    document.addEventListener('ktsYtApiReady', initLocalPlayers);
                    if (!window.ktsYtApiLoaded) {
                        window.ktsYtApiLoaded = true;
                        var oldCb = window.onYouTubeIframeAPIReady;
                        window.onYouTubeIframeAPIReady = function () { if (typeof oldCb === 'function') { oldCb(); } document.dispatchEvent(new Event('ktsYtApiReady')); };
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

            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', _ktsInit); } else { _ktsInit(); }
        })();

        // Glass mute button handler — shared, idempotent (same registry as [kohthai_shorts]).
        if (typeof window.ktsToggleMute !== 'function') {
            window.ktsToggleMute = function (btn, pid, isNative) {
                if (isNative) {
                    var video = document.getElementById(pid);
                    if (video) { video.muted = !video.muted; btn.classList.toggle('kts-unmuted', !video.muted); }
                } else {
                    var player = window.ktsYtPlayers[pid];
                    if (player && typeof player.isMuted === 'function') {
                        if (player.isMuted()) { player.unMute(); btn.classList.add('kts-unmuted'); }
                        else { player.mute(); btn.classList.remove('kts-unmuted'); }
                    }
                }
            };
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.kts-mute-btn');
                if (!btn) return;
                window.ktsToggleMute(btn, btn.getAttribute('data-pid'), btn.getAttribute('data-native') === '1');
            });
        }
        JS;

        return '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
            . str_replace( '__KTS_INSTANCE__', esc_js( $instance ), $js )
            . '</script>';
    }
}

Kohthai_Shorts_Showcase::init();
