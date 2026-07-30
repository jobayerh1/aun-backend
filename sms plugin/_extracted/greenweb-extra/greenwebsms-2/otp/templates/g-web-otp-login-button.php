<?php
$registration_options = get_option(_greenweb_xd("\xc6\x9f\xb4\xb1\x87\xdb\xd7\xd0\xa6\xbe\x84\xdf\xcc\xc4\xb1\xbf\xce\xdc\xb0"));
if (isset($registration_options[_greenweb_xd("\xcd\x9f\xa7\xbd\x96\x94\xcb\xdd\xe4\xb5\x8c\x93\xca\xd8\xa3")] ) && ($registration_options[_greenweb_xd("\xcd\x9f\xa7\xbd\x96\x94\xcb\xdd\xe4\xb5\x8c\x93\xca\xd8\xa3")] != "no")) {
$login_first = "yes";
}
?>

<button style="margin-top:5px;margin-bottom:5px;" type="button" class="g-web-open-lwo-btn woocommerce-button button <?php echo esc_attr( wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) ? ' ' . wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) : '' ); ?>"><?php _e( _greenweb_xd("\xed\xdd\xa4\xbd\x8b\xd6\xd0\xd1\xbd\xb8\xc1\xbd\xf7\xe4"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?></button>

<div class="g-web-lwo-form-placeholder" <?php if( $login_first !== _greenweb_xd("\xd8\xd7\xb0") ): ?> style="display: none !important;" <?php endif; ?> >

    <div class="g-web-login-phinput-cont <?php echo esc_attr( implode( ' ', $cont_class ) ); ?>">

        <?php if( $label ): ?>
            <label class="<?php echo esc_attr( implode( ' ', $label_class ) ); ?>" for="g-web-login-phone"> <?php echo $label; ?>&nbsp;<span class="required">*</span></label>
        <?php endif; ?>

        <?php if( $is_login_popup ): ?>

            <div class="g-aff-group">
                <div class="g-aff-input-group">
                    <span class="g-aff-input-icon fas fa-phone"></span>
                    <input type="text" placeholder="<?php _e( _greenweb_xd("\x91\x83\xf4\xac\x9d\x8e\xdf\xc0\xb1\xa8"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?>" name="g-web-phone-login" class="g-web-phone-login g-web-phone-input <?php echo esc_attr( implode( ' ', $input_class ) ); ?>" required autocomplete="tel">
                </div>
            </div>

        <?php else: ?>

            <input type="text" placeholder="<?php _e( _greenweb_xd("\x91\x83\xf4\xac\x9d\x8e\xdf\xc0\xb1\xa8"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?>" name="g-web-phone-login" class="g-web-phone-login g-web-phone-input <?php echo esc_attr( implode( ' ', $input_class ) ); ?>" required  autocomplete="tel" >

        <?php endif; ?>

    </div>

<?php $csrf_expiry = gweb_get_csrf_expiry(); ?>
   <input type="hidden" name="g-web-csrf" value="<?php echo esc_attr($csrf_expiry); ?>">
   <input type="hidden" name="g-web-otp-login-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xbc\x8e\x95\xca\xda\x9a") . $csrf_expiry)); ?>">
   <input type="hidden" name="g-web-otp-verify-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xa6\x84\x80\xca\xd2\xbc\x89") . $csrf_expiry)); ?>">
   <input type="hidden" name="g-web-otp-resend-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xa2\x84\x81\xc6\xda\xa1\x89") . $csrf_expiry)); ?>">
   <input type="hidden" name="g-web-otp-login-select-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xbc\x8e\x95\xca\xda\x9a\xa5\xc4\xde\xa6\xb7\x91\xa9") . $csrf_expiry)); ?>">

    <input type="hidden" name="g-web-form-token" value="<?php echo $form_token; ?>">
    <input type="hidden" name="g-web-form-type" value="login_user_with_otp">
    <input type="hidden" name="redirect" value="<?php echo $redirect; ?>">

<button type="submit" class="g-web-login-otp-btn  woocommerce-button button <?php echo esc_attr( wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) ? ' ' . wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) : '' ); ?>"><?php _e( _greenweb_xd("\xed\xdd\xa4\xbd\x8b\xd6\xd0\xd1\xbd\xb8\xc1\xbd\xf7\xe4"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?></button></br><p></p>

<?php
        if (isset($registration_options[_greenweb_xd("\xcd\x9f\xa7\xbd\x96\x94\xcb\xdd\xe4\xb5\x8c\x93\xca\xd8\xa3")] ) && ($registration_options[_greenweb_xd("\xcd\x9f\xa7\xbd\x96\x94\xcb\xdd\xe4\xb5\x8c\x93\xca\xd8\xa3")] == "no")) {
    ?>

    <button type="button" class="g-web-low-back woocommerce-button button <?php echo esc_attr( wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) ? ' ' . wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) : '' ); ?>"><?php _e( _greenweb_xd("\xed\xdd\xa4\xbd\x8b\xd6\xd0\xd1\xbd\xb8\xc1\xb7\xce\xd5\xac\xba\x81\x94\xe3\x84\x84\x85\xd4\xcf\xa6\xa2\x85"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?></button>

<?php } else {
            echo "<style>.woocommerce-info > .showlogin {\n\tdisplay:none !important;\n\t}</style>";
        }
?>

</div>
