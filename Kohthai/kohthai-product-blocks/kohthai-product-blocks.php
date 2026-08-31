<?php
/**
 * Plugin Name: Kohthai Product Blocks
 * Description: The Kohthai product page, in one place. Feature icons (per-product or shortcode),
 *              the trust row, the product-details type scale, and the theme fixes that used to
 *              live in Customizer → Additional CSS. Settings → Product Blocks.
 * Version:     1.5.1
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 * Requires PHP: 7.4
 *
 * v1.5.1
 *   - The description paragraph runs in TWO columns on desktop. Asked about four times, and each
 *     time I answered with typography theory instead of measuring. Measured: at 68ch the line was
 *     already 86 characters - past the comfortable 45-75 range - while filling only 64%% of the
 *     row and leaving 380px of dead white. So widening it would have made reading worse, not
 *     better. Two columns fix both at once: ~50 characters per line, the full row used, and 29px
 *     shorter. The 460px columns line up exactly with Size & Fit / Materials underneath.
 *
 * v1.5.0
 *   - [kt_lead], [kt_details] and [kt_frames]. The description field needed ~40 lines of
 *     hand-pasted HTML per product, including inline SVG copied by hand for every icon. One
 *     mistyped tag broke the layout silently, and nobody but me could edit a product. Same
 *     reasoning that produced [kt_features]: the markup belongs in code, the content belongs in
 *     the editor. A whole product description is now three lines.
 *
 *     [kt_details] separates its notes on a PIPE, not a comma. The notes contain commas
 *     ("Fits a phone, wallet, makeup"), and comma-splitting is the exact trap the features list
 *     already fell into once.
 *
 *     A column with no content is not rendered, so a product with no dimensions still gets a
 *     tidy single-column Materials block rather than an empty half.
 *
 *   - Delivery & Returns from the design is NOT a shortcode, on purpose. It is identical on
 *     every product, and Flatsome already has a "Global custom tab" in the Customizer which puts
 *     one extra tab on every product from a single field. Writing a shortcode for it would mean
 *     pasting the same text 21 times and re-pasting it whenever the policy changes.
 *
 * v1.4.0
 *   - Stock line. Measured on the live catalogue: an in-stock product printed NO stock text at
 *     all - WooCommerce only emits one when stock is managed or the item is on backorder - so 18
 *     of 21 products said nothing about availability at the moment of decision.
 *
 *     It renders ONLY where WooCommerce prints nothing. That is deliberate: where WooCommerce
 *     does emit a line it is already correct AND variation aware (it re-renders per variation
 *     from availability_html), so a second server-rendered line would duplicate it and then go
 *     stale the moment a colour is chosen. This fills the silence and styles what already
 *     exists, rather than competing with it.
 *
 *     Backorder products are skipped too - the Smart Delivery plugin states the wait directly
 *     underneath, and saying it twice in one column reads as padding.
 *
 *   - NOT BUILT: the "You save X" price block from the design. Checked all 21 products via the
 *     Store API - on_sale is false on every one and regular_price equals price everywhere, so it
 *     would render nothing across the whole catalogue. Whether to run regular/sale pricing is a
 *     merchandising decision, not a design one. Once sale prices exist WooCommerce already shows
 *     the struck-through original and only the saving line needs adding.
 *
 * v1.3.0
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
 *   - Harmonised the Smart Delivery plugin's box with the rest of the buy box. The column had
 *     three blocks in three design languages: the trust row (warm #faf7f3 tiles), the chat block
 *     (cool #fafafa) and the delivery estimate (cool #f7f7f7 with a FontAwesome truck). Same
 *     400px column, three different looks.
 *
 *     This is done as a STYLE OVERRIDE, not by editing that plugin. Smart Delivery also splits
 *     the cart by stock status, writes estimates into order meta and into customer emails — its
 *     logic must not be touched to change a colour. Product Blocks already owns the product-page
 *     look (it sets .kts-feat-title for the Shorts plugin too), so it owns this as well.
 *
 *     Its FontAwesome glyph is replaced with an inline-SVG background so the icon matches the
 *     rest of the system. The <i> carries an inline style="margin-right:.4em", which is why the
 *     margin reset needs !important.
 *
 *     The backorder notice moves from alarm red (#b30000 on #fff0f0) to amber. It was the
 *     loudest thing in the buy box, outweighing the price, for information that is important but
 *     not an emergency.
 *
 * v1.2.0
 *   - The trust row reads stock status. "Ships from Dhaka / Not a pre-order" was appearing on
 *     backorder products, on the same screen as the delivery plugin saying "This is a backorder
 *     item, estimated delivery 1-2 weeks". Two of the 21 products are on backorder, so the page
 *     was contradicting itself on both of them.
 *     Each tile can now carry alternative wording used only when the product is on backorder.
 *     Leave a tile's backorder title empty and that tile simply keeps its normal text.
 *     Detection is `$product->is_on_backorder()`, read server-side from the PARENT product.
 *     CAVEAT: on a variable product whose variations differ, the parent status is all we have at
 *     render time, so a mixed product shows the parent's wording until a variation is chosen.
 *     Both current backorder products are backordered at parent level, so this is correct today.
 *
 * v1.1.0
 *   - [kt_trust] — the four-tile trust row, wording editable in settings.
 *   - PER-PRODUCT FEATURE ICONS. A field on the product data panel, rendered on
 *     woocommerce_single_product_summary at priority 31. That is the only way to get the icons
 *     BELOW the Add to Cart button, which is where the design puts them: the short description
 *     renders at priority 20 (before add_to_cart at 30), and Flatsome's "HTML after Add To Cart"
 *     field is global, so it cannot carry per-product icons. 31 also lands before the Smart
 *     Delivery plugin at 35, so the delivery estimate stays underneath.
 *   - ABSORBED THE CUSTOMIZER CSS. Everything the product page had accumulated in Additional CSS
 *     now lives here, deduplicated. Two rules in there were dead or wrong:
 *       * .kt-feat (the old hand-pasted feature row) — checked all 21 products, ZERO still use
 *         that markup, so it is deleted rather than carried.
 *       * .kt-h and .kt-spec b were each declared TWICE, the second silently overriding the
 *         first (13px then 16px, 19px then 17px). Only the winning values are kept.
 *     The video-gap fix IS carried over: 17 of 21 products still use [ux_video].
 *   - FIXED THE ACCORDION ARROW. The Customizer rule had padding-left:0 !important, and
 *     Flatsome's toggle is position:absolute;left:0 and 47px wide, so the title text ran
 *     underneath it. Confirmed still broken live before this release. The toggle now moves to
 *     the right and the title lines up with the content beneath it.
 *
 * v1.0.1
 *   - The icon row always sits on ONE row. Two causes, both worth remembering:
 *       * Flatsome ships `.entry-content ul li { margin-left: 1.3em }` (20.8px) and that selector
 *         outranks `.kt-features li`, so `margin:0` silently lost. Five icons really needed 540px,
 *         not the 436px the arithmetic promised, inside a 459px column.
 *       * flex-wrap breaks lines on the flex BASIS, before it shrinks anything — so a 76px basis
 *         wraps at 360px however small the items could become. Only a ZERO basis (flex:1 1 0)
 *         guarantees one row; max-width stops three icons sprawling.
 *     Measured: 459px column → 3/110px, 4/104px, 5/81px. 360px → 5/62px, 6/53px. All one row.
 *
 * v1.0.0
 *   - [kt_features]. Replaces five [featured_box] shortcodes (~40 lines) per product, which
 *     emitted 18 empty <h5> and 37 empty <p> per page and five oversized lazy-loaded PNGs.
 *     DO NOT ever "fix" that with a content filter — an earlier attempt corrupted the document
 *     and took the live page down. Clean markup at the source is the only safe route.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kohthai_Product_Blocks {

	const VERSION = '1.5.1';
	const OPTION  = 'kt_blocks_options';
	const META    = '_kt_features';

	public static function init() {
		add_shortcode( 'kt_features', array( __CLASS__, 'features_shortcode' ) );
		add_shortcode( 'kt_trust', array( __CLASS__, 'trust_shortcode' ) );
		add_shortcode( 'kt_lead', array( __CLASS__, 'lead_shortcode' ) );
		add_shortcode( 'kt_details', array( __CLASS__, 'details_shortcode' ) );
		add_shortcode( 'kt_frames', array( __CLASS__, 'frames_shortcode' ) );

		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// Per-product icons — product data panel + front-end render.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_product_features' ), 31 );

		// Priority 11: straight after the price (10), before the excerpt (20). That is where the
		// design puts availability - price, then "can I actually get it", then colour.
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_stock_line' ), 11 );
	}

	/* =====================================================================
	 * Options
	 * ===================================================================== */

	public static function defaults() {
		return array(
			'css_theme_fixes'     => 1,
			'css_product_details' => 1,
			'css_plugin_harmony'  => 1,
			'enable_stock_line'   => 1,
			'stock_text'          => 'In stock - ready to ship',
			'trust_1_title' => 'Cash on Delivery', 'trust_1_sub' => 'Pay when it arrives',
			'trust_2_title' => '7-Day Return',     'trust_2_sub' => 'If damaged or not as described',
			'trust_3_title' => '100% Genuine',     'trust_3_sub' => 'Imported stock',
			'trust_4_title' => 'Ships from Dhaka', 'trust_4_sub' => 'Not a pre-order',

			// Used INSTEAD of the above when the product is on backorder. An empty title here
			// means "no change" — that tile keeps its normal wording. Only tile 4 differs by
			// default, because it is the only one whose claim depends on having stock: cash on
			// delivery, the return window and authenticity are all true either way.
			'trust_1_bo_title' => '', 'trust_1_bo_sub' => '',
			'trust_2_bo_title' => '', 'trust_2_bo_sub' => '',
			'trust_3_bo_title' => '', 'trust_3_bo_sub' => '',
			'trust_4_bo_title' => 'Ships in 1-2 weeks', 'trust_4_bo_sub' => 'Ordered in specially for you',

			'returns_url'   => '/returns_refund/',
		);
	}

	public static function options() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function register_settings() {
		register_setting( 'kt_blocks_group', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => self::defaults(),
		) );
	}

	public static function sanitize( $input ) {
		$clean = array();
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}
		foreach ( self::defaults() as $key => $default ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : null;

			if ( 0 === strpos( $key, 'css_' ) ) {
				$clean[ $key ] = empty( $raw ) ? 0 : 1;
				continue;
			}
			if ( 'returns_url' === $key ) {
				$raw = trim( (string) $raw );
				// A site-relative path is fine; anything else must be a real URL.
				$clean[ $key ] = ( '' === $raw || '/' === $raw[0] )
					? sanitize_text_field( $raw )
					: esc_url_raw( $raw );
				continue;
			}
			$clean[ $key ] = sanitize_text_field( (string) $raw );
		}
		return $clean;
	}

	/* =====================================================================
	 * Icons
	 * ===================================================================== */

	/** key => [ label, path data ]. Paths only — the <svg> wrapper is added once, in svg(). */
	public static function icons() {
		return array(
			'adjustable-strap' => array( 'Adjustable Strap',
				'<path d="M2.5 12h19"/><rect x="8" y="8.5" width="8" height="7" rx="2"/><path d="M2.5 9.6v4.8M21.5 9.6v4.8"/>' ),
			'optional-strap' => array( 'Optional Strap',
				'<path d="M10.2 7.2H8.4a4.8 4.8 0 0 0 0 9.6h1.8"/><path d="M13.8 7.2h1.8a4.8 4.8 0 0 1 0 9.6h-1.8"/><path d="M8.8 12h6.4"/>' ),
			'inner-pocket' => array( 'Inner Pocket',
				'<path d="M4.6 8.6h14.8l-1.1 10.9a1.7 1.7 0 0 1-1.7 1.5H7.4a1.7 1.7 0 0 1-1.7-1.5z"/><path d="M8.6 8.6V6.4a3.4 3.4 0 0 1 6.8 0v2.2"/><path d="M9.2 12.9h5.6v4.2H9.2z" stroke-dasharray="2.4 1.8"/>' ),
			'outer-pocket' => array( 'Outer Pocket',
				'<path d="M4.6 8.6h14.8l-1.1 10.9a1.7 1.7 0 0 1-1.7 1.5H7.4a1.7 1.7 0 0 1-1.7-1.5z"/><path d="M8.6 8.6V6.4a3.4 3.4 0 0 1 6.8 0v2.2"/><path d="M6.9 13.8h10.2v7.2H6.9z"/>' ),
			'zipper' => array( 'Zipper',
				'<path d="M12 3v10.4"/><path d="M9.6 5.6h4.8M9.6 8.2h4.8M9.6 10.8h4.8"/><rect x="9.9" y="13.6" width="4.2" height="6.8" rx="2.1"/>' ),
			'magnetic-flap' => array( 'Magnetic Flap',
				'<path d="M4.6 10.2h14.8v9.2a1.7 1.7 0 0 1-1.7 1.6H6.3a1.7 1.7 0 0 1-1.7-1.6z"/><path d="M4.6 10.2 7.1 4.6h9.8l2.5 5.6"/><circle cx="12" cy="14.4" r="1.7"/>' ),
			'chain-strap' => array( 'Chain Strap',
				'<rect x="2.4" y="10" width="8.4" height="4" rx="2"/><rect x="8.4" y="10" width="8.4" height="4" rx="2"/><rect x="14.4" y="10" width="7.2" height="4" rx="2"/>' ),
			'water-resistant' => array( 'Water Resistant',
				'<path d="M12 3.4s6.2 6.7 6.2 10.4a6.2 6.2 0 1 1-12.4 0C5.8 10.1 12 3.4 12 3.4z"/><path d="M9.4 14.3a2.7 2.7 0 0 0 2.6 2.6"/>' ),
			'expandable' => array( 'Expandable',
				'<path d="M4.6 8.6h14.8l-1.1 10.9a1.7 1.7 0 0 1-1.7 1.5H7.4a1.7 1.7 0 0 1-1.7-1.5z"/><path d="M8.6 8.6V6.4a3.4 3.4 0 0 1 6.8 0v2.2"/><path d="M9.4 15.6h5.2"/><path d="M12 13v5.2"/>' ),
			'lightweight' => array( 'Lightweight',
				'<path d="M20 5c-7.2 0-12 4.4-12 10.6V19"/><path d="M8 14.6h6.4"/><path d="M8 19H5.2"/>' ),
		);
	}

	/** Trust tile icons, same system. */
	private static function trust_icon( $n ) {
		switch ( (int) $n ) {
			case 1: return '<rect x="2" y="7" width="20" height="11" rx="2"/><circle cx="12" cy="12.5" r="2.6"/>';
			case 2: return '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>';
			case 3: return '<path d="M12 3l7 4v5c0 4-3 7.5-7 9-4-1.5-7-5-7-9V7z"/><path d="M9.5 12l2 2 3.5-4"/>';
			default: return '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>';
		}
	}

	/** The single definition of the SVG wrapper, so no icon can drift from the system. */
	private static function svg( $paths ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"'
			. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths . '</svg>';
	}

	/* =====================================================================
	 * Feature row
	 * ===================================================================== */

	/** Shared renderer for the shortcode and the per-product field. */
	public static function render_features( $items, $align = 'center' ) {
		$keys = array_filter( array_map( 'trim', explode( ',', (string) $items ) ) );
		if ( ! $keys ) {
			return '';
		}

		$icons = self::icons();
		$out   = array();

		foreach ( $keys as $raw ) {
			$label = '';
			if ( false !== strpos( $raw, '|' ) ) {
				list( $raw, $label ) = array_map( 'trim', explode( '|', $raw, 2 ) );
			}
			$key = sanitize_key( $raw );
			if ( ! isset( $icons[ $key ] ) ) {
				continue; // a typo must never leave a hole in the row on a live product
			}
			if ( '' === $label ) {
				$label = $icons[ $key ][0];
			}
			$out[] = sprintf( '<li>%s<span>%s</span></li>', self::svg( $icons[ $key ][1] ), esc_html( $label ) );
		}

		if ( ! $out ) {
			return '';
		}

		$style = ( 'left' === $align ) ? ' style="justify-content:flex-start"' : '';
		return '<ul class="kt-features"' . $style . '>' . implode( '', $out ) . '</ul>';
	}

	public static function features_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'items' => '', 'align' => 'center' ), $atts, 'kt_features' );
		$align = in_array( $atts['align'], array( 'center', 'left' ), true ) ? $atts['align'] : 'center';
		return self::render_features( $atts['items'], $align );
	}

	/* =====================================================================
	 * Per-product feature icons
	 * ===================================================================== */

	public static function product_field() {
		$keys = implode( ', ', array_keys( self::icons() ) );
		woocommerce_wp_text_input( array(
			'id'          => self::META,
			'label'       => __( 'Feature icons', 'kohthai-product-blocks' ),
			'placeholder' => 'adjustable-strap, inner-pocket, zipper',
			'desc_tip'    => false,
			'description' => sprintf(
				/* translators: %s: comma separated list of icon keys */
				__( 'Comma separated. Shown below the Add to Cart button. Leave empty for none. Add |Your Label after a key to change its wording. Available: %s', 'kohthai-product-blocks' ),
				$keys
			),
		) );
	}

	public static function save_product_field( $post_id ) {
		// WooCommerce has already verified the nonce and capability for this hook.
		if ( ! isset( $_POST[ self::META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			delete_post_meta( $post_id, self::META );
			return;
		}
		$raw = wp_unslash( $_POST[ self::META ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$val = sanitize_text_field( $raw );
		if ( '' === trim( $val ) ) {
			delete_post_meta( $post_id, self::META );
		} else {
			update_post_meta( $post_id, self::META, $val );
		}
	}

	/**
	 * Priority 31: after add_to_cart (30), before the Smart Delivery plugin (35).
	 * That ordering is the whole point — the icons belong under the button, and the delivery
	 * estimate belongs under them.
	 */
	public static function render_product_features() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$items = get_post_meta( $product->get_id(), self::META, true );
		if ( ! $items ) {
			return;
		}
		echo self::render_features( $items ); // phpcs:ignore WordPress.Security.EscapeOutput -- labels escaped in render_features().
	}

	/* =====================================================================
	 * Stock line
	 * ===================================================================== */

	/**
	 * Says "in stock" where WooCommerce says nothing at all.
	 *
	 * WooCommerce only emits stock text for managed stock or a backorder, so a plainly in-stock
	 * bag showed no availability anywhere on the page. This fills that silence and otherwise
	 * stays out of the way:
	 *
	 *   out of stock    - nothing. The button is already disabled and labelled; saying it twice
	 *                     helps nobody.
	 *   on backorder    - nothing. The Smart Delivery plugin states the wait right below.
	 *   Woo has a line  - nothing. Its version is correct and, on a variable product, re-renders
	 *                     per variation. A server-rendered copy would go stale on first click.
	 *   otherwise       - "In stock - ready to ship".
	 */
	public static function render_stock_line() {
		$o = self::options();
		if ( empty( $o['enable_stock_line'] ) ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ! $product->is_in_stock() || $product->is_on_backorder() ) {
			return;
		}

		// If WooCommerce already prints availability, leave it to WooCommerce.
		if ( function_exists( 'wc_get_stock_html' ) && '' !== trim( wc_get_stock_html( $product ) ) ) {
			return;
		}

		$text = trim( (string) $o['stock_text'] );
		if ( '' === $text ) {
			return;
		}

		echo '<p class="kt-stock"><span class="kt-stock__dot"></span>' . esc_html( $text ) . '</p>';
	}
	/* =====================================================================
	 * [kt_trust]
	 * ===================================================================== */

	/**
	 * Is the product being viewed on backorder?
	 *
	 * Read from the PARENT product, because the trust row renders once, before any variation is
	 * chosen. Both of Kohthai's current backorder products are set at parent level so this is
	 * accurate; a variable product with mixed variations would show the parent's wording.
	 */
	private static function is_backorder() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		return $product->is_on_backorder();
	}

	public static function trust_shortcode() {
		$o    = self::options();
		$url  = trim( (string) $o['returns_url'] );
		$bo   = self::is_backorder();
		$rows = array();

		for ( $i = 1; $i <= 4; $i++ ) {
			$title = trim( (string) $o[ "trust_{$i}_title" ] );
			$sub   = trim( (string) $o[ "trust_{$i}_sub" ] );

			// On backorder, a tile may say something different. An empty backorder title means
			// this tile has nothing stock-dependent to say, so it keeps its normal wording.
			if ( $bo ) {
				$bo_title = trim( (string) $o[ "trust_{$i}_bo_title" ] );
				if ( '' !== $bo_title ) {
					$title = $bo_title;
					$sub   = trim( (string) $o[ "trust_{$i}_bo_sub" ] );
				}
			}

			if ( '' === $title ) {
				continue; // an empty title hides that tile
			}

			// Only the returns tile links out, and only when a URL is configured.
			$is_link = ( 2 === $i && '' !== $url );
			$tag     = $is_link ? 'a' : 'div';

			$rows[] = sprintf(
				'<%1$s class="kt-trust__i"%2$s>%3$s<span><b>%4$s</b>%5$s</span></%1$s>',
				$tag,
				$is_link ? ' href="' . esc_url( $url ) . '"' : '',
				self::svg( self::trust_icon( $i ) ),
				esc_html( $title ),
				'' !== $sub ? '<span>' . esc_html( $sub ) . '</span>' : ''
			);
		}

		return $rows ? '<div class="kt-trust">' . implode( '', $rows ) . '</div>' : '';
	}

	/* =====================================================================
	 * Styles — everything that used to be in Customizer → Additional CSS
	 * ===================================================================== */

	public static function enqueue() {
		wp_register_style( 'kohthai-product-blocks', false, array(), self::VERSION );
		wp_enqueue_style( 'kohthai-product-blocks' );
		wp_add_inline_style( 'kohthai-product-blocks', self::css() );
	}

	public static function css() {
		$o   = self::options();
		$css = self::css_features() . self::css_stock() . self::css_frames() . self::css_trust();

		if ( ! empty( $o['css_theme_fixes'] ) ) {
			$css .= self::css_theme_fixes();
		}
		if ( ! empty( $o['css_product_details'] ) ) {
			$css .= self::css_product_details();
		}
		if ( ! empty( $o['css_plugin_harmony'] ) ) {
			$css .= self::css_plugin_harmony();
		}
		return $css;
	}

	/** Always on: the shortcode's own markup depends on it. */
	public static function css_features() {
		return '
.kt-features{display:flex;flex-wrap:wrap;justify-content:center;gap:20px 14px;padding:22px 0;margin:0 0 1rem;
 border-top:1px solid #e8e2d9;border-bottom:1px solid #e8e2d9;list-style:none}
/* margin needs !important: Flatsome ships `.entry-content ul li{margin-left:1.3em}` (20.8px) and
   that selector outranks this one. flex-basis must be ZERO -- flex-wrap breaks on the basis
   before it shrinks anything, so any px basis wraps on a phone. max-width stops 3 icons sprawling. */
.kt-features li{flex:1 1 0;min-width:0;max-width:110px;margin:0 !important;
 display:flex;flex-direction:column;align-items:center;gap:9px;text-align:center}
.kt-features svg{width:26px;height:26px;color:#8a7358;flex:none}
.kt-features span{font-size:13px;line-height:1.35;letter-spacing:.02em;color:#4a4038;font-weight:600}
@media(max-width:549px){.kt-features{gap:16px 8px;padding:18px 0}}
';
	}

	public static function css_stock() {
		return '
.kt-stock{display:flex;align-items:center;gap:7px;margin:0 0 .9em;font-size:13px;font-weight:700;
 letter-spacing:.05em;text-transform:uppercase;color:#2f6b42}
.kt-stock__dot{width:7px;height:7px;border-radius:50%;background:currentColor;flex:none}
/* WooCommerce prints its own line for managed stock and backorders. Style that to match rather
   than replace it - it is variation aware and ours is not. */
.woocommerce div.product p.stock,
.woocommerce div.product .woocommerce-variation-availability p.stock{
 font-size:13px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin:0 0 .9em}
.woocommerce div.product p.stock.in-stock{color:#2f6b42}
.woocommerce div.product p.stock.available-on-backorder{color:#8a6110}
.woocommerce div.product p.stock.out-of-stock{color:#9a3b30}
';
	}

	public static function css_frames() {
		return '
.kt-frames{margin:2.2em 0 0}
.kt-frames__sub{font-size:13px;color:#6f6459;margin:-14px 0 14px}
.kt-frames__row{display:flex;gap:10px;overflow-x:auto;padding-bottom:4px;scroll-snap-type:x proximity}
.kt-frames__row>div{flex:0 0 clamp(140px,28%,190px);scroll-snap-align:start}
.kt-frames__row img{width:100%;height:auto;aspect-ratio:3/4;object-fit:cover;border-radius:3px;display:block}
/* The fit row needs a visually hidden label: a tick and a cross alone are colour-only meaning. */
.kt-fits .screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
';
	}

	public static function css_trust() {
		return '
.kt-trust{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:1.1em 0}
.kt-trust__i{display:flex;gap:9px;align-items:flex-start;background:#faf7f3;border:1px solid #e8e2d9;
 border-radius:3px;padding:10px 11px;text-decoration:none}
a.kt-trust__i:hover{border-color:#a08565}
a.kt-trust__i:focus-visible{outline:2px solid #654321;outline-offset:2px}
.kt-trust__i svg{width:20px;height:20px;color:#654321;flex:none;margin-top:1px}
.kt-trust__i b{display:block;font-size:14px;color:#1f1a15;line-height:1.3;font-weight:700}
.kt-trust__i span span{display:block;font-size:13px;color:#6f6459;line-height:1.35;margin-top:2px}
';
	}

	/**
	 * Theme fixes. These were in Additional CSS; they are not decoration, each one repairs a
	 * specific conflict and every one is annotated with what it repairs.
	 */
	public static function css_theme_fixes() {
		return '
/* Flatsome [ux_video] reserves a 16:9 box on .video-fit and absolutely positions the IFRAME into
   it. WP Rocket\'s "Replace YouTube iframe with preview image" swaps that iframe for
   <div class="rll-youtube-player">, which is NOT absolutely positioned and brings its own
   padding-bottom -- so Flatsome\'s box sits empty and Rocket\'s stacks below it. Measured 978.56px
   where 489.19px belongs. Still needed: 17 of 21 products use [ux_video]. */
.video-fit > .rll-youtube-player{position:absolute;top:0;left:0;width:100%;height:100%;padding-bottom:0 !important}
.video-fit > .rll-youtube-player img{width:100%;height:100%;object-fit:cover}

/* Colour name under each swatch. The swatches are photographs of the real bag on purpose -- a flat
   colour chip promises a shade the bag may not be -- but three 45px crops look identical, and the
   name lived only in a title tooltip, which a phone does not have. */
.ux-swatches--large .ux-swatch{position:relative;margin-bottom:1.9em}
.ux-swatches--large .ux-swatch::after{content:attr(data-name);position:absolute;top:calc(100% + 6px);
 left:50%;transform:translateX(-50%);width:78px;font-size:12px;line-height:1.25;text-align:center;
 color:#6f6459;pointer-events:none}
.ux-swatches--large .ux-swatch[aria-checked="true"]::after{color:#654321;font-weight:700}
.ux-swatches--large .ux-swatch.out-of-stock::after,
.ux-swatches--large .ux-swatch.disabled::after{text-decoration:line-through;opacity:.55}

/* Accordion headers: text and a rule, no fill -- the way C&K and COS do it. The old Additional CSS
   set padding-left:0 !important, and Flatsome\'s toggle is position:absolute;left:0 and 47px wide,
   so the title text ran underneath it. Moving the toggle right frees the left edge AND lets the
   title line up with the content below (both land at the same x). */
.product-page-accordian .accordion-title{background-color:transparent !important;
 border-top:1px solid #e8e2d9 !important;font-family:"Playfair Display",Georgia,serif !important;
 font-size:21px !important;padding-left:36px !important;padding-right:44px !important;position:relative !important}
.product-page-accordian .accordion-title .toggle{left:auto !important;right:0 !important}
';
	}

	/**
	 * The product-details type scale. Sizes are rebased on the theme\'s 16px body — an earlier
	 * version was designed against a 13px mockup and shipped 20-34% too small.
	 * Ladder: H1 27px > accordion 21px > card heading 18px > section 16px upper > body 16px.
	 */
	public static function css_product_details() {
		return '
.kt-lead{font-size:17px;line-height:1.75;color:#4a4038;margin:0 0 34px;max-width:68ch}
/* Two columns on desktop. This is not about filling space for its own sake: at full row width the
   line ran 86 characters, already past the comfortable 45-75, and 64%% of the row left 380px of
   dead white. Two 460px columns bring the line to ~50 characters AND fill the row - and 460px is
   exactly the column width of the Size & Fit / Materials block underneath, so the whole
   description reads on one grid. Single column below 850px, where there is no room to split. */
@media(min-width:850px){.kt-lead{max-width:none;columns:2;column-gap:56px}}

/* min() stops the 300px minimum forcing columns wider than the phone they sit in --
   without it each column measures 300px inside a 286px container. */
.kt-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));
 gap:40px 56px;align-items:start}

/* 16px UPPERCASE, not 13px: uppercase has no ascenders or descenders so it reads smaller than its
   px size -- 16 upper carries about the weight of the 18px sentence-case heading beside it. */
.kt-h{font-size:16px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#654321;
 margin:0 0 22px;padding-bottom:12px;border-bottom:1px solid #e8e2d9}

.kt-spec{display:grid;grid-template-columns:repeat(2,1fr);gap:22px 16px;margin:0 0 24px}
.kt-spec div{display:flex;align-items:center;gap:12px}
.kt-spec svg{width:26px;height:26px;color:#8a7358;flex:none}
/* 17px, not 19px: a dimension is data, not a heading, and at 19px it outranked the section title. */
.kt-spec b{display:block;font-size:17px;color:#1f1a15;line-height:1.25}
.kt-spec em{font-style:normal;font-size:13px;color:#6f6459;letter-spacing:.03em}

.kt-notes{margin:0;padding-left:19px;font-size:15px;line-height:1.7;color:#4a4038}
.kt-notes li{margin:0 0 7px}

.kt-fits{display:flex;gap:34px;margin:26px 0 0}
.kt-fits div{display:flex;flex-direction:column;align-items:center;gap:9px;font-size:13px;color:#6f6459}
.kt-fits .dev{width:30px;height:30px;color:#8a7358}
.kt-fits .mk{width:19px;height:19px}

.kt-care{display:grid;grid-template-columns:1fr;gap:20px}
.kt-care > div{display:flex;gap:13px;align-items:flex-start}
.kt-care svg{width:24px;height:24px;color:#8a7358;flex:none;margin-top:2px}
.kt-care b{display:block;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:#1f1a15;margin-bottom:5px}
.kt-care span span{font-size:15px;color:#6f6459;line-height:1.6}

/* Belongs to Kohthai Shorts Showcase, but the product-page type ladder is decided here so it
   lives in one place. 18px sits below the 21px accordion title instead of level with it. */
.kts-feat-title{font-size:18px !important}
';
	}

	/**
	 * Makes the Smart Delivery plugin's estimate box part of the same family as the trust row.
	 * Style only — that plugin's logic is left alone on purpose.
	 */
	/** A truck icon as a data URI, stroked in the colour given. */
	private static function truck_uri( $color ) {
		return 'data:image/svg+xml;utf8,' . rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="'
			. $color . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/>'
			. '<circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>'
		);
	}

	public static function css_plugin_harmony() {
		// Truck, drawn on the same 24px / 1.5-stroke grid as every other icon here, encoded as a
		// background so the plugin's FontAwesome <i> can be reused without touching its markup.
		// The colour is substituted BEFORE encoding -- encoding first would turn the placeholder
		// itself into %25COLOR%25 and the swap would silently never happen.
		$brand = self::truck_uri( '#654321' );
		$amber = self::truck_uri( '#8a6110' );

		return '
/* ---- Kohthai Smart Delivery: same warm tile as the trust row ---- */
.kohthai-delivery-estimate-container{background:#faf7f3 !important;border:1px solid #e8e2d9 !important;
 border-radius:3px !important;padding:11px 12px !important;margin:1.1em 0 !important}
.kohthai-delivery-estimate{display:flex !important;align-items:flex-start !important;gap:10px;margin:0 !important;
 font-size:14px;line-height:1.45;color:#6f6459}
/* The <i> carries an inline margin-right, hence !important. font-size:0 hides the glyph; the
   icon itself comes from the background image so the markup never has to change. */
.kohthai-delivery-estimate i{font-size:0 !important;margin:1px 0 0 0 !important;width:20px;height:20px;
 flex:none;display:inline-block;background:url("' . $brand . '") no-repeat center/contain}
.kohthai-delivery-estimate .kohthai-delivery-text{flex:1;min-width:0}
.kohthai-delivery-estimate strong,.kohthai-delivery-estimate b{color:#1f1a15;font-weight:700}
.kohthai-delivery-estimate a,.kohthai-location-selector a{color:#654321;text-underline-offset:2px}

/* Backorder: amber, not an alarm. It was #b30000 on #fff0f0 — the loudest thing in the buy box,
   outweighing the price, for something important but not an emergency. */
.kohthai-delivery-estimate-container.kohthai-backorder-notice{background:#fdf6e7 !important;
 border-color:#eeddb8 !important;color:#8a6110 !important}
.kohthai-backorder-notice .kohthai-delivery-estimate{color:#8a6110 !important}
.kohthai-backorder-notice .kohthai-delivery-estimate strong,
.kohthai-backorder-notice .kohthai-delivery-estimate b{color:#6f4a0c !important}
.kohthai-backorder-notice .kohthai-delivery-estimate i{background-image:url("' . $amber . '") !important}

/* The zone picker inherits the same type as everything else in the column. */
.kohthai-location-selector,.kohthai-delivery-estimate-container select{font-size:13px}
.kohthai-delivery-estimate-container select{max-width:100%;margin-top:6px}
';
	}

	/* =====================================================================
	 * [kt_lead] · [kt_details] · [kt_frames]
	 *
	 * These replace ~40 lines of hand-pasted HTML per product. That HTML was fine once, but it
	 * meant every product needed inline SVG copied by hand, and a single mistyped tag broke the
	 * layout silently. Same reasoning as [kt_features].
	 * ===================================================================== */

	/** Spec icons. Same 24px / 1.5-stroke system as the feature row; paths only. */
	private static function spec_icons() {
		return array(
			'length'  => '<path d="M3 12h18"/><path d="M5.5 9v6M18.5 9v6"/>',
			'height'  => '<path d="M12 3v18"/><path d="M9 5.5h6M9 18.5h6"/>',
			'width'   => '<path d="M7 5.5 4 12l3 6.5"/><path d="M17 5.5 20 12l-3 6.5"/><path d="M5 12h14"/>',
			'weight'  => '<path d="M5.5 8.5h13l-1.2 11.5H6.7z"/><path d="M9 8.5V6.4a3 3 0 0 1 6 0v2.1"/>',
			'material' => '<path d="M12 3.5 3.5 8 12 12.5 20.5 8z"/><path d="M3.5 12 12 16.5 20.5 12"/><path d="M3.5 16 12 20.5 20.5 16"/>',
			'care'     => '<path d="M12 3.5s6 6.4 6 10a6 6 0 0 1-12 0c0-3.6 6-10 6-10z"/>',
			'avoid'    => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/>',
			'storage'  => '<path d="M4 7.5h16v11a1.6 1.6 0 0 1-1.6 1.5H5.6A1.6 1.6 0 0 1 4 18.5z"/><path d="M4 7.5 6 4h12l2 3.5"/><path d="M10 11h4"/>',
			'phone'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 5h3"/>',
			'tablet'   => '<rect x="4.5" y="3" width="15" height="18" rx="2"/><path d="M10.5 18h3"/>',
			'laptop'   => '<rect x="3.5" y="5" width="17" height="11" rx="1.6"/><path d="M2 19h20"/>',
		);
	}

	/** The lead paragraph. Enclosing, because prose inside an attribute breaks on quotes. */
	public static function lead_shortcode( $atts, $content = '' ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}
		// do_shortcode so a link or <strong> written by hand still works.
		return '<p class="kt-lead">' . do_shortcode( wp_kses_post( $content ) ) . '</p>';
	}

	/**
	 * [kt_details length="28 cm" height="19 cm" width="11 cm" weight="650 g"
	 *             notes="First note | Second note"
	 *             fits="phone"
	 *             material="PU leather" care="Wipe with a damp cloth"
	 *             avoid="Direct sunlight and heat" storage="Keep in the dust bag"]
	 *
	 * Notes are separated by a PIPE, not a comma — the notes themselves contain commas
	 * ("Fits a phone, wallet, makeup"), and comma-splitting is exactly the trap the features
	 * list already fell into.
	 *
	 * `fits` lists only what DOES fit. Anything in the device list not named gets a cross, so
	 * fits="phone" renders phone ✓, tablet ✗, laptop ✗. Leave it empty to hide that row.
	 *
	 * A column with nothing in it is not rendered, so a product with no dimensions still gets a
	 * tidy single-column Materials block.
	 */
	public static function details_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'length' => '', 'height' => '', 'width' => '', 'weight' => '',
			'notes'  => '', 'fits'   => '',
			'material' => '', 'care' => '', 'avoid' => '', 'storage' => '',
			'size_title' => 'Size &amp; Fit',
			'care_title' => 'Materials &amp; Care',
			'devices'    => 'phone,tablet,laptop',
		), $atts, 'kt_details' );

		$icons = self::spec_icons();

		// ---- left column: size & fit -------------------------------------
		$left = '';

		$specs = '';
		foreach ( array( 'length' => 'Length', 'height' => 'Height', 'width' => 'Width', 'weight' => 'Weight' ) as $key => $label ) {
			$val = trim( (string) $a[ $key ] );
			if ( '' === $val ) {
				continue;
			}
			$specs .= '<div>' . self::svg( $icons[ $key ] )
				. '<span><b>' . esc_html( $val ) . '</b><em>' . esc_html( $label ) . '</em></span></div>';
		}
		if ( '' !== $specs ) {
			$left .= '<div class="kt-spec">' . $specs . '</div>';
		}

		$notes = array_filter( array_map( 'trim', explode( '|', (string) $a['notes'] ) ) );
		if ( $notes ) {
			$left .= '<ul class="kt-notes">';
			foreach ( $notes as $n ) {
				$left .= '<li>' . esc_html( $n ) . '</li>';
			}
			$left .= '</ul>';
		}

		$devices = array_filter( array_map( 'trim', explode( ',', (string) $a['devices'] ) ) );
		$fits    = array_map( 'strtolower', array_filter( array_map( 'trim', explode( ',', (string) $a['fits'] ) ) ) );
		if ( $devices && $fits ) {
			$row = '';
			foreach ( $devices as $dev ) {
				$key = sanitize_key( $dev );
				if ( ! isset( $icons[ $key ] ) ) {
					continue;
				}
				$yes  = in_array( strtolower( $dev ), $fits, true );
				$mark = $yes
					? '<svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#2f6b42" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.3l2.6 2.6L16 9.5"/></svg>'
					: '<svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>';
				$row .= '<div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
					. $icons[ $key ] . '</svg>' . $mark
					. '<span>' . esc_html( ucfirst( $dev ) ) . '</span>'
					. '<span class="screen-reader-text">' . ( $yes ? esc_html__( 'fits', 'kohthai-product-blocks' ) : esc_html__( 'does not fit', 'kohthai-product-blocks' ) ) . '</span></div>';
			}
			if ( '' !== $row ) {
				$left .= '<div class="kt-fits">' . $row . '</div>';
			}
		}

		// ---- right column: materials & care -------------------------------
		$right = '';
		foreach ( array( 'material' => 'Material', 'care' => 'Care', 'avoid' => 'Avoid', 'storage' => 'Storage' ) as $key => $label ) {
			$val = trim( (string) $a[ $key ] );
			if ( '' === $val ) {
				continue;
			}
			$right .= '<div>' . self::svg( $icons[ $key ] )
				. '<span><b>' . esc_html( $label ) . '</b><span>' . esc_html( $val ) . '</span></span></div>';
		}
		if ( '' !== $right ) {
			$right = '<div class="kt-care">' . $right . '</div>';
		}

		if ( '' === $left && '' === $right ) {
			return '';
		}

		$out = '<div class="kt-cols">';
		if ( '' !== $left ) {
			$out .= '<div><h4 class="kt-h">' . esc_html( wp_specialchars_decode( $a['size_title'] ) ) . '</h4>' . $left . '</div>';
		}
		if ( '' !== $right ) {
			$out .= '<div><h4 class="kt-h">' . esc_html( wp_specialchars_decode( $a['care_title'] ) ) . '</h4>' . $right . '</div>';
		}
		return $out . '</div>';
	}

	/**
	 * [kt_frames ids="1234,1235,1236"]
	 *
	 * The worn-on-a-person strip from the design. A flat product shot cannot answer "how big is
	 * it really" — four photos of someone carrying it can, in about a second.
	 *
	 * Takes WordPress media IDs. An id that is not an image is skipped rather than leaving a gap.
	 */
	public static function frames_shortcode( $atts ) {
		$a = shortcode_atts( array(
			'ids'   => '',
			'title' => 'In Frame',
			'sub'   => 'Worn on a real person, so you can judge the size',
			'size'  => 'medium',
		), $atts, 'kt_frames' );

		$ids = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $a['ids'] ) ) ) );
		if ( ! $ids ) {
			return '';
		}

		$items = '';
		foreach ( $ids as $id ) {
			$img = wp_get_attachment_image( $id, $a['size'], false, array( 'loading' => 'lazy' ) );
			if ( ! $img ) {
				continue; // a deleted or non-image attachment must not leave a hole
			}
			$items .= '<div>' . $img . '</div>';
		}
		if ( '' === $items ) {
			return '';
		}

		$title = trim( (string) $a['title'] );
		$sub   = trim( (string) $a['sub'] );

		return '<div class="kt-frames">'
			. ( '' !== $title ? '<h4 class="kt-h">' . esc_html( $title ) . '</h4>' : '' )
			. ( '' !== $sub ? '<p class="kt-frames__sub">' . esc_html( $sub ) . '</p>' : '' )
			. '<div class="kt-frames__row">' . $items . '</div></div>';
	}

	/* =====================================================================
	 * Settings screen
	 * ===================================================================== */

	public static function menu() {
		add_options_page( 'Kohthai Product Blocks', 'Product Blocks', 'manage_options',
			'kohthai-product-blocks', array( __CLASS__, 'screen' ) );
	}

	public static function screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o     = self::options();
		$icons = self::icons();
		?>
		<div class="wrap">
			<h1>Kohthai Product Blocks</h1>

			<div class="card" style="max-width:840px;padding:20px;border-left:4px solid #654321">
				<h2 style="margin-top:0">Feature icons</h2>
				<p>Set them <strong>per product</strong> — Products → edit → Product data → General →
					<strong>Feature icons</strong>. They render just below the Add to Cart button.</p>
				<p style="color:#6f6459">
					The old shortcode still works if you prefer it in the short description:
					<code>[kt_features items="adjustable-strap, zipper"]</code> — but that renders
					<em>above</em> the button, because the short description is output before the cart
					form. <strong>Use one or the other, not both, or the row appears twice.</strong>
				</p>
			</div>

			<div class="card" style="max-width:840px;padding:20px;margin-top:20px;border-left:4px solid #a08565">
				<h2 style="margin-top:0">Writing a product description</h2>
				<p>Three lines replace the block of HTML this used to need. Paste into the product&rsquo;s
					<strong>Description</strong> field.</p>
				<textarea readonly onclick="this.select()" rows="7"
					style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #c3c4c7;border-radius:4px">[kt_lead]Add a touch of timeless elegance to your collection with this beautifully designed magnetic flap crossbody bag.[/kt_lead]

[kohthai_shorts ids="VIDEO_ID" title="" icon="false" feature_title="Why you will love it" features="Fits your phone and wallet,Wear on shoulder or crossbody,Light enough for all day"]

[kt_details length="28 cm" height="19 cm" width="11 cm" weight="650 g" fits="phone" notes="Fits a phone, wallet, makeup and your daily essentials | Size and weight may vary slightly with the material" material="PU leather with embroidered flap" care="Wipe with a dry or slightly damp cloth" avoid="Direct sunlight and heat" storage="Keep in the dust bag when not in use"]</textarea>
				<table class="widefat striped" style="margin-top:16px">
					<thead><tr><th style="width:120px">Attribute</th><th>What it does</th></tr></thead>
					<tbody>
						<tr><td><code>length</code> <code>height</code><br><code>width</code> <code>weight</code></td>
							<td>Each one you fill in gets an icon and a label. Leave any of them out and it is simply not shown.</td></tr>
						<tr><td><code>notes</code></td><td>Bullet points, separated by a <strong>pipe</strong> <code>|</code> &mdash; not a comma, because the notes themselves contain commas.</td></tr>
						<tr><td><code>fits</code></td><td>Which devices fit, comma separated: <code>fits="phone"</code> shows phone with a tick and tablet and laptop with a cross. Leave empty to hide that row entirely.</td></tr>
						<tr><td><code>material</code> <code>care</code><br><code>avoid</code> <code>storage</code></td><td>The right-hand column. Same rule &mdash; anything you leave out is not shown.</td></tr>
						<tr><td><code>size_title</code><br><code>care_title</code></td><td>Rename either column heading if you want.</td></tr>
					</tbody></table>
				<p style="margin:14px 0 0;color:#6f6459">If a product has no dimensions, just leave those out &mdash;
					the block drops to a single tidy column instead of showing an empty half.</p>

				<h2 style="margin-top:26px">[kt_frames] &mdash; worn-on-a-person strip</h2>
				<p>A flat product shot cannot answer &ldquo;how big is it really&rdquo;. Four photos of someone
					carrying it can. Upload the photos, then paste their media IDs:</p>
				<code style="display:block;padding:10px;background:#f6f7f7">[kt_frames ids="1234, 1235, 1236, 1237"]</code>
				<p style="margin:12px 0 0;color:#6f6459">Media IDs are in the URL when you open an image in
					<strong>Media &rarr; Library</strong> (<code>item=1234</code>). Scrolls sideways on a phone.</p>
			</div>

			<div class="card" style="max-width:840px;padding:20px;margin-top:20px">
				<h2 style="margin-top:0">Available icons</h2>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px">
					<?php foreach ( $icons as $key => $icon ) : ?>
						<div style="text-align:center;border:1px solid #e0e0e0;border-radius:4px;padding:14px 8px;background:#fff">
							<div style="color:#654321"><?php echo self::svg( $icon[1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
							<div style="font-size:12px;font-weight:600;margin-top:8px"><?php echo esc_html( $icon[0] ); ?></div>
							<input type="text" readonly onclick="this.select()" value="<?php echo esc_attr( $key ); ?>"
								style="width:100%;margin-top:6px;font-family:monospace;font-size:10px;text-align:center;border:1px solid #ddd;background:#f6f7f7;padding:3px">
						</div>
					<?php endforeach; ?>
				</div>
				<style>.wrap .card svg{width:30px;height:30px}</style>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'kt_blocks_group' ); ?>

				<div class="card" style="max-width:840px;padding:20px;margin-top:20px">
					<h2 style="margin-top:0">Trust row — <code>[kt_trust]</code></h2>
					<p>Paste <code>[kt_trust]</code> into <strong>Customize → WooCommerce → Product Page →
						HTML after Add To Cart button</strong>, above your <code>[kohthai_chat_order]</code>.</p>

					<p style="max-width:52em;color:#4a4038">
						Each tile can say something different on a <strong>backorder</strong> product.
						By default only the shipping tile changes &mdash; &ldquo;Ships from Dhaka / Not a
						pre-order&rdquo; becomes &ldquo;Ships in 1-2 weeks / Ordered in specially for you&rdquo;.
						Cash on delivery, the return window and authenticity are true either way, so they
						are left alone.
					</p>

					<div class="notice notice-warning inline" style="max-width:52em;padding:10px 12px;margin:12px 0">
						<p style="margin:0"><strong>Keep this honest.</strong> Kohthai's policy is a
						<em>conditional</em> 7-day return — damaged, incorrect, incomplete or not as advertised —
						and the customer pays return shipping. Never write "no questions asked", "free returns"
						or "hassle-free returns". And these are bags: no warranty claim belongs on the page.</p>
					</div>

					<table class="form-table" role="presentation">
						<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
							<tr>
								<th scope="row">Tile <?php echo (int) $i; ?></th>
								<td>
									<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[trust_<?php echo (int) $i; ?>_title]"
										value="<?php echo esc_attr( $o[ "trust_{$i}_title" ] ); ?>" placeholder="Title">
									<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[trust_<?php echo (int) $i; ?>_sub]"
										value="<?php echo esc_attr( $o[ "trust_{$i}_sub" ] ); ?>" placeholder="Small print">
									<p class="description" style="margin-bottom:6px">Leave the title empty to hide this tile.</p>

									<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[trust_<?php echo (int) $i; ?>_bo_title]"
										value="<?php echo esc_attr( $o[ "trust_{$i}_bo_title" ] ); ?>" placeholder="Title when on backorder">
									<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[trust_<?php echo (int) $i; ?>_bo_sub]"
										value="<?php echo esc_attr( $o[ "trust_{$i}_bo_sub" ] ); ?>" placeholder="Small print when on backorder">
									<p class="description">Used only on backorder products. Leave empty and this tile keeps its normal wording.</p>
								</td>
							</tr>
						<?php endfor; ?>
						<tr>
							<th scope="row"><label for="kt_returns_url">Returns policy link</label></th>
							<td><input type="text" class="regular-text" id="kt_returns_url"
									name="<?php echo esc_attr( self::OPTION ); ?>[returns_url]"
									value="<?php echo esc_attr( $o['returns_url'] ); ?>">
								<p class="description">Tile 2 links here. A path like <code>/returns_refund/</code> is fine.</p></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:840px;padding:20px;margin-top:20px">
					<h2 style="margin-top:0">Styles moved out of Additional CSS</h2>
					<p>These used to live in <strong>Customize → Additional CSS</strong>. They are here so the
						product page lives in one place. Switch either off if you want to go back to
						hand-editing it.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Theme fixes</th>
							<td><label><input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION ); ?>[css_theme_fixes]"
								<?php checked( ! empty( $o['css_theme_fixes'] ) ); ?>> On</label>
								<p class="description">The <code>[ux_video]</code> gap fix (still needed — most products
									use it), the colour name under each swatch, and the accordion header
									(no fill, toggle on the right so the arrow stops sitting on the title).</p></td>
						</tr>
						<tr>
							<th scope="row">Stock line</th>
							<td><label><input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION ); ?>[enable_stock_line]"
								<?php checked( ! empty( $o['enable_stock_line'] ) ); ?>> On</label>
							<input type="text" class="regular-text" style="margin-left:10px"
								name="<?php echo esc_attr( self::OPTION ); ?>[stock_text]"
								value="<?php echo esc_attr( $o['stock_text'] ); ?>">
							<p class="description">Shown under the price <strong>only where WooCommerce prints nothing</strong>
								&mdash; which is most of your products, since WooCommerce only speaks up for managed stock
								and backorders. Backorder items are skipped because the delivery estimate already states
								the wait.</p></td>
						</tr>
						<tr>
							<th scope="row">Match the other plugins</th>
							<td><label><input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION ); ?>[css_plugin_harmony]"
								<?php checked( ! empty( $o['css_plugin_harmony'] ) ); ?>> On</label>
								<p class="description">Restyles the <strong>Smart Delivery</strong> estimate box to match
									the trust row — warm tile instead of cool grey, matching truck icon, and the
									backorder notice in amber rather than alarm red. Style only; that plugin's
									logic is untouched.</p></td>
						</tr>
						<tr>
							<th scope="row">Product details type scale</th>
							<td><label><input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION ); ?>[css_product_details]"
								<?php checked( ! empty( $o['css_product_details'] ) ); ?>> On</label>
								<p class="description">Styles for <code>.kt-lead</code>, <code>.kt-cols</code>,
									<code>.kt-h</code>, <code>.kt-spec</code>, <code>.kt-notes</code>,
									<code>.kt-fits</code> and <code>.kt-care</code> in the description.</p></td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="card" style="max-width:840px;padding:20px;margin-top:20px">
				<h2 style="margin-top:0">What to delete from Additional CSS</h2>
				<p>Once this plugin is active, remove everything from <strong>Customize → Additional CSS</strong>
					<em>except</em> your reCAPTCHA badge rule. Specifically:</p>
				<ul style="list-style:disc;padding-left:20px;color:#4a4038;max-width:56em">
					<li>the <code>.video-fit</code> block — now here</li>
					<li>the <code>.ux-swatches--large</code> block — now here</li>
					<li>the whole <code>.kt-feat</code> block — <strong>dead code</strong>, no product uses that markup any more</li>
					<li><code>.kt-lead</code>, <code>.kt-cols</code>, <code>.kt-h</code>, <code>.kt-spec</code>,
						<code>.kt-notes</code>, <code>.kt-fits</code>, <code>.kt-care</code> — now here.
						You had <code>.kt-h</code> and <code>.kt-spec b</code> declared <strong>twice</strong>;
						only the winning values (16px and 17px) are kept</li>
					<li>the <code>.accordion-title</code> block — now here, <strong>with the arrow bug fixed</strong></li>
					<li><code>.kts-feat-title</code> — now here</li>
				</ul>
				<p style="color:#9a3b30"><strong>Keep</strong> <code>.grecaptcha-badge { visibility: hidden; }</code> —
					that is yours and nothing to do with this plugin.</p>
			</div>
		</div>
		<?php
	}
}

Kohthai_Product_Blocks::init();
