# AUN Projector Customer App — v1.2

Built 2026-07-12. Two parts: a WordPress backend plugin and a native Android app (Flutter — **not** a WebView).

## What's where

| Piece | Location |
|---|---|
| Backend plugin (upload to live site) | `aun-app-api.zip` in this folder |
| ERP lookup endpoint (install on UltimatePOS) | `ERP/AppLookupController.php` |
| Built APK (install on phone) | `AUN-Projector-app.apk` in this folder |
| App source code | `C:\dev\aun-app` |
| APK builder (double-click to rebuild) | `build-aun-app.cmd` in this folder |
| Local test bench copy | `wp-local\site\wp-content\plugins\aun-app-api` (dev OTP mode ON there) |

## v1.2 changes (from your feedback)

- **Direct store sales are never registered** and send no SMS/email — warranty just runs from the ERP sale date. Only **Dealers-group** serials open the registration form. Same logic as the website.
- **Profile** comes from your ERP (name by phone), not the WordPress account — fixes the "AUN / info@" bug. Add a **profile photo** (camera/gallery), emoji, or auto-Gravatar from email.
- **Camera scanner fixed** (was a missing permission). Scans any barcode format, plus scan-from-photo.
- **Spare parts & repair** are locked to a projector you own (no free-typed model) and pull the **reference photos** straight from Spare Parts → Parts Catalogue.
- **My Devices**: remove a device (frees its serial); a serial you hold can't be added by anyone else.

> ⚠️ One-time cleanup: the earlier bug auto-registered your test serial **523986765361** on the live site. Delete that entry in **SLB Warranty → Registrations** (or remove it from the app) so its warranty recalculates from the real ERP date.

## Phase 1 features (all working, tested)

- **Login**: phone number + SMS OTP (same Alpha SMS flow as the website; no passwords). New phone numbers get a customer account automatically.
- **My Devices**: every warranty registration on the customer's number, with live warranty bar (start / end / days left) using the same rules as the SLB Warranty plugin (12 months, purchase date, dealer-sale fallback).
- **Register device**: serial + model + dealer dropdowns (live from your site) + purchase date + optional invoice photo. Auto-approve/mismatch/not-found — identical logic to the website form, plus the same SMS/email notifications.
- **Warranty check**: type any serial → registered to you / registered to someone else (masked) / genuine dealer stock (warranty from ERP sale date) / unknown.
- **Per-model content**: firmware downloads, manuals, YouTube video guides, tips — managed from WP Admin → **AUN App → App Content**.
- **Support**: WhatsApp chat button, tap-to-call, hours, website/Facebook, FAQ tips.
- **Home**: announcement bar, promo banner carousel, app-discount note, quick actions — all editable in WP Admin → **AUN App → Settings**.
- **Bangla-first UI** with English toggle; Material 3 design, dark mode, smooth animations.
- **Self-update**: the app checks your server's latest/minimum version and prompts (or forces) an APK update — so you can push updates before the Play Store account exists.

## To get the APK

Double-click **`build-aun-app.cmd`** → wait → `AUN-Projector-app.apk` appears next to it. Send to any Android phone and install.
(The build must run outside Claude's sandbox — that's why it's a manual double-click.)

## To go live later

1. Upload `aun-app-api` folder to the live site's `wp-content/plugins/`, activate.
2. Fill in WP Admin → AUN App → Settings (WhatsApp number, support phone, banners...).
3. Add content under AUN App → App Content.
4. Rebuild the APK — the app already points at `https://aun-projector.com.bd`.
5. **Never** add `AUN_APP_DEV_OTP` to the live wp-config.php (test bench only — it returns OTPs in the API response).

## Phase 2 (not built yet)

Shopping: product catalog, 3-screen checkout, SSLCommerz payment, app-only discount enforced server-side, order tracking. The backend and app are structured so this bolts on without rework.
