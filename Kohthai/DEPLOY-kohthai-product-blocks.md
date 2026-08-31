# Deploy — Kohthai Product Blocks v1.0.0

Upload `kohthai-product-blocks.zip` → **Plugins → Add New → Upload Plugin**, activate, clear
WP Rocket. Reference and the full icon list live at **Settings → Product Blocks**.

**One shortcode only.** After the eleven-feature bundle that broke the page, this ships with
`[kt_features]` and nothing else. The trust row, price block and the rest follow once you've
confirmed this one works.

---

## Replacing your feature row

In the **product short description**, delete the whole `[ux_stack]…[/ux_stack]` block of
`[featured_box]` shortcodes and put this in its place:

```
[kt_features items="adjustable-strap, inner-pocket, outer-pocket, optional-strap, zipper"]
```

Forty-odd lines become one.

**Custom wording** where a bag differs — a pipe after the key overrides the label:

```
[kt_features items="adjustable-strap|Extra Long Strap, zipper"]
```

**Ten icons available:** `adjustable-strap` · `optional-strap` · `inner-pocket` · `outer-pocket` ·
`zipper` · `magnetic-flap` · `chain-strap` · `water-resistant` · `expandable` · `lightweight`.
The settings screen shows each one drawn, with its key in a click-to-select box.

An unknown key is **skipped**, not rendered as a blank slot — a typo can never leave a hole in
the row on a live product.

---

## What this actually fixes

**No more empty headings.** `title="<h5>Zipper</h5>"` made Flatsome emit an empty `<h5>` before
each real one — **18 of them on one product**, turning your heading outline into H1 followed by
twenty-three H5s. This shortcode emits no headings at all. That closes the finding at the source,
which is the only safe place to close it: the last attempt to strip them afterwards corrupted the
document and broke the live page.

**No more `wpautop` debris.** No blank lines, so no `<p></p>`, so nothing for a cleanup filter to
be tempted by.

**Five fewer image requests per product**, and no lazy-load flash — the PNGs were loading at
~300px to be displayed at 24px.

**You can delete the `.kt-feat` rules** from Additional CSS. The plugin ships its own styles.

---

## The icon system

24px grid, 1.5 stroke, round caps and joins, no fills. Every bag icon is built from the **same
body path**, so the row reads as one family rather than assorted clip-art.

The pair that matters: **Inner Pocket is dashed, Outer Pocket is solid** — inside versus outside.
At 24px your old PNG pair both just read as "a bag", which is why they looked identical in the
screenshots.

If you ever add an icon, keep the shared body path and the 1.5 stroke or the row stops looking
related. The `<svg>` wrapper is defined in one place in the code precisely so an icon can't drift.

---

## Tests

```bash
php test-kohthai-product-blocks.php
```

**83 assertions, 0 failures.** Beyond the obvious, it asserts the three things this plugin exists
to prevent — that the output contains **no headings, no paragraphs, no `<br>`, and no `<img>`** —
plus that every icon carries no `<svg>` of its own and sets no fill or stroke-width (which would
break the family look), that inner/outer pocket stay dashed-vs-solid on the same body path, that
a label containing `<script>` is escaped, and that five 76px items plus gaps still fit the 459px
summary column while the old 88px basis did not.

---

## After deploying

1. Clear WP Rocket.
2. Put the shortcode on **one** product, remove that product's old `[featured_box]` block.
3. Check on a phone — five icons on one row, or 4+1 with the leftover centred.
4. If it looks right, work through the rest of the products.

Then tell me and I'll build the next block — the trust row is the obvious one, and it goes in
Flatsome's **HTML after Add To Cart button** field beside your chat shortcode.
