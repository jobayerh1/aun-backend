# Where the two new pages need to be linked

Short answer: **Play only needs the two URLs pasted into Play Console.** Nothing else is a hard
requirement. But three cheap additions make the app look properly maintained and remove the small
risk of a reviewer deciding the pages look orphaned.

---

## 1. Website footer — recommended, not required

Right now the pages are only reachable if you know the URL. That's technically fine, but:

- A customer who wants to delete their account and has already uninstalled the app has no way to
  find the page except by searching.
- Reviewers occasionally look at whether a policy page is genuinely part of the site or a page put
  up only for the submission.

Add both to the footer, next to your existing Privacy Policy link:

```html
<a href="/app-privacy-policy/">App Privacy Policy</a> &nbsp;·&nbsp;
<a href="/delete-account/">Delete Your Account</a>
```

Keep your existing `/privacy-policy/` link exactly where it is — that one covers the website and
the shop, and the two are deliberately different documents.

**One useful extra:** put the deletion link on the APK download page too
(`/get-aun-care-app/`). Someone deciding whether to install is exactly the person who wants to know
they can get out again.

---

## 2. Inside the app — done in this build

- **Privacy policy** — new row in Settings (under "Report a problem"), opens the page in the
  browser. Play expects an app with accounts to link its policy from inside the app, not only from
  the store listing.
- **Delete my account** — already in Settings; deletion happens in the app itself, which is the
  form Play prefers. The web page is the fallback for people who have already uninstalled.

Both URLs now live in one place in the code (`lib/src/env.dart`), so if a slug ever changes there's
a single line to edit.

---

## 3. smartliving.com.bd — one small block, worth doing

Your **developer account** will be verified as *Smart Living Bangladesh* with
`smartliving.com.bd` as its website. A reviewer starting from that website currently finds no trace
of the app at all, while the policy sits on a different domain. That gap is easy to close and
removes the only weak link between your legal entity and the policy host.

The ready-made block is in **`aun-app-play-store-checklist.md` → section 6** — a short "AUN Care —
our customer app" section with three links (download, app privacy policy, delete account). Paste it
into any existing page, or make a small `/aun-care-app/` page.

Nothing else on the parent site needs to change. In particular you do **not** need to duplicate the
privacy policy there — one authoritative copy, linked from both sites, is better than two copies
that will eventually disagree.

---

## Summary

| Where | What | Needed? |
|---|---|---|
| Play Console | Both URLs | **Required** |
| App Settings | Privacy policy link | Done in v1.45 |
| App Settings | Delete my account | Already there |
| aun-projector.com.bd footer | Both links | Recommended |
| APK download page | Delete-account link | Nice to have |
| smartliving.com.bd | The pointer block | Recommended |
| smartliving.com.bd | A second copy of the policy | **No** — one copy, linked twice |
