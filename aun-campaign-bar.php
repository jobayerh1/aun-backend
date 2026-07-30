<?php
/**
 * Plugin Name: AUN Campaign Notice Bar
 * Description: Scheduled campaign notices via shortcodes. Context-Aware Top Bars, Global Sale Detection, Flatsome badge integration, pre-installed campaign templates, Bangla (/bn/, TranslatePress) auto-switch, and stock/discontinued awareness.
 * Version: 1.5.0
 * Author: Smart Living Bangladesh
 *
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

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_shortcode('aun_campaign_notice', [__CLASS__, 'shortcode_top']);
        add_shortcode('aun_campaign_product_notice', [__CLASS__, 'shortcode_product']);

        add_filter('woocommerce_sale_flash', [__CLASS__, 'custom_smart_sale_bubble'], 99, 3);
        add_action('wp_footer', [__CLASS__, 'render_custom_css']);

        // The "any sale active" answer is cached — refresh it whenever a product changes.
        add_action('save_post_product', [__CLASS__, 'flush_sale_cache']);
        add_action('woocommerce_update_product', [__CLASS__, 'flush_sale_cache']);
    }

    public static function defaults() {
        return [
            'enabled'                       => '1',
            'message'                       => '🎉 Offer — Use code <strong>EIDBIGSCREEN</strong> (Ends 20 Mar)',
            'message_bn'                    => '🎉 অফার — কোড <strong>EIDBIGSCREEN</strong> ব্যবহার করুন (২০ মার্চ পর্যন্ত)',
            'product_message'               => '🎉 Offer — Use code <strong>EIDBIGSCREEN</strong> <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">Details →</a>',
            'product_message_bn'            => '🎉 অফার — কোড <strong>EIDBIGSCREEN</strong> ব্যবহার করুন <a href="/eid-festival/" style="font-size:13px; color:#0188fe; text-decoration:none; font-weight:700;">বিস্তারিত →</a>',
            'start'                         => '',
            'end'                           => '',
            'timezone'                      => 'Asia/Dhaka',

            // Smart Sale Defaults
            'smart_sale_enabled'            => '1',
            'smart_sale_message'            => '🔥 <strong>Flash Sale!</strong> Save <strong style="color:#16a34a;">{discount_amount}</strong> on this model. Ends on {sale_end_date}.',
            'smart_sale_message_bn'         => '🔥 <strong>ফ্ল্যাশ সেল!</strong> এই মডেলে <strong style="color:#16a34a;">{discount_amount}</strong> সাশ্রয় করুন। অফার শেষ {sale_end_date}।',
            'smart_sale_top_message'        => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> Save <strong style="color:#fde047;">{discount_amount}</strong> on this model today.',
            'smart_sale_top_message_bn'     => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> আজই এই মডেলে <strong style="color:#fde047;">{discount_amount}</strong> সাশ্রয় করুন।',
            'smart_sale_global_top_message' => '🔥 <strong style="color:#fff;">Flash Sale Active!</strong> We have special discounts running right now. <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">Shop the Sale →</a>',
            'smart_sale_global_top_message_bn' => '🔥 <strong style="color:#fff;">ফ্ল্যাশ সেল চলছে!</strong> এখনই বিশেষ ছাড় চলছে। <a href="/shop/" style="color:#fde047; text-decoration:underline; font-weight:700;">সেল দেখুন →</a>',
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
        return array_merge(self::defaults(), $opts);
    }

    public static function register_settings() {
        register_setting('aun_campaign_notice_bar', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default' => self::defaults(),
        ]);
    }

    public static function sanitize_options($input) {
        $out = self::get_options();

        // Global Campaign
        $out['enabled'] = (!empty($input['enabled']) && $input['enabled'] === '1') ? '1' : '0';
        $out['timezone'] = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : 'Asia/Dhaka';
        if ($out['timezone'] === '') { $out['timezone'] = 'Asia/Dhaka'; }
        $out['start'] = isset($input['start']) ? self::sanitize_dt($input['start']) : '';
        $out['end']   = isset($input['end']) ? self::sanitize_dt($input['end']) : '';

        // Smart Sale toggle
        $out['smart_sale_enabled'] = (!empty($input['smart_sale_enabled']) && $input['smart_sale_enabled'] === '1') ? '1' : '0';

        // All message fields (EN + BN) share the same sanitizer.
        foreach (self::message_keys() as $key) {
            $out[$key] = isset($input[$key]) ? wp_kses_post($input[$key]) : '';
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
    public static function is_bn() {
        // TranslatePress sets the current language globally on the frontend.
        if (isset($GLOBALS['TRP_LANGUAGE']) && is_string($GLOBALS['TRP_LANGUAGE']) && $GLOBALS['TRP_LANGUAGE'] !== '') {
            return stripos($GLOBALS['TRP_LANGUAGE'], 'bn') === 0;
        }
        // Fallback: the /bn/ URL prefix itself.
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        return (bool) preg_match('#^/bn(/|$|\?)#i', $uri);
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
        $name = self::OPTION_KEY;
        echo '<textarea id="aun-cb-' . esc_attr($key) . '" name="' . esc_attr($name) . '[' . esc_attr($key) . ']" rows="' . (int) $rows . '" class="large-text">' . esc_textarea($opts[$key]) . '</textarea>';
        if ($desc !== '') {
            echo '<p class="description">' . $desc . '</p>';
        }
        echo '<p style="margin:8px 0 2px;font-weight:600;color:#0f6c2f;">বাংলা version <span style="font-weight:normal;color:#646970;">(shown automatically on /bn/ pages — leave empty to reuse the English text)</span></p>';
        echo '<textarea id="aun-cb-' . esc_attr($key) . '_bn" name="' . esc_attr($name) . '[' . esc_attr($key) . '_bn]" rows="' . (int) $rows . '" class="large-text" style="border-color:#9fd4ae;">' . esc_textarea($opts[$key . '_bn']) . '</textarea>';
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
                            <?php self::field_pair($opts, 'smart_sale_global_top_message', 2, 'If ANY in-stock product is on sale, this message shows on the Homepage/Shop to attract customers to the deals.'); ?>
                        </td>
                    </tr>
                    <tr style="background: #f0fdf4; border-left: 3px solid #16a34a;">
                        <th scope="row" style="padding-left: 15px;">Product Page Smart Sale Message</th>
                        <td>
                            <?php self::field_pair($opts, 'smart_sale_message', 3,
                                '<strong>Magic Tags:</strong><br>'
                                . '<code>{discount_amount}</code> = Automatically shows the exact Taka saved (e.g., ৳3,500).<br>'
                                . '<code>{sale_end_date}</code> = Automatically pulls the "Sale price dates" end date from WooCommerce.'); ?>
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
                            <?php self::field_pair($opts, 'message', 3, 'Used with shortcode <code>[aun_campaign_notice]</code>.'); ?>
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
                            <p class="description">⚠️ <strong>WP Rocket note:</strong> pages are cached, so the bar appears/disappears on a cached page only after the cache refreshes. For an exact start/end, clear the WP Rocket cache at the boundary (or keep cache lifespan short during campaigns).</p>
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
                    <div style="padding:12px;border:1px solid #006bc7;border-radius:8px;background:#0188fe; color:#fff; font-weight: 500;">
                        <?php echo self::render_top_notice(true, 'sale'); ?>
                    </div>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h3>Top Bar Notice (Shop/Global Magnet)</h3>
                    <p class="description" style="margin-top:-10px; margin-bottom: 10px;">Shows on the Homepage if ANY in-stock product is on sale in your store.</p>
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

        $tz_name = $opts['timezone'] ?: 'Asia/Dhaka';
        try { $tz = new DateTimeZone($tz_name); }
        catch (Exception $e) { $tz = new DateTimeZone('Asia/Dhaka'); }

        $now = new DateTime('now', $tz);

        if (!empty($opts['start'])) {
            $start = DateTime::createFromFormat('Y-m-d H:i', $opts['start'], $tz);
            if ($start instanceof DateTime && $now < $start) return false;
        }

        if (!empty($opts['end'])) {
            $end = DateTime::createFromFormat('Y-m-d H:i', $opts['end'], $tz);
            if ($end instanceof DateTime && $now >= $end) return false;
        }

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
        $cached = get_transient('aun_cb_any_sale');
        if ($cached !== false) {
            return $cached === '1';
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

        set_transient('aun_cb_any_sale', $active ? '1' : '0', 10 * MINUTE_IN_SECONDS);
        return $active;
    }

    public static function flush_sale_cache() {
        delete_transient('aun_cb_any_sale');
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
        if ($product->is_type('variable')) {
            $reg = $product->get_variation_regular_price('min', true);
            $sale = $product->get_variation_sale_price('min', true);
            $saved = $reg - $sale;
        } else {
            $saved = (float)$product->get_regular_price() - (float)$product->get_price();
        }

        if ($saved <= 0) return $html;

        $saved_text = wp_strip_all_tags(wc_price($saved));
        $custom_text = 'Save<br>' . esc_html($saved_text);

        // Inject our custom text and classes directly into Flatsome's native badge HTML
        // so Flatsome stacks it perfectly with its own "New" badge.
        if (strpos($html, 'badge-inner') !== false) {
            $html = preg_replace('/<span class="onsale">.*?<\/span>/i', '<span class="onsale">' . $custom_text . '</span>', $html);
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

    public static function shortcode_top($atts = [], $content = null) {
        return self::render_top_notice(false, 'auto');
    }

    public static function shortcode_product($atts = [], $content = null) {
        return self::render_product_notice(false, 'auto');
    }

    /* ------------------------------------------------------------- Render logic */

    /** Replace {sale_end_date}; when there is no end date, drop that sentence / soften the wording (EN + BN aware). */
    private static function fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn) {
        $msg = str_replace('{discount_amount}', $saved_amt_html, $msg);
        if ($end_date_formatted) {
            $msg = str_replace('{sale_end_date}', $end_date_formatted, $msg);
        } else {
            $msg = preg_replace('/[^.!?।]*(?:Ends on|Valid until|অফার শেষ)[^.!?।]*\{sale_end_date\}[^.!?।]*[.!?।]/iu', '', $msg);
            $msg = str_replace('{sale_end_date}', $used_bn ? 'শীঘ্রই' : 'soon', $msg);
        }
        return $msg;
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

                if ($product->is_type('variable')) {
                    $reg = $product->get_variation_regular_price('min', true);
                    $sale = $product->get_variation_sale_price('min', true);
                    $saved = $reg - $sale;
                } else {
                    $saved = (float)$product->get_regular_price() - (float)$product->get_price();
                }
                $saved_amt_html = wc_price($saved);

                $end_ts = '';
                if ($product->get_date_on_sale_to()) {
                    $end_ts = $product->get_date_on_sale_to()->getOffsetTimestamp();
                } elseif ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->is_on_sale() && $variation->get_date_on_sale_to()) {
                            $end_ts = $variation->get_date_on_sale_to()->getOffsetTimestamp();
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
            $msg = self::fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn);
            return wp_kses_post($msg);

        } elseif ($show_sale_global) {
            $msg = $force ? trim((string)$opts['smart_sale_global_top_message']) : self::pick($opts, 'smart_sale_global_top_message');
            if (empty($msg)) return $force ? '<em>No Global Sale Alert message set.</em>' : '';
            return wp_kses_post($msg);

        } elseif ($show_global) {
            $msg = $force ? trim((string)$opts['message']) : self::pick($opts, 'message');
            if (empty($msg)) return $force ? '<em>No message set.</em>' : '';
            return wp_kses_post($msg);
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

                if ($product->is_type('variable')) {
                    $reg = $product->get_variation_regular_price('min', true);
                    $sale = $product->get_variation_sale_price('min', true);
                    $saved = $reg - $sale;
                } else {
                    $saved = (float)$product->get_regular_price() - (float)$product->get_price();
                }
                $saved_amt_html = wc_price($saved);

                $end_ts = '';
                if ($product->get_date_on_sale_to()) {
                    $end_ts = $product->get_date_on_sale_to()->getOffsetTimestamp();
                } elseif ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation && $variation->is_on_sale() && $variation->get_date_on_sale_to()) {
                            $end_ts = $variation->get_date_on_sale_to()->getOffsetTimestamp();
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

            $message = self::fill_sale_tags($msg, $saved_amt_html, $end_date_formatted, $used_bn);
            $icon = 'fa-tags';
            $theme_color = '#16a34a'; // Green
            $bg_color = '#f0fdf4';
            $border_color = '#dcfce7';

        } elseif ($show_global) {
            $message = $force ? trim((string)$opts['product_message']) : self::pick($opts, 'product_message');
            if (empty($message)) return $force ? '<em>No global product message set.</em>' : '';

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

AUN_Campaign_Notice_Bar::init();
