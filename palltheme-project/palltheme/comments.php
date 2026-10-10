<?php
/**
 * Comments.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="pt-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="pt-section-title">
			<?php
			$count = get_comments_number();
			/* translators: %s: comment count. */
			echo esc_html( sprintf( _n( '%s comment', '%s comments', $count, 'palltheme' ), number_format_i18n( $count ) ) );
			?>
		</h2>
		<ol class="pt-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="pt-comments__closed"><?php esc_html_e( 'Comments are closed.', 'palltheme' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_submit'       => 'pt-btn pt-btn--primary',
			'title_reply_before' => '<h2 id="reply-title" class="pt-section-title">',
			'title_reply_after'  => '</h2>',
		)
	);
	?>
</section>
