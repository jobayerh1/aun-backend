<?php
/**
 * Settings → Smart Living Site.
 *
 * Contact details, the Contact Form 7 shortcode, per-page SEO overrides, and
 * the button that adopts the six pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sl_admin_menu() {
	add_options_page(
		'Smart Living Site',
		'Smart Living Site',
		'manage_options',
		'smart-living-site',
		'sl_admin_page'
	);
}
add_action( 'admin_menu', 'sl_admin_menu' );

function sl_admin_register() {
	register_setting(
		'sl_site_group',
		'sl_site_options',
		array( 'sanitize_callback' => 'sl_admin_sanitize' )
	);
	register_setting(
		'sl_site_group',
		'sl_site_seo',
		array( 'sanitize_callback' => 'sl_admin_sanitize_seo' )
	);
}
add_action( 'admin_init', 'sl_admin_register' );

function sl_admin_sanitize( $input ) {
	$clean    = array();
	$defaults = sl_defaults();
	$urls     = array( 'maps_url', 'facebook', 'aun_url', 'koh_url', 'breo_url', 'app_url' );

	foreach ( $defaults as $key => $default ) {
		$value = isset( $input[ $key ] ) ? $input[ $key ] : '';

		if ( 'og_image' === $key ) {
			$clean[ $key ] = (int) $value;
			continue;
		}
		if ( in_array( $key, $urls, true ) ) {
			$clean[ $key ] = esc_url_raw( trim( $value ) );
			continue;
		}
		if ( 'email' === $key ) {
			$clean[ $key ] = sanitize_email( $value );
			continue;
		}
		if ( 'cf7' === $key ) {
			// A shortcode, so angle brackets and PHP have no business here.
			$clean[ $key ] = wp_strip_all_tags( trim( $value ) );
			continue;
		}
		if ( 'founded' === $key ) {
			$ts            = strtotime( $value );
			$clean[ $key ] = $ts ? gmdate( 'Y-m-d', $ts ) : $default;
			continue;
		}
		$clean[ $key ] = sanitize_text_field( $value );
	}
	return $clean;
}

function sl_admin_sanitize_seo( $input ) {
	$clean = array();
	foreach ( sl_pages() as $key => $page ) {
		$clean[ $key ] = array(
			'title' => isset( $input[ $key ]['title'] ) ? sanitize_text_field( $input[ $key ]['title'] ) : '',
			'desc'  => isset( $input[ $key ]['desc'] ) ? sanitize_textarea_field( $input[ $key ]['desc'] ) : '',
		);
	}
	return $clean;
}

/**
 * "Build pages" — adopts the existing pages by slug, creates any that are
 * missing. Never touches page content.
 */
function sl_admin_build() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'sl_build_pages' );

	$report = sl_build_pages();
	set_transient( 'sl_build_report', $report, 60 );

	wp_safe_redirect( admin_url( 'options-general.php?page=smart-living-site&built=1' ) );
	exit;
}
add_action( 'admin_post_sl_build_pages', 'sl_admin_build' );

function sl_admin_page() {
	$o     = sl_opt();
	$seo   = get_option( 'sl_site_seo', array() );
	$defs  = sl_c_seo();
	$pages = sl_pages();

	$fields = array(
		'phone'      => array( 'Phone (shown)', 'text' ),
		'phone_link' => array( 'Phone (tel: link)', 'text' ),
		'email'      => array( 'Email', 'text' ),
		'address_1'  => array( 'Address line 1', 'text' ),
		'address_2'  => array( 'Address line 2', 'text' ),
		'locality'   => array( 'City (for schema)', 'text' ),
		'postcode'   => array( 'Postcode (for schema)', 'text' ),
		'maps_url'   => array( 'Google Maps link', 'text' ),
		'facebook'   => array( 'Facebook page', 'text' ),
		'hours_days' => array( 'Opening days', 'text' ),
		'hours_time' => array( 'Opening hours', 'text' ),
		'closed_day' => array( 'Closed day', 'text' ),
		'founded'    => array( 'Trading since (YYYY-MM-DD)', 'text' ),
		'customers'  => array( 'Customers served', 'text' ),
		'aun_url'    => array( 'AUN Projector site', 'text' ),
		'koh_url'    => array( 'Kohthai site', 'text' ),
		'breo_url'   => array( 'Breo site', 'text' ),
		'app_url'    => array( 'AUN Care app page', 'text' ),
	);
	?>
	<div class="wrap">
		<h1>Smart Living Site</h1>

		<?php if ( isset( $_GET['built'] ) ) : ?>
			<?php $report = get_transient( 'sl_build_report' ); ?>
			<div class="notice notice-success">
				<p><strong>Pages checked.</strong></p>
				<?php if ( is_array( $report ) ) : ?>
					<ul style="margin-left:1.4em;list-style:disc">
						<?php foreach ( $report as $key => $state ) : ?>
							<li><code><?php echo esc_html( $key ); ?></code> — <?php echo esc_html( $state ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="card" style="max-width:820px;padding:16px 20px">
			<h2 style="margin-top:0">Pages</h2>
			<p>
				This finds the six pages that already exist on the site, adopts them by their current
				slug, and creates any that are missing. Existing URLs and page content are never
				changed — these pages are rendered entirely by the plugin.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sl_build_pages">
				<?php wp_nonce_field( 'sl_build_pages' ); ?>
				<?php submit_button( 'Build / check pages', 'primary', 'submit', false ); ?>
			</form>
			<p class="description" style="margin-top:12px">
				The home page follows <em>Settings → Reading</em>. It must be set to a static page.
			</p>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'sl_site_group' ); ?>

			<h2>Contact details</h2>
			<p class="description">These feed the header, footer, contact blocks and the schema.</p>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $meta ) : ?>
					<tr>
						<th scope="row"><label for="sl-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta[0] ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="sl-<?php echo esc_attr( $key ); ?>"
								name="sl_site_options[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( $o[ $key ] ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="sl-cf7">Contact Form 7 shortcode</label></th>
					<td>
						<input type="text" class="large-text code" id="sl-cf7"
							name="sl_site_options[cf7]" value="<?php echo esc_attr( $o['cf7'] ); ?>"
							placeholder="[contact-form-7 id=&quot;123&quot; title=&quot;Contact form 1&quot;]">
						<p class="description">
							Paste the shortcode from <em>Contact → Contact Forms</em>. Leave empty and the
							contact page shows an email button instead. See DEPLOY.md for form markup that
							matches this design.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sl-og">Social share image ID</label></th>
					<td>
						<input type="number" class="small-text" id="sl-og"
							name="sl_site_options[og_image]" value="<?php echo esc_attr( (int) $o['og_image'] ); ?>">
						<p class="description">
							Media library attachment ID, 1200×630. Leave at 0 to use the card bundled with
							the plugin.
						</p>
					</td>
				</tr>
			</table>

			<h2>SEO</h2>
			<p class="description">
				Leave a field empty to use the default shown beneath it. No SEO plugin is needed —
				these produce the title, description, Open Graph tags and schema.
			</p>
			<table class="form-table" role="presentation">
				<?php foreach ( $pages as $key => $page ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $page['title'] ); ?></th>
						<td>
							<input type="text" class="large-text" placeholder="Title tag"
								name="sl_site_seo[<?php echo esc_attr( $key ); ?>][title]"
								value="<?php echo esc_attr( isset( $seo[ $key ]['title'] ) ? $seo[ $key ]['title'] : '' ); ?>">
							<p class="description" style="margin:4px 0 10px"><code><?php echo esc_html( $defs[ $key ]['title'] ); ?></code></p>
							<textarea class="large-text" rows="2" placeholder="Meta description"
								name="sl_site_seo[<?php echo esc_attr( $key ); ?>][desc]"><?php echo esc_textarea( isset( $seo[ $key ]['desc'] ) ? $seo[ $key ]['desc'] : '' ); ?></textarea>
							<p class="description" style="margin:4px 0 0"><?php echo esc_html( $defs[ $key ]['desc'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
