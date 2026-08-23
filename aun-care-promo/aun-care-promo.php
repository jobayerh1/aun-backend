<?php
/**
 * Plugin Name: AUN Care App — Promo & Callouts
 * Description: Settings at Settings -> AUN Care App. Puts the AUN Care app on the website. [aun_care_promo] runs the promo as a live, responsive HTML animation (not a video). [aun_app_callout type="…" style="box|inline"] drops a page-appropriate app mention into any existing page. Also adds a slim, dismissible Android-only install bar.
 * Version: 1.4.0
 * Author: AUN Projector Bangladesh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AUN_CARE_PROMO_VER', '1.4.0' );

require_once plugin_dir_path( __FILE__ ) . 'settings.php';
require_once plugin_dir_path( __FILE__ ) . 'callout.php';
require_once plugin_dir_path( __FILE__ ) . 'banner.php';

/**
 * [aun_care_promo]
 *
 *  ratio   auto | 1:1 | 9:16 | 16:9    default auto
 *          auto = 16:9 on desktop, 1:1 on tablet, 9:16 on phone.
 *  mood    night | day | auto          default night
 *          auto = follows the visitor's OS light/dark setting.
 *  lang    en | bn | auto              default auto (site locale)
 *  max     max width in px for 16:9    default 1040
 *  caption yes | no                    default no  (the small line under the frame)
 */
function aun_care_promo_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'ratio'   => 'auto',
		'mood'    => 'night',
		'lang'    => 'auto',
		'max'     => '1040',
		'caption' => 'no',
	), $atts, 'aun_care_promo' );

	wp_enqueue_style( 'aun-care-promo' );
	wp_enqueue_script( 'aun-care-promo' );

	$base = plugin_dir_url( __FILE__ ) . 'assets/';

	$lang = $a['lang'];
	if ( 'auto' === $lang ) {
		$lang = ( 0 === strpos( get_locale(), 'bn' ) ) ? 'bn' : 'en';
	}
	$lang = in_array( $lang, array( 'en', 'bn' ), true ) ? $lang : 'en';

	$ratio = in_array( $a['ratio'], array( 'auto', '1:1', '9:16', '16:9' ), true ) ? $a['ratio'] : 'auto';
	$mood  = in_array( $a['mood'], array( 'night', 'day', 'auto' ), true ) ? $a['mood'] : 'night';
	$max   = (int) $a['max'];
	if ( $max < 320 || $max > 1920 ) $max = 1040;

	// data-ar is set to a real value immediately so there is no layout shift before JS runs.
	$initial_ar = ( 'auto' === $ratio ) ? '16:9' : $ratio;

	ob_start();
	?>
<div class="aun-promo-wrap" style="--aun-promo-max:<?php echo esc_attr( $max ); ?>px">
  <div class="aun-promo-stage"
       data-ar="<?php echo esc_attr( $initial_ar ); ?>"
       data-ar-mode="<?php echo esc_attr( $ratio ); ?>"
       data-mood="<?php echo esc_attr( $mood ); ?>"
       data-lang="<?php echo esc_attr( $lang ); ?>"
       role="img"
       aria-label="<?php esc_attr_e( 'A short animation showing the AUN Care app: live repair and parts tracking, warranty, model guides and in-app support.', 'aun-care-promo' ); ?>">

    <div class="aun-promo-beam"></div>
    <div class="aun-promo-spill"></div>
    <div class="aun-promo-motes"></div>

    <div class="aun-promo-phone">
      <div class="aun-promo-btn-pwr"></div><div class="aun-promo-btn-vol"></div>
      <div class="aun-promo-screen">

        <div class="aun-promo-view" data-view="splash">
          <div class="aun-promo-full"><img src="<?php echo esc_url( $base . 'splash.webp' ); ?>" alt="" loading="lazy" decoding="async"></div>
          <div class="aun-promo-spin"></div>
        </div>

        <div class="aun-promo-view" data-view="home">
          <div class="aun-promo-port"><div class="aun-promo-track"><img src="<?php echo esc_url( $base . 'home.webp' ); ?>" alt="" loading="lazy" decoding="async"></div></div>
          <img class="aun-promo-chrome-top" src="<?php echo esc_url( $base . 'home-top.webp' ); ?>" alt="" loading="lazy" decoding="async">
          <img class="aun-promo-chrome-nav" src="<?php echo esc_url( $base . 'home-nav.webp' ); ?>" alt="" loading="lazy" decoding="async">
        </div>

        <div class="aun-promo-view" data-view="devices">
          <div class="aun-promo-port"><div class="aun-promo-track fit"><img src="<?php echo esc_url( $base . 'devices.webp' ); ?>" alt="" loading="lazy" decoding="async"></div></div>
          <img class="aun-promo-chrome-top" src="<?php echo esc_url( $base . 'devices-top.webp' ); ?>" alt="" loading="lazy" decoding="async">
          <img class="aun-promo-chrome-nav" src="<?php echo esc_url( $base . 'devices-nav.webp' ); ?>" alt="" loading="lazy" decoding="async">
        </div>

        <div class="aun-promo-view" data-view="detail">
          <div class="aun-promo-port">
            <div class="aun-promo-track fit">
              <img src="<?php echo esc_url( $base . 'detail-firmware.webp' ); ?>" alt="" loading="lazy" decoding="async">
              <img class="aun-promo-layer2" src="<?php echo esc_url( $base . 'detail-help.webp' ); ?>" alt="" loading="lazy" decoding="async">
              <div class="aun-promo-tap"></div>
            </div>
          </div>
          <img class="aun-promo-chrome-top" src="<?php echo esc_url( $base . 'detail-top.webp' ); ?>" alt="" loading="lazy" decoding="async">
        </div>

        <div class="aun-promo-view" data-view="support">
          <div class="aun-promo-port"><div class="aun-promo-track"><img src="<?php echo esc_url( $base . 'support.webp' ); ?>" alt="" loading="lazy" decoding="async"></div></div>
          <img class="aun-promo-chrome-top" src="<?php echo esc_url( $base . 'support-top.webp' ); ?>" alt="" loading="lazy" decoding="async">
          <img class="aun-promo-chrome-nav" src="<?php echo esc_url( $base . 'support-nav.webp' ); ?>" alt="" loading="lazy" decoding="async">
        </div>

        <div class="aun-promo-gloss"></div>
        <div class="aun-promo-camera"></div>
      </div>
    </div>

    <div class="aun-promo-caption">
      <p data-c="0"></p><p data-c="1"></p><p data-c="2"></p><p data-c="3"></p>
      <p data-c="4"></p><p data-c="5"></p><p data-c="6"></p>
    </div>

    <div class="aun-promo-grain"></div>
    <div class="aun-promo-vig"></div>
  </div>

  <?php if ( 'yes' === $a['caption'] ) : ?>
  <p class="aun-promo-note"><?php esc_html_e( 'A 20-second loop built from real app screens.', 'aun-care-promo' ); ?></p>
  <?php endif; ?>
</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'aun_care_promo', 'aun_care_promo_shortcode' );

/** Register assets. Registered always, enqueued only by the shortcode. */
function aun_care_promo_register() {
	wp_register_style(
		'aun-care-promo',
		plugin_dir_url( __FILE__ ) . 'assets/promo.css',
		array(),
		AUN_CARE_PROMO_VER
	);
	wp_register_script(
		'aun-care-promo',
		plugin_dir_url( __FILE__ ) . 'assets/promo.js',
		array(),
		AUN_CARE_PROMO_VER,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'aun_care_promo_register' );

/**
 * WP Rocket / optimiser guards.
 * The animation measures real element heights on load; deferring or lazy-rendering
 * it produces a mis-sized scroll port, so keep this file out of JS optimisation.
 */
add_filter( 'rocket_exclude_defer_js', function ( $excluded ) {
	$excluded[] = 'aun-care-promo/assets/promo.js';
	return $excluded;
} );
add_filter( 'rocket_exclude_js', function ( $excluded ) {
	$excluded[] = 'aun-care-promo/assets/promo.js';
	return $excluded;
} );
add_filter( 'rocket_delay_js_exclusions', function ( $excluded ) {
	$excluded[] = 'aun-care-promo/assets/promo';
	return $excluded;
} );
