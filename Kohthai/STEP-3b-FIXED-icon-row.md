# Step 3b — corrected. Replace the previous version.

My earlier 3b was wrong, and your screenshots showed exactly why. **Delete the old 3b block
from Additional CSS and paste this one instead.**

## What I got wrong

Your `.stack-row` has **11 direct children, but only 4 are icons**:

```
P(empty) · P(empty) · [Adjustable] · BR · [Inner] · BR ·
[Optional] · BR · [Zipper] · P(empty) · P(holds a <style> block)
```

In a CSS grid **every child takes a cell**. I hid the `<br>` elements but not the empty
`<p>` tags that wpautop leaves behind — so those four paragraphs were eating grid cells and
shoving the icons into the wrong columns. That's the misalignment in your screenshots.

The giant ✓/✗ came from the same change: those images are sized `width: 50%` of their
container, and a grid cell is wider than the old flex item was, so 50% became huge.

## The corrected block

```css
@media (max-width: 549px) {
	/* Flatsome's .stack-row is flex-nowrap, so the icon labels get squeezed to
	   one word per line. Switch to a 2-column grid on phones.

	   The row also contains empty <p> and <br> that wpautop left behind. In a
	   grid EVERY child takes a cell, so these must be hidden or the icons land
	   in the wrong columns. (A <style> inside a hidden <p> still applies its
	   CSS, so hiding them is safe.) */
	.product-short-description .stack-row {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 18px 10px;
		align-items: start;
	}

	.product-short-description .stack-row > p,
	.product-short-description .stack-row > br {
		display: none;
	}

	.product-short-description .stack-row .icon-box {
		margin: 0;
		width: 100%;
	}

	/* The yes/no images are width:50% of their container, and a grid cell is
	   wider than the old flex item — cap them so they stay icon-sized. */
	.product-short-description .stack-row .img {
		max-width: 44px;
		margin-left: auto;
		margin-right: auto;
	}
}
```

## Verified before sending

Tested live at 390px on both products, with the CSS injected. Every stack on the page:

| Stack | Items | Result |
|---|---|---|
| Feature icons | 5 | 2 + 2 + 1 |
| Length / Height / Width | 3 | 2 + 1 |
| Fits phone / tablet / laptop | 3 | 2 + 1 |
| Material / Care / Avoid / Storage | 4 | 2 + 2 |

Columns land at x=31 and x=216 in every case — properly aligned. Labels sit on one line.
The ✓/✗ images measure **44 × 44** instead of blowing up.

Desktop is untouched — the whole block is inside `@media (max-width: 549px)`.
