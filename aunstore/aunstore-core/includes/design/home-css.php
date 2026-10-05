<?php
/**
 * Styles for [aunstore_home]. Everything is scoped under .aunx and resets the theme's heading/list/button
 * styles inside it, so the page looks the same whatever Flatsome's typography settings are.
 * States toggled by home-js.php: .aunx-js (script is running), .is-in (scrolled into view), .is-active, .is-dim.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/ui-base.php';

function aunstore_home_css() {
	return aunstore_ui_base_css() . <<<'CSS'
/* ── hero: real projection photos ─────────────────────────────────── */
.aunx-hero{background:radial-gradient(900px 520px at 4% 108%,rgba(0,198,255,.14),transparent 60%),linear-gradient(180deg,#040814,#0a1224 72%,#0b1426);color:var(--txt);padding:clamp(120px,15vh,170px) 0 44px}
.aunx-hero .aunx-wrap{position:static}
.aunx-hero-in{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.86fr);gap:40px;align-items:center;min-height:min(60vh,540px)}
.aunx-hero-copy{position:relative;z-index:2}
.aunx-pill{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#cfe6ff}
.aunx-pill i{color:var(--sky)}
.aunx-hero h1{font-size:clamp(38px,4.7vw,62px);margin:22px 0 18px;color:#fff;letter-spacing:-.035em;line-height:1.03}
.aunx-hero h1 em{display:block;font-style:normal;background:linear-gradient(100deg,#4da8ff,#00e0ff 55%,#a9deff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
/* the last headline line changes word; with the script running it stays on one line and the script sizes it to fit */
.aunx-hero h1.is-fit em{white-space:nowrap}
.aunx-word{display:inline-block;transition:opacity .28s ease,transform .28s ease}
.aunx-word.is-out{opacity:0;transform:translateY(.18em)}
.aunx-hero-copy>p{font-size:clamp(16px,1.45vw,19px);color:#b3c4db;max-width:520px;line-height:1.65}
.aunx-cta{display:flex;flex-wrap:wrap;gap:12px;margin:30px 0 28px}
.aunx-hero-trust{display:flex;flex-wrap:wrap;gap:10px 22px;font-size:14px;color:#9db1cb}
.aunx-hero-trust li{display:flex;align-items:center;gap:8px}
.aunx-hero-trust i{color:var(--sky)}
/* the photo fills the right of the banner and fades into the dark behind the headline */
.aunx-hero-visual{align-self:stretch;display:flex;align-items:flex-end;justify-content:flex-end}
.aunx-hero-media{position:absolute;top:0;bottom:0;right:0;left:33%;z-index:0;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 36%);mask-image:linear-gradient(90deg,transparent 0,#000 36%)}
.aunx-hero-media::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(4,8,20,.74) 0,rgba(4,8,20,0) 24%,rgba(4,8,20,0) 60%,#0b1426 100%);pointer-events:none}
.aunx .aunx-hero-shot{position:absolute;inset:0;width:100%;height:100%;max-width:none;object-fit:cover;object-position:56% 50%;opacity:0;transform:scale(1.07);transition:opacity 1s ease,transform 7s ease-out}
.aunx .aunx-hero-shot.is-active{opacity:1;transform:none}
/* the control card: scene switch + the projector that took the photo */
.aunx-hcard{position:relative;z-index:2;width:100%;max-width:430px;padding:8px;border-radius:24px;background:rgba(8,14,28,.72);border:1px solid rgba(255,255,255,.15);-webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px);box-shadow:0 30px 70px rgba(0,0,0,.5)}
.aunx-hero-tabs{display:grid;grid-template-columns:repeat(var(--n,3),minmax(0,1fr));gap:4px;padding:4px;border-radius:17px;background:rgba(255,255,255,.07)}
.aunx .aunx-hero-tab{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 6px;border-radius:13px;color:#c3d3e8;font-size:14px;font-weight:700;line-height:1;transition:background .2s ease,color .2s ease}
.aunx .aunx-hero-tab:hover{color:#fff}
.aunx .aunx-hero-tab.is-active{background:#fff;color:#0b1426}
.aunx-hero-tab i{font-size:13px}
.aunx-hero-prog{display:block;height:3px;margin:9px 8px 0;border-radius:3px;background:rgba(255,255,255,.14);overflow:hidden}
.aunx-hero-prog i{display:block;height:100%;width:0;border-radius:3px;background:linear-gradient(90deg,var(--bl),var(--cy))}
.aunx-hero.is-playing .aunx-hero-prog i{animation:aunxBar var(--dur,6s) linear forwards}
.aunx-hshots{display:grid}
.aunx .aunx-hshot{grid-area:1/1;display:grid;grid-template-columns:104px minmax(0,1fr) auto;gap:14px;align-items:center;padding:12px 12px 8px 10px;border-radius:16px;color:inherit;opacity:0;visibility:hidden;transform:translateY(8px);transition:opacity .35s ease,transform .4s ease,visibility 0s linear .35s}
.aunx .aunx-hshot.is-active{opacity:1;visibility:visible;transform:none;transition:opacity .45s ease .12s,transform .5s ease .12s,visibility 0s}
.aunx-hshot.no-model{grid-template-columns:minmax(0,1fr)}
.aunx-hshot-img{width:104px;filter:drop-shadow(0 12px 14px rgba(0,0,0,.55))}
.aunx-hshot-b{display:block;min-width:0}
.aunx-hshot small{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#8fb8e6}
.aunx-hshot b{display:block;font-size:24px;font-weight:800;color:#fff;letter-spacing:-.02em;line-height:1.15;margin:3px 0 8px}
.aunx-hshot-sp{display:flex;flex-wrap:wrap;gap:6px}
.aunx-hshot-sp span{font-size:12px;font-weight:700;color:#d6e4f5;padding:4px 9px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);white-space:nowrap}
.aunx-hshot-go{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);color:#fff;font-size:14px;transition:background .2s ease,transform .2s ease}
.aunx a.aunx-hshot:hover .aunx-hshot-go{background:var(--bl);border-color:var(--bl);transform:translateX(3px)}
.aunx-hshot-go .screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
@keyframes aunxFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}

/* ── marquee ──────────────────────────────────────────────────────── */
.aunx-marq{background:#0b1426;border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:16px 0}
.aunx-marq-track{display:flex;width:max-content;animation:aunxMarq 38s linear infinite}
.aunx-marq:hover .aunx-marq-track{animation-play-state:paused}
.aunx-marq-item{display:inline-flex;align-items:center;gap:10px;padding:0 28px;color:#c3d3e8;font-size:14px;font-weight:600;white-space:nowrap}
.aunx-marq-item i{color:var(--sky);font-size:14px}
@keyframes aunxMarq{to{transform:translateX(-50%)}}

/* ── lineup explorer ──────────────────────────────────────────────── */
.aunx-lineup{background:linear-gradient(180deg,#fff,var(--soft));padding:90px 0 84px}
.aunx-note{max-width:760px;margin:0 auto 26px!important;padding:12px 16px;border-radius:14px;background:#fff8e6;border:1px dashed #f0b429;color:#7a4b00;font-size:14px;text-align:center}
.aunx-filters{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:0 0 30px}
.aunx .aunx-filter{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;border:1px solid var(--rule);background:#fff;color:var(--slate);font-size:14px;font-weight:700;line-height:1;transition:all .2s ease}
.aunx .aunx-filter:hover{border-color:#b9d6f7;color:var(--ink)}
.aunx .aunx-filter.is-active{background:var(--ink);border-color:var(--ink);color:#fff}
.aunx .aunx-filter i{font-size:13px;opacity:.85}
.aunx-exp{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:0;background:#fff;border:1px solid var(--rule);border-radius:30px;overflow:hidden;box-shadow:0 30px 70px rgba(15,23,42,.08)}
.aunx-exp-stage{position:relative;min-height:440px;background:radial-gradient(circle at 50% 56%,#1d4a86 0,#10264a 38%,#0a1428 74%);overflow:hidden}
.aunx-exp-spot{position:absolute;left:50%;top:54%;width:74%;aspect-ratio:1;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(circle,rgba(150,205,255,.5),rgba(80,160,255,.12) 55%,transparent 70%)}
.aunx-exp-ring{position:absolute;left:50%;top:54%;width:84%;aspect-ratio:1;transform:translate(-50%,-50%);border-radius:50%;border:1px dashed rgba(255,255,255,.2);animation:aunxSpin 60s linear infinite}
.aunx-exp-floor{display:none}
/* the turntable: one studio box per model (4:3, the device stands on the same floor line in every view) */
.aunx-exp-img{position:absolute;left:50%;top:50%;width:86%;aspect-ratio:4/3;transform:translate(-50%,-46%) scale(.92);opacity:0;transition:opacity .5s ease,transform .7s cubic-bezier(.2,.8,.2,1);perspective:1600px;pointer-events:none}
.aunx-exp-img.is-active{opacity:1;transform:translate(-50%,-50%) scale(1)}
.aunx-exp-img::before{content:"";position:absolute;left:14%;right:14%;top:79%;height:10%;border-radius:50%;background:radial-gradient(ellipse at center,rgba(0,0,0,.62),rgba(0,0,0,.25) 45%,transparent 72%);filter:blur(8px)}
/* a turn = the old side swings away and fades first, then the new angle swings in and settles (little overlap, so no double image) */
.aunx .aunx-view{position:absolute;inset:0;width:100%;height:100%;max-width:none;object-fit:contain;opacity:0;transform-origin:50% 84%;transform:rotateY(-30deg) translateX(9%) scale(.95);transition:opacity .55s cubic-bezier(.2,.8,.2,1) .28s,transform .95s cubic-bezier(.2,.8,.2,1) .28s;will-change:transform,opacity;backface-visibility:hidden}
.aunx .aunx-view.is-on{opacity:1;transform:none}
.aunx .aunx-view.is-out{transform:rotateY(30deg) translateX(-9%) scale(.95);transition:opacity .38s cubic-bezier(.5,0,.75,0),transform .55s cubic-bezier(.5,0,.75,0)}
.aunx .is-back .aunx-view{transform:rotateY(30deg) translateX(-9%) scale(.95)}
.aunx .is-back .aunx-view.is-on{transform:none}
.aunx .is-back .aunx-view.is-out{transform:rotateY(-30deg) translateX(9%) scale(.95)}
.aunx-exp-stage.is-turning .aunx-exp-spot{transform:translate(-50%,-50%) scale(1.06);opacity:.85}
.aunx-exp-spot{transition:transform 1.2s ease,opacity 1.2s ease}
.aunx-exp-stage{touch-action:pan-y;cursor:grab}
@keyframes aunxSpin{to{transform:translate(-50%,-50%) rotate(360deg)}}
.aunx-exp-info{position:relative;display:grid}
.aunx-panel{grid-area:1/1;padding:44px 44px 40px;display:flex;flex-direction:column;opacity:0;visibility:hidden;transform:translateY(14px);transition:opacity .4s ease,transform .5s ease,visibility 0s linear .4s}
.aunx-panel.is-active{opacity:1;visibility:visible;transform:none;transition:opacity .45s ease .1s,transform .5s ease .1s,visibility 0s}
.aunx-panel-k{font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--bl)}
.aunx-panel-k i{font-style:normal;color:#a9b8cc}
.aunx-panel h3{font-size:clamp(34px,4vw,50px);margin:10px 0 12px;letter-spacing:-.035em}
.aunx-best{display:inline-flex;align-items:flex-start;gap:8px;font-size:15px;color:#0166c8;font-weight:700;line-height:1.4}
.aunx-best i{margin-top:3px}
.aunx-specs{display:grid!important;grid-template-columns:1fr 1fr;gap:12px;margin:26px 0 28px!important}
.aunx-specs li{background:var(--soft);border:1px solid var(--rule);border-radius:16px;padding:16px 16px 14px;display:grid;grid-template-columns:auto 1fr;column-gap:10px;align-items:center}
.aunx-specs i{grid-row:span 2;width:38px;height:38px;border-radius:12px;background:rgba(1,136,254,.1);color:var(--bl);display:flex;align-items:center;justify-content:center;font-size:15px}
.aunx-specs b{font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.2;font-variant-numeric:tabular-nums}
.aunx-specs span{font-size:12.5px;color:var(--slate);line-height:1.3}
.aunx-panel-foot{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.aunx-price{font-size:26px;font-weight:800;letter-spacing:-.02em;color:var(--ink)}
.aunx-price del{font-size:16px;font-weight:600;color:#9aa8bb;margin-right:6px}
.aunx-price ins{text-decoration:none}
.aunx-rail{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-top:20px}
.aunx .aunx-tab{position:relative;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:16px;background:#fff;border:1px solid var(--rule);overflow:hidden;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease,opacity .2s ease;min-width:0}
.aunx .aunx-tab:hover{border-color:#b9d6f7;transform:translateY(-2px)}
.aunx .aunx-tab.is-active{border-color:var(--bl);box-shadow:0 10px 26px rgba(1,136,254,.16)}
.aunx .aunx-tab.is-dim{opacity:.32}
.aunx-tab img{width:44px;height:44px;object-fit:contain;flex-shrink:0;background:radial-gradient(circle,#1d4a86,#0a1428);border-radius:12px;padding:4px}
.aunx-tab>span{min-width:0;display:block}
.aunx-tab b{display:block;font-size:14px;font-weight:800;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.aunx-tab small{display:block;font-size:11.5px;color:var(--slate);white-space:nowrap}
.aunx-tab-bar{position:absolute;left:0;bottom:0;height:3px;width:0;background:linear-gradient(90deg,var(--bl),var(--cy))}
.aunx-tab.is-active .aunx-tab-bar{width:100%}
.aunx-tab.is-active.is-playing .aunx-tab-bar{animation:aunxBar var(--dur,6s) linear forwards}
@keyframes aunxBar{from{width:0}to{width:100%}}
.aunx-lineup-all{text-align:center;margin-top:28px!important}
.aunx-lineup-all a{font-weight:700;color:var(--bl);font-size:15px}
.aunx-lineup-all a i{font-size:12px;margin-left:4px;transition:transform .2s ease}
.aunx-lineup-all a:hover i{transform:translateX(3px)}

/* ── smart-feature bento ──────────────────────────────────────────── */
.aunx-feats{background:radial-gradient(900px 520px at 88% 0,rgba(1,136,254,.2),transparent 60%),linear-gradient(180deg,#0b1426,#060b16);color:var(--txt);padding:92px 0}
.aunx-feats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:330px;gap:16px}
.aunx .aunx-feat{position:relative;display:flex;flex-direction:column;justify-content:flex-end;border-radius:24px;overflow:hidden;isolation:isolate;background:#0d1629;border:1px solid var(--line);color:#fff;transition:border-color .3s ease,box-shadow .3s ease}
.aunx .aunx-feat-wide{grid-column:span 2}
.aunx .aunx-feat-img{position:absolute;inset:0;z-index:-2;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover;transition:transform 1.1s cubic-bezier(.2,.8,.2,1)}
.aunx-feat::after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(4,8,18,0) 38%,rgba(4,8,18,.72) 68%,rgba(4,8,18,.94));transition:background .4s ease}
.aunx-feat-b{display:block;padding:24px 26px 24px}
.aunx-feat-tag{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border-radius:999px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.2);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);font-size:11.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#e3efff}
.aunx-feat-tag i{color:var(--sky);font-size:12px}
.aunx-feat h3{font-size:clamp(20px,1.8vw,26px);font-weight:800;letter-spacing:-.02em;line-height:1.2;color:#fff;margin:12px 0 0}
.aunx-feat-wide h3{font-size:clamp(24px,2.4vw,32px)}
.aunx-feat-more{display:block}
.aunx-feat-p{display:block;margin-top:8px;font-size:14.5px;line-height:1.55;color:#c3d3e8;max-width:470px}
.aunx-feat-go{display:inline-flex;align-items:center;gap:7px;margin-top:12px;font-size:13.5px;font-weight:700;color:var(--sky)}
.aunx-feat-go i{font-size:11px;transition:transform .2s ease}
a.aunx-feat:hover{border-color:rgba(77,168,255,.5);box-shadow:0 26px 60px rgba(0,0,0,.45)}
.aunx-feat:hover .aunx-feat-img{transform:scale(1.06)}
a.aunx-feat:hover .aunx-feat-go i{transform:translateX(3px)}
/* with a mouse, each tile is a picture and a headline; the detail slides up on hover or keyboard focus */
@media (hover:hover) and (min-width:850px){
	.aunx-feat-more{display:grid;grid-template-rows:0fr;opacity:0;transition:grid-template-rows .45s cubic-bezier(.2,.8,.2,1),opacity .3s ease}
	.aunx-feat-more>*{overflow:hidden}
	.aunx-feat-more{overflow:hidden}
	.aunx-feat:hover .aunx-feat-more,.aunx-feat:focus-visible .aunx-feat-more,.aunx-feat:focus-within .aunx-feat-more{grid-template-rows:1fr;opacity:1}
	.aunx-feat:hover::after{background:linear-gradient(180deg,rgba(4,8,18,.05) 20%,rgba(4,8,18,.8) 58%,rgba(4,8,18,.96))}
	.aunx-feat-more .aunx-feat-p{margin-top:8px}
}

/* ── videos ([aun_shorts] inside) ─────────────────────────────────── */
.aunx-vids{background:var(--soft);padding:92px 0}
/* the row runs edge to edge: it breaks out of the 1200px column, starts in line with the content and scrolls to the screen edge */
.aunx-vids-rail{width:100vw;position:relative;left:50%;margin-left:-50vw}
.aunx-vids .aun-shorts-wrapper{margin:0!important;width:100%!important;max-width:none!important}
/* like the BD site: five tall cards fill the screen edge to edge (three on tablets, one-and-a-bit on phones) */
.aunx-vids{--vgap:24px;--vcols:5}
/* keep the plugin's own thin scrollbar (it shows only when the videos don't fit); thumb edge matches this section's background */
.aunx-vids .aun-shorts-scroll-container{gap:var(--vgap)!important;padding:6px 8px 20px!important;scroll-padding-left:8px}
.aunx-vids .aun-shorts-scroll-container::-webkit-scrollbar-thumb{border-color:var(--soft)}
.aunx-vids .aun-short-card{width:calc((100vw - 16px - (var(--vcols) - 1) * var(--vgap)) / var(--vcols))!important;height:auto!important;min-height:0!important;aspect-ratio:9/16;scroll-snap-align:start;border-radius:18px!important}
.aunx-vids .aun-swipe-hint{display:none!important}
@media (max-width:1024px){.aunx-vids{--vcols:3;--vgap:16px}}
@media (max-width:549px){.aunx-vids{--vcols:1.35;--vgap:12px}.aunx-vids .aun-shorts-scroll-container{padding-left:18px!important;scroll-padding-left:18px}}
.aunx-vids .aun-short-card{box-shadow:0 18px 40px rgba(8,20,45,.18)}

/* ── customer reviews ─────────────────────────────────────────────── */
.aunx-revs{background:var(--soft);padding:92px 0}
.aunx-rev-score{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px;font-size:15px;color:var(--slate)}
.aunx-rev-score b{font-size:18px;color:var(--ink)}
.aunx-stars{display:inline-flex;gap:2px;color:#f5a623;font-size:14px}
.aunx-revs-grid{columns:3 300px;column-gap:20px}
.aunx-rev{break-inside:avoid;display:flex;flex-direction:column;gap:14px;margin:0 0 20px;padding:24px;border-radius:22px;background:#fff;border:1px solid rgba(15,35,68,.08);box-shadow:0 12px 30px rgba(8,20,45,.06)}
.aunx-rev blockquote{margin:0;padding:0;border:0;font-style:normal}
.aunx-rev blockquote p{margin:0;font-size:15.5px;line-height:1.65;color:#1f2d45;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical;overflow:hidden}
.aunx-rev figcaption{display:flex;align-items:center;gap:12px;padding-top:14px;border-top:1px solid rgba(15,35,68,.07)}
.aunx-rev-av{flex:0 0 40px;height:40px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--bl),var(--cy));color:#fff;font-weight:800;font-size:16px}
.aunx-rev-who{flex:1;min-width:0;display:flex;flex-direction:column;line-height:1.3}
.aunx-rev-who b{font-size:14.5px;color:#0b1426}
.aunx-rev-who b i{color:var(--bl);font-size:12px}
.aunx-rev-who small{font-size:12.5px;color:#6b7a90}
.aunx .aunx-rev-prod{flex:0 0 auto;font-size:12px;font-weight:700;color:var(--bl);padding:5px 10px;border-radius:999px;background:rgba(1,136,254,.08);white-space:nowrap}
.aunx-rev-note{display:flex;gap:12px;align-items:flex-start;padding:18px 20px;border-radius:16px;border:2px dashed rgba(1,136,254,.35);background:#fff;color:#334155;font-size:14.5px;line-height:1.55}
.aunx-rev-note i{color:var(--bl);margin-top:3px}

/* ── shop by space ────────────────────────────────────────────────── */
.aunx-rooms{background:#fff;padding:84px 0}
.aunx-rooms-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.aunx-room{position:relative;display:flex;flex-direction:column;justify-content:flex-end;min-height:440px;padding:26px;border-radius:26px;overflow:hidden;isolation:isolate;background:radial-gradient(circle at 70% 78%,#1b4378 0,#0f2344 40%,#0a1428 78%);color:#fff!important;transition:transform .35s ease,box-shadow .35s ease}
.aunx-room:hover{transform:translateY(-6px);box-shadow:0 30px 60px rgba(8,20,45,.35)}
.aunx-room .aunx-room-art{position:absolute;inset:0;z-index:-2;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover;transition:transform 1s cubic-bezier(.2,.8,.2,1)}
.aunx-room::after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(6,11,22,0) 50%,rgba(6,11,22,.82) 70%,rgba(6,11,22,.97))}
.aunx-room:hover .aunx-room-art{transform:scale(1.05)}
.aunx-il-beam{animation:aunxBeam 4.5s ease-in-out infinite}
.aunx-il-sun{animation:aunxSun 9s ease-in-out infinite alternate}
.aunx-il-star{animation:aunxStar 3.2s ease-in-out infinite;animation-delay:calc(var(--i,0)*-.47s)}
.aunx-il-bar{transform-box:fill-box;transform-origin:50% 100%;transition:transform .9s cubic-bezier(.2,.8,.2,1);transition-delay:calc(.25s + var(--i,0)*.09s)}
.aunx-js .aunx-room:not(.is-in) .aunx-il-bar{transform:scaleY(.12)}
@keyframes aunxBeam{0%,100%{opacity:.78}50%{opacity:1}}
@keyframes aunxSun{from{transform:translateY(10px)}to{transform:translateY(-8px)}}
@keyframes aunxStar{0%,100%{opacity:.35}50%{opacity:1}}
.aunx-room h3{font-size:28px;margin:0 0 8px;color:#fff}
.aunx-room p{color:#c8d6e8;font-size:15px;max-width:290px;margin-bottom:18px}
.aunx-room-go{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:14px;color:#fff;padding:10px 16px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.24);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);transition:background .2s ease,border-color .2s ease}
.aunx-room:hover .aunx-room-go{background:var(--bl);border-color:var(--bl)}
.aunx-room-go i{font-size:12px;transition:transform .2s ease}
.aunx-room:hover .aunx-room-go i{transform:translateX(3px)}

/* ── why AUN ──────────────────────────────────────────────────────── */
.aunx-why{background:radial-gradient(900px 500px at 50% -10%,rgba(1,136,254,.22),transparent 60%),linear-gradient(180deg,#070c18,#0b1426);color:var(--txt);padding:92px 0}
.aunx-why-bg{position:absolute;inset:0;background-size:cover;background-position:center 40%}
.aunx-why-bg::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,#070c18 0,rgba(7,12,24,.8) 26%,rgba(7,12,24,.84) 72%,#0b1426 100%)}
.aunx-stats{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--line);border-radius:22px;background:rgba(10,18,36,.6);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);margin:0 0 22px!important;overflow:hidden}
.aunx-stats li{padding:28px 22px;text-align:center;border-left:1px solid var(--line)}
.aunx-stats li:first-child{border-left:0}
.aunx-stats b{display:block;font-size:clamp(28px,3.4vw,42px);font-weight:800;letter-spacing:-.03em;line-height:1;background:linear-gradient(100deg,#fff,#9fd2ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;font-variant-numeric:tabular-nums}
.aunx-stats span{display:block;margin-top:10px;font-size:13px;color:var(--mut);line-height:1.4}
.aunx-why-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
.aunx-glass{padding:28px;border-radius:22px;background:linear-gradient(180deg,rgba(16,27,49,.72),rgba(10,18,36,.6));-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border:1px solid var(--line);transition:transform .3s ease,border-color .3s ease}
.aunx-glass:hover{transform:translateY(-5px);border-color:rgba(77,168,255,.45)}
.aunx-glass-ico{width:50px;height:50px;border-radius:15px;background:linear-gradient(135deg,rgba(1,136,254,.4),rgba(0,198,255,.18));color:#cfe9ff;display:flex;align-items:center;justify-content:center;font-size:20px}
.aunx-glass h3{font-size:19px;margin:18px 0 8px;color:#fff;letter-spacing:-.01em}
.aunx-glass p{color:#a3b6cf;font-size:15px}
.aunx-glass a{color:var(--sky);font-weight:700;white-space:nowrap}
.aunx-glass a i{font-size:11px}

/* ── warranty walkthrough ─────────────────────────────────────────── */
.aunx-care{background:var(--soft);padding:92px 0}
.aunx-care-in{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:56px;align-items:center}
.aunx-faqs-copy h2{font-size:clamp(28px,3.6vw,42px);margin:12px 0 16px}
.aunx-faqs-copy p{color:var(--slate);font-size:17px;margin-bottom:26px}
.aunx-chat{width:100%;max-width:400px;margin:0 auto;border-radius:30px;background:#fff;border:1px solid var(--rule);box-shadow:0 34px 80px rgba(15,23,42,.13);overflow:hidden}
.aunx-chat-top{display:flex;align-items:center;gap:12px;padding:16px 18px;background:linear-gradient(135deg,#0166c8,#0188fe 60%,#00c6ff);color:#fff}
.aunx-chat-av{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.aunx-chat-top b{display:block;font-size:15px;line-height:1.25;color:#fff}
.aunx-chat-top small{display:flex;align-items:center;gap:6px;font-size:12px;opacity:.92}
.aunx-chat-top small i{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block}
.aunx-chat-body{padding:18px;display:flex;flex-direction:column;gap:12px;background:var(--soft);min-height:352px}
.aunx-msg{align-self:flex-start;max-width:88%;padding:12px 14px;border-radius:18px 18px 18px 6px;background:#fff;border:1px solid var(--rule);font-size:14px;color:var(--ink);line-height:1.45;transition:opacity .45s ease,transform .45s cubic-bezier(.2,.8,.2,1)}
.aunx-js .aunx-msg{opacity:0;transform:translateY(12px) scale(.97)}
.aunx-js .aunx-msg.is-on{opacity:1;transform:none}
.aunx-msg-me{align-self:flex-end;background:linear-gradient(135deg,var(--bl),var(--cy));border:0;color:#fff;border-radius:18px 18px 6px 18px}
.aunx-msg-vid{display:flex;align-items:center;justify-content:center;position:relative;height:92px;border-radius:12px;background:linear-gradient(135deg,#0b1426,#1d4a86);margin-bottom:8px;color:#fff;font-size:18px}
.aunx-msg-vid em{position:absolute;right:8px;bottom:6px;font-style:normal;font-size:11px;background:rgba(0,0,0,.5);padding:1px 6px;border-radius:6px}
.aunx-msg-row{display:flex;gap:10px;align-items:flex-start}
.aunx-msg-row>i{width:34px;height:34px;border-radius:10px;background:rgba(1,136,254,.1);color:var(--bl);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.aunx-msg-row>span{color:var(--slate);font-size:13.5px}
.aunx-msg-row b{display:block;font-size:14px;color:var(--ink)}
.aunx-steps{display:grid;gap:12px}
.aunx-steps .aunx-btn{justify-self:start;margin-top:10px}
.aunx .aunx-step{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center;padding:18px 20px;border-radius:20px;background:#fff;border:1px solid var(--rule);transition:border-color .25s ease,box-shadow .25s ease,background .25s ease}
.aunx .aunx-step:hover{border-color:#b9d6f7}
.aunx-step-n{width:40px;height:40px;border-radius:13px;background:var(--soft);border:1px solid var(--rule);color:var(--slate);font-weight:800;display:flex;align-items:center;justify-content:center;transition:all .25s ease}
.aunx-step-b{display:block;min-width:0}
.aunx-step-b b{display:block;font-size:17px;font-weight:800;color:var(--ink);letter-spacing:-.01em}
.aunx-step-t{display:block;max-height:0;overflow:hidden;opacity:0;font-size:14.5px;color:var(--slate);line-height:1.55;transition:max-height .4s ease,opacity .3s ease,margin .3s ease}
.aunx-step-ico{font-size:18px;color:#c3cfde;transition:color .25s ease,transform .25s ease}
.aunx .aunx-step.is-active{border-color:var(--bl);box-shadow:0 16px 36px rgba(1,136,254,.13)}
.aunx-step.is-active .aunx-step-n{background:linear-gradient(135deg,var(--bl),var(--cy));border-color:transparent;color:#fff}
.aunx-step.is-active .aunx-step-t{max-height:90px;opacity:1;margin-top:4px}
.aunx-step.is-active .aunx-step-ico{color:var(--bl);transform:scale(1.15)}

/* ── finder band ──────────────────────────────────────────────────── */
.aunx-finder{background:linear-gradient(120deg,#0166c8,#0188fe 45%,#00c6ff);color:#fff;padding:56px 0}
.aunx-finder::before,.aunx-finder::after{content:"";position:absolute;border-radius:50%;border:1px solid rgba(255,255,255,.22)}
.aunx-finder::before{width:420px;height:420px;right:-120px;top:-210px}
.aunx-finder::after{width:260px;height:260px;right:-40px;top:-130px}
.aunx-finder-in{display:grid;grid-template-columns:auto 1fr auto;gap:26px;align-items:center}
.aunx-finder-ico{width:74px;height:74px;border-radius:22px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:28px;animation:aunxFloat 5s ease-in-out infinite}
.aunx-finder h2{font-size:clamp(24px,3vw,34px);color:#fff}
.aunx-finder p{color:#e3f2ff;font-size:16px;margin-top:8px;max-width:620px}

/* ── FAQ ──────────────────────────────────────────────────────────── */
.aunx-faqs{background:#fff;padding:92px 0}
.aunx-faqs-in{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:56px;align-items:start}
.aunx-faq details{border-bottom:1px solid var(--rule)}
.aunx-faq details:first-child{border-top:1px solid var(--rule)}
.aunx-faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:22px 4px;font-size:18px;font-weight:700;color:var(--ink);letter-spacing:-.01em}
.aunx-faq summary::-webkit-details-marker{display:none}
.aunx-faq summary i{width:32px;height:32px;border-radius:50%;background:var(--soft);color:var(--bl);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;transition:transform .3s ease,background .3s ease,color .3s ease}
.aunx-faq details[open] summary i{transform:rotate(45deg);background:var(--bl);color:#fff}
.aunx-faq summary:hover{color:var(--bl)}
.aunx-faq-a{padding:0 50px 24px 4px;color:var(--slate);font-size:16px;line-height:1.7}
.aunx-faq-a a{color:var(--bl);font-weight:600}

/* ── responsive ───────────────────────────────────────────────────── */
@media (max-width:1024px){
	.aunx-rail{grid-template-columns:repeat(3,minmax(0,1fr))}
	.aunx-panel{padding:34px 30px 32px}
}
@media (max-width:849px){
	.aunx-hero{padding:112px 0 36px}
	.aunx-hero-in{grid-template-columns:1fr;gap:28px;min-height:0}
	.aunx-hero-copy{text-align:center}
	.aunx-hero-copy>p{margin:0 auto}
	.aunx-cta,.aunx-hero-trust{justify-content:center}
	/* phones and tablets: the photo sits under the headline, edge to edge, with the card overlapping its bottom */
	.aunx-hero-visual{display:block}
	.aunx-hero-media{position:relative;left:auto;aspect-ratio:3/2;margin:0 -24px;-webkit-mask-image:none;mask-image:none}
	.aunx-hero-media::after{background:linear-gradient(180deg,#0a1224 0,rgba(4,8,20,0) 16%,rgba(4,8,20,0) 56%,#0b1426 100%)}
	.aunx .aunx-hero-shot{object-position:50% 50%}
	.aunx-hcard{max-width:520px;margin:-64px auto 0}
	.aunx-exp{grid-template-columns:1fr;border-radius:24px}
	.aunx-exp-stage{min-height:0;aspect-ratio:1/.72}
	.aunx-exp-img{width:88%}
	.aunx-rooms-grid,.aunx-why-grid{grid-template-columns:1fr}
	.aunx-feats-grid{grid-template-columns:1fr 1fr;grid-auto-rows:300px}
	.aunx-room{min-height:430px}
	.aunx-stats{grid-template-columns:1fr 1fr}
	.aunx-stats li:nth-child(3){border-left:0}
	.aunx-stats li:nth-child(n+3){border-top:1px solid var(--line)}
	.aunx-care-in,.aunx-faqs-in{grid-template-columns:1fr;gap:34px}
	.aunx-steps .aunx-btn{justify-self:stretch}
	.aunx-chat-body{min-height:280px}
	.aunx-finder-in{grid-template-columns:1fr;text-align:center;justify-items:center}
	.aunx-lineup,.aunx-rooms,.aunx-why,.aunx-care,.aunx-faqs,.aunx-feats,.aunx-vids,.aunx-revs{padding:64px 0}
}
@media (max-width:549px){
	.aunx-wrap{padding:0 18px}
	/* phones: reviews become a swipe rail too */
	.aunx-revs-grid{columns:auto;display:flex;align-items:flex-start;overflow-x:auto;gap:12px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;margin:0 -18px;padding:0 18px 12px;scrollbar-width:none}
	.aunx-revs-grid::-webkit-scrollbar{display:none}
	.aunx-rev{flex:0 0 86%;margin:0;scroll-snap-align:center}
	/* phones: the bento becomes a swipe rail, next tile peeking in */
	.aunx-feats-grid{display:flex;overflow-x:auto;gap:12px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;margin:0 -18px;padding:0 18px 12px;scrollbar-width:none}
	.aunx-feats-grid::-webkit-scrollbar{display:none}
	.aunx .aunx-feat{flex:0 0 84%;height:400px;scroll-snap-align:center}
	.aunx-feat-b{padding:20px}
	.aunx-feat-wide h3,.aunx-feat h3{font-size:22px}
	.aunx-btn{padding:14px 20px;font-size:14.5px}
	.aunx-cta .aunx-btn{width:100%}
	.aunx-hero-media{margin:0 -18px;aspect-ratio:4/3}
	.aunx-hcard{margin-top:-54px;border-radius:20px}
	.aunx .aunx-hero-tab{font-size:13px;gap:6px;padding:11px 4px}
	.aunx .aunx-hshot{grid-template-columns:72px minmax(0,1fr) auto;gap:10px;padding:12px 6px 8px}
	.aunx-hshot-img{width:72px}
	.aunx-hshot small{font-size:10px;letter-spacing:.06em;white-space:nowrap}
	.aunx-hshot b{font-size:21px;margin-bottom:7px}
	.aunx-hshot-sp{gap:5px}
	.aunx-hshot-sp span{font-size:11.5px;padding:3px 8px}
	.aunx-hshot-go{width:36px;height:36px}
	/* phones: get the photo into the first screen — the strip right under the banner repeats these three promises */
	.aunx-hero{padding-top:98px}
	.aunx-hero h1{margin:16px 0 12px}
	.aunx-hero-trust{display:none!important}
	.aunx-hero .aunx-cta{margin:22px 0 0}
	.aunx-hero-in{gap:26px}
	.aunx-rail{display:flex;overflow-x:auto;gap:10px;padding:4px 2px 10px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;margin-left:-18px;margin-right:-18px;padding-left:18px;padding-right:18px}
	.aunx .aunx-tab{flex:0 0 172px;scroll-snap-align:start}
	.aunx-panel{padding:26px 22px 26px}
	.aunx-specs{gap:10px}
	.aunx-specs li{padding:12px}
	.aunx-specs b{font-size:16px}
	.aunx-panel-foot .aunx-btn{width:100%}
	.aunx-faq summary{font-size:16.5px;padding:18px 2px}
	.aunx-faq-a{padding-right:8px}
}
@media (max-width:379px){
	/* the whole row is the link; on the narrowest phones the arrow gives its room to the specs */
	.aunx-hshot-go{display:none}
}
@media (prefers-reduced-motion:reduce){
	.aunx *,.aunx *::before,.aunx *::after{animation:none!important;transition:none!important}
}
CSS;
}
