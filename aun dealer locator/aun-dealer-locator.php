<?php
/**
 * Plugin Name: AUN Dealer Locator
 * Description: Lightweight dealer locator for AUN Projector Bangladesh. Manage dealers in admin and display via shortcode. Includes product page notice shortcode.
 * Version: 1.2.1
 * Author: Smart Living Bangladesh
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AUN_Dealer_Locator {
	const OPT_KEY = 'aun_dealers_data';
	const VER     = '1.2.1';

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );
		add_action( 'admin_post_aun_dealers_save', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_post_aun_dealers_delete', [ __CLASS__, 'handle_delete' ] );
		add_shortcode( 'aun_dealers', [ __CLASS__, 'shortcode' ] );
		add_shortcode( 'aun_dealer_notice', [ __CLASS__, 'render_notice_shortcode' ] );
		// Assets are registered lazily inside shortcode() — no need to register
		// globally on every page load.
	}

	/** Prevents the notice badge <style> from being output more than once. */
	private static bool $notice_styles_printed = false;

	/** -------------------- Data -------------------- */

	private static function get_dealers() : array {
		$dealers = get_option( self::OPT_KEY, [] );
		return is_array( $dealers ) ? $dealers : [];
	}

	private static function set_dealers( array $dealers ) : void {
		update_option( self::OPT_KEY, array_values( $dealers ), false );
	}

	private static function sanitize_dealer( array $raw ) : array {
		$clean = [
			'id'       => isset( $raw['id'] ) ? sanitize_text_field( (string) $raw['id'] ) : '',
			'name'     => isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '',
			'district' => isset( $raw['district'] ) ? sanitize_text_field( (string) $raw['district'] ) : '',
			'address'  => isset( $raw['address'] ) ? sanitize_textarea_field( (string) $raw['address'] ) : '',
			'phone'    => isset( $raw['phone'] ) ? preg_replace( '/[^0-9]/', '', (string) $raw['phone'] ) : '',
			'whatsapp' => isset( $raw['whatsapp'] ) ? self::normalise_bd_phone( (string) $raw['whatsapp'] ) : '',
			'map_url'  => isset( $raw['map_url'] ) ? esc_url_raw( (string) $raw['map_url'] ) : '',
			'note'     => isset( $raw['note'] ) ? sanitize_text_field( (string) $raw['note'] ) : '',
		];

		if ( empty( $clean['id'] ) ) {
			// uniqid( '', true ) adds microseconds — collision-safe even on rapid saves.
			$clean['id'] = uniqid( '', true );
		}

		return $clean;
	}

	/**
	 * Normalises a Bangladesh phone number to full international format (880XXXXXXXXXX).
	 * Accepts any common format: 017xxxxxxxx, 8801xxxxxxx, +8801xxxxxxx.
	 * Stored in international format so JS can use it directly in wa.me links.
	 */
	private static function normalise_bd_phone( string $raw ) : string {
		$digits = preg_replace( '/[^0-9]/', '', $raw );
		if ( empty( $digits ) ) return '';

		// Already full international: 8801XXXXXXXXX (13 digits)
		if ( strlen( $digits ) === 13 && str_starts_with( $digits, '880' ) ) {
			return $digits;
		}
		// Local format: 01XXXXXXXXX (11 digits) — prepend 88
		if ( strlen( $digits ) === 11 && str_starts_with( $digits, '0' ) ) {
			return '88' . $digits;
		}
		// Partial international: 1XXXXXXXXX (10 digits) — prepend 880
		if ( strlen( $digits ) === 10 && str_starts_with( $digits, '1' ) ) {
			return '880' . $digits;
		}
		// Return as-is for any other format
		return $digits;
	}

	/** -------------------- Admin -------------------- */

	public static function admin_menu() : void {
		add_menu_page(
			'AUN Dealers',
			'AUN Dealers',
			'manage_options',
			'aun-dealers',
			[ __CLASS__, 'admin_page' ],
			'dashicons-location-alt',
			58
		);
	}

	public static function admin_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$dealers = self::get_dealers();
		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( (string) $_GET['edit'] ) : '';
		$editing = null;

		if ( $edit_id ) {
			foreach ( $dealers as $d ) {
				if ( isset( $d['id'] ) && $d['id'] === $edit_id ) {
					$editing = $d;
					break;
				}
			}
		}

		// Defaults for "Add new"
		$form = array_merge(
			[
				'id'       => '',
				'name'     => '',
				'district' => '',
				'address'  => '',
				'phone'    => '',
				'whatsapp' => '',
				'map_url'  => '',
				'note'     => '',
			],
			is_array( $editing ) ? $editing : []
		);

		?>
		<div class="wrap">
			<h1>AUN Dealer Locator</h1>
			<p><strong>Directory Shortcode:</strong> <code>[aun_dealers]</code> — paste it into your “Authorized Dealers” page.</p>
			<p><strong>Product Notice Shortcode:</strong> <code>[aun_dealer_notice]</code> — paste into Flatsome's Product Page Custom HTML block.</p>

			<hr />

			<h2><?php echo $editing ? 'Edit Dealer' : 'Add Dealer'; ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aun_dealers_save" />
				<?php wp_nonce_field( 'aun_dealers_save', 'aun_dealers_nonce' ); ?>
				<input type="hidden" name="dealer[id]" value="<?php echo esc_attr( $form['id'] ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aun_name">Shop / Dealer Name</label></th>
						<td><input id="aun_name" name="dealer[name]" type="text" class="regular-text" required value="<?php echo esc_attr( $form['name'] ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_district">District</label></th>
						<td><input id="aun_district" name="dealer[district]" type="text" class="regular-text" placeholder="e.g., Pabna" value="<?php echo esc_attr( $form['district'] ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_address">Address</label></th>
						<td><textarea id="aun_address" name="dealer[address]" rows="3" class="large-text"><?php echo esc_textarea( $form['address'] ); ?></textarea></td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_phone">Phone</label></th>
						<td><input id="aun_phone" name="dealer[phone]" type="text" class="regular-text" placeholder="e.g., 017xxxxxxxx" value="<?php echo esc_attr( $form['phone'] ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_whatsapp">WhatsApp</label></th>
						<td>
							<input id="aun_whatsapp" name="dealer[whatsapp]" type="text" class="regular-text" placeholder="e.g., 01712345678" value="<?php echo esc_attr( $form['whatsapp'] ); ?>" />
							<p class="description">Enter in local format (e.g. <code>01712345678</code>) — country code is added automatically.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_map">Google Maps Link</label></th>
						<td><input id="aun_map" name="dealer[map_url]" type="url" class="large-text" placeholder="https://maps.google.com/?q=..." value="<?php echo esc_attr( $form['map_url'] ); ?>" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="aun_note">Optional Note</label></th>
						<td><input id="aun_note" name="dealer[note]" type="text" class="regular-text" placeholder="e.g., Demo available" value="<?php echo esc_attr( $form['note'] ); ?>" /></td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $editing ? 'Update Dealer' : 'Add Dealer'; ?></button>
					<?php if ( $editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aun-dealers' ) ); ?>" class="button">Cancel</a>
					<?php endif; ?>
				</p>
			</form>

			<hr />

			<h2>Dealers</h2>
			<?php if ( empty( $dealers ) ) : ?>
				<p>No dealers added yet.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Dealer</th>
							<th>District</th>
							<th>Phone</th>
							<th>WhatsApp</th>
							<th>Map</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $dealers as $d ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $d['name'] ?? '' ); ?></strong><?php if ( ! empty( $d['address'] ) ) : ?><br><span style="color:#64748b"><?php echo esc_html( $d['address'] ); ?></span><?php endif; ?></td>
								<td><?php echo esc_html( $d['district'] ?? '' ); ?></td>
								<td><?php echo esc_html( $d['phone'] ?? '' ); ?></td>
								<td><?php echo esc_html( $d['whatsapp'] ?? '' ); ?></td>
								<td>
									<?php if ( ! empty( $d['map_url'] ) ) : ?>
										<a href="<?php echo esc_url( $d['map_url'] ); ?>" target="_blank" rel="noopener noreferrer">Open</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=aun-dealers&edit=' . rawurlencode( $d['id'] ) ) ); ?>">Edit</a>
									<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete this dealer?');">
										<input type="hidden" name="action" value="aun_dealers_delete" />
										<?php wp_nonce_field( 'aun_dealers_delete', 'aun_dealers_nonce' ); ?>
										<input type="hidden" name="id" value="<?php echo esc_attr( $d['id'] ); ?>" />
										<button type="submit" class="button button-small button-link-delete">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top:14px;color:#64748b">
				<strong>Tip:</strong> Use full Google Maps share link for each dealer (from Google Maps → Share → Copy link).
			</p>
		</div>
		<?php
	}

	public static function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'aun_dealers_save', 'aun_dealers_nonce' );

		$raw = isset( $_POST['dealer'] ) && is_array( $_POST['dealer'] ) ? wp_unslash( $_POST['dealer'] ) : [];
		$dealer = self::sanitize_dealer( $raw );

		$dealers = self::get_dealers();

		$updated = false;
		foreach ( $dealers as $i => $d ) {
			if ( isset( $d['id'] ) && $d['id'] === $dealer['id'] ) {
				$dealers[ $i ] = $dealer;
				$updated = true;
				break;
			}
		}
		if ( ! $updated ) {
			$dealers[] = $dealer;
		}

		self::set_dealers( $dealers );

		wp_safe_redirect( admin_url( 'admin.php?page=aun-dealers' ) );
		exit;
	}

	public static function handle_delete() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'aun_dealers_delete', 'aun_dealers_nonce' );

		$id = isset( $_POST['id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=aun-dealers' ) );
			exit;
		}

		$dealers = array_values( array_filter( self::get_dealers(), function( $d ) use ( $id ) {
			return isset( $d['id'] ) && $d['id'] !== $id;
		}));

		self::set_dealers( $dealers );

		wp_safe_redirect( admin_url( 'admin.php?page=aun-dealers' ) );
		exit;
	}

	/** -------------------- Product Page Notice -------------------- */

	/**
	 * Outputs the notice badge <style> block once per page request.
	 *
	 * WHY inline in shortcode output (not wp_enqueue_scripts):
	 *   - Flatsome's UX Builder stores shortcode content outside $post->post_content,
	 *     so has_shortcode() always returns false and enqueue hooks never fire.
	 *   - WP Rocket's Remove Unused CSS strips dynamically enqueued inline styles.
	 *   - Outputting the <style> directly in the shortcode return value is immune
	 *     to both. data-no-optimize tells WP Rocket to leave this tag alone.
	 */
	private static function maybe_print_notice_styles() : string {
		if ( self::$notice_styles_printed ) return '';
		self::$notice_styles_printed = true;

		return '
		<style id="aun-dealer-notice-css" data-no-optimize="1" data-no-minify="1">
			.aun-dn-wrap      { margin-top:12px; margin-bottom:0; }
			.aun-dn-label     { font-size:12px; font-weight:600; text-transform:uppercase;
			                    letter-spacing:.5px; color:#64748b; margin:0 0 8px; }
			.aun-dn-buttons   { display:inline-flex; border:1px solid #e2e8f0;
			                    border-radius:8px; overflow:hidden; background:#fff; }
			.aun-dn-btn       { display:flex; align-items:center; gap:7px; padding:10px 18px;
			                    font-size:14px; font-weight:600; text-decoration:none !important;
			                    transition:background .15s; line-height:1; }
			.aun-dn-btn--loc  { color:#2563eb; }
			.aun-dn-btn--loc:hover { background:#f1f5f9; }
			.aun-dn-btn--info { color:#16a34a; cursor:default; }
			.aun-dn-btn i     { font-size:16px; }
			.aun-dn-divider   { width:1px; background:#e2e8f0; align-self:stretch; flex-shrink:0; }
		</style>';
	}

	public static function render_notice_shortcode() : string {
		// wc_get_product( get_the_ID() ) is reliable inside and outside the WC loop,
		// in page builders, and REST contexts — unlike global $product.
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;

		if ( ! $product || ! function_exists( 'is_product' ) || ! is_product() ) {
			return '';
		}

		// Hide on discontinued products.
		if ( taxonomy_exists( 'product_discontinued' ) && has_term( 'dp-discontinued', 'product_discontinued', $product->get_id() ) ) {
			return '';
		}

		$dealers_url = get_permalink( get_page_by_path( 'authorized-dealers' ) ) ?: home_url( '/authorized-dealers/' );

		ob_start();
		echo self::maybe_print_notice_styles();
		?>
		<div class="aun-dn-wrap">
		<!--	<p class="aun-dn-label">Authorized dealers</p> -->
			<div class="aun-dn-buttons">

				<a class="aun-dn-btn aun-dn-btn--loc"
				   href="<?php echo esc_url( $dealers_url ); ?>"
				   rel="noopener noreferrer">
					<i class="fa-solid fa-location-dot" aria-hidden="true"></i>
					<span>View nearby dealer</span>
				</a>
<!--
				<div class="aun-dn-divider" aria-hidden="true"></div>

				<span class="aun-dn-btn aun-dn-btn--info">
					<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
					<span>Demo &amp; warranty support</span>
				</span>
-->
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** -------------------- Front-end -------------------- */

	public static function register_assets() : void {
		$base = plugin_dir_url( __FILE__ );
		wp_register_style( 'aun-dealers', $base . 'assets/aun-dealers.css', [], self::VER );
		wp_register_script( 'aun-dealers', $base . 'assets/aun-dealers.js', [], self::VER, true );
	}

	public static function shortcode( $atts ) : string {
		$atts = shortcode_atts( [
			'title'    => 'Find an Authorized AUN Dealer',
			'subtitle' => 'Visit verified partners for live demo & official warranty support.',
			'notice'   => 'Only purchases from listed partners qualify for official warranty & service support.',
		], $atts, 'aun_dealers' );

		$dealers = self::get_dealers();

		// Register and enqueue assets here rather than globally — this way they
		// only load on pages that actually use the shortcode.
		$base = plugin_dir_url( __FILE__ );
		wp_register_style(  'aun-dealers', $base . 'assets/aun-dealers.css', [], self::VER );
		wp_register_script( 'aun-dealers', $base . 'assets/aun-dealers.js',  [], self::VER, true );
		wp_enqueue_style(  'aun-dealers' );
		wp_enqueue_script( 'aun-dealers' );

		wp_localize_script( 'aun-dealers', 'AUN_DEALERS', [
			'dealers' => $dealers,
			'i18n'    => [
				'search_placeholder' => 'Search by district, shop name, or address...',
				'no_results'         => 'No dealers found. Try another search.',
				'call'               => 'Call',
				'whatsapp'           => 'WhatsApp',
				'navigate'           => 'Navigate',
			],
		] );

		ob_start();
		?>
		<div class="aun-dealers-wrap">
			<?php if ( $atts['title'] !== '' || $atts['subtitle'] !== '' ) : ?>
			<div class="aun-dealers-hero">
				<?php if ( $atts['title'] !== '' ) : ?><h2 class="aun-dealers-title"><i class="fa-solid fa-store"></i> <?php echo esc_html( $atts['title'] ); ?></h2><?php endif; ?>
				<?php if ( $atts['subtitle'] !== '' ) : ?><p><?php echo esc_html( $atts['subtitle'] ); ?></p><?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="aun-dealers-search">
				<input type="text" id="aunDealerSearch" placeholder="Search..." />
			</div>

			<div id="aunDealerGrid" class="aun-dealers-grid" aria-live="polite"></div>

			<div class="aun-dealers-notice"><?php echo esc_html( $atts['notice'] ); ?></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

AUN_Dealer_Locator::init();