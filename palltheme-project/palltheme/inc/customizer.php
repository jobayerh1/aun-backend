<?php
/**
 * Customizer: design settings (Appearance → Customize → Palltheme).
 *
 * Every control is declared once in palltheme_customizer_schema() and
 * registered generically, so adding a setting is a one-line change.
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Font choices (all free/open-licensed Google Fonts).
 *
 * @return array<string,string>
 */
function palltheme_font_choices(): array {
	return array(
		'Inter'             => 'Inter',
		'Manrope'           => 'Manrope',
		'Plus Jakarta Sans' => 'Plus Jakarta Sans',
		'Space Grotesk'     => 'Space Grotesk',
	);
}

/**
 * Control schema grouped by section.
 *
 * @return array<string,array{title:string,priority:int,controls:array}>
 */
function palltheme_customizer_schema(): array {
	$bool = array( 'type' => 'checkbox' );

	return array(
		'palltheme_branding'    => array(
			'title'    => __( 'Branding & Logos', 'palltheme' ),
			'priority' => 10,
			'controls' => array(
				'logo_light'  => array( 'type' => 'image', 'label' => __( 'Light logo (used on dark/transparent headers & footer)', 'palltheme' ) ),
				'logo_dark'   => array( 'type' => 'image', 'label' => __( 'Logo for dark mode', 'palltheme' ) ),
				'logo_sticky' => array( 'type' => 'image', 'label' => __( 'Sticky header logo (optional)', 'palltheme' ) ),
				'logo_height' => array( 'type' => 'number', 'label' => __( 'Logo height (px)', 'palltheme' ), 'min' => 20, 'max' => 120 ),
			),
		),
		'palltheme_colors'      => array(
			'title'       => __( 'Global Colors', 'palltheme' ),
			'priority'    => 20,
			'description' => __( 'These colors are used across the whole site and are synced to Elementor → Site Settings → Global Colors automatically.', 'palltheme' ),
			'controls'    => array(
				'color_primary'   => array( 'type' => 'color', 'label' => __( 'Primary', 'palltheme' ) ),
				'color_secondary' => array( 'type' => 'color', 'label' => __( 'Secondary', 'palltheme' ) ),
				'color_accent'    => array( 'type' => 'color', 'label' => __( 'Accent', 'palltheme' ) ),
				'color_dark'      => array( 'type' => 'color', 'label' => __( 'Dark', 'palltheme' ) ),
				'color_light'     => array( 'type' => 'color', 'label' => __( 'Light', 'palltheme' ) ),
				'color_white'     => array( 'type' => 'color', 'label' => __( 'White', 'palltheme' ) ),
				'color_success'   => array( 'type' => 'color', 'label' => __( 'Success', 'palltheme' ) ),
				'color_warning'   => array( 'type' => 'color', 'label' => __( 'Warning', 'palltheme' ) ),
				'color_danger'    => array( 'type' => 'color', 'label' => __( 'Danger', 'palltheme' ) ),
			),
		),
		'palltheme_typography'  => array(
			'title'    => __( 'Typography', 'palltheme' ),
			'priority' => 30,
			'controls' => array(
				'font_heading'   => array( 'type' => 'select', 'label' => __( 'Heading font', 'palltheme' ), 'choices' => palltheme_font_choices() ),
				'font_body'      => array( 'type' => 'select', 'label' => __( 'Body font', 'palltheme' ), 'choices' => palltheme_font_choices() ),
				'font_source'    => array(
					'type'    => 'select',
					'label'   => __( 'Font loading', 'palltheme' ),
					'choices' => array(
						'google' => __( 'Google Fonts (display=swap)', 'palltheme' ),
						'system' => __( 'System fonts only (fastest, no external request)', 'palltheme' ),
					),
				),
				'base_font_size' => array( 'type' => 'number', 'label' => __( 'Base font size (px)', 'palltheme' ), 'min' => 14, 'max' => 20 ),
			),
		),
		'palltheme_shape'       => array(
			'title'    => __( 'Corners & Buttons', 'palltheme' ),
			'priority' => 40,
			'controls' => array(
				'border_radius' => array( 'type' => 'number', 'label' => __( 'Card corner radius (px)', 'palltheme' ), 'min' => 0, 'max' => 32 ),
				'button_style'  => array(
					'type'    => 'select',
					'label'   => __( 'Button style', 'palltheme' ),
					'choices' => array(
						'rounded' => __( 'Rounded', 'palltheme' ),
						'pill'    => __( 'Pill', 'palltheme' ),
						'square'  => __( 'Square', 'palltheme' ),
					),
				),
			),
		),
		'palltheme_header'      => array(
			'title'    => __( 'Header', 'palltheme' ),
			'priority' => 50,
			'controls' => array(
				'header_style'             => array(
					'type'    => 'select',
					'label'   => __( 'Header style', 'palltheme' ),
					'choices' => array(
						'solid'       => __( 'Solid', 'palltheme' ),
						'transparent' => __( 'Transparent over hero (turns solid on scroll)', 'palltheme' ),
					),
				),
				'header_transparent_pages' => array(
					'type'    => 'select',
					'label'   => __( 'Transparent header applies to', 'palltheme' ),
					'choices' => array(
						'front' => __( 'Homepage only', 'palltheme' ),
						'all'   => __( 'All pages', 'palltheme' ),
					),
				),
				'header_sticky'    => $bool + array( 'label' => __( 'Sticky header', 'palltheme' ) ),
				'header_topbar'    => $bool + array( 'label' => __( 'Show top bar (contact info + social)', 'palltheme' ) ),
				'header_search'    => $bool + array( 'label' => __( 'Show search', 'palltheme' ) ),
				'header_account'   => $bool + array( 'label' => __( 'Show My Account icon', 'palltheme' ) ),
				'header_cart'      => $bool + array( 'label' => __( 'Show cart icon', 'palltheme' ) ),
				'header_wishlist'  => $bool + array( 'label' => __( 'Show wishlist icon (needs a wishlist plugin)', 'palltheme' ) ),
				'header_cta_text'  => array( 'type' => 'text', 'label' => __( 'Header button text (empty = hidden)', 'palltheme' ) ),
				'header_cta_url'   => array( 'type' => 'url', 'label' => __( 'Header button link', 'palltheme' ) ),
			),
		),
		'palltheme_footer'      => array(
			'title'    => __( 'Footer', 'palltheme' ),
			'priority' => 60,
			'controls' => array(
				'footer_layout'           => array(
					'type'    => 'select',
					'label'   => __( 'Footer layout', 'palltheme' ),
					'choices' => array(
						'mega' => __( 'Enterprise: brand, newsletter & contact + 7 link columns', 'palltheme' ),
						'5'    => __( 'Brand + 4 columns', 'palltheme' ),
						'4'    => __( 'Brand + 3 columns', 'palltheme' ),
					),
				),
				'footer_description'      => array( 'type' => 'textarea', 'label' => __( 'Footer description (empty = site tagline)', 'palltheme' ) ),
				'footer_newsletter_title' => array( 'type' => 'text', 'label' => __( 'Newsletter heading', 'palltheme' ) ),
				'footer_payments'         => array( 'type' => 'text', 'label' => __( 'Accepted payments (comma separated, shown as badges)', 'palltheme' ) ),
				'footer_copyright'        => array( 'type' => 'text', 'label' => __( 'Copyright — use {year} and {site}', 'palltheme' ) ),
			),
		),
		'palltheme_darkmode'    => array(
			'title'    => __( 'Dark Mode', 'palltheme' ),
			'priority' => 70,
			'controls' => array(
				'dark_mode_default' => array(
					'type'    => 'select',
					'label'   => __( 'Default appearance', 'palltheme' ),
					'choices' => array(
						'system' => __( 'System default', 'palltheme' ),
						'light'  => __( 'Light', 'palltheme' ),
						'dark'   => __( 'Dark', 'palltheme' ),
					),
				),
				'dark_mode_toggle'  => $bool + array( 'label' => __( 'Let visitors switch (remembers their choice)', 'palltheme' ) ),
			),
		),
		'palltheme_blog'        => array(
			'title'    => __( 'Blog', 'palltheme' ),
			'priority' => 80,
			'controls' => array(
				'blog_layout'       => array(
					'type'    => 'select',
					'label'   => __( 'Archive layout', 'palltheme' ),
					'choices' => array(
						'grid' => __( 'Grid', 'palltheme' ),
						'list' => __( 'List', 'palltheme' ),
					),
				),
				'blog_sidebar'      => $bool + array( 'label' => __( 'Show sidebar', 'palltheme' ) ),
				'blog_reading_time' => $bool + array( 'label' => __( 'Show reading time', 'palltheme' ) ),
				'blog_share'        => $bool + array( 'label' => __( 'Show share buttons', 'palltheme' ) ),
				'blog_author_box'   => $bool + array( 'label' => __( 'Show author box', 'palltheme' ) ),
			),
		),
		'palltheme_shop'        => array(
			'title'    => __( 'Shop', 'palltheme' ),
			'priority' => 90,
			'controls' => array(
				'shop_columns'       => array( 'type' => 'number', 'label' => __( 'Products per row (desktop)', 'palltheme' ), 'min' => 2, 'max' => 5 ),
				'shop_per_page'      => array( 'type' => 'number', 'label' => __( 'Products per page', 'palltheme' ), 'min' => 4, 'max' => 60 ),
				'shop_pagination'    => array(
					'type'    => 'select',
					'label'   => __( 'Pagination', 'palltheme' ),
					'choices' => array(
						'numbers'  => __( 'Page numbers', 'palltheme' ),
						'loadmore' => __( 'Load more button (AJAX)', 'palltheme' ),
					),
				),
				'shop_sidebar'       => $bool + array( 'label' => __( 'Show filter sidebar', 'palltheme' ) ),
				'shop_shipping_note' => array( 'type' => 'textarea', 'label' => __( 'Default shipping info (product page)', 'palltheme' ) ),
				'shop_warranty_note' => array( 'type' => 'textarea', 'label' => __( 'Default warranty info (product page)', 'palltheme' ) ),
			),
		),
		'palltheme_notfound'    => array(
			'title'    => __( '404 Page', 'palltheme' ),
			'priority' => 100,
			'controls' => array(
				'notfound_title' => array( 'type' => 'text', 'label' => __( 'Heading', 'palltheme' ) ),
				'notfound_text'  => array( 'type' => 'textarea', 'label' => __( 'Message', 'palltheme' ) ),
			),
		),
		'palltheme_performance' => array(
			'title'       => __( 'Performance', 'palltheme' ),
			'priority'    => 110,
			'description' => __( 'Switch off visual extras to make the site even lighter. Visitors who ask their device for reduced motion always get a static experience.', 'palltheme' ),
			'controls'    => array(
				'perf_animations'    => $bool + array( 'label' => __( 'Enable animations (scroll reveal, counters, floating cards)', 'palltheme' ) ),
				'perf_videos'        => $bool + array( 'label' => __( 'Enable background videos (desktop only; mobile always gets the poster image)', 'palltheme' ) ),
				'perf_lazyload'      => $bool + array( 'label' => __( 'Native lazy-loading for images and embeds', 'palltheme' ) ),
				'perf_preload_fonts' => $bool + array( 'label' => __( 'Preconnect to the font server', 'palltheme' ) ),
				'perf_block_css'     => $bool + array( 'label' => __( 'Load only the styles of blocks used on each page (instead of the full 140 KB block library)', 'palltheme' ) ),
				'perf_no_emoji'      => $bool + array( 'label' => __( 'Remove the WordPress emoji script (browsers show emoji natively)', 'palltheme' ) ),
			),
		),
	);
}

/**
 * Sanitize a value according to control type.
 *
 * @param string $type    Control type.
 * @param array  $control Control definition.
 * @return callable
 */
function palltheme_sanitize_callback( string $type, array $control ): callable {
	switch ( $type ) {
		case 'color':
			return 'sanitize_hex_color';
		case 'checkbox':
			return static fn( $v ) => (bool) $v;
		case 'number':
			return static function ( $v ) use ( $control ) {
				$v = (int) $v;
				return max( $control['min'] ?? 0, min( $control['max'] ?? 9999, $v ) );
			};
		case 'image':
			return 'absint';
		case 'url':
			return 'esc_url_raw';
		case 'textarea':
			return 'sanitize_textarea_field';
		case 'select':
			return static function ( $v ) use ( $control ) {
				$v = (string) $v;
				return array_key_exists( $v, $control['choices'] ) ? $v : array_key_first( $control['choices'] );
			};
		default:
			return 'sanitize_text_field';
	}
}

/**
 * Register panel, sections, settings and controls.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function palltheme_customize_register( WP_Customize_Manager $wp_customize ): void {
	$defaults = palltheme_defaults();

	$wp_customize->add_panel(
		'palltheme',
		array(
			'title'       => __( 'Palltheme Settings', 'palltheme' ),
			'description' => __( 'Design settings. Business details (phone, email, social links, statistics) are under Palltheme → Business Settings in the admin menu.', 'palltheme' ),
			'priority'    => 20,
		)
	);

	foreach ( palltheme_customizer_schema() as $section_id => $section ) {
		$wp_customize->add_section(
			$section_id,
			array(
				'title'       => $section['title'],
				'priority'    => $section['priority'],
				'panel'       => 'palltheme',
				'description' => $section['description'] ?? '',
			)
		);

		foreach ( $section['controls'] as $key => $control ) {
			$type = $control['type'];
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => $defaults[ $key ] ?? '',
					'sanitize_callback' => palltheme_sanitize_callback( $type, $control ),
					'transport'         => 'refresh',
				)
			);

			$args = array(
				'label'    => $control['label'],
				'section'  => $section_id,
				'settings' => $key,
			);

			if ( 'color' === $type ) {
				$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $key, $args ) );
			} elseif ( 'image' === $type ) {
				$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $key, $args + array( 'mime_type' => 'image' ) ) );
			} else {
				$args['type'] = $type;
				if ( isset( $control['choices'] ) ) {
					$args['choices'] = $control['choices'];
				}
				if ( 'number' === $type ) {
					$args['input_attrs'] = array(
						'min' => $control['min'] ?? 0,
						'max' => $control['max'] ?? 9999,
					);
				}
				$wp_customize->add_control( $key, $args );
			}
		}
	}

}
add_action( 'customize_register', 'palltheme_customize_register', 20 );

/**
 * Convert a hex color to "r g b" for use in rgb(var(--x) / alpha).
 */
function palltheme_hex_to_rgb( string $hex ): string {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '0 0 0';
	}
	return implode( ' ', array_map( 'hexdec', str_split( $hex, 2 ) ) );
}

/**
 * CSS custom properties generated from the Customizer.
 */
function palltheme_dynamic_css(): string {
	$map = array(
		'primary'   => 'color_primary',
		'secondary' => 'color_secondary',
		'accent'    => 'color_accent',
		'dark'      => 'color_dark',
		'light'     => 'color_light',
		'white'     => 'color_white',
		'success'   => 'color_success',
		'warning'   => 'color_warning',
		'danger'    => 'color_danger',
	);

	$vars = array();
	foreach ( $map as $name => $mod ) {
		$hex = (string) palltheme_mod( $mod );
		$vars[] = "--pt-{$name}:{$hex}";
		$vars[] = "--pt-{$name}-rgb:" . palltheme_hex_to_rgb( $hex );
	}

	$stack_sans = 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
	if ( 'system' === palltheme_mod( 'font_source' ) ) {
		$vars[] = '--pt-font-heading:' . $stack_sans;
		$vars[] = '--pt-font-body:' . $stack_sans;
	} else {
		$vars[] = '--pt-font-heading:"' . esc_attr( palltheme_mod( 'font_heading' ) ) . '",' . $stack_sans;
		$vars[] = '--pt-font-body:"' . esc_attr( palltheme_mod( 'font_body' ) ) . '",' . $stack_sans;
	}

	$radius   = (int) palltheme_mod( 'border_radius' );
	$btn      = (string) palltheme_mod( 'button_style' );
	$btn_rad  = 'pill' === $btn ? '999px' : ( 'square' === $btn ? '2px' : max( 6, (int) round( $radius * 0.7 ) ) . 'px' );
	$vars[]   = '--pt-radius:' . $radius . 'px';
	$vars[]   = '--pt-radius-sm:' . max( 0, (int) round( $radius * 0.6 ) ) . 'px';
	$vars[]   = '--pt-radius-btn:' . $btn_rad;
	$vars[]   = '--pt-font-size:' . (int) palltheme_mod( 'base_font_size' ) . 'px';
	$vars[]   = '--pt-logo-h:' . (int) palltheme_mod( 'logo_height' ) . 'px';

	return ':root{' . implode( ';', $vars ) . '}';
}
