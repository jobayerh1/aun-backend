<?php
/**
 * SEO: keyword-targeted RankMath titles/descriptions/focus keywords, Product
 * schema fixes, and the buying-guide copy on the shop and category pages.
 *
 * Search demand in Bangladesh is "<product> price in bangladesh / in bd", so
 * category pages target the generic term and product pages the Breo model.
 * RankMath fields are written once per BREO_BD_SEO_VER and only where the
 * field is empty or still holds what we wrote, so edits made in RankMath win.
 */
defined( 'ABSPATH' ) || exit;

define( 'BREO_BD_SEO_VER', '2' );

/** Keyword map: what each URL should rank for. */
function breo_bd_seo_map() {
	return array(
		'front'    => array(
			'title' => 'Breo Bangladesh | Official Breo Massager Store in BD',
			'desc'  => 'Authorized Breo distributor in Bangladesh. Neck, eye and back massagers and massage guns with official warranty, cash on delivery and nationwide delivery.',
			'focus' => 'breo bangladesh, breo massager, massager price in bd',
		),
		'shop'     => array(
			'title' => 'Massager & Massage Machine Price in Bangladesh %currentyear%',
			'desc'  => 'Genuine Breo massage machines in Bangladesh: neck and shoulder, eye, back massagers and massage guns. Official prices, warranty and cash on delivery.',
			'focus' => 'massager price in bangladesh, massage machine price in bd, body massage machine price in bangladesh',
		),
		'cats'     => array(
			'neck-shoulder-massagers' => array(
				'title' => 'Neck Massager Price in Bangladesh %currentyear% | Breo Bangladesh',
				'desc'  => 'Heated, hands-free neck and shoulder massagers from Breo, officially imported to Bangladesh. Cordless, quiet, with local warranty and cash on delivery.',
				'focus' => 'neck massager price in bangladesh, neck massager price in bd, neck and shoulder massager',
			),
			'eye-massagers'           => array(
				'title' => 'Eye Massager Price in Bangladesh %currentyear% | Breo Bangladesh',
				'desc'  => 'Breo eye massagers with warm compress and airbag massage for tired, screen-strained eyes. Official Breo price in Bangladesh, warranty and cash on delivery.',
				'focus' => 'eye massager price in bangladesh, eye massager price in bd, eye massager with heat',
			),
			'back-waist-massagers'    => array(
				'title' => 'Back Massager Price in Bangladesh %currentyear% | Breo Bangladesh',
				'desc'  => 'Cordless Breo back and neck massage pillows with infrared heat, for office chairs and sofas. Official price in Bangladesh, warranty and cash on delivery.',
				'focus' => 'back massager price in bangladesh, massage pillow price in bangladesh, neck and back massager',
			),
			'massage-guns'            => array(
				'title' => 'Massage Gun Price in BD %currentyear% | Breo Bangladesh',
				'desc'  => 'Breo heated massage guns for sore muscles and post-workout recovery. Officially imported, with local warranty and cash on delivery anywhere in Bangladesh.',
				'focus' => 'massage gun price in bd, massage gun price in bangladesh, heated massage gun',
			),
		),
		'products' => array(
			'N990000631' => array(
				'title' => 'Breo N6 Mini Neck Massager Price in Bangladesh',
				'desc'  => 'Buy the Breo N6 Mini heated neck and shoulder massager in Bangladesh: hands-free kneading, 40-45°C heat, cordless. Official warranty and cash on delivery.',
				'focus' => 'breo n6 mini, neck massager price in bd, heated neck and shoulder massager',
			),
			'N910200212' => array(
				'title' => 'Breo See KE Eye Massager Price in Bangladesh',
				'desc'  => 'Breo See KE eye massager with 43°C warm compress and airbag massage for tired, screen-strained eyes. Official price in BD, warranty and cash on delivery.',
				'focus' => 'breo see ke, eye massager price in bd, eye massager with heat',
			),
			'N990000981' => array(
				'title' => 'Breo P2 Neck & Back Massage Pillow Price in Bangladesh',
				'desc'  => 'Breo P2 cordless neck and back massage pillow with 5-point heat, 3 modes and a 100-minute battery. Official price in BD, warranty and cash on delivery.',
				'focus' => 'breo p2, massage pillow price in bangladesh, neck and back massager',
			),
			'N990000360' => array(
				'title' => 'Breo No.7 Heated Massage Gun Price in Bangladesh',
				'desc'  => 'Breo No.7 heated massage gun: 2,800 strokes a minute, a 45°C head that warms in seconds, only 405 g. Official price in BD, warranty and cash on delivery.',
				'focus' => 'breo no.7, massage gun price in bd, heated massage gun',
			),
		),
	);
}

/* ------------------------------------------------------------------------
 * Write the map into RankMath's own fields (visible and editable there).
 * --------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	if ( ! defined( 'RANK_MATH_VERSION' ) || BREO_BD_SEO_VER === get_option( 'breo_bd_seo_ver' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( breo_bd_seo_sync() ) {
		update_option( 'breo_bd_seo_ver', BREO_BD_SEO_VER, false );
		delete_transient( 'breo_bd_seo_wait' );
	} elseif ( ! get_transient( 'breo_bd_seo_wait' ) ) {
		// no products yet: try again later instead of on every admin page load
		set_transient( 'breo_bd_seo_wait', 1, HOUR_IN_SECONDS );
	}
} );

/** Writes one RankMath field unless someone has changed it since we last wrote it. */
function breo_bd_seo_put( $kind, $id, $key, $value ) {
	$get  = 'term' === $kind ? 'get_term_meta' : 'get_post_meta';
	$set  = 'term' === $kind ? 'update_term_meta' : 'update_post_meta';
	$mark = (array) $get( $id, '_breo_seo', true );
	$cur  = $get( $id, $key, true );
	$ours = isset( $mark[ $key ] ) && md5( maybe_serialize( $cur ) ) === $mark[ $key ];
	if ( '' === $cur || array() === $cur || $ours ) {
		$set( $id, $key, $value );
		$mark[ $key ] = md5( maybe_serialize( $value ) );
		$set( $id, '_breo_seo', $mark );
	}
}

function breo_bd_seo_apply( $kind, $id, $m ) {
	breo_bd_seo_put( $kind, $id, 'rank_math_title', $m['title'] );
	breo_bd_seo_put( $kind, $id, 'rank_math_description', $m['desc'] );
	breo_bd_seo_put( $kind, $id, 'rank_math_focus_keyword', $m['focus'] );
}

/** Returns false while the products aren't imported yet, so it retries later. */
function breo_bd_seo_sync() {
	if ( get_transient( 'breo_bd_seo_wait' ) ) {
		return false;
	}
	$map = breo_bd_seo_map();
	$hit = 0;
	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
		breo_bd_seo_apply( 'post', (int) get_option( 'page_on_front' ), $map['front'] );
	}
	$shop = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
	if ( $shop > 0 ) {
		breo_bd_seo_apply( 'post', $shop, $map['shop'] );
	}
	foreach ( $map['cats'] as $slug => $m ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $t ) {
			breo_bd_seo_apply( 'term', $t->term_id, $m );
		}
	}
	foreach ( $map['products'] as $sku => $m ) {
		$pid = wc_get_product_id_by_sku( $sku );
		if ( $pid ) {
			breo_bd_seo_apply( 'post', $pid, $m );
			$hit++;
		}
	}
	// Cart, checkout and account pages: noindex in RankMath too, which also drops them from its sitemap.
	foreach ( array( 'cart', 'checkout', 'myaccount' ) as $wc_page ) {
		$id = wc_get_page_id( $wc_page );
		if ( $id > 0 ) {
			breo_bd_seo_put( 'post', $id, 'rank_math_robots', array( 'noindex', 'follow' ) );
		}
	}
	return $hit > 0;
}

/* ------------------------------------------------------------------------
 * Schema: RankMath builds Product from the SEO title ("... Price in
 * Bangladesh") and has no brand. Use the real name, add the brand, delivery
 * details from the Breo settings, and drop the empty seller logo.
 * --------------------------------------------------------------------- */

add_filter( 'rank_math/json_ld', function ( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$product = is_singular( 'product' ) ? wc_get_product( get_queried_object_id() ) : null;
	foreach ( $data as $k => $node ) {
		if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
			continue;
		}
		$types = (array) $node['@type'];
		if ( is_front_page() && array_intersect( $types, array( 'Article', 'BlogPosting', 'Person' ) ) ) {
			unset( $data[ $k ] ); // the homepage is a store front, not an article by a person
			continue;
		}
		if ( $product && in_array( 'Product', $types, true ) ) {
			$d                    = breo_bd_product_data( $product );
			$data[ $k ]['name']   = $d ? $d['name'] : $product->get_name();
			$data[ $k ]['brand']  = array( '@type' => 'Brand', 'name' => 'Breo' );
			$data[ $k ]['offers'] = breo_bd_seo_offer( isset( $node['offers'] ) ? $node['offers'] : array() );
		}
	}
	return $data;
}, 99 );

function breo_bd_seo_offer( $offer ) {
	if ( ! is_array( $offer ) ) {
		return $offer;
	}
	if ( isset( $offer['seller'] ) && is_array( $offer['seller'] ) && empty( $offer['seller']['logo'] ) ) {
		unset( $offer['seller']['logo'] );
	}
	// Delivery details need a real fee; the larger (outside Dhaka) one keeps the promise conservative.
	$fee  = max( (float) breo_bd_opt( 'dhaka_fee' ), (float) breo_bd_opt( 'outside_fee' ) );
	$days = max( (int) breo_bd_opt( 'dhaka_days' ), (int) breo_bd_opt( 'outside_days' ) );
	if ( $fee > 0 && $days > 0 && empty( $offer['shippingDetails'] ) ) {
		$offer['shippingDetails'] = array(
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => $fee, 'currency' => 'BDT' ),
			'shippingDestination' => array( '@type' => 'DefinedRegion', 'addressCountry' => 'BD' ),
			'deliveryTime'        => array(
				'@type'        => 'ShippingDeliveryTime',
				'handlingTime' => array( '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY' ),
				'transitTime'  => array( '@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => $days, 'unitCode' => 'DAY' ),
			),
		);
	}
	return $offer;
}

/* ------------------------------------------------------------------------
 * Buying-guide copy for the shop and category pages. Each category has one
 * or two products, so without this the pages are too thin to rank for
 * "<category> price in bangladesh".
 * --------------------------------------------------------------------- */

function breo_bd_category_seo() {
	return array(
		''                        => array(
			'noun'  => 'Breo massager',
			'lead'  => 'Genuine Breo massagers and massage machines for the neck, eyes, back and muscles, officially imported, with local warranty and cash on delivery anywhere in Bangladesh.',
			'h2'    => 'Which Breo massager is right for you?',
			'text'  => array(
				'Start with where you feel it. Stiffness across the neck and shoulders after a long day at a desk is what a [neck massager](neck-shoulder-massagers) is made for. Heavy, tired eyes after hours on a screen call for an [eye massager](eye-massagers) with a warm compress. An aching lower back from office chairs and long commutes is where a [back massage pillow](back-waist-massagers) helps, and tight legs and shoulders after exercise are what a [massage gun](massage-guns) is for.',
				'Every Breo on this page is sold by Breo Bangladesh, the authorized Breo distributor. That means a genuine device, a %warranty% warranty handled here in Bangladesh, and support in Bangla or English on WhatsApp.',
			),
			'faq'   => array(
				array( 'Are these original Breo products?', 'Yes. Breo Bangladesh is an authorized Breo distributor, and every device is officially imported with a local warranty.' ),
			),
		),
		'neck-shoulder-massagers' => array(
			'noun'  => 'neck massager',
			'lead'  => 'Heated, hands-free neck and shoulder massagers from Breo, officially imported, with local warranty and cash on delivery anywhere in Bangladesh.',
			'h2'    => 'How to choose a neck massager',
			'text'  => array(
				'Most neck stiffness comes from the same few habits: long hours at a desk, looking down at a phone and long commutes in traffic. A good neck massager works on the muscles that tighten from that posture, along the back of the neck and across the tops of the shoulders.',
				'Look for three things. Heat, because warmth helps tight muscles let go faster. A cordless battery, so you can use it at your desk or on the sofa. And a weight you can wear for 15 minutes without noticing. The Breo N6 mini has all three: 40-45°C heat, a 2500mAh battery and hands-free kneading in a 1.25 kg wearable.',
				'Buy from an authorized seller. Cheap copies of branded massagers are common online, and they rarely come with a warranty that works. Every Breo from Breo Bangladesh is covered by a %warranty% warranty here in Bangladesh.',
			),
			'faq'   => array(
				array( 'Can I use a neck massager every day?', 'Yes. About 15 minutes per session, up to 2-3 times a day, is the usual routine. Anyone with a medical condition, a pacemaker or a neck injury should ask a doctor first.' ),
				array( 'Can I use it while working?', 'Yes. The N6 mini is hands-free and quiet (under 55dB), so it works on your shoulders while you work.' ),
			),
		),
		'eye-massagers'           => array(
			'noun'  => 'eye massager',
			'lead'  => 'Breo eye massagers with a warm compress and gentle airbag massage for tired, screen-strained eyes. Official warranty and cash on delivery across Bangladesh.',
			'h2'    => 'How to choose an eye massager',
			'text'  => array(
				'Hours on a laptop and phone leave the eyes and the muscles around them tired and tense. An eye massager combines two things that help you switch off: a warm compress over the eyes, and a soft rhythmic massage around the eyes and temples.',
				'Check the temperature control first. A sensor-controlled warm compress, like the 43°C on the Breo See KE, stays comfortable for a full 10-minute session. Then look at the weight and the fit: a light, foldable massager is one you will actually use every evening, and one you can take on a trip.',
				'Buy from an authorized seller, so the device is genuine and the warranty is honoured. Breo Bangladesh covers every eye massager with a %warranty% warranty in Bangladesh.',
			),
			'faq'   => array(
				array( 'How long should I use an eye massager?', 'About 10 minutes, once or twice a day. Take off glasses and contact lenses first.' ),
				array( 'Who should not use an eye massager?', 'Don\'t use one after recent eye surgery, or with conditions such as glaucoma, retinal problems or an eye infection, unless your doctor approves.' ),
			),
		),
		'back-waist-massagers'    => array(
			'noun'  => 'back massager',
			'lead'  => 'Cordless Breo massage pillows for the neck and lower back, with infrared heat. Official warranty and cash on delivery anywhere in Bangladesh.',
			'h2'    => 'How to choose a back massager',
			'text'  => array(
				'Lower back ache is the price of long days in an office chair or a car seat. A massage pillow is the simplest way to deal with it: you place it behind your lower back when you sit, or behind your neck when you lean back on the sofa.',
				'Look for kneading heads that are shaped for the curve of the back, heat you can switch on or off, and a battery, so the pillow works without a cable across the room. The Breo P2 has 5-point infrared heat at 40 or 45°C, three modes and about 100 minutes on a charge.',
				'Genuine matters for anything with a heater and a battery. Every Breo from Breo Bangladesh is officially imported and covered by a %warranty% warranty in Bangladesh.',
			),
			'faq'   => array(
				array( 'Can I use a massage pillow for both my neck and back?', 'Yes. The Breo P2 is designed for both: behind your neck when reclining, or against your lower back when sitting.' ),
				array( 'Who should not use a back massager?', 'Ask a doctor first if you are pregnant, have a pacemaker or a spinal injury, or have reduced sensitivity to heat.' ),
			),
		),
		'massage-guns'            => array(
			'noun'  => 'massage gun',
			'lead'  => 'Breo heated massage guns for sore muscles and post-workout recovery. Officially imported, with local warranty and cash on delivery across Bangladesh.',
			'h2'    => 'How to choose a massage gun',
			'text'  => array(
				'A massage gun delivers fast, short strokes deep into the muscle. It is the quickest way to loosen tight calves, thighs and shoulders after football, the gym or a long day on your feet.',
				'Compare three numbers. Speed (strokes per minute), stroke depth and push force decide how deep it reaches: the Breo No.7 gives up to 2,800 strokes a minute with a 10 mm stroke and 8-10 kg of force. Then check the weight, because a light gun is easier to use on your own back and shoulders. A heated head is a bonus: it warms tight muscles so they release faster.',
				'Low-cost massage guns are everywhere, but their batteries and motors rarely last. Every Breo from Breo Bangladesh is genuine and covered by a %warranty% warranty in Bangladesh.',
			),
			'faq'   => array(
				array( 'How long should I use a massage gun?', 'One to two minutes per muscle group, and no more than 10-15 minutes per session. Avoid bones, joints, the spine, the front of the neck and the head.' ),
				array( 'Is a massage gun good for beginners?', 'Yes. Start on the lowest level and move slowly along the muscle. Anyone with a medical condition, a pacemaker or recent surgery should ask a doctor first.' ),
			),
		),
	);
}

/** Price + delivery FAQs, built from the live prices and the Breo settings. */
function breo_bd_seo_auto_faq( $noun, $products ) {
	$faq    = array();
	$priced = array();
	foreach ( $products as $p ) {
		if ( $p && '' !== $p->get_price() ) {
			$d        = breo_bd_product_data( $p );
			$priced[] = ( $d ? 'Breo ' . $d['short_name'] : $p->get_name() ) . ': ' . breo_bd_plain_price( $p );
		}
	}
	if ( $priced ) {
		$faq[] = array(
			'What is the price of a ' . $noun . ' in Bangladesh?',
			'At Breo Bangladesh: ' . implode( '; ', $priced ) . '. Every price includes the official warranty, and the delivery charge is shown at checkout.',
		);
	}
	$dd = breo_bd_opt( 'dhaka_days' );
	$od = breo_bd_opt( 'outside_days' );
	if ( $dd && $od ) {
		$faq[] = array(
			'Do you deliver outside Dhaka?',
			'Yes, anywhere in Bangladesh: within ' . $dd . ' working days inside Dhaka and ' . $od . ' working days outside Dhaka. You can pay ' . strtolower( breo_bd_opt( 'payments' ) ) . '.',
		);
	}
	return $faq;
}

/** Guide + FAQ block printed under the product grid. */
function breo_bd_category_guide_html( $term ) {
	$all = breo_bd_category_seo();
	$key = $term ? $term->slug : '';
	if ( ! isset( $all[ $key ] ) ) {
		return '';
	}
	$c        = $all[ $key ];
	$warranty = breo_bd_warranty_period() ? breo_bd_warranty_period() : 'an official';
	$products = wc_get_products( array( 'status' => 'publish', 'limit' => 12, 'category' => $term ? array( $term->slug ) : array() ) );
	$faq      = array_merge( breo_bd_seo_auto_faq( $c['noun'], $products ), $c['faq'] );

	$h  = '<section class="breo-s breo-catguide"><div class="breo-wrap breo-narrow">';
	$h .= '<h2 class="breo-title">' . esc_html( $c['h2'] ) . '</h2>';
	foreach ( $c['text'] as $para ) {
		$para = esc_html( str_replace( '%warranty%', $warranty, $para ) );
		// [label](category-slug) -> internal link to that category
		$para = preg_replace_callback( '/\[([^\]]+)\]\(([a-z0-9-]+)\)/', function ( $m ) {
			$t = get_term_by( 'slug', $m[2], 'product_cat' );
			return $t ? '<a href="' . esc_url( get_term_link( $t ) ) . '">' . $m[1] . '</a>' : $m[1];
		}, $para );
		$h .= '<p>' . $para . '</p>';
	}
	if ( $faq ) {
		$h .= '<h2 class="breo-title breo-catguide__faqh">Questions, answered</h2><div class="breo-faq">';
		foreach ( $faq as $q ) {
			$h .= '<details><summary>' . esc_html( $q[0] ) . '</summary><p>' . esc_html( $q[1] ) . '</p></details>';
		}
		$h .= '</div>';
	}
	return $h . '</div></section>';
}
