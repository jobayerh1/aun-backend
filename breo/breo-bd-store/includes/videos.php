<?php
/**
 * Product videos, self-hosted.
 *
 * Upload the prepared files to the Media Library and they appear on their
 * product by themselves. A file belongs to a product when its name starts
 * with the product's SKU; files sharing a stem form one video:
 *
 *   N990000981-film.mp4          the video (the best quality available)
 *   N990000981-film-720.mp4      lighter version for phones        (optional)
 *   N990000981-film-loop.mp4     short silent loop for autoplay    (optional)
 *   N990000981-film-poster.webp  still shown before it plays       (optional)
 *
 * Orientation is read from the video file itself: a landscape film becomes a
 * full-width cinematic band, a portrait one sits in a phone-shaped frame next
 * to its copy, and portrait videos from every product make the homepage reel.
 * The full video (with sound) only loads when someone presses play.
 */
defined( 'ABSPATH' ) || exit;

/** Splits "N990000981-film-720-1.mp4" into sku / slug / role. */
function breo_bd_video_file_parts( $file ) {
	$base = wp_basename( (string) $file );
	if ( ! preg_match( '/^([A-Za-z0-9]+)-([a-z0-9]+)(?:-(720|loop|poster))?(?:-\d+)?\.(mp4|m4v|webm|webp|jpe?g|png)$/i', $base, $m ) ) {
		return null;
	}
	return array(
		'sku'  => strtoupper( $m[1] ),
		'slug' => strtolower( $m[2] ),
		'role' => $m[3] ? strtolower( $m[3] ) : 'main',
		'ext'  => strtolower( $m[4] ),
	);
}

/**
 * The videos for one product, best first (a landscape film before portrait cuts).
 *
 * @return array[] id, url, url720, loop, poster, width, height, vertical, seconds, length, date, stem
 */
function breo_bd_product_videos( $product ) {
	static $cache = array();
	if ( ! $product instanceof WC_Product ) {
		return array();
	}
	$pid = $product->get_id();
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}
	$sku = strtoupper( (string) $product->get_sku() );
	if ( ! $sku || 'yes' === $product->get_meta( '_breo_videos_hide' ) ) {
		return $cache[ $pid ] = array();
	}
	$ids = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'video', 'image' ),
		'posts_per_page' => 60,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ), // the newest upload of a name wins
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => '_wp_attached_file', 'value' => $sku . '-', 'compare' => 'LIKE' ),
		),
	) );
	$groups = array();
	foreach ( $ids as $id ) {
		$parts = breo_bd_video_file_parts( get_post_meta( $id, '_wp_attached_file', true ) );
		if ( ! $parts || $parts['sku'] !== $sku ) {
			continue;
		}
		$is_video = 0 === strpos( (string) get_post_mime_type( $id ), 'video/' );
		$role     = $parts['role'];
		if ( ( 'poster' === $role ) === $is_video ) {
			continue; // a poster must be an image, everything else a video
		}
		$stem = $parts['sku'] . '-' . $parts['slug'];
		if ( ! isset( $groups[ $stem ][ $role ] ) ) {
			$groups[ $stem ][ $role ] = (int) $id;
		}
	}
	$out = array();
	foreach ( $groups as $stem => $g ) {
		if ( empty( $g['main'] ) ) {
			continue;
		}
		$id   = $g['main'];
		$meta = (array) wp_get_attachment_metadata( $id );
		$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$sec  = isset( $meta['length'] ) ? (int) $meta['length'] : 0;
		$out[] = array(
			'id'       => $id,
			'stem'     => $stem,
			'url'      => wp_get_attachment_url( $id ),
			'url720'   => ! empty( $g['720'] ) ? wp_get_attachment_url( $g['720'] ) : '',
			'loop'     => ! empty( $g['loop'] ) ? wp_get_attachment_url( $g['loop'] ) : '',
			'poster'   => ! empty( $g['poster'] ) ? wp_get_attachment_url( $g['poster'] ) : '',
			'width'    => $w,
			'height'   => $h,
			'vertical' => $h > $w,
			'seconds'  => $sec,
			'length'   => $sec ? floor( $sec / 60 ) . ':' . str_pad( (string) ( $sec % 60 ), 2, '0', STR_PAD_LEFT ) : '',
			'date'     => get_post_time( 'c', true, $id ),
		);
	}
	usort( $out, function ( $a, $b ) {
		return (int) $a['vertical'] - (int) $b['vertical']; // landscape first
	} );
	return $cache[ $pid ] = $out;
}

/** The one video a product page leads with. */
function breo_bd_primary_video( $product ) {
	$v = breo_bd_product_videos( $product );
	return $v ? $v[0] : null;
}

/** What the player needs, as a data attribute. */
function breo_bd_video_play_attr( $v, $title ) {
	return ' data-breo-play="' . esc_attr( wp_json_encode( array(
		'src'    => $v['url'],
		'src720' => $v['url720'],
		'poster' => $v['poster'],
		'title'  => $title,
	) ) ) . '"';
}

/** The silent clip for autoplay: the loop, else the light version, else the film itself (muted). */
function breo_bd_video_ambient_src( $v ) {
	return $v['loop'] ? $v['loop'] : ( $v['url720'] ? $v['url720'] : $v['url'] );
}

function breo_bd_video_short_name( $product ) {
	$d = breo_bd_product_data( $product );
	return $d ? $d['short_name'] : $product->get_name();
}

/** "▶ Watch the film · 0:58" for the hero, next to Buy now. */
function breo_bd_film_button( $product, $class = 'breo-hero-cta__film' ) {
	$v = breo_bd_primary_video( $product );
	if ( ! $v ) {
		return '';
	}
	return '<button type="button" class="' . esc_attr( $class ) . '"' . breo_bd_video_play_attr( $v, 'Breo ' . breo_bd_video_short_name( $product ) ) . '>'
		. '<span class="breo-playdot">' . breo_bd_icon( 'play', 14 ) . '</span>Watch the film'
		. ( $v['length'] ? '<span class="breo-film-len">' . esc_html( $v['length'] ) . '</span>' : '' ) . '</button>';
}

/**
 * Product page video section. Placed after the buy panel automatically; a
 * product can put it elsewhere with array( 'type' => 'video' ) in its sections.
 */
function breo_bd_sec_video( $product, $d = null, $s = array() ) {
	$v = breo_bd_primary_video( $product );
	if ( ! $v ) {
		return '';
	}
	$name   = breo_bd_video_short_name( $product );
	$play   = breo_bd_video_play_attr( $v, 'Breo ' . $name );
	$poster = $v['poster'] ? ' poster="' . esc_url( $v['poster'] ) . '"' : '';
	$clip   = '<video class="breo-vid__clip" src="' . esc_url( breo_bd_video_ambient_src( $v ) ) . '"' . $poster
		. ' muted loop playsinline preload="none" data-breo-video data-no-lazy="1" aria-hidden="true"></video>';
	$btn    = '<button type="button" class="breo-vid__play"' . $play . '><span class="breo-playdot">' . breo_bd_icon( 'play', 16 ) . '</span>'
		. 'Watch the film'
		. ( $v['length'] ? '<span class="breo-film-len">' . esc_html( $v['length'] ) . '</span>' : '' ) . '</button>';

	if ( ! $v['vertical'] ) {
		// Factory films carry their own captions, so our copy sits above the picture, never on it,
		// and the frame stops at 1120px so a 720p source stays sharp.
		return '<section class="breo-s breo-vid is-wide" id="film" aria-label="' . esc_attr( 'The Breo ' . $name . ' film' ) . '">'
			. '<div class="breo-wrap">'
			. '<header class="breo-vid__head" data-reveal><p class="breo-eyebrow">The film</p>'
			. '<h2 class="breo-title">The ' . esc_html( $name ) . ', in motion</h2>' . $btn . '</header>'
			. '<div class="breo-vid__screen" style="--vid-ratio:' . (int) max( 1, $v['width'] ) . ' / ' . (int) max( 1, $v['height'] ) . '">' . $clip
			. '<button type="button" class="breo-vid__tap"' . $play . ' aria-label="' . esc_attr( 'Play the ' . $name . ' film' ) . '"><span>' . breo_bd_icon( 'play', 26 ) . '</span></button>'
			. '</div></div></section>';
	}
	return '<section class="breo-s breo-vid is-tall" id="film" aria-label="' . esc_attr( 'The Breo ' . $name . ' video' ) . '">'
		. '<div class="breo-wrap breo-vid__split">'
		. '<div class="breo-vid__copy" data-reveal><p class="breo-eyebrow">See it in action</p>'
		. '<h2 class="breo-title">The ' . esc_html( $name ) . ', in motion</h2>'
		. '<p class="breo-lead">Filmed by Breo. See how it looks, how it works and where it fits into an ordinary day.</p>' . $btn . '</div>'
		. '<div class="breo-vid__frame" style="--vid-ratio:' . (int) max( 1, $v['width'] ) . ' / ' . (int) max( 1, $v['height'] ) . '">' . $clip
		. '<button type="button" class="breo-vid__tap"' . $play . ' aria-label="' . esc_attr( 'Play the ' . $name . ' film' ) . '"><span>' . breo_bd_icon( 'play', 26 ) . '</span></button>'
		. '</div></div></section>';
}

/** Homepage "See Breo in action": the portrait video of each product, side by side. */
function breo_bd_reels_html() {
	$cards = '';
	foreach ( breo_bd_products() as $p ) {
		$tall = array_values( array_filter( breo_bd_product_videos( $p ), function ( $v ) {
			return $v['vertical'];
		} ) );
		if ( ! $tall ) {
			continue;
		}
		$v    = $tall[0];
		$d    = breo_bd_product_data( $p );
		$name = breo_bd_video_short_name( $p );
		$cards .= '<article class="breo-reel" style="--accent:' . esc_attr( $d ? $d['accent'] : '' ) . '">'
			. '<div class="breo-reel__media">'
			. '<video class="breo-reel__clip" src="' . esc_url( breo_bd_video_ambient_src( $v ) ) . '"' . ( $v['poster'] ? ' poster="' . esc_url( $v['poster'] ) . '"' : '' )
			. ' muted loop playsinline preload="none" data-breo-video data-breo-reel data-no-lazy="1" aria-hidden="true"></video>'
			. '<button type="button" class="breo-reel__play"' . breo_bd_video_play_attr( $v, 'Breo ' . $name ) . ' aria-label="' . esc_attr( 'Play the ' . $name . ' video' ) . '">'
			. '<span>' . breo_bd_icon( 'play', 22 ) . '</span>' . ( $v['length'] ? '<em>' . esc_html( $v['length'] ) . '</em>' : '' ) . '</button></div>'
			. '<a class="breo-reel__name" href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $name )
			. '<span>' . esc_html( $d ? $d['tagline'] : '' ) . '</span></a></article>';
	}
	if ( ! $cards ) {
		return '';
	}
	return '<section class="breo-s breo-reels"><div class="breo-wrap">'
		. '<header class="breo-sec-head" data-reveal><p class="breo-eyebrow">In motion</p><h2 class="breo-title">See Breo in action</h2></header>'
		. '<div class="breo-reels__row">' . $cards . '</div></div></section>';
}

/* ---------- search engines: one VideoObject per product film ---------- */

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) || ! is_singular( 'product' ) ) {
		return $data;
	}
	$product = wc_get_product( get_queried_object_id() );
	$v       = $product ? breo_bd_primary_video( $product ) : null;
	if ( ! $v || ! $v['poster'] ) {
		return $data; // Google requires a thumbnail for a VideoObject
	}
	$name = breo_bd_video_short_name( $product );
	$desc = wp_strip_all_tags( $product->get_short_description() );
	$node = array(
		'@type'        => 'VideoObject',
		'name'         => 'Breo ' . $name . ' - product film',
		'description'  => $desc ? $desc : 'The Breo ' . $name . ' in use.',
		'thumbnailUrl' => array( $v['poster'] ),
		'contentUrl'   => $v['url'],
		'uploadDate'   => $v['date'],
	);
	if ( $v['seconds'] ) {
		$node['duration'] = 'PT' . ( $v['seconds'] >= 60 ? floor( $v['seconds'] / 60 ) . 'M' : '' ) . ( $v['seconds'] % 60 ) . 'S';
	}
	$data['breoVideo'] = $node;
	return $data;
}, 97 );

/* ---------- product edit screen: what was found, and a way to hide it ---------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'breo-videos', 'Breo: product videos', 'breo_bd_videos_box', 'product', 'side' );
} );

function breo_bd_videos_box( $post ) {
	$product = wc_get_product( $post->ID );
	if ( ! $product ) {
		return;
	}
	$hide = 'yes' === $product->get_meta( '_breo_videos_hide' );
	if ( $hide ) {
		$product->delete_meta_data( '_breo_videos_hide' ); // list what *would* show
	}
	$found = breo_bd_product_videos( $product );
	wp_nonce_field( 'breo_videos', 'breo_videos_nonce' );
	echo '<p class="description">Upload files named with this SKU (' . esc_html( $product->get_sku() ) . '), e.g. <code>' . esc_html( $product->get_sku() ) . '-film.mp4</code>, <code>-film-720.mp4</code>, <code>-film-loop.mp4</code>, <code>-film-poster.webp</code>. They appear on the page by themselves.</p>';
	if ( $found ) {
		echo '<ul style="margin:.5em 0 1em;padding-left:1.1em;list-style:disc">';
		foreach ( $found as $v ) {
			$extra = array_filter( array( $v['url720'] ? 'phone version' : '', $v['loop'] ? 'loop' : '', $v['poster'] ? 'poster' : '' ) );
			echo '<li><strong>' . esc_html( $v['stem'] ) . '</strong> · ' . ( $v['vertical'] ? 'portrait' : 'landscape' ) . ( $v['length'] ? ' · ' . esc_html( $v['length'] ) : '' )
				. ( $extra ? '<br><span class="description">+ ' . esc_html( implode( ', ', $extra ) ) . '</span>' : '' ) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p><em>No videos found for this product.</em></p>';
	}
	echo '<p><label><input type="checkbox" name="breo_videos_hide" value="yes"' . checked( $hide, true, false ) . '> Hide videos on this product</label></p>';
}

add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['breo_videos_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['breo_videos_nonce'] ), 'breo_videos' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return;
	}
	if ( ! empty( $_POST['breo_videos_hide'] ) ) {
		$product->update_meta_data( '_breo_videos_hide', 'yes' );
	} else {
		$product->delete_meta_data( '_breo_videos_hide' );
	}
	$product->save_meta_data();
} );
