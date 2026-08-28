/**
 * AUN Alpha SMS OTP Login — front-end controller.
 *
 * Each WooCommerce login form on the page gets its own independent instance.
 * No-JS visitors keep the normal email/password form (this script only ever
 * reveals/arranges the OTP UI; it never hides the password form server-side).
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.aunAlphaOtp === 'undefined' ) {
		return;
	}

	var CFG  = window.aunAlphaOtp;
	var I18N = CFG.i18n || {};

	/**
	 * One AJAX round trip.
	 *
	 * `retry` is internal. The nonce is printed into HTML that WP Rocket caches,
	 * so on a page that has been cached longer than the nonce lives it arrives
	 * already stale. Rather than dead-end a real customer, a 'bad_nonce' refusal
	 * mints a fresh nonce and replays the request exactly once. Any other
	 * refusal is passed straight through untouched.
	 */
	function post( action, data, done, retry ) {
		data = data || {};
		data.action = action;
		data.nonce  = CFG.nonce;

		function settle( resp ) {
			resp = resp || { success: false, data: {} };

			var stale = ! resp.success && resp.data && resp.data.code === 'bad_nonce';
			if ( stale && ! retry ) {
				$.post( CFG.ajaxurl, { action: 'aun_alpha_otp_nonce' }, null, 'json' )
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
		self.$native = self.$form.children().not( self.$wrap );

		self.$phone      = self.$wrap.find( '.aun-otp-phone' );
		self.$code       = self.$wrap.find( '.aun-otp-code' );
		self.$phoneInput = self.$wrap.find( '.aun-otp-phone-input' );
		self.$sendBtn    = self.$wrap.find( '.aun-otp-send' );
		self.$verifyBtn  = self.$wrap.find( '.aun-otp-verify' );
		self.$digits     = self.$wrap.find( '.aun-otp-digit' );
		self.$toOtp      = self.$wrap.find( '.aun-otp-to-otp' );
		self.$toPwd      = self.$wrap.find( '.aun-otp-to-pwd' );
		self.$sentTxt    = self.$wrap.find( '.aun-otp-sent-txt' );
		self.$change     = self.$wrap.find( '.aun-otp-change' );
		self.$resend     = self.$wrap.find( '.aun-otp-resend' );
		self.$timer      = self.$wrap.find( '.aun-otp-timer' );

		self.sendLabel   = self.$sendBtn.text();
		self.verifyLabel = self.$verifyBtn.text();
		self.busy        = false;
		self.timerId     = null;

		self.bind();
		self.$wrap.show();
		self.setState( ( CFG.otpFirst || CFG.hideEmail ) ? 'phone' : 'pwd' );
	}

	OtpForm.prototype.setState = function ( state ) {
		this.state = state;
		var pwd   = state === 'pwd';
		var phone = state === 'phone';
		var code  = state === 'code';

		this.$native.toggle( pwd );
		this.$phone.toggle( phone );
		this.$code.toggle( code );

		// Toggle buttons.
		this.$toOtp.toggle( pwd && ! CFG.hideEmail );
		this.$toPwd.toggle( phone && ! CFG.hideEmail );

		if ( phone ) {
			this.clearNotice();
			this.$phoneInput.trigger( 'focus' );
		}
		if ( code ) {
			this.$digits.eq( 0 ).trigger( 'focus' );
		}
		if ( ! code ) {
			this.stopTimer();
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

		self.$resend.on( 'click keypress', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			e.preventDefault();
			if ( $( this ).hasClass( 'aun-otp-disabled' ) ) {
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
				$this.prev( '.aun-otp-digit' ).trigger( 'focus' );
			}
		} );

		self.$digits.on( 'input', function () {
			var $this = $( this );
			$this.val( $this.val().replace( /\D/g, '' ).slice( 0, 1 ) );
			if ( $this.val() !== '' ) {
				$this.next( '.aun-otp-digit' ).trigger( 'focus' );
			}
			if ( self.getOtp().length === self.$digits.length ) {
				self.verify();
			}
		} );

		self.$digits.on( 'paste', function ( e ) {
			var data = ( e.originalEvent || e ).clipboardData.getData( 'text' ) || '';
			data = data.replace( /\D/g, '' );
			if ( ! data ) {
				return;
			}
			e.preventDefault();
			self.$digits.each( function ( i ) {
				$( this ).val( data.charAt( i ) || '' );
			} );
			var filled = Math.min( data.length, self.$digits.length );
			self.$digits.eq( Math.min( filled, self.$digits.length - 1 ) ).trigger( 'focus' );
			if ( self.getOtp().length === self.$digits.length ) {
				self.verify();
			}
		} );
	};

	OtpForm.prototype.getOtp = function () {
		var otp = '';
		this.$digits.each( function () {
			otp += $( this ).val();
		} );
		return otp;
	};

	OtpForm.prototype.notice = function ( msg, type ) {
		var $n = this.$wrap.find( '.aun-otp-' + ( this.state === 'code' ? 'code' : 'phone' ) + ' .aun-otp-notice' );
		$n.removeClass( 'aun-otp-notice-error aun-otp-notice-success' )
			.addClass( type === 'success' ? 'aun-otp-notice-success' : 'aun-otp-notice-error' )
			.html( msg )
			.show();
	};

	OtpForm.prototype.clearNotice = function () {
		this.$wrap.find( '.aun-otp-notice' ).hide().empty();
	};

	OtpForm.prototype.send = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		var phone = $.trim( self.$phoneInput.val() );
		if ( ! phone ) {
			self.notice( I18N.enterCode );
			return;
		}
		self.busy = true;
		self.clearNotice();
		self.$sendBtn.prop( 'disabled', true ).text( I18N.sending );

		post( 'aun_alpha_otp_send', { phone: phone }, function ( resp ) {
			self.busy = false;
			self.$sendBtn.prop( 'disabled', false ).text( self.sendLabel );

			if ( resp.success ) {
				self.setState( 'code' );
				self.$sentTxt.text( ( I18N.sentTo || '' ) + ' ' + ( resp.data.masked || '' ) );
				self.$digits.val( '' );
				self.startTimer( resp.data.resendWait || 0 );
				self.$digits.eq( 0 ).trigger( 'focus' );
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

		post( 'aun_alpha_otp_verify', { otp: otp }, function ( resp ) {
			self.busy = false;
			self.$verifyBtn.prop( 'disabled', false ).text( self.verifyLabel );

			if ( resp.success && resp.data ) {
				if ( resp.data.redirect ) {
					window.location = resp.data.redirect;
					return;
				}
				if ( resp.data.multipleAccounts ) {
					self.showAccounts( resp.data.accounts || [] );
					return;
				}
			}

			var d = resp.data || {};
			self.notice( d.message || I18N.connError );
			self.$digits.val( '' ).eq( 0 ).trigger( 'focus' );
			if ( d.reset ) {
				self.setState( 'phone' );
			}
		} );
	};

	OtpForm.prototype.resend = function () {
		var self = this;
		if ( self.busy ) {
			return;
		}
		self.busy = true;
		self.clearNotice();

		post( 'aun_alpha_otp_resend', {}, function ( resp ) {
			self.busy = false;
			var d = resp.data || {};
			if ( resp.success ) {
				self.notice( d.message || '', 'success' );
				self.$digits.val( '' ).eq( 0 ).trigger( 'focus' );
				self.startTimer( d.resendWait || 0 );
			} else {
				self.notice( d.message || I18N.connError );
				if ( d.reset ) {
					self.setState( 'phone' );
				}
			}
		} );
	};

	OtpForm.prototype.showAccounts = function ( accounts ) {
		var self = this;
		var html = '<div class="aun-otp-accounts">';
		html += '<h4>' + ( I18N.chooseAcct || '' ) + '</h4>';
		$.each( accounts, function ( i, a ) {
			html += '<button type="button" class="aun-otp-account button" data-id="' + parseInt( a.id, 10 ) + '">';
			html += '<span class="aun-otp-account-email">' + escapeHtml( a.email ) + '</span>';
			html += '<span class="aun-otp-account-meta">' + escapeHtml( a.name ) + ' · ' + parseInt( a.orders, 10 ) + ' ' + ( I18N.orders || '' ) + '</span>';
			html += '</button>';
		} );
		html += '</div>';

		self.$code.html( html );

		self.$code.find( '.aun-otp-account' ).on( 'click', function () {
			var id = $( this ).data( 'id' );
			self.$code.find( '.aun-otp-account' ).prop( 'disabled', true );
			$( this ).text( I18N.loggingIn );
			post( 'aun_alpha_otp_select', { user_id: id }, function ( resp ) {
				if ( resp.success && resp.data && resp.data.redirect ) {
					window.location = resp.data.redirect;
					return;
				}
				self.$code.find( '.aun-otp-account' ).prop( 'disabled', false );
				alert( ( resp.data && resp.data.message ) || I18N.connError );
			} );
		} );
	};

	OtpForm.prototype.startTimer = function ( seconds ) {
		var self = this;
		self.stopTimer();
		seconds = parseInt( seconds, 10 ) || 0;
		if ( seconds <= 0 ) {
			self.$resend.removeClass( 'aun-otp-disabled' );
			self.$timer.text( '' );
			return;
		}
		self.$resend.addClass( 'aun-otp-disabled' );
		var remaining = seconds;
		self.$timer.text( '(' + remaining + ')' );
		self.timerId = setInterval( function () {
			remaining--;
			if ( remaining <= 0 ) {
				self.stopTimer();
				self.$resend.removeClass( 'aun-otp-disabled' );
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
		$( '.aun-otp-wrap' ).each( function () {
			new OtpForm( this );
		} );
	} );

} )( jQuery );
