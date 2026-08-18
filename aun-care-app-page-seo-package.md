# AUN Care App — page + SEO launch package

Paste-ready content for a **new page** at `/aun-care-app/`, built in the house
info-page template (Flatsome UX Builder shortcodes, brand `#0188fe`).

This is the page every other app mention on the site should link to. Build it first.

---

## 0. Placeholders — search & replace these

Everything you will need to change later is a bracketed token. Nothing else needs editing.

| Token | Replace with | When |
|---|---|---|
| `[PLAY_STORE_URL]` | `https://play.google.com/store/apps/details?id=bd.com.aunprojector.aun_app` | The day the Play listing goes live |
| `[SHOT_01_URL]` … `[SHOT_08_URL]` | Media Library URLs of the 8 Play screenshots | Before publishing |
| `[APP_ICON_URL]` | Media Library URL of `play-icon-512.png` | Before publishing |

**No video placeholder needed** — the promo runs as the live HTML animation via the
`aun-care-promo` plugin (§3a), not as an embedded video.

> The Play button is already written and styled — it sits in the page commented-out
> as `<!-- PLAY BUTTON -->`. On launch day: uncomment it, paste the URL, done.
> No redesign, no rewrite.

---

## 1. Page setup

| Field | Value |
|---|---|
| Page title | AUN Care App |
| **Slug** | `aun-care-app` → `https://aun-projector.com.bd/aun-care-app/` |
| Template | Default / full width (no sidebar) |
| Parent | none (top level — it is a destination page) |

> Slug reasoning: `aun-care-app` carries the brand *and* the category word. Avoid
> `/app/` (too generic, and you already use `/wp-content/uploads/app/` for the APK).

---

## 2. RankMath fields

**SEO title** (≤60 chars)
```
AUN Care App — Projector Warranty & Repair Tracking BD
```

**Meta description** (≤160 chars)
```
Free Android app for AUN projector owners in Bangladesh. Check warranty, order spare parts, book repairs and track everything — in Bangla. Download free.
```

**Focus keyword**
```
AUN Care app
```

**Secondary keywords**
```
projector warranty app bangladesh
projector service app
AUN projector app download
projector repair tracking bangladesh
```

**Why these:** brand terms are winnable immediately (nobody competes for "AUN Care").
The category terms have low volume but *zero* specialist competition in BD — the
same long-tail profile that wins fast on your product pages.

**Social/OG image:** `animation\playstore\play-feature-graphic-1024x500.png` — it is
already designed as a 1024×500 banner, which is almost exactly the OG ratio.

---

## 3. Upload these first

Upload to the Media Library, then collect the URLs for the placeholders above.

| File (in `animation\playstore\`) | Used for |
|---|---|
| `01-everything-after-you-buy.png` → `08-most-brands-sell-a-box.png` | The screenshot rail (§4) |
| `play-icon-512.png` | Hero app icon |
| `play-feature-graphic-1024x500.png` | RankMath OG/social image |

---

## 3a. Install the promo animation plugin

The promo on this page is **the original HTML animation, running live** — not the
encoded MP4. It is sharper (vector text, real CSS lighting, no compression), it is
**796 KB instead of an 11 MB video**, and it adapts its own frame to the device.

1. Zip the `aun-care-promo\` folder and install it via **Plugins → Add New → Upload**,
   or upload the folder to `wp-content/plugins/`.
2. Activate **AUN Care Promo Animation**.
3. The page content below already contains the shortcode — nothing else to configure.

**Shortcode reference**

```
[aun_care_promo ratio="auto" mood="night" lang="auto" max="1040" caption="no"]
```

| Attribute | Values | Default | Notes |
|---|---|---|---|
| `ratio` | `auto` · `1:1` · `9:16` · `16:9` | `auto` | **auto** = 16:9 desktop (≥900px), 1:1 tablet (600–899), 9:16 phone (<600). Re-frames on resize. |
| `mood` | `night` · `day` · `auto` | `night` | `auto` follows the visitor's OS light/dark setting. |
| `lang` | `en` · `bn` · `auto` | `auto` | `auto` reads the site locale. |
| `max` | px | `1040` | Max width of the 16:9 frame. |
| `caption` | `yes` · `no` | `no` | The small "20-second loop" line under the frame. |

**Built in:** the loop pauses when scrolled off screen (battery), honours
`prefers-reduced-motion` by holding one readable frame instead of animating, uses
lazily-loaded images, and is excluded from WP Rocket's JS defer/delay — it measures
real element heights on load, so deferring it would mis-size the phone screen.

> Still upload `animation\video\aun-care-16x9-night.mp4` to **YouTube** separately —
> the Play Console listing needs a YouTube URL, and it is worth having for social.
> Public, embedding allowed, monetization off, custom thumbnail from
> `thumb-youtube-1280x720.png`. It just is not what this page uses.

---

## 4. Page content — paste into UX Builder

```
[section bg_color="rgb(9, 15, 28)" dark="true" padding="60px" padding__sm="40px" label="App Hero"]

[ux_text text_align="center"]
<img src="[APP_ICON_URL]" alt="AUN Care app icon" width="88" height="88" style="border-radius:20px;box-shadow:0 10px 30px rgba(1,136,254,.35);display:block;margin:0 auto 18px;" />
<span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(1,136,254,0.14);border:1px solid rgba(1,136,254,0.35);font-weight:800;font-size:12px;color:#7fc0ff;text-transform:uppercase;letter-spacing:.5px;"><i class="fa-brands fa-android"></i> Free Android App</span>
<h1 style="margin:16px 0 10px;color:#fff;line-height:1.15;">AUN Care &mdash; Everything After You Buy</h1>
<p style="max-width:760px;margin:0 auto;color:#9fb3c8;font-size:16px;line-height:1.7;">Most brands sell you a box. <strong style="color:#fff;">AUN Care</strong> is the free Android app that keeps your projector working for years &mdash; check your warranty, order genuine spare parts, book a repair and follow every step, all from your phone. Bangla first, English when you want it.</p>
[/ux_text]
[gap height="24px"]

[ux_text text_align="center"]
<a href="https://aun-projector.com.bd/get-aun-care-app/" rel="nofollow" style="display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:16px 32px;border-radius:14px;text-decoration:none;color:#fff;font-weight:800;font-size:17px;background:linear-gradient(135deg,#0188fe,#00c6ff);box-shadow:0 14px 30px rgba(1,136,254,.40);"><i class="fa-solid fa-circle-down" style="font-size:22px;"></i> Download the App</a>

<!-- PLAY BUTTON — uncomment on launch day and paste the URL into [PLAY_STORE_URL]
<a href="[PLAY_STORE_URL]" style="display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:15px 28px;margin-left:10px;border-radius:14px;text-decoration:none;color:#fff;font-weight:800;font-size:16px;background:linear-gradient(180deg,#161d2b,#0d131e);border:1px solid rgba(255,255,255,.22);"><i class="fa-brands fa-google-play" style="font-size:20px;color:#3ddc84;"></i> <span style="line-height:1.2;"><span style="display:block;font-size:10px;font-weight:600;color:#c7d3e0;letter-spacing:1px;text-transform:uppercase;">Get it on</span>Google Play</span></a>
-->

<p style="color:#7d92a8;font-size:13px;margin:14px 0 0;">Free &middot; Android 7.0+ &middot; বাংলা &amp; English &middot; No account needed to explore</p>
[/ux_text]

[/section]

[section bg_color="rgb(13, 22, 42)" dark="true" padding="50px" padding__sm="34px" label="Promo Animation"]

[ux_text text_align="center"]
<h2 style="color:#fff;margin-bottom:8px;">See It in Motion</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 18px;opacity:.85;"></div>
[/ux_text]

[aun_care_promo ratio="auto" mood="night" max="1040"]

[/section]

[section bg_color="rgb(255, 255, 255)" padding="46px" padding__sm="32px" label="Screenshot Rail"]

[ux_text text_align="center"]
<h2 style="color:#0f172a;margin-bottom:8px;">A Look Inside the App</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
<p style="color:#64748b;max-width:680px;margin:0 auto;">Swipe to see what you get. Every screen below is the real app &mdash; nothing staged.</p>
[/ux_text]
[gap height="20px"]

[ux_text]
<style data-no-optimize="1" data-no-minify="1">
.aun-shots{display:flex;gap:18px;overflow-x:auto;padding:6px 4px 22px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;}
.aun-shots::-webkit-scrollbar{height:8px}
.aun-shots::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:99px}
.aun-shots::-webkit-scrollbar-track{background:#f1f5f9;border-radius:99px}
.aun-shots img{flex:0 0 auto;width:250px;height:auto;border-radius:16px;scroll-snap-align:center;box-shadow:0 10px 28px rgba(15,23,42,.14);border:1px solid #e2e8f0;background:#0188fe;}
.aun-shots-hint{text-align:center;color:#94a3b8;font-size:13px;margin:0;}
@media(max-width:549px){.aun-shots img{width:200px}}
</style>
<div class="aun-shots">
<img src="[SHOT_01_URL]" alt="AUN Care app home screen showing repair, spare parts and support ticket progress" loading="lazy" width="250" />
<img src="[SHOT_02_URL]" alt="AUN Care warranty screen showing days remaining on an AUN projector" loading="lazy" width="250" />
<img src="[SHOT_03_URL]" alt="Requesting projector spare parts and booking a repair in the AUN Care app" loading="lazy" width="250" />
<img src="[SHOT_04_URL]" alt="Asking a support question inside the AUN Care app" loading="lazy" width="250" />
<img src="[SHOT_05_URL]" alt="Firmware, manuals and videos filtered to your exact AUN projector model" loading="lazy" width="250" />
<img src="[SHOT_06_URL]" alt="Projector Planner showing what screen size fits your room" loading="lazy" width="250" />
<img src="[SHOT_07_URL]" alt="Referral feature giving a friend 5% off an AUN projector" loading="lazy" width="250" />
<img src="[SHOT_08_URL]" alt="AUN Care — most brands sell a box, AUN stays after the sale" loading="lazy" width="250" />
</div>
<p class="aun-shots-hint"><i class="fa-solid fa-arrows-left-right"></i> Swipe to explore</p>
[/ux_text]

[/section]

[section bg_color="rgb(246, 248, 251)" padding="40px" label="What You Can Do"]

[ux_text text_align="center"]
<h2 style="color:#0f172a;margin-bottom:8px;">What You Can Do in the App</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
<p style="color:#64748b;max-width:700px;margin:0 auto;">Register your projector once by scanning the barcode on the box &mdash; then everything that happens afterwards lives in one place.</p>
[/ux_text]
[gap height="18px"]

[row]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-shield-halved"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Your Warranty, Always With You</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">See whether you are still covered and exactly how many days are left &mdash; no hunting for the invoice.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-microchip"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Spare Parts to Your Door</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Request an LCD, motherboard, remote and more &mdash; free under warranty. Get a price, approve it, pay online or cash on delivery, then track every part.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-screwdriver-wrench"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Repairs You Can Follow</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Book a repair and watch it move from received, to repaired, to on its way back &mdash; with courier tracking, without phoning anyone.</p>
</div>
[/col]

[/row]
[gap height="16px"]

[row]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-headset"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Support That Answers In-App</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Open a ticket, attach photos, and get a reply from our own team inside the app &mdash; the whole conversation stays in one thread.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-book-open"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Made for Your Exact Model</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Firmware, manuals and how-to videos filtered to the projector you actually own &mdash; and a notification when new firmware is released for it.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-broom"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Reminders Before the Picture Dims</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Bangladesh is dusty. The app reminds you to clean the dust filter at the right time &mdash; the single biggest cause of a dull picture.</p>
</div>
[/col]

[/row]

[/section]

[section bg_color="rgb(255, 255, 255)" padding="40px" label="Before You Buy"]

[ux_text text_align="center"]
<h2 style="color:#0f172a;margin-bottom:8px;">Useful Even Before You Own a Projector</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
<p style="color:#64748b;max-width:700px;margin:0 auto;">You do not need to own an AUN projector to find the app useful. Three tools help you decide before you spend anything.</p>
[/ux_text]
[gap height="18px"]

[row]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-ruler-combined"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Projector Planner</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">See what screen size fits your room, and how far back the projector needs to sit &mdash; before you buy, not after.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Help Me Choose</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Answer five questions and get a recommendation &mdash; with the reasons written out, so you can judge them yourself.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-cube"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">Preview on Your Wall in AR</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Point your phone at the wall and see how big the picture will actually be in your own room.</p>
</div>
[/col]

[/row]

[/section]

[section bg_color="rgb(246, 248, 251)" padding="40px" label="Getting Started"]

[ux_text text_align="center"]
<h2 style="color:#0f172a;margin-bottom:8px;">How to Get Started</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
<p style="color:#64748b;max-width:660px;margin:0 auto;">Three steps, about two minutes.</p>
[/ux_text]
[gap height="18px"]

[row]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-download"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">1. Download &amp; Install</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Tap <a href="https://aun-projector.com.bd/get-aun-care-app/" style="color:#0188fe;font-weight:700;">Download the App</a>, open the file, and allow the install when Android asks. It takes a few seconds.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-mobile-screen"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">2. Sign In With Your Phone</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Enter your mobile number and the code we text you. No password to create, and none to forget.</p>
</div>
[/col]

[col span="4" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;box-shadow:0 2px 6px rgba(15,23,42,0.04);height:100%;">
<div style="width:48px;height:48px;margin:0 auto 12px;border-radius:14px;background:rgba(1,136,254,0.10);color:#0188fe;display:flex;align-items:center;justify-content:center;font-size:20px;"><i class="fa-solid fa-barcode"></i></div>
<h3 style="font-size:16px;margin:0 0 6px;color:#0f172a;">3. Add Your Projector</h3>
<p style="font-size:14px;color:#64748b;margin:0;line-height:1.6;">Scan the barcode on the box. If you bought directly from us, your purchase is often found automatically by your phone number.</p>
</div>
[/col]

[/row]

[/section]

[section bg_color="rgb(9, 15, 28)" dark="true" padding="50px" label="Why It Matters"]

[ux_text text_align="center"]
<h2 style="color:#fff;margin-bottom:8px;">Why We Built It</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 14px;opacity:.85;"></div>
<p style="color:#9fb3c8;max-width:740px;margin:0 auto;font-size:16px;line-height:1.7;">Buying a projector in Bangladesh usually means the relationship ends at the shop door. A year later something goes wrong, and you are hunting for the invoice, guessing whether you are still covered, and calling around to find out where to send it.<br><br><strong style="color:#fff;">AUN Care removes all of that.</strong> Every repair, every part, every ticket &mdash; visible, tracked and answered. You always know where your request stands, without having to ask anyone.</p>
[/ux_text]
[gap height="22px"]

[ux_text text_align="center"]
<a href="https://aun-projector.com.bd/get-aun-care-app/" rel="nofollow" style="display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:16px 32px;border-radius:14px;text-decoration:none;color:#fff;font-weight:800;font-size:17px;background:linear-gradient(135deg,#0188fe,#00c6ff);box-shadow:0 14px 30px rgba(1,136,254,.40);"><i class="fa-solid fa-circle-down" style="font-size:22px;"></i> Download AUN Care Free</a>
[/ux_text]

[/section]

[section bg_color="rgb(255, 255, 255)" padding="40px" label="App FAQ"]

[ux_text]
<style data-no-optimize="1" data-no-minify="1">
.aun-faq{max-width:820px;margin:0 auto}
.aun-faq details{background:#fff;border:1px solid #e2e8f0;border-left:3px solid transparent;border-radius:10px;margin-bottom:12px;box-shadow:0 2px 5px rgba(0,0,0,0.04);overflow:hidden;transition:border-color .2s ease,box-shadow .2s ease}
.aun-faq details[open]{border-left-color:#ffbc00;box-shadow:0 6px 18px rgba(1,136,254,0.08)}
.aun-faq summary{list-style:none;cursor:pointer;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:14px;font-size:16px;font-weight:700;color:#1e293b;outline:none}
.aun-faq summary::-webkit-details-marker{display:none}
.aun-faq summary:hover{background:#f8fafc}
.aun-faq summary .aun-chev{color:#0188fe;font-size:13px;transition:transform .25s ease;flex-shrink:0}
.aun-faq details[open] summary .aun-chev{transform:rotate(180deg)}
.aun-faq .aun-faq-a{padding:2px 22px 20px;color:#555;line-height:1.65;font-size:14.5px;animation:aunFaqFade .25s ease}
.aun-faq .aun-faq-a a{color:#0188fe}
@keyframes aunFaqFade{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
</style>
<h2 style="color:#0f172a; text-align:center; margin-bottom:10px;">Frequently Asked Questions &mdash; AUN Care App</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 22px;opacity:.85;"></div>
<div class="aun-faq">
<details open><summary><span>Is the AUN Care app free?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes, completely free. There are no ads, no subscription and no in-app purchase to unlock features. You only ever pay for spare parts or an out-of-warranty repair, and only after you approve the price.</div></details>
<details><summary><span>Which phones does it work on?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Any Android phone running <strong>Android 7.0 or newer</strong>, which covers virtually every phone in use in Bangladesh today. There is no iPhone version yet.</div></details>
<details><summary><span>Why is it not on the Google Play Store?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Our Google Play organisation account is still being verified &mdash; that process requires a D-U-N-S business number, which takes time. Until it is approved you can download the app safely and directly from our own website. The app updates itself automatically, so you will always be on the latest version.</div></details>
<details><summary><span>Android warns me about installing from an unknown source. Is that normal?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes, that warning appears for any app installed outside the Play Store, and it is expected here. Tap <strong>Allow</strong> or <strong>Install anyway</strong> to continue. The file is served from our own domain, aun-projector.com.bd.</div></details>
<details><summary><span>Do I need an AUN projector to use the app?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">No. Anyone can use the Projector Planner to work out what screen size suits their room, answer five questions in &ldquo;Help me choose&rdquo; for a recommendation, or preview the picture on their wall in AR. Registering a projector unlocks the warranty, parts and repair features.</div></details>
<details><summary><span>Is the app in Bangla?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes &mdash; Bangla first, with an English toggle in settings. It was built for Bangladeshi customers, not translated as an afterthought.</div></details>
<details><summary><span>How do I check my warranty in the app?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Add your projector by scanning the barcode on the box, then open My Devices. You will see whether you are covered and exactly how many days remain. If you bought directly from us, your purchase is often linked automatically using your phone number. You can also <a href="https://aun-projector.com.bd/warranty-register/">register your warranty on the website</a>.</div></details>
<details><summary><span>What data does the app collect?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Your phone number to sign in, plus whatever you choose to add &mdash; name, email, your projector serial and any service requests. The app asks for only three permissions: internet, camera (to scan the barcode and attach photos) and notifications. <strong>It never asks for your location.</strong> Full details are in our <a href="https://aun-projector.com.bd/app-privacy-policy/">app privacy policy</a>.</div></details>
<details><summary><span>Can I delete my account and data?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes. Go to Settings &rarr; Delete my account inside the app, or use the <a href="https://aun-projector.com.bd/delete-account/">account deletion page</a>. You will see exactly what is removed before you confirm.</div></details>
<details><summary><span>Do I still need to phone or WhatsApp you?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Not for the everyday things &mdash; warranty checks, parts, repairs and support tickets all work inside the app, and you can see the status any time without asking. You are always welcome to <a href="https://aun-projector.com.bd/contact-us/">call or message us</a> if you would rather talk to a person.</div></details>
</div>
[/ux_text]

[/section]

[section bg_color="rgb(246, 248, 251)" padding="40px" label="Ecosystem Cross Links"]

[ux_text text_align="center"]
<h2 style="color:#0f172a;margin-bottom:8px;">Explore the AUN After-Sales Ecosystem</h2>
<div style="width:60px;height:3px;background:#0188fe;border-radius:2px;margin:0 auto 10px;opacity:.85;"></div>
<p style="color:#64748b;max-width:680px;margin:0 auto 6px;">Everything the app does is also available on the website &mdash; use whichever suits you.</p>
[/ux_text]
[gap height="16px"]

[row]

[col span="3" span__sm="6" align="center"]
<a href="https://aun-projector.com.bd/warranty-register/" style="text-decoration:none; display:block; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 2px 6px rgba(0,0,0,0.04);"><span style="font-size:30px; color:#0188fe; display:block;"><i class="fa-solid fa-shield-halved"></i></span><span style="font-size:16px; color:#0f172a; font-weight:700; display:block; margin:10px 0 6px;">Register Warranty</span><span style="font-size:13px; color:#777; display:block;">Activate your 1-year warranty online.</span></a>
[/col]

[col span="3" span__sm="6" align="center"]
<a href="https://aun-projector.com.bd/spare-parts/" style="text-decoration:none; display:block; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 2px 6px rgba(0,0,0,0.04);"><span style="font-size:30px; color:#0188fe; display:block;"><i class="fa-solid fa-microchip"></i></span><span style="font-size:16px; color:#0f172a; font-weight:700; display:block; margin:10px 0 6px;">Spare Parts</span><span style="font-size:13px; color:#777; display:block;">Order genuine parts without sending your projector.</span></a>
[/col]

[col span="3" span__sm="6" align="center"]
<a href="https://aun-projector.com.bd/repair-status/" style="text-decoration:none; display:block; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 2px 6px rgba(0,0,0,0.04);"><span style="font-size:30px; color:#0188fe; display:block;"><i class="fa-solid fa-screwdriver-wrench"></i></span><span style="font-size:16px; color:#0f172a; font-weight:700; display:block; margin:10px 0 6px;">Repair Status</span><span style="font-size:13px; color:#777; display:block;">Track a projector that is with our service centre.</span></a>
[/col]

[col span="3" span__sm="6" align="center"]
<a href="https://aun-projector.com.bd/help/" style="text-decoration:none; display:block; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 2px 6px rgba(0,0,0,0.04);"><span style="font-size:30px; color:#0188fe; display:block;"><i class="fa-solid fa-circle-question"></i></span><span style="font-size:16px; color:#0f172a; font-weight:700; display:block; margin:10px 0 6px;">Help Center</span><span style="font-size:13px; color:#777; display:block;">Setup guides and videos for your exact model.</span></a>
[/col]

[/row]

[/section]
```

---

## 5. Schema (JSON-LD) — the part that feeds Google *and* AI

Add in a **separate `[ux_html]` element at the very bottom of the page**, or via
RankMath → Schema → Custom Schema.

**This is the highest-leverage item on the page.** ChatGPT, Gemini, Perplexity and
Google's AI Overviews read structured data to decide what an entity *is*. Without it,
an AI asked "is there an app for projector warranty in Bangladesh?" has only prose to
guess from. With it, the facts are unambiguous.

```html
<script type="application/ld+json" data-no-optimize="1" data-no-minify="1">
{
  "@context": "https://schema.org",
  "@type": "MobileApplication",
  "name": "AUN Care Bangladesh",
  "alternateName": "AUN Care",
  "applicationCategory": "UtilitiesApplication",
  "operatingSystem": "Android 7.0 and up",
  "url": "https://aun-projector.com.bd/aun-care-app/",
  "downloadUrl": "https://aun-projector.com.bd/get-aun-care-app/",
  "installUrl": "https://aun-projector.com.bd/get-aun-care-app/",
  "inLanguage": ["bn", "en"],
  "isAccessibleForFree": true,
  "description": "Free Android app for AUN projector owners in Bangladesh. Check warranty status, request genuine spare parts, book and track repairs, open support tickets, and download firmware and manuals for your exact model. Bangla and English.",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "BDT"
  },
  "featureList": [
    "Register a projector by scanning the box barcode",
    "Check warranty status and days remaining",
    "Request genuine spare parts, free under warranty",
    "Approve a quote and pay online or by cash on delivery",
    "Track each spare part to your door",
    "Book a repair and follow it from received to returned",
    "Open a support ticket with photo attachments",
    "Download firmware, manuals and how-to videos for your model",
    "Dust filter cleaning reminders",
    "Projector Planner for screen size and throw distance",
    "Help me choose projector recommendation",
    "AR wall preview before you buy"
  ],
  "screenshot": [
    "[SHOT_01_URL]",
    "[SHOT_02_URL]",
    "[SHOT_03_URL]"
  ],
  "publisher": {
    "@type": "Organization",
    "name": "Smart Living Bangladesh",
    "alternateName": "AUN Projector Bangladesh",
    "url": "https://aun-projector.com.bd",
    "telephone": "+880 9638-078888",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "108 Golartek, Mirpur",
      "addressLocality": "Dhaka",
      "postalCode": "1216",
      "addressCountry": "BD"
    }
  }
}
</script>
```

**On launch day, add one line** inside the object (after `"installUrl"`):

```json
  "sameAs": ["[PLAY_STORE_URL]"],
```

⚠️ **Do not add an `aggregateRating`.** Inventing review scores is a Google
structured-data violation and would risk a manual action. Once the app is on Play
with real reviews, ratings can be added honestly.

**FAQ schema:** RankMath will not read a hand-rolled `<details>` block, so either add
FAQPage schema manually mirroring the 10 questions, or use RankMath's FAQ block. Note
Google now shows FAQ rich results only for authoritative health/government sites —
**the value here is feeding AI systems, not stars in the results page.**

---

## 6. Post-publish checklist

1. Upload the 8 screenshots + app icon; fill every `[SHOT_nn_URL]` and `[APP_ICON_URL]`.
2. Install + activate the `aun-care-promo` plugin (§3a).
3. Publish, then **purge WP Rocket cache**.
4. Test the schema in **Google Rich Results Test** and the **Schema.org validator**.
5. **Search Console → URL Inspection → Request indexing.**
6. **Link to the page — an orphan page ranks poorly.** Two places, both site-wide:
   - **Main menu** (strongest signal): add **AUN Care App**. Top level if there is
     room; otherwise under the Support/Help dropdown.
   - **Footer**, "LET US HELP YOU" column: add a text link `AUN Care App`
     → `https://aun-projector.com.bd/aun-care-app/`.
7. **Point clicks at the page, scans at the APK.** In `aun-app-footer-block.html` the
   badge now goes to `/aun-care-app/` and only the QR encodes `/get-aun-care-app/`.
   Do the same on the homepage section. Rationale: the page needs the internal links,
   and on desktop a direct APK is a 106 MB file the visitor cannot install — whereas
   anyone *scanning* a QR is holding an Android phone. This also matches how the real
   Google Play badge behaves (it opens the listing), so swapping it in later changes
   nothing about the flow.

**On Play launch day — four edits, all pre-marked:**

1. Uncomment the `<!-- PLAY BUTTON -->` block and paste `[PLAY_STORE_URL]`.
2. Add `"sameAs": ["[PLAY_STORE_URL]"]` to the schema.
3. Rewrite the "Why is it not on the Google Play Store?" FAQ answer.
4. Repoint the footer/homepage CTAs at Play.

---

## 7. Where the assets live

| Asset | Path |
|---|---|
| Play screenshots (8) | `animation\playstore\01-…` → `08-…` (1080×1920) |
| Feature graphic (OG image) | `animation\playstore\play-feature-graphic-1024x500.png` |
| App icon 512 | `animation\playstore\play-icon-512.png` |
| Promo video 16:9 (YouTube) | `animation\video\aun-care-16x9-night.mp4` |
| Promo video 9:16 (Reel) | `animation\video\aun-care-9x16-night.mp4` |
| YouTube thumbnail | `animation\playstore\thumb-youtube-1280x720.png` |
| Store copy bank | `animation\playstore\LISTING-COPY.md` |
| Publish checklist | `PLAY-STORE-READINESS.md` |
