# AUN Care app — site-wide rollout & SEO audit

*Revised. The first version put a promo box on 21 pages; this one uses a box on 11
and a single inline sentence on 10. The reasoning is in §1 — it matters more than
the placement list.*

Audit of all 42 page exports in the working directory. Prerequisite:
`/aun-care-app/` is published and the **AUN Care App — Promo & Callouts** plugin
(v1.2.0) is active.

---

## 1. What this does and does not do

Be clear-eyed about the mechanism, or the effort goes to the wrong place.

**Adding an app callout to a page does not help THAT page rank.** Promotional copy
about a different product is not topical content for the spare-parts page. What it
actually does is narrower:

- **Passes internal link equity to `/aun-care-app/`** — that helps *the app page*
  rank. Real and well-established, but concentrated on one page.
- **Drives installs** — a business goal, worth doing on its own merits, but not SEO.

**The FAQ additions (§3) are the item that actually feeds AI answers.** Assistants
quote question-and-answer pairs because a question matching the user's phrasing is
the cleanest thing to lift. No assistant will ever quote "After you buy, we are
still here." Do §3 first.

### Box or inline?

A bordered card with an icon, heading and gradient button is the visual signature of
an advertisement. On one page it reads as helpful; repeated across twenty it becomes
banner-blind wallpaper while still pushing real content down. On product pages it
also competes with your own "add to cart".

So there are two styles, and the choice is about **whether the app is the answer to
the page's question, or merely a useful aside**:

```
[aun_app_callout type="repair|parts|warranty|support|choose|owner"
                 style="box|inline" theme="light|dark"]
```

| Style | Use when | Renders |
|---|---|---|
| `box` (default) | The app **is** the answer to what this page is about | Icon card, heading, paragraph, button |
| `inline` | The app is a **useful aside** — don't compete with the page's own CTA | One sentence with a link, in the flow |

`theme="dark"` is only for the `rgb(9,15,28)` cinema bands.

Six variants, each with **genuinely different copy** in both styles — repeating one
identical block is boilerplate, and search engines largely discount boilerplate when
judging what a page is about.

---

## 2. Where each variant goes

### Tier 1 — the app does exactly what this page does (highest value)

| Page | Shortcode | Place it |
|---|---|---|
| `repair status` | `[aun_app_callout type="repair"]` | Directly under the `[slb_repair_tracker]` shortcode, above "How it works" |
| `send-projector-page` | `[aun_app_callout type="repair"]` | After the Pathao receiver block, before the FAQ |
| `spare-parts-request-page` | `[aun_app_callout type="parts"]` | Under `[aun_spare_parts]`, above "How to Request a Spare Part" |
| `spare-parts-status-page` | `[aun_app_callout type="parts"]` | Under the tracker, above the FAQ |
| `Warranty Register` | `[aun_app_callout type="warranty"]` | Under the registration form, above the FAQ |
| `warranty policy` | `[aun_app_callout type="warranty"]` | After the coverage table, before "Last updated" |
| `track order` | `[aun_app_callout type="warranty"]` | Under `[aun_live_tracking]` |
| `contact us` | `[aun_app_callout type="support"]` | Above the "Talk to a Human" panel |
| `help-center-page-content` | `[aun_app_callout type="support"]` | Under the Help Center tool, above the cross-links |

### Tier 2 — pre-purchase tools

The Planner, "Help me choose" and AR preview make the app a **top-of-funnel** asset,
not just an after-sales one. That is the most under-used fact you have.

**Box** — on the two pages where "which projector, and what size?" *is* the question:

| Page | Shortcode | Place it |
|---|---|---|
| `projector finder` | `[aun_app_callout type="choose"]` | Under `[aun_projector_finder]` |
| `projector compare` | `[aun_app_callout type="choose"]` | Under the compare tool |

**Inline** — category pages are doing keyword work; a promo card there dilutes them:

| Page | Shortcode | Place it |
|---|---|---|
| `projector price category page` | `[aun_app_callout type="choose" style="inline"]` | **Bottom** description, above the FAQ |
| `home theater projector category page` | same | Bottom description |
| `mini projector category page` | same | Bottom description |
| `office projector category page` | same | Bottom description |

> Never the **top** category description — that box is doing the head-term work.

### Tier 3 — product pages (inline only)

| Page | Shortcode | Place it |
|---|---|---|
| `u001 pro product page` | `[aun_app_callout type="owner" style="inline"]` | Just under the FAQ accordion, before the trust row |
| `u002 product page` | same | same |
| `a004 pro product page` | same | same |
| `a005 product page` | same | same |
| `a32 pro product page` | same | same |
| `A45 Pro Product Page` | same | same |

> ⚠️ **Inline, not a box, and this is deliberate.** A product page has one job. A
> bordered promo between the FAQ and the "1 Year Warranty / Fast Delivery / Easy EMI"
> row competes with your own buy path — a conversion cost, not just an aesthetic one.
> One sentence of after-sales reassurance supports the sale instead of interrupting
> it. If you would rather keep product pages completely clean, **skipping this tier
> costs you very little** — the spec-tab row in §4 already covers it.

### Tier 4 — brand story

| Page | Shortcode | Place it |
|---|---|---|
| `about us` | `[aun_app_callout type="owner"]` | After the "since 2019" / counter section |

> On About Us the app is not a feature, it is **evidence**. The page claims you stay
> after the sale; the app is the thing that proves it. Box is right here.

---

## 3. FAQ additions — the part that actually feeds AI

**This is higher-value than the callouts.** Assistants quote question-and-answer
pairs, because a question that matches the user's phrasing is the cleanest thing to
lift. A callout box is a link; an FAQ entry is a *quotable answer*.

These use the existing hand-rolled `.aun-faq` markup, so they drop straight into the
FAQ blocks already on those pages. Paste each `<details>` inside the existing
`<div class="aun-faq">`.

**Every question below is checked against what that page already asks**, so none of
them duplicate an existing entry — a near-duplicate question is worse than no
question, because it splits the answer an assistant would otherwise quote.

> ⚠️ **`contact us` and `help-center` have no FAQ section at all**, so there is
> nothing to add to. They get the callout only. `warranty policy` uses numbered
> `[accordion]` legal clauses in English and Bangla — an app question would be out of
> place there; the callout is enough.

**`repair status` — add:**
```html
<details><summary><span>Can I track my repair on my phone?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes. The free <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> shows your repair moving from received, to repaired, to on its way back, with courier tracking and a notification at each stage. You can also keep using this page &mdash; both show the same live status from our service system.</div></details>
```

**`spare-parts-request-page` — add:**
```html
<details><summary><span>Can I order spare parts from my phone?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes. In the <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> you can request a part, receive the price, approve it, pay online or choose cash on delivery, and then track each item to your door. Parts covered by warranty are free, and nothing is ordered until you approve the cost.</div></details>
```

**`Warranty Register` — add:**
```html
<details><summary><span>Can I check my warranty in an app?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes. Scan the barcode on the box with the <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> and it shows whether you are still covered and exactly how many days remain. If you bought directly from us, your purchase is often linked automatically using your phone number, so there is nothing to type in.</div></details>
```

**`spare-parts-status-page` (Track Spare Parts Request) — add:**
*(Its 7 existing questions cover tracking, statuses, approving a quote, photos, timing
and missing requests — so this one is about not having to check at all.)*
```html
<details><summary><span>Can I get updates without checking this page?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes. The free <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> notifies you the moment a part changes status, so you do not have to keep opening this page to see if anything moved. You can also approve a quote and pay from the app, and every part of the request is listed with its own status.</div></details>
```

**`send-projector-page` (Projector Repair Service in Bangladesh) — add:**
*(Its 8 existing questions stop at "what happens after you receive it" — none cover
the return leg, which is the part customers actually worry about.)*
```html
<details><summary><span>How will I know when it is on its way back to me?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">The free <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> follows the whole job &mdash; received, under repair, repaired, and then dispatched with the return courier details &mdash; and sends you a notification at each stage, so you are not left guessing after you hand the projector over. You can also check the <a href="https://aun-projector.com.bd/repair-status/">repair status page</a> any time.</div></details>
```

**`track order` — add:**
*(Its 5 existing questions are all about the delivery itself; this one picks up
where they stop.)*
```html
<details><summary><span>What happens after my order arrives?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Install the free <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> and sign in with the same phone number you ordered with &mdash; your purchase and its warranty are linked automatically, so you can see how many days of cover you have left without keeping the invoice. From there you can also order spare parts, book a repair, and get manuals and firmware for your exact model.</div></details>
```

**`projector compare` — add:**
*(Its 6 existing questions are all about the comparison tool; none answer whether the
projector suits the buyer's actual room.)*
```html
<details><summary><span>Specs aside, how do I know it will fit my room?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">A spec sheet cannot tell you how big the picture will be on your wall. The free <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> has a Projector Planner that works out what screen size fits your room and how far back the projector needs to sit, plus an AR preview that shows the picture on your own wall &mdash; and you do not need to own a projector to use either.</div></details>
```

**`projector finder` — add:**
```html
<details><summary><span>Can I work out the screen size before I buy?</span><i class="fa-solid fa-chevron-down aun-chev"></i></summary><div class="aun-faq-a">Yes, and you do not need to own a projector to do it. The <a href="https://aun-projector.com.bd/aun-care-app/">AUN Care app</a> has a Projector Planner that shows what screen size fits your room and how far back the projector needs to sit, plus an AR preview so you can see the picture on your own wall before you spend anything.</div></details>
```

⚠️ If any of these pages has **FAQ schema** generated separately, add the new
question there too, or the schema and the visible page will disagree.

---

## 4. Product-page spec tabs — one line each

The six `* specification tab.txt` files are pure spec tables. Add one row at the
bottom of each — it is small, but it puts the app in the part of the page buyers
actually read:

```html
<tr><td><strong>App support</strong></td><td>AUN Care for Android &mdash; warranty, spare parts, repairs and model-specific guides (<a href="https://aun-projector.com.bd/aun-care-app/">free download</a>)</td></tr>
```

---

## 5. Deliberately NOT touched — and why

Restraint is part of the strategy. Adding the app here would read as spam, dilute
pages that are doing a different job, and weaken the boilerplate signal on the pages
where it *does* belong.

| Page | Why not |
|---|---|
| `Privacy Policy`, `terms and condition`, `shipping policy` | Legal pages. A marketing CTA here erodes trust exactly where you need it. |
| `return and replacement` | The app does not handle returns. Claiming otherwise creates a support burden. |
| `emi` | Payments and financing, unrelated to the app. |
| `authorized dealer` | Tempting (dealer devices need manual registration) but a stretch — the page is about finding a shop. |
| `projector brightness explained` | Educational page winning on neutrality. A product CTA undercuts that. |
| `aun-app-privacy-policy`, `aun-app-delete-account` | Already app pages. |
| `tutorial` | A 436-byte shortcode wrapper, no body content. |
| `homepage` | Done — teaser section. |
| `short description sample`, `warranty tab` | Fragments, not pages. |

---

## 6. The mobile install bar — what platforms actually use

Included in plugin v1.2.0, **on by default**, no shortcode needed. A slim dismissible
bar at the bottom of the screen on Android phones: icon, "AUN Care — Warranty, parts
& repairs — free", a **Get** button, and a dismiss ×.

This is the mechanism Amazon, Booking and Uber use to drive installs — not promo
blocks scattered through the content. It costs you zero content real estate and
outperforms them.

### How often it appears — the part that decides whether people resent it

**A realistic visitor sees this at most once, and many never see it at all.**

| Rule | Behaviour |
|---|---|
| **Never on the first page of a visit** | Someone who lands and leaves is never interrupted. Only people already browsing can see it. |
| **From the 2nd page: once per visit** | Not once per page. Shown a single time, then silent for the rest of the session no matter how many pages they open. |
| **Only after engagement** | Appears after they scroll ~25% or dwell ~6 seconds — never on arrival. |
| **Dismissed → silent 60 days** | Asking again tomorrow is how you train people to hate a site. |
| **Tapped "Get" → silent 180 days** | They have seen the app page; stop selling. |
| **Never on cart, checkout or account pages** | Interrupting a purchase is the worst thing this could do. |
| **Never on the app's own pages** | They already carry the download. |

Worked example: a visitor lands on a product page (nothing), opens a category page
and scrolls (bar appears once), then visits four more pages (nothing). They dismiss
it — and see nothing for two months.

### The engineering choices behind it

| Behaviour | Why |
|---|---|
| **Android phones only**, decided in the browser | iOS has a native Smart App Banner meta tag; Android has **no equivalent** for a native app (Chrome's prompt is PWA-only), so this has to be custom. ⚠️ The check must be client-side — deciding server-side would be **cached by WP Rocket and then served to desktop and iPhone visitors too**. |
| **Slim and dismissible, never full-screen** | Google treats intrusive mobile interstitials as a ranking negative. A small bar is fine; a popup would actively cost you. |
| **`position:fixed`, and it pads the body** | Cannot cause layout shift (CLS is a Core Web Vital), and cannot cover your footer. |
| **The dwell timer waits for the tab to be visible** | A link opened in a background tab would otherwise burn the one impression while the visitor never saw it. |
| **Reveals without `requestAnimationFrame`** | rAF does not fire in a background tab, which would leave the bar marked "shown" but invisible. A forced reflow does the same job and always runs. |

Turn it off with:

```php
add_filter( 'aun_app_banner_enabled', '__return_false' );
```

*Verified in a browser: hidden on pageview 1 even when scrolled 60%; appears on
pageview 2 after the dwell timer; stays hidden on pageview 3 despite an 80% scroll;
dismiss writes a 60-day mute and clears the body padding; suppressed on a Windows
desktop UA; and `/cart/`, `/checkout/`, `/my-account/` and the app pages render no
markup at all.*

---

## 7. Order of work

1. Publish `/aun-care-app/` — **everything below links to it.**
2. Install/activate plugin **v1.2.0**. The mobile bar starts working immediately.
3. **The six FAQ additions (§3).** Highest value on this page — do them first.
4. **Tier 1** boxes (9 pages).
5. Tier 2 (2 boxes + 4 inline).
6. Tier 3 inline + Tier 4, and the spec-tab rows (§4).
7. Purge WP Rocket cache.
8. Search Console → request indexing for `/aun-care-app/` and the Tier-1 pages.

**Do not do everything in one sitting and request indexing on all of it at once.**
Ship steps 3–4, let it settle a week, then continue — if something reads oddly you
have changed nine pages, not twenty-one.

---

## 8. On launch day (Play Store)

Because everything routes through the shortcode and the landing page, launch day is:

1. `callout.php` — no change needed (all variants point at the page, not the APK).
2. `/aun-care-app/` — uncomment the Play button, add `sameAs` to the schema, rewrite
   the "why is it not on Play" FAQ answer.
3. Footer block — swap the badge for the official Google Play badge.

Twenty-one pages update themselves. That is the whole point of the shortcode.
