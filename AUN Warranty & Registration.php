<?php
/**
 * Plugin Name: AUN Warranty Registration
 * Description: Manage distributors, products, and CF7 warranty registrations with auto-approval, SMS and email notifications.
 * Version:     2.9.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if(!defined('ABSPATH')) exit;

/* --- Activation: create tables & indexes --- */
register_activation_hook(__FILE__, 'slb_create_tables');
function slb_create_tables(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $t_serials = $wpdb->prefix . 'slb_serials';
    $t_regs    = $wpdb->prefix . 'slb_registrations';
    $t_dist    = $wpdb->prefix . 'slb_distributors';
    $t_prods   = $wpdb->prefix . 'slb_products';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE IF NOT EXISTS $t_dist (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        address varchar(255) DEFAULT NULL,
        code varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name_unique (name)
    ) $charset_collate;";

    $sql_prod = "CREATE TABLE IF NOT EXISTS $t_prods (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name_unique (name)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE IF NOT EXISTS $t_serials (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        serial varchar(191) NOT NULL,
        product_sku varchar(100) DEFAULT NULL,
        product_id bigint(20) DEFAULT NULL,
        distributor_id bigint(20) DEFAULT NULL,
        shipped_date date DEFAULT NULL,
        source varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        registered tinyint(1) DEFAULT 0,
        registration_id bigint(20) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY serial_idx (serial),
        KEY distributor_idx (distributor_id)
    ) $charset_collate;";

    $sql3 = "CREATE TABLE IF NOT EXISTS $t_regs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        serial varchar(191) NOT NULL,
        distributor_id bigint(20) DEFAULT NULL,
        customer_name varchar(191) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        email varchar(191) DEFAULT NULL,
        product_model varchar(191) DEFAULT NULL, 
        invoice_no varchar(100) DEFAULT NULL,
        purchase_date date DEFAULT NULL,
        dealer_name varchar(191) DEFAULT NULL,
        invoice_file varchar(255) DEFAULT NULL,
        product_photo varchar(255) DEFAULT NULL,
        status varchar(30) DEFAULT 'pending',
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY serial_unique (serial),
        KEY phone_idx (phone)
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql_prod);
    dbDelta($sql2);
    dbDelta($sql3);

    // Ensure unique index on serials in regs table
    $table = $wpdb->prefix . 'slb_registrations';
    $index_name = 'serial_unique';
    $exists = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM $table WHERE Key_name=%s", $index_name));
    if ( empty($exists) ) {
        $wpdb->query( "ALTER TABLE $table ADD UNIQUE KEY {$index_name} (serial)" );
    }

    $defaults = [
        'cf7_form_id'=>0,
        'field_serial'=>'serial-number',
        'field_name'=>'full-name',
        'field_phone'=>'phone',
        'field_email'=>'email',
        'field_model'=>'model',
        'field_invoice'=>'invoice-number',
        'field_date'=>'purchase-date',
        'field_dealer'=>'dealer-name',
        'alpha_api_url'=>'https://api.sms.net.bd/sendsms',
        'alpha_api_key'=>'',
        'alpha_sender'=>'SmartLiving',
        'auto_approve_rule'=>'match_serial_and_distributor',
        'sms_templates'=>[
            'received'=>"SmartLiving: We received your warranty registration for serial {serial}. We will verify and confirm.",
            'approved'=>"SmartLiving: Registration approved for serial {serial}. Warranty starts {start} and ends {end}.",
            'rejected'=>"SmartLiving: Registration for serial {serial} could not be verified. Contact support.",
            'duplicate'=>"SmartLiving: Serial {serial} is already registered under invoice {invoice}.",
            'not_found'=>"SmartLiving: We could not find serial {serial} in distributor records. Your registration is under review.",
            'mismatch'=>"SmartLiving: Serial {serial} exists but the details do not match. Your registration is under review.",
            'model_mismatch'=>"SmartLiving: Serial {serial} is registered to a different model. Please register again and select the correct projector model.",
            'shop_mismatch'=>"SmartLiving: Serial {serial} was sold by a different shop. Please register again and select the correct shop."
        ],
        'email_templates'=>[
            'received'=>"Hello {name},\n\nWe received your warranty registration for serial {serial}. We will verify and respond soon.\n\nInvoice: {invoice}\nRegards,\nSmartLiving",
            'approved'=>"Hello {name},\n\nYour warranty registration for serial {serial} has been approved.\nWarranty period: {start} to {end}.\nInvoice: {invoice}\nRegards,\nSmartLiving",
            'rejected'=>"Hello {name},\n\nWe couldn't verify your registration for serial {serial}. Please contact support.\nRegards,\nSmartLiving",
            'duplicate'=>"Hello {name},\n\nSerial {serial} is already registered under invoice {invoice}. If you think this is a mistake contact support.\nRegards,\nSmartLiving",
            'not_found'=>"Hello {name},\n\nWe could not find serial {serial} in distributor records. Your registration is under review.\nRegards,\nSmartLiving",
            'mismatch'=>"Hello {name},\n\nSerial {serial} exists but the details do not match our records. Your registration is under review.\nRegards,\nSmartLiving",
            'model_mismatch'=>"Hello {name},\n\nSerial {serial} is registered to a different model. Please register again and select the correct projector model.\nRegards,\nSmartLiving",
            'shop_mismatch'=>"Hello {name},\n\nSerial {serial} was sold by a different shop. Please register again and select the correct shop.\nRegards,\nSmartLiving"
        ],
        'rest_secret_key'=> wp_generate_password(24, false, false)
    ];
    if(!get_option('slb_warranty_opts')) update_option('slb_warranty_opts',$defaults);
}

/* --- Admin menu & assets --- */
add_action('admin_menu', function(){
    add_menu_page('SLB Warranty','SLB Warranty','manage_options','slb-warranty','slb_admin_dashboard','dashicons-shield',56);
    add_submenu_page('slb-warranty','Dashboard','Dashboard','manage_options','slb-warranty','slb_admin_dashboard');
    add_submenu_page('slb-warranty','Registrations','Registrations','manage_options','slb-warranty-registrations','slb_admin_registrations');
    add_submenu_page('slb-warranty','Products','Products','manage_options','slb-warranty-products','slb_admin_products');
    add_submenu_page('slb-warranty','Distributors','Distributors','manage_options','slb-warranty-distributors','slb_admin_distributors');
    add_submenu_page('slb-warranty','Serials','Serials','manage_options','slb-warranty-serials','slb_admin_serials');
    add_submenu_page('slb-warranty','Settings','Settings','manage_options','slb-warranty-settings','slb_admin_settings');
});

add_action('admin_enqueue_scripts', function($hook){
    if(strpos($hook,'slb-warranty')===false) return;
    // Modern admin styling — injected inline so the plugin is self-contained
    // (no dependency on external admin.css/admin.js files that may be missing).
    wp_register_style('slb-admin-modern', false);
    wp_enqueue_style('slb-admin-modern');
    wp_add_inline_style('slb-admin-modern', slb_admin_css());
});

function slb_admin_css(): string {
    return <<<'CSS'
    .slb-wrap{max-width:1280px;margin:20px 20px 40px 0}
    .slb-wrap h1.slb-title{font-size:23px;font-weight:600;display:flex;align-items:center;gap:10px;margin-bottom:4px}
    .slb-wrap h1.slb-title .dashicons{color:#2271b1;font-size:28px;width:28px;height:28px}
    .slb-subtitle{color:#646970;margin:0 0 22px;font-size:14px}

    /* Cards */
    .slb-card{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:22px 24px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .slb-card h2{margin-top:0;font-size:16px;font-weight:600;color:#1d2327;display:flex;align-items:center;gap:8px}
    .slb-card-accent{border-left:4px solid #2271b1}

    /* Stat tiles */
    .slb-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
    .slb-stat{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:20px 22px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:transform .15s,box-shadow .15s}
    .slb-stat:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
    .slb-stat .num{font-size:30px;font-weight:700;line-height:1.1;color:#1d2327}
    .slb-stat .lbl{font-size:13px;color:#646970;margin-top:6px;text-transform:uppercase;letter-spacing:.4px;font-weight:600}
    .slb-stat .dashicons{font-size:22px;width:22px;height:22px;margin-bottom:6px}
    .slb-stat.blue .dashicons{color:#2271b1} .slb-stat.green .dashicons{color:#16a34a}
    .slb-stat.amber .dashicons{color:#d97706} .slb-stat.red .dashicons{color:#dc2626}
    .slb-stat.purple .dashicons{color:#7c3aed}

    /* Modern table */
    .slb-wrap .widefat{border:1px solid #e2e4e7;border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.04)}
    .slb-wrap .widefat thead th{background:#f6f7f7;font-weight:600;color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.4px;padding:12px 14px}
    .slb-wrap .widefat td{padding:12px 14px;vertical-align:middle}
    .slb-wrap .widefat tbody tr:hover{background:#f8fafc}

    /* Badges */
    .slb-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;line-height:1.5}
    .slb-badge-green{background:#dcfce7;color:#166534}
    .slb-badge-amber{background:#fef3c7;color:#92400e}
    .slb-badge-red{background:#fee2e2;color:#991b1b}
    .slb-badge-indigo{background:#e0e7ff;color:#3730a3}
    .slb-badge-grey{background:#f3f4f6;color:#6b7280}
    .slb-badge-blue{background:#dbeafe;color:#1e40af}

    /* Buttons polish */
    .slb-wrap .button{border-radius:6px}

    /* Quick links grid on dashboard */
    .slb-quick{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
    .slb-quick a{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:16px 18px;text-decoration:none;color:#1d2327;font-weight:600;transition:border-color .15s,box-shadow .15s}
    .slb-quick a:hover{border-color:#2271b1;box-shadow:0 4px 12px rgba(34,113,177,.12)}
    .slb-quick a .dashicons{font-size:24px;width:24px;height:24px;color:#2271b1}

    /* Search bar row */
    .slb-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
    .slb-toolbar input[type=text],.slb-toolbar input[type=search],.slb-toolbar select{height:36px;border-radius:6px}

    /* Registration action buttons — compact grid so they sit side by side */
    .slb-reg-table td{vertical-align:middle}
    .slb-action-group{display:flex;flex-wrap:wrap;gap:5px}
    .slb-action-group .button{margin:0}
    .slb-btn-reject{color:#b91c1c!important;border-color:#f3c0c0!important}
    .slb-btn-reject:hover{background:#fef2f2!important;border-color:#dc2626!important}
    .slb-btn-delete{color:#6b7280!important}
    .slb-btn-delete:hover{background:#f9fafb!important;color:#dc2626!important}
    CSS;
}

/* -----------------------------------------------------------------------
   Dashboard landing page
   ----------------------------------------------------------------------- */
function slb_admin_dashboard(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $t_serials = $wpdb->prefix.'slb_serials';
    $t_regs    = $wpdb->prefix.'slb_registrations';
    $t_dist    = $wpdb->prefix.'slb_distributors';
    $t_prods   = $wpdb->prefix.'slb_products';

    $total_serials   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t_serials");
    $registered      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t_serials WHERE registered=1");
    $total_regs      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t_regs");
    $approved        = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_regs WHERE status=%s",'approved'));
    $pending         = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_regs WHERE status IN (%s,%s)",'pending','not_found'));
    $total_dist      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t_dist");
    $total_prods     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t_prods");

    $recent = $wpdb->get_results("SELECT r.*, d.name as distributor_name FROM $t_regs r LEFT JOIN $t_dist d ON r.distributor_id=d.id ORDER BY r.created_at DESC LIMIT 8");

    $url = fn($p) => admin_url('admin.php?page='.$p);
    ?>
    <div class="wrap slb-wrap">
        <h1 class="slb-title"><span class="dashicons dashicons-shield"></span> Warranty Dashboard</h1>
        <p class="slb-subtitle">Overview of serial inventory, registrations, and partner data.</p>

        <div class="slb-stats">
            <div class="slb-stat blue"><span class="dashicons dashicons-tag"></span><div class="num"><?php echo number_format($total_serials); ?></div><div class="lbl">Total Serials</div></div>
            <div class="slb-stat green"><span class="dashicons dashicons-yes-alt"></span><div class="num"><?php echo number_format($approved); ?></div><div class="lbl">Approved</div></div>
            <div class="slb-stat amber"><span class="dashicons dashicons-clock"></span><div class="num"><?php echo number_format($pending); ?></div><div class="lbl">Pending Review</div></div>
            <div class="slb-stat purple"><span class="dashicons dashicons-businessperson"></span><div class="num"><?php echo number_format($total_dist); ?></div><div class="lbl">Distributors</div></div>
            <div class="slb-stat red"><span class="dashicons dashicons-archive"></span><div class="num"><?php echo number_format($total_prods); ?></div><div class="lbl">Products</div></div>
        </div>

        <div class="slb-card">
            <h2><span class="dashicons dashicons-admin-links"></span> Quick Actions</h2>
            <div class="slb-quick">
                <a href="<?php echo esc_url($url('slb-warranty-registrations')); ?>"><span class="dashicons dashicons-list-view"></span> Review Registrations</a>
                <a href="<?php echo esc_url($url('slb-warranty-distributors')); ?>"><span class="dashicons dashicons-businessperson"></span> Manage Distributors</a>
                <a href="<?php echo esc_url($url('slb-warranty-products')); ?>"><span class="dashicons dashicons-archive"></span> Manage Products</a>
                <a href="<?php echo esc_url($url('slb-warranty-serials')); ?>"><span class="dashicons dashicons-tag"></span> Browse Serials</a>
                <a href="<?php echo esc_url($url('slb-warranty-settings')); ?>"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
            </div>
        </div>

        <div class="slb-card">
            <h2><span class="dashicons dashicons-clock"></span> Recent Registrations</h2>
            <?php if(empty($recent)): ?>
                <p style="color:#888">No registrations yet.</p>
            <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Serial</th><th>Customer</th><th>Distributor</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php
                $badge = ['approved'=>'slb-badge-green','pending'=>'slb-badge-amber','rejected'=>'slb-badge-red','duplicate'=>'slb-badge-indigo','not_found'=>'slb-badge-grey','mismatch'=>'slb-badge-amber'];
                foreach($recent as $r):
                    $cls = $badge[$r->status] ?? 'slb-badge-grey';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->serial); ?></strong></td>
                        <td><?php echo esc_html($r->customer_name); ?></td>
                        <td><?php echo esc_html($r->distributor_name ?: $r->dealer_name); ?></td>
                        <td><span class="slb-badge <?php echo $cls; ?>"><?php echo esc_html(ucfirst(str_replace('_',' ',$r->status))); ?></span></td>
                        <td style="color:#646970"><?php echo esc_html(date('M j, Y', strtotime($r->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-bottom:0"><a href="<?php echo esc_url($url('slb-warranty-registrations')); ?>" class="button">View all registrations →</a></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


/* -----------------------------------------------------------------------
   Settings page
   ----------------------------------------------------------------------- */
function slb_admin_settings(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $opts = get_option('slb_warranty_opts',[]);
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['slb_settings_nonce']) && wp_verify_nonce($_POST['slb_settings_nonce'],'slb_settings_save')){
        $opts['cf7_form_id'] = intval($_POST['cf7_form_id'] ?? 0);
        $opts['field_serial'] = sanitize_text_field($_POST['field_serial'] ?? 'serial-number');
        $opts['field_name'] = sanitize_text_field($_POST['field_name'] ?? 'full-name');
        $opts['field_phone'] = sanitize_text_field($_POST['field_phone'] ?? 'phone');
        $opts['field_email'] = sanitize_text_field($_POST['field_email'] ?? 'email');
        $opts['field_model'] = sanitize_text_field($_POST['field_model'] ?? 'model');
        $opts['field_invoice'] = sanitize_text_field($_POST['field_invoice'] ?? 'invoice-number');
        $opts['field_date'] = sanitize_text_field($_POST['field_date'] ?? 'purchase-date');
        $opts['field_dealer'] = sanitize_text_field($_POST['field_dealer'] ?? 'dealer-name');

        $opts['alpha_api_url'] = esc_url_raw($_POST['alpha_api_url'] ?? '');
        // Only save the API key if a constant isn't already defined — the constant takes precedence
        if ( ! defined('SLB_SMS_API_KEY') ) {
            $opts['alpha_api_key'] = sanitize_text_field($_POST['alpha_api_key'] ?? '');
        }
        $opts['alpha_sender'] = sanitize_text_field($_POST['alpha_sender'] ?? 'SmartLiving');
        $opts['auto_approve_rule'] = in_array($_POST['auto_approve_rule'] ?? '', ['off','match_serial','match_serial_and_distributor']) ? $_POST['auto_approve_rule'] : 'match_serial_and_distributor';

        $templates = $opts['sms_templates'] ?? [];
        foreach(['received','approved','rejected','duplicate','not_found','mismatch','model_mismatch','shop_mismatch'] as $k){
            $templates[$k] = sanitize_textarea_field($_POST['sms_'.$k] ?? $templates[$k] ?? '');
        }
        $opts['sms_templates'] = $templates;

        $email_templates = $opts['email_templates'] ?? [];
        foreach(['received','approved','rejected','duplicate','not_found','mismatch','model_mismatch','shop_mismatch'] as $k){
            // Fall back to what is already stored when a field is ABSENT from the
            // POST - exactly what the SMS loop above does. Without this, any save
            // that did not carry these fields overwrote the template with an empty
            // string, and an empty template makes slb_send_templated_email() return
            // false without a word: customers simply stop receiving email, with
            // nothing logged anywhere. A field that is present but deliberately
            // cleared is still honoured.
            $email_templates[$k.'_subject'] = isset($_POST['email_'.$k.'_subject'])
                ? sanitize_text_field( $_POST['email_'.$k.'_subject'] )
                : ( $email_templates[$k.'_subject'] ?? '' );
            $email_templates[$k] = isset($_POST['email_'.$k])
                ? wp_kses_post( $_POST['email_'.$k] )
                : ( $email_templates[$k] ?? '' );
        }
        $opts['email_templates'] = $email_templates;

        update_option('slb_warranty_opts',$opts);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    // ── Backlog tool: compress already-uploaded invoice files on demand ──
    $bulk_result = null;
    if ( isset($_POST['slb_bulk_compress_nonce']) && wp_verify_nonce($_POST['slb_bulk_compress_nonce'], 'slb_bulk_compress') ) {
        $bulk_result = slb_run_bulk_compression( 15 );
    }

    $templates = $opts['sms_templates'] ?? [];
    $email_templates = $opts['email_templates'] ?? [];

    $cf7_forms = [];
    if ( class_exists('WPCF7_ContactForm') ) {
        $posts = get_posts([
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        foreach ($posts as $p) $cf7_forms[] = $p;
    }

    ?>
    <div class="wrap"><h1>SLB Warranty - Settings</h1>
    <form method="post">
      <?php wp_nonce_field('slb_settings_save','slb_settings_nonce'); ?>
      <table class="form-table">
    <?php
    echo '<tr><th>Contact Form 7 Form</th><td>';
    if (!empty($cf7_forms)) {
        echo '<select name="cf7_form_id">';
        echo '<option value="">-- Select Contact Form 7 --</option>';
        foreach ($cf7_forms as $f) {
            $selected = (intval($opts['cf7_form_id'] ?? 0) === intval($f->ID)) ? ' selected' : '';
            echo '<option value="' . intval($f->ID) . '"' . $selected . '>' . esc_html($f->ID . ' — ' . $f->post_title) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input name="cf7_form_id" value="' . esc_attr($opts['cf7_form_id'] ?? '') . '" />';
        echo '<p class="description">No Contact Form 7 forms found. Install/activate CF7 or enter numeric form ID (e.g. 117).</p>';
    }
    echo '</td></tr>';
    ?>

        <tr><th>CF7 field - serial</th><td><input name="field_serial" value="<?php echo esc_attr($opts['field_serial'] ?? 'serial-number'); ?>" /></td></tr>
        <tr><th>CF7 field - name</th><td><input name="field_name" value="<?php echo esc_attr($opts['field_name'] ?? 'full-name'); ?>" /></td></tr>
        <tr><th>CF7 field - phone</th><td><input name="field_phone" value="<?php echo esc_attr($opts['field_phone'] ?? 'phone'); ?>" /></td></tr>
        <tr><th>CF7 field - email</th><td><input name="field_email" value="<?php echo esc_attr($opts['field_email'] ?? 'email'); ?>" /></td></tr>
        <tr><th>CF7 field - product model</th><td><input name="field_model" value="<?php echo esc_attr($opts['field_model'] ?? 'model'); ?>" /></td></tr>
        <tr><th>CF7 field - invoice no</th><td><input name="field_invoice" value="<?php echo esc_attr($opts['field_invoice'] ?? 'invoice-number'); ?>" /></td></tr>
        <tr><th>CF7 field - purchase date</th><td><input name="field_date" value="<?php echo esc_attr($opts['field_date'] ?? 'purchase-date'); ?>" /></td></tr>
        <tr><th>CF7 field - dealer name</th><td><input name="field_dealer" value="<?php echo esc_attr($opts['field_dealer'] ?? 'dealer-name'); ?>" /></td></tr>

        <tr><th>Alpha SMS API URL</th><td><input name="alpha_api_url" style="width:100%" value="<?php echo esc_attr($opts['alpha_api_url'] ?? ''); ?>" /></td></tr>
        <tr><th>Alpha SMS API Key</th><td>
            <?php if ( defined('SLB_SMS_API_KEY') ) : ?>
                <input disabled value="••••••••••••••••" style="width:100%;color:#888;" />
                <p class="description">Key is set via <code>define('SLB_SMS_API_KEY', '...')</code> in <code>wp-config.php</code> and cannot be overridden here.</p>
            <?php else : ?>
                <input name="alpha_api_key" style="width:100%" value="<?php echo esc_attr($opts['alpha_api_key'] ?? ''); ?>" />
                <p class="description">For better security, define this as a constant in <code>wp-config.php</code>: <code>define('SLB_SMS_API_KEY', 'your-key');</code></p>
            <?php endif; ?>
        </td></tr>
        <tr><th>SMS Sender ID</th><td><input name="alpha_sender" value="<?php echo esc_attr($opts['alpha_sender'] ?? 'SmartLiving'); ?>" /></td></tr>

        <tr><th>Auto-approve rule</th>
          <td>
            <select name="auto_approve_rule">
              <option value="off" <?php selected($opts['auto_approve_rule'] ?? '', 'off'); ?>>Off (always pending)</option>
              <option value="match_serial" <?php selected($opts['auto_approve_rule'] ?? '', 'match_serial'); ?>>Auto-approve when serial exists (ignore distributor)</option>
              <option value="match_serial_and_distributor" <?php selected($opts['auto_approve_rule'] ?? '', 'match_serial_and_distributor'); ?>>Auto-approve when serial exists AND dealer matches distributor</option>
            </select>
          </td>
        </tr>

      </table>

      <h2>SMS Templates (placeholders: {serial}, {start}, {end}, {invoice}, {name}, {phone})</h2>
      <?php foreach(['received','approved','rejected','duplicate','not_found','mismatch','model_mismatch','shop_mismatch'] as $k): ?>
        <p><strong><?php echo ucwords(str_replace('_',' ',$k)); ?> SMS template</strong><br/>
        <textarea name="sms_<?php echo $k; ?>" rows="3" style="width:100%"><?php echo esc_textarea($templates[$k] ?? ''); ?></textarea></p>
      <?php endforeach; ?>

      <h2>Email Templates (subject & body allowed — placeholders: {serial}, {start}, {end}, {invoice}, {name}, {phone}, {year})</h2>
      <?php foreach(['received','approved','rejected','duplicate','not_found','mismatch','model_mismatch','shop_mismatch'] as $k):
         $label = ucwords(str_replace('_',' ',$k));
         $sub = $email_templates[$k . '_subject'] ?? ($k === 'approved' ? 'Warranty approved for {serial}' : $label.' - Warranty update for {serial}');
         $body = $email_templates[$k] ?? '';
      ?>
        <p><strong><?php echo $label; ?> Email subject</strong><br/>
        <input name="email_<?php echo $k; ?>_subject" style="width:100%" value="<?php echo esc_attr($sub); ?>" /></p>
        <p><strong><?php echo $label; ?> Email body</strong><br/>
        <textarea name="email_<?php echo $k; ?>" rows="6" style="width:100%"><?php echo esc_textarea($body); ?></textarea></p>
      <?php endforeach; ?>

      <p><input type="submit" class="button-primary" value="Save Settings" /></p>
    </form>

    <hr style="margin:28px 0" />
    <h2>🗜️ Compress existing invoice images</h2>
    <p class="description" style="max-width:760px">
        Shrinks invoice files uploaded <strong>before</strong> auto-compression was active (or that the
        background job missed). It runs in small batches so it can't time out — just keep clicking until
        it says <em>finished</em>. Images are resized/re-encoded; PDFs compress if Ghostscript is available.
        Files that are already small are skipped automatically, so it's always safe to run.
    </p>
    <?php
    $t_regs_c    = $wpdb->prefix . 'slb_registrations';
    $total_files = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_regs_c WHERE invoice_file <> ''" );
    $cursor      = max( 0, (int) get_option( 'slb_compress_cursor', 0 ) );

    if ( $bulk_result ) {
        $saved_h = size_format( $bulk_result['saved'] ?: 0, 2 );
        echo '<div class="notice notice-success" style="max-width:760px"><p>';
        echo 'Batch done: processed <strong>' . intval($bulk_result['done']) . '</strong> file(s), '
           . '<strong>' . intval($bulk_result['shrunk']) . '</strong> shrunk, saving <strong>' . esc_html($saved_h) . '</strong>. ';
        if ( $bulk_result['finished'] ) {
            echo '✅ <strong>All ' . intval($bulk_result['grand_total']) . ' files processed.</strong>';
        } else {
            echo 'Progress: <strong>' . intval($bulk_result['processed']) . ' / ' . intval($bulk_result['grand_total']) . '</strong> — click again to continue.';
        }
        echo '</p></div>';
        $cursor = $bulk_result['finished'] ? 0 : $bulk_result['processed'];
    }
    $remaining = max( 0, $total_files - $cursor );
    ?>
    <form method="post" style="margin-bottom:24px">
        <?php wp_nonce_field('slb_bulk_compress','slb_bulk_compress_nonce'); ?>
        <p>
            <button type="submit" class="button button-primary">Compress next batch (15 files)</button>
            <span style="margin-left:10px;color:#646970">
                <?php echo intval($total_files); ?> invoice file(s) on record<?php
                    if ( $cursor > 0 && $remaining > 0 ) echo ', ~' . intval($remaining) . ' remaining this pass';
                ?>.
            </span>
        </p>
    </form>

    <h2>REST Secret Key</h2>
    <p>Secret key for ERP import (header <code>x-slb-key</code>): <code><?php echo esc_html($opts['rest_secret_key'] ?? ''); ?></code></p>

    </div>
    <?php
}

/* -----------------------------------------------------------------------
   Products admin
   ----------------------------------------------------------------------- */
function slb_admin_products(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $t_prods = $wpdb->prefix.'slb_products';

    // AUTO-FIX: Check if table exists
    if($wpdb->get_var("SHOW TABLES LIKE '$t_prods'") != $t_prods) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $t_prods (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name_unique (name)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    $has_erp_col = !empty($wpdb->get_results("SHOW COLUMNS FROM $t_prods LIKE 'erp_product_id'"));
    $base_url    = admin_url('admin.php?page=slb-warranty-products');

    // ── Handle Add ──
    if(isset($_POST['add_prod_nonce']) && wp_verify_nonce($_POST['add_prod_nonce'],'slb_add_prod')){
        $name = sanitize_text_field($_POST['prod_name'] ?? '');
        if($name){
            $wpdb->insert($t_prods, ['name'=>$name]);
            $new_id  = $wpdb->insert_id;
            $erp_pid = absint($_POST['prod_erp_id'] ?? 0);
            if($new_id && $erp_pid && $has_erp_col){
                $wpdb->update($t_prods, ['erp_product_id'=>$erp_pid], ['id'=>$new_id], ['%d'], ['%d']);
            }
            wp_safe_redirect(add_query_arg('slb_msg','added',$base_url)); exit;
        }
    }

    // ── Handle Delete ──
    if(isset($_GET['action']) && $_GET['action']=='delete_prod' && isset($_GET['id']) && check_admin_referer('slb_delete_prod','slb_delete_nonce')){
        $wpdb->delete($t_prods, ['id'=>intval($_GET['id'])]);
        wp_safe_redirect(add_query_arg('slb_msg','deleted',$base_url)); exit;
    }

    // ── Handle Edit ──
    if(isset($_POST['edit_prod_nonce']) && wp_verify_nonce($_POST['edit_prod_nonce'],'slb_edit_prod')){
        $id = intval($_POST['prod_edit_id']);
        $name = sanitize_text_field($_POST['prod_edit_name']);
        $wpdb->update($t_prods, ['name'=>$name], ['id'=>$id], ['%s'], ['%d']);
        if($has_erp_col){
            $erp_pid = absint($_POST['prod_erp_id'] ?? 0);
            $wpdb->update($t_prods, ['erp_product_id'=>($erp_pid ?: null)], ['id'=>$id], [$erp_pid ? '%d' : null], ['%d']);
        }
        wp_safe_redirect(add_query_arg('slb_msg','updated',$base_url)); exit;
    }

    // ── Flash message ──
    $msg = sanitize_text_field($_GET['slb_msg'] ?? '');
    $msg_map = ['added'=>'Product added.','updated'=>'Product updated.','deleted'=>'Product deleted.'];
    if(isset($msg_map[$msg])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($msg_map[$msg]).'</p></div>';

    // ── Editing? load row ──
    $editing = null;
    if(!empty($_GET['edit_id'])){
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_prods WHERE id=%d", intval($_GET['edit_id'])));
    }

    $prods = $wpdb->get_results("SELECT * FROM $t_prods ORDER BY name ASC");
    ?>
    <div class="wrap slb-wrap"><h1 class="slb-title"><span class="dashicons dashicons-archive"></span> Products</h1><p class="slb-subtitle">Product models available for warranty registration.</p>

    <?php if($editing): /* ── EDIT FORM AT TOP ── */
        $current_erp_id = ($has_erp_col && isset($editing->erp_product_id)) ? intval($editing->erp_product_id) : 0;
        ?>
        <div class="card" style="max-width:600px;padding:16px 20px;margin-bottom:20px;border-left:4px solid #2271b1;">
            <h2 style="margin-top:0">Edit Product: <?php echo esc_html($editing->name); ?></h2>
            <form method="post">
              <?php wp_nonce_field('slb_edit_prod','edit_prod_nonce'); ?>
              <input type="hidden" name="prod_edit_id" value="<?php echo intval($editing->id); ?>" />
              <table class="form-table">
                <tr><th>Product Model Name</th><td><input name="prod_edit_name" required value="<?php echo esc_attr($editing->name); ?>" style="width:300px;" /></td></tr>
                <?php if($has_erp_col): ?>
                <tr><th>ERP Product ID</th><td>
                    <input name="prod_erp_id" type="number" min="0" value="<?php echo esc_attr($current_erp_id ?: ''); ?>" placeholder="e.g. 7" style="width:120px;" />
                    <p class="description">From the ERP URL when editing this product, e.g. <code>/products/7</code></p>
                </td></tr>
                <?php endif; ?>
              </table>
              <p>
                <input type="submit" class="button button-primary" value="Save Changes" />
                <a href="<?php echo esc_url($base_url); ?>" class="button">Cancel</a>
              </p>
            </form>
        </div>
    <?php else: /* ── ADD FORM ── */ ?>
        <h2>Add Product Model</h2>
        <form method="post">
          <?php wp_nonce_field('slb_add_prod','add_prod_nonce'); ?>
          <table class="form-table">
            <tr><th>Product Model Name</th><td><input name="prod_name" required placeholder="e.g. A32" /></td></tr>
            <?php if($has_erp_col): ?>
            <tr><th>ERP Product ID</th><td>
                <input name="prod_erp_id" type="number" min="0" placeholder="e.g. 7" style="width:120px;" />
                <p class="description">Product ID from the ERP (visible in the URL when editing the product, e.g. <code>/products/7</code>). Required for automatic serial import.</p>
            </td></tr>
            <?php endif; ?>
          </table>
          <p><input type="submit" class="button button-primary" value="Add Product" /></p>
        </form>
    <?php endif; ?>

    <h2>Product Models List</h2>
    <table class="widefat fixed striped">
        <thead><tr>
            <th style="width:60px">ID</th>
            <th>Name</th>
            <?php if($has_erp_col): ?><th style="width:140px">ERP Product ID</th><?php endif; ?>
            <th style="width:160px">Actions</th>
        </tr></thead>
        <tbody>
        <?php if(empty($prods)): ?>
            <tr><td colspan="<?php echo $has_erp_col ? 4 : 3; ?>" style="text-align:center;color:#888;padding:20px">No products added yet.</td></tr>
        <?php else: foreach($prods as $p):
            $edit_link = add_query_arg(['page'=>'slb-warranty-products','edit_id'=>$p->id], admin_url('admin.php'));
            $delete_link = wp_nonce_url(add_query_arg(['page'=>'slb-warranty-products','action'=>'delete_prod','id'=>$p->id], admin_url('admin.php')), 'slb_delete_prod', 'slb_delete_nonce');
            $erp_id = ($has_erp_col && !empty($p->erp_product_id)) ? intval($p->erp_product_id) : 0;
            ?>
            <tr>
                <td><?php echo intval($p->id); ?></td>
                <td><strong><?php echo esc_html($p->name); ?></strong></td>
                <?php if($has_erp_col): ?>
                <td>
                    <?php if($erp_id): ?>
                        <span style="display:inline-block;background:#dcfce7;color:#166534;padding:2px 10px;border-radius:12px;font-weight:700;font-size:13px"><?php echo $erp_id; ?></span>
                    <?php else: ?>
                        <span style="display:inline-block;background:#f3f4f6;color:#9ca3af;padding:2px 10px;border-radius:12px;font-size:12px">Not set</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td>
                    <a class="button" href="<?php echo esc_url($edit_link); ?>">Edit</a>
                    <a class="button button-secondary" href="<?php echo esc_url($delete_link); ?>" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    </div>
    <?php
}

/* -----------------------------------------------------------------------
   Distributors admin
   ----------------------------------------------------------------------- */
function slb_admin_distributors(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $t_dist = $wpdb->prefix.'slb_distributors';
    $t_serials = $wpdb->prefix.'slb_serials';

    // Auto-repair: add address column if missing — runs once per DB version, not on every load
    if ( get_option('slb_db_version', '0') < '1.4' ) {
        $has_col = $wpdb->get_results("SHOW COLUMNS FROM $t_dist LIKE 'address'");
        if(empty($has_col)){
            $wpdb->query("ALTER TABLE $t_dist ADD COLUMN address varchar(255) DEFAULT NULL AFTER name");
        }
        update_option('slb_db_version', '1.4');
    }

    $has_erp_col = !empty($wpdb->get_results("SHOW COLUMNS FROM $t_dist LIKE 'erp_customer_id'"));
    $base_url    = admin_url('admin.php?page=slb-warranty-distributors');

    if(isset($_POST['add_dist_nonce']) && wp_verify_nonce($_POST['add_dist_nonce'],'slb_add_dist')){
        $name    = sanitize_text_field($_POST['dist_name']    ?? '');
        $address = sanitize_text_field($_POST['dist_address'] ?? '');
        $code    = sanitize_text_field($_POST['dist_code']    ?? '');
        if($name){
            $wpdb->insert($t_dist, ['name'=>$name, 'address'=>$address, 'code'=>$code]);
            $new_id  = $wpdb->insert_id;
            $erp_cid = absint($_POST['dist_erp_id'] ?? 0);
            if($new_id && $erp_cid && $has_erp_col){
                $wpdb->update($t_dist, ['erp_customer_id'=>$erp_cid], ['id'=>$new_id], ['%d'], ['%d']);
            }
            wp_safe_redirect(add_query_arg('slb_msg','added',$base_url)); exit;
        }
    }

    if(isset($_GET['action']) && $_GET['action']=='delete_dist' && isset($_GET['id']) && check_admin_referer('slb_delete_dist','slb_delete_nonce')){
        $id = intval($_GET['id']);
        $wpdb->update($t_serials, ['distributor_id'=>null], ['distributor_id'=>$id]);
        $wpdb->delete($t_dist, ['id'=>$id]);
        wp_safe_redirect(add_query_arg('slb_msg','deleted',$base_url)); exit;
    }

    if(isset($_POST['edit_dist_nonce']) && wp_verify_nonce($_POST['edit_dist_nonce'],'slb_edit_dist')){
        $id      = intval($_POST['dist_edit_id']);
        $name    = sanitize_text_field($_POST['dist_edit_name']);
        $address = sanitize_text_field($_POST['dist_edit_address']);
        $wpdb->update($t_dist, ['name'=>$name, 'address'=>$address], ['id'=>$id], ['%s','%s'], ['%d']);
        if($has_erp_col){
            $erp_cid = absint($_POST['dist_erp_id'] ?? 0);
            $wpdb->update($t_dist, ['erp_customer_id'=>($erp_cid ?: null)], ['id'=>$id], [$erp_cid ? '%d' : null], ['%d']);
        }
        wp_safe_redirect(add_query_arg('slb_msg','updated',$base_url)); exit;
    }

    // Flash message
    $msg = sanitize_text_field($_GET['slb_msg'] ?? '');
    $msg_map = ['added'=>'Distributor added.','updated'=>'Distributor updated.','deleted'=>'Distributor deleted (serials detached).'];
    if(isset($msg_map[$msg])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($msg_map[$msg]).'</p></div>';

    // Editing? load row
    $editing = null;
    if(!empty($_GET['edit_id'])){
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_dist WHERE id=%d", intval($_GET['edit_id'])));
    }

    $dists = $wpdb->get_results("SELECT * FROM $t_dist ORDER BY name ASC");
    ?>
    <div class="wrap slb-wrap"><h1 class="slb-title"><span class="dashicons dashicons-businessperson"></span> Distributors</h1><p class="slb-subtitle">Manage your authorized dealers. To add serials, use the Serials page.</p>

    <?php if($editing): /* ── EDIT FORM AT TOP ── */
        $current_erp_cid = ($has_erp_col && isset($editing->erp_customer_id)) ? intval($editing->erp_customer_id) : 0;
        ?>
        <div class="card" style="max-width:600px;padding:16px 20px;margin-bottom:20px;border-left:4px solid #2271b1;">
            <h2 style="margin-top:0">Edit Distributor: <?php echo esc_html($editing->name); ?></h2>
            <form method="post">
              <?php wp_nonce_field('slb_edit_dist','edit_dist_nonce'); ?>
              <input type="hidden" name="dist_edit_id" value="<?php echo intval($editing->id); ?>" />
              <table class="form-table">
                <tr><th>Name</th><td><input name="dist_edit_name" required value="<?php echo esc_attr($editing->name); ?>" style="width:300px;" /></td></tr>
                <tr><th>Address</th><td><input name="dist_edit_address" value="<?php echo esc_attr($editing->address); ?>" style="width:300px;" /></td></tr>
                <?php if($has_erp_col): ?>
                <tr><th>ERP Contact ID</th><td>
                    <input name="dist_erp_id" type="number" min="0" value="<?php echo esc_attr($current_erp_cid ?: ''); ?>" placeholder="e.g. 160" style="width:120px;" />
                    <p class="description">From the ERP URL when viewing this dealer, e.g. <code>/contacts/160</code></p>
                </td></tr>
                <?php endif; ?>
              </table>
              <p>
                <input type="submit" class="button button-primary" value="Save Changes" />
                <a href="<?php echo esc_url($base_url); ?>" class="button">Cancel</a>
              </p>
            </form>
        </div>
    <?php else: /* ── ADD FORM ── */ ?>
        <div class="slb-card slb-card-accent" style="max-width:600px">
        <h2><span class="dashicons dashicons-plus-alt"></span> Add Distributor</h2>
        <form method="post">
          <?php wp_nonce_field('slb_add_dist','add_dist_nonce'); ?>
          <table class="form-table">
            <tr><th>Name</th><td><input name="dist_name" required style="width:300px;" /></td></tr>
            <tr><th>Address / Location</th><td><input name="dist_address" placeholder="e.g. Dhaka, Gulshan" style="width:300px;" /></td></tr>
            <tr><th>Code (optional)</th><td><input name="dist_code" style="width:100px;" /></td></tr>
            <?php if($has_erp_col): ?>
            <tr><th>ERP Contact ID</th><td>
                <input name="dist_erp_id" type="number" min="0" placeholder="e.g. 160" style="width:120px;" />
                <p class="description">The number in the ERP URL when viewing this dealer (e.g. <code>/contacts/160</code>). Required for automatic serial import.</p>
            </td></tr>
            <?php endif; ?>
          </table>
          <p><input type="submit" class="button button-primary" value="Add Distributor" /></p>
        </form>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:24px">Distributors List</h2>
    <table class="widefat fixed striped">
        <thead><tr>
            <th style="width:50px">ID</th>
            <th>Name</th>
            <th>Address</th>
            <?php if($has_erp_col): ?><th style="width:130px">ERP Contact ID</th><?php endif; ?>
            <th style="width:70px">Serials</th>
            <th style="width:220px">Actions</th>
        </tr></thead>
        <tbody>
        <?php if(empty($dists)): ?>
            <tr><td colspan="<?php echo $has_erp_col ? 6 : 5; ?>" style="text-align:center;color:#888;padding:20px">No distributors added yet.</td></tr>
        <?php else: foreach($dists as $d):
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_serials WHERE distributor_id=%d",$d->id));
            $edit_link = add_query_arg(['page'=>'slb-warranty-distributors','edit_id'=>$d->id], admin_url('admin.php'));
            $delete_link = wp_nonce_url(add_query_arg(['page'=>'slb-warranty-distributors','action'=>'delete_dist','id'=>$d->id], admin_url('admin.php')), 'slb_delete_dist', 'slb_delete_nonce');
            $serials_link = add_query_arg(['page'=>'slb-warranty-serials','dist_filter'=>$d->id], admin_url('admin.php'));
            $erp_cid = ($has_erp_col && !empty($d->erp_customer_id)) ? intval($d->erp_customer_id) : 0;
            ?>
            <tr>
                <td><?php echo intval($d->id); ?></td>
                <td><strong><?php echo esc_html($d->name); ?></strong></td>
                <td><?php echo esc_html($d->address); ?></td>
                <?php if($has_erp_col): ?>
                <td>
                    <?php if($erp_cid): ?>
                        <span style="display:inline-block;background:#dcfce7;color:#166534;padding:2px 10px;border-radius:12px;font-weight:700;font-size:13px"><?php echo $erp_cid; ?></span>
                    <?php else: ?>
                        <span style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:12px;font-size:12px">Not set</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td><?php echo intval($count); ?></td>
                <td>
                    <a class="button" href="<?php echo esc_url($edit_link); ?>">Edit</a>
                    <a class="button button-secondary" href="<?php echo esc_url($delete_link); ?>" onclick="return confirm('Delete this distributor? Serial links will be detached')">Delete</a>
                    <a class="button" href="<?php echo esc_url($serials_link); ?>">Serials</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    </div>
    <?php
}

/* -----------------------------------------------------------------------
   DISTRIBUTOR select handlers (UPDATED: Shows Address in dropdown)
   ----------------------------------------------------------------------- */
add_action( 'wpcf7_init', function() {
    if ( function_exists( 'wpcf7_add_form_tag' ) ) {
        wpcf7_add_form_tag( array( 'slb_distributor_select', 'slb_distributor_select*' ), 'slb_cf7_distributor_tag_handler', array( 'name-attr' => true ) );
        wpcf7_add_form_tag( array( 'slb_product_select', 'slb_product_select*' ), 'slb_cf7_product_tag_handler', array( 'name-attr' => true ) );
    }
} );

function slb_cf7_distributor_tag_handler( $tag ) {
    global $wpdb;
    if ( ! class_exists( 'WPCF7_FormTag' ) ) return '';
    $tag = new WPCF7_FormTag( $tag );
    if ( empty( $tag->name ) ) return '';

    $t_dist = $wpdb->prefix . 'slb_distributors';
    $dists = $wpdb->get_results( "SELECT id, name, address FROM $t_dist ORDER BY name ASC" );

    $id = $tag->get_id_option();
    $classes = $tag->get_class_option();
    $placeholder = 'Select shop';
    if ( ! empty( $tag->options ) ) {
        foreach ( $tag->options as $opt ) {
            if ( strpos( $opt, 'placeholder:' ) === 0 ) {
                $placeholder = substr( $opt, 12 );
            }
        }
    }

    $class = wpcf7_form_controls_class( $tag->type, 'wpcf7-select' );
    if ( $tag->is_required() ) $class .= ' wpcf7-validates-as-required';
    if ( $classes ) $class .= ' ' . $classes;

    $atts = array();
    $atts['class'] = $class;
    $atts['id'] = $id;
    $atts['name'] = $tag->name;
    $atts['aria-required'] = $tag->is_required() ? 'true' : 'false';

    $atts_html = '';
    foreach ( $atts as $key => $val ) {
        if ( isset( $val ) && '' !== $val ) {
            $atts_html .= ' ' . $key . '="' . esc_attr( $val ) . '"';
        }
    }

    $options_html = '<option value="">' . esc_html( $placeholder ) . '</option>';
    foreach ( $dists as $d ) {
        // Concatenate Name and Address if address exists
        $label = $d->name;
        if ( ! empty( $d->address ) ) {
            $label .= ' (' . $d->address . ')';
        }
        $options_html .= '<option value="' . esc_attr( $d->name ) . '">' . esc_html( $label ) . '</option>';
    }

    $html = sprintf( '<select %1$s>%2$s</select>', $atts_html, $options_html );
    $validation_error = wpcf7_get_validation_error( $tag->name );
    $class_wrapper = 'wpcf7-form-control-wrap ' . sanitize_html_class( $tag->name );

    return sprintf(
        '<span class="%1$s" data-name="%2$s">%3$s%4$s</span>',
        esc_attr( $class_wrapper ), esc_attr( $tag->name ), $html, $validation_error
    );
}

// Distributor Validation
add_filter( 'wpcf7_validate_slb_distributor_select', 'slb_cf7_validate_distributor_select', 10, 2 );
add_filter( 'wpcf7_validate_slb_distributor_select*', 'slb_cf7_validate_distributor_select', 10, 2 );

function slb_cf7_validate_distributor_select( $result, $tag ) {
    if ( ! ( $tag instanceof WPCF7_FormTag ) ) $tag = new WPCF7_FormTag( $tag );
    if ( $tag->is_required() ) {
        $name = $tag->name;
        $value = isset( $_POST[$name] ) ? trim( $_POST[$name] ) : '';
        if ( '' === $value ) {
            $result->invalidate( $tag, 'Please select a distributor.' );
        }
    }
    return $result;
}

/* -----------------------------------------------------------------------
   PRODUCT select handlers
   ----------------------------------------------------------------------- */
function slb_cf7_product_tag_handler( $tag ) {
    global $wpdb;
    if ( ! class_exists( 'WPCF7_FormTag' ) ) return '';
    $tag = new WPCF7_FormTag( $tag );
    if ( empty( $tag->name ) ) return '';

    $t_prods = $wpdb->prefix . 'slb_products';
    $prods = $wpdb->get_results( "SELECT id, name FROM $t_prods ORDER BY name ASC" );

    $id = $tag->get_id_option();
    $classes = $tag->get_class_option();
    $placeholder = 'Select model';
    if ( ! empty( $tag->options ) ) {
        foreach ( $tag->options as $opt ) {
            if ( strpos( $opt, 'placeholder:' ) === 0 ) {
                $placeholder = substr( $opt, 12 );
            }
        }
    }

    $class = wpcf7_form_controls_class( $tag->type, 'wpcf7-select' );
    if ( $tag->is_required() ) $class .= ' wpcf7-validates-as-required';
    if ( $classes ) $class .= ' ' . $classes;

    $atts = array();
    $atts['class'] = $class;
    $atts['id'] = $id;
    $atts['name'] = $tag->name;
    $atts['aria-required'] = $tag->is_required() ? 'true' : 'false';

    $atts_html = '';
    foreach ( $atts as $key => $val ) {
        if ( isset( $val ) && '' !== $val ) {
            $atts_html .= ' ' . $key . '="' . esc_attr( $val ) . '"';
        }
    }

    $options_html = '<option value="">' . esc_html( $placeholder ) . '</option>';
    foreach ( $prods as $p ) {
        $options_html .= '<option value="' . esc_attr( $p->name ) . '">' . esc_html( $p->name ) . '</option>';
    }

    $html = sprintf( '<select %1$s>%2$s</select>', $atts_html, $options_html );
    $validation_error = wpcf7_get_validation_error( $tag->name );
    $class_wrapper = 'wpcf7-form-control-wrap ' . sanitize_html_class( $tag->name );

    return sprintf(
        '<span class="%1$s" data-name="%2$s">%3$s%4$s</span>',
        esc_attr( $class_wrapper ), esc_attr( $tag->name ), $html, $validation_error
    );
}

// Product Validation
add_filter( 'wpcf7_validate_slb_product_select', 'slb_cf7_validate_product_select', 10, 2 );
add_filter( 'wpcf7_validate_slb_product_select*', 'slb_cf7_validate_product_select', 10, 2 );

function slb_cf7_validate_product_select( $result, $tag ) {
    if ( ! ( $tag instanceof WPCF7_FormTag ) ) $tag = new WPCF7_FormTag( $tag );
    if ( $tag->is_required() ) {
        $name = $tag->name;
        $value = isset( $_POST[$name] ) ? trim( $_POST[$name] ) : '';
        if ( '' === $value ) {
            $result->invalidate( $tag, 'Please select a product model.' );
        }
    }
    return $result;
}

/* -----------------------------------------------------------------------
   Serials admin: prevents deletion if approved registration exists
   ----------------------------------------------------------------------- */
function slb_admin_serials(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $t_serials = $wpdb->prefix.'slb_serials';
    $t_dist = $wpdb->prefix.'slb_distributors';
    $t_regs = $wpdb->prefix.'slb_registrations';

    $base_url = admin_url('admin.php?page=slb-warranty-serials');

    // handle delete serial via GET (admin nonce) - protected against deleting approved serials
    if(isset($_GET['action']) && $_GET['action']=='delete_serial' && isset($_GET['id']) && check_admin_referer('slb_delete_serial','slb_delete_serial_nonce')){
        $id = intval($_GET['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT serial FROM $t_serials WHERE id=%d",$id));
        if($row){
            $approved = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_regs WHERE serial=%s AND status=%s", $row->serial, 'approved'));
            if($approved > 0){
                wp_safe_redirect(add_query_arg('slb_msg','protected',$base_url)); exit;
            } else {
                $wpdb->delete($t_serials, ['id'=>$id]);
                wp_safe_redirect(add_query_arg('slb_msg','deleted',$base_url)); exit;
            }
        }
    }

    // ── CSV upload (moved here from Distributors page) ──
    if(isset($_POST['ser_csv_nonce']) && wp_verify_nonce($_POST['ser_csv_nonce'],'slb_ser_csv') && !empty($_FILES['ser_csv']['tmp_name']) && !empty($_POST['ser_dist_id'])){
        $dist_id = intval($_POST['ser_dist_id']);
        $csv = array_map('str_getcsv', file($_FILES['ser_csv']['tmp_name']));
        $header = array_map('trim',$csv[0]);
        $count=0; $duplicates=[];
        foreach(array_slice($csv,1) as $r){
            if(count($header) !== count($r)) continue;
            $data = array_combine($header,$r);
            $serial = trim($data['serial'] ?? '');
            if(!$serial) continue;
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t_serials WHERE serial=%s", $serial));
            if($exists){ $duplicates[]=$serial; }
            else {
                $wpdb->insert($t_serials, [
                    'serial'=>$serial,
                    'product_sku'=>sanitize_text_field($data['product_sku'] ?? ''),
                    'product_id'=> !empty($data['product_id']) ? intval($data['product_id']) : null,
                    'distributor_id'=>$dist_id,
                    'shipped_date'=> !empty($data['shipped_date']) ? date('Y-m-d', strtotime($data['shipped_date'])) : null,
                    'source'=>'csv'
                ]);
                $count++;
            }
        }
        set_transient('slb_ser_notice', ['type'=>'csv','count'=>$count,'dups'=>$duplicates], 30);
        wp_safe_redirect($base_url); exit;
    }

    // ── Manual paste (moved here from Distributors page) ──
    if(isset($_POST['ser_manual_nonce']) && wp_verify_nonce($_POST['ser_manual_nonce'],'slb_ser_manual') && !empty($_POST['ser_manual_text'])){
        $dist_id = intval($_POST['ser_manual_dist_id']);
        $lines = preg_split('/\r\n|\r|\n/', trim($_POST['ser_manual_text']));
        $c=0; $duplicates=[];
        foreach($lines as $ln){
            $s = trim($ln);
            if(!$s) continue;
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t_serials WHERE serial=%s", $s));
            if($exists){ $duplicates[]=$s; }
            else { $wpdb->insert($t_serials, ['serial'=>$s, 'distributor_id'=>$dist_id, 'source'=>'manual']); $c++; }
        }
        set_transient('slb_ser_notice', ['type'=>'manual','count'=>$c,'dups'=>$duplicates], 30);
        wp_safe_redirect($base_url); exit;
    }

    $msg = sanitize_text_field($_GET['slb_msg'] ?? '');
    if($msg==='deleted')   echo '<div class="notice notice-success is-dismissible"><p>Serial deleted.</p></div>';
    if($msg==='protected') echo '<div class="notice notice-error is-dismissible"><p>Cannot delete — an approved registration exists for that serial. Protected to ensure warranty integrity.</p></div>';

    // Upload result notice (from transient set before redirect)
    $up = get_transient('slb_ser_notice');
    if($up){
        delete_transient('slb_ser_notice');
        echo '<div class="notice notice-success is-dismissible"><p>Added '.intval($up['count']).' new serial'.($up['count']==1?'':'s').'.</p></div>';
        if(!empty($up['dups'])){
            echo '<div class="notice notice-warning is-dismissible"><p><strong>'.count($up['dups']).' duplicate'.(count($up['dups'])==1?'':'s').' skipped</strong> (already exist): '.esc_html(implode(', ', array_slice($up['dups'],0,30))).(count($up['dups'])>30?'…':'').'</p></div>';
        }
    }

    $q = trim($_GET['q'] ?? '');
    $dist_filter = intval($_GET['dist_filter'] ?? 0);
    $where_clauses = [];
    $params = [];

    if($q){
        $where_clauses[] = '(s.serial LIKE %s OR s.product_sku LIKE %s)';
        $like = '%'.$wpdb->esc_like($q).'%';
        $params[] = $like; $params[] = $like;
    }
    if($dist_filter){
        $where_clauses[] = 's.distributor_id = %d';
        $params[] = $dist_filter;
    }

    $where = $where_clauses ? implode(' AND ', $where_clauses) : '1=1';
    // prepare() with no placeholders raises "called incorrectly" on WP 6.x, and this
    // screen has no filter by default -- so it complained on a plain page load.
    $sql = "SELECT s.*, d.name as distributor_name, d.address as distributor_address FROM $t_serials s LEFT JOIN $t_dist d ON s.distributor_id=d.id WHERE $where ORDER BY s.created_at DESC LIMIT 500";
    $rows = $wpdb->get_results( $params ? $wpdb->prepare( $sql, ...$params ) : $sql );
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $t_serials");

    $dists = $wpdb->get_results("SELECT * FROM $t_dist ORDER BY name ASC");
    ?>
    <div class="wrap slb-wrap">
        <h1 class="slb-title"><span class="dashicons dashicons-tag"></span> Serials</h1>
        <p class="slb-subtitle"><?php echo number_format($total_count); ?> serials in inventory<?php echo ($q||$dist_filter)?' — showing filtered results (max 500)':' (showing latest 500)'; ?>.</p>

        <?php if(empty($dists)): ?>
            <div class="notice notice-warning" style="margin:0 0 20px"><p>Add at least one distributor first (in the <a href="<?php echo esc_url(admin_url('admin.php?page=slb-warranty-distributors')); ?>">Distributors</a> page) before uploading serials manually.</p></div>
        <?php else: ?>
        <div class="slb-card slb-card-accent">
            <h2><span class="dashicons dashicons-upload"></span> Add Serials Manually</h2>
            <p style="color:#646970;margin-top:0">Serials from dealer sales are imported automatically via ERP Sync. Use the forms below only for manual additions or bulk CSV imports.</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:16px">

                <div>
                    <h3 style="margin-top:0">Paste serials (one per line)</h3>
                    <form method="post">
                        <?php wp_nonce_field('slb_ser_manual','ser_manual_nonce'); ?>
                        <p><label>Distributor:<br>
                            <select name="ser_manual_dist_id" style="min-width:260px">
                                <?php foreach($dists as $d) echo '<option value="'.$d->id.'">'.esc_html($d->name . ($d->address ? ' ('.$d->address.')' : '')).'</option>'; ?>
                            </select>
                        </label></p>
                        <p><textarea name="ser_manual_text" rows="6" style="width:100%" placeholder="100000000001&#10;100000000002&#10;100000000003"></textarea></p>
                        <p><input type="submit" class="button button-primary" value="Add Serials" /></p>
                    </form>
                </div>

                <div>
                    <h3 style="margin-top:0">Upload CSV file</h3>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('slb_ser_csv','ser_csv_nonce'); ?>
                        <p><label>Distributor:<br>
                            <select name="ser_dist_id" style="min-width:260px">
                                <?php foreach($dists as $d) echo '<option value="'.$d->id.'">'.esc_html($d->name . ($d->address ? ' ('.$d->address.')' : '')).'</option>'; ?>
                            </select>
                        </label></p>
                        <p><input type="file" name="ser_csv" accept=".csv" /></p>
                        <p style="color:#646970;font-size:12px">CSV header row: <code>serial,product_sku,product_id,shipped_date</code></p>
                        <p><input type="submit" class="button button-primary" value="Upload CSV" /></p>
                    </form>
                </div>

            </div>
        </div>
        <?php endif; ?>

        <h2 style="margin-top:24px">Serial Inventory</h2>
        <form method="get" class="slb-toolbar">
            <input type="hidden" name="page" value="slb-warranty-serials" />
            <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search serial or SKU…" style="min-width:240px" />
            <select name="dist_filter">
                <option value="0">All distributors</option>
                <?php foreach($dists as $d) echo '<option value="'.$d->id.'"'.selected($dist_filter,$d->id,false).'>'.esc_html($d->name).'</option>'; ?>
            </select>
            <input type="submit" class="button button-primary" value="Search" />
            <?php if($q||$dist_filter): ?><a class="button" href="<?php echo esc_url($base_url); ?>">Clear</a><?php endif; ?>
        </form>

        <table class="widefat striped">
            <thead><tr><th style="width:50px">ID</th><th>Serial</th><th>SKU</th><th>Distributor</th><th style="width:110px">Shipped</th><th style="width:110px">Registered</th><th style="width:90px">Actions</th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;color:#888;padding:24px">No serials found.</td></tr>
            <?php else: foreach($rows as $r):
                $del = wp_nonce_url(add_query_arg(['page'=>'slb-warranty-serials','action'=>'delete_serial','id'=>$r->id], admin_url('admin.php')), 'slb_delete_serial', 'slb_delete_serial_nonce');
            ?>
                <tr>
                    <td><?php echo intval($r->id); ?></td>
                    <td><strong><?php echo esc_html($r->serial); ?></strong></td>
                    <td><?php echo esc_html($r->product_sku); ?></td>
                    <td>
                        <?php echo esc_html($r->distributor_name); ?>
                        <?php if(!empty($r->distributor_address)): ?><br><small style="color:#646970"><?php echo esc_html($r->distributor_address); ?></small><?php endif; ?>
                    </td>
                    <td style="color:#646970"><?php echo esc_html($r->shipped_date); ?></td>
                    <td>
                        <?php if($r->registered): ?>
                            <span class="slb-badge slb-badge-green">Registered</span>
                        <?php else: ?>
                            <span class="slb-badge slb-badge-grey">Available</span>
                        <?php endif; ?>
                    </td>
                    <td><a class="button button-small" href="<?php echo esc_url($del); ?>" onclick="return confirm('Delete this serial?')">Delete</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* -----------------------------------------------------------------------
   Registrations admin (UPDATED: AUTO-DB REPAIR for product_model)
   ----------------------------------------------------------------------- */
/**
 * Row actions (approve / reject / duplicate / delete), handled BEFORE any output.
 *
 * Previously this lived inside the page renderer and acted on a plain GET, which
 * meant the action URL stayed in the address bar: one press of F5 -- or the back
 * button -- re-ran the decision and sent the customer another SMS and another
 * email. It also threw the admin back to the unfiltered list, so a decision made
 * from the toolbar's filtered view looked like it had done nothing.
 *
 * Now it acts once, then redirects back to the list the admin came from with a
 * short result code. Refreshing that URL repeats nothing.
 */
add_action( 'admin_init', 'slb_handle_registration_action' );
function slb_handle_registration_action() {
    if ( ! isset( $_GET['page'], $_GET['action'], $_GET['id'] ) ) return;
    if ( 'slb-warranty-registrations' !== $_GET['page'] ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'slb_reg_action', 'slb_reg_nonce' );

    global $wpdb;
    $t_regs    = $wpdb->prefix . 'slb_registrations';
    $t_serials = $wpdb->prefix . 'slb_serials';

    $id     = intval( $_GET['id'] );
    $action = sanitize_key( $_GET['action'] );
    $row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_regs WHERE id=%d", $id ) );

    $code = '';
    $arg  = '';

    if ( ! $row ) {
        $code = 'missing';
    } elseif ( 'released' === $row->status ) {
        // A RELEASED record belongs to nobody until the next owner takes it over
        // (from the app or this site's form). Approving it would silently hand it
        // back to the person who let it go; deleting it would let the serial be
        // registered with a NEW date -- a fresh warranty. Neither is allowed.
        $code = 'released';
    } elseif ( in_array( $action, array( 'approve', 'reject', 'duplicate' ), true ) ) {
        $target = array( 'approve' => 'approved', 'reject' => 'rejected', 'duplicate' => 'duplicate' );
        $want   = $target[ $action ];

        if ( $row->status === $want ) {
            // Already there. Doing it again would only text the customer a second
            // time about a decision that has not changed.
            $code = 'nochange';
            $arg  = $want;
        } elseif ( 'approve' === $action && ! slb_serial_free_for( $row->serial, $id ) ) {
            // One serial, one live warranty. Without this a second registration for
            // the same serial could also be approved, and the serial row would point
            // at whichever was approved last while BOTH claimed to be approved.
            $code = 'taken';
            $arg  = (string) slb_serial_owner( $row->serial );
        } else {
            $data = array( 'status' => $want );
            $data['notes'] = slb_stamp_decision( $row->notes, $want );

            if ( 'approved' === $want ) {
                $srow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_serials WHERE serial=%s", $row->serial ) );
                // The auto-approver records which shop the unit actually came from;
                // a manual approval used to leave this unset, so the Distributor
                // column stayed blank on exactly the rows a person had checked.
                if ( $srow && ! empty( $srow->distributor_id ) ) {
                    $data['distributor_id'] = (int) $srow->distributor_id;
                }
                $wpdb->update( $t_regs, $data, array( 'id' => $id ) );
                $wpdb->update( $t_serials, array( 'registered' => 1, 'registration_id' => $id ), array( 'serial' => $row->serial ) );
            } else {
                $wpdb->update( $t_regs, $data, array( 'id' => $id ) );
                // Rejecting or duplicating a row that HAD the serial must release it,
                // or the serial stays flagged as registered to a dead claim and the
                // real owner can never register it.
                $wpdb->update( $t_serials, array( 'registered' => 0, 'registration_id' => null ), array( 'registration_id' => $id ) );
            }

            slb_notify_decision( $row, $want );
            slb_flush_manual_counts();
            $code = $want;
        }
    } elseif ( 'delete' === $action ) {
        if ( 'approved' === $row->status ) {
            $code = 'nodelete';
        } else {
            $files_removed = 0;
            if ( ! empty( $row->invoice_file ) )  { $files_removed += slb_delete_uploaded_file( $row->invoice_file )  ? 1 : 0; }
            if ( ! empty( $row->product_photo ) ) { $files_removed += slb_delete_uploaded_file( $row->product_photo ) ? 1 : 0; }
            $wpdb->delete( $t_regs, array( 'id' => $id ) );
            $wpdb->update( $t_serials, array( 'registered' => 0, 'registration_id' => null ), array( 'registration_id' => $id ) );
            slb_flush_manual_counts();
            $code = 'deleted';
            $arg  = (string) $files_removed;
        }
    } else {
        return; // not one of ours
    }

    wp_safe_redirect( slb_registrations_url( array(
        'slb_done' => $code,
        'slb_id'   => $id,
        'slb_arg'  => $arg,
    ) ) );
    exit;
}

/** Is this serial free to be approved for $reg_id (nobody else holds it)? */
function slb_serial_free_for( $serial, $reg_id ) {
    $owner = slb_serial_owner( $serial );
    return ( 0 === $owner || (int) $reg_id === $owner );
}

/** Which registration currently holds this serial, if any. 0 = none. */
function slb_serial_owner( $serial ) {
    global $wpdb;
    $t_regs = $wpdb->prefix . 'slb_registrations';
    if ( '' === (string) $serial ) return 0;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $t_regs WHERE serial=%s AND status='approved' ORDER BY id ASC LIMIT 1", $serial
    ) );
}

/**
 * Append a line to notes saying who decided what, and when. The table had no
 * record of this at all: a rejected registration looked identical whether a
 * person had checked it or the reconciler had.
 */
function slb_stamp_decision( $notes, $status ) {
    $user = wp_get_current_user();
    $who  = ( $user && $user->display_name ) ? $user->display_name : 'admin';
    $line = sprintf( '[DECISION] %s by %s on %s', $status, $who, current_time( 'Y-m-d H:i' ) );
    $notes = (string) $notes;
    return '' === trim( $notes ) ? $line : $notes . ' | ' . $line;
}

/** The one place a decision SMS + email is sent, so every path behaves alike. */
function slb_notify_decision( $row, $status ) {
    $opts  = get_option( 'slb_warranty_opts', array() );
    $tpl   = $opts['sms_templates'][ $status ]   ?? '';
    $eml   = $opts['email_templates'][ $status ] ?? '';
    $start = $row->purchase_date ?: date( 'Y-m-d' );
    $vars  = array(
        'serial'  => $row->serial,
        'invoice' => $row->invoice_no,
        'name'    => $row->customer_name,
        'phone'   => $row->phone,
        'start'   => $start,
        'end'     => slb_warranty_end( $start, $row->serial ),
    );
    if ( $tpl ) slb_send_templated_sms( $row->phone, $tpl, $vars );
    if ( $eml ) slb_send_templated_email( $row->email, $eml, $vars, $status );
}

/** The registrations URL, keeping whatever the admin was filtered/searched on. */
function slb_registrations_url( $extra = array() ) {
    $keep = array( 'page' => 'slb-warranty-registrations' );
    foreach ( array( 'search_q', 'status_filter', 'paged' ) as $k ) {
        if ( ! empty( $_GET[ $k ] ) ) {
            $keep[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
        }
    }
    return add_query_arg( array_filter( array_merge( $keep, $extra ), 'strlen' ), admin_url( 'admin.php' ) );
}

/** Render the result of the last action, carried in the URL after the redirect. */
function slb_render_action_notice() {
    $code = isset( $_GET['slb_done'] ) ? sanitize_key( $_GET['slb_done'] ) : '';
    if ( '' === $code ) return;
    $id  = intval( $_GET['slb_id'] ?? 0 );
    $arg = sanitize_text_field( wp_unslash( $_GET['slb_arg'] ?? '' ) );

    $map = array(
        'approved'  => array( 'success', "Registration #$id approved. The customer has been notified." ),
        'rejected'  => array( 'warning', "Registration #$id rejected. The customer has been notified." ),
        'duplicate' => array( 'warning', "Registration #$id marked as duplicate. The customer has been notified." ),
        'deleted'   => array( 'success', "Registration #$id deleted." . ( $arg > 0 ? ' Uploaded file removed from the server.' : '' ) ),
        'nochange'  => array( 'info',    "Registration #$id was already " . esc_html( $arg ) . " &mdash; nothing changed and no message was sent." ),
        'nodelete'  => array( 'error',   'Cannot delete an approved registration &mdash; warranty integrity is protected.' ),
        'missing'   => array( 'error',   "Registration #$id no longer exists." ),
        'released'  => array( 'warning', "<strong>Registration #$id is released.</strong> Its previous owner let it go; the next owner takes it over (with the remaining warranty) from the app or the registration form. It cannot be approved, rejected or deleted." ),
        'taken'     => array( 'error',   "Serial already approved under registration #" . intval( $arg ) . ". One serial can hold only one live warranty &mdash; mark this one as <em>Duplicate</em>, or reject the other first." ),
    );
    if ( ! isset( $map[ $code ] ) ) return;
    list( $kind, $text ) = $map[ $code ];
    echo '<div class="notice notice-' . esc_attr( $kind ) . ' is-dismissible"><p>' . $text . '</p></div>';
}

function slb_admin_registrations(){
    if(!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;
    $t_regs = $wpdb->prefix.'slb_registrations';
    $t_serials = $wpdb->prefix.'slb_serials';
    $t_dist = $wpdb->prefix.'slb_distributors';

    // Auto-repair: add product_model column if missing — runs once per DB version, not on every load
    if ( get_option('slb_db_regs_version', '0') < '1.4' ) {
        $has_col = $wpdb->get_results("SHOW COLUMNS FROM $t_regs LIKE 'product_model'");
        if(empty($has_col)){
            $wpdb->query("ALTER TABLE $t_regs ADD COLUMN product_model varchar(191) DEFAULT NULL AFTER email");
        }
        update_option('slb_db_regs_version', '1.4');
    }

    // Row actions are handled on admin_init (see slb_handle_registration_action)
    // and the result arrives back here as a code in the URL, so refreshing the
    // page cannot repeat the decision.
    slb_render_action_notice();

    // Search / filter
    $search_q    = sanitize_text_field($_GET['search_q'] ?? '');
    $status_filter = sanitize_text_field($_GET['status_filter'] ?? '');
    $where_parts = [];
    $where_params = [];
    if ( $search_q ) {
        $like = '%' . $wpdb->esc_like($search_q) . '%';
        $where_parts[]  = '(r.serial LIKE %s OR r.customer_name LIKE %s OR r.phone LIKE %s OR r.invoice_no LIKE %s)';
        $where_params[] = $like; $where_params[] = $like; $where_params[] = $like; $where_params[] = $like;
    }
    if ( $status_filter ) {
        $where_parts[]  = 'r.status = %s';
        $where_params[] = $status_filter;
    }
    $where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    $page_num = max(1, intval($_GET['paged'] ?? 1));
    $per  = 30;
    $offset = ($page_num - 1) * $per;
    $count_sql = $where_params
        ? $wpdb->prepare("SELECT COUNT(*) FROM $t_regs r $where_sql", ...$where_params)
        : "SELECT COUNT(*) FROM $t_regs r";
    $total = $wpdb->get_var($count_sql);

    $data_sql = $where_params
        ? $wpdb->prepare("SELECT r.*, d.name as distributor_name, d.address as distributor_address FROM $t_regs r LEFT JOIN $t_dist d ON r.distributor_id=d.id $where_sql ORDER BY r.created_at DESC LIMIT %d OFFSET %d", ...array_merge($where_params, [$per, $offset]))
        : $wpdb->prepare("SELECT r.*, d.name as distributor_name, d.address as distributor_address FROM $t_regs r LEFT JOIN $t_dist d ON r.distributor_id=d.id ORDER BY r.created_at DESC LIMIT %d OFFSET %d", $per, $offset);
    $rows = $wpdb->get_results($data_sql);

    // Status badge colours
    $status_styles = [
        'approved'   => 'background:#dcfce7;color:#166534;',
        'pending'    => 'background:#fef3c7;color:#92400e;',
        'rejected'   => 'background:#fee2e2;color:#991b1b;',
        'duplicate'  => 'background:#e0e7ff;color:#3730a3;',
        'not_found'  => 'background:#f3f4f6;color:#374151;',
        'mismatch'   => 'background:#fff7ed;color:#9a3412;',
        'released'   => 'background:#ede9fe;color:#5b21b6;',
    ];

    $base_url = add_query_arg(['page' => 'slb-warranty-registrations'], admin_url('admin.php'));

    echo '<div class="wrap slb-wrap"><h1 class="slb-title"><span class="dashicons dashicons-list-view"></span> Warranty Registrations</h1><p class="slb-subtitle">Review and approve customer warranty submissions.</p>';

    // Search bar
    echo '<form method="get" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
    echo '<input type="hidden" name="page" value="slb-warranty-registrations">';
    echo '<input name="search_q" value="'.esc_attr($search_q).'" placeholder="Search serial, name, phone, invoice…" style="min-width:260px;">';
    echo '<select name="status_filter">';
    echo '<option value="">All statuses</option>';
    foreach(['approved','pending','rejected','duplicate','not_found','mismatch','released'] as $st){
        echo '<option value="'.esc_attr($st).'"'.selected($status_filter,$st,false).'>'.ucfirst(str_replace('_',' ',$st)).'</option>';
    }
    echo '</select>';
    echo '<input type="submit" class="button" value="Search">';
    if($search_q || $status_filter) echo ' <a class="button" href="'.esc_url($base_url).'">Clear</a>';
    echo '</form>';

    // Say plainly what this list is showing. Arriving from the toolbar lands on a
    // FILTERED list, and without a line like this it just looks like the same table
    // with most of the rows missing -- as though the two screens disagreed.
    if ( $search_q || $status_filter ) {
        $bits = [];
        if ( $status_filter ) { $bits[] = '<strong>' . esc_html( ucfirst( str_replace('_',' ', $status_filter) ) ) . '</strong>'; }
        if ( $search_q )      { $bits[] = 'matching &ldquo;' . esc_html( $search_q ) . '&rdquo;'; }
        $grand = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_regs" );
        echo '<p style="margin:-4px 0 12px;color:#4b5563;">Showing ' . implode( ' ', $bits )
            . ' &mdash; <strong>' . intval( $total ) . '</strong> of ' . $grand . ' registrations. '
            . '<a href="' . esc_url( $base_url ) . '">Show all</a></p>';
    }

    echo '<table class="widefat striped slb-reg-table"><thead><tr><th>ID</th><th>Serial</th><th>Model</th><th>Distributor</th><th>Name</th><th>Phone</th><th>Invoice</th><th>Purchase</th><th>File</th><th>Status</th><th style="min-width:210px">Actions</th></tr></thead><tbody>';
    foreach($rows as $r){
        // Keep the search and the status filter on every action link. Without this a
        // decision taken from the toolbar's filtered view dumped the admin back on the
        // unfiltered list -- which reads as a different table that disagrees with the
        // one they were just looking at.
        $act = function ( $what ) use ( $r ) {
            return wp_nonce_url(
                slb_registrations_url( array( 'action' => $what, 'id' => $r->id ) ),
                'slb_reg_action', 'slb_reg_nonce'
            );
        };
        $approve = $act('approve'); $reject = $act('reject');
        $dup     = $act('duplicate'); $del   = $act('delete');

        $file_html = '';
        if(!empty($r->invoice_file)){
            $url = esc_url($r->invoice_file);
            $ext = strtolower(pathinfo($r->invoice_file, PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png','gif','webp'])){
                $file_html = '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" style="display:inline-block;line-height:0;vertical-align:middle"><img src="'.$url.'" style="display:block;max-width:80px;max-height:60px;object-fit:contain;border:1px solid #ddd;padding:2px" /></a>';
            } else {
                $file_html = '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">Download</a>';
            }
        }

        $d_name = $r->distributor_name ?: $r->dealer_name;
        $d_html = esc_html($d_name);
        if(!empty($r->distributor_address)){
            $d_html .= '<br><small style="color:#666">'.esc_html($r->distributor_address).'</small>';
        }

        $status_style = $status_styles[$r->status] ?? 'background:#f3f4f6;color:#374151;';
        $status_badge = '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;'.$status_style.'">'.esc_html(ucfirst(str_replace('_',' ',$r->status))).'</span>';
        // Why this one waits for a PERSON. The reason lives in the notes, which
        // this table does not otherwise show — without this, a held registration
        // looked like any other pending one and staff could approve it blind.
        $r_notes = (string) ( $r->notes ?? '' );
        if ( in_array( $r->status, ['pending','not_found','mismatch'], true ) ) {
            $held_at = strrpos( $r_notes, '[HELD FOR REVIEW]' );
            if ( false !== $held_at ) {
                $why = trim( explode( ' | ', trim( substr( $r_notes, $held_at + 17 ) ) )[0] );
                $status_badge .= '<br><span title="'.esc_attr($why).'" style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:10px;font-size:11px;font-weight:700;background:#fee2e2;color:#991b1b;cursor:help">Held for review</span>'
                    . '<br><small style="color:#666">'.esc_html( wp_html_excerpt( $why, 140, '…' ) ).'</small>';
            }
            if ( false !== strpos( $r_notes, 'Conflicting submission from' ) ) {
                $status_badge .= '<br><span style="display:inline-block;margin-top:4px;padding:1px 6px;border-radius:10px;font-size:11px;font-weight:700;background:#fff7ed;color:#9a3412">Another number also submitted this serial</span>';
            }
        }

        echo '<tr>';
        echo '<td>'.intval($r->id).'</td>';
        echo '<td>'.esc_html($r->serial).'</td>';
        echo '<td>'.esc_html($r->product_model ?? '-').'</td>';
        echo '<td>'.$d_html.'</td>';
        echo '<td>'.esc_html($r->customer_name).'</td>';
        echo '<td>'.esc_html($r->phone).'</td>';
        echo '<td>'.esc_html($r->invoice_no).'</td>';
        echo '<td>'.esc_html($r->purchase_date).'</td>';
        echo '<td>'.$file_html.'</td>';
        echo '<td>'.$status_badge.'</td>';
        echo '<td><div class="slb-action-group">';
        if ( $r->status === 'released' ) {
            // Nothing to act on: the next owner takes it over from the app or
            // the form, keeping the original warranty. (The actions are refused
            // for released rows anyway — these buttons only invited the error.)
            echo '<span style="font-size:12px;color:#5b21b6">Waiting for the next owner</span>';
        } else {
            // Only the decisions that would CHANGE something. Offering "Approve" on an
            // approved row invited a second approval SMS to a customer who had already
            // had one -- the button did not refuse, it just did it again.
            if ( $r->status !== 'approved' ) {
                echo '<a class="button button-small button-primary" href="'.esc_url($approve).'">Approve</a>';
            }
            if ( $r->status !== 'rejected' ) {
                echo '<a class="button button-small slb-btn-reject" href="'.esc_url($reject).'" onclick="return confirm(\'Reject this registration? The customer will be told.\')">Reject</a>';
            }
            if ( $r->status !== 'duplicate' ) {
                echo '<a class="button button-small" href="'.esc_url($dup).'">Duplicate</a>';
            }
            if ( $r->status !== 'approved' ) {
                echo '<a class="button button-small slb-btn-delete" href="'.esc_url($del).'" onclick="return confirm(\'Delete this registration? This cannot be undone.\')">Delete</a>';
            }
        }
        echo '</div></td></tr>';
    }
    if ( empty( $rows ) ) {
        // An empty result used to render as bare column headings and nothing else,
        // which reads as a broken table rather than an answered question.
        echo '<tr><td colspan="11" style="padding:22px;text-align:center;color:#6b7280;">'
            . ( ( $search_q || $status_filter )
                ? 'Nothing matches this filter. <a href="' . esc_url( $base_url ) . '">Show all registrations</a>'
                : 'No registrations yet.' )
            . '</td></tr>';
    }
    echo '</tbody></table>';

    $pages = ceil($total/$per);
    if($pages>1){
        echo '<div style="margin-top:12px;">';
        for($i=1;$i<=$pages;$i++){
            $plink = add_query_arg(array_filter(['page'=>'slb-warranty-registrations','paged'=>$i,'search_q'=>$search_q,'status_filter'=>$status_filter]), admin_url('admin.php'));
            $style = ($i===$page_num) ? 'font-weight:bold;margin-right:6px;' : 'margin-right:6px;';
            echo '<a style="'.$style.'" href="'.esc_url($plink).'">'.$i.'</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}

/* -----------------------------------------------------------------------
   Cross-check helpers — verify a registration's model & shop against the
   ERP-synced truth carried on the serial row, so a customer cannot register
   the wrong projector model or claim the wrong shop.
   ----------------------------------------------------------------------- */
function slb_norm_match( $value ) {
    return mb_strtolower( trim( preg_replace( '/\s+/', ' ', (string) $value ) ) );
}

/**
 * For a serial that EXISTS in inventory ($srow must carry serial, product_name,
 * product_id, distributor_name, distributor_id), decide approve vs mismatch.
 *   - Model is cross-checked whenever the serial's real product is known.
 *     (Skipped if the serial has no product on file — i.e. the product has no
 *     ERP Product ID mapped — so we never wrongly reject what we can't verify.)
 *   - Shop is cross-checked when the auto-approve rule requires distributor match.
 * A mismatch is HELD for manual review, never auto-rejected.
 * Returns ['status' => 'approved'|'mismatch', 'note' => string].
 */
function slb_registration_match_status( $model, $dealer_name, $srow, $auto_rule ) {
    if ( $model !== '' && ! empty( $srow->product_name ) ) {
        if ( slb_norm_match( $model ) !== slb_norm_match( $srow->product_name ) ) {
            return [
                'status' => 'mismatch',
                'reason' => 'model',
                'note'   => 'MODEL MISMATCH: registered as "' . $model . '" but serial ' . $srow->serial . ' was sold as "' . $srow->product_name . '".',
            ];
        }
    }

    if ( $auto_rule === 'match_serial_and_distributor'
         && $dealer_name !== '' && ! empty( $srow->distributor_id ) ) {
        if ( slb_norm_match( $dealer_name ) !== slb_norm_match( $srow->distributor_name ) ) {
            return [
                'status' => 'mismatch',
                'reason' => 'shop',
                'note'   => 'SHOP MISMATCH: selected "' . $dealer_name . '" but serial ' . $srow->serial . ' was sold by "' . $srow->distributor_name . '".',
            ];
        }
    }

    return [ 'status' => 'approved', 'reason' => '', 'note' => '' ];
}

/* -----------------------------------------------------------------------
   CF7 submission handler
   ----------------------------------------------------------------------- */
add_action('wpcf7_mail_sent', 'slb_cf7_process_warranty_registration_v2');
function slb_cf7_process_warranty_registration_v2($contact_form){
    slb_cf7_outcome( '' ); // this submission's on-screen message starts as CF7's own
    try {

        if ( ! class_exists('WPCF7_Submission') ) {
            error_log('SLB ERROR: WPCF7_Submission class not found.');
            return;
        }

        $opts = get_option('slb_warranty_opts', []);
        $target_form_id = intval( $opts['cf7_form_id'] ?? 0 );
        $form_id = ( method_exists($contact_form,'id') ? (int) $contact_form->id() : 0 );

        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log("SLB DEBUG: CF7 handler fired. form_id={$form_id} target={$target_form_id}");

        if ( $target_form_id && $form_id !== $target_form_id ) {
            return;
        }

        $sub = WPCF7_Submission::get_instance();
        if ( ! $sub ) {
            error_log('SLB ERROR: WPCF7_Submission::get_instance returned null.');
            return;
        }

        $data = $sub->get_posted_data();
        $uploaded_files = method_exists($sub,'uploaded_files') ? $sub->uploaded_files() : [];

        // map fields
        $f_serial = $opts['field_serial'] ?? 'serial-number';
        $f_name   = $opts['field_name'] ?? 'full-name';
        $f_phone  = $opts['field_phone'] ?? 'phone';
        $f_email  = $opts['field_email'] ?? 'email';
        $f_model  = $opts['field_model'] ?? 'model';
        $f_invoice= $opts['field_invoice'] ?? 'invoice-number';
        $f_date   = $opts['field_date'] ?? 'purchase-date';
        $f_dealer = $opts['field_dealer'] ?? 'dealer-name';

        $serial = sanitize_text_field( $data[$f_serial] ?? '' );
        $name   = sanitize_text_field( $data[$f_name] ?? '' );
        $phone  = sanitize_text_field( $data[$f_phone] ?? '' );
        $email  = sanitize_email( $data[$f_email] ?? '' );
        $model  = sanitize_text_field( $data[$f_model] ?? '' );
        $invoice= sanitize_text_field( $data[$f_invoice] ?? '' );
        $pdate_raw = $data[$f_date] ?? '';
        if ( ! empty( $pdate_raw ) ) {
            $ts = strtotime( $pdate_raw );
            $pdate = ( $ts !== false ) ? date('Y-m-d', $ts) : null;
        } else {
            $pdate = null;
        }
        $dealer_name = sanitize_text_field( $data[$f_dealer] ?? '' );
        $notes = sanitize_textarea_field( $data['notes'] ?? '' );

        if ( ! $serial ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log("SLB DEBUG: Submission missing serial.");
            return;
        }

        global $wpdb;
        $t_serials = $wpdb->prefix . 'slb_serials';
        $t_regs    = $wpdb->prefix . 'slb_registrations';
        $t_dist    = $wpdb->prefix . 'slb_distributors';
        $t_prods   = $wpdb->prefix . 'slb_products';

        // Existing-registration check.
        // Only FINAL states block a re-registration: 'approved' is genuinely
        // registered, and 'rejected' is a decision staff already made. Anything
        // still in progress (pending / not_found / mismatch) is NOT registered,
        // so we let the customer re-submit to correct their details — handled
        // as an update further down (the serial column is UNIQUE, so we update
        // the same row rather than creating a duplicate).
        $existing = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t_regs WHERE serial=%s", $serial) );

        // ⚠️ OWNERSHIP RULES — the same ones the app follows (AUN_App_Transfers
        // in the AUN App API plugin): the warranty belongs to the projector and
        // is never reset; a projector someone owns only moves with their consent.
        // Each branch below closes a way this form used to let one customer take
        // or reset another's warranty. When the App API plugin is not active the
        // form behaves exactly as it did before.
        $engine            = class_exists( 'AUN_App_Transfers' ) && class_exists( 'AUN_App_Phone' );
        $force_pending     = false;
        $replaced_rejected = '';

        // Released by its previous owner: whoever registers next takes over the
        // REST of the original warranty — never a fresh one from today's date.
        if ( $existing && 'released' === $existing->status ) {
            if ( $engine ) {
                $res = AUN_App_Transfers::claim_released_for_web( $serial, $phone, $name, $email );
                if ( is_wp_error( $res ) ) {
                    slb_send_sms( $phone, 'SmartLiving: We could not register serial ' . $serial . ' just now. Please contact AUN support.' );
                    slb_cf7_outcome( 'We could not register this serial just now. Please contact AUN support. · এই মুহূর্তে সিরিয়ালটি রেজিস্টার করা যায়নি। অনুগ্রহ করে AUN সাপোর্টে যোগাযোগ করুন।' );
                } else {
                    slb_send_sms( $phone, sprintf( 'SmartLiving: Serial %s is now registered to you. It continues the original warranty, valid until %s.', $serial, $res['warranty_end'] ) );
                    slb_cf7_outcome( sprintf( 'Registered to you. Its previous owner released it, so the original warranty continues until %1$s. · রেজিস্টার হয়েছে। আগের মালিক ছেড়ে দিয়েছেন, তাই মূল ওয়ারেন্টি %1$s পর্যন্ত চলবে।', $res['warranty_end'] ) );
                }
            }
            return;
        }

        // Registered to SOMEONE ELSE: ask them to transfer it, instead of a dead
        // end. They get an SMS link; nothing moves unless they accept.
        if ( $engine && $existing && 'approved' === $existing->status && ! slb_same_phone( $phone, $existing->phone ) ) {
            slb_web_transfer_request( $serial, $name, $phone, $email );
            return;
        }

        // Staff rejected someone ELSE's claim: that must not lock the real owner
        // out for ever. Their submission replaces it and always goes to a person.
        if ( $existing && 'rejected' === $existing->status && ! slb_same_phone( $phone, $existing->phone ) ) {
            $replaced_rejected = 'Replaces a registration by ' . $existing->phone . ' that staff had rejected.';
            $wpdb->delete( $t_regs, ['id' => $existing->id] );
            slb_flush_manual_counts();
            $existing      = null;
            $force_pending = true;
        }

        if ( $existing && in_array( $existing->status, ['approved','rejected'], true ) ) {
            $key       = ( $existing->status === 'rejected' ) ? 'rejected' : 'duplicate';
            $tpl_sms   = $opts['sms_templates'][$key]   ?? ( $opts['sms_templates']['duplicate']   ?? '' );
            $tpl_email = $opts['email_templates'][$key] ?? ( $opts['email_templates']['duplicate'] ?? '' );
            $vars = ['serial'=>$serial,'invoice'=>$existing->invoice_no,'name'=>$name,'phone'=>$phone];
            if ( $tpl_sms )   slb_send_templated_sms($phone, $tpl_sms, $vars);
            if ( $tpl_email ) slb_send_templated_email($email, $tpl_email, $vars, $key);
            return;
        }

        // ⚠️ Still being verified, but submitted from a DIFFERENT number: never
        // overwrite it. This used to replace the pending registration outright —
        // name, phone, invoice — so a second person could put their number on
        // someone else's projector just before staff approved it. The attempt is
        // recorded for staff instead, and nothing changes.
        if ( $existing && ! slb_same_phone( $phone, $existing->phone ) ) {
            $wpdb->update( $t_regs, [ 'notes' => (string) $existing->notes . ' | Conflicting submission from ' . $phone . ' (' . $name . ') on ' . current_time( 'Y-m-d H:i' ) . ' — not applied.' ], [ 'id' => $existing->id ] );
            slb_send_sms( $phone, 'SmartLiving: Serial ' . $serial . ' already has a registration being verified. AUN support will contact you.' );
            slb_cf7_outcome( 'This serial already has a registration being verified. AUN support will contact you. · এই সিরিয়ালের একটি রেজিস্ট্রেশন যাচাই চলছে। AUN সাপোর্ট আপনার সাথে যোগাযোগ করবে।' );
            return;
        }

        // Not registered on the website: is it an AUN DIRECT purchase, or one a
        // customer holds in the app? Those need no registration here, and must
        // never get a second owner through this form.
        if ( $engine && ! $existing ) {
            $owner = AUN_App_Transfers::owner_of( $serial );
            if ( 'released_direct' === $owner['kind'] ) {
                $res = AUN_App_Transfers::claim_released_for_web( $serial, $phone, $name, $email );
                slb_send_sms( $phone, is_wp_error( $res )
                    ? 'SmartLiving: We could not register serial ' . $serial . ' just now. Please contact AUN support.'
                    : sprintf( 'SmartLiving: Serial %s is now registered to you. It continues the original warranty, valid until %s.', $serial, $res['warranty_end'] ) );
                slb_cf7_outcome( is_wp_error( $res )
                    ? 'We could not register this serial just now. Please contact AUN support. · এই মুহূর্তে সিরিয়ালটি রেজিস্টার করা যায়নি। অনুগ্রহ করে AUN সাপোর্টে যোগাযোগ করুন।'
                    : sprintf( 'Registered to you. Its previous owner released it, so the original warranty continues until %1$s. · রেজিস্টার হয়েছে। আগের মালিক ছেড়ে দিয়েছেন, তাই মূল ওয়ারেন্টি %1$s পর্যন্ত চলবে।', $res['warranty_end'] ) );
                return;
            }
            if ( 'direct' === $owner['kind'] ) {
                if ( slb_same_phone( $phone, $owner['phone'] ) ) {
                    slb_send_sms( $phone, 'SmartLiving: Serial ' . $serial . ' is already registered to you in the AUN Care app. No registration is needed.' );
                    slb_cf7_outcome( 'This projector is already registered to you in the AUN Care app — nothing more to do. · প্রজেক্টরটি AUN Care অ্যাপে আগে থেকেই আপনার নামে রেজিস্টার করা — আর কিছু করতে হবে না।' );
                } else {
                    slb_web_transfer_request( $serial, $name, $phone, $email );
                }
                return;
            }
            if ( 'none' === $owner['kind'] ) {
                $to = AUN_App_Transfers::transferable_owner( $serial );
                if ( ! is_wp_error( $to ) && 'erp_direct' === $to['kind'] ) {
                    if ( slb_same_phone( $phone, $to['phone'] ) ) {
                        $w_end = AUN_App_Transfers::warranty_end_for( $serial, (string) $to['sale']['sale_date'] );
                        slb_send_sms( $phone, sprintf( 'SmartLiving: You bought serial %s directly from AUN, so no registration is needed. Your warranty is active until %s.', $serial, $w_end ) );
                        slb_cf7_outcome( sprintf( 'You bought this projector directly from AUN, so no registration is needed. Your warranty is active until %1$s. · প্রজেক্টরটি সরাসরি AUN থেকে কেনা, তাই রেজিস্ট্রেশন লাগবে না। ওয়ারেন্টি %1$s পর্যন্ত চালু আছে।', $w_end ) );
                    } else {
                        slb_web_transfer_request( $serial, $name, $phone, $email );
                    }
                    return;
                }
            }
        }

        $row = $wpdb->get_row( $wpdb->prepare("SELECT s.*, d.name as distributor_name, p.name as product_name FROM $t_serials s LEFT JOIN $t_dist d ON s.distributor_id=d.id LEFT JOIN $t_prods p ON s.product_id=p.id WHERE s.serial=%s", $serial) );

        $status = 'pending';
        $system_note = '';
        $mismatch_reason = '';
        $auto_rule = $opts['auto_approve_rule'] ?? 'match_serial_and_distributor';

        if ( $row ) {
            if ( $auto_rule === 'off' ) {
                // Auto-approval disabled — everything waits for manual review.
                $status = 'pending';
            } else {
                // Cross-check the submitted model and shop against the ERP truth
                // carried on this serial. Wrong model or wrong shop -> held as
                // 'mismatch' for manual review instead of being auto-approved.
                $match = slb_registration_match_status( $model, $dealer_name, $row, $auto_rule );
                $status = $match['status'];
                $system_note = $match['note'];
                $mismatch_reason = $match['reason']; // 'model' | 'shop' | ''
            }
        } else {
            $status = 'not_found';
        }

        // ⚠️ Late-date guard (same as the app). The warranty counts from the date
        // typed on this form, so registering long after buying and typing a
        // recent date would buy extra months. A purchase dated more than 180
        // days after the dealer received the unit — or before it — is held for
        // a person to check against the invoice.
        if ( $row && $pdate && ! empty( $row->shipped_date ) && '0000-00-00' !== $row->shipped_date
            && in_array( $status, [ 'approved', 'mismatch' ], true ) ) {
            $gap = (int) floor( ( strtotime( $pdate ) - strtotime( $row->shipped_date ) ) / DAY_IN_SECONDS );
            if ( $gap < 0 || $gap > 180 ) {
                $status      = 'pending';
                $system_note = trim( $system_note . ' [HELD FOR REVIEW] Purchase date ' . $pdate . ' is ' . ( $gap < 0 ? 'BEFORE' : $gap . ' days after' ) . ' the dealer received this unit (' . $row->shipped_date . ') — check the invoice date.' );
            }
        }
        if ( $force_pending ) {
            $status      = 'pending';
            $system_note = trim( $system_note . ' [HELD FOR REVIEW] ' . $replaced_rejected );
        }

        // handle uploaded invoice/file -> move to uploads
        $invoice_file = '';
        if ( ! empty($uploaded_files) ) {
            foreach ($uploaded_files as $k => $v) {
                // Safely handle array if multiple files (though rare for single file upload)
                $path_to_check = is_array($v) ? ($v[0] ?? '') : $v;

                if ( ! empty($path_to_check) && file_exists($path_to_check) ) {
                    $orig_name = basename($path_to_check);
                    $moved_url = slb_move_cf7_uploaded_file_to_uploads($path_to_check, $orig_name);
                    if($moved_url){
                        $invoice_file = $moved_url;
                        break;
                    }
                } elseif ( is_string($path_to_check) && strpos($path_to_check, 'http') === 0 ) {
                    $invoice_file = $path_to_check;
                    break;
                }
            }
        }

        $dist_id_safe = ( $row && isset($row->distributor_id) ) ? $row->distributor_id : null;

        // Prepend any system mismatch reason so staff see WHY it is held.
        $final_notes = $system_note !== ''
            ? ( $notes !== '' ? $system_note . ' | Customer note: ' . $notes : $system_note )
            : $notes;

        $data_insert = [
            'serial' => $serial,
            'distributor_id' => $dist_id_safe,
            'customer_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'product_model' => $model,
            'invoice_no' => $invoice,
            'purchase_date' => $pdate,
            'dealer_name' => $dealer_name,
            'invoice_file' => $invoice_file,
            'product_photo' => '',
            'status' => $status,
            'notes' => $final_notes
        ];

        $formats = [
            '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ];

        if ( $existing ) {
            // In-progress re-submission: update the same row (serial is UNIQUE)
            // and re-evaluate from the corrected details.
            $update_data = $data_insert;
            if ( $invoice_file === '' ) unset( $update_data['invoice_file'] ); // keep previously uploaded file
            unset( $update_data['product_photo'] );                            // not managed by this handler
            $ok = $wpdb->update( $t_regs, $update_data, ['id' => $existing->id] );
            slb_flush_manual_counts();
            if ( $ok === false ) {
                error_log("SLB ERROR: DB update failed for serial={$serial}. SQL error: " . $wpdb->last_error);
                return;
            }
            $reg_id = $existing->id;
        } else {
            $ok = $wpdb->insert( $t_regs, $data_insert, $formats );
            slb_flush_manual_counts();
            if ( $ok === false ) {
                error_log("SLB ERROR: DB insert failed for serial={$serial}. SQL error: " . $wpdb->last_error);
                return;
            }
            $reg_id = $wpdb->insert_id;
        }

        // Queue image/PDF compression for AFTER the response is sent (see slb_queue_compression).
        // Done by registration ID so that if a PNG is converted to JPEG, we can update the
        // stored file link in the DB and the admin "view image" button keeps working.
        if ( $reg_id && ! empty($invoice_file) ) {
            slb_queue_compression( $reg_id );
        }

        // send notifications according to status (sms + email templates)
        $tpls_sms = $opts['sms_templates'] ?? [];
        $tpls_email = $opts['email_templates'] ?? [];

        if($status === 'approved'){
            if ( $row ) $wpdb->update( $t_serials, ['registered'=>1,'registration_id'=>$reg_id], ['id'=>$row->id] );
            $tpl_sms = $tpls_sms['approved'] ?? '';
            $tpl_email = $tpls_email['approved'] ?? '';
            $start = $pdate ?: date('Y-m-d');
            $end = slb_warranty_end( $start, $serial ); // the product's real warranty length, not always 12 months
            if($tpl_sms) slb_send_templated_sms($phone,$tpl_sms,['serial'=>$serial,'start'=>$start,'end'=>$end,'invoice'=>$invoice,'name'=>$name,'phone'=>$phone]);
            if($tpl_email) slb_send_templated_email($email,$tpl_email,['serial'=>$serial,'start'=>$start,'end'=>$end,'invoice'=>$invoice,'name'=>$name,'phone'=>$phone], 'approved');
        } else {
            // Handle pending, not_found, mismatch. A mismatch gets its reason-
            // specific template (model_mismatch / shop_mismatch) so the customer
            // knows exactly what to fix; falls back to generic 'mismatch' then
            // 'received'.
            $tpl_key = $status;
            if ( $status === 'mismatch' && $mismatch_reason !== '' ) {
                $tpl_key = $mismatch_reason . '_mismatch'; // 'model_mismatch' | 'shop_mismatch'
            }
            $tpl_sms   = $tpls_sms[$tpl_key]   ?? $tpls_sms['mismatch']   ?? $tpls_sms['received']   ?? '';
            $tpl_email = $tpls_email[$tpl_key] ?? $tpls_email['mismatch'] ?? $tpls_email['received'] ?? '';

            if($tpl_sms) slb_send_templated_sms($phone,$tpl_sms,['serial'=>$serial,'invoice'=>$invoice,'name'=>$name,'phone'=>$phone]);
            if($tpl_email) slb_send_templated_email($email,$tpl_email,['serial'=>$serial,'invoice'=>$invoice,'name'=>$name,'phone'=>$phone], $tpl_key);
        }

    } catch ( Throwable $e ) {
        error_log( 'SLB CRITICAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
    }
}

/* -----------------------------------------------------------------------
   AUTO-RECONCILIATION BRAIN
   Re-checks waiting registrations and auto-approves the ones whose serial
   has since arrived in inventory — closing the gap where a customer registers
   BEFORE their serial syncs from the ERP. Triggered three ways:
     (a) instantly, when the ERP sync imports a serial   -> 'slb_serial_synced'
     (b) once a day, as a safety net                     -> WP-Cron
     (c) on demand, from the ERP Sync admin page button
   It reuses the SAME auto_approve_rule, templates and senders as a normal
   approval, so an auto-reconciled registration is indistinguishable from a
   manually-approved one. Policy (per configuration): clean matches are
   approved; dealer-name mismatches are held as 'mismatch' for manual review;
   serials that never arrive stay waiting (never auto-rejected).

   @param  string|null $serial  Reconcile just this serial, or null to sweep.
   @param  int         $limit   Max registrations to process in a sweep (batch).
   @return int                  Number of registrations auto-approved.
   ----------------------------------------------------------------------- */
function slb_reconcile_pending_registrations( $serial = null, $limit = 100 ) {
    global $wpdb;
    $t_regs    = $wpdb->prefix . 'slb_registrations';
    $t_serials = $wpdb->prefix . 'slb_serials';
    $t_dist    = $wpdb->prefix . 'slb_distributors';
    $t_prods   = $wpdb->prefix . 'slb_products';

    $opts      = get_option( 'slb_warranty_opts', [] );
    $auto_rule = $opts['auto_approve_rule'] ?? 'match_serial_and_distributor';

    // If auto-approval is switched Off, the brain stays hands-off: everything
    // remains for manual review exactly as configured.
    if ( $auto_rule === 'off' ) return 0;

    // Gather the waiting registrations to examine. 'mismatch' is intentionally
    // excluded here — those are held for manual review by design.
    //
    // ⚠️ So is anything marked HELD FOR REVIEW. The late-date guard and the
    // "replaces someone else's rejected claim" rule both park a registration as
    // 'pending' FOR A PERSON. Without this exclusion the next sweep (daily, or
    // the moment the serial syncs) auto-approved them on model + shop alone,
    // quietly undoing both guards within a day.
    if ( $serial !== null ) {
        $regs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t_regs WHERE serial=%s AND status IN ('not_found','pending') AND ( notes IS NULL OR notes NOT LIKE '%%[HELD FOR REVIEW]%%' )", $serial
        ) );
    } else {
        // Bulk sweep — lift the time limit and cap the batch so one run stays
        // bounded even when many serials need synchronous SMS/email sends.
        if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 0 ); }
        $limit = max( 1, (int) $limit );
        $regs  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t_regs WHERE status IN ('not_found','pending') AND ( notes IS NULL OR notes NOT LIKE '%%[HELD FOR REVIEW]%%' ) ORDER BY created_at ASC LIMIT %d", $limit
        ) );
    }

    if ( empty( $regs ) ) return 0;

    $tpls_sms   = $opts['sms_templates'] ?? [];
    $tpls_email = $opts['email_templates'] ?? [];
    $approved   = 0;

    foreach ( $regs as $reg ) {
        // The serial must now exist in inventory.
        $srow = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, d.name AS distributor_name, p.name AS product_name FROM $t_serials s LEFT JOIN $t_dist d ON s.distributor_id=d.id LEFT JOIN $t_prods p ON s.product_id=p.id WHERE s.serial=%s",
            $reg->serial
        ) );

        // Still not synced -> leave it waiting (never auto-reject).
        if ( ! $srow ) continue;

        // Already linked to a registration -> nothing to do here.
        if ( ! empty( $srow->registered ) ) continue;

        // Cross-check model + shop against the ERP truth before auto-approving.
        // Wrong model or wrong shop -> hold as 'mismatch' for manual review.
        $match = slb_registration_match_status( $reg->product_model, $reg->dealer_name, $srow, $auto_rule );
        if ( $match['status'] === 'mismatch' ) {
            if ( $reg->status !== 'mismatch' ) {
                $note = ! empty( $reg->notes ) ? $match['note'] . ' | ' . $reg->notes : $match['note'];
                $wpdb->update( $t_regs, ['status'=>'mismatch', 'notes'=>$note], ['id'=>$reg->id] );
                slb_flush_manual_counts();

                // Tell the customer which detail to correct (same reason-specific template).
                $mk  = ( $match['reason'] !== '' ) ? $match['reason'] . '_mismatch' : 'mismatch';
                $tpl = $tpls_sms[$mk]   ?? $tpls_sms['mismatch']   ?? $tpls_sms['received']   ?? '';
                $eml = $tpls_email[$mk] ?? $tpls_email['mismatch'] ?? $tpls_email['received'] ?? '';
                $v   = ['serial'=>$reg->serial,'invoice'=>$reg->invoice_no,'name'=>$reg->customer_name,'phone'=>$reg->phone];
                if ( $tpl ) slb_send_templated_sms( $reg->phone, $tpl, $v );
                if ( $eml ) slb_send_templated_email( $reg->email, $eml, $v, $mk );
            }
            continue;
        }

        // ⚠️ The late-date guard, here too. A registration made BEFORE its
        // serial synced had no dealer date to check against, so it was never
        // held — and would be approved right here the moment the serial
        // arrives, with whatever purchase date was typed. Same rule as at
        // registration time: more than 180 days after the dealer received the
        // unit, or before it, waits for a person.
        if ( $reg->purchase_date && ! empty( $srow->shipped_date ) && '0000-00-00' !== $srow->shipped_date ) {
            $gap = (int) floor( ( strtotime( $reg->purchase_date ) - strtotime( $srow->shipped_date ) ) / DAY_IN_SECONDS );
            if ( $gap < 0 || $gap > 180 ) {
                $held = ' | [HELD FOR REVIEW] Purchase date ' . $reg->purchase_date . ' is ' . ( $gap < 0 ? 'BEFORE' : $gap . ' days after' ) . ' the dealer received this unit (' . $srow->shipped_date . ') — check the invoice date.';
                $wpdb->update( $t_regs, ['status'=>'pending', 'notes'=>(string) $reg->notes . $held], ['id'=>$reg->id] );
                slb_flush_manual_counts();
                continue;
            }
        }

        // -- Qualifies -> auto-approve, link the serial, notify the customer --
        $wpdb->update( $t_regs,    ['status'=>'approved', 'distributor_id'=>$srow->distributor_id], ['id'=>$reg->id] );
        slb_flush_manual_counts();
        $wpdb->update( $t_serials, ['registered'=>1, 'registration_id'=>$reg->id], ['id'=>$srow->id] );

        $start = $reg->purchase_date ?: date('Y-m-d');
        $end   = slb_warranty_end( $start, $reg->serial );
        $vars  = ['serial'=>$reg->serial,'start'=>$start,'end'=>$end,'invoice'=>$reg->invoice_no,'name'=>$reg->customer_name,'phone'=>$reg->phone];

        $tpl_sms   = $tpls_sms['approved'] ?? '';
        $tpl_email = $tpls_email['approved'] ?? '';
        if ( $tpl_sms )   slb_send_templated_sms( $reg->phone, $tpl_sms, $vars );
        if ( $tpl_email ) slb_send_templated_email( $reg->email, $tpl_email, $vars, 'approved' );

        $approved++;
    }

    return $approved;
}

// (a) Instant reconciliation when the ERP sync imports a serial.
add_action( 'slb_serial_synced', 'slb_reconcile_pending_registrations', 10, 1 );

// (b) Daily safety sweep — also catches serials added via CSV / manual paste.
add_action( 'slb_warranty_daily_reconcile', function () {
    slb_reconcile_pending_registrations( null, 200 );
} );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'slb_warranty_daily_reconcile' ) ) {
        wp_schedule_event( time() + 3600, 'daily', 'slb_warranty_daily_reconcile' );
    }
} );

/* -----------------------------------------------------------------------
   Move CF7 uploaded temp file to WP uploads and return URL
   ----------------------------------------------------------------------- */
function slb_move_cf7_uploaded_file_to_uploads($tmp_path, $original_name = '') {
    if ( is_array($tmp_path) ) $tmp_path = $tmp_path[0] ?? '';
    if ( empty($tmp_path) || ! file_exists($tmp_path) ) return '';

    // Security: allowlist of safe extensions only.
    // PHP files or executables must never be stored in the public uploads directory.
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $name_to_check = $original_name ?: basename($tmp_path);
    $ext = strtolower( pathinfo($name_to_check, PATHINFO_EXTENSION) );
    if ( ! in_array($ext, $allowed_extensions, true) ) {
        error_log('SLB WARRANTY: Blocked upload of disallowed file type: ' . $ext . ' (' . $name_to_check . ')');
        return '';
    }
    $upload = wp_upload_dir();
    if ( ! $upload || empty($upload['basedir']) ) return '';

    // Store warranty invoices in a dedicated, dated subfolder so they are easy to
    // find, back up, or purge — instead of cluttering the uploads root. Existing
    // records keep working because their full URL is already stored in the DB.
    $subdir       = 'warranty-invoices/' . date('Y/m');
    $dest_dir     = trailingslashit( $upload['basedir'] ) . $subdir;
    $dest_baseurl = trailingslashit( $upload['baseurl'] ) . $subdir;
    if ( ! wp_mkdir_p( $dest_dir ) ) {
        // Fall back to the uploads root if the subfolder cannot be created.
        $dest_dir     = $upload['basedir'];
        $dest_baseurl = $upload['baseurl'];
    }

    $name = sanitize_file_name( $original_name ?: basename($tmp_path) );
    $dest = wp_unique_filename( $dest_dir, $name );
    $dest_path = trailingslashit( $dest_dir ) . $dest;
    $dest_url  = trailingslashit( $dest_baseurl ) . $dest;

    $moved = @rename( $tmp_path, $dest_path ) || @copy( $tmp_path, $dest_path );
    if ( ! $moved ) return '';
    @chmod( $dest_path, 0644 );

    // NOTE: compression is intentionally NOT done here. The caller queues it by
    // registration ID (after the DB insert) so it can run AFTER the response is
    // flushed AND can update the stored link if a PNG gets converted to JPEG.
    return $dest_url;
}

/**
 * Queues a registration's uploaded file for compression in the most reliable way:
 *   1) PRIMARY — run on PHP 'shutdown', AFTER the HTTP response is flushed to the
 *      browser (fastcgi_finish_request / litespeed_finish_request). Namecheap runs
 *      LiteSpeed, so this fires in the same request a heartbeat after the customer
 *      gets their confirmation — with no dependence on WP-Cron (unreliable here).
 *   2) FALLBACK — also schedule a WP-Cron event in case the request dies early.
 * Keyed by registration ID so conversion (PNG→JPEG) can update the stored link.
 */
function slb_queue_compression( $reg_id ) {
    $reg_id = (int) $reg_id;
    if ( $reg_id <= 0 ) return;

    if ( ! isset( $GLOBALS['slb_compress_queue'] ) ) {
        $GLOBALS['slb_compress_queue'] = [];
        add_action( 'shutdown', 'slb_process_compression_queue', 99 );
    }
    $GLOBALS['slb_compress_queue'][ $reg_id ] = true;

    if ( ! wp_next_scheduled( 'slb_compress_upload_event', [ $reg_id ] ) ) {
        wp_schedule_single_event( time() + 1, 'slb_compress_upload_event', [ $reg_id ] );
    }
}

/**
 * Shutdown handler: flush the response to the user FIRST, then compress everything
 * queued during this request. If the server can't flush-and-continue, bail and let
 * WP-Cron handle it (running here would hold the user's connection open).
 */
function slb_process_compression_queue() {
    $flushed = false;
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        @fastcgi_finish_request(); $flushed = true;
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        @litespeed_finish_request(); $flushed = true;
    }
    if ( ! $flushed || empty( $GLOBALS['slb_compress_queue'] ) ) return;

    foreach ( array_keys( $GLOBALS['slb_compress_queue'] ) as $reg_id ) {
        try {
            slb_compress_registration_file( $reg_id );
            $ts = wp_next_scheduled( 'slb_compress_upload_event', [ $reg_id ] );
            if ( $ts ) wp_unschedule_event( $ts, 'slb_compress_upload_event', [ $reg_id ] );
        } catch ( \Throwable $e ) {
            error_log( 'SLB WARRANTY: post-response compression error: ' . $e->getMessage() );
        }
    }
}

/**
 * Background worker (WP-Cron fallback): compresses one registration's file.
 */
add_action( 'slb_compress_upload_event', 'slb_run_deferred_compression', 10, 1 );
function slb_run_deferred_compression( $reg_id ) {
    try {
        slb_compress_registration_file( $reg_id );
    } catch ( \Throwable $e ) {
        error_log( 'SLB WARRANTY: deferred compression error: ' . $e->getMessage() );
    }
}

/**
 * Compresses the invoice file attached to one registration. Images are resized and
 * re-encoded; non-JPEG images (PNG/GIF/WebP) are converted to JPEG for far smaller
 * files. If the filename changes (e.g. .png → .jpg), the registration's stored link
 * is updated so the admin view still works, and the old file is removed.
 *
 * @return int Bytes saved (0 if nothing changed).
 */
function slb_compress_registration_file( $reg_id ) {
    global $wpdb;
    $reg_id = (int) $reg_id;
    if ( $reg_id <= 0 ) return 0;

    $t_regs = $wpdb->prefix . 'slb_registrations';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, invoice_file FROM $t_regs WHERE id=%d", $reg_id ) );
    if ( ! $row || empty( $row->invoice_file ) ) return 0;

    $path = slb_url_to_local_path( $row->invoice_file );
    if ( ! $path || ! is_file( $path ) ) return 0;

    $ext    = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    $before = (int) filesize( $path );
    $final  = $path;

    if ( in_array( $ext, ['jpg','jpeg','png','gif','webp'], true ) ) {
        $result = slb_compress_image_file( $path );           // returns final path or false
        if ( is_string( $result ) && $result !== '' ) $final = $result;
    } elseif ( $ext === 'pdf' ) {
        slb_compress_pdf_file( $path );
    } else {
        return 0;
    }

    // If conversion produced a new filename, update the stored link and the original is gone.
    if ( $final !== $path ) {
        $new_url = slb_path_to_upload_url( $final );
        if ( $new_url ) {
            $wpdb->update( $t_regs, ['invoice_file' => $new_url], ['id' => $reg_id], ['%s'], ['%d'] );
        }
    }

    clearstatcache( true, $final );
    $after = is_file( $final ) ? (int) filesize( $final ) : $before;
    return max( 0, $before - $after );
}

/**
 * Inverse of slb_url_to_local_path(): maps a safe in-uploads absolute path back to
 * its public URL. Returns '' if the path is not inside the uploads directory.
 */
function slb_path_to_upload_url( $path ) {
    $upload = wp_upload_dir();
    if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) return '';
    $real_base = realpath( $upload['basedir'] );
    $real_path = realpath( $path );
    if ( ! $real_base || ! $real_path ) return '';
    if ( strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) !== 0 ) return '';
    $relative = ltrim( substr( $real_path, strlen( $real_base ) ), '/\\' );
    return trailingslashit( $upload['baseurl'] ) . str_replace( '\\', '/', $relative );
}

/**
 * Resolves a stored file URL to a safe absolute path INSIDE this site's uploads
 * directory, or '' if it is anything else. Shared by the deleter and the bulk tool.
 * Hardened against directory-traversal (e.g. "../../wp-config.php").
 */
function slb_url_to_local_path( $file_url ) {
    $file_url = trim( (string) $file_url );
    if ( $file_url === '' ) return '';

    $upload = wp_upload_dir();
    if ( ! $upload || empty($upload['basedir']) || empty($upload['baseurl']) ) return '';
    if ( strpos( $file_url, $upload['baseurl'] ) !== 0 ) return '';

    $relative = ltrim( substr( $file_url, strlen( $upload['baseurl'] ) ), '/\\' );
    $path     = trailingslashit( $upload['basedir'] ) . $relative;

    $real_base = realpath( $upload['basedir'] );
    $real_path = realpath( $path );
    if ( ! $real_base || ! $real_path ) return '';
    if ( strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) !== 0 ) return '';
    return $real_path;
}

/**
 * Safely deletes a previously-uploaded warranty file, given the URL stored in the
 * registration row. Hardened against accidental/malicious deletion: it will ONLY
 * remove a file that physically resolves to a location INSIDE this site's uploads
 * directory. Anything else (external URLs, paths that escape uploads via "..", or
 * non-files) is refused. Returns true only if a real file was deleted.
 *
 * @param string $file_url The stored file URL (e.g. https://site/wp-content/uploads/...).
 */
function slb_delete_uploaded_file( $file_url ) {
    $real_path = slb_url_to_local_path( $file_url );
    if ( ! $real_path || ! is_file( $real_path ) ) return false;
    return @unlink( $real_path );
}

/**
 * One-time backlog tool: compresses a batch of already-uploaded invoice files.
 * Processes in small batches (cursor stored in an option) so it never times out on
 * shared hosting. Re-running on already-small files is a harmless no-op (they are
 * skipped by the size guard). Returns counts for the admin UI.
 */
function slb_run_bulk_compression( $batch = 15 ) {
    global $wpdb;
    $t_regs = $wpdb->prefix . 'slb_registrations';

    $grand_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_regs WHERE invoice_file <> ''" );
    $offset      = max( 0, (int) get_option( 'slb_compress_cursor', 0 ) );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, invoice_file FROM $t_regs WHERE invoice_file <> '' ORDER BY id ASC LIMIT %d OFFSET %d",
        $batch, $offset
    ) );

    $done = 0; $shrunk = 0; $saved = 0;
    foreach ( $rows as $r ) {
        $done++;
        // Registration-aware: compresses, converts PNG→JPEG, and updates the stored link.
        try {
            $bytes = slb_compress_registration_file( $r->id );
        } catch ( \Throwable $e ) {
            error_log( 'SLB WARRANTY: bulk compression error: ' . $e->getMessage() );
            $bytes = 0;
        }
        if ( $bytes > 0 ) { $shrunk++; $saved += $bytes; }
    }

    $new_offset = $offset + count( $rows );
    $finished   = ( $new_offset >= $grand_total ) || empty( $rows );
    if ( $finished ) {
        delete_option( 'slb_compress_cursor' );
        $new_offset = $grand_total;
    } else {
        update_option( 'slb_compress_cursor', $new_offset );
    }

    return [
        'done'        => $done,
        'shrunk'      => $shrunk,
        'saved'       => $saved,
        'processed'   => $new_offset,
        'grand_total' => $grand_total,
        'finished'    => $finished,
    ];
}

/**
 * Resizes and compresses an image to shrink its size, and CONVERTS non-JPEG images
 * (PNG/GIF/WebP) to JPEG — because those lossless formats barely shrink, whereas an
 * 800px JPEG at quality 72 keeps invoice text legible while a 3–8 MB photo (or a
 * heavy PNG screenshot) drops to roughly 80–150 KB.
 *
 * @param string $path    Absolute path to the image on disk.
 * @param int    $max_dim Longest-edge cap in pixels (aspect ratio preserved).
 * @param int    $quality JPEG quality (0–100). 72 keeps text edges crisp at 800px.
 * @return string|false   The FINAL path on disk (may be a new .jpg after conversion),
 *                        or false only if the input file does not exist.
 */
function slb_compress_image_file( $path, $max_dim = 800, $quality = 72 ) {
    if ( ! file_exists( $path ) ) return false;

    $ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    $is_jpeg = in_array( $ext, ['jpg','jpeg'], true );

    // Already small? Little to gain, and avoids needless re-encoding. (Returns the
    // unchanged path so callers don't think the filename changed.)
    if ( filesize( $path ) < 80 * 1024 ) return $path; // under 80 KB

    // Memory guard: getimagesize() reads only the header (cheap). Skip anything over
    // ~40 MP so we never trigger an out-of-memory fatal in the background worker.
    $info = @getimagesize( $path );
    if ( is_array( $info ) && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
        if ( ( $info[0] * $info[1] ) / 1000000 > 40 ) {
            error_log( 'SLB WARRANTY: image too large to compress safely, original kept: ' . basename($path) );
            return $path;
        }
    }

    if ( function_exists( 'wp_raise_memory_limit' ) ) {
        wp_raise_memory_limit( 'image' );
    }

    $editor = wp_get_image_editor( $path );
    if ( is_wp_error( $editor ) ) {
        error_log( 'SLB WARRANTY: image editor unavailable, leaving original: ' . $editor->get_error_message() );
        return $path;
    }

    $size = $editor->get_size();
    if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
        if ( $size['width'] > $max_dim || $size['height'] > $max_dim ) {
            $editor->resize( $max_dim, $max_dim, false ); // false = fit, don't crop
        }
    }
    $editor->set_quality( (int) $quality );

    // JPEG → re-encode in place, keep the same filename.
    if ( $is_jpeg ) {
        $saved = $editor->save( $path );
        if ( is_wp_error( $saved ) ) {
            error_log( 'SLB WARRANTY: JPEG compression failed, original kept: ' . $saved->get_error_message() );
        }
        return $path;
    }

    // PNG / GIF / WebP → convert to a new .jpg, then delete the original.
    // (Transparency is flattened — perfectly fine for invoice evidence.)
    $dir      = dirname( $path );
    $base     = pathinfo( $path, PATHINFO_FILENAME );
    $jpg_name = wp_unique_filename( $dir, $base . '.jpg' );
    $jpg_path = trailingslashit( $dir ) . $jpg_name;

    $saved = $editor->save( $jpg_path, 'image/jpeg' );
    if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
        $msg = is_wp_error( $saved ) ? $saved->get_error_message() : 'unknown error';
        error_log( 'SLB WARRANTY: PNG→JPEG conversion failed, original kept: ' . $msg );
        return $path;
    }

    $final = $saved['path'];
    if ( $final !== $path && is_file( $path ) ) {
        @unlink( $path ); // remove the now-redundant original PNG/GIF/WebP
    }
    @chmod( $final, 0644 );
    return $final;
}

/**
 * Returns true only if PHP's exec() is callable AND not blocked by disable_functions.
 * Most shared hosts disable exec() for security, so this is checked before any
 * attempt to shell out to Ghostscript.
 */
function slb_exec_available(): bool {
    if ( ! function_exists('exec') ) return false;
    $disabled = array_map( 'trim', explode( ',', (string) ini_get('disable_functions') ) );
    return ! in_array( 'exec', $disabled, true );
}

/**
 * Locates a usable Ghostscript binary, or returns '' if none is available.
 * You can hard-set the path in wp-config.php: define('SLB_GS_BIN', '/usr/bin/gs');
 */
function slb_find_ghostscript(): string {
    if ( ! slb_exec_available() ) return '';
    if ( defined('SLB_GS_BIN') && SLB_GS_BIN ) return SLB_GS_BIN;

    $candidates = ['gs', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/local/bin/gs', '/bin/gs'];
    foreach ( $candidates as $bin ) {
        $out = []; $code = 1;
        @exec( escapeshellarg($bin) . ' --version 2>/dev/null', $out, $code );
        if ( $code === 0 && ! empty($out) ) return $bin;
    }
    return '';
}

/**
 * Compresses a PDF IN PLACE using Ghostscript, if (and only if) it is installed
 * and exec() is permitted. Safe no-op otherwise — the original PDF is kept and a
 * note is logged. Typically shrinks invoice PDFs by 40–70%.
 *
 * Ghostscript -dPDFSETTINGS presets: /screen (smallest), /ebook (good balance),
 * /printer, /prepress (largest). 'ebook' is ideal for legible evidence.
 *
 * @param string $path    Absolute path to the PDF on disk.
 * @param string $setting One of screen|ebook|printer|prepress.
 */
function slb_compress_pdf_file( $path, $setting = 'ebook' ): bool {
    if ( ! file_exists( $path ) ) return false;
    if ( filesize( $path ) < 300 * 1024 ) return true; // under 300 KB — not worth it

    $gs = slb_find_ghostscript();
    if ( ! $gs ) {
        error_log( 'SLB WARRANTY: Ghostscript unavailable on this host; PDF left uncompressed: ' . basename($path) );
        return false;
    }

    $allowed = ['screen','ebook','printer','prepress'];
    if ( ! in_array( $setting, $allowed, true ) ) $setting = 'ebook';

    $tmp = $path . '.gs-tmp.pdf';
    $cmd = escapeshellarg( $gs )
         . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
         . ' -dPDFSETTINGS=/' . $setting
         . ' -dNOPAUSE -dQUIET -dBATCH -dDetectDuplicateImages=true'
         . ' -dColorImageResolution=120 -dGrayImageResolution=120 -dMonoImageResolution=150'
         . ' -sOutputFile=' . escapeshellarg( $tmp )
         . ' ' . escapeshellarg( $path )
         . ' 2>/dev/null';

    $out = []; $code = 1;
    @exec( $cmd, $out, $code );

    // Only replace the original if Ghostscript succeeded AND actually made it smaller.
    if ( $code === 0 && file_exists($tmp) && filesize($tmp) > 1024 && filesize($tmp) < filesize($path) ) {
        @rename( $tmp, $path );
        @chmod( $path, 0644 );
        return true;
    }

    if ( file_exists($tmp) ) @unlink( $tmp ); // clean up failed/no-gain attempt
    return false;
}

/* -----------------------------------------------------------------------
   SMS helpers
   ----------------------------------------------------------------------- */
/**
 * Whether two phone numbers are the same line, however they were typed
 * (01XXXXXXXXX, 8801XXXXXXXXX, +880 1XXX-XXXXXX).
 */
function slb_same_phone( $a, $b ) {
    if ( class_exists( 'AUN_App_Phone' ) ) {
        $x = AUN_App_Phone::normalize( (string) $a );
        $y = AUN_App_Phone::normalize( (string) $b );
        return $x && $x === $y;
    }
    $d = function ( $p ) { return substr( preg_replace( '/\D/', '', (string) $p ), -10 ); };
    return '' !== $d( $a ) && $d( $a ) === $d( $b );
}

/**
 * Warranty end date for a serial. Uses the product's REAL warranty from the ERP
 * (via the App API plugin) — this used to be start + 12 months for every
 * product, so any other warranty length went out in the SMS wrong.
 */
function slb_warranty_end( $start, $serial = '' ) {
    $start = $start ? $start : date( 'Y-m-d' );
    if ( class_exists( 'AUN_App_Warranty' ) && '' !== (string) $serial ) {
        list( $wd, $wu ) = AUN_App_Warranty::erp_warranty_for_serial( (string) $serial );
        $w = AUN_App_Warranty::warranty_from( $start, $wd, $wu );
        if ( ! empty( $w['end'] ) ) {
            return $w['end'];
        }
    }
    return date( 'Y-m-d', strtotime( $start . ' +12 months' ) );
}

/**
 * The website form met a projector someone else owns: ask that owner to
 * transfer it, and tell the person who submitted the form what happens next.
 */
function slb_web_transfer_request( $serial, $name, $phone, $email ) {
    $res = AUN_App_Transfers::request( $serial, array( 'user_id' => 0, 'phone' => $phone, 'name' => $name, 'email' => $email, 'source' => 'web' ) );
    if ( is_wp_error( $res ) ) {
        slb_send_sms( $phone, 'SmartLiving: ' . $res->get_error_message() );
        slb_cf7_outcome( $res->get_error_message() );
        return;
    }
    slb_send_sms( $phone, sprintf(
        'SmartLiving: Serial %s is registered to another customer (%s). We have asked them to transfer it to you - you will get an SMS when they answer.',
        $serial,
        $res['owner_phone']
    ) );
    slb_cf7_outcome( sprintf(
        'This projector is registered to another customer (%1$s). We have asked them to transfer it to you — you will get an SMS when they answer. · প্রজেক্টরটি অন্য একজন গ্রাহকের (%1$s) নামে রেজিস্টার করা। আমরা তাঁকে হস্তান্তরের অনুরোধ পাঠিয়েছি — উত্তর দিলে SMS পাবেন।',
        $res['owner_phone']
    ) );
}

/**
 * What the registration form should say ON SCREEN for this submission.
 *
 * ⚠️ The handler runs on wpcf7_mail_sent, after CF7 has decided the submission
 * succeeded — so without this the visitor always saw the form's normal
 * "registered" message, even when the projector turned out to be someone
 * else's, already theirs in the app, or needing no registration at all. The
 * SMS told the truth; the screen did not. Set during the handler, applied to
 * CF7's reply by the filter below; empty = CF7's own message, as before.
 */
function slb_cf7_outcome( $message = null ) {
    static $current = '';
    if ( null !== $message ) {
        $current = (string) $message;
    }
    return $current;
}
add_filter( 'wpcf7_feedback_response', function ( $response, $result = null ) {
    $m = slb_cf7_outcome();
    if ( '' !== $m && is_array( $response ) && 'mail_sent' === ( $response['status'] ?? '' ) ) {
        $response['message'] = $m;
    }
    return $response;
}, 10, 2 );

function slb_send_templated_sms($to, $template, $vars = []){
    if(empty($template)) return false;
    $message = $template;
    foreach($vars as $k=>$v) $message = str_replace('{'.$k.'}', $v, $message);
    if(mb_strlen($message) > 320) $message = mb_substr($message,0,320);
    return slb_send_sms($to,$message);
}

function slb_send_sms($to, $message){
    $opts = get_option('slb_warranty_opts', []);
    $api_url = rtrim( $opts['alpha_api_url'] ?? 'https://api.sms.net.bd/sendsms', '/' );
    // Constant in wp-config.php takes precedence over options — keeps key out of DB
    $api_key = defined('SLB_SMS_API_KEY') ? SLB_SMS_API_KEY : ( $opts['alpha_api_key'] ?? '' );
    $sender  = $opts['alpha_sender'] ?? 'SmartLiving';

    if ( empty($api_url) || empty($api_key) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('SLB SMS: API URL or API key not configured.');
        return false;
    }

    $to_clean = preg_replace('/\D+/', '', (string)$to);
    // Normalise to full Bangladesh international format: 8801XXXXXXXXX (13 digits)
    if ( strlen($to_clean) === 11 && substr($to_clean, 0, 2) !== '88' ) {
        // 01XXXXXXXXX → 8801XXXXXXXXX
        $to_clean = '88' . $to_clean;
    } elseif ( strlen($to_clean) === 10 && substr($to_clean, 0, 1) === '1' ) {
        // 1XXXXXXXXX → 8801XXXXXXXXX (stripped country code)
        $to_clean = '880' . $to_clean;
    }
    if ( ! preg_match('/^88(01|02|03|04|05|06|07|08|09)\d{8,10}$/', $to_clean) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('SLB SMS: invalid phone after normalization: ' . $to . ' -> ' . $to_clean);
        return false;
    }

    $msg = trim( wp_strip_all_tags( (string)$message ) );
    if ( mb_strlen($msg) > 640 ) $msg = mb_substr($msg, 0, 640);

    $payload = [
        'api_key'   => $api_key,
        'msg'       => $msg,
        'to'        => $to_clean,
        'sender_id' => $sender,
    ];

    $args = [
        'body'    => $payload,
        'timeout' => 20,
        'headers' => [ 'Accept' => 'application/json' ],
    ];

    $response = wp_remote_post( $api_url, $args );

    if ( is_wp_error($response) ) {
        error_log('SLB SMS: wp_remote_post error: ' . $response->get_error_message() );
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if ( $http_code === 200 && is_array($json) && isset($json['error']) ) {
        if ( intval($json['error']) === 0 ) {
            return $json; // success
        } else {
            // API returned an error code — log it and return false
            error_log('SLB SMS: API error ' . $json['error'] . ' — ' . ($json['msg'] ?? 'unknown') . ' for recipient ' . $to_clean);
            return false;
        }
    }
    return false;
}

/* -----------------------------------------------------------------------
   Email helpers (templated)
   ----------------------------------------------------------------------- */
function slb_send_templated_email($to_email, $template, $vars = [], $subject_key = '') {
    if (empty($template) || empty($to_email)) return false;
    
    // Inject current year automatically
    $vars['year'] = date('Y');

    // load subject from settings if subject_key provided (e.g. 'approved')
    $opts = get_option('slb_warranty_opts', []);
    $subject = '';
    if (!empty($subject_key)) {
        // admin stores subject in settings as email_<key>_subject (as in Settings UI)
        $subject_opt = $opts['email_templates'][$subject_key . '_subject'] ?? '';
        if ($subject_opt) {
            $subject = $subject_opt;
        }
    }
    if (!$subject) $subject = "Warranty update for {serial}";

    // replace placeholders in subject and body
    foreach($vars as $k=>$v) {
        $subject = str_replace('{'.$k.'}', $v, $subject);
    }
    $body_html = $template;
    foreach($vars as $k=>$v) {
        $body_html = str_replace('{'.$k.'}', esc_html($v), $body_html);
    }

    // Headers for HTML email
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $sent = wp_mail($to_email, $subject, $body_html, $headers);
    if ( ! $sent ) {
        // wp_mail() returning false means WordPress could not hand the message to a
        // mail server at all. Silence here is what made "customers stopped getting
        // emails" so hard to notice, so leave a trail in the PHP error log.
        error_log( 'SLB WARRANTY: wp_mail() failed for ' . $to_email . ' (subject: ' . $subject . ') - check the SMTP plugin / mail settings.' );
    }
    return $sent;
}

/* -----------------------------------------------------------------------
   CF7 product serial validation
   ----------------------------------------------------------------------- */
add_filter( 'wpcf7_validate_text*', 'slb_validate_serial_field', 20, 2 );
function slb_validate_serial_field( $result, $tag ) {
    $opts  = get_option( 'slb_warranty_opts', [] );
    $serial_field = $opts['field_serial'] ?? 'serial-number';
    if ( $tag->name !== $serial_field ) return $result;

    $value = isset( $_POST[ $tag->name ] ) ? trim( $_POST[ $tag->name ] ) : '';
    if ( $value && ! preg_match( '/^\d{12}$/', $value ) ) {
        $result->invalidate( $tag, 'Please enter a valid 12-digit serial number (digits only, no spaces).' );
    }
    return $result;
}

/* -----------------------------------------------------------------------
   CF7 date validation & frontend JS max date
   ----------------------------------------------------------------------- */
add_filter( 'wpcf7_validate_date*', 'slb_cf7_validate_purchase_date', 20, 2 );
add_filter( 'wpcf7_validate_date',  'slb_cf7_validate_purchase_date', 20, 2 );

function slb_cf7_validate_purchase_date( $result, $tag ) {
    if ( ! class_exists('WPCF7_FormTag') ) return $result;
    $tag_obj = new WPCF7_FormTag( $tag );
    $name = $tag_obj->name;

    $opts = get_option('slb_warranty_opts', []);
    $purchase_field = $opts['field_date'] ?? 'purchase-date';
    if ( $name !== $purchase_field ) return $result;

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) return $result;
    $data = $submission->get_posted_data();

    $val = trim( $data[$name] ?? '' );
    if ( empty($val) ) return $result;

    $submitted = strtotime( $val );
    if ( $submitted === false ) return $result;

    $today_end = strtotime( date('Y-m-d 23:59:59') );
    if ( $submitted > $today_end ) {
        $result->invalidate( $tag_obj, "Purchase date cannot be in the future." );
    }

    return $result;
}

add_action('wp_footer', function(){
    if ( is_admin() ) return;
    // Only inject on pages that contain a CF7 form shortcode
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || (
        strpos( $post->post_content, '[contact-form-7' ) === false &&
        strpos( $post->post_content, '[slb_' ) === false
    ) ) return;
    $opts = get_option('slb_warranty_opts', []);
    $field = esc_js($opts['field_date'] ?? 'purchase-date');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var today = new Date().toISOString().split('T')[0];
      var el = document.querySelector('input[name="<?php echo $field; ?>"]');
      if (el) el.setAttribute('max', today);
    });
    </script>
    <?php
});

/* --- Frontend CSS for Validation Highlighting --- */
add_action('wp_head', function(){
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || (
        strpos( $post->post_content, '[contact-form-7' ) === false &&
        strpos( $post->post_content, '[slb_' ) === false
    ) ) return;
    ?>
    <style data-no-optimize="1" data-no-minify="1">
    form.wpcf7-form .wpcf7-not-valid {
        border-color: #dc3232 !important;
        border-width: 1px !important;
    }
    span.wpcf7-not-valid-tip {
        color: #dc3232;
        font-size: 0.9em;
        display: block;
    }

    /* "Register Warranty" submit — same design as the AUN Repair Tracker / Live Tracking buttons */
    form.wpcf7-form .wpcf7-submit {
        width: 100%;
        margin-top: 8px;
        padding: 15px 24px;
        background: #0188fe;
        color: #fff;
        border: 1px solid #0188fe;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        box-sizing: border-box;
        -webkit-appearance: none;
        appearance: none;
        transition: background .2s;
    }
    form.wpcf7-form .wpcf7-submit:hover { background: #0070d6; border-color: #0070d6; }
    form.wpcf7-form .wpcf7-submit:disabled { background: #99c9fb; border-color: #99c9fb; cursor: not-allowed; }
    </style>
    <?php
});

/* -----------------------------------------------------------------------
   TOOLBAR BADGE - registrations waiting for a human
   -----------------------------------------------------------------------
   Registration is automatic, so the only thing worth interrupting the admin
   for is the exception: a serial we could not find, a dealer that did not
   match, or anything still sitting at 'pending'. Those need a decision.

   Deliberately shows NOTHING when there is nothing to do - no node, no zero
   badge, no styles - so its presence always means "something is waiting".
   ----------------------------------------------------------------------- */
add_action( 'admin_bar_menu', 'slb_admin_bar_node', 80 );
add_action( 'wp_head',    'slb_admin_bar_styles' );
add_action( 'admin_head', 'slb_admin_bar_styles' );

/**
 * How many registrations need a human, by status.
 *
 * Cached for a minute: the toolbar renders on EVERY page load (front end too,
 * for logged-in staff), so this must never become a per-request query.
 *
 * @return array{pending:int,not_found:int,mismatch:int,total:int}
 */
function slb_manual_action_counts() {
    $cached = get_transient( 'slb_manual_counts' );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    global $wpdb;
    $t   = $wpdb->prefix . 'slb_registrations';
    $out = [ 'pending' => 0, 'not_found' => 0, 'mismatch' => 0, 'total' => 0 ];

    // The table does not exist until the plugin has been activated once; querying
    // it before then would throw a DB error on every single page load.
    if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) n FROM $t WHERE status IN ('pending','not_found','mismatch') GROUP BY status"
        );
        foreach ( (array) $rows as $r ) {
            if ( isset( $out[ $r->status ] ) ) {
                $out[ $r->status ] = (int) $r->n;
                $out['total']     += (int) $r->n;
            }
        }
    }

    set_transient( 'slb_manual_counts', $out, MINUTE_IN_SECONDS );
    return $out;
}

/** Drop the cached count so a just-handled registration clears straight away. */
function slb_flush_manual_counts() {
    delete_transient( 'slb_manual_counts' );
}

function slb_admin_bar_node( $bar ) {
    if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
        return;
    }
    $c = slb_manual_action_counts();
    if ( $c['total'] < 1 ) {
        return; // nothing needs a human - say nothing at all
    }

    $base  = admin_url( 'admin.php?page=slb-warranty-registrations' );
    $title = '<span class="ab-icon dashicons dashicons-shield" style="top:2px;"></span>'
           . '<span class="ab-label">Warranty</span>'
           . '<span class="slb-ab-bubble">' . (int) $c['total'] . '</span>';

    $bar->add_node( [
        'id'    => 'slb-warranty-alert',
        'title' => $title,
        'href'  => $base,
        'meta'  => [ 'title' => $c['total'] . ' registration' . ( 1 === $c['total'] ? '' : 's' ) . ' need your decision' ],
    ] );

    $subs = [
        [ 'pending',   'Waiting for review',      $c['pending'] ],
        [ 'not_found', 'Serial not found',        $c['not_found'] ],
        [ 'mismatch',  'Dealer / model mismatch', $c['mismatch'] ],
    ];
    foreach ( $subs as $sub ) {
        list( $status, $label, $n ) = $sub;
        if ( $n < 1 ) {
            continue; // only list the kinds that actually have something
        }
        $bar->add_node( [
            'parent' => 'slb-warranty-alert',
            'id'     => 'slb-warranty-alert-' . $status,
            'title'  => $label . ' <span class="slb-ab-n">' . (int) $n . '</span>',
            'href'   => add_query_arg( 'status_filter', $status, $base ),
        ] );
    }
}

/** Bubble styling. Emitted only when the node is actually there. */
function slb_admin_bar_styles() {
    if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
        return;
    }
    $c = slb_manual_action_counts();
    if ( $c['total'] < 1 ) {
        return;
    }
    echo '<style id="slb-ab-css" data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
       . '#wpadminbar .slb-ab-bubble{display:inline-block;min-width:16px;height:16px;margin:0 0 0 5px;padding:0 5px;border-radius:8px;background:#d63638;color:#fff;font-size:11px;line-height:16px;text-align:center;font-weight:600;vertical-align:1px;}'
       . '#wpadminbar .slb-ab-n{display:inline-block;min-width:16px;padding:0 5px;margin-left:4px;border-radius:8px;background:rgba(255,255,255,0.18);color:#fff;font-size:11px;line-height:16px;text-align:center;}'
       . '#wpadminbar #wp-admin-bar-slb-warranty-alert .ab-icon:before{color:#f0f0f1;}'
       . '</style>';
}
