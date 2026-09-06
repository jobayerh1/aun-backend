# The 429 on review submission — diagnosis and fix

**Date:** 2026-09-01 · Reproduced and measured, not guessed.

---

## Short answer

**No, this is not WooCommerce, and no WooCommerce store behaves like this.** WooCommerce never
sends a 429 for a review. Nothing on your site is broken — not the theme, not the plugins, not the
reviews setting you just changed.

**Cloudflare is blocking it, before the request ever reaches your server.**

---

## The proof

Your stack is **Cloudflare → LiteSpeed → WordPress**. I posted to the same endpoint your review
form uses (`wp-comments-post.php`), six times, with empty fields so nothing could be created:

```
req 1 -> 200   x-turbo-charged-by: LiteSpeed     <- reached your server
req 2 -> 200   x-turbo-charged-by: LiteSpeed     <- reached your server
req 3 -> 429   (no LiteSpeed header)             <- never reached your server
req 4 -> 429   (no LiteSpeed header)
req 5 -> 429   (no LiteSpeed header)
req 6 -> 429   (no LiteSpeed header)
```

⭐ **That missing `x-turbo-charged-by: LiteSpeed` header is the whole proof.** Every response that
reaches your server carries it. The 429s do not, and they carry `Server: cloudflare` with a
CF-RAY. Cloudflare answered them itself. Your WordPress never saw them.

The body is identical to your screenshot:

```html
<html><body><h1>429 Too Many Requests</h1>
You have sent too many requests in a given amount of time.
</body></html>
```

## What I measured about the block

| | |
|---|---|
| **Threshold** | 2 POSTs allowed; blocked from the **3rd** |
| **Window** | those 3 requests were within about 3 seconds |
| **Block length** | still blocked after **65 seconds** — so it is minutes, not seconds |
| **Scope** | **path-specific.** From the same blocked IP, the product page and home page both still returned **200** |

**The good news: shopping and checkout are completely unaffected.** Only comment/review posting is
being limited. Nobody is being stopped from buying.

---

## Why you must not leave it as it is

Two POSTs is far too low, for a reason specific to your market:

⭐ **Bangladeshi mobile networks put many customers behind one shared public IP (CGNAT).** GP,
Robi and Banglalink subscribers routinely share an address. A per-IP limit of 2 means one
customer's retry plus a second customer's first attempt can block **both of them** — and the
block lasts minutes.

And a single customer trips it easily on her own: submit → miss a required field → get an error →
correct it → submit again → that is already attempt 3.

You have just switched reviews on to start collecting them. This limit would quietly stop a
large share of them, and neither you nor she would see why.

---

## How to fix it — all in the Cloudflare dashboard, no server access needed

**1. Find the rule that fired.**
Cloudflare dashboard → select **kohthaibd.com** → **Security → Events**.
Filter **Action = Block**, or search the Ray ID from my test: **`a341a4e7fb8d1900`**
(2026-09-01, 04:48 UTC). The event will name the exact rule.

**2. Then, depending on what it names:**

- **Security → WAF → Rate limiting rules** — the most likely home. Edit the rule.
- **Security → WAF → Custom rules** — if it was added as a custom rule.
- If you find nothing, **your hosting company added it.** Many hosts put a default anti-spam
  rate limit on `wp-login.php`, `xmlrpc.php` and `wp-comments-post.php`. Send them this document
  and ask them to raise the threshold on the comments endpoint.

**3. What to change it to.**

**Do not delete the rule.** That endpoint is a genuine spam target and some limit belongs there.
Raise it instead:

```
Path:      /wp-comments-post.php
Threshold: 5 requests per 1 minute   (was effectively 2)
Duration:  1 minute                  (currently minutes)
Action:    Managed Challenge  — better than Block
```

**Why "Managed Challenge" rather than "Block":** a real customer gets a one-second check and her
review still goes through. A spam bot fails it. With Block, a real customer just gets the broken-
looking page you saw, with no way forward.

A genuine review is **one** POST. Five per minute is generous to customers and still useless to a
spammer.

---

## After you change it

Tell me, and I will re-run the same test and confirm the threshold from the outside — you should
see the LiteSpeed header come back on all of them.

---

## One thing to check while you are in there

Since reviews are now open to everyone rather than verified buyers only, you have removed the
barrier that was keeping spam out. Make sure **WooCommerce → Settings → Products → Reviews →
"Enable comment moderation"** is on, or that Akismet is active, so nothing appears on a product
page until you approve it. Otherwise the first spam review publishes itself.
