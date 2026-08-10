<?php
/**
 * App content: firmware files, user manuals, video guides and tips & tricks,
 * each optionally tied to a product model (model_id 0 = shown for every model).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Content {

	// Content types offered when ADDING content. 'tip' is retired (the app's
	// Help tab replaced it) — existing tip rows still display/manage in admin
	// and the app, but no new tips can be created.
	const TYPES = array( 'firmware', 'manual', 'video' );

	/**
	 * Extract a YouTube video id from the usual URL shapes ('' if not YouTube).
	 *
	 * @param string $url Video URL.
	 * @return string
	 */
	public static function youtube_id( $url ) {
		$url = (string) $url;

		// Path-based shapes: youtu.be/ID, /embed/ID, /shorts/ID, /live/ID, /v/ID
		// (also the youtube-nocookie.com domain).
		if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m ) ) {
			return $m[1];
		}

		// watch URLs with v= ANYWHERE in the query (?app=desktop&v=ID, &si=…).
		if ( false !== stripos( $url, 'youtu' ) && preg_match( '~[?&]v=([A-Za-z0-9_-]{6,20})~', $url, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Every YouTube id embedded in a block of admin HTML, in order, no repeats.
	 *
	 * The WordPress editor turns a pasted link into whichever shape it feels
	 * like — an `<iframe>`, an oEmbed `<figure>`, an `<a>`, or a bare URL on its
	 * own line — so all of them count as "play this here". Matching only
	 * iframes would miss the commonest case.
	 *
	 * @param string $html Admin-written HTML.
	 * @return string[]
	 */
	public static function youtube_ids_in_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return array();
		}
		$out = array();
		$re  = '~(?:youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|v/|watch\?[^"\s<]*[?&]?v=)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i';
		if ( preg_match_all( $re, $html, $m ) ) {
			foreach ( $m[1] as $id ) {
				if ( '' !== $id && ! in_array( $id, $out, true ) ) {
					$out[] = $id;
				}
			}
		}
		return $out;
	}

	/**
	 * The videos embedded in a firmware/manual note, with their real YouTube
	 * titles and upload dates.
	 *
	 * ⚠️ Resolved HERE, not in the app. The YouTube Data API key lives on the
	 * server, and this is the same `youtube_meta()` the video library uses — so
	 * a tutorial embedded in an installation note opens the SAME window as a
	 * video published as its own content item, with the same title and date
	 * underneath. Without this the app would have an id and nothing to show
	 * around the player.
	 *
	 * @param object $r Content row.
	 * @return array[] {youtube_id, title, published_at}
	 */
	private static function note_videos( $r ) {
		$html = (string) $r->description . ' ' . (string) ( $r->changelog ?? '' );
		$ids  = self::youtube_ids_in_html( $html );
		if ( empty( $ids ) ) {
			return array();
		}

		$out = array();
		foreach ( $ids as $id ) {
			$meta  = self::youtube_meta( $id );
			$out[] = array(
				'youtube_id'   => $id,
				// Falls back to the parent entry's title, so the window is
				// never headed by an empty string when no API key is set.
				'title'        => '' !== (string) $meta['title'] ? (string) $meta['title'] : (string) $r->title,
				'published_at' => (string) $meta['published_at'],
			);
		}
		return $out;
	}

	/**
	 * Shape a row for the app.
	 *
	 * @param object $r          Content row.
	 * @param string $model_name Resolved model name ('' when model_id = 0).
	 * @return array
	 */
	private static function payload( $r, $model_name = '' ) {
		$is_video = 'video' === $r->type;
		$yt_id    = $is_video ? self::youtube_id( $r->url ) : '';
		$yt_meta  = ( $is_video && '' !== $yt_id ) ? self::youtube_meta( $yt_id ) : array( 'title' => '', 'published_at' => '' );
		return array(
			'id'          => (int) $r->id,
			'type'        => (string) $r->type,
			'model_id'    => (int) $r->model_id,
			'model'       => $model_name,
			'title'       => (string) $r->title,
			'description' => (string) $r->description,
			// What changed in this release, and therefore why anyone should
			// bother installing it. Kept apart from the steps: a customer
			// deciding whether to update has not yet agreed to follow
			// instructions, and putting the reason under them hides it.
			'changelog'   => (string) ( $r->changelog ?? '' ),
			// Firmware/manual downloads go through OUR redirect endpoint, which
			// resolves the real download URL at request time (authenticated
			// OneDrive via WP File Download's connector, else a direct link) — so
			// the app always hits a stable URL and cloud quirks are fixed
			// server-side with no app rebuild. Video URLs are left as-is.
			// An OTA entry has no stored file, so it must NOT be given the
			// redirect URL — that endpoint would resolve to nothing and the
			// app would offer a download that 404s.
			'url'         => $is_video
				? (string) $r->url
				: ( '' === trim( (string) $r->url )
					? ''
					: rest_url( 'aun-app/v1/content/' . (int) $r->id . '/file' ) ),
			// The REAL file extension, taken from the stored (original) URL — the
			// redirect URL above has no extension, so the app must not guess the
			// file kind from it. Drives "open the PDF in the in-app reader" vs
			// "download". ('' when unknown.)
			'file_ext'    => $is_video ? '' : self::file_extension( $r->url ),
			// Whether the app should offer the download button (admin toggle).
			'app_downloadable' => $is_video || ! isset( $r->app_downloadable ) || (int) $r->app_downloadable === 1,
			// OTA: firmware the projector fetches over Wi-Fi by itself. There
			// is no zip to publish, so "no file" IS the definition — an admin
			// leaves the URL blank and writes the steps instead.
			//
			// Derived rather than a separate flag on purpose: a checkbox that
			// can disagree with the presence of a file eventually does, and
			// then the app shows a download button for a file that is not
			// there, or hides one for a file that is. This cannot drift.
			'ota'         => ( 'firmware' === (string) $r->type && '' === trim( (string) $r->url ) ),
			// Tutorials embedded in the notes above, ready to open in the app's
			// normal video window. Empty for a video item — its own url IS the
			// video.
			'note_videos' => $is_video ? array() : self::note_videos( $r ),
			'version'     => (string) $r->version,
			'file_size'   => (string) $r->file_size,
			'youtube_id'  => $yt_id,
			// Real YouTube title + upload date when a Data API key is set (the app
			// falls back to the admin-entered title / added date when empty).
			'youtube_title' => (string) $yt_meta['title'],
			'published_at'  => (string) $yt_meta['published_at'],
			'updated_at'  => date( 'Y-m-d', strtotime( $r->updated_at ) ),
		);
	}

	/**
	 * Rewrite a OneDrive / SharePoint "share" link into a link that downloads
	 * the file directly (their share URLs open a web viewer, so the app's
	 * downloader would fetch an HTML page instead of the firmware). Requires the
	 * file to be shared as "Anyone with the link" (the app downloads
	 * anonymously). Non-cloud URLs are returned untouched.
	 *
	 * @param string $url Stored URL.
	 * @return string
	 */
	/**
	 * The file extension of a stored URL, lowercased ('' when the URL carries
	 * none — e.g. a OneDrive share link). Read from the ORIGINAL stored URL: the
	 * app's download URL is our extensionless redirect endpoint, so the app can't
	 * infer the file kind from it. Cheap string work only — never a network call
	 * (this runs for every row of every content list).
	 *
	 * @param string $url Stored URL.
	 * @return string e.g. 'pdf', 'zip', 'rar', or ''.
	 */
	public static function file_extension( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return '';
		}
		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		return preg_match( '/^[a-z0-9]{2,5}$/', $ext ) ? $ext : '';
	}

	/**
	 * The raw row (type/url/active/downloadable) for the /content/{id}/file
	 * redirect endpoint. Kept minimal — the endpoint resolves the URL itself.
	 *
	 * @param int $id Content id.
	 * @return object|null
	 */
	public static function download_row( $id ) {
		global $wpdb;
		$t = aun_app_api_content_table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT type, url, active, app_downloadable FROM $t WHERE id = %d", (int) $id )
		);
	}

	/**
	 * Resolve a stored file URL to the best DIRECT download URL for the app:
	 *  1. OneDrive/SharePoint → authenticated Microsoft Graph download URL using
	 *     the token WP File Download's OneDrive-Business connector already holds
	 *     (works even for "people in your org" shares).
	 *  2. → the anonymous download.aspx / shares fallback (works for "Anyone with
	 *     the link" shares).
	 *  3. Non-cloud URLs (self-hosted, a WP File Download link, …) pass through.
	 *
	 * @param string $url Stored URL.
	 * @return string Direct URL, or '' when nothing usable.
	 */
	public static function resolve_download_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$is_onedrive = false !== strpos( $host, 'sharepoint.com' )
			|| false !== strpos( $host, 'onedrive.live.com' )
			|| false !== strpos( $host, '1drv.ms' );
		if ( $is_onedrive ) {
			$graph = self::graph_download_url( $url );
			if ( '' !== $graph ) {
				return $graph;
			}
			return self::direct_download_url( $url );
		}
		return $url;
	}

	/**
	 * A OneDrive access token for Graph, from OUR OWN credentials first so the
	 * app never depends on another plugin:
	 *   1. This plugin's own OneDrive settings (client id + secret + tenant, and
	 *      either a refresh token or client-credentials) → minted + cached here.
	 *   2. FALLBACK: the token WP File Download's OneDrive-Business connector
	 *      holds — convenient while that plugin is installed, but optional.
	 * '' when neither is configured (the anonymous share link is then used).
	 *
	 * @return string
	 */
	public static function onedrive_token() {
		$own = self::own_onedrive_token();
		if ( '' !== $own ) {
			return $own;
		}
		return self::wpfd_onedrive_token();
	}

	/**
	 * Mint an access token from THIS plugin's own OneDrive credentials
	 * (AUN App → Settings → OneDrive). Supports both grants:
	 *  - refresh_token  (delegated: a user's personal/business drive)
	 *  - client_credentials (app-only, needs Files.Read.All app permission)
	 * Cached until shortly before it expires. '' when not configured.
	 *
	 * @return string
	 */
	private static function own_onedrive_token() {
		$o        = aun_app_api_get_options();
		$client   = trim( (string) ( $o['onedrive_client_id'] ?? '' ) );
		$secret   = trim( (string) ( $o['onedrive_client_secret'] ?? '' ) );
		$tenant   = trim( (string) ( $o['onedrive_tenant'] ?? '' ) );
		$refresh  = trim( (string) ( $o['onedrive_refresh_token'] ?? '' ) );
		if ( '' === $client || '' === $secret ) {
			return '';
		}
		if ( '' === $tenant ) {
			$tenant = 'common';
		}

		$cached = get_transient( 'aun_app_od_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$body = array(
			'client_id'     => $client,
			'client_secret' => $secret,
		);
		if ( '' !== $refresh ) {
			$body['grant_type']    = 'refresh_token';
			$body['refresh_token'] = $refresh;
			$body['scope']         = 'https://graph.microsoft.com/.default offline_access';
		} else {
			$body['grant_type'] = 'client_credentials';
			$body['scope']      = 'https://graph.microsoft.com/.default';
		}

		$resp = wp_remote_post(
			'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token',
			array( 'timeout' => 15, 'body' => $body )
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return '';
		}
		$data  = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			return '';
		}
		// Refresh tokens rotate — keep the newest so the connection never dies.
		if ( ! empty( $data['refresh_token'] ) && $data['refresh_token'] !== $refresh ) {
			$opts = get_option( AUN_APP_API_OPTION, array() );
			if ( is_array( $opts ) ) {
				$opts['onedrive_refresh_token'] = (string) $data['refresh_token'];
				update_option( AUN_APP_API_OPTION, $opts );
			}
		}
		$ttl = isset( $data['expires_in'] ) ? max( 60, (int) $data['expires_in'] - 120 ) : 3000;
		set_transient( 'aun_app_od_token', $token, $ttl );
		return $token;
	}

	/**
	 * The OneDrive-Business OAuth access token that WP File Download's cloud
	 * connector maintains. OPTIONAL fallback — see [onedrive_token()]. '' when
	 * that plugin isn't installed/connected.
	 *
	 * @return string
	 */
	public static function wpfd_onedrive_token() {
		$cfg = get_option( '_wpfdAddon_onedrive_business_config' );
		if ( ! is_array( $cfg ) || empty( $cfg['state'] ) ) {
			return '';
		}
		$state = $cfg['state'];
		if ( is_array( $state ) ) {
			return isset( $state['token']['data']['access_token'] ) ? (string) $state['token']['data']['access_token'] : '';
		}
		if ( is_object( $state ) ) {
			return isset( $state->token->data->access_token ) ? (string) $state->token->data->access_token : '';
		}
		return '';
	}

	/**
	 * Resolve a OneDrive/SharePoint share URL to a Microsoft Graph pre-authorised
	 * download URL, using WP File Download's stored token. Cached 20 min (Graph's
	 * downloadUrl is valid ~1 h). '' when no token or the lookup fails.
	 *
	 * @param string $share_url Share URL.
	 * @return string
	 */
	private static function graph_download_url( $share_url ) {
		$token = self::onedrive_token();
		if ( '' === $token ) {
			return '';
		}
		$cache_key = 'aun_app_dl_' . md5( $share_url );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$enc = 'u!' . rtrim( strtr( base64_encode( $share_url ), '+/', '-_' ), '=' );
		$api = 'https://graph.microsoft.com/v1.0/shares/' . $enc . '/driveItem?%24select=id,name,@microsoft.graph.downloadUrl';
		$resp = wp_remote_get( $api, array(
			'timeout' => 12,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return '';
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$dl   = isset( $body['@microsoft.graph.downloadUrl'] ) ? (string) $body['@microsoft.graph.downloadUrl'] : '';
		if ( '' !== $dl ) {
			set_transient( $cache_key, $dl, 20 * MINUTE_IN_SECONDS );
		}
		return $dl;
	}

	/**
	 * Admin "Test download" check — resolve a stored URL and fetch the first few
	 * bytes to confirm the app would get a real FILE (not an HTML viewer page).
	 * Lets the admin verify from the panel instead of testing each file in the app.
	 *
	 * @param string $url Stored URL.
	 * @return array {ok, code, type, via, message}
	 */
	public static function verify_download( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return array( 'ok' => false, 'message' => 'No file URL is set for this item.' );
		}
		$resolved = self::resolve_download_url( $url );
		if ( '' === $resolved ) {
			return array( 'ok' => false, 'message' => 'Could not resolve a download URL.' );
		}
		$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$onedrive = false !== strpos( $host, 'sharepoint.com' ) || false !== strpos( $host, 'onedrive' ) || false !== strpos( $host, '1drv.ms' );
		$via      = $onedrive
			? ( '' !== self::onedrive_token() ? 'OneDrive (authenticated)' : 'OneDrive public share link' )
			: 'direct link';
		$resp = wp_remote_get( $resolved, array(
			'timeout'     => 15,
			'redirection' => 5,
			'headers'     => array( 'Range' => 'bytes=0-2047', 'User-Agent' => 'AUN-Care-App/1.0' ),
		) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'via' => $via, 'message' => 'Request failed: ' . $resp->get_error_message() );
		}
		$code    = (int) wp_remote_retrieve_response_code( $resp );
		$ctype   = strtolower( (string) wp_remote_retrieve_header( $resp, 'content-type' ) );
		$is_html = false !== strpos( $ctype, 'text/html' );
		$ok      = ( $code >= 200 && $code < 400 ) && ! $is_html;
		return array(
			'ok'      => $ok,
			'code'    => $code,
			'type'    => $ctype,
			'via'     => $via,
			'message' => $ok
				? sprintf( 'Downloadable ✓ — the app will get a file (%s), via %s.', $ctype ? $ctype : 'binary', $via )
				: ( $is_html
					? 'Not downloadable ✗ — the link returns a web PAGE, not a file. Make sure the OneDrive file is shared as "Anyone with the link".'
					: sprintf( 'Not downloadable ✗ — HTTP %d from the file host.', $code ) ),
		);
	}

	public static function direct_download_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return $url;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		// SharePoint / OneDrive-for-Business share link
		// (https://TENANT-my.sharepoint.com/:u:/g/personal/USER/SHAREID?e=…).
		// The reliable anonymous direct download is the site's `download.aspx`
		// with the share id: it serves the file in ONE response — octet-stream,
		// correct filename, Range-resumable, NO redirects and NO cookies. The
		// old `?download=1` viewer link instead 302-chains through pages that
		// need a guest cookie the app's downloader drops, so it saved an HTML
		// page. (Verified live against a real firmware share.) `api.onedrive.com`
		// is NOT usable here — the tenant is migrated ("User migrated" 308).
		if ( false !== strpos( $host, 'sharepoint.com' ) ) {
			$direct = self::sharepoint_share_download( $url );
			return '' !== $direct ? $direct : $url;
		}
		// Consumer OneDrive (onedrive.live.com / 1drv.ms) is NOT migrated — the
		// OneDrive "shares" API serves anonymous "Anyone with the link" content.
		if ( false !== strpos( $host, 'onedrive.live.com' ) || false !== strpos( $host, '1drv.ms' ) ) {
			return self::onedrive_share_download( $url );
		}
		// Already-direct endpoints + non-cloud URLs pass through untouched.
		return $url;
	}

	/**
	 * SharePoint / OneDrive-for-Business share URL → its anonymous download.aspx:
	 *   https://HOST/:u:/g/personal/USER/SHAREID?e=…
	 *   → https://HOST/personal/USER/_layouts/15/download.aspx?share=SHAREID
	 * Handles personal (`/personal/user`) and site (`/sites/x`, `/teams/x`)
	 * collections. Returns '' when the URL isn't a recognisable share link (an
	 * already-direct `_layouts/15/download.aspx` link falls through here and the
	 * caller keeps it as-is). Requires the file be shared "Anyone with the link".
	 *
	 * @param string $url Share URL.
	 * @return string Direct-download URL, or '' if not a share link.
	 */
	private static function sharepoint_share_download( $url ) {
		$parts  = wp_parse_url( $url );
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( '' === $host || '' === $path ) {
			return '';
		}
		// /:<type>:/<realm>/<site-path…>/<share-id>  (type = u/f/w/b/…, realm = g/s/t/…)
		if ( preg_match( '#^/:[^:/]+:/[^/]+/(.+)/([^/]+)$#', $path, $m ) ) {
			$site_path = $m[1];  // e.g. personal/info_aun-projector_com_bd
			$share_id  = $m[2];  // e.g. IQAJ…ygHs (URL-safe already)
			return $scheme . '://' . $host . '/' . $site_path
				. '/_layouts/15/download.aspx?share=' . rawurlencode( $share_id );
		}
		return '';
	}

	/**
	 * Consumer OneDrive share URL → the anonymous direct-download endpoint via
	 * the documented `encodeSharingUrl` scheme (personal OneDrive only — NOT
	 * business/SharePoint tenants, which are migrated off api.onedrive.com):
	 *   token = 'u!' . base64url(url)   (base64, `+`→`-`, `/`→`_`, `=` trimmed)
	 *   GET https://api.onedrive.com/v1.0/shares/{token}/root/content
	 *
	 * @param string $url Share URL.
	 * @return string Direct-download URL.
	 */
	private static function onedrive_share_download( $url ) {
		$token = 'u!' . rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' );
		return 'https://api.onedrive.com/v1.0/shares/' . $token . '/root/content';
	}

	/**
	 * The real upload date of a YouTube video (its snippet.publishedAt), via the
	 * YouTube Data API. Needs a free API key in Settings; cached 30 days (the
	 * publish date never changes). '' when no key / not found — the app then
	 * shows the admin-added date instead.
	 *
	 * @param string $video_id YouTube id.
	 * @return string Y-m-d, or ''.
	 */
	/**
	 * The YouTube Data API key: our own Settings field first, then the key the
	 * `aun-tutorials` plugin already stores (option `aun_tut_api_key`) so the
	 * admin only ever enters it once. Works for videos on ANY public channel —
	 * `videos.list?id=` reads public metadata regardless of who owns the video.
	 *
	 * @return string
	 */
	public static function youtube_api_key() {
		$key = trim( (string) ( aun_app_api_get_options()['youtube_api_key'] ?? '' ) );
		if ( '' === $key ) {
			$key = trim( (string) get_option( 'aun_tut_api_key', '' ) );
		}
		return $key;
	}

	/**
	 * YouTube video metadata (title + upload date) in one cached Data-API call.
	 * Cached 30 days. Returns ['title'=>'', 'published_at'=>''] when no key /
	 * not found. Works for videos on any public channel.
	 *
	 * @param string $video_id YouTube id.
	 * @return array{title:string,published_at:string}
	 */
	public static function youtube_meta( $video_id ) {
		$empty    = array( 'title' => '', 'published_at' => '' );
		$video_id = trim( (string) $video_id );
		if ( '' === $video_id ) {
			return $empty;
		}
		$key = self::youtube_api_key();
		if ( '' === $key ) {
			return $empty;
		}
		$cache_key = 'aun_app_yt_meta_' . $video_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached; // a cached miss (both empty) is valid too
		}
		$url  = 'https://www.googleapis.com/youtube/v3/videos?' . http_build_query( array(
			'part'   => 'snippet',
			'id'     => $video_id,
			'key'    => $key,
			'fields' => 'items(snippet(title,publishedAt))',
		) );
		$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
		$out  = $empty;
		if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
			$body    = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
			$snippet = (array) ( $body['items'][0]['snippet'] ?? array() );
			$out     = array(
				'title'        => (string) ( $snippet['title'] ?? '' ),
				'published_at' => '' !== (string) ( $snippet['publishedAt'] ?? '' ) ? gmdate( 'Y-m-d', strtotime( (string) $snippet['publishedAt'] ) ) : '',
			);
		}
		set_transient( $cache_key, $out, 30 * DAY_IN_SECONDS );
		return $out;
	}

	/** Back-compat: just the upload date. */
	public static function youtube_published_at( $video_id ) {
		return self::youtube_meta( $video_id )['published_at'];
	}

	/**
	 * Store an optional bug-report screenshot (base64 image) in the media
	 * library and return its URL. Images only; a bad/oversized/non-image input
	 * returns '' so the report itself never fails.
	 *
	 * @param mixed $data data:image/...;base64,... or raw base64.
	 * @return string URL, or ''.
	 */
	public static function store_feedback_image( $data ) {
		if ( ! is_string( $data ) || '' === trim( $data ) ) {
			return '';
		}
		$ext = 'jpg';
		if ( preg_match( '#^data:image/([a-z0-9.+-]+);base64,#i', $data, $m ) ) {
			$ext  = strtolower( $m[1] );
			$data = substr( $data, strpos( $data, ',' ) + 1 );
		}
		$bin = base64_decode( $data, true );
		if ( false === $bin || '' === $bin || strlen( $bin ) > 6 * 1024 * 1024 ) {
			return '';
		}
		$info = @getimagesizefromstring( $bin ); // the real validation: is it an image?
		if ( ! is_array( $info ) ) {
			return '';
		}
		$ext = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ? $ext : 'jpg';

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$name = 'aun-bug-' . time() . '-' . wp_generate_password( 6, false ) . '.' . $ext;
		$up   = wp_upload_bits( $name, null, $bin );
		if ( ! empty( $up['error'] ) || empty( $up['file'] ) ) {
			return '';
		}
		$attach_id = wp_insert_attachment( array(
			'post_mime_type' => (string) ( $info['mime'] ?? 'image/jpeg' ),
			'post_title'     => 'AUN bug report screenshot',
			'post_status'    => 'inherit',
		), $up['file'] );
		if ( ! is_wp_error( $attach_id ) && $attach_id ) {
			wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $up['file'] ) );
		}
		return (string) $up['url'];
	}

	/**
	 * List active content, optionally filtered by type and/or model.
	 * Items with model_id 0 apply to all models and are always included.
	 *
	 * @param string $type     One of TYPES or '' for all.
	 * @param int    $model_id Model id or 0 for all.
	 * @return array[]
	 */
	public static function get_list( $type = '', $model_id = 0 ) {
		global $wpdb;
		$t = aun_app_api_content_table();

		$where  = array( 'active = 1' );
		$params = array();

		// Filter by ANY requested type — including retired ones like 'tip'. (The
		// old `in_array($type, self::TYPES)` guard silently returned ALL content
		// once 'tip' was removed from TYPES, leaking firmware/manuals/videos into
		// the app's FAQ list.)
		if ( '' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}
		if ( $model_id > 0 ) {
			$where[]  = '(model_id = 0 OR model_id = %d)';
			$params[] = $model_id;
		}

		$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort ASC, id DESC';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		$rows = $wpdb->get_results( $sql );

		// Resolve model names in one query.
		$model_names = array();
		$t_prods     = AUN_App_Warranty::t_prods();
		if ( AUN_App_Warranty::available() ) {
			foreach ( (array) $wpdb->get_results( "SELECT id, name FROM $t_prods" ) as $p ) {
				$model_names[ (int) $p->id ] = (string) $p->name;
			}
		}

		$out = array();
		foreach ( (array) $rows as $r ) {
			$name  = isset( $model_names[ (int) $r->model_id ] ) ? $model_names[ (int) $r->model_id ] : '';
			$out[] = self::payload( $r, $name );
		}
		return $out;
	}

	/**
	 * Content for a SET of model ids (a customer's devices) plus general
	 * (model_id 0) items, optionally filtered by type. Powers the home screen's
	 * "video guides" so only relevant models show.
	 *
	 * @param int[]  $model_ids Model ids.
	 * @param string $type      One of TYPES or '' for all.
	 * @return array[]
	 */
	public static function for_models( $model_ids, $type = '' ) {
		global $wpdb;
		$t = aun_app_api_content_table();

		$model_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $model_ids ) ) ) );

		$where  = array( 'active = 1' );
		$params = array();
		// Filter by ANY requested type — including retired ones like 'tip'. (The
		// old `in_array($type, self::TYPES)` guard silently returned ALL content
		// once 'tip' was removed from TYPES, leaking firmware/manuals/videos into
		// the app's FAQ list.)
		if ( '' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}
		if ( $model_ids ) {
			$ph       = implode( ',', array_fill( 0, count( $model_ids ), '%d' ) );
			$where[]  = "(model_id = 0 OR model_id IN ($ph))";
			$params   = array_merge( $params, $model_ids );
		} else {
			// No devices → only general content.
			$where[] = 'model_id = 0';
		}

		$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY sort ASC, id DESC';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		$rows = $wpdb->get_results( $sql );

		$model_names = array();
		$t_prods     = AUN_App_Warranty::t_prods();
		if ( AUN_App_Warranty::available() ) {
			foreach ( (array) $wpdb->get_results( "SELECT id, name FROM $t_prods" ) as $p ) {
				$model_names[ (int) $p->id ] = (string) $p->name;
			}
		}

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = self::payload( $r, $model_names[ (int) $r->model_id ] ?? '' );
		}
		return $out;
	}

	/**
	 * Everything the device-detail screen needs, grouped by type.
	 *
	 * @param int $model_id Model id.
	 * @return array{firmware:array,manual:array,video:array,tip:array}
	 */
	public static function bundle( $model_id ) {
		$all = self::get_list( '', (int) $model_id );
		$out = array_fill_keys( self::TYPES, array() );
		foreach ( $all as $item ) {
			$out[ $item['type'] ][] = $item;
		}
		return $out;
	}

	/* ------------------------- Admin CRUD helpers ------------------------- */

	/**
	 * Insert or update a content row from the admin form.
	 *
	 * @param array $data Sanitised field values.
	 * @param int   $id   Row id to update, 0 to insert.
	 * @return int Row id, 0 on failure.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$t = aun_app_api_content_table();

		$row = array(
			'type'        => in_array( $data['type'], self::TYPES, true ) ? $data['type'] : 'tip',
			'model_id'    => max( 0, (int) $data['model_id'] ),
			'title'       => substr( sanitize_text_field( $data['title'] ), 0, 191 ),
			'description' => wp_kses_post( $data['description'] ),
			'changelog'   => wp_kses_post( $data['changelog'] ?? '' ),
			'url'         => esc_url_raw( $data['url'] ),
			'version'     => substr( sanitize_text_field( $data['version'] ), 0, 50 ),
			'file_size'   => substr( sanitize_text_field( $data['file_size'] ), 0, 30 ),
			'sort'        => (int) $data['sort'],
			'active'      => ! empty( $data['active'] ) ? 1 : 0,
			// Absent key (e.g. video) defaults to downloadable; the firmware/manual
			// form always sends it so the admin toggle is honoured.
			'app_downloadable' => array_key_exists( 'app_downloadable', $data ) ? ( ! empty( $data['app_downloadable'] ) ? 1 : 0 ) : 1,
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$ok = $wpdb->update( $t, $row, array( 'id' => $id ) );
			return false === $ok ? 0 : $id;
		}

		$row['created_at'] = current_time( 'mysql' );
		$ok                = $wpdb->insert( $t, $row );
		if ( false === $ok ) {
			return 0;
		}
		$new_id = (int) $wpdb->insert_id;

		// Notify app users who own this model (in-app notification centre).
		// Deduped on the content id, so edits never re-notify.
		if ( $row['active'] && class_exists( 'AUN_App_Notices' ) ) {
			$model_name = '';
			if ( $row['model_id'] > 0 && AUN_App_Warranty::available() ) {
				$t_prods    = AUN_App_Warranty::t_prods();
				$model_name = (string) $wpdb->get_var(
					$wpdb->prepare( "SELECT name FROM $t_prods WHERE id = %d", $row['model_id'] )
				);
			}
			AUN_App_Notices::content_published( $new_id, $row['type'], $row['model_id'], $row['title'], $model_name, $row['changelog'] );
		}

		return $new_id;
	}

	/**
	 * Fetch one row (admin edit form).
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = aun_app_api_content_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
	}

	/**
	 * One ACTIVE item shaped for the app — powers the deep link when the
	 * customer taps a "new firmware/manual/video/tip" push notification.
	 *
	 * @param int $id Row id.
	 * @return array|null Null when missing or unpublished.
	 */
	public static function get_public( $id ) {
		global $wpdb;
		$t = aun_app_api_content_table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND active = 1", (int) $id ) );
		if ( ! $r ) {
			return null;
		}

		$name = '';
		if ( (int) $r->model_id > 0 && AUN_App_Warranty::available() ) {
			$tp   = AUN_App_Warranty::t_prods();
			$name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $tp WHERE id = %d", (int) $r->model_id ) );
		}
		return self::payload( $r, $name );
	}

	/**
	 * Delete one row.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( aun_app_api_content_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Toggle/set the active (published) flag of one row — used by the admin
	 * list's inline show/hide switch.
	 *
	 * @param int  $id     Row id.
	 * @param bool $active Publish (true) or hide (false).
	 */
	public static function set_active( $id, $active ) {
		global $wpdb;
		$wpdb->update(
			aun_app_api_content_table(),
			array( 'active' => $active ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * All rows for the admin list table.
	 *
	 * @return array
	 */
	public static function all_for_admin() {
		global $wpdb;
		$t = aun_app_api_content_table();
		return (array) $wpdb->get_results( "SELECT * FROM $t ORDER BY type ASC, sort ASC, id DESC" );
	}

	/**
	 * Counts by type for the admin dashboard.
	 *
	 * @return array
	 */
	public static function counts() {
		global $wpdb;
		$t    = aun_app_api_content_table();
		$rows = (array) $wpdb->get_results( "SELECT type, COUNT(*) AS c FROM $t WHERE active = 1 GROUP BY type" );
		$out  = array_fill_keys( self::TYPES, 0 );
		foreach ( $rows as $r ) {
			if ( isset( $out[ $r->type ] ) ) {
				$out[ $r->type ] = (int) $r->c;
			}
		}
		return $out;
	}
}
