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
| **APK builder (user double-clicks)** | `<workdir>\build-aun-app.cmd` → outputs `AUN-Projector-app.apk` + `aun-app-build.log` |
| **Toolchain** | Flutter `C:\dev\flutter`, Android SDK `C:\dev\android-sdk`, JDK21 `C:\dev\jdk-21.0.11+10`, pub cache `C:\dev\pub-cache` |
| **Warranty plugin (reference)** | `<workdir>\AUN Warranty & Registration.php` (a.k.a. SLB Warranty) |
| **Spare parts plugin (reference)** | bench: `wp-local\site\wp-content\plugins\aun-spare-parts\` |
| **Local WP test bench** | `C:\Users\Jobayer Hossain\wp-local` (PHP 8.2 + WordPress-on-SQLite + wp-cli) |
| **Bench tests / seed (scratchpad)** | `test-app-api.php` (44), `test-app-api-v2.php` (42), `seed-slb.php`, `debug-col.php` |

`<workdir>` = `C:\Users\Jobayer Hossain\Downloads\Claude session`

Current versions: **app 1.41.0+45**, **plugin 1.36.0 (DB v13)**.

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
