/**
 * Breo Alpha SMS OTP Login — front-end controller.
 *
 * Steps: phone → code → (new number only) name → signed in.
 * Each WooCommerce login form on the page gets its own independent instance.
 * No-JS visitors keep the normal email/password form (this script only ever
 * reveals/arranges the phone UI; it never hides the password form server-side).
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.breoAlphaOtp === 'undefined' ) {
		return;
	}

	var CFG  = window.breoAlphaOtp;
	var I18N = CFG.i18n || {};

	/**
	 * One AJAX round trip.
	 *
	 * `retry` is internal. The nonce is printed into HTML that a page cache may
	 * keep longer than the nonce lives, so a 'bad_nonce' refusal mints a fresh
	 * nonce and replays the request exactly once. Any other refusal is passed
	 * straight through untouched.
	 */
	function post( action, data, done, retry ) {
		data = data || {};
		data.action = action;
		data.nonce  = CFG.nonce;

		function settle( resp ) {
			resp = resp || { success: false, data: {} };

			var stale = ! resp.success && resp.data && resp.data.code === 'bad_nonce';
			if ( stale && ! retry ) {
				$.post( CFG.ajaxurl, { action: 'breo_alpha_otp_nonce' }, null, 'json' )
					.done( function ( n ) {
						if ( n && n.success && n.data && n.data.nonce ) {
							CFG.nonce = n.data.nonce;
							post( action, data, done, true );
						} else {
							done( resp );
						}
					} )
					.fail( function () {
						done( resp );
					} );
				return;
			}

			done( resp );
		}

		$.post( CFG.ajaxurl, data, null, 'json' )
			.done( settle )
			.fail( function ( xhr ) {
				// wp_send_json_error() may return a non-200 status with a JSON body;
				// jQuery routes that to .fail(), so read the real message from responseJSON.
				if ( xhr && xhr.responseJSON ) {
					settle( xhr.responseJSON );
				} else {
					done( { success: false, data: { message: I18N.connError } } );
				}
			} );
	}

	function OtpForm( wrap ) {
		var self = this;

		self.$wrap   = $( wrap );
		self.$form   = self.$wrap.closest( 'form' );
		// The social sign-in buttons (Breo Social Login) stay visible on every step.
		self.$native = self.$form.children().not( self.$wrap ).not( '.breo-sl' );

		self.$phone      = self.$wrap.find( '.breo-otp-phone' );
		self.$code       = self.$wrap.find( '.breo-otp-code' );
		self.$profile    = self.$wrap.find( '.breo-otp-profile' );
		self.$phoneInput = self.$wrap.find( '.breo-otp-phone-input' );
		self.$sendBtn    = self.$wrap.find( '.breo-otp-send' );
		self.$verifyBtn  = self.$wrap.find( '.breo-otp-verify' );
		self.$createBtn  = self.$wrap.find( '.breo-otp-create' );
		self.$name       = self.$wrap.find( '.breo-otp-name' );
		self.$email      = self.$wrap.find( '.breo-otp-email' );
		self.$digits     = self.$wrap.find( '.breo-otp-digit' );
		self.$toOtp      = self.$wrap.find( '.breo-otp-to-otp' );
		self.$toPwd      = self.$wrap.find( '.breo-otp-to-pwd' );
		self.$sentTxt    = self.$wrap.find( '.breo-otp-sent-txt' );
		self.$change     = self.$wrap.find( '.breo-otp-change' );
		self.$resend     = self.$wrap.find( '.breo-otp-resend' );
		self.$timer      = self.$wrap.find( '.breo-otp-timer' );

		self.sendLabel   = self.$sendBtn.text();
		self.verifyLabel = self.$verifyBtn.text();
		self.createLabel = self.$createBtn.text();
		self.busy        = false;
		self.timerId     = null;

		self.bind();
		self.$wrap.show();
		// Quiet on page load: don't pull focus (and scroll) to the form.
		self.setState( ( CFG.otpFirst || CFG.hideEmail ) ? 'phone' : 'pwd', true );
	}

	OtpForm.prototype.setState = function ( state, quiet ) {
		this.state = state;
		var pwd     = state === 'pwd';
		var phone   = state === 'phone';
		var code    = state === 'code';
		var profile = state === 'profile';

		this.$native.toggle( pwd );
		this.$phone.toggle( phone );
		this.$code.toggle( code );
		this.$profile.toggle( profile );

		// Switch links.
		this.$toOtp.toggle( pwd );
		this.$toPwd.toggle( phone && ! CFG.hideEmail );

		this.clearNotice();
		if ( ! code ) {
			this.stopTimer();
		}
		if ( quiet ) {
			return;
		}
		if ( phone ) {
			this.$phoneInput.trigger( 'focus' );
		} else if ( code ) {
			this.$digits.eq( 0 ).trigger( 'focus' );
		} else if ( profile ) {
			this.$name.trigger( 'focus' );
		}
	};

	OtpForm.prototype.bind = function () {
		var self = this;

		self.$toOtp.on( 'click', function ( e ) {
			e.preventDefault();
			self.setState( 'phone' );
		} );
		self.$toPwd.on( 'click', function ( e ) {
			e.preventDefault();
			self.setState( 'pwd' );
		} );
		self.$change.on( 'click keypress', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			e.preventDefault();
			self.setState( 'phone' );
		} );

		self.$sendBtn.on( 'click', function ( e ) {
			e.preventDefault();
			self.send();
		} );
		self.$phoneInput.on( 'keypress', function ( e ) {
			if ( e.which === 13 ) {
				e.preventDefault();
				self.send();
			}
		} );

		self.$verifyBtn.on( 'click', function ( e ) {
			e.preventDefault();
			self.verify();
		} );

		self.$createBtn.on( 'click', function ( e ) {
			e.preventDefault();
			self.create();
		} );
		self.$name.add( self.$email ).on( 'keypress', function ( e ) {
			if ( e.which === 13 ) {
				e.preventDefault(); // never submit the (hidden) password form
				self.create();
			}
		} );

		self.$resend.on( 'click keypress', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			e.preventDefault();
			if ( $( this ).hasClass( 'breo-otp-disabled' ) ) {
				return;
			}
			self.resend();
		} );

		self.bindDigits();
	};

	OtpForm.prototype.bindDigits = function () {
		var self = this;

		self.$digits.on( 'keydown', function ( e ) {
			var $this = $( this );
			if ( e.which === 8 && $this.val() === '' ) {
				$this.prev( '.breo-otp-digit' ).trigger( 'focus' );
			}
		} );

		self.$digits.on( 'input', function () {
			var $this = $( this );
			var val   = $this.val().replace( /\D/g, '' );
			// SMS autofill (autocomplete="one-time-code") drops the whole code into one box.
			if ( val.length > 1 ) {
				self.fill( val );
				return;
			}
			$this.val( val );
			if ( val !== '' ) {
				$this.next( '.breo-otp-digit' ).trigger( 'focus' );
			}
			if ( self.getOtp().length === self.$digits.length ) {
				self.verify();
			}
		} );

		self.$digits.on( 'paste', function ( e ) {
			var data = ( ( e.originalEvent || e ).clipboardData.getData( 'text' ) || '' ).replace( /\D/g, '' );
			if ( ! data ) {
				return;
			}
			e.preventDefault();
			self.fill( data );
		} );
	};

	OtpForm.prototype.fill = function ( data ) {
		this.$digits.each( function ( i ) {
			$( this ).val( data.charAt( i ) || '' );
		} );
		var filled = Math.min( data.length, this.$digits.length );
		this.$digits.eq( Math.min( filled, this.$digits.length - 1 ) ).trigger( 'focus' );
		if ( this.getOtp().length === this.$digits.length ) {
			this.verify();
		}
	};

	OtpForm.prototype.getOtp = function () {
		var otp = '';
		this.$digits.each( function () {
			otp += $( this ).val();
		} );
		return otp;
	};

	OtpForm.prototype.notice = function ( msg, type ) {
		var step = this.state === 'pwd' ? 'phone' : this.state;
		var $n   = this.$wrap.find( '.breo-otp-' + step + ' .breo-otp-notice' );
		$n.removeClass( 'breo-otp-notice-error breo-otp-notice-success' )
			.addClass( type === 'success' ? 'breo-otp-notice-success' : 'breo-otp-notice-error' )
			.text( msg )
			.show();
	};

	OtpForm.prototype.clearNotice = function () {
		this.$wrap.find( '.breo-otp-notice' ).hide().empty();
	};

	OtpForm.prototype.send = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		var phone = $.trim( self.$phoneInput.val() );
		if ( ! phone ) {
			self.notice( I18N.enterPhone );
			self.$phoneInput.trigger( 'focus' );
			return;
		}
		self.busy = true;
		self.clearNotice();
		self.$sendBtn.prop( 'disabled', true ).text( I18N.sending );

		post( 'breo_alpha_otp_send', { phone: phone }, function ( resp ) {
			self.busy = false;
			self.$sendBtn.prop( 'disabled', false ).text( self.sendLabel );

			if ( resp.success ) {
				self.$digits.val( '' );
				self.setState( 'code' );
				self.$sentTxt.text( ( I18N.sentTo || '' ) + ' ' + ( resp.data.masked || '' ) );
				self.startTimer( resp.data.resendWait || 0 );
			} else {
				self.notice( ( resp.data && resp.data.message ) || I18N.connError );
			}
		} );
	};

	OtpForm.prototype.verify = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		var otp = self.getOtp();
		if ( otp.length !== self.$digits.length ) {
			self.notice( I18N.enterCode );
			return;
		}
		self.busy = true;
		self.clearNotice();
		self.$verifyBtn.prop( 'disabled', true ).text( I18N.verifying );

		post( 'breo_alpha_otp_verify', { otp: otp }, function ( resp ) {
			if ( resp.success && resp.data && resp.data.redirect ) {
				self.$verifyBtn.text( I18N.loggingIn );
				window.location = resp.data.redirect;
				return;
			}
			self.busy = false;
			self.$verifyBtn.prop( 'disabled', false ).text( self.verifyLabel );

			if ( resp.success && resp.data ) {
				if ( resp.data.needsProfile ) {
					self.setState( 'profile' );
					return;
				}
				if ( resp.data.multipleAccounts ) {
					self.showAccounts( resp.data.accounts || [] );
					return;
				}
			}

			var d = resp.data || {};
			if ( d.reset ) {
				self.setState( 'phone' );
			} else {
				self.$digits.val( '' ).eq( 0 ).trigger( 'focus' );
			}
			self.notice( d.message || I18N.connError );
		} );
	};

	OtpForm.prototype.create = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		var name = $.trim( self.$name.val() );
		if ( name.length < 2 ) {
			self.notice( I18N.enterName );
			self.$name.trigger( 'focus' );
			return;
		}
		self.busy = true;
		self.clearNotice();
		self.$createBtn.prop( 'disabled', true ).text( I18N.creating );

		post( 'breo_alpha_otp_register', { name: name, email: $.trim( self.$email.val() ) }, function ( resp ) {
			if ( resp.success && resp.data && resp.data.redirect ) {
				window.location = resp.data.redirect;
				return;
			}
			self.busy = false;
			self.$createBtn.prop( 'disabled', false ).text( self.createLabel );
			var d = resp.data || {};
			if ( d.reset ) {
				self.setState( 'phone' );
			}
			self.notice( d.message || I18N.connError );
		} );
	};

	OtpForm.prototype.resend = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		self.busy = true;
		self.clearNotice();

		post( 'breo_alpha_otp_resend', {}, function ( resp ) {
			self.busy = false;
			var d = resp.data || {};
			if ( resp.success ) {
				self.notice( d.message || '', 'success' );
				self.$digits.val( '' ).eq( 0 ).trigger( 'focus' );
				self.startTimer( d.resendWait || 0 );
			} else {
				if ( d.reset ) {
					self.setState( 'phone' );
				}
				self.notice( d.message || I18N.connError );
			}
		} );
	};

	OtpForm.prototype.showAccounts = function ( accounts ) {
		var self = this;
		var html = '<div class="breo-otp-accounts">';
		html += '<h4>' + escapeHtml( I18N.chooseAcct || '' ) + '</h4>';
		$.each( accounts, function ( i, a ) {
			html += '<button type="button" class="breo-otp-account button" data-id="' + parseInt( a.id, 10 ) + '">';
			html += '<span class="breo-otp-account-email">' + escapeHtml( a.email ) + '</span>';
			html += '<span class="breo-otp-account-meta">' + escapeHtml( a.name ) + ' · ' + parseInt( a.orders, 10 ) + ' ' + escapeHtml( I18N.orders || '' ) + '</span>';
			html += '</button>';
		} );
		html += '</div>';

		self.$code.html( html );

		self.$code.find( '.breo-otp-account' ).on( 'click', function () {
			var id = $( this ).data( 'id' );
			self.$code.find( '.breo-otp-account' ).prop( 'disabled', true );
			$( this ).text( I18N.loggingIn );
			post( 'breo_alpha_otp_select', { user_id: id }, function ( resp ) {
				if ( resp.success && resp.data && resp.data.redirect ) {
					window.location = resp.data.redirect;
					return;
				}
				self.$code.find( '.breo-otp-account' ).prop( 'disabled', false );
				alert( ( resp.data && resp.data.message ) || I18N.connError );
			} );
		} );
	};

	OtpForm.prototype.startTimer = function ( seconds ) {
		var self = this;
		self.stopTimer();
		seconds = parseInt( seconds, 10 ) || 0;
		if ( seconds <= 0 ) {
			self.$resend.removeClass( 'breo-otp-disabled' );
			self.$timer.text( '' );
			return;
		}
		self.$resend.addClass( 'breo-otp-disabled' );
		var remaining = seconds;
		self.$timer.text( '(' + remaining + ')' );
		self.timerId = setInterval( function () {
			remaining--;
			if ( remaining <= 0 ) {
				self.stopTimer();
				self.$resend.removeClass( 'breo-otp-disabled' );
				self.$timer.text( '' );
			} else {
				self.$timer.text( '(' + remaining + ')' );
			}
		}, 1000 );
	};

	OtpForm.prototype.stopTimer = function () {
		if ( this.timerId ) {
			clearInterval( this.timerId );
			this.timerId = null;
		}
	};

	function escapeHtml( str ) {
		return $( '<div>' ).text( str == null ? '' : String( str ) ).html();
	}

	$( function () {
		$( '.breo-otp-wrap' ).each( function () {
			new OtpForm( this );
		} );
	} );

} )( jQuery );
