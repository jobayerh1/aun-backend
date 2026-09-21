<?php
/**
 * Breo order emails.
 *
 * WooCommerce 10 only lets you set colours, a logo and footer text for its
 * emails, so the Breo design is applied here:
 *  - every WooCommerce email gets the Breo header (dark band + logo) and footer
 *    (help card, links, company line);
 *  - the customer order emails (processing, on-hold, completed, cancelled,
 *    failed, refunded, invoice, note) share one status-aware template with a
 *    headline, a Placed → Confirmed → Shipped → Delivered bar and a clear button;
 *  - Breo CSS on top of WooCommerce's, so the order table and addresses match;
 *  - friendlier default subject lines (a subject you typed yourself is kept).
 * Plain-text emails are untouched. Switch off in Tools → Breo BD Setup.
 */
defined( 'ABSPATH' ) || exit;

function breo_bd_emails_enabled() {
	return 'no' !== breo_bd_opt( 'email_design' );
}

/** HTML templates of the customer order emails that use templates/emails/customer-order.php. */
function breo_bd_email_order_templates() {
	return array(
		'emails/customer-processing-order.php',
		'emails/customer-on-hold-order.php',
		'emails/customer-completed-order.php',
		'emails/customer-cancelled-order.php',
		'emails/customer-failed-order.php',
		'emails/customer-refunded-order.php',
		'emails/customer-invoice.php',
		'emails/customer-note.php',
	);
}

add_filter( 'wc_get_template', function ( $template, $name ) {
	if ( ! breo_bd_emails_enabled() ) {
		return $template;
	}
	if ( 'emails/email-header.php' === $name ) {
		return BREO_BD_DIR . 'templates/emails/email-header.php';
	}
	if ( 'emails/email-footer.php' === $name ) {
		return BREO_BD_DIR . 'templates/emails/email-footer.php';
	}
	if ( in_array( $name, breo_bd_email_order_templates(), true ) ) {
		return BREO_BD_DIR . 'templates/emails/customer-order.php';
	}
	return $template;
}, 20, 2 );

/*
 * WooCommerce's header/footer templates are not told which email they belong to;
 * the woocommerce_email_header action is, so remember it for them.
 */
add_action( 'woocommerce_email_header', function ( $heading, $email = null ) {
	$GLOBALS['breo_email_current'] = $email;
}, 1, 2 );

add_filter( 'woocommerce_email_styles', function ( $css ) {
	return breo_bd_emails_enabled() ? $css . "\n" . breo_bd_email_css() : $css;
}, 20 );

/* ------------------------------------------------------------------------
 * Subjects: replace only WooCommerce's own default wording.
 * --------------------------------------------------------------------- */

function breo_bd_email_subjects() {
	return array(
		'customer_processing_order'         => 'Order #{order_number} confirmed. Thank you!',
		'customer_on_hold_order'            => 'We have received your order #{order_number}',
		'customer_completed_order'          => 'Your order #{order_number} is complete',
		'customer_cancelled_order'          => 'Your order #{order_number} has been cancelled',
		'customer_failed_order'             => 'Payment for order #{order_number} did not go through',
		'customer_refunded_order'           => 'Your refund for order #{order_number}',
		'customer_partially_refunded_order' => 'Part of your order #{order_number} has been refunded',
		'customer_note'                     => 'An update on your order #{order_number}',
	);
}

foreach ( array_keys( breo_bd_email_subjects() ) as $breo_email_id ) {
	add_filter( 'woocommerce_email_subject_' . $breo_email_id, function ( $subject, $order = null, $email = null ) use ( $breo_email_id ) {
		if ( ! breo_bd_emails_enabled() || ! $email instanceof WC_Email ) {
			return $subject;
		}
		$defaults = array( $email->format_string( $email->get_default_subject() ) );
		if ( method_exists( $email, 'get_default_subject' ) ) {
			$defaults[] = $email->format_string( $email->get_default_subject( true ) ); // partial-refund / paid variants
		}
		if ( ! in_array( $subject, $defaults, true ) ) {
			return $subject; // the owner wrote their own subject
		}
		$map = breo_bd_email_subjects();
		return $email->format_string( $map[ $breo_email_id ] );
	}, 20, 3 );
}
unset( $breo_email_id );

/* ------------------------------------------------------------------------
 * Copy per email / status.
 * --------------------------------------------------------------------- */

/** Progress step for an order: 1 placed, 2 confirmed, 3 shipped, 4 delivered, 0 = no bar. */
function breo_bd_email_step( $order ) {
	$status = $order->get_status();
	if ( in_array( $status, array( 'completed', 'delivered' ), true ) ) {
		return 4;
	}
	if ( 'shipped' === $status ) {
		return 3;
	}
	if ( 'processing' === $status ) {
		foreach ( array( 'pathao_consignment_id', '_pathao_consignment_id', '_tracking_number' ) as $k ) {
			if ( $order->get_meta( $k ) ) {
				return 3; // handed to the courier
			}
		}
		return 2;
	}
	if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
		return 1;
	}
	return 0;
}

function breo_bd_email_track_url( $order ) {
	$p = get_page_by_path( 'track-order' );
	if ( $p && 'publish' === $p->post_status ) {
		return add_query_arg( 'order', $order->get_order_number(), get_permalink( $p ) );
	}
	return $order->get_view_order_url();
}

/**
 * Headline copy for a customer order email.
 *
 * @return array{eyebrow:string,title:string,text:string,step:int,tone:string,cta:array}
 */
function breo_bd_email_copy( $email, $order, $partial = false ) {
	$id    = $email instanceof WC_Email ? $email->id : '';
	$first = trim( (string) $order->get_billing_first_name() );
	$num   = $order->get_order_number();
	$track = array( 'Track your order', breo_bd_email_track_url( $order ) );
	$view  = array( 'View your order', $order->get_customer_id() ? $order->get_view_order_url() : breo_bd_email_track_url( $order ) );
	$pay   = array( 'Pay for this order', $order->get_checkout_payment_url() );
	$money = function ( $amount ) use ( $order ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	};
	$w = function_exists( 'breo_bd_warranty_period' ) ? breo_bd_warranty_period() : '';

	switch ( $id ) {
		case 'customer_processing_order':
			$c = array(
				'eyebrow' => 'Order confirmed',
				'title'   => ( $first ? 'Thank you, ' . $first . '!' : 'Thank you!' ) . ' Your order is confirmed.',
				'text'    => 'We are packing it now and will hand it to our delivery partner soon. We will text you when it is on the way.',
				'tone'    => 'good',
				'cta'     => $track,
			);
			break;
		case 'customer_on_hold_order':
			$c = array(
				'eyebrow' => 'Order received',
				'title'   => 'We have received your order' . ( $first ? ', ' . $first : '' ) . '.',
				'text'    => 'It is on hold until your payment is confirmed. We will email and text you as soon as it is.',
				'tone'    => 'wait',
				'cta'     => $view,
			);
			break;
		case 'customer_completed_order':
			$c = array(
				'eyebrow' => 'Order complete',
				'title'   => 'Enjoy your Breo' . ( $first ? ', ' . $first : '' ) . '!',
				'text'    => 'Your order is complete. ' . ( $w ? 'Your ' . $w . ' warranty has started, so keep this email as your proof of purchase. ' : 'Keep this email as your proof of purchase. ' ) . 'Questions about using your device? Just reply, or message us on WhatsApp.',
				'tone'    => 'good',
				'cta'     => $view,
			);
			break;
		case 'customer_cancelled_order':
			$c = array(
				'eyebrow' => 'Order cancelled',
				'title'   => 'Your order #' . $num . ' has been cancelled.',
				'text'    => 'If you did not ask for this, or you have already paid, reply to this email or message us on WhatsApp and we will sort it out.',
				'tone'    => 'bad',
				'cta'     => array( 'Continue shopping', breo_bd_shop_url() ),
			);
			break;
		case 'customer_failed_order':
			$c = array(
				'eyebrow' => 'Payment unsuccessful',
				'title'   => 'Your payment did not go through.',
				'text'    => 'Your order is saved. You can try the payment again, or message us and we will help you finish it.',
				'tone'    => 'bad',
				'cta'     => $order->needs_payment() ? array( 'Try the payment again', $order->get_checkout_payment_url() ) : $view,
			);
			break;
		case 'customer_refunded_order':
		case 'customer_partially_refunded_order':
			$partial = $partial || 'customer_partially_refunded_order' === $id;
			$c       = array(
				'eyebrow' => $partial ? 'Partial refund' : 'Refund issued',
				'title'   => $partial ? 'We have refunded part of your order.' : 'Your refund is on its way.',
				'text'    => 'We have refunded ' . $money( $order->get_total_refunded() ) . ( $partial ? ' so far' : '' ) . ' for order #' . $num . '. Depending on how you paid, it can take a few working days to reach you.',
				'tone'    => 'wait',
				'cta'     => $view,
			);
			break;
		case 'customer_invoice':
			$c = $order->needs_payment()
				? array( 'eyebrow' => 'Payment request', 'title' => 'Your order is ready for payment.', 'text' => 'Use the button below to pay securely. Your order details are below.', 'tone' => 'wait', 'cta' => $pay )
				: array( 'eyebrow' => 'Your order', 'title' => 'Here are your order details' . ( $first ? ', ' . $first : '' ) . '.', 'text' => 'Placed on ' . wc_format_datetime( $order->get_date_created(), 'j F Y' ) . '.', 'tone' => 'good', 'cta' => $view );
			break;
		case 'customer_note':
			$c = array(
				'eyebrow' => 'Order update',
				'title'   => 'A note about your order #' . $num . '.',
				'text'    => '',
				'tone'    => 'good',
				'cta'     => $track,
			);
			break;
		default:
			$c = array( 'eyebrow' => 'Your order', 'title' => 'Order #' . $num, 'text' => '', 'tone' => 'good', 'cta' => $view );
	}
	$c['step'] = in_array( $id, array( 'customer_cancelled_order', 'customer_failed_order', 'customer_refunded_order', 'customer_partially_refunded_order' ), true ) ? 0 : breo_bd_email_step( $order );
	return $c;
}

/* ------------------------------------------------------------------------
 * Building blocks (inline styles, table layout: works in Gmail and Outlook).
 * --------------------------------------------------------------------- */

function breo_bd_email_font() {
	return "'Helvetica Neue', Helvetica, Arial, sans-serif";
}

function breo_bd_email_logo_url() {
	return file_exists( BREO_BD_DIR . 'assets/email-logo.png' ) ? BREO_BD_URL . 'assets/email-logo.png' : '';
}

/** Four-segment progress bar: Placed · Confirmed · Shipped · Delivered. */
function breo_bd_email_steps( $step ) {
	$labels = array( 'Placed', 'Confirmed', 'Shipped', 'Delivered' );
	$h      = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="breo-e-steps" style="width:100%;margin:26px 0 4px;border-collapse:separate;"><tr>';
	foreach ( $labels as $i => $label ) {
		$n     = $i + 1;
		$done  = $n < $step || 4 === $step;
		$cur   = $n === $step && $step < 4;
		$bar   = $done ? '#1c1c1c' : ( $cur ? '#e8692c' : '#e8e2da' );
		$color = ( $done || $cur ) ? '#1c1c1c' : '#a8a29a';
		$h    .= '<td width="25%" valign="top" style="width:25%;padding:0 ' . ( 4 === $n ? '0' : '6px' ) . ' 0 0;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td height="5" bgcolor="' . $bar . '" style="height:5px;padding:0;background:' . $bar . ';border-radius:5px;font-size:0;line-height:0;">&nbsp;</td></tr></table>'
			. '<p style="margin:9px 0 0;padding:0;font-family:' . breo_bd_email_font() . ';font-size:12px;line-height:1.3;font-weight:' . ( $cur ? '700' : '600' ) . ';color:' . $color . ';">' . esc_html( $label ) . '</p></td>';
	}
	return $h . '</tr></table>';
}

/** Pill button (a table cell carries the colour, so it survives Outlook). */
function breo_bd_email_button( $label, $url, $bg = '#1c1c1c', $fg = '#ffffff' ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 4px;"><tr>'
		. '<td bgcolor="' . esc_attr( $bg ) . '" style="padding:0;border-radius:999px;background:' . esc_attr( $bg ) . ';">'
		. '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:14px 30px;border-radius:999px;font-family:' . breo_bd_email_font() . ';font-size:15px;line-height:1;font-weight:700;color:' . esc_attr( $fg ) . ';text-decoration:none;">' . esc_html( $label ) . '</a>'
		. '</td></tr></table>';
}

/** Breo layer on top of WooCommerce's email CSS (inlined by WooCommerce). */
function breo_bd_email_css() {
	$f = breo_bd_email_font();
	return "
body, #outer_wrapper { background-color: #f3eee7 !important; }
#wrapper { padding: 28px 10px !important; }
#inner_wrapper { background-color: transparent !important; }
#template_container { background-color: #ffffff !important; border: 1px solid #e8e2da !important; border-radius: 20px !important; overflow: hidden; }
#template_header { background-color: #ffffff !important; color: #1c1c1c !important; }
#header_wrapper { padding: 32px 32px 0 !important; }
h1, #template_header h1 { color: #1c1c1c !important; font-family: {$f} !important; font-size: 26px !important; letter-spacing: -0.5px; line-height: 125% !important; }
h2 { color: #1c1c1c !important; font-family: {$f} !important; font-size: 17px !important; }
h3 { color: #1c1c1c !important; font-family: {$f} !important; }
#body_content { background-color: #ffffff !important; }
#body_content_inner { color: #4a463f !important; font-family: {$f} !important; font-size: 15px !important; line-height: 160% !important; }
#body_content p { margin: 0 0 14px; }
a, .link { color: #b4531a; } /* no !important: the buttons' own inline white must win */
.td, .text, .order-item-data, .address-title { color: #3b3833 !important; font-family: {$f} !important; }
#body_content .email-order-details th.td { color: #1c1c1c !important; }
#body_content .email-order-details thead th { color: #8a857d !important; font-size: 12px !important; letter-spacing: 1px; text-transform: uppercase; }
#body_content .email-order-details tbody tr:last-child td { border-bottom: 1px solid #e8e2da !important; }
#body_content .email-order-details .order-totals-last td, #body_content .email-order-details .order-totals-last th { border-bottom: 1px solid #e8e2da !important; }
#body_content .email-order-details .order-totals-total td { color: #1c1c1c !important; font-size: 20px !important; }
#body_content .email-order-details .order-totals-total th { color: #1c1c1c !important; }
.email-order-item-meta { color: #8a857d !important; }
.address { background-color: #f7f2eb !important; border-radius: 14px; padding: 14px 16px !important; color: #3b3833 !important; line-height: 150% !important; }
.hr { border-bottom: 1px solid #e8e2da !important; opacity: 1 !important; }
img { margin-right: 16px; }
blockquote { margin: 18px 0 0; padding: 14px 18px; border-left: 3px solid #e8692c; background-color: #f7f2eb; border-radius: 0 12px 12px 0; color: #3b3833; }
.breo-e-extra { color: #6f6a63; font-size: 14px; text-align: center; }
.breo-e-extra p { margin: 0 0 10px; }
@media screen and (max-width: 600px) {
	/* Only the outer content cell: '#body_content table td' would also pad every order-table cell. */
	#header_wrapper { padding: 26px 20px 0 !important; }
	#body_content_inner_cell { padding-left: 20px !important; padding-right: 20px !important; }
	#body_content .email-order-details thead th { font-size: 11px !important; letter-spacing: 0 !important; }
	#body_content table .email-order-details td, #body_content table .email-order-details th { padding-left: 6px !important; padding-right: 6px !important; }
	#body_content table .email-order-details td:first-child, #body_content table .email-order-details th:first-child { padding-left: 0 !important; }
	#body_content table .email-order-details td:last-child, #body_content table .email-order-details th:last-child { padding-right: 0 !important; }
	.email-order-details img { width: 40px !important; margin-right: 10px !important; }
	.breo-e-title { font-size: 24px !important; }
}
";
}
