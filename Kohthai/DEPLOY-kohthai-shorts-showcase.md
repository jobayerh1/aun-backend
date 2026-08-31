# Deploy — Kohthai Shorts Showcase v1.1.0

Upload `kohthai-shorts-showcase.zip` via **Plugins → Add New → Upload Plugin**, activate,
then clear the WP Rocket cache.

**Everything below is also inside WordPress** — see **Settings → Shorts Showcase**. You
shouldn't need this file day to day.

---

## The problem it solves

Every Kohthai product video is a YouTube Short. Flatsome's `[ux_video]` forces it into a
**16:9 player**, so a square or vertical clip gets black bars down both sides — roughly 60%
of every frame — and the bag renders at about 40% of its possible size. No CSS fixes that,
because the letterboxing happens inside the player.

This gives the video a card in its **real shape**, so it fills the frame.

---

## First: set the default shape

**Settings → Shorts Showcase → Default video shape.**

I measured two of your live product videos via YouTube's original-aspect still
(`i.ytimg.com/vi/<id>/oar2.jpg`):

| Video | oar2.jpg | Shape |
|---|---|---|
| `tUnExtJfYdQ` (Versatile Soft PU) | 720 × 720 | **1:1** |
| `bUSme6zuVwQ` (Retro Artistic) | 720 × 720 | **1:1** |

Both are **square**, not 9:16. Set the default to **1:1** and you never have to type `ratio`
on a shortcode again.

```
1:1     380px wide card   ← your current videos
4:5     340px wide card
9:16    280px wide card   ← plugin default
```

**Not sure about a video?** The settings page has a checker — paste the 11-character ID and it
reads the true shape and tells you which to use. Getting this wrong is the one thing that stops
the plugin working: a mismatch puts the black bars straight back.

---

## Using it on a product page

Replace `[ux_video …]` in the Description field with:

```
[kohthai_shorts ids="bUSme6zuVwQ" title="" icon="false" feature_title="Why you'll love it" feature_desc="Watch it worn in real life — see how it hangs, how big it really is, and how much it actually holds." features="Fits your phone, wallet and makeup, Adjustable strap for shoulder or crossbody, Light enough to carry all day"]
```

With **one** video it renders a **side-by-side block** — video left, selling points right. That
fills the 1,050px content area properly instead of stretching, which was the problem with the
Step 4 desktop layout.

⚠️ **Always give it `feature_title` and `features`.** With a single video the right-hand column
always renders, so leaving them empty produces an empty column beside the video.

`title="" icon="false"` suppress the plugin's own heading, because the accordion already prints
"Description" above it.

**A gallery of several videos** — drag on desktop, swipe on mobile:

```
[kohthai_shorts ids="bUSme6zuVwQ, tUnExtJfYdQ"]
```

**A landscape 16:9 video** has its own shortcode:

```
[kohthai_video id="VIDEO_ID" title="Optional title" desc="Optional line"]
```

Full attribute tables for both are on the settings page.

---

## What it does automatically

- **Autoplays muted** when scrolled into view, pauses when it leaves. Nothing makes sound
  unless the viewer taps the speaker.
- **Loops seamlessly** — no end screen, no "watch next" suggestions pulling people off the page.
- **Poster frame in the right shape.** Loads `oar2.jpg` (original aspect) layered over
  `hqdefault.jpg`. hqdefault is always a 4:3 crop and looks wrong on a square or vertical video;
  because CSS skips a background layer that 404s, hqdefault only shows when oar2 doesn't exist.
- **Sources:** an 11-character YouTube ID, a WordPress Media ID, or an .mp4 / .webm / .mov URL.

---

## No leftover wording

You asked for none, and it took four passes. The first rename only caught lowercase `aun-` and
`aun_`. Also found and fixed:

- an uppercase `__AUN_INSTANCE__` placeholder token
- **seven camelCase JavaScript globals** — `aunYtPlayers`, `aunToggleMute`, `_aunInit`,
  `aunYtApiLoaded`, `aunYtApiReady`, `aunPlayersInitialized`, and the `aunSwipePulse` CSS
  keyframe. These cross-reference each other, so they had to be renamed together or the player
  would have silently died. Verified by counting every token before and after.
- two comments referencing `[kohthai_head]` — **a shortcode that doesn't exist on this site**
- the entire help page, which was projector copy: "Experience the Clarity", "Native 1080p
  Resolution, Seamless Android TV", "4K hands-on review", and media IDs from the other site

**The file now has zero case-insensitive matches for "aun", "projector", "cinema", "lumen",
"1080p" or "android tv"** — and the test harness scans for all of them on every run, so it
can't creep back in.

One neutral line of provenance stays in the file header — *"proven on the owner's other
WooCommerce site"* — because deleting where the code came from would make this hard to maintain
later. It names no brand and it's a PHP comment, never shown to anyone. Say the word and I'll
remove that too.

---

## Tests

```bash
php test-kohthai-shorts-showcase.php
```

**60 assertions, 0 failures.** Covers the ratio map and its fallbacks, the settings save/sanitize
path, the poster layer order, the whole-file wording scan, and that every renamed JavaScript
token is present with its keyframe matching its animation.

It also fires **seven malformed video IDs** — CSS injection, path traversal, a script tag,
quote-breaking, wrong lengths — and asserts zero cards rendered and nothing leaked for each. The
ID is interpolated into a CSS `url()`, so **don't relax `/^[A-Za-z0-9_-]{11}$/`**.

---

## After deploying

1. Clear WP Rocket.
2. Settings → Shorts Showcase → set the default shape to **1:1**.
3. Put the shortcode on **one** product.
4. Check on a phone: the video should fill its frame with no black bars, autoplay muted, loop.
5. If it still letterboxes, the shape doesn't match — run that video through the checker.

Once it looks right, the `.kt-media { max-width: 640px }` rule from Step 4 stops doing anything
for that product, since the plugin now owns the video's size.
