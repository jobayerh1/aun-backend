<?php
/**
 * Archives: categories, tags, authors, dates and Palltheme Core post types.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$eyebrow = '';
$desc    = get_the_archive_description();

if ( is_post_type_archive() ) {
	$pto     = get_queried_object();
	$eyebrow = get_bloginfo( 'name' );
	$desc    = $pto && ! empty( $pto->description ) ? esc_html( $pto->description ) : $desc;
	if ( function_exists( 'pallcore_setting' ) && $pto ) {
		$custom = pallcore_setting( 'intro_' . $pto->name );
		$desc   = $custom ? esc_html( $custom ) : $desc;
	}
} elseif ( is_category() || is_tag() ) {
	$eyebrow = is_category() ? __( 'Category', 'palltheme' ) : __( 'Tag', 'palltheme' );
} elseif ( is_author() ) {
	$eyebrow = __( 'Author', 'palltheme' );
}

palltheme_page_header( wp_strip_all_tags( get_the_archive_title() ), (string) $desc, $eyebrow );

if ( is_post_type_archive() || ( is_tax() && ! is_tax( array( 'product_cat', 'product_tag' ) ) ) ) :
	?>
	<main id="primary" class="pt-main pt-section">
		<div class="pt-container">
			<?php if ( have_posts() ) : ?>
				<div class="pt-grid pt-grid--3">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/content/card', 'cpt' );
					endwhile;
					?>
				</div>
				<?php palltheme_pagination(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/content/none' ); ?>
			<?php endif; ?>
		</div>
		<?php get_template_part( 'template-parts/single/cta' ); ?>
	</main>
	<?php
else :
	get_template_part( 'template-parts/content/loop', 'posts' );
endif;

get_footer();
