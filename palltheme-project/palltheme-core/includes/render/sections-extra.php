<?php
/**
 * Additional section renderers: feature/trust cards, split (image + text)
 * blocks and the media credits table. Same contract as sections.php:
 * attributes in, escaped HTML out, used by shortcodes + Elementor widgets.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse "a | b | c | d" lines into rows with a fixed number of columns.
 * A literal "\n" (as written in shortcode attributes) also starts a new row.
 *
 * @return array<int,array<int,string>>
 */
function pallcore_parse_rows( string $text, int $columns ): array {
	$text = str_replace( array( '\n', '\r' ), "\n", $text );
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts  = array_map( 'trim', explode( '|', $line, $columns ) );
		$rows[] = array_pad( $parts, $columns, '' );
	}
	return $rows;
}

/**
 * Feature / trust / value cards.
 *
 * Items: one per line — "icon | Title | Short text | optional link".
 * Icons are names from the built-in icon library (server, cloud, shield…).
 *
 * @param array  $atts    Attributes (items, columns, style = cards|trust|minimal).
 * @param string $content Shortcode content (alternative to the items attribute).
 */
function pallcore_render_features( $atts = array(), $content = '' ): string {
	$a = pallcore_atts(
		array(
			'items'   => '',
			'columns' => 3,
			'style'   => 'cards',
		),
		$atts
	);
	$source = '' !== trim( (string) $a['items'] ) ? (string) $a['items'] : ( '' !== trim( (string) $content ) ? wp_strip_all_tags( (string) $content ) : (string) pallcore_setting( 'trust_items' ) );
	$rows   = pallcore_parse_rows( $source, 4 );
	if ( ! $rows ) {
		return '';
	}

	$style = in_array( $a['style'], array( 'cards', 'trust', 'minimal' ), true ) ? $a['style'] : 'cards';
	$cols  = max( 2, min( 4, (int) $a['columns'] ) );
	$html  = '<div class="pt-features pt-features--' . esc_attr( $style ) . ' pt-grid pt-grid--' . $cols . '">';

	foreach ( $rows as [ $icon, $title, $text, $url ] ) {
		$icon_html = pallcore_icon( $icon ?: 'check' );
		$inner     = '<span class="pt-icon-tile">' . $icon_html . '</span><div class="pt-feature-card__body"><h3 class="pt-feature-card__title">'
			. ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) )
			. '</h3>' . ( $text ? '<p>' . esc_html( $text ) . '</p>' : '' )
			. ( $url && 'cards' === $style ? '<span class="pt-link-arrow" aria-hidden="true">' . esc_html__( 'Learn more', 'palltheme-core' ) . pallcore_ui_icon( 'arrow' ) . '</span>' : '' )
			. '</div>';
		$html     .= '<div class="pt-feature-card' . ( 'cards' === $style ? ' pt-card' : '' ) . ( $url ? ' is-linked' : '' ) . ' pt-reveal">' . $inner . '</div>';
	}
	$html .= '</div>';

	if ( 'trust' === $style && 'normal' === $a['spacing'] ) {
		$a['spacing'] = 'tight';
	}
	return pallcore_section( $html, $a, 'pt-section--features' );
}

/**
 * Split block: image (or Lottie) beside text, bullets and buttons.
 *
 * @param array  $atts    Attributes.
 * @param string $content Body text (alternative to the text attribute).
 */
function pallcore_render_split( $atts = array(), $content = '' ): string {
	$a = pallcore_atts(
		array(
			'image'        => '',
			'image_url'    => '',
			'image_alt'    => '',
			'lottie'       => '',
			'text'         => '',
			'bullets'      => '',
			'button_text'  => '',
			'button_url'   => '',
			'button2_text' => '',
			'button2_url'  => '',
			'reverse'      => 'no',
			'badge_value'  => '',
			'badge_label'  => '',
			'priority'     => 'no', // "yes" when the section is the first thing on the page: load its image first.
		),
		$atts
	);

	// Media. Lazy by default; a section at the top of the page is usually the largest paint, so it loads eagerly.
	$first = 'yes' === $a['priority'];
	$load  = $first ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' );
	$media = '';
	if ( $a['lottie'] ) {
		$media = '<div class="pt-split-media__lottie" data-pt-lottie="' . esc_url( $a['lottie'] ) . '" aria-hidden="true"></div>';
	} elseif ( $a['image'] ) {
		$media = (string) wp_get_attachment_image( (int) $a['image'], 'large', false, $load + array( 'class' => 'pt-split-media__img' ) );
	} elseif ( $a['image_url'] ) {
		$media = '<img class="pt-split-media__img" src="' . esc_url( $a['image_url'] ) . '" alt="' . esc_attr( $a['image_alt'] ) . '" loading="' . ( $first ? 'eager" fetchpriority="high' : 'lazy' ) . '" width="1200" height="800">';
	}
	if ( $media && $a['badge_value'] ) {
		$media .= '<div class="pt-float-card pt-split-media__badge"><span><strong data-pt-count="' . esc_attr( $a['badge_value'] ) . '">' . esc_html( $a['badge_value'] ) . '</strong><span>' . esc_html( $a['badge_label'] ) . '</span></span></div>';
	}

	// Text.
	$body_src = '' !== trim( (string) $content ) ? (string) $content : str_replace( array( '\n\n', '\n' ), array( "\n\n", "\n" ), (string) $a['text'] );
	$body     = $body_src ? '<div class="pt-split-media__text">' . wp_kses_post( wpautop( $body_src ) ) . '</div>' : '';

	$bullets = '';
	foreach ( pallcore_parse_rows( (string) $a['bullets'], 1 ) as [ $item ] ) {
		$bullets .= '<li>' . pallcore_ui_icon( 'check' ) . '<span>' . esc_html( $item ) . '</span></li>';
	}
	$bullets = $bullets ? '<ul class="pt-checklist pt-checklist--cols">' . $bullets . '</ul>' : '';

	$buttons = '';
	if ( $a['button_text'] && $a['button_url'] ) {
		$buttons .= '<a class="pt-btn pt-btn--primary" href="' . esc_url( $a['button_url'] ) . '">' . esc_html( $a['button_text'] ) . pallcore_ui_icon( 'arrow' ) . '</a>';
	}
	if ( $a['button2_text'] && $a['button2_url'] ) {
		$buttons .= '<a class="pt-btn pt-btn--ghost" href="' . esc_url( $a['button2_url'] ) . '">' . esc_html( $a['button2_text'] ) . '</a>';
	}
	$buttons = $buttons ? '<div class="pt-btn-row">' . $buttons . '</div>' : '';

	// The heading lives inside the text column here, not above the grid.
	$head_atts          = $a;
	$head_atts['align'] = 'left';
	$head               = pallcore_section_head( array_merge( $head_atts, array( 'link_text' => '', 'link_url' => '' ) ) );

	$inner = sprintf(
		'<div class="pt-split-media%1$s">%2$s<div class="pt-split-media__body">%3$s%4$s%5$s%6$s</div></div>',
		'yes' === $a['reverse'] ? ' pt-split-media--reverse' : '',
		$media ? '<figure class="pt-split-media__figure pt-reveal">' . $media . '</figure>' : '',
		$head,
		$body,
		$bullets,
		$buttons
	);

	// Section wrapper without repeating the heading.
	$wrap_atts            = $a;
	$wrap_atts['title']   = '';
	$wrap_atts['eyebrow'] = '';
	$wrap_atts['lead']    = '';
	return pallcore_section( $inner, $wrap_atts, 'pt-section--split' );
}

/**
 * Media credits table built from attachment credit fields.
 * Every image with a "Source" credit is listed — demo or uploaded later.
 *
 * @param array $atts Attributes.
 */
function pallcore_render_media_credits( $atts = array() ): string {
	$a   = pallcore_atts( array(), $atts );
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'meta_key'       => '_pall_credit_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	if ( ! $ids ) {
		return pallcore_section( '<p>' . esc_html__( 'All images on this website are original works or owned by the company.', 'palltheme-core' ) . '</p>', $a );
	}
	$rows = '';
	foreach ( $ids as $id ) {
		$m       = static fn( $k ) => (string) get_post_meta( $id, '_pall_credit_' . $k, true );
		$author  = $m( 'author_url' ) ? '<a href="' . esc_url( $m( 'author_url' ) ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $m( 'author' ) ) . '</a>' : esc_html( $m( 'author' ) );
		$source  = $m( 'url' ) ? '<a href="' . esc_url( $m( 'url' ) ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $m( 'source' ) ) . '</a>' : esc_html( $m( 'source' ) );
		$license = $m( 'license_url' ) ? '<a href="' . esc_url( $m( 'license_url' ) ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $m( 'license' ) ) . '</a>' : esc_html( $m( 'license' ) );
		$thumb   = wp_attachment_is_image( $id ) ? wp_get_attachment_image( $id, array( 96, 64 ), false, array( 'loading' => 'lazy' ) ) : '';
		$rows   .= '<tr><td>' . $thumb . '</td><td>' . esc_html( get_the_title( $id ) ) . ( $m( 'usage' ) ? '<br><small>' . esc_html( $m( 'usage' ) ) . '</small>' : '' ) . '</td><td>' . $author . '</td><td>' . $source . '</td><td>' . $license . '</td></tr>';
	}
	$table = '<div class="pt-table-scroll"><table class="pt-credits-table"><thead><tr><th><span class="screen-reader-text">' . esc_html__( 'Preview', 'palltheme-core' ) . '</span></th><th>' . esc_html__( 'Media', 'palltheme-core' ) . '</th><th>' . esc_html__( 'Author', 'palltheme-core' ) . '</th><th>' . esc_html__( 'Source', 'palltheme-core' ) . '</th><th>' . esc_html__( 'License', 'palltheme-core' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
	return pallcore_section( $table, $a );
}

/* ======================================================================
 * Attachment credit fields (Media Library → edit image)
 * ==================================================================== */

/**
 * Credit field definitions.
 *
 * @return array<string,string>
 */
function pallcore_credit_fields(): array {
	return array(
		'source'      => __( 'Credit: source (e.g. Unsplash)', 'palltheme-core' ),
		'url'         => __( 'Credit: original URL', 'palltheme-core' ),
		'author'      => __( 'Credit: author', 'palltheme-core' ),
		'author_url'  => __( 'Credit: author URL', 'palltheme-core' ),
		'license'     => __( 'Credit: license', 'palltheme-core' ),
		'license_url' => __( 'Credit: license URL', 'palltheme-core' ),
		'usage'       => __( 'Credit: used for', 'palltheme-core' ),
	);
}

add_filter(
	'attachment_fields_to_edit',
	static function ( $fields, $post ) {
		foreach ( pallcore_credit_fields() as $key => $label ) {
			$fields[ 'pall_credit_' . $key ] = array(
				'label' => $label,
				'input' => 'text',
				'value' => (string) get_post_meta( $post->ID, '_pall_credit_' . $key, true ),
				'helps' => 'source' === $key ? __( 'Fill these for third-party images; they appear on the Media Credits page ([pall_media_credits]).', 'palltheme-core' ) : '',
			);
		}
		return $fields;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	static function ( $post, $attachment ) {
		if ( ! current_user_can( 'edit_post', $post['ID'] ) ) {
			return $post;
		}
		foreach ( array_keys( pallcore_credit_fields() ) as $key ) {
			if ( isset( $attachment[ 'pall_credit_' . $key ] ) ) {
				$value = in_array( $key, array( 'url', 'author_url', 'license_url' ), true ) ? esc_url_raw( $attachment[ 'pall_credit_' . $key ] ) : sanitize_text_field( $attachment[ 'pall_credit_' . $key ] );
				if ( '' === $value ) {
					delete_post_meta( $post['ID'], '_pall_credit_' . $key );
				} else {
					update_post_meta( $post['ID'], '_pall_credit_' . $key, $value );
				}
			}
		}
		return $post;
	},
	10,
	2
);
