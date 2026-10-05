<?php
/**
 * Titles, meta, Open Graph and schema — everything an SEO plugin would do,
 * for the six pages this plugin owns, and nothing else.
 *
 * The piece that matters most is the Organization → subOrganization graph:
 * it is the machine-readable statement that AUN, Kohthai and Breo are one
 * company, which is what the "<brand> distributor in Bangladesh" searches
 * need in order to resolve to us. See smartliving-seo-plan.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title and description for the current page, with the settings override
 * applied if one has been saved.
 */
function sl_seo_for( $key ) {
	$defaults = sl_c_seo();
	$base     = isset( $defaults[ $key ] ) ? $defaults[ $key ] : array(
		'title' => '',
		'desc'  => '',
	);

	$over = get_option( 'sl_site_seo', array() );
	if ( is_array( $over ) && ! empty( $over[ $key ] ) ) {
		if ( ! empty( $over[ $key ]['title'] ) ) {
			$base['title'] = $over[ $key ]['title'];
		}
		if ( ! empty( $over[ $key ]['desc'] ) ) {
			$base['desc'] = $over[ $key ]['desc'];
		}
	}
	return $base;
}

/**
 * Replace the theme's title entirely on our pages.
 */
function sl_seo_title( $title ) {
	$key = sl_current_page();
	if ( '' === $key ) {
		return $title;
	}
	if ( '404' === $key ) {
		return 'Page not found | Smart Living Bangladesh';
	}
	$seo = sl_seo_for( $key );
	return $seo['title'] ? $seo['title'] : $title;
}
add_filter( 'pre_get_document_title', 'sl_seo_title', 99 );

/**
 * Description, canonical, Open Graph and Twitter tags.
 */
function sl_seo_head() {
	$key = sl_current_page();
	if ( '' === $key ) {
		return;
	}

	// A 404 gets our shell and a title, but no canonical, no social card and no
	// schema — it is not a page anyone should land on from search.
	if ( '404' === $key ) {
		printf(
			'<meta name="description" content="%s">' . "
",
			esc_attr( 'Page not found on smartliving.com.bd.' )
		);
		return;
	}

	$seo  = sl_seo_for( $key );
	$url  = sl_url( $key );
	$desc = wp_strip_all_tags( html_entity_decode( $seo['desc'], ENT_QUOTES, 'UTF-8' ) );

	$og_id  = (int) sl_opt( 'og_image' );
	$og_img = $og_id ? wp_get_attachment_image_url( $og_id, 'full' ) : sl_img( 'og-card.png' );

	echo "\n<!-- Smart Living Site -->\n";
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );

	printf( '<meta property="og:type" content="website">' . "\n" );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( 'Smart Living Bangladesh' ) );
	printf( '<meta property="og:locale" content="en_US">' . "\n" );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $seo['title'] ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $og_img ) );
	printf( '<meta property="og:image:width" content="1200">' . "\n" );
	printf( '<meta property="og:image:height" content="630">' . "\n" );

	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $seo['title'] ) );
	printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $og_img ) );
}
add_action( 'wp_head', 'sl_seo_head', 2 );

/**
 * The JSON-LD graph.
 */
function sl_seo_jsonld() {
	$key = sl_current_page();
	if ( '' === $key || '404' === $key ) {
		return;
	}

	$o     = sl_opt();
	$home  = home_url( '/' );
	$org   = $home . '#organization';
	$place = $home . '#localbusiness';
	$seo   = sl_seo_for( $key );
	$pages = sl_pages();

	$address = array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => $o['address_1'],
		'addressLocality' => $o['locality'],
		'postalCode'      => $o['postcode'],
		'addressCountry'  => 'BD',
	);

	$graph = array();

	// The group itself, and the three brands that belong to it.
	$graph[] = array(
		'@type'           => 'Organization',
		'@id'             => $org,
		'name'            => 'Smart Living Bangladesh',
		'alternateName'   => 'Smart Living BD',
		'url'             => $home,
		'description'     => 'Import and distribution company in Dhaka, Bangladesh. Authorized distributor of AUN projectors and Breo massagers, and owner of the Kohthai Bags label.',
		'foundingDate'    => $o['founded'],
		'logo'            => array(
			'@type' => 'ImageObject',
			'url'   => sl_img( 'sl-logo.webp' ),
		),
		'address'         => $address,
		'telephone'       => $o['phone'],
		'email'           => $o['email'],
		'sameAs'          => array_values( array_filter( array( $o['facebook'] ) ) ),
		'subOrganization' => array(
			array(
				'@type'       => 'Organization',
				'name'        => 'AUN Projector Bangladesh',
				'url'         => $o['aun_url'],
				'description' => 'Authorized AUN projector distributor in Bangladesh.',
			),
			array(
				'@type'       => 'Organization',
				'name'        => 'Kohthai Bags',
				'url'         => $o['koh_url'],
				'description' => "Women's handbag label — tote, crossbody, clutch, shoulder and saddle bags.",
			),
			array(
				'@type'       => 'Organization',
				'name'        => 'Breo Bangladesh',
				'url'         => $o['breo_url'],
				'description' => 'Authorized Breo distributor in Bangladesh — neck, eye and body massagers and massage guns.',
			),
		),
	);

	// The physical office.
	$local = array(
		'@type'               => 'LocalBusiness',
		'@id'                 => $place,
		'name'                => 'Smart Living Bangladesh',
		'url'                 => $home,
		'parentOrganization'  => array( '@id' => $org ),
		'address'             => $address,
		'telephone'           => $o['phone'],
		'email'               => $o['email'],
		'image'               => sl_img( 'sl-logo.webp' ),
		'priceRange'          => 'BDT',
		'openingHoursSpecification' => array(
			array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ),
				'opens'     => '10:00',
				'closes'    => '18:00',
			),
		),
	);
	if ( $o['maps_url'] ) {
		$local['hasMap'] = $o['maps_url'];
	}
	$graph[] = $local;

	// The page.
	$graph[] = array(
		'@type'      => 'WebPage',
		'@id'        => sl_url( $key ) . '#webpage',
		'url'        => sl_url( $key ),
		'name'       => $seo['title'],
		'description' => wp_strip_all_tags( html_entity_decode( $seo['desc'], ENT_QUOTES, 'UTF-8' ) ),
		'isPartOf'   => array( '@id' => $org ),
		'about'      => array( '@id' => $org ),
		'inLanguage' => 'en',
	);

	// Breadcrumbs on the inner pages.
	if ( 'home' !== $key ) {
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Home',
					'item'     => $home,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $pages[ $key ]['title'],
					'item'     => sl_url( $key ),
				),
			),
		);
	}

	// FAQ pages — the two B2B pages carry real question/answer pairs.
	$faq = array();
	if ( 'corporate' === $key ) {
		$faq = sl_c_corporate_faq();
	} elseif ( 'dealers' === $key ) {
		$faq = sl_c_dealer_faq();
	}
	if ( $faq ) {
		$items = array();
		foreach ( $faq as $row ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( html_entity_decode( $row[0], ENT_QUOTES, 'UTF-8' ) ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( html_entity_decode( $row[1], ENT_QUOTES, 'UTF-8' ) ),
				),
			);
		}
		$graph[] = array(
			'@type'      => 'FAQPage',
			'@id'        => sl_url( $key ) . '#faq',
			'mainEntity' => $items,
		);
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	// data-no-* keeps WP Rocket and friends from minifying or deferring the
	// structured data, which breaks it.
	echo "\n" . '<script type="application/ld+json" data-no-optimize="1" data-no-minify="1" data-noptimize="1" data-cfasync="false">' . "\n";
	echo $json . "\n";
	echo '</script>' . "\n";
}
add_action( 'wp_head', 'sl_seo_jsonld', 3 );

/**
 * The old home page had no description or OG tags at all, and other plugins
 * may still try to emit a generic one. Drop WordPress's generator tag and the
 * shortlink while we are here — neither helps and both leak version numbers.
 */
function sl_seo_tidy_head() {
	if ( sl_current_page() === '' ) {
		return;
	}
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
}
add_action( 'wp', 'sl_seo_tidy_head' );
