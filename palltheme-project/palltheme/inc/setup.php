<?php
/**
 * Theme setup: supports, menus, sidebars, image sizes, body classes.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme supports and menu locations.
 */
function palltheme_setup(): void {
	load_theme_textdomain( 'palltheme', PALLTHEME_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus(
		array(
			'primary'         => __( 'Primary Menu (header)', 'palltheme' ),
			'mobile'          => __( 'Mobile Menu (falls back to Primary)', 'palltheme' ),
			'topbar'          => __( 'Top Bar Links', 'palltheme' ),
			'footer_company'   => __( 'Footer: Company', 'palltheme' ),
			'footer_services'  => __( 'Footer: Services', 'palltheme' ),
			'footer_solutions' => __( 'Footer: Solutions', 'palltheme' ),
			'footer_products'  => __( 'Footer: Products', 'palltheme' ),
			'footer_support'   => __( 'Footer: Support', 'palltheme' ),
			'footer_resources' => __( 'Footer: Resources', 'palltheme' ),
			'footer_legal'     => __( 'Footer: Legal', 'palltheme' ),
		)
	);

	add_image_size( 'palltheme-card', 720, 480, true );
	add_image_size( 'palltheme-square', 600, 600, true );
	add_image_size( 'palltheme-wide', 1600, 900, true );

	$GLOBALS['content_width'] = 1240;
}
add_action( 'after_setup_theme', 'palltheme_setup' );

/**
 * Widget areas.
 */
function palltheme_widgets_init(): void {
	$base = array(
		'before_widget' => '<section id="%1$s" class="pt-widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="pt-widget__title">',
		'after_title'   => '</h2>',
	);

	register_sidebar(
		$base + array(
			'name'        => __( 'Blog Sidebar', 'palltheme' ),
			'id'          => 'sidebar-blog',
			'description' => __( 'Shown beside blog posts and archives.', 'palltheme' ),
		)
	);
	register_sidebar(
		$base + array(
			'name'        => __( 'Shop Filters', 'palltheme' ),
			'id'          => 'sidebar-shop',
			'description' => __( 'Shop sidebar. Add the "Palltheme: Product Filters" widget here — filters are generated from your product attributes automatically.', 'palltheme' ),
		)
	);
	register_sidebar(
		$base + array(
			'name'        => __( 'Footer Newsletter', 'palltheme' ),
			'id'          => 'footer-newsletter',
			'description' => __( 'Optional. Place a newsletter form widget/shortcode from your email-marketing plugin (Mailchimp, FluentCRM, MailPoet...). Leave empty to hide.', 'palltheme' ),
		)
	);
	for ( $i = 1; $i <= 7; $i++ ) {
		register_sidebar(
			$base + array(
				/* translators: %d: column number. */
				'name'        => sprintf( __( 'Footer Column %d', 'palltheme' ), $i ),
				'id'          => 'footer-' . $i,
				'description' => __( 'Optional. When empty, the matching Footer menu is shown instead.', 'palltheme' ),
			)
		);
	}
}
add_action( 'widgets_init', 'palltheme_widgets_init' );

/**
 * Body classes for header style, layout and features.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function palltheme_body_classes( array $classes ): array {
	if ( palltheme_header_is_transparent() ) {
		$classes[] = 'pt-header-transparent';
	}
	if ( palltheme_mod( 'header_sticky' ) ) {
		$classes[] = 'pt-header-sticky';
	}
	if ( ! palltheme_mod( 'perf_animations' ) ) {
		$classes[] = 'pt-no-anim';
	}
	$classes[] = 'pt-btn-' . sanitize_html_class( (string) palltheme_mod( 'button_style' ) );
	if ( is_singular() && palltheme_is_elementor_page() ) {
		$classes[] = 'pt-elementor-page';
	}
	return $classes;
}
add_filter( 'body_class', 'palltheme_body_classes' );

/**
 * Whether the header should start transparent on this request.
 */
function palltheme_header_is_transparent(): bool {
	if ( 'transparent' !== palltheme_mod( 'header_style' ) ) {
		return false;
	}
	if ( 'all' === palltheme_mod( 'header_transparent_pages' ) ) {
		return is_page() || is_front_page();
	}
	return is_front_page();
}

/**
 * Shorter, cleaner excerpts.
 */
add_filter( 'excerpt_length', static fn() => 24 );
add_filter( 'excerpt_more', static fn() => '&hellip;' );

/**
 * Pingback header for single posts.
 */
function palltheme_pingback_header(): void {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">' . "\n", esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'palltheme_pingback_header' );
