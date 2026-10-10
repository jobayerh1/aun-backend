<?php
/**
 * Pages. Elementor pages render full-width; others get a title band and a readable column.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	if ( get_post_meta( get_the_ID(), '_elementor_edit_mode', true ) ) :
		the_content();
	else :
		?>
		<section class="ds-pagehead"><div class="ds-container"><h1><?php the_title(); ?></h1></div></section>
		<div class="ds-container ds-container--narrow ds-section ds-prose"><?php the_content(); ?></div>
		<?php
	endif;
endwhile;
get_footer();
