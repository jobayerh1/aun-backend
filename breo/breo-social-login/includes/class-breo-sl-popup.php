<?php
/**
 * Sign in through a popup window instead of navigating the whole page away.
 *
 * HOW IT WORKS
 *   The buttons are ordinary links to our own /?breo_sl=start flow. This script
 *   intercepts the click and opens that same URL in a small window with
 *   &popup=1. The server does the entire OAuth exchange exactly as it always
 *   has; on the last step it renders a tiny page that postMessages the result
 *   back to the opener and closes itself. The parent then reloads.
 *
 * GOOGLE IS DIFFERENT, AND THAT IS THE POINT
 *   A window we open ourselves is still a window: address bar, covers the page.
 *   The browser's OWN dialog — headed "Sign in to <site> with google.com", drawn
 *   by Chrome, no address bar — is FedCM Button Mode.
 *
 *   Per Google's reference it can ONLY be raised by THEIR rendered button:
 *   google.accounts.id.renderButton() with use_fedcm_for_button: true. A custom
 *   anchor cannot summon it. Two earlier builds tried:
 *     1. prompt() with isNotDisplayed()/isSkippedMoment() — those THROW under
 *        FedCM, so the catch fell through to a redirect on every click.
 *     2. prompt() with getMomentType() — One Tap's prompt is a passive surface;
 *        it does not give a button the dialog, so it opened a window instead.
 *   Note also that use_fedcm_for_prompt, which this plugin used to set, is
 *   deprecated and ignored — it was never doing anything.
 *
 *   The trade-off: Google's button is styled by Google, so its look comes from
 *   GsiButtonConfiguration rather than our CSS. The options below map the
 *   plugin's shape/size settings onto the nearest equivalents.
 *
 * WHY NOT THE PROVIDER SDKs FOR FACEBOOK — a first attempt used FB.login() and
 *   failed on the live site with "JSSDK Option is Not Toggled": it needs "Login
 *   with JavaScript SDK" switched on in the Facebook app plus a domain allow-list,
 *   and the failure appeared as a dead-end error page inside the popup instead of
 *   a clean fallback. Driving our own flow in a window needs no third-party
 *   script, no extra provider settings, and let a token-accepting endpoint be
 *   deleted — a real reduction in attack surface.
 *
 * FALLBACK
 *   If the popup cannot open — blocker, in-app browser, JavaScript off — the
 *   click proceeds to the link as normal and the visitor gets the full-page
 *   redirect. Nothing here can prevent someone signing in.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class BREO_SL_Popup {

	public static function init() {
		if ( ! BREO_SL_Options::get( 'js_flow' ) ) {
			return;
		}
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 98 );
		add_action( 'login_footer', array( __CLASS__, 'render' ), 98 );
	}

	public static function render() {
		if ( is_user_logged_in() || ! BREO_SL_Options::active_providers() ) {
			return;
		}

		// The popup posts its result to this exact origin, and we check it again
		// here. Same language-neutral base the OAuth URLs are built from.
		$origin = untrailingslashit( set_url_scheme( get_option( 'home' ) ) );

		/*
		 * Google draws its own button, so its appearance comes from their options
		 * rather than our CSS. These map the plugin's existing look onto the
		 * nearest thing GsiButtonConfiguration allows, so the row still matches.
		 */
		$o    = BREO_SL_Options::all();
		$size = (int) $o['size'];
		$labelled = ! empty( $o['show_label'] );
		$gbtn = array(
			'type'           => $labelled ? 'standard' : 'icon',
			'shape'          => ( 'round' === $o['shape'] ) ? 'circle' : 'square',
			'theme'          => 'outline',
			// Google's sizes are 40 / 32 / 20px tall; 'large' matches our 44px+ buttons best.
			'size'           => ( $size >= 44 ) ? 'large' : ( ( $size >= 36 ) ? 'medium' : 'small' ),
			// Google accepts only its own four phrasings; 'brand' is ours alone, so
			// the nearest legal equivalent is used when Google draws the button.
			'text'           => ( 'brand' === $o['label_style'] ) ? 'signin_with' : $o['label_style'],
			'logo_alignment' => 'left',
			// Draw Google's label in the site's language, not the visitor's Google/browser language.
			'locale'         => get_locale(),
		);
		/*
		 * Only the standard (labelled) button accepts a width, and that is the one
		 * lever we have for making the two buttons line up exactly. The icon button
		 * has no width option at all — there, the only option is to measure what
		 * Google drew and size ours to it.
		 */
		if ( $labelled ) {
			$gbtn['width'] = '400'; // Google's maximum; the script narrows it to fit the card.
			/*
			 * Follow the admin's Icon shape rather than forcing a pill. Google offers
			 * only 'pill' and 'rectangular' for a wide button, so 'rounded' and
			 * 'square' both land on rectangular — Google will not render a hard 0px
			 * corner, and our button matches whatever they draw.
			 */
			$gbtn['shape'] = ( 'round' === $o['shape'] ) ? 'pill' : 'rectangular';
		}
		?>
<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
(function(){
  var ORIGIN = <?php echo wp_json_encode( $origin ); ?>;
  var win = null, poll = null;

  function cleanup(){
    if (poll) { clearInterval(poll); poll = null; }
    win = null;
  }

  window.addEventListener('message', function(ev){
    /* Only ever trust our own origin, and only our own shape of message. */
    if (ev.origin !== ORIGIN) return;
    var d = ev.data;
    if (!d || d.breo_sl !== 'done') return;

    cleanup();

    if (d.ok && d.redirect) {
      window.location.assign(d.redirect);
    } else {
      /* Reload so the refusal notice renders through the normal server path,
         rather than trying to reproduce the message here. */
      var u = new URL(window.location.href);
      if (d.code) { u.searchParams.set('breo_sl_error', d.code); }
      window.location.assign(u.toString());
    }
  });

  /* ------------------------------------------------------------ Google ---
     Chrome's own account-chooser dialog — no address bar, headed "Sign in to
     <site> with google.com" — is FedCM Button Mode. Per Google's reference it
     can ONLY be raised by their own rendered button (renderButton) with
     use_fedcm_for_button enabled. A custom anchor calling prompt() cannot do it;
     two earlier builds tried and both ended up opening a window instead.

     So Google's button is mounted in place of ours, and ours is hidden. If the
     library never arrives, or renderButton throws, our anchor simply stays
     visible and keeps the window/redirect behaviour. */
  var GBTN    = <?php echo wp_json_encode( $gbtn ); ?>;
  var GNATIVE = <?php echo wp_json_encode( 'native' === $o['google_button'] ); ?>;

  function mountGoogle(){
    /* 'custom' means the site owner chose their own button wording over the
       browser dialog. Leave our anchor alone; the click handler gives it the
       popup window. */
    if (!GNATIVE) { return true; }
    if (!(window.google && google.accounts && google.accounts.id &&
          typeof google.accounts.id.renderButton === 'function')) { return false; }

    var anchors = document.querySelectorAll('a.breo-sl-google');
    if (!anchors.length) return true;   // nothing to do on this page

    var mounted = 0;
    Array.prototype.forEach.call(anchors, function(a){
      if (a.getAttribute('data-breo-g') === '1') { mounted++; return; }
      var host = document.createElement('span');
      host.className = 'breo-sl-gbtn';
      var row = a.closest ? a.closest('.breo-sl') : null;
      try {
        a.parentNode.insertBefore(host, a);
        var cfg = Object.assign({}, GBTN);
        // Google draws a fixed-width iframe: fit it to the row (200–400px, their limits) so it never overflows a phone.
        if (cfg.width) { var avail = (host.parentNode && host.parentNode.clientWidth) || 400; cfg.width = String(Math.max(200, Math.min(400, avail))); }
        google.accounts.id.renderButton(host, cfg);
        /* Only hide ours once Google has actually drawn something, so a silent
           failure cannot leave the visitor with no Google button at all. */
        if (host.firstChild) {
          a.style.display = 'none';
          a.setAttribute('data-breo-g', '1');
          matchSiblings(host);
          /* Tell the stylesheet that Google is drawing a button here, so our
             hover can match theirs — see .breo-sl-native. */
          if (row) { row.classList.add('breo-sl-native'); }
          mounted++;
        } else {
          host.parentNode.removeChild(host);
        }
      } catch (err) {
        if (host.parentNode) { host.parentNode.removeChild(host); }
      }
    });
    return mounted > 0;
  }

  /* Google decides how big its own button is, and we cannot override it. So
     rather than force Google's button to match ours and clip it, measure what it
     actually rendered and size OUR buttons to match — the row then lines up
     whatever size Google chose, on any future change to their styling. */
  function matchSiblings(host){
    var row = host.closest ? host.closest('.breo-sl') : null;
    if (!row) return;

    function apply(){
      var h = host.offsetHeight, w = host.offsetWidth;
      if (!h) { return false; }
      row.style.setProperty('--breo-sl-size', h + 'px');
      if (w) { row.style.setProperty('--breo-sl-gw', w + 'px'); }
      return true;
    }

    /* Google's button lays out asynchronously, so measuring straight after
       renderButton() returns 0 and nothing matches — which is exactly why the
       Google icon came out smaller than the Facebook one. Watch until it has
       actually taken up space, and keep watching for later reflows. */
    if (!apply()) {
      var tries = 0;
      var iv = setInterval(function(){
        if (apply() || ++tries > 40) { clearInterval(iv); }
      }, 100);
    }
    if (window.ResizeObserver) {
      try { new ResizeObserver(function(){ apply(); }).observe(host); } catch (err) {}
    }
  }

  /* The library is loaded async by the One Tap block, so it may not be there
     yet. Poll briefly, then give up and leave the plain links in place. */
  (function waitForGoogle(tries){
    if (mountGoogle()) return;
    if (tries <= 0) return;
    setTimeout(function(){ waitForGoogle(tries - 1); }, 200);
  })(30);

  function openWindow(a){
    var href = a.getAttribute('href');
    if (!href) return false;
    var url = href + (href.indexOf('?') === -1 ? '?' : '&') + 'popup=1';
    var w = 500, h = 650;
    var x = window.screenX + Math.max(0, (window.outerWidth  - w) / 2);
    var y = window.screenY + Math.max(0, (window.outerHeight - h) / 2);
    var opened;
    try {
      opened = window.open(url, 'breo_sl_login',
        'width=' + w + ',height=' + h + ',left=' + Math.round(x) + ',top=' + Math.round(y) +
        ',resizable=yes,scrollbars=yes');
    } catch (err) { opened = null; }
    if (!opened || opened.closed || typeof opened.focus !== 'function') { return false; }
    win = opened;
    try { win.focus(); } catch (err) {}
    if (poll) clearInterval(poll);
    poll = setInterval(function(){ if (!win || win.closed) { cleanup(); } }, 700);
    return true;
  }

  document.addEventListener('click', function(e){
    var a = e.target && e.target.closest ? e.target.closest('a.breo-sl-btn') : null;
    if (!a) return;

    var href = a.getAttribute('href');
    if (!href) return;

    /* Everything else: a window, opened synchronously inside the click or the
       browser treats it as unsolicited and blocks it. Blocked, or an in-app
       browser that ignores window.open, means we let the click through and the
       visitor gets the ordinary full-page redirect. */
    if (openWindow(a)) {
      e.preventDefault();
    }
  }, false);
})();
</script>
		<?php
	}
}
