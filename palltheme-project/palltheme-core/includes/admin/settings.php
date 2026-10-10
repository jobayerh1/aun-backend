<?php
/**
 * Business Settings (Palltheme → Business Settings).
 *
 * Contact details, social links, homepage hero, statistics, CTA, chat
 * buttons, contact form, maintenance mode. Stored in one option
 * (`pallcore_settings`) so it survives theme changes.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings tabs.
 *
 * @return array<string,string>
 */
function pallcore_settings_tabs(): array {
	return array(
		'company'     => __( 'Company & Contact', 'palltheme-core' ),
		'social'      => __( 'Social Media', 'palltheme-core' ),
		'hero'        => __( 'Homepage Hero', 'palltheme-core' ),
		'stats'       => __( 'Statistics & CTA', 'palltheme-core' ),
		'chat'        => __( 'Chat Buttons', 'palltheme-core' ),
		'form'        => __( 'Contact Form', 'palltheme-core' ),
		'newsletter'  => __( 'Newsletter', 'palltheme-core' ),
		'maintenance' => __( 'Maintenance Mode', 'palltheme-core' ),
		'advanced'    => __( 'Advanced', 'palltheme-core' ),
	);
}

/**
 * Setting fields: key => [tab, type, label, help].
 *
 * @return array<string,array{0:string,1:string,2:string,3?:string}>
 */
function pallcore_settings_fields(): array {
	return array(
		// Company & contact.
		'company_name'        => array( 'company', 'text', __( 'Company name', 'palltheme-core' ), __( 'Leave empty to use the Site Title.', 'palltheme-core' ) ),
		'phone'               => array( 'company', 'text', __( 'Phone', 'palltheme-core' ) ),
		'email'               => array( 'company', 'email', __( 'Email', 'palltheme-core' ) ),
		'address'             => array( 'company', 'textarea', __( 'Address', 'palltheme-core' ) ),
		'hours'               => array( 'company', 'textarea', __( 'Business hours', 'palltheme-core' ), __( 'One per line: Day | Hours, e.g. "Mon – Fri | 9:00 – 18:00".', 'palltheme-core' ) ),
		'hours_short'         => array( 'company', 'text', __( 'Short hours (top bar)', 'palltheme-core' ), __( 'e.g. "Mon – Fri 9:00 – 18:00 · 24/7 NOC"', 'palltheme-core' ) ),
		'map_query'           => array( 'company', 'text', __( 'Google Maps location', 'palltheme-core' ), __( 'An address or place name. The map is loaded only after the visitor clicks it (privacy + speed).', 'palltheme-core' ) ),
		'whatsapp'            => array( 'company', 'text', __( 'WhatsApp number', 'palltheme-core' ), __( 'International format, e.g. +8801XXXXXXXXX.', 'palltheme-core' ) ),
		'telegram'            => array( 'company', 'text', __( 'Telegram username', 'palltheme-core' ), __( 'Without @.', 'palltheme-core' ) ),
		'messenger'           => array( 'company', 'text', __( 'Facebook Messenger page username', 'palltheme-core' ) ),
		'careers_email'       => array( 'company', 'email', __( 'Careers email', 'palltheme-core' ) ),
		'quote_url'           => array( 'company', 'url', __( '“Get a Quote” link', 'palltheme-core' ), __( 'Used by call-to-action buttons. Defaults to the Contact page.', 'palltheme-core' ) ),

		// Social.
		'social_facebook'     => array( 'social', 'url', 'Facebook' ),
		'social_instagram'    => array( 'social', 'url', 'Instagram' ),
		'social_linkedin'     => array( 'social', 'url', 'LinkedIn' ),
		'social_youtube'      => array( 'social', 'url', 'YouTube' ),
		'social_x'            => array( 'social', 'url', 'X (Twitter)' ),
		'social_tiktok'       => array( 'social', 'url', 'TikTok' ),

		// Hero.
		'hero_eyebrow'        => array( 'hero', 'text', __( 'Small label above the heading', 'palltheme-core' ) ),
		'hero_title'          => array( 'hero', 'text', __( 'Heading', 'palltheme-core' ), __( 'Wrap words in *asterisks* to highlight them, e.g. "Powering the Future with *Intelligent Technology*".', 'palltheme-core' ) ),
		'hero_text'           => array( 'hero', 'textarea', __( 'Subheading', 'palltheme-core' ) ),
		'hero_btn1_text'      => array( 'hero', 'text', __( 'Primary button text', 'palltheme-core' ) ),
		'hero_btn1_url'       => array( 'hero', 'url', __( 'Primary button link', 'palltheme-core' ) ),
		'hero_btn2_text'      => array( 'hero', 'text', __( 'Secondary button text', 'palltheme-core' ) ),
		'hero_btn2_url'       => array( 'hero', 'url', __( 'Secondary button link', 'palltheme-core' ) ),
		'hero_image'          => array( 'hero', 'image', __( 'Hero image', 'palltheme-core' ) ),
		'hero_cards'          => array( 'hero', 'textarea', __( 'Floating cards', 'palltheme-core' ), __( 'Up to 3 lines: Value | Label | icon, e.g. "24/7 | Monitoring | headset". Icons: server, cloud, shield, network, speed, headset…', 'palltheme-core' ) ),
		'hero_video_mp4'      => array( 'hero', 'url', __( 'Background video (MP4, optional)', 'palltheme-core' ), __( 'Desktop only, muted, loaded after the page. Keep it under ~4 MB.', 'palltheme-core' ) ),
		'hero_video_webm'     => array( 'hero', 'url', __( 'Background video (WebM, optional)', 'palltheme-core' ) ),
		'hero_poster'         => array( 'hero', 'image', __( 'Video poster / mobile background image', 'palltheme-core' ) ),
		'hero_lottie'         => array( 'hero', 'url', __( 'Lottie animation JSON URL (optional, replaces the hero image)', 'palltheme-core' ) ),

		// Stats & CTA.
		'stats'               => array( 'stats', 'textarea', __( 'Statistics', 'palltheme-core' ), __( 'One per line: Value | Label, e.g. "500+ | Projects Delivered". Numbers animate automatically.', 'palltheme-core' ) ),
		'trust_items'         => array( 'stats', 'textarea', __( 'Trust / why-us items', 'palltheme-core' ), __( 'One per line: icon | Title | Short text. Used by the "Features / Trust" widget when it has no items of its own. Icons: shield, headset, speed, layers, users, check, lock, server, cloud…', 'palltheme-core' ) ),
		'cta_title'           => array( 'stats', 'text', __( 'CTA heading', 'palltheme-core' ) ),
		'cta_text'            => array( 'stats', 'textarea', __( 'CTA text', 'palltheme-core' ) ),
		'cta_button'          => array( 'stats', 'text', __( 'CTA button text', 'palltheme-core' ) ),

		// Chat.
		'chat_whatsapp'       => array( 'chat', 'checkbox', __( 'Show WhatsApp button', 'palltheme-core' ) ),
		'chat_telegram'       => array( 'chat', 'checkbox', __( 'Show Telegram button', 'palltheme-core' ) ),
		'chat_messenger'      => array( 'chat', 'checkbox', __( 'Show Messenger button', 'palltheme-core' ) ),
		'chat_phone'          => array( 'chat', 'checkbox', __( 'Show Call button (mobile)', 'palltheme-core' ) ),
		'chat_live_url'       => array( 'chat', 'url', __( 'Live chat link (optional)', 'palltheme-core' ), __( 'If you use a live-chat plugin (Tawk.to, Crisp…) it adds its own bubble — leave this empty.', 'palltheme-core' ) ),
		'chat_message'        => array( 'chat', 'text', __( 'Pre-filled WhatsApp message', 'palltheme-core' ) ),
		'chat_label'          => array( 'chat', 'text', __( 'Button label', 'palltheme-core' ) ),

		// Form.
		'form_shortcode'      => array( 'form', 'text', __( 'Form plugin shortcode', 'palltheme-core' ), __( 'Paste a Fluent Forms / WPForms / Contact Form 7 shortcode to use it on the contact page. Leave empty to use the built-in lightweight form.', 'palltheme-core' ) ),
		'form_recipient'      => array( 'form', 'email', __( 'Built-in form: send to', 'palltheme-core' ), __( 'Defaults to the Email above, then the site admin email.', 'palltheme-core' ) ),
		'form_success'        => array( 'form', 'text', __( 'Built-in form: success message', 'palltheme-core' ) ),
		'form_privacy'        => array( 'form', 'checkbox', __( 'Built-in form: require privacy-policy consent', 'palltheme-core' ) ),

		// Newsletter.
		'newsletter_enabled'  => array( 'newsletter', 'checkbox', __( 'Show newsletter sign-up in the footer', 'palltheme-core' ) ),
		'newsletter_text'     => array( 'newsletter', 'text', __( 'Short text above the field', 'palltheme-core' ) ),
		'newsletter_shortcode' => array( 'newsletter', 'text', __( 'Newsletter plugin shortcode (optional)', 'palltheme-core' ), __( 'Paste a shortcode from MailPoet, FluentCRM, Mailchimp for WP, etc. Leave empty to use the built-in form: sign-ups are listed under Theme Settings → Subscribers with a CSV export.', 'palltheme-core' ) ),

		// Maintenance.
		'maint_enabled'       => array( 'maintenance', 'checkbox', __( 'Enable maintenance mode', 'palltheme-core' ), __( 'Logged-in administrators still see the site. Visitors get a 503 "back soon" page. If you use a dedicated maintenance plugin, leave this off.', 'palltheme-core' ) ),
		'maint_title'         => array( 'maintenance', 'text', __( 'Heading', 'palltheme-core' ) ),
		'maint_text'          => array( 'maintenance', 'textarea', __( 'Message', 'palltheme-core' ) ),
		'maint_until'         => array( 'maintenance', 'datetime-local', __( 'Back online at (countdown)', 'palltheme-core' ) ),

		// Advanced.
		'search_suggestions'  => array( 'advanced', 'text', __( 'Popular searches (comma separated)', 'palltheme-core' ) ),
		'search_types'        => array( 'advanced', 'text', __( 'Live search content types', 'palltheme-core' ), __( 'Comma separated: product, pall_service, pall_solution, post, pall_case_study', 'palltheme-core' ) ),
		'intro_pall_service'  => array( 'advanced', 'textarea', __( 'Services archive intro', 'palltheme-core' ) ),
		'intro_pall_solution' => array( 'advanced', 'textarea', __( 'Solutions archive intro', 'palltheme-core' ) ),
		'intro_pall_case_study' => array( 'advanced', 'textarea', __( 'Case studies archive intro', 'palltheme-core' ) ),
		'intro_pall_team'     => array( 'advanced', 'textarea', __( 'Team archive intro', 'palltheme-core' ) ),
		'home_sections'       => array( 'advanced', 'textarea', __( 'Default homepage sections (order)', 'palltheme-core' ), __( 'Used only when the homepage is not built with Elementor. One per line: hero, clients, services, stats, solutions, products, process, cases, technologies, testimonials, pricing, blog, faq, cta', 'palltheme-core' ) ),
	);
}

/**
 * Default values.
 *
 * @return array<string,mixed>
 */
function pallcore_setting_defaults(): array {
	return array(
		'hero_eyebrow'      => __( 'Enterprise IT · Cloud · Security', 'palltheme-core' ),
		'hero_title'        => __( 'Powering the Future with *Intelligent Technology*', 'palltheme-core' ),
		'hero_text'         => __( 'Enterprise-grade infrastructure, cloud, networking, cybersecurity and digital solutions designed for modern businesses.', 'palltheme-core' ),
		'hero_btn1_text'    => __( 'Explore Solutions', 'palltheme-core' ),
		'hero_btn2_text'    => __( 'Contact Us', 'palltheme-core' ),
		'hero_cards'        => "99.99% | Uptime target | speed\n24/7 | Monitoring | headset\n15+ | Years of experience | check",
		'stats'             => "500+ | Projects Delivered\n99.99% | Infrastructure Uptime\n24/7 | Technical Support\n15+ | Years Experience",
		'trust_items'       => "shield | Secure infrastructure | Security designed in from day one, not bolted on.\nheadset | 24/7 support | Engineers on call around the clock for critical systems.\nspeed | Fast deployment | Pre-staged hardware and proven runbooks shorten go-live.\nlayers | Scalable solutions | Architectures sized for today with room to grow.\nusers | Professional engineers | Specialists in networking, cloud and security.\ncheck | Quality assurance | Every system is tested and documented before handover.",
		'newsletter_enabled' => '1',
		'newsletter_text'   => __( 'Monthly insights on infrastructure, cloud and security. No spam.', 'palltheme-core' ),
		'cta_title'         => __( 'Ready to modernize your infrastructure?', 'palltheme-core' ),
		'cta_text'          => __( 'Talk to our engineers for a free assessment and a tailored proposal.', 'palltheme-core' ),
		'cta_button'        => __( 'Get a Quote', 'palltheme-core' ),
		'chat_message'      => __( 'Hello! I would like to know more about your services.', 'palltheme-core' ),
		'chat_label'        => __( 'Chat with us', 'palltheme-core' ),
		'form_success'      => __( 'Thank you! Your message has been sent. We will reply within one business day.', 'palltheme-core' ),
		'form_privacy'      => '1',
		'maint_title'       => __( 'We’re upgrading our systems', 'palltheme-core' ),
		'maint_text'        => __( 'Scheduled maintenance is in progress to make things faster and more secure. We’ll be back shortly.', 'palltheme-core' ),
		'search_types'      => 'product, pall_service, pall_solution, post, pall_case_study',
		'home_sections'     => "hero\nclients\nservices\nstats\nsolutions\nproducts\nprocess\ncases\ntechnologies\ntestimonials\nblog\ncta",
	);
}

/**
 * Register the option with a sanitize callback.
 */
function pallcore_register_settings(): void {
	register_setting(
		'pallcore_settings',
		'pallcore_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'pallcore_sanitize_settings',
			'default'           => array(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'pallcore_register_settings' );

/**
 * Sanitize settings. Only fields of the submitted tab are replaced;
 * others are kept.
 *
 * @param mixed $input Raw input.
 */
function pallcore_sanitize_settings( $input ): array {
	$current = (array) get_option( 'pallcore_settings', array() );
	$input   = is_array( $input ) ? $input : array();
	$tab     = isset( $input['__tab'] ) ? sanitize_key( $input['__tab'] ) : '';

	foreach ( pallcore_settings_fields() as $key => $field ) {
		if ( $tab && $field[0] !== $tab ) {
			continue;
		}
		$raw = $input[ $key ] ?? '';
		switch ( $field[1] ) {
			case 'email':
				$current[ $key ] = sanitize_email( (string) $raw );
				break;
			case 'url':
				$current[ $key ] = esc_url_raw( (string) $raw );
				break;
			case 'textarea':
				$current[ $key ] = sanitize_textarea_field( (string) $raw );
				break;
			case 'checkbox':
				$current[ $key ] = $raw ? '1' : '';
				break;
			case 'image':
				$current[ $key ] = absint( $raw ) ?: '';
				break;
			default:
				$current[ $key ] = sanitize_text_field( (string) $raw );
		}
	}
	return $current;
}

/**
 * Render the settings page.
 */
function pallcore_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = pallcore_settings_tabs();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch only.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'company';
	$tab = array_key_exists( $tab, $tabs ) ? $tab : 'company';

	$values = wp_parse_args( (array) get_option( 'pallcore_settings', array() ), pallcore_setting_defaults() );
	?>
	<div class="wrap pallcore-settings">
		<h1><?php esc_html_e( 'Business Settings', 'palltheme-core' ); ?></h1>
		<p><?php esc_html_e( 'Everything here appears automatically across the website — header, footer, contact page, schema and buttons. Colors, fonts and layout are in Appearance → Customize → Palltheme Settings.', 'palltheme-core' ); ?></p>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'pallcore-settings', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="options.php">
			<?php settings_fields( 'pallcore_settings' ); ?>
			<input type="hidden" name="pallcore_settings[__tab]" value="<?php echo esc_attr( $tab ); ?>">
			<table class="form-table" role="presentation">
				<?php
				foreach ( pallcore_settings_fields() as $key => $field ) :
					if ( $field[0] !== $tab ) {
						continue;
					}
					$name  = 'pallcore_settings[' . $key . ']';
					$id    = 'pallcore-' . $key;
					$value = $values[ $key ] ?? '';
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field[2] ); ?></label></th>
						<td>
							<?php
							switch ( $field[1] ) {
								case 'textarea':
									printf( '<textarea id="%1$s" name="%2$s" class="large-text" rows="4">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
									break;
								case 'checkbox':
									printf( '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ) );
									break;
								case 'image':
									$img = $value ? wp_get_attachment_image( (int) $value, 'thumbnail' ) : '';
									printf(
										'<div class="pallcore-media" data-pallcore-media="single"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><div class="pallcore-media__preview">%4$s</div><button type="button" class="button" data-pallcore-media-pick>%5$s</button> <button type="button" class="button-link-delete" data-pallcore-media-clear>%6$s</button></div>',
										esc_attr( $id ),
										esc_attr( $name ),
										esc_attr( (string) $value ),
										$img, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										esc_html__( 'Select image', 'palltheme-core' ),
										esc_html__( 'Remove', 'palltheme-core' )
									);
									break;
								default:
									printf( '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text">', esc_attr( $field[1] ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
							}
							if ( ! empty( $field[3] ) ) {
								echo '<p class="description">' . esc_html( $field[3] ) . '</p>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Settings page assets (media picker).
 *
 * @param string $hook Hook.
 */
function pallcore_settings_assets( string $hook ): void {
	if ( ! str_contains( $hook, 'pallcore' ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'pallcore-admin', PALLCORE_URL . 'assets/css/admin.css', array(), PALLCORE_VERSION );
	wp_enqueue_script( 'pallcore-admin', PALLCORE_URL . 'assets/js/admin.js', array(), PALLCORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'pallcore_settings_assets' );

/**
 * Parsed statistics rows.
 *
 * @return array<int,array{0:string,1:string}>
 */
function pallcore_stats(): array {
	return pallcore_parse_pairs( (string) pallcore_setting( 'stats' ) );
}
