<?php
/**
 * Image compression for uploaded proof photos.
 *
 * Reuses the proven engine from the AUN Warranty & Registration plugin: resize +
 * re-encode via wp_get_image_editor, convert non-JPEG to JPEG, skip tiny files,
 * guard against huge (out-of-memory) images, and — crucially — run AFTER the HTTP
 * response is flushed (LiteSpeed/FastCGI) with a WP-Cron fallback, so the customer
 * never waits on compression.
 *
 * Tuned for part photos rather than invoices: a printed serial/model number fills
 * only a slice of the frame, so we cap the long edge at 1600px (vs 800 for
 * invoices) at quality 82 — still crushes an 8 MB phone photo to a few hundred KB
 * while keeping the digits readable.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Image {

	const MAX_DIM = 1600;
	const QUALITY = 82;

	public static function init() {
		add_action( 'aun_sp_compress_event', array( __CLASS__, 'run_deferred' ), 10, 1 );
	}

	/**
	 * Queue a request's uploaded photos for compression:
	 *   1) PRIMARY  — on PHP shutdown, after the response is flushed to the browser.
	 *   2) FALLBACK — a one-off WP-Cron event, in case the request dies early.
	 */
	public static function queue( $request_id ) {
		$request_id = (int) $request_id;
		if ( $request_id <= 0 ) {
			return;
		}

		if ( ! isset( $GLOBALS['aun_sp_compress_queue'] ) ) {
			$GLOBALS['aun_sp_compress_queue'] = array();
			add_action( 'shutdown', array( __CLASS__, 'process_queue' ), 99 );
		}
		$GLOBALS['aun_sp_compress_queue'][ $request_id ] = true;

		if ( ! wp_next_scheduled( 'aun_sp_compress_event', array( $request_id ) ) ) {
			wp_schedule_single_event( time() + 1, 'aun_sp_compress_event', array( $request_id ) );
		}
	}

	/** Shutdown handler: flush to the user first, then compress. */
	public static function process_queue() {
		$flushed = false;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			@fastcgi_finish_request();
			$flushed = true;
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			@litespeed_finish_request();
			$flushed = true;
		}
		if ( ! $flushed || empty( $GLOBALS['aun_sp_compress_queue'] ) ) {
			return; // Can't flush-and-continue — let WP-Cron handle it.
		}

		foreach ( array_keys( $GLOBALS['aun_sp_compress_queue'] ) as $request_id ) {
			try {
				self::compress_request( $request_id );
				$ts = wp_next_scheduled( 'aun_sp_compress_event', array( $request_id ) );
				if ( $ts ) {
					wp_unschedule_event( $ts, 'aun_sp_compress_event', array( $request_id ) );
				}
			} catch ( \Throwable $e ) {
				error_log( 'AUN SP: post-response compression error: ' . $e->getMessage() );
			}
		}
	}

	/** WP-Cron fallback worker. */
	public static function run_deferred( $request_id ) {
		try {
			self::compress_request( $request_id );
		} catch ( \Throwable $e ) {
			error_log( 'AUN SP: deferred compression error: ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------ settings */

	/**
	 * Compression levels, chosen in Spare Parts -> Settings.
	 *
	 * These photos are read by a person hunting for a printed serial or model
	 * number, so even "Smallest" keeps enough pixels for that — provided the
	 * customer framed the label, which the form asks them to do.
	 */
	public static function presets() {
		return array(
			'balanced' => array( 'label' => 'Balanced — 1600px, quality 82 (sharpest)', 'dim' => 1600, 'q' => 82 ),
			'smaller'  => array( 'label' => 'Smaller — 1400px, quality 76',            'dim' => 1400, 'q' => 76 ),
			'smallest' => array( 'label' => 'Smallest — 1200px, quality 70',           'dim' => 1200, 'q' => 70 ),
		);
	}

	/** The chosen preset key (falls back to 'balanced' for anything unknown). */
	public static function preset() {
		$p = (string) get_option( 'aun_sp_img_preset', 'balanced' );
		return array_key_exists( $p, self::presets() ) ? $p : 'balanced';
	}

	/** Whether this server's image library can write WebP at all. */
	public static function webp_supported() {
		return function_exists( 'wp_image_editor_supports' )
			&& wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * The output format: 'webp' by default, 'jpeg' if the admin chose it.
	 *
	 * WebP is the default because these photos are pure storage cost on a shared
	 * server — a WebP is typically 25-35% smaller than the same JPEG at the same
	 * visual quality, and every browser in use today opens it.
	 *
	 * The support check is at RUN TIME, not at save time, so a host that lacks (or
	 * later drops) WebP silently keeps writing JPEG instead of failing every photo.
	 */
	public static function format() {
		return ( 'jpeg' !== get_option( 'aun_sp_img_format', 'webp' ) && self::webp_supported() ) ? 'webp' : 'jpeg';
	}

	/* ------------------------------------------------------------- workers */

	/**
	 * Compress one request's photos that have NOT been processed yet.
	 *
	 * "Not yet" is the fix. This used to re-encode EVERY photo on the request each
	 * time it ran — and it runs again on every re-upload — so a customer's original
	 * proof photo was re-compressed on each "send us a clearer photo" cycle, losing
	 * a little detail every time while saving nothing. A row is now processed once:
	 * bytes_after > 0 marks it done.
	 */
	public static function compress_request( $request_id ) {
		global $wpdb;
		$request_id = (int) $request_id;
		if ( $request_id <= 0 ) {
			return 0;
		}

		$t    = AUN_SP_Install::table( 'attachments' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, file_url, bytes_before FROM $t WHERE request_id = %d AND bytes_after = 0",
			$request_id
		) );

		$saved = 0;
		foreach ( (array) $rows as $row ) {
			$saved += self::compress_row( $row );
		}
		return $saved;
	}

	/**
	 * Catch-up worker: any photo the upload path never queued.
	 *
	 * Chiefly photos sent from the AUN Care app, which writes straight into this
	 * plugin's tables and never asked for compression — so they sat on disk at full
	 * phone size (up to 8 MB each). Also mops up anything a failed post-response
	 * job left behind.
	 *
	 * Bounded in both count and time: a shared server must not spend a minute
	 * re-encoding in one go. Rows younger than 10 minutes are left to the normal
	 * post-upload pipeline so the two never touch the same file at once.
	 *
	 * @return array{done:int,saved:int,left:int}
	 */
	public static function sweep( $limit = 10, $seconds = 20 ) {
		global $wpdb;
		$t      = AUN_SP_Install::table( 'attachments' );
		// created_at is stored in site-local time (current_time('mysql')), so the
		// cutoff is built the same way.
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 10 * MINUTE_IN_SECONDS );
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, file_url, bytes_before FROM $t
			 WHERE bytes_after = 0 AND ( created_at IS NULL OR created_at < %s )
			 ORDER BY id ASC LIMIT %d",
			$cutoff,
			max( 1, (int) $limit )
		) );

		$start = microtime( true );
		$done  = 0;
		$saved = 0;
		foreach ( (array) $rows as $row ) {
			if ( microtime( true ) - $start > $seconds ) {
				break;
			}
			$saved += self::compress_row( $row );
			$done++;
		}
		return array( 'done' => $done, 'saved' => $saved, 'left' => self::pending_count() );
	}

	/**
	 * Hourly entry point (aun_sp_hourly_tidy).
	 *
	 * A wrapper rather than hooking sweep() directly: WordPress calls a no-argument
	 * hook's callbacks with an empty string as the first argument, which sweep()
	 * would read as a limit of 1 — one photo an hour.
	 */
	public static function sweep_hourly() {
		self::sweep( 10, 20 );
	}

	/** Photos not yet processed. */
	public static function pending_count() {
		global $wpdb;
		$t = AUN_SP_Install::table( 'attachments' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE bytes_after = 0" );
	}

	/**
	 * Storage figures for the Settings page.
	 *
	 * The CASE guards matter on live MySQL: the columns are UNSIGNED, and a bare
	 * bytes_before - bytes_after on a row where after > before is an out-of-range
	 * ERROR in strict mode, not a negative number.
	 *
	 * @return array{total:int,pending:int,saved:int,on_disk:int}
	 */
	public static function stats() {
		global $wpdb;
		$t = AUN_SP_Install::table( 'attachments' );
		$r = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN bytes_after = 0 THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN bytes_after > 0 AND bytes_before > bytes_after THEN bytes_before - bytes_after ELSE 0 END) AS saved,
				SUM(CASE WHEN bytes_after > 1 THEN bytes_after ELSE 0 END) AS on_disk
			 FROM $t"
		);
		return array(
			'total'   => (int) ( $r->total ?? 0 ),
			'pending' => (int) ( $r->pending ?? 0 ),
			'saved'   => (int) ( $r->saved ?? 0 ),
			'on_disk' => (int) ( $r->on_disk ?? 0 ),
		);
	}

	/**
	 * Process one attachment row and record the outcome, so it is never picked up
	 * again. Returns the bytes saved.
	 */
	private static function compress_row( $row ) {
		global $wpdb;
		$t    = AUN_SP_Install::table( 'attachments' );
		$path = self::url_to_path( $row->file_url );
		$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

		// Nothing to compress: the file is gone, or isn't an image we handle. Mark it
		// done anyway (after = before, so it counts as 0 saved) — re-checking a
		// missing file every hour forever is exactly the waste this is meant to cut.
		if ( ! $path || ! is_file( $path ) || ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$wpdb->update( $t,
				array( 'bytes_after' => max( 1, (int) ( $row->bytes_before ?? 0 ) ) ),
				array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) );
			return 0;
		}

		$before = (int) filesize( $path );
		$final  = self::compress_image_file( $path );
		if ( ! is_string( $final ) || '' === $final || ! is_file( $final ) ) {
			$final = $path;
		}
		clearstatcache( true, $final );
		$after = max( 1, (int) filesize( $final ) );

		$update = array( 'bytes_before' => $before, 'bytes_after' => $after );
		$format = array( '%d', '%d' );
		// Format changed (e.g. png -> jpg, or -> webp): the file has a new name.
		if ( $final !== $path ) {
			$new_url = self::path_to_url( $final );
			if ( $new_url ) {
				$update['file_url'] = $new_url;
				$format[]           = '%s';
			}
		}
		$wpdb->update( $t, $update, array( 'id' => (int) $row->id ), $format, array( '%d' ) );
		return max( 0, $before - $after );
	}

	/**
	 * Resize + re-encode one image in the configured format (JPEG, or WebP where the
	 * server supports it). Returns the FINAL path — a new file when the format
	 * changed — or the unchanged path when nothing was worth doing.
	 *
	 * Three rules make this safe to run on a customer's only copy of a photo:
	 *   1. Turn phone photos upright FIRST. Cameras store "rotate me" as an EXIF
	 *      flag instead of rotating the pixels, and re-encoding drops that flag —
	 *      so without this a portrait photo of an LCD serial came out sideways.
	 *   2. Write the result BESIDE the original and keep it only if it is really
	 *      smaller. A photo that was already well compressed can come out bigger
	 *      from a re-encode; then the original stays and nothing is lost.
	 *   3. Never load more than ~40 megapixels, so a background worker on a shared
	 *      host can't run out of memory.
	 *
	 * @param string   $path
	 * @param int|null $max_dim Long-edge cap; null = the configured preset.
	 * @param int|null $quality 1-100; null = the configured preset.
	 * @return string|false
	 */
	public static function compress_image_file( $path, $max_dim = null, $quality = null ) {
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$preset  = self::presets()[ self::preset() ];
		$max_dim = $max_dim ? (int) $max_dim : (int) $preset['dim'];
		$quality = $quality ? (int) $quality : (int) $preset['q'];
		$format  = self::format();

		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$same = ( 'webp' === $format ) ? ( 'webp' === $ext ) : in_array( $ext, array( 'jpg', 'jpeg' ), true );

		// Already tiny AND already in the target format — nothing to gain.
		if ( $same && filesize( $path ) < 80 * 1024 ) {
			return $path;
		}

		// Rule 3: memory guard.
		$info = @getimagesize( $path );
		if ( is_array( $info ) && ! empty( $info[0] ) && ! empty( $info[1] ) && ( $info[0] * $info[1] ) / 1000000 > 40 ) {
			error_log( 'AUN SP: image too large to compress safely, original kept: ' . basename( $path ) );
			return $path;
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			error_log( 'AUN SP: image editor unavailable, leaving original: ' . $editor->get_error_message() );
			return $path;
		}

		// Rule 1: upright before anything else (no-op without EXIF, or without the
		// exif extension — then it behaves exactly as before).
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}

		$size = $editor->get_size();
		if ( ! empty( $size['width'] ) && ! empty( $size['height'] )
			&& ( $size['width'] > $max_dim || $size['height'] > $max_dim ) ) {
			$editor->resize( $max_dim, $max_dim, false ); // false = fit, don't crop
		}
		$editor->set_quality( $quality );

		$mime    = ( 'webp' === $format ) ? 'image/webp' : 'image/jpeg';
		$out_ext = ( 'webp' === $format ) ? 'webp' : 'jpg';
		$dir     = dirname( $path );
		$base    = pathinfo( $path, PATHINFO_FILENAME );

		// Rule 2: write beside the original first.
		$tmp   = trailingslashit( $dir ) . wp_unique_filename( $dir, $base . '-aunsp-tmp.' . $out_ext );
		$saved = $editor->save( $tmp, $mime );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
			error_log( 'AUN SP: compression failed, original kept: '
				. ( is_wp_error( $saved ) ? $saved->get_error_message() : 'unknown error' ) );
			return $path;
		}
		$tmp = $saved['path'];
		clearstatcache( true, $tmp );
		clearstatcache( true, $path );

		if ( filesize( $tmp ) >= filesize( $path ) ) {
			@unlink( $tmp ); // bigger is not better — keep the original untouched
			return $path;
		}

		// Same format: replace in place, so the stored URL stays valid. New format:
		// give it a clean name and remove the original.
		$final = $same ? $path : trailingslashit( $dir ) . wp_unique_filename( $dir, $base . '.' . $out_ext );
		if ( ! @rename( $tmp, $final ) ) {
			@unlink( $tmp );
			error_log( 'AUN SP: could not move the compressed file into place, original kept: ' . basename( $path ) );
			return $path;
		}
		if ( $final !== $path && is_file( $path ) ) {
			@unlink( $path );
		}
		@chmod( $final, 0644 );
		return $final;
	}

	/** Map a stored uploads URL to a safe absolute path (traversal-hardened). */
	public static function url_to_path( $file_url ) {
		$file_url = trim( (string) $file_url );
		if ( $file_url === '' ) {
			return '';
		}
		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return '';
		}
		if ( strpos( $file_url, $upload['baseurl'] ) !== 0 ) {
			return '';
		}
		$relative  = ltrim( substr( $file_url, strlen( $upload['baseurl'] ) ), '/\\' );
		$path      = trailingslashit( $upload['basedir'] ) . $relative;
		$real_base = realpath( $upload['basedir'] );
		$real_path = realpath( $path );
		if ( ! $real_base || ! $real_path ) {
			return '';
		}
		if ( strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return '';
		}
		return $real_path;
	}

	/** Inverse of url_to_path(): in-uploads absolute path back to its public URL. */
	public static function path_to_url( $path ) {
		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return '';
		}
		$real_base = realpath( $upload['basedir'] );
		$real_path = realpath( $path );
		if ( ! $real_base || ! $real_path ) {
			return '';
		}
		if ( strpos( $real_path, $real_base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return '';
		}
		$relative = ltrim( substr( $real_path, strlen( $real_base ) ), '/\\' );
		return trailingslashit( $upload['baseurl'] ) . str_replace( '\\', '/', $relative );
	}
}
