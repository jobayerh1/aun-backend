<?php
/**
 * Plugin Name: AUN Favicon
 * Description: Outputs the AUN favicon set and suppresses WordPress's own auto-generated Site Icon tags so the two cannot fight.
 * Version: 1.0.0
 *
 * INSTALL
 *  1. Upload the whole `favicon/` folder to the web root, so the files sit at
 *     https://aun-projector.com.bd/favicon/…
 *     Also copy favicon/favicon.ico to the web ROOT itself — browsers and some
 *     crawlers request /favicon.ico directly without reading any HTML.
 *  2. Upload THIS file to wp-content/mu-plugins/ (create the folder if needed).
 *     mu-plugins auto-run; there is nothing to activate.
 *  3. Purge WP Rocket, then hard-reload (Ctrl+F5). Favicons cache aggressively —
 *     if the old one lingers, open the site in a private window to confirm.
 *
 * WHY A PLUGIN RATHER THAN EDITING THE THEME
 *  A Flatsome update overwrites theme files. An mu-plugin survives updates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WordPress prints its own <link rel="icon"> tags when a Site Icon is set in the
 * Customizer. Those are produced by downscaling one image, which turns the
 * wordmark to mush at 16px — the exact problem this set exists to solve. Remove
 * them so only our per-size artwork is served.
 */
remove_action( 'wp_head', 'wp_site_icon', 99 );

add_action( 'wp_head', function () {
	$b = home_url( '/favicon/' );
	?>
<link rel="icon" href="<?php echo esc_url( home_url( '/favicon.ico' ) ); ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $b . 'favicon.svg' ); ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( $b . 'favicon-16x16.png' ); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $b . 'favicon-32x32.png' ); ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?php echo esc_url( $b . 'favicon-48x48.png' ); ?>">
<link rel="icon" type="image/png" sizes="96x96" href="<?php echo esc_url( $b . 'favicon-96x96.png' ); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $b . 'apple-touch-icon.png' ); ?>">
<link rel="manifest" href="<?php echo esc_url( $b . 'site.webmanifest' ); ?>">
<meta name="theme-color" content="#0188fe">
	<?php
}, 2 );

/*
 * WHY favicon.svg USES THE LETTERS-ONLY ARTWORK
 *
 * Modern browsers prefer an SVG icon over every PNG once one is declared, and they
 * use it mostly at 16–32px (tabs, bookmarks, history). Measured white-pixel
 * coverage at 16px: letters-only 13.3%, full mark with frame just 3.9% — the frame
 * effectively disappears and the tile reads as a blue smudge. So the SVG carries the
 * wordmark alone, matching the 16/32 PNGs.
 *
 * The full mark (frame + wordmark) still ships as favicon/aun-logo.svg and is what
 * apple-touch-icon, the Android icons and the large PNGs use. aun-logo.svg is also
 * a clean vector of the logo — reuse it anywhere on the site instead of a PNG.
 */
