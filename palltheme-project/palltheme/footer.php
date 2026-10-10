<?php
/**
 * Site footer.
 *
 * Layouts (Customizer → Footer):
 *  - "mega": brand + newsletter + contact row, then seven link columns
 *    (Company, Services, Solutions, Products, Support, Resources, Legal).
 *  - "5" / "4": brand + four / three link columns.
 * Every column is a menu location (or a widget area) — nothing is hard-coded.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;
?>
</div><!-- #page -->

<?php
if ( ! palltheme_elementor_location( 'footer' ) ) :
	$layout      = (string) palltheme_mod( 'footer_layout' );
	$description = (string) palltheme_mod( 'footer_description' ) ?: get_bloginfo( 'description' );
	$social      = palltheme_social_links();
	$payments    = array_filter( array_map( 'trim', explode( ',', (string) palltheme_mod( 'footer_payments' ) ) ) );

	if ( 'mega' === $layout ) {
		$columns = array(
			1 => 'footer_company',
			2 => 'footer_services',
			3 => 'footer_solutions',
			4 => 'footer_products',
			5 => 'footer_support',
			6 => 'footer_resources',
			7 => 'footer_legal',
		);
	} else {
		$columns = array(
			1 => 'footer_company',
			2 => 'footer_products',
			3 => 'footer_support',
			4 => 'footer_legal',
		);
		if ( '4' === $layout ) {
			unset( $columns[4] );
		}
	}

	$newsletter = '';
	if ( is_active_sidebar( 'footer-newsletter' ) ) {
		ob_start();
		dynamic_sidebar( 'footer-newsletter' );
		$newsletter = (string) ob_get_clean();
	} elseif ( function_exists( 'pallcore_newsletter_form' ) ) {
		$newsletter = pallcore_newsletter_form();
	}
	?>
	<footer class="pt-footer pt-footer--<?php echo esc_attr( $layout ); ?>" data-scheme-lock="dark">
		<div class="pt-footer__glow" aria-hidden="true"></div>
		<div class="pt-container">
			<?php if ( 'mega' === $layout ) : ?>
				<div class="pt-footer__top">
					<div class="pt-footer__brand">
						<?php palltheme_logo(); ?>
						<?php if ( $description ) : ?>
							<p class="pt-footer__desc"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
						<?php get_template_part( 'template-parts/footer/social', null, array( 'social' => $social ) ); ?>
					</div>
					<?php if ( $newsletter ) : ?>
						<div class="pt-footer__newsletter">
							<h2 class="pt-footer__title"><?php echo esc_html( (string) palltheme_mod( 'footer_newsletter_title' ) ); ?></h2>
							<?php echo $newsletter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- widget/plugin output, escaped at source. ?>
						</div>
					<?php endif; ?>
					<div class="pt-footer__reach">
						<h2 class="pt-footer__title"><?php esc_html_e( 'Contact', 'palltheme' ); ?></h2>
						<?php get_template_part( 'template-parts/footer/contact' ); ?>
					</div>
				</div>
				<nav class="pt-footer__links" aria-label="<?php esc_attr_e( 'Footer', 'palltheme' ); ?>">
					<?php foreach ( $columns as $index => $location ) : ?>
						<div class="pt-footer__col"><?php palltheme_footer_column( $index, $location ); ?></div>
					<?php endforeach; ?>
				</nav>
			<?php else : ?>
				<div class="pt-footer__grid pt-footer__grid--<?php echo esc_attr( $layout ); ?>">
					<div class="pt-footer__brand">
						<?php palltheme_logo(); ?>
						<?php if ( $description ) : ?>
							<p class="pt-footer__desc"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
						<?php if ( $newsletter ) : ?>
							<div class="pt-footer__newsletter">
								<h2 class="pt-footer__title"><?php echo esc_html( (string) palltheme_mod( 'footer_newsletter_title' ) ); ?></h2>
								<?php echo $newsletter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
						<?php get_template_part( 'template-parts/footer/social', null, array( 'social' => $social ) ); ?>
					</div>
					<?php foreach ( $columns as $index => $location ) : ?>
						<div class="pt-footer__col">
							<?php palltheme_footer_column( $index, $location ); ?>
							<?php if ( 3 === $index ) : ?>
								<?php get_template_part( 'template-parts/footer/contact' ); ?>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="pt-footer__bottom">
				<p class="pt-footer__copy"><?php echo esc_html( palltheme_tokens( (string) palltheme_mod( 'footer_copyright' ) ) ); ?></p>
				<?php if ( $payments ) : ?>
					<ul class="pt-payments" aria-label="<?php esc_attr_e( 'Accepted payment methods', 'palltheme' ); ?>">
						<?php foreach ( $payments as $payment ) : ?>
							<li><?php echo esc_html( $payment ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</footer>
<?php endif; ?>

<button type="button" class="pt-to-top" data-pt-to-top aria-label="<?php esc_attr_e( 'Back to top', 'palltheme' ); ?>" hidden><?php palltheme_the_icon( 'up' ); ?></button>

<?php wp_footer(); ?>
</body>
</html>
