<?php
/**
 * Related posts of any Palltheme type. Args: ids (int[]), title.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$ids = array_filter( array_map( 'absint', (array) ( $args['ids'] ?? array() ) ) );
if ( ! $ids || ! function_exists( 'pallcore_card' ) ) {
	return;
}

$posts = get_posts(
	array(
		'post_type'      => 'any',
		'post__in'       => $ids,
		'orderby'        => 'post__in',
		'posts_per_page' => 6,
		'post_status'    => 'publish',
	)
);
if ( ! $posts ) {
	return;
}
?>
<section class="pt-section pt-section--alt">
	<div class="pt-container">
		<div class="pt-section-head">
			<h2 class="pt-section-title"><?php echo esc_html( $args['title'] ?? __( 'Related', 'palltheme' ) ); ?></h2>
		</div>
		<div class="pt-grid pt-grid--3">
			<?php
			foreach ( $posts as $related ) {
				echo pallcore_card( $related ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
	</div>
</section>
