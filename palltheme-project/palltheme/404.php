<?php
/**
 * 404 — Signal Lost.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$contact = get_page_by_path( 'contact' ) ?: get_page_by_path( 'support' );
?>
<main id="primary" class="pt-main pt-404">
	<div class="pt-404__bg" aria-hidden="true">
		<svg class="pt-404__signal" viewBox="0 0 600 200" preserveAspectRatio="none">
			<path d="M0 100 H170 L190 40 L215 160 L240 70 L260 120 L280 100 H330" />
			<path class="pt-404__flat" d="M330 100 H600" />
		</svg>
	</div>
	<div class="pt-container pt-404__inner">
		<p class="pt-404__code" aria-hidden="true">404</p>
		<h1 class="pt-404__title"><?php echo esc_html( (string) palltheme_mod( 'notfound_title' ) ); ?></h1>
		<p class="pt-404__text"><?php echo esc_html( (string) palltheme_mod( 'notfound_text' ) ); ?></p>
		<div class="pt-404__search"><?php get_search_form(); ?></div>
		<div class="pt-btn-row pt-btn-row--center">
			<a class="pt-btn pt-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php palltheme_the_icon( 'home' ); ?><?php esc_html_e( 'Return Home', 'palltheme' ); ?></a>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a class="pt-btn pt-btn--ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php palltheme_the_icon( 'box' ); ?><?php esc_html_e( 'Browse Products', 'palltheme' ); ?></a>
			<?php endif; ?>
			<?php if ( $contact ) : ?>
				<a class="pt-btn pt-btn--ghost" href="<?php echo esc_url( get_permalink( $contact ) ); ?>"><?php palltheme_the_icon( 'support' ); ?><?php esc_html_e( 'Contact Support', 'palltheme' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
