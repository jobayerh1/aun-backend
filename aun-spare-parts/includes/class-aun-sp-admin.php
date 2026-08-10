<?php
/**
 * Admin UI for AUN Spare Parts (Phase 1).
 *
 * Menu: Spare Parts
 *   - Dashboard      : at-a-glance counts (filled out as later phases land)
 *   - Import & test  : import the inFlow CSV, then test phone/order/serial lookups
 *   - Settings       : warranty months, grace days, alert email
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Admin {

	private $requests;

	public function __construct() {
		$this->requests = new AUN_SP_Requests();
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'admin_title', array( $this, 'admin_title' ), 10, 2 );
	}

	public function menu() {
		add_menu_page(
			'Spare Parts',
			'Spare Parts',
			'manage_options',
			'aun-sp',
			array( $this, 'page_dashboard' ),
			'dashicons-screenoptions',
			56
		);
		// Page title 'Spare Parts' (not 'Dashboard') so the browser tab is distinct from
		// the main WordPress Dashboard; the menu label stays "Dashboard".
		add_submenu_page( 'aun-sp', 'Spare Parts', 'Dashboard', 'manage_options', 'aun-sp', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'aun-sp', 'Import & test', 'Import & test', 'manage_options', 'aun-sp-import', array( $this, 'page_import' ) );
		add_submenu_page( 'aun-sp', 'Parts catalogue', 'Parts catalogue', 'manage_options', 'aun-sp-parts', array( $this, 'page_parts' ) );
		add_submenu_page( 'aun-sp', 'Messages', 'Messages', 'manage_options', 'aun-sp-messages', array( $this, 'page_messages' ) );
		add_submenu_page( 'aun-sp', 'Translations', 'Translations', 'manage_options', 'aun-sp-translations', array( $this, 'page_translations' ) );
		add_submenu_page( 'aun-sp', 'Settings', 'Settings', 'manage_options', 'aun-sp-settings', array( $this, 'page_settings' ) );
	}

	/**
	 * Give every Spare Parts screen a clear, distinct browser-tab title (the Dashboard
	 * one otherwise read exactly like the main WordPress "Dashboard"). On a request
	 * detail page the title leads with the SP- reference, so multiple open tabs are easy
	 * to tell apart.
	 */
	public function admin_title( $admin_title, $title ) {
		if ( empty( $_GET['page'] ) ) {
			return $admin_title;
		}
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( strpos( $page, 'aun-sp' ) !== 0 ) {
			return $admin_title;
		}

		$labels = array(
			'aun-sp'              => 'Spare parts requests',
			'aun-sp-import'       => 'Import & test',
			'aun-sp-parts'        => 'Parts catalogue',
			'aun-sp-messages'     => 'Messages',
			'aun-sp-translations' => 'Translations',
			'aun-sp-settings'     => 'Settings',
		);
		$label = isset( $labels[ $page ] ) ? $labels[ $page ] : 'Spare Parts';

		if ( 'aun-sp' === $page && ! empty( $_GET['request'] ) ) {
			global $wpdb;
			$ref = $wpdb->get_var( $wpdb->prepare(
				'SELECT ref FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
				(int) $_GET['request']
			) );
			$label = ( $ref ? $ref : 'Request #' . (int) $_GET['request'] ) . ' · Spare parts';
		}

		return $label . ' ‹ ' . get_bloginfo( 'name' ) . ' — Spare Parts';
	}

	/* ---------------------------------------------------------------- Dashboard */

	public function page_dashboard() {
		$this->requests->screen();
	}

	/* --------------------------------------------------------------- Import + test */

	public function page_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$import_notice = '';
		$lookup_result = null;

		// Handle CSV import (uploaded file OR a server path, for files over the upload limit).
		if ( isset( $_POST['aun_sp_import_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_import_nonce'], 'aun_sp_import' ) ) {
			$path = '';
			if ( ! empty( $_FILES['aun_sp_csv']['tmp_name'] ) && is_uploaded_file( $_FILES['aun_sp_csv']['tmp_name'] ) ) {
				$path = $_FILES['aun_sp_csv']['tmp_name'];
			} elseif ( ! empty( $_POST['aun_sp_csv_path'] ) ) {
				$candidate = realpath( trim( wp_unslash( $_POST['aun_sp_csv_path'] ) ) );
				if ( $candidate && is_file( $candidate ) && strtolower( pathinfo( $candidate, PATHINFO_EXTENSION ) ) === 'csv' ) {
					$path = $candidate;
				}
			}

			if ( $path === '' ) {
				$import_notice = $this->notice( 'No CSV provided (or the server path is not a readable .csv file).', 'error' );
			} else {
				$res = AUN_SP_Importer::import_csv( $path );
				if ( is_wp_error( $res ) ) {
					$import_notice = $this->notice( 'Import failed: ' . esc_html( $res->get_error_message() ), 'error' );
				} else {
					$import_notice = $this->notice( sprintf(
						'Imported %s AUN warranty orders. Skipped %s non-AUN orders and %s quote/blank rows.',
						number_format_i18n( $res['imported'] ),
						number_format_i18n( $res['dropped'] ),
						number_format_i18n( $res['skipped'] )
					), 'success' );
				}
			}
		}

		// Handle a lookup test.
		if ( isset( $_POST['aun_sp_lookup_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_lookup_nonce'], 'aun_sp_lookup' ) ) {
			$search_by     = sanitize_text_field( wp_unslash( $_POST['search_by'] ?? 'mobile' ) );
			$query         = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
			$lookup_result = AUN_SP_Lookup::find( $search_by, $query );
		}

		$upload_max = ini_get( 'upload_max_filesize' );

		echo '<div class="wrap"><h1>Import &amp; test</h1>';
		echo $import_notice;

		echo '<h2>Import legacy sales (inFlow CSV)</h2>';
		echo '<p style="color:#646970;max-width:640px;">Loads the inFlow export into the read-only legacy archive. Re-running is safe — orders are matched by order number and updated, never duplicated. Only <code>Paid</code> and <code>Invoiced</code> orders are imported.</p>';
		echo '<form method="post" enctype="multipart/form-data" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;max-width:640px;">';
		wp_nonce_field( 'aun_sp_import', 'aun_sp_import_nonce' );
		echo '<p><label><strong>Upload CSV</strong> <span style="color:#646970;">(server upload limit: ' . esc_html( $upload_max ) . ')</span><br><input type="file" name="aun_sp_csv" accept=".csv"></label></p>';
		echo '<p style="color:#646970;">— or, if the file is larger than the upload limit —</p>';
		echo '<p><label><strong>Server path to CSV</strong><br><input type="text" name="aun_sp_csv_path" class="regular-text" placeholder="/home/user/inFlow_SalesOrder.csv" style="width:100%;max-width:600px;"></label></p>';
		echo '<p><button type="submit" class="button button-primary">Import now</button></p>';
		echo '</form>';

		echo '<h2 style="margin-top:28px;">Test a lookup</h2>';
		echo '<p style="color:#646970;">Confirm the importer + warranty calc against real orders before we build the customer form.</p>';
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;max-width:640px;">';
		wp_nonce_field( 'aun_sp_lookup', 'aun_sp_lookup_nonce' );
		$sb = sanitize_text_field( wp_unslash( $_POST['search_by'] ?? 'mobile' ) );
		echo '<p><label>Search by ';
		echo '<select name="search_by">';
		foreach ( array( 'mobile' => 'Phone number', 'order' => 'Order number', 'serial' => 'Serial number' ) as $val => $lbl ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $sb, $val, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select></label> ';
		echo '<input type="text" name="query" class="regular-text" value="' . esc_attr( wp_unslash( $_POST['query'] ?? '' ) ) . '" placeholder="01711561441 or SO-000003"> ';
		echo '<button type="submit" class="button">Look up</button></p>';
		echo '</form>';

		if ( is_array( $lookup_result ) ) {
			$this->render_lookup_result( $lookup_result );
		}

		echo '</div>';
	}

	private function render_lookup_result( array $r ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;max-width:640px;margin-top:14px;">';
		if ( empty( $r['found'] ) ) {
			echo '<p style="margin:0;color:#b32d2e;"><strong>No matching purchase found.</strong></p>';
			echo '</div>';
			return;
		}
		$w     = $r['warranty'];
		$wcolor = ! empty( $w['known'] ) ? ( $w['in_warranty'] ? '#1a7f37' : '#bf6a02' ) : '#646970';

		echo '<table class="widefat striped" style="border:0;"><tbody>';
		$this->kv( 'Found in', strtoupper( esc_html( $r['source'] ) ) );
		$this->kv( 'Order number', esc_html( $r['order_number'] ) );
		$this->kv( 'Device model', esc_html( $r['model'] ) );
		$this->kv( 'Purchase date', esc_html( $r['purchase_date'] ) );
		$this->kv( 'Warranty', '<strong style="color:' . esc_attr( $wcolor ) . ';">' . esc_html( $w['label'] ) . '</strong>' . ( ! empty( $w['known'] ) ? ' <span style="color:#646970;">(ends ' . esc_html( $w['ends'] ) . ')</span>' : '' ) );
		$this->kv( 'Customer name', esc_html( $r['customer_name'] ) );
		$this->kv( 'Phone on file', esc_html( $r['phone'] ) );
		$this->kv( 'Address on file', esc_html( $r['address'] ) );
		echo '</tbody></table>';
		echo '</div>';
	}

	private function kv( $k, $v ) {
		echo '<tr><td style="width:160px;color:#646970;">' . esc_html( $k ) . '</td><td>' . $v . '</td></tr>';
	}

	/* ------------------------------------------------------------ Parts catalogue */

	public function page_parts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		$notice = '';
		if ( isset( $_POST['aun_sp_parts_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_parts_nonce'], 'aun_sp_parts' ) ) {
			$notice = $this->save_parts();
		}

		$parts  = AUN_SP_Parts::all();
		$photos = array( 'required' => 'Photo required', 'optional' => 'Photo optional', 'none' => 'No photo' );

		echo '<div class="wrap"><h1>Parts catalogue</h1>';
		echo '<p style="color:#646970;max-width:720px;">These are the parts a customer can request on the form. Edit the labels (English + বাংলা), the proof photo each one needs, and whether it\'s active. Untick <em>Active</em> to hide a part without deleting it. Add a new part in the last row.</p>';
		echo $notice;

		echo '<form method="post">';
		wp_nonce_field( 'aun_sp_parts', 'aun_sp_parts_nonce' );
		echo '<input type="hidden" name="p_count" value="' . count( $parts ) . '">';
		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		echo '<th style="width:80px;">Key</th><th>Label (EN)</th><th>Label (বাংলা)</th><th>Proof note (EN)</th><th>Proof note (বাংলা)</th><th style="width:110px;">Photo</th><th>Reference image</th><th style="width:50px;">Active</th><th style="width:55px;">Delete</th>';
		echo '</tr></thead><tbody>';

		$i = 0;
		foreach ( $parts as $p ) {
			$this->part_row( (string) $i, $p, $photos, false );
			$i++;
		}
		$this->part_row( 'new', array( 'key' => '', 'label_en' => '', 'label_bn' => '', 'proof_en' => '', 'proof_bn' => '', 'photo' => 'required', 'ref_image' => '', 'active' => 1 ), $photos, true );

		echo '</tbody></table>';
		echo '<p><button class="button button-primary">Save catalogue</button></p>';
		echo '</form>';
		echo '<script data-no-optimize="1" data-no-minify="1">jQuery(function($){$(document).on("click",".aun-sp-pick-img",function(e){e.preventDefault();var c=$(this).closest("td"),u=c.find(".aun-sp-img-url"),th=c.find(".aun-sp-img-thumb");var f=wp.media({title:"Select reference image",button:{text:"Use this image"},multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();u.val(a.url);th.html("<img src=\'"+a.url+"\' style=\'max-width:60px;max-height:46px;margin-top:4px;border-radius:4px;display:block;\'>");});f.open();});});</script>';
		echo '</div>';
	}

	private function part_row( $i, $p, $photos, $is_new ) {
		$pre = 'p_' . $i . '_';
		echo '<tr>';
		echo '<td><input type="text" name="' . esc_attr( $pre ) . 'key" value="' . esc_attr( $p['key'] ) . '" class="small-text" ' . ( $is_new ? 'placeholder="auto"' : 'readonly' ) . '></td>';
		echo '<td><input type="text" name="' . esc_attr( $pre ) . 'label_en" value="' . esc_attr( $p['label_en'] ) . '" style="width:150px;" ' . ( $is_new ? 'placeholder="e.g. Speaker"' : '' ) . '></td>';
		echo '<td><input type="text" name="' . esc_attr( $pre ) . 'label_bn" value="' . esc_attr( $p['label_bn'] ) . '" style="width:150px;"></td>';
		echo '<td><input type="text" name="' . esc_attr( $pre ) . 'proof_en" value="' . esc_attr( $p['proof_en'] ) . '" style="width:210px;"></td>';
		echo '<td><input type="text" name="' . esc_attr( $pre ) . 'proof_bn" value="' . esc_attr( $p['proof_bn'] ) . '" style="width:210px;"></td>';
		echo '<td><select name="' . esc_attr( $pre ) . 'photo">';
		foreach ( $photos as $val => $lbl ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $p['photo'], $val, false ) . '>' . esc_html( $lbl ) . '</option>';
		}
		echo '</select></td>';
		$img = isset( $p['ref_image'] ) ? $p['ref_image'] : '';
		echo '<td><input type="url" name="' . esc_attr( $pre ) . 'ref_image" value="' . esc_attr( $img ) . '" class="aun-sp-img-url" style="width:150px;" placeholder="image URL"> <button type="button" class="button aun-sp-pick-img">Choose</button><div class="aun-sp-img-thumb">' . ( $img ? '<img src="' . esc_url( $img ) . '" style="max-width:60px;max-height:46px;margin-top:4px;border-radius:4px;display:block;">' : '' ) . '</div></td>';
		echo '<td style="text-align:center;"><input type="checkbox" name="' . esc_attr( $pre ) . 'active" value="1" ' . checked( ! empty( $p['active'] ), true, false ) . '></td>';
		echo '<td style="text-align:center;">' . ( $is_new ? '' : '<input type="checkbox" name="' . esc_attr( $pre ) . 'delete" value="1">' ) . '</td>';
		echo '</tr>';
	}

	private function save_parts() {
		$count   = (int) ( $_POST['p_count'] ?? 0 );
		$allowed = array( 'required', 'optional', 'none' );
		$out     = array();
		$seen    = array();

		$collect = function ( $i ) use ( $allowed, &$seen ) {
			$pre = 'p_' . $i . '_';
			if ( ! empty( $_POST[ $pre . 'delete' ] ) ) {
				return null;
			}
			$label_en = sanitize_text_field( wp_unslash( $_POST[ $pre . 'label_en' ] ?? '' ) );
			if ( $label_en === '' ) {
				return null; // empty row (incl. the blank add-row when unused)
			}
			$key = sanitize_key( $_POST[ $pre . 'key' ] ?? '' );
			if ( $key === '' ) {
				$key = sanitize_key( $label_en );
			}
			if ( $key === '' ) {
				$key = 'part';
			}
			$base = $key;
			$n    = 2;
			while ( isset( $seen[ $key ] ) ) {
				$key = $base . '_' . $n;
				$n++;
			}
			$seen[ $key ] = true;

			$photo = sanitize_text_field( wp_unslash( $_POST[ $pre . 'photo' ] ?? 'required' ) );
			if ( ! in_array( $photo, $allowed, true ) ) {
				$photo = 'required';
			}

			return array(
				'key'      => $key,
				'label_en' => $label_en,
				'label_bn' => sanitize_text_field( wp_unslash( $_POST[ $pre . 'label_bn' ] ?? '' ) ),
				'proof_en' => sanitize_text_field( wp_unslash( $_POST[ $pre . 'proof_en' ] ?? '' ) ),
				'proof_bn'  => sanitize_text_field( wp_unslash( $_POST[ $pre . 'proof_bn' ] ?? '' ) ),
				'photo'     => $photo,
				'ref_image' => esc_url_raw( wp_unslash( $_POST[ $pre . 'ref_image' ] ?? '' ) ),
				'active'    => ! empty( $_POST[ $pre . 'active' ] ) ? 1 : 0,
			);
		};

		for ( $i = 0; $i < $count; $i++ ) {
			$row = $collect( (string) $i );
			if ( $row ) {
				$out[] = $row;
			}
		}
		$new = $collect( 'new' );
		if ( $new ) {
			$out[] = $new;
		}

		if ( empty( $out ) ) {
			return $this->notice( 'Keep at least one part — nothing was saved.', 'error' );
		}

		AUN_SP_Parts::save( $out );
		return $this->notice( 'Parts catalogue saved.', 'success' );
	}

	/* ------------------------------------------------------------------- Messages */

	public function page_messages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = '';
		if ( isset( $_POST['aun_sp_messages_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_messages_nonce'], 'aun_sp_messages' ) && isset( $_POST['restore_defaults'] ) ) {
			foreach ( AUN_SP_Messages::sms_defaults() as $k => $v ) {
				update_option( $k, $v );
			}
			update_option( AUN_SP_Messages::OPT_REJECT_TPL, AUN_SP_Messages::reject_defaults() );
			$notice = $this->notice( 'Default message wording restored. (Your payment instructions were kept.)', 'success' );
		} elseif ( isset( $_POST['aun_sp_messages_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_messages_nonce'], 'aun_sp_messages' ) ) {
			update_option( AUN_SP_Messages::OPT_SMS_RECEIVED, sanitize_textarea_field( wp_unslash( $_POST['sms_received'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_STATUS, sanitize_textarea_field( wp_unslash( $_POST['sms_status'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_PARTS, sanitize_textarea_field( wp_unslash( $_POST['sms_parts'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_REJECT, sanitize_textarea_field( wp_unslash( $_POST['sms_reject'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_PHOTO, sanitize_textarea_field( wp_unslash( $_POST['sms_photo'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_QUOTE, sanitize_textarea_field( wp_unslash( $_POST['sms_quote'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_APPROVED, sanitize_textarea_field( wp_unslash( $_POST['sms_approved'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_PAY, sanitize_textarea_field( wp_unslash( $_POST['sms_pay'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_PAID, sanitize_textarea_field( wp_unslash( $_POST['sms_paid'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_REFUND, sanitize_textarea_field( wp_unslash( $_POST['sms_refund'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_REMIND, sanitize_textarea_field( wp_unslash( $_POST['sms_remind'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_REMIND2, sanitize_textarea_field( wp_unslash( $_POST['sms_remind_final'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_EXPIRED, sanitize_textarea_field( wp_unslash( $_POST['sms_expired'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_SMS_DECLINED, sanitize_textarea_field( wp_unslash( $_POST['sms_declined'] ?? '' ) ) );
			update_option( AUN_SP_Messages::OPT_PAY_INFO, sanitize_textarea_field( wp_unslash( $_POST['pay_info'] ?? '' ) ) );

			$count = (int) ( $_POST['r_count'] ?? 0 );
			$tpls  = array();
			for ( $i = 0; $i <= $count; $i++ ) {
				if ( ! empty( $_POST[ 'r_del_' . $i ] ) ) {
					continue;
				}
				$name = sanitize_text_field( wp_unslash( $_POST[ 'r_name_' . $i ] ?? '' ) );
				$text = sanitize_textarea_field( wp_unslash( $_POST[ 'r_text_' . $i ] ?? '' ) );
				if ( $name === '' || $text === '' ) {
					continue;
				}
				$tpls[] = array( 'name' => $name, 'text' => $text );
			}
			update_option( AUN_SP_Messages::OPT_REJECT_TPL, $tpls );
			$notice = $this->notice( 'Messages saved.', 'success' );
		}

		$received = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_RECEIVED );
		$status   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_STATUS );
		$partsu   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PARTS );
		$reject   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REJECT );
		$photo    = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PHOTO );
		$quote    = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_QUOTE );
		$approved = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_APPROVED );
		$smspay   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PAY );
		$smspaid  = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PAID );
		$smsref   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REFUND );
		$smsrem   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REMIND );
		$smsrem2  = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REMIND2 );
		$smsexp   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_EXPIRED );
		$smsdec   = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_DECLINED );
		$pay      = AUN_SP_Messages::pay_info();
		$tpls     = AUN_SP_Messages::reject_templates();

		echo '<div class="wrap"><h1>Messages</h1>';
		echo '<p style="color:#646970;max-width:740px;">Reword any customer message. Placeholders are filled in automatically: <code>{ref}</code> <code>{model}</code> <code>{status}</code> <code>{changes}</code> <code>{track}</code> <code>{reason}</code> <code>{coupon}</code> <code>{parts}</code> <code>{date}</code> <code>{age}</code> <code>{detail}</code> <code>{expires}</code>. You can write these in Bangla if you prefer.</p>';
		echo $notice;
		echo '<form method="post">';
		wp_nonce_field( 'aun_sp_messages', 'aun_sp_messages_nonce' );

		echo '<h2>Customer SMS</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Request received</th><td><textarea name="sms_received" rows="2" class="large-text">' . esc_textarea( $received ) . '</textarea><p class="description">Sent automatically the moment a customer submits a request &mdash; gives them their reference number + direct link.</p></td></tr>';
		echo '<tr><th>Status update</th><td><textarea name="sms_status" rows="2" class="large-text">' . esc_textarea( $status ) . '</textarea><p class="description">Sent when you tick &ldquo;Text the customer&rdquo; and the request as a whole moves <em>without</em> any individual part changing. When a part <em>does</em> move, the Parts update below is sent instead &mdash; it names the part, which is what the customer actually wants to know.</p></td></tr>';
		echo '<tr><th>Parts update</th><td><textarea name="sms_parts" rows="2" class="large-text">' . esc_textarea( $partsu ) . '</textarea><p class="description">Sent whenever you save a change to any part&rsquo;s status &mdash; this is the everyday update. <code>{changes}</code> lists each changed part and its new stage (e.g. <em>LCD screen: Shipped from the factory &mdash; on its way to Bangladesh</em>). <code>{detail}</code> is the same list with the full explanation of each stage &mdash; clearer, but several times longer, so it costs more SMS parts.</p></td></tr>';
		echo '<tr><th>Rejection</th><td><textarea name="sms_reject" rows="2" class="large-text">' . esc_textarea( $reject ) . '</textarea><p class="description"><code>{coupon}</code> becomes your goodwill line when a coupon code is set in Settings.</p></td></tr>';
		echo '<tr><th>Better-photo request</th><td><textarea name="sms_photo" rows="2" class="large-text">' . esc_textarea( $photo ) . '</textarea><p class="description">Sent when you click &ldquo;Ask customer for a better photo&rdquo; on a request.</p></td></tr>';
		echo '<tr><th>Quote ready</th><td><textarea name="sms_quote" rows="2" class="large-text">' . esc_textarea( $quote ) . '</textarea><p class="description">Sent when you click &ldquo;Send quote for approval&rdquo;. <code>{total}</code> is the quoted amount.</p></td></tr>';
		// The chase ladder. Every one of these says the sentence customers miss:
		// asking for the part is not the same as ordering it.
		$qd = AUN_SP_Requests::quote_valid_days();
		list( $qr1, $qr2 ) = AUN_SP_Requests::reminder_days();
		echo '<tr><th>Quote reminder</th><td><textarea name="sms_remind" rows="2" class="large-text">' . esc_textarea( $smsrem ) . '</textarea><p class="description">Sent automatically on <strong>day ' . (int) $qr1 . '</strong> if the customer has not answered the quote. <code>{expires}</code> is the date it lapses.</p></td></tr>';
		echo '<tr><th>Final quote reminder</th><td><textarea name="sms_remind_final" rows="2" class="large-text">' . esc_textarea( $smsrem2 ) . '</textarea><p class="description">Sent on <strong>day ' . (int) $qr2 . '</strong> — the last nudge before the quote expires. Chasing beyond three messages stops working and costs money.</p></td></tr>';
		echo '<tr><th>Quote expired</th><td><textarea name="sms_expired" rows="2" class="large-text">' . esc_textarea( $smsexp ) . '</textarea><p class="description">Sent when the quote lapses' . ( $qd > 0 ? ' (day ' . (int) $qd . ')' : '' ) . '. Say clearly that nothing was ordered and that they can ask for a new quote &mdash; their tracking page shows an &ldquo;I still want this part&rdquo; button.</p></td></tr>';
		echo '<tr><th>Quote declined</th><td><textarea name="sms_declined" rows="2" class="large-text">' . esc_textarea( $smsdec ) . '</textarea><p class="description">Sent when the customer declines. Declining used to send <em>nothing</em>, so a customer who tapped Decline by mistake had no record it happened and no way back &mdash; this is both the receipt and the invitation to ask for a new quote.</p></td></tr>';
		echo '<tr><th>Quote approved</th><td><textarea name="sms_approved" rows="2" class="large-text">' . esc_textarea( $approved ) . '</textarea><p class="description">Sent to the customer when they approve the quote. <code>{pay}</code> inserts your payment instructions below.</p></td></tr>';
		echo '<tr><th>Online payment link</th><td><textarea name="sms_pay" rows="2" class="large-text">' . esc_textarea( $smspay ) . '</textarea><p class="description">Sent when you press <strong>Send online payment link</strong> on a request. <code>{link}</code> is the payment page, <code>{total}</code> the amount. (Approving a quote does <em>not</em> send this &mdash; cash on delivery is the default.)</p></td></tr>';
		echo '<tr><th>Payment received</th><td><textarea name="sms_paid" rows="2" class="large-text">' . esc_textarea( $smspaid ) . '</textarea><p class="description">Sent by <strong>this plugin</strong> the moment an online payment succeeds, and written to the request&rsquo;s activity log. WooCommerce&rsquo;s own emails/SMS are not used.</p></td></tr>';
		echo '<tr><th>Refund issued</th><td><textarea name="sms_refund" rows="2" class="large-text">' . esc_textarea( $smsref ) . '</textarea><p class="description">Sent when you record a manual refund on a paid request that was rejected or declined. <code>{total}</code> is the amount refunded.</p></td></tr>';
		echo '<tr><th>Payment instructions</th><td><textarea name="pay_info" rows="2" class="large-text">' . esc_textarea( $pay ) . '</textarea><p class="description">Fallback for when WooCommerce is unavailable &mdash; shown with the quote and via <code>{pay}</code>, e.g. &ldquo;Pay 50% advance to bKash 017&hellip; to confirm.&rdquo; With WooCommerce active the Pay button replaces this.</p></td></tr>';
		echo '</tbody></table>';

		echo '<h2 style="margin-top:24px;">Reject reason templates</h2>';
		echo '<p style="color:#646970;">These fill the reject dropdown on a request. Clear a name (or tick Delete) to remove a row; use the last row to add one.</p>';
		echo '<input type="hidden" name="r_count" value="' . count( $tpls ) . '">';
		echo '<table class="wp-list-table widefat striped"><thead><tr><th style="width:230px;">Name</th><th>Message</th><th style="width:55px;">Delete</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $tpls as $tpl ) {
			$this->reject_tpl_row( $i, isset( $tpl['name'] ) ? $tpl['name'] : '', isset( $tpl['text'] ) ? $tpl['text'] : '', false );
			$i++;
		}
		$this->reject_tpl_row( $i, '', '', true );
		echo '</tbody></table>';

		echo '<p style="margin-top:14px;"><button class="button button-primary">Save messages</button> <button class="button" name="restore_defaults" value="1" onclick="return confirm(\'Restore all default message wording? Your payment instructions are kept.\');">Restore default wording</button></p>';
		echo '</form></div>';
	}

	private function reject_tpl_row( $i, $name, $text, $is_new ) {
		echo '<tr>';
		echo '<td><input type="text" name="r_name_' . (int) $i . '" value="' . esc_attr( $name ) . '" style="width:100%;" ' . ( $is_new ? 'placeholder="New reason…"' : '' ) . '></td>';
		echo '<td><textarea name="r_text_' . (int) $i . '" rows="2" style="width:100%;">' . esc_textarea( $text ) . '</textarea></td>';
		echo '<td style="text-align:center;">' . ( $is_new ? '' : '<input type="checkbox" name="r_del_' . (int) $i . '" value="1">' ) . '</td>';
		echo '</tr>';
	}

	/* --------------------------------------------------------------- Translations */

	public function page_translations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = '';
		if ( isset( $_POST['aun_sp_i18n_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_i18n_nonce'], 'aun_sp_i18n' ) ) {
			if ( isset( $_POST['restore_defaults'] ) ) {
				delete_option( AUN_SP_I18N::OPTION );
				$notice = $this->notice( 'Default translations restored.', 'success' );
			} else {
				// Store only what differs from the built-in default, so untouched strings
				// keep tracking future default wording and the option stays small.
				$store = array();
				foreach ( AUN_SP_I18N::all_slugs() as $slug ) {
					$def = AUN_SP_I18N::def( $slug );
					$en  = sanitize_text_field( wp_unslash( $_POST[ 'i18n_' . $slug . '_en' ] ?? '' ) );
					$bn  = sanitize_text_field( wp_unslash( $_POST[ 'i18n_' . $slug . '_bn' ] ?? '' ) );
					if ( $en !== $def['en'] || $bn !== $def['bn'] ) {
						$store[ $slug ] = array( 'en' => $en, 'bn' => $bn );
					}
				}
				if ( empty( $store ) ) {
					delete_option( AUN_SP_I18N::OPTION );
				} else {
					update_option( AUN_SP_I18N::OPTION, $store );
				}
				$notice = $this->notice( 'Translations saved.', 'success' );
			}
		}

		$detected = AUN_SP_I18N::current_lang();
		echo '<div class="wrap"><h1>Translations</h1>';
		echo '<p style="color:#646970;max-width:760px;">Every word the customer sees on the request form and tracking page — in English and বাংলা. Edit either column; leave a Bangla box empty to fall back to the English. Part names live in <strong>Parts catalogue</strong> and text-message wording in <strong>Messages</strong>.</p>';
		echo '<div class="notice notice-info inline" style="max-width:760px;margin:12px 0;"><p style="margin:.5em 0;"><strong>The language is now automatic.</strong> The old বাংলা / English buttons have been removed: the form and tracker follow whatever language the visitor is browsing in (TranslatePress — e.g. the <code>/bn/</code> URLs — or the WordPress site language). A visitor on a Bangla page sees Bangla, including error messages sent from the server.'
			. ' <span style="color:#646970;">Right now this admin screen resolves to: <code>' . esc_html( $detected ) . '</code>.</span></p></div>';
		echo $notice;

		echo '<form method="post">';
		wp_nonce_field( 'aun_sp_i18n', 'aun_sp_i18n_nonce' );

		foreach ( AUN_SP_I18N::groups() as $group ) {
			echo '<h2 style="margin-top:26px;">' . esc_html( $group['label'] ) . '</h2>';
			echo '<table class="wp-list-table widefat striped" style="max-width:900px;"><thead><tr>';
			echo '<th style="width:50%;">English</th><th style="width:50%;">বাংলা (Bangla)</th>';
			echo '</tr></thead><tbody>';
			foreach ( $group['strings'] as $slug => $def ) {
				$c = AUN_SP_I18N::raw( $slug );
				echo '<tr>';
				echo '<td><input type="text" name="i18n_' . esc_attr( $slug ) . '_en" value="' . esc_attr( $c['en'] ) . '" style="width:100%;"></td>';
				echo '<td><input type="text" name="i18n_' . esc_attr( $slug ) . '_bn" value="' . esc_attr( $c['bn'] ) . '" style="width:100%;" lang="bn"></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<p style="margin-top:18px;"><button class="button button-primary">Save translations</button> ';
		echo '<button class="button" name="restore_defaults" value="1" onclick="return confirm(\'Restore all translations to the built-in defaults?\');">Restore defaults</button></p>';
		echo '</form></div>';
	}

	/* ------------------------------------------------------------------- Settings */

	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = '';
		if ( isset( $_POST['aun_sp_settings_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_settings_nonce'], 'aun_sp_settings' ) ) {
			update_option( 'aun_sp_warranty_months', max( 0, (int) ( $_POST['warranty_months'] ?? 12 ) ) );
			update_option( 'aun_sp_warranty_grace_days', max( 0, (int) ( $_POST['grace_days'] ?? 4 ) ) );
			update_option( 'aun_sp_alert_email', sanitize_email( wp_unslash( $_POST['alert_email'] ?? '' ) ) );
			update_option( 'aun_sp_hard_source_years', max( 0, (int) ( $_POST['hard_source_years'] ?? 3 ) ) );
			update_option( 'aun_sp_tracking_url', esc_url_raw( wp_unslash( $_POST['tracking_url'] ?? '' ) ) );
			update_option( 'aun_sp_service_url', esc_url_raw( wp_unslash( $_POST['service_url'] ?? '' ) ) );
			update_option( 'aun_sp_goodwill_coupon', sanitize_text_field( wp_unslash( $_POST['goodwill_coupon'] ?? '' ) ) );
			// Turning expiry ON must not retroactively lapse quotes that were sent
			// under "no deadline" terms — that would expire a pile of live quotes (and
			// text every one of those customers) the very next morning. They get the
			// new window counted from today instead.
			$was_days = (int) get_option( 'aun_sp_quote_valid_days', 7 );
			$new_days = max( 0, (int) ( $_POST['quote_valid_days'] ?? 7 ) );
			update_option( 'aun_sp_quote_valid_days', $new_days );
			$notice = $this->notice( 'Settings saved.', 'success' );
			if ( $new_days > 0 && $was_days !== $new_days ) {
				$dated = AUN_SP_Install::date_open_quotes( $new_days );
				if ( $dated ) {
					$notice .= $this->notice( $dated . ' quote(s) already awaiting a reply were given a new deadline ' . $new_days . ' day(s) from today, rather than being expired retroactively.', 'info' );
				}
			}
		}

		$months = (int) get_option( 'aun_sp_warranty_months', 12 );
		$grace  = (int) get_option( 'aun_sp_warranty_grace_days', 4 );
		$email  = (string) get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		$hard   = (int) get_option( 'aun_sp_hard_source_years', 3 );
		$track   = (string) get_option( 'aun_sp_tracking_url', '' );
		$service = (string) get_option( 'aun_sp_service_url', '' );
		$coupon  = (string) get_option( 'aun_sp_goodwill_coupon', '' );
		$has_key = defined( 'AUN_SP_ERP_API_KEY' ) && AUN_SP_ERP_API_KEY !== '';

		echo '<div class="wrap"><h1>Settings</h1>';
		echo $notice;
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;max-width:640px;">';
		wp_nonce_field( 'aun_sp_settings', 'aun_sp_settings_nonce' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Warranty period (months)</th><td><input type="number" name="warranty_months" value="' . esc_attr( $months ) . '" min="0" class="small-text"></td></tr>';
		echo '<tr><th>Grace period (days)</th><td><input type="number" name="grace_days" value="' . esc_attr( $grace ) . '" min="0" class="small-text"> <span style="color:#646970;">covers online-delivery time</span></td></tr>';
		echo '<tr><th>Alert email</th><td><input type="email" name="alert_email" value="' . esc_attr( $email ) . '" class="regular-text"></td></tr>';
		echo '<tr><th>Hard-to-source after (years)</th><td><input type="number" name="hard_source_years" value="' . esc_attr( $hard ) . '" min="0" class="small-text"> <span style="color:#646970;">flags older devices on a request</span></td></tr>';
		echo '<tr><th>Tracking page URL</th><td><input type="url" name="tracking_url" value="' . esc_attr( $track ) . '" class="regular-text" placeholder="https://aun-projector.com.bd/spare-parts-status/"> <span style="color:#646970;">included in customer status SMS</span></td></tr>';
		echo '<tr><th>Send-projector page URL</th><td><input type="url" name="service_url" value="' . esc_attr( $service ) . '" class="regular-text" placeholder="https://aun-projector.com.bd/send-projector/"> <span style="color:#646970;">where the &ldquo;Send my projector&rdquo; choice links (defaults to /send-projector/)</span></td></tr>';
		echo '<tr><th>Goodwill coupon code</th><td><input type="text" name="goodwill_coupon" value="' . esc_attr( $coupon ) . '" class="regular-text" placeholder="e.g. UPGRADE10"> <span style="color:#646970;">added to rejection SMS as an apology</span></td></tr>';
		$qdays = AUN_SP_Requests::quote_valid_days();
		list( $qr1, $qr2 ) = AUN_SP_Requests::reminder_days();
		echo '<tr><th>Quote valid for (days)</th><td><input type="number" name="quote_valid_days" value="' . esc_attr( $qdays ) . '" min="0" max="365" class="small-text"> ';
		if ( $qdays > 0 ) {
			echo '<span style="color:#646970;">the customer is reminded on day <strong>' . (int) $qr1 . '</strong> and day <strong>' . (int) $qr2
				. '</strong>, then the quote expires on day <strong>' . (int) $qdays . '</strong>.</span>';
		} else {
			echo '<span style="color:#646970;">0 = quotes never expire; the customer is still reminded on day ' . (int) $qr1 . ' and day ' . (int) $qr2 . '.</span>';
		}
		echo '<p class="description" style="margin:6px 0 0;">An expired quote is <strong>not</strong> a rejection: it means the customer never answered. They keep a &ldquo;I still want this part&rdquo; button on their tracking page, which puts the request back in front of you for a fresh price.</p></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">Save settings</button></p>';
		echo '</form>';

		// Payment bridge self-check: says plainly whether an approved quote can turn
		// into a payable order, and which gateways the customer would be offered.
		global $wpdb;
		$t_req    = AUN_SP_Install::table( 'requests' );
		$has_col  = false;
		foreach ( (array) $wpdb->get_results( "DESCRIBE $t_req" ) as $c ) {
			if ( 'wc_order_id' === $c->Field ) { $has_col = true; break; }
		}
		$gateways = array();
		if ( AUN_SP_Woo::is_active() && function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gw ) {
				$gateways[] = $gw->get_title();
			}
		}
		$orders_made = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_req WHERE wc_order_id > 0" );
		$ok          = AUN_SP_Woo::is_active() && $has_col && ! empty( $gateways );

		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-warning' ) . ' inline" style="max-width:640px;margin-top:18px;"><p style="margin:.6em 0;">';
		echo '<strong>Payment bridge:</strong> ' . ( $ok ? 'ready' : 'not ready' ) . '<br>';
		echo 'WooCommerce detected: <strong>' . ( AUN_SP_Woo::is_active() ? 'yes' : 'NO' ) . '</strong><br>';
		echo 'Database ready (<code>wc_order_id</code>): <strong>' . ( $has_col ? 'yes' : 'NO — deactivate and reactivate the plugin' ) . '</strong><br>';
		echo 'Payment methods a customer would see: <strong>' . ( $gateways ? esc_html( implode( ', ', $gateways ) ) : 'NONE — enable SSLCommerz / Cash on delivery in WooCommerce → Settings → Payments' ) . '</strong><br>';
		echo 'Orders created so far: <strong>' . $orders_made . '</strong>';
		echo '</p></div>';

		echo '<p style="margin-top:14px;color:#646970;max-width:640px;">ERP live-lookup key (<code>AUN_SP_ERP_API_KEY</code> in wp-config.php): <strong>' . ( $has_key ? 'configured' : 'not set' ) . '</strong>. Without it, lookups use the legacy archive only — fine until the <code>/api/sales-lookup</code> endpoint is deployed.</p>';
		echo '</div>';
	}

	/* --------------------------------------------------------------------- helpers */

	private function notice( $msg, $type = 'success' ) {
		$class = $type === 'error' ? 'notice-error' : 'notice-success';
		return '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>';
	}
}
