jQuery(document).ready(function($){


	var otpForm = function( $otpForm, $parentForm ){
		var self 			= this;
		self.$otpForm 		= $otpForm;
		self.$parentForm 	= $parentForm;
		self.$verifyBtn 	= self.$otpForm.find('.g-web-otp-verify-btn');
		self.$inputs 		= self.$otpForm.find('.g-web-otp-input');
		self.$noticeCont 	= self.$otpForm.find('.g-web-notice');
		self.$resendLink 	= self.$otpForm.find('.g-web-otp-resend-link');
		self.noticeTimout 	= self.resendTimer = false;
		self.activeNumber 	= self.activeCode = '';
		self.formType 		= self.$parentForm.find('input[name="g-web-form-type"]').length ? self.$parentForm.find('input[name="g-web-form-type"]').val() : '';

		//Methods
		self.getPhoneNumber 	= self.getPhoneNumber.bind(this);
		self.validateInputs 	= self.validateInputs.bind(this);
		self.setPhoneData 		= self.setPhoneData.bind(this);
		self.onSuccess 			= self.onSuccess.bind(this);
		self.startResendTimer 	= self.startResendTimer.bind(this);
		self.showNotice 		= self.showNotice.bind(this);
		self.onOTPSent 			= self.onOTPSent.bind(this);

		self.$otpForm.on( 'keyup', '.g-web-otp-input', self.switchInput );
		self.$otpForm.on( 'paste', '.g-web-otp-input', { otpForm: self }, self.pasteOTP );
		self.$otpForm.on( 'submit', { otpForm: self }, self.onSubmit );
		self.$resendLink.on( 'click', { otpForm: self }, self.resendOTP );
		self.$otpForm.find('.g-web-otp-no-change').on( 'click', { otpForm: self }, self.onNumberChange );

		self.$otpForm.find( 'input[name="g-web-form-token"]' ).val( self.$parentForm.find( 'input[name="g-web-form-token"]' ).val() );

	}


	otpForm.prototype.onSubmit = function( event ){
		event.preventDefault();
		var otpForm = event.data.otpForm;
		if( !otpForm.validateInputs() ) return false;

		var form_data = {
			'otp': otpForm.getOtpValue(),
			'token': otpForm.$otpForm.find( 'input[name="g-web-form-token"]' ).val(),
			'action': 'g_web_otp_form_submit',
			'parentFormData': objectifyForm( otpForm.$parentForm.serializeArray() ),
			'g-web-csrf': otpForm.$parentForm.find('[name="g-web-csrf"]').val(),
			'g-web-otp-verify-nonce': otpForm.$parentForm.find('[name="g-web-otp-verify-nonce"]').val()
		}

		$.ajax({
			url: g_web_phone_localize.adminurl,
			type: 'POST',
			data: form_data,
			success: function(response){
				if( response.notice ){
					otpForm.showNotice( response.notice );
				}

				if( response.error === 0 ){
					otpForm.onSuccess();
					otpForm.$otpForm.trigger( 'g_web_on_otp_success', [response] );
				}
			}
		});

	}

	otpForm.prototype.showNotice = function( notice ){
		var _t = this;
		clearTimeout(this.noticeTimout);
		this.$noticeCont.html( notice ).show();
		this.$otpForm.find('.g-web-otp-notice-cont').show();
		this.noticeTimout = setTimeout(function(){
			_t.$noticeCont.attr('style','display:none !important');
			_t.$otpForm.find('.g-web-otp-notice-cont').attr('style','display:none !important');
		},4000)
	}

	otpForm.prototype.onSuccess = function(){
		this.$otpForm.attr('style','display:none !important');
		this.$inputs.val('');
		this.$parentForm.show();
	}

	otpForm.prototype.switchInput = function( event ){

		if( $(this).val().length === parseInt( $(this).attr('maxlength') ) && $(this).next('input.g-web-otp-input').length !== 0 ){
			$(this).next('input.g-web-otp-input').focus();
		}

		//Backspace is pressed
		if( $(this).val().length === 0 && event.keyCode == 8 && $(this).prev('input.g-web-otp-input').length !== 0 ){
			$(this).prev('input.g-web-otp-input').focus().val('');
		}

	}

	otpForm.prototype.pasteOTP = function( event ){
		var otpForm = event.data.otpForm;
		var pasteData = (event.originalEvent || event).clipboardData.getData('text');
		if( !pasteData || !/^\d+$/.test( pasteData ) ) return;

		event.preventDefault();

		var inputs = otpForm.$inputs;
		var digits = pasteData.substring( 0, inputs.length ).split('');

		inputs.each( function( i ){
			if( i < digits.length ){
				$(this).val( digits[i] );
			}
		});

		// Focus the next empty input or the last one
		var focusIndex = digits.length < inputs.length ? digits.length : inputs.length - 1;
		inputs.eq( focusIndex ).focus();
	}

	otpForm.prototype.onNumberChange = function( event ){
		var otpForm = event.data.otpForm;
		otpForm.$otpForm.attr('style','display:none !important');
		otpForm.$parentForm.show();
		otpForm.$inputs.val('');
	}

	otpForm.prototype.validateInputs = function(){
		var passedValidation = true;
		this.$inputs.each( function( index, input ){
			var $input = $(input);
			if( !parseInt( $input.val() ) && parseInt( $input.val() ) !== 0 ){
				$input.focus();
				passedValidation = false;
				return false;
			}
		} );
		return passedValidation;
	}

	otpForm.prototype.getOtpValue = function(){
		var otp = '';
		this.$inputs.each( function( index, input ){
			otp += $(input).val();
		});
		return otp;
	}

	otpForm.prototype.setPhoneData = function( data ){
		this.$otpForm.find('.g-web-otp-no-txt').html( data.otp_txt );
		this.activeNumber = data.phone_no;
		this.activeCode   = data.phone_code;
	}

	otpForm.prototype.getPhoneNumber = function( $only ){
		
	}

	otpForm.prototype.startResendTimer = function(){
		var _t 				= this,
			$cont 			= this.$otpForm.find('.g-web-otp-resend'),
			$resendLink 	= $cont.find('.g-web-otp-resend-link'),
			$timer 			= $cont.find('.g-web-otp-resend-timer'),
			resendTime 		= parseInt( g_web_phone_localize.resend_wait );

		if( resendTime === 0 ) return;

		$resendLink.addClass('g-web-disabled');

		clearInterval( this.resendTimer );

		this.resendTimer = setInterval(function(){
			$timer.html('('+resendTime+')');
			if( resendTime <= 0 ){
				clearInterval( _t.resendTimer );
				$resendLink.removeClass('g-web-disabled');
				$timer.html('');
			}
			resendTime--;
		},1000) 
	}

	otpForm.prototype.resendOTP = function( event ){
		event.preventDefault();
		var otpForm = event.data.otpForm;

		otpForm.startResendTimer();

		var form_data = {
			action: 'g_web_resend_otp',
			'g-web-csrf': otpForm.$parentForm.find('[name="g-web-csrf"]').val(),
			'g-web-otp-resend-nonce': otpForm.$parentForm.find('[name="g-web-otp-resend-nonce"]').val()
		}

		$.ajax({
			url: g_web_phone_localize.adminurl,
			type: 'POST',
			data: form_data,
			success: function(response){
				if(response.notice){
					otpForm.showNotice( response.notice );
				}
			}
		});
	}

	otpForm.prototype.onOTPSent = function( response ){
		var otpFormHandler = this;
		otpFormHandler.$otpForm.show();
		otpFormHandler.startResendTimer();
		otpFormHandler.setPhoneData( response );
		otpFormHandler.$parentForm.attr('style','display:none !important');
	}

	var i = 0;
	var PhoneForm = function( $phoneForm ){
		var self = this;
		self.$phoneForm = $phoneForm;
		self.prepare();
		self.$phoneInput 	= self.$phoneForm.find( '.g-web-phone-input' );
		self.$phoneCode 	= self.$phoneForm.find( '.g-web-phone-cc' );
		self.$otpForm 		= self.$phoneForm.next('form.g-web-otp-form');
		self.otpFormHandler = self.$otpForm.length ? new otpForm( self.$otpForm, self.$phoneForm ) : null;
		self.$noticeCont 	= self.$phoneForm.find('.g-web-notice');
		self.$noticeContWrap = self.$phoneForm.find('.g-web-notice-cont');
		self.formType 		= self.$phoneForm.find('input[name="g-web-form-type"]').length ? self.$phoneForm.find('input[name="g-web-form-type"]').val() : ''
		self.$submit_btn 	= self.$phoneForm.find('button[type="submit"]');

		//Methods
		self.sendFormData 				= self.sendFormData.bind(this);
		self.getPhoneNumber 			= self.getPhoneNumber.bind(this);
		self.getOTPFormPreviousState 	= self.getOTPFormPreviousState.bind(this);
	}

	PhoneForm.prototype.prepare = function(){
		$( $('.g-web-form-placeholder').html() ).insertAfter(this.$phoneForm); //OTP form
		this.$phoneForm.prepend( '<div class="g-web-notice-cont" style="display:none"><div class="g-web-notice"></div></div>' ); //Notice element
	}


	PhoneForm.prototype.sendFormData = function( form_data ){

		var phoneForm 		= this;

		if( phoneForm.$submit_btn.length && phoneForm.$submit_btn.attr('name') ){
			form_data = form_data + '&' + phoneForm.$submit_btn.attr('name') + '=' + phoneForm.$submit_btn.val();
		}

		phoneForm.$submit_btn.addClass('g-web-processing');

		$.ajax({
			url: g_web_phone_localize.adminurl,
			type: 'POST',
			data: form_data,
			success: function(response){

				if( response.notice ){
					phoneForm.$noticeCont.html( response.notice ).show();
					phoneForm.$noticeContWrap.show();
				}
				//Display otp form
				if( response.otp_sent ){
					phoneForm.otpFormHandler.onOTPSent( response );
				}

				phoneForm.$phoneForm.trigger( 'g_web_phone_register_form_submit', [ form_data, response ] );

				phoneForm.$submit_btn.removeClass('g-web-processing');
				
			}
		});
	}


	PhoneForm.prototype.getPhoneNumber = function( $only ){
		var phoneForm 		= this,
			phoneNumber 	= '';

		code 	= phoneForm.$phoneCode.length && phoneForm.$phoneCode.val().trim() ? phoneForm.$phoneCode.val().toString() : '';
		number 	= phoneForm.$phoneInput.val().toString().trim();

		if( $only === 'code' ){
			return code;
		}
		else if( $only === 'number' ){
			return number;
		}
		else{
			return code+number;
		}
	}


	PhoneForm.prototype.getOTPFormPreviousState = function(){
		var phoneFormHandler = this;
		//If requested for changing phone number & same number is put again.
 		if( ( !phoneFormHandler.$phoneCode.length || phoneFormHandler.otpFormHandler.activeCode ===  phoneFormHandler.getPhoneNumber('code') ) && phoneFormHandler.otpFormHandler.activeNumber ===  phoneFormHandler.getPhoneNumber('number') ){
 			phoneFormHandler.$otpForm.show();
 			phoneFormHandler.$phoneForm.attr('style','display:none !important');
 			return true;
 		}

 		return false;
	}


	var RegisterForm = function( $phoneForm ){

		PhoneForm.call( this, $phoneForm );
		var self 			= this;
	
		self.$changePhone 	= self.$phoneForm.find('.g-web-reg-phone-change');
		self.verifiedPHone 	= false;

		//Methods
		self.fieldsValidation = self.fieldsValidation.bind(this);

		//event
		self.$phoneForm.on( 'submit', { phoneForm: self }, self.onSubmit );
		self.$otpForm.on( 'g_web_on_otp_success', { phoneForm: self }, self.onOtpSuccess );
		self.$changePhone.on( 'click', { phoneForm: self }, self.changePhone );

		//If this is an update form
		if( self.getPhoneNumber( 'number' ) && self.formType === 'update_user' ){
			self.verifiedPHone = self.getPhoneNumber();
		}

	}


	RegisterForm.prototype = Object.create( PhoneForm.prototype );

	RegisterForm.prototype.fieldsValidation = function(){
		var phoneFormHandler = this,
			$phoneForm 		= phoneFormHandler.$phoneForm,
			error_string 		= ''; 

		if( phoneFormHandler.getPhoneNumber( 'number' ).length !== 11 ){
			error_string 		= g_web_phone_localize.notices.invalid_phone;
		}
			
		//If is a woocommerce register form
		if( $phoneForm.find('input[name="woocommerce-register-nonce"]').length ){

			var $emailField 	= $phoneForm.find('input[name="email"]'),
				$passwordField 	= $phoneForm.find('input[name="password"]');

			//If email field is empty
			if( $emailField.length && !$emailField.val() ){
				error_string = g_web_phone_localize.notices.empty_email;
			}

			if( $passwordField.length && !$passwordField.val() ){
				error_string = g_web_phone_localize.notices.empty_password;
			}

		}



		if( error_string ){
			phoneFormHandler.$noticeCont.html( error_string ).show();
			phoneFormHandler.$noticeContWrap.show();
			return false;
		}

		return true;

	}

	RegisterForm.prototype.onSubmit = function( event ){
		var phoneFormHandler = event.data.phoneForm;
		phoneFormHandler.$noticeCont.attr('style','display:none !important');
		phoneFormHandler.$noticeContWrap.attr('style','display:none !important');

		//If number is optional
		if( phoneFormHandler.getPhoneNumber('number').length === 0 && g_web_phone_localize.show_phone !== 'required' ){
			return;
		}

		//Check if OTP form exists & number is already verified 
		if( !phoneFormHandler.otpFormHandler || phoneFormHandler.verifiedPHone === phoneFormHandler.getPhoneNumber() ) return;

		event.preventDefault();
 		event.stopImmediatePropagation();

 		if( !phoneFormHandler.fieldsValidation() ) return;

 		//If requested for changing phone number & same number is not put again.
 		
 		if( !phoneFormHandler.getOTPFormPreviousState() ) {
 			phoneFormHandler.verifiedPHone = false;
 			var form_data = phoneFormHandler.$phoneForm.serialize()+'&action=g_web_phone_register_form_submit';
			phoneFormHandler.sendFormData( form_data );
 			$(window).scrollTop( phoneFormHandler.$phoneForm.offset().top );
		}
	}


	RegisterForm.prototype.onOtpSuccess = function( event, response ){
		var phoneForm 	= event.data.phoneForm,
			otpFormHandler 	= phoneForm.otpFormHandler;

		phoneForm.verifiedPHone = phoneForm.getPhoneNumber();

		phoneForm.$phoneInput
			.prop('readonly', true)
			.addClass( 'g-web-disabled' );
		phoneForm.$changePhone.show();

		if( response.notice ){
			if( g_web_phone_localize.auto_submit_reg === "yes" ){
				phoneForm.$phoneForm.find('[type="submit"]').trigger('click');
			}
			phoneForm.$noticeCont.html( response.notice ).show();
			phoneForm.$noticeContWrap.show();
		}

	}

	RegisterForm.prototype.changePhone = function( event ){
		$(this).attr('style','display:none !important');
		event.data.phoneForm.$phoneInput
			.prop( 'readonly', false )
			.focus();
	}


	$('input[name="g-web-reg-phone"]').each( function( key, form ){
		new RegisterForm( $(this).closest('form') );
	} );





	var LoginForm = function( $phoneForm, $parentForm ){

		var self 				= this;
		self.$parentForm 		= $parentForm;
		self.$phoneForm 		= $phoneForm;

		this.createFormHTML();

		PhoneForm.call( this, $phoneForm );

		self.$parentOTPLoginBtn = self.$parentForm.find('.g-web-open-lwo-btn');
		self.$loginOTPBtn 		= self.$phoneForm.find( '.g-web-login-otp-btn' );

		//event
		self.$phoneForm.on( 'submit', { phoneForm: self }, self.onLogin );
		self.$parentOTPLoginBtn.on( 'click', { phoneForm: self }, self.openLoginForm );
		self.$otpForm.on( 'g_web_on_otp_success', { phoneForm: self }, self.onOtpSuccess );

		//Back to parent form
		$('.g-web-low-back').on('click',function(){
			self.$parentForm.show();
			self.$phoneForm.attr('style','display:none !important');
		})

	}


	LoginForm.prototype = Object.create( PhoneForm.prototype );

	LoginForm.prototype.createFormHTML = function(){
		
		var formHTMLPlaceholder = this.$parentForm.find('.g-web-lwo-form-placeholder');
		//attach form elements
		this.$phoneForm.append( formHTMLPlaceholder.html() );
		formHTMLPlaceholder.remove();
		//If otp login form is displayed first
		if( g_web_phone_localize.login_first === "yes" ){
			this.$parentForm.attr('style','display:none !important');
		}
		else{
			this.$phoneForm.attr('style','display:none !important');
		}
	}

	LoginForm.prototype.onLogin = function( event ){

		event.preventDefault();
		event.stopImmediatePropagation();
		var phoneFormHandler = event.data.phoneForm;
		phoneFormHandler.$noticeCont.attr('style','display:none !important');
		phoneFormHandler.$noticeContWrap.attr('style','display:none !important');

		if( !phoneFormHandler.getOTPFormPreviousState()  ){
			var form_data = 'action=g_web_login_with_otp&'+phoneFormHandler.$phoneForm.serialize()
			phoneFormHandler.sendFormData( form_data );
		}

	}


	LoginForm.prototype.onOtpSuccess = function( event, response ){
		var phoneFormHandler = event.data.phoneForm;

		if( response.notice ){
			phoneFormHandler.$noticeCont.html( response.notice ).show();
			phoneFormHandler.$noticeContWrap.show();
		}

		if( response.multiple_accounts ){
			// Multiple accounts share this phone — show account selector
			showAccountSelector( response.accounts, response.phone_no, phoneFormHandler );
		} else if( response.redirect ){
			window.location = response.redirect;
		}

	}

	/**
	 * Show account selector when multiple accounts share the same phone number.
	 */
	function showAccountSelector( accounts, phoneNo, phoneFormHandler ) {
		var csrf = phoneFormHandler.$phoneForm.find('[name="g-web-csrf"]').val();
		var redirect = phoneFormHandler.$phoneForm.find('[name="redirect"]').val();

		// Add notice container inside $otpForm (not $phoneForm — $phoneForm is hidden at this point)
		var html = '<div class="g-web-account-selector">';
		html += '<div class="g-web-notice"></div>';
		html += '<h3>' + g_web_phone_localize.select_account_txt + '</h3>';
		html += '<p class="g-web-account-subtitle">' + g_web_phone_localize.phone_linked_txt + ' <strong>' + phoneNo + '</strong></p>';
		html += '<div class="g-web-account-list">';

		$.each( accounts, function( i, account ) {
			var dateJoined = new Date( account.registered );
			var dateStr = dateJoined.toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric' } );
			html += '<div class="g-web-account-item" data-user-id="' + account.id + '" tabindex="0" role="button">';
			html += '<div class="g-web-account-item-email">' + account.email + '</div>';
			html += '<div class="g-web-account-item-meta">' + account.display_name + ' &middot; ' + account.order_count + ' orders &middot; Member since ' + dateStr + '</div>';
			html += '</div>';
		});

		html += '</div></div>';

		// Replace OTP form content with the account selector
		phoneFormHandler.$otpForm.html( html );
		phoneFormHandler.$otpForm.show();
		phoneFormHandler.$parentForm.attr('style','display:none !important');
		phoneFormHandler.$phoneForm.attr('style','display:none !important');

		// Generate a select nonce for the CSRF session
		var selectNonce = '';

		// Handle account selection
		phoneFormHandler.$otpForm.find('.g-web-account-item').on('click', function() {
			var userId = $(this).data('user-id');
			$(this).addClass('g-web-account-selected');
			phoneFormHandler.$otpForm.find('.g-web-account-item').not(this).css('pointer-events','none');
			phoneFormHandler.$otpForm.find('.g-web-notice').html( '<div class="g-web-loading"><span class="g-web-spinner"></span>Logging in...</div>' ).show();

			// Get the select nonce from a hidden field (generated in the form)
			selectNonce = phoneFormHandler.$phoneForm.find('[name="g-web-otp-login-select-nonce"]').val();

			$.ajax({
				url: g_web_phone_localize.adminurl,
				type: 'POST',
				data: {
					action: 'g_web_otp_login_select_account',
					user_id: userId,
					phone_no: phoneNo,
					redirect: redirect,
					'g-web-csrf': csrf,
					'_wpnonce': selectNonce,
				},
				success: function( response ) {
					if( response.redirect ) {
						window.location = response.redirect;
						return;
					}
					if( response.notice ) {
						phoneFormHandler.$otpForm.find('.g-web-notice').html( response.notice ).show();
						phoneFormHandler.$otpForm.find('.g-web-account-item').removeClass('g-web-account-selected').css('pointer-events','auto');
					}
				},
				error: function() {
					phoneFormHandler.$otpForm.find('.g-web-notice').html( '<div class="g-web-notice-error">Connection error. Please try again.</div>' ).show();
					phoneFormHandler.$otpForm.find('.g-web-account-item').removeClass('g-web-account-selected').css('pointer-events','auto');
				}
			});
		});
	}
	LoginForm.prototype.openLoginForm = function( event, response ){
		var phoneFormHandler = event.data.phoneForm;
		phoneFormHandler.$phoneForm.show();
		phoneFormHandler.$parentForm.attr('style','display:none !important');
		$('.g-el-notice').attr('style','display:none !important');
	}


	$('.g-web-open-lwo-btn').each( function( key, el ){
		var $parentForm = $(this).closest('form');
		//attach login with otp form
		$('<form class="g-lwo-form woocommerce-form woocommerce-form-login login"></form>').insertAfter( $parentForm );
		var $loginForm = $parentForm.next('.g-lwo-form');
		new LoginForm( $loginForm, $parentForm );
	} );


	//converts serializeArray to json object
	function objectifyForm(formArray) {//serialize data function

	  var returnArray = {};
	  for (var i = 0; i < formArray.length; i++){
	    returnArray[formArray[i]['name']] = formArray[i]['value'];
	  }
	  return returnArray;
	}


	$('.g-el-form-popup, .g-el-form-inline').on('g_el_form_tab_switched', function(){

		$(this).find('.g-web-notice-cont, .g-web-notice').attr('style','display:none !important');

		var lwoForm = $(this).find('.g-lwo-form'),
			parentLoginForm = $(this).find('.g-el-form-login');

		//If login with OTP form is to be displayed first.
		if( parentLoginForm.length ){
			if( g_web_phone_localize.login_first === "yes" && lwoForm.length ){
				lwoForm.show();
				parentLoginForm.attr('style','display:none !important');
			}
			else{
				lwoForm.attr('style','display:none !important');
				parentLoginForm.show();
			}
		}

		$otpForm = $(this).find( '.g-web-otp-form' ); 
		if( $otpForm.length ){
			$otpForm.attr('style','display:none !important');
		}
	})


})