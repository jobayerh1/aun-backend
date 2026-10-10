<?php
/**
 * Image gallery. Args: ids (int[]).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$ids = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );
if ( ! $ids ) {
	return;
}
?>
<section class="pt-section pt-section--tight">
	<div class="pt-container">
		<div class="pt-gallery">
			<?php foreach ( $ids as $img ) : ?>
				<a class="pt-gallery__item" href="<?php echo esc_url( (string) wp_get_attachment_image_url( $img, 'full' ) ); ?>" target="_blank" rel="noopener">
					<?php echo wp_get_attachment_image( $img, 'palltheme-card' ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
