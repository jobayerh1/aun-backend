# Deploy — Kohthai Size View v1.16.1

Answers "how big is it really" on the product page, two ways.

**Preview it before you install anything:** open `Kohthai\_preview\size-view.html` in
your browser. That page is generated from the plugin's own CSS and JS, so what you
see there is exactly what the site will render — four real Kohthai bags, both
tabs, working sliders.

---

## What it does

**On you** — **the real photograph of the bag**, cut out and placed on a figure at
*her* height, at true scale. She sets her own height with a slider (remembered across
the whole shop), and she can **pick the bag up and drag it** between the ways it can be
worn — right hand, other hand, shoulder, across the body. The positions she is not using
show as dashed outlines, so it is obvious the bag can be moved. A tap counts as "next
position", because "drag the little bag precisely" is a poor instruction on a phone, and
the arrow keys do the same thing for anyone using a keyboard.

There are **two sliders**, as on Coach: her height, and her build (Slim / Medium / Curvy /
Fuller / Plus). The build widens the figure through the waist and hips, and the bag moves
outward with her. Shoulders and arms follow only part of the way and the head barely at
all — widening everything equally produced a broad-shouldered giant rather than a fuller
woman. Both settings are remembered across the whole shop. This tab opens by default.

**The frame stays still and she changes inside it**, exactly as in the Coach screenshots:
the panel is a fixed size, her feet stay on one floor line, and only the top of her head
moves. Between 135 cm and 185 cm the figure goes from 341 px to 467 px in the same
520 px panel. An earlier version sized the drawing to fit whatever it contained, which
meant every height filled the panel identically and the slider appeared to do nothing but
shrink the background.

**Will it fit** — a real fitting test rather than a picture of one. The bag is drawn at
true scale; pick any of eleven everyday things from the tray — iPhone 16 Pro Max, iPad
Pro 11", MacBook Air 13", 500 ml water bottle, folding umbrella, sunglasses, card holder,
keys, 30 ml perfume, lipstick, compact mirror — **drag it onto the bag and turn it**, and
it tells you whether it goes in *held that way*.

⚠️ **Two of the eleven are categories, not products.** The Apple sizes are published
specifications and exact. A "500 ml water bottle" and a "folding umbrella" vary by brand,
so both were checked against published ranges and set at the **small end** — the module
therefore errs towards saying yes rather than wrongly refusing something:

| | Published range | Used |
|---|---|---|
| 500 ml PET bottle | 215–230 mm tall, 68–72 mm at its widest | 212 × 68 mm |
| Folding umbrella, 3-fold | 230–280 mm folded | 235 × 52 mm |

The bottle uses its **widest** diameter, not its 52 mm base, because the widest point is
what has to pass the opening.

⚠️ **Two bugs meant a hand-drawn box could not survive at all — both fixed in 1.16.1.**

They only became visible once there was a comfortable way to draw a box, but both were older
than the editor.

1. **The save threw the box away.** Most of your products have no *upload* — they use the
   cut-out that ships with the plugin — and the save loop treated "no upload" as "no
   photograph": it deleted the body box and moved on to the next product. Every box measured
   by hand was lost, and the screen came back showing the shipped one.
2. **A shipped photograph never read the saved box anyway.** It took its box straight from
   the manifest, so even a box that had been stored would have changed nothing on the
   product page.

Both paths are covered by tests now, including one that walks the real route — a real
product, a real manifest slug — and checks the drawn box comes back out at the other end. A
box that is broken or outside the picture still falls back to the shipped one rather than
being trusted.

⭐ **You can now draw the bag body yourself, on the settings screen.**

The box is still found automatically and mostly it is right, but the detector is a heuristic
and it has been wrong before — a chain handle is exactly as wide as its bag, so on six of
nineteen products it originally returned the whole picture. Until now the only way to correct
it was to type four decimal fractions.

On the settings screen the photograph is now 240 px wide with the box drawn over it:

* **drag on the picture** to draw a fresh box
* **drag the middle** to move it
* **drag a corner or an edge** to resize — the opposite edge stays put
* the four fractions follow whatever you draw, and typing in them still moves the box
* **measure it for me again** clears it and hands it back to the detector on the next save

A rectangle is enough even for a curved bag. The box only ever says *where* the body sits in
the photograph, so the picture can be scaled to the real measurements and clipped to it —
nothing traces the outline, so a polygon would buy nothing and cost a great deal to place.

Under the numbers is a quiet check: the box's shape against the bag's own across-by-tall.
Photographs are taken at an angle and bags are not rectangles, so a difference is normal and
only a large one is queried — which is exactly what a handle swept in with the body looks
like. It is advice, never a rule, and it never blocks a save.

⚠️ **The bag photograph was being sliced across the top on phones.** The cut-out is scaled
so the bag *body* lands on the box it is measured by — which means a chain or a long handle
reaches far above the bag: on one of these products, 191 mm above a 280 mm bag. That spilled
outside the drawing and was chopped off by the panel it sits in. On a desktop the panel is
roomy enough to hide the problem; on a phone it is not, which is why it looked like a
device-specific glitch. In the fitting view the body is the whole subject, so the photograph
is now held to it with a clip path — the cut lands exactly on the outline that is drawn
anyway, instead of somewhere across the middle of a chain. The figure on "On you" keeps its
straps, because there the bag hangs from her hand and the strap is the point.

⚠️ **`touch-action` has to sit on the `<svg>` root, not on the thing being dragged.**
Browsers do not honour `touch-action` on SVG *child* elements — only on the root or on HTML
elements. It was set on the dragged groups, so on a phone the browser claimed every drag as
a pan, fired `pointercancel`, and the drag died before it began while a mouse worked
perfectly. Nothing else about the drag was ever wrong. Dragging by finger now works on both
tabs.

⚠️ **WP Rocket was holding this plugin's script until the visitor's first interaction.**
That is why the "See bag size" button did not appear until the page was scrolled. The guards
already on the tag — `data-no-optimize`, `data-no-minify`, `data-no-defer`, `data-cfasync` —
cover minification, combination and deferral, but **"Delay JavaScript Execution" is a
separate feature and none of them covers it.** Confirmed in the live HTML, where the tag
really did read `type="text/rocketlazyloadscript"`. The plugin now excludes itself through
`rocket_delay_js_exclusions`, so nothing needs setting in the WP Rocket dashboard. There is
also a CSS safety net: if nothing has claimed the button within three seconds it shows
itself where it stands, so a script that never runs can no longer mean "there is no button".

**On a phone the edge-on strip stands down and the lettering holds a floor.** The strip cost
a quarter of the width for something the caption under the bag and the DEEP box already say
in words, and that quarter came out of everything else. Dropping it where the panel is
narrow makes the drawing **58% bigger** — a 27 cm bag goes from 118 px to 187 px on a 390 px
phone — and captions, which are written in millimetres and so shrink with the drawing, are
raised after layout until they clear 12.5 real pixels. On a wide screen nothing changes: the
strip stays and no caption is under the floor. The scale is still identical for every
product on a given screen.

⭐ **Everything in this tab is drawn in a fixed frame, identical on every product.**

The drawing used to be sized to whatever it happened to contain, so the scale changed from
product to product. A small clutch was blown up to fill the panel and a laptop laid over it
looked monstrous, while the very same laptop on a 40 cm tote looked modest. Nothing could
be compared with anything, and — worse — a small bag did not look small, which is the one
thing this module exists to convey.

The frame is now a constant **42 × 30 cm**, and every bag and every object is drawn true to
size inside it. A clutch occupies a small part of the frame, a tote nearly all of it, and a
MacBook is exactly the same size on every page in the shop. The reference is a little larger
than the largest bag (40 × 28 cm) and comfortably larger than the largest object in the
tray (30.4 × 21.5 cm). If a bigger bag is ever added the frame grows to hold it, so that one
product draws at its own scale rather than being clipped.

Verified across 4 bags × 5 objects: **one single scene box for all 20 combinations**, and
nothing drawn outside it.

⚠️ **Dragging by finger did nothing at all, on either tab, while a mouse worked
perfectly.** The move and release listeners were attached to the SVG element being
dragged, which only works if `setPointerCapture` takes — and capture on an SVG element is
not dependable in mobile browsers. Without it the finger leaves the shape within a few
pixels and no further event ever reaches the object. They now listen on the **window** for
the duration of a drag, so the pointer is followed wherever it goes, and they unbind
themselves on release. A tap kept working throughout, which is what made this look like
"drag does nothing" rather than "no events at all".

Objects also carry an **invisible grab pad** sized to the finger. Only the drawn artwork
used to be touchable, and a lipstick is a few millimetres of it — no target at all on a
phone.

⚠️ **The panel was measured from the bag alone.** A MacBook against a 24 × 14 cm bag is
wider and taller than the whole scene used to be, so it covered the panel edge to edge and
hid the very bag it was being compared against — nothing was readable. The scene now makes
room for whatever is being tried: anything that overhangs buys its own margin and both are
drawn inside it, which is exactly the picture "too big for this bag" is meant to paint. A
thing that overhangs is also drawn see-through at rest, and the bag's outline and labels
are drawn last, over the top, so neither can be buried.

**Pick something up and it goes see-through** while you carry it, so the bag underneath
stays readable while the two are lined up — on a phone, where her own finger covers the
object, that is the only way to see what is under it at all. It goes solid again the
moment she lets go, because the verdict is read at rest and a permanently ghosted phone
looks like a rendering fault. The bag's true-scale edge is also **redrawn on top of the
object**: a MacBook or a diary is large enough to rub out the very line she is judging
against, and an edge you cannot see is an edge you cannot use.

**The bag is drawn twice: face on, and from the side.** Thickness is the one dimension a
face-on drawing cannot show, so it gets its own narrow strip beside the bag with the item's
own profile in it. An item too thick to go in visibly breaks out of that strip instead of
sitting neatly inside the outline while being refused for reasons the customer cannot see.

⚠️ **The MacBook had its width and height the wrong way round** — 215 × 304 rather
than 304 × 215 — so a laptop was being drawn taller than it is wide, which is the one
shape a laptop never has. The verdicts were unaffected, because turning it tests both ways
round either way, but on screen it read as a tablet. Now landscape, with the hinge drawn
dark along the back edge: closed and seen from above, that gap is the only thing that says
the object opens.

**Eleven objects, at their real published sizes** — iPhone 16 Pro Max (77.6 × 163 ×
8.3 mm), iPad Pro 11″, MacBook Air 13″ (304.1 × 215 × 11.3 mm), a 500 ml bottle, a folding umbrella, sunglasses,
card holder, keys, 30 ml perfume, lipstick and a compact mirror. Kept short on purpose: a
long tray of near-identical objects is noise.

Each is **drawn as itself** — the phone has its camera island, the bottle has a cap and a
label, the card holder is leather with a card showing. A tray of identical rounded
rectangles is what made the old version feel dead.

**A quarter turn, and nothing laid on its side.** Turn it with the handle that appears on
the object when you hover it — on a phone, where there is no hover, the handle simply stays
visible — or with the button in the tray, or by pressing R. It swings rather than jumps,
and **pivots on the object's own centre**, so it turns where it sits instead of sliding
sideways. Anyone who has asked their system for less motion gets the turn without the
swing.

 The object keeps its own colours whether
it fits or not; what changes is the box around it — solid green when it goes in, red and
dashed when it does not. Depth is judged separately and called out on its own, so a 500 ml
bottle is refused by a 5 cm clutch for the right reason rather than "fitting" on paper.

## All 19 bags are real photographs

Your own product shots, backgrounds removed, shipped inside the plugin. Nothing to
upload and nothing to configure — the plugin matches a product to its photograph by
slug.

**How the scale is kept honest.** A product photo includes the strap; your measurements
do not. Scaling a whole photograph to "27 × 22 cm" would draw every bag with a shoulder
strap far too small. So each photograph also carries a **body box** — where the bag body
sits inside its own image — and the plugin scales until *that* matches the real
centimetres, letting the strap fall outside it.

Finding those boxes by eye would have meant nineteen judgement calls. Instead each one
was found by searching the silhouette for the rectangle whose width-to-height ratio
equals **that product's own measured ratio**. Two things follow: the box can never be
the wrong shape, so the photograph is placed with no distortion whatsoever; and the
search is driven by your measurements rather than by a rule of thumb. Guessing the body
from the silhouette alone had failed — a chain handle is exactly as wide as the bag it
hangs from.

To replace a photo later, drop a new cut-out in `assets/bags/` and re-run
`tools/make-cutouts.py`, or override a single product with `cutout=` /
`_kt_size_cutout`.

**Versus Coach.** Their widget is **Tangiblee**, a paid third-party service. We now match
it on the substance: real bag photography, a figure at your own height, drag between
carry positions, and a "will it fit" spread. They still have one thing we do not — a
photo library of the *objects* in "will it fit"; ours are drawn. We have one thing they
do not: true 1:1 on the glass.

One thing not to copy: their height slider defaults to 162 cm, which is not a useful
default here. Ours starts at 155 cm and is the first thing she touches.

## Install

1. Upload the `kohthai-size-view` folder to `wp-content/plugins/`.
2. Activate **Kohthai Size View**.
3. **Clear the WP Rocket cache.**

That is all — **no shortcode needed.** A **See bag size** button appears by itself on
every product page, in the bottom-right corner of the product photo, and opens the size
view in a window over the page. Nineteen products would otherwise have been nineteen
edits, and the twentieth would have been forgotten.

The button is anchored to **`.woocommerce-product-gallery`** — the same element Flatsome
positions its own New and Sale badges against, so it sits on the photo and lines up with
them. Not `.product-gallery`, which is the whole column including the thumbnail strip.

**Why bottom right and not top right like Coach.** Measured on your own product page:
your sale badge sits top left, your zoom button sits bottom left, and Flatsome puts
hover tools top right — which is exactly where Coach's button goes. Bottom right is the
one free corner. It is a setting, so you can move it.

The button is added after the gallery and then lifted into it by the script, rather than
being injected into Flatsome's markup. If a theme update ever changes that markup the
button simply stays under the photo instead of vanishing.

You can still place it by hand anywhere with `[kt_size_view]` — set Placement to
"Nowhere" first, or you will get both.

## Settings — WooCommerce → Size View

**Placement** — on the product photo (default), under the photo, or nowhere if you would
rather place the shortcode yourself. **Corner** — which corner the button sits in.

Below that, every product is listed so you can replace any bag with your own photograph.

**Upload the PNG as it is.** Transparent background, handle and strap included, no
cropping and no resizing. When you save, the plugin opens the image and works out where
the bag *body* sits inside it, then scales that to the real centimetres — so the bag is
drawn true to size and the handle falls outside the measurement, exactly as it should.

It finds the body the same way the shipped photographs were prepared: by searching for
the rectangle whose width-to-height ratio matches **that product's own measured ratio**.
So it cannot land on a box of the wrong shape, and the photo is never distorted. The
measured box is drawn in red over the thumbnail so you can see what it decided; if one is
wrong you can type the four numbers yourself, or clear the field and save to have it
measured again. There is a "measure everything again" checkbox at the bottom.

Each row also shows the size being used and where it came from, so a product with no
usable dimensions is obvious — those render nothing at all.

⚠️ This needs PHP's **GD** image library, which nearly every host has. If it is missing
the screen says so and you can still upload photographs and enter the box by hand.

## Options

```
[kt_size_view]                                     inline block, on a product page
[kt_size_view mode="modal"]                        a button that opens the window
[kt_size_view hair="bun"]                          ponytail (default) | bun | down
[kt_size_view across="27" tall="22" deep="8"]      anywhere, e.g. a landing page
[kt_size_view id="2193"]                           a specific product
[kt_size_view shape="tote"]                        override the silhouette
[kt_size_view cutout="https://…/bag.png" cutout_body="0.08,0.3,0.84,0.62"]
                                                   override the photograph
[kt_size_view title="…" intro="…"]                 different wording
```

`shape` is one of `tote · shoulder · crossbody · handbag · clutch`. Left alone it is
guessed from the product title and category, and it decides how the bag is carried —
a crossbody strap goes over the opposite shoulder, a tote hangs from the hand.

## Where the sizes come from

1. Attributes you pass in the shortcode.
2. **The product's own `[kt_details]` block** — the page is the source of truth, as
   you said.
3. WooCommerce's dimension fields, last.

Step 3 protects itself. Since WooCommerce's middle field is labelled Width but holds
depth, and many products had depth and height entered the other way round, the plugin
applies one rule: **a bag is never deeper than it is tall.** If the stored numbers say
otherwise it swaps them. So the module is right whether or not the catalogue is —
though fixing the fields is still worth doing for Google Shopping.

⚠️ **A product with no usable size renders nothing at all.** No placeholder, no
guess. A wrong size drawn confidently is worse than no drawing, and it is the exact
mistake this plugin exists to prevent.

## What it never claims

- The drawings are the **bag body**. Straps and handles are faint and excluded, and
  the module says so on the page.
- A bag whose width is a range is drawn at its widest, and labelled as soft.
- "Will it fit" says soft bags stretch, so borderline items are borderline.
- No returns promise, no warranty language, anywhere. (Kohthai bags carry neither —
  see `reference_kohthai_returns_policy`.)

## Performance and caching

CSS and JS are inline, printed once, and **only on pages where the block actually
rendered**. No extra requests, no library.

The `data-no-optimize` / `data-no-minify` / `data-no-defer` / `data-cfasync` guards on
those tags are deliberate and there is a test that fails if they are removed. The
module draws itself in JavaScript, so if WP Rocket defers or delays that script the
customer sees an empty panel where the picture should be.

The stylesheet is printed in the **head** on product pages, not the footer. In the footer
it arrived after the browser had already painted the button, which flashed up full-width
and unstyled before snapping into place. The button also stays invisible until the script
has put it in the corner, so it is never seen mid-jump.

The whole thing degrades safely: with JavaScript off, or before the script runs, the
Across / Tall / Deep figures are already in the HTML.

## Browser storage

Her height and her screen calibration live in `localStorage` on her own device.
Nothing is uploaded and nothing reaches the server. Both reads and writes are wrapped,
so a browser with storage blocked simply shows the defaults rather than breaking —
which is not theoretical, the preview pane blocks it and the module still worked.

---

**Tests: 184 passed, 0 failed** — including a check that the PHP measurement built into the
settings screen agrees with the script that prepared the shipped photographs (worst corner
0.028 of the image), so a bag you upload is treated exactly like one that shipped. See `kohthai-size-view/test-kohthai-size-view.php`, run on the local
WP + WooCommerce 10.9.4 bench. Lint clean. Verified in-browser at desktop and at
375 px mobile.

---

## Version history

The plugin sat at 1.0.0 through a dozen rounds of changes, which meant the plugins list
could not tell you which build was live. Fixed, and there is now a test that fails if the
header comment and the code's own constant ever disagree again. The running version is
also stamped into the stylesheet, so you can confirm what is live from view-source without
opening a file, and it is printed beside the heading on the settings screen.

| Version | What changed |
|---|---|
| **1.16.1** | ⚠️ Boxes drawn by hand were thrown away on save, and would not have reached the product page even if they had been kept |
| 1.16.0 | ⭐ The bag body can be drawn by hand on the settings screen — drag a box on the photograph instead of typing four decimal fractions |
| 1.15.1 | The bag photo no longer runs off the top of the panel and gets sliced — worst on a phone, invisible on a desktop |
| 1.15.0 | ⭐ Finger dragging finally works — `touch-action` was on the wrong element; WP Rocket was holding the script until the first interaction, which is why the button appeared late; the phone layout drops the edge-on strip and holds the lettering to a readable size |
| 1.14.0 | ⭐ The fitting view is drawn in a FIXED frame, so every product in the shop draws at the same scale and a small bag finally looks small |
| 1.13.0 | Dragging by finger worked nowhere on a phone — fixed; the panel now makes room for anything bigger than the bag, instead of letting it cover the bag completely |
| 1.12.0 | The object goes see-through while it is being carried, and the bag's own edge is redrawn above it, so nothing large can rub out the line she is lining it up against; MacBook was being drawn portrait and looked like a tablet — now landscape, with a hinge |
| 1.11.0 | Actual size tab removed; the bag is now also drawn from the side, so thickness can be seen rather than only stated |
| 1.10.0 | The Actual size tab now appears only on screens physically wide enough to show that bag life-size — which is never a phone |
| 1.9.1 | Bottle and umbrella sizes checked against published ranges and corrected |
| 1.9.0 | Turning pivots on the object's own centre and is animated; the artwork turns through a full circle rather than flipping between two states; the outline is dropped when the item fits |
| 1.8.1 | Sunglasses redrawn as a proper framed pair; a turn handle now rides on the object itself, appearing on hover |
| 1.8.0 | Eleven named objects at their real published sizes, drawn in their own colours; a quarter turn only, nothing laid on its side |
| 1.7.0 | "Will it fit" rebuilt as a real fitting test — pick an object, drag it onto the bag, turn it, and get a verdict for that exact orientation |
| 1.6.1 | Tab buttons no longer pushed to the top of their pill by the theme's button margin; every bag now opens in her hand; carry labels given a halo so they stay readable over the figure |
| 1.6.0 | Bag now hangs from her shoulder or hand instead of floating; empty carry positions are labelled; tabs centred; the mouse wheel no longer reaches the page behind the window |
| 1.5.0 | Window moved to `<body>` so Flickity's transform can no longer trap it; stylesheet moved to the head to stop the button flashing; sliders stood vertically, which gave the figure ~110px of height back |
| 1.4.0 | Button anchored to the photo rather than the whole gallery column; every bag shape now offers a carry position on both sides; window sizes itself to the screen instead of scrolling; page properly pinned behind it on phones |
| 1.3.0 | "See bag size" button and the window it opens; placement and corner settings; no shortcode needed |
| 1.2.0 | Fixed frame with the figure growing inside it; figure rebuilt to the reference — contrapposto stance, correct waist-to-hip ratio, blank face, hair as a cap with one ponytail |
| 1.1.0 | Settings screen with per-product photograph upload and automatic body-box measurement; build slider |
| 1.0.0 | First build: On you / Will it fit / Actual size, all 19 bags as real cut-outs, drag between carry positions |

**Bump the version on every change from here.** WordPress reads the header comment, the
code reads the constant, and both are checked against each other by the harness.
