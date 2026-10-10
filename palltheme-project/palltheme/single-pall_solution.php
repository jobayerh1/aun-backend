<?php
/**
 * Single Solution: problem → solution → architecture → implementation,
 * capabilities, outcomes, proof (case studies), services and products.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id         = get_the_ID();
	$industries = wp_get_post_terms( $id, 'pall_industry', array( 'fields' => 'names' ) );
	get_template_part(
		'template-parts/single/hero',
		null,
		array(
			'eyebrow' => is_wp_error( $industries ) || ! $industries ? __( 'Solution', 'palltheme' ) : implode( ' · ', $industries ),
		)
	);

	$story = array(
		'problem'        => __( 'The problem', 'palltheme' ),
		'approach'       => __( 'Our solution', 'palltheme' ),
		'architecture'   => __( 'Reference architecture', 'palltheme' ),
		'implementation' => __( 'Implementation', 'palltheme' ),
	);
	?>
	<main id="primary" class="pt-main">
		<?php if ( palltheme_is_elementor_page() ) : ?>
			<?php the_content(); ?>
		<?php else : ?>
			<section class="pt-section">
				<div class="pt-container pt-split pt-split--content">
					<div class="pt-story">
						<?php if ( trim( (string) get_the_content() ) ) : ?>
							<div class="pt-prose entry-content"><?php the_content(); ?></div>
						<?php endif; ?>
						<?php foreach ( $story as $key => $label ) : ?>
							<?php $text = (string) palltheme_meta( $id, $key ); ?>
							<?php if ( $text ) : ?>
								<div class="pt-story__step pt-reveal">
									<h2 class="pt-story__title"><?php echo esc_html( $label ); ?></h2>
									<div class="pt-prose"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<aside class="pt-split__aside">
						<?php get_template_part( 'template-parts/single/list-card', null, array( 'title' => __( 'Key capabilities', 'palltheme' ), 'items' => palltheme_lines( $id, 'features' ) ) ); ?>
						<?php get_template_part( 'template-parts/single/chips', null, array( 'title' => __( 'Technology', 'palltheme' ), 'items' => palltheme_lines( $id, 'technologies' ) ) ); ?>
					</aside>
				</div>
			</section>
			<?php get_template_part( 'template-parts/single/benefits', null, array( 'items' => palltheme_pairs( $id, 'benefits' ), 'title' => __( 'Outcomes you can expect', 'palltheme' ) ) ); ?>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_cases' ), 'title' => __( 'Proven in the field', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_services' ), 'title' => __( 'Services behind this solution', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related-products', null, array( 'ids' => palltheme_meta( $id, 'related_products' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/cta' ); ?>
	</main>
	<?php
endwhile;

get_footer();
