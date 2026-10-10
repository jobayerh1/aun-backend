<?php
/**
 * Section renderers. Each takes an attribute array and returns HTML.
 * Used by shortcodes, Elementor widgets and the default homepage, so the
 * three always look identical.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Common section attributes.
 *
 * @return array<string,string>
 */
function pallcore_section_defaults(): array {
	return array(
		'eyebrow'    => '',
		'title'      => '',
		'lead'       => '',
		'align'      => 'left',   // left | center.
		'link_text'  => '',
		'link_url'   => '',
		'background' => 'default', // default | alt | dark.
		'spacing'    => 'normal',  // normal | tight | none.
		'id'         => '',
		'wrap'       => 'yes',     // yes = full <section>, no = inner content only (Elementor controls spacing).
	);
}

/**
 * Section head (eyebrow, title, lead, link).
 *
 * @param array $a Attributes.
 */
function pallcore_section_head( array $a ): string {
	if ( '' === $a['title'] && '' === $a['eyebrow'] && '' === $a['lead'] ) {
		return '';
	}
	$row   = $a['link_text'] && $a['link_url'] && 'center' !== $a['align'];
	$class = 'pt-section-head' . ( 'center' === $a['align'] ? ' pt-section-head--center' : '' ) . ( $row ? ' pt-section-head--row' : '' );

	$html = '<div class="' . esc_attr( $class ) . '"><div>';
	if ( $a['eyebrow'] ) {
		$html .= '<p class="pt-eyebrow">' . esc_html( $a['eyebrow'] ) . '</p>';
	}
	if ( $a['title'] ) {
		$html .= '<h2 class="pt-section-title">' . pallcore_highlight( $a['title'] ) . '</h2>';
	}
	if ( $a['lead'] ) {
		$html .= '<p class="pt-section-lead">' . esc_html( $a['lead'] ) . '</p>';
	}
	$html .= '</div>';
	if ( $a['link_text'] && $a['link_url'] ) {
		$html .= '<a class="pt-link-arrow" href="' . esc_url( $a['link_url'] ) . '">' . esc_html( $a['link_text'] ) . pallcore_ui_icon( 'arrow' ) . '</a>';
	}
	return $html . '</div>';
}

/**
 * Turn *word* into a gradient highlight (input is escaped first).
 */
function pallcore_highlight( string $text, string $tag = 'span' ): string {
	$text = esc_html( $text );
	$open = 'em' === $tag ? '<em>' : '<span class="pt-gradient-text">';
	$close = 'em' === $tag ? '</em>' : '</span>';
	return (string) preg_replace( '/\*(.+?)\*/', $open . '$1' . $close, $text );
}

/**
 * Wrap inner HTML in a section with head.
 *
 * @param string $inner   Inner HTML (already escaped).
 * @param array  $a       Attributes.
 * @param string $classes Extra classes.
 */
function pallcore_section( string $inner, array $a, string $classes = '' ): string {
	if ( '' === trim( $inner ) ) {
		return '';
	}
	$head = pallcore_section_head( $a );
	if ( 'no' === $a['wrap'] ) {
		return '<div class="' . esc_attr( trim( 'pt-block ' . $classes ) ) . '">' . $head . $inner . '</div>';
	}
	$cls = array( 'pt-section', $classes );
	if ( 'alt' === $a['background'] ) {
		$cls[] = 'pt-section--alt';
	} elseif ( 'dark' === $a['background'] ) {
		$cls[] = 'pt-section--dark';
	}
	if ( 'tight' === $a['spacing'] ) {
		$cls[] = 'pt-section--tight';
	}
	$style = 'none' === $a['spacing'] ? ' style="padding-block:0"' : '';
	$lock  = 'dark' === $a['background'] ? ' data-scheme-lock="dark"' : '';
	$id    = $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : '';
	return '<section' . $id . ' class="' . esc_attr( implode( ' ', array_filter( $cls ) ) ) . '"' . $lock . $style . '><div class="pt-container">' . $head . $inner . '</div></section>';
}

/**
 * Merge user attributes with section + specific defaults.
 *
 * @param array $defaults Specific defaults.
 * @param mixed $atts     User attributes.
 */
function pallcore_atts( array $defaults, $atts ): array {
	return shortcode_atts( array_merge( pallcore_section_defaults(), $defaults ), is_array( $atts ) ? $atts : array() );
}

/**
 * Query helper for our post types.
 *
 * @param string $type  Post type.
 * @param array  $a     Attributes (count, ids, orderby, tax/term).
 * @param string $tax   Taxonomy for the `category` attribute.
 * @return WP_Post[]
 */
function pallcore_query( string $type, array $a, string $tax = '' ): array {
	$args = array(
		'post_type'           => $type,
		'posts_per_page'      => max( 1, min( 48, (int) ( $a['count'] ?? 6 ) ) ),
		'post_status'         => 'publish',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'orderby'             => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	);
	if ( ! empty( $a['ids'] ) ) {
		$args['post__in']       = array_map( 'absint', explode( ',', (string) $a['ids'] ) );
		$args['orderby']        = 'post__in';
		$args['posts_per_page'] = count( $args['post__in'] );
	}
	if ( $tax && ! empty( $a['category'] ) ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', (string) $a['category'] ) ),
			),
		);
	}
	if ( ! empty( $a['orderby'] ) && in_array( $a['orderby'], array( 'date', 'title', 'rand' ), true ) && empty( $a['ids'] ) ) {
		$args['orderby'] = $a['orderby'];
	}
	return get_posts( apply_filters( 'pallcore_query_args', $args, $type, $a ) );
}

/**
 * Grid wrapper.
 *
 * @param WP_Post[] $posts   Posts.
 * @param int       $columns Columns.
 */
function pallcore_grid( array $posts, int $columns = 3 ): string {
	if ( ! $posts ) {
		return '';
	}
	$columns = max( 2, min( 4, $columns ) );
	$html    = '<div class="pt-grid pt-grid--' . $columns . '">';
	foreach ( $posts as $post ) {
		$html .= pallcore_card( $post );
	}
	return $html . '</div>';
}

/* ======================================================================
 * Sections
 * ==================================================================== */

/**
 * Hero.
 *
 * @param array $atts Attributes (fall back to Business Settings → Homepage Hero).
 */
function pallcore_render_hero( $atts = array() ): string {
	$s = static fn( $k ) => (string) pallcore_setting( $k );
	$a = shortcode_atts(
		array(
			'eyebrow'    => $s( 'hero_eyebrow' ),
			'title'      => $s( 'hero_title' ),
			'text'       => $s( 'hero_text' ),
			'btn1_text'  => $s( 'hero_btn1_text' ),
			'btn1_url'   => $s( 'hero_btn1_url' ) ?: ( get_post_type_archive_link( 'pall_solution' ) ?: home_url( '/' ) ),
			'btn2_text'  => $s( 'hero_btn2_text' ),
			'btn2_url'   => $s( 'hero_btn2_url' ) ?: ( $s( 'quote_url' ) ?: home_url( '/contact/' ) ),
			'image'      => $s( 'hero_image' ),
			'image_url'  => '',
			'cards'      => $s( 'hero_cards' ),
			'stats'      => 'no',
			'video_mp4'  => $s( 'hero_video_mp4' ),
			'video_webm' => $s( 'hero_video_webm' ),
			'poster'     => $s( 'hero_poster' ),
			'lottie'     => $s( 'hero_lottie' ),
		),
		is_array( $atts ) ? $atts : array()
	);

	// Background media.
	$bg = '<span class="pt-hero__grid"></span><span class="pt-hero__orb"></span>';
	if ( $a['poster'] ) {
		$bg .= wp_get_attachment_image( (int) $a['poster'], 'full', false, array( 'class' => 'pt-hero__media', 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high' ) );
	}
	if ( $a['video_mp4'] || $a['video_webm'] ) {
		$bg .= '<video class="pt-hero__media pt-hero__video" muted loop playsinline preload="none" aria-hidden="true" data-pt-video>';
		if ( $a['video_webm'] ) {
			$bg .= '<source data-src="' . esc_url( $a['video_webm'] ) . '" type="video/webm">';
		}
		if ( $a['video_mp4'] ) {
			$bg .= '<source data-src="' . esc_url( $a['video_mp4'] ) . '" type="video/mp4">';
		}
		$bg .= '</video>';
	}

	// Visual.
	$visual = '';
	if ( $a['lottie'] ) {
		$visual = '<div class="pt-hero__lottie" data-pt-lottie="' . esc_url( $a['lottie'] ) . '" aria-hidden="true"></div>';
	} elseif ( $a['image'] ) {
		$visual = '<div class="pt-hero__image">' . wp_get_attachment_image( (int) $a['image'], 'large', false, array( 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => '' ) ) . '</div>';
	} elseif ( $a['image_url'] ) {
		$visual = '<div class="pt-hero__image"><img src="' . esc_url( $a['image_url'] ) . '" alt="" loading="eager" fetchpriority="high" width="800" height="680"></div>';
	} else {
		$visual = '<div class="pt-hero__image">' . pallcore_hero_illustration() . '</div>';
	}

	$cards = '';
	foreach ( array_slice( pallcore_parse_pairs( $a['cards'] ), 0, 3 ) as $i => $row ) {
		$parts = array_map( 'trim', explode( '|', $row[1] ) );
		$cards .= sprintf(
			'<div class="pt-float-card pt-float-card--%1$d"><span class="pt-icon-tile">%2$s</span><span><strong>%3$s</strong><span>%4$s</span></span></div>',
			$i + 1,
			pallcore_icon( $parts[1] ?? 'check' ),
			esc_html( $row[0] ),
			esc_html( $parts[0] )
		);
	}

	$stats_html = '';
	if ( 'yes' === $a['stats'] ) {
		$stats = array_slice( pallcore_stats(), 0, 3 );
		if ( $stats ) {
			$stats_html = '<div class="pt-hero__stats">';
			foreach ( $stats as $row ) {
				$stats_html .= '<div><strong data-pt-count="' . esc_attr( $row[0] ) . '">' . esc_html( $row[0] ) . '</strong><span>' . esc_html( $row[1] ) . '</span></div>';
			}
			$stats_html .= '</div>';
		}
	}

	$buttons = '';
	if ( $a['btn1_text'] ) {
		$buttons .= '<a class="pt-btn pt-btn--accent" href="' . esc_url( $a['btn1_url'] ) . '">' . esc_html( $a['btn1_text'] ) . pallcore_ui_icon( 'arrow' ) . '</a>';
	}
	if ( $a['btn2_text'] ) {
		$buttons .= '<a class="pt-btn pt-btn--glass" href="' . esc_url( $a['btn2_url'] ) . '">' . esc_html( $a['btn2_text'] ) . '</a>';
	}

	return sprintf(
		'<section class="pt-hero" data-scheme-lock="dark"><div class="pt-hero__bg" aria-hidden="true">%1$s</div><div class="pt-container pt-hero__inner"><div class="pt-hero__content">%2$s<h1 class="pt-hero__title">%3$s</h1>%4$s<div class="pt-btn-row">%5$s</div>%6$s</div><div class="pt-hero__visual">%7$s%8$s</div></div></section>',
		$bg,
		$a['eyebrow'] ? '<p class="pt-eyebrow">' . esc_html( $a['eyebrow'] ) . '</p>' : '',
		pallcore_highlight( $a['title'], 'em' ),
		$a['text'] ? '<p class="pt-hero__lead">' . esc_html( $a['text'] ) . '</p>' : '',
		$buttons,
		$stats_html,
		$visual,
		$cards
	);
}

/**
 * Built-in hero illustration (original SVG — a stylised network/rack scene).
 */
function pallcore_hero_illustration(): string {
	$file = PALLCORE_DIR . 'assets/images/hero-network.svg';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	return '<img src="' . esc_url( PALLCORE_URL . 'assets/images/hero-network.svg' ) . '" alt="" width="800" height="680" loading="eager" fetchpriority="high">';
}

/**
 * Services grid.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_services( $atts = array() ): string {
	$a = pallcore_atts( array( 'count' => 6, 'columns' => 3, 'category' => '', 'ids' => '', 'orderby' => '' ), $atts );
	return pallcore_section( pallcore_grid( pallcore_query( 'pall_service', $a, 'pall_service_cat' ), (int) $a['columns'] ), $a );
}

/**
 * Solutions grid.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_solutions( $atts = array() ): string {
	$a = pallcore_atts( array( 'count' => 6, 'columns' => 3, 'category' => '', 'ids' => '', 'orderby' => '' ), $atts );
	return pallcore_section( pallcore_grid( pallcore_query( 'pall_solution', $a, 'pall_industry' ), (int) $a['columns'] ), $a );
}

/**
 * Case studies.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_case_studies( $atts = array() ): string {
	$a = pallcore_atts( array( 'count' => 3, 'columns' => 3, 'category' => '', 'ids' => '', 'orderby' => '' ), $atts );
	return pallcore_section( pallcore_grid( pallcore_query( 'pall_case_study', $a, 'pall_industry' ), (int) $a['columns'] ), $a );
}

/**
 * Team.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_team( $atts = array() ): string {
	$a = pallcore_atts( array( 'count' => 8, 'columns' => 4, 'category' => '', 'ids' => '', 'orderby' => '' ), $atts );
	return pallcore_section( pallcore_grid( pallcore_query( 'pall_team', $a, 'pall_department' ), (int) $a['columns'] ), $a );
}

/**
 * Slider wrapper (scroll-snap) for any card HTML list.
 *
 * @param string[] $items    Card HTML.
 * @param int      $autoplay Autoplay ms (0 = off).
 */
function pallcore_slider( array $items, int $autoplay = 0 ): string {
	if ( ! $items ) {
		return '';
	}
	return '<div class="pt-slider" data-pt-slider data-autoplay="' . (int) $autoplay . '"><div class="pt-slider__track" tabindex="0" role="region" aria-label="' . esc_attr__( 'Carousel', 'palltheme-core' ) . '">' . implode( '', $items ) . '</div><div class="pt-slider__nav"><button type="button" data-dir="-1" aria-label="' . esc_attr__( 'Previous', 'palltheme-core' ) . '">' . pallcore_ui_icon( 'arrow' ) . '</button><button type="button" data-dir="1" aria-label="' . esc_attr__( 'Next', 'palltheme-core' ) . '">' . pallcore_ui_icon( 'arrow' ) . '</button></div></div>';
}

/**
 * Testimonials (slider | grid).
 *
 * @param array $atts Attributes.
 */
function pallcore_render_testimonials( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 9, 'columns' => 3, 'layout' => 'slider', 'ids' => '', 'autoplay' => 6000, 'orderby' => '' ), $atts );
	$posts = pallcore_query( 'pall_testimonial', $a );
	if ( 'grid' === $a['layout'] ) {
		return pallcore_section( pallcore_grid( $posts, (int) $a['columns'] ), $a );
	}
	return pallcore_section( pallcore_slider( array_map( 'pallcore_card', $posts ), (int) $a['autoplay'] ), $a );
}

/**
 * Client logos (carousel | grid).
 *
 * @param array $atts Attributes.
 */
function pallcore_render_clients( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 24, 'layout' => 'carousel', 'speed' => 40, 'ids' => '', 'category' => '' ), $atts );
	$posts = pallcore_query( 'pall_client', $a, 'pall_industry' );
	if ( ! $posts ) {
		return '';
	}
	$items = '';
	foreach ( $posts as $post ) {
		$url  = (string) pallcore_meta( $post->ID, 'url' );
		$logo = has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'medium', array( 'alt' => esc_attr( get_the_title( $post ) ), 'loading' => 'lazy' ) ) : '<span>' . esc_html( get_the_title( $post ) ) . '</span>';
		$tag  = $url ? 'a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow"' : 'span';
		$items .= '<' . $tag . ' class="pt-logo-item" title="' . esc_attr( get_the_title( $post ) ) . '">' . $logo . '</' . ( $url ? 'a' : 'span' ) . '>';
	}
	$carousel = 'carousel' === $a['layout'];
	// Duplicate the list once so the marquee loops seamlessly (copy hidden from AT).
	$track = $items . ( $carousel ? '<span class="pt-logos__dup" aria-hidden="true" style="display:contents">' . str_replace( '<a ', '<a tabindex="-1" ', $items ) . '</span>' : '' );
	$inner = '<div class="pt-logos' . ( $carousel ? '' : ' pt-logos--grid' ) . '" style="--pt-marquee-speed:' . max( 10, (int) $a['speed'] ) . 's"><div class="pt-logos__track">' . $track . '</div></div>';
	return pallcore_section( $inner, $a, 'pt-section--clients' );
}

/**
 * Technology stack (with group filter).
 *
 * @param array $atts Attributes.
 */
function pallcore_render_technologies( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 32, 'filter' => 'yes', 'ids' => '', 'category' => '' ), $atts );
	$posts = pallcore_query( 'pall_technology', $a, 'pall_tech_cat' );
	if ( ! $posts ) {
		return '';
	}
	$groups = array();
	$items  = '';
	foreach ( $posts as $post ) {
		$terms = get_the_terms( $post, 'pall_tech_cat' );
		$slugs = array();
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$slugs[]               = $term->slug;
				$groups[ $term->slug ] = $term->name;
			}
		}
		$short = (string) pallcore_meta( $post->ID, 'short' ) ?: mb_substr( get_the_title( $post ), 0, 2 );
		$mark  = has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'thumbnail', array( 'alt' => '' ) ) : esc_html( $short );
		$note  = (string) pallcore_meta( $post->ID, 'note' );
		$url   = (string) pallcore_meta( $post->ID, 'url' );
		$tag   = $url ? 'a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow"' : 'div';
		$items .= '<' . $tag . ' class="pt-card pt-tech-item" data-cats="' . esc_attr( implode( ' ', $slugs ) ) . '"><span class="pt-tech-item__mark" aria-hidden="true">' . $mark . '</span>' . esc_html( get_the_title( $post ) ) . ( $note ? '<small>' . esc_html( $note ) . '</small>' : '' ) . '</' . ( $url ? 'a' : 'div' ) . '>';
	}
	$filter = '';
	if ( 'yes' === $a['filter'] && count( $groups ) > 1 ) {
		$filter = '<div class="pt-chips pt-tech-filter" data-pt-tech-filter role="group" aria-label="' . esc_attr__( 'Filter technologies', 'palltheme-core' ) . '"><button type="button" class="pt-chip is-active" data-cat="*" aria-pressed="true">' . esc_html__( 'All', 'palltheme-core' ) . '</button>';
		foreach ( $groups as $slug => $name ) {
			$filter .= '<button type="button" class="pt-chip" data-cat="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
		}
		$filter .= '</div>';
	}
	return pallcore_section( $filter . '<div class="pt-tech-grid">' . $items . '</div>', $a );
}

/**
 * Statistics.
 *
 * @param array $atts Attributes (items = "Value | Label" lines; default from settings).
 */
function pallcore_render_stats( $atts = array() ): string {
	$a    = pallcore_atts( array( 'items' => '', 'style' => 'band' ), $atts ); // band | cards | plain.
	$rows = $a['items'] ? pallcore_parse_pairs( str_replace( array( '\n', ';' ), "\n", $a['items'] ) ) : pallcore_stats();
	if ( ! $rows ) {
		return '';
	}
	$card  = 'cards' === $a['style'] ? ' pt-card' : '';
	$inner = '<div class="pt-stats' . ( 'plain' === $a['style'] || 'band' === $a['style'] ? ' pt-stats--plain' : '' ) . '">';
	foreach ( $rows as $row ) {
		$inner .= '<div class="pt-stat' . $card . ' pt-reveal"><span class="pt-stat__value" data-pt-count="' . esc_attr( $row[0] ) . '">' . esc_html( $row[0] ) . '</span><span class="pt-stat__label">' . esc_html( $row[1] ) . '</span></div>';
	}
	$inner .= '</div>';
	if ( 'band' === $a['style'] ) {
		$inner = '<div class="pt-stats-band">' . $inner . '</div>';
	}
	if ( '' === $a['spacing'] || 'normal' === $a['spacing'] ) {
		$a['spacing'] = 'tight';
	}
	return pallcore_section( $inner, $a );
}

/**
 * WooCommerce products.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_products( $atts = array() ): string {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '';
	}
	$a = pallcore_atts(
		array(
			'count'    => 8,
			'columns'  => 4,
			'category' => '',
			'ids'      => '',
			'show'     => 'recent', // recent | featured | sale | best_selling | top_rated.
		),
		$atts
	);
	$sc = array(
		'limit'   => (int) $a['count'],
		'columns' => (int) $a['columns'],
	);
	if ( $a['category'] ) {
		$sc['category'] = sanitize_text_field( $a['category'] );
	}
	if ( $a['ids'] ) {
		$sc['ids']     = sanitize_text_field( $a['ids'] );
		$sc['orderby'] = 'post__in';
	}
	switch ( $a['show'] ) {
		case 'featured':
			$sc['visibility'] = 'featured';
			break;
		case 'sale':
			$sc['on_sale'] = 'true';
			break;
		case 'best_selling':
			$sc['best_selling'] = 'true';
			break;
		case 'top_rated':
			$sc['top_rated'] = 'true';
			break;
		default:
			$sc['orderby'] = $sc['orderby'] ?? 'date';
			$sc['order']   = 'DESC';
	}
	$parts = array();
	foreach ( $sc as $k => $v ) {
		$parts[] = $k . '="' . esc_attr( (string) $v ) . '"';
	}
	// WooCommerce's [products] reads $GLOBALS['post'] unchecked; it is unset in REST/AJAX renders such as the Elementor editor.
	if ( ! array_key_exists( 'post', $GLOBALS ) ) {
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
	$html = do_shortcode( '[products ' . implode( ' ', $parts ) . ']' );
	if ( ! $a['link_text'] && ! $a['link_url'] ) {
		$a['link_text'] = __( 'Browse the store', 'palltheme-core' );
		$a['link_url']  = wc_get_page_permalink( 'shop' );
	}
	return pallcore_section( str_contains( $html, '<li' ) ? $html : '', $a, 'pt-section--products' );
}

/**
 * Blog posts.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_posts( $atts = array() ): string {
	$a    = pallcore_atts( array( 'count' => 3, 'columns' => 3, 'category' => '', 'orderby' => '' ), $atts );
	$args = array(
		'posts_per_page'      => max( 1, (int) $a['count'] ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'post_status'         => 'publish',
	);
	if ( $a['category'] ) {
		$args['category_name'] = sanitize_text_field( $a['category'] );
	}
	if ( 'comment_count' === $a['orderby'] ) {
		$args['orderby'] = 'comment_count';
	}
	return pallcore_section( pallcore_grid( get_posts( $args ), (int) $a['columns'] ), $a );
}

/**
 * Pricing table.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_pricing( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 4, 'columns' => 3, 'ids' => '' ), $atts );
	$posts = pallcore_query( 'pall_pricing', $a );
	if ( ! $posts ) {
		return '';
	}
	$html = '<div class="pt-grid pt-grid--' . max( 2, min( 4, (int) $a['columns'] ) ) . '">';
	foreach ( $posts as $post ) {
		$featured = (bool) pallcore_meta( $post->ID, 'featured' );
		$features = (array) ( pallcore_meta( $post->ID, 'features' ) ?: array() );
		$list     = '';
		foreach ( $features as $f ) {
			$list .= '<li>' . pallcore_ui_icon( 'check' ) . '<span>' . esc_html( $f ) . '</span></li>';
		}
		$badge = (string) pallcore_meta( $post->ID, 'badge' );
		$html .= sprintf(
			'<div class="pt-card pt-pricing pt-reveal%1$s">%2$s<h3 class="pt-pricing__name">%3$s</h3><p class="pt-pricing__desc">%4$s</p><div class="pt-pricing__price">%5$s <span class="pt-pricing__period">%6$s</span></div><ul class="pt-checklist">%7$s</ul><a class="pt-btn %8$s pt-btn--block" href="%9$s">%10$s</a></div>',
			$featured ? ' is-featured' : '',
			$badge ? '<span class="pt-pricing__badge">' . esc_html( $badge ) . '</span>' : '',
			esc_html( get_the_title( $post ) ),
			esc_html( get_the_excerpt( $post ) ),
			esc_html( (string) pallcore_meta( $post->ID, 'price' ) ),
			esc_html( (string) pallcore_meta( $post->ID, 'period' ) ),
			$list,
			$featured ? 'pt-btn--primary' : 'pt-btn--ghost',
			esc_url( (string) pallcore_meta( $post->ID, 'button_url' ) ?: ( pallcore_setting( 'quote_url' ) ?: home_url( '/contact/' ) ) ),
			esc_html( (string) pallcore_meta( $post->ID, 'button_text' ) ?: __( 'Get started', 'palltheme-core' ) )
		);
	}
	return pallcore_section( $html . '</div>', $a );
}

/**
 * FAQ list from the FAQ post type.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_faq( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 20, 'category' => '', 'ids' => '' ), $atts );
	$posts = pallcore_query( 'pall_faq', $a, 'pall_faq_cat' );
	$items = array();
	foreach ( $posts as $post ) {
		$items[] = array( get_the_title( $post ), $post->post_content );
	}
	$inner = pallcore_faq_html( $items );
	return pallcore_section( $inner ? '<div class="pt-container--narrow" style="margin-inline:auto;padding:0">' . $inner . '</div>' : '', $a );
}

/**
 * Job openings.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_jobs( $atts = array() ): string {
	$a     = pallcore_atts( array( 'count' => 20, 'category' => '' ), $atts );
	$posts = pallcore_query( 'pall_job', $a, 'pall_department' );
	if ( ! $posts ) {
		$email = (string) pallcore_setting( 'careers_email' );
		return pallcore_section(
			'<div class="pt-card pt-empty"><h3>' . esc_html__( 'No open positions right now', 'palltheme-core' ) . '</h3><p>' . esc_html__( 'We are always happy to meet talented engineers. Send us your CV.', 'palltheme-core' ) . '</p>' . ( $email ? '<a class="pt-btn pt-btn--primary" href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>' : '' ) . '</div>',
			$a
		);
	}
	return pallcore_section( '<div class="pt-jobs">' . implode( '', array_map( 'pallcore_card', $posts ) ) . '</div>', $a );
}

/**
 * Process / how we work steps.
 *
 * @param array $atts Attributes (steps = "Title | Text" lines).
 */
function pallcore_render_process( $atts = array() ): string {
	$a = pallcore_atts(
		array(
			'steps' => implode(
				"\n",
				array(
					__( 'Assess | We audit your infrastructure, security posture and business goals.', 'palltheme-core' ),
					__( 'Design | Our architects design a vendor-neutral, future-proof solution.', 'palltheme-core' ),
					__( 'Deploy | Certified engineers implement with zero-downtime migration plans.', 'palltheme-core' ),
					__( 'Manage | 24/7 monitoring, patching and optimisation keep you ahead.', 'palltheme-core' ),
				)
			),
		),
		$atts
	);
	$rows = pallcore_parse_pairs( str_replace( array( '\n', ';' ), "\n", $a['steps'] ) );
	if ( ! $rows ) {
		return '';
	}
	$html = '<div class="pt-grid pt-grid--4">';
	foreach ( $rows as $i => $row ) {
		$html .= '<div class="pt-card pt-feature pt-reveal"><span class="pt-feature__num">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span><h3 class="pt-feature__title">' . esc_html( $row[0] ) . '</h3><p>' . esc_html( $row[1] ) . '</p></div>';
	}
	return pallcore_section( $html . '</div>', $a );
}

/**
 * CTA band.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_cta( $atts = array() ): string {
	$a = pallcore_atts(
		array(
			'heading'     => (string) pallcore_setting( 'cta_title' ),
			'text'        => (string) pallcore_setting( 'cta_text' ),
			'button'      => (string) pallcore_setting( 'cta_button' ),
			'button_url'  => (string) pallcore_setting( 'quote_url' ) ?: home_url( '/contact/' ),
			'show_phone'  => 'yes',
		),
		$atts
	);
	$phone = 'yes' === $a['show_phone'] ? (string) pallcore_setting( 'phone' ) : '';
	$inner = sprintf(
		'<div class="pt-cta-band" data-scheme-lock="dark"><div class="pt-cta-band__glow" aria-hidden="true"></div><div class="pt-cta-band__text"><h2>%1$s</h2><p>%2$s</p></div><div class="pt-btn-row"><a class="pt-btn pt-btn--accent" href="%3$s">%4$s%5$s</a>%6$s</div></div>',
		pallcore_highlight( $a['heading'] ),
		esc_html( $a['text'] ),
		esc_url( $a['button_url'] ),
		esc_html( $a['button'] ),
		pallcore_ui_icon( 'arrow' ),
		$phone ? '<a class="pt-btn pt-btn--glass" href="tel:' . esc_attr( pallcore_phone_digits( $phone ) ) . '">' . pallcore_ui_icon( 'phone' ) . esc_html( $phone ) . '</a>' : ''
	);
	if ( 'normal' === $a['spacing'] ) {
		$a['spacing'] = 'tight';
	}
	return pallcore_section( $inner, $a );
}

/**
 * Contact block: info cards + form + map.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_contact( $atts = array() ): string {
	$a       = pallcore_atts( array( 'map' => 'yes', 'form' => 'yes' ), $atts );
	$phone   = (string) pallcore_setting( 'phone' );
	$email   = (string) pallcore_setting( 'email' );
	$address = (string) pallcore_setting( 'address' );
	$hours   = pallcore_parse_pairs( (string) pallcore_setting( 'hours' ) );
	$wa      = (string) pallcore_setting( 'whatsapp' );

	$items = '';
	if ( $phone ) {
		$tg     = (string) pallcore_setting( 'telegram' );
		$extras = ( $wa ? '<p><a href="https://wa.me/' . esc_attr( pallcore_phone_digits( $wa, false ) ) . '" target="_blank" rel="noopener">WhatsApp: ' . esc_html( $wa ) . '</a></p>' : '' )
			. ( $tg ? '<p><a href="https://t.me/' . esc_attr( rawurlencode( ltrim( $tg, '@' ) ) ) . '" target="_blank" rel="noopener">Telegram: @' . esc_html( ltrim( $tg, '@' ) ) . '</a></p>' : '' );
		$items .= '<li class="pt-card pt-contact-item"><span class="pt-icon-tile">' . pallcore_ui_icon( 'phone' ) . '</span><div><h3>' . esc_html__( 'Call us', 'palltheme-core' ) . '</h3><a href="tel:' . esc_attr( pallcore_phone_digits( $phone ) ) . '">' . esc_html( $phone ) . '</a>' . $extras . '</div></li>';
	}
	if ( $email ) {
		$items .= '<li class="pt-card pt-contact-item"><span class="pt-icon-tile">' . pallcore_ui_icon( 'mail' ) . '</span><div><h3>' . esc_html__( 'Email', 'palltheme-core' ) . '</h3><a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a></div></li>';
	}
	if ( $address ) {
		$items .= '<li class="pt-card pt-contact-item"><span class="pt-icon-tile">' . pallcore_ui_icon( 'pin' ) . '</span><div><h3>' . esc_html__( 'Visit us', 'palltheme-core' ) . '</h3><p>' . nl2br( esc_html( $address ) ) . '</p></div></li>';
	}
	if ( $hours ) {
		$list = '';
		foreach ( $hours as $row ) {
			$list .= '<li><span>' . esc_html( $row[0] ) . '</span><span>' . esc_html( $row[1] ) . '</span></li>';
		}
		$items .= '<li class="pt-card pt-contact-item"><span class="pt-icon-tile">' . pallcore_ui_icon( 'clock' ) . '</span><div style="flex:1"><h3>' . esc_html__( 'Business hours', 'palltheme-core' ) . '</h3><ul class="pt-hours">' . $list . '</ul></div></li>';
	}

	$social = '';
	foreach ( array( 'facebook', 'instagram', 'linkedin', 'youtube', 'x', 'tiktok' ) as $net ) {
		$url = (string) pallcore_setting( 'social_' . $net );
		if ( $url ) {
			$social .= '<li><a class="pt-chip" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( 'x' === $net ? 'X' : ucfirst( $net ) ) . '</a></li>';
		}
	}
	if ( $social ) {
		$items .= '<li><ul class="pt-chips">' . $social . '</ul></li>';
	}

	$form = 'yes' === $a['form'] ? '<div class="pt-card pt-form-card"><h2 class="pt-section-title" style="font-size:1.5rem">' . esc_html__( 'Send us a message', 'palltheme-core' ) . '</h2>' . pallcore_render_contact_form() . '</div>' : '';
	$html = '<div class="pt-contact-grid"><ul class="pt-contact-list">' . $items . '</ul>' . $form . '</div>';

	$map_q = (string) pallcore_setting( 'map_query' );
	if ( 'yes' === $a['map'] && $map_q ) {
		$src   = 'https://www.google.com/maps?q=' . rawurlencode( $map_q ) . '&output=embed';
		$html .= '<div class="pt-map" style="margin-top:28px" data-pt-map="' . esc_url( $src ) . '"><button type="button" class="pt-btn pt-btn--ghost" style="margin:auto;display:flex;height:100%;width:100%;border:0;border-radius:0" data-pt-map-load>' . pallcore_ui_icon( 'pin' ) . esc_html__( 'Show map (loads Google Maps)', 'palltheme-core' ) . '</button></div>';
	}
	return pallcore_section( $html, $a );
}

/**
 * Contact form: a configured form-plugin shortcode, or the built-in form.
 */
function pallcore_render_contact_form(): string {
	$shortcode = trim( (string) pallcore_setting( 'form_shortcode' ) );
	if ( $shortcode && preg_match( '/^\[[a-zA-Z0-9_\-]+[^\]]*\]$/', $shortcode ) ) {
		return do_shortcode( $shortcode );
	}
	return pallcore_builtin_form();
}

/**
 * Default homepage: sections in the order set in Business Settings.
 */
function pallcore_default_homepage(): string {
	$order = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n|,/', (string) pallcore_setting( 'home_sections' ) ) ) );
	$map   = pallcore_homepage_section_map();
	$html  = '';
	foreach ( $order as $key ) {
		if ( isset( $map[ $key ] ) ) {
			$html .= call_user_func( $map[ $key ][0], $map[ $key ][1] );
		}
	}
	return $html;
}

/**
 * Homepage section map: key => [renderer, default attributes].
 *
 * @return array<string,array{0:callable,1:array}>
 */
function pallcore_homepage_section_map(): array {
	return array(
		'hero'         => array( 'pallcore_render_hero', array( 'stats' => 'no' ) ),
		'clients'      => array( 'pallcore_render_clients', array( 'eyebrow' => __( 'Trusted by industry leaders', 'palltheme-core' ), 'align' => 'center', 'spacing' => 'tight' ) ),
		'services'     => array( 'pallcore_render_services', array( 'eyebrow' => __( 'What we do', 'palltheme-core' ), 'title' => __( 'End-to-end *IT services* for modern businesses', 'palltheme-core' ), 'lead' => __( 'From the network edge to the cloud, our certified engineers design, deploy and run the technology your business depends on.', 'palltheme-core' ), 'link_text' => __( 'All services', 'palltheme-core' ), 'link_url' => get_post_type_archive_link( 'pall_service' ) ) ),
		'stats'        => array( 'pallcore_render_stats', array() ),
		'solutions'    => array( 'pallcore_render_solutions', array( 'eyebrow' => __( 'Industry solutions', 'palltheme-core' ), 'title' => __( 'Built for the way *your industry* works', 'palltheme-core' ), 'background' => 'alt', 'link_text' => __( 'All solutions', 'palltheme-core' ), 'link_url' => get_post_type_archive_link( 'pall_solution' ) ) ),
		'products'     => array( 'pallcore_render_products', array( 'eyebrow' => __( 'Technology store', 'palltheme-core' ), 'title' => __( 'Enterprise hardware, *ready to ship*', 'palltheme-core' ), 'show' => 'featured' ) ),
		'process'      => array( 'pallcore_render_process', array( 'eyebrow' => __( 'How we work', 'palltheme-core' ), 'title' => __( 'A proven delivery *process*', 'palltheme-core' ), 'background' => 'alt' ) ),
		'cases'        => array( 'pallcore_render_case_studies', array( 'eyebrow' => __( 'Case studies', 'palltheme-core' ), 'title' => __( 'Results that *speak for themselves*', 'palltheme-core' ), 'link_text' => __( 'All case studies', 'palltheme-core' ), 'link_url' => get_post_type_archive_link( 'pall_case_study' ) ) ),
		'technologies' => array( 'pallcore_render_technologies', array( 'eyebrow' => __( 'Technology stack', 'palltheme-core' ), 'title' => __( 'Vendor-neutral expertise across *leading platforms*', 'palltheme-core' ), 'align' => 'center', 'background' => 'alt' ) ),
		'testimonials' => array( 'pallcore_render_testimonials', array( 'eyebrow' => __( 'Testimonials', 'palltheme-core' ), 'title' => __( 'What our *clients* say', 'palltheme-core' ) ) ),
		'pricing'      => array( 'pallcore_render_pricing', array( 'eyebrow' => __( 'Pricing', 'palltheme-core' ), 'title' => __( 'Simple, *transparent* plans', 'palltheme-core' ), 'align' => 'center' ) ),
		'blog'         => array( 'pallcore_render_posts', array( 'eyebrow' => __( 'Insights', 'palltheme-core' ), 'title' => __( 'Latest from our *engineers*', 'palltheme-core' ), 'background' => 'alt', 'link_text' => __( 'Read the blog', 'palltheme-core' ), 'link_url' => get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ) ) ),
		'faq'          => array( 'pallcore_render_faq', array( 'eyebrow' => __( 'FAQ', 'palltheme-core' ), 'title' => __( 'Questions, answered', 'palltheme-core' ), 'align' => 'center' ) ),
		'cta'          => array( 'pallcore_render_cta', array() ),
	);
}
