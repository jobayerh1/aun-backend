<?php
/**
 * App-specific customer profile.
 *
 * The WordPress account is only the login identity — and a phone number can
 * match a pre-existing/shared account (e.g. the store's own admin), whose name
 * and email must NOT leak into the app. So the app keeps its own profile in
 * user meta, seeded from the ERP sales record on first login and editable by
 * the customer. Nothing here ever writes to the WordPress account fields.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Profile {

	const META_NAME   = 'aun_app_name';
	const META_EMAIL  = 'aun_app_email';
	const META_AVATAR = 'aun_app_avatar'; // a URL, or "emoji:😀"

	public static function get_name( $user_id ) {
		return trim( (string) get_user_meta( $user_id, self::META_NAME, true ) );
	}

	public static function get_email( $user_id ) {
		return trim( (string) get_user_meta( $user_id, self::META_EMAIL, true ) );
	}

	public static function set_name( $user_id, $name ) {
		update_user_meta( $user_id, self::META_NAME, sanitize_text_field( $name ) );
	}

	public static function set_email( $user_id, $email ) {
		update_user_meta( $user_id, self::META_EMAIL, sanitize_email( $email ) );
	}

	/**
	 * Store an uploaded avatar URL, or an emoji ("emoji:😀"), or clear it ('').
	 */
	public static function set_avatar( $user_id, $value ) {
		update_user_meta( $user_id, self::META_AVATAR, $value );
	}

	private static function raw_avatar( $user_id ) {
		return (string) get_user_meta( $user_id, self::META_AVATAR, true );
	}

	/**
	 * Resolve the avatar image URL: uploaded photo first, else a Gravatar for
	 * the profile email (which 404s to the app's initials fallback when the
	 * customer has no Gravatar), else empty.
	 */
	public static function avatar_url( $user_id ) {
		$raw = self::raw_avatar( $user_id );
		if ( '' !== $raw && 0 === strpos( $raw, 'http' ) ) {
			return $raw;
		}
		$email = self::get_email( $user_id );
		if ( '' !== $email && is_email( $email ) ) {
			return 'https://www.gravatar.com/avatar/' . md5( strtolower( trim( $email ) ) ) . '?s=200&d=404';
		}
		return '';
	}

	public static function avatar_emoji( $user_id ) {
		$raw = self::raw_avatar( $user_id );
		return 0 === strpos( $raw, 'emoji:' ) ? substr( $raw, 6 ) : '';
	}

	/**
	 * Seed name/email from the ERP contact for this phone, once, if unset.
	 * Called on login so a returning customer sees their real name with no typing.
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Canonical phone.
	 */
	public static function seed_from_erp( $user_id, $canonical ) {
		if ( '' !== self::get_name( $user_id ) ) {
			return; // already have a name — never overwrite the customer's own.
		}
		if ( ! AUN_App_ERP::configured() ) {
			return;
		}
		$sales = AUN_App_ERP::lookup_phone( $canonical );
		if ( is_wp_error( $sales ) || empty( $sales ) ) {
			return;
		}
		foreach ( $sales as $sale ) {
			$name = trim( (string) ( $sale['contact_name'] ?? '' ) );
			if ( '' !== $name ) {
				self::set_name( $user_id, $name );
				return;
			}
		}
	}

	/**
	 * Full profile payload for the app.
	 *
	 * @param WP_User $user  User.
	 * @param array   $extra Extra keys to merge (e.g. is_new).
	 * @return array
	 */
	public static function payload( $user, $extra = array() ) {
		$canonical = AUN_App_Phone::user_phone( $user->ID );
		$name      = self::get_name( $user->ID );

		return array_merge(
			array(
				'id'           => (int) $user->ID,
				'name'         => $name,
				'email'        => self::get_email( $user->ID ),
				'phone'        => $canonical ? $canonical : '',
				'phone_masked' => $canonical ? AUN_App_Phone::mask( $canonical ) : '',
				'avatar_url'   => self::avatar_url( $user->ID ),
				'avatar_emoji' => self::avatar_emoji( $user->ID ),
				// The app shows the "what's your name?" prompt when this is true.
				'need_profile' => '' === $name,
			),
			$extra
		);
	}
}
