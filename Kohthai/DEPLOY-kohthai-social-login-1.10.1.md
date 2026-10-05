# Deploy — Kohthai Social Login v1.10.1

**File:** `Kohthai\kohthai-social-login.zip` · 1.9.1 → **1.10.1**
**Tests: 162 passed / 0 failed** (main harness) · **20 passed / 0 failed** (new page-gating bench test) · lint clean.

---

## Yes, Kohthai had both bugs

Confirmed by reading the code, not assumed:

```
logo_alignment            'left'   -> the G pinned to the far edge
One Tap render()          ungated  -> the card could appear on every page
```

Both are now fixed the same way you fixed Breo.

## What I merged

**1. Google logo centred.** `logo_alignment` `'left'` → `'center'`, so the G sits beside the words
like your Facebook button.

**2. One Tap only where it belongs.** New `prompt_here()` and four settings under
**"Show the One Tap card on"** — Checkout ✓, My Account ✓, Cart ✗, Every other page ✗ — with the
order-received and pay-for-order screens excluded, since those come after the decision.

I verified the port is **byte-identical to your Breo version** after normalising the class
prefixes: zero differences in any of the ported code, across all four files.

---

## ⭐ But Kohthai was two versions behind, not one

This is the part worth knowing. Breo was on **1.9.3** and Kohthai on **1.9.1**, so Kohthai was also
missing the 1.9.2/1.9.3 fixes. Bumping it straight to 1.10.1 would have made the version number
claim a parity it did not have — the plugins are supposed to track each other, so that matters.

I brought those across too:

| | was | now |
|---|---|---|
| `locale` | absent | `get_locale()` — Google draws its own label, and without this it follows the **visitor's** Google language, not your site's |
| labelled-button width | `340` fixed | `400` (Google's max), **narrowed at render time to fit the row, 200–400px**, so it cannot overflow a phone |
| `size` threshold | `>= 48` | `>= 44` — Google's sizes are 40/32/20px tall, so a 44px button should map to `large` |

**⚠️ One visible change to check.** Your button height default is **44px**. Under the old `>= 48`
threshold that rendered Google's **medium** (32px); it now renders **large** (40px), which is the
better match for a 44px row. Look at the login page after installing — if you prefer the old look,
change that one line back to `48`.

**The width fix is dormant on Kohthai for now.** It only applies to the labelled button, and your
`show_label` default is `0` (icon buttons). It is there and correct if you ever switch labels on.

**Left alone deliberately** — these differ from Breo on purpose and are not bugs:
`title` ("Or login with" vs Breo's "Or continue with"), `size` 44 vs 46, `show_label` 0 vs 1, and
the `kohthai_google_client_id` legacy-migration block, which is Kohthai-only.

---

## Tests

The main harness gained three assertions (the `400` width, the render-time clamp, the locale, and
the centred logo). I also ported your Breo bench test as
`test-kohthai-onetap-pages.php` — it saves your settings, exercises checkout / account / cart /
other / thank-you against the defaults and with every box ticked, checks One Tap off, checks the
Google library is not loaded when nothing on the page needs it, checks signed-in users see
nothing, checks the boxes save as 0/1, and restores your settings at the end. **20/20 on Kohthai**,
same as Breo.

Run it the same way:

```bash
php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file "Kohthai/kohthai-social-login/test-kohthai-onetap-pages.php" --skip-themes
```

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Settings → Kohthai Social Login → confirm **Show the One Tap card on** reads Checkout + My
   Account only.
4. Look at the login page and check the Google button height against the Facebook one.
