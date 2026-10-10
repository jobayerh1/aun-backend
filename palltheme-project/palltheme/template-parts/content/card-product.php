<?php
/**
 * Compact product card for non-shop contexts (search results).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$product = wc_get_product( get_the_ID() );
if ( ! $product ) {
	return;
}
?>
<article <?php post_class( 'pt-card pt-post-card pt-reveal' ); ?>>
	<a class="pt-post-card__media pt-post-card__media--contain" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
	<div class="pt-post-card__body">
		<span class="pt-tag"><?php esc_html_e( 'Product', 'palltheme' ); ?></span>
		<h2 class="pt-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="pt-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
	</div>
</article>
