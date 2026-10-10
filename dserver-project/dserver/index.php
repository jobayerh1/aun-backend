<?php
/**
 * Fallback template: blog, archives, search.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="ds-pagehead">
	<div class="ds-container">
		<h1><?php
		if ( is_home() ) {
			echo esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) ?: __( 'Blog', 'dserver' ) );
		} elseif ( is_search() ) {
			/* translators: %s: search term */
			printf( esc_html__( 'Search: %s', 'dserver' ), esc_html( get_search_query() ) );
		} else {
			the_archive_title();
		}
		?></h1>
		<?php the_archive_description( '<div class="ds-pagehead__lead">', '</div>' ); ?>
	</div>
</section>
<div class="ds-container ds-section">
	<?php if ( have_posts() ) : ?>
		<div class="ds-posts">
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'ds-post-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="ds-post-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'medium_large' ); ?></a>
					<?php endif; ?>
					<div class="ds-post-card__body">
						<p class="ds-post-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<a class="ds-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'dserver' ); ?> <?php echo dserver_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'dserver' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
