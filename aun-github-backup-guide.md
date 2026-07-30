# Backing up your work to a private GitHub account

Everything on your side is already prepared. Two local repositories exist and the
first snapshot of all your work is saved into them. What's left is the part only
you can do: **create the account** (I must never create accounts or type
passwords on your behalf) and then run the backup script once.

---

## First, your three questions answered honestly

**Is it free for life?**
Unlimited **private** repositories on the GitHub Free plan cost nothing, with no
size or time limit for what you're doing here (your two repos are ~10 MB and
~1.5 MB — GitHub's soft limit is 1 GB per repo). Private repos have been free
since 2019. Nobody can promise a company's pricing forever, but there is no
signal this is changing, and if it ever did, your work is still on your PC and
can move to another host in minutes. It is not a lock-in.

**Can anybody see my code?**
A private repo is not listed, not searchable, and not viewable by anyone except
you and people you explicitly invite. Two honest caveats:

- A small number of GitHub staff can technically access private repositories in
  narrow cases (a support request you file, a legal order, an abuse
  investigation). That is true of every hosting provider, including a private
  server.
- **Your account is the real lock.** If someone gets your password, they get
  everything. So: use a password you use nowhere else, and turn on **two-factor
  authentication** immediately (Settings → Password and authentication). This is
  the single most important step on this page.

**Will it back up automatically whenever we change something?**
Not by itself — Git only saves when told. You have two options, and I'd suggest
both:
1. **`backup-to-github.cmd`** in this folder — double-click it any time. It saves
   and uploads both projects and tells you plainly what happened.
2. I'll run the backup at the end of each work session from now on, so you don't
   have to remember.

If you'd like it to run on a schedule as well (say every evening at 8pm), say so
and I'll set up a Windows scheduled task.

---

## ⚠️ Before anything: what is deliberately NOT being uploaded

I scanned every file that was about to be uploaded and found **live credentials**
that would have been exposed. These are now excluded and stay only on your PC:

| File | Why it must never be uploaded |
|---|---|
| `ERP/aun maintenance sms/env` | Your ERP's **live database and mail passwords** |
| `aun-projector-firebase-adminsdk-*.json` | Firebase **service-account key** — full access to your Firebase project |
| `rank-math-settings-*.json` | Contains a live Google Maps API key and your Search Console token |
| `inFlow_SalesOrder.csv` | **Customer/sales data** — personal information does not belong in a code repo |
| `android/key.properties`, `aun-upload-key.jks` | Your app **signing key and its passwords** |
| APKs, zips, logs, `build/` folders | Large and rebuilt anyway (GitHub also rejects files over 100 MB) |

Nothing was committed before this check, so none of it is in the history.

**Two things worth doing separately, when you have a moment:**
- Move that Firebase service-account JSON and the ERP `env` file out of the
  Downloads folder into a password manager or an encrypted folder. A working
  folder is an easy thing to accidentally zip up and share.
- Your keystore backup (from the keystore guide) still matters — GitHub is not
  backing it up, deliberately.

---

## Step 1 — Create the account (5 minutes, you do this)

1. Go to **https://github.com/signup**
2. Use an email you'll keep — `jobayerh@gmail.com` is fine.
3. Pick a username. It's public, so something like `smartliving-bd` or
   `jobayer-aun` rather than anything personal.
4. Verify the email GitHub sends you.
5. **Turn on two-factor authentication right away:** click your avatar (top
   right) → **Settings** → **Password and authentication** → **Enable
   two-factor authentication**. Use an authenticator app on your phone.
   Save the recovery codes it gives you somewhere safe — they are how you get
   back in if you lose the phone.

You do **not** need a paid plan. Ignore any upgrade prompts.

---

## Step 2 — Create two empty private repositories (you do this)

Go to **https://github.com/new** and create the first one:

- **Repository name:** `aun-care-app`
- **Description:** *AUN Care Bangladesh — Flutter customer app* (optional)
- Select **Private** ← the important one
- **Do NOT** tick "Add a README", ".gitignore" or "license". Leave all three
  empty — your folder already has content, and adding files here creates a
  conflict on the first upload.
- Click **Create repository**

Then repeat at **https://github.com/new** for the second:

- **Repository name:** `aun-backend`
- Select **Private**
- Again, **do not** add a README, .gitignore or license.

---

## Step 3 — Tell me your username

Reply with the username you chose and I'll connect both folders to your
repositories (one command each). Or do it yourself — replace `YOUR-USERNAME`:

```bash
cd /d C:\dev\aun-app && git remote add origin https://github.com/YOUR-USERNAME/aun-care-app.git
```

```bash
cd /d "C:\Users\Jobayer Hossain\Downloads\Claude session" && git remote add origin https://github.com/YOUR-USERNAME/aun-backend.git
```

---

## Step 4 — The first upload (you double-click)

Double-click **`backup-to-github.cmd`** in this folder.

The first time, a browser window opens asking you to sign in to GitHub and
authorise Git. Approve it. That happens **once** — after that the script runs
silently.

When it finishes you'll see *"ALL BACKED UP"*. Refresh your repository page on
github.com and your files will be there.

---

## After that

- **Double-click `backup-to-github.cmd`** whenever you want a snapshot — after a
  good work session, before trying something risky, before travelling.
- Every backup keeps the previous versions too, so you can go back to how a file
  looked last week, not just restore the latest copy.

### If your laptop is lost or replaced

On the new machine: install Git and Flutter, sign in to GitHub, then:

```bash
git clone https://github.com/YOUR-USERNAME/aun-care-app.git C:\dev\aun-app
```

```bash
git clone https://github.com/YOUR-USERNAME/aun-backend.git
```

Everything comes back except the deliberately-excluded secrets — which is why
the keystore and the Firebase key need their own backup in a password manager.
Without the keystore you cannot publish an app update, so please don't skip that.
