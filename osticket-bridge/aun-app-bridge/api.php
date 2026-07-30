<?php
/**
 * AUN App <-> osTicket bridge (osTicket v1.18.x).
 *
 * Upload this folder ("aun-app-bridge") into the osTicket ROOT directory on
 * support.smartliving.com.bd (next to main.inc.php), then copy
 * bridge-config.sample.php to bridge-config.php and set a long random secret.
 * The same secret goes into WordPress: AUN App -> Settings -> Support Tickets.
 *
 * The ONLY caller is the aun-projector.com.bd WordPress plugin (server to
 * server, X-AUN-Bridge-Secret header). The customer's phone app never talks
 * to this host directly.
 *
 * Writes go through osTicket's own engine (Ticket::create / postMessage), so
 * staff alerts, auto-responses, SLA and ticket numbering behave exactly as if
 * the ticket came from the web portal. Reads are direct SQL on the osTicket
 * tables (osTicket has no read API).
 *
 * Endpoints (all JSON; GET or POST):
 *   ?action=ping                                     -> {ok, version}
 *   ?action=topics                                   -> active public help topics + their custom fields
 *   ?action=create   (POST)                          -> create a ticket
 *   ?action=tickets  &emails=a,b                     -> the customer's tickets, newest first
 *   ?action=thread   &number=&emails=a,b             -> one ticket + its message thread
 *   ?action=reply    (POST: number, emails, message) -> customer reply into the thread
 *   ?action=updated  &since=YYYY-mm-dd HH:ii:ss      -> staff replies since a timestamp (for push)
 */

// ---------------------------------------------------------------------------
// Config + auth BEFORE booting osTicket (cheap rejection for bad callers).
// ---------------------------------------------------------------------------

$config_file = __DIR__ . '/bridge-config.php';
if ( ! file_exists( $config_file ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'ok' => false, 'error' => 'bridge_not_configured' ) );
	exit;
}
require $config_file; // defines AUN_BRIDGE_SECRET

$given = isset( $_SERVER['HTTP_X_AUN_BRIDGE_SECRET'] ) ? (string) $_SERVER['HTTP_X_AUN_BRIDGE_SECRET'] : '';
if ( ! defined( 'AUN_BRIDGE_SECRET' )
	|| strlen( AUN_BRIDGE_SECRET ) < 20
	|| ! hash_equals( AUN_BRIDGE_SECRET, $given ) ) {
	http_response_code( 403 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'ok' => false, 'error' => 'forbidden' ) );
	exit;
}

// ---------------------------------------------------------------------------
// Boot osTicket.
// ---------------------------------------------------------------------------

@ini_set( 'display_errors', '0' );
define( 'DISABLE_SESSION', true );

require dirname( __DIR__ ) . '/main.inc.php';
require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.topic.php';
require_once INCLUDE_DIR . 'class.dynamic_forms.php';
require_once INCLUDE_DIR . 'class.file.php';       // AttachmentFile (downloads)
require_once INCLUDE_DIR . 'class.user.php';       // User (create/lookup for uid)

function aun_out( $data, $code = 200 ) {
	http_response_code( $code );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( $data, JSON_UNESCAPED_UNICODE );
	exit;
}

function aun_fail( $error, $code = 400, $extra = array() ) {
	aun_out( array_merge( array( 'ok' => false, 'error' => $error ), $extra ), $code );
}

/** Request param helper (POST body JSON preferred, then POST, then GET). */
function aun_param( $key, $default = '' ) {
	static $json = null;
	if ( null === $json ) {
		$raw  = file_get_contents( 'php://input' );
		$json = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $json ) ) {
			$json = array();
		}
	}
	if ( array_key_exists( $key, $json ) ) {
		return $json[ $key ];
	}
	if ( isset( $_POST[ $key ] ) ) {
		return $_POST[ $key ];
	}
	if ( isset( $_GET[ $key ] ) ) {
		return $_GET[ $key ];
	}
	return $default;
}

/** Comma list / array of emails -> sanitised unique array. */
function aun_emails_param() {
	$raw = aun_param( 'emails', '' );
	if ( is_string( $raw ) ) {
		$raw = explode( ',', $raw );
	}
	$out = array();
	foreach ( (array) $raw as $email ) {
		$email = strtolower( trim( (string) $email ) );
		if ( '' !== $email && filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$out[] = $email;
		}
	}
	return array_values( array_unique( $out ) );
}

/** SQL-quoted IN(...) list for emails. */
function aun_email_in_sql( $emails ) {
	$quoted = array();
	foreach ( $emails as $email ) {
		$quoted[] = db_input( $email );
	}
	return implode( ',', $quoted );
}

/** Whether an email is one of our synthetic per-phone addresses. */
function aun_is_synthetic_email( $email ) {
	return (bool) preg_match( '/^8801\d{9}@/', (string) $email );
}

/**
 * Active, public help topics. Filters by the real `ispublic` column and then
 * by the model's own active check (the active flag is a bitfield, not always a
 * filterable column). Robust across osTicket 1.1x versions.
 *
 * @return Topic[]
 */
function aun_public_topics() {
	$out = array();
	try {
		foreach ( Topic::objects()->filter( array( 'ispublic' => 1 ) ) as $topic ) {
			if ( method_exists( $topic, 'isActive' ) && ! $topic->isActive() ) {
				continue;
			}
			$out[] = $topic;
		}
	} catch ( Throwable $e ) {
		// Fall back to a raw query if the ORM shape is unexpected.
		$out = array();
		$P   = TABLE_PREFIX;
		$res = db_query( "SELECT topic_id FROM {$P}help_topic WHERE ispublic = 1 ORDER BY `topic` ASC" );
		while ( $res && ( $row = db_fetch_array( $res ) ) ) {
			$t = Topic::lookup( (int) $row['topic_id'] );
			if ( $t && ( ! method_exists( $t, 'isActive' ) || $t->isActive() ) ) {
				$out[] = $t;
			}
		}
	}
	return $out;
}

/**
 * Resolve (or create) the osTicket end-user for a ticket and return its id.
 *
 * WHY THIS EXISTS: when `Ticket::create()` is called WITHOUT a `uid`, osTicket
 * re-validates the whole "Contact Information" user form from the supplied vars
 * and fails with "Incomplete client information" if that form has ANY required
 * field the API didn't fill (e.g. an admin-added required Phone or custom user
 * field). By creating/looking up the User ourselves and passing its `uid`,
 * osTicket skips that inline validation entirely. `User::fromVars()` only needs
 * a name + email to create the base user (custom user-form data is attached
 * best-effort), so this works regardless of how the user form is configured.
 *
 * @param string $name  Display name (falls back to the email).
 * @param string $email Valid email (synthetic per-phone or the real profile).
 * @param string $phone Optional phone, fed to the user form if it wants one.
 * @return int User id, or 0 if the user could not be resolved/created.
 */
function aun_resolve_user_id( $name, $email, $phone = '' ) {
	try {
		$existing = User::lookupByEmail( $email );
		if ( $existing ) {
			return (int) $existing->getId();
		}
		$uvars = array(
			'name'  => '' !== trim( (string) $name ) ? $name : $email,
			'email' => $email,
		);
		if ( '' !== trim( (string) $phone ) ) {
			$uvars['phone'] = $phone;
		}
		$user = User::fromVars( $uvars, true /* create */ );
		return $user ? (int) $user->getId() : 0;
	} catch ( Throwable $e ) {
		return 0;
	}
}

/**
 * Normalise app-supplied attachments into osTicket API file arrays
 * ({name, type, encoding:'base64', data}). WordPress has already validated
 * count and size before they reach here. Empty/malformed entries are dropped.
 *
 * @param array $items [{name, type, data(base64)}]
 * @return array
 */
function aun_bridge_files( $items ) {
	$out = array();
	foreach ( (array) $items as $f ) {
		if ( ! is_array( $f ) ) {
			continue;
		}
		$name = trim( (string) ( $f['name'] ?? '' ) );
		$data = (string) ( $f['data'] ?? '' ); // base64
		if ( '' === $name || '' === $data ) {
			continue;
		}
		$type  = trim( (string) ( $f['type'] ?? '' ) );
		$out[] = array(
			'name'     => $name,
			'type'     => '' !== $type ? $type : 'application/octet-stream',
			'encoding' => 'base64',
			'data'     => $data,
		);
		if ( count( $out ) >= 10 ) {
			break; // hard ceiling; WP enforces the real limit
		}
	}
	return $out;
}

/**
 * Ownership gate: the ticket's user must hold one of the given addresses.
 *
 * @return Ticket
 */
function aun_owned_ticket( $number, $emails ) {
	$number = trim( (string) $number );
	if ( '' === $number || empty( $emails ) ) {
		aun_fail( 'not_found', 404 );
	}
	$ticket = Ticket::lookupByNumber( $number );
	if ( ! $ticket ) {
		aun_fail( 'not_found', 404 );
	}
	$sql = 'SELECT COUNT(*) FROM ' . TABLE_PREFIX . 'user_email
	         WHERE user_id = ' . db_input( (int) $ticket->getOwnerId() ) . '
	           AND address IN (' . aun_email_in_sql( $emails ) . ')';
	if ( ! db_result( db_query( $sql ) ) ) {
		aun_fail( 'not_found', 404 ); // never reveal foreign tickets exist
	}
	return $ticket;
}

/** Ticket row -> summary array shared by tickets/thread. */
function aun_ticket_summary( $row ) {
	return array(
		'number'     => (string) $row['number'],
		'subject'    => (string) ( $row['subject'] ?? '' ),
		'status'     => (string) ( $row['status_name'] ?? '' ),
		// open | closed (osTicket status *state*; archived/deleted never listed)
		'state'      => (string) ( $row['state'] ?? 'open' ),
		'answered'   => ! empty( $row['isanswered'] ),
		'created'    => (string) $row['created'],
		'updated'    => (string) ( $row['lastupdate'] ?: $row['created'] ),
	);
}

$action = isset( $_GET['action'] ) ? (string) $_GET['action'] : '';

switch ( $action ) {

	// -----------------------------------------------------------------------
	case 'ping':
		aun_out( array( 'ok' => true, 'version' => defined( 'THIS_VERSION' ) ? THIS_VERSION : '?' ) );

	// -----------------------------------------------------------------------
	// Active, public help topics — id + name only. The app never needs the
	// per-topic custom-field metadata (WordPress fills those fields from its
	// own admin mapping), so we avoid iterating dynamic forms entirely: that
	// was fragile and could 500 the whole call. `ispublic` is a real column;
	// active state lives in a flags bitfield across osTicket versions, so it
	// is checked via the model's own isActive() rather than an ORM filter.
	// -----------------------------------------------------------------------
	case 'topics':
		$topics = array();
		foreach ( aun_public_topics() as $topic ) {
			$topics[] = array(
				'id'   => (int) $topic->getId(),
				'name' => (string) $topic->getFullName(),
			);
		}
		aun_out( array( 'ok' => true, 'topics' => $topics ) );

	// -----------------------------------------------------------------------
	// Create a ticket through osTicket's own pipeline (alerts + numbering +
	// SLA all normal). `fields` carries custom form values by variable name;
	// unknown variables are ignored by osTicket, invalid required ones come
	// back in `errors`.
	// -----------------------------------------------------------------------
	case 'create':
		$name    = trim( (string) aun_param( 'name' ) );
		$email   = strtolower( trim( (string) aun_param( 'email' ) ) );
		$phone   = trim( (string) aun_param( 'phone' ) );
		$subject = trim( (string) aun_param( 'subject' ) );
		$html    = (string) aun_param( 'message_html' );
		$topic   = (int) aun_param( 'topic_id' );

		if ( '' === $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			aun_fail( 'invalid_email' );
		}
		if ( '' === $subject || '' === trim( strip_tags( $html ) ) ) {
			aun_fail( 'missing_fields' );
		}

		// osTicket needs a valid help topic. If the app didn't send one (topics
		// list was empty/unavailable), fall back to the first active public one.
		if ( $topic <= 0 ) {
			$first = aun_public_topics();
			if ( ! empty( $first ) ) {
				$topic = (int) $first[0]->getId();
			}
		}

		// Resolve/create the end-user up front and pass its uid, so
		// Ticket::create never re-validates the user form (the source of the
		// "Incomplete client information" failure). name/email stay as a
		// harmless fallback.
		$uid = aun_resolve_user_id( $name, $email, $phone );

		$vars = array(
			'name'    => '' !== $name ? $name : $email,
			'email'   => $email,
			'phone'   => $phone,
			'subject' => mb_substr( $subject, 0, 255 ),
			// Plain HTML — NOT a data: URI. osTicket's direct Ticket::create path
			// does not decode a "data:text/html,…" message (it stored the literal
			// prefix + our content), but it DOES wrap a plain string as an
			// HtmlThreadEntryBody (rich text is enabled), so raw HTML renders
			// correctly and UTF-8 (Bangla, etc.) is preserved.
			'message' => $html,
			'topicId' => $topic > 0 ? $topic : 0,
			'source'  => 'API',
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
		);
		if ( $uid > 0 ) {
			$vars['uid'] = $uid;
		}
		foreach ( (array) aun_param( 'fields', array() ) as $var => $value ) {
			$var = trim( (string) $var );
			if ( '' !== $var && ! isset( $vars[ $var ] ) && is_scalar( $value ) ) {
				$vars[ $var ] = (string) $value;
			}
		}

		// Customer-uploaded files (osTicket API base64 format). Attached to the
		// opening message so the agent sees them on the ticket.
		$files = aun_bridge_files( aun_param( 'attachments', array() ) );
		if ( $files ) {
			$vars['attachments'] = $files;
		}

		// Synthetic per-phone addresses would only bounce — skip the
		// auto-response for them (the app shows the ticket instantly anyway).
		$autorespond = ! aun_is_synthetic_email( $email );

		// SELF-HEALING create. A customer must never be blocked by admin form
		// config (e.g. a required "Purchase Channel" choices field rejecting a
		// value that isn't in its list yet). Attempts, in order:
		//   1. as requested (custom fields + chosen topic);
		//   2. without the custom fields (they are nice-to-have metadata — the
		//      agent context table in the message body carries everything);
		//   3. without custom fields on the first public topic (topic-level
		//      required fields can then no longer apply).
		$attempts = array( $vars );

		$bare = $vars;
		foreach ( array_keys( (array) aun_param( 'fields', array() ) ) as $custom_key ) {
			unset( $bare[ $custom_key ] );
		}
		if ( $bare !== $vars ) {
			$attempts[] = $bare;
		}

		$fallback_topics = aun_public_topics();
		if ( ! empty( $fallback_topics ) ) {
			$other = $bare;
			$other['topicId'] = (int) $fallback_topics[0]->getId();
			if ( $other !== $bare ) {
				$attempts[] = $other;
			}
		}

		$ticket      = null;
		$errors      = array();
		$used_fields = true;
		foreach ( $attempts as $i => $attempt_vars ) {
			$errors = array();
			$ticket = Ticket::create( $attempt_vars, $errors, 'API', $autorespond, true );
			if ( $ticket ) {
				$used_fields = ( 0 === $i );
				break;
			}
		}
		if ( ! $ticket ) {
			aun_fail( 'create_failed', 422, array(
				'errors'        => $errors,
				// Diagnostic: whether we managed to pre-resolve the end-user. If
				// false and the error mentions the user/client, the osTicket
				// "Contact Information" (user) form has a required field that
				// blocked user creation.
				'user_resolved' => $uid > 0,
			) );
		}
		aun_out( array(
			'ok'     => true,
			'number' => (string) $ticket->getNumber(),
			// False = the admin field mapping was dropped to save the ticket —
			// a hint (in WP logs) that a mapped value no longer validates.
			'fields_applied' => $used_fields,
		), 201 );

	// -----------------------------------------------------------------------
	case 'tickets':
		$emails = aun_emails_param();
		if ( empty( $emails ) ) {
			aun_out( array( 'ok' => true, 'tickets' => array() ) );
		}
		$P   = TABLE_PREFIX;
		$sql = "SELECT t.number, t.created, t.lastupdate, t.isanswered,
		               ts.name AS status_name, ts.state, cd.subject
		          FROM {$P}ticket t
		          JOIN {$P}ticket_status ts ON ts.id = t.status_id
		     LEFT JOIN {$P}ticket__cdata cd ON cd.ticket_id = t.ticket_id
		          JOIN {$P}user_email ue ON ue.user_id = t.user_id
		         WHERE ue.address IN (" . aun_email_in_sql( $emails ) . ")
		           AND ts.state IN ('open','closed')
		      GROUP BY t.ticket_id
		      ORDER BY COALESCE(t.lastupdate, t.created) DESC
		         LIMIT 100";
		$res  = db_query( $sql );
		$list = array();
		while ( $res && ( $row = db_fetch_array( $res ) ) ) {
			$list[] = aun_ticket_summary( $row );
		}
		aun_out( array( 'ok' => true, 'tickets' => $list ) );

	// -----------------------------------------------------------------------
	// STAFF-SIDE queue summary: how many tickets are open, and how many are
	// waiting on US. Not scoped to a customer — this answers "is anyone waiting
	// for a reply right now?" for the WordPress admin bar, so the team never has
	// to remember to log into the support centre.
	//
	// osTicket's own `isanswered` flag is the source of truth: 0 means the last
	// post on the thread came from the customer, i.e. the ball is in our court.
	case 'queue':
		$P   = TABLE_PREFIX;
		$sql = "SELECT t.ticket_id, t.number, t.created, t.lastupdate, t.isanswered,
		               ts.name AS status_name, ts.state, cd.subject
		          FROM {$P}ticket t
		          JOIN {$P}ticket_status ts ON ts.id = t.status_id
		     LEFT JOIN {$P}ticket__cdata cd ON cd.ticket_id = t.ticket_id
		         WHERE ts.state = 'open'
		      ORDER BY t.isanswered ASC, COALESCE(t.lastupdate, t.created) ASC
		         LIMIT 25";
		$res     = db_query( $sql );
		$list    = array();
		$open    = 0;
		$waiting = 0;
		while ( $res && ( $row = db_fetch_array( $res ) ) ) {
			$open++;
			$item               = aun_ticket_summary( $row );
			$item['ticket_id']  = (int) $row['ticket_id'];
			$item['waiting']    = empty( $row['isanswered'] );
			if ( $item['waiting'] ) {
				$waiting++;
			}
			$list[] = $item;
		}

		// The LIMIT above caps the detail list; get the true totals separately
		// so a busy queue still reports honest numbers.
		$tot = db_fetch_array( db_query(
			"SELECT COUNT(*) AS open_total,
			        SUM(CASE WHEN t.isanswered = 0 THEN 1 ELSE 0 END) AS waiting_total
			   FROM {$P}ticket t
			   JOIN {$P}ticket_status ts ON ts.id = t.status_id
			  WHERE ts.state = 'open'"
		) );

		aun_out( array(
			'ok'      => true,
			'open'    => isset( $tot['open_total'] ) ? (int) $tot['open_total'] : $open,
			'waiting' => isset( $tot['waiting_total'] ) ? (int) $tot['waiting_total'] : $waiting,
			'tickets' => $list,
		) );

	// -----------------------------------------------------------------------
	case 'thread':
		$emails = aun_emails_param();
		$ticket = aun_owned_ticket( aun_param( 'number' ), $emails );

		$P   = TABLE_PREFIX;
		$tid = (int) $ticket->getId();

		$head_sql = "SELECT t.number, t.created, t.lastupdate, t.isanswered,
		                    ts.name AS status_name, ts.state, cd.subject
		               FROM {$P}ticket t
		               JOIN {$P}ticket_status ts ON ts.id = t.status_id
		          LEFT JOIN {$P}ticket__cdata cd ON cd.ticket_id = t.ticket_id
		              WHERE t.ticket_id = " . db_input( $tid );
		$head     = db_fetch_array( db_query( $head_sql ) );

		// M = customer message, R = staff response. Internal notes (N) are
		// staff-only and never leave this server.
		$sql = "SELECT te.id, te.type, te.poster, te.body, te.format, te.created
		          FROM {$P}thread th
		          JOIN {$P}thread_entry te ON te.thread_id = th.id
		         WHERE th.object_id = " . db_input( $tid ) . "
		           AND th.object_type = 'T'
		           AND te.type IN ('M','R')
		      ORDER BY te.created ASC, te.id ASC
		         LIMIT 300";
		$res     = db_query( $sql );
		$entries = array();
		while ( $res && ( $row = db_fetch_array( $res ) ) ) {
			$entries[] = array(
				'id'          => (int) $row['id'],
				// 'customer' | 'staff' — the app renders a chat from this.
				'from'        => 'M' === $row['type'] ? 'customer' : 'staff',
				'poster'      => (string) $row['poster'],
				'body'        => (string) $row['body'],
				'format'      => (string) $row['format'], // html | text
				'created'     => (string) $row['created'],
				'attachments' => array(),
			);
		}

		// Real (non-inline) attachments per entry — the files an agent or the
		// customer attached, downloadable through the WordPress proxy. `ref` is
		// the attachment id; the download re-verifies ownership via this ticket.
		$att_sql = "SELECT a.object_id AS entry_id, a.id AS ref,
		                   COALESCE(NULLIF(a.name, ''), f.name) AS name, f.type AS mime, f.size
		              FROM {$P}attachment a
		              JOIN {$P}file f ON f.id = a.file_id
		              JOIN {$P}thread_entry te ON te.id = a.object_id AND a.`type` = 'H'
		              JOIN {$P}thread th ON th.id = te.thread_id AND th.object_type = 'T'
		             WHERE th.object_id = " . db_input( $tid ) . " AND a.inline = 0
		          ORDER BY a.id ASC";
		$att_by_entry = array();
		$ares         = db_query( $att_sql );
		while ( $ares && ( $arow = db_fetch_array( $ares ) ) ) {
			$att_by_entry[ (int) $arow['entry_id'] ][] = array(
				'ref'  => (int) $arow['ref'],
				'name' => (string) $arow['name'],
				'size' => (int) $arow['size'],
				'type' => (string) $arow['mime'],
			);
		}
		foreach ( $entries as &$entry_ref ) {
			if ( isset( $att_by_entry[ $entry_ref['id'] ] ) ) {
				$entry_ref['attachments'] = $att_by_entry[ $entry_ref['id'] ];
			}
		}
		unset( $entry_ref );

		aun_out( array(
			'ok'      => true,
			'ticket'  => $head ? aun_ticket_summary( $head ) : null,
			'entries' => $entries,
		) );

	// -----------------------------------------------------------------------
	// Customer reply — postMessage() alerts the assigned staff and reopens a
	// closed-but-reopenable ticket exactly like a portal reply would.
	// -----------------------------------------------------------------------
	case 'reply':
		$emails = aun_emails_param();
		$ticket = aun_owned_ticket( aun_param( 'number' ), $emails );
		$html   = (string) aun_param( 'message_html' );
		if ( '' === trim( strip_tags( $html ) ) ) {
			aun_fail( 'missing_message' );
		}

		$vars = array(
			// Plain HTML, not a data: URI (see the create action).
			'message' => $html,
			'userId'  => (int) $ticket->getOwnerId(),
			'poster'  => (string) $ticket->getName(),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
		);
		$files = aun_bridge_files( aun_param( 'attachments', array() ) );
		if ( $files ) {
			$vars['attachments'] = $files;
		}
		$entry = $ticket->postMessage( $vars, 'API' );
		if ( ! $entry ) {
			aun_fail( 'reply_failed', 422 );
		}
		aun_out( array( 'ok' => true, 'number' => (string) $ticket->getNumber() ), 201 );

	// -----------------------------------------------------------------------
	// Stream ONE attachment's bytes. Ownership is enforced by the join (the
	// attachment must hang off a thread entry of a ticket the caller's emails
	// own), so a guessed ref from another ticket resolves to nothing → 404.
	// Raw binary out (not JSON) — WordPress proxies it to the phone.
	// -----------------------------------------------------------------------
	case 'attachment':
		$emails = aun_emails_param();
		$ticket = aun_owned_ticket( aun_param( 'number' ), $emails );
		$ref    = (int) aun_param( 'ref' );
		$P      = TABLE_PREFIX;
		$tid    = (int) $ticket->getId();

		$file_id = db_result( db_query(
			"SELECT a.file_id
			   FROM {$P}attachment a
			   JOIN {$P}thread_entry te ON te.id = a.object_id AND a.`type` = 'H'
			   JOIN {$P}thread th ON th.id = te.thread_id AND th.object_type = 'T'
			  WHERE th.object_id = " . db_input( $tid ) . "
			    AND a.id = " . db_input( $ref )
		) );
		if ( ! $file_id ) {
			aun_fail( 'not_found', 404 );
		}
		$file = AttachmentFile::lookup( (int) $file_id );
		if ( ! $file ) {
			aun_fail( 'not_found', 404 );
		}

		$data = $file->getData();
		$name = (string) $file->getName();
		$type = $file->getType() ?: 'application/octet-stream';

		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $name ) . '"' );
		header( 'X-AUN-Filename: ' . rawurlencode( $name ) ); // WP reads the real name from here
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data;
		exit;

	// -----------------------------------------------------------------------
	// Staff replies since a timestamp — WordPress polls this on a cron and
	// turns each row into an in-app notice + push notification.
	// -----------------------------------------------------------------------
	case 'updated':
		$since = trim( (string) aun_param( 'since' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since ) ) {
			aun_fail( 'invalid_since' );
		}
		$P   = TABLE_PREFIX;
		$sql = "SELECT te.id AS entry_id, te.created, te.poster,
		               t.number, cd.subject, ue.address AS email
		          FROM {$P}thread_entry te
		          JOIN {$P}thread th ON th.id = te.thread_id AND th.object_type = 'T'
		          JOIN {$P}ticket t ON t.ticket_id = th.object_id
		     LEFT JOIN {$P}ticket__cdata cd ON cd.ticket_id = t.ticket_id
		          JOIN {$P}user u ON u.id = t.user_id
		          JOIN {$P}user_email ue ON ue.id = u.default_email_id
		         WHERE te.type = 'R'
		           AND te.created > " . db_input( $since ) . "
		      ORDER BY te.created ASC
		         LIMIT 100";
		$res     = db_query( $sql );
		$replies = array();
		while ( $res && ( $row = db_fetch_array( $res ) ) ) {
			$replies[] = array(
				'entry_id' => (int) $row['entry_id'],
				'number'   => (string) $row['number'],
				'subject'  => (string) ( $row['subject'] ?? '' ),
				'poster'   => (string) $row['poster'],
				'email'    => strtolower( (string) $row['email'] ),
				'created'  => (string) $row['created'],
			);
		}
		aun_out( array( 'ok' => true, 'replies' => $replies ) );

	// -----------------------------------------------------------------------
	default:
		aun_fail( 'unknown_action', 404 );
}
