# "Will it fit" — should we draw the full bag? Research notes

**2026-09-22.** Nothing implemented. I patched the plugin before you asked me to stop; that is
**reverted**, working tree clean, harness back to **184 passed / 0 failed**.

---

## What is actually happening now (measured on your live page)

```
body box (the measured bag)   210 × 160 mm
full photograph drawn at      260 × 275 mm
cut away by the clip          109.8 mm above   (the handle)
                               46.2 mm right   (the scarf)
```

The photo itself is fine: 545 × 580 PNG, drawn at **0.6% distortion** — none. Your body box is
good. **Nothing is broken.** The scarf is simply being sliced by a hard straight edge, and a
straight cut through a ribbon reads as a broken image. You are right that it looks bad.

---

## What I could and could not verify

**Could not:** Coach, Kipling and Victorinox — the three retailers Tangiblee names — are all behind
bot protection, so I could not see the widget running. Their marketing pages describe features but
not rendering.

**Could:**

**1. ⭐ Tangiblee's "What Fits Inside" is not the same kind of thing as our fit tab.** It works as
a **capacity meter**: the bag "loads at 100% capacity and the customer unloads and loads items of
their choosing", with "a moving scale that indicates a percentage of bag capacity." It answers
*"will my things fit"* with a **fullness percentage and an item list** — a volume model, not an
object laid over a picture of the bag.

**2. Foley + Corinna's handbag size visualizer** (a live, public implementation) is
**photographs only** — the same model wearing Mini / Small / Medium / Large, with measurements
under each. No outlines, no cropping, no object overlay. Their own description: *"Two questions and
a look at four photographs."*

So neither reference does what our "Will it fit" tab does. The closest thing to our tab is our tab.

---

## The disadvantages of drawing the full bag

Real, and worth weighing before deciding:

**1. The measured box stops being obvious — and the bag looks bigger than it is.**
This is the serious one. Drawn whole, your bag's silhouette is **1.24× wider and 1.72× taller**
than the 21 × 16 cm it is sold as. A customer reads the outer shape as "the bag". Making a bag
look bigger than it is, is precisely the failure this module exists to prevent — it is the returns
problem, not a styling detail.

**2. The fit test becomes ambiguous.** She drags an iPhone across. If it clears the body but
overlaps the scarf, has it fitted? The geometry only tests the body box. A fully drawn strap
invites a comparison the test is not making, and the verdict then looks arbitrary — which is the
same complaint the code notes were already written about depth.

**3. It costs scale, for every product.** The fixed frame is what makes a clutch look small and a
tote look big, and lets one bag be compared with another. Handles vary enormously — the notes
record one bag whose chain rises **191 mm above a 280 mm body**. Sizing the frame to fit every
handle shrinks every bag on every product, to show straps that are explicitly *not* what the
numbers describe.

**4. Clutter.** Embroidery, a scarf and a chain under a dragged iPhone is a busy picture. The
object you are judging has to stay the most legible thing on screen.

---

## ⭐ The middle path, and why I think it is right

Your own caption, printed under every scene, already says:

> *"Measurements are the bag body. Straps and handles are drawn faintly and are not included."*

**"On You" keeps that promise. "Will it fit" is the only view that breaks it** — it deletes the
straps instead of drawing them faintly.

So: **draw the whole photograph faintly, and redraw the body box over it at full strength.** You
get the whole bag back, the measured box stays unmistakably the subject, and there is no hard cut.
It is what the interface already tells the customer is happening.

On the handle problem, the fix that avoids touching scale: fade the ghost out towards the top of
its own image, so a long chain ends in a fade rather than a cut and **no frame has to grow**. That
is what I had built and reverted; it is a small change and it does not disturb the fixed frame.

### The alternatives, honestly

| | Pro | Con |
|---|---|---|
| **Full bag, solid** | looks complete | bag reads 1.24× / 1.72× too big; fit verdict ambiguous; costs scale |
| **Full bag, faded straps** (recommended) | whole bag, no cut, honours the caption, scale untouched | straps still visible under a dragged object |
| **Softer crop only** | smallest change, zero risk | still looks cut off — does not fix your actual complaint |
| **Silhouette in fit tab** | clearest geometry, closest to a pure fit test | a drawing where you asked for photographs; feels like going backwards |

### And one idea worth considering separately

Tangiblee answers the question with a **capacity meter** rather than a picture. Your tab already
runs a real 3-D fit test over all six orientations — so *"7 of 11 everyday things go in"*, which
you already display, is arguably the strongest thing on that screen. If the picture keeps fighting
us, the honest fallback is to lean on the verdict and let the image be smaller and calmer.

---

## Nothing is committed

Say which of the four you want and I will build it. My recommendation is **full bag with faded
straps**, because it is the only option that both answers your complaint and keeps the promise
already printed on the screen.

Sources: [Tangiblee — What Fits Inside](https://www.tangiblee.com/blog/innovation-spotlight-what-fits-inside) ·
[Tangiblee — handbags & luggage](https://www.tangiblee.com/categories/handbags-luggage) ·
[Tangiblee — communicating handbag sizing](https://www.tangiblee.com/blog/how-to-communicate-product-sizing-for-handbags) ·
[Foley + Corinna handbag size visualizer](https://www.foleyandcorinna.com/handbag-size-visualizer/)
