<?php
/**
 * Editable customer messages + reject reason templates.
 *
 * Everything the customer receives (status SMS, rejection SMS, "send a better
 * photo" SMS) and the reject reason templates live in options so the admin can
 * reword them — including writing them in Bangla — without touching code.
 *
 * Placeholders (replaced at send time): {ref} {model} {status} {reason} {coupon}
 * {track} {parts} {date} {age} {changes} {total} {pay}. Unknown placeholders are stripped.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Messages {

	const OPT_SMS_RECEIVED = 'aun_sp_sms_received';
	const OPT_SMS_STATUS   = 'aun_sp_sms_status';
	const OPT_SMS_PARTS    = 'aun_sp_sms_parts';
	const OPT_SMS_REJECT   = 'aun_sp_sms_reject';
	const OPT_SMS_PHOTO    = 'aun_sp_sms_photo';
	const OPT_SMS_QUOTE    = 'aun_sp_sms_quote';
	const OPT_SMS_APPROVED = 'aun_sp_sms_approved';
	const OPT_SMS_PAY      = 'aun_sp_sms_pay';
	const OPT_SMS_PAID     = 'aun_sp_sms_paid';
	const OPT_REJECT_TPL   = 'aun_sp_reject_templates';
	const OPT_PAY_INFO     = 'aun_sp_pay_info';

	/** Default SMS bodies, keyed by option name. */
	public static function sms_defaults() {
		return array(
			self::OPT_SMS_RECEIVED => 'AUN: thank you! Your spare-parts request {ref} has been received. Save this number and track it any time here: {track}',
			self::OPT_SMS_STATUS   => 'AUN: your spare-parts request {ref} is now "{status}". Track it: {track}',
			self::OPT_SMS_PARTS    => 'AUN: update on your spare-parts request {ref} — {changes}. Track it: {track}',
			self::OPT_SMS_REJECT   => 'AUN: update on your spare-parts request {ref}. {reason} {coupon}',
			self::OPT_SMS_PHOTO    => 'AUN: the photo for request {ref} needs to be clearer. Please open the link, take it just like the example shown, and re-upload: {track}',
			self::OPT_SMS_QUOTE    => 'AUN: your spare-parts quote for {ref} is ready - total Tk {total}. Please review and approve it here: {track}',
			self::OPT_SMS_APPROVED => 'AUN: thank you, your quote for {ref} is approved. We will now start sourcing your parts. {pay}',
			self::OPT_SMS_PAY      => 'AUN: to pay online for {ref} (Tk {total}), open this link: {link}',
			self::OPT_SMS_PAID     => 'AUN: payment of Tk {total} received for {ref} - thank you. Track it: {track}',
		);
	}

	/** Payment instructions shown with the quote (admin-editable; may be empty). */
	public static function pay_info() {
		return (string) get_option( self::OPT_PAY_INFO, '' );
	}

	/**
	 * A customer's permanent direct link to their request: the tracking page with
	 * ?ref=SP-... appended, so the SMS link opens straight to their request (and the
	 * Approve button when a quote is waiting).
	 */
	public static function track_link( $ref ) {
		$base = trim( (string) get_option( 'aun_sp_tracking_url', '' ) );
		if ( $base === '' ) {
			// Fall back to the standard status-page slug so the SMS never carries a
			// dangling "track it here:" with no link when the setting is unset.
			$base = home_url( '/spare-parts-status/' );
		}
		$ref = trim( (string) $ref );
		if ( $ref === '' ) {
			return $base;
		}
		return $base . ( strpos( $base, '?' ) !== false ? '&' : '?' ) . 'ref=' . rawurlencode( $ref );
	}

	/** Default reject reason templates (genuine "cannot supply" + duplicate cases). */
	public static function reject_defaults() {
		return array(
			array( 'name' => 'Duplicate request',               'text' => "It looks like you sent us this request more than once. No problem — we're handling it under your earlier request, so we've closed this duplicate. You can keep tracking the original one; nothing else is needed from you." ),
			array( 'name' => 'Old device — parts discontinued', 'text' => "Your {model} was purchased on {date} and is now about {age} years old. The factory no longer produces the {parts} for a unit this old, so we're unable to source it. We're sorry for the inconvenience — if you'd like, we can suggest a current model as an upgrade." ),
			array( 'name' => 'Part discontinued (model)',        'text' => "We checked with the factory: the {parts} for the {model} has been discontinued and is no longer available. Unfortunately we can't supply this part." ),
			array( 'name' => 'Not economically repairable',      'text' => "For the {model}, the cost of the {parts} plus shipping now exceeds what a newer projector would cost. Rather than spend on an aging unit, we'd recommend an upgrade — we're happy to help you choose." ),
		);
	}

	/**
	 * One-time migration: append a default reject template to an EXISTING saved set
	 * if no template by that name is present. New defaults (like "Duplicate request")
	 * otherwise only reach fresh installs via seed(); this gets them to live sites
	 * without clobbering the admin's own edits. Called from install on version bump.
	 */
	public static function ensure_reject_template( $name ) {
		$saved = get_option( self::OPT_REJECT_TPL, null );
		if ( ! is_array( $saved ) ) {
			return; // never customised — seed()/reject_templates() already serve defaults
		}
		foreach ( $saved as $tpl ) {
			if ( isset( $tpl['name'] ) && strcasecmp( trim( $tpl['name'] ), $name ) === 0 ) {
				return; // already there (or admin re-added it)
			}
		}
		foreach ( self::reject_defaults() as $def ) {
			if ( strcasecmp( $def['name'], $name ) === 0 ) {
				array_unshift( $saved, $def );
				update_option( self::OPT_REJECT_TPL, $saved );
				return;
			}
		}
	}

	public static function sms( $key ) {
		$d = self::sms_defaults();
		return (string) get_option( $key, isset( $d[ $key ] ) ? $d[ $key ] : '' );
	}

	public static function reject_templates() {
		$t = get_option( self::OPT_REJECT_TPL, null );
		return ( is_array( $t ) && ! empty( $t ) ) ? $t : self::reject_defaults();
	}

	/** Replace {placeholders} and tidy whitespace. */
	public static function fill( $template, $vars ) {
		foreach ( $vars as $k => $v ) {
			$template = str_replace( '{' . $k . '}', (string) $v, $template );
		}
		$template = preg_replace( '/\{[a-z_]+\}/', '', $template ); // drop any leftover tokens
		return trim( preg_replace( '/\s{2,}/', ' ', $template ) );
	}

	public static function seed() {
		foreach ( self::sms_defaults() as $k => $v ) {
			add_option( $k, $v );
		}
		add_option( self::OPT_PAY_INFO, '' );
		if ( get_option( self::OPT_REJECT_TPL, null ) === null ) {
			add_option( self::OPT_REJECT_TPL, self::reject_defaults() );
		}
	}
}
