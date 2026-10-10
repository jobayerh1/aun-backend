<?php
/**
 * FAQ accordion built on <details> (works without JavaScript).
 * Args: items (array of [question, answer]).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['items'] ) ) {
	return;
}

if ( function_exists( 'pallcore_faq_html' ) ) {
	echo '<section class="pt-section"><div class="pt-container pt-container--narrow"><div class="pt-section-head"><p class="pt-eyebrow">' . esc_html__( 'FAQ', 'palltheme' ) . '</p><h2 class="pt-section-title">' . esc_html__( 'Frequently asked questions', 'palltheme' ) . '</h2></div>';
	echo pallcore_faq_html( $args['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	echo '</div></section>';
}
