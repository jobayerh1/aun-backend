# Step 4 — move the tabs below the button, and make it beautiful

Do this on **one product first** (Retro Artistic Embroidered Crossbody). Nothing here is code —
it's CSS in the Customizer plus content in two fields. Fully reversible: keep a copy of your old
short description before you paste over it.

**Measured result on a 390×844 phone:**

| | Before | After |
|---|---|---|
| Colour swatches | 1,275px | 906px |
| **ADD TO CART** | **1,561px** | **1,192px** |
| Screens to the button | 1.85 | **1.41** |
| Page height | 4,003px | 3,634px |

**369px closer to the button**, and nothing was removed — it all moved below, into the accordion
that's already open by default.

---

## 4a — the CSS

Append to **Appearance → Customize → Additional CSS**.

```css
/* ---------- feature row (short description) ---------- */
.kt-feat{
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(88px, 1fr));
	gap: 16px 8px;
	padding: 18px 0;
	margin: 0;
	border-top: 1px solid #e8e2d9;
	border-bottom: 1px solid #e8e2d9;
}
.kt-feat span{ display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center; }
.kt-feat svg{ width:24px; height:24px; color:#8a7358; flex:none; }
.kt-feat b{ font-size:10.5px; line-height:1.35; letter-spacing:.03em; color:#4a4038; font-weight:600; }

/* ---------- product details (description field) ---------- */
.kt-lead{ font-size:15px; line-height:1.7; color:#4a4038; margin:0 0 22px; max-width:62ch; }

.kt-h{
	font-size: 11px;
	font-weight: 700;
	letter-spacing: .14em;
	text-transform: uppercase;
	color: #654321;
	margin: 30px 0 14px;
	padding-bottom: 8px;
	border-bottom: 1px solid #e8e2d9;
}
.kt-h:first-child{ margin-top:0; }

.kt-spec{
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
	gap: 14px 16px;
	margin: 0 0 16px;
}
.kt-spec div{ display:flex; align-items:center; gap:10px; }
.kt-spec svg{ width:22px; height:22px; color:#8a7358; flex:none; }
.kt-spec b{ display:block; font-size:14px; color:#1f1a15; line-height:1.2; }
.kt-spec em{ font-style:normal; font-size:11px; color:#6f6459; letter-spacing:.04em; }

.kt-notes{ margin:0; padding-left:18px; font-size:13.5px; line-height:1.7; color:#4a4038; }
.kt-notes li{ margin:0 0 4px; }

.kt-fits{ display:flex; gap:26px; margin:18px 0 0; flex-wrap:wrap; }
.kt-fits div{ display:flex; flex-direction:column; align-items:center; gap:7px; font-size:11px; color:#6f6459; }
.kt-fits .dev{ width:26px; height:26px; color:#8a7358; }
.kt-fits .mk{ width:17px; height:17px; }

.kt-care{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; }
.kt-care > div{ display:flex; gap:10px; align-items:flex-start; }
.kt-care svg{ width:20px; height:20px; color:#8a7358; flex:none; margin-top:1px; }
.kt-care b{ display:block; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#1f1a15; margin-bottom:2px; }
.kt-care span span{ font-size:12.5px; color:#6f6459; line-height:1.5; }
```

---

## 4b — Product Short Description

**Replace the entire field with this.** Everything else moves to 4c.

⚠️ **Do not add blank lines inside it.** A blank line makes WordPress insert `<p></p>`, which is
what created the 18 empty headings in the first place.

```html
<div class="kt-feat"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><rect x="8.5" y="8.5" width="7" height="7" rx="1.5"/><path d="M3 9.5v5M21 9.5v5"/></svg><b>Adjustable Strap</b></span><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 9h15l-1 10.5a1.6 1.6 0 0 1-1.6 1.5H7.1a1.6 1.6 0 0 1-1.6-1.5z"/><path d="M8.5 9V6.5a3.5 3.5 0 0 1 7 0V9"/><path d="M9 13.5h6v3.5H9z" stroke-dasharray="2.2 1.6"/></svg><b>Inner Pocket</b></span><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 9h15l-1 10.5a1.6 1.6 0 0 1-1.6 1.5H7.1a1.6 1.6 0 0 1-1.6-1.5z"/><path d="M8.5 9V6.5a3.5 3.5 0 0 1 7 0V9"/><path d="M6.6 14.5h10.8v6.5H6.6z"/></svg><b>Outer Pocket</b></span><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5.2 19.5V11a6.8 6.8 0 0 1 13.6 0v8.5" stroke-dasharray="3 2.2"/><circle cx="5.2" cy="20.8" r="1.7"/><circle cx="18.8" cy="20.8" r="1.7"/></svg><b>Optional Strap</b></span><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9.6 3v7.4M14.4 3v7.4"/><path d="M9.6 5.2H7.4M9.6 8.2H7.4M14.4 5.2h2.2M14.4 8.2h2.2"/><path d="M9.2 10.4h5.6v2.4a2.8 2.8 0 0 1-5.6 0z"/><path d="M12 12.8v4.4"/><circle cx="12" cy="19" r="2"/></svg><b>Zipper</b></span></div>
```

**To reuse on another product:** delete the `<span>…</span>` blocks you don't need, or copy one and
change the `<b>` label. The five available icons are Adjustable Strap, Inner Pocket, Outer Pocket,
Optional Strap and Zipper.

---

## 4c — Product Description

**Paste this into the main Description field**, replacing the lone `[ux_video]`. Again: no blank
lines. Change the copy, the four numbers, and the ✓/✗ marks per product.

```html
<p class="kt-lead">Add a touch of timeless elegance to your collection with this beautifully designed magnetic flap crossbody bag. It blends a classic, vintage-inspired look with modern functionality — roomy enough for your phone, wallet, keys and makeup, and light enough to carry all day.</p>
[ux_video url="https://www.youtube.com/watch?v=bUSme6zuVwQ"]
<h4 class="kt-h">Size &amp; Fit</h4>
<div class="kt-spec"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M5.5 9v6M18.5 9v6"/></svg><span><b>28 cm</b><em>Length</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M9 5.5h6M9 18.5h6"/></svg><span><b>19 cm</b><em>Height</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5.5 4 12l3 6.5"/><path d="M17 5.5 20 12l-3 6.5"/><path d="M5 12h14"/></svg><span><b>11 cm</b><em>Width</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 8.5h13l-1.2 11.5H6.7z"/><path d="M9 8.5V6.4a3 3 0 0 1 6 0v2.1"/></svg><span><b>650 g</b><em>Weight</em></span></div></div>
<ul class="kt-notes"><li>Fits a phone, wallet, makeup and your daily essentials</li><li>Size and weight may vary slightly with the material</li></ul>
<div class="kt-fits"><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 5h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#2f6b42" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.3l2.6 2.6L16 9.5"/></svg><span>Phone</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="4.5" y="3" width="15" height="18" rx="2"/><path d="M10.5 18h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Tablet</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="11" rx="1.6"/><path d="M2 19h20"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Laptop</span></div></div>
<h4 class="kt-h">Materials &amp; Care</h4>
<div class="kt-care"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5 3.5 8 12 12.5 20.5 8z"/><path d="M3.5 12 12 16.5 20.5 12"/><path d="M3.5 16 12 20.5 20.5 16"/></svg><span><b>Material</b><span>PU leather with embroidered flap</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5s6 6.4 6 10a6 6 0 0 1-12 0c0-3.6 6-10 6-10z"/></svg><span><b>Care</b><span>Wipe with a dry or slightly damp cloth</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/></svg><span><b>Avoid</b><span>Direct sunlight and heat</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M4 7.5h16v11a1.6 1.6 0 0 1-1.6 1.5H5.6A1.6 1.6 0 0 1 4 18.5z"/><path d="M4 7.5 6 4h12l2 3.5"/><path d="M10 11h4"/></svg><span><b>Storage</b><span>Keep in the dust bag when not in use</span></span></div></div>
```

---

## What you should see

- **Short description** = one quiet row of five icons between the price and the colour picker.
  3 across then 2 on a phone, all five in a row on desktop.
- **Add to Cart about 370px higher** than before.
- **Below the button**, inside the already-open Description accordion: the copy, the video, a
  Size & Fit spec grid, the phone/tablet/laptop marks, and Materials & Care.
- The section headings pick up your theme's serif, so it looks like the rest of the site.

## What this fixes on the way past

- **The 18 empty `<h5>` tags are gone** — at the source. No `[featured_box]`, no
  `title="<h5>…</h5>"`, so the cause no longer exists. This is the only safe way to fix it.
- **Five fewer image requests per product**, and no lazy-loaded PNGs flashing blank. The icons
  are inline SVG in your brand tone.
- **The giant ✓/✗ problem disappears** — those were images sized `width:50%`; they're now 17px
  inline SVG marks.
- **Step 3b's grid CSS becomes redundant** for this product, because there are no stray `<p>`
  and `<br>` to work around. Leave 3b in place until every product is converted.

## Notes

Editing this per product means pasting a block and changing numbers. That's fine for a handful.
Once you're happy with the design, the same output becomes one line —
`[kt_features items="adjustable-strap, inner-pocket, zipper"]` and
`[kt_size length="28cm" height="19cm" width="11cm" weight="650g"]` — via a small shortcode plugin.
**Get the design right first on one product; the shortcode is just a shorter way to type it.**
