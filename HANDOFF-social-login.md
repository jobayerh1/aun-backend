# Handoff — continue in a new chat

> Full detail is already in Claude's memory (`project_aun_site_plugins.md`), which auto-loads.
> This file is just the short version of **where we stopped**.

---

## 1. `aun-social-login/` — NEW plugin, v1.5.0 ⚠️ NOT YET DEPLOYED

Replaces **Super Socializer 7.14.5**, which was pulled from the WP directory with an
unpatched **unauthenticated stored XSS** (+ auth bypass, + SQLi). It must be deleted.

**What it does:** Google + Facebook sign-in for WooCommerce, brand icon buttons in the
same spots Super Socializer used, plus Google One Tap. Social login only — no
sharing/comments (that was most of Super Socializer's attack surface).

**8 files, all lint-clean.** Security-critical logic lives in `class-aun-sl-oauth.php`.

### Deploy steps (in order)
1. Delete **Super Socializer**; deactivate + delete **`aun-google-one-tap.php`**
   (One Tap is merged in now — leaving it on shows the prompt twice).
2. Upload `aun-social-login/`, activate.
3. **Settings → AUN Social Login** — Client ID/secret. The Google Client ID is
   **auto-adopted** from the old One Tap plugin, so it may already be filled.
4. Copy the **Redirect URL** boxes into the Google/Facebook consoles (shown in 5 places
   on the settings page; must match character-for-character).
5. Press **Save**, then **Test Google connection** — runs the real flow, changes nothing.
6. Tick **One Tap** if wanted. Purge WP Rocket. Test in a private window.

### Known / expected
- Testing with **your own admin account** will report *"staff accounts must sign in with
  a password"* — that's the admin-block rule working. Customers are unaffected.
- **Never live-tested** — needs real credentials. Logic is harness-verified (see below).

### Settings worth knowing
- **"Above the WooCommerce login form"** — tick this if the icons don't appear on the
  login side. The phone-OTP plugin hides the email/password form, and anything rendered
  *inside* it disappears too.
- `block_admins`, `link_by_email`, `allow_register`, avatars, One Tap — all toggles.

### What changed in 1.4.0–1.5.0 (after the first handoff was written)
Three real problems found on a second read, all fixed and re-verified:

1. **Error messages were free text in the URL.** `?aun_sl_error=<any sentence>` was
   printed inside our own red WooCommerce error box on My Account. Escaping stopped
   markup but not wording — anyone could send a customer a link to *our* domain
   showing *our* styling saying "your account is locked, call 017…". Now the URL
   carries a short **code**; `AUN_SL_OAuth::messages()` is the only source of
   sentences, and an unrecognised code renders nothing at all. This also stops raw
   `wp_insert_user()` wording leaking to visitors.
2. **One Tap on checkout dumped the customer at My Account.** `redirect_url()` asked
   `is_checkout()`, but the sign-in runs over `admin-ajax.php`, where that is *always*
   false. The page URL is now sent with the request and revalidated server-side with
   `wp_validate_redirect()`, so people land back where they were.
3. **One Tap's AJAX endpoint had no rate limit** — an unauthenticated way to make the
   site call Google's `tokeninfo` on demand. It now shares the redirect flow's per-IP
   ceiling (`AUN_SL_OAuth::rate_ok()`, made public for this).

4. **(1.4.1) The buttons did not return you to the page you left.** `current_url()`
   was misnamed: on checkout it returned checkout, but **everywhere else — cart, a
   product page, a category — it returned My Account**, so anyone signing in from the
   cart was dumped on the account page. Its own comment said it should land you back
   where you started; the implementation never matched. It now builds the real page
   URL from `REQUEST_URI`, rehosted through `home_url()` so the host is pinned, and
   strips a stale `aun_sl_error` on the way. This also makes the buttons and One Tap
   behave the same, which fix 2 had otherwise left inconsistent.

Plus a hardening tweak: the avatar sanitiser now rejects quotes, angle brackets,
backslashes and whitespace outright, so a stored URL can't rely on every future
output site remembering to escape it.

5. **(1.5.0) Buttons on the cart page**, new `at_wc_cart` toggle, **default ON**, hook
   `woocommerce_before_cart` (above the cart table). A logged-out shopper with items in
   the cart is the highest-intent visitor on the site; signing in there prefills
   checkout and attaches the order to an account.

### Decided, not built
- **Redirect vs popup — staying with redirect.** Google's `renderButton` and Facebook's
  JS SDK both default to a popup, which preserves cart/checkout form state. Rejected for
  this site: mobile-dominant traffic and heavy Facebook in-app-browser arrivals, where
  popups are blocked or broken most often; redirect also keeps both provider SDKs off
  the page. Revisit only as a desktop-only option.

### Loose end
- The buttons' heading is one global string, default **"Or login with"**. That reads
  fine under a login form and oddly above a cart table. Either reword it to something
  placement-neutral ("Sign in with") on the settings page, or give the cart its own
  title option.

### Verified by harness (not by live login) — `test-social-login.php`, **69/69 pass**
The harness is now a **file in the repo**, not something ad-hoc to re-type:

```bash
cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file "C:/Users/Jobayer Hossain/Downloads/Claude session/test-social-login.php" --skip-themes
```

(the plugin must be copied into `wp-local/site/wp-content/plugins/` and activated;
`-c php.ini` is required or PDO SQLite is missing.)

- **A — 13 account-linking cases**, incl. **unverified email → refuse** (the
  account-takeover guard), existing link beating any email, and duplicate-link refusal
- **B — 14 avatar-URL cases** — rejects `javascript:`, `data:`, protocol-relative, plain
  http, and host spoofs like `evil-googleusercontent.com` / `googleusercontent.com.evil.tld`
- **C — 13 One Tap claim cases** — incl. **token minted for another Google app → rejected**,
  and an `aud` that is a *prefix* of ours
- **D — 8 error-code cases** — greps the source so any `fail('x')` added later without a
  message is caught, and asserts a crafted code renders nothing
- **E — 8 return-URL cases** — cart/product/query-string preserved, stale notice dropped,
  and hostile `REQUEST_URI` values (`//evil.com`, `/\evil.com`) can't leave our host
- **F — 13 placement cases** — every toggle has a default and survives a Save. A
  placement missing from `sanitize()`'s whitelist silently resets to 0 on every Save,
  which presents as "the setting won't stick"; this catches that.

Re-run it after every change to this plugin.

---

## 2. `aun-care-promo/` — v1.5.0 ⚠️ NOT YET DEPLOYED

- New **Settings → AUN Care App**: on/off for the app install bar (the ask), plus
  page-views / dwell / scroll / mute-days, all previously hard-coded.
- New file: `settings.php`. Defaults unchanged, so behaviour is identical until edited.
- **One Tap coexistence:** on phones the One Tap prompt also docks to the bottom, over
  the app bar. The bar now waits until the prompt is gone instead of burning its single
  per-visit impression.

### v1.5.0 — the install bar was covering the Add to cart button
**Confirmed on the live site**, not theoretical: Flatsome's sticky *Add to cart* bar is
enabled and docks to the bottom of the screen on phones. The install bar is
`position:fixed; bottom:0`, so on product pages the two overlapped — covering the buy
button, on the page where the customer is deciding to buy.

Fixed by **not competing at all** rather than stacking:

- New **`skip_products`** toggle (Settings → AUN Care App → *Product pages*), **default
  ON**: the bar stays off single product pages.
- **The impression is not spent.** The skip sits deliberately *after* the pageview
  counter, so the visit still counts and the bar appears on the next page they open.
  On a projector shop most browsing is product pages — skipping the count instead
  would have meant the bar effectively never showed at all. There is a harness case
  pinning this ordering.
- `isProduct` is resolved server-side via `is_product()`, which is cache-safe because a
  product page has its own URL and WP Rocket caches per URL.
- New **`bottomBusy()`** guard, theme-agnostic: instead of hunting for Flatsome's markup
  by class name (which breaks on theme updates), it asks the browser what is actually
  painted at the bottom-centre of the viewport and yields to anything `position:fixed`.
  Catches cookie notices, chat docks and future sticky bars too.
- The wait-and-retry now **gives up after 5 tries** with the impression intact, instead
  of looping forever on a page it can never show on.

**Why not stack above the sticky bar:** two fixed bars eat ~120px of a phone screen, and
it would tie us to Flatsome's DOM. More basically — on a product page a buy button
outranks an app install, so the right move is to get out of the way.

### Harness — `test-care-promo.php`, **36/36 pass**
```bash
cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file "C:/Users/Jobayer Hossain/Downloads/Claude session/test-care-promo.php" --skip-themes
```
Covers the sanitiser (incl. `-5 days` clamping to the minimum rather than flipping to
`5`), every suppression path (cart / checkout / account / app pages / master switch),
the JSON config handed to the browser, `isProduct` being 1 on a real product page and 0
elsewhere, the WP Rocket inline-script guards, and that the product skip runs *after*
the pageview counter. It creates a published product if the bench has none — the only
one there is a `private` leftover from the spare-parts tests, which `is_product()`
would not treat as a normal shop page.

---

## 3. Other work from this session still awaiting deploy

Each is a single file/folder replace + **purge WP Rocket**:

| Plugin | Version | What changed |
|---|---|---|
| `aun-help-center/` | 2.7.0 | Per-model user manuals (WP File Download); code-review fixes |
| `aun-campaign-bar.php` | 1.5.0 | Campaign templates, Bangla `/bn/`, skips out-of-stock/discontinued |
| `ERP/slb-repair-tracker.php` | 1.2.0 | Pathao consignment ID in notes → tappable tracking chip |
| `AUN Smart Bundler/` | 2.5.0 | Sold-out variations shown greyed instead of hidden |
| `aun-shorts-showcase.php` | 3.8.3 | Mobile swipe hint position; `title="" icon="false"` usage |
| `aun-throw-distance-calculator.php` | 3.5.0 | Stats row all in inches (+ cm/ft sub-line) |

**Resolved this session (no action):** the `<span> - </span>` in variation names —
root cause was **TranslatePress**; fixed with a child-theme filter + two SQL cleanups.

---

## Suggested next steps
1. Deploy + live-test **aun-social-login** (the security-urgent one).
2. Deploy **aun-care-promo**.
3. Work through the table above.
4. Optional idea discussed: raise the app bar's "page views first" to 3 so One Tap gets
   the first shot on mobile.

## House rules the new session should keep
- WP Rocket guards (`data-no-optimize` / `data-no-minify`) on every bare inline
  `<style>`/`<script>`; **never** bake a WP nonce into public cached HTML.
- Replace plugin files **in place** — a duplicate copy causes a "Cannot redeclare" fatal.
- `php -l` every touched file; bump the version whenever CSS/JS changes.
- Update a plugin's own Settings/help page in the same edit as any shortcode change.
