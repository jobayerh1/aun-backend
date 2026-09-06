# STEP 10 — where each block goes. The decision, with the measurements behind it.

**Date:** 2026-08-31 · Measured live at 390×844 on the Elegant Flap product.

---

## First, the rule both benchmarks agree on

Look again at your own Fossil desktop screenshot and note where **Add To Bag** sits: near the top,
at roughly y=378. What is above it? **Colours, and Personalize. Nothing else.**

SKU, star rating, the description, and Key Features are all **below** the button.

Charles & Keith (which I measured directly) does the same thing — buy column ends at the CTA, and
everything else is accordions underneath.

> ⭐ **The rule: above the button goes only what she must CHOOSE. Everything else goes below.**

That is the principle to decide by. Now apply it to your page rather than copying Fossil's
positions literally.

---

## 1. Key Features — KEEP IT WHERE IT IS (above the button)

I am recommending you differ from Fossil here, on purpose, for three reasons:

**a) Your buyer's objections are different from Fossil's.** A Fossil customer already knows what
Fossil leather is and that the brand is real. Your customer is buying an unbranded bag online in a
market full of fakes and inflated photos. The two questions that stop her are *"is this real
leather or plastic?"* and *"is it big enough for my phone and wallet?"* — which is exactly
**Material** and **Size**. Those are not decoration; for you they are part of the decision.

**b) You have room, Fossil does not.** Fossil's above-button area is full: Colours **and**
Personalize. Yours has one row — Color. There is no size variant to choose.

**c) It is cheap.** Measured: the Key Features row costs **61px**. That is nothing. The blocks
that are genuinely expensive above the button are the icon row (**108px**) and the description
(**506px**) — and those are the two I am moving.

**Move the cheap decisive thing up. Move the expensive reassuring things down.** That is the whole
decision.

---

## 2. The five key points (Adjustable Strap, Inner Pocket …) — MOVE THEM DOWN

They cost **108px in the most valuable space on the page**, and they are reassurance, not
decision. Nobody abandons a bag because she is unsure whether it has an inner pocket — she
abandons because she is unsure of the material, the size, or whether she'll get her money back.

They go to the **top of the description block**, where they become the first thing in the
"Product Details" section. They are visual and scannable, so they make that block feel designed
instead of texty — which is precisely the role Fossil's chip grid plays.

Nothing is lost. They just stop taxing the buy box.

---

## 3. The description — you are right, and here is what to do with it

> *"nobody reads description i guess"*

Largely true, and both benchmarks agree with you — **neither gives it a big block.** But do not
delete it: it does your SEO work, and the minority who do read it are the ones closest to buying.

**Fossil clamps it to ~4 lines with View More, inside the buy column, below the button. C&K puts
it in the buy column as an accordion. Two out of two.** So the answer to your question — before
or after Add to Cart — is **after**.

And not immediately after. In Bangladesh the objections nearest the button should be **Cash on
Delivery, 7-day return, delivery time**. So the order below the button is:

```
ADD TO CART / BUY NOW
  -> trust row (COD / return / genuine / ships in)
  -> delivery estimate
  -> WhatsApp + Messenger
  -> ABOUT THIS BAG  (3 lines, then "View more")
```

Clamped to 3 lines it is **77px instead of 506px** — and the full text is still one tap away, and
still fully indexed by Google, because clamping is visual only.

⭐ **This also permanently ends the description-width question you have asked me five times.**
There is no wide paragraph left to get the width wrong on.

---

## The measured result

```
                          NOW      AFTER STEP 10
ADD TO CART (390px)      1090          966      -124px
description height        506           77 (+28 for "View more")
page height              4683         4316
```

No horizontal overflow at 390px. **Add to Cart is now 1,561 → 966px since we started: 1.85 screens
down to 1.14.**

---

## The full structure we are landing on

**Buy column, above the button** — only what she chooses:
`title → rating → price → KEY FEATURES (3 cards) → Colour → qty + ADD TO CART / BUY NOW`

**Buy column, below the button** — reassurance, then content:
`trust row → delivery estimate → WhatsApp/Messenger → ABOUT THIS BAG (clamped)`

**Full width below** — imagery and reference only, no prose:
`video band → PRODUCT DETAILS (the 5 icons + dimensions/weight as a chip grid) → MATERIALS & CARE
→ In Frame → Reviews → Related`

---

## What to do now

This one needs the plugin, because moving the description into the buy column is not something
CSS can do — so I will ship it as **`kohthai-product-blocks` v1.6.0**, with:

- `[kt_keyfeatures material="…" size="…" closure="…"]` so you stop pasting HTML
- the description relocated to the buy column, clamped, with a working "View more"
- the five feature icons moved to the top of the details block
- **a single on/off switch**, so if you dislike it you revert in one click

Two small things you can do yourself in the meantime, both one line:

1. **Hide `SKU: N/A`** — it is showing right above the description now and it says nothing.
   Customizer → Additional CSS:
   ```css
   .product_meta .sku_wrapper{display:none}
   ```
   (Skip this if you plan to enter real SKUs one day.)

2. **Customize → WooCommerce → Product Page → "Global custom tab"** — put your Delivery & Returns
   policy there. Written once, appears on all 21 products, no code, and it takes the last piece of
   policy text out of the description.

Say go and I will build v1.6.0.
