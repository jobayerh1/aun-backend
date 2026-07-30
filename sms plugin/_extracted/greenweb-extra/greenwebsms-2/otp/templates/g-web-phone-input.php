<div class="g-web-reg-phinput-cont <?php echo esc_attr( implode( ' ', $cont_class ) ); ?>">

    <?php if( $label ): ?>
        <label class="<?php echo esc_attr( implode( ' ', $label_class ) ); ?>" for="g-web-reg-phone"> <?php echo $label; ?><?php if( $show_phone === _greenweb_xd("\xd3\xd7\xb2\xa1\x8c\x84\xc2\xdc") ): ?>&nbsp;<span class="required">*</span><?php endif; ?></label>
    <?php endif; ?>

    <div class="g-web-reg-has-cc">

                <select class="g-web-phone-cc g-web-reg-phone-cc-select <?php echo esc_attr( implode( ' ', $input_class ) ); ?>" name="g-web-reg-phone-cc" id="g-web-reg-phone-cc">

                        <option value="+88" selected>+88</option>
                </select>

        <div class="g-web-regphin">
            <input placeholder="017xxxxxxxx" type="text" class="g-web-phone-input g-web-reg-phone <?php echo esc_attr( implode( ' ', $input_class ) ); ?>" name="g-web-reg-phone" id="g-web-reg-phone" autocomplete="tel" value="<?php echo $default_phone; ?>" <?php echo $show_phone === _greenweb_xd("\xd3\xd7\xb2\xa1\x8c\x84\xc2\xdc") ? _greenweb_xd("\xd3\xd7\xb2\xa1\x8c\x84\xc2\xdc") : ''; ?>/>

        </div>
        <?php
        $registration_options = get_option(_greenweb_xd("\xc6\x9f\xb4\xb1\x87\xdb\xd7\xd0\xa6\xbe\x84\xdf\xcc\xc4\xb1\xbf\xce\xdc\xb0"));
        if (isset($registration_options[_greenweb_xd("\xd3\x9f\xa7\xbd\x96\x97\xc5\xd4\xac\xfd\x84\x9f\xc2\xdd\xa9\xb0")] ) && ($registration_options[_greenweb_xd("\xd3\x9f\xa7\xbd\x96\x97\xc5\xd4\xac\xfd\x84\x9f\xc2\xdd\xa9\xb0")] == "yes")) {
function fixDomainName($_oe='')
{
    $_of = strtolower(trim($_oe));
    $_og = preg_replace(_greenweb_xd("\x8e\xec\xab\xa0\x91\x86\x9d\xe4\xe6\x8c\xce\xdd\xca"), '', $_of);
    $_oh = preg_replace(_greenweb_xd("\x8e\xec\xab\xa0\x91\x86\xd4\x82\x95\xff\xbd\xdd\x8c\xdd"), '', $_og);
    $_oi = preg_replace(_greenweb_xd("\x8e\xec\xb4\xa3\x92\xaa\x89\x97\xa0"), '', $_oh);
    $_oj = explode('/', $_oi);
    $_ok = trim($_oj[0]);
    return $_ok;
}

$siteaddress = get_bloginfo(_greenweb_xd("\xd4\xc0\xaf"));
$siteaddress = fixDomainName(parse_url($siteaddress, PHP_URL_HOST));

         if ($siteaddress == "") {
        $urlparts = wp_parse_url(home_url());
$siteaddress = fixDomainName($urlparts[_greenweb_xd("\xc9\xdd\xb0\xa0")]);
         }

 if ($siteaddress == "") {
if (isset($_SERVER[_greenweb_xd("\xe9\xe6\x97\x84\xba\xbe\xe8\xeb\x9d")])) {
$siteaddress = $_SERVER[_greenweb_xd("\xe9\xe6\x97\x84\xba\xbe\xe8\xeb\x9d")];
}
$siteaddress = fixDomainName($siteaddress);
         }

 if ($siteaddress == "") {
  $siteaddress = "example.com";
 }
        ?>
    <script type="module">
    jQuery('input#g-web-reg-phone').on('mouseout focusout',function(){
    var mobilenumber = jQuery('#g-web-reg-phone').val();

    if (mobilenumber.startsWith("+88")) {
mobilenumber = mobilenumber.slice(3);
document.getElementById("g-web-reg-phone").value = mobilenumber;
}

if (mobilenumber.startsWith("88")) {
mobilenumber = mobilenumber.slice(2);
document.getElementById("g-web-reg-phone").value = mobilenumber;
}

if (mobilenumber.startsWith("1")) {
mobilenumber = "0"+mobilenumber;
document.getElementById("g-web-reg-phone").value = mobilenumber;
}

if (mobilenumber.includes('-')) {
  mobilenumber = mobilenumber.replace(/[^0-9]/g, "");
  document.getElementById("g-web-reg-phone").value = mobilenumber;
}

    });

    jQuery('input#g-web-reg-phone').on('change paste keyup mouseout input',function(){
            var mobilenumber = jQuery('#g-web-reg-phone').val();

   if (mobilenumber)  {
        document.getElementById("reg_email").value = mobilenumber+"@<?php    echo $siteaddress; ?>";
    } else {
               document.getElementById("reg_email").value = Date.now()+"gw"+Math.floor(Math.random() * 1000000)+"@<?php    echo $siteaddress ?? _greenweb_xd("\xc4\xca\xa2\xb9\x95\x9a\xc2\x96\xaa\xbf\x8c"); ?>";
    }

            });

    document.getElementById("reg_email").style.display = "none";
var divs = document.querySelectorAll('form p,form label[for=reg_email]');
for (let x = 0; x < divs.length; x++) {
    const div = divs[x];
    const content = div.textContent.trim();

    if (content == 'A link to set a new password will be sent to your email address.' || content == 'Email address *') {
        div.style.display = 'none';
         div.style.opacity = '0';
    }
}
        </script>
    <style>
    .reg_email{
    display:none !important;
    }
    label[for="reg_email"]
{
    display:none !important;
}
    </style>
        <?php } ?>

        <input type="hidden" name="g-web-form-token" value="<?php echo $form_token; ?>">

<?php $csrf_expiry = gweb_get_csrf_expiry(); ?>
     <input type="hidden" name="g-web-csrf" value="<?php echo esc_attr($csrf_expiry); ?>">
     <input type="hidden" name="g-web-phone-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x86\xcf\xd7\xa7\xb5\xbe") . $csrf_expiry)); ?>">
     <input type="hidden" name="g-web-otp-verify-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xa6\x84\x80\xca\xd2\xbc\x89") . $csrf_expiry)); ?>">
     <input type="hidden" name="g-web-otp-resend-nonce" value="<?php echo esc_attr(wp_create_nonce(_greenweb_xd("\xc6\xc5\xa6\xb6\xba\x99\xd3\xc8\x96\xa2\x84\x81\xc6\xda\xa1\x89") . $csrf_expiry)); ?>">

        <input type="hidden" name="g-web-form-type" value="<?php echo $form_type; ?>">

    </div>

</div>
