<?php
/**
 * Breo customer order email: one template for processing, on-hold, completed,
 * cancelled, failed, refunded, invoice and note emails (the wording comes from
 * breo_bd_email_copy()). Every WooCommerce hook of the stock templates is kept,
 * so the order table, addresses, delivery estimate and other plugins' blocks
 * still appear.
 *
 * @var WC_Order $order
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$sent_to_admin      = $sent_to_admin ?? false;
$plain_text         = $plain_text ?? false;
$additional_content = $additional_content ?? '';
$breo_font          = breo_bd_email_font();
$breo_copy          = breo_bd_email_copy( $email, $order, ! empty( $partial_refund ) );
$breo_eyebrow_color = 'bad' === $breo_copy['tone'] ? '#b3261e' : ( 'wait' === $breo_copy['tone'] ? '#8a6a12' : '#b4531a' );

// The Breo header draws the brand band; the headline is drawn below instead of WooCommerce's heading.
do_action( 'woocommerce_email_header', '', $email );
?>
<div class="breo-e-hero" style="padding:8px 0 26px;margin:0 0 26px;border-bottom:1px solid #e8e2da;">
	<p style="margin:0 0 10px;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:12px;line-height:1.3;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:<?php echo esc_attr( $breo_eyebrow_color ); ?>;"><?php echo esc_html( $breo_copy['eyebrow'] ); ?></p>
	<h1 class="breo-e-title" style="margin:0 0 12px;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:28px;line-height:1.2;font-weight:700;letter-spacing:-0.5px;color:#1c1c1c;text-align:left;"><?php echo esc_html( $breo_copy['title'] ); ?></h1>
	<?php if ( $breo_copy['text'] ) : ?>
		<p style="margin:0;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:15px;line-height:1.6;color:#4a463f;"><?php echo esc_html( $breo_copy['text'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $customer_note ) ) : ?>
		<blockquote style="margin:16px 0 0;padding:14px 18px;border-left:3px solid #e8692c;background:#f7f2eb;color:#3b3833;font-family:<?php echo esc_attr( $breo_font ); ?>;font-size:15px;line-height:1.6;">
			<?php echo wpautop( make_clickable( wc_wptexturize_order_note( $customer_note ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</blockquote>
	<?php endif; ?>

	<?php if ( $breo_copy['step'] ) : ?>
		<?php echo breo_bd_email_steps( $breo_copy['step'] ); // phpcs:ignore ?>
	<?php endif; ?>

	<?php if ( ! empty( $breo_copy['cta'][1] ) ) : ?>
		<?php echo breo_bd_email_button( $breo_copy['cta'][0], $breo_copy['cta'][1] ); // phpcs:ignore ?>
	<?php endif; ?>
</div>
<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

// Text the owner added to this email in WooCommerce → Settings → Emails.
if ( $additional_content ) {
	echo '<div class="breo-e-extra" style="margin-top:24px;">' . wp_kses_post( wpautop( wptexturize( $additional_content ) ) ) . '</div>';
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
