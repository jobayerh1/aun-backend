# Kohthai — Product Page Audit (2026-08-29)

Page audited live: https://kohthaibd.com/retro-artistic-style-embroidered-crossbody-bag-for-women/
Method: DOM + computed-style measurement in a real browser at 1400×900 and 375×812, plus curl for TTFB.
All numbers below are **measured**, not estimated.

**Verified NOT a problem:** WP Rocket page cache is working (curl TTFB 0.31s / 0.70s). The 2.7s TTFB
seen in-browser was a cold first hit. Do not go chasing the AUN "dead page cache" bug here.

---

## P0 — Actually broken

### 1. ~490px of blank space above every product video
- `.video-fit` (Flatsome `[ux_video]`) sets `padding-top: 56.25%` to reserve the 16:9 box.
- WP Rocket's **"Replace YouTube iframe with preview image"** swaps the iframe for
  `<div class="rll-youtube-player">`, which carries **its own** `padding-bottom: 56.25%`
  and is **not** absolutely positioned — so it does not fill the reserved box, it stacks below it.
- Measured desktop: wrapper height **978.56px**, of which `padding-top` **489.375px** is empty,
  rll player **489.19px**. Exactly double. Mobile: 388px total, 194px empty.
- Fix (CSS, no plugin change):
```css
.video-fit .rll-youtube-player{position:absolute;top:0;left:0;width:100%;height:100%;padding-bottom:0!important}
.video-fit .rll-youtube-player img,.video-fit .rll-youtube-player iframe{width:100%;height:100%}
```

### 2. 18 empty `<h5>` and 37 empty `<p>` in the product content
- Cause A: `title="<h5>Adjustable Strap</h5>"` — Flatsome **already** wraps `title` in
  `<h5 class="uppercase">`. Result: an empty `<h5>` followed by yours.
- Cause B: every blank line inside a shortcode gets `wpautop`'d into `<p></p>`.
- Heading outline is now `H1 → 23 × H5`, no H2/H3. Screen readers announce 18 empty headings.
- Fix: `title="Adjustable Strap"` (plain text, no tags), and write shortcodes with **no blank
  lines** between them. Applies to every `[featured_box]` on every product.

### 3. No variation preselected
- `attribute_pa_color` select value on load = `""` ("Choose an option").
- She must pick a colour before Add to Cart works; clicking it first throws an error.
- Fix: set a default variation = the colour shown in the hero photo.

### 4. Colour swatches are indistinguishable
- All three are 45px crops of the same product photo:
  `...-7-247x247.jpg` (Khaki), `-6-` (Sea Blue), `-8-` (Tea Green).
- Names live **only** in a hover tooltip — which does not exist on a phone.
- Fix: real colour swatches, or keep photos but crop to the bag body **and** always render the
  selected name as text ("Colour: Tea Green").

---

## P1 — Costing sales

### 5. No sticky Add to Cart on mobile
Page is **4,052px** tall at 375px wide with **18 gallery images**. Past the buy box there is no
way to purchase without scrolling back up. Flatsome has a built-in sticky product bar — enable it.

### 6. Add to Cart fails contrast AND is the weaker-looking button
- Measured: `background #a08565` + `color #fff` = **3.48:1**. WCAG AA needs 4.5:1.
- `#654321` on white = **8.87:1**.
- Buy Now is currently the *darker* button, so the secondary action looks primary.
- Fix: Add to Cart on `#654321` solid; Buy Now as outline/secondary.

### 7. ~3,400px of dead whitespace in the desktop right column
Gallery **4,797px**, summary column **4,797px** (stretched), but summary *content* ends ~1,400px.
Summary is `position: relative`. Fix: make it sticky so the buy box follows the 18 photos.

### 8. Reviews are locked to verified buyers, and there are none
Every product shows five empty stars — for a new bag brand that reads as "nobody bought this".
Two of four related products do have 1 review. Fix: allow moderated non-purchase reviews, seed with
real customer photos from WhatsApp/Messenger, and hide the star row entirely when count = 0.

### 9. An out-of-stock product leads the Related row
"Wave Pattern Small Square Crossbody Bag" carries `.out-of-stock-label` and is first. Push OOS last
or exclude it.

### 10. No trust signals in the buy box
Only "Delivery in 1–3 business days". Her actual questions: cash on delivery? delivery charge?
can I return it? Add a compact COD / easy-return / genuine-product row directly under the CTA.

### 11. Share row has Twitter and LinkedIn, no WhatsApp
Bags get shared to friends and family groups on WhatsApp/Messenger in Bangladesh. Keep Facebook
and Pinterest; drop Twitter and LinkedIn; add WhatsApp and Messenger.

---

## P2 — Polish / IA

12. **Two "Description" tabs** — the pills tabgroup in the short description, and the WooCommerce
    tab below which contains *only* the video. Rename the lower one "Video" or move it up.
13. **Breadcrumb reads "LADIES BAG PRICE IN BANGLADESH"** — SEO category name leaking into
    customer-facing UI; wraps to two lines on mobile.
14. **The useful copy is hidden behind "See more"** — the visible half is marketing, the hidden
    half is "fits your phone, wallet, makeup". Swap them.
15. **`SKU: N/A`** displayed with no value — hide when empty.
16. **No stock indicator** anywhere ("In stock" / "Only 2 left").
17. **Mobile header has no search** — hamburger + cart only.
18. **Gallery thumbnails render as empty white boxes** before lazy-load fires.
19. **Feature icon row is `flex-wrap: nowrap`** — at 345px the labels squeeze to one word per column.
20. **Footer payment strip is ~40 illegible logos** — cut to bKash / Nagad / Rocket / Visa / MC / COD.
21. **Footer copyright says "Smart Living Bangladesh"** on a Kohthai-branded site.
22. **1.21MB / 128 requests**, of which reCAPTCHA **344KB** and Facebook Pixel **105KB + 66KB**
    load on a page with no form. Gate reCAPTCHA to pages that actually have forms.

---

## Suggested order of work
1. CSS one-liner for the video gap (#1) — biggest visible win, zero risk.
2. Shortcode cleanup template (#2) — then re-apply across all products.
3. Buy-box block: default variation, swatch labels, button contrast, trust row, sticky mobile bar
   (#3, #4, #5, #6, #10).
4. Sticky desktop summary + related-products ordering (#7, #9).
5. Reviews strategy (#8).
6. Content/IA sweep (#12–#21).
7. Third-party JS diet (#22).
