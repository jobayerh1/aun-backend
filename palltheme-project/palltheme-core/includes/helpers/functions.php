<?php
/**
 * Core helpers shared by the theme, shortcodes and Elementor widgets.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a Business Setting (contact, social, stats...).
 *
 * @param string $key Setting key.
 * @return mixed
 */
function pallcore_setting( string $key ) {
	static $cache = null;
	if ( null === $cache ) {
		$cache = wp_parse_args( (array) get_option( 'pallcore_settings', array() ), pallcore_setting_defaults() );
	}
	return $cache[ $key ] ?? '';
}

/**
 * Read a custom field. Meta keys are stored as `_pall_{key}`.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Field key.
 * @return mixed
 */
function pallcore_meta( int $post_id, string $key ) {
	return get_post_meta( $post_id, '_pall_' . $key, true );
}

/**
 * Post types managed by this plugin.
 *
 * @return string[]
 */
function pallcore_post_types(): array {
	return array( 'pall_service', 'pall_solution', 'pall_case_study', 'pall_team', 'pall_testimonial', 'pall_client', 'pall_technology', 'pall_pricing', 'pall_faq', 'pall_job' );
}

/**
 * Icon markup for a service/solution post: uploaded SVG/image first,
 * otherwise the chosen icon from the built-in set.
 */
function pallcore_post_icon( int $post_id ): string {
	$image = (int) pallcore_meta( $post_id, 'icon_image' );
	if ( $image ) {
		return (string) wp_get_attachment_image( $image, 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) );
	}
	$name = (string) pallcore_meta( $post_id, 'icon' );
	return pallcore_icon( $name ?: 'cpu' );
}

/**
 * Star rating markup.
 */
function pallcore_stars( float $rating ): string {
	$rating = max( 0, min( 5, $rating ) );
	$star   = '<svg class="pt-icon %s" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/></svg>';
	$out    = '<span class="pt-stars" role="img" aria-label="' . esc_attr( sprintf( /* translators: %s: rating. */ __( 'Rated %s out of 5', 'palltheme-core' ), number_format_i18n( $rating, 1 ) ) ) . '">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$out .= sprintf( $star, $i <= round( $rating ) ? '' : 'is-empty' );
	}
	return $out . '</span>';
}

/**
 * FAQ accordion HTML + FAQPage schema (schema skipped when an SEO plugin
 * that manages FAQ schema is active, or via the `pallcore_faq_schema` filter).
 *
 * @param array<int,array{0:string,1:string}> $items Question/answer pairs.
 */
function pallcore_faq_html( array $items ): string {
	$items = array_values( array_filter( $items, static fn( $row ) => ! empty( $row[0] ) ) );
	if ( ! $items ) {
		return '';
	}
	$html   = '<div class="pt-faq">';
	$schema = array();
	foreach ( $items as $i => $row ) {
		$html    .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $row[0] ) . pallcore_ui_icon( 'plus' ) . '</summary><div class="pt-faq__answer">' . wp_kses_post( wpautop( (string) ( $row[1] ?? '' ) ) ) . '</div></details>';
		$schema[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $row[0] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( (string) ( $row[1] ?? '' ) ),
			),
		);
	}
	$html .= '</div>';

	// SEO plugins only emit FAQ schema for their own FAQ blocks, so ours is always useful.
	// Return false from this filter if a page also uses an SEO plugin's FAQ block.
	$emit = apply_filters( 'pallcore_faq_schema', true );
	if ( $emit ) {
		$html .= '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $schema,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . '</script>';
	}
	return $html;
}

/**
 * Small UI icons used in plugin markup (keeps the plugin theme-independent).
 */
function pallcore_ui_icon( string $name ): string {
	$paths = array(
		'plus'    => '<path d="M12 5v14M5 12h14"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'check'   => '<path d="m5 12 5 5 9-10"/>',
		'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'     => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'chevron' => '<path d="m6 9 6 6 6-6"/>',
		'download'=> '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'play'    => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5Z"/>',
		'bolt'    => '<path d="M13 3 5 13h6l-1 8 8-10h-6l1-8Z"/>',
		'left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	return '<svg class="pt-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['arrow'] ) . '</svg>';
}

/**
 * Parse "a | b" lines into pairs (used by settings textareas).
 *
 * @return array<int,array{0:string,1:string}>
 */
function pallcore_parse_pairs( string $text ): array {
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
		$rows[] = array( $parts[0], $parts[1] ?? '' );
	}
	return $rows;
}

/**
 * Clean phone number for tel: / wa.me links.
 */
function pallcore_phone_digits( string $phone, bool $keep_plus = true ): string {
	return preg_replace( $keep_plus ? '/[^0-9+]/' : '/[^0-9]/', '', $phone );
}

/**
 * Kses rules for our inline SVG icons.
 *
 * @return array<string,array<string,bool>>
 */
function pallcore_svg_kses(): array {
	$a = array_fill_keys( array( 'class', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'd', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'rx', 'ry', 'x1', 'x2', 'y1', 'y2', 'points', 'transform', 'opacity', 'viewbox', 'xmlns', 'aria-hidden', 'focusable', 'role', 'aria-label' ), true );
	return array(
		'svg'      => $a,
		'path'     => $a,
		'circle'   => $a,
		'rect'     => $a,
		'line'     => $a,
		'polyline' => $a,
		'polygon'  => $a,
		'g'        => $a,
		'ellipse'  => $a,
	);
}
