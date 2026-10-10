<?php
/**
 * Template Name: Palltheme — Full Width (header + footer, no title)
 * Template Post Type: page, post, pall_service, pall_solution, pall_case_study
 *
 * Best choice for Elementor-designed pages.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="pt-main pt-main--elementor">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
