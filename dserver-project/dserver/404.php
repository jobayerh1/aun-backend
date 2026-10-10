<?php
/**
 * 404.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<section class="ds-404">
	<div class="ds-container">
		<p class="ds-404__code">404</p>
		<h1><?php esc_html_e( 'This server isn’t responding', 'dserver' ); ?></h1>
		<p><?php esc_html_e( 'The page you were looking for has moved or never existed. Try the homepage or our VPS plans.', 'dserver' ); ?></p>
		<p class="ds-404__actions">
			<a class="ds-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'dserver' ); ?></a>
			<a class="ds-button ds-button--ghost" href="<?php echo esc_url( home_url( '/vps-hosting/' ) ); ?>"><?php esc_html_e( 'View VPS plans', 'dserver' ); ?></a>
		</p>
	</div>
</section>
<?php
get_footer();
