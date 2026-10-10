<?php
/**
 * Elementor compatibility.
 *
 * - Works with free Elementor: every page/post can be built with Elementor;
 *   the theme provides "Full Width" and "Canvas" page templates.
 * - Theme colors and fonts are pushed into Elementor's Global Colors /
 *   Global Fonts so Elementor widgets and the theme share one palette.
 * - If Elementor Pro is present, its Theme Builder header/footer locations
 *   automatically replace the theme header/footer.
 * - The Palltheme widgets themselves (Services, Case Studies, Hero, ...) are
 *   registered by the Palltheme Core plugin.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Let Elementor Pro Theme Builder take over header/footer when used.
 *
 * @param object $manager Locations manager.
 */
function palltheme_elementor_locations( $manager ): void {
	$manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'palltheme_elementor_locations' );

/**
 * Whether Elementor Pro renders a given location.
 */
function palltheme_elementor_location( string $location ): bool {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}

/**
 * Push theme colors/fonts into the active Elementor Kit.
 */
function palltheme_sync_elementor_kit(): void {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	$kit_id = (int) get_option( 'elementor_active_kit' );
	if ( ! $kit_id ) {
		return;
	}

	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : array();

	$settings['system_colors'] = array(
		array( '_id' => 'primary', 'title' => __( 'Primary', 'palltheme' ), 'color' => palltheme_mod( 'color_primary' ) ),
		array( '_id' => 'secondary', 'title' => __( 'Secondary', 'palltheme' ), 'color' => palltheme_mod( 'color_secondary' ) ),
		array( '_id' => 'text', 'title' => __( 'Text', 'palltheme' ), 'color' => '#334155' ),
		array( '_id' => 'accent', 'title' => __( 'Accent', 'palltheme' ), 'color' => palltheme_mod( 'color_accent' ) ),
	);

	$custom = array();
	foreach ( array( 'dark', 'light', 'white', 'success', 'warning', 'danger' ) as $name ) {
		$custom[] = array(
			'_id'   => 'pt_' . $name,
			'title' => ucfirst( $name ),
			'color' => palltheme_mod( 'color_' . $name ),
		);
	}
	// Keep user-made custom colors that are not ours.
	foreach ( (array) ( $settings['custom_colors'] ?? array() ) as $color ) {
		if ( isset( $color['_id'] ) && ! str_starts_with( (string) $color['_id'], 'pt_' ) ) {
			$custom[] = $color;
		}
	}
	$settings['custom_colors'] = $custom;

	$heading = 'system' === palltheme_mod( 'font_source' ) ? '' : palltheme_mod( 'font_heading' );
	$body    = 'system' === palltheme_mod( 'font_source' ) ? '' : palltheme_mod( 'font_body' );
	$settings['system_typography'] = array(
		array( '_id' => 'primary', 'title' => __( 'Primary', 'palltheme' ), 'typography_typography' => 'custom', 'typography_font_family' => $heading, 'typography_font_weight' => '700' ),
		array( '_id' => 'secondary', 'title' => __( 'Secondary', 'palltheme' ), 'typography_typography' => 'custom', 'typography_font_family' => $heading, 'typography_font_weight' => '600' ),
		array( '_id' => 'text', 'title' => __( 'Text', 'palltheme' ), 'typography_typography' => 'custom', 'typography_font_family' => $body, 'typography_font_weight' => '400' ),
		array( '_id' => 'accent', 'title' => __( 'Accent', 'palltheme' ), 'typography_typography' => 'custom', 'typography_font_family' => $body, 'typography_font_weight' => '600' ),
	);
	// Match the theme container width.
	$settings['container_width'] = array( 'unit' => 'px', 'size' => 1240 );

	update_post_meta( $kit_id, '_elementor_page_settings', $settings );

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}
add_action( 'customize_save_after', 'palltheme_sync_elementor_kit' );
add_action( 'after_switch_theme', 'palltheme_sync_elementor_kit' );

/**
 * Elementor's default page width/colors would fight the theme on first
 * activation; disable its default colors/fonts schemes.
 */
function palltheme_elementor_defaults(): void {
	if ( get_option( 'palltheme_elementor_defaults_set' ) ) {
		return;
	}
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
	update_option( 'elementor_cpt_support', array( 'page', 'post', 'pall_service', 'pall_solution', 'pall_case_study', 'pall_team' ) );
	update_option( 'palltheme_elementor_defaults_set', 1 );
}
add_action( 'after_switch_theme', 'palltheme_elementor_defaults' );
