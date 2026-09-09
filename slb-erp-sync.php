<?php
/**
 * Plugin Name: SLB ERP Sync
 * Description: Automatically imports dealer serial numbers from UltimatePOS ERP
 *              into the SLB Warranty plugin. Runs hourly via cPanel cron.
 * Version:     1.1.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HOW IT WORKS
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. A cPanel cron job runs every hour and hits:
 *      GET https://aun-projector.com.bd/wp-json/slb/v1/erp-sync
 *      Header: X-SLB-Key: {your sync secret}
 *
 * 2. WordPress calls the ERP endpoint:
 *      GET https://portal.smartliving.com.bd/api/warranty-serials
 *      Header: X-Warranty-Secret: {WARRANTY_API_SECRET from ERP .env}
 *      Params: since={last_sync_date}&dealer_group_id={configured group}
 *
 * 3. The ERP returns serials from recent dealer sales.
 *
 * 4. WordPress matches each serial's contact_id to a distributor (via the
 *    erp_customer_id column you set once per distributor), inserts new serials,
 *    and logs the result.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE-TIME SETUP
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. Install and activate this plugin.
 * 2. Go to SLB Warranty → ERP Sync Settings.
 * 3. Fill in: ERP Base URL, ERP Secret, Dealer Group ID.
 * 4. For each distributor in SLB Warranty → Distributors, enter their ERP
 *    Contact ID (the number in the URL when you view them, e.g. /contacts/160).
 * 5. For each product in SLB Warranty → Products, enter their ERP Product ID.
 * 6. Add the cPanel cron job (Settings page shows the exact command).
 * ─────────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Activation — create sync log table, add erp_customer_id / erp_product_id cols
// ─────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'slb_sync_activate' );
function slb_sync_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Sync log table
    $t_log = $wpdb->prefix . 'slb_sync_log';
    dbDelta( "CREATE TABLE IF NOT EXISTS $t_log (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        serials_new int DEFAULT 0,
        serials_skipped int DEFAULT 0,
        serials_total int DEFAULT 0,
        erp_since date DEFAULT NULL,
        status varchar(20) DEFAULT 'ok',
        message text,
        PRIMARY KEY (id)
    ) $charset;" );

    // Add erp_customer_id to distributors (once — safe to call multiple times)
    $t_dist = $wpdb->prefix . 'slb_distributors';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$t_dist'" ) === $t_dist ) {
        $has = $wpdb->get_results( "SHOW COLUMNS FROM $t_dist LIKE 'erp_customer_id'" );
        if ( empty( $has ) ) {
            $wpdb->query( "ALTER TABLE $t_dist ADD COLUMN erp_customer_id bigint(20) DEFAULT NULL AFTER code" );
            $wpdb->query( "ALTER TABLE $t_dist ADD KEY erp_customer_id_idx (erp_customer_id)" );
        }
    }

    // Add erp_product_id to products (once)
    $t_prods = $wpdb->prefix . 'slb_products';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$t_prods'" ) === $t_prods ) {
        $has = $wpdb->get_results( "SHOW COLUMNS FROM $t_prods LIKE 'erp_product_id'" );
        if ( empty( $has ) ) {
            $wpdb->query( "ALTER TABLE $t_prods ADD COLUMN erp_product_id bigint(20) DEFAULT NULL AFTER name" );
        }
    }

    // Store activation flag so we show a welcome notice
    update_option( 'slb_sync_activated', 1 );
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin menu — adds under existing SLB Warranty menu
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'slb_sync_admin_menu', 20 );
function slb_sync_admin_menu() {
    add_submenu_page(
        'slb-warranty',
        'ERP Sync',
        'ERP Sync',
        'manage_options',
        'slb-erp-sync',
        'slb_sync_settings_page'
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Activation notice
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_notices', 'slb_sync_activation_notice' );
function slb_sync_activation_notice() {
    if ( ! get_option( 'slb_sync_activated' ) ) return;
    delete_option( 'slb_sync_activated' );
    $url = admin_url( 'admin.php?page=slb-erp-sync' );
    echo '<div class="notice notice-success"><p><strong>SLB ERP Sync activated.</strong> '
       . '<a href="' . esc_url( $url ) . '">Go to ERP Sync Settings →</a></p></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// Settings helpers
// ─────────────────────────────────────────────────────────────────────────────

function slb_sync_get_settings(): array {
    return wp_parse_args( get_option( 'slb_sync_settings', [] ), [
        'erp_base_url'       => '',
        'erp_secret'         => '',
        'dealer_group_id'    => '',
        'sync_secret'        => wp_generate_password( 32, false ),
        'lookback_days'      => 7,
        'last_sync_date'     => '',
        'last_sync_ts'       => '',
        'last_backfill_ts'   => '',
    ] );
}

// ─────────────────────────────────────────────────────────────────────────────
// Settings + sync log page
// ─────────────────────────────────────────────────────────────────────────────

function slb_sync_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;

    $opts = slb_sync_get_settings();

    // ── Save settings ──
    if ( isset( $_POST['slb_sync_nonce'] ) && wp_verify_nonce( $_POST['slb_sync_nonce'], 'slb_sync_save' ) ) {
        $opts['erp_base_url']    = esc_url_raw( rtrim( $_POST['erp_base_url'] ?? '', '/' ) );
        $opts['erp_secret']      = sanitize_text_field( $_POST['erp_secret'] ?? '' );
        $opts['dealer_group_id'] = absint( $_POST['dealer_group_id'] ?? 0 );
        $opts['lookback_days']   = max( 1, min( 90, absint( $_POST['lookback_days'] ?? 7 ) ) );
        // Preserve generated sync_secret; allow manual override
        if ( ! empty( $_POST['sync_secret'] ) ) {
            $opts['sync_secret'] = sanitize_text_field( $_POST['sync_secret'] );
        }
        update_option( 'slb_sync_settings', $opts );
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    // ── Manual sync ──
    $manual_result = '';
    if ( isset( $_POST['slb_sync_manual_nonce'] ) && wp_verify_nonce( $_POST['slb_sync_manual_nonce'], 'slb_sync_manual' ) ) {
        $result = slb_sync_run( $opts );
        if ( is_wp_error( $result ) ) {
            $manual_result = '<div class="notice notice-error"><p><strong>Sync failed:</strong> ' . esc_html( $result->get_error_message() ) . '</p></div>';
        } else {
            $manual_result = '<div class="notice notice-success"><p><strong>Sync complete.</strong> '
                . 'New: ' . intval($result['new']) . ' &nbsp;|&nbsp; '
                . 'Skipped (already exist): ' . intval($result['skipped']) . ' &nbsp;|&nbsp; '
                . 'Total from ERP: ' . intval($result['total']) . '</p></div>';
        }
    }

    // ── Backfill / Full re-scan (recover previously-missed serials) ──
    if ( isset( $_POST['slb_sync_backfill_nonce'] ) && wp_verify_nonce( $_POST['slb_sync_backfill_nonce'], 'slb_sync_backfill' ) ) {
        $backfill_since = sanitize_text_field( $_POST['backfill_since'] ?? '' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $backfill_since ) ) {
            $manual_result = '<div class="notice notice-error"><p><strong>Backfill failed:</strong> Please enter a valid date in YYYY-MM-DD format.</p></div>';
        } else {
            $result = slb_sync_run( $opts, $backfill_since );
            if ( is_wp_error( $result ) ) {
                $manual_result = '<div class="notice notice-error"><p><strong>Backfill failed:</strong> ' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                $manual_result = '<div class="notice notice-success"><p><strong>Backfill complete (scanning from ' . esc_html( $backfill_since ) . ').</strong> &nbsp;'
                    . 'Recovered (newly imported): <strong>' . intval($result['new']) . '</strong> &nbsp;|&nbsp; '
                    . 'Already present: ' . intval($result['skipped']) . ' &nbsp;|&nbsp; '
                    . 'Total serials scanned: ' . intval($result['total']) . '</p></div>';
                $opts = slb_sync_get_settings(); // refresh so the card shows the new backfill time
            }
        }
    }

    // ── Reconcile pending registrations (auto-approve those whose serials now exist) ──
    if ( isset( $_POST['slb_sync_reconcile_nonce'] ) && wp_verify_nonce( $_POST['slb_sync_reconcile_nonce'], 'slb_sync_reconcile' ) ) {
        if ( function_exists( 'slb_reconcile_pending_registrations' ) ) {
            $approved = (int) slb_reconcile_pending_registrations( null, 100 );
            $manual_result = '<div class="notice notice-success"><p><strong>Reconciliation complete.</strong> Auto-approved <strong>' . $approved . '</strong> registration(s) whose serials are now in inventory. If you had a large backlog, click again to process the next batch of 100.</p></div>';
        } else {
            $manual_result = '<div class="notice notice-error"><p>The <strong>AUN Warranty &amp; Registration</strong> plugin must be active to run reconciliation.</p></div>';
        }
    }

    // ── Sync log ──
    $t_log = $wpdb->prefix . 'slb_sync_log';
    $logs  = $wpdb->get_results( "SELECT * FROM $t_log ORDER BY synced_at DESC LIMIT 30" );

    $cron_url  = rest_url( 'slb/v1/erp-sync' );
    $cron_cmd  = "curl -s \"{$cron_url}\" -H \"X-SLB-Sync-Secret: " . esc_attr($opts['sync_secret']) . "\" > /dev/null 2>&1";

    ?>
    <div class="wrap">
        <h1>ERP Sync — Serial Number Automation</h1>

        <?php echo $manual_result; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

            <!-- Settings -->
            <div class="card" style="padding:20px;">
                <h2 style="margin-top:0">Connection Settings</h2>
                <form method="post">
                    <?php wp_nonce_field( 'slb_sync_save', 'slb_sync_nonce' ); ?>
                    <table class="form-table" style="margin:0">
                        <tr>
                            <th>ERP Base URL</th>
                            <td><input name="erp_base_url" style="width:100%" value="<?php echo esc_attr($opts['erp_base_url']); ?>" placeholder="https://portal.smartliving.com.bd" /></td>
                        </tr>
                        <tr>
                            <th>ERP Secret</th>
                            <td>
                                <input name="erp_secret" style="width:100%" value="<?php echo esc_attr($opts['erp_secret']); ?>" placeholder="Must match WARRANTY_API_SECRET in ERP .env" />
                                <p class="description">Must exactly match the value you set in the ERP's <code>.env</code> file as <code>WARRANTY_API_SECRET</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Dealers Group ID</th>
                            <td>
                                <input name="dealer_group_id" type="number" style="width:100px" value="<?php echo esc_attr($opts['dealer_group_id']); ?>" placeholder="e.g. 2" />
                                <p class="description">In your ERP go to <strong>Contacts → Customer Groups</strong> and note the ID of your "Dealers" group. Leave 0 to import from all contacts (not recommended).</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Look-back (days)</th>
                            <td>
                                <input name="lookback_days" type="number" min="1" max="90" style="width:80px" value="<?php echo esc_attr($opts['lookback_days']); ?>" />
                                <p class="description">How many days back to check on first run (or after a gap). Normally 7 is fine since sync runs hourly.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Cron Secret</th>
                            <td>
                                <input name="sync_secret" style="width:100%" value="<?php echo esc_attr($opts['sync_secret']); ?>" />
                                <p class="description">This secret is sent by the cPanel cron job to authenticate sync requests to WordPress. Keep it private.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                </form>
            </div>

            <!-- Cron + status -->
            <div>
                <div class="card" style="padding:20px;margin-bottom:16px;">
                    <h2 style="margin-top:0">cPanel Cron Job</h2>
                    <p>Add this command in cPanel → Cron Jobs. Set it to run <strong>every hour</strong> (select "Once Per Hour" from the common settings dropdown).</p>
                    <code style="display:block;background:#f1f1f1;padding:10px;border-radius:4px;word-break:break-all;font-size:12px;"><?php echo esc_html($cron_cmd); ?></code>
                    <p style="margin-top:8px;font-size:12px;color:#666">In cPanel → Cron Jobs, paste the above into the "Command" field and select "Once Per Hour".</p>
                </div>

                <div class="card" style="padding:20px;">
                    <h2 style="margin-top:0">Manual Sync</h2>
                    <p>Trigger a sync right now to test the connection or catch up after a gap.</p>
                    <?php
                    $last = $opts['last_sync_ts'] ? 'Last sync: ' . esc_html($opts['last_sync_ts']) : 'Never synced yet.';
                    echo '<p style="color:#666;font-size:13px;">' . $last . '</p>';
                    ?>
                    <form method="post">
                        <?php wp_nonce_field( 'slb_sync_manual', 'slb_sync_manual_nonce' ); ?>
                        <button type="submit" class="button button-primary">
                            ▶ Sync Now
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Backfill / recovery -->
        <div class="card" style="padding:20px;margin-bottom:24px;border-left:4px solid #2271b1;">
            <h2 style="margin-top:0">Backfill — Recover Missing Serials</h2>
            <p>Re-scans <strong>all</strong> dealer sales updated on or after the date below and imports any serials that aren't already in the warranty database. Safe to run anytime: existing serials are skipped, only missing ones are added, and the normal hourly sync is left untouched.</p>
            <?php
            $last_bf = ! empty($opts['last_backfill_ts']) ? 'Last backfill: ' . esc_html($opts['last_backfill_ts']) : 'No backfill run yet.';
            echo '<p style="color:#666;font-size:13px;">' . $last_bf . '</p>';
            $default_bf = date( 'Y-m-d', strtotime( '-30 days' ) );
            ?>
            <form method="post" onsubmit="return confirm('Run a full backfill from the selected date? This may take up to a minute.');">
                <?php wp_nonce_field( 'slb_sync_backfill', 'slb_sync_backfill_nonce' ); ?>
                <label><strong>Scan sales updated since:</strong>
                    <input type="date" name="backfill_since" value="<?php echo esc_attr( $default_bf ); ?>" required style="margin:0 8px;" />
                </label>
                <button type="submit" class="button button-secondary">⟳ Run Backfill</button>
                <p class="description" style="margin-top:8px">To recover everything since you went live, set this to your ERP go-live date (e.g. 2025-12-01). To fix only the recent losses, 30 days back is enough. You can run it repeatedly — it never creates duplicates.</p>
            </form>
        </div>

        <!-- Auto-approve waiting registrations -->
        <div class="card" style="padding:20px;margin-bottom:24px;border-left:4px solid #00a32a;">
            <h2 style="margin-top:0">Auto-Approve Waiting Registrations</h2>
            <p>Checks every <em>pending / not-found</em> warranty registration and automatically approves the ones whose serial is now in inventory, using your existing auto-approval rule. Customers are notified exactly like a normal approval. Dealer-name mismatches and still-missing serials are left for manual review.</p>
            <p style="color:#646970;font-size:13px;">This already runs automatically — instantly as each serial syncs, and once daily as a safety net. Use this button to clear your existing backlog now, in batches of 100.</p>
            <form method="post">
                <?php wp_nonce_field( 'slb_sync_reconcile', 'slb_sync_reconcile_nonce' ); ?>
                <button type="submit" class="button button-secondary">✓ Reconcile Pending Registrations Now</button>
            </form>
        </div>

        <!-- Distributor mapping hint -->
        <div class="card" style="padding:20px;margin-bottom:24px;background:#fffbeb;border-left:4px solid #f59e0b;">
            <h3 style="margin-top:0;color:#92400e">⚠ One-time setup required: Distributor & Product ID mapping</h3>
            <p>For serials to be linked to the correct distributor on import, you must set the <strong>ERP Contact ID</strong> for each distributor.</p>
            <ol style="margin-bottom:0">
                <li>Go to <strong>SLB Warranty → Distributors</strong></li>
                <li>Edit each distributor and enter their <strong>ERP Contact ID</strong> (the number in the ERP URL when you view that contact — e.g. <code>/contacts/160</code> → enter <code>160</code>)</li>
                <li>Optionally go to <strong>SLB Warranty → Products</strong> and set the <strong>ERP Product ID</strong> for each product (from the ERP URL when viewing the product)</li>
            </ol>
            <p style="margin-bottom:0;margin-top:10px">Serials for unmapped distributors will still be imported but will not be linked to any distributor — you can edit them later from the Serials page.</p>
        </div>

        <!-- Sync log -->
        <h2>Sync Log <small style="font-weight:normal;font-size:14px">(last 30 runs)</small></h2>
        <?php if ( empty($logs) ) : ?>
            <p style="color:#888">No sync runs yet. Click "Sync Now" above to test, or wait for the cron job to fire.</p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:160px">Time</th>
                        <th style="width:80px">Status</th>
                        <th style="width:80px">New</th>
                        <th style="width:100px">Skipped</th>
                        <th style="width:80px">Total</th>
                        <th style="width:100px">ERP Since</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $log ) :
                    $badge_style = $log->status === 'ok'
                        ? 'background:#dcfce7;color:#166534;'
                        : 'background:#fee2e2;color:#991b1b;';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $log->synced_at ); ?></td>
                        <td><span style="<?php echo $badge_style; ?>padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700"><?php echo esc_html( $log->status ); ?></span></td>
                        <td style="color:#166534;font-weight:700"><?php echo intval($log->serials_new); ?></td>
                        <td style="color:#888"><?php echo intval($log->serials_skipped); ?></td>
                        <td><?php echo intval($log->serials_total); ?></td>
                        <td><?php echo esc_html( $log->erp_since ); ?></td>
                        <td style="font-size:12px;color:#555"><?php echo esc_html( $log->message ?: '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTE: ERP ID fields (erp_customer_id / erp_product_id) are now rendered
// directly in the warranty plugin's distributor and product admin forms.
// No JS injection needed here.
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// REST endpoint — triggered by cPanel cron job every hour
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'rest_api_init', 'slb_sync_register_rest' );
function slb_sync_register_rest() {
    register_rest_route( 'slb/v1', '/erp-sync', [
        'methods'             => 'GET',
        'callback'            => 'slb_sync_rest_handler',
        'permission_callback' => '__return_true', // auth done inside the handler
    ] );
}

function slb_sync_rest_handler( WP_REST_Request $request ) {
    $opts     = slb_sync_get_settings();
    $expected = $opts['sync_secret'] ?? '';
    $provided = $request->get_header( 'X-SLB-Sync-Secret' ) ?? '';

    if ( empty( $expected ) || ! hash_equals( $expected, $provided ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Unauthorized.' ], 401 );
    }

    $result = slb_sync_run( $opts );

    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'message' => $result->get_error_message(),
        ], 500 );
    }

    return new WP_REST_Response( [
        'success' => true,
        'new'     => $result['new'],
        'skipped' => $result['skipped'],
        'total'   => $result['total'],
    ], 200 );
}

// ─────────────────────────────────────────────────────────────────────────────
// Core sync logic
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetches serials from the ERP and inserts new ones into the WordPress serial table.
 *
 * @return array{new:int, skipped:int, total:int}|WP_Error
 */
function slb_sync_run( array $opts, $override_since = null ) {
    global $wpdb;

    $erp_base_url    = rtrim( $opts['erp_base_url'] ?? '', '/' );
    $erp_secret      = $opts['erp_secret']      ?? '';
    $dealer_group_id = absint( $opts['dealer_group_id'] ?? 0 );
    $lookback_days   = max( 1, absint( $opts['lookback_days'] ?? 7 ) );

    if ( empty( $erp_base_url ) || empty( $erp_secret ) ) {
        return new WP_Error( 'not_configured', 'ERP Base URL or ERP Secret is not configured in ERP Sync Settings.' );
    }

    // A backfill passes an explicit start date and must NOT disturb the live
    // hourly cursor; a normal run uses (and later advances) last_sync_date.
    $is_backfill = ! empty( $override_since );

    if ( $is_backfill ) {
        $since = $override_since;
        // A backfill can span months over several pages; lift the PHP time
        // limit where the host allows it so it doesn't die half-way.
        if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); }
    } else {
        // Use the last sync date or fall back to lookback_days days ago
        $since = ! empty( $opts['last_sync_date'] )
            ? $opts['last_sync_date']
            : date( 'Y-m-d', strtotime( "-{$lookback_days} days" ) );
    }

    $t_serials = $wpdb->prefix . 'slb_serials';
    $t_dist    = $wpdb->prefix . 'slb_distributors';
    $t_prods   = $wpdb->prefix . 'slb_products';

    $per_page  = 500;
    $offset    = 0;
    $new_count = 0;
    $skipped   = 0;
    $total     = 0;
    $warnings  = [];

    // Page through the ERP results until it reports no more pages. The hard
    // cap is a safety stop against an infinite loop (200 x 500 = 100k rows).
    for ( $page = 0; $page < 200; $page++ ) {
        $params = [
            'since'           => $since,
            'dealer_group_id' => $dealer_group_id,
            'per_page'        => $per_page,
            'offset'          => $offset,
        ];
        $url = $erp_base_url . '/api/warranty-serials?' . http_build_query( $params );

        $response = wp_remote_get( $url, [
            'headers' => [
                'X-Warranty-Secret' => $erp_secret,
                'Accept'            => 'application/json',
            ],
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            slb_sync_log( $new_count, $skipped, $total, $since, 'error', 'ERP connection failed: ' . $response->get_error_message() );
            return new WP_Error( 'erp_connection', $response->get_error_message() );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            $msg = "ERP returned HTTP {$http_code}.";
            slb_sync_log( $new_count, $skipped, $total, $since, 'error', $msg );
            return new WP_Error( 'erp_error', $msg );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE || empty( $body['success'] ) ) {
            $msg = 'ERP returned invalid JSON or success=false.';
            slb_sync_log( $new_count, $skipped, $total, $since, 'error', $msg );
            return new WP_Error( 'erp_invalid', $msg );
        }

        $serials_data = $body['data'] ?? [];
        $total       += count( $serials_data );

        foreach ( $serials_data as $item ) {
            $serial = sanitize_text_field( $item['serial'] ?? '' );
            if ( empty( $serial ) ) continue;

            // Check duplicate
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t_serials WHERE serial=%s", $serial ) );
            if ( $exists ) {
                $skipped++;
                continue;
            }

            // Match distributor by erp_customer_id
            $contact_id   = intval( $item['contact_id'] ?? 0 );
            $distributor_id = null;
            if ( $contact_id ) {
                $dist_row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM $t_dist WHERE erp_customer_id=%d LIMIT 1",
                    $contact_id
                ) );
                if ( $dist_row ) {
                    $distributor_id = intval( $dist_row->id );
                } else {
                    $warnings[] = "contact_id {$contact_id} ({$item['dealer_name']}) has no matching distributor — serial {$serial} imported without distributor link.";
                }
            }

            // Match product by erp_product_id
            $erp_product_id = intval( $item['product_id'] ?? 0 );
            $wp_product_id  = null;
            if ( $erp_product_id ) {
                $prod_row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM $t_prods WHERE erp_product_id=%d LIMIT 1",
                    $erp_product_id
                ) );
                if ( $prod_row ) $wp_product_id = intval( $prod_row->id );
            }

            $sale_date = ! empty( $item['sale_date'] ) ? date( 'Y-m-d', strtotime( $item['sale_date'] ) ) : null;

            $inserted = $wpdb->insert( $t_serials, [
                'serial'         => $serial,
                'product_sku'    => sanitize_text_field( $item['product_sku'] ?? '' ),
                'product_id'     => $wp_product_id,
                'distributor_id' => $distributor_id,
                'shipped_date'   => $sale_date,
                'source'         => 'erp_sync',
            ] );

            if ( $inserted ) {
                $new_count++;
                // Instant reconciliation: let the warranty brain auto-approve any
                // registration that was waiting for this serial. Skipped during a
                // bulk backfill to avoid a burst of synchronous notifications in
                // one request — the backlog is cleared via the "Reconcile pending
                // registrations" button (batched) or the daily safety sweep.
                if ( ! $is_backfill ) {
                    do_action( 'slb_serial_synced', $serial );
                }
            }
        }

        // Decide whether to fetch the next page. Prefer the endpoint's explicit
        // has_more flag; fall back to row-count for older ERP builds.
        $rows_in_page = isset( $body['rows'] ) ? (int) $body['rows'] : count( $serials_data );
        $has_more     = isset( $body['has_more'] ) ? (bool) $body['has_more'] : ( $rows_in_page >= $per_page );

        if ( ! $has_more ) break;
        $offset += $per_page;
    }

    // Advance the live cursor ONLY for normal runs. A backfill leaves it alone
    // so the hourly sync keeps working from wherever it already was.
    $opts_updated = slb_sync_get_settings();
    if ( $is_backfill ) {
        $opts_updated['last_backfill_ts'] = date( 'Y-m-d H:i:s' );
    } else {
        $opts_updated['last_sync_date'] = date( 'Y-m-d' );
        $opts_updated['last_sync_ts']   = date( 'Y-m-d H:i:s' );
    }
    update_option( 'slb_sync_settings', $opts_updated );

    $message = $warnings
        ? implode( ' | ', array_slice( $warnings, 0, 5 ) )
        : ( $is_backfill ? 'Backfill from ' . $since : '' );
    slb_sync_log( $new_count, $skipped, $total, $since, 'ok', $message );

    return [
        'new'     => $new_count,
        'skipped' => $skipped,
        'total'   => $total,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Log helper
// ─────────────────────────────────────────────────────────────────────────────

function slb_sync_log( int $new, int $skipped, int $total, string $since, string $status, string $message = '' ) {
    global $wpdb;
    $t_log = $wpdb->prefix . 'slb_sync_log';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$t_log'" ) !== $t_log ) return;
    $wpdb->insert( $t_log, [
        'serials_new'     => $new,
        'serials_skipped' => $skipped,
        'serials_total'   => $total,
        'erp_since'       => $since,
        'status'          => $status,
        'message'         => mb_substr( $message, 0, 1000 ),
    ] );
}
