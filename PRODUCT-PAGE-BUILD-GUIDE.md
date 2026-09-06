# Kohthai Product Page — the complete build guide

**Date:** 2026-08-31 · This supersedes STEP-4 through STEP-10 as the single reference.
Everything below is measured on the live site, not estimated.

---

## Part 0 — Answering your question about the short description field

**Yes, you can write there — but not the description paragraph.**

Measured on your live page right now:

```
short description field renders at   y = 838
ADD TO CART button                   y = 1090
```

The short description field renders **above the button**. That is *why* the five feature icons
are costing you 108px in the most valuable space on the page. If you put the description text
there too, you would be putting back the 506px wall we just decided to move.

**So we swap what the two fields are for.** Each field gets exactly one job:

| Field | What goes in it | Where it appears |
|---|---|---|
| **Product data → Key Features** *(new, I build it)* | Material · Size · Closure | above the button |
| **Short description** | the About paragraph, plain text | **moved to below the button**, clamped |
| **Description** | the video, the 5 icons, dimensions, care | full width below |
| **Customizer → Global custom tab** | Delivery & Returns policy | every product, written once |

⭐ The short description field is where the About text goes — **the plugin moves that whole field
from above the button to below it.** So your instinct was right, it just needs the move to come
with it. One setting, and you never paste positioning HTML again.

---

## The target layout

**Above the button — only what she must choose:**
```
title -> rating -> price -> KEY FEATURES (3 cards) -> Colour -> qty + ADD TO CART / BUY NOW
```

**Below the button — reassurance first, then content:**
```
trust row (COD / 7-day / genuine / ships in)
delivery estimate
WhatsApp + Messenger
ABOUT THIS BAG  (3 lines, then "View more")
```

**Full width below — imagery and reference only, no prose:**
```
video band -> PRODUCT DETAILS (5 icons + dimension chips) -> MATERIALS & CARE
-> In Frame -> Reviews -> Related
```

**Measured result:** ADD TO CART moves 1,090 → 966px on a phone. Across this whole project:
**1,561 → 966px, 1.85 screens down to 1.14.**

---

# The steps, in order

Do them in this order. Steps 1–3 you can do today; step 4 waits on me.

---

## ✅ STEP 1 — Two settings, 2 minutes, do these first

**1a. Hide the "SKU: N/A" line.** It currently sits right where the description is going, and it
tells a customer nothing. Customizer → Additional CSS, add:

```css
.product_meta .sku_wrapper{display:none}
```

*(Skip this only if you intend to enter real SKUs one day.)*

**1b. Fill the global Delivery & Returns tab.** Customize → WooCommerce → Product Page → scroll to
**"Global custom tab title"** and **"Global custom tab content"**.

- Title: `Delivery & Returns`
- Content: your real policy.

This adds one tab to **all 21 products from a single field.** Change the policy once, it changes
everywhere. No code, no per-product editing.

⚠️ Write the real terms — **7 days, conditional on damaged / wrong / not as described, customer
pays return shipping.** Never "no questions asked", and never mention warranty; you don't offer
one on bags. Suggested text:

```
Cash on delivery is available across Bangladesh.
Inside Dhaka: 1-2 days. Outside Dhaka: 2-4 days.
Backorder items: 1-2 weeks, and we tell you before you pay.

Returns: within 7 days of delivery if the bag arrives damaged, is the wrong
item, or does not match its description. Please keep the packaging and send
us a photo on WhatsApp first. Return shipping is paid by the customer.
```

---

## ✅ STEP 2 — Tidy the Key Features row you already installed

You have it live and it looks right. Two adjustments:

**2a.** Keep each value to **2–3 words**. `28 × 19 × 11 cm` is exactly at the one-line limit at
390px; anything longer wraps and the three cards go uneven.

**2b. Stop the "CLEAR" link floating over the row above it.** Measured: it sits at y=485,
overlapping. Customizer → Additional CSS:

```css
.variations .reset_variations{position:static!important;display:inline-block;
  margin-top:6px;font-size:13px;color:#8a7f72}
```

Leave the cards themselves alone — do **not** move them below the button, even though Fossil does.
Your buyer's two blocking questions are "is it real leather?" and "is it big enough?", which is
Material and Size. For Fossil those are trivia; for an unbranded bag in this market they are the
decision. And the row only costs 61px.

---

## ⏳ STEP 3 — Prepare the content for ONE product (do this while I build)

Pick **Elegant Flap PU Leather Crossbody Bag** as the test product. Write out, in a text file:

**A. Key Features — three short values**
```
Material: PU Leather
Size:     28 × 19 × 11 cm
Closure:  Magnetic Flap
```

**B. About paragraph — 3 to 5 sentences.** The first sentence has to earn the rest, because only
3 lines show before "View more". Lead with what she gets, not with adjectives.

**C. Feature points** — the five you already use: `Adjustable Strap, Inner Pocket, Outer Pocket,
Optional Strap, Zipper`. ⚠️ **No commas inside a point** — the list splits on commas.

**D. Dimensions, notes, materials, care** — you already have these in `[kt_details]`; keep them.
⚠️ Notes split on a **pipe `|`**, not a comma.

**E. The YouTube Short id** for that product.

---

## 🔨 STEP 4 — I build `kohthai-product-blocks` v1.6.0

What it will do:

1. **Adds a "Key Features" panel** to WooCommerce → Product data, with three text fields —
   Material, Size, Closure. The cards then render automatically on every product that has them.
   **No more pasting HTML into the short description.**
2. **Moves the short description below the button** (priority 36 — after the delivery estimate,
   before the meta), headed **ABOUT THIS BAG**, clamped to 3 lines with a working **View more**.
3. **Moves the five feature icons** into the top of the details block.
4. **One on/off switch** in the settings. If you dislike any of it, revert in one click.

Everything ships with the test harness green before you see it — and I am shipping **one** version
with one switch, not a bundle, because v1.0.0 is what took your product page down.

---

## 📄 STEP 5 — After v1.6.0 is installed, the per-product recipe

This is what you'll do for each of the 21 products. Three fields, and it's the same every time.

**Product data → Key Features:**
```
Material  PU Leather
Size      28 × 19 × 11 cm
Closure   Magnetic Flap
```

**Short description field** — just the paragraph, plain text, no HTML, **no blank lines**:
```
Add a touch of timeless elegance to your collection with this magnetic flap
crossbody bag. Inside you'll find a roomy main compartment that takes a phone,
wallet, keys and makeup. The adjustable strap wears it hands-free across the
body, and the optional strap turns it into a shoulder bag for the evening.
```

**Description field** — four lines, **no blank lines between them**:
```
[kt_features items="Adjustable Strap, Inner Pocket, Outer Pocket, Optional Strap, Zipper"]
[kohthai_shorts ids="YOUTUBE_ID" title="" icon="false" feature_title="Why you will love it" features="Real crossbody comfort, Fits your daily essentials, Magnetic flap opens one-handed"]
[kt_details length="28 cm" height="19 cm" width="11 cm" weight="650 g" fits="phone" notes="Fits a phone, wallet and makeup | Size may vary slightly with the material" material="PU leather with a soft fabric lining" care="Wipe with a dry cloth" avoid="Rain and direct sun" storage="Keep stuffed in the dust bag"]
[kt_frames ids="MEDIA_ID, MEDIA_ID"]
```

*(`[kt_frames]` is optional — it's the worn-on-a-person strip. Leave the line out if you have no
model shots for that bag.)*

### The three traps, so you don't hit them
1. ⚠️ **Never leave a blank line** in either field. WordPress turns blank lines into empty
   paragraphs — that is what created the empty-tag mess we spent Step 4 cleaning up.
2. ⚠️ **`features` and `items` split on commas** — a feature point cannot contain a comma.
3. ⚠️ **`notes` splits on a pipe `|`** — precisely so the notes *can* contain commas.

---

## 📋 STEP 6 — Roll out, then re-measure

Do **one** product. Look at it on your own phone. Tell me what's wrong before we touch the other
twenty — every time we've skipped that step in this project, it has cost us more than it saved.

Then the remaining PDP backlog, in the order I'd do it:

| | Item | Effort |
|---|---|---|
| 1 | Breadcrumb still reads "LADIES BAG PRICE IN BANGLADESH" — that's the category display name | 5 min |
| 2 | Reviews locked to verified buyers → WooCommerce → Settings → Products → Reviews, uncheck it | 2 min |
| 3 | Uncheck **Show Share Icons** (C&K, Fossil and Zara all have none) | 2 min |
| 4 | Out-of-stock product leads the Related row | small filter |
| 5 | Mobile header has no search | theme setting |

And still parked by your decision, correctly: review collection, sale/"You save ৳X" pricing
(nothing is genuinely on sale — do not invent a discount), the backorder notice, the footer
payment strip, and "Smart Living Bangladesh".

---

## What I need from you to start

Just **"go"** on step 4. Steps 1 and 2 you can do right now without me.
