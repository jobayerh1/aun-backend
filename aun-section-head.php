<?php
/**
 * Plugin Name: AUN Section Head
 * Description: Premium, big-brand style section headers for product pages — eyebrow label + bold headline
 *              + accent line + subtitle. Also provides the .aun-feat-eyebrow class for category labels on
 *              feature rows. Translatable (TranslatePress) & WP-Rocket-safe.
 *              Shortcode: [aun_head eyebrow="Display" title="Full HD Resolution" sub="..." icon="fa-star" align="center"]
 * Version:     1.0.0
 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'aun_head', 'aun_head_shortcode' );

function aun_head_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'eyebrow' => '',
		'title'   => '',
		'sub'     => '',
		'icon'    => '',
		'align'   => 'center',
	), $atts, 'aun_head' );

	if ( $a['title'] === '' && $a['eyebrow'] === '' && $a['sub'] === '' ) return '';

	$align   = in_array( $a['align'], array( 'center', 'left', 'right' ), true ) ? $a['align'] : 'center';
	$icon    = $a['icon'] !== '' ? '<i class="fa-solid ' . esc_attr( $a['icon'] ) . '"></i> ' : '';
	$eyebrow = $a['eyebrow'] !== '' ? '<span class="aun-head-eyebrow">' . $icon . esc_html( $a['eyebrow'] ) . '</span>' : '';
	$title   = $a['title'] !== '' ? '<h2 class="aun-head-title">' . esc_html( $a['title'] ) . '</h2>' : '';
	$accent  = $a['title'] !== '' ? '<span class="aun-head-accent"></span>' : '';
	$sub     = $a['sub'] !== '' ? '<p class="aun-head-sub">' . wp_kses_post( $a['sub'] ) . '</p>' : '';

	return aun_head_assets()
		. '<div class="aun-head aun-head--' . $align . '">' . $eyebrow . $title . $accent . $sub . '</div>';
}

function aun_head_assets() {
	static $done = false;
	if ( $done ) return '';
	$done = true;

	$css = <<<'CSS'
.aun-head{max-width:680px;margin:0 auto 30px;padding:0 14px;box-sizing:border-box}
.aun-head--center{text-align:center}
.aun-head--left{text-align:left;margin-left:0}
.aun-head--right{text-align:right;margin-right:0}
.aun-head-eyebrow{display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:11px;line-height:1;letter-spacing:2px;text-transform:uppercase;color:#0188fe;background:#eaf4ff;border:1px solid #cfe6ff;padding:7px 13px;border-radius:999px;margin-bottom:14px}
.aun-head-eyebrow i{font-size:11px}
.aun-head-title{font-size:clamp(24px,3.6vw,34px);font-weight:800;color:#0c2a4a;letter-spacing:-.5px;line-height:1.15;margin:0}
.aun-head-accent{display:block;width:46px;height:3px;border-radius:3px;background:linear-gradient(90deg,#0188fe,#00c6ff);margin-top:14px}
.aun-head--center .aun-head-accent{margin-left:auto;margin-right:auto}
.aun-head-sub{color:#6b86a3;font-size:clamp(14px,1.6vw,16px);line-height:1.65;margin:14px auto 0;max-width:560px}
.aun-head--left .aun-head-sub,.aun-head--right .aun-head-sub{margin-left:0;margin-right:0}

/* Category "eyebrow" label for feature rows (Apple/Sony pattern) */
.aun-feat-eyebrow{margin:0 0 7px;font-weight:800;font-size:12px;line-height:1;letter-spacing:1.5px;text-transform:uppercase;color:#0188fe}
CSS;

	return '<style id="aun-head-css" data-no-optimize="1" data-no-minify="1">' . $css . '</style>';
}
