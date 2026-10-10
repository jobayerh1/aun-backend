<?php
/**
 * Custom post types.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post type definitions: key => [singular, plural, slug, icon, args].
 *
 * @return array<string,array>
 */
function pallcore_post_type_definitions(): array {
	$public = array(
		'public'       => true,
		'has_archive'  => true,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'elementor' ),
	);
	$internal = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'show_in_rest'        => true,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	);

	return array(
		'pall_service'     => array( __( 'Service', 'palltheme-core' ), __( 'Services', 'palltheme-core' ), 'services', 'dashicons-admin-tools', 21, $public, __( 'What you offer: managed IT, networking, cloud, security… Each service gets its own page.', 'palltheme-core' ) ),
		'pall_solution'    => array( __( 'Solution', 'palltheme-core' ), __( 'Solutions', 'palltheme-core' ), 'solutions', 'dashicons-lightbulb', 22, $public, __( 'Industry solutions (ISP, Banking, Healthcare…).', 'palltheme-core' ) ),
		'pall_case_study'  => array( __( 'Case Study', 'palltheme-core' ), __( 'Case Studies', 'palltheme-core' ), 'case-studies', 'dashicons-portfolio', 23, $public, __( 'Projects you delivered: challenge, solution and results.', 'palltheme-core' ) ),
		'pall_team'        => array( __( 'Team Member', 'palltheme-core' ), __( 'Team', 'palltheme-core' ), 'team', 'dashicons-groups', 24, array_merge( $public, array( 'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'elementor' ) ) ), __( 'People shown on the Team page.', 'palltheme-core' ) ),
		'pall_testimonial' => array( __( 'Testimonial', 'palltheme-core' ), __( 'Testimonials', 'palltheme-core' ), 'testimonials', 'dashicons-format-quote', 25, $internal, __( 'Write the quote in the content box. Featured image = customer photo.', 'palltheme-core' ) ),
		'pall_client'      => array( __( 'Client', 'palltheme-core' ), __( 'Clients', 'palltheme-core' ), 'clients', 'dashicons-building', 26, array_merge( $internal, array( 'supports' => array( 'title', 'thumbnail', 'page-attributes' ) ) ), __( 'Featured image = client logo (transparent PNG/SVG works best).', 'palltheme-core' ) ),
		'pall_technology'  => array( __( 'Technology', 'palltheme-core' ), __( 'Technologies', 'palltheme-core' ), 'technologies', 'dashicons-admin-generic', 27, array_merge( $internal, array( 'supports' => array( 'title', 'thumbnail', 'page-attributes' ) ) ), __( 'Technologies / platforms you work with. Use a logo only when its license allows it; otherwise the short label is shown.', 'palltheme-core' ) ),
		'pall_pricing'     => array( __( 'Pricing Plan', 'palltheme-core' ), __( 'Pricing Plans', 'palltheme-core' ), 'pricing-plans', 'dashicons-money-alt', 28, array_merge( $internal, array( 'supports' => array( 'title', 'excerpt', 'page-attributes' ) ) ), __( 'Plans shown in the pricing table.', 'palltheme-core' ) ),
		'pall_faq'         => array( __( 'FAQ', 'palltheme-core' ), __( 'FAQs', 'palltheme-core' ), 'faqs', 'dashicons-editor-help', 29, array_merge( $internal, array( 'supports' => array( 'title', 'editor', 'page-attributes' ) ) ), __( 'Title = question, content = answer.', 'palltheme-core' ) ),
		'pall_job'         => array( __( 'Job Opening', 'palltheme-core' ), __( 'Careers', 'palltheme-core' ), 'careers/jobs', 'dashicons-id-alt', 30, array_merge( $public, array( 'has_archive' => 'careers/jobs', 'supports' => array( 'title', 'editor', 'excerpt', 'revisions' ) ) ), __( 'Open positions listed on the Careers page.', 'palltheme-core' ) ),
	);
}

/**
 * Register all post types.
 */
function pallcore_register_post_types(): void {
	foreach ( pallcore_post_type_definitions() as $type => $def ) {
		[ $singular, $plural, $slug, $icon, $position, $args, $description ] = $def;

		$labels = array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			/* translators: %s: singular name. */
			'add_new_item'          => sprintf( __( 'Add New %s', 'palltheme-core' ), $singular ),
			/* translators: %s: singular name. */
			'edit_item'             => sprintf( __( 'Edit %s', 'palltheme-core' ), $singular ),
			/* translators: %s: singular name. */
			'new_item'              => sprintf( __( 'New %s', 'palltheme-core' ), $singular ),
			/* translators: %s: singular name. */
			'view_item'             => sprintf( __( 'View %s', 'palltheme-core' ), $singular ),
			/* translators: %s: plural name. */
			'search_items'          => sprintf( __( 'Search %s', 'palltheme-core' ), $plural ),
			/* translators: %s: plural name. */
			'not_found'             => sprintf( __( 'No %s yet', 'palltheme-core' ), strtolower( $plural ) ),
			/* translators: %s: plural name. */
			'all_items'             => sprintf( __( 'All %s', 'palltheme-core' ), $plural ),
			'featured_image'        => 'pall_client' === $type ? __( 'Client logo', 'palltheme-core' ) : ( 'pall_testimonial' === $type ? __( 'Customer photo', 'palltheme-core' ) : ( 'pall_technology' === $type ? __( 'Logo (optional)', 'palltheme-core' ) : __( 'Featured image', 'palltheme-core' ) ) ),
			'set_featured_image'    => __( 'Set image', 'palltheme-core' ),
			'menu_name'             => $plural,
			'archives'              => $plural,
			'items_list'            => $plural,
		);

		register_post_type(
			$type,
			array_merge(
				array(
					'labels'        => $labels,
					'description'   => $description,
					'menu_icon'     => $icon,
					'menu_position' => $position,
					'rewrite'       => array(
						'slug'       => apply_filters( 'pallcore_slug_' . $type, $slug ),
						'with_front' => false,
					),
					'map_meta_cap'  => true,
				),
				$args
			)
		);
	}
}
add_action( 'init', 'pallcore_register_post_types', 5 );

/**
 * Helpful placeholder text for titles.
 */
add_filter(
	'enter_title_here',
	static function ( $text, $post ) {
		$map = array(
			'pall_testimonial' => __( 'Customer name', 'palltheme-core' ),
			'pall_client'      => __( 'Client name', 'palltheme-core' ),
			'pall_technology'  => __( 'Technology name (e.g. Kubernetes)', 'palltheme-core' ),
			'pall_team'        => __( 'Full name', 'palltheme-core' ),
			'pall_faq'         => __( 'Question', 'palltheme-core' ),
			'pall_pricing'     => __( 'Plan name', 'palltheme-core' ),
			'pall_job'         => __( 'Job title', 'palltheme-core' ),
		);
		return $map[ $post->post_type ] ?? $text;
	},
	10,
	2
);

/**
 * Order internal types by menu order on the admin list and in queries.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		$type = $q->get( 'post_type' );
		if ( is_string( $type ) && in_array( $type, pallcore_post_types(), true ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		}
	}
);
