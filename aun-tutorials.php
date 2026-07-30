<?php
/**
 * Plugin Name: AUN Tutorials
 * Description: Smart, lightweight tutorial video grid for product pages. Give it a YouTube PLAYLIST and it
 *              auto-loads every video with an auto-shortened title. Uses the YouTube Data API when a key is
 *              set (Settings → Tutorials) for reliable, full-playlist loading; otherwise falls back to the
 *              keyless RSS feed. Or list videos manually. Click-to-play lightbox (thumbnails ~15 KB each,
 *              player loads only on click), premium cards, 2-col mobile. Reference: Settings → Tutorials.
 *              Shortcode:  [aun_tutorials playlist="PLAYLIST_ID_OR_URL"]
 *                    or:   [aun_tutorials ids="VIDEOID:Title, VIDEOID:Title, ..."]
 * Version:     1.3.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'aun_tutorials', 'aun_tut_shortcode' );

function aun_tut_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'playlist'  => '',   // YouTube playlist ID or full URL → auto-load every video
		'ids'       => '',   // manual: "VIDEOID:Title, VIDEOID:Title" — works ALONE or alongside playlist
		'ids_first' => '0',  // 1 = show the manual videos BEFORE the playlist (default: after)
		'limit'     => '0',  // optional cap on how many to show (0 = all)
		'strip'     => '',   // optional extra phrases to remove from auto titles (comma-separated)
	), $atts, 'aun_tutorials' );

	// Build each source independently, then merge — so you can combine a playlist with extra videos.
	$from_playlist = array();
	if ( trim( $a['playlist'] ) !== '' ) {
		// Accept a bare ID or a full playlist URL (…?list=XXXX).
		$pid = trim( $a['playlist'] );
		if ( preg_match( '/[?&]list=([A-Za-z0-9_-]+)/', $pid, $m ) ) $pid = $m[1];
		if ( preg_match( '/^[A-Za-z0-9_-]{10,}$/', $pid ) ) {
			foreach ( aun_tut_fetch_playlist( $pid ) as $v ) {
				$from_playlist[] = array( 'id' => $v['id'], 'title' => aun_tut_clean_title( $v['title'], $a['strip'] ) );
			}
		}
	}

	$from_ids = array();
	if ( trim( $a['ids'] ) !== '' ) {
		foreach ( explode( ',', $a['ids'] ) as $pair ) {
			$pair = trim( $pair );
			if ( $pair === '' ) continue;
			$pos   = strpos( $pair, ':' );                       // split on FIRST colon only
			$vid   = trim( $pos === false ? $pair : substr( $pair, 0, $pos ) );
			$title = $pos === false ? '' : trim( substr( $pair, $pos + 1 ) );
			$from_ids[] = array( 'id' => $vid, 'title' => $title );
		}
	}

	// Manual videos go after the playlist by default (extras at the end); ids_first="1" puts them first.
	$items = (int) $a['ids_first'] ? array_merge( $from_ids, $from_playlist ) : array_merge( $from_playlist, $from_ids );

	// De-dupe by video id, keeping the first occurrence (so an extra video already in the playlist won't double).
	$seen = array(); $unique = array();
	foreach ( $items as $it ) {
		$id = strtolower( trim( $it['id'] ) );
		if ( $id === '' || isset( $seen[ $id ] ) ) continue;
		$seen[ $id ] = true;
		$unique[]    = $it;
	}
	$items = $unique;

	$limit = (int) $a['limit'];
	if ( $limit > 0 ) $items = array_slice( $items, 0, $limit );

	$cards = '';
	foreach ( $items as $it ) $cards .= aun_tut_card( $it['id'], $it['title'] );
	if ( $cards === '' ) return '';

	return aun_tut_assets() . '<div class="aun-tut"><div class="aun-tut-grid">' . $cards . '</div></div>';
}

/** Builds one video card (validates the YouTube id). */
function aun_tut_card( $vid, $title ) {
	$vid = trim( $vid );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{6,15}$/', $vid ) ) return '';
	$thumb = 'https://i.ytimg.com/vi/' . $vid . '/mqdefault.jpg';
	$watch = 'https://www.youtube.com/watch?v=' . $vid;
	return '<a class="aun-tut-card" href="' . esc_url( $watch ) . '" target="_blank" rel="noopener noreferrer"'
		. ' data-vid="' . esc_attr( $vid ) . '" aria-label="' . esc_attr( $title !== '' ? 'Play: ' . $title : 'Play video' ) . '">'
		. '<span class="aun-tut-thumb"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy">'
		. '<span class="aun-tut-play"><i class="fa-solid fa-play"></i></span></span>'
		. ( $title !== '' ? '<span class="aun-tut-title">' . esc_html( $title ) . '</span>' : '' )
		. '</a>';
}

/**
 * Fetches a playlist's videos. Returns [ ['id'=>, 'title'=>], ... ].
 * Uses the YouTube Data API when a key is saved (Settings → Tutorials) — reliable and loads the FULL
 * playlist. Without a key it falls back to YouTube's keyless RSS feed (metadata-only on many playlists).
 * Cached for 6h; serves the last-good copy if YouTube is unreachable so the page never goes blank.
 */
function aun_tut_fetch_playlist( $pid ) {
	$ver = (int) get_option( 'aun_tut_cache_ver', 1 );
	$key = 'aun_tut_pl_' . $ver . '_' . md5( $pid );

	$cached = get_transient( $key );
	if ( $cached !== false ) return $cached;

	$api   = trim( (string) get_option( 'aun_tut_api_key', '' ) );
	$items = $api !== '' ? aun_tut_fetch_via_api( $pid, $api ) : aun_tut_fetch_via_rss( $pid );

	if ( ! empty( $items ) ) {
		set_transient( $key, $items, 6 * HOUR_IN_SECONDS );
		update_option( 'aun_tut_last_' . md5( $pid ), $items, false );
		return $items;
	}

	// API/RSS returned nothing (quota, network, empty feed) → serve last-good copy if we have one.
	$last = get_option( 'aun_tut_last_' . md5( $pid ) );
	return is_array( $last ) ? $last : array();
}

/**
 * YouTube Data API v3 — reads every video in the playlist (paginates, up to 500).
 * Skips deleted/private placeholder entries. Returns [] on any error so the caller serves last-good.
 */
function aun_tut_fetch_via_api( $pid, $api_key ) {
	$items = array();
	$page  = '';
	$guard = 0;

	do {
		$args = array(
			'part'       => 'snippet',
			'maxResults' => 50,
			'playlistId' => $pid,
			'key'        => $api_key,
		);
		if ( $page !== '' ) $args['pageToken'] = $page;
		$url = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/playlistItems' );

		$res = wp_remote_get( $url, array( 'timeout' => 12 ) );
		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) break;

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['items'] ) ) break;

		foreach ( $data['items'] as $it ) {
			$sn    = isset( $it['snippet'] ) ? $it['snippet'] : array();
			$vid   = isset( $sn['resourceId']['videoId'] ) ? $sn['resourceId']['videoId'] : '';
			$title = isset( $sn['title'] ) ? (string) $sn['title'] : '';
			if ( $vid === '' || $title === 'Deleted video' || $title === 'Private video' ) continue;
			$items[] = array( 'id' => $vid, 'title' => $title );
		}

		$page = isset( $data['nextPageToken'] ) ? (string) $data['nextPageToken'] : '';
		$guard++;
	} while ( $page !== '' && $guard < 10 );

	return $items;
}

/** Keyless fallback: YouTube's public playlist RSS feed (up to ~15 videos, often empty for newer playlists). */
function aun_tut_fetch_via_rss( $pid ) {
	$res = wp_remote_get( 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode( $pid ), array( 'timeout' => 10 ) );
	if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return array();
	return aun_tut_parse_feed( wp_remote_retrieve_body( $res ) );
}

/** Parses the YouTube Atom feed → [ ['id'=>, 'title'=>], ... ]. SimpleXML with a regex fallback. */
function aun_tut_parse_feed( $body ) {
	$items = array();
	if ( $body === '' ) return $items;

	if ( function_exists( 'simplexml_load_string' ) ) {
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $body );
		libxml_use_internal_errors( $prev );
		if ( $xml !== false && isset( $xml->entry ) ) {
			foreach ( $xml->entry as $entry ) {
				$yt    = $entry->children( 'http://www.youtube.com/xml/schemas/2015' );
				$vid   = isset( $yt->videoId ) ? (string) $yt->videoId : '';
				$title = (string) $entry->title;
				if ( $vid !== '' ) $items[] = array( 'id' => $vid, 'title' => $title );
			}
			return $items;
		}
	}

	// Fallback: videoId always appears before title within an <entry>.
	if ( preg_match_all( '/<yt:videoId>([A-Za-z0-9_-]+)<\/yt:videoId>.*?<title>(.*?)<\/title>/s', $body, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) $items[] = array( 'id' => $row[1], 'title' => html_entity_decode( strip_tags( $row[2] ), ENT_QUOTES ) );
	}
	return $items;
}

/**
 * The "brain": turns a long YouTube title into a short caption.
 * e.g. "How to Factory Reset AUN U002 Projector" → "Factory Reset".
 * Falls back to the full title if it ends up too short.
 */
function aun_tut_clean_title( $t, $strip = '' ) {
	$orig = trim( html_entity_decode( (string) $t, ENT_QUOTES ) );
	$t    = $orig;

	if ( $strip !== '' ) {
		foreach ( explode( ',', $strip ) as $ph ) {
			$ph = trim( $ph );
			if ( $ph !== '' ) $t = str_ireplace( $ph, '', $t );
		}
	}

	$t = preg_replace( '/^\s*how\s+to\s+/i', '', $t );                       // leading "How to"
	$t = preg_replace( '/\b(on|for|in|with)\s+AUN[\s\w-]*?projector\b/i', '', $t ); // "…on AUN U002 Projector"
	$t = preg_replace( '/\bAUN[\s\w-]*?projector\b/i', '', $t );             // "AUN U002 Projector"
	$t = preg_replace( '/\bAUN\b/i', '', $t );                               // stray "AUN"
	$t = preg_replace( '/\b[AU]\d{2,4}\s*(pro)?\b/i', '', $t );              // model codes U002 / A45 / A005…
	$t = preg_replace( '/\b(tutorial|step[\s-]?by[\s-]?step|full\s*hd|genuine\s*android\s*tv|android\s*tv|projector)\b/i', '', $t );
	$t = preg_replace( '/[|:•·\-–—]+/u', ' ', $t );                          // separators → space
	$t = preg_replace( '/\s{2,}/', ' ', $t );
	$t = trim( $t, " \t\n\r-|:" );

	return ( function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t ) ) < 3 ? $orig : $t;
}

/* ── Admin reference: Settings → Tutorials ────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_options_page( 'Tutorials', 'Tutorials', 'manage_options', 'aun-tutorials', 'aun_tut_help_page' );
} );

function aun_tut_help_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	if ( isset( $_GET['aun_tut_refresh'] ) && check_admin_referer( 'aun_tut_refresh' ) ) {
		update_option( 'aun_tut_cache_ver', ( (int) get_option( 'aun_tut_cache_ver', 1 ) ) + 1 );
		echo '<div class="notice notice-success is-dismissible"><p>Tutorial playlists refreshed — the latest videos will load now.</p></div>';
	}

	if ( isset( $_POST['aun_tut_api_key_field'] ) && check_admin_referer( 'aun_tut_save_key' ) ) {
		$new = sanitize_text_field( wp_unslash( $_POST['aun_tut_api_key_field'] ) );
		update_option( 'aun_tut_api_key', $new );
		update_option( 'aun_tut_cache_ver', ( (int) get_option( 'aun_tut_cache_ver', 1 ) ) + 1 ); // bust cache so the key takes effect immediately
		echo '<div class="notice notice-success is-dismissible"><p>' . ( $new === ''
			? 'API key cleared — playlists will use the keyless RSS fallback.'
			: 'API key saved. Playlists now load through the YouTube Data API.' ) . '</p></div>';
	}

	$api_key  = (string) get_option( 'aun_tut_api_key', '' );
	$api_on   = $api_key !== '';
	$masked   = $api_on ? str_repeat( '•', max( 0, strlen( $api_key ) - 4 ) ) . substr( $api_key, -4 ) : '';
	$refresh  = wp_nonce_url( admin_url( 'options-general.php?page=aun-tutorials&aun_tut_refresh=1' ), 'aun_tut_refresh' );
	?>
	<div class="wrap">
		<h1>🎥 Tutorials — Shortcode Reference</h1>

		<h2 style="margin-top:6px">YouTube Data API key</h2>
		<p style="max-width:720px">
			<?php if ( $api_on ) : ?>
				<span style="display:inline-block;background:#edfaef;border:1px solid #b7e3bf;color:#0a6b1f;padding:4px 10px;border-radius:6px;font-weight:600">✓ Active</span>
				&nbsp;Playlists load through the API. Saved key: <code><?php echo esc_html( $masked ); ?></code>
			<?php else : ?>
				<span style="display:inline-block;background:#fff4e5;border:1px solid #f0cf9a;color:#8a5300;padding:4px 10px;border-radius:6px;font-weight:600">Not set</span>
				&nbsp;Using the keyless RSS fallback (unreliable — often returns an empty playlist). Add a key below for full, reliable loading.
			<?php endif; ?>
		</p>
		<form method="post" style="margin:0 0 6px">
			<?php wp_nonce_field( 'aun_tut_save_key' ); ?>
			<input type="password" name="aun_tut_api_key_field" value="<?php echo esc_attr( $api_key ); ?>"
				autocomplete="off" spellcheck="false" placeholder="AIza…"
				style="width:100%;max-width:480px;font-family:monospace;padding:7px 10px;border:1px solid #c3c4c7;border-radius:6px">
			<button type="submit" class="button button-primary">Save key</button>
		</form>
		<p class="description" style="max-width:720px">Stored in your site database, admin-only. Restrict the key to <strong>YouTube Data API v3</strong> in Google Cloud so it can't be reused elsewhere. After adding videos to a playlist, click <a href="<?php echo esc_url( $refresh ); ?>" class="button button-small">Refresh playlists now</a></p>

		<hr>

		<h2>Smart mode (recommended) — just give it a playlist</h2>
		<p>Put all of a model's tutorials in one YouTube playlist, then drop this into the product <strong>Tutorial tab</strong>.
		   The plugin loads every video and shortens each title automatically.</p>
		<p><code style="font-size:14px;background:#f0f7ff;padding:6px 10px;border-radius:6px;display:inline-block">[aun_tutorials playlist="PLxxxxxxxxxxxx"]</code></p>
		<p class="description">You can paste the full playlist link too — e.g. <code>playlist="https://www.youtube.com/playlist?list=PLxxxx"</code>. The <code>list=</code> part is the playlist ID.</p>
		<ul style="list-style:disc;padding-left:20px;max-width:720px">
			<li>The playlist must be <strong>Public</strong> or <strong>Unlisted</strong> (not Private).</li>
			<li>With an API key set (above): loads the <strong>entire</strong> playlist, reliably. Without one: keyless RSS fallback, which YouTube often returns empty.</li>
			<li>Titles auto-shorten: <em>"How to Factory Reset AUN U002 Projector"</em> → <strong>"Factory Reset"</strong>.</li>
			<li>Results are cached ~6 hours. Added a new tutorial? Click <a href="<?php echo esc_url( $refresh ); ?>" class="button button-small">Refresh playlists now</a></li>
		</ul>

		<h3>Fine-tuning the auto titles (optional)</h3>
		<p>If a title still has words you want gone, add them with <code>strip</code> (comma-separated):</p>
		<p><code style="background:#f6f7f7;padding:5px 9px;border-radius:5px;display:inline-block">[aun_tutorials playlist="PLxxxx" strip="Smart, Home Cinema"]</code></p>

		<hr>
		<h2>Manual mode — pick exact videos &amp; titles</h2>
		<p>Each entry is <code>VIDEOID:Title</code>, separated by commas (the ID is the part after <code>watch?v=</code>):</p>
		<textarea readonly onclick="this.select()" rows="3"
			style="width:100%;max-width:760px;font-family:monospace;font-size:12.5px;padding:10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff">[aun_tutorials ids="nO70wP9bmDU:Factory Reset, KJwFhuis4GA:Screen Mirroring, fHMe1qsGmp4:Change Language"]</textarea>

		<hr>
		<h2>Combine — a playlist <em>plus</em> extra videos</h2>
		<p>Use <code>playlist</code> and <code>ids</code> together in one shortcode. The playlist loads automatically and your extra video(s) are added into the <strong>same grid</strong> — handy for a video that isn't (or can't be) in the playlist.</p>
		<textarea readonly onclick="this.select()" rows="3"
			style="width:100%;max-width:760px;font-family:monospace;font-size:12.5px;padding:10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff">[aun_tutorials playlist="PLxxxxxxxxxxxx" ids="dQw4w9WgXcQ:Quick Setup Guide"]</textarea>
		<ul style="list-style:disc;padding-left:20px;max-width:720px">
			<li>Extra videos appear <strong>after</strong> the playlist by default. Add <code>ids_first="1"</code> to show them <strong>before</strong> it.</li>
			<li>Duplicates are removed automatically — if an extra video is already in the playlist, it won't show twice.</li>
		</ul>

		<p style="margin-top:18px;color:#646970"><strong>Tip:</strong> add a heading above the shortcode in the tab —<br>
			<code>[aun_head eyebrow="Learn in minutes" icon="fa-circle-play" title="Projector Tutorials" sub="Short step-by-step videos — setup, daily use and care."]</code></p>
	</div>
	<?php
}

/* ── Assets (once per request, WP Rocket-guarded) ─────────────────────────── */
function aun_tut_assets() {
	static $done = false;
	if ( $done ) return '';
	$done = true;

	$css = <<<'CSS'
.aun-tut{max-width:1180px;margin:0 auto;box-sizing:border-box}
.aun-tut *{box-sizing:border-box}
.aun-tut-grid{display:flex;flex-wrap:wrap;justify-content:center;gap:18px}
.aun-tut-card{flex:1 1 240px;max-width:300px;display:block;text-decoration:none !important;background:linear-gradient(180deg,#fff,#f5faff);border:1px solid #e3eefb;border-radius:14px;overflow:hidden;box-shadow:0 8px 22px rgba(12,42,74,.07);transition:transform .22s,box-shadow .22s,border-color .22s}
.aun-tut-card:hover{transform:translateY(-4px);box-shadow:0 16px 34px rgba(1,136,254,.16);border-color:#bcd9f8}
.aun-tut-thumb{position:relative;display:block;aspect-ratio:16/9;background:#0a1f38;overflow:hidden}
.aun-tut-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
.aun-tut-card:hover .aun-tut-thumb img{transform:scale(1.04)}
.aun-tut-thumb::after{content:"";position:absolute;inset:0;background:rgba(5,15,30,.18);transition:background .22s}
.aun-tut-card:hover .aun-tut-thumb::after{background:rgba(5,15,30,.05)}
.aun-tut-play{position:absolute;z-index:2;left:50%;top:50%;transform:translate(-50%,-50%);width:46px;height:46px;border-radius:50%;background:rgba(1,136,254,.92);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,.35);transition:transform .22s,background .22s}
.aun-tut-play i{color:#fff;font-size:15px;margin-left:3px}
.aun-tut-card:hover .aun-tut-play{transform:translate(-50%,-50%) scale(1.1);background:#0188fe}
.aun-tut-title{display:block;padding:11px 13px 13px;font-size:14px;font-weight:700;color:#0c2a4a;line-height:1.35;text-align:center}
@media(max-width:560px){
.aun-tut-grid{gap:12px}
.aun-tut-card{flex:1 1 calc(50% - 6px);max-width:none}
.aun-tut-title{font-size:12.5px;padding:9px 9px 11px}
.aun-tut-play{width:38px;height:38px}
}
/* lightbox */
.aun-tut-lb{position:fixed;inset:0;z-index:999999;background:rgba(7,13,26,.92);display:none;align-items:center;justify-content:center;padding:20px;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
.aun-tut-lb.open{display:flex}
/* width is also capped by viewport HEIGHT so the 16:9 frame never overflows (e.g. phone landscape) —
   reserve ~120px for the close button above + breathing room top/bottom */
.aun-tut-lb-box{position:relative;width:min(960px,96vw,(100vh - 120px) * 16 / 9)}
.aun-tut-lb-frame{position:relative;width:100%;aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.6)}
.aun-tut-lb-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
/* close button — locked to a perfect circle so no theme button style can distort it; anchored to the VIDEO window's top-right (sits just above the frame on desktop) */
.aun-tut-lb-close{position:absolute;top:-54px;right:0;z-index:3;box-sizing:border-box !important;width:46px !important;height:46px !important;min-width:46px !important;min-height:46px !important;max-width:46px !important;padding:0 !important;margin:0 !important;border-radius:50% !important;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.34) !important;color:#fff;font-size:24px;font-weight:400;line-height:1 !important;cursor:pointer;display:flex !important;align-items:center;justify-content:center;-webkit-appearance:none;appearance:none;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);box-shadow:0 4px 16px rgba(0,0,0,.45);text-shadow:none;transition:background .2s,border-color .2s,transform .25s}
.aun-tut-lb-close:hover{background:#0188fe;border-color:#0188fe !important;transform:rotate(90deg)}
.aun-tut-lb-close:focus-visible{outline:2px solid #4da8ff;outline-offset:3px}
/* on small screens keep it ABOVE the frame too (video is short + centered, so it never covers content) */
@media(max-width:560px){.aun-tut-lb-close{top:-46px;right:0;width:38px !important;height:38px !important;min-width:38px !important;min-height:38px !important;max-width:38px !important;font-size:20px}}
@media(prefers-reduced-motion:reduce){
.aun-tut-card,.aun-tut-thumb img,.aun-tut-play,.aun-tut-lb-close{transition:none !important}
}
CSS;

	$js = <<<'JS'
(function(){
  var lb = null;
  function buildLb(){
    if (lb) return lb;
    lb = document.createElement('div');
    lb.className = 'aun-tut-lb';
    lb.innerHTML = '<div class="aun-tut-lb-box">'
      + '<button class="aun-tut-lb-close" type="button" aria-label="Close">&times;</button>'
      + '<div class="aun-tut-lb-frame"></div></div>';
    document.body.appendChild(lb);
    lb.addEventListener('click', function(e){
      if (e.target === lb || e.target.closest('.aun-tut-lb-close')) close();
    });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
    return lb;
  }
  function open(vid){
    var box = buildLb();
    box.querySelector('.aun-tut-lb-frame').innerHTML =
      '<iframe src="https://www.youtube-nocookie.com/embed/' + vid + '?autoplay=1&rel=0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    box.classList.add('open');
    document.documentElement.style.overflow = 'hidden';
  }
  function close(){
    if (!lb) return;
    lb.classList.remove('open');
    lb.querySelector('.aun-tut-lb-frame').innerHTML = '';   // stop playback
    document.documentElement.style.overflow = '';
  }
  document.addEventListener('click', function(e){
    var card = e.target.closest('.aun-tut-card');
    if (!card) return;
    var vid = card.getAttribute('data-vid');
    if (!vid) return;
    e.preventDefault();                                      // JS available → lightbox instead of YouTube
    open(vid);
  });
})();
JS;

	return '<style id="aun-tut-css" data-no-optimize="1" data-no-minify="1">' . $css . '</style>'
		. '<script id="aun-tut-js" data-no-optimize="1" data-no-minify="1" data-cfasync="false">' . $js . '</script>';
}
