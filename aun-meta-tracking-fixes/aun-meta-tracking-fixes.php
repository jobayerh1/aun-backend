<?php
/**
 * Plugin Name:       AUN Meta Tracking Fixes
 * Plugin URI:        https://aun-projector.com.bd/
 * Description:       Fixes premature Purchase events, bot traffic, and staff test orders in the Meta for WooCommerce plugin. Each fix can be toggled independently.
 * Version:           1.1.0
 * Author:            Smart Living Bangladesh
 * License:           GPL-2.0-or-later
 * Requires PHP:      7.4
 *
 * Why this exists
 * ---------------
 * Meta for WooCommerce registers its Purchase handler on FOUR hooks:
 *
 *   woocommerce_new_order                  @10  (server / CAPI)  <- order CREATION, before payment
 *   woocommerce_process_shop_order_meta    @20  (server / CAPI)  <- admin saves an order
 *   woocommerce_checkout_update_order_meta @30  (server / CAPI)  <- checkout, before payment result
 *   woocommerce_thankyou                   @40  (browser pixel)  <- order-received page
 *
 * As of 3.7.x the plugin DOES deduplicate browser vs server: it stores one
 * shared event_id on the order and a per-context meta flag, so Meta merges
 * the pixel and CAPI hits. Double-counting in Events Manager is therefore no
 * longer the problem it was in earlier versions.
 *
 * What is still wrong is the TIMING of the server event. It fires the moment
 * the order row is created — before payment is confirmed — so failed
 * payments, abandoned checkouts and refused COD orders all report a Purchase.
 * On this store the CAPI hit also lands during ?wc-ajax=update_order_review,
 * where the line items are not populated yet, so it ships empty content_ids /
 * contents and loses catalog attribution.
 *
 * Fix 1 therefore DEFERS the server Purchase to payment confirmation rather
 * than deleting it — deleting it would throw away the CAPI half of the
 * signal, which is the half that survives ad blockers and ITP.
 *
 * Separately, cache preloaders and SEO crawlers trigger pixel events on every
 * page they touch, flooding the dataset with fake traffic. This plugin also
 * excludes those, plus logged-in staff.
 *
 * Verified against Meta for WooCommerce 3.7.6.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AUN_Meta_Tracking_Fixes {

	const VERSION      = '1.2.0';
	const OPTION_KEY   = 'aun_meta_fixes_settings';
	const LAST_RUN_KEY = 'aun_meta_fixes_last_run';
	const MENU_SLUG    = 'aun-meta-fixes';

	/** The Meta plugin method we are relocating. */
	const TRACKER_METHOD = 'inject_purchase_event';

	/** @var AUN_Meta_Tracking_Fixes|null */
	private static $instance = null;

	/** @var WC_Facebookcommerce_EventsTracker|null Cached once found. */
	private $tracker = null;

	/** @var array Diagnostics for the settings screen. */
	private $report = array(
		'ran'      => false,
		'removed'  => array(),
		'readded'  => array(),
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( __FILE__ ),
			array( $this, 'add_settings_link' )
		);

		// The events tracker is constructed by the integration, which WooCommerce
		// loads during init. wp_loaded is the first hook guaranteed to run after
		// every integration constructor has finished registering its hooks.
		add_action( 'wp_loaded', array( $this, 'fix_purchase_event_timing' ), 5 );

		// Fixes 2 and 3 both filter the same flag; separate callbacks so
		// each can be toggled independently.
		add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', array( $this, 'fix_block_bots' ), 20 );
		add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', array( $this, 'fix_exclude_staff' ), 21 );
	}

	/* =============================================================
	 * Hook maps
	 * ============================================================= */

	/**
	 * Hooks where the Meta plugin fires Purchase BEFORE payment is confirmed.
	 * These are the ones Fix 1 detaches.
	 *
	 * woocommerce_thankyou (@40, the browser pixel) is deliberately absent —
	 * it fires after checkout completes and is the event we keep.
	 */
	public static function premature_hooks() {
		return array(
			'woocommerce_new_order',
			'woocommerce_process_shop_order_meta',
			'woocommerce_checkout_update_order_meta',
		);
	}

	/**
	 * Hooks that mean "money is actually confirmed". Fix 1 re-attaches the
	 * Meta handler here in defer mode.
	 *
	 * - woocommerce_payment_complete       gateway confirmed the transaction
	 * - woocommerce_order_status_processing paid, or COD accepted
	 * - woocommerce_order_status_completed  covers orders that skip processing
	 */
	public static function confirmed_hooks() {
		return array(
			'woocommerce_payment_complete',
			'woocommerce_order_status_processing',
			'woocommerce_order_status_completed',
		);
	}

	/** Every hook the tracker's Purchase handler may sit on, for diagnostics. */
	public static function all_purchase_hooks() {
		return array_merge( self::premature_hooks(), array( 'woocommerce_thankyou' ), self::confirmed_hooks() );
	}

	/* =============================================================
	 * Settings
	 * ============================================================= */

	public function defaults() {
		return array(
			'fix_duplicate_purchase' => 1,
			'purchase_mode'          => 'defer',
			'fix_block_bots'         => 1,
			'fix_exclude_staff'      => 1,
			'bot_patterns'           => $this->default_bot_patterns(),
		);
	}

	public function default_bot_patterns() {
		return implode(
			"\n",
			array(
				'wp rocket',
				'preload',
				'bot',
				'crawler',
				'spider',
				'facebookexternalhit',
				'meta-externalads',
				'headlesschrome',
				'python-requests',
				'curl/',
				'wget',
				'lighthouse',
				'pagespeed',
				'gtmetrix',
				'pingdom',
				'uptimerobot',
			)
		);
	}

	public function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->defaults() );
	}

	public function is_enabled( $key ) {
		$settings = $this->get_settings();
		return ! empty( $settings[ $key ] );
	}

	public function register_settings() {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['fix_duplicate_purchase'] = empty( $input['fix_duplicate_purchase'] ) ? 0 : 1;
		$clean['fix_block_bots']         = empty( $input['fix_block_bots'] ) ? 0 : 1;
		$clean['fix_exclude_staff']      = empty( $input['fix_exclude_staff'] ) ? 0 : 1;

		$mode                   = isset( $input['purchase_mode'] ) ? (string) $input['purchase_mode'] : 'defer';
		$clean['purchase_mode'] = in_array( $mode, array( 'defer', 'remove' ), true ) ? $mode : 'defer';

		$patterns = isset( $input['bot_patterns'] ) ? (string) $input['bot_patterns'] : '';
		$lines    = array_filter( array_map( 'trim', explode( "\n", $patterns ) ) );

		if ( empty( $lines ) ) {
			$lines = array_filter( array_map( 'trim', explode( "\n", $this->default_bot_patterns() ) ) );
		}

		$clean['bot_patterns'] = implode( "\n", array_map( 'sanitize_text_field', $lines ) );

		return $clean;
	}

	/* =============================================================
	 * Locating the Meta events tracker
	 * ============================================================= */

	/**
	 * Find the live WC_Facebookcommerce_EventsTracker instance.
	 *
	 * We deliberately do NOT read $integration->events_tracker. That property
	 * is declared private; the integration exposes it through __get() but
	 * defines no __isset(), so isset( $integration->events_tracker ) is always
	 * false from outside the class — that is what made the old status check
	 * report "unavailable". Reading it through __get() would work but emits a
	 * _doing_it_wrong() notice and is explicitly deprecated by Meta.
	 *
	 * Instead we find the object through the hooks it registered on itself.
	 * That needs no property access, raises no notice, and keeps working if
	 * Meta moves where the object is stored.
	 *
	 * @return WC_Facebookcommerce_EventsTracker|null
	 */
	public function get_events_tracker() {

		if ( null !== $this->tracker ) {
			return $this->tracker;
		}

		if ( ! class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) {
			return null;
		}

		global $wp_filter;

		foreach ( self::all_purchase_hooks() as $hook ) {

			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {

					if ( ! is_array( $callback['function'] ) || ! isset( $callback['function'][0], $callback['function'][1] ) ) {
						continue;
					}

					if (
						is_object( $callback['function'][0] )
						&& $callback['function'][0] instanceof WC_Facebookcommerce_EventsTracker
						&& self::TRACKER_METHOD === $callback['function'][1]
					) {
						// Cache only on success — an early miss must not be sticky.
						$this->tracker = $callback['function'][0];
						return $this->tracker;
					}
				}
			}
		}

		return null;
	}

	/* =============================================================
	 * Fix 1 — stop Purchase firing before payment is confirmed
	 * ============================================================= */

	public function fix_purchase_event_timing() {

		if ( ! $this->is_enabled( 'fix_duplicate_purchase' ) ) {
			return;
		}

		$tracker = $this->get_events_tracker();

		if ( ! $tracker ) {
			// Distinguish "Meta deliberately switched the pixel off for this
			// visitor" (staff, or a blocked bot — the tracker constructor bails
			// before registering any hooks) from a genuine failure.
			$this->record_run(
				(bool) apply_filters( 'facebook_for_woocommerce_integration_pixel_enabled', true )
					? 'tracker_missing'
					: 'pixel_off_for_visitor'
			);
			return;
		}

		$this->report['ran'] = true;

		$settings = $this->get_settings();
		$callback = array( $tracker, self::TRACKER_METHOD );

		global $wp_filter;

		// 1. Detach the handler from every pre-payment hook. Priorities are
		//    undocumented and have changed between releases, so discover them
		//    rather than hardcoding 10/20/30.
		foreach ( self::premature_hooks() as $hook ) {

			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
				continue;
			}

			// Copy the priority list first — remove_action() mutates it.
			$priorities = array_keys( $wp_filter[ $hook ]->callbacks );

			foreach ( $priorities as $priority ) {
				if ( remove_action( $hook, $callback, $priority ) ) {
					$this->report['removed'][] = $hook . ' @' . $priority;
				}
			}
		}

		// 2. In defer mode, re-attach it where payment is actually confirmed,
		//    so the CAPI half of the signal is kept — just later, and only for
		//    orders that really completed.
		if ( 'remove' === $settings['purchase_mode'] ) {
			return;
		}

		foreach ( self::confirmed_hooks() as $hook ) {
			if ( ! has_action( $hook, $callback ) ) {
				add_action( $hook, $callback, 10, 1 );
				$this->report['readded'][] = $hook . ' @10';
			}
		}

		$this->record_run( 'applied' );
	}

	/**
	 * Remember what Fix 1 did, so the settings screen can show evidence from a
	 * real front-end request.
	 *
	 * This matters because an admin viewing the settings page can never observe
	 * Fix 1 directly: with Fix 3 on, the pixel is disabled for staff, the Meta
	 * tracker's constructor bails before registering hooks, and there is
	 * therefore nothing on the page to inspect.
	 *
	 * Only writes when the outcome actually changes, so this is not a database
	 * write on every page load.
	 *
	 * @param string $status applied | pixel_off_for_visitor | tracker_missing
	 */
	private function record_run( $status ) {

		$settings = $this->get_settings();

		$entry = array(
			'status'  => $status,
			'mode'    => $settings['purchase_mode'],
			'removed' => $this->report['removed'],
			'readded' => $this->report['readded'],
			'admin'   => is_admin(),
		);

		$signature = md5( wp_json_encode( $entry ) );
		$stored    = get_option( self::LAST_RUN_KEY );

		if ( is_array( $stored ) && isset( $stored['signature'] ) && $stored['signature'] === $signature ) {
			return;
		}

		$entry['signature'] = $signature;
		$entry['time']      = time();

		update_option( self::LAST_RUN_KEY, $entry, false );
	}

	/* =============================================================
	 * Fix 2 — block bot traffic from firing pixel + CAPI
	 * ============================================================= */

	public function fix_block_bots( $enabled ) {

		if ( ! $this->is_enabled( 'fix_block_bots' ) ) {
			return $enabled;
		}

		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// No user agent is itself a strong bot signal.
			return false;
		}

		$agent    = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
		$settings = $this->get_settings();
		$patterns = array_filter( array_map( 'trim', explode( "\n", $settings['bot_patterns'] ) ) );

		foreach ( $patterns as $needle ) {
			if ( '' !== $needle && false !== strpos( $agent, strtolower( $needle ) ) ) {
				return false;
			}
		}

		return $enabled;
	}

	/* =============================================================
	 * Fix 3 — exclude logged-in staff
	 * ============================================================= */

	public function fix_exclude_staff( $enabled ) {

		if ( ! $this->is_enabled( 'fix_exclude_staff' ) ) {
			return $enabled;
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		return $enabled;
	}

	/* =============================================================
	 * Admin UI
	 * ============================================================= */

	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::MENU_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'aun-meta-fixes' ) . '</a>' );
		return $links;
	}

	public function add_settings_page() {
		add_options_page(
			'AUN Meta Tracking Fixes',
			'Meta Tracking Fixes',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/** Renders a list of hook names as comma-separated <code> spans, each escaped. */
	private static function code_list( $items ) {
		$items = array_map(
			static function ( $item ) {
				return '<code>' . esc_html( $item ) . '</code>';
			},
			(array) $items
		);
		return implode( ', ', $items );
	}

	/** Version of the Meta plugin, or '' if it cannot be read. */
	private function meta_plugin_version() {
		if ( defined( 'WC_Facebookcommerce_Loader::PLUGIN_VERSION' ) ) {
			return (string) constant( 'WC_Facebookcommerce_Loader::PLUGIN_VERSION' );
		}
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			$plugin = facebook_for_woocommerce();
			if ( is_object( $plugin ) && method_exists( $plugin, 'get_version' ) ) {
				return (string) $plugin->get_version();
			}
		}
		return '';
	}

	/**
	 * Live map of which Purchase hooks the tracker is currently attached to.
	 *
	 * @return array hook => priority|false
	 */
	private function live_hook_map() {

		$map     = array();
		$tracker = $this->get_events_tracker();

		if ( ! $tracker ) {
			return $map;
		}

		$callback = array( $tracker, self::TRACKER_METHOD );

		foreach ( self::all_purchase_hooks() as $hook ) {
			$map[ $hook ] = has_action( $hook, $callback );
		}

		return $map;
	}

	public function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = $this->get_settings();
		$meta_active = function_exists( 'facebook_for_woocommerce' );
		$version     = $this->meta_plugin_version();
		$tracker_ok  = (bool) $this->get_events_tracker();
		$hook_map    = $this->live_hook_map();

		// Fix 3 disables the pixel for staff. Meta's tracker constructor bails
		// out when the pixel is disabled, so on this screen it registers no
		// hooks and is undetectable — expected, not a fault.
		$staff_excluded = ! $tracker_ok
			&& $this->is_enabled( 'fix_exclude_staff' )
			&& false === $this->fix_exclude_staff( true );
		?>
		<style data-no-optimize="1" data-no-minify="1" data-cfasync="false">
			.aun-mtf-panel { margin:16px 0; padding:12px 16px; background:#fff; border-left:4px solid #2271b1; box-shadow:0 1px 1px rgba(0,0,0,.04); }
			.aun-mtf-panel table { border-collapse:collapse; margin-top:8px; }
			.aun-mtf-panel td { padding:2px 18px 2px 0; font-size:13px; }
			.aun-mtf-ok { color:#008a20; font-weight:600; }
			.aun-mtf-bad { color:#d63638; font-weight:600; }
			.aun-mtf-muted { color:#646970; }
		</style>

		<div class="wrap">
			<h1>AUN Meta Tracking Fixes</h1>

			<div class="aun-mtf-panel">
				<p style="margin:0;"><strong>Status</strong></p>
				<p style="margin:6px 0 0;">
					Meta for WooCommerce:
					<?php if ( $meta_active ) : ?>
						<span class="aun-mtf-ok">active<?php echo $version ? ' (' . esc_html( $version ) . ')' : ''; ?></span>
					<?php else : ?>
						<span class="aun-mtf-bad">not detected — these fixes will do nothing</span>
					<?php endif; ?>
					<br>
					Events tracker:
					<?php if ( $tracker_ok ) : ?>
						<span class="aun-mtf-ok">found</span>
						<span class="aun-mtf-muted">— located via its registered hooks, not via private properties.</span>
					<?php elseif ( $staff_excluded ) : ?>
						<span class="aun-mtf-ok">not loaded on this page — expected</span>
						<br>
						<span class="aun-mtf-muted">
							Fix 3 is on and you are logged in as staff, so the pixel is disabled for
							you. Meta's tracker constructor bails out before it registers any hooks,
							so there is nothing here to detect. <strong>This does not mean Fix 1 is
							broken</strong> — it runs normally for logged-out customers. See
							&ldquo;Last front-end run&rdquo; below for proof from a real visit.
							To see the tracker on this screen instead, turn Fix 3 off temporarily.
						</span>
					<?php else : ?>
						<span class="aun-mtf-bad">not found — Fix 1 cannot run.</span>
						<span class="aun-mtf-muted">
							The Pixel ID may be empty in the Meta plugin settings (the tracker is
							only constructed when a Pixel ID is set), or Meta has renamed
							<code>WC_Facebookcommerce_EventsTracker</code>.
						</span>
					<?php endif; ?>
				</p>

				<?php
				$last = get_option( self::LAST_RUN_KEY );
				if ( is_array( $last ) && ! empty( $last['time'] ) ) :
					$ago = human_time_diff( (int) $last['time'], time() );
					?>
					<p style="margin:12px 0 0;"><strong>Last front-end run</strong>
						<span class="aun-mtf-muted">(<?php echo esc_html( $ago ); ?> ago)</span>
					</p>
					<p style="margin:4px 0 0;">
						<?php if ( 'applied' === $last['status'] ) : ?>
							<span class="aun-mtf-ok">Fix 1 applied</span>
							<span class="aun-mtf-muted">in <?php echo esc_html( $last['mode'] ); ?> mode.</span>
							<br>
							<span class="aun-mtf-muted">
								Detached: <?php echo $last['removed'] ? self::code_list( $last['removed'] ) : 'nothing (already clean)'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in code_list(). ?>
							</span>
							<?php if ( ! empty( $last['readded'] ) ) : ?>
								<br>
								<span class="aun-mtf-muted">
									Re-attached: <?php echo self::code_list( $last['readded'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in code_list(). ?>
								</span>
							<?php endif; ?>
						<?php elseif ( 'pixel_off_for_visitor' === $last['status'] ) : ?>
							<span class="aun-mtf-muted">
								Pixel was intentionally disabled for that visitor (staff, or a user agent
								matched by Fix 2), so there was nothing for Fix 1 to do.
							</span>
						<?php else : ?>
							<span class="aun-mtf-bad">Tracker was missing on a request where the pixel should have been active.</span>
							<span class="aun-mtf-muted">Check the Pixel ID in the Meta plugin settings.</span>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $last['admin'] ) ) : ?>
						<p style="margin:4px 0 0;" class="aun-mtf-muted">
							<em>Recorded from a wp-admin request. Load a product page while logged out
							(private window) and refresh this screen for a front-end reading.</em>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p style="margin:12px 0 0;" class="aun-mtf-muted">
						<strong>Last front-end run:</strong> nothing recorded yet. Open the shop in a
						logged-out private window, then refresh this page.
					</p>
				<?php endif; ?>

				<?php if ( $hook_map ) : ?>
					<p style="margin:12px 0 0;"><strong>Purchase handler is currently attached to:</strong></p>
					<table>
						<?php foreach ( $hook_map as $hook => $priority ) : ?>
							<tr>
								<td><code><?php echo esc_html( $hook ); ?></code></td>
								<td>
									<?php if ( false !== $priority ) : ?>
										<span class="aun-mtf-ok">yes</span>
										<span class="aun-mtf-muted">(priority <?php echo esc_html( $priority ); ?>)</span>
									<?php else : ?>
										<span class="aun-mtf-muted">no</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<p style="margin:8px 0 0;" class="aun-mtf-muted">
						With Fix 1 on in <em>Defer</em> mode you should see <strong>no</strong> against the three
						pre-payment hooks, and <strong>yes</strong> against <code>woocommerce_thankyou</code>
						plus the payment-confirmed hooks.
					</p>
				<?php endif; ?>
			</div>

			<div class="notice notice-info inline" style="margin:16px 0;padding:10px 14px;">
				<p style="margin:0;">
					<strong>Note on duplicates.</strong> Meta for WooCommerce 3.7.x already deduplicates the
					browser and server Purchase: it stores one shared <code>event_id</code> on the order and
					sends it with both hits, so Events Manager merges them. If you are still seeing two
					purchases per order, the cause is almost certainly something else (a second pixel in the
					theme or GTM), not this plugin. What 3.7.x still gets wrong is the <em>timing</em> — the
					server event fires at order creation, before payment — which is what Fix 1 addresses.
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_KEY ); ?>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row">Fix 1 — Purchase timing</th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fix_duplicate_purchase]"
								       value="1" <?php checked( $settings['fix_duplicate_purchase'], 1 ); ?>>
								Stop Purchase firing before payment is confirmed
							</label>
							<p class="description">
								Meta registers its Purchase handler on <code>woocommerce_new_order</code>,
								<code>woocommerce_process_shop_order_meta</code> and
								<code>woocommerce_checkout_update_order_meta</code> — all of which run
								<strong>before</strong> the payment result is known. Failed payments and
								abandoned checkouts therefore report a Purchase. On this store the early
								hit also lands during <code>?wc-ajax=update_order_review</code>, where the
								line items are not populated yet, so it ships empty
								<code>content_ids</code> and loses catalog attribution.
							</p>

							<p style="margin:12px 0 4px;"><strong>What to do with the server event:</strong></p>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[purchase_mode]"
								       value="defer" <?php checked( $settings['purchase_mode'], 'defer' ); ?>>
								<strong>Defer it</strong> — move it to <code>woocommerce_payment_complete</code> /
								order status <code>processing</code> / <code>completed</code>.
								<span class="aun-mtf-muted">Recommended. Keeps CAPI coverage, which is the half of the signal that survives ad blockers and Safari ITP.</span>
							</label>
							<label style="display:block;">
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[purchase_mode]"
								       value="remove" <?php checked( $settings['purchase_mode'], 'remove' ); ?>>
								<strong>Remove it entirely</strong> — browser pixel only, on the thank-you page.
								<span class="aun-mtf-muted">Only pick this if you are deliberately running pixel-only tracking. You will lose roughly the share of purchases that ad blockers hide.</span>
							</label>

							<p class="description" style="margin-top:10px;">
								<strong>Verify in WooCommerce &rarr; Status &rarr; Logs</strong> after a test order:
								you should see a <code>Purchase event fired ... (context: server)</code> line only
								<em>after</em> payment, never on <code>woocommerce_new_order</code>.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Fix 2 — Block bots</th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fix_block_bots]"
								       value="1" <?php checked( $settings['fix_block_bots'], 1 ); ?>>
								Do not send pixel or CAPI events for bot traffic
							</label>
							<p class="description">
								Cache preloaders and crawlers fire pixel events on every page they visit.
								Requests whose user agent contains any string below are excluded entirely.
								Matching is case-insensitive substring matching — keep entries short.
							</p>
							<textarea
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bot_patterns]"
								rows="10" cols="40"
								style="font-family:monospace;"><?php echo esc_textarea( $settings['bot_patterns'] ); ?></textarea>
							<p class="description">
								One pattern per line. Leave empty to restore defaults.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Fix 3 — Exclude staff</th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fix_exclude_staff]"
								       value="1" <?php checked( $settings['fix_exclude_staff'], 1 ); ?>>
								Do not track logged-in administrators and shop managers
							</label>
							<p class="description">
								Lets you place test orders without polluting the dataset.
								Note this only covers logged-in staff — test from a logged-out
								private window and you will still be tracked.
							</p>
						</td>
					</tr>

				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h2>After changing settings</h2>
			<ol>
				<li>Clear the WP Rocket cache.</li>
				<li>Enable debug logging in the Meta for WooCommerce settings.</li>
				<li>Place one test order while logged out, in a private window, and pay for it.</li>
				<li>Open <strong>WooCommerce &rarr; Status &rarr; Logs</strong> and select the
					<code>facebook_for_woocommerce</code> log.</li>
				<li>Confirm the server Purchase line names a payment-confirmed hook, not
					<code>woocommerce_new_order</code>.</li>
				<li>Turn debug logging back off — <strong>it writes live access tokens to disk</strong>,
					and those log files are readable by anyone who can reach the uploads directory.</li>
			</ol>

			<p><em>Re-check the log after every Meta for WooCommerce update. Fix 1 depends on the
			plugin's internal hook layout and could stop working silently if Meta refactors it.
			The status panel above shows the live hook map, so you can confirm at a glance.</em></p>
		</div>
		<?php
	}
}

AUN_Meta_Tracking_Fixes::instance();
