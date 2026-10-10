<?php
/**
 * Chip list (technologies). Args: title, items.
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
	<ul class="pt-chips">
		<?php foreach ( $args['items'] as $item ) : ?>
			<li><span class="pt-chip"><?php echo esc_html( $item ); ?></span></li>
		<?php endforeach; ?>
	</ul>
</div>
