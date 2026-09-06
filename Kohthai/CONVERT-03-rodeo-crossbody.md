# Product 3 of 21 — Fashion Vegetable Tanned Leather Rodeo Crossbody Bag for Women

`https://kohthaibd.com/fashion-vegetable-tanned-leather-rodeo-crossbody-bag-for-women/` · id **2130**
Video `DmT5xSgRfPs` — confirmed against that page. Colour variations on the site: **Black, Red.**

⚠️ **Install v1.6.4 first** — it adds a **Card Slot** icon, which did not exist before.

---

# 🔴 STOP — one question you must answer before this goes live

**Your own content contradicts itself about what this bag is made of.**

| Where | What it says |
|---|---|
| Product **title** | Fashion **Vegetable Tanned Leather** Rodeo Crossbody Bag |
| Materials & Care tab | **Vegetable Tanned Leather** |
| Description paragraph | *"crafted from high-quality, **medium-soft PU** with a stunning glossy finish"* |

Vegetable-tanned leather and PU are not variations of the same thing. One is cowhide tanned with
tree bark; the other is plastic film on fabric. **One of these is wrong, and it is wrong in your
product title**, which is also your Google result.

**My honest reading: it is probably PU.** Two reasons, and I would rather say so plainly than let
you find out from a customer:

1. **Vegetable-tanned leather is not glossy.** It is matte and slightly chalky, and its whole
   selling point is that it darkens and develops a patina with use. "Stunning glossy finish" is a
   description of coated PU.
2. **The price.** A genuine veg-tan leather crossbody at 24 × 14 × 5 cm would not sit in the same
   price band as your PU bags. Nothing on this product is priced like real leather.

**Why this one matters more than a typo.** Selling plastic as "Vegetable Tanned Leather" is a
false material claim. It is the same class of problem as a fake review or an invented "was" price
— and it is worse, because it is the kind of thing a customer can discover by touch, then
photograph, then post. Under your own 7-day policy, "not as described" is a return **you** pay
for. And it is the exact reputation you have said you want to build away from.

**What to do:**

- **If it is PU** — change the product title (drop "Vegetable Tanned Leather"), and use
  option A below. Your supplier's listing is likely where the phrase came from; suppliers
  routinely call coated PU "leather".
- **If it genuinely is vegetable-tanned leather** — wonderful, that is a real selling point worth
  far more than the title is currently getting from it. Use option B, and fix the description
  paragraph that calls it PU.
- **If you are not sure** — ask the supplier in writing before you sell another one.

Everything else below is ready to paste and does not depend on the answer.

---

## 1. Product data → General → Key feature fields

```
Key feature: Size        24 × 14 × 5 cm
Key feature: Closure     Gold Buckle Latch
```

**Material — pick one, per the question above:**

```
Option A (if PU):        PU Leather
Option B (if real):      Vegetable Tanned Leather
```

## 2. Product data → General → Feature icons

```
card-slot, optional-strap, zipper
```

Three this time. `card-slot` is new in v1.6.4 — the row will render blank on that item if you are
still on 1.6.3.

## 3. Short description field

Delete everything. Paste this — plain text, **no blank lines**:

```
An oversized gold buckle latch on a slim glossy body, in Black or Red. Inside there is more order than the shape suggests — a zip pocket, a phone slot and a card slot, so nothing has to be hunted for. Slim at 5cm deep and 330g, it wears close to the body from the office to the evening.
```

Two deliberate changes from your original. **"LEMON KOKO" is gone** — that is your supplier's
brand name, and printing it on your own product page sends a curious customer straight to
searching for it instead of buying from you. And the opening now leads with the gold buckle,
which is the thing a customer actually sees in the photo.

I have avoided naming the material in this paragraph until you have answered the question above.

## 4. Description field

Delete the `[ux_video]`. Paste these **two lines, no blank line between them** — and set the
`material` value to match whichever option you chose:

```
[kohthai_shorts ids="DmT5xSgRfPs" title="" icon="false" feature_title="Why you will love it" features="Oversized gold buckle latch, Card slot and phone pocket inside, Slim 5cm profile that wears close"]
[kt_details length="24 cm" height="14 cm" width="5 cm" weight="330 g" fits="phone" notes="Ideal for a phone, cards, lipstick and keys | Size and weight may vary slightly with the material" material="PU leather with a glossy finish" care="Wipe with a dry or slightly damp cloth" avoid="Direct sunlight and heat" storage="Keep in the dust bag when not in use"]
```

⚠️ `material="PU leather with a glossy finish"` assumes **option A**. If it is genuinely
vegetable-tanned leather, change it to
`material="Vegetable tanned leather"` and drop "glossy" from the short description too.

---

## Small notes

- Your description says **"Wine Red"**; the actual variation on the site is called **"Red"**.
  I used Red so the copy matches the swatch she clicks.
- **Fits: phone only** — same `443` tick / `442` cross reading as products 1 and 2.
- Weight 330g for this size is consistent and looks right.

## After saving

Clear the WP Rocket cache and check on your phone: **two** Key Feature cards if you leave Material
blank, three once you fill it; three icons at the top of the details block; About clamped with a
working View more.
