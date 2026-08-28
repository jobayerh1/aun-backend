# Kohthai Alpha SMS OTP Login v1.0.0 — deploy notes

Passwordless **phone + OTP** login on the WooCommerce account form, sending codes
through **Alpha SMS (sms.net.bd)**. Ported from `aun-alpha-otp-login` v1.0.1,
fully re-namespaced for Kohthai. Upload `kohthai-alpha-otp-login.zip` via
Plugins → Add New → Upload.

## ⚠️ Prerequisite — read this first

This plugin **does not store an API key of its own**. It reads the key and sender
ID out of the **Alpha SMS plugin's** option (`alpha_sms`), so the credentials are
configured in one place only.

That means **the Alpha SMS plugin must be installed and configured on Kohthai**,
not just on AUN. Sharing one sms.net.bd *account* between the two sites is fine —
the same API key can live in both — but the key has to actually be present in
Kohthai's own database. Options are the same; nothing here is per-site.

If the key is missing, the plugin shows a yellow admin notice on its settings page
and on the Plugins list, and OTP sending stays switched off. **If Kohthai does not
run the Alpha SMS plugin, tell me and I will add a direct API-key field to this
plugin instead** — it is a small change, I just did not want to fork the
"credentials in one place" design without asking.

## Deploy steps

1. Confirm the **Alpha SMS** plugin is active on Kohthai with the sms.net.bd API
   key and sender ID filled in.
2. Upload and activate **Kohthai Alpha SMS OTP Login**. Defaults are seeded on
   activation, so it works immediately.
3. **Settings → Kohthai Alpha OTP Login** — review the defaults below.
4. **Purge WP Rocket.** Test in a private window with a real phone number that is
   already on a customer account.

## Defaults worth knowing

| Setting | Default | Note |
|---|---|---|
| `otp_first` | yes | OTP form shown **above** the email/password form |
| `hide_email_login` | no | Set yes for phone-only login |
| `otp_length` | 6 | |
| `otp_expiry` | 120s | code validity |
| `resend_wait` | 30s | |
| `max_per_day` | 10 | per phone **and** per IP |
| `max_attempts` | 5 | wrong tries before the code dies |
| `multi_account` | auto | when one phone matches several accounts |
| `redirect` | blank | blank = My Account |

SMS template default:
`Your OTP for [site] login is [otp]. Valid for [min] minutes. Do not share this code.`
`[site]` resolves to the **site title**, so it will read "Kohthai" automatically —
nothing hardcoded. Keep the wording short; Alpha bills per SMS segment.

## How a phone is matched to an account

`normalize_phone()` accepts `01712345678`, `+8801712345678`, `8801712345678`,
`1712345678`, with spaces or dashes, and canonicalises to `8801XXXXXXXXX`. It
validates the operator prefix (013–019) and rejects anything else. Matching then
tries the `billing_phone` and `mobile_phone` user meta in four stored formats,
because different plugins and eras saved them differently. All of this is
Bangladesh-generic — nothing was AUN-specific, so it carries over unchanged.

## One real fix I made during the port

The AUN version prints its AJAX nonce into the page via `wp_localize_script`. The
login form lives in the **Flatsome header modal**, which appears on *every* page —
so that nonce is baked into HTML that **WP Rocket caches**. A nonce lives ~12–24
hours; a cached page can be served much longer. Once it goes stale, every OTP
request from that cached page fails the security check and the customer sees
"Security check failed. Please reload the page." — with no idea why.

Fixed here by:
- a new `kt_alpha_otp_nonce` AJAX endpoint that mints a fresh nonce on demand,
- a `'code' => 'bad_nonce'` tag on the nonce refusal,
- `post()` in the JS transparently re-minting and **replaying the request once**
  when it sees that code. Any other refusal passes through untouched, and the
  normal path costs no extra request.

This is the same approach `kohthai-social-login` already uses for Google One Tap.

**This bug exists in the live AUN plugin too.** I have not touched
`aun-alpha-otp-login/` — say the word and I will backport the same fix.

## Namespace changes from the AUN version

`AUN_Alpha_OTP_*` → `KT_Alpha_OTP_*`, `AUN_ALPHA_OTP_*` → `KT_ALPHA_OTP_*`,
`aun_alpha_otp*` → `kt_alpha_otp*`, JS object `aunAlphaOtp` → `ktAlphaOtp`,
CSS/markup `.aun-otp-*` → `.kt-otp-*`, option `aun_alpha_otp` → `kt_alpha_otp`,
text domain → `kohthai-alpha-otp-login`, settings slug
`options-general.php?page=kohthai-alpha-otp-login`. Accent colour `#0188fe` →
Kohthai chocolate `#654321`. Version reset to 1.0.0.

`KT_ALPHA_OTP_ALPHA_OPTION` is deliberately still **`alpha_sms`** — that is the
Alpha SMS plugin's own option, not ours, and renaming it would break the key
lookup.

Class-name sets were diffed against the original: a clean 1:1 rename, no drift.
All 6 PHP files lint clean; the JS parses clean.

## Interaction with Kohthai Social Login

Both plugins render into the WooCommerce login form and coexist on AUN already.
If the social icons do not appear on the login side, tick **"Above the
WooCommerce login form"** in Kohthai Social Login — with `hide_email_login` on,
this OTP plugin replaces the email/password form, and anything drawn *inside*
that form is hidden along with it.
