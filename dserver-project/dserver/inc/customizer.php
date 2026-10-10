<?php
/**
 * Company details and header/footer options (Appearance → Customize → DServer).
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Defaults. Contact details are placeholders — replace them in the Customizer.
 */
function dserver_defaults(): array {
	return array(
		'phone'        => '+44 20 7946 0958',
		'email'        => 'hello@dserver.example',
		'address'      => "Unit 4, 120 Server Lane\nLondon EC2A 0DS, United Kingdom",
		'hours'        => '24/7 server support · Sales Mon–Fri 9:00–18:00',
		'footer_text'  => 'DServer Technology runs fast NVMe VPS, dedicated servers and managed cloud from data centers in Frankfurt, Amsterdam and London — backed by engineers 24/7.',
		'cta_text'     => 'Get a VPS',
		'cta_url'      => '/vps-hosting/',
		'topbar'       => true,
		'facebook'     => 'https://facebook.com/',
		'linkedin'     => 'https://linkedin.com/',
		'x'            => 'https://x.com/',
		'github'       => 'https://github.com/',
		'copyright'    => '© {year} DServer Technology. All rights reserved.',
		'status_text'  => 'All systems operational',
	);
}

/**
 * Get an option.
 *
 * @param string $key Key.
 * @return mixed
 */
function dserver_opt( string $key ) {
	$d = dserver_defaults();
	return get_theme_mod( 'ds_' . $key, $d[ $key ] ?? '' );
}

/**
 * Register Customizer controls.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function dserver_customize( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section( 'dserver', array( 'title' => __( 'DServer: company & header/footer', 'dserver' ), 'priority' => 30 ) );
	$fields = array(
		'phone'       => array( __( 'Phone', 'dserver' ), 'text' ),
		'email'       => array( __( 'Email', 'dserver' ), 'email' ),
		'address'     => array( __( 'Address', 'dserver' ), 'textarea' ),
		'hours'       => array( __( 'Opening / support hours (top bar)', 'dserver' ), 'text' ),
		'status_text' => array( __( 'Status label (top bar)', 'dserver' ), 'text' ),
		'topbar'      => array( __( 'Show top bar', 'dserver' ), 'checkbox' ),
		'cta_text'    => array( __( 'Header button text', 'dserver' ), 'text' ),
		'cta_url'     => array( __( 'Header button link', 'dserver' ), 'text' ),
		'footer_text' => array( __( 'Footer description', 'dserver' ), 'textarea' ),
		'copyright'   => array( __( 'Copyright ({year} and {site} are replaced)', 'dserver' ), 'text' ),
		'facebook'    => array( 'Facebook URL', 'url' ),
		'linkedin'    => array( 'LinkedIn URL', 'url' ),
		'x'           => array( 'X (Twitter) URL', 'url' ),
		'github'      => array( 'GitHub URL', 'url' ),
	);
	$d = dserver_defaults();
	foreach ( $fields as $key => [ $label, $type ] ) {
		$wp_customize->add_setting(
			'ds_' . $key,
			array(
				'default'           => $d[ $key ],
				'sanitize_callback' => 'checkbox' === $type ? 'wp_validate_boolean' : ( 'textarea' === $type ? 'sanitize_textarea_field' : ( 'url' === $type ? 'esc_url_raw' : 'sanitize_text_field' ) ),
			)
		);
		$wp_customize->add_control( 'ds_' . $key, array( 'label' => $label, 'section' => 'dserver', 'type' => $type ) );
	}
}
add_action( 'customize_register', 'dserver_customize' );

/**
 * Link for a CTA value that may be relative.
 *
 * @param string $url URL or path.
 */
function dserver_link( string $url ): string {
	return str_starts_with( $url, '/' ) ? home_url( $url ) : $url;
}
