# Step 5 — one CSS change, plus plugin v1.2.1

The page is measuring right almost everywhere. Two things to fix, both found by measuring
your live product page at 1440px and 390px.

---

## 5a — the feature icon row (Additional CSS)

**The problem.** Your icon row is a grid with `minmax(88px, 1fr)`. The summary column is 459px,
and five 88px columns plus gaps need 472px — so only four fit and **Zipper drops onto a second
row on its own, left-aligned**. That's the gap under "Adjustable Strap" in your screenshot.

```
grid columns: 108.75px × 4
row 1: Adjustable · Inner · Outer · Optional
row 2: Zipper                                ← orphan
row height: 146px
```

**The fix.** Switch from grid to a centred flex row. Find `.kt-feat` in Additional CSS and
replace the first two rules with these:

```css
/* Flex rather than grid: a fixed 76px basis fits all five across the 459px summary
   column, and justify-content:center means a leftover icon is centred instead of
   stranded on the left. Also degrades correctly for products with only 4 icons. */
.kt-feat{
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 18px 14px;
	padding: 18px 0;
	margin: 0;
	border-top: 1px solid #e8e2d9;
	border-bottom: 1px solid #e8e2d9;
}

.kt-feat span{
	flex: 0 0 76px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	text-align: center;
}
```

Leave `.kt-feat svg` and `.kt-feat b` exactly as they are.

**Measured result on your page:**

| | Before | After |
|---|---|---|
| Desktop rows | 4 + 1 orphan | **all 5 on one row** |
| Row height | 146px | **98px** |
| Mobile (390px) | 4 + 1 left-aligned | 4 + 1 **centred** |

It also handles a 4-icon product properly — four centred, no empty cell pulling them left.

---

## 5b — plugin v1.2.1 (attached)

A bug I introduced in v1.2.0. The video card gets its width from an **inline** style (that's
how the ratio map works), and an inline style beats the stylesheet's mobile `width: 85vw`
rule. So on a phone:

```
card:   380px
column: 286px      ← overflowing by 94px
```

It didn't cause a horizontal scrollbar, so it was easy to miss — the card just sat wider than
its column. Now capped at `max-width: 100%`, and `aspect-ratio` keeps it exactly square as it
shrinks: **286 × 286, ratio 1.00**.

Upload it the same way and clear WP Rocket.

---

## What's now measuring correctly

```
Video card       380 × 380, aspect-ratio 1/1     square, filling its frame
Card background  rgb(250,247,243)                warm palette applied
Feature block    video 380 left · text 464 right
Size & Fit       x=224 w=460  |  Materials x=741 w=460
Lead paragraph   539px (60ch)
Feature text     display:block on mobile         the v1.2.0 fix is live
```

**And the number that matters:** Add to Cart is now at **y=1037 on a 390×844 phone — 1.23
screens down.** It started this project at 1,561px / 1.85 screens. That's the whole point of
moving the tabs below the button, and it's done.

---

## Smaller things I noticed, none urgent

- **"CLEAR" sits above the colour swatches**, right-aligned and floating. It's WooCommerce's
  reset-variation link. It only needs to appear once a colour is chosen — worth hiding until then.
- **The backorder notice is the loudest thing in the buy box.** Red on white, directly under the
  CTA. It's honest and it should stay, but it currently outweighs the price. Toning it to an amber
  info style would keep the honesty without making the product look like a problem.
- **Share icons are still Facebook / Twitter / email / Pinterest / LinkedIn.** No WhatsApp, which
  is the one Bangladeshi customers actually use. Check Customize → Social before we write anything.
- **The footer payment strip is still ~40 illegible logos.**
- **Footer still says "Smart Living Bangladesh"** on a Kohthai-branded page.
