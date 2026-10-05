# First product image missing — diagnosis, and the corrected fix

**Updated 2026-09-22** after your WP Rocket export and the Flatsome screenshot.

---

## Short version

**Both lazy loaders are still running.** Turning off the Flatsome switch has not taken effect, so
nothing changed. **Turn off WP Rocket's image lazy load instead** — that one I can see in your
export and it will definitely respond.

```
WP Rocket → Media → LazyLoad → uncheck "Enable for images"
```

Then clear the WP Rocket cache.

---

## ⚠️ I have to correct my earlier advice

Last time I said "match AUN — turn Flatsome's lazy load off." That was the right *idea*, but the
wrong switch to reach for on this site, because **the Flatsome setting is not actually taking
effect.** Here is the proof.

I fetched your product page twice: once normally (the cached page real visitors get) and once with
a random query string, which makes WP Rocket skip its optimisations entirely.

```
                                cached page     WP Rocket bypassed
<img> tags total                    103                 81
Flatsome lazy-load images            33                 33   <-- unchanged
WP Rocket data-lazy-src images       21                  0
flatsome-lazy-load.js loaded        yes                yes   <-- unchanged
wp-rocket lazyload.js loaded        yes                 no
```

⭐ **Flatsome's lazy loading is still on.** Its script loads and 33 images carry its `lazy-load`
class **even on the page where WP Rocket does nothing at all** — so this is not a caching problem.
Either the checkbox did not save, or that particular option is not the one driving it.

Your WP Rocket export confirms the other half:

```json
"lazyload": 1,             <- image lazy load ON
"exclude_lazyload": [],    <- nothing excluded
```

So both are on, exactly as before.

## How the two of them break the first thumbnail

They split your images between them — **0 images are handled by both**, they divide them up:

| | first thumbnail | thumbnails 2, 3, 4 … |
|---|---|---|
| Owned by | **WP Rocket** (`data-lazy-src`) | **Flatsome** (`lazy-load` class) |
| Result | **blank** | fine |

⭐ And this is the tell: on a normally-loaded page I measured the first thumbnail carrying
`data-ll-status="loaded"` and the class `entered lazyloaded` **while its `src` was still the blank
placeholder.** WP Rocket believes it has already swapped the image in. It has not — which is why
there is no console error, and why it looks like the image "vanishes" a second or two after the
page settles.

**Proof it is not your image:** I copied the URL out of WP Rocket's own `data-lazy-src` attribute
into `src` on the live page and the thumbnail appeared instantly. Every file returns HTTP 200. Your
media library is fine, and the big gallery image is correctly excluded already (`ux-skip-lazy`),
which is why only the thumbnail is affected.

---

# The fix

**WP Rocket → Media → LazyLoad → uncheck "Enable for images."** Save, then **clear the WP Rocket
cache.**

Why this one rather than the Flatsome switch:

- It is the setting I can see is active in your export (`"lazyload": 1`), so there is no ambiguity
  about whether it applied.
- Flatsome's loader already handles 33 images on this page perfectly well, so **you lose no
  performance** — lazy loading keeps working, just from one plugin instead of two.
- It removes WP Rocket's `lazyload.min.js` from the page entirely, so the conflict cannot recur.

### If you would rather keep WP Rocket's and stop Flatsome's

That is equally valid — the defect is having both. But first check whether the theme option is
saving at all: open any product page, **View Source**, and search for `flatsome-lazy-load`. If it
is still there after unchecking and saving, the option is not doing what its label suggests, and
WP Rocket's switch is the one to use.

### Narrowest option

**WP Rocket → Media → "Excluded images or iframes"** (currently empty) and add:

```
attachment-woocommerce_thumbnail
```

I would treat this as a fallback. The thumbnails are built by JavaScript rather than being in the
page source, so a server-side exclusion list is not guaranteed to reach them.

---

## One small bonus

That first thumbnail currently renders with an **empty `alt`**, while the others carry the product
name. Whichever loader ends up owning it, the alt text comes back — across all 20 products.

## After you change it

Tell me and I will re-run the same comparison from the outside. What I expect to see is
`wp-rocket lazyload.js loaded: no` and `data-lazy-src images: 0` on the cached page — at which
point the thumbnail cannot be stolen from Flatsome again.
