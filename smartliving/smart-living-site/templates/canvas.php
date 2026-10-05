<?php
/**
 * The full-page template. The theme renders none of this.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sl_page = sl_current_page();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php // No maximum-scale: the old template blocked pinch-zoom, which is an accessibility failure. ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'sl-site sl-page-' . esc_attr( $sl_page ) ); ?>>

<?php sl_header(); ?>

<main id="sl-main">
	<?php
	switch ( $sl_page ) {
		case 'home':
			sl_render_home();
			break;
		case 'about':
			sl_render_about();
			break;
		case 'brands':
			sl_render_brands();
			break;
		case 'corporate':
			sl_render_corporate();
			break;
		case 'dealers':
			sl_render_dealers();
			break;
		case 'contact':
			sl_render_contact();
			break;
		case '404':
			sl_render_404();
			break;
	}
	?>
</main>

<?php
sl_footer();
wp_footer();
?>
</body>
</html>
