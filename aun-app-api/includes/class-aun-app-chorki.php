<?php
/**
 * Chorki "hot and fresh" → the app's What-to-watch rail, automatically.
 *
 * Local platforms used to be hand-typed in Settings, one line per title, and
 * they went stale the moment nobody remembered to update them. This keeps the
 * Bangladeshi half of the rail current on its own: Chorki publishes a list, we
 * read it every few hours, and the app shows whatever is on it today.
 *
 * ─── The two rules this file is built around ──────────────────────────────
 *
 * 1. **Chorki is the source of truth. TMDB is a bonus, never a gate.**
 *    Chorki's own page carries the title, poster, synopsis, runtime, genre and
 *    director. TMDB frequently has NOTHING for a new Bangladeshi release and
 *    essentially never has Bangladeshi short films — three of the fifteen
 *    titles on the list are shortfilms. If a TMDB match were required, the rail
 *    would go empty for exactly the content we most want to promote. So a pick
 *    is built entirely from Chorki first, and TMDB is layered on top only when
 *    it is confidently the same title.
 *
 * 2. **A wrong match is far worse than no match.**
 *    The list's fourth title is "Rockstar" — a 2026 Bangladeshi musical drama.
 *    Search TMDB for "Rockstar" and the top hit is the 2011 Ranbir Kapoor film.
 *    Take result #1 blindly and a customer taps a Bangladeshi film, sees an
 *    Indian star's poster and cast, then goes to Chorki looking for a film that
 *    is not there. That is not a cosmetic bug; it discredits the whole rail.
 *    See {@see match_tmdb()} for the four gates a candidate has to pass.
 *
 * ─── Where the data comes from ────────────────────────────────────────────
 *
 * The list page is used for ONE thing: the hrefs, which give us order, slug and
 * content kind (`/en/movie/rockstar` → movie). Everything else comes from each
 * title's own page, which carries a schema.org **JSON-LD VideoObject** plus
 * Open Graph tags. That split is deliberate — JSON-LD and og: are contracts a
 * site keeps for Google and Facebook, so they survive redesigns that would
 * destroy any CSS-selector scraping.
 *
 * ⚠️ Chorki's robots.txt disallows `/api/*`. Their JSON API is therefore off
 * limits however tempting it looks; the public HTML pages are not. We also send
 * a User-Agent that says who we are and links back to us, so if Chorki ever
 * wants this stopped or done differently they know who to call. **If they offer
 * a proper feed, replace {@see fetch_list()} and {@see fetch_detail()} and
 * nothing above them changes.**
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Chorki {

	/** Stored picks (stale-while-revalidate), same pattern as the global rail. */
	const STORE_KEY = 'aun_app_chorki_store';

	/**
	 * Bump whenever a field is added to a built row.
	 *
	 * Without it an upgrade is invisible: the store keeps serving rows built by
	 * the OLD code until the TTL lapses. Same trap AUN_App_Watch::SCHEMA exists
	 * for.
	 */
	const SCHEMA = 1;

	const TTL       = 6 * HOUR_IN_SECONDS;
	const BASE      = 'https://www.chorki.net';
	const LIST_URL  = 'https://www.chorki.net/lists/hot-and-fresh';
	const PLATFORM  = 'Chorki';
	const LIMIT     = 5;
	const MAX_LIMIT = 12;
	const TIMEOUT   = 12;

	/**
	 * How alike two titles must be (0–1) before TMDB is allowed to describe a
	 * Chorki film. 0.86 accepts punctuation and spacing differences but rejects
	 * "Meu" against "Meurtres" — short Bangla titles are the dangerous case,
	 * because a loose threshold matches almost anything.
	 */
	const MATCH_MIN = 0.86;

	/** Content kinds Chorki puts in its URLs, mapped to what TMDB calls them. */
	private static function tmdb_type( $kind ) {
		return ( 'series' === $kind ) ? 'tv' : 'movie';
	}

	/**
	 * Chorki's URL kind → the value the app's WatchPick.kind expects.
	 *
	 * Kept separate from {@see tmdb_type()} because they answer different
	 * questions: TMDB has no notion of a short film and must be told 'movie',
	 * while the app can usefully say so.
	 */
	private static function app_kind( $kind ) {
		switch ( $kind ) {
			case 'series':
				return 'series';
			case 'shortfilm':
				return 'short';
			default:
				return 'movie';
		}
	}

	/* ─────────────────────────── settings ─────────────────────────────── */

	public static function enabled() {
		return ! empty( aun_app_api_get_options()['chorki_enabled'] );
	}

	public static function list_url() {
		$url = trim( (string) ( aun_app_api_get_options()['chorki_list_url'] ?? '' ) );
		return '' !== $url ? $url : self::LIST_URL;
	}

	public static function limit() {
		$n = (int) ( aun_app_api_get_options()['chorki_limit'] ?? 0 );
		if ( $n < 1 ) {
			$n = self::LIMIT;
		}
		return min( self::MAX_LIMIT, $n );
	}

	/* ─────────────────────── the swappable fetch layer ─────────────────── */

	/**
	 * One HTTP GET, identifying ourselves honestly.
	 *
	 * @return string Body, or '' on any failure.
	 */
	private static function http_get( $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				// Says who we are and where to complain. A scraper that hides
				// behind a browser UA is a scraper you cannot be asked to stop.
				'user-agent'  => 'AUN-Care-App/1.0 (+https://aun-projector.com.bd)',
				'headers'     => array( 'Accept' => 'text/html,application/xhtml+xml' ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * The list page → an ordered list of {kind, path}.
	 *
	 * ⚠️ Only hrefs are read, and that is the whole point. Titles, posters and
	 * dates all live on the detail pages inside structured data; pulling them
	 * out of the list markup would mean CSS selectors against a Next.js build,
	 * which change with every deploy. A URL shape is the most stable thing a
	 * content site has.
	 *
	 * A regex rather than DOMDocument on purpose: this matches one very
	 * specific attribute shape, and loading 430 KB of markup into libxml (with
	 * its warning noise) to read href attributes buys nothing.
	 *
	 * @return array[] {kind, path}, de-duplicated, in document order.
	 */
	public static function fetch_list( $url = '', $limit = 0 ) {
		$html = self::http_get( '' !== $url ? $url : self::list_url() );
		if ( '' === $html ) {
			return array();
		}
		$limit = $limit > 0 ? $limit : self::limit();

		preg_match_all(
			'#href="(/(?:[a-z]{2}/)?(movie|series|shortfilm)/[^"?\#]+)"#i',
			$html,
			$m,
			PREG_SET_ORDER
		);

		$out  = array();
		$seen = array();
		foreach ( $m as $hit ) {
			$path = $hit[1];
			$kind = strtolower( $hit[2] );
			// The same title appears more than once (card + hover + carousel),
			// so de-dupe on the slug, keeping the first occurrence's position.
			$slug = strtolower( preg_replace( '#^/(?:[a-z]{2}/)?#', '', $path ) );
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$out[]         = array( 'kind' => $kind, 'path' => $path );
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * One title page → everything the app needs, from structured data only.
	 *
	 * Reads the schema.org JSON-LD VideoObject first and Open Graph second.
	 * Both are maintained for Google and Facebook, which is exactly why they
	 * are safer to depend on than any element in the page body.
	 *
	 * @return array {title, url, poster, overview, year, runtime, genre, director, via}
	 */
	public static function fetch_detail( $path ) {
		$url  = 0 === strpos( $path, 'http' ) ? $path : self::BASE . $path;
		$html = self::http_get( $url );
		if ( '' === $html ) {
			return array();
		}

		$out = array(
			'title'    => '',
			'url'      => $url,
			'poster'   => '',
			'overview' => '',
			'year'     => '',
			'runtime'  => '',
			'genre'    => '',
			'director' => '',
			// Which strategy produced the data — surfaced in the admin
			// diagnostic, so a future breakage says WHICH contract Chorki
			// dropped rather than just "no titles".
			'via'      => '',
		);

		// ── 1. JSON-LD ────────────────────────────────────────────────────
		if ( preg_match_all( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $blocks ) ) {
			foreach ( $blocks[1] as $raw ) {
				$d = json_decode( trim( $raw ), true );
				if ( ! is_array( $d ) ) {
					continue;
				}
				// Some sites wrap everything in an @graph array.
				$nodes = isset( $d['@graph'] ) && is_array( $d['@graph'] ) ? $d['@graph'] : array( $d );
				foreach ( $nodes as $n ) {
					if ( ! is_array( $n ) || empty( $n['name'] ) ) {
						continue;
					}
					$type = strtolower( (string) ( $n['@type'] ?? '' ) );
					if ( false === strpos( $type, 'video' ) && false === strpos( $type, 'movie' )
						&& false === strpos( $type, 'series' ) && false === strpos( $type, 'episode' ) ) {
						continue;
					}
					$out['title']    = self::clean( (string) $n['name'] );
					$out['overview'] = self::clean( (string) ( $n['description'] ?? '' ) );
					$out['genre']    = self::clean( (string) ( $n['genre'] ?? '' ) );
					$out['runtime']  = self::duration( (string) ( $n['duration'] ?? '' ) );
					// ⚠️ uploadDate is when CHORKI published it, not the film's
					// release date. Close enough for a ±1-year sanity check
					// against TMDB, and never shown as a release year on its
					// own — TMDB's year wins whenever a match is made.
					$date = (string) ( $n['uploadDate'] ?? $n['datePublished'] ?? '' );
					if ( '' !== $date ) {
						$out['year'] = substr( $date, 0, 4 );
					}
					if ( ! empty( $n['director'] ) ) {
						$dir = is_array( $n['director'] ) ? ( $n['director'][0] ?? $n['director'] ) : $n['director'];
						$out['director'] = self::clean( (string) ( is_array( $dir ) ? ( $dir['name'] ?? '' ) : $dir ) );
					}
					// The canonical, locale-free URL. Better than the /en/ path
					// we arrived by: the app is Bangla-first, and pinning every
					// customer to the English site would be an odd choice made
					// by accident.
					if ( ! empty( $n['embedUrl'] ) ) {
						$out['url'] = esc_url_raw( (string) $n['embedUrl'] );
					}
					$out['via'] = 'json-ld';
					break 2;
				}
			}
		}

		// ── 2. Open Graph (poster always; the rest only as a fallback) ─────
		// JSON-LD's VideoObject carries no image for the title itself — the
		// only images in it are the director's headshots — so og:image is the
		// poster source even when JSON-LD worked.
		if ( preg_match( '#<meta[^>]+property="og:image"[^>]+content="([^"]+)"#i', $html, $m ) ) {
			$out['poster'] = esc_url_raw( self::clean( $m[1] ) );
		}
		if ( '' === $out['title'] && preg_match( '#<meta[^>]+property="og:title"[^>]+content="([^"]+)"#i', $html, $m ) ) {
			$out['title'] = self::clean( $m[1] );
			$out['via']   = 'open-graph';
		}
		if ( '' === $out['overview'] && preg_match( '#<meta[^>]+property="og:description"[^>]+content="([^"]+)"#i', $html, $m ) ) {
			$out['overview'] = self::clean( $m[1] );
		}

		// Last resort: the slug. An un-named card is worse than a de-slugified
		// one, and this at least gets "Tomar Jonno Mon" out of the URL.
		if ( '' === $out['title'] ) {
			$slug          = preg_replace( '#^.*/#', '', rtrim( $path, '/' ) );
			$out['title']  = ucwords( str_replace( '-', ' ', $slug ) );
			$out['via']    = 'slug';
		}

		return $out;
	}

	/** Decode entities, collapse whitespace. JSON-LD arrives with &apos; in it. */
	private static function clean( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	/**
	 * ISO-8601 duration ("P0DT2H19M41S") → "2h 19m".
	 *
	 * Formatted to match AUN_App_Watch::title_detail() exactly, so a Chorki row
	 * and a TMDB row cannot be told apart in the app.
	 */
	private static function duration( $iso ) {
		if ( '' === $iso || ! preg_match( '#^P#i', $iso ) ) {
			return '';
		}
		try {
			$d = new DateInterval( $iso );
		} catch ( Exception $e ) {
			return '';
		}
		$mins = ( (int) $d->d * 24 * 60 ) + ( (int) $d->h * 60 ) + (int) $d->i;
		if ( $mins <= 0 ) {
			return '';
		}
		$h = intdiv( $mins, 60 );
		$m = $mins % 60;
		return $h > 0 ? ( $m > 0 ? "{$h}h {$m}m" : "{$h}h" ) : "{$m}m";
	}

	/* ──────────────────────────── the matcher ─────────────────────────── */

	/** Lowercase, strip punctuation, collapse spaces — for comparison only. */
	private static function normalize( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	/** 0–1 similarity. */
	private static function similarity( $a, $b ) {
		$a = self::normalize( $a );
		$b = self::normalize( $b );
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		if ( $a === $b ) {
			return 1.0;
		}
		similar_text( $a, $b, $pct );
		return $pct / 100;
	}

	/**
	 * Find this Chorki title on TMDB — or, much more often, correctly decline.
	 *
	 * Four gates, all of which must pass:
	 *
	 *   1. **Not a short film.** TMDB has effectively no Bangladeshi shorts, so
	 *      every search is a chance to match something else that shares a
	 *      common two-word name. There is no upside to trying.
	 *   2. **Bangladeshi.** `original_language` is `bn`, or (series only, since
	 *      TMDB's movie search results carry no country at all)
	 *      `origin_country` contains BD. **This one gate is what kills the
	 *      Rockstar collision**: the 2011 Indian film is `hi`, the American one
	 *      `en`.
	 *   3. **The title actually matches** — against TMDB's title AND its
	 *      original title, because Bangladeshi entries are commonly filed with
	 *      a Bangla-script original and a transliterated display title, and
	 *      Chorki gives us the transliteration.
	 *   4. **The year is plausible** (±1). Chorki's date is an upload date, so
	 *      this is a sanity check, not an assertion.
	 *
	 * @return string "movie:123" / "tv:456" for hydrate_local(), or '' — and ''
	 *                is a perfectly good outcome, not a failure.
	 */
	public static function match_tmdb( $title, $kind, $year = '' ) {
		if ( 'shortfilm' === $kind || '' === trim( (string) $title ) ) {
			return '';
		}
		$type = self::tmdb_type( $kind );

		// Typed search, not /search/multi: the typed endpoints return the
		// original_language field the country gate depends on.
		$res = AUN_App_Watch::api(
			'/search/' . $type,
			array( 'query' => $title, 'include_adult' => 'false' )
		);

		return self::pick_match( $title, $type, $year, (array) ( $res['results'] ?? array() ) );
	}

	/**
	 * The gates themselves, over candidate rows that are already in hand.
	 *
	 * Split out from {@see match_tmdb()} so the decision can be tested without
	 * a TMDB key or a network — this is the part that must not regress, and
	 * "did it reject Bollywood Rockstar" is a question a test should be able to
	 * ask offline.
	 *
	 * @param string $title   The Chorki title.
	 * @param string $type    'movie' | 'tv'.
	 * @param string|int $year Chorki's (upload) year, or '' if unknown.
	 * @param array  $results TMDB search result rows.
	 * @return string "movie:123" / "tv:456", or ''.
	 */
	public static function pick_match( $title, $type, $year, $results ) {
		foreach ( $results as $r ) {
			$id = (int) ( $r['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}

			// Gate 2 — Bangladeshi, or nothing.
			$lang      = strtolower( (string) ( $r['original_language'] ?? '' ) );
			$countries = array_map( 'strtoupper', (array) ( $r['origin_country'] ?? array() ) );
			if ( 'bn' !== $lang && ! in_array( 'BD', $countries, true ) ) {
				continue;
			}

			// Gate 3 — the name.
			$cand_title = (string) ( $r['title'] ?? $r['name'] ?? '' );
			$cand_orig  = (string) ( $r['original_title'] ?? $r['original_name'] ?? '' );
			$score      = max( self::similarity( $title, $cand_title ), self::similarity( $title, $cand_orig ) );
			if ( $score < self::MATCH_MIN ) {
				continue;
			}

			// Gate 4 — the year, when both sides know one.
			$date      = (string) ( $r['release_date'] ?? $r['first_air_date'] ?? '' );
			$cand_year = '' !== $date ? (int) substr( $date, 0, 4 ) : 0;
			if ( $year && $cand_year && abs( (int) $year - $cand_year ) > 1 ) {
				continue;
			}

			return $type . ':' . $id;
		}

		return '';
	}

	/* ─────────────────────────── assembly ─────────────────────────────── */

	/**
	 * Build the rows. Slow (one HTTP call per title, plus TMDB) — cron only.
	 *
	 * @param array|null $log Filled with per-title notes for the diagnostic.
	 * @return array[] Rows in AUN_App_Watch pick shape.
	 */
	public static function build( &$log = null ) {
		$log   = array();
		$items = self::fetch_list();
		if ( empty( $items ) ) {
			return array();
		}

		$out = array();
		foreach ( $items as $item ) {
			$d = self::fetch_detail( $item['path'] );
			if ( empty( $d ) || '' === $d['title'] ) {
				$log[] = array( 'path' => $item['path'], 'title' => '', 'matched' => '', 'note' => 'detail fetch failed' );
				continue;
			}

			$row = array(
				'title'    => $d['title'],
				'platform' => self::PLATFORM,
				'url'      => $d['url'],
				'poster'   => $d['poster'],
				'local'    => true,
				// Chorki's own values. Rule 1: these stand on their own, and a
				// TMDB match only enriches them below.
				'overview' => $d['overview'],
				'backdrop' => '',
				'year'     => $d['year'],
				'rating'   => 0,
				// Chorki's three content kinds, carried through as-is.
				//
				// ⚠️ 'short' is FORWARD-COMPATIBLE on purpose, and that is what makes
				// this safe to deploy without an app release. Every build already in
				// customers' hands renders this field as
				//     kind == 'series' ? Series : Movie
				// so a short film shows "Movie" on those exactly as it does today —
				// no breakage, no blank chip. When the app learns the third value it
				// starts saying "Short film" on its own, with no server change.
				'kind'     => self::app_kind( $item['kind'] ),
				// Chorki's genre arrives as one loose string ("Musical Drama
				// Romance"). Kept whole rather than split on spaces: "Sci-Fi
				// Thriller" and "Musical Drama" do not divide the same way, and
				// one honest chip beats three invented ones.
				'genres'   => '' !== $d['genre'] ? array( $d['genre'] ) : array(),
				'runtime'  => $d['runtime'],
				'tagline'  => '',
				'cast'     => array(),
				'trailer'  => '',
			);

			$link = self::match_tmdb( $d['title'], $item['kind'], $d['year'] );
			if ( '' !== $link ) {
				// hydrate_local() already protects what matters: the admin's —
				// here Chorki's — title, platform, URL and poster always win.
				// Only the enrichment fields (cast, trailer, rating, backdrop,
				// proper genres) are taken from TMDB.
				$row = AUN_App_Watch::hydrate_local( $row, $link );
			}

			$log[] = array(
				'path'    => $item['path'],
				'title'   => $d['title'],
				'matched' => $link,
				'via'     => $d['via'],
				'note'    => '' !== $link ? 'TMDB matched' : ( 'shortfilm' === $item['kind'] ? 'short film — TMDB skipped' : 'no confident TMDB match (Chorki data used)' ),
			);
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * The rows for the app. **Never builds on a customer's request.**
	 *
	 * Building means five-plus HTTP round trips to chorki.net plus TMDB
	 * lookups — many seconds. The global rail can afford a small inline first
	 * fill because TMDB is one fast host; this cannot. A cold store therefore
	 * returns nothing and queues a background build: the rail is briefly one
	 * section short, which nobody notices, instead of the app hanging, which
	 * everybody does.
	 */
	public static function picks( $force = false ) {
		if ( ! self::enabled() ) {
			return array();
		}

		$store = get_option( self::STORE_KEY );
		$have  = is_array( $store )
			&& isset( $store['picks'] ) && is_array( $store['picks'] )
			&& (int) ( $store['v'] ?? 0 ) === self::SCHEMA;

		if ( $force ) {
			$fresh = self::build();
			if ( ! empty( $fresh ) ) {
				self::store( $fresh );
				return $fresh;
			}
			// A failed rebuild keeps whatever we had. Chorki being briefly
			// unreachable must never empty the rail.
			return $have ? $store['picks'] : array();
		}

		if ( $have ) {
			if ( ( time() - (int) ( $store['at'] ?? 0 ) ) > self::TTL ) {
				self::schedule_refresh();
			}
			return $store['picks'];
		}

		self::schedule_refresh();
		return array();
	}

	private static function store( $picks ) {
		update_option(
			self::STORE_KEY,
			array(
				'v'     => self::SCHEMA,
				'at'    => time(),
				'picks' => array_values( (array) $picks ),
			),
			false // never autoload
		);
	}

	private static function schedule_refresh() {
		if ( ! wp_next_scheduled( 'aun_app_chorki_refresh' ) ) {
			wp_schedule_single_event( time() + 5, 'aun_app_chorki_refresh' );
		}
	}

	/** Cron worker. Only overwrites on success. */
	public static function refresh_cron() {
		if ( ! self::enabled() ) {
			return;
		}
		$fresh = self::build();
		if ( ! empty( $fresh ) ) {
			self::store( $fresh );
		}
	}

	/**
	 * Live check for the admin screen: what was found, and what matched.
	 *
	 * Reports the extraction strategy per title, so a future Chorki redesign
	 * shows up as "via: slug" on every row — a readable symptom — instead of
	 * silently degraded cards nobody looks at.
	 */
	public static function diagnostic() {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'message' => 'Chorki auto-picks are switched off.', 'rows' => array() );
		}

		$started = microtime( true );
		$log     = array();
		$rows    = self::build( $log );
		$secs    = round( microtime( true ) - $started, 1 );

		if ( empty( $rows ) ) {
			return array(
				'ok'      => false,
				'rows'    => $log,
				'message' => sprintf(
					'Nothing came back from %s (%.1fs). Chorki may be unreachable, or the page layout changed — the app is still showing the last good list.',
					self::list_url(),
					$secs
				),
			);
		}

		$matched = 0;
		foreach ( $log as $l ) {
			if ( ! empty( $l['matched'] ) ) {
				$matched++;
			}
		}

		return array(
			'ok'      => true,
			'rows'    => $log,
			'message' => sprintf(
				'Working. %d titles pulled from Chorki in %.1fs; %d enriched with TMDB details. Titles without a TMDB match still show, using Chorki\'s own synopsis and poster.',
				count( $rows ),
				$secs,
				$matched
			),
		);
	}
}
