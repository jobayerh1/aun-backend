<?php
/**
 * Plugin Name: AUN Boot Profiler
 * Description: Measures how long each active plugin takes to load, plus the main WordPress boot phases. Must-use plugin: loads before everything else.
 * Version: 1.2.0
 * Author: AUN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AUN_Boot_Profiler {

	/** Secret needed to view the report in the browser. Change this. */
	const KEY = 'aun-boot-2026';

	/** Only log requests slower than this (seconds). Keeps the log small. */
	const SLOW_THRESHOLD = 1.5;

	private $start;
	private $last;
	private $plugins = array();
	private $phases  = array();

	public function __construct() {
		$this->start = defined( 'WP_START_TIMESTAMP' ) ? WP_START_TIMESTAMP : microtime( true );
		$this->last  = microtime( true );

		add_action( 'plugin_loaded', array( $this, 'mark_plugin' ) );

		foreach ( array( 'muplugins_loaded', 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'template_redirect' ) as $hook ) {
			add_action( $hook, array( $this, 'mark_phase' ), -PHP_INT_MAX );
		}

		add_action( 'shutdown', array( $this, 'report' ), PHP_INT_MAX );
	}

	public function mark_plugin( $file ) {
		$now = microtime( true );
		$this->plugins[ plugin_basename( $file ) ] = $now - $this->last;
		$this->last = $now;
	}

	public function mark_phase() {
		$this->phases[ current_action() ] = microtime( true ) - $this->start;
	}

	public function report() {
		$total = microtime( true ) - $this->start;

		arsort( $this->plugins );

		$lines   = array();
		$lines[] = sprintf(
			'%s  %s  total=%.3fs  queries=%d  mem=%.1fMB',
			gmdate( 'Y-m-d H:i:s' ),
			$this->request_label(),
			$total,
			get_num_queries(),
			memory_get_peak_usage( true ) / 1048576
		);

		foreach ( $this->phases as $hook => $elapsed ) {
			$lines[] = sprintf( '    phase  %-18s %.3fs', $hook, $elapsed );
		}

		$shown = 0;
		foreach ( $this->plugins as $plugin => $elapsed ) {
			if ( $shown++ >= 15 ) {
				break;
			}
			$lines[] = sprintf( '    plugin %-48s %.3fs', $plugin, $elapsed );
		}

		$report = implode( "\n", $lines ) . "\n\n";

		if ( $total >= self::SLOW_THRESHOLD ) {
			$this->write( $report );
		}

		if ( $this->wants_browser_output() ) {
			echo "\n<!-- AUN BOOT PROFILE\n" . esc_html( $report ) . "-->\n";
		}
	}

	private function request_label() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '?';
		$uri = strtok( $uri, '?' );

		$kind = 'web';
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$kind = 'rest';
		} elseif ( wp_doing_ajax() ) {
			$kind = 'ajax';
		} elseif ( wp_doing_cron() ) {
			$kind = 'cron';
		} elseif ( is_admin() ) {
			$kind = 'admin';
		}

		/*
		 * For ajax and cron the URI is always the same file, which tells us
		 * nothing. Record what the request actually asked for.
		 */
		$detail = '';

		if ( 'ajax' === $kind && ! empty( $_REQUEST['action'] ) ) {
			$detail = ' action=' . sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( 'cron' === $kind ) {
			$due = array();
			foreach ( (array) _get_cron_array() as $ts => $hooks ) {
				if ( $ts > time() ) {
					break;
				}
				$due = array_merge( $due, array_keys( (array) $hooks ) );
			}
			if ( $due ) {
				$detail = ' hooks=' . implode( ',', array_slice( array_unique( $due ), 0, 4 ) );
			}
		}

		return sprintf( '[%s] %s%s', $kind, substr( $uri, 0, 60 ), $detail );
	}

	private function wants_browser_output() {
		return isset( $_GET['boot_profile'] ) && self::KEY === $_GET['boot_profile'];
	}

	private function write( $report ) {
		$dir = wp_upload_dir();
		if ( ! empty( $dir['error'] ) ) {
			return;
		}

		$file = trailingslashit( $dir['basedir'] ) . 'aun-boot-profile.log';

		if ( file_exists( $file ) && filesize( $file ) > 10485760 ) {
			return; // Stop at 10MB rather than fill the disk.
		}

		file_put_contents( $file, $report, FILE_APPEND | LOCK_EX );
	}
}

new AUN_Boot_Profiler();
