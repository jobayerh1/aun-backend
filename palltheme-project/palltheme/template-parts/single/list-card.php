<?php
/**
 * Checklist card. Args: title, items (string[]).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['items'] ) ) {
	return;
}
?>
<div class="pt-card pt-list-card">
	<h2 class="pt-list-card__title"><?php echo esc_html( $args['title'] ?? '' ); ?></h2>
	<ul class="pt-checklist">
		<?php foreach ( $args['items'] as $item ) : ?>
			<li><?php palltheme_the_icon( 'check' ); ?><span><?php echo esc_html( $item ); ?></span></li>
		<?php endforeach; ?>
	</ul>
</div>
