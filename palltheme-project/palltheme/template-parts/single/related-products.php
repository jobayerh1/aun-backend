<?php
/**
 * Related WooCommerce products. Args: ids (int[]).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$ids = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );
if ( ! $ids || ! class_exists( 'WooCommerce' ) ) {
	return;
}
?>
<section class="pt-section">
	<div class="pt-container">
		<div class="pt-section-head pt-section-head--row">
			<h2 class="pt-section-title"><?php esc_html_e( 'Recommended hardware', 'palltheme' ); ?></h2>
			<a class="pt-link-arrow" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Visit the store', 'palltheme' ); ?><?php palltheme_the_icon( 'arrow' ); ?></a>
		</div>
		<?php echo do_shortcode( '[products ids="' . esc_attr( implode( ',', $ids ) ) . '" columns="4" orderby="post__in"]' ); ?>
	</div>
</section>
