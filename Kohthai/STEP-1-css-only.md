# Step 1 — two CSS fixes, no plugin

Paste into **Appearance → Customize → Additional CSS**. Nothing else changes.
Both are pure CSS: if you dislike either, delete those lines and it's gone.

Do them **one at a time**, clearing the WP Rocket cache after each, and look at the
page before adding the next.

---

## 1a — the video gap

Removes the ~490px of blank space above every product video.

```css
/* Flatsome's [ux_video] reserves a 16:9 box on .video-fit and absolutely
   positions the IFRAME into it. WP Rocket's "Replace YouTube iframe with
   preview image" swaps that iframe for <div class="rll-youtube-player">,
   which is NOT absolutely positioned and brings its own padding-bottom.
   So Flatsome's box sits empty and Rocket's box stacks below it.
   Measured: 978.56px where 489.19px belongs. */
.video-fit > .rll-youtube-player {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	padding-bottom: 0 !important;
}

.video-fit > .rll-youtube-player img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
```

**Check:** open a product with a video. The blank block above it should be gone,
and the video should still show its red play button and load when clicked.

---

## 1b — permanent colour names under the swatches

Your photo swatches stay exactly as they are. This only adds the name underneath,
which is currently hidden in a hover tooltip that phones don't have.

Flatsome already prints the name in the markup — `data-name="Khaki"` — so no PHP
is needed.

```css
/* Colour name under each swatch, always visible. */
.ux-swatches--large .ux-swatch {
	position: relative;
	margin-bottom: 1.9em;
}

.ux-swatches--large .ux-swatch::after {
	content: attr(data-name);
	position: absolute;
	top: calc(100% + 6px);
	left: 50%;
	transform: translateX(-50%);
	width: 74px;
	font-size: 11px;
	line-height: 1.25;
	text-align: center;
	color: #6f6459;
	pointer-events: none;
}

/* The chosen one stands out. */
.ux-swatches--large .ux-swatch[aria-checked="true"]::after {
	color: #654321;
	font-weight: 700;
}

/* Sold-out colours read as unavailable, not just faded. */
.ux-swatches--large .ux-swatch.out-of-stock::after,
.ux-swatches--large .ux-swatch.disabled::after {
	text-decoration: line-through;
	opacity: 0.55;
}
```

**Check on a phone**, not just desktop — the phone is the case that was broken.
You should see Black / Brown / Red under the three swatches, with the selected
one in bold brown.

If the names sit too close together, raise `width: 74px`. If they overlap the
row below, raise `margin-bottom: 1.9em`.

---

## Not in this step, on purpose

- **The empty `<h5>` problem** — has to be fixed in the shortcode text itself, not
  by filtering the output. Attempting the latter is what broke the page.
- **Anything involving WhatsApp, Messenger or delivery estimates** — you already
  have plugins for both. Nothing new should duplicate them.
- **Sticky add-to-cart, tabs, layout** — check Flatsome's own Customizer options
  first. If the theme already does it, we use the theme.
