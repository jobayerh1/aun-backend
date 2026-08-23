<?php
/**
 * Mobile app install bar — modelled on how large platforms actually behave.
 *
 * SHAPE
 *  - iOS has a native Smart App Banner (<meta name="apple-itunes-app">). Android has
 *    NO equivalent for a native app — Chrome's prompt is PWA-only — so for a
 *    sideloaded APK this has to be custom. There is no built-in to use.
 *  - Slim and dismissible on purpose. Google treats intrusive mobile interstitials
 *    as a ranking negative, so this must never be full-screen or block content.
 *  - position:fixed, so it cannot cause layout shift (CLS is a Core Web Vital).
 *
 * FREQUENCY — the part that decides whether visitors resent it
 *  1. NEVER on the first page of a visit. Someone who lands and leaves is never
 *     interrupted; only people who are actually browsing ever see it.
 *  2. From the second page on: shown ONCE PER SESSION, and only after the visitor
 *     scrolls a quarter of the page or stays ~6 seconds — i.e. after engagement,
 *     not on arrival.
 *  3. Dismissed  → silent for 60 days.
 *  4. Tapped Get → silent for 180 days (they have seen the app page).
 *  5. Never on cart, checkout or account pages. Interrupting a purchase is the
 *     single worst thing this could do.
 *  6. Never on single product pages, where Flatsome's sticky "Add to cart" bar
 *     already owns the bottom edge. The impression is not spent, so the bar shows
 *     on the next page instead. Beyond the overlap, the principle is that a buy
 *     button always outranks an app install.
 *  7. More generally, if anything else is painted at the bottom-centre of the
 *     viewport (cookie notice, chat dock, One Tap), the bar waits its turn and
 *     then yields the page rather than covering it.
 *
 * ⚠️ CACHE SAFETY: the markup is ALWAYS rendered and hidden; JavaScript decides
 * whether to reveal it. Detecting Android server-side would be cached by WP Rocket
 * and then served to everyone, including desktop and iPhone visitors.
 *
 * Disable entirely:  add_filter( 'aun_app_banner_enabled', '__return_false' );
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aun_app_banner_render() {

	if ( ! apply_filters( 'aun_app_banner_enabled', (bool) aun_care_promo_opt( 'banner_enabled' ) ) ) return;
	if ( is_admin() ) return;

	// Never on the app's own pages, and never mid-purchase.
	$path = strtolower( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ) );
	$skip = array( 'aun-care-app', 'get-aun-care-app', 'cart', 'checkout', 'my-account' );
	foreach ( $skip as $s ) {
		if ( false !== strpos( $path, $s ) ) return;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) return;

	$icon = plugin_dir_url( __FILE__ ) . 'assets/app-icon.webp';
	?>
<div id="aun-appbar" class="aun-appbar" hidden>
  <img class="aun-appbar-ic" src="<?php echo esc_url( $icon ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
  <div class="aun-appbar-tx">
    <b>AUN Care</b>
    <span><?php esc_html_e( 'Warranty, parts &amp; repairs — free', 'aun-care-promo' ); ?></span>
  </div>
  <a class="aun-appbar-go" href="https://aun-projector.com.bd/aun-care-app/"><?php esc_html_e( 'Get', 'aun-care-promo' ); ?></a>
  <button class="aun-appbar-x" type="button" aria-label="<?php esc_attr_e( 'Dismiss', 'aun-care-promo' ); ?>">&times;</button>
</div>
<script data-no-optimize="1" data-no-minify="1">
(function(){
  var bar = document.getElementById('aun-appbar');
  if(!bar) return;

  // Settings -> AUN Care App. Numbers only, so nothing here can be injected.
  var CFG = <?php echo wp_json_encode( array(
      'minViews'    => (int) aun_care_promo_opt( 'min_views' ),
      'delay'       => (int) aun_care_promo_opt( 'delay' ),
      'scrollPct'   => (int) aun_care_promo_opt( 'scroll_pct' ),
      'muteDismiss' => (int) aun_care_promo_opt( 'mute_dismiss' ),
      'muteTap'     => (int) aun_care_promo_opt( 'mute_tap' ),
      'avoidOneTap' => (int) aun_care_promo_opt( 'avoid_onetap' ),
      'skipProduct' => (int) aun_care_promo_opt( 'skip_products' ),
      // Resolved on the server, which is safe: a product page has its own URL, so
      // WP Rocket caches this value with the page it belongs to.
      'isProduct'   => ( function_exists( 'is_product' ) && is_product() ) ? 1 : 0,
  ) ); ?>;

  // Google One Tap docks to the BOTTOM of the screen on phones — exactly where
  // this bar lives. Rather than fight over z-index, we simply wait our turn.
  function oneTapUp(){
    return !!document.querySelector(
      '#credential_picker_container,#credential_picker_iframe,' +
      'iframe[src*="gsi/iframe"],iframe[src*="accounts.google.com/gsi"]'
    );
  }

  // Is something else already pinned to the bottom edge of the screen?
  //
  // Deliberately theme-agnostic: rather than hunt for Flatsome's sticky
  // Add-to-cart markup by class name (which changes between theme versions and
  // would silently stop matching after an update), just ask the browser what is
  // actually painted at the bottom-centre of the viewport and check whether it,
  // or any of its parents, is position:fixed. Catches the sticky buy bar, cookie
  // notices, chat docks and anything else we haven't thought of.
  function bottomBusy(){
    try{
      var el = document.elementFromPoint(
        Math.round(window.innerWidth/2),
        window.innerHeight - 8
      );
      while(el && el !== document.body && el !== document.documentElement){
        if(el === bar) return false;                                  // that's us
        if(getComputedStyle(el).position === 'fixed') return true;
        el = el.parentElement;
      }
    }catch(e){}
    return false;
  }

  var MUTE='aunAppBarMute',      // localStorage: timestamp to stay quiet until
      SEEN='aunAppBarSeen',      // sessionStorage: already shown this visit
      VIEWS='aunAppBarViews',    // sessionStorage: pages viewed this visit
      DAY=86400000;

  function quiet(days){
    try{ localStorage.setItem(MUTE, String(Date.now()+days*DAY)); }catch(e){}
  }

  // Android phones only. Decided here, never on the server — a cached page would
  // otherwise show this to desktop and iPhone visitors too.
  if(!/Android/i.test(navigator.userAgent||'')) return;
  if(window.innerWidth>820) return;

  try{
    var until=parseInt(localStorage.getItem(MUTE)||'0',10);
    if(until && Date.now()<until) return;          // dismissed or already tapped
    if(sessionStorage.getItem(SEEN)) return;       // once per visit, not per page

    // Count this pageview. Never interrupt the first page of a visit.
    var views=(parseInt(sessionStorage.getItem(VIEWS)||'0',10)||0)+1;
    sessionStorage.setItem(VIEWS,String(views));
    if(views < CFG.minViews) return;
  }catch(e){ return; }

  // A product page is where the buy button lives, and Flatsome's sticky Add to
  // cart bar owns the bottom edge there. An app install never outranks a sale,
  // so we stay off entirely. Note this sits AFTER the pageview counter above:
  // the visit still counts and the impression is NOT marked as used, so the bar
  // simply appears on the next page the visitor opens. On a projector shop most
  // browsing is product pages, so skipping the count instead would mean the bar
  // effectively never showed at all.
  if(CFG.skipProduct && CFG.isProduct) return;

  var shown=false, waiting=false, tries=0;
  function show(){
    if(shown) return;
    // Never burn the one impression behind a sign-in prompt or under someone
    // else's fixed bar: wait a moment and look again. After a few tries give up
    // on THIS page with the impression still unspent, so the next page gets it.
    if((CFG.avoidOneTap && oneTapUp()) || bottomBusy()){
      if(tries >= 5){ cleanup(); return; }
      if(waiting) return;
      waiting=true; tries++;
      setTimeout(function(){ waiting=false; show(); }, 3000);
      return;
    }
    shown=true;
    cleanup();
    try{ sessionStorage.setItem(SEEN,'1'); }catch(e){}
    bar.hidden=false;
    // Force a reflow, then reveal synchronously. requestAnimationFrame is NOT
    // used here on purpose: it never fires while the tab is in the background,
    // which would mark the impression as spent without the visitor ever seeing
    // the bar. The reflow is what lets the CSS transition still animate.
    void bar.offsetHeight;
    bar.classList.add('is-in');
    document.body.style.paddingBottom=bar.offsetHeight+'px';
  }
  function hide(days){
    bar.classList.remove('is-in');
    document.body.style.paddingBottom='';
    setTimeout(function(){ bar.hidden=true; },220);
    quiet(days);
  }
  function onScroll(){
    var d=document.documentElement,
        max=(d.scrollHeight-d.clientHeight)||1;
    if((window.scrollY||d.scrollTop)/max > (CFG.scrollPct/100)) show();
  }
  function cleanup(){
    window.removeEventListener('scroll',onScroll);
    clearTimeout(timer);
  }

  // Engagement gate: a quarter of the way down, or ~6 seconds in.
  // The dwell timer only starts once the page is actually being looked at, so a
  // link opened in a background tab does not quietly burn the one impression.
  var timer;
  function arm(){
    if(timer) return;
    window.addEventListener('scroll',onScroll,{passive:true});
    timer=setTimeout(show, CFG.delay*1000);
  }
  if(document.visibilityState==='visible'){ arm(); }
  else{
    document.addEventListener('visibilitychange',function once(){
      if(document.visibilityState==='visible'){
        document.removeEventListener('visibilitychange',once);
        arm();
      }
    });
  }

  bar.querySelector('.aun-appbar-x').addEventListener('click',function(){ hide(CFG.muteDismiss); });
  bar.querySelector('.aun-appbar-go').addEventListener('click',function(){ quiet(CFG.muteTap); });
})();
</script>
	<?php
}
add_action( 'wp_footer', 'aun_app_banner_render', 20 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! apply_filters( 'aun_app_banner_enabled', (bool) aun_care_promo_opt( 'banner_enabled' ) ) ) return;
	wp_enqueue_style(
		'aun-app-banner',
		plugin_dir_url( __FILE__ ) . 'assets/banner.css',
		array(),
		AUN_CARE_PROMO_VER
	);
} );
