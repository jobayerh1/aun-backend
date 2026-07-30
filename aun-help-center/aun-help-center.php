<?php
/**
 * Plugin Name: AUN Help Center
 * Description: Searchable, bilingual (বাংলা/English), MODEL-AWARE self-serve support FAQ. Model list is pulled DYNAMICALLY from your WooCommerce projectors (category "projector-price"), excluding discontinued. Each model's tutorial videos (resolved from its YouTube playlist via aun-tutorials.php) are embedded INSIDE the matching question, playing in an in-page lightbox. Shortcode: [aun_help_center] (preselect: model="product-slug").
 * Version: 2.7.0
 * Author: AUN Projector Bangladesh
 * Text Domain: aun-help-center
 *
 * v2.7.0: code-review hardening + per-model USER MANUALS.
 *         • New KB-020 "User Manual" — extracts each product's WP File Download shortcode ([wpfd_category id=N])
 *           from its Download tab and renders THAT model's manual list inside the answer (server-rendered).
 *         • Version constant AUN_HC_VER drives the cache auto-flush (was a stale hardcoded '2.6.0').
 *         • Empty model list is cached for only 5 min (a WooCommerce hiccup can no longer blank the dropdown for 6h).
 *         • YouTube video ids validated server-side; embed URL encoded client-side; wp_json_encode false-guard.
 * v2.6.3: KB-017 HDMI answer now covers the COMPUTER side too — Windows: Win+P → Duplicate/Extend/Second screen
 *         only (same on Win 7/8/10/11); MacBook: auto-detect, else System Settings → Displays → Mirror/Extend
 *         (+ USB-C-to-HDMI adapter note); TV box needs nothing.
 * v2.6.2: per-model answer variants — questions that differ by OS type (iPhone mirror, Android cast, browser,
 *         APK install) have a non-certified answer (a_bn/a_en) and a certified one (a_bn_cert/a_en_cert); the
 *         plugin shows ONLY the one matching the selected model (no more universal "on certified / on others").
 * v2.6.1: lightbox close-button fix (cap box width by viewport height + z-index, matching aun-tutorials so the
 *         × never gets cut off); per-question video shown as a thumbnail card (YouTube preview + play overlay).
 * v2.6: videos are embedded IN each relevant answer (per-question), not a separate grid. Resolves the model's
 *       full playlist via aun-tutorials' aun_tut_fetch_playlist() and classifies each title -> FAQ topic.
 *       Own in-page lightbox (no AJAX). KB-011 (Pixel) back to non-certified-only. Added KB-019 (Change Language).
 * v2.5: ran the tutorial plugin's lightbox JS after AJAX inject; casting answers (AirScreen / pre-installed apps).
 * v2.4: AJAX tutorials, extracted shortcode only. v2.0: dynamic brain.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AUN_HC_VER', '2.7.0' ); // single source of truth — bump on every release; drives the cache auto-flush

/* ============================================================
 *  CONFIG
 * ============================================================ */
function aun_hc_config() {
	return apply_filters( 'aun_hc_config', array(
		'category'        => 'projector-price',        // product_cat slug to list (the projector catalog)
		'certified_match' => 'android tv',             // certified Android TV when product name/short-desc contains this
		'tab_meta_key'    => 'yikes_woo_products_tabs', // Custom Product Tabs for WooCommerce (Web Builder 143)
		'cache_ttl'       => 6 * HOUR_IN_SECONDS,
	) );
}

/* Label -> FAQ topic synonyms (matched against each tutorial's title). iOS before Android. */
function aun_hc_topic_map() {
	return array(
		'keystone'        => array( 'keystone', 'trapezoid', 'placement', 'alignment' ),
		'focus'           => array( 'focus' ),
		'ios_mirror'      => array( 'ios', 'iphone', 'airplay' ),
		'android_mirror'  => array( 'android', 'mirror', 'cast', 'miracast', 'smart view', 'screen mirror' ),
		'hdmi'            => array( 'hdmi' ),
		'flash_drive'     => array( 'flash', 'usb', 'pen drive', 'pendrive' ),
		'maintenance'     => array( 'maintenance', 'clean', 'dust', 'care' ),
		'factory_reset'   => array( 'factory', 'reset' ),
		'change_language' => array( 'language' ),
	);
}

/** Classify a tutorial title into a FAQ topic key, or '' if none. */
function aun_hc_classify( $title ) {
	$l = strtolower( (string) $title );
	foreach ( aun_hc_topic_map() as $topic => $syns ) {
		foreach ( $syns as $s ) {
			if ( strpos( $l, $s ) !== false ) return $topic;
		}
	}
	return '';
}

/* ============================================================
 *  DYNAMIC DATA — pulled from WooCommerce products
 * ============================================================ */

/** Pull a product's tutorial shortcode content (custom "Tutorial" tab -> fallback any meta -> fallback post content). */
function aun_hc_product_tutorials_content( $pid, $cfg ) {
	$content = '';
	$tabs = get_post_meta( $pid, $cfg['tab_meta_key'], true );
	if ( is_array( $tabs ) ) {
		foreach ( $tabs as $tab ) {
			if ( is_array( $tab ) && ! empty( $tab['content'] ) && isset( $tab['title'] ) && stripos( $tab['title'], 'tutorial' ) !== false ) {
				$content = $tab['content'];
				break;
			}
		}
		if ( stripos( $content, 'aun_tutorials' ) === false ) {
			foreach ( $tabs as $tab ) {
				if ( is_array( $tab ) && ! empty( $tab['content'] ) ) $content .= ' ' . $tab['content'];
			}
		}
	}
	if ( stripos( $content, 'aun_tutorials' ) === false ) {
		foreach ( get_post_meta( $pid ) as $vals ) {
			foreach ( (array) $vals as $val ) {
				$s = is_string( $val ) ? $val : maybe_serialize( $val );
				if ( is_string( $s ) && stripos( $s, 'aun_tutorials' ) !== false ) { $content = $s; break 2; }
			}
		}
	}
	if ( stripos( $content, 'aun_tutorials' ) === false ) {
		$pc = get_post_field( 'post_content', $pid );
		if ( is_string( $pc ) && stripos( $pc, 'aun_tutorials' ) !== false ) $content = $pc;
	}
	return $content;
}

/** Extract ONLY the [aun_tutorials ...] shortcode from a blob. */
function aun_hc_extract_tutorials_shortcode( $content ) {
	if ( ! is_string( $content ) || $content === '' ) return '';
	if ( preg_match( '/\[aun_tutorials\b[^\]]*\]/i', $content, $m ) ) return $m[0];
	return '';
}

/**
 * Resolve a model's tutorial videos to [ ['id'=>,'title'=>], ... ] from its [aun_tutorials] shortcode:
 * the full playlist (via aun-tutorials' own fetcher, which uses the YouTube API key + 6h cache) plus any inline ids.
 */
function aun_hc_resolve_videos( $shortcode ) {
	$out = array();
	if ( ! is_string( $shortcode ) || $shortcode === '' ) return $out;

	// playlist="…list=PLxxxx" or a bare playlist ID
	if ( preg_match( '/\bplaylist=("|\')(.*?)\1/i', $shortcode, $pm ) ) {
		$pid = trim( $pm[2] );
		if ( preg_match( '/[?&]list=([A-Za-z0-9_-]+)/', $pid, $lm ) ) $pid = $lm[1];
		if ( preg_match( '/^[A-Za-z0-9_-]{10,}$/', $pid ) && function_exists( 'aun_tut_fetch_playlist' ) ) {
			foreach ( aun_tut_fetch_playlist( $pid ) as $v ) {
				if ( ! empty( $v['id'] ) && preg_match( '/^[A-Za-z0-9_-]{6,15}$/', $v['id'] ) ) {
					$out[] = array( 'id' => $v['id'], 'title' => isset( $v['title'] ) ? $v['title'] : '' );
				}
			}
		}
	}
	// inline ids="VIDEOID:Title, …"
	if ( preg_match( '/\bids=("|\')(.*?)\1/i', $shortcode, $im ) ) {
		foreach ( explode( ',', $im[2] ) as $pair ) {
			$pair = trim( $pair );
			if ( $pair === '' ) continue;
			$p     = explode( ':', $pair, 2 );
			$id    = trim( $p[0] );
			$title = isset( $p[1] ) ? trim( $p[1] ) : '';
			if ( $id !== '' && preg_match( '/^[A-Za-z0-9_-]{6,15}$/', $id ) ) $out[] = array( 'id' => $id, 'title' => $title );
		}
	}
	return $out;
}

/** Extract the WP File Download category id from a product's "Download" tab ([wpfd_category id="305"]). */
function aun_hc_product_manual_id( $pid, $cfg ) {
	$blob = '';
	$tabs = get_post_meta( $pid, $cfg['tab_meta_key'], true );
	if ( is_array( $tabs ) ) {
		foreach ( $tabs as $tab ) {
			if ( is_array( $tab ) && ! empty( $tab['content'] ) && isset( $tab['title'] ) && stripos( $tab['title'], 'download' ) !== false ) {
				$blob = $tab['content'];
				break;
			}
		}
		if ( stripos( $blob, 'wpfd_category' ) === false ) {
			foreach ( $tabs as $tab ) {
				if ( is_array( $tab ) && ! empty( $tab['content'] ) ) $blob .= ' ' . $tab['content'];
			}
		}
	}
	if ( stripos( $blob, 'wpfd_category' ) === false ) {
		foreach ( get_post_meta( $pid ) as $vals ) {
			foreach ( (array) $vals as $val ) {
				$s = is_string( $val ) ? $val : maybe_serialize( $val );
				if ( is_string( $s ) && stripos( $s, 'wpfd_category' ) !== false ) { $blob = $s; break 2; }
			}
		}
	}
	return preg_match( '/\[wpfd_category[^\]]*\bid=["\']?(\d+)/i', $blob, $m ) ? (int) $m[1] : 0;
}

/** Derive a short model name: first word after "AUN" + optional Pro suffix (handles AKEY9S, A005 vs A005 Pro). */
function aun_hc_short_label( $title ) {
	$t = trim( preg_replace( '/^\s*AUN\s+/i', '', (string) $title ) );
	if ( $t === '' ) return $title;
	$parts = preg_split( '/\s+/', $t );
	$label = $parts[0];
	if ( isset( $parts[1] ) && preg_match( '/^(pro|plus|max|ultra|lite)$/i', $parts[1] ) ) {
		$label .= ' ' . ucfirst( strtolower( $parts[1] ) );
	}
	return $label;
}

/** Certified Android TV? Auto-detected from the product name + short description. */
function aun_hc_is_certified( $pid, $cfg ) {
	$needle = strtolower( $cfg['certified_match'] );
	if ( $needle === '' ) return 0;
	$hay = strtolower( get_the_title( $pid ) . ' ' . get_post_field( 'post_excerpt', $pid ) );
	return ( strpos( $hay, $needle ) !== false ) ? 1 : 0;
}

/** Build the model list by querying WooCommerce. Heavy — always called through the cache. */
function aun_hc_build_models() {
	$cfg = aun_hc_config();
	$models = array();

	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			'relation' => 'AND',
			array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cfg['category'] ),
			array( 'taxonomy' => 'product_discontinued', 'field' => 'slug', 'terms' => array( 'dp-discontinued' ), 'operator' => 'NOT IN' ),
		),
	) );

	foreach ( $q->posts as $pid ) {
		$videos = aun_hc_resolve_videos( aun_hc_extract_tutorials_shortcode( aun_hc_product_tutorials_content( $pid, $cfg ) ) );
		$topics = array();
		foreach ( $videos as $v ) {
			$t = aun_hc_classify( $v['title'] );
			if ( $t !== '' && ! isset( $topics[ $t ] ) ) $topics[ $t ] = $v['id'];
		}
		$models[ get_post_field( 'post_name', $pid ) ] = array(
			'label'     => aun_hc_short_label( get_the_title( $pid ) ),
			'certified' => aun_hc_is_certified( $pid, $cfg ),
			'topics'    => $topics,
			'manual'    => aun_hc_product_manual_id( $pid, $cfg ),
		);
	}
	return $models;
}

/** Cached accessor (rebuilds if the cache has a stale shape from an older version). */
function aun_hc_models() {
	$cache = get_transient( 'aun_hc_models' );
	if ( is_array( $cache ) ) {
		if ( empty( $cache ) ) return $cache; // cached-empty has a short TTL (below) — retries soon
		$first = reset( $cache );
		if ( is_array( $first ) && array_key_exists( 'manual', $first ) ) return $cache;
	}
	$models = ( function_exists( 'WC' ) && class_exists( 'WP_Query' ) ) ? aun_hc_build_models() : array();
	// Never poison the cache for hours with an empty list (e.g. WooCommerce briefly unavailable) — retry in 5 min.
	set_transient( 'aun_hc_models', $models, empty( $models ) ? 5 * MINUTE_IN_SECONDS : aun_hc_config()['cache_ttl'] );
	return $models;
}

/** Invalidate the cache whenever a product (or its tabs/terms) changes. */
function aun_hc_flush_cache() { delete_transient( 'aun_hc_models' ); }
add_action( 'save_post_product', 'aun_hc_flush_cache' );
add_action( 'woocommerce_update_product', 'aun_hc_flush_cache' );
add_action( 'woocommerce_new_product', 'aun_hc_flush_cache' );
add_action( 'edited_product_cat', 'aun_hc_flush_cache' );

/** Auto-flush the cache when the plugin version changes, so structure updates take effect without a product save. */
add_action( 'init', function() {
	if ( get_option( 'aun_hc_ver' ) !== AUN_HC_VER ) { aun_hc_flush_cache(); update_option( 'aun_hc_ver', AUN_HC_VER ); }
} );

/* ============================================================
 *  STATIC FAQ TEXT. topic = which tutorial video to embed (or '' for none).
 *  applies: 'all' | 'uncertified' (hidden for certified models).
 * ============================================================ */
function aun_hc_data() {
	return array(
		array( 'id'=>'KB-001','cat'=>'setup','applies'=>'all','topic'=>'keystone',
			'q_bn'=>'ছবি বাঁকা / সোজা হচ্ছে না (Keystone / Trapezoid)','q_en'=>'Picture looks crooked (Keystone / Trapezoid)',
			'search'=>'baka soja keystone trapezoid tilt crooked thik korbo',
			'a_bn'=>'ছবিটা বাঁকা দেখাচ্ছে কারণ প্রজেক্টরটি দেয়ালের সাথে সোজাসুজি নেই। (১) প্রজেক্টরটি দেয়ালের ঠিক সামনে, সমান উচ্চতায় ও সোজা করে বসান। (২) Settings-এ গিয়ে Keystone Correction (কিছু মডেলে এটি Trapezoid নামে থাকে) দিয়ে ছবিটি সোজা করুন।',
			'a_en'=>'The projector is not square to the wall. Place it straight and level, or open Settings and use Keystone Correction (called Trapezoid on some models) to straighten the image.' ),

		array( 'id'=>'KB-002','cat'=>'setup','applies'=>'all','topic'=>'focus',
			'q_bn'=>'ছবি ঝাপসা / ফোকাস ঠিক করা','q_en'=>'Image is blurry — adjusting focus',
			'search'=>'focus jhapsa blurry sharp focus button remote electronic',
			'a_bn'=>'আমাদের সব মডেলে ইলেকট্রনিক ফোকাস। অটো-ফোকাস মডেলে এটি নিজে নিজেই ঠিক হয়; ম্যানুয়াল ইলেকট্রিক ফোকাস মডেলে রিমোটের ফোকাস বাটন (+ / −) চেপে ছবিটি স্পষ্ট করুন। (কোনো মডেলেই হাতে ঘোরানোর ফোকাস রিং নেই।)',
			'a_en'=>'All our models use electronic focus. Auto-focus models adjust themselves; on manual electric-focus models, press the focus button (+ / −) on the remote until the image is sharp. No model has a manual focus dial/ring.' ),

		array( 'id'=>'KB-003','cat'=>'setup','applies'=>'all','topic'=>'',
			'q_bn'=>'ছবি বড় বা ছোট করব কীভাবে','q_en'=>'How to make the image bigger or smaller',
			'search'=>'boro choto size zoom bigger smaller distance dur',
			'a_bn'=>'ছবি বড়/ছোট হওয়া নির্ভর করে প্রজেক্টর থেকে দেয়ালের দূরত্বের উপর— বড় করতে চাইলে প্রজেক্টরটি দেয়াল থেকে আরও পিছনে সরান; ছোট করতে চাইলে কাছে আনুন বা Zoom ব্যবহার করুন। মনে রাখবেন, Zoom দিয়ে শুধু ছবি ছোট করা যায়, বড় করা যায় না। সঠিক দূরত্ব বের করতে আমাদের ওয়েবসাইটের Screen Size Calculator ব্যবহার করুন।',
			'a_en'=>'Image size depends on distance: move further back to enlarge, closer or Zoom to shrink. Zoom only shrinks. Use our Screen Size Calculator for the exact distance.' ),

		array( 'id'=>'KB-004','cat'=>'apps','applies'=>'uncertified','topic'=>'',
			'q_bn'=>'অ্যাপ স্টোরে অ্যাপ খুঁজে পাচ্ছি না','q_en'=>'Cannot find an app in the store',
			'search'=>'app store khuje pacchi na nai khuji',
			'a_bn'=>'এই মডেলে থার্ড-পার্টি অ্যাপ স্টোর থাকে, তাই কিছু অ্যাপ সেখানে নাও পাওয়া যেতে পারে। অ্যাপটির APK ফাইল দিয়ে ইনস্টল করা যায়। কোন অ্যাপটি দরকার বলুন, আমরা দেখিয়ে দিচ্ছি।',
			'a_en'=>'This model uses a third-party store, so some apps may be missing. Install them from their APK — tell us which app.' ),

		array( 'id'=>'KB-005','cat'=>'apps','applies'=>'all','topic'=>'',
			'q_bn'=>'ব্রাউজার / Chrome নেই (ইন্টারনেট ব্রাউজ)','q_en'=>'No browser / Chrome — how to browse the web',
			'search'=>'chrome browser internet web browse browser nai play store',
			'a_bn'=>'আমাদের কোনো প্রজেক্টরেই আগে থেকে কোনো ব্রাউজার বা Chrome দেওয়া থাকে না—এটা স্বাভাবিক। প্রজেক্টরে দেওয়া অ্যাপ স্টোর থেকে একটি ব্রাউজার (যেমন TV Bro) ইনস্টল করে নিন।',
			'a_en'=>'None of our projectors come with a browser or Chrome pre-installed — that is normal. Install a browser (e.g. TV Bro) from the built-in app store.',
			'a_bn_cert'=>'এই মডেলেও (Android TV) আগে থেকে কোনো ব্রাউজার বা Chrome দেওয়া থাকে না—এটা স্বাভাবিক। Google Play Store থেকে একটি ব্রাউজার (যেমন TV Bro) ইনস্টল করে নিন।',
			'a_en_cert'=>'Even on this model (Android TV) there is no pre-installed browser or Chrome — that is normal. Install a browser (e.g. TV Bro) from the Google Play Store.' ),

		array( 'id'=>'KB-006','cat'=>'apps','applies'=>'all','topic'=>'',
			'q_bn'=>'অ্যাপ ইনস্টল (APK / সাইডলোড)','q_en'=>'Install an app via APK (sideload)',
			'search'=>'apk install sideload file manager usb flash drive browser baire theke',
			'a_bn'=>'অ্যাপ ইনস্টল: কম্পিউটারে APK ডাউনলোড করে পেনড্রাইভে নিন → প্রজেক্টরে লাগিয়ে রিমোটের Input/Source বাটনে চেপে USB-তে যান → APK ফাইলটি খুঁজে ইনস্টল করুন। অথবা একটি ব্রাউজার ইনস্টল করে সরাসরি APK ডাউনলোড করুন। Unknown sources অনুমতি চাইলে Allow করুন।',
			'a_en'=>'Installing apps: download the APK on a computer, copy it to a flash drive, plug it in, press the remote Input/Source button, switch to USB, find the APK and install. Or install a browser and download the APK directly. Allow unknown sources if asked.',
			'a_bn_cert'=>'অ্যাপ ইনস্টল: Play Store থেকে একটি File Manager ইনস্টল করুন; কম্পিউটারে APK ডাউনলোড করে পেনড্রাইভে নিয়ে প্রজেক্টরে লাগান, তারপর File Manager দিয়ে পেনড্রাইভে গিয়ে APK ফাইলটি ইনস্টল করুন। অথবা ব্রাউজারে সরাসরি APK ডাউনলোড করুন। Unknown sources অনুমতি চাইলে Allow করুন।',
			'a_en_cert'=>'Installing apps: install a File Manager from the Play Store; download the APK on a computer, copy to a flash drive, plug it in, then open the flash drive in File Manager and install the APK. Or download the APK directly in a browser. Allow unknown sources if asked.' ),

		array( 'id'=>'KB-007','cat'=>'hardware','applies'=>'all','topic'=>'',
			'q_bn'=>'রিমোট কন্ট্রোল কাজ করছে না','q_en'=>'Remote control not working properly',
			'search'=>'remote kaj kore na battery point',
			'a_bn'=>'রিমোটটি দেয়াল/স্ক্রিনের দিকে না তাক করে প্রজেক্টরের দিকে (IR সেন্সরের দিকে) তাক করুন—বেশিরভাগ সময় এতেই কাজ করে। ব্যাটারি ঠিকভাবে লাগানো ও চার্জ আছে কিনা দেখুন। ব্লুটুথ রিমোট হলে আগে পেয়ার করে নিতে হবে।',
			'a_en'=>'Point the remote at the projector IR sensor, not the wall. Check the batteries; pair Bluetooth remotes first.' ),

		array( 'id'=>'KB-008','cat'=>'apps','applies'=>'uncertified','topic'=>'',
			'q_bn'=>'একটি অ্যাপ চলছে না','q_en'=>'A particular app does not work',
			'search'=>'app kaj kore na chole na',
			'a_bn'=>'কোন অ্যাপটির কথা বলছেন? এই মডেলে কিছু অ্যাপ ভিন্নভাবে ইনস্টল করতে হয়, বা কিছু অ্যাপ টিভি স্ক্রিনের জন্য তৈরি নয়। অ্যাপটির নাম বলুন, আমরা সঠিক সমাধান দিচ্ছি।',
			'a_en'=>'Tell us which app — some install differently on this model or are not built for TV screens.' ),

		array( 'id'=>'KB-009','cat'=>'apps','applies'=>'uncertified','topic'=>'',
			'q_bn'=>'Netflix চলছে না','q_en'=>'Netflix does not work',
			'search'=>'netflix account cholche na hd',
			'a_bn'=>'কয়েকটি বিষয় চেক করুন: (১) নিজের অফিশিয়াল Netflix অ্যাকাউন্ট ব্যবহার করুন—শেয়ার করা অ্যাকাউন্ট প্রায়ই ব্লক হয়। (২) Netflix অ্যাপটি ইনস্টল/সাইডলোড করুন; আমাদের ডিভাইসে Netflix HD কোয়ালিটিতে চলে। (৩) টাচ নয়, রিমোটের মাউস মোড দিয়ে চালাতে হবে।',
			'a_en'=>'Use an official personal account (shared accounts get blocked). Install/sideload the Netflix app — it runs in HD on our devices — and navigate with the remote mouse mode.' ),

		array( 'id'=>'KB-010','cat'=>'apps','applies'=>'uncertified','topic'=>'',
			'q_bn'=>'Netflix-এর ভেতরে রিমোট কাজ করে না','q_en'=>'Remote does not work inside Netflix',
			'search'=>'remote netflix mouse mode pointer',
			'a_bn'=>'Netflix-এর মতো অ্যাপের ভেতরে রিমোটটি মাউস মোডে নিন (পয়েন্টার বাটন) এবং কার্সারের মতো নাড়িয়ে অপশনে ট্যাপ করুন।',
			'a_en'=>'Switch the remote to mouse mode (pointer button) to navigate inside apps like Netflix.' ),

		array( 'id'=>'KB-011','cat'=>'casting','applies'=>'uncertified','topic'=>'',
			'q_bn'=>'Google Pixel মিরর / কাস্ট হচ্ছে না','q_en'=>'Cannot mirror a Google Pixel',
			'search'=>'pixel cast mirror google chromecast',
			'a_bn'=>'Google Pixel কাস্টের জন্য Chromecast ব্যবহার করে, যা এই (নন-সার্টিফায়েড) মডেলে বিল্ট-ইন নেই—তাই Pixel সরাসরি কাস্ট হয় না। সমাধান: USB-C-to-HDMI ক্যাবল দিয়ে তারের মাধ্যমে, অথবা HDMI পোর্টে একটি Chromecast ডংগল লাগিয়ে ব্যবহার করুন (অনেক নতুন Pixel-এ Miracast নেই)।',
			'a_en'=>'Google Pixel casts via Chromecast, which this (non-certified) model does not have built in — so a Pixel will not cast directly. Fix: use a USB-C-to-HDMI cable, or add a Chromecast dongle to the HDMI port (many newer Pixels have no Miracast).' ),

		array( 'id'=>'KB-012','cat'=>'casting','applies'=>'all','topic'=>'ios_mirror',
			'q_bn'=>'আইফোন মিরর হচ্ছে না','q_en'=>'Cannot mirror an iPhone',
			'search'=>'iphone ios airplay mirror cast airscreen aicast imirror wifi',
			'a_bn'=>'আইফোন মিরর করতে আইফোন ও প্রজেক্টর অবশ্যই একই Wi-Fi-তে থাকতে হবে (AirPlay)। প্রজেক্টরে আগে থেকেই দেওয়া iOS মিররিং অ্যাপটি (সাধারণত "AICast / iMirror" ধরনের নাম—মডেলভেদে আলাদা) খুলুন, তারপর আইফোনের Control Center → Screen Mirroring থেকে প্রজেক্টর সিলেক্ট করুন।',
			'a_en'=>'iPhone mirroring needs the iPhone and projector on the SAME Wi-Fi (AirPlay). Open the pre-installed iOS mirroring app on the projector (usually named like "AICast / iMirror" — varies by model), then use iPhone Control Center → Screen Mirroring and pick the projector.',
			'a_bn_cert'=>'আইফোন মিরর করতে আইফোন ও প্রজেক্টর অবশ্যই একই Wi-Fi-তে থাকতে হবে (AirPlay)। Play Store থেকে "AirScreen" অ্যাপটি ইনস্টল করে চালু রাখুন (ইনস্টল ফ্রি; কিছু ফিচার/লিমিট প্রিমিয়ামে), তারপর আইফোনের Control Center → Screen Mirroring থেকে প্রজেক্টর সিলেক্ট করুন।',
			'a_en_cert'=>'iPhone mirroring needs the iPhone and projector on the SAME Wi-Fi (AirPlay). Install "AirScreen" from the Play Store and open it (free to install; some features/limits are premium), then use iPhone Control Center → Screen Mirroring and pick the projector.' ),

		array( 'id'=>'KB-013','cat'=>'casting','applies'=>'all','topic'=>'android_mirror',
			'q_bn'=>'অ্যান্ড্রয়েড ফোন কাস্ট হচ্ছে না','q_en'=>'Cannot cast an Android phone',
			'search'=>'android cast mirror smart view wireless display mobile miracast airscreen wifi',
			'a_bn'=>'Android ফোন মিরর করতে সাধারণত একই Wi-Fi-তে থাকা জরুরি নয় (Miracast সরাসরি কানেক্ট হয়)। প্রজেক্টরে আগে থেকে দেওয়া মিররিং অ্যাপটি (সাধারণত "Cast / Miracast" নামে—মডেলভেদে আলাদা) খুলুন এবং ফোনের Smart View / Cast চালু করুন। ফোনে এই অপশন না থাকলে USB-C-to-HDMI ক্যাবল ব্যবহার করুন।',
			'a_en'=>'Android mirroring usually does NOT need the same Wi-Fi (Miracast connects directly). Open the pre-installed mirroring app on the projector (usually named like "Cast / Miracast" — varies by model) and turn on Smart View / Cast on the phone. If the phone lacks it, use a USB-C-to-HDMI cable.',
			'a_bn_cert'=>'Android ফোন মিরর: Google Pixel সরাসরি কাজ করে; অন্যান্য Android ফোনের জন্য Play Store থেকে "AirScreen" অ্যাপ ইনস্টল করুন, অথবা ফোনের Smart View / Cast ব্যবহার করুন। (Android মিররিং-এ সাধারণত একই Wi-Fi জরুরি নয়।)',
			'a_en_cert'=>'Android mirroring: a Google Pixel works directly; for other Android phones install "AirScreen" from the Play Store, or use the phone Smart View / Cast. (Android mirroring usually does not need the same Wi-Fi.)' ),

		array( 'id'=>'KB-014','cat'=>'hardware','applies'=>'all','topic'=>'',
			'q_bn'=>'প্রজেক্টর গরম হয়ে যায়','q_en'=>'The projector heats up a lot',
			'search'=>'gorom heat garam tap fan thanda',
			'a_bn'=>'এটা সম্পূর্ণ স্বাভাবিক—প্রজেক্টর কিছুটা গরম হয় এবং ভেতরে কুলিং ফ্যান থাকে যা তাপ নিয়ন্ত্রণ করে। শুধু পাশের/পেছনের ভেন্টগুলো খোলা রাখুন। (নিজে নিজে বন্ধ হলে বা পোড়া গন্ধ আসলে বন্ধ করে আমাদের জানান।)',
			'a_en'=>'Totally normal — projectors run warm and have cooling fans. Keep the vents clear.' ),

		array( 'id'=>'KB-015','cat'=>'hardware','applies'=>'all','topic'=>'maintenance',
			'q_bn'=>'স্ক্রিনে দাগ / ডেড পিক্সেল','q_en'=>'Spot on the screen / a dead pixel',
			'search'=>'dead pixel dust dag spot dhula',
			'a_bn'=>'চিন্তার কিছু নেই—সাধারণত এটি ডেড পিক্সেল নয়, বরং লেন্সে সামান্য ধুলা পড়ার কারণে দাগ দেখা যায়, যা স্বাভাবিক। U002 মডেল সম্পূর্ণ ডাস্টপ্রুফ; বেশিরভাগ মডেলে ধুলা ফিল্টার আছে; A32 সিরিজে ফিল্টার নেই বলে একটু বেশি পরিষ্কার রাখতে হয়। দাগ পরিষ্কারের পরও থাকলে আমাদের জানান।',
			'a_en'=>'Usually dust, not a dead pixel — normal. U002 is dustproof; A32 has no filter. Clean it; tell us if it persists.' ),

		array( 'id'=>'KB-016','cat'=>'hardware','applies'=>'all','topic'=>'factory_reset',
			'q_bn'=>'ফ্যাক্টরি রিসেট / হ্যাং করছে','q_en'=>'Factory reset / projector is stuck',
			'search'=>'reset factory hang slow restart',
			'a_bn'=>'Settings → System → Factory Reset-এ যান। মনে রাখবেন, এতে আপনার ইনস্টল করা অ্যাপ ও সেটিংস মুছে যাবে।',
			'a_en'=>'Go to Settings → System → Factory Reset (this erases your apps and settings).' ),

		array( 'id'=>'KB-017','cat'=>'setup','applies'=>'all','topic'=>'hdmi',
			'q_bn'=>'HDMI দিয়ে কানেক্ট (ল্যাপটপ / ম্যাকবুক / টিভি বক্স)','q_en'=>'Connect via HDMI (laptop / MacBook / TV box)',
			'search'=>'hdmi laptop tv box dish setup connect windows macbook mac duplicate extend mirror win p',
			'a_bn'=>'প্রথমে ডিভাইসটি প্রজেক্টরের HDMI পোর্টে লাগান এবং প্রজেক্টরের Source / Input মেনু থেকে HDMI সিলেক্ট করুন। এরপর কম্পিউটারটি প্রস্তুত করুন— • Windows ল্যাপটপ: কীবোর্ডে Windows key + P চাপুন, তারপর Duplicate (দুই স্ক্রিনে একই ছবি), Extend (দুই স্ক্রিন আলাদা) বা Second screen only (শুধু প্রজেক্টরে) বেছে নিন — Windows 7/8/10/11 সব ভার্সনে একই শর্টকাট। • MacBook: লাগালে সাধারণত নিজে নিজেই চলে আসে; না এলে System Settings → Displays-এ গিয়ে Mirror (একই ছবি) বা Extend বেছে নিন। নতুন MacBook-এ HDMI পোর্ট না থাকলে USB-C-to-HDMI অ্যাডাপ্টার লাগবে। • টিভি বক্স/ডিশ রিসিভার: কিছু করতে হয় না, শুধু Source ঠিক থাকলেই ছবি আসবে।',
			'a_en'=>'First plug the device into the projector HDMI port and pick HDMI in the projector Source/Input menu. Then prepare the computer — Windows laptop: press Windows key + P and choose Duplicate (same picture on both), Extend, or Second screen only (same shortcut on Windows 7/8/10/11). MacBook: it usually shows up automatically; if not, open System Settings → Displays and choose Mirror or Extend (newer MacBooks without an HDMI port need a USB-C-to-HDMI adapter). TV boxes / dish receivers need nothing extra — just the right Source.' ),

		array( 'id'=>'KB-018','cat'=>'setup','applies'=>'all','topic'=>'flash_drive',
			'q_bn'=>'পেনড্রাইভ থেকে মুভি চালানো','q_en'=>'Play movies from a USB / pen-drive',
			'search'=>'usb pendrive flash drive movie cholabo',
			'a_bn'=>'পেনড্রাইভটি USB পোর্টে লাগান, File Manager / Media Player খুলে আপনার ভিডিও/মুভি সিলেক্ট করুন।',
			'a_en'=>'Insert the pen-drive, open File Manager/Media Player, and select your video.' ),

		array( 'id'=>'KB-019','cat'=>'setup','applies'=>'all','topic'=>'change_language',
			'q_bn'=>'ভাষা পরিবর্তন (মেনু বাংলা / ইংরেজি)','q_en'=>'Change the menu language',
			'search'=>'language vasha bhasha bangla english change menu',
			'a_bn'=>'Settings → System → Language (বা Language & Input)-এ গিয়ে আপনার পছন্দের ভাষা সিলেক্ট করুন।',
			'a_en'=>'Go to Settings → System → Language (or Language & Input) and choose your preferred language.' ),

		array( 'id'=>'KB-020','cat'=>'setup','applies'=>'all','topic'=>'','manual'=>1,
			'q_bn'=>'ইউজার ম্যানুয়াল ডাউনলোড (User Manual)','q_en'=>'Download the user manual (PDF)',
			'search'=>'manual user manual pdf download guide boi nirdeshika ম্যানুয়াল নির্দেশিকা ডাউনলোড',
			'a_bn'=>'প্রতিটি মডেলের ইউজার ম্যানুয়াল আলাদা। নিচে আপনার মডেলের ম্যানুয়ালটি ডাউনলোড করুন—',
			'a_en'=>'Each model has its own user manual — download yours below.' ),
	);
}

/* ============================================================
 *  RENDER
 * ============================================================ */
function aun_hc_render( $atts = array() ) {
	$atts = shortcode_atts( array( 'cat' => 'all', 'model' => '' ), $atts, 'aun_help_center' );
	$catval = preg_replace( '/[^a-z]/', '', strtolower( $atts['cat'] ) );
	if ( $catval === '' ) $catval = 'all';

	$cats = array(
		'all'      => 'সব / All',
		'setup'    => 'সেটআপ / Setup',
		'apps'     => 'অ্যাপ / Apps',
		'casting'  => 'কাস্টিং / Casting',
		'hardware' => 'হার্ডওয়্যার / Hardware',
	);

	$models = aun_hc_models();

	// Dropdown options + model -> {certified, topics} map for the client.
	$opts = '<option value="">আপনার মডেল সিলেক্ট করুন / Select your model</option>';
	$js   = array();
	foreach ( $models as $slug => $m ) {
		$opts .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $m['label'] ) . '</option>';
		$js[ $slug ] = array( 'c' => (int) $m['certified'], 't' => (object) ( isset( $m['topics'] ) ? $m['topics'] : array() ) );
	}
	$models_json = wp_json_encode( $js );
	if ( ! is_string( $models_json ) || $models_json === '' ) $models_json = '{}'; // never break the script block

	// Category chips
	$chips = '';
	foreach ( $cats as $k => $label ) {
		$on = ( $k === $catval ) ? ' aun-hc-chip--on' : '';
		$chips .= '<button type="button" class="aun-hc-chip' . $on . '" data-cat="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</button>';
	}

	// FAQ items — each topic entry gets a hidden "watch" button placed INSIDE the answer.
	$items_html = '';
	foreach ( aun_hc_data() as $it ) {
		$kw  = strtolower( $it['q_en'] . ' ' . $it['q_bn'] . ' ' . $it['search'] );
		$btn = ( $it['topic'] !== '' )
			? '<button type="button" class="aun-hc-vid" style="display:none"><span class="aun-hc-vthumb"><span class="aun-hc-vplay"><i class="fa-solid fa-play"></i></span></span><span class="aun-hc-vlabel">📺 এই মডেলের ভিডিও দেখুন</span></button>'
			: '';
		$def_en = ! empty( $it['a_en'] ) ? '<p class="aun-hc-en">' . esc_html( $it['a_en'] ) . '</p>' : '';
		$ans = '<div class="aun-hc-ans aun-hc-ans--def"><p>' . esc_html( $it['a_bn'] ) . '</p>' . $def_en . '</div>';
		$hascert = '';
		if ( ! empty( $it['a_bn_cert'] ) ) {
			$cert_en = ! empty( $it['a_en_cert'] ) ? '<p class="aun-hc-en">' . esc_html( $it['a_en_cert'] ) . '</p>' : '';
			$ans .= '<div class="aun-hc-ans aun-hc-ans--cert" style="display:none"><p>' . esc_html( $it['a_bn_cert'] ) . '</p>' . $cert_en . '</div>';
			$hascert = ' data-hascert="1"';
		}
		// Per-model user-manual blocks (KB-020): render each model's own WP File Download category; JS shows the match.
		if ( ! empty( $it['manual'] ) ) {
			$ans .= '<div class="aun-hc-man-hint"></div>';
			foreach ( $models as $mslug => $mm ) {
				if ( empty( $mm['manual'] ) ) continue;
				$ans .= '<div class="aun-hc-man" data-model="' . esc_attr( $mslug ) . '" style="display:none">'
					. do_shortcode( '[wpfd_category id="' . (int) $mm['manual'] . '"]' ) . '</div>';
			}
		}
		$items_html .=
			'<details class="aun-faq" data-cat="' . esc_attr( $it['cat'] ) . '" data-applies="' . esc_attr( $it['applies'] ) . '" data-topic="' . esc_attr( $it['topic'] ) . '" data-k="' . esc_attr( $kw ) . '"' . $hascert . '>'
			. '<summary><span class="aun-hc-cat aun-hc-cat--' . esc_attr( $it['cat'] ) . '">' . esc_html( ucfirst( $it['cat'] ) ) . '</span>'
			. '<span class="aun-hc-q">' . esc_html( $it['q_bn'] ) . '</span>'
			. '<span class="aun-hc-qen">' . esc_html( $it['q_en'] ) . '</span></summary>'
			. '<div class="aun-hc-a">' . $ans . $btn . '</div>'
			. '</details>';
	}

	// Assets + lightbox printed once (WP Rocket guards per house standing rule)
	static $assets_done = false;
	$assets = '';
	if ( ! $assets_done ) {
		$assets_done = true;
		$assets = '
<style data-no-optimize="1" data-no-minify="1">
.aun-hc{max-width:880px;margin:0 auto;font-family:"Inter",sans-serif;}
.aun-hc-selwrap{position:relative;margin-bottom:18px;}
.aun-hc-selwrap::after{content:"\\25BC";position:absolute;right:16px;top:50%;transform:translateY(-50%);color:#0188fe;font-size:12px;pointer-events:none;}
.aun-hc-model{display:block;width:100%;box-sizing:border-box;height:auto;min-height:54px;padding:14px 42px 14px 16px;border:2px solid #0188fe;border-radius:12px;font-size:15px;line-height:1.6;font-weight:700;color:#0f172a;background:#fff;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none;}
.aun-hc-model:focus{outline:none;box-shadow:0 0 0 3px rgba(1,136,254,.18);}
.aun-hc-model.aun-hc-pulse{animation:aunhcpulse 1.8s ease-in-out infinite;}
@keyframes aunhcpulse{0%,100%{box-shadow:0 0 0 0 rgba(1,136,254,0);}50%{box-shadow:0 0 0 6px rgba(1,136,254,.14);}}
.aun-hc-search{width:100%;box-sizing:border-box;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;font-size:16px;margin-bottom:14px;outline:none;}
.aun-hc-search:focus{border-color:#0188fe;box-shadow:0 0 0 3px rgba(1,136,254,.12);}
.aun-hc-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;}
.aun-hc-chip{border:1px solid #e2e8f0;background:#fff;color:#556777;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;}
.aun-hc-chip:hover{border-color:#0188fe;color:#0188fe;}
.aun-hc-chip--on{background:#0188fe;border-color:#0188fe;color:#fff;}
.aun-hc-chip--on:hover{background:#0166c8;border-color:#0166c8;color:#fff;}
.aun-faq{background:#fff;border:1px solid #e2e8f0;border-left:3px solid #e2e8f0;border-radius:12px;margin-bottom:10px;box-shadow:0 2px 6px rgba(15,23,42,.04);overflow:hidden;}
.aun-faq[open]{border-left-color:#ffbc00;}
.aun-faq summary{list-style:none;cursor:pointer;padding:15px 18px;display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-weight:700;color:#0f172a;}
.aun-faq summary::-webkit-details-marker{display:none;}
.aun-faq summary::after{content:"\\002B";margin-left:auto;color:#0188fe;font-size:18px;font-weight:700;}
.aun-faq[open] summary::after{content:"\\2212";}
.aun-hc-q{font-size:15px;}
.aun-hc-qen{font-size:12.5px;color:#94a3b8;font-weight:600;width:100%;}
.aun-hc-cat{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px;background:rgba(1,136,254,.10);color:#0166c8;}
.aun-hc-cat--hardware{background:rgba(255,188,0,.14);color:#a86b00;}
.aun-hc-cat--casting{background:rgba(37,211,102,.14);color:#15803d;}
.aun-hc-cat--apps{background:rgba(99,102,241,.12);color:#4f46e5;}
.aun-hc-a{padding:0 18px 18px;color:#475569;line-height:1.75;font-size:14.5px;}
.aun-hc-a p{margin:0 0 10px;}
.aun-hc-en{font-size:12.5px;color:#94a3b8;border-top:1px dashed #e2e8f0;padding-top:8px;margin-top:4px;}
.aun-hc-vid{width:210px;max-width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:11px;overflow:hidden;cursor:pointer;padding:0;margin:4px 0 12px;text-align:left;box-shadow:0 2px 8px rgba(15,23,42,.06);transition:transform .18s,box-shadow .18s,border-color .18s;font-family:inherit;}
.aun-hc-vid:hover{border-color:#0188fe;box-shadow:0 8px 18px rgba(1,136,254,.18);transform:translateY(-2px);}
.aun-hc-vthumb{position:relative;display:block;width:100%;aspect-ratio:16/9;background:#0a1f38 center/cover no-repeat;}
.aun-hc-vthumb::after{content:"";position:absolute;inset:0;background:rgba(5,15,30,.14);}
.aun-hc-vplay{position:absolute;z-index:2;left:50%;top:50%;transform:translate(-50%,-50%);width:38px;height:38px;border-radius:50%;background:rgba(1,136,254,.94);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;box-shadow:0 4px 14px rgba(0,0,0,.3);}
.aun-hc-vplay i{margin-left:2px;}
.aun-hc-vlabel{display:block;padding:8px 11px 10px;font-size:12.5px;font-weight:700;color:#0c2a4a;line-height:1.3;}
.aun-hc-man-hint{background:rgba(1,136,254,.06);border:1px dashed rgba(1,136,254,.30);color:#0166c8;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin:2px 0 10px;}
.aun-hc-man{margin:2px 0 10px;}
.aun-hc-empty{display:none;text-align:center;color:#94a3b8;padding:28px;}
.aun-hc-help{margin-top:18px;text-align:center;background:rgb(246,248,251);border:1px solid #e2e8f0;border-radius:14px;padding:22px;}
.aun-hc-wa{display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;padding:11px 20px;border-radius:10px;font-weight:800;text-decoration:none;margin-top:10px;}
.aun-hc-wa:hover{background:#1eb955;color:#fff;}
.aun-hc-lb{position:fixed;inset:0;z-index:999999;display:none;align-items:center;justify-content:center;background:rgba(7,13,26,.92);padding:20px;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);}
.aun-hc-lb.open{display:flex;}
.aun-hc-lb-box{position:relative;width:min(900px,96vw,(100vh - 120px) * 16 / 9);}
.aun-hc-lb-frame{position:relative;width:100%;aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.6);}
.aun-hc-lb-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}
.aun-hc-lb-x{position:absolute;top:-54px;right:0;z-index:3;box-sizing:border-box !important;width:46px !important;height:46px !important;min-width:46px !important;min-height:46px !important;max-width:46px !important;padding:0 !important;margin:0 !important;border-radius:50% !important;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.34) !important;color:#fff;font-size:24px;font-weight:400;line-height:1 !important;cursor:pointer;display:flex !important;align-items:center;justify-content:center;-webkit-appearance:none;appearance:none;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);box-shadow:0 4px 16px rgba(0,0,0,.45);text-shadow:none;transition:background .2s,border-color .2s,transform .25s;}
.aun-hc-lb-x:hover{background:#0188fe;border-color:#0188fe !important;transform:rotate(90deg);}
.aun-hc-lb-x:focus-visible{outline:2px solid #4da8ff;outline-offset:3px;}
@media(max-width:560px){.aun-hc-lb-x{top:-46px;right:0;width:38px !important;height:38px !important;min-width:38px !important;min-height:38px !important;max-width:38px !important;font-size:20px;}}
</style>
<div class="aun-hc-lb"><div class="aun-hc-lb-box"><button class="aun-hc-lb-x" type="button" aria-label="Close">&times;</button><div class="aun-hc-lb-frame"></div></div></div>';
	}

	$html = $assets
		. '<div class="aun-hc" id="aun-hc" data-preset="' . esc_attr( $atts['model'] ) . '">'
		. '<div class="aun-hc-selwrap"><select class="aun-hc-model aun-hc-pulse" aria-label="Select your model">' . $opts . '</select></div>'
		. '<input type="text" class="aun-hc-search" placeholder="আপনার সমস্যা লিখুন... (যেমন: baka, focus, netflix, গরম)" aria-label="Search help">'
		. '<div class="aun-hc-chips">' . $chips . '</div>'
		. $items_html
		. '<div class="aun-hc-empty">কোনো ফলাফল পাওয়া যায়নি — নিচে সরাসরি WhatsApp করুন।</div>'
		. '<div class="aun-hc-help"><p style="margin:0 0 4px;font-weight:800;color:#0f172a;">আপনার উত্তর পাননি?</p>'
		. '<p style="margin:0;color:#64748b;font-size:14px;">সরাসরি আমাদের সাপোর্ট টিমের সাথে কথা বলুন।</p>'
		. '<a class="aun-hc-wa" href="https://wa.me/8801787698268" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp: +880 1787-698268</a></div>'
		. '</div>';

	$script = <<<JS
<script data-no-optimize="1" data-no-minify="1">
(function(){
var MODELS={$models_json};
var lb=document.querySelector(".aun-hc-lb");
function openV(id){ if(!lb||!id)return; lb.querySelector(".aun-hc-lb-frame").innerHTML='<iframe src="https://www.youtube-nocookie.com/embed/'+encodeURIComponent(id)+'?autoplay=1&rel=0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>'; lb.classList.add("open"); document.documentElement.style.overflow="hidden"; }
function closeV(){ if(!lb)return; lb.classList.remove("open"); lb.querySelector(".aun-hc-lb-frame").innerHTML=""; document.documentElement.style.overflow=""; }
if(lb && !lb.dataset.bound){ lb.dataset.bound="1";
	lb.addEventListener("click",function(e){ if(e.target===lb||e.target.closest(".aun-hc-lb-x")) closeV(); });
	document.addEventListener("keydown",function(e){ if(e.key==="Escape") closeV(); });
}
document.querySelectorAll(".aun-hc").forEach(function(root){
	if(root.dataset.hcReady) return; root.dataset.hcReady="1";
	var search=root.querySelector(".aun-hc-search");
	var chips=root.querySelectorAll(".aun-hc-chip");
	var items=root.querySelectorAll(".aun-faq");
	var empty=root.querySelector(".aun-hc-empty");
	var model=root.querySelector(".aun-hc-model");
	var cat="{$catval}";
	function info(){ return (model&&model.value&&MODELS[model.value])?MODELS[model.value]:null; }
	function videos(){
		var d=info(); var t=(d&&d.t)?d.t:{};
		items.forEach(function(it){
			var btn=it.querySelector(".aun-hc-vid"); if(!btn) return;
			var topic=it.getAttribute("data-topic");
			var id=(topic&&t[topic])?t[topic]:"";
			if(id){ btn.setAttribute("data-vid",id); var th=btn.querySelector(".aun-hc-vthumb"); if(th) th.style.backgroundImage="url(https://i.ytimg.com/vi/"+id+"/mqdefault.jpg)"; btn.style.display="inline-block"; } else { btn.removeAttribute("data-vid"); btn.style.display="none"; }
		});
		if(model&&model.value) model.classList.remove("aun-hc-pulse");
	}
	function variants(){
		var d=info(); var cert=d&&d.c;
		items.forEach(function(it){
			var cer=it.querySelector(".aun-hc-ans--cert"); if(!cer) return;
			var def=it.querySelector(".aun-hc-ans--def");
			if(cert){ if(def)def.style.display="none"; cer.style.display=""; }
			else{ if(def)def.style.display=""; cer.style.display="none"; }
		});
	}
	function manuals(){
		var m=model?model.value:"";
		items.forEach(function(it){
			var hint=it.querySelector(".aun-hc-man-hint");
			var boxes=it.querySelectorAll(".aun-hc-man");
			if(!hint&&!boxes.length) return;
			var found=false;
			boxes.forEach(function(b){ var on=(m&&b.getAttribute("data-model")===m); b.style.display=on?"":"none"; if(on)found=true; });
			if(hint){
				if(!m){ hint.style.display=""; hint.textContent="উপরের তালিকা থেকে আপনার মডেলটি সিলেক্ট করুন—এখানে সেই মডেলের ম্যানুয়াল দেখাবে।"; }
				else if(!found){ hint.style.display=""; hint.textContent="এই মডেলের ম্যানুয়াল শীঘ্রই যোগ করা হবে — প্রয়োজনে নিচের WhatsApp বাটনে নক করুন।"; }
				else{ hint.style.display="none"; }
			}
		});
	}
	function apply(){
		var q=(search.value||"").toLowerCase().trim();
		var d=info(); var cert=d&&d.c;
		var shown=0;
		items.forEach(function(it){
			var okCat=(cat==="all")||(it.getAttribute("data-cat")===cat);
			var okQ=!q||((it.getAttribute("data-k")||"").indexOf(q)>-1);
			var okApp=!(cert&&it.getAttribute("data-applies")==="uncertified");
			var show=okCat&&okQ&&okApp;
			it.style.display=show?"":"none";
			if(show)shown++;
		});
		if(empty)empty.style.display=shown?"none":"block";
	}
	root.addEventListener("click",function(e){
		var b=e.target.closest(".aun-hc-vid[data-vid]");
		if(b){ e.preventDefault(); openV(b.getAttribute("data-vid")); }
	});
	if(search)search.addEventListener("input",apply);
	if(model){ model.addEventListener("focus",function(){model.classList.remove("aun-hc-pulse");}); model.addEventListener("change",function(){videos();variants();manuals();apply();}); }
	chips.forEach(function(c){c.addEventListener("click",function(){chips.forEach(function(x){x.classList.remove("aun-hc-chip--on");});c.classList.add("aun-hc-chip--on");cat=c.getAttribute("data-cat");apply();});});
	var pre=root.getAttribute("data-preset")||"";
	try{var p=new URLSearchParams(window.location.search).get("aun_model");if(p)pre=p;}catch(e){}
	if(pre&&model){for(var i=0;i<model.options.length;i++){if(model.options[i].value===pre){model.value=pre;break;}}}
	videos();variants();manuals();apply();
});
})();
</script>
JS;

	return $html . $script;
}
add_shortcode( 'aun_help_center', 'aun_hc_render' );
