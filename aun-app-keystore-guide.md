# Creating your real upload keystore — step by step

**Do this yourself.** I've wired the build to use the key, but I must not generate or hold your
signing material — whoever has the keystore file plus its passwords can publish an update that
Android accepts as genuinely yours. It stays on your machine only.

Time: about 10 minutes.

---

## Before you start: what this key is, in plain terms

Android refuses to install an update unless it is signed with the **same key** as the version
already installed. That's the whole security model — it's how the phone knows an update really
came from you and not from someone who repackaged your APK.

Right now your APK is signed with Flutter's **debug key**, which is a throwaway key that exists on
every developer machine. That's why Play Protect warns on install, and it's why Play will reject
the upload outright.

With **Play App Signing** (on by default for new apps) there are two keys:

| Key | Who holds it | What it does |
|---|---|---|
| **Upload key** — the one you're making now | You | Signs what you upload to Play |
| **App signing key** | Google | What Play re-signs with before sending to phones |

That split is good news: if you ever lose the upload key, Google can reset it. Losing it is
recoverable. But **keep it safe anyway** — a reset is a support round-trip you don't want mid-launch.

---

## Step 1 — Create the keystore

Open **Command Prompt** (not PowerShell — the quoting differs) and paste this **as one line**:

```bash
"C:\dev\jdk-21.0.11+10\bin\keytool.exe" -genkeypair -v -keystore C:\dev\aun-app\android\aun-upload-key.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10950 -alias aun-upload
```

What the parts mean:
- `-validity 10950` = 30 years. Play requires a key valid past **22 October 2033**; 30 years clears
  it comfortably. Do not use a short validity — an expired key is a real problem.
- `-alias aun-upload` = the name of the key inside the file. Remember it; it goes in the config.

It will ask you a series of questions:

| Prompt | What to enter |
|---|---|
| Enter keystore password | **Choose a strong password. Write it down now.** Nothing is displayed as you type — that's normal. |
| Re-enter new password | Same again |
| What is your first and last name? | `Smart Living Bangladesh` |
| What is the name of your organizational unit? | `AUN` |
| What is the name of your organization? | `Smart Living Bangladesh` |
| What is the name of your City or Locality? | `Dhaka` |
| What is the name of your State or Province? | `Dhaka` |
| What is the two-letter country code? | `BD` |
| Is CN=..., OU=..., ... correct? | type `yes` and press Enter |
| Enter key password for `<aun-upload>` | **Press Enter** to reuse the keystore password (simplest, and fine) |

These details are baked into the certificate and **cannot be changed later**, so use the legal
company name — it should match your D-U-N-S registration.

You should now have: `C:\dev\aun-app\android\aun-upload-key.jks`

---

## Step 2 — Tell the build about it

Create a new file at **`C:\dev\aun-app\android\key.properties`** (there's a template next to it,
`key.properties.example`). Put in exactly this, with your real passwords:

```properties
storePassword=THE_KEYSTORE_PASSWORD_YOU_JUST_CHOSE
keyPassword=THE_SAME_PASSWORD_IF_YOU_PRESSED_ENTER
keyAlias=aun-upload
storeFile=aun-upload-key.jks
```

Notes:
- No quotes around the values, no spaces around the `=`.
- If your password contains a backslash `\`, double it (`\\`).
- `storeFile` is relative to the `android` folder, so this points at the file from step 1.

Both `key.properties` and `*.jks` are already in `.gitignore`, so they can't be committed by accident.

---

## Step 3 — Build

Double-click `build-aun-app.cmd` as usual. The last screen now tells you which key was used:

```
Signed with: RELEASE (your upload key)
```

If it says **DEBUG KEY**, the build did not find `key.properties` — check the filename (no `.txt`
on the end; Windows hides extensions by default) and that it's in `C:\dev\aun-app\android\`.

---

## Step 4 — Verify it actually worked

In Command Prompt:

```bash
"C:\dev\jdk-21.0.11+10\bin\keytool.exe" -printcert -jarfile "C:\Users\Jobayer Hossain\Downloads\Claude session\AUN-Care-Bangladesh.apk"
```

You want to see `Owner: CN=Smart Living Bangladesh, ...`.
If it says `CN=Android Debug, O=Android, C=US`, it's still the debug key.

Keep the **SHA-256** fingerprint it prints — Play shows the same value once you upload, and matching
them confirms the right key went up.

---

## ⚠️ Step 5 — The one thing that will surprise you

**The new APK will not install over the old one.** Android sees a different signature and refuses
with "App not installed" — this is the security model working correctly, not a bug.

So, once:
1. On every phone that already has the app (yours, staff, testers): **uninstall it first**, then
   install the new APK.
2. On the website download page, nothing changes — but anyone updating from an older sideloaded
   copy has to uninstall first. Worth a line on the download page if you have testers out there.
3. Uninstalling clears the app's local data. Not a problem: everything real lives on the server, so
   signing in again with the same number restores devices, warranty, tickets and requests.

After this one-time break, every future build installs over the previous one normally.

**Do it now, before you have many users** — the same break would be far worse later.

---

## Step 6 — Back up the keystore properly

Right now this file exists in exactly one place, on one PC.

- Copy `aun-upload-key.jks` somewhere durable and private: a password manager's file attachment, an
  encrypted drive, or a company OneDrive folder that only you and the owner can open.
- Store the two passwords in a **password manager**, not in the same folder as the key, and not in
  a text file on the desktop.
- Do **not** email it or send it over WhatsApp.
- The `.jks` file alone is useless without the password, and the password is useless without the
  file — keeping them apart is the point.

---

## What about Firebase / push notifications?

**Nothing to do.** FCM doesn't use the signing certificate, so push keeps working after re-signing.
(SHA-1 registration in Firebase is only needed for Google Sign-In, Dynamic Links and App Check —
none of which you use.)

## What about OTP auto-fill?

**Nothing to do, and this is worth knowing.** Android's SMS Retriever hash is derived from the
signing key, so it changes with the new key. The app already computes its hash at runtime and sends
it with each OTP request — that's why it was built that way. It'll simply start sending the new
hash. Nothing is hardcoded, nothing to update on the server.

Do test one real login after the first release-signed build, just to see it auto-fill.

---

## Later, when the developer account is ready

Keep building APKs as you are now — that's the right call while the D-U-N-S is pending. When the
account exists, Play needs an **`.aab`** app bundle instead. It uses the same keystore and the same
config you just set up; only the build command changes. Tell me then and I'll add a
`build-aun-app-bundle.cmd` alongside the APK one, so you keep both:

- **APK** → the direct download on your website
- **AAB** → Play Store uploads
