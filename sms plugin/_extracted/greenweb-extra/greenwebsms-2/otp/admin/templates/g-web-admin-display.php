<?php
$current_tab = isset( $_GET[_greenweb_xd("\xd5\xd3\xa1")] ) ? $_GET[_greenweb_xd("\xd5\xd3\xa1")] : _greenweb_xd("\xd1\xda\xac\xba\x80");
?>
<link rel="stylesheet" media="screen" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/><script>
window.addEventListener("DOMContentLoaded", (event) => {
let menu = document.querySelector('.menugweb');
let toggle = document.querySelector('.togglegweb');
let gwebsetcontent = document.querySelector('.g-container');

toggle.addEventListener('click', () => {
  menu.classList.toggle('active');
	gwebsetcontent.classList.toggle('active');
});

var dropdown = document.getElementsByClassName("dropdown-btn");
var i;

for (i = 0; i < dropdown.length; i++) {
  dropdown[i].addEventListener("click", function() {

    this.classList.toggle("active");
    var dropdownContent = this.nextElementSibling;
    if (dropdownContent.style.display === "block") {
      dropdownContent.style.display = "none";
    } else {
      dropdownContent.style.display = "block";
    }
  });
}
});
</script>
<style>
.cardgweb {
  position: absolute;
	max-width: 225px;
	margin:35px 5px 0 2px;

    top: 100px;
   display:inline-flex;
	}
@media screen and (max-width: 450px) {
.menugweb {
  width: 67px;
  background-color: #ffffff;
  border-radius: 10px;
  box-shadow: 10px 0 50px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  transition: 0.5s;
	margin-left: -30px;
}
}
@media screen and (min-width: 451px) {
.menugweb {
  width: 67px;
  background-color: #ffffff;
  border-radius: 10px;
  box-shadow: 10px 0 50px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  transition: 0.5s;
}
}

.menugweb.active {
  width: 225px;

}

.togglegweb {
  position: absolute;
  top: -20px;
  right: -15px;
  height: 40px;
  width: 40px;
  border-radius: 50%;
  z-index: 1;
  background-color: #000000;
  border: 4px solid #666666;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.line-1gweb,
.line-2gweb {
  height: 2px;
  width: 15px;
  background-color: #ffffff;
  transition: 0.5s ease;
}

.line-1gweb {
  transform: rotate(-35deg) translate(5px, 8px);
}

.line-2gweb {
  transform: rotate(35deg) translate(-8px, 0px);
}

.menugweb.active .line-1gweb {
  transform: rotate(-45deg) translate(5px, 6px);
}

.menugweb.active .line-2gweb {
  transform: rotate(45deg) translate(-5px, 6px);
}

.ulgweb {
  position: relative;
  width: 100%;
}

.ligweb {
  position: relative;
  list-style: none;
  height: 35px;
  width: 100%;
  padding-left: 10px;
  display: flex;
  align-items: center;
  cursor: pointer;

}
.menugweb a {

    text-decoration: none;
    color:#3c434a;

}
.ligweb:hover {
  background-color: #dddddd;
}

.spangweb {
  margin-left: 15px;
  white-space: nowrap;
  visibility: hidden;
  opacity: 0;
}

.menugweb.active .spangweb {
  visibility: visible;
  opacity: 1;
}

.dropdown-btn {

  white-space: nowrap;
   position: relative;
  list-style: none;
  height: 35px;
  width: 100%;

  display: flex;
  align-items: center;
  cursor: pointer;
	margin-left: 0px;
}

.dropdown-btn:hover {
background-color: #dddddd;
}

.dropdown-container {
  display: none;

  background:#efe9a6;
}
	.activedrop {
		background-color: #5397eb;
    border-radius: 5px;
    color: white;
		 position: relative;
  list-style: none;
  height: 35px;
  width: 100%;
  padding-left: 10px;
  display: flex;
  align-items: center;
  cursor: pointer;
	}

.fa-caret-down {
  float: right;
  padding-right: 8px;
}
	.g-container{
		width:79%;
		float:right;
		padding: 0px;
		border: none;
		background: #f0f0f1;
	}
	.g-container.active{
		width:93%;
		float:right;
		padding: 0px;
		border: none;
		background: #f0f0f1;
	}

	.greenwebsms-tab-group {
    border: 1px solid #d8d8d8;
    width: 100%;
    float: left;
    background-color: #ffffff;
    margin-top: 18px;
    border-radius: 3px;
}
	.greenwebsms-tab-content {
		background: url(https://sms.greenweb.com.bd/images/wp-bg.webp);
		background-size: cover;
		display: grid;
		padding: 6px 33px;
		border: 2px dashed #dc3232;
		border-radius: 3px;
		min-height: 564px;
		margin: 20px;
	}

</style>

<div class="cardgweb">

  <div class="menugweb active">
    <div class="togglegweb">
      <div class="line-1gweb"></div>
      <div class="line-2gweb"></div>
    </div>
      <ul class="ulgweb">
		  <li class="">
			  <span class="spangweb"><img src="https://bdbulksms.net/images/bdbulksmslogo.gif" style="width:85%;"></img></span>
        </li>

		   <a href="https://otp.li/phelp" target="_blank"> <li class="ligweb">
          <i class="fa-solid fa-person-chalkboard fa-2xl" style="color: #fd5c17;margin-right:-12px;"></i>
          <span class="spangweb">Setup Tutorial</span>
        </li></a>
       <a href="admin.php?page=greenweb-sms-settings&tab=general"> <li class="ligweb">
          <i class="fa-solid fa-sliders fa-2xl" style="color: #fd5c17;margin-right:-8px;"></i>
			<span class="spangweb  <?php if ($current_tab=="general") {echo "activedrop";}?>">Basic Setup</span>
        </li></a>
       <a href="admin.php?page=greenweb-sms-settings&tab=gateway"> <li class="ligweb  ">
          <i class="fa-solid fa-comment-sms fa-2xl" style="color: #fd5c17;margin-right:-8px;"></i>
          <span class="spangweb <?php if ($current_tab=="gateway") {echo "activedrop";}?>">Gateway Setup</span>
        </li></a>

    <button class="dropdown-btn "><i class="fa-brands fa-wordpress fa-2xl" style="color: #fd5c17;margin-right:-8px;"></i> <span class="spangweb">Wordpress Menu</span>
    <i class="fa fa-caret-down"></i>
  </button>
  <div class="dropdown-container" <?php if (($current_tab=="newsletter") OR ($current_tab=="wp") OR ($current_tab=="notifications")) { echo "style='display:block'";}?>>
	  <a href="admin.php?page=greenweb-sms-settings&tab=newsletter"> <li class="ligweb">
		  <i class="fa-solid fa-square-rss fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		  <span class="spangweb <?php if ($current_tab=="newsletter") {echo "activedrop";}?>">SMS Newsletter</span></li></a>
    <a href="admin.php?page=greenweb-sms-settings&tab=notifications"><li class="ligweb"> <i class="fa-solid fa-bell fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		<span class="spangweb  <?php if ($current_tab=="notifications") {echo "activedrop";}?>">Blog Notifications</span></li></a>
   <a href="admin.php?page=greenweb-sms-extra&tab=wp"> <li class="ligweb"><i class="fa-solid fa-key fa-xl" style="color: #8c217e;margin-left:10px;"></i>
	   <span class="spangweb   <?php if ($current_tab=="wp") {echo "activedrop";}?>">OTP for Wordpress User</span></li></a>
  </div>

		  <button class="dropdown-btn"><i class="fa-solid fa-cart-arrow-down fa-2xl" style="color: #fd5c17;margin-right:-10px;"></i><span class="spangweb">WooCommerce Menu</span>
    <i class="fa fa-caret-down"></i>
  </button>
  <div class="dropdown-container" <?php if (($current_tab=="wc") OR ($current_tab=="greenwebwccotp") OR ($_GET[_greenweb_xd("\xd1\xd3\xa4\xb1")]=="g-web") OR ($current_tab=="gwebreview") OR ($current_tab=="gwebwcstock")) { echo "style='display:block'";}?>>
	 <a href="admin.php?page=greenweb-sms-extra&tab=wc"> <li class="ligweb"><i class="fa-solid fa-money-bill fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		 <span class="spangweb  <?php if ($current_tab=="wc") {echo "activedrop";}?>">Order Notification</span></li></a>
   <a href="admin.php?page=greenweb-sms-extra&tab=greenwebwccotp"> <li class="ligweb"><i class="fa-solid fa-shop-lock fa-xl" style="color: #8c217e;margin-left:10px;"></i>
	   <span class="spangweb   <?php if ($current_tab=="greenwebwccotp") {echo "activedrop";}?>">Checkout Page OTP</span></li></a>
	     <a href="admin.php?page=g-web"><li class="ligweb"><i class="fa-solid fa-user-lock fa-xl" style="color: #8c217e;margin-left:10px;"></i>
			 <span class="spangweb  <?php if ($_GET[_greenweb_xd("\xd1\xd3\xa4\xb1")]=="g-web") {echo "activedrop";}?>">Login & Registration OTP</span></li></a>

	  <a href="admin.php?page=greenweb-sms-extra&tab=gwebwcstock"> <li class="ligweb"><i class="fa-solid fa-boxes-stacked fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		  <span class="spangweb   <?php if ($current_tab=="gwebwcstock") {echo "activedrop";}?>">Product Stock Notification</span></li></a>

   <a href="admin.php?page=greenweb-sms-extra&tab=gwebreview"> <li class="ligweb"><i class="fa-solid fa-comments fa-xl" style="color: #8c217e;margin-left:10px;"></i>
	   <span class="spangweb   <?php if ($current_tab=="gwebreview") {echo "activedrop";}?>">Product Review (Via SMS)</span></li></a>
  </div>

		  <button class="dropdown-btn"><i class="fa-solid fa-cubes fa-2xl" style="color: #fd5c17;margin-right:-10px;"></i> <span class="spangweb">Product Back in Stock SMS</span>
    <i class="fa fa-caret-down"></i>
  </button>
  <div class="dropdown-container" <?php if (($current_tab=="pending") OR ($current_tab=="sent") OR ($current_tab=="settings")) { echo "style='display:block'";}?>>
	 <a href="admin.php?page=gweb-stock-notify&tab=pending&groupby=mobile"> <li class="ligweb">
		 <i class="fa-solid fa-circle-exclamation fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		 <span class="spangweb  <?php if ($current_tab=="pending") {echo "activedrop";}?>">Pending SMS Alert</span></li></a>
    <a href="admin.php?page=gweb-stock-notify&tab=sent&groupby=mobile"><li class="ligweb">
		<i class="fa-solid fa-check fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		<span class="spangweb  <?php if ($current_tab=="sent") {echo "activedrop";}?>">BIS Sent Alert</span></li></a>
   <a href="admin.php?page=gweb-stock-notify&tab=settings&groupby=mobile"> <li class="ligweb">
	   <i class="fa-solid fa-square-rss fa-xl" style="color: #8c217e;margin-left:10px;"></i>
	   <span class="spangweb   <?php if ($current_tab=="settings") {echo "activedrop";}?>">Manage Back in Stock SMS</span></li></a>
  </div>

    <button class="dropdown-btn"><i class="fa-brands fa-wpforms fa-2xl" style="color: #fd5c17;margin-right:-5px;"></i> <span class="spangweb">Integrate with Form
    <i class="fa fa-caret-down"></i>
  </button>
  <div class="dropdown-container" <?php if (($current_tab=="gwebcf7form") OR ($current_tab=="qf") OR ($current_tab=="gf")) { echo "style='display:block'";}?>>
	 <a href="admin.php?page=greenweb-sms-settings&tab=gwebcf7form"> <li class="ligweb"><i class="fa-solid fa-table fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		 <span class="spangweb  <?php if ($current_tab=="gwebcf7form") {echo "activedrop";}?>">Contact From 7</span></li></a>
    <a href="admin.php?page=greenweb-sms-extra&tab=qf"><li class="ligweb"><i class="fa-solid fa-square-rss fa-xl" style="color: #8c217e;margin-left:10px;"></i>
		<span class="spangweb  <?php if ($current_tab=="qf") {echo "activedrop";}?>">Qu Forms</span></li></a>
   <a href="admin.php?page=greenweb-sms-extra&tab=gf"> <li class="ligweb"><i class="fa-solid fa-square-rss fa-xl" style="color: #8c217e;margin-left:10px;"></i>
	   <span class="spangweb   <?php if ($current_tab=="gf") {echo "activedrop";}?>">GForms</span></li></a>
  </div>

		  <a href="admin.php?page=greenweb-sms-extra&tab=as" > <li class="ligweb ">
          <i class="fa-solid fa-headset fa-2xl" style="color: #fd5c17;margin-right:-9px;"></i>
          <span class="spangweb <?php if ($current_tab=="as") {echo "activedrop";}?>">Awesome Support</span>
        </li></a>

		  <a href="admin.php?page=greenweb-sms-extra&tab=job"> <li class="ligweb ">
           <i class="fa-solid fa-user-tie fa-2xl" style="color: #fd5c17;margin-right:-7px;"></i>
          <span class="spangweb <?php if ($current_tab=="job") {echo "activedrop";}?>">WP Job Manager</span>
        </li></a>

		  <a href="admin.php?page=greenweb-sms-extra&tab=gwebpageotp"> <li class="ligweb ">
           <i class="fa-solid fa-shield-halved fa-2xl" style="color: #fd5c17;margin-right:-7px;"></i>
          <span class="spangweb <?php if ($current_tab=="gwebpageotp") {echo "activedrop";}?>">Protect Page/Post By OTP</span>
        </li></a>

      </ul>
  </div>
</div>

<?php $option_name = _greenweb_xd("\xc6\x9f\xb4\xb1\x87\xdb").$current_tab._greenweb_xd("\x8c\xdd\xb3\xa0\x8c\x99\xc9\xcb"); ?>

<div class="wrap g-container" style="margin-top: 30px;">
	<?php if (isset($_GET[_greenweb_xd("\xd2\xd7\xb7\xa0\x8c\x98\xc0\xcb\xe4\xa5\x91\x96\xc2\xc0\xa0\xb2")])) : ?>
	<div class="notice notice-success is-dismissible"><p><?php _e(_greenweb_xd("\xf2\xd7\xb7\xa0\x8c\x98\xc0\xcb\xe9\x83\x80\x84\xc6\xd0\xe5\x85\xd4\xd1\xa0\xb1\x96\x85\xc1\xcd\xa5\xbc\x98\xd2\x82")); ?>.</p></div>
<?php endif; ?>
	<div class="greenwebsms-tab-group g-main">
		<div class="greenwebsms-tab-content">

		<?php do_action( _greenweb_xd("\xc6\xed\xb4\xb1\x87\xa9\xc6\xdc\xa4\xb9\x8f\xad\xd0\xd1\xb1\xa2\xc8\xdc\xa4\xa7\xba\x85\xd3\xd9\xbb\xa4") ); ?>

			<form method="post" action="options.php">
				<?php

				settings_fields( $option_name );

				do_settings_sections( $option_name );

				?><p class="submit" style="text-align:center !important;"><?php submit_button( _greenweb_xd("\xf2\xd3\xb5\xb1\xc5\xa5\xc2\xcc\xbd\xb9\x8f\x95\xd0"), _greenweb_xd("\xd1\xc0\xaa\xb9\x84\x84\xde"), _greenweb_xd("\xd2\xc7\xa1\xb9\x8c\x82"), !1 ); ?></p><?php
				?>

			</form>

		</div>
	</div>

</div>