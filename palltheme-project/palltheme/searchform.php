<?php
/**
 * Search form.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

$uid = wp_unique_id( 'pt-s-' );
?>
<form role="search" method="get" class="pt-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Search for:', 'palltheme' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $uid ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'palltheme' ); ?>">
	<button type="submit" class="pt-btn pt-btn--primary"><?php palltheme_the_icon( 'search' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Search', 'palltheme' ); ?></span></button>
</form>
