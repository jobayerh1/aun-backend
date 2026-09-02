# AUN Care Bangladesh — Play Store readiness

_Everything needed to publish, so that the day the D-U-N-S number arrives the only
remaining work is uploading._

**Status legend:** ✅ done · 🟡 needs a decision or a file from you · ⛔ blocked on D-U-N-S

Last updated: 2026-08-10 · app **1.87.0+91** · package **`bd.com.aunprojector.aun_app`**
(verified in `android/app/build.gradle.kts`). **The package name can never be changed after the
first release** — this is the one you are committing to.

---

## 0. The one-way doors

Three decisions cannot be undone after the first upload. Get them right before you press publish,
not after.

| Decision | Why it is permanent |
|---|---|
| **Package name** (`applicationId`) | Identifies the app for ever. A change means a new listing and every existing user is orphaned. |
| **Signing key** | Lose it and you can never update the app again. See §3 — use Play App Signing and the loss becomes recoverable. |
| **Developer account type** (organisation vs individual) | You are waiting on D-U-N-S for an ORGANISATION account. Registering as an individual to "get started" cannot be converted later. **Wait.** |

---

## 1. Account & legal ⛔

| Item | Status | Notes |
|---|---|---|
| Google Play Developer account (organisation) | ⛔ | $25 one-off. Needs the D-U-N-S number. |
| D-U-N-S number | ⛔ | Free from Dun & Bradstreet; typically 7–30 days. Business name/address must match your Google Play entry **exactly**. |
| Organisation legal name + address | 🟡 | Must match the D-U-N-S record character for character. Mismatch is the usual rejection cause. |
| Public contact email + phone | 🟡 | Shown on the store listing. Use a role address (support@…), not a personal one. |
| Developer website URL | ✅ | aun-projector.com.bd |

**Identity verification:** Google now verifies organisation accounts against the D-U-N-S record.
Have the certificate of incorporation and a utility bill ready — they are often asked for.

---

## 2. Privacy policy 🟡 — live, but one sentence is now out of date

The policy exists and is good: **`aun-projector.com.bd/app-privacy-policy/`** — verified live, and
it already covers the phone number, profile, serials, service requests and push tokens.

⚠️ **One sentence must change before you publish.** It currently promises:

> "No advertising, no advertising ID, **no third-party analytics or tracking SDKs**, and no location
> access."

That was true until app 1.88.0 added crash reporting. It is still true about *analytics* — the usage
counts are first-party and never leave our server, which is precisely why they were built that way
(§11) — but **Crashlytics is a third-party SDK** and the sentence has to say so.

📄 **`aun-app-privacy-policy.txt` in the working directory is the COMPLETE corrected page.** Open the
page in WordPress, switch to Text/Code view, select all, delete, paste everything below the `=====`
line, update, then purge the WP Rocket cache. The date at the bottom is already changed.

It corrects **three** stale claims, not one — the other two were found while fixing the first:

| Was | Now |
|---|---|
| "no third-party analytics or tracking SDKs" | Still true of analytics (first-party); Crashlytics disclosed |
| "No payments in the app" | Spare-parts payments exist — with the important part said plainly: card details are entered on SSLCommerz's page and never reach us |
| camera "used only to scan the barcode" | Also photos you attach and the wall preview — scanning and preview never leave the phone |

SSLCommerz and the couriers are now listed as recipients too, which they always should have been.

---

## 3. Signing ✅ / 🟡

| Item | Status | Notes |
|---|---|---|
| Release keystore exists | ✅ | Verified: `android/key.properties` points at a keystore that is present on this machine. |
| `key.properties` NOT in git | ✅ | Verified: `.gitignore` excludes `**/key.properties` and `**/*.jks`. |
| Keystore backed up OFF this machine | 🟡 | **The remaining risk.** It exists here and nowhere else. |
| Play App Signing enrolled | 🟡 | Do this at first upload. Google holds the app signing key; you keep an upload key that CAN be reset if lost. Without it, losing your keystore ends the app. |
| Build is an **AAB**, not APK | 🟡 | Play requires `.aab`. Your `build-aun-app.cmd` currently produces an APK for side-loading — see §9. |

> ⚠️ Back the keystore up **off this machine** — a second physical drive and an encrypted cloud
> copy. This is the single most common irrecoverable mistake in Android publishing.

---

## 4. Data safety declaration 🟡 — fill it in from this table

Play requires you to declare every data type. Getting this wrong is a policy strike, and the form
is long, so here is the completed answer set for this app as built today.

| Data type | Collected | Shared | Purpose | Optional? |
|---|---|---|---|---|
| Phone number | Yes | No | Account management, app functionality | Required |
| Name | Yes | No | Account management | Optional |
| Email address | Yes | No | Account management | Optional |
| Photos (profile) | Yes | No | Account management | Optional |
| Photos (fault/proof images) | Yes | No | App functionality (service requests) | Optional |
| Physical address | Yes | No | App functionality (parts delivery) | Optional |
| Purchase history | Yes | No | App functionality (warranty, orders) | Required |
| Crash logs | Yes | **Yes — Google (Crashlytics)** | Diagnostics | Cannot be turned off in-app |
| Diagnostics / app interactions | Yes | **No — first-party, our own server** | Analytics | Cannot be turned off in-app |
| Device identifiers (FCM token) | Yes | No | App functionality (push) | Required |
| Approximate/precise location | **No** | — | — | — |
| Contacts, calendar, SMS, call logs | **No** | — | — | — |

Also declare, and all three are true:

- ✅ Data is **encrypted in transit** (HTTPS everywhere).
- ✅ Users **can request deletion** (§6).
- ✅ You follow the Families policy only if you target children — **you do not**; content rating is
  for a general audience.

> ⚠️ **Crash logs are the only row where "Shared: Yes" applies.** Usage counts go to our own
> server, never to Google — that is the whole reason they were built first-party (§11). Declaring
> them as shared would be inaccurate in the other direction.
>
> If you ever untick **App analytics** in wp-admin, both Diagnostics rows stop being collected. A
> declaration that overstates is safer than one that understates, so leaving them declared is fine.

---

## 5. Permissions ✅ — audited, and genuinely clean

Every permission must be explainable, and broad ones trigger separate slow reviews.

**Audited against the manifest: the app declares exactly three, and nothing else.**

| Permission | Why | Play form |
|---|---|---|
| INTERNET | Everything | No declaration needed |
| CAMERA | Barcode scan, fault photos, AR preview | Explain in the listing |
| POST_NOTIFICATIONS | Push (Android 13+) | No declaration needed |

There is **no** storage permission, no `READ_MEDIA_IMAGES`, no `MANAGE_EXTERNAL_STORAGE`, no
`QUERY_ALL_PACKAGES`, and no location. That avoids every one of the permission declarations that
slow a review down.

Two reasons it is clean, worth not breaking later:

- Photo picking uses Android's **system photo picker**, which needs no permission at all.
- Firmware downloads go through `background_downloader` into **app-scoped storage**.

⚠️ **Re-check after adding any plugin.** Permissions merge in silently from dependencies, so the
source manifest is not the truth. The merged list after a release build is at:

```
build/app/intermediates/merged_manifests/release/AndroidManifest.xml
```

---

## 6. Account & data deletion ✅ — already done

**Correction to an earlier draft of this document, which called this the biggest
blocker. It is not: it was already built.** Verified end to end:

| Piece | Status |
|---|---|
| Backend `AUN_App_Account::delete()` | ✅ Sessions and push tokens first (so a phone stops acting as them even if a later step fails), then profile, avatar attachment, linked devices, dismissals, notifications, bug reports. |
| REST `POST /me/delete` | ✅ Requires `confirm: "DELETE"`. |
| In-app: Settings → Delete my account | ✅ With a screen listing exactly what is removed and what is kept. |
| Public web page | ✅ Live at `aun-projector.com.bd/delete-account/` — this is the URL Play's form asks for. |
| Analytics erased with the account | ✅ Added in 1.79.0 via `aun_app_account_deleted`. |

**The design is right and worth not changing:** service requests are *detached*
(`user_id → 0`) rather than deleted — a repair may be physically on a workbench
right now, and its history is a business record. The row keeps the phone number
it was created with, because the service centre still has to reach that person.
`AUN_App_Account::summary()` returns the removed/kept lists so the confirmation
screen and the code cannot drift apart.

⚠️ **Check the deletion page still matches** after any change to what is
deleted. The page and `summary()` are two descriptions of one behaviour.

---

## 7. Store listing assets 🟡

| Asset | Spec | Status |
|---|---|---|
| App name | 30 chars max — "AUN Care Bangladesh" fits | 🟡 |
| Short description | 80 chars | 🟡 |
| Full description | 4000 chars | 🟡 |
| App icon | 512×512 PNG, 32-bit, no transparency | 🟡 |
| Feature graphic | 1024×500 PNG/JPG, no transparency | 🟡 |
| Phone screenshots | 2–8, min 320px, max 3840px, 16:9 or 9:16 | 🟡 |
| Tablet screenshots | Optional, but improves ranking if you support tablets | 🟡 |
| Promo video | Optional YouTube URL — you already produce tutorials | 🟡 |

**Write both languages.** Play supports per-locale listings; Bangla + English matches the app and
matters commercially in this market.

**Suggested screenshots**, in order — lead with what is distinctive, not with a login screen:

1. Home with the live status strip (a real quote counting down)
2. "Help me choose" results with the reasons
3. A warranty card / My Devices
4. Firmware with the What's new block
5. A spare-parts quote with Approve/Decline
6. The projector planner or AR preview

⚠️ Screenshots must show the REAL app. Mock-ups with invented data are a policy violation.

---

## 8. Content rating & category 🟡

- Complete the IARC questionnaire → expect **Everyone / 3+**.
- Category: **Tools** or **Business**. Tools is the better fit — it is a utility for a product you
  own.
- Tags: projector, warranty, service, support.
- **Ads: none.** Declare "No ads" — you have none.

---

## 9. Build changes needed before upload 🟡

| Change | Why |
|---|---|
| **Produce an AAB ✅ done** — double-click **`build-aun-app-aab.cmd`** | Play does not accept APKs for new apps. Deliberately a second script, not a flag on the APK one: the APK build is allowed to fall back to the debug key (test builds are useful), a Play bundle never is, so this one refuses to start without `android\key.properties`. Output is versioned (`AUN-Care-Bangladesh-2.1.4-112.aab`) and every older `.aab` in the folder is deleted first — the same stale-file trap that once cost weeks on the APK side. |
| Keep the APK build too | You still side-load from the website while the listing is pending. |
| `minSdkVersion` sanity check | Lower = more of the market. Confirm what the plugins force. |
| `targetSdkVersion` current | Play enforces a minimum target each August–November. Check before upload. |
| ProGuard/R8 + Crashlytics mapping upload | Already handled by the Crashlytics Gradle plugin added in 1.87.0 — without it release stack traces are unreadable. |
| ~~Delete `update_sheet.dart`~~ **DONE differently, 1.95.0 — do not delete it** | The real problem was not the prompt, it was the DESTINATION: sending a Play install to a website APK breaks Play's Device and Network Abuse policy. Deleting the sheet would also have stripped the update path from every side-loaded copy the website still distributes. `AppState.updateUrl` now routes by `PackageInfo.installerStore` — Play listing for a Play install, APK for a side-loaded one — so one binary serves both. Three call sites (update sheet, force-update screen, **Settings**) all go through it. |
| Remove/repoint the APK-download band on the website | Once the listing is live it should point at Play, not a direct APK. |
| **Backups ✅ done, 1.95.0** | `allowBackup="false"` + `aun_backup_rules.xml` (≤11) + `aun_data_extraction_rules.xml` (12+). The login token's Keystore key is never backed up, so a restored copy is undecryptable ciphertext and the customer looks signed in while being signed out. ⚠️ `allowBackup="false"` alone does NOT stop `<device-transfer>` — the new-phone copy people actually use. |
| **Verify the AAB is signed with the upload key ✅ automatic** | `key.properties` absent ⇒ the build silently falls back to the DEBUG key. `build-aun-app-aab.cmd` now reads the certificate back **out of the finished file** — the only check a stale `key.properties` or a Gradle cache cannot fool — prints Owner + SHA-256, and hard-stops if it says `Android Debug`. ⚠️ Note the same command does **not** work on the APK: `keytool -printcert -jarfile AUN-Care-Bangladesh.apk` answers *"Not a signed jar file"*, because modern APKs carry only signature scheme v2/v3 and keytool reads v1 JAR signatures. Bundles are JAR-signed, so it works there. For an APK use `apksigner verify --print-certs`. |

---

## 10. Pre-launch checks Google runs for you ✅ free

Upload to **internal testing first**, never straight to production. Play then gives you:

- **Pre-launch report** — installs the app on real devices and reports crashes, ANRs, accessibility
  problems and screenshots per device. It routinely finds things a single test phone does not.
- **Android vitals** — crash and ANR rates against the thresholds that affect ranking.

Suggested rollout: internal testing → closed testing (staff + a few dealers) → **staged production
rollout at 20%**, watching vitals, then 100%.

---

## 11. What the numbers will tell you after launch (app 1.88.0)

**Usage counting is FIRST-PARTY** — the app posts small batches to our own WordPress server
(`POST /events`), stored in `wp_aun_app_events`, purged automatically after **180 days**.

⚠️ **This is deliberate and must not be "simplified" into Firebase Analytics later.** The published
privacy policy promises customers no third-party analytics SDK. Firebase Analytics would break that
promise, and every question below is answerable from our own data. Only **crash reporting** uses an
outside service (Crashlytics), because there is no realistic first-party equivalent — and it is
disclosed on exactly those terms.

**Two locks on personal data, and both matter:** the app never sends a phone number, name, address,
serial or request reference, and the server's `clean_params()` drops anything phone- or email-shaped
plus any string over 40 characters (prose is not a category). Event names are an **allow-list** — a
future app version cannot invent new ones.

**Where to read it:** wp-admin → **AUN App → Usage**. It opens with the standing questions answered
in words, not a wall of charts.

| Event | The question it answers |
|---|---|
| `service_opened` | Which of the five services justify their upkeep? |
| `home_strip_tap` | Does Home's prime real estate get used, or is it decoration? |
| `finder_started` / `_step` / `_result` / `_product_opened` | Does the finder get used, where do people quit, and does it lead to a product? |
| `parts_form_opened` / `parts_submitted` | Is there a drop-off inside the request form? |
| `quote_answered` | Do customers answer quotes **in the app**, or still by SMS link? Compare with total decisions in Spare Parts → Requests; the gap is the SMS crowd. |
| `quote_revived` | Is the 10-day recovery window worth keeping? |
| `pay_started` / `pay_finished` | Online payment vs cash on delivery, and where attempts fail. |
| `content_opened` | Does anyone read the firmware and manuals we publish? |
| `watch_opened` | Does "what to watch" earn its place on Home? |
| `notifications_prompted` | The ceiling on every push feature we build. |
| `notification_opened` | Which notification types people actually care about. |
| `app_problem` | Handled failures — not crashes, which Crashlytics catches itself. |

**First week after launch, look at three things only:** crash-free users in Crashlytics (aim >99%),
the notification grant rate, and the `service_opened` split. Everything else can wait a month, when
there is enough data for the number to mean anything.

⚠️ **Events arrive in batches**, not instantly — up to a dozen at a time or ~20 seconds after a
burst, and they survive being offline. Do not expect a tap to appear in the dashboard immediately.

---

## 12. Ordered plan

**Now, while waiting on D-U-N-S — none of this needs the Play account:**

1. **Back up the keystore off this machine** — `Downloads\AUN-KEYSTORE-BACKUP\` is prepared with a
   README; copy it to a USB stick and an encrypted cloud folder. *10 minutes, and the highest-stakes
   item on this page.*
2. **Replace the privacy-policy page** with `aun-app-privacy-policy.txt` — select all, paste, update, purge cache (§2). *5 minutes.*
3. Write the listing text in Bangla + English, capture screenshots (§7).
4. Produce the app icon (512×512) and feature graphic (1024×500) (§7).
5. Audit the merged manifest after a release build (§5).
6. Switch the build script to also produce an **AAB** (§9).
7. Confirm the delete-account page still matches what deletion actually does (§6).

**The day D-U-N-S arrives:**

8. Register the organisation Developer account; complete identity verification.
9. Create the app and **enrol in Play App Signing** (§3).
10. Fill Data safety from §4; complete the content-rating questionnaire (§8).
11. Upload the AAB to **internal testing**; read the pre-launch report (§10).
12. Closed testing with staff and a few dealers.
13. Staged production rollout, 20% → 100%, watching Android vitals.
14. Delete `update_sheet.dart` and repoint the website's APK band at Play (§9).

**Realistically:** items 1–7 are about **1–2 days** of work now that deletion turned out to be
built. Items 8–13 take 3–7 days, mostly Google's review time. The app can be live roughly a week
after the D-U-N-S number lands, provided 1–7 are finished while waiting.

---

## 13. Where things live

| What | Where |
|---|---|
| This checklist | `<workdir>\PLAY-STORE-READINESS.md` |
| Corrected privacy policy (paste whole) | `<workdir>\aun-app-privacy-policy.txt` |
| Signing key backup + instructions | `Downloads\AUN-KEYSTORE-BACKUP\READ-ME-FIRST.txt` |
| Full development history | `<workdir>\aun-app-HANDOFF.md` |
| Flutter app | `C:\dev\aun-app` |
| Usage dashboard | wp-admin → AUN App → Usage |
| Analytics on/off | wp-admin → AUN App → Settings → App analytics |
