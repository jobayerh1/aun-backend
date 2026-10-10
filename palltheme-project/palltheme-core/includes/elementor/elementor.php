<?php
/**
 * Elementor widgets (free Elementor).
 *
 * Every Palltheme section is available under the "Palltheme" category in
 * the Elementor panel. Widgets read live data (services, products, case
 * studies…) on every render — content is never copied into the page.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Category.
 */
add_action(
	'elementor/elements/categories_registered',
	static function ( $manager ) {
		$manager->add_category(
			'palltheme',
			array(
				'title' => __( 'Palltheme', 'palltheme-core' ),
				'icon'  => 'eicon-apps',
			)
		);
	}
);

/**
 * Base widget: controls declared as data, rendering delegated to a renderer.
 */
abstract class Pallcore_Elementor_Widget extends Widget_Base {

	/**
	 * Widget config: name, title, icon, renderer, controls, section (bool).
	 */
	abstract protected function config(): array;

	/** @inheritDoc */
	public function get_name() {
		return 'pall_' . $this->config()['name'];
	}

	/** @inheritDoc */
	public function get_title() {
		return $this->config()['title'];
	}

	/** @inheritDoc */
	public function get_icon() {
		return $this->config()['icon'];
	}

	/** @inheritDoc */
	public function get_categories() {
		return array( 'palltheme' );
	}

	/** @inheritDoc */
	public function get_keywords() {
		return array( 'palltheme', $this->config()['name'] );
	}

	/** Elementor ≥3.x performance: no wrapper div needed for our markup. */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Register controls from config.
	 */
	protected function register_controls() {
		$config = $this->config();

		$this->start_controls_section( 'pall_section_content', array( 'label' => __( 'Content', 'palltheme-core' ) ) );
		foreach ( $config['controls'] as $key => $control ) {
			$this->add_control( $key, $this->map_control( $control ) );
		}
		$this->end_controls_section();

		if ( $config['section'] ?? true ) {
			$this->start_controls_section( 'pall_section_heading', array( 'label' => __( 'Section heading', 'palltheme-core' ) ) );
			$this->add_control( 'eyebrow', array( 'label' => __( 'Small label', 'palltheme-core' ), 'type' => Controls_Manager::TEXT, 'default' => $config['defaults']['eyebrow'] ?? '' ) );
			$this->add_control( 'title', array( 'label' => __( 'Title', 'palltheme-core' ), 'type' => Controls_Manager::TEXT, 'label_block' => true, 'default' => $config['defaults']['title'] ?? '', 'description' => __( 'Wrap words in *asterisks* to highlight them.', 'palltheme-core' ) ) );
			$this->add_control( 'lead', array( 'label' => __( 'Intro text', 'palltheme-core' ), 'type' => Controls_Manager::TEXTAREA, 'default' => $config['defaults']['lead'] ?? '' ) );
			$this->add_control( 'align', array( 'label' => __( 'Alignment', 'palltheme-core' ), 'type' => Controls_Manager::SELECT, 'default' => $config['defaults']['align'] ?? 'left', 'options' => array( 'left' => __( 'Left', 'palltheme-core' ), 'center' => __( 'Center', 'palltheme-core' ) ) ) );
			$this->add_control( 'link_text', array( 'label' => __( 'Link text', 'palltheme-core' ), 'type' => Controls_Manager::TEXT ) );
			$this->add_control( 'link_url', array( 'label' => __( 'Link', 'palltheme-core' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
			$this->end_controls_section();

			$this->start_controls_section( 'pall_section_layout', array( 'label' => __( 'Section layout', 'palltheme-core' ) ) );
			$this->add_control(
				'wrap',
				array(
					'label'        => __( 'Theme section spacing & width', 'palltheme-core' ),
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'Turn off to control spacing and width entirely from the Elementor container.', 'palltheme-core' ),
				)
			);
			$this->add_control(
				'background',
				array(
					'label'   => __( 'Background', 'palltheme-core' ),
					'type'    => Controls_Manager::SELECT,
					'default' => $config['defaults']['background'] ?? 'default',
					'options' => array(
						'default' => __( 'Page background', 'palltheme-core' ),
						'alt'     => __( 'Light tint', 'palltheme-core' ),
						'dark'    => __( 'Dark', 'palltheme-core' ),
					),
				)
			);
			$this->add_control(
				'spacing',
				array(
					'label'   => __( 'Vertical spacing', 'palltheme-core' ),
					'type'    => Controls_Manager::SELECT,
					'default' => 'normal',
					'options' => array(
						'normal' => __( 'Normal', 'palltheme-core' ),
						'tight'  => __( 'Compact', 'palltheme-core' ),
						'none'   => __( 'None', 'palltheme-core' ),
					),
				)
			);
			$this->add_control( 'anchor', array( 'label' => __( 'Section ID (anchor)', 'palltheme-core' ), 'type' => Controls_Manager::TEXT ) );
			$this->end_controls_section();
		}
	}

	/**
	 * Map our compact control definition to Elementor's.
	 *
	 * @param array $c Control.
	 */
	private function map_control( array $c ): array {
		$types = array(
			'text'     => Controls_Manager::TEXT,
			'textarea' => Controls_Manager::TEXTAREA,
			'number'   => Controls_Manager::NUMBER,
			'select'   => Controls_Manager::SELECT,
			'switch'   => Controls_Manager::SWITCHER,
			'media'    => Controls_Manager::MEDIA,
			'url'      => Controls_Manager::URL,
		);
		// URL and media controls store arrays; Elementor fatals on a string default.
		$default = $c['default'] ?? '';
		if ( 'url' === $c['type'] ) {
			$default = array( 'url' => is_string( $default ) ? $default : '', 'is_external' => '', 'nofollow' => '' );
		} elseif ( 'media' === $c['type'] ) {
			$default = array( 'url' => '', 'id' => '' );
		}
		$out = array(
			'label'       => $c['label'],
			'type'        => $types[ $c['type'] ] ?? Controls_Manager::TEXT,
			'default'     => $default,
			'label_block' => in_array( $c['type'], array( 'text', 'textarea', 'url' ), true ),
		);
		if ( isset( $c['options'] ) ) {
			$out['options'] = $c['options'];
		}
		if ( isset( $c['description'] ) ) {
			$out['description'] = $c['description'];
		}
		if ( 'switch' === $c['type'] ) {
			$out['return_value'] = 'yes';
		}
		if ( 'number' === $c['type'] ) {
			$out['min'] = $c['min'] ?? 1;
			$out['max'] = $c['max'] ?? 48;
		}
		if ( in_array( $c['type'], array( 'text', 'textarea', 'url', 'media' ), true ) ) {
			$out['dynamic'] = array( 'active' => true );
		}
		return $out;
	}

	/**
	 * Render via the shared renderer.
	 */
	protected function render() {
		$config   = $this->config();
		$settings = $this->get_settings_for_display();
		$atts     = array();

		foreach ( $config['controls'] as $key => $control ) {
			$value = $settings[ $key ] ?? '';
			// Empty media/URL fields are skipped so renderer defaults (Business Settings) apply.
			if ( 'media' === $control['type'] ) {
				if ( is_array( $value ) && ! empty( $value['id'] ) ) {
					$atts[ $control['att'] ?? $key ] = (string) $value['id'];
				} elseif ( isset( $control['att_url'] ) && is_array( $value ) && ! empty( $value['url'] ) && ! str_contains( (string) $value['url'], 'placeholder' ) ) {
					$atts[ $control['att_url'] ] = $value['url'];
				}
			} elseif ( 'url' === $control['type'] ) {
				$url = is_array( $value ) ? (string) ( $value['url'] ?? '' ) : (string) $value;
				if ( '' !== $url ) {
					$atts[ $key ] = $url;
				}
			} elseif ( 'switch' === $control['type'] ) {
				$atts[ $key ] = 'yes' === $value ? 'yes' : 'no';
			} elseif ( '' !== $value && null !== $value ) {
				$atts[ $key ] = is_scalar( $value ) ? (string) $value : '';
			}
		}

		if ( $config['section'] ?? true ) {
			foreach ( array( 'eyebrow', 'title', 'lead', 'align', 'link_text', 'background', 'spacing' ) as $key ) {
				$atts[ $key ] = (string) ( $settings[ $key ] ?? '' );
			}
			$atts['link_url'] = is_array( $settings['link_url'] ?? null ) ? (string) ( $settings['link_url']['url'] ?? '' ) : '';
			$atts['wrap']     = 'yes' === ( $settings['wrap'] ?? 'yes' ) ? 'yes' : 'no';
			$atts['id']       = sanitize_html_class( (string) ( $settings['anchor'] ?? '' ) );
		}

		$html = call_user_func( $config['renderer'], $atts );
		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			/* translators: %s: widget title. */
			$html = '<div style="padding:24px;border:1px dashed #9ca3af;border-radius:12px;text-align:center;color:#6b7280">' . esc_html( sprintf( __( '%s: nothing to show yet — add some content in the WordPress admin.', 'palltheme-core' ), $config['title'] ) ) . '</div>';
		}
		// Renderers escape every value they output (same code path as the shortcodes);
		// running kses again would strip the contact form and FAQ schema.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Widget definitions.
 *
 * @return array<string,array>
 */
function pallcore_elementor_widget_configs(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$count   = static fn( $d, $l = null ) => array( 'type' => 'number', 'label' => $l ?? __( 'Number of items', 'palltheme-core' ), 'default' => $d, 'min' => 1, 'max' => 48 );
	$columns = static fn( $d ) => array( 'type' => 'select', 'label' => __( 'Columns', 'palltheme-core' ), 'default' => (string) $d, 'options' => array( '2' => '2', '3' => '3', '4' => '4' ) );
	$ids     = array( 'type' => 'text', 'label' => __( 'Show specific items (IDs, comma separated)', 'palltheme-core' ), 'description' => __( 'Optional. Leave empty to show the latest items automatically.', 'palltheme-core' ) );
	$cat     = static fn( $l ) => array( 'type' => 'text', 'label' => $l, 'description' => __( 'Optional slug(s), comma separated.', 'palltheme-core' ) );

	$cache = array(
		'hero'         => array(
			'name'     => 'hero',
			'title'    => __( 'Hero', 'palltheme-core' ),
			'icon'     => 'eicon-banner',
			'renderer' => 'pallcore_render_hero',
			'section'  => false,
			'controls' => array(
				'eyebrow'    => array( 'type' => 'text', 'label' => __( 'Small label', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_eyebrow' ) ),
				'title'      => array( 'type' => 'textarea', 'label' => __( 'Heading (*highlight*)', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_title' ) ),
				'text'       => array( 'type' => 'textarea', 'label' => __( 'Subheading', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_text' ) ),
				'btn1_text'  => array( 'type' => 'text', 'label' => __( 'Primary button', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_btn1_text' ) ),
				'btn1_url'   => array( 'type' => 'url', 'label' => __( 'Primary button link', 'palltheme-core' ) ),
				'btn2_text'  => array( 'type' => 'text', 'label' => __( 'Secondary button', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_btn2_text' ) ),
				'btn2_url'   => array( 'type' => 'url', 'label' => __( 'Secondary button link', 'palltheme-core' ) ),
				'image'      => array( 'type' => 'media', 'label' => __( 'Hero image', 'palltheme-core' ), 'att_url' => 'image_url' ),
				'cards'      => array( 'type' => 'textarea', 'label' => __( 'Floating cards (Value | Label | icon per line)', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'hero_cards' ) ),
				'stats'      => array( 'type' => 'switch', 'label' => __( 'Show statistics row', 'palltheme-core' ), 'default' => '' ),
				'video_mp4'  => array( 'type' => 'text', 'label' => __( 'Background video MP4 URL (optional)', 'palltheme-core' ) ),
				'video_webm' => array( 'type' => 'text', 'label' => __( 'Background video WebM URL (optional)', 'palltheme-core' ) ),
				'poster'     => array( 'type' => 'media', 'label' => __( 'Background image / video poster', 'palltheme-core' ) ),
				'lottie'     => array( 'type' => 'text', 'label' => __( 'Lottie JSON URL (optional)', 'palltheme-core' ) ),
			),
		),
		'services'     => array( 'name' => 'services', 'title' => __( 'Services Grid', 'palltheme-core' ), 'icon' => 'eicon-gallery-grid', 'renderer' => 'pallcore_render_services', 'defaults' => array( 'eyebrow' => __( 'What we do', 'palltheme-core' ), 'title' => __( 'End-to-end *IT services*', 'palltheme-core' ) ), 'controls' => array( 'count' => $count( 6 ), 'columns' => $columns( 3 ), 'category' => $cat( __( 'Service category', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'solutions'    => array( 'name' => 'solutions', 'title' => __( 'Solutions Grid', 'palltheme-core' ), 'icon' => 'eicon-flow', 'renderer' => 'pallcore_render_solutions', 'defaults' => array( 'eyebrow' => __( 'Industry solutions', 'palltheme-core' ), 'title' => __( 'Built for *your industry*', 'palltheme-core' ) ), 'controls' => array( 'count' => $count( 6 ), 'columns' => $columns( 3 ), 'category' => $cat( __( 'Industry', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'case_studies' => array( 'name' => 'case_studies', 'title' => __( 'Case Studies', 'palltheme-core' ), 'icon' => 'eicon-posts-grid', 'renderer' => 'pallcore_render_case_studies', 'defaults' => array( 'eyebrow' => __( 'Case studies', 'palltheme-core' ), 'title' => __( 'Results that *speak*', 'palltheme-core' ) ), 'controls' => array( 'count' => $count( 3 ), 'columns' => $columns( 3 ), 'category' => $cat( __( 'Industry', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'team'         => array( 'name' => 'team', 'title' => __( 'Team', 'palltheme-core' ), 'icon' => 'eicon-person', 'renderer' => 'pallcore_render_team', 'defaults' => array( 'eyebrow' => __( 'Our team', 'palltheme-core' ), 'title' => __( 'Meet the *engineers*', 'palltheme-core' ) ), 'controls' => array( 'count' => $count( 8 ), 'columns' => $columns( 4 ), 'category' => $cat( __( 'Department', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'testimonials' => array( 'name' => 'testimonials', 'title' => __( 'Testimonials', 'palltheme-core' ), 'icon' => 'eicon-testimonial-carousel', 'renderer' => 'pallcore_render_testimonials', 'defaults' => array( 'eyebrow' => __( 'Testimonials', 'palltheme-core' ), 'title' => __( 'What our *clients* say', 'palltheme-core' ) ), 'controls' => array( 'layout' => array( 'type' => 'select', 'label' => __( 'Layout', 'palltheme-core' ), 'default' => 'slider', 'options' => array( 'slider' => __( 'Slider', 'palltheme-core' ), 'grid' => __( 'Grid / cards', 'palltheme-core' ) ) ), 'count' => $count( 9 ), 'columns' => $columns( 3 ), 'autoplay' => array( 'type' => 'number', 'label' => __( 'Autoplay (ms, 0 = off)', 'palltheme-core' ), 'default' => 6000, 'min' => 0, 'max' => 20000 ), 'ids' => $ids ) ),
		'clients'      => array( 'name' => 'clients', 'title' => __( 'Client Logos', 'palltheme-core' ), 'icon' => 'eicon-logo', 'renderer' => 'pallcore_render_clients', 'defaults' => array( 'eyebrow' => __( 'Trusted by industry leaders', 'palltheme-core' ), 'align' => 'center' ), 'controls' => array( 'layout' => array( 'type' => 'select', 'label' => __( 'Layout', 'palltheme-core' ), 'default' => 'carousel', 'options' => array( 'carousel' => __( 'Infinite scroll', 'palltheme-core' ), 'grid' => __( 'Grid', 'palltheme-core' ) ) ), 'speed' => array( 'type' => 'number', 'label' => __( 'Scroll duration (seconds)', 'palltheme-core' ), 'default' => 40, 'min' => 10, 'max' => 200 ), 'count' => $count( 24 ), 'category' => $cat( __( 'Industry', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'technologies' => array( 'name' => 'technologies', 'title' => __( 'Technology Stack', 'palltheme-core' ), 'icon' => 'eicon-apps', 'renderer' => 'pallcore_render_technologies', 'defaults' => array( 'eyebrow' => __( 'Technology stack', 'palltheme-core' ), 'title' => __( 'Expertise across *leading platforms*', 'palltheme-core' ), 'align' => 'center' ), 'controls' => array( 'filter' => array( 'type' => 'switch', 'label' => __( 'Show group filter', 'palltheme-core' ), 'default' => 'yes' ), 'count' => $count( 32 ), 'category' => $cat( __( 'Technology group', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'stats'        => array( 'name' => 'stats', 'title' => __( 'Statistics Counter', 'palltheme-core' ), 'icon' => 'eicon-counter', 'renderer' => 'pallcore_render_stats', 'controls' => array( 'items' => array( 'type' => 'textarea', 'label' => __( 'Items (Value | Label per line)', 'palltheme-core' ), 'description' => __( 'Leave empty to use Business Settings → Statistics.', 'palltheme-core' ) ), 'style' => array( 'type' => 'select', 'label' => __( 'Style', 'palltheme-core' ), 'default' => 'band', 'options' => array( 'band' => __( 'Floating band', 'palltheme-core' ), 'cards' => __( 'Cards', 'palltheme-core' ), 'plain' => __( 'Plain', 'palltheme-core' ) ) ) ) ),
		'products'     => array( 'name' => 'products', 'title' => __( 'Products', 'palltheme-core' ), 'icon' => 'eicon-products', 'renderer' => 'pallcore_render_products', 'defaults' => array( 'eyebrow' => __( 'Technology store', 'palltheme-core' ), 'title' => __( 'Enterprise hardware, *ready to ship*', 'palltheme-core' ) ), 'controls' => array( 'show' => array( 'type' => 'select', 'label' => __( 'Show', 'palltheme-core' ), 'default' => 'recent', 'options' => array( 'recent' => __( 'Latest', 'palltheme-core' ), 'featured' => __( 'Featured', 'palltheme-core' ), 'sale' => __( 'On sale', 'palltheme-core' ), 'best_selling' => __( 'Best selling', 'palltheme-core' ), 'top_rated' => __( 'Top rated', 'palltheme-core' ) ) ), 'count' => $count( 8 ), 'columns' => $columns( 4 ), 'category' => $cat( __( 'Product category', 'palltheme-core' ) ), 'ids' => $ids ) ),
		'posts'        => array( 'name' => 'posts', 'title' => __( 'Blog Posts', 'palltheme-core' ), 'icon' => 'eicon-post-list', 'renderer' => 'pallcore_render_posts', 'defaults' => array( 'eyebrow' => __( 'Insights', 'palltheme-core' ), 'title' => __( 'Latest from our *engineers*', 'palltheme-core' ) ), 'controls' => array( 'count' => $count( 3 ), 'columns' => $columns( 3 ), 'category' => $cat( __( 'Category', 'palltheme-core' ) ), 'orderby' => array( 'type' => 'select', 'label' => __( 'Order', 'palltheme-core' ), 'default' => '', 'options' => array( '' => __( 'Latest', 'palltheme-core' ), 'comment_count' => __( 'Most discussed (popular)', 'palltheme-core' ) ) ) ) ),
		'pricing'      => array( 'name' => 'pricing', 'title' => __( 'Pricing Table', 'palltheme-core' ), 'icon' => 'eicon-price-table', 'renderer' => 'pallcore_render_pricing', 'defaults' => array( 'eyebrow' => __( 'Pricing', 'palltheme-core' ), 'title' => __( 'Simple, *transparent* plans', 'palltheme-core' ), 'align' => 'center' ), 'controls' => array( 'count' => $count( 3 ), 'columns' => $columns( 3 ), 'ids' => $ids ) ),
		'faq'          => array( 'name' => 'faq', 'title' => __( 'FAQ', 'palltheme-core' ), 'icon' => 'eicon-accordion', 'renderer' => 'pallcore_render_faq', 'defaults' => array( 'eyebrow' => __( 'FAQ', 'palltheme-core' ), 'title' => __( 'Questions, answered', 'palltheme-core' ), 'align' => 'center' ), 'controls' => array( 'category' => $cat( __( 'FAQ group', 'palltheme-core' ) ), 'count' => $count( 20 ) ) ),
		'jobs'         => array( 'name' => 'jobs', 'title' => __( 'Job Openings', 'palltheme-core' ), 'icon' => 'eicon-bullet-list', 'renderer' => 'pallcore_render_jobs', 'defaults' => array( 'eyebrow' => __( 'Careers', 'palltheme-core' ), 'title' => __( 'Open *positions*', 'palltheme-core' ) ), 'controls' => array( 'category' => $cat( __( 'Department', 'palltheme-core' ) ), 'count' => $count( 20 ) ) ),
		'process'      => array( 'name' => 'process', 'title' => __( 'Process Steps', 'palltheme-core' ), 'icon' => 'eicon-number-field', 'renderer' => 'pallcore_render_process', 'defaults' => array( 'eyebrow' => __( 'How we work', 'palltheme-core' ), 'title' => __( 'A proven delivery *process*', 'palltheme-core' ) ), 'controls' => array( 'steps' => array( 'type' => 'textarea', 'label' => __( 'Steps (Title | Text per line)', 'palltheme-core' ), 'default' => "Assess | We audit your infrastructure, security posture and business goals.\nDesign | Our architects design a vendor-neutral, future-proof solution.\nDeploy | Certified engineers implement with zero-downtime migration plans.\nManage | 24/7 monitoring, patching and optimisation keep you ahead." ) ) ),
		'cta'          => array( 'name' => 'cta', 'title' => __( 'Call to Action', 'palltheme-core' ), 'icon' => 'eicon-call-to-action', 'renderer' => 'pallcore_render_cta', 'controls' => array( 'heading' => array( 'type' => 'text', 'label' => __( 'Heading', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'cta_title' ) ), 'text' => array( 'type' => 'textarea', 'label' => __( 'Text', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'cta_text' ) ), 'button' => array( 'type' => 'text', 'label' => __( 'Button text', 'palltheme-core' ), 'default' => (string) pallcore_setting( 'cta_button' ) ), 'button_url' => array( 'type' => 'url', 'label' => __( 'Button link', 'palltheme-core' ) ), 'show_phone' => array( 'type' => 'switch', 'label' => __( 'Show phone button', 'palltheme-core' ), 'default' => 'yes' ) ) ),
		'features'     => array( 'name' => 'features', 'title' => __( 'Features / Trust Cards', 'palltheme-core' ), 'icon' => 'eicon-info-box', 'renderer' => 'pallcore_render_features', 'defaults' => array( 'eyebrow' => __( 'Why us', 'palltheme-core' ), 'title' => __( 'Built on *trust*', 'palltheme-core' ) ), 'controls' => array( 'items' => array( 'type' => 'textarea', 'label' => __( 'Items (icon | Title | Text | optional link — one per line)', 'palltheme-core' ), 'description' => __( 'Leave empty to use Business Settings → Trust items. Icons: server, network, cloud, shield, lock, headset, speed, layers, users, check, code, ai, rack, backup, chart, globe, consult, settings…', 'palltheme-core' ) ), 'style' => array( 'type' => 'select', 'label' => __( 'Style', 'palltheme-core' ), 'default' => 'cards', 'options' => array( 'cards' => __( 'Cards', 'palltheme-core' ), 'trust' => __( 'Compact trust strip', 'palltheme-core' ), 'minimal' => __( 'Minimal (no card)', 'palltheme-core' ) ) ), 'columns' => $columns( 3 ) ) ),
		'split'        => array( 'name' => 'split', 'title' => __( 'Image + Text (Split)', 'palltheme-core' ), 'icon' => 'eicon-image-box', 'renderer' => 'pallcore_render_split', 'defaults' => array( 'eyebrow' => __( 'About us', 'palltheme-core' ), 'title' => __( 'Engineers *first*', 'palltheme-core' ) ), 'controls' => array( 'image' => array( 'type' => 'media', 'label' => __( 'Image', 'palltheme-core' ), 'att_url' => 'image_url' ), 'lottie' => array( 'type' => 'text', 'label' => __( 'Or Lottie JSON URL (replaces the image)', 'palltheme-core' ) ), 'text' => array( 'type' => 'textarea', 'label' => __( 'Text (blank line = new paragraph)', 'palltheme-core' ) ), 'bullets' => array( 'type' => 'textarea', 'label' => __( 'Bullet points (one per line)', 'palltheme-core' ) ), 'button_text' => array( 'type' => 'text', 'label' => __( 'Button text', 'palltheme-core' ) ), 'button_url' => array( 'type' => 'url', 'label' => __( 'Button link', 'palltheme-core' ) ), 'button2_text' => array( 'type' => 'text', 'label' => __( 'Second button text', 'palltheme-core' ) ), 'button2_url' => array( 'type' => 'url', 'label' => __( 'Second button link', 'palltheme-core' ) ), 'badge_value' => array( 'type' => 'text', 'label' => __( 'Floating badge value (e.g. 15+)', 'palltheme-core' ) ), 'badge_label' => array( 'type' => 'text', 'label' => __( 'Floating badge label', 'palltheme-core' ) ), 'reverse' => array( 'type' => 'switch', 'label' => __( 'Image on the right', 'palltheme-core' ), 'default' => '' ), 'priority' => array( 'type' => 'switch', 'label' => __( 'First section on the page (load the image first)', 'palltheme-core' ), 'default' => '' ) ) ),
		'contact'      => array( 'name' => 'contact', 'title' => __( 'Contact Block', 'palltheme-core' ), 'icon' => 'eicon-mail', 'renderer' => 'pallcore_render_contact', 'controls' => array( 'form' => array( 'type' => 'switch', 'label' => __( 'Show contact form', 'palltheme-core' ), 'default' => 'yes' ), 'map' => array( 'type' => 'switch', 'label' => __( 'Show map', 'palltheme-core' ), 'default' => 'yes' ) ) ),
	);
	return $cache;
}

/** Widget: hero. */
final class Pallcore_Widget_Hero extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['hero'];
	}
}

/** Widget: services. */
final class Pallcore_Widget_Services extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['services'];
	}
}

/** Widget: solutions. */
final class Pallcore_Widget_Solutions extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['solutions'];
	}
}

/** Widget: case_studies. */
final class Pallcore_Widget_Case_Studies extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['case_studies'];
	}
}

/** Widget: team. */
final class Pallcore_Widget_Team extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['team'];
	}
}

/** Widget: testimonials. */
final class Pallcore_Widget_Testimonials extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['testimonials'];
	}
}

/** Widget: clients. */
final class Pallcore_Widget_Clients extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['clients'];
	}
}

/** Widget: technologies. */
final class Pallcore_Widget_Technologies extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['technologies'];
	}
}

/** Widget: stats. */
final class Pallcore_Widget_Stats extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['stats'];
	}
}

/** Widget: products. */
final class Pallcore_Widget_Products extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['products'];
	}
}

/** Widget: posts. */
final class Pallcore_Widget_Posts extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['posts'];
	}
}

/** Widget: pricing. */
final class Pallcore_Widget_Pricing extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['pricing'];
	}
}

/** Widget: faq. */
final class Pallcore_Widget_Faq extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['faq'];
	}
}

/** Widget: jobs. */
final class Pallcore_Widget_Jobs extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['jobs'];
	}
}

/** Widget: process. */
final class Pallcore_Widget_Process extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['process'];
	}
}

/** Widget: cta. */
final class Pallcore_Widget_Cta extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['cta'];
	}
}

/** Widget: features. */
final class Pallcore_Widget_Features extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['features'];
	}
}

/** Widget: split. */
final class Pallcore_Widget_Split extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['split'];
	}
}

/** Widget: contact. */
final class Pallcore_Widget_Contact extends Pallcore_Elementor_Widget {
	/** @inheritDoc */
	protected function config(): array {
		return pallcore_elementor_widget_configs()['contact'];
	}
}

/**
 * Register widgets.
 */
add_action(
	'elementor/widgets/register',
	static function ( $widgets_manager ) {
		foreach ( array( 'Pallcore_Widget_Hero', 'Pallcore_Widget_Services', 'Pallcore_Widget_Solutions', 'Pallcore_Widget_Case_Studies', 'Pallcore_Widget_Team', 'Pallcore_Widget_Testimonials', 'Pallcore_Widget_Clients', 'Pallcore_Widget_Technologies', 'Pallcore_Widget_Stats', 'Pallcore_Widget_Products', 'Pallcore_Widget_Posts', 'Pallcore_Widget_Pricing', 'Pallcore_Widget_Faq', 'Pallcore_Widget_Jobs', 'Pallcore_Widget_Process', 'Pallcore_Widget_Cta', 'Pallcore_Widget_Contact', 'Pallcore_Widget_Features', 'Pallcore_Widget_Split' ) as $class ) {
			$widgets_manager->register( new $class() );
		}
	}
);
