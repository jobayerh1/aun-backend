<?php
/**
 * Plugin Name: Kohthai Product Page
 * Description: Rebuilds the WooCommerce product page for Kohthai — permanent colour-swatch names, in-stock line, honest trust row, WhatsApp/Messenger ordering, a sticky mobile buy bar, and fixes for the Flatsome/WP Rocket video gap, the empty-heading flood and the failing Add-to-Cart contrast.
 * Version: 1.0.1
 * Author: Smart Living Bangladesh
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * v1.0.1: WITHDREW two features after the v1.0.0 build broke the live product page.
 *
 *         REMOVED -- strip_empty_markup() on woocommerce_short_description.
 *         It corrupted the document. Measured against the real short description of
 *         the Versatile Soft PU Leather Shoulder Bag:
 *              <style>     8 -> 5   (three --stack-gap blocks deleted)
 *              <div>     152 -> 147 (five opening divs eaten)
 *              </div>    152 -> 139 (thirteen closing divs eaten)
 *              balance     0 -> +8  (eight unclosed divs)
 *         Cause: wpautop leaves <p> tags whose </p> sits on the far side of a <div>
 *         boundary, so a lazy <p>(.*?)</p> match happily spans the div and swallows
 *         it. Flatsome also parks real layout CSS INSIDE <p> tags, e.g.
 *             <p><style>#stack-1431617891 > * { --stack-gap: 1rem; }</style></p>
 *         which the "is this paragraph empty?" test scored as empty.
 *         Eight unclosed divs means the browser re-nests the document: the tab
 *         panels merged into one stream, the icon stacks went vertical, and images
 *         escaped their sized wrappers.
 *         THE LESSON IS NOT "FIX THE REGEX". Flatsome's rendered output is not
 *         safely rewritable. The empty-<h5> problem gets fixed at the SOURCE, in the
 *         shortcode text, or not at all. Do not reintroduce a content filter here.
 *         The method is kept (with its tests) purely as a record; nothing calls it.
 *
 *         REMOVED -- render_chat_row(). Kohthai already has the "Kohthai Chat & Order
 *         Notice" plugin (shortcode [kohthai_chat_order]) doing exactly this. Building
 *         a second one was duplication. Delivery estimates likewise belong to the
 *         "Kohthai Smart Delivery Plugin", which renders at
 *         woocommerce_single_product_summary priority 35 -- do not compete with it.
 *
 *         NOT FOR DEPLOYMENT AS-IS. Being reintroduced one step at a time.
 *
 * v1.0.0: First release. Everything here was written against MEASURED faults on
 *         https://kohthaibd.com/retro-artistic-style-embroidered-crossbody-bag-for-women/
 *         (real browser, computed styles, 1400x900 and 375x812). Numbers in the
 *         comments below are what the live page actually reported, not estimates.
 *
 *         WHAT IT FIXES
 *           1.  Video gap. Flatsome's [ux_video] wraps in .video-fit and reserves a
 *               16:9 box with padding-top, then absolutely positions the IFRAME into
 *               it. WP Rocket's "Replace YouTube iframe with preview image" deletes
 *               that iframe and substitutes <div class="rll-youtube-player">, which
 *               carries its OWN padding-bottom:56.23% and is NOT absolutely
 *               positioned. So Flatsome's box sits empty and Rocket's box stacks
 *               below it. Measured: 978.56px where 489.19px belongs -- exactly
 *               double. We pull Rocket's player into Flatsome's reserved box, which
 *               keeps the reservation (no layout shift) AND keeps Rocket's preview
 *               image (which defers ~500KB-1MB of YouTube JS until someone clicks).
 *
 *           2.  Colour swatch names. The owner uses PHOTO swatches on purpose -- a
 *               flat colour chip promises a shade the bag may not be, and that
 *               causes disputes. But three 45px crops of the same bag are
 *               indistinguishable, and the names lived only in a hover tooltip,
 *               which does not exist on a phone. Flatsome already emits
 *               data-name="Khaki" on each .ux-swatch, so the label is pure CSS
 *               (content: attr(data-name)) and needs no PHP at all.
 *
 *           3.  Empty headings. Writing title="<h5>Foo</h5>" in a [featured_box] is
 *               wrong -- Flatsome ALREADY wraps the title in <h5 class="uppercase">,
 *               so you get an empty h5 followed by yours. Plus every blank line
 *               between shortcodes becomes <p></p> via wpautop. Measured: 18 empty
 *               <h5> and 37 empty <p>, giving a heading outline of H1 followed by
 *               twenty-three H5s. We strip them from the short description at render
 *               time so the fix applies to every existing product without anyone
 *               re-editing 100 products by hand.
 *
 *           4.  Add-to-Cart contrast. It was #a08565 (tan) behind white text =
 *               3.48:1, under the WCAG AA 4.5:1 floor -- and Buy Now was the DARKER
 *               button, so the secondary action read as primary. Add to Cart now
 *               uses #654321 (8.87:1) and Buy Now becomes an outline.
 *
 *           5.  Sticky mobile buy bar. The page is 4,052px tall on a 375px phone
 *               with 18 gallery images; past the buy box there was no way to
 *               purchase without scrolling all the way back.
 *
 *           6.  Stock line, trust row, WhatsApp/Messenger ordering, SKU:N/A hidden,
 *               out-of-stock products pushed to the END of Related Products, and
 *               the feature icon row wrapping instead of squeezing on mobile.
 *
 *         COPY RULE, ENFORCED IN THE DEFAULTS
 *           Kohthai's real policy (kohthaibd.com/returns_refund/) is a CONDITIONAL
 *           7-day return -- damaged, incorrect, incomplete or not as advertised --
 *           and the CUSTOMER pays return shipping. So the default trust text is
 *           "7-Day Return / If damaged or not as described". Never ship a build
 *           that says "no questions asked", "free returns" or "hassle-free": all
 *           three are false here and would invite the exact disputes the owner is
 *           trying to avoid. And Kohthai sells BAGS -- there is no warranty on any
 *           product, so no warranty badge belongs anywhere on this site.
 *
 *         WP ROCKET NOTES
 *           No nonce is ever printed into product HTML. That is deliberate: the
 *           product page is cached, and a nonce baked into cached HTML dies in
 *           12-24h and starts throwing "Security check failed" at real customers
 *           (this is the live bug in aun-alpha-otp-login). This plugin has no
 *           front-end AJAX, so there is nothing to nonce. Inline CSS/JS carries
 *           data-no-optimize / data-no-minify / data-cfasync guards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KT_PDP_VERSION', '1.0.1' );
define( 'KT_PDP_FILE', __FILE__ );
define( 'KT_PDP_DIR', plugin_dir_path( __FILE__ ) );
define( 'KT_PDP_URL', plugin_dir_url( __FILE__ ) );
define( 'KT_PDP_OPTION', 'kt_pdp_options' );

/**
 * Everything the plugin does, in one class.
 */
final class KT_Product_Page {

	/** @var KT_Product_Page|null */
	private static $instance = null;

	/** @var array|null Resolved options, memoised per request. */
	private $options = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * WooCommerce may not be active. Fail visibly in the admin, silently on the front.
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_no_woocommerce' ) );
			return;
		}

		// Admin.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Front-end assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );

		// Registered here rather than inside enqueue(): body_class() is emitted
		// immediately after wp_head(), so a filter added during wp_enqueue_scripts
		// only just makes it. Registering early and testing is_product() inside
		// the callback removes the ordering dependency entirely.
		add_filter( 'body_class', array( $this, 'body_class' ) );

		$o = $this->options();

		// --- content repairs -------------------------------------------------
		if ( $o['enable_sku_hide'] ) {
			add_filter( 'wc_product_sku_enabled', array( $this, 'hide_empty_sku' ) );
		}

		if ( $o['enable_related_sort'] ) {
			add_filter( 'woocommerce_related_products', array( $this, 'sort_related_in_stock_first' ), 20 );
		}

		// --- summary additions ----------------------------------------------
		// WooCommerce default priorities in woocommerce_single_product_summary:
		//   5 title | 10 rating | 10 price | 20 excerpt | 30 add_to_cart | 40 meta | 50 sharing
		if ( $o['enable_stock'] ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_stock_line' ), 25 );
		}
		if ( $o['enable_trust'] ) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_row' ), 31 );
		}

		// --- sticky buy bar --------------------------------------------------
		if ( $o['enable_sticky'] ) {
			add_action( 'wp_footer', array( $this, 'render_sticky_bar' ), 20 );
		}
	}

	public function notice_no_woocommerce() {
		echo '<div class="notice notice-error"><p><strong>Kohthai Product Page</strong> needs WooCommerce to be active. It is doing nothing right now.</p></div>';
	}

	/* =====================================================================
	 * Options
	 * ===================================================================== */

	public static function defaults() {
		return array(
			// Feature toggles — every one can be turned off without touching code.
			'enable_video_fix'     => 1,
			'enable_swatch_names'  => 1,
			'enable_buttons'       => 1,
			'enable_feature_wrap'  => 1,
			'enable_clean_excerpt' => 1,
			'enable_stock'         => 1,
			'enable_trust'         => 1,
			'enable_chat'          => 1,
			'enable_sticky'        => 1,
			'enable_sku_hide'      => 1,
			'enable_related_sort'  => 1,

			// Contact.
			'whatsapp'  => '',
			'messenger' => '',

			// Trust row. Defaults are written to match the real returns policy —
			// see the copy rule in the file header before changing them.
			'trust_1_title' => 'Cash on Delivery',
			'trust_1_sub'   => 'Pay when it arrives',
			'trust_2_title' => '7-Day Return',
			'trust_2_sub'   => 'If damaged or not as described',
			'trust_3_title' => '100% Genuine',
			'trust_3_sub'   => 'Imported stock',
			'returns_url'   => '/returns_refund/',

			// Stock line.
			'stock_text' => 'In stock — ready to ship',
		);
	}

	public function options() {
		if ( null === $this->options ) {
			$saved         = get_option( KT_PDP_OPTION, array() );
			$this->options = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return $this->options;
	}

	private function opt( $key ) {
		$o = $this->options();
		return isset( $o[ $key ] ) ? $o[ $key ] : '';
	}

	/* =====================================================================
	 * Assets
	 * ===================================================================== */

	public function enqueue() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'kt-pdp',
			KT_PDP_URL . 'assets/kt-pdp.css',
			array(),
			KT_PDP_VERSION
		);

		wp_enqueue_script(
			'kt-pdp',
			KT_PDP_URL . 'assets/kt-pdp.js',
			array( 'jquery' ),
			KT_PDP_VERSION,
			true
		);

		wp_localize_script(
			'kt-pdp',
			'ktPdp',
			array(
				'colourLabel' => __( 'Colour', 'kohthai-product-page' ),
				'notPicked'   => __( 'Choose below', 'kohthai-product-page' ),
				'sticky'      => (bool) $this->opt( 'enable_sticky' ),
			)
		);
	}

	/**
	 * Feature toggles become body classes so the stylesheet can stay static and
	 * cacheable rather than being rebuilt per request.
	 */
	public function body_class( $classes ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $classes;
		}

		$map = array(
			'enable_video_fix'    => 'kt-fix-video',
			'enable_swatch_names' => 'kt-swatch-names',
			'enable_buttons'      => 'kt-fix-buttons',
			'enable_feature_wrap' => 'kt-fix-features',
		);
		foreach ( $map as $key => $class ) {
			if ( $this->opt( $key ) ) {
				$classes[] = $class;
			}
		}
		return $classes;
	}

	/* =====================================================================
	 * Content repairs
	 * ===================================================================== */

	/**
	 * Remove the empty headings and paragraphs Flatsome's shortcodes leave behind.
	 *
	 * Two sources, both harmless-looking and both real:
	 *   title="<h5>Foo</h5>"  -> Flatsome wraps title in <h5 class="uppercase">,
	 *                            so an EMPTY h5 is emitted before yours.
	 *   blank lines           -> wpautop turns each into <p></p>.
	 *
	 * Measured on one live product: 18 empty <h5> and 37 empty <p>.
	 *
	 * "Empty" here means: no text once you discount whitespace and &nbsp;, AND no
	 * media inside. An <h5><img></h5> is NOT empty and must survive.
	 */
	public function strip_empty_markup( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}

		// Headings and paragraphs only. Never touch divs — Flatsome's layout lives in them.
		$pattern = '#<(h[1-6]|p)\b[^>]*>(.*?)</\1>#is';

		$out = preg_replace_callback(
			$pattern,
			static function ( $m ) {
				$inner = $m[2];

				// Anything that renders on its own keeps the wrapper alive.
				if ( preg_match( '#<(img|svg|iframe|video|audio|picture|canvas|input|button|a)\b#i', $inner ) ) {
					return $m[0];
				}

				$text = wp_strip_all_tags( $inner );
				$text = str_replace( array( "\xc2\xa0", '&nbsp;' ), ' ', $text );

				return ( '' === trim( $text ) ) ? '' : $m[0];
			},
			$html
		);

		// preg_replace_callback returns null on failure (e.g. backtrack limit on a
		// pathological document). Never hand null back to the template.
		return ( null === $out ) ? $html : $out;
	}

	/**
	 * WooCommerce prints "SKU: N/A" when a product has none. Suppress the whole row.
	 */
	public function hide_empty_sku( $enabled ) {
		global $product;
		if ( $product instanceof WC_Product && '' === (string) $product->get_sku() ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Out-of-stock products should not LEAD the related row — measured: the
	 * out-of-stock "Wave Pattern Small Square Crossbody Bag" was first. Keep them
	 * (they still get discovered and can sell later) but move them to the end.
	 */
	public function sort_related_in_stock_first( $related_ids ) {
		if ( ! is_array( $related_ids ) || count( $related_ids ) < 2 ) {
			return $related_ids;
		}

		$in = array();
		$out = array();

		foreach ( $related_ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p && ! $p->is_in_stock() ) {
				$out[] = $id;
			} else {
				$in[] = $id;
			}
		}

		return array_merge( $in, $out );
	}

	/* =====================================================================
	 * Summary blocks
	 * ===================================================================== */

	public function render_stock_line() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Variable products resolve stock per variation — WooCommerce's own
		// .stock line handles that after selection, so only speak for the parent.
		if ( ! $product->is_in_stock() ) {
			echo '<p class="kt-stock kt-stock--out"><span class="kt-dot"></span>' .
				esc_html__( 'Currently out of stock', 'kohthai-product-page' ) . '</p>';
			return;
		}

		$text = $this->opt( 'stock_text' );
		if ( '' === trim( (string) $text ) ) {
			return;
		}

		echo '<p class="kt-stock"><span class="kt-dot"></span>' . esc_html( $text ) . '</p>';
	}

	public function render_trust_row() {
		$items = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$title = trim( (string) $this->opt( "trust_{$i}_title" ) );
			if ( '' === $title ) {
				continue;
			}
			$items[] = array(
				'title' => $title,
				'sub'   => trim( (string) $this->opt( "trust_{$i}_sub" ) ),
				'icon'  => $i,
			);
		}

		if ( ! $items ) {
			return;
		}

		$returns_url = trim( (string) $this->opt( 'returns_url' ) );

		echo '<div class="kt-trust">';
		foreach ( $items as $item ) {
			// Only the returns tile links out, and only if a URL is configured.
			$is_return = ( 2 === $item['icon'] && '' !== $returns_url );
			$tag       = $is_return ? 'a' : 'div';

			printf(
				'<%1$s class="kt-trust__item"%2$s>%3$s<b>%4$s</b>%5$s</%1$s>',
				esc_attr( $tag ),
				$is_return ? ' href="' . esc_url( $returns_url ) . '"' : '',
				$this->trust_icon( $item['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput -- static inline SVG, no user data.
				esc_html( $item['title'] ),
				'' !== $item['sub'] ? '<span>' . esc_html( $item['sub'] ) . '</span>' : ''
			);
		}
		echo '</div>';
	}

	/**
	 * Static inline SVGs. Deliberately not FontAwesome: AUN's icon font is SUBSET,
	 * so a newly used glyph renders blank until the subset is regenerated. Inline
	 * SVG has no such trap and costs no extra request.
	 */
	private function trust_icon( $n ) {
		$open  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		$close = '</svg>';

		switch ( (int) $n ) {
			case 1: // Cash on delivery — a banknote.
				return $open . '<rect x="2" y="7" width="20" height="11" rx="2"/><circle cx="12" cy="12.5" r="2.6"/>' . $close;
			case 2: // Return — an arrow curling back.
				return $open . '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>' . $close;
			default: // Genuine — a shield with a tick.
				return $open . '<path d="M12 3l7 4v5c0 4-3 7.5-7 9-4-1.5-7-5-7-9V7z"/><path d="M9.5 12l2 2 3.5-4"/>' . $close;
		}
	}

	/**
	 * WhatsApp and Messenger as ORDER buttons, not share icons. A large share of
	 * Kohthai's customers would rather message than fill in a checkout form.
	 */
	public function render_chat_row() {
		global $product;

		$wa = preg_replace( '/\D+/', '', (string) $this->opt( 'whatsapp' ) );
		$fb = trim( (string) $this->opt( 'messenger' ) );

		if ( '' === $wa && '' === $fb ) {
			return;
		}

		$name = ( $product instanceof WC_Product ) ? $product->get_name() : '';
		$url  = ( $product instanceof WC_Product ) ? get_permalink( $product->get_id() ) : '';

		/* translators: 1: product name, 2: product URL */
		$message = sprintf(
			__( "Hi! I'd like to order this bag: %1\$s — %2\$s", 'kohthai-product-page' ),
			$name,
			$url
		);

		echo '<div class="kt-chat"><span class="kt-chat__lead">' .
			esc_html__( 'Prefer to order by message?', 'kohthai-product-page' ) .
			'</span><div class="kt-chat__row">';

		if ( '' !== $wa ) {
			printf(
				'<a class="kt-chat__btn kt-chat__btn--wa" href="%s" target="_blank" rel="noopener nofollow">%s%s</a>',
				esc_url( 'https://wa.me/' . $wa . '?text=' . rawurlencode( $message ) ),
				'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1a12 12 0 0 1-6.6-5.8c-.5-.9-.5-1.7-.4-2.3.1-.5.7-1.4 1.2-1.5h.7c.2 0 .4 0 .6.5l.8 1.9c.1.2 0 .4-.1.6l-.4.5c-.2.2-.3.3-.1.6a8.7 8.7 0 0 0 3.9 3.3c.3.1.5.1.7-.1l.7-.9c.2-.2.4-.2.6-.1l1.8.9c.3.1.4.2.4.4 0 .2 0 .6-.3 1.2z"/></svg>',
				esc_html__( 'Order on WhatsApp', 'kohthai-product-page' )
			);
		}

		if ( '' !== $fb ) {
			printf(
				'<a class="kt-chat__btn kt-chat__btn--fb" href="%s" target="_blank" rel="noopener nofollow">%s%s</a>',
				esc_url( $fb ),
				'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2C6.3 2 2 6.2 2 11.6c0 3 1.4 5.7 3.7 7.4V22l3.4-1.9c.9.3 1.9.4 2.9.4 5.7 0 10-4.2 10-9.6S17.7 2 12 2zm1 12.6l-2.5-2.7-5 2.7 5.5-5.8 2.6 2.7 4.9-2.7-5.5 5.8z"/></svg>',
				esc_html__( 'Messenger', 'kohthai-product-page' )
			);
		}

		echo '</div></div>';
	}

	/* =====================================================================
	 * Sticky buy bar
	 * ===================================================================== */

	/**
	 * Rendered server-side (so it survives WP Rocket's cache with no flicker) and
	 * revealed by JS once the real Add to Cart scrolls out of view.
	 *
	 * The price is deliberately re-read from the product rather than scraped from
	 * the DOM, so a variable product shows its range until a variation is chosen —
	 * at which point the JS updates it from WooCommerce's own found_variation event.
	 */
	public function render_sticky_bar() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		// Deliberately NOT the $product global. By wp_footer the Related Products
		// loop has already run, and it leaves the global pointing at the last
		// related product — which would put the wrong bag's photo and price in
		// the buy bar. The queried object is the only thing still trustworthy here.
		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
			return;
		}

		$img = $product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'kt-buybar__img' ) );

		?>
		<div class="kt-buybar" data-kt-buybar hidden>
			<div class="kt-buybar__inner">
				<div class="kt-buybar__thumb"><?php echo wp_kses_post( $img ); ?></div>
				<div class="kt-buybar__meta">
					<span class="kt-buybar__price" data-kt-price><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<span class="kt-buybar__variant" data-kt-variant></span>
				</div>
				<button type="button" class="kt-buybar__btn" data-kt-buy>
					<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/* =====================================================================
	 * Settings screen
	 * ===================================================================== */

	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Kohthai Product Page', 'kohthai-product-page' ),
			__( 'Product Page', 'kohthai-product-page' ),
			'manage_woocommerce',
			'kt-product-page',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'kt_pdp_group',
			KT_PDP_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Whitelist-based: anything not in defaults() is dropped on the floor.
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		foreach ( $defaults as $key => $default ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : null;

			if ( 0 === strpos( $key, 'enable_' ) ) {
				$clean[ $key ] = empty( $raw ) ? 0 : 1;
				continue;
			}

			if ( 'returns_url' === $key ) {
				$raw = trim( (string) $raw );
				// Allow a site-relative path as well as an absolute URL.
				$clean[ $key ] = ( '' === $raw || '/' === $raw[0] )
					? sanitize_text_field( $raw )
					: esc_url_raw( $raw );
				continue;
			}

			if ( 'messenger' === $key ) {
				$clean[ $key ] = esc_url_raw( trim( (string) $raw ) );
				continue;
			}

			if ( 'whatsapp' === $key ) {
				// Digits only — wa.me rejects anything else anyway.
				$clean[ $key ] = preg_replace( '/\D+/', '', (string) $raw );
				continue;
			}

			$clean[ $key ] = sanitize_text_field( (string) $raw );
		}

		return $clean;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'kohthai-product-page' ) );
		}

		$o = $this->options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kohthai Product Page', 'kohthai-product-page' ); ?></h1>

			<p style="max-width:46em">
				<?php esc_html_e( 'Every item below can be switched off on its own. If something looks wrong on the live site, turn off the single row that causes it rather than deactivating the whole plugin.', 'kohthai-product-page' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'kt_pdp_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Fixes', 'kohthai-product-page' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( 'enable_video_fix', __( 'Video gap', 'kohthai-product-page' ), __( 'Removes the ~490px of blank space above every product video (Flatsome + WP Rocket both reserve a 16:9 box).', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_swatch_names', __( 'Colour names', 'kohthai-product-page' ), __( 'Prints each colour name permanently under its swatch instead of only in a hover tooltip, which phones do not have.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_buttons', __( 'Button contrast', 'kohthai-product-page' ), __( 'Add to Cart becomes solid brand brown (8.87:1) and Buy Now becomes an outline. The old tan measured 3.48:1, below the accessible minimum.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_feature_wrap', __( 'Feature icon row', 'kohthai-product-page' ), __( 'Lets the icon row wrap on phones instead of squeezing labels to one word per line.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_clean_excerpt', __( 'Strip empty headings', 'kohthai-product-page' ), __( 'Removes the empty h5 and p tags Flatsome shortcodes leave behind. Applies to every product without re-editing any of them.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_sku_hide', __( 'Hide empty SKU', 'kohthai-product-page' ), __( 'Stops the page printing "SKU: N/A".', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_related_sort', __( 'Related products', 'kohthai-product-page' ), __( 'Moves out-of-stock products to the end of the Related row instead of letting them lead it.', 'kohthai-product-page' ), $o );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Additions', 'kohthai-product-page' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( 'enable_stock', __( 'Stock line', 'kohthai-product-page' ), __( 'Says out loud that the bag is in stock.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_trust', __( 'Trust row', 'kohthai-product-page' ), __( 'Three reassurances under Add to Cart.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_chat', __( 'WhatsApp / Messenger', 'kohthai-product-page' ), __( 'Order-by-message buttons under the CTA.', 'kohthai-product-page' ), $o );
					$this->checkbox_row( 'enable_sticky', __( 'Sticky buy bar', 'kohthai-product-page' ), __( 'Price and Add to Cart follow the customer down the page once the real button scrolls away.', 'kohthai-product-page' ), $o );
					?>
					<tr>
						<th scope="row"><label for="kt_stock_text"><?php esc_html_e( 'Stock wording', 'kohthai-product-page' ); ?></label></th>
						<td><input type="text" class="regular-text" id="kt_stock_text"
							name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[stock_text]"
							value="<?php echo esc_attr( $o['stock_text'] ); ?>"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Contact', 'kohthai-product-page' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="kt_whatsapp"><?php esc_html_e( 'WhatsApp number', 'kohthai-product-page' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="kt_whatsapp"
								name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[whatsapp]"
								value="<?php echo esc_attr( $o['whatsapp'] ); ?>" placeholder="8801XXXXXXXXX">
							<p class="description"><?php esc_html_e( 'Country code first, digits only, no + and no spaces. Leave empty to hide the WhatsApp button.', 'kohthai-product-page' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kt_messenger"><?php esc_html_e( 'Messenger link', 'kohthai-product-page' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="kt_messenger"
								name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[messenger]"
								value="<?php echo esc_attr( $o['messenger'] ); ?>" placeholder="https://m.me/yourpage">
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Trust row wording', 'kohthai-product-page' ); ?></h2>
				<div class="notice notice-warning inline" style="max-width:46em;padding:10px 12px">
					<p style="margin:0">
						<strong><?php esc_html_e( 'Keep this honest.', 'kohthai-product-page' ); ?></strong>
						<?php esc_html_e( 'Kohthai\'s policy is a conditional 7-day return — damaged, incorrect, incomplete or not as advertised — and the customer pays return shipping. Do not write "no questions asked", "free returns" or "hassle-free returns": all three are untrue here and invite exactly the disputes you are trying to avoid. And these are bags, so no warranty claim belongs on the page.', 'kohthai-product-page' ); ?>
					</p>
				</div>
				<table class="form-table" role="presentation">
					<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
						<tr>
							<th scope="row"><?php printf( esc_html__( 'Item %d', 'kohthai-product-page' ), (int) $i ); ?></th>
							<td>
								<input type="text" class="regular-text"
									name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[trust_<?php echo (int) $i; ?>_title]"
									value="<?php echo esc_attr( $o[ "trust_{$i}_title" ] ); ?>"
									placeholder="<?php esc_attr_e( 'Title', 'kohthai-product-page' ); ?>">
								<input type="text" class="regular-text"
									name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[trust_<?php echo (int) $i; ?>_sub]"
									value="<?php echo esc_attr( $o[ "trust_{$i}_sub" ] ); ?>"
									placeholder="<?php esc_attr_e( 'Small print', 'kohthai-product-page' ); ?>">
								<p class="description"><?php esc_html_e( 'Leave the title empty to hide this item.', 'kohthai-product-page' ); ?></p>
							</td>
						</tr>
					<?php endfor; ?>
					<tr>
						<th scope="row"><label for="kt_returns_url"><?php esc_html_e( 'Returns policy link', 'kohthai-product-page' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="kt_returns_url"
								name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[returns_url]"
								value="<?php echo esc_attr( $o['returns_url'] ); ?>">
							<p class="description"><?php esc_html_e( 'The middle trust item links here. A path like /returns_refund/ is fine.', 'kohthai-product-page' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<p class="description">
				<?php esc_html_e( 'After saving, clear the WP Rocket cache — product pages are cached and will otherwise keep serving the old markup.', 'kohthai-product-page' ); ?>
			</p>
		</div>
		<?php
	}

	private function checkbox_row( $key, $label, $help, $o ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" value="1"
						name="<?php echo esc_attr( KT_PDP_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]"
						<?php checked( ! empty( $o[ $key ] ) ); ?>>
					<?php esc_html_e( 'On', 'kohthai-product-page' ); ?>
				</label>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			</td>
		</tr>
		<?php
	}
}

KT_Product_Page::instance();
