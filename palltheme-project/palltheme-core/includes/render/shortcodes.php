<?php
/**
 * Shortcodes — thin wrappers around the section renderers.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode tag => renderer.
 *
 * @return array<string,callable>
 */
function pallcore_shortcode_map(): array {
	return array(
		'pall_hero'         => 'pallcore_render_hero',
		'pall_services'     => 'pallcore_render_services',
		'pall_solutions'    => 'pallcore_render_solutions',
		'pall_case_studies' => 'pallcore_render_case_studies',
		'pall_team'         => 'pallcore_render_team',
		'pall_testimonials' => 'pallcore_render_testimonials',
		'pall_clients'      => 'pallcore_render_clients',
		'pall_technologies' => 'pallcore_render_technologies',
		'pall_stats'        => 'pallcore_render_stats',
		'pall_products'     => 'pallcore_render_products',
		'pall_posts'        => 'pallcore_render_posts',
		'pall_pricing'      => 'pallcore_render_pricing',
		'pall_faq'          => 'pallcore_render_faq',
		'pall_jobs'         => 'pallcore_render_jobs',
		'pall_process'      => 'pallcore_render_process',
		'pall_cta'          => 'pallcore_render_cta',
		'pall_contact'      => 'pallcore_render_contact',
		'pall_contact_form' => 'pallcore_render_contact_form',
		'pall_section_head' => static function ( $atts ) {
			$a = pallcore_atts( array(), $atts );
			return pallcore_section_head( $a );
		},
		'pall_home'         => 'pallcore_default_homepage',
		'pall_features'     => 'pallcore_render_features',
		'pall_split'        => 'pallcore_render_split',
		'pall_media_credits' => 'pallcore_render_media_credits',
		'pall_newsletter'   => static fn() => function_exists( 'pallcore_newsletter_form' ) ? pallcore_newsletter_form() : '',
	);
}

add_action(
	'init',
	static function () {
		foreach ( pallcore_shortcode_map() as $tag => $callback ) {
			add_shortcode(
				$tag,
				static function ( $atts, $content = '' ) use ( $callback ) {
					return call_user_func( $callback, is_array( $atts ) ? $atts : array(), (string) $content );
				}
			);
		}
	}
);
