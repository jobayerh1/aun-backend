<?php
/**
 * Template Name: Palltheme — Blank Canvas (no header/footer)
 * Template Post Type: page
 *
 * For landing pages and the maintenance page built in Elementor.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pt-canvas' ); ?>>
<?php wp_body_open(); ?>
<main id="primary" class="pt-main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
