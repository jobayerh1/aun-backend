<?php
/**
 * Off-canvas mobile menu.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$location = has_nav_menu( 'mobile' ) ? 'mobile' : 'primary';
$phone    = palltheme_biz( 'phone' );
$email    = palltheme_biz( 'email' );
$cta_text = (string) palltheme_mod( 'header_cta_text' );
$cta_url  = (string) palltheme_mod( 'header_cta_url' ) ?: home_url( '/contact/' );
?>
<div id="pt-offcanvas" class="pt-offcanvas" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'palltheme' ); ?>" hidden>
	<div class="pt-offcanvas__backdrop" data-pt-offcanvas-close></div>
	<div class="pt-offcanvas__panel">
		<div class="pt-offcanvas__head">
			<?php palltheme_logo(); ?>
			<button type="button" class="pt-icon-btn" data-pt-offcanvas-close>
				<?php palltheme_the_icon( 'close' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'palltheme' ); ?></span>
			</button>
		</div>

		<form role="search" method="get" class="pt-offcanvas__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="pt-offcanvas-s"><?php esc_html_e( 'Search', 'palltheme' ); ?></label>
			<input id="pt-offcanvas-s" type="search" name="s" placeholder="<?php esc_attr_e( 'Search products, services…', 'palltheme' ); ?>">
			<button type="submit" class="pt-icon-btn"><?php palltheme_the_icon( 'search' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Search', 'palltheme' ); ?></span></button>
		</form>

		<nav class="pt-offcanvas__nav" aria-label="<?php esc_attr_e( 'Mobile', 'palltheme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => $location,
					'container'      => false,
					'menu_class'     => 'pt-mobile-menu',
					'menu_id'        => 'pt-mobile-menu',
					'depth'          => 3,
					'fallback_cb'    => 'palltheme_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="pt-offcanvas__foot">
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<a class="pt-offcanvas__link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php palltheme_the_icon( 'user' ); ?><?php esc_html_e( 'My account', 'palltheme' ); ?></a>
				<?php $wishlist = palltheme_wishlist_data(); ?>
				<?php if ( $wishlist ) : ?>
					<a class="pt-offcanvas__link" href="<?php echo esc_url( $wishlist['url'] ); ?>"><?php palltheme_the_icon( 'heart' ); ?><?php esc_html_e( 'Wishlist', 'palltheme' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $phone ) : ?>
				<a class="pt-offcanvas__link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php palltheme_the_icon( 'phone' ); ?><?php echo esc_html( $phone ); ?></a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a class="pt-offcanvas__link" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php palltheme_the_icon( 'mail' ); ?><?php echo esc_html( antispambot( $email ) ); ?></a>
			<?php endif; ?>
			<?php if ( $cta_text ) : ?>
				<a class="pt-btn pt-btn--primary pt-btn--block" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
