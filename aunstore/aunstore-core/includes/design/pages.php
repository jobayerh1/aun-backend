<?php
/**
 * Designed info pages — the building blocks every info and policy page is written with, so the whole site
 * speaks the homepage's design language. The text stays in the page (edit it in WordPress); only the look
 * lives here.
 *
 * Page bands (each draws its own full-width section):
 *   [aunp_hero eyebrow="" icon="" title="" accent="" sub="" btn="" btn_url="" btn_icon="" btn2="" btn2_url="" btn2_icon="" img="" chips="A|B|C"]
 *   [aunp_section tone="white|soft|dark" id=""] …blocks… [/aunp_section]
 *   [aunp_help]            closing "talk to a real person" panel (from Settings → AUN Store Details)
 *   [aunp_updated date=""] "Last updated" line
 * Blocks (inside a section):
 *   [aunp_head eyebrow="" icon="" title="" sub="" align="center|left"]
 *   [aunp_cards cols="3" align="left|center"] [aunp_card icon="" title="" url=""]text[/aunp_card] … [/aunp_cards]
 *   [aunp_steps] [aunp_step icon="" title=""]text[/aunp_step] … [/aunp_steps]
 *   [aunp_doc title=""] [aunp_clause title="1. …"]html[/aunp_clause] … [/aunp_doc]      (sticky contents list)
 *   [aunp_faq] [aunp_q q="" open="1"]answer[/aunp_q] … [/aunp_faq]                      (+ FAQPage schema)
 *   [aunp_note tone="warn|info|draft" icon=""]html[/aunp_note]
 *   [aunp_stats] [aunp_stat value="100" suffix="+" label="" plain=""] … [/aunp_stats]
 *   [aunp_facts] [aunp_fact icon="" label=""]value[/aunp_fact] … [/aunp_facts]
 *   [aunp_split img="" eyebrow="" title="" reverse=""]html[/aunp_split]
 *   [aunp_btn url="" icon="" style="pri|line"]Label[/aunp_btn]
 *   [aunp_contact]         channel cards from Store Details
 *   [aunp_tool]…a tool shortcode…[/aunp_tool]
 *
 * Item shortcodes hand their data to their container instead of printing, so the paragraph tags WordPress
 * adds between shortcode lines can never leak into the layout.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/ui-base.php';
require_once __DIR__ . '/pages-css.php';
require_once __DIR__ . '/pages-js.php';

final class AUNStore_Pages {

	/** @var array<int,array> item data collected by the container being rendered */
	private static $stack = array();

	/** @var array<string,true> anchor ids used on this page */
	private static $ids = array();

	public static function init() {
		foreach ( array( 'hero', 'section', 'help', 'updated', 'head', 'cards', 'card', 'steps', 'step', 'doc', 'clause', 'faq', 'q', 'note', 'stats', 'stat', 'facts', 'fact', 'split', 'btn', 'contact', 'tool' ) as $tag ) {
			add_shortcode( 'aunp_' . $tag, array( __CLASS__, 'sc_' . $tag ) );
		}
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 21 );
		add_action( 'wp_footer', array( __CLASS__, 'print_js' ), 20 );
	}

	/** Does the current singular page use the designed blocks? */
	public static function in_use() {
		$post = is_singular() ? get_post() : null;
		return $post && false !== strpos( (string) $post->post_content, '[aunp_' );
	}

	public static function print_css() {
		if ( self::in_use() ) echo '<style id="aunp-css" data-no-optimize="1" data-no-minify="1">' . aunstore_ui_base_css() . aunstore_pages_css() . "</style>\n";
	}

	public static function print_js() {
		if ( self::in_use() ) echo '<script id="aunp-js" data-no-optimize="1" data-no-minify="1" data-cfasync="false">' . aunstore_pages_js() . "</script>\n";
	}

	/* ── helpers ─────────────────────────────────────────────────────── */

	private static function icon( $icon, $extra = '' ) {
		$icon = trim( (string) $icon );
		if ( '' === $icon ) return '';
		$cls = false !== strpos( $icon, ' ' ) ? $icon : 'fa-solid ' . $icon;
		return '<i class="' . esc_attr( trim( $cls . ' ' . $extra ) ) . '" aria-hidden="true"></i>';
	}

	private static function url( $url ) {
		$url = trim( (string) $url );
		return 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? home_url( $url ) : $url;
	}

	private static function external( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
	}

	private static function link_attrs( $url ) {
		$url = self::url( $url );
		return ' href="' . esc_url( $url ) . '"' . ( self::external( $url ) ? ' target="_blank" rel="noopener"' : '' );
	}

	/** Paragraph debris WordPress leaves at the edges of shortcode content. */
	private static function trim_p( $html ) {
		$html = trim( (string) $html );
		$html = preg_replace( '#^(?:\s*(?:</p>|<br\s*/?>))+#i', '', $html );
		$html = preg_replace( '#(?:(?:<p>|<br\s*/?>)\s*)+$#i', '', $html );
		return trim( preg_replace( '#<p>\s*</p>#i', '', $html ) );
	}

	/** Short text (a card or step line): one paragraph's worth, unwrapped. */
	private static function text( $content ) {
		$html = self::trim_p( do_shortcode( (string) $content ) );
		if ( preg_match( '#^<p>(.*)</p>$#is', $html, $m ) && false === stripos( $m[1], '<p' ) ) $html = $m[1];
		return $html;
	}

	/** Longer HTML (a clause, an answer): keeps its paragraphs, loses the debris. */
	private static function html( $content ) {
		$html = self::trim_p( do_shortcode( shortcode_unautop( (string) $content ) ) );
		if ( '' !== $html && ! preg_match( '#^<(?:p|ul|ol|div|table|h[2-6])\b#i', $html ) ) $html = '<p>' . $html; // text before the first paragraph
		return force_balance_tags( $html );
	}

	/** Block-level output inside a section, without the <p> wrappers WordPress puts around shortcode lines. */
	private static function blocks( $content ) {
		$html  = do_shortcode( shortcode_unautop( (string) $content ) );
		$block = '(?:div|section|nav|aside|ul|ol|h[1-6]|figure|details|article|table|script|style|form|a class="aunp)';
		$html  = preg_replace( '#<p>\s*(<' . $block . ')#i', '$1', $html );
		$html  = preg_replace( '#(</(?:div|section|nav|aside|ul|ol|h[1-6]|figure|details|article|table|script|style|form|a)>)\s*(?:</p>|<br\s*/?>)#i', '$1', $html );
		return self::trim_p( $html );
	}

	private static function collect( $content ) {
		self::$stack[] = array();
		do_shortcode( shortcode_unautop( (string) $content ) );
		return array_pop( self::$stack );
	}

	private static function push( $item ) {
		if ( self::$stack ) self::$stack[ count( self::$stack ) - 1 ][] = $item;
		return '';
	}

	private static function anchor( $title ) {
		$base = sanitize_title( preg_replace( '/^\s*\d+[.)]\s*/', '', wp_strip_all_tags( $title ) ) ) ?: 'section';
		$id   = $base;
		for ( $n = 2; isset( self::$ids[ $id ] ); $n++ ) $id = $base . '-' . $n;
		self::$ids[ $id ] = true;
		return $id;
	}

	private static function split_number( $title ) {
		return preg_match( '/^\s*(\d+)[.)]\s*(.+)$/s', $title, $m ) ? array( $m[1], $m[2] ) : array( '', $title );
	}

	private static function btn( $label, $url, $icon = '', $style = 'pri' ) {
		if ( '' === trim( (string) $label ) || '' === trim( (string) $url ) ) return '';
		$arrow = '' === $icon && 'pri' === $style;
		return '<a class="aunx-btn aunx-btn-' . esc_attr( $style ) . '"' . self::link_attrs( $url ) . '>'
			. self::icon( $icon ) . '<span>' . wp_kses_post( $label ) . '</span>'
			. ( $arrow ? self::icon( 'fa-arrow-right' ) : '' ) . '</a>';
	}

	/* ── page bands ──────────────────────────────────────────────────── */

	public static function sc_hero( $atts ) {
		$a   = shortcode_atts( array( 'eyebrow' => '', 'icon' => '', 'title' => '', 'accent' => '', 'sub' => '', 'btn' => '', 'btn_url' => '', 'btn_icon' => '', 'btn2' => '', 'btn2_url' => '', 'btn2_icon' => '', 'img' => '', 'chips' => '' ), $atts, 'aunp_hero' );
		$img = $a['img'] ? wp_get_attachment_image_url( (int) $a['img'], 'full' ) : '';

		$out  = '<section class="aunx aunx-sec aunp-hero' . ( $img ? ' has-img' : '' ) . '">';
		if ( $img ) $out .= '<div class="aunp-hero-img" style="background-image:url(\'' . esc_url( $img ) . '\')" aria-hidden="true"></div>';
		$out .= '<div class="aunx-wrap aunp-hero-in"><div class="aunp-hero-copy">';
		if ( $a['eyebrow'] ) $out .= '<span class="aunp-pill">' . self::icon( $a['icon'] ) . ' ' . esc_html( $a['eyebrow'] ) . '</span>';
		$out .= '<h1>' . wp_kses_post( $a['title'] ) . ( $a['accent'] ? ' <em>' . wp_kses_post( $a['accent'] ) . '</em>' : '' ) . '</h1>';
		if ( $a['sub'] ) $out .= '<p>' . wp_kses_post( $a['sub'] ) . '</p>';
		$btns = self::btn( $a['btn'], $a['btn_url'], $a['btn_icon'], 'pri' ) . self::btn( $a['btn2'], $a['btn2_url'], $a['btn2_icon'], 'ghost' );
		if ( $btns ) $out .= '<div class="aunp-cta">' . $btns . '</div>';
		$out .= '</div>';
		if ( ! $img && $a['icon'] ) {
			$out .= '<div class="aunp-hero-art" aria-hidden="true"><span class="aunp-glow"></span><span class="aunp-ring aunp-ring-1"></span><span class="aunp-ring aunp-ring-2"></span>'
				. '<span class="aunp-orb">' . self::icon( $a['icon'] ) . '</span>';
			foreach ( array_slice( array_filter( array_map( 'trim', explode( '|', $a['chips'] ) ) ), 0, 3 ) as $n => $chip ) {
				$out .= '<span class="aunp-chip aunp-chip-' . ( $n + 1 ) . '"><i class="fa-solid fa-circle-check"></i> ' . esc_html( $chip ) . '</span>';
			}
			$out .= '</div>';
		}
		return $out . '</div></section>';
	}

	public static function sc_section( $atts, $content = '' ) {
		$a    = shortcode_atts( array( 'tone' => 'white', 'id' => '' ), $atts, 'aunp_section' );
		$tone = in_array( $a['tone'], array( 'white', 'soft', 'dark' ), true ) ? $a['tone'] : 'white';
		$body = self::blocks( $content );
		if ( '' === trim( wp_strip_all_tags( $body, true ) ) && false === strpos( $body, '<img' ) ) return ''; // nothing to show (e.g. no contact details yet)
		return '<section class="aunx aunx-sec aunp-sec aunp-' . $tone . '"' . ( $a['id'] ? ' id="' . esc_attr( sanitize_title( $a['id'] ) ) . '"' : '' ) . '><div class="aunx-wrap">'
			. $body . '</div></section>';
	}

	public static function sc_help() {
		$wa    = aunstore_detail( 'whatsapp' );
		$email = aunstore_detail( 'email' );
		$phone = aunstore_detail( 'phone' );
		$hours = aunstore_detail( 'hours' );
		$page  = function ( $slug ) {
			$p = get_page_by_path( $slug );
			return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
		};

		$btns = '';
		if ( $wa )    $btns .= '<a class="aunp-help-btn is-wa" href="https://wa.me/' . esc_attr( $wa ) . '" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp us</a>';
		if ( $email ) $btns .= '<a class="aunp-help-btn" href="mailto:' . esc_attr( $email ) . '"><i class="fa-solid fa-envelope" aria-hidden="true"></i> ' . esc_html( $email ) . '</a>';
		if ( $phone ) $btns .= '<a class="aunp-help-btn" href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ) . '"><i class="fa-solid fa-phone" aria-hidden="true"></i> ' . esc_html( $phone ) . '</a>';
		if ( ! $btns && ( $u = $page( 'contact-us' ) ) ) $btns .= '<a class="aunp-help-btn" href="' . esc_url( $u ) . '"><i class="fa-solid fa-headset" aria-hidden="true"></i> Contact us</a>';

		$links = '';
		foreach ( array(
			array( 'warranty', 'fa-screwdriver-wrench', 'Warranty & Support', 'Claims, parts and repair videos' ),
			array( 'verify-authenticity', 'fa-fingerprint', 'Verify Authenticity', 'Check your 12-digit code' ),
			array( 'shipping-policy', 'fa-truck-fast', 'Shipping Policy', 'Delivery, tracking and customs' ),
			array( 'returns-and-refunds', 'fa-arrow-right-arrow-left', 'Returns & Refunds', 'Damaged, faulty or wrong items' ),
		) as $l ) {
			$u = $page( $l[0] );
			if ( ! $u || untrailingslashit( $u ) === untrailingslashit( (string) get_permalink() ) ) continue; // not the page we are on
			$links .= '<a class="aunp-help-link" href="' . esc_url( $u ) . '">' . self::icon( $l[1] ) . '<span><b>' . esc_html( $l[2] ) . '</b><small>' . esc_html( $l[3] ) . '</small></span><i class="fa-solid fa-arrow-right aunp-help-go" aria-hidden="true"></i></a>';
		}

		return '<section class="aunx aunx-sec aunp-sec aunp-white aunp-help-sec"><div class="aunx-wrap"><div class="aunp-help" data-rv>'
			. '<div class="aunp-help-l"><span class="aunx-eyebrow">' . self::icon( 'fa-headset' ) . ' Still need help?</span><h2>Talk to a real person</h2><p>Before or after you buy &mdash; questions about a model, an order or a warranty claim.</p>'
			. ( $btns ? '<div class="aunp-help-btns">' . $btns . '</div>' : '' )
			. ( $hours ? '<p class="aunp-help-hours">' . self::icon( 'fa-clock' ) . ' ' . esc_html( $hours ) . '</p>' : '' ) . '</div>'
			. ( $links ? '<div class="aunp-help-r">' . $links . '</div>' : '' )
			. '</div></div></section>';
	}

	public static function sc_updated( $atts ) {
		$a = shortcode_atts( array( 'date' => '' ), $atts, 'aunp_updated' );
		if ( '' === trim( $a['date'] ) ) return '';
		return '<section class="aunx aunx-sec aunp-updated"><div class="aunx-wrap"><p>' . self::icon( 'fa-clock-rotate-left' ) . ' Last updated: <strong>' . esc_html( $a['date'] ) . '</strong></p></div></section>';
	}

	/* ── blocks ──────────────────────────────────────────────────────── */

	public static function sc_head( $atts ) {
		$a = shortcode_atts( array( 'eyebrow' => '', 'icon' => '', 'title' => '', 'sub' => '', 'align' => 'center' ), $atts, 'aunp_head' );
		return '<div class="aunx-head aunp-head' . ( 'left' === $a['align'] ? ' is-left' : '' ) . '" data-rv>'
			. ( $a['eyebrow'] ? '<span class="aunx-eyebrow">' . self::icon( $a['icon'] ) . ' ' . esc_html( $a['eyebrow'] ) . '</span>' : '' )
			. ( $a['title'] ? '<h2>' . wp_kses_post( $a['title'] ) . '</h2>' : '' )
			. ( $a['sub'] ? '<p>' . wp_kses_post( $a['sub'] ) . '</p>' : '' ) . '</div>';
	}

	public static function sc_card( $atts, $content = '' ) {
		return self::push( shortcode_atts( array( 'icon' => '', 'title' => '', 'url' => '', 'link' => '' ), $atts, 'aunp_card' ) + array( 'text' => self::text( $content ) ) );
	}

	public static function sc_cards( $atts, $content = '' ) {
		$a     = shortcode_atts( array( 'cols' => '3', 'align' => 'left' ), $atts, 'aunp_cards' );
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$cols = max( 2, min( 4, (int) $a['cols'] ) );
		$out  = '<div class="aunp-cards aunp-cols-' . $cols . ( 'center' === $a['align'] ? ' is-center' : '' ) . '">';
		foreach ( $items as $n => $c ) {
			$tag  = $c['url'] ? 'a' : 'div';
			$out .= '<' . $tag . ' class="aunp-card"' . ( $c['url'] ? self::link_attrs( $c['url'] ) : '' ) . ' data-rv style="--d:' . esc_attr( ( $n % $cols ) * 0.08 ) . 's">'
				. ( $c['icon'] ? '<span class="aunp-ico">' . self::icon( $c['icon'] ) . '</span>' : '' )
				. '<h3>' . wp_kses_post( $c['title'] ) . '</h3>'
				. ( '' !== $c['text'] ? '<p>' . $c['text'] . '</p>' : '' )
				. ( $c['url'] ? '<span class="aunp-card-go">' . esc_html( $c['link'] ?: 'Learn more' ) . ' ' . self::icon( 'fa-arrow-right' ) . '</span>' : '' )
				. '</' . $tag . '>';
		}
		return $out . '</div>';
	}

	public static function sc_step( $atts, $content = '' ) {
		return self::push( shortcode_atts( array( 'icon' => '', 'title' => '' ), $atts, 'aunp_step' ) + array( 'text' => self::text( $content ) ) );
	}

	public static function sc_steps( $atts, $content = '' ) {
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$out = '<ol class="aunp-steps" style="--n:' . count( $items ) . '">';
		foreach ( $items as $n => $s ) {
			$out .= '<li class="aunp-step" data-rv style="--d:' . esc_attr( $n * 0.1 ) . 's"><span class="aunp-step-dot">' . self::icon( $s['icon'] ?: 'fa-circle' ) . '</span>'
				. '<span class="aunp-step-n">Step ' . ( $n + 1 ) . '</span><h3>' . wp_kses_post( $s['title'] ) . '</h3>'
				. ( '' !== $s['text'] ? '<p>' . $s['text'] . '</p>' : '' ) . '</li>';
		}
		return $out . '</ol>';
	}

	public static function sc_clause( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'title' => '' ), $atts, 'aunp_clause' );
		return self::push( array( 'title' => $a['title'], 'html' => self::html( $content ) ) );
	}

	public static function sc_doc( $atts, $content = '' ) {
		$a     = shortcode_atts( array( 'title' => '' ), $atts, 'aunp_doc' );
		self::$ids = array(); // anchors are per document, so a second render of the same page (some themes do) keeps the same ids
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$toc  = '';
		$body = '';
		foreach ( $items as $n => $c ) {
			list( $num, $title ) = self::split_number( $c['title'] );
			$num = '' !== $num ? $num : (string) ( $n + 1 );
			$id  = self::anchor( $title );
			$toc  .= '<a href="#' . esc_attr( $id ) . '"><span>' . esc_html( $num ) . '</span>' . wp_kses_post( $title ) . '</a>';
			$body .= '<article class="aunp-clause" id="' . esc_attr( $id ) . '"><h3><span class="aunp-clause-n">' . esc_html( $num ) . '</span>' . wp_kses_post( $title ) . '</h3><div class="aunp-clause-c">' . $c['html'] . '</div></article>';
		}
		return '<div class="aunp-doc">'
			. '<aside class="aunp-toc"><details class="aunp-toc-box" open><summary>' . self::icon( 'fa-list-ul' ) . ' On this page</summary><nav aria-label="On this page">' . $toc . '</nav></details></aside>'
			. '<div class="aunp-doc-body">' . ( $a['title'] ? '<h2 class="aunp-doc-title">' . wp_kses_post( $a['title'] ) . '</h2>' : '' ) . $body . '</div></div>';
	}

	public static function sc_q( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'q' => '', 'open' => '' ), $atts, 'aunp_q' );
		return self::push( array( 'q' => $a['q'], 'open' => '' !== $a['open'], 'a' => self::html( $content ) ) );
	}

	public static function sc_faq( $atts, $content = '' ) {
		$a     = shortcode_atts( array( 'schema' => '1' ), $atts, 'aunp_faq' );
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$out = '<div class="aunp-faq">';
		$ld  = array();
		foreach ( $items as $n => $q ) {
			$out .= '<details class="aunp-q" data-rv style="--d:' . esc_attr( min( $n, 5 ) * 0.05 ) . 's"' . ( $q['open'] ? ' open' : '' ) . '><summary><span>' . esc_html( $q['q'] ) . '</span><i class="aunp-q-ico" aria-hidden="true"></i></summary><div class="aunp-q-a">' . $q['a'] . '</div></details>';
			$ld[]  = array( '@type' => 'Question', 'name' => wp_strip_all_tags( $q['q'] ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $q['a'] ) ) ) ) );
		}
		$out .= '</div>';
		if ( '1' === (string) $a['schema'] ) {
			$out .= '<script type="application/ld+json" data-no-optimize="1" data-no-minify="1">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
		}
		return $out;
	}

	public static function sc_note( $atts, $content = '' ) {
		$a    = shortcode_atts( array( 'tone' => 'warn', 'icon' => '' ), $atts, 'aunp_note' );
		$tone = in_array( $a['tone'], array( 'warn', 'info', 'draft' ), true ) ? $a['tone'] : 'warn';
		$icon = $a['icon'] ?: array( 'warn' => 'fa-circle-exclamation', 'info' => 'fa-circle-info', 'draft' => 'fa-pen-ruler' )[ $tone ];
		return '<div class="aunp-note aunp-note-' . $tone . '" data-rv><span class="aunp-note-ico">' . self::icon( $icon ) . '</span><div>' . self::html( $content ) . '</div></div>';
	}

	public static function sc_stat( $atts ) {
		return self::push( shortcode_atts( array( 'value' => '', 'suffix' => '', 'label' => '', 'plain' => '' ), $atts, 'aunp_stat' ) );
	}

	public static function sc_stats( $atts, $content = '' ) {
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$out = '<ul class="aunp-stats" style="--n:' . count( $items ) . '" data-rv>';
		foreach ( $items as $s ) {
			// only real quantities roll up; a year or a single digit ("1-year") would flash odd values on the way
			$num  = is_numeric( $s['value'] ) && ! $s['plain'] && (float) $s['value'] >= 10 ? ' data-count="' . esc_attr( $s['value'] ) . '"' . ( $s['suffix'] ? ' data-suffix="' . esc_attr( $s['suffix'] ) . '"' : '' ) . ( $s['plain'] ? ' data-plain="1"' : '' ) : '';
			$out .= '<li><b' . $num . '>' . esc_html( $s['value'] . $s['suffix'] ) . '</b><span>' . esc_html( $s['label'] ) . '</span></li>';
		}
		return $out . '</ul>';
	}

	public static function sc_fact( $atts, $content = '' ) {
		return self::push( shortcode_atts( array( 'icon' => '', 'label' => '' ), $atts, 'aunp_fact' ) + array( 'text' => self::text( $content ) ) );
	}

	public static function sc_facts( $atts, $content = '' ) {
		$items = self::collect( $content );
		if ( ! $items ) return '';
		$out = '<ul class="aunp-facts" data-rv>';
		foreach ( $items as $f ) {
			$out .= '<li><span class="aunp-ico">' . self::icon( $f['icon'] ?: 'fa-circle-check' ) . '</span><span><small>' . esc_html( $f['label'] ) . '</small><b>' . $f['text'] . '</b></span></li>';
		}
		return $out . '</ul>';
	}

	public static function sc_split( $atts, $content = '' ) {
		$a   = shortcode_atts( array( 'img' => '', 'eyebrow' => '', 'icon' => '', 'title' => '', 'reverse' => '', 'frame' => '1' ), $atts, 'aunp_split' );
		$img = $a['img'] ? wp_get_attachment_image( (int) $a['img'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'aunp-split-img' ) ) : '';
		return '<div class="aunp-split' . ( $a['reverse'] ? ' is-rev' : '' ) . ( $img ? '' : ' no-img' ) . ( '0' === (string) $a['frame'] ? ' is-bare' : '' ) . '">'
			. ( $img ? '<div class="aunp-split-media" data-rv>' . $img . '</div>' : '' )
			. '<div class="aunp-split-copy" data-rv style="--d:.1s">'
			. ( $a['eyebrow'] ? '<span class="aunx-eyebrow">' . self::icon( $a['icon'] ) . ' ' . esc_html( $a['eyebrow'] ) . '</span>' : '' )
			. ( $a['title'] ? '<h2>' . wp_kses_post( $a['title'] ) . '</h2>' : '' )
			. '<div class="aunp-split-text">' . self::html( $content ) . '</div></div></div>';
	}

	public static function sc_btn( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'url' => '', 'icon' => '', 'style' => 'pri' ), $atts, 'aunp_btn' );
		return self::btn( wp_strip_all_tags( $content ), $a['url'], $a['icon'], in_array( $a['style'], array( 'pri', 'line', 'ghost' ), true ) ? $a['style'] : 'pri' );
	}

	public static function sc_contact( $atts ) {
		$a     = shortcode_atts( array( 'eyebrow' => '', 'icon' => '', 'title' => '', 'sub' => '' ), $atts, 'aunp_contact' );
		$cards = array();
		if ( $v = aunstore_detail( 'whatsapp' ) ) $cards[] = array( 'fa-brands fa-whatsapp', 'WhatsApp', '+' . $v, 'https://wa.me/' . $v, 'is-wa', 'Usually the fastest way to reach us' );
		if ( $v = aunstore_detail( 'email' ) )    $cards[] = array( 'fa-solid fa-envelope', 'Email', $v, 'mailto:' . $v, '', 'For orders, warranty claims and videos' );
		if ( $v = aunstore_detail( 'phone' ) )    $cards[] = array( 'fa-solid fa-phone', 'Phone', $v, 'tel:' . preg_replace( '/[^\d+]/', '', $v ), '', 'During support hours' );
		if ( ! $cards ) return ''; // hours alone are no way to reach anyone
		if ( $v = aunstore_detail( 'hours' ) )    $cards[] = array( 'fa-solid fa-clock', 'Support hours', $v, '', 'is-hours', 'We reply as soon as we are back' );
		$out = ( $a['title'] ? self::sc_head( $a ) : '' ) . '<div class="aunp-channels" style="--n:' . count( $cards ) . '">';
		foreach ( $cards as $n => $c ) {
			$tag  = $c[3] ? 'a' : 'div';
			$out .= '<' . $tag . ' class="aunp-channel ' . $c[4] . '"' . ( $c[3] ? ' href="' . esc_url( $c[3] ) . '"' . ( 0 === strpos( $c[3], 'https://' ) ? ' target="_blank" rel="noopener"' : '' ) : '' ) . ' data-rv style="--d:' . esc_attr( $n * 0.08 ) . 's">'
				. '<span class="aunp-channel-ico"><i class="' . esc_attr( $c[0] ) . '" aria-hidden="true"></i></span>'
				. '<small>' . esc_html( $c[1] ) . '</small><b>' . esc_html( $c[2] ) . '</b><span class="aunp-channel-note">' . esc_html( $c[5] ) . '</span></' . $tag . '>';
		}
		return $out . '</div>';
	}

	/** A band of its own, outside the .aunx scope, so a tool's own button/list styles are not reset. */
	public static function sc_tool( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'frame' => '1' ), $atts, 'aunp_tool' ); // frame="0" for a tool that draws its own card
		return '<section class="aunx-sec aunp-tool-sec"><div class="aunx-wrap"><div class="aunp-tool' . ( '0' === (string) $a['frame'] ? ' is-bare' : '' ) . '">' . do_shortcode( shortcode_unautop( self::trim_p( $content ) ) ) . '</div></div></section>';
	}
}

AUNStore_Pages::init();
