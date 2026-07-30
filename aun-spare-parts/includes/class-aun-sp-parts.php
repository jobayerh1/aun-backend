<?php
/**
 * The spare-parts catalogue — editable from the admin (Spare Parts → Parts catalogue)
 * instead of being hardcoded. Each part stores English + Bangla label and proof text,
 * its photo requirement, and an active flag. The form, reject templates, and (later)
 * the bilingual layer all read from here.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Parts {

	const OPTION = 'aun_sp_parts';

	/** The seven we started with — used to seed a fresh install. */
	public static function defaults() {
		return array(
			array( 'key' => 'lcd',         'label_en' => 'LCD screen',            'label_bn' => 'এলসিডি স্ক্রিন',        'proof_en' => 'Photo of the LCD serial (printed on the ribbon)',        'proof_bn' => 'এলসিডি সিরিয়াল নম্বরের ছবি (রিবনে লেখা থাকে)',          'photo' => 'required', 'active' => 1 ),
			array( 'key' => 'heat_glass',  'label_en' => 'Heat insulation glass',  'label_bn' => 'হিট ইনসুলেশন গ্লাস',   'proof_en' => 'Photo of the LCD serial — the glass must match your LCD', 'proof_bn' => 'এলসিডি সিরিয়াল নম্বরের ছবি — গ্লাস এলসিডির সাথে মিলতে হবে', 'photo' => 'required', 'active' => 1 ),
			array( 'key' => 'fresnel',     'label_en' => 'Fresnel lens',           'label_bn' => 'ফ্রেনেল লেন্স',        'proof_en' => '',                                                       'proof_bn' => '',                                                       'photo' => 'none',     'active' => 1 ),
			array( 'key' => 'motherboard', 'label_en' => 'Motherboard',            'label_bn' => 'মাদারবোর্ড',           'proof_en' => 'Photo of the board (the model number printed on it)',    'proof_bn' => 'বোর্ডের ছবি (বোর্ডে লেখা মডেল নম্বরসহ)',                  'photo' => 'required', 'active' => 1 ),
			array( 'key' => 'led',         'label_en' => 'LED',                    'label_bn' => 'এলইডি',                'proof_en' => 'Photo of the LED chip (the model number near it)',       'proof_bn' => 'এলইডি চিপের ছবি (পাশে লেখা মডেল নম্বরসহ)',                'photo' => 'required', 'active' => 1 ),
			array( 'key' => 'power_board', 'label_en' => 'Power board',            'label_bn' => 'পাওয়ার বোর্ড',         'proof_en' => 'Photo of the power board (so we match an identical one)', 'proof_bn' => 'পাওয়ার বোর্ডের ছবি (যাতে আমরা হুবহু মিলিয়ে দিতে পারি)',   'photo' => 'required', 'active' => 1 ),
			array( 'key' => 'remote',      'label_en' => 'Remote control',         'label_bn' => 'রিমোট কন্ট্রোল',       'proof_en' => 'Photo of the remote (optional — skip if you lost it)',    'proof_bn' => 'রিমোটের ছবি (ঐচ্ছিক — হারিয়ে ফেললে বাদ দিন)',            'photo' => 'optional', 'active' => 1 ),
		);
	}

	/** All catalogue entries (falls back to defaults if never saved). */
	public static function all() {
		$p = get_option( self::OPTION, null );
		return ( is_array( $p ) && ! empty( $p ) ) ? $p : self::defaults();
	}

	/** Only the active entries, in order — what the form offers. */
	public static function active() {
		return array_values( array_filter( self::all(), function ( $x ) {
			return ! empty( $x['active'] );
		} ) );
	}

	public static function get( $key ) {
		foreach ( self::all() as $p ) {
			if ( $p['key'] === $key ) {
				return $p;
			}
		}
		return null;
	}

	public static function save( array $parts ) {
		update_option( self::OPTION, array_values( $parts ) );
	}

	/** Seed the option once on activation so the admin can see/edit them. */
	public static function seed() {
		if ( get_option( self::OPTION, null ) === null ) {
			add_option( self::OPTION, self::defaults() );
		}
	}
}
