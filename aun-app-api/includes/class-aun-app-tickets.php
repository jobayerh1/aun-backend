<?php
/**
 * Support tickets: bridge client for the osTicket install on the support
 * server (see osticket-bridge/aun-app-bridge/api.php).
 *
 * The app never talks to the support host — WordPress proxies every call and
 * attaches the customer's OTP-verified identity. osTicket keys customers by
 * EMAIL; app customers are keyed by PHONE. Resolution, in order:
 *
 *   1. the customer's app-profile email, when they have one (their older
 *      website tickets under that address appear in the app automatically);
 *   2. otherwise a synthetic per-phone address {8801XXXXXXXXX}@{host}, which
 *      the bridge recognises and never sends auto-response mail to.
 *
 * Nobody types an order number: the WP layer looks the invoice up itself
 * (the ticket may be "about" one of the customer's registered devices) and
 * appends a formatted "Customer & device" block to the message, plus fills
 * any custom form fields the admin mapped in Settings (tokens like
 * {invoice|serial|phone} — nothing hardcoded, works with any future topic).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Tickets {

	const TOPICS_CACHE = 'aun_app_ost_topics';
	const TOPICS_TTL   = 15 * MINUTE_IN_SECONDS;

	/** Option holding the poll cursor (last staff-reply timestamp seen). */
	const SINCE_OPTION = 'aun_app_tickets_since';

	/** Upload limits (kept modest so base64 fits typical PHP post_max_size). */
	const MAX_FILES     = 5;
	const MAX_FILE_BYTES = 8 * 1024 * 1024; // 8 MB per file, decoded.

	/* --------------------------------------------------------------------- *
	 * Configuration
	 * --------------------------------------------------------------------- */

	public static function settings() {
		$opts = aun_app_api_get_options();
		return array(
			'base'    => rtrim( (string) ( $opts['tickets_base_url'] ?? '' ), '/' ),
			'secret'  => (string) ( $opts['tickets_secret'] ?? '' ),
			'mapping' => (string) ( $opts['tickets_field_map'] ?? '' ),
		);
	}

	public static function configured() {
		$s = self::settings();
		return '' !== $s['base'] && strlen( $s['secret'] ) >= 20;
	}

	/* --------------------------------------------------------------------- *
	 * Staff queue — "is a customer waiting on us right now?"
	 *
	 * Nobody on the team logs into the support centre daily, so a reply can sit
	 * unanswered for days without anyone noticing. This keeps a small snapshot
	 * of the open queue in an option, refreshed by the existing 10-minute
	 * tickets cron, and the admin bar renders straight from it.
	 *
	 * ⚠️ The admin bar must NEVER call the bridge itself — that would add a
	 * cross-server HTTP round-trip to every single wp-admin page load, and hang
	 * the dashboard whenever the support server is slow or firewalled.
	 * --------------------------------------------------------------------- */

	const QUEUE_OPTION = 'aun_app_ticket_queue';

	/** Refresh the snapshot from the bridge. Returns the stored array. */
	public static function refresh_queue() {
		if ( ! self::configured() ) {
			delete_option( self::QUEUE_OPTION );
			return array();
		}

		$body = self::call( 'queue' );
		if ( is_wp_error( $body ) || empty( $body['ok'] ) ) {
			// Bridge unreachable: keep the last good snapshot but mark it, so
			// the admin bar can say "can't reach support" instead of lying that
			// the queue is empty.
			$prev            = (array) get_option( self::QUEUE_OPTION, array() );
			$prev['error']   = true;
			$prev['checked'] = time();
			update_option( self::QUEUE_OPTION, $prev, false );
			return $prev;
		}

		$tickets = array();
		foreach ( (array) ( $body['tickets'] ?? array() ) as $t ) {
			$tickets[] = array(
				'number'    => (string) ( $t['number'] ?? '' ),
				'ticket_id' => (int) ( $t['ticket_id'] ?? 0 ),
				'subject'   => (string) ( $t['subject'] ?? '' ),
				'status'    => (string) ( $t['status'] ?? '' ),
				'waiting'   => ! empty( $t['waiting'] ),
				'updated'   => (string) ( $t['updated'] ?? '' ),
			);
		}

		$snapshot = array(
			'open'    => (int) ( $body['open'] ?? 0 ),
			'waiting' => (int) ( $body['waiting'] ?? 0 ),
			'tickets' => $tickets,
			'checked' => time(),
			'error'   => false,
		);
		update_option( self::QUEUE_OPTION, $snapshot, false );
		return $snapshot;
	}

	/** The stored snapshot (never makes a network call). */
	public static function queue() {
		$q = get_option( self::QUEUE_OPTION, array() );
		return is_array( $q ) ? $q : array();
	}

	/** Agent-panel URL for one ticket, for the admin-bar links. */
	public static function agent_url( $ticket_id = 0 ) {
		$base = self::settings()['base'];
		if ( '' === $base ) {
			return '';
		}
		return $ticket_id > 0
			? $base . '/scp/tickets.php?id=' . (int) $ticket_id
			: $base . '/scp/tickets.php';
	}

	/** Domain for synthetic per-phone addresses (customers without email). */
	public static function synthetic_domain() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return 'app.' . preg_replace( '/^www\./', '', $host ? $host : 'aun-projector.com.bd' );
	}

	/**
	 * The e-mail identities this user may own tickets under.
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Phone 8801XXXXXXXXX.
	 * @return string[] Synthetic first (stable), profile email second.
	 */
	public static function emails_for_user( $user_id, $canonical ) {
		$emails = array();
		if ( '' !== (string) $canonical ) {
			$emails[] = strtolower( $canonical . '@' . self::synthetic_domain() );
		}
		$profile = strtolower( trim( (string) AUN_App_Profile::get_email( (int) $user_id ) ) );
		if ( '' !== $profile && is_email( $profile ) ) {
			$emails[] = $profile;
		}
		return array_values( array_unique( $emails ) );
	}

	/* --------------------------------------------------------------------- *
	 * Bridge transport
	 * --------------------------------------------------------------------- */

	/**
	 * Call the bridge.
	 *
	 * @param string $action api.php action.
	 * @param array  $params Query (GET) or JSON body (POST).
	 * @param string $method GET|POST.
	 * @return array|WP_Error Decoded body.
	 */
	public static function call( $action, $params = array(), $method = 'GET' ) {
		$s = self::settings();
		if ( ! self::configured() ) {
			return new WP_Error( 'tickets_not_configured', 'Support tickets are not configured.' );
		}

		$url  = $s['base'] . '/aun-app-bridge/api.php?action=' . rawurlencode( $action );
		$args = array(
			'timeout'    => 20,
			'headers'    => array( 'X-AUN-Bridge-Secret' => $s['secret'] ),
			// A plain UA: many cPanel/ModSecurity firewalls 403 the default
			// "WordPress/x.x" bot user-agent on server-to-server calls.
			'user-agent' => 'AUN-App-Bridge/1.0',
		);

		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body']                    = wp_json_encode( $params );
			$response                        = wp_remote_post( $url, $args );
		} else {
			$url      = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), $url );
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			self::log_bridge( $action, $response->get_error_message() );
			return new WP_Error( 'tickets_unreachable', self::friendly_error(), array( 'status' => 0, 'detail' => $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		if ( ! is_array( $body ) ) {
			// Usually a WAF/ModSecurity or cPanel HTML page. Keep the exact server
			// text for the DEVELOPER (log + admin test), never for the app.
			$snippet   = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $raw ) ) );
			$technical = 'Unexpected response (HTTP ' . $code . ')' . ( '' !== $snippet ? ' — server said: "' . mb_substr( $snippet, 0, 200 ) . '"' : ' (empty response)' );
			self::log_bridge( $action, $technical );
			return new WP_Error( 'tickets_bad_response', self::friendly_error(), array( 'status' => $code, 'detail' => $technical ) );
		}
		if ( empty( $body['ok'] ) ) {
			$error_code = (string) ( $body['error'] ?? 'error' );
			// Build the TECHNICAL detail (osTicket per-field errors flattened) for
			// the developer only — logged + returned in data['detail'] for the
			// admin test. The app-facing message stays generic (see friendly_error).
			$technical = 'Support system error: ' . $error_code;
			if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
				$flat = array();
				array_walk_recursive( $body['errors'], function ( $v, $k ) use ( &$flat ) {
					if ( is_scalar( $v ) && '' !== (string) $v ) {
						$flat[] = "$k: $v";
					}
				} );
				if ( $flat ) {
					$technical .= ' — ' . implode( '; ', array_slice( $flat, 0, 5 ) );
				}
			}
			self::log_bridge( $action, $technical );
			return new WP_Error(
				'bridge_' . $error_code,
				self::friendly_error( $error_code ),
				array( 'status' => $code, 'detail' => $technical, 'errors' => $body['errors'] ?? null )
			);
		}
		return $body;
	}

	/** Log a bridge problem for the developer (admin can read it in wp logs). */
	private static function log_bridge( $action, $detail ) {
		error_log( 'AUN APP API tickets [' . $action . ']: ' . $detail ); // phpcs:ignore
	}

	/**
	 * The ONLY message the app ever sees for a support failure — friendly and
	 * generic, never an osTicket/WAF internal. The technical cause is in the
	 * WP_Error data['detail'] (admin "Send test ticket") and the PHP error log.
	 *
	 * @param string $code Bridge error code (unused today; kept for future tuning).
	 * @return string
	 */
	private static function friendly_error( $code = '' ) {
		return 'Support is having a temporary problem. Please try again in a little while.';
	}

	/* --------------------------------------------------------------------- *
	 * Attachments
	 * --------------------------------------------------------------------- */

	/**
	 * Validate app-supplied uploads before they cross to the bridge.
	 *
	 * @param mixed $items [{name, type, data(base64)}] from the request.
	 * @return array|WP_Error Cleaned list (base64 kept), or an error to surface.
	 */
	public static function sanitize_uploads( $items ) {
		if ( empty( $items ) ) {
			return array();
		}
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'bad_attachments', 'Attachments were malformed.', array( 'status' => 400 ) );
		}

		$out = array();
		foreach ( $items as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$name = sanitize_file_name( (string) ( $f['name'] ?? '' ) );
			$b64  = (string) ( $f['data'] ?? '' );
			if ( '' === $name || '' === $b64 ) {
				continue;
			}
			$decoded = base64_decode( $b64, true );
			if ( false === $decoded ) {
				return new WP_Error( 'bad_attachment', 'One of the files could not be read. Please try again.', array( 'status' => 400 ) );
			}
			if ( strlen( $decoded ) > self::MAX_FILE_BYTES ) {
				return new WP_Error( 'attachment_too_large', 'Each file must be under 8 MB.', array( 'status' => 400 ) );
			}
			$out[] = array(
				'name' => $name,
				'type' => (string) ( $f['type'] ?? '' ) ?: 'application/octet-stream',
				'data' => $b64,
			);
			if ( count( $out ) >= self::MAX_FILES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Fetch one attachment's raw bytes through the bridge (ownership enforced
	 * bridge-side by the ticket the caller owns). Returns a plain array so the
	 * REST layer can stream it — NOT the JSON envelope.
	 *
	 * @param array  $me     identity() bundle.
	 * @param string $number Ticket number.
	 * @param int    $ref    Attachment id.
	 * @return array|WP_Error {type, name, body}
	 */
	public static function fetch_attachment( $me, $number, $ref ) {
		$s = self::settings();
		if ( ! self::configured() ) {
			return new WP_Error( 'tickets_not_configured', 'Support tickets are not configured.' );
		}
		$emails = self::emails_for_user( $me['user_id'], $me['phone'] );
		if ( empty( $emails ) ) {
			return new WP_Error( 'not_found', 'Attachment not found.', array( 'status' => 404 ) );
		}

		$url = $s['base'] . '/aun-app-bridge/api.php?' . http_build_query( array(
			'action' => 'attachment',
			'number' => (string) $number,
			'emails' => implode( ',', $emails ),
			'ref'    => (int) $ref,
		) );

		$response = wp_remote_get( $url, array(
			'timeout'    => 25,
			'headers'    => array( 'X-AUN-Bridge-Secret' => $s['secret'] ),
			'user-agent' => 'AUN-App-Bridge/1.0',
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'attachment_unavailable', 'This attachment is no longer available.', array( 'status' => 404 === $code ? 404 : 502 ) );
		}
		return array(
			'type' => (string) ( wp_remote_retrieve_header( $response, 'content-type' ) ?: 'application/octet-stream' ),
			'name' => rawurldecode( (string) wp_remote_retrieve_header( $response, 'x-aun-filename' ) ),
			'body' => wp_remote_retrieve_body( $response ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Topics
	 * --------------------------------------------------------------------- */

	/**
	 * Active public help topics (cached). Add a topic in osTicket → it shows
	 * up in the app's "New ticket" form on its own.
	 *
	 * @return array[]|WP_Error [{id,name,fields[]}]
	 */
	public static function topics() {
		$cached = get_transient( self::TOPICS_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$body = self::call( 'topics' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$topics = is_array( $body['topics'] ?? null ) ? $body['topics'] : array();
		set_transient( self::TOPICS_CACHE, $topics, self::TOPICS_TTL );
		return $topics;
	}

	/* --------------------------------------------------------------------- *
	 * Create
	 * --------------------------------------------------------------------- */

	/**
	 * Resolve the admin's field-mapping lines against a token context.
	 *
	 * Each line: `variable = value with {tokens}`. A {a|b|c} token takes the
	 * first non-empty of a, b, c. Lines whose value resolves empty are
	 * skipped entirely (better absent than a blank required field).
	 *
	 * @param string $mapping Textarea option.
	 * @param array  $tokens  token => value.
	 * @return array variable => resolved value.
	 */
	public static function resolve_field_map( $mapping, $tokens ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $mapping ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $var, $template ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' === $var ) {
				continue;
			}
			$value = preg_replace_callback( '/\{([a-z0-9_|]+)\}/i', function ( $m ) use ( $tokens ) {
				foreach ( explode( '|', $m[1] ) as $key ) {
					$candidate = (string) ( $tokens[ strtolower( trim( $key ) ) ] ?? '' );
					if ( '' !== $candidate ) {
						return $candidate;
					}
				}
				return '';
			}, $template );
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$out[ $var ] = $value;
			}
		}
		return $out;
	}

	/**
	 * The customer's device matching a serial ('' serial → null).
	 *
	 * @param array  $me     identity() bundle.
	 * @param string $serial Serial.
	 * @return array|null Device payload from AUN_App_Warranty::get_devices.
	 */
	private static function owned_device( $me, $serial ) {
		$serial = strtoupper( trim( (string) $serial ) );
		if ( '' === $serial || ! class_exists( 'AUN_App_Warranty' ) || ! AUN_App_Warranty::available() ) {
			return null;
		}
		foreach ( (array) AUN_App_Warranty::get_devices( $me['phone'], $me['user_id'] ) as $device ) {
			if ( strtoupper( (string) ( $device['serial'] ?? '' ) ) === $serial ) {
				return $device;
			}
		}
		return null;
	}

	/**
	 * Create a ticket. The message the agent sees = the customer's text plus
	 * a "Customer & device" context table built from what the app already
	 * knows — that is how tickets are linked to customers without ever asking
	 * for an order number.
	 *
	 * @param array  $me       identity() bundle (user_id, customer_name, phone, email).
	 * @param int    $topic_id osTicket help-topic id.
	 * @param string $subject  Subject.
	 * @param string $message  Customer's plain text.
	 * @param string $serial   Optional serial of the device the ticket is about.
	 * @param string $app_ver  App version string (diagnostics).
	 * @param array  $attachments Cleaned uploads [{name,type,data(base64)}].
	 * @return array|WP_Error {number}
	 */
	public static function create( $me, $topic_id, $subject, $message, $serial = '', $app_ver = '', $attachments = array() ) {
		$device  = self::owned_device( $me, $serial );
		$invoice = (string) ( $device['invoice_no'] ?? '' );

		$tokens = array(
			'invoice' => $invoice,
			'serial'  => (string) ( $device['serial'] ?? '' ),
			'model'   => (string) ( $device['model'] ?? '' ),
			'phone'   => (string) $me['phone'],
			'name'    => (string) $me['customer_name'],
		);
		$fields = self::resolve_field_map( self::settings()['mapping'], $tokens );

		// The agent-facing context block. Plain table HTML — osTicket keeps it.
		$rows = array( array( 'Phone (OTP-verified)', $me['phone'] ) );
		if ( $device ) {
			$rows[] = array( 'Projector', trim( ( $device['model'] ?? '' ) . ' — SN ' . ( $device['serial'] ?? '' ) ) );
			if ( '' !== (string) ( $device['purchase_date'] ?? '' ) ) {
				$rows[] = array( 'Purchased', (string) $device['purchase_date'] );
			}
			if ( '' !== $invoice ) {
				$rows[] = array( 'Invoice / order no', $invoice );
			}
			if ( ! empty( $device['warranty']['end'] ) ) {
				$days   = isset( $device['warranty']['days_left'] ) ? (int) $device['warranty']['days_left'] : null;
				$rows[] = array(
					'Warranty',
					'until ' . $device['warranty']['end'] . ( null !== $days ? ( $days > 0 ? " ({$days} days left)" : ' (EXPIRED)' ) : '' ),
				);
			}
		}
		if ( '' !== $app_ver ) {
			$rows[] = array( 'App version', $app_ver );
		}

		$context = '<br><hr><p><em>Sent from the AUN Care app</em></p><table border="0" cellpadding="4">';
		foreach ( $rows as $row ) {
			$context .= '<tr><td><strong>' . esc_html( $row[0] ) . ':</strong></td><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
		$context .= '</table>';

		$emails = self::emails_for_user( $me['user_id'], $me['phone'] );
		$body   = self::call( 'create', array(
			'name'         => '' !== $me['customer_name'] ? $me['customer_name'] : $me['phone'],
			// Prefer the real profile email so osTicket's own mails reach them.
			'email'        => end( $emails ),
			'phone'        => $me['phone'],
			'subject'      => $subject,
			'message_html' => nl2br( esc_html( $message ) ) . $context,
			'topic_id'     => (int) $topic_id,
			'fields'       => $fields,
			'attachments'  => array_values( (array) $attachments ),
		), 'POST' );

		if ( is_wp_error( $body ) ) {
			return $body;
		}
		// The bridge saves the ticket even when a mapped custom-field value no
		// longer validates in osTicket (it drops the fields and retries). Leave
		// a trail so the admin knows the mapping needs updating.
		if ( isset( $body['fields_applied'] ) && false === $body['fields_applied'] && ! empty( $fields ) ) {
			error_log( 'AUN APP API: osTicket rejected the mapped custom form fields (' . implode( ', ', array_keys( $fields ) ) . ') — ticket created WITHOUT them. Check AUN App → Settings → Custom form values against the osTicket form choices.' );
		}
		return array( 'number' => (string) ( $body['number'] ?? '' ) );
	}

	/* --------------------------------------------------------------------- *
	 * List / thread / reply
	 * --------------------------------------------------------------------- */

	/** @return array[]|WP_Error */
	public static function list_for( $me ) {
		$emails = self::emails_for_user( $me['user_id'], $me['phone'] );
		if ( empty( $emails ) ) {
			return array();
		}
		$body = self::call( 'tickets', array( 'emails' => implode( ',', $emails ) ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return is_array( $body['tickets'] ?? null ) ? $body['tickets'] : array();
	}

	/** @return array|WP_Error {ticket, entries[]} */
	public static function thread( $me, $number ) {
		$emails = self::emails_for_user( $me['user_id'], $me['phone'] );
		$body   = self::call( 'thread', array(
			'number' => (string) $number,
			'emails' => implode( ',', $emails ),
		) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$entries = array();
		foreach ( (array) ( $body['entries'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$from = 'staff' === ( $entry['from'] ?? '' ) ? 'staff' : 'customer';
			$html = (string) ( $entry['body'] ?? '' );
			if ( 'html' !== (string) ( $entry['format'] ?? 'html' ) ) {
				$html = nl2br( esc_html( $html ) );
			}
			// Older tickets stored the "data:text/html;charset=utf-8," data-URI
			// marker literally at the start of the body — and, for a brief window,
			// with the content after it percent-encoded. Strip the marker, then
			// url-decode if what remains is clearly percent-encoded. New tickets
			// send plain HTML, so both steps are no-ops for them.
			$stripped = preg_replace( '#^\s*data:text/[a-z0-9.+-]+(?:;[^,]*)?,#i', '', (string) $html );
			if ( $stripped !== (string) $html && preg_match( '/%3[CcEe]|%2[02Ff]/', (string) $stripped ) ) {
				$decoded = rawurldecode( (string) $stripped );
				if ( is_string( $decoded ) && '' !== $decoded ) {
					$stripped = $decoded;
				}
			}
			$html = $stripped;
			// The "Customer & device" context table we append is meta for the
			// support agent, not the customer. Hide it from the customer's own
			// message (everything from the first <hr> onward). Their typed text
			// is esc_html'd, so a literal <hr> only ever comes from our block.
			if ( 'customer' === $from ) {
				$html = preg_split( '/<hr\b[^>]*>/i', (string) $html, 2 )[0];
			}
			// Inline cid: images can't resolve outside osTicket — drop images.
			$html = preg_replace( '/<img[^>]*>/i', '', $html );

			$attachments = array();
			foreach ( (array) ( $entry['attachments'] ?? array() ) as $att ) {
				if ( ! is_array( $att ) || empty( $att['ref'] ) ) {
					continue;
				}
				$attachments[] = array(
					'ref'  => (int) $att['ref'],
					'name' => (string) ( $att['name'] ?? '' ),
					'size' => (int) ( $att['size'] ?? 0 ),
					'type' => (string) ( $att['type'] ?? '' ),
				);
			}

			$entries[] = array(
				'id'          => (int) ( $entry['id'] ?? 0 ),
				'from'        => 'staff' === ( $entry['from'] ?? '' ) ? 'staff' : 'customer',
				'poster'      => (string) ( $entry['poster'] ?? '' ),
				'body'        => wp_kses_post( $html ),
				'created'     => (string) ( $entry['created'] ?? '' ),
				'attachments' => $attachments,
			);
		}

		return array(
			'ticket'  => is_array( $body['ticket'] ?? null ) ? $body['ticket'] : null,
			'entries' => $entries,
		);
	}

	/** @return array|WP_Error {number} */
	public static function reply( $me, $number, $message, $attachments = array() ) {
		$emails = self::emails_for_user( $me['user_id'], $me['phone'] );
		$body   = self::call( 'reply', array(
			'number'       => (string) $number,
			'emails'       => implode( ',', $emails ),
			'message_html' => nl2br( esc_html( $message ) ),
			'attachments'  => array_values( (array) $attachments ),
		), 'POST' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return array( 'number' => (string) ( $body['number'] ?? $number ) );
	}

	/* --------------------------------------------------------------------- *
	 * Staff-reply poll → notification centre + push
	 * --------------------------------------------------------------------- */

	/**
	 * Map an osTicket owner email back to app user ids: synthetic address →
	 * phone → user; anything else → users whose app-profile email matches.
	 *
	 * @param string $email Address from the bridge.
	 * @return int[]
	 */
	public static function users_for_ticket_email( $email ) {
		$email = strtolower( trim( (string) $email ) );
		if ( '' === $email ) {
			return array();
		}

		if ( preg_match( '/^(8801\d{9})@/', $email, $m ) ) {
			$ids = array();
			foreach ( AUN_App_Phone::find_users( $m[1] ) as $user ) {
				$ids[] = (int) $user->ID;
			}
			return $ids;
		}

		$found = get_users( array(
			'meta_key'   => 'aun_app_email',
			'meta_value' => $email,
			'fields'     => 'ID',
			'number'     => 5,
		) );
		return array_map( 'intval', (array) $found );
	}

	/**
	 * Cron: fetch staff replies since the stored cursor and turn each into a
	 * personal notice + push, deduped per thread entry. Safe to run any
	 * number of times; the cursor only advances when the bridge answered.
	 *
	 * @return array{checked:int,notified:int}
	 */
	public static function poll_replies() {
		$stats = array( 'checked' => 0, 'notified' => 0 );
		if ( ! self::configured() ) {
			return $stats;
		}

		$since = (string) get_option( self::SINCE_OPTION, '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since ) ) {
			// First run: start from NOW — old replies pre-date the feature.
			update_option( self::SINCE_OPTION, current_time( 'mysql' ), false );
			return $stats;
		}

		$body = self::call( 'updated', array( 'since' => $since ) );
		if ( is_wp_error( $body ) ) {
			return $stats; // bridge unreachable — try again next run, cursor kept
		}

		$latest = $since;
		foreach ( (array) ( $body['replies'] ?? array() ) as $reply ) {
			if ( ! is_array( $reply ) ) {
				continue;
			}
			$stats['checked']++;
			$number  = (string) ( $reply['number'] ?? '' );
			$subject = (string) ( $reply['subject'] ?? '' );
			$created = (string) ( $reply['created'] ?? '' );
			if ( '' !== $created && $created > $latest ) {
				$latest = $created;
			}

			foreach ( self::users_for_ticket_email( (string) ( $reply['email'] ?? '' ) ) as $user_id ) {
				$title    = "Support replied — ticket #$number";
				$title_bn = "সাপোর্ট উত্তর দিয়েছে — টিকিট #$number";
				$id       = AUN_App_Notices::create( array(
					'user_id'   => $user_id,
					'type'      => 'ticket',
					'title'     => $title,
					'title_bn'  => $title_bn,
					'body'      => $subject,
					'body_bn'   => $subject,
					'data'      => array( 'number' => $number ),
					'dedup_key' => 'ticket:' . (int) ( $reply['entry_id'] ?? 0 ) . ':' . $user_id,
				) );
				if ( $id && AUN_App_Notices::$last_was_new && AUN_App_Push::configured() ) {
					AUN_App_Push::push_to_users(
						array( $user_id ),
						array(
							'title'    => $title,
							'title_bn' => $title_bn,
							'body'     => $subject,
							'body_bn'  => $subject,
						),
						array( 'notice_id' => $id, 'type' => 'ticket', 'number' => $number )
					);
					$stats['notified']++;
				}
			}
		}

		update_option( self::SINCE_OPTION, $latest, false );
		return $stats;
	}
}
