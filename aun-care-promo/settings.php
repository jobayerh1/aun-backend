<?php
/**
 * Settings for the app install bar — Settings → AUN Care App.
 *
 * The bar used to be controllable only through the `aun_app_banner_enabled` filter
 * (i.e. by editing code). Everything that used to be hard-coded in the banner
 * JavaScript now lives here instead, and is passed to the browser as plain numbers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const AUN_CARE_OPT = 'aun_care_promo_opts';

function aun_care_promo_defaults() {
	return array(
		'banner_enabled' => 1,   // master on/off for the install bar
		'min_views'      => 2,   // never interrupt the first page of a visit
		'delay'          => 6,   // seconds of dwell before it appears
		'scroll_pct'     => 25,  // or this far down the page, whichever comes first
		'mute_dismiss'   => 60,  // days of silence after the visitor closes it
		'mute_tap'       => 180, // days of silence after they tap "Get"
		'avoid_onetap'   => 1,   // hold back while a Google One Tap prompt is on screen
		'skip_products'  => 1,   // never compete with the sticky Add to cart bar
	);
}

function aun_care_promo_opts() {
	$o = get_option( AUN_CARE_OPT, array() );
	if ( ! is_array( $o ) ) $o = array();
	return array_merge( aun_care_promo_defaults(), $o );
}

function aun_care_promo_opt( $key ) {
	$o = aun_care_promo_opts();
	return isset( $o[ $key ] ) ? $o[ $key ] : null;
}

/** Whitelist + clamp. Anything not listed is discarded. */
function aun_care_promo_sanitize( $in ) {
	$d   = aun_care_promo_defaults();
	$out = array();

	foreach ( array( 'banner_enabled', 'avoid_onetap', 'skip_products' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	$ranges = array(
		'min_views'    => array( 1, 10 ),
		'delay'        => array( 0, 60 ),
		'scroll_pct'   => array( 0, 100 ),
		'mute_dismiss' => array( 1, 365 ),
		'mute_tap'     => array( 1, 365 ),
	);
	foreach ( $ranges as $k => $r ) {
		// intval, not absint: a negative should clamp to the minimum, not flip
		// sign into a plausible-looking value (-5 days must not become 5 days).
		$v         = isset( $in[ $k ] ) ? intval( $in[ $k ] ) : $d[ $k ];
		$out[ $k ] = max( $r[0], min( $r[1], $v ) );
	}
	return $out;
}

add_action( 'admin_init', function () {
	register_setting( 'aun_care_promo_group', AUN_CARE_OPT, array(
		'type'              => 'array',
		'sanitize_callback' => 'aun_care_promo_sanitize',
		'default'           => aun_care_promo_defaults(),
	) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'AUN Care App', 'AUN Care App', 'manage_options', 'aun-care-promo', 'aun_care_promo_settings_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( dirname( __FILE__ ) . '/aun-care-promo.php' ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=aun-care-promo' ) ) . '">Settings</a>' );
	return $links;
} );

function aun_care_promo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$o = aun_care_promo_opts();
	$n = function ( $k ) { return AUN_CARE_OPT . '[' . $k . ']'; };
	?>
	<div class="wrap">
		<h1>AUN Care App</h1>
		<p>Controls the slim <strong>&ldquo;AUN Care — Get&rdquo;</strong> install bar that slides up from the bottom
		   on Android phones. The <code>[aun_care_promo]</code> and <code>[aun_app_callout]</code> shortcodes are
		   unaffected by these settings.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'aun_care_promo_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Install bar</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $n( 'banner_enabled' ) ); ?>" value="1" <?php checked( $o['banner_enabled'], 1 ); ?>>
							Show the app download bar
						</label>
						<p class="description">Turn this off to hide it site-wide without deactivating the plugin.
						   It already never appears on cart, checkout, my-account, single product pages or the app&rsquo;s own pages,
						   and only on Android phones.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Google One Tap</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $n( 'avoid_onetap' ) ); ?>" value="1" <?php checked( $o['avoid_onetap'], 1 ); ?>>
							Wait while a Google sign-in prompt is on screen
						</label>
						<p class="description"><strong>Recommended.</strong> On phones the Google One Tap prompt also
						   docks to the bottom of the screen, so the two can land on top of each other. With this on,
						   the install bar simply waits until the sign-in prompt is gone.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Product pages</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $n( 'skip_products' ) ); ?>" value="1" <?php checked( $o['skip_products'], 1 ); ?>>
							Stay off single product pages
						</label>
						<p class="description"><strong>Recommended.</strong> Flatsome&rsquo;s sticky
						   <em>Add to cart</em> bar docks to the bottom of the screen on phones &mdash; the same
						   place this bar lives &mdash; and on a product page the buy button has to win.
						   The visit still counts, so the bar just appears on the next page they open
						   instead of being used up here.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">When it appears</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Page views first</th>
					<td>
						<input type="number" min="1" max="10" class="small-text" name="<?php echo esc_attr( $n( 'min_views' ) ); ?>" value="<?php echo esc_attr( (int) $o['min_views'] ); ?>">
						<p class="description">Pages the visitor must view in a session before the bar can show. <code>2</code> means never on the first page.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Dwell time</th>
					<td>
						<input type="number" min="0" max="60" class="small-text" name="<?php echo esc_attr( $n( 'delay' ) ); ?>" value="<?php echo esc_attr( (int) $o['delay'] ); ?>"> seconds
						<p class="description">Counted only while the tab is actually being viewed.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Or scrolled</th>
					<td>
						<input type="number" min="0" max="100" class="small-text" name="<?php echo esc_attr( $n( 'scroll_pct' ) ); ?>" value="<?php echo esc_attr( (int) $o['scroll_pct'] ); ?>">%
						<p class="description">Whichever happens first — the dwell time or this much of the page.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">How long it stays quiet</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">After dismissing</th>
					<td>
						<input type="number" min="1" max="365" class="small-text" name="<?php echo esc_attr( $n( 'mute_dismiss' ) ); ?>" value="<?php echo esc_attr( (int) $o['mute_dismiss'] ); ?>"> days
					</td>
				</tr>
				<tr>
					<th scope="row">After tapping Get</th>
					<td>
						<input type="number" min="1" max="365" class="small-text" name="<?php echo esc_attr( $n( 'mute_tap' ) ); ?>" value="<?php echo esc_attr( (int) $o['mute_tap'] ); ?>"> days
						<p class="description">Longer, because they most likely installed it.</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<p class="description" style="max-width:760px;">Testing tip: the bar shows once per visit and then stays quiet.
		   To see it again, open a private window, or clear <code>aunAppBarMute</code> from the browser&rsquo;s local storage.</p>
	</div>
	<?php
}
