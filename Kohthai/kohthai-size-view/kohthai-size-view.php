<?php
/**
 * Plugin Name:       Kohthai Size View
 * Plugin URI:        https://kohthaibd.com/
 * Description:       "See how big it really is" — the bag on a woman of your own height, what actually fits inside it, and its true size on your screen. Built to answer the question that causes returns.
 * Version:           1.17.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kohthai
 * Text Domain:       kohthai-size-view
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * Returns come from two things: "I don't like it" and "it isn't the size I
 * expected". This is the second half. The measurements are already printed on
 * every product page and customers still misjudge them, because "27 × 22 × 8 cm"
 * is not a picture — nobody can see a number.
 *
 * Three answers, three tabs:
 *   On you      — the bag beside a figure set to HER height, at true scale.
 *                 This is the one that changes minds.
 *   Will it fit — everyday things drawn at true scale against the opening.
 *
 * ---------------------------------------------------------------------------
 * WHERE THE NUMBERS COME FROM (this order matters)
 * ---------------------------------------------------------------------------
 *   1. Explicit shortcode attributes: [kt_size_view across="27" tall="22" deep="8"]
 *   2. The product's own [kt_details] block — the page is the source of truth.
 *   3. WooCommerce's dimension fields, as a last resort.
 *
 * Step 3 needs care. WooCommerce's middle field is labelled "Width" but here it
 * holds DEPTH, and on many products depth and height were entered the other way
 * round because the page prints them in a different order. Rather than trust the
 * field order, we apply one rule that holds for every handbag ever made: a bag is
 * not deeper than it is tall. If the numbers say otherwise, they are swapped.
 *
 * A product with no usable size renders NOTHING rather than a guess. A wrong size
 * drawn confidently is worse than no drawing — it is the exact mistake this
 * plugin exists to stop.
 *
 * ---------------------------------------------------------------------------
 * THE BAG ARTWORK
 * ---------------------------------------------------------------------------
 * The bag is drawn as a vector silhouette whose PROPORTIONS come from the real
 * measurements. That means it works today, for every product, with no new
 * photography.
 *
 * It is also built to be replaced. Give a product a cut-out PNG (transparent
 * background, cropped tight to the bag body) in the `cutout` attribute or the
 * `_kt_size_cutout` meta field and that photograph is placed at exactly the same
 * true-scale geometry instead of the drawing. Swapping in real product images is
 * then a data job, not a rewrite.
 */

defined( 'ABSPATH' ) || exit;

final class Kohthai_Size_View {

	const VERSION = '1.17.0';

	/** Assets print once, in the footer, and only if something rendered. */
	private static $used = false;

	const MENU  = 'kt-size-view';
	const NONCE = 'kt_size_view_save';

	/** A token that appears in the inline JS, so WP Rocket can be told to leave
	 *  it alone. See exclude_from_rocket_delay(). */
	const DELAY_MARK = 'ktSizeViewModule';

	public static function init() {
		add_shortcode( 'kt_size_view', array( __CLASS__, 'shortcode' ) );
		// ⭐ The stylesheet goes in the HEAD. Printed in the footer it arrived
		// after the button had already been painted, so on every refresh the
		// button flashed up full-width and unstyled before snapping into place.
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 5 );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_from_rocket_delay' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_assets' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		// Sits after the gallery (images run at priority 20), so the button can be
		// lifted into it by the script without depending on the theme's markup.
		add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'auto_button' ), 25 );
		add_action( 'admin_post_kt_size_view_save', array( __CLASS__, 'admin_save' ) );
	}

	/* =====================================================================
	 * Data
	 * ================================================================== */

	/**
	 * Read one number out of a value like "27 cm", "6.5", "31-37 cm".
	 * A range keeps its larger end; the caller is told, so it can say so.
	 */
	private static function num( $raw, &$was_range = false ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return 0.0;
		}
		$s = str_replace( array( "\xe2\x80\x93", "\xe2\x80\x94" ), '-', $raw );
		$s = preg_replace( '/[^0-9.\-]/', '', $s );
		if ( '' === $s || '.' === $s || '-' === $s ) {
			return 0.0;
		}
		if ( false !== strpos( $s, '-' ) ) {
			$parts = array_values( array_filter( array_map( 'trim', explode( '-', $s ) ), 'strlen' ) );
			if ( count( $parts ) >= 2 ) {
				$was_range = true;
				return (float) max( array_map( 'floatval', $parts ) );
			}
			$s = str_replace( '-', '', $s );
		}
		return is_numeric( $s ) ? (float) $s : 0.0;
	}

	/** Pull the [kt_details] attributes off a product, wherever they are stored. */
	private static function details_atts( $post_id ) {
		if ( ! $post_id ) {
			return null;
		}
		$hay  = array();
		$post = get_post( $post_id );
		if ( $post ) {
			$hay[] = $post->post_content;
			$hay[] = $post->post_excerpt;
		}
		foreach ( (array) get_post_meta( $post_id ) as $values ) {
			foreach ( (array) $values as $v ) {
				if ( is_string( $v ) && false !== strpos( $v, '[kt_details' ) ) {
					$hay[] = $v;
				}
			}
		}
		$regex = get_shortcode_regex( array( 'kt_details' ) );
		foreach ( $hay as $content ) {
			if ( ! is_string( $content ) || false === strpos( $content, '[kt_details' ) ) {
				continue;
			}
			if ( preg_match( '/' . $regex . '/s', $content, $m ) ) {
				$atts = shortcode_parse_atts( $m[3] );
				if ( is_array( $atts ) ) {
					return $atts;
				}
			}
		}
		return null;
	}

	/**
	 * Work out across / tall / deep, in centimetres.
	 *
	 * @return array{across:float,tall:float,deep:float,source:string,range:bool}|null
	 */
	private static function resolve_dimensions( $atts, $post_id ) {
		$range = false;

		// 1. Told to us directly.
		$a = self::num( isset( $atts['across'] ) ? $atts['across'] : '', $range );
		$t = self::num( isset( $atts['tall'] ) ? $atts['tall'] : '', $range );
		$d = self::num( isset( $atts['deep'] ) ? $atts['deep'] : '', $range );
		if ( $a > 0 && $t > 0 ) {
			return array( 'across' => $a, 'tall' => $t, 'deep' => $d, 'source' => 'shortcode', 'range' => $range );
		}

		// 2. The page's own [kt_details] block. Its labels are unambiguous:
		//    length = across, height = tall, width = depth.
		$det = self::details_atts( $post_id );
		if ( $det ) {
			$a = self::num( isset( $det['length'] ) ? $det['length'] : '', $range );
			$t = self::num( isset( $det['height'] ) ? $det['height'] : '', $range );
			$d = self::num( isset( $det['width'] ) ? $det['width'] : '', $range );
			if ( $a > 0 && $t > 0 ) {
				return array( 'across' => $a, 'tall' => $t, 'deep' => $d, 'source' => 'kt_details', 'range' => $range );
			}
		}

		// 3. WooCommerce, with the sanity rule.
		if ( function_exists( 'wc_get_product' ) && $post_id ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$a = (float) $product->get_length();
				$d = (float) $product->get_width();
				$t = (float) $product->get_height();

				// A handbag is never deeper than it is tall. When the stored
				// numbers claim otherwise, depth and height were entered the
				// wrong way round.
				if ( $d > 0 && $t > 0 && $d > $t ) {
					$swap = $d;
					$d    = $t;
					$t    = $swap;
				}
				if ( $a > 0 && $t > 0 ) {
					return array( 'across' => $a, 'tall' => $t, 'deep' => $d, 'source' => 'woocommerce', 'range' => false );
				}
			}
		}

		return null;
	}

	/**
	 * Which silhouette to draw, and therefore how the bag is carried. The
	 * drawing is stylised, so this only has to be the right FAMILY — the
	 * proportions come from the real measurements.
	 */
	private static function infer_shape( $atts, $post_id ) {
		if ( ! empty( $atts['shape'] ) ) {
			$s = sanitize_key( $atts['shape'] );
			if ( in_array( $s, array( 'tote', 'clutch', 'crossbody', 'shoulder', 'handbag' ), true ) ) {
				return $s;
			}
		}
		$hay = strtolower( (string) get_the_title( $post_id ) );
		if ( $post_id ) {
			$terms = get_the_terms( $post_id, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$hay .= ' ' . strtolower( $term->name );
				}
			}
		}
		$map = array(
			'tote'      => array( 'tote', 'shopper', 'commuter' ),
			'clutch'    => array( 'clutch', 'evening', 'dinner', 'pouch' ),
			'crossbody' => array( 'crossbody', 'cross body', 'messenger', 'sling', 'saddle' ),
			'handbag'   => array( 'handbag', 'satchel', 'pillow', 'top handle' ),
			'shoulder'  => array( 'shoulder', 'hobo', 'baguette' ),
		);
		foreach ( $map as $shape => $needles ) {
			foreach ( $needles as $n ) {
				if ( false !== strpos( $hay, $n ) ) {
					return $shape;
				}
			}
		}
		return 'shoulder';
	}

	/** The shipped cut-out manifest, loaded once. */
	private static function manifest() {
		static $map = null;
		if ( null === $map ) {
			$file = __DIR__ . '/cutouts.php';
			$map  = is_readable( $file ) ? (array) require $file : array();
		}
		return $map;
	}

	/**
	 * The cut-out photograph that replaces the drawn silhouette, and the body
	 * box inside it.
	 *
	 * The body box is the whole point. A product photo includes the strap; the
	 * measurements do not. Scaling the entire photograph to "27 × 22 cm" would
	 * draw every bag far too small. So each entry records where the bag body
	 * sits inside its own image, and the plugin scales until THAT matches.
	 *
	 * @return array{url:string,body:array}|null
	 */
	/**
	 * Read four fractions, or nothing.
	 *
	 * Strict on purpose: this is what decides whether a hand-drawn box is kept
	 * or thrown away, so "nearly valid" has to fail rather than be guessed at.
	 *
	 * @return array<int,float>|null
	 */
	public static function valid_body( $raw ) {
		$parts = array_map( 'floatval', array_map( 'trim', explode( ',', (string) $raw ) ) );
		if ( 4 !== count( $parts ) ) {
			return null;
		}
		if ( $parts[2] <= 0 || $parts[3] <= 0 || $parts[0] < 0 || $parts[1] < 0 ) {
			return null;
		}
		if ( $parts[0] + $parts[2] > 1.001 || $parts[1] + $parts[3] > 1.001 ) {
			return null;
		}
		return $parts;
	}

	/**
	 * What a save should do with one product's body box.
	 *
	 * Kept apart from admin_save() because admin_save() ends in a redirect and
	 * an exit, which makes it untestable, and this is the rule that was wrong.
	 *
	 * @return array{action:string,box:array<int,float>|null}
	 */
	public static function plan_body_box( $att, $raw, $redetect ) {
		$box = $redetect ? null : self::valid_body( $raw );
		if ( $box ) {
			return array( 'action' => 'save', 'box' => $box );
		}
		// Nothing usable was given. With an uploaded photograph it can be
		// measured; with a shipped one, forgetting the override falls back to
		// the box that ships with the plugin, which the same detector produced.
		return array( 'action' => $att ? 'detect' : 'forget', 'box' => null );
	}

	private static function cutout( $atts, $post_id ) {
		$parse_body = static function ( $raw ) {
			$parts = self::valid_body( $raw );
			return $parts ? $parts : array( 0.0, 0.0, 1.0, 1.0 );
		};

		if ( ! empty( $atts['cutout'] ) ) {
			return array(
				'url'  => esc_url_raw( $atts['cutout'] ),
				'body' => $parse_body( isset( $atts['cutout_body'] ) ? $atts['cutout_body'] : '' ),
			);
		}

		if ( $post_id ) {
			$meta = get_post_meta( $post_id, '_kt_size_cutout', true );
			$url  = '';
			if ( is_numeric( $meta ) ) {
				$url = (string) wp_get_attachment_image_url( (int) $meta, 'large' );
			} elseif ( is_string( $meta ) && '' !== $meta ) {
				$url = esc_url_raw( $meta );
			}
			if ( '' !== $url ) {
				return array( 'url' => $url, 'body' => $parse_body( get_post_meta( $post_id, '_kt_size_cutout_body', true ) ) );
			}

			$post = get_post( $post_id );
			if ( $post ) {
				$map = self::manifest();
				if ( isset( $map[ $post->post_name ] ) ) {
					$entry = $map[ $post->post_name ];
					// ⭐ A box drawn on the settings screen wins over the one that
					// ships with the plugin. Without this the shipped box was the
					// only one a shipped photograph could ever use, so correcting
					// it by hand changed nothing on the product page.
					$own = self::valid_body( get_post_meta( $post_id, '_kt_size_cutout_body', true ) );
					return array(
						'url'  => plugins_url( 'assets/bags/' . $entry['file'], __FILE__ ),
						'body' => $own ? $own : $entry['body'],
					);
				}
			}
		}
		return null;
	}

	/**
	 * Everything another plugin needs to draw this bag at true size.
	 *
	 * ⭐ The app draws its own size view natively rather than showing a web page
	 * in a box, so it needs the same numbers this plugin renders from — and it
	 * must get them from HERE rather than working them out again. The resolution
	 * order is not obvious (the page's own [kt_details] beats WooCommerce,
	 * because WooCommerce's width and height are transposed on most of this
	 * shop) and a second copy of that rule would drift from this one, which is
	 * how the website and the app would end up disagreeing about a bag in front
	 * of a customer who is looking at both.
	 *
	 * @param int $post_id Product.
	 * @return array{across:float,tall:float,deep:float,source:string,range:bool,
	 *               cutout:string,body:array<int,float>}|null Null when the
	 *               product has no usable measurements.
	 */
	public static function size_payload( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return null;
		}

		$dims = self::resolve_dimensions( array(), $post_id );
		if ( ! $dims || $dims['across'] <= 0 || $dims['tall'] <= 0 ) {
			return null;
		}

		$cut = self::cutout( array(), $post_id );

		return array(
			'across' => (float) $dims['across'],
			'tall'   => (float) $dims['tall'],
			'deep'   => (float) $dims['deep'],
			// Where the numbers came from, so a wrong one can be traced to the
			// place that needs correcting rather than guessed at.
			'source' => (string) $dims['source'],
			// A soft bag quoted as a range: the larger end is used, and the app
			// should say so the way the website does.
			'range'  => ! empty( $dims['range'] ),
			'cutout' => $cut ? (string) $cut['url'] : '',
			'body'   => $cut ? array_map( 'floatval', $cut['body'] ) : array( 0.0, 0.0, 1.0, 1.0 ),
		);
	}

	/* =====================================================================
	 * Render
	 * ================================================================== */

	/**
	 * [kt_size_view]
	 * [kt_size_view across="27" tall="22" deep="8" shape="tote"]
	 * [kt_size_view id="2193" cutout="https://…/bag-cutout.png"]
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id'     => 0,
			'across' => '',
			'tall'   => '',
			'deep'   => '',
			'shape'  => '',
			'mode'   => '',
			'hair'   => '',
			'cutout' => '',
			// Must be declared here or shortcode_atts() silently drops it and
			// every hand-supplied photograph is scaled as if it were all bag.
			'cutout_body' => '',
			'title'  => '',
			'intro'  => '',
		), $atts, 'kt_size_view' );

		$post_id = (int) $atts['id'];
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$dims = self::resolve_dimensions( $atts, $post_id );
		if ( ! $dims ) {
			// No trustworthy size. Draw nothing.
			return '';
		}

		self::$used = true;

		$mode = sanitize_key( $atts['mode'] );
		if ( '' === $mode ) {
			$mode = 'inline';
		}
		$corner = sanitize_key( (string) get_option( 'kt_size_view_corner', 'bottom-right' ) );
		if ( ! in_array( $corner, array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' ), true ) ) {
			$corner = 'bottom-right';
		}

		$shape  = self::infer_shape( $atts, $post_id );
		$hair   = sanitize_key( $atts['hair'] );
		if ( ! in_array( $hair, array( 'ponytail', 'bun', 'down' ), true ) ) {
			$hair = 'ponytail';
		}
		$cutout = self::cutout( $atts, $post_id );
		$title  = '' !== $atts['title'] ? $atts['title'] : __( 'See how big it really is', 'kohthai-size-view' );
		$intro  = '' !== $atts['intro'] ? $atts['intro'] : __( 'A measurement is hard to picture. Set your own height and see the bag exactly as it will look on you.', 'kohthai-size-view' );

		$fmt = static function ( $v ) {
			return rtrim( rtrim( number_format( (float) $v, 1, '.', '' ), '0' ), '.' );
		};

		ob_start();
		if ( 'modal' === $mode ) :
		?>
<div class="kt-sv-launch" data-kt-launch data-corner="<?php echo esc_attr( $corner ); ?>">
	<button type="button" class="kt-sv-btn" data-kt-open>
		<svg class="kt-sv-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
			stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<rect x="2" y="8" width="20" height="8" rx="1.5"/>
			<path d="M6 8v3M10 8v4M14 8v3M18 8v4"/>
		</svg>
		<?php esc_html_e( 'See bag size', 'kohthai-size-view' ); ?>
	</button>
	<div class="kt-sv-modal" data-kt-modal hidden>
		<div class="kt-sv-modal__scrim" data-kt-close></div>
		<div class="kt-sv-modal__panel" role="dialog" aria-modal="true"
			aria-label="<?php esc_attr_e( 'See bag size', 'kohthai-size-view' ); ?>">
			<button type="button" class="kt-sv-modal__close" data-kt-close
				aria-label="<?php esc_attr_e( 'Close', 'kohthai-size-view' ); ?>">&times;</button>
		<?php endif; ?>
<section class="kt-sv" data-kt-sv
	data-across="<?php echo esc_attr( $fmt( $dims['across'] ) ); ?>"
	data-tall="<?php echo esc_attr( $fmt( $dims['tall'] ) ); ?>"
	data-deep="<?php echo esc_attr( $fmt( $dims['deep'] ) ); ?>"
	data-shape="<?php echo esc_attr( $shape ); ?>"
	data-hair="<?php echo esc_attr( $hair ); ?>"
	<?php if ( $cutout ) : ?>
	data-cutout="<?php echo esc_url( $cutout['url'] ); ?>"
	data-cutout-body="<?php echo esc_attr( implode( ',', $cutout['body'] ) ); ?>"
	<?php endif; ?>>

	<div class="kt-sv__head">
		<p class="kt-sv__eyebrow"><?php esc_html_e( 'True size', 'kohthai-size-view' ); ?></p>
		<h3 class="kt-sv__title"><?php echo esc_html( $title ); ?></h3>
		<p class="kt-sv__intro"><?php echo esc_html( $intro ); ?></p>
	</div>

	<div class="kt-sv__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Size views', 'kohthai-size-view' ); ?>">
		<button type="button" class="kt-sv__tab is-on" role="tab" aria-selected="true" data-view="onyou">
			<?php esc_html_e( 'On you', 'kohthai-size-view' ); ?>
		</button>
		<button type="button" class="kt-sv__tab" role="tab" aria-selected="false" data-view="fits">
			<?php esc_html_e( 'Will it fit', 'kohthai-size-view' ); ?>
		</button>

	</div>

	<div class="kt-sv__stage" data-kt-stage>
		<div class="kt-sv__scroll" data-kt-scroll>
			<div class="kt-sv__canvas" data-kt-canvas></div>
		</div>

		<!-- Her height and build. Shown on the "On you" tab, remembered between
		     products so she sets them once for the whole shop. -->
		<div class="kt-sv__controls" data-kt-height hidden>
			<div class="kt-sv__control">
				<label class="kt-sv__control-label" for="kt-sv-h-<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Your height', 'kohthai-size-view' ); ?>
					<output class="kt-sv__control-out" data-kt-height-out>155 cm</output>
				</label>
				<input class="kt-sv__range" id="kt-sv-h-<?php echo esc_attr( $post_id ); ?>" type="range"
					min="135" max="185" step="1" value="155" data-kt-height-input>
			</div>
			<div class="kt-sv__control">
				<label class="kt-sv__control-label" for="kt-sv-b-<?php echo esc_attr( $post_id ); ?>">
					<?php esc_html_e( 'Your build', 'kohthai-size-view' ); ?>
					<output class="kt-sv__control-out" data-kt-build-out>Medium</output>
				</label>
				<input class="kt-sv__range" id="kt-sv-b-<?php echo esc_attr( $post_id ); ?>" type="range"
					min="0" max="4" step="1" value="1" data-kt-build-input>
			</div>
		</div>

		<!-- The things she can try in the bag. Built by the script from one list,
		     so the shapes and the fit test can never drift apart. -->
		<div class="kt-sv__tray" data-kt-tray hidden></div>

		<p class="kt-sv__hint" data-kt-hint hidden></p>
	</div>



	<dl class="kt-sv__specs">
		<div class="kt-sv__spec">
			<dt><?php esc_html_e( 'Across', 'kohthai-size-view' ); ?></dt>
			<dd><?php echo esc_html( $fmt( $dims['across'] ) ); ?> <span>cm</span></dd>
		</div>
		<div class="kt-sv__spec">
			<dt><?php esc_html_e( 'Tall', 'kohthai-size-view' ); ?></dt>
			<dd><?php echo esc_html( $fmt( $dims['tall'] ) ); ?> <span>cm</span></dd>
		</div>
		<?php if ( $dims['deep'] > 0 ) : ?>
		<div class="kt-sv__spec">
			<dt><?php esc_html_e( 'Deep', 'kohthai-size-view' ); ?></dt>
			<dd><?php echo esc_html( $fmt( $dims['deep'] ) ); ?> <span>cm</span></dd>
		</div>
		<?php endif; ?>
	</dl>

	<?php if ( $dims['range'] ) : ?>
	<p class="kt-sv__note"><?php esc_html_e( 'This bag is soft, so it widens as it fills. The drawing shows it at its widest.', 'kohthai-size-view' ); ?></p>
	<?php endif; ?>
	<p class="kt-sv__note"><?php esc_html_e( 'Measurements are the bag body. Straps and handles are drawn faintly and are not included.', 'kohthai-size-view' ); ?></p>
</section>
		<?php if ( 'modal' === $mode ) : ?>
		</div>
	</div>
</div>
		<?php endif; ?>
		<?php
		return trim( ob_get_clean() );
	}

	/* =====================================================================
	 * Assets
	 *
	 * Inline, once, only when a module rendered. The WP Rocket / Cloudflare
	 * guards are deliberate and must stay: the module draws itself in JS, so a
	 * deferred or delayed script leaves an empty panel where the picture goes.
	 * ================================================================== */

	/**
	 * Put the "See bag size" button on every product page by itself.
	 *
	 * Nineteen products is nineteen edits otherwise, and a shortcode that has to
	 * be pasted into each description gets forgotten on the twentieth product.
	 */
	public static function auto_button() {
		if ( 'off' === get_option( 'kt_size_view_placement', 'gallery' ) ) {
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		echo do_shortcode( '[kt_size_view mode="modal"]' );
	}

	/* =====================================================================
	 * Admin — one screen to give every bag its own photograph
	 * ================================================================== */

	public static function admin_menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';
		add_submenu_page(
			$parent,
			__( 'Size View', 'kohthai-size-view' ),
			__( 'Size View', 'kohthai-size-view' ),
			'manage_woocommerce',
			self::MENU,
			array( __CLASS__, 'admin_page' )
		);
	}

	/**
	 * Work out where the bag body sits inside a photograph.
	 *
	 * This is the piece that makes "just upload a PNG" actually work. A photo
	 * includes the handle; the measurements do not. Scaling the whole image to
	 * "27 × 22 cm" would draw every bag far too small, so we have to know which
	 * part of the picture IS the bag.
	 *
	 * Rather than guess the shape, we use the one number already known — the
	 * product's own width-to-height ratio — and look for the rectangle of
	 * exactly that ratio covering the most of the bag. The box can then never be
	 * the wrong shape, so the photograph is placed with no distortion at all.
	 *
	 * @param string $path  Image file on disk.
	 * @param float  $ratio across / tall.
	 * @return array|null   [x, y, w, h] as fractions of the image.
	 */
	public static function detect_body_box( $path, $ratio ) {
		if ( ! function_exists( 'imagecreatefromstring' ) || $ratio <= 0 || ! is_readable( $path ) ) {
			return null;
		}
		$raw = @file_get_contents( $path );
		if ( ! $raw ) {
			return null;
		}
		$im = @imagecreatefromstring( $raw );
		if ( ! $im ) {
			return null;
		}
		$ow = imagesx( $im );
		$oh = imagesy( $im );
		if ( $ow < 4 || $oh < 4 ) {
			imagedestroy( $im );
			return null;
		}

		// Small is plenty — we only need fractions back, and this keeps the
		// search comfortably inside a normal PHP request.
		$scale = min( 1.0, 220 / max( $ow, $oh ) );
		$w     = max( 8, (int) round( $ow * $scale ) );
		$h     = max( 8, (int) round( $oh * $scale ) );
		$small = imagecreatetruecolor( $w, $h );
		imagealphablending( $small, false );
		imagesavealpha( $small, true );
		imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 255, 255, 255, 127 ) );
		imagecopyresampled( $small, $im, 0, 0, 0, 0, $w, $h, $ow, $oh );
		imagedestroy( $im );

		// Solid = actually opaque, or (for a flattened image) not near-white.
		$solid   = array();
		$has_alpha = false;
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$c = imagecolorat( $small, $x, $y );
				$a = ( $c >> 24 ) & 0x7F;
				if ( $a > 8 ) {
					$has_alpha = true;
				}
				$solid[ $y ][ $x ] = ( $a < 40 ) ? 1 : 0;
			}
		}
		if ( ! $has_alpha ) {
			for ( $y = 0; $y < $h; $y++ ) {
				for ( $x = 0; $x < $w; $x++ ) {
					$c = imagecolorat( $small, $x, $y );
					$r = ( $c >> 16 ) & 0xFF;
					$g = ( $c >> 8 ) & 0xFF;
					$b = $c & 0xFF;
					$solid[ $y ][ $x ] = ( $r >= 240 && $g >= 240 && $b >= 240 ) ? 0 : 1;
				}
			}
		}
		imagedestroy( $small );

		// Summed-area table, so any rectangle costs four lookups.
		$sum = array_fill( 0, $h + 1, array_fill( 0, $w + 1, 0 ) );
		for ( $y = 1; $y <= $h; $y++ ) {
			$row = 0;
			for ( $x = 1; $x <= $w; $x++ ) {
				$row              += $solid[ $y - 1 ][ $x - 1 ];
				$sum[ $y ][ $x ]  = $sum[ $y - 1 ][ $x ] + $row;
			}
		}
		$total = $sum[ $h ][ $w ];
		if ( $total < 8 ) {
			return null;
		}

		$best = null;
		// Strictest first, and starting high on purpose — see tools/make-cutouts.py.
		// A loose floor lets the WHOLE image win whenever the straps are thin, and
		// then the "body" includes the strap and the bag is drawn too small.
		foreach ( array( 0.88, 0.80, 0.70, 0.58, 0.45, 0.0 ) as $floor ) {
			for ( $i = 0; $i <= 22; $i++ ) {
				$bh = (int) round( $h * ( 1.0 - $i * 0.03 ) );
				$bw = (int) round( $bh * $ratio );
				if ( $bh < 6 || $bw < 6 || $bw > $w || $bh > $h ) {
					continue;
				}
				$step = max( 1, (int) round( min( $w, $h ) * 0.02 ) );
				for ( $y = 0; $y + $bh <= $h; $y += $step ) {
					for ( $x = 0; $x + $bw <= $w; $x += $step ) {
						$in = $sum[ $y + $bh ][ $x + $bw ] - $sum[ $y ][ $x + $bw ]
							- $sum[ $y + $bh ][ $x ] + $sum[ $y ][ $x ];
						if ( $in / ( $bw * $bh ) < $floor ) {
							continue;
						}
						if ( null === $best || $in > $best[0] ) {
							$best = array( $in, $x, $y, $bw, $bh );
						}
					}
				}
			}
			if ( null !== $best ) {
				break;
			}
		}
		if ( null === $best ) {
			return null;
		}
		list( , $x, $y, $bw, $bh ) = $best;
		return array(
			round( $x / $w, 4 ),
			round( $y / $h, 4 ),
			round( $bw / $w, 4 ),
			round( $bh / $h, 4 ),
		);
	}

	/** Products the module can actually draw, with their resolved sizes. */
	private static function admin_rows() {
		$rows = array();
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $rows;
		}
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$man = self::manifest();
		foreach ( $ids as $id ) {
			$dims = self::resolve_dimensions( array(), $id );
			$post = get_post( $id );
			$att  = (int) get_post_meta( $id, '_kt_size_cutout', true );
			$rows[] = array(
				'id'       => $id,
				'name'     => get_the_title( $id ),
				'dims'     => $dims,
				'att'      => $att,
				'body'     => (string) get_post_meta( $id, '_kt_size_cutout_body', true ),
				'shipped'  => ( $post && isset( $man[ $post->post_name ] ) ) ? $man[ $post->post_name ] : null,
			);
		}
		return $rows;
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this.', 'kohthai-size-view' ) );
		}
		wp_enqueue_media();
		$rows = self::admin_rows();
		$done = isset( $_GET['saved'] ) ? absint( $_GET['saved'] ) : -1;
		?>
		<div class="wrap kt-svadmin">
			<h1><?php esc_html_e( 'Size View — bag photographs', 'kohthai-size-view' ); ?>
				<span style="font-size:.6em;font-weight:400;color:#646970;vertical-align:middle">v<?php echo esc_html( self::VERSION ); ?></span>
			</h1>
			<p class="description" style="max-width:46em">
				<?php esc_html_e( 'Upload a cut-out PNG of each bag — transparent background, handle and strap included. You do not need to crop or resize it: the plugin measures where the bag body sits inside the picture and scales that to the real centimetres, so the bag is drawn at true size and the handle falls outside it.', 'kohthai-size-view' ); ?>
			</p>
			<?php if ( $done >= 0 ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php printf( esc_html__( 'Saved. %d bag photograph(s) measured.', 'kohthai-size-view' ), (int) $done ); ?>
				</p></div>
			<?php endif; ?>
			<?php if ( ! function_exists( 'imagecreatefromstring' ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'PHP\'s GD image library is not available, so the bag body cannot be measured automatically. You can still upload photographs and type the body box by hand.', 'kohthai-size-view' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kt_size_view_save">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="widefat striped" style="margin-top:1em">
					<thead><tr>
						<th style="width:26%"><?php esc_html_e( 'Product', 'kohthai-size-view' ); ?></th>
						<th style="width:14%"><?php esc_html_e( 'Size used', 'kohthai-size-view' ); ?></th>
						<th style="width:20%"><?php esc_html_e( 'Photograph', 'kohthai-size-view' ); ?></th>
						<th><?php esc_html_e( 'Bag body inside the picture', 'kohthai-size-view' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) :
						$id   = $r['id'];
						$url  = $r['att'] ? wp_get_attachment_image_url( $r['att'], 'medium' ) : '';
						$body = $r['body'];
						if ( '' === $body && $r['shipped'] ) {
							$body = implode( ',', $r['shipped']['body'] );
						}
						$parts = array_map( 'floatval', explode( ',', $body ) );
						if ( 4 !== count( $parts ) ) {
							$parts = array( 0, 0, 1, 1 );
						}
						$shown = $url ? $url : ( $r['shipped'] ? plugins_url( 'assets/bags/' . $r['shipped']['file'], __FILE__ ) : '' );
						?>
						<tr data-kt-row
							data-across="<?php echo esc_attr( $r['dims'] ? $r['dims']['across'] : '' ); ?>"
							data-tall="<?php echo esc_attr( $r['dims'] ? $r['dims']['tall'] : '' ); ?>">
							<td>
								<strong><?php echo esc_html( $r['name'] ); ?></strong><br>
								<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>" class="description"><?php esc_html_e( 'edit product', 'kohthai-size-view' ); ?></a>
							</td>
							<td>
								<?php if ( $r['dims'] ) : ?>
									<?php echo esc_html( sprintf( '%s × %s × %s cm', $r['dims']['across'], $r['dims']['tall'], $r['dims']['deep'] ) ); ?>
									<br><span class="description"><?php echo esc_html( $r['dims']['source'] ); ?></span>
								<?php else : ?>
									<span style="color:#b32d2e"><?php esc_html_e( 'no size — nothing will render', 'kohthai-size-view' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<div class="kt-svadmin__thumb" data-kt-thumb>
									<?php if ( $shown ) : ?>
										<img src="<?php echo esc_url( $shown ); ?>" alt="">
										<span class="kt-svadmin__box" style="left:<?php echo esc_attr( $parts[0] * 100 ); ?>%;top:<?php echo esc_attr( $parts[1] * 100 ); ?>%;width:<?php echo esc_attr( $parts[2] * 100 ); ?>%;height:<?php echo esc_attr( $parts[3] * 100 ); ?>%"></span>
									<?php endif; ?>
								</div>
								<p class="description" style="margin:.3em 0 .5em">
									<?php echo $r['att'] ? esc_html__( 'your upload', 'kohthai-size-view' ) : ( $r['shipped'] ? esc_html__( 'shipped with the plugin', 'kohthai-size-view' ) : esc_html__( 'drawing (no photo)', 'kohthai-size-view' ) ); ?>
								</p>
								<input type="hidden" name="cutout[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $r['att'] ); ?>" data-kt-att>
								<button type="button" class="button" data-kt-pick><?php esc_html_e( 'Choose image', 'kohthai-size-view' ); ?></button>
								<?php if ( $r['att'] ) : ?>
									<button type="button" class="button-link" data-kt-clear style="color:#b32d2e"><?php esc_html_e( 'remove', 'kohthai-size-view' ); ?></button>
								<?php endif; ?>
							</td>
							<td>
								<input type="text" class="regular-text code" name="body[<?php echo esc_attr( $id ); ?>]"
									value="<?php echo esc_attr( $body ); ?>" placeholder="0,0,1,1" data-kt-body>
								<p class="description">
									<?php esc_html_e( 'Drag a box around the bag body on the photograph — corners and edges to resize, the middle to move. Or drag on the picture to start a fresh box. These four numbers are left, top, width and height as fractions, and they follow whatever you draw.', 'kohthai-size-view' ); ?>
								</p>
								<p style="margin:.4em 0 0">
									<button type="button" class="button-link" data-kt-remeasure><?php esc_html_e( 'measure it for me again', 'kohthai-size-view' ); ?></button>
								</p>
								<p class="description" data-kt-hint style="margin:.4em 0 0"></p>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h2 style="margin-top:1.6em"><?php esc_html_e( 'Where the button appears', 'kohthai-size-view' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Placement', 'kohthai-size-view' ); ?></th>
						<td>
							<?php $pl = get_option( 'kt_size_view_placement', 'gallery' ); ?>
							<select name="placement">
								<option value="gallery" <?php selected( $pl, 'gallery' ); ?>><?php esc_html_e( 'On the product photo (recommended)', 'kohthai-size-view' ); ?></option>
								<option value="under" <?php selected( $pl, 'under' ); ?>><?php esc_html_e( 'Under the product photo', 'kohthai-size-view' ); ?></option>
								<option value="off" <?php selected( $pl, 'off' ); ?>><?php esc_html_e( 'Nowhere — I will place [kt_size_view] myself', 'kohthai-size-view' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Adds a "See bag size" button to every product page. It opens the size view in a window over the page.', 'kohthai-size-view' ); ?></p>
						</td>
					</tr>

						<td>
							<?php $co = get_option( 'kt_size_view_corner', 'bottom-right' ); ?>
							<select name="corner">
								<option value="bottom-right" <?php selected( $co, 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right (recommended — your other corners are taken)', 'kohthai-size-view' ); ?></option>
								<option value="top-right" <?php selected( $co, 'top-right' ); ?>><?php esc_html_e( 'Top right', 'kohthai-size-view' ); ?></option>
								<option value="top-left" <?php selected( $co, 'top-left' ); ?>><?php esc_html_e( 'Top left', 'kohthai-size-view' ); ?></option>
								<option value="bottom-left" <?php selected( $co, 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'kohthai-size-view' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Your sale badge sits top left and the zoom button bottom left, so bottom right is the free corner.', 'kohthai-size-view' ); ?></p>
						</td>
					</tr>
				</table>

				<p style="margin-top:1em">
					<label><input type="checkbox" name="redetect" value="1">
						<?php esc_html_e( 'Measure every bag body again, even the ones already filled in', 'kohthai-size-view' ); ?></label>
				</p>
				<?php submit_button( __( 'Save photographs', 'kohthai-size-view' ) ); ?>
			</form>
		</div>

		<style>
		/* Wide enough to aim at. At 120px a two-pixel slip was a centimetre of bag. */
		.kt-svadmin__thumb{position:relative;display:inline-block;width:240px;max-width:100%;min-height:40px;background:#fff;
		border:1px solid #dcdcde;cursor:crosshair;touch-action:none;-webkit-user-select:none;user-select:none}
		.kt-svadmin__thumb img{display:block;width:100%;height:auto;pointer-events:none}
		.kt-svadmin__box{position:absolute;border:2px solid #d63638;background:rgba(214,54,56,.10);cursor:move}
		.kt-svadmin__thumb.is-busy{cursor:grabbing}
		/* Handles sit ON the corners and edges, half outside, so the whole grab
		   area is usable even when the box is pushed against the picture edge. */
		.kt-svadmin__h{position:absolute;width:12px;height:12px;margin:-7px 0 0 -7px;
		background:#fff;border:2px solid #d63638;border-radius:2px}
		.kt-svadmin__h[data-h="nw"]{left:0;top:0;cursor:nwse-resize}
		.kt-svadmin__h[data-h="n"] {left:50%;top:0;cursor:ns-resize}
		.kt-svadmin__h[data-h="ne"]{left:100%;top:0;cursor:nesw-resize}
		.kt-svadmin__h[data-h="e"] {left:100%;top:50%;cursor:ew-resize}
		.kt-svadmin__h[data-h="se"]{left:100%;top:100%;cursor:nwse-resize}
		.kt-svadmin__h[data-h="s"] {left:50%;top:100%;cursor:ns-resize}
		.kt-svadmin__h[data-h="sw"]{left:0;top:100%;cursor:nesw-resize}
		.kt-svadmin__h[data-h="w"] {left:0;top:50%;cursor:ew-resize}
		.kt-svadmin__warn{color:#b32d2e}
		</style>
		<script>
		jQuery(function($){

			/* ------------------------------------------------------------------
			 * Drawing the bag body by hand.
			 *
			 * The box is found automatically, and mostly it is right — but the
			 * detector is a heuristic and it has been wrong before (a chain handle
			 * is exactly as wide as its bag, so the whole picture came back as the
			 * body). Until now the only way to correct it was to type four decimal
			 * fractions, which is not a thing anyone should be asked to do.
			 *
			 * A rectangle is all that is needed, even for a curved bag: the box
			 * only ever says WHERE the body sits in the photograph, so the picture
			 * can be scaled to the real measurements and — since 1.15.1 — clipped
			 * to it. Nothing traces the outline, so a polygon would buy nothing and
			 * cost the person drawing it a great deal.
			 * ---------------------------------------------------------------- */
			var HANDLES = ["nw","n","ne","e","se","s","sw","w"];
			var MIN = 0.02;

			function clamp01(v){ return Math.max(0, Math.min(1, v)); }
			function round4(v){ return Math.round(v * 10000) / 10000; }

			function readBox($row){
				var raw = String($row.find("[data-kt-body]").val() || "").split(",");
				var n = [], i;
				for (i = 0; i < raw.length; i++) n.push(parseFloat(raw[i]));
				if (n.length !== 4) return null;
				for (i = 0; i < 4; i++) if (isNaN(n[i])) return null;
				return { l:n[0], t:n[1], w:n[2], h:n[3] };
			}

			/** The one place that writes: the field, the rectangle and the hint. */
			function writeBox($row, b, silent){
				b.w = Math.max(MIN, b.w); b.h = Math.max(MIN, b.h);
				b.l = clamp01(Math.min(b.l, 1 - MIN)); b.t = clamp01(Math.min(b.t, 1 - MIN));
				if (b.l + b.w > 1) b.w = 1 - b.l;
				if (b.t + b.h > 1) b.h = 1 - b.t;

				if (!silent){
					$row.find("[data-kt-body]").val(
						[round4(b.l), round4(b.t), round4(b.w), round4(b.h)].join(","));
				}
				var $box = ensureBox($row);
				if ($box) $box.css({ left:(b.l*100)+"%", top:(b.t*100)+"%",
				                     width:(b.w*100)+"%", height:(b.h*100)+"%" });
				hint($row, b);
			}

			/** The rectangle only exists in the markup when there is a photograph. */
			function ensureBox($row){
				var $thumb = $row.find("[data-kt-thumb]");
				if (!$thumb.find("img").length) return null;
				var $box = $thumb.find(".kt-svadmin__box");
				if (!$box.length) $box = $("<span class=\"kt-svadmin__box\"></span>").appendTo($thumb);
				if (!$box.find(".kt-svadmin__h").length){
					for (var i = 0; i < HANDLES.length; i++){
						$box.append("<span class=\"kt-svadmin__h\" data-h=\"" + HANDLES[i] + "\"></span>");
					}
				}
				return $box;
			}

			/**
			 * A quiet sanity check, not a rule.
			 *
			 * If the box really is the bag body then its shape should resemble the
			 * bag's own across-by-tall. Photographs are taken at an angle and bags
			 * are not rectangles, so some difference is normal and only a large one
			 * means anything — usually that a handle has been swept in with the
			 * body, which is exactly the mistake the detector makes.
			 */
			function hint($row, b){
				var $h = $row.find("[data-kt-hint]").removeClass("kt-svadmin__warn").text("");
				var across = parseFloat($row.attr("data-across"));
				var tall   = parseFloat($row.attr("data-tall"));
				var img    = $row.find("[data-kt-thumb] img")[0];
				if (!across || !tall || !img || !img.naturalWidth) return;
				var drawn = (b.w * img.naturalWidth) / (b.h * img.naturalHeight);
				var real  = across / tall;
				var off   = Math.abs(drawn - real) / real;
				if (off > 0.2){
					$h.addClass("kt-svadmin__warn").text(
						"Box shape " + drawn.toFixed(2) + " wide-to-tall, but the bag is "
						+ real.toFixed(2) + ". Worth a look — a handle swept in with the body does this.");
				} else {
					$h.text("Box shape " + drawn.toFixed(2) + " wide-to-tall, bag is " + real.toFixed(2) + ".");
				}
			}

			$("[data-kt-row]").each(function(){
				var $row = $(this), b = readBox($row);
				if (b) writeBox($row, b, true);
				var img = $row.find("[data-kt-thumb] img")[0];
				// naturalWidth is 0 until the picture has loaded, and the hint needs it.
				if (img && !img.complete){
					$(img).on("load", function(){ var c = readBox($row); if (c) hint($row, c); });
				}
			});

			// Typing in the field still works, and the rectangle follows along.
			$(".kt-svadmin").on("input", "[data-kt-body]", function(){
				var $row = $(this).closest("[data-kt-row]"), b = readBox($row);
				if (b) writeBox($row, b, true);
			});

			$(".kt-svadmin").on("click", "[data-kt-remeasure]", function(e){
				e.preventDefault();
				var $row = $(this).closest("[data-kt-row]");
				$row.find("[data-kt-body]").val("");
				$row.find(".kt-svadmin__box").remove();
				$row.find("[data-kt-hint]").removeClass("kt-svadmin__warn")
					.text("Cleared. It will be measured again when you save.");
			});

			/* --- the drag itself ------------------------------------------- */
			var drag = null;

			$(".kt-svadmin").on("pointerdown", "[data-kt-thumb]", function(e){
				var $thumb = $(this);
				if (!$thumb.find("img").length) return;
				var $row  = $thumb.closest("[data-kt-row]");
				var r     = $thumb[0].getBoundingClientRect();
				var ev    = e.originalEvent || e;
				var x     = clamp01((ev.clientX - r.left) / r.width);
				var y     = clamp01((ev.clientY - r.top) / r.height);
				var $t    = $(e.target);
				var mode  = $t.is(".kt-svadmin__h") ? $t.attr("data-h")
				          : ($t.is(".kt-svadmin__box") ? "move" : "new");
				var start = readBox($row) || { l:x, t:y, w:MIN, h:MIN };
				if (mode === "new") start = { l:x, t:y, w:MIN, h:MIN };

				drag = { $row:$row, $thumb:$thumb, mode:mode, r:r, x:x, y:y, start:start };
				$thumb.addClass("is-busy");

				// On the window, not the element: the pointer leaves a 240 px box
				// almost at once, and capture on it is not worth relying on.
				$(window).on("pointermove.ktbox", onMove).on("pointerup.ktbox pointercancel.ktbox", onUp);
				e.preventDefault();
			});

			function onMove(e){
				if (!drag) return;
				var ev = e.originalEvent || e;
				var x  = clamp01((ev.clientX - drag.r.left) / drag.r.width);
				var y  = clamp01((ev.clientY - drag.r.top) / drag.r.height);
				var s  = drag.start, m = drag.mode, b;

				if (m === "new"){
					b = { l:Math.min(drag.x, x), t:Math.min(drag.y, y),
					      w:Math.abs(x - drag.x), h:Math.abs(y - drag.y) };
				} else if (m === "move"){
					b = { l:clamp01(s.l + (x - drag.x)), t:clamp01(s.t + (y - drag.y)), w:s.w, h:s.h };
					if (b.l + b.w > 1) b.l = 1 - b.w;
					if (b.t + b.h > 1) b.t = 1 - b.h;
				} else {
					// Each letter of the handle moves the edge it names; the
					// opposite edge stays where it is.
					var l = s.l, t = s.t, rgt = s.l + s.w, bot = s.t + s.h;
					if (m.indexOf("n") > -1) t   = Math.min(y, bot - MIN);
					if (m.indexOf("s") > -1) bot = Math.max(y, t + MIN);
					if (m.indexOf("w") > -1) l   = Math.min(x, rgt - MIN);
					if (m.indexOf("e") > -1) rgt = Math.max(x, l + MIN);
					b = { l:l, t:t, w:rgt - l, h:bot - t };
				}
				writeBox(drag.$row, b);
				e.preventDefault();
			}

			function onUp(){
				if (!drag) return;
				drag.$thumb.removeClass("is-busy");
				drag = null;
				$(window).off(".ktbox");
			}

			$('.kt-svadmin').on('click','[data-kt-pick]',function(e){
				e.preventDefault();
				var cell = $(this).closest('td');
				var frame = wp.media({ title:'Choose a cut-out photograph', library:{ type:'image' },
					button:{ text:'Use this image' }, multiple:false });
				frame.on('select', function(){
					var a = frame.state().get('selection').first().toJSON();
					cell.find('[data-kt-att]').val(a.id);
					// A new picture means the old body box is meaningless — clearing
					// it is what asks the server to measure this one on save.
					cell.closest('tr').find('[data-kt-body]').val('');
					var t = cell.find('[data-kt-thumb]');
					t.html('<img src="'+(a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url)+'" alt="">');
				});
				frame.open();
			});
			$('.kt-svadmin').on('click','[data-kt-clear]',function(e){
				e.preventDefault();
				var cell = $(this).closest('td');
				cell.find('[data-kt-att]').val('');
				cell.find('[data-kt-thumb]').empty();
				cell.closest('tr').find('[data-kt-body]').val('');
			});
		});
		</script>
		<?php
	}

	public static function admin_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to change this.', 'kohthai-size-view' ) );
		}
		check_admin_referer( self::NONCE );

		$cutouts  = isset( $_POST['cutout'] ) && is_array( $_POST['cutout'] ) ? wp_unslash( $_POST['cutout'] ) : array();
		$bodies   = isset( $_POST['body'] ) && is_array( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : array();
		$redetect = ! empty( $_POST['redetect'] );

		$placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : 'gallery';
		if ( ! in_array( $placement, array( 'gallery', 'under', 'off' ), true ) ) {
			$placement = 'gallery';
		}
		update_option( 'kt_size_view_placement', $placement );

		$corner = isset( $_POST['corner'] ) ? sanitize_key( wp_unslash( $_POST['corner'] ) ) : 'bottom-right';
		if ( ! in_array( $corner, array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' ), true ) ) {
			$corner = 'bottom-right';
		}
		update_option( 'kt_size_view_corner', $corner );


		$measured = 0;

		foreach ( $cutouts as $post_id => $att ) {
			$post_id = absint( $post_id );
			$att     = absint( $att );
			if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
				continue;
			}

			$body = isset( $bodies[ $post_id ] ) ? trim( (string) $bodies[ $post_id ] ) : '';
			$plan = self::plan_body_box( $att, $body, $redetect );

			// ⚠️ A product with no UPLOAD is not a product with no photograph —
			// most of them use the cut-out that ships with the plugin. This used
			// to delete the body box and skip to the next product, so every box
			// drawn by hand on a shipped photograph was thrown away on save and
			// the screen came back showing the old one.
			if ( ! $att ) {
				delete_post_meta( $post_id, '_kt_size_cutout' );
			} else {
				update_post_meta( $post_id, '_kt_size_cutout', $att );
			}

			if ( 'save' === $plan['action'] ) {
				update_post_meta( $post_id, '_kt_size_cutout_body', implode( ',', $plan['box'] ) );
				continue;
			}

			if ( 'forget' === $plan['action'] ) {
				// Back to the box that ships with the plugin.
				delete_post_meta( $post_id, '_kt_size_cutout_body' );
				continue;
			}

			$dims = self::resolve_dimensions( array(), $post_id );
			$file = get_attached_file( $att );
			$box  = ( $dims && $dims['tall'] > 0 && $file )
				? self::detect_body_box( $file, $dims['across'] / $dims['tall'] )
				: null;
			if ( $box ) {
				update_post_meta( $post_id, '_kt_size_cutout_body', implode( ',', $box ) );
				$measured++;
			} else {
				// Better a whole-image box than a wrong one; the admin screen
				// shows what was stored so it can be corrected by hand.
				update_post_meta( $post_id, '_kt_size_cutout_body', '0,0,1,1' );
			}
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => self::MENU, 'saved' => $measured ),
			admin_url( class_exists( 'WooCommerce' ) ? 'admin.php' : 'options-general.php' )
		) );
		exit;
	}

	/** Whether the stylesheet has gone out already. */
	private static $css_done = false;

	/**
	 * The stylesheet, in the head.
	 *
	 * On a product page we know the module is coming before it has rendered, so
	 * the CSS can go out early. That matters: printed in the footer it landed
	 * after the browser had already painted the button, which flashed up
	 * full-width and unstyled and then jumped into the gallery.
	 */
	/**
	 * Keep WP Rocket from delaying this module's script.
	 *
	 * ⚠️ The guards already on the tag — data-no-optimize, data-no-minify,
	 * data-no-defer, data-cfasync — cover minification, combination and
	 * deferral. They do NOT cover "Delay JavaScript Execution", which is a
	 * separate feature: it rewrites the tag to type="text/rocketlazyloadscript"
	 * and runs it only after the visitor's first interaction. On this site that
	 * meant the "See bag size" button did not appear until the page was
	 * scrolled or touched — confirmed in the live HTML, where the module's
	 * script really was carrying that type.
	 *
	 * This is the plugin-side fix, so nothing has to be remembered in the
	 * WP Rocket dashboard. The pattern is matched against the script tag and
	 * its contents, which is why the marker is printed inside the JS.
	 */
	public static function exclude_from_rocket_delay( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$excluded[] = self::DELAY_MARK;
		return $excluded;
	}

	public static function print_css() {
		if ( self::$css_done ) {
			return;
		}
		$on_product = function_exists( 'is_product' ) && is_product()
			&& 'off' !== get_option( 'kt_size_view_placement', 'gallery' );
		if ( ! $on_product ) {
			return;   // a shortcode elsewhere still gets its CSS from the footer
		}
		self::$css_done = true;
		echo '<style data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
			. '/* Kohthai Size View ' . self::VERSION . ' */' . self::css() . '</style>';
	}

	public static function print_assets() {
		if ( ! self::$used ) {
			return;
		}
		if ( ! self::$css_done ) {
			self::$css_done = true;
			echo '<style data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
				. '/* Kohthai Size View ' . self::VERSION . ' */' . self::css() . '</style>';
		}
		echo '<script data-no-optimize="1" data-no-minify="1" data-no-defer="1" data-cfasync="false">' . self::js() . '</script>';
	}

	private static function css() {
		return <<<'CSS'
.kt-sv{--kt-ink:#3d2a18;--kt-brand:#654321;--kt-tan:#a08565;--kt-gold:#FFBC00;--kt-paper:#faf7f2;--kt-line:#e7ded1;
box-sizing:border-box;margin:2.5rem 0;padding:0;color:var(--kt-ink);font-size:15px;line-height:1.6}
.kt-sv *,.kt-sv *::before,.kt-sv *::after{box-sizing:border-box}
/* Any rule that sets `display` beats the hidden attribute's own display:none,
   and this module toggles `hidden` on half a dozen elements — including the
   modal, which is display:flex and so opened itself on page load. */
.kt-sv [hidden],.kt-sv-launch [hidden]{display:none!important}
/* ⚠️ Flatsome sets `button{margin-bottom:1em}` globally. That 14px sat INSIDE
   the tab pill, making it taller than its buttons so they looked stuck to the
   top of it. Every button this plugin renders must clear the theme's margins. */
.kt-sv button,.kt-sv-launch button,.kt-sv-modal button{margin:0;line-height:1.2}
.kt-sv__head{max-width:44rem}
.kt-sv__eyebrow{margin:0 0 .5rem;font-size:.6875rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--kt-tan)}
.kt-sv__eyebrow::after{content:"";display:inline-block;width:1.75rem;height:2px;margin-left:.7rem;vertical-align:.28em;background:var(--kt-gold)}
.kt-sv__title{margin:0 0 .5rem;font-size:clamp(1.4rem,1.1rem + 1.1vw,1.9rem);font-weight:600;line-height:1.2;color:var(--kt-brand);letter-spacing:-.01em}
.kt-sv__intro{margin:0;color:#6f6155;max-width:38rem}

.kt-sv__tabs{display:inline-flex;gap:.25rem;margin:1.5rem 0 0;padding:.25rem;background:#f1ece4;border-radius:999px}
.kt-sv__tab{appearance:none;border:0;cursor:pointer;padding:.55rem 1.05rem;border-radius:999px;background:transparent;
font:inherit;font-size:.875rem;font-weight:600;color:#7c6c5c;white-space:nowrap;
transition:background .22s ease,color .22s ease,box-shadow .22s ease}
.kt-sv__tab:hover{color:var(--kt-brand)}
.kt-sv__tab.is-on{background:#fff;color:var(--kt-brand);box-shadow:0 1px 3px rgba(61,42,24,.13)}
.kt-sv__tab:focus-visible{outline:2px solid var(--kt-brand);outline-offset:2px}

.kt-sv__stage{margin-top:1rem;border:1px solid var(--kt-line);border-radius:14px;background:
linear-gradient(0deg,rgba(160,133,101,.055) 1px,transparent 1px) 0 0/100% 12px,
linear-gradient(90deg,rgba(160,133,101,.055) 1px,transparent 1px) 0 0/12px 100%,var(--kt-paper);
overflow:hidden}
.kt-sv__scroll{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;padding:1.25rem 1rem}
/* A 155 cm figure is a very tall drawing. The panel caps its height and the
   SVG scales to fit — except the Actual size view, which must keep the exact
   pixel size it was given or it stops being actual size. */
.kt-sv__canvas{display:flex;justify-content:center;align-items:flex-end;min-height:150px;--kt-max:520px}
/* The figure stands on the floor, so it is bottom aligned. The fitting view has
   no floor, and bottom aligning it left a tall band of empty panel above the
   drawing on a phone. */
.kt-sv__canvas.is-fits{align-items:center}
.kt-sv__canvas svg{display:block;width:auto;height:auto;max-width:100%;max-height:var(--kt-max);overflow:visible;
/* ⭐⭐ touch-action MUST be here, on the <svg> root.
   It was set on the dragged groups inside the SVG instead, and browsers do not
   honour touch-action on SVG child elements — only on the root or on HTML
   elements. So on a phone the browser claimed every drag as a pan (the scroll
   container around this one being an eager taker), fired pointercancel, and the
   drag died before it began, while a mouse worked perfectly. Nothing else about
   the drag was ever wrong. */
touch-action:none}
.kt-sv__canvas svg.is-fixed{max-width:none;max-height:none;flex:0 0 auto}
.kt-sv__hint{margin:0;padding:.6rem 1rem .8rem;font-size:.8125rem;color:#8a7a6a;text-align:center;border-top:1px dashed var(--kt-line)}
.kt-sv__hint.is-no{color:#a8392f;font-weight:600}

/* Text size is set per-scene in JS, in scene units, so a clutch and a tote come
   out looking the same weight. Do not add font-size here — a CSS rule would beat
   the presentation attribute and break that. */
/* Opaque on purpose: over the figure's legs a translucent bag looks like a
   ghost rather than something she is carrying. */
.kt-sv__body{fill:#f1e9dd;stroke:var(--kt-brand);stroke-width:1.6;stroke-linejoin:round}
.kt-sv__strap{fill:none;stroke:var(--kt-tan);stroke-width:1.2;stroke-dasharray:4 4;opacity:.65}
.kt-sv__detail{fill:none;stroke:var(--kt-brand);stroke-width:1.1;opacity:.45}
.kt-sv__ref{fill:#fff;stroke:var(--kt-tan);stroke-width:1.3}
.kt-sv__refline{fill:none;stroke:var(--kt-tan);stroke-width:1.1;opacity:.75}
.kt-sv__guide{fill:none;stroke:var(--kt-tan);stroke-width:1;opacity:.85}
.kt-sv__floor{stroke:var(--kt-line);stroke-width:1;fill:none}
.kt-sv__caption{fill:#7c6c5c;font-weight:600;text-anchor:middle;letter-spacing:.02em}
.kt-sv__caption--sub{fill:#a08565;font-weight:500}
.kt-sv__caption--out{fill:#bcae9d;font-weight:500}
.kt-sv__dim{fill:var(--kt-brand);font-weight:700;text-anchor:middle}
.kt-sv__bagname{fill:var(--kt-brand);font-weight:700;text-anchor:middle;letter-spacing:.02em}
/* Three tones, so the figure reads as a person in a top and trousers rather
   than one flat blob. Same trick the big brands use. */
/* One hairline through the whole figure, so it reads as a single fashion
   illustration rather than flat shapes stacked on each other. Stroke widths are
   in figure units and the figure is scaled, so they stay in proportion. */
/* Matched to the reference the owner supplied: high-contrast greys, near-black
   hair, dark high-waisted trousers, light skin, and bare ankles above black
   heels. The warm low-contrast palette this replaced looked washed out beside
   it — the contrast is most of what makes the reference read as expensive. */
.kt-sv__skin{fill:#ece7e1}
.kt-sv__cloth{fill:#736e68}
.kt-sv__cloth3{fill:#6a655f}
.kt-sv__cloth2{fill:#33302d}
.kt-sv__hair{fill:#221f1d}
.kt-sv__shoe{fill:#141312}

.kt-sv__tooBig{opacity:.34}

/* ---- "Will it fit": a real fitting test ---------------------------------
   The bag at true scale, and something you can pick up, move and turn. */
.kt-sv__opening{fill:none;stroke:var(--kt-brand);stroke-width:1.3;stroke-dasharray:7 5;opacity:.5}
/* The bag seen edge on, so thickness stops being an invisible rule. */
.kt-sv__side{fill:rgba(101,67,33,.07);stroke:var(--kt-brand);stroke-width:1.3;stroke-dasharray:7 5;opacity:.55}
.kt-sv__sidebox{fill:rgba(47,107,66,.28);stroke:#2f6b42;stroke-width:1.8}
.kt-sv__sidething.is-out .kt-sv__sidebox{fill:rgba(168,57,47,.26);stroke:#a8392f;stroke-dasharray:6 4}
.kt-sv__thing{cursor:grab;touch-action:none}
/* The invisible pad that makes the object grabbable by finger. touch-action
   here too: it is the element the gesture actually starts on. */
.kt-sv__grab{cursor:grab;touch-action:none}
.kt-sv__thing.is-dragging .kt-sv__grab{cursor:grabbing}
.kt-sv__thing.is-dragging{cursor:grabbing}
.kt-sv__thing:focus-visible{outline:2px solid var(--kt-brand);outline-offset:3px}
/* The objects carry their own colours. Only the box around them answers the
   question, so a phone still looks like a phone whether it fits or not. */
.kt-sv__thingbox{fill:none;stroke:#a8392f;stroke-width:2.4;stroke-dasharray:7 5}
.kt-sv__thingbox.is-quiet{stroke:none}
.kt-sv__thing{filter:drop-shadow(0 2px 4px rgba(38,26,14,.28))}

/* The swing itself. transform-box:fill-box pivots on the group's own centre,
   which saves working the centre out in user units and keeps it exact. */
.kt-sv__spin{transform-box:fill-box;transform-origin:50% 50%;
transition:transform .3s cubic-bezier(.34,1.12,.5,1),opacity .14s ease}
@media (prefers-reduced-motion:reduce){ .kt-sv__spin{transition:opacity .14s ease} }

/* Carried, it goes see-through, so the bag underneath stays readable while she
   lines the two up — and on a phone, where her own finger covers the object,
   that is the only way to see what is under it at all. It goes solid again the
   moment she lets go: the verdict is read at rest, and a permanently ghosted
   phone looks like a rendering fault rather than a deliberate state. */
.kt-sv__thing.is-dragging .kt-sv__spin{opacity:.45}
/* And see-through at rest too when it hangs over the bag, because that is the
   case where it would otherwise hide the very thing it is being compared
   against — a MacBook against a clutch covers it completely. Anything that
   sits inside the bag stays solid: the bag shows all round it anyway. */
.kt-sv__thing.is-over .kt-sv__spin{opacity:.62}

/* Labels ride over the object, so they need to survive whatever is under them. */
.kt-sv__halo{paint-order:stroke;stroke:#faf7f2;stroke-width:4;stroke-linejoin:round}

/* The bag's true-scale outline, drawn a SECOND time on top of the object. A
   MacBook or a diary is large enough to rub out the very edge she is lining up
   against, and an edge you cannot see is an edge you cannot judge. Never a drag
   target, or it would swallow the press meant for the object beneath it. */
.kt-sv__opening--over{pointer-events:none;opacity:.34;transition:opacity .14s ease}
svg.is-carrying .kt-sv__opening--over{opacity:.95}

/* The turn handle rides on the object, the way the reference does it. */
.kt-sv__rotate{cursor:pointer;opacity:0;transition:opacity .16s ease}
.kt-sv__thing:hover .kt-sv__rotate,
.kt-sv__thing:focus-within .kt-sv__rotate,
.kt-sv__thing.is-dragging .kt-sv__rotate{opacity:1}
.kt-sv__rotate-disc{fill:#fff;stroke:#d9cfc0;stroke-width:1.2;vector-effect:non-scaling-stroke}
.kt-sv__rotate-icon{fill:none;stroke:#654321;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
.kt-sv__rotate:hover .kt-sv__rotate-disc{fill:#f6f1e8}
.kt-sv__rotate:focus-visible .kt-sv__rotate-disc{stroke:#654321;stroke-width:2.4}
/* No hover on a phone, so it simply stays visible there. */
@media (hover:none){ .kt-sv__rotate{opacity:1} }

.kt-sv__tray{display:flex;gap:.4rem;overflow-x:auto;padding:.55rem .8rem .7rem;
border-top:1px dashed var(--kt-line);-webkit-overflow-scrolling:touch}
.kt-sv__pick{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:.15rem;
padding:.35rem .45rem;cursor:pointer;border:1px solid transparent;border-radius:10px;background:none;
font:inherit;font-size:.62rem;font-weight:600;letter-spacing:.02em;color:#8a7a6a;white-space:nowrap}
.kt-sv__pick:hover{background:#fff;border-color:var(--kt-line)}
.kt-sv__pick.is-on{background:#fff;border-color:var(--kt-brand);color:var(--kt-brand)}
.kt-sv__pick:focus-visible{outline:2px solid var(--kt-brand);outline-offset:1px}
.kt-sv__pick svg{width:30px;height:30px;display:block}
.kt-sv__pick svg{overflow:visible}
.kt-sv__pick.is-too-big{opacity:.4}
.kt-sv__turn{flex:0 0 auto;align-self:center;margin-left:auto;padding:.42rem .8rem;cursor:pointer;
border:1px solid var(--kt-brand);border-radius:999px;background:#fff;
font:inherit;font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--kt-brand)}
.kt-sv__turn:hover{background:var(--kt-brand);color:#fff}
.kt-sv-modal .kt-sv__tray{grid-area:tray;border-top:0;padding:.35rem .6rem .1rem}

/* ---- the launcher and its window ---------------------------------------
   The button is rendered after the gallery and then lifted into it by the
   script, so nothing here depends on the theme's own markup. If the gallery
   cannot be found it simply stays where it was, under the photo. */
.kt-sv-launch{--kt-brand:#654321;--kt-gold:#FFBC00}
/* The launcher sits OUTSIDE .kt-sv, so the module's own border-box reset never
   reached it and the panel's padding was being added to its width. */
.kt-sv-launch,.kt-sv-launch *,.kt-sv-launch *::before,.kt-sv-launch *::after{box-sizing:border-box}
/* Hidden until the script has put it where it belongs, so it is never seen
   full-width under the photo and then jumping into the corner. */
.kt-sv-launch{visibility:hidden}
.kt-sv-launch.is-placed{visibility:visible;animation:none}
/* A safety net. The button is hidden until the script has put it on the photo,
   so it is never seen mid-jump — but that made "the script never ran" and "the
   script ran late" both look like "there is no button". If nothing has claimed
   it within a few seconds it shows itself where it stands. */
.kt-sv-launch{animation:kt-sv-reveal 0s linear 3s forwards}
@keyframes kt-sv-reveal{to{visibility:visible}}
.kt-sv-modal,.kt-sv-modal *,.kt-sv-modal *::before,.kt-sv-modal *::after{box-sizing:border-box}
.kt-sv-modal[hidden]{display:none!important}
.kt-sv-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem .85rem;cursor:pointer;
border:1px solid rgba(101,67,33,.35);border-radius:999px;background:rgba(255,255,255,.94);
color:var(--kt-brand);font:inherit;font-size:.72rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase;
box-shadow:0 1px 6px rgba(61,42,24,.12);transition:background .18s ease,box-shadow .18s ease,transform .18s ease}
.kt-sv-btn:hover{background:#fff;box-shadow:0 3px 12px rgba(61,42,24,.2);transform:translateY(-1px)}
.kt-sv-btn:focus-visible{outline:2px solid var(--kt-brand);outline-offset:2px}
.kt-sv-btn__icon{width:1.05rem;height:1.05rem;flex:0 0 auto}
.kt-sv-launch.is-over-gallery{position:absolute;z-index:4}
.kt-sv-launch.is-over-gallery[data-corner="bottom-right"]{right:14px;bottom:14px}
.kt-sv-launch.is-over-gallery[data-corner="bottom-left"]{left:14px;bottom:14px}
.kt-sv-launch.is-over-gallery[data-corner="top-right"]{right:14px;top:14px}
.kt-sv-launch.is-over-gallery[data-corner="top-left"]{left:14px;top:14px}

/* The window sizes itself to the screen and the DRAWING takes up the slack, so
   there is nothing to scroll inside it. Letting the panel scroll instead meant
   the customer had to hunt for the sliders below the fold. */
.kt-sv-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;
padding:3vh 3vw;overscroll-behavior:contain}
.kt-sv-modal__scrim{position:absolute;inset:0;background:rgba(38,26,14,.55);backdrop-filter:blur(2px)}
.kt-sv-modal__panel{position:relative;display:flex;flex-direction:column;overflow:auto;
width:min(940px,100%);
/* A DEFINITE height, not just a maximum: flex-grow has no free space to hand
   the drawing when the panel is only as tall as its own content, so the figure
   came out small on a big screen. Capped so it does not stretch absurdly. */
height:min(94vh,860px);max-height:94vh;
padding:1.5rem 1.5rem 1rem;border-radius:16px;background:#fff;box-shadow:0 24px 60px rgba(38,26,14,.34)}
.kt-sv-modal__close{position:absolute;top:.55rem;right:.6rem;z-index:3;width:2.3rem;height:2.3rem;
display:flex;align-items:center;justify-content:center;cursor:pointer;
border:0;border-radius:50%;background:#f1ece4;color:#654321;font-size:1.5rem;line-height:1}
.kt-sv-modal__close:hover{background:#e4dbcd}
.kt-sv-modal__close:focus-visible{outline:2px solid #654321;outline-offset:2px}

/* Inside the window the module becomes a column and the stage absorbs whatever
   height is left over. min-height:0 on each link of the chain is what actually
   lets a flex child shrink below its content. */
.kt-sv-modal .kt-sv{margin:0;display:flex;flex-direction:column;flex:1 1 auto;min-height:0}
.kt-sv-modal .kt-sv__intro{display:none}
.kt-sv-modal .kt-sv__head{padding-right:2.4rem}
.kt-sv-modal .kt-sv__head{text-align:center;max-width:none}
.kt-sv-modal .kt-sv__tabs{margin-top:.9rem}
/* The tab pill is inline-flex, so it needs a centred parent rather than
   text-align on itself. */
.kt-sv-modal .kt-sv__head + .kt-sv__tabs{display:inline-flex}
.kt-sv-modal > .kt-sv,.kt-sv-modal__panel > .kt-sv{align-items:center}
.kt-sv-modal .kt-sv__stage,.kt-sv-modal .kt-sv__specs{align-self:stretch}
.kt-sv-modal .kt-sv__stage{display:flex;flex-direction:column;flex:1 1 auto;min-height:0}
.kt-sv-modal .kt-sv__scroll{flex:1 1 auto;min-height:170px;overflow-y:hidden}
.kt-sv-modal .kt-sv__canvas{height:100%;min-height:0}
/* Everything that is not the drawing is put on a diet, because every pixel
   these give up is a pixel the figure gets. The spec cards were the worst
   offender: three of them wrapped onto two rows and cost ~90px. */
.kt-sv-modal .kt-sv__eyebrow{display:none}
.kt-sv-modal .kt-sv__title{font-size:1.15rem}
.kt-sv-modal .kt-sv__specs{gap:.35rem;margin-top:.6rem}
.kt-sv-modal .kt-sv__spec{flex:1 1 0;min-width:0;padding:.4rem .55rem}
.kt-sv-modal .kt-sv__spec dd{font-size:1rem}
.kt-sv-modal .kt-sv__note{font-size:.68rem;margin-top:.4rem;line-height:1.4}
.kt-sv-modal .kt-sv__hint{padding:.45rem .8rem .55rem}

/* ⭐ Inside the window the two sliders stand UP the right-hand side, as in the
   reference. Horizontal they cost ~110px of height that the figure wanted; as a
   column they cost width, which there is plenty of. */
.kt-sv-modal .kt-sv__stage{display:grid;grid-template-columns:minmax(0,1fr) auto;
grid-template-rows:minmax(0,1fr) auto auto;
grid-template-areas:"canvas controls" "tray tray" "hint hint";align-items:stretch}
.kt-sv-modal .kt-sv__scroll{grid-area:canvas}
.kt-sv-modal .kt-sv__hint{grid-area:hint}
.kt-sv-modal .kt-sv__controls{grid-area:controls;display:flex;flex-direction:column;
justify-content:center;gap:1.4rem;padding:.8rem .9rem .8rem .2rem;flex-wrap:nowrap}
.kt-sv-modal .kt-sv__control{flex:0 0 auto;max-width:none;display:flex;flex-direction:column;align-items:center;gap:.4rem}
.kt-sv-modal .kt-sv__control-label{margin:0;font-size:.6rem;letter-spacing:.1em;text-align:center}
.kt-sv-modal .kt-sv__control-out{display:block;margin:.15rem 0 0;font-size:.85rem}
.kt-sv-modal .kt-sv__range{
/* writing-mode is the standard way to stand a range control up; the WebKit
   appearance is the older fallback. A browser that supports neither simply
   keeps a horizontal slider, which still works. */
-webkit-appearance:slider-vertical;appearance:slider-vertical;
writing-mode:vertical-lr;direction:rtl;
width:1.4rem;max-width:none;height:clamp(110px,24vh,210px);margin:0}

@media (max-width:600px){
.kt-sv-modal .kt-sv__controls{gap:.9rem;padding:.5rem .5rem .5rem .1rem}
.kt-sv-modal .kt-sv__range{height:clamp(90px,20vh,160px)}
.kt-sv-modal .kt-sv__control-label{font-size:.55rem}
.kt-sv-modal .kt-sv__control-out{font-size:.78rem}
}

/* Scroll lock. overflow:hidden alone does not hold on a phone — the page keeps
   moving under the window — so the body is pinned and the position restored. */
html.kt-sv-locked{overflow:hidden}
body.kt-sv-locked{position:fixed;width:100%;overflow:hidden}

@media (max-width:600px){
/* Deliberately NOT full-bleed: the reference leaves the page showing above and
   below, which is what tells you the window is a window and can be closed. */
.kt-sv-modal{padding:5vh 3vw}
.kt-sv-modal__panel{width:100%;height:90vh;max-height:90vh;border-radius:14px;padding:1.1rem .8rem .8rem}
.kt-sv-modal__close{width:2.5rem;height:2.5rem;background:#e9e1d5}
.kt-sv-btn{font-size:.66rem;padding:.44rem .7rem}
}
@media (prefers-reduced-motion:reduce){.kt-sv-btn{transition:none}.kt-sv-btn:hover{transform:none}}

/* Pick the bag up and move it between the ways it can be worn.
   touch-action:none is required — without it a drag scrolls the page instead. */
.kt-sv__carried{cursor:grab;touch-action:none}
.kt-sv__carried.is-dragging{cursor:grabbing}
.kt-sv__carried:focus-visible{outline:2px solid var(--kt-brand);outline-offset:4px}
.kt-sv__ghosts{opacity:.4;transition:opacity .2s ease}
.is-picking .kt-sv__ghosts{opacity:1}
.kt-sv__ghostbox{fill:none;stroke:var(--kt-tan);stroke-width:1.4;stroke-dasharray:6 6}
/* Named, because two unlabelled dashed boxes on the same side of her read as a
   glitch rather than as two ways of carrying it. */
.kt-sv__ghostlabel{fill:#a08565;font-weight:600;text-anchor:middle;letter-spacing:.02em;
/* A halo, so the caption stays legible where it crosses her legs. */
paint-order:stroke;stroke:#faf7f2;stroke-width:5;stroke-linejoin:round}
.kt-sv__ghost.is-near .kt-sv__ghostlabel{fill:var(--kt-brand)}
.kt-sv__ghost.is-near .kt-sv__ghostbox{stroke:var(--kt-brand);stroke-width:2;fill:rgba(101,67,33,.07)}

.kt-sv__controls{display:flex;flex-wrap:wrap;gap:.5rem 2rem;justify-content:center;padding:.2rem 1rem 1rem}
.kt-sv__control{flex:1 1 13rem;max-width:22rem;text-align:center}
.kt-sv__control-label{display:block;margin-bottom:.4rem;font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--kt-tan)}
.kt-sv__control-out{margin-left:.5rem;font-size:.95rem;font-weight:700;letter-spacing:0;text-transform:none;color:var(--kt-brand)}
.kt-sv__range{display:block;width:100%;max-width:24rem;margin:0 auto;accent-color:var(--kt-brand)}


.kt-sv__specs{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0 0;padding:0}
.kt-sv__spec{flex:1 1 7rem;margin:0;padding:.7rem .9rem;border:1px solid var(--kt-line);border-radius:11px;background:#fff}
.kt-sv__spec dt{margin:0 0 .12rem;font-size:.6875rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--kt-tan)}
.kt-sv__spec dd{margin:0;font-size:1.3rem;font-weight:600;line-height:1.1;color:var(--kt-brand)}
.kt-sv__spec dd span{font-size:.8rem;font-weight:500;color:#9a8a79}
.kt-sv__note{margin:.75rem 0 0;font-size:.8125rem;color:#8a7a6a}

@media (max-width:600px){
.kt-sv{margin:2rem 0}
.kt-sv__tabs{display:flex;width:100%}
.kt-sv__tab{flex:1 1 auto;text-align:center;padding:.55rem .5rem;font-size:.82rem}
.kt-sv__scroll{padding:1rem .6rem}
.kt-sv__canvas{--kt-max:400px}
.kt-sv__spec dd{font-size:1.15rem}
}
@media (prefers-reduced-motion:reduce){
.kt-sv__tab,.kt-sv__btn{transition:none}
.kt-sv__btn:hover{transform:none}
}
CSS;
	}

	private static function js() {
		return <<<JS
(function(){
"use strict";
/* ktSizeViewModule — the marker exclude_from_rocket_delay() matches on. Do not
   rename it in one place without the other, or WP Rocket goes back to holding
   this script until the visitor's first interaction. */
window.ktSizeViewModule = true;
var K_HT = "ktsv.heightCm", K_BUILD = "ktsv.build";

/* The panel is sized for the TALLEST woman the slider offers, plus the drawing's
   own margins, and every height is then drawn against that same ruler.
   Fitting each drawing to the panel instead — which is what it used to do —
   meant a 135 cm and a 185 cm figure came out exactly the same size on screen
   and only the surroundings appeared to move. The slider has to change HER. */
var REF_H_MM = 2090;

/* How much wider the figure gets, and what to call each step. The wording is
   deliberately plain — this is a shopper looking at her own body, not a size
   chart to argue with. */
var BUILDS = [
  { k:0.88, label:"Slim" },
  { k:1.00, label:"Medium" },
  { k:1.12, label:"Curvy" },
  { k:1.20, label:"Fuller" },
  { k:1.30, label:"Plus" }
];

function ns(t){ return document.createElementNS("http://www.w3.org/2000/svg", t); }
function attr(el, o){ for (var k in o){ if(Object.prototype.hasOwnProperty.call(o,k)) el.setAttribute(k, o[k]); } return el; }
function num(v){ v = parseFloat(v); return isFinite(v) ? v : 0; }
function fmt(v){ return (Math.round(v*10)/10).toString().replace(/\\.0$/,""); }
function store(k, v){
  try { if (v === undefined) { var r = parseFloat(window.localStorage.getItem(k)); return isFinite(r) ? r : 0; }
        window.localStorage.setItem(k, String(v)); } catch(e){ return 0; }
  return 0;
}

var uid = 0;

function text(g, x, y, str, cls, size){
  var t = attr(ns("text"), { x:x, y:y, "class":cls, "font-size":size });
  t.appendChild(document.createTextNode(str));
  g.appendChild(t);
  return t;
}
function shadowDefs(svg, id){
  var defs = ns("defs"), rg = attr(ns("radialGradient"), { id:id });
  rg.appendChild( attr(ns("stop"), { offset:"0%",   "stop-color":"#3d2a18", "stop-opacity":"0.20" }) );
  rg.appendChild( attr(ns("stop"), { offset:"100%", "stop-color":"#3d2a18", "stop-opacity":"0" }) );
  defs.appendChild(rg); svg.appendChild(defs); return id;
}
function shadow(g, id, cx, floorY, w, r){
  g.appendChild(attr(ns("ellipse"), { cx:cx, cy:floorY, rx:w*0.52, ry:r, fill:"url(#"+id+")" }));
}

/* ---------------------------------------------------------------------------
   The bag. A stylised outline whose PROPORTIONS are the real measurements —
   or, when a cut-out photograph is supplied, that photograph at the same
   geometry. Everything downstream only calls drawBag().
--------------------------------------------------------------------------- */
function strapRoom(shape, h){
  if (shape === "clutch")    return 0;
  if (shape === "crossbody") return h * 0.68;
  if (shape === "handbag")   return h * 0.34;
  if (shape === "tote")      return h * 0.46;
  return h * 0.48;
}

function shapePaths(shape, x, y, w, h){
  var X = function(f){ return x + f*w; }, Y = function(f){ return y + f*h; };
  var r = Math.min(w, h) * 0.12;
  function rounded(x0,y0,x1,y1,rad){
    rad = Math.min(rad, (x1-x0)/2, (y1-y0)/2);
    return "M"+(x0+rad)+","+y0+"H"+(x1-rad)+"A"+rad+","+rad+" 0 0 1 "+x1+","+(y0+rad)+
           "V"+(y1-rad)+"A"+rad+","+rad+" 0 0 1 "+(x1-rad)+","+y1+
           "H"+(x0+rad)+"A"+rad+","+rad+" 0 0 1 "+x0+","+(y1-rad)+
           "V"+(y0+rad)+"A"+rad+","+rad+" 0 0 1 "+(x0+rad)+","+y0+"Z";
  }
  switch(shape){
    case "tote":
      return { body:"M"+X(0)+","+Y(0)+" L"+X(1)+","+Y(0)+" L"+X(0.93)+","+Y(0.94)+
                    " Q"+X(0.5)+","+Y(1.02)+" "+X(0.07)+","+Y(0.94)+" Z",
               detail:null,
               strap:"M"+X(0.2)+","+Y(0)+" C"+X(0.24)+","+Y(-0.42)+" "+X(0.44)+","+Y(-0.42)+" "+X(0.46)+","+Y(0)+
                     "M"+X(0.54)+","+Y(0)+" C"+X(0.56)+","+Y(-0.42)+" "+X(0.76)+","+Y(-0.42)+" "+X(0.8)+","+Y(0) };
    case "clutch":
      return { body:rounded(X(0),Y(0),X(1),Y(1),r*0.9),
               detail:"M"+X(0.04)+","+Y(0.42)+" Q"+X(0.5)+","+Y(0.56)+" "+X(0.96)+","+Y(0.42),
               strap:null };
    case "crossbody":
      return { body:rounded(X(0),Y(0),X(1),Y(1),r),
               detail:"M"+X(0.02)+","+Y(0.38)+" L"+X(0.98)+","+Y(0.38)+
                      "M"+X(0.44)+","+Y(0.38)+" h"+(0.12*w)+" v"+(0.1*h)+" h"+(-0.12*w)+" Z",
               strap:"M"+X(0.1)+","+Y(0.06)+" C"+X(0.02)+","+Y(-0.62)+" "+X(0.98)+","+Y(-0.62)+" "+X(0.9)+","+Y(0.06) };
    case "handbag":
      return { body:rounded(X(0),Y(0),X(1),Y(1),r*0.7),
               detail:"M"+X(0.42)+","+Y(0.03)+" h"+(0.16*w)+" v"+(0.12*h)+" h"+(-0.16*w)+" Z",
               strap:"M"+X(0.26)+","+Y(0)+" C"+X(0.3)+","+Y(-0.3)+" "+X(0.7)+","+Y(-0.3)+" "+X(0.74)+","+Y(0) };
    default:
      return { body:"M"+X(0.03)+","+Y(0.1)+" Q"+X(0.5)+","+Y(-0.03)+" "+X(0.97)+","+Y(0.1)+
                    " L"+X(0.93)+","+Y(0.9)+" Q"+X(0.5)+","+Y(1.03)+" "+X(0.07)+","+Y(0.9)+" Z",
               detail:null,
               strap:"M"+X(0.14)+","+Y(0.08)+" C"+X(0.2)+","+Y(-0.44)+" "+X(0.8)+","+Y(-0.44)+" "+X(0.86)+","+Y(0.08) };
  }
}

/**
 * Place the photograph so that its BODY BOX lands exactly on (x, y, w, h).
 *
 * The strap then falls wherever it naturally falls, outside that rectangle. The
 * box was chosen to have the product's own measured aspect ratio, so scaling it
 * to the real centimetres stretches nothing.
 */
function bagImageRect(bag, x, y, w, h){
  var b = bag.body, iw = w / b[2], ih = h / b[3];
  return { x: x - b[0]*iw, y: y - b[1]*ih, w: iw, h: ih };
}

var CLIP_N = 0;

function drawBag(g, bag, x, y, w, h, withStrap, clip){
  if (bag.cutout){
    var r = bagImageRect(bag, x, y, w, h);
    var img = attr(ns("image"), { x:r.x, y:r.y, width:r.w, height:r.h,
      href:bag.cutout, preserveAspectRatio:"none" });
    // ⭐ The photograph is scaled so the BAG BODY lands on the box it is
    // measured by, which means a chain or a long handle reaches far above it —
    // on one of these bags, 191 mm above a 280 mm bag. That spilled outside the
    // drawing and was chopped off by the panel it sits in, so the bag appeared
    // sliced across the top on a phone, where the panel is tighter, and not on
    // a desktop. In the fitting view the body is the whole subject, so the
    // photograph is held to it: the cut then lands exactly on the outline that
    // is drawn anyway, instead of somewhere across the middle of a chain.
    if (clip){
      var id = "kt-sv-clip-" + (++CLIP_N);
      var cp = attr(ns("clipPath"), { id:id });
      cp.appendChild(attr(ns("rect"), { x:x, y:y, width:w, height:h,
        rx:Math.min(w,h)*0.06, ry:Math.min(w,h)*0.06 }));
      g.appendChild(cp);
      attr(img, { "clip-path":"url(#" + id + ")" });
    }
    g.appendChild(img);
    return;
  }
  var p = shapePaths(bag.shape, x, y, w, h);
  if (withStrap !== false && p.strap)
    g.appendChild(attr(ns("path"), { d:p.strap, "class":"kt-sv__strap", "vector-effect":"non-scaling-stroke" }));
  g.appendChild(attr(ns("path"), { d:p.body, "class":"kt-sv__body", "vector-effect":"non-scaling-stroke" }));
  if (p.detail)
    g.appendChild(attr(ns("path"), { d:p.detail, "class":"kt-sv__detail", "vector-effect":"non-scaling-stroke" }));
}

/* ---------------------------------------------------------------------------
   The figure. Drawn once in a 1000-unit-tall box and scaled to whatever height
   she chooses, so the bag beside it is always at true relative scale. That is
   the whole trick: nothing here is decorative.
--------------------------------------------------------------------------- */
/* Proportions are a fashion croquis — a shade under nine heads, waist defined,
   arms drawn clear of the torso. A boxy figure reads as a mannequin and the
   whole point is that she should look like a person. */
/* The hair options, each a list of paths drawn behind the face.
   ponytail — centre-parted, past the jaw, gathered over one shoulder
   bun      — sleek and pulled back to a low chignon; the most formal
   down     — worn loose past the shoulders, the softest of the three */
var HAIR = {
  // Matched to the reference: the hair is a close CAP over the skull rather
  // than two long lengths framing the face, and a single ponytail falls
  // forward over one shoulder. The face inside it stays completely blank.
  ponytail: [
    "M-50,72 C-54,4 46,2 48,72 C48,110 44,140 38,160 L-38,162 C-46,140 -50,110 -50,72 Z",
    "M-38,116 C-62,138 -73,204 -68,266 C-65,296 -55,304 -48,295 C-55,246 -54,188 -32,148 Z"
  ],
  bun: [
    "M-48,64 C-52,-4 44,-6 46,64 C46,96 42,118 38,138 L14,138 C22,104 24,86 22,64 C6,76 -10,76 -26,64 C-28,86 -24,104 -16,138 L-40,138 C-44,118 -48,96 -48,64 Z",
    "M2,102 C24,102 38,114 38,130 C38,148 24,158 2,158 C-20,158 -34,148 -34,130 C-34,113 -20,102 2,102 Z"
  ],
  down: [
    "M-56,62 C-60,-6 52,-8 54,62 C54,122 50,182 46,244 C44,258 30,262 22,254 C28,192 30,124 26,64 C8,80 -10,80 -28,64 C-32,124 -30,192 -24,254 C-32,262 -46,258 -48,244 C-52,182 -56,122 -56,62 Z"
  ]
};

function drawFigure(g, build, style){
  // Everything below the chin widens with the build. The head follows only part
  // of the way — scaling it fully reads as a caricature, not scaling it at all
  // leaves a pin head on a fuller figure. Sleeves sit between the two.
  build = build || 1;
  var headK = 1 + (build - 1) * 0.35;
  var hairBack = attr(ns("g"), { transform:"scale("+headK+",1)" });                    // behind her
  var core     = attr(ns("g"), { transform:"scale("+build+",1)" });
  var arms     = attr(ns("g"), { transform:"scale("+(1 + (build - 1) * 0.55)+",1)" });
  var head     = attr(ns("g"), { transform:"scale("+headK+",1)" });                    // face, on top
  g.appendChild(hairBack); g.appendChild(core); g.appendChild(arms); g.appendChild(head);

  var into = core;
  function add(tag, o, cls){
    var el = attr(ns(tag), o); el.setAttribute("class", cls);
    into.appendChild(el);
    return el;
  }

  /* --------------------------------------------------------------------
     Built to the reference: a fitted long-sleeved turtleneck over dark
     high-waisted cropped trousers, bare ankles, black heels, hair pulled back
     with a ponytail over one shoulder. Feet apart and turned out, weight
     through her right leg.

     The details that actually carry the look, in order of how much they
     matter: the CONTRAST between a mid-grey top and near-black trousers; the
     strip of bare ankle above the shoe; and the ponytail sitting BEHIND her
     shoulder rather than down her chest, where it reads as a strap.
     ------------------------------------------------------------------ */

  // --- hair ----------------------------------------------------------------
  // Three styles, because which one suits the brand is a taste call and not
  // something to hard-code. All three sit BEHIND the face; a lock drawn on top
  // runs down her chest and reads as a bag strap.
  into = hairBack;
  var hair = HAIR[style] || HAIR.ponytail;
  for (var hp = 0; hp < hair.length; hp++) {
    add("path", { d:hair[hp] }, "kt-sv__hair");
  }

  // --- trousers: high waist, thighs converging, lower legs opening out -----
  // She stands with her feet apart, not together. The reference gets most of
  // its poise from that one thing: hips wide, knees close, ankles out again.
  add("path", { d:"M-53,390 C-68,426 -77,468 -75,508 C-80,572 -70,628 -62,698 C-60,784 -60,846 -60,888 L-30,888 C-30,846 -30,786 -30,702 C-28,648 -14,568 -4,512 C6,568 20,648 22,702 C22,786 22,846 22,888 L52,888 C52,846 52,784 54,698 C62,628 72,572 67,508 C69,468 60,426 45,388 Z" }, "kt-sv__cloth2");

  // --- bare ankles: the strip of skin above the shoe is a big part of it ---
  add("path", { d:"M-60,884 L-30,884 L-28,936 L-58,936 Z" }, "kt-sv__skin");
  add("path", { d:"M30,884 L60,884 L58,936 L28,936 Z" }, "kt-sv__skin");

  // --- black pumps: her left foot turned out in profile, her right nearly
  // square on. Each is drawn to its OWN ankle — mirroring them once left a
  // heel floating beside its shoe instead of under it.
  add("path", { d:"M-60,932 L-30,932 C-27,938 -26,948 -27,958 C-28,976 -32,990 -42,994 L-68,1000 C-74,1002 -78,998 -76,990 C-72,974 -66,952 -60,932 Z" }, "kt-sv__shoe");
  add("path", { d:"M-27,954 L-38,961 L-36,1000 L-26,1000 Z" }, "kt-sv__shoe");
  add("path", { d:"M30,932 L60,932 C66,952 72,974 76,990 C78,998 74,1002 68,1000 L42,994 C32,990 28,976 27,958 C26,948 27,938 30,932 Z" }, "kt-sv__shoe");
  add("path", { d:"M27,954 L38,961 L36,1000 L26,1000 Z" }, "kt-sv__shoe");

  // --- a narrow neck, a turtleneck collar, then the fitted top -------------
  // The neck was three quarters the width of her head and read as a tube. A
  // real one is nearer a third; the collar is what gives it any width at all.
  add("path", { d:"M-13,104 L13,104 C14,130 16,146 19,166 L-19,168 C-16,146 -14,130 -13,104 Z" }, "kt-sv__skin");
  add("path", { d:"M-16,128 L16,128 C17,146 19,160 22,176 L-22,178 C-19,160 -17,146 -16,128 Z" }, "kt-sv__cloth");

  // Shoulders slope away from the neck instead of meeting the sleeve at a
  // corner — that square notch was the "shoulder joint" problem.
  add("path", { d:"M-74,204 C-86,248 -83,298 -70,338 L-53,406 C-20,424 28,422 62,402 L52,384 C68,298 83,248 72,190 C60,174 44,166 24,164 L-22,166 C-44,170 -62,186 -74,204 Z" }, "kt-sv__cloth");

  // --- sleeves: capped under the shoulder, not perched on top of it --------
  into = arms;
  add("path", { d:"M72,190 C94,214 106,266 108,326 C110,376 108,420 105,472 L83,470 C86,418 88,374 87,328 C85,272 76,228 52,212 Z" }, "kt-sv__cloth3");
  add("path", { d:"M-74,204 C-96,228 -108,280 -110,340 C-112,390 -110,434 -107,486 L-85,484 C-88,432 -90,388 -89,342 C-87,286 -78,242 -54,226 Z" }, "kt-sv__cloth3");
  add("ellipse", { cx:94,  cy:484, rx:12, ry:21 }, "kt-sv__skin");
  add("ellipse", { cx:-96, cy:498, rx:12, ry:21 }, "kt-sv__skin");

  // --- face last, over the hair --------------------------------------------
  // A tapered jaw and a hairline that sits low. The plain wide oval it replaces
  // read as neither one thing nor the other.
  into = head;
  add("path", { d:"M0,22 C21,22 35,41 35,67 C35,88 27,107 14,118 C8,123 -8,123 -14,118 C-27,107 -35,88 -35,67 C-35,41 -21,22 0,22 Z" }, "kt-sv__skin");
  add("path", { d:"M-36,56 C-38,6 36,4 38,54 C30,26 -28,28 -36,56 Z" }, "kt-sv__hair");
  // No features. The reference leaves the face blank and it is the right call:
  // the head renders about 25 px tall, where any mark reads as a smudge, and a
  // blank face lets every customer see herself rather than someone else.
}

/** Where the bag actually lands on her, in her own terms. */
function whereItSits(topF, botF){
  var mid = (topF + botF) / 2;
  if (mid < 0.36) return "sits high on the chest";
  if (mid < 0.47) return "sits at the waist";
  if (mid < 0.58) return "sits at the hip";
  return "hangs down by the thigh";
}

/** Where the bag hangs, as a fraction of her height from the top of her head. */
/**
 * The ways a bag can be worn, as fractions of her height. Anchors match
 * drawFigure(): shoulder (±90, 178), hand (±96, 470).
 *
 * Every bag gets more than one, because "will it look right on me" and "how do
 * I carry it" are the same question. She drags the bag between them.
 */
function carryOptions(shape){
  // Her right side sits higher than her left — she is standing in contrapposto,
  // so the two sides are genuinely different and the anchors say so.
  var RH = { key:"right-hand", label:"In hand",    top:0.512, cx: 0.094, from:{ x: 0.094, y:0.484 } };
  var LH = { key:"left-hand",  label:"Other hand", top:0.524, cx:-0.096, from:{ x:-0.096, y:0.498 } };
  var RS = { key:"right-shoulder", label:"On the shoulder", top:0.415, cx: 0.118, from:{ x: 0.072, y:0.192 } };
  var LS = { key:"left-shoulder",  label:"Other shoulder",  top:0.428, cx:-0.118, from:{ x:-0.074, y:0.206 } };
  var XB = { key:"crossbody",  label:"Across the body", top:0.470, cx: 0.078, from:{ x:-0.074, y:0.206 } };

  // ⚠️ Every shape must offer a position on BOTH sides. Crossbody used to be
  // [across the body, right shoulder, right hand] — all three on her right, so
  // the ghosts stacked on one side and the bag could not be moved across her.
  // ⭐ The FIRST option is what she sees first, and every bag now opens in her
  // hand — so the handle or strap always meets her hand rather than being slung
  // somewhere she has to interpret. The other ways are still one drag away.
  if (shape === "clutch")    return [ RH, LH ];
  if (shape === "crossbody") return [ RH, XB, LS ];
  if (shape === "shoulder")  return [ RH, RS, LS ];
  return [ RH, LH, RS ];   // tote and handbag
}

/* ------------------------------ "On you" -------------------------------- */
function buildOnYou(bag, heightCm, activeKey, build){
  build = build || 1;
  var Hmm = heightCm * 10;
  var k   = Hmm / 1000;                       // figure units -> millimetres
  var bw  = bag.across*10, bh = bag.tall*10;

  // A wider body carries the bag further out, and reaches for it from further
  // out, so every anchor moves with the build.
  var armK = 1 + (build - 1) * 0.55;      // must match drawFigure()'s arm group
  var opts = carryOptions(bag.shape).map(function(o){
    return { key:o.key, label:o.label, top:o.top, cx:o.cx*build,
             from:o.from ? { x:o.from.x*armK, y:o.from.y } : null };
  });
  var active = 0, i;
  for (i=0;i<opts.length;i++){ if (opts[i].key === activeKey) active = i; }
  var c = opts[active];

  /* ------------------------------------------------------------------
     The frame is FIXED and she stands on a fixed floor. Only the figure
     changes size inside it.

     Sizing the drawing to whatever it happened to contain meant a 135 cm and a
     185 cm woman filled the panel identically — the frame shrank around her and
     she never appeared to change. So the scene is built once, at the size the
     TALLEST woman on the slider needs, and every height is drawn against that.
     ------------------------------------------------------------------ */
  var REF = 1850;                             // the slider's maximum, in mm
  var fs  = REF * 0.024;

  /**
   * Where the bag BODY sits for one carry option, measured from her centre line
   * and from the top of her head, at a given height.
   *
   * ⭐ A photograph brings its own strap, and the strap decides where the body
   * ends up — so a cut-out is HUNG from the shoulder or hand and the body falls
   * the length of that strap below it. Parking the body at a fixed height
   * instead left the strap ending in mid-air beside her, which is why she did
   * not look like she was holding the bag.
   *
   * The body still governs the SCALE. Only its position is derived from it.
   */
  function bodyOffset(opt, H){
    if (bag.cutout && opt.from) {
      var imgH  = bh / bag.body[3];            // the whole picture, in mm
      var above = bag.body[1] * imgH;          // how much strap sits above the body
      if (above >= bh * 0.12) {                // a real strap, not a rounding error
        var imgW = bw / bag.body[2];
        return { bx: opt.from.x*H - imgW/2 + bag.body[0]*imgW,
                 by: opt.from.y*H + above, hung: true };
      }
    }
    return { bx: opt.cx*H - bw/2, by: opt.top*H, hung: false };
  }

  // Room for the bag at EVERY carry position and at the tallest setting, so
  // nothing is ever clipped and nothing moves as she drags either slider.
  var figHalfMax = 140 * (REF / 1000) * build;
  var minX = -figHalfMax, maxX = figHalfMax, minY = 0;
  for (i = 0; i < opts.length; i++) {
    var o  = bodyOffset(opts[i], REF);
    var r  = bag.cutout ? bagImageRect(bag, o.bx, o.by, bw, bh)
                        : { x:o.bx, y:o.by, w:bw, h:bh };
    minX = Math.min(minX, r.x); maxX = Math.max(maxX, r.x + r.w);
    // In this space her head is 0 and her feet are REF, so a negative y is the
    // only thing that needs extra headroom — a photo whose strap rises above
    // her shoulder. Comparing against REF instead reserved a metre of nothing.
    minY = Math.min(minY, r.y);
  }

  var PADX   = fs*3.4, PADT = Math.max(fs*1.2, -minY + fs*0.6), PADB = fs*2.6;
  var figX   = PADX - minX;
  var sceneW = PADX + (maxX - minX) + PADX;
  var sceneH = PADT + REF + PADB;
  var floorY = PADT + REF;                    // constant: her feet never move
  var headY  = floorY - Hmm;                  // only the top of her head moves

  var svg = attr(ns("svg"), { viewBox:"0 0 "+sceneW+" "+sceneH, "class":"is-fixed", role:"img",
    "aria-label":"The bag shown on a figure "+heightCm+" centimetres tall, at true scale" });
  var sid = shadowDefs(svg, "ktsv-sh-"+(++uid));
  var g = ns("g");

  g.appendChild(attr(ns("path"), { d:"M"+(PADX*0.4)+","+floorY+" H"+(sceneW-PADX*0.4),
    "class":"kt-sv__floor", "vector-effect":"non-scaling-stroke" }));
  shadow(g, sid, figX, floorY, 140*k*build*2.1, fs*0.5);

  var fig = attr(ns("g"), { transform:"translate("+figX+","+headY+") scale("+k+")",
    "class":"kt-sv__fig" });
  drawFigure(fig, build, bag.hair);
  g.appendChild(fig);

  /** Draw the bag, and its strap, at one carry option. */
  /**
   * Where the bag actually hangs.
   *
   * ⭐ A photograph brings its own strap, and the strap is what decides where
   * the body ends up — so for a cut-out we hang the picture FROM the shoulder
   * or hand rather than parking its body at a fixed height. Placing the body
   * first left the strap ending in mid-air beside her, which is what made it
   * look like she was not holding the bag at all.
   *
   * The body still governs the SCALE. Only its position is derived.
   */
  function place(parent, opt, ghost){
    var pos = bodyOffset(opt, Hmm);
    var bx = figX + pos.bx, by = headY + pos.by;
    var grp = attr(ns("g"), { "class": ghost ? "kt-sv__ghost" : "kt-sv__carried" });

    /* Connect the bag to her.
       Two cases, and both have to be handled or she looks like she is standing
       next to a floating bag. If the photograph carries a strap ABOVE the body,
       the picture is hung from her shoulder and the strap meets her by itself.
       But most of these photographs show the strap folded down at the SIDES,
       level with the bag — nothing to hang from — so a strap is drawn instead,
       exactly as it is for the vector silhouette. */
    if (opt.from && !pos.hung){
      var sx = figX + opt.from.x*Hmm, sy = headY + opt.from.y*Hmm, nip = 7*k;
      grp.appendChild(attr(ns("path"), {
        d:"M"+(sx-nip)+","+sy+" L"+(bx+bw*0.24)+","+by+
          "M"+(sx+nip)+","+sy+" L"+(bx+bw*0.76)+","+by,
        "class":"kt-sv__strap", "vector-effect":"non-scaling-stroke" }));
    }
    if (ghost){
      grp.appendChild(attr(ns("rect"), { x:bx, y:by, width:bw, height:bh,
        rx:Math.min(bw,bh)*0.1, ry:Math.min(bw,bh)*0.1,
        "class":"kt-sv__ghostbox", "vector-effect":"non-scaling-stroke" }));
      // ⭐ Name each empty position. Two unlabelled boxes on the same side read
      // as a glitch; "On the shoulder" and "In hand" read as choices.
      text(grp, bx + bw/2, by + bh + fs*1.15, opt.label, "kt-sv__ghostlabel", fs*0.78);
    } else {
      drawBag(grp, bag, bx, by, bw, bh, false);
    }
    parent.appendChild(grp);
    return grp;
  }

  // Ghost outlines of the positions she has not chosen — the hint that the bag
  // can be moved at all.
  var ghosts = ns("g");
  attr(ghosts, { "class":"kt-sv__ghosts" });
  for (i=0;i<opts.length;i++){ if (i !== active) place(ghosts, opts[i], true); }
  g.appendChild(ghosts);

  var carried = place(g, c, false);
  attr(carried, { tabindex:"0", role:"button", "aria-label":"Carry position: "+c.label+". Drag, or use the arrow keys, to move it." });

  // Her height, marked down the left like a fashion croquis.
  var gx = PADX*0.55;
  g.appendChild(attr(ns("path"), {
    d:"M"+(gx-fs*0.4)+","+headY+" h"+(fs*0.8)+"M"+(gx-fs*0.4)+","+floorY+" h"+(fs*0.8)+"M"+gx+","+headY+" V"+floorY,
    "class":"kt-sv__guide", "vector-effect":"non-scaling-stroke" }));
  attr( text(g, gx, headY + Hmm/2, heightCm+" cm", "kt-sv__dim", fs),
        { transform:"rotate(-90 "+gx+" "+(headY+Hmm/2)+")", dy:-fs*0.45 } );

  text(g, figX, floorY + fs*1.5, fmt(bag.across)+" × "+fmt(bag.tall)+" cm", "kt-sv__bagname", fs*0.9);

  svg.appendChild(g);

  return {
    svg: svg, carried: carried, ghosts: ghosts, opts: opts, active: active,
    sceneW: sceneW, sceneH: sceneH, figX: figX, headY: headY, Hmm: Hmm, bw: bw, bh: bh,
    summary: "On someone "+heightCm+" cm tall, "+c.label.toLowerCase()+", this bag "+
             whereItSits(c.top, c.top + bh/Hmm)+". Drag it to try another way."
  };
}

/* Everyday things, in millimetres, laid flat. Sized from real objects rather
   than invented, because the whole module dies the moment one number is wrong. */
/* The things worth testing, at their real published sizes in millimetres.
   Kept short on purpose: a long tray of near-identical objects is noise, and
   these are the ones a customer actually weighs up. */
var THINGS = [
  { key:"iphone",   w:77.6,  h:163,   d:8.3,  label:"iPhone 16 Pro Max" },
  { key:"ipad",     w:177.5, h:249.7, d:5.3,  label:"iPad Pro 11\u2033" },
  // 30.41 x 21.5 x 1.13 cm. It had w and h the wrong way round, so a laptop
  // was being drawn taller than it is wide, which is the one shape a laptop
  // never has. Fit was unaffected (turning it tests both ways round) but it
  // looked like an iPad.
  { key:"macbook",  w:304.1, h:215,     d:11.3, label:"MacBook Air 13\u2033" },
  // ⚠️ These two are CATEGORIES, not a single published product, so they vary by
  // brand far more than the Apple sizes do. Checked against published ranges:
  // a 500 ml PET bottle runs 215-230 mm tall with a MAX diameter of 68-72 mm
  // (the widest point is what has to pass the opening, not the 52 mm base), and
  // a three-fold compact umbrella collapses to 230-280 mm. Both values below sit
  // at the small end of their range, so the module errs towards saying yes.
  { key:"bottle",   w:68,    h:212,   d:68,   label:"Water bottle 500 ml" },
  { key:"umbrella", w:52,    h:235,   d:52,   label:"Folding umbrella" },
  { key:"sunglasses", w:145, h:48,    d:40,   label:"Sunglasses" },
  { key:"cardcase", w:100,   h:75,    d:15,   label:"Card holder" },
  { key:"keys",     w:60,    h:95,    d:20,   label:"Keys" },
  { key:"perfume",  w:45,    h:95,    d:30,   label:"Perfume 30 ml" },
  { key:"lipstick", w:25,    h:85,    d:25,   label:"Lipstick" },
  { key:"compact",  w:70,    h:70,    d:14,   label:"Compact mirror" }
];

/**
 * A real box fit, not a flat one. Without the depth term a 500 ml bottle
 * "fits" a 5 cm-deep clutch, which is exactly the kind of confident wrong
 * answer this whole module exists to prevent.
 */
function fitsInside(bag, it){
  var W = bag.across*10, H = bag.tall*10, D = bag.deep*10;
  if (D <= 0) D = Math.min(W, H);
  if (it.d > D) return false;
  return (it.w <= W && it.h <= H) || (it.h <= W && it.w <= H);
}

/**
 * Upright, or given a quarter turn. Nothing is laid on its side.
 *
 * The FOOTPRINT repeats every half turn — a phone at 180 degrees takes up the
 * same rectangle as at 0 — but the ARTWORK does not, so the two are counted
 * separately. Collapsing them meant a phone turned twice snapped its camera
 * back to the top while appearing to keep turning.
 */
function facing(it, rot){
  return (rot % 2) ? { w:it.h, h:it.w, d:it.d } : { w:it.w, h:it.h, d:it.d };
}

/** Does it go in held the way it is now, and would turning it help? */
function verdictFor(bag, it, rot){
  var W = bag.across*10, H = bag.tall*10, D = bag.deep*10 || Math.min(W, H);
  var f = facing(it, rot);
  if (it.d > D){
    return { ok:false, text: it.label + " is too thick \u2014 this bag is only " + fmt(bag.deep) + " cm deep." };
  }
  if (f.w <= W && f.h <= H){
    var slack = Math.min(W - f.w, H - f.h);
    return { ok:true, text: it.label + (slack < 12 ? " only just goes in like this." : " goes in easily like this.") };
  }
  var o = facing(it, rot + 1);
  if (o.w <= W && o.h <= H){
    return { ok:false, text:"Not this way round \u2014 turn it and it goes in." };
  }
  return { ok:false, text: it.label + " is too big for this bag, whichever way you turn it." };
}

/* ---------------------------------------------------------------------------
   The objects themselves. Drawn with their own colours and details rather than
   as outlines: a tray of identical rounded rectangles is what made this tab
   feel dead, and "is that my phone or my purse?" is not a question the
   customer should have to ask.

   Each is drawn at its natural size and rotated as a whole, so the artwork
   never ends up stretched.
--------------------------------------------------------------------------- */
function shape(g, tag, o){ return g.appendChild(attr(ns(tag), o)); }
function rrect(g, x, y, w, h, r, fill, stroke, sw){
  return shape(g, "rect", { x:x, y:y, width:w, height:h, rx:r, ry:r, fill:fill,
    stroke:stroke || "none", "stroke-width":sw || 0, "vector-effect":sw ? "non-scaling-stroke" : null });
}

function drawThing(g, it, x, y, w, h){
  var X = function(f){ return x + f*w; }, Y = function(f){ return y + f*h; };
  var S = function(f){ return Math.min(w, h) * f; };

  switch (it.key){
    case "iphone":
      // Back of the phone: titanium body and the three-lens island.
      rrect(g, x, y, w, h, S(0.13), "#3b3f45", "#23262a", 1.2);
      rrect(g, X(0.05), Y(0.03), w*0.9, h*0.94, S(0.11), "#4a4f56");
      rrect(g, X(0.06), Y(0.02), w*0.46, w*0.46, S(0.12), "#2f3338", "#5a6068", 1);
      shape(g, "circle", { cx:X(0.17), cy:Y(0.02)+w*0.12, r:S(0.075), fill:"#15171a", stroke:"#6b727a", "stroke-width":1, "vector-effect":"non-scaling-stroke" });
      shape(g, "circle", { cx:X(0.40), cy:Y(0.02)+w*0.12, r:S(0.075), fill:"#15171a", stroke:"#6b727a", "stroke-width":1, "vector-effect":"non-scaling-stroke" });
      shape(g, "circle", { cx:X(0.17), cy:Y(0.02)+w*0.34, r:S(0.075), fill:"#15171a", stroke:"#6b727a", "stroke-width":1, "vector-effect":"non-scaling-stroke" });
      shape(g, "circle", { cx:X(0.40), cy:Y(0.02)+w*0.34, r:S(0.038), fill:"#c9b98a" });
      break;

    case "ipad":
      rrect(g, x, y, w, h, S(0.055), "#b9bcc0", "#8e9296", 1.2);
      rrect(g, X(0.035), Y(0.025), w*0.93, h*0.95, S(0.035), "#2b2e31");
      rrect(g, X(0.055), Y(0.04), w*0.89, h*0.92, S(0.025), "#f3f4f6");
      shape(g, "circle", { cx:X(0.5), cy:Y(0.022), r:S(0.018), fill:"#3a3d40" });
      break;

    case "macbook":
      // Closed and seen from above, a laptop is a plain slab, so everything
      // rests on the cues that say "this opens": the base showing as a thin
      // darker frame all round (two plates stacked, not one tablet), the hinge
      // strip along the back, and the wide shallow finger recess cut into the
      // front edge. The mark in the middle of the lid is deliberately absent:
      // it is a trademark, and the shape reads without it.
      rrect(g, X(-0.008), Y(-0.007), w*1.016, h*1.014, S(0.055), "#8b9197");
      rrect(g, x, y, w, h, S(0.05), "#c9ccd0", "#8a9096", 1.3);
      rrect(g, X(0.021), Y(0.03), w*0.958, h*0.94, S(0.04), "#d9dce0");
      // brushed-aluminium sheen, falling away to the right
      shape(g, "path", { d:"M"+X(0.03)+","+Y(0.04)+" L"+X(0.34)+","+Y(0.04)+
                            " L"+X(0.11)+","+Y(0.96)+" L"+X(0.03)+","+Y(0.96)+" Z",
        fill:"#e7eaed", opacity:"0.6" });
      // the hinge, dark because on a real one you are looking into the gap
      // between lid and base. It is the single strongest cue that this thing
      // opens rather than being a tablet.
      rrect(g, X(0.06), Y(0.02), w*0.88, h*0.042, h*0.021, "#6f767d");
      shape(g, "path", { d:"M"+X(0.06)+","+Y(0.066)+" H"+X(0.94),
        stroke:"#b3b9bf", "stroke-width":1.2, fill:"none", "vector-effect":"non-scaling-stroke" });
      // the finger recess: wide and shallow, or it reads as a tab rather than
      // somewhere to put a thumb.
      shape(g, "path", { d:"M"+X(0.37)+","+Y(0.972)+" q"+(w*0.13)+",-"+(h*0.05)+" "+(w*0.26)+",0 Z",
        fill:"#a6acb2" });
      break;

    case "bottle":
      var nw = w*0.44, cap = h*0.075;
      rrect(g, X(0.5)-nw/2, y, nw, cap, S(0.05), "#5b7f9e");
      shape(g, "path", { d:"M"+(X(0.5)-nw/2)+","+(y+cap)+" v"+(h*0.05)+
        " L"+x+","+Y(0.2)+" V"+Y(0.96)+" a"+S(0.06)+","+S(0.06)+" 0 0 0 "+S(0.06)+","+S(0.06)+
        " H"+(x+w-S(0.06))+" a"+S(0.06)+","+S(0.06)+" 0 0 0 "+S(0.06)+",-"+S(0.06)+
        " V"+Y(0.2)+" L"+(X(0.5)+nw/2)+","+(y+cap+h*0.05)+" v"+(-h*0.05)+" Z",
        fill:"#dfeaf1", stroke:"#a9c0d0", "stroke-width":1.2, "vector-effect":"non-scaling-stroke" });
      rrect(g, X(0.02), Y(0.42), w*0.96, h*0.3, 0, "#e8edf0", "#c3d2db", 1);
      shape(g, "path", { d:"M"+X(0.12)+","+Y(0.5)+" h"+(w*0.76)+"M"+X(0.12)+","+Y(0.58)+" h"+(w*0.5),
        stroke:"#9fb3c0", "stroke-width":1.4, fill:"none", "vector-effect":"non-scaling-stroke" });
      break;

    case "umbrella":
      rrect(g, X(0.16), Y(0.06), w*0.68, h*0.86, S(0.3), "#3f4a55", "#2b333b", 1.2);
      shape(g, "path", { d:"M"+X(0.5)+","+Y(0.06)+" v"+(h*0.86),
        stroke:"#59646f", "stroke-width":1.4, fill:"none", "vector-effect":"non-scaling-stroke" });
      rrect(g, X(0.3), Y(0)+0, w*0.4, h*0.07, S(0.16), "#8a939c");
      rrect(g, X(0.24), Y(0.9), w*0.52, h*0.09, S(0.16), "#6b7681");
      break;

    case "sunglasses":
      // Two ellipses and a bridge came out looking like a moustache. Squared
      // lenses with a proper frame, a raised brow bar and folded temples read
      // as sunglasses even at tray size.
      var lw = w*0.435, lh = h*0.8, ly2 = Y(0.12), rad = Math.min(lw, lh)*0.3;
      // temples, folded back behind the frame
      shape(g, "path", { d:"M"+X(0.09)+","+Y(0.3)+" L"+X(0.02)+","+Y(0.62)+
                            "M"+X(0.91)+","+Y(0.3)+" L"+X(0.98)+","+Y(0.62),
        stroke:"#26292d", "stroke-width":3, fill:"none", "stroke-linecap":"round",
        "vector-effect":"non-scaling-stroke" });
      rrect(g, x, ly2, lw, lh, rad, "#38424c", "#1e2226", 2.6);
      rrect(g, X(1)-lw, ly2, lw, lh, rad, "#38424c", "#1e2226", 2.6);
      // brow bar across the top and the bridge between the lenses
      rrect(g, X(0.42), ly2 + lh*0.06, w*0.16, lh*0.2, lh*0.09, "#1e2226");
      shape(g, "path", { d:"M"+x+","+(ly2+lh*0.1)+" H"+X(1),
        stroke:"#1e2226", "stroke-width":3.4, fill:"none", "vector-effect":"non-scaling-stroke" });
      // a glint on each lens
      shape(g, "path", { d:"M"+X(0.07)+","+(ly2+lh*0.72)+" L"+X(0.2)+","+(ly2+lh*0.3)+
                            "M"+X(0.64)+","+(ly2+lh*0.72)+" L"+X(0.77)+","+(ly2+lh*0.3),
        stroke:"#8d9aa6", "stroke-width":2.2, fill:"none", "stroke-linecap":"round",
        opacity:"0.7", "vector-effect":"non-scaling-stroke" });
      break;

    case "cardcase":
      rrect(g, x, y, w, h, S(0.09), "#6d4b34", "#4c331f", 1.2);
      rrect(g, X(0.06), Y(0.1), w*0.88, h*0.8, S(0.07), "#7d5941");
      shape(g, "path", { d:"M"+X(0.24)+","+Y(0.34)+" h"+(w*0.52)+" a"+S(0.06)+","+S(0.06)+" 0 0 1 0,"+S(0.12)+" h"+(-w*0.52)+" Z",
        fill:"#efe7dc" });
      break;

    case "keys":
      shape(g, "circle", { cx:X(0.5), cy:Y(0.16), r:S(0.15), fill:"none", stroke:"#9aa1a8", "stroke-width":3.4, "vector-effect":"non-scaling-stroke" });
      rrect(g, X(0.34), Y(0.3), w*0.16, h*0.62, S(0.06), "#b9bfc5");
      rrect(g, X(0.52), Y(0.3), w*0.16, h*0.55, S(0.06), "#d8b45c");
      shape(g, "path", { d:"M"+X(0.5)+","+Y(0.74)+" h"+(w*0.1)+"M"+X(0.5)+","+Y(0.84)+" h"+(w*0.07),
        stroke:"#8f949a", "stroke-width":2.2, fill:"none", "vector-effect":"non-scaling-stroke" });
      break;

    case "perfume":
      rrect(g, X(0.32), y, w*0.36, h*0.16, S(0.06), "#3c3a37");
      rrect(g, X(0.4), Y(0.14), w*0.2, h*0.1, 0, "#c9b98a");
      shape(g, "path", { d:"M"+X(0.08)+","+Y(0.28)+" q0,-"+(h*0.06)+" "+(w*0.14)+",-"+(h*0.06)+
        " h"+(w*0.56)+" q"+(w*0.14)+",0 "+(w*0.14)+","+(h*0.06)+" V"+Y(0.94)+
        " q0,"+(h*0.06)+" -"+(w*0.14)+","+(h*0.06)+" h"+(-w*0.56)+" q-"+(w*0.14)+",0 -"+(w*0.14)+",-"+(h*0.06)+" Z",
        fill:"#e7dcc8", stroke:"#c2b49b", "stroke-width":1.2, "vector-effect":"non-scaling-stroke" });
      rrect(g, X(0.22), Y(0.5), w*0.56, h*0.24, S(0.03), "#cbb994");
      break;

    case "lipstick":
      rrect(g, X(0.12), y, w*0.76, h*0.44, S(0.16), "#2f2b2a");
      rrect(g, X(0.1), Y(0.42), w*0.8, h*0.08, 0, "#c9a24a");
      rrect(g, X(0.14), Y(0.48), w*0.72, h*0.52, S(0.1), "#3a3533");
      break;

    case "compact":
      shape(g, "circle", { cx:X(0.5), cy:Y(0.5), r:S(0.48), fill:"#d9b7a0", stroke:"#b08d78", "stroke-width":1.4, "vector-effect":"non-scaling-stroke" });
      shape(g, "circle", { cx:X(0.5), cy:Y(0.5), r:S(0.34), fill:"#e8d3c5" });
      rrect(g, X(0.44), Y(0.0), w*0.12, h*0.08, S(0.03), "#c0a48f");
      break;

    default:
      rrect(g, x, y, w, h, S(0.12), "#cfc6ba", "#a99c8c", 1.2);
  }
}

/** Draw it at natural size and turn the whole thing, so nothing is stretched. */
function placeThing(parent, it, rot, x, y){
  var f = facing(it, rot);
  var g = ns("g");
  var ox = x + (f.w - it.w)/2, oy = y + (f.h - it.h)/2;
  drawThing(g, it, ox, oy, it.w, it.h);
  var q = ((rot % 4) + 4) % 4;
  if (q){
    attr(g, { transform:"rotate(" + (q * 90) + " " + (x + f.w/2) + " " + (y + f.h/2) + ")" });
  }
  parent.appendChild(g);
  return f;
}

/* ---------------------------- "Will it fit" ------------------------------ */
function buildFits(bag, state, narrow){
  var W = bag.across*10, H = bag.tall*10, D = bag.deep*10;

  // ⭐ A FIXED frame, identical on every product in the shop.
  //
  // The drawing used to be sized to whatever it happened to contain, so the
  // scale changed from bag to bag: a small clutch was blown up to fill the
  // panel and the laptop laid over it looked monstrous, while the very same
  // laptop on a 40 cm tote looked modest. Nothing could be compared with
  // anything, and a small bag did not look small — which is the one thing this
  // module exists to convey.
  //
  // So the frame is a constant in millimetres and everything is drawn true to
  // size inside it. A clutch now occupies a small part of it and a tote nearly
  // all of it, which is the honest picture. The reference is a little larger
  // than the largest bag in the shop (40 x 28 cm) and comfortably larger than
  // the largest thing in the tray (a MacBook, 30.4 x 21.5 cm), and it grows if
  // a bigger bag is ever added — that one product then draws at its own scale
  // rather than being clipped.
  var REF_W = 420, REF_H = 300, REF_BAND = 170;    // mm
  var f = (state && state.item) ? facing(state.item, state.rot) : null;

  // On a phone the edge-on strip costs a quarter of the width for something the
  // caption under the bag and the DEEP box below already say in words, and that
  // quarter is taken out of everything else: bag, object and lettering all
  // shrink to pay for it. It is dropped where the panel is narrow and kept
  // where there is room, so the same scale still applies to every product on a
  // given screen. When depth is what stops an item going in, the verdict says
  // so in a sentence either way.
  var roomy = !narrow;
  var frameW = Math.max(REF_W, W, f ? f.w : 0);
  var frameH = Math.max(REF_H, H, f ? f.h : 0);

  // Sized from the frame, not from the bag, or the padding and lettering would
  // scale with the bag and undo the whole point of a fixed frame.
  var fs = Math.max(REF_W, REF_H) * 0.05;
  var PAD = fs*(narrow ? 1.3 : 2.4), GAP = fs*1.5;

  // A strip showing the bag EDGE ON, next to the face-on view. Depth was being
  // judged but never shown: an item too thick to go in still sat neatly inside
  // the outline, so the refusal read as arbitrary. Thickness is the one
  // dimension a face-on drawing cannot show, so it gets its own view. Its band
  // is fixed too: anything that varies changes the scene box, and the scene box
  // is what sets the scale.
  var SIDE = (roomy && D > 0) ? D : 0, band = Math.max(REF_BAND, SIDE);

  var BX = PAD + (frameW - W)/2;                   // the bag, centred in it
  var BY = PAD + (frameH - H)/2;
  var sideX  = PAD + frameW + GAP;
  var sceneW = sideX + (SIDE ? band + PAD : PAD - GAP);
  // The label stays glued to the bag rather than sitting at a fixed height, so
  // it always reads as this bag's size. Drawn last, over everything, with a
  // halo behind it, so a thing laid across the bag cannot bury it.
  var capY   = BY + H + fs*1.2;
  var sceneH = PAD + frameH + fs*1.7 + PAD*0.5;

  var svg = attr(ns("svg"), { viewBox:"0 0 "+sceneW+" "+sceneH, role:"img",
    "aria-label":"Drag an everyday item onto the bag to see whether it goes in" });
  var g = ns("g");

  // The bag at true scale, and the opening everything is measured against.
  drawBag(g, bag, BX, BY, W, H, false, true);
  g.appendChild(attr(ns("rect"), { x:BX, y:BY, width:W, height:H,
    rx:Math.min(W,H)*0.06, ry:Math.min(W,H)*0.06,
    "class":"kt-sv__opening", "vector-effect":"non-scaling-stroke" }));
  if (SIDE){
    g.appendChild(attr(ns("rect"), { x:sideX, y:BY, width:SIDE, height:H,
      rx:Math.min(SIDE,H)*0.06, ry:Math.min(SIDE,H)*0.06,
      "class":"kt-sv__side", "vector-effect":"non-scaling-stroke" }));
  }

  var v = null;
  if (state && state.item){
    v = verdictFor(bag, state.item, state.rot);

    // Held as a centre RELATIVE TO THE BAG, and clamped as one. Relative,
    // because turning a big object changes how far it overhangs and so shifts
    // the whole layout: an absolute position would slide against the bag every
    // quarter turn. A centre, because a corner would swing the object sideways
    // as it turned.
    var acx = BX + ((state.cx === null) ? W/2 : state.cx);
    var acy = BY + ((state.cy === null) ? H/2 : state.cy);
    var loX = f.w/2 + fs*0.2, hiX = (SIDE ? sideX - GAP*0.4 : sceneW - fs*0.2) - f.w/2;
    var loY = f.h/2 + fs*0.2, hiY = sceneH - fs*1.2 - f.h/2;
    acx = (loX > hiX) ? (loX + hiX)/2 : Math.max(loX, Math.min(hiX, acx));
    acy = (loY > hiY) ? (loY + hiY)/2 : Math.max(loY, Math.min(hiY, acy));
    state.cx = acx - BX; state.cy = acy - BY;
    var ix = acx - f.w/2, iy = acy - f.h/2;

    // See-through only when it actually hangs over the bag and would hide it.
    // Failing merely because it needs turning is not a reason to fade it: the
    // bag is already visible all round it, and a ghosted object reads as one
    // that has been disabled.
    var hides = (f.w > W + fs*0.5) || (f.h > H + fs*0.5);
    var grp = attr(ns("g"), { "class":"kt-sv__thing " + (v.ok ? "is-in" : "is-out") + (hides ? " is-over" : ""),
      tabindex:"0", role:"button",
      "aria-label": state.item.label + ". " + v.text + " Drag to move it, or press R to turn it." });
    // The artwork and its box live in one group so the turn can be animated as
    // a single thing. The handle stays outside it, or it would spin too.
    var spin = attr(ns("g"), { "class":"kt-sv__spin" });
    placeThing(spin, state.item, state.rot, ix, iy);
    // An outline only when it does NOT go in. When it does, the object speaks
    // for itself and a box is just clutter. The one case that genuinely needs
    // marking is an item that fits the opening but is too THICK - it sits
    // neatly inside the bag and still does not go in, which without a cue
    // reads as the module contradicting itself.
    spin.appendChild(attr(ns("rect"), { x:ix, y:iy, width:f.w, height:f.h,
      rx:Math.min(f.w,f.h)*0.08, ry:Math.min(f.w,f.h)*0.08,
      "class":"kt-sv__thingbox" + (v.ok ? " is-quiet" : ""),
      "vector-effect":"non-scaling-stroke" }));
    grp.appendChild(spin);
    state.spin = spin;

    // A transparent pad over the whole footprint, so a finger anywhere on the
    // object picks it up. Without it only the drawn shapes are hittable and the
    // gaps between them are not — a lipstick is a few millimetres of artwork
    // to aim at, which is no target at all on a phone. Padded out to a minimum
    // so the smallest things can still be grabbed. fill must be transparent,
    // not none: fill:none is invisible to hit testing.
    var grab = Math.max(0, (fs*1.6 - Math.min(f.w, f.h)) / 2);
    grp.appendChild(attr(ns("rect"), { x:ix - grab, y:iy - grab,
      width:f.w + grab*2, height:f.h + grab*2,
      fill:"transparent", "class":"kt-sv__grab" }));

    // A turn handle on the object itself, as in the reference: it appears on
    // hover, and stays put on a touch screen where there is no hover to have.
    // Sized from the scene, not the object, so it never becomes a speck on a
    // lipstick or a dinner plate on a MacBook.
    var rr = fs*0.62, rcx = ix + f.w + rr*0.15, rcy = iy - rr*0.15;
    var rot = attr(ns("g"), { "class":"kt-sv__rotate", role:"button", tabindex:"0",
      "aria-label":"Turn it a quarter turn" });
    rot.appendChild(attr(ns("circle"), { cx:rcx, cy:rcy, r:rr, "class":"kt-sv__rotate-disc" }));
    rot.appendChild(attr(ns("path"), {
      d:"M"+(rcx - rr*0.42)+","+(rcy + rr*0.1)+
        " a"+(rr*0.44)+","+(rr*0.44)+" 0 1 1 "+(rr*0.34)+","+(rr*0.4)+
        "M"+(rcx - rr*0.62)+","+(rcy - rr*0.06)+" l"+(rr*0.2)+","+(rr*0.2)+" l"+(rr*0.24)+",-"+(rr*0.26),
      "class":"kt-sv__rotate-icon", "vector-effect":"non-scaling-stroke" }));
    grp.appendChild(rot);
    state.rotHandle = rot;

    g.appendChild(grp);

    // The same object seen edge on, at the same scale and lined up with itself.
    // When it is too thick it visibly breaks out of the strip, which is the
    // whole point - the customer can see the refusal, not just read it.
    if (SIDE){
      var tooThick = state.item.d > D;
      var sg = attr(ns("g"), { "class":"kt-sv__sidething" + (tooThick ? " is-out" : "") });
      sg.appendChild(attr(ns("rect"), { x:sideX, y:iy, width:f.d, height:f.h,
        rx:Math.min(f.d,f.h)*0.12, ry:Math.min(f.d,f.h)*0.12,
        "class":"kt-sv__sidebox", "vector-effect":"non-scaling-stroke" }));
      g.appendChild(sg);
    }
    state.node = grp;
    state.fw = f.w; state.fh = f.h;
  }

  // Last, so they sit above everything: the bag's own edge and the labels. A
  // thing bigger than the bag would otherwise bury both.
  g.appendChild(attr(ns("rect"), { x:BX, y:BY, width:W, height:H,
    rx:Math.min(W,H)*0.06, ry:Math.min(W,H)*0.06,
    "class":"kt-sv__opening kt-sv__opening--over", "vector-effect":"non-scaling-stroke" }));
  text(g, BX + W/2, capY,
       fmt(bag.across)+" × "+fmt(bag.tall)+" × "+fmt(bag.deep)+" cm",
       "kt-sv__bagname kt-sv__halo", fs*0.78);
  if (SIDE){
    text(g, sideX + SIDE/2, capY, fmt(bag.deep)+" cm deep",
         "kt-sv__caption kt-sv__halo", fs*0.7);
    text(g, sideX + SIDE/2, fs*1.5, "from the side",
         "kt-sv__caption kt-sv__caption--sub kt-sv__halo", fs*0.62);
  }

  svg.appendChild(g);
  var n = 0, i;
  for (i=0;i<THINGS.length;i++){ if (fitsInside(bag, THINGS[i])) n++; }
  return { svg:svg, sceneW:sceneW, verdict:v,
    summary: v ? v.text
               : n + " of " + THINGS.length + " everyday things go in. Pick one below and drag it onto the bag." };
}



/* ------------------------------- controller ------------------------------ */
function boot(root){
  var canvas = root.querySelector("[data-kt-canvas]");
  var scroll = root.querySelector("[data-kt-scroll]");
  var hint   = root.querySelector("[data-kt-hint]");
  var stage  = root.querySelector("[data-kt-stage]");
  var hBox   = root.querySelector("[data-kt-height]");
  var tray   = root.querySelector("[data-kt-tray]");
  var hIn    = root.querySelector("[data-kt-height-input]");
  var hOut   = root.querySelector("[data-kt-height-out]");
  var bIn    = root.querySelector("[data-kt-build-input]");
  var bOut   = root.querySelector("[data-kt-build-out]");
  var tabs   = root.querySelectorAll(".kt-sv__tab");
  if (!canvas) return;

  var body = (root.getAttribute("data-cutout-body") || "0,0,1,1").split(",").map(num);
  if (body.length !== 4 || body[2] <= 0 || body[3] <= 0) body = [0, 0, 1, 1];

  var bag = {
    across: num(root.getAttribute("data-across")),
    tall:   num(root.getAttribute("data-tall")),
    deep:   num(root.getAttribute("data-deep")),
    shape:  root.getAttribute("data-shape") || "shoulder",
    cutout: root.getAttribute("data-cutout") || "",
    body:   body,
    hair:   root.getAttribute("data-hair") || "ponytail"
  };
  if (bag.across <= 0 || bag.tall <= 0) return;

  var view = "onyou";
  // What she is currently trying in the bag, and how she is holding it.
  // ⭐ The CENTRE is stored, not the top-left corner. A quarter turn swaps the
  // footprint, so pinning the corner would slide the object sideways every time
  // it was turned; pinning the centre makes it pivot where it sits.
  var fitState = { item:null, rot:0, cx:null, cy:null, node:null, fw:0, fh:0 };
  var heightCm = store(K_HT) || 155;
  // Stored 1-based: store() cannot tell "absent" from "zero", and index 0 is a
  // real build, so a first-time visitor was being handed the Slim figure.
  var buildIx  = store(K_BUILD) - 1;
  if (!(buildIx >= 0 && buildIx < BUILDS.length)) buildIx = 1;
  var carryKey = "";           // which carry position is showing
  if (hIn){ hIn.value = heightCm; }
  if (hOut){ hOut.textContent = heightCm + " cm"; }
  if (bIn){ bIn.value = buildIx; }
  if (bOut){ bOut.textContent = BUILDS[buildIx].label; }

  function show(node){
    while (canvas.firstChild) canvas.removeChild(canvas.firstChild);
    if (node) canvas.appendChild(node);
    canvas.classList.toggle("is-fits", view === "fits");
  }

  /**
   * Lettering that stays readable however small the drawing goes.
   *
   * Text inside the drawing is written in millimetres like everything else, so
   * it shrinks with the scale — and on a phone, where a 42 cm frame is squeezed
   * into a few hundred pixels, the captions came out too small to read at all.
   * Sizes are therefore raised, after the drawing is on the page and its real
   * width is known, until they clear a floor in actual screen pixels. On a
   * desktop nothing is touched, because nothing is under the floor there.
   */
  function readable(svg, sceneW){
    if (!svg || !sceneW) return;
    var w = svg.getBoundingClientRect().width;
    if (!w) return;
    var k = w / sceneW, MIN = 12.5;          // px
    var t = svg.querySelectorAll("text"), i, size;
    for (i = 0; i < t.length; i++){
      size = parseFloat(t[i].getAttribute("font-size"));
      if (size && size * k < MIN) t[i].setAttribute("font-size", MIN / k);
    }
  }
  function setHint(msg){
    if (!hint) return;
    if (msg){ hint.textContent = msg; hint.hidden = false; } else { hint.hidden = true; }
  }
  /**
   * How tall the drawing may be.
   *
   * Inside the window this is measured, not declared: the panel is a fixed
   * height and the stage flexes, so whatever is left after the tabs, sliders
   * and figures is what the drawing gets. That is what removes the scrollbar.
   * The measurement is safe from feedback because the scroller has
   * min-height:0 and hides its own overflow, so the drawing cannot grow it.
   */
  function panelCap(){
    var inModal = root.closest ? root.closest(".kt-sv-modal") : null;
    if (inModal && scroll && scroll.clientHeight > 160) {
      return scroll.clientHeight - 34;
    }
    var v = 0;
    try { v = parseFloat(getComputedStyle(canvas).getPropertyValue("--kt-max")); } catch(e){}
    return (isFinite(v) && v > 80) ? v : 520;
  }

  /**
   * Let her pick the bag up and move it between carry positions.
   *
   * Pointer events cover mouse, touch and pen in one path. A press that never
   * really moves is treated as a tap and advances to the next position, because
   * on a phone "drag the little bag precisely" is a poor instruction.
   */
  function wireDrag(built){
    var handle = built.carried;
    if (!handle || built.opts.length < 2) return;

    var svg = built.svg, dragging = false, moved = false, sx = 0, sy = 0, scale = 1;

    function sceneScale(){
      var r = svg.getBoundingClientRect();
      return r.width ? built.sceneW / r.width : 1;
    }
    function nearest(dxMm, dyMm){
      var cur = built.opts[built.active];
      var cx  = built.figX + cur.cx*built.Hmm + dxMm;
      var cy  = built.headY + cur.top*built.Hmm + dyMm;
      var best = built.active, bestD = Infinity;
      for (var i=0;i<built.opts.length;i++){
        var ox = built.figX + built.opts[i].cx*built.Hmm;
        var oy = built.headY + built.opts[i].top*built.Hmm;
        var d  = (ox-cx)*(ox-cx) + (oy-cy)*(oy-cy);
        if (d < bestD){ bestD = d; best = i; }
      }
      return best;
    }
    function settle(idx){
      carryKey = built.opts[idx].key;
      render();
    }

    // ⭐ Move and release are listened for on the WINDOW, not on the bag.
    // On the bag they depend on setPointerCapture, which is not dependable on
    // an SVG element in mobile browsers — the finger leaves the shape almost
    // at once and the drag dies, while a mouse works perfectly. A tap still
    // worked, which is what made this look like "drag does nothing" rather
    // than "no events". Bound only while a drag is running.
    var pid = null;

    function onMove(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      e.preventDefault();
      var dx = (e.clientX - sx) * scale, dy = (e.clientY - sy) * scale;
      if (Math.abs(e.clientX - sx) + Math.abs(e.clientY - sy) > 4) moved = true;
      handle.setAttribute("transform", "translate("+dx+","+dy+")");
      var near = nearest(dx, dy);
      var kids = built.ghosts.childNodes;
      for (var i=0, j=0;i<built.opts.length;i++){
        if (i === built.active) continue;
        if (kids[j]) kids[j].classList.toggle("is-near", i === near);
        j++;
      }
    }
    function unbind(){
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", end);
      window.removeEventListener("pointercancel", onCancel);
    }
    function end(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      dragging = false; pid = null; unbind();
      handle.classList.remove("is-dragging");
      svg.classList.remove("is-picking");
      var dx = (e.clientX - sx) * scale, dy = (e.clientY - sy) * scale;
      // A tap, not a drag: step to the next position rather than doing nothing.
      settle( moved ? nearest(dx, dy) : (built.active + 1) % built.opts.length );
    }
    function onCancel(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      dragging = false; pid = null; unbind();
      handle.removeAttribute("transform");
      handle.classList.remove("is-dragging");
      svg.classList.remove("is-picking");
    }

    handle.addEventListener("pointerdown", function(e){
      if (dragging) return;               // a second finger must not hijack it
      dragging = true; moved = false; pid = e.pointerId;
      sx = e.clientX; sy = e.clientY; scale = sceneScale();
      handle.classList.add("is-dragging");
      svg.classList.add("is-picking");
      if (handle.setPointerCapture) { try { handle.setPointerCapture(e.pointerId); } catch(err){} }
      window.addEventListener("pointermove", onMove, { passive:false });
      window.addEventListener("pointerup", end);
      window.addEventListener("pointercancel", onCancel);
      e.preventDefault();
    });

    handle.addEventListener("keydown", function(e){
      var k = e.key, n = built.opts.length;
      if (k === "ArrowRight" || k === "ArrowDown" || k === " " || k === "Enter"){
        settle((built.active + 1) % n); e.preventDefault();
      } else if (k === "ArrowLeft" || k === "ArrowUp"){
        settle((built.active - 1 + n) % n); e.preventDefault();
      }
    });
  }

  /** The row of things she can try, drawn from the same list as the fit test. */
  function buildTray(){
    if (!tray) return;
    if (tray.getAttribute("data-built") !== "1"){
      var frag = document.createDocumentFragment();
      for (var i=0;i<THINGS.length;i++){
        (function(it){
          var b = document.createElement("button");
          b.type = "button";
          b.className = "kt-sv__pick" + (fitsInside(bag, it) ? "" : " is-too-big");
          var mini = ns("svg");
          attr(mini, { viewBox:"0 0 40 40", "aria-hidden":"true" });
          var mg = ns("g");
          // Draw it into a 40-unit box, keeping its real proportions.
          var sc = 30 / Math.max(it.w, it.h);
          drawThing(mg, it, 20 - it.w*sc/2, 20 - it.h*sc/2, it.w*sc, it.h*sc);
          mini.appendChild(mg);
          b.appendChild(mini);
          b.appendChild(document.createTextNode(it.label));
          b.addEventListener("click", function(){
            fitState.item = it; fitState.rot = 0; fitState.cx = null; fitState.cy = null;
            render();
          });
          frag.appendChild(b);
        })(THINGS[i]);
      }
      var turn = document.createElement("button");
      turn.type = "button"; turn.className = "kt-sv__turn"; turn.textContent = "Turn it";
      turn.addEventListener("click", function(){ turnThing(); });
      frag.appendChild(turn);
      tray.appendChild(frag);
      tray.setAttribute("data-built", "1");
    }
    var picks = tray.querySelectorAll(".kt-sv__pick");
    for (var j=0;j<picks.length;j++){
      picks[j].classList.toggle("is-on", !!fitState.item && picks[j].lastChild.nodeValue === fitState.item.label);
    }
  }

  /**
   * Turn it a quarter turn, with the swing shown rather than jumped.
   *
   * The drawing is rebuilt on every change, and a rebuilt element cannot
   * transition from a state it never had — so the swing is played on the
   * element that is already on screen and the rebuild happens once it lands.
   * Because the CENTRE is what is stored, the rebuild lands exactly where the
   * animation finished and nothing shifts.
   */
  function turnThing(){
    if (!fitState.item || fitState.spinning) return;
    var spin = fitState.spin;
    var still = false;
    try { still = window.matchMedia("(prefers-reduced-motion: reduce)").matches; } catch(e){}
    if (!spin || still){
      fitState.rot = (fitState.rot + 1) % 4;
      render();
      return;
    }
    fitState.spinning = true;
    spin.style.transform = "rotate(90deg)";
    window.setTimeout(function(){
      fitState.spinning = false;
      fitState.rot = (fitState.rot + 1) % 4;
      render();
    }, 300);
  }

  /** Pick the thing up, move it, turn it. */
  function wireThing(built){
    var node = fitState.node;
    if (!node) return;

    // The handle must swallow the press, or picking it up starts a drag instead.
    var handle = fitState.rotHandle;
    if (handle){
      handle.addEventListener("pointerdown", function(e){ e.stopPropagation(); });
      handle.addEventListener("click", function(e){
        e.stopPropagation();
        turnThing();
      });
      handle.addEventListener("keydown", function(e){
        if (e.key === "Enter" || e.key === " "){ turnThing(); e.preventDefault(); }
      });
    }

    var svg = built.svg, dragging = false, pid = null;
    var sx = 0, sy = 0, ox = 0, oy = 0, scale = 1;

    // ⭐ The move and release listeners go on the WINDOW, not on the object.
    //
    // They used to be on the object itself, which works with a mouse and fails
    // completely on a phone: it relies on setPointerCapture, and capture on an
    // SVG element is not dependable across mobile browsers. Without it the
    // finger leaves the shape within a few pixels, no further pointermove ever
    // reaches the object, and the drag dies on the first movement. On the
    // window they arrive wherever the finger goes. They are bound only for the
    // duration of a drag, so nothing leaks when the drawing is rebuilt.
    function onMove(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      e.preventDefault();
      node.setAttribute("transform",
        "translate("+((e.clientX-sx)*scale)+","+((e.clientY-sy)*scale)+")");
    }
    function unbind(){
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", onUp);
      window.removeEventListener("pointercancel", onCancel);
    }
    function release(){
      dragging = false; pid = null; unbind();
      node.classList.remove("is-dragging");
      svg.classList.remove("is-carrying");
    }
    function onUp(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      var dx = (e.clientX - sx) * scale, dy = (e.clientY - sy) * scale;
      release();
      fitState.cx = ox + dx;
      fitState.cy = oy + dy;
      render();
    }
    function onCancel(e){
      if (!dragging || (pid !== null && e.pointerId !== pid)) return;
      release();
      node.removeAttribute("transform");
    }

    node.addEventListener("pointerdown", function(e){
      if (dragging) return;                 // a second finger must not hijack it
      dragging = true; pid = e.pointerId;
      sx = e.clientX; sy = e.clientY;
      ox = fitState.cx; oy = fitState.cy;
      var r = svg.getBoundingClientRect();
      scale = r.width ? built.sceneW / r.width : 1;
      node.classList.add("is-dragging");
      svg.classList.add("is-carrying");
      // Kept as a belt-and-braces measure where it does work; the window
      // listeners are what the drag actually depends on.
      if (node.setPointerCapture) { try { node.setPointerCapture(e.pointerId); } catch(err){} }
      window.addEventListener("pointermove", onMove, { passive:false });
      window.addEventListener("pointerup", onUp);
      window.addEventListener("pointercancel", onCancel);
      e.preventDefault();
    });
    node.addEventListener("keydown", function(e){
      var step = 8;
      if (e.key === "r" || e.key === "R"){ turnThing(); e.preventDefault(); }
      else if (e.key === "ArrowLeft"){ fitState.cx -= step; render(); e.preventDefault(); }
      else if (e.key === "ArrowRight"){ fitState.cx += step; render(); e.preventDefault(); }
      else if (e.key === "ArrowUp"){ fitState.cy -= step; render(); e.preventDefault(); }
      else if (e.key === "ArrowDown"){ fitState.cy += step; render(); e.preventDefault(); }
    });
  }

  function render(){
    if (hBox)  hBox.hidden  = (view !== "onyou");
    if (tray)  tray.hidden  = (view !== "fits");

    if (view === "onyou"){
      if (hint) hint.classList.remove("is-no");
      var b = buildOnYou(bag, heightCm, carryKey, BUILDS[buildIx].k);
      // The scene is already a fixed size in millimetres, so the panel simply
      // maps onto it. The frame never moves; only the figure inside it grows.
      var ppm = panelCap() / b.sceneH;
      attr(b.svg, { width:(b.sceneW*ppm)+"px", height:(b.sceneH*ppm)+"px" });
      show(b.svg); setHint(b.summary); scroll.scrollLeft = 0;
      readable(b.svg, b.sceneW);
      wireDrag(b);
      return;
    }
    if (view === "fits"){
      // Measured, not guessed from the viewport: the module can sit in a narrow
      // column on a wide screen just as easily as on a phone.
      var f = buildFits(bag, fitState, (scroll ? scroll.clientWidth : 0) < 560);
      show(f.svg); setHint(f.summary); scroll.scrollLeft = 0;
      readable(f.svg, f.sceneW);
      if (hint) hint.classList.toggle("is-no", !!(f.verdict && !f.verdict.ok));
      buildTray();
      wireThing(f);
    }
  }

  for (var i=0;i<tabs.length;i++){
    (function(tab){
      tab.addEventListener("click", function(){
        for (var k=0;k<tabs.length;k++){
          tabs[k].classList.toggle("is-on", tabs[k] === tab);
          tabs[k].setAttribute("aria-selected", tabs[k] === tab ? "true" : "false");
        }
        view = tab.getAttribute("data-view") || "onyou";
        render();
      });
    })(tabs[i]);
  }

  if (hIn) hIn.addEventListener("input", function(){
    heightCm = num(hIn.value) || 155;
    if (hOut) hOut.textContent = heightCm + " cm";
    store(K_HT, heightCm);          // remembered, so she sets it once for the shop
    if (view === "onyou") render();
  });

  if (bIn) bIn.addEventListener("input", function(){
    var v = Math.round(num(bIn.value));
    buildIx = (v >= 0 && v < BUILDS.length) ? v : 1;
    if (bOut) bOut.textContent = BUILDS[buildIx].label;
    store(K_BUILD, buildIx + 1);
    if (view === "onyou") render();
  });






  // The modal renders this while it is still hidden, where every measurement
  // comes back as zero. It re-runs this the moment the window opens.
  root.ktRender = function(){ render(); };

  render();
}

/* ------------------------- the "See bag size" window --------------------- */
function bootLauncher(launch){
  var openBtn = launch.querySelector("[data-kt-open]");
  var modal   = launch.querySelector("[data-kt-modal]");
  var panel   = launch.querySelector(".kt-sv-modal__panel");
  var module  = launch.querySelector("[data-kt-sv]");
  if (!openBtn || !modal) return;

  /* ⭐ Move the WINDOW to <body> before anything else.
     Flatsome's gallery is a Flickity slider, and Flickity puts a transform on
     it. A transformed ancestor becomes the containing block for position:fixed
     descendants — so once the launcher was lifted into the gallery, the window
     stopped being fixed to the viewport, got clipped to the photo, and the rest
     of the page painted straight over it. Only the BUTTON belongs in the
     gallery; the dialog belongs at the top of the document, like every modal. */
  if (modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  // Lift the button onto the product photo. Done here rather than in PHP so it
  // works whatever the theme calls its gallery, and so a theme we do not
  // recognise simply leaves the button sitting under the photo instead.
  if (launch.getAttribute("data-corner")) {
    // Order matters. ".product-gallery" is Flatsome's whole COLUMN — image plus
    // the thumbnail strip below it — so anchoring there dropped the button to
    // the bottom of the column instead of onto the photo. The element the
    // theme's own New/Sale badges are positioned against is
    // ".woocommerce-product-gallery", which measures exactly the image.
    var gal = document.querySelector(".woocommerce-product-gallery")
           || document.querySelector(".product-gallery-slider")
           || document.querySelector(".product-images")
           || document.querySelector(".product-gallery");
    if (gal) {
      var pos = window.getComputedStyle(gal).position;
      if (pos === "static") gal.style.position = "relative";
      gal.appendChild(launch);
      launch.classList.add("is-over-gallery");
    }
  }
  // Only now is it in its final place and safe to show. Until this runs it is
  // hidden, so it can never be seen mid-jump.
  launch.classList.add("is-placed");

  var lastFocus = null, savedY = 0;

  function open(){
    lastFocus = document.activeElement;
    modal.hidden = false;
    // Pin the page. body{overflow:hidden} does not stop a phone scrolling the
    // page behind the window; fixing the body and restoring the offset does.
    savedY = window.pageYOffset || document.documentElement.scrollTop || 0;
    document.body.style.top = (-savedY) + "px";
    document.body.classList.add("kt-sv-locked");
    document.documentElement.classList.add("kt-sv-locked");
    // Now that it has real dimensions, draw it properly.
    if (module && module.ktRender) module.ktRender();
    var btn = modal.querySelector(".kt-sv-modal__close");
    if (btn && btn.focus) btn.focus();
  }

  window.addEventListener("resize", function(){
    if (!modal.hidden && module && module.ktRender) module.ktRender();
  });
  function close(){
    modal.hidden = true;
    document.body.classList.remove("kt-sv-locked");
    document.documentElement.classList.remove("kt-sv-locked");
    document.body.style.top = "";
    window.scrollTo(0, savedY);
    // Fall back to the button: if the window was opened from script rather than
    // a click, the remembered element is the body and focus would be lost.
    var back = (lastFocus && lastFocus !== document.body && lastFocus.focus) ? lastFocus : openBtn;
    if (back && back.focus) back.focus();
  }

  openBtn.addEventListener("click", open);
  var closers = modal.querySelectorAll("[data-kt-close]");
  for (var i=0;i<closers.length;i++) closers[i].addEventListener("click", close);

  // Pinning the body stops the page moving, but a wheel event over the window
  // still travels to whatever is behind it. Swallow anything that is not over a
  // part of the window that can actually scroll.
  modal.addEventListener("wheel", function(e){
    var n = e.target;
    while (n && n !== modal) {
      if (n.scrollHeight > n.clientHeight + 1) {
        var atTop = n.scrollTop <= 0, atEnd = n.scrollTop + n.clientHeight >= n.scrollHeight - 1;
        if (!((e.deltaY < 0 && atTop) || (e.deltaY > 0 && atEnd))) return;
      }
      n = n.parentElement;
    }
    e.preventDefault();
  }, { passive: false });

  modal.addEventListener("touchmove", function(e){
    if (e.target === modal || e.target.classList.contains("kt-sv-modal__scrim")) e.preventDefault();
  }, { passive: false });

  document.addEventListener("keydown", function(e){
    if (modal.hidden) return;
    if (e.key === "Escape"){ close(); return; }
    if (e.key !== "Tab" || !panel) return;
    // Keep tabbing inside the window while it is open.
    var f = panel.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    var first = f[0], last = f[f.length-1];
    if (e.shiftKey && document.activeElement === first){ last.focus(); e.preventDefault(); }
    else if (!e.shiftKey && document.activeElement === last){ first.focus(); e.preventDefault(); }
  });
}

function init(){
  var nodes = document.querySelectorAll("[data-kt-sv]");
  for (var i=0;i<nodes.length;i++) boot(nodes[i]);
  var launches = document.querySelectorAll("[data-kt-launch]");
  for (var j=0;j<launches.length;j++) bootLauncher(launches[j]);
}
if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
else init();
})();
JS;
	}
}

Kohthai_Size_View::init();
