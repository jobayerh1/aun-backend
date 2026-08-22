# AUN Projector Bangladesh — App Development Handoff

_Last updated: 2026-07-23. Read this first when continuing in a new session._

A native Android customer app for AUN projector buyers (Bangla-first, English toggle),
backed by the existing WordPress/WooCommerce site + UltimatePOS ERP. **Not a WebView.**

---

## 1. Where everything lives

| Piece | Path |
|---|---|
| **Flutter app source** | `C:\dev\aun-app` |
| **Backend plugin (source)** | `<workdir>\aun-app-api\` and zipped `<workdir>\aun-app-api.zip` |
| **ERP endpoint files** | `<workdir>\ERP\AppLookupController.php`, `<workdir>\ERP\WarrantyApiController.php`, `<workdir>\ERP\slb-erp-sync.php` |
| **APK builder (user double-clicks)** | `<workdir>\build-aun-app.cmd` → outputs **`AUN-Care-Bangladesh.apk`** + `aun-app-build.log` |
| **Toolchain** | Flutter `C:\dev\flutter`, Android SDK `C:\dev\android-sdk`, JDK21 `C:\dev\jdk-21.0.11+10`, pub cache `C:\dev\pub-cache` |
| **Warranty plugin (reference)** | `<workdir>\AUN Warranty & Registration.php` (a.k.a. SLB Warranty) |
| **Spare parts plugin (reference)** | bench: `wp-local\site\wp-content\plugins\aun-spare-parts\` |
| **Local WP test bench** | `C:\Users\Jobayer Hossain\wp-local` (PHP 8.2 + WordPress-on-SQLite + wp-cli) |
| **Bench tests / seed (scratchpad)** | `test-app-api.php` (44), `test-app-api-v2.php` (42), `seed-slb.php`, `debug-col.php` |

`<workdir>` = `C:\Users\Jobayer Hossain\Downloads\Claude session`

Current versions: **app 2.1.1+109**, **plugin 1.98.0 (DB v21)**, **spare-parts 0.40.0 (DB v10)**,
**projector wizard 3.5.0**.

📋 **Play Store: see `PLAY-STORE-READINESS.md`** — the full pre-flight list, with the Data safety
answers already worked out and an ordered plan for what to do while D-U-N-S is pending.

## 2026-08-22 (3) — RESTORE POINT before Phase 3: git tag `phase2-final`

Owner: *"our app is almost perfect now — keep the current condition somewhere, if something goes
wrong we can revert back to this version."* Done, in **both** repos.

| repo | commit | tag |
|---|---|---|
| `C:\dev\aun-app` (Flutter) | `6863a17` | `phase2-final` |
| workdir (plugins, ERP, docs) | `09db1aa` | `phase2-final` |

Captured state: **app 2.1.1+109 / app-api 1.98.0 (DB v21) / spare-parts 0.40.0 (DB v10) /
wizard 3.5.0**.

⚠️ **A tag on the last commit would have been WRONG.** Both repos' newest commits were dated
08-18 — four days of work (the track-parcel chip, the repair status chip, `fetch_media()`'s local
route and `s-maxage`, the spare-parts crash guard) was sitting UNCOMMITTED in the working tree. A
tag alone would have created a restore point that silently threw all of it away. Everything was
committed first, then tagged.

**To go back:** `git checkout phase2-final` in the repo concerned, or `git reset --hard phase2-final`
to abandon Phase 3 outright. Do it in BOTH repos — an app rolled back against a Phase 3 backend, or
the reverse, is a half-revert.

⚠️ **`backup-to-github.cmd` does NOT push tags.** It pushes commits only, so the restore point
exists on this PC alone until somebody runs `git push origin phase2-final` in each repo. Worth doing:
the whole point of a restore point is surviving the disk it was made on.

## 2026-08-22 (2) — app 2.1.1+109: one courier chip, used twice

Owner liked the repair track-parcel chip and asked for the same style on spare parts.

⚠️ **Shared the WIDGET, not the appearance.** Spare parts had an underlined blue link while repairs
had a filled chip — the same action, in the same app, wearing two different clothes. Both now render
`_TrackParcelChip`. Copying the styling would have looked identical today and drifted the first time
either side was touched; sharing the widget means a change to the icon, the wording or a future
"copied" confirmation lands in both.

Behaviour rules carry over unchanged: the chip appears ONLY when the server produced a URL, so a note
typed into the tracking field still shows nothing rather than a chip opening a courier page that
finds nothing.

## 2026-08-22 — app-api 1.98.0: repair PHOTOS were the one call still going the long way

Owner: *"the server is optimised, so why do repair images still take several seconds?"* **Our code,
and one half of it was a miss when the local route was wired in.**

Measured live: `/repair-image?file=nope.jpg` took **0.8-2.7 s just to return an ERROR** - so the
time was not the image bytes.

⚠️ **`AUN_App_ERP::fetch_media()` never got the local route.** `get()` and `repair_get()` were wired
up on 2026-08-19; the media proxy was missed. It is the call where it matters MOST: a repair screen
fetches every job-sheet photo through it, so ONE screen paid the Cloudflare round trip six or seven
times - on the largest payloads in the app, where the connection setup repeats on the slowest
transfers. **When adding a cross-cutting optimisation, enumerate every call site rather than the
ones you happen to be reading.**

⚠️ **No `s-maxage`, so Cloudflare never cached a single photo.** The response carried
`max-age=86400`, which is what BROWSERS read; a shared cache reads `s-maxage`, and without it
Cloudflare left it `DYNAMIC`. Every viewing re-fetched every photo from the ERP through WordPress.
Now `public, max-age=86400, s-maxage=2592000, immutable` - safe because the URL names one immutable
file (the ERP uploads a new NAME, it never rewrites one) - plus `Vary: Accept-Encoding` to stop the
host default `Vary: User-Agent` shredding the edge cache.

**Owner action:** add `/wp-json/aun-app/v1/repair-image` to the Cloudflare cache rule; the header
makes it eligible but the rule lists paths explicitly.

## 2026-08-22 — REFERRAL AUDIT (no code change): ERP returns are already handled

Owner asked how unlocking works and what a sell return in the ERP does.

**Unlocking is not "an order in the ERP".** `is_established_customer()` accepts EITHER a WooCommerce
order in a payout status OR a registered projector ("a serial we sold" - how offline buyers qualify
at all). Deliberately stricter than `has_purchase_history()`, and the code comment records the
loophole that forced the split: *order at 10am, mint a code, invite the neighbourhood, refuse the
parcel at the door.*

**ERP sell returns ARE handled.** The hourly sweep calls `lookup_serial()` per device:
`returned` -> unlinked at once; sale deleted -> 3 strikes then unlinked; **ERP unreachable ->
skipped, never treated as "they never bought it"**.

**And the obvious loophole is closed:** `claim()` re-checks `can_invite( $referrer )` LIVE at the
endpoint, not just in the app UI - so a code shared before a return stops working within the hour.

⚠️ **The one real gap is a PROCESS one, not code.** Rewards reverse on WooCommerce refunds
(`on_order_reversed` + `on_partial_refund`). A website sale returned **in the ERP only**, without
refunding or cancelling the Woo order, fires nothing and the referrer keeps the reward.
**Staff rule: a returned website sale must be refunded in WooCommerce, not just in the ERP.**

Judgement recorded: rewards already earned SHOULD survive the referrer later returning their own
projector - the friends' purchases were real. Changing that is a policy decision, not a bug fix.

## 2026-08-21 (2) — app 2.1.0+108 / app-api 1.97.0: repair status chip + track-parcel chip

### Referral programme: audited, NOT the mu-plugin (unresolved, needs a symptom)

Owner suspected the mu-plugin broke referrals. Audited rather than guessed: `AUN_App_Referrals`
depends only on WooCommerce (**kept**) and its own `AUN_App_*` classes, and the payout hook
`woocommerce_order_status_changed` fires on website checkout, wp-admin, the SSLCommerz callback and
cron — **none of which the mu-plugin touches** (`is_admin()` + a URI test for `/wp-json/aun-app/`).
On the one path it does touch, `aun-app-api` is kept, so the hook is still registered.

**No mechanism found. The A/B to settle it:** `define( 'AUN_API_LEAN_OFF', true )` in wp-config,
retest, remove. Still open — nobody has said WHAT looks wrong.

### The repair row was speaking a different language from the spare-parts row

Spare parts showed a status CHIP; repairs showed bold coloured Text. Side by side on one screen, the
text read as a heading rather than a state. Repairs now use the same `statusChip`.

⚠️ **On its own line, NOT inline like spare parts.** Workshop statuses are long ("Repair Not
Authorized – Customer Unresponsive") and would crush the ref; `Flexible` lets one wrap inside the
pill instead of overflowing the card.

⚠️ Coloured by STATE, not wording: `isFinished` (which includes `erp_completed`) goes green as soon
as the ERP says so, without waiting for the 10-minute poll — but **never overrides a rejection**,
which must stay red.

### Track-parcel chip, mirroring slb-repair-tracker

The service centre types a consignment into an engineer's note ("Sent by Pathao DA200826WQJCJ5"), and
the website turns it into a tappable chip. The app now does too.

**Extraction is SERVER-side** (`AUN_App_ERP::consignment_in()`), and the URL still comes from the
shared `AUN_App_Services::courier_tracking_url()` — so only the "find it in prose" rule is new.
⚠️ **That rule mirrors the tracker plugin's JAVASCRIPT regex** (2-3 letters, 6-8 digits, 4-10
alphanumerics) and must be kept in step, or the website and the app will disagree about whether the
same note contains a trackable parcel.

⚠️ **A bug worth remembering:** writing the pattern through a shell heredoc put a literal BACKSPACE
byte (0x08) into the PHP where `` was intended. The regex would have matched nothing, ever, and
the feature would have looked implemented while doing nothing. Caught only by testing against real
notes. **After writing a regex through any tooling, print it back and look at it.**

**UI choices:** a filled chip, not a link — underlined text mid-paragraph is easy to read straight
past, and this is the one thing on that line the customer can act on. It shows the NUMBER as well as
the action, because somebody phoning the courier has to read it out.

**Tests:** extraction verified 6/6 on the bench against real IDs from the owner's screenshots and on
ordinary notes ("replaced the LCD panel, tested 30 minutes" -> nothing).

## 2026-08-21 — LIVE INCIDENT: a third-party plugin white-screened the Spare Parts admin

Owner saved a delivery charge on request 17 and got a WordPress critical error. **Not caused by the
app work or the mu-plugin** — that bails out twice on an admin URL (`is_admin()`, and the URI does
not contain `/wp-json/aun-app/`). Verified before anything else was touched.

**What happened**, reading the trace bottom-up:
`sync_delivery_charge()` -> `create_order()` -> `wc_create_order()` -> WooCommerce fires its order
hooks -> **"Connect for Yeamazing" throws `get() on null`** at `YEAMCO_WcHooks.php:119`. It expects a
cart or session that does not exist in wp-admin: written for storefront checkout, and the Spare Parts
screen creates orders from the admin side.

The bug is theirs. **Taking the whole screen down was ours to prevent.**

### spare-parts 0.39.0

`create_order()` is now a thin wrapper with a try/catch; the work moved to a private `build_order()`.

⚠️ **`\Throwable`, NOT `Exception`.** "Call to a member function on null" is a PHP **Error**, which
sails straight past `catch ( Exception $e )`. Getting this wrong would look like a fix and change
nothing.

⚠️ **`sync_delivery_charge()` was lying.** It returned `'updated'` unconditionally, so a failed
rebuild still told the admin "Delivery charge updated on the customer's order" — they would have sent
a payment link carrying the OLD total. It now returns `'failed'` and the notice says the charge was
saved but the payment order was not.

⚠️ **A leaked mute window.** `create_order()` calls `mute_on()` early but could return without
`mute_off()`, leaving a `pre_http_request` filter armed for the rest of the request and silently
blocking other plugins' outbound HTTP. The catch block closes it.

**Tests:** NEW bench `test-order-crash-guard.php` (**4**) — reproduces the incident with a hook that
throws the same `Error` shape, and asserts the page survives, the admin is told the truth, and orders
build again once the bad plugin is gone. ⚠️ **Reaching the final line IS the assertion**: a
regression fatals the run rather than printing a FAIL.

**Still open for the owner:** Yeamazing is still broken, it just cannot take the screen down now. If
it is unused, deactivate it and the problem is gone entirely; if it is used, its author needs to know
their hook fatals on admin-created orders.

## 2026-08-20 (3) — app 2.0.3+107: the photo rail reaches the screen edge

Owner asked whether the count in the heading was the right call, or whether a scrollbar would be
better. **The count is right and the scrollbar was wrong** — a scrollbar answers "where am I in this
list", which is meaningless for six photos, and it draws over the content (which is exactly what the
owner saw). Gmail, iOS Photos, Amazon and the Material 3 carousel guidance all use PEEK; WhatsApp and
Drive put the COUNT in the header. This rail now has both, and dots were rejected because they belong
to one-at-a-time carousels, not multi-item rails.

The one real weakness that discussion exposed: the rail sat inside the page's `EdgeInsets.all(16)`,
so an overflowing tile was clipped **at the content margin**. That reads as "the strip ends here and
the last one is oddly cut"; a tile clipped by the **screen edge** reads as "this continues", which is
the entire job of a peek.

⚠️ **Flutter has no negative padding.** The idiom is `OverflowBox`: give the child a constraint
`maxWidth + 32` and centre it, so it spills 16 px past each side; the ListView's own
`padding: horizontal 16` then puts the first and last tiles back on the page margin, so only a
SCROLLED tile ever reaches the edge.

⚠️ **Nothing is clipped, and that is by construction rather than luck:** the page's ListView viewport
is already full-width and its padding insets CONTENT, not the canvas — so painting into that margin
is legitimate. If the rail is ever moved inside a Card or a clipping parent, this breaks and the
tiles will be cut at the old margin again.

## 2026-08-20 (2) — app 2.0.2+106: the photo rail, third attempt and the right one

Owner, on the 2.0.1 build: *"the thumbnail is like this new design now, and the scrollbar appears on
them. please fix the design and make it beautiful."* Both fair. A screenshot showed portrait phone
screenshots stranded between wide grey bars, and the scrollbar drawn across the bottom of the first
photo.

⚠️ **Two attempts, wrong in opposite directions, because both treated it as a choice of BoxFit:**

| tile | result |
|---|---|
| fixed landscape + `cover` | filled the frame; closing a portrait photo JERKED (viewer is `contain`) |
| fixed landscape + `contain` | flight smooth; every portrait photo LETTERBOXED |

**The fit was never the variable. The SHAPE was.** New `_PhotoThumb` sizes each tile to its own
image's aspect ratio: one shared height (116) so the rail keeps a straight top and bottom, width from
the photo. When the tile matches the photo, `cover` and `contain` render identically — it fills its
frame AND the Hero flight is geometrically exact end to end.

Details that matter:
- Asks the image its shape through the SAME `ImageProvider` it then draws, so it resolves from
  Flutter's cache — no extra download.
- ⚠️ Aspect **clamped to ~0.62–1.78**. A panorama would fill the whole rail and hide that there are
  five more photos; a very tall shot would shrink to an unrecognisable sliver.
- `AnimatedContainer`: the true shape arrives a frame or two after first paint, so the tile eases
  from a neutral 4:3 rather than snapping.

⚠️ **The Scrollbar is REMOVED, and should not come back.** It was added for discoverability and drew
over the photo — a horizontal scrollbar lives inside the scroll view's own box, and the tiles filled
that box. The count in the heading plus the half-visible next tile carry that job. Varied tile widths
help too: a rail of identical rectangles could plausibly end at the screen edge; irregular ones read
as continuing.

**Left alone deliberately:** the spare-parts reference image (`services_screens.dart`) is a 48 px
square badge beside a checkbox, not a rail. It is `contain` at every stage, so it is self-consistent
and has nothing to tear; aspect-sizing it would break the checkbox row.

⚠️ **Process note:** `AunMotion.quick` was used without importing `motion.dart`, and a watcher that
matched on the wrong word led to reporting analyze as clean before it had finished. It had not — the
missing import would have failed the build. **Match a watcher on the tool's OWN completion string,
never on a word that another line of output might contain.**

## 2026-08-20 — app 2.0.1+105: two layout bugs the owner spotted on a real phone

### 1. The tracking link drew straight through the status text

The part row was a `ListTile`, and its halves fought for width with nothing bounded: `trailing`
carried a status label as long as "Dispatched to customer (courier)" and took whatever it wanted,
while `subtitle` carried the ETA AND the tracking link.

⚠️ **ListTile is for a title, one line of detail and a SMALL trailing widget.** This row has six
pieces of information. Replaced with `_PartRow`: left side `Expanded` (long names and consignment
numbers wrap), right side `ConstrainedBox(maxWidth: 116)` (the status label can no longer push into
the left side; it wraps to two lines), and the tracking link on its OWN line with the number
`Flexible` so it wraps inside the link rather than shoving the icon off the edge.

### 2. The image viewer jerked and stretched on close

Owner: *"it zooms out then sudden jerk and it stretched to the landscape thumbnail again."* Three
stages, and one disagreed:

| stage | fit |
|---|---|
| thumbnail | **cover** |
| Hero flight shuttle (`heroImageShuttle`) | contain |
| full-screen viewer | contain |

So the return flight shrank the photo correctly and then, **on its very last frame**, swapped to a
cover-cropped thumbnail. Worst on the PORTRAIT photos the service centre uploads, because cover
crops those hardest.

⚠️ **All three stages must use the same BoxFit or a Hero tears at the hand-off.** Now `contain`
everywhere, on a tinted tile so a portrait photo has somewhere to sit instead of being cropped.
Fixed in BOTH places that use `heroImageShuttle` with a thumbnail — the repair job-sheet photos and
the spare-parts reference photos had the identical bug.

**Also (owner observation):** a horizontal rail that runs off the screen edge looks exactly like a
rail with nothing more in it. The heading now reads "Attachments (5)" when there is more than one,
and an always-visible `Scrollbar` sits under the rail — one that appears only once you are already
scrolling cannot tell you that scrolling is possible. ⚠️ It needs its OWN `ScrollController`: the
page's PrimaryScrollController belongs to the vertical list, and handing that to a horizontal rail
draws a bar tracking the wrong axis.

The full-screen viewer already supported swiping through every photo with a "2 / 5" counter. That
was working — just undiscoverable from the rail.

## 2026-08-19 (2) — THE BIG ONE: app REST bootstrap 1300 ms -> 110 ms

`mu-aun-api-lean.php` (in the workdir; installs to `wp-content/mu-plugins/`) drops **49 of the 68
active plugins** on requests whose URI contains `/wp-json/aun-app/`. Measured from outside, same
server, same minute — the ONLY difference is the URL path:

| route | TTFB |
|---|---|
| `/wp-json/aun-app/v1/ping` (trimmed) | **100-123 ms** |
| `/wp-json/wp/v2/types` (not trimmed) | **1260-1620 ms** |

`/config` measured **1136 ms** on 2026-08-15 and **72-210 ms** after. ~90% of the app's server time
was WordPress loading a slider suite, three SEO plugins, Site Kit, Facebook for WooCommerce and a
file manager, on requests that return JSON.

**This dwarfs everything else done for speed this week.** The local ERP route saved ~90 ms; this
saved ~1200 ms.

⚠️ **`aun-latency-probe.php` CANNOT measure this.** The probe is a direct `.php` file, not a REST
request, so the mu-plugin's URI test never matches and Section D still reports 68 plugins and ~630 ms.
That is correct behaviour, not a failure — but do not read Section D as "the trimming did not work".
Measure with `curl` against `/wp-json/aun-app/v1/ping` instead.

**Kept, and why** — traced from real calls, not guessed: WooCommerce, SSLCommerz, smart-coupons,
spare-parts, warranty, erp-sync, repair-tracker, projector-wizard, throw-calculator, help-center,
alpha-sms + otp-login, maintenance-sms, wpo365 mailer, and **wordfence** (the API is the most exposed
surface we have; never unload the firewall). TranslatePress is kept deliberately —
`AUN_SP_I18N::req_lang()` falls back to it, and dropping it would make Bangla quietly return in
English on some responses.

⚠️ A DENY-list, not an allow-list: a deny-list can only remove what is named, so the blast radius is
readable. Off switch: `define( 'AUN_API_LEAN_OFF', true )`.

### Still open after this

- ⚠️ **1.96.0's edge-cache headers are NOT live** — `/config` still answers `public, max-age=0`
  (the WordPress default), so the Cloudflare cache rule caches nothing. Check the deployed version in
  wp-admin. Worth much less now that the bootstrap is 110 ms, but the rule is inert until it is fixed.
- **APCu IS available and enabled** (cPanel screenshot + probe both confirm) — an earlier note here
  said to skip the object cache because Namecheap has no Redis/Memcached. That was right about
  Redis/Memcached and wrong about the conclusion. APCu can back a WordPress object cache.
- **The WEBSITE is still slow** (1.3-1.6 s). The mu-plugin only trims app routes. Shop pages need
  those plugins, so the same trick does not transfer — an object cache is the lever there.
- **Autoloaded options: 283 KB / 1434 rows = healthy** (under 300 KB). But it holds dead data from
  plugins removed years ago: WPML (`icl_*`, `wpml_*`, `otgs_*`, ~60 KB), Yoast (`wpseo_titles`),
  P3 Profiler (`p3_scan_*` dated 2019), Jetpack, Kirki. Tidy, not urgent.
- ⚠️ **Cloudflare's route to the ERP degraded badly during this run** — 636 ms vs 25 ms pinned
  (TCP connect alone was 82 ms). The local route insulated the app completely. That is the value of
  AUN_App_Local_Route showing up in the wild.

## 2026-08-19 — app-api 1.95.0: MEASURED, then fixed — the ERP/osTicket hop stays on the machine

Owner asked whether the WordPress -> ERP / -> osTicket "handshake" could be sped up. It was measured
on the live server with `aun-latency-probe.php` (kept in the workdir; admin-only, read-only, works
via `wp eval-file` OR a browser visit).

### ⚠️ The first hypothesis was WRONG, and the measurement said so

The guess was "the TLS/DNS handshake is the cost". It is not:

| call | DNS+TCP | waiting for the reply | total |
|---|---|---|---|
| ERP | 15.9 ms | 173.7 ms | 189.6 ms |
| bridge | 13.9 ms | 337.1 ms | 351.0 ms |

Handshake is **4-8%** of the call. The rest is the other application thinking. **Do not go back and
optimise the handshake.**

### ⚠️ And the probe's OWN verdict line was wrong — a 404 is not a fast answer

v2 reported "96% saving" by comparing against `127.0.0.1` runs that returned **HTTP 404**: loopback
answers on :443 but with the WRONG vhost. The verdict took the fastest local run without checking it
was a VALID one. **Whenever a benchmark compares two routes, assert the status code first.**

Real comparison, 200-only, best of three:

| call | via Cloudflare | via server IP | saving |
|---|---|---|---|
| ERP | 132 ms (worst 187) | **33 ms** (worst 38) | ~99 ms (75%) |
| bridge | 103 ms (worst 302) | **17 ms** (worst 26) | ~86 ms (83%) |

The WORST case is the prize: Cloudflare swung 103-302 ms, local sits at 17-26 ms.

### New `AUN_App_Local_Route`

`CURLOPT_RESOLVE` changes only the IP dialled — URL, Host header and TLS SNI are untouched, so the
vhost still routes and the request is byte-identical at the far end. Wired into `AUN_App_ERP::get()`,
`AUN_App_ERP::repair_get()` and `AUN_App_Tickets::call()`.

⚠️ **Pins to `SERVER_ADDR`, NEVER to 127.0.0.1** — loopback returned 404 for both hostnames when
measured. "Surely localhost is faster" is the edit that would break both services.

⚠️ **The IP is read from the server, never hardcoded** — a host migration cannot strand us pointing
at somebody else's machine. Captured on `init` from web traffic, because SERVER_ADDR does not exist
under WP-CLI or cron, which is when the polls run.

⚠️ **READS ONLY.** POSTs were wired up first and then deliberately removed: the failure-retry cannot
tell "connection died before anything happened" from "the reply was accepted and the answer got
lost", so a retried POST could post a customer's support reply twice. The shortcut buys ~100 ms and
nobody notices that on a button they pressed on purpose.

⚠️ **Certificate verification is off for pinned calls only.** The origin serves a Cloudflare Origin
Certificate, which cannot validate publicly — that is what failed in v1. Verification proves you
reached the intended machine; here that machine is the one running the code.

**Backs off on any failure** for 10 minutes and immediately retries the normal way, so a broken
shortcut is one slow call, never a broken repair screen. Off switch: `AUN_APP_NO_LOCAL_ROUTE` or the
`local_route` option. `AUN_App_Local_Route::status()` reports on/off/backed-off for an admin screen.

**Verified on the bench:** declines to arm with no IP known; arms once the IP is known; declines for
this site's own hostname (self-deadlock guard); `enabled()` false after a failure.

**Perspective, still true:** ~90 ms saved sits inside a request already paying **~1000 ms of
WordPress bootstrap**. Worth having, but the elephant is unchanged.

## 2026-08-16 (7) — app-api 1.94.0: ticket threads cached; a probe for the ERP hop

Owner asked whether the WordPress→ERP / WordPress→osTicket "handshake" can be sped up, since all
of it lives on the same Namecheap machine.

### The hypothesis worth testing (NOT yet confirmed)

Both `AUN_App_ERP::get()` and `AUN_App_Tickets::call()` reach their service by its PUBLIC hostname
through `wp_remote_get`. With DNS on Cloudflare that likely means the call leaves the server, goes
to a Cloudflare edge, and comes back to the SAME machine — paying a DNS lookup, a TCP handshake and
a full TLS negotiation for a service one directory away, on every app request that needs a repair
status or a ticket.

⚠️ **This is a hypothesis, not a measurement.** Server-to-server latency is invisible from a Claude
session; only the owner's server can answer it. Hence:

**NEW `erp-latency-probe.php`** — run on the LIVE server with `wp eval-file`. Read-only, prints no
secrets. For the ERP and the bridge it reports which IP the call actually reaches and where the
milliseconds go (DNS / TCP / TLS / first byte), then retries both pinned to `127.0.0.1` via
**`CURLOPT_RESOLVE`** — which keeps the URL, Host header and TLS SNI identical, so vhost routing and
certificate validation still work and only the dialled IP changes.

Three outcomes, all useful: a Cloudflare address on the normal route confirms the hairpin; a FAILED
local attempt means the shortcut is unavailable (many shared hosts block connections to their own
IP) and the idea is dead; and if `first byte − tls done` dominates, it is the other service's own
thinking time and no networking change will help.

### ⚠️ Fixed without waiting: ticket threads had NO cache at all

Repairs have cached their ERP lookup for 2 minutes since day one. `AUN_App_Tickets::thread()` cached
nothing, so every open, re-open, back-navigation and pull-to-refresh paid a full round trip to
osTicket through the bridge — the slowest hop in the app.

Now **45 seconds**, and the number is deliberate rather than copied from repairs: a ticket is a
CONVERSATION. Two minutes would hide a staff reply that has already fired a push, so the customer
taps the notification and sees nothing new.

**Two invalidations make it safe:**
- `reply()` clears it — otherwise the customer posts a message and gets back a 45-second-old thread
  without it.
- `poll_replies()` clears it per affected customer BEFORE sending the push — that is precisely the
  "tapped the notification, saw nothing" case.

The cached value is the FINISHED shape, not the raw bridge body, so the HTML cleaning (data-URI
markers, the agent-only context table, cid: images) is not redone on every open.

**Tests:** NEW bench `test-ticket-cache.php` (**9**). A support thread is the most private thing in
the app, so a key collision here is a DISCLOSURE, not a slow page — the suite asserts that the same
ticket viewed by two different customers gets different entries, that an extra address on the
account is a different view, and that clearing one customer's copy leaves the other's intact.

## 2026-08-16 (6) — app 2.0.0+104: two detail screens that had the data and waited anyway

Owner: *"spare-parts requests open instantly, repairs take a few seconds, support tickets too — is
that the ERP, or unoptimised code?"* **Both, and the app was the bigger half of each.**

Server side is genuinely slow and already documented: ~1.1 s of WordPress bootstrap on every
request, plus a live ERP call for repairs (2-min cache, so a re-open is quick) and the osTicket
bridge for tickets — the slowest hop in the app. Neither is fixable from Dart.

### Repairs — the screen was handed everything and drew a spinner

`RepairDetailScreen` receives the whole `RepairEntry` from the list (ref, model, issue, status, live
`erpStatus`, job sheet, courier, tracking). `build()` even computed `_detail?.request ?? widget.entry`
— and then short-circuited on `_detail == null ? CircularProgressIndicator()`, so **that fallback
could never be reached on the first frame**. Same defect as the notification centre, different
screen. Only the ERP card waits now (`_ErpPending`, which shows the status the list already knew —
a skeleton with no information is a spinner wearing a card).

⚠️ **The flash this nearly caused, and the reason to be careful when painting early:** the "post it
to us" card renders when `erp == null`, which used to mean "no job sheet". Painting before the fetch
made that briefly true for EVERY repair — so a customer whose projector is already on our bench
would see "courier it to us" for a second. Now gated on `!entry.isLinked`, which the list row knows.
**When you make a screen paint earlier, re-check every `x == null` branch: they were written knowing
the data had arrived.**

### Tickets — the summary was thrown away at the door

`TicketThreadScreen` took only `number`, so it genuinely had nothing to draw while the bridge
answered. Now takes an optional `summary`; the header (subject + status) paints instantly and only
the messages wait. **Optional on purpose:** the notification router and deep links really do have
only a number.

**Honest limit:** this makes both screens FEEL instant without being faster. The ERP and bridge calls
take as long as they take. Real gains need the server work from the 2026-08-15 connection audit.

⚠️ **Version 2.0.0 was my choice, not the owner's** — 1.99.0 had nowhere natural to go, and the Play
listing is a reasonable milestone for it. 1.100.0 is equally valid if they prefer to keep the 1.x
line; the build number (104) is what actually matters.

## 2026-08-16 (5) — app 1.99.0+103 / app-api 1.93.0: the app DELEGATES the courier link

Owner fixed the tracking-link bug reported last round, in **spare parts 0.38.0**: new
`AUN_SP_Requests::courier_url()` + `clean_consignment()`, used by the admin badge AND the website
tracker, plus normalisation on save (a value that isn't a consignment ID is refused, the old one
kept, and the admin gets a red ⚠).

`AUN_App_Services::courier_tracking_url()` now **calls the plugin's helper** instead of building the
URL itself (fallback only for a plugin older than 0.38.0).

⚠️ **Why the app's own version was not good enough, and it is a lesson about length checks:** the
plugin rejects anything failing `/^(?=.*\d)[A-Za-z0-9]{8,32}$/`. A note typed into the box —
"i will add later" — collapses to `iwilladdlater`, a respectable 13 characters, which the app's
version would have turned into a link to a Pathao page that finds nothing. **Requiring a DIGIT is
what separates an ID from a sentence**; length alone never does.

### ⚠️ CORRECTION to the previous entry — no plain-text fallback

The 1.98.0 note argued a number with no URL should render as PLAIN TEXT, on the grounds that a dead
link is worse than an honest label. Half right, wrong conclusion. After 0.38.0 an empty URL means
**the stored value is not a tracking number**, and `sp-track.js` renders the chip ONLY when
`tracking_url` is non-empty — the website shows nothing at all. Printing
`Tracking: i will add later` to a customer is not an honest label, it is leaking a staff note. The
app now hides it too.

**That is the second time in two rounds the app displayed something the website deliberately
withholds** (the first was the progress-history leak). When in doubt, look at what the website does
with the same field before inventing a fallback.

**Tests:** `test-parts-billing.php` now **37**. The important addition is a PARITY LOOP — for a real
ID, a note, an empty string, a foreign courier URL and a spaced/dashed ID, the app's output must
equal `AUN_SP_Requests::courier_url()` byte for byte. That is the assertion that fails the day
somebody reimplements this in the app again.

**Known and accepted:** rows written before 0.38.0 keep whatever is stored, so they show no chip in
the app or on the website until the request is re-saved. The admin's red ⚠ badge is how staff find
them.

## 2026-08-16 (4) — app 1.98.0+102 / app-api 1.92.0: the app was showing customers our staff notes

Owner shipped **spare parts 0.37.0** and asked for the app to follow. The new work over 0.36.0 was
`PUBLIC_EVENTS`, re-worded money lines, and the i18n strings for them.

### ⛔ The app's progress history was leaking internal notes

The app filtered the timeline with a **deny-list**
(`type NOT IN ('sms','contact_changed','wc_order','quote_reminder')`) and printed the raw stored
message. Exactly as the plugin's own comment predicts, every event type added afterwards leaked by
default. Two were reaching customers:

- `refund_due` → **"REFUND DUE — ৳3,400 was paid online and the request is now declined."**
  An instruction to our staff, sitting in the customer's progress list.
- `duplicate_confirmed` → "Customer was warned this overlaps SP-0042 and chose to submit anyway"

and `payment` printed raw as *"Online payment received for order #9275 via SSLCommerz"*, exposing
WooCommerce order numbers.

Now reads **`AUN_SP_Requests::PUBLIC_EVENTS`** (guarded, with a literal fallback for an older
plugin) so the next event type the plugin adds is private here automatically, and re-words
payment/refund through `AUN_SP_I18N::msg( 'tl_payment' … )` like the website tracker.

**New `X-AUN-Lang` header** carries the app's language to every endpoint. ⚠️ A HEADER, not a query
parameter, and not a value captured at construction: language is a property of the phone rather than
of one call, a new call site cannot forget it, and `ApiClient.getLang` is a CALLBACK because the
customer can change language long after the client is built.

### The tracking number is now a link (owner request)

`tracking_url` is sent per item and the number renders as brand-blue underlined text with an ↗ icon
and a padded tap target.

⚠️ **Built on the SERVER, never in Dart.** The consignment-URL pattern belongs to the spare-parts
plugin; a copy in the app would be a third place to change when AUN switches courier — and the app
is the one nobody would remember, because a wrong link still LOOKS like a link.
⚠️ **No URL ⇒ plain text, not a dead tappable.** A link that does nothing is worse than an honest
label: the customer taps it again and concludes the app is broken.

### ⚠️ Open plugin bug, NOT fixed (needs the owner's call)

The admin field is labelled **"Pathao ID / URL"**, but both the admin badge
(`class-aun-sp-requests.php:652`) and the website tracker
(`class-aun-sp-tracking.php:165`) push whatever is typed into `?consignment_id=`. Paste a whole URL
— as the label invites — and the link becomes `?consignment_id=https%3A%2F%2F…`, which opens a
Pathao page that finds nothing. `AUN_App_Services::courier_tracking_url()` passes a URL through
unchanged, **so the app is now correct where the website is not**. The clean fix is one shared
helper in the plugin used by all three; not done because the owner had just edited those files.

**Tests:** `test-parts-billing.php` now **29** — the leak cases are asserted by absence (REFUND DUE,
SP-0042, `#9275`, the SMS log) with a real progress event asserted present so the filter cannot pass
by hiding everything; plus the pasted-URL passthrough and the empty-number case.

## 2026-08-16 (3) — app 1.97.0+101 / app-api 1.91.0: the app could charge twice

Owner fixed bugs in **spare parts 0.36.0** and asked for the app to follow. Reading the diff found
the app carrying the SAME defects, plus one of its own.

### The plugin's two new rules

- **`NOT_CHARGEABLE = ( unavailable, cancelled )`** — a part we can't supply, or that isn't going
  ahead, keeps its price in the history but drops out of the amount owed.
- **`payable_state( $id )`** — the ONE gate for online payment. Refuses when the request is
  completed or cancelled, **or when every priced part is already `dispatched`/`delivered`
  (`HANDED_OVER`)**: on cash on delivery the courier collects the money, so a Pay button after
  dispatch asks for it a second time.

### ⚠️ The app had the double-charge bug in TWO places

`can_pay` excluded `quote_sent/rejected/declined/expired` but **not `closed`, and not dispatched
parts** — so a delivered COD request still showed "Pay online". The **pay endpoint** had the same
gap, and that is the one that takes money: a screen left open before dispatch, a back button or a
replayed POST reaches it with the button never visible. Both now call `payable_state()`.
**The plugin's own comment is the rule: "the button is only a hint."**

### ⚠️ Found while wiring it up: `payable` was built from a stale column

The app sent `quote_total + delivery`, but `quote_total` is a STORED column that only refreshes when
an admin saves the request. Mark a part unavailable and the app kept charging for it until someone
re-saved. `payable` now comes from `payable_state()['money']`, which reads the lines. The same stale
value was also used to decide whether an existing unpaid order still matched — so it would call a
wrong order "the same" and skip the rebuild that fixes it.

**App changes:** `chargeable` per item in the payload; `SpRequestItem.chargeable` (**defaults true**
so an older server keeps today's behaviour); non-billed parts render struck-through with
`notCharged` (en/bn) rather than being hidden — the customer asked for that part and deserves to
know what happened to it; and the payment breakdown's parts line is derived from `payable`, never
`quoteTotal`, so parts + delivery always add up above the button that takes the money.

Every plugin call is `method_exists`-guarded — an older spare-parts plugin degrades to the previous
behaviour instead of fataling.

**Tests:** NEW bench `test-parts-billing.php` (**18**) — run against the real 0.36.0 plugin: what is
owed with unavailable/cancelled lines, every stop-paying case, and the app payload itself (payable
excludes the unsuppliable part, all three parts still SHOWN, the delivered request offers no Pay
button).

⚠️ **Worth knowing before adding any SMS to the app:** the plugin's new `block_foreign_sms` mutes
third-party SMS during ITS order transitions and identifies "ours" via `AUN_SP_SMS::is_sending()`.
`AUN_App_SMS` is a different class, so an app SMS to the same customer during a spare-parts order
transition would be silently blocked. Nothing in the current flow does this.

## 2026-08-16 (2) — app 1.96.0+100 / app-api 1.89.0 (DB v21): how a repair ENDS

Owner asked how the repair cycle closes, given their ERP has 16 statuses of which only 2 are flagged
completed — and what happens if a job sheet is deleted. Tracing it found three defects.

### The closing path itself is correct

`poll_repair_statuses()` (10-min cron) → `maybe_close()` reads **`$erp['completed']`**, i.e. the
ERP's own `repair_statuses.is_completed_status`. Set "Delivered / Collected" and within ten minutes
the row goes `closed`, the customer gets a final notification, and the poll query (which excludes
final rows) stops touching it forever.

### ✅ "Repair Deferred – Awaiting Parts" as a COMPLETED status is CORRECT — do not undo it

⚠️ **An earlier draft of this entry told the owner to untick it. That advice was WRONG and would
have created a dead end.** When parts are 4–5 weeks out, customers often ask for the projector back;
AUN returns it and the customer re-contacts them later. The device is out of our hands and nothing
further happens on that job sheet — it IS an ending.

And the tick is not merely acceptable, it is **required**: closing the app request is what releases
`open_repair_for()`'s duplicate lock. Untick it and the customer keeps a permanently open repair, so
when the parts arrive and they try to book again they are refused with "you already have a repair in
progress" — with no way out.

**The lesson, and it is the same one as the bug this release fixes:** the workflow was inferred from
the STATUS NAME instead of asking. A status label is not a specification of the business.

Worth changing (owner, ERP only): the label is the last thing that customer sees, and *"Repair
Deferred – Awaiting Parts"* reads as in-progress while the app closes the card. Something like
**"Returned – Awaiting Parts (contact us when ready)"** makes the words and the behaviour agree.

### 1. ⚠️ The app decided "finished" by matching ENGLISH WORDS in the status label

`home_status_strip.dart` had `_isDone()` testing the label for `deliver|collect|complet|closed|
cancel|reject`. Against the real 16 statuses it failed in both directions:

- **"Ready for Delivery / Collection" contains BOTH "deliver" and "collect"** → the Home card
  vanished at the single moment it mattered most: the projector was repaired and waiting to be
  collected.
- "Unrepairable – Parts Not Available" and "Repair Not Authorized – Customer Unresponsive" match
  nothing → they sat on Home forever.

**A status name is prose a staff member typed into an admin screen. It is not an API.** The ERP has
a real boolean and `class-aun-app-erp.php` even carries the comment *"never inferred from names"* —
the server had it right and only ever sent the LABEL, leaving the app to guess.

Fixed: `repair_entry()` now sends **`erp_completed`**; `RepairEntry.isFinished` (app final states OR
that flag) is the single definition; `_isDone()` is deleted with a tombstone comment where it stood.

⚠️ **`OngoingRequestNotice.repairFor()` deliberately still uses ONLY the app-status set.** It must
stay aligned with the server's duplicate guard — if the app used the looser test the form would
open and the server would then 409, which is exactly the "prevent, don't correct" defect fixed on
2026-08-13.

### 2. ⚠️ A DELETED job sheet froze the repair forever — and the fix was already in the code

The poll did `if ( ! is_array( $erp ) ) { skip; }`, collapsing two different failures. But
`repair_get()` **already distinguishes them and we were discarding it**:

| Return | Meaning |
|---|---|
| `WP_Error` | transport/config failure — the ERP is unreachable |
| `false` | HTTP 404 / `success:false` — there is no such record |

So a deleted job sheet was indistinguishable from an outage: the row was skipped for ever, the
customer's repair froze on "Projector received" with a "see the progress" button that could never
progress, and because `link_pending_repairs()` only considers rows with an EMPTY `job_sheet_no`, a
replacement sheet could never be adopted either. Nobody would ever have found out.

New `handle_missing_job_sheet()` + **DB v21 `erp_missing_since`**: an outage still touches nothing
(not even the clock); a definite 404 starts a clock and, after **24 h of UNBROKEN misses**, unlinks
and emails STAFF (never the customer — "our records lost your repair" is not actionable by them).

⚠️ **A 404 is only judged AFTER the whole poll run, and only when `$stats['checked'] > 0`.** The
first draft acted on each 404 as it arrived, which meant a broken deploy or a proxy 404-ing
everything could age healthy repairs into being unlinked. At least one OTHER job sheet resolving in
the same run is the proof that the ERP is answering and that "no such record" means what it says.

Sizing this correctly came from the owner: **job sheets are essentially never deleted here.** So the
cost of being slow is nil and the cost of being wrong is a live repair detached from its job sheet —
bias hard towards doing nothing. Known and accepted: if the ONLY active repair is the deleted one,
nothing can prove the ERP is up, so it is never unlinked and no email fires. **This is a safety net,
not a guarantee**, and that is the safe direction to fail.

⚠️ **Unlinking clears `job_sheet_no` but deliberately does NOT touch `status`.** Every status a
linked repair can hold is already in `REPAIR_LINKABLE`, so an empty job sheet number is all that
`link_pending_repairs()` needs to adopt a replacement — while winding the customer back from
"ready" to "received" would be a visible lie about where their projector is. Being wrong is cheap
and self-correcting: the next linking run re-matches by phone + serial.

⚠️ The v21 column is added in its OWN guard, not inside the v20 block — the v20 guard tests for
`courier_tracking`, so any site already on v20 would have skipped a bundled column entirely.

## 2026-08-16 — PRE-PUBLISH AUDIT: app 1.95.0+99 / app-api 1.88.0

Owner asked for a full audit before the Play Store listing — outdated code, bugs, loopholes,
security, UX. **Four real findings, all fixed. One recommendation deliberately NOT applied.**

### 1. ⛔ PLAY POLICY BLOCKER — the app updated itself from our website

`update_sheet.dart`, `force_update_screen.dart` **and** `settings_tab.dart` all sent the customer to
`config.apkUrl` — a direct APK download. **Google Play's Device and Network Abuse policy forbids an
app distributed through Play from updating by any route other than Play.** This is a rejection or
suspension risk, not a style point, and it was in three separate places (the third one, Settings,
was nearly missed — grep for the FIELD, not the screen).

⚠️ **Deleting the feature was not the answer**: the same binary still serves the side-loaded copies
the website distributes today, which genuinely need the APK link. New `AppState.installedFromPlay`
reads `PackageInfo.installerStore` (`com.android.vending` = Play), and **`AppState.updateUrl` is now
the ONE place** that decides — Play listing for a Play install, website APK for a side-loaded one.
`updateAvailable` follows the same rule: a Play install always has somewhere to go even when the
server sends no `apk_url`.

### 2. 🔐 The login token was being backed up to Google Drive

`android:allowBackup` was undeclared, which means **true**. The bearer token lives in
flutter_secure_storage, whose key stays in the Android Keystore and is **never** backed up — so a
restored copy is ciphertext nobody can decrypt, and the customer lands in a session that looks
signed in and is not. The local bookkeeping (dismissed rows, queued usage events) was travelling to
Drive too.

Now `allowBackup="false"` **plus two rule files**, because one is not enough:
`aun_backup_rules.xml` (Android ≤11) and `aun_data_extraction_rules.xml` (12+).
⚠️ **`allowBackup="false"` does NOT stop `<device-transfer>`** — the phone-to-phone copy during new
device setup — which is the case people actually hit. That needs the 12+ file.

### 3. 🔐 `/events` was an unauthenticated, unthrottled INSERT

`permission_callback => '__return_true'`, correctly (it records `app_open` and the login funnel,
which happen before anyone has a token) — but nothing capped how many REQUESTS could arrive. The
allow-list bounds what a row can say and `MAX_BATCH` bounds one request; a script could still grow
`wp_aun_app_events` without end, which on shared hosting is an availability problem as much as a
storage one. Now 60 requests per IP per 10 minutes (transient, same pattern as the OTP limiter). A
real phone flushes at most every 20 s, so it never comes close. **Over the cap it still answers
202** — the client is fire-and-forget, and announcing the limit only tells a flooder what to route
around.

### Verified clean (do not re-audit from scratch)

`targetSdk 36 / minSdk 24 / compileSdk 36` — meets Play's 2026 requirement. No cleartext HTTP
anywhere. No `print`/`debugPrint` leaking anything. Every `$wpdb` call with a variable in it goes
through `prepare()`. Every ref-based endpoint scopes to the caller's phone variants, and notice
actions to `user_id` — no IDOR found. Ticket attachments are auth-gated and ownership is re-checked
at the bridge. OTP uses `wp_rand`, stores an HMAC, compares with `hash_equals`, and rate-limits per
phone AND per IP.

### ⚠️ Recommended, NOT applied: narrow the FileProvider paths

`aun_file_paths.xml` exposes `files-path path="."` (the whole internal files dir) where only
`aun-ticket-files/` is ever used. The provider is `exported="false"` and only hands out URIs we
build, so there is no reachable vulnerability — this is defence in depth against a future bug.
**Left alone on purpose:** narrowing it can only be validated by opening a real attachment on a real
phone, and this is the wrong week to change a working path on an unverifiable hunch. Do it with a
device in hand.

## 2026-08-15 — CONNECTION AUDIT: the client was never the bottleneck (app 1.94.0+98)

Owner asked for an audit of how the app talks to the backend, and for it to be made faster.
**Measured the live server rather than reading code alone — and the answer was somewhere else.**

| Request | TTFB (measured, live) |
|---|---|
| `robots.txt` (no PHP) | **0.10 s** |
| `/wp-json/aun-app/v1/ping` (trivial handler) | **1.13 s** |
| `/wp-json/aun-app/v1/config` (real work) | **1.14 s** |

⚠️ **`/ping` returns almost nothing and costs the same as a real endpoint**, while a static file on
the same host answers in 0.10 s. So ~1.0 s of EVERY app request is WordPress bootstrap, before any
of our code runs. **Our plugin's handler time is negligible; the platform is the cost.** Any future
"make the app faster" work should start here, not in Dart.

**Verified healthy, do not "optimise" these again:** one shared `http.Client` (TLS kept alive — a
per-call `http.get()` would add a DNS+TCP+TLS handshake to every request); the token is held in
memory and read from secure storage once at boot (no keystore hit per call); auth is ONE query
against a UNIQUE `token_hash` index with `last_used` writes throttled to hourly; gzip is on
(927 → 535 bytes); no redirects.

**The one real client defect, fixed:** `init()` awaited `/config` and THEN `/me`, though they are
independent (`/me` needs only the token, already in hand) — two full round trips before the first
screen could draw, ~1.1 s wasted at the measured TTFB. Now one `Future.wait`. ⚠️ The 401 token-clear
runs AFTER the wait, never inside it, so it cannot race the other branch.

**Two findings worth the owner's attention (server side, not ours):** the `/wp-json/` route index is
**1.44 MB**, which means a lot of plugins register routes; and a **Facebook pixel cookie (`_fbp`) is
set on app API responses**, so a tracking plugin executes on calls that have nothing to do with it.

**Capacity question (Namecheap Stellar Business, shared, 2×Woo + static + ERP + app):** at ~1.1 s
per PHP request one worker serves <1 req/s, and shared hosting caps concurrent entry processes at
tens. Cached pages are nearly free, but **checkout, the ERP and the whole app API are uncacheable by
nature**. Advice given: fix the TTFB first (it multiplies capacity on the same plan), then move the
**ERP** off shared hosting — it is pure PHP and business-critical, and today a shop traffic spike
can starve it. 1,000 concurrent across all properties is not achievable on this plan.

## 2026-08-15 — DECISION (no code change): referral coupons stack on WooCommerce sale items

Owner tested the referral programme, found it working, and asked whether a friend + referrer should
both be able to discount an item that is ALREADY on a scheduled WooCommerce sale — and what other
platforms do. **Decision: leave both coupons stacking.** Margin is controlled by the sale price and
the existing minimum-spend setting, not by an exclusion.

The reasoning, because it will come up again:

- **The two coupons are different in kind, and the code already says so** — the reward coupon's own
  comment reads *"An EARNED reward is not a promotion."* The friend's WELCOME coupon is a marketing
  offer; the referrer's THANKS coupon is money they earned by bringing us a customer. Credit-based
  referral programmes (Uber, Airbnb) stack for exactly this reason; it is marketplace flash-sale
  vouchers that exclude. Telling someone their earned reward is void this week is how a referral
  programme loses trust — and we already have the "coupon nobody could spend" scar.
- **Carts here are usually ONE projector**, so "exclude sale items" does not shrink the discount,
  it kills the coupon outright during any sale.

⚠️ **VERIFIED IN THE WOOCOMMERCE SOURCE — do not "fix" this by ticking Exclude sale items:**

- **The checkbox is a NO-OP on a `fixed_cart` coupon.** `WC_Discounts::get_items_to_apply_coupon()`
  keeps an item when `is_valid_for_product() || is_valid_for_cart()`, and `fixed_cart` is a *cart*
  coupon type (`wc_get_cart_coupon_types()`), so the second test always passes and the sale-item
  check is never reached. **The referrer's reward is ALWAYS `fixed_cart`** — the setting would look
  configured in admin and do nothing.
- **On a `percent` coupon it does not reduce the discount, it REJECTS the coupon** —
  `validate_coupon_excluded_items()` throws "not applicable to selected products" (code 109) when no
  cart item qualifies. The friend's coupon is `percent` whenever the admin expressed the welcome
  discount as a percentage.

So real exclusion would need our own `woocommerce_coupon_is_valid_for_cart` / validation filter, not
a checkbox. If it is ever wanted, build it as an **admin switch defaulting to allow**.

## 2026-08-14 (4) — app 1.94.0+98 / app-api 1.87.0: the status-bar icon, and the 4–6 s bell

### Home settled in jerks — sections popping in and shoving the page down

Owner: *"the bottom content appears first, then the middle, then the top pushes everything down."*
Accurate, and it was **layout shift**, not dropped frames. Four separate sections rendered
`SizedBox.shrink()` (or were simply absent from the list) while their data loaded, then appeared at
full height in ONE frame:

| Section | Why it landed late |
|---|---|
| `HomeMaintenanceCard` | notices cache — and it is the FIRST thing on Home |
| `HomeStatusStrip` | requests cache, which reaches the **ERP** — usually last to arrive |
| announcement / banners / discount note | `/config`, which lands after the first paint |
| projector rail | skeleton and real rail are **different heights**, so even the three-state
loading jumped at the swap |

⚠️ **`AunReveal` was not enough, and this is the subtle part.** It cross-fades, but it adopts the new
child's height on the swap frame — so the *fade* was smooth while the *layout* still jumped. The
video rail already used it and still shifted. **Animating opacity does not animate size.**

New **`AunSectionReveal`** (motion.dart) = `AnimatedSize(alignment: topCenter)` wrapped around
`AunReveal`, now used by all four. Sections grow into place over 260 ms and what is below slides
instead of teleporting. It works in reverse too: completing a maintenance task shrinks the card away
instead of snapping the page up under the customer's finger.

⚠️ **Height does NOT get a spatial spring.** The M3 Expressive spatial springs are under-damped, and
an overshooting height lays a card out briefly shorter or taller than its natural size — clipped
content or a flashed overflow stripe. `Curves.easeOutCubic`. This is the one place in the app where
bounce is a bug.

**Known limit, accepted:** on the *reverse* transition the outgoing child is `Positioned.fill`ed
inside a Stack that is already collapsing, so it squashes rather than fading out cleanly. The
dominant direction (nothing → card) is the one that was hurting, and it is correct.

**Not done on purpose:** truly removing the shift means holding the whole page blank until the
slowest section (the ERP request) answers — trading a smooth 2 s wait for a jerky 0.5 s one.

### The notification centre took 4–6 seconds to open. Two causes, both ours.

Owner asked why, and whether that was normal. It is not, and neither cause was network weather.

⚠️ **1 — the server did an HTTP call to the osTicket bridge inside the request.**
`notifications_feed()` called `AUN_App_Tickets::poll_replies()` **inline** — a round-trip to the
bridge with a **20-second timeout** (the same bridge Cloudflare's geo-WAF is known to block
outright), and then one FCM post per reply it found. Throttled to once every 3 minutes, which is
exactly why it felt random: most opens were quick, then one sat there for seconds. Now it
`wp_schedule_single_event()`s a queued poll and returns immediately.

⚠️ **It needs its OWN hook (`aun_app_tickets_poll_now`), not the recurring `aun_app_tickets_poll`.**
`wp_schedule_single_event()` drops a request as a duplicate when the same hook+args is already
scheduled within 10 minutes — and the recurring poll runs every 10 minutes, so it would have been
silently dropped nearly every time.

**The general rule: a freshness optimisation must never sit on the critical path of the thing it is
keeping fresh.**

**2 — the app threw away data it already had.** `notifications_screen` called `api.notifications()`
directly and showed a spinner until it answered — while the **bell that opens it draws its counter
from `data.notices`**, so the list was already in memory. It now paints the cached feed on the first
frame and refreshes quietly behind it (stale-while-revalidate, the store.dart rule). This is what
makes other apps' notification lists feel instant: they are not fetching, they are rendering.

Details: `_freshIds` is a **union** across the refresh, so an item highlighted on open stays
highlighted after the server reports it read; an error screen appears **only** when there is no
cached feed AND the fetch failed; and `_dismiss` now removes the notice from the shared cache too —
without that, painting from the cache would resurrect a swiped-away item on the next open.

**Tests:** Flutter 227, PHP lints clean.

### The white square in the status bar

Owner: *"the logo looks fine when I pull the shade down, but the icon in the status bar looks bad."*
Exactly right, and it is not a matter of taste — it is a platform rule we were breaking.

⚠️ **Android 5+ throws away every colour in a status-bar (small) icon and keeps only the ALPHA
channel**, painting the silhouette white. Our logo is a full-bleed opaque square, so its alpha *is* a
square: the system was faithfully drawing a white rectangle. No amount of redrawing the logo fixes
this; the icon has to be a transparent-background silhouette in the first place.

**The cause was in TWO places, and the server one overrode the app one.**
`class-aun-app-push.php` explicitly sent `'icon' => 'ic_launcher'` in the FCM payload, and the
manifest declared no `default_notification_icon` at all — so even a correct drawable would have been
ignored. **Check what the sender names before assuming the app decides.**

- New `android/app/src/main/res/drawable/ic_stat_aun.xml` — a vector silhouette of the same
  front-view projector the app already draws in My Devices (`ProjectorIcon`, `widgets.dart`): body,
  vent slot, lens ring, pupil, feet. Holes are punched with `fillType="evenOdd"`, **not** painted in
  a background colour — an opaque "background" is opaque alpha and would fill itself back in.
- Manifest now declares `default_notification_icon` + `default_notification_color`
  (`@color/aun_notification` = #0188FE, the brand blue Android tints the icon and the app-name line
  with), and the push payload sends `ic_stat_aun` + `color`.

⚠️ **Safe for phones still on 1.93.0.** An icon name the installed APK does not have falls back to
the launcher icon — this is **not** the channel-id case from the brand-sound round, where an unknown
id makes the notification vanish silently. Old installs keep today's behaviour; new ones get the
projector. So the plugin can be deployed before anyone updates.

**The two icons are meant to differ, and that is what leading apps do.** Gmail, Maps and WhatsApp all
put a flat monochrome glyph in the status bar and let the shade show the full-colour app icon
beside the name. The shade icon is drawn by Android from the launcher icon — nothing to change, and
the owner already likes it.

## 2026-08-14 (3) — app 1.93.0+97 / app-api 1.86.0: a Messages page, and two tracking-form bugs

Owner: *"where are the message templates? I can't find them."* They were rendering — buried in the
middle of the **Support & contact** card, on a Settings page twelve cards long. Rendering and being
findable are not the same thing, and the sibling plugin already trained them to look for a
**Messages** page.

⚠️ **And the audit two rounds ago missed one.** I reported the OTP SMS as fine because it reads
`$cfg['sms_template']` from the options. It does — but **nothing in the admin could ever write that
option**. The most-sent message in the entire system (every single login) was, from the owner's
side, hardcoded. Reading an option is not the same as being editable; check for the EDITOR, not the
getter.

**New: AUN App → Messages**, its own submenu beside Content/Repairs/Usage, holding:
- **Login code SMS** (new — previously uneditable). ⚠️ Refuses to save without `[otp]` in it: a
  login SMS with no code in it means nobody can sign in, and that is worse than an unsaved edit.
- The four repair templates, moved out of Settings. Settings keeps a button pointing here.

### Two bugs in the tracking form, both owner-reported

**A) The keyboard hid the Courier field.** `autofocus` was on the tracking number, which is the
SECOND field — so the keyboard rose over the courier box above it and people never saw it. Moved the
autofocus to the first field. **Autofocus anything but the first field and you hide what is above
it.**

**B) The number could be sent over and over.** `trackingSent` was local screen state, so it forgot
the moment the screen closed and the button came back on every visit — each submission silently
overwriting the last. ⚠️ **The server was never asked.** `repair_entry()` now returns `tracking` and
`courier`, and the card reads `entry.trackingSent || _trackingSent` — the server's answer, OR'd with
this session's so the card still updates instantly. A successful send also re-reads the row.

**Tests:** `test-request-guards.php` now 46 — the payload carries the tracking number back so the
button can hide, and a repair without one reports an empty string so it still shows. Flutter 227.

## 2026-08-14 (2) — app-api 1.85.0: "Reject" on an APPROVED repair said the wrong thing

Owner spotted a Reject button on an already-approved repair and asked whether the logic was right.
**The permission is right; the presentation was wrong, in a way that could land badly on a
customer.**

Keeping the ability to cancel after approving is correct — an approval can be a mistake, or the
model turns out to be unserviceable. What was wrong:

⚠️ **It reused the rejection wording.** By that point we have already told the customer *"approved,
please send us your projector"*. Sending "we could not accept repair request RP-…" reads as if
**they** did something wrong, when what actually happened is that **we changed our mind after
telling them to ship**. Different event, same words.

⚠️ **It ignored the fact that the projector may already be in transit.** Since 1.81.0 the customer
can send us their courier tracking number — so we often *know* a parcel is on its way — and the
screen still offered a bare one-click "Reject".

**Fixed:**
- The button at `approved` is now **"Cancel this repair"**, in red, with a confirm that explains the
  customer will get an apology rather than a rejection.
- ⚠️ When `courier_tracking` is present the row shows an amber **"The customer has already posted
  it"** panel with the courier, the number and the date, and the confirm says so too: *"Please make
  sure someone has spoken to them first."*
- New 4th template **`repair_sms_cancelled`** (Settings → "Repair cancelled after approval"), which
  apologises and tells them what to do if the parcel is already on its way. The handler picks it
  automatically based on the status it is cancelling FROM.
- The note placeholder changed from "Optional note" to **"Why? This is shown to the customer"** — at
  this point a reason is not optional in any sense that matters.

**Tests:** `test-repair-sms.php` now 15 — refusing and cancelling produce different messages, the
cancellation apologises, and it never contains "could not accept".

## 2026-08-14 — app-api 1.84.0: SMS audit — four hardcoded messages moved to admin

Owner asked for a careful audit of hardcoded SMS. Swept every send path across **all** plugins
(`AUN_App_SMS::send`, `AUN_SP_SMS::send_tracked`, `AUN_Alpha_OTP_SMS::send`) plus the Flutter app.

**Result: four hardcoded, all in `aun-app-api`. Everything else was already admin-editable.**

| Was hardcoded | Now |
|---|---|
| Spare-parts request received (APP path) | Uses the **plugin's own** `OPT_SMS_RECEIVED` template |
| Repair request received | `repair_sms_received` option |
| Repair approved | `repair_sms_approved` option |
| Repair rejected | `repair_sms_rejected` option |

⚠️ **The spare-parts one was the worst of the four**, and not just because it was hardcoded: the
website form sends the admin's "Request received" template while the app sent its own sentence, so
**one event produced two different texts depending on which door the customer came through**, and
editing the admin template changed only half of them. Now both use the plugin's template.

⚠️ All four also said **"SmartLiving:"** while every spare-parts message says **"AUN:"** — the same
company introducing itself two different ways in the same customer's inbox. New defaults say "AUN:".

**New: AUN App → Settings → "Repair SMS to the customer"** — three boxes, placeholders `{ref}`
`{model}` `{status}` `{note}`. **An emptied box means send nothing for that event**, which is a
legitimate choice (the change still appears in the app's notification centre).

⚠️ **Found in my own change while writing the test:** `create_repair()` called
`AUN_App_SMS::send()` with whatever `repair_sms()` returned, including an empty string — a blank
body handed to the gateway is a wasted send and, on some gateways, a billed one. Guarded. The admin
approve/reject path already checked.

**Verified clean:** spare-parts has 14 SMS templates and **all 14 are both saved and rendered** in
its Messages page (counted, not assumed); the OTP plugins read their template from an option with a
fallback only when blank; and the **Flutter app has no SMS path at all** — no permission, no
`sms:` intent, nothing.

**Tests:** NEW bench `test-repair-sms.php` (12) — placeholders fill, no placeholder left showing, an
empty `{note}` leaves no double space or trailing space, **an emptied template sends nothing rather
than falling back to a default**, Bangla text and placeholders survive together, a template with no
placeholders passes through, an unknown event returns empty instead of erroring.

## 2026-08-13 (2) — app 1.92.0+96 / app-api 1.83.0: prevent, don't correct

Owner: *"I can still see the form, no ongoing request visible, and only on Submit do I get 'you
already have one in progress'. Is this the best design?"* No, and it was my mistake in the round
before: I built the guard as **validation** when it should have been **prevention**.

⚠️ **The app knew the answer before it drew the first field.** Letting someone choose parts, type an
address and attach photos, then refusing on Submit, throws away everything they just did. New
`OngoingRequestNotice` appears the MOMENT a projector is picked, above the form, with the existing
ref, its live status and a button into it. The server guard stays as the backstop — the app is a UI,
never a security boundary — but it should now be unreachable in normal use.

**The two cases differ on purpose:**
- **Repairs — a wall.** Two open repairs for one projector is one box in one van, described twice.
  The form is replaced by the card; the way forward is "see where mine is".
- **Spare parts — a warning.** ⚠️ The owner spotted the real defect here: **the website has always
  let a second request through** (warn + continue), so the app refusing outright was both a dead end
  AND an inconsistency between two doors to the same business. Now matches the website: "I need
  different parts — request anyway" sets `confirm`, which the server already honours.

**Implementation notes:** the notice reads the SHARED `data.requests` cache — no new endpoint, and
the form draws on the first frame. ⚠️ It force-refreshes that cache on open, because a request filed
on the WEBSITE ten minutes ago must appear here or the customer hits the server refusal this screen
exists to prevent. `serial` added to the spare-parts payload so a request can be matched to a
device; falls back to model for rows raised before that existed. Choosing a different projector
clears the "request anyway" flag — a different projector is a different question.

### Scroll consistency (owner observation 3)

Looked at it. Home has **no** app bar (its greeting is personalised *content*, so it scrolls away);
Devices/Support/Settings have a pinned title. That split is deliberate and standard — Gmail, Files
and Settings all do it, because a signpost that leaves when you walk past it is no use.

Added `scrolledUnderElevation` so the bar separates itself from the page only once there is content
behind it, and sits flat when there is not.

⚠️ **Not done, and worth knowing why:** the M3 way to truly unify these is a LARGE title that
collapses on scroll, which needs `SliverAppBar.large` inside a `CustomScrollView`. `devices_tab` has
three separate `ListView`s in conditional branches, so that is a real restructure of three files
rather than a tweak — a deliberate piece of work, not something to bolt on at the end of a session.
**An earlier draft of this change carried a comment claiming the bar collapsed, which it did not.**
Corrected before commit.

## 2026-08-13 — app 1.91.0+95 / app-api 1.81.0 (DB v20): duplicate guards + tracking in-system

Four owner findings, three of them real defects. **Two genuine loopholes confirmed by reading the
code, not assumed.**

### 1. The call button is gone; tracking numbers come into the system

Owner: *"why is a call button there? all customers will call for no reason."* Correct — the whole
card exists so nothing needs a phone call, and offering one invites calls with no question behind
them. Removed. The receiver's number stays in the copy block, where a courier form asks for it.

The WhatsApp "send us the tracking number" button is replaced by an **in-app form** →
`POST /repairs/tracking` → stored on the repair row (DB v20: `courier_name`, `courier_tracking`,
`courier_at`) → shown as a 📦 badge **beside the request in AUN App → Repairs**, plus an email so
somebody knows a parcel is coming. ⚠️ The old route put the number in a *different system from the
request it belongs to* — findable only by scrolling a chat, invisible to whoever receives parcels,
unsearchable when one goes missing. Courier is free text, not a dropdown: people use a dozen local
firms and a list that omits theirs is a dead end.

### 2. ⚠️ LOOPHOLE — unlimited repair requests for the same projector

`create_repair()` had **no duplicate check at all**. One customer could file the same repair ten
times; the Repairs list filled with copies of one physical projector, and each copy independently
tried to adopt the same ERP job sheet.

New `open_repair_for( user_id, serial )` → 409 `already_open` carrying the **existing ref**, so the
app can show that request's progress instead of only refusing. ⚠️ **Matched on SERIAL, not on the
customer** — someone with three projectors may legitimately have three repairs open. It is the same
*device* twice that is the mistake.

### 3. ⚠️ LOOPHOLE — the app walked past the parts guard the WEBSITE already had

The spare-parts **website** form has always guarded this (`AUN_SP_Form::open_requests()` + a "you
already have a request in progress" dialog). The **app path** (`create_part_request()`) had none —
so the protection existed and the app simply bypassed it. This is the more interesting bug: not a
missing feature, a missing *enforcement point*.

New `open_parts_request( phone, serial )` reads **`AUN_SP_Requests::TERMINAL_STATES` rather than
restating it** — the plugin has added statuses twice already (`declined`, then `expired`) and a
second copy here would have silently gone stale both times. Scoped to the device; `confirm: true`
lets a genuinely different need through.

### 4. How the ERP job sheet links (answer, no change needed)

**By serial, not by scanning.** `repair_erp_sync()` tries `repair_by_phone( phone, serial )` then
`repair_by_serial( serial )`, with two guards that matter: a job sheet created >3 days *before* the
request is an older repair of the same device, and one already completed before the request existed
is a finished past repair. Without those, every new request re-adopted the customer's last
delivered job.

### 5. Bench tests found TWO more holes in the guards I had just written

`test-request-guards.php` (43 assertions, run under `wp eval-file` against the real DB). Both holes
were in code that read correctly and had passed review:

- ⚠️ **A repair with a BLANK SERIAL was never matched — no guard at all.** `open_repair_for()`
  returned null on an empty serial, so every serial-less request was unlimited. That is exactly the
  customer whose sticker has worn off, and the one most likely to submit twice. Now falls back to
  **model** (which a repair always carries), then to the customer. Slightly over-strict for someone
  sending two identical models at once; they get the existing ref and can ask us. No protection at
  all was the alternative.
- ⚠️ **A CLOSED repair still accepted a tracking number**, overwriting the one that actually tracked
  the parcel we received — destroying the only record of it. Now refused with `already_finished`,
  and a test asserts the original value survives the attempt.

Also fixed in the harness itself: ⚠️ **`wp eval-file` runs the file inside a FUNCTION scope**, so
top-level `$pass`/`$fail` are not globals and `global $pass` in a helper binds to an empty variable.
The first run printed a screen of PASS lines and then "0 passed, 0 failed". **Use `$GLOBALS`
explicitly in any bench file run this way** — a counter that silently reads zero makes a green run
meaningless.

Confirmed sound by the same run: terminal statuses release the lock and in-flight ones hold it (all
of them, enumerated); another customer with the same serial is unaffected; case and whitespace
differences in a serial do not defeat it; **a request filed on the WEBSITE (01…) is seen by the app
guard (8801…)**, so the duplicate cannot be created across channels; and another customer cannot
attach a tracking number to someone else's repair.

**Tests:** bench 43, Flutter 227, PHP lints clean.

⚠️ **Known and accepted:** the guards are SELECT-then-INSERT, so two genuinely simultaneous
submissions could both pass. The app disables its own button while submitting, which covers the
double-tap that actually happens; a database-level constraint is not possible while "open" depends
on a status list.

## 2026-08-12 — app 1.90.0+94: admin HTML could paint invisible text in dark mode

Owner-reported: the first line of a firmware note — `<span style="color: #333333">Please follow the
steps below carefully:</span>` — was **completely invisible in dark mode**. Near-black text on a
near-black card. They asked whether they were doing something wrong. They were not.

⚠️ **The WordPress editor adds `color:#333333` to almost anything pasted into it** (from Word, from
a browser, from another page). Those colours were chosen against a white page, and the app was
letting them decide what to paint on a dark one. This will keep happening with every note anyone
pastes, so telling the admin to strip the span would have fixed one note and left the trap.

New `lib/src/ui/html_colors.dart` — `stripUnreadableColors( html, background: … )`, applied in
`RichNote` to every admin note. **The rule: keep colour that carries meaning, drop colour that
carries none**, decided by **WCAG contrast ratio against the real surface** (≥ 4.5:1, the AA
threshold for body text — a published number, not one invented here).

Why measured rather than "strip all colours in dark mode": stripping everything flattens a
deliberate red warning, stripping nothing leaves invisible text. **Contrast is the only thing that
tells those two apart.** A red warning passes on both themes and survives untouched; a decorative
grey fails and is dropped so the text inherits the theme colour, which is readable because we chose
it.

⚠️ The background comes from `context.findAncestorWidgetOfExactType<Material>()`, not
`colorScheme.surface` — these notes sit inside Cards and tinted containers, and the contrast that
matters is against what is actually behind the text.

Handles the formats the editor really emits (`#333`, `#333333`, `rgb()`, named colours, single or
double quotes), leaves `background-color` alone (removing it changes layout, not legibility), and
**keeps an unparseable colour** (`var(--brand)`) rather than guessing — at worst that looks the same
as before this existed.

**Tests:** NEW `html_colors_test.dart` (14) built around the owner's exact note — invisible grey
dropped in dark, **the same grey KEPT in light**, font-size and letter-spacing survive, a red
warning survives, white-on-light dropped (the mirror bug), every colour format, background-color
untouched, list/link/text intact, no dangling `style=""`. Flutter suite **227**.

⚠️ **Caught before release, and it would have been bad.** The live privacy policy at
`/app-privacy-policy/` promises customers: *"no third-party analytics or tracking SDKs"*. Shipping
Firebase Analytics (the previous round) would have made that a public lie. **Always read what the
policy already promises before adding an SDK.**

Owner chose to keep the promise. **Usage counting is now first-party** — `POST /events` → new
`wp_aun_app_events` (DB v19), purged after 180 days by cron, erased with the account via
`aun_app_account_deleted`. `firebase_analytics` is removed from the app. **Crashlytics stays**: a
stack trace is diagnostics not tracking, there is no realistic first-party equivalent, and it is now
disclosed on exactly those terms.

**Two locks on personal data.** The app never sends phone/name/address/serial/ref; the server's
`clean_params()` drops anything phone- or email-shaped and any string over 40 chars (prose is not a
category). Event names are an **allow-list** — a future app version cannot invent new ones, and that
list doubles as the readable answer to "what does the app collect?".

**The client is a batching queue**, not a per-tap request: flush at 12 events or 20s after a burst,
persisted to SharedPreferences so events survive a close, capped at 60 so an unreachable server
cannot grow it without bound. ⚠️ **The batch is cleared BEFORE the request and restored only on
failure** — the reverse order double-counts every event whenever a response is slow enough for a
second flush to start.

⚠️ `Analytics.init()` takes **callbacks** for version and token, not values: it runs before AppState
has read the package info or the stored token, so capturing values records an empty version for the
whole session and loses the account id on every event.

**Admin: AUN App → Usage.** Opens with the standing questions answered *in words* ("nobody is
finding it", "38% submitted") rather than a wall of charts — a dashboard nobody opens twice answers
nothing. Same on/off switch as crash reports.

**Tests:** NEW bench `test-events.php` (13) — the allow-list rejects unknown names, phone numbers in
both formats and emails are dropped, long strings dropped, params capped, malformed input degrades,
retention bounded. One test documents a real limitation rather than hiding it: **a short ref like
`SP-2026-0042` would pass the scrubber**, which is why the app is the first lock. Flutter 213.

**Also this round:** keystore backed up to `Downloads\AUN-KEYSTORE-BACKUP\` (outside any git repo,
with a plain-language README); `privacy-policy-update.html` written with the exact edits to paste.

⚠️ **Correction to `PLAY-STORE-READINESS.md`:** an earlier draft called account deletion the biggest
blocker. **It was already fully built** — backend, `POST /me/delete`, the Settings flow, and a live
public page at `/delete-account/`. Verified before writing anything new. The remaining pre-flight
work is now about 1–2 days, not a week.

## 2026-08-11 — app 1.88.0+92 / app-api 1.79.0: analytics + crash reporting

For apps, "Google Analytics" **is** Firebase Analytics (GA4) — Universal Analytics for mobile is
gone. `firebase_core` + `firebase_messaging` + `google-services.json` were already here for push, so
this was mostly wiring.

**Crashlytics matters more than the analytics.** Until now an app that crashed on a customer's phone
taught us nothing at all — they just stopped opening it. `FlutterError.onError` and
`PlatformDispatcher.instance.onError` are both wired, collection is off in debug builds, and the
**Crashlytics Gradle plugin** is added in `settings.gradle.kts` + `app/build.gradle.kts` — without
it release stack traces are unreadable obfuscated line numbers.

**`lib/src/services/analytics.dart` is the ONE place** the app touches Firebase; nothing else
imports it. Same rule as `Haptics`: one file to tune, one file to switch off, one fixed vocabulary.

⚠️ **Never pass personal data as an event parameter** — no phone, name, address, serial or `ref`.
Parameters are categories and counts. `_clean()` truncates to Firebase's 100 chars and **drops
anything phone-shaped** as a last line of defence, but the rule is not to send it at all.

**~16 events, each attached to a decision** (full table in the readiness doc §11). The one that
matters most: `quote_answered` finally says whether customers answer quotes **in the app** or still
through the SMS link — which is the justification for the entire reminder/countdown feature.

**Server kill switch:** `analytics_enabled` in `/config`, admin checkbox under Support settings.
Applied on every config load and it calls `setAnalyticsCollectionEnabled` — so it stops collection
inside the SDK, not merely our calls, with no APK rebuild. Defaults ON so an older server still
reports crashes.

**Verified facts for the readiness doc** (checked, not assumed): package is
`bd.com.aunprojector.aun_app`; the keystore exists and `key.properties`/`*.jks` are gitignored; the
manifest declares exactly **three** permissions — INTERNET, CAMERA, POST_NOTIFICATIONS — with no
storage, media, location or `QUERY_ALL_PACKAGES`. That last one is worth protecting: photo picking
uses Android's system picker and downloads are app-scoped, which is why it is clean.

⚠️ **Biggest non-D-U-N-S blocker: account deletion** (in-app + a public web URL) is mandatory for
any app with accounts and is **not built**. Deletion must anonymise rather than destroy — warranty
and paid-order records are business records.

## 2026-08-10 (7) — app 1.87.0+91 / app-api 1.78.1: an inline link stays inline

Second correction, from a screenshot: the same two videos appeared as big cards under **both**
"What's new" and "Installation steps", and the admin's inline link — *"formatted as FAT32
(Video Tutorial)"* inside step 3 — had been ripped out of the sentence.

**The rule now, and it is the point of `rich_note.dart`:**

- **An `<a>` the admin wrote into a sentence STAYS THERE.** It keeps their wording, keeps the video
  next to the step it explains, and `onTapUrl` opens the app's video window instead of a browser.
  Lifting it out deletes text they wrote and moves the video away from what it is about.
- **Only embeds with nothing to tap become cards** — `<iframe>`, an oEmbed `<figure>`, or a bare URL
  on its own line. `standaloneYoutubeIds()` = ids remaining after every `<a>…</a>` is removed.
  `htmlWithoutStandaloneYouTube()` strips only those; ⚠️ its bare-URL sweep carries a
  **negative lookbehind for `="`** or it reaches inside `href` and shreds the very link being
  protected.

**The duplication** was `note_videos` covering the whole content item (steps AND changelog) being
rendered wholesale in each block. `videos` is now a **lookup by id**, never a list to render — each
block renders only the ids present in its own html.

⚠️ **Regex bug found by the tests, in BOTH copies:** `watch\?[^"\s<]*[?&]v=` cannot match
`watch?v=ID` — the `?` is already consumed by `watch\?`, so the separator must be optional
(`[?&]?v=`). The Dart and PHP patterns are near-identical and must be changed together.

**Tests:** `rich_note_test.dart` 25, built around the owner's REAL note — the anchor is never turned
into a card, `href` and link text and "FAT32" all survive, the id is still recovered so the tap can
open the player, an iframe and a bare URL still do become cards, and a note mixing both yields one
card and one live link. Flutter suite **213**.

## 2026-08-10 (6) — app 1.86.0+90 / app-api 1.78.0: a note's tutorial opens the REAL video window

Correction to the previous round. The owner's ask was not "make the embed render" — it was **"give
it the same window Video content gets"**: tap the tutorial in an installation note, get the full
video screen with the player on top and the title and date beneath. I built an inline facade player
instead, which fixed the blank box and missed the point.

**Now:** `RichNote` renders each embedded tutorial as a **card** (thumbnail, title, date), and
tapping it pushes `VideoPlayerScreen` — literally the screen a published video guide opens. One
video window in the whole app, so there is no second player to keep in step with it, and the
full-screen playback, back handling, embed workaround and "Watch on YouTube" fallback all come
along. A tutorial should not look like a lesser thing because of where it happens to be filed.

**The title and date come from the SERVER.** New `note_videos` on every non-video content item:
the ids are extracted from `description` + `changelog` and each is run through the same
`youtube_meta()` the video library uses. ⚠️ **The YouTube Data API key lives on the server** — the
app cannot resolve a title, so without this it would have an id and nothing to put around the
player. Falls back to the parent entry's title when no key is configured, never to an empty heading.

The app still parses the ids locally as a fallback, so an older server means a working video with a
generic heading rather than a blank rectangle.

**Tests:** `rich_note_test.dart` 19 — titles and dates arrive, thumbnails derive from the id, and an
older server with no `note_videos` still yields a playable id. Flutter suite **207**.

## 2026-08-10 (5) — app 1.85.0+89 / app-api 1.77.0 (DB v18): firmware "What's new"

Owner asked where to put a changelog. There was nowhere — so it would have gone at the top of the
installation note, which is the wrong shape: a firmware entry answers two questions asked in ORDER,
**"should I install this?"** then **"how?"**, and burying the reason under the instructions hides it
from everyone who has not already decided. It matters most for OTA, where there is no download
button — the customer must get up and open the projector's menu, so they need a reason worth it.

**DB v18** adds `aun_app_content.changelog`. New "What's new" rich editor on the firmware form,
above the (now renamed) **Installation steps**. Rendered in the app above the steps in its own
tinted block, through `RichNote` — so a demo video pasted into the changelog plays too.

**The notification now carries the reason, not just the name.** `content_published()` takes the
changelog and uses `first_line()` for the body: previously it was the title, so a push said "New
firmware update / A45 Pro firmware 2.1.0" — two ways of saying a number changed. Now it says "Fixes
no sound over HDMI on some TVs". First line only; the full list is one tap away.

⚠️ **Found by the bench, not by review:** `first_line()` guarded truncation with
`function_exists( 'mb_strlen' )` and returned the line UNTOUCHED when mbstring was missing — so a
server without the extension would store a 500-character notification body, the exact case the limit
exists for. The fallback now cuts on a byte boundary at a space. The scratchpad PHP has no mbstring,
which is the only reason this surfaced. **A capability guard must still do the job, not skip it.**

**Tests:** bench `test-changelog-line.php` (11) — first bullet not the whole list, bullets never run
together, `<br>` and `<p>` shapes, a typed bullet character stripped, entities decoded, empty falls
back to the title, an empty first bullet skipped, long lines cut **without mbstring**, no markup ever
reaching a lock screen. `rich_note_test.dart` 17 (changelog and steps stay distinct). Flutter **205**.

## 2026-08-10 (4) — app 1.84.0+88 / app-api 1.76.0: video in a note, and OTA firmware

### 1. The blank player in an installation note

⚠️ **Two independent causes, and fixing either alone would still have left a blank rectangle:**

1. **`HtmlWidget` does not render `<iframe>`.** It drops it silently, so the admin's embedded
   tutorial became a gap.
2. **Even rendered, a direct youtube.com iframe fails** — "Video unavailable, error 152-4" from an
   anonymous WebView context. `VideoPlayerScreen` already knew this and loads
   `${Env.baseUrl}/embed?v=ID` instead. **Anything showing YouTube in this app must go through that
   page.**

New `lib/src/screens/rich_note.dart`: `RichNote` lifts the videos OUT of the HTML, renders them with
our own player, and gives `HtmlWidget` the remainder — otherwise the note keeps the dead hole where
the iframe was. Used by the firmware installation note AND the generic content description.

`youtubeIdsInHtml()` is deliberately greedy about WHERE it looks — iframe `src`, anchor `href`, a
bare URL in a `wp-block-embed` figure — because which one you get depends on how the link was pasted
into the editor, and **the commonest case is a bare link on its own line**. `htmlWithoutYouTube()`
removes the WP wrappers too, or an empty bordered box replaces the old hole.

`NoteVideo` is a **facade**: poster + play button, WebView built only on tap. A note can carry
several videos, and nothing should autoplay at someone who opened a page to read steps.

### 2. OTA firmware — publish it with no file

**"No file" IS the definition.** An admin leaves the URL blank on a firmware entry and the server
derives `ota => firmware && url === ''`. Derived rather than a separate checkbox on purpose: a flag
that can disagree with whether a file exists eventually does, and then the app offers a download
that 404s. The URL is also emptied rather than pointing at the redirect endpoint.

The app replaces the download area with an **"installs over Wi-Fi"** card — no button, the admin's
steps and video above it (via `RichNote`), and the warning every OTA needs: stay on Wi-Fi, do not
switch off part-way. ⚠️ The `ota` branch must come **before** the `appDownloadable` check, or an OTA
entry falls into "get it from the website" and sends people hunting for a zip that was never
published. Everything else is unchanged: it still appears under the model, keeps its version number,
and still fires the "new firmware" notification. Admin form explains it inline.

**Tests:** NEW `rich_note_test.dart` (15) — all four editor shapes, order and de-duplication, a
non-YouTube link ignored, a video-free note left byte-identical, wrappers removed, **the surrounding
instructions always survive**, and OTA parsing including an older server that sends no flag (must
never guess OTA and hide a real download). Suite **203**.

## 2026-08-10 (3) — app 1.83.0+87 / app-api 1.75.0: the receiver block matches Pathao's form

The owner pointed out the website already shows the return address laid out as **Pathao's own
booking form** — Receiver Name, Receiver Phone, Delivery Address, then City / Zone / Area marked as
DROPDOWNs. That is a better idea than the prose address block I shipped, for a reason worth writing
down: **the customer is not reading an address, they are filling in a form.** Naming each field the
way their screen names it turns transcription into matching, and picking the wrong Zone or Area is
the commonest way a parcel reaches the wrong hub — a street address does not tell you which Zone
Pathao files it under.

New options `repair_ship_city` / `_zone` / `_area` (admin: "Pathao City / Zone / Area", typed
exactly as they appear in Pathao's list), carried in `/config`, rendered as labelled rows with a
DROPDOWN tag. Optional throughout — blank means a counter courier and the address alone.

**Two things the app does that the website page does not, both because it is a phone:**
- **Every row is individually tap-to-copy.** A single "copy everything" button is useless against a
  dropdown — you cannot paste six lines into a `<select>` — and the dropdowns are exactly where
  people go wrong. Tap "Mirpur Buddhijibi Koborsthan", paste it into Pathao's search box, done.
  Copy-all is kept for sharing the block.
- **`copyText` LABELS city/zone/area** rather than running them onto the street line. A dropdown
  answer glued to an address reads as one long address and is then wrong in both fields.

**Tests:** `repair_ship_test.dart` now 9 — carried through when set, optional when not, any one of
the three shows the block, and the copy block labels them.

## 2026-08-10 (2) — app-api 1.74.1: the local-picks form showed one character per field

Owner screenshot: Title, Platform, URL and Poster had collapsed to about one visible character while
the date picker sat there at full width.

⚠️ **A `<table>` cannot lay out a form containing `<input type="date">` or a button.** Both have hard
intrinsic minimum widths that table auto-layout will not shrink, so they take what they need and
every free-text column absorbs the loss. The `width:22%` hints on `<th>` are only hints and lose to
unshrinkable content every time. Adding the 7th column (TMDB) is what pushed it over.

Replaced with **a card per pick** and a wrapping CSS grid
(`repeat(auto-fit, minmax(240px, 1fr))`): every field declares a floor it will never go below, and
the grid moves it to the next line instead of squeezing it. Fields are labelled above rather than by
a distant column header, and the link — the longest value — spans the full width. The JS was written
against `.aun-watch-row` / `.aun-watch-del` / `.aun-watch-find` class hooks, so it needed no changes.

**Verified, not assumed:** rendered the real markup in the Browser pane and measured. At 1280px
Title is **293px** (previously ~40) and the link field 910px; at a narrow width the grid drops to
fewer columns with fields still ≥290px and `body.scrollWidth == innerWidth` — no horizontal
overflow. **Measure a layout fix; a screenshot of the fixed version proves nothing about the widths
that broke.**

## 2026-08-10 — app 1.82.0+86 / app-api 1.74.0: sending a projector, and Bangla films with real detail

### 1. "Send for repair" no longer ends at approval

Approval used to be the end of the road: the admin pressed a button, the app said nothing further,
and the customer sat holding a projector with nowhere to post it.

New `_ShipItCard` on the repair detail, shown **only when `status == 'approved'` and no ERP job
sheet exists yet** — before approval there is nothing to send (a request may still be refused, and a
customer who posts a projector we then decline has paid courier fees for nothing); after it arrives,
telling them how to post it is nonsense. Address in one copyable + selectable block, tap-to-call,
four steps (write the ref on the box → what to pack → any courier counter → **keep the receipt**),
and a WhatsApp button pre-filled to send us the consignment number.

⚠️ **No address configured → no address shown.** `RepairShipTo.configured` is the single gate, and a
name + phone with no street is NOT an address — a half-blank line on a courier form loses somebody's
projector. The card degrades to "we will message you the address; do not send it yet", which is
true. New admin fields under Support settings; new `repair_ship` block in `/config`.

**Why not Pathao** (owner asked): their merchant API is pickup-from-us → deliver-to-customer, i.e.
the RETURN leg only, and `courier.pathao.com/order` is a consumer form behind a session and a CSRF
token with no contract — automating it breaks silently and pushes customer addresses through an
unofficial channel. Worth asking Pathao whether reverse pickup is enabled on the merchant account;
if it is, both legs come from the API we already have.

### 2. Admin-curated Bangla films get the full TMDB screen

**Bangladeshi cinema IS on TMDB** (Hawa, Poran, Surongo, Priyotoma…) — it simply never reaches the
global trending feed, which is why those picks are curated by hand. They were opening a browser only
because the app decided by ORIGIN, not by data: `hasDetail => !local && …`.

- Local pick lines gained a 6th field: `Title|Platform|URL|Poster|End|movie:1044789`.
- `AUN_App_Watch::hydrate_local()` fills overview, backdrop, year, rating, kind, genres, runtime,
  tagline, cast and trailer from TMDB. ⚠️ **The admin's title, platform and URL always win** — TMDB
  does not know we are pointing people at Chorki, and an English TMDB title must not replace a Bangla
  one. Their poster wins too when set.
- ⚠️ `local_picks()` is deliberately never cached (end dates must bite immediately), so the cache
  lives **per title** (7-day transient) — otherwise every launch spends a TMDB round trip per pick on
  the customer's own request. Flushed on save so a corrected id shows immediately. A dead id caches a
  1-hour failure and the pick still works as the poster-and-link it was.
- Admin: **Search TMDB** button per row → modal with poster/year/original title → click writes
  `movie:1044789`. Only fills title/poster when they are still blank. Ajax is
  `manage_options` + nonce (it spends the site's TMDB quota).
- App: `hasDetail => overview.isNotEmpty || trailer.isNotEmpty` — **judge what arrived, never where
  it came from.** The detail screen's pinned "Watch on {platform}" button already used `pick.url`,
  so a linked Chorki title needed no other change.

**Tests:** NEW `repair_ship_test.dart` (5) — a name+phone with no street is not an address, no stray
blank lines in the copy block; NEW `watch_local_detail_test.dart` (6) — a linked local pick gets the
screen, an unlinked one does not, a trailer alone is enough, identical metadata gets identical
treatment regardless of origin. Suite **184**.

## 2026-08-09 (4) — app 1.81.0+85: the image zoom-OUT that shrank from the corner

Owner-reported: tapping a spare-part reference photo zoomed in beautifully, closing it glitched and
collapsed toward the left.

⚠️ **A Hero child must never carry its own fixed size.** The thumbnail was
`Hero(child: ClipRRect(Stack(Image(width: 48, height: 48))))`. On the return flight Flutter uses the
DESTINATION hero's child — that Stack — and lays it out inside a full-screen rect. A `Stack` sizes
to its largest child and aligns top-left, so a 48 px image rendered in the corner of the screen and
shrank from there. Push looked fine only because the destination was then the viewer's plain
full-bleed image.

**Three fixes, all needed:**
- **Size moved OUTSIDE the Hero** (`SizedBox` wrapping it) plus `StackFit.expand` inside, so the
  child adopts whatever rect the flight hands it.
- **`flightShuttleBuilder` on both ends** — new shared `heroImageShuttle(url)` in `widgets.dart`,
  one plain `BoxFit.contain` image for the whole flight so nothing re-lays-out mid-air. The residual
  cover/contain mismatch is left at the 48 px end where it is invisible. **Any hero pair whose two
  ends are not literally the same widget needs one.**
- **No hero while pinched in.** The Hero sits inside the `InteractiveViewer`, so a zoomed transform
  makes its rect several times the screen and often mostly off it; flying that to a thumbnail is a
  smear. Zoomed → plain fade. Tapping a zoomed photo now zooms out instead of closing, which is what
  every photo viewer does and guarantees the close starts untransformed.

**The same bug was latent in the repair-photo strip** (fixed 128×96 inside its Hero) and is fixed
too — it just had a less extreme size ratio, so it read as a small jump rather than a glitch.

## 2026-08-09 (3) — app 1.80.0+84 / app-api 1.73.0: the ৳ that printed as `&#2547;`

### 1. ⚠️ Stripping tags is not decoding entities

Owner screenshot: the finder card read **"&#2547;&nbsp;14,500 0% EMIs from ৳2,417/month"**. Two
separate mistakes in one line, `wp_strip_all_tags( $p->get_price_html() )`:

- **Entities are not tags.** WooCommerce writes the taka sign as `&#2547;` and its spacing as
  `&nbsp;`. Stripping tags leaves both, and the app renders **plain text** — a browser would have
  hidden this bug, a `Text` widget cannot. **Anything crossing from WordPress into the app must be
  entity-decoded server-side, not just tag-stripped.**
- **`get_price_html()` is a filtered free-for-all.** The EMI plugin appends its own sentence to it,
  so a field the app treats as one price arrived as a paragraph of a third party's marketing.

Fixed with `price_text()` / `sale_before_text()` built from WooCommerce primitives (`wc_price()`,
explicit variable-product range) plus one `plain()` helper — tags out, entities decoded, `&nbsp;`'s
U+00A0 turned into a real space, whitespace collapsed. `plain()` also now covers the product NAME,
the summary and every reason, which had the same latent bug. New `price_before` renders struck
through when a product is genuinely on sale.

**Tests:** `finder_test.dart` 10 — asserts no `&#`, no `&nbsp`, and no "EMI" survives in a price.

### 2. Home strip: how a dead row goes away

Answering the owner's question, and it needed a second answer. Automatic: the server drops expired
and declined rows after **10 days** (`PARTS_RECOVERY_DAYS`). Manual: those rows are now
**swipe-to-dismiss**, remembered in SharedPreferences (`aun_strip_dismissed`, capped at 40).

⚠️ **Only ENDED rows are swipeable.** Hiding a repair still on our bench does not make it stop
happening, and a quote still awaiting an answer is the customer's own deadline — letting them swipe
that away is helping them miss it. The dismissal is **local to the phone**: "I have read this" is a
fact about their screen, never about the request, so the admin's list never loses anything.

### 3. Support → Services regrouped, and the finder renamed

One card of five rows mixed "help me decide what to buy" with "my projector is broken" — different
people, different moods, and a shopper had to read past three after-sales rows to reach the two
meant for them. Now two cards: **"Thinking about buying one?"** (finder, planner) and **"Already
have a projector?"** (parts, repair, my requests). Buying leads because someone who owns nothing has
no other route in, while an owner is already pointed at their request by Home, Devices and push.
Five near-identical hand-built ListTiles collapsed into one `_ServiceTile`.

**"Find my projector" → "Help me choose"** (bn: "কোনটি নেব, সাহায্য করুন"). The old name reads like
Find My iPhone — locate a projector you already own — which is the opposite of what it does.

## 2026-08-09 (2) — app 1.79.0+83 / app-api 1.72.0 / wizard 3.5.0: the finder in the app, and Home's clock

### 1. Spare parts 0.32.0 — a DECLINED quote can now be revived too

`revive_quote()` accepts `expired` **and** `declined`; new `cancelled` line status; new declined SMS.
The reasoning is right: Decline is one tap on a phone, and a mis-tap was previously silent and
unrecoverable. So the app now offers a re-quote on both, with **different words** — telling someone
who pressed Decline that "we did not hear back" calls them unresponsive when they answered, and
telling someone who never replied that "you cancelled this" accuses them of a decision they never
made. One card (`_ExpiredCard`), two faces, one button. `can_revive` covers both server-side.

`declined` also gained notice + push copy, for the same reason the plugin texts it.

### 2. ⚠️ Two Home-strip bugs, both live since the strip shipped

`HomeStatusStrip._isDone()` decided whether something was finished by **searching the human status
label** for "cancel", "reject", "complet"… Neither `declined`/"Quote declined" nor
`expired`/"Quote expired — no reply" contains any of those words, so **both sat on the customer's
Home for ever**. It would also have broken the moment a label was reworded — which just happened.

Replaced by a server flag, `is_active` (`AUN_App_Services::parts_is_active()`), with three answers
rather than two: live work → always; a real ending (completed/rejected) → gone immediately;
**expired or declined → kept for `PARTS_RECOVERY_DAYS` (10)**, because both are undoable in one tap
and the days right after are exactly when someone realises they still want the part. **Never decide
lifecycle by string-matching a display label.**

### 3. The countdown on Home (owner's request)

A waiting quote is the only row in that strip with a clock on it, and the only one that ENDS if the
customer does nothing — so it is the only one that gets urgency, or urgency stops meaning anything.
Trailing pill (`3d left` → `Today`), the row and its icon turn amber inside 24 h, a 3 px hairline
under the row shows the window closing, and the subtitle says **"Needs your answer · ৳3,400"**
instead of "Quote sent — awaiting approval" (our filing vs their task). Expired rows show
`Ended` + "Tap to ask for a new quote".

### 4. Projector finder, native (`lib/src/screens/finder_screen.dart`)

⚠️ **The scoring was NOT reimplemented in Dart, and must not be.** Wizard 3.5.0 splits
`recommend( $answers )` (data) out of `process_recommendation()` (HTML); the ajax handler now renders
from it, and new **`POST /finder`** returns the same result as JSON. Both front ends therefore read
the same thresholds an admin set in wp-admin — a Dart copy would drift the first time one moved, and
the app and the website would recommend different projectors to the same customer with nobody
watching. `score_to_pct` / `generate_reasons` / `generate_ai_summary` became public for this.

**The endpoint is unauthenticated on purpose**: this is the only feature in the app for someone who
owns nothing yet, and asking for a phone number before answering "which one should I buy?" demands
trust before giving any reason for it.

⚠️ **Brightness travels as a chip ("High brightness"), never as a number** — the store's public copy
is deliberately qualitative (wizard 3.4.0) and shipping the raw figure would republish exactly the
claim the site stopped making. See the ANSI-sync decision. Failing reasons are shown beside passing
ones: a finder that only ever agrees with you is a sales page. `physics_warn` renders **above** the
cards.

Entry: top of the Services list in the Support tab, above the planner — "which one should I buy?"
comes before "how big will it be on my wall?".

⚠️ **`FinderResult` collides with `flutter_test`'s own class** — the model is `FinderOutcome`.

**Tests:** NEW `finder_test.dart` (7) — reasoning survives parsing, a compromise is never filtered
out, the price is never re-derived, no numeric brightness reaches the app, the stretch warning
survives, empty and malformed payloads degrade. `quote_expiry_test.dart` now 13 (both endings
re-quotable, a real ending is not, Home visibility is the server's call). Suite **172**.

**To deploy:** `aun-app-api.zip` (1.72.0) + `aun-projector-wizard.php` (3.5.0) + APK 1.79.0+83.
Clear WP Rocket cache.

## 2026-08-09 — app 1.78.0+82 / app-api 1.71.0: the app half of the quote chase (spare parts 0.31.0)

Spare parts 0.31.0 gave an unanswered quote a life: **quote → reminder (day ~3) → final reminder
(day 6) → expired (day 7)**, `quote_valid_days` configurable, reminder days derived from it so the
ladder always fits inside the window. `expired` is a NEW terminal status and is emphatically **not
`declined`** — nobody said no, nothing was ordered, and the customer can revive it in one tap. All
of that talked only to SMS and the website tracker. This is the app's half.

**Push / notification centre**
- `aun_sp_quote_reminder` → `AUN_App_Services::on_parts_quote_reminder()` →
  new `AUN_App_Notices::parts_quote_reminder()`. Reminder 1 leads with *"We have NOT ordered your
  part yet"*; reminder 2 leads with the deadline, because on the last day the date IS the message.
- `expired` added to `parts_status_changed()`'s copy — worded as "we did not hear back", never as a
  refusal.
- ⚠️ **The app mirrors the SMS ladder exactly and never adds a day of its own.** A push on a day the
  SMS does not go out is a fourth chase wearing a different hat, and three touches is where chasing
  stops working.

⚠️ **Dedup bug found and fixed while adding this.** `dedup_key` was `parts_status:REF:quote_sent`,
one per request for ever. 0.31.0 makes a request go round the quote loop more than once (expired →
revive → fresh quote), so the SECOND quote would have collided with the first notice and been
dropped in silence — the one status the customer must answer, arriving as nothing. The key now
carries the quote round (`quoted_at`). **Any per-status dedup key needs the round when the status
can recur.**

**Payload** (`my_requests`): `expires` (ISO), `days_left`, `expired`, `can_revive`. `days_left` is
computed **server-side** — the server owns the deadline and a phone with a wrong clock must not be
able to show a customer a day they do not have. **null ≠ 0**: null is "never expires" (a supported
setting), 0 is "expires today". `expired` excluded from `can_pay`, and `POST /parts/pay` now refuses
it with code `expired` — paying would commit us to a withdrawn price AND implicitly approve it at
that figure. `quote_reminder` excluded from the app timeline, as the website tracker does: it
records that we chased them, which reads as nagging on their own progress list.

**New `POST /parts/revive`** — mirrors the website's `aun_sp_revive`, plus the authorisation the
website does not need (the ref must belong to the caller's phone, or a guessed ref puts our staff to
work re-pricing a stranger's request). The state claim, price reset and admin email stay the
plugin's, so app and website can never disagree about what reviving means.

**App:** deadline line on the live quote card (`_DeadlineLine`) — the last day is a different
*sentence*, not a smaller number ("Last day to reply", amber, timer icon), plus "Nothing has been
ordered yet" above it. New `_ExpiredCard` with "I still want this part" and **no confirm dialog**
(the tap commits them to nothing) — and the old amount appears nowhere on it, because re-quoting is
the point. `not_expired` from the server is shown as the success it is. `statusChip` keeps `expired`
**amber, not red**: red would tell them they were rejected. List rows get the expired line and a
days-left line.

**Tests:** NEW `test/quote_expiry_test.dart` (8) — deadline round-trip, **null vs 0**, expired is not
awaiting a decision, expired is never payable, expiry inferred from the status on an older app-api,
no revive button without the endpoint, expired ≠ declined. Flutter suite **161**. analyze clean.

**To deploy:** `aun-app-api.zip` (1.71.0) + APK (1.78.0+82). Clear WP Rocket cache. Then check
**Spare Parts → Settings → "Quote valid for (days)"** (7) and the three new templates under
**Messages** (Quote reminder / Final quote reminder / Quote expired).

## 2026-08-08 — app 1.77.0+81 / app-api 1.70.0: update prompt rebuilt the way real apps do it

Fixing the broken layout left a worse problem in place: a **permanent, undismissable card in the
middle of Home**, occupying the space belonging to the customer's projectors and requests, on every
launch until they gave in.

**Now:** `lib/src/screens/update_sheet.dart` — a dismissible bottom sheet shown **once per version
code** (`aun_update_seen_<code>` in SharedPreferences), on the first Home load after a new version
appears. The Home card is gone; Settings keeps the on-demand check.

The four rules it follows, which is what established apps do:
- **Ask once.** Nagging teaches people to dismiss without reading — the exact habit you do not want
  when a genuinely important release lands.
- **Always dismissible.** Blocking is reserved for `min_version_code` + the force-update screen: a
  separate, deliberate admin decision, never the default for every release.
- **Leave a way back.** "Later" says where it went; Settings still has the check.
- **Say what changed.** New admin field **What's new** (`release_notes` → `/config`), shown in the
  sheet. A version number is a fact about us; one line about what improved is a reason for them.

⚠️ The dismissal is remembered **before** the sheet is shown — force-closing the app mid-sheet
still counts as asked, or the next launch re-asks and we are back to nagging.

**Tests:** NEW `update_sheet_test.dart` (3) — asked once then remembered, a NEW version asks again,
an older version is not resurrected by a newer dismissal. `update_banner_test.dart` retained for
the layout rule. Flutter suite **153**.

**When the Play listing goes live, delete this file.** Play's in-app update API does the same job
natively with a background download, and is the right answer for a Play-distributed app. This
exists because the APK is currently side-loaded.

## 2026-08-08 — app 1.76.0+80: the update banner rendered one character per line

The banner APPEARING was correct: `/config` carries `latest_version_code`, the app compares it to
its own build on every launch, and shows the card automatically. No "check for update" tap needed —
that button is only for checking on demand. Raising the version code in admin is what triggered it.

**The layout was the bug.** The banner was a `ListTile` with `trailing: FilledButton`. A ListTile
gives its trailing widget that widget's own intrinsic width and the title/subtitle only what is
left; after the leading icon and the tile's padding there were a few pixels of text column on a
narrow phone, so the text wrapped to **one character per line** and the card grew past the screen.

Rebuilt as title row → body → full-width button. Nothing can squeeze a `Column`, and the button is
easier to hit. The same pattern in `widgets.dart`'s content tile (short labels, so lower risk) was
hardened with `maxLines: 2` + ellipsis.

⚠️ **Never put a wide button in a ListTile's `trailing` next to text that must wrap.**

**Tests:** NEW `test/update_banner_test.dart` (2) at **320dp**, asserting the card stays under 240px
and the title keeps real width. **The test was verified against the OLD layout first** — it produced
a **1444px** card on a 640px screen and both assertions failed, so the test genuinely catches this
rather than merely passing. Flutter suite now **150**.

`flutter analyze` cannot see a layout bug like this; only a widget test at a real width can.

## 2026-08-07 — app-api 1.69.0: a reward worth more than the cart was silently burned

**Measured on the bench, not assumed:**

| Situation | What WooCommerce does |
|---|---|
| ৳500 reward, ৳300 cart | accepted · discount ৳300 · total ৳0 · **৳200 destroyed** (single-use coupon marked used) |
| add a 2nd ৳500 reward  | also accepted · discount **still ৳300** · that coupon contributes nothing and is **also consumed** |

So enabling reward stacking last round created a way to destroy ৳700 of earned money in one order,
with nothing on screen saying so.

**`excess_reward_notice()`** on the cart and checkout: when applied rewards' face value exceeds the
subtotal it names the wasted amount — *"About ৳200 of them will not be used, and a used reward
cannot be recovered. Remove a reward to keep it for next time."*

**Warn, do not block.** Blocking means refusing customers their own money at the moment they try to
spend it, and some genuinely will not care about the remainder. Silently burning it is the only
option that is actually indefensible.

**Current rules, for reference:** rewards stack with rewards; nothing else stacks with a reward;
one use per coupon; one claim per phone ever; referrer cap 5 rewards per 30 days; claim window 30
days from first app login; friend coupon 90 days, reward 365 (0 = never).

**Tests:** NEW `test-excess-notice.php` (5) — warns with the amount when a reward exceeds the cart,
stays quiet when it fits, warns on the combined excess of two, and never removes the coupon.
Suites green: stacking 20, checkout 24, payout-status 16.

## 2026-08-07 — app 1.75.0+79 / app-api 1.68.0: many friends, many rewards

**How it works** (asked, then verified): **one coupon per rewarded friend** — `THANKS-XXXXXX`,
created by `create_referrer_reward()` at payout. Amounts are never merged into one growing coupon,
because a `fixed_cart` coupon is consumed whole on a single order: a ৳2,500 coupon spent on an ৳800
cart loses ৳1,700. Small separate coupons waste far less. The app shows **Invited / Rewarded /
Available**, then every coupon with its code, date, value, used/unused and expiry.

**The bug that was in it:** every reward is `individual_use`, so a referrer who brought five
customers held five ৳500 coupons and could spend **one per order** — five orders to collect ৳2,500.
Nobody reads that as generous, and nothing in the app said so.

Fixed with WooCommerce's own two filters, keeping the rule narrow — rewards combine with REWARDS,
and with nothing else:
- `woocommerce_apply_individual_use_coupon` → `keep_rewards_together()`
- `woocommerce_apply_with_individual_use_coupon` → `allow_reward_stacking()`

A seasonal sale coupon still cannot ride along, in either order of application. Identity comes from
new meta `_aun_referral_reward` (prefix only as a fallback for coupons issued before it), so
renaming the prefix cannot silently change who may combine with whom.

**`available` added to `summary()`** — unspent, still-valid reward money. The app's third stat is now
**Available** with "of ৳2,500 earned" beneath it when some has been spent; "earned" alone invited
"so where is it?". A line under the rewards list explains that several can be used on one order.

**Tests:** NEW `test-reward-stacking.php` (20): three rewards issued separately, all three applied to
one cart, a shop coupon refused alongside (both application orders), and the app's figures —
invited/rewarded/earned/available, one row per coupon with code and expiry, and `earned` holding
steady while `available` drops when one is spent.

## 2026-08-07 — app-api 1.67.0: "Pay the reward when: Delivered" now actually means Delivered

Owner set **Delivered** and rewards still went out at **Shipped**. My line:

```php
$out = array( $s['reward_status'] );
if ( ! in_array( 'completed', $out, true ) ) { $out[] = 'completed'; }   // ← always
```

`completed` was added unconditionally "so an order you later mark complete is never stranded".
**AST Pro flips an order to Completed at the moment it is marked Shipped**, so `completed` fired at
dispatch and the setting did nothing — the exact refused-cash-on-delivery hole it exists to close.
The same list drives `is_established_customer()`, which is why the friend could also start
inviting at dispatch.

**Fixed:** the admin's choice is honoured exactly. New opt-in `referral_reward_also_completed`
(default OFF) for a shop that genuinely wants both, with a warning next to it naming AST Pro. The
settings card now **prints the statuses that are paying right now**, so this can never hide again.

**Also fixed, found by the test:** `wc_get_orders()` silently IGNORES a status filter naming a
status WooCommerce does not have registered. If the shipment plugin is ever deactivated, or the
setting names a status that no longer exists, the filter becomes "any order at all" and every
customer looks established. `is_established_customer()` now re-checks each returned order's own
status. **Never trust a `wc_get_orders` status filter for an authorisation decision.**

**Refund after delivery now tells BOTH parties.** The referrer already got a notice; the friend —
the person who actually returned the order — got nothing. New `notify_friend_reversed()`: in-app +
push, saying the referral no longer counts and that their friend was not rewarded either. Finds the
account by phone when the stored user id is stale.

**Tests:** NEW `test-payout-status.php` (16) walking the real sequence with `wc-shipped` /
`wc-delivered` **registered as AST Pro registers them** — without that WooCommerce rejects
`set_status('delivered')` and the test proves nothing. Asserts: shipped pays nothing, the
auto-Completed that follows pays nothing, the friend cannot invite yet, delivered pays and unlocks
inviting, refund revokes + destroys the coupon + notifies both, and an admin mis-click is still
undoable. `test-referral-lifecycle.php` updated (28) — its "completed still pays too" assertion was
asserting the bug.

## 2026-08-07 — app-api 1.66.0: the coupon phone check moved OUT of coupon validity

Owner-reported, and the diagnosis is a design mistake of mine: the ownership check was hooked to
**`woocommerce_coupon_is_valid`**. That is a *validity* filter, and ownership is not validity. Three
consequences, all seen live:

1. **"Coupon applied successfully" above a ৳0 discount.** The filter is consulted at apply time AND
   again on every recalculation, with different data available each time. Apply before typing a
   phone → passes. Recalculate after typing a wrong one → fails. Applied, and worth nothing.
2. **Slow checkout.** An account + form + session lookup on every single cart calculation, for a
   fact that changes at most once per checkout.
3. **A refusal that stuck for ever.** Once the wrong number was in the session, re-applying failed
   through refreshes with the right number on screen.

**The shape now — which is what large stores do:**
- **Applying is instant and always succeeds.** No phone check at all. `woocommerce_applied_coupon`
  only ANNOUNCES the condition ("linked to 017******78 — use that number"), and stays quiet when
  the number already matches.
- **Enforced once, at placement** (`woocommerce_after_checkout_validation`), where the number the
  order will actually carry is finally known. On mismatch it **removes the coupon** and says so, so
  the customer can complete the order at full price instead of hitting a wall they cannot clear.
- **Backstop** on `woocommerce_checkout_create_order`, which fires for the block checkout and Store
  API too — routes that never reach the classic validation hook.
- One `blocked_coupons( $typed )` feeds both gates, so they cannot disagree about the same cart.

`on_coupon_is_valid()` is **deleted**. Do not reintroduce an ownership rule into a validity filter.

**Tests:** `test-coupon-checkout.php` rewritten (24) around the three live failures: applies with a
wrong number already on file and is genuinely in the cart (never "applied but ৳0"), applies in
<250ms, refused at placement AND removed, **re-applies successfully afterwards despite the stale
session**, applies before any phone is entered, every phone format, logged-in owner ordering to
another number, unlocked coupons untouched, Store API backstop stops order creation.
`test-referral-lock.php` 32 (its cart-validation section moved to the checkout suite).

## 2026-08-07 — app 1.74.0+78 / app-api 1.65.0 (DB v17): why most push stopped, and the coupon lock at checkout

### 1. "Only a few notifications work" — I caused this two rounds ago

⚠️ **Android silently refuses to display a notification whose channel the app has not created.**
No error, no log, nothing on screen. When the brand sound shipped, the channel ids gained a `_v2`
suffix in **app 1.65.0**, and `channel_for_type()` started naming them in **plugin 1.55.0**. Any
phone on an older APK than build 69 therefore received pushes addressed to channels it did not
have — and dropped every one. In-app notices still appeared on refresh, which is exactly the
"some work, most don't" pattern reported.

**Three fixes, because one is not enough:**
- **DB v17** adds `aun_app_tokens.fcm_build`; the app now sends its build with the FCM token, and
  `channel_for_build()` names the OLD channels for builds < 69. Unknown build is treated as old —
  guessing "new" loses the notification; guessing "old" costs at most the brand sound.
- **Manifest fallback**: `com.google.firebase.messaging.default_notification_channel_id` =
  `aun_default_v2`. Without it ANY future channel rename repeats this outage. **Never rename a
  channel again without checking both.**
- **Notification health card** (Settings → Notifications): push configured y/n, active device
  tokens (and how many are on pre-69 builds), each cron with its next run or NOT SCHEDULED, a
  `DISABLE_WP_CRON` warning, and notices created per type in the last 7 days — separating "the
  trigger never fired" from "it fired but never reached a phone".

### 2. Refunded / refund-due on the service-request list

The list showed a green "Paid" for ever, including on a request that was rejected and refunded.
Now ordered refunded → refund due → paid → quote, because once money is coming back that is the
only thing the customer wants to know. Shared `_MoneyLine` widget so the three cannot drift apart.

### 3. The coupon lock refused the CORRECT number

`on_coupon_is_valid()` read only `WC()->customer->get_billing_phone()` — the **session** copy.
Enter a wrong number once and WooCommerce caches it, so every later check compared against the
stale value: right number typed, field cleared, page refreshed, all refused for ever.

`known_phones()` now collects every number we can attribute to the shopper — the logged-in
**account's** number first (OTP-verified, cannot be mistyped), then the live checkout form
(`$_POST['billing_phone']` and the ajax `post_data` blob), then the session last because it is the
one that goes stale — and **any** match accepts. Placement-time validation uses the same set, so a
logged-in customer checking out to a relative's phone is no longer refused by a rule written to
protect them.

**Tests:** NEW `test-coupon-checkout.php` (14) replaying the exact live sequence — wrong number
refused, then corrected and accepted despite the stale session, via both the direct POST and the
ajax `post_data` path, every phone format, account-number acceptance, stranger still refused with a
masked hint, unlocked coupons untouched. Flutter 148, referral-lock 40, parts-pay 30, sslcommerz 26.

## 2026-08-07 — app 1.73.0+77 / app-api 1.64.0: the stuck return page, and the ghost orders

Direct SSLCommerz payment worked on the live site — but the app never closed afterwards.

**1. Stuck on "Payment received. Returning to the app…".**
⚠️ **Android's WebView does not call `shouldOverrideUrlLoading` for POST navigations**, and
SSLCommerz returns the customer by POSTing to `success_url`. So `onNavigationRequest` — the only
place the outcome was checked — never fired. The payment had gone through; the app just never
learned. **Anything that watches for a return URL must never rely on that callback alone.**

Fixed on both sides, because either alone is a single point of failure:
- App: one `_checkOutcome( url )` called from `onNavigationRequest`, **`onPageFinished`** and
  `onUrlChange`. Each signal misses a different case; page-finished is the one that catches POSTs.
- Server: the callback page now **bounces itself once, as a GET**, to the same outcome plus
  `aun_final=1` (meta-refresh AND `location.replace`, so it works without JS). A GET navigation is
  reported by every WebView on every Android version. `aun_final` means "already validated, just
  render" — so the bounce cannot re-run validation without a `val_id` and turn a paid order into a
  failure page, and it never bounces twice.

**2. Ghost ৳0 "Pending payment" orders with no customer.** `wc_create_order()` makes an EMPTY order
first; items, billing name and total are added afterwards. Every attempt that fataled in between —
the `connect-yeamazing` window — left the shell behind. Cause was already fixed; the debris was not.
- **Prevention:** `/parts/pay` watches `woocommerce_new_order` during the attempt and, if it fails,
  deletes what it made — but only when the order is pending AND unpaid AND ৳0 AND has no items AND
  no billing name AND no `_aun_sp_ref`. A real order cannot be all six.
- **Cleanup:** `AUN_App_Services::purge_empty_orders()` + a card in **Settings → Integrations** that
  appears only when there is debris, previews the ids, and moves them to **Trash** (recoverable),
  never destroys them.

Also extracted `aun_app_api_payment_page()` from the callback handler — the handler must `exit`, and
a function that exits cannot be asserted about.

**Tests:** NEW `test-pay-return.php` (18) — bounce present with and without JS, carries the right
outcome, never bounces twice, cancel/fail never render a success marker; empty shell found and
trashed while a real order is untouched. sslcommerz 26, parts-pay 30, wc-context 13, referral 28,
Flutter 148.

## 2026-08-07 — app 1.72.0+76 / app-api 1.63.0: direct SSLCommerz session (the overlay is gone)

The overlay existed to hide WooCommerce's checkout page. The better answer is not to go there at
all — which is what every serious app does: **the server creates a hosted gateway session and the
app opens the gateway URL directly.**

**New `class-aun-app-sslcommerz.php`:**
- `credentials()` — read from the **already-installed WooCommerce SSLCommerz gateway** by scanning
  `woocommerce_%sslcommerz%_settings` (gateway ids differ between the official plugin and its
  forks, so it matches the option NAME, not one hardcoded id). Admin override exists but should
  stay blank: two copies of a credential drift, and the forgotten one breaks at midnight.
- `create_session()` → `gwprocess/v4/api.php` → `GatewayPageURL`. **A fresh `tran_id` per ATTEMPT**,
  not per order: reusing an abandoned attempt's id makes the gateway reject the retry as a duplicate.
- `validate()` + `settle()` — server-to-server confirmation. **A redirect is a claim by the
  customer's browser, never a receipt.** `settle()` refuses on: non-VALID status, amount mismatch
  (pay ৳1 for a ৳3,520 order), currency mismatch, a `tran_id` belonging to another order, unknown
  order — and treats an already-paid order as success so a replayed callback or duplicate IPN
  cannot double-count. Only then `payment_complete()`, which is what drives the spare-parts
  receipt SMS, activity log and refund trail.

**Callbacks** on `template_redirect` at `?aun_sslc=success|fail|cancel|ipn`. The IPN answers plainly
and exits (it is server-to-server, nobody is watching). The others render a bare marker page the
app's WebView matches on. **The `ipn_url` matters most** — it arrives even if the customer kills the
app mid-payment.

**Fallback preserved:** no credentials, gateway unreachable, or session refused → the WooCommerce
checkout path that has already taken real money. Recorded to `aun_app_last_pay_error` so a gateway
quietly refusing sessions is visible instead of hiding behind a working fallback. `pay_payload()`
returns `direct: true|false` so the app knows which it got.

**Why NOT "payment separate from WooCommerce"** (the owner asked): the order is the single record
the refund trail, the admin badge and the plugin's SMS all hang off. A second ledger would need
reconciling by hand for ever, and the first refund would prove it. Only the checkout PAGE is
bypassed. Still no `sslCommerzSdk.aar`: its own payment UI is a WebView on this same hosted page —
we now open that page directly, without shipping credentials in the APK.

**Tests:** NEW `test-sslcommerz.php` (26) — credential discovery + override, session payload
(amount/currency/order-id/success+ipn urls), fresh tran_id per retry, and **every settle() refusal
proven to refuse**, replay safety, dead-gateway → WP_Error not fatal. parts-pay 30, wc-context 13,
Flutter 148.

**To deploy:** `aun-app-api.zip` (1.63.0) + APK (1.72.0+76). Then check
**Settings → Integrations → "In-app payments (SSLCommerz)"** — it should say *✓ direct payment
ready* and name the option it read the store id from. ⚠️ If that gateway is in **sandbox**, the card
says so; real money only moves when it is not.

## 2026-08-07 — app 1.71.0+75 / app-api 1.62.0: first successful live payment, three polish fixes

**Payment confirmed working end to end on the live site** (order #9292, ৳15, SSLCommerz).

**1. Website pages flashed past before the gateway.** The overlay was appended after `load`, so the
customer watched the checkout form, the terms box and WooCommerce's intermediate redirect page
appear and vanish. It is now printed at the **start of `<body>`** via `wp_body_open`
(`aun_app_api_checkout_cover()`), so it paints before anything else and stays up across each
redirect — every one of those pages runs the same hook. It only ever COVERS; the 8-second timer
still removes it if the submit failed. ⚠️ Depends on the theme calling `wp_body_open` (Flatsome
does); without it there is simply no overlay, which is the old behaviour, not a break.

**2. Internal order bookkeeping was in the customer's status history** — "order #9292 created",
"order #9292 refreshed — now ৳15.00", one line per tap of Pay. Two fixes:
- The app timeline now excludes event type **`wc_order`** (plumbing) while keeping `payment` and
  `refund` (real money events). The plugin's type separation was already exactly right.
- `/parts/pay` **reuses an existing unpaid order** when its total still matches the current payable
  instead of rebuilding it, so tapping Pay twice no longer writes a "refreshed" line at all. Compared
  against the live payable, never assumed — if the admin edited a price the totals differ and it
  falls through to the rebuild, which is when rebuilding earns its keep.

**3. A paid parts request looked identical to an unpaid one in My service requests.** The list now
shows a green "Paid ৳15.00" line with a tick, and unpaid quotes gain "Pay online or cash on
delivery" under the amount — the most useful difference on that screen, previously invisible.

**Tests:** `test-parts-pay.php` now **30** (second tap reuses the order and writes no extra
`wc_order` event; a price change still rebuilds to the new total; no "created"/"refreshed" lines
reach the customer timeline). wc-context 13, referral suites green, Flutter 148.

## 2026-08-07 — app 1.70.0+74 / app-api 1.61.0: the checkout reload loop

The auto-submit added in 1.59.0 clicked Place Order without ticking WooCommerce's **terms**
checkbox. Validation failed → page reloaded → the script ran again → submitted again. An infinite
flashing checkout, and the customer could never read the error explaining why.

**Two fixes, and the second matters more than the first:**

1. Tick `input#terms` (with a `change` event, so anything listening reacts) before submitting.
2. **A loop guard: auto-submit AT MOST ONCE per order, ever.** `sessionStorage` keyed on the path,
   checked before anything else. Any failure — unticked box, declined card, gateway timeout —
   reloads the page, and an auto-submitter that does not remember trying will retry for ever.
   **Any future auto-submit must carry this guard.** Also: never auto-submit over a visible
   `.woocommerce-error`, and drop the "Opening secure payment…" overlay after 8s so a failed submit
   shows the page instead of a spinner that never ends.

**Consent:** the app ticks the terms box on the customer's behalf, so the app now SAYS so, on the
button they press — "By continuing you accept our terms and conditions." (`payTermsNote`, bn+en).
Ticking it silently would be putting words in their mouth.

**Tests:** NEW `scratchpad/loop-test.html` — runs the exact printed script against a synthetic
checkout with a click counter. **8/8**, including *"the page reloads five times → still only ONE
submit"*. Served over the local PHP server and read through the Browser pane (a `file://` page
renders only as a static snapshot; and `navigate` to 127.0.0.1 is policy-blocked — use
`preview_start` with the URL instead).

## 2026-08-07 — app-api 1.60.0: the "critical error" was a missing WooCommerce session

**Root cause, from the live error card:** `connect-yeamazing`
(`YEAMCO_WcHooks.php:119` — "Call to a member function get() on null") hooks order creation and
reads `WC()->session`. **WooCommerce does not start a session for REST requests** — verified in its
own source: `WooCommerce::is_request('frontend')` returns false when `is_rest_api_request()` is true
(class-woocommerce.php:660), and `init_session()` only runs for front-end requests. So
`WC()->session` was null and that plugin fataled.

This is also exactly why the WEBSITE never hit it: its "Pay online" goes through **admin-ajax**,
which IS a front-end request and therefore has a session. Only the app took the REST path.

**Fix:** `AUN_App_Services::ensure_wc_context()` — creates the session (honouring the
`woocommerce_session_handler` filter), the `WC_Customer` for the logged-in app user, and the cart,
before `create_order()`. Our request now looks like the one every other plugin was written against.
**Any REST endpoint that creates or mutates a WooCommerce order should call this first.**

**Also:** WooCommerce's `wc_create_order()` catches `Exception` internally and returns `WP_Error`, so
a hook throwing an Exception lands in the `! $order_id` branch, not in our `catch ( Throwable )`.
That quiet path now records to `aun_app_last_pay_error` too — otherwise the admin card stays empty
for half the failure modes. (The live crash was an `Error`, which does propagate to our catch.)

**Tests:** NEW `test-wc-context.php` (13) — reproduces the exact fatal shape with a hook calling
`WC()->session->get()`, proves order creation survives, and proves a *different* exploding hook
still yields a clean 503 with the cause recorded for the admin and nothing internal leaked to the
customer. ⚠️ Bench caveat: under WP-CLI a session already exists, so the bench cannot reproduce the
NULL-session condition itself — the fix is derived from WooCommerce's source, not from a red test.
parts-pay 24, referral suites green.

## 2026-08-07 — app 1.69.0+73 / app-api 1.59.0: three payment bugs from live testing

**1. Pay landed on WooCommerce's method chooser, not the gateway.** The order-pay page is a chooser,
and the customer has already chosen — they tapped "Pay online securely". `aun_app_api_checkout_chrome()`
now auto-selects and submits **only when exactly one payment method exists** (the spare-parts plugin
already strips COD from its own orders, so SSLCommerz is usually alone), behind a
"Opening secure payment…" overlay. With two or more methods the chooser correctly stays — picking
for the customer would be guessing.

**2. Approve/Decline AND Pay shown together on a quote.** The app asked the same question twice with
three answers, one of which (Decline) contradicts another (Pay). `can_pay` now excludes
**`quote_sent`** as well as the closed statuses, so the flow is: decide first → then cash on
delivery by default, with Pay online as an option. Deliberately stricter than the website tracker,
which lets an SMS recipient pay straight from a quote — there, paying IS the answer. Guarded in the
app too (`_PaymentCard` returns nothing while awaiting) so an older server cannot reproduce the
confusing screen. `_refresh()` after a decision makes the Pay button appear the instant they approve.

**3. "There has been a critical error" when paying a new request.** `AUN_SP_Woo::create_order()`
reaches deep into WooCommerce (products, taxes, gateways), and a fatal there returned a WordPress
error PAGE which the app then showed the customer as raw HTML. Now wrapped in `catch ( Throwable )`:
the customer gets a sentence and cash on delivery still works, and the cause is recorded in
`error_log` **and** in option `aun_app_last_pay_error`, surfaced as a red card in
**Settings → Integrations → "Last spare-parts payment failure"** (with a Clear button). Most site
owners cannot read a PHP error log, and "critical error" is not a bug report.
⚠️ **The underlying fatal is not yet diagnosed** — it did not reproduce on the bench (24/24 green).
The card is what will name it on the live site.

Tests: `test-parts-pay.php` now **24** (adds: no pay button while awaiting, approving turns it on,
declining turns it off). Flutter 148, analyze clean.

## 2026-08-07 — app 1.68.0+72 / app-api 1.58.0: payment moves INSIDE the app

The previous round opened the payment page in the external browser. Wrong call — the customer left
the app. Now an in-app WebView (`lib/src/screens/payment_screen.dart`), which is what Daraz, Pathao
and Foodpanda do here. `webview_flutter` was already a dependency (the YouTube embed), so no new
native plugin.

**The credentials still never enter the app, and that part is not negotiable:** the SERVER creates
the payment URL, the app only displays it. SSLCommerz's Android SDK wants the store ID and password
in the APK — a zip anyone can unpack — and those same credentials authorise refunds and transaction
queries. Server-created session + WebView is the standard answer and costs the customer nothing;
they never see a browser.

**Things that will break an in-app checkout in this market, handled:**
- **Wallet deep links.** bKash/Nagad hand off with `bkash://`, `intent://`, `tel:`. A WebView cannot
  load those and shows a blank page. `onNavigationRequest` sends any non-http(s) scheme to the OS.
- **Back button.** Goes back a WebView page first; only asks "leave payment?" at the first page.
- **Theme chrome.** `/parts/pay` appends `?aun_app=1`; `aun_app_api_checkout_chrome()` hides header,
  footer, menus and breadcrumbs on checkout pages. A **cookie** carries the flag across the gateway
  round trip, because the customer returns to a fresh page load with no query string of ours.
  Presentation only — it never touches prices, the order or the gateway.
- **Trust.** A permanent padlock + real hostname bar under the WebView.

**The WebView's result is never believed.** Reaching `order-received` only means they got to the end
of checkout; whether money arrived is the SERVER's answer, because the gateway confirms to it and
not to the phone. The screen re-fetches and shows whatever the server says.

Tests: `test-parts-pay.php` still 21/21 (pay URL now carries `aun_app=1`), Flutter 148, analyze
clean. ⚠️ First real Gradle build with the new payment screen happens in the user's build script.

## 2026-08-07 — app 1.67.0+71 / app-api 1.57.0: online payment for spare parts (first money in the app)

Spare-parts 0.29.0 added online payment on the website. This brings the same thing into the app.

**How the plugin actually does it** (read before changing anything here): payment is a real
**WooCommerce order**, minted ON DEMAND by `AUN_SP_Woo::create_order()` and paid at
`$order->get_checkout_payment_url()` with whatever gateways the shop has enabled. Cash on delivery
creates no order at all — it is simply what happens if the customer does nothing.
`AUN_SP_Woo::customer_summary()` is the single source of truth for the money position.

**⚠️ The app deliberately does NOT use `sslCommerzSdk.aar`, and this should not be "fixed" later.**
Three reasons, in order of cost: (1) the SDK needs the merchant store ID + password inside the APK,
where anyone can unzip them out — publishing them, in effect; (2) the gateway's server-to-server
callback is what marks the order paid, and THAT is what fires the plugin's receipt SMS, the
activity-log entry and the refund trail — a payment settled inside the app leaves all of those
empty; (3) WooCommerce already offers every method the shop enables (cards, bKash, Nagad) without
the app knowing they exist. The AAR is left unused in the workdir.

**Backend:** `AUN_App_Services::payment_summary()` wraps `customer_summary()` (guarded by
`method_exists` — the two plugins ship separately). The spare payload gains `delivery`, `payable`
(= quote + delivery, computed server-side), `payment{…}` and `can_pay`. New `POST /parts/pay`
mirrors the website's `ajax_pay` including the **implicit approval** — paying is a stronger yes than
pressing Approve — but adds the authorisation the website does not need: the ref must belong to the
caller's phone, or anyone could mint an order against a stranger's request by guessing a ref.

**App:** `SpPayment` model (totals kept as pre-formatted STRINGS so the app never gives a second
opinion about someone's money). New `_PaymentCard` with four mutually exclusive faces — refunded /
refund due / paid / owing — because the customer's question is different in each. The owing face
shows parts + delivery adding up to the button's figure. **`PartsDetailScreen` now re-fetches on app
resume**: the browser is a different app and the gateway confirms to the SERVER, so returning from
payment is just a resume, and the server decides whether the money arrived.

**Tests:** NEW `test-parts-pay.php` (21): payload fields, payable arithmetic, no order until asked,
pay URL, auto-approval, order total = parts + delivery, **a stranger gets 404**, paid state, pay_url
disappears once settled, paying twice = 409, rejected request refuses. Flutter 148, all referral
suites green.

**To deploy:** upload **both** zips (`aun-spare-parts.zip` 0.29.0 if not already live, and
`aun-app-api.zip` 1.57.0) and rebuild the APK (1.67.0+71). Then check
**WooCommerce → Settings → Payments** has SSLCommerz enabled — the app's Pay button leads there.

## 2026-08-07 — app 1.66.0+70 / app-api 1.56.0: claim window from first app login, profile row

**The claim window now runs from FIRST APP LOGIN, not WordPress account creation.**
`aun_app_signup` is stamped on first successful OTP verify for **every** account (previously only
for accounts the app itself created), and `joined_at()` reads it with `user_registered` as a
fallback. Measuring from the WP account refused people who made a website account years ago, never
bought, and were installing the app for the first time — exactly the new customers the programme
exists to attract.

**And the customer is now told the deadline** instead of discovering it the day it lapses.
`summary()` returns `claim_window_days` + `claim_deadline`; the app shows "Redeem by 6 Sep 2026" on
the status row AND inside the enter-a-code dialog. `claim_blocked_reason()` gained `too_late` so the
redeem box is not offered to someone whose window has closed — it mirrors every rule `claim()`
enforces, which is the standing rule for that function.

**Settings profile is now a tappable ROW** (avatar + name + phone + chevron) opening the existing
`showProfileSheet()` — the same editor the Home avatar opens, so there is one profile editor rather
than two that can drift. The always-open form implied unsaved state on every visit and pushed
language and referrals below the fold.

**Design options for "My devices"** published as an artifact (compact status row / hero card with
warranty ring / grouped by state). Recommendation: the compact row. Awaiting the owner's choice.

## 2026-08-07 — app 1.65.0+69 / app-api 1.55.0: coupon expiry in the app, brand sound, haptics

**Expiry dates are now visible and follow the admin settings.** `summary()` returns
`my_coupon_expires`, per-reward `expires`, and `friend_expiry_days` / `reward_expiry_days`. The app
shows "Valid until 12 Nov 2026" (or "No expiry date") under the coupon and on every reward row, and
the terms paragraph states both durations. **Read from the COUPON, never recomputed from today's
setting** — changing the setting must not appear to move a deadline on a coupon already issued.
Dates travel as ISO and are formatted per locale in the app.

**Brand notification sound.** `android/app/src/main/res/raw/aun_notification.wav` — 1.05 s: a soft
filtered-noise "lamp breath", then a rising A5–C#6–E6 bell with inharmonic partials and exponential
decay. Generated by `scratchpad/make_sound.py` (pure stdlib, no numpy on this machine) — **keep that
script if the sound ever needs regenerating.**
⚠️ **Android freezes a channel's sound at creation and ignores later edits, for ever.** Shipping a
new sound therefore required NEW channel ids: every channel is now `*_v2`, old ids are deleted in
`createNotificationChannels()` so Settings does not show duplicates, and
`AUN_App_Push::channel_for_type()` was updated to match. **Those two lists must change together.**
Firmware/guides deliberately keep the system default — the brand sound is for messages about the
customer's own business with us; hearing it for content we published is how a nice sound becomes an
irritating one. New `aun_referral_v2` channel.

**Haptic vocabulary** in `lib/src/ui/haptics.dart`: `selection` / `confirm` / `success` (two taps
rising — Android has no success-notification haptic) / `warn` / `failure`. Deliberately five words,
because a phone that buzzes at everything says nothing. Nothing fires on scroll, navigation, tab
switches or pull-to-refresh. `confirmHaptic()` now delegates here so there is one place to tune it.

**Answered, not changed:** the Claim window (30 days) is measured from **WP account creation**, not
from receiving a code — see the note in the reply; worth revisiting if website-era accounts start
being refused.

**Pending the owner's choice:** three "My devices" card designs were shown (compact status row /
hero card with warranty ring / grouped by warranty state) and a recommendation on the always-open
Settings profile section. Neither implemented yet.

## 2026-08-06 — app-api 1.54.0 (DB v16): referral payout hardening, 7 owner-reported issues

All seven came from the owner testing the live flow end to end. Several were real money leaks.

**1. A placed COD order made you an inviter instantly.** `can_invite()` used
`has_purchase_history()`, which counts `processing` — so: order at 10am, mint a code, invite the
neighbourhood, refuse the parcel at the door. Codes stayed live. The two callers pull in OPPOSITE
risk directions, so they are now different functions: `has_purchase_history()` stays BROAD (decides
who is REFUSED a welcome discount — a pending order is still enough to say "not new"), while new
`is_established_customer()` is NARROW (decides who may MINT codes — a payout-status order, or a
registered projector, which is a serial we sold).

**2. Reward now pays on a configurable status** (`referral_reward_status`, default `completed`).
The store runs **AST Pro** (adds `wc-shipped` / `wc-delivered`) — set it to **Delivered**. Hooked to
`woocommerce_order_status_changed` so custom statuses work; the dropdown hides refunded/cancelled/
failed/pending, and `completed` always pays too so a later completion is never stranded.

**3. Reward expiry split from the friend's.** A welcome discount is a promotion and deserves a
deadline; an earned reward is money the referrer worked for. New `referral_reward_expiry_days`,
default **365**, **0 = never expires**.

**4. The reward coupon is now phone-locked** like the friend's, and the email restriction is GONE
from both. Email bound nothing on an app account (no `user_email`) and bound the wrong thing when
it did. One lock, OTP-verified, on both coupons.

**5. `set_individual_use(true)` on BOTH coupons** — the reward never had it, so it could be stacked
on top of a seasonal sale coupon.

**6. Revocation now explains itself:** `notify_reward_revoked()` → in-app notice + push, saying the
order was cancelled/returned, that nothing else is affected, and that other rewards are kept.
Taking a reward back silently is how a loyal customer decides they were cheated.

**7. An admin mis-click is no longer permanent.** Cancelled → corrected used to leave the claim
REVOKED for ever. DB v16 adds `revoke_reason`: `reversed` (undoable) vs `ineligible` (a judgement
about the buyer, never undone by a status change). Returning to a paying status reinstates the claim
and issues a fresh reward coupon.

**Found while testing #6/#7 — the clawback could silently fail.** `claim_for_order()`'s fallback
filtered on `PENDING`, so an order placed WITHOUT the coupon was matched and paid via that fallback,
and then on refund matched nothing (no longer pending) — the reward survived a returned order. Now
tried in order: coupon on the order → **`order_id` on the claim (any status)** → pending-buyer
fallback. The last one stays PENDING-only on purpose, or a customer's later unrelated refund would
reach back and revoke a reward a different order earned.

**Partial refunds** (`woocommerce_order_refunded`) are judged on what was KEPT: only a refund that
drags the kept amount below `min_order_total` revokes. A ৳200 goodwill refund on a ৳60,000 projector
must not punish the referrer for our own customer-service gesture.

**Tests:** NEW `test-referral-lifecycle.php` (28) covering all seven. Bench suites all green: lock
40, identity 5, reason 9, reset 19, settings render 16.

**To deploy:** upload `aun-app-api.zip` (1.54.0) — **DB v16 migration runs on activation**. No APK
needed (1.64.0+68 still pending from the previous round). **Then set "Pay the reward when" to
Delivered.**

## 2026-08-06 — app-api 1.53.0: test the referral programme with only two SIMs

The programme lets a number claim **once, ever** — right in production, impossible to test against.
Settings → Referrals → **"Testing with your own number"**:

**1. Test lines** (`referral_test_phones`, one per line, any format). These may redeem a code even
though they are already customers. It bypasses **that one rule** — self-referral is still blocked,
the reward is still paid only on completion, the clawback still applies, the coupon is still locked
to the number. Active test lines are echoed back in an amber warning so they cannot be left on by
accident.

**2. Reset a number's referral history.** `reset_for_phone( $phone, $dry_run )` removes claims as a
friend (by phone, matching `has_claimed`), claims as a referrer (all accounts answering to that
number), the coupons those claims issued, and the invite code row. **Never** orders, devices,
warranties, tickets or the account — a test tool that could delete a real customer's purchase
history would be far worse than the inconvenience it saves.

**Two-step by design**: the first click only ever reports what exists ("about to delete: 1 claim,
0 invitations, 1 coupon WELCOME-K3P7QA, their invite code"), and deleting needs a second explicit
confirm. It destroys real coupons, so one mistyped digit must not be able to wipe a real customer.

Also: `can_claim` is now literally `'' === claim_blocked_reason()`, so the flag and the reason can
never disagree — one function decides, both fields come from it.

**Tests:** NEW `test-referral-reset.php` (19): dry run changes nothing, real reset clears
claim/coupon/code, account and order survive untouched, still blocked as an existing customer after
a reset, unblocked once marked a test line, and **self-referral still refused for a test line** —
the bypass is one rule, not a free pass.

⚠️ **Bench gotcha:** `wc_get_coupon_id_by_code()` memoises in the object cache within a single
request, so it keeps returning the id of a coupon that has just been deleted. Assert deletion via
WooCommerce's own query (`post_title` + `shop_coupon` + `publish`) or by `new WC_Coupon( $code )`
throwing "Invalid coupon" — not by that helper.

## 2026-08-06 — app 1.64.0+68 / app-api 1.52.0: the referral card explains itself instead of vanishing

Driven by a real cost: the owner spent a morning debugging a feature that was working correctly,
because "not eligible" and "broken" looked identical from the app. **An absence cannot be read.**

**New `ReferralFace.used`.** Programme running, cannot invite, cannot claim, holds no coupon —
previously `hidden`, now a quiet card that says *"You have already used a referral code. One code
per customer. Register your AUN projector to start inviting friends and earning rewards yourself."*
It is styled deliberately UNLIKE an offer (grey accent band, grey icon, `isInformational`): an
explanation dressed as a promotion is a worse lie than silence. `hidden` now means one thing only —
**the programme is switched off** — because advertising a discontinued offer to avoid an empty space
is worse than the empty space.

**"What is available to you" status card** on the referral screen, always shown, both halves
always stated: *Inviting friends* and *Redeeming a friend's code*, each with ✓/lock and a reason.
A customer is normally eligible for only one side; saying nothing about the other is what made this
look broken. The lock icon is neutral grey, never red — ineligibility is not an error.

**`can_claim` now mirrors EVERY rule `claim()` enforces.** It omitted the purchase-history test, so
an existing customer was shown "I have a code" and then refused with "referral codes are for
first-time customers" — an offer we already knew we would not honour. New `claim_blocked` field
returns the reason as data (`'' | off | used | existing_customer`), so the app states a customer's
standing instead of inferring it from a combination of booleans. Inference is what produced the
silent blank in the first place.

**Tests:** Flutter referral suite 19 (used-state explains, only-off hides, informational styling
never applied to a real offer, blocked reason carried through, absent reason not invented); NEW
`test-claim-reason.php` bench suite (9): brand-new friend unblocked, already-used → `used`,
existing customer → `existing_customer` **and** can invite instead, programme off → `off`.
Full suite **148 Flutter pass**, analyze clean, all bench suites green (lock 40, identity 5).

**To deploy:** upload `aun-app-api.zip` (1.52.0) **and** rebuild the APK (1.64.0+68) — both sides.

## 2026-08-06 — app-api 1.51.0: the claim is keyed by PHONE, so read it back by phone

The diagnostic ended the guessing in one shot. Account #1681 returned
`can_invite:false, can_claim:false, my_coupon:(none), has used a code before: YES` — so the app
had nothing to show and correctly showed nothing. **That account had already redeemed a code**,
which is per-number and permanent by design; it was not a visibility bug at all.

But the payload was self-contradictory in a way that exposed a real bug. `has_claimed()` and the
one-claim-per-person rule key on **`referred_phone`** (deliberately — deleting and recreating an
account must not buy a second discount), while `summary()` read the claim back by
**`referred_user_id`**. When those disagree — recreated account, or a claim made while matched to a
different WP user with the same number — the customer is told "you have already used a code"
(found by phone) AND shown no coupon (not found by id). Both doors shut: cannot claim again,
cannot reach the coupon they were given.

Fixed with one `claim_row( $user_id, $phone, $status )` used everywhere, matching id OR phone,
newest first. `claim_for_order()`'s fallback gained the same treatment and is arguably the bigger
win: it matched the buyer by account id only, so **an app customer who checked out as a guest, or
under a different account with the same number, never paid the referrer.** It now matches the
order's billing phone too.

**Diagnostic extended** to show the claim row (date, status, coupon), the coupon's real state
(unspent / already spent / deleted), and a ⚠ line when the claim sits on a different account id
with the same number. The "nothing to show" verdict now says plainly that it is correct behaviour,
that registering a projector resolves it, and that re-testing the redeem flow needs a different SIM.

**Tests:** NEW `test-claim-identity.php` (5) — claim written under one account id and read back
under another with the same number: still refuses a second claim, still surfaces the coupon, app
face becomes `coupon` not `hidden`, and an order under that number still finds the claim.
`test-referral-lock.php` 40 pass, diagnostic suite green, `php -l` clean.

## 2026-08-06 — app 1.63.0+67 / app-api 1.50.0: stop guessing why a customer can't see the referral card

App 1.62 was confirmed installed and the card was STILL missing for a no-device account. Bench
trace of the current plugin against a fresh phone-only user proves the backend is right
(`can_invite:false, can_claim:true` → app computes face `claim` → shows). So the remaining
suspect is **what the LIVE site returns** — most likely a plugin older than the one that added
`can_claim`.

**App fix — an old server must not be able to hide the feature.** `ReferralSummary.fromJson`
treated an ABSENT `can_claim` as `false`. A site running a pre-`can_claim` plugin therefore blanked
the redeem entry for everyone without a projector, with nothing in the app to explain it. Absent
now means "server didn't say" → allow; the claim endpoint is the real authority and refuses with a
precise reason. An explicit `false` is still honoured.

**Admin diagnostic — Settings → Referrals → "Why can't a customer see the referral card?"**
Type the customer's number, get the actual payload (`enabled` / `can_invite` / `can_claim` / `code`
/ `my_coupon`, plus "has bought before" and "has used a code before") and the verdict:
INVITE / REDEEM / COUPON / NOTHING, with the reason in words. `referral_diagnose()` mirrors
`ReferralSummary.face` rather than describing it, so admin and app cannot drift. Accepts any phone
format. When the verdict says the card SHOULD show, it tells you to check the phone's app version —
closing the last loop.

**Plugin version now shown in the page header** (`plugin v1.50.0` badge). "Is the new backend
actually live?" was unanswerable from inside wp-admin, and a stale upload looks exactly like an app
bug — which is precisely how this round started.

**Tests:** referral suite 16 Flutter (explicit-false honoured, absent-key allows, can_invite
default unchanged); NEW `test-diagnostic.php` bench check (4 phone formats, full row output,
blank/garbage/unknown-number error paths, programme-off diagnosed as programme-off rather than
blamed on the customer). Full suite **145 Flutter pass**, analyze clean, `php -l` clean.

## 2026-08-06 — ⚠️ THE STALE-APK TRAP (cost three rounds of "the fix doesn't work")

The referral card was reported missing three times after it had been fixed, verified and built.
The code was never the problem. **The output filename changed** (`AUN-Projector-app.apk` →
`AUN-Care-Bangladesh.apk`) and the old July build was still sitting in the same folder — two
similar `.apk` names side by side, and the stale one kept being the one installed.

How it was diagnosed, and how to diagnose it next time in one minute:
1. `aun-app-build.log` timestamp + last line → did the build actually succeed, and when?
2. `ls -la *.apk` → is the file the user is sending the file the build just wrote?
3. `grep -a "<a string only the new build has>" the.apk` → decisive. Release AOT keeps literal
   strings, so grepping the APK for a new UI string proves whether a fix is inside it.

Fixed so it cannot recur: `build-aun-app.cmd` now **deletes any old-name APK** after a successful
build and **prints the version** it just wrote. The stale file was renamed to
`OLD-2026-07-23-DO-NOT-INSTALL.apk.bak` (not deleted). Users can self-check in the app:
**Settings → App version**.

Rule of thumb for any future "the fix isn't working": before touching code, prove which build is
running. `flutter analyze` + tests passing says nothing about what is installed on the phone.

## 2026-08-06 — app-api 1.49.0: settings page rebuilt + admin override for the coupon lock

**Admin override.** `AUN_App_Referrals::unlock_coupon( $code )` removes `_aun_referral_phone` from
one coupon; `coupon_lock_status()` reports what a coupon is currently locked to. Surfaced in
Settings → Referrals → "Release a coupon's phone lock". **Releases only** — there is deliberately no
"lock it to a different number", because the only number we can vouch for is the one that passed
OTP, and letting an admin type a replacement turns a verified fact into a typo. Single-use, expiry
and minimum-order survive the release (tested). Releasing twice is harmless — support will click it
twice.

**Settings page redesigned.** It had become one endless column where new settings were appended to
whichever card happened to be last, so things were filed under headings they had nothing to do with:
- **OneDrive / SharePoint (firmware & manuals)** was inside **"What to Watch"**.
- **YouTube Data API key** (video guides) was also inside **"What to Watch"**.
- **"Login screen video"** was under **"Home Screen"**.
- The ticket test, maintenance-reminder test and testing note floated at the bottom of the page,
  detached from the features they test.

Now a sticky left nav with seven sections — Support & contact / App screens / Notifications /
Integrations / What to watch / Referrals / App release — each with a one-line explanation of what it
actually affects. Every test tool moved next to its own feature (the maintenance test lives under
Notifications, the ticket test inside the osTicket card).

**Mechanics worth knowing:**
- ONE `<form>` spans every section, so a single Save covers the lot and switching sections cannot
  lose a half-typed change. Hidden panels are `display:none`, and hidden inputs still submit —
  verified in-browser (43 fields reach the save handler).
- Action buttons use the HTML `form="…"` attribute to reach standalone forms rendered outside the
  settings form. **HTML forbids nested forms**; this is what lets each tool sit with its feature
  instead of in a heap at the bottom.
- **Every input `name` is unchanged**, so the save handler needed no edits — the redesign cannot
  silently drop a setting.
- The chosen section is remembered in `localStorage` + the URL hash, so saving (a page reload)
  returns you to the section you were editing. Unknown/renamed section falls back to the first.

**Tests:** `test-referral-lock.php` now **40 pass** (10 new for the override). NEW
`render-settings.php` bench check: all 7 panels render once, forms balanced with no nesting, divs
balanced, all 38 settings fields still present, and the three misfiled settings assert as being in
their NEW section and absent from the old one. Browser-verified: tab switching, hash/localStorage
memory, and every `form=` association resolving to the right form.

**To deploy:** re-upload `aun-app-api.zip` (1.49.0). **No APK rebuild** — this round is backend only.

## 2026-08-06 — app 1.62.0+66 / app-api 1.48.0: a referral coupon now belongs to ONE phone number

**Why:** with no email on app accounts, a referral coupon was a bearer token — single-use and
expiring, but whoever typed the code first got the discount, including anyone the friend passed it
to. The *reward* was already well guarded (`on_order_completed()` revokes the claim if the buyer's
phone belongs to an existing customer); the *discount* was not.

**The lock.** `claim()` already knows the friend's OTP-verified number, so the coupon is stamped
with it: coupon meta `_aun_referral_phone` = canonical `8801XXXXXXXXX` (`COUPON_META_PHONE`).
Coupons issued before this carry no meta and stay unlocked **on purpose** — retro-locking a code
someone already holds breaks a promise we made.

**Matching is forgiving about format, strict about identity.** `phone_matches()` canonicalises
through `AUN_App_Phone::normalize()`, so `+8801712345678`, `01712345678`, `1712345678` (no leading
zero), `017 1234 5678`, `017-1234-5678` and `00880…` are all the same number. The one addition is
stripping a leading `00`, done locally in the referrals class rather than in the shared phone class
that OTP login depends on.

**Two hooks, deliberately.**
- `woocommerce_coupon_is_valid` — LENIENT. Judges only when a billing phone is already known,
  because people apply the code before filling the form and refusing there reads as "this code is
  broken". Throws an `Exception`, which WooCommerce turns into the customer-facing notice.
- `woocommerce_after_checkout_validation` — STRICT. By then the phone has actually been submitted,
  so blank is a real answer. **This is the gate that stops the order.**

**The refusal explains itself** and shows the number *masked* (`017******78`, via
`AUN_App_Phone::mask`) — enough for the owner to recognise, useless to a stranger. Separate wording
for "no phone entered" vs "wrong phone".

**Told up front, not at the till.** `POST /me/referral/claim` returns `phone`, and `summary()`
returns `my_coupon_phone` (read from the COUPON's own meta, so an unlocked older coupon is never
described as restricted). The app replaced the redeem snackbar with a dialog showing the coupon and
"Use 01712345678 as your mobile number at checkout", and repeats the note on the coupon card —
the dialog is seen once, the card is where they return at checkout. Formatting lives server-side
(`display_phone()`) so the promised number and the compared number cannot drift.

**Tests:** NEW bench suite `test-referral-lock.php` — **29 pass** (10 input formats, wrong/garbage/
landline/too-short rejections, empty-lock-matches-nothing, meta round-trip, unlocked-coupon
passthrough, lenient-while-unknown, format-insensitive pass, wrong-number refusal, message masks
the number, already-invalid coupon not re-judged). Flutter **143 pass**, analyze clean.
⚠️ Bench gotcha re-confirmed: `wp eval-file` runs in FUNCTION scope — a file-level `$pass` is not
the `global $pass` a helper increments; use `$GLOBALS`.

**Residual, accepted:** WooCommerce Blocks checkout does not fire `woocommerce_after_checkout_
validation`; the `is_valid` filter still covers it whenever a phone is known. The classic checkout
this site uses is fully gated.

**To deploy:** re-upload `aun-app-api.zip` (1.48.0) **and** rebuild the APK (1.62.0+66). No DB
migration.

> ⚠️ This log has entries only up to v1.45. The rounds between v1.45 and v1.59
> (referral programme, planner) were shipped but never written up here — read
> `git log` for those. Everything above/below this line is still accurate.

## 2026-08-06 — app 1.61.0+65 / app-api 1.47.0: referral audit — the coupon nobody could spend

**The programme-breaking one.** `create_friend_coupon()` and `create_referrer_reward()` both called
`set_email_restrictions( array( $user->user_email ) )`. App accounts are created from a PHONE —
`AUN_App_REST::create_user()` never passes a `user_email` — so every app customer's coupon was
locked to the **empty string**. WooCommerce treats a non-empty restriction array as a real
restriction and matches it against the billing email at checkout, which nothing can satisfy. So the
friend redeemed a code, was told "your discount is ready", and the coupon was then **rejected at the
till**. Same for the referrer's THANKS reward. Fixed by `customer_email()` — app profile meta
(`aun_app_email`) first, then `user_email`, `is_email()`-validated — and restricting only when
there IS an email to restrict to. Single-use + per-user limit + expiry + minimum-order still apply.
Coupons already issued are healed where they are read (`heal_coupon_restrictions()`, called from
`summary()`): a live customer's real reward is repaired, not written off, and no migration to run.

**Percentage rewards were shown as taka (user-reported).** Admin sets "referrer gets 5%", the app
showed "৳5". `referralYouGet` / `referralStep3` carried a hardcoded ৳ around a raw number. The
backend was right all along — the reward coupon is always issued `fixed_cart` via `reward_value()`
against the friend's real order total — so this was purely a lie in the copy.

**৳ printed twice (user-reported).** Same root cause from the other side: `referrerLabel` returned
"৳500" and was then dropped into a string that already had a ৳ → "৳৳500". `referrerLabel` is gone;
one helper `referrerRewardLabel()` now decides the wording, and a percentage gets a whole different
sentence (`referralRewardPercent`, "5% of their order") because a percentage is not a currency with
a different symbol.

**Third referral face.** `can_claim` flips false the instant a friend redeems — so a card keyed on
it disappeared at the exact moment they were handed a coupon, taking the only screen that shows
that coupon with it. `ReferralSummary.face` (`invite` / `claim` / `coupon` / `hidden`) is now the
single decision, replacing the boolean-per-screen guessing that caused all three bugs.

**Tests:** `test/referral_visibility_test.dart` now 14 (three faces, redeem-doesn't-vanish, both
currency bugs in EN **and** BN, friend-label double-sign guard). Full suite **143 Flutter tests
pass**, analyze clean, `php -l` clean.

**To deploy:** re-upload `aun-app-api.zip` (1.47.0) **and** rebuild the APK (1.61.0+65). Both sides
changed. No DB migration.

## 2026-08-06 — app 1.60.0+64: the referral programme was invisible to the friend it exists for

**The bug (user-reported):** the referral card only appeared for customers with a registered
projector. Someone with no device saw nothing — and since the *redeem a friend's code* field
lives behind that card, the one person a code is meant to reach had no way to enter one.

**Root cause:** `ReferralSettingsCard` gated the whole card on `can_invite`. The backend was
already right: `referral()` returns `can_invite` and `can_claim` as two independent answers
(`can_invite` = has purchase history, `can_claim` = programme on and hasn't used a code yet).
The app collapsed both sides of a two-sided programme onto the invite side. **No backend or
plugin change this round.**

**Fix:** the visibility rule moved out of the widget onto `ReferralSummary` as
`showEntryPoint` / `canShowInvite` / `isClaimOnly` (same pattern as `AppNotice.isActiveTask`),
so it is one testable decision instead of an inline condition duplicated per screen. The card
shows when the customer can invite **or** can claim. An empty code counts as "cannot invite" —
otherwise the share sheet would send a message with no code in it.

**Two faces, not one card with a hole in it:** a claim-only visitor gets redeem-oriented copy
(`referralClaimHeadline` / `referralClaimSub`, bn+en) and a redeem icon, and on `ReferralScreen`
the hero speaks to them, the invite code + share button stay hidden, and **the "I have a code"
button moves to the top** — it used to sit below "how it works" and the terms, which is a second
way the same feature stayed unfound.

**Tests:** new `test/referral_visibility_test.dart` (7) pinning the two flags apart, incl. the
regression itself, programme-off, empty-code, and the JSON defaults (`can_claim` defaults false,
`can_invite` defaults true). Full suite **136 Flutter tests pass**, `flutter analyze` clean.

**To deploy:** rebuild the APK via `build-aun-app.cmd` (app 1.60.0+64). Nothing to upload.

## 2026-08-01 — app 1.45.0+49 / app-api 1.39.0: notification deep links everywhere, admin-bar repair queue, repair-pipeline gaps closed

**Every notification tap now lands on the specific thing it's about**
- Added the two missing routes: `parts` → that parts request (quote + Approve/Decline), `maintenance` → the projector the reminder is about (by serial).
- **Root fix, not just the two cases:** a system-tray tap only carries what the SERVER put in the FCM payload, while an in-app tap carries the notice's full stored data. When those disagree the same notification lands in two different places depending on where you tapped — which had already happened: maintenance pushes shipped without `serial`. Now (a) the maintenance push includes `serial`, and (b) the router falls back to looking the notice up by `notice_id` and routing on its stored data whenever the payload can't resolve a target. That makes the two tap paths identical by construction, and any future notice type whose push forgets a routing field still deep-links.
- The admin test reminder (serial `TEST`) deliberately does NOT deep-link — it matches no real device, so it falls back to the notification centre instead of stranding the tester on a blank screen.

**Admin panel**
- Browser tab titles: the AUN App pages used bare page titles, so "Dashboard" was indistinguishable from WordPress's own Dashboard tab. Page titles are now qualified ("AUN App Dashboard", "AUN App Settings", …) while the sidebar menu labels stay short.
- New admin-bar counter for **repair requests awaiting approval** (hammer icon), matching the existing bug-report counter. Cleared instantly when a request arrives or is decided rather than waiting out the 60s cache. (Bug reports already had one — this round added the repairs half.)

**Repair pipeline: two real gaps closed (see "how the repair flow works" below)**
- **Linking only ever happened when the customer opened the app.** `repair_erp_sync()` runs on app requests, so a customer who shipped their projector and then waited quietly was never linked to their job sheet, never polled, and never notified — the pipeline dead-ended for exactly the most patient customers. The cron now links pending requests itself (`AUN_App_Services::link_pending_repairs()`), reusing `repair_erp_sync()` so the matching rules stay in one place.
- **The arrival itself was never announced.** The status poll treats its first observation as a silent baseline (so it doesn't announce a status the customer has already seen), which meant the moment the job sheet was created — "we have received your projector", the single most reassuring message in the flow — produced no notification at all. New `AUN_App_Notices::repair_received()` fires on link, deduped per ref, and sets the poll baseline so the poll doesn't immediately repeat it.

### How the repair flow actually works (end to end)
1. Customer submits "Send for repair" in the app → row in `aun_app_repairs`, status `submitted`, SMS says *don't ship yet*.
2. Admin approves/rejects in **AUN App → Repairs** → status `approved`, in-app notice + push + SMS. **← now surfaced in the admin bar**
3. Customer ships. You receive it and create a job sheet in UltimatePOS exactly as always — no double entry.
4. The job sheet is auto-linked to the app request by phone + serial, guarded so an older/finished job sheet for the same device can't be adopted. Status → `received`. **← now also happens from cron, and now notifies**
5. `poll_repair_statuses()` cron watches the linked job sheet and pushes on every ERP status change (Under Repair → Ready → Delivered…).
6. A terminal ERP status auto-closes the app request.

So it was never a dead end — but steps 4–5 depended on the customer opening the app, and step 4 was silent, which is why it looked like one.

**Tests:** 17 new PHP bench tests + 7 new Flutter routing tests. Full suites: 32 parts + 22 maintenance + 25 v12 + 19 ticket-queue + 17 repair-linking PHP (115 total), 77 Flutter — zero regressions.

**To deploy:** re-upload `aun-app-api.zip` (1.39.0) and rebuild the APK via `build-aun-app.cmd` (app 1.45.0+49). No DB migration. Spare-parts plugin unchanged this round (still 0.22.0 — upload it if you haven't already from the previous round).

## 2026-08-01 — app 1.44.0+48 / app-api 1.38.0 / spare-parts 0.22.0: in-app quote approval + spare-parts notifications + "Request spare parts" rename

**Approve / decline a spare-parts quote inside the app**
- Previously the customer could only answer a quote by opening the SMS tracking link in a browser. Now the quote card in the parts request detail screen has **Approve** / **Decline** buttons with a confirm dialog.
- The decision logic moved into one shared method, `AUN_SP_Requests::customer_decision( $id, $decision, $source )`, which the public tracking page AND the app both call. Atomic claim (`UPDATE … WHERE overall_status = 'quote_sent'`), confirmation SMS, audit-log event and admin email all live there, so the two channels can't drift apart. The audit log records which channel answered.
- New endpoint `POST /parts/decision` `{ref, decision}`. **Authorisation matters here**: the web tracker identifies a request by ref alone (it's reached from an SMS link), but every app caller is a known account, so the endpoint confirms the request belongs to *their* phone. Without that, any logged-in user could approve someone else's quote by guessing a sequential `SP-` ref. Covered by a test.
- Answering a quote that was already answered (e.g. they tapped the SMS link first) returns **409 already_answered** and the app shows "This quote was already answered." rather than a generic failure.
- The quote card renders whenever a decision is pending **even if the total is ৳0** — an in-warranty quote still needs approving, and gating on the total would have hidden the buttons from exactly the customers who owe nothing.

**Spare parts finally send notifications (they never did before)**
- Audit finding: repairs, maintenance, tickets and content all pushed; **spare parts had zero notification integration** — SMS only. A quote could arrive and the app would never say a word.
- New `AUN_App_Notices::parts_status_changed()` → in-app notice + push, deduped per (ref, status). Notifies on `quote_sent`, `approved`, `waiting_customer`, `ready`, `closed`, `rejected`. Internal churn (`submitted`, `in_progress`) deliberately stays silent.
- Delivery is two-layer: the spare-parts plugin fires `do_action( 'aun_sp_status_changed', … )` on quote-send and on the customer's answer (real-time push for the statuses that can't wait), and `my_requests()` syncs any other status when the app next refreshes — bounded to requests touched in the last 14 days so deploying this doesn't notify people about months-old requests.
- New Android channel `aun_parts` ("Spare parts updates", IMPORTANCE_HIGH) so it can be muted independently; own icon/colour in the notification centre.

**"Buy spare parts" → "Request spare parts"**
- Warranty-valid customers aren't buying anything, so "Buy" was wrong. Renamed in EN + BN across the services tile, the onboarding slide copy, the tile subtitle ("free under warranty"), and a stale code comment. Matches the vocabulary the plugin already uses (`SP-` *requests*, "Track your spare-part request").

**Tests:** 32 new PHP bench tests (shared decision path, double-answer refusal, cross-account authorisation, REST 404/409, notice dedup, silent internal statuses, hook bridge, push channel) + 6 new Flutter unit tests. Full suites re-run: 22 maintenance + 25 v12 + 19 ticket-queue PHP, 70 Flutter — zero regressions.

**Known gap (pre-existing, not introduced here):** tapping a push notification opens the app but doesn't deep-link to the specific request. That's true for every notification type today, not just parts.

**To deploy this round:** re-upload **both** `aun-app-api.zip` (1.38.0) **and** `aun-spare-parts.zip` (0.22.0) — the shared decision method lives in the spare-parts plugin and the app-api calls it. Upload order doesn't matter: the endpoint checks `method_exists( 'AUN_SP_Requests', 'customer_decision' )` and returns a clean 503 rather than fataling if the spare-parts plugin is still the old one. Then rebuild the APK via `build-aun-app.cmd` (app 1.44.0+48). No DB migration this round.

## 2026-08-01 — app 1.43.0+47 / plugin 1.37.0 (DB v14): actionable maintenance reminders + welcome-animation dust fix + pending-mood confirmation dialogs

**Actionable maintenance (dust-filter) reminders — home task card + mark done / snooze**
- Backend: `aun_app_notice_state` gets two new columns, `completed_at` and `snoozed_until` (DB v14 migration, both fresh-install CREATE TABLE and existing-install ALTER TABLE paths covered).
- New REST endpoints: `POST /me/notifications/complete` and `POST /me/notifications/snooze` (`days`, clamped 1–14 server-side).
- `AUN_App_Notices::feed()` now returns `completed` and `snoozed_until` per item; a lapsed snooze automatically reports `snoozed_until: null` again (server-side, so the phone's clock is never trusted for this).
- App: `AppNotice.isActiveTask` = `type == 'maintenance' && !completed && snoozedUntil == null`. New `HomeMaintenanceCard` widget shows any active maintenance reminder directly on Home (above the status strip), with "Mark as done" / "Remind me later" (3 days / 1 week) actions — optimistic UI, re-syncs from server on failure.
- Notification-centre list now shows a "Done" / "Snoozed" pill next to completed/snoozed maintenance items.
- **New admin test tool** (WP Admin → AUN App settings → "Maintenance Reminder Test" card): enter any phone number that has logged into the app at least once and pick day 30/60/90 — sends a real reminder immediately (push + in-app), so the pipeline can be verified without waiting on the real 30-day ERP schedule. Test sends use a separate dedup namespace (`admintest:`) so they never collide with or get suppressed by the real cron path, and can be repeated freely.
- Tests: 22 new PHP bench tests (schema, feed defaults, complete/snooze endpoints, snooze clamping, lapsed-snooze-reactivates, invalid-id rejection, admin test-tool incl. dedup/repeatability) + 6 new Flutter unit tests (`isActiveTask` logic, JSON parsing incl. backward-compatible defaults for older responses) — all passing. Full existing suite (64 Flutter tests total) re-run with zero regressions.

**Welcome onboarding animation — dust-mote physics fix**
- Fixed a bug where dust motes appeared to move in a "trail, faster in one direction line by line": speed was accidentally locked to each mote's screen position (checkerboard pattern) instead of being independent. Motes now get independently-randomized speed/lane, explicitly balanced across buckets so small-N randomness can't accidentally clump one speed on one side. Regression test added (`test/welcome_dust_test.dart`).

**Pending-mood confirmation dialogs**
- Spare-parts and repair-request "request submitted" confirmation dialogs now use the existing `ProjectorSceneArt(mood: ProjectorMood.pending)` character animation instead of a static emoji icon, matching the request's status.

**To deploy this round:** re-upload `aun-app-api.zip` to WordPress (plugin 1.37.0 — DB v14 migration runs automatically on activation) **and** rebuild the APK via `build-aun-app.cmd` (app 1.43.0+47) — this round touches both sides, so both steps are required.

Also: **osTicket bridge** (deploys to support.smartliving.com.bd, osTicket v1.18.4) lives at
`<workdir>\osticket-bridge\aun-app-bridge\` (api.php + bridge-config.sample.php + .htaccess).

---

## 2. Build & test workflow (IMPORTANT)

- **The APK CANNOT be built inside a Claude session** — this environment blocks the loopback
  sockets Gradle's build workers need. The user **double-clicks `build-aun-app.cmd`** (runs in a
  clean process outside Claude). It `flutter clean`s, builds, copies the APK next to itself, and
  tees the full log to `aun-app-build.log`. If a build fails, read that log.
- What CAN be verified in-session: `flutter analyze`, `flutter test` (widget tests), and the Dart
  AOT release compile (`flutter assemble … android_aot_bundle_release_android-arm64`). These catch
  Dart/kernel errors but NOT native Gradle/AGP issues.
- **Backend is fully testable** on the bench via `wp eval-file` (uses `rest_do_request`, no HTTP server):
  ```
  cd /c/Users/Jobayer\ Hossain/wp-local
  php -c php.ini wp-cli.phar --path=site eval-file <scratchpad>/seed-slb.php
  php -c php.ini wp-cli.phar --path=site eval-file <scratchpad>/test-app-api-v2.php
  ```
  Deploy to bench: copy `aun-app-api/` → `wp-local\site\wp-content\plugins\aun-app-api\`, then
  `wp plugin deactivate aun-app-api; wp plugin activate aun-app-api` (re-runs migrations).

### Hard-won build gotchas (do not regress)
- `android/gradle.properties` → **`shrink=false`** is MANDATORY. Flutter force-enables R8 in release
  (`FlutterPlugin.kt` sets `isMinifyEnabled=true`); R8 strips mobile_scanner + ML Kit → camera won't
  start & barcode decode fails, RELEASE-ONLY. Keep rules also in `android/app/proguard-rules.pro`.
- **`PUB_CACHE=C:\dev\pub-cache`** — the Windows username has a space ("Jobayer Hossain"); the default
  pub cache path corrupts Dart compiles (`%20` in package URIs). Set in build script + user env.
- **Plugin compileSdk override** (v1.14): `file_picker 8.3.7` hardcodes `compileSdk 34` in its own
  build.gradle, but its transitive `flutter_plugin_android_lifecycle 2.0.35` (required `^2.0.22`)
  demands consumers compile against SDK 36 → `:file_picker:checkReleaseAarMetadata` fails. Fix in
  `android/build.gradle.kts`: a `subprojects { … }` block forces every Android module to
  `compileSdk 36` via reflection (`setCompileSdk`). ⚠️ It must GUARD `project.state.executed` — the
  Flutter template's earlier `subprojects { evaluationDependsOn(":app") }` eagerly evaluates `:app`,
  so calling `afterEvaluate` on it throws "Cannot run Project.afterEvaluate when already evaluated";
  configure executed projects directly, use afterEvaluate only for the rest.
- Toolchain uses **JDK 21** (JDK17's loopback pipes failed even in-session; JDK21 uses Unix-domain sockets).
- **SQLite bench quirks**: `SHOW COLUMNS ... LIKE 'x'` is IGNORED (returns all columns) → test column
  existence with `in_array('x', $wpdb->get_col("SHOW COLUMNS FROM $t"))`. `ALTER TABLE ADD COLUMN`
  must NOT use `AFTER`.
- Bench siteurl = `http://127.0.0.1:8080`; run via wp-cli's `server` (routes `/wp-json`). `AUN_APP_DEV_OTP`
  is defined `true` in bench wp-config only (returns OTP in the API response). **Never on live.**
- Flutter web preview hangs in the in-app Browser pane — don't use it; rely on widget tests + real phone.

---

## 3. Architecture

**Backend WordPress plugin `aun-app-api`** (REST namespace `aun-app/v1`), files:
- `aun-app-api.php` — bootstrap, options, tables + activation/migration (DB v4). Tables: `aun_app_tokens`,
  `aun_app_content`, `aun_app_devices` (direct-purchase links), `aun_app_repairs`.
- `includes/class-aun-app-phone.php` — BD phone normalisation (canonical `8801XXXXXXXXX`) + user matching.
- `includes/class-aun-app-sms.php` — Alpha SMS (sms.net.bd), reuses the Alpha SMS plugin's key.
- `includes/class-aun-app-otp.php` — OTP request/verify (phone-keyed transients, HMAC, rate limits).
- `includes/class-aun-app-tokens.php` — bearer tokens (hashed, `aun_app_tokens`, 180-day).
- `includes/class-aun-app-erp.php` — live ERP calls, reuses SLB ERP Sync settings (`slb_sync_settings`:
  erp_base_url, erp_secret, dealer_group_id). Endpoints: `/api/app-lookup/serial`, `/api/app-lookup/phone`.
- `includes/class-aun-app-profile.php` — app profile in user meta (`aun_app_name/email/avatar`), seeded
  from ERP by phone. Never touches the WP account (a phone can match a shared/admin account).
- `includes/class-aun-app-warranty.php` — the "brain": resolve serial → direct_sale / dealer_stock /
  registered_own / registered_other / unknown; device lists; STRICT model matching by ERP product id.
- `includes/class-aun-app-content.php` — firmware/manual/video/tip content per model.
- `includes/class-aun-app-services.php` — spare-parts (writes into aun-spare-parts plugin tables) + repairs.
- `includes/class-aun-app-rest.php` — all REST routes + envelope `{success,data}` / `{success,error:{code,message}}`.
- `admin/class-aun-app-admin.php` — WP admin: **AUN App** menu → Dashboard, App Content (rich `wp_editor`
  descriptions), Repairs, Settings.

**ERP (UltimatePOS)** — two read-only Laravel controllers in `routes/api.php`, both auth via
`X-Warranty-Secret` (env `WARRANTY_API_SECRET`):
- `WarrantyApiController` `/api/warranty-serials` — existing hourly dealer-serial sync source.
- `AppLookupController` `/api/app-lookup/serial` + `/api/app-lookup/phone` — app lookups. **Returns
  `product_id` (ERP product id)** — the app maps it EXACTLY to a WP model via `slb_products.erp_product_id`.

**Model matching (STRICT — safety critical, do not make fuzzy):** content is keyed to `slb_products.id`.
A device → product id via: registration serial → `slb_serials.product_id` (already resolved by the hourly
sync); direct link → stored `erp_product_id` → `slb_products WHERE erp_product_id`. Only fallback is EXACT
(case-insensitive) name match. `A005` and `A005 Pro` never share firmware. Unmatched → general (model 0)
content only, never another model's.

**Flutter app** `C:\dev\aun-app\lib\`: `main.dart` (root screen switch), `src/app_state.dart`
(InheritedNotifier session state), `src/api/{api_client,models}.dart`, `src/ui/{theme,widgets}.dart`,
`src/screens/*` (splash, onboarding, login, home shell w/ 4 tabs, add_device, register_device, scanner,
warranty_check, device_detail, content_screens [video/pdf/firmware], services_screens, settings/support/
devices tabs, force_update). Bilingual ARB in `lib/l10n/`. Brand `#0188FE`. Logo `assets/icon/aun-logo.png`.

App deps: http, flutter_secure_storage, shared_preferences, url_launcher, image_picker,
package_info_plus, mobile_scanner, permission_handler, flutter_widget_from_html_core,
youtube_player_iframe, flutter_pdfview, path_provider, intl.

---

## 4. Features complete & tested (Phase 1 + content system)

- **Login**: phone + SMS OTP (passwordless, Alpha SMS). New numbers auto-create a customer account.
  Profile (name/email/avatar) in app meta, seeded from ERP by phone; asks name only if ERP has none.
  Avatar = uploaded photo / Gravatar-from-email / emoji / initials.
- **Onboarding**: 4 slides, first launch only.
- **Add a device (3 ways)**: scan barcode (camera + scan-from-gallery), type the 12-digit box barcode,
  or find by purchase phone. "Where is my barcode?" illustrated help (box sticker vs projector SN).
- **Smart resolve**: direct AUN sale → warranty from ERP sale date, auto-linked, NO registration/SMS/email;
  Dealers-group serial → registration form (invoice number + invoice photo MANDATORY, mirrors the warranty
  plugin's approve/mismatch/not_found logic, notifications delegated to `slb_send_templated_sms/email`);
  already registered → shows warranty; website-registered dealer devices appear automatically by phone.
- **My Devices**: warranty progress bars, per-model content, remove (frees serial), serial uniqueness
  (no one else can claim a serial you hold), "direct purchase" badge.
- **Per-model content**: firmware (formatted HTML install guide + Download button → Android system
  DownloadManager for 500MB–3GB zips), manuals (in-app PDF viewer, downloads to temp), videos (in-app
  YouTube player w/ controls + fullscreen), tips (formatted HTML). Home "Video guides" shows only the
  customer's own models' videos (+ general). Descriptions are rich HTML (admin `wp_editor`).
- **After-sales**: Buy spare parts (native, writes into aun-spare-parts plugin → SP- refs, bilingual
  catalog + reference photos with tap-to-fullscreen zoom, locked to an owned device); Send-in repair
  (RP- refs, `aun_app_repairs`, admin **AUN App → Repairs** with SMS status updates); My service requests.
- **Support**: WhatsApp chat, tap-to-call, website, Facebook, FAQ tips.
- **Settings**: profile + avatar, Bangla/English toggle, check-for-update (APK version gate), logout.
- **Self-update**: `/config` exposes latest/min version code + apk_url; app prompts / force-updates.

Test coverage: backend **42 + 44 = 86** bench tests pass; app `flutter analyze` clean + 3 widget tests +
AOT release compile OK. Camera **confirmed working on the user's phone**.

---

## 5. Go-live checklist (what the user does)

1. **Plugin**: upload `aun-app-api.zip` to the live site → Plugins → Add New → Upload → Activate/replace.
2. **ERP**: deploy `AppLookupController.php` (+ existing `WarrantyApiController.php`) to
   `app/Http/Controllers/`, add their routes to `routes/api.php`, ensure `WARRANTY_API_SECRET` in `.env`.
   Re-deploy AppLookupController whenever it changes (it now returns `product_id`).
3. **SLB Warranty → Products**: every model must have its **ERP Product ID** set (this is the glue for
   exact content matching). Adding a new projector = add it here (name + ERP Product ID); the app picks it
   up live, no rebuild.
4. **AUN App admin**: Settings (WhatsApp number, support phone, banners, discount note, apk_url, version
   codes) + App Content (firmware/manuals/videos/tips per model, rich descriptions).
5. **APK**: double-click `build-aun-app.cmd` → install `AUN-Projector-app.apk`.
6. **Never** define `AUN_APP_DEV_OTP` on live (bench only).
7. **Cleanup**: an early v1.1 bug auto-registered test serial **523986765361** on live — delete that one
   row in SLB Warranty → Registrations so its warranty recalculates from the real ERP date.

---

## 6. Open items / next steps

- **User-noted bugs**: after camera was fixed the user said "there are some bugs, I'll fix them later"
  (unspecified) — ask what they are.
- **Release signing**: the Gradle side is DONE (v1.42) — `android/app/build.gradle.kts` reads
  `android/key.properties` and signs release with the real upload key, falling back to the debug key
  when that file is absent. The **user must create the keystore themselves** (never generate or hold
  their signing material): step-by-step in `<workdir>\aun-app-keystore-guide.md`. ⚠️ The first
  release-signed APK will NOT install over a debug-signed copy — every existing phone must uninstall
  once. Google Play **organization** account under "Smart Living Bangladesh" (D-U-N-S applied for,
  pending). Company/author name everywhere = "Smart Living Bangladesh" / brand "AUN".
- **Play Store pages LIVE** (2026-07-30): `https://aun-projector.com.bd/app-privacy-policy/` and
  `https://aun-projector.com.bd/delete-account/` — both verified public, no login wall. Sources +
  the Data-safety answer sheet: `aun-app-privacy-policy.txt`, `aun-app-delete-account.txt`,
  `aun-app-play-store-checklist.md`. The website's existing `/privacy-policy/` stays as-is (it's
  eCommerce-only and does NOT cover the app).
- **Phase 2 (planned, not started)**: in-app shopping — product catalog, 3-screen easy checkout,
  **SSLCommerz** payment (merchant account exists), app-only discount enforced server-side, order tracking.
- **Phase 3**: FCM push notifications, loyalty, deeper spare-parts integration.
- **Full-build verification**: since Claude can't run the native Gradle build, any newly added native
  plugin's first real test is the user's `build-aun-app.cmd`. New plugins are checked for AGP-9 `namespace`
  before shipping.

---

## 7. Version history

- **v1.0** — Phase 1 MVP: OTP login, My Devices/warranty, register, manuals/firmware lists, FAQ, WhatsApp.
- **v1.1** — ERP "brain" (direct vs dealer), by-phone lookup, native spare parts + repair, admin pages.
- **v1.2** — Branding (AUN logo, renamed "AUN Projector Bangladesh"), onboarding, profile-in-meta (fixed
  "AUN/info@" bug), removed direct-sale auto-registration + broken email (delegate to warranty plugin),
  device remove + serial security, parts locked to owned device + ref images, profile avatar.
- **v1.3 / 1.3.1** — Camera: runtime permission (permission_handler) then the real fix `shrink=false`
  (R8 was stripping the scanner). Registration parity (invoice mandatory), numeric barcode field, barcode
  help illustration, add-device UX fixes, parts full-screen zoom.
- **v1.4.0** — Content system: in-app video (YouTube), in-app PDF manuals, firmware screen (HTML guide +
  large-file system download), rich HTML descriptions (admin `wp_editor`), home videos filtered to the
  customer's models. Then **strict ERP-product-id model matching** (A005 vs A005 Pro never share content).
- **v1.5.0** — (this round, user-reported fixes + upgrades)
  - **Home**: premium time-of-day greeting (Bangla/English, projector-flavoured sublines, 5 time slots);
    avatar is tappable → profile bottom sheet (shared `ProfileEditor` widget, also used by Settings);
    "Check warranty" quick action REMOVED (screen deleted; `/warranty/check` API left intact).
  - **Login video**: admin uploads MP4 → AUN App Settings → "Login screen video" (option
    `login_video_url`, served in /config as `login_video`). App downloads ONCE to app-support storage
    (FNV-1a-named cache, old files auto-purged), plays muted/looped full-bleed behind the login form with
    dark overlay; form sits in a solid card. Gradient fallback while downloading/offline. `video_player` dep
    added — **first native Gradle build of it happens in the user's build-aun-app.cmd**.
  - **YouTube fix (error 152-4)**: real `origin` (aun-projector.com.bd) + Chrome-mobile `userAgent`
    (stock WebView UA carries "; wv" which YouTube's bot check rejects). Error listener flips to a
    "Watch on YouTube" fallback; app-bar button always offers YouTube. No API key needed. User must keep
    "Allow embedding" ON in YouTube Studio (their own channel — confirmed).
  - **Repairs ↔ UltimatePOS**: app request = pickup request only. When the service centre creates the job
    sheet in the ERP Repair module (normal flow, ERP sends its SMS), the backend auto-links it by
    phone+serial (guard: job created ≥ request date − 3 days), bumps app status to `received`, stores
    `job_sheet_no` (DB v5 column). From then on the app shows the LIVE ERP status/activities/cost via
    `/api/repair-status` (same endpoint + `SLB_ERP_API_KEY` the website tracker uses; override key in
    AUN App Settings → Repair Tracking). Walk-in job sheets (no app request) appear automatically.
    New endpoint `GET /me/repair-detail?ref=RP-…|2025/0001`. Admin Repairs page shows JS badge + flow help.
  - **Service request details**: parts + repair cards now tappable → full detail screens
    (`request_detail_screens.dart`): parts items/quote/tracking; repair ERP timeline or app-status stepper.
  - **Scanner**: 1D-only formats, a code must be seen on 3 frames ("hold steady" progress, green frame,
    haptic), implausible values (wrong length/garbage) never count; gallery decode capped at 2400px,
    prefers 12-digit numeric, and asks the user to confirm the number before use.
  - Tests: backend 44 + 42 pass (run each suite right after a fresh `seed-slb.php` — they contaminate each
    other's state otherwise) + NEW `test-app-api-v3.php` (repair integration, mocks `/api/repair-status`;
    note: flush the 2-min ERP cache with `delete_transient`, not SQL — one-process object cache).
- **v1.6.0** — (2026-07-16 second feedback round)
  - **Home-video → website redirect FIXED**: the v1.5 `origin` override became the player WebView's
    baseUrl, so taps inside a failed player resolved links against aun-projector.com.bd and launched the
    browser. Reverted to the package default; the Chrome-mobile `userAgent` (the actual 152-4 fix) stays.
    `youtube_id()` extraction broadened server-side (v= anywhere, /live/, /v/, nocookie, si params) with a
    mirrored client-side fallback (`youtubeIdFromUrl` in models.dart); direct .mp4/.m3u8 URLs now play in a
    NEW in-app Mp4PlayerScreen (video_player, custom controls). Both players share the redesigned
    `VideoDetailShell` (dark immersive top, rounded player, model/date chips, HTML description sheet).
  - **ProjectorIcon** (CustomPaint: body+lens+beam, cut-outs via one saveLayer + BlendMode.clear) replaces
    the videocam icon in device cards, nav bar, home quick action, barcode help. `QuickAction` accepts
    `iconWidget`. **Device cards redesigned**: brand-gradient tile, serial pill, purchase-date row.
  - **In-app firmware downloads**: `background_downloader ^9` (namespace OK). Progress card (%, cancel) +
    system notification (POST_NOTIFICATIONS added to manifest + runtime request), retries, follows the
    WP File Download → OneDrive redirect, reads filename from Content-Disposition
    (`withSuggestedFilename`), lands in shared Downloads (`moveToSharedStorage`), "Open file" on done,
    browser fallback on failure.
  - **Gallery barcode scan fixed**: full-res pick, then `image ^4` variants in a `compute` isolate
    (bakeOrientation, ≤2000px, rot90/rot270) through `analyzeImage`, final all-formats attempt; user
    confirms the found number. Temp variants deleted after.
  - **Parts status history**: `/me/service-requests` spare entries now carry `status_label`/`items[].status_label`
    (labels from `AUN_SP_Requests::overall_statuses()/item_statuses()`) + `timeline` (aun_sp_events,
    excluding sms/contact_changed — identical to the website tracker). App shows a timeline in
    PartsDetailScreen (shared `EventTimeline` widget).
  - **Repairs admin rebuilt**: page auto-links rows to UltimatePOS job sheets on load (same 2-min-cached
    `repair_erp_sync`), shows the LIVE ERP status read-only ("update it in UltimatePOS"); the hardcoded
    status dropdown is GONE — only Approve (SMS "send it in") / Reject / Save note remain. RP- ref is just
    the pre-job-sheet pickup reference used in those SMS.
  - Tests: NEW `test-app-api-v4.php` (19: youtube_id shapes + parts timeline/labels); full pass =
    44 + 42 + 22 + 19. App: analyze clean, 3 widget tests, AOT release compile OK.
  - ⚠️ First real Gradle build of `background_downloader` + `video_player` happens in the user's
    `build-aun-app.cmd`.
- **v1.7.0** — (2026-07-16 third feedback round)
  - **OTP auto-fill**: app fetches its SMS-Retriever signature hash (`smart_auth ^3`, changes with the
    signing key so never hardcode) and sends it as `app_hash` with request-otp; server appends it on its
    own line to the APP OTP SMS only (`AUN_App_OTP::append_app_hash`, website SMS untouched). Listener is
    armed BEFORE the SMS is sent; code auto-fills + auto-verifies ("Code detected from your SMS ✓").
  - **YouTube 152-4 — real fix**: the Chrome-UA trick was NOT enough; YouTube blocks anonymous WebView
    embeds outright. New public endpoint `GET /embed?v=ID` serves a real HTML page from
    aun-projector.com.bd with the iframe (`AUN_App_REST::embed_html`, callback echoes + exits); the app
    plays it in plain `webview_flutter` (`youtube_player_iframe` REMOVED). Navigation delegate keeps only
    our embed URL in the WebView; everything else opens externally. Prominent red "Watch on YouTube"
    button in the sheet. ⚠️ videos must still have "Allow embedding" ON in YouTube Studio.
  - **Downloads v2**: new `AppDownloads` singleton (`lib/src/services/downloads.dart`) — group
    `aun-files`, registerCallbacks + trackTasksInGroup + resumeFromBackground at app boot (fire-and-forget:
    awaiting it hangs widget tests). FirmwareScreen: Pause/Resume/Cancel, reattaches to running/paused
    tasks after leaving the screen OR restarting the app (database.allRecords by metaData
    `aun-content-{id}`), remembers finished file path in prefs (`aun_dl_path_{id}`) → shows "Open file"
    while the file exists. Multi-GB files OK (native engine; moveToSharedStorage shows a "moving" state).
  - **Repairs serial matching**: `RepairStatusApiController.php` now supports `search_by=serial`
    (⚠️ **must be redeployed to the ERP**). Sync evaluates phone+serial AND serial-only candidates, each
    against the ±3-day date guard (lesson: a fallback gated on "miss" never fires when the first lookup
    returns an old job). Links even when the job sheet was created under a different POS contact. App
    pre-jobsheet stepper trimmed to submitted/approved + "live tracking starts at the service centre" —
    no more hardcoded repair stages anywhere.
  - **Projector icon redrawn**: side view — lens barrel protrudes from the body's right edge, beam starts
    at the lens rim.
  - Tests: backend 44 + 42 + 24 + 25 = 135 (v3 gained serial-fallback tests; v4 gained embed + app-hash);
    analyze clean, 3 widget tests, AOT release compile OK. ⚠️ First Gradle build of `smart_auth` +
    `webview_flutter` (and removal of youtube_player_iframe) happens in the user's build-aun-app.cmd.
- **v1.8.0** — (2026-07-16 fourth feedback round)
  - **Repair lifecycle closed properly** (user found the repeat-repair loophole: every request re-adopted
    the customer's latest — already delivered — job sheet). The ERP Repair module flags terminal statuses
    in `repair_statuses.is_completed_status` (user marks "Delivered / Collected" + "Repair deferred –
    Awaiting Parts"); `RepairStatusApiController` now returns it as `status_completed`
    (⚠️ **redeploy to ERP**) — NOTHING hardcoded (module source in `ERP/Modules/Repair`). Sync rules:
    a completed job sheet older than the request is never adopted; linked rows auto-close (`maybe_close`)
    when their job completes; new REPAIR_FINAL const; list views pass `$fetch_final=false` so finished
    rows don't hit the ERP each load (admin page + my-requests), detail still fetches the final timeline.
  - **Walk-ins cross-checked**: discovered by account phone AND by each registered device's serial
    (covers job sheets under a different POS contact); completed walk-ins are past repairs → hidden.
  - **Notifications fixed**: tasks carry `displayName` = content title; group notifications use
    `{displayName}` + `{progress}` — never `{filename}` (wpfd/OneDrive URLs yield token gibberish).
    `_safeFilename()` also replaces garbage suggested filenames with a title slug + proper extension.
  - **Icon**: reverted to a clean FRONT view (no beam — the side view read as a submarine). EmptyState +
    onboarding `_IconArt` gained widget slots; replaced remaining videocam icons (no-devices screen,
    onboarding slide 3, by-phone purchase list, register model dropdown).
  - **OTP send hardened**: 3s timeout on getAppSignature, retriever listener removed before re-arming.
    (User's two transient send errors were likely resend-cooldown taps; not reproducible.)
  - **Home redesigned**: greeting kept; NEW "My projectors" horizontal carousel (gradient tile, serial,
    warranty-days chip, "+ add" card) or a gradient "Add your projector" hero when empty; quick actions
    now Register / My requests / WhatsApp; video guides are cinematic 16:9 cards (thumbnail, gradient,
    model chip, white play badge).
  - Tests: backend 44 + 42 + 30 + 25 = 141 (v3 gained the completed-lifecycle phase); analyze clean,
    3 widget tests, AOT OK. Only Dart-side changes to existing plugins — no new natives this round.
- **v1.9.0** — (2026-07-17, DB v6)
  - **In-app notification centre**: `aun_app_notices` (+`aun_app_notice_state` read/dismiss per user;
    bilingual title/body columns; nullable `dedup_key` UNIQUE). Feed = personal notices + broadcasts
    targeted at the user's models. New class `class-aun-app-notices.php`; REST `GET /me/notifications`,
    `POST …/read` (null ids = all → counter reset), `POST …/dismiss`. Triggers: NEW active content row →
    model-targeted broadcast (deduped `content:{id}`); admin repair approve/reject → personal notice
    (deduped per decision). App: avatar moved LEFT on Home, bell RIGHT with live Badge counter;
    `notifications_screen.dart` — swipe-to-dismiss, auto mark-all-read on open, fresh items highlighted.
  - **Maintenance (dust-filter) reminders** mirror `ERP/aun maintenance sms/MaintenanceSmsService.php`
    EXACTLY: eligibility `products.product_custom_field1 = 'MAINT_SMS'` (returned as `maintenance` by
    AppLookupController), reminders +30/60/90 days after purchase, same 3 SMS texts (+bn). Materialised
    ON DEMAND per user+serial+offset with `created_at` = original due date → **late installers see past
    months' reminders** (bench-tested). 6-h per-user throttle transient `aun_app_maint_chk_{uid}`.
  - **Warranty from the ERP + midnight day boundary**: AppLookupController now also returns
    `warranty_duration`/`warranty_unit` (products.warranty_id → warranties table — what the invoice
    prints). Stored on direct links (v6 cols), resolved via the cached serial lookup for registrations;
    12-month fallback only when the ERP is silent. `days_left` is whole-day midnight math in the site
    timezone (was flipping at time-of-purchase). ⚠️ **AppLookupController must be redeployed to the ERP.**
  - **Repair attachment photos**: ERP `documents` passed through `repair_get` → thumbnails in the repair
    detail screen → tap = fullscreen pinch-zoom (existing showImageViewer).
  - **Bug reports**: `aun_app_feedback` table, `POST /feedback`, Settings → "Report a problem" sheet,
    admin **AUN App → Bug Reports** (new/in-progress/resolved + delete).
  - **PUSH (FCM) NOT yet wired** — needs the user's own Firebase project (google-services.json +
    service-account key). The in-app centre + backfill work fully without it; counter refreshes on app
    open/Home refresh. Firebase steps are the user's homework for the next round.
  - Tests: backend 44+42+30+25+22 = 163 (NEW `test-app-api-v5.php`); analyze clean, 3 widget tests,
    AOT OK. No new native plugins. ⚠️ Suites cache-contaminate via the 10-min `aun_app_erp_s_*` serial
    transients — every suite now flushes them in its clean-slate block.
- **v1.10.0** — (2026-07-17, DB v7) — returns/deletions + repair-photo polish
  - **Device links now expire when the ERP says so.** Root cause found by the user: a UltimatePOS sell
    return does NOT delete or unfinalise the sale — `TransactionUtil::addSellReturn()` writes
    `transaction_sell_lines.quantity_returned` on the ORIGINAL line — so the app (which filtered only
    `type='sell' AND status='final'`) could never see it. AppLookupController now selects
    `tsl.quantity`/`tsl.quantity_returned` and reports `returned` = FULL line return only (a partial
    return can't say WHICH serial came back, and a false positive would strip a real warranty).
    `byPhone` omits returned units; `bySerial` still returns them with `returned:true` so the plugin can
    tell "given back" from "sale deleted". ⚠️ **AppLookupController must be redeployed to the ERP.**
  - **Hourly sweep** `aun_app_verify_devices` (registered on activation, unscheduled on deactivate) →
    `AUN_App_Warranty::verify_devices_batch()`: 40 devices/run, each re-checked ~daily
    (`verified_at`, `verify_misses` = DB v7 cols; fresh links start verified). Outcomes:
    `returned` → unlink at once; `missing` (found:false) → **2 strikes ≥24h apart** then unlink;
    ERP error/outage → **never touch anything** (breaks the loop so a down ERP isn't hammered).
    Unlinking frees the serial for resale and deletes that serial's dust-filter reminders
    (`AUN_App_Notices::delete_for_serial`, also called from the customer's own device removal).
    `resolve_serial`/`purchases_by_phone` treat a returned sale as no sale (falls through to manual
    review). **Dealer registrations are deliberately never swept** — they're the customer's own proof of
    purchase from a dealer, human-approved in the warranty plugin.
  - **Repair photos**: new public `GET /repair-image?file=` proxy (basename guard + extension allowlist +
    content-type allowlist + 24h cache), mirroring the website tracker's `slb/v1/repair-image`. The ERP
    host is never exposed to the phone and non-images (PDFs in the same `media` table) are dropped in
    `repair_get` so no broken tiles. NOTE: `media_filename()` uses `?:` (parse_url returns FALSE, not
    null) and `media_proxy_url()` uses `add_query_arg` (rest_url already carries `?rest_route=` when
    permalinks are plain). Photos are still ORIGINALS — no server-side thumbnails yet (data-usage TODO).
  - **App gallery**: `showImageGallery(urls, initialIndex, heroTag)` — PageView + per-page
    InteractiveViewer pinch-zoom + "2 / 5" counter; page swipe is disabled while zoomed
    (TransformationController listener). `showImageViewer` now delegates to it, so parts photos are
    unchanged. Repair detail opens the whole set at the tapped photo.
  - Tests: NEW `test-app-api-v6.php` (27: outage safety, return→unlink→reminders gone→serial freed→
    resold to a new owner, delete 2-strike, strike reset, dealer regs untouched, proxy/type-filter/
    traversal). Full pass = 44+42+30+25+22+27 = **190**. analyze clean, 3 widget tests, AOT OK.
  - ⚠️ TEST GOTCHA (cost an hour): `wp eval-file` runs the file inside a function scope, so file-level
    `$var`s are NOT globals — helper functions must derive table names themselves (`global $t_dev` was
    silently NULL, making an UPDATE a no-op and the sweep look broken).
- **v1.11.0** — (2026-07-17 late, DB v8) — FCM push, greeting brain, in-app Help Center
  - **FCM push (HTTP v1, no SDK)**: new `class-aun-app-push.php` — service-account JSON pasted into
    AUN App → Settings (options table, never a web file), RS256 JWT signed with openssl → OAuth token
    (cached 50 min) → per-token FCM send. Device tokens live ON aun_app_tokens rows
    (v8 cols `fcm_token`/`fcm_lang`) → logout/expiry stops push; one FCM token belongs to exactly ONE
    session (`POST /me/fcm-token` steals it from older rows); UNREGISTERED responses prune. Triggers:
    new content → owners of that model (`AUN_App_Warranty::user_ids_for_model`; model 0 = all live
    sessions); repair approve/reject → that customer; NEW daily cron `aun_app_daily_notices` →
    `daily_maintenance_run()` materialises due dust-filter reminders WITH push (in-app materialisation
    stays silent — customer is already looking). Bilingual per token lang. `Notices::$last_was_new`
    distinguishes insert vs dedup so nothing ever pushes twice.
  - **App FCM**: firebase_core 4.12.1 + firebase_messaging 16.4.3, google-services Gradle plugin 4.4.4
    (settings.gradle.kts + app plugins block), `AppPush` service (init in main() best-effort; register on
    login + returning session; onTokenRefresh; foregroundPings ValueNotifier → Home refreshes the bell
    live when a push arrives while open). ⚠️ **BLOCKER for the user's build**: their google-services.json
    is registered for package `com.aunprojector.app` but the app id is `bd.com.aunprojector.aun_app` —
    they must add that Android app in Firebase console and replace android/app/google-services.json,
    else Gradle fails with "No matching client found".
  - **Greeting brain**: `class-aun-app-greeting.php` in /config → `greeting` {weather{condition,temp_c},
    holiday, is_weekend}. Weather = Open-Meteo Dhaka (keyless, 30-min cache, WMO code simplified to
    clear/cloudy/fog/rain/storm); holidays = Nager.Date BD (24-h cache); weekend = Fri/Sat. App engine
    priority: holiday > storm > rain > ≥36°C heat > weekend > time-of-day, 2 sub-variants per bucket
    rotated by day-of-year. Deliberately NO device GPS (no new permission; Dhaka ≈ national mood).
  - **Help Center in the app** (decision on the user's "what goes in Tips?" question): the empty Tips tab
    is now a model-aware **Help tab** powered by the website's aun-help-center plugin via
    `class-aun-app-help.php` + `GET /help-center?model_id=` — same bilingual FAQs (q/a bn+en + Banglish
    search keywords), certified-Android-TV filtering, and each FAQ's model-specific tutorial video plays
    in the app's own player. Models matched by SLB name ↔ help-center short label (exact,
    case-insensitive). Admin "tip" content items still show on top. Knowledge-base articles stay on the
    website (SEO content, not per-model). aun-help-center is now installed on the BENCH too.
  - Tests: NEW `test-app-api-v7.php` (21) — full push pipeline with a real signed JWT (⚠️ bench gotcha:
    `openssl_pkey_new` fails on Windows PHP without openssl.cnf → test key pre-generated by the openssl
    CLI into scratchpad/test-fcm-key.pem; product code only READS keys, unaffected). Full pass =
    44+42+30+25+22+27+21 = **211**. analyze clean, 3 widget tests, AOT OK.
  - ⚠️ First Gradle build of firebase_core/firebase_messaging + google-services plugin happens in the
    user's build-aun-app.cmd — AFTER they fix the Firebase package registration.
- **v1.12.0** — (2026-07-19, DB stays v8) — Help-tab fixes, clickable push, sweep visibility
  - Context: user fixed the Firebase registration; **FCM push confirmed working on their phone**
    (test firmware push received). This round: their 4 feedback items.
  - **Help tab model matching FIXED** (answers didn't match the registered device): two real bugs.
    (1) `AUN_App_Help::match_model()` compared the full SLB product name against the help-center's
    SHORT label — now both the raw name AND its short-label derivation are tried (via the
    help-center's own `aun_hc_short_label()` when present, mirrored fallback otherwise; exact,
    case-insensitive, never fuzzy — nothing hardcoded, new models keep working).
    (2) The bundle never served the certified answer variants — certified models now get
    `a_bn_cert`/`a_en_cert` (mirror of the website's client-side swap) and hide uncertified-only
    rows correctly. Also per user request the app payload is now TEXT-ONLY: no `video_id`
    (dedicated Videos tab) and the KB-020 manual row is skipped (dedicated Manuals tab); cache key
    bumped to `aun_app_help2_*`. App: FAQ cards show per-category coloured icon tiles
    (setup/apps/casting/hardware — same visual language as the notification centre) instead of the
    one generic question icon.
  - **Clickable push notifications**: content pushes now carry `content_id`+`model_id`; new public
    `GET /content/{id}` (`AUN_App_Content::get_public`, active rows only). App: `AppPush` captures
    taps (`onMessageOpenedApp` + `getInitialMessage` → `tapped` ValueNotifier), HomeScreen consumes
    them, and `lib/src/services/notification_router.dart` routes: firmware/manual/video/tip →
    the content itself (fetched fresh by id); repair (ref) → RepairDetailScreen (stub entry, loads
    live by ref); maintenance/unknown/failed fetch → notification centre (full message there) — a
    tap never dead-ends. Notification-centre rows with a real target are now tappable too (chevron).
  - **Sweep visibility** (user deleted an ERP sale, device still visible 30 h later — logic was
    CORRECT, just invisible: deleted sale = 2 strikes ≥24 h apart at ~daily checks → unlink lands
    between ~24–48 h; returns unlink at the first check ≤24 h). Dashboard now has a "Device
    Verification Sweep" card: last-run stats (stored in option `aun_app_last_sweep` by the cron),
    next scheduled run (warns if unscheduled), pending-strike serials table, timeline explanation,
    and a **"Run verification sweep now"** button.
  - **Location question answered**: Android has NO permissionless location API (coarse still needs
    the runtime permission; other apps' location can't be read). Options if ever wanted: IP-based
    server-side geolocation (unreliable on BD mobile CGNAT — most IPs resolve to Dhaka anyway).
    Decision: keep the Dhaka-wide weather, no new permission.
  - Tests: v7 suite extended to 36 (label matching from full titles, cert variants, KB-020/video_id
    trims, push data payloads, /content/{id} 200/404). Full pass = 44+42+30+25+22+27+36 = **226**.
    analyze clean, 3 widget tests, AOT OK. No new native plugins (pure Dart changes) — the user's
    build-aun-app.cmd should build exactly as v1.11 did.
  - **Stale-tab fix** (user confirmed the sweep unlinked their deleted device ~after midnight, but
    the My Devices tab still showed it until pull-to-refresh — kept-alive IndexedStack tabs only
    loaded in initState): HomeScreen shell now owns `_refreshSignal` (ValueNotifier), bumped on
    every tab (re)select AND on app lifecycle resume (WidgetsBindingObserver); HomeTab + DevicesTab
    take a `refreshSignal` ValueListenable and silently re-fetch on it. No flicker (UI keeps old
    data until the new list arrives).
- **v1.13.0** — (2026-07-19, DB v9 = cron re-schedule only) — osTicket support tickets in the app
  - User's osTicket: **v1.18.4** at support.smartliving.com.bd (cPanel access confirmed). Help
    topics: Feedback / General Inquiry / Report a Problem (after-sales; custom form: Order Number
    `ordernumber` required, Purchase Channel `purchasechannel` choices required).
  - **Bridge** `<workdir>\osticket-bridge\aun-app-bridge\` → upload folder into the osTicket ROOT
    (next to main.inc.php), copy bridge-config.sample.php → bridge-config.php with a 40+ char
    secret. api.php: `X-AUN-Bridge-Secret` gate (hash_equals, checked BEFORE osTicket boots),
    then boots osTicket (DISABLE_SESSION like api.inc.php). Actions: ping / topics (active public
    topics + their custom-field metadata — nothing hardcoded) / create (Ticket::create with
    `data:text/html` message → staff alerts + numbering + SLA all normal; autorespond SUPPRESSED
    for synthetic emails so nothing bounces) / tickets / thread (SQL reads; only types M+R —
    internal notes never leave) / reply (postMessage → staff alert, reopens reopenable tickets) /
    updated?since= (staff replies for the WP poll). Ownership: ticket's user must hold one of the
    caller's emails; foreign tickets 404 (never revealed).
  - **Identity (the "no order number" answer)**: osTicket keys customers by EMAIL, app by PHONE.
    WP sends BOTH identities: profile email when set (old website tickets appear in the app
    automatically) else synthetic `{8801…}@app.{host}`. The agent never guesses who it is: WP
    appends a "Customer & device" HTML table (OTP-verified phone, model+serial, purchase date,
    invoice, warranty days, app version) and fills custom form fields via the admin-editable
    mapping (Settings → "Custom form values", default `ordernumber = {invoice|serial|phone}` +
    `purchasechannel = AUN Projector App`; {a|b} = first non-empty; empty lines skipped; unknown
    variables ignored by osTicket). Customer just picks WHICH projector (optional dropdown).
  - **WP plugin v1.13.0**: class-aun-app-tickets.php (bridge client, topics 15-min cache,
    resolve_field_map, emails_for_user, users_for_ticket_email reverse map). REST: GET/POST
    /me/tickets, GET /me/tickets/thread, POST /me/tickets/reply, GET /tickets/topics; /config
    gains `tickets_enabled`. Settings card "Support Tickets (osTicket)": base URL, secret (live
    ping status), field-map textarea. NEW cron `aun_app_tickets_poll` (custom 10-min schedule):
    bridge `updated` → personal notice type 'ticket' (dedup `ticket:{entry_id}:{uid}`) + FCM push
    with `{type:ticket, number}`; cursor option `aun_app_tickets_since` (first run plants NOW;
    outage keeps cursor). DB v9 bump only re-runs activation to schedule the cron.
  - **App 1.13.0+15**: Support tab gains "Support tickets" card (only when `tickets_enabled`);
    `tickets_screens.dart` — TicketsScreen (status/answered chips, FAB), TicketThreadScreen
    (chat bubbles customer-right/staff-left, HtmlWidget bodies, reply composer, closed-ticket
    reopen note), NewTicketScreen (dynamic topic dropdown, "which projector?" device dropdown,
    auto-context privacy note). Router: type 'ticket' → thread by number. Notification centre:
    'ticket' icon + tappable. 24 new bn+en strings (ran `flutter gen-l10n` — REQUIRED after ARB
    edits, output lives in lib/l10n/).
  - Tests: NEW `test-app-api-v8.php` (38: disabled state, config gate, identity emails, token
    fallbacks, create payload incl. context block + field map + foreign-serial rejection,
    list/thread/reply proxying + cid-image strip + 404 ownership, poll → notice+push, dedup,
    outage cursor safety, reverse email mapping). Full pass = 44+42+30+25+22+27+36+38 = **264**.
  - Go-live adds: (1) upload bridge folder via cPanel to osTicket root, set secret in
    bridge-config.php; (2) same secret + base URL in AUN App → Settings; (3) add "AUN Projector
    App" as a Purchase Channel list choice in osTicket (Admin → Manage → Lists) so the mapped
    value validates; (4) new plugin zip; (5) rebuild APK (no new native plugins).
- **v1.14.0** — (2026-07-19, DB stays v9) — ticket attachments both directions
  - **Customer uploads** on new ticket AND reply: `file_picker ^8.1` (NEW native plugin — first
    Gradle build in the user's build-aun-app.cmd), `withData` → base64. Caps: 5 files, 8 MB each
    (client + server `AUN_App_Tickets::MAX_FILES/MAX_FILE_BYTES`). App→WP `attachments`
    [{name,type,data(base64)}] → `sanitize_uploads` (count/size/base64 validation, WP_Error →
    400) → bridge `aun_bridge_files` → `$vars['attachments']` (osTicket API base64 format) on
    Ticket::create / postMessage. Wrapped so a bad file never sinks the ticket.
  - **Agent files → downloadable**: bridge `thread` now returns per-entry non-inline attachments
    (`ost_attachment` inline=0 join, `ref`=attachment id); new bridge `attachment` action streams
    file bytes (AttachmentFile::getData) with an ownership join (guessed ref from another ticket
    → 404). WP `GET /me/tickets/attachment?number=&ref=` (AUTH-gated, private — unlike the public
    repair-image proxy) → `fetch_attachment` → echo+exit stream. App: download chips under each
    message, tap → `background_downloader` DownloadTask with the X-AUN-Token header →
    `FileDownloader().openFile` (system viewer). **Agent links** → `HtmlWidget onTapUrl` opens the
    browser. `require_once INCLUDE_DIR.'class.file.php'` added to the bridge for AttachmentFile.
  - 7 new bn+en strings (ran gen-l10n). Tests: v8 extended to 50 (create/reply forward
    attachments, oversize/bad-base64/count-cap rejection, thread exposes attachments + link
    survives + cid-img still stripped, download proxy ownership + bytes). Full pass =
    44+42+30+25+22+27+36+50 = **276**. analyze clean, widget tests, AOT OK.
  - ⚠️ osTicket-side prerequisite (user): the "Report a Problem" (and any) help-topic message
    field must have attachments ENABLED (osTicket form field setting) and the file type/size in
    Admin → Settings → System must allow the customer's files. The bridge's attachment write path
    can't be bench-tested (no osTicket locally) — verify on the real server with a photo.
  - ⚠️ Deploy gotcha found live: the WP→bridge call is a server-to-server request. Two things bit
    the user: (1) they left the osTicket **site URL** blank in Settings → status read "not
    configured" (a purely LOCAL check: base URL non-empty AND secret ≥20 chars, never touches the
    server); status text now names the exact missing field. (2) After filling it, a **Cloudflare**
    WAF custom rule on support.smartliving.com.bd (`ip.geoip.country ne "BD"`) blocked the WP
    server's Singapore IP → HTTP 403 with a Cloudflare HTML block page (not our JSON). The fix is
    on Cloudflare, not in code: add an exception to that rule for the WP server IP
    (`... and ip.src ne <WP_IP>`) or the `/aun-app-bridge/` path — the bridge secret still gates
    it. The plugin now sends a plain `AUN-App-Bridge/1.0` UA (many cPanel WAFs 403 the default
    "WordPress" bot UA) and surfaces the server's actual response snippet in the error so the cause
    is visible, not guessed.
- **v1.15.0** — (2026-07-19, DB stays v9) — 7 post-install feedback items
  - **Ticket attachments** shipped in 1.14 needed a Gradle fix to build: `file_picker 8.3.7`
    hardcodes `compileSdk 34` but its transitive `flutter_plugin_android_lifecycle 2.0.35` requires
    36 → build failed at `:file_picker:checkReleaseAarMetadata`. Fixed in `android/build.gradle.kts`
    (see "Plugin compileSdk override" gotcha above).
  - **(1) New-ticket "something went wrong"**: the bridge `topics` action filtered
    `Topic::objects()->filter(['isactive'=>1])` — `isactive` isn't a filterable ORM column in
    osTicket 1.18 (active state is a flags bitfield) → the call 500'd. Rewrote to `aun_public_topics()`
    (filter by the real `ispublic` column, then the model's `isActive()` method; raw-SQL fallback) and
    dropped the fragile per-topic form-field iteration entirely (the app only needs id+name; the
    field mapping is applied WP-side). `create` now falls back to the first public topic when the app
    sent none. App `NewTicketScreen` made resilient — topics/devices both `catchError`→empty, so a
    customer can always open a ticket even with no devices.
  - **(2) Device auto-detection** (the big one): NOTHING queried the ERP by the login phone —
    `get_devices` only returned manually-added links + dealer registrations. New
    `AUN_App_Warranty::sync_own_purchases($uid,$phone,$force)` runs `purchases_by_phone` (which
    already links retail/direct sales and skips Dealers-group ones) — forced on **verify-otp** and
    throttled (5 min) on **/me/devices**, with `?refresh=1` (pull-to-refresh) forcing it. So retail
    purchases appear with zero scanning, past or future; only Dealers-group needs manual
    registration (`is_dealer_sale` = customer_group_id === settings dealer_group_id). ⚠️ Requires the
    ERP AppLookupController's phone endpoint returning `customer_group_id`; already does.
  - **(3) Add-device by-phone UX**: results were appended below the number pad (buried). Now: a pure
    retail match auto-links and **pops back with a success snackbar**; mixed/dealer results show in a
    dedicated `_PhoneResults` view that REPLACES the options (never buried) with a "search again"
    button. Serial field renamed **"Box barcode number" → "Serial number"** everywhere (label +
    help + subtitles), matching the user's language; the "where is it" help still explains the box
    sticker location.
  - **(4) Rename to AUN Care**: Android label + MaterialApp title = "AUN Care Bangladesh"; `appTitle`
    (home top line, fallbacks) = "AUN Care"; ticket agent-block byline = "AUN Care app" (osTicket
    purchase-channel value kept as "AUN Projector App" so the user's existing osTicket list still
    validates). Greeting weather **emoji → clean Material weather icons** (`_greeting` returns
    `IconData`: wb_sunny/water_drop/thunderstorm/wb_cloudy/wb_twilight/celebration/local_movies/
    bedtime) — crisp at small size, rendered as an Icon beside the title.
  - **(5) OTP length**: SMS was 4-digit (live `aun_alpha_otp` config) but the app hardcoded "6-digit".
    `/auth/request-otp` now returns `otp_length`; app stores it, shows "A {count}-digit code…", and
    the min-length check uses it. `otpSentTo` string gains a `{count}` placeholder.
  - **(6) Support tab redesigned**: when tickets are enabled, a prominent green **ticket hero card**
    ("Open a support ticket" + why-a-ticket note) is the primary CTA; WhatsApp + Call drop to small
    secondary `_MiniContact` tiles under "Other ways to reach us". WhatsApp-first layout is kept only
    as the fallback when the bridge isn't configured.
  - **(7) Home live status strip** (`home_status_strip.dart`): a compact card at the top of Home
    listing anything in progress — repair (not delivered/completed), spare-parts order (not
    completed/cancelled), open ticket (state != closed) — each row tappable to its detail. Renders
    nothing when all settled; refreshes on the shell's refreshSignal.
  - Tests: v8 → 54 (auto-sync on login: retail auto-links, Dealers-group doesn't, later sale on
    refresh; + otp_length in request-otp). v2/v6 patched for the new login auto-link (clear
    auto-linked rows to isolate the manual-resolve cases). Full pass = 44+42+30+25+22+27+36+54 =
    **280**. analyze clean, widget tests, AOT OK.
  - ⚠️ First Gradle build with `file_picker` — the compileSdk-36 override in build.gradle.kts makes
    it build; if a future plugin hits the same AAR-metadata check, that override already covers it.
- **v1.16.0** — (2026-07-19, app only, plugin unchanged) — premium look: cinematic header + skeletons
  - User wanted the app to "look premium" and asked whether a full-screen animated projector-beam
    background was a good idea. Design call (agreed with user): NO full-screen beam — it fights the
    system light/dark theme (app is white by day, dark by night), reads as over-designed, and costs
    battery on budget phones. Instead: keep the cinema motif but CONFINE + theme it.
  - **Theme-aware cinematic header** `lib/src/ui/projector_ambient_background.dart`. Two widgets share
    one `_Cfg`/`_Particle`/painter: `ProjectorAmbientBackground` (full-screen, opaque dark, animated —
    the original spec) and **`ProjectorHeaderGlow`** (the one actually used on Home). Header behaviour:
    DARK theme → electric-blue beam (breathing) + drifting dust; LIGHT theme → a soft brand-blue
    (#0188FE) static wash, no dust, NO ticker (calm + zero battery by day). ShaderMask fades the band
    into the content. Wired into `home_screen.dart` HomeTab as a `Positioned(top:0,height: topPad+190)`
    behind the greeting, edge-to-edge under the status bar. Perf: single Canvas pass, one Ticker,
    RepaintBoundary, no blur, pauses off-screen/background/covered-route, honours
    MediaQuery.disableAnimations, `intensity` scales opacity+particle count. The spec was written for
    Jetpack Compose — translated faithfully to Flutter (Compose wouldn't compile in this app).
  - **Skeleton loaders** `lib/src/ui/skeleton.dart`: `Shimmer` (one controller, moving-sheen gradient,
    reduced-motion aware) + `SkeletonBox` + `DeviceCardSkeleton`. Replaced the plain spinner on the
    My Devices loading state; reusable for other lists.
  - Preview: an HTML artifact (scratchpad/ambient-preview.html) replicates the header math in BOTH
    themes side-by-side so the look can be judged/tuned without an APK build (Flutter web preview
    hangs in-session, so this is the visual proxy).
  - Verify: analyze clean, widget tests, AOT OK. No backend/plugin change. ⚠️ Home is
    still light-themed content; the header glow sits behind the greeting only — the rest of Home is
    unchanged.
  - **Premium fundamentals completed (same 1.16.0 round, user asked "did you do all four?")**:
    (1) **Typography** — bundled **Hind Siliguri** (Bengali+Latin, one family, weights 300–700;
    `assets/fonts/`, declared in pubspec) as the app-wide `fontFamily` + a deliberate `textTheme`
    scale in `theme.dart` (display 30 → label 11, weight-driven hierarchy, tightened tracking on
    large Latin headings). One family covers BOTH scripts so Bangla never falls back mid-sentence.
    (2) **Neutral system** — scaffold ground tinted toward the brand (light = 3.5% aunBlue over
    #F6F8FB; dark = #0C0F14 below surface), cards borderless-elevation style kept but with
    `surfaceTintColor` transparent, cooler softer shadowColor, tuned divider/input/nav-bar tokens.
    (3) **Micro-interactions** — `lib/src/ui/press.dart`: `PressableScale` (~3% dip + selection
    haptic, reduced-motion aware) wired into `QuickAction`; `confirmHaptic()` (light impact) fired
    on ticket create, ticket reply, and device-added successes.
    (4) Motion with intent + skeletons were already in (header glow, status strip, FadeSlideIn,
    predictive back, Shimmer/DeviceCardSkeleton on My Devices).
    ⚠️ Font files downloaded from google/fonts GitHub (OFL); ~1.3 MB total added to the APK.
  - **Follow-up round (user tested 1.16 build)**:
    (a) **New-ticket UX**: attach button + submit existed but sat BELOW THE FOLD of the long form →
    user thought they were missing. Restructured `NewTicketScreen`: submit is now a **pinned
    bottomNavigationBar button** (always visible), device picker first, topic second, attachments
    directly under the description, tighter spacing.
    (b) **"Something went wrong" on submit**: osTicket's required "Purchase Channel" choices field
    rejects our mapped value until the admin adds the choice → Ticket::create fails. Bridge create is
    now **self-healing**: attempt 1 as-requested → attempt 2 without custom fields → attempt 3
    without fields on the first public topic; response carries `fields_applied` and WP error_logs a
    hint when the mapping was dropped. A customer can never be blocked by form config again.
    ⚠️ re-upload the bridge folder.
    (c) Correct osTicket steps for the Purchase Channel choice (earlier guidance said Manage → Lists
    — WRONG for an inline choices field): **Admin Panel → Manage → Forms → "Ticket Details" (the
    custom form on Report a Problem) → click the "Purchase Channel" field's Config/wrench → add
    "AUN Projector App" on a new line in Choices → Save → Save Changes**. If it was built as a list
    instead, then Manage → Lists → (that list) → Items → Add.
    (d) **Smooth day/night**: `themeAnimationDuration: 450ms` + `easeInOutCubicEmphasized` on
    MaterialApp, and the Home header glow now CROSSFADES beam↔light-wash via AnimatedSwitcher
    (450 ms, same curve) instead of snapping.
    (e) Design position on auto-selected topic/device: smart defaults kept (friction-free,
    standard for support forms); both remain editable dropdowns, device listed first.
- **v1.17.0** — (2026-07-19, app 1.17.0+19, plugin 1.17.0) — ticket diagnosis, device pickers,
  greeting variety, "what to watch" brain
  - (1) **Ticket "something went wrong" with no error log**: the bridge no longer crashes (topics
    fixed) so nothing hit error_log — the failure is now osTicket rejecting the create (required
    Purchase Channel choice). Made it VISIBLE without a log: WP `call()` flattens osTicket's
    per-field `errors` into the message; app `errorText` appends the technical cause for non-API
    exceptions + a `timeout` code; **NEW admin "Send test ticket" button** (Settings) does a real
    end-to-end create from the server and prints the exact code+message. So the user can now read
    the true reason on-screen. (The self-healing create from 1.16 still saves the ticket by dropping
    the offending fields — if it's STILL failing, the test button will say why.)
  - (2) **Device picker**: Buy Spare Parts + Send for Repair shared `_DevicePicker` (ChoiceChips,
    messy with many devices) → now the same `DropdownButtonFormField` as the ticket form; one edit
    fixed both. Auto-selects when there's a single device.
  - (3a) **Greeting variety**: was 2 variants (odd/even day) → now **3 variants per bucket** rotated
    by day-of-year %3 × 5 time slots, so a given line repeats only every 3rd day. 15 new sub strings
    each language.
  - (3b) **"What to watch" brain** `class-aun-app-watch.php` + `GET /watch` (public, 12 h cache):
    LOCAL picks admin-curated in Settings (`Title | Platform | URL | poster`, shown first — Chorki/
    Bioscope/Hoichoi have no APIs); GLOBAL = TMDB weekly trending with REAL per-title BD
    watch/providers detection (Netflix/Prime/Hoichoi…), playable-on-a-subscription titles ranked
    first, provider deep-links. Needs a free TMDB key in Settings; blank key → global off; both
    empty → card hidden. App: `watch_card.dart` horizontal poster rail on Home (below videos),
    local/trending ribbon, tap → opens the platform. WatchPick model + api.watchPicks().
  - Tests: v8 → 62 (8 new watch: local parse/malformed-drop, TMDB merge + BD-platform detection +
    ranking + deep-link + poster CDN, no-key path). analyze clean, widget tests, AOT (pending
    confirmation this run).
  - ⚠️ NO new native plugins → APK builds like 1.16. ⚠️ user must add a free TMDB API key in
    AUN App → Settings for the global "what to watch" section; local picks work with no key.
    (TMDB signup form answers, given this round: Application Name "AUN Care Bangladesh", URL
    https://aun-projector.com.bd, Type of Use = Personal/Non-Commercial, summary = shows trending
    titles + streaming availability, not resold. It is free.)
- **v1.18.0** — (2026-07-23, app 1.18.0+20, plugin 1.18.0, DB v10) — dismissed-purchases brain +
  two UX fixes
  - **(2) "Removed device keeps re-appearing" — root cause + fix.** Removing an auto-linked (direct
    ERP) device only deleted the `aun_app_devices` row; the ERP sale is still there, so the next
    login/refresh `sync_own_purchases` → `purchases_by_phone` → `link_direct` re-added it forever.
    Nothing remembered the customer's choice. NEW **dismissed-purchases ledger** `aun_app_dismissed`
    (DB v10; one row per user+serial: invoice_no, sale_date, dismissed_at). The decision brain:
    `link_direct($args)` gained an `auto` flag. AUTO path (login/refresh, `sync_own_purchases` passes
    `auto=true`): if a dismissal exists for this (user, serial) AND it's the SAME purchase (invoice
    matches, or same sale-date when no invoice, or nothing to compare → stay suppressed) → skip
    silently (code `dismissed`). A genuinely NEW purchase of the same unit (different invoice) clears
    the stale dismissal and links. MANUAL path (scan / find-by-phone / add-device, default
    `auto=false`): clears any dismissal and links — the customer's escape hatch to undo a removal.
    `remove_device` (direct branch only) records the dismissal, capturing the row's invoice_no +
    purchase_date. Serial stays FREED for other buyers (row truly deleted; ledger is per-user) — the
    "one serial, one account" rule and the hourly return/deletion sweep are untouched (the sweep uses
    `unlink_verified` direct-delete, never `remove_device`, so it never writes a dismissal). New
    helpers `record_dismissal` / `clear_dismissal` / `is_purchase_dismissed`; `purchases_by_phone`
    surfaces a `dismissed` flag per result row. Table accessor `aun_app_api_dismissed_table()` /
    `AUN_App_Warranty::t_dismissed()`.
  - **(3) Home "AUN Care" brand eyebrow removed.** Design call (leading logged-in home screens lead
    with the greeting, not the app name — that lives on the launcher icon / splash / app switcher).
    `home_screen.dart` now leads with the greeting title + the weather icon inline (18px); the small
    letter-spaced `l.appTitle` eyebrow above it is gone. Easily reverted. (`l.appTitle` still used as
    a fallback elsewhere.)
  - **(4) Support tab first-card misalignment FIXED.** It was a stray lone `SectionHeader
    (supportHelpHeading)` above the ticket-hero card — no other tab has a header above its first
    card, so the card sat lower. Removed it; the ticket hero now aligns with Home / My Devices /
    Settings. The ticket hero's own "Open a support ticket" title made the heading redundant anyway.
  - Tests: NEW `test-app-api-v9.php` (14: table exists, auto-link→remove→ledger recorded→does NOT
    re-appear on refresh [the bug], manual by-phone re-adds + clears ledger, new-invoice re-link
    despite dismissal, per-user isolation [freed serial claimable by another customer], no-invoice
    date-match suppression). All 14 pass; v8 suite re-run = 62/0 (no regression). `flutter analyze`
    on the two edited screens = No issues found. v9 lives in this session's scratchpad
    (`0815f8fe-…/scratchpad/test-app-api-v9.php`).
  - ⚠️ NO new native plugins → APK builds exactly like 1.17. Plugin zip rebuilt at
    `<workdir>\aun-app-api.zip` (must be re-uploaded — DB v10 migration runs on activation).
- **v1.19.0** — (2026-07-23, app 1.19.0+21, plugin 1.19.0, DB stays v10) — 6-item feedback round
  - **(3) osTicket "Incomplete client information" — ROOT CAUSE FIXED.** The admin test + app create
    both failed at osTicket's `Ticket::create` with `user: Incomplete client information`. Cause: when
    `Ticket::create` gets NO `uid`, osTicket re-validates the whole **Contact Information (user) form**
    from the API vars and fails if that form has ANY required field the API didn't fill (their osTicket
    has one — a required Phone/custom user field). The self-heal from 1.16 only dropped *ticket* custom
    fields + topic, never the user, so it could never fix this. FIX in the bridge (`api.php`): new
    `aun_resolve_user_id()` looks up / `User::fromVars()`-creates the osTicket end-user (needs only
    name+email) and passes its `uid` to `Ticket::create` → osTicket skips the user-form validation
    entirely. `require_once INCLUDE_DIR.'class.user.php'` added. Failure response now also returns
    `user_resolved` for diagnosis. ⚠️ **re-upload the `aun-app-bridge` folder to the osTicket root**
    (the WP plugin is unchanged for this item; bench can't test it — verify with the admin "Send test
    ticket" button, which should now print a real ticket number).
  - **(1) "What to watch" now OTT-only (no cinema-only titles).** `class-aun-app-watch.php`
    `global_picks()` rewritten: instead of raw weekly trending (which surfaced films only in cinemas),
    it asks TMDB **Discover** with `watch_region=BD & with_watch_monetization_types=flatrate &
    sort_by=popularity.desc` (movies + TV pooled, popular first) — so titles must be streaming on a BD
    OTT subscription to appear; a per-title `watch/providers` check resolves the platform label + deep
    link and drops any straggler with no BD provider. Cinema-only releases have no providers → never
    shown. `WATCHABLE_BUCKETS` = flatrate>free>ads>rent>buy. Provider lookups paid only up to
    GLOBAL_LIMIT served.
  - **(2) Local picks = a real admin FORM + auto-expiry.** Settings → What to Watch: the freeform
    textarea is replaced by a repeatable-row table (Title / Platform / URL / Poster / **Show until**
    date) with **+ Add a pick** and per-row remove (vanilla JS, no deps). Server helper
    `AUN_App_Admin::collect_local_picks()` serialises rows → the stored `Title|Platform|URL|Poster|End`
    line format (pipes in values escaped to `/`, empty trailing cols trimmed, title+URL required,
    invalid dates dropped). `local_picks()` now parses a 5th end-date column and **skips picks past
    their end date** (site tz) so a "showing now" recommendation drops off by itself. Old 3–4 col lines
    still parse.
  - **(6) Home quick actions redesigned.** Dropped the **WhatsApp** button (contradicted the
    ticket-first Support tab) and **My requests** (now auto-surfaced by the HomeStatusStrip when
    something's in progress). The 3-up grid is now channel-neutral projector-owner actions:
    **Register device / Book a repair / Spare parts** (deep-link to RepairRequestScreen /
    PartsRequestScreen). New l10n `quickRepair`/`quickParts` (ran gen-l10n). Matches leading
    device-care apps (add device / repair / parts, no chat button on the home grid).
  - **(4) [answered] Removed-device memory survives reinstall/clear-data** — the `aun_app_dismissed`
    ledger is keyed on the WP **user_id**, which is stable per phone; clearing app data / reinstalling
    just re-logs-in the same account, so dismissals still apply. Nothing lives on the device.
  - **(5) [answered] Device compatibility** — one **universal** APK (`flutter build apk --release`, no
    `--split-per-abi`) carries all ABIs (armeabi-v7a 32-bit, arm64-v8a 64-bit, x86_64) → every real
    Android phone. `minSdk = flutter.minSdkVersion = 24` → **Android 7.0+** (covers ~all phones in use;
    Flutter warns <24, errors <23, so 24 is the sensible floor). Flutter renders in logical pixels →
    DPI/screen-size independent. Low-end friendly: the cinematic header is dark-only + honours
    reduced-motion + pauses off-screen; skeletons are cheap.
  - Tests: v8 → **64** (watch section rewritten: Discover mock, cinema-only exclusion, expired local
    pick auto-hidden). v9 dismissal brain still 14/14. `collect_local_picks()` round-trip verified via
    reflection. `flutter analyze` (home + support) clean, gen-l10n ran, AOT pending the user's build.
  - ⚠️ NO new native plugins → APK builds like 1.17/1.18. Re-upload `aun-app-api.zip` AND the
    `aun-app-bridge` folder (the osTicket fix is in the bridge).
- **v1.20.0** — (2026-07-23, app 1.20.0+22, plugin 1.20.0, DB stays v10) — 8-item post-ticket round
  (tickets now open successfully; these polish + fix the ticket experience end-to-end)
  - **(1) App never shows debug/error internals.** `AUN_App_Tickets::call()` now returns a single
    friendly message to the app (`friendly_error()` — "Support is having a temporary problem…") while
    the TECHNICAL detail (osTicket per-field errors, WAF HTML) goes ONLY to `error_log` +
    `WP_Error data['detail']`. Admin "Send test ticket" reads `data['detail']` and prints the real
    cause (admin-only). App-side `errorText()` no longer appends raw `error.toString()` for
    non-ApiException failures either. Bench-tested: create failure → app msg has no "Incomplete client
    information", detail preserved for dev.
  - **(2) Raw `data:text/html;charset=utf-8,` prefix cleaned.** Root cause: the bridge sent the data:
    URI with RAW (un-encoded) HTML, so osTicket stored the literal prefix in the body. Bridge
    create+reply now `rawurlencode()` the payload (proper data URI) → clean at the source (app AND
    osTicket agent view). WP `thread()` also (a) strips any leftover `data:text/…,` prefix for OLD
    tickets and (b) hides the agent-only "Customer & device" context block from the CUSTOMER's own
    message (splits on the first `<hr>`; their typed text is esc_html'd so a literal `<hr>` only ever
    comes from our block). Bench-tested.
  - **(3) Attachments open again.** Images now open **IN-APP** (`_ImageAttachmentViewer`: fullscreen
    pinch-zoom `Image.network` with the `X-AUN-Token` header) — no system-viewer / FileProvider, which
    was the "couldn't open the file" failure point. Covers both directions (customer uploads + agent
    replies). Non-images (PDF/ZIP) keep the `background_downloader`→`openFile` path. ⚠️ If PDF/ZIP
    still fail after this, it's the Android system-viewer step — needs a device log; images are the
    common case and now reliable. (Bridge attachment queries were reviewed — `type='H'` thread-entry
    join is correct for both API-uploaded and agent files.)
  - **(4) Live ticket thread.** `TicketThreadScreen` now polls every 12s while open, refreshes on app
    resume (WidgetsBindingObserver), and refreshes instantly on a foreground push
    (`AppPush.foregroundPings`). Silent refresh (no spinner/error takeover); auto-scrolls to the newest
    only when the entry count grows. Staff replies appear without leaving + reopening.
  - **(5) Ticket push reliability.** The push pipeline was correct (v8 tested) — the gap was the 10-min
    WP-Cron poll not firing on a low-traffic site. Added: `init` self-heal that (re)schedules
    `aun_app_tickets_poll` if missing when tickets are configured; and an OPPORTUNISTIC throttled poll
    (`poll_replies`, 3-min global lock) on the notifications-feed fetch so replies surface whenever the
    app is opened even if WP-Cron is idle. Confirmed push triggers: content (firmware/video/tip for
    owned models), repair approve/reject, maintenance (daily), ticket reply. ⚠️ For push while the app
    is CLOSED the site still needs WP-Cron to actually run — recommend a real server cron hitting
    `wp-cron.php` every ~5 min (or a monitor service). ⚠️ NOT yet built: push on ERP-driven repair
    STATUS changes (received→ready→delivered) — those come live from the ERP with no server-side
    change hook; would need a per-active-repair ERP poll (future work).
  - **(6) Greeting name no longer cropped.** The greeting title (`Good evening, {name}`) was a
    single-line ellipsized Text → the name truncated to "Jo…". Now `maxLines: 2` so it wraps instead of
    cropping a person's name (the leading-app approach; not a marquee/slider).
  - **(7) "What to watch" restored + GLOBAL.** Reverted the BD-Discover change (returned empty — TMDB's
    BD provider data is sparse). Back to `/trending/all/week` (worldwide), filtered to titles that are
    actually STREAMING on an OTT platform: per-title `watch/providers` checked across well-covered
    regions (US, GB, CA, AU, IN) then any region, buckets flatrate/free/ads (rent/buy excluded).
    Cinema-only releases have no providers → dropped. NOT filtered to Bangladesh — global discovery;
    platforms surfaced (Netflix/Prime/Disney+…) are available here anyway. Bench-tested (streamable
    kept, cinema-only excluded).
  - **(8) App name = "AUN Care Bangladesh" everywhere.** Android `android:label` + MaterialApp title
    already correct; updated the **build output** (`build-aun-app.cmd` title/echoes + APK now
    **`AUN-Care-Bangladesh.apk`**, was `AUN-Projector-app.apk`), pubspec description, and the plugin
    header description. Left alone (intentional/non-user-facing): "AUN projectors" in onboarding copy
    (refers to the hardware) and the osTicket `purchasechannel` default value "AUN Projector App" (an
    agent-facing osTicket LIST choice — changing it needs the user to add a new choice in osTicket;
    editable in AUN App → Settings if they want).
  - Tests: v8 → **70** (item 1 generic-error + item 2 prefix/context-strip added). v9 14/14.
    `flutter analyze` (tickets/home/widgets) clean, gen-l10n ran, 3 widget tests pass.
  - ⚠️ NO new native plugins → APK builds like before. Re-upload **`aun-app-api.zip`** AND the
    **`aun-app-bridge` folder** (items 2+3 touch the bridge). New APK filename is
    `AUN-Care-Bangladesh.apk`.
- **v1.21.0** — (2026-07-23, plugin 1.21.0, DB v11, **app UNCHANGED 1.20.0+22 → no APK rebuild**) —
  cron guidance + repair-status push + App Content admin redesign
  - **(1) [answered] Server cron.** Their cPanel cron ran aun-projector.com.bd's `wp-cron.php` only at
    `27,57` (twice/hr) — why ticket push lagged. Told them to change THAT row to `*/5 * * * *` (and
    optionally `define('DISABLE_WP_CRON', true)` in that site's wp-config). Negligible load on Namecheap
    shared hosting — wp-cron only *checks* for due events; heavy jobs keep their hourly/daily schedules.
  - **(2) ERP repair-status change PUSH (DB v11).** New `aun_app_repairs.last_erp_status` column + new
    10-min cron `aun_app_repair_poll` → `AUN_App_Services::poll_repair_statuses()`: for every active
    LINKED repair (has a job sheet, not terminal) it fetches the live ERP status, and on a REAL change
    pushes the owner (`AUN_App_Notices::repair_status_changed` → notice type 'repair' + FCM, deduped
    per status). First sighting sets a silent baseline (no "you're at status X" spam); ERP outage skips
    the row untouched; terminal status pushes the final update + auto-closes. Reuses the existing app
    router (push data `{type:'repair', ref}` → RepairDetailScreen — no app change needed). Cron
    scheduled on activation + self-healed on init + unscheduled on deactivate. Bench-tested: NEW
    `test-app-api-v10.php` (13: baseline-no-push, change→notice+push+deep-link, dedup, terminal→
    push+close+stop-polling, outage safety). ⚠️ push while app CLOSED still needs the */5 server cron
    (item 1). All push triggers now: content, ticket reply, repair approve/reject, **repair status**,
    maintenance.
  - **(3) App Content admin page REDESIGNED** (`admin/class-aun-app-admin.php page_content()`). Was one
    flat table — unusable with lots of content. Now: **type quick-filter chips with live counts**
    (All / Firmware / Manual / Video / Tip), a **device + title-search filter bar** (GET params, so
    filters survive save/delete/toggle redirects), a **collapsible Add form** (`<details>`, native, no
    JS; auto-opens when editing; **model + type pre-filled from the active filter** so adding many
    items for one device is fast), and the list **grouped by device/model** (collapsible sections with
    counts, ordered Products-first). NEW **inline Live/Hidden toggle** per row (`AUN_App_Content::
    set_active`, nonce link). ⚠️ IMPORTANT FIX found while testing: content whose `model_id` is no
    longer in Products (removed/renamed model) is NO LONGER hidden from admin — it renders under a
    "Model #N" group so it's always manageable. Inline `<style>`/`<script>` carry `data-no-optimize="1"`
    (WP Rocket rule). Render + all three filters bench-verified.
  - Tests: NEW v10 (13) repair-status poll; v8 70/0, v9 14/14 no regressions; admin render + filters
    smoke-verified via wp eval. All PHP lints clean.
  - ⚠️ **Plugin-only round.** Re-upload `aun-app-api.zip` (DB v11 migration runs on activation, schedules
    the repair cron). The **app APK does NOT need rebuilding** — no Dart changes; the installed 1.20.0
    app already handles repair-status push + the new content just flows through existing endpoints.
- **v1.22.0** — (2026-07-23/24, plugin 1.22.0, app 1.21.0+23, DB stays v11) — TMDB key + ticket message
  + open-any-file + dynamic content form
  - **(1) TMDB picks empty — v3/v4 key auth.** `AUN_App_Watch::tmdb()` only sent the key as the v3
    `?api_key=` param; if the admin pasted TMDB's **v4 "API Read Access Token"** (a JWT) every call
    401'd → blank rail. Now auto-detects: JWT (`eyJ…`, 2+ dots) → `Authorization: Bearer` header, else
    v3 query param. NEW admin **"Test TMDB key"** button (Settings → What to Watch) runs a live
    `AUN_App_Watch::diagnostic()` → prints detected credential type + trending/pick counts or the exact
    problem. Bench-tested (v3→query, v4→bearer, both return picks).
  - **(2) Ticket message was raw `data:text/html…` + %-encoded gibberish.** ROOT CAUSE: osTicket's
    direct `Ticket::create` path does NOT decode a `data:` URI (the API-controller layer does that, and
    the bridge bypasses it) — it stored our whole string verbatim. Last round's `rawurlencode` just made
    it %-encoded. FIX: the bridge now sends **plain HTML** as `message` (osTicket wraps a plain string as
    an HtmlThreadEntryBody since rich text is on → renders correctly, UTF-8/Bangla preserved). WP
    `thread()` read-side hardened for the already-created broken tickets: strip the `data:…,` prefix,
    then **url-decode** if what's left is clearly percent-encoded, then hide the agent context (`<hr>`
    split). ⚠️ **re-upload the `aun-app-bridge` folder.** Bench-tested incl. the %-encoded decode.
    (Bangla: message is `nl2br(esc_html())` = UTF-8 safe; raw HTML preserves it end-to-end.)
  - **(3) Open ANY attachment type via Android's "open with".** Non-images failed on the old
    `FileDownloader.openFile` (FileProvider). NEW dep **`open_filex ^4.5`**: download through the auth'd
    proxy, then `OpenFilex.open(path, type: mime)` fires an ACTION_VIEW intent → Android shows the app
    chooser (PDF/ZIP/DOC/…). Returns opened / noApp / failed → distinct messages (new l10n
    `ticketNoApp`, gen-l10n ran). Images still open in-app (`_ImageAttachmentViewer`). ⚠️ **NEW native
    plugin → first Gradle build of `open_filex` happens in the user's build-aun-app.cmd** (4.x has AGP
    `namespace`, should be clean).
  - **(4) Add-Content form now DYNAMIC + 'Tip' retired.** `AUN_App_Content::TYPES` = firmware/manual/
    video (no 'tip'). Add form JS (`#aun-type` change) shows only each type's fields: Firmware = file +
    version + size; Manual = file + size; Video = a link (no version/size, media button hidden), with
    URL/description labels + hints adapting per type. Existing 'tip' rows stay fully visible/manageable
    (chip appears only if legacy tips exist; edit dropdown keeps a "(retired)" option); no NEW tips can
    be created. Bench render-verified (tip gone from add dropdown, legacy tip still listed, dynamic rows
    present).
  - Tests: v8 → **73** (url-decode, v3-query + v4-bearer auth). v9 14/14, v10 13/13. `flutter analyze`
    clean, gen-l10n ran, 3 widget tests pass. All PHP lints clean.
  - ⚠️ This round needs BOTH: re-upload `aun-app-api.zip` + the `aun-app-bridge` folder, AND **rebuild
    the APK** (open_filex is a new native plugin; app changed). New APK = `AUN-Care-Bangladesh.apk`.
- **v1.23.0** — (2026-07-24, plugin 1.23.0, app 1.21.0+24, DB v11) — dynamic-form follow-through +
  attachments always via the OS chooser
  - **(1) [answered] TMDB confirmed working (v3 key).** The `/watch` picks auto-refresh: server caches
    12 h (TMDB trending updates weekly), local picks refresh on Settings save.
  - **(2) Add-Content form now VISIBLY dynamic.** Last round only hid Version/File-size; the always-
    visible **Title placeholder was still firmware-specific** for every type (so it looked unchanged).
    Rewrote the toggler as plain DOM (no jQuery/TinyMCE timing dependency, `getAttribute('data-types')`)
    driven by a `PRESET` map — per type it now sets the **Title placeholder** (firmware/manual/video
    examples), URL field label + help + placeholder, description label, hides the media button for
    video, and shows only that type's rows (Firmware = file+version+size, Manual = file+size, Video =
    link only). Title input got `id="aun-title"`. Bench render-verified (PRESET + setPh('aun-title') +
    per-type examples present).
  - **(3) ALL ticket attachments now open via Android's "open with" chooser — images included.** Per
    the user's call (a huge/odd image could break an in-app viewer), removed the in-app image viewer
    entirely; `_openAttachment` always downloads + `OpenFilex.open` so the OS chooser handles every
    type safely. Deleted the now-unused `_ImageAttachmentViewer`.
  - Tests: analyze clean, 3 widget tests pass, admin render smoke-verified, PHP lints clean.
  - ⚠️ Re-upload `aun-app-api.zip` (admin change) + **rebuild the APK** (`open_filex` from v1.22 is the
    first native build; app changed again). Bridge folder unchanged since v1.22 — but if not yet
    uploaded, upload it (the plain-HTML ticket-message fix lives there).
- **v1.24.0** — (2026-07-24, plugin 1.24.0, app 1.21.0+25, DB v11) — content leak + OneDrive downloads
  + real YouTube dates
  - **(1) Support-tab "Common Questions" leaked ALL content.** Root cause of a regression from removing
    'tip' from `AUN_App_Content::TYPES`: `get_list()`/`for_models()` only applied the type filter when
    `in_array($type, self::TYPES)` — so `type=tip` (no longer a listed type) **skipped the filter and
    returned every firmware/manual/video**. Fixed both to filter on ANY non-empty `$type` (a legacy/
    unknown type returns only its rows / nothing). The support-tab FAQ now shows only real tip rows
    (legacy) — delete them in admin to empty the section, or ask to remove the section. Bench-tested.
  - **(2) OneDrive/SharePoint firmware links now download.** Their share links (e.g.
    `…-my.sharepoint.com/:u:/g/…?e=…`) open a web VIEWER, so the app downloaded an HTML page. NEW
    `AUN_App_Content::direct_download_url()` rewrites share links → direct download (sharepoint.com →
    append `download=1`; onedrive.live.com `redir?`→`download?`; 1drv.ms → `download=1`); applied to
    firmware/manual `url` in the payload (video URLs untouched, non-cloud URLs untouched). ⚠️ **No API
    needed — but the file must be shared as "Anyone with the link"** (the app downloads anonymously),
    not "People in your org". Bench-tested (rewrite + non-cloud passthrough).
  - **(3) Real YouTube upload date on video cards.** Was showing the admin-added date. NEW
    `AUN_App_Content::youtube_published_at()` fetches `snippet.publishedAt` via the YouTube Data API
    (new Settings key `youtube_api_key`; cached **30 days** — publish date is immutable; '' when no key
    → app falls back to added date). Payload gains `published_at`; app `ContentItem.publishedAt` +
    `displayDate` getter (published_at || updatedAt) used on the video detail chip + ContentTile meta.
    ⚠️ needs a free **YouTube Data API v3** key (Google Cloud Console) in AUN App → Settings. Bench-
    tested (no-key→empty, key→real date via mocked Data API).
  - Tests: focused R5 eval (type-filter no-leak, SharePoint rewrite, YT date) all pass; analyze clean,
    3 widget tests pass, PHP lints clean.
  - ⚠️ Re-upload `aun-app-api.zip` + **rebuild the APK** (app changed: models/content_screens/widgets).
    No new native plugins this round → builds like 1.22/1.23. Bridge unchanged. New optional key:
    YouTube Data API v3 in Settings for real video dates.
- **v1.25.0** — (2026-07-24, plugin 1.25.0, app 1.22.0+26, DB v11) — profile editor, attachment
  chooser+cache, description firmware-only, Tip fully removed, reuse tutorials YT key
  - **(1) Profile editor.** (a) Save didn't close the sheet → `ProfileEditor` gained `onSaved`
    callback; `showProfileSheet` passes `Navigator.pop`; `_save` captures the app-level messenger
    BEFORE the pop so the snackbar still shows (Settings-tab embed passes no callback, stays open).
    (b) Removed the edit-pencil badge from the HOME avatar (leading apps keep it clean; the pencil
    lives in the editor). (c) Emoji upgraded: `Avatar` now draws an emoji on a vibrant per-emoji
    coloured disc (`avatarColorForEmoji` + `kAvatarPalette` in widgets.dart) at ~1.32× (was 1.05× —
    fixes "too small"); curated cinema/fun set; picker shows big coloured discs (`_EmojiDisc`) with a
    selection ring. NOTE: true Netflix-style illustrated avatars need bundled art — this is the
    emoji-on-colour approach (a real visual upgrade); offer custom art if they want.
  - **(2) Attachments: force "open with" chooser + cache.** `open_filex` opened the DEFAULT app
    directly (a default was set). NEW native `MethodChannel('aun/openwith')` in **MainActivity.kt** does
    `Intent.createChooser(ACTION_VIEW)` via a dedicated **FileProvider** (`${applicationId}.aunfp`,
    manifest `<provider>` + `res/xml/aun_file_paths.xml`), so the chooser ALWAYS shows; falls back to
    `OpenFilex.open` if the channel throws. **Download CACHE**: stable filename `t{number}_{ref}_{name}`
    under `BaseDirectory.applicationSupport/aun-ticket-files`; `downloadTicketAttachment` skips the
    download when `File(path).exists()` → never re-downloads. ⚠️ **First build with native
    MainActivity/FileProvider changes** (no new pub plugin though).
  - **(3) Description field is firmware-only** in Add Content (`data-types="firmware"`) — hidden for
    manual/video (their existing descriptions are preserved on save via the hidden editor).
  - **(4) 'Tip' COMPLETELY removed** (user confirmed none exist). Gone: Dashboard "Tips Published"
    stat, content-admin `type_meta`/`counts`/retired-chip/edit-dropdown handling (save default →
    firmware), the app **Support-tab FAQ/tips section** (+ its `contentList(type:'tip')` load),
    `ContentItem` default type → 'firmware'. (The get_list type-filter fix from v1.24 stays.)
  - **(5) YouTube key reused from aun-tutorials.** `AUN_App_Content::youtube_api_key()` = our Settings
    key first, else the tutorials plugin's `aun_tut_api_key` option — enter it once. Works for videos on
    ANY channel (videos.list?id= reads public metadata). Admin field note updated. Bench-tested
    (fallback + own-key-wins).
  - Tests: R6 eval (YT fallback, no Tips stat/chip, desc firmware-only) pass; analyze clean; 3 widget
    tests pass; PHP lints clean.
  - ⚠️ Re-upload `aun-app-api.zip` + **rebuild the APK** (native MainActivity change + Dart). Bridge
    unchanged. `aun_tut_api_key` reused for YouTube dates.
- **v1.26.0** — (2026-07-24, plugin 1.26.0, app 1.23.0+27, DB v12) — 7-item round
  - **(1) Bundled illustrated avatar pack.** NEW dep **`flutter_svg ^2.0.10`** + 9 hand-authored SVGs in
    `assets/avatars/` (robot/alien/cat/monster/cool/ghost/panda/dino/star). Picked via profile editor
    "Fun avatars"; stored as token `pack:NAME` in the avatar-emoji field (no backend change). `Avatar`
    renders `pack:` → `PackAvatarImage` (SvgPicture). `kAvatarPack`/`kAvatarPackPrefix` in widgets.dart.
    (Emoji-on-colour set from v1.25 stays too.)
  - **(2) [answered] ZIP opening directly = normal.** `Intent.createChooser` still shows a picker, but if
    the phone has only ONE app that handles that MIME it opens straight there. Not a bug.
  - **(3) Attachment cached indicator.** `_attachmentChip` now shows a green "Saved · tap to open" +
    open-in icon when the file is already on disk (checked via `ticketAttachmentFile().exists()` on
    thread load + after a download), vs "Tap to download" + download icon. Shared `_ticketFileName`
    helper. New l10n ticketSaved/ticketTapToDownload.
  - **(4) Android notification channels.** MainActivity.kt creates categorised channels (aun_support/
    aun_repairs/aun_content/aun_reminders/aun_default) so users get per-type controls in Android
    Settings + the system organiser groups them. Server `AUN_App_Push::channel_for_type()` sets the
    channel_id per push type (ticket→support, repair→repairs, content types→content, maintenance→
    reminders). Bench-tested the mapping. (Channels are created on app open — fine, login is required.)
  - **(5) Real YouTube title.** `youtube_published_at` refactored to `youtube_meta()` (title+publishedAt
    in ONE cached Data-API call). Payload gains `youtube_title`; app `ContentItem.youtubeTitle` +
    `displayTitle` getter used on video cards/detail/tiles (falls back to admin title). Bench-tested.
  - **(6) TMDB limit** `GLOBAL_LIMIT` 6 → **15** (trending page has 20; OTT filter trims). Bench-verified.
  - **(7) Bug-report screenshot + admin-bar counter.** DB v12 `aun_app_feedback.image_url`. `/feedback`
    accepts an `image` (base64/data-URI, images only) → `AUN_App_Content::store_feedback_image()`
    validates via `getimagesizefromstring` + stores in the media library (returns '' on any problem so
    the report never fails). App Settings "Report a problem" sheet gained an "Attach a screenshot"
    picker (image_picker, single, ≤6MB). Admin Bug Reports page shows a tappable thumbnail. NEW live
    **admin-bar badge** (`aun_app_api_admin_bar_counter`, front+back end, count of status='new', cached
    60s, cache busted on new report) → one-click to the Bug Reports page. Bench-tested (image store,
    image_url column). Admin resolve/start/reopen/delete already existed.
  - Tests: R7 eval (channels, feedback image + column, GLOBAL_LIMIT) pass; v8 73/0, v9 14/0, v10 13/0;
    analyze clean; 3 widget tests pass; PHP lints clean.
  - ⚠️ Re-upload `aun-app-api.zip` (DB v12 migration) + **rebuild the APK** — NEW native dep
    `flutter_svg` (first Gradle build) + MainActivity notification-channel change + Dart. Bridge unchanged.
- **v1.27.0** — (2026-07-24, plugin 1.27.0, app 1.24.0+28, DB stays v12) — 5-item feedback round
  - **(1) "Fun avatars" showed a raw string, no image → REMOVED the separate menu; folded into the
    emoji picker** (user's call: only a handful, no need for a second menu). Dropped `flutter_svg` dep
    + the 9 `assets/avatars/*.svg` + `PackAvatarImage`/`kAvatarPack`. The emoji set gained character
    glyphs (🤖👽👾👻🦕⭐ etc.). Legacy migration: any stored `pack:NAME` token is mapped to its emoji
    via `resolveAvatarEmoji()` in widgets.dart so existing users never see "pack:robot". ⚠️ removing a
    native dep changes the build (should be clean — one fewer plugin).
  - **(2) OneDrive/SharePoint download — REAL fix, live-verified against the user's firmware link.**
    Root cause proven by curling the actual share URL (`bdsmartliving-my.sharepoint.com/:u:/g/…`): the
    `?download=1` link 302-**chains through 4 redirects** and the final file host needs a guest cookie
    that Android's downloader drops across redirects → it returned a **200 `text/html` 56 KB page**
    (the "download" the user saw). Also proven: `api.onedrive.com/v1.0/shares/…` returns **"308 User
    migrated"** for their tenant (that consumer API no longer serves migrated SharePoint/OneDrive-for-
    Business accounts) — so my first attempt this round would NOT have worked. The winner (curled: HTTP
    206, `application/octet-stream`, correct `Content-Disposition` filename, **0 redirects, no
    cookies**, Range-resumable, 732 MB) is the site's **`download.aspx?share={shareid}`**:
    `AUN_App_Content::sharepoint_share_download()` parses `/:u:/g/personal/USER/SHAREID` →
    `https://HOST/personal/USER/_layouts/15/download.aspx?share=SHAREID` (handles `/personal/…` and
    `/sites/…`; already-direct `download.aspx` links + non-cloud URLs pass through). Consumer OneDrive
    (`onedrive.live.com`/`1drv.ms`, NOT migrated) keeps the `api.onedrive.com` shares method. ⚠️ still
    requires "Anyone with the link" sharing. ⚠️ the firmware is a **`.rar`** — Android needs a RAR
    extractor app; a `.zip` would open natively (worth telling the user).
  - **(3) First launch respects the device language.** `app_state.dart` init(): when nothing is saved,
    read `WidgetsBinding.instance.platformDispatcher.locale` — a Bangla phone opens in Bangla, every
    other language opens in English (was hardcoded `bn`). Manual Settings toggle unchanged. Smoke tests
    updated to set `platformDispatcher.localeTestValue` (they also now cover item 3).
  - **(4) TMDB pick → in-app detail screen.** Backend `class-aun-app-watch.php`: global picks now carry
    `overview`/`backdrop`/`year`/`rating` (free from the trending row) + `trailer` (a YouTube key from
    TMDB `/videos`, one lookup per served title). New `WatchDetailScreen` (`watch_detail_screen.dart`):
    cinematic header playing the trailer IN-APP (reuses the site-hosted `/embed?v=` webview the video
    content screens use; falls back to poster + "Watch on YouTube"), synopsis, year/rating/platform
    chips, and a pinned **"Watch on {platform}"** button → the streaming service. `WatchPick` gained the
    fields + `hasDetail`. Local (admin) picks have no TMDB metadata → they still open their link
    directly. Bench-verified the payload shape.
  - **(5) Offline / server-down UX (leading-app pattern).** NEW dep **`connectivity_plus ^6.1`** (first
    Gradle build). `AppState` watches connectivity (fire-and-forget, never blocks boot) → `offline`
    ValueNotifier drives a slim global **`OfflineBanner`** at the top of the Home shell (auto-hides when
    back); on reconnect it bumps the Home refresh signal so tabs silently re-fetch. `errorText()` now
    distinguishes **server-down** (5xx / bad_response / timeout → `errServerDown` "can't reach our
    servers") from **no-internet** (`errOffline`), so the customer doesn't blame their wifi for a server
    outage. Existing per-screen retry EmptyStates (devices tab etc.) already cover the reload path;
    session + config stay cached offline (unchanged).
  - **(5b) Delightful projector animations for empty/error states** (user asked for "funny" animations
    like other apps, brainstorm-then-build). NEW `lib/src/ui/projector_animations.dart` — a hand-drawn
    `CustomPaint` projector (body + blinking blue lens-eye + beam + status LED projecting onto a screen),
    3 moods: **offline** (beam flickers, screen shows Wi-Fi arcs failing + amber no-signal slash, amber
    LED), **serverDown** (beam steady-dim, a rotating gear + tiny counter-rotating helper gear, red LED),
    **empty** (energetic bob, a pulsing "+" with twinkling sparkles, green LED). One `AnimationController`,
    RepaintBoundary, reduced-motion aware (parks on a static frame). `ProjectorStateView` (art + title +
    subtitle + action) replaced the plain-icon EmptyState on the **My Devices** tab: no-devices → empty
    mood; load error → offline vs serverDown by error code, with new copy (offlineTitle/Hint,
    serverDownTitle/Hint). **Preview artifact** (same canvas math) published so the look is judgeable
    without an APK build. Answer to "how do leading apps handle it": persistent offline indicator, cached
    last-known UI, clear retry, auto-recovery on reconnect, honest copy separating "offline" from "down",
    + a bit of on-brand delight.
  - Tests: `flutter analyze` clean, 3 widget tests pass (updated for device-locale), gen-l10n ran, AOT
    via user build. Backend: OneDrive transform **live-verified against the real firmware link** + local-
    pick shape bench-verified; PHP lints clean.
  - ⚠️ Re-upload `aun-app-api.zip` (plugin 1.27.0; DB stays v12) + **rebuild the APK** — native dep set
    changed: **+connectivity_plus**, **−flutter_svg**. Bridge unchanged.
- **v1.28.0** — (2026-07-25, plugin 1.28.0, app 1.25.0+29, DB v13) — 5-item round
  - **(1) OneDrive download — the real fix, using WP File Download's connector.** Firmware/manual payload
    `url` now points at a NEW public redirect endpoint **`GET /content/{id}/file`** which resolves the real
    download URL server-side and 302s to it — the app hits OUR domain, and OneDrive is fixed server-side
    (works with the CURRENTLY-installed app, no rebuild needed for the download itself). Resolver
    (`AUN_App_Content::resolve_download_url`): OneDrive/SharePoint → **authenticated Microsoft Graph
    downloadUrl via the OAuth token WP File Download's OneDrive-Business connector holds** (`wpfd_onedrive_token`
    reads `_wpfdAddon_onedrive_business_config['state']['token']['data']['access_token']`; `graph_download_url`
    → `/shares/u!{b64}/driveItem?$select=@microsoft.graph.downloadUrl`, 20-min cache) → falls back to the
    anonymous `download.aspx?share={id}` transform. ⚠️ curl-PROVED `download.aspx` works with any UA / no
    cookies / no redirects for both the old and the new links — past app failures were the raw share link
    (stale `?download=1` HTML) reaching the app, now bypassed. NEW admin per-item **"Downloadable in app"**
    toggle (`aun_app_content.app_downloadable`, DB v13; app hides the button + shows a website note when off)
    + a **"Test download from server"** button (runs `verify_download` → live-checks a real file, not HTML;
    admin verifies from the panel, not the app). Bench-verified incl. a LIVE SharePoint fetch.
  - **(2) State animations given a brain.** Home hands the whole screen to the offline/serverDown buddy when
    the first load can't reach the server (self-heals on reconnect). Login shows the offline buddy on
    first-open-with-no-internet. Spare-parts + Send-repair with no projector → the empty buddy + "Register
    now". (Devices tab already had them.) `_applyConnectivity` now also `notifyListeners()` so login/home
    react to connectivity.
  - **(3) OTP auto-submits** the moment `otp_length` digits are typed (manual entry too), with a `_busy`
    double-submit guard.
  - **(4) Profile avatar was 30s–1min → now INSTANT.** Optimistic local update (emoji/remove instant; picked
    photo shown from its local file path) + background persist that reverts on failure. `UserProfile.copyWith`
    + `Avatar` renders local file paths. (Server /me handler was already light.)
  - **(5) Snackbars readable at night** — theme.dart sets an explicit dark snackbar background in both themes
    (was white text on Material's default light-in-dark inverseSurface).
  - Tests: `flutter analyze` clean, 7 widget tests pass, download resolver bench-verified (live fetch), PHP
    lints clean. ⚠️ Re-upload `aun-app-api.zip` (DB v13 migration) + **rebuild the APK** (no new native
    plugins). Bridge unchanged.

- **v1.29.0** — (2026-07-25, plugin 1.29.0, app 1.26.0+30, DB stays v13) — 6-item round.
  **Firmware download confirmed working by the user.**
  - **(4) ⚠️ REGRESSION FIXED — the manual "Read" button downloaded instead of opening the in-app
    reader.** v1.28 pointed manual/firmware `url` at the extensionless redirect `/content/{id}/file`,
    but `openContent()` tested `url.contains('.pdf')` → never matched → `launchExternal` (download).
    Server now sends **`file_ext`** (from the ORIGINAL stored URL, `AUN_App_Content::file_extension()`,
    string-only, no network); the app routes on `ContentItem.isReadablePdf`, never the URL. The PDF
    viewer also re-fetches when its cached temp file isn't really a PDF and errors clearly on a
    non-PDF body. LESSON: never infer file type from a URL the server may rewrite.
  - **(2)/(8) WP File Download dependency removed.** `onedrive_token()` now prefers OUR OWN creds
    (Settings → OneDrive: client id/secret/tenant/refresh token; `own_onedrive_token()` mints at
    login.microsoftonline.com, supports refresh_token AND client_credentials, caches to expiry−120s,
    persists rotated refresh tokens); WPFD's token is an OPTIONAL fallback; then the anonymous
    `download.aspx?share=` public link. Removing WPFD can't break downloads. Settings card shows a
    live 3-state status + Azure setup steps.
  - **(1) "Test download" is inline AJAX** (`wp_ajax_aun_app_test_download`, nonce + CAP): no reload,
    spinner → colour-coded result directly below the button, and it tests the URL **currently in the
    form** (verify before saving). Top-of-page notice removed.
  - **(3) Paused downloads show Resume + Cancel in-app.** `_pause`/`_resume` flip state optimistically
    (the native callback can lag or never arrive); paused UI = full-width Resume + Cancel; a failed
    pause reverts to running with a "can't be paused" message.
  - **(5) OTP = dynamic boxed field** (`lib/src/ui/otp_field.dart`): exactly `otp_length` boxes,
    hidden TextField keeps SMS auto-fill/paste working, animated fill, auto-verify on last digit.
  - **(6) Add-device keyboard fix**: ScrollController + GlobalKey anchors; opening/tapping
    "type the number" or "find by phone" runs `Scrollable.ensureVisible(alignment:1)` twice (layout,
    then ~260ms later once the keyboard settles) + ListView bottom padding = keyboard inset.
  - Tests: analyze clean, 7 widget tests, backend bench-verified (incl. a live SharePoint fetch),
    PHP lints clean. ⚠️ Re-upload `aun-app-api.zip` + **rebuild the APK** (no new native plugins).
- **v1.30.0** — (2026-07-25, app 1.27.0+31, plugin unchanged 1.29.0) — 5-item APP-ONLY round
  - **(3) ⚠️ BUG FIXED — profile always showed the PREVIOUS emoji.** Race from the optimistic-avatar
    work: picking an emoji fires a background `POST /me {avatar_emoji}`, but **Save** fires its own
    `POST /me {name,email}` whose response carries the avatar the server had at that instant — landing
    first, it restored the old one. `_applyAvatar` now stores its future in `_avatarSave` and `_save()`
    **awaits it first**. LESSON: an endpoint returning the whole entity can clobber a concurrent
    optimistic update — sequence them.
  - **(2) Login video never loaded after the network returned.** Booting offline leaves `config == null`
    (so `login_video` is empty forever — nothing re-fetched /config) and `_prepare()` never retried.
    `AppState._applyConnectivity` now `refreshConfig()`s on reconnect (also restores support numbers /
    version gates after an offline boot); the video widget re-prepares on `didUpdateWidget` and retries
    on app resume, guarded by `_working`.
  - **(4) Scroll affordance (IMDb-style).** Global, not per-screen: new `AunScrollBehavior` wraps every
    VERTICAL scrollable in a `Scrollbar` (horizontal poster/video rails stay clean) via
    `MaterialApp.scrollBehavior`, plus a theme-aware `scrollbarTheme` (4px, rounded, fades in while
    scrolling, draggable, brighter on dark). Home + Support + every list at once.
  - **(5) Settings version was WRONG** — hardcoded `'1.10.0'` in two places while the app was 1.26.x.
    Now `AppState.versionName` (PackageInfo) + `versionLabel` → "1.27.0 (31)", used by Settings and bug
    reports.
  - **(1) Welcome screen refreshed + alive.** Content was stale (mentioned "tips" — removed in v1.25;
    led with WhatsApp — tickets are primary since v1.15; never mentioned auto-find-by-phone,
    notifications or what-to-watch). Now 5 slides: welcome → add in seconds (scan **or auto-found from
    your mobile number**) → everything for your exact model → help that follows through (tickets with
    in-app replies, parts, repair w/ live tracking) → never miss what matters (push + what to watch).
    Art upgraded to ONE shared 12s ticker: breathing halo, truly orbiting satellites (elliptical +
    per-icon counter-float), floating hero, staggered fade+rise entrance replayed per slide, per-slide
    accent colour crossfading the page wash/dots/CTA. Reduced-motion aware.
  - Tests: analyze clean, 7 widget tests pass. ⚠️ **APP-ONLY — rebuild the APK; no plugin re-upload
    needed** (plugin stays 1.29.0).
- **v1.31.0** — (2026-07-25, app 1.28.0+32, plugin unchanged) — 3 follow-up fixes
  - **(1) Onboarding loop bug.** Satellites used `spin * .5` = half a revolution per 12s loop → they
    snapped back 180° at the boundary (the "stops and restarts"). Now exactly ONE revolution per loop;
    every other term already completed whole cycles, so the scene is seamless. RULE: every ambient term
    must complete an integer number of cycles over the controller period.
  - **(1b) Onboarding colours → brand.** Violet/amber read as another product's palette; onboarding now
    stays in the AUN blue family, varying only in depth (sky #35A7FF / brand #0188FE / deep #0A63C9 /
    navy #08498F). Green kept only where semantic (scan/verify).
  - **(2) Add-device keyboard overlap — the v1.29 scroll fix did NOT work; replaced.** Scaffold consumes
    the bottom inset (so the extra padding was ~0) and the anchor scroll landed short. Now choosing
    "type the number" / "find by phone" REPLACES the option list with a single focused task (header +
    back arrow, one field, helper, action) via AnimatedSwitcher — with the other options gone it fits
    above the keypad by construction. Back returns to the options first. LESSON: remove the content that
    can be covered rather than trying to out-scroll the keyboard.
  - **(3) Scrollbar missing on Home/Support — root cause.** Those ListViews had no controller →
    `primary: true` → all four IndexedStack tabs shared the PrimaryScrollController, so `Scrollbar`
    couldn't attach and painted nothing (standalone routes like Buy spare parts have one scrollable, so
    they worked). Gave HomeTab (both lists), SupportTab and DevicesTab (all 3 lists) their own
    controllers. The global scroll behaviour/theme from v1.30 were already correct.
  - Tests: analyze clean, 7 widget tests pass. ⚠️ APP-ONLY — rebuild the APK.
- **v1.32.0** — (2026-07-25, app 1.29.0+33, plugin 1.30.0, DB stays v13) — 2 follow-ups
  - **(1) Add-device inputs → keyboard-aware BOTTOM SHEET.** The v1.31 "focused view" (which replaced
    the whole option list) wasn't liked. Now the three option cards STAY put and "type the number" /
    "find by phone" open a modal sheet with `isScrollControlled: true` and bottom padding =
    `MediaQuery.viewInsetsOf(sheetContext).bottom`, so the sheet sits exactly on top of the keypad —
    field, helper and CTA always visible, swipe to dismiss, context kept behind. One shared
    `_showInputSheet()` builds both. Removed `_FocusedInput`, the mode flags and the PopScope branch.
    ⭐ PATTERN: for a short input on mobile, a keyboard-aware modal sheet beats inline-expand (gets
    covered) and screen-replacement (loses context).
  - **(2) TMDB rail was hardcoded to 15 → admin setting + multi-page.** Default 15→30, new `MAX_LIMIT`
    100 / `MAX_PAGES` 8, new **watch_limit** setting (Settings → What to Watch → "How many titles")
    read via `AUN_App_Watch::global_limit()`. `global_picks()` now walks trending PAGES (20/title page)
    until the limit is filled — previously it only ever read page 1, so it could never exceed ~15–20.
    Local picks still come first and are additional. Each served title costs 2 TMDB lookups on the
    12-hourly rebuild, hence the ceiling.
  - Tests: analyze clean, 7 widget tests, watch-limit bench-verified. ⚠️ Re-upload `aun-app-api.zip`
    + rebuild the APK.
- **v1.33.0** — (2026-07-25, app 1.30.0+34, plugin 1.31.0, DB stays v13) — 3 fixes
  - **(1) Device-card Hero bug.** DeviceCard (My Devices), the Home carousel card and DeviceDetailScreen
    all used `device-{source}-{id}`, and Home + My Devices are mounted together in the IndexedStack — so
    opening a device from Home let the flight bind to the OFFSTAGE tab card ("mixed up" cards, direction
    varying per device). Tags are now unique per ORIGIN and passed explicitly (`device-home-…` vs
    `device-list-…`); `DeviceDetailScreen({heroTag})` uses exactly what it was given. ⭐ RULE: with
    simultaneously-mounted tabs a Hero tag must be unique per origin, not just per data id.
  - **(2) Links in rich-text descriptions did nothing** — those HtmlWidgets had no `onTapUrl`. New shared
    `openDescriptionUrl()`: YouTube links play in the app's own player (synthetic ContentItem), anything
    else opens externally. Wired into all three description sites.
  - **(3) Pending-registration loophole closed.** `owns_serial()` (the server gate for parts/repair)
    accepted registrations of ANY status; now requires `status = 'approved'` (direct ERP links still
    pass). App pickers in parts/repair/tickets filter to `isApproved`. Content stays public (as on the
    website) and general tickets still work. Bench-verified pending ✗ / approved ✓ / rejected ✗.
  - Tests: analyze clean, 7 widget tests, loophole bench-verified. ⚠️ Re-upload `aun-app-api.zip` +
    rebuild the APK.
- **v1.34.0** — (2026-07-25, app 1.31.0+35, plugin unchanged) — the projector animation language, app-wide
  - **`ProjectorMood` 3 → 10**, all the SAME V1 projector; only what it projects + its mood change:
    offline (wifi fail), serverDown (gear), empty ("+"), **noTickets** (speech bubble, typing dots),
    **noRequests** (clipboard, ticks appear), **notFound** (searching "?" + orbiting spark),
    **pending** (hourglass: sand drains, then flips), **noPurchase** (magnifier sweeping a phone),
    **noNotifications** (ringing bell), **noContent** (film strip, scrolling sprockets). Eye grouped by
    mood (nervous / sleepy / scanning / waiting / bright), LED green-amber-red by severity.
  - **NEW `ProjectorErrorView(error, onRetry)`** — one widget for every failed load; picks offline vs
    serverDown from the error code. Replaced every `EmptyState(icon: wifi_off…)`: tickets list/thread/
    new-ticket, my requests, notifications, help tab, device detail, request detail.
  - Wired: support-tickets empty → noTickets; my-service-requests empty → noRequests; add-device
    "serial not found" → notFound (new optional `mood:` on the result shell, replaces ❓); register
    "registration received" → pending (approved keeps 🎉); find-by-phone "no purchase found" →
    noPurchase; help-tab no content → noContent; notifications empty → noNotifications.
  - New l10n `noRequestsHint` (en+bn). Tests: analyze clean, **14** widget tests (the paint test loops
    `ProjectorMood.values`, so every new mood is covered automatically). ⚠️ APP-ONLY — rebuild the APK.
- **v1.35.0** — (2026-07-25, app 1.32.0+36, plugin unchanged) — 3 items
  - **(1) Squashed animation in the registration dialog.** `AlertDialog`'s `icon` slot passes a wide
    tight constraint and the painter scaled X/Y independently → vertical squeeze. Now the widget wraps
    in `Center` AND the painter scales uniformly (`min(w,h)/_vb`, centred), so it can't be distorted by
    any parent.
  - **(2) Light-theme scrollbar barely visible** — thumb was `onSurface @ .22` on near-white. Now slate
    `#334155` @ .48 idle / .70 dragged (dark .34/.60), thickness 4→5.
  - **(3) Notification opt-in prompt with a brain** — `services/notification_prompt.dart` +
    `screens/notification_optin.dart`. Never triggers the OS dialog cold (our explainer first —
    Android 13+ can't re-ask after a hard denial); never on first launch; only when invested (≥1 device
    OR ≥3 opens); only at a natural pause (after Home's list loads); once per app run; "Not now" backs
    off 14d → 45d → never (max 3); stops permanently once the OS dialog is answered. Sheet uses our
    `noNotifications` projector + 3 concrete benefits. `AppState.launches` added; `_granted()` fails
    safe with a `@visibleForTesting` seam. NEW notification_prompt_test.dart (5 tests).
  - Tests: analyze clean, **19** widget tests. ⚠️ APP-ONLY — rebuild the APK.
- **v1.36.0** — (2026-07-25, app 1.33.0+37, plugin unchanged) — 4 items
  - **(4) ⚠️ Loop bug again — 17 non-integer phase multipliers** in the V1 art (gear at `phase*0.5` =
    half a turn per loop, beams at 1.5/1.6, blinks at 3.3/1.1, sparkle orbits at 1.4/2.4…). All rounded
    to integers; the film-strip shift went from `(phase*6)%12` to `(phase/2π*24)%12`. ⭐ STANDING RULE:
    every `sin/cos(phase * k)` needs an INTEGER k (and modulo periods must divide evenly) or it snaps
    at the loop point. Check with `grep -oE "phase \* [0-9]+\.[0-9]+"` — must be empty.
  - **(1) Video rail was hardcoded `take(8)`** so extra videos were unreachable (worse with several
    projectors). Home now keeps them all, previews 8, and shows **"See all"** only when there's more →
    NEW `video_library_screen.dart`: everything **grouped by projector model**, general guides last,
    full-width 16:9 cards. New l10n `videoGeneralGroup`.
  - **(2) Opt-in "Turn on" did nothing** when notifications were already off — `Permission.request()`
    only shows the dialog the first time. `accepted()` now returns `NotifOptInResult`
    (enabled/openedSettings/denied) and falls back to `openAppSettings()`. Since that returns when
    Settings merely OPENS, the UI no longer claims success/failure — it shows the new
    `notifOpenSettings` hint.
  - **(3) Updater prompts animated**: new `ProjectorMood.update` (breathing download arrow into a tray,
    one full breath per loop) on BOTH the Settings update dialog and ForceUpdateScreen.
  - Tests: analyze clean, **20** widget tests. ⚠️ APP-ONLY — rebuild the APK.
- **v1.37.0** — (2026-07-26, app 1.34.0+38, plugin 1.32.0, DB stays v13) — 5 items
  - **(1) Launch felt slow — two root causes.** SERVER: `/watch` built the picks list inside the
    customer's request (~2 TMDB calls per title + trending pages = 60+ calls, minutes) → app timed out,
    hence "TMDB appears after a few minutes". Now **stale-while-revalidate**: persistent option
    `aun_app_watch_store` served instantly even when stale, background `aun_app_watch_refresh` single
    event + `twicedaily` `aun_app_watch_warm` cron, `FIRST_FILL`=6 for the very first call, and a failed
    refresh never overwrites (an outage can't empty the rail). ⭐ RULE: never build expensive
    third-party data in a user request. APP: three sequential awaits on Home → one `Future.wait`; and
    the add-projector hero was shown while `_devices == null` (loading treated as empty) — that was the
    flash-then-refresh. Now a `_DeviceRailSkeleton` covers the loading state.
  - **(2) One motion language app-wide** — new `ui/motion.dart`: `AunPageTransitionsBuilder` (spring
    rise + 97→100% scale + fade in, page behind settles back) for every route, `TabFadeThrough` for
    bottom-nav switches, `RailItemMotion` for horizontal rails, `AunMotion` duration/curve tokens.
    Reduced-motion aware throughout.
  - **(3) Device-card entrance differed per card** — a running `FadeSlideIn` transform sat above each
    Hero, and a Hero measures its current rect, so flights started from different places. Now a
    transform-free uniform `_FadeIn`. ⭐ RULE: never put a running transform above a Hero.
  - **(4)** TMDB poster rail got `RailItemMotion` (scale/opacity falloff toward the edges while swiping).
  - **(5)** Profile Save is disabled while the name is empty (+ inline `nameRequired` hint) — an empty
    name was dropped by the API, so it "saved" without changing anything.
  - Tests: analyze clean, 20 widget tests, 13 new watch-SWR bench checks. ⚠️ Re-upload
    `aun-app-api.zip` + rebuild the APK.
- **v1.38.0** — (2026-07-30, app 1.35.0+39, plugin unchanged) — APP-ONLY: the tab-switch reload
  disaster, one rail language, device transitions, content/route sync
  - **(1) ⚠️ SEVERE REGRESSION FIXED — every tab switch re-created and re-fetched all four tabs.**
    Two compounding causes, both introduced in v1.37:
    (a) `TabFadeThrough` wrapped the IndexedStack in `AnimatedSwitcher(child: KeyedSubtree(key:
    ValueKey(tabIndex)))`. A changed key means a NEW child, so Flutter built a fresh element subtree
    and disposed the old one — **all four tab States destroyed and rebuilt on every tap** (initState →
    full re-fetch, scroll positions lost, entrance animations replayed = the "it refreshes then loads"
    the user saw).
    (b) The shell bumped ONE shared `_refreshSignal` on every switch, so even the tabs the customer
    wasn't looking at re-fetched. HomeTab alone fired **six** requests per tap — devices, videos,
    notifications, service requests (hits the **ERP**), tickets (hits **osTicket** through the bridge),
    watch — plus DevicesTab's own devices call. Switching fast stacked those into a request storm on
    shared hosting → 25 s timeouts → the "server down" screen.
    THE FIX, three parts:
    • **`FadeThroughStack`** (`ui/motion.dart`) replaces TabFadeThrough: the IndexedStack widget is
    stable and only its *index* changes, so state/scroll/data survive; a Material fade-through
    (out 90 ms → swap at the midpoint → in 200 ms + 98.5→100 % scale) plays on top. ⭐ RULE: never key
    a persistent stack into an AnimatedSwitcher. ⚠️ Second-order trap hit while building it: returning
    a bare `child` at rest and a wrapped one while animating ALSO changes the tree shape and destroys
    the stack — the wrappers must be present every frame (identity opacity/scale at rest). The new
    widget test caught exactly this.
    • **Per-tab refresh signals** — `List<ValueNotifier<int>>`, only the selected tab's is bumped
    (also on resume/reconnect).
    • **Shared data layer** `lib/src/services/store.dart`: `Cached<T>` (last good value + TTL +
    single in-flight request shared by all callers + `set/invalidate/clear`) and `AppData` on
    `AppState` holding devices (90 s), videos (10 min), notices (60 s), service requests (90 s),
    tickets (90 s), watch (6 h). Screens read the cache instead of the API, so **one** device fetch
    serves Home, My Devices, Book a repair, Buy spare parts and the ticket form; a tab switch inside
    the TTL costs **zero requests**. ⭐ RULE: **a failed refresh never replaces good data** — the
    cached list stays and the error is only shown when there is nothing at all (that was the other
    half of the phantom "server down"). Cleared on login/logout so a shared phone can't leak data.
    • Also: `ApiClient` now holds **one `http.Client`** instead of `http.get()`/`http.post()`, which
    build a throwaway client (fresh DNS+TCP+TLS, ~0.5–1 s on mobile) per call; multipart uploads go
    through it too.
    • ⚠️ Two traps avoided and commented in code: a cache listener must NOT itself fetch (a failing
    fetch would notify → fetch → fail forever — the status strip now separates `_load` from
    `_rebuild`); and `dispose()` must never look up an inherited widget (asserts on a defunct
    element — every screen now captures `AppData`/`Cached` in a `late final` field). The pre-existing
    `AppScope.read(context)` in `_HomeScreenState.dispose()` was fixed the same way.
  - **(2) ONE rail language app-wide.** The "what to watch" rail used scroll-linked `RailItemMotion`
    while **video guides** used a staggered `FadeSlideIn(delayMs: 60*i)` entrance — two different
    languages side by side on the same screen. Video guides and the **My projectors** carousel now use
    `RailItemMotion` too (correct `itemExtent` per rail). Vertical lists went the other way: the
    per-row stagger is gone from device content, tickets and notifications — the screen-level reveal
    carries the list in as one piece. RULE: **rails = scroll-linked motion; lists = one uniform fade;
    page sections = the one-time entrance stagger.**
  - **(3) Device card transitions differed per device — real, now gone.** Not random: a Hero flight is
    measured from wherever the card happens to sit, and the two ends have different shapes (a card with
    a warranty bar vs one under review, list card vs detail card), so device 1 got a short shrink and
    device 3 a long grow. Third Hero bug on these cards in three rounds (v1.33 tags, v1.37 transform).
    **The whole-card Hero is removed** from DeviceCard, the Home mini card and DeviceDetailScreen
    (`heroTag` params deleted); every device now opens with the one shared page transition, identical
    from every screen. Photo→fullscreen Heroes stay — a constant-shape flight is consistent by nature.
  - **(4) Content now arrives WITH the route, not after it.** Two fixes:
    • The real cause of "Book a repair → seconds of spinner → *pop* add your projector first" was a
    fresh `myDevices()` per screen. Repair, spare parts, tickets and my-requests now read the shared
    cache, so the answer is usually there on the first frame of the page transition.
    • New **`AunReveal`** (`ui/motion.dart`): one cross-fade (+3.5 % rise, same tokens as the route
    transition) for every screen whose content lands late, replacing hard swaps. Wired into My Devices,
    device detail, repair, spare parts, tickets and my-requests, each with a **layout-shaped skeleton**
    (`_ServiceFormSkeleton`, `_TicketListSkeleton`, `_ContentSkeleton`) instead of a centred spinner,
    so nothing jumps when data lands. A cold service screen also shows the offline/server-down buddy
    instead of falsely claiming the customer owns no projector.
  - Tests: NEW `test/store_test.dart` (8: TTL, shared in-flight request, force, **failed refresh keeps
    good data**, recovery, stale re-fetch + notify, invalidate, clear) and `test/tab_state_test.dart`
    (2: tabs survive switching — the regression guard — and offstage tabs aren't hit-testable).
    **30 widget/unit tests pass**, `flutter analyze` clean, AOT release compile OK.
  - ⚠️ **APP-ONLY — rebuild the APK; no plugin re-upload, no bridge upload** (plugin stays 1.32.0,
    DB v13). No new native plugins → builds exactly like v1.37.
- **v1.39.0** — (2026-07-30, app 1.36.0+40, plugin 1.33.0, DB stays v13) — 6-item round
  - **(1) Route transition rebuilt on Material 3 Expressive MOTION PHYSICS.** The old one was
    curve-based (`easeOutBack` rise + fade). M3 Expressive replaced duration+easing with springs,
    split in two: **spatial** springs move things (position/scale/corners) and may overshoot;
    **effects** springs drive colour/opacity and are critically damped — a fade that wobbles reads
    as a rendering bug. New `SpringCurve` (real `SpringSimulation`, normalised over its settle time)
    + a token set in `AunMotion`: spatial fast/default/slow (`stiffness` 800/380/200, ζ .75/.82/.85)
    and effects fast/default/slow (3800/1600/800, ζ 1.0). `AunPageTransitionsBuilder` now enters
    from the trailing edge on the spatial spring while the covered page **recedes exactly like
    Android's predictive-back preview** (scale 92%, 24 px drift, 25% dim, 28 px corner rounding) —
    so a tap-navigation and a back-swipe finally produce the same shape. ⭐ RULE: opacity NEVER
    rides an overshooting spring. Preview: `aun-app-transition-preview.html` (old vs new, real
    spring maths in JS, 0.25× speed + curve graphs) so it's judgeable without an APK.
  - **(2) Downloads now read like a download manager**: "128 MB of 732 MB · 3.4 MB/s · 3m 20s left"
    under the bar, from `TaskProgressUpdate`'s `expectedFileSize` / `networkSpeed` / `timeRemaining`
    (each shown only when the plugin flags it valid, so a server with no Content-Length never shows
    "of ?"). Bytes formatted GB/MB/kB, tabular figures so digits don't jitter. New l10n
    `downloadOfSize` / `downloadTimeLeft`.
  - **(3) Spare-parts QUANTITY, matching the website.** The site has had qty for a while (items
    table `qty`, 1..`aun_sp_max_qty` = 5, quote = unit × qty) but the app wrote nothing → every app
    order was silently 1 of each. Now: `/parts/catalog` returns **`max_qty` read from the website's
    own `AUN_SP_Form::max_qty()`** (filter-aware — never hardcoded in the app); the app shows a
    −/n/+ stepper on each ticked part (hidden entirely if the site allows only 1); create sends
    `qty=key:n,key:n` and the server clamps to 1..max exactly like the site form; the created event
    log spells out "Remote ×2" for staff. Read side gains `qty` + **`line_total`** (price stays PER
    PIECE as the site quotes it) so a qty-3 line no longer shows one piece's price next to a 3×
    quote total. An older APK that sends no `qty` behaves exactly as before.
  - **(4) TMDB detail screen is now a real title page**: genres, runtime ("2h 14m" for a film,
    "3 seasons · 48m per episode" for a series), movie/series chip, tagline, an "About" synopsis and
    a **cast rail** (headshots + character names, `RailItemMotion` like every other rail). Backend
    `title_detail()` folds this in with **one** `append_to_response=videos,credits` call, REPLACING
    the old separate `/videos` call — so the richer screen costs the same 2 lookups per served title.
    Also added a `seen` guard so a title repeated across trending pages can't be paid for twice.
  - **(5) FAB bug — "New ticket" morphing into "Add device" then fading.** Every Flutter FAB shares
    ONE default hero tag, and the My Devices tab sits in the shell underneath pushed routes, so its
    FAB flew to/from the tickets FAB on every push/pop. Distinct `heroTag`s (`fab-devices`,
    `fab-tickets`). ⭐ RULE: give every FAB an explicit heroTag once a second one exists anywhere.
  - **(6) Profile stayed blank after an outage until an app restart.** `init()` swallows a failed
    `api.me()` to keep the session, and nothing ever re-fetched it — pull-to-refresh included.
    Added `AppState.refreshUser()`, called on reconnect, on Home's pull-to-refresh, and **on any
    Home load when `loggedIn && user == null`** — that last one matters because a SERVER outage
    (phone online, site down) fires no connectivity change at all, which is the case the user hit.
  - Tests: NEW `test-app-api-v11.php` (**37**: qty stored/defaulted/clamped/junk-proof, old-app
    compatibility, max_qty follows the site's filter, event log, qty+line_total read-back, TMDB
    genres/runtime/cast/trailer-preference/series formatting, and a **cost guard** asserting one
    details call per title and no `/videos` call). Regression: v8 **73/0**, v9 14/0, v10 13/0.
    App: `flutter analyze` clean, **35** tests (NEW `motion_test.dart` — 5, incl. "effects springs
    never overshoot"), gen-l10n ran, AOT release compile OK.
    ⚠️ v8's watch section was patched to `delete_option(AUN_App_Watch::STORE_KEY)` alongside the
    transient — it predated the v1.37 stale-while-revalidate store, so it was serving a stale store
    and would have failed on its own. Test bug, not a product bug (proved with a focused probe).
  - ⚠️ Re-upload **`aun-app-api.zip`** (plugin 1.33.0, no DB change) **and rebuild the APK**. No new
    native plugins → builds like v1.38. Bridge unchanged.
- **v1.40.0** — (2026-07-30, app 1.37.0+41, plugin unchanged) — APP-ONLY: one failure rule app-wide
  - **Bug: a server outage showed the "no internet" projector on Home and "server down" on My
    Devices.** Two causes.
    (a) **`http.ClientException` escaped the API layer uncoded.** `_get`/`_post` caught only
    SocketException / HttpException / TimeoutException — but a server that dies mid-request throws
    `ClientException` ("Connection closed before full header was received"), which is NOT a
    SocketException. It reached the UI as a bare `Exception`, and each screen guessed: Home's rule
    said "not an ApiException → offline", My Devices' said "not code 'offline' → serverDown". Same
    error, two animations. `ApiClient._rethrowNetwork()` now maps EVERYTHING that leaves the client
    to a coded ApiException (new code **`network`** for a refused/dropped connection or TLS
    failure); all six catch sites (incl. the three multipart uploads) go through it.
    (b) **Three different classification rules** (Home, DevicesTab, ProjectorErrorView) plus a
    fourth in `errorText()`. Replaced by ONE: `lib/src/ui/failure.dart` → `classifyFailure()` /
    `failureFor(context, error)`. ⭐ THE RULE: **the phone decides, not the exception.** If Android
    reports a network, anything that failed is server-down — a refused connection, a dropped
    socket, a 502 and a timeout all mean "we're down", never "your internet is broken". Only when
    connectivity_plus reports no network do we blame the connection. New `AppState.connectivityKnown`
    marks whether that reading is real (false in widget tests / if the plugin is missing), and only
    then do we fall back to the exception's own code.
    Home now renders the shared `ProjectorErrorView` instead of hand-rolling the mood, so the art
    and the snackbar wording (`errorText`) can never contradict each other again.
  - Tests: NEW `test/failure_test.dart` (9) — server-down for every failure while online, offline
    for every failure while the phone is offline, unknown-connectivity fallback, and a guard that a
    transport failure always leaves the client as a *coded* ApiException. **44** app tests pass,
    analyze clean.
  - ⚠️ **APP-ONLY — rebuild the APK.** Plugin stays 1.33.0; no bridge change.
- **v1.41.0** — (2026-07-30, plugin 1.34.0, app 1.37.0+41, DB stays v13) — ACCOUNT DELETION
  - **Why**: Google Play requires in-app account deletion for any app with sign-in — a hard
    blocker for the Play Store submission, and the right thing anyway. Scope was the user's call:
    **delete the app account only, immediately, no grace period.**
  - **`class-aun-app-account.php`** → `AUN_App_Account::delete($user_id, $phone)`, ordered so the
    SESSION dies first (if anything later fails the phone still can't act as them):
    sessions + FCM tokens → app profile meta (+ the uploaded avatar attachment) → device links →
    dismissed-purchase ledger → notices + notice_state → bug reports → repairs **detached**
    (`user_id = 0`) but KEPT. `AUN_App_Account::summary()` holds the bilingual "what goes / what
    stays" text so the code and the app's confirm screen can't drift apart. Fires
    `aun_app_account_deleted`.
  - **KEPT on purpose**: warranty registrations (the customer's own proof of purchase — their
    warranty stays valid), spare-parts + repair requests (service records, possibly a projector on
    a workbench right now — the row keeps its phone so the service centre can still call), the ERP
    sale, and the **WordPress user** (it may carry WooCommerce order history). Deleting the device
    link FREES the serial for the next owner, same as "remove device".
  - **REST** `POST /me/delete` (auth) requires `confirm=DELETE` in the body — POST not DELETE so it
    survives every proxy/WAF. App: `api.deleteAccount()`, then `handleUnauthorized()` to drop the
    local session and land on login.
  - **App UI**: Settings → a quiet `person_remove` text button (findable, not tappable by accident)
    → a sheet listing what's deleted (red) and what's kept (green) + "you can sign in again with
    the same number and start fresh" → a final confirm dialog. Two deliberate steps, both bilingual
    (17 new strings, gen-l10n ran).
  - Tests: NEW `test-app-api-v12.php` (**25**) — refuses without `confirm`, then proves BOTH halves:
    every session/push token/profile field/device/dismissal/notice/read-state/bug report gone and
    the serial freed, AND repair + parts + warranty registration + the WP user still there, with
    the repair detached but keeping its phone. Regression: v8 73/0, v9 14/0, v10 13/0, v11 37/0.
    App: analyze clean, 44 tests, AOT OK.
  - ⚠️ Re-upload **`aun-app-api.zip`** (plugin 1.34.0, no DB change) **and rebuild the APK**.
- **v1.42.0** — (2026-07-30, build config + website only; app/plugin code unchanged) — Play Store prep
  - **Two website pages published and verified live** (see the Go-live section above). The existing
    website privacy policy was checked and is eCommerce-only — no OTP login, no push tokens, no
    uploaded photos, no serials, no deletion route — so a separate **app** policy was written rather
    than merged. Both pages name Smart Living Bangladesh as operator and cross-link, tying the
    developer-account legal entity to the domain hosting the policy.
    `aun-app-play-store-checklist.md` carries the exact **Data safety** answers (incl. why "shared"
    is No for Alpha SMS/Firebase — Google's service-provider exception), the Play Console fields,
    and an optional block for smartliving.com.bd.
  - **Release signing wired.** `signingConfigs` + `key.properties` (gitignored; `.example` template
    added), release falls back to the debug key when the file is absent so the project still builds
    for anyone without the key. `build-aun-app.cmd` now PRINTS which key signed the build
    ("RELEASE (your upload key)" vs "DEBUG KEY - test builds only") so a debug-signed APK can't ship
    by accident, and warns about the one-time uninstall.
  - ⚠️ **Signature change = existing installs must be uninstalled once** (Android refuses an update
    signed with a different key). Local app data is lost, which is harmless — everything real is
    server-side and returns on login. Do it before the user base grows.
  - Nothing to do for **FCM** (push doesn't use the signing cert) or **OTP auto-fill** (the SMS
    Retriever hash is computed at runtime and sent as `app_hash` — that is exactly why it was never
    hardcoded; it just starts sending the new hash).
  - ⚠️ The Gradle change can't be built in-session (loopback) — its first real test is the user's
    `build-aun-app.cmd`. `flutter analyze` clean; no Dart changes.
  - NEXT when the developer account exists: a `build-aun-app-bundle.cmd` for the `.aab` (same
    keystore, same config — only the build command differs). APK stays for the website download.
  - ⚠️⚠️ **The re-install break happens TWICE unless you act at enrolment.** Play App Signing
    normally has Google generate its own app-signing key, so a Play-delivered build would NOT match
    the website APK — website users could not update from Play and vice versa. To avoid a second
    break, at app creation choose to **upload the existing `aun-upload-key.jks` as the APP SIGNING
    key** ("use an existing key / export from a Java keystore"). Then both channels carry the same
    signature. Announcement text for the one-time re-install:
    `<workdir>\aun-app-reinstall-announcement.md`.
- **v1.43.0** — (2026-07-30, app 1.38.0+42, plugin 1.35.0, DB stays v13) — 4 fixes
  - **(2) Profile setup: Save worked on an empty name.** The post-OTP "What is your name?" sheet is
    a DIFFERENT screen from the ProfileEditor fixed in v1.37, and still let Save through with ""
    (the API drops an empty name, so it "saved" and changed nothing). Save is now disabled until
    there's a name, with the `nameRequired` helper text; Enter submits; **Skip** still works — that
    is the honest way to move on without giving one.
  - **(4) Home showed "Under review" for a REJECTED device.** Home's compact card had its OWN
    three-branch status logic that lumped everything not-approved into "under review", while
    My Devices used `StatusChip` (which handles rejected/duplicate). Extracted ONE resolver
    `deviceStatusStyle(l, device)` in widgets.dart; `StatusChip` and the Home mini card both use it.
    Home keeps only its local nicety of showing days-left instead of the word "active".
    ⭐ Same lesson as failure.dart: two places deciding the same thing WILL drift.
  - **(3) TMDB details still missing in the app — the code was right, the CACHE was stale.**
    `/watch` is stale-while-revalidate: the persistent `aun_app_watch_store` still held rows built
    by the pre-1.33 code (no genres/runtime/cast) and kept serving them until the 12 h TTL lapsed
    AND a background cron ran. Fix: the store is now **version-stamped** (`AUN_App_Watch::SCHEMA`,
    bump it whenever a field is added to a pick) — a payload from an older schema is ignored and
    rebuilt, so plugin upgrades are self-healing. Plus a new admin **"Rebuild picks now"** button
    (Settings → What to Watch) that clears the store, rebuilds in-request and reports how many
    titles came back with full details. Bench-tested (7 checks: stale rows not served, rebuilt rows
    carry genres/runtime/cast, new store stamped, current-schema store reused not rebuilt).
    ⚠️ **After uploading this plugin, press "Rebuild picks now"** — otherwise the rail stays bare
    until the next twice-daily rebuild.
  - **(5) Home sections popped in.** Video guides and "what to watch" rendered nothing while
    loading, then appeared at full size — a visible jump a second after the screen settled. Both
    now have THREE states like the device rail (skeleton → content → nothing), cross-faded with
    `AunReveal` and held at the right height by shape-matched skeletons (`_RailSkeleton`,
    `_WatchSkeleton`), so the page never resizes under the customer's thumb.
  - Tests: app analyze clean, 44 tests, AOT OK. Backend v8 73/0, v11 37/0, v12 25/0 + the new
    schema checks. ⚠️ Re-upload `aun-app-api.zip` **and** rebuild the APK.
- **v1.44.0** — (2026-07-30, app 1.39.0+43, plugin 1.36.0, **bridge changed**, DB stays v13)
  - **(2) ⚠️ ROOT CAUSE — the notification opt-in sheet could NEVER appear.** `AppPush.register()`
    called `messaging.requestPermission()`, and register runs from `AppState.init()` (returning
    session) and `setLoggedIn()` — so Android 13+'s system dialog fired **cold, on the very first
    launch**, before any explanation. Once answered, `NotificationPrompt.shouldAsk()` correctly
    stands down forever, so our explainer was dead on arrival. The v1.35 brain was fine; v1.11's
    push registration undercut it. A second cold request sat in the firmware download path.
    FIX: **one owner for the permission** — the opt-in sheet. `register()` no longer requests on
    Android (kept for iOS, which needs permission before it will issue an APNS token); the download
    path no longer requests at all. ⭐ Registering WITHOUT permission is correct: Android still
    issues a valid FCM token and messages still arrive, they're simply not displayed until
    permission is granted — and they start showing the moment it is, with no re-registration.
    ⭐ RULE: never call an OS permission API outside the flow that explains it.
  - **(1) Home "my projectors" status — full audit, two real holes closed.** Confirmed OK: direct
    ERP purchases (`status:'direct'`, covered by `isApproved`), approved/expired/pending/mismatch/
    not_found/duplicate/rejected. Holes found and fixed in `deviceStatusStyle`:
    (a) **approved with NO warranty block was labelled "Warranty expired"** — telling a customer
    their warranty is gone when we merely have no data. Now requires `warranty != null`; approved
    without a window shows the new neutral **"Registered"** (`statusRegistered`, en+bn).
    (b) **an empty or unknown status fell through to "Rejected"** — a definite, alarming claim made
    on no evidence (an empty `status` string is reachable: `?? 'pending'` doesn't catch `''`).
    "Rejected" is now shown ONLY when the server literally says `rejected`; anything unrecognised
    is treated as still under review. Also "0 days left" on the last day now reads as the plain
    status instead. NEW `test/device_status_test.dart` (11) pins the whole matrix, including
    "'rejected' is only ever shown when the server actually says so".
  - **(3) NEW: support-ticket queue in the WP admin bar.** Nobody logs into osTicket daily, so
    customer replies sat unnoticed. Bridge gains a **`queue`** action (staff-side, not per-customer)
    using osTicket's own `isanswered` flag — 0 means the last post was the customer's, i.e. the ball
    is in our court — returning open/waiting totals plus the 25 oldest. WP
    `AUN_App_Tickets::refresh_queue()/queue()/agent_url()` stores a snapshot in
    `aun_app_ticket_queue`, refreshed by the EXISTING 10-minute tickets cron (and once when an admin
    opens Settings, so it works immediately after setup). ⚠️ The admin bar renders **only** from
    that option — it must never call the bridge inline, or every wp-admin page would carry a
    cross-server round-trip and hang whenever support is slow. Indicator: **red + pulse** with the
    waiting count when customers are waiting, neutral when tickets are open but answered, gone when
    the queue is empty — it clears itself the moment an agent replies. Sub-menu lists the waiting
    tickets (oldest first, red/green dot, "2 hours ago") linking straight into the agent panel.
    A bridge outage keeps the last good numbers and flags itself rather than claiming "all clear".
    Admin-only. Inline `<style>` carries the WP Rocket guards.
  - Tests: NEW `test-ticket-queue.php` (**19**: snapshot, zero-HTTP rendering, outage keeps last
    good + flags, badge shows WAITING not open, waiting/idle styling, disappears when empty, hidden
    from non-admins). App: analyze clean, **55** tests, AOT OK. Backend v8 73/0, v12 25/0.
  - ⚠️ This round needs **all three**: re-upload `aun-app-api.zip`, **re-upload the
    `aun-app-bridge` folder** (new `queue` action), and rebuild the APK.
- **v1.45.0** — (2026-07-30, app 1.40.0+44, plugin unchanged) — APP-ONLY: 4 items
  - **(4) ⚠️ Offline → Buy spare parts said "add your projector first".** Same class of bug fixed in
    RepairRequestScreen in v1.39, still present in PartsRequestScreen: on a failed device fetch it
    did `_devices ??= const []`, so a customer with three projectors was told they own none — and
    offered a Register button. Now it shows `ProjectorErrorView` with Retry when there's nothing
    cached AND the fetch failed. The catch block no longer blanks the device list either: if only
    the parts CATALOGUE fails, the form stays usable.
    ⭐ LESSON (third time): "empty" and "couldn't load" are different states — never collapse one
    into the other.
  - **(2) Parts list appeared out of nowhere.** The device list resolves instantly from cache while
    the catalogue is still in flight, so "Which parts do you need?" rendered above an empty gap and
    the rows popped in a second later. Now a `_PartsListSkeleton` (4 part-shaped rows) holds the
    space, plus an honest `partsUnavailable` line if the catalogue comes back empty. Same three-state
    pattern as every other async section.
  - **(3) Duplicate offline warning removed.** The slim shell banner AND the full-screen projector
    both fired for one outage. The banner's job is "what you're looking at may be stale" — it earns
    its place when there IS content, and is pure noise on top of a screen already explaining the
    outage. NEW `fullScreenFailures` counter in failure.dart: `ProjectorErrorView` (now stateful)
    registers while mounted and the banner stands down. A counter, not a bool, because routes
    overlap during a page transition. DevicesTab switched from a hand-rolled ProjectorStateView to
    the shared `ProjectorErrorView`, so it benefits too and loses its duplicated classification.
  - **(1) Privacy-policy link in the app** — new Settings row (under Report a problem) opening
    `Env.privacyPolicyUrl`. Play expects an account-based app to link its policy from inside the
    app, not only from the store listing. Both URLs now live in ONE place (`lib/src/env.dart`), so a
    slug change is a one-line edit. Where else the pages should be linked (website footer, APK
    download page, the parent-site block) is written up in
    `<workdir>\aun-app-page-links-guide.md` — and NO second copy of the policy on
    smartliving.com.bd: one authoritative page, linked from both sites.
  - Tests: analyze clean, 55 tests, AOT OK. ⚠️ **APP-ONLY — rebuild the APK.** Plugin stays 1.36.0;
    the bridge upload from v1.44 is still required if not done yet.
- **v1.46.0** — (2026-07-31, app 1.41.0+45, plugin unchanged) — APP-ONLY: the welcome card + 3 fixes
  - **NEW `ProjectorMood.welcome`.** The first onboarding card was the only one that looked static
    (it had a breathing halo, just too quiet next to the orbiting cards). It now uses **the app's own
    projector buddy** — the same character every empty/error state uses — powering on. ⭐ The user
    had to correct me twice here: I first drew a *new* projector, then a rear-view one, when the
    right answer was always "use the character we already have". If an illustration is needed
    anywhere, check `projector_animations.dart` FIRST.
    The power-on runs in the order a real projector does it, driven by a SEPARATE one-shot
    controller (`_intro`, 1700 ms) so it plays once and then hands over to the 5 s loop — re-running
    a greeting every 5 s would be a nag:
      1. **POWER** (0–0.16) the green LED catches with a bright flick, then **stays on** (a steady
         LED reads "powered and fine"; the slow pulse the other moods use reads "waiting/trouble"),
         with a soft halo behind it.
      2. **LAMP** (0.14–0.52) the beam strikes — narrow and unsteady, widening and steadying, with a
         damped ripple, because real lamps catch and waver rather than fading up. Dust motes ride the
         cone (16 of them, each making a WHOLE number of trips per loop — the standing rule).
      3. **PICTURE** (0.46–0.92) the projector's **real boot screen** forms, with the same bloom pass
         every "forming" thing in the app gets.
    Sustain: LED steady, beam breathing, dust drifting, and the eye **excited** — wider (9.2 vs 8.5),
    a happy double-blink 3× per loop, a bigger catchlight plus a second sparkle, pupil darting up at
    the picture; the body gains a second faster bounce.
  - **The boot screen is a real asset** — `assets/img/aun-boot-logo.webp` (46 KB, user-downscaled),
    the actual image their projectors boot to. First asset the painter has ever used: decoded once in
    `initState` (welcome only) at `targetWidth: 720`, disposed with the state, and the painter falls
    back to a plain "AUN" wordmark if it hasn't decoded yet or is missing. ⚠️ Drawn with **contain**
    fit (`min(sw/iw, sh/ih)`), so a 16:9 image inside the 4:3 screen letterboxes exactly as it really
    would and can NEVER stretch or overflow at any device size; the whole scene already scales
    uniformly from the 200-unit viewbox. Measured at a 220 pt art size: the AUN wordmark lands at
    ~23.6 px (legible), the taglines at 5.2/2.4 px (texture, not words) — the user was shown this
    trade-off in the preview and chose the full image.
  - Reduced motion → `intro: 1`, i.e. the finished scene, no power-on.
  - Also in this build (fixed earlier, shipping now): **Settings profile name** (the editor seeded
    its fields once from a possibly-empty profile and latched — now keyed on the profile's content,
    never while mid-edit), **"See all" sizing** (12.5 px hand-rolled button vs the theme's ~14 px —
    both now go through `SectionHeader`), and **video titles** (admin title on cards/rails/lists via
    `displayTitle`, the real YouTube title inside the player via the new `playerTitle`).
  - Tests: analyze clean, **56** widget tests (`projector_art_test` loops `ProjectorMood.values`, so
    the new mood is covered automatically), AOT OK, asset confirmed in the bundle.
  - ⚠️ **APP-ONLY — rebuild the APK.** Plugin stays 1.36.0. Previews kept for reference:
    `aun-welcome-animation-preview.html`.
- **v1.47.0** — (2026-08-01, app 1.42.0+46, plugin unchanged) — APP-ONLY: dust physics + status icons
  - **(1) ⚠️ REGRESSION FIXED — welcome-mood dust "moved in lines".** Real bug in the code that
    shipped in v1.46, not a perception issue: `trips = 1 + (i % 2)` alternated 1,2,1,2… in lock-step
    with the particle's own index — which was ALSO its position along the beam (`off = i/16 + …`).
    That's a perfect checkerboard: exactly half the motes travelled at 2× the other half, arranged in
    strict alternating order. It read as organised lanes because it *was* organised, just not on
    purpose. Same root cause as the `sin(phase * 0.5)` non-integer-multiplier bug from v1.31/v1.36 —
    deriving a visual parameter from loop-position math instead of treating it as data.
    Fix: new `_DustMote` (off/trips/lane/wobAmp/wobPhase/radius), generated ONCE with a seeded
    `Random` — genuinely independent per particle, not a formula of `i`. ⚠️ Second bug caught by the
    new test before it shipped: with only 16 motes across 3 speed buckets, pure independent
    randomness can (and with the first seed tried, DID) land an entire speed bucket on one side of
    the beam by chance — a smaller-scale repeat of the same bug, from luck instead of a formula. Fixed
    by explicitly BALANCING `trips` and `lane` (even spread, then each shuffled independently), so
    speed and position are uncorrelated **by construction**, not by hoping the RNG behaves.
    `trips` stays a whole number (1/2/3) — the standing rule that a loop-position multiplier must be
    an integer or the loop snaps.
  - **(2) Spare-parts and repair submit confirmations now use the projector buddy.** Both dialogs
    showed a static emoji (🛠️ for parts, 📦 for repair). Replaced with
    `ProjectorSceneArt(mood: ProjectorMood.pending, size: 150)` — the same waiting/hourglass mood the
    device-registration success dialog already uses for "received, review in progress" (see
    `register_device_screen.dart`). One visual language for every "we got it, hang tight" moment in
    the app, not three different ones.
  - Tests: NEW `test/welcome_dust_test.dart` (2 — renders without throwing; trip counts stay whole
    AND are not a fixed alternating pattern AND no speed bucket clusters on one side of the beam),
    exposed via a `@visibleForTesting` seam (`debugDustMoteSpeeds()`) rather than weakening the
    painter's privacy. Full suite: analyze clean, **58** tests, AOT OK.
  - ⚠️ **APP-ONLY — rebuild the APK.** Plugin stays 1.36.0.
