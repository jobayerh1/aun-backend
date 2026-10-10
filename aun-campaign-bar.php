<?php
/**
 * Plugin Name: AUN Campaign Notice Bar
 * Description: Scheduled campaign notices via shortcodes. Context-Aware Top Bars, Global Sale Detection, Flatsome badge integration, pre-installed campaign templates, Bangla (/bn/, TranslatePress) auto-switch, and stock/discontinued awareness.
 * Version: 1.10.0
 * Author: Smart Living Bangladesh
 *
 * v1.10.0: • CAMPAIGN POPUP BANNER, built in. Replaces the hand-pasted Flatsome [lightbox auto_open] code that had
 *           to be added and removed by hand for every campaign. It follows the campaign's own switch and start/end
 *           time, and behaves like the popups on the big shops: opens after N seconds, X% scrolled or on exit intent
 *           (computers); never while typing, over another open window, in a background tab or before the image has
 *           loaded; once per visit; N days of quiet after a close; never again for that campaign once tapped, the
 *           code is copied or an order is placed; never on cart/checkout/account/order pages or the page it links to.
 *           One-tap coupon copy, live countdown, bottom sheet with swipe-to-close on phones, separate phone and
 *           বাংলা artwork, admin preview, and shown / tapped / copied / closed counts per campaign.
 *         • FIX: WP Rocket's "Delay JavaScript execution" was holding back the top bar's timer until the visitor's
 *           first tap or scroll (live HTML: type="text/rocketlazyloadscript"). The exclusion pattern 'aun-cb-top'
 *           never appeared inside that script, so it never matched. Until that first interaction the countdown
 *           stood still, a closed notice came back, and a scheduled one stayed hidden. The marker is now in the script.
 * v1.9.0: • CAMPAIGN NOW OUTRANKS THE SALE MAGNET (behaviour change). Previously, if ANY product had a
 *           scheduled sale with an end date, the automatic "Flash Sale Active!" message replaced a
 *           global campaign on EVERY page except the discounted product's own — one discounted
 *           accessory silently killed a site-wide campaign you had deliberately scheduled.
 *           New "When both are running" setting; default 'campaign', set to 'sale' for the old order.
 *           A product on sale still always wins on its own page: most specific wins where it is
 *           specific, broadest wins everywhere else.
 *         • Either side is skipped when its own message box is empty, so an unfilled field can no
 *           longer blank the bar while the other option had something to say.
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
 *         • Selector and breakpoint are filterable (aun_cb_topbar_selector, aun_cb_topbar_breakpoint).
 * v1.7.1: FIX — the empty top bar was not collapsing. The collapse ran from the notice's own inline
 *           script, so when there was NO notice to render (campaign off, ended, or no message) nothing
 *           was output and no JavaScript existed to do the job — exactly the case it was built for.
 *           A hidden marker + script is now emitted whenever "Empty top bar" is on. The bar lookup also
 *           uses Element.closest() against a filterable selector (aun_cb_topbar_selector).
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

class AUN_Campaign_Notice_Bar {
    const OPTION_KEY = 'aun_campaign_notice_bar_options';
    const SALE_CACHE = 'aun_cb_any_sale_cache';

    /** Per-request memos. Both answers are asked for more than once per page. */
    private static $sale_memo = null;
    private static $will_show = null;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_shortcode('aun_campaign_notice', [__CLASS__, 'shortcode_top']);
        add_shortcode('aun_campaign_product_notice', [__CLASS__, 'shortcode_product']);

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
        add_action('aun_cb_boundary', [__CLASS__, 'purge_caches']);
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

        $sel = (string) apply_filters('aun_cb_topbar_selector', '#top-bar');
        $css = $sel . '{display:none !important;}';

        if ($mode === 'mobile') {
            // Flatsome's "medium" breakpoint: below this, hide-for-medium applies.
            $bp  = (int) apply_filters('aun_cb_topbar_breakpoint', 849);
            $css = '@media (max-width:' . $bp . 'px){' . $css . '}';
        }

        echo '<style id="aun-cb-hidebar" data-no-optimize="1" data-no-minify="1">' . $css . '</style>' . "\n";
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
        wp_clear_scheduled_hook('aun_cb_boundary', ['start']);
        wp_clear_scheduled_hook('aun_cb_boundary', ['end']);

        $w   = self::window_ts();
        $now = time();
        foreach (['start', 'end'] as $which) {
            if ($w[$which] && $w[$which] > $now) {
                wp_schedule_single_event($w[$which], 'aun_cb_boundary', [$which]);
            }
        }
        // Remember what we scheduled, so a missed cron can still be detected.
        // Autoloaded on purpose: catch_up_boundary() reads it on every request, and a
        // non-autoloaded option would mean an extra database query site-wide.
        update_option('aun_cb_boundaries', ['start' => $w['start'], 'end' => $w['end'], 'done' => 0], true);
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

        $state = get_option('aun_cb_boundaries');
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
        update_option('aun_cb_boundaries', $state, true);
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
        do_action('aun_cb_cache_purged');
    }

    public static function defaults() {
        return array_merge(AUN_CB_Popup::defaults(), [
            'enabled'                       => '1',
            'message'                       => '🎉 Offer — Use code <strong>EIDBIGSCREEN</strong> (Ends 20 Mar)',
            'message_bn'                    => '🎉 অফার — কোড <strong>EIDBIGSCREEN</strong> ব্যবহার করুন (২০ মার্চ পর্যন্ত)',
            'product_message'               => '🎉 Offer — Use code <strong>EIDBIGSCREEN</strong> <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">Details →</a>',
            'product_message_bn'            => '🎉 অফার — কোড <strong>EIDBIGSCREEN</strong> ব্যবহার করুন <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">বিস্তারিত →</a>',
            'start'                         => '',
            'end'                           => '',
            'timezone'                      => 'Asia/Dhaka',
            'hide_empty_topbar'             => 'mobile',  // off | mobile | always
            'notice_priority'               => 'campaign', // campaign | sale
            'allow_dismiss'                 => '1',   // let visitors close the notice
            'dismiss_days'                  => '3',   // ...and stay closed this long

            // Smart Sale Defaults
            'smart_sale_enabled'            => '1',
            'smart_sale_message'            => '🔥 <strong>Flash Sale!</strong> Save <strong style="color:#16a34a;">{discount_amount}</strong> on this model. Ends on {sale_end_date}.',
            'smart_sale_message_bn'         => '🔥 <strong>ফ্ল্যাশ সেল!</strong> এই মডেলে <strong style="color:#16a34a;">{discount_amount}</strong> সাশ্রয় করুন। অফার শেষ {sale_end_date}।',
            'smart_sale_top_message'        => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Save <strong style="color:#fde047;">{discount_amount}</strong> on this model today.',
            'smart_sale_top_message_bn'     => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> আজই এই মডেলে <strong style="color:#fde047;">{discount_amount}</strong> সাশ্রয় করুন।',
            'smart_sale_global_top_message' => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> We have special discounts running right now. <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">Shop the Sale →</a>',
            'smart_sale_global_top_message_bn' => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> এখনই বিশেষ ছাড় চলছে। <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">সেল দেখুন →</a>',
        ]);
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
                    'message_bn'         => '🎉 শুধু এই সপ্তাহে: প্রথম কেনাকাটায় <strong>৫% ছাড়</strong> — চেকআউটে কোড <strong>WELCOME5</strong> ব্যবহার করুন!',
                    'product_message'    => '🎉 First purchase? Get <strong>5% off</strong> this projector with code <strong>WELCOME5</strong> at checkout.',
                    'product_message_bn' => '🎉 প্রথম কেনাকাটা? চেকআউটে <strong>WELCOME5</strong> কোড দিয়ে এই প্রজেক্টরে <strong>৫% ছাড়</strong> নিন।',
                ],
            ],
            'flash' => [
                'label'  => '🔥 Flash sale on a specific product (auto-detected)',
                'fields' => [
                    'smart_sale_top_message'           => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Save <strong style="color:#fde047;">{discount_amount}</strong> on this model today.',
                    'smart_sale_top_message_bn'        => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> আজই এই মডেলে <strong style="color:#fde047;">{discount_amount}</strong> সাশ্রয় করুন।',
                    'smart_sale_global_top_message'    => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Special discounts are running right now. <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">Shop the Sale →</a>',
                    'smart_sale_global_top_message_bn' => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> এখনই বিশেষ ছাড় চলছে। <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">সেল দেখুন →</a>',
                    'smart_sale_message'               => '🔥 <strong>Flash Sale!</strong> Save <strong style="color:#16a34a;">{discount_amount}</strong> on this model. Ends on {sale_end_date}.',
                    'smart_sale_message_bn'            => '🔥 <strong>ফ্ল্যাশ সেল!</strong> এই মডেলে <strong style="color:#16a34a;">{discount_amount}</strong> সাশ্রয় করুন। অফার শেষ {sale_end_date}।',
                ],
            ],
            'eid' => [
                'label'  => '🌙 Big festival campaign (Eid) — with landing page link',
                'fields' => [
                    'message'            => '🌙 <strong>Eid Mubarak!</strong> Up to <strong>15% off</strong> across the store — <a href="/eid-festival/" style="color:#fde047; text-decoration:underline; font-weight:700;">See the Eid deals →</a>',
                    'message_bn'         => '🌙 <strong>ঈদ মোবারক!</strong> পুরো স্টোরে <strong>১৫% পর্যন্ত ছাড়</strong> — <a href="/eid-festival/" style="color:#fde047; text-decoration:underline; font-weight:700;">ঈদ অফার দেখুন →</a>',
                    'product_message'    => '🌙 <strong>Eid offer</strong> on this projector — order today for delivery before Eid! <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">All Eid deals →</a>',
                    'product_message_bn' => '🌙 এই প্রজেক্টরে <strong>ঈদ অফার</strong> — ঈদের আগে ডেলিভারি পেতে আজই অর্ডার করুন! <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">সব ঈদ অফার →</a>',
                ],
            ],
            /*
             * COUNTDOWN TEMPLATES
             *
             * The sentence holding {countdown} is written so it can be removed cleanly
             * when no end date is set: it opens with a trigger word the stripper looks
             * for ("Only" / "Hurry" / "শেষ হতে") and closes with a full stop or danda.
             * Keep decimals out of any inline style in that sentence — a "." there
             * would look like the end of the sentence to the stripper.
             */
            'countdown' => [
                'label'  => '⏳ Countdown campaign — live timer to your End date',
                'fields' => [
                    'message'            => '⏳ <strong>Sale ends soon!</strong> Only <strong style="color:#fde047;">{countdown}</strong> left — use code <strong>SAVE10</strong> at checkout. <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">Shop now →</a>',
                    'message_bn'         => '⏳ <strong>অফার শেষ হয়ে আসছে!</strong> শেষ হতে বাকি <strong style="color:#fde047;">{countdown}</strong> — চেকআউটে <strong>SAVE10</strong> কোড ব্যবহার করুন। <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">এখনই কিনুন →</a>',
                    'product_message'    => '⏳ This offer ends in <strong style="color:#0188fe;">{countdown}</strong>. Order now to make sure you get it.',
                    'product_message_bn' => '⏳ অফার শেষ হতে বাকি <strong style="color:#0188fe;">{countdown}</strong>। নিশ্চিত করতে এখনই অর্ডার করুন।',
                ],
            ],
            'lastchance' => [
                'label'  => '🚨 Final hours push — swap this in near the end',
                'fields' => [
                    'message'            => '🚨 <strong>Final hours!</strong> Hurry — only <strong style="color:#fde047;">{countdown}</strong> before prices go back up. <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">Grab yours →</a>',
                    'message_bn'         => '🚨 <strong>শেষ সুযোগ!</strong> দাম বাড়ার আগে শেষ হতে বাকি <strong style="color:#fde047;">{countdown}</strong>। <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">এখনই নিন →</a>',
                    'product_message'    => '🚨 Last chance on this model — only <strong style="color:#0188fe;">{countdown}</strong> left at this price.',
                    'product_message_bn' => '🚨 এই মডেলে শেষ সুযোগ — এই দামে বাকি <strong style="color:#0188fe;">{countdown}</strong>।',
                ],
            ],
            'flash_countdown' => [
                'label'  => '🔥 Flash sale + countdown (auto-detected, uses the WooCommerce sale end)',
                'fields' => [
                    'smart_sale_top_message'           => '🔥 <strong style="color:#fff;">Flash Sale!</strong> Save <strong style="color:#fde047;">{discount_amount}</strong> — only <strong style="color:#fde047;">{countdown}</strong> left.',
                    'smart_sale_top_message_bn'        => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল!</strong> <strong style="color:#fde047;">{discount_amount}</strong> সাশ্রয় করুন — শেষ হতে বাকি <strong style="color:#fde047;">{countdown}</strong>।',
                    'smart_sale_message'               => '🔥 <strong>Flash Sale!</strong> You save <strong style="color:#16a34a;">{discount_amount}</strong> on this model. Hurry — only <strong>{countdown}</strong> left.',
                    'smart_sale_message_bn'            => '🔥 <strong>ফ্ল্যাশ সেল!</strong> এই মডেলে <strong style="color:#16a34a;">{discount_amount}</strong> সাশ্রয়। শেষ হতে বাকি <strong>{countdown}</strong>।',
                ],
            ],
            'delivery' => [
                'label'  => '🚚 Free / discounted delivery week',
                'fields' => [
                    'message'            => '🚚 <strong>Free delivery nationwide</strong> this week on every projector — no code needed!',
                    'message_bn'         => '🚚 এই সপ্তাহে প্রতিটি প্রজেক্টরে <strong>সারাদেশে ফ্রি ডেলিভারি</strong> — কোনো কোড লাগবে না!',
                    'product_message'    => '🚚 Order this week and get <strong>free delivery nationwide</strong> on this projector.',
                    'product_message_bn' => '🚚 এই সপ্তাহে অর্ডার করলে এই প্রজেক্টরে <strong>সারাদেশে ফ্রি ডেলিভারি</strong>।',
                ],
            ],
            'mega' => [
                'label'  => '🛍️ Mega sale (11.11 / Black Friday) — with landing page link',
                'fields' => [
                    'message'            => '🛍️ <strong>MEGA SALE is live!</strong> Biggest discounts of the year — <a href="/sale/" style="color:#fde047; text-decoration:underline; font-weight:700;">Grab yours before stock runs out →</a>',
                    'message_bn'         => '🛍️ <strong>মেগা সেল চলছে!</strong> বছরের সবচেয়ে বড় ছাড় — <a href="/sale/" style="color:#fde047; text-decoration:underline; font-weight:700;">স্টক শেষ হওয়ার আগেই নিন →</a>',
                    'product_message'    => '🛍️ <strong>Mega Sale price</strong> on this model — limited stock! <a href="/sale/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">See all deals →</a>',
                    'product_message_bn' => '🛍️ এই মডেলে <strong>মেগা সেল মূল্য</strong> — সীমিত স্টক! <a href="/sale/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">সব অফার দেখুন →</a>',
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
        register_setting('aun_campaign_notice_bar', self::OPTION_KEY, [
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
        foreach (['enabled', 'smart_sale_enabled', 'allow_dismiss', 'popup_enabled', 'popup_countdown', 'popup_exit'] as $key) {
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

        if (isset($input['notice_priority'])) {
            $out['notice_priority'] = ($input['notice_priority'] === 'sale') ? 'sale' : 'campaign';
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

        // The popup banner's own fields (same rule: absent means leave alone).
        $out = AUN_CB_Popup::sanitize($input, $out);

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
            'live'      => ['#1a7f37', '#f0fdf4', '#bbf7d0', '🟢'],
            'scheduled' => ['#9a6700', '#fffbeb', '#fde68a', '🕒'],
            'ended'     => ['#b42318', '#fef2f2', '#fecaca', '🔴'],
            'off'       => ['#57606a', '#f6f8fa', '#d0d7de', '⚪'],
        ];
        list($fg, $bg, $border, $dot) = $skin[$state['key']];

        // What would actually render on a normal page right now? A running sale
        // outranks the campaign, and that surprises people.
        $override = '';
        if ($state['key'] === 'live' && $opts['smart_sale_enabled'] === '1' && self::any_scheduled_sale_active()) {
            $override = ($opts['notice_priority'] === 'sale')
                ? 'A scheduled product sale is also running, and with the current priority the '
                  . '<strong>Global Sale</strong> message <strong>replaces this campaign everywhere</strong> '
                  . 'except the discounted product&rsquo;s own page. Switch the priority below if that is not what you want.'
                : 'A scheduled product sale is also running. This campaign takes priority, so the '
                  . '<strong>Global Sale</strong> message is held back until the campaign ends. The discounted '
                  . 'product&rsquo;s own page still shows its specific sale notice.';
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
        if (in_array($state['key'], ['live', 'scheduled'], true)) {
            echo '<p style="margin:10px 0 0;color:#444;">🪧 ' . esc_html(AUN_CB_Popup::status_line($opts)) . '</p>';
        }

        echo '</div>';
    }

    public static function admin_menu() {
        add_options_page(
            'AUN Campaign Notice Bar',
            'AUN Campaign Bar',
            'manage_options',
            'aun-campaign-notice-bar',
            [__CLASS__, 'settings_page']
        );
    }

    /** A message textarea + its Bangla twin, rendered as one settings row. */
    private static function field_pair($opts, $key, $rows, $desc) {
        $name    = self::OPTION_KEY;
        $bn_val  = (string) $opts[$key . '_bn'];
        $has_bn  = trim($bn_val) !== '';

        echo '<textarea id="aun-cb-' . esc_attr($key) . '" name="' . esc_attr($name) . '[' . esc_attr($key) . ']" rows="' . (int) $rows . '" class="large-text">' . esc_textarea($opts[$key]) . '</textarea>';
        if ($desc !== '') {
            echo '<p class="description">' . $desc . '</p>';
        }

        // The Bangla twin is collapsed unless it already has text. Showing every
        // pair expanded doubled the length of this page even when only the English
        // was being edited; a field with content is never hidden from you.
        echo '<div class="aun-cb-bnwrap" style="margin-top:8px;">';
        echo '<button type="button" class="button-link aun-cb-bntoggle" aria-expanded="' . ($has_bn ? 'true' : 'false') . '"'
           . ' data-target="aun-cb-' . esc_attr($key) . '_bn_box"'
           . ' style="text-decoration:none;font-weight:600;color:#0f6c2f;display:inline-flex;align-items:center;gap:6px;">'
           . '<span class="aun-cb-caret" style="display:inline-block;transition:transform .15s ease;' . ($has_bn ? 'transform:rotate(90deg);' : '') . '">&#9656;</span>'
           . 'বাংলা version'
           . '<span style="font-weight:normal;color:' . ($has_bn ? '#0f6c2f' : '#646970') . ';">'
           . ($has_bn ? '&nbsp;&#10003; set' : '&nbsp;&mdash; not set, English will be reused')
           . '</span>'
           . '</button>';
        echo '<div id="aun-cb-' . esc_attr($key) . '_bn_box" class="aun-cb-bnbox"' . ($has_bn ? '' : ' hidden') . ' style="margin-top:6px;">';
        echo '<p class="description" style="margin:0 0 4px;">Shown when the visitor switches the site to বাংলা (TranslatePress). Leave empty to reuse the English text.</p>';
        echo '<textarea id="aun-cb-' . esc_attr($key) . '_bn" name="' . esc_attr($name) . '[' . esc_attr($key) . '_bn]" rows="' . (int) $rows . '" class="large-text" style="border-color:#9fd4ae;">' . esc_textarea($bn_val) . '</textarea>';
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
            <h1>AUN Campaign Notice Settings</h1>
            <p>Manage your synchronized campaign notices across your website.</p>

            <?php self::status_card(); ?>

            <!-- CAMPAIGN TEMPLATES -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #0188fe;border-radius:4px;padding:14px 18px;margin:16px 0;max-width:900px;">
                <h2 style="margin:0 0 6px;">📋 Campaign templates</h2>
                <p style="margin:0 0 10px;color:#555;">Pick a ready-made campaign, click <strong>Apply</strong> to fill the matching message boxes below (English + বাংলা), then edit the coupon code / landing page link and <strong>Save Changes</strong>.</p>
                <select id="aun-cb-template" style="min-width:340px;">
                    <option value="">— Choose a template —</option>
                    <?php foreach ($templates as $tkey => $t): ?>
                        <option value="<?php echo esc_attr($tkey); ?>"><?php echo esc_html($t['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary" id="aun-cb-apply">Apply template</button>
                <span id="aun-cb-applied" style="display:none;color:#0f6c2f;font-weight:600;margin-left:8px;">✓ Filled — review below, then Save Changes.</span>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('aun_campaign_notice_bar'); ?>
                <?php /* Tells sanitize_options() that unticked checkboxes really mean "off". */ ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[_form]" value="1" />

                <table class="form-table" role="presentation">

                    <!-- PRODUCT SPECIFIC SMART SALE SECTION -->
                    <tr><th colspan="2" style="padding-top: 20px;">
                        <h2 style="margin: 0; color: #16a34a;">🏷️ Product-Specific Smart Sales</h2>
                        <p style="font-weight: normal; margin-top: 5px; color: #555;">These settings automatically detect when you put a specific projector on sale natively inside WooCommerce (via Sale Price). It calculates the discount mathematically and overrides the global campaign to show a highly-targeted green notice and dynamic Sale Bubble. <strong>Out-of-stock and discontinued products are skipped automatically.</strong></p>
                    </th></tr>

                    <tr style="background: #f0fdf4; border-left: 3px solid #16a34a;">
                        <th scope="row" style="padding-left: 15px;">Enable Smart Sales</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[smart_sale_enabled]" value="1" <?php checked($opts['smart_sale_enabled'], '1'); ?> />
                                Automatically detect sales, inject exact discount text, and add a pulsing Sale Bubble.
                            </label>
                        </td>
                    </tr>
                    <tr style="background: #f0fdf4; border-left: 3px solid #16a34a;">
                        <th scope="row" style="padding-left: 15px;">Top Bar (Specific Product)</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_top_message', 2, 'Replaces the Global Top Bar notice <strong>only</strong> when the customer is looking at the actual on-sale product.'); ?>
                        </td>
                    </tr>
                    <tr style="background: #f0fdf4; border-left: 3px solid #16a34a;">
                        <th scope="row" style="padding-left: 15px;">Top Bar (Shop/Global Magnet)</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_global_top_message', 2, 'Shows on the Homepage/Shop when at least one in-stock product is on a sale that has an <strong>end date</strong> set '
                                . '(WooCommerce &rarr; product &rarr; Sale price dates). A sale with no end date does not trigger it, because '
                                . 'there would be no deadline to create any urgency.'); ?>
                        </td>
                    </tr>
                    <tr style="background: #f0fdf4; border-left: 3px solid #16a34a;">
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
                        <h2 style="margin: 0; color: #0188fe;">🌐 Global Sitewide Campaign</h2>
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
                                'Used with shortcode <code>[aun_campaign_notice]</code>.<br>'
                                . '<strong>Magic tag:</strong> <code>{countdown}</code> = a live ticking &ldquo;time left&rdquo; '
                                . 'counting to the <strong>End date &amp; time</strong> below (e.g. <code>2d 04:11:09</code>, '
                                . 'shown in Bangla numerals on the বাংলা site). It keeps counting correctly even on a cached page. '
                                . 'With no end date set, the tag and its sentence are removed automatically.'); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Global Product Page Notice</th>
                        <td>
                            <?php self::field_pair($opts, 'product_message', 3, 'Used with shortcode <code>[aun_campaign_product_notice]</code> when a product is NOT on a specific sale. Hidden automatically on out-of-stock / discontinued products.'); ?>
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
                        <th scope="row">When both are running</th>
                        <td>
                            <?php $np = $opts['notice_priority']; ?>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[notice_priority]" style="min-width:340px;">
                                <option value="campaign" <?php selected($np, 'campaign'); ?>>Show the campaign (recommended)</option>
                                <option value="sale"     <?php selected($np, 'sale'); ?>>Show the &ldquo;Flash Sale Active!&rdquo; message</option>
                            </select>
                            <p class="description">Which message wins the top bar when a global campaign <em>and</em> a scheduled
                               product sale are both live.</p>
                            <p class="description">A product on sale <strong>always</strong> shows its own specific notice on its own
                               page &mdash; this only decides what appears everywhere else. Keeping the campaign first means one
                               discounted item cannot quietly replace a campaign you scheduled; the sale message then acts as a
                               fallback that fills the bar when no campaign is running.</p>
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
                            <p class="description">When no campaign is running, the HTML widget holding <code>[aun_campaign_notice]</code>
                               prints nothing and the Flatsome top bar is left as an empty coloured strip.</p>
                            <p class="description"><strong>Phones and tablets only</strong> is right for this site: the mobile top bar contains
                               just the campaign widget, while the desktop one also carries the Top Bar Menu and the language switcher —
                               hiding that everywhere would take the switcher with it. Choose <strong>every screen size</strong> only if the
                               desktop top bar holds nothing else.</p>
                            <p class="description">The bar is hidden in the page&rsquo;s <code>&lt;head&gt;</code>, so it is never drawn and
                               there is no flicker. Below <?php echo (int) apply_filters('aun_cb_topbar_breakpoint', 849); ?>px counts as mobile
                               (filter <code>aun_cb_topbar_breakpoint</code>); the bar itself is matched with <code><?php echo esc_html(apply_filters('aun_cb_topbar_selector', '#top-bar')); ?></code>
                               (filter <code>aun_cb_topbar_selector</code>).</p>
                        </td>
                    </tr>

                    <?php AUN_CB_Popup::settings_rows($opts); ?>
                </table>

                <?php submit_button('Save Changes'); ?>
            </form>

            <hr />
            <h2>Live Previews</h2>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Specific Product Sale)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">If the user is viewing an on-sale product, the top bar changes to this.</p>
                    <div style="padding:12px;border:1px solid #006bc7;border-radius:8px;background:#0188fe; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Shop/Global Magnet)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">Shows on the Homepage when an in-stock product is on a sale that has an end date set.</p>
                    <div style="padding:12px;border:1px solid #006bc7;border-radius:8px;background:#0188fe; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'global_sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Global Campaign Mode)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">The default message shown if NO products are on sale.</p>
                    <div style="padding:12px;border:1px solid #006bc7;border-radius:8px;background:#0188fe; color:#fff; font-weight: 500;">
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
            var sel = document.getElementById('aun-cb-template');
            var btn = document.getElementById('aun-cb-apply');
            var ok  = document.getElementById('aun-cb-applied');
            if (!sel || !btn) return;
            btn.addEventListener('click', function(){
                var key = sel.value;
                if (!key || !TPL[key]) { alert('Choose a template first.'); return; }
                var fields = TPL[key];
                var touched = [];
                for (var f in fields) {
                    var el = document.getElementById('aun-cb-' + f);
                    if (!el) continue;
                    if (el.value.trim() !== '' && el.value !== fields[f]) {
                        touched.push(f);
                    }
                }
                if (touched.length && !confirm('This will replace the current text in ' + touched.length + ' message box(es). Continue?')) {
                    return;
                }
                for (var f2 in fields) {
                    var el2 = document.getElementById('aun-cb-' + f2);
                    if (el2) { el2.value = fields[f2]; }
                }
                if (ok) { ok.style.display = 'inline'; setTimeout(function(){ ok.style.display = 'none'; }, 6000); }
                /* A template fills the বাংলা boxes too, so open any it wrote into —
                   otherwise the text lands somewhere the admin cannot see. */
                document.querySelectorAll('.aun-cb-bnbox').forEach(function(box){
                    var ta = box.querySelector('textarea');
                    if (ta && ta.value.trim() !== '') { openBn(box, true); }
                });
            });
        })();

        /* Collapsible বাংলা fields. */
        (function(){
            function setCaret(btn, open){
                var caret = btn.querySelector('.aun-cb-caret');
                if (caret) { caret.style.transform = open ? 'rotate(90deg)' : 'none'; }
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            window.openBn = function(box, open){
                box.hidden = !open;
                var btn = document.querySelector('[data-target="' + box.id + '"]');
                if (btn) { setCaret(btn, open); }
            };
            document.querySelectorAll('.aun-cb-bntoggle').forEach(function(btn){
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
        delete_transient('aun_cb_any_sale');   // left over from before 1.8.1
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
            $html = str_replace('badge-inner', 'badge-inner aun-smart-sale-bubble', $html);
            return $html;
        }

        return '<div class="callout badge badge-circle"><div class="badge-inner secondary on-sale aun-smart-sale-bubble"><span class="onsale">' . $custom_text . '</span></div></div>';
    }

    public static function render_custom_css() {
        $opts = self::get_options();
        if ($opts['smart_sale_enabled'] !== '1') return;
        ?>
        <style data-no-optimize="1" data-no-minify="1">
            .badge-inner.aun-smart-sale-bubble {
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
            .badge-inner.aun-smart-sale-bubble .onsale {
                font-size: 11px !important;
                font-weight: 800 !important;
                letter-spacing: 0 !important;
                display: block;
                position: relative;
                z-index: 2;
            }
            .badge-inner.aun-smart-sale-bubble::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                border-radius: 50%;
                background: rgba(239, 68, 68, 0.5);
                z-index: 1;
                animation: aunSmoothRipple 2s cubic-bezier(0.25, 0.8, 0.25, 1) infinite;
                pointer-events: none;
            }
            @keyframes aunSmoothRipple {
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

        $id = 'aun-cb-' . (++self::$top_seq);

        // The dismissal is remembered against a signature of THIS campaign, so
        // editing the message or the dates makes it a new campaign and everyone
        // sees it again. Closing one offer must not silently mute the next.
        $sig = substr(md5($w['start'] . '|' . $w['end'] . '|' . $html), 0, 12);

        $close = '';
        if ($dismissible) {
            $close = '<button type="button" class="aun-cb-x" aria-label="'
                   . esc_attr(self::is_bn() ? 'বন্ধ করুন' : 'Dismiss this notice')
                   . '">&times;</button>';
        }

        $out .= sprintf(
            '<span id="%s" class="aun-cb-top" data-start="%d" data-end="%d" data-sig="%s" data-mute="%d"%s>'
            . '<span class="aun-cb-msg">%s</span>%s</span>',
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
            . '.aun-cb-top[hidden]{display:none !important;}'
            . '.aun-cb-bar-empty{display:none !important;}'
            . '.aun-cb-top{display:inline-flex;align-items:center;gap:10px;max-width:100%;}'
            . '.aun-cb-msg{min-width:0;}'
            /* Tabular figures stop the countdown jittering as the digits change. */
            . '.aun-cb-cd{font-variant-numeric:tabular-nums;font-feature-settings:"tnum";'
            . 'white-space:nowrap;font-weight:700;}'
            . '.aun-cb-x{all:unset;cursor:pointer;line-height:1;font-size:18px;opacity:.6;'
            . 'padding:0 2px;flex:0 0 auto;transition:opacity .15s ease;}'
            . '.aun-cb-x:hover,.aun-cb-x:focus-visible{opacity:1;}'
            . '.aun-cb-x:focus-visible{outline:2px solid currentColor;outline-offset:2px;border-radius:3px;}'
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
        $bar_sel  = (string) apply_filters('aun_cb_topbar_selector', '#top-bar');
        $bar_bp   = (int) apply_filters('aun_cb_topbar_breakpoint', 849);

        ob_start();
        ?>
<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
(function(){
  /* Looked up by id, never by walking back from this script tag: the widget's
     content passes through wpautop, which can slip a <p> or <br> in between and
     would silently break a sibling walk. */
  var el = document.getElementById(<?php echo wp_json_encode($id); ?>);
  /* The class test is also WP Rocket's marker: 'aun-cb-top' must appear INSIDE this script
     for rocket_delay_js_exclusions to match it. Without it the script was held back until the
     visitor's first tap or scroll, so the countdown stood still and a closed notice came back. */
  if(!el || (' ' + el.className + ' ').indexOf(' aun-cb-top ') < 0) return;

  var start = parseInt(el.getAttribute('data-start')||'0',10),
      end   = parseInt(el.getAttribute('data-end')||'0',10),
      sig   = el.getAttribute('data-sig')||'',
      mute  = parseInt(el.getAttribute('data-mute')||'0',10);

  /* ---- Dismissal ------------------------------------------------------
     Remembered against this campaign's signature, so closing one offer never
     mutes the next: change the message or the dates and it is a new key. */
  var MUTE_KEY = 'aunCbMute:' + sig;
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

  var clocks = el.querySelectorAll('.aun-cb-cd'), cdTimer;
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
    var st = document.getElementById('aun-cb-hidebar');
    if(st) return st;
    if(BAR_MODE === 'off') return null;
    st = document.createElement('style');
    st.id = 'aun-cb-hidebar';
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

  var x = el.querySelector('.aun-cb-x');
  if(x){
    /* isRocket: see the popup script. Without it WP Rocket parks this listener until
       its delayed scripts load, so the first tap on the x did nothing. */
    x.addEventListener('click', function(e){
      if(e && e.stopPropagation){ e.stopPropagation(); }
      remember();
      el.hidden = true;
      clearTimeout(cdTimer);
      syncBar();
    }, { isRocket: true });
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

        $el = '<span class="aun-cb-cd" data-until="' . (int) $end_ts . '" data-lang="' . ($used_bn ? 'bn' : 'en') . '">'
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

            } else {
                /*
                 * Neither is page-specific, so this is the real decision: a campaign
                 * you deliberately scheduled versus the automatic "some product is on
                 * sale" magnet.
                 *
                 * Default is campaign-first. Previously the magnet won, which meant a
                 * single discounted accessory silently replaced a site-wide campaign
                 * on every page — the deliberate message losing to the automatic one.
                 * The magnet is a fallback that fills the bar when you have nothing
                 * else to say, not an override.
                 *
                 * Either side is skipped when its message box is empty, so an unfilled
                 * field never blanks the bar while the other option had something.
                 */
                $campaign_ok = self::is_active()
                    && trim((string) self::pick($opts, 'message')) !== '';

                $magnet_ok = $opts['smart_sale_enabled'] === '1'
                    && self::any_scheduled_sale_active()
                    && trim((string) self::pick($opts, 'smart_sale_global_top_message')) !== '';

                if ($opts['notice_priority'] === 'sale') {
                    if ($magnet_ok)        { $show_sale_global = true; }
                    elseif ($campaign_ok)  { $show_global = true; }
                } else {
                    if ($campaign_ok)      { $show_global = true; }
                    elseif ($magnet_ok)    { $show_sale_global = true; }
                }
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
            $theme_color = '#16a34a'; // Green
            $bg_color = '#f0fdf4';
            $border_color = '#dcfce7';

        } elseif ($show_global) {
            $used_bn = false;
            $message = $force ? trim((string)$opts['product_message']) : self::pick($opts, 'product_message', $used_bn);
            if (empty($message)) return $force ? '<em>No global product message set.</em>' : '';

            // {countdown} works here too, counting to the campaign's own end date —
            // otherwise a template using the tag would print it raw on product pages.
            $w       = self::window_ts();
            $message = self::inject_countdown($message, $force ? time() + 2 * DAY_IN_SECONDS : $w['end'], $used_bn);

            $icon = 'fa-gift';
            $theme_color = '#0188fe'; // Blue
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

/* =====================================================================================
 * CAMPAIGN POPUP BANNER (1.10.0)
 *
 * A banner that opens over the page while the global campaign is live. It shares the
 * campaign's switch and its start/end time (window_ts()), so there is one schedule to
 * manage, not two.
 *
 * Every "should it show?" decision that depends on the VISITOR is made in the browser,
 * never on the server: WP Rocket serves one cached copy of each page to everybody, so a
 * server-side "this person already saw it" would be baked into the cache for the next
 * visitor. The server only decides things that are the same for everyone on a URL (is
 * this the checkout, is this the Bangla page, is the popup switched on).
 *
 * The browser then behaves like the popups on the big shops:
 *   - waits: N seconds, or X% scrolled, or the mouse heading for the tabs (computers)
 *   - never interrupts: not while typing, not over another open window (cart drawer,
 *     image zoom, language prompt, Google sign-in), not in a background tab, and not
 *     before the image has fully loaded, so it never appears half-drawn
 *   - never nags: once per visit; N days of quiet after a close; never again for this
 *     campaign once they tap it, copy the code or place an order
 *   - never on cart / checkout / account / order pages, nor on the page it links to
 * ===================================================================================== */
class AUN_CB_Popup {
    const STATS       = 'aun_cb_popup_stats';
    const PREVIEW_ARG = 'aun_cb_popup_preview';
    const BP          = 849;   // phones + tablets below this (Flatsome's "medium" breakpoint)

    private static $printed = false;

    public static function init() {
        add_action('wp_footer', [__CLASS__, 'render'], 50);
        add_action('wp_ajax_aun_cb_pop_stat', [__CLASS__, 'ajax_stat']);
        add_action('wp_ajax_nopriv_aun_cb_pop_stat', [__CLASS__, 'ajax_stat']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
    }

    public static function defaults() {
        return [
            'popup_enabled'    => '0',   // off until an image is chosen and it is switched on
            'popup_image'      => 0,
            'popup_image_m'    => 0,     // optional taller artwork for phones
            'popup_image_bn'   => 0,
            'popup_image_m_bn' => 0,
            'popup_link'       => '',
            'popup_code'       => '',
            'popup_button'     => 'Shop now',
            'popup_button_bn'  => 'এখনই কিনুন',
            'popup_countdown'  => '1',
            'popup_width'      => '560',
            'popup_delay'      => '8',
            'popup_scroll'     => '40',
            'popup_exit'       => '1',
            'popup_pageview'   => '1',
            'popup_snooze'     => '3',
            'popup_devices'    => 'all', // all | desktop | mobile
            'popup_exclude'    => '',
        ];
    }

    /* ------------------------------------------------------------------ settings */

    /** Called from AUN_Campaign_Notice_Bar::sanitize_options(). Absent = leave alone. */
    public static function sanitize($input, $out) {
        foreach (['popup_image', 'popup_image_m', 'popup_image_bn', 'popup_image_m_bn'] as $k) {
            if (isset($input[$k])) {
                $id = absint($input[$k]);
                $out[$k] = ($id && wp_attachment_is_image($id)) ? $id : 0;
            }
        }
        if (isset($input['popup_link'])) {
            $out['popup_link'] = self::clean_link($input['popup_link']);
        }
        if (isset($input['popup_code'])) {
            // Coupon codes have no spaces; a stray one would make the copied code fail.
            $out['popup_code'] = substr(preg_replace('/\s+/', '', sanitize_text_field($input['popup_code'])), 0, 40);
        }
        foreach (['popup_button', 'popup_button_bn'] as $k) {
            if (isset($input[$k])) {
                $out[$k] = self::cut(sanitize_text_field($input[$k]), 60);
            }
        }
        $ints = [
            'popup_width'    => [320, 900],
            'popup_delay'    => [0, 300],
            'popup_scroll'   => [0, 100],
            'popup_pageview' => [1, 5],
            'popup_snooze'   => [1, 90],
        ];
        foreach ($ints as $k => $range) {
            if (isset($input[$k])) {
                $out[$k] = (string) max($range[0], min($range[1], intval($input[$k])));
            }
        }
        if (isset($input['popup_devices'])) {
            $out['popup_devices'] = in_array($input['popup_devices'], ['all', 'desktop', 'mobile'], true) ? $input['popup_devices'] : 'all';
        }
        if (isset($input['popup_exclude'])) {
            $lines = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $input['popup_exclude']) as $line) {
                $line = trim(sanitize_text_field($line));
                if ($line === '') continue;
                // A pasted full address becomes its path, which is what gets matched.
                if (strpos($line, '://') !== false) {
                    $p = wp_parse_url($line, PHP_URL_PATH);
                    $line = is_string($p) && $p !== '' ? $p : '';
                }
                if ($line !== '') $lines[] = $line;
            }
            $out['popup_exclude'] = implode("\n", array_slice(array_unique($lines), 0, 30));
        }
        return $out;
    }

    private static function cut($s, $n) {
        return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
    }

    /** A path on this site ("/projector-price/") stays relative; anything else must be http(s). */
    private static function clean_link($v) {
        $v = trim((string) $v);
        if ($v === '') return '';
        if ($v[0] === '/' && (!isset($v[1]) || $v[1] !== '/')) {
            $clean = esc_url_raw(home_url($v));
            return $clean !== '' ? wp_make_link_relative($clean) : '';
        }
        return esc_url_raw($v, ['http', 'https']);
    }

    /**
     * Identity of THIS campaign's popup. Closing or using one campaign must never
     * silence the next, so any change to the dates, artwork, link or code is a new
     * signature — and everyone sees the new one.
     */
    public static function signature($opts) {
        $w = AUN_Campaign_Notice_Bar::window_ts();
        return substr(md5(implode('|', [
            $w['start'], $w['end'],
            (int) $opts['popup_image'], (int) $opts['popup_image_m'],
            (int) $opts['popup_image_bn'], (int) $opts['popup_image_m_bn'],
            (string) $opts['popup_link'], (string) $opts['popup_code'],
        ])), 0, 12);
    }

    /* ------------------------------------------------------------------ server-side gate */

    public static function is_preview() {
        return isset($_GET[self::PREVIEW_ARG]) && current_user_can('manage_options');
    }

    /** Pages where a popup would get in the way. Same answer for every visitor on the URL. */
    public static function excluded_here($opts) {
        if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) return true;
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) return true;
        if (is_feed() || is_embed() || is_customize_preview()) return true;
        // Page builders' editing canvases.
        if (isset($_GET['uxb_iframe']) || isset($_GET['elementor-preview'])) return true;

        $path = strtolower((string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
        foreach (preg_split('/\n/', (string) $opts['popup_exclude']) as $frag) {
            $frag = strtolower(trim($frag));
            if ($frag !== '' && strpos($path, $frag) !== false) return true;
        }
        return false;
    }

    /**
     * The artwork for this visitor's language and screen. Language beats device: on
     * the Bangla site a Bangla main image is a better phone fallback than an English
     * phone image.
     */
    private static function image_for($opts, $bn, $phone) {
        if ($phone) {
            $order = $bn ? ['popup_image_m_bn', 'popup_image_bn', 'popup_image_m', 'popup_image'] : ['popup_image_m', 'popup_image'];
        } else {
            $order = $bn ? ['popup_image_bn', 'popup_image'] : ['popup_image'];
        }
        foreach ($order as $k) {
            $id = (int) $opts[$k];
            if ($id && wp_attachment_is_image($id)) return $id;
        }
        return 0;
    }

    /* ------------------------------------------------------------------ front end */

    public static function render() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
        if (self::$printed) return;
        self::$printed = true;

        // An order just went through: never offer this campaign to them again.
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            self::mark_converted();
            return;
        }

        $preview = self::is_preview();
        $opts    = AUN_Campaign_Notice_Bar::get_options();
        $w       = AUN_Campaign_Notice_Bar::window_ts();

        if (!$preview) {
            if ($opts['popup_enabled'] !== '1' || $opts['enabled'] !== '1') return;
            // Ended: print nothing. Not yet started: print it, and the browser opens it
            // only once the start time has passed — the page may sit in WP Rocket's cache
            // across that moment.
            if ($w['end'] && time() >= $w['end']) return;
            if (self::excluded_here($opts)) return;
        }

        $bn  = AUN_Campaign_Notice_Bar::is_bn();
        $id  = self::image_for($opts, $bn, false);
        $idm = self::image_for($opts, $bn, true);
        if (!$id) return;

        echo self::style();
        echo self::markup($opts, $w, $bn, $id, $idm, $preview);
        echo self::script();
    }

    private static function mark_converted() {
        $opts = AUN_Campaign_Notice_Bar::get_options();
        if ($opts['popup_enabled'] !== '1' || $opts['enabled'] !== '1') return;
        echo '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">/* aun-cb-pop */'
           . 'try{localStorage.setItem(' . wp_json_encode('aunCbPopDone:' . self::signature($opts)) . ',"1");}catch(e){}</script>' . "\n";
    }

    private static function strings($bn) {
        return $bn ? [
            'label'    => 'বিশেষ অফার',
            'close'    => 'বন্ধ করুন',
            'ends'     => 'অফার শেষ হতে বাকি',
            'codeL'    => 'আপনার কোড',
            'copy'     => 'কপি',
            'copied'   => 'কপি হয়েছে',
            'hint'     => 'চেকআউটে এই কোডটি ব্যবহার করুন',
            'hintDone' => 'কপি হয়েছে — চেকআউটে পেস্ট করুন',
            'copyFail' => 'কোডটি কপি করতে চেপে ধরুন',
            'preview'  => 'প্রিভিউ — শুধু আপনি দেখছেন',
        ] : [
            'label'    => 'Special offer',
            'close'    => 'Close',
            'ends'     => 'Offer ends in',
            'codeL'    => 'Your code',
            'copy'     => 'Copy',
            'copied'   => 'Copied',
            'hint'     => 'Use this code at checkout',
            'hintDone' => 'Copied — paste it at checkout',
            'copyFail' => 'Press and hold the code to copy it',
            'preview'  => 'Preview — only you can see this',
        ];
    }

    /** width / height of an attachment, for fitting the card to short screens. */
    private static function ratio($id) {
        $src = wp_get_attachment_image_src($id, 'full');
        return ($src && $src[1] > 0 && $src[2] > 0) ? round($src[1] / $src[2], 4) : 0;
    }

    private static function markup($opts, $w, $bn, $id, $idm, $preview) {
        $t      = self::strings($bn);
        $sig    = self::signature($opts);
        $width  = (int) $opts['popup_width'];
        $link   = (string) $opts['popup_link'];
        $code   = (string) $opts['popup_code'];
        $button = trim((string) ($bn && trim((string) $opts['popup_button_bn']) !== '' ? $opts['popup_button_bn'] : $opts['popup_button']));
        $cd     = $opts['popup_countdown'] === '1' && $w['end'] && $w['end'] > time();

        $cfg = [
            'start'   => (int) $w['start'],
            'end'     => (int) $w['end'],
            'sig'     => $sig,
            'delay'   => (int) $opts['popup_delay'],
            'scroll'  => (int) $opts['popup_scroll'],
            'exit'    => $opts['popup_exit'] === '1' ? 1 : 0,
            'pv'      => (int) $opts['popup_pageview'],
            'snooze'  => (int) $opts['popup_snooze'],
            'dev'     => $opts['popup_devices'],
            'bp'      => self::BP,
            'w'       => $width,
            'ar'      => self::ratio($id),
            'arM'     => self::ratio($idm),
            'link'    => $link,
            'bn'      => $bn ? 1 : 0,
            'preview' => $preview ? 1 : 0,
            'ajax'    => admin_url('admin-ajax.php'),
            't'       => ['copy' => $t['copy'], 'copied' => $t['copied'], 'hint' => $t['hint'], 'hintDone' => $t['hintDone'], 'copyFail' => $t['copyFail']],
        ];

        // ---- the picture: real URLs held back in data-* until the browser decides to load
        $src    = wp_get_attachment_image_src($id, 'full');
        $srcset = (string) wp_get_attachment_image_srcset($id, 'full');
        $alt    = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
        if ($alt === '') { $alt = $t['label']; }

        $pic = '<picture>';
        if ($idm && $idm !== $id) {
            $m    = wp_get_attachment_image_src($idm, 'full');
            $mset = (string) wp_get_attachment_image_srcset($idm, 'full');
            $pic .= '<source media="(max-width: ' . self::BP . 'px)" data-srcset="' . esc_attr($mset !== '' ? $mset : $m[0]) . '" sizes="100vw"'
                  . ' width="' . (int) $m[1] . '" height="' . (int) $m[2] . '">';
        }
        $pic .= '<img class="aun-pop__img skip-lazy no-lazyload" data-no-lazy="1" decoding="async" alt="' . esc_attr($alt) . '"'
              . ' width="' . (int) $src[1] . '" height="' . (int) $src[2] . '"'
              . ' data-src="' . esc_url($src[0]) . '"'
              . ($srcset !== '' ? ' data-srcset="' . esc_attr($srcset) . '" data-sizes="(max-width: ' . self::BP . 'px) 100vw, ' . $width . 'px"' : '')
              . '></picture>';

        $media = $link !== ''
            ? '<a class="aun-pop__media" href="' . esc_url($link) . '" data-aun-pop-go>' . $pic . '</a>'
            : '<div class="aun-pop__media">' . $pic . '</div>';

        // ---- the strip under the banner
        $foot = '';
        if ($cd) {
            $foot .= '<div class="aun-pop__cd"><span class="aun-pop__dot" aria-hidden="true"></span>'
                   . esc_html($t['ends']) . ' <b>&nbsp;</b></div>';
        }
        if ($code !== '') {
            $foot .= '<div class="aun-pop__code">'
                   . '<span class="aun-pop__code-txt"><span class="aun-pop__code-l">' . esc_html($t['codeL']) . '</span>'
                   . '<span class="aun-pop__code-v">' . esc_html($code) . '</span></span>'
                   . '<button type="button" class="aun-pop__copy" data-code="' . esc_attr($code) . '">'
                   . '<svg class="aun-pop__ic-copy" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M5 15V6.5A2.5 2.5 0 0 1 7.5 4H15"/></svg>'
                   . '<svg class="aun-pop__ic-ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'
                   . '<span class="aun-pop__copy-t">' . esc_html($t['copy']) . '</span></button>'
                   . '</div>'
                   . '<p class="aun-pop__hint" aria-live="polite">' . esc_html($t['hint']) . '</p>';
        }
        if ($link !== '' && $button !== '') {
            $foot .= '<a class="aun-pop__cta" href="' . esc_url($link) . '" data-aun-pop-go>' . esc_html($button)
                   . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>';
        }

        $html  = '<div id="aun-cb-pop" class="aun-pop' . ($foot === '' ? ' aun-pop--bare' : '') . '" hidden'
               . ' data-cfg="' . esc_attr(wp_json_encode($cfg)) . '" style="--aun-pop-w:' . $width . 'px">';
        $html .= '<div class="aun-pop__bg" data-aun-pop-close></div>';
        $html .= '<div class="aun-pop__card" role="dialog" aria-modal="true" aria-label="' . esc_attr($t['label']) . '" tabindex="-1">';
        $html .= '<span class="aun-pop__grab" aria-hidden="true"></span>';
        if ($preview) {
            $html .= '<span class="aun-pop__tag">' . esc_html($t['preview']) . '</span>';
        }
        $html .= '<button type="button" class="aun-pop__x" data-aun-pop-close aria-label="' . esc_attr($t['close']) . '">'
               . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
        $html .= $media;
        if ($foot !== '') {
            $html .= '<div class="aun-pop__foot">' . $foot . '</div>';
        }
        $html .= '</div></div>' . "\n";
        return $html;
    }

    private static function style() {
        return <<<'CSS'
<style id="aun-cb-pop-css" data-no-optimize="1" data-no-minify="1">
.aun-pop[hidden]{display:none!important}
.aun-pop{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:24px;-webkit-tap-highlight-color:transparent;font-family:inherit;color:#0f172a}
.aun-pop *{box-sizing:border-box}
.aun-pop__bg{position:absolute;inset:0;background:rgba(9,15,28,.62);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);opacity:0;transition:opacity .3s ease}
.aun-pop__card{position:relative;width:100%;max-width:var(--aun-pop-w,560px);max-height:calc(100vh - 48px);max-height:calc(100dvh - 48px);overflow:auto;overscroll-behavior:contain;background:#fff;border-radius:20px;box-shadow:0 32px 80px -24px rgba(2,12,27,.6),0 0 0 1px rgba(15,23,42,.06);opacity:0;transform:translateY(18px) scale(.965);transition:transform .42s cubic-bezier(.2,.9,.3,1.1),opacity .25s ease;outline:none;scrollbar-width:none}
.aun-pop__card::-webkit-scrollbar{display:none}
.aun-pop.is-open .aun-pop__bg{opacity:1}
.aun-pop.is-open .aun-pop__card{opacity:1;transform:none}
.aun-pop__media{display:block;margin:0 auto;line-height:0;border-radius:20px 20px 0 0;overflow:hidden}
.aun-pop--bare .aun-pop__media{border-radius:20px}
.aun-pop__media img{display:block;width:100%;height:auto;max-width:100%;margin:0}
a.aun-pop__media img{transition:transform .5s cubic-bezier(.2,.8,.2,1)}
a.aun-pop__media:hover img{transform:scale(1.015)}
.aun-pop .aun-pop__x{position:absolute;top:12px;right:12px;z-index:3;width:38px;height:38px;min-height:0;margin:0;padding:0;border:0;border-radius:50%;background:rgba(255,255,255,.94);color:#0f172a;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 14px rgba(2,12,27,.28);transition:transform .15s ease,background .15s ease;line-height:1;text-transform:none;letter-spacing:0}
.aun-pop .aun-pop__x:hover{background:#fff;transform:rotate(90deg)}
.aun-pop__x svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round}
.aun-pop__foot{display:flex;flex-direction:column;gap:12px;padding:18px 20px 20px;line-height:1.35}
.aun-pop__cd{align-self:center;display:inline-flex;align-items:center;gap:6px;padding:6px 14px 6px 12px;border-radius:999px;background:#eef6ff;color:#3b5b7a;font-size:13px;font-weight:600}
.aun-pop__cd b{font-variant-numeric:tabular-nums;font-feature-settings:"tnum";color:#0188fe;font-weight:800;letter-spacing:.02em}
.aun-pop__dot{width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 0 rgba(239,68,68,.55);animation:aunPopPulse 1.8s ease-out infinite;margin-right:2px}
@keyframes aunPopPulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}100%{box-shadow:0 0 0 9px rgba(239,68,68,0)}}
.aun-pop__code{display:flex;align-items:center;gap:12px;padding:8px 8px 8px 16px;border:2px dashed #93c9ff;border-radius:14px;background:#f5faff}
.aun-pop__code-txt{display:flex;flex-direction:column;min-width:0;flex:1}
.aun-pop__code-l{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;line-height:1.3}
.aun-pop__code-v{font-size:21px;font-weight:800;letter-spacing:.08em;color:#0f172a;line-height:1.2;overflow-wrap:anywhere;-webkit-user-select:all;user-select:all}
.aun-pop .aun-pop__copy{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;min-height:44px;margin:0;padding:0 18px;border:0;border-radius:11px;background:#0188fe;color:#fff;font-family:inherit;font-size:14px;font-weight:700;line-height:1;letter-spacing:0;text-transform:none;cursor:pointer;box-shadow:0 6px 16px -8px rgba(1,136,254,.8);transition:background .15s ease,transform .1s ease}
.aun-pop .aun-pop__copy:hover{background:#0074dc}
.aun-pop .aun-pop__copy:active{transform:scale(.96)}
.aun-pop__copy svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.aun-pop.is-copied .aun-pop__copy{background:#16a34a;box-shadow:0 6px 16px -8px rgba(22,163,74,.8)}
.aun-pop__ic-ok,.aun-pop.is-copied .aun-pop__ic-copy{display:none}
.aun-pop.is-copied .aun-pop__ic-ok{display:block;stroke-width:2.6}
.aun-pop.is-copied .aun-pop__code{border-color:#86efac;background:#f0fdf4}
.aun-pop__hint{margin:-4px 0 0;padding:0;text-align:center;font-size:12.5px;color:#64748b}
.aun-pop.is-copied .aun-pop__hint{color:#15803d;font-weight:600}
.aun-pop .aun-pop__cta{display:flex;align-items:center;justify-content:center;gap:8px;min-height:52px;margin:0;padding:0 20px;border-radius:13px;background:linear-gradient(135deg,#0188fe,#00c6ff);color:#fff;font-size:16px;font-weight:800;letter-spacing:.01em;text-decoration:none;box-shadow:0 10px 24px -10px rgba(1,136,254,.75);transition:transform .15s ease,box-shadow .15s ease}
.aun-pop .aun-pop__cta:hover{color:#fff;transform:translateY(-1px);box-shadow:0 14px 28px -10px rgba(1,136,254,.85)}
.aun-pop__cta svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;transition:transform .15s ease}
.aun-pop__cta:hover svg{transform:translateX(3px)}
.aun-pop a:focus-visible,.aun-pop button:focus-visible{outline:3px solid #0188fe;outline-offset:3px}
.aun-pop__tag{position:absolute;top:14px;left:14px;z-index:3;padding:5px 10px;border-radius:999px;background:#0f172a;color:#fff;font-size:11.5px;font-weight:700;letter-spacing:.02em;pointer-events:none}
.aun-pop__grab{display:none}
html.aun-pop-lock{overflow:hidden!important;scrollbar-gutter:stable;overscroll-behavior:none}
@media (max-width:849px){
.aun-pop{align-items:flex-end;padding:0}
.aun-pop__card{max-width:100%!important;border-radius:22px 22px 0 0;max-height:calc(100vh - 12px);max-height:calc(100dvh - 12px);opacity:1;transform:translateY(105%);transition:transform .45s cubic-bezier(.2,.9,.25,1);padding-bottom:env(safe-area-inset-bottom)}
.aun-pop__media{border-radius:22px 22px 0 0}
.aun-pop--bare .aun-pop__media{border-radius:22px 22px 0 0}
.aun-pop__grab{display:block;position:absolute;top:8px;left:50%;z-index:3;width:42px;height:5px;margin-left:-21px;border-radius:3px;background:rgba(255,255,255,.9);box-shadow:0 1px 5px rgba(0,0,0,.3)}
.aun-pop .aun-pop__x{top:14px;right:12px}
.aun-pop__foot{padding:16px 16px 18px}
}
@media (prefers-reduced-motion:reduce){.aun-pop__bg,.aun-pop__card,.aun-pop__media img,.aun-pop__x{transition:none!important}.aun-pop__dot{animation:none}}
</style>
CSS;
    }

    private static function script() {
        return <<<'JS'
<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
(function(){
  /* aun-cb-pop: everything here decides for THIS visitor, in their browser. The page
     itself is the same cached copy for everyone. */
  var root = document.getElementById('aun-cb-pop');
  if(!root || root.getAttribute('data-ready')) return;
  root.setAttribute('data-ready', '1');
  var C;
  try{ C = JSON.parse(root.getAttribute('data-cfg') || '{}'); }catch(e){ return; }
  var T = C.t || {};
  /* fixed positioning must not be trapped inside a transformed theme wrapper */
  if(root.parentNode !== document.body && document.body){ document.body.appendChild(root); }

  var card  = root.querySelector('.aun-pop__card'),
      media = root.querySelector('.aun-pop__media'),
      foot  = root.querySelector('.aun-pop__foot'),
      img   = root.querySelector('.aun-pop__img');
  function NOW(){ return Date.now(); }
  var t0 = NOW();

  /* WP Rocket's "Delay JavaScript execution" REPLACES addEventListener: until its
     delayed scripts have loaded (which waits for the visitor's first tap or key), any
     listener for a click, key, touch or mouse event is parked instead of attached.
     The popup's close button, Esc, Copy, swipe and exit intent then did nothing until
     a second later — longer on a slow phone. Rocket attaches a listener at once when
     its options carry isRocket:true; browsers ignore the unknown key, so this is
     harmless anywhere else. */
  function on(el, type, fn, opts){ opts = opts || {}; opts.isRocket = true; el.addEventListener(type, fn, opts); }
  function off(el, type, fn, opts){ opts = opts || {}; opts.isRocket = true; el.removeEventListener(type, fn, opts); }
  /* Rocket also holds back the page's first click and replays it later. Our own
     controls keep their clicks to themselves, so a link opens at once and nothing
     runs twice. */
  function own(e){ if(e && e.stopPropagation){ e.stopPropagation(); } }

  function get(k){ try{ return window.localStorage.getItem(k); }catch(e){ return null; } }
  function put(k, v){ try{ window.localStorage.setItem(k, v); }catch(e){} }
  var K_DONE = 'aunCbPopDone:' + C.sig, K_SNOOZE = 'aunCbPopSnooze:' + C.sig, K_SESS = 'aunCbPopSess';

  function mm(q){ return !!(window.matchMedia && window.matchMedia(q).matches); }
  function isPhone(){ return mm('(max-width: ' + C.bp + 'px)'); }
  function reduced(){ return mm('(prefers-reduced-motion: reduce)'); }

  /* A visit = activity with no gap over 30 minutes, shared by all of this visitor's tabs. */
  function session(){
    var s = null;
    try{ s = JSON.parse(get(K_SESS) || 'null'); }catch(e){}
    if(!s || typeof s !== 'object' || !s.last || NOW() - s.last > 1800000){ s = { pv: 0, seen: {} }; }
    if(!s.seen || typeof s.seen !== 'object'){ s.seen = {}; }
    return s;
  }
  function saveSession(s){ s.last = NOW(); put(K_SESS, JSON.stringify(s)); }

  var sent = {};
  function stat(ev){
    if(C.preview || sent[ev]) return;
    sent[ev] = 1;
    try{
      if(C.ajax && navigator.sendBeacon){
        var fd = new FormData();
        fd.append('action', 'aun_cb_pop_stat'); fd.append('ev', ev); fd.append('sig', C.sig);
        navigator.sendBeacon(C.ajax, fd);
      }
    }catch(e){}
    try{ if(window.dataLayer && window.dataLayer.push){ window.dataLayer.push({ event: 'aun_campaign_popup', popup_action: ev, popup_campaign: C.sig }); } }catch(e){}
  }

  function inWindow(){ var s = NOW() / 1000; return (!C.start || s >= C.start) && (!C.end || s < C.end); }
  function norm(p){ p = (p || '/').replace(/^\/bn(?=\/|$)/i, '').replace(/\/+$/, '').toLowerCase(); return p || '/'; }
  /* Advertising a page to someone who is already on it is noise. */
  function onLandingPage(){
    if(!C.link) return false;
    var a = document.createElement('a'); a.href = C.link;
    if(a.hostname && a.hostname !== location.hostname) return false;
    var p = a.pathname.charAt(0) === '/' ? a.pathname : '/' + a.pathname;
    return norm(p) === norm(location.pathname);
  }

  function eligible(){
    if(C.preview) return true;
    if(!inWindow()) return false;
    if(C.dev === 'desktop' && isPhone()) return false;
    if(C.dev === 'mobile' && !isPhone()) return false;
    if(get(K_DONE)) return false;
    var until = parseInt(get(K_SNOOZE) || '0', 10);
    if(until && NOW() < until) return false;
    if(session().seen[C.sig]) return false;
    if(onLandingPage()) return false;
    return true;
  }

  /* ---- the image: fetched quietly after the page has loaded; the popup only ever
     opens on a fully loaded banner, never a half-drawn one ---- */
  var imgState = 0, imgWait = [];          /* 0 idle, 1 loading, 2 ready, 3 failed */
  function settle(ok){
    if(imgState > 1) return;
    imgState = ok ? 2 : 3;
    var q = imgWait; imgWait = [];
    for(var i = 0; i < q.length; i++){ q[i](ok); }
  }
  function loadImage(){
    if(imgState) return;
    if(!img){ imgState = 2; return; }
    imgState = 1;
    var pic = img.parentNode;
    if(pic && pic.tagName === 'PICTURE'){
      var ss = pic.getElementsByTagName('source');
      for(var i = 0; i < ss.length; i++){ var v = ss[i].getAttribute('data-srcset'); if(v){ ss[i].setAttribute('srcset', v); } }
    }
    var sz = img.getAttribute('data-sizes'), set = img.getAttribute('data-srcset');
    if(sz){ img.setAttribute('sizes', sz); }
    if(set){ img.setAttribute('srcset', set); }
    img.onload  = function(){ settle(true); };
    img.onerror = function(){ settle(false); };
    img.setAttribute('src', img.getAttribute('data-src'));
    if(img.complete && img.naturalWidth){ settle(true); }
    setTimeout(function(){ settle(!!(img.complete && img.naturalWidth)); }, 20000);
  }
  function whenImage(cb){
    if(imgState > 1){ cb(imgState === 2); return; }
    imgWait.push(cb);
    loadImage();
  }

  /* ---- waiting for the right moment ---- */
  var fired = false, retries = 0, delayT = 0, opened = false;

  function typing(){
    var a = document.activeElement;
    if(!a || a === document.body || root.contains(a)) return false;
    if(a.isContentEditable) return true;
    if(a.tagName === 'TEXTAREA' || a.tagName === 'SELECT') return true;
    if(a.tagName === 'INPUT'){
      return ['button','submit','reset','checkbox','radio','image','range','color','file','hidden'].indexOf((a.type || 'text').toLowerCase()) < 0;
    }
    return false;
  }
  /* Cart drawer / menu (Flatsome), image zoom, TranslatePress language prompt,
     Google sign-in, or any other open dialog. */
  function otherWindowOpen(){
    var list = document.querySelectorAll('.mfp-wrap, .pswp--open, #trp_ald_modal_container, #credential_picker_container, #credential_picker_iframe, [aria-modal="true"]');
    for(var i = 0; i < list.length; i++){
      var el = list[i];
      if(el === root || root.contains(el)) continue;
      if(el.getClientRects().length && window.getComputedStyle(el).visibility !== 'hidden'){ return true; }
    }
    return false;
  }
  function busy(){ return !C.preview && (typing() || otherWindowOpen()); }

  function trigger(){
    if(fired) return;
    fired = true;
    clearTimeout(delayT);
    off(window, 'scroll', onScroll, { passive: true });
    off(document, 'mouseout', onLeave);
    attempt();
  }
  function attempt(){
    if(opened) return;
    if(document.visibilityState === 'hidden'){
      var again = function(){
        if(document.visibilityState !== 'visible') return;
        off(document, 'visibilitychange', again);
        setTimeout(attempt, 1200);   /* let them settle back in first */
      };
      on(document, 'visibilitychange', again);
      return;
    }
    if(busy()){
      if(++retries <= 40){ setTimeout(attempt, 3000); }   /* keep waiting, up to 2 minutes */
      return;
    }
    whenImage(function(ok){
      if(!ok || !eligible()) return;
      if(busy()){ if(++retries <= 40){ setTimeout(attempt, 3000); } return; }
      open();
    });
  }
  function onScroll(){
    var de = document.documentElement,
        max = Math.max(de.scrollHeight, document.body ? document.body.scrollHeight : 0) - window.innerHeight;
    if(max < 200) return;
    if((window.pageYOffset || de.scrollTop || 0) / max * 100 >= C.scroll && NOW() - t0 >= 2500){ trigger(); }
  }
  /* Computers: the pointer leaving through the top of the window, towards the tabs,
     the address bar or the close button. */
  function onLeave(e){
    if(e.relatedTarget || e.toElement) return;
    if(e.clientY > 10 || NOW() - t0 < 3000) return;
    trigger();
  }

  /* ---- open / close ---- */
  var lastFocus = null, cdT = 0;

  function fit(){
    var phone = isPhone(), ar = phone ? (C.arM || C.ar) : C.ar;
    card.style.maxWidth = '';
    if(media){ media.style.maxWidth = ''; }
    if(!ar || !media) return;
    var footH = foot ? foot.offsetHeight : 0,
        room  = window.innerHeight - (phone ? 16 : 48) - footH,
        w     = Math.floor(room * ar);
    /* On a short screen the banner shrinks to fit rather than being cut off. */
    if(phone){
      if(w < window.innerWidth){ media.style.maxWidth = Math.max(220, w) + 'px'; }
    } else {
      card.style.maxWidth = Math.max(340, Math.min(C.w, window.innerWidth - 48, w)) + 'px';
    }
  }
  function lock(on){
    var de = document.documentElement;
    if(on){ de.classList.add('aun-pop-lock'); } else { de.classList.remove('aun-pop-lock'); }
  }
  function focusables(){
    var list = card.querySelectorAll('a[href], button:not([disabled])'), out = [];
    for(var i = 0; i < list.length; i++){ if(list[i].getClientRects().length){ out.push(list[i]); } }
    return out;
  }
  function onKey(e){
    if(e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27){ e.preventDefault(); own(e); close('closed'); return; }
    if(e.key !== 'Tab' && e.keyCode !== 9) return;
    own(e);
    var f = focusables();
    if(!f.length){ e.preventDefault(); card.focus(); return; }
    var first = f[0], last = f[f.length - 1], a = document.activeElement;
    if(e.shiftKey && (a === first || a === card || !card.contains(a))){ e.preventDefault(); last.focus(); }
    else if(!e.shiftKey && (a === last || !card.contains(a))){ e.preventDefault(); first.focus(); }
  }
  function open(){
    if(opened) return;
    opened = true;
    if(!C.preview){ var s = session(); s.seen[C.sig] = 1; saveSession(s); }
    lastFocus = document.activeElement;
    lock(true);
    root.hidden = false;
    fit();
    root.getBoundingClientRect();          /* commit the closed state, so the entrance animates */
    root.classList.add('is-open');
    setTimeout(function(){ try{ card.focus({ preventScroll: true }); }catch(e){ card.focus(); } }, 60);
    on(document, 'keydown', onKey, { capture: true });
    on(window, 'resize', fit);
    tick();
    stat('view');
  }
  function close(why){
    if(!opened) return;
    opened = false;
    if(!C.preview && why === 'closed'){
      put(K_SNOOZE, String(NOW() + C.snooze * 86400000));
      stat('close');
    }
    root.classList.remove('is-open');
    off(document, 'keydown', onKey, { capture: true });
    off(window, 'resize', fit);
    clearTimeout(cdT);
    setTimeout(function(){
      root.hidden = true;
      lock(false);
      card.style.transform = '';
      if(lastFocus && lastFocus.focus && document.documentElement.contains(lastFocus)){
        try{ lastFocus.focus({ preventScroll: true }); }catch(e){}
      }
    }, reduced() ? 0 : 340);
  }

  /* ---- countdown: from an absolute deadline, so a cached page is still right ---- */
  var BN = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], cdEl = root.querySelector('.aun-pop__cd b');
  function pad(n){ return n < 10 ? '0' + n : '' + n; }
  function clock(left){
    var d = Math.floor(left / 86400),
        txt = pad(Math.floor(left % 86400 / 3600)) + ':' + pad(Math.floor(left % 3600 / 60)) + ':' + pad(left % 60);
    if(d > 0){ txt = d + (C.bn ? ' দিন ' : 'd ') + txt; }
    return C.bn ? txt.replace(/[0-9]/g, function(x){ return BN[+x]; }) : txt;
  }
  function tick(){
    clearTimeout(cdT);
    if(!cdEl || !C.end) return;
    var left = Math.max(0, C.end - Math.floor(NOW() / 1000));
    cdEl.textContent = clock(left);
    if(left <= 0 && !C.preview){ close('expired'); return; }
    if(opened && left > 0){ cdT = setTimeout(tick, 1000); }
  }

  /* ---- copy the code ---- */
  var copyBtn = root.querySelector('.aun-pop__copy'),
      copyTxt = root.querySelector('.aun-pop__copy-t'),
      hint    = root.querySelector('.aun-pop__hint'), copyT = 0;
  function legacyCopy(text){
    var ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
    card.appendChild(ta); ta.select();
    var ok = false;
    try{ ok = document.execCommand('copy'); }catch(e){}
    card.removeChild(ta);
    try{ copyBtn.focus({ preventScroll: true }); }catch(e){}
    return ok;
  }
  function copied(ok){
    clearTimeout(copyT);
    if(ok){
      root.classList.add('is-copied');
      if(copyTxt){ copyTxt.textContent = T.copied; }
      if(hint){ hint.textContent = T.hintDone; }
      if(!C.preview){ put(K_DONE, '1'); }   /* they have what they came for */
      stat('copy');
      copyT = setTimeout(function(){
        root.classList.remove('is-copied');
        if(copyTxt){ copyTxt.textContent = T.copy; }
      }, 2600);
    } else {
      if(hint){ hint.textContent = T.copyFail; }
      var v = root.querySelector('.aun-pop__code-v');
      if(v && window.getSelection && document.createRange){
        var r = document.createRange(); r.selectNodeContents(v);
        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
      }
    }
  }
  if(copyBtn){
    var copying = false;
    on(copyBtn, 'click', function(e){
      own(e);
      if(copying) return;                  /* one tap, one copy */
      copying = true;
      setTimeout(function(){ copying = false; }, 1000);
      var code = copyBtn.getAttribute('data-code') || '', done = false;
      function finish(ok){ if(done) return; done = true; copied(ok); }
      if(navigator.clipboard && window.isSecureContext){
        /* The clipboard API can sit unanswered (an unfocused document, a permission
           prompt that never shows). A tap must always visibly do something, so fall
           back to the old copy route if it has not answered in time. */
        var guard = setTimeout(function(){ finish(legacyCopy(code)); }, 800);
        navigator.clipboard.writeText(code).then(
          function(){ clearTimeout(guard); finish(true); },
          function(){ clearTimeout(guard); finish(legacyCopy(code)); }
        );
      } else {
        finish(legacyCopy(code));
      }
    });
  }

  /* ---- tapping through counts as "used": never offer this campaign again ---- */
  var go = root.querySelectorAll('[data-aun-pop-go]');
  for(var g = 0; g < go.length; g++){
    on(go[g], 'click', function(e){ own(e); if(!C.preview){ put(K_DONE, '1'); } stat('click'); });
  }
  var closers = root.querySelectorAll('[data-aun-pop-close]');
  for(var c = 0; c < closers.length; c++){
    on(closers[c], 'click', function(e){ e.preventDefault(); own(e); close('closed'); });
  }

  /* ---- phones: swipe the sheet down to dismiss ---- */
  var sy = null, dy = 0;
  on(card, 'touchstart', function(e){
    if(!isPhone() || card.scrollTop > 0 || e.touches.length !== 1){ sy = null; return; }
    sy = e.touches[0].clientY; dy = 0;
  }, { passive: true });
  on(card, 'touchmove', function(e){
    if(sy === null) return;
    dy = e.touches[0].clientY - sy;
    if(dy > 0){ card.style.transition = 'none'; card.style.transform = 'translateY(' + dy + 'px)'; }
  }, { passive: true });
  on(card, 'touchend', function(){
    if(sy === null) return;
    sy = null;
    card.style.transition = '';
    card.style.transform = '';
    if(dy > 90){ close('closed'); }
    dy = 0;
  }, { passive: true });

  /* ---- go ---- */
  if(!C.preview){
    var s0 = session();
    s0.pv = (s0.pv || 0) + 1;
    saveSession(s0);
    if(s0.pv < C.pv) return;               /* "start from their 2nd page" */
  }
  if(!eligible()) return;

  function warm(){
    if(imgState) return;
    if(window.requestIdleCallback){ window.requestIdleCallback(loadImage, { timeout: 4000 }); }
    else { setTimeout(loadImage, 1500); }
  }
  /* Rocket also reports document.readyState as "loading" until its own scripts are
     in, so a fallback timer makes sure the banner is fetched either way. */
  if(document.readyState === 'complete'){ warm(); } else { on(window, 'load', warm, { once: true }); }
  setTimeout(warm, 4000);

  if(C.preview){ setTimeout(trigger, 500); return; }
  delayT = setTimeout(trigger, Math.max(0, C.delay) * 1000);
  if(C.scroll > 0){ on(window, 'scroll', onScroll, { passive: true }); }
  if(C.exit && mm('(hover: hover) and (pointer: fine)')){ on(document, 'mouseout', onLeave); }
})();
</script>
JS;
    }

    /* ------------------------------------------------------------------ stats */

    /** One beacon per event per page view. Counts are approximate by nature. */
    public static function ajax_stat() {
        $ev  = isset($_POST['ev']) ? sanitize_key(wp_unslash($_POST['ev'])) : '';
        $sig = isset($_POST['sig']) ? sanitize_key(wp_unslash($_POST['sig'])) : '';
        if (!in_array($ev, ['view', 'click', 'copy', 'close'], true) || $sig === '') {
            wp_send_json_error(null, 400);
        }
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|headless|lighthouse/i', $ua)) {
            wp_send_json_success();
        }
        $opts = AUN_Campaign_Notice_Bar::get_options();
        // Only the campaign that is on screen now: a stale tab or a made-up signature
        // cannot grow the option.
        if ($opts['popup_enabled'] !== '1' || !hash_equals(self::signature($opts), $sig)) {
            wp_send_json_success();
        }
        $all = get_option(self::STATS, []);
        if (!is_array($all)) { $all = []; }
        if (!isset($all[$sig]) || !is_array($all[$sig])) {
            $all[$sig] = ['view' => 0, 'click' => 0, 'copy' => 0, 'close' => 0, 'since' => time()];
        }
        $all[$sig][$ev] = (int) ($all[$sig][$ev] ?? 0) + 1;
        if (count($all) > 6) {
            uasort($all, function ($a, $b) { return (int) ($b['since'] ?? 0) - (int) ($a['since'] ?? 0); });
            $all = array_slice($all, 0, 6, true);
        }
        update_option(self::STATS, $all, false);
        wp_send_json_success();
    }

    public static function stats_for($sig) {
        $all = get_option(self::STATS, []);
        return (is_array($all) && isset($all[$sig]) && is_array($all[$sig])) ? $all[$sig] : null;
    }

    /* ------------------------------------------------------------------ admin */

    public static function admin_assets($hook) {
        if ($hook === 'settings_page_aun-campaign-notice-bar') {
            wp_enqueue_media();
        }
    }

    /** One line for the status card at the top of the settings page. */
    public static function status_line($opts) {
        if ($opts['popup_enabled'] !== '1') {
            return 'Popup banner: off.';
        }
        if (!(int) $opts['popup_image']) {
            return 'Popup banner: switched on, but no banner image is chosen yet, so nothing shows.';
        }
        $when = ['after ' . (int) $opts['popup_delay'] . ' seconds'];
        if ((int) $opts['popup_scroll'] > 0) { $when[] = 'at ' . (int) $opts['popup_scroll'] . '% scrolled'; }
        if ($opts['popup_exit'] === '1')     { $when[] = 'when leaving (computers)'; }
        return 'Popup banner: on. Opens ' . implode(', or ', $when) . ', whichever comes first.';
    }

    /**
     * The old way of doing this was a Flatsome [lightbox auto_open] in the theme's
     * footer scripts. Left in place, visitors would get two popups.
     */
    private static function lightbox_warning() {
        $mods = get_theme_mods();
        if (!is_array($mods)) return;
        foreach ($mods as $val) {
            if (is_string($val) && stripos($val, '[lightbox') !== false && stripos($val, 'auto_open') !== false) {
                echo '<div style="margin:12px 0 0;padding:10px 14px;border:1px solid #fde68a;border-left:4px solid #d97706;border-radius:6px;background:#fffbeb;color:#78350f;font-weight:normal;">'
                   . '⚠️ <strong>The old Flatsome popup is still in place.</strong> Flatsome &rarr; Advanced &rarr; Global Settings &rarr; '
                   . '<em>Footer Scripts</em> contains a <code>[lightbox auto_open=&hellip;]</code> code. Delete it when you switch this '
                   . 'popup on, or visitors will get two popups.</div>';
                return;
            }
        }
    }

    private static function media_field($opts, $key, $label, $desc) {
        $name = AUN_Campaign_Notice_Bar::OPTION_KEY . '[' . $key . ']';
        $id   = (int) $opts[$key];
        $src  = $id ? wp_get_attachment_image_src($id, 'medium') : false;
        $full = $id ? wp_get_attachment_image_src($id, 'full') : false;
        echo '<div class="aun-pop-media" style="display:flex;gap:14px;align-items:flex-start;margin:0 0 14px;">';
        echo '<div style="width:96px;height:96px;flex:0 0 96px;border:1px dashed #c3c4c7;border-radius:8px;background:#f6f7f7 center/contain no-repeat;overflow:hidden;display:flex;align-items:center;justify-content:center;">'
           . '<img alt="" style="max-width:100%;max-height:100%;' . ($src ? '' : 'display:none;') . '" ' . ($src ? 'src="' . esc_url($src[0]) . '"' : '') . '>'
           . '<span class="aun-pop-none" style="color:#a7aaad;font-size:12px;' . ($src ? 'display:none;' : '') . '">none</span></div>';
        echo '<div><strong>' . $label . '</strong>'
           . ' <span class="aun-pop-dims" style="color:#646970;">' . ($full ? esc_html((int) $full[1] . ' × ' . (int) $full[2] . ' px') : '') . '</span><br>'
           . '<input type="hidden" name="' . esc_attr($name) . '" value="' . $id . '">'
           . '<span style="display:flex;align-items:center;gap:12px;margin-top:6px;">'
           . '<button type="button" class="button aun-pop-choose">Choose image</button>'
           . '<button type="button" class="button-link aun-pop-remove" style="color:#b32d2e;' . ($id ? '' : 'display:none;') . '">Remove</button>'
           . '</span>'
           . '<p class="description" style="margin-top:6px;">' . $desc . '</p></div>';
        echo '</div>';
    }

    /** The popup's rows inside the settings form table. */
    public static function settings_rows($opts) {
        $n   = function ($k) { return esc_attr(AUN_Campaign_Notice_Bar::OPTION_KEY . '[' . $k . ']'); };
        $sig = self::signature($opts);
        $st  = self::stats_for($sig);
        $has_bn = (int) $opts['popup_image_bn'] || (int) $opts['popup_image_m_bn'];
        ?>
        <tr><th colspan="2" style="padding-top:40px;">
            <h2 style="margin:0;color:#0188fe;">🪧 Campaign Popup Banner</h2>
            <p style="font-weight:normal;margin-top:5px;color:#555;max-width:820px;">A banner that opens over the page while the
               <strong>global campaign above is live</strong> &mdash; it uses the same switch and the same start and end time, so
               there is nothing extra to schedule. It waits for the right moment, appears at most once per visit, and leaves
               people alone once they have closed it or used it.</p>
            <?php self::lightbox_warning(); ?>
        </th></tr>

        <tr>
            <th scope="row">Popup banner</th>
            <td>
                <label><input type="checkbox" name="<?php echo $n('popup_enabled'); ?>" value="1" <?php checked($opts['popup_enabled'], '1'); ?> />
                    Show the popup while the campaign is live</label>
                <?php if ($st): ?>
                    <?php $v = max(1, (int) $st['view']); ?>
                    <p style="margin:8px 0 0;padding:8px 12px;background:#f0f6fc;border-radius:6px;display:inline-block;">
                        📊 <strong>This campaign so far:</strong>
                        <?php echo esc_html(number_format_i18n((int) $st['view'])); ?> shown &middot;
                        <?php echo esc_html(number_format_i18n((int) $st['click'])); ?> tapped through
                        (<?php echo esc_html(number_format_i18n(100 * (int) $st['click'] / $v, 1)); ?>%) &middot;
                        <?php echo esc_html(number_format_i18n((int) $st['copy'])); ?> copied the code &middot;
                        <?php echo esc_html(number_format_i18n((int) $st['close'])); ?> closed it
                    </p>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th scope="row">Banner image</th>
            <td>
                <?php self::media_field($opts, 'popup_image', 'Main image', 'Square works best, e.g. 1080 &times; 1080. Used on computers, and on phones too unless you add a phone image.'); ?>
                <?php self::media_field($opts, 'popup_image_m', 'Phone image <span style="font-weight:normal;color:#646970;">(optional)</span>', 'A taller version for phones, e.g. 1080 &times; 1350 (4:5). Leave empty to reuse the main image.'); ?>
                <details <?php echo $has_bn ? 'open' : ''; ?> style="margin-top:4px;">
                    <summary style="cursor:pointer;font-weight:600;color:#0f6c2f;">বাংলা images
                        <span style="font-weight:normal;color:<?php echo $has_bn ? '#0f6c2f' : '#646970'; ?>;"><?php echo $has_bn ? '&#10003; set' : '&mdash; not set, the images above will be reused'; ?></span></summary>
                    <div style="margin-top:12px;">
                        <?php self::media_field($opts, 'popup_image_bn', 'Main image (বাংলা)', 'Shown when the visitor reads the site in বাংলা.'); ?>
                        <?php self::media_field($opts, 'popup_image_m_bn', 'Phone image (বাংলা)', 'Optional.'); ?>
                    </div>
                </details>
            </td>
        </tr>

        <tr>
            <th scope="row">Tapping it opens</th>
            <td>
                <input type="text" class="regular-text" name="<?php echo $n('popup_link'); ?>" value="<?php echo esc_attr($opts['popup_link']); ?>" placeholder="/projector-price/" />
                <p class="description">A page on your site, e.g. <code>/projector-price/</code>, or a full address. Leave empty and the banner is a picture only.
                   (The popup never shows on the page it links to.)</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Coupon code</th>
            <td>
                <input type="text" class="regular-text" name="<?php echo $n('popup_code'); ?>" value="<?php echo esc_attr($opts['popup_code']); ?>" placeholder="WELCOME5" style="font-weight:700;letter-spacing:.05em;" />
                <p class="description">Shown under the banner with a one-tap <strong>Copy</strong> button. Leave empty to hide it.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Button</th>
            <td>
                <input type="text" name="<?php echo $n('popup_button'); ?>" value="<?php echo esc_attr($opts['popup_button']); ?>" placeholder="Shop now" style="width:200px;" />
                <span style="margin:0 6px 0 12px;color:#0f6c2f;font-weight:600;">বাংলা</span>
                <input type="text" name="<?php echo $n('popup_button_bn'); ?>" value="<?php echo esc_attr($opts['popup_button_bn']); ?>" placeholder="এখনই কিনুন" style="width:200px;" />
                <p class="description">Goes to the link above. Leave the text empty for no button; the banner itself stays tappable.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Countdown</th>
            <td>
                <label><input type="checkbox" name="<?php echo $n('popup_countdown'); ?>" value="1" <?php checked($opts['popup_countdown'], '1'); ?> />
                    Show &ldquo;Offer ends in 2d 04:11:09&rdquo;, counting to the campaign&rsquo;s <strong>End date</strong></label>
                <p class="description">Hidden automatically when the campaign has no end date. Bangla numerals on the বাংলা site.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">When it opens</th>
            <td style="line-height:2.3;">
                After <input type="number" min="0" max="300" class="small-text" name="<?php echo $n('popup_delay'); ?>" value="<?php echo esc_attr((int) $opts['popup_delay']); ?>" /> seconds on the page,<br>
                or sooner once they scroll <input type="number" min="0" max="100" class="small-text" name="<?php echo $n('popup_scroll'); ?>" value="<?php echo esc_attr((int) $opts['popup_scroll']); ?>" />% of the way down <span style="color:#646970;">(0 = off)</span>,<br>
                <label><input type="checkbox" name="<?php echo $n('popup_exit'); ?>" value="1" <?php checked($opts['popup_exit'], '1'); ?> />
                    or when the mouse heads for the tabs to leave <span style="color:#646970;">(computers only)</span></label><br>
                Starting from their
                <select name="<?php echo $n('popup_pageview'); ?>">
                    <?php foreach ([1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth'] as $pv => $word): ?>
                        <option value="<?php echo (int) $pv; ?>" <?php selected((int) $opts['popup_pageview'], $pv); ?>><?php echo esc_html($word); ?></option>
                    <?php endforeach; ?>
                </select> page of a visit.
                <p class="description" style="line-height:1.5;">Whichever comes first. It also waits politely: never while someone is typing in a form, never on top
                   of another open window (cart drawer, image zoom, the language prompt, Google sign-in), never in a background tab, and
                   only once the image has fully loaded &mdash; so it never appears half-drawn.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">How often</th>
            <td>
                After they close it, keep it away for
                <input type="number" min="1" max="90" class="small-text" name="<?php echo $n('popup_snooze'); ?>" value="<?php echo esc_attr((int) $opts['popup_snooze']); ?>" /> days.
                <ul style="margin:8px 0 0 18px;list-style:disc;color:#646970;">
                    <li>At most once per visit.</li>
                    <li>Never again for this campaign once they tap the banner or button, copy the code, or place an order.</li>
                    <li>Never on the cart, checkout, My Account or order pages &mdash; nothing gets in the way of a purchase.</li>
                    <li>Change the image, link, code or dates and it counts as a new campaign, so everyone sees the new one.</li>
                </ul>
            </td>
        </tr>

        <tr>
            <th scope="row">Show on</th>
            <td>
                <select name="<?php echo $n('popup_devices'); ?>">
                    <option value="all" <?php selected($opts['popup_devices'], 'all'); ?>>Phones and computers</option>
                    <option value="desktop" <?php selected($opts['popup_devices'], 'desktop'); ?>>Computers only</option>
                    <option value="mobile" <?php selected($opts['popup_devices'], 'mobile'); ?>>Phones and tablets only</option>
                </select>
            </td>
        </tr>

        <tr>
            <th scope="row">Width on computers</th>
            <td>
                <input type="number" min="320" max="900" class="small-text" name="<?php echo $n('popup_width'); ?>" value="<?php echo esc_attr((int) $opts['popup_width']); ?>" /> px
                <p class="description">On phones it slides up from the bottom, full width, and can be swiped away. On short screens it shrinks to fit, so the banner is never cut off.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Never on pages containing</th>
            <td>
                <textarea name="<?php echo $n('popup_exclude'); ?>" rows="3" class="large-text code" style="max-width:420px;" placeholder="/spare-parts/&#10;/warranty/"><?php echo esc_textarea($opts['popup_exclude']); ?></textarea>
                <p class="description">One per line. Any page whose address contains it is skipped &mdash; the বাংলা <code>/bn/</code> pages too.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Preview</th>
            <td>
                <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(add_query_arg(self::PREVIEW_ARG, '1', home_url('/'))); ?>">👁 Preview on the site</a>
                <p class="description">Opens your homepage with the popup showing straight away &mdash; only for you, as a logged-in admin. The
                   preview ignores the schedule and the &ldquo;once per visit&rdquo; rules. <strong>Save Changes first</strong> to see your latest edits.</p>
            </td>
        </tr>

        <script>
        (function(){
            /* This runs in the middle of the page, but WordPress loads the media library
               (wp.media) in the footer, after it. So look for it when the button is
               pressed, not now — checking now found nothing and left the button dead. */
            document.querySelectorAll('.aun-pop-media').forEach(function(box){
                var input = box.querySelector('input[type=hidden]'), img = box.querySelector('img'),
                    none = box.querySelector('.aun-pop-none'), dims = box.querySelector('.aun-pop-dims'),
                    choose = box.querySelector('.aun-pop-choose'), remove = box.querySelector('.aun-pop-remove'), frame;
                choose.addEventListener('click', function(e){
                    e.preventDefault();
                    if(!window.wp || !wp.media){ alert('The media library is still loading. Please try again in a moment.'); return; }
                    if(!frame){
                        frame = wp.media({ title: 'Choose the popup banner', library: { type: 'image' }, button: { text: 'Use this image' }, multiple: false });
                        frame.on('select', function(){
                            var a = frame.state().get('selection').first().toJSON();
                            var s = (a.sizes && (a.sizes.medium || a.sizes.thumbnail)) || a;
                            input.value = a.id;
                            img.src = s.url; img.style.display = ''; none.style.display = 'none';
                            remove.style.display = '';
                            dims.textContent = (a.width && a.height) ? a.width + ' × ' + a.height + ' px' : '';
                        });
                    }
                    frame.open();
                });
                remove.addEventListener('click', function(e){
                    e.preventDefault();
                    input.value = '0';
                    img.removeAttribute('src'); img.style.display = 'none'; none.style.display = '';
                    remove.style.display = 'none'; dims.textContent = '';
                });
            });
        })();
        </script>
        <?php
    }
}

AUN_Campaign_Notice_Bar::init();
AUN_CB_Popup::init();

/**
 * The boundary timer must not be deferred or delayed: it runs during parse so the
 * notice settles before first paint. If WP Rocket holds it back the bar flashes in
 * and then vanishes, which looks worse than being late.
 */
add_filter('rocket_delay_js_exclusions', function ($excluded) {
    $excluded[] = 'aun-cb-top';
    $excluded[] = 'aun-cb-pop';   // the popup's timer must start with the page, not on first tap
    return $excluded;
});
add_filter('rocket_excluded_inline_js_content', function ($excluded) {
    $excluded[] = 'aun-cb-top';
    $excluded[] = 'aun-cb-pop';
    return $excluded;
});
/* Remove Unused CSS would see .is-open / .aun-pop-lock as unused: they only exist once the
   popup opens. Keep the popup's stylesheet whole. */
add_filter('rocket_rucss_inline_content_exclusions', function ($excluded) {
    $excluded[] = '.aun-pop';
    return (array) $excluded;
});
add_filter('rocket_rucss_safelist', function ($safelist) {
    $safelist[] = '.aun-pop';
    $safelist[] = '.aun-pop-lock';
    return (array) $safelist;
});

register_activation_hook(__FILE__, function () {
    AUN_Campaign_Notice_Bar::reschedule_boundaries();
    AUN_Campaign_Notice_Bar::purge_caches();
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('aun_cb_boundary', ['start']);
    wp_clear_scheduled_hook('aun_cb_boundary', ['end']);
    AUN_Campaign_Notice_Bar::purge_caches();
});
