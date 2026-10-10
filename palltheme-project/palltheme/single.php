<?php
/**
 * Single blog post.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	if ( palltheme_is_elementor_page() ) {
		echo '<main id="primary" class="pt-main">';
		the_content();
		echo '</main>';
		continue;
	}

	$has_sidebar = palltheme_mod( 'blog_sidebar' ) && is_active_sidebar( 'sidebar-blog' );
	$cats        = get_the_category();
	?>
	<main id="primary" class="pt-main">
		<header class="pt-article-header">
			<div class="pt-page-header__bg" aria-hidden="true"></div>
			<div class="pt-container pt-container--narrow">
				<?php palltheme_breadcrumbs(); ?>
				<?php if ( $cats ) : ?>
					<a class="pt-tag" href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
				<?php endif; ?>
				<h1 class="pt-article-header__title"><?php the_title(); ?></h1>
				<?php palltheme_post_meta(); ?>
			</div>
		</header>

		<div class="pt-container <?php echo $has_sidebar ? 'pt-layout-sidebar' : 'pt-container--narrow'; ?> pt-section pt-section--tight">
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'pt-layout-content pt-article' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="pt-article__media">
						<?php the_post_thumbnail( 'palltheme-wide', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
					</figure>
				<?php endif; ?>

				<div class="pt-prose entry-content">
					<?php
					the_content();
					wp_link_pages(
						array(
							'before' => '<nav class="pt-page-links">' . esc_html__( 'Pages:', 'palltheme' ),
							'after'  => '</nav>',
						)
					);
					?>
				</div>

				<footer class="pt-article__footer">
					<?php
					$tags = get_the_tag_list( '<ul class="pt-chips"><li>', '</li><li>', '</li></ul>' );
					if ( $tags && ! is_wp_error( $tags ) ) {
						echo wp_kses_post( str_replace( '<a ', '<a class="pt-chip" ', $tags ) );
					}
					palltheme_share_links();
					?>
				</footer>

				<?php palltheme_author_box(); ?>

				<?php
				the_post_navigation(
					array(
						'prev_text' => '<span class="pt-eyebrow">' . esc_html__( 'Previous', 'palltheme' ) . '</span><span class="pt-post-nav__title">%title</span>',
						'next_text' => '<span class="pt-eyebrow">' . esc_html__( 'Next', 'palltheme' ) . '</span><span class="pt-post-nav__title">%title</span>',
						'class'     => 'pt-post-nav',
					)
				);

				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			</article>
			<?php if ( $has_sidebar ) : ?>
				<?php get_sidebar(); ?>
			<?php endif; ?>
		</div>

		<?php get_template_part( 'template-parts/single/related', null, array( 'ids' => palltheme_meta( get_the_ID(), 'related_services' ), 'title' => __( 'Services related to this article', 'palltheme' ) ) ); ?>
		<?php get_template_part( 'template-parts/single/related-products', null, array( 'ids' => palltheme_meta( get_the_ID(), 'related_products' ) ) ); ?>

		<div class="pt-container pt-section pt-section--tight">
			<?php palltheme_related_posts(); ?>
		</div>
	</main>
	<?php
endwhile;

get_footer();
