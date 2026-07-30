<?php

if ( ! defined( _greenweb_xd("\xe0\xf0\x90\x84\xa4\xa2\xef") ) ) {
	exit;
}

	$phone_codes[_greenweb_xd("\xe3\xf6")] = "+880 (BD)";

$option_name = _greenweb_xd("\xc6\x9f\xb4\xb1\x87\xdb\xd7\xd0\xa6\xbe\x84\xdf\xcc\xc4\xb1\xbf\xce\xdc\xb0");

$settings = array(

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'section',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'section',
		_greenweb_xd("\xc8\xd6") 			=> 'otp-section',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Woocommerce Customers OTP login/registration Settings',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'otp-digits',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'OTP Digits',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '4',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'otp-expiry',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'OTP Expiry',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '120',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'In Seconds'
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'otp-resend-limit',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Resend OTP Limit',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '8',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'otp-resend-wait',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Resend OTP Wait Time',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '30',
		_greenweb_xd("\xc5\xd7\xb0\xb7")			=> 'Waiting time to resend a new OTP (In seconds) '
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'gweb-otp-session-timeout',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Login Page OTP session time (in seconds)',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '600',
		_greenweb_xd("\xc5\xd7\xb0\xb7")			=> 'Set it too low to prevent OTP abuse and high enough to avoid normal users session time out'
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'number',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'otp-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'otp-incorrect-limit',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Maximum OTP Request Limit Per IP Or Mobile',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '10',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'একদিনে একটি IP or Mobile নাম্বার থেকে সর্বোচ্চ কতবার OTP রিকোয়েস্ট দেওয়া যাবে তা সেট করুন, অনেক বেশি দিলে কেউ বারবার OTP রিকোয়েস্ট দিয়ে Abuse করতে পারবে, অনেক কম দিলে যারা বারবার লগিন/লগআউট করে তাদের সমস্যা হতে পারে । Normally একজন User একদিনে ১০ বারের বেশি লগিন/লগআউট করে না, এজন্য Default ভাবে ১০ দেওয়া আছে ।',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'section',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'section',
		_greenweb_xd("\xc8\xd6") 			=> 'reg-section',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Configure Woocommerce Customers Registration Using Mobile (OTP)',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'r-enable-phone',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Enable Phone Verification',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'yes',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> ''
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'select',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'r-show-country-code-as',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Display Country Code Field as',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'selectbox',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'A valid phone number needs a country code. If disabled, the default one selected below is set as country code.',
		_greenweb_xd("\xc4\xca\xb7\xa6\x84")			=> array(
			_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4") => array(
				_greenweb_xd("\xd2\xd7\xaf\xb1\x86\x82\xc5\xd7\xb1") => 'Select Box',
			)
		)
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'select',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'r-default-country-code-type',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Country',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'Bangladesh',
		_greenweb_xd("\xc4\xca\xb7\xa6\x84")			=> array(
			_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4") => array(
				_greenweb_xd("\xc2\xc7\xb0\xa0\x8a\x9b")   		=> 'Bangladesh',
			)
		)
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'select',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6")			=> 'r-default-country-code',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Country Code',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'BD',
		_greenweb_xd("\xc4\xca\xb7\xa6\x84")			=> array(
			_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4") => $phone_codes
		)
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'select',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'r-phone-field',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Phone Field',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'required',
		_greenweb_xd("\xc4\xca\xb7\xa6\x84")			=> array(
			_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4") => array(
								_greenweb_xd("\xd3\xd7\xb2\xa1\x8c\x84\xc2\xdc") 			=> 'Required',
			)
		)
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'textarea',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'r-sms-txt',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'SMS Text',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'Shortcodes: [otp], Note: SMS Body শুরুতে অবশ্যই ব্রাকেট দিয়ে কোম্পানীর নাম/সাইট এড্রেস/ব্রান্ড নেম দিবেন, অনথ্যায় ওটিপি এসএমএস যাবে না। যেমনে, (Company Name) অথবা (কোম্পানীসাইট.কম)',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> __("(Company Name)
আপনার ওটিপি: [otp]
@domain.com, #[otp]",_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\x8a\xd4\xa6\xb7\x88\x9c\x8e\xc3\xaa\xb9\xc2\xdd\xae\xb9\x80\x84\xc4\xdd"))
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'r-disable-emailf',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Hide Email Field From Registration Page',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'no',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'যারা রেজিস্ট্রেশন পেজ হতে ইমেইল বাতিল করে শুধু মোবাইল OTP দিয়ে রেজিস্ট্রেশন করার পদ্ধতি ব্যবহার করতে চান তারা এই অপশন Enable করবেন । ইউজার ইমেইল ইনপুট করা ছাড়াই রেজিস্ট্রেশন করতে পারবে । '
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'reg-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'r-auto-submit',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Auto submit form',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'Auto submit registration form on otp verification.',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'yes'
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'section',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'section',
		_greenweb_xd("\xc8\xd6") 			=> 'login-section',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Configure Woocommerce Customers Login System Via Mobile (OTP)',
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'login-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'l-enable-login-with-otp',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Enable Login with OTP',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'yes',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> ''
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'login-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'l-login-display',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Display OTP login form first',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'yes',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> ''
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'checkbox',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'login-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'l-disble-emailf',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Remove Login with EMail Button',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> 'no',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'এই অপশনটি Enable করলে আপনার লগিন পেজ হতে Login With Email বাটনটি হাইড হবে । ফলে শুধুমাত্র মোবাইল নাম্বার দিয়ে লগিন করা যাবে । তবে যে সকল ব্যবহারকারীর অ্যাকাউন্ট পূর্বে ইমেইল ব্যবহার করে করা হয়েছে  এবং মোবাইল নাম্বার  ADD করা নেই তাদের নতুন করে রেজিস্ট্রেশন/প্রোফাইল Edit করে মোবাইল নাম্বার যুক্ত করে নিতে হবে ।'
	),

	array(
		_greenweb_xd("\xd5\xcb\xb3\xb1") 			=> 'setting',
		_greenweb_xd("\xc2\xd3\xaf\xb8\x87\x97\xc4\xd3") 		=> 'text',
		_greenweb_xd("\xd2\xd7\xa0\xa0\x8c\x99\xc9") 		=> 'login-section',
		_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84") 	=> $option_name,
		_greenweb_xd("\xc8\xd6") 			=> 'l-redirect',
		_greenweb_xd("\xd5\xdb\xb7\xb8\x80") 		=> 'Login with OTP Redirect',
		_greenweb_xd("\xc5\xd7\xb0\xb7") 			=> 'Leave empty to redirect on the same page',
		_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3") 		=> '',
	),

);

return $settings;

?>