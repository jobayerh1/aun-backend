# Deploy — Kohthai Product Blocks v1.6.6

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 298 passed, 0 failed** · lint clean.
Your three reports, all fixed. Install and clear the WP Rocket cache — nothing to configure.

---

## 1. Overlapping colour names — a real bug, and worse than it looked

Measured on the Elegant PU Leather Handbag page:

```
swatch width      45px
gap               10px
=> pitch          55px   (distance from one swatch to the next)
label width       78px   <- already 23px too wide, on EVERY product
label white-space nowrap <- long names ignore the width entirely
```

⭐ **The labels were overlapping on every product, not just this one.** With short names like
"Black" and "Red" the text inside the 78px box is narrow enough that you cannot see it. As soon as
a name got long — "Flower Style Coffee" paints about 100px — it ran straight over its neighbour.

The label is **absolutely positioned**, so it never pushes anything apart; it can only overlap.
That means the only thing that prevents a collision is **label width < pitch**. So:

```
column-gap  10px -> 30px   => pitch 75px
label width 78px -> 72px   + white-space:normal, so long names wrap
font        12px -> 11px
```

**Measured after the fix:** no overlap · "Flower Style Beige" wraps to two tidy lines · all four
swatches still on one row on a 390px phone (308px of 390) · the block below moved down just 11px.

There is now a test that asserts **label width is less than the pitch**, so if either number is
ever tweaked, the arithmetic has to stay consistent.

## 2. The Top Handle icon — you were right to ask

It was drawn **round**, because I designed it for the Round Pleated Clutch. On a structured
rectangular handbag it reads as a **padlock**.

Redrawn as a **wide shallow body with a big arch handle** — shape-neutral, so it suits the round
clutch and the square handbag equally, and still clearly different from Inner Pocket, which is
taller and carries a flap and a dashed pocket.

## 3. The Width icon — you were right about this too, and the reason is worth stating

It was a horizontal double arrow. **Length is a horizontal line with end caps.** Two horizontal
marks for two different dimensions is not a drawing problem, it is an ambiguity — no amount of
redrawing arrowheads fixes it.

Width is the **front-to-back** dimension, so the new icon is a **3D box**: different in *kind* from
the length and height arrows, which is the entire point.

I drew four candidates and compared them at the real 26px on a live page. A side-profile-with-arrows
version was rejected because at 26px it read as the **phone icon in your Fits row**.

The set now reads: `Length ↔` · `Height ↕` · `Width ▣ (3D)` · `Weight`.

---

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Look at any product with long colour names — the Elegant PU Leather Handbag is the best test.

Nothing to configure and no content to change.
