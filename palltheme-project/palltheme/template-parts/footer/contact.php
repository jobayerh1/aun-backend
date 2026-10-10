<?php
/**
 * Compact contact block for the footer support column.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$phone   = palltheme_biz( 'phone' );
$email   = palltheme_biz( 'email' );
$address = palltheme_biz( 'address' );
if ( ! $phone && ! $email && ! $address ) {
	return;
}
?>
<ul class="pt-footer__contact">
	<?php if ( $phone ) : ?>
		<li><?php palltheme_the_icon( 'phone' ); ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
	<?php endif; ?>
	<?php if ( $email ) : ?>
		<li><?php palltheme_the_icon( 'mail' ); ?><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
	<?php endif; ?>
	<?php if ( $address ) : ?>
		<li><?php palltheme_the_icon( 'pin' ); ?><span><?php echo nl2br( esc_html( $address ) ); ?></span></li>
	<?php endif; ?>
</ul>
