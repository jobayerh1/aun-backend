# Deploy — Kohthai Review Photos v1.0.0

Makes photo uploads in product reviews work. **Tests: 19 passed, 0 failed**, plus
a live-page run in the browser covering every outcome below.

## Install

1. Plugins → Add New → Upload Plugin → `kohthai-review-photos.zip` → Activate.
2. WP Rocket → **Clear cache**, so product pages are rebuilt with the script in them.
3. Nothing to configure. **Customer Reviews for WooCommerce stays exactly as it is** —
   this works around it, so updating that plugin cannot undo the fix.

## Why photos weren't getting through

Every one of these failed **silently** — the thumbnail appeared, the progress bar
stayed empty, and nothing ever said why:

1. **The server drops large photos.** PHP's per-file upload limit on the server is
   under 11 MB, while the review plugin allows 10 MB. A phone photo between the two
   passes the plugin's check and is thrown away by PHP before WordPress sees it.
   (Measured without storing anything: an 11 MB test file was reported as the wrong
   *type*, not too *large* — the plugin saw a size of zero.)
2. **A bug in the review plugin.** When WordPress fails to save an upload, the plugin
   writes the error — then overwrites it with "200 OK" and no photo. Its own
   JavaScript then crashes reading the missing photo.
3. **No error handling at all** in its upload request, so a dropped connection or any
   unexpected reply ends the same way.
4. **Phones were forced into the camera.** The upload box carried
   `capture="environment"`, which opens the rear camera directly — she couldn't pick
   the photo she'd already taken from her gallery.
5. **WebP isn't accepted**, and that's what most pictures saved from the web are.

## What it does

- **Shrinks photos in the browser before sending** — anything over 1.5 MB, or in a
  format the review plugin refuses, is redrawn as a JPEG no larger than 2048 px.
  A phone photo lands at a few hundred KB: under the server limit, fast on mobile
  data, and still sharper than a review photo is ever shown. WebP becomes JPEG.
  Videos and GIFs are sent untouched.
- **Lets phones use the gallery** again.
- **Always says what happened.** A failed upload removes its frozen thumbnail and
  shows one of: *"That photo couldn't be saved…"*, *"The upload was interrupted…"*, or
  *"The shop couldn't take that photo just now…"*.

## Verified on the live product page

Run in a browser against kohthaibd.com with the network faked, so **nothing was
uploaded to the site**:

| Case | Result |
|---|---|
| 11 MB phone photo | sent as a 2048×1536 JPEG, thumbnail marked done |
| server bug (200 with no photo) | thumbnail removed, "couldn't be saved" shown |
| "Checking your browser" page | thumbnail removed, "try again in a moment" shown |
| connection lost | thumbnail removed, "check your connection" shown |
| WebP from the web | sent as JPEG, accepted |
| camera-only attribute | gone |

## Worth knowing

- **Maximum upload size.** WP Admin → Media → Add New shows *"Maximum upload file
  size"*. That's the server limit from point 1. It no longer matters for photos, but
  if it's 2 MB or less, customers' review **videos** will still fail — raise it in
  cPanel → Select PHP Version → Options (`upload_max_filesize`, `post_max_size`).
- **HEIC** (some iPhone and Samsung settings) can't be read by most Android browsers,
  so those go through unchanged and the review plugin says which types it accepts.
  iPhones normally convert to JPEG on their own when a photo is chosen.
