<?php
/**
 * Custom field definitions for every content type.
 *
 * Field types: text, textarea, html (textarea allowing basic HTML), url,
 * email, number, checkbox, select, icon, image, gallery, lines (one item
 * per line → array), pairs (repeatable two-column rows → array), posts
 * (multi-select of other content), products (multi-select of products).
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reusable CTA fields.
 *
 * @return array<string,array>
 */
function pallcore_cta_fields(): array {
	return array(
		'cta_title' => array( 'type' => 'text', 'label' => __( 'Call-to-action heading', 'palltheme-core' ), 'help' => __( 'Optional. Leave empty to use the site-wide CTA from Business Settings.', 'palltheme-core' ) ),
		'cta_text'  => array( 'type' => 'textarea', 'label' => __( 'Call-to-action text', 'palltheme-core' ) ),
		'cta_url'   => array( 'type' => 'url', 'label' => __( 'Call-to-action link', 'palltheme-core' ) ),
	);
}

/**
 * Meta boxes: post type => [ box id => [title, context, fields] ].
 *
 * @return array<string,array<string,array>>
 */
function pallcore_field_schema(): array {
	$icon = array(
		'icon'       => array( 'type' => 'icon', 'label' => __( 'Icon', 'palltheme-core' ) ),
		'icon_image' => array( 'type' => 'image', 'label' => __( 'Or upload your own icon (SVG/PNG)', 'palltheme-core' ) ),
	);

	$schema = array(
		'pall_service'     => array(
			'pall_service_details' => array(
				__( 'Service details', 'palltheme-core' ),
				'normal',
				$icon + array(
					'features'         => array( 'type' => 'lines', 'label' => __( 'Features / what’s included', 'palltheme-core' ), 'help' => __( 'One per line.', 'palltheme-core' ) ),
					'benefits'         => array( 'type' => 'pairs', 'label' => __( 'Benefits', 'palltheme-core' ), 'cols' => array( __( 'Benefit', 'palltheme-core' ), __( 'Short explanation', 'palltheme-core' ) ) ),
					'technologies'     => array( 'type' => 'lines', 'label' => __( 'Technologies', 'palltheme-core' ), 'help' => __( 'One per line, e.g. Cisco, VMware, Kubernetes.', 'palltheme-core' ) ),
					'gallery'          => array( 'type' => 'gallery', 'label' => __( 'Gallery', 'palltheme-core' ) ),
					'faq'              => array( 'type' => 'pairs', 'label' => __( 'FAQ', 'palltheme-core' ), 'cols' => array( __( 'Question', 'palltheme-core' ), __( 'Answer', 'palltheme-core' ) ) ),
					'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ) ),
					'related_products' => array( 'type' => 'products', 'label' => __( 'Related products', 'palltheme-core' ) ),
				) + pallcore_cta_fields(),
			),
		),
		'pall_solution'    => array(
			'pall_solution_details' => array(
				__( 'Solution details', 'palltheme-core' ),
				'normal',
				$icon + array(
					'problem'          => array( 'type' => 'html', 'label' => __( 'The problem', 'palltheme-core' ), 'help' => __( 'The challenge this industry typically faces.', 'palltheme-core' ) ),
					'approach'         => array( 'type' => 'html', 'label' => __( 'Our solution', 'palltheme-core' ) ),
					'architecture'     => array( 'type' => 'html', 'label' => __( 'Reference architecture', 'palltheme-core' ) ),
					'implementation'   => array( 'type' => 'html', 'label' => __( 'Implementation approach', 'palltheme-core' ) ),
					'features'         => array( 'type' => 'lines', 'label' => __( 'Key capabilities', 'palltheme-core' ), 'help' => __( 'One per line.', 'palltheme-core' ) ),
					'benefits'         => array( 'type' => 'pairs', 'label' => __( 'Benefits / outcomes', 'palltheme-core' ), 'cols' => array( __( 'Benefit', 'palltheme-core' ), __( 'Short explanation', 'palltheme-core' ) ) ),
					'technologies'     => array( 'type' => 'lines', 'label' => __( 'Technologies', 'palltheme-core' ) ),
					'related_cases'    => array( 'type' => 'posts', 'post_type' => 'pall_case_study', 'label' => __( 'Related case studies', 'palltheme-core' ) ),
					'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ) ),
					'related_products' => array( 'type' => 'products', 'label' => __( 'Related products', 'palltheme-core' ) ),
				) + pallcore_cta_fields(),
			),
		),
		'pall_case_study'  => array(
			'pall_case_details' => array(
				__( 'Case study details', 'palltheme-core' ),
				'normal',
				array(
					'client'             => array( 'type' => 'text', 'label' => __( 'Client', 'palltheme-core' ), 'help' => __( 'Client name, or an anonymised description such as “Tier-1 ISP”.', 'palltheme-core' ) ),
					'location'           => array( 'type' => 'text', 'label' => __( 'Location', 'palltheme-core' ) ),
					'duration'           => array( 'type' => 'text', 'label' => __( 'Project duration', 'palltheme-core' ) ),
					'challenge'          => array( 'type' => 'html', 'label' => __( 'Challenge', 'palltheme-core' ) ),
					'solution'           => array( 'type' => 'html', 'label' => __( 'Solution', 'palltheme-core' ) ),
					'implementation'     => array( 'type' => 'html', 'label' => __( 'Implementation', 'palltheme-core' ) ),
					'results'            => array( 'type' => 'pairs', 'label' => __( 'Results / statistics', 'palltheme-core' ), 'cols' => array( __( 'Value (e.g. 99.99%)', 'palltheme-core' ), __( 'Label (e.g. availability)', 'palltheme-core' ) ) ),
					'technologies'       => array( 'type' => 'lines', 'label' => __( 'Technologies used', 'palltheme-core' ) ),
					'gallery'            => array( 'type' => 'gallery', 'label' => __( 'Images', 'palltheme-core' ) ),
					'video'              => array( 'type' => 'url', 'label' => __( 'Video URL (YouTube/Vimeo or .mp4)', 'palltheme-core' ) ),
					'testimonial'        => array( 'type' => 'textarea', 'label' => __( 'Client testimonial', 'palltheme-core' ) ),
					'testimonial_author' => array( 'type' => 'text', 'label' => __( 'Testimonial author (name, role)', 'palltheme-core' ) ),
					'related_services'   => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Services delivered', 'palltheme-core' ) ),
					'related_solutions'  => array( 'type' => 'posts', 'post_type' => 'pall_solution', 'label' => __( 'Related solutions', 'palltheme-core' ) ),
				),
			),
		),
		'pall_team'        => array(
			'pall_team_details' => array(
				__( 'Team member details', 'palltheme-core' ),
				'normal',
				array(
					'position' => array( 'type' => 'text', 'label' => __( 'Position', 'palltheme-core' ) ),
					'email'    => array( 'type' => 'email', 'label' => __( 'Email (optional, shown publicly)', 'palltheme-core' ) ),
					'phone'    => array( 'type' => 'text', 'label' => __( 'Phone (optional, shown publicly)', 'palltheme-core' ) ),
					'skills'   => array( 'type' => 'pairs', 'label' => __( 'Skills', 'palltheme-core' ), 'cols' => array( __( 'Skill', 'palltheme-core' ), __( 'Level 0–100 (optional)', 'palltheme-core' ) ) ),
					'linkedin' => array( 'type' => 'url', 'label' => 'LinkedIn' ),
					'x'        => array( 'type' => 'url', 'label' => 'X' ),
					'facebook' => array( 'type' => 'url', 'label' => 'Facebook' ),
				),
			),
		),
		'pall_testimonial' => array(
			'pall_testimonial_details' => array(
				__( 'Testimonial details', 'palltheme-core' ),
				'normal',
				array(
					'position'     => array( 'type' => 'text', 'label' => __( 'Position', 'palltheme-core' ) ),
					'company'      => array( 'type' => 'text', 'label' => __( 'Company', 'palltheme-core' ) ),
					'rating'       => array( 'type' => 'select', 'label' => __( 'Rating', 'palltheme-core' ), 'choices' => array( '5' => '★★★★★', '4' => '★★★★', '3' => '★★★', '0' => __( 'Hide rating', 'palltheme-core' ) ), 'default' => '5' ),
					'company_logo' => array( 'type' => 'image', 'label' => __( 'Company logo', 'palltheme-core' ) ),
				),
			),
		),
		'pall_client'      => array(
			'pall_client_details' => array(
				__( 'Client details', 'palltheme-core' ),
				'normal',
				array(
					'url'      => array( 'type' => 'url', 'label' => __( 'Website', 'palltheme-core' ) ),
					'industry' => array( 'type' => 'text', 'label' => __( 'Industry (short label)', 'palltheme-core' ) ),
				),
			),
		),
		'pall_technology'  => array(
			'pall_tech_details' => array(
				__( 'Technology details', 'palltheme-core' ),
				'normal',
				array(
					'short' => array( 'type' => 'text', 'label' => __( 'Short label (shown when no logo, max 4 characters, e.g. K8s)', 'palltheme-core' ) ),
					'note'  => array( 'type' => 'text', 'label' => __( 'Caption (e.g. Partner, Certified)', 'palltheme-core' ) ),
					'url'   => array( 'type' => 'url', 'label' => __( 'Link (optional)', 'palltheme-core' ) ),
				),
			),
		),
		'pall_pricing'     => array(
			'pall_pricing_details' => array(
				__( 'Plan details', 'palltheme-core' ),
				'normal',
				array(
					'price'       => array( 'type' => 'text', 'label' => __( 'Price (as displayed, e.g. $49 or Custom)', 'palltheme-core' ) ),
					'period'      => array( 'type' => 'text', 'label' => __( 'Period (e.g. /month)', 'palltheme-core' ) ),
					'features'    => array( 'type' => 'lines', 'label' => __( 'Features', 'palltheme-core' ), 'help' => __( 'One per line.', 'palltheme-core' ) ),
					'featured'    => array( 'type' => 'checkbox', 'label' => __( 'Highlight this plan', 'palltheme-core' ) ),
					'badge'       => array( 'type' => 'text', 'label' => __( 'Badge text (e.g. Most popular)', 'palltheme-core' ) ),
					'button_text' => array( 'type' => 'text', 'label' => __( 'Button text', 'palltheme-core' ), 'default' => __( 'Get started', 'palltheme-core' ) ),
					'button_url'  => array( 'type' => 'url', 'label' => __( 'Button link (a product, checkout or contact page)', 'palltheme-core' ) ),
				),
			),
		),
		'pall_job'         => array(
			'pall_job_details' => array(
				__( 'Job details', 'palltheme-core' ),
				'normal',
				array(
					'location'    => array( 'type' => 'text', 'label' => __( 'Location', 'palltheme-core' ) ),
					'job_type'    => array( 'type' => 'select', 'label' => __( 'Job type', 'palltheme-core' ), 'choices' => array( 'Full-time' => __( 'Full-time', 'palltheme-core' ), 'Part-time' => __( 'Part-time', 'palltheme-core' ), 'Contract' => __( 'Contract', 'palltheme-core' ), 'Internship' => __( 'Internship', 'palltheme-core' ), 'Remote' => __( 'Remote', 'palltheme-core' ) ) ),
					'department'  => array( 'type' => 'text', 'label' => __( 'Department', 'palltheme-core' ) ),
					'salary'      => array( 'type' => 'text', 'label' => __( 'Salary (optional)', 'palltheme-core' ) ),
					'deadline'    => array( 'type' => 'text', 'label' => __( 'Apply by (date)', 'palltheme-core' ) ),
					'apply_url'   => array( 'type' => 'url', 'label' => __( 'Application link (optional)', 'palltheme-core' ) ),
					'apply_email' => array( 'type' => 'email', 'label' => __( 'Or application email', 'palltheme-core' ) ),
				),
			),
		),
		'post'             => array(
			'pall_post_links' => array(
				__( 'Related services & products (Palltheme)', 'palltheme-core' ),
				'normal',
				array(
					'related_services' => array( 'type' => 'posts', 'post_type' => 'pall_service', 'label' => __( 'Related services', 'palltheme-core' ), 'help' => __( 'Shown below the article.', 'palltheme-core' ) ),
					'related_products' => array( 'type' => 'products', 'label' => __( 'Recommended products', 'palltheme-core' ) ),
				),
			),
		),
		'product'          => array(
			'pall_product_tech' => array(
				__( 'Technical details (Palltheme)', 'palltheme-core' ),
				'normal',
				array(
					'specs'         => array( 'type' => 'pairs', 'label' => __( 'Specifications', 'palltheme-core' ), 'cols' => array( __( 'Specification (e.g. CPU)', 'palltheme-core' ), __( 'Details (e.g. Intel Xeon Gold 6430)', 'palltheme-core' ) ), 'help' => __( 'Start a row with "#" to make it a group heading, e.g. "#Networking". Shown in a Specifications tab.', 'palltheme-core' ) ),
					'downloads'     => array( 'type' => 'pairs', 'label' => __( 'Downloads (datasheets, manuals, drivers)', 'palltheme-core' ), 'cols' => array( __( 'Label', 'palltheme-core' ), __( 'File URL (upload in Media, paste link)', 'palltheme-core' ) ) ),
					'video'         => array( 'type' => 'url', 'label' => __( 'Product video URL (YouTube/Vimeo/.mp4)', 'palltheme-core' ) ),
					'warranty'      => array( 'type' => 'text', 'label' => __( 'Warranty (overrides the default text)', 'palltheme-core' ) ),
					'shipping_info' => array( 'type' => 'text', 'label' => __( 'Shipping info (overrides the default text)', 'palltheme-core' ) ),
				),
			),
		),
	);

	return apply_filters( 'pallcore_field_schema', $schema );
}
