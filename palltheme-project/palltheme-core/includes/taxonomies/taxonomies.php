<?php
/**
 * Taxonomies.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register taxonomies.
 */
function pallcore_register_taxonomies(): void {
	$taxes = array(
		'pall_service_cat' => array( __( 'Service Category', 'palltheme-core' ), __( 'Service Categories', 'palltheme-core' ), array( 'pall_service' ), 'service-category', true ),
		'pall_industry'    => array( __( 'Industry', 'palltheme-core' ), __( 'Industries', 'palltheme-core' ), array( 'pall_solution', 'pall_case_study', 'pall_client' ), 'industry', true ),
		'pall_tech_cat'    => array( __( 'Technology Group', 'palltheme-core' ), __( 'Technology Groups', 'palltheme-core' ), array( 'pall_technology' ), 'technology-group', false ),
		'pall_faq_cat'     => array( __( 'FAQ Group', 'palltheme-core' ), __( 'FAQ Groups', 'palltheme-core' ), array( 'pall_faq' ), 'faq-group', false ),
		'pall_department'  => array( __( 'Department', 'palltheme-core' ), __( 'Departments', 'palltheme-core' ), array( 'pall_team', 'pall_job' ), 'department', false ),
	);

	foreach ( $taxes as $tax => [ $singular, $plural, $types, $slug, $public ] ) {
		register_taxonomy(
			$tax,
			$types,
			array(
				'labels'            => array(
					'name'          => $plural,
					'singular_name' => $singular,
					/* translators: %s: singular name. */
					'add_new_item'  => sprintf( __( 'Add New %s', 'palltheme-core' ), $singular ),
					/* translators: %s: singular name. */
					'edit_item'     => sprintf( __( 'Edit %s', 'palltheme-core' ), $singular ),
					/* translators: %s: plural name. */
					'search_items'  => sprintf( __( 'Search %s', 'palltheme-core' ), $plural ),
					'menu_name'     => $plural,
				),
				'hierarchical'      => true,
				'public'            => $public,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => $slug,
					'with_front' => false,
				),
			)
		);
	}
}
add_action( 'init', 'pallcore_register_taxonomies', 6 );
