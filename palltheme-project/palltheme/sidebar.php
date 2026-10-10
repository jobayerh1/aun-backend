<?php
/**
 * Blog sidebar.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'sidebar-blog' ) ) {
	return;
}
?>
<aside class="pt-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'palltheme' ); ?>">
	<?php dynamic_sidebar( 'sidebar-blog' ); ?>
</aside>
