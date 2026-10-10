<?php
/**
 * Helper functions.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values for every theme_mod used by the theme.
 *
 * @return array<string,mixed>
 */
function palltheme_defaults(): array {
	return array(
		// Colors.
		'color_primary'      => '#2567AD',
		'color_secondary'    => '#0B1220',
		'color_accent'       => '#00A8FF',
		'color_dark'         => '#07111F',
		'color_light'        => '#F5F8FC',
		'color_white'        => '#FFFFFF',
		'color_success'      => '#16A34A',
		'color_warning'      => '#F59E0B',
		'color_danger'       => '#DC2626',
		// Typography.
		'font_heading'       => 'Plus Jakarta Sans',
		'font_body'          => 'Inter',
		'font_source'        => 'google', // google | system.
		'base_font_size'     => 16,
		// Shape.
		'border_radius'      => 14,
		'button_style'       => 'rounded', // rounded | pill | square.
		// Branding.
		'logo_light'         => 0,
		'logo_dark'          => 0,
		'logo_sticky'        => 0,
		'logo_height'        => 40,
		// Header.
		'header_style'       => 'solid', // solid | transparent.
		'header_transparent_pages' => 'front', // front | all.
		'header_sticky'      => true,
		'header_topbar'      => true,
		'header_search'      => true,
		'header_account'     => true,
		'header_cart'        => true,
		'header_wishlist'    => true,
		'header_cta_text'    => __( 'Get a Quote', 'palltheme' ),
		'header_cta_url'     => '',
		// Footer.
		'footer_layout'      => 'mega', // mega | 5 | 4.
		'footer_description' => '',
		'footer_copyright'   => __( '© {year} {site}. All Rights Reserved.', 'palltheme' ),
		'footer_payments'    => 'Visa, Mastercard, PayPal, Bank Transfer, Cash on Delivery',
		'footer_newsletter_title' => __( 'Stay ahead of the curve', 'palltheme' ),
		// Dark mode.
		'dark_mode_default'  => 'system', // light | dark | system.
		'dark_mode_toggle'   => true,
		// Performance.
		'perf_animations'    => true,
		'perf_videos'        => true,
		'perf_lazyload'      => true,
		'perf_preload_fonts' => true,
		'perf_block_css'     => true,
		'perf_no_emoji'      => true,
		// Blog.
		'blog_layout'        => 'grid', // grid | list.
		'blog_sidebar'       => true,
		'blog_reading_time'  => true,
		'blog_share'         => true,
		'blog_author_box'    => true,
		// Shop.
		'shop_columns'       => 3,
		'shop_per_page'      => 12,
		'shop_pagination'    => 'numbers', // numbers | loadmore.
		'shop_sidebar'       => true,
		'shop_shipping_note' => __( 'Free shipping on qualifying orders. Same-day dispatch for in-stock items ordered before 2 PM.', 'palltheme' ),
		'shop_warranty_note' => __( 'All products include the manufacturer warranty. Extended warranty and on-site support plans are available.', 'palltheme' ),
		// 404.
		'notfound_title'     => __( 'Signal Lost', 'palltheme' ),
		'notfound_text'      => __( 'The requested resource could not be reached. It may have moved, or the link is broken.', 'palltheme' ),
	);
}

/**
 * Read a theme mod with the theme default as fallback.
 *
 * @param string $key Mod name.
 * @return mixed
 */
function palltheme_mod( string $key ) {
	$defaults = palltheme_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? null );
}

/**
 * Read a business setting from the companion plugin (contact, social, ...).
 * Returns $fallback when the plugin is inactive so the theme never fatals.
 *
 * @param string $key      Setting key.
 * @param mixed  $fallback Fallback.
 * @return mixed
 */
function palltheme_biz( string $key, $fallback = '' ) {
	if ( function_exists( 'pallcore_setting' ) ) {
		$value = pallcore_setting( $key );
		return ( '' === $value || null === $value ) ? $fallback : $value;
	}
	return $fallback;
}

/**
 * Custom field value from Palltheme Core (raw, unescaped).
 *
 * @return mixed
 */
function palltheme_meta( int $post_id, string $key ) {
	return function_exists( 'pallcore_meta' ) ? pallcore_meta( $post_id, $key ) : '';
}

/**
 * List field (one item per line) as array of strings.
 *
 * @return string[]
 */
function palltheme_lines( int $post_id, string $key ): array {
	$value = palltheme_meta( $post_id, $key );
	return is_array( $value ) ? $value : array();
}

/**
 * Pair/repeater field as array of [a, b] rows.
 *
 * @return array<int,array{0:string,1:string}>
 */
function palltheme_pairs( int $post_id, string $key ): array {
	$value = palltheme_meta( $post_id, $key );
	return is_array( $value ) ? $value : array();
}

/**
 * Content icon (service/solution icons) from the plugin, with UI-icon fallback.
 */
function palltheme_content_icon( int $post_id ): string {
	if ( function_exists( 'pallcore_post_icon' ) ) {
		return pallcore_post_icon( $post_id );
	}
	return palltheme_icon( 'bolt' );
}

/**
 * Whether the companion plugin is active.
 */
function palltheme_has_core(): bool {
	return function_exists( 'pallcore_setting' );
}

/**
 * Whether the current page is built with Elementor.
 */
function palltheme_is_elementor_page( ?int $post_id = null ): bool {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id || ! did_action( 'elementor/loaded' ) ) {
		return false;
	}
	return (bool) get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/**
 * Replace {year} and {site} tokens.
 */
function palltheme_tokens( string $text ): string {
	return strtr(
		$text,
		array(
			'{year}' => wp_date( 'Y' ),
			'{site}' => get_bloginfo( 'name' ),
		)
	);
}

/**
 * UI icon set (inline SVG, stroke icons, 24px grid). Content icons for
 * services/solutions are provided by the companion plugin.
 *
 * @param string $name  Icon name.
 * @param string $class Extra class.
 */
function palltheme_icon( string $name, string $class = '' ): string {
	static $paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'cart'     => '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.76L21 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17.5" cy="20" r="1.3"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'heart'    => '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 5.6 5 5 0 0 1 21.2 11c-2.2 4.6-9.2 9-9.2 9Z"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h10"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'     => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>',
		'monitor'  => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'chevron'  => '<path d="m6 9 6 6 6-6"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		'list'     => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
		'filter'   => '<path d="M4 5h16l-6 8v5l-4 2v-7L4 5Z"/>',
		'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'check'    => '<path d="m5 12 5 5 9-10"/>',
		'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/>',
		'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'shield'   => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
		'truck'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
		'home'     => '<path d="M4 11 12 4l8 7v9h-5v-6H9v6H4v-9Z"/>',
		'box'      => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>',
		'support'  => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19a3 3 0 0 1-3 3h-3"/>',
		'share'    => '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6M8.2 13.2l7.6 3.6"/>',
		'up'       => '<path d="m6 15 6-6 6 6"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'minus'    => '<path d="M5 12h14"/>',
		'bolt'     => '<path d="M13 3 5 13h6l-1 8 8-10h-6l1-8Z"/>',
	);
	$svg = $paths[ $name ] ?? $paths['arrow'];
	return sprintf(
		'<svg class="pt-icon %1$s" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		esc_attr( $class ),
		$svg
	);
}

/**
 * Allowed SVG tags for wp_kses on icon output.
 *
 * @return array<string,array<string,bool>>
 */
function palltheme_svg_kses(): array {
	$common = array(
		'class' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true,
		'stroke-linecap' => true, 'stroke-linejoin' => true, 'd' => true, 'cx' => true,
		'cy' => true, 'r' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true,
		'rx' => true, 'ry' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true,
		'points' => true, 'transform' => true, 'opacity' => true,
	);
	return array(
		'svg'      => $common + array( 'viewbox' => true, 'xmlns' => true, 'aria-hidden' => true, 'focusable' => true, 'role' => true ),
		'path'     => $common,
		'circle'   => $common,
		'rect'     => $common,
		'line'     => $common,
		'polyline' => $common,
		'polygon'  => $common,
		'g'        => $common,
		'ellipse'  => $common,
	);
}

/**
 * Echo an icon.
 */
function palltheme_the_icon( string $name, string $class = '' ): void {
	echo wp_kses( palltheme_icon( $name, $class ), palltheme_svg_kses() );
}

/**
 * Estimated reading time in minutes.
 */
function palltheme_reading_time( ?int $post_id = null ): int {
	$content = get_post_field( 'post_content', $post_id ?: get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( (string) $content ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Social profiles from the companion plugin settings.
 *
 * @return array<string,string> network => url
 */
function palltheme_social_links(): array {
	$networks = array( 'facebook', 'instagram', 'linkedin', 'youtube', 'x', 'tiktok' );
	$out      = array();
	foreach ( $networks as $network ) {
		$url = palltheme_biz( 'social_' . $network );
		if ( $url ) {
			$out[ $network ] = $url;
		}
	}
	return $out;
}

/**
 * Brand glyphs for social networks (simple monochrome marks).
 */
function palltheme_social_icon( string $network ): string {
	$paths = array(
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v7h4v-7h3l1-4h-4V8.5a.5.5 0 0 1 .5-.5Z" fill="currentColor" stroke="none"/>',
		'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor"/>',
		'linkedin'  => '<path d="M6.5 9v10M6.5 5.5v.01M10.5 19v-6a3 3 0 0 1 6 0v6M10.5 9v10" />',
		'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3V9Z" fill="currentColor"/>',
		'x'         => '<path d="M4 4l16 16M20 4 4 20" />',
		'tiktok'    => '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.5 2.6 2.4 4.4 5 4.6" />',
	);
	return sprintf(
		'<svg class="pt-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
		$paths[ $network ] ?? ''
	);
}

/**
 * Wishlist URL + count from a supported wishlist plugin (YITH by default).
 * Other plugins can hook `palltheme_wishlist_data`.
 *
 * @return array{url:string,count:int}|null
 */
function palltheme_wishlist_data(): ?array {
	$data = null;
	if ( function_exists( 'YITH_WCWL' ) && function_exists( 'yith_wcwl_count_all_products' ) ) {
		$data = array(
			'url'   => (string) YITH_WCWL()->get_wishlist_url(),
			'count' => (int) yith_wcwl_count_all_products(),
		);
	}
	return apply_filters( 'palltheme_wishlist_data', $data );
}

/**
 * AJAX: current wishlist count (refreshed by main.js after wishlist changes,
 * so the header badge stays correct on cached pages).
 */
function palltheme_ajax_wishlist_count(): void {
	$data = palltheme_wishlist_data();
	wp_send_json_success( array( 'count' => $data ? (int) $data['count'] : 0 ) );
}
add_action( 'wp_ajax_palltheme_wishlist_count', 'palltheme_ajax_wishlist_count' );
add_action( 'wp_ajax_nopriv_palltheme_wishlist_count', 'palltheme_ajax_wishlist_count' );

/**
 * Human label for a social network.
 */
function palltheme_social_label( string $network ): string {
	$labels = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'x'         => 'X',
		'tiktok'    => 'TikTok',
	);
	return $labels[ $network ] ?? ucfirst( $network );
}
