<?php
/**
 * Plugin Name: Kohthai Campaign Notice Bar
 * Description: Scheduled campaign notices via shortcodes. Context-aware top bars, global sale detection, live countdowns, Flatsome badge integration, pre-installed campaign templates, and stock awareness. Kohthai brand colours.
 * Version: 2.0.0
 * Author: Smart Living Bangladesh
 *
 * v2.0.0: REBUILT on the AUN Campaign Notice Bar v1.8.3 engine.
 *
 *         Kohthai had been sitting on a fork of the v1.4.x engine, which by now was
 *         four feature releases behind. It was rebuilt rather than patched forward:
 *         a function-by-function comparison found NO behaviour unique to Kohthai --
 *         the fork was the old AUN engine plus brand colours -- so there was nothing
 *         to merge back in, and hand-porting four releases of engine changes would
 *         have meant re-implementing them (and their bugs) by hand.
 *
 *         KEPT IDENTICAL so existing pages and settings keep working:
 *           - shortcodes [kohthai_campaign_notice] and [kohthai_campaign_product_notice]
 *           - option key 'kohthai_campaign_notice_bar_options' -- your saved
 *             campaign, dates and messages carry straight over on upgrade.
 *
 *         WHAT KOHTHAI GAINS over the old 1.4.6 fork:
 *           - {countdown} live timer, driven from an absolute deadline so it stays
 *             correct on a WP-Rocket-cached page.
 *           - Empty top bar collapses in the <head>, BEFORE paint, instead of being
 *             measured in the DOM afterwards -- no more appear-then-vanish flicker.
 *             Three-way choice: phones/tablets only (default), every size, or never.
 *           - FIX: a campaign scheduled for a FUTURE date used to leave the top bar
 *             visible but empty for the whole wait.
 *           - Dismissible notices (visitor closes it; stays closed N days).
 *           - 8 pre-installed campaign templates, rewritten for bags.
 *           - Performance: the "any sale active" answer moved from a transient to an
 *             autoloaded option (0 extra queries instead of 2), and the sale detector
 *             is memoised per request instead of running twice per page.
 *           - Out-of-stock products are skipped automatically, so a sold-out bag is
 *             never advertised. (The discontinued-taxonomy check is guarded by
 *             taxonomy_exists(), so it is simply inert here.)
 *
 *         KOHTHAI DESIGN LANGUAGE -- the palette already used by the 1.4.6 fork:
 *           #654321 chocolate (primary: links, buttons, admin accents)
 *           #a08565 tan       (savings + links ON the chocolate top bar)
 *           #8fbc8f sage      (savings figure on light backgrounds)
 *           #4a3b31 dark brown, #fdfaf7 cream, #efe7dd / #d9cbbd borders
 *
 *         BANGLA: this site is English-only (no TranslatePress, no /bn/), so the
 *         Bangla admin fields are hidden -- see bn_available(). The engine itself is
 *         left in place on purpose: keeping this file structurally identical to
 *         aun-campaign-bar.php is what keeps the NEXT port a rename rather than a
 *         rewrite. Install TranslatePress and the fields come back on their own.
 *
 *         Everything below this line is the AUN v1.8.3 engine, unchanged except for
 *         naming, colours and copy. Its own history follows.
 *
 * ---------------------------------------------------------------------------
 * Upstream history (AUN Campaign Notice Bar)
 * ---------------------------------------------------------------------------
 * Author: Smart Living Bangladesh
 *
 * v1.8.3: • Three new templates built around the live timer: "Countdown campaign", "Final hours push",
 *           and "Flash sale + countdown" (the last counts to the WooCommerce sale end date).
 *         • FIX: the product-page campaign message did not support {countdown} — a template using the
 *           tag there would have printed it raw on every product page.
 * v1.8.2: FIX — a campaign SCHEDULED for a future date left the top bar visible but empty for the
 *           whole wait. top_will_show() counted "scheduled" as showing; it is now top_is_showing()
 *           and answers about this second, so the bar stays collapsed until the campaign actually
 *           opens. The message is still pre-rendered hidden, and the browser un-collapses the bar at
 *           the same moment it reveals the notice.
 * v1.8.1: PERFORMANCE. Measured on the bench, then fixed what the measurement showed:
 *         • The "any sale active" answer lived in a TRANSIENT. A transient with an expiry is not
 *           autoloaded, so reading it cost 2 extra queries on every cache-miss page. It is now an
 *           autoloaded option -> 0 extra queries.
 *         • top_is_showing() (wp_head) and the shortcode both consulted the sale detector, so it ran
 *           twice per page. Both answers are now memoised per request.
 *         Net cost per uncached page: 0 extra queries, ~0.17 ms PHP. On a WP-Rocket-cached page the
 *         plugin does not run at all.
 * v1.8.0: • The empty top bar is now collapsed in the <head>, BEFORE the page is painted, instead of
 *           being measured in the DOM afterwards. The old way made the bar appear and then visibly
 *           vanish a moment later — you cannot measure your way out of a flash. No JS, no flicker,
 *           and the HTML is correct for crawlers too.
 *         • "Empty top bar" became a three-way choice: phones/tablets only (default), every screen
 *           size, or never. Explicit beats guessing: on this site the desktop bar also holds the
 *           language switcher, which blanket-hiding would have taken with it. Old '1'/'0' values
 *           migrate to mobile/off automatically.
 *         • Selector and breakpoint are filterable (kt_cb_topbar_selector, kt_cb_topbar_breakpoint).
 * v1.7.1: FIX — the empty top bar was not collapsing. The collapse ran from the notice's own inline
 *           script, so when there was NO notice to render (campaign off, ended, or no message) nothing
 *           was output and no JavaScript existed to do the job — exactly the case it was built for.
 *           A hidden marker + script is now emitted whenever "Empty top bar" is on. The bar lookup also
 *           uses Element.closest() against a filterable selector (kt_cb_topbar_selector).
 * v1.7.0: • {countdown} magic tag — a live ticking "time left", driven from an absolute deadline so it
 *           stays correct on a cached page. Bangla numerals on the বাংলা site.
 *         • Visitors can dismiss the notice. The memory is keyed to THIS campaign's signature, so a new
 *           message or new dates always shows again.
 *         • Settings page opens with a "what visitors see right now" status card: live / scheduled /
 *           ended / off, the next boundary, and a warning when a running sale outranks the campaign.
 *         • বাংলা fields collapse unless they already have text (the page was twice as long as needed).
 *         • is_bn() now follows the TranslatePress language the visitor actually picked, with the
 *           configured URL slug and the site locale as fallbacks.
 *         • FIX: sale end dates used getOffsetTimestamp() and were then passed to wp_date(), applying
 *           the timezone offset twice — "Ends on" could show the wrong day near midnight.
 * v1.6.1: AUDIT FIXES.
 *         • "Save {discount_amount}" was wrong on VARIABLE products: it subtracted the cheapest sale
 *           price from the cheapest regular price, which can come from different variations. A real
 *           5,000 saving could compute as 0 and suppress the notice entirely. One shared saving_for().
 *         • sanitize_options() blanked any field missing from the input. It runs on EVERY
 *           update_option() for our key, so a partial programmatic update wiped messages + schedule.
 *         • Timezone was stored unvalidated; a typo silently fell back and shifted every campaign.
 *         • Sale badge used preg_replace with unescaped $ in the replacement.
 *         • Boundary state is autoloaded (it is read on every request) and claimed before purging.
 * v1.6.0: • Campaign start/end now flip on the exact minute regardless of WP Rocket: the window is
 *           enforced in the browser from data attributes, and the cache is purged by a scheduled event
 *           at both boundaries (plus a self-healing catch-up, since WP-Cron is unreliable on a cached site).
 *         • New "Empty top bar" option: collapses the theme top bar when the notice is off and nothing
 *           else is visible in it — fixes the empty coloured strip on mobile in the Flatsome header builder.
 * v1.5.0: • Pre-installed campaign templates (weekly / flash / Eid / free delivery / mega sale) with one-click Apply.
 *         • Bangla fields for every message — auto-shown when the visitor is on /bn/ (TranslatePress), falls back to English.
 *         • Sale notices/bubbles are suppressed for OUT-OF-STOCK and DISCONTINUED (dp-discontinued) products.
 *         • Global "any sale" detector now verifies the product is actually ON sale (was firing for future-scheduled
 *           sales) and is cached in a 10-min transient (was a meta query on every page load).
 * v1.4.6: Critical Syntax Repair & Scheduled Sale Logic.
 */

if (!defined('ABSPATH')) { exit; }

class Kohthai_Campaign_Notice_Bar {
    const OPTION_KEY = 'kohthai_campaign_notice_bar_options';
    const SALE_CACHE = 'kt_cb_any_sale_cache';

    /** Per-request memos. Both answers are asked for more than once per page. */
    private static $sale_memo = null;
    private static $will_show = null;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_shortcode('kohthai_campaign_notice', [__CLASS__, 'shortcode_top']);
        add_shortcode('kohthai_campaign_product_notice', [__CLASS__, 'shortcode_product']);

        add_filter('woocommerce_sale_flash', [__CLASS__, 'custom_smart_sale_bubble'], 99, 3);
        add_action('wp_footer', [__CLASS__, 'render_custom_css']);

        // Decided in the <head>, before the bar can be painted — see top_is_showing().
        add_action('wp_head', [__CLASS__, 'maybe_hide_topbar_css'], 1);

        // The "any sale active" answer is cached — refresh it whenever a product changes.
        add_action('save_post_product', [__CLASS__, 'flush_sale_cache']);
        add_action('woocommerce_update_product', [__CLASS__, 'flush_sale_cache']);

        // Campaign boundaries: reschedule + purge whenever the schedule is edited,
        // purge again at the moment the campaign opens and closes.
        add_action('update_option_' . self::OPTION_KEY, [__CLASS__, 'on_options_saved'], 10, 0);
        add_action('kt_cb_boundary', [__CLASS__, 'purge_caches']);
        add_action('init', [__CLASS__, 'catch_up_boundary'], 99);
    }

    /* ------------------------------------------------------------- Empty top bar */

    /**
     * Is the top notice VISIBLE right now, this second?
     *
     * Answered on the SERVER, before a byte is sent. Measuring the DOM after the
     * page had painted made the bar appear and then visibly collapse a moment later
     * — you cannot measure your way out of a flash, because by the time there is
     * something to measure it has already been drawn.
     *
     * A campaign that starts TOMORROW is showing nothing today, so this is false and
     * the bar is collapsed. The message is still pre-rendered hidden, and when the
     * browser reveals it on the minute, syncBar() switches this same rule back off
     * so the bar returns with it.
     */
    public static function top_is_showing() {
        if (self::$will_show !== null) {
            return self::$will_show;
        }
        return self::$will_show = self::compute_is_showing();
    }

    private static function compute_is_showing() {
        $opts = self::get_options();

        if ($opts['smart_sale_enabled'] === '1') {
            global $product;
            if (function_exists('is_product') && is_product() && self::is_product_on_scheduled_sale($product)
                && trim((string) self::pick($opts, 'smart_sale_top_message')) !== '') {
                return true;
            }
            if (self::any_scheduled_sale_active() && trim((string) self::pick($opts, 'smart_sale_global_top_message')) !== '') {
                return true;
            }
        }

        if ($opts['enabled'] !== '1' || trim((string) self::pick($opts, 'message')) === '') {
            return false;
        }

        // The campaign's own window is the last word: nothing is on screen before
        // it opens or after it closes.
        return self::is_active();
    }

    /**
     * Hide the theme's top bar in the <head>, so it is never painted in the first
     * place. Printed only when nothing will occupy it.
     *
     * The mode is explicit rather than measured: on this site the mobile top bar
     * holds only the campaign widget, while the desktop one also carries the menu
     * and language switcher — so blanket-hiding would take the switcher with it.
     */
    public static function maybe_hide_topbar_css() {
        $opts = self::get_options();
        $mode = $opts['hide_empty_topbar'];

        if ($mode === 'off' || is_admin()) return;
        if (self::top_is_showing())        return;

        $sel = (string) apply_filters('kt_cb_topbar_selector', '#top-bar');
        $css = $sel . '{display:none !important;}';

        if ($mode === 'mobile') {
            // Flatsome's "medium" breakpoint: below this, hide-for-medium applies.
            $bp  = (int) apply_filters('kt_cb_topbar_breakpoint', 849);
            $css = '@media (max-width:' . $bp . 'px){' . $css . '}';
        }

        echo '<style id="kt-cb-hidebar" data-no-optimize="1" data-no-minify="1">' . $css . '</style>' . "\n";
    }

    /* ------------------------------------------------------------- Schedule -> cache */

    /**
     * The campaign window as UTC timestamps. 0 means "open ended on that side".
     * Single source of truth: is_active(), the boundary cron and the browser-side
     * timer all read this, so they can never disagree about when the campaign runs.
     */
    public static function window_ts() {
        $opts = self::get_options();
        $tz_name = $opts['timezone'] ?: 'Asia/Dhaka';
        try { $tz = new DateTimeZone($tz_name); }
        catch (Exception $e) { $tz = new DateTimeZone('Asia/Dhaka'); }

        $out = ['start' => 0, 'end' => 0];
        foreach (['start', 'end'] as $k) {
            if (empty($opts[$k])) continue;
            $d = DateTime::createFromFormat('Y-m-d H:i', $opts[$k], $tz);
            if ($d instanceof DateTime) { $out[$k] = $d->getTimestamp(); }
        }
        return $out;
    }

    public static function on_options_saved() {
        self::reschedule_boundaries();
        self::purge_caches();
    }

    /**
     * One single event per boundary. The two carry different arguments on purpose:
     * wp_schedule_single_event() silently refuses a second identical event within
     * 10 minutes of the first, which would otherwise drop the "end" purge for any
     * campaign shorter than that.
     */
    public static function reschedule_boundaries() {
        wp_clear_scheduled_hook('kt_cb_boundary', ['start']);
        wp_clear_scheduled_hook('kt_cb_boundary', ['end']);

        $w   = self::window_ts();
        $now = time();
        foreach (['start', 'end'] as $which) {
            if ($w[$which] && $w[$which] > $now) {
                wp_schedule_single_event($w[$which], 'kt_cb_boundary', [$which]);
            }
        }
        // Remember what we scheduled, so a missed cron can still be detected.
        // Autoloaded on purpose: catch_up_boundary() reads it on every request, and a
        // non-autoloaded option would mean an extra database query site-wide.
        update_option('kt_cb_boundaries', ['start' => $w['start'], 'end' => $w['end'], 'done' => 0], true);
    }

    /**
     * Self-healing fallback. WP-Cron is triggered by page loads, but WP Rocket
     * serves a cached page and exits before WordPress boots — so on a well-cached
     * site the boundary event can fire late, or not at all until someone visits an
     * uncached URL. This runs on any request that DID boot WordPress and purges if
     * a boundary has quietly passed.
     */
    public static function catch_up_boundary() {
        if (wp_doing_cron()) return;

        $state = get_option('kt_cb_boundaries');
        if (!is_array($state)) return;

        $now  = time();
        $done = (int) ($state['done'] ?? 0);

        // The LATEST boundary that has passed but not yet been acted on — not the
        // first. If a whole campaign ran while cron was asleep, both its start and
        // its end are in the past, and purging once settles the page; taking the
        // first would purge again on the very next request for no benefit.
        $latest = 0;
        foreach (['start', 'end'] as $which) {
            $ts = (int) ($state[$which] ?? 0);
            if ($ts && $ts <= $now && $ts > $done && $ts > $latest) {
                $latest = $ts;
            }
        }
        if (!$latest) return;

        // Claim the boundary BEFORE purging: rocket_clean_domain() is slow, and two
        // simultaneous requests would otherwise both decide it was theirs to do.
        $state['done'] = $latest;
        update_option('kt_cb_boundaries', $state, true);
        self::purge_caches();
    }

    /**
     * Drop the page cache so the notice appears / disappears server-side too.
     * The browser-side timer already handles the visible flip instantly; this is
     * what makes the cached HTML itself correct, which matters for visitors with
     * JavaScript off and for whatever Google crawls.
     */
    public static function purge_caches() {
        self::flush_sale_cache();

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();                 // WP Rocket: whole site
        }
        if (function_exists('rocket_clean_minify')) {
            rocket_clean_minify();
        }
        // Other layers, each a no-op when the plugin is not installed.
        if (function_exists('w3tc_flush_all'))       { w3tc_flush_all(); }
        if (function_exists('wp_cache_clear_cache')) { wp_cache_clear_cache(); }
        do_action('litespeed_purge_all');

        // For anything else the site gains later (Cloudflare, a CDN plugin, …).
        do_action('kt_cb_cache_purged');
    }

    public static function defaults() {
        return [
            'enabled'                       => '1',
            'message'                       => '🎉 Eid Festival Offer — Use code <strong>EIDSTYLE10</strong>',
            'message_bn'                    => '',
            'product_message'               => '🎉 Eid Festival Offer — Use code <strong>EIDSTYLE10</strong> <a href="/eid-festival/" style="font-size:13px; color:#654321; text-decoration:none; font-weight:700;">View Offer →</a>',
            'product_message_bn'            => '',
            'start'                         => '',
            'end'                           => '',
            'timezone'                      => 'Asia/Dhaka',
            'hide_empty_topbar'             => 'mobile',  // off | mobile | always
            'allow_dismiss'                 => '1',   // let visitors close the notice
            'dismiss_days'                  => '3',   // ...and stay closed this long

            // Smart Sale Defaults
            'smart_sale_enabled'            => '1',
            'smart_sale_message'            => '🔥 <strong>Flash Sale!</strong> Save <strong style="color:#8fbc8f;">{discount_amount}</strong> on this bag. Ends on {sale_end_date}.',
            'smart_sale_message_bn'         => '',
            // Secondary tan (#a08565) for contrast against the chocolate top bar.
            'smart_sale_top_message'        => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Save <strong style="color:#a08565;">{discount_amount}</strong> on this bag today.',
            'smart_sale_top_message_bn'     => '',
            'smart_sale_global_top_message' => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> We have special discounts running right now. <a href="/shop/" style="color:#a08565; text-decoration:underline; font-weight:700;">Shop the Sale →</a>',
            'smart_sale_global_top_message_bn' => '',
        ];
    }

    /* ------------------------------------------------------------- Campaign templates */

    /**
     * Pre-installed campaign templates. Each fills a set of message fields (EN + BN);
     * the admin picks one from the dropdown, clicks Apply, edits (e.g. the landing
     * page URL or the coupon code), then saves.
     */
    public static function templates() {
        return [
            'weekly' => [
                'label'  => '🎉 Weekly offer — e.g. 5% off first purchase',
                'fields' => [
                    'message'            => '🎉 This week only: <strong>5% off</strong> your first purchase — use code <strong>WELCOME5</strong> at checkout!',
                    'product_message'    => '🎉 First purchase? Get <strong>5% off</strong> this bag with code <strong>WELCOME5</strong> at checkout.',
                ],
            ],
            'flash' => [
                'label'  => '🔥 Flash sale on a specific product (auto-detected)',
                'fields' => [
                    'smart_sale_top_message'        => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Save <strong style="color:#a08565;">{discount_amount}</strong> on this bag today.',
                    'smart_sale_global_top_message' => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Special discounts are running right now. <a href="/shop/" style="color:#a08565; text-decoration:underline; font-weight:700;">Shop the Sale →</a>',
                    'smart_sale_message'            => '🔥 <strong>Flash Sale!</strong> Save <strong style="color:#8fbc8f;">{discount_amount}</strong> on this bag. Ends on {sale_end_date}.',
                ],
            ],
            'eid' => [
                'label'  => '🌙 Big festival campaign (Eid) — with landing page link',
                'fields' => [
                    'message'            => '🌙 <strong>Eid Mubarak!</strong> Up to <strong>15% off</strong> across the store — <a href="/eid-festival/" style="color:#a08565; text-decoration:underline; font-weight:700;">See the Eid deals →</a>',
                    'product_message'    => '🌙 <strong>Eid offer</strong> on this bag — order today for delivery before Eid! <a href="/eid-festival/" style="font-size:13px; color:#654321; text-decoration:none; font-weight:700;">All Eid deals →</a>',
                ],
            ],
            /*
             * COUNTDOWN TEMPLATES
             *
             * The sentence holding {countdown} is written so it can be removed cleanly
             * when no end date is set: it opens with a trigger word the stripper looks
             * for ("Only" / "Hurry") and closes with a full stop. Keep decimals out of
             * any inline style in that sentence — a "." there would look like the end
             * of the sentence to the stripper.
             */
            'countdown' => [
                'label'  => '⏳ Countdown campaign — live timer to your End date',
                'fields' => [
                    'message'            => '⏳ <strong>Sale ends soon!</strong> Only <strong style="color:#a08565;">{countdown}</strong> left — use code <strong>SAVE10</strong> at checkout. <a href="/shop/" style="color:#a08565; text-decoration:underline; font-weight:700;">Shop now →</a>',
                    'product_message'    => '⏳ This offer ends in <strong style="color:#654321;">{countdown}</strong>. Order now to make sure you get it.',
                ],
            ],
            'lastchance' => [
                'label'  => '🚨 Final hours push — swap this in near the end',
                'fields' => [
                    'message'            => '🚨 <strong>Final hours!</strong> Hurry — only <strong style="color:#a08565;">{countdown}</strong> before prices go back up. <a href="/shop/" style="color:#a08565; text-decoration:underline; font-weight:700;">Grab yours →</a>',
                    'product_message'    => '🚨 Last chance on this bag — only <strong style="color:#654321;">{countdown}</strong> left at this price.',
                ],
            ],
            'flash_countdown' => [
                'label'  => '🔥 Flash sale + countdown (auto-detected, uses the WooCommerce sale end)',
                'fields' => [
                    'smart_sale_top_message' => '🔥 <strong style="color:#fff;">Flash Sale!</strong> Save <strong style="color:#a08565;">{discount_amount}</strong> — only <strong style="color:#a08565;">{countdown}</strong> left.',
                    'smart_sale_message'     => '🔥 <strong>Flash Sale!</strong> You save <strong style="color:#8fbc8f;">{discount_amount}</strong> on this bag. Hurry — only <strong>{countdown}</strong> left.',
                ],
            ],
            'delivery' => [
                'label'  => '🚚 Free / discounted delivery week',
                'fields' => [
                    'message'            => '🚚 <strong>Free delivery nationwide</strong> this week on every bag — no code needed!',
                    'product_message'    => '🚚 Order this week and get <strong>free delivery nationwide</strong> on this bag.',
                ],
            ],
            'mega' => [
                'label'  => '🛍️ Mega sale (11.11 / Black Friday) — with landing page link',
                'fields' => [
                    'message'            => '🛍️ <strong>MEGA SALE is live!</strong> Biggest discounts of the year — <a href="/sale/" style="color:#a08565; text-decoration:underline; font-weight:700;">Grab yours before stock runs out →</a>',
                    'product_message'    => '🛍️ <strong>Mega Sale price</strong> on this bag — limited stock! <a href="/sale/" style="font-size:13px; color:#654321; text-decoration:none; font-weight:700;">See all deals →</a>',
                ],
            ],
        ];
    }

    public static function get_options() {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) { $opts = []; }
        $opts = array_merge(self::defaults(), $opts);
        $opts['hide_empty_topbar'] = self::hide_mode($opts['hide_empty_topbar']);
        return $opts;
    }

    /**
     * Collapse mode for the theme's top bar, normalising the older on/off values
     * so a site that saved '1' before this became a three-way choice keeps working.
     */
    public static function hide_mode($value) {
        if ($value === '1' || $value === 1 || $value === true) { return 'mobile'; }
        if ($value === '0' || $value === 0 || $value === false) { return 'off'; }
        return in_array($value, ['off', 'mobile', 'always'], true) ? $value : 'mobile';
    }

    public static function register_settings() {
        register_setting('kohthai_campaign_notice_bar', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default' => self::defaults(),
        ]);
    }

    /**
     * Whitelist + sanitise.
     *
     * ⚠️ This runs on EVERY update_option() for our key, not only on a settings-page
     * save — WordPress calls it through the sanitize_option_{$key} filter. So a
     * missing field must mean "leave it alone", never "blank it": otherwise any
     * programmatic partial update would silently wipe the campaign messages and the
     * schedule. Checkboxes are the exception (an unticked box sends nothing at all),
     * so they are only read as "off" when the settings form itself was submitted,
     * which the hidden `_form` marker tells us.
     */
    public static function sanitize_options($input) {
        $out = self::get_options();
        if (!is_array($input)) { return $out; }

        $from_form = !empty($input['_form']);

        // Checkboxes: absent means off, but only on a real form submission.
        foreach (['enabled', 'smart_sale_enabled', 'allow_dismiss'] as $key) {
            if (isset($input[$key])) {
                $out[$key] = ($input[$key] === '1') ? '1' : '0';
            } elseif ($from_form) {
                $out[$key] = '0';
            }
        }

        if (isset($input['timezone'])) {
            $tz = sanitize_text_field($input['timezone']);
            // Only accept a zone PHP actually knows, so a typo cannot silently shift
            // every campaign by hours via the fallback in window_ts().
            $out['timezone'] = in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Asia/Dhaka';
        }

        foreach (['start', 'end'] as $key) {
            if (isset($input[$key])) {
                $out[$key] = self::sanitize_dt($input[$key]);
            }
        }

        if (isset($input['hide_empty_topbar'])) {
            $out['hide_empty_topbar'] = self::hide_mode($input['hide_empty_topbar']);
        }

        if (isset($input['dismiss_days'])) {
            // intval, not absint: -5 should clamp to the minimum, not become 5 days.
            $out['dismiss_days'] = (string) max(1, min(90, intval($input['dismiss_days'])));
        }

        // All message fields (EN + BN) share the same sanitizer.
        foreach (self::message_keys() as $key) {
            if (isset($input[$key])) {
                $out[$key] = wp_kses_post($input[$key]);
            }
        }

        return $out;
    }

    /** Every message option (English + its _bn twin). */
    private static function message_keys() {
        return [
            'message', 'message_bn',
            'product_message', 'product_message_bn',
            'smart_sale_message', 'smart_sale_message_bn',
            'smart_sale_top_message', 'smart_sale_top_message_bn',
            'smart_sale_global_top_message', 'smart_sale_global_top_message_bn',
        ];
    }

    private static function sanitize_dt($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') return '';
        $value = str_replace('T', ' ', $value);
        $value = preg_replace('/[^0-9:\- ]/', '', $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return '';
        }
        return $value;
    }

    /* ------------------------------------------------------------- Language (TranslatePress /bn/) */

    /** True when the visitor is browsing the Bangla site (/bn/ URLs via TranslatePress). */
    /**
     * Is the visitor reading the site in Bangla right now?
     *
     * This follows TranslatePress rather than guessing: whatever language the
     * visitor picked in the switcher is the language these notices speak. The
     * checks run most-authoritative first, so switching to Bangla on ANY page —
     * including the front page, which has no /bn/ prefix in some configurations —
     * is picked up correctly.
     *
     * Cache-safe: TranslatePress serves each language on its own URL, and WP Rocket
     * caches per URL, so the English and Bangla pages are separate cache entries.
     */
    public static function is_bn() {
        // 1. TranslatePress's own resolved language for this request (e.g. "bn_BD").
        if (isset($GLOBALS['TRP_LANGUAGE']) && is_string($GLOBALS['TRP_LANGUAGE']) && $GLOBALS['TRP_LANGUAGE'] !== '') {
            return stripos($GLOBALS['TRP_LANGUAGE'], 'bn') === 0;
        }

        // 2. Ask TranslatePress directly if the global has not been populated yet
        //    (it is set late on some requests, e.g. inside REST or AJAX).
        if (class_exists('TRP_Translate_Press')) {
            $trp = TRP_Translate_Press::get_trp_instance();
            if ($trp) {
                $settings = $trp->get_component('settings');
                if ($settings && method_exists($settings, 'get_settings')) {
                    $s = $settings->get_settings();
                    if (!empty($s['url-slugs']) && is_array($s['url-slugs'])) {
                        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
                        foreach ($s['url-slugs'] as $locale => $slug) {
                            if ($slug === '' || stripos($locale, 'bn') !== 0) continue;
                            if (preg_match('#^/' . preg_quote($slug, '#') . '(/|$|\?)#i', $uri)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        // 3. The /bn/ URL prefix, the default TranslatePress slug for Bangla.
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (preg_match('#^/bn(/|$|\?)#i', $uri)) {
            return true;
        }

        // 4. No TranslatePress at all: fall back to the site locale.
        return stripos(get_locale(), 'bn') === 0;
    }

    /** Bangla numerals, so a countdown reads naturally on the /bn/ site. */
    private static function bn_digits($text) {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            (string) $text
        );
    }

    /**
     * Pick the right language version of a message: Bangla on /bn/ when a Bangla
     * text is set, otherwise English. $used_bn reports which one was chosen.
     */
    private static function pick($opts, $key, &$used_bn = null) {
        $used_bn = false;
        if (self::is_bn()) {
            $bn = trim((string) ($opts[$key . '_bn'] ?? ''));
            if ($bn !== '') {
                $used_bn = true;
                return $bn;
            }
        }
        return trim((string) ($opts[$key] ?? ''));
    }

    /* ------------------------------------------------------------- Admin */

    /**
     * What is actually happening right now — the question you open this page to
     * answer. Returns a key, a headline, and the next thing that will change.
     */
    public static function campaign_state() {
        $opts = self::get_options();
        $w    = self::window_ts();
        $now  = time();

        if ($opts['enabled'] !== '1') {
            return ['key' => 'off', 'label' => 'Switched off', 'at' => 0,
                    'detail' => 'The global campaign notice is disabled, so nothing shows.'];
        }
        if (trim((string) $opts['message']) === '') {
            return ['key' => 'off', 'label' => 'No message set', 'at' => 0,
                    'detail' => 'The campaign is on, but the message box is empty, so nothing renders.'];
        }
        if ($w['start'] && $now < $w['start']) {
            return ['key' => 'scheduled', 'label' => 'Scheduled', 'at' => $w['start'],
                    'detail' => 'Starts in ' . human_time_diff($now, $w['start']) . '.'];
        }
        if ($w['end'] && $now >= $w['end']) {
            return ['key' => 'ended', 'label' => 'Ended', 'at' => $w['end'],
                    'detail' => 'Finished ' . human_time_diff($w['end'], $now) . ' ago — visitors see nothing.'];
        }
        if ($w['end']) {
            return ['key' => 'live', 'label' => 'Live now', 'at' => $w['end'],
                    'detail' => 'Ends in ' . human_time_diff($now, $w['end']) . '.'];
        }
        return ['key' => 'live', 'label' => 'Live now', 'at' => 0,
                'detail' => 'No end date set — this runs until you switch it off.'];
    }

    /** The coloured "what visitors see right now" card at the top of the settings page. */
    private static function status_card() {
        $opts  = self::get_options();
        $state = self::campaign_state();
        $tz    = $opts['timezone'] ?: 'Asia/Dhaka';

        $skin = [
            'live'      => ['#1a7f37', '#fdfaf7', '#bbf7d0', '🟢'],
            'scheduled' => ['#9a6700', '#fffbeb', '#fde68a', '🕒'],
            'ended'     => ['#b42318', '#fef2f2', '#fecaca', '🔴'],
            'off'       => ['#57606a', '#f6f8fa', '#d0d7de', '⚪'],
        ];
        list($fg, $bg, $border, $dot) = $skin[$state['key']];

        // What would actually render on a normal page right now? A running sale
        // outranks the campaign, and that surprises people.
        $override = '';
        if ($state['key'] === 'live' && $opts['smart_sale_enabled'] === '1' && self::any_scheduled_sale_active()) {
            $override = 'A scheduled product sale is also running, and on the shop and home pages the '
                      . '<strong>Global Sale</strong> message takes priority over this campaign.';
        }

        echo '<div style="background:' . esc_attr($bg) . ';border:1px solid ' . esc_attr($border) . ';'
           . 'border-left:5px solid ' . esc_attr($fg) . ';border-radius:6px;padding:14px 18px;margin:16px 0;max-width:900px;">';

        echo '<div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">';
        echo '<span style="font-size:17px;">' . $dot . '</span>';
        echo '<strong style="font-size:16px;color:' . esc_attr($fg) . ';">' . esc_html($state['label']) . '</strong>';
        echo '<span style="color:#444;">' . esc_html($state['detail']) . '</span>';
        echo '</div>';

        if ($state['at']) {
            echo '<p style="margin:8px 0 0;color:#646970;font-size:12px;">'
               . esc_html(wp_date('l, j F Y \a\t g:i a', $state['at'], new DateTimeZone($tz)))
               . ' &middot; ' . esc_html($tz) . '</p>';
        }

        if ($state['key'] === 'ended') {
            echo '<p style="margin:10px 0 0;color:' . esc_attr($fg) . ';">'
               . '<strong>This campaign is over.</strong> Clear the end date to run it again, '
               . 'set a new one, or untick <em>Enable Global Campaign</em> to tidy up.</p>';
        }
        if ($override !== '') {
            echo '<p style="margin:10px 0 0;color:#9a6700;">⚠️ ' . wp_kses_post($override) . '</p>';
        }

        echo '</div>';
    }

    public static function admin_menu() {
        add_options_page(
            'Kohthai Campaign Notice Bar',
            'Kohthai Campaign Bar',
            'manage_options',
            'kohthai-campaign-notice-bar',
            [__CLASS__, 'settings_page']
        );
    }

    /** A message textarea + its Bangla twin, rendered as one settings row. */
    /**
     * Is a Bangla translation layer actually installed?
     *
     * KOHTHAI: this site runs English only — no TranslatePress, no /bn/. The Bangla
     * engine is kept intact (is_bn() simply answers false, so the English text is
     * used everywhere) but its ADMIN fields are hidden, because five collapsed
     * "বাংলা version" toggles for a language the store does not serve is just noise.
     *
     * Deliberately not deleted: keeping this file structurally identical to
     * aun-campaign-bar.php is what makes the next port a rename instead of a
     * rewrite. Install TranslatePress and the fields reappear on their own.
     *
     * Stored *_bn values survive either way — sanitize_options() only overwrites a
     * key when it is actually present in the POST (isset), so a hidden field is
     * left alone rather than blanked.
     */
    private static function bn_available() {
        return class_exists('TRP_Translate_Press')
            || (isset($GLOBALS['TRP_LANGUAGE']) && is_string($GLOBALS['TRP_LANGUAGE']) && $GLOBALS['TRP_LANGUAGE'] !== '');
    }

    private static function field_pair($opts, $key, $rows, $desc) {
        $name    = self::OPTION_KEY;
        $bn_val  = (string) $opts[$key . '_bn'];
        $has_bn  = trim($bn_val) !== '';

        echo '<textarea id="kt-cb-' . esc_attr($key) . '" name="' . esc_attr($name) . '[' . esc_attr($key) . ']" rows="' . (int) $rows . '" class="large-text">' . esc_textarea($opts[$key]) . '</textarea>';
        if ($desc !== '') {
            echo '<p class="description">' . $desc . '</p>';
        }

        // No translation layer on this site -> the English box is the whole field.
        if (!self::bn_available() && !$has_bn) {
            return;
        }

        // The Bangla twin is collapsed unless it already has text. Showing every
        // pair expanded doubled the length of this page even when only the English
        // was being edited; a field with content is never hidden from you.
        echo '<div class="kt-cb-bnwrap" style="margin-top:8px;">';
        echo '<button type="button" class="button-link kt-cb-bntoggle" aria-expanded="' . ($has_bn ? 'true' : 'false') . '"'
           . ' data-target="kt-cb-' . esc_attr($key) . '_bn_box"'
           . ' style="text-decoration:none;font-weight:600;color:#4a3b31;display:inline-flex;align-items:center;gap:6px;">'
           . '<span class="kt-cb-caret" style="display:inline-block;transition:transform .15s ease;' . ($has_bn ? 'transform:rotate(90deg);' : '') . '">&#9656;</span>'
           . 'বাংলা version'
           . '<span style="font-weight:normal;color:' . ($has_bn ? '#4a3b31' : '#646970') . ';">'
           . ($has_bn ? '&nbsp;&#10003; set' : '&nbsp;&mdash; not set, English will be reused')
           . '</span>'
           . '</button>';
        echo '<div id="kt-cb-' . esc_attr($key) . '_bn_box" class="kt-cb-bnbox"' . ($has_bn ? '' : ' hidden') . ' style="margin-top:6px;">';
        echo '<p class="description" style="margin:0 0 4px;">Shown when the visitor switches the site to বাংলা (TranslatePress). Leave empty to reuse the English text.</p>';
        echo '<textarea id="kt-cb-' . esc_attr($key) . '_bn" name="' . esc_attr($name) . '[' . esc_attr($key) . '_bn]" rows="' . (int) $rows . '" class="large-text" style="border-color:#d9cbbd;">' . esc_textarea($bn_val) . '</textarea>';
        echo '</div></div>';
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $opts = self::get_options();
        $tz_list = [
            'Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Bangkok', 'Asia/Singapore', 'UTC',
        ];
        $templates = self::templates();
        ?>
        <div class="wrap">
            <h1>Kohthai Campaign Notice Settings</h1>
            <p>Manage your synchronized campaign notices across your website.</p>

            <?php self::status_card(); ?>

            <!-- CAMPAIGN TEMPLATES -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #654321;border-radius:4px;padding:14px 18px;margin:16px 0;max-width:900px;">
                <h2 style="margin:0 0 6px;">📋 Campaign templates</h2>
                <p style="margin:0 0 10px;color:#555;">Pick a ready-made campaign, click <strong>Apply</strong> to fill the matching message boxes below (English + বাংলা), then edit the coupon code / landing page link and <strong>Save Changes</strong>.</p>
                <select id="kt-cb-template" style="min-width:340px;">
                    <option value="">— Choose a template —</option>
                    <?php foreach ($templates as $tkey => $t): ?>
                        <option value="<?php echo esc_attr($tkey); ?>"><?php echo esc_html($t['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary" id="kt-cb-apply">Apply template</button>
                <span id="kt-cb-applied" style="display:none;color:#4a3b31;font-weight:600;margin-left:8px;">✓ Filled — review below, then Save Changes.</span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('kohthai_campaign_notice_bar'); ?>
                <?php /* Tells sanitize_options() that unticked checkboxes really mean "off". */ ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[_form]" value="1" />

                <table class="form-table" role="presentation">

                    <!-- PRODUCT SPECIFIC SMART SALE SECTION -->
                    <tr><th colspan="2" style="padding-top: 20px;">
                        <h2 style="margin: 0; color: #8fbc8f;">🏷️ Product-Specific Smart Sales</h2>
                        <p style="font-weight: normal; margin-top: 5px; color: #555;">These settings automatically detect when you put a specific bag on sale natively inside WooCommerce (via Sale Price). It calculates the discount mathematically and overrides the global campaign to show a highly-targeted sale notice and dynamic Sale Bubble. <strong>Out-of-stock and discontinued products are skipped automatically.</strong></p>
                    </th></tr>

                    <tr style="background: #fdfaf7; border-left: 3px solid #8fbc8f;">
                        <th scope="row" style="padding-left: 15px;">Enable Smart Sales</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[smart_sale_enabled]" value="1" <?php checked($opts['smart_sale_enabled'], '1'); ?> />
                                Automatically detect sales, inject exact discount text, and add a pulsing Sale Bubble.
                            </label>
                        </td>
                    </tr>
                    <tr style="background: #fdfaf7; border-left: 3px solid #8fbc8f;">
                        <th scope="row" style="padding-left: 15px;">Top Bar (Specific Product)</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_top_message', 2, 'Replaces the Global Top Bar notice <strong>only</strong> when the customer is looking at the actual on-sale product.'); ?>
                        </td>
                    </tr>
                    <tr style="background: #fdfaf7; border-left: 3px solid #8fbc8f;">
                        <th scope="row" style="padding-left: 15px;">Top Bar (Shop/Global Magnet)</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_global_top_message', 2, 'Shows on the Homepage/Shop when at least one in-stock product is on a sale that has an <strong>end date</strong> set '
                                . '(WooCommerce &rarr; product &rarr; Sale price dates). A sale with no end date does not trigger it, because '
                                . 'there would be no deadline to create any urgency.'); ?>
                        </td>
                    </tr>
                    <tr style="background: #fdfaf7; border-left: 3px solid #8fbc8f;">
                        <th scope="row" style="padding-left: 15px;">Product Page Smart Sale Message</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_message', 3,
                                '<strong>Magic Tags:</strong><br>'
                                . '<code>{discount_amount}</code> = Automatically shows the exact Taka saved (e.g., ৳3,500).<br>'
                                . '<code>{sale_end_date}</code> = Automatically pulls the "Sale price dates" end date from WooCommerce.<br>'
                                . '<code>{countdown}</code> = A live ticking countdown to that same sale end time.'); ?>
                        </td>
                    </tr>

                    <!-- GLOBAL SITEWIDE CAMPAIGN SECTION -->
                    <tr><th colspan="2" style="padding-top: 40px;">
                        <h2 style="margin: 0; color: #654321;">🌐 Global Sitewide Campaign</h2>
                        <p style="font-weight: normal; margin-top: 5px; color: #555;">These settings trigger a blue site-wide notice (like an Eid or Black Friday campaign) for the rest of your website.</p>
                    </th></tr>

                    <tr>
                        <th scope="row">Enable Global Campaign</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($opts['enabled'], '1'); ?> />
                                Show global scheduled notices across the site
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Global Top Bar Notice</th>
                        <td>
                            <?php self::field_pair($opts, 'message', 3,
                                'Used with shortcode <code>[kohthai_campaign_notice]</code>.<br>'
                                . '<strong>Magic tag:</strong> <code>{countdown}</code> = a live ticking &ldquo;time left&rdquo; '
                                . 'counting to the <strong>End date &amp; time</strong> below (e.g. <code>2d 04:11:09</code>, '
                                . 'shown in Bangla numerals on the বাংলা site). It keeps counting correctly even on a cached page. '
                                . 'With no end date set, the tag and its sentence are removed automatically.'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Global Product Page Notice</th>
                        <td>
                            <?php self::field_pair($opts, 'product_message', 3, 'Used with shortcode <code>[kohthai_campaign_product_notice]</code> when a product is NOT on a specific sale. Hidden automatically on out-of-stock / discontinued products.'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Timezone</th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[timezone]">
                                <?php foreach ($tz_list as $tz): ?>
                                    <option value="<?php echo esc_attr($tz); ?>" <?php selected($opts['timezone'], $tz); ?>>
                                        <?php echo esc_html($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Global Start date &amp; time</th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr(self::OPTION_KEY); ?>[start]" value="<?php echo esc_attr(self::to_dt_local($opts['start'])); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Global End date &amp; time</th>
                        <td>
                            <input type="datetime-local" name="<?php echo esc_attr(self::OPTION_KEY); ?>[end]" value="<?php echo esc_attr(self::to_dt_local($opts['end'])); ?>" />
                            <p class="description">✅ <strong>Starts and ends on the minute.</strong> The notice is timed in the
                               visitor's browser, so WP Rocket's cache no longer delays it, and the cache is purged automatically
                               at the start and end times as well (so the cached HTML is right for crawlers and for anyone with
                               JavaScript off). Saving this page also purges the cache immediately.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Let visitors close it</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[allow_dismiss]" value="1" <?php checked($opts['allow_dismiss'], '1'); ?> />
                                Show a small &times; on the notice
                            </label>
                            <p style="margin:6px 0 0;">
                                Stay hidden for
                                <input type="number" min="1" max="90" class="small-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[dismiss_days]" value="<?php echo esc_attr((int) $opts['dismiss_days']); ?>" />
                                days after they close it.
                            </p>
                            <p class="description">Only for that one visitor, in that browser. The memory is tied to
                               <strong>this</strong> campaign &mdash; edit the message or the dates and everyone sees the
                               new one straight away, so closing one offer never mutes the next.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Empty top bar</th>
                        <td>
                            <?php $hm = $opts['hide_empty_topbar']; ?>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[hide_empty_topbar]" style="min-width:320px;">
                                <option value="mobile" <?php selected($hm, 'mobile'); ?>>Hide it on phones and tablets only (recommended)</option>
                                <option value="always" <?php selected($hm, 'always'); ?>>Hide it on every screen size</option>
                                <option value="off"    <?php selected($hm, 'off'); ?>>Never hide it</option>
                            </select>
                            <p class="description">When no campaign is running, the HTML widget holding <code>[kohthai_campaign_notice]</code>
                               prints nothing and the Flatsome top bar is left as an empty coloured strip.</p>
                            <p class="description"><strong>Phones and tablets only</strong> is right for this site: the mobile top bar contains
                               just the campaign widget, while the desktop one also carries the Top Bar Menu and the language switcher —
                               hiding that everywhere would take the switcher with it. Choose <strong>every screen size</strong> only if the
                               desktop top bar holds nothing else.</p>
                            <p class="description">The bar is hidden in the page&rsquo;s <code>&lt;head&gt;</code>, so it is never drawn and
                               there is no flicker. Below <?php echo (int) apply_filters('kt_cb_topbar_breakpoint', 849); ?>px counts as mobile
                               (filter <code>kt_cb_topbar_breakpoint</code>); the bar itself is matched with <code><?php echo esc_html(apply_filters('kt_cb_topbar_selector', '#top-bar')); ?></code>
                               (filter <code>kt_cb_topbar_selector</code>).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Changes'); ?>
            </form>

            <hr />
            <h2>Live Previews</h2>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Specific Product Sale)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">If the user is viewing an on-sale product, the top bar changes to this.</p>
                    <div style="padding:12px;border:1px solid #4a3b31;border-radius:8px;background:#654321; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Shop/Global Magnet)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">Shows on the Homepage when an in-stock product is on a sale that has an end date set.</p>
                    <div style="padding:12px;border:1px solid #4a3b31;border-radius:8px;background:#654321; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'global_sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Global Campaign Mode)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">The default message shown if NO products are on sale.</p>
                    <div style="padding:12px;border:1px solid #4a3b31;border-radius:8px;background:#654321; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'global'); ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 30px;">
                <div style="flex: 1; min-width: 300px;">
                    <h3>Product Page (Smart Sale Mode)</h3>
                    <div style="max-width:100%;">
                        <?php echo self::render_product_notice(true, 'sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Product Page (Global Mode)</h3>
                    <div style="max-width:100%;">
                        <?php echo self::render_product_notice(true, 'global'); ?>
                    </div>
                </div>
            </div>

        </div>
        <script>
        (function(){
            var TPL = <?php echo wp_json_encode(array_map(function ($t) { return $t['fields']; }, $templates)); ?>;
            var sel = document.getElementById('kt-cb-template');
            var btn = document.getElementById('kt-cb-apply');
            var ok  = document.getElementById('kt-cb-applied');
            if (!sel || !btn) return;
            btn.addEventListener('click', function(){
                var key = sel.value;
                if (!key || !TPL[key]) { alert('Choose a template first.'); return; }
                var fields = TPL[key];
                var touched = [];
                for (var f in fields) {
                    var el = document.getElementById('kt-cb-' + f);
                    if (!el) continue;
                    if (el.value.trim() !== '' && el.value !== fields[f]) {
                        touched.push(f);
                    }
                }
                if (touched.length && !confirm('This will replace the current text in ' + touched.length + ' message box(es). Continue?')) {
                    return;
                }
                for (var f2 in fields) {
                    var el2 = document.getElementById('kt-cb-' + f2);
                    if (el2) { el2.value = fields[f2]; }
                }
                if (ok) { ok.style.display = 'inline'; setTimeout(function(){ ok.style.display = 'none'; }, 6000); }
                /* A template fills the বাংলা boxes too, so open any it wrote into —
                   otherwise the text lands somewhere the admin cannot see. */
                document.querySelectorAll('.kt-cb-bnbox').forEach(function(box){
                    var ta = box.querySelector('textarea');
                    if (ta && ta.value.trim() !== '') { openBn(box, true); }
                });
            });
        })();

        /* Collapsible বাংলা fields. */
        (function(){
            function setCaret(btn, open){
                var caret = btn.querySelector('.kt-cb-caret');
                if (caret) { caret.style.transform = open ? 'rotate(90deg)' : 'none'; }
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            window.openBn = function(box, open){
                box.hidden = !open;
                var btn = document.querySelector('[data-target="' + box.id + '"]');
                if (btn) { setCaret(btn, open); }
            };
            document.querySelectorAll('.kt-cb-bntoggle').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var box = document.getElementById(btn.getAttribute('data-target'));
                    if (!box) return;
                    var open = box.hidden;
                    window.openBn(box, open);
                    if (open) {
                        var ta = box.querySelector('textarea');
                        if (ta) { ta.focus(); }
                    }
                });
            });
        })();
        </script>
        <?php
    }

    private static function to_dt_local($stored) {
        if (!is_string($stored) || $stored === '') return '';
        return str_replace(' ', 'T', $stored);
    }

    /* ------------------------------------------------------------- Schedule + product checks */

    private static function is_active() {
        $opts = self::get_options();
        if ($opts['enabled'] !== '1') return false;

        // Reads window_ts() rather than re-parsing the dates, so the server, the
        // boundary cron and the browser timer can never disagree by a minute.
        $w   = self::window_ts();
        $now = time();

        if ($w['start'] && $now < $w['start']) return false;
        if ($w['end']   && $now >= $w['end'])  return false;

        return true;
    }

    /**
     * Can this product be promoted at all? A discount notice on a product the
     * customer cannot buy (out of stock, or marked discontinued by the
     * WooCommerce Discontinued Products plugin) is misleading — skip those.
     */
    private static function is_promotable($product) {
        if (!$product || !is_a($product, 'WC_Product')) return false;
        if (!$product->is_in_stock()) return false;
        $pid = $product->get_id();
        if (taxonomy_exists('product_discontinued') && has_term('dp-discontinued', 'product_discontinued', $pid)) {
            return false;
        }
        return true;
    }

    /**
     * How much money this product actually saves the customer.
     *
     * Variable products were computed as (min regular price − min sale price),
     * which mixes figures from DIFFERENT variations: the cheapest regular price
     * and the cheapest sale price need not belong to the same one. That could
     * inflate the saving, understate it, or go negative — and this number is
     * printed to customers as "Save ৳X", so a wrong one is a trust problem.
     * Every on-sale variation is now measured on its own and the best genuine
     * saving is used.
     */
    private static function saving_for($product) {
        if (!$product || !is_a($product, 'WC_Product')) return 0.0;

        if ($product->is_type('variable')) {
            $best = 0.0;
            foreach ($product->get_children() as $vid) {
                $v = wc_get_product($vid);
                if (!$v || !$v->is_on_sale()) continue;
                $diff = (float) $v->get_regular_price() - (float) $v->get_price();
                if ($diff > $best) { $best = $diff; }
            }
            return $best;
        }

        return (float) $product->get_regular_price() - (float) $product->get_price();
    }

    private static function is_product_on_scheduled_sale($product) {
        if (!$product || !is_a($product, 'WC_Product') || !$product->is_on_sale()) {
            return false;
        }
        if (!self::is_promotable($product)) {
            return false;
        }
        if ($product->get_date_on_sale_to()) {
            return true;
        }
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->is_on_sale() && $variation->get_date_on_sale_to()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Is ANY promotable product on an active scheduled sale right now?
     * Cached for 10 minutes (this used to run a meta query on every page load)
     * and verified with is_on_sale() so a future-scheduled sale no longer
     * triggers the "Flash Sale Active!" banner early.
     */
    private static function any_scheduled_sale_active() {
        // Per-request memo: wp_head asks (top_is_showing) and so does the shortcode.
        if (self::$sale_memo !== null) {
            return self::$sale_memo;
        }

        // An AUTOLOADED option, not a transient. A transient with an expiry is not
        // autoloaded, so reading it cost two extra queries on every cache-miss page
        // just to answer a question that is usually "no".
        $cache = get_option(self::SALE_CACHE);
        if (is_array($cache) && isset($cache['v'], $cache['exp']) && (int) $cache['exp'] > time()) {
            return self::$sale_memo = (bool) $cache['v'];
        }

        $active = false;
        if (function_exists('wc_get_products')) {
            $ids = wc_get_products([
                'status'       => 'publish',
                'limit'        => 10,
                'return'       => 'ids',
                'stock_status' => 'instock',
                'meta_query'   => [
                    [
                        'key'     => '_sale_price_dates_to',
                        'value'   => time(),
                        'compare' => '>=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]);
            foreach ((array) $ids as $pid) {
                $p = wc_get_product($pid);
                if ($p && $p->is_on_sale() && self::is_promotable($p)) {
                    $active = true;
                    break;
                }
            }
        }

        update_option(self::SALE_CACHE, ['v' => $active ? 1 : 0, 'exp' => time() + 10 * MINUTE_IN_SECONDS], true);
        return self::$sale_memo = $active;
    }

    public static function flush_sale_cache() {
        self::$sale_memo = null;
        self::$will_show = null;
        delete_option(self::SALE_CACHE);
        delete_transient('kt_cb_any_sale');   // left over from before 1.8.1
    }

    /* ------------------------------------------------------------- Sale bubble + CSS */

    public static function custom_smart_sale_bubble($html, $post, $product) {
        $opts = self::get_options();

        if ($opts['smart_sale_enabled'] !== '1') {
            return $html;
        }

        // On-sale but out of stock / discontinued: hide the sale flash entirely —
        // a pulsing discount badge on an unbuyable product just creates complaints.
        if ($product && is_a($product, 'WC_Product') && $product->is_on_sale() && !self::is_promotable($product)) {
            return '';
        }

        if (!self::is_product_on_scheduled_sale($product)) {
            return $html;
        }

        // Calculate exact mathematical savings
        $saved = self::saving_for($product);

        if ($saved <= 0) return $html;

        $saved_text = wp_strip_all_tags(wc_price($saved));
        $custom_text = 'Save<br>' . esc_html($saved_text);

        // Inject our custom text and classes directly into Flatsome's native badge HTML
        // so Flatsome stacks it perfectly with its own "New" badge.
        if (strpos($html, 'badge-inner') !== false) {
            // $ and \ are special in a preg_replace REPLACEMENT string, so a currency
            // symbol like "$" would be read as a backreference and mangle the badge.
            $safe_replacement = str_replace(['\\', '$'], ['\\\\', '\\$'], $custom_text);
            $html = preg_replace('/<span class="onsale">.*?<\/span>/i', '<span class="onsale">' . $safe_replacement . '</span>', $html);
            $html = str_replace('badge-inner', 'badge-inner kohthai-smart-sale-bubble', $html);
            return $html;
        }

        return '<div class="callout badge badge-circle"><div class="badge-inner secondary on-sale kohthai-smart-sale-bubble"><span class="onsale">' . $custom_text . '</span></div></div>';
    }

    public static function render_custom_css() {
        $opts = self::get_options();
        if ($opts['smart_sale_enabled'] !== '1') return;
        ?>
        <style data-no-optimize="1" data-no-minify="1">
            .badge-inner.kohthai-smart-sale-bubble {
                position: relative !important;
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
                color: #ffffff !important;
                border: none !important;
                box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4) !important;
                display: flex !important;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
                line-height: 1.1 !important;
                padding: 4px !important;
                z-index: 9;
            }
            .badge-inner.kohthai-smart-sale-bubble .onsale {
                font-size: 11px !important;
                font-weight: 800 !important;
                letter-spacing: 0 !important;
                display: block;
                position: relative;
                z-index: 2;
            }
            .badge-inner.kohthai-smart-sale-bubble::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                border-radius: 50%;
                background: rgba(239, 68, 68, 0.5);
                z-index: 1;
                animation: kohthaiSmoothRipple 2s cubic-bezier(0.25, 0.8, 0.25, 1) infinite;
                pointer-events: none;
            }
            @keyframes kohthaiSmoothRipple {
                0% { transform: scale(1); opacity: 1; }
                100% { transform: scale(1.6); opacity: 0; }
            }
        </style>
        <?php
    }

    /* ------------------------------------------------------------- Shortcodes */

    /** Which branch produced the last top notice: sale_specific | sale_global | campaign | '' */
    private static $top_source = '';

    /** So the wrapper's CSS + helper JS is printed once even with several shortcodes. */
    private static $top_printed = false;

    /** Counter giving each notice on the page its own id for the timer to find. */
    private static $top_seq = 0;

    public static function shortcode_top($atts = [], $content = null) {
        self::$top_source = '';
        $html = self::render_top_notice(false, 'auto');

        // PRE-RENDER: the campaign has not opened yet, but this page may well be
        // sitting in WP Rocket's cache when it does. Emit the message hidden so the
        // browser can reveal it exactly on time instead of waiting for a cache
        // rebuild. Only when nothing else claimed the bar.
        $prerender = false;
        if ($html === '' && self::$top_source === '') {
            $opts = self::get_options();
            $w    = self::window_ts();
            if ($opts['enabled'] === '1' && $w['start'] && time() < $w['start']) {
                $msg = self::pick($opts, 'message');
                if ($msg !== '') {
                    $html      = wp_kses_post($msg);
                    $prerender = true;
                    self::$top_source = 'campaign';
                }
            }
        }

        // When this renders nothing, nothing is emitted at all: maybe_hide_topbar_css()
        // has already collapsed the bar in the <head>, so there is no work left for
        // the browser and no marker to leave behind.
        return self::wrap_top($html, $prerender);
    }


    /**
     * Wrap the campaign notice so the browser can flip it on the exact minute.
     *
     * WHY THIS EXISTS: the notice used to appear and disappear whenever WP Rocket
     * happened to rebuild the page, which could be hours late. The schedule is now
     * enforced in the browser from data attributes, so caching stops mattering for
     * the visible result. The cron purge (see purge_caches) still corrects the
     * cached HTML itself for no-JS visitors and crawlers.
     *
     * Sale-driven notices are returned untouched — those depend on product data,
     * not on a clock, and already have their own invalidation.
     */
    private static function wrap_top($html, $prerender = false) {
        if ($html === '') return '';
        if (self::$top_source !== 'campaign') return $html;

        $opts = self::get_options();
        $w    = self::window_ts();

        $dismissible = ($opts['allow_dismiss'] === '1');
        $timed       = ($w['start'] || $w['end']);

        // An open-ended campaign that also cannot be dismissed has nothing for the
        // browser to do, so it stays plain markup — no wrapper, no script.
        if (!$timed && !$dismissible) {
            return $html;
        }

        $out = '';
        if (!self::$top_printed) {
            self::$top_printed = true;
            $out .= self::top_style();
        }

        $id = 'kt-cb-' . (++self::$top_seq);

        // The dismissal is remembered against a signature of THIS campaign, so
        // editing the message or the dates makes it a new campaign and everyone
        // sees it again. Closing one offer must not silently mute the next.
        $sig = substr(md5($w['start'] . '|' . $w['end'] . '|' . $html), 0, 12);

        $close = '';
        if ($dismissible) {
            $close = '<button type="button" class="kt-cb-x" aria-label="'
                   . esc_attr(self::is_bn() ? 'বন্ধ করুন' : 'Dismiss this notice')
                   . '">&times;</button>';
        }

        $out .= sprintf(
            '<span id="%s" class="kt-cb-top" data-start="%d" data-end="%d" data-sig="%s" data-mute="%d"%s>'
            . '<span class="kt-cb-msg">%s</span>%s</span>',
            esc_attr($id),
            $w['start'],
            $w['end'],
            esc_attr($sig),
            $dismissible ? (int) $opts['dismiss_days'] : 0,
            $prerender ? ' hidden' : '',
            $html,
            $close
        );

        $out .= self::top_script($id);
        return $out;
    }

    /** Styling for the notice wrapper. Inherits the theme's bar colours on purpose. */
    private static function top_style() {
        return '<style data-no-optimize="1" data-no-minify="1">'
            . '.kt-cb-top[hidden]{display:none !important;}'
            . '.kt-cb-bar-empty{display:none !important;}'
            . '.kt-cb-top{display:inline-flex;align-items:center;gap:10px;max-width:100%;}'
            . '.kt-cb-msg{min-width:0;}'
            /* Tabular figures stop the countdown jittering as the digits change. */
            . '.kt-cb-cd{font-variant-numeric:tabular-nums;font-feature-settings:"tnum";'
            . 'white-space:nowrap;font-weight:700;}'
            . '.kt-cb-x{all:unset;cursor:pointer;line-height:1;font-size:18px;opacity:.6;'
            . 'padding:0 2px;flex:0 0 auto;transition:opacity .15s ease;}'
            . '.kt-cb-x:hover,.kt-cb-x:focus-visible{opacity:1;}'
            . '.kt-cb-x:focus-visible{outline:2px solid currentColor;outline-offset:2px;border-radius:3px;}'
            . '</style>';
    }

    /**
     * The inline timer. Deliberately synchronous and placed straight after the
     * element: it settles visibility during parse, before first paint, so an
     * out-of-window notice never flashes and the top bar never shifts the page
     * (layout shift at the very top of the document is the worst kind).
     */
    private static function top_script($id) {
        $opts     = self::get_options();
        $bar_mode = $opts['hide_empty_topbar'];
        $bar_sel  = (string) apply_filters('kt_cb_topbar_selector', '#top-bar');
        $bar_bp   = (int) apply_filters('kt_cb_topbar_breakpoint', 849);

        ob_start();
        ?>
<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
(function(){
  /* Looked up by id, never by walking back from this script tag: the widget's
     content passes through wpautop, which can slip a <p> or <br> in between and
     would silently break a sibling walk. */
  var el = document.getElementById(<?php echo wp_json_encode($id); ?>);
  if(!el) return;

  var start = parseInt(el.getAttribute('data-start')||'0',10),
      end   = parseInt(el.getAttribute('data-end')||'0',10),
      sig   = el.getAttribute('data-sig')||'',
      mute  = parseInt(el.getAttribute('data-mute')||'0',10);

  /* ---- Dismissal ------------------------------------------------------
     Remembered against this campaign's signature, so closing one offer never
     mutes the next: change the message or the dates and it is a new key. */
  var MUTE_KEY = 'ktCbMute:' + sig;
  function isMuted(){
    if(!mute) return false;
    try{
      var until = parseInt(localStorage.getItem(MUTE_KEY)||'0',10);
      return until && Date.now() < until;
    }catch(e){ return false; }
  }
  function remember(){
    try{ localStorage.setItem(MUTE_KEY, String(Date.now() + mute*86400000)); }catch(e){}
  }

  /* ---- Countdown ------------------------------------------------------
     Mirrors countdown_text() in PHP. Driven from an absolute deadline, so a
     cached page still counts down correctly. */
  var BN = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function bn(t){ return t.replace(/[0-9]/g, function(d){ return BN[+d]; }); }

  function countdownText(until, isBn){
    var left = Math.max(0, until - Math.floor(Date.now()/1000)),
        d    = Math.floor(left/86400),
        txt  = pad(Math.floor((left%86400)/3600)) + ':' +
               pad(Math.floor((left%3600)/60)) + ':' + pad(left%60);
    if(d > 0){ txt = d + (isBn ? ' দিন ' : 'd ') + txt; }
    return isBn ? bn(txt) : txt;
  }

  var clocks = el.querySelectorAll('.kt-cb-cd'), cdTimer;
  function tickClocks(){
    if(!clocks.length) return;
    var anyLeft = false;
    for(var i=0;i<clocks.length;i++){
      var until = parseInt(clocks[i].getAttribute('data-until')||'0',10);
      if(!until) continue;
      clocks[i].textContent = countdownText(until, clocks[i].getAttribute('data-lang') === 'bn');
      if(until > Math.floor(Date.now()/1000)) anyLeft = true;
    }
    clearTimeout(cdTimer);
    /* Stop ticking once every clock has run out — and when the tab is hidden,
       so a backgrounded tab is not woken every second for nothing. */
    if(anyLeft && !el.hidden && document.visibilityState === 'visible'){
      cdTimer = setTimeout(tickClocks, 1000);
    }
  }
  document.addEventListener('visibilitychange', function(){
    if(document.visibilityState === 'visible'){ tick(); }
  });

  /* ---- The theme's top bar -------------------------------------------
     Nothing is measured here. maybe_hide_topbar_css() already decided in the
     <head> whether the bar should be hidden, so the page is painted correctly
     the first time. All this does is flip that decision if the notice appears
     or disappears while the visitor has the page open — at a campaign boundary,
     or when they press the dismiss button. */
  var BAR_MODE = <?php echo wp_json_encode($bar_mode); ?>,
      BAR_SEL  = <?php echo wp_json_encode($bar_sel); ?>,
      BAR_BP   = <?php echo (int) $bar_bp; ?>;

  function barStyle(){
    var st = document.getElementById('kt-cb-hidebar');
    if(st) return st;
    if(BAR_MODE === 'off') return null;
    st = document.createElement('style');
    st.id = 'kt-cb-hidebar';
    st.textContent = (BAR_MODE === 'mobile')
      ? '@media (max-width:' + BAR_BP + 'px){' + BAR_SEL + '{display:none !important;}}'
      : BAR_SEL + '{display:none !important;}';
    document.head.appendChild(st);
    return st;
  }

  function syncBar(){
    if(BAR_MODE === 'off') return;
    var st = barStyle();
    if(!st) return;
    /* disabled, not removed: flipping it back on costs nothing and keeps the
       rule in one place. */
    st.disabled = !el.hidden;
  }

  var timer;
  function tick(){
    var now = Date.now()/1000;
    var inWindow = (!start || now >= start) && (!end || now < end);
    el.hidden = !inWindow || isMuted();
    syncBar();
    tickClocks();

    /* Re-check at the next boundary, so a tab left open across the start or end
       time updates itself without a reload. Only worth arming inside a day. */
    var next = 0;
    if(start && now < start)   next = start - now;
    else if(end && now < end)  next = end - now;
    clearTimeout(timer);
    if(next > 0 && next < 86400){
      timer = setTimeout(tick, next*1000 + 500);
    }
  }

  var x = el.querySelector('.kt-cb-x');
  if(x){
    x.addEventListener('click', function(){
      remember();
      el.hidden = true;
      clearTimeout(cdTimer);
      syncBar();
    });
  }

  tick();
})();
</script>
        <?php
        return ob_get_clean();
    }

    public static function shortcode_product($atts = [], $content = null) {
        return self::render_product_notice(false, 'auto');
    }

    /* ------------------------------------------------------------- Render logic */

    /** Replace {sale_end_date}; when there is no end date, drop that sentence / soften the wording (EN + BN aware). */
    private static function fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn, $end_ts = 0) {
        $msg = str_replace('{discount_amount}', $saved_amt_html, $msg);
        if ($end_date_formatted) {
            $msg = str_replace('{sale_end_date}', $end_date_formatted, $msg);
        } else {
            $msg = preg_replace('/[^.!?।]*(?:Ends on|Valid until|অফার শেষ)[^.!?।]*\{sale_end_date\}[^.!?।]*[.!?।]/iu', '', $msg);
            $msg = str_replace('{sale_end_date}', $used_bn ? 'শীঘ্রই' : 'soon', $msg);
        }
        return self::inject_countdown($msg, $end_ts, $used_bn);
    }

    /**
     * {countdown} -> a live, ticking "time left" element.
     *
     * The element carries the deadline as an absolute UTC timestamp and the browser
     * recomputes it, so a page sitting in WP Rocket's cache still shows the right
     * number. The server still renders a value so that visitors without JavaScript,
     * and crawlers, see something sensible rather than a blank.
     */
    private static function inject_countdown($msg, $end_ts, $used_bn = false) {
        if (strpos($msg, '{countdown}') === false) {
            return $msg;
        }

        // No deadline to count to: drop the whole "ends in …" clause rather than
        // leaving a dangling sentence, the same way {sale_end_date} does.
        if (!$end_ts || $end_ts <= time()) {
            $msg = preg_replace('/[^.!?।]*(?:Ends in|Only|Hurry|শেষ হতে|বাকি আছে)[^.!?।]*\{countdown\}[^.!?।]*[.!?।]/iu', '', $msg);
            return str_replace('{countdown}', '', $msg);
        }

        $el = '<span class="kt-cb-cd" data-until="' . (int) $end_ts . '" data-lang="' . ($used_bn ? 'bn' : 'en') . '">'
            . esc_html(self::countdown_text($end_ts, $used_bn))
            . '</span>';

        return str_replace('{countdown}', $el, $msg);
    }

    /**
     * "2d 04:11:09", or Bangla "২ দিন ০৪:১১:০৯".
     * The JavaScript in top_script() reproduces this exactly — keep them in step.
     */
    private static function countdown_text($end_ts, $bn = false) {
        $left = max(0, (int) $end_ts - time());
        $d    = (int) floor($left / DAY_IN_SECONDS);
        $clock = sprintf(
            '%02d:%02d:%02d',
            (int) floor(($left % DAY_IN_SECONDS) / HOUR_IN_SECONDS),
            (int) floor(($left % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS),
            (int) ($left % MINUTE_IN_SECONDS)
        );

        $txt = $d > 0 ? $d . ($bn ? ' দিন ' : 'd ') . $clock : $clock;
        return $bn ? self::bn_digits($txt) : $txt;
    }

    private static function render_top_notice($force = false, $preview_type = 'auto') {
        $opts = self::get_options();
        global $product;

        $show_sale_specific = false;
        $show_sale_global = false;
        $show_global = false;
        $saved_amt_html = '';
        $end_date_formatted = '';

        if ($force) {
            if ($preview_type === 'sale') {
                $show_sale_specific = ($opts['smart_sale_enabled'] === '1');
                $saved_amt_html = function_exists('wc_price') ? wc_price(2500) : '৳2,500';
                $end_date_formatted = date('j M Y', strtotime('+3 days'));
            } elseif ($preview_type === 'global_sale') {
                $show_sale_global = ($opts['smart_sale_enabled'] === '1');
            } else {
                $show_global = ($opts['enabled'] === '1');
            }
        } else {
            // Context Aware Checks: Is this a product page AND is the product on a scheduled sale?
            if ($opts['smart_sale_enabled'] === '1' && function_exists('is_product') && is_product() && self::is_product_on_scheduled_sale($product)) {
                $show_sale_specific = true;

                $saved = self::saving_for($product);
                $saved_amt_html = wc_price($saved);

                $end_ts = '';
                if ($product->get_date_on_sale_to()) {
                    $end_ts = $product->get_date_on_sale_to()->getTimestamp();
                } elseif ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->is_on_sale() && $variation->get_date_on_sale_to()) {
                            $end_ts = $variation->get_date_on_sale_to()->getTimestamp();
                            break;
                        }
                    }
                }
                $end_date_formatted = $end_ts ? wp_date('j M Y', $end_ts) : '';

            } elseif ($opts['smart_sale_enabled'] === '1' && self::any_scheduled_sale_active()) {
                // Customer is on shop/home, and at least one buyable product is on sale
                $show_sale_global = true;
            } elseif (self::is_active()) {
                $show_global = true;
            }
        }

        if (!$show_sale_specific && !$show_sale_global && !$show_global) {
            return $force ? '<em>Notice is disabled or inactive.</em>' : '';
        }

        if ($show_sale_specific) {
            $used_bn = false;
            $msg = $force ? trim((string)$opts['smart_sale_top_message']) : self::pick($opts, 'smart_sale_top_message', $used_bn);
            if (empty($msg)) return $force ? '<em>No Smart Sale Top Bar message set.</em>' : '';
            $msg = self::fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn, $end_ts);
            self::$top_source = 'sale_specific';
            return wp_kses_post($msg);

        } elseif ($show_sale_global) {
            $msg = $force ? trim((string)$opts['smart_sale_global_top_message']) : self::pick($opts, 'smart_sale_global_top_message');
            if (empty($msg)) return $force ? '<em>No Global Sale Alert message set.</em>' : '';
            self::$top_source = 'sale_global';
            return wp_kses_post($msg);

        } elseif ($show_global) {
            $used_bn = false;
            $msg = $force ? trim((string)$opts['message']) : self::pick($opts, 'message', $used_bn);
            if (empty($msg)) return $force ? '<em>No message set.</em>' : '';
            self::$top_source = 'campaign';
            $w = self::window_ts();
            return self::inject_countdown(wp_kses_post($msg), $force ? time() + 2 * DAY_IN_SECONDS : $w['end'], $used_bn);
        }

        return '';
    }

    private static function render_product_notice($force = false, $preview_type = 'auto') {
        $opts = self::get_options();
        global $product;

        $show_sale = false;
        $show_global = false;
        $saved_amt_html = '';
        $end_date_formatted = '';

        if ($force) {
            if ($preview_type === 'sale') {
                $show_sale = ($opts['smart_sale_enabled'] === '1');
                $saved_amt_html = function_exists('wc_price') ? wc_price(2500) : '৳2,500';
                $end_date_formatted = date('j M Y', strtotime('+3 days'));
            } else {
                $show_global = ($opts['enabled'] === '1');
            }
        } else {
            // Never show a discount/campaign notice on a product the customer can't buy.
            if (function_exists('is_product') && is_product() && $product && is_a($product, 'WC_Product') && !self::is_promotable($product)) {
                return '';
            }

            if ($opts['smart_sale_enabled'] === '1' && self::is_product_on_scheduled_sale($product)) {
                $show_sale = true;

                $saved = self::saving_for($product);
                $saved_amt_html = wc_price($saved);

                $end_ts = '';
                if ($product->get_date_on_sale_to()) {
                    $end_ts = $product->get_date_on_sale_to()->getTimestamp();
                } elseif ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->is_on_sale() && $variation->get_date_on_sale_to()) {
                            $end_ts = $variation->get_date_on_sale_to()->getTimestamp();
                            break;
                        }
                    }
                }
                $end_date_formatted = $end_ts ? wp_date('j M Y', $end_ts) : '';

            } elseif (self::is_active()) {
                $show_global = true;
            }
        }

        if (!$show_sale && !$show_global) {
            return $force ? '<em>Notice is disabled or inactive.</em>' : '';
        }

        $message = '';
        $icon = '';
        $theme_color = '';
        $bg_color = '';
        $border_color = '';

        if ($show_sale) {
            $used_bn = false;
            $msg = $force ? trim((string)$opts['smart_sale_message']) : self::pick($opts, 'smart_sale_message', $used_bn);
            if (empty($msg)) return $force ? '<em>No Smart Sale message set.</em>' : '';

            $message = self::fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn, $end_ts);
            $icon = 'fa-tags';
            $theme_color = '#8fbc8f'; // Green
            $bg_color = '#fdfaf7';
            $border_color = '#efe7dd';

        } elseif ($show_global) {
            $used_bn = false;
            $message = $force ? trim((string)$opts['product_message']) : self::pick($opts, 'product_message', $used_bn);
            if (empty($message)) return $force ? '<em>No global product message set.</em>' : '';

            // {countdown} works here too, counting to the campaign's own end date —
            // otherwise a template using the tag would print it raw on product pages.
            $w       = self::window_ts();
            $message = self::inject_countdown($message, $force ? time() + 2 * DAY_IN_SECONDS : $w['end'], $used_bn);

            $icon = 'fa-gift';
            $theme_color = '#654321'; // Blue
            $bg_color = '#f6f8fb';
            $border_color = '#e9eef5';
        }

        // Robust two-column flex layout (icon + text) that behaves on mobile.
        $html = '<div style="margin:12px 0 14px; padding:10px 12px; border:1px solid ' . $border_color . '; border-left:4px solid ' . $theme_color . '; border-radius:8px; background:' . $bg_color . '; font-size:14px; color:#555; display:flex; align-items:flex-start; gap:10px;">';
        $html .= '  <span style="color:' . $theme_color . '; font-size:16px; flex-shrink:0; margin-top:2px;">';
        $html .= '    <i class="fa-solid ' . $icon . '"></i>';
        $html .= '  </span>';
        $html .= '  <span style="flex:1; line-height:1.5;">';
        $html .= do_shortcode(wp_kses_post($message));
        $html .= '  </span>';
        $html .= '</div>';

        return $html;
    }
}

Kohthai_Campaign_Notice_Bar::init();

/**
 * The boundary timer must not be deferred or delayed: it runs during parse so the
 * notice settles before first paint. If WP Rocket holds it back the bar flashes in
 * and then vanishes, which looks worse than being late.
 */
add_filter('rocket_delay_js_exclusions', function ($excluded) {
    $excluded[] = 'kt-cb-top';
    return $excluded;
});
add_filter('rocket_excluded_inline_js_content', function ($excluded) {
    $excluded[] = 'kt-cb-top';
    return $excluded;
});

register_activation_hook(__FILE__, function () {
    Kohthai_Campaign_Notice_Bar::reschedule_boundaries();
    Kohthai_Campaign_Notice_Bar::purge_caches();
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('kt_cb_boundary', ['start']);
    wp_clear_scheduled_hook('kt_cb_boundary', ['end']);
    Kohthai_Campaign_Notice_Bar::purge_caches();
});
