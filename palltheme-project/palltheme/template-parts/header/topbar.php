<?php
/**
 * Top bar: contact details + social links (from Business Settings).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( ! palltheme_mod( 'header_topbar' ) ) {
	return;
}

$phone  = palltheme_biz( 'phone' );
$email  = palltheme_biz( 'email' );
$hours  = palltheme_biz( 'hours_short' );
$social = palltheme_social_links();

if ( ! $phone && ! $email && ! $hours && ! $social && ! has_nav_menu( 'topbar' ) ) {
	return;
}
?>
<div class="pt-topbar">
	<div class="pt-container pt-topbar__inner">
		<ul class="pt-topbar__info">
			<?php if ( $phone ) : ?>
				<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php palltheme_the_icon( 'phone' ); ?><?php echo esc_html( $phone ); ?></a></li>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<li><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php palltheme_the_icon( 'mail' ); ?><?php echo esc_html( antispambot( $email ) ); ?></a></li>
			<?php endif; ?>
			<?php if ( $hours ) : ?>
				<li class="pt-topbar__hours"><?php palltheme_the_icon( 'clock' ); ?><?php echo esc_html( $hours ); ?></li>
			<?php endif; ?>
		</ul>
		<div class="pt-topbar__end">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'topbar',
					'container'      => false,
					'menu_class'     => 'pt-topbar__menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
			<?php if ( $social ) : ?>
				<ul class="pt-social pt-social--sm">
					<?php foreach ( $social as $network => $url ) : ?>
						<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( palltheme_social_label( $network ) ); ?>"><?php echo palltheme_social_icon( $network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
