# STEP 9 — the Fossil pattern: Key Features row + clamped description

**Date:** 2026-08-31 · **Test product:** Elegant Flap PU Leather Crossbody Bag
**Type:** CSS + content only. No plugin update yet — design first, shortcode second.

---

## What the Fossil screenshot actually shows (and why it changes my advice)

I previously measured **Charles & Keith** and reported that they have *no full-width description
block at all* — every word lives in the 446px buy column as accordions. That was true, and it led
me to propose a large restructure (move everything into the buy column, per-product content move).

**Fossil does something different, and simpler.** Fossil keeps a full-width block below the
product. What makes it work is not the width — it is that **almost nothing in it is prose**:

| Fossil block | What it is |
|---|---|
| Description | **3–4 lines, then "View More"** — never a wall |
| **Key Features** | 3 outlined cards: `MATERIAL / Leather` · `MEASUREMENTS / 11"L × 4.5"W × 8"H` · `CLOSURE / Zipper` |
| Product Details | a **chip grid** of label/value pairs, not sentences |
| Product Care | headed accordion, collapsed |

⭐ **So the fix is not "move the description into the buy column". It is "stop putting prose in
the full-width block."** That is far less work than the C&K restructure and it is the pattern the
brand you actually pointed at is using.

---

## The two changes in Step 9

### 9a — Key Features row (replaces the 5 icon names in the short description)

Today the short description holds five feature *names* — Adjustable Strap, Inner Pocket, Outer
Pocket, Optional Strap, Zipper. They occupy the prime slot directly under the price and tell a
buyer almost nothing she can act on.

The three facts she is actually deciding on — **what it is made of, how big it is, how it
closes** — are currently buried in `.kt-cols`, **1,835px below the Add to Cart button.**

9a swaps them. The five names move down into the details block; the top three specs come up into
the same slot, styled as Fossil's outlined cards.

**Same real estate. Three times the information. And it is 47px shorter.**

### 9b — clamp the description to 4 lines with "View more"

`.kt-lead` is a **536px wall of text on a phone.** Fossil truncates to 3 lines with a toggle.

This also finally settles the desktop-width question you asked four times: a 4-line intro at 670px
is a tidy paragraph, not a 380px-wide field of dead white. No two-column newspaper trick needed —
that rule gets deleted.

---

## Measured live, by injecting it into the real page

**Phone (375px):**

```
                       BEFORE    AFTER
ADD TO CART             1074      1013     -61px
short description        107        60
.kt-lead                 536       119     -417px
Description accordion   2138      1796     -342px
page height             4726      4322     -404px
```

**Desktop (1440px):**

```
ADD TO CART              607       562     -45px
short description        116        61
.kt-lead width           976       670     (2-column newspaper effect gone)
Key Features cards      165 x 61 each, all values on ONE line, no overflow
```

Phone cards measure 110×61, values single-line, `scrollWidth == innerWidth` (no sideways overflow).

**Add to Cart has now moved 1,561 → 1,013px since this project started.** From 1.85 screens down
to **1.20**.

### Bonus fix included: the floating "CLEAR" link
`.reset_variations` is `position:absolute` and was landing **on top of** the row above the
swatches. Now `position:static`, in flow, small and grey below the colours. Measured y=485
(overlapping) → y=517 (clean).

---

## 1) Paste into Customizer → Additional CSS

Add this at the end. **Also delete the old `@media(min-width:850px){.kt-lead{columns:2 …}}` rule**
— 9b replaces it.

```css
/* ---- Step 9a: Key Features cards ---- */
.kt-kf{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:4px 0 2px}
.kt-kf>div{border:1px solid #e8e2d9;border-radius:3px;padding:10px 8px;text-align:center;background:#fff}
.kt-kf em{display:block;font-style:normal;font-size:11px;letter-spacing:.08em;
  text-transform:uppercase;color:#8a7f72;margin-bottom:4px}
.kt-kf b{display:block;font-size:13px;line-height:1.35;font-weight:600;color:#3a3229}

/* ---- Step 9b: clamped description ---- */
.kt-lead.kt-clamp{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
.kt-more{background:none;border:0;padding:6px 0;margin:0 0 10px;font-size:14px;color:#654321;
  text-decoration:underline;box-shadow:none;min-height:0;line-height:1.4;
  text-transform:none;letter-spacing:0;cursor:pointer}
.kt-lead{max-width:68ch}

/* ---- Step 9c: "Clear" no longer floats over the row above ---- */
.variations .reset_variations{position:static!important;display:inline-block;
  margin-top:6px;font-size:13px;color:#8a7f72}
```

## 2) Short description field — ONE product first

Replace the whole short description with this. **No blank lines** (a blank line makes WordPress
insert `<p></p>` — that is what created the empty-tag mess originally).

```html
<div class="kt-kf">
<div><em>Material</em><b>PU Leather</b></div>
<div><em>Size</em><b>28 &times; 19 &times; 11 cm</b></div>
<div><em>Closure</em><b>Magnetic Flap</b></div>
</div>
```

Keep each value **short — 2 or 3 words.** Long values wrap to two lines and the three cards go
uneven. `28 × 19 × 11 cm` is exactly at the limit and fits on one line at both sizes; do not add
a fourth dimension there.

## 3) Nothing to change in the Description field yet

The five feature names are not lost — they belong in the details block, and that is Step 10, which
is where the `[kt_details]` prose becomes a Fossil-style chip grid.

---

## After you confirm you like it

This becomes **`kohthai-product-blocks` v1.6.0**, one new shortcode, so you never paste HTML again:

```
[kt_keyfeatures material="PU Leather" size="28 × 19 × 11 cm" closure="Magnetic Flap"]
```

and the "View more" toggle ships as real code rather than needing JS in the page. I am deliberately
**not** building that until you have looked at it on one product — the plugin that broke the page
in v1.0.0 was a bundle shipped before anyone had seen it.

## Then Step 10 (proposed, not built)

Split the one 1,796px Description accordion into Fossil's three:
**Product Details** (chip grid — the 5 feature names, dimensions, weight, all as label/value
chips) · **Product Care** · **Delivery & Returns** (Customizer global custom tab — written once,
appears on all 21 products, zero code).
