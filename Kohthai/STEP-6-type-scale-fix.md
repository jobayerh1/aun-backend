# Step 6 — the text really is too small. Here's the corrected CSS.

**Replace your entire `.kt-*` block in Additional CSS with the block below.** It supersedes
Step 4a, Step 4-revised and Step 5a. Nothing else changes — no HTML edits, no plugin change.

---

## You were right, and it wasn't intentional

I measured your live page. The section is running two different type scales:

| | Size | vs body |
|---|---|---|
| Theme body text | 16px | — |
| **Plugin** feature text | 16px | correct |
| **Plugin** heading | 20px | correct |
| My section headings | 11px | **−31%** |
| My feature labels | 10.5px | **−34%** |
| My care values | 13px | −19% |
| My notes list | 13.5px | −16% |
| My lead paragraph | 15.5px | −3% |

I designed that block against the phone mockup, which had a 13px base, and then carried the
same numbers onto your real site without rebasing them to your theme's 16px. So the plugin's
half of the section is correctly sized and everything I hand-wrote below it is 20–34% too
small. That's the whole reason the bottom looks tiny next to the top.

The icons were undersized for the same reason — 20–22px glyphs sitting next to 16px text.

---

## The corrected block

```css
/* ---------- feature row (short description) ---------- */
.kt-feat{
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: 20px 14px;
	padding: 22px 0;
	margin: 0;
	border-top: 1px solid #e8e2d9;
	border-bottom: 1px solid #e8e2d9;
}
.kt-feat span{
	flex: 0 0 76px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 9px;
	text-align: center;
}
.kt-feat svg{ width:26px; height:26px; color:#8a7358; flex:none; }
.kt-feat b{ font-size:13px; line-height:1.35; letter-spacing:.02em; color:#4a4038; font-weight:600; }

/* ---------- product details ---------- */
.kt-lead{ font-size:17px; line-height:1.75; color:#4a4038; margin:0 0 34px; max-width:68ch; }

/* min() stops the 300px minimum forcing the columns wider than a phone.
   Without it each column measures 300px inside a 286px container. */
.kt-cols{
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
	gap: 40px 56px;
	align-items: start;
}

.kt-h{
	font-size: 13px;
	font-weight: 700;
	letter-spacing: .14em;
	text-transform: uppercase;
	color: #654321;
	margin: 0 0 20px;
	padding-bottom: 11px;
	border-bottom: 1px solid #e8e2d9;
}

.kt-spec{ display:grid; grid-template-columns:repeat(2,1fr); gap:22px 16px; margin:0 0 24px; }
.kt-spec div{ display:flex; align-items:center; gap:12px; }
.kt-spec svg{ width:26px; height:26px; color:#8a7358; flex:none; }
.kt-spec b{ display:block; font-size:19px; color:#1f1a15; line-height:1.25; }
.kt-spec em{ font-style:normal; font-size:13px; color:#6f6459; letter-spacing:.03em; }

.kt-notes{ margin:0; padding-left:19px; font-size:15px; line-height:1.7; color:#4a4038; }
.kt-notes li{ margin:0 0 7px; }

.kt-fits{ display:flex; gap:34px; margin:26px 0 0; }
.kt-fits div{ display:flex; flex-direction:column; align-items:center; gap:9px; font-size:13px; color:#6f6459; }
.kt-fits .dev{ width:30px; height:30px; color:#8a7358; }
.kt-fits .mk{ width:19px; height:19px; }

.kt-care{ display:grid; grid-template-columns:1fr; gap:20px; }
.kt-care > div{ display:flex; gap:13px; align-items:flex-start; }
.kt-care svg{ width:24px; height:24px; color:#8a7358; flex:none; margin-top:2px; }
.kt-care b{ display:block; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#1f1a15; margin-bottom:5px; }
.kt-care span span{ font-size:15px; color:#6f6459; line-height:1.6; }
```

**Delete** the old `.kt-media{ max-width:640px }` rule if it's still there.

---

## Measured before / after, on your live page

| | Before | After |
|---|---|---|
| Section headings | 11px | **13px** |
| Dimension values | 15px | **19px** |
| Notes list | 13.5px | **15px** |
| Care values | 13px | **15px** |
| Feature labels | 10.5px | **13px** |
| Lead paragraph | 15.5px / 539px wide | **17px / 670px** |
| Spec icons | 22px | **26px** |
| Feature icons | 22px | **26px** |
| Feature row | 4 + 1 orphan, 146px tall | **all 5 on one row, 115px** |
| `.kt-cols` on a phone | 300px in a 286px box | **286 / 286, no overflow** |

Add to Cart stays at **1.26 screens** on a 390px phone. Two columns still hold at 460px each
on desktop, one column on mobile.

---

## On your other four points

**2. Backorder notice — agreed, that's the delivery plugin.** Leaving it alone until we get
there. Worth noting when we do: it's the loudest element in the buy box and currently outweighs
the price.

**3. Share icons — your instinct is right, drop them.** I checked Charles & Keith's product page
earlier in this project and read its full contents: Add to Wishlist, Personalise With, Editor's
Note, Product Details & Care, Promotions, Shipping & Returns, Style Inspiration, You May Also
Like. **No share icons at all.** Zara and COS are the same. Fashion brands don't put a share row
on a product page — people share by copying the link or using the phone's own share sheet.

Flatsome showing WhatsApp only on mobile isn't a limitation, it's correct: a `wa.me` link on
desktop opens a clumsy web-WhatsApp flow. So the sensible options are to turn the whole row off
(Customize → WooCommerce → Product Page → uncheck **Show Share Icons**), or leave it and accept
it's decorative. I'd turn it off — it's five taps' worth of clutter under your buy box earning
nothing.

**4. Payment strip — understood**, if the gateway requires those logos then it stays. Ignore my
earlier note.

**5. "Smart Living Bangladesh" — understood**, same treatment as the AUN site, later.

---

## After this

The product page is essentially done. What's left on it is small: the "CLEAR" link floating
above the swatches, reviews being locked to verified buyers, and the breadcrumb still reading
"Ladies Bag Price in Bangladesh".

The bigger question is whether to roll this template across the rest of the products now, or
move to **checkout** — which is where your original goal ("seamless right through to checking
out") actually lives, and which we haven't looked at once.
