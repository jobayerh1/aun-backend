<?php
/**
 * Context for the app's smart home-screen greeting: live weather, Bangladesh
 * public holidays and the weekend flag. Served inside /config; the app's
 * rule engine turns it into projector-flavoured messages.
 *
 * Sources (both free, keyless, cached):
 *  - weather:  Open-Meteo current conditions for Dhaka (Bangladesh is small
 *    enough that Dhaka's sky matches the national mood; no location
 *    permission needed on the phone). Cached 30 min.
 *  - holidays: Nager.Date public-holiday calendar for BD. Cached 24 h.
 *
 * Everything degrades to '' / null silently — the greeting then falls back to
 * its plain time-of-day lines, exactly as before.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Greeting {

	// Dhaka. Filterable for a future per-region setting.
	const LAT = 23.8103;
	const LON = 90.4125;

	const WEATHER_CACHE = 'aun_app_weather';
	const HOLIDAY_CACHE = 'aun_app_holidays_'; // + year

	/**
	 * Simplify an Open-Meteo WMO weather code into the buckets the app's
	 * message engine understands.
	 *
	 * @param int $code WMO code.
	 * @return string clear|cloudy|fog|rain|storm
	 */
	public static function simplify_code( $code ) {
		$code = (int) $code;
		if ( $code >= 95 ) {
			return 'storm';
		}
		if ( ( $code >= 51 && $code <= 67 ) || ( $code >= 80 && $code <= 82 ) || ( $code >= 71 && $code <= 77 ) ) {
			return 'rain';
		}
		if ( 45 === $code || 48 === $code ) {
			return 'fog';
		}
		if ( $code >= 1 && $code <= 3 ) {
			return 'cloudy';
		}
		return 'clear';
	}

	/**
	 * Current Dhaka weather: {condition, temp_c} or null when unavailable.
	 *
	 * @return array|null
	 */
	public static function weather() {
		$cached = get_transient( self::WEATHER_CACHE );
		if ( is_array( $cached ) ) {
			return empty( $cached['__miss'] ) ? $cached : null;
		}

		$url = add_query_arg( array(
			'latitude'        => self::LAT,
			'longitude'       => self::LON,
			'current_weather' => 'true',
			'timezone'        => 'Asia/Dhaka',
		), 'https://api.open-meteo.com/v1/forecast' );

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::WEATHER_CACHE, array( '__miss' => 1 ), 10 * MINUTE_IN_SECONDS );
			return null;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$current = is_array( $body ) ? ( $body['current_weather'] ?? null ) : null;
		if ( ! is_array( $current ) || ! isset( $current['weathercode'] ) ) {
			set_transient( self::WEATHER_CACHE, array( '__miss' => 1 ), 10 * MINUTE_IN_SECONDS );
			return null;
		}

		$out = array(
			'condition' => self::simplify_code( (int) $current['weathercode'] ),
			'temp_c'    => round( (float) ( $current['temperature'] ?? 0 ) ),
		);
		set_transient( self::WEATHER_CACHE, $out, 30 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Today's Bangladesh public holiday name, or ''.
	 *
	 * @return string
	 */
	public static function holiday_today() {
		$year  = (int) current_time( 'Y' );
		$key   = self::HOLIDAY_CACHE . $year;
		$table = get_transient( $key );

		if ( ! is_array( $table ) ) {
			$response = wp_remote_get(
				'https://date.nager.at/api/v3/PublicHolidays/' . $year . '/BD',
				array( 'timeout' => 10 )
			);
			$table = array();
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $row ) {
					if ( is_array( $row ) && ! empty( $row['date'] ) && ! empty( $row['localName'] ) ) {
						$table[ (string) $row['date'] ] = (string) $row['localName'];
					}
				}
			}
			// Cache even an empty table briefly, a full one for a day.
			set_transient( $key, $table, empty( $table ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
		}

		return (string) ( $table[ current_time( 'Y-m-d' ) ] ?? '' );
	}

	/**
	 * The block served in /config.
	 *
	 * @return array{weather:array|null,holiday:string,is_weekend:bool}
	 */
	public static function context() {
		// Bangladesh weekend: Friday + Saturday.
		$dow = (int) current_time( 'N' ); // 1=Mon … 7=Sun

		return array(
			'weather'    => self::weather(),
			'holiday'    => self::holiday_today(),
			'is_weekend' => in_array( $dow, array( 5, 6 ), true ),
		);
	}
}
