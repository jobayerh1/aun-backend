<?php
/**
 * Search results across products, services, solutions, posts and case studies.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$count = (int) $wp_query->found_posts;
/* translators: 1: result count, 2: query. */
$title = sprintf( _n( '%1$d result for “%2$s”', '%1$d results for “%2$s”', $count, 'palltheme' ), $count, get_search_query() );

palltheme_page_header( $title, '', __( 'Search', 'palltheme' ) );
?>
<main id="primary" class="pt-main pt-section">
	<div class="pt-container">
		<div class="pt-search-page__form"><?php get_search_form(); ?></div>
		<?php if ( have_posts() ) : ?>
			<div class="pt-grid pt-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					$type = get_post_type();
					if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
						get_template_part( 'template-parts/content/card', 'product' );
					} elseif ( 'post' === $type || 'page' === $type ) {
						get_template_part( 'template-parts/content/card', 'post' );
					} else {
						get_template_part( 'template-parts/content/card', 'cpt' );
					}
				endwhile;
				?>
			</div>
			<?php palltheme_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
