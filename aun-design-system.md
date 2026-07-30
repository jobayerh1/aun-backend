# AUN Projector Bangladesh — Design System & Page Patterns
*Reverse-engineered from the live site (homepage, 6 product pages, all info/policy pages). Use this as the source of truth when building any new page so it matches the house style. Theme: **Flatsome / UX Builder**.*

---

## 1. Brand colors
| Token | Value | Use |
|---|---|---|
| **Primary blue** | `#0188fe` | Headings, icons, buttons, accents — THE brand color |
| Deep blue (eyebrow) | `#0166c8` | Pill-badge text |
| CTA gradient | `linear-gradient(135deg,#0188fe,#00c6ff)` | Trust rows, EMI banner |
| Cinema dark | `rgb(9,15,28)` / inner `rgb(13,22,42)` | Dark "key stats" & "cinema core" bands (`dark="true"`) |
| Light section bg | `rgb(246, 248, 251)` (primary), `#f8fafc`, `rgb(249,249,249)` | Alternating section backgrounds |
| Accent gold | `#ffbc00` | Stars, FAQ open-state border, warning notes |
| Heading text | `#0f172a` | Dark headings |
| Muted text | `#556777` / `#64748b` / `#777` | Body copy |
| Eyebrow on dark | `#4da8ff` | Feature eyebrow on cinema-dark bg |
| Card border | `#e2e8f0` | All cards |
| WhatsApp/success green | `#25D366` / `#16a34a` | Chat buttons |

> ⚠️ Legacy pages use `#0b3d91` blue + `bounceIn/flipInY` animations + a plain `<h3>See The World Through Larger Screen</h3>` intro. **Newest pattern = `#0188fe` + `[aun_head]`.** Always use the new one.

## 2. Typography
- Hero font: **Inter** (`font-family:'Inter',sans-serif;`). Otherwise theme default.
- Big stat numbers: `font-size:clamp(30px,4vw,46px);font-weight:800;line-height:1;`
- Stat labels: `font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;` color `#7da2c9` (on dark)
- Feature copy: `<h3><strong>Title</strong></h3>` + `<p><span style="font-size:140%">…</span></p>`, eyebrow via `<p class="aun-feat-eyebrow">`

## 3. Custom shortcodes (their own plugins — reuse these, don't reinvent)
| Shortcode | Purpose |
|---|---|
| `[aun_head eyebrow="" icon="fa-…" title="" sub=""]` | **Signature section header** (FA6 icons, e.g. `fa-circle-play`) |
| `[aun_video id="…"]` | Single video embed |
| `[aun_shorts ids="id1, id2" features="a, b, c" feature_title=""]` | YouTube Shorts row |
| `[aun_smart_adjustments tof="1" obstacle="0" alignment="0"]` | Auto-focus/keystone demo |
| `[aun_tutorials ids="videoID:Label, …"]` | Tutorial grid |
| `[aun_projector_finder]` | 5-question finder quiz |
| `[aun_dealers]` · `[aun_live_tracking]` · `[slb_repair_tracker]` | Dealer locator / order tracking / repair tracker |
| `[aun_worldcup_hero]` | Homepage hero |

## 4. Reusable HTML snippets

**Pill badge** (tops every info-page hero):
```html
<span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(1,136,254,0.08);border:1px solid rgba(1,136,254,0.20);font-weight:800;font-size:12px;color:#0166c8;text-transform:uppercase;letter-spacing:.5px;"><i class="fa-solid fa-…"></i> Label</span>
```

**Centered H2 + underline bar** (section headers on info pages):
```html
<h2 style="color:#0f172a;margin-bottom:8px;">Title</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
```

**Icon card** (3-up "how it works" / benefits / cross-links):
```html
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
  <div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-…"></i></div>
  <h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Title</h3>
  <p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Text</p>
</div>
```

## 5. PRODUCT PAGE template (the consistent skeleton)
1. **Brand intro** — `[featured_box img="1557" img_width="70"]` (AUN logo) + `[aun_head title="See the World Through a Larger Screen" sub="Cinema-grade visuals, effortless smart setup…"]`
2. **Hero banner** — `[ux_banner height="50%" bg_overlay="rgba(0,0,0,0.2)" hover="zoom"]` with "Access to Smarter / Unlimited wonders" + `<h6>Powered by AUN</h6>` + hidden `[video_button]`
3. **Key Stats band** — `[section bg_color="rgb(9,15,28)" dark="true" padding="48px"]`, 4× `[col span="3" span__sm="6"]` big white number + `#7da2c9` uppercase label
4. `[aun_head eyebrow="See it live" icon="fa-circle-play" title="Watch It In Action" sub="See how it turns any wall into a big-screen cinema."]` + `[aun_shorts]`
5. *(smart models)* `[aun_head eyebrow="Effortless setup" icon="fa-wand-magic-sparkles" title="Smart Adjustments"]` + `[aun_smart_adjustments]`
6. `[aun_head eyebrow="Real shots" icon="fa-image" title="Seen in Real Homes" sub="Actual photos from real customer setups — no studio tricks."]` + `[ux_gallery grid="3"]`
7. `[aun_head eyebrow="Why you'll love it" icon="fa-star" title="Designed to Impress"]` then **alternating feature rows**: 2-col `[ux_banner height="350px"]` text↔image. White bg for normal, **`rgb(9,15,28)` "cinema core"** bands for premium features (eyebrow `#4da8ff`).
8. *(optional)* 3× `[ux_image_box]` mini-features (Autofocus / 4-Point Warping / Zoom Out)
9. `[aun_head eyebrow="In the box" icon="fa-box-open" title="What's Inside the Box"]` + image/featured_box grid
10. `[aun_head eyebrow="Good to know" icon="fa-circle-question" title="Frequently Asked Questions" sub="Quick answers to what buyers ask us most."]` + `[accordion]` inside `[col span="10"]`
11. **Trust/assurance row** — `[section bg="linear-gradient(135deg,#0188fe,#00c6ff)" dark="true" padding="40px"]`, 3 cols: **1 Year Warranty** (`fa-shield-alt`) / **Fast Delivery Nationwide** (`fa-truck`) / **Easy EMI Options** (`fa-credit-card`)

**Standard product FAQ (6 Qs, reuse the wording):** bright room? · screen size / throw distance? · YouTube/Netflix directly? · dust in Bangladesh? · warranty & service? · EMI/bKash/COD?

## 6. INFO / UTILITY PAGE template (finder, dealers, track, repair, warranty, policies)
1. **Hero** — `[section bg_color="rgb(246,248,251)" padding="44px"]`, centered: pill badge + `<h1 style="color:#0188fe">` with FA icon + `<p style="color:#556777">`
2. The tool shortcode in a white section
3. **"How it works"** — 3 icon cards
4. **FAQ** — note: info pages use a **hand-rolled `<details>` accordion** with `.aun-faq` inline `<style>` (open state border-left `#ffbc00`), NOT the Flatsome `[accordion]`. Product pages use `[accordion]`.
5. **"Explore the AUN After-Sales Ecosystem"** — 3 cross-link cards (Shop / Warranty or Repair / Contact)
6. **"Talk to a Human" + "Warranty & Support"** two-panel contact block (on contact/policy pages)
7. **"Last updated"** strip, bilingual EN/BN on policy pages

## 7. Voice & tone
- Friendly, benefit-led, **Bangladesh-specific** ("built for Bangladesh's dusty conditions", "Dhaka same-day delivery", "nationwide").
- **Anti-hype / honest**: "Real Projection Photos — what you see is what you get", "no studio tricks", "actual photos from real customer setups".
  - ⚠️ **Brightness rule (July 2026):** never attach an "ANSI" figure to an AUN product on public pages. Brightness is always the parent company's official figure labelled "Lumens (Manufacturer Rated)" / "(Official Rating)", paired with the qualitative line "shines in dim or dark rooms; close the curtains in daytime". Real-world ANSI values live only in the hidden `_aun_internal_brightness` product meta used by the Smart Projector Finder. *Generic* education about the ANSI/ISO 21118 standard (no brand-specific numbers) is allowed and lives at `/projector-brightness-explained/` — link to it from every brightness mention (spec tab row + product FAQ item). Never state on-site that AUN's own published figures are false.
- Trust-first: official 1-year warranty, authorized dealers, real after-sales, live repair tracking ("the only projector brand in BD with…").
- Policy pages are **bilingual English + বাংলা**.

## 8. Standing rules
- **WP Rocket guards:** every inline `<style>`/`<script>` gets `data-no-optimize="1" data-no-minify="1"` — auto-add, never ask.
- Cards: white, `border:1px solid #e2e8f0`, `border-radius:14px`, soft shadow `0 2px 6px rgba(15,23,42,0.04)`.
- Mobile: always set `span__sm="12"` on columns.

## 9. Business facts (for copy)
- **AUN Projector Bangladesh** (legal: Smart Living Bangladesh), since **1 April 2019**
- 108 Golartek, Mirpur, Dhaka 1216 · **+880 9638-078888** · WhatsApp **8801787698268** · info@aun-projector.com.bd
- Payments: 0% EMI (34 banks, up to 6 mo), bKash/Nagad/Rocket/upay, cards, COD
- Delivery: inside Dhaka ≤2 days (৳70–135), outside ≤4 days (৳130–245)
- Socials: facebook/youtube/instagram/tiktok @aunprojectorbd
