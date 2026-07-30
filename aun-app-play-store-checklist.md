# Google Play submission — URLs, Data safety answers, and the parent-site block

Everything here matches what the app actually does (checked against the source) and what the two
new pages say. **Keep all three in sync** — Play rejects apps where the Data safety form and the
privacy policy disagree, and that is the single most common reason for a rejection at this stage.

---

## 1. Where each page goes

| Page | Put it on | URL | Used for |
|---|---|---|---|
| **App privacy policy** | aun-projector.com.bd | `https://aun-projector.com.bd/app-privacy-policy/` | Play Console → **App content → Privacy policy**, and the store listing |
| **Delete your account** | aun-projector.com.bd | `https://aun-projector.com.bd/delete-account/` | Play Console → **App content → Data safety → Data deletion** ("URL for users to request data deletion") |
| Pointer block (optional but recommended) | smartliving.com.bd | anywhere on an existing page, or a small `/aun-care-app/` page | Ties the legal entity's website to the app |

### Why the projector site and not the parent site

The privacy policy has to describe **where the app's data actually lives**, and that is
aun-projector.com.bd — the app's backend, database, uploaded photos and account records are all
there. Hosting the policy on the same domain is the honest and simplest arrangement.

Play does **not** require the privacy policy to sit on the developer account's website. It requires
a live, public, non-editable URL that covers the app. What it does care about is that a reviewer
can connect **Smart Living Bangladesh** (the developer account name, backed by your D-U-N-S) to the
domain hosting the policy. Both new pages state "operated by Smart Living Bangladesh" and link to
smartliving.com.bd, which closes that gap. Adding the pointer block below closes it from the other
direction too, and costs five minutes.

---

## 2. Your existing privacy policy will **not** be enough

Your current `/privacy-policy/` page is a website/eCommerce policy: orders, cookies, comments,
Gravatar, IP addresses. It never mentions the app. It has nothing about:

- signing in with a mobile number and a one-time SMS code
- push notification tokens (Firebase)
- photos uploaded from the phone (invoice, repair, spare-part, ticket attachments, screenshots)
- projector serial numbers and warranty records pulled from your sales system
- the SMS gateway (Alpha SMS) as a recipient of the customer's number
- how to delete an account

A reviewer comparing that page to your Data safety form would find a mismatch immediately.

**Keep your existing page as-is for the website**, and publish the new app-specific policy
alongside it. That is cleaner than merging them: the two products collect genuinely different
things, and a single page trying to cover both gets confusing and goes stale fast.

---

## 3. Data safety form — exact answers

**App content → Data safety.**

### Does your app collect or share any of the required user data types? → **Yes**

### Is all of the user data collected by your app encrypted in transit? → **Yes**
(Everything is HTTPS.)

### Do you provide a way for users to request that their data is deleted? → **Yes**
URL: `https://aun-projector.com.bd/delete-account/`
Also tick: **users can request that some data is deleted** *and* **account deletion is offered
in-app** — both are true.

### Data types — declare these as **collected**

| Category → Type | Collected | Shared | Optional? | Purposes |
|---|---|---|---|---|
| Personal info → **Name** | Yes | No | Optional | App functionality, Account management |
| Personal info → **Email address** | Yes | No | Optional | App functionality, Account management |
| Personal info → **Phone number** | Yes | No¹ | Required | App functionality, Account management |
| Personal info → **Address** | Yes | No | Optional | App functionality (repair pickup / parts delivery) |
| Personal info → **Other info** (projector serial, invoice no., warranty dates) | Yes | No | Required² | App functionality |
| Photos and videos → **Photos** | Yes | No | Optional | App functionality, Customer support |
| Messages → **Other in-app messages** (support tickets, repair descriptions) | Yes | No | Optional | Customer support |
| App activity → **Other user-generated content** (bug reports) | Yes | No | Optional | Customer support, App functionality |
| Device or other IDs → **Device or other IDs** (push notification token) | Yes | No | Optional | App functionality (push notifications) |

¹ **On "Shared":** Google's definition of *shared* excludes transfers to a **service provider**
processing data on your behalf. Alpha SMS (sending your OTP) and Firebase (delivering your push)
are service providers under that definition, so "No" is correct — and the privacy policy names both
of them explicitly anyway, which is what matters.

² Required in the sense that the app's core purpose (warranty and model-specific content) needs it —
but a customer can use the app without adding a projector, so "Optional" is also defensible. Pick
one and make sure the policy reads the same way.

### Data types — explicitly **NOT** collected

Leave all of these unticked, and be ready to defend it (you can — the code backs it up):

- **Location** (approximate or precise) — no location permission is declared at all
- **Financial info** — no payments in this version
- **Health and fitness**, **Contacts**, **Calendar**, **Search history**, **Installed apps**
- **Photos → Videos**, **Audio**
- **App activity → App interactions / In-app search history** — no analytics SDK
- **Device or other IDs → Advertising ID** — no ads, no ad ID

### Ads
**Does your app contain ads? → No.**

### Data collection disclosure notes worth writing in the free-text box
> The camera is used only to scan the barcode printed on the projector box. Scanning happens on the
> device; the image is never uploaded or stored. The app reads no SMS messages — one-time codes are
> auto-filled by Android's SMS Retriever API, which passes the app only that single message.

---

## 4. Other Play Console fields

| Field | Value |
|---|---|
| Developer account name | **Smart Living Bangladesh** (must match the D-U-N-S record exactly) |
| Developer website | `https://www.smartliving.com.bd/` |
| Developer support email | a **monitored** address — `info@aun-projector.com.bd` (Play sends real mail here) |
| App name | AUN Care Bangladesh |
| Package name | `bd.com.aunprojector.aun_app` |
| Target audience | Adults (18+) — the app is for people who bought a projector. Do **not** opt into the Families programme. |
| Permissions declared | `CAMERA`, `INTERNET`, `POST_NOTIFICATIONS` only |

**Good news on permissions:** you do not use `READ_SMS` / `RECEIVE_SMS` (the OTP auto-fill uses the
SMS Retriever API instead), so you avoid the Sensitive App Permissions declaration form entirely.
That form is a common source of delays. Same for location.

---

## 5. Before you submit — the things that actually block release

- [ ] **Release keystore.** The APK is still debug-signed. Create a real upload keystore, keep the
      backup somewhere safe, and never lose it — you cannot update the app without it.
- [ ] Build an **App Bundle (.aab)**, not an APK. Play requires it. (Your `build-aun-app.cmd`
      currently makes an APK for sideloading; keep that for direct downloads, add a bundle build
      for Play — tell me and I'll add it.)
- [ ] Publish both pages and open them in a **private browser window** to confirm they load with no
      login and no cookie wall.
- [ ] Purge WP Rocket after publishing both.
- [ ] Check the deletion page is **not** set to noindex — the reviewer must be able to reach it.
- [ ] Store listing assets: 512×512 icon, 1024×500 feature graphic, at least 2 phone screenshots,
      short + full description.
- [ ] Set version code / name on the Play track and in **AUN App → Settings** (the in-app updater),
      so sideloaded users and Play users don't fight each other.

---

## 6. Optional block for smartliving.com.bd

Paste into any page on the parent site (Flatsome Text/Code view or an HTML element). It links the
legal entity's own website to the app and its policies — useful if a reviewer starts from your
developer account website.

```html
[section bg_color="rgb(246, 248, 251)" padding="36px" label="AUN Care App"]

[row]

[col span="7" span__sm="12"]
<h2 style="font-size:22px;margin:0 0 10px;color:#0f172a;">AUN Care — our customer app</h2>
<p style="font-size:15px;color:#475569;line-height:1.7;margin:0 0 14px;">Smart Living Bangladesh publishes <strong>AUN Care Bangladesh</strong>, the free Android app for customers who own an AUN projector: warranty status, firmware and manuals for your exact model, spare-part orders, repair booking and support — in Bangla and English.</p>
<p style="font-size:14.5px;color:#475569;line-height:1.9;margin:0;">
<a href="https://aun-projector.com.bd/get-aun-care-app/" style="color:#0188fe;font-weight:600;">Download the app</a> &nbsp;·&nbsp;
<a href="https://aun-projector.com.bd/app-privacy-policy/" style="color:#0188fe;font-weight:600;">App privacy policy</a> &nbsp;·&nbsp;
<a href="https://aun-projector.com.bd/delete-account/" style="color:#0188fe;font-weight:600;">Delete your account</a>
</p>
[/col]

[col span="5" span__sm="12" align="center"]
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;text-align:center;">
<div style="width:56px;height:56px;margin:0 auto 12px;border-radius:16px;background:linear-gradient(135deg,#0188fe,#01bafe);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;"><i class="fa-solid fa-mobile-screen-button"></i></div>
<p style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 4px;">AUN Care Bangladesh</p>
<p style="font-size:13px;color:#64748b;margin:0;">Free · Android 7.0+ · বাংলা &amp; English</p>
</div>
[/col]

[/row]

[/section]
```

---

## 7. Still to do (not blocking, but soon)

- **Link the privacy policy from inside the app** (Settings → a "Privacy policy" row opening the
  URL). Not strictly required by Play when the store listing has it, but expected for an app with
  accounts, and it is a one-line change. Say the word.
- The **`.aab` build** for Play (see section 5).
