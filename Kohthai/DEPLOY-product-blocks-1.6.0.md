# Deploy — Kohthai Product Blocks v1.6.0

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 238 passed, 0 failed** · PHP 8.2 lint clean.

---

## What's in it

Two changes, **one on/off switch each**, both under **Settings → Product Blocks**.

**1. Key Features** — three cards under the price: Material · Size · Closure. You fill them in per
product; no HTML to paste ever again. A product with none of the three filled in shows no row at
all, so it is safe to turn on before you have edited anything.

**2. Description below the button** — moves the **short description** field from above the Add to
Cart button to below it, headed *About this bag*, clamped to three lines with a working
**View more**.

That second one is the one that matters. Measured on your live page: the short description renders
at **y=838** and the button at **y=1090** — a 506px paragraph was standing between the price and
the thing she came to click.

---

## Measured, on the real page, with this exact CSS and markup

```
                        BEFORE    AFTER
ADD TO CART (390px)      1102       978      -124px
description height        506        89      (3 lines; 417px expanded)
Key Features cards      115 x 61 each, every value on one line
horizontal overflow      none
```

The View more toggle was clicked both ways: 89 → 417 → 89px, label flipping
`View more` → `View less` → `View more`.

**Add to Cart is now 1,561 → 978px since this project began. 1.85 screens down to 1.16.**

---

## Install

1. **Plugins → Add New → Upload Plugin** → `kohthai-product-blocks.zip` → Replace current.
2. **Clear the WP Rocket cache.** (Always, on this site.)
3. Go to **Settings → Product Blocks** and confirm the two new switches are on.

Nothing else changes — the trust row, stock line, shortcodes and CSS are all untouched.

---

## Then set up ONE product

**Products → Elegant Flap PU Leather Crossbody Bag → edit.**

**a) Product data → General**, scroll to the three new fields:
```
Key feature: Material   PU Leather
Key feature: Size       28 × 19 × 11 cm
Key feature: Closure    Magnetic Flap
```
⚠️ **Two or three words each.** `28 × 19 × 11 cm` is exactly at the one-line limit at 390px.
Longer and it wraps, and then the three cards go uneven.

**b) Short description field** — delete what's in there now and put in the plain paragraph.
No HTML, **no blank lines**:
```
Add a touch of timeless elegance to your collection with this magnetic flap
crossbody bag. Inside you'll find a roomy main compartment that takes a phone,
wallet, keys and makeup. The adjustable strap wears it hands-free across the
body, and the optional strap turns it into a shoulder bag for the evening.
```
⚠️ Only the **first three lines** show before "View more" — make the first sentence earn the rest.

**c) Product data → General → Feature icons** — this is where the five icons go now:
```
adjustable-strap, inner-pocket, outer-pocket, optional-strap, zipper
```
⚠️ **And remove `[kt_features]` from the short description if it is still there**, or the row
renders twice. The product-data field renders it below the button; the shortcode renders it above.
Use one, not both.

**d) Description field** — unchanged. Keep `[kohthai_shorts]` and `[kt_details]` as they are.

---

## Then look at it on your own phone

One product. Before we touch the other twenty. Every time we've skipped that in this project it
has cost more than it saved.

---

## If you don't like it

**Settings → Product Blocks** → untick either switch → clear WP Rocket cache. The description
snaps back above the button and the cards disappear. Your content stays exactly where it is in
both cases — nothing is deleted or rewritten.

---

## Two things worth knowing about how it works

**The description is moved, not rewritten.** It is a `remove_action` / `add_action` pair — the
field's output is relocated intact and never parsed. v1.0.0 ran a regex over Flatsome's rendered
HTML, ate eight closing `</div>` tags and took your product page down. That is not repeated here.

**Clamping is visual only.** The full text stays in the page source, so Google still reads every
word of it. You lose 400px of column height and none of the SEO.

---

## Still yours to do, no code needed

- **Customize → WooCommerce → Product Page → Global custom tab** — put Delivery & Returns there.
  One field, all 21 products.
- `.product_meta .sku_wrapper{display:none}` in Additional CSS — the `SKU: N/A` line now sits right
  above the description.
- `.variations .reset_variations{position:static!important;display:inline-block;margin-top:6px;
  font-size:13px;color:#8a7f72}` — stops "CLEAR" floating on top of the row above the swatches.
