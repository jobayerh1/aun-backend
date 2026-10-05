<?php
/**
 * The design tokens and basics shared by the homepage ([aunstore_home]) and the designed info pages ([aunp_*]):
 * colours, the .aunx scope reset, 100vw section breakout, section heads, buttons and scroll-reveal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function aunstore_ui_base_css() {
	return <<<'CSS'
.aunx{--bl:#0188fe;--cy:#00c6ff;--sky:#4da8ff;--navy:#060b16;--navy2:#0b1426;--card:#101b31;--line:rgba(255,255,255,.1);--mut:#8ea3c0;--txt:#eaf1fb;--ink:#0f172a;--slate:#5b6b82;--soft:#f4f7fb;--rule:#e3eaf3;color:var(--ink);line-height:1.6;text-align:left;-webkit-font-smoothing:antialiased}
.aunx *,.aunx *::before,.aunx *::after{box-sizing:border-box}
/* Sections are 100vw wide, which includes the scrollbar; clip the few extra pixels instead of scrolling sideways. */
html{overflow-x:hidden;overflow-x:clip}
.aunx-sec{width:100vw;max-width:100vw;position:relative;left:50%;margin-left:-50vw;overflow:hidden}
.aunx-wrap{width:100%;max-width:1200px;margin:0 auto;padding:0 24px;position:relative}
.aunx h1,.aunx h2,.aunx h3{margin:0;width:auto;font-weight:800;letter-spacing:-.025em;line-height:1.1;color:inherit;text-transform:none}
.aunx p{margin:0}
.aunx ul{list-style:none;margin:0;padding:0}
.aunx li{margin:0}
.aunx a{text-decoration:none}
.aunx img{max-width:100%;height:auto;display:block}
.aunx button{all:unset;box-sizing:border-box;cursor:pointer;font-family:inherit}
.aunx button:focus-visible,.aunx a:focus-visible,.aunx summary:focus-visible{outline:2px solid var(--bl);outline-offset:3px;border-radius:10px}

.aunx-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--bl)}
.aunx-head{max-width:720px;margin:0 auto 40px;text-align:center}
.aunx-head h2{font-size:clamp(28px,4vw,44px);margin-top:12px}
.aunx-head p{color:var(--slate);font-size:17px;margin-top:14px}
.aunx-head-dark h2{color:#fff}
.aunx-head-dark p{color:var(--mut)}
.aunx-head-dark .aunx-eyebrow{color:var(--sky)}

.aunx-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:15px 24px;border-radius:14px;font-weight:700;font-size:15px;line-height:1;white-space:nowrap;transition:transform .2s ease,box-shadow .2s ease,background .2s ease,border-color .2s ease;cursor:pointer}
.aunx-btn i{font-size:13px;transition:transform .2s ease}
.aunx-btn:hover{transform:translateY(-2px)}
.aunx-btn:hover .fa-arrow-right{transform:translateX(3px)}
.aunx-btn-pri{background:linear-gradient(135deg,var(--bl),var(--cy));color:#fff!important;box-shadow:0 12px 28px rgba(1,136,254,.35)}
.aunx-btn-pri:hover{box-shadow:0 16px 36px rgba(1,136,254,.48)}
.aunx-btn-ghost{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.2);color:#fff!important;-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.aunx-btn-ghost:hover{background:rgba(255,255,255,.13)}
.aunx-btn-line{border:1px solid #cdd9e8;color:var(--ink)!important;background:#fff}
.aunx-btn-line:hover{border-color:var(--bl);color:var(--bl)!important}
.aunx-btn-white{background:#fff;color:#0166c8!important;box-shadow:0 12px 28px rgba(0,40,90,.25)}

/* reveal on scroll (only once the script has marked the page) */
.aunx-js [data-rv]{opacity:0;transform:translateY(26px);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.7,.2,1);transition-delay:var(--d,0s)}
.aunx-js [data-rv].is-in{opacity:1;transform:none}
CSS;
}
