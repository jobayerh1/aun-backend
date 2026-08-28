# Kohthai Campaign Notice Bar v2.0.0 — upgrade notes

Rebuilt on the **AUN Campaign Notice Bar v1.8.3** engine. Replaces the old
v1.4.6 file. Upload `kohthai-campaign-bar.php` over the existing one (or upload
the folder), then **purge WP Rocket**.

## Why rebuilt instead of merged

I compared the two plugins function by function first. Kohthai's v1.4.6 had
**no method that AUN v1.8.3 lacks** — it was the old AUN engine plus brand
colours, with no Kohthai-only behaviour anywhere in it. So there was nothing to
merge *back*, and hand-porting four releases of engine work into the old base
would have meant re-implementing it (bugs included) instead of taking the version
that is already running in production on AUN.

## Nothing you have to change

Deliberately kept identical, so this is a drop-in upgrade:

- **Shortcodes** — `[kohthai_campaign_notice]` and
  `[kohthai_campaign_product_notice]`. Your Flatsome layout keeps working.
- **Option key** — `kohthai_campaign_notice_bar_options`. Your current campaign,
  dates, timezone and messages carry over. New settings simply take defaults.
- **Sale bubble CSS class** — `.kohthai-smart-sale-bubble` and the
  `kohthaiSmoothRipple` keyframes. My first pass renamed these to `kt-*`; I put
  them back, because any custom CSS you had written against them would have
  silently stopped matching. That is the one thing in this upgrade that could
  have broken quietly.

## What Kohthai gains over 1.4.6

- **`{countdown}` live timer** — driven from an absolute deadline, so it stays
  correct on a WP-Rocket-cached page.
- **Empty top bar collapses before paint**, in the `<head>`, instead of being
  measured in the DOM afterwards — no more appear-then-vanish flicker. Three-way
  choice: phones/tablets only (default), every screen size, or never.
- **Fix:** a campaign scheduled for a *future* date used to leave the top bar
  visible but empty for the entire wait.
- **Dismissible notices** — visitor closes it, stays closed N days (default 3).
- **8 campaign templates**, rewritten for bags: weekly offer, flash sale, Eid,
  countdown, final-hours push, flash+countdown, delivery week, mega sale.
- **Performance:** the "any sale active" answer moved from a transient to an
  autoloaded option (2 extra queries → 0), and the sale detector is memoised per
  request instead of running twice per page.
- **Out-of-stock products are skipped**, so a sold-out bag is never advertised.

## Kohthai design language

I kept the palette the 1.4.6 fork already used rather than inventing tokens:

| Token | Where |
|---|---|
| `#654321` chocolate | primary — links, buttons, admin accents |
| `#a08565` tan | savings + links **on** the chocolate top bar |
| `#8fbc8f` sage | the savings figure on light backgrounds |
| `#4a3b31` dark brown | headings / borders |
| `#fdfaf7` cream, `#efe7dd`, `#d9cbbd` | panels and hairlines |

**One thing to look at:** `#8fbc8f` sage as bold text on white is fairly low
contrast. It is your existing choice so I did not override it, but the savings
figure is the single most important number in the notice — if it reads faint to
you on a phone in daylight, say so and I will deepen it a shade.

## Bangla

Kohthai is English-only — I checked the live site: no TranslatePress, no `/bn/`,
no hreflang. So the Bangla **admin fields are hidden** (see `bn_available()`),
and the template `*_bn` copy is gone.

The Bangla **engine** is deliberately left in place. Keeping this file
structurally identical to `aun-campaign-bar.php` is what makes the next
AUN→Kohthai port a rename instead of a rewrite. It costs nothing at runtime:
`is_bn()` just answers false. Install TranslatePress and the fields come back on
their own — and any `*_bn` values already stored are preserved, because
`sanitize_options()` only overwrites a key that is actually present in the POST.

## Verification

Ported the AUN regression harness and ran it against this build on the local
bench (PHP 8.2 + WooCommerce): **159 passed, 0 failed**, covering the campaign
window, `is_active()` across boundaries, shortcode output, the inline timer,
boundary cron scheduling, settings, `sanitize_options()` on partial input,
`saving_for()` on a variable product, the `{countdown}` tag, dismissal, the empty
top bar, and all 8 templates.

Harness is in `kohthai-campaign-bar/test-kohthai-campaign-bar.php`:

    cd ~/wp-local
    php -c php.ini wp-cli.phar --path=site eval-file "<path>/test-kohthai-campaign-bar.php" --skip-themes

One upstream assertion was removed rather than fixed: the AUN harness checks that
the Bangla half of the countdown template strips cleanly. Kohthai's templates
carry no `*_bn` fields, so there is nothing to assert. That is a removed test for
a removed feature, not a silenced failure.
