<?php
/**
 * Plugin Name: AUN Smart Adjustments
 * Description: Premium showcase of a projector's automatic image corrections — Auto Focus, Auto Keystone,
 *              Screen Alignment, Obstacle Avoidance — as LARGE animated mini-demos with a single descriptive
 *              caption each. Compact & responsive (2x2 on mobile). No built-in title (add yours in the page
 *              builder). TOF Laser mode for instant laser focus/keystone. Reference: Settings → Smart Adjustments.
 *              Shortcode: [aun_smart_adjustments]   e.g. [aun_smart_adjustments tof="1" obstacle="0"]
 * Version:     1.3.1

 * Author:      Smart Living Bangladesh
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'aun_smart_adjustments', 'aun_sa_shortcode' );

/**
 * Feature catalogue. Each card = a big animated demo + ONE descriptive caption
 * (bold name + light benefit). $tof switches Focus/Keystone to TOF-laser variants.
 */
function aun_sa_features( $tof = false ) {
	return array(
		'focus' => array(
			'stage' => $tof
				? '<div class="d-screen d-focus d-focus-tof"><span class="d-laser"></span><i class="fa-solid fa-clapperboard"></i></div>'
				: '<div class="d-screen d-focus"><i class="fa-solid fa-clapperboard"></i></div>',
			'name'  => $tof ? 'TOF Laser Focus' : 'Auto Focus',
			'sub'   => $tof ? 'instant &amp; razor-sharp' : 'always razor-sharp',
			'tag'   => $tof ? 'Laser' : '',
		),
		'keystone' => array(
			'stage' => $tof
				? '<div class="d-screen d-keystone d-keystone-tof"></div>'
				: '<div class="d-screen d-keystone"></div>',
			'name'  => $tof ? 'TOF Auto Keystone' : 'Auto Keystone',
			'sub'   => $tof ? 'instant correction' : 'square at any angle',
			'tag'   => $tof ? 'Laser' : '',
		),
		'alignment' => array(
			'stage' => '<div class="d-wrap"><div class="d-frame"></div><div class="d-screen d-align"></div></div>',
			'name'  => 'Screen Alignment',
			'sub'   => 'fits your screen',
			'tag'   => '',
		),
		'obstacle' => array(
			'stage' => '<div class="d-wrap"><div class="d-screen d-obstacle"></div><div class="d-ob"></div></div>',
			'name'  => 'Obstacle Avoidance',
			'sub'   => 'skips walls &amp; objects',
			'tag'   => '',
		),
	);
}

function aun_sa_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'compact'   => '1',   // small footprint (default)
		'tof'       => '0',   // TOF laser model? relabels + instant snap + laser badge
		'focus'     => '1',
		'keystone'  => '1',
		'alignment' => '1',
		'obstacle'  => '1',
	), $atts, 'aun_smart_adjustments' );

	$is_on = function ( $v ) {
		return in_array( strtolower( (string) $v ), array( '1', 'true', 'yes', 'on' ), true );
	};

	$tof     = $is_on( $a['tof'] );
	$compact = $is_on( $a['compact'] );

	$cards = '';
	foreach ( aun_sa_features( $tof ) as $key => $f ) {
		if ( empty( $a[ $key ] ) || ! $is_on( $a[ $key ] ) ) continue;
		$tag = ( $f['tag'] !== '' )
			? '<span class="aun-sa-tag"><i class="fa-solid fa-bolt"></i> ' . esc_html( $f['tag'] ) . '</span>'
			: '';
		$sub = ( $f['sub'] !== '' ) ? ' <span class="sb">' . wp_kses_post( $f['sub'] ) . '</span>' : '';
		$cards .= '<article class="aun-sa-card">'
			. '<div class="aun-sa-stage">' . $tag . $f['stage'] . '</div>'
			. '<div class="aun-sa-label"><span class="nm">' . esc_html( $f['name'] ) . '</span>' . $sub . '</div>'
			. '</article>';
	}

	if ( $cards === '' ) return '';

	$cls = 'aun-sa' . ( $compact ? ' compact' : '' );

	ob_start();
	echo aun_sa_assets();
	// No built-in title — add your own heading in the page builder above the shortcode.
	?>
	<section class="<?php echo esc_attr( $cls ); ?>">
		<div class="aun-sa-grid"><?php echo $cards; ?></div>
	</section>
	<?php
	return ob_get_clean();
}

/* ── Admin reference: Settings → Smart Adjustments ────────────────────────── */
add_action( 'admin_menu', function () {
	add_options_page( 'Smart Adjustments', 'Smart Adjustments', 'manage_options', 'aun-smart-adjustments', 'aun_sa_help_page' );
} );

function aun_sa_help_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$ex = array(
		'TOF-laser model (recommended for premium models)' => '[aun_smart_adjustments tof="1"]',
		'Standard / camera-based model'                    => '[aun_smart_adjustments]',
		'Hide a feature this model lacks'                   => '[aun_smart_adjustments tof="1" obstacle="0"]',
		'Large showcase version (for a landing page)'      => '[aun_smart_adjustments tof="1" compact="0"]',
	);
	?>
	<div class="wrap">
		<h1>⚙️ Smart Adjustments — Shortcode Reference</h1>
		<p>Add this shortcode to a product description or any page/builder block. <strong>There is no built-in title</strong> — add your own heading in the page builder above the shortcode.</p>

		<h2>Shortcode</h2>
		<p><code style="font-size:15px;background:#f0f7ff;padding:6px 10px;border-radius:6px;display:inline-block">[aun_smart_adjustments]</code></p>

		<h2>Attributes</h2>
		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th>Attribute</th><th>Default</th><th>What it does</th></tr></thead>
			<tbody>
				<tr><td><code>tof</code></td><td><code>0</code></td><td><code>1</code> = TOF Laser model: relabels to “TOF Laser Focus / TOF Auto Keystone”, instant laser-snap animation, gold “Laser” badge.</td></tr>
				<tr><td><code>compact</code></td><td><code>1</code></td><td><code>1</code> = small footprint. <code>0</code> = large showcase (even bigger demos).</td></tr>
				<tr><td><code>focus</code></td><td><code>1</code></td><td><code>0</code> to hide Auto Focus.</td></tr>
				<tr><td><code>keystone</code></td><td><code>1</code></td><td><code>0</code> to hide Auto Keystone.</td></tr>
				<tr><td><code>alignment</code></td><td><code>1</code></td><td><code>0</code> to hide Screen Alignment.</td></tr>
				<tr><td><code>obstacle</code></td><td><code>1</code></td><td><code>0</code> to hide Obstacle Avoidance.</td></tr>
			</tbody>
		</table>

		<h2>Copy-paste examples</h2>
		<?php foreach ( $ex as $label => $code ) : ?>
			<p style="margin:14px 0 4px;font-weight:600;color:#1d2327"><?php echo esc_html( $label ); ?></p>
			<input type="text" readonly onclick="this.select()" value="<?php echo esc_attr( $code ); ?>"
			       style="width:100%;max-width:560px;font-family:monospace;font-size:13px;padding:8px 10px;border:1px solid #c3c4c7;border-radius:6px;background:#fff">
		<?php endforeach; ?>

		<p style="margin-top:20px;color:#646970"><strong>Tip:</strong> use <code>tof="1"</code> for every model with TOF laser auto-focus/keystone — it shows the “instant laser” animation and badge that set those models apart.</p>
	</div>
	<?php
}

/**
 * Stylesheet + script, once per request. Bare inline tags carry WP Rocket guards.
 */
function aun_sa_assets() {
	static $done = false;
	if ( $done ) return '';
	$done = true;

	$css = <<<'CSS'
.aun-sa{max-width:1180px;margin:0 auto;padding:0;font-family:inherit;box-sizing:border-box}
.aun-sa *{box-sizing:border-box}
.aun-sa-grid{display:flex;flex-wrap:wrap;justify-content:center;align-items:stretch;gap:18px}
.aun-sa-card{display:flex;flex-direction:column;flex:1 1 250px;max-width:285px;background:linear-gradient(180deg,#fff,#f5faff);border:1px solid #e3eefb;border-radius:18px;overflow:hidden;box-shadow:0 12px 30px rgba(12,42,74,.08);transition:transform .25s,box-shadow .25s,border-color .25s}
.aun-sa-card:hover{transform:translateY(-6px);box-shadow:0 22px 46px rgba(1,136,254,.18);border-color:#bcd9f8}
.aun-sa-stage{position:relative;height:168px;background:radial-gradient(130% 110% at 50% 0%,#1a3c66,#0a1f38);overflow:hidden;display:flex;align-items:center;justify-content:center}
.aun-sa .d-wrap{position:relative;width:128px;height:72px}
.aun-sa .d-screen{position:relative;width:128px;height:72px;border-radius:7px;background:linear-gradient(135deg,#5bb0ff,#0188fe);box-shadow:0 8px 22px rgba(1,136,254,.6);display:flex;align-items:center;justify-content:center;color:#fff;overflow:hidden}
.aun-sa-label{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;text-align:center;padding:13px 14px 16px;min-height:3em;line-height:1.3}
.aun-sa-label .nm{font-weight:800;color:#0c2a4a;font-size:15.5px}
.aun-sa-label .sb{font-weight:600;color:#7a93ad;font-size:13px}
.aun-sa-tag{position:absolute;top:9px;left:9px;z-index:4;font-weight:800;font-size:9.5px;letter-spacing:1px;text-transform:uppercase;color:#0c2a4a;background:linear-gradient(135deg,#ffe08a,#ffbc00);padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:3px;box-shadow:0 2px 6px rgba(0,0,0,.3)}
.aun-sa .d-focus{animation:aunSaFocus 3s ease-in-out infinite}
.aun-sa .d-focus i{font-size:26px;opacity:.95}
@keyframes aunSaFocus{0%,100%{filter:blur(4px);opacity:.72}50%{filter:blur(0);opacity:1}}
.aun-sa .d-keystone{animation:aunSaKey 3.8s ease-in-out infinite}
@keyframes aunSaKey{0%,100%{transform:perspective(260px) rotateX(16deg) rotateY(-16deg) scale(.9);opacity:.8}50%{transform:perspective(260px) rotateX(0) rotateY(0) scale(1);opacity:1}}
.aun-sa .d-frame{position:absolute;inset:0;border:2px dashed rgba(255,255,255,.55);border-radius:7px}
.aun-sa .d-align{position:absolute;inset:0;animation:aunSaAlign 3.4s ease-in-out infinite}
@keyframes aunSaAlign{0%{transform:translate(-18px,9px) scale(.82);opacity:.72}45%,62%{transform:translate(0,0) scale(1);opacity:1}100%{transform:translate(16px,-7px) scale(.85);opacity:.72}}
.aun-sa .d-obstacle{position:absolute;inset:0;animation:aunSaAvoid 4s ease-in-out infinite}
@keyframes aunSaAvoid{0%,100%{clip-path:polygon(0 0,100% 0,100% 100%,0 100%)}50%{clip-path:polygon(0 0,62% 0,62% 44%,100% 44%,100% 100%,0 100%)}}
.aun-sa .d-ob{position:absolute;top:-8px;right:-8px;width:26px;height:26px;border-radius:6px;background:#fbbf24;border:2px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,.4);z-index:3}
.aun-sa .d-ob::after{content:"\f1fc";font-family:"Font Awesome 6 Free";font-weight:900;font-size:11px;color:#7a5200;position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
.aun-sa .d-focus-tof{animation:aunSaFocusTof 3s ease-in-out infinite}
@keyframes aunSaFocusTof{0%{filter:blur(3px);opacity:.7}13%{filter:blur(0);opacity:1}100%{filter:blur(0);opacity:1}}
.aun-sa .d-laser{position:absolute;left:0;right:0;top:0;height:2px;z-index:2;background:linear-gradient(90deg,transparent,#ff5b5b,transparent);box-shadow:0 0 10px #ff4b4b;opacity:0;animation:aunSaLaser 3s ease-in-out infinite}
@keyframes aunSaLaser{0%{top:0;opacity:0}4%{opacity:1}13%{top:100%;opacity:0}100%{top:100%;opacity:0}}
.aun-sa .d-keystone-tof{animation:aunSaKeyTof 3s ease-in-out infinite}
@keyframes aunSaKeyTof{0%{transform:perspective(260px) rotateX(16deg) rotateY(-16deg) scale(.9);opacity:.85}16%{transform:perspective(260px) rotateX(0) rotateY(0) scale(1);opacity:1}100%{transform:perspective(260px) rotateX(0) rotateY(0) scale(1);opacity:1}}
/* COMPACT — small footprint but still a big, clearly-visible demo */
.aun-sa.compact .aun-sa-card{flex:1 1 220px;max-width:255px;border-radius:14px}
.aun-sa.compact .aun-sa-stage{height:130px}
.aun-sa.compact .d-screen,.aun-sa.compact .d-wrap{width:108px;height:61px}
.aun-sa.compact .d-focus i{font-size:22px}
.aun-sa.compact .d-ob{width:22px;height:22px;top:-6px;right:-6px}
.aun-sa.compact .d-ob::after{font-size:9px}
.aun-sa.compact .aun-sa-label{padding:11px 12px 14px;min-height:2.9em}
.aun-sa.compact .aun-sa-label .nm{font-size:14px}
.aun-sa.compact .aun-sa-label .sb{font-size:12px}
/* MOBILE — compact shows a clean 2-column grid */
@media(max-width:560px){
.aun-sa.compact .aun-sa-grid{gap:12px}
.aun-sa.compact .aun-sa-card{flex:1 1 calc(50% - 6px);max-width:none}
.aun-sa.compact .aun-sa-stage{height:120px}
.aun-sa.compact .d-screen,.aun-sa.compact .d-wrap{width:96px;height:54px}
}
@media(max-width:340px){
.aun-sa.compact .aun-sa-card{flex:1 1 100%}
}
/* entrance (progressive enhancement) */
.aun-sa.js .aun-sa-card{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease}
.aun-sa.js.in .aun-sa-card{opacity:1;transform:none}
.aun-sa.js .aun-sa-card:nth-child(2){transition-delay:.08s}
.aun-sa.js .aun-sa-card:nth-child(3){transition-delay:.16s}
.aun-sa.js .aun-sa-card:nth-child(4){transition-delay:.24s}
@media(prefers-reduced-motion:reduce){
.aun-sa .d-focus,.aun-sa .d-keystone,.aun-sa .d-align,.aun-sa .d-obstacle,.aun-sa .d-focus-tof,.aun-sa .d-keystone-tof,.aun-sa .d-laser{animation:none !important}
.aun-sa .d-keystone,.aun-sa .d-keystone-tof{transform:none !important}
.aun-sa.js .aun-sa-card{opacity:1 !important;transform:none !important;transition:none !important}
}
CSS;

	$js = <<<'JS'
(function(){
  function boot(){
    document.querySelectorAll('.aun-sa').forEach(function(sec){
      if(sec.__aunSa) return; sec.__aunSa = true;
      sec.classList.add('js');
      if('IntersectionObserver' in window){
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(e){ if(e.isIntersecting){ sec.classList.add('in'); io.unobserve(sec); } });
        }, { threshold: 0.2 });
        io.observe(sec);
      } else {
        sec.classList.add('in');
      }
    });
  }
  if(document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
})();
JS;

	return '<style id="aun-sa-css" data-no-optimize="1" data-no-minify="1">' . $css . '</style>'
		. '<script id="aun-sa-js" data-no-optimize="1" data-no-minify="1" data-cfasync="false">' . $js . '</script>';
}
