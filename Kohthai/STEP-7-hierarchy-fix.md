# Step 7 — heading hierarchy, the description column, and the two backgrounds

All four things you spotted are real. Three are bugs in my work, one is a design decision I
should have explained. Here's what I measured on your live page.

---

## What was actually wrong: the hierarchy was inverted

```
Product H1                27.2px  Playfair  700
Plugin "Why you will…"      20px  Playfair  700   ← a SUB-heading
Accordion "Description"   17.6px  Lato      700   ← its PARENT, smaller
My spec value               19px  Lato      700   ← a number, near title size
My "SIZE & FIT"             13px  Playfair  700   ← peer of the 20px one
```

A child heading was outranking its parent, and two headings at the same level were **13px and
20px**. That's exactly the inconsistency your eye caught.

### (a) Yes, they're titles — and they were the wrong size

"Size & Fit" and "Materials & Care" are **sub-headings**, one level below "Description". So they
*should* be smaller than it — but they were 13px against a 20px peer ("Why you will love it"),
which is the mismatch you saw. They're now **16px**.

Uppercase letters have no ascenders or descenders, so they read smaller than their pixel size —
16px uppercase carries about the same visual weight as 18–20px sentence case. That's why they're
16 and not 18.

### (b) The big numbers — half design language, half overtuned

The number being larger than its label **is** deliberate: "28 cm" is the fact she wants, "Length"
is just the caption. Every spec block on a fashion site works that way.

But 19px was too far. It was bigger than the section title above it, so a dimension was
outranking a heading. **Dropped to 17px** — still clearly dominant over the 13px label, no longer
competing with titles.

### (c) The description column — you were right to keep asking

I kept telling you a narrow measure was correct typography. It is, but that wasn't the real
answer: the problem is that **paragraph shouldn't be standing alone at all**.

The fix is to move it into the card, beside the video, replacing the generic "Watch it worn in
real life…" line. Measured:

| | Before | After |
|---|---|---|
| Paragraph | 670px alone in a 1050px row | in the 464px column beside the video |
| Text column height | — | 290px, fits inside the 380px video |
| Section height | 1,035px | **946px** |
| Orphaned column | yes | **gone** |

### (2) Two tinted panels, two different temperatures

```
Accordion header  rgba(0,0,0,0.03)   a 3% BLACK tint — cool grey
Shorts card       rgb(250,247,243)   #faf7f3 — warm cream
```

Both are "a light neutral panel", 40px apart, but one is cool and one is warm. That's the
muddiness you're sensing — nothing is wrong individually, they just don't belong to the same
palette.

Charles & Keith and COS both use accordion headers that are **plain text with a divider rule**,
no fill. Doing the same leaves the cream card as the only tinted surface in the section, so the
tint actually means something.

---

## The corrected ladder, measured after the fix

```
H1 product title      27.2px  Playfair Display
Description (section)   21px  Playfair Display
Why you will love it    18px  Playfair Display
SIZE & FIT              16px  Playfair Display
spec value  28 cm       17px  Lato
body copy               16px  Lato
spec label              13px  Lato
```

A clean descending ladder, one display face for every heading. The accordion title was also the
only heading still set in Lato — it's now Playfair like the rest.

---

## 1 · Add to Additional CSS

```css
/* Sub-headings become proper peers of "Why you will love it".
   Uppercase reads smaller than its px size, so 16 upper ≈ 18–20 sentence case. */
.kt-h{ font-size:16px; letter-spacing:.12em; margin:0 0 22px; padding-bottom:12px; }

/* A dimension is data, not a heading — stop it competing with section titles. */
.kt-spec b{ font-size:17px; }

/* One tinted surface per section, not two of different temperatures.
   Accordion headers become text + a rule, the way C&K and COS do it. */
.product-page-accordian .accordion-title{
	background-color: transparent !important;
	border-top: 1px solid #e8e2d9 !important;
	padding-left: 0 !important;
	padding-right: 0 !important;
	font-family: "Playfair Display", Georgia, serif !important;
	font-size: 21px !important;
}

/* Card heading sits below the section title, not level with it. */
.kts-feat-title{ font-size:18px !important; }
```

Everything else from Step 6 stays exactly as it is.

## 2 · Move the description into the card

In the Description field, **delete the `<p class="kt-lead">…</p>` line entirely** and put that
text into `feature_desc`:

```
[kohthai_shorts ids="bUSme6zuVwQ" title="" icon="false" feature_title="Why you will love it" feature_desc="Add a touch of timeless elegance to your collection with this beautifully designed magnetic flap crossbody bag. It blends a classic, vintage-inspired look with modern functionality — roomy enough for your phone, wallet, keys and makeup, and light enough to carry all day." features="Fits your phone and wallet,Wear on shoulder or crossbody,Light enough for all day"]
```

⚠️ **No double quotes inside `feature_desc`** — they'd end the attribute. Use single quotes or
none. Commas are fine there (only `features` splits on commas). The em-dash is fine.

You can then drop the `.kt-lead` rule from Additional CSS, or leave it — harmless either way.

---

## What this leaves

The product page is done bar three small things: the "CLEAR" link floating above the swatches,
reviews being locked to verified buyers, and the breadcrumb still reading "Ladies Bag Price in
Bangladesh".

Worth deciding now whether to roll this template across the rest of the products, or start on
**checkout** — still the one template we haven't looked at, and the one your original goal was
actually about.
