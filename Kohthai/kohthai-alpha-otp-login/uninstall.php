<?php
/**
 * Uninstall cleanup — runs only when the plugin is deleted from the admin.
 * Removes our own option and any leftover OTP / rate-limit transients.
 * (Alpha SMS's own option is left untouched — we only borrowed its API key.)
 *
 * @package KT_Alpha_OTP_Login
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'kt_alpha_otp' );

global $wpdb;

// Remove transients created by this plugin (prefixes: kt_alpha_otp_, _rlp_, _rli_).
$like = $wpdb->esc_like( '_transient_kt_alpha_otp_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB

$like_timeout = $wpdb->esc_like( '_transient_timeout_kt_alpha_otp_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB
