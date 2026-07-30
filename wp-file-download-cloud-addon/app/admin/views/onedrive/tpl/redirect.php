<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */


// No direct access.
defined('ABSPATH') || die();

?>
<div id="loggedInMsg">
    <div class="modal-header" style="text-align: left">
        <h2><?php esc_html_e('You\'re logged in, Enjoy!!!', 'wpfdAddon'); ?></h2>
        <p><?php esc_html_e('You can know use  OneDrive folders in WP File Download!', 'wpfdAddon'); ?></p>
    </div>
</div>
<style>
    #loggedInMsg {
        display: none;
    }
</style>
<script>
    window.opener.autoReload();
    self.close();
</script>
