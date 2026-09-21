<?php
/**
 * Plugin Name: Breo Live Tracking (Pathao)
 * Description: Order tracking for Breo Bangladesh. Customers enter their order number or phone number and see a live Pathao delivery timeline. Shortcode [breo_live_tracking]; a "Track Your Order" page is created on activation.
 * Version: 1.0.0
 * Author: Breo Bangladesh
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 *
 * Ported from AUN Live Tracking (Pathao) 2.10.0 — same engine, Breo design:
 *  - Pathao token comes from the official Pathao Courier plugin (pt_hms_get_token).
 *  - Phone search strips formatting inside SQL (HPOS + legacy), newest order with a
 *    tracking ID wins; phone/address are masked in the result.
 *  - IP rate limit 20 / 5 min, Pathao responses cached 20 min, IPv4 forced.
 *  - Nonce is verified softly and a stale one is re-minted and replayed once
 *    (cached pages outlive nonces — see mint_nonce()).
 *  New for Breo: icons are inline SVG (no FontAwesome on breo.bd), vanilla JS, and
 *  when the Pathao API is unavailable the customer still sees their order and a
 *  public Pathao tracking link instead of an error.
 */

defined( 'ABSPATH' ) || exit;

define( 'BREO_TRK_VERSION', '1.0.0' );
define( 'BREO_TRK_URL', plugin_dir_url( __FILE__ ) );

class Breo_Live_Tracking {

	const NONCE = 'breo_tracking_nonce';

	public static function init() {
		add_shortcode( 'breo_live_tracking', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'wp_ajax_breo_track_order', array( __CLASS__, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_breo_track_order', array( __CLASS__, 'ajax_track' ) );
		add_action( 'wp_ajax_breo_track_nonce', array( __CLASS__, 'mint_nonce' ) );
		add_action( 'wp_ajax_nopriv_breo_track_nonce', array( __CLASS__, 'mint_nonce' ) );

		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
	}

	/* ------------------------------------------------------------------ setup */

	/** Create the "Track Your Order" page once (never overwrites an existing one). */
	public static function activate() {
		if ( get_page_by_path( 'track-order' ) ) {
			return;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Track Your Order',
			'post_name'    => 'track-order',
			'post_excerpt' => 'Enter your order number or the phone number you ordered with to see where your parcel is.',
			'post_content' => "<!-- wp:shortcode -->\n[breo_live_tracking]\n<!-- /wp:shortcode -->",
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'breo_trk_page_id', (int) $id );
		}
	}

	public static function dependency_notice() {
		if ( function_exists( 'pt_hms_get_token' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Breo Live Tracking:</strong> install and connect the official <em>Pathao Courier</em> plugin to show live courier updates. Until then, customers still see their order status and a Pathao tracking link.</p></div>';
	}

	/* ------------------------------------------------------------------ front end */

	public static function assets() {
		wp_register_style( 'breo-tracking', BREO_TRK_URL . 'assets/breo-tracking.css', array(), BREO_TRK_VERSION );
		wp_register_script( 'breo-tracking', BREO_TRK_URL . 'assets/breo-tracking.js', array(), BREO_TRK_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		// Load early (in <head>) on pages that carry the shortcode, to avoid a flash of unstyled form.
		$post = get_post();
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'breo_live_tracking' ) ) {
			self::enqueue();
		}
	}

	private static function enqueue() {
		wp_enqueue_style( 'breo-tracking' );
		wp_enqueue_script( 'breo-tracking' );
		wp_localize_script( 'breo-tracking', 'breoTracking', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
			'i18n'  => array(
				'empty'    => __( 'Please enter your order number or phone number.', 'breo-live-tracking' ),
				'track'    => __( 'Track order', 'breo-live-tracking' ),
				'tracking' => __( 'Tracking…', 'breo-live-tracking' ),
				'fetching' => __( 'Fetching your delivery updates…', 'breo-live-tracking' ),
				'conn'     => __( 'Connection error. Please try again.', 'breo-live-tracking' ),
				'generic'  => __( 'Something went wrong. Please try again.', 'breo-live-tracking' ),
				'reload'   => __( 'Security check failed. Please reload the page.', 'breo-live-tracking' ),
			),
		) );
	}

	public static function render() {
		self::enqueue();
		$q = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		ob_start();
		?>
		<div class="breo-trk" data-breo-trk>
			<form class="breo-trk__form" novalidate>
				<label class="breo-trk__label" for="breo-trk-q"><?php esc_html_e( 'Order number or phone number', 'breo-live-tracking' ); ?></label>
				<div class="breo-trk__row">
					<span class="breo-trk__field">
						<?php echo self::icon( 'search' ); // phpcs:ignore ?>
						<input id="breo-trk-q" type="text" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'e.g. 1024 or 017XXXXXXXX', 'breo-live-tracking' ); ?>" value="<?php echo esc_attr( $q ); ?>" required>
					</span>
					<button type="submit" class="breo-trk__btn"><?php esc_html_e( 'Track order', 'breo-live-tracking' ); ?></button>
				</div>
				<p class="breo-trk__hint"><?php esc_html_e( 'Use the phone number you placed the order with. Personal details are partly hidden for your privacy.', 'breo-live-tracking' ); ?></p>
			</form>
			<div class="breo-trk__result" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ AJAX */

	/**
	 * Fresh nonce for the browser. Deliberately unguarded: needing a nonce to get a
	 * nonce defeats the purpose; it reveals nothing a page load does not, and the
	 * action it protects is rate-limited.
	 */
	public static function mint_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE ) ) );
	}

	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : ''; // phpcs:ignore
		// Forwarded-for headers are client-controlled; behind Cloudflare/a proxy, return the
		// visitor IP from a header you trust via this filter.
		$ip = (string) apply_filters( 'breo_trk_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	public static function ajax_track() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'bad_nonce', 'message' => __( 'Security check failed. Please reload the page.', 'breo-live-tracking' ) ), 403 );
		}

		// IP rate limit: 20 lookups per 5-minute window.
		$bucket   = floor( time() / ( 5 * MINUTE_IN_SECONDS ) );
		$rate_key = 'breo_trk_rl_' . md5( self::client_ip() . '_' . $bucket );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 20 ) {
			wp_send_json_error( __( 'Too many lookups. Please wait a few minutes and try again.', 'breo-live-tracking' ), 429 );
		}
		set_transient( $rate_key, $attempts + 1, 6 * MINUTE_IN_SECONDS );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		if ( '' === $search ) {
			wp_send_json_error( __( 'Please enter your order number or phone number.', 'breo-live-tracking' ), 400 );
		}

		$order = self::find_order( $search );
		if ( ! $order ) {
			wp_send_json_error( __( 'We could not find an order with that number or phone. Please check and try again.', 'breo-live-tracking' ), 404 );
		}

		wp_send_json_success( array( 'html' => self::result_html( $order ) ) );
	}

	/** Order by number first, then by billing phone (formatting-insensitive). */
	private static function find_order( $search ) {
		$clean = ltrim( $search, '#' );
		if ( ctype_digit( $clean ) && strlen( $clean ) < 10 ) {
			$o = wc_get_order( (int) $clean );
			if ( $o instanceof WC_Order ) {
				return $o;
			}
		}

		$digits = preg_replace( '/\D+/', '', $search );
		if ( strlen( $digits ) < 10 ) {
			return null;
		}
		$last10 = substr( $digits, -10 );

		global $wpdb;
		$like  = '%' . $wpdb->esc_like( $last10 ) . '%';
		$strip = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')";
		$hpos  = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		if ( $hpos ) {
			$table = $wpdb->prefix . 'wc_order_addresses';
			$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$table} WHERE address_type = 'billing' AND " . sprintf( $strip, 'phone' ) . ' LIKE %s ORDER BY order_id DESC LIMIT 10', $like ) ); // phpcs:ignore
		} else {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_billing_phone' AND " . sprintf( $strip, 'meta_value' ) . ' LIKE %s ORDER BY post_id DESC LIMIT 10', $like ) ); // phpcs:ignore
		}

		$orders = array();
		foreach ( (array) $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o instanceof WC_Order ) {
				$orders[] = $o;
			}
		}
		if ( ! $orders ) {
			return null;
		}
		// Prefer the newest order that already has a tracking ID.
		foreach ( $orders as $o ) {
			if ( '' !== self::tracking_id( $o ) ) {
				return $o;
			}
		}
		return $orders[0];
	}

	/** Pathao consignment ID from the known meta locations. */
	private static function tracking_id( WC_Order $order ) {
		foreach ( array( 'pathao_consignment_id', '_pathao_consignment_id', '_tracking_number', 'tracking_number' ) as $k ) {
			$v = $order->get_meta( $k );
			if ( ! empty( $v ) ) {
				return self::clean_id( (string) $v );
			}
		}
		$ast = $order->get_meta( '_wc_shipment_tracking_items' );
		if ( is_array( $ast ) && ! empty( $ast[0]['tracking_number'] ) ) {
			return self::clean_id( (string) $ast[0]['tracking_number'] );
		}
		return '';
	}

	private static function clean_id( $id ) {
		$id = strtok( $id, '&' );
		$id = strtok( $id, '?' );
		return trim( (string) $id );
	}

	private static function pathao_token() {
		if ( function_exists( 'pt_hms_get_token' ) ) {
			$t = pt_hms_get_token( true );
			if ( ! empty( $t ) ) {
				return $t;
			}
		}
		return '';
	}

	public static function force_ipv4( $handle ) {
		curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); // phpcs:ignore
	}

	/** Live data from Pathao, cached for 20 minutes. Null when unavailable. */
	private static function pathao_data( $tracking_id ) {
		$cache_key = 'breo_pathao_' . md5( $tracking_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
		$token = self::pathao_token();
		if ( '' === $token ) {
			return null;
		}
		$opts = get_option( 'pt_hms_settings' );
		$base = ( is_array( $opts ) && isset( $opts['environment'] ) && 'staging' === $opts['environment'] ) ? 'https://api-hermes-staging.pathao.com' : 'https://api-hermes.pathao.com';

		add_action( 'http_api_curl', array( __CLASS__, 'force_ipv4' ) );
		$res = wp_remote_get( $base . '/aladdin/api/v1/orders/' . rawurlencode( $tracking_id ), array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
			'timeout' => 25,
		) );
		remove_action( 'http_api_curl', array( __CLASS__, 'force_ipv4' ) );

		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['data'] ) || ( isset( $body['type'] ) && 'error' === $body['type'] ) ) {
			return null;
		}
		set_transient( $cache_key, $body['data'], 20 * MINUTE_IN_SECONDS );
		return $body['data'];
	}

	/* ------------------------------------------------------------------ result */

	private static function result_html( WC_Order $order ) {
		$num      = $order->get_order_number();
		$created  = $order->get_date_created();
		$ts       = $created ? $created->getTimestamp() : time();
		$placed   = wp_date( 'F j, Y', $ts );
		$tid      = self::tracking_id( $order );
		$status   = $order->get_status();
		$head     = '<p class="breo-trk-res__eyebrow">' . esc_html( sprintf( __( 'Order #%1$s · placed %2$s', 'breo-live-tracking' ), $num, $placed ) ) . '</p>';

		ob_start();
		echo '<div class="breo-trk-res">';

		if ( '' === $tid ) {
			// Not handed to a courier yet (or delivered in-house).
			if ( in_array( $status, array( 'completed', 'delivered', 'shipped' ), true ) ) {
				$state = array( 'done', 'check', __( 'Delivered', 'breo-live-tracking' ), __( 'This order has been delivered. Enjoy your Breo!', 'breo-live-tracking' ) );
			} elseif ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				$state = array( 'bad', 'x', wc_get_order_status_name( $status ), __( 'If you think this is a mistake, please contact our support team.', 'breo-live-tracking' ) );
			} else {
				$state = array( 'current', 'box', __( 'Preparing your order', 'breo-live-tracking' ), __( 'We are packing your order. A courier tracking number will appear here once it ships.', 'breo-live-tracking' ) );
			}
			echo '<div class="breo-trk-res__head">' . $head . '</div>'; // phpcs:ignore
			echo '<div class="breo-trk-state is-' . esc_attr( $state[0] ) . '"><span class="breo-trk-state__ico">' . self::icon( $state[1] ) . '</span><div><h3>' . esc_html( $state[2] ) . '</h3><p>' . esc_html( $state[3] ) . '</p></div></div>'; // phpcs:ignore
			echo self::details( $order, '' ); // phpcs:ignore
			echo '</div>';
			return ob_get_clean();
		}

		$data = self::pathao_data( $tid );
		if ( ! $data ) {
			echo '<div class="breo-trk-res__head">' . $head . '<p class="breo-trk-res__id">' . esc_html__( 'Pathao tracking ID', 'breo-live-tracking' ) . ' <strong>' . esc_html( $tid ) . '</strong></p></div>'; // phpcs:ignore
			echo '<div class="breo-trk-state is-current"><span class="breo-trk-state__ico">' . self::icon( 'truck' ) . '</span><div><h3>' . esc_html__( 'Handed to the courier', 'breo-live-tracking' ) . '</h3><p>' . esc_html__( 'Live updates are not available right now. You can follow the parcel directly on Pathao.', 'breo-live-tracking' ) . '</p></div></div>'; // phpcs:ignore
			echo self::details( $order, $tid ); // phpcs:ignore
			echo '</div>';
			return ob_get_clean();
		}

		$raw     = trim( (string) ( isset( $data['order_status'] ) ? $data['order_status'] : 'Pending' ) );
		$key     = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( $raw ) ), '_' );
		$fmt     = 'M j, g:i A';
		$at_new  = ! empty( $data['order_created_at'] ) ? wp_date( $fmt, strtotime( $data['order_created_at'] ) ) : wp_date( $fmt, $ts );
		$at_upd  = ! empty( $data['order_status_updated_at'] ) ? wp_date( $fmt, strtotime( $data['order_status_updated_at'] ) ) : '';

		$level = 0;
		if ( preg_match( '/pending|accept|assign/', $key ) ) { $level = 1; }
		if ( preg_match( '/pick/', $key ) ) { $level = 2; }
		if ( preg_match( '/transit/', $key ) ) { $level = 3; }
		if ( preg_match( '/hub|ready|out_for|delivery/', $key ) ) { $level = 4; }
		if ( preg_match( '/delivered|success|payment/', $key ) ) { $level = 5; }
		$level = max( 1, $level ); // it has a consignment, so at least "accepted"

		$steps = array(
			array( 'clipboard', __( 'Order accepted', 'breo-live-tracking' ), __( 'Confirmed and pickup requested', 'breo-live-tracking' ), $at_new ),
			array( 'box', __( 'Picked up', 'breo-live-tracking' ), __( 'The courier has collected your parcel', 'breo-live-tracking' ), 2 === $level ? $at_upd : '' ),
			array( 'truck', __( 'In transit', 'breo-live-tracking' ), __( 'On the way to your area', 'breo-live-tracking' ), 3 === $level ? $at_upd : '' ),
			array( 'hub', __( 'Out for delivery', 'breo-live-tracking' ), __( 'At the local hub, getting ready for delivery', 'breo-live-tracking' ), 4 === $level ? $at_upd : '' ),
			array( 'check', __( 'Delivered', 'breo-live-tracking' ), __( 'Handed over to you', 'breo-live-tracking' ), 5 === $level ? $at_upd : '' ),
		);
		$bad = '';
		if ( false !== strpos( $key, 'cancel' ) ) {
			$steps = array(
				$steps[0],
				array( 'x', __( 'Cancelled', 'breo-live-tracking' ), __( 'This shipment was cancelled', 'breo-live-tracking' ), $at_upd ),
			);
			$level = 2;
			$bad   = 'cancel';
		} elseif ( false !== strpos( $key, 'fail' ) ) {
			// A failed pickup/delivery attempt is retried, so keep the journey and flag that step.
			$at               = false !== strpos( $key, 'pick' ) ? 2 : 4;
			$steps[ $at - 1 ] = 2 === $at
				? array( 'x', __( 'Pickup failed', 'breo-live-tracking' ), __( 'The courier will try to collect it again', 'breo-live-tracking' ), $at_upd )
				: array( 'x', __( 'Delivery attempt failed', 'breo-live-tracking' ), __( 'The courier will contact you to try again', 'breo-live-tracking' ), $at_upd );
			$level            = $at;
			$bad              = 'fail';
		} elseif ( false !== strpos( $key, 'return' ) ) {
			$steps[4] = array( 'return', __( 'Returned', 'breo-live-tracking' ), __( 'The parcel is being returned to us', 'breo-live-tracking' ), $at_upd );
			$level    = 5;
			$bad      = 'return';
		}

		$label = ucwords( str_replace( '_', ' ', $key ) );
		$tone  = $bad ? 'bad' : ( 5 === $level ? 'done' : 'current' );
		$pills = array( 'cancel' => __( 'Cancelled', 'breo-live-tracking' ), 'fail' => __( 'Attempt failed', 'breo-live-tracking' ), 'return' => __( 'Returned', 'breo-live-tracking' ) );
		$pill  = $bad ? $pills[ $bad ] : ( 5 === $level ? __( 'Delivered', 'breo-live-tracking' ) : __( 'On the way', 'breo-live-tracking' ) );
		$pill  = 0 === strcasecmp( $pill, $label ) ? '' : '<span class="breo-trk-pill is-' . esc_attr( $tone ) . '">' . esc_html( $pill ) . '</span>';

		echo '<div class="breo-trk-res__head"><div>' . $head . '<h3 class="breo-trk-res__status">' . esc_html( $label ) . '</h3></div>' . $pill . '</div>'; // phpcs:ignore
		echo '<p class="breo-trk-res__id">' . esc_html__( 'Pathao tracking ID', 'breo-live-tracking' ) . ' <strong>' . esc_html( $tid ) . '</strong></p>';

		echo '<ol class="breo-trk-steps">';
		foreach ( $steps as $i => $s ) {
			$n     = $i + 1;
			$class = $n < $level ? 'is-done' : ( $n > $level ? 'is-todo' : ( $bad ? 'is-bad' : ( 5 === $level ? 'is-done' : 'is-current' ) ) );
			echo '<li class="' . esc_attr( $class ) . '"><span class="breo-trk-steps__dot">' . self::icon( $s[0] ) . '</span><div class="breo-trk-steps__txt"><strong>' . esc_html( $s[1] ) . '</strong>'; // phpcs:ignore
			if ( 'is-todo' !== $class ) {
				echo '<span>' . esc_html( $s[2] ) . '</span>';
				if ( $s[3] ) {
					echo '<time>' . self::icon( 'clock' ) . esc_html( $s[3] ) . '</time>'; // phpcs:ignore
				}
			}
			echo '</div></li>';
		}
		echo '</ol>';

		echo self::details( $order, $tid ); // phpcs:ignore
		echo '</div>';
		return ob_get_clean();
	}

	/** Customer + order cards (personal data masked) and the public Pathao link. */
	private static function details( WC_Order $order, $tid ) {
		$phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
		$phone = strlen( $phone ) > 11 ? substr( $phone, -11 ) : $phone; // drop the 880 country code
		$phone = strlen( $phone ) >= 8 ? substr( $phone, 0, 3 ) . str_repeat( '•', strlen( $phone ) - 6 ) . substr( $phone, -3 ) : '•••';
		// First name + last initial: enough to recognise your own order, not to identify someone else.
		$last = trim( (string) $order->get_billing_last_name() );
		$name = trim( $order->get_billing_first_name() . ( '' !== $last ? ' ' . mb_substr( $last, 0, 1 ) . '.' : '' ) );
		$name = '' !== $name ? $name : '—';
		$city  = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
		$pay   = $order->get_payment_method_title();
		$st    = $order->get_status();
		if ( in_array( $st, array( 'cancelled', 'failed' ), true ) ) {
			$pay .= ' · ' . __( 'cancelled', 'breo-live-tracking' );
		} elseif ( 'refunded' === $st ) {
			$pay .= ' · ' . __( 'refunded', 'breo-live-tracking' );
		}

		$h  = '<div class="breo-trk-grid">';
		$h .= '<div class="breo-trk-card"><h4>' . self::icon( 'user' ) . esc_html__( 'Delivery to', 'breo-live-tracking' ) . '</h4>';
		$h .= '<p><span>' . esc_html__( 'Name', 'breo-live-tracking' ) . '</span>' . esc_html( $name ) . '</p>';
		$h .= '<p><span>' . esc_html__( 'Phone', 'breo-live-tracking' ) . '</span>' . esc_html( $phone ) . '</p>';
		$h .= '<p><span>' . esc_html__( 'Area', 'breo-live-tracking' ) . '</span>' . esc_html( $city ? $city : '—' ) . '</p></div>';

		$h .= '<div class="breo-trk-card"><h4>' . self::icon( 'receipt' ) . esc_html__( 'Order summary', 'breo-live-tracking' ) . '</h4>';
		$h .= '<ul class="breo-trk-items">';
		foreach ( $order->get_items() as $item ) {
			$name = wp_strip_all_tags( html_entity_decode( str_replace( array( '<span>', '</span>' ), '', $item->get_name() ) ) );
			$prod = $item->get_product();
			$h   .= '<li><span class="breo-trk-items__q">' . (int) $item->get_quantity() . '×</span>'
				. ( $prod ? '<a href="' . esc_url( $prod->get_permalink() ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</li>';
		}
		$h .= '</ul>';
		$h .= '<p><span>' . esc_html__( 'Total', 'breo-live-tracking' ) . '</span>' . wp_kses_post( $order->get_formatted_order_total() ) . '</p>';
		$h .= '<p><span>' . esc_html__( 'Payment', 'breo-live-tracking' ) . '</span>' . esc_html( $pay ? $pay : '—' ) . '</p></div>';
		$h .= '</div>';

		if ( $tid ) {
			$h .= '<a class="breo-trk-ext" href="' . esc_url( 'https://merchant.pathao.com/public-tracking?consignment_id=' . rawurlencode( $tid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View full history on Pathao', 'breo-live-tracking' ) . self::icon( 'ext' ) . '</a>';
		}
		return $h;
	}

	/* ------------------------------------------------------------------ icons */

	private static function icon( $name ) {
		$p = array(
			'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
			'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 13l2 2 4-4"/>',
			'box'       => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
			'truck'     => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
			'hub'       => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/>',
			'check'     => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 10"/>',
			'x'         => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
			'return'    => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>',
			'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
			'receipt'   => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
			'ext'       => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
		);
		return '<svg class="breo-trk-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $p[ $name ] ) ? $p[ $name ] : '' ) . '</svg>';
	}
}

Breo_Live_Tracking::init();
register_activation_hook( __FILE__, array( 'Breo_Live_Tracking', 'activate' ) );
