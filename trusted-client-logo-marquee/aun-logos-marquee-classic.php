<?php
/**
 * Plugin Name: AUN Trusted Client Logos Marquee
 * Description: Continuous, draggable logo marquee for Flatsome — stable build with optional inertia (no other effects). Loads only on homepage.
 * Version: 3.2.0
 * Author: Smart Living Bangladesh
 * License: GPLv2 or later
 * Text Domain: aun-logos-marquee-classic
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ALMC_VERSION', '3.2.0' );
define( 'ALMC_URL', plugin_dir_url( __FILE__ ) );
define( 'ALMC_PATH', plugin_dir_path( __FILE__ ) );

function almc_enqueue_assets() {
    if ( is_admin() ) { return; }
    if ( ! ( is_front_page() || is_home() ) ) { return; }

    wp_enqueue_style(
        'almc-marquee',
        ALMC_URL . 'assets/front.css',
        array(),
        ALMC_VERSION
    );

    wp_enqueue_script(
        'almc-marquee',
        ALMC_URL . 'assets/front.js',
        array(),
        ALMC_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'almc_enqueue_assets' );
