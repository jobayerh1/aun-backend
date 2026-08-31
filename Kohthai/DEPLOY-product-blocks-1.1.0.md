# Deploy — Kohthai Product Blocks v1.1.0

Upload, activate, clear WP Rocket. **Then clean out Additional CSS** — that's the point of this
release.

---

## 1 · Feature icons are now per-product

**Products → edit a product → Product data → General → Feature icons**

```
adjustable-strap, inner-pocket, zipper
```

They render **just below the Add to Cart button**, which is where the design puts them.

That placement is the whole reason for the field. The short description renders at
`woocommerce_single_product_summary` priority **20** — *before* Add to Cart at 30 — so a
shortcode there can only ever appear above the button. Flatsome's "HTML after Add To Cart" field
*is* below it, but it's **global**, so it can't carry per-product icons. Priority **31** is the
only spot that's both below the button and above your delivery estimate (which runs at 35).

⚠️ **Use the field or the shortcode, not both** — a product with both will show the row twice.
`[kt_features]` still works for anything you've already done.

## 2 · Trust row

Paste into **Customize → WooCommerce → Product Page → HTML after Add To Cart button**, above your
existing chat shortcode:

```
[kt_trust]
[kohthai_chat_order]
```

Four tiles, wording editable at **Settings → Product Blocks**. Defaults:

```
Cash on Delivery   Pay when it arrives
7-Day Return       If damaged or not as described     ← links to /returns_refund/
100% Genuine       Imported stock
Ships from Dhaka   Not a pre-order
```

Emptying a title hides that tile. The wording is deliberately qualified — the tests actually
assert the defaults never contain "no questions asked", "hassle-free", "free return", "warranty"
or "guarantee", because every one of those is false against your real policy.

---

## 3 · Now delete your Additional CSS

Everything below moved into the plugin. Go to **Customize → Additional CSS** and delete all of it
**except the reCAPTCHA rule**:

| Block | What happened |
|---|---|
| `.grecaptcha-badge` | **KEEP** — yours, nothing to do with this |
| `.video-fit` / `.rll-youtube-player` | moved in — still needed, 17 of your 21 products use `[ux_video]` |
| `.ux-swatches--large` | moved in, label bumped 11px → 12px |
| `.kt-feat` (the whole block) | **deleted — dead code.** I checked all 21 products: none use that markup any more |
| `.kt-lead` `.kt-cols` `.kt-h` `.kt-spec` `.kt-notes` `.kt-fits` `.kt-care` | moved in |
| `.accordion-title` | moved in **with the arrow bug fixed** |
| `.kts-feat-title` | moved in |

**Two things I found while consolidating:**

**You had duplicates.** `.kt-h` was declared twice (13px, then 16px) and `.kt-spec b` twice (19px,
then 17px). The second silently won each time — which is exactly the mess that accumulates in a
Customizer box. Only the winning values are kept.

**The accordion arrow is still broken on your live site.** You never applied Step 8, and I
confirmed it before building:

```
padding-left: 0px
toggle spans 188–234px   ·   title text starts at 188px
ARROW_OVERLAPS_TEXT: true
```

Fixed in the plugin: the toggle moves to the right, and the title now lines up with the content
beneath it.

---

## If something looks wrong

**Settings → Product Blocks** has two switches:

- **Theme fixes** — video gap, swatch names, accordion header
- **Product details type scale** — `.kt-lead`, `.kt-cols`, `.kt-h`, `.kt-spec`, etc.

Turn off whichever block misbehaves and paste that CSS back into Customizer temporarily. The
feature row and trust row keep their own styles regardless, so switching either off can't break
them.

---

## Tests

```bash
php test-kohthai-product-blocks.php
```

**110 assertions, 0 failures.** As well as the rendering, they assert: the dead `.kt-feat` block
is gone, `.kt-h` appears exactly once and at 16px, the superseded 19px is gone, no rule sets
`padding-left: 0`, the toggle is moved right, the one-row flex guarantee survived the refactor,
each CSS toggle removes only its own block, a `javascript:` returns URL is rejected, and the
trust defaults contain none of the five banned phrases.

---

## Order of work

1. Upload, activate, clear WP Rocket.
2. Add `[kt_trust]` to the Custom HTML field.
3. On **one** product: set the Feature icons field, and remove that product's `[kt_features]`
   shortcode from its short description.
4. Check it — icons below the button, trust row above them, delivery estimate below.
5. Then delete the Additional CSS per the table.

Do step 5 last, so you can compare before and after.
