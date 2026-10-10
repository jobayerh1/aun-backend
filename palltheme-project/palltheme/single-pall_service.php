<?php
/**
 * Single Service.
 *
 * Data comes from Palltheme Core custom fields. Building the page in
 * Elementor instead replaces the body (the hero band stays).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id = get_the_ID();
	get_template_part( 'template-parts/single/hero', null, array( 'eyebrow' => __( 'Service', 'palltheme' ) ) );
	?>
	<main id="primary" class="pt-main">
		<?php if ( palltheme_is_elementor_page() ) : ?>
			<?php the_content(); ?>
		<?php else : ?>
			<section class="pt-section">
				<div class="pt-container pt-split pt-split--content">
					<div class="pt-prose entry-content">
						<?php the_content(); ?>
					</div>
					<aside class="pt-split__aside">
						<?php get_template_part( 'template-parts/single/list-card', null, array( 'title' => __( 'What’s included', 'palltheme' ), 'items' => palltheme_lines( $id, 'features' ) ) ); ?>
						<?php get_template_part( 'template-parts/single/chips', null, array( 'title' => __( 'Technologies', 'palltheme' ), 'items' => palltheme_lines( $id, 'technologies' ) ) ); ?>
					</aside>
				</div>
			</section>

			<?php get_template_part( 'template-parts/single/benefits', null, array( 'items' => palltheme_pairs( $id, 'benefits' ) ) ); ?>
			<?php get_template_part( 'template-parts/single/gallery', null, array( 'ids' => palltheme_meta( $id, 'gallery' ) ) ); ?>
			<?php get_template_part( 'template-parts/single/faq', null, array( 'items' => palltheme_pairs( $id, 'faq' ) ) ); ?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_services' ), 'title' => __( 'Related services', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related-products', null, array( 'ids' => palltheme_meta( $id, 'related_products' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/cta' ); ?>
	</main>
	<?php
endwhile;

get_footer();
