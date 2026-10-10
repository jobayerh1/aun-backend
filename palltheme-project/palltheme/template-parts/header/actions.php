<?php
/**
 * Header action icons: search, appearance, wishlist, account, cart, CTA.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$has_woo  = class_exists( 'WooCommerce' );
$wishlist = palltheme_wishlist_data();
$cta_text = (string) palltheme_mod( 'header_cta_text' );
$cta_url  = (string) palltheme_mod( 'header_cta_url' );
if ( $cta_text && ! $cta_url ) {
	$contact = get_page_by_path( 'contact' );
	$cta_url = $contact ? get_permalink( $contact ) : home_url( '/' );
}
?>
<?php if ( palltheme_mod( 'header_search' ) ) : ?>
	<button type="button" class="pt-icon-btn" data-pt-search-open aria-controls="pt-search" aria-expanded="false">
		<?php palltheme_the_icon( 'search' ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Search', 'palltheme' ); ?></span>
	</button>
<?php endif; ?>

<?php if ( palltheme_mod( 'dark_mode_toggle' ) ) : ?>
	<button type="button" class="pt-icon-btn pt-scheme-toggle" data-pt-scheme-toggle>
		<span class="pt-scheme-toggle__icon pt-scheme-toggle__icon--light"><?php palltheme_the_icon( 'sun' ); ?></span>
		<span class="pt-scheme-toggle__icon pt-scheme-toggle__icon--dark"><?php palltheme_the_icon( 'moon' ); ?></span>
		<span class="pt-scheme-toggle__icon pt-scheme-toggle__icon--system"><?php palltheme_the_icon( 'monitor' ); ?></span>
		<span class="screen-reader-text" data-pt-scheme-label><?php esc_html_e( 'Change color scheme', 'palltheme' ); ?></span>
	</button>
<?php endif; ?>

<?php if ( $wishlist && palltheme_mod( 'header_wishlist' ) ) : ?>
	<a class="pt-icon-btn pt-hide-mobile" href="<?php echo esc_url( $wishlist['url'] ); ?>">
		<?php palltheme_the_icon( 'heart' ); ?>
		<span class="pt-badge pt-wishlist-count" data-pt-wishlist-count <?php echo $wishlist['count'] ? '' : 'hidden'; ?>><?php echo esc_html( (string) $wishlist['count'] ); ?></span>
		<span class="screen-reader-text"><?php esc_html_e( 'Wishlist', 'palltheme' ); ?></span>
	</a>
<?php endif; ?>

<?php if ( $has_woo && palltheme_mod( 'header_account' ) ) : ?>
	<a class="pt-icon-btn pt-hide-mobile" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
		<?php palltheme_the_icon( 'user' ); ?>
		<span class="screen-reader-text"><?php is_user_logged_in() ? esc_html_e( 'My account', 'palltheme' ) : esc_html_e( 'Sign in', 'palltheme' ); ?></span>
	</a>
<?php endif; ?>

<?php if ( $has_woo && palltheme_mod( 'header_cart' ) ) : ?>
	<a class="pt-icon-btn pt-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>" data-pt-minicart-open aria-controls="pt-minicart" aria-expanded="false">
		<?php palltheme_the_icon( 'cart' ); ?>
		<?php palltheme_cart_count_html(); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Cart', 'palltheme' ); ?></span>
	</a>
<?php endif; ?>

<?php if ( $cta_text ) : ?>
	<a class="pt-btn pt-btn--primary pt-header__cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
<?php endif; ?>
