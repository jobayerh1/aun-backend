# Rodeo bag — what to change, and what your URL does

## The answer to your actual worry: **the title and the URL are separate fields.**

In WordPress, the product **title** and the **permalink slug** are two different things. Changing
the title does **not** touch the URL. The slug only changes if you click *Edit* next to the
permalink and change it yourself.

So:

```
https://kohthaibd.com/fashion-vegetable-tanned-leather-rodeo-crossbody-bag-for-women/
```

**stays exactly as it is. Nothing you have published anywhere breaks.**

And changing the title fixes far more than you might expect, because these are all generated
*from* it — I checked your live page:

- the browser `<title>`
- `og:title` (what Facebook and WhatsApp show when someone shares the link)
- the `<h1>` on the page
- the Rank Math **schema.org product name** that Google reads
- the breadcrumb

**All five update automatically from one field.**

---

# Do these in order

## Step 1 — the grain test. Everything below depends on it.

Take a bag off the shelf. Follow the grain across the flat front panel under good light. **If the
same pore pattern repeats every few centimetres, it is PU.** Real leather never repeats.

If it turns out to be genuine leather, stop — keep the title and just fix the description
paragraph that calls it "medium-soft PU", plus steps 3 and 4 below.

## Step 2 — if it is PU, change the title only

```
OLD   Fashion Vegetable Tanned Leather Rodeo Crossbody Bag for Women
NEW   Rodeo Style PU Leather Crossbody Bag for Women
```

Why this one:

- **"PU Leather" is already your house convention** — *Elegant Flap PU Leather Crossbody Bag*,
  *Versatile Soft PU Leather Shoulder Bag*. This makes the odd one out consistent.
- Keeps every term anyone actually searches: **crossbody bag**, **women**, **leather**, **Rodeo**.
- You lose nothing real. **Nobody in Bangladesh is searching "vegetable tanned leather."**

Edit the title field. **Do not touch the permalink box underneath it.**

## Step 3 — 🔴 fix the meta description. This is a separate bug and it is worse than the material.

Your Rank Math meta description on this product currently reads:

> *"Buy the Stylish PU Leather Crossbody Bag at Kohthai. Trendy pleated ladies bag in Bangladesh
> with chic scrunchie handle. Versatile & cute. Order now."*

**That is a different bag.** Pleated, with a scrunchie handle — it has been pasted in from another
product. This is the sentence Google shows under your link in search results, so **your Google
listing for this product currently describes something you are not selling.**

Products → edit → scroll to the Rank Math box → Edit Snippet → Description. Replace with:

```
Rodeo style crossbody bag with an oversized buckle latch and a slim 5cm profile. Black or Red, cash on delivery across Bangladesh. Order from Kohthai.
```

⭐ **Check the other 20 products for this.** If one meta description was pasted from another
product, others probably were too. It is a five-minute audit and it directly affects click-through
from Google.

## Step 4 — fix the image alt text (10 images)

Your product images carry `alt="Fashion Vegetable Tanned Leather Rodeo Crossbody Bag for Women"`.
Alt text is read by Google Images and by screen readers, so it is a real claim, and it is
editable with no side effects.

Media → Library → find the 10 Rodeo images → update Alternative Text to match the new title.

---

## What to leave alone, deliberately

**The URL slug.** Keep it. WordPress would auto-redirect if you changed it, but:
- a slug is a historical identifier, not a claim anyone reads as a promise
- you have published this link widely, and every redirect is one more thing that can misbehave
  behind Cloudflare **and** WP Rocket
- the SEO value of that phrase is approximately zero here

This is a judgement call rather than a rule, and it is yours to make — but I would not spend the
risk on it.

**The image filenames** (`Fashion-Vegetable-Tanned-Leather-Rodeo-...-6.jpg`, 45 files including
resized versions). Changing these means re-uploading every image and re-linking the gallery and
the variations. Very high effort, very low visibility. Not worth it.

---

## One more thing to check

If you run a **Meta / Facebook product catalog**, the product title syncs into it. After changing
the title, force a catalog refresh so your ads and shop tab do not keep showing the old claim.

---

## Checklist for this product

- [ ] grain test → material decided
- [ ] title changed (permalink untouched)
- [ ] meta description replaced — it currently describes a different bag
- [ ] image alt text updated on 10 images
- [ ] weight corrected 330 g → **310 g**
- [ ] small (24×14×5) vs large (30×18×7) confirmed
- [ ] pockets counted → Card Slot icon kept or dropped
- [ ] Meta catalog refreshed, if you use one
- [ ] WP Rocket cache cleared

Tell me the outcome of the grain test and the pocket count, and I will send the finished paste for
this product in one go.
