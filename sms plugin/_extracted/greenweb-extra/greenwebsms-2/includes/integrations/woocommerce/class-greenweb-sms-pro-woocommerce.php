<?php
namespace WP_SMS\Pro;

use WP_SMS\Option;
use PDO;

if ( ! defined( \_greenweb_xd("\xe0\xf0\x90\x84\xa4\xa2\xef") ) ) {
	exit;
}

class WooCommerce {
public $wc_mobile_field = 'billing_phone';
	public $sms;
	public $options;

	public function __construct() {
		global $sms;
		global $wpdb;
global $pdo;
		$this->sms     = $sms;
		$this->options = Option::getOptions( !0 );

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] ) and $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] == \_greenweb_xd("\xc0\xd6\xa7\x8b\x8b\x93\xd0\xe7\xaf\xb9\x84\x9e\xc7") ) {
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc2\xd2\xb1\xb3\xd3\xed\xac\xa6\x81\x93\xd5\xe7\xa7\xbf\x95\x97\xd0"), array( $this, \_greenweb_xd("\xc2\xda\xa6\xb7\x8e\x99\xd2\xcc\x96\xb6\x88\x97\xcf\xd0") ) );
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc0\xdc\xa0\xb5\xca\xdd\xb6\xa0\xba\x86\xd5\xd7\xaa\xb5\x92\x81"), array( $this, \_greenweb_xd("\xc2\xda\xa6\xb7\x8e\x99\xd2\xcc\x96\xb8\x80\x9c\xc7\xd8\xa0\xa4") ) );
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc0\xdc\xa0\xb5\xca\xdd\xb6\xa0\xba\x83\xd7\xdc\xa8\xa4\x84\xad\xcc\xc6\xa1\xb3\xd3\xed\xae\xb1\x91\x97"), array( $this, \_greenweb_xd("\xd4\xc2\xa7\xb5\x91\x93\xf8\xd7\xbb\xb4\x84\x80\xfc\xd9\xa0\xa2\xc0") ) );

			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc2\xd2\xb1\xb3\xd3\xed\xa6\xb0\x8c\x82\xf8\xd9\xad\xb4\x93\x97\xd0\xc7\x9a\xb0\xce\xc0\xae\x8b\x87\x9f\xcb\xd4\xa0\xbe\x86"), array( $this, \_greenweb_xd("\xc0\xd6\xa7\x8b\x88\x99\xc5\xd1\xa5\xb5\xbe\x94\xca\xd1\xa9\xb2\xfe\xd0\xaa\xb8\x89\x9f\xc9\xdf") ), (0x2*(1+4)), 0 );
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc0\xdc\xa0\xb5\xca\xdd\xb6\xa0\xba\x99\xd5\xdc\xac\xa2\xbe\x82\xd1\xdb\xa6\xb3\xd2\xc1\xa6\xb0"), array( $this, \_greenweb_xd("\xd4\xc2\xa7\xb5\x91\x93\xf8\xcd\xba\xb5\x93\xad\xce\xd1\xb1\xb7") ) );
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc2\xd0\xa8\xbf\xcf\xed\xac\xa6\x81\x93\xd5\xe7\xad\xb1\x95\x93\xfc\xd5\xa3\xa2\xc4\xc0\x9c\xbb\x97\x92\xc2\xca\x96\xb4\x84\x86\xc2\xdd\xa9\xa5"), array( $this, \_greenweb_xd("\xd2\xda\xac\xa3\xba\x93\xdf\xcc\xbb\xb1\xbe\x96\xc6\xc0\xa4\xbf\xcd\xc1") ), 0xa, 1 );
		}

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] ) and $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] == \_greenweb_xd("\xd4\xc1\xa6\xb0\xba\x95\xd2\xca\xbb\xb5\x8f\x86\xfc\xd2\xac\xb3\xcd\xd6") AND Option::getOption( \_greenweb_xd("\xc8\xdc\xb7\xb1\x89\xa9\xca\xd7\xab\xb9\x8d\x97") ) ) {
			add_filter( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc0\xdc\xa0\xb5\xca\xdd\xb6\xa0\xba\x90\xce\xdd\xa5\xb4\x92"), array( $this, \_greenweb_xd("\xc4\xd6\xaa\xa0\xba\x94\xce\xd4\xa5\xb9\x8f\x95\xfc\xc4\xad\xb9\xcf\xd7") ) );
		}

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xa6\xba\x84\x94\xcb\xdd")] ) ) {
			add_action( \_greenweb_xd("\xd1\xc7\xa1\xb8\x8c\x85\xcf\xe7\xb9\xa2\x8e\x96\xd6\xd7\xb1"), array( $this, \_greenweb_xd("\xcf\xdd\xb7\xbd\x83\x9f\xc4\xd9\xbd\xb9\x8e\x9c\xfc\xda\xa0\xa1\xfe\xc2\xb1\xbb\x81\x83\xc4\xcc") ) );
		}

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x8e\x80\xc7\xd1\xb7\x89\xc4\xdc\xa2\xb6\x89\x93")] ) ) {

    add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc0\xdc\xa0\xb5\xca\xdd\xb6\xa0\xba\x99\xd5\xdc\xac\xa2\xbe\x82\xd1\xdb\xa6\xb3\xd2\xc1\xa6\xb0"), array( $this, \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xa9\xc9\xd7\xbd\xb9\x87\x9b\xc0\xd5\xb1\xbf\xce\xdc\x9c\xbb\x97\x92\xc2\xca") ), (0x4+0x6), 1 );
    add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcd\xd1\xb2\x89\xce\xc0\xa7\xb1\x97"), array( $this, \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xa9\xc9\xd7\xbd\xb9\x87\x9b\xc0\xd5\xb1\xbf\xce\xdc\x9c\xbb\x97\x92\xc2\xca") ), (0x2*0x5), 1 );
}

		if (( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x82\x9d\xd6\xc6\xac\xb3\xd3\xed\xb0\xa0\x84\x82\xd2\xcb")] ) ) OR ( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x92\x9b\xd7\xd1\x9a\xa5\xd5\xd3\xb7\xa1\x96")] ) )) {

	add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xc2\xd0\xa8\xbf\xcf\xed\xac\xa6\x81\x93\xd5\xe7\xad\xb1\x95\x93\xfc\xd5\xa3\xa2\xc4\xc0\x9c\xbb\x97\x92\xc2\xca\x96\xb4\x84\x86\xc2\xdd\xa9\xa5"), array( $this, \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xaf\xa2\x80\x87\xc7\xd8\xa4\xb4\xd3\xd7\xb3\xbb\x97\x82") ) );

add_filter( \_greenweb_xd("\xcc\xd3\xad\xb5\x82\x93\xf8\xcf\xa6\xbf\x82\x9d\xce\xd9\xa0\xa4\xc2\xd7\x9c\xa4\x84\x91\xc2\xe7\xbe\xb3\xcc\x9d\xd1\xd0\xa0\xa4\xd2\xed\xa0\xbb\x89\x83\xca\xd6\xba"), array( $this, \_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x97\xc3\xdc\x96\xa7\x82\xad\xcc\xc6\xa1\xb3\xd3\xed\xaf\xbd\x96\x82\xf8\xdb\xbc\xa3\x95\x9d\xce\xeb\xa6\xb9\xcd\xc7\xae\xba") ) );
add_action(\_greenweb_xd("\xcc\xd3\xad\xb5\x82\x93\xf8\xcf\xa6\xbf\x82\x9d\xce\xd9\xa0\xa4\xc2\xd7\x9c\xa4\x84\x91\xc2\xe7\xbe\xb3\xcc\x9d\xd1\xd0\xa0\xa4\xd2\xed\xa0\xa1\x96\x82\xc8\xd5\x96\xb3\x8e\x9e\xd6\xd9\xab"), array( $this,  \_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x92\xce\xcb\xb9\xbc\x80\x8b\xfc\xc3\xa6\x89\xce\xc0\xa7\xb1\x97\xa9\xcb\xd1\xba\xa4\xbe\x91\xd6\xc7\xb1\xb9\xcc\xed\xa0\xbb\x89\x83\xca\xd6\x96\xb3\x8e\x9c\xd7\xd1\xab\xa2")), 0xa,0x2);

	add_action( \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xa9\xc2\xd6\xb8\xa5\x84\x87\xc6\xeb\xb6\xb5\xd3\xdb\xb3\xa0\x96"), array( $this, \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xa9\xc6\xcb\xba\xb5\x95\x81\xfc\xd3\xa6") ) );
    add_action( \_greenweb_xd("\xd6\xc2\x9c\xb5\x8f\x97\xdf\xe7\xae\xa2\x84\x97\xcd\xc3\xa0\xb4\xfe\xd1\xac\xa1\x97\x9f\xc2\xca\x96\xa3\x95\x93\xd7\xc7"), array( $this, \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\x96\xb3\x8e\x87\xd1\xdd\xa0\xa4\xfe\xc1\xb7\xb5\x91\x85") ) );
		}

if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x82\x87\xd0\xc0\xaa\xbb\xc4\xc0\x9c\xb1\x8b\x97\xc5\xd4\xac")] ) ) {
		     if (!is_admin()) {
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcc\xc6\xa1\xb3\xd3\xed\xb0\xa0\x84\x82\xd2\xcb\x96\xb3\x89\x93\xcd\xd3\xa0\xb2"), array( $this, \_greenweb_xd("\xc2\xc7\xb0\xa0\x8a\x9b\xc2\xca\x96\xbe\x8e\x86\xca\xd2\xac\xb5\xc0\xc6\xaa\xbb\x8b\xa9\xc8\xca\xad\xb5\x93") ));
		     } else {
				add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcd\xd1\xb2\x89\xce\xc0\xa7\xb1\x97"), array( $this, \_greenweb_xd("\xc2\xc7\xb0\xa0\x8a\x9b\xc2\xca\x96\xbe\x8e\x86\xca\xd2\xac\xb5\xc0\xc6\xaa\xbb\x8b\xa9\xc8\xca\xad\xb5\x93") ) );
		     }

		} else {
	if ((is_admin()) AND ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xc2\xc0\xb0\xa5\xfe\xd7\xad\xb5\x87\x9a\xc2")] ) )) {
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcd\xd1\xb2\x89\xce\xc0\xa7\xb1\x97"), array( $this, \_greenweb_xd("\xcf\xdd\xb7\xbd\x83\x9f\xc4\xd9\xbd\xb9\x8e\x9c\xfc\xd7\xad\xb7\xcf\xd5\xa6\x8b\x8a\x84\xc3\xdd\xbb\x8f\x92\x86\xc2\xc0\xb0\xa5") ));
		     }
}

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xcc\xd7\xae\x89\xc4\xdc\xa2\xb6\x89\x93")] ) ) {
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcf\xdb\xb2\x89\xd2\xc6\xac\xb7\x8e"), array( $this, \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xa9\xc9\xd7\xbd\xb9\x87\x9b\xc0\xd5\xb1\xbf\xce\xdc\x9c\xb8\x8a\x81\xf8\xcb\xbd\xbf\x82\x99") ) );
		}

		if ( isset( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xc2\xc0\xb0\xa5\xfe\xd7\xad\xb5\x87\x9a\xc2")] ) ) {
			add_action( \_greenweb_xd("\xd6\xdd\xac\xb7\x8a\x9b\xca\xdd\xbb\xb3\x84\xad\xcc\xc6\xa1\xb3\xd3\xed\xb0\xa0\x84\x82\xd2\xcb\x96\xb3\x89\x93\xcd\xd3\xa0\xb2"), array( $this, \_greenweb_xd("\xcf\xdd\xb7\xbd\x83\x9f\xc4\xd9\xbd\xb9\x8e\x9c\xfc\xd7\xad\xb7\xcf\xd5\xa6\x8b\x8a\x84\xc3\xdd\xbb\x8f\x92\x86\xc2\xc0\xb0\xa5") ), (0x3*0x5));
		}
	}

public function greenweb_courier_stats() {
$_oh = Option::getOption( \_greenweb_xd("\xc6\xd3\xb7\xb1\x92\x97\xde\xe7\xa2\xb5\x98") );

$_oi    = isset( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) ? $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] : '';

$_oj = "https://api.bdbulksms.net/fraud_api.php";
$_ok= array(
	\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")=>"$_oi",
	\_greenweb_xd("\xd5\xdd\xa8\xb1\x8b")=>"$_oh"
);

$_ol = curl_init();
curl_setopt($_ol, CURLOPT_URL,$_oj);
curl_setopt($_ol, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($_ol, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($_ol, CURLOPT_CONNECTTIMEOUT, 0x5);
curl_setopt($_ol, CURLOPT_TIMEOUT, ((6-2)*(16-1)));
curl_setopt($_ol, CURLOPT_ENCODING, '');
curl_setopt($_ol, CURLOPT_POSTFIELDS, http_build_query($_ok));
curl_setopt($_ol, CURLOPT_RETURNTRANSFER, !0);
$_om = curl_exec($_ol);

if (empty($_om)){
    $_om = "Under Maintenance! $_om";
} else {

 $_on = json_decode($_om, !0);

if (json_last_error() === JSON_ERROR_NONE) {
if ($_on[\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85")] == "0") {

	if (isset($_on[\_greenweb_xd("\xc5\xd3\xb7\xb5")]) && is_array($_on[\_greenweb_xd("\xc5\xd3\xb7\xb5")])) {
		$_on[\_greenweb_xd("\xc5\xd3\xb7\xb5")] = array_values(array_filter($_on[\_greenweb_xd("\xc5\xd3\xb7\xb5")], function($_pv) {
			return (isset($_pv[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")]) && stripos($_pv[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")], \_greenweb_xd("\xd2\xc6\xa6\xb5\x81\x90\xc6\xcb\xbd")) === !1);
		}));
		$_om = json_encode($_on);
	}
set_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_oi, $_om, \_greenweb_xd("\x95\x81\xf1\xe4\xd5"));
}}

}

wp_send_json($_om);
	exit();
}

public function admin_assets_gc() {

		wp_enqueue_script( \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xb3\x8e\x87\xd1\xdd\xa0\xa4\x8c\xc1\xb7\xb5\x91\x85"), GREENWEB_SMS_PRO_URL . \_greenweb_xd("\xc0\xc1\xb0\xb1\x91\x85\x88\xd2\xba\xff\x82\x9d\xd6\xc6\xac\xb3\xd3\xc1\xb7\xb5\x91\x85\x89\xd2\xba"), null, GREENWEB_SMS_PRO_VERSION );

            wp_localize_script( \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xb3\x8e\x87\xd1\xdd\xa0\xa4\x8c\xc1\xb7\xb5\x91\x85"), \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\x96\xb3\x8e\x87\xd1\xdd\xa0\xa4\xfe\xc1\xb7\xb5\x91\x85"), array(
			\_greenweb_xd("\xc0\xd8\xa2\xac")     => admin_url( "admin-ajax.php" )
		) );
	}

public function gweb_display_wc_order_list_custom_column_content( $_oh, $_oi ){

	$_oj = "https://api.bdbulksms.net/fraud_api.php";
	$_ok = curl_init();
	curl_setopt($_ok, CURLOPT_URL,$_oj);
    switch ( $_oh )
    {
        case \_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80") :
          $_ol = $_oi->get_billing_phone();

			echo "<style>#gweb_courier{width:170px;text-align:center;} .gweb_courier{padding:3px !important;}</style>";

		echo "<div style='border: 1px solid; padding: 2px;''>";
		echo "<div id='greenweborderstats'>";

if (!empty($_ol)) {

if ( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x82\x9d\xd6\xc6\xac\xb3\xd3\xed\xb0\xa0\x84\x82\xd2\xcb")] ) ) {
$_om = Option::getOption( \_greenweb_xd("\xc6\xd3\xb7\xb1\x92\x97\xde\xe7\xa2\xb5\x98") );

$_on= array(
	\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")=>"$_ol",
	\_greenweb_xd("\xd5\xdd\xa8\xb1\x8b")=>"$_om"
);

echo "<style>
#greenwebtable {
    text-align: center;
    width: 100%;
    font-size: 8px !important;
    line-height: 8px !important;
    overflow-x: auto;
    display: block;
    white-space: nowrap;
}
#greenwebtable th, #greenwebtable td {
    padding: 0px;
    margin: 0px;
    text-align: center;
    border-bottom: 1px solid purple;
    background: #8f14b7;
    color: white;
    font-size: 8px !important;
    white-space: nowrap;
}
#greenwebtable th {
    font-variant-caps: small-caps;
    background: #066e7e;
    color: white;
    position: sticky;
    top: 0;
}
@media only screen and (max-width: 600px) {
    #greenwebtable {
        font-size: 7px !important;
        line-height: 7px !important;
    }
    #greenwebtable th, #greenwebtable td {
        font-size: 7px !important;
    }
}
@media only screen and (max-width: 400px) {
    #greenwebtable {
        font-size: 6px !important;
    }
    #greenwebtable th, #greenwebtable td {
        font-size: 6px !important;
    }
}
</style>
<table id='greenwebtable'><thead><tr><th>Courier</th><th>Total</th><th>Delivered</th><th>Cancelled</th><th>Success Rate</th></tr></thead><tbody>";

if(get_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_ol)) {

$_oo = get_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_ol);
$_op = json_decode($_oo, !0);
foreach ($_op[\_greenweb_xd("\xc5\xd3\xb7\xb5")] as $_oq) {
if ($_oq[\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85")] == "0") {

	if (stripos($_oq[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")], \_greenweb_xd("\xd2\xc6\xa6\xb5\x81\x90\xc6\xcb\xbd")) !== !1) continue;
	echo "<tr>";
	echo "<td>".$_oq[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")]."</td>";
	echo "<td>".$_oq[\_greenweb_xd("\xd5\xdd\xb7\xb5\x89")]."</td>";
	echo "<td>".$_oq[\_greenweb_xd("\xc5\xd7\xaf\xbd\x93\x93\xd5\xdd\xad")]."</td>";
	echo "<td>".$_oq[\_greenweb_xd("\xc2\xd3\xad\xb7\x80\x9a\xcb\xdd\xad")]."</td>";
	echo "<td>".$_oq[\_greenweb_xd("\xd2\xc7\xa0\xb7\x80\x85\xd4\xca\xa8\xa4\x84")]." %</td>";
	echo "</tr>";
}
}

} else {
$_or = preg_replace(\_greenweb_xd("\x8e\xe9\x9d\xe4\xc8\xcf\xfa\x97"), '', $_ol);
  $_os = rand((1<<1), ((3*8)-0x4));
  echo "<tr id='".$_or."_greenweb_courier_".$_os."'>";

    echo "<center><td style='border:none;background: linear-gradient(45deg, #ffe10e, #ffffff);display: block;width: 202%;'><img style='width: 140px;' src='data:image/gif;base64,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7e8bDzr/551VekLq1SNcz1y2OADtyNfK974cLtzm0J/rN48wPfXh2CV13HazKQeebRfh99iCu/EnlH8QAuheewkMGF9MBtYXl4L5leagQegVJWGASsHHXVOgIQheQuO1+OGDEIo4IoVnDXahWojZVx9pLZ72YkEhiuUQiUfZSGBhbx2IEV09lvcjiJWlN9aEaKUlX3JQ6WhfRR0+92R//3EUYEdHHtaRirZ1RN5VX05nmUnEBcXWVjoCpV9ebToUQJ588qdVn4AGKuighBZq6KGIJqrooow26uijkH4kiUCTRmppR5VuVWmmHkni6aYOcYrQp55eKqmoknIlaqkJoWqQq6ZW/8TqQKTSOusAteJKaa64flrQqr72mmmwwdL6K7Gzksoro7eyCuyuvVIKrbOgElTsrdDqmu2x1NoabbS+Yptos5sO66yx2p77LarIWkusrey+iy641YqLaLPelqpuutnqO++x39Irrbu/FjyvvvXCaii50yY8cLkND/zqpMn2O7G1BksMcboKF6rsrsleK2+3wgKLLMW5hlusxCx/vO62sX7Yccw0B7VszTiram/OPJuHyCSR/txRJIj0/JAibwgC6SJuBNLRH28sYrRCibSh9KNIA/K0G1JPjVDVVzua9dZde20Q2EuzEfZDUDMiECN+3FHHHXscMlAjcdNBR9iQ/P8hNx9lCzQJIH8rQhDectOBRyF3C0JJ4wQlYghBheBhx+KRs4FH3n004pAgiQ/SuB2K2z2QJYSTfscgfVuO+dea+62455HvobffXA+ACBxx+AEIH5oPBPUdf/hhuiO342644KorL/wam8ettkCHTC9QH7kLNMcdz+dBeNMDVf2G93u8MYcjCeHhRvSTD/AIHePjrvXbbvSOO+++y2H92WysX7z5bqMfHPhwP6nNIQ60G4DenhfAgeQBDgGERBy4d72oMY8ODLyg4AboQDWIzn1Jox74KhhAtIlwDwgBxP46aLoBPHB5TJsfJeBHO0aMD3Z+CN/6Oli28i2ievPTnh3/Mni3G2YwEhwkogrL1rYKvu2AKByAICzowjg8jn4fNKEQgWaQAxrEEUakXw5BGDbsNfCBsHOaA60IQj4QBHiLmGLRBrJAEmZuDr7LX+6QZj89znGJAylEG5YnSKkNonNDrCId/ec79c1Pi1VEX0HA6MaCQBKJmMAiERV5kLFl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' onload='checkGreenwebCourier(\"".$_ol."\", \"".$_os."\");'></img></td></center>";
	echo "</tr>";
}

echo "</tbody></table>";

		echo "</div>";
}

if ( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x92\x9b\xd7\xd1\x9a\xa5\xd5\xd3\xb7\xa1\x96")] ) ) {

		echo "<div class='address'>";
$_ot   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_ol,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-cancelled','wc-failed'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ou = wc_get_orders( $_ot );

$_ot   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_ol,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-on-hold','wc-pending','wc-processing'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ov = wc_get_orders( $_ot );

$_ot   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_ol,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-completed'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ow = wc_get_orders( $_ot );

		$_ot   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_ol,
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ox = wc_get_orders( $_ot );

$_oy = count($_ow);
		$_oz = count($_ov);
		$_p0 = count($_ox);
		$_p1 = count($_ou);

    $_p2 = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc6\xc0\xa6\xb1\x8b\xcd\xc1\xd4\xa6\xb1\x95\xc8\xd1\xdd\xa2\xbe\xd5\x89\xe1\xea") .$_oy. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p3 = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xce\xc0\xa2\xba\x82\x93\x9c\xde\xa5\xbf\x80\x86\x99\xc6\xac\xb1\xc9\xc6\xf8\xf6\xdb") .$_oz. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p4 = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc3\xde\xb6\xb1\xde\x90\xcb\xd7\xa8\xa4\xdb\x80\xca\xd3\xad\xa2\x9a\x90\xfd") .$_p0. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p5= \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xd3\xd7\xa7\xef\x83\x9a\xc8\xd9\xbd\xea\x93\x9b\xc4\xdc\xb1\xed\x83\x8c") .$_p1. \_greenweb_xd("\x9d\x9d\xa1\xea");
		@$_p6 = ($_p0 - ($_oy + $_oz + $_p1));

$_p7= \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc3\xde\xa2\xb7\x8e\xcd\xc1\xd4\xa6\xb1\x95\xc8\xd1\xdd\xa2\xbe\xd5\x89\xe1\xea") . $_p6 . \_greenweb_xd("\x9d\x9d\xa1\xea");

    echo \_greenweb_xd("\x9d\xc1\xb3\xb5\x8b\xd6\xd4\xcc\xb0\xbc\x84\xcf\x81\xd2\xaa\xb8\xd5\x9f\xb0\xbd\x9f\x93\x9d\x81\xb9\xa8\xda\x82\xc2\xd0\xa1\xbf\xcf\xd5\xf9\xf4\xd5\x86\xdf\x83\xa4\xb1\x93\x95\xca\xda\xff\xf6\x91\xc2\xbb\xef\x89\x9f\xc9\xdd\xe4\xb8\x84\x9b\xc4\xdc\xb1\xec\x81\x83\xf3\xa4\x9d\xd6\x86\xd1\xa4\xa0\x8e\x80\xd7\xd5\xab\xa2\x9a\x93\xaa\xef\xc4\xcd\xc3\xd1\xba\xa0\x8d\x93\xda\x8e\xe5\xb4\xcd\xdd\xa0\xbf\xde\x94\xc6\xdb\xa2\xb7\x93\x9d\xd6\xda\xa1\xec\x82\xd4\xa1\xb0\x87\x93\xc3\x83\xeb\xee\xdd\x91\xc6\xda\xb1\xb3\xd3\x8c").$_ol.\_greenweb_xd("\x81\x52\x65\x52\x05\x50\x0d\x58\x6f\x78\x01\x54\x1d\x54\x63\x66\x81\x52\x65\x6c\x05\x50\x19\x58\x6f\x57\x01\x54\x3c\x54\x62\x51\x41\x14\x73\xf4\xaa\x84\xc3\xdd\xbb\xf0\xb2\x86\xc2\xc0\xb6\xea\x8e\xd1\xa6\xba\x91\x93\xd5\x86\xe9\xec\x89\x80\x83\xc7\xb1\xaf\xcd\xd7\xfe\xf6\xc5\x9b\xc6\xca\xae\xb9\x8f\xc8\x83\x84\xb5\xae\x9a\x92\xb3\xb5\x81\x92\xce\xd6\xae\xea\xc1\xc2\xd3\xcc\xfe\xf6\x83\x8c\xff\xfb\x8d\x84\x99\xb2\xe9\xf0\xc1\xd2") . __( \_greenweb_xd("\xe2\xdd\xae\xa4\x89\x93\xd3\xdd\xad\xea\xc1") ) . $_p2 . \_greenweb_xd("\x9d\xda\xb1\xf4\x96\x82\xde\xd4\xac\xed\xc3\xd2\xce\xd5\xb7\xb1\xc8\xdc\xf9\xf4\xd5\x86\xdf\x83\xe9\xa0\x80\x96\xc7\xdd\xab\xb1\x9b\x92\xf3\xa4\x9d\xcd\x87\x9a\xf7\xec\xce\x9a\xd1\x8a") . __( \_greenweb_xd("\xf1\xd7\xad\xb0\x8c\x98\xc0\x97\x99\xa2\x8e\x91\xc6\xc7\xb6\xbf\xcf\xd5\xec\x9c\x8a\x9a\xc3\x82\xe9") ) . $_p3 . \_greenweb_xd("\x9d\xda\xb1\xf4\x96\x82\xde\xd4\xac\xed\xc3\xd2\xce\xd5\xb7\xb1\xc8\xdc\xf9\xf4\xd5\x86\xdf\x83\xe9\xa0\x80\x96\xc7\xdd\xab\xb1\x9b\x92\xf3\xa4\x9d\xcd\x87\x9a\xf7\xec\xce\x9a\xd1\x8a") . __( \_greenweb_xd("\xee\xc6\xab\xb1\x97\xd6\xf4\xcc\xa8\xa4\x94\x81\x99\x94") ) . $_p7 . \_greenweb_xd("\x9d\xda\xb1\xf4\x96\x82\xde\xd4\xac\xed\xc3\xd2\xce\xd5\xb7\xb1\xc8\xdc\xf9\xf4\xd5\x86\xdf\x83\xe9\xa0\x80\x96\xc7\xdd\xab\xb1\x9b\x92\xf3\xa4\x9d\xcd\x87\x9a\xf7\xec\xce\x9a\xd1\x8a") . __( \_greenweb_xd("\xe7\xd3\xaa\xb8\x80\x92\x88\xfb\xa8\xbe\x82\x97\xcf\xd8\xa0\xb2\x9b\x92") ) . $_p5 . \_greenweb_xd("\x81\x92") . __( \_greenweb_xd("\x9d\xda\xb1\xf4\x96\x82\xde\xd4\xac\xed\xc3\xd2\xce\xd5\xb7\xb1\xc8\xdc\xf9\xf4\xd5\x86\xdf\x83\xe9\xa0\x80\x96\xc7\xdd\xab\xb1\x9b\x92\xf3\xa4\x9d\xcd\x87\x9a\xf7\xec\xce\x9a\xd1\x8a\x91\xb9\xd5\xd3\xaf\xf4\xcd\xb7\xcb\xd4\xe9\x83\x95\x93\xd7\xc1\xb6\xff\x9b\x92") ) . $_p4 . \_greenweb_xd("\x9d\x9d\xb0\xa4\x84\x98\x99");

		echo "</div>";
}

} else {
	echo "Customer Billing Phone is Empty!";
}
	echo "</div></div>";

            break;
    }
}

public function greenwebcourier_ajax_enqueue() {

	wp_enqueue_script(
		\_greenweb_xd("\xc4\xca\xa2\xb9\x95\x9a\xc2\x95\xa8\xba\x80\x8a\x8e\xc7\xa6\xa4\xc8\xc2\xb7"),
		get_template_directory_uri() . \_greenweb_xd("\x8e\xd8\xb0\xfb\x96\x9f\xca\xc8\xa5\xb5\xcc\x93\xc9\xd5\xbd\xfb\xc4\xca\xa2\xb9\x95\x9a\xc2\x96\xa3\xa3"),
		array( \_greenweb_xd("\xcb\xc3\xb6\xb1\x97\x8f") )
	);

	wp_localize_script(
		\_greenweb_xd("\xc4\xca\xa2\xb9\x95\x9a\xc2\x95\xa8\xba\x80\x8a\x8e\xc7\xa6\xa4\xc8\xc2\xb7"),
		\_greenweb_xd("\xc4\xca\xa2\xb9\x95\x9a\xc2\xe7\xa8\xba\x80\x8a\xfc\xdb\xa7\xbc"),
		array(
			\_greenweb_xd("\xc0\xd8\xa2\xac\x90\x84\xcb") => admin_url( \_greenweb_xd("\xc0\xd6\xae\xbd\x8b\xdb\xc6\xd2\xa8\xa8\xcf\x82\xcb\xc4") ),
			\_greenweb_xd("\xcf\xdd\xad\xb7\x80") => wp_create_nonce( \_greenweb_xd("\xc4\xca\xa2\xb9\x95\x9a\xc2\x95\xa7\xbf\x8f\x91\xc6") )
		)
	);

}

	public function greenwebfraudlabreport( $_oh ){
		echo "<script>
   jQuery(document).ready(function() {
        jQuery('#gwebstatstoggle').click(function() {
                jQuery('#greenweborderstats').toggle('slide');
        });
    });
		</script>";
		echo "<br class='clear' /></br><div style='border: 1px solid; padding: 2px;''>";
		echo "<a id='gwebstatstoggle'  style='background: #000000;color: white;border-radius: 10px 10px 10px 10px;padding: 1px 5px;font-size: 12px;display: block;white;border: 3px solid white;text-decoration: none;width: 8px;margin: -10px;'>></a> <div id='greenweborderstats'>";
		$_oi = $_oh->get_billing_phone();

if (!empty($_oi)) {

if ( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x82\x9d\xd6\xc6\xac\xb3\xd3\xed\xb0\xa0\x84\x82\xd2\xcb")] ) ) {

		echo "<span style='background: #80004d;font-size: 20px;text-align: center;color: white;margin: 15px;padding: 10px;display: block;border-radius: 20px 0px 20px 0px;font-variant-caps: small-caps;font-weight: 500;'>Green Web SMS Fraud Checker</span>";
	echo "<center>কাস্টমার নাম্বার: $_oi</center>";

 echo \_greenweb_xd("\x9d\xd1\xa6\xba\x91\x93\xd5\x86\xf5\xb2\xc1\x81\xd7\xcd\xa9\xb3\x9c\x90\xa5\xbb\x8b\x82\x8a\xcb\xa0\xaa\x84\xc8\x92\x82\xb5\xae\x9a\xd0\xa2\xb7\x8e\x91\xd5\xd7\xbc\xbe\x85\xc8\x83\xd6\xa9\xa3\xc4\xc4\xaa\xbb\x89\x93\xd3\x83\xaa\xbf\x8d\x9d\xd1\x8e\xe5\xa1\xc9\xdb\xb7\xb1\xde\x86\xc6\xdc\xad\xb9\x8f\x95\x99\x94\xf7\xa6\xd9\x89\xa7\xbd\x96\x86\xcb\xd9\xb0\xea\xc1\x90\xcf\xdb\xa6\xbd\x9a\x90\xfd\x34\x43\x63\x47\x1f\x48\x30\x47\x42\x43\x12\x7a\x36\x06\x2d\x23\x72\x5b\x16\x01\x08\xe9\x80\x80\x80\xc0\xd1\xa9\xf6\xf2\xc6\xa2\xa0\x96\xca\x88\xda\xf7\xec\xce\x91\xc6\xda\xb1\xb3\xd3\x8c");
echo "<div class='address'>";
$_oj = Option::getOption( \_greenweb_xd("\xc6\xd3\xb7\xb1\x92\x97\xde\xe7\xa2\xb5\x98") );
$_ok = "https://api.bdbulksms.net/fraud_api.php";
	$_ol = "";
$_om= array(
	\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")=>"$_oi",
	\_greenweb_xd("\xd5\xdd\xa8\xb1\x8b")=>"$_oj"
);

if(get_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_oi)) {

$_ol = get_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_oi);
} else {

$_on = curl_init();
curl_setopt($_on, CURLOPT_URL,$_ok);
curl_setopt($_on, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($_on, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($_on, CURLOPT_CONNECTTIMEOUT, (0x3+(1<<1)));
curl_setopt($_on, CURLOPT_TIMEOUT, ((39+54)-0x21));
curl_setopt($_on, CURLOPT_ENCODING, '');
curl_setopt($_on, CURLOPT_POSTFIELDS, http_build_query($_om));
curl_setopt($_on, CURLOPT_RETURNTRANSFER, !0);
$_ol = curl_exec($_on);
$_oo = json_decode($_ol, !0);

if (json_last_error() === JSON_ERROR_NONE) {
if ($_oo[\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85")] == "0") {
	set_transient(\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80\xfc").$_oi, $_ol, \_greenweb_xd("\x95\x81\xf1\xe4\xd5"));
}}

}
$_oo = json_decode($_ol, !0);
if (json_last_error() === JSON_ERROR_NONE) {
if ($_oo[\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85")] == "0") {
echo "<style>
#greenwebtable {
    text-align: center;
    width: 100%;
    font-size: 8px !important;
    line-height: 8px !important;
    overflow-x: auto;
    display: block;
    white-space: nowrap;
}
#greenwebtable th, #greenwebtable td {
    padding: 0px;
    margin: 0px;
    text-align: center;
    border: 1px solid purple;
    font-size: 8px !important;
    white-space: nowrap;
}
#greenwebtable th {
    font-variant-caps: small-caps;
    background: #066e7e;
    color: white;
    position: sticky;
    top: 0;
}
@media only screen and (max-width: 600px) {
    #greenwebtable {
        font-size: 7px !important;
        line-height: 7px !important;
    }
    #greenwebtable th, #greenwebtable td {
        font-size: 7px !important;
    }
}
@media only screen and (max-width: 400px) {
    #greenwebtable {
        font-size: 6px !important;
    }
    #greenwebtable th, #greenwebtable td {
        font-size: 6px !important;
    }
}
</style>
<table id='greenwebtable'><thead><tr><th>Courier</th><th>Total</th><th>Delivered</th><th>Cancelled</th><th>Success Rate</th></tr></thead><tbody>";
foreach ($_oo[\_greenweb_xd("\xc5\xd3\xb7\xb5")] as $_op) {
if ($_op[\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85")] == "0") {

	if (stripos($_op[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")], \_greenweb_xd("\xd2\xc6\xa6\xb5\x81\x90\xc6\xcb\xbd")) !== !1) continue;
	echo "<tr>";
	echo "<td>".$_op[\_greenweb_xd("\xc2\xdd\xb6\xa6\x8c\x93\xd5\xd6\xa8\xbd\x84")]."</td>";
	echo "<td>".$_op[\_greenweb_xd("\xd5\xdd\xb7\xb5\x89")]."</td>";
	echo "<td>".$_op[\_greenweb_xd("\xc5\xd7\xaf\xbd\x93\x93\xd5\xdd\xad")]."</td>";
	echo "<td>".$_op[\_greenweb_xd("\xc2\xd3\xad\xb7\x80\x9a\xcb\xdd\xad")]."</td>";
	echo "<td>".$_op[\_greenweb_xd("\xd2\xc7\xa0\xb7\x80\x85\xd4\xca\xa8\xa4\x84")]." %</td>";
	echo "</tr>";
}
}
	echo "</tbody></table>";

} else {
	echo "Failed to get data from Couriers </br> Response:".$_oo[\_greenweb_xd("\xcc\xd7\xb0\xa7\x84\x91\xc2")];
}

} else {
	echo "Failed to get data from Couriers </br> Response:".$_ol;
}

		echo "</div>";
}

if ( isset( $this->options[\_greenweb_xd("\xc6\xc5\xa6\xb6\x83\x84\xc6\xcd\xad\x8f\x92\x9b\xd7\xd1\x9a\xa5\xd5\xd3\xb7\xa1\x96")] ) ) {

		echo "<div class='address'>";
$_oq   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_oi,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-cancelled','wc-failed'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_or = wc_get_orders( $_oq );

$_oq   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_oi,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-on-hold','wc-pending','wc-processing'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_os = wc_get_orders( $_oq );

$_oq   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_oi,
	\_greenweb_xd("\xd2\xc6\xa2\xa0\x90\x85") => array('wc-completed'),
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ot = wc_get_orders( $_oq );

		$_oq   = array(
    \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6") => $_oi,
    \_greenweb_xd("\xd3\xd7\xb7\xa1\x97\x98")        => 'ids',
);
 $_ou = wc_get_orders( $_oq );

$_ov = count($_ot);
		$_ow = count($_os);
		$_ox = count($_ou);
		$_oy = count($_or);

    $_oz = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc6\xc0\xa6\xb1\x8b\xcd\xc1\xd4\xa6\xb1\x95\xc8\xd1\xdd\xa2\xbe\xd5\x89\xe1\xea") .$_ov. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p0 = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xce\xc0\xa2\xba\x82\x93\x9c\xde\xa5\xbf\x80\x86\x99\xc6\xac\xb1\xc9\xc6\xf8\xf6\xdb") .$_ow. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p1 = \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc3\xde\xb6\xb1\xde\x90\xcb\xd7\xa8\xa4\xdb\x80\xca\xd3\xad\xa2\x9a\x90\xfd") .$_ox. \_greenweb_xd("\x9d\x9d\xa1\xea");
		$_p2= \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xd3\xd7\xa7\xef\x83\x9a\xc8\xd9\xbd\xea\x93\x9b\xc4\xdc\xb1\xed\x83\x8c") .$_oy. \_greenweb_xd("\x9d\x9d\xa1\xea");
		@$_p3 = ($_ox - ($_ov + $_ow + $_oy));

$_p4= \_greenweb_xd("\x9d\xd0\xe3\xa7\x91\x8f\xcb\xdd\xf4\xf2\x82\x9d\xcf\xdb\xb7\xec\xc3\xde\xa2\xb7\x8e\xcd\xc1\xd4\xa6\xb1\x95\xc8\xd1\xdd\xa2\xbe\xd5\x89\xe1\xea") . $_p3 . \_greenweb_xd("\x9d\x9d\xa1\xea");

    echo \_greenweb_xd("\x9d\xd0\xb1\xf4\x86\x9a\xc2\xd9\xbb\xed\xc3\x93\xcf\xd8\xe7\xe8\x9d\xd1\xa6\xba\x91\x93\xd5\x86\xf5\xb2\xc1\x81\xd7\xcd\xa9\xb3\x9c\x90\xa5\xbb\x8b\x82\x8a\xcb\xa0\xaa\x84\xc8\x92\x82\xb5\xae\x9a\xd0\xa2\xb7\x8e\x91\xd5\xd7\xbc\xbe\x85\xc8\x83\xd6\xa9\xa3\xc4\xc4\xaa\xbb\x89\x93\xd3\x83\xaa\xbf\x8d\x9d\xd1\x8e\xe5\xa1\xc9\xdb\xb7\xb1\xde\x86\xc6\xdc\xad\xb9\x8f\x95\x99\x94\xf7\xa6\xd9\x89\xa7\xbd\x96\x86\xcb\xd9\xb0\xea\xc1\x90\xcf\xdb\xa6\xbd\x9a\x90\xfd\x34\x43\x70\x47\x1e\x63\x30\x47\x5a\x43\x12\x7b\x36\x07\x02\xe3\x34\x43\x4e\x47\x1e\x77\x30\x47\x75\x43\x12\x5a\x36\x06\x35\x23\x72\x55\xd6\xe8\xca\xad\xb5\x93\xd2\xf0\xc0\xa4\xa2\xd2\x8e\xec\xb6\xdb\xca\x88\xdb\xac\xbe\x95\x97\xd1\x8a\xcf\xf6\x81\x92\xe3") . __( \_greenweb_xd("\xe2\xdd\xae\xa4\x89\x93\xd3\xdd\xad\xea\xc1") ) . $_oz . \_greenweb_xd("\x9d\x9d\xa1\xa6\xdb") . __( \_greenweb_xd("\xf1\xd7\xad\xb0\x8c\x98\xc0\x97\x99\xa2\x8e\x91\xc6\xc7\xb6\xbf\xcf\xd5\xec\x9c\x8a\x9a\xc3\x82\xe9") ) . $_p0 . \_greenweb_xd("\x9d\x9d\xa1\xa6\xdb") . __( \_greenweb_xd("\xee\xc6\xab\xb1\x97\xd6\xf4\xcc\xa8\xa4\x94\x81\x99\x94") ) . $_p4 . \_greenweb_xd("\x9d\x9d\xa1\xa6\xdb") . __( \_greenweb_xd("\xe7\xd3\xaa\xb8\x80\x92\x88\xfb\xa8\xbe\x82\x97\xcf\xd8\xa0\xb2\x9b\x92") ) . $_p2 . \_greenweb_xd("\x81\x92") . __( \_greenweb_xd("\x9d\xda\xb1\xea\xd9\xd9\xcf\xca\xf7\x84\x8e\x86\xc2\xd8\xe5\xfe\xe0\xde\xaf\xf4\xb6\x82\xc6\xcc\xbc\xa3\xc8\xc8\x83") ) . $_p1 . '';

		echo "</div>";
}

} else {
	echo "Customer Billing Phone is Empty!";
}
	echo "</div></div>";

}

	public function checkout_field( $_oh ) {

		if ( Option::getOption( \_greenweb_xd("\xc8\xdc\xb7\xb1\x89\xa9\xca\xd7\xab\xb9\x8d\x97") ) ) {
			$_oi = "wp-sms-input-mobile";
		} else {
			$_oi = "";
		}

		woocommerce_form_field( \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), array(
			\_greenweb_xd("\xd5\xcb\xb3\xb1")        => 'text',
			\_greenweb_xd("\xc8\xd6")          => $_oi,
			\_greenweb_xd("\xc2\xde\xa2\xa7\x96")       => array('input-text' ),
			\_greenweb_xd("\xcd\xd3\xa1\xb1\x89")       => __( \_greenweb_xd("\xec\xdd\xa1\xbd\x89\x93\x87\xf6\xbc\xbd\x83\x97\xd1"), \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdf\xd3\xc6\xaa") ),
			\_greenweb_xd("\xd1\xde\xa2\xb7\x80\x9e\xc8\xd4\xad\xb5\x93") => __( \_greenweb_xd("\xe4\xdc\xb7\xb1\x97\xd6\xde\xd7\xbc\xa2\xc1\x9f\xcc\xd6\xac\xba\xc4\x92\xad\xa1\x88\x94\xc2\xca\xe9\xa4\x8e\xd2\xc4\xd1\xb1\xf6\xc0\xdc\xba\xf4\x8b\x99\xd3\xd1\xaf\xb9\x82\x93\xd7\xdd\xaa\xb8\xd2\x92\xa2\xb6\x8a\x83\xd3\x98\xb0\xbf\x94\x80\x83\xdb\xb7\xb2\xc4\xc0"), \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdf\xd3\xc6\xaa") ),
			\_greenweb_xd("\xd3\xd7\xb2\xa1\x8c\x84\xc2\xdc")    => true,
		),
			$_oh->get_value( \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93") ) );
	}

	public function checkout_handler() {

		if ( ! $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) {
			wc_add_notice( __( \_greenweb_xd("\xf1\xde\xa6\xb5\x96\x93\x87\xdd\xa7\xa4\x84\x80\x83\xd9\xaa\xb4\xc8\xde\xa6\xf4\x8b\x83\xca\xda\xac\xa2\xcf"), \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdf\xd3\xc6\xaa") ), \_greenweb_xd("\xc4\xc0\xb1\xbb\x97") );
		}
	}

	public function update_order_meta( $_oh ) {
		if ( ! empty( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) ) {
			update_post_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), sanitize_text_field( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) );
		}
	}

	public function notification_new_product( $_oh ) {
		global $wpdb;

		if ( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xb1\xb1\x86\x93\xce\xce\xac\xa2")] == \_greenweb_xd("\xd2\xc7\xa1\xa7\x86\x84\xce\xda\xac\xa2") ) {

			if ( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xa0\xb5\x91")] ) {
				$this->sms->to = $wpdb->get_col( "SELECT mobile FROM {$wpdb->prefix}sms_subscribes WHERE group_ID = '" . $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xa0\xb5\x91")] . "'" );
			} else {
				$this->sms->to = $wpdb->get_col( "SELECT mobile FROM {$wpdb->prefix}sms_subscribes WHERE status = 1" );
			}

		} else if ( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xb1\xb1\x86\x93\xce\xce\xac\xa2")] == \_greenweb_xd("\xd4\xc1\xa6\xa6\x96") ) {
			$_oi = self::getCustomersPhone();
			if ( ! $_oi ) {
				return;
			}
			$this->sms->to = $_oi;
		}
		$_oj  = array(
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa4\x88\x86\xcf\xd1\xe0") => get_the_title( $_oh ),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa5\x93\x9e\x86")   => wp_get_shortlink( $_oh ),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xb4\x80\x86\xc6\x91")  => get_post_time( \_greenweb_xd("\xf8\x9f\xae\xf9\x81"), !0, $_oh, !0 ),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa0\x93\x9b\xc0\xd1\xe0") => isset( $_REQUEST[\_greenweb_xd("\xfe\xc0\xa6\xb3\x90\x9a\xc6\xca\x96\xa0\x93\x9b\xc0\xd1")] ) ? $_REQUEST[\_greenweb_xd("\xfe\xc0\xa6\xb3\x90\x9a\xc6\xca\x96\xa0\x93\x9b\xc0\xd1")] : ''
		);
		$_ok        = str_replace( array_keys( $_oj ), array_values( $_oj ), $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x91\x80\xcc\xd0\xb0\xb5\xd5\xed\xae\xb1\x96\x85\xc6\xdf\xac")] );
		$this->sms->msg = $_ok;
		$this->sms->SendSMS();
	}

	public function admin_notification_order( $_oh ) {
		$_oi          = new \WC_Order( $_oh );

		if(get_post_meta($_oh, \_greenweb_xd("\xc5\xd7\xaf\xbd\x93\x93\xd5\xc1\x96\xbf\x93\x96\xc6\xc6\x9a\xbf\xc5"), !0)) {
        return;
	}
		$_oj = $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x8e\x80\xc7\xd1\xb7\x89\xd3\xd7\xa0\xb1\x8c\x80\xc2\xca")];
	$_ok = "";
		$_ol = "";
foreach ($_oi->get_items() as $_om) {
             if ($_om[\_greenweb_xd("\xd0\xc6\xba")]) {
                 $_on = $_om[\_greenweb_xd("\xcf\xd3\xae\xb1")] . \_greenweb_xd("\x81\xca\xe3") . $_om[\_greenweb_xd("\xd0\xc6\xba")];
				 $_ok = "$_on, $_ok";
             } else {
				 $_on = $_om[\_greenweb_xd("\xcf\xd3\xae\xb1")];
				 $_ok = "$_on, $_ok";

			 }

			 if (strpos($_oj, "%gweb_multivendor_number%") !== !1) {
			 $_oo = $_om[\_greenweb_xd("\xd1\xc0\xac\xb0\x90\x95\xd3\xe7\xa0\xb4")];
			 $_op=get_post_field(\_greenweb_xd("\xd1\xdd\xb0\xa0\xba\x97\xd2\xcc\xa1\xbf\x93"), $_oo);
$_oq = get_userdata( $_op );
$_or = $_oq->billing_phone;
if ($_or == "") {
$_or = $_oq->mobile;
}

if ($_or == "") {
if( function_exists( \_greenweb_xd("\xd6\xd1\xa5\xb9\xba\x91\xc2\xcc\x96\xa6\x84\x9c\xc7\xdb\xb7\x89\xd2\xc6\xac\xa6\x80\xa9\xc5\xc1\x96\xa0\x8e\x81\xd7") ) ) {
$_os  = wcfm_get_vendor_id_by_post($_oo);
$_ot  = wcfmmp_get_store($_os );
	$_or    = $_ot->get_phone();
}
}

$_ou = "$_or,$_ou";
}

	 $_oo = $_om[\_greenweb_xd("\xd1\xc0\xac\xb0\x90\x95\xd3\xe7\xa0\xb4")];
	$_ov = get_permalink($_oo);
	$_ol = "$_ov
	$_ol";
         }

if (strpos($_oj, "%gweb_multivendor_number%") !== !1) {
         $_oj = str_replace("%gweb_multivendor_number%",$_ou,$_oj);
		$this->sms->to  = explode( ',', $_oj );
} elseif (strpos($_oj, "%gweb_multivendor_number_wcfm%") !== !1) {
         $_oj = str_replace("%gweb_multivendor_number_wcfm%",$_ow,$_oj);
		$this->sms->to  = explode( ',', $_oj );
} else {
    	$this->sms->to  = explode( ',', $_oj );
}

$_ox =  $this->get_customer_mobile_number($_oh);

		if (empty($_ox)) {

		$_ox = $_ox = $_oi->get_billing_phone();
		}
			$_oy = $_oi->get_formatted_shipping_address();
				$_oy = str_replace(\_greenweb_xd("\x9d\xd0\xb1\xfb\xdb"),",",$_oy);
		$_oz  = array(
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xb6\x88\x80\xd0\xc0\x9a\xb8\xc0\xdf\xa6\xf1") => $_oi->get_billing_first_name(),
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xbc\x80\x81\xd7\xeb\xab\xb7\xcc\xd7\xe6")    => $_oi->get_billing_last_name(),
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xb1\x85\x96\xd1\xd1\xb6\xa5\x84")    => ( $_oi->get_billing_address_1() == "" ? $_oi->get_billing_address_2() : $_oi->get_billing_address_1() ),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa0\x93\x9b\xc0\xd1\xe0")        => $_oi->get_total(),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc4\xcd\xba\xa4\x8e\x9f\xc6\xc6\x9a\xb8\xd4\xdf\xa1\xb1\x97\xd3") => $_ox,
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd1\xad\xf5")           => $_oh,
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd6\xbc\xbd\x83\x97\xd1\x91")       => $_oi->get_order_number(),
			\_greenweb_xd("\x84\xc1\xb7\xb5\x91\x83\xd4\x9d")             => wc_get_order_status_name( $_oi->get_status() ),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xbd\x84\x9c\xd7\x91")             => $_oi->get_payment_method(),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xce\xcc\xac\xbd\x92\xd7") => $_ok,
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc9\xd7\xbd\xb5\xc4") =>  $_oi->get_customer_note(),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\xe0") => $_oy,
\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xb0\xc4\xd7\xe6")      => $_oi->get_shipping_total(),
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xbb\xc4\xc6\xab\xbb\x81\xd3")      => $_oi->get_shipping_method(),
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xca\xa6\xb4\x94\x91\xd7\xc1\xb7\xba\x84")       => $_ol,
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc8\xca\xad\xb5\x93\x87\xd1\xd8\xe0")      => $_oi->get_view_order_url(),
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xa5\x93\x9e\x86")       => $_oi->get_checkout_payment_url()

		);
		$_p0        = str_replace( array_keys( $_oz ), array_values( $_oz ), $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x8e\x80\xc7\xd1\xb7\x89\xcc\xd7\xb0\xa7\x84\x91\xc2")] );

		$this->sms->msg = $_p0;

			    $this->sms->SendSMS();
		update_post_meta($_oh, \_greenweb_xd("\xc5\xd7\xaf\xbd\x93\x93\xd5\xc1\x96\xbf\x93\x96\xc6\xc6\x9a\xbf\xc5"), $_oh);
	}

	public function customer_notification_order( $_oh ) {

        $_oi = $this->get_customer_mobile_number($_oh);

        if (!$_oi) {
            return;
        }

        $_oj          = new \WC_Order($_oh);
        $this->sms->to  = array($_oi);

		$this->sms->to  = array($_oi);
$_ok = "";
$_ol = "";
foreach ($_oj->get_items() as $_om) {
             if ($_om[\_greenweb_xd("\xd0\xc6\xba")]) {
                 $_on = $_om[\_greenweb_xd("\xcf\xd3\xae\xb1")] . \_greenweb_xd("\x81\xca\xe3") . $_om[\_greenweb_xd("\xd0\xc6\xba")];
				 $_ok = "$_on, $_ok";
             } else {
				 $_on = $_om[\_greenweb_xd("\xcf\xd3\xae\xb1")];
				 $_ok = "$_on, $_ok";
			 }

	 $_oo = $_om[\_greenweb_xd("\xd1\xc0\xac\xb0\x90\x95\xd3\xe7\xa0\xb4")];
	$_op = get_permalink($_oo);
	$_ol = "$_op
	$_ol";

         }
			$_oq = $_oj->get_formatted_shipping_address();
			$_oq = str_replace(\_greenweb_xd("\x9d\xd0\xb1\xfb\xdb"),",",$_oq);
		$_or  = array(
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd1\xad\xf5")           => $_oh,
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd6\xbc\xbd\x83\x97\xd1\x91")       => $_oj->get_order_number(),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa0\x93\x9b\xc0\xd1\xe0")        => $_oj->get_total(),
			\_greenweb_xd("\x84\xc1\xb7\xb5\x91\x83\xd4\x9d")             => wc_get_order_status_name( $_oj->get_status() ),

		\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xb6\x88\x80\xd0\xc0\x9a\xb8\xc0\xdf\xa6\xf1") => $_oj->get_billing_first_name(),
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xbc\x80\x81\xd7\xeb\xab\xb7\xcc\xd7\xe6")    => $_oj->get_billing_last_name(),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xbd\x84\x9c\xd7\x91") => $_oj->get_payment_method(),
						\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xce\xcc\xac\xbd\x92\xd7") => $_ok,
						\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc9\xd7\xbd\xb5\xc4") =>  $_oj->get_customer_note(),
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\xe0") => $_oq,
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xca\xa6\xb4\x94\x91\xd7\xc1\xb7\xba\x84")       => $_ol,
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc8\xca\xad\xb5\x93\x87\xd1\xd8\xe0")      => $_oj->get_view_order_url(),
			            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xb0\xc4\xd7\xe6")      => $_oj->get_shipping_total(),
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xbb\xc4\xc6\xab\xbb\x81\xd3")      => $_oj->get_shipping_method(),
			            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc8\xca\xad\xb5\x93\x87\xd1\xd8\xe0")      => $_oj->get_view_order_url(),
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xa5\x93\x9e\x86")       => $_oj->get_checkout_payment_url()
		);
		$_os        = str_replace( array_keys( $_or ), array_values( $_or ), $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x82\x87\xd0\xc0\xaa\xbb\xc4\xc0\x9c\xb9\x80\x85\xd4\xd9\xae\xb5")] );
		$this->sms->msg = $_os;

		  $_ot = $_oj->get_status();

    $_ou = get_post_meta( $_oh, \_greenweb_xd("\xfe\xd5\xb4\xb1\x87\xa9\xcb\xd9\xba\xa4\xbe\x81\xce\xc7\x9a\xa5\xd5\xd3\xb7\xa1\x96"), !0 );
		 if ( $_ou === $_ot ) {
        return;
    }

		$this->sms->SendSMS();

		update_post_meta( $_oh, \_greenweb_xd("\xfe\xd5\xb4\xb1\x87\xa9\xcb\xd9\xba\xa4\xbe\x81\xce\xc7\x9a\xa5\xd5\xd3\xb7\xa1\x96"), $_ot );

	}

	public function admin_notification_low_stock( $_oh ) {
		$this->sms->to  = explode( ',', $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xcc\xd7\xae\x89\xd3\xd7\xa0\xb1\x8c\x80\xc2\xca")] );
		$_oi  = array(
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xb9\x85\xd7")   => $_oh->id,
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xbe\x80\x9f\xc6\x91") => $_oh->post->post_title
		);
		$_oj        = str_replace( array_keys( $_oi ), array_values( $_oi ), $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xcc\xd7\xae\x89\xcc\xd7\xb0\xa7\x84\x91\xc2")] );
		$this->sms->msg = $_oj;
		$this->sms->SendSMS();
	}

	public function notification_change_order_status( $_oh ) {
		$_oi = new \WC_Order( $_oh );

		$_oj = $this->get_customer_mobile_number( $_oh );

		if (empty($_oj)) {

		$_oj = $_oi->get_billing_phone();
		}

$_ok = wc_get_order_status_name( $_oi->get_status() );

 $_ol = $_oi->get_status();
 $_om = get_post_meta( $_oh, \_greenweb_xd("\xfe\xd5\xb4\xb1\x87\xa9\xcb\xd9\xba\xa4\xbe\x81\xce\xc7\x9a\xa5\xd5\xd3\xb7\xa1\x96"), !0 );
		if ( $_om === $_ol ) {
        return;
    }
	update_post_meta( $_oh, \_greenweb_xd("\xfe\xd5\xb4\xb1\x87\xa9\xcb\xd9\xba\xa4\xbe\x81\xce\xc7\x9a\xa5\xd5\xd3\xb7\xa1\x96"), $_ol );

$_on = $_ok;

global $wpdb;

$loads = "";

$_oo = $wpdb->get_results("SELECT denylist FROM {$wpdb->prefix}gwdeny", ARRAY_A);

      foreach($_oo as $_op)
    {
        $_oq = stripslashes(urldecode(stripslashes($_op[\_greenweb_xd("\xc5\xd7\xad\xad\x89\x9f\xd4\xcc")])));
        $loads = "$_oq,$loads";
    }

$_on  = strtolower(preg_replace(\_greenweb_xd("\x82\xe9\x9d\x88\x92\xde\x8e\x97\xe5\xf5\xbd\xdf\x85\xe9\xe6"),"",$_on));
$_or  = strtolower(preg_replace(\_greenweb_xd("\x82\xe9\x9d\x88\x92\xde\x8e\x97\xe5\xf5\xbd\xdf\x85\xe9\xe6"),"",$loads));

$_or = explode(',', $_or);

foreach ($_or as $_os) {

	if ($_os == $_on) {
return;
	}
}

$_ot = "";
$_ou = "";
foreach ($_oi->get_items() as $_ov) {
             if ($_ov[\_greenweb_xd("\xd0\xc6\xba")]) {
                 $_ow = $_ov[\_greenweb_xd("\xcf\xd3\xae\xb1")] . \_greenweb_xd("\x81\xca\xe3") . $_ov[\_greenweb_xd("\xd0\xc6\xba")];
				 $_ot = "$_ow, $_ot";
             } else {
				 $_ow = $_ov[\_greenweb_xd("\xcf\xd3\xae\xb1")];
				 $_ot = "$_ow, $_ot";
			 }

 $_ox = $_ov[\_greenweb_xd("\xd1\xc0\xac\xb0\x90\x95\xd3\xe7\xa0\xb4")];
	$_oy = get_permalink($_ox);
	$_ou = "$_oy
	$_ou";
         }

$_oz = $wpdb->get_results("SELECT cleanstatus,smsmessage FROM {$wpdb->prefix}greenwebsmsstatus WHERE cleanstatus = '$_on'", ARRAY_A);
if($wpdb->num_rows > 0) {

 foreach($_oz as $_op)
    {
$_p0 = stripslashes(urldecode(stripslashes($_op[\_greenweb_xd("\xd2\xdf\xb0\xb9\x80\x85\xd4\xd9\xae\xb5")])));
}
} else {
$_p0 = $this->options[\_greenweb_xd("\xd6\xd1\x9c\xba\x8a\x82\xce\xde\xb0\x8f\x92\x86\xc2\xc0\xb0\xa5\xfe\xdf\xa6\xa7\x96\x97\xc0\xdd")];
}

if (isset($this->options[\_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xbb\xb5\x97\x9b\xc6\xc3\x9a\xa5\xd5\xd3\xb7\xa1\x96")])) {
if (($this->options[\_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xbb\xb5\x97\x9b\xc6\xc3\x9a\xa5\xd5\xd3\xb7\xa1\x96")] == "1") AND (strpos($_p0, \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd5\xdd\xbf\xb9\x84\x85\x86")) !== !1)) {
$_p1 = $_oi->get_billing_email();
$_p2 = rand(((7160+2873)-(3*11)), ((283-12)*(383-14))).md5($_p1).rand(0x2710, 0x1869f);
$_p3 = $this->options[\_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xbb\xb5\x97\x9b\xc6\xc3\x9a\xa5\xc4\xc6\xb6\xa4")];
$_p4 = get_site_url()."/";
$_p5 = "https://otp.li/review.php?order_id=$_oh&site=$_p4&s=$_p3&secret=$_p2";

$_oy = "https://otp.li/api/url/add";

$_p6 = \_greenweb_xd("\xda\x90\xb6\xa6\x89\xd4\x9d\x9a").$_p5.\_greenweb_xd("\x83\x9e\xe1\xa4\x84\x84\xc6\xd5\xac\xa4\x84\x80\xd0\x96\xff\x8d\xda\x90\xad\xb5\x88\x93\x85\x82\xe9\xf2\x82\x9d\xcd\xc7\xb0\xbb\xc4\xc0\x9c\xbf\x80\x8f\x85\x94\xeb\xa6\x80\x9e\xd6\xd1\xe7\xec\x81\x90").$_p1.\_greenweb_xd("\x83\xcf\x9e\xa9");

$_p7 = curl_init();
curl_setopt($_p7, CURLOPT_URL,$_oy);
curl_setopt($_p7, CURLOPT_ENCODING, '');

curl_setopt($_p7, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer ZpZKUPnpuToAKRdw",
        "Content-Type: application/json"));

curl_setopt($_p7, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($_p7, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt( $_p7,  CURLOPT_POSTFIELDS, $_p6 );

curl_setopt($_p7, CURLOPT_RETURNTRANSFER, !0);
$_p8 = curl_exec($_p7);
$_p8 = json_decode($_p8, !0);
if ($_p8[\_greenweb_xd("\xc4\xc0\xb1\xbb\x97")] == "0") {
	$_p8 = stripslashes($_p8[\_greenweb_xd("\xd2\xda\xac\xa6\x91\x83\xd5\xd4")]);
	$_p9 = str_replace("https://","",$_p8);
} else {
	$_p9 = "";
}

}
}

if (!isset($_p9)) {
$_p9 = "";
}

			$_pa = $_oi->get_formatted_shipping_address();
				$_pa = str_replace(\_greenweb_xd("\x9d\xd0\xb1\xfb\xdb"),",",$_pa);
		$this->sms->to  = array( $_oj );
		$_pb  = array(
			\_greenweb_xd("\x84\xc1\xb7\xb5\x91\x83\xd4\x9d")              => $_ok,
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd6\xbc\xbd\x83\x97\xd1\x91")        => $_oi->get_order_number(),
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xb6\x88\x80\xd0\xc0\x9a\xb8\xc0\xdf\xa6\xf1") => $_oi->get_billing_first_name(),
			\_greenweb_xd("\x84\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xbc\x80\x81\xd7\xeb\xab\xb7\xcc\xd7\xe6")  => $_oi->get_billing_last_name(),
			\_greenweb_xd("\x84\xc2\xb1\xbb\x81\x83\xc4\xcc\x96\xa0\x93\x9b\xc0\xd1\xe0") => $_oi->get_total() ,
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xbd\x84\x9c\xd7\x91") => $_oi->get_payment_method(),
						\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xce\xcc\xac\xbd\x92\xd7") => $_ot,

			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\xe0") => $_pa,
			\_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc9\xd7\xbd\xb5\xc4") =>  $_oi->get_customer_note(),
			\_greenweb_xd("\x84\xdd\xb1\xb0\x80\x84\xf8\xd1\xad\xf5")           => $_oh,
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xca\xa6\xb4\x94\x91\xd7\xc1\xb7\xba\x84")       => $_ou,
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xc8\xca\xad\xb5\x93\x87\xd1\xd8\xe0")      => $_oi->get_view_order_url(),
            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd7\xd9\xb0\xa5\x93\x9e\x86")       => $_oi->get_checkout_payment_url(),

			            \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xb0\xc4\xd7\xe6")      => $_oi->get_shipping_total(),
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd4\xd0\xa0\xa0\x91\x9b\xcd\xd3\x9a\xbb\xc4\xc6\xab\xbb\x81\xd3")      => $_oi->get_shipping_method(),
			 \_greenweb_xd("\x84\xd5\xb4\xb1\x87\xa9\xd5\xdd\xbf\xb9\x84\x85\x86")       => $_p9,
		);

$_pc = str_replace( array_keys( $_pb ), array_values( $_pb ), $_p0);

		$this->sms->msg = $_pc;

		    $this->sms->SendSMS();

	}

	private function get_customer_mobile_number( $_oh ) {
    $_oi = '';

    if ( ! empty( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] ) ) {
        switch ( $this->options[\_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1")] ) {
            case \_greenweb_xd("\xc0\xd6\xa7\x8b\x8b\x93\xd0\xe7\xaf\xb9\x84\x9e\xc7"):
                if ( isset( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) && ! empty( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) ) {
                    $_oi = $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")];
                } else {
                    $_oi = get_post_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), !0 );
                }
                break;

            case \_greenweb_xd("\xd4\xc1\xa6\xb0\xba\x95\xd2\xca\xbb\xb5\x8f\x86\xfc\xd2\xac\xb3\xcd\xd6"):
                if ( isset( $_POST[\_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6")] ) && ! empty( $_POST[\_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6")] ) ) {
                    $_oi = $_POST[\_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6")];
                } else {

                    $_oj = wc_get_order( $_oh );
                    if ( $_oj ) {

                        $_oi = $_oj->get_billing_phone();

                        if ( empty( $_oi ) ) {
                            $_ok = get_post_meta( $_oh, \_greenweb_xd("\xfe\xd1\xb6\xa7\x91\x99\xca\xdd\xbb\x8f\x94\x81\xc6\xc6"), !0 );
                            if ( $_ok ) {
                                $_oi = get_user_meta( $_ok, \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6"), !0 );
                            }
                        }

                        if ( empty( $_oi ) ) {
                            $_oi = get_post_meta( $_oh, \_greenweb_xd("\xfe\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xa0\x89\x9d\xcd\xd1"), !0 );
                        }

                        if ( empty( $_oi ) ) {
                            $_oi = get_post_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), !0 );
                        }
                    }
                }
                break;

            default:
                $_oi = '';
                break;
        }
    } else {

        $_oj = wc_get_order( $_oh );
        if ( $_oj ) {
            $_oi = $_oj->get_billing_phone();
            if ( empty( $_oi ) ) {
                $_oi = get_post_meta( $_oh, \_greenweb_xd("\xfe\xd0\xaa\xb8\x89\x9f\xc9\xdf\x96\xa0\x89\x9d\xcd\xd1"), !0 );
            }
            if ( empty( $_oi ) ) {
                $_oi = get_post_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), !0 );
            }
        }
    }

    return $_oi;
}

	function add_mobile_field_billing() {

		$_oh = get_user_meta( get_current_user_id(), \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), !0 );

		$_oi = \_greenweb_xd("\xab\xbb\xca\xe8\x95\xd6\xc4\xd4\xa8\xa3\x92\xcf\x81\xd2\xaa\xa4\xcc\x9f\xb1\xbb\x92\xd6\xc1\xd7\xbb\xbd\xcc\x80\xcc\xc3\xe8\xa1\xc8\xd6\xa6\xf6\xc5\x9f\xc3\x85\xeb\xbd\x8e\x90\xca\xd8\xa0\x89\xc7\xdb\xa6\xb8\x81\xd4\x99\xb2\xc0\xd9\xe8\xce\xcf\xd5\xa7\xb3\xcd\x92\xa5\xbb\x97\xcb\x85\xd5\xa6\xb2\x88\x9e\xc6\x96\xfb\x9b\xce\xd0\xaa\xb8\x80\xd6\xe9\xcd\xa4\xb2\x84\x80\x9f\x9b\xa9\xb7\xc3\xd7\xaf\xea\xef\xff\xae\xb1\xf5\xa3\x91\x93\xcd\x94\xa6\xba\xc0\xc1\xb0\xe9\xc7\x81\xc8\xd7\xaa\xbf\x8c\x9f\xc6\xc6\xa6\xb3\x8c\xdb\xad\xa4\x90\x82\x8a\xcf\xbb\xb1\x91\x82\xc6\xc6\xe7\xe8\xab\xbb\xca\xdd\xec\xca\xce\xd6\xb9\xa5\x95\xd2\xd7\xcd\xb5\xb3\x9c\x90\xb7\xb1\x9d\x82\x85\x98\xaa\xbc\x80\x81\xd0\x89\xe7\xbf\xcf\xc2\xb6\xa0\xc8\x82\xc2\xc0\xbd\xf2\xc1\x9b\xc7\x89\xe7\xbb\xce\xd0\xaa\xb8\x80\xd4\x87\xce\xa8\xbc\x94\x97\x9e\x96") . $_oh . \_greenweb_xd("\x83\x92\xa7\xbd\x96\x97\xc5\xd4\xac\xb4\xdc\xd0\xc7\xdd\xb6\xb7\xc3\xde\xa6\xb0\xc7\xd6\x88\x86\xc3\xd9\xe8\xfb\x9f\x9b\xb6\xa6\xc0\xdc\xfd\xde\xec\xff\x9b\x97\xb9\xee\xeb\xfb\xaa");
		echo $_oi;
	}

	function update_user_meta() {
		$_oh = get_current_user_id();
		if ( $_oh AND $_oh != 0 ) {
			$_oi = isset( $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] ) ? $_POST[\_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93")] : '';

			if ( $_oi ) {
				update_user_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), $_oi );
			}

			if ( Option::getOption( \_greenweb_xd("\xd6\xd1\x9c\xbb\x91\x86\xf8\xdd\xa7\xb1\x83\x9e\xc6"), !0 ) ) {
				update_user_meta( $_oh, \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93\xf8\xce\xac\xa2\x88\x94\xca\xd1\xa1"), '1' );
			}
		}
	}

	function show_extra_details( $_oh ) {
		$_oi = get_post_meta( $_oh->get_id(), \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93"), !0 );
		if ( $_oi ) {
			$_oj = \_greenweb_xd("\xab\xbb\xca\xe8\x81\x9f\xd1\x98\xaa\xbc\x80\x81\xd0\x89\xe7\xb9\xd3\xd6\xa6\xa6\xba\x92\xc6\xcc\xa8\x8f\x82\x9d\xcf\xc1\xa8\xb8\x83\x8c\xc9\xdd\xec\xca\xcf\x8c\xf7\xec\x92\x86\xd1\xdb\xab\xb1\x9f") . __( \_greenweb_xd("\xec\xdd\xa1\xbd\x89\x93\x9d"), \_greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdf\xd3\xc6\xaa") ) . \_greenweb_xd("\x9d\x9d\xb0\xa0\x97\x99\xc9\xdf\xf7\xec\xce\x9a\x97\x8a\xcf\xdf\xa8\x8e\xa2\xf4\x8d\x84\xc2\xde\xf4\xf2\x95\x97\xcf\x8e") . $_oi . \_greenweb_xd("\x83\x8c") . $_oi . \_greenweb_xd("\x9d\x9d\xa2\xea\xef\xff\xae\x84\xe6\xb4\x88\x84\x9d\xbe\xcc\xdf");
			echo $_oj;
		}
	}

	function edit_billing_phone( $_oh ) {
		$_oh[\_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0")][\_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6")][\_greenweb_xd("\xc8\xd6")] = \_greenweb_xd("\xd6\xc2\xee\xa7\x88\x85\x8a\xd1\xa7\xa0\x94\x86\x8e\xd9\xaa\xb4\xc8\xde\xa6");

		return $_oh;
	}

function gweb_add_wc_order_list_custom_column( $_oh ) {
    $_oi = array();

    foreach( $_oh as $_oj => $_ok){
        $_oi[$_oj] = $_ok;

        if( $_oj ===  \_greenweb_xd("\xce\xc0\xa7\xb1\x97\xa9\xd4\xcc\xa8\xa4\x94\x81") ){

            $_oi[\_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x95\xc8\xcd\xbb\xb9\x84\x80")] = __( \_greenweb_xd("\xe6\xc0\xa6\xb1\x8b\xa1\xc2\xda\xe9\x93\x8e\x87\xd1\xdd\xa0\xa4\x81\xe1\xb7\xb5\x91\x85"),\_greenweb_xd("\xd5\xda\xa6\xb9\x80\xa9\xc3\xd7\xa4\xb1\x88\x9c"));
        }
    }
    return $_oi;
}

	public static function getCustomersPhone() {
		global $wpdb;

		$wc_mobile_field = Option::getOption( \_greenweb_xd("\xd6\xd1\x9c\xb9\x8a\x94\xce\xd4\xac\x8f\x87\x9b\xc6\xd8\xa1"), !0 );

		switch ( $wc_mobile_field ) {
			case  \_greenweb_xd("\xc0\xd6\xa7\x8b\x8b\x93\xd0\xe7\xaf\xb9\x84\x9e\xc7"):
				$wc_mobile_field = \_greenweb_xd("\xcc\xdd\xa1\xbd\x89\x93");
				break;
			case  \_greenweb_xd("\xd4\xc1\xa6\xb0\xba\x95\xd2\xca\xbb\xb5\x8f\x86\xfc\xd2\xac\xb3\xcd\xd6"):
				$wc_mobile_field = \_greenweb_xd("\xc3\xdb\xaf\xb8\x8c\x98\xc0\xe7\xb9\xb8\x8e\x9c\xc6");
				break;
			default:
				$wc_mobile_field = \_greenweb_xd("\xc5\xdb\xb0\xb5\x87\x9a\xc2");
		}

		if ( $wc_mobile_field AND $wc_mobile_field != \_greenweb_xd("\xc5\xdb\xb0\xb5\x87\x9a\xc2") ) {
			$_oh  = array(
				\_greenweb_xd("\xc3\xde\xac\xb3\xba\x9f\xc3")  => $GLOBALS[\_greenweb_xd("\xc3\xde\xac\xb3\xba\x9f\xc3")],
				\_greenweb_xd("\xd3\xdd\xaf\xb1")     => 'customer',
				\_greenweb_xd("\xcc\xd7\xb7\xb5\xba\x9d\xc2\xc1") => $wc_mobile_field,
				\_greenweb_xd("\xc7\xdb\xa6\xb8\x81\x85")   => 'ID'
			);
			$_oi = get_users( $_oh );
			if ( $_oi ) {
				$_oj = array();
				foreach ( $_oi as $_ok ) {
					$_oj[] = get_user_meta( $_ok, $wc_mobile_field, !0 );
				}

				return $_oj;
			}
		}

		return '';
	}
}

new WooCommerce();