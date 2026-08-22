<?php
/**
 * Bench tests for AUN_App_Chorki (app-api 1.99.0).
 *
 * Run:  php test-chorki.php
 *
 * Deliberately standalone — no WordPress needed. The parsers and the matcher
 * are the parts that must not regress, and none of them touch WP except through
 * http_get(), which the fixtures replace by feeding saved HTML straight in.
 *
 * ⚠️ Reaching the last line IS the assertion. A regression fatals or prints
 * FAIL rather than quietly passing.
 */

// ── Minimal WP surface the class file expects at parse time ────────────────
define( 'WPINC', 1 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['aun_opts'] = array(
	'chorki_enabled'  => 1,
	'chorki_limit'    => 5,
	'chorki_list_url' => '',
);
function aun_app_api_get_options() {
	return $GLOBALS['aun_opts'];
}
function esc_url_raw( $u ) {
	return $u;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['fetched'][] = $url;
	// A queue when one is set (build() fetches the list, then a page per title),
	// otherwise the single fixture.
	if ( ! empty( $GLOBALS['fixture_seq'] ) ) {
		return array( 'body' => array_shift( $GLOBALS['fixture_seq'] ), 'code' => 200 );
	}
	return array( 'body' => $GLOBALS['fixture_html'] ?? '', 'code' => 200 );
}
function is_wp_error( $x ) {
	return false;
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function get_option( $k, $d = false ) {
	return $GLOBALS['opts_store'][ $k ] ?? $d;
}
function update_option( $k, $v, $a = true ) {
	$GLOBALS['opts_store'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['opts_store'][ $k ] );
	return true;
}
function wp_next_scheduled( $h ) {
	return false;
}
function wp_schedule_single_event( $t, $h ) {
	$GLOBALS['scheduled'][] = $h;
	return true;
}
class AUN_App_Watch {
	public static $last_query = null;
	public static function api( $path, $params = array() ) {
		self::$last_query = array( 'path' => $path, 'params' => $params );
		return $GLOBALS['tmdb_response'] ?? array();
	}
	public static function hydrate_local( $row, $link ) {
		$GLOBALS['hydrated'][] = $link;
		$row['cast']    = array( array( 'name' => 'From TMDB' ) );
		$row['rating']  = 7.1;
		return $row;
	}
}

require 'C:/Users/Jobayer Hossain/Downloads/Claude session/aun-app-api/includes/class-aun-app-chorki.php';

$pass = 0;
$fail = 0;
function check( $label, $got, $want ) {
	global $pass, $fail;
	$ok = ( $got === $want );
	if ( $ok ) {
		$pass++;
		echo "  PASS  $label\n";
	} else {
		$fail++;
		echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n";
	}
}
function section( $t ) {
	echo "\n$t\n" . str_repeat( '-', strlen( $t ) ) . "\n";
}

$FIX = getenv( 'CHORKI_FIXTURES' ) ?: sys_get_temp_dir();

/* ══════════════════════════════════════════════════════════════════════════
   1. LIST PARSING — against the real saved hot-and-fresh page
   ══════════════════════════════════════════════════════════════════════════ */
section( '1. List page → ordered {kind, path}' );

$list_html = @file_get_contents( $FIX . '/chorki-list.html' );
if ( false === $list_html ) {
	echo "  SKIP  no chorki-list.html fixture at $FIX\n";
} else {
	$GLOBALS['fixture_html'] = $list_html;
	$items = AUN_App_Chorki::fetch_list( 'x', 5 );

	check( 'honours the limit', count( $items ), 5 );
	check( 'first item path', $items[0]['path'], '/en/shortfilm/faisha-gesi' );
	check( 'first item kind', $items[0]['kind'], 'shortfilm' );
	check( 'movie kind read from URL', $items[3]['kind'], 'movie' );
	check( 'movie path', $items[3]['path'], '/en/movie/rockstar' );

	// Order is the editorial signal — Chorki puts the newest first, and a
	// re-ordered rail would silently stop being "hot and fresh".
	$all = AUN_App_Chorki::fetch_list( 'x', 50 );
	check( 'full list length (deduped)', count( $all ), 15 );
	check( 'series detected', $all[7]['kind'], 'series' );
	check( 'document order preserved', $all[7]['path'], '/en/series/cactus' );

	// A page that fails to load must yield nothing, NOT a partial list — the
	// caller keeps the last good store only if we return empty.
	$GLOBALS['fixture_html'] = '';
	check( 'empty body → no items', AUN_App_Chorki::fetch_list( 'x', 5 ), array() );
}

/* ══════════════════════════════════════════════════════════════════════════
   2. DETAIL PARSING — against the real saved Rockstar page
   ══════════════════════════════════════════════════════════════════════════ */
section( '2. Detail page → structured data (JSON-LD + og:)' );

$detail_html = @file_get_contents( $FIX . '/chorki-detail.html' );
if ( false === $detail_html ) {
	echo "  SKIP  no chorki-detail.html fixture at $FIX\n";
} else {
	$GLOBALS['fixture_html'] = $detail_html;
	$d = AUN_App_Chorki::fetch_detail( '/en/movie/rockstar' );

	check( 'title from JSON-LD', $d['title'], 'Rockstar' );
	check( 'read via json-ld', $d['via'], 'json-ld' );
	check( 'year from uploadDate', $d['year'], '2026' );
	check( 'ISO duration → "2h 19m"', $d['runtime'], '2h 19m' );
	check( 'genre carried whole', $d['genre'], 'Musical Drama Romance' );
	check( 'director', $d['director'], 'Azman Rusho' );
	// embedUrl is locale-free — the app is Bangla-first and must not be pinned
	// to Chorki's English site by the path we happened to crawl.
	check( 'canonical URL from embedUrl', $d['url'], 'https://www.chorki.net/movie/rockstar' );
	check( 'poster from og:image', 0 === strpos( $d['poster'], 'https://image.chorkicdn.com/' ), true );
	// JSON-LD arrives with &apos; in the text.
	check( 'entities decoded in synopsis', false === strpos( $d['overview'], '&apos;' ), true );
	check( 'synopsis is real prose', strlen( $d['overview'] ) > 100, true );

	// Degradation: no structured data at all still yields a usable card.
	$GLOBALS['fixture_html'] = '<html><body>nothing useful</body></html>';
	$bare = AUN_App_Chorki::fetch_detail( '/en/movie/tomar-jonno-mon' );
	check( 'slug fallback title', $bare['title'], 'Tomar Jonno Mon' );
	check( 'slug fallback flagged', $bare['via'], 'slug' );
}

/* ══════════════════════════════════════════════════════════════════════════
   3. THE MATCHER — the gates, with real TMDB-shaped rows
   ══════════════════════════════════════════════════════════════════════════ */
section( '3. TMDB matcher gates' );

// ⚠️ THE ONE THAT MATTERS. This is what TMDB actually returns for "Rockstar":
// the 2011 Ranbir Kapoor film first. Accepting it would put an Indian star's
// poster and cast on a Bangladeshi film.
$rockstar_results = array(
	array( 'id' => 61697, 'title' => 'Rockstar', 'original_title' => 'रॉकस्टार', 'original_language' => 'hi', 'release_date' => '2011-11-11' ),
	array( 'id' => 293670, 'title' => 'Rockstar', 'original_title' => 'Rockstar', 'original_language' => 'en', 'release_date' => '2015-09-11' ),
);
check(
	'rejects Bollywood Rockstar (hi)',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', $rockstar_results ),
	''
);

// Same search, but the Bangladeshi one is present further down the list.
$with_bd = array_merge(
	$rockstar_results,
	array( array( 'id' => 999001, 'title' => 'Rockstar', 'original_title' => 'রকস্টার', 'original_language' => 'bn', 'release_date' => '2026-07-18' ) )
);
check(
	'finds the Bangladeshi one below two decoys',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', $with_bd ),
	'movie:999001'
);

// ⚠️ THE 1.100.0 CASE, and how TMDB really stores these. TMDB files the
// Bangladeshi Rockstar under its BANGLA name — there is no Latin title on the
// record at all — so title comparison scores ~0 and 1.99.x rejected the correct
// match. The synopsis is the fingerprint that survives the change of script.
$real_bd = array(
	array( 'id' => 61697, 'title' => 'Rockstar', 'original_title' => 'रॉकस्टार', 'original_language' => 'hi', 'release_date' => '2011-11-11',
		'overview' => 'A young man from a small town rises to fame as a rock musician after heartbreak.' ),
	array( 'id' => 777001, 'title' => 'রকস্টার', 'original_title' => 'রকস্টার', 'original_language' => 'bn', 'release_date' => '2026-07-18',
		'overview' => 'Agun overcomes his childhood stage fright to become a world-famous rockstar. But with fame comes addiction, heartbreak, imprisonment, and the devastating loss of his voice.' ),
);
$chorki_synopsis = 'Agun overcomes his childhood stage fright to become a world-famous rockstar. But with fame comes addiction, heartbreak, imprisonment, and the devastating loss of his voice. Supported by Aslam\'s friendship and Meera\'s love, he struggles to rebuild his life.';

check( 'Bangla-script title still rejected on NAME alone',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', $real_bd ), '' );
check( 'but MATCHED once the synopsis is supplied',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', $real_bd, $chorki_synopsis ), 'movie:777001' );

// The synopsis must not become a backdoor around the country gate: the same
// story text on an Indian record is still rejected.
check( 'a matching synopsis cannot bypass the BD gate',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', array(
		array( 'id' => 3, 'title' => 'Rockstar', 'original_language' => 'hi', 'release_date' => '2026-01-01', 'overview' => $chorki_synopsis ),
	), $chorki_synopsis ),
	'' );

// Nor around the year gate.
check( 'a matching synopsis cannot bypass the year gate',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', array(
		array( 'id' => 4, 'title' => 'রকস্টার', 'original_language' => 'bn', 'release_date' => '2015-01-01', 'overview' => $chorki_synopsis ),
	), $chorki_synopsis ),
	'' );

// Two unrelated Bangladeshi 2026 films must not match each other on stopwords.
check( 'unrelated bn films of the same year do not match',
	AUN_App_Chorki::pick_match( 'Lifeline', 'movie', '2026', array(
		array( 'id' => 6, 'title' => 'লাইফলাইন', 'original_language' => 'bn', 'release_date' => '2026-03-01',
			'overview' => 'A village schoolteacher fights to keep the last ferry route open across the river.' ),
	), 'How far would you go, and what would you risk for the one you love? Ananya is driven to an impossible choice.' ),
	'' );

// A too-short synopsis is no evidence at all — it must not match on nothing.
check( 'a one-line synopsis is not treated as proof',
	AUN_App_Chorki::pick_match( 'Meu', 'movie', '2026', array(
		array( 'id' => 9, 'title' => 'মেউ', 'original_language' => 'bn', 'release_date' => '2026-05-01', 'overview' => 'A cat.' ),
	), 'A cat.' ),
	'' );

// Gate 3: right language, wrong film.
check(
	'rejects a bn title that is not this title',
	AUN_App_Chorki::pick_match( 'Rockstar', 'movie', '2026', array(
		array( 'id' => 5, 'title' => 'Hawa', 'original_title' => 'হাওয়া', 'original_language' => 'bn', 'release_date' => '2022-07-29' ),
	) ),
	''
);

// Gate 4: right name and language, implausible year.
check(
	'rejects a bn match 6 years out',
	AUN_App_Chorki::pick_match( 'Lifeline', 'movie', '2026', array(
		array( 'id' => 7, 'title' => 'Lifeline', 'original_title' => 'Lifeline', 'original_language' => 'bn', 'release_date' => '2020-01-01' ),
	) ),
	''
);
check(
	'accepts ±1 year drift (upload vs release)',
	AUN_App_Chorki::pick_match( 'Lifeline', 'movie', '2026', array(
		array( 'id' => 8, 'title' => 'Lifeline', 'original_title' => 'Lifeline', 'original_language' => 'bn', 'release_date' => '2025-12-20' ),
	) ),
	'movie:8'
);

// Series use origin_country, which TMDB's MOVIE search never returns.
check(
	'series matched via origin_country BD',
	AUN_App_Chorki::pick_match( 'Cactus', 'tv', '2026', array(
		array( 'id' => 42, 'name' => 'Cactus', 'original_name' => 'ক্যাকটাস', 'original_language' => 'bn', 'origin_country' => array( 'BD' ), 'first_air_date' => '2026-06-01' ),
	) ),
	'tv:42'
);

// Punctuation and spacing must not defeat a real match.
check(
	'tolerates punctuation differences',
	AUN_App_Chorki::pick_match( 'Jaya Aar Sharmin', 'movie', '2026', array(
		array( 'id' => 11, 'title' => 'Jaya Aar Sharmin', 'original_title' => 'জয়া আর শারমিন', 'original_language' => 'bn', 'release_date' => '2026-02-14' ),
	) ),
	'movie:11'
);

// Short films skip the lookup entirely — no request, no chance of a wrong hit.
// ⚠️ 1.99.x SKIPPED short films on the assumption TMDB had no Bangladeshi ones.
// It does — "Faisha Gesi" and "Paint on Dry Leaf" are both catalogued — so the
// skip was throwing away real cast and trailer data. They are looked up now.
$GLOBALS['tmdb_response'] = array( 'results' => array( array( 'id' => 1, 'title' => 'Faisha Gesi', 'original_title' => 'ফাইসা গেছি', 'original_language' => 'bn', 'release_date' => '2026-08-20' ) ) );
AUN_App_Watch::$last_query = null;
check( 'short films ARE looked up now', AUN_App_Chorki::match_tmdb( 'Faisha Gesi', 'shortfilm', '2026' ), 'movie:1' );
check( 'and a short film DOES make a TMDB call', AUN_App_Watch::$last_query['path'], '/search/movie' );

// And the typed endpoint is used, not /search/multi — that is what carries
// original_language.
AUN_App_Chorki::match_tmdb( 'Domm', 'movie', '2026' );
check( 'uses typed /search/movie', AUN_App_Watch::$last_query['path'], '/search/movie' );
AUN_App_Chorki::match_tmdb( 'Aatka', 'series', '2026' );
check( 'series uses /search/tv', AUN_App_Watch::$last_query['path'], '/search/tv' );

/* ══════════════════════════════════════════════════════════════════════════
   4. ROW BUILDING + the "never blank the rail" rule
   ══════════════════════════════════════════════════════════════════════════ */
section( '4. Built rows and failure behaviour' );

if ( false !== $list_html && false !== $detail_html ) {
	// The real sequence build() performs: one list fetch, then one page per
	// title. Every detail fetch returns the Rockstar page, which is enough to
	// prove row shape, platform stamping and the TMDB layering.
	$GLOBALS['fixture_html']  = '';
	$GLOBALS['fixture_seq']   = array_merge( array( $list_html ), array_fill( 0, 5, $detail_html ) );
	$GLOBALS['fetched']       = array();
	$GLOBALS['tmdb_response'] = array( 'results' => array() ); // nothing matches
	$GLOBALS['hydrated']      = array();

	$log  = array();
	$rows = AUN_App_Chorki::build( $log );

	check( 'builds one row per listed title', count( $rows ), 5 );
	check( 'fetches list + one page per title', count( $GLOBALS['fetched'] ), 6 );
	check( 'detail URL is absolute', $GLOBALS['fetched'][1], 'https://www.chorki.net/en/shortfilm/faisha-gesi' );

	$r = $rows[0];
	check( 'platform stamped', $r['platform'], 'Chorki' );
	check( 'flagged as a local pick', $r['local'], true );
	check( 'title from the detail page', $r['title'], 'Rockstar' );
	check( 'poster carried', 0 === strpos( $r['poster'], 'https://image.chorkicdn.com/' ), true );
	check( 'synopsis carried', strlen( $r['overview'] ) > 100, true );
	check( 'runtime carried', $r['runtime'], '2h 19m' );
	check( 'genre is ONE honest chip, not three invented ones', $r['genres'], array( 'Musical Drama Romance' ) );
	// Chorki's three kinds are carried through distinctly. 'short' is new and
	// forward-compatible: shipped apps render anything that is not 'series' as
	// "Movie", so this cannot break an install that has not updated.
	check( 'short film typed as short', $r['kind'], 'short' );          // /en/shortfilm/faisha-gesi
	check( 'movie typed as movie', $rows[3]['kind'], 'movie' );          // /en/movie/rockstar
	check( 'movie typed as movie (2)', $rows[4]['kind'], 'movie' );      // /en/movie/lifeline

	// RULE 1: no TMDB match must not blank the card.
	check( 'no TMDB match still yields a full card',
		'' !== $r['title'] && '' !== $r['overview'] && '' !== $r['poster'], true );
	check( 'nothing was hydrated', $GLOBALS['hydrated'], array() );
	check( 'log explains the misses', $log[3]['note'], "not on TMDB — showing Chorki's own details" );
	check( 'the note no longer claims shorts are skipped', $log[0]['note'], "not on TMDB — showing Chorki's own details" );
	check( 'log records the extraction strategy', $log[0]['via'], 'json-ld' );

	// Now WITH a confident match: TMDB enrichment layers on top, and Chorki's
	// own identity fields survive it.
	$GLOBALS['fixture_seq']   = array_merge( array( $list_html ), array_fill( 0, 5, $detail_html ) );
	$GLOBALS['tmdb_response'] = array( 'results' => array(
		array( 'id' => 999001, 'title' => 'Rockstar', 'original_title' => 'রকস্টার', 'original_language' => 'bn', 'release_date' => '2026-07-18' ),
	) );
	$GLOBALS['hydrated'] = array();
	$log2  = array();
	$rows2 = AUN_App_Chorki::build( $log2 );
	// ⚠️ The first THREE entries on the real list are short films — which is
	// precisely the case that makes "TMDB is a bonus, never a gate" the right
	// rule. Only the two movies are eligible for a lookup.
	check( 'every title is now eligible, shorts included', count( $GLOBALS['hydrated'] ), 5 );
	// ⚠️ TMDB has no notion of a short film and hydrate_local() stamps 'movie'
	// over the kind. Chorki is right about its own catalogue, so its answer wins.
	check( 'a hydrated short film STAYS typed short', $rows2[1]['kind'], 'short' );
	check( 'a hydrated short film still gets TMDB rating', $rows2[1]['rating'], 7.1 );
	check( 'Chorki title survives enrichment', $rows2[3]['title'], 'Rockstar' );
	check( 'Chorki platform survives enrichment', $rows2[3]['platform'], 'Chorki' );
	check( 'TMDB rating layered onto the movie', $rows2[3]['rating'], 7.1 );
	check( 'TMDB cast layered onto the movie', $rows2[3]['cast'][0]['name'], 'From TMDB' );
	// And the short film still has a complete card from Chorki alone.
	check( 'short film card is still complete',
		'' !== $rows2[1]['title'] && '' !== $rows2[1]['overview'] && '' !== $rows2[1]['poster'], true );
}

$GLOBALS['fixture_seq'] = array();

// A cold store must NEVER build inline — five HTTP calls on a customer's
// request is how a rail becomes a timeout.
$GLOBALS['opts_store'] = array();
$GLOBALS['scheduled']  = array();
check( 'cold store returns empty immediately', AUN_App_Chorki::picks(), array() );
check( 'cold store queues a background build', in_array( 'aun_app_chorki_refresh', $GLOBALS['scheduled'], true ), true );

// Disabled → nothing at all, and no work done.
$GLOBALS['aun_opts']['chorki_enabled'] = 0;
$GLOBALS['scheduled'] = array();
check( 'disabled returns empty', AUN_App_Chorki::picks(), array() );
check( 'disabled schedules nothing', $GLOBALS['scheduled'], array() );
$GLOBALS['aun_opts']['chorki_enabled'] = 1;

// A stored list survives a failed rebuild. This is the rule that keeps the app
// looking alive while chorki.net is down.
$GLOBALS['opts_store'][ AUN_App_Chorki::STORE_KEY ] = array(
	'v'     => AUN_App_Chorki::SCHEMA,
	'at'    => time(),
	'picks' => array( array( 'title' => 'Kept', 'platform' => 'Chorki' ) ),
);
$GLOBALS['fixture_html'] = ''; // chorki.net unreachable
$kept = AUN_App_Chorki::picks( true );
check( 'failed forced rebuild keeps the last good list', $kept[0]['title'], 'Kept' );

// An old schema must be rebuilt, not served — otherwise a plugin upgrade that
// adds a field is invisible until the TTL lapses.
$GLOBALS['opts_store'][ AUN_App_Chorki::STORE_KEY ]['v'] = 0;
check( 'stale schema is not served', AUN_App_Chorki::picks(), array() );

section( '4b. Backfill covers a broken page, not a TMDB miss' );

// A title whose page cannot be parsed is dropped; a spare from further down the
// list takes its place so the rail still shows the requested number.
$GLOBALS['opts_store'] = array();
$GLOBALS['aun_opts']['chorki_limit'] = 3;
$GLOBALS['tmdb_response'] = array( 'results' => array() );
// list, then: good, BROKEN, good, good  → the broken one must be replaced.
$GLOBALS['fixture_seq'] = array( $list_html, $detail_html, '', $detail_html, $detail_html );
$GLOBALS['fetched'] = array();
$log3 = array();
$rows3 = AUN_App_Chorki::build( $log3 );
check( 'a broken page is replaced by a spare', count( $rows3 ), 3 );
check( 'the failure is recorded', $log3[1]['note'], 'detail fetch failed' );

// ⚠️ And the case we deliberately do NOT skip: no TMDB match keeps the title.
$GLOBALS['aun_opts']['chorki_limit'] = 5;
$GLOBALS['fixture_seq'] = array_merge( array( $list_html ), array_fill( 0, 8, $detail_html ) );
$GLOBALS['fetched'] = array();
$log4 = array();
$rows4 = AUN_App_Chorki::build( $log4 );
check( 'unmatched titles are KEPT, not skipped over', count( $rows4 ), 5 );
check( 'no spare pages were fetched when nothing failed', count( $GLOBALS['fetched'] ), 6 );

section( '5. The diagnostic button persists its work (1.99.2 regression)' );

// ⚠️ Shipped in 1.99.0 and reported from the live site: the button said
// "5 titles pulled from Chorki" while the app went on showing nothing, because
// diagnostic() built the rows and threw them away. A success message for work
// that was discarded is worse than an error — the admin stops looking.
$GLOBALS['opts_store'] = array();
$GLOBALS['aun_opts']['chorki_enabled'] = 1;
$GLOBALS['fixture_seq']   = array_merge( array( $list_html ), array_fill( 0, 5, $detail_html ) );
$GLOBALS['tmdb_response'] = array( 'results' => array() );

$diag = AUN_App_Chorki::diagnostic();
check( 'diagnostic reports success', $diag['ok'], true );
$saved = get_option( AUN_App_Chorki::STORE_KEY );
check( 'diagnostic SAVED what it built', count( $saved['picks'] ?? array() ), 5 );

// The very next app request must therefore be served with no cron in between.
$GLOBALS['fixture_seq'] = array();
$GLOBALS['scheduled']   = array();
check( 'app served immediately after the button', count( AUN_App_Chorki::picks() ), 5 );
check( 'and no rebuild is queued', $GLOBALS['scheduled'], array() );

// A failed run must NOT wipe a good store.
$GLOBALS['fixture_html'] = ''; // chorki.net down
$diag2 = AUN_App_Chorki::diagnostic();
check( 'a failed test reports failure', $diag2['ok'], false );
$still = get_option( AUN_App_Chorki::STORE_KEY );
check( 'a failed test keeps the last good list', count( $still['picks'] ?? array() ), 5 );

/* ══════════════════════════════════════════════════════════════════════════ */
echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "  $pass passed, $fail failed\n";
echo str_repeat( '=', 60 ) . "\n";
exit( $fail > 0 ? 1 : 0 );
