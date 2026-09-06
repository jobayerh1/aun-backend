<?php
/**
 * Which models have a dust filter, and where it is.
 *
 * Three facts live here, and every one of them is per MODEL, not per app build:
 *
 *  - `back`            — the filter comes out of the rear panel;
 *  - `bottom`          — a wide tray in the underside, pulled out sideways;
 *  - `bottom_vertical` — a tall card in the underside, drawn straight out;
 *  - `none`            — no user-serviceable filter at all.
 *
 * ⚠️ The two `bottom` values are not the same setting with a different picture.
 * Which way a part comes out IS the instruction: a customer pulling a card
 * sideways when it seats front-to-back will feel it stick, decide it is clipped
 * in, and force it.
 *
 * ⚠️ `none` is not cosmetic. It suppresses the maintenance reminders entirely
 * for that model. Telling someone to clean a filter their projector does not
 * have is worse than saying nothing: they go looking, find no filter, and the
 * next reminder we send — about a warranty, a repair, anything — arrives at a
 * customer who has already learnt that our messages are not about their
 * machine. One wrong reminder devalues all of them.
 *
 * ⚠️ Keyed on the NORMALISED model name, not on a product id. Devices arrive
 * from the ERP carrying a typed model string ("A005 pro", "AUN-A005-Pro"), and
 * they have to resolve to the same setting as the WooCommerce product. This
 * reuses AUN_App_Projectors::normalise_model() rather than inventing a second
 * spelling rule — two normalisers in one codebase will disagree, and the day
 * they do, a customer gets the wrong picture or a reminder they should not.
 *
 * @package AUN_App_API
 */

defined( 'ABSPATH' ) || exit;

class AUN_App_Filters {

	/** Option holding the map: normalised model => one of CHOICES. */
	const OPTION = 'aun_app_filter_map';

	/** Valid values, in the order the admin select shows them. */
	const CHOICES = array(
		'back'            => 'Back panel',
		'bottom'          => 'Underside — wide tray, pulls out sideways',
		'bottom_vertical' => 'Underside — tall card, pulls straight out',
		'none'            => 'No dust filter (no reminders)',
	);

	/**
	 * The whole map.
	 *
	 * @return array<string,string>
	 */
	public static function map() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Replace the map. Values outside CHOICES are dropped rather than stored,
	 * so a bad POST cannot put a value in here that the app has no picture for.
	 *
	 * @param array $map normalised model => choice.
	 */
	public static function save( array $map ) {
		$clean = array();
		foreach ( $map as $model => $choice ) {
			$model = self::key( $model );
			if ( '' === $model || ! isset( self::CHOICES[ $choice ] ) ) {
				continue;
			}
			$clean[ $model ] = $choice;
		}
		update_option( self::OPTION, $clean, false );
	}

	/**
	 * Normalise a model name into the map's key.
	 *
	 * @param string $model Raw model name.
	 * @return string
	 */
	public static function key( $model ) {
		if ( class_exists( 'AUN_App_Projectors' ) ) {
			return AUN_App_Projectors::normalise_model( $model );
		}
		// Only reachable if the planner class is somehow absent; keeps the
		// admin page usable rather than fataling.
		return trim( strtolower( preg_replace( '/[^a-z0-9 ]/i', '', (string) $model ) ) );
	}

	/**
	 * Where this model's filter is.
	 *
	 * @param string $model Model name as the device reports it.
	 * @return string one of CHOICES, or 'unknown' when not configured
	 */
	public static function location( $model ) {
		$key = self::key( $model );
		if ( '' === $key ) {
			return 'unknown';
		}
		$map = self::map();
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		// Not configured. ⚠️ Deliberately 'unknown' and NOT a guess: the app
		// draws a projector without pointing at a face and says "check the back
		// or the underside", which is honest. Defaulting to 'back' would be
		// right about half the range and confidently wrong about the rest.
		return 'unknown';
	}

	/**
	 * Should this model get dust-filter reminders at all?
	 *
	 * ⚠️ Unconfigured models DO get them. The reminder predates this setting
	 * and every model in the range that has a filter needs it; going silent for
	 * anything an admin has not touched yet would quietly switch off a live
	 * feature for the whole catalogue the moment this ships.
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	public static function reminders_enabled( $model ) {
		return 'none' !== self::location( $model );
	}
}
