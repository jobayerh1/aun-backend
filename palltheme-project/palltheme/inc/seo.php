<?php
/**
 * SEO compatibility.
 *
 * Rank Math / Yoast / SEOPress / AIOSEO own titles, meta, Open Graph and
 * schema when installed. The theme only outputs a minimal fallback
 * (description, Open Graph, X card, Organization schema) when none of them
 * is active, so there is never duplicated or competing metadata.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a dedicated SEO plugin is active.
 */
function palltheme_seo_plugin_active(): bool {
	return defined( 'RANK_MATH_VERSION' )
		|| defined( 'WPSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| class_exists( 'The_SEO_Framework\Load' );
}

/**
 * Minimal meta + Open Graph fallback.
 */
function palltheme_fallback_meta(): void {
	if ( palltheme_seo_plugin_active() || ! apply_filters( 'palltheme_output_fallback_meta', true ) ) {
		return;
	}

	$title = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$url   = home_url( add_query_arg( array() ) );
	$image = '';
	$type  = 'website';

	if ( is_singular() ) {
		$post  = get_queried_object();
		$desc  = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
		$url   = get_permalink( $post );
		$image = get_the_post_thumbnail_url( $post, 'large' ) ?: '';
		$type  = 'post' === $post->post_type ? 'article' : 'website';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$desc = wp_strip_all_tags( term_description() ) ?: $desc;
	}
	if ( ! $image && has_site_icon() ) {
		$image = get_site_icon_url( 512 );
	}

	$desc = trim( preg_replace( '/\s+/', ' ', (string) $desc ) );

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	if ( $desc ) {
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
	}
	printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );

	if ( is_front_page() ) {
		$org = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);
		$logo = (int) get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			$org['logo'] = wp_get_attachment_image_url( $logo, 'full' );
		}
		$phone = palltheme_biz( 'phone' );
		$email = palltheme_biz( 'email' );
		if ( $phone || $email ) {
			$org['contactPoint'] = array_filter(
				array(
					'@type'       => 'ContactPoint',
					'telephone'   => $phone,
					'email'       => $email,
					'contactType' => 'customer service',
				)
			);
		}
		$same = array_values( palltheme_social_links() );
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}
}
add_action( 'wp_head', 'palltheme_fallback_meta', 5 );
