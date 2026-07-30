<?php
/**
 * Toolbar (admin-bar) badge for AUN Spare Parts.
 *
 * Puts a "Spare Parts" item in the top WordPress toolbar — on both the front end and
 * wp-admin — with a red count bubble for requests that need action (new to review,
 * approved-to-order, ready-to-dispatch). It's here so the store owner notices pending
 * work without having to remember to open the Spare Parts screen. Sub-items jump
 * straight to the relevant filtered view.
 *
 * Loaded unconditionally (the toolbar shows on the front end, where is_admin() is
 * false); every node is gated on manage_options so only staff ever see it.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Admin_Bar {

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_node' ), 80 );
		add_action( 'wp_head', array( __CLASS__, 'styles' ) );
		add_action( 'admin_head', array( __CLASS__, 'styles' ) );
	}

	public static function add_node( $bar ) {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		$c    = AUN_SP_Requests::nav_counts();
		$base = admin_url( 'admin.php?page=aun-sp' );

		// Title: label + a bubble when something needs action (styled like core's
		// update/comment bubbles). Inline styles = no external CSS to cache-bust.
		$bubble = $c['action'] > 0
			? ' <span class="aun-sp-ab-bubble">' . (int) $c['action'] . '</span>'
			: '';
		$title = '<span class="ab-icon dashicons dashicons-screenoptions" style="top:2px;"></span>'
			. '<span class="ab-label">Spare Parts</span>' . $bubble;

		$bar->add_node( array(
			'id'    => 'aun-sp',
			'title' => $title,
			'href'  => $base,
			'meta'  => array( 'title' => $c['open'] . ' open request' . ( 1 === $c['open'] ? '' : 's' ) ),
		) );

		// Sub-items — each a filtered dashboard view. Show a count next to each.
		$sub = array(
			array( 'new',      'New to review',        $c['submitted'], $base . '&status=submitted' ),
			array( 'approved', 'Approved — to order',  $c['approved'],  $base . '&status=approved' ),
			array( 'ready',    'Ready to dispatch',    $c['ready'],     $base . '&status=ready' ),
			array( 'quote',    'Quotes awaiting reply', $c['quote'],    $base . '&status=quote_sent' ),
			array( 'waiting',  'Waiting on customer',  $c['waiting'],   $base . '&status=waiting_customer' ),
		);
		foreach ( $sub as $s ) {
			list( $slug, $label, $n, $href ) = $s;
			$bar->add_node( array(
				'parent' => 'aun-sp',
				'id'     => 'aun-sp-' . $slug,
				'title'  => $label . ' <span class="aun-sp-ab-n">' . (int) $n . '</span>',
				'href'   => $href,
			) );
		}

		$bar->add_node( array(
			'parent' => 'aun-sp',
			'id'     => 'aun-sp-all',
			'title'  => 'All requests (' . (int) $c['open'] . ' open)',
			'href'   => $base,
		) );
	}

	/** Tiny styling for the bubble + sub-item counts. Guarded so WP Rocket leaves it. */
	public static function styles() {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}
		echo '<style id="aun-sp-ab-css" data-no-optimize="1" data-no-minify="1">'
			. '#wpadminbar .aun-sp-ab-bubble{display:inline-block;min-width:16px;height:16px;margin:0 0 0 4px;padding:0 5px;border-radius:8px;background:#d63638;color:#fff;font-size:11px;line-height:16px;text-align:center;font-weight:600;vertical-align:1px;}'
			. '#wpadminbar .aun-sp-ab-n{display:inline-block;min-width:16px;padding:0 5px;margin-left:4px;border-radius:8px;background:rgba(255,255,255,0.18);color:#fff;font-size:11px;line-height:16px;text-align:center;}'
			. '#wpadminbar #wp-admin-bar-aun-sp .ab-icon:before{color:#f0f0f1;}'
			. '</style>';
	}
}
