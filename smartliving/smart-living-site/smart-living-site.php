<?php
/**
 * Plugin Name:       Smart Living Site
 * Plugin URI:        https://www.smartliving.com.bd/
 * Description:       Renders the whole Smart Living Bangladesh parent-company site — its own templates, copy, SEO and schema — without the theme or a page builder. Replaces the Slider Revolution home page.
 * Version:           1.0.4
 * Author:            Smart Living Bangladesh
 * License:           GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       smart-living-site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SL_SITE_VER', '1.0.4' );
define( 'SL_SITE_FILE', __FILE__ );
define( 'SL_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SL_SITE_URL', plugin_dir_url( __FILE__ ) );

require_once SL_SITE_DIR . 'includes/helpers.php';
require_once SL_SITE_DIR . 'includes/content.php';
require_once SL_SITE_DIR . 'includes/layout.php';
require_once SL_SITE_DIR . 'includes/render-home.php';
require_once SL_SITE_DIR . 'includes/render-pages.php';
require_once SL_SITE_DIR . 'includes/seo.php';

if ( is_admin() ) {
	require_once SL_SITE_DIR . 'includes/admin.php';
}

/**
 * Swap in our own full-page template for the six pages we own.
 */
function sl_template_include( $template ) {
	if ( sl_current_page() === '' ) {
		return $template;
	}
	$canvas = SL_SITE_DIR . 'templates/canvas.php';
	return file_exists( $canvas ) ? $canvas : $template;
}
add_filter( 'template_include', 'sl_template_include', 99 );

/**
 * Front-end assets. Only loaded on the pages this plugin renders, so the rest
 * of the site (wp-login, any future pages) is untouched.
 */
function sl_enqueue() {
	if ( sl_current_page() === '' ) {
		return;
	}

	wp_enqueue_style(
		'sl-site',
		SL_SITE_URL . 'assets/sl.css',
		array(),
		SL_SITE_VER
	);

	wp_enqueue_script(
		'sl-site',
		SL_SITE_URL . 'assets/sl.js',
		array(),
		SL_SITE_VER,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'sl_enqueue', 100 );

/**
 * Drop the front-end bundles nothing on these pages uses.
 *
 * Runs very late, and again on wp_print_styles, because Flatsome enqueues its
 * own assets after priority 100 — dequeueing from sl_enqueue() was too early
 * and silently did nothing. That mattered: Flatsome's form reset sets
 * `select, input { height: 2.507em }`, a fixed height that clipped the text in
 * our taller form fields, and its googlefonts handle was still pulling Lato and
 * Dancing Script from fonts.googleapis.com on a site that self-hosts its type.
 */
function sl_dequeue_legacy_assets() {
	if ( sl_current_page() === '' ) {
		return;
	}

	$styles = array(
		'flatsome-main',
		'flatsome-style',
		'flatsome-shop',
		'flatsome-effect-bounce',
		'flatsome-googlefonts',
		// Every icon on this site is an inline SVG; two of these are external
		// requests to use.fontawesome.com.
		'font-awesome-official',
		'font-awesome-official-v4shim',
		'font-awesome-svg-styles',
		// Slider Revolution, in case it is ever reactivated.
		'rs-plugin-settings',
		'sr7css',
	);
	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}

	$scripts = array(
		'flatsome-js',
		'flatsome-theme-js',
		'flatsome-live-search',
		'rbtools',
		'revmin',
		'tp-tools',
		'revslider-typewriter-addon',
	);
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'sl_dequeue_legacy_assets', 9999 );
add_action( 'wp_print_styles', 'sl_dequeue_legacy_assets', 0 );

/**
 * Stop the theme printing its own off-canvas mobile drawer into our footer.
 *
 * Flatsome hooks a function onto wp_footer that outputs
 * `<div id="main-menu" class="mobile-sidebar mfp-hide">` with the old
 * WordPress menu inside it. It is normally invisible because flatsome.css
 * hides `.mfp-hide` — but we now dequeue that stylesheet, so from v1.0.1 the
 * markup showed up as a stray list of links below the footer.
 *
 * The callback's name differs between theme versions, so rather than hardcode
 * one and have it silently stop matching, we look at what is actually
 * registered on wp_footer and drop the theme's own drawer callbacks. Only
 * functions the theme prefixed with "flatsome" are eligible, so a plugin with
 * a similarly named function is never touched. If nothing matches — a
 * different theme, or a renamed function — the CSS rule in sl.css still hides
 * it, so the page is correct either way.
 */
function sl_drop_theme_offcanvas() {
	if ( sl_current_page() === '' ) {
		return;
	}

	global $wp_filter;
	if ( empty( $wp_filter['wp_footer'] ) || ! is_object( $wp_filter['wp_footer'] ) ) {
		return;
	}

	foreach ( $wp_filter['wp_footer']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = isset( $cb['function'] ) ? $cb['function'] : null;
			if ( ! is_string( $fn ) || 0 !== strpos( $fn, 'flatsome' ) ) {
				continue;
			}
			if ( preg_match( '/(sidebar|off_?canvas|main_menu|mobile)/i', $fn ) ) {
				remove_action( 'wp_footer', $fn, $priority );
			}
		}
	}
}
add_action( 'wp_footer', 'sl_drop_theme_offcanvas', 0 );

/**
 * Flatsome ships demo content — "Awesome Pencil Poster", "Flat T-shirt Company",
 * UX Blocks — as public post types, so they land in wp-sitemap.xml and can be
 * indexed as pages of this company. Keep them out of the sitemap and tell
 * crawlers to ignore them.
 *
 * This does not delete anything. Removing the demo posts themselves is a
 * separate job for the owner.
 */
function sl_hide_demo_post_types( $post_types ) {
	foreach ( array( 'blocks', 'featured_item' ) as $type ) {
		unset( $post_types[ $type ] );
	}
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'sl_hide_demo_post_types' );

function sl_hide_demo_taxonomies( $taxonomies ) {
	unset( $taxonomies['featured_item_category'] );
	return $taxonomies;
}
add_filter( 'wp_sitemaps_taxonomies', 'sl_hide_demo_taxonomies' );

function sl_noindex_demo_content( $robots ) {
	if ( is_404()
		|| is_singular( array( 'blocks', 'featured_item' ) )
		|| is_tax( 'featured_item_category' )
		|| is_post_type_archive( array( 'blocks', 'featured_item' ) )
	) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'sl_noindex_demo_content' );

/**
 * Drop resource hints for hosts nothing loads from any more.
 *
 * WordPress and the theme emit `<link rel="dns-prefetch">` for
 * fonts.googleapis.com and use.fontawesome.com. Since we dequeue both, those
 * hints now ask the browser to resolve two domains it will never contact.
 */
function sl_trim_resource_hints( $urls, $relation ) {
	if ( 'dns-prefetch' !== $relation || sl_current_page() === '' ) {
		return $urls;
	}
	$dead = '#(fonts\.googleapis\.com|fonts\.gstatic\.com|use\.fontawesome\.com|s\.w\.org)#i';
	return array_values(
		array_filter(
			$urls,
			function ( $url ) use ( $dead ) {
				$href = is_array( $url ) ? ( isset( $url['href'] ) ? $url['href'] : '' ) : $url;
				return ! preg_match( $dead, (string) $href );
			}
		)
	);
}
add_filter( 'wp_resource_hints', 'sl_trim_resource_hints', 10, 2 );

/**
 * Preload the two self-hosted faces so the first paint has them.
 */
function sl_preload_fonts() {
	if ( sl_current_page() === '' ) {
		return;
	}
	$fonts = array(
		'fraunces-latin-normal.woff2',
		'instrument-sans-latin-normal.woff2',
	);
	foreach ( $fonts as $f ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( SL_SITE_URL . 'assets/fonts/' . $f )
		);
	}
}
add_action( 'wp_head', 'sl_preload_fonts', 1 );

/**
 * WordPress emits the emoji script from s.w.org on every page. Nothing here
 * uses it and it is an external request on a site that self-hosts everything.
 */
function sl_drop_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'sl_drop_emoji' );

/**
 * On activation, make sure the six pages exist and are stamped. Safe to run
 * repeatedly: existing pages are adopted by slug, never duplicated.
 */
function sl_activate() {
	sl_build_pages();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sl_activate' );
