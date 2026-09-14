<?php
/**
 * Editable translations (English + বাংলা) for everything the customer sees on the
 * request form and the tracking page.
 *
 * Until now the Bangla was hardcoded — in the PHP markup (paired <span> tags) and in
 * the two JS files (I18N dicts). This class makes ALL of it admin-editable:
 *
 *   - PHP static strings are looked up by their English source text, so the form/
 *     tracking call sites stay as `self::t( 'English', 'fallback বাংলা' )` and simply
 *     resolve to any saved override.
 *   - JS strings + the customer-facing status labels are shipped to the browser as a
 *     JSON `data-i18n` attribute (no inline <script>, so WP Rocket stays happy) and
 *     merged over the scripts' built-in defaults.
 *
 * Nothing is seeded into the DB — an unsaved string falls back to the default below,
 * and "Restore defaults" simply deletes the option.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_I18N {

	const OPTION = 'aun_sp_i18n';

	/** The languages this plugin renders. Everything not Bangla falls back to English. */
	const LANGS = array( 'en', 'bn' );

	/**
	 * Which language to render RIGHT NOW — resolved from the site, not from a toggle.
	 *
	 * Order of precedence:
	 *   1. the `aun_sp_language` filter (lets a theme/shortcode force one)
	 *   2. TranslatePress's active language (its $TRP_LANGUAGE global is set on every
	 *      front-end request, e.g. 'bn_BD' on /bn/ URLs) — also covers its language
	 *      switcher, cookie and browser auto-detect, because we read the RESULT
	 *   3. WordPress's own determined locale (user profile / site language)
	 *
	 * Result is cached per request: it can be called dozens of times while rendering.
	 */
	public static function current_lang() {
		static $lang = null;
		if ( $lang !== null ) {
			return $lang;
		}

		$forced = (string) apply_filters( 'aun_sp_language', '' );
		if ( in_array( $forced, self::LANGS, true ) ) {
			return $lang = $forced;
		}

		$locale = '';
		// TranslatePress: the active language code for this request.
		if ( isset( $GLOBALS['TRP_LANGUAGE'] ) && is_string( $GLOBALS['TRP_LANGUAGE'] ) && $GLOBALS['TRP_LANGUAGE'] !== '' ) {
			$locale = $GLOBALS['TRP_LANGUAGE'];
		}
		if ( $locale === '' && function_exists( 'determine_locale' ) ) {
			$locale = (string) determine_locale();
		}
		if ( $locale === '' ) {
			$locale = (string) get_locale();
		}

		return $lang = self::locale_to_lang( $locale );
	}

	/** 'bn_BD' / 'bn' → 'bn'; anything else → 'en'. */
	public static function locale_to_lang( $locale ) {
		return ( strpos( strtolower( (string) $locale ), 'bn' ) === 0 ) ? 'bn' : 'en';
	}

	/**
	 * Language for an admin-ajax request. admin-ajax.php runs OUTSIDE TranslatePress's
	 * URL context (no /bn/ prefix), so the browser tells us which language the page it
	 * came from was rendered in. Validated against the whitelist, never trusted raw.
	 */
	public static function req_lang() {
		$posted = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
		if ( in_array( $posted, self::LANGS, true ) ) {
			return $posted;
		}
		return self::current_lang();
	}

	/**
	 * A server-side (AJAX) message in the caller's language. These used to be
	 * hardcoded English, so a Bangla visitor got English errors. Every one of them is
	 * editable in Spare Parts → Translations like the rest.
	 *
	 * @param string $slug  catalogue slug from the 'server' group
	 * @param array  $vars  optional {placeholders}
	 * @param string $lang  'en'|'bn'; defaults to the request language
	 */
	public static function msg( $slug, $vars = array(), $lang = null ) {
		$lang = in_array( $lang, self::LANGS, true ) ? $lang : self::req_lang();
		$pair = self::current( $slug );
		$text = isset( $pair[ $lang ] ) ? $pair[ $lang ] : $pair['en'];
		if ( ! empty( $vars ) ) {
			$text = AUN_SP_Messages::fill( $text, $vars );
		}
		return $text;
	}

	/**
	 * The whole catalogue, grouped for the admin screen.
	 * type:
	 *   php  → PHP static string, resolved by its English source (no 'k' needed)
	 *   ov   → customer-facing overall-status label  ('k' = status key)
	 *   it   → customer-facing per-part-status label ('k' = item status key)
	 *   jsf  → request-form JS micro-copy            ('k' = JS dict key)
	 *   jst  → tracking-page JS micro-copy           ('k' = JS dict key)
	 */
	public static function groups() {
		static $g = null;
		if ( $g !== null ) {
			return $g;
		}
		$g = array(

			'form' => array(
				'label' => 'Request form',
				'type'  => 'php',
				'strings' => array(
					'f_find_heading'   => array( 'en' => 'Find your purchase', 'bn' => 'আপনার ক্রয় খুঁজুন' ),
					'f_tab_phone'      => array( 'en' => 'Phone number', 'bn' => 'ফোন নম্বর' ),
					'f_tab_order'      => array( 'en' => 'Order number', 'bn' => 'অর্ডার নম্বর' ),
					'f_tab_serial'     => array( 'en' => 'Serial number', 'bn' => 'সিরিয়াল নম্বর' ),
					'f_find_btn'       => array( 'en' => 'Find', 'bn' => 'খুঁজুন' ),
					'f_chooser'        => array( 'en' => 'You have more than one purchase — which device needs the part?', 'bn' => 'আপনার একাধিক ক্রয় আছে — কোন ডিভাইসের জন্য পার্টস দরকার?' ),
					'f_found_head'     => array( 'en' => 'We found your purchase', 'bn' => 'আপনার ক্রয় পাওয়া গেছে' ),
					'f_lbl_model'      => array( 'en' => 'Device model', 'bn' => 'ডিভাইস মডেল' ),
					'f_lbl_purchase'   => array( 'en' => 'Purchase date', 'bn' => 'ক্রয়ের তারিখ' ),
					'f_lbl_warranty'   => array( 'en' => 'Warranty', 'bn' => 'ওয়ারেন্টি' ),
					'f_fork_h'         => array( 'en' => 'What would you like to do?', 'bn' => 'আপনি কী করতে চান?' ),
					'f_fork_parts_t'   => array( 'en' => 'I need a spare part', 'bn' => 'আমার একটি পার্টস দরকার' ),
					'f_fork_parts_s'   => array( 'en' => 'Keep your projector — we ship you the part.', 'bn' => 'প্রজেক্টর আপনার কাছেই থাকবে — আমরা পার্টস পাঠিয়ে দেব।' ),
					'f_fork_service_t' => array( 'en' => 'Send my projector for service', 'bn' => 'প্রজেক্টর সার্ভিসে পাঠান' ),
					'f_fork_service_s' => array( 'en' => 'Not sure what’s wrong? We diagnose and repair it.', 'bn' => 'সমস্যা বুঝতে পারছেন না? আমরা পরীক্ষা করে মেরামত করে দেব।' ),
					'f_escape'         => array( 'en' => 'Projector won’t turn on, or can’t find your order? Send it in for service →', 'bn' => 'প্রজেক্টর চালু হচ্ছে না, বা অর্ডার খুঁজে পাচ্ছেন না? সার্ভিসে পাঠান →' ),
					'f_delivery_legend'=> array( 'en' => 'Delivery details', 'bn' => 'ডেলিভারি তথ্য' ),
					'f_deliver_onfile' => array( 'en' => 'Deliver to your details on file', 'bn' => 'আপনার সংরক্ষিত তথ্যে ডেলিভারি হবে' ),
					'f_change'         => array( 'en' => 'Change', 'bn' => 'পরিবর্তন' ),
					'f_new_phone'      => array( 'en' => 'New phone number (leave blank to keep your current one)', 'bn' => 'নতুন ফোন নম্বর (বর্তমানটি রাখতে খালি রাখুন)' ),
					'f_new_address'    => array( 'en' => 'New delivery address (leave blank to keep your current one)', 'bn' => 'নতুন ডেলিভারি ঠিকানা (বর্তমানটি রাখতে খালি রাখুন)' ),
					'f_use_onfile'     => array( 'en' => 'Use my details on file instead', 'bn' => 'সংরক্ষিত তথ্য ব্যবহার করুন' ),
					'f_need_legend'    => array( 'en' => 'What do you need?', 'bn' => 'আপনার কী প্রয়োজন?' ),
					'f_required'       => array( 'en' => 'required', 'bn' => 'আবশ্যক' ),
					'f_optional'       => array( 'en' => 'optional', 'bn' => 'ঐচ্ছিক' ),
					'f_see_example'    => array( 'en' => 'See example', 'bn' => 'উদাহরণ দেখুন' ),
					'f_submit'         => array( 'en' => 'Submit request', 'bn' => 'অনুরোধ জমা দিন' ),
					'f_done_head'      => array( 'en' => 'Request received', 'bn' => 'অনুরোধ গৃহীত হয়েছে' ),
					'f_done_line'      => array( 'en' => 'Your reference number is {ref} — please keep it.', 'bn' => 'আপনার রেফারেন্স নম্বর {ref} — সংরক্ষণ করুন।' ),
					'f_done_hint'      => array( 'en' => "We'll verify the details and start sourcing your part. Track it any time with your reference number — parts not in stock usually take 3–4 weeks.", 'bn' => 'আমরা তথ্য যাচাই করে পার্টস সংগ্রহ শুরু করব। যেকোনো সময় রেফারেন্স নম্বর দিয়ে ট্র্যাক করতে পারবেন। স্টকে না থাকলে সাধারণত ৩–৪ সপ্তাহ লাগে।' ),
					'f_track_now'      => array( 'en' => 'Track this request', 'bn' => 'এই অনুরোধ ট্র্যাক করুন' ),
					// Short on purpose: it renders as a chip on the part's own row.
					'f_qty'            => array( 'en' => 'Qty', 'bn' => 'সংখ্যা' ),
				),
			),

			'track' => array(
				'label' => 'Tracking page',
				'type'  => 'php',
				'strings' => array(
					't_heading'   => array( 'en' => 'Track your spare-part request', 'bn' => 'আপনার পার্টস অনুরোধ ট্র্যাক করুন' ),
					't_tab_ref'   => array( 'en' => 'Reference (SP-…)', 'bn' => 'রেফারেন্স (SP-…)' ),
					't_track_btn' => array( 'en' => 'Track', 'bn' => 'ট্র্যাক' ),
				),
			),

			'status_overall' => array(
				'label' => 'Status labels — overall (what the customer sees)',
				'type'  => 'ov',
				'strings' => array(
					'ov_submitted'        => array( 'k' => 'submitted',        'en' => 'Submitted', 'bn' => 'জমা হয়েছে' ),
					'ov_in_progress'      => array( 'k' => 'in_progress',      'en' => 'In progress', 'bn' => 'প্রক্রিয়াধীন' ),
					'ov_quote_sent'       => array( 'k' => 'quote_sent',       'en' => 'Quote — awaiting your approval', 'bn' => 'কোটেশন — আপনার অনুমোদনের অপেক্ষায়' ),
					'ov_approved'         => array( 'k' => 'approved',         'en' => 'Approved — sourcing parts', 'bn' => 'অনুমোদিত — পার্টস সংগ্রহ চলছে' ),
					'ov_waiting_customer' => array( 'k' => 'waiting_customer', 'en' => 'Waiting on you', 'bn' => 'আপনার জন্য অপেক্ষমাণ' ),
					'ov_ready'            => array( 'k' => 'ready',            'en' => 'Ready to dispatch', 'bn' => 'পাঠানোর জন্য প্রস্তুত' ),
					'ov_closed'           => array( 'k' => 'closed',           'en' => 'Completed', 'bn' => 'সম্পন্ন' ),
					'ov_declined'         => array( 'k' => 'declined',         'en' => 'Quote declined', 'bn' => 'কোটেশন বাতিল' ),
					'ov_expired'          => array( 'k' => 'expired',          'en' => 'Quote expired', 'bn' => 'কোটেশনের মেয়াদ শেষ' ),
					'ov_rejected'         => array( 'k' => 'rejected',         'en' => 'Rejected', 'bn' => 'বাতিল' ),
				),
			),

			'status_item' => array(
				'label' => 'Status labels — per part',
				'type'  => 'it',
				'strings' => array(
					// Each label names WHERE the part is, because "Shipped" and "Dispatched"
					// are meaningless on their own — a customer cannot tell whether a part
					// shipped from the factory abroad or from our Dhaka office.
					'it_pending'     => array( 'k' => 'pending',     'en' => 'Pending review', 'bn' => 'যাচাই চলছে' ),
					'it_quoted'      => array( 'k' => 'quoted',      'en' => 'Price quoted', 'bn' => 'মূল্য জানানো হয়েছে' ),
					'it_applied'     => array( 'k' => 'applied',     'en' => 'Ordered from the factory', 'bn' => 'ফ্যাক্টরিতে অর্ডার করা হয়েছে' ),
					'it_at_factory'  => array( 'k' => 'at_factory',  'en' => 'Being prepared at the factory', 'bn' => 'ফ্যাক্টরিতে প্রস্তুত হচ্ছে' ),
					'it_shipped'     => array( 'k' => 'shipped',     'en' => 'Shipped from the factory — on its way to Bangladesh', 'bn' => 'ফ্যাক্টরি থেকে পাঠানো হয়েছে — বাংলাদেশের পথে' ),
					'it_arrived'     => array( 'k' => 'arrived',     'en' => 'Arrived at AUN, Dhaka', 'bn' => 'ঢাকায় AUN-এ পৌঁছেছে' ),
					'it_dispatched'  => array( 'k' => 'dispatched',  'en' => 'Out for delivery to you', 'bn' => 'আপনার ঠিকানায় পাঠানো হয়েছে' ),
					'it_delivered'   => array( 'k' => 'delivered',   'en' => 'Delivered to you', 'bn' => 'আপনি বুঝে পেয়েছেন' ),
					'it_unavailable' => array( 'k' => 'unavailable', 'en' => 'Unavailable', 'bn' => 'অনুপলব্ধ' ),
					'it_cancelled'   => array( 'k' => 'cancelled',   'en' => 'Not going ahead', 'bn' => 'এগোনো হচ্ছে না' ),
				),
			),

			// One plain-language line per part status, printed under the status chip on
			// the tracking page (and usable in SMS via {detail}). The label says WHAT the
			// status is; this says what it MEANS for the customer and what happens next.
			'status_item_help' => array(
				'label' => 'Status explanations — per part (shown to the customer under each status)',
				'type'  => 'ith',
				'strings' => array(
					'ith_pending'     => array( 'k' => 'pending',     'en' => 'We have your request and are checking the part and its availability.', 'bn' => 'আমরা আপনার অনুরোধ পেয়েছি এবং পার্টটি ও তার প্রাপ্যতা যাচাই করছি।' ),
					'ith_quoted'      => array( 'k' => 'quoted',      'en' => 'We have sent you the price. We order the part once you approve it.', 'bn' => 'আমরা আপনাকে মূল্য জানিয়েছি। আপনি অনুমোদন দিলেই আমরা পার্টটি অর্ডার করব।' ),
					'ith_applied'     => array( 'k' => 'applied',     'en' => 'We have placed the order with the factory and are waiting for them to confirm it.', 'bn' => 'আমরা ফ্যাক্টরিতে অর্ডার দিয়েছি এবং তাদের নিশ্চিতকরণের অপেক্ষায় আছি।' ),
					'ith_at_factory'  => array( 'k' => 'at_factory',  'en' => 'The factory has confirmed your part and is preparing it for shipment. This is usually the longest step.', 'bn' => 'ফ্যাক্টরি আপনার পার্টটি নিশ্চিত করেছে এবং পাঠানোর জন্য প্রস্তুত করছে। সাধারণত এই ধাপেই সবচেয়ে বেশি সময় লাগে।' ),
					'ith_shipped'     => array( 'k' => 'shipped',     'en' => 'Your part has left the factory and is in transit to Bangladesh. It has not reached us yet.', 'bn' => 'আপনার পার্টটি ফ্যাক্টরি থেকে রওনা হয়েছে এবং বাংলাদেশে আসছে। এটি এখনও আমাদের কাছে পৌঁছায়নি।' ),
					'ith_arrived'     => array( 'k' => 'arrived',     'en' => 'Your part has reached our Dhaka office. We will courier it to you next.', 'bn' => 'আপনার পার্টটি ঢাকায় আমাদের অফিসে পৌঁছেছে। এরপর আমরা কুরিয়ারে আপনার কাছে পাঠাব।' ),
					'ith_dispatched'  => array( 'k' => 'dispatched',  'en' => 'We have handed your part to the courier. You can track the parcel with the link below.', 'bn' => 'আমরা আপনার পার্টটি কুরিয়ারে দিয়ে দিয়েছি। নিচের লিংক দিয়ে পার্সেলটি ট্র্যাক করতে পারবেন।' ),
					'ith_delivered'   => array( 'k' => 'delivered',   'en' => 'The courier has delivered your part. Thank you for staying with AUN.', 'bn' => 'কুরিয়ার আপনার পার্টটি পৌঁছে দিয়েছে। AUN-এর সঙ্গে থাকার জন্য ধন্যবাদ।' ),
					'ith_unavailable' => array( 'k' => 'unavailable', 'en' => 'We are unable to supply this part. See the note above for the reason.', 'bn' => 'আমরা এই পার্টটি সরবরাহ করতে পারছি না। কারণ উপরে উল্লেখ করা হয়েছে।' ),
					'ith_cancelled'   => array( 'k' => 'cancelled',   'en' => 'This part was not ordered. Ask us any time if you would like a new price.', 'bn' => 'এই পার্টটি অর্ডার করা হয়নি। নতুন দাম জানতে চাইলে যেকোনো সময় আমাদের জানান।' ),
				),
			),

			'js_form' => array(
				'label' => 'Request form — messages & prompts',
				'type'  => 'jsf',
				'strings' => array(
					'jsf_searching'    => array( 'k' => 'searching',    'en' => 'Searching…', 'bn' => 'খোঁজা হচ্ছে…' ),
					'jsf_submitting'   => array( 'k' => 'submitting',   'en' => 'Submitting…', 'bn' => 'জমা হচ্ছে…' ),
					'jsf_enter_number' => array( 'k' => 'enter_number', 'en' => 'Please enter your number.', 'bn' => 'আপনার নম্বর লিখুন।' ),
					'jsf_choose_part'  => array( 'k' => 'choose_part',  'en' => 'Please choose at least one part.', 'bn' => 'অন্তত একটি পার্টস নির্বাচন করুন।' ),
					'jsf_need_photo'   => array( 'k' => 'need_photo',   'en' => 'Please add the required photo for:', 'bn' => 'এই অংশের জন্য ছবি দিন:' ),
					'jsf_too_large'    => array( 'k' => 'too_large',    'en' => 'That photo is over 15 MB — please choose a smaller one for:', 'bn' => 'ছবিটি ১৫ MB-এর বেশি — অনুগ্রহ করে ছোট ছবি দিন:' ),
					'jsf_net_err'      => array( 'k' => 'net_err',      'en' => 'Network error — please try again.', 'bn' => 'নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।' ),
					'jsf_not_found'    => array( 'k' => 'not_found',    'en' => "We couldn't find that purchase.", 'bn' => 'আপনার ক্রয় খুঁজে পাওয়া যায়নি।' ),
					'jsf_verified'     => array( 'k' => 'verified',     'en' => 'verified', 'bn' => 'যাচাইকৃত' ),
					'jsf_on_record'    => array( 'k' => 'on_record',    'en' => 'on record', 'bn' => 'রেকর্ডে আছে' ),
					'jsf_w_in'         => array( 'k' => 'w_in',         'en' => 'Yes — in warranty', 'bn' => 'হ্যাঁ — ওয়ারেন্টিতে আছে' ),
					'jsf_w_out'        => array( 'k' => 'w_out',        'en' => 'No — out of warranty', 'bn' => 'না — ওয়ারেন্টির বাইরে' ),
					'jsf_w_unknown'    => array( 'k' => 'w_unknown',    'en' => 'Purchase date unknown', 'bn' => 'ক্রয়ের তারিখ অজানা' ),
					// "You already have a request open" panel + duplicate confirmation.
					'jsf_open_one'     => array( 'k' => 'open_one',     'en' => 'You already have a request in progress:', 'bn' => 'আপনার একটি অনুরোধ ইতিমধ্যেই চলমান আছে:' ),
					'jsf_open_many'    => array( 'k' => 'open_many',    'en' => 'You already have {n} requests in progress:', 'bn' => 'আপনার ইতিমধ্যেই {n} টি অনুরোধ চলমান আছে:' ),
					'jsf_open_track'   => array( 'k' => 'open_track',   'en' => 'Track it', 'bn' => 'ট্র্যাক করুন' ),
					'jsf_dup_h'        => array( 'k' => 'dup_h',        'en' => 'You have already requested this part', 'bn' => 'আপনি এই পার্টসটি ইতিমধ্যেই চেয়েছেন' ),
					'jsf_dup_body'     => array( 'k' => 'dup_body',     'en' => 'We are still working on your earlier request. Sending it again will not make it faster — it only creates a second request we have to cancel.', 'bn' => 'আপনার আগের অনুরোধটি নিয়ে আমরা এখনো কাজ করছি। আবার পাঠালে দ্রুত হবে না — বরং একটি দ্বিতীয় অনুরোধ তৈরি হবে যা আমাদের বাতিল করতে হবে।' ),
					'jsf_dup_track'    => array( 'k' => 'dup_track',    'en' => 'Track my existing request', 'bn' => 'আমার চলমান অনুরোধ ট্র্যাক করুন' ),
					'jsf_dup_anyway'   => array( 'k' => 'dup_anyway',   'en' => 'This is a different problem — submit anyway', 'bn' => 'এটি ভিন্ন সমস্যা — তবুও জমা দিন' ),
					'jsf_dup_cancel'   => array( 'k' => 'dup_cancel',   'en' => 'Cancel', 'bn' => 'বাতিল' ),
					'jsf_lb_close'     => array( 'k' => 'lb_close',     'en' => 'Close', 'bn' => 'বন্ধ করুন' ),
					// Preview of the photo the customer just picked — tap it to see it large.
					'jsf_chosen_photo' => array( 'k' => 'chosen_photo', 'en' => 'Your photo', 'bn' => 'আপনার ছবি' ),
					'jsf_too_large_1'  => array( 'k' => 'too_large_1',  'en' => 'That photo is over 15 MB — please choose a smaller one.', 'bn' => 'ছবিটি ১৫ MB-এর বেশি — অনুগ্রহ করে ছোট ছবি দিন।' ),
				),
			),

			'js_track' => array(
				'label' => 'Tracking page — messages & prompts',
				'type'  => 'jst',
				'strings' => array(
					'jst_searching'    => array( 'k' => 'searching',    'en' => 'Searching…', 'bn' => 'খোঁজা হচ্ছে…' ),
					'jst_not_found'    => array( 'k' => 'not_found',    'en' => 'No request found. Check your SP- number, or contact us on WhatsApp.', 'bn' => 'কোনো অনুরোধ পাওয়া যায়নি। SP- নম্বরটি দেখুন, বা WhatsApp-এ যোগাযোগ করুন।' ),
					'jst_enter'        => array( 'k' => 'enter',        'en' => 'Please enter your reference or phone.', 'bn' => 'আপনার রেফারেন্স বা ফোন নম্বর লিখুন।' ),
					'jst_net_err'      => array( 'k' => 'net_err',      'en' => 'Network error — please try again.', 'bn' => 'নেটওয়ার্ক সমস্যা — আবার চেষ্টা করুন।' ),
					'jst_reupload_h'   => array( 'k' => 'reupload_h',   'en' => 'We need a clear, correct photo to continue:', 'bn' => 'এগিয়ে যেতে আমাদের একটি স্পষ্ট ও সঠিক ছবি দরকার:' ),
					'jst_match_example'=> array( 'k' => 'match_example','en' => 'Please take the photo just like this example:', 'bn' => 'অনুগ্রহ করে এই উদাহরণ ছবির মতো করে তুলুন:' ),
					'jst_track_parcel' => array( 'k' => 'track_parcel', 'en' => 'Track your parcel on Pathao', 'bn' => 'পাঠাও-এ আপনার পার্সেল ট্র্যাক করুন' ),
					'jst_send_photo'   => array( 'k' => 'send_photo',   'en' => 'Send photo', 'bn' => 'ছবি পাঠান' ),
					'jst_choose_first' => array( 'k' => 'choose_first', 'en' => 'Choose a photo first.', 'bn' => 'প্রথমে একটি ছবি নির্বাচন করুন।' ),
					'jst_uploading'    => array( 'k' => 'uploading',    'en' => 'Uploading…', 'bn' => 'আপলোড হচ্ছে…' ),
					'jst_upload_done'  => array( 'k' => 'upload_done',  'en' => 'Thanks — we received your new photo.', 'bn' => 'ধন্যবাদ — আমরা আপনার নতুন ছবি পেয়েছি।' ),
					'jst_eta'          => array( 'k' => 'eta',          'en' => 'ETA', 'bn' => 'আনুমানিক' ),
					'jst_quote_h'      => array( 'k' => 'quote_h',      'en' => 'Quote — please review', 'bn' => 'কোটেশন — অনুগ্রহ করে দেখুন' ),
					'jst_price_h'      => array( 'k' => 'price_h',      'en' => 'Price for your parts', 'bn' => 'আপনার পার্টসের মূল্য' ),
					'jst_payable_h'    => array( 'k' => 'payable_h',    'en' => 'Approved — amount payable', 'bn' => 'অনুমোদিত — প্রদেয় পরিমাণ' ),
					'jst_delivery'     => array( 'k' => 'delivery',     'en' => 'Delivery', 'bn' => 'ডেলিভারি চার্জ' ),
					'jst_pay_online'   => array( 'k' => 'pay_online',   'en' => 'Pay online now', 'bn' => 'এখনই অনলাইনে পেমেন্ট করুন' ),
					'jst_pay_wait'     => array( 'k' => 'pay_wait',     'en' => 'Opening payment…', 'bn' => 'পেমেন্ট পেজ খোলা হচ্ছে…' ),
					'jst_cod_default'  => array( 'k' => 'cod_default',  'en' => 'Prefer cash on delivery? Nothing to do — just pay when we hand over the parts.', 'bn' => 'ক্যাশ অন ডেলিভারি পছন্দ? কিছু করতে হবে না — পার্টস হাতে পাওয়ার সময় পেমেন্ট করবেন।' ),
					'jst_paid_msg'     => array( 'k' => 'paid_msg',     'en' => 'Payment received — thank you.', 'bn' => 'পেমেন্ট পাওয়া গেছে — ধন্যবাদ।' ),
					'jst_decide_h'     => array( 'k' => 'decide_h',     'en' => 'Do you want to go ahead?', 'bn' => 'আপনি কি এগিয়ে যেতে চান?' ),
					'jst_decide_sub'   => array( 'k' => 'decide_sub',   'en' => 'Approve and we will start sourcing your parts. Nothing is charged yet.', 'bn' => 'অনুমোদন করলে আমরা পার্টস সংগ্রহ শুরু করব। এখনই কোনো টাকা কাটা হবে না।' ),
					'jst_pay_h'        => array( 'k' => 'pay_h',        'en' => 'How would you like to pay?', 'bn' => 'আপনি কীভাবে পেমেন্ট করতে চান?' ),
					'jst_pay_sub'      => array( 'k' => 'pay_sub',      'en' => 'Pay online now, or simply pay cash when we hand the parts over.', 'bn' => 'এখনই অনলাইনে পেমেন্ট করুন, অথবা পার্টস হাতে পাওয়ার সময় ক্যাশ পরিশোধ করুন।' ),
					'jst_refunded_msg' => array( 'k' => 'refunded_msg', 'en' => 'Refunded ৳{amount} on {date}.', 'bn' => '{date} তারিখে ৳{amount} ফেরত দেওয়া হয়েছে।' ),
					'jst_refund_due'   => array( 'k' => 'refund_due_msg', 'en' => 'This request was cancelled after payment — your refund is being processed.', 'bn' => 'পেমেন্টের পর অনুরোধটি বাতিল হয়েছে — আপনার টাকা ফেরতের প্রক্রিয়া চলছে।' ),
					'jst_total'        => array( 'k' => 'total',        'en' => 'Total', 'bn' => 'মোট' ),
					'jst_approve'      => array( 'k' => 'approve',      'en' => 'Approve & proceed', 'bn' => 'অনুমোদন করুন' ),
					'jst_decline'      => array( 'k' => 'decline',      'en' => 'Decline', 'bn' => 'বাতিল করুন' ),
					'jst_approved_msg' => array( 'k' => 'approved_msg', 'en' => 'Thank you — your quote is approved. We will start sourcing your parts.', 'bn' => 'ধন্যবাদ — আপনার কোটেশন অনুমোদিত হয়েছে। আমরা পার্টস সংগ্রহ শুরু করব।' ),
					'jst_declined_msg' => array( 'k' => 'declined_msg', 'en' => 'Your quote has been declined. Contact us any time if you change your mind.', 'bn' => 'আপনার কোটেশন বাতিল করা হয়েছে। মত পরিবর্তন হলে যেকোনো সময় যোগাযোগ করুন।' ),
					'jst_history'      => array( 'k' => 'history',      'en' => 'Progress history', 'bn' => 'অগ্রগতির ইতিহাস' ),
					// Quote deadline + the expired/revive flow.
					'jst_reply_by'     => array( 'k' => 'reply_by',     'en' => 'Please reply by {date}', 'bn' => '{date} তারিখের মধ্যে জানান' ),
					'jst_days_left'    => array( 'k' => 'days_left',    'en' => '{n} day(s) left', 'bn' => 'আর {n} দিন বাকি' ),
					'jst_last_day'     => array( 'k' => 'last_day',     'en' => 'Last day to reply', 'bn' => 'জানানোর শেষ দিন' ),
					'jst_nothing_yet'  => array( 'k' => 'nothing_yet',  'en' => 'Nothing has been ordered yet — we start only after you approve.', 'bn' => 'এখনও কিছু অর্ডার করা হয়নি — আপনি অনুমোদন দিলেই আমরা শুরু করব।' ),
					'jst_expired_h'    => array( 'k' => 'expired_h',    'en' => 'This quote has expired', 'bn' => 'এই কোটেশনের মেয়াদ শেষ হয়েছে' ),
					'jst_expired_sub'  => array( 'k' => 'expired_sub',  'en' => 'We did not hear back, so nothing was ordered. Prices may have changed — if you still need the part, ask us for a new quote.', 'bn' => 'আপনার উত্তর না পাওয়ায় কিছু অর্ডার করা হয়নি। দাম পরিবর্তন হতে পারে — পার্টটি এখনও প্রয়োজন হলে নতুন কোটেশন চান।' ),
					'jst_revive'       => array( 'k' => 'revive',       'en' => 'I still want this part', 'bn' => 'আমার এখনও এই পার্টটি প্রয়োজন' ),
					'jst_revive_wait'  => array( 'k' => 'revive_wait',  'en' => 'Sending…', 'bn' => 'পাঠানো হচ্ছে…' ),
					'jst_declined_h'   => array( 'k' => 'declined_h',   'en' => 'You cancelled this quote', 'bn' => 'আপনি এই কোটেশনটি বাতিল করেছেন' ),
					'jst_declined_sub' => array( 'k' => 'declined_sub', 'en' => 'Nothing was ordered and nothing is owed. If you tapped Decline by mistake, or you have changed your mind, ask us for a new quote.', 'bn' => 'কিছু অর্ডার করা হয়নি এবং কোনো টাকাও বাকি নেই। ভুল করে বাতিল করে থাকলে, বা মত পরিবর্তন হলে, আমাদের কাছে নতুন কোটেশন চান।' ),
					'jst_revive_decl'  => array( 'k' => 'revive_declined', 'en' => 'Changed my mind — quote me again', 'bn' => 'মত পরিবর্তন হয়েছে — আবার কোটেশন দিন' ),
					'jst_lb_close'     => array( 'k' => 'lb_close',     'en' => 'Close', 'bn' => 'বন্ধ করুন' ),
					// The customer's own photos — shown exactly like the "see example" thumbnail.
					'jst_your_photo'   => array( 'k' => 'your_photo',   'en' => 'Your photo', 'bn' => 'আপনার ছবি' ),
					'jst_resent_photo' => array( 'k' => 'resent_photo', 'en' => 'The new photo you sent us', 'bn' => 'আপনার পাঠানো নতুন ছবি' ),
					'jst_too_large'    => array( 'k' => 'too_large',    'en' => 'That photo is over 15 MB — please choose a smaller one.', 'bn' => 'ছবিটি ১৫ MB-এর বেশি — অনুগ্রহ করে ছোট ছবি দিন।' ),
				),
			),

			// Replies sent from the server (admin-ajax). These used to be hardcoded
			// English — a Bangla visitor saw English errors. Resolved per request via
			// AUN_SP_I18N::msg(); {part} / {n} are filled in at send time.
			'server' => array(
				'label' => 'Error & confirmation messages (from the server)',
				'type'  => 'srv',
				'strings' => array(
					'srv_enter_query'    => array( 'en' => 'Please enter your phone, order or serial number.', 'bn' => 'আপনার ফোন, অর্ডার বা সিরিয়াল নম্বর লিখুন।' ),
					'srv_not_found'      => array( 'en' => "We couldn't find that purchase. Try your order number, or contact us on WhatsApp.", 'bn' => 'সেই ক্রয়টি খুঁজে পাওয়া যায়নি। অর্ডার নম্বর দিয়ে চেষ্টা করুন, বা WhatsApp-এ যোগাযোগ করুন।' ),
					'srv_lookup_again'   => array( 'en' => 'Please look up your purchase again before submitting.', 'bn' => 'জমা দেওয়ার আগে আপনার ক্রয়টি আবার খুঁজুন।' ),
					'srv_choose_part'    => array( 'en' => 'Please choose at least one part.', 'bn' => 'অন্তত একটি পার্টস নির্বাচন করুন।' ),
					'srv_photo_required' => array( 'en' => 'A photo is required for: {part}', 'bn' => 'এই পার্টসের জন্য ছবি আবশ্যক: {part}' ),
					'srv_photo_failed'   => array( 'en' => 'The photo for "{part}" failed to upload — please try again with a smaller photo.', 'bn' => '"{part}" এর ছবি আপলোড হয়নি — অনুগ্রহ করে ছোট ছবি দিয়ে আবার চেষ্টা করুন।' ),
					'srv_photo_large'    => array( 'en' => 'The photo for "{part}" is too large (max 15 MB). Please choose a smaller one.', 'bn' => '"{part}" এর ছবিটি অনেক বড় (সর্বোচ্চ ১৫ MB)। ছোট একটি ছবি দিন।' ),
					'srv_photo_type'     => array( 'en' => 'The photo for "{part}" must be a JPG, PNG or WebP image.', 'bn' => '"{part}" এর ছবিটি JPG, PNG বা WebP ফরম্যাটে হতে হবে।' ),
					'srv_bad_phone'      => array( 'en' => 'Please enter a valid Bangladeshi mobile number (01XXXXXXXXX).', 'bn' => 'সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন (০১XXXXXXXXX)।' ),
					'srv_need_contact'   => array( 'en' => 'We still need a phone number and a delivery address to ship your part.', 'bn' => 'পার্টস পাঠাতে আমাদের একটি ফোন নম্বর ও ডেলিভারি ঠিকানা প্রয়োজন।' ),
					'srv_save_error'     => array( 'en' => 'Something went wrong saving your request. Please try again.', 'bn' => 'আপনার অনুরোধ সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।' ),
					'srv_rate_limited'   => array( 'en' => 'Too many tries. Please wait a moment.', 'bn' => 'অনেকবার চেষ্টা করা হয়েছে। কিছুক্ষণ অপেক্ষা করুন।' ),
					'srv_session'        => array( 'en' => 'Your session expired — please refresh the page.', 'bn' => 'আপনার সেশন শেষ হয়ে গেছে — পেজটি রিফ্রেশ করুন।' ),
					'srv_track_enter'    => array( 'en' => 'Please enter your reference or phone number.', 'bn' => 'আপনার রেফারেন্স বা ফোন নম্বর লিখুন।' ),
					'srv_track_none'     => array( 'en' => 'No request found. Check your SP- number, or contact us on WhatsApp.', 'bn' => 'কোনো অনুরোধ পাওয়া যায়নি। SP- নম্বরটি দেখুন, বা WhatsApp-এ যোগাযোগ করুন।' ),
					'srv_ru_choose'      => array( 'en' => 'Please choose a photo.', 'bn' => 'একটি ছবি নির্বাচন করুন।' ),
					'srv_ru_notfound'    => array( 'en' => 'Request not found.', 'bn' => 'অনুরোধ খুঁজে পাওয়া যায়নি।' ),
					'srv_ru_large'       => array( 'en' => 'That photo is too large (max 15 MB).', 'bn' => 'ছবিটি অনেক বড় (সর্বোচ্চ ১৫ MB)।' ),
					'srv_ru_notwaiting'  => array( 'en' => "We're not expecting a photo on this request right now. If you still need to send one, please message us on WhatsApp.", 'bn' => 'এই অনুরোধের জন্য এখন কোনো ছবি প্রয়োজন নেই। তবুও পাঠাতে চাইলে WhatsApp-এ মেসেজ করুন।' ),
					'srv_ru_badtype'     => array( 'en' => "That file type isn't allowed. Please upload a photo (JPG/PNG).", 'bn' => 'এই ফাইল ফরম্যাট গ্রহণযোগ্য নয়। একটি ছবি (JPG/PNG) আপলোড করুন।' ),
					'srv_ru_ok'          => array( 'en' => "Thanks — we've received your new photo and will review it.", 'bn' => 'ধন্যবাদ — আমরা আপনার নতুন ছবি পেয়েছি এবং যাচাই করব।' ),
					'srv_dup_short'      => array( 'en' => 'You already have an open request for this part.', 'bn' => 'এই পার্টসের জন্য আপনার একটি অনুরোধ ইতিমধ্যেই চলমান আছে।' ),
					'srv_pay_unavailable' => array( 'en' => 'Online payment isn\'t available for this request. You can still pay cash on delivery.', 'bn' => 'এই অনুরোধের জন্য অনলাইন পেমেন্ট এখন সম্ভব নয়। আপনি ক্যাশ অন ডেলিভারিতে পরিশোধ করতে পারবেন।' ),
					'srv_already_paid'   => array( 'en' => 'This request is already paid — thank you.', 'bn' => 'এই অনুরোধের পেমেন্ট ইতিমধ্যেই সম্পন্ন হয়েছে — ধন্যবাদ।' ),
					'srv_quote_gone'     => array( 'en' => 'This quote is no longer awaiting approval.', 'bn' => 'এই কোটেশনটি আর অনুমোদনের অপেক্ষায় নেই।' ),
					'srv_invalid'        => array( 'en' => 'Invalid request.', 'bn' => 'অনুরোধটি সঠিক নয়।' ),
					// Progress-history lines for the two money events. The stored note is
					// internal ("Online payment order #9275 refreshed — now ৳16.00") and
					// must never be shown to the customer.
					'tl_payment'         => array( 'en' => 'Payment received — ৳{amount}. Thank you.', 'bn' => 'পেমেন্ট পাওয়া গেছে — ৳{amount}। ধন্যবাদ।' ),
					'tl_refund'          => array( 'en' => 'Refund of ৳{amount} issued to you.', 'bn' => 'আপনাকে ৳{amount} ফেরত দেওয়া হয়েছে।' ),
					// Used when the figure isn't available (e.g. the order row is gone).
					// Better a sentence with no number than "৳." with nothing after it.
					'tl_payment_plain'   => array( 'en' => 'Payment received. Thank you.', 'bn' => 'পেমেন্ট পাওয়া গেছে। ধন্যবাদ।' ),
					'tl_refund_plain'    => array( 'en' => 'A refund has been issued to you.', 'bn' => 'আপনাকে টাকা ফেরত দেওয়া হয়েছে।' ),
					'srv_revive_ok'      => array( 'en' => 'Thank you — we will check the price again and send you a new quote shortly.', 'bn' => 'ধন্যবাদ — আমরা আবার দাম যাচাই করে শীঘ্রই নতুন কোটেশন পাঠাব।' ),
					'srv_revive_no'      => array( 'en' => 'This request is already active — no need to ask again.', 'bn' => 'এই অনুরোধটি ইতিমধ্যেই সক্রিয় — আবার অনুরোধ করার দরকার নেই।' ),
				),
			),

		);
		return $g;
	}

	/**
	 * Customer-facing label for an overall-status key, in the request language.
	 * (The tracking page localises statuses in JS; server-rendered lists — like the
	 * "already in progress" panel — need this.)
	 */
	public static function ov_label( $key, $lang = null ) {
		$lang = in_array( $lang, self::LANGS, true ) ? $lang : self::req_lang();
		foreach ( self::groups()['status_overall']['strings'] as $slug => $def ) {
			if ( isset( $def['k'] ) && $def['k'] === $key ) {
				$pair = self::current( $slug );
				return isset( $pair[ $lang ] ) ? $pair[ $lang ] : $pair['en'];
			}
		}
		$fallback = AUN_SP_Requests::overall_statuses();
		return isset( $fallback[ $key ] ) ? $fallback[ $key ] : $key;
	}

	/** Flat list of every slug, in catalogue order. */
	public static function all_slugs() {
		$out = array();
		foreach ( self::groups() as $g ) {
			foreach ( $g['strings'] as $slug => $d ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/** The default definition for a slug (en/bn[/k]), or null. */
	public static function def( $slug ) {
		foreach ( self::groups() as $g ) {
			if ( isset( $g['strings'][ $slug ] ) ) {
				return $g['strings'][ $slug ];
			}
		}
		return null;
	}

	/**
	 * The en/bn actually rendered to a customer.
	 *  - never edited        → the built-in default (English + Bangla)
	 *  - edited, Bangla blank → English (admin deliberately removed the translation)
	 *  - edited               → the saved values
	 * English never renders blank — it falls back to the default.
	 */
	public static function current( $slug ) {
		$d = self::def( $slug );
		if ( ! $d ) {
			return array( 'en' => '', 'bn' => '' );
		}
		$saved = get_option( self::OPTION, array() );
		$en = $d['en'];
		$bn = ( $d['bn'] !== '' ) ? $d['bn'] : $d['en'];

		if ( isset( $saved[ $slug ] ) && is_array( $saved[ $slug ] ) ) {
			$row = $saved[ $slug ];
			$en  = ( isset( $row['en'] ) && $row['en'] !== '' ) ? $row['en'] : $d['en'];
			$bn  = ( isset( $row['bn'] ) && $row['bn'] !== '' ) ? $row['bn'] : $en; // emptied → English
		}
		return array( 'en' => $en, 'bn' => $bn );
	}

	/**
	 * Raw stored values for the admin editor — what the admin actually typed (so a
	 * cleared Bangla box stays visibly empty), falling back to defaults when unsaved.
	 */
	public static function raw( $slug ) {
		$d = self::def( $slug );
		if ( ! $d ) {
			return array( 'en' => '', 'bn' => '' );
		}
		$saved = get_option( self::OPTION, array() );
		if ( isset( $saved[ $slug ] ) && is_array( $saved[ $slug ] ) ) {
			return array(
				'en' => isset( $saved[ $slug ]['en'] ) ? $saved[ $slug ]['en'] : $d['en'],
				'bn' => isset( $saved[ $slug ]['bn'] ) ? $saved[ $slug ]['bn'] : $d['bn'],
			);
		}
		return array( 'en' => $d['en'], 'bn' => $d['bn'] );
	}

	/** English-source → slug index for the PHP-static groups. */
	private static function php_index() {
		static $idx = null;
		if ( $idx !== null ) {
			return $idx;
		}
		$idx = array();
		foreach ( self::groups() as $g ) {
			if ( $g['type'] !== 'php' ) {
				continue;
			}
			foreach ( $g['strings'] as $slug => $d ) {
				$idx[ $d['en'] ] = $slug;
			}
		}
		return $idx;
	}

	/**
	 * Resolve a PHP-static string by its English source. The call sites still pass the
	 * inline Bangla as a fallback, so an English string we don't (yet) track still works.
	 */
	public static function php_pair( $en, $bn ) {
		$idx = self::php_index();
		if ( isset( $idx[ $en ] ) ) {
			return self::current( $idx[ $en ] );
		}
		return array( 'en' => $en, 'bn' => ( $bn !== '' ? $bn : $en ) );
	}

	/** Build a {en:{}, bn:{}} dict from a group's strings (keyed by its JS 'k'). */
	private static function js_dict( $group_key ) {
		$en = array();
		$bn = array();
		$grp = self::groups()[ $group_key ]['strings'];
		foreach ( $grp as $slug => $d ) {
			$c = self::current( $slug );
			$k = isset( $d['k'] ) ? $d['k'] : $slug;
			$en[ $k ] = $c['en'];
			$bn[ $k ] = $c['bn'];
		}
		return array( 'en' => $en, 'bn' => $bn );
	}

	/** Payload for sp-form.js (flat keys). */
	public static function js_form_payload() {
		return self::js_dict( 'js_form' );
	}

	/** Payload for sp-track.js (flat keys + nested ov{} / it{} / ith{} status maps). */
	public static function js_track_payload() {
		$base = self::js_dict( 'js_track' );
		$ov   = self::js_dict( 'status_overall' );
		$it   = self::js_dict( 'status_item' );
		$ith  = self::js_dict( 'status_item_help' );
		$base['en']['ov'] = $ov['en'];
		$base['bn']['ov'] = $ov['bn'];
		$base['en']['it'] = $it['en'];
		$base['bn']['it'] = $it['bn'];
		$base['en']['ith'] = $ith['en'];
		$base['bn']['ith'] = $ith['bn'];
		return $base;
	}

	/**
	 * The CUSTOMER-facing label / explanation for a per-part status key.
	 *
	 * The admin dropdown has its own shorter working labels (AUN_SP_Requests::
	 * item_statuses()); those must never be what the customer is told, both because
	 * they are internal shorthand and because they are English-only. SMS and any
	 * server-rendered part status go through here instead.
	 */
	public static function it_label( $key, $lang = null ) {
		return self::status_string( 'it_' . $key, $key, $lang );
	}

	/** The one-line explanation of a per-part status ('' if the slug is unknown). */
	public static function it_help( $key, $lang = null ) {
		return self::status_string( 'ith_' . $key, '', $lang );
	}

	private static function status_string( $slug, $fallback, $lang ) {
		if ( ! self::def( $slug ) ) {
			return $fallback;
		}
		$lang = in_array( $lang, self::LANGS, true ) ? $lang : self::current_lang();
		$pair = self::current( $slug );
		return isset( $pair[ $lang ] ) ? $pair[ $lang ] : $pair['en'];
	}
}
