<?php
/**
 * App account deletion.
 *
 * Google Play requires any app with accounts to offer in-app account deletion.
 * What that means HERE is a deliberate, narrow thing: it deletes the customer's
 * AUN Care **app account and personal data** — profile, sessions, push tokens,
 * their list of linked projectors, notifications, bug reports.
 *
 * It deliberately does NOT delete:
 *   • warranty registrations — that is the customer's own proof of purchase and
 *     the basis of a warranty they are still entitled to;
 *   • spare-parts / repair requests — service records, often still in progress;
 *   • the ERP sale — our transaction record, which we are required to keep;
 *   • the WordPress user — it may carry website (WooCommerce) order history.
 * The confirmation screen in the app states all of this before anything happens.
 *
 * Logging in again with the same phone number gives a clean, empty app profile.
 *
 * @package AUN_App_API
 */

defined( 'ABSPATH' ) || exit;

class AUN_App_Account {

	/**
	 * Delete the app account of one user.
	 *
	 * Ordered so that the SESSION dies first: if anything later fails, the
	 * customer is still logged out and their phone can no longer act as them.
	 *
	 * @param int    $user_id   WP user id.
	 * @param string $canonical Canonical phone (8801XXXXXXXXX), for the
	 *                          phone-keyed tables.
	 * @return array{ok:bool,deleted:array<string,int>}
	 */
	public static function delete( $user_id, $canonical = '' ) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array( 'ok' => false, 'deleted' => array() );
		}

		$deleted = array();

		// 1. Every session on every phone, and with them the FCM push tokens
		//    (they live on the token rows) — so pushes stop immediately.
		$deleted['sessions'] = (int) $wpdb->delete(
			aun_app_api_tokens_table(),
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// 2. The app profile: name, email, avatar. The avatar image itself is
		//    an attachment we uploaded, so it goes too.
		$avatar = (string) get_user_meta( $user_id, AUN_App_Profile::META_AVATAR, true );
		if ( '' !== $avatar && 0 !== strpos( $avatar, 'emoji:' ) ) {
			$attachment_id = attachment_url_to_postid( $avatar );
			if ( $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
		delete_user_meta( $user_id, AUN_App_Profile::META_NAME );
		delete_user_meta( $user_id, AUN_App_Profile::META_EMAIL );
		delete_user_meta( $user_id, AUN_App_Profile::META_AVATAR );
		$deleted['profile'] = 1;

		// 3. Linked projectors. Deleting the row FREES the serial for whoever
		//    owns the unit next — the same thing "remove device" does.
		$deleted['devices'] = (int) $wpdb->delete(
			aun_app_api_devices_table(),
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// 4. The dismissed-purchases ledger. It only exists to remember a
		//    choice this account made; with the account gone it is meaningless
		//    (and keeping it would silently suppress purchases on a fresh
		//    account created from the same phone later).
		$deleted['dismissals'] = (int) $wpdb->delete(
			aun_app_api_dismissed_table(),
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// 5. Notifications: personal notices, and the read/dismissed state for
		//    broadcasts.
		$t_notice = AUN_App_Notices::table();
		$t_state  = AUN_App_Notices::state_table();
		$deleted['notifications'] = (int) $wpdb->delete( $t_notice, array( 'user_id' => $user_id ), array( '%d' ) );
		$deleted['notice_state']  = (int) $wpdb->delete( $t_state, array( 'user_id' => $user_id ), array( '%d' ) );

		// 6. Bug reports they filed from Settings (free text they wrote).
		$deleted['feedback'] = (int) $wpdb->delete(
			$wpdb->prefix . 'aun_app_feedback',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// 7. Detach service requests from the account WITHOUT deleting them:
		//    a repair may be physically sitting on a workbench right now, and
		//    its history is a business record. The row keeps the phone number
		//    it was created with (the service centre needs to reach them).
		$t_repairs = $wpdb->prefix . 'aun_app_repairs';
		$deleted['repairs_detached'] = (int) $wpdb->update(
			$t_repairs,
			array( 'user_id' => 0 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);

		/**
		 * Fires after an app account has been deleted.
		 *
		 * @param int    $user_id
		 * @param string $canonical Canonical phone.
		 */
		do_action( 'aun_app_account_deleted', $user_id, (string) $canonical );

		return array( 'ok' => true, 'deleted' => $deleted );
	}

	/**
	 * A plain-language summary of what deletion does, so the app's confirm
	 * screen and this code can never drift apart. Bilingual.
	 *
	 * @return array{removed:string[],kept:string[]}
	 */
	public static function summary( $lang = 'en' ) {
		if ( 'bn' === $lang ) {
			return array(
				'removed' => array(
					'আপনার প্রোফাইল — নাম, ইমেইল ও ছবি',
					'সব ডিভাইসে আপনার লগইন ও নোটিফিকেশন',
					'অ্যাপে যুক্ত করা প্রজেক্টরের তালিকা',
					'আপনার পাঠানো সমস্যার রিপোর্ট',
				),
				'kept'    => array(
					'আপনার ওয়ারেন্টি রেজিস্ট্রেশন — এটি আপনারই কেনার প্রমাণ',
					'চলমান বা শেষ হওয়া রিপেয়ার ও যন্ত্রাংশের অনুরোধ',
					'আমাদের বিক্রয় রেকর্ড (ইনভয়েস)',
				),
			);
		}
		return array(
			'removed' => array(
				'Your profile — name, email and photo',
				'Your login on every device, and your notifications',
				'The list of projectors you added in the app',
				'Any problem reports you sent us',
			),
			'kept'    => array(
				"Your warranty registration — it's your own proof of purchase",
				'Repair and spare-parts requests, finished or in progress',
				'Our sales record (your invoice)',
			),
		);
	}
}
