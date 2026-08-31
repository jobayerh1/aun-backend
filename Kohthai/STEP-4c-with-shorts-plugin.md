# Step 4c — Description field, now using the Shorts plugin

Replaces the previous 4c. **Install `kohthai-shorts-showcase` v1.2.0 first**, and set
**Settings → Shorts Showcase → Default video shape = 1:1**.

## What changes

The plugin now owns the video *and* the block beside it. So:

- `[ux_video]` and the `<div class="kt-media">` wrapper both go away
- the plugin renders **video + your three selling points** side by side on desktop, stacked on mobile
- your `.kt-cols` block keeps **Size & Fit** and **Materials & Care** underneath

Three clean bands instead of a stretched column.

---

## 1 · Remove one CSS rule

Delete this from Additional CSS — the plugin sizes the video now:

```css
.kt-media{ max-width:640px; margin:0 0 38px; }   /* DELETE */
```

Everything else from the revised Step 4a stays exactly as it is.

---

## 2 · The Description field

Paste this, replacing what's there. **No blank lines.**

```html
<p class="kt-lead">Add a touch of timeless elegance to your collection with this beautifully designed magnetic flap crossbody bag. It blends a classic, vintage-inspired look with modern functionality — roomy enough for your phone, wallet, keys and makeup, and light enough to carry all day.</p>
[kohthai_shorts ids="bUSme6zuVwQ" title="" icon="false" feature_title="Why you will love it" features="Fits your phone and wallet,Wear on shoulder or crossbody,Light enough for all day"]
<div class="kt-cols">
<div>
<h4 class="kt-h">Size &amp; Fit</h4>
<div class="kt-spec"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M5.5 9v6M18.5 9v6"/></svg><span><b>28 cm</b><em>Length</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"/><path d="M9 5.5h6M9 18.5h6"/></svg><span><b>19 cm</b><em>Height</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 5.5 4 12l3 6.5"/><path d="M17 5.5 20 12l-3 6.5"/><path d="M5 12h14"/></svg><span><b>11 cm</b><em>Width</em></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 8.5h13l-1.2 11.5H6.7z"/><path d="M9 8.5V6.4a3 3 0 0 1 6 0v2.1"/></svg><span><b>650 g</b><em>Weight</em></span></div></div>
<ul class="kt-notes"><li>Fits a phone, wallet, makeup and your daily essentials</li><li>Size and weight may vary slightly with the material</li></ul>
<div class="kt-fits"><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 5h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#2f6b42" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.3l2.6 2.6L16 9.5"/></svg><span>Phone</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="4.5" y="3" width="15" height="18" rx="2"/><path d="M10.5 18h3"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Tablet</span></div><div><svg class="dev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="11" rx="1.6"/><path d="M2 19h20"/></svg><svg class="mk" viewBox="0 0 24 24" fill="none" stroke="#9a3b30" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg><span>Laptop</span></div></div>
</div>
<div>
<h4 class="kt-h">Materials &amp; Care</h4>
<div class="kt-care"><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5 3.5 8 12 12.5 20.5 8z"/><path d="M3.5 12 12 16.5 20.5 12"/><path d="M3.5 16 12 20.5 20.5 16"/></svg><span><b>Material</b><span>PU leather with embroidered flap</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M12 3.5s6 6.4 6 10a6 6 0 0 1-12 0c0-3.6 6-10 6-10z"/></svg><span><b>Care</b><span>Wipe with a dry or slightly damp cloth</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"/></svg><span><b>Avoid</b><span>Direct sunlight and heat</span></span></div><div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"><path d="M4 7.5h16v11a1.6 1.6 0 0 1-1.6 1.5H5.6A1.6 1.6 0 0 1 4 18.5z"/><path d="M4 7.5 6 4h12l2 3.5"/><path d="M10 11h4"/></svg><span><b>Storage</b><span>Keep in the dust bag when not in use</span></span></div></div>
</div>
</div>
```

---

## ⚠️ Two traps in the shortcode

**1. Commas split the features list.** The plugin does `explode(',', features)`, so a point
containing a comma breaks into several. My earlier example was wrong:

```
features="Fits your phone, wallet and makeup, Adjustable strap for shoulder or crossbody, Light enough"
```

That renders as **four** items — "Fits your phone" / "wallet and makeup" / "Adjustable strap…" /
"Light enough". Keep each point short and comma-free, as in the block above.

**2. No `feature_desc` here, on purpose.** The `.kt-lead` paragraph above already carries the
product prose, and having both would say the same thing twice. Long copy is also much easier to
edit as HTML than inside a shortcode attribute, where quotes and `]` cause trouble.

Also: `ratio` is omitted — once the site-wide default is 1:1 you never need it. Add
`ratio="9:16"` only on a product whose video really is tall.

---

## What you should see

**Mobile:** lead paragraph → square video filling its frame → "Why you will love it" with three
ticked points underneath → Size & Fit → Materials & Care.

**Desktop:** lead paragraph → video on the left with the three points beside it in a warm card →
Size & Fit and Materials & Care side by side below.

---

## Two plugin bugs this turned up, both now fixed in v1.2.0

**The feature text was hidden on mobile.** The upstream build had
`.kts-feature-text { display: none !important }` inside `@media (max-width: 768px)`, because those
pages repeated the same points elsewhere. On a shop where nearly all traffic is a phone, that
would have hidden your selling points from almost every customer. It now stacks under the video.

**The video column was pinned to 320px.** `.kts-feature-video { flex: 0 0 320px }` was left over
from when every card was 9:16, so a 380px square card was being squeezed. The basis now follows
the card width.

Both were only reachable once the ratio became configurable — worth knowing if you ever port
this engine again.

---

## Honest note on verification

The rendered markup is verified — I ran the shortcode and confirmed the three points split
correctly, the card comes out `380px` at `aspect-ratio: 1 / 1`, and the wrapper carries the right
variables. **The visual layout is verified by reading the CSS, not by seeing it**, because the
plugin isn't on your site yet and I can't render it there.

So: put it on **one** product first and look at it on your phone before doing the rest.
