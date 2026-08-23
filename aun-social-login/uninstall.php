<?php
/**
 * Removes plugin settings on uninstall.
 * User accounts and their provider links are deliberately KEPT — deleting them
 * would lock real customers out of their orders.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

delete_option( 'aun_sl_options' );
