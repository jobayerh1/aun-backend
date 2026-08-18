<?php
/**
 * [aun_app_callout] — a compact AUN Care app callout for existing pages.
 *
 * Six variants with DELIBERATELY DIFFERENT copy. Repeating one identical block
 * across 15 pages is treated as boilerplate and largely discounted by search
 * engines, so each variant speaks to the page it sits on and carries its own
 * keywords. Every variant links to /aun-care-app/ (never straight to the APK —
 * see the link-architecture note in the page package).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aun_app_callout_copy() {
	return array(

		'repair' => array(
			'icon'   => 'fa-solid fa-screwdriver-wrench',
			'head'   => 'Track your repair from your phone',
			'body'   => 'The free <strong>AUN Care</strong> app shows your repair moving from received, to repaired, to on its way back &mdash; with courier tracking and a notification at each step, so you never have to call and ask where it is.',
			'cta'    => 'Get the AUN Care app',
			'inline' => 'You can also follow this repair step by step, with a notification at each stage, in the free %s.',
		),

		'parts' => array(
			'icon'   => 'fa-solid fa-microchip',
			'head'   => 'Order and track spare parts in the app',
			'body'   => 'Request a part, get the price, approve it, then follow each item to your door &mdash; all inside <strong>AUN Care</strong>. Parts covered by warranty stay free, and nothing is ordered until you approve the cost.',
			'cta'    => 'Get the AUN Care app',
			'inline' => 'You can also request parts, approve the price and track each item to your door in the free %s.',
		),

		'warranty' => array(
			'icon'   => 'fa-solid fa-shield-halved',
			'head'   => 'Your warranty, always in your pocket',
			'body'   => 'Scan the barcode on the box once and the free <strong>AUN Care</strong> app shows whether you are still covered and exactly how many days are left &mdash; no hunting for the invoice a year later.',
			'cta'    => 'Get the AUN Care app',
			'inline' => 'You can also check your warranty and days remaining any time in the free %s.',
		),

		'support' => array(
			'icon'   => 'fa-solid fa-headset',
			'head'   => 'Ask us inside the app',
			'body'   => 'Open a support ticket in <strong>AUN Care</strong>, attach a photo of the problem, and get an answer from our own team in one thread &mdash; no phone calls, and nothing gets forgotten.',
			'cta'    => 'Get the AUN Care app',
			'inline' => 'You can also ask us inside the free %s and keep the whole conversation in one thread.',
		),

		'choose' => array(
			'icon'   => 'fa-solid fa-ruler-combined',
			'head'   => 'Not sure what fits your room?',
			'body'   => 'You do not need to own a projector yet. <strong>AUN Care</strong>&rsquo;s Projector Planner shows what screen size fits your room and how far back the projector must sit. Answer five questions in &ldquo;Help me choose&rdquo; for a recommendation with reasons &mdash; or preview the picture on your own wall in AR.',
			'cta'    => 'Try it in the app',
			'inline' => 'Planning on your phone? The free %s shows what screen size fits your room, and previews the picture on your own wall in AR.',
		),

		'owner' => array(
			'icon'   => 'fa-brands fa-android',
			'head'   => 'After you buy, we are still here',
			'body'   => 'Every AUN projector is backed by the free <strong>AUN Care</strong> app &mdash; register your warranty by scanning the box, order genuine spare parts, book a repair and follow it, and get firmware, manuals and guides made for your exact model.',
			'cta'    => 'See what the app does',
			'inline' => 'Every AUN projector is backed by the free %s &mdash; warranty, spare parts, repairs and guides for your exact model.',
		),
	);
}

/**
 * [aun_app_callout type="repair" theme="light" style="box"]
 *
 *  type   repair | parts | warranty | support | choose | owner
 *  theme  light (default, for white/#f6f8fb sections) | dark (for rgb(9,15,28) bands)
 *  style  box (default) | inline
 *
 * `inline` renders ONE sentence with a link, no card. Use it wherever the app is a
 * useful aside rather than the answer to the page — category and product pages.
 * A boxed promo repeated on every page becomes banner-blind wallpaper and competes
 * with the page's own call to action; a sentence in the flow reads as help.
 */
function aun_app_callout_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'type'  => 'owner',
		'theme' => 'light',
		'style' => 'box',
	), $atts, 'aun_app_callout' );

	$copy = aun_app_callout_copy();
	$k    = isset( $copy[ $a['type'] ] ) ? $a['type'] : 'owner';
	$c    = $copy[ $k ];
	$dark = ( 'dark' === $a['theme'] );

	wp_enqueue_style( 'aun-app-callout' );

	$url = 'https://aun-projector.com.bd/aun-care-app/';

	if ( 'inline' === $a['style'] ) {
		$link = '<a href="' . esc_url( $url ) . '">AUN Care app</a>';
		return '<p class="aun-cta-line' . ( $dark ? ' aun-cta-line--dark' : '' ) . '">'
			. '<i class="fa-brands fa-android" aria-hidden="true"></i> '
			. sprintf( wp_kses_post( $c['inline'] ), $link )
			. '</p>';
	}

	ob_start();
	?>
<div class="aun-cta<?php echo $dark ? ' aun-cta--dark' : ''; ?>">
  <div class="aun-cta-ic"><i class="<?php echo esc_attr( $c['icon'] ); ?>"></i></div>
  <div class="aun-cta-tx">
    <h3><?php echo esc_html( $c['head'] ); ?></h3>
    <p><?php echo wp_kses_post( $c['body'] ); ?></p>
  </div>
  <div class="aun-cta-go">
    <a href="<?php echo esc_url( $url ); ?>"><i class="fa-solid fa-circle-down"></i> <?php echo esc_html( $c['cta'] ); ?></a>
  </div>
</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'aun_app_callout', 'aun_app_callout_shortcode' );

function aun_app_callout_register() {
	wp_register_style(
		'aun-app-callout',
		plugin_dir_url( __FILE__ ) . 'assets/callout.css',
		array(),
		AUN_CARE_PROMO_VER
	);
}
add_action( 'wp_enqueue_scripts', 'aun_app_callout_register' );
