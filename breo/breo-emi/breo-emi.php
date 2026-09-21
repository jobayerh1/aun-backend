<?php
/**
 * Plugin Name: Breo EMI Plans
 * Description: Shows credit-card EMI plans on Breo product pages ("0% EMI from ৳X/month · View plans" + a bank-by-bank pop-up) and an EMI calculator page [breo_emi_calculator]. Banks, months and interest are edited in Settings → EMI Plans.
 * Version: 1.0.0
 * Author: Breo Bangladesh
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 *
 * Rebuilt for Breo from the AUN EMI Table's behaviour: per bank, each tenure has an
 * interest rate; total = price × (1 + rate), monthly = total ÷ months (whole taka).
 * Default bank list = the 33 banks / rates published on aun-projector.com.bd.
 * The plans are computed in the browser from a small JSON config, so product pages
 * stay light and nothing here needs a nonce (it is read-only).
 */

defined( 'ABSPATH' ) || exit;

define( 'BREO_EMI_VERSION', '1.0.0' );
define( 'BREO_EMI_URL', plugin_dir_url( __FILE__ ) );

class Breo_EMI {

	const OPTION = 'breo_emi';

	public static function defaults() {
		$std  = '3:0, 6:0, 9:6.5, 12:8.5';
		$banks = array(
			'AB Bank PLC', 'Al-Arafah Islami Bank PLC', 'Bank Asia PLC', 'Citizens Bank PLC', 'Community Bank Bangladesh PLC',
			'Dhaka Bank PLC', 'Dutch Bangla Bank PLC', 'Eastern Bank PLC', 'EXIM Bank PLC', 'Islami Bank Bangladesh PLC',
			'Jamuna Bank PLC', 'LankaBangla Finance PLC', 'Mercantile Bank PLC', 'Meghna Bank PLC', 'Modhumoti Bank PLC',
			'Midland Bank PLC', 'Mutual Trust Bank PLC', 'NCC Bank PLC', 'NRB Bank PLC', 'NRBC Bank PLC', 'One Bank PLC',
			'Premier Bank PLC', 'Prime Bank PLC', 'Pubali Bank PLC', 'SBAC Bank PLC', 'Shahjalal Islami Bank PLC',
			'Shimanto Bank PLC', 'Southeast Bank PLC', 'Standard Bank PLC', 'Standard Chartered Bank', 'Trust Bank PLC',
			'The City Bank PLC', 'United Commercial Bank PLC',
		);
		$lines = array();
		foreach ( $banks as $b ) {
			$lines[] = $b . ' | ' . ( 'Standard Chartered Bank' === $b ? '3:0, 6:0, 9:8, 12:10.5' : $std );
		}
		return array(
			'enabled'   => 'no',
			'min_price' => 5000,
			'banks'     => implode( "\n", $lines ),
			'note'      => 'EMI is available on credit cards from the banks listed when you pay online at checkout. Interest is set by each bank and can change; your bank may also charge a processing fee.',
		);
	}

	public static function opts() {
		$o = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
	}

	public static function enabled() {
		return 'yes' === self::opts()['enabled'];
	}

	/** "Bank | 3:0, 6:0, 9:6.5" lines → [ [name, slug, [[months, rate], …]], … ] */
	public static function banks() {
		static $out = null;
		if ( null !== $out ) {
			return $out;
		}
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::opts()['banks'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( count( $parts ) < 2 || '' === $parts[0] ) {
				continue;
			}
			$plans = array();
			foreach ( explode( ',', $parts[1] ) as $p ) {
				if ( preg_match( '/^\s*(\d{1,2})\s*:\s*([\d.]+)\s*$/', $p, $m ) && (int) $m[1] > 0 ) {
					$plans[] = array( (int) $m[1], (float) $m[2] );
				}
			}
			if ( $plans ) {
				usort( $plans, function ( $a, $b ) { return $a[0] - $b[0]; } );
				$out[] = array( 'name' => $parts[0], 'slug' => sanitize_title( $parts[0] ), 'plans' => $plans );
			}
		}
		return $out;
	}

	/** Lowest monthly amount across all banks/plans for a price: [monthly, months, rate]. */
	public static function best( $price ) {
		$best = null;
		foreach ( self::banks() as $b ) {
			foreach ( $b['plans'] as $p ) {
				$total   = round( $price * ( 1 + $p[1] / 100 ) );
				$monthly = round( $total / $p[0] );
				if ( null === $best || $monthly < $best[0] ) {
					$best = array( $monthly, $p[0], $p[1] );
				}
			}
		}
		return $best;
	}

	/** Summary cards for the Breo EMI page ("at a glance"). */
	public static function glance() {
		$banks = self::banks();
		$zero  = 0;
		$max   = 0;
		foreach ( $banks as $b ) {
			foreach ( $b['plans'] as $p ) {
				$max = max( $max, $p[0] );
				if ( 0.0 === (float) $p[1] ) {
					$zero = max( $zero, $p[0] );
				}
			}
		}
		return array(
			array( 'badge', count( $banks ) . ' banks', 'credit cards accepted' ),
			array( 'cash', $zero ? '0% interest' : '', 'on plans up to ' . $zero . ' months' ),
			array( 'clock', $max ? 'Up to ' . $max . ' months' : '', 'to spread the cost' ),
			array( 'shield', 'Pay online', 'choose EMI at checkout' ),
		);
	}

	/* ------------------------------------------------------------------ boot */

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'links' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'breo_emi_calculator', array( __CLASS__, 'calculator' ) );

		// Breo product page (buy panel) and, on any other theme, the standard summary.
		add_action( 'breo_bd_buy_after_price', array( __CLASS__, 'product_trigger' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_trigger' ), 11 );
	}

	public static function activate() {
		if ( get_page_by_path( 'emi-plans' ) ) {
			return;
		}
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'EMI Plans',
			'post_name'    => 'emi-plans',
			'post_excerpt' => 'Spread the cost of your Breo over easy monthly instalments with your credit card.',
			'post_content' => "<!-- wp:shortcode -->\n[breo_emi_calculator]\n<!-- /wp:shortcode -->",
		) );
	}

	/* ------------------------------------------------------------------ front end */

	public static function assets() {
		wp_register_style( 'breo-emi', BREO_EMI_URL . 'assets/breo-emi.css', array(), BREO_EMI_VERSION );
		wp_register_script( 'breo-emi', BREO_EMI_URL . 'assets/breo-emi.js', array(), BREO_EMI_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( ! self::enabled() ) {
			return;
		}
		$post = get_post();
		if ( ( function_exists( 'is_product' ) && is_product() ) || ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'breo_emi_calculator' ) ) ) {
			wp_enqueue_style( 'breo-emi' );
			wp_enqueue_script( 'breo-emi' );
		}
	}

	private static function config( $price ) {
		return array(
			'price'  => (float) $price,
			'symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
			'banks'  => array_map( function ( $b ) {
				return array( $b['name'], $b['slug'], $b['plans'] );
			}, self::banks() ),
		);
	}

	private static function money( $n ) {
		return html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) . number_format_i18n( $n );
	}

	public static function product_trigger( $product = null ) {
		static $done = array();
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! $product instanceof WC_Product ) {
			global $product;
		}
		if ( ! $product instanceof WC_Product || isset( $done[ $product->get_id() ] ) ) {
			return;
		}
		$price = (float) wc_get_price_to_display( $product );
		if ( $price <= 0 || $price < (float) self::opts()['min_price'] || ! $product->is_purchasable() ) {
			return;
		}
		$best = self::best( $price );
		if ( ! $best ) {
			return;
		}
		$done[ $product->get_id() ] = true;
		wp_enqueue_style( 'breo-emi' );
		wp_enqueue_script( 'breo-emi' );

		$uid   = 'breo-emi-' . $product->get_id();
		$lead  = ( 0.0 === (float) $best[2] ) ? sprintf( '0%% EMI from %s/month', self::money( $best[0] ) ) : sprintf( 'EMI from %s/month', self::money( $best[0] ) );
		?>
		<div class="breo-emi-line">
			<span class="breo-emi-line__ico" aria-hidden="true"><?php echo self::card_icon(); // phpcs:ignore ?></span>
			<span class="breo-emi-line__txt"><strong><?php echo esc_html( $lead ); ?></strong> <span><?php esc_html_e( 'with your credit card', 'breo-emi' ); ?></span></span>
			<button type="button" class="breo-emi-line__btn" data-breo-emi-open="<?php echo esc_attr( $uid ); ?>" aria-haspopup="dialog"><?php esc_html_e( 'View plans', 'breo-emi' ); ?></button>
		</div>
		<div class="breo-emi-overlay" id="<?php echo esc_attr( $uid ); ?>" hidden>
			<div class="breo-emi-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-t">
				<div class="breo-emi-modal__hd">
					<div>
						<p class="breo-emi-modal__eyebrow"><?php echo esc_html( $product->get_name() ); ?></p>
						<h3 class="breo-emi-modal__title" id="<?php echo esc_attr( $uid ); ?>-t"><?php esc_html_e( 'EMI plans', 'breo-emi' ); ?> · <?php echo esc_html( self::money( $price ) ); ?></h3>
					</div>
					<button type="button" class="breo-emi-close" data-breo-emi-close aria-label="<?php esc_attr_e( 'Close', 'breo-emi' ); ?>">&times;</button>
				</div>
				<?php echo self::board( $price, $uid ); // phpcs:ignore ?>
			</div>
		</div>
		<?php
	}

	/** Bank list + plan table; filled by breo-emi.js from the JSON config. */
	private static function board( $price, $uid ) {
		$note = trim( (string) self::opts()['note'] );
		return '<div class="breo-emi-board" data-breo-emi="' . esc_attr( wp_json_encode( self::config( $price ) ) ) . '">'
			. '<div class="breo-emi-banks"><label class="breo-emi-search"><span class="screen-reader-text">' . esc_html__( 'Find your bank', 'breo-emi' ) . '</span>'
			. '<input type="search" placeholder="' . esc_attr__( 'Find your bank', 'breo-emi' ) . '" data-breo-emi-find></label>'
			. '<ul class="breo-emi-banklist" role="listbox" aria-label="' . esc_attr__( 'Banks', 'breo-emi' ) . '"></ul></div>'
			. '<div class="breo-emi-plans"><div class="breo-emi-plans__hd"><strong data-breo-emi-bank></strong><span data-breo-emi-price></span></div>'
			. '<div class="breo-emi-tablewrap"><table class="breo-emi-table"><thead><tr><th scope="col">' . esc_html__( 'Months', 'breo-emi' ) . '</th><th scope="col">' . esc_html__( 'Interest', 'breo-emi' ) . '</th><th scope="col">' . esc_html__( 'Per month', 'breo-emi' ) . '</th><th scope="col">' . esc_html__( 'Total', 'breo-emi' ) . '</th></tr></thead><tbody></tbody></table></div>'
			. ( $note ? '<p class="breo-emi-note">' . esc_html( $note ) . '</p>' : '' )
			. '</div></div>';
	}

	/** [breo_emi_calculator amount="15000"] — amount input + the same board. */
	public static function calculator( $atts ) {
		if ( ! self::enabled() ) {
			return current_user_can( 'manage_options' ) ? '<p><em>EMI Plans is switched off. Turn it on in Settings → EMI Plans.</em></p>' : '';
		}
		$atts = shortcode_atts( array( 'amount' => '' ), $atts );
		$amt  = (float) preg_replace( '/[^\d.]/', '', (string) $atts['amount'] );
		if ( $amt <= 0 ) {
			$amt = 10000;
		}
		wp_enqueue_style( 'breo-emi' );
		wp_enqueue_script( 'breo-emi' );
		$min = (float) self::opts()['min_price'];
		return '<div class="breo-emi-calc">'
			. '<label class="breo-emi-calc__lbl" for="breo-emi-amt">' . esc_html__( 'Product price', 'breo-emi' ) . '</label>'
			. '<div class="breo-emi-calc__row"><span class="breo-emi-calc__sym">' . esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ) . '</span>'
			. '<input id="breo-emi-amt" type="number" inputmode="numeric" min="0" step="100" value="' . esc_attr( (string) $amt ) . '" data-breo-emi-amount></div>'
			. ( $min > 0 ? '<p class="breo-emi-calc__hint">' . esc_html( sprintf( __( 'EMI is available on orders from %s.', 'breo-emi' ), self::money( $min ) ) ) . '</p>' : '' )
			. self::board( $amt, 'breo-emi-calc' )
			. '</div>';
	}

	private static function card_icon() {
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/></svg>';
	}

	/* ------------------------------------------------------------------ admin */

	public static function menu() {
		add_options_page( 'EMI Plans', 'EMI Plans', 'manage_woocommerce', 'breo-emi', array( __CLASS__, 'page' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=breo-emi' ) ) . '">Settings</a>' );
		return $links;
	}

	public static function register() {
		register_setting( 'breo_emi_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$d = self::defaults();
		$in = is_array( $in ) ? $in : array();
		$out = array(
			'enabled'   => ( isset( $in['enabled'] ) && 'yes' === $in['enabled'] ) ? 'yes' : 'no',
			'min_price' => max( 0, (int) ( isset( $in['min_price'] ) ? $in['min_price'] : $d['min_price'] ) ),
			'banks'     => isset( $in['banks'] ) ? sanitize_textarea_field( $in['banks'] ) : $d['banks'],
			'note'      => isset( $in['note'] ) ? sanitize_textarea_field( $in['note'] ) : $d['note'],
		);
		if ( '' === trim( $out['banks'] ) ) {
			$out['banks'] = $d['banks'];
		}
		return $out;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o     = self::opts();
		$count = count( self::banks() );
		$ex    = self::best( 20000 );
		?>
		<div class="wrap">
			<h1>EMI Plans</h1>
			<p style="max-width:56em">Shows <strong>“0% EMI from ৳X/month · View plans”</strong> under the price on product pages, with a bank-by-bank pop-up, and powers the calculator on the <em>EMI Plans</em> page (shortcode <code>[breo_emi_calculator]</code>).</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'breo_emi_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Show EMI plans</th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="yes" <?php checked( $o['enabled'], 'yes' ); ?>> Customers can pay by credit-card EMI on this site</label>
						<p class="description">Only switch this on once EMI actually works at your checkout (for example SSLCommerz EMI), so the prices shown are real.</p></td></tr>
					<tr><th scope="row"><label for="breo-emi-min">Minimum price (৳)</label></th><td><input id="breo-emi-min" type="number" min="0" step="100" name="<?php echo esc_attr( self::OPTION ); ?>[min_price]" value="<?php echo esc_attr( $o['min_price'] ); ?>" class="small-text">
						<p class="description">Products cheaper than this show no EMI line (banks usually require a minimum, often ৳5,000).</p></td></tr>
					<tr><th scope="row"><label for="breo-emi-banks">Banks &amp; plans</label></th><td>
						<textarea id="breo-emi-banks" name="<?php echo esc_attr( self::OPTION ); ?>[banks]" rows="16" class="large-text code"><?php echo esc_textarea( $o['banks'] ); ?></textarea>
						<p class="description">One bank per line: <code>Bank name | months:interest%, months:interest%</code> — e.g. <code>City Bank PLC | 3:0, 6:0, 9:6.5, 12:8.5</code>. Currently <?php echo (int) $count; ?> banks.
						<?php if ( $ex ) : ?> Example: a ৳20,000 product shows “from ৳<?php echo esc_html( number_format_i18n( $ex[0] ) ); ?>/month” (<?php echo (int) $ex[1]; ?> months at <?php echo esc_html( $ex[2] ); ?>%).<?php endif; ?></p></td></tr>
					<tr><th scope="row"><label for="breo-emi-note">Note under the table</label></th><td><textarea id="breo-emi-note" name="<?php echo esc_attr( self::OPTION ); ?>[note]" rows="3" class="large-text"><?php echo esc_textarea( $o['note'] ); ?></textarea></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

Breo_EMI::init();
register_activation_hook( __FILE__, array( 'Breo_EMI', 'activate' ) );
