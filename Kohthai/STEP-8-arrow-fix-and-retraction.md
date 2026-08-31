# Step 8 — arrow fix, and I'm retracting Step 7(c)

Two things: a bug I introduced, and a recommendation I got wrong. You caught both.

---

## 1 · The accordion arrow — my bug

`padding-left: 0 !important` removed the space the toggle sits in. The toggle is
`position: absolute; left: 0` and **47px wide**, so the title text slid straight underneath it.

The fix moves the toggle to the **right**, which also lets the title align with the content
below it. Replace the accordion rule from Step 7 with this:

```css
.product-page-accordian .accordion-title{
	background-color: transparent !important;
	border-top: 1px solid #e8e2d9 !important;
	font-family: "Playfair Display", Georgia, serif !important;
	font-size: 21px !important;
	padding-left: 36px !important;
	padding-right: 44px !important;
	position: relative !important;
}

/* The toggle is absolutely positioned at left:0 and 47px wide, which is what the
   title text was running under. Moving it right frees the left edge, so the title
   can line up with the content underneath instead of being indented past an icon. */
.product-page-accordian .accordion-title .toggle{
	left: auto !important;
	right: 0 !important;
}
```

Measured after: title text starts at **x=224**, exactly aligned with the content below it;
toggle sits at the far right; **no overlap**.

---

## 2 · Retracting Step 7(c) — don't move the description beside the video

You were right to question it. I tested it on the one product I had already shortened, which
wasn't representative of your catalogue.

**Your real description lengths, across all 19 products:**

```
min 396  ·  median 705  ·  max 913 characters
```

**What happens if that goes beside the video:**

| Description | Text column | Video | Result |
|---|---|---|---|
| 260 chars | 262px | 380px | fits |
| **705 (your median)** | 441px | 380px | **61px of blank beside the video** |
| **913 (your longest)** | 518px | 380px | **138px blank, card grows to 600px** |

At your median it already breaks — the text column outgrows the video, so the card stretches and
you get dead space next to a short video. It only works under about 400 characters, and almost
nothing you sell is that short.

**And the "lone column" problem was mine, not the layout's:**

```
my truncated 181 chars  →   2 lines,  60px   ← looks stranded in a 1050px row
your real    646 chars  →   8 lines, 238px   ← a proper intro block
your longest 906 chars  →  10 lines, 298px   ← substantial
```

All at the same 670px width. A two-line paragraph in a wide row looks like a fragment; an
eight-to-ten line paragraph at 64% of the row is exactly how every fashion site sets an intro.
I created the problem by cutting your copy down, then tried to fix the layout instead of the copy.

### So: keep the description where it is

- **`<p class="kt-lead">` stays above the card**, with your **full** description — don't shorten it
- **`feature_desc` gets one short line about the video**, not the product description

```
[kohthai_shorts ids="bUSme6zuVwQ" title="" icon="false" feature_title="Why you will love it" feature_desc="Watch it worn in real life - how it hangs, how big it really is, and how much it actually holds." features="Fits your phone and wallet,Wear on shoulder or crossbody,Light enough for all day"]
```

**On the Elegant Flap product specifically:** restore its full original description to
`.kt-lead`. It currently reads as a two-line fragment because I truncated it, and that's the
only reason that section looked unbalanced.

---

## Where this leaves the pattern

```
Description  (accordion, open)
  ├─ full product description          ~8-10 lines at 68ch
  ├─ [kohthai_shorts]                  video + why-you-will-love-it + 3 points
  └─ SIZE & FIT  |  MATERIALS & CARE   two columns
Reviews  (accordion, closed)
```

Everything from Step 6 and the rest of Step 7 stands — only 7(c) is withdrawn.

---

## One thing worth saying about the copy itself

Your descriptions run 396–913 characters. Charles & Keith's equivalent is around 250, Zara's is
a line or two. Yours are roughly three times the length the format expects, and a fair amount of
it repeats what the feature icons and Size & Fit already say — "inner zipper pocket", "front and
back zipper pockets", "lightweight" are all stated twice on the page.

That's **not** a problem to fix now, and it may well be deliberate for search. But if you ever
want the page to feel more like a premium brand and less like a spec sheet, tightening the
descriptions would do more than any further CSS. Worth a separate pass, not part of this one.
