<?php
/**
 * The AUN Rewards landing page — where an invite link lands, and the
 * programme's guide for everyone else.
 *
 * Shortcode `[aun_refer]`, placed on a page the plugin creates once (slug
 * `refer`). The invite link the app shares is `/refer/?code=K7PQ2M`, so the
 * friend — the person who most needs the explanation, and the one who does not
 * have the app yet — lands on "you've been invited, here is your code, here is
 * what to do" instead of the homepage.
 *
 * Built on the site's INFO-PAGE template (aun-design-system.md §6): pill-badge
 * hero → the tool (the invite code) → "how it works" icon cards → a hand-rolled
 * <details> FAQ → a talk-to-a-human block. Inline SVG icons rather than Font
 * Awesome: the site's icon font is a subset, and an icon outside it renders blank.
 *
 * Every number on the page is read from the programme's own settings, so the
 * guide can never promise terms the checkout does not honour.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Rewards_Page {

	const OPTION_PAGE    = 'aun_app_refer_page_id';
	const OPTION_CREATED = 'aun_app_refer_page_created';
	const SHORTCODE      = 'aun_refer';

	/** Where "Get the app" goes. Clicks go to the app page; only a QR goes to the APK. */
	public static function app_url() {
		return (string) apply_filters( 'aun_app_refer_app_url', home_url( '/aun-care-app/' ) );
	}

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
	}

	/**
	 * Create the page ONCE.
	 *
	 * Once only, on purpose: if the owner deletes or unpublishes it, that is a
	 * decision, and an update quietly putting it back would be overruling it.
	 * Without the page the app simply keeps sharing the website link.
	 */
	public static function ensure_page() {
		if ( get_option( self::OPTION_CREATED ) ) {
			return;
		}
		// Never before permalinks exist — see aun_app_api_make_refer_page().
		if ( empty( $GLOBALS['wp_rewrite'] ) ) {
			update_option( 'aun_app_refer_page_pending', 1, false );
			return;
		}
		$existing = get_page_by_path( 'refer' );
		if ( $existing && false !== strpos( (string) $existing->post_content, '[' . self::SHORTCODE ) ) {
			update_option( self::OPTION_PAGE, (int) $existing->ID );
			update_option( self::OPTION_CREATED, 1 );
			return;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'AUN Rewards — Invite & Earn',
			// wp_insert_post() makes the slug unique itself if "refer" is taken.
			'post_name'    => 'refer',
			'post_content' => '[' . self::SHORTCODE . ']',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::OPTION_PAGE, (int) $id );
		}
		update_option( self::OPTION_CREATED, 1 );
	}

	/* --------------------------------------------------------------------- *
	 * Language
	 * --------------------------------------------------------------------- */

	/** 'bn' on the Bangla version of the site (TranslatePress), else 'en'. */
	public static function lang() {
		$l = '';
		if ( isset( $GLOBALS['TRP_LANGUAGE'] ) && is_string( $GLOBALS['TRP_LANGUAGE'] ) ) {
			$l = $GLOBALS['TRP_LANGUAGE'];
		} elseif ( function_exists( 'determine_locale' ) ) {
			$l = (string) determine_locale();
		}
		return 0 === stripos( $l, 'bn' ) ? 'bn' : 'en';
	}

	/** One string, in the page's language, with {placeholders} filled. */
	private static function t( $key, $vars = array() ) {
		static $s = null;
		if ( null === $s ) {
			$s = self::strings();
		}
		$pair = $s[ $key ] ?? array( 'en' => $key, 'bn' => $key );
		$text = $pair[ self::lang() ] ?? $pair['en'];
		foreach ( (array) $vars as $k => $v ) {
			$text = str_replace( '{' . $k . '}', (string) $v, $text );
		}
		return $text;
	}

	private static function strings() {
		return array(
			'pill'            => array( 'en' => 'AUN Rewards', 'bn' => 'AUN রিওয়ার্ড' ),
			'h_named'         => array( 'en' => '{name} invited you — {d} off your first AUN projector', 'bn' => '{name} আপনাকে আমন্ত্রণ জানিয়েছেন — প্রথম AUN প্রজেক্টরে {d} ছাড়' ),
			'h_invited'       => array( 'en' => 'You\'ve been invited — {d} off your first AUN projector', 'bn' => 'আপনাকে আমন্ত্রণ জানানো হয়েছে — প্রথম AUN প্রজেক্টরে {d} ছাড়' ),
			'h_plain'         => array( 'en' => 'Invite friends. Save on your next projector.', 'bn' => 'বন্ধুকে আমন্ত্রণ জানান, পরের প্রজেক্টরে সাশ্রয় করুন।' ),
			'sub_invited'     => array( 'en' => 'Get the AUN Care app, enter this code, and your discount is ready in seconds.', 'bn' => 'AUN Care অ্যাপ নিন, এই কোডটি দিন — কয়েক সেকেন্ডেই আপনার ছাড় তৈরি।' ),
			'sub_plain'       => array( 'en' => 'Your friend gets {d} off their first projector, and you get {r} when it\'s delivered.', 'bn' => 'আপনার বন্ধু প্রথম প্রজেক্টরে {d} ছাড় পাবেন, আর ডেলিভারি হলে আপনি পাবেন {r}।' ),
			'code_label'      => array( 'en' => 'Your invite code', 'bn' => 'আপনার আমন্ত্রণ কোড' ),
			'copy'            => array( 'en' => 'Copy', 'bn' => 'কপি করুন' ),
			'copied'          => array( 'en' => 'Copied', 'bn' => 'কপি হয়েছে' ),
			'get_app'         => array( 'en' => 'Get the AUN Care app', 'bn' => 'AUN Care অ্যাপ নিন' ),
			'code_where'      => array( 'en' => 'In the app: Rewards → I have a code → enter this code.', 'bn' => 'অ্যাপে: রিওয়ার্ড → আমার কাছে কোড আছে → কোডটি দিন।' ),
			'bad_code'        => array( 'en' => 'We couldn\'t find that invite code. Please check it with your friend — the steps below still apply.', 'bn' => 'এই আমন্ত্রণ কোডটি খুঁজে পাওয়া যায়নি। বন্ধুর কাছ থেকে কোডটি আবার মিলিয়ে নিন — নিচের ধাপগুলো একই থাকবে।' ),
			'off'             => array( 'en' => 'Our invite programme is paused right now. Please check back soon.', 'bn' => 'আমাদের আমন্ত্রণ প্রোগ্রাম এখন বন্ধ আছে। কিছুদিন পরে আবার দেখুন।' ),

			'friend_h'        => array( 'en' => 'Got a code? Here\'s how to use it', 'bn' => 'কোড পেয়েছেন? যেভাবে ব্যবহার করবেন' ),
			'f1_t'            => array( 'en' => 'Get the app', 'bn' => 'অ্যাপটি নিন' ),
			'f1_p'            => array( 'en' => 'Install AUN Care and sign in with your mobile number.', 'bn' => 'AUN Care ইনস্টল করে আপনার মোবাইল নম্বর দিয়ে সাইন ইন করুন।' ),
			'f2_t'            => array( 'en' => 'Enter the code', 'bn' => 'কোডটি দিন' ),
			'f2_p'            => array( 'en' => 'Open Rewards → I have a code. Your {d} discount code appears straight away.', 'bn' => 'রিওয়ার্ড → আমার কাছে কোড আছে খুলুন। আপনার {d} ছাড়ের কোড সাথে সাথে পেয়ে যাবেন।' ),
			'f3_t'            => array( 'en' => 'Buy your projector', 'bn' => 'প্রজেক্টর কিনুন' ),
			'f3_p'            => array( 'en' => 'Use the discount code at checkout with the same mobile number.', 'bn' => 'চেকআউটে একই মোবাইল নম্বর দিয়ে ছাড়ের কোডটি ব্যবহার করুন।' ),
			'f3_p_showroom'   => array( 'en' => 'Use the discount code at checkout with the same mobile number — or show it at our showroom.', 'bn' => 'চেকআউটে একই মোবাইল নম্বর দিয়ে ছাড়ের কোডটি ব্যবহার করুন — অথবা আমাদের শোরুমে দেখান।' ),

			'invite_h'        => array( 'en' => 'Invite friends and earn', 'bn' => 'বন্ধুকে আমন্ত্রণ জানিয়ে আয় করুন' ),
			'i1_t'            => array( 'en' => 'Own an AUN projector', 'bn' => 'AUN প্রজেক্টরের মালিক হন' ),
			'i1_p'            => array( 'en' => 'Buy from us or register your projector in the app, and your invite code unlocks.', 'bn' => 'আমাদের কাছ থেকে কিনুন অথবা অ্যাপে প্রজেক্টর রেজিস্টার করুন — আপনার আমন্ত্রণ কোড চালু হয়ে যাবে।' ),
			'i2_t'            => array( 'en' => 'Share your code', 'bn' => 'কোড শেয়ার করুন' ),
			'i2_p'            => array( 'en' => 'From Rewards in the app, send it on WhatsApp in one tap.', 'bn' => 'অ্যাপের রিওয়ার্ড থেকে এক ট্যাপেই হোয়াটসঅ্যাপে পাঠান।' ),
			'i3_t'            => array( 'en' => 'Earn {r}', 'bn' => '{r} পান' ),
			'i3_p'            => array( 'en' => 'When your friend\'s projector is delivered, your reward arrives in the app as a discount code.', 'bn' => 'বন্ধুর প্রজেক্টর ডেলিভারি হলে আপনার রিওয়ার্ড অ্যাপে ছাড়ের কোড হিসেবে চলে আসবে।' ),

			'owner_h'         => array( 'en' => 'Buy again, save again', 'bn' => 'আবার কিনুন, আবার সাশ্রয় করুন' ),
			'owner_p'         => array( 'en' => 'Every projector you buy from AUN gives you {o} off your next one — valid for {m} months, on our website or at our showroom. Buy again and it\'s renewed.', 'bn' => 'AUN থেকে কেনা প্রতিটি প্রজেক্টরে পরের প্রজেক্টরে {o} ছাড় — {m} মাস পর্যন্ত, ওয়েবসাইটে অথবা শোরুমে। আবার কিনলে মেয়াদ আবার শুরু হবে।' ),
			'owner_p_web'     => array( 'en' => 'Every projector you buy from AUN gives you {o} off your next one — valid for {m} months. Buy again and it\'s renewed.', 'bn' => 'AUN থেকে কেনা প্রতিটি প্রজেক্টরে পরের প্রজেক্টরে {o} ছাড় — {m} মাস পর্যন্ত। আবার কিনলে মেয়াদ আবার শুরু হবে।' ),

			'faq_h'           => array( 'en' => 'Good to know', 'bn' => 'যা জেনে রাখা ভালো' ),
			'q_who'           => array( 'en' => 'Who can use an invite code?', 'bn' => 'কারা আমন্ত্রণ কোড ব্যবহার করতে পারবেন?' ),
			'a_who'           => array( 'en' => 'Anyone buying their first AUN projector. One code per mobile number, entered within {w} days of joining the app. If you have bought from us before — online or at our showroom — the welcome discount isn\'t for you, but you can invite friends and earn.', 'bn' => 'যিনি প্রথমবার AUN প্রজেক্টর কিনছেন। একটি মোবাইল নম্বরে একটিই কোড, অ্যাপে যোগ দেওয়ার {w} দিনের মধ্যে। আগে আমাদের কাছ থেকে কিনে থাকলে — অনলাইনে বা শোরুমে — স্বাগত ছাড়টি আপনার জন্য নয়, তবে আপনি বন্ধুদের আমন্ত্রণ জানিয়ে আয় করতে পারবেন।' ),
			'a_who_nowin'     => array( 'en' => 'Anyone buying their first AUN projector. One code per mobile number. If you have bought from us before — online or at our showroom — the welcome discount isn\'t for you, but you can invite friends and earn.', 'bn' => 'যিনি প্রথমবার AUN প্রজেক্টর কিনছেন। একটি মোবাইল নম্বরে একটিই কোড। আগে আমাদের কাছ থেকে কিনে থাকলে — অনলাইনে বা শোরুমে — স্বাগত ছাড়টি আপনার জন্য নয়, তবে আপনি বন্ধুদের আমন্ত্রণ জানিয়ে আয় করতে পারবেন।' ),
			'q_two'           => array( 'en' => 'Why does the app give me a different code?', 'bn' => 'অ্যাপ কেন আলাদা একটি কোড দেয়?' ),
			'a_two'           => array( 'en' => 'Your friend\'s code unlocks your discount. The app then gives you your own discount code, starting WELCOME-, to use at checkout. It works only with the mobile number you signed in with, so nobody else can use it.', 'bn' => 'বন্ধুর কোড দিয়ে আপনার ছাড় চালু হয়। এরপর অ্যাপ আপনাকে চেকআউটে ব্যবহারের জন্য WELCOME- দিয়ে শুরু হওয়া নিজস্ব ছাড়ের কোড দেয়। এটি শুধু আপনার সাইন-ইন করা মোবাইল নম্বরেই কাজ করে, তাই অন্য কেউ ব্যবহার করতে পারবে না।' ),
			'q_long'          => array( 'en' => 'How long do discounts last?', 'bn' => 'ছাড় কতদিন থাকে?' ),
			'a_long'          => array( 'en' => 'A welcome discount is valid for {fv} days after you claim it. An invite reward is valid for {rv}.', 'bn' => 'স্বাগত ছাড় দাবি করার পর {fv} দিন পর্যন্ত থাকে। আমন্ত্রণের রিওয়ার্ড থাকে {rv}।' ),
			'rv_days'         => array( 'en' => '{n} days', 'bn' => '{n} দিন' ),
			'rv_forever'      => array( 'en' => 'as long as you need it — it never expires', 'bn' => 'যতদিন দরকার — এর মেয়াদ শেষ হয় না' ),
			'q_when'          => array( 'en' => 'When do I get my reward for inviting?', 'bn' => 'আমন্ত্রণের রিওয়ার্ড কখন পাব?' ),
			'a_when'          => array( 'en' => 'When your friend\'s projector is delivered. If the order is cancelled or returned, the reward is withdrawn. You can invite up to {cap} friends a month.', 'bn' => 'বন্ধুর প্রজেক্টর ডেলিভারি হলে। অর্ডার বাতিল বা ফেরত হলে রিওয়ার্ডটি তুলে নেওয়া হয়। প্রতি মাসে সর্বোচ্চ {cap} জন বন্ধুকে আমন্ত্রণ জানাতে পারবেন।' ),
			'a_when_nocap'    => array( 'en' => 'When your friend\'s projector is delivered. If the order is cancelled or returned, the reward is withdrawn.', 'bn' => 'বন্ধুর প্রজেক্টর ডেলিভারি হলে। অর্ডার বাতিল বা ফেরত হলে রিওয়ার্ডটি তুলে নেওয়া হয়।' ),
			'q_showroom'      => array( 'en' => 'Can I use it at the showroom?', 'bn' => 'শোরুমে কি ব্যবহার করা যাবে?' ),
			'a_showroom'      => array( 'en' => 'Yes. Tell our staff your mobile number; they send a code to your phone to confirm it\'s you, then apply your discount.', 'bn' => 'হ্যাঁ। আমাদের স্টাফকে আপনার মোবাইল নম্বর বলুন; আপনিই কিনা নিশ্চিত হতে তারা আপনার ফোনে একটি কোড পাঠাবেন, তারপর ছাড়টি দেবেন।' ),
			'q_mix'           => array( 'en' => 'Can I combine rewards?', 'bn' => 'একসাথে একাধিক রিওয়ার্ড কি ব্যবহার করা যায়?' ),
			'a_mix'           => array( 'en' => 'Yes — your rewards can be used together on one order{cap}. They can\'t be combined with other coupon codes.', 'bn' => 'হ্যাঁ — একটি অর্ডারে আপনার রিওয়ার্ডগুলো একসাথে ব্যবহার করা যায়{cap}। অন্য কোনো কুপন কোডের সাথে মেলানো যায় না।' ),
			'a_mix_cap'       => array( 'en' => ', up to {c}% off in total — anything that does not fit stays in your Rewards as a new code, so nothing is lost', 'bn' => ', মোট সর্বোচ্চ {c}% পর্যন্ত — যা বাকি থাকে তা নতুন কোড হিসেবে আপনার রিওয়ার্ডে থেকে যায়, কিছুই হারায় না' ),
			'q_sale'          => array( 'en' => 'Does the owner reward work on sale items?', 'bn' => 'অফারে থাকা পণ্যে কি মালিকের রিওয়ার্ড কাজ করে?' ),
			'a_sale'          => array( 'en' => 'No — a product that already has a sale price keeps that price instead. Your reward waits for your next full-price projector.', 'bn' => 'না — যে পণ্যে আগে থেকেই অফার মূল্য আছে, সেখানে অফার মূল্যই থাকবে। আপনার রিওয়ার্ড পরের পূর্ণ মূল্যের প্রজেক্টরের জন্য থেকে যাবে।' ),

			'help_h'          => array( 'en' => 'Questions? Talk to a human', 'bn' => 'প্রশ্ন আছে? আমাদের সাথে কথা বলুন' ),
			'help_p'          => array( 'en' => 'We\'re happy to walk you through it.', 'bn' => 'আমরা ধাপে ধাপে সাহায্য করতে পেরে খুশি।' ),
			'whatsapp'        => array( 'en' => 'WhatsApp us', 'bn' => 'হোয়াটসঅ্যাপ করুন' ),
			'call'            => array( 'en' => 'Call 09638-078888', 'bn' => 'কল করুন 09638-078888' ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Facts the page states — all read from the settings
	 * --------------------------------------------------------------------- */

	/** "5%" or "৳500" — the friend's welcome discount. */
	private static function friend_label( $s ) {
		$a = (float) $s['friend_amount'];
		return 'percent' === $s['friend_type']
			? self::num( $a ) . '%'
			: '৳' . number_format( $a );
	}

	/** "5% of their order" / "৳500" — the inviter's reward. */
	private static function reward_label( $s ) {
		$a = (float) $s['referrer_amount'];
		if ( 'percent' === $s['referrer_type'] ) {
			return 'bn' === self::lang()
				? 'তাদের অর্ডারের ' . self::num( $a ) . '%'
				: self::num( $a ) . '% of their order';
		}
		return '৳' . number_format( $a );
	}

	private static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
	}

	/** The Owner Rewards terms, or null when that programme is off/absent. */
	private static function owner() {
		if ( ! class_exists( 'AUN_App_Owner_Rewards' ) || ! AUN_App_Owner_Rewards::enabled() ) {
			return null;
		}
		return AUN_App_Owner_Rewards::settings();
	}

	/** Whether rewards can be spent at the showroom. */
	private static function showroom() {
		return class_exists( 'AUN_App_Owner_Rewards' ) && AUN_App_Owner_Rewards::showroom_enabled();
	}

	/** The combined ceiling in %, 0 when there is none. */
	private static function ceiling() {
		return class_exists( 'AUN_App_Owner_Rewards' ) ? (int) AUN_App_Owner_Rewards::ceiling_percent() : 0;
	}

	/** The code from the link, when it is a real, live invite code. */
	private static function code_from_request() {
		$raw = isset( $_GET['code'] ) ? (string) wp_unslash( $_GET['code'] ) : '';
		$raw = strtoupper( substr( preg_replace( '/[^A-Za-z0-9\-]/', '', $raw ), 0, 32 ) );
		return $raw;
	}

	/** The inviter's first name, only when they gave one — never a login or a number. */
	private static function inviter_name( $user_id ) {
		$first = trim( (string) get_user_meta( (int) $user_id, 'first_name', true ) );
		if ( '' === $first || preg_match( '/\d/', $first ) ) {
			return '';
		}
		$first = preg_split( '/\s+/', $first )[0];
		return function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 24 ) : substr( $first, 0, 24 );
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	public static function render( $atts = array() ) {
		if ( ! class_exists( 'AUN_App_Referrals' ) ) {
			return '';
		}
		$s     = AUN_App_Referrals::settings();
		$on    = AUN_App_Referrals::available();
		$d     = self::friend_label( $s );
		$r     = self::reward_label( $s );
		$owner = self::owner();
		$shop  = self::showroom();
		$cap   = self::ceiling();

		$code   = self::code_from_request();
		$owner_id = ( '' !== $code && $on ) ? (int) AUN_App_Referrals::owner_of( $code ) : 0;
		$valid  = $owner_id > 0;
		$name   = $valid ? self::inviter_name( $owner_id ) : '';

		if ( $valid ) {
			$h1  = '' !== $name ? self::t( 'h_named', array( 'name' => esc_html( $name ), 'd' => $d ) ) : self::t( 'h_invited', array( 'd' => $d ) );
			$sub = self::t( 'sub_invited' );
		} else {
			$h1  = self::t( 'h_plain' );
			$sub = self::t( 'sub_plain', array( 'd' => $d, 'r' => $r ) );
		}

		ob_start();
		self::styles();
		?>
		<div class="aun-rw" lang="<?php echo esc_attr( 'bn' === self::lang() ? 'bn' : 'en' ); ?>">

			<section class="aun-rw-hero">
				<span class="aun-rw-pill"><?php echo self::icon( 'gift' ); ?> <?php echo esc_html( self::t( 'pill' ) ); ?></span>
				<h1 class="aun-rw-h1"><?php echo wp_kses_post( $h1 ); ?></h1>
				<p class="aun-rw-sub"><?php echo esc_html( $sub ); ?></p>

				<?php if ( ! $on ) : ?>
					<div class="aun-rw-note"><?php echo esc_html( self::t( 'off' ) ); ?></div>
				<?php elseif ( $valid ) : ?>
					<div class="aun-rw-codecard">
						<div class="aun-rw-codelabel"><?php echo esc_html( self::t( 'code_label' ) ); ?></div>
						<div class="aun-rw-coderow">
							<span class="aun-rw-code" id="aun-rw-code"><?php echo esc_html( $code ); ?></span>
							<button type="button" class="aun-rw-copy" data-copied="<?php echo esc_attr( self::t( 'copied' ) ); ?>"
								onclick="aunRwCopy(this)"><?php echo esc_html( self::t( 'copy' ) ); ?></button>
						</div>
						<a class="aun-rw-btn" href="<?php echo esc_url( self::app_url() ); ?>"><?php echo self::icon( 'phone' ); ?> <?php echo esc_html( self::t( 'get_app' ) ); ?></a>
						<p class="aun-rw-where"><?php echo esc_html( self::t( 'code_where' ) ); ?></p>
					</div>
				<?php elseif ( '' !== $code ) : ?>
					<div class="aun-rw-note"><?php echo esc_html( self::t( 'bad_code' ) ); ?></div>
				<?php else : ?>
					<a class="aun-rw-btn" href="<?php echo esc_url( self::app_url() ); ?>"><?php echo self::icon( 'phone' ); ?> <?php echo esc_html( self::t( 'get_app' ) ); ?></a>
				<?php endif; ?>
			</section>

			<?php if ( $on ) : ?>
			<section class="aun-rw-sec">
				<h2 class="aun-rw-h2"><?php echo esc_html( self::t( 'friend_h' ) ); ?></h2>
				<div class="aun-rw-bar"></div>
				<div class="aun-rw-cards">
					<?php
					self::card( 'phone', '1', self::t( 'f1_t' ), self::t( 'f1_p' ) );
					self::card( 'ticket', '2', self::t( 'f2_t' ), self::t( 'f2_p', array( 'd' => $d ) ) );
					self::card( 'cart', '3', self::t( 'f3_t' ), self::t( $shop ? 'f3_p_showroom' : 'f3_p' ) );
					?>
				</div>
			</section>

			<section class="aun-rw-sec aun-rw-alt">
				<h2 class="aun-rw-h2"><?php echo esc_html( self::t( 'invite_h' ) ); ?></h2>
				<div class="aun-rw-bar"></div>
				<div class="aun-rw-cards">
					<?php
					self::card( 'shield', '1', self::t( 'i1_t' ), self::t( 'i1_p' ) );
					self::card( 'share', '2', self::t( 'i2_t' ), self::t( 'i2_p' ) );
					self::card( 'gift', '3', self::t( 'i3_t', array( 'r' => $r ) ), self::t( 'i3_p' ) );
					?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $owner ) : ?>
			<section class="aun-rw-sec">
				<div class="aun-rw-owner">
					<div class="aun-rw-owner-ico"><?php echo self::icon( 'repeat' ); ?></div>
					<div>
						<h2 class="aun-rw-h2 aun-rw-left"><?php echo esc_html( self::t( 'owner_h' ) ); ?></h2>
						<p class="aun-rw-p"><?php
							echo esc_html( self::t( $shop ? 'owner_p' : 'owner_p_web', array(
								'o' => self::num( $owner['percent'] ) . '%',
								'm' => (int) $owner['valid_months'],
							) ) );
						?></p>
					</div>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $on || $owner ) : ?>
			<section class="aun-rw-sec aun-rw-alt">
				<h2 class="aun-rw-h2"><?php echo esc_html( self::t( 'faq_h' ) ); ?></h2>
				<div class="aun-rw-bar"></div>
				<div class="aun-rw-faq">
					<?php
					if ( $on ) {
						$w = (int) $s['claim_window_days'];
						self::faq( self::t( 'q_who' ), $w > 0 ? self::t( 'a_who', array( 'w' => $w ) ) : self::t( 'a_who_nowin' ) );
						self::faq( self::t( 'q_two' ), self::t( 'a_two' ) );
						$rv = (int) $s['reward_expiry_days'] > 0
							? self::t( 'rv_days', array( 'n' => (int) $s['reward_expiry_days'] ) )
							: self::t( 'rv_forever' );
						self::faq( self::t( 'q_long' ), self::t( 'a_long', array( 'fv' => (int) $s['coupon_expiry_days'], 'rv' => $rv ) ) );
						self::faq( self::t( 'q_when' ), (int) $s['monthly_cap'] > 0 ? self::t( 'a_when', array( 'cap' => (int) $s['monthly_cap'] ) ) : self::t( 'a_when_nocap' ) );
					}
					if ( $shop ) {
						self::faq( self::t( 'q_showroom' ), self::t( 'a_showroom' ) );
					}
					self::faq( self::t( 'q_mix' ), self::t( 'a_mix', array( 'cap' => $cap > 0 ? self::t( 'a_mix_cap', array( 'c' => $cap ) ) : '' ) ) );
					if ( $owner && ! empty( $owner['exclude_sale'] ) ) {
						self::faq( self::t( 'q_sale' ), self::t( 'a_sale' ) );
					}
					?>
				</div>
			</section>
			<?php endif; ?>

			<section class="aun-rw-sec aun-rw-help">
				<h2 class="aun-rw-h2"><?php echo esc_html( self::t( 'help_h' ) ); ?></h2>
				<p class="aun-rw-p aun-rw-center"><?php echo esc_html( self::t( 'help_p' ) ); ?></p>
				<div class="aun-rw-helpbtns">
					<a class="aun-rw-btn aun-rw-wa" href="https://wa.me/8801787698268" target="_blank" rel="noopener"><?php echo self::icon( 'chat' ); ?> <?php echo esc_html( self::t( 'whatsapp' ) ); ?></a>
					<a class="aun-rw-btn aun-rw-ghost" href="tel:+8809638078888"><?php echo self::icon( 'call' ); ?> <?php echo esc_html( self::t( 'call' ) ); ?></a>
				</div>
			</section>
		</div>
		<script data-no-optimize="1" data-no-minify="1" data-no-defer="1" data-cfasync="false">
		function aunRwCopy(btn){
			var el=document.getElementById('aun-rw-code'); if(!el){return;}
			var code=el.textContent.trim(), done=function(){var t=btn.textContent;btn.textContent=btn.getAttribute('data-copied');setTimeout(function(){btn.textContent=t;},1600);};
			if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(code).then(done,function(){aunRwSelect(el);});}else{aunRwSelect(el);done();}
		}
		function aunRwSelect(el){try{var r=document.createRange();r.selectNodeContents(el);var s=window.getSelection();s.removeAllRanges();s.addRange(r);document.execCommand('copy');}catch(e){}}
		</script>
		<?php
		return (string) ob_get_clean();
	}

	private static function card( $icon, $n, $title, $text ) {
		?>
		<div class="aun-rw-card">
			<div class="aun-rw-ico"><?php echo self::icon( $icon ); ?><span class="aun-rw-step"><?php echo esc_html( $n ); ?></span></div>
			<h3 class="aun-rw-h3"><?php echo esc_html( $title ); ?></h3>
			<p class="aun-rw-cardp"><?php echo esc_html( $text ); ?></p>
		</div>
		<?php
	}

	private static function faq( $q, $a ) {
		?>
		<details class="aun-faq"><summary><?php echo esc_html( $q ); ?></summary><p><?php echo esc_html( $a ); ?></p></details>
		<?php
	}

	/** Small inline icons — no icon font needed (the site's is a subset). */
	private static function icon( $name ) {
		$p = array(
			'gift'   => '<path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/>',
			'phone'  => '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>',
			'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4 2 2 0 0 0 0-4V6H3z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
			'cart'   => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 11.4a1.5 1.5 0 0 0 1.5 1.1h8.7a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>',
			'shield' => '<path d="M12 2l8 3v6c0 5-3.4 9.3-8 11-4.6-1.7-8-6-8-11V5z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
			'share'  => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4"/>',
			'repeat' => '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>',
			'chat'   => '<path d="M21 12a8.5 8.5 0 0 1-12.4 7.6L3 21l1.4-5.4A8.5 8.5 0 1 1 21 12z"/>',
			'call'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
		);
		return '<svg class="aun-rw-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
			. ( $p[ $name ] ?? '' ) . '</svg>';
	}

	/** House style (aun-design-system.md): #0188fe, 14px cards, #e2e8f0 borders. */
	private static function styles() {
		?>
		<style id="aun-rw-css" data-no-optimize="1" data-no-minify="1">
		.aun-rw{--b:#0188fe;--bd:#0166c8;--ink:#0f172a;--mut:#556777;--line:#e2e8f0;max-width:1080px;margin:0 auto;color:var(--ink)}
		.aun-rw *{box-sizing:border-box}
		.aun-rw-hero{background:rgb(246,248,251);border-radius:18px;padding:44px 20px;text-align:center}
		.aun-rw-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(1,136,254,.08);border:1px solid rgba(1,136,254,.2);font-weight:800;font-size:12px;color:var(--bd);text-transform:uppercase;letter-spacing:.5px}
		.aun-rw-pill .aun-rw-svg{width:15px;height:15px}
		.aun-rw-h1{color:var(--b);font-size:clamp(24px,4vw,36px);line-height:1.25;margin:16px auto 10px;max-width:760px}
		.aun-rw-sub{color:var(--mut);font-size:16px;max-width:620px;margin:0 auto 22px;line-height:1.6}
		.aun-rw-codecard{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 6px rgba(15,23,42,.04);padding:22px;max-width:420px;margin:0 auto}
		.aun-rw-codelabel{font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:var(--mut)}
		.aun-rw-coderow{display:flex;align-items:center;justify-content:center;gap:10px;margin:10px 0 16px;flex-wrap:wrap}
		.aun-rw-code{font-size:30px;font-weight:800;letter-spacing:3px;color:var(--ink);border:2px dashed rgba(1,136,254,.45);border-radius:12px;padding:6px 16px;background:rgba(1,136,254,.05);word-break:break-all}
		.aun-rw-copy{border:1px solid var(--line);background:#fff;color:var(--bd);font-weight:700;border-radius:10px;padding:10px 14px;cursor:pointer;min-height:44px}
		.aun-rw-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:12px 22px;border-radius:12px;background:linear-gradient(135deg,#0188fe,#00c6ff);color:#fff!important;font-weight:800;text-decoration:none!important;box-shadow:0 6px 16px rgba(1,136,254,.25)}
		.aun-rw-codecard .aun-rw-btn{width:100%}
		.aun-rw-where{font-size:13px;color:var(--mut);margin:12px 0 0}
		.aun-rw-note{background:#fff8e5;border:1px solid #f5d98a;color:#7a5a00;border-radius:12px;padding:14px 16px;max-width:560px;margin:0 auto;font-size:14px;line-height:1.55}
		.aun-rw-sec{padding:40px 16px}
		.aun-rw-alt{background:#f8fafc;border-radius:18px}
		.aun-rw-h2{color:var(--ink);font-size:clamp(20px,3vw,26px);text-align:center;margin:0 0 8px}
		.aun-rw-left{text-align:left}
		.aun-rw-bar{width:60px;height:3px;background:var(--b);border-radius:2px;margin:0 auto 22px;opacity:.85}
		.aun-rw-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
		.aun-rw-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,.04);text-align:center}
		.aun-rw-ico{position:relative;width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,.1);color:var(--b);display:flex;align-items:center;justify-content:center}
		.aun-rw-step{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:var(--b);color:#fff;font-size:11px;font-weight:800;line-height:20px}
		.aun-rw-h3{font-size:16px;margin:0 0 6px;color:var(--ink)}
		.aun-rw-cardp{font-size:14px;color:#64748b;margin:0;line-height:1.6}
		.aun-rw-owner{display:flex;gap:18px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-left:4px solid #ffbc00;border-radius:14px;padding:22px;box-shadow:0 2px 6px rgba(15,23,42,.04);max-width:820px;margin:0 auto}
		.aun-rw-owner-ico{flex:0 0 48px;height:48px;border-radius:14px;background:rgba(255,188,0,.14);color:#b07d00;display:flex;align-items:center;justify-content:center}
		.aun-rw-p{color:var(--mut);font-size:15px;line-height:1.65;margin:0}
		.aun-rw-center{text-align:center;margin-bottom:16px}
		.aun-rw-faq{max-width:820px;margin:0 auto}
		.aun-rw .aun-faq{background:#fff;border:1px solid var(--line);border-radius:12px;margin:0 0 10px;padding:0 16px}
		.aun-rw .aun-faq[open]{border-left:3px solid #ffbc00}
		.aun-rw .aun-faq summary{cursor:pointer;font-weight:700;padding:14px 0;list-style:none;color:var(--ink)}
		.aun-rw .aun-faq summary::-webkit-details-marker{display:none}
		.aun-rw .aun-faq summary:after{content:'+';float:right;color:var(--b);font-weight:800}
		.aun-rw .aun-faq[open] summary:after{content:'–'}
		.aun-rw .aun-faq p{color:var(--mut);margin:0 0 14px;line-height:1.65;font-size:14px}
		.aun-rw-helpbtns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
		.aun-rw-wa{background:#25D366;box-shadow:none}
		.aun-rw-ghost{background:#fff;color:var(--bd)!important;border:1px solid var(--line);box-shadow:none}
		@media (max-width:767px){.aun-rw-cards{grid-template-columns:1fr}.aun-rw-owner{flex-direction:column}.aun-rw-hero{padding:32px 16px}.aun-rw-code{font-size:24px}}
		</style>
		<?php
	}
}
