# Product 1 of 21 — Versatile Soft PU Leather Shoulder Bag for Women

`https://kohthaibd.com/versatile-soft-pu-leather-shoulder-bag-for-women/` · product id **2193**

⚠️ **Install `kohthai-product-blocks` v1.6.3 first** — it is what makes the Feature icons field
render in the right place. Then clear the WP Rocket cache.

Everything below is extracted from your existing content. **Nothing is invented** — the only new
text is the About paragraph, which is your own description re-ordered.

---

## 1. Product data → General → the three Key feature fields

```
Key feature: Material    PU Leather
Key feature: Size        27 × 22 × 8 cm
Key feature: Closure     Top Zipper
```

## 2. Product data → General → Feature icons

```
adjustable-strap, inner-pocket, outer-pocket, optional-strap, zipper
```

You were right to push back on this. As of v1.6.3 the field renders at the **top of the details
block** — the position is decided in code, not by which box you paste into. You just fill in the
data. And you no longer have to be careful about using the field and the shortcode together: if
the description contains `[kt_features]`, the field is skipped automatically, so the row can only
ever appear once.

## 3. Short description field

Delete everything currently in it. Replace with this one paragraph — **plain text, no shortcodes,
no blank lines**:

```
Soft PU leather in a clean horizontal shape, with a secure top zip and a dedicated phone pocket inside. Wear it on the shoulder or across the body — the strap adjusts and detaches, so one bag covers the Dhaka commute and an evening out. In Black, Red and Brown, and light enough at 490g to carry all day.
```

**What changed and why.** Your original opened with *"Effortlessly upgrade your everyday wardrobe
with the … available exclusively at Kohthai."* Only the **first three lines** show before
"View more" — and she is already on Kohthai, so that sentence spent your most valuable copy
telling her where she is. The rewrite puts material, the zip and the phone pocket in those three
lines. Every fact is yours; only the order changed.

`[read more]` is gone — the plugin clamps and adds the toggle by itself now.

## 4. Description field

Delete everything currently in it (the lone `[ux_video]`). Replace with these **two lines, with no
blank line between them**:

```
[kohthai_shorts ids="bUSme6zuVwQ" title="" icon="false" feature_title="Why you will love it" features="Phone pocket built into the lining, Strap adjusts and detaches, Only 490g to carry all day"]
[kt_details length="27 cm" height="22 cm" width="8 cm" weight="490 g" fits="phone" notes="Ideal for a phone, wallet, makeup and daily necessities | Size and weight may vary slightly with the material" material="PU leather with a sewn thread detail" care="Wipe with a dry or slightly damp cloth" avoid="Direct sunlight and heat" storage="Keep in the dust bag when not in use"]
```

No `[kt_features]` line — the field in step 2 handles it now.
`ratio` is omitted on purpose: your site-wide default is already 1:1, the true shape of these Shorts.

---

## What this replaces

| Was | Now |
|---|---|
| `[gap]` `[ux_stack]` × 3, `[divider]`, `[tabgroup]` with 3 `[tab]`s | 2 shortcode lines + 4 fields |
| 12 × `[featured_box]`, each with `title="<h5>…</h5>"` | 0 |
| 12 icon images loaded at ~300px and scaled to 24px | inline SVG, **12 fewer image requests** |
| `[ux_image]` tick/cross graphics at `width="50"` | 17px inline SVG with a screen-reader label |

⭐ Every `title="<h5>…</h5>"` produced **an empty `<h5>` on top of the real one** — that is the
source of the empty-heading flood in the original audit. Converting the product removes it at
source. No content filter, which is what broke the page once before.

## Two facts I read out of your old markup, please confirm

1. **Fits: phone only.** Your `[featured_box]` row used image `443` for iPhone and image `442`
   for both iPad and MacBook — so 443 is the tick and 442 the cross. `fits="phone"` reproduces
   that exactly: phone ✓, tablet ✗, laptop ✗.
2. **Closure "Top Zipper"** comes from *"it secures with a top zipper"* in your description.

---

## Before you move to product 2

Save, **clear the WP Rocket cache**, and open the page on your phone. Check:

- three Key Feature cards under the price, each value on **one line**
- About paragraph showing three lines with **View more**, and the toggle works
- the five icons at the **top of the description block**, once, not twice
- video card square, and the Size & Fit / Materials columns filled

Then send me the next product's two fields and I'll do the same.
