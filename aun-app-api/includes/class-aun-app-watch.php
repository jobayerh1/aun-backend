<?php
/**
 * "What to watch tonight" brain for the app home screen.
 *
 * Two sources, merged:
 *  1. GLOBAL — TMDB weekly trending (movies + TV) with REAL per-title
 *     streaming-provider detection for region BD via TMDB's watch/providers
 *     data, so a pick can say "Netflix" and deep-link into it. Needs a free
 *     TMDB API key (Settings). Cached 12 h — a handful of requests twice a
 *     day, nothing per-customer.
 *  2. LOCAL — Chorki/Bioscope/Hoichoi etc. have no public APIs, so local
 *     picks are admin-curated in Settings (one line each). The admin updates
 *     them when something big drops; the app shows them first.
 *
 * Nothing is hardcoded: no key → global section simply absent; empty
 * textarea → local section absent; both empty → the app hides the card.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Watch {

	const CACHE_KEY = 'aun_app_watch_picks';

	/** Persistent store for the expensive global picks (stale-while-revalidate). */
	const STORE_KEY = 'aun_app_watch_store';

	/**
	 * Shape of the stored payload. **Bump this whenever a field is added to a
	 * pick** (genres, runtime, cast… were added in 1.33.0).
	 *
	 * ⚠️ Without it, an upgrade is invisible to customers: the stale-while-
	 * revalidate store keeps serving rows built by the OLD code — with none of
	 * the new fields — until the 12-hour TTL lapses AND a background cron
	 * actually runs. A version stamp makes an upgrade self-healing instead.
	 */
	const SCHEMA = 2;

	/** Titles fetched on the very first call, so a rail appears immediately. */
	const FIRST_FILL = 6;
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/** Default number of global titles served when the admin hasn't set one. */
	const GLOBAL_LIMIT = 30;

	/** Hard ceiling for the admin setting (each title costs 2 TMDB lookups). */
	const MAX_LIMIT = 100;

	/** How many trending pages to walk (TMDB serves 20 per page). */
	const MAX_PAGES = 8;

	const TMDB_BASE     = 'https://api.themoviedb.org/3';
	const POSTER_BASE   = 'https://image.tmdb.org/t/p/w342';
	const BACKDROP_BASE = 'https://image.tmdb.org/t/p/w780';
	/** Small square-ish crop — cast headshots are shown at ~56 px in the app. */
	const PROFILE_BASE  = 'https://image.tmdb.org/t/p/w185';

	/** How many cast members to carry to the app. */
	const CAST_LIMIT = 10;

	/**
	 * Map a TMDB provider name to a customer-openable URL for a title. The
	 * search deep link opens the platform's own app when installed.
	 *
	 * @param string $provider Provider name from TMDB (e.g. "Netflix").
	 * @param string $title    Title to search.
	 * @return string '' when the provider has no useful destination.
	 */
	private static function provider_link( $provider, $title ) {
		$q = rawurlencode( $title );
		$p = strtolower( $provider );
		if ( false !== strpos( $p, 'netflix' ) ) {
			return "https://www.netflix.com/search?q=$q";
		}
		if ( false !== strpos( $p, 'amazon' ) || false !== strpos( $p, 'prime' ) ) {
			return "https://www.primevideo.com/search/ref=atv_nb_sr?phrase=$q";
		}
		if ( false !== strpos( $p, 'hoichoi' ) ) {
			return "https://www.hoichoi.tv/search?q=$q";
		}
		if ( false !== strpos( $p, 'disney' ) ) {
			return "https://www.disneyplus.com/search?q=$q";
		}
		if ( false !== strpos( $p, 'apple' ) ) {
			return "https://tv.apple.com/search?term=$q";
		}
		return '';
	}

	/** Is this a TMDB v4 "API Read Access Token" (a JWT), vs a v3 API key? */
	private static function is_v4_token( $key ) {
		return 0 === strpos( (string) $key, 'eyJ' ) && substr_count( (string) $key, '.' ) >= 2;
	}

	/**
	 * Public door to the ONE TMDB client.
	 *
	 * Exists so AUN_App_Chorki can run its own typed searches without growing a
	 * second copy of the key handling — the v3-vs-v4 credential detection below
	 * is exactly the kind of thing that gets fixed in one place and stays broken
	 * in the other.
	 *
	 * @return array Decoded response, or array() on any failure.
	 */
	public static function api( $path, $params = array() ) {
		return self::tmdb( $path, $params );
	}

	/** One TMDB GET, decoded ({} on failure). */
	private static function tmdb( $path, $params = array() ) {
		$key = trim( (string) ( aun_app_api_get_options()['tmdb_api_key'] ?? '' ) );
		if ( '' === $key ) {
			return array();
		}

		$args = array( 'timeout' => 12, 'headers' => array( 'Accept' => 'application/json' ) );
		// TMDB offers TWO credentials on its API settings page: a v3 "API Key"
		// (passed as ?api_key=) and a v4 "API Read Access Token" (a long JWT sent
		// as a Bearer header). Pasting the wrong one silently 401s every call —
		// so support whichever the admin entered.
		if ( self::is_v4_token( $key ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $key;
			$url = self::TMDB_BASE . $path . ( empty( $params ) ? '' : '?' . http_build_query( $params ) );
		} else {
			$url = self::TMDB_BASE . $path . '?' . http_build_query( array_merge( array( 'api_key' => $key ), $params ) );
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}

	/**
	 * Admin diagnostic for the "Test TMDB" button — a live, human-readable
	 * summary of what the key returns, so a blank rail is easy to debug.
	 *
	 * @return array {ok, key_type, trending, picks, message}
	 */
	public static function diagnostic() {
		$key = trim( (string) ( aun_app_api_get_options()['tmdb_api_key'] ?? '' ) );
		if ( '' === $key ) {
			return array( 'ok' => false, 'key_type' => 'none', 'trending' => 0, 'picks' => 0, 'message' => 'No TMDB key set — global picks are off (local picks still show).' );
		}
		$key_type = self::is_v4_token( $key ) ? 'v4 Read Access Token (Bearer)' : 'v3 API Key';
		$trending = self::tmdb( '/trending/all/week' );
		$count    = is_array( $trending['results'] ?? null ) ? count( $trending['results'] ) : 0;
		if ( 0 === $count ) {
			return array(
				'ok'       => false,
				'key_type' => $key_type,
				'trending' => 0,
				'picks'    => 0,
				'message'  => 'TMDB returned nothing for this key. If you pasted the "API Read Access Token", that is fine (we detect it); double-check the key is active on themoviedb.org → Settings → API, and that this server can reach api.themoviedb.org.',
			);
		}
		$picks = self::picks( true ); // force a fresh build
		return array(
			'ok'       => true,
			'key_type' => $key_type,
			'trending' => $count,
			'picks'    => count( (array) $picks ),
			'message'  => sprintf( 'Working. %d trending titles fetched; %d total picks now showing in the app (streamable titles + your local picks).', $count, count( (array) $picks ) ),
		);
	}

	/**
	 * watch/providers "buckets" that mean a title is streaming on an OTT
	 * platform (watchable at home), NOT just in cinemas. flatrate = with a
	 * subscription, free = free, ads = free-with-ads. rent/buy are deliberately
	 * excluded (paid-per-title ≠ "on a streaming platform"). A title showing
	 * ONLY in cinemas has no providers at all, so it never qualifies.
	 */
	const STREAM_BUCKETS = array( 'flatrate', 'free', 'ads' );

	/**
	 * Regions checked (in order) for a recognizable streaming provider. This is
	 * a GLOBAL discovery — we are not limiting to Bangladesh; we only use these
	 * well-covered regions to read a title's platform (Netflix, Prime, Disney+…)
	 * for the label + deep link. Those platforms are global anyway.
	 */
	const PROVIDER_REGIONS = array( 'US', 'GB', 'CA', 'AU', 'IN' );

	/**
	 * Is a trending title streaming on an OTT platform anywhere? Returns the
	 * provider name for the label (preferring the well-covered regions above,
	 * then any region), or '' when it streams nowhere (i.e. cinema-only / not
	 * yet released online) so it can be skipped.
	 *
	 * @param string $type movie|tv.
	 * @param int    $id   TMDB id.
	 * @return string Provider name, or '' if not streaming anywhere.
	 */
	private static function stream_provider( $type, $id ) {
		$providers = self::tmdb( "/$type/" . (int) $id . '/watch/providers' );
		$regions   = (array) ( $providers['results'] ?? array() );

		// Preferred, well-covered regions first (recognizable global platforms).
		foreach ( self::PROVIDER_REGIONS as $region ) {
			$name = self::first_stream_name( $regions[ $region ] ?? array() );
			if ( '' !== $name ) {
				return $name;
			}
		}
		// Otherwise, streaming ANYWHERE still counts as "watchable online".
		foreach ( $regions as $data ) {
			$name = self::first_stream_name( (array) $data );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * A YouTube trailer key for a title, so the app can play it in-app. Prefers
	 * an official Trailer, then any Trailer, then any Teaser — all on YouTube.
	 *
	 * @param string $type movie|tv.
	 * @param int    $id   TMDB id.
	 * @return string YouTube video id, or ''.
	 */
	private static function pick_trailer( $rows ) {
		$best   = '';
		$rank   = 99;
		foreach ( (array) $rows as $v ) {
			if ( ! is_array( $v ) || 'YouTube' !== ( $v['site'] ?? '' ) || empty( $v['key'] ) ) {
				continue;
			}
			$vtype = strtolower( (string) ( $v['type'] ?? '' ) );
			// Lower rank = preferred.
			$r = 'trailer' === $vtype ? ( ! empty( $v['official'] ) ? 0 : 1 ) : ( 'teaser' === $vtype ? 2 : 9 );
			if ( $r < $rank ) {
				$rank = $r;
				$best = (string) $v['key'];
			}
		}
		return $best;
	}

	/**
	 * Everything the in-app detail screen shows about one title: genres, how
	 * long it runs, the top cast, its tagline — and the trailer.
	 *
	 * ONE request (`append_to_response=videos,credits`) replaces the separate
	 * /videos call this used to make, so a richer screen costs the same two
	 * TMDB lookups per served title as before (this + watch/providers).
	 *
	 * @param string $type movie|tv.
	 * @param int    $id   TMDB id.
	 * @return array
	 */
	private static function title_detail( $type, $id ) {
		$d = self::tmdb(
			"/$type/" . (int) $id,
			array( 'append_to_response' => 'videos,credits' )
		);
		if ( ! is_array( $d ) || empty( $d ) ) {
			return array(
				'genres'  => array(),
				'runtime' => '',
				'tagline' => '',
				'cast'    => array(),
				'trailer' => '',
			);
		}

		$genres = array();
		foreach ( (array) ( $d['genres'] ?? array() ) as $g ) {
			$name = (string) ( $g['name'] ?? '' );
			if ( '' !== $name ) {
				$genres[] = $name;
			}
		}

		// Movies have one runtime; series report per-episode minutes plus a
		// season count, which is the more useful number for a series.
		$runtime = '';
		if ( 'movie' === $type ) {
			$mins = (int) ( $d['runtime'] ?? 0 );
			if ( $mins > 0 ) {
				$h       = intdiv( $mins, 60 );
				$m       = $mins % 60;
				$runtime = $h > 0 ? ( $m > 0 ? "{$h}h {$m}m" : "{$h}h" ) : "{$m}m";
			}
		} else {
			$seasons = (int) ( $d['number_of_seasons'] ?? 0 );
			$eps     = (array) ( $d['episode_run_time'] ?? array() );
			$per     = ! empty( $eps ) ? (int) $eps[0] : 0;
			$parts   = array();
			if ( $seasons > 0 ) {
				$parts[] = $seasons . ( 1 === $seasons ? ' season' : ' seasons' );
			}
			if ( $per > 0 ) {
				$parts[] = "{$per}m per episode";
			}
			$runtime = implode( ' · ', $parts );
		}

		$cast = array();
		foreach ( (array) ( $d['credits']['cast'] ?? array() ) as $c ) {
			$name = (string) ( $c['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$cast[] = array(
				'name'      => $name,
				'character' => (string) ( $c['character'] ?? '' ),
				'photo'     => empty( $c['profile_path'] ) ? '' : self::PROFILE_BASE . (string) $c['profile_path'],
			);
			if ( count( $cast ) >= self::CAST_LIMIT ) {
				break;
			}
		}

		return array(
			'genres'  => $genres,
			'runtime' => $runtime,
			'tagline' => (string) ( $d['tagline'] ?? '' ),
			'cast'    => $cast,
			'trailer' => self::pick_trailer( $d['videos']['results'] ?? array() ),
		);
	}

	/** First provider name across the streaming buckets of one region, or ''. */
	private static function first_stream_name( $region_data ) {
		foreach ( self::STREAM_BUCKETS as $bucket ) {
			foreach ( (array) ( $region_data[ $bucket ] ?? array() ) as $prov ) {
				$name = (string) ( $prov['provider_name'] ?? '' );
				if ( '' !== $name ) {
					return $name;
				}
			}
		}
		return '';
	}

	/**
	 * Global picks — this week's trending movies & series worldwide, filtered to
	 * the ones actually streaming on an OTT platform (so a projector customer can
	 * watch them at home). Titles only in cinemas have no streaming providers and
	 * are dropped. NOT limited to Bangladesh — this is a global media discovery;
	 * the platforms surfaced (Netflix, Prime, Disney+…) are available here too.
	 *
	 * @return array[]
	 */
	private static function global_picks( $limit = 0 ) {
		if ( '' === trim( (string) ( aun_app_api_get_options()['tmdb_api_key'] ?? '' ) ) ) {
			return array();
		}

		$limit = $limit > 0 ? (int) $limit : self::global_limit();
		$out   = array();
		// Titles already handled, so a repeat across pages can never cost a
		// second pair of TMDB lookups (or appear twice in the rail).
		$seen = array();

		// TMDB returns 20 titles per page and many are filtered out (no poster,
		// people, cinema-only) — so walk pages until we have enough. Capped so a
		// large limit can never turn the 12-hourly rebuild into a runaway job.
		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$trending = self::tmdb( '/trending/all/week', array( 'page' => $page ) );
			$rows     = (array) ( $trending['results'] ?? array() );
			if ( empty( $rows ) ) {
				break; // no more pages
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['poster_path'] ) ) {
					continue;
				}
				$type = (string) ( $row['media_type'] ?? '' );
				if ( 'movie' !== $type && 'tv' !== $type ) {
					continue; // people/collections aren't watchable
				}
				$title = (string) ( $row['title'] ?? $row['name'] ?? '' );
				if ( '' === $title ) {
					continue;
				}

				$uid = $type . ':' . (int) $row['id'];
				if ( isset( $seen[ $uid ] ) ) {
					continue;
				}
				$seen[ $uid ] = true;

				// Keep only titles streaming on some OTT platform → excludes
				// cinema-only releases.
				$provider = self::stream_provider( $type, (int) $row['id'] );
				if ( '' === $provider ) {
					continue;
				}

				$link = self::provider_link( $provider, $title );
				if ( '' === $link ) {
					// Streaming somewhere, but we don't hand-map that platform's
					// search — send the customer to the title's "where to watch" page.
					$link = 'https://www.themoviedb.org/' . $type . '/' . (int) $row['id'] . '/watch';
				}
				// Enrich for the in-app detail screen. Overview/backdrop/year/
				// rating come free from the trending row; genres, runtime, cast
				// and the trailer come from ONE details call.
				$date   = (string) ( $row['release_date'] ?? $row['first_air_date'] ?? '' );
				$detail = self::title_detail( $type, (int) $row['id'] );
				$out[]  = array(
					'title'    => $title,
					'platform' => $provider,
					'url'      => $link,
					'poster'   => self::POSTER_BASE . (string) $row['poster_path'],
					'local'    => false,
					'overview' => (string) ( $row['overview'] ?? '' ),
					'backdrop' => empty( $row['backdrop_path'] ) ? '' : self::BACKDROP_BASE . (string) $row['backdrop_path'],
					'year'     => '' !== $date ? substr( $date, 0, 4 ) : '',
					'rating'   => isset( $row['vote_average'] ) ? round( (float) $row['vote_average'], 1 ) : 0,
					'kind'     => 'tv' === $type ? 'series' : 'movie',
					'genres'   => $detail['genres'],
					'runtime'  => $detail['runtime'],
					'tagline'  => $detail['tagline'],
					'cast'     => $detail['cast'],
					'trailer'  => $detail['trailer'],
				);
				if ( count( $out ) >= $limit ) {
					return $out; // only pay for as many lookups as we serve
				}
			}
		}
		return $out;
	}

	/**
	 * How many global titles to serve. Admin-set (AUN App → Settings → What to
	 * Watch); falls back to GLOBAL_LIMIT. Clamped to MAX_LIMIT because every
	 * extra title costs two TMDB lookups (providers + trailer) when the 12-hour
	 * cache rebuilds.
	 *
	 * @return int
	 */
	public static function global_limit() {
		$n = (int) ( aun_app_api_get_options()['watch_limit'] ?? 0 );
		if ( $n <= 0 ) {
			$n = self::GLOBAL_LIMIT;
		}
		return max( 1, min( self::MAX_LIMIT, $n ) );
	}

	/**
	 * Admin-curated local picks. One per line (written by the Settings form):
	 *   Title | Platform | URL | poster URL | end date (YYYY-MM-DD)
	 *
	 * Poster and end date are optional. A pick past its end date is skipped, so
	 * a "showing now" recommendation drops off on its own with no admin action.
	 * Older 3–4 column lines still parse (no end date = never expires).
	 *
	 * @return array[]
	 */
	/** How long one linked title's TMDB metadata is kept. */
	const LOCAL_META_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Search TMDB by title, for the admin's "find this film" box.
	 *
	 * Exists so nobody has to leave WordPress, open themoviedb.org, find the
	 * film and copy a number out of its URL — a five-step errand where the only
	 * hard part is not mistyping the id. `include_adult` is off, and the query
	 * is not restricted by language: Bangladeshi titles are often filed under
	 * their English name.
	 *
	 * @return array[] {id, type, title, year, poster, overview}
	 */
	public static function search( $query, $limit = 8 ) {
		$query = trim( (string) $query );
		if ( '' === $query ) {
			return array();
		}

		$res = self::tmdb(
			'/search/multi',
			array( 'query' => $query, 'include_adult' => 'false' )
		);

		$out = array();
		foreach ( (array) ( $res['results'] ?? array() ) as $r ) {
			$type = (string) ( $r['media_type'] ?? '' );
			// People come back from /search/multi too, and a director is not
			// something you can put on the Home rail.
			if ( 'movie' !== $type && 'tv' !== $type ) {
				continue;
			}
			$date  = (string) ( $r['release_date'] ?? $r['first_air_date'] ?? '' );
			$out[] = array(
				'id'       => (int) ( $r['id'] ?? 0 ),
				'type'     => $type,
				'title'    => (string) ( $r['title'] ?? $r['name'] ?? '' ),
				// The original title disambiguates the remakes and the
				// same-name films that make picking the right row hard.
				'original' => (string) ( $r['original_title'] ?? $r['original_name'] ?? '' ),
				'year'     => '' !== $date ? substr( $date, 0, 4 ) : '',
				'poster'   => empty( $r['poster_path'] ) ? '' : self::POSTER_BASE . (string) $r['poster_path'],
				'overview' => (string) ( $r['overview'] ?? '' ),
			);
			if ( count( $out ) >= (int) $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Fill an admin-curated pick with the TMDB metadata for a linked title.
	 *
	 * @param array  $row  The curated row (platform + url are the admin's).
	 * @param string $link "movie:1044789" or "tv:12345"; a bare number = movie.
	 */
	public static function hydrate_local( $row, $link ) {
		if ( ! preg_match( '/^(?:(movie|tv):)?(\d+)$/i', $link, $m ) ) {
			return $row;
		}
		$type = strtolower( $m[1] ?: 'movie' );
		$id   = (int) $m[2];

		// ⚠️ local_picks() is deliberately NEVER cached — end dates have to take
		// effect the moment they pass. So the CACHE LIVES HERE, per title:
		// without it every single app launch would spend a TMDB round trip per
		// curated pick, on the customer's own request.
		$key  = 'aun_app_watch_meta_' . $type . '_' . $id;
		$meta = get_transient( $key );

		if ( ! is_array( $meta ) ) {
			$d = self::tmdb( "/$type/$id", array( 'append_to_response' => 'videos,credits' ) );
			if ( ! is_array( $d ) || empty( $d['id'] ) ) {
				// A dead id, a missing API key, TMDB down — the pick still works
				// as the poster-and-link it was before. Cached briefly so a
				// broken id cannot retry on every launch.
				set_transient( $key, array( 'ok' => false ), HOUR_IN_SECONDS );
				return $row;
			}

			$detail = self::title_detail( $type, $id );
			$date   = (string) ( $d['release_date'] ?? $d['first_air_date'] ?? '' );

			$meta = array(
				'ok'       => true,
				'title'    => (string) ( $d['title'] ?? $d['name'] ?? '' ),
				'overview' => (string) ( $d['overview'] ?? '' ),
				'poster'   => empty( $d['poster_path'] ) ? '' : self::POSTER_BASE . (string) $d['poster_path'],
				'backdrop' => empty( $d['backdrop_path'] ) ? '' : self::BACKDROP_BASE . (string) $d['backdrop_path'],
				'year'     => '' !== $date ? substr( $date, 0, 4 ) : '',
				'rating'   => round( (float) ( $d['vote_average'] ?? 0 ), 1 ),
				'kind'     => ( 'tv' === $type ) ? 'series' : 'movie',
				'genres'   => $detail['genres'],
				'runtime'  => $detail['runtime'],
				'tagline'  => $detail['tagline'],
				'cast'     => $detail['cast'],
				'trailer'  => $detail['trailer'],
			);
			set_transient( $key, $meta, self::LOCAL_META_TTL );
		}

		if ( empty( $meta['ok'] ) ) {
			return $row;
		}

		foreach ( array( 'overview', 'backdrop', 'year', 'rating', 'kind', 'genres', 'runtime', 'tagline', 'cast', 'trailer' ) as $f ) {
			$row[ $f ] = $meta[ $f ];
		}

		// ⚠️ The admin's own title, platform and URL always win. TMDB knows the
		// film; it does not know that WE are pointing people at Chorki, and an
		// English TMDB title must not replace a Bangla one the admin typed.
		// Their poster wins too when they set one — a local promo still beats a
		// generic key art.
		if ( '' === $row['poster'] ) {
			$row['poster'] = (string) $meta['poster'];
		}
		if ( '' === trim( (string) $row['title'] ) ) {
			$row['title'] = (string) $meta['title'];
		}

		return $row;
	}

	/**
	 * Drop the cached metadata for every linked title.
	 *
	 * Called when the picks are saved, so correcting a wrong id shows the right
	 * film immediately instead of a week later.
	 */
	public static function flush_local_meta() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_aun_app_watch_meta_%'
			    OR option_name LIKE '_transient_timeout_aun_app_watch_meta_%'"
		);
	}

	private static function local_picks() {
		$raw   = (string) ( aun_app_api_get_options()['watch_local_picks'] ?? '' );
		$today = current_time( 'Y-m-d' );
		$out   = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 3 || '' === $parts[0] || '' === $parts[2] ) {
				continue;
			}
			// Auto-expire: skip once the end date has passed (site timezone).
			$end = isset( $parts[4] ) ? trim( $parts[4] ) : '';
			if ( '' !== $end && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) && $end < $today ) {
				continue;
			}
			$row = array(
				'title'    => $parts[0],
				'platform' => $parts[1],
				'url'      => esc_url_raw( $parts[2] ),
				'poster'   => isset( $parts[3] ) && '' !== $parts[3] ? esc_url_raw( $parts[3] ) : '',
				'local'    => true,
				// Filled in below when the admin linked a TMDB title. Without
				// one the pick is a poster and a link, and the app opens it.
				'overview' => '',
				'backdrop' => '',
				'year'     => '',
				'rating'   => 0,
				'kind'     => '',
				'genres'   => array(),
				'runtime'  => '',
				'tagline'  => '',
				'cast'     => array(),
				'trailer'  => '',
			);

			// The 6th field links this pick to a TMDB title ("movie:1044789").
			// Bangladeshi cinema IS on TMDB — Hawa, Poran, Surongo and the rest
			// — it simply is not in the trending feed, which is why these are
			// curated. Linking one gives a Chorki or Bioscope title exactly the
			// same screen a global pick gets: synopsis, cast, trailer, runtime.
			$link = isset( $parts[5] ) ? trim( $parts[5] ) : '';
			if ( '' !== $link ) {
				$row = self::hydrate_local( $row, $link );
			}

			$out[] = $row;
		}
		return $out;
	}

	/**
	 * The merged feed (local first — Chorki/Bioscope deserve the spotlight).
	 *
	 * @param bool $force Skip the cache (admin save).
	 * @return array[]
	 */
	public static function picks( $force = false ) {
		// Local picks are cheap (an option parse) and must honour their end
		// dates immediately, so they're never cached.
		$local = self::local_picks();

		// Chorki's own "hot and fresh", pulled automatically. Served from its own
		// store and NEVER built inline (see AUN_App_Chorki::picks()), so a slow
		// chorki.net cannot delay this response.
		//
		// ⚠️ Ordered AFTER the hand-curated picks deliberately: when the admin has
		// pinned something for a campaign, that is a decision, and an automatic
		// feed must not push it down the rail.
		$local = array_merge( $local, AUN_App_Chorki::picks( $force ) );

		if ( $force ) {
			$global = self::global_picks();
			self::store_global( $global );
			return array_merge( $local, $global );
		}

		$store = get_option( self::STORE_KEY );
		$have  = is_array( $store )
			&& isset( $store['picks'] )
			&& is_array( $store['picks'] )
			// Rows written by an older version of this class are missing the
			// fields the app now shows — rebuild rather than serve them.
			&& (int) ( $store['v'] ?? 1 ) === self::SCHEMA;
		$age   = $have ? ( time() - (int) ( $store['at'] ?? 0 ) ) : PHP_INT_MAX;

		// STALE-WHILE-REVALIDATE. Building the global list costs ~2 TMDB calls
		// PER TITLE (providers + trailer) plus the trending pages — a minute or
		// more. That must NEVER happen inside a customer's request: the app
		// times out and the rail looks broken. So we always serve what we have
		// and refresh in the background.
		if ( $have ) {
			if ( $age > self::CACHE_TTL ) {
				self::schedule_refresh();
			}
			return array_merge( $local, $store['picks'] );
		}

		// Nothing stored at all (first ever call). Build a SMALL batch so the
		// customer still sees a rail quickly, then fill the rest in background.
		$quick = self::global_picks( self::FIRST_FILL );
		self::store_global( $quick );
		self::schedule_refresh();
		return array_merge( $local, $quick );
	}

	/** Persist the expensive global picks with a timestamp. */
	private static function store_global( $picks ) {
		update_option(
			self::STORE_KEY,
			array(
				'v'     => self::SCHEMA,
				'at'    => time(),
				'picks' => array_values( (array) $picks ),
			),
			false // never autoload — it can be a big array
		);
	}

	/** Queue one background rebuild (deduped). */
	private static function schedule_refresh() {
		if ( ! wp_next_scheduled( 'aun_app_watch_refresh' ) ) {
			wp_schedule_single_event( time() + 5, 'aun_app_watch_refresh' );
		}
	}

	/**
	 * Cron worker: rebuild the global picks off the request path. Also runs on a
	 * schedule so the store is normally warm before anyone asks.
	 */
	public static function refresh_cron() {
		$global = self::global_picks();
		// Only overwrite on success — a TMDB hiccup must not empty the rail.
		if ( ! empty( $global ) ) {
			self::store_global( $global );
		}
	}
}
