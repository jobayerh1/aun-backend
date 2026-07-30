<?php
/**
 * AUN App bridge configuration.
 *
 * 1. Copy this file to  bridge-config.php  (same folder).
 * 2. Replace the secret below with a LONG random string (40+ characters —
 *    letters and digits; e.g. generate one at https://www.random.org/strings/
 *    or mash the keyboard).
 * 3. Put the SAME secret in WordPress: AUN App -> Settings -> Support Tickets.
 *
 * Anyone without this secret gets a 403 before osTicket even loads.
 */

define( 'AUN_BRIDGE_SECRET', 'CHANGE-ME-TO-A-LONG-RANDOM-SECRET' );
