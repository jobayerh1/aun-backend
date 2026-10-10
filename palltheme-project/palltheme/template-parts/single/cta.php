<?php
/**
 * Closing call-to-action band. Uses per-post CTA fields when set,
 * otherwise the global CTA from Business Settings.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$id    = (int) get_the_ID();
$title = palltheme_meta( $id, 'cta_title' ) ?: palltheme_biz( 'cta_title', __( 'Ready to modernize your infrastructure?', 'palltheme' ) );
$text  = palltheme_meta( $id, 'cta_text' ) ?: palltheme_biz( 'cta_text', __( 'Talk to our engineers for a free assessment and a tailored proposal.', 'palltheme' ) );
$url   = palltheme_meta( $id, 'cta_url' ) ?: palltheme_biz( 'quote_url', home_url( '/contact/' ) );
$label = palltheme_biz( 'cta_button', __( 'Get a Quote', 'palltheme' ) );
$phone = palltheme_biz( 'phone' );
?>
<section class="pt-section pt-section--tight">
	<div class="pt-container">
		<div class="pt-cta-band" data-scheme-lock="dark">
			<div class="pt-cta-band__glow" aria-hidden="true"></div>
			<div class="pt-cta-band__text">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $text ); ?></p>
			</div>
			<div class="pt-btn-row">
				<a class="pt-btn pt-btn--accent" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?><?php palltheme_the_icon( 'arrow' ); ?></a>
				<?php if ( $phone ) : ?>
					<a class="pt-btn pt-btn--glass" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php palltheme_the_icon( 'phone' ); ?><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
