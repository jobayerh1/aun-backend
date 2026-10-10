<?php
/**
 * Menu enhancements: mega menus, descriptions, accessible submenu toggles.
 *
 * Mega menu: in Appearance → Menus, open "Screen Options", enable
 * "CSS Classes" and add the class `mega` to a top-level item. Its children
 * become columns; grandchildren become links inside each column.
 * Item descriptions (enable "Description" in Screen Options) are shown
 * under links inside mega menus.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add a submenu toggle button after parent links (used by mobile + keyboard).
 *
 * @param string   $output Item output.
 * @param WP_Post  $item   Menu item.
 * @param int      $depth  Depth.
 * @param stdClass $args   Args.
 */
function palltheme_menu_toggle( string $output, $item, int $depth, $args ): string {
	if ( ! isset( $args->theme_location ) || ! in_array( $args->theme_location, array( 'primary', 'mobile' ), true ) ) {
		return $output;
	}
	if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		$output .= sprintf(
			'<button type="button" class="pt-submenu-toggle" aria-expanded="false" aria-label="%1$s">%2$s</button>',
			/* translators: %s: menu item title. */
			esc_attr( sprintf( __( 'Show submenu for %s', 'palltheme' ), wp_strip_all_tags( $item->title ) ) ),
			palltheme_icon( 'chevron' )
		);
	}
	return $output;
}
add_filter( 'walker_nav_menu_start_el', 'palltheme_menu_toggle', 10, 4 );

/**
 * Show item descriptions inside mega menus.
 *
 * @param string   $title Title.
 * @param WP_Post  $item  Item.
 * @param stdClass $args  Args.
 * @param int      $depth Depth.
 */
function palltheme_menu_description( string $title, $item, $args, int $depth ): string {
	if ( $depth > 0 && ! empty( $item->description ) && isset( $args->theme_location ) && 'primary' === $args->theme_location ) {
		$title = '<span class="pt-menu-title">' . $title . '</span><span class="pt-menu-desc">' . esc_html( $item->description ) . '</span>';
	}
	return $title;
}
add_filter( 'nav_menu_item_title', 'palltheme_menu_description', 10, 4 );

/**
 * Normalise the `mega` class.
 *
 * @param string[] $classes Classes.
 * @param WP_Post  $item    Item.
 * @param stdClass $args    Args.
 * @param int      $depth   Depth.
 */
function palltheme_menu_classes( array $classes, $item, $args, int $depth ): array {
	if ( 0 === $depth && in_array( 'mega', $classes, true ) ) {
		$classes[] = 'pt-mega';
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'palltheme_menu_classes', 10, 4 );

/**
 * Fallback when no menu is assigned: list top-level pages.
 *
 * @param array $args Menu args.
 */
function palltheme_menu_fallback( array $args = array() ): void {
	if ( ! current_user_can( 'edit_theme_options' ) && empty( $args['show_pages'] ) ) {
		return;
	}
	echo '<ul class="' . esc_attr( $args['menu_class'] ?? 'pt-menu' ) . '">';
	wp_list_pages(
		array(
			'title_li' => '',
			'depth'    => 1,
			'number'   => 6,
		)
	);
	echo '</ul>';
}

/**
 * Render a footer column: widget area if used, otherwise the footer menu.
 *
 * @param int    $index    Widget column number.
 * @param string $location Menu location.
 */
function palltheme_footer_column( int $index, string $location ): void {
	if ( is_active_sidebar( 'footer-' . $index ) ) {
		dynamic_sidebar( 'footer-' . $index );
		return;
	}
	if ( ! has_nav_menu( $location ) ) {
		return;
	}
	$locations = get_nav_menu_locations();
	$menu      = wp_get_nav_menu_object( $locations[ $location ] );
	// The menu name is the column heading; a "Footer: " prefix (handy for telling menus apart in the admin) is dropped.
	$title = $menu ? trim( (string) preg_replace( '/^\s*footer\s*[:\-–—]\s*/iu', '', $menu->name ) ) : '';
	echo '<h2 class="pt-footer__title">' . esc_html( $title ) . '</h2>';
	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => 'nav',
			'container_aria_label' => $title,
			'menu_class'     => 'pt-footer__menu',
			'depth'          => 1,
			'fallback_cb'    => false,
		)
	);
}
