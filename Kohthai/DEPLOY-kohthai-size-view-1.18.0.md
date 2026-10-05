# Deploy — Kohthai Size View v1.18.0

**File:** `Kohthai\kohthai-size-view.zip` · **Tests: 193 passed, 0 failed** (was 184) · lint clean.
One change: how the bag is drawn in **Will it fit**.

---

## What changed

The photograph is no longer cut off at the measured box. The **whole bag** is drawn faintly, and
the **measured body** is redrawn over it at full strength.

```
before   photo clipped to the body box   -> scarf sliced by a straight edge, handle gone
after    whole photo at 26% opacity      -> body solid, straps trailing off, no cut anywhere
```

**Nothing else moved.** Same scene box, same scale, same dashed outline, same fit maths, same
verdicts. Only the treatment of the photograph differs — so a clutch still looks small and a tote
still looks big, and bags remain comparable with each other.

## Why this, rather than simply drawing the full bag solid

Drawn solid, this bag's silhouette is **1.24× wider and 1.72× taller** than the 21 × 16 cm it is
sold as. The customer reads the outer shape as the product, so the bag looks bigger than it is —
which is the returns problem this module exists to prevent. Faint straps keep the whole bag visible
without letting it be mistaken for the measured part.

It is also what your interface already promises. Printed under every scene:

> *"Measurements are the bag body. Straps and handles are drawn faintly and are not included."*

"On you" already kept that promise. **"Will it fit" was the only view that broke it.** Now it does
not.

## The handle problem, and why no frame had to grow

A long handle runs off the top of the scene — one of your bags carries a chain **191 mm above a
280 mm body**. Rather than enlarging the frame (which would shrink every bag on every product to
make room for straps the numbers exclude), the faint layer **fades out towards the top of its own
image**. A chain therefore ends in a fade instead of a cut, and the fixed scale is untouched.

## Verified

- Regenerated `Kohthai\_preview\size-view.html` **from the plugin's own `css()` and `js()`** — not
  a hand-written copy — and checked it in a browser. The fit view emits two image layers: a masked
  ghost (`kt-sv__bagghost`) beneath a clipped solid one, with unique `mask`, `linearGradient` and
  `clipPath` ids.
- **"On you" is unchanged** and gains no ghost — there the whole bag is already solid, and a second
  faint copy under it would double every edge. There is a test for that.

## New tests (9)

The clip assertion used to pin a literal line of code that this change rewrites. It now checks the
**intent** instead, and is stronger than before: that the body is still cut by a real `clipPath`,
that its id is unique per drawing, that the faint layer is the whole photograph, that it sits
**under** the solid one, that the fade is a gradient mask rather than a plain opacity, that the
ghost is actually faint, and that the figure view never gains one.

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Open any product with a photograph → **See bag size** → **Will it fit**.

The zip carries the plugin, `cutouts.php` and all 19 bag photographs, as before.
