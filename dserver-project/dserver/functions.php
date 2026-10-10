<?php
/**
 * DServer theme bootstrap.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;

define( 'DSERVER_DIR', get_template_directory() . '/' );
define( 'DSERVER_URI', get_template_directory_uri() . '/' );

require DSERVER_DIR . 'inc/customizer.php';
require DSERVER_DIR . 'inc/contact-form.php';
require DSERVER_DIR . 'inc/setup-content.php';

/**
 * Theme supports and menus.
 */
function dserver_setup(): void {
	load_theme_textdomain( 'dserver', DSERVER_DIR . 'languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 52, 'width' => 250, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	register_nav_menus(
		array(
			'primary'        => __( 'Header menu', 'dserver' ),
			'footer_services' => __( 'Footer: Services', 'dserver' ),
			'footer_company' => __( 'Footer: Company', 'dserver' ),
			'footer_legal'   => __( 'Footer: Legal (bottom bar)', 'dserver' ),
		)
	);
}
add_action( 'after_setup_theme', 'dserver_setup' );

/**
 * Content width for embeds.
 */
function dserver_content_width(): void {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'dserver_content_width', 0 );

/**
 * Styles and scripts.
 */
function dserver_assets(): void {
	$ver = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'dserver-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'dserver', DSERVER_URI . 'assets/css/main.css', array(), $ver . '.' . filemtime( DSERVER_DIR . 'assets/css/main.css' ) );
	wp_enqueue_script( 'dserver', DSERVER_URI . 'assets/js/main.js', array(), $ver . '.' . filemtime( DSERVER_DIR . 'assets/js/main.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'dserver_assets' );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls     URLs.
 * @param string $relation Relation.
 */
function dserver_resource_hints( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'dserver_resource_hints', 10, 2 );

/**
 * Widget area for the blog sidebar.
 */
function dserver_widgets(): void {
	register_sidebar(
		array(
			'name'          => __( 'Blog sidebar', 'dserver' ),
			'id'            => 'blog',
			'before_widget' => '<section id="%1$s" class="ds-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="ds-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'dserver_widgets' );

/**
 * Body class for pages built with Elementor (full-width, no title band).
 *
 * @param string[] $classes Classes.
 */
function dserver_body_class( array $classes ): array {
	if ( is_singular() && get_post_meta( get_the_ID(), '_elementor_edit_mode', true ) ) {
		$classes[] = 'ds-elementor-page';
	}
	return $classes;
}
add_filter( 'body_class', 'dserver_body_class' );

/**
 * Small inline SVG icon set used by the header/footer.
 *
 * @param string $name Icon.
 */
function dserver_icon( string $name ): string {
	$paths = array(
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v7h4v-7h3l1-4h-4V8z"/>',
		'linkedin' => '<path d="M6 9v10M6 5v.01M10 19v-6a3 3 0 0 1 6 0v6M10 10v9"/>',
		'x'        => '<path d="M4 4l16 16M20 4 4 20"/>',
		'github'   => '<path d="M9 19c-4 1.5-4-2-6-2m12 4v-3.5a3 3 0 0 0-1-2.5c3 0 6-1.5 6-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.6 1.3a12.3 12.3 0 0 0-6.4 0C6.1 2.3 5 2.6 5 2.6a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 3.6 9c0 4.5 3 6 6 6a3 3 0 0 0-1 2.5V21"/>',
	);
	return '<svg class="ds-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Logo (custom logo or text fallback).
 */
function dserver_logo( bool $white = false ): void {
	$white_id = (int) get_theme_mod( 'ds_logo_white' );
	if ( $white && $white_id ) {
		printf( '<a class="custom-logo-link" href="%s" rel="home">%s</a>', esc_url( home_url( '/' ) ), wp_get_attachment_image( $white_id, 'full', false, array( 'class' => 'custom-logo', 'alt' => get_bloginfo( 'name' ) ) ) );
		return;
	}
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf( '<a class="ds-logo-text" href="%s" rel="home">%s</a>', esc_url( home_url( '/' ) ), esc_html( get_bloginfo( 'name' ) ) );
}
