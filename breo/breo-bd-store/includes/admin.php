<?php
/**
 * Tools → Breo BD Setup: business/policy settings, the product + media
 * importer (step-by-step over AJAX so no request hits the host's timeout),
 * and the one-click site build (pages, front page, currency, policy links).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Breo BD Setup', 'Breo BD Setup', 'manage_woocommerce', 'breo-bd-setup', 'breo_bd_admin_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( BREO_BD_DIR . 'breo-bd-store.php' ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=breo-bd-setup' ) ) . '">Setup</a>' );
	return $links;
} );

/* ------------------------------------------------------------------------
 * Settings fields
 * --------------------------------------------------------------------- */

function breo_bd_settings_fields() {
	return array(
		'Business details' => array(
			'company'  => array( 'Company / legal name', 'text', 'Used in the footer, Terms and Privacy Policy. Empty = "Breo Bangladesh".' ),
			'address'  => array( 'Office / service address', 'text', 'Shown on Contact, Warranty and policy pages. Empty = hidden.' ),
			'maps'     => array( 'Google Maps link', 'url', 'Your Google Business Profile link (Share → Copy link), e.g. https://maps.app.goo.gl/xxxx . Used on the Contact page, in the footer and in the store search-engine data.' ),
			'phone'    => array( 'Phone (display)', 'text', 'e.g. +880 1X XXXX XXXX' ),
			'whatsapp' => array( 'WhatsApp number', 'text', 'With country code, digits only, e.g. 8801XXXXXXXXX. Empty = no WhatsApp buttons.' ),
			'email_design' => array( 'Breo email design', 'check', 'Send WooCommerce emails (order confirmations, updates, invoices) in the Breo design. Untick to go back to the plain WooCommerce emails.' ),
			'wa_float' => array( 'Floating WhatsApp button', 'check', 'Show the round WhatsApp button in the bottom-right corner of every page. The WhatsApp buttons on product pages and in the footer stay either way.' ),
			'media_webp' => array( 'Import images as WebP', 'check', 'Convert every photo to WebP as it is imported. WebP files are around 70% smaller than JPEG or PNG at the same quality, so pages load faster on mobile data.' ),
			'wa_header' => array( 'WhatsApp icon in the header', 'check', 'Show the WhatsApp icon in the top menu bar, and the Chat on WhatsApp button inside the phone menu.' ),
			'messenger' => array( 'Facebook Messenger', 'text', 'Your Facebook Page username (the part after facebook.com/), or paste the Page link. Adds a Messenger button next to WhatsApp on product pages. Empty = hidden.' ),
			'email'    => array( 'Email', 'text', '' ),
			'hours'    => array( 'Support hours', 'text', '' ),
		),
		'Social links' => array(
			'facebook'  => array( 'Facebook page URL', 'url', '' ),
			'instagram' => array( 'Instagram URL', 'url', '' ),
			'youtube'   => array( 'YouTube URL', 'url', '' ),
			'tiktok'    => array( 'TikTok URL', 'url', '' ),
		),
		'Policy numbers (used in every policy page)' => array(
			'warranty_months'       => array( 'Warranty (months)', 'num', 'Empty = no warranty period mentioned.' ),
			'return_faulty_days'    => array( 'Report damaged / faulty items within (days)', 'num', '' ),
			'return_unopened_hours' => array( 'Change-of-mind returns, unopened, within (hours)', 'num', '' ),
			'refund_days'           => array( 'Refund processed within (business days)', 'num', '' ),
			'dhaka_days'            => array( 'Delivery inside Dhaka (working days)', 'num', '' ),
			'outside_days'          => array( 'Delivery outside Dhaka (working days)', 'num', '' ),
			'dhaka_fee'             => array( 'Delivery charge inside Dhaka (৳)', 'num', 'Optional. Empty = "shown at checkout".' ),
			'outside_fee'           => array( 'Delivery charge outside Dhaka (৳)', 'num', 'Optional.' ),
			'cutoff'                => array( 'Same-day order cut-off time', 'text', '' ),
			'payments'              => array( 'Payment methods (sentence)', 'text', 'e.g. Cash on Delivery, bKash, Nagad and cards' ),
		),
	);
}

/* ------------------------------------------------------------------------
 * Media import
 * --------------------------------------------------------------------- */

function breo_bd_find_attachment( $sig ) {
	$q = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'meta_key'       => '_breo_src',
		'meta_value'     => $sig,
		'fields'         => 'ids',
		'posts_per_page' => 1,
	) );
	return $q ? (int) $q[0] : 0;
}

/** WebP is on unless switched off, and only if this server's image library can write it. */
function breo_bd_webp_on() {
	static $ok = null;
	if ( null === $ok ) {
		$ok = function_exists( 'imagewebp' )
			|| ( class_exists( 'Imagick' ) && in_array( 'WEBP', array_map( 'strtoupper', Imagick::queryFormats() ), true ) );
	}
	return $ok && 'no' !== breo_bd_opt( 'media_webp' );
}

/** Rewrites a downloaded JPEG/PNG as WebP. Returns the new path, or '' if it couldn't. */
function breo_bd_to_webp( $path ) {
	$ed = wp_get_image_editor( $path );
	if ( is_wp_error( $ed ) || ! $ed->supports_mime_type( 'image/webp' ) ) {
		return '';
	}
	$ed->set_quality( 82 );
	$saved = $ed->save( $path . '.webp', 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
		return '';
	}
	@unlink( $path ); // phpcs:ignore
	return $saved['path'];
}

/** True when an already-imported file should be fetched again because it isn't WebP yet. */
function breo_bd_webp_replace( $id ) {
	if ( empty( $GLOBALS['breo_bd_webp_force'] ) || ! breo_bd_webp_on() ) {
		return false;
	}
	$file = get_attached_file( $id );
	return $file && 'webp' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
}

/** Download one media item into the Media Library (cropping it first if asked). */
function breo_bd_import_media_item( $pid, $sku, $key, $spec, $tries = 3 ) {
	$sig   = ( ! empty( $spec['local'] ) ? 'local:' . $spec['local'] : $spec['src'] ) . ( empty( $spec['crop'] ) ? '' : '#' . implode( ',', $spec['crop'] ) );
	$found = breo_bd_find_attachment( $sig );
	if ( $found && ! breo_bd_webp_replace( $found ) ) {
		return $found;
	}
	$replacing = $found; // deleted once its WebP replacement is safely in place
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ext = strtolower( pathinfo( ! empty( $spec['local'] ) ? $spec['local'] : (string) wp_parse_url( $spec['src'], PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$ext = 'jpeg' === $ext ? 'jpg' : $ext;

	// Breo's CDN (Alibaba edges) sometimes refuses the first connection: allow
	// 20 s to connect (WordPress default is 10 s). The admin importer calls this
	// with $tries = 1 and re-runs failed files in later passes, so no single
	// request runs long enough to hit the host's time limit.
	if ( ! empty( $spec['local'] ) ) {
		// A file shipped inside the plugin (e.g. the manual illustration).
		$tmp = wp_tempnam( basename( $spec['local'] ) );
		if ( ! $tmp || ! @copy( BREO_BD_DIR . $spec['local'], $tmp ) ) { // phpcs:ignore
			return new WP_Error( 'breo_local', 'Could not read ' . $spec['local'] );
		}
	} else {
		$slow = function ( $handle ) {
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 20 ); // phpcs:ignore
		};
		add_action( 'http_api_curl', $slow );
		for ( $try = 1; $try <= $tries; $try++ ) {
			$tmp = download_url( $spec['src'], 300 );
			if ( ! is_wp_error( $tmp ) ) {
				break;
			}
			if ( $try < $tries ) {
				sleep( 3 * $try );
			}
		}
		remove_action( 'http_api_curl', $slow );
	}
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	if ( ! empty( $spec['crop'] ) && in_array( $ext, array( 'jpg', 'png', 'webp' ), true ) ) {
		$ed = wp_get_image_editor( $tmp );
		if ( ! is_wp_error( $ed ) ) {
			list( $x, $y, $w, $h ) = $spec['crop'];
			$size = $ed->get_size();
			$ed->crop( $x, $y, min( $w, $size['width'] - $x ), min( $h, $size['height'] - $y ) );
			$ed->set_quality( 86 );
			$saved = $ed->save( $tmp . '-crop.' . $ext );
			if ( ! is_wp_error( $saved ) ) {
				@unlink( $tmp ); // phpcs:ignore
				$tmp = $saved['path'];
			}
		}
	}
	if ( breo_bd_webp_on() && in_array( $ext, array( 'jpg', 'png' ), true ) ) {
		$webp = breo_bd_to_webp( $tmp );
		if ( $webp ) {
			$tmp = $webp;
			$ext = 'webp';
		}
	}
	$file = array(
		'name'     => sanitize_file_name( 'breo-' . strtolower( $sku ) . '-' . str_replace( '_', '-', $key ) . '.' . $ext ),
		'tmp_name' => $tmp,
	);
	$id = media_handle_sideload( $file, $pid, isset( $spec['alt'] ) ? $spec['alt'] : '' );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return $id;
	}
	update_post_meta( $id, '_breo_src', $sig );
	if ( ! empty( $spec['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $spec['alt'] );
	}
	if ( $replacing ) {
		// carry over anything the owner edited, then drop the old JPEG/PNG and its sizes
		$old_alt = get_post_meta( $replacing, '_wp_attachment_image_alt', true );
		if ( $old_alt && empty( $spec['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $old_alt );
		}
		wp_delete_attachment( $replacing, true );
	}
	return (int) $id;
}

/* ------------------------------------------------------------------------
 * Products
 * --------------------------------------------------------------------- */

function breo_bd_ensure_categories() {
	$ids = array();
	foreach ( breo_bd_categories() as $slug => $c ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $t ) {
			$r            = wp_insert_term( $c[0], 'product_cat', array( 'slug' => $slug, 'description' => $c[2] ) );
			$ids[ $slug ] = is_wp_error( $r ) ? 0 : (int) $r['term_id'];
		} else {
			$ids[ $slug ] = (int) $t->term_id;
		}
	}
	return $ids;
}

function breo_bd_price( $v ) {
	$v = preg_replace( '/[^\d.]/', '', (string) $v );
	return '' === $v ? '' : wc_format_decimal( $v );
}

/** Plain-HTML long description (feeds, emails, search engines). */
function breo_bd_long_description( $d ) {
	$h = '';
	foreach ( $d['sections'] as $s ) {
		if ( ! empty( $s['title'] ) && ! empty( $s['text'] ) ) {
			$h .= '<h3>' . esc_html( $s['title'] ) . '</h3><p>' . esc_html( $s['text'] ) . '</p>';
		}
		if ( ! empty( $s['items'] ) && 'cards' === $s['type'] ) {
			foreach ( $s['items'] as $it ) {
				$h .= '<h3>' . esc_html( $it[0] ) . '</h3><p>' . esc_html( $it[1] ) . '</p>';
			}
		}
		if ( ! empty( $s['items'] ) && 'tiles' === $s['type'] ) {
			foreach ( $s['items'] as $it ) {
				$h .= '<h3>' . esc_html( $it[1] ) . '</h3><p>' . esc_html( $it[2] ) . '</p>';
			}
		}
	}
	$h .= '<h3>Specifications</h3><ul>';
	foreach ( $d['specs'] as $k => $v ) {
		$h .= '<li>' . esc_html( $k . ': ' . $v ) . '</li>';
	}
	return $h . '</ul>';
}

function breo_bd_save_product( $sku, $d, $regular, $sale, $cat_ids ) {
	$id = wc_get_product_id_by_sku( $sku );
	$p  = $id ? wc_get_product( $id ) : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	if ( ! $id ) {
		$p->set_slug( $d['slug'] );
		$p->set_sku( $sku );
		$p->set_status( 'publish' );
		$p->set_manage_stock( false );
		$p->set_stock_status( 'instock' );
	}
	$p->set_short_description( $d['short'] );
	$p->set_description( breo_bd_long_description( $d ) );
	$p->set_regular_price( $regular );
	$p->set_sale_price( ( '' !== $sale && '' !== $regular && (float) $sale < (float) $regular ) ? $sale : '' );
	$p->set_weight( (string) $d['weight'] );
	$p->set_length( (string) $d['dims'][0] );
	$p->set_width( (string) $d['dims'][1] );
	$p->set_height( (string) $d['dims'][2] );
	if ( ! empty( $cat_ids[ $d['category'] ] ) ) {
		$p->set_category_ids( array( $cat_ids[ $d['category'] ] ) );
	}
	$attrs = array();
	foreach ( array( 'Model' => $d['model'], 'Color' => $d['color'] ) as $label => $val ) {
		$a = new WC_Product_Attribute();
		$a->set_name( $label );
		$a->set_options( array( $val ) );
		$a->set_visible( true );
		$a->set_variation( false );
		$attrs[] = $a;
	}
	$p->set_attributes( $attrs );
	$p->update_meta_data( '_breo_sku_key', $sku );
	$p->delete_meta_data( '_breo_data' ); // v1 stored the copy here; the data file is now the source.
	return $p->save();
}

function breo_bd_apply_media( $pid, $d ) {
	$p   = wc_get_product( $pid );
	$map = $p->get_meta( '_breo_media' );
	$map = is_array( $map ) ? $map : array();
	if ( ! empty( $map['tile'] ) ) {
		$p->set_image_id( $map['tile'] );
	}
	$g = array();
	foreach ( $d['gallery'] as $k ) {
		if ( ! empty( $map[ $k ] ) ) {
			$g[] = (int) $map[ $k ];
		}
	}
	$p->set_gallery_image_ids( $g );
	$p->save();
}

add_action( 'wp_ajax_breo_bd_import_step', function () {
	check_ajax_referer( 'breo_bd_setup' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Not allowed.' );
	}
	@set_time_limit( 300 ); // phpcs:ignore
	$GLOBALS['breo_bd_webp_force'] = ! empty( $_POST['webp_force'] );
	$data                          = breo_bd_data();
	$sku  = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';

	// Site graphics for the About / policy pages.
	if ( 'site' === $sku ) {
		$specs  = breo_bd_site_media();
		$keys   = array_keys( $specs );
		$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$map    = get_option( 'breo_bd_site_media', array() );
		$map    = is_array( $map ) ? $map : array();
		$errors = array();
		$start  = microtime( true );
		while ( $offset < count( $keys ) && ( microtime( true ) - $start ) < 20 ) {
			$k = $keys[ $offset ];
			if ( empty( $map[ $k ] ) || ! get_post( $map[ $k ] ) || breo_bd_webp_replace( $map[ $k ] ) ) {
				$r = breo_bd_import_media_item( 0, 'site', $k, $specs[ $k ], 1 );
				if ( is_wp_error( $r ) ) {
					$errors[] = $k . ': ' . $r->get_error_message();
				} else {
					$map[ $k ] = $r;
				}
			}
			$offset++;
		}
		update_option( 'breo_bd_site_media', $map, false );
		$done = $offset >= count( $keys );
		wp_send_json_success( array( 'phase' => $done ? 'done' : 'media', 'offset' => $offset, 'total' => count( $keys ), 'errors' => $errors, 'view' => breo_bd_page_url( 'about-breo' ) ) );
	}
	if ( ! isset( $data[ $sku ] ) ) {
		wp_send_json_error( 'Unknown SKU.' );
	}
	$d     = $data[ $sku ];
	$phase = isset( $_POST['phase'] ) ? sanitize_key( $_POST['phase'] ) : 'product';

	if ( 'product' === $phase ) {
		$pid = breo_bd_save_product(
			$sku,
			$d,
			breo_bd_price( isset( $_POST['regular'] ) ? wp_unslash( $_POST['regular'] ) : '' ),
			breo_bd_price( isset( $_POST['sale'] ) ? wp_unslash( $_POST['sale'] ) : '' ),
			breo_bd_ensure_categories()
		);
		if ( empty( $_POST['with_media'] ) ) {
			breo_bd_apply_media( $pid, $d );
			wp_send_json_success( array( 'phase' => 'done', 'id' => $pid, 'view' => get_permalink( $pid ), 'errors' => array() ) );
		}
		wp_send_json_success( array( 'phase' => 'media', 'offset' => 0, 'total' => count( $d['media'] ), 'id' => $pid ) );
	}

	$pid = wc_get_product_id_by_sku( $sku );
	if ( ! $pid ) {
		wp_send_json_error( 'Product not found. Run the import again.' );
	}
	$keys   = array_keys( $d['media'] );
	$offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
	$p      = wc_get_product( $pid );
	$map    = $p->get_meta( '_breo_media' );
	$map    = is_array( $map ) ? $map : array();
	$errors = array();
	$start  = microtime( true );
	while ( $offset < count( $keys ) && ( microtime( true ) - $start ) < 20 ) {
		$k = $keys[ $offset ];
		$r = breo_bd_import_media_item( $pid, $sku, $k, $d['media'][ $k ], 1 );
		if ( is_wp_error( $r ) ) {
			$errors[] = $k . ': ' . $r->get_error_message();
		} else {
			$map[ $k ] = $r;
		}
		$offset++;
	}
	$p->update_meta_data( '_breo_media', $map );
	$p->save();
	$done = $offset >= count( $keys );
	if ( $done ) {
		breo_bd_apply_media( $pid, $d );
	}
	wp_send_json_success( array(
		'phase'  => $done ? 'done' : 'media',
		'offset' => $offset,
		'total'  => count( $keys ),
		'errors' => $errors,
		'id'     => $pid,
		'view'   => get_permalink( $pid ),
	) );
} );

/* ------------------------------------------------------------------------
 * Site build: pages, front page, currency, policy links
 * --------------------------------------------------------------------- */

function breo_bd_ensure_home_page() {
	$existing = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'meta_key'       => '_breo_home',
		'meta_value'     => '1',
		'fields'         => 'ids',
		'posts_per_page' => 1,
	) );
	if ( $existing ) {
		$pid = (int) $existing[0];
		wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
	} else {
		$pid = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Home',
			'post_name'    => 'home',
			'post_content' => "<!-- wp:shortcode -->\n[breo_home]\n<!-- /wp:shortcode -->",
		) );
		update_post_meta( $pid, '_breo_home', '1' );
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $pid );
	return $pid;
}

function breo_bd_build_pages() {
	$defs = require BREO_BD_DIR . 'includes/pages-content.php';
	$msgs = array();
	$ids  = array();
	update_option( 'breo_bd_pages_built', time() );
	foreach ( $defs as $slug => $def ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		$args = array(
			'post_type'    => 'page',
			'post_title'   => $def['title'],
			'post_name'    => $slug,
			'post_excerpt' => $def['intro'],
			'post_content' => trim( $def['content'] ),
			'post_status'  => 'publish',
		);
		if ( $page ) {
			$ours   = get_post_meta( $page->ID, '_breo_page', true );
			$edited = $ours && get_post_meta( $page->ID, '_breo_hash', true ) !== md5( $page->post_content );
			if ( $edited ) {
				$msgs[] = array( 'info', esc_html( $def['title'] ) . ': kept as is. You have edited this page.' );
				$ids[ $slug ] = $page->ID;
				continue;
			}
			if ( ! $ours && 'publish' === $page->post_status ) {
				$msgs[] = array( 'warning', esc_html( $def['title'] ) . ': a published page at /' . esc_html( $slug ) . '/ already exists, so it was left untouched.' );
				$ids[ $slug ] = $page->ID;
				continue;
			}
			$args['ID'] = $page->ID;
			$pid        = wp_update_post( $args );
		} else {
			$pid = wp_insert_post( $args );
		}
		if ( ! $pid || is_wp_error( $pid ) ) {
			$msgs[] = array( 'error', esc_html( $def['title'] ) . ': could not be saved.' );
			continue;
		}
		update_post_meta( $pid, '_breo_page', $slug );
		update_post_meta( $pid, '_breo_hash', md5( get_post_field( 'post_content', $pid, 'raw' ) ) );
		$ids[ $slug ] = $pid;
		$msgs[]       = array( 'success', '<a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">' . esc_html( $def['title'] ) . '</a> ready.' );
	}
	return array( $ids, $msgs );
}

function breo_bd_handle_post() {
	if ( empty( $_POST['breo_bd_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return array();
	}
	check_admin_referer( 'breo_bd_setup' );
	$action = sanitize_key( $_POST['breo_bd_action'] );
	$out    = array();

	if ( 'settings' === $action ) {
		$s = array();
		foreach ( breo_bd_settings_fields() as $group ) {
			foreach ( $group as $k => $f ) {
				$v = isset( $_POST['breo'][ $k ] ) ? wp_unslash( $_POST['breo'][ $k ] ) : ''; // phpcs:ignore
				if ( 'url' === $f[1] ) {
					$v = esc_url_raw( trim( $v ) );
				} elseif ( 'check' === $f[1] ) {
					$v = $v ? 'yes' : 'no';
				} elseif ( 'num' === $f[1] ) {
					$v = preg_replace( '/[^\d.\-–]/u', '', $v );
				} else {
					$v = sanitize_text_field( $v );
				}
				$s[ $k ] = $v;
			}
		}
		$s['whatsapp'] = preg_replace( '/\D+/', '', $s['whatsapp'] );
		$s['maps']     = breo_bd_clean_maps_url( $s['maps'] );
		// Accept a pasted Page link (facebook.com/breobd, m.me/breobd, profile.php?id=…) and keep just the Page name/ID.
		$ms = trim( $s['messenger'] );
		if ( preg_match( '/[?&]id=(\d+)/', $ms, $m ) ) {
			$ms = $m[1];
		}
		$ms             = preg_replace( '~^(?:https?://)?(?:www\.|m\.|web\.)?(?:facebook\.com|fb\.com|m\.me)/~i', '', $ms );
		$s['messenger'] = preg_replace( '/[^A-Za-z0-9.\-_]/', '', (string) strtok( $ms, '/?#' ) );
		update_option( 'breo_bd_settings', $s );
		$out[] = array( 'success', 'Settings saved. Every page and policy now uses them.' );
	}

	if ( 'build' === $action ) {
		if ( ! empty( $_POST['pages'] ) ) {
			list( $ids, $msgs ) = breo_bd_build_pages();
			$out                = array_merge( $out, $msgs );
			if ( ! empty( $ids['privacy-policy'] ) ) {
				update_option( 'wp_page_for_privacy_policy', (int) $ids['privacy-policy'] );
			}
			if ( ! empty( $_POST['terms'] ) && ! empty( $ids['terms-conditions'] ) ) {
				update_option( 'woocommerce_terms_page_id', (int) $ids['terms-conditions'] );
				$out[] = array( 'success', 'Checkout now asks customers to accept the Terms & Conditions.' );
			}
		}
		if ( ! empty( $_POST['make_home'] ) ) {
			$hp    = breo_bd_ensure_home_page();
			$out[] = array( 'success', 'Breo homepage is now the front page (page #' . (int) $hp . '). <a href="' . esc_url( home_url( '/' ) ) . '" target="_blank">View site</a>' );
		}
		if ( ! empty( $_POST['set_currency'] ) ) {
			update_option( 'woocommerce_currency', 'BDT' );
			update_option( 'woocommerce_price_num_decimals', '0' );
			$out[] = array( 'success', 'Store currency set to Bangladeshi Taka (৳), no decimals.' );
		}
		if ( ! empty( $_POST['checkout_fields'] ) ) {
			update_option( 'woocommerce_checkout_phone_field', 'required' );
			update_option( 'woocommerce_checkout_company_field', 'hidden' );
			$out[] = array( 'success', 'Checkout now requires a phone number (couriers need it) and hides the Company name field.' );
		}
		if ( ! empty( $_POST['site_icon'] ) ) {
			$icon = breo_bd_import_media_item( 0, 'site', 'site-icon', array( 'local' => 'assets/icons/icon-512.png', 'alt' => 'Breo' ), 1 );
			if ( is_wp_error( $icon ) ) {
				$out[] = array( 'error', 'Favicon: ' . esc_html( $icon->get_error_message() ) );
			} else {
				update_option( 'site_icon', (int) $icon );
				$out[] = array( 'success', 'Breo favicon set as the site icon (browser tab, bookmarks, phone home screen).' );
			}
		}
		if ( ! empty( $_POST['samples'] ) ) {
			foreach ( array( array( 'sample-page', 'page' ), array( 'hello-world', 'post' ) ) as $x ) {
				$post = get_page_by_path( $x[0], OBJECT, $x[1] );
				if ( $post && 'publish' === $post->post_status ) {
					wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
					$out[] = array( 'success', '"' . esc_html( $post->post_title ) . '" moved to drafts (not deleted).' );
				}
			}
		}
	}
	return $out;
}

/* ------------------------------------------------------------------------
 * The page
 * --------------------------------------------------------------------- */

function breo_bd_admin_page() {
	$notices = breo_bd_handle_post();
	$s       = wp_parse_args( (array) get_option( 'breo_bd_settings', array() ), breo_bd_defaults() );
	$front   = (int) get_option( 'page_on_front' );
	$is_home = $front && get_post_meta( $front, '_breo_home', true );
	?>
	<div class="wrap breo-admin">
		<h1>Breo BD Setup</h1>
		<p>Run the three steps in order. Everything can be re-run safely: products are matched by SKU, media is never downloaded twice, and pages you have edited are never overwritten.</p>
		<?php foreach ( (array) $notices as $n ) : ?>
			<div class="notice notice-<?php echo esc_attr( $n[0] ); ?>"><p><?php echo wp_kses_post( $n[1] ); ?></p></div>
		<?php endforeach; ?>

		<h2>1. Business details &amp; policy numbers</h2>
		<form method="post">
			<?php wp_nonce_field( 'breo_bd_setup' ); ?>
			<input type="hidden" name="breo_bd_action" value="settings">
			<?php foreach ( breo_bd_settings_fields() as $group => $fields ) : ?>
				<h3><?php echo esc_html( $group ); ?></h3>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $k => $f ) : ?>
						<tr>
							<th scope="row"><label for="breo-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
							<td>
								<?php if ( 'check' === $f[1] ) : ?>
									<label><input id="breo-<?php echo esc_attr( $k ); ?>" type="checkbox" name="breo[<?php echo esc_attr( $k ); ?>]" value="yes" <?php checked( 'no' !== $s[ $k ] ); ?>> Show</label>
								<?php else : ?>
									<input id="breo-<?php echo esc_attr( $k ); ?>" class="<?php echo 'num' === $f[1] ? 'small-text' : 'regular-text'; ?>" type="<?php echo 'url' === $f[1] ? 'url' : 'text'; ?>" name="breo[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $s[ $k ] ); ?>">
								<?php endif; ?>
								<?php if ( $f[2] ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button( 'Save settings' ); ?>
		</form>

		<hr>
		<h2>2. Import the launch products</h2>
		<p>Creates or updates the four products and downloads their photos and videos into <strong>your</strong> Media Library (about 45 files, including 6 short videos). Nothing is loaded from Breo's servers afterwards. Prices are in Taka. Leave Sale empty for no discount, and leave both empty to show "Price coming soon".</p>
		<form method="post" id="breo-import">
			<?php wp_nonce_field( 'breo_bd_setup' ); ?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Product</th><th>SKU</th><th>Regular price (৳)</th><th>Sale price (৳)</th><th>Status</th></tr></thead>
				<tbody>
				<?php
				foreach ( breo_bd_data() as $sku => $d ) :
					$ex  = wc_get_product_id_by_sku( $sku );
					$exp = $ex ? wc_get_product( $ex ) : null;
					$med = $exp ? count( (array) $exp->get_meta( '_breo_media' ) ) : 0;
					?>
					<tr data-sku="<?php echo esc_attr( $sku ); ?>">
						<td><strong><?php echo esc_html( $d['name'] ); ?></strong><?php echo $exp ? ' · <a href="' . esc_url( $exp->get_permalink() ) . '" target="_blank">view</a>' : ''; ?></td>
						<td><code><?php echo esc_html( $sku ); ?></code></td>
						<td><input type="text" inputmode="numeric" name="regular" value="<?php echo esc_attr( $exp ? $exp->get_regular_price() : '' ); ?>" style="width:110px"></td>
						<td><input type="text" inputmode="numeric" name="sale" value="<?php echo esc_attr( $exp ? $exp->get_sale_price() : '' ); ?>" style="width:110px"></td>
						<td class="breo-status"><?php echo $exp ? esc_html( sprintf( 'Imported · %d/%d media', $med, count( $d['media'] ) ) ) : 'Not imported'; ?></td>
					</tr>
				<?php endforeach; ?>
					<?php $site_n = count( array_filter( (array) get_option( 'breo_bd_site_media', array() ) ) ); ?>
					<tr data-sku="site">
						<td><strong>Site graphics</strong> <span class="description">(About &amp; policy page photos)</span></td>
						<td>—</td><td>—</td><td>—</td>
						<td class="breo-status"><?php echo esc_html( sprintf( '%d/%d images', $site_n, count( breo_bd_site_media() ) ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<p><label><input type="checkbox" id="breo-with-media" checked> Download photos &amp; videos (untick to only update prices and text)</label></p>
			<p><label><input type="checkbox" id="breo-webp-force"> Re-download photos already imported as JPEG/PNG and replace them with WebP<?php echo breo_bd_webp_on() ? '' : ' <strong>(this server cannot write WebP)</strong>'; ?></label></p>
			<p><button type="submit" class="button button-primary button-large" id="breo-import-btn">Import / update products</button></p>
		</form>

		<hr>
		<h2>3. Build the site</h2>
		<form method="post">
			<?php wp_nonce_field( 'breo_bd_setup' ); ?>
			<input type="hidden" name="breo_bd_action" value="build">
			<p><label><input type="checkbox" name="pages" value="1" checked> Create the pages: About Breo, Contact, FAQ, Warranty Policy, Shipping &amp; Delivery, Returns &amp; Refunds, Privacy Policy, Terms &amp; Conditions</label></p>
			<p><label><input type="checkbox" name="make_home" value="1" <?php checked( ! $is_home ); ?>> Use the Breo homepage as the site's front page</label></p>
			<p><label><input type="checkbox" name="terms" value="1" checked> Ask customers to accept the Terms &amp; Conditions at checkout</label></p>
			<p><label><input type="checkbox" name="set_currency" value="1" <?php checked( 'BDT' !== get_option( 'woocommerce_currency' ) || '0' !== (string) get_option( 'woocommerce_price_num_decimals' ) ); ?>> Set store currency to Taka (৳) and show prices without decimals (৳10,000 instead of ৳10,000.00)</label></p>
			<p><label><input type="checkbox" name="checkout_fields" value="1" <?php checked( 'required' !== get_option( 'woocommerce_checkout_phone_field' ) ); ?>> Checkout: make the phone number required (couriers need it) and hide the "Company name" field</label></p>
			<p><label><input type="checkbox" name="site_icon" value="1" <?php checked( ! has_site_icon() ); ?>> Use the Breo logo as the site icon (favicon)</label></p>
			<p><label><input type="checkbox" name="samples" value="1" checked> Move WordPress's "Sample Page" and "Hello world!" post to drafts</label></p>
			<?php submit_button( 'Build site pages', 'primary large' ); ?>
		</form>
	</div>

	<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" data-no-defer="1">
	(function () {
		var form = document.getElementById('breo-import');
		if (!form || !window.fetch) return;
		var nonce = form.querySelector('[name=_wpnonce]').value;
		function post(data) {
			var fd = new FormData();
			fd.append('action', 'breo_bd_import_step');
			fd.append('_ajax_nonce', nonce);
			Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
			return fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) { if (!j.success) throw new Error(j.data || 'Request failed'); return j.data; });
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = document.getElementById('breo-import-btn');
			var media = document.getElementById('breo-with-media').checked ? '1' : '';
			var force = document.getElementById('breo-webp-force').checked ? '1' : '';
			var rows = Array.prototype.slice.call(form.querySelectorAll('tr[data-sku]'));
			btn.disabled = true;
			var failed = 0, pass = 1, errRows = [];
			(function nextRow(i) {
				if (i >= rows.length) {
					// Re-run products whose files timed out (finished files are skipped).
					if (errRows.length && pass < 3) {
						rows = errRows; errRows = []; pass++; failed = 0;
						rows.forEach(function (r) { r.querySelector('.breo-status').textContent = 'Some files timed out, retrying in a moment…'; });
						setTimeout(function () { nextRow(0); }, 8000);
						return;
					}
					btn.disabled = false;
					btn.textContent = failed ? 'Retry import' : 'Import / update products';
					return;
				}
				var row = rows[i], st = row.querySelector('.breo-status'), sku = row.getAttribute('data-sku'), errs = [];
				st.style.color = '';
				st.textContent = 'Saving product…';
				function step(data) {
					return post(data).then(function (r) {
						if (r.errors && r.errors.length) errs = errs.concat(r.errors);
						if (r.phase === 'media') {
							st.textContent = 'Downloading media ' + r.offset + ' / ' + r.total + '…';
							return step({ sku: sku, phase: 'media', offset: r.offset, webp_force: force });
						}
						st.innerHTML = (errs.length ? '⚠ Done with ' + errs.length + ' media error(s)' : '✓ Done') + ' · <a target="_blank" href="' + r.view + '">view</a>';
						st.style.color = errs.length ? '#b26200' : '#008a20';
						if (errs.length) { st.title = errs.join('\n'); errRows.push(row); }
					});
				}
				step({
					sku: sku, phase: 'product', with_media: media, webp_force: force,
					regular: (row.querySelector('[name=regular]') || {}).value || '',
					sale: (row.querySelector('[name=sale]') || {}).value || ''
				}).catch(function (err) {
					failed++;
					errRows.push(row);
					st.textContent = '✗ ' + err.message + ' (click Retry; finished files are kept)';
					st.style.color = '#d63638';
				}).then(function () { nextRow(i + 1); });
			})(0);
		});
	})();
	</script>
	<?php
}
