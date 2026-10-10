<?php
/**
 * Single post.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<section class="ds-pagehead">
		<div class="ds-container ds-container--narrow">
			<p class="ds-pagehead__meta"><?php echo esc_html( get_the_date() ); ?> · <?php the_category( ', ' ); ?></p>
			<h1><?php the_title(); ?></h1>
		</div>
	</section>
	<article <?php post_class( 'ds-container ds-container--narrow ds-section' ); ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="ds-featured"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>
		<div class="ds-prose"><?php the_content(); ?></div>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;
get_footer();
