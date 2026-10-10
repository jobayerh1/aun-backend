<?php
/**
 * Footer social icons. Args: social (network => url).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$social = $args['social'] ?? array();
if ( ! $social ) {
	return;
}
?>
<ul class="pt-social">
	<?php foreach ( $social as $network => $url ) : ?>
		<li><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( palltheme_social_label( $network ) ); ?>"><?php echo palltheme_social_icon( $network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
	<?php endforeach; ?>
</ul>
