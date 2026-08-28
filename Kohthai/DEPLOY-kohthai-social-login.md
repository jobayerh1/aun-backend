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
