<?php
/**
 * Styles for the designed info pages ([aunp_*], design/pages.php). Printed after aunstore_ui_base_css(),
 * everything scoped under .aunx (the tool band is deliberately outside it, so the tools keep their own styles).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_pages_css() {
	return <<<'CSS'
/* ── hero ─────────────────────────────────────────────────────────── */
.aunp-hero{background:radial-gradient(760px 460px at 82% 38%,rgba(1,136,254,.24),transparent 62%),radial-gradient(700px 420px at 0% 115%,rgba(0,198,255,.12),transparent 60%),linear-gradient(180deg,#040814,#0a1224 70%,#0b1426);color:var(--txt);padding:clamp(128px,16vh,176px) 0 80px}
.aunp-hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:56px 56px;-webkit-mask-image:radial-gradient(ellipse 60% 75% at 78% 45%,#000,transparent 72%);mask-image:radial-gradient(ellipse 60% 75% at 78% 45%,#000,transparent 72%);pointer-events:none}
.aunp-hero-in{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,.7fr);gap:48px;align-items:center;min-height:300px}
.aunp-hero-copy{position:relative;z-index:1}
.aunp-pill{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#cfe6ff}
.aunp-pill i{color:var(--sky)}
.aunp-hero h1{font-size:clamp(36px,4.8vw,62px);color:#fff;letter-spacing:-.035em;line-height:1.04;margin:22px 0 18px}
.aunp-hero h1 em{display:block;font-style:normal;padding-bottom:.06em;background:linear-gradient(100deg,#4da8ff,#00e0ff 55%,#a9deff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
.aunp-hero-copy>p{font-size:clamp(16px,1.4vw,18.5px);color:#b3c4db;max-width:600px;line-height:1.65}
.aunp-cta{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
.aunp-hero-art{position:relative;width:min(340px,100%);aspect-ratio:1;justify-self:center}
.aunp-glow{position:absolute;inset:16%;border-radius:50%;background:radial-gradient(circle,rgba(1,136,254,.6),rgba(0,198,255,.14) 55%,transparent 72%);filter:blur(26px)}
.aunp-ring{position:absolute;border-radius:50%}
.aunp-ring-1{inset:11%;border:1.5px dashed rgba(122,190,255,.5);animation:aunpSpin 40s linear infinite}
.aunp-ring-1::after{content:"";position:absolute;top:-5px;left:50%;width:10px;height:10px;margin-left:-5px;border-radius:50%;background:var(--cy);box-shadow:0 0 16px var(--cy)}
.aunp-ring-2{inset:0;border:1px solid rgba(122,190,255,.2);animation:aunpSpin 70s linear infinite reverse}
.aunp-orb{position:absolute;inset:29%;border-radius:30%;display:grid;place-items:center;background:linear-gradient(145deg,rgba(77,168,255,.36),rgba(1,136,254,.1) 58%,rgba(0,198,255,.22));border:1px solid rgba(255,255,255,.22);box-shadow:0 30px 70px rgba(1,60,140,.55),inset 0 1px 0 rgba(255,255,255,.35);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);animation:aunpFloat 6s ease-in-out infinite}
.aunp-orb i{font-size:clamp(44px,4.6vw,62px);background:linear-gradient(160deg,#fff,#9fd2ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
.aunp-chip{position:absolute;display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border-radius:999px;background:rgba(10,18,36,.78);border:1px solid rgba(255,255,255,.14);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);font-size:12.5px;font-weight:700;color:#e3efff;white-space:nowrap;box-shadow:0 14px 30px rgba(0,0,0,.35);animation:aunpFloat 7s ease-in-out infinite}
.aunp-chip i{color:#34d399}
.aunp-chip-1{top:6%;right:-4%}
.aunp-chip-2{top:52%;left:-14%;animation-delay:-2.3s}
.aunp-chip-3{bottom:2%;right:0;animation-delay:-4.6s}
@keyframes aunpSpin{to{transform:rotate(360deg)}}
@keyframes aunpFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.aunp-hero.has-img{padding-bottom:104px}
.aunp-hero-img{position:absolute;inset:0;background-size:cover;background-position:center}
.aunp-hero-img::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(4,8,20,.95) 0,rgba(4,8,20,.8) 42%,rgba(4,8,20,.42)),linear-gradient(180deg,rgba(4,8,20,.75),transparent 32%,transparent 68%,#0b1426)}
.aunp-hero.has-img .aunp-hero-in{grid-template-columns:minmax(0,760px)}

/* ── sections ─────────────────────────────────────────────────────── */
/* visible, not hidden: an overflow:hidden ancestor would stop the contents list from sticking */
.aunp-sec{padding:88px 0;overflow:visible}
.aunp-white{background:#fff}
.aunp-soft{background:var(--soft)}
.aunp-dark{background:radial-gradient(900px 500px at 50% -10%,rgba(1,136,254,.2),transparent 60%),linear-gradient(180deg,#070c18,#0b1426);color:var(--txt)}
.aunp-sec>.aunx-wrap>*+*{margin-top:44px}
.aunx .aunp-head{margin-bottom:0}
.aunp-head.is-left{text-align:left;margin-left:0}
.aunp-dark .aunx-head h2{color:#fff}
.aunp-dark .aunx-head p{color:var(--mut)}
.aunp-dark .aunx-eyebrow{color:var(--sky)}

/* ── cards ────────────────────────────────────────────────────────── */
.aunp-cards{display:grid;gap:20px;grid-template-columns:repeat(3,minmax(0,1fr))}
.aunp-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.aunp-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}
.aunx .aunp-card{position:relative;display:flex;flex-direction:column;align-items:flex-start;padding:28px 26px;border-radius:22px;background:#fff;border:1px solid var(--rule);box-shadow:0 1px 2px rgba(15,23,42,.04);color:var(--ink);transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease}
.aunp-soft .aunp-card{box-shadow:0 10px 30px rgba(15,35,68,.05)}
.aunx .aunp-card:hover{transform:translateY(-4px);box-shadow:0 24px 48px rgba(15,35,68,.1);border-color:#bfdcfb}
.aunp-ico{width:52px;height:52px;flex:0 0 52px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(1,136,254,.14),rgba(0,198,255,.08));color:var(--bl);font-size:21px}
.aunp-card h3{font-size:18.5px;margin-top:20px;letter-spacing:-.015em;line-height:1.25;color:var(--ink)}
.aunp-card p{margin-top:8px;font-size:15px;line-height:1.65;color:var(--slate)}
.aunp-card p a,.aunp-step p a{color:var(--bl);font-weight:700}
.aunp-card-go{margin-top:auto;padding-top:18px;display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:700;color:var(--bl)}
.aunp-card-go i{font-size:11px;transition:transform .2s ease}
a.aunp-card:hover .aunp-card-go i{transform:translateX(3px)}
.is-center .aunp-card{align-items:center;text-align:center}
.aunp-dark .aunp-card{background:linear-gradient(180deg,rgba(16,27,49,.72),rgba(10,18,36,.6));border-color:var(--line);color:var(--txt);box-shadow:none}
.aunp-dark .aunp-card:hover{border-color:rgba(77,168,255,.45);box-shadow:0 24px 48px rgba(0,0,0,.3)}
.aunp-dark .aunp-card h3{color:#fff}
.aunp-dark .aunp-card p{color:#a3b6cf}
.aunp-dark .aunp-ico{background:linear-gradient(135deg,rgba(1,136,254,.4),rgba(0,198,255,.18));color:#cfe9ff}

/* ── steps ────────────────────────────────────────────────────────── */
.aunx .aunp-steps{position:relative;display:grid;grid-template-columns:repeat(var(--n,4),minmax(0,1fr));gap:24px;list-style:none;margin-left:0;margin-right:0;margin-bottom:0;padding:0}
.aunp-steps::before{content:"";position:absolute;top:32px;left:calc(50% / var(--n,4));right:calc(50% / var(--n,4));height:2px;background:linear-gradient(90deg,var(--bl),var(--cy));opacity:.35}
.aunp-step{position:relative;text-align:center;padding:0 6px}
.aunp-step-dot{position:relative;z-index:1;width:64px;height:64px;margin:0 auto;border-radius:50%;display:grid;place-items:center;background:#fff;border:2px solid rgba(1,136,254,.28);color:var(--bl);font-size:22px;box-shadow:0 12px 26px rgba(1,136,254,.16),0 0 0 8px #fff}
.aunp-soft .aunp-step-dot{box-shadow:0 12px 26px rgba(1,136,254,.16),0 0 0 8px var(--soft)}
.aunp-step-n{display:block;margin-top:18px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--bl)}
.aunp-step h3{font-size:18px;margin-top:6px;color:var(--ink)}
.aunp-step p{max-width:260px;margin:8px auto 0;font-size:14.5px;line-height:1.6;color:var(--slate)}

/* ── document with contents list ──────────────────────────────────── */
.aunp-doc{display:grid;grid-template-columns:250px minmax(0,1fr);gap:64px;align-items:start}
.aunp-toc{position:sticky;top:110px}
.aunp-toc-box summary{list-style:none;display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--slate);cursor:default;pointer-events:none}
.aunp-toc-box summary::-webkit-details-marker{display:none}
.aunp-toc nav{display:flex;flex-direction:column;border-left:2px solid var(--rule)}
.aunx .aunp-toc nav a{display:flex;gap:10px;margin-left:-2px;padding:8px 0 8px 16px;border-left:2px solid transparent;font-size:14px;line-height:1.4;color:var(--slate);transition:color .2s ease,border-color .2s ease}
.aunp-toc nav a span{min-width:16px;color:#9aabc2;font-variant-numeric:tabular-nums}
.aunx .aunp-toc nav a:hover{color:var(--ink)}
.aunx .aunp-toc nav a.is-active{color:var(--bl);border-left-color:var(--bl);font-weight:700}
.aunx .aunp-toc nav a.is-active span{color:var(--bl)}
.aunp-doc-body{max-width:790px}
.aunp-doc-title{font-size:clamp(26px,3vw,36px);margin-bottom:6px!important}
.aunp-clause{padding:30px 0;border-bottom:1px solid var(--rule);scroll-margin-top:110px}
.aunp-clause:last-child{border-bottom:0;padding-bottom:0}
.aunp-doc+.aunp-note{max-width:790px;margin-left:314px}
.aunp-clause h3{display:flex;align-items:center;gap:14px;font-size:clamp(19px,1.9vw,22px);color:var(--ink)}
.aunp-clause-n{flex:0 0 34px;height:34px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(1,136,254,.14),rgba(0,198,255,.08));color:var(--bl);font-size:14px;font-weight:800;letter-spacing:0}
.aunp-clause-c{margin-top:14px;padding-left:48px;color:#334155;font-size:16px;line-height:1.75}
.aunp-clause-c p+p,.aunp-clause-c p+ul,.aunp-clause-c ul+p,.aunp-clause-c ol+p,.aunp-clause-c p+ol{margin-top:12px}
.aunp-clause-c ul,.aunp-clause-c ol{list-style:none;padding:0}
.aunp-clause-c li{position:relative;padding-left:24px;margin-top:8px}
.aunp-clause-c li::before{content:"";position:absolute;left:4px;top:.66em;width:7px;height:7px;border-radius:50%;background:var(--bl)}
.aunp-clause-c strong{color:var(--ink)}
.aunp-clause-c a,.aunp-q-a a,.aunp-note a,.aunp-split-text a:not(.aunx-btn){color:var(--bl);font-weight:700}

/* ── FAQ ──────────────────────────────────────────────────────────── */
.aunp-faq{display:grid;gap:12px;max-width:840px;margin-left:auto;margin-right:auto}
.aunp-q{background:#fff;border:1px solid var(--rule);border-radius:18px;transition:border-color .25s ease,box-shadow .25s ease}
.aunp-q[open]{border-color:#bfdcfb;box-shadow:0 18px 40px rgba(15,35,68,.08)}
.aunp-q summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 24px;font-size:17px;font-weight:700;line-height:1.4;color:var(--ink)}
.aunp-q summary::-webkit-details-marker{display:none}
.aunp-q-ico{position:relative;flex:0 0 30px;height:30px;border-radius:50%;background:var(--soft);transition:background .25s ease}
.aunp-q-ico::before,.aunp-q-ico::after{content:"";position:absolute;left:50%;top:50%;width:12px;height:2px;margin:-1px 0 0 -6px;border-radius:2px;background:var(--bl);transition:transform .3s ease,background .25s ease}
.aunp-q-ico::after{transform:rotate(90deg)}
.aunp-q[open] .aunp-q-ico{background:var(--bl)}
.aunp-q[open] .aunp-q-ico::before,.aunp-q[open] .aunp-q-ico::after{background:#fff}
.aunp-q[open] .aunp-q-ico::after{transform:rotate(0)}
.aunp-q-a{padding:0 24px 22px;font-size:15.5px;line-height:1.7;color:var(--slate)}
.aunp-q-a p+p{margin-top:10px}

/* ── note ─────────────────────────────────────────────────────────── */
.aunp-note{display:flex;align-items:flex-start;gap:14px;max-width:840px;margin-left:auto;margin-right:auto;padding:18px 22px;border-radius:18px;font-size:15px;line-height:1.65;color:#334155}
.aunp-note>div{flex:1;min-width:0}
.aunp-note-ico{flex:0 0 34px;height:34px;border-radius:11px;display:grid;place-items:center;font-size:15px}
.aunp-note-warn{background:#fff8e8;border:1px solid #f7dc9c}
.aunp-note-warn .aunp-note-ico{background:#ffe7ad;color:#b45309}
.aunp-note-info{background:#eef6ff;border:1px solid #cfe3fb}
.aunp-note-info .aunp-note-ico{background:#d8eaff;color:var(--bl)}
.aunp-note-draft{background:#fffaf0;border:2px dashed #f59e0b;color:#92400e}
.aunp-note-draft .aunp-note-ico{background:#fde7c2;color:#b45309}
.aunp-note strong{color:var(--ink)}
.aunp-note p+p{margin-top:8px}

/* ── stats & facts ────────────────────────────────────────────────── */
.aunx .aunp-stats{display:grid;grid-template-columns:repeat(var(--n,3),minmax(0,1fr));border:1px solid var(--rule);border-radius:24px;background:#fff;overflow:hidden;box-shadow:0 12px 34px rgba(15,35,68,.06)}
.aunp-stats li{padding:30px 20px;text-align:center;border-left:1px solid var(--rule)}
.aunp-stats li:first-child{border-left:0}
.aunp-stats b{display:block;font-size:clamp(32px,3.6vw,46px);font-weight:800;letter-spacing:-.03em;line-height:1.05;background:linear-gradient(100deg,var(--ink),var(--bl));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;font-variant-numeric:tabular-nums}
.aunp-stats span{display:block;margin-top:10px;font-size:14px;color:var(--slate)}
.aunp-dark .aunp-stats{background:rgba(10,18,36,.6);border-color:var(--line);box-shadow:none}
.aunp-dark .aunp-stats li{border-color:var(--line)}
.aunp-dark .aunp-stats b{background:linear-gradient(100deg,#fff,#9fd2ff);-webkit-background-clip:text;background-clip:text}
.aunp-dark .aunp-stats span{color:var(--mut)}
.aunx .aunp-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.aunp-facts li{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:18px;background:#fff;border:1px solid var(--rule)}
.aunp-facts .aunp-ico{width:44px;height:44px;flex-basis:44px;border-radius:14px;font-size:18px}
.aunp-facts small{display:block;font-size:11.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#8a9ab0}
.aunp-facts b{display:block;margin-top:2px;font-size:15.5px;font-weight:700;color:var(--ink)}

/* ── split: picture + story ───────────────────────────────────────── */
.aunp-split{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:56px;align-items:center}
.aunp-split.is-rev .aunp-split-media{order:2}
.aunp-split.no-img{grid-template-columns:minmax(0,1fr);max-width:800px;margin-left:auto;margin-right:auto}
.aunp-split-media{border-radius:28px;overflow:hidden;background:var(--soft);box-shadow:0 30px 60px rgba(15,35,68,.16)}
.aunp-split.is-bare .aunp-split-media{border-radius:0;overflow:visible;background:none;box-shadow:none}
.aunx .aunp-split-img{width:100%}
.aunp-split-copy h2{font-size:clamp(28px,3.4vw,42px);margin:12px 0 16px}
.aunp-split-text{font-size:16.5px;line-height:1.75;color:#334155}
.aunp-split-text p+p{margin-top:14px}
.aunp-split-text .aunx-btn{margin-top:14px}
.aunp-dark .aunp-split-copy h2{color:#fff}
.aunp-dark .aunp-split-text{color:#b3c4db}

/* ── contact channels ─────────────────────────────────────────────── */
.aunp-channels{display:grid;grid-template-columns:repeat(min(var(--n,4),4),minmax(0,1fr));gap:18px}
.aunx .aunp-channel{display:flex;flex-direction:column;align-items:flex-start;padding:26px;border-radius:24px;background:#fff;border:1px solid var(--rule);box-shadow:0 12px 32px rgba(15,35,68,.06);color:var(--ink);transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease}
.aunx a.aunp-channel:hover{transform:translateY(-4px);box-shadow:0 24px 48px rgba(15,35,68,.12);border-color:#bfdcfb}
.aunp-channel-ico{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;font-size:24px;color:#fff;background:linear-gradient(135deg,var(--bl),var(--cy));box-shadow:0 12px 26px rgba(1,136,254,.3)}
.aunp-channel.is-wa .aunp-channel-ico{background:linear-gradient(135deg,#25d366,#128c7e);box-shadow:0 12px 26px rgba(37,211,102,.3)}
.aunp-channel.is-hours .aunp-channel-ico{background:linear-gradient(135deg,#f59e0b,#f97316);box-shadow:0 12px 26px rgba(245,158,11,.3)}
.aunp-channel small{margin-top:20px;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#8a9ab0}
.aunp-channel b{margin-top:4px;font-size:18px;line-height:1.35;word-break:break-word}
.aunp-channel-note{margin-top:6px;font-size:14px;color:var(--slate)}

/* ── closing help panel ───────────────────────────────────────────── */
.aunp-help-sec{padding:24px 0 80px}
.aunp-help{position:relative;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:40px;align-items:center;padding:48px;border-radius:32px;overflow:hidden;background:radial-gradient(600px 300px at 0% 0%,rgba(1,136,254,.35),transparent 60%),radial-gradient(500px 300px at 100% 100%,rgba(0,198,255,.18),transparent 60%),linear-gradient(135deg,#0a1428,#0d1b36);color:var(--txt);box-shadow:0 40px 80px rgba(8,20,45,.25)}
.aunp-help .aunx-eyebrow{color:var(--sky)}
.aunp-help h2{font-size:clamp(28px,3.2vw,40px);color:#fff;margin:12px 0 10px}
.aunp-help-l>p{font-size:16px;color:#a9bbd3}
.aunp-help-btns{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.aunx .aunp-help-btn{display:inline-flex;align-items:center;gap:9px;padding:13px 18px;border-radius:14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#fff;font-size:14.5px;font-weight:700;line-height:1.2;transition:background .2s ease,transform .2s ease}
.aunx .aunp-help-btn:hover{background:rgba(255,255,255,.15);transform:translateY(-2px)}
.aunx .aunp-help-btn.is-wa{background:#25d366;border-color:#25d366;color:#052e16}
.aunx .aunp-help-btn.is-wa:hover{background:#34e27a}
.aunx .aunp-help-hours{display:flex;align-items:center;gap:8px;margin-top:18px;font-size:14px;color:#8ea3c0}
.aunp-help-r{display:grid;gap:10px}
.aunx .aunp-help-link{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;transition:background .2s ease,border-color .2s ease}
.aunx .aunp-help-link:hover{background:rgba(255,255,255,.1);border-color:rgba(77,168,255,.45)}
.aunp-help-link>i:first-child{flex:0 0 40px;width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:rgba(1,136,254,.22);color:#9fd2ff;font-size:16px}
.aunp-help-link span{flex:1;min-width:0;display:flex;flex-direction:column;line-height:1.35}
.aunp-help-link b{font-size:15px}
.aunp-help-link small{font-size:13px;color:#8ea3c0}
.aunp-help-go{font-size:12px;color:#8ea3c0;transition:transform .2s ease,color .2s ease}
.aunp-help-link:hover .aunp-help-go{transform:translateX(3px);color:#fff}

/* ── tool band (outside .aunx: the tools keep their own styles) ───── */
.aunp-tool-sec{overflow:visible;display:flow-root;background:linear-gradient(180deg,#f4f7fb,#fff);padding:0 0 72px}
.aunp-tool.is-bare{padding:0;background:none;border:0;box-shadow:none;border-radius:0}
.aunp-tool{position:relative;z-index:2;max-width:1000px;margin:-44px auto 0;padding:28px;border-radius:30px;background:#fff;border:1px solid #e3eaf3;box-shadow:0 30px 70px rgba(15,35,68,.12)}

/* ── last updated ─────────────────────────────────────────────────── */
.aunp-updated{background:var(--soft);padding:22px 0;border-top:1px solid var(--rule)}
.aunp-updated p{text-align:center;font-size:13px;color:#8a9ab0}
.aunp-updated i{margin-right:4px}
.aunp-updated strong{color:var(--slate)}

/* ── responsive ───────────────────────────────────────────────────── */
@media (max-width:1024px){
	.aunp-doc{grid-template-columns:220px minmax(0,1fr);gap:40px}
	.aunp-doc+.aunp-note{margin-left:260px}
	.aunp-cols-4{grid-template-columns:repeat(2,minmax(0,1fr))}
	.aunp-channels{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:849px){
	.aunp-hero{padding:112px 0 56px}
	.aunp-hero-in{grid-template-columns:minmax(0,1fr);min-height:0}
	.aunp-hero-art{display:none}
	.aunp-sec{padding:64px 0}
	.aunp-sec>.aunx-wrap>*+*{margin-top:34px}
	.aunp-cards,.aunp-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
	.aunx .aunp-steps{grid-template-columns:minmax(0,1fr);gap:0}
	.aunp-steps::before{top:32px;bottom:32px;left:31px;right:auto;width:2px;height:auto;background:linear-gradient(180deg,var(--bl),var(--cy))}
	.aunp-step{display:grid;grid-template-columns:64px minmax(0,1fr);column-gap:18px;text-align:left;padding:0 0 26px}
	.aunp-step:last-child{padding-bottom:0}
	.aunp-step-dot{grid-row:span 3;margin:0}
	.aunp-step-n{margin-top:4px}
	.aunp-step p{margin:6px 0 0;max-width:none}
	.aunp-doc{grid-template-columns:minmax(0,1fr);gap:26px}
	.aunp-toc{position:static}
	.aunp-toc-box{border:1px solid var(--rule);border-radius:18px;background:var(--soft);padding:14px 18px}
	.aunp-toc-box summary{margin:0;pointer-events:auto;cursor:pointer}
	.aunp-toc-box summary::after{content:"";margin-left:auto;width:8px;height:8px;border-right:2px solid var(--slate);border-bottom:2px solid var(--slate);transform:rotate(45deg);transition:transform .25s ease}
	.aunp-toc-box[open] summary::after{transform:rotate(225deg)}
	.aunp-toc-box nav{margin-top:12px}
	.aunp-clause-c{padding-left:0}
	.aunp-doc+.aunp-note{margin-left:0}
	.aunp-split{grid-template-columns:minmax(0,1fr);gap:32px}
	.aunp-split.is-rev .aunp-split-media{order:0}
	.aunp-help{grid-template-columns:minmax(0,1fr);gap:28px;padding:36px 28px}
	.aunp-tool{margin-top:-28px;padding:18px}
}
@media (max-width:549px){
	.aunp-cards,.aunp-cols-2,.aunp-cols-4{grid-template-columns:minmax(0,1fr)}
	/* phones: compact cards, icon beside the text */
	.aunx .aunp-card{display:grid;grid-template-columns:44px minmax(0,1fr);column-gap:16px;align-items:start;padding:20px}
	.aunp-card .aunp-ico{grid-row:span 3;width:44px;height:44px;flex-basis:44px;border-radius:14px;font-size:18px}
	.aunp-card h3{margin-top:2px;font-size:17px}
	.aunp-card p{margin-top:6px;font-size:14.5px}
	.aunp-card-go{padding-top:10px}
	.is-center .aunp-card{text-align:left;align-items:start}
	.aunx .aunp-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
	.aunp-stats li:nth-child(odd){border-left:0}
	.aunp-stats li:nth-child(n+3){border-top:1px solid var(--rule)}
	.aunp-stats li:last-child:nth-child(odd){grid-column:span 2}
	.aunx .aunp-facts{grid-template-columns:minmax(0,1fr)}
	.aunp-channels{grid-template-columns:minmax(0,1fr)}
	.aunx .aunp-channel{display:grid;grid-template-columns:48px minmax(0,1fr);column-gap:16px;align-items:center;padding:18px 20px}
	.aunp-channel-ico{grid-row:span 3;width:48px;height:48px;border-radius:15px;font-size:20px}
	.aunp-channel small{margin-top:0}
	.aunp-channel b{font-size:16.5px}
	.aunp-channel-note{font-size:13.5px}
	.aunp-q summary{padding:18px 18px;font-size:16px}
	.aunp-q-a{padding:0 18px 20px}
	.aunp-help{padding:30px 22px;border-radius:26px}
	.aunx .aunp-help-btn{flex:1 1 100%;justify-content:center}
	.aunp-tool{padding:12px;border-radius:22px}
}
@media (prefers-reduced-motion:reduce){
	.aunp-ring,.aunp-orb,.aunp-chip{animation:none!important}
}
CSS;
}
