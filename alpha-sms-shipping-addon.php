<?php
/**
 * Plugin Name: Alpha SMS - Shipping Status Addon
 * Description: Send SMS on shipment statuses using Alpha SMS settings and custom templates.
 * Version: 1.2
 * Author: Smart Living Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1) TRIGGER SMS IN THE BACKGROUND
 */
add_action( 'woocommerce_order_status_changed', 'alpha_sms_trigger_background_sms', 20, 4 );

function alpha_sms_trigger_background_sms( $order_id, $old_status, $new_status, $order ) {
    if ( ! $order_id || ! is_object( $order ) ) {
        return;
    }
	
    /**
     * In AST you renamed "Completed" label to "Shipped".
     * So internally WooCommerce status is still "completed".
     * We therefore treat:
     * - completed  -> Shipped SMS
     * - delivered  -> Delivered SMS
     */
    $target_statuses = array( 'completed', 'delivered' );
    if ( ! in_array( $new_status, $target_statuses, true ) ) {
        return;
    }

    // ✅ Push to queue, passing "1" as the first attempt number
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'alpha_sms_process_background_action', array( $order_id, $new_status, 1 ), 'alpha-sms' );
    } else {
        // Fallback for older WordPress versions
        wp_schedule_single_event( time(), 'alpha_sms_process_background_action', array( $order_id, $new_status, 1 ) );
    }
}

/**
 * 2) EXECUTE SMS IN THE BACKGROUND
 */
// ✅ Change priority & args: Now expects 3 arguments instead of 2
add_action( 'alpha_sms_process_background_action', 'alpha_sms_execute_background_sms', 10, 3 );

function alpha_sms_execute_background_sms( $order_id, $new_status, $attempt = 1 ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    // Read Alpha SMS main plugin settings (reuse API key & sender)
    $alpha_opts = get_option( 'alpha_sms' );
    if ( ! $alpha_opts || empty( $alpha_opts['api_key'] ) ) {
        return; // Alpha SMS not configured
    }

    $api_key   = $alpha_opts['api_key'];
    $sender_id = alpha_sms_shipping_addon_get_sender_id_from_alpha( $alpha_opts ); // NEW helper

    // Our custom templates (from this addon settings page)
    $shipped_tpl   = get_option( 'alpha_sms_shipped_template', '' );
    $delivered_tpl = get_option( 'alpha_sms_delivered_template', '' );

    // Fallback to Alpha plugin's Completed template if custom is empty
    $completed_tpl = isset( $alpha_opts['ORDER_STATUS_COMPLETED_SMS'] ) ? $alpha_opts['ORDER_STATUS_COMPLETED_SMS'] : '';

    if ( 'completed' === $new_status ) {
        // This is your "Shipped" status (label renamed by AST)
        $template = $shipped_tpl ? $shipped_tpl : $completed_tpl;
        if ( ! $template ) {
            $template = '[store_name] - প্রিয় [billing_first_name], আপনার অর্ডার #[order_id] শিপ করা হয়েছে। শীঘ্রই ডেলিভারি হবে ইনশাআল্লাহ।';
        }
    } else { // delivered
        $template = $delivered_tpl ? $delivered_tpl : $completed_tpl;
        if ( ! $template ) {
            $template = '[store_name] - প্রিয় [billing_first_name], আপনার অর্ডার #[order_id] সফলভাবে ডেলিভারড হয়েছে। ধন্যবাদ আমাদের সাথে থাকার জন্য!';
        }
    }

    // Replace tokens
    $message = alpha_sms_shipping_addon_replace_tokens( $template, $order );

    // Customer phone
    $phone = $order->get_billing_phone();
    if ( empty( $phone ) ) {
        return;
    }

    // Alpha SMS endpoint (as per their docs)
    $endpoint = 'https://api.sms.net.bd/sendsms';

    // Build body according to Alpha API
    $body = array(
        'api_key' => $api_key,
        'msg'     => wp_strip_all_tags( $message ),
        'to'      => $phone,
    );

    // Alpha SMS expects this exact key
    if ( ! empty( $sender_id ) ) {
        $body['sender_id'] = $sender_id;
    }

    $response = wp_remote_post(
        $endpoint,
        array(
            'body'    => $body,
            'timeout' => 60, // Stays at 60 seconds
        )
    );
    
    // -------- RETRY LOGIC & ADD ORDER NOTE --------
    $is_success = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );

    if ( $is_success ) {
        $order->add_order_note( 'Alpha SMS : Notified customer about his order ' . $new_status . ' status via SMS (Sender: ' . $sender_id . ').' );
    } else {
        // If it failed, check if we should try again
        if ( $attempt < 2 ) {
            // ✅ Schedule attempt 2 for 5 minutes (300 seconds) from now
            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time() + 300, 'alpha_sms_process_background_action', array( $order_id, $new_status, $attempt + 1 ), 'alpha-sms' );
            } else {
                wp_schedule_single_event( time() + 300, 'alpha_sms_process_background_action', array( $order_id, $new_status, $attempt + 1 ) );
            }
            return; // Stop here so we don't write the "Failed" note yet!
        } else {
            // Ultimately failed after 2 attempts
            $order->add_order_note( 'Alpha SMS : Failed to notify customer about his order ' . $new_status . ' status via SMS after 2 attempts.' );
            
            // Write exactly why it failed to the error log
            if ( is_wp_error( $response ) ) {
                error_log( 'Alpha SMS Shipping Addon error: ' . $response->get_error_message() );
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                error_log( 'Alpha SMS Shipping Addon non-200 response: ' . $code . ' - ' . wp_remote_retrieve_body( $response ) );
            }
        }
    }
}
/**
 * Helper: find Sender ID from Alpha main plugin options
 * Tries several common keys so it follows whatever the main plugin uses.
 */
function alpha_sms_shipping_addon_get_sender_id_from_alpha( $opts ) {

    if ( ! is_array( $opts ) ) {
        return '';
    }

    $keys_to_try = array(
        'senderid',
        'sender_id',
        'sender',
        'from',
        'alpha_sender',
    );

    foreach ( $keys_to_try as $key ) {
        if ( ! empty( $opts[ $key ] ) ) {
            return $opts[ $key ];
        }
    }

    return '';
}

/**
 * Token replacement
 */
function alpha_sms_shipping_addon_replace_tokens( $template, $order ) {
    if ( ! $order || ! is_object( $order ) ) {
        return $template;
    }

    $store_name    = get_bloginfo( 'name' );
    $first_name    = $order->get_billing_first_name();
    $order_id      = $order->get_id();
    $status        = $order->get_status();
    $amount        = $order->get_total();
    $currency      = $order->get_currency();
    $date_created  = $order->get_date_created();
    $date_completed = method_exists( $order, 'get_date_completed' ) ? $order->get_date_completed() : false;

    $replacements = array(
        '[store_name]'           => $store_name,
        '[billing_first_name]'   => $first_name,
        '[order_id]'             => $order_id,
        '[order_status]'         => $status,
        '[order_amount]'         => $amount . ' ' . $currency,
        '[order_currency]'       => $currency,
        '[order_date_created]'   => $date_created ? $date_created->date_i18n( 'Y-m-d H:i:s' ) : '',
        '[order_date_completed]' => $date_completed ? $date_completed->date_i18n( 'Y-m-d H:i:s' ) : '',
    );

    return strtr( $template, $replacements );
}

/**
 * 2) ADMIN SETTINGS PAGE – textareas for shipped & delivered templates
 */

add_action( 'admin_menu', 'alpha_sms_shipping_addon_admin_menu' );

function alpha_sms_shipping_addon_admin_menu() {
    add_options_page(
        'Alpha SMS Shipping',
        'Alpha SMS Shipping',
        'manage_options',
        'alpha-sms-shipping-addon',
        'alpha_sms_shipping_addon_settings_page'
    );
}

function alpha_sms_shipping_addon_settings_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Save form
    if ( isset( $_POST['alpha_sms_shipping_addon_save'] ) ) {
        check_admin_referer( 'alpha_sms_shipping_addon_save_action', 'alpha_sms_shipping_addon_nonce' );

        $shipped   = isset( $_POST['alpha_sms_shipped_template'] ) ? wp_kses_post( wp_unslash( $_POST['alpha_sms_shipped_template'] ) ) : '';
        $delivered = isset( $_POST['alpha_sms_delivered_template'] ) ? wp_kses_post( wp_unslash( $_POST['alpha_sms_delivered_template'] ) ) : '';

        update_option( 'alpha_sms_shipped_template', $shipped );
        update_option( 'alpha_sms_delivered_template', $delivered );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $shipped_tpl   = get_option( 'alpha_sms_shipped_template', '' );
    $delivered_tpl = get_option( 'alpha_sms_delivered_template', '' );

    ?>
    <div class="wrap">
        <h1>Alpha SMS - Shipping Status Addon</h1>

        <p>
            These templates are used when WooCommerce orders change to
            <strong>Completed (labelled Shipped)</strong> or <strong>Delivered</strong>
            via the Advanced Shipment Tracking plugin.
        </p>

        <h2>Available Tokens</h2>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><code>[store_name]</code> – Site/Store name</li>
            <li><code>[billing_first_name]</code> – Customer first name</li>
            <li><code>[order_id]</code> – WooCommerce order ID</li>
            <li><code>[order_status]</code> – Current order status (completed, delivered, etc.)</li>
            <li><code>[order_amount]</code> – Order total with currency code</li>
            <li><code>[order_currency]</code> – Currency code (e.g. BDT)</li>
            <li><code>[order_date_created]</code> – Order created date/time</li>
            <li><code>[order_date_completed]</code> – Order completed date/time (if available)</li>
        </ul>

        <form method="post" action="">
            <?php wp_nonce_field( 'alpha_sms_shipping_addon_save_action', 'alpha_sms_shipping_addon_nonce' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="alpha_sms_shipped_template">Shipped (Completed) status SMS</label></th>
                    <td>
                        <textarea
                            name="alpha_sms_shipped_template"
                            id="alpha_sms_shipped_template"
                            rows="4"
                            cols="70"
                        ><?php echo esc_textarea( $shipped_tpl ); ?></textarea>
                        <p class="description">
                            Sent when WooCommerce order status becomes <code>completed</code>
                            (shown as <strong>Shipped</strong> in AST).
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="alpha_sms_delivered_template">Delivered status SMS</label></th>
                    <td>
                        <textarea
                            name="alpha_sms_delivered_template"
                            id="alpha_sms_delivered_template"
                            rows="4"
                            cols="70"
                        ><?php echo esc_textarea( $delivered_tpl ); ?></textarea>
                        <p class="description">
                            Sent when order status becomes <code>delivered</code>.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="alpha_sms_shipping_addon_save" class="button button-primary">
                    Save Changes
                </button>
            </p>
        </form>
    </div>
    <?php
}
