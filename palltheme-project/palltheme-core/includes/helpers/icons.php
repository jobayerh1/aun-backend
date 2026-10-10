<?php
/**
 * Built-in content icon set (original line icons, 24px grid).
 * Only the icons actually rendered on a page are output — no icon font.
 * Admins can also upload their own SVG/PNG icon per post.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icon library: name => [label, svg inner markup].
 *
 * @return array<string,array{0:string,1:string}>
 */
function pallcore_icon_library(): array {
	return array(
		'server'     => array( __( 'Server', 'palltheme-core' ), '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6"/>' ),
		'network'    => array( __( 'Network', 'palltheme-core' ), '<rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v4M5 17v-3h14v3M12 11v3"/>' ),
		'cloud'      => array( __( 'Cloud', 'palltheme-core' ), '<path d="M7 18h10a4.5 4.5 0 0 0 .6-8.96A6 6 0 0 0 6.1 10.1 4 4 0 0 0 7 18Z"/>' ),
		'shield'     => array( __( 'Security', 'palltheme-core' ), '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>' ),
		'lock'       => array( __( 'Lock', 'palltheme-core' ), '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2"/>' ),
		'cpu'        => array( __( 'Processor', 'palltheme-core' ), '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>' ),
		'database'   => array( __( 'Database / Storage', 'palltheme-core' ), '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>' ),
		'code'       => array( __( 'Software', 'palltheme-core' ), '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>' ),
		'ai'         => array( __( 'AI', 'palltheme-core' ), '<path d="M12 3a3 3 0 0 0-3 3v.2A3 3 0 0 0 6 9a3 3 0 0 0 .5 1.7A3 3 0 0 0 6 16a3 3 0 0 0 3 3 3 3 0 0 0 3 2 3 3 0 0 0 3-2 3 3 0 0 0 3-3 3 3 0 0 0-.5-5.3A3 3 0 0 0 18 9a3 3 0 0 0-3-2.8V6a3 3 0 0 0-3-3Z"/><path d="M12 3v18M9 9h1.5M13.5 15H15M9 14h1M14 9h1"/>' ),
		'iot'        => array( __( 'IoT', 'palltheme-core' ), '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/>' ),
		'headset'    => array( __( 'Support', 'palltheme-core' ), '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19a3 3 0 0 1-3 3h-3"/>' ),
		'backup'     => array( __( 'Backup', 'palltheme-core' ), '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>' ),
		'chart'      => array( __( 'Analytics', 'palltheme-core' ), '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-7"/>' ),
		'globe'      => array( __( 'Globe', 'palltheme-core' ), '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>' ),
		'wifi'       => array( __( 'Wireless', 'palltheme-core' ), '<path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1"/>' ),
		'router'     => array( __( 'Router', 'palltheme-core' ), '<rect x="2" y="13" width="20" height="7" rx="2"/><path d="M6 16.5h.01M10 16.5h.01M17 13V9M14 6.5a4 4 0 0 1 6 0"/>' ),
		'rack'       => array( __( 'Data Center', 'palltheme-core' ), '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M5 7h14M5 12h14M5 17h14M8 4.5h.01M8 9.5h.01M8 14.5h.01M8 19.5h.01"/>' ),
		'fiber'      => array( __( 'Fiber Optics', 'palltheme-core' ), '<path d="M3 20c4 0 5-4 9-8s5-8 9-8"/><path d="M3 14c3 0 4-2 6-4M15 20c2-2 3-4 6-4"/><circle cx="21" cy="4" r="1"/><circle cx="3" cy="20" r="1"/>' ),
		'hosting'    => array( __( 'Hosting / VPS', 'palltheme-core' ), '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4M7 8h4M7 11h7"/>' ),
		'consult'    => array( __( 'Consultancy', 'palltheme-core' ), '<path d="M21 12a8 8 0 0 1-11.7 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/>' ),
		'settings'   => array( __( 'Managed Services', 'palltheme-core' ), '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>' ),
		'users'      => array( __( 'Team', 'palltheme-core' ), '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>' ),
		'building'   => array( __( 'Enterprise', 'palltheme-core' ), '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>' ),
		'bank'       => array( __( 'Banking', 'palltheme-core' ), '<path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/>' ),
		'health'     => array( __( 'Healthcare', 'palltheme-core' ), '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 5.6 5 5 0 0 1 21.2 11c-2.2 4.6-9.2 9-9.2 9Z"/><path d="M8 12h2.5l1-2 2 4 1-2H16"/>' ),
		'education'  => array( __( 'Education', 'palltheme-core' ), '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5M22 9v6"/>' ),
		'government' => array( __( 'Government', 'palltheme-core' ), '<path d="M12 2v4M8 6h8M5 10h14M4 21h16M6 10v8M10 10v8M14 10v8M18 10v8"/>' ),
		'retail'     => array( __( 'Retail', 'palltheme-core' ), '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.76L21 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17.5" cy="20" r="1.3"/>' ),
		'factory'    => array( __( 'Manufacturing', 'palltheme-core' ), '<path d="M3 21V10l6 4V10l6 4V6l6 3v12H3Z"/><path d="M7 17h2M12 17h2M17 17h1"/>' ),
		'telecom'    => array( __( 'Telecom / ISP', 'palltheme-core' ), '<path d="M12 10v12M8.5 22h7M12 10l-4 12M12 10l4 12"/><circle cx="12" cy="7" r="2"/><path d="M7.8 2.8a6 6 0 0 0 0 8.4M16.2 2.8a6 6 0 0 1 0 8.4"/>' ),
		'rocket'     => array( __( 'Transformation', 'palltheme-core' ), '<path d="M5 15c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2-.1-2.8a2.1 2.1 0 0 0-2.9-.2Z"/><path d="M12 15l-3-3a22 22 0 0 1 2-4A12.9 12.9 0 0 1 22 2c0 2.7-.8 7.5-6 11a22.4 22.4 0 0 1-4 2Z"/><path d="M9 12H4s.6-3 2-4c1.6-1.1 5 0 5 0M12 15v5s3-.6 4-2c1.1-1.6 0-5 0-5"/>' ),
		'layers'     => array( __( 'Layers', 'palltheme-core' ), '<path d="m12 2 10 5-10 5L2 7l10-5Z"/><path d="m2 12 10 5 10-5M2 17l10 5 10-5"/>' ),
		'terminal'   => array( __( 'Terminal / Linux', 'palltheme-core' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/>' ),
		'switch'     => array( __( 'Switch', 'palltheme-core' ), '<rect x="2" y="7" width="20" height="10" rx="2"/><path d="M6 12h.01M9 12h.01M12 12h.01M15 12h.01M18 12h.01"/>' ),
		'firewall'   => array( __( 'Firewall', 'palltheme-core' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 14h18M9 4v5M15 9v5M9 14v6"/>' ),
		'license'    => array( __( 'License / Software', 'palltheme-core' ), '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h4"/><circle cx="16" cy="16" r="2"/>' ),
		'power'      => array( __( 'Power / UPS', 'palltheme-core' ), '<path d="M13 3 5 13h6l-1 8 8-10h-6l1-8Z"/>' ),
		'speed'      => array( __( 'Performance', 'palltheme-core' ), '<path d="M12 14l4-4"/><path d="M3.3 19a10 10 0 1 1 17.4 0"/>' ),
		'check'      => array( __( 'Check', 'palltheme-core' ), '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>' ),
	);
}

/**
 * Render a library icon.
 */
function pallcore_icon( string $name, string $class = '' ): string {
	$lib  = pallcore_icon_library();
	$icon = $lib[ $name ] ?? $lib['cpu'];
	return '<svg class="pt-icon ' . esc_attr( $class ) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icon[1] . '</svg>';
}

/**
 * Choices for icon select fields.
 *
 * @return array<string,string>
 */
function pallcore_icon_choices(): array {
	$out = array();
	foreach ( pallcore_icon_library() as $key => $icon ) {
		$out[ $key ] = $icon[0];
	}
	return $out;
}
