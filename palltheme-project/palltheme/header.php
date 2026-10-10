<?php
/**
 * Site header.
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
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="pt-skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'palltheme' ); ?></a>

<?php
if ( ! palltheme_elementor_location( 'header' ) ) :
	get_template_part( 'template-parts/header/topbar' );
	?>
	<header id="masthead" class="pt-header" data-pt-header>
		<div class="pt-container pt-header__inner">
			<div class="pt-header__brand">
				<?php palltheme_logo(); ?>
			</div>

			<nav class="pt-nav" aria-label="<?php esc_attr_e( 'Primary', 'palltheme' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'pt-menu',
						'menu_id'        => 'pt-primary-menu',
						'depth'          => 3,
						'fallback_cb'    => 'palltheme_menu_fallback',
					)
				);
				?>
			</nav>

			<div class="pt-header__actions">
				<?php get_template_part( 'template-parts/header/actions' ); ?>
				<button type="button" class="pt-icon-btn pt-header__burger" aria-controls="pt-offcanvas" aria-expanded="false" data-pt-offcanvas-open>
					<?php palltheme_the_icon( 'menu' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'palltheme' ); ?></span>
				</button>
			</div>
		</div>
	</header>
	<?php
	get_template_part( 'template-parts/header/offcanvas' );
	get_template_part( 'template-parts/header/search-overlay' );
endif;
?>
<div id="page" class="pt-site">
