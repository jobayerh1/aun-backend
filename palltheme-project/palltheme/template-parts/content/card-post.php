<?php
/**
 * Post card.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$cats = get_the_category();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pt-card pt-post-card pt-reveal' ); ?>>
	<a class="pt-post-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'palltheme-card', array( 'alt' => '' ) ); ?>
		<?php else : ?>
			<span class="pt-media-fallback"><?php palltheme_the_icon( 'bolt' ); ?></span>
		<?php endif; ?>
	</a>
	<div class="pt-post-card__body">
		<?php if ( $cats ) : ?>
			<a class="pt-tag" href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
		<?php endif; ?>
		<h2 class="pt-post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="pt-post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
		<div class="pt-post-card__meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php if ( 'post' === get_post_type() && palltheme_mod( 'blog_reading_time' ) ) : ?>
				<span aria-hidden="true">•</span>
				<?php /* translators: %d: minutes. */ ?>
				<span><?php echo esc_html( sprintf( __( '%d min read', 'palltheme' ), palltheme_reading_time() ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>
