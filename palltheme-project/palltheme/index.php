<?php
/**
 * Fallback template / blog index.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_for_posts = (int) get_option( 'page_for_posts' );
$title          = is_home() && $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Insights & Articles', 'palltheme' );
$description    = is_home() && $page_for_posts ? get_the_excerpt( $page_for_posts ) : '';

palltheme_page_header( $title, $description, __( 'Blog', 'palltheme' ) );
get_template_part( 'template-parts/content/loop', 'posts' );

get_footer();
