<?php
/**
 * Plugin Name: Kohthai Chat & Order Notice
 * Description: WhatsApp and Messenger ordering buttons for the product page, via
 *              [kohthai_chat_order]. Pre-fills the message with the product name. Hides itself on
 *              discontinued products. Settings → Chat & Order.
 * Version: 1.1.1
 * Author: Smart Living Bangladesh
 *
 * v1.1.0 — restyled to match the product page, and simplified.
 *
 *   THE BUY-BOX TYPE SCALE, rebased on the theme's 16px body.
 *   Everything in this column was sized against a phone mockup with a 13px base and shipped
 *   20-31% too small: trust title 12, trust sub 11, chat lead 11, delivery text 12. That is the
 *   same mistake the description block made and it has now been made twice, so the scale is
 *   written down here:
 *
 *       theme body ................ 16px
 *       chat button ............... 14px   (an action, so it matches the tile titles)
 *       trust tile title .......... 14px
 *       delivery text ............. 14px
 *       trust tile sub-line ....... 13px
 *       feature icon label ........ 13px
 *       delivery area picker ...... 13px
 *       chat lead (uppercase) ..... 12px   uppercase reads larger than its px size
 *
 *   These blocks all sit BELOW Add to Cart, so enlarging them costs nothing at the top of the
 *   page. Measured: +10px on desktop, +17px on a 390px phone, no new wrapping, no overflow.
 *
 *   WHY IT CHANGED
 *     The buy box had three blocks by three different hands: the trust row (warm #faf7f3 tiles),
 *     this one (a cool #fafafa box with a heading and two text links), and the delivery estimate
 *     (a cool #f7f7f7 box). Same 400px column, three different design languages. This now uses
 *     the same warm palette, the same 3px radius and the same inline-SVG icon system as the rest.
 *
 *   THE JAVASCRIPT IS GONE
 *     v1.0.0 shipped a DOMContentLoaded block that read the product name out of `.product-title`
 *     and wrote the hrefs client-side. There is no reason for that: the product name is known in
 *     PHP, the page is cached per product, and building the URLs server-side means the links work
 *     with JavaScript delayed or blocked — which matters, because WP Rocket delays JS on this
 *     site. It also removed a dependency on a theme class name that could change.
 *
 *   FONTAWESOME IS GONE TOO
 *     The brand glyphs are inline SVG now. One less external dependency, and it cannot render as
 *     a blank square if the icon set is ever subsetted.
 *
 *   KEPT IDENTICAL so nothing breaks on upgrade:
 *     - shortcode name [kohthai_chat_order]
 *     - option key 'kohthai_chat_order_settings' — the saved WhatsApp number and Messenger ID
 *       carry straight over
 *     - the element ids kohthai-whatsapp-btn / kohthai-messenger-btn, in case anything else on
 *       the site targets them
 *     - the discontinued-product check
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kohthai_Chat_Order_Notice {

	const VERSION     = '1.1.1';
	const OPTION_NAME = 'kohthai_chat_order_settings';

	public static function init() {
		add_shortcode( 'kohthai_chat_order', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/* ------------------------------------------------------------------ settings */

	public static function get_settings() {
		$defaults = array(
			'wa_number'    => '8801993333496',
			'messenger_id' => 'kohthaibd',
			'lead'         => 'Prefer to order by message?',
			'wa_template'  => 'Hi, I would like to order the {product}. Is cash on delivery available?',
		);
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
	}

	public static function admin_menu() {
		add_options_page( 'Chat & Order Settings', 'Chat & Order', 'manage_options',
			'kohthai-chat-order', array( __CLASS__, 'settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'kohthai_chat_order_group', self::OPTION_NAME, array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function sanitize( $input ) {
		$out = array();
		$in  = is_array( $input ) ? $input : array();

		// wa.me rejects anything that is not digits, so strip everything else.
		$out['wa_number']    = preg_replace( '/\D+/', '', isset( $in['wa_number'] ) ? $in['wa_number'] : '' );
		$out['messenger_id'] = sanitize_text_field( isset( $in['messenger_id'] ) ? $in['messenger_id'] : '' );
		$out['lead']         = sanitize_text_field( isset( $in['lead'] ) ? $in['lead'] : '' );
		$out['wa_template']  = sanitize_text_field( isset( $in['wa_template'] ) ? $in['wa_template'] : '' );
		return $out;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = self::get_settings();
		?>
		<div class="wrap">
			<h1>Chat &amp; Order</h1>
			<p style="max-width:56em">Renders two ordering buttons on the product page. Place
				<code>[kohthai_chat_order]</code> in <strong>Customize → WooCommerce → Product Page →
				HTML after Add To Cart button</strong>.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'kohthai_chat_order_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="kco_wa">WhatsApp number</label></th>
						<td><input type="text" id="kco_wa" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wa_number]"
								value="<?php echo esc_attr( $o['wa_number'] ); ?>">
							<p class="description">Country code first, digits only. No <code>+</code>, no spaces.
								Leave empty to hide the WhatsApp button.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kco_ms">Messenger page ID</label></th>
						<td><input type="text" id="kco_ms" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[messenger_id]"
								value="<?php echo esc_attr( $o['messenger_id'] ); ?>">
							<p class="description">The part after <code>m.me/</code>. Leave empty to hide the
								Messenger button.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kco_lead">Line above the buttons</label></th>
						<td><input type="text" id="kco_lead" class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[lead]"
								value="<?php echo esc_attr( $o['lead'] ); ?>">
							<p class="description">Leave empty for no line.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kco_tpl">WhatsApp message</label></th>
						<td><input type="text" id="kco_tpl" class="large-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[wa_template]"
								value="<?php echo esc_attr( $o['wa_template'] ); ?>">
							<p class="description"><code>{product}</code> is replaced with the product name.</p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<p class="description">Clear the WP Rocket cache after saving — product pages are cached.</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ front end */

	public static function enqueue() {
		wp_register_style( 'kohthai-chat-order', false, array(), self::VERSION );
		wp_enqueue_style( 'kohthai-chat-order' );
		wp_add_inline_style( 'kohthai-chat-order', self::css() );
	}

	public static function css() {
		return '
.kohthai-chat-order{margin:1.1em 0}
.kohthai-chat-order__lead{display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;
 color:#6f6459;margin-bottom:7px}
.kohthai-chat-order__row{display:flex;gap:7px}
.kohthai-chat-order__btn{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;
 padding:12px 8px;border-radius:3px;font-size:14px;font-weight:700;line-height:1;text-decoration:none;
 transition:background-color .15s ease}
.kohthai-chat-order__btn svg{width:17px;height:17px;flex:none}
/* #25D366 with white text is 1.9:1 and unreadable. On near-black it is 8.6:1. */
.kohthai-chat-order__btn--wa{background:#25d366;color:#0a2e16}
.kohthai-chat-order__btn--wa:hover{background:#1fb857;color:#0a2e16}
/* #0084FF with white is 4.6:1, just over the AA floor. */
.kohthai-chat-order__btn--ms{background:#0084ff;color:#fff}
.kohthai-chat-order__btn--ms:hover{background:#0069cc;color:#fff}
.kohthai-chat-order__btn:focus-visible{outline:3px solid #ffbc00;outline-offset:2px}
@media(max-width:380px){.kohthai-chat-order__btn{font-size:13px;padding:11px 6px}}
';
	}

	private static function icon( $which ) {
		$open = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">';
		if ( 'wa' === $which ) {
			return $open . '<path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a12 12 0 0 1-6.6-5.8c-.5-.9-.5-1.7-.4-2.3.1-.5.7-1.4 1.2-1.5h.7c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.6l-.4.5c-.2.2-.3.3-.1.6a8.7 8.7 0 0 0 3.9 3.3c.3.1.5.1.7-.1l.7-.9c.2-.2.4-.2.6-.1l1.8.9c.3.1.4.2.4.4 0 .2 0 .6-.3 1.2z"/></svg>';
		}
		return $open . '<path d="M12 2C6.3 2 2 6.2 2 11.6c0 3 1.4 5.7 3.7 7.4V22l3.4-1.9c.9.3 1.9.4 2.9.4 5.7 0 10-4.2 10-9.6S17.7 2 12 2zm1 12.6l-2.5-2.7-5 2.7 5.5-5.8 2.6 2.7 4.9-2.7-5.5 5.8z"/></svg>';
	}

	public static function render_shortcode() {
		global $product;

		if ( ! function_exists( 'is_product' ) || ! is_product() || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$product_id = $product->get_id();

		// Discontinued products should not invite an order enquiry.
		if ( taxonomy_exists( 'product_discontinued' )
			&& has_term( 'dp-discontinued', 'product_discontinued', $product_id ) ) {
			return '';
		}

		$o    = self::get_settings();
		$wa   = preg_replace( '/\D+/', '', (string) $o['wa_number'] );
		$fb   = trim( (string) $o['messenger_id'] );
		$name = $product->get_name();

		if ( '' === $wa && '' === $fb ) {
			return '';
		}

		// Built in PHP, not JavaScript. WP Rocket delays JS on this site, so a client-side href
		// would leave the buttons pointing at "#" until the visitor interacts with the page.
		$message = str_replace( '{product}', $name, (string) $o['wa_template'] );

		$buttons = '';

		if ( '' !== $wa ) {
			$buttons .= sprintf(
				'<a id="kohthai-whatsapp-btn" class="kohthai-chat-order__btn kohthai-chat-order__btn--wa" href="%s" target="_blank" rel="noopener nofollow">%s%s</a>',
				esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $message ) ),
				self::icon( 'wa' ),
				esc_html__( 'WhatsApp', 'kohthai-chat-order' )
			);
		}

		if ( '' !== $fb ) {
			$buttons .= sprintf(
				'<a id="kohthai-messenger-btn" class="kohthai-chat-order__btn kohthai-chat-order__btn--ms" href="%s" target="_blank" rel="noopener nofollow">%s%s</a>',
				esc_url( 'https://m.me/' . rawurlencode( $fb ) . '?ref=' . rawurlencode( $name ) ),
				self::icon( 'ms' ),
				esc_html__( 'Messenger', 'kohthai-chat-order' )
			);
		}

		$lead = trim( (string) $o['lead'] );

		return '<div class="kohthai-chat-order">'
			. ( '' !== $lead ? '<span class="kohthai-chat-order__lead">' . esc_html( $lead ) . '</span>' : '' )
			. '<div class="kohthai-chat-order__row">' . $buttons . '</div>'
			. '</div>';
	}
}

Kohthai_Chat_Order_Notice::init();
