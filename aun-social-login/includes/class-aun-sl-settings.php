<?php
/**
 * Settings screen: Settings -> AUN Social Login.
 *
 * Uses the WordPress Settings API, so the nonce, referer check and capability
 * check are handled by options.php. sanitize() whitelists every field.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SL_Settings {

	const PAGE  = 'aun-social-login';
	const GROUP = 'aun_sl_group';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AUN_SL_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function menu() {
		add_options_page( 'AUN Social Login', 'AUN Social Login', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( self::GROUP, AUN_SL_Options::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( 'AUN_SL_Options', 'sanitize' ),
			'default'           => AUN_SL_Options::defaults(),
		) );
	}

	public static function action_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Settings</a>' );
		return $links;
	}

	private static function name( $key ) {
		return AUN_SL_Options::OPTION . '[' . $key . ']';
	}

	private static function checkbox( $key, $label, $o, $desc = '' ) {
		echo '<label style="display:block;margin:0 0 6px;"><input type="checkbox" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		if ( $desc !== '' ) {
			echo '<p class="description" style="margin:0 0 10px;">' . wp_kses_post( $desc ) . '</p>';
		}
	}

	/**
	 * A read-only, click-to-select box holding the exact redirect URI for a provider.
	 * Shown at the top AND inside each provider block so it can never be missed.
	 */
	private static function redirect_field( $provider ) {
		$url = AUN_SL_OAuth::callback_url( $provider );
		echo '<input type="text" readonly onclick="this.select()" value="' . esc_attr( $url ) . '" '
			. 'style="width:100%;max-width:640px;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;'
			. 'padding:9px 11px;border:1px solid #8c8f94;border-radius:5px;background:#fff;color:#1d2327;">';
	}

	private static function text( $key, $o, $placeholder = '', $type = 'text' ) {
		printf(
			'<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" autocomplete="off" spellcheck="false">',
			esc_attr( $type ),
			esc_attr( self::name( $key ) ),
			esc_attr( 'password' === $type ? '' : (string) $o[ $key ] ),
			esc_attr( $placeholder )
		);
	}

	/** Secrets are never echoed back; we only show whether one is stored. */
	private static function secret_state( $key ) {
		$stored = (string) AUN_SL_Options::get( $key );
		$const  = ( 'google_client_secret' === $key ) ? 'AUN_SL_GOOGLE_CLIENT_SECRET' : 'AUN_SL_FACEBOOK_APP_SECRET';
		if ( defined( $const ) && constant( $const ) !== '' ) {
			return '<span style="color:#0a6b1f;font-weight:600;">Set in wp-config.php</span> (the field below is ignored)';
		}
		if ( $stored !== '' ) {
			return '<span style="color:#0a6b1f;font-weight:600;">Saved</span> — leave blank to keep it, or type a new one to replace';
		}
		return '<span style="color:#8a5300;font-weight:600;">Not set</span>';
	}

	/** "Test this connection" button for one provider (disabled until configured). */
	private static function test_button( $provider ) {
		$label = ( 'google' === $provider ) ? 'Google' : 'Facebook';
		if ( ! AUN_SL_Options::provider_ready( $provider ) ) {
			echo '<button type="button" class="button" disabled>Test ' . esc_html( $label ) . ' connection</button>';
			echo '<p class="description">Enter the ID and secret above and press <strong>Save Changes</strong> first.</p>';
			return;
		}
		echo '<a class="button button-secondary" href="' . esc_url( AUN_SL_OAuth::test_url( $provider ) ) . '">Test ' . esc_html( $label ) . ' connection</a>';
		echo '<p class="description">Runs the real sign-in against ' . esc_html( $label ) . ' and reports what happened. '
			. '<strong>Nothing is created, linked or signed in</strong> — it is a dry run.</p>';
	}

	/** Show the result of the last test, then clear it (one-shot). */
	private static function test_result() {
		$key = 'aun_sl_test_' . get_current_user_id();
		$r   = get_transient( $key );
		if ( ! is_array( $r ) ) {
			return;
		}
		delete_transient( $key );

		$provider = ( isset( $r['provider'] ) && 'google' === $r['provider'] ) ? 'Google' : 'Facebook';

		if ( empty( $r['ok'] ) ) {
			echo '<div class="notice notice-error" style="max-width:900px;padding:12px 16px;">';
			echo '<p style="margin:.2em 0;font-size:14px;"><strong>' . esc_html( $provider ) . ' test failed.</strong></p>';
			echo '<p style="margin:.4em 0;">' . esc_html( (string) $r['error'] ) . '</p>';
			echo '<p style="margin:.4em 0;color:#646970;">Double-check the <strong>Redirect URL</strong> box below matches the provider console exactly.</p>';
			echo '</div>';
			return;
		}

		$pf  = isset( $r['profile'] ) ? $r['profile'] : array();
		$out = isset( $r['outcome'] ) ? $r['outcome'] : array();
		$refused = ( isset( $out['action'] ) && 'refuse' === $out['action'] );

		echo '<div class="notice notice-success" style="max-width:900px;padding:14px 18px;">';
		echo '<p style="margin:0 0 10px;font-size:14px;"><strong>' . esc_html( $provider ) . ' is connected and working.</strong> '
			. 'Credentials, redirect URL and permissions are all correct.</p>';

		echo '<table class="widefat striped" style="max-width:640px;margin:0 0 12px;"><tbody>';
		printf( '<tr><td style="width:190px;"><strong>Name</strong></td><td>%s</td></tr>', esc_html( $pf['name'] !== '' ? $pf['name'] : '(not shared)' ) );
		printf( '<tr><td><strong>Email</strong></td><td>%s</td></tr>', esc_html( $pf['email'] !== '' ? $pf['email'] : '(not shared)' ) );
		printf(
			'<tr><td><strong>Email verified by %s</strong></td><td>%s</td></tr>',
			esc_html( $provider ),
			! empty( $pf['verified'] )
				? '<span style="color:#0a6b1f;font-weight:600;">Yes</span>'
				: '<span style="color:#8a5300;font-weight:600;">No</span> &mdash; this profile could not be linked to an existing account'
		);
		printf(
			'<tr><td><strong>Profile picture</strong></td><td>%s</td></tr>',
			! empty( $pf['avatar'] )
				? '<img src="' . esc_url( $pf['avatar'] ) . '" alt="" width="32" height="32" style="border-radius:50%;vertical-align:middle;margin-right:8px;">Received'
				: 'Not shared (the normal Gravatar would be used)'
		);
		printf( '<tr><td><strong>Provider account ID</strong></td><td><code>%s</code></td></tr>', esc_html( $pf['id'] ) );
		echo '</tbody></table>';

		echo '<p style="margin:0 0 4px;font-weight:600;">If a customer signed in with this account right now:</p>';
		echo '<p style="margin:0;padding:9px 12px;border-radius:6px;background:' . ( $refused ? '#fcf0e4' : '#edfaef' ) . ';border:1px solid ' . ( $refused ? '#e8a33d' : '#b7e3bf' ) . ';">'
			. esc_html( isset( $out['message'] ) ? $out['message'] : '' ) . '</p>';

		if ( $refused && isset( $out['code'] ) && 'aun_sl_admin' === $out['code'] ) {
			echo '<p style="margin:10px 0 0;color:#646970;">That is expected — you are testing with a staff account and '
				. '<em>Block social sign-in for administrators</em> is on. A normal customer would sign in fine.</p>';
		}
		echo '</div>';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = AUN_SL_Options::all();
		?>
		<div class="wrap">
			<h1>AUN Social Login</h1>
			<p>Sign in with Google / Facebook for WooCommerce — social login only, no sharing or comment features.</p>

			<?php self::test_result(); ?>

			<div class="card" style="max-width:900px;padding:18px 22px;margin:16px 0 24px;border-left:4px solid #0188fe;background:#f6fbff;">
				<h2 style="margin:0 0 6px;">Step 1 &middot; Redirect URLs &mdash; copy these into the provider consoles</h2>
				<p style="margin:0 0 16px;color:#50575e;">Each provider only accepts sign-ins that come back to a URL you have registered.
				   Paste the matching box below &mdash; it must match <strong>character for character</strong>.
				   <em>Click a box to select it, then press Ctrl&nbsp;+&nbsp;C.</em></p>

				<p style="margin:0 0 5px;font-weight:600;">Google
					<span style="font-weight:400;color:#646970;">&mdash; Google Cloud console &rarr; APIs &amp; Services &rarr; Credentials &rarr; your OAuth client &rarr; <em>Authorised redirect URIs</em></span>
				</p>
				<?php self::redirect_field( 'google' ); ?>

				<p style="margin:16px 0 5px;font-weight:600;">Facebook
					<span style="font-weight:400;color:#646970;">&mdash; Facebook app &rarr; Facebook Login &rarr; Settings &rarr; <em>Valid OAuth Redirect URIs</em></span>
				</p>
				<?php self::redirect_field( 'facebook' ); ?>

				<p style="margin:16px 0 0;color:#646970;">Also add your domain under <em>Authorised JavaScript origins</em> (Google) if it asks:
					<code><?php echo esc_html( home_url() ); ?></code></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title">Google</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enable</th>
						<td><?php self::checkbox( 'google_enabled', 'Show the Google button', $o ); ?></td>
					</tr>
					<tr>
						<th scope="row">Client ID</th>
						<td><?php self::text( 'google_client_id', $o, '1234567890-abc.apps.googleusercontent.com' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Redirect URL</th>
						<td>
							<?php self::redirect_field( 'google' ); ?>
							<p class="description">Must be listed in your Google OAuth client.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Client secret</th>
						<td>
							<?php self::text( 'google_client_secret', $o, 'Enter to change', 'password' ); ?>
							<p class="description"><?php echo wp_kses_post( self::secret_state( 'google_client_secret' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">One Tap</th>
						<td>
							<?php self::checkbox( 'onetap_enabled', 'Show the Google One Tap prompt to signed-out visitors', $o, 'The floating &ldquo;Sign in as &hellip;&rdquo; card in the corner. Uses the same Client ID above and the same account rules as the buttons. Replaces the separate <em>AUN Google One Tap</em> plugin &mdash; <strong>deactivate that one</strong> or the prompt appears twice.' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Test</th>
						<td><?php self::test_button( 'google' ); ?></td>
					</tr>
				</table>

				<h2 class="title">Facebook</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enable</th>
						<td><?php self::checkbox( 'facebook_enabled', 'Show the Facebook button', $o ); ?></td>
					</tr>
					<tr>
						<th scope="row">App ID</th>
						<td><?php self::text( 'facebook_app_id', $o, '123456789012345' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Redirect URL</th>
						<td>
							<?php self::redirect_field( 'facebook' ); ?>
							<p class="description">Must be listed under Valid OAuth Redirect URIs in your Facebook app.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">App secret</th>
						<td>
							<?php self::text( 'facebook_app_secret', $o, 'Enter to change', 'password' ); ?>
							<p class="description"><?php echo wp_kses_post( self::secret_state( 'facebook_app_secret' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">Test</th>
						<td><?php self::test_button( 'facebook' ); ?></td>
					</tr>
				</table>

				<h2 class="title">Where to show the buttons</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Placement</th>
						<td>
							<?php
							self::checkbox( 'at_wc_login', 'WooCommerce login form', $o, 'Inside the form, just above the Login button.' );
							self::checkbox( 'at_wc_login_before', 'Above the WooCommerce login form', $o, 'Use this instead if the phone-OTP plugin hides the email/password form — anything inside that form gets hidden with it.' );
							self::checkbox( 'at_wc_register', 'WooCommerce register form', $o, 'Inside the form, just above the Register button (same spot as the old plugin).' );
							self::checkbox( 'at_wc_checkout', 'WooCommerce checkout page', $o );
							self::checkbox( 'at_wp_login', 'WordPress wp-login.php screen', $o, 'Leave off if only customers use social login.' );
							?>
							<p class="description">You can also place them anywhere with the shortcode <code>[aun_social_login]</code>.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Appearance</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Caption</th>
						<td>
							<?php self::text( 'title', $o, 'Or login with' ); ?>
							<p class="description">Shown above the icons. Leave empty to hide.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Icon shape</th>
						<td>
							<select name="<?php echo esc_attr( self::name( 'shape' ) ); ?>">
								<?php foreach ( array( 'round' => 'Round', 'rounded' => 'Rounded square', 'square' => 'Square' ) as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['shape'], $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Icon size</th>
						<td>
							<input type="number" min="28" max="72" name="<?php echo esc_attr( self::name( 'size' ) ); ?>" value="<?php echo esc_attr( (int) $o['size'] ); ?>" class="small-text"> px
						</td>
					</tr>
					<tr>
						<th scope="row">Alignment</th>
						<td>
							<select name="<?php echo esc_attr( self::name( 'align' ) ); ?>">
								<?php foreach ( array( 'center' => 'Centre', 'left' => 'Left', 'right' => 'Right' ) as $k => $v ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['align'], $k ); ?>><?php echo esc_html( $v ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Button style</th>
						<td><?php self::checkbox( 'show_label', 'Wide buttons with text ("Continue with Google")', $o, 'Off = compact brand icons, like the old plugin.' ); ?></td>
					</tr>
				</table>

				<h2 class="title">Accounts &amp; security</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Registration</th>
						<td><?php self::checkbox( 'allow_register', 'Create an account when a new person signs in socially', $o ); ?></td>
					</tr>
					<tr>
						<th scope="row">Link by email</th>
						<td><?php self::checkbox( 'link_by_email', 'Link to an existing account when the email matches', $o, 'Only ever applies to an address the provider has <strong>verified</strong>. With this off, an existing customer must sign in with their password once before the social account can be linked.' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Staff accounts</th>
						<td><?php self::checkbox( 'block_admins', 'Block social sign-in for administrators / shop managers (recommended)', $o, 'Staff should sign in with a password so a compromised Google or Facebook account cannot reach the dashboard.' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Profile pictures</th>
						<td><?php self::checkbox( 'use_avatar', 'Use the Google / Facebook profile picture as the customer avatar', $o, 'Refreshed at every sign-in. Only images served from the providers own CDNs are accepted; anything else is ignored. Customers with no social picture keep the normal Gravatar.' ); ?></td>
					</tr>
					<tr>
						<th scope="row">Notifications</th>
						<td><?php self::checkbox( 'notify_admin', 'Email the admin when a new user registers this way', $o ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
