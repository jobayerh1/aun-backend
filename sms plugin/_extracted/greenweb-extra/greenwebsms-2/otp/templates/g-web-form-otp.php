<div class="g-web-form-placeholder">
	<form class="g-web-otp-form">

		<div class="g-web-otp-sent-txt">
			<span class="g-web-otp-no-txt"></span>
			<span class="g-web-otp-no-change"> <?php _e( "🛠️ Change", _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?></span>
		</div>

		<div class="g-web-otp-notice-cont">
			<div class="g-web-notice"></div>
		</div>

		<div class="g-web-otp-input-cont">
			<?php for ( $i= 0; $i < $otp_length; $i++ ): ?>
				<input type="number" maxlength="1" autocomplete="off" inputmode="numeric" name="g-web-otp[]" class="g-web-otp-input">
			<?php endfor; ?>
		</div>

		<input type="hidden" name="g-web-otp-phone-no" >
		<input type="hidden" name="g-web-otp-phone-code" >

		<button type="submit" class="button btn g-web-otp-verify-btn  <?php if ( function_exists( _greenweb_xd("\xd6\xd1\x9c\xa3\x95\xa9\xd3\xd0\xac\xbd\x84\xad\xc4\xd1\xb1\x89\xc4\xde\xa6\xb9\x80\x98\xd3\xe7\xaa\xbc\x80\x81\xd0\xeb\xab\xb7\xcc\xd7") ) ) { echo esc_attr( wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) ? ' ' . wc_wp_theme_get_element_class_name( _greenweb_xd("\xc3\xc7\xb7\xa0\x8a\x98") ) : '' ); ?>"><?php _e( _greenweb_xd("\xf2\xc7\xa1\xb9\x8c\x82\x87\xf7\x9d\x80"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); } ?> </button>

		<div class="g-web-otp-resend">
			<a class="g-web-otp-resend-link"><?php _e( _greenweb_xd("\x51\x2d\x57\x50\xc5\xa4\xc2\xcb\xac\xbe\x85\xd2\xec\xe0\x95"), _greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd") ); ?></a>
			<span class="g-web-otp-resend-timer"></span>
		</div>

		<input type="hidden" name="g-web-form-token" value="">

	</form>

</div>