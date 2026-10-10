<?php
/**
 * Single Case Study: challenge → solution → implementation → results.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$id         = get_the_ID();
	$industries = wp_get_post_terms( $id, 'pall_industry', array( 'fields' => 'names' ) );
	$industry   = is_wp_error( $industries ) ? '' : implode( ', ', $industries );

	get_template_part(
		'template-parts/single/hero',
		null,
		array(
			'eyebrow' => __( 'Case study', 'palltheme' ),
			'meta'    => array(
				__( 'Client', 'palltheme' )   => (string) palltheme_meta( $id, 'client' ),
				__( 'Industry', 'palltheme' ) => $industry,
				__( 'Location', 'palltheme' ) => (string) palltheme_meta( $id, 'location' ),
				__( 'Duration', 'palltheme' ) => (string) palltheme_meta( $id, 'duration' ),
			),
		)
	);

	$results = palltheme_pairs( $id, 'results' );
	$story   = array(
		'challenge'      => __( 'The challenge', 'palltheme' ),
		'solution'       => __( 'Our solution', 'palltheme' ),
		'implementation' => __( 'Implementation', 'palltheme' ),
	);
	?>
	<main id="primary" class="pt-main">
		<?php if ( $results ) : ?>
			<section class="pt-section pt-section--tight">
				<div class="pt-container">
					<div class="pt-stats pt-stats--cards">
						<?php foreach ( $results as $row ) : ?>
							<div class="pt-stat pt-card pt-reveal">
								<span class="pt-stat__value" data-pt-count="<?php echo esc_attr( $row[0] ); ?>"><?php echo esc_html( $row[0] ); ?></span>
								<span class="pt-stat__label"><?php echo esc_html( $row[1] ?? '' ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( palltheme_is_elementor_page() ) : ?>
			<?php the_content(); ?>
		<?php else : ?>
			<section class="pt-section pt-section--tight">
				<div class="pt-container pt-split pt-split--content">
					<div class="pt-story">
						<?php foreach ( $story as $key => $label ) : ?>
							<?php $text = (string) palltheme_meta( $id, $key ); ?>
							<?php if ( $text ) : ?>
								<div class="pt-story__step pt-reveal">
									<h2 class="pt-story__title"><?php echo esc_html( $label ); ?></h2>
									<div class="pt-prose"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( trim( (string) get_the_content() ) ) : ?>
							<div class="pt-prose entry-content"><?php the_content(); ?></div>
						<?php endif; ?>
					</div>
					<aside class="pt-split__aside">
						<?php get_template_part( 'template-parts/single/chips', null, array( 'title' => __( 'Technologies used', 'palltheme' ), 'items' => palltheme_lines( $id, 'technologies' ) ) ); ?>
						<?php
						$quote = (string) palltheme_meta( $id, 'testimonial' );
						if ( $quote ) :
							?>
							<figure class="pt-card pt-quote-card">
								<blockquote><p><?php echo esc_html( $quote ); ?></p></blockquote>
								<figcaption><?php echo esc_html( (string) palltheme_meta( $id, 'testimonial_author' ) ); ?></figcaption>
							</figure>
						<?php endif; ?>
					</aside>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$video = (string) palltheme_meta( $id, 'video' );
		if ( $video ) :
			?>
			<section class="pt-section pt-section--tight">
				<div class="pt-container pt-container--narrow"><div class="pt-embed"><?php echo wp_oembed_get( $video ) ?: wp_video_shortcode( array( 'src' => esc_url( $video ), 'preload' => 'none' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
			</section>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/single/gallery', null, array( 'ids' => palltheme_meta( $id, 'gallery' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_services' ), 'title' => __( 'Services delivered', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( $id, 'related_solutions' ), 'title' => __( 'Related solutions', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/cta' ); ?>
	</main>
	<?php
endwhile;

get_footer();
