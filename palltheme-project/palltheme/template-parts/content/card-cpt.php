<?php
/**
 * Card for Palltheme Core post types (services, solutions, case studies,
 * team, jobs...). Rendering is delegated to the plugin so the same card is
 * used by shortcodes, Elementor widgets and archives.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'pallcore_card' ) ) {
	echo pallcore_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
} else {
	get_template_part( 'template-parts/content/card', 'post' );
}
