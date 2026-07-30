<?php
/**
 * Plugin Name: AUN World Cup Hero
 * Description: Full-screen, cinematic, scroll & mouse reactive World Cup hero. A pure-CSS home-theatre scene — a projector casting a volumetric beam onto a glowing big screen that shows a live broadcast pitch with a real glassmorphism scoreboard. v4 adds an automatic LIVE data feed (football-data.org): 🔴 live score + minute during a match (with a ⚽ GOAL! confetti burst when the score changes), 🏁 full-time result for a few hours after, ⏳ countdown to the next fixture, 🏆 last result when idle — fully responsive, with graceful fallback to the manual countdown. Shortcode: [aun_worldcup_hero]. Settings: Settings → World Cup Hero.
 * Version:     4.1.3
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =========================================================================
 *  CONFIG HELPERS
 * ========================================================================= */

function awc_opt( $key, $default = '' ) {
	$v = get_option( $key, null );
	return ( $v === null || $v === '' ) ? $default : $v;
}
function awc_api_key() {
	if ( defined( 'AWC_FOOTBALL_API_KEY' ) && AWC_FOOTBALL_API_KEY ) return AWC_FOOTBALL_API_KEY;
	return trim( (string) awc_opt( 'awc_api_key', '' ) );
}
function awc_competition() {
	return preg_replace( '/[^A-Z0-9]/', '', strtoupper( awc_opt( 'awc_competition', 'WC' ) ) );
}
function awc_live_enabled() {
	return awc_opt( 'awc_live_enabled', '1' ) === '1';
}

/* =========================================================================
 *  LIVE DATA  —  server-side proxy + cache  (football-data.org v4)
 * ========================================================================= */

/**
 * Returns a normalized match-state array.
 *
 * Rate-limit safety (football-data.org free tier = 10 calls/min):
 *   • The external API is called at most once per TTL — 60s when a match is
 *     live (~1 call/min), 20min when idle (~0.05 call/min) — REGARDLESS of how
 *     many visitors are on the page, because every visitor reads this same
 *     server-side cache, not the API.
 *   • A short refresh "lock" prevents a cache stampede: when the cache goes
 *     stale, only the first request refreshes; concurrent requests are served
 *     the slightly-older cached copy (stale-while-revalidate). This keeps the
 *     call rate at ~1/min even under heavy World Cup traffic.
 *   • On any failure (incl. a 429 rate-limit), it backs off and keeps serving
 *     the last good result instead of hammering the API.
 *
 * Cache keys: option awc_match_last (last good data), option awc_match_fresh
 * (unix time the cache is fresh until), transient awc_lock (refresh guard).
 */
function awc_fetch_match( $force = false ) {
	$key = awc_api_key();
	if ( ! $key ) return array( 'state' => 'off' );

	$data  = get_option( 'awc_match_last' );
	$fresh = (int) get_option( 'awc_match_fresh', 0 );
	$now   = time();

	// Cache still fresh → serve it, no API call.
	if ( ! $force && $data && $now < $fresh ) return $data;

	// Stale (or forced refresh). Take the lock so only ONE request hits the API.
	if ( ! $force ) {
		if ( get_transient( 'awc_lock' ) && $data ) return $data; // someone else is refreshing
		set_transient( 'awc_lock', 1, 20 );
	}

	$comp = awc_competition();
	$url  = 'https://api.football-data.org/v4/competitions/' . $comp . '/matches';
	$res  = wp_remote_get( $url, array(
		'headers' => array( 'X-Auth-Token' => $key ),
		'timeout' => 8,
	) );
	$code = (int) wp_remote_retrieve_response_code( $res );

	if ( is_wp_error( $res ) || $code !== 200 ) {
		// Back off so we don't keep hitting a failing/limited endpoint.
		update_option( 'awc_match_fresh', $now + ( $code === 429 ? 120 : 60 ), false );
		delete_transient( 'awc_lock' );
		if ( $data ) return $data; // keep serving last good result
		$detail = is_wp_error( $res ) ? $res->get_error_message() : ( $code ? $code : 'no response' );
		return array( 'state' => 'error', 'detail' => $detail );
	}

	$body    = json_decode( wp_remote_retrieve_body( $res ), true );
	$matches = ( isset( $body['matches'] ) && is_array( $body['matches'] ) ) ? $body['matches'] : array();
	$new     = awc_normalize( $matches );

	$ttl = ( $new['state'] === 'live' ) ? 60 : 20 * MINUTE_IN_SECONDS;
	update_option( 'awc_match_last',  $new,        false );
	update_option( 'awc_match_fresh', $now + $ttl, false );
	delete_transient( 'awc_lock' );

	return $new;
}

/**
 * Pick the single most relevant match and decide the display state.
 * Priority: live now  ▸  finished within 3h  ▸  next upcoming  ▸  last finished.
 */
function awc_normalize( $matches ) {
	if ( empty( $matches ) ) return array( 'state' => 'off' );

	$now = time();
	$live = $recent = $next = $last = null;

	foreach ( $matches as $m ) {
		$st = isset( $m['status'] ) ? $m['status'] : '';
		$ts = isset( $m['utcDate'] ) ? strtotime( $m['utcDate'] ) : 0;

		if ( $st === 'IN_PLAY' || $st === 'PAUSED' ) {
			if ( ! $live || $ts < $live['_ts'] ) { $m['_ts'] = $ts; $live = $m; }
		} elseif ( $st === 'FINISHED' ) {
			if ( ! $last || $ts > $last['_ts'] ) { $m['_ts'] = $ts; $last = $m; }
			if ( $ts >= $now - 3 * HOUR_IN_SECONDS ) {
				if ( ! $recent || $ts > $recent['_ts'] ) { $m['_ts'] = $ts; $recent = $m; }
			}
		} else { // SCHEDULED / TIMED / etc.
			if ( $ts >= $now ) {
				if ( ! $next || $ts < $next['_ts'] ) { $m['_ts'] = $ts; $next = $m; }
			}
		}
	}

	if ( $live )   return awc_shape( $live,   'live' );
	if ( $recent ) return awc_shape( $recent, 'finished' );
	if ( $next )   return awc_shape( $next,   'upcoming' );
	if ( $last )   return awc_shape( $last,   'finished' );
	return array( 'state' => 'off' );
}

function awc_shape( $m, $state ) {
	$home  = isset( $m['homeTeam'] ) ? $m['homeTeam'] : array();
	$away  = isset( $m['awayTeam'] ) ? $m['awayTeam'] : array();
	$ft    = isset( $m['score']['fullTime'] ) ? $m['score']['fullTime'] : array( 'home' => null, 'away' => null );
	$ts    = isset( $m['utcDate'] ) ? strtotime( $m['utcDate'] ) : 0;
	$st    = isset( $m['status'] ) ? $m['status'] : '';

	$minute = ( isset( $m['minute'] ) && $m['minute'] ) ? intval( $m['minute'] ) : null;
	if ( $state === 'live' && ! $minute && $ts ) {
		$minute = max( 1, min( 120, intval( floor( ( time() - $ts ) / 60 ) ) + 1 ) );
	}

	$group     = ( isset( $m['group'] ) && $m['group'] ) ? $m['group'] : '';
	$stage     = isset( $m['stage'] ) ? awc_pretty_stage( $m['stage'] ) : '';
	$stageText = $group ? $group : $stage;

	$statusText = '';
	if ( $state === 'live' )     $statusText = ( $st === 'PAUSED' ) ? 'Half Time' : 'Live';
	if ( $state === 'finished' ) $statusText = 'Full Time';

	return array(
		'state'      => $state,
		'label'      => $stageText ? $stageText : ( $state === 'upcoming' ? 'Next Match' : 'World Cup' ),
		'home'       => array( 'name' => awc_team_name( $home ), 'flag' => isset( $home['crest'] ) ? $home['crest'] : '' ),
		'away'       => array( 'name' => awc_team_name( $away ), 'flag' => isset( $away['crest'] ) ? $away['crest'] : '' ),
		'score'      => array( 'home' => $ft['home'], 'away' => $ft['away'] ),
		'minute'     => $minute,
		'statusText' => $statusText,
		'stageText'  => $stageText,
		'utcDate'    => isset( $m['utcDate'] ) ? $m['utcDate'] : '',
		'updated'    => time(),
	);
}

function awc_team_name( $t ) {
	if ( empty( $t ) ) return 'TBD';
	if ( ! empty( $t['shortName'] ) ) return $t['shortName'];
	if ( ! empty( $t['name'] ) )      return $t['name'];
	if ( ! empty( $t['tla'] ) )       return $t['tla'];
	return 'TBD';
}

function awc_pretty_stage( $code ) {
	$map = array(
		'GROUP_STAGE'   => 'Group Stage',
		'LAST_16'       => 'Round of 16',
		'QUARTER_FINALS'=> 'Quarter-final',
		'SEMI_FINALS'   => 'Semi-final',
		'THIRD_PLACE'   => 'Third-place Play-off',
		'FINAL'         => 'Final',
		'PRELIMINARY'   => 'Preliminary',
		'PLAYOFFS'      => 'Play-offs',
	);
	if ( isset( $map[ $code ] ) ) return $map[ $code ];
	return ucwords( strtolower( str_replace( '_', ' ', (string) $code ) ) );
}

/* =========================================================================
 *  REST ENDPOINT  ▸  /wp-json/awc/v1/match
 * ========================================================================= */

add_action( 'rest_api_init', function () {
	register_rest_route( 'awc/v1', '/match', array(
		'methods'             => 'GET',
		'callback'            => function () { return rest_ensure_response( awc_fetch_match() ); },
		'permission_callback' => '__return_true',
	) );
} );

/* =========================================================================
 *  ADMIN SETTINGS  ▸  Settings → World Cup Hero
 * ========================================================================= */

add_action( 'admin_menu', function () {
	add_options_page( 'World Cup Hero', 'World Cup Hero', 'manage_options', 'awc-hero', 'awc_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'awc_hero', 'awc_api_key',      array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'awc_hero', 'awc_competition',  array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'awc_hero', 'awc_live_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
} );

function awc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$test = null;
	if ( isset( $_GET['awc_action'] ) && check_admin_referer( 'awc_action' ) ) {
		$act = sanitize_key( $_GET['awc_action'] );
		if ( $act === 'purge' ) {
			delete_option( 'awc_match_fresh' );   // force next request to refresh
			delete_transient( 'awc_lock' );
			echo '<div class="notice notice-success is-dismissible"><p>Live-data cache purged — next page view will fetch fresh data.</p></div>';
		} elseif ( $act === 'test' ) {
			$test = awc_fetch_match( true );
		}
	}

	$key_const = defined( 'AWC_FOOTBALL_API_KEY' ) && AWC_FOOTBALL_API_KEY;
	$endpoint  = rest_url( 'awc/v1/match' );
	$test_url  = wp_nonce_url( admin_url( 'options-general.php?page=awc-hero&awc_action=test' ),  'awc_action' );
	$purge_url = wp_nonce_url( admin_url( 'options-general.php?page=awc-hero&awc_action=purge' ), 'awc_action' );
	?>
	<div class="wrap">
		<h1>⚽ World Cup Hero — Live Data</h1>
		<p>Paste your free <a href="https://www.football-data.org/client/register" target="_blank" rel="noopener">football-data.org</a> API key below. The hero then switches itself to a 🔴 live score during a match, shows the 🏁 full-time result for a few hours after, ⏳ counts down to the next fixture, and shows the 🏆 last result when the tournament is idle. If the API is ever unreachable it quietly falls back to your manual countdown.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'awc_hero' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="awc_api_key">API Key</label></th>
					<td>
						<?php if ( $key_const ) : ?>
							<p><em>Defined in <code>wp-config.php</code> via <code>AWC_FOOTBALL_API_KEY</code> — this field is ignored.</em></p>
						<?php else : ?>
							<input name="awc_api_key" id="awc_api_key" type="text" class="regular-text" autocomplete="off"
							       value="<?php echo esc_attr( awc_opt( 'awc_api_key', '' ) ); ?>" placeholder="your football-data.org token">
							<p class="description">Stored in the database. For extra safety you may instead define <code>AWC_FOOTBALL_API_KEY</code> in <code>wp-config.php</code>.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="awc_competition">Competition Code</label></th>
					<td>
						<input name="awc_competition" id="awc_competition" type="text" class="small-text"
						       value="<?php echo esc_attr( awc_competition() ); ?>">
						<p class="description"><code>WC</code> = FIFA World Cup (default). Change only for testing other competitions.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Live Feed</th>
					<td>
						<input type="hidden" name="awc_live_enabled" value="0">
						<label><input name="awc_live_enabled" type="checkbox" value="1" <?php checked( awc_live_enabled() ); ?>> Enable automatic live data</label>
						<p class="description">Uncheck to force the manual countdown everywhere.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>Diagnostics</h2>
		<p>
			<a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary">Test connection</a>
			<a href="<?php echo esc_url( $purge_url ); ?>" class="button">Purge cache</a>
		</p>
		<p><strong>REST endpoint:</strong> <code><?php echo esc_html( $endpoint ); ?></code></p>

		<?php if ( $test !== null ) : ?>
			<h3>Test result</h3>
			<?php if ( $test['state'] === 'error' ) : ?>
				<div class="notice notice-error inline"><p>API error: <code><?php echo esc_html( isset( $test['detail'] ) ? $test['detail'] : 'unknown' ); ?></code> — check the key &amp; competition code.</p></div>
			<?php elseif ( $test['state'] === 'off' ) : ?>
				<div class="notice notice-warning inline"><p>No key set, or the competition returned no matches.</p></div>
			<?php else : ?>
				<table class="widefat striped" style="max-width:640px">
					<tbody>
						<tr><td><strong>State</strong></td><td><?php echo esc_html( strtoupper( $test['state'] ) ); ?></td></tr>
						<tr><td><strong>Match</strong></td><td><?php echo esc_html( $test['home']['name'] . '  vs  ' . $test['away']['name'] ); ?></td></tr>
						<?php if ( $test['score']['home'] !== null ) : ?>
							<tr><td><strong>Score</strong></td><td><?php echo esc_html( $test['score']['home'] . ' – ' . $test['score']['away'] ); ?><?php echo $test['minute'] ? ' (' . esc_html( $test['minute'] ) . "')" : ''; ?></td></tr>
						<?php endif; ?>
						<tr><td><strong>Stage</strong></td><td><?php echo esc_html( $test['stageText'] ?: '—' ); ?></td></tr>
						<?php if ( $test['utcDate'] ) : ?>
							<tr><td><strong>Kick-off (UTC)</strong></td><td><?php echo esc_html( $test['utcDate'] ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<hr>
		<p style="color:#777"><strong>WP Rocket note:</strong> add <code>awc-hero</code> to “Delay JavaScript Execution” exclusions, then purge WP Rocket cache, so the live sync &amp; parallax run on desktop.</p>
	</div>
	<?php
}

/* =========================================================================
 *  SHORTCODE
 * ========================================================================= */

add_shortcode( 'aun_worldcup_hero', 'aun_wc_hero_shortcode' );

/**
 * Attributes (all optional):
 *   badge, title (wrap a word in {braces} for gold), subtitle, btn1_text/btn1_link, btn2_text/btn2_link
 *   kickoff      – ISO datetime to count down to, e.g. "2026-06-11T11:00:00"
 *   match_label  – small label above the matchup
 *   home / away  – team names ; home_flag / away_flag – flag emojis (leave home/away empty to hide the matchup)
 *   venue        – stadium / city line
 *   height       – leave empty for full-screen (default) or set e.g. "640px"
 *   full         – "1" full-bleed width (default), "0" stay in container
 *   live         – "auto" (default: live feed if configured), "off" (always manual), "on" (force live feed)
 */
function aun_wc_hero_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'badge'       => '⚽ FIFA World Cup 2026',
		'title'       => 'Watch the World Cup on the {Big Screen}',
		'subtitle'    => 'Turn any wall into a stadium. AUN projectors bring every match to life in 100–150″ of Full HD &amp; 4K — with official warranty and 0% EMI.',
		'btn1_text'   => 'Shop Projectors',
		'btn1_link'   => '/projector-price/',
		'btn2_text'   => 'Find Your Match',
		'btn2_link'   => '/projector-finder/',
		'kickoff'     => '2026-06-11T11:00:00',
		'match_label' => 'Opening Match',
		'home'        => 'Mexico',
		'home_flag'   => '🇲🇽',
		'away'        => 'Canada',
		'away_flag'   => '🇨🇦',
		'venue'       => 'Estadio Azteca, Mexico City',
		'height'      => '',
		'full'        => '1',
		'live'        => 'auto',
	), $atts, 'aun_worldcup_hero' );

	$title_html = preg_replace( '/\{(.+?)\}/', '<span>$1</span>', esc_html( $a['title'] ) );
	$full_class = ( $a['full'] === '1' ) ? ' awc-full' : '';
	$mh = ( $a['height'] !== '' ) ? 'min-height:' . esc_attr( $a['height'] ) . ';' : '';

	$live_on  = ( $a['live'] !== 'off' ) && awc_live_enabled() && awc_api_key();
	$endpoint = esc_url( rest_url( 'awc/v1/match' ) );

	$ambient = '';
	for ( $i = 0; $i < 16; $i++ ) {
		$ambient .= '<span class="awc-mote" style="left:' . wp_rand(2,97) . '%;bottom:' . wp_rand(0,60) . '%;width:' . wp_rand(2,6) . 'px;height:' . wp_rand(2,6) . 'px;opacity:' . ( wp_rand(20,55)/100 ) . ';animation-duration:' . wp_rand(14,28) . 's;animation-delay:' . wp_rand(0,16) . 's;"></span>';
	}
	$dust = '';
	for ( $i = 0; $i < 12; $i++ ) {
		$dust .= '<span class="awc-dust" style="left:' . wp_rand(14,86) . '%;top:' . wp_rand(8,92) . '%;width:' . wp_rand(2,5) . 'px;height:' . wp_rand(2,5) . 'px;animation-duration:' . wp_rand(6,13) . 's;animation-delay:' . wp_rand(0,9) . 's;"></span>';
	}

	ob_start();

	static $css_done = false;
	if ( ! $css_done ) : $css_done = true; ?>
<style id="awc-hero-css" data-no-optimize="1" data-no-minify="1">
.awc-hero{position:relative;overflow:hidden;width:100%;display:flex;align-items:center;isolation:isolate;
  min-height:100vh;min-height:100svh;background:#04070f;color:#fff;
  font-family:'Inter',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
.awc-hero.awc-full{width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);}
.awc-hero *{box-sizing:border-box;}

.awc-room{position:absolute;inset:0;z-index:0;pointer-events:none;
  background:
   radial-gradient(70% 55% at 72% 16%, rgba(1,136,254,.20), transparent 60%),
   radial-gradient(55% 45% at 22% 92%, rgba(255,188,0,.08), transparent 60%),
   radial-gradient(140% 90% at 50% 130%, rgba(7,18,38,.9), #04070f 72%),
   linear-gradient(180deg,#070e1c,#04070f);}
.awc-grid{position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.10;
  background-image:linear-gradient(rgba(120,170,255,.5) 1px,transparent 1px),linear-gradient(90deg,rgba(120,170,255,.5) 1px,transparent 1px);
  background-size:46px 46px;mask-image:radial-gradient(80% 60% at 50% 40%,#000 30%,transparent 75%);
  -webkit-mask-image:radial-gradient(80% 60% at 50% 40%,#000 30%,transparent 75%);}
.awc-mote{position:absolute;z-index:1;border-radius:50%;pointer-events:none;will-change:transform,opacity;
  background:radial-gradient(circle,#cfe6ff,rgba(207,230,255,0) 70%);animation:awcMote linear infinite;}
@keyframes awcMote{0%{transform:translateY(34px);opacity:0}12%{opacity:1}88%{opacity:1}100%{transform:translateY(-170px);opacity:0}}
.awc-vignette{position:absolute;inset:0;z-index:6;pointer-events:none;
  background:radial-gradient(135% 105% at 50% 42%,transparent 46%,rgba(0,0,0,.62) 100%);}
.awc-grain{position:absolute;inset:0;z-index:6;pointer-events:none;opacity:.05;mix-blend-mode:overlay;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");}

.awc-stage{position:relative;z-index:3;width:100%;max-width:1180px;margin:0 auto;padding:104px 26px 72px;
  display:grid;grid-template-columns:1.02fr 1.05fr;gap:42px;align-items:center;}
@media(max-width:880px){.awc-stage{grid-template-columns:1fr;gap:32px;padding:96px 20px 60px;text-align:center;}}

.awc-content{will-change:transform,opacity;animation:awcUp .9s ease .1s both;}
.awc-badge{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#ffce4d,#ffbc00);color:#0a1730;
  font-weight:800;font-size:12px;letter-spacing:1.4px;padding:8px 16px;border-radius:999px;text-transform:uppercase;box-shadow:0 8px 24px rgba(255,188,0,.4);}
.awc-title{font-size:clamp(32px,5.2vw,58px);line-height:1.03;font-weight:800;letter-spacing:-1.2px;margin:18px 0 14px;text-shadow:0 6px 34px rgba(0,0,0,.55);}
.awc-title span{background:linear-gradient(105deg,#ffe08a,#ffbc00 45%,#ff9d00);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:#ffbc00;}
.awc-sub{color:#cdddf0;font-size:clamp(15px,2.2vw,18px);line-height:1.6;max-width:540px;margin:0 0 26px;}
@media(max-width:880px){.awc-sub{margin-left:auto;margin-right:auto;}}
.awc-cta{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:22px;}
@media(max-width:880px){.awc-cta{justify-content:center;}}
.awc-btn{display:inline-flex;align-items:center;gap:8px;padding:15px 26px;border-radius:12px;font-weight:700;font-size:15px;text-decoration:none;line-height:1;transition:transform .16s ease,box-shadow .16s ease,background .16s ease;}
.awc-btn-primary{background:linear-gradient(135deg,#22a0ff,#0188fe);color:#fff;box-shadow:0 14px 30px rgba(1,136,254,.45);}
.awc-btn-primary:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(1,136,254,.6);color:#fff;}
.awc-btn-ghost{background:rgba(255,255,255,.07);color:#fff;border:1px solid rgba(255,255,255,.32);backdrop-filter:blur(6px);}
.awc-btn-ghost:hover{background:rgba(255,255,255,.15);transform:translateY(-2px);color:#fff;}
.awc-meta{display:flex;flex-wrap:wrap;gap:8px 18px;color:#9fb6d0;font-size:13px;font-weight:600;}
@media(max-width:880px){.awc-meta{justify-content:center;}}
.awc-meta span{display:inline-flex;align-items:center;gap:7px;}
.awc-meta i{color:#34d399;}

.awc-cinema{position:relative;perspective:1500px;min-height:330px;will-change:transform;}
@media(max-width:880px){.awc-cinema{min-height:280px;}}
.awc-bias{position:absolute;z-index:1;left:50%;top:42%;width:78%;height:74%;transform:translate(-50%,-50%);border-radius:50%;
  filter:blur(64px);opacity:.85;will-change:transform;
  background:radial-gradient(circle,rgba(1,136,254,.62),rgba(255,188,0,.22) 56%,transparent 72%);
  animation:awcBias 16s ease-in-out infinite alternate;}
@keyframes awcBias{0%{transform:translate(-54%,-50%) scale(1);filter:blur(64px) hue-rotate(0deg)}100%{transform:translate(-46%,-50%) scale(1.14);filter:blur(76px) hue-rotate(28deg)}}

.awc-screen{position:relative;z-index:3;width:100%;aspect-ratio:16/9;border-radius:16px;overflow:hidden;
  border:1px solid rgba(255,255,255,.10);background:#081226;transform:rotateY(-13deg) rotateX(3deg);transition:transform .25s ease;
  will-change:transform;box-shadow:0 0 0 1px rgba(255,255,255,.06),0 46px 100px rgba(1,136,254,.32),0 16px 44px rgba(0,0,0,.6);
  animation:awcScreenOn 1s ease .15s both;}
.awc-match{position:absolute;inset:0;overflow:hidden;background:#0b3a20;}
.awc-pitch{position:absolute;inset:0;background:repeating-linear-gradient(90deg,#1d8c49 0 9%,#178140 9% 18%);}
.awc-pitch::before{content:"";position:absolute;inset:6%;border:2px solid rgba(255,255,255,.5);border-radius:3px;}
.awc-pitch::after{content:"";position:absolute;left:50%;top:6%;bottom:6%;width:2px;margin-left:-1px;background:rgba(255,255,255,.5);}
.awc-circle{position:absolute;left:50%;top:50%;width:22%;aspect-ratio:1;transform:translate(-50%,-50%);border:2px solid rgba(255,255,255,.5);border-radius:50%;}
.awc-spot{position:absolute;left:50%;top:50%;width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.7);transform:translate(-50%,-50%);}
.awc-box{position:absolute;top:50%;transform:translateY(-50%);height:52%;width:12%;border:2px solid rgba(255,255,255,.5);}
.awc-box-l{left:6%;border-left:0;}
.awc-box-r{right:6%;border-right:0;}
.awc-box::before{content:"";position:absolute;top:50%;transform:translateY(-50%);height:46%;width:40%;border:2px solid rgba(255,255,255,.5);}
.awc-box-l::before{left:0;border-left:0;}
.awc-box-r::before{right:0;border-right:0;}
.awc-floods{position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(28% 46% at 12% -10%,rgba(228,242,255,.5),transparent 60%),radial-gradient(28% 46% at 88% -10%,rgba(228,242,255,.5),transparent 60%);}
.awc-fade{position:absolute;inset:0;pointer-events:none;background:radial-gradient(125% 95% at 50% 34%,transparent 50%,rgba(0,0,0,.40));}
.awc-sheen{position:absolute;inset:0;transform:translateX(-120%);background:linear-gradient(115deg,transparent 32%,rgba(255,255,255,.14) 48%,transparent 62%);animation:awcSheen 8s ease-in-out infinite;}
@keyframes awcSheen{0%,68%{transform:translateX(-120%)}100%{transform:translateX(120%)}}

/* glass scoreboard */
.awc-board{position:absolute;z-index:4;left:50%;top:50%;transform:translate(-50%,-50%);width:min(86%,380px);
  padding:16px 18px;border-radius:16px;text-align:center;background:rgba(7,13,26,.58);
  -webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.14);
  box-shadow:0 26px 64px rgba(0,0,0,.55);animation:awcScreenOn 1s ease .55s both;}
.awc-bk{font-size:10px;font-weight:800;letter-spacing:1.6px;color:#ffce4d;text-transform:uppercase;}
.awc-mrow{display:flex;align-items:center;justify-content:center;gap:10px;margin:10px 0 6px;}
.awc-team{display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:14px;color:#fff;flex:1;min-width:0;}
.awc-team-h{justify-content:flex-end;text-align:right;}
.awc-team-a{justify-content:flex-start;text-align:left;}
.awc-team .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.awc-team .flag{font-size:18px;line-height:1;flex:none;}
.awc-team .flag img{display:block;width:24px;height:16px;object-fit:cover;border-radius:2px;box-shadow:0 1px 3px rgba(0,0,0,.45);}
.awc-team.win{color:#ffd76a;}
.awc-team.win .nm::after{content:"";}
.awc-vs{font-size:10px;font-weight:800;color:#9fb6d0;background:rgba(255,255,255,.09);padding:3px 8px;border-radius:6px;flex:none;}
.awc-score{display:none;font-size:25px;font-weight:800;color:#fff;line-height:1;font-variant-numeric:tabular-nums;padding:0 4px;flex:none;white-space:nowrap;}
.awc-status{display:none;width:fit-content;margin:0 auto;font-size:10px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;padding:4px 12px;border-radius:999px;}
.awc-status.is-live{display:block;color:#fff;background:linear-gradient(135deg,#ff4b4b,#d61f1f);box-shadow:0 6px 18px rgba(214,31,31,.5);}
.awc-status.is-ft{display:block;color:#0a1730;background:linear-gradient(135deg,#ffce4d,#ffbc00);box-shadow:0 6px 18px rgba(255,188,0,.4);}
.awc-status .dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:7px;vertical-align:middle;animation:awcPulse 1.2s ease-in-out infinite;}
@keyframes awcPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.65)}}
.awc-statline{display:none;font-size:11px;font-weight:700;letter-spacing:.4px;color:#aec4dd;margin:4px 0 2px;}
.awc-clabel{font-size:9px;font-weight:700;letter-spacing:2px;color:#aec4dd;text-transform:uppercase;margin:8px 0 8px;}
.awc-count{display:flex;justify-content:center;gap:7px;}
.awc-unit{flex:1;max-width:62px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);border-radius:10px;padding:8px 2px;}
.awc-unit b{display:block;font-size:clamp(18px,4.5vw,25px);font-weight:800;color:#fff;line-height:1;font-variant-numeric:tabular-nums;}
.awc-unit span{display:block;font-size:9px;font-weight:700;letter-spacing:.4px;color:#9fb6d0;text-transform:uppercase;margin-top:5px;}
.awc-venue{margin-top:13px;font-size:11px;color:#cdddf0;font-weight:600;}
.awc-venue i{color:#ff5a52;margin-right:5px;}
.awc-livenow{display:none;font-size:17px;font-weight:800;color:#34d399;letter-spacing:1px;margin-top:6px;}

/* goal celebration */
.awc-goal{position:absolute;z-index:7;left:50%;top:40%;transform:translate(-50%,-50%) scale(.5);pointer-events:none;opacity:0;
  display:flex;align-items:center;gap:10px;
  font-size:clamp(26px,7vw,54px);font-weight:900;letter-spacing:1px;text-transform:uppercase;white-space:nowrap;}
.awc-goal .ball{filter:drop-shadow(0 4px 14px rgba(0,0,0,.5));animation:awcBall .55s ease-in-out infinite alternate;}
.awc-goal .txt{text-shadow:0 4px 26px rgba(0,0,0,.45);
  background:linear-gradient(105deg,#ffe08a,#ffbc00 50%,#ff9d00);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:#ffbc00;}
@keyframes awcBall{from{transform:rotate(-13deg) scale(.95)}to{transform:rotate(13deg) scale(1.08)}}
.awc-goal.show{animation:awcGoal 2.4s cubic-bezier(.2,.9,.2,1) forwards;}
@keyframes awcGoal{0%{opacity:0;transform:translate(-50%,-50%) scale(.5)}14%{opacity:1;transform:translate(-50%,-50%) scale(1.18)}30%{transform:translate(-50%,-50%) scale(1)}72%{opacity:1}100%{opacity:0;transform:translate(-50%,-50%) scale(1.06)}}
.awc-confetti{position:absolute;z-index:7;top:40%;width:10px;height:16px;border-radius:2px;pointer-events:none;opacity:0;box-shadow:0 1px 3px rgba(0,0,0,.35);will-change:transform,opacity;}
@keyframes awcConf{0%{opacity:1;transform:translate(0,0) rotate(0)}100%{opacity:0;transform:translate(var(--dx),var(--dy)) rotate(var(--rot))}}
.awc-board.goal{animation:awcBoardGoal 1s ease;}
@keyframes awcBoardGoal{0%,100%{box-shadow:0 26px 64px rgba(0,0,0,.55)}25%,60%{box-shadow:0 0 0 3px rgba(255,188,0,.95),0 0 54px rgba(255,188,0,.8),0 26px 64px rgba(0,0,0,.55)}}

.awc-reflect{position:absolute;z-index:2;left:6%;right:6%;top:100%;height:30%;border-radius:16px;
  background:linear-gradient(180deg,rgba(1,136,254,.28),transparent 70%);filter:blur(14px);transform:scaleY(-1);opacity:.5;}

.awc-beam{position:absolute;z-index:4;left:50%;bottom:-7%;width:96%;height:118%;transform:translateX(-50%);
  pointer-events:none;mix-blend-mode:screen;filter:blur(3px);opacity:0;will-change:opacity;
  clip-path:polygon(48% 100%,52% 100%,90% 0,10% 0);
  background:linear-gradient(0deg,rgba(150,200,255,.05),rgba(173,214,255,.20) 34%,rgba(255,255,255,.07) 74%,transparent);
  animation:awcBeamOn 1.3s ease .4s forwards,awcFlicker 5.5s ease-in-out 1.7s infinite;}
@keyframes awcBeamOn{to{opacity:.6}}
@keyframes awcFlicker{0%,100%{opacity:.5}45%{opacity:.72}70%{opacity:.58}}
.awc-dust{position:absolute;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.95),transparent 70%);pointer-events:none;animation:awcDust linear infinite;will-change:transform,opacity;}
@keyframes awcDust{0%{transform:translateY(8px) translateX(0);opacity:0}15%{opacity:.9}85%{opacity:.9}100%{transform:translateY(-46px) translateX(8px);opacity:0}}

.awc-projector{position:absolute;z-index:6;left:50%;bottom:-34px;transform:translateX(-50%);width:108px;height:52px;animation:awcScreenOn 1s ease .35s both;}
.awc-projector::after{content:"";position:absolute;left:50%;top:44px;width:128px;height:24px;transform:translateX(-50%);background:radial-gradient(ellipse at center,rgba(90,176,255,.34),transparent 70%);filter:blur(5px);}
.awc-pj-lens{position:absolute;left:50%;top:9px;transform:translateX(-50%);width:34px;height:34px;border-radius:50%;z-index:1;
  background:radial-gradient(circle at 50% 36%,#eaf6ff 0%,#86c4ff 40%,#1f74d6 72%,#0a2a52 100%);border:2px solid #2c3e62;
  box-shadow:0 -8px 34px 9px rgba(90,176,255,.6),0 0 16px 4px rgba(90,176,255,.4);animation:awcLens 5.5s ease-in-out infinite;}
@keyframes awcLens{0%,100%{box-shadow:0 -7px 28px 7px rgba(90,176,255,.5),0 0 14px 3px rgba(90,176,255,.34)}50%{box-shadow:0 -9px 40px 12px rgba(90,176,255,.72),0 0 20px 5px rgba(90,176,255,.46)}}
.awc-pj-body{position:absolute;left:0;right:0;bottom:0;height:38px;border-radius:12px;z-index:2;
  background:linear-gradient(180deg,#2a3a58 0%,#172742 58%,#0d1828 100%);border:1px solid rgba(255,255,255,.12);
  box-shadow:0 12px 28px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.14);}
.awc-pj-body::before{content:"";position:absolute;right:13px;top:11px;width:20px;height:6px;border-radius:3px;background:repeating-linear-gradient(90deg,rgba(255,255,255,.22) 0 2px,transparent 2px 4px);}
.awc-pj-body::after{content:"";position:absolute;left:15px;top:12px;width:6px;height:6px;border-radius:50%;background:#34d399;box-shadow:0 0 9px #34d399;}

@keyframes awcUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
@keyframes awcScreenOn{from{opacity:0}to{opacity:1}}

@media(prefers-reduced-motion:reduce){
  .awc-mote,.awc-bias,.awc-sheen,.awc-beam,.awc-dust,.awc-projector,.awc-screen,.awc-board,.awc-content,.awc-status .dot{animation:none !important;}
  .awc-beam{opacity:.55 !important;}
}
/* tablets: keep the card comfortably inside the screen */
@media(max-width:740px){
  .awc-board{width:min(92%,360px);padding:14px 14px;}
  .awc-unit{max-width:58px;}
}
/* phones: let the screen grow to fit the card (no more overflow) and flow the card in normal layout */
@media(max-width:600px){
  .awc-bias{filter:blur(46px);} .awc-grid{display:none;}
  .awc-screen{aspect-ratio:auto;min-height:230px;transform:none !important;
    display:flex;align-items:center;justify-content:center;padding:26px 14px;}
  .awc-board{position:relative;left:auto;top:auto;transform:none;width:86%;max-width:310px;margin:0 auto;}
  .awc-cinema{min-height:auto;}
  .awc-score{font-size:23px;}
  .awc-team{font-size:13px;}
}
@media(max-width:380px){
  .awc-board{padding:13px 11px;}
  .awc-count{gap:5px;} .awc-unit{padding:7px 1px;}
}
</style>
<?php endif; ?>

<section class="awc-hero<?php echo $full_class; ?>" style="<?php echo $mh; ?>"
         data-awc-live="<?php echo $live_on ? '1' : '0'; ?>"
         data-awc-endpoint="<?php echo $endpoint; ?>"
         data-awc-poll="60000">
	<div class="awc-room" aria-hidden="true"></div>
	<div class="awc-grid" aria-hidden="true"></div>
	<?php echo $ambient; ?>

	<div class="awc-stage">
		<div class="awc-content">
			<span class="awc-badge"><?php echo esc_html( $a['badge'] ); ?></span>
			<div class="awc-title"><?php echo $title_html; ?></div>
			<p class="awc-sub"><?php echo wp_kses_post( $a['subtitle'] ); ?></p>
			<div class="awc-cta">
				<a class="awc-btn awc-btn-primary" href="<?php echo esc_url( $a['btn1_link'] ); ?>"><?php echo esc_html( $a['btn1_text'] ); ?> <span aria-hidden="true">&rarr;</span></a>
				<a class="awc-btn awc-btn-ghost" href="<?php echo esc_url( $a['btn2_link'] ); ?>"><?php echo esc_html( $a['btn2_text'] ); ?></a>
			</div>
			<div class="awc-meta">
				<span><i class="fa-solid fa-shield-halved"></i> Official Warranty</span>
				<span><i class="fa-solid fa-bolt"></i> 0% EMI</span>
				<span><i class="fa-solid fa-truck"></i> Fast Delivery</span>
			</div>
		</div>

		<div class="awc-cinema">
			<div class="awc-bias" aria-hidden="true"></div>
			<div class="awc-screen">
				<div class="awc-match" aria-hidden="true">
					<div class="awc-pitch"><span class="awc-circle"></span><span class="awc-spot"></span><span class="awc-box awc-box-l"></span><span class="awc-box awc-box-r"></span></div>
					<div class="awc-floods"></div>
					<div class="awc-fade"></div>
					<div class="awc-sheen"></div>
				</div>
				<div class="awc-board" data-kickoff="<?php echo esc_attr( $a['kickoff'] ); ?>">
					<div class="awc-bk">⚽ <?php echo esc_html( $a['match_label'] ); ?></div>
					<div class="awc-status" aria-hidden="true"></div>
					<?php if ( $a['home'] !== '' && $a['away'] !== '' ) : ?>
					<div class="awc-mrow">
						<span class="awc-team awc-team-h"><span class="flag"><?php echo esc_html( $a['home_flag'] ); ?></span> <span class="nm"><?php echo esc_html( $a['home'] ); ?></span></span>
						<span class="awc-vs">VS</span>
						<span class="awc-score" aria-hidden="true"></span>
						<span class="awc-team awc-team-a"><span class="nm"><?php echo esc_html( $a['away'] ); ?></span> <span class="flag"><?php echo esc_html( $a['away_flag'] ); ?></span></span>
					</div>
					<?php endif; ?>
					<div class="awc-statline" aria-hidden="true"></div>
					<div class="awc-cwrap">
						<div class="awc-clabel">Kick-off in</div>
						<div class="awc-count">
							<div class="awc-unit"><b data-d>00</b><span>Days</span></div>
							<div class="awc-unit"><b data-h>00</b><span>Hrs</span></div>
							<div class="awc-unit"><b data-m>00</b><span>Min</span></div>
							<div class="awc-unit"><b data-s>00</b><span>Sec</span></div>
						</div>
					</div>
					<div class="awc-livenow">🔴 LIVE NOW</div>
					<?php if ( $a['venue'] !== '' ) : ?><div class="awc-venue"><i class="fa-solid fa-location-dot"></i> <?php echo esc_html( $a['venue'] ); ?></div><?php endif; ?>
				</div>
				<div class="awc-goal" aria-hidden="true"><span class="ball">⚽</span><span class="txt">GOAL!</span></div>
			</div>
			<div class="awc-reflect" aria-hidden="true"></div>
			<div class="awc-beam" aria-hidden="true"><?php echo $dust; ?></div>
			<div class="awc-projector" aria-hidden="true"><span class="awc-pj-lens"></span><span class="awc-pj-body"></span></div>
		</div>
	</div>

	<div class="awc-vignette" aria-hidden="true"></div>
	<div class="awc-grain" aria-hidden="true"></div>
</section>

<?php
	static $js_done = false;
	if ( ! $js_done ) : $js_done = true; ?>
<script id="awc-hero-js" data-no-optimize="1" data-no-defer="1" data-cfasync="false">
(function(){
  function pad(n){ return (n<10?'0':'') + n; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

  /* ---- manual / API-driven countdown ---- */
  function countdown(hero){
    var b = hero.querySelector('.awc-board'); if(!b) return;
    var t = b.getAttribute('data-kickoff'); if(!t) return;
    var end = new Date(t).getTime(); if(isNaN(end)) return;
    if(b.__timer) clearInterval(b.__timer);
    var d=b.querySelector('[data-d]'),h=b.querySelector('[data-h]'),m=b.querySelector('[data-m]'),s=b.querySelector('[data-s]');
    var wrap=b.querySelector('.awc-cwrap'), live=b.querySelector('.awc-livenow');
    var liveData = hero.getAttribute('data-awc-live')==='1';
    function tick(){
      var diff = end - Date.now();
      if(diff <= 0){
        if(wrap)wrap.style.display='none';
        if(live && !liveData) live.style.display='block';   // manual placeholder only when API is off
        clearInterval(b.__timer); b.__timer=null; return;
      }
      var x=Math.floor(diff/1000);
      var dd=Math.floor(x/86400); x-=dd*86400;
      var hh=Math.floor(x/3600);  x-=hh*3600;
      var mm=Math.floor(x/60);    x-=mm*60;
      if(d)d.textContent=pad(dd); if(h)h.textContent=pad(hh); if(m)m.textContent=pad(mm); if(s)s.textContent=pad(x);
    }
    if(wrap)wrap.style.display=''; if(live)live.style.display='none';
    tick(); b.__timer=setInterval(tick,1000);
  }

  /* ---- live data sync ---- */
  function setTeam(el, t, awaySide){
    if(!el||!t) return;
    var flag='';
    if(t.flag){ flag = (/^https?:\/\//.test(t.flag))
      ? '<span class="flag"><img src="'+esc(t.flag)+'" alt="" loading="lazy"></span>'
      : '<span class="flag">'+esc(t.flag)+'</span>'; }
    var name='<span class="nm">'+esc(t.name)+'</span>';
    el.classList.remove('win');
    el.innerHTML = awaySide ? (name+' '+flag) : (flag+' '+name);
  }

  function celebrate(hero){
    if(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var screen=hero.querySelector('.awc-screen'); if(!screen) return;
    var burst=hero.querySelector('.awc-cinema')||screen;   // not clipped → confetti flies across the whole TV
    var g=screen.querySelector('.awc-goal');
    if(g){ g.classList.remove('show'); void g.offsetWidth; g.classList.add('show'); setTimeout(function(){g.classList.remove('show');},2500); }
    var board=hero.querySelector('.awc-board');
    if(board){ board.classList.remove('goal'); void board.offsetWidth; board.classList.add('goal'); setTimeout(function(){board.classList.remove('goal');},1000); }
    var colors=['#ffbc00','#22a0ff','#ff4b4b','#34d399','#ffffff'];
    for(var i=0;i<22;i++){ (function(i){
      var c=document.createElement('span'); c.className='awc-confetti';
      c.style.left=(30+Math.random()*40)+'%';
      c.style.background=colors[i%colors.length];
      c.style.setProperty('--dx',((Math.random()*2-1)*200)+'px');
      c.style.setProperty('--dy',(90+Math.random()*170)+'px');
      c.style.setProperty('--rot',((Math.random()*720)-360)+'deg');
      c.style.animation='awcConf '+(1.1+Math.random()*0.8)+'s ease-out '+(Math.random()*0.15)+'s forwards';
      burst.appendChild(c);
      setTimeout(function(){ if(c.parentNode) c.parentNode.removeChild(c); },2300);
    })(i); }
  }

  function applyState(hero, dat){
    var b=hero.querySelector('.awc-board'); if(!b||!dat||!dat.state) return;
    if(dat.state==='off'||dat.state==='error') return;   // keep manual fallback running

    var vs=b.querySelector('.awc-vs'), score=b.querySelector('.awc-score'),
        wrap=b.querySelector('.awc-cwrap'), live=b.querySelector('.awc-livenow'),
        status=b.querySelector('.awc-status'), statline=b.querySelector('.awc-statline'),
        bk=b.querySelector('.awc-bk'), teams=b.querySelectorAll('.awc-team');

    if(dat.home && dat.away && teams.length>=2){ setTeam(teams[0],dat.home,false); setTeam(teams[1],dat.away,true); }
    if(dat.label && bk) bk.innerHTML='⚽ '+esc(dat.label);

    // GOAL! detection — celebrate when the score increases between polls
    if((dat.state==='live'||dat.state==='finished') && dat.score && dat.score.home!=null && dat.score.away!=null){
      var prev=b.__score;
      if(prev && (dat.score.home>prev.h || dat.score.away>prev.a)) celebrate(hero);
      b.__score={h:dat.score.home,a:dat.score.away};
    } else if(dat.state==='upcoming'){
      b.__score=null;   // reset so the next match starts fresh
    }

    function showScore(){ if(vs)vs.style.display='none'; if(score){score.style.display='inline-block'; score.textContent=(dat.score.home==null?'0':dat.score.home)+' - '+(dat.score.away==null?'0':dat.score.away);} }
    function hideScore(){ if(vs)vs.style.display=''; if(score)score.style.display='none'; }

    if(dat.state==='live'){
      if(b.__timer){clearInterval(b.__timer); b.__timer=null;}
      if(wrap)wrap.style.display='none'; if(live)live.style.display='none';
      showScore();
      if(status){status.className='awc-status is-live'; status.innerHTML='<span class="dot"></span>'+esc(dat.statusText||'Live');}
      if(statline){statline.style.display='block'; statline.textContent = dat.minute ? (dat.minute+"'  ·  "+(dat.stageText||'')) : (dat.stageText||'');}
    } else if(dat.state==='finished'){
      if(b.__timer){clearInterval(b.__timer); b.__timer=null;}
      if(wrap)wrap.style.display='none'; if(live)live.style.display='none';
      showScore();
      if(status){status.className='awc-status is-ft'; status.textContent='Full Time';}
      if(statline){statline.style.display='block'; statline.textContent=dat.stageText||'';}
      if(teams.length>=2 && dat.score.home!=null && dat.score.away!=null){
        if(dat.score.home>dat.score.away) teams[0].classList.add('win');
        else if(dat.score.away>dat.score.home) teams[1].classList.add('win');
      }
    } else if(dat.state==='upcoming'){
      if(status){status.className='awc-status'; status.style.display='none';}
      hideScore();
      if(statline){ if(dat.stageText){statline.style.display='block'; statline.textContent=dat.stageText;} else statline.style.display='none'; }
      if(dat.utcDate){ b.setAttribute('data-kickoff', dat.utcDate); }
      countdown(hero);   // (re)start ticking toward the real next fixture
    }
  }

  function liveSync(hero){
    if(hero.__awcLive) return; hero.__awcLive = true;
    var ep=hero.getAttribute('data-awc-endpoint'); if(!ep) return;
    var poll=parseInt(hero.getAttribute('data-awc-poll'),10)||60000;
    function pull(){
      fetch(ep,{headers:{'Accept':'application/json'}})
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(d){ if(d) applyState(hero,d); })
        .catch(function(){});
    }
    pull(); setInterval(pull, poll);
  }

  /* ---- parallax / tilt (unchanged) ---- */
  function parallax(hero){
    if(hero.__awc) return; hero.__awc = true;
    var reduce  = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var room=hero.querySelector('.awc-room'),content=hero.querySelector('.awc-content'),
        cinema=hero.querySelector('.awc-cinema'),screen=hero.querySelector('.awc-screen'),
        bias=hero.querySelector('.awc-bias'),beam=hero.querySelector('.awc-beam');
    var base='rotateY(-13deg) rotateX(3deg)', ticking=false, mx=0, my=0, visible=true;
    function frame(){
      ticking=false; if(!visible||reduce) return;
      var r=hero.getBoundingClientRect(), vh=window.innerHeight||1;
      var p=(r.top + r.height/2 - vh/2)/vh;
      if(room)    room.style.transform   = 'translateY('+(p*60)+'px)';
      if(cinema)  cinema.style.transform  = 'translateY('+(p*-120)+'px) scale('+(1-p*0.05).toFixed(3)+')';
      if(content){content.style.transform = 'translateY('+(p*84)+'px)'; content.style.opacity=String(Math.max(0,1-Math.abs(p)*0.9));}
      if(beam)    beam.style.opacity       = String(Math.max(0.25,0.6-Math.abs(p)*0.45));
      if(screen)  screen.style.transform   = 'rotateY('+(-13+mx*12)+'deg) rotateX('+(3+my*-7)+'deg)';
      if(bias)    bias.style.transform     = 'translate(calc(-50% + '+(mx*-26)+'px), calc(-50% + '+(my*-18)+'px))';
    }
    function req(){ if(!ticking){ ticking=true; requestAnimationFrame(frame); } }
    function move(e){ var r=hero.getBoundingClientRect(); mx=(e.clientX-r.left)/r.width-0.5; my=(e.clientY-r.top)/r.height-0.5; req(); }
    function leave(){ mx=0; my=0; if(screen)screen.style.transform=base; if(bias)bias.style.transform='translate(-50%,-50%)'; req(); }
    window.addEventListener('scroll', req, {passive:true});
    window.addEventListener('resize', req);
    hero.addEventListener('mousemove', move);
    hero.addEventListener('mouseleave', leave);
    if('IntersectionObserver' in window){ new IntersectionObserver(function(es){ visible=es[0].isIntersecting; if(visible)req(); },{threshold:0}).observe(hero); }
    req();
  }

  function boot(){
    document.querySelectorAll('.awc-hero').forEach(function(h){
      parallax(h);
      countdown(h);
      if(h.getAttribute('data-awc-live')==='1') liveSync(h);
    });
  }
  if(document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
  window.addEventListener('load', boot);

  // Preview the full goal celebration from the console: awcTestGoal()
  window.awcTestGoal = function(){ document.querySelectorAll('.awc-hero').forEach(function(h){ celebrate(h); }); };
})();
</script>
<?php endif;

	return ob_get_clean();
}
