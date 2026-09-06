# Product 7 of 21 — Elegant Round Pleated Dinner Clutch Bag for Women

`https://kohthaibd.com/elegant-round-pleated-dinner-clutch-bag-for-women/` · id **1836** ·
**৳2,290** · Video `Bogu4XOCM18` · Colours: **Black, Gold, Purple, Silver**

⚠️ **Install v1.6.5 first** — it adds a **Top Handle** icon, which did not exist.

---

## Two things the photo told me that your content does not

**1. The closure is a ball clasp — and it is written down nowhere.** Not in the description, not
in the Materials tab, not in the icons. It is a small fluted gold sphere on a kiss-lock frame,
clearly visible in your first product image. That is now the Closure card.

**2. Your copy names no colours at all — and you sell four.** Black, Gold, Purple and Silver. The
description never mentions a single one.

⭐ That is the mirror image of the Chain Square bag, where the copy named only black and you sell
three. **Worth adding to your product checklist: does the copy match the swatches?** So far it has
been wrong in both directions.

Also: **550g for an 18cm clutch is heavy, and it is correct** — the metal arch handle and clasp
frame account for it. Same as the chain bag. No need to query it.

---

## 1. Product data → General → Key feature fields

```
Key feature: Material    Pleated Fabric
Key feature: Size        18 × 18 × 6.5 cm
Key feature: Closure     Ball Clasp
```

## 2. Product data → General → Feature icons

```
top-handle, optional-strap
```

`top-handle` is new in v1.6.5. Your own copy calls the gold arch handle *"the highlight of this
bag"* — and before this, the feature row showed one icon, for the detachable strap, and left out
the thing the bag is actually bought for.

I drew it as a **round** body with an arch and a clasp dot, because every other bag icon in the
set is a boxy body and a fourth boxy body would have been unreadable at 26px. Side by side it is
close to a line drawing of your own photograph.

## 3. Short description field

```
A round pleated clutch with a gold arch handle you can hold in your hand or slip over your wrist, and a ball clasp that shuts with a click. Sized for a phone, lipstick, cards and cash — the things you actually carry to a wedding. In Black, Gold, Purple and Silver.
```

The wrist-carry idea is yours and it is the best line in the original — it survives, moved to the
front where it will be seen. *"Make a dazzling statement"* and *"Designed to turn heads"* are gone;
they cost you two of your three visible lines and said nothing.

## 4. Description field

Delete the `[ux_video]`. Paste these **two lines, no blank line between them**:

```
[kohthai_shorts ids="Bogu4XOCM18" title="" icon="false" feature_title="Why you will love it" features="Gold arch handle for the hand or the wrist, Ball clasp shuts with one click, Four colours from black to silver"]
[kt_details length="18 cm" height="18 cm" width="6.5 cm" weight="550 g" fits="phone" notes="Suitable for a phone, card holder and lipstick | Size and weight may vary slightly with the material" material="Pleated shimmer fabric on a metal frame" care="Wipe with a dry or slightly damp cloth" avoid="Water, cleaners and excessive rubbing" storage="Keep in the dust bag when not in use"]
```

The velvet-style `avoid` value is kept again — right for a shimmer fabric on a frame.

## 5. Rank Math → Edit Snippet → Description

```
Round pleated evening clutch with a gold arch handle and ball clasp, in Black, Gold, Purple or Silver. Cash on delivery across Bangladesh from Kohthai.
```

149 characters.

---

## Noticed while I was on the page

Your **"CLEAR"** link is still floating over the row above the colour swatches — the one-line fix
from Step 2 has not been applied. Customizer → Additional CSS:

```css
.variations .reset_variations{position:static!important;display:inline-block;
  margin-top:6px;font-size:13px;color:#8a7f72}
```

Otherwise the page looked right: **"IN STOCK - READY TO SHIP"** is rendering, and the trust row is
showing the in-stock wording rather than the backorder wording. Both correct.

---

## Checklist

- [ ] install v1.6.5, clear WP Rocket cache
- [ ] four fields pasted
- [ ] Rank Math description set
- [ ] CLEAR link CSS added
