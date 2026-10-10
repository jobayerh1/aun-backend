<?php
/**
 * Nothing found.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="pt-empty pt-card">
	<span class="pt-empty__icon"><?php palltheme_the_icon( 'search' ); ?></span>
	<h2><?php esc_html_e( 'Nothing found', 'palltheme' ); ?></h2>
	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No results matched your search. Try different keywords or browse our categories.', 'palltheme' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'There is no content here yet.', 'palltheme' ); ?></p>
	<?php endif; ?>
	<?php get_search_form(); ?>
</section>
