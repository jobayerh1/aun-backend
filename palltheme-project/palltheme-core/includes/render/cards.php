<?php
/**
 * Card renderers. One function per content type; pallcore_card() dispatches.
 * All output is escaped here.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the right card for a post.
 *
 * @param WP_Post|int|null $post Post.
 */
function pallcore_card( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	switch ( $post->post_type ) {
		case 'pall_service':
		case 'pall_solution':
			return pallcore_card_service( $post );
		case 'pall_case_study':
			return pallcore_card_case( $post );
		case 'pall_team':
			return pallcore_card_team( $post );
		case 'pall_testimonial':
			return pallcore_card_testimonial( $post );
		case 'pall_job':
			return pallcore_card_job( $post );
		default:
			return pallcore_card_post( $post );
	}
}

/**
 * Service / solution card.
 */
function pallcore_card_service( WP_Post $post ): string {
	$meta = '';
	if ( 'pall_solution' === $post->post_type ) {
		$terms = get_the_terms( $post, 'pall_industry' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$meta = '<span class="pt-service-card__meta">' . esc_html( $terms[0]->name ) . '</span>';
		}
	}
	return sprintf(
		'<article class="pt-card pt-service-card pt-reveal"><span class="pt-icon-tile">%1$s</span>%2$s<h3 class="pt-service-card__title"><a href="%3$s">%4$s</a></h3><p class="pt-service-card__text">%5$s</p><span class="pt-link-arrow" aria-hidden="true">%6$s%7$s</span></article>',
		wp_kses( pallcore_post_icon( $post->ID ), array_merge( pallcore_svg_kses(), array( 'img' => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'loading' => true, 'srcset' => true, 'sizes' => true, 'decoding' => true ) ) ) ),
		$meta,
		esc_url( get_permalink( $post ) ),
		esc_html( get_the_title( $post ) ),
		esc_html( wp_trim_words( get_the_excerpt( $post ), 22 ) ),
		esc_html__( 'Learn more', 'palltheme-core' ),
		pallcore_ui_icon( 'arrow' )
	);
}

/**
 * Case study card with up to 3 results.
 */
function pallcore_card_case( WP_Post $post ): string {
	$terms    = get_the_terms( $post, 'pall_industry' );
	$industry = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : (string) pallcore_meta( $post->ID, 'client' );
	$results  = array_slice( (array) ( pallcore_meta( $post->ID, 'results' ) ?: array() ), 0, 3 );

	$res_html = '';
	if ( $results ) {
		$res_html = '<div class="pt-case-card__results">';
		foreach ( $results as $row ) {
			$res_html .= '<div><strong>' . esc_html( $row[0] ?? '' ) . '</strong><span>' . esc_html( $row[1] ?? '' ) . '</span></div>';
		}
		$res_html .= '</div>';
	}

	$img = has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'palltheme-card', array( 'alt' => '' ) ) : '';

	return sprintf(
		'<article class="pt-card pt-case-card pt-reveal"><div class="pt-case-card__media">%1$s<span class="pt-case-card__industry">%2$s</span></div><div class="pt-case-card__body"><h3 class="pt-case-card__title"><a href="%3$s">%4$s</a></h3><p class="pt-service-card__text">%5$s</p>%6$s</div></article>',
		$img, // Core image markup.
		esc_html( $industry ),
		esc_url( get_permalink( $post ) ),
		esc_html( get_the_title( $post ) ),
		esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ),
		$res_html
	);
}

/**
 * Team card.
 */
function pallcore_card_team( WP_Post $post ): string {
	$photo = has_post_thumbnail( $post )
		? get_the_post_thumbnail( $post, 'palltheme-square', array( 'alt' => '' ) )
		: '<span class="pt-media-fallback">' . pallcore_icon( 'users' ) . '</span>';
	return sprintf(
		'<article class="pt-card pt-team-card pt-reveal"><div class="pt-team-card__photo">%1$s</div><div class="pt-team-card__body"><h3 class="pt-team-card__name"><a href="%2$s">%3$s</a></h3><p class="pt-team-card__role">%4$s</p></div></article>',
		$photo,
		esc_url( get_permalink( $post ) ),
		esc_html( get_the_title( $post ) ),
		esc_html( (string) pallcore_meta( $post->ID, 'position' ) )
	);
}

/**
 * Testimonial card.
 */
function pallcore_card_testimonial( WP_Post $post ): string {
	$rating = (int) ( pallcore_meta( $post->ID, 'rating' ) ?: 5 );
	$role   = trim( implode( ', ', array_filter( array( pallcore_meta( $post->ID, 'position' ), pallcore_meta( $post->ID, 'company' ) ) ) ) );
	$logo   = (int) pallcore_meta( $post->ID, 'company_logo' );
	$photo  = has_post_thumbnail( $post ) ? get_the_post_thumbnail( $post, 'thumbnail', array( 'alt' => '' ) ) : '';

	return sprintf(
		'<figure class="pt-card pt-testimonial pt-reveal"><div class="pt-testimonial__top">%1$s%6$s</div><blockquote>%2$s</blockquote><figcaption>%3$s<span><span class="pt-testimonial__name">%4$s</span><span class="pt-testimonial__role">%5$s</span></span></figcaption></figure>',
		$rating ? pallcore_stars( $rating ) : '',
		wp_kses_post( wpautop( $post->post_content ) ),
		$photo,
		esc_html( get_the_title( $post ) ),
		esc_html( $role ),
		$logo ? wp_get_attachment_image( $logo, 'medium', false, array( 'class' => 'pt-testimonial__logo', 'alt' => esc_attr( (string) pallcore_meta( $post->ID, 'company' ) ) ) ) : ''
	);
}

/**
 * Job row.
 */
function pallcore_card_job( WP_Post $post ): string {
	$chips = '';
	foreach ( array( 'location', 'job_type', 'department' ) as $key ) {
		$val = (string) pallcore_meta( $post->ID, $key );
		if ( $val ) {
			$chips .= '<span class="pt-chip">' . esc_html( $val ) . '</span>';
		}
	}
	return sprintf(
		'<article class="pt-card pt-card--hover pt-job-row"><h3><a href="%1$s">%2$s</a></h3><div class="pt-job-row__meta">%3$s</div><span class="pt-link-arrow" aria-hidden="true">%4$s%5$s</span></article>',
		esc_url( get_permalink( $post ) ),
		esc_html( get_the_title( $post ) ),
		$chips,
		esc_html__( 'View role', 'palltheme-core' ),
		pallcore_ui_icon( 'arrow' )
	);
}

/**
 * Blog post card.
 */
function pallcore_card_post( WP_Post $post ): string {
	$cats = get_the_category( $post->ID );
	$img  = has_post_thumbnail( $post )
		? get_the_post_thumbnail( $post, 'palltheme-card', array( 'alt' => '' ) )
		: '<span class="pt-media-fallback">' . pallcore_ui_icon( 'bolt' ) . '</span>';
	$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $post->post_content ) ) / 220 ) );
	return sprintf(
		'<article class="pt-card pt-post-card pt-reveal"><a class="pt-post-card__media" href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a><div class="pt-post-card__body">%3$s<h3 class="pt-post-card__title"><a href="%1$s">%4$s</a></h3><p class="pt-post-card__excerpt">%5$s</p><div class="pt-post-card__meta"><time datetime="%6$s">%7$s</time><span aria-hidden="true">•</span><span>%8$s</span></div></div></article>',
		esc_url( get_permalink( $post ) ),
		$img,
		$cats ? '<span class="pt-tag">' . esc_html( $cats[0]->name ) . '</span>' : '',
		esc_html( get_the_title( $post ) ),
		esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ),
		esc_attr( get_the_date( DATE_W3C, $post ) ),
		esc_html( get_the_date( '', $post ) ),
		/* translators: %d: minutes. */
		esc_html( sprintf( __( '%d min read', 'palltheme-core' ), $minutes ) )
	);
}
