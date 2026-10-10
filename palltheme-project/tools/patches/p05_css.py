"""Patch: CSS for features/trust cards, split media, enterprise footer, newsletter,
credits table and the WooCommerce store notice. Inserted before the Motion section."""
import os

P = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "palltheme", "assets", "css", "main.css")
s = open(P, encoding="utf-8").read()
MARK = "/* ==========================================================================\n   18. Motion"
BLOCK = r"""/* ==========================================================================
   17b. Features / trust cards, split media, newsletter, credits, store notice
   ========================================================================== */
.pt-features .pt-feature-card { display: flex; flex-direction: column; gap: 16px; }
.pt-features--cards .pt-feature-card { padding: 28px; }
.pt-feature-card__title { margin: 0 0 6px; font-size: 1.12rem; }
.pt-feature-card__title a { color: var(--pt-heading); }
.pt-feature-card__body p { margin: 0; color: var(--pt-muted); font-size: .95rem; }
.pt-feature-card.is-linked { cursor: pointer; }
.pt-feature-card.is-linked .pt-feature-card__title a::after { content: ""; position: absolute; inset: 0; }
.pt-feature-card.is-linked:hover { transform: translateY(-4px); box-shadow: var(--pt-shadow); border-color: var(--pt-border-strong); }
.pt-feature-card .pt-link-arrow { margin-top: 12px; font-size: .9rem; }
.pt-features--minimal .pt-feature-card { flex-direction: row; gap: 18px; }
.pt-features--trust { gap: 0; padding: 8px; border: 1px solid var(--pt-border); border-radius: calc(var(--pt-radius) + 4px); background: var(--pt-surface); box-shadow: var(--pt-shadow-sm); }
.pt-features--trust .pt-feature-card { flex-direction: row; align-items: flex-start; gap: 14px; padding: 18px; }
.pt-features--trust .pt-icon-tile { width: 44px; height: 44px; }
.pt-features--trust .pt-icon-tile svg { width: 22px; height: 22px; }
.pt-features--trust .pt-feature-card__title { font-size: 1rem; margin-bottom: 2px; }
.pt-features--trust .pt-feature-card__body p { font-size: .87rem; line-height: 1.5; }
@media (min-width: 1024px) {
	.pt-features--trust.pt-grid--3 .pt-feature-card:not(:nth-child(3n + 1)) { border-inline-start: 1px solid var(--pt-border); }
	.pt-features--trust.pt-grid--3 .pt-feature-card:nth-child(n + 4) { border-top: 1px solid var(--pt-border); }
}

.pt-split-media { display: grid; gap: clamp(28px, 5vw, 72px); align-items: center; }
@media (min-width: 960px) {
	.pt-split-media { grid-template-columns: 1fr 1fr; }
	.pt-split-media--reverse .pt-split-media__figure { order: 2; }
}
.pt-split-media__figure { position: relative; margin: 0; }
.pt-split-media__img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: calc(var(--pt-radius) + 6px); box-shadow: var(--pt-shadow-lg); }
.pt-split-media__lottie { width: 100%; aspect-ratio: 4 / 3; }
.pt-split-media__badge { inset-inline-end: -12px; bottom: -18px; animation: none; }
@media (max-width: 959px) { .pt-split-media__badge { inset-inline-end: 12px; } }
.pt-split-media__body .pt-section-head { margin-bottom: 18px; }
.pt-split-media__text { color: var(--pt-muted); font-size: 1.05rem; }
.pt-split-media__text p:last-child { margin-bottom: 0; }
.pt-split-media__body .pt-checklist { margin: 22px 0 0; }
.pt-split-media__body .pt-btn-row { margin-top: 28px; }
.pt-checklist--cols { display: grid; gap: 10px 22px; }
@media (min-width: 640px) { .pt-checklist--cols { grid-template-columns: 1fr 1fr; } }

/* Enterprise footer */
.pt-footer__top { display: grid; gap: 36px; padding-bottom: 40px; border-bottom: 1px solid var(--pt-border); }
@media (min-width: 960px) { .pt-footer__top { grid-template-columns: 1.3fr 1.2fr .9fr; gap: 56px; } }
.pt-footer--mega .pt-footer__brand { max-width: 420px; }
.pt-footer__links { display: grid; gap: 32px 24px; grid-template-columns: repeat(2, 1fr); padding-block: 40px 44px; }
@media (min-width: 640px) { .pt-footer__links { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1024px) { .pt-footer__links { grid-template-columns: repeat(7, 1fr); } }
.pt-footer__reach .pt-footer__contact { margin-top: 0; }
.pt-newsletter__text { margin: 0 0 14px; }
.pt-newsletter__row { display: flex; gap: 8px; }
.pt-newsletter__row input { flex: 1; min-width: 0; background: rgb(255 255 255 / .06); border-color: var(--pt-border-strong); color: #fff; }
.pt-newsletter__row input::placeholder { color: var(--pt-muted); }
.pt-newsletter__row .pt-btn { flex-shrink: 0; }
.pt-newsletter__note { margin: 10px 0 0; font-size: .8rem; }
.pt-newsletter__note a { text-decoration: underline; }
.pt-newsletter__msg { margin: 10px 0 0; font-size: .88rem; color: #4ade80; }
.pt-newsletter__msg.is-error { color: #f87171; }
@media (max-width: 420px) { .pt-newsletter__row { flex-direction: column; } }

/* Media credits */
.pt-credits-table { min-width: 640px; font-size: .9rem; }
.pt-credits-table th, .pt-credits-table td { padding: 10px 12px; border-bottom: 1px solid var(--pt-border); text-align: start; vertical-align: middle; }
.pt-credits-table th { color: var(--pt-heading); font-weight: 600; background: var(--pt-surface-2); }
.pt-credits-table img { width: 96px; height: 64px; object-fit: cover; border-radius: 6px; }
.pt-credits-table small { color: var(--pt-muted); }

/* WooCommerce demo / store notice (WooCommerce → Customize → Store notice) */
p.woocommerce-store-notice, p.demo_store {
	position: fixed; z-index: 98; inset-inline: 0; bottom: 0; top: auto; margin: 0; padding: 10px 56px 10px 16px;
	background: var(--pt-secondary); color: rgb(255 255 255 / .86); font-size: .85rem; text-align: center; box-shadow: 0 -8px 24px -12px rgb(0 0 0 / .5);
}
p.woocommerce-store-notice a.woocommerce-store-notice__dismiss-link { color: #fff; font-weight: 600; margin-inline-start: 12px; text-decoration: underline; }
body.woocommerce-demo-store .pt-float-chat, body.woocommerce-demo-store .pt-to-top { bottom: 64px; }
body.woocommerce-demo-store.has-float-chat .pt-to-top { bottom: 136px; }

"""
if "17b. Features / trust cards" not in s:
    assert MARK in s
    s = s.replace(MARK, BLOCK + MARK, 1)
    open(P, "w", encoding="utf-8").write(s)
print("ok")
