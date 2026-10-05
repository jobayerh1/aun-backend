<?php
/**
 * Page registry, settings, and the small helpers every template leans on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The six pages this plugin owns.
 *
 * 'slug' is the existing path on the live site — we adopt those pages rather
 * than creating duplicates, so no URL changes and nothing loses its history.
 */
function sl_pages() {
	return array(
		'home' => array(
			'slug'  => '',
			'title' => 'Home',
			'nav'   => 'Home',
		),
		'about' => array(
			'slug'  => 'about-us',
			'title' => 'About Us',
			'nav'   => 'About Us',
		),
		'brands' => array(
			'slug'  => 'our-brands',
			'title' => 'Our Brands',
			'nav'   => 'Our Brands',
		),
		'corporate' => array(
			'slug'  => 'corporate-solutions',
			'title' => 'Corporate Solutions',
			'nav'   => 'Corporate Solutions',
		),
		'dealers' => array(
			'slug'  => 'dealer-partnership',
			'title' => 'Dealer & Partnership',
			'nav'   => 'Dealer &amp; Partnership',
		),
		'contact' => array(
			'slug'  => 'contact-us',
			'title' => 'Contact Us',
			'nav'   => 'Contact',
		),
	);
}

/**
 * Which of our pages is being viewed, or '' for anything else.
 *
 * Checks the stamped meta first so a renamed slug keeps working, then falls
 * back to matching the slug — which means the plugin renders correctly even
 * before "Build pages" has ever been run.
 */
function sl_current_page() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	// Never take over anything in wp-admin, a feed, or a REST request.
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$cache = '';
		return $cache;
	}

	if ( is_front_page() ) {
		$cache = 'home';
		return $cache;
	}

	// 404 gets our shell too, so a wrong URL still looks like this company
	// rather than like whichever theme happens to be active.
	if ( is_404() ) {
		$cache = '404';
		return $cache;
	}

	if ( ! is_page() ) {
		$cache = '';
		return $cache;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		$cache = '';
		return $cache;
	}

	$stamped = get_post_meta( $post_id, '_sl_page', true );
	$pages   = sl_pages();
	if ( $stamped && isset( $pages[ $stamped ] ) ) {
		$cache = $stamped;
		return $cache;
	}

	$path = get_post_field( 'post_name', $post_id );
	foreach ( $pages as $key => $page ) {
		if ( '' !== $page['slug'] && $page['slug'] === $path ) {
			$cache = $key;
			return $cache;
		}
	}

	$cache = '';
	return $cache;
}

/**
 * Permalink for one of our pages.
 */
function sl_url( $key ) {
	$pages = sl_pages();
	if ( ! isset( $pages[ $key ] ) ) {
		return home_url( '/' );
	}
	if ( 'home' === $key ) {
		return home_url( '/' );
	}

	$found = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_sl_page',
			'meta_value'     => $key,
			'fields'         => 'ids',
		)
	);
	if ( $found ) {
		return get_permalink( $found[0] );
	}

	$page = get_page_by_path( $pages[ $key ]['slug'] );
	return $page ? get_permalink( $page ) : home_url( '/' . $pages[ $key ]['slug'] . '/' );
}

/* ---------------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------------ */

function sl_defaults() {
	return array(
		'phone'        => '+880 9638-078888',
		'phone_link'   => '+8809638078888',
		'email'        => 'info@smartliving.com.bd',
		'address_1'    => '108 Golartek, Mazar Road',
		'address_2'    => 'Mirpur, Dhaka 1216',
		'locality'     => 'Dhaka',
		'postcode'     => '1216',
		'maps_url'     => 'https://maps.app.goo.gl/R2yFgRFhLprkVM4k6',
		'facebook'     => 'https://www.facebook.com/bdSmartLiving',
		'hours_days'   => 'Saturday – Thursday',
		'hours_time'   => '10:00 AM – 6:00 PM',
		'closed_day'   => 'Friday',
		'founded'      => '2019-04-01',
		'customers'    => '5,600+',
		'aun_url'      => 'https://aun-projector.com.bd/',
		'koh_url'      => 'https://kohthaibd.com/',
		'breo_url'     => 'https://breo.bd/',
		'app_url'      => 'https://aun-projector.com.bd/get-aun-care-app/',
		'cf7'          => '',
		'og_image'     => 0,
	);
}

function sl_opt( $key = null ) {
	static $opts = null;
	if ( null === $opts ) {
		$saved = get_option( 'sl_site_options', array() );
		$opts  = wp_parse_args( is_array( $saved ) ? $saved : array(), sl_defaults() );
	}
	if ( null === $key ) {
		return $opts;
	}
	return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
}

/**
 * Years trading, computed from the founding date so it never goes stale.
 */
function sl_years() {
	$founded = sl_opt( 'founded' );
	$start   = $founded ? strtotime( $founded ) : 0;
	if ( ! $start ) {
		return 0;
	}
	$years = (int) floor( ( time() - $start ) / YEAR_IN_SECONDS );
	return max( 0, $years );
}

/**
 * Founding date as "1 April 2019".
 */
function sl_founded_long() {
	$ts = strtotime( sl_opt( 'founded' ) );
	return $ts ? date_i18n( 'j F Y', $ts ) : '';
}

/**
 * The customers-served figure, from the settings screen, split into the parts
 * the different places need.
 *
 * The setting is free text ("6,000+"), but the home page counter has to count
 * up to a number and then re-append the suffix, so it needs them separately.
 *
 * @return array{display:string, num:int, suffix:string}
 */
function sl_customers() {
	static $parts = null;
	if ( null !== $parts ) {
		return $parts;
	}
	$raw    = trim( (string) sl_opt( 'customers' ) );
	$num    = (int) preg_replace( '/[^0-9]/', '', $raw );
	$suffix = ( '' !== $raw && substr( $raw, -1 ) === '+' ) ? '+' : '';
	$parts  = array(
		'display' => $raw,
		'num'     => $num,
		'suffix'  => $suffix,
	);
	return $parts;
}

/* ---------------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------------ */

function sl_img( $name ) {
	return SL_SITE_URL . 'assets/img/' . $name;
}

/**
 * <img> for one of the three brand band photographs, with a 900px source for
 * phones. data-no-lazy keeps WP Rocket from deferring the hero images.
 */
function sl_band_img( $key, $alt, $hero = false ) {
	$src  = sl_img( $key . '.webp' );
	$small = sl_img( $key . '-900.webp' );
	printf(
		'<img src="%1$s" srcset="%2$s 900w, %1$s 1600w" sizes="%3$s" alt="%4$s" width="1600" height="938"%5$s>',
		esc_url( $src ),
		esc_url( $small ),
		$hero ? '100vw' : '(max-width: 880px) 100vw, 52vw',
		esc_attr( $alt ),
		$hero ? ' data-no-lazy="1" fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"'
	);
}

/**
 * The Breo wordmark, inline so it takes currentColor.
 */
function sl_breo_mark() {
	static $svg = null;
	if ( null === $svg ) {
		$file = SL_SITE_DIR . 'assets/breo-wordmark.svg';
		$svg  = file_exists( $file ) ? file_get_contents( $file ) : '';
		$svg  = str_replace( ' xmlns="http://www.w3.org/2000/svg"', '', $svg );
	}
	return $svg;
}

/**
 * Small stroked icons used across the pages. Kept here so the markup stays
 * readable and no icon font is loaded.
 */
function sl_icon( $name, $class = 'sl-ico' ) {
	$paths = array(
		'projector' => '<rect x="2" y="4" width="20" height="13" rx="1.5"/><path d="M12 17v4M8 21h8"/>',
		'school'    => '<path d="M3 8l9-4 9 4-9 4-9-4z"/><path d="M7 10.5V16c0 1.5 2.5 3 5 3s5-1.5 5-3v-5.5"/>',
		'chart'     => '<path d="M4 20V9m5 11V4m5 16v-7m5 7V7"/>',
		'gift'      => '<path d="M20 12v9H4v-9"/><rect x="2" y="7" width="20" height="5" rx="1"/><path d="M12 21V7M12 7S10.5 3 8 3a2.5 2.5 0 000 5h4zM12 7s1.5-4 4-4a2.5 2.5 0 010 5h-4z"/>',
		'wellness'  => '<path d="M12 2.5a4 4 0 014 4v1a4 4 0 01-8 0v-1a4 4 0 014-4z"/><path d="M4 21v-1.5A5.5 5.5 0 019.5 14h5a5.5 5.5 0 015.5 5.5V21"/>',
		'building'  => '<path d="M3 21h18M5 21V8l7-5 7 5v13M9.5 21v-5h5v5"/>',
		'shield'    => '<path d="M12 2.5 4 6v6c0 4.8 3.3 8.5 8 9.5 4.7-1 8-4.7 8-9.5V6l-8-3.5z"/><path d="M9 12l2 2 4-4"/>',
		'bench'     => '<path d="M3 21h18M5 21V9l7-5 7 5v12"/><path d="M9.5 21v-6h5v6M9 12h1.5M13.5 12H15"/>',
		'doc'       => '<path d="M7 3h10a1 1 0 011 1v16l-6-3-6 3V4a1 1 0 011-1z"/><path d="M9.5 8.5h5M9.5 12h5"/>',
		'box'       => '<path d="M20 12v9H4v-9"/><rect x="2" y="7" width="20" height="5" rx="1"/><path d="M12 21V7"/>',
		'tick'      => '<path d="M20 6 9 17l-5-5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $class ),
		$paths[ $name ]
	);
}

/* ---------------------------------------------------------------------------
 * Page creation
 * ------------------------------------------------------------------------ */

/**
 * Make sure each of our pages exists and carries the _sl_page stamp.
 *
 * Adopts the page already living at the slug rather than creating a second
 * one, so the live URLs, menu items and any inbound links all survive. Page
 * content is never touched — these pages render entirely from PHP.
 *
 * @return array Report keyed by page: 'adopted' | 'created' | 'exists'.
 */
function sl_build_pages() {
	$report = array();

	foreach ( sl_pages() as $key => $page ) {
		if ( 'home' === $key ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front && 'page' === get_option( 'show_on_front' ) ) {
				update_post_meta( $front, '_sl_page', 'home' );
				$report['home'] = 'adopted';
			} else {
				$report['home'] = 'front page is not a static page — set it in Settings → Reading';
			}
			continue;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'meta_key'       => '_sl_page',
				'meta_value'     => $key,
				'fields'         => 'ids',
			)
		);
		if ( $existing ) {
			$report[ $key ] = 'exists';
			continue;
		}

		$by_slug = get_page_by_path( $page['slug'] );
		if ( $by_slug ) {
			update_post_meta( $by_slug->ID, '_sl_page', $key );
			$report[ $key ] = 'adopted';
			continue;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => '',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_sl_page', $key );
			$report[ $key ] = 'created';
		} else {
			$report[ $key ] = 'failed';
		}
	}

	return $report;
}
