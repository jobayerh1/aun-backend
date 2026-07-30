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

	/** Compress every attachment belonging to one request; record bytes saved. */
	public static function compress_request( $request_id ) {
		global $wpdb;
		$request_id = (int) $request_id;
		if ( $request_id <= 0 ) {
			return 0;
		}

		$t    = AUN_SP_Install::table( 'attachments' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, file_url FROM $t WHERE request_id = %d", $request_id ) );
		if ( empty( $rows ) ) {
			return 0;
		}

		$total_saved = 0;
		foreach ( $rows as $row ) {
			$path = self::url_to_path( $row->file_url );
			if ( ! $path || ! is_file( $path ) ) {
				continue;
			}

			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				continue;
			}

			$before = (int) filesize( $path );
			$final  = self::compress_image_file( $path );
			if ( ! is_string( $final ) || $final === '' ) {
				continue;
			}

			$update = array( 'bytes_before' => $before );
			$format = array( '%d' );

			// Filename may have changed (png -> jpg); update the stored URL.
			if ( $final !== $path ) {
				$new_url = self::path_to_url( $final );
				if ( $new_url ) {
					$update['file_url'] = $new_url;
					$format[]           = '%s';
				}
			}

			clearstatcache( true, $final );
			$after                 = is_file( $final ) ? (int) filesize( $final ) : $before;
			$update['bytes_after'] = $after;
			$format[]              = '%d';

			$wpdb->update( $t, $update, array( 'id' => (int) $row->id ), $format, array( '%d' ) );
			$total_saved += max( 0, $before - $after );
		}

		return $total_saved;
	}

	/**
	 * Resize + re-encode one image, converting non-JPEG to JPEG. Returns the FINAL
	 * path (a new .jpg after conversion) or the unchanged path if nothing was done.
	 */
	public static function compress_image_file( $path, $max_dim = self::MAX_DIM, $quality = self::QUALITY ) {
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$is_jpeg = in_array( $ext, array( 'jpg', 'jpeg' ), true );

		// Already small — nothing to gain, avoid needless re-encode.
		if ( filesize( $path ) < 80 * 1024 ) {
			return $path;
		}

		// Memory guard: skip anything over ~40 MP so a background worker can't OOM.
		$info = @getimagesize( $path );
		if ( is_array( $info ) && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
			if ( ( $info[0] * $info[1] ) / 1000000 > 40 ) {
				error_log( 'AUN SP: image too large to compress safely, original kept: ' . basename( $path ) );
				return $path;
			}
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			error_log( 'AUN SP: image editor unavailable, leaving original: ' . $editor->get_error_message() );
			return $path;
		}

		$size = $editor->get_size();
		if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
			if ( $size['width'] > $max_dim || $size['height'] > $max_dim ) {
				$editor->resize( $max_dim, $max_dim, false ); // false = fit, don't crop
			}
		}
		$editor->set_quality( (int) $quality );

		if ( $is_jpeg ) {
			$saved = $editor->save( $path );
			if ( is_wp_error( $saved ) ) {
				error_log( 'AUN SP: JPEG compression failed, original kept: ' . $saved->get_error_message() );
			}
			return $path;
		}

		// PNG / GIF / WebP -> convert to .jpg (transparency flattened — fine for photos).
		$dir      = dirname( $path );
		$base     = pathinfo( $path, PATHINFO_FILENAME );
		$jpg_name = wp_unique_filename( $dir, $base . '.jpg' );
		$jpg_path = trailingslashit( $dir ) . $jpg_name;

		$saved = $editor->save( $jpg_path, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			$msg = is_wp_error( $saved ) ? $saved->get_error_message() : 'unknown error';
			error_log( 'AUN SP: PNG->JPEG conversion failed, original kept: ' . $msg );
			return $path;
		}

		$final = $saved['path'];
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
