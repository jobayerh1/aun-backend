# Firmware release — ready-to-paste copy

For **AUN App → Content → Firmware**, one entry per release. Three boxes get filled:
**What's new**, **Installation steps** (the Wi-Fi/OTA ones), and the new
**If the update fails → offline installer** block.

> Everything below is HTML. Paste it with the editor's **Text** tab, not Visual, or the
> tags become visible text.

---

## 1. What's new

Yours, unchanged apart from plain-language endings — this is the part that decides whether
anyone starts the update at all, so it says what the customer will *notice*, not what we changed.

```html
<ul>
  <li><strong>Sharper auto-focus</strong> — the picture settles faster and stops hunting after you move the projector.</li>
  <li><strong>Better distance sensing (TOF)</strong> — fewer wrong focus jumps on textured walls and screens.</li>
  <li><strong>Steadier streaming apps</strong> — fixes apps closing on their own during playback.</li>
  <li><strong>General system improvements</strong> — small speed and stability fixes.</li>
</ul>
```

---

## 2. Installation steps (over Wi-Fi)

Two additions to what you have: **how long it takes**, and **what to do if it stalls** — so a
stuck bar is an expected event with an answer, not a failure.

```html
<p>The projector downloads this update itself. Keep it plugged in and connected to Wi-Fi
until it finishes — usually 10–20 minutes.</p>
<ol>
  <li>On the projector, go to <strong>Settings</strong>.</li>
  <li>Select <strong>WLAN</strong> and connect to your Wi-Fi network.</li>
  <li>Go to <strong>About &gt; Online Update</strong> and start the update.</li>
  <li>Wait for the projector to restart by itself. It may show a blank screen for a minute.</li>
</ol>
<div class="warning-box">⚠️ <strong>Important</strong><br />
Do not switch the projector off or unplug it while it is updating.<br />
It restarts on its own — that is normal, please wait for it.<br />
If the progress bar stops moving for more than 15 minutes, switch the projector off and on,
move it closer to your router, and start again. If it still will not finish, use the
<strong>offline update</strong> below — it is the same version.</div>
```

---

## 3. If the update fails → offline installer

**File:** the offline `.zip` of **the same version**. **Size:** e.g. `412 MB`.
**Steps** (adjust the menu names to match the model — this is the part customers follow blind):

```html
<p>Use this when the Wi-Fi update will not finish. It installs <strong>the same version</strong> —
nothing is skipped.</p>
<p><strong>You will need:</strong> a USB pen drive (8 GB or more), formatted <strong>FAT32</strong>.</p>
<ol>
  <li>Tap <strong>Download</strong> below and wait for the file to save. You can pause it and
      continue later — it will not start over.</li>
  <li>Copy the file to the USB drive. Keep it in the <strong>main folder</strong> of the drive,
      not inside any folder, and do not unzip it.</li>
  <li>Plug the drive into the projector's <strong>USB</strong> port.</li>
  <li>On the projector, go to <strong>Settings &gt; About &gt; Local Update</strong> (or
      <strong>System Update &gt; Local</strong>) and select the file.</li>
  <li>Confirm, then wait. The projector restarts by itself when it is done.</li>
</ol>
<div class="warning-box">⚠️ <strong>Important</strong><br />
Keep the projector plugged in and do not remove the USB drive while it is updating.<br />
Do not rename the file.<br />
Still stuck? Message us on WhatsApp and we will do it for you at the service centre.</div>
```

---

## Two rules for this to keep working

1. **One entry per release.** The offline file belongs on the *same* firmware entry, in the
   "If the update fails" block. Publishing it as a second Firmware item sends a second
   "new firmware" notification and leaves the customer guessing which one is newer.
2. **Same version in both files.** The app shows one version number for both paths, taken from
   the entry. If the zip is an older build, nothing on screen can say so.

Use **Test download from server** next to the offline file before saving — it checks that the app
would receive a real file rather than a OneDrive web page.
