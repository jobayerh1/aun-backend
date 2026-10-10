<?php
/**
 * Blog-style loop with optional sidebar (used by index, archive, search).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$has_sidebar = palltheme_mod( 'blog_sidebar' ) && is_active_sidebar( 'sidebar-blog' ) && ! is_search();
$layout      = (string) palltheme_mod( 'blog_layout' );
?>
<main id="primary" class="pt-main pt-section">
	<div class="pt-container <?php echo $has_sidebar ? 'pt-layout-sidebar' : ''; ?>">
		<div class="pt-layout-content">
			<?php if ( have_posts() ) : ?>
				<div class="pt-grid <?php echo 'list' === $layout ? 'pt-grid--list' : ( $has_sidebar ? 'pt-grid--2' : 'pt-grid--3' ); ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/content/card', get_post_type() === 'post' || ! palltheme_has_core() ? 'post' : 'cpt' );
					endwhile;
					?>
				</div>
				<?php palltheme_pagination(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/content/none' ); ?>
			<?php endif; ?>
		</div>
		<?php if ( $has_sidebar ) : ?>
			<?php get_sidebar(); ?>
		<?php endif; ?>
	</div>
</main>
