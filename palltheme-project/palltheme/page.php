<?php
/**
 * Default page. Elementor-built pages render edge-to-edge below the title
 * band; choose the "Full Width" template to control the whole page.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	// Pages assembled from Palltheme section shortcodes (e.g. the demo pages
	// when Elementor is inactive) render full width like Elementor pages.
	$is_sections = (bool) preg_match( '/\[pall_(?!contact_form)/', (string) get_post_field( 'post_content' ) );

	if ( palltheme_is_elementor_page() || $is_sections ) :
		// Elementor layout below a title band. Use the "Full Width" page
		// template to remove the band (the homepage does this).
		palltheme_page_header( get_the_title(), has_excerpt() ? esc_html( get_the_excerpt() ) : '' );
		?>
		<main id="primary" class="pt-main pt-main--elementor">
			<?php the_content(); ?>
		</main>
		<?php
		continue;
	endif;

	$is_woo = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
	palltheme_page_header( get_the_title(), has_excerpt() ? esc_html( get_the_excerpt() ) : '' );
	?>
	<main id="primary" class="pt-main pt-section pt-section--tight">
		<div class="pt-container <?php echo $is_woo ? '' : 'pt-container--narrow'; ?>">
			<article id="post-<?php the_ID(); ?>" <?php post_class( $is_woo ? '' : 'pt-prose entry-content' ); ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</article>
			<?php
			if ( ! $is_woo && ( comments_open() || get_comments_number() ) ) {
				comments_template();
			}
			?>
		</div>
	</main>
	<?php
endwhile;

get_footer();
