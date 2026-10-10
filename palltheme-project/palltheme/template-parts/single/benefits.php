<?php
/**
 * Benefits grid. Args: items (array of [title, text]), title.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['items'] ) ) {
	return;
}
?>
<section class="pt-section pt-section--alt">
	<div class="pt-container">
		<div class="pt-section-head">
			<p class="pt-eyebrow"><?php esc_html_e( 'Why it matters', 'palltheme' ); ?></p>
			<h2 class="pt-section-title"><?php echo esc_html( $args['title'] ?? __( 'Business benefits', 'palltheme' ) ); ?></h2>
		</div>
		<div class="pt-grid pt-grid--3">
			<?php foreach ( $args['items'] as $i => $row ) : ?>
				<div class="pt-card pt-feature pt-reveal">
					<span class="pt-feature__num"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="pt-feature__title"><?php echo esc_html( $row[0] ?? '' ); ?></h3>
					<?php if ( ! empty( $row[1] ) ) : ?>
						<p><?php echo esc_html( $row[1] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
