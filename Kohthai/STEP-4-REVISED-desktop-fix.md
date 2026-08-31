# Step 4 — revised. Fixes the desktop layout.

You were right about desktop. **Replace the `.kt-*` CSS from Step 4 with the block below**, and
restructure the Description field as shown. Mobile barely changes (it already stacked); desktop
changes completely.

## Why it looked bad

The accordion content area is **1,050px wide**. In the first version the lead paragraph was
capped at 62ch but everything under it — the spec grid, Materials & Care, and the video — ran the
full 1,050px. So you got a narrow paragraph over a very wide, sparse grid with big empty gaps
between an icon and the next item. **No fashion brand runs body content at 1,050px.** Charles &
Keith, COS, Massimo Dutti and Zara all keep product detail in a narrow column.

Measured before and after, at 1440px:

| | Before | After |
|---|---|---|
| Lead paragraph | 62ch, then everything else 1,050px | 539px |
| Video | 976 × 549 | **640 × 360** |
| Size &amp; Fit | full width, 4 across | 460px column, 2 across |
| Materials &amp; Care | full width, 4 across | 460px column beside it |
| Shared left edge | no | **yes — everything at x=224** |

---

## 4a (revised) — replace the CSS

```css
/* ---------- feature row (short description) — UNCHANGED from Step 4 ---------- */
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

/* ---------- product details — THIS PART IS NEW ---------- */
.kt-lead{ font-size:15.5px; line-height:1.75; color:#4a4038; margin:0 0 26px; max-width:60ch; }

/* The video no longer stretches to the full 1050px container. */
.kt-media{ max-width:640px; margin:0 0 38px; }

/* Size & Fit and Materials & Care sit side by side on desktop,
   and collapse to one column on anything narrower than ~660px. */
.kt-cols{
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 40px 56px;
	align-items: start;
}

.kt-h{
	font-size: 11px;
	font-weight: 700;
	letter-spacing: .14em;
	text-transform: uppercase;
	color: #654321;
	margin: 0 0 16px;
	padding-bottom: 9px;
	border-bottom: 1px solid #e8e2d9;
}

.kt-spec{ display:grid; grid-template-columns:repeat(2,1fr); gap:18px 14px; margin:0 0 20px; }
.kt-spec div{ display:flex; align-items:center; gap:11px; }
.kt-spec svg{ width:22px; height:22px; color:#8a7358; flex:none; }
.kt-spec b{ display:block; font-size:15px; color:#1f1a15; line-height:1.2; }
.kt-spec em{ font-style:normal; font-size:11px; color:#6f6459; letter-spacing:.04em; }

.kt-notes{ margin:0; padding-left:18px; font-size:13.5px; line-height:1.7; color:#4a4038; }
.kt-notes li{ margin:0 0 5px; }

.kt-fits{ display:flex; gap:30px; margin:20px 0 0; }
.kt-fits div{ display:flex; flex-direction:column; align-items:center; gap:7px; font-size:11px; color:#6f6459; }
.kt-fits .dev{ width:26px; height:26px; color:#8a7358; }
.kt-fits .mk{ width:17px; height:17px; }

.kt-care{ display:grid; grid-template-columns:1fr; gap:17px; }
.kt-care > div{ display:flex; gap:12px; align-items:flex-start; }
.kt-care svg{ width:20px; height:20px; color:#8a7358; flex:none; margin-top:2px; }
.kt-care b{ display:block; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#1f1a15; margin-bottom:3px; }
.kt-care span span{ font-size:13px; color:#6f6459; line-height:1.55; }
```

---

## 4c (revised) — restructure the Description field

Only three things change from what you pasted:

1. Wrap the `[ux_video]` in `<div class="kt-media">…</div>`
2. Open `<div class="kt-cols">` before Size &amp; Fit
3. Put each heading + its content in its own `<div>`, and close `.kt-cols` at the end

Still **no blank lines**.

```html
<p class="kt-lead">Effortlessly upgrade your everyday wardrobe with this versatile soft PU leather shoulder bag — an urban-simplicity design in buttery-soft leather with subtle sewing-thread detailing, made to carry from a morning commute to an evening out.</p>
<div class="kt-media">[ux_video url="https://www.youtube.com/watch?v=bUSme6zuVwQ"]</div>
<div class="kt-cols">
<div>
<h4 class="kt-h">Size &amp; Fit</h4>
<div class="kt-spec"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M5.5 9v6M18.5 9v6"/></svg><span><b>27 cm</b><em>Length</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M9 5.5h6M9 18.5h6"/></svg><span><b>22 cm</b><em>Height</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5.5 4 12l3 6.5"/><path d="M17 5.5 20 12l-3 6.5"/><path d="M5 12h14"/></svg><span><b>8 cm</b><em>Width</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 8.5h13l-1.2 11.5H6.7z"/><path d="M9 8.5V6.4a3 3 0 0 1 6 0v2.1"/></svg><span><b>490 g</b><em>Weight</em></span></div></div>
<ul class="kt-notes"><li>Fits a phone, wallet, makeup and your daily essentials</li><li>Size and weight may vary slightly with the material</li></ul>
<div class="kt-fits"><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 5h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#2f6b42" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.3l2.6 2.6L16 9.5"/></svg><span>Phone</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="4.5" y="3" width="15" height="18" rx="2"/><path d="M10.5 18h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Tablet</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="11" rx="1.6"/><path d="M2 19h20"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Laptop</span></div></div>
</div>
<div>
<h4 class="kt-h">Materials &amp; Care</h4>
<div class="kt-care"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5 3.5 8 12 12.5 20.5 8z"/><path d="M3.5 12 12 16.5 20.5 12"/><path d="M3.5 16 12 20.5 20.5 16"/></svg><span><b>Material</b><span>PU leather</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5s6 6.4 6 10a6 6 0 0 1-12 0c0-3.6 6-10 6-10z"/></svg><span><b>Care</b><span>Wipe with a dry or slightly damp cloth</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/></svg><span><b>Avoid</b><span>Direct sunlight and heat</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M4 7.5h16v11a1.6 1.6 0 0 1-1.6 1.5H5.6A1.6 1.6 0 0 1 4 18.5z"/><path d="M4 7.5 6 4h12l2 3.5"/><path d="M10 11h4"/></svg><span><b>Storage</b><span>Keep in the dust bag when not in use</span></span></div></div>
</div>
</div>
```

---

## ⚠️ The video is the weak link, and no CSS can fix it

Your video file is **16:9, with a vertical clip letterboxed inside it**. Roughly 60% of every
frame is black bars. At 976px wide that was very obvious; at 640px it's less so, but the bag
still only occupies a narrow strip in the middle.

Two real fixes, both content-side:

- **Re-export the video as true 16:9** — crop or reframe the footage so it fills the frame. Best
  option for the website.
- **Or shoot/keep it vertical and publish as a YouTube Short**, then we give it a 9:16 container.
  Better if you're reusing the same clip for Reels and TikTok.

This would improve the page more than any further layout change. Right now the single most
persuasive asset you have is displaying at about 40% of its frame.

## Honest note on how I checked this

The browser screenshots stopped working reliably partway through, so **this revision was verified
by measuring element positions, not by looking at it.** The numbers say it's right — one shared
left edge, no stretched grids, video constrained. But please look at it yourself before rolling it
out to other products, and tell me if anything reads wrong.
