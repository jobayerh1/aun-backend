<?php
/**
 * Plugin Name: Breo Smart Delivery
 * Description: Delivery estimates for Breo Bangladesh: a Delivery card on product pages (with an area picker), real delivery dates under each shipping option in the cart and checkout, on order pages and in emails. Backorder items get their own lead time and can ship as a separate package. Settings: WooCommerce → Delivery Estimates.
 * Version: 1.1.0
 * Author: Breo Bangladesh
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 *
 * Ported from AUN Smart Delivery 20.1.0: same rules engine (per shipping method
 * transit days + order cut-off, Friday weekend, holiday ranges, backorder lead
 * time with a per-product override and optional live countdown). Changed for Breo:
 *  - Block cart/checkout: the estimate is set as the rate's delivery_time, which
 *    the checkout block prints under each option (woocommerce_after_shipping_rate
 *    only fires in the classic template, where the same text is echoed).
 *  - Packages keep all of WooCommerce's keys; the cart is split into "ready to
 *    ship" / "on backorder" only when it mixes both (and splitting is optional).
 *  - A method with blank days has no estimate (AUN treated blank as 0 = "today").
 *  - Breo-styled UI, inline SVG icons (no FontAwesome), vanilla JS, and the
 *    stale-nonce re-mint + single replay for cached product pages.
 */

defined( 'ABSPATH' ) || exit;

define( 'BREO_DLV_VERSION', '1.1.0' );
define( 'BREO_DLV_URL', plugin_dir_url( __FILE__ ) );

final class Breo_Smart_Delivery {

	const OPTION = 'breo_delivery_rules';
	const NONCE  = 'breo_delivery_nonce';
	const BO_MIN = 7;
	const BO_MAX = 14;

	/** Per-product backorder ETA meta (cleared when a countdown product leaves backorder). */
	const BO_META = array( '_breo_backorder_countdown', '_breo_backorder_anchor', '_breo_backorder_min_days', '_breo_backorder_max_days', '_breo_backorder_label', '_breo_backorder_last_status' );

	private static $rules = null;
	private static $zones = null;

	public function __construct() {
		// Cart & checkout.
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'prepare_packages' ), 20 );
		add_filter( 'woocommerce_shipping_package_name', array( $this, 'package_name' ), 10, 3 );
		add_filter( 'woocommerce_package_rates', array( $this, 'rate_estimates' ), 20, 2 );
		add_action( 'woocommerce_after_shipping_rate', array( $this, 'classic_rate_estimate' ), 20, 1 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_note' ), 10, 2 );

		// Orders & emails.
		add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'save_to_shipping_item' ), 10, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_estimates' ), 20, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_estimates' ), 20, 4 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'order_item_note' ), 10, 4 );

		// Product page: the Breo buy panel, or the standard summary on any other theme.
		add_action( 'breo_bd_buy_after_form', array( $this, 'product_card' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'product_card' ), 35 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_breo_delivery_estimate', array( $this, 'ajax_estimate' ) );
		add_action( 'wp_ajax_nopriv_breo_delivery_estimate', array( $this, 'ajax_estimate' ) );
		add_action( 'wp_ajax_breo_delivery_nonce', array( $this, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_breo_delivery_nonce', array( $this, 'ajax_nonce' ) );

		// Admin.
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 60 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_breo_delivery_group', array( $this, 'settings_capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_metabox' ) );

		// A per-product countdown is one backorder spell: it ends when the product leaves backorder.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'stock_changed' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'stock_changed' ), 10, 1 );
		add_action( 'woocommerce_updated_product_stock', array( $this, 'stock_changed' ), 10, 1 );
		add_action( 'woocommerce_product_object_updated_props', array( $this, 'props_updated' ), 10, 2 );
	}

	/* =====================================================================
	 * Rules
	 * ================================================================== */

	private function rules() {
		if ( null === self::$rules ) {
			$o           = get_option( self::OPTION, array() );
			self::$rules = is_array( $o ) ? $o : array();
		}
		return self::$rules;
	}

	/** Transit rule for one shipping method, or null when it has no days set. */
	private function rule( $zone_id, $instance_id ) {
		$all = $this->rules();
		$key = (int) $zone_id . ':' . (int) $instance_id;
		if ( empty( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
			return null;
		}
		$r   = $all[ $key ];
		$min = isset( $r['min_days'] ) ? trim( (string) $r['min_days'] ) : '';
		$max = isset( $r['max_days'] ) ? trim( (string) $r['max_days'] ) : '';
		if ( '' === $min && '' === $max ) {
			return null;
		}
		$min           = '' === $min ? (int) $max : (int) $min;
		$max           = '' === $max ? $min : (int) $max;
		$r['min_days'] = $min;
		$r['max_days'] = max( $min, $max );
		return $r;
	}

	/** Zones (id => customer-facing name) that have at least one enabled method with a rule. */
	private function zones_with_rules() {
		if ( null !== self::$zones ) {
			return self::$zones;
		}
		self::$zones = array();
		foreach ( $this->all_zones() as $zid => $z ) {
			foreach ( $z['methods'] as $m ) {
				if ( $m->is_enabled() && $this->rule( $zid, $m->get_instance_id() ) ) {
					self::$zones[ $zid ] = $z['name'];
					break;
				}
			}
		}
		return self::$zones;
	}

	/** Every zone with its methods; "Everywhere else" (zone 0) only when it has methods. */
	private function all_zones() {
		$out = array();
		foreach ( WC_Shipping_Zones::get_zones() as $zid => $z ) {
			$out[ (int) $zid ] = array(
				'name'     => $z['zone_name'],
				'where'    => isset( $z['formatted_zone_location'] ) ? $z['formatted_zone_location'] : '',
				'methods'  => isset( $z['shipping_methods'] ) ? (array) $z['shipping_methods'] : array(),
			);
		}
		$rest = ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods();
		if ( $rest ) {
			$out[0] = array( 'name' => 'Everywhere else', 'where' => 'Locations not covered by your other zones', 'methods' => $rest );
		}
		return $out;
	}

	private function default_message() {
		$r = $this->rules();
		$m = isset( $r['default_message'] ) ? trim( (string) $r['default_message'] ) : '';
		return '' !== $m ? $m : $this->suggested_message();
	}

	/** Built from the Breo BD Setup delivery days when available. */
	private function suggested_message() {
		if ( function_exists( 'breo_bd_opt' ) ) {
			$in  = breo_bd_opt( 'dhaka_days' );
			$out = breo_bd_opt( 'outside_days' );
			if ( '' !== $in && '' !== $out ) {
				$wd = function ( $v ) {
					return $v . ' working ' . ( '1' === trim( $v ) ? 'day' : 'days' );
				};
				return 'Inside Dhaka in ' . $wd( $in ) . ' · outside Dhaka in ' . $wd( $out );
			}
		}
		return 'Delivered across Bangladesh in 2–4 working days';
	}

	/* =====================================================================
	 * Backorder lead time
	 * ================================================================== */

	/**
	 * Backorder dispatch lead time for a product (or the global default).
	 * The product's own days/label win over the global setting. With "live
	 * countdown" the remaining days shrink daily from the anchor date, then
	 * read "any day now".
	 *
	 * @return array{min:int,max:int,label:string,imminent:bool}
	 */
	private function leadtime( $product = null ) {
		$r        = $this->rules();
		$min      = ( isset( $r['backorder_min_days'] ) && '' !== (string) $r['backorder_min_days'] ) ? max( 0, (int) $r['backorder_min_days'] ) : self::BO_MIN;
		$max      = ( isset( $r['backorder_max_days'] ) && '' !== (string) $r['backorder_max_days'] ) ? max( 0, (int) $r['backorder_max_days'] ) : self::BO_MAX;
		$max      = max( $min, $max );
		$label    = isset( $r['backorder_label'] ) ? trim( (string) $r['backorder_label'] ) : '';
		$imminent = false;

		if ( $product instanceof WC_Product ) {
			$m   = $this->product_meta( $product );
			$own = false;
			if ( '' !== $m['min_days'] && is_numeric( $m['min_days'] ) ) {
				$min = max( 0, (int) $m['min_days'] );
				$own = true;
			}
			if ( '' !== $m['max_days'] && is_numeric( $m['max_days'] ) ) {
				$max = max( 0, (int) $m['max_days'] );
				$own = true;
			}
			$max = max( $min, $max );
			if ( '' !== trim( $m['label'] ) ) {
				$label = trim( $m['label'] );       // explicit per-product wording wins
			} elseif ( $own ) {
				$label = '';                        // own days → regenerate, ignore the global label
			}
			if ( '1' === $m['countdown'] && '' !== $m['anchor'] ) {
				$gone = $this->days_since( $m['anchor'] );
				$min  = max( 0, $min - $gone );
				$max  = max( 0, $max - $gone );
				if ( $max <= 0 ) {
					$imminent = true;
					$label    = 'any day now';
				} else {
					$label = $this->days_label( $min, $max );
				}
			}
		}
		if ( '' === $label ) {
			$label = $this->days_label( $min, $max );
		}
		return array( 'min' => $min, 'max' => $max, 'label' => $label, 'imminent' => $imminent );
	}

	/** Per-product meta; a variation inherits blank fields from its parent. */
	private function product_meta( WC_Product $product ) {
		$out = array();
		foreach ( array( 'min_days', 'max_days', 'label', 'countdown', 'anchor' ) as $k ) {
			$out[ $k ] = (string) get_post_meta( $product->get_id(), '_breo_backorder_' . $k, true );
		}
		if ( $product->is_type( 'variation' ) ) {
			$parent = $product->get_parent_id();
			foreach ( array( 'min_days', 'max_days', 'label' ) as $k ) {
				if ( '' === $out[ $k ] ) {
					$out[ $k ] = (string) get_post_meta( $parent, '_breo_backorder_' . $k, true );
				}
			}
			if ( '' === $out['countdown'] ) {
				$out['countdown'] = (string) get_post_meta( $parent, '_breo_backorder_countdown', true );
				$out['anchor']    = (string) get_post_meta( $parent, '_breo_backorder_anchor', true );
			}
		}
		return $out;
	}

	/** Whole days since a Y-m-d anchor (store timezone), floored at 0. */
	private function days_since( $ymd ) {
		$tz     = wp_timezone();
		$anchor = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, $tz );
		if ( ! $anchor ) {
			return 0;
		}
		$today = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0 );
		return $today <= $anchor ? 0 : (int) $anchor->diff( $today )->days;
	}

	/** The slowest lead time among the backordered items of a package. */
	private function package_leadtime( $package ) {
		$best = null;
		foreach ( (array) ( isset( $package['contents'] ) ? $package['contents'] : array() ) as $ci ) {
			$p = isset( $ci['data'] ) ? $ci['data'] : null;
			if ( ! $p instanceof WC_Product || ! $p->is_on_backorder( isset( $ci['quantity'] ) ? (int) $ci['quantity'] : 0 ) ) {
				continue;
			}
			$lt = $this->leadtime( $p );
			if ( null === $best || $lt['max'] > $best['max'] || ( $lt['max'] === $best['max'] && $lt['min'] > $best['min'] ) ) {
				$best = $lt;
			}
		}
		return $best ? $best : $this->leadtime();
	}

	/** "1–2 weeks", "3–5 days", "1 week", "5 days". Mirrored by the admin preview JS. */
	private function days_label( $min, $max ) {
		$min = (int) $min;
		$max = (int) $max;
		if ( $max <= 0 ) {
			return '1–2 weeks';
		}
		if ( 0 === $min % 7 && 0 === $max % 7 ) {
			$a = intdiv( $min, 7 );
			$b = intdiv( $max, 7 );
			return ( $a === $b || 0 === $a ) ? $b . ' ' . ( 1 === $b ? 'week' : 'weeks' ) : $a . '–' . $b . ' weeks';
		}
		return ( $min === $max || 0 === $min ) ? $max . ' ' . ( 1 === $max ? 'day' : 'days' ) : $min . '–' . $max . ' days';
	}

	/* =====================================================================
	 * Dates
	 * ================================================================== */

	private function holidays() {
		$r   = $this->rules();
		$out = array();
		foreach ( (array) ( isset( $r['holidays'] ) ? $r['holidays'] : array() ) as $h ) {
			if ( ! empty( $h['start'] ) ) {
				$out[] = array( $h['start'], ! empty( $h['end'] ) ? $h['end'] : $h['start'] );
			}
		}
		return $out;
	}

	/** Friday is the weekly day off in Bangladesh; plus the holiday ranges. */
	private function is_day_off( DateTimeImmutable $d ) {
		if ( in_array( (int) $d->format( 'w' ), (array) apply_filters( 'breo_delivery_weekend_days', array( 5 ) ), true ) ) {
			return true;
		}
		$ymd = $d->format( 'Y-m-d' );
		foreach ( $this->holidays() as $h ) {
			if ( $ymd >= $h[0] && $ymd <= $h[1] ) {
				return true;
			}
		}
		return false;
	}

	private function add_working_days( DateTimeImmutable $d, $n ) {
		while ( $n > 0 ) {
			$d = $d->modify( '+1 day' );
			if ( ! $this->is_day_off( $d ) ) {
				$n--;
			}
		}
		return $d;
	}

	/**
	 * "by Tue, Sep 17" · "Tue, Sep 17 – Thu, Sep 19" · "today if you order by 5:00 pm".
	 * Counting starts today (tomorrow once today's cut-off has passed) and skips days off.
	 */
	private function when( $min_days, $max_days, $cutoff = '', $html = false ) {
		$tz   = wp_timezone();
		$now  = new DateTimeImmutable( 'now', $tz );
		$late = false;
		$cut  = null;
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $cutoff, $m ) ) {
			$cut  = $now->setTime( (int) $m[1], (int) $m[2] );
			$late = $now > $cut;
		}
		$start = $late ? $now->modify( '+1 day' ) : $now;
		while ( $this->is_day_off( $start ) ) {
			$start = $start->modify( '+1 day' );
		}
		$first = $this->add_working_days( $start, (int) $min_days );
		$last  = $this->add_working_days( $first, max( 0, (int) $max_days - (int) $min_days ) );
		$b     = function ( $s ) use ( $html ) {
			return $html ? '<strong>' . esc_html( $s ) . '</strong>' : $s;
		};

		if ( 0 === (int) $max_days && $first->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
			$tf = get_option( 'time_format' );
			return $b( 'today' ) . ( $cut ? ' if you order by ' . wp_date( $tf ? $tf : 'g:i a', $cut->getTimestamp() ) : '' );
		}
		$fmt = 'D, M j';
		$a   = wp_date( $fmt, $first->getTimestamp() );
		if ( $first->format( 'Y-m-d' ) === $last->format( 'Y-m-d' ) ) {
			return 'by ' . $b( $a );
		}
		return $b( $a ) . ' – ' . $b( wp_date( $fmt, $last->getTimestamp() ) );
	}

	/**
	 * Estimate for one shipping method: "Arrives by Tue, Sep 17", "Ready for pickup
	 * Tue, Sep 17 – Thu, Sep 19", "Arriving any day now". '' when the method has no rule.
	 */
	private function method_estimate( $rule, $type = 'in_stock', $package = null, $html = false ) {
		if ( ! $rule ) {
			return '';
		}
		$pickup = ! empty( $rule['pickup'] ) && '1' === (string) $rule['pickup'];
		$pmsg   = ! empty( $rule['pickup_message'] ) ? $rule['pickup_message'] : 'Ready for pickup';
		$pmsg   = $html ? esc_html( $pmsg ) : $pmsg;

		if ( 'backorder' === $type ) {
			$lt = $package ? $this->package_leadtime( $package ) : $this->leadtime();
			if ( $lt['imminent'] ) {
				return $pickup ? $pmsg . ' as soon as it arrives (any day now)' : 'Arriving any day now';
			}
			if ( $pickup ) {
				return $pmsg . ' in ' . $lt['label'];
			}
			return 'Arrives ' . $this->when( $lt['min'] + (int) $rule['min_days'], $lt['max'] + (int) $rule['max_days'], '', $html );
		}
		return ( $pickup ? $pmsg : 'Arrives' ) . ' ' . $this->when( $rule['min_days'], $rule['max_days'], isset( $rule['cutoff'] ) ? $rule['cutoff'] : '', $html );
	}

	/* =====================================================================
	 * Cart & checkout
	 * ================================================================== */

	/**
	 * Tag each package in_stock/backorder and, when splitting is on and the cart
	 * mixes both, ship backorder items as their own package. The hourly stamp
	 * changes the package hash so cached rates (and their dates) refresh.
	 */
	public function prepare_packages( $packages ) {
		if ( ! is_array( $packages ) ) {
			return $packages;
		}
		$r     = $this->rules();
		$split = ! isset( $r['split'] ) || 'no' !== $r['split'];
		$stamp = current_time( 'Y-m-d H' );
		$out   = array();
		foreach ( $packages as $pkg ) {
			$ready = array();
			$later = array();
			foreach ( (array) ( isset( $pkg['contents'] ) ? $pkg['contents'] : array() ) as $key => $item ) {
				$p = isset( $item['data'] ) ? $item['data'] : null;
				if ( $p instanceof WC_Product && $p->is_on_backorder( isset( $item['quantity'] ) ? (int) $item['quantity'] : 0 ) ) {
					$later[ $key ] = $item;
				} else {
					$ready[ $key ] = $item;
				}
			}
			$pkg['breo_eta'] = $stamp;
			if ( $split && $ready && $later ) {
				foreach ( array( 'in_stock' => $ready, 'backorder' => $later ) as $type => $items ) {
					$part                  = $pkg;
					$part['contents']      = $items;
					$part['contents_cost'] = array_sum( wp_list_pluck( $items, 'line_total' ) );
					$part['package_type']  = $type;
					$part['breo_split']    = true;
					$out[]                 = $part;
				}
			} else {
				$pkg['package_type'] = $later ? 'backorder' : 'in_stock';
				$out[]               = $pkg;
			}
		}
		return $out;
	}

	public function package_name( $name, $index, $package ) {
		if ( ! empty( $package['breo_split'] ) ) {
			return 'backorder' === $package['package_type'] ? 'On backorder (ships later)' : 'Ready to ship';
		}
		return $name;
	}

	/** Put the estimate on each rate; the checkout block shows it under the option. */
	public function rate_estimates( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}
		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		$zid  = $zone ? $zone->get_id() : 0;
		$type = isset( $package['package_type'] ) ? $package['package_type'] : 'in_stock';
		foreach ( $rates as $rate ) {
			if ( ! $rate instanceof WC_Shipping_Rate || ! method_exists( $rate, 'set_delivery_time' ) ) {
				continue;
			}
			$text = $this->method_estimate( $this->rule( $zid, $rate->get_instance_id() ), $type, $package );
			if ( '' !== $text ) {
				$rate->set_delivery_time( $text );
			}
		}
		return $rates;
	}

	/** Classic cart/checkout template: echo the same text under the option. */
	public function classic_rate_estimate( $method ) {
		if ( $method instanceof WC_Shipping_Rate && method_exists( $method, 'get_delivery_time' ) && '' !== (string) $method->get_delivery_time() ) {
			echo '<span class="breo-dlv-rate">' . esc_html( $method->get_delivery_time() ) . '</span>';
		}
	}

	public function cart_item_note( $data, $cart_item ) {
		$p = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( $p instanceof WC_Product && $p->is_on_backorder( isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0 ) ) {
			$lt     = $this->leadtime( $p );
			$data[] = array(
				'key'   => 'Delivery',
				'value' => $lt['imminent'] ? 'On backorder, arriving any day now' : 'On backorder, ships in ' . $lt['label'],
			);
		}
		return $data;
	}

	/* =====================================================================
	 * Orders & emails
	 * ================================================================== */

	public function save_to_shipping_item( $item, $package_key, $package, $order ) {
		if ( ! $item instanceof WC_Order_Item_Shipping ) {
			return;
		}
		$text = '';
		foreach ( (array) ( isset( $package['rates'] ) ? $package['rates'] : array() ) as $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && $rate->get_method_id() === $item->get_method_id() && (int) $rate->get_instance_id() === (int) $item->get_instance_id() ) {
				$text = method_exists( $rate, 'get_delivery_time' ) ? (string) $rate->get_delivery_time() : '';
				break;
			}
		}
		if ( '' === $text ) {
			$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
			$text = $this->method_estimate( $this->rule( $zone ? $zone->get_id() : 0, $item->get_instance_id() ), isset( $package['package_type'] ) ? $package['package_type'] : 'in_stock', $package );
		}
		if ( '' === $text ) {
			return;
		}
		$names = array();
		foreach ( (array) ( isset( $package['contents'] ) ? $package['contents'] : array() ) as $ci ) {
			if ( ! empty( $ci['data'] ) && $ci['data'] instanceof WC_Product ) {
				$names[] = trim( wp_strip_all_tags( html_entity_decode( $ci['data']->get_name() ) ) ) . ' × ' . (int) $ci['quantity'];
			}
		}
		$item->add_meta_data( '_breo_delivery_estimate', $text, true );
		$item->add_meta_data( '_breo_package_contents', $names, true );
		$item->add_meta_data( '_breo_package_type', isset( $package['package_type'] ) ? $package['package_type'] : 'in_stock', true );
	}

	/** Saved estimates, or none once the order is finished (a past date helps nobody). */
	private function order_rows( $order ) {
		if ( ! $order instanceof WC_Order || $order->has_status( array( 'completed', 'cancelled', 'refunded', 'failed' ) ) ) {
			return array();
		}
		$rows = array();
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$eta = (string) $item->get_meta( '_breo_delivery_estimate' );
			if ( '' !== $eta ) {
				$rows[] = array(
					'type'   => (string) $item->get_meta( '_breo_package_type' ),
					'method' => $item->get_name(),
					'eta'    => $eta,
					'items'  => (array) $item->get_meta( '_breo_package_contents' ),
				);
			}
		}
		return $rows;
	}

	public function order_estimates( $order ) {
		$rows = $this->order_rows( $order );
		if ( ! $rows ) {
			return;
		}
		$multi = count( $rows ) > 1;
		echo '<section class="breo-dlv-order"><h2 class="breo-dlv-order__h">Delivery estimate</h2>';
		foreach ( $rows as $r ) {
			$bo = 'backorder' === $r['type'];
			echo '<div class="breo-dlv-order__pkg' . ( $bo ? ' is-backorder' : '' ) . '"><span class="breo-dlv__ico">' . $this->icon( $bo ? 'box' : 'truck' ) . '</span><div>'; // phpcs:ignore
			if ( $multi ) {
				echo '<p class="breo-dlv-order__t">' . esc_html( $bo ? 'On backorder' : 'Ready to ship' ) . '</p>';
			}
			echo '<p class="breo-dlv-order__eta">' . esc_html( $r['eta'] ) . '</p>';
			echo '<p class="breo-dlv-order__m">' . esc_html( $r['method'] . ( $multi && $r['items'] ? ' · ' . implode( ', ', $r['items'] ) : '' ) ) . '</p>';
			echo '</div></div>';
		}
		echo '</section>';
	}

	public function email_estimates( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		$rows = $this->order_rows( $order );
		if ( ! $rows ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n\nDELIVERY ESTIMATE\n";
			foreach ( $rows as $r ) {
				echo "\n" . wp_strip_all_tags( $r['eta'] . ' (' . $r['method'] . ')' ) . "\n"; // phpcs:ignore
				if ( $r['items'] ) {
					echo wp_strip_all_tags( implode( ', ', $r['items'] ) ) . "\n"; // phpcs:ignore
				}
			}
			return;
		}
		echo '<div style="margin:0 0 32px"><h2>Delivery estimate</h2><table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse">';
		foreach ( $rows as $r ) {
			echo '<tr><td style="padding:14px 16px;border:1px solid #e8e2da;background:#f7f2eb;vertical-align:top">'
				. '<strong style="display:block;font-size:15px;color:#1c1c1c">' . esc_html( $r['eta'] ) . '</strong>'
				. '<span style="display:block;margin-top:4px;font-size:13px;color:#6f6a63">' . esc_html( $r['method'] . ( $r['items'] ? ' · ' . implode( ', ', $r['items'] ) : '' ) ) . '</span>'
				. '</td></tr>';
		}
		echo '</table></div>';
	}

	public function order_item_note( $item_id, $item, $order, $plain_text = false ) {
		if ( ! $item instanceof WC_Order_Item_Product || ! $order instanceof WC_Order || $order->has_status( array( 'completed', 'cancelled', 'refunded' ) ) ) {
			return;
		}
		$product = $item->get_product();
		if ( ! $product instanceof WC_Product || ! $product->is_on_backorder() ) {
			return;
		}
		$lt  = $this->leadtime( $product );
		$msg = $lt['imminent'] ? 'Backorder item, arriving any day now.' : 'Backorder item, ships in ' . $lt['label'] . '.';
		if ( $plain_text ) {
			echo "\n" . wp_strip_all_tags( $msg ); // phpcs:ignore
		} else {
			echo '<div style="margin-top:6px"><small>' . esc_html( $msg ) . '</small></div>';
		}
	}

	/* =====================================================================
	 * Product page
	 * ================================================================== */

	public function assets() {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}
		$product = is_product();
		if ( ! $product && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}
		wp_enqueue_style( 'breo-delivery', BREO_DLV_URL . 'assets/breo-delivery.css', array(), BREO_DLV_VERSION );
		if ( $product ) {
			wp_enqueue_script( 'breo-delivery', BREO_DLV_URL . 'assets/breo-delivery.js', array(), BREO_DLV_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_localize_script( 'breo-delivery', 'breoDelivery', array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => array(
					'wait' => 'Checking delivery dates…',
					'fail' => 'Could not load delivery dates. Please try again.',
				),
			) );
		}
	}

	public function product_card( $product = null ) {
		static $done = array();
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof WC_Product || isset( $done[ $product->get_id() ] ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}
		$done[ $product->get_id() ] = true;

		if ( $product->is_on_backorder() ) {
			$lt  = $this->leadtime( $product );
			$msg = $lt['imminent'] ? 'Arriving any day now. Order now to get one of the first.' : 'Ships in ' . $lt['label'] . '. Order now to reserve yours.';
			echo '<div class="breo-dlv is-backorder"><div class="breo-dlv__main"><span class="breo-dlv__ico">' . $this->icon( 'box' ) . '</span><div class="breo-dlv__body">' // phpcs:ignore
				. '<p class="breo-dlv__k">Pre-order</p><div class="breo-dlv__v">' . esc_html( $msg ) . '</div></div></div></div>';
			return;
		}

		$zones = $this->zones_with_rules();
		$uid   = 'breo-dlv-zone-' . $product->get_id();
		?>
		<div class="breo-dlv" data-breo-dlv data-product="<?php echo (int) $product->get_id(); ?>">
			<div class="breo-dlv__main">
				<span class="breo-dlv__ico"><?php echo $this->icon( 'truck' ); // phpcs:ignore ?></span>
				<div class="breo-dlv__body">
					<p class="breo-dlv__k">Delivery</p>
					<div class="breo-dlv__v" data-breo-dlv-out aria-live="polite"><?php echo esc_html( $this->default_message() ); ?></div>
				</div>
			</div>
			<?php if ( $zones ) : ?>
				<div class="breo-dlv__area">
					<label for="<?php echo esc_attr( $uid ); ?>">Deliver to</label>
					<span class="breo-dlv__select">
						<select id="<?php echo esc_attr( $uid ); ?>" data-breo-dlv-zone>
							<option value="">Choose your area</option>
							<?php foreach ( $zones as $zid => $name ) : ?>
								<option value="<?php echo (int) $zid; ?>"><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * What one of this product would cost to ship, asked of WooCommerce itself.
	 *
	 * Reading the method's own "cost" setting was wrong: a flat rate can price
	 * by shipping class or a [qty] formula (the raw field then reads 0, which
	 * showed as "Free" while checkout charged the real amount), and free
	 * shipping can have a minimum. Asking the method for its rates covers
	 * every case, including third-party couriers.
	 *
	 * @return array list of array( label, cost ) - cost null when unknown.
	 */
	private function method_rates( $m, $package ) {
		$out = array();
		if ( ! is_callable( array( $m, 'get_rates_for_package' ) ) ) {
			return array( array( 'label' => $m->get_title(), 'cost' => null ) );
		}
		try {
			$rates = (array) $m->get_rates_for_package( $package );
		} catch ( Throwable $e ) { // a courier plugin that needs a real cart
			return array( array( 'label' => $m->get_title(), 'cost' => null ) );
		}
		foreach ( $rates as $rate ) {
			if ( ! $rate instanceof WC_Shipping_Rate ) {
				continue;
			}
			$cost = (float) $rate->get_cost();
			if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
				$cost += array_sum( (array) $rate->get_taxes() );
			}
			$out[] = array( 'label' => $rate->get_label() ? $rate->get_label() : $m->get_title(), 'cost' => $cost );
		}
		return $out; // empty = the method is not available here (e.g. free shipping below its minimum)
	}

	/** A one-item package for this product, addressed to the chosen zone. */
	private function pseudo_package( $product, $zone ) {
		$price    = $product ? (float) $product->get_price() : 0;
		$contents = array();
		if ( $product ) {
			$contents[ 'breo_' . $product->get_id() ] = array(
				'key'               => 'breo_' . $product->get_id(),
				'product_id'        => $product->get_id(),
				'variation_id'      => 0,
				'variation'         => array(),
				'quantity'          => 1,
				'data'              => $product,
				'line_total'        => $price,
				'line_subtotal'     => $price,
				'line_tax'          => 0,
				'line_subtotal_tax' => 0,
			);
		}
		if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart(); // free shipping asks the cart about totals and coupons
		}
		return array(
			'contents'        => $contents,
			'contents_cost'   => $price,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => get_current_user_id() ),
			'destination'     => $this->zone_destination( $zone ),
			'cart_subtotal'   => $price,
		);
	}

	/** An address inside the zone, so the method prices it the way checkout will. */
	private function zone_destination( $zone ) {
		$dest = array(
			'country'   => '',
			'state'     => '',
			'postcode'  => '',
			'city'      => '',
			'address'   => '',
			'address_1' => '',
			'address_2' => '',
		);
		foreach ( (array) $zone->get_zone_locations() as $loc ) {
			if ( 'country' === $loc->type && ! $dest['country'] ) {
				$dest['country'] = $loc->code;
			} elseif ( 'state' === $loc->type ) {
				$parts           = explode( ':', $loc->code );
				$dest['country'] = $parts[0];
				$dest['state']   = isset( $parts[1] ) ? $parts[1] : '';
			} elseif ( 'city' === $loc->type && ! $dest['city'] ) {
				$dest['city'] = $loc->code;
			} elseif ( 'postcode' === $loc->type && ! $dest['postcode'] && ! preg_match( '/[^0-9]/', $loc->code ) ) {
				$dest['postcode'] = $loc->code;
			}
		}
		if ( ! $dest['country'] ) {
			$dest['country'] = WC()->countries->get_base_country(); // "Everywhere else" has no locations
		}
		return $dest;
	}

	/**
	 * Fresh nonce for the product-page script. Deliberately unguarded: needing a
	 * nonce to get a nonce defeats the purpose, and it reveals nothing a page
	 * load does not. Cached pages outlive their nonces (WP Rocket).
	 */
	public function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE ) ) );
	}

	public function ajax_estimate() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'bad_nonce' ), 403 );
		}
		$zid   = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : -1; // phpcs:ignore
		$zones = $this->zones_with_rules();
		if ( ! isset( $zones[ $zid ] ) ) {
			wp_send_json_error( 'Unknown area.', 400 );
		}
		$pid     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0; // phpcs:ignore
		$product = $pid ? wc_get_product( $pid ) : null;
		$zone    = new WC_Shipping_Zone( $zid );
		$package = $this->pseudo_package( $product instanceof WC_Product ? $product : null, $zone );
		$rows    = '';
		foreach ( $zone->get_shipping_methods( true ) as $m ) {
			$rule = $this->rule( $zid, $m->get_instance_id() );
			if ( ! $rule ) {
				continue;
			}
			$days = $this->method_estimate( $rule, 'in_stock', null, true );
			foreach ( $this->method_rates( $m, $package ) as $rate ) {
				$cost  = (float) $rate['cost'];
				$price = null === $rate['cost'] ? '' : ( $cost > 0 ? wc_price( $cost ) : 'Free' );
				$rows .= '<li><span class="breo-dlv__m">' . esc_html( $rate['label'] ) . ( $price ? ' <em>' . wp_kses_post( $price ) . '</em>' : '' ) . '</span>'
					. '<span class="breo-dlv__d">' . wp_kses_post( $days ) . '</span></li>';
			}
		}
		wp_send_json_success( $rows ? '<ul class="breo-dlv__list">' . $rows . '</ul>' : '<p>Delivery options for this area are shown at checkout.</p>' );
	}

	private function icon( $name ) {
		$p = array(
			'truck' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
			'box'   => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
		);
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( isset( $p[ $name ] ) ? $p[ $name ] : '' ) . '</svg>';
	}

	/* =====================================================================
	 * Settings page (WooCommerce → Delivery Estimates)
	 * ================================================================== */

	public function admin_menu() {
		add_submenu_page( 'woocommerce', 'Delivery Estimates', 'Delivery Estimates', 'manage_woocommerce', 'breo-delivery', array( $this, 'settings_page' ) );
	}

	public function settings_capability() {
		return 'manage_woocommerce';
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=breo-delivery' ) ) . '">Settings</a>' );
		return $links;
	}

	public function register_setting() {
		register_setting( 'breo_delivery_group', self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize' ) ) );
	}

	public function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$num = function ( $v ) {
			$v = trim( (string) $v );
			return '' === $v ? '' : absint( $v );
		};
		$out = array(
			'default_message'    => isset( $in['default_message'] ) ? sanitize_text_field( wp_unslash( $in['default_message'] ) ) : '',
			'backorder_min_days' => $num( isset( $in['backorder_min_days'] ) ? $in['backorder_min_days'] : '' ),
			'backorder_max_days' => $num( isset( $in['backorder_max_days'] ) ? $in['backorder_max_days'] : '' ),
			'backorder_label'    => isset( $in['backorder_label'] ) ? sanitize_text_field( wp_unslash( $in['backorder_label'] ) ) : '',
			'split'              => ( isset( $in['split'] ) && 'yes' === $in['split'] ) ? 'yes' : 'no',
			'holidays'           => array(),
		);
		foreach ( (array) ( isset( $in['holidays'] ) ? $in['holidays'] : array() ) as $h ) {
			$s = isset( $h['start'] ) ? sanitize_text_field( $h['start'] ) : '';
			$e = isset( $h['end'] ) ? sanitize_text_field( $h['end'] ) : '';
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
				continue;
			}
			$e = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $e ) ? $e : $s;
			$out['holidays'][] = $e < $s ? array( 'start' => $e, 'end' => $s ) : array( 'start' => $s, 'end' => $e );
		}
		foreach ( $in as $key => $v ) {
			if ( ! is_array( $v ) || ! preg_match( '/^\d+:\d+$/', (string) $key ) ) {
				continue;
			}
			$cut         = isset( $v['cutoff'] ) ? sanitize_text_field( $v['cutoff'] ) : '';
			$out[ $key ] = array(
				'min_days'       => $num( isset( $v['min_days'] ) ? $v['min_days'] : '' ),
				'max_days'       => $num( isset( $v['max_days'] ) ? $v['max_days'] : '' ),
				'cutoff'         => preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $cut ) ? $cut : '',
				'pickup'         => empty( $v['pickup'] ) ? '0' : '1',
				'pickup_message' => isset( $v['pickup_message'] ) ? sanitize_text_field( wp_unslash( $v['pickup_message'] ) ) : '',
			);
		}
		self::$rules = null;
		self::$zones = null;
		return $out;
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$r     = $this->rules();
		$n     = self::OPTION;
		$zones = $this->all_zones();
		$split = ! isset( $r['split'] ) || 'no' !== $r['split'];
		$val   = function ( $k ) use ( $r ) {
			return isset( $r[ $k ] ) ? (string) $r[ $k ] : '';
		};
		?>
		<style data-no-optimize="1" data-no-minify="1">
		.bda{max-width:980px;margin:18px 20px 0 0;color:#1c1c1c}
		.bda *{box-sizing:border-box}
		.bda-hero{background:#1b1a1d radial-gradient(120% 140% at 100% 0,rgba(232,105,44,.3),transparent 55%);color:#d9d2c8;border-radius:18px;padding:26px 28px}
		.bda-hero h1{color:#fff;margin:0 0 6px;font-size:24px;font-weight:600;padding:0;line-height:1.25}
		.bda-hero p{margin:0;max-width:700px;font-size:13.5px;line-height:1.6}
		.bda-card{background:#fff;border:1px solid #e8e2da;border-radius:18px;padding:22px 24px;margin-top:16px}
		.bda-card>h2{margin:0 0 4px;font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px;padding:0}
		.bda-card>h2 .dashicons{color:#e8692c}
		.bda-card>.sub{margin:0 0 16px;color:#6f6a63;font-size:13px;line-height:1.55}
		.bda-row{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end}
		.bda-f{display:flex;flex-direction:column;gap:5px}
		.bda-f>label{font-size:11px;font-weight:600;color:#6f6a63;text-transform:uppercase;letter-spacing:.08em}
		.bda input[type=number],.bda input[type=text],.bda input[type=date],.bda input[type=time]{padding:7px 12px;border:1px solid #d9d1c6;border-radius:10px;font-size:14px;line-height:1.3;min-height:38px;background:#fff;box-shadow:none}
		.bda input[type=number]{width:86px}
		.bda input:focus{border-color:#1c1c1c;box-shadow:0 0 0 3px rgba(28,28,28,.08);outline:none}
		.bda .dash{align-self:center;color:#a8a29a;padding-top:18px}
		.bda-chip{display:inline-flex;align-items:center;gap:6px;background:#f7f2eb;border:1px solid #e8e2da;color:#1c1c1c;font-weight:600;padding:5px 12px;border-radius:999px;font-size:13px}
		.bda-prev{margin-top:14px;font-size:13px;color:#6f6a63;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
		.bda-zone{border:1px solid #e8e2da;border-radius:14px;margin-bottom:12px;overflow:hidden}
		.bda-zone>summary{background:#f7f2eb;padding:12px 16px;font-weight:600;display:flex;align-items:center;gap:8px;cursor:pointer;list-style:none}
		.bda-zone>summary::-webkit-details-marker{display:none}
		.bda-zone>summary::before{content:"\25B8";color:#a8a29a;transition:transform .15s}
		.bda-zone[open]>summary::before{transform:rotate(90deg)}
		.bda-zone .loc{font-weight:400;color:#6f6a63;font-size:12.5px}
		.bda-m{padding:14px 16px;border-top:1px solid #efe9e1}
		.bda-m .mname{font-weight:600;margin:0 0 10px}
		.bda-m .mname small{font-weight:400;color:#a8a29a;margin-left:6px}
		.bda-pick{margin-top:12px;display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer}
		.bda-pmsg{margin:10px 0 0;max-width:340px}
		.bda-help{color:#6f6a63;font-size:12.5px;margin:12px 0 0;line-height:1.6}
		.bda-help code{background:#f3eee7;padding:1px 6px;border-radius:5px}
		.bda-hol{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap}
		.bda-hol .dash{padding-top:0}
		.bda-add{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px dashed #cfc5b8;color:#1c1c1c;padding:8px 16px;border-radius:999px;cursor:pointer;font-size:13px;font-weight:600}
		.bda-add:hover{background:#f7f2eb}
		.bda-rm{background:#fff;border:1px solid #ecd0cc;color:#b3261e;border-radius:999px;padding:6px 12px;cursor:pointer;font-size:12.5px}
		.bda-switch{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.5;cursor:pointer;margin-top:16px;max-width:720px}
		.bda-switch input{margin-top:3px}
		.bda-empty{padding:14px 16px;border-radius:12px;background:#fdf6ee;color:#7a4a1c}
		.bda-save{margin:20px 0 30px}
		.bda-save .button-primary{background:#1c1c1c;border-color:#1c1c1c;border-radius:999px;padding:6px 26px;height:auto;font-size:14px}
		.bda-save .button-primary:hover,.bda-save .button-primary:focus{background:#000;border-color:#000}
		@media(max-width:600px){.bda input[type=text]{width:100%}}
		</style>
		<div class="wrap bda">
			<div class="bda-hero">
				<h1>Delivery estimates</h1>
				<p>Real delivery dates for each shipping method, worked out from its transit days and order cut-off, skipping Fridays and the holidays below. They appear on product pages, under each option in the cart and checkout, on the order confirmation and in order emails.</p>
			</div>
			<form action="options.php" method="post">
				<?php settings_fields( 'breo_delivery_group' ); ?>

				<div class="bda-card">
					<h2><span class="dashicons dashicons-location-alt"></span> Shipping methods</h2>
					<p class="sub">Working days from order to doorstep for each method. Leave the days blank to show no estimate for that method. After the cut-off time, counting starts the next working day.</p>
					<?php if ( ! $zones ) : ?>
						<p class="bda-empty">No shipping zones yet. Add them in <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>">WooCommerce → Settings → Shipping</a> (for example “Inside Dhaka” and “Outside Dhaka”), then come back here.</p>
					<?php endif; ?>
					<?php foreach ( $zones as $zid => $z ) : ?>
						<details class="bda-zone" open>
							<summary><?php echo esc_html( $z['name'] ); ?> <span class="loc"><?php echo esc_html( $z['where'] ); ?></span></summary>
							<?php if ( ! $z['methods'] ) : ?>
								<div class="bda-m" style="color:#6f6a63">No shipping methods in this zone.</div>
							<?php endif; ?>
							<?php
							foreach ( $z['methods'] as $iid => $m ) :
								$key  = $zid . ':' . $m->get_instance_id();
								$cur  = ( isset( $r[ $key ] ) && is_array( $r[ $key ] ) ) ? $r[ $key ] : array();
								$pick = ! empty( $cur['pickup'] ) && '1' === (string) $cur['pickup'];
								$live = $this->rule( $zid, $m->get_instance_id() );
								$f    = $n . '[' . $key . ']';
								?>
								<div class="bda-m">
									<p class="mname"><?php echo esc_html( $m->get_title() ); ?><?php echo $m->is_enabled() ? '' : ' <small>(disabled)</small>'; ?></p>
									<div class="bda-row">
										<div class="bda-f"><label>Min days</label><input type="number" min="0" name="<?php echo esc_attr( $f ); ?>[min_days]" value="<?php echo esc_attr( isset( $cur['min_days'] ) ? $cur['min_days'] : '' ); ?>" placeholder="1"></div>
										<span class="dash">–</span>
										<div class="bda-f"><label>Max days</label><input type="number" min="0" name="<?php echo esc_attr( $f ); ?>[max_days]" value="<?php echo esc_attr( isset( $cur['max_days'] ) ? $cur['max_days'] : '' ); ?>" placeholder="2"></div>
										<div class="bda-f"><label>Order cut-off</label><input type="time" name="<?php echo esc_attr( $f ); ?>[cutoff]" value="<?php echo esc_attr( isset( $cur['cutoff'] ) ? $cur['cutoff'] : '' ); ?>"></div>
									</div>
									<label class="bda-pick"><input type="checkbox" class="bda-pickcb" name="<?php echo esc_attr( $f ); ?>[pickup]" value="1" <?php checked( $pick ); ?>> This is a store pickup option</label>
									<div class="bda-f bda-pmsg"<?php echo $pick ? '' : ' hidden'; ?>><label>Pickup message</label><input type="text" name="<?php echo esc_attr( $f ); ?>[pickup_message]" value="<?php echo esc_attr( isset( $cur['pickup_message'] ) ? $cur['pickup_message'] : '' ); ?>" placeholder="Ready for pickup"></div>
									<?php if ( $live ) : ?>
										<p class="bda-prev">Customers see today: <span class="bda-chip"><?php echo esc_html( $this->method_estimate( $live ) ); ?></span></p>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</details>
					<?php endforeach; ?>
				</div>

				<div class="bda-card">
					<h2><span class="dashicons dashicons-products"></span> Product page</h2>
					<p class="sub">The Delivery card under the Buy buttons shows this message, and customers can pick their area to see exact dates for each method.</p>
					<div class="bda-f" style="max-width:560px"><label for="bda-msg">Message before an area is chosen</label>
						<input id="bda-msg" type="text" name="<?php echo esc_attr( $n ); ?>[default_message]" value="<?php echo esc_attr( $val( 'default_message' ) ); ?>" placeholder="<?php echo esc_attr( $this->suggested_message() ); ?>"></div>
					<p class="bda-help">Leave blank to use the placeholder text, which is built from the delivery days in <em>Tools → Breo BD Setup</em>.</p>
				</div>

				<div class="bda-card">
					<h2><span class="dashicons dashicons-clock"></span> Backorder lead time</h2>
					<p class="sub">How long a backordered product takes to be ready to ship. This is the default; set a different time for one product in its <em>Backorder delivery</em> box on the product editor. At checkout the method's transit days are added to give a real date.</p>
					<div class="bda-row">
						<div class="bda-f"><label for="bda-bo-min">Min days</label><input type="number" min="0" id="bda-bo-min" name="<?php echo esc_attr( $n ); ?>[backorder_min_days]" value="<?php echo esc_attr( $val( 'backorder_min_days' ) ); ?>" placeholder="<?php echo (int) self::BO_MIN; ?>"></div>
						<span class="dash">–</span>
						<div class="bda-f"><label for="bda-bo-max">Max days</label><input type="number" min="0" id="bda-bo-max" name="<?php echo esc_attr( $n ); ?>[backorder_max_days]" value="<?php echo esc_attr( $val( 'backorder_max_days' ) ); ?>" placeholder="<?php echo (int) self::BO_MAX; ?>"></div>
						<div class="bda-f" style="flex:1;min-width:220px"><label for="bda-bo-label">Custom wording (optional)</label><input type="text" id="bda-bo-label" name="<?php echo esc_attr( $n ); ?>[backorder_label]" value="<?php echo esc_attr( $val( 'backorder_label' ) ); ?>" placeholder="automatic, e.g. 1–2 weeks"></div>
					</div>
					<p class="bda-prev">Product page shows: <span class="bda-chip" id="bda-bo-prev">…</span></p>
					<label class="bda-switch"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[split]" value="yes" <?php checked( $split ); ?>>
						<span><strong>Ship backorder items separately.</strong> When a cart mixes in-stock and backorder items, checkout shows two shipments with their own dates, and each shipment is charged its own delivery fee. When this is off, the whole order ships once everything is ready.</span></label>
				</div>

				<div class="bda-card">
					<h2><span class="dashicons dashicons-calendar-alt"></span> Holidays and closed days</h2>
					<p class="sub">No dispatch or delivery on these dates (Eid, Puja, national holidays). Fridays are always skipped.</p>
					<div id="bda-hols">
						<?php
						$hols = ! empty( $r['holidays'] ) && is_array( $r['holidays'] ) ? array_values( $r['holidays'] ) : array( array( 'start' => '', 'end' => '' ) );
						foreach ( $hols as $i => $h ) :
							?>
							<div class="bda-hol">
								<input type="date" name="<?php echo esc_attr( $n . '[holidays][' . $i . '][start]' ); ?>" value="<?php echo esc_attr( isset( $h['start'] ) ? $h['start'] : '' ); ?>" aria-label="From">
								<span class="dash">→</span>
								<input type="date" name="<?php echo esc_attr( $n . '[holidays][' . $i . '][end]' ); ?>" value="<?php echo esc_attr( isset( $h['end'] ) ? $h['end'] : '' ); ?>" aria-label="To">
								<button type="button" class="bda-rm">Remove</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="bda-add" id="bda-add">+ Add closed period</button>
				</div>

				<div class="bda-save"><?php submit_button( 'Save delivery settings', 'primary', 'submit', false ); ?></div>
			</form>
		</div>
		<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" data-no-defer="1">
		(function () {
			var NAME = <?php echo wp_json_encode( $n ); ?>;
			document.querySelectorAll('.bda-pickcb').forEach(function (cb) {
				var box = cb.closest('.bda-m').querySelector('.bda-pmsg');
				cb.addEventListener('change', function () { box.hidden = !cb.checked; });
			});
			var wrap = document.getElementById('bda-hols');
			document.getElementById('bda-add').addEventListener('click', function () {
				var i = Date.now(), d = document.createElement('div');
				d.className = 'bda-hol';
				d.innerHTML = '<input type="date" name="' + NAME + '[holidays][' + i + '][start]" aria-label="From"> <span class="dash">→</span> ' +
					'<input type="date" name="' + NAME + '[holidays][' + i + '][end]" aria-label="To"> <button type="button" class="bda-rm">Remove</button>';
				wrap.appendChild(d);
			});
			wrap.addEventListener('click', function (e) {
				if (e.target.classList.contains('bda-rm')) e.target.closest('.bda-hol').remove();
			});
			// Mirrors days_label() in PHP.
			function label(a, b) {
				if (b <= 0) return '1–2 weeks';
				if (a % 7 === 0 && b % 7 === 0) { var x = a / 7, y = b / 7; return (x === y || x === 0) ? y + ' ' + (y === 1 ? 'week' : 'weeks') : x + '–' + y + ' weeks'; }
				return (a === b || a === 0) ? b + ' ' + (b === 1 ? 'day' : 'days') : a + '–' + b + ' days';
			}
			var mn = document.getElementById('bda-bo-min'), mx = document.getElementById('bda-bo-max'), lb = document.getElementById('bda-bo-label'), out = document.getElementById('bda-bo-prev');
			function prev() {
				var c = lb.value.trim();
				var a = mn.value === '' ? <?php echo (int) self::BO_MIN; ?> : parseInt(mn.value, 10), b = mx.value === '' ? <?php echo (int) self::BO_MAX; ?> : parseInt(mx.value, 10);
				if (isNaN(a)) a = <?php echo (int) self::BO_MIN; ?>; if (isNaN(b)) b = <?php echo (int) self::BO_MAX; ?>; if (b < a) b = a;
				out.textContent = 'Pre-order · ships in ' + (c || label(a, b));
			}
			[mn, mx, lb].forEach(function (el) { el.addEventListener('input', prev); });
			prev();
		})();
		</script>
		<?php
	}

	/* =====================================================================
	 * Per-product backorder box (product editor sidebar)
	 * ================================================================== */

	public function add_metabox() {
		add_meta_box( 'breo_backorder_box', 'Backorder delivery', array( $this, 'render_metabox' ), 'product', 'side', 'default' );
	}

	public function render_metabox( $post ) {
		$get     = function ( $k ) use ( $post ) {
			return (string) get_post_meta( $post->ID, '_breo_backorder_' . $k, true );
		};
		$g       = $this->leadtime();
		$product = wc_get_product( $post->ID );
		$now     = $product instanceof WC_Product ? $this->leadtime( $product ) : $g;
		$cd      = '1' === $get( 'countdown' );
		$anchor  = $get( 'anchor' );
		wp_nonce_field( 'breo_save_backorder', 'breo_backorder_nonce' );
		?>
		<style data-no-optimize="1" data-no-minify="1">
		#breo_backorder_box .bbo-note{margin:0 0 12px;color:#50575e;line-height:1.5;font-size:12.5px}
		#breo_backorder_box .bbo-days{display:flex;align-items:flex-end;gap:8px;margin-bottom:12px}
		#breo_backorder_box label.f{display:flex;flex-direction:column;gap:4px;font-weight:600;color:#6f6a63;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em}
		#breo_backorder_box input[type=number],#breo_backorder_box input[type=text]{padding:6px 9px;border:1px solid #d9d1c6;border-radius:8px;font-size:13px;width:100%}
		#breo_backorder_box .bbo-days input[type=number]{width:72px}
		#breo_backorder_box .bbo-full{display:block;margin-bottom:12px}
		#breo_backorder_box .bbo-cd{display:flex;gap:8px;align-items:flex-start;background:#fdf6ee;border:1px solid #f1dcc4;border-radius:9px;padding:9px 11px;margin-bottom:10px;font-size:12px;line-height:1.45;color:#5b4326;cursor:pointer}
		#breo_backorder_box .bbo-cd input{margin-top:2px}
		#breo_backorder_box .bbo-anchor{margin:0 0 12px;font-size:12px;color:#50575e;background:#f6f7f7;border-radius:8px;padding:9px 11px}
		#breo_backorder_box .bbo-anchor label{display:flex;gap:6px;align-items:center;margin-top:8px;color:#1d2327;font-size:12px;cursor:pointer}
		#breo_backorder_box .bbo-prev{background:#f7f2eb;border-radius:9px;padding:10px 12px;color:#1c1c1c}
		#breo_backorder_box .bbo-prev .l{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#6f6a63;margin-bottom:3px}
		#breo_backorder_box .bbo-chip{font-weight:600;font-size:13.5px}
		</style>
		<p class="bbo-note">Used only while this product is <strong>on backorder</strong>. Leave blank for the default (<?php echo esc_html( $g['label'] ); ?>).</p>
		<div class="bbo-days">
			<label class="f">Min days <input type="number" min="0" id="bbo-min" name="_breo_backorder_min_days" value="<?php echo esc_attr( $get( 'min_days' ) ); ?>" placeholder="<?php echo (int) $g['min']; ?>"></label>
			<span style="padding-bottom:7px;color:#a8a29a">–</span>
			<label class="f">Max days <input type="number" min="0" id="bbo-max" name="_breo_backorder_max_days" value="<?php echo esc_attr( $get( 'max_days' ) ); ?>" placeholder="<?php echo (int) $g['max']; ?>"></label>
		</div>
		<label class="f bbo-full">Custom wording (optional) <input type="text" id="bbo-label" name="_breo_backorder_label" value="<?php echo esc_attr( $get( 'label' ) ); ?>" placeholder="automatic"></label>
		<label class="bbo-cd"><input type="checkbox" id="bbo-cd" name="_breo_backorder_countdown" value="1" <?php checked( $cd ); ?>>
			<span><strong>Live countdown:</strong> the time shrinks every day, then shows “arriving any day now”. It resets to the default when the product is back in stock.</span></label>
		<?php if ( $cd && $anchor ) : ?>
			<div class="bbo-anchor">Counting down since <strong><?php echo esc_html( $anchor ); ?></strong>.
				<label><input type="checkbox" name="_breo_backorder_restart" value="1"> Restart from today when saving</label></div>
		<?php endif; ?>
		<div class="bbo-prev"><span class="l">Customers see</span><span class="bbo-chip" id="bbo-prev"><?php echo esc_html( $now['imminent'] ? 'arriving any day now' : 'ships in ' . $now['label'] ); ?></span></div>
		<p style="margin:10px 0 0;font-size:12px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=breo-delivery' ) ); ?>" target="_blank">Edit the default →</a></p>
		<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" data-no-defer="1">
		(function () {
			var G = { min: <?php echo (int) $g['min']; ?>, max: <?php echo (int) $g['max']; ?>, label: <?php echo wp_json_encode( $g['label'] ); ?> };
			function label(a, b) {
				if (b <= 0) return '1–2 weeks';
				if (a % 7 === 0 && b % 7 === 0) { var x = a / 7, y = b / 7; return (x === y || x === 0) ? y + ' ' + (y === 1 ? 'week' : 'weeks') : x + '–' + y + ' weeks'; }
				return (a === b || a === 0) ? b + ' ' + (b === 1 ? 'day' : 'days') : a + '–' + b + ' days';
			}
			function el(id) { return document.getElementById(id); }
			function upd() {
				var c = el('bbo-label').value.trim(), a = el('bbo-min').value, b = el('bbo-max').value, t;
				if (c) t = c;
				else if (a === '' && b === '') t = G.label + ' (default)';
				else { a = a === '' ? G.min : parseInt(a, 10); b = b === '' ? G.max : parseInt(b, 10); if (b < a) b = a; t = label(a, b); }
				el('bbo-prev').textContent = 'ships in ' + t + (el('bbo-cd').checked ? ' · counts down daily' : '');
			}
			['bbo-min', 'bbo-max', 'bbo-label', 'bbo-cd'].forEach(function (id) { el(id).addEventListener('input', upd); el(id).addEventListener('change', upd); });
		})();
		</script>
		<?php
	}

	public function save_metabox( $post_id ) {
		// Only when this box was submitted: quick edit / REST saves must not wipe the values.
		if ( ! isset( $_POST['breo_backorder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['breo_backorder_nonce'] ) ), 'breo_save_backorder' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in   = function ( $k ) {
			return isset( $_POST[ '_breo_backorder_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ '_breo_backorder_' . $k ] ) ) : ''; // phpcs:ignore
		};
		$min  = $in( 'min_days' );
		$max  = $in( 'max_days' );
		$prev = array(
			'min'    => (string) get_post_meta( $post_id, '_breo_backorder_min_days', true ),
			'max'    => (string) get_post_meta( $post_id, '_breo_backorder_max_days', true ),
			'cd'     => (string) get_post_meta( $post_id, '_breo_backorder_countdown', true ),
			'anchor' => (string) get_post_meta( $post_id, '_breo_backorder_anchor', true ),
		);
		foreach ( array( 'min_days' => $min, 'max_days' => $max ) as $k => $v ) {
			if ( '' === $v || ! is_numeric( $v ) ) {
				delete_post_meta( $post_id, '_breo_backorder_' . $k );
			} else {
				update_post_meta( $post_id, '_breo_backorder_' . $k, max( 0, (int) $v ) );
			}
		}
		$label = $in( 'label' );
		if ( '' === $label ) {
			delete_post_meta( $post_id, '_breo_backorder_label' );
		} else {
			update_post_meta( $post_id, '_breo_backorder_label', $label );
		}

		// Countdown: re-anchor to today when freshly enabled, when the days changed, or on "restart".
		if ( isset( $_POST['_breo_backorder_countdown'] ) ) {
			$changed = (string) $min !== $prev['min'] || (string) $max !== $prev['max'];
			$again   = isset( $_POST['_breo_backorder_restart'] ) || '1' !== $prev['cd'] || $changed || '' === $prev['anchor'];
			update_post_meta( $post_id, '_breo_backorder_countdown', '1' );
			update_post_meta( $post_id, '_breo_backorder_anchor', $again ? current_time( 'Y-m-d' ) : $prev['anchor'] );
			$prod = wc_get_product( $post_id );
			if ( $prod ) {
				update_post_meta( $post_id, '_breo_backorder_last_status', $prod->get_stock_status() );
			}
		} else {
			delete_post_meta( $post_id, '_breo_backorder_countdown' );
			delete_post_meta( $post_id, '_breo_backorder_anchor' );
			delete_post_meta( $post_id, '_breo_backorder_last_status' );
		}
	}

	/* =====================================================================
	 * Countdown reset when a product leaves backorder
	 * ================================================================== */

	public function stock_changed( $product_id ) {
		$this->sync_spell( (int) $product_id );
	}

	public function props_updated( $product, $props ) {
		if ( $product instanceof WC_Product && is_array( $props ) && in_array( 'stock_status', $props, true ) ) {
			$this->sync_spell( $product->get_id() );
		}
	}

	/**
	 * A per-product countdown covers ONE backorder spell. When the product
	 * leaves backorder, clear its per-product ETA so it falls back to the
	 * default until a new one is set. _last_status guards against re-runs.
	 */
	private function sync_spell( $product_id ) {
		if ( $product_id <= 0 || '1' !== get_post_meta( $product_id, '_breo_backorder_countdown', true ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		$now = $product->get_stock_status();
		if ( (string) get_post_meta( $product_id, '_breo_backorder_last_status', true ) === $now ) {
			return;
		}
		if ( 'onbackorder' !== $now ) {
			foreach ( self::BO_META as $key ) {
				delete_post_meta( $product_id, $key );
			}
			return;
		}
		update_post_meta( $product_id, '_breo_backorder_last_status', $now );
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) {
		new Breo_Smart_Delivery();
	}
}, 20 );
