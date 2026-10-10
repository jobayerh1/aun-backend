<?php
/**
 * Admin menu: Palltheme → Dashboard / Business Settings / Design / Import Demo.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register menus.
 */
function pallcore_admin_menu(): void {
	add_menu_page(
		__( 'Palltheme', 'palltheme-core' ),
		__( 'Theme Settings', 'palltheme-core' ),
		'manage_options',
		'pallcore',
		'pallcore_render_dashboard',
		'dashicons-superhero-alt',
		3
	);
	add_submenu_page( 'pallcore', __( 'Dashboard', 'palltheme-core' ), __( 'Dashboard', 'palltheme-core' ), 'manage_options', 'pallcore', 'pallcore_render_dashboard' );
	add_submenu_page( 'pallcore', __( 'Business Settings', 'palltheme-core' ), __( 'Business Settings', 'palltheme-core' ), 'manage_options', 'pallcore-settings', 'pallcore_render_settings_page' );
	add_submenu_page( 'pallcore', __( 'Design (Customizer)', 'palltheme-core' ), __( 'Design & Colors', 'palltheme-core' ), 'edit_theme_options', 'customize.php?autofocus[panel]=palltheme' );
	add_submenu_page( 'pallcore', __( 'Import Demo', 'palltheme-core' ), __( 'Import Demo', 'palltheme-core' ), 'manage_options', 'pallcore-demo', 'pallcore_render_demo_page' );
}
add_action( 'admin_menu', 'pallcore_admin_menu' );

/**
 * Dashboard with quick links and a health checklist.
 */
function pallcore_render_dashboard(): void {
	$links = array(
		array( __( 'Business details', 'palltheme-core' ), __( 'Phone, email, address, hours, social links.', 'palltheme-core' ), admin_url( 'admin.php?page=pallcore-settings' ) ),
		array( __( 'Colors, fonts & header', 'palltheme-core' ), __( 'Brand colors, typography, header and footer.', 'palltheme-core' ), admin_url( 'customize.php?autofocus[panel]=palltheme' ) ),
		array( __( 'Logo & site icon', 'palltheme-core' ), __( 'Upload your logo and favicon.', 'palltheme-core' ), admin_url( 'customize.php?autofocus[section]=title_tagline' ) ),
		array( __( 'Menus', 'palltheme-core' ), __( 'Header mega menu and footer columns.', 'palltheme-core' ), admin_url( 'nav-menus.php' ) ),
		array( __( 'Services', 'palltheme-core' ), __( 'Add or edit the services you offer.', 'palltheme-core' ), admin_url( 'edit.php?post_type=pall_service' ) ),
		array( __( 'Products', 'palltheme-core' ), __( 'Your WooCommerce catalog.', 'palltheme-core' ), admin_url( 'edit.php?post_type=product' ) ),
		array( __( 'Homepage', 'palltheme-core' ), __( 'Edit the homepage with Elementor.', 'palltheme-core' ), get_option( 'page_on_front' ) ? admin_url( 'post.php?post=' . (int) get_option( 'page_on_front' ) . '&action=edit' ) : admin_url( 'options-reading.php' ) ),
		array( __( 'Import demo', 'palltheme-core' ), __( 'Install the complete TechNova demo website.', 'palltheme-core' ), admin_url( 'admin.php?page=pallcore-demo' ) ),
	);

	$checks = array(
		array( __( 'Palltheme theme active', 'palltheme-core' ), 'palltheme' === get_template() ),
		array( __( 'Elementor active', 'palltheme-core' ), defined( 'ELEMENTOR_VERSION' ) ),
		array( __( 'WooCommerce active', 'palltheme-core' ), class_exists( 'WooCommerce' ) ),
		array( __( 'Pretty permalinks enabled', 'palltheme-core' ), (bool) get_option( 'permalink_structure' ) ),
		array( __( 'Phone and email set', 'palltheme-core' ), pallcore_setting( 'phone' ) && pallcore_setting( 'email' ) ),
		array( __( 'Logo uploaded', 'palltheme-core' ), (bool) get_theme_mod( 'custom_logo' ) ),
		array( __( 'Site icon (favicon) set', 'palltheme-core' ), has_site_icon() ),
		array( __( 'SEO plugin active (Rank Math or Yoast)', 'palltheme-core' ), defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ),
	);
	?>
	<div class="wrap pallcore-settings">
		<h1><?php esc_html_e( 'Palltheme Dashboard', 'palltheme-core' ); ?></h1>
		<p><?php esc_html_e( 'Manage your whole website from here — no code needed.', 'palltheme-core' ); ?></p>
		<div class="pallcore-card">
			<h2><?php esc_html_e( 'Quick links', 'palltheme-core' ); ?></h2>
			<ul>
				<?php foreach ( $links as $link ) : ?>
					<li><a href="<?php echo esc_url( $link[2] ); ?>"><strong><?php echo esc_html( $link[0] ); ?></strong></a> — <?php echo esc_html( $link[1] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="pallcore-card">
			<h2><?php esc_html_e( 'Setup checklist', 'palltheme-core' ); ?></h2>
			<ul style="list-style:none;padding:0">
				<?php foreach ( $checks as $check ) : ?>
					<li><?php echo $check[1] ? '✅' : '⬜'; ?> <?php echo esc_html( $check[0] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="pallcore-card">
			<h2><?php esc_html_e( 'Shortcodes (for any page or Elementor “Shortcode” widget)', 'palltheme-core' ); ?></h2>
			<p><code>[pall_hero]</code> <code>[pall_services count="6"]</code> <code>[pall_solutions]</code> <code>[pall_case_studies]</code> <code>[pall_team]</code> <code>[pall_testimonials layout="slider|grid"]</code> <code>[pall_clients layout="carousel|grid"]</code> <code>[pall_technologies]</code> <code>[pall_stats]</code> <code>[pall_products]</code> <code>[pall_posts]</code> <code>[pall_pricing]</code> <code>[pall_faq]</code> <code>[pall_jobs]</code> <code>[pall_contact]</code> <code>[pall_contact_form]</code> <code>[pall_cta]</code> <code>[pall_process]</code> <code>[pall_section_head]</code></p>
			<p><?php esc_html_e( 'Every shortcode is also available as a drag-and-drop widget in Elementor under “Palltheme”.', 'palltheme-core' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Friendly admin columns: thumbnails for visual types.
 */
foreach ( array( 'pall_client', 'pall_team', 'pall_testimonial', 'pall_technology' ) as $pallcore_type ) {
	add_filter(
		"manage_{$pallcore_type}_posts_columns",
		static function ( $cols ) {
			return array_slice( $cols, 0, 1, true ) + array( 'pall_thumb' => __( 'Image', 'palltheme-core' ) ) + array_slice( $cols, 1, null, true );
		}
	);
	add_action(
		"manage_{$pallcore_type}_posts_custom_column",
		static function ( $col, $post_id ) {
			if ( 'pall_thumb' === $col ) {
				echo get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'style' => 'width:48px;height:48px;object-fit:contain;border-radius:6px;background:#f6f7f7' ) );
			}
		},
		10,
		2
	);
}
