<?php
/**
 * Template tags used across templates.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output the site logo (default, light, dark and sticky variants are all
 * printed; CSS decides which one is visible).
 */
function palltheme_logo(): void {
	$home   = esc_url( home_url( '/' ) );
	$name   = get_bloginfo( 'name' );
	$main   = (int) get_theme_mod( 'custom_logo' );
	$light  = (int) palltheme_mod( 'logo_light' );
	$dark   = (int) palltheme_mod( 'logo_dark' );
	$sticky = (int) palltheme_mod( 'logo_sticky' );

	echo '<a class="pt-logo" href="' . $home . '" rel="home">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	if ( ! $main ) {
		printf(
			'<span class="pt-logo__mark" aria-hidden="true">%1$s</span><span class="pt-logo__text">%2$s</span>',
			esc_html( mb_substr( $name, 0, 1 ) ),
			esc_html( $name )
		);
		echo '</a>';
		return;
	}

	$variants = array(
		'default' => $main,
		'light'   => $light,
		'dark'    => $dark,
		'sticky'  => $sticky,
	);
	foreach ( $variants as $variant => $id ) {
		if ( ! $id ) {
			continue;
		}
		echo wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'class'         => 'pt-logo__img pt-logo__img--' . $variant,
				'alt'           => 'default' === $variant ? $name : '',
				'loading'       => 'eager',
				'fetchpriority' => 'default' === $variant ? 'high' : 'low',
			)
		);
	}
	echo '</a>';
}

/**
 * Breadcrumbs: Rank Math → Yoast → WooCommerce → built-in fallback.
 */
function palltheme_breadcrumbs(): void {
	if ( is_front_page() ) {
		return;
	}
	if ( function_exists( 'rank_math_the_breadcrumbs' ) && class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'is_breadcrumbs_enabled' ) && \RankMath\Helper::is_breadcrumbs_enabled() ) {
		echo '<div class="pt-breadcrumbs">';
		rank_math_the_breadcrumbs();
		echo '</div>';
		return;
	}
	if ( function_exists( 'yoast_breadcrumb' ) ) {
		$opts = get_option( 'wpseo_titles' );
		if ( ! empty( $opts['breadcrumbs-enable'] ) ) {
			yoast_breadcrumb( '<div class="pt-breadcrumbs">', '</div>' );
			return;
		}
	}
	if ( function_exists( 'woocommerce_breadcrumb' ) && function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
		woocommerce_breadcrumb(
			array(
				'wrap_before' => '<nav class="pt-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'palltheme' ) . '"><ol>',
				'wrap_after'  => '</ol></nav>',
				'before'      => '<li>',
				'after'       => '</li>',
				'delimiter'   => '',
			)
		);
		return;
	}

	$crumbs   = array( array( __( 'Home', 'palltheme' ), home_url( '/' ) ) );
	$obj      = get_queried_object();
	$post_obj = null;

	if ( is_singular() && $obj instanceof WP_Post ) {
		$post_obj = $obj;
		if ( 'post' === $obj->post_type ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$crumbs[] = array( get_the_title( $page_for_posts ), get_permalink( $page_for_posts ) );
			}
			$cats = get_the_category( $obj->ID );
			if ( $cats ) {
				$crumbs[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
			}
		} elseif ( 'page' !== $obj->post_type ) {
			$pto = get_post_type_object( $obj->post_type );
			if ( $pto && $pto->has_archive ) {
				$crumbs[] = array( $pto->labels->name, get_post_type_archive_link( $obj->post_type ) );
			}
		} else {
			foreach ( array_reverse( get_post_ancestors( $obj ) ) as $ancestor ) {
				$crumbs[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
			}
		}
		$crumbs[] = array( get_the_title( $obj ), '' );
	} elseif ( is_post_type_archive() ) {
		$crumbs[] = array( post_type_archive_title( '', false ), '' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$crumbs[] = array( single_term_title( '', false ), '' );
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$crumbs[] = array( sprintf( __( 'Search: %s', 'palltheme' ), get_search_query() ), '' );
	} elseif ( is_404() ) {
		$crumbs[] = array( __( 'Not found', 'palltheme' ), '' );
	} elseif ( is_home() ) {
		$crumbs[] = array( __( 'Blog', 'palltheme' ), '' );
	} elseif ( is_author() ) {
		$crumbs[] = array( get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) ), '' );
	} elseif ( is_archive() ) {
		$crumbs[] = array( get_the_archive_title(), '' );
	}

	echo '<nav class="pt-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'palltheme' ) . '"><ol>';
	$last = count( $crumbs ) - 1;
	foreach ( $crumbs as $i => $crumb ) {
		if ( $i === $last || '' === $crumb[1] ) {
			echo '<li aria-current="page">' . esc_html( wp_strip_all_tags( $crumb[0] ) ) . '</li>';
		} else {
			echo '<li><a href="' . esc_url( $crumb[1] ) . '">' . esc_html( wp_strip_all_tags( $crumb[0] ) ) . '</a></li>';
		}
	}
	echo '</ol></nav>';

	// BreadcrumbList schema only when no SEO plugin handles it.
	if ( ! palltheme_seo_plugin_active() ) {
		$items = array();
		foreach ( $crumbs as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( $crumb[0] ),
				'item'     => $crumb[1] ?: ( $post_obj ? get_permalink( $post_obj ) : '' ),
			);
		}
		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode(
				array(
					'@context'        => 'https://schema.org',
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $items,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}
}

/**
 * Page header band (title + breadcrumbs + optional description).
 *
 * @param string $title       Title (already plain text).
 * @param string $description Optional description (HTML allowed: post kses).
 * @param string $eyebrow     Small label above the title.
 */
function palltheme_page_header( string $title, string $description = '', string $eyebrow = '' ): void {
	?>
	<header class="pt-page-header">
		<div class="pt-page-header__bg" aria-hidden="true"></div>
		<div class="pt-container">
			<?php palltheme_breadcrumbs(); ?>
			<?php if ( $eyebrow ) : ?>
				<p class="pt-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h1 class="pt-page-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $description ) : ?>
				<div class="pt-page-header__desc"><?php echo wp_kses_post( $description ); ?></div>
			<?php endif; ?>
		</div>
	</header>
	<?php
}

/**
 * Post meta line: author, date, reading time, category.
 */
function palltheme_post_meta(): void {
	$parts = array();

	$parts[] = sprintf(
		'<span class="pt-meta__author">%1$s <a href="%2$s">%3$s</a></span>',
		get_avatar( get_the_author_meta( 'ID' ), 28, '', '', array( 'class' => 'pt-meta__avatar' ) ),
		esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ),
		esc_html( get_the_author() )
	);
	$parts[] = sprintf(
		'<time datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
	if ( palltheme_mod( 'blog_reading_time' ) ) {
		/* translators: %d: minutes. */
		$parts[] = '<span>' . esc_html( sprintf( _n( '%d min read', '%d min read', palltheme_reading_time(), 'palltheme' ), palltheme_reading_time() ) ) . '</span>';
	}

	echo '<div class="pt-meta">' . implode( '<span class="pt-meta__sep" aria-hidden="true">•</span>', $parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Social sharing links (plain links — no third-party scripts).
 */
function palltheme_share_links(): void {
	if ( ! palltheme_mod( 'blog_share' ) ) {
		return;
	}
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( wp_strip_all_tags( get_the_title() ) );
	$links = array(
		'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
		'x'        => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
		'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
	);
	echo '<div class="pt-share"><span class="pt-share__label">' . esc_html__( 'Share', 'palltheme' ) . '</span>';
	foreach ( $links as $network => $href ) {
		printf(
			'<a class="pt-share__link" href="%1$s" target="_blank" rel="noopener noreferrer nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( $href ),
			/* translators: %s: network name. */
			esc_attr( sprintf( __( 'Share on %s', 'palltheme' ), palltheme_social_label( $network ) ) ),
			palltheme_social_icon( $network ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	printf(
		'<button type="button" class="pt-share__link" data-pt-copy="%1$s" aria-label="%2$s">%3$s</button>',
		esc_url( get_permalink() ),
		esc_attr__( 'Copy link', 'palltheme' ),
		palltheme_icon( 'share' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
	echo '</div>';
}

/**
 * Author box.
 */
function palltheme_author_box(): void {
	if ( ! palltheme_mod( 'blog_author_box' ) ) {
		return;
	}
	$id  = (int) get_the_author_meta( 'ID' );
	$bio = get_the_author_meta( 'description', $id );
	?>
	<aside class="pt-author-box pt-card">
		<?php echo get_avatar( $id, 80 ); ?>
		<div>
			<p class="pt-eyebrow"><?php esc_html_e( 'Written by', 'palltheme' ); ?></p>
			<h2 class="pt-author-box__name"><a href="<?php echo esc_url( get_author_posts_url( $id ) ); ?>"><?php echo esc_html( get_the_author() ); ?></a></h2>
			<?php if ( $bio ) : ?>
				<p><?php echo esc_html( $bio ); ?></p>
			<?php endif; ?>
		</div>
	</aside>
	<?php
}

/**
 * Related posts by shared category (cached in a transient).
 */
function palltheme_related_posts( int $count = 3 ): void {
	$post_id = get_the_ID();
	$key     = 'pt_related_' . $post_id;
	$ids     = get_transient( $key );

	if ( false === $ids ) {
		$cats = wp_get_post_categories( $post_id );
		$q    = new WP_Query(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => $count,
				'post__not_in'        => array( $post_id ),
				'category__in'        => $cats,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'fields'              => 'ids',
			)
		);
		$ids = $q->posts;
		set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
	}
	if ( empty( $ids ) ) {
		return;
	}

	$q = new WP_Query(
		array(
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	echo '<section class="pt-related"><h2 class="pt-section-title">' . esc_html__( 'Related articles', 'palltheme' ) . '</h2><div class="pt-grid pt-grid--3">';
	while ( $q->have_posts() ) {
		$q->the_post();
		get_template_part( 'template-parts/content/card', 'post' );
	}
	echo '</div></section>';
	wp_reset_postdata();
}

/**
 * Bust related-post cache when a post is saved.
 */
add_action( 'save_post_post', static fn( $id ) => delete_transient( 'pt_related_' . $id ) );

/**
 * Numbered pagination.
 */
function palltheme_pagination(): void {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => '<span class="screen-reader-text">' . __( 'Previous', 'palltheme' ) . '</span>&larr;',
			'next_text'          => '<span class="screen-reader-text">' . __( 'Next', 'palltheme' ) . '</span>&rarr;',
			'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'palltheme' ) . ' </span>',
			'class'              => 'pt-pagination',
		)
	);
}

/**
 * Archive title without the "Category:" prefix.
 */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
