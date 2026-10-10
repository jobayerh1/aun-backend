<?php
/**
 * Footer.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
$phone   = (string) dserver_opt( 'phone' );
$email   = (string) dserver_opt( 'email' );
$address = (string) dserver_opt( 'address' );
$menu    = static function ( string $location, string $fallback ): void {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}
	$locations = get_nav_menu_locations();
	$obj       = wp_get_nav_menu_object( $locations[ $location ] );
	$title     = $obj ? trim( (string) preg_replace( '/^\s*footer\s*[:\-–—]\s*/iu', '', $obj->name ) ) : $fallback;
	echo '<div class="ds-footer__col"><h2 class="ds-footer__title">' . esc_html( $title ?: $fallback ) . '</h2>';
	wp_nav_menu( array( 'theme_location' => $location, 'container' => false, 'menu_class' => 'ds-footer__menu', 'depth' => 1, 'fallback_cb' => false ) );
	echo '</div>';
};
?>
</main>
<footer class="ds-footer">
	<div class="ds-container">
		<div class="ds-footer__grid">
			<div class="ds-footer__brand">
				<?php dserver_logo( true ); ?>
				<p><?php echo esc_html( (string) dserver_opt( 'footer_text' ) ); ?></p>
				<ul class="ds-social">
					<?php foreach ( array( 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X', 'github' => 'GitHub' ) as $key => $label ) : ?>
						<?php if ( dserver_opt( $key ) ) : ?>
							<li><a href="<?php echo esc_url( (string) dserver_opt( $key ) ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo dserver_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php $menu( 'footer_services', __( 'Services', 'dserver' ) ); ?>
			<?php $menu( 'footer_company', __( 'Company', 'dserver' ) ); ?>
			<div class="ds-footer__col">
				<h2 class="ds-footer__title"><?php esc_html_e( 'Contact', 'dserver' ); ?></h2>
				<ul class="ds-footer__contact">
					<?php if ( $phone ) : ?><li><?php echo dserver_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
					<?php if ( $email ) : ?><li><?php echo dserver_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li><?php endif; ?>
					<?php if ( $address ) : ?><li><?php echo dserver_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo nl2br( esc_html( $address ) ); ?></span></li><?php endif; ?>
				</ul>
			</div>
		</div>
		<div class="ds-footer__bottom">
			<p><?php echo esc_html( str_replace( array( '{year}', '{site}' ), array( wp_date( 'Y' ), get_bloginfo( 'name' ) ), (string) dserver_opt( 'copyright' ) ) ); ?></p>
			<?php wp_nav_menu( array( 'theme_location' => 'footer_legal', 'container' => false, 'menu_class' => 'ds-legal', 'depth' => 1, 'fallback_cb' => false ) ); ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
