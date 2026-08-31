# Kohthai Social Login v1.0.0 — deploy notes

Google + Facebook sign-in for WooCommerce **with Google One Tap merged in**.
Ported from `aun-social-login` v1.5.0, fully re-namespaced for Kohthai.
Upload `kohthai-social-login.zip` via Plugins → Add New → Upload.

## Order of operations

1. **Delete Super Socializer.** It was pulled from the WP directory with an
   unpatched unauthenticated stored XSS (plus auth bypass and SQLi). Deleting it
   is the point of this plugin, not an optional cleanup.
2. **Deactivate + delete `Kohthai Google One Tap`.** One Tap is built in now;
   leaving the old plugin active shows the prompt twice.
3. Activate **Kohthai Social Login**.
4. **Settings → Kohthai Social Login.** The Google Client ID is **auto-adopted**
   from the old One Tap plugin's `kohthai_google_client_id` option, so that box
   should already be filled. Add the Google **client secret** (the old One Tap
   plugin never needed one — the redirect flow does).
5. Copy the **Redirect URL** boxes into the Google / Facebook consoles. They must
   match character for character. Also add `https://kohthaibd.com` under Google's
   *Authorised JavaScript origins*.
6. **Save**, then **Test Google connection** — runs the real flow, changes nothing.
7. Tick **One Tap** if wanted. **Purge WP Rocket.** Test in a private window.

## Expected, not a bug

Testing with your own **admin** account reports *"staff accounts must sign in with
a password"*. That is the `block_admins` rule working as designed. Customers are
unaffected. Test with a non-admin Google account.

## What changed from the AUN version

- Namespace: `AUN_SL_*` → `KT_SL_*`, `aun_sl_*` → `kt_sl_*`, CSS `.aun-sl-*` →
  `.kt-sl-*`, shortcode `[aun_social_login]` → **`[kohthai_social_login]`**,
  option `aun_sl_options` → `kt_sl_options`, settings slug
  `options-general.php?page=kohthai-social-login`.
- User meta for provider links is `kt_sl_google_id` / `kt_sl_facebook_id`.
- Legacy Client ID adoption now reads Kohthai's `kohthai_google_client_id`.
- Brand colours: focus ring and settings callout now use Kohthai chocolate
  `#654321`; the "Or login with" divider uses tan `#a08565` on `#e6ded5` rules.
- Copy that referenced the AUN phone-OTP plugin was reworded generically —
  Kohthai has no OTP plugin.
- Version reset to 1.0.0.

## About the app-install banner

There was **nothing to remove**. The AUN social login plugin never contained any
app-install banner, APK link, or Android dependency — that code lives in other
AUN plugins (`aun-app-api`, `aun-app-apk-download.php`), none of which this one
touches. Verified by grep across all 8 files: zero hits for apk / android /
banner / install. So there is no app dependency to strip, and nothing was cut
that Kohthai needs.

## Security model (unchanged, do not weaken)

- Authorization-code flow, server-to-server. The browser never sees the secret.
- CSRF via single-use 32-byte `state` in a 10-minute transient, `hash_equals()`.
- Buttons carry **no nonce on purpose** — WP Rocket caches the HTML and a baked-in
  nonce goes stale. `state` is minted at click time; One Tap fetches its nonce
  over AJAX at the moment of use.
- One Tap tokens are re-checked for `aud` (this site), `iss`, `exp` and
  `email_verified`. Skipping `aud` would accept a token minted for any other
  Google app — the classic One Tap hole.
- An existing account is matched only by a previously linked provider ID or a
  **provider-verified** email. Never by an unverified one.
- Errors travel as short **codes** in `?kt_sl_error=`, never as free text.

## Still true from the AUN build

Never live-tested against real credentials — the logic is lint- and
harness-verified only. Step 6's Test button is the first real exercise of it.

---

# UPDATE — v1.9.1 (was 1.0.0)

Resynced with `aun-social-login` **1.9.1**. Upload the new `kohthai-social-login/`
over the old one, then **purge WP Rocket**.

## Version numbering changed on purpose

Kohthai now carries the **upstream AUN version number** (1.9.1) instead of its own
1.0.0 line. This plugin is a straight mirror of AUN's, so matching the numbers makes
"is Kohthai in sync?" answerable at a glance. The jump 1.0.0 → 1.9.1 is that
renumbering, not nine releases of Kohthai work.

## What arrived from upstream

- **NEW `class-kt-sl-popup.php` (262 lines) — in-page sign-in.** Clicking a button
  no longer navigates the whole page away. Google gets the browser's own FedCM
  account dialog ("Sign in to kohthaibd.com with google.com", drawn by Chrome, no
  address bar); Facebook opens a small popup window running our normal OAuth flow.
  Falls back to the full-page redirect if a popup is blocked, JS is off, or the
  visitor is in an in-app browser — which matters here, given the Facebook
  in-app traffic. New setting `js_flow`, **default ON**.
- **Google's own rendered button.** FedCM's dialog can only be raised by Google's
  `renderButton()`, so in `native` mode Google draws it. New setting
  `google_button`: `native` (FedCM dialog, Google's styling) or `custom` (our
  styling, our popup, no dialog). Genuine either/or — sites that appear to have
  both are on the retired `gapi.auth2` shim.
- **`label_style`** — Google permits exactly four phrasings and no bare "Google";
  Facebook's label mirrors whichever you pick so the pair reads consistently.
- **CSS work to make the two buttons match**: our button measures Google's rendered
  one (`--kt-sl-gw`, `--kt-sl-size`) instead of guessing, and once Google's button
  mounts our hover drops its lift to match Google's, which cannot be restyled.
- **Redirect-URI fix**: OAuth URLs are now built from `get_option('home')` rather
  than `home_url()`. Upstream hit this because TranslatePress rewrites `home_url()`
  to add `/bn/`, producing a redirect URI Google had never been given. Kohthai runs
  no TranslatePress, so this was not biting here — but the fix is strictly more
  correct and is kept for parity. (Any plugin that filters `home_url()` would have
  caused the same failure.)

## Kohthai deltas re-applied

Re-applied on top of the new upstream, unchanged in intent:

- palette — chocolate `#654321` focus ring and settings callout on cream
  `#faf7f3`; tan `#a08565` divider caption on `#e6ded5` rules
- the two copy edits that referenced AUN's phone-OTP plugin, which Kohthai does
  not run
- legacy Client ID adoption from `kohthai_google_client_id`
- namespace: `KT_SL_*`, `kt_sl_*`, `.kt-sl-*`, `[kohthai_social_login]`

One thing the bulk rename missed and I caught by hand: the new popup script sets
and reads a `data-aun-g` attribute. Renamed to `data-kt-g`. It is self-contained
within that one file, so nothing external depended on it.

A provenance block at the top of `kohthai-social-login.php` now lists exactly these
deltas, so the next resync is a diff rather than an investigation.

## Verification

Ported the upstream regression harness and ran it against this build on the bench:
**159 passed, 0 failed**, covering the `decide()` account-linking matrix (including
the unverified-email takeover case), avatar URL sanitisation, One Tap claim checks
(`aud` above all), visitor-facing error codes, `current_url()`, placement toggles,
the language-neutral redirect URI, and the whole new popup/FedCM surface.

Harness: `kohthai-social-login/test-kohthai-social-login.php`

    cd ~/wp-local
    php -c php.ini wp-cli.phar --path=site eval-file "<path>/test-kohthai-social-login.php" --skip-themes

## Settings to look at after upgrading

Both new toggles default ON / `native`, so the sign-in experience changes on
upload without you touching anything:

| Setting | Default | If you want the old behaviour |
|---|---|---|
| `js_flow` | ON | turn OFF for full-page redirects everywhere |
| `google_button` | `native` | `custom` to keep our styling, losing the FedCM dialog |
| `label_style` | `continue_with` | — |

Still true from the original build: never live-tested against real credentials.
The **Test Google connection** button on the settings page remains the first real
exercise of the flow.
