# Deploying `smart-living-site` v1.0.4 to smartliving.com.bd

One plugin renders the whole parent-company site — its own templates, all the copy, the SEO and the
schema. Flatsome stays installed but renders none of these six pages.

Everything below is done from **WP Admin**. No SSH or terminal needed.

---

## 1. Install

1. **Plugins → Add New → Upload Plugin** → `smart-living-site.zip` → **Install Now** → **Activate**.
2. Activation automatically finds the six existing pages and adopts them by their current slug.
   Your URLs do not change and no page content is touched.
3. Check **Settings → Reading**: "Your homepage displays" must be **A static page**, with Homepage set
   to your existing Home page. It already is — just confirm.
4. Go to **Settings → Smart Living Site** and press **Build / check pages**. You should see six lines
   reading `adopted` or `exists`.

Visit the site. The home page should now show the dark teal hero with the animated gold circuit field.

## 2. Connect the contact form

The plugin does not handle mail — your existing **Contact Form 7** does, so Akismet keeps protecting it.

1. **Contact → Contact Forms** → open your form (or add a new one).
2. Replace the form template with this, so the fields match the design and the topic dropdown feeds the
   enquiry reporting in the SEO plan:

```
<label>Your name
  [text* your-name autocomplete:name akismet:author] </label>

<label>Email
  [email* your-email autocomplete:email akismet:author_email] </label>

<label>Phone
  [tel your-phone placeholder "01XXXXXXXXX"] </label>

<label>What is this about
  [select your-topic "Product enquiry" "Support for something I own" "Corporate or bulk supply" "Dealer or partnership" "Something else"] </label>

<label>Message
  [textarea* your-message maxlength:3000] </label>

[turnstile appearance:interaction-only size:flexible theme:light]

[submit "Send message"]
```

⚠️ **The `akismet:` options are not optional.** Contact Form 7 only sends a submission to Akismet if at
least one field carries `akismet:author`, `akismet:author_email` or `akismet:author_url` — without them
Akismet is never asked, even when it shows "active". (CF7 source, `modules/akismet/akismet.php`:
`wpcf7_akismet_submitted_params()` returns false and the check is skipped.) The first version of this
template left them out, which is why spam got straight through.

`[turnstile]` renders nothing until Cloudflare Turnstile is configured under *Contact → Integration*, so
it is safe to paste now. Without the tag, CF7 would insert the widget at the **top** of the form.

3. On the **Mail** tab:

   - **Subject:** `[your-topic] from [your-name] — smartliving.com.bd`
     (the form has no `your-subject` field; using it prints the literal text `[your-subject]`)
   - **Additional headers:** `Reply-To: [your-email]`
   - **Message body:**

```
Name:    [your-name]
Email:   [your-email]
Phone:   [your-phone]
About:   [your-topic]

[your-message]

--
Sent from the contact form on smartliving.com.bd
```

   - Untick **Use HTML content type** — the body is plain text, and spam HTML then can never render.

4. Copy the form's shortcode and paste it into **Settings → Smart Living Site → Contact Form 7 shortcode**.

Until you do this, the contact page shows an "Email us instead" button rather than a broken form.

## 2b. Upgrading

Upload the new zip the same way (WordPress will ask to replace the existing plugin — say yes), then
**clear the WP Rocket cache**. No settings are lost and no pages are touched.

**What changed in 1.0.4**

- **"Mirpur" removed from all prose.** It read awkwardly in a sentence and means little to a buyer outside
  Dhaka, so seven sentences now say "Dhaka", "our own bench", or drop the location entirely. The home
  page's closing headline is now "Talk to a person, not a form reply." The contact page lede is now
  "…all four reach the same desk."
- **It remains in the postal address only** — the footer, the Visit us card, the About page's head-office
  row, the schema, and the contact page's meta description. Those are the address, not a sentence, and
  removing the district would make the address less useful to a courier. If you want it gone from there
  too it is one field: **Settings → Smart Living Site → Address line 2**. No code change.

**What changed in 1.0.3** — from the full live audit

- **The home page no longer ends by repeating its own footer.** The old closing band showed the address,
  phone, email and opening hours in a four-column grid, directly above a footer carrying the same four
  things. Measured on the live page: the address appeared twice, the phone twice and the opening hours
  three times. That band is now a closing call to action — one line, a Contact button and a tap-to-call
  number. Reference details live in the footer only.
- **The vertical rhythm is tighter.** Every section boundary carried 266–288 px of empty space, because
  both neighbours add their own padding. On a dark→dark boundary that reads as a void rather than as
  breathing room. `--band` is reduced, and two adjacent dark sections now collapse the boundary. The home
  page is 738 px shorter with nothing removed but emptiness.
- **Brand text colour restored.** Flatsome prints its Customizer CSS *inline* into `wp_head`, which cannot
  be dequeued like a stylesheet. It sets `body { color:#333 }` and, being later in the document, won — so
  the three brand paragraphs on the home page rendered in generic grey instead of the brand ink `#0F2B29`.
  Pinned via the body class.
- **Titles and meta descriptions trimmed** to what Google actually shows. Four titles were 68–71 chars
  (truncates ~60) and four descriptions were 180–210 chars (truncates ~160). All now 47–60 and 107–148.
- **Heading levels fixed.** The credentials band and the values grid used `h4` directly under an `h2`,
  skipping `h3` — an accessibility and outline problem. Both are now `h3`.
- **Dead resource hints removed.** `dns-prefetch` for fonts.googleapis.com and use.fontawesome.com were
  still being emitted for hosts nothing loads from any more.

**What changed in 1.0.2** — two regressions that 1.0.1 introduced

- ⚠️ **The stray menu below the footer is gone.** Flatsome prints its own off-canvas mobile drawer
  (`<div id="main-menu" class="mobile-sidebar mfp-hide">`, carrying the old WordPress menu) into
  `wp_footer()` on every page. It was invisible only because `.mfp-hide { display:none }` lives in
  flatsome.css — the stylesheet 1.0.1 finally succeeded in dequeueing. Removing the CSS that hid it left
  the markup on show. The plugin now removes the theme's drawer callback outright, and also carries the
  `.mfp-hide` rule itself as a guarantee.
- ⚠️ **The burger is back at the right edge.** 1.0.1's defensive layer set `margin: 0` on `.burger` to
  kill the theme's `button { margin-bottom: 1em }`, and wiped the `margin-left: auto` that pushes the
  button right — so it sat next to the logo. Now `margin: 0 0 0 auto`.
- **Two regression guards added** to `verify-smart-living-site.py`: nothing may render after `</footer>`,
  and the stylesheet's effective `margin-left` on `.burger` must stay `auto`.

**What changed in 1.0.1**

Upload the new zip the same way (WordPress will ask to replace the existing plugin — say yes), then
**clear the WP Rocket cache**. No settings are lost and no pages are touched.

**What changed in 1.0.1**

- ⚠️ **The mobile menu is fixed.** Flatsome also defines a `.nav` class, and its stylesheet is enqueued
  *after* this plugin's. Same specificity, later in source order, so Flatsome won — at phone width its
  `.nav { display:flex; position:relative; width:100% }` beat our `display:none`, so the full desktop menu
  rendered inline *and* the burger showed. The menu class is now `.sl-nav`, which nothing else uses.
- ⚠️ **The "What is this about" dropdown is fixed.** Same root cause: Flatsome sets
  `select, input { height: 2.507em }` — a fixed 40px — while our fields need 53px for their padding and
  line-height, so the option text was clipped. The theme dequeue now runs late enough to actually fire,
  and the field styling no longer depends on it.
- **The dequeue now works at all.** It was hooked at priority 100, but Flatsome enqueues later than that,
  so it silently did nothing. It now runs at 9999 and again on `wp_print_styles`, and also drops
  `flatsome-googlefonts` (Lato and Dancing Script were still being pulled from fonts.googleapis.com on a
  site that self-hosts its type) and the three Font Awesome handles, two of which are external requests
  to use.fontawesome.com.
- **A defensive CSS layer** scopes the critical rules under the `.sl-site` body class, a full class above
  anything a theme can assert. If the dequeue ever fails again, or you switch themes, the layout holds.
- **The customers figure now comes from Settings everywhere.** It was hardcoded in three places — the home
  page counter, the AUN chip on the home page, and the About page meta description.
- **Breo is listed by category, not by model.** Neck & shoulder, eye massagers, massage guns, back & waist,
  each linking to the matching category page on breo.bd — so a discontinued model never dates this page.
- **Nav links no longer show the browser's default underline** (they were `<button>`s in the prototype and
  became `<a>`s here).
- **A branded 404 page**, returning a proper 404 status, noindexed.
- **Flatsome's demo content is kept out of the sitemap and noindexed** — see section 7.

## 3. Turn off Slider Revolution

The old home page was a Slider Revolution slide, and **that slider is the reason the old hero never
rendered** — it sat on its loading spinner forever in a clean browser, so first-time visitors saw a logo,
a menu and white space. Nothing in the new site uses it.

1. Confirm the new home page looks right first.
2. **Plugins** → deactivate **Slider Revolution** and **Slider Revolution Typewriter Addon**.
3. Leave them installed for a week in case you want to roll back, then delete.

The plugin already dequeues their CSS and JS on our six pages, so you get the speed benefit immediately
even before you deactivate them.

## 4. WP Rocket

After any change: **WP Rocket → Clear cache**.

Settings worth checking:
- **File optimisation → Minify/Combine CSS & JS** — safe. The structured data is already tagged
  `data-no-optimize` / `data-no-minify` so it will not be mangled.
- **Media → LazyLoad images** — safe. The three hero images carry `data-no-lazy="1"` so they load
  immediately; everything below the fold lazy-loads normally.
- **Delay JavaScript execution** — if this is on, add `sl.js` to the exclusion list. Otherwise the
  circuit animation and the condensing header wait for the first user interaction. Not broken, just late.
- **Preload → Fonts** — not needed. The plugin already emits `<link rel="preload">` for both woff2 files.

## 5. Check it worked

- [ ] Home page shows the animated hero, not a spinner
- [ ] All six pages load and the nav highlights the current one
- [ ] Phone width: burger menu opens, nothing scrolls sideways
- [ ] Paste the home URL into <https://developers.facebook.com/tools/debug/> — you should get the teal
      share card with "Three brands. One standard." (the site currently produces a blank card)
- [ ] Paste each URL into <https://search.google.com/test/rich-results> — expect **Organization**,
      **LocalBusiness**, and on Corporate Solutions and Dealer & Partnership also **FAQPage**
- [ ] `https://www.smartliving.com.bd/wp-sitemap.xml` lists the six pages

## 6. Flatsome's demo content (found in the live audit)

Your sitemap currently lists Flatsome's demo posts as pages of your company:

```
/featured_item/awesome-pencil-poster/      /blocks/shop-category-slider/
/featured_item/flat-t-shirt-company/       /blocks/footer-newsletter-signup/
/featured_item/lookbook-summer/            /blocks/demos/            ...and more
```

These are the theme's dummy content, imported at some point and never removed. They are public,
crawlable, and dilute what the site is about.

v1.0.1 removes them from `wp-sitemap.xml` and marks them `noindex, nofollow`, which stops the bleeding.
**You should still delete them properly**: *Pages → UX Blocks* and the *Featured Items* menu in wp-admin.
Check nothing you actually use is in there first — UX Blocks can hold real content on some sites, though
on yours they are all Flatsome demos.

## 7. One plugin worth deactivating

**Simple Facebook Twitter Widget.** Measured on the live site: it loads three resources on every page,
two of them from `connect.facebook.net` (Facebook's full JS SDK, with an appId), plus a hidden `#fb-root`
element. The new site never displays that widget — the Facebook link in the footer is an inline SVG that
needs no SDK.

Deactivating it removes two third-party requests per page and stops Facebook being able to set cookies on
visitors who never interacted with it. Check first that no other page you care about uses its shortcode.

For reference, the other third-party hosts the site currently contacts are Google Tag Manager, Google
Analytics and Cloudflare Insights — all deliberate.

## 8. Then do these two things

1. **Create a Google Business Profile** for Smart Living Bangladesh at the Mirpur address. Breo Bangladesh
   already has one; the parent company does not. For a local B2B company this is the highest-return
   action on the whole list.
2. **Add the site to Google Search Console** and submit the sitemap. None of the SEO work is measurable
   until this exists.

---

## What's in the plugin

```
smart-living-site/
  smart-living-site.php        bootstrap, asset loading, template swap
  includes/
    helpers.php                page registry, settings, page adoption
    content.php                ← every word on the site lives here
    layout.php                 header, footer, shared blocks
    render-home.php            the home page
    render-pages.php           the five inner pages
    seo.php                    titles, meta, Open Graph, JSON-LD
    admin.php                  Settings → Smart Living Site
  templates/canvas.php         the full-page template
  assets/
    sl.css  sl.js
    fonts/                     Fraunces + Instrument Sans, self-hosted (106 KB)
    img/                       brand imagery as WebP + the share card (285 KB)
    breo-wordmark.svg
```

**To change wording**, edit `includes/content.php`. Nothing lives in page content, so there is no builder
to fight and no stale markup — just clear the WP Rocket cache.

**Contact details, opening hours and the brand URLs** are in Settings, not in code — they feed the header,
footer, contact blocks and the schema at once.

## Deliberately not included: trade licence and BIN

Per your decision on 21 September 2026, neither number appears anywhere on the site.

For the record, the reasoning: a trade licence number has no public verification path in Bangladesh, so a
visitor cannot check it and it proves nothing; it renews annually, so it goes stale on the page; and the
people who actually need it — tender and procurement teams — ask for the scanned certificate, which you
send privately with the quotation.

What does the trust work instead: authorized distributor status for AUN and Breo, trading since 2019, a
real address with a map link, a phone number, and your own service bench. All on the About and Our Brands
pages already.

If you later decide to publish a BIN, it belongs in the "On the record" table on the About page —
`includes/render-pages.php`, in `sl_render_about()`.

## Rolling back

Deactivate the plugin. Every page reverts to Flatsome rendering exactly as before — the plugin never
changed page content, only which template draws it. Re-activate Slider Revolution if you had disabled it.

## Should I switch to a default WordPress theme?

You can — it is tested and it works. **But do it in this order, and not today.**

Tested on the bench: with the plugin active under **Twenty Twenty-Five**, all six pages plus the 404
render correctly, every verification check passes, and the page is visually identical to Flatsome. The
plugin does not depend on the theme for anything.

**Why not today:** switching gains you nothing your visitors can see. As of 1.0.2 Flatsome's CSS and JS
are dequeued on all seven visitor-facing views and its off-canvas drawer is removed, so it is already
inert. Meanwhile switching costs you the theme-level settings (header builder, site logo, typography) and
changes how search-results pages and any future page outside these six look.

**The order that matters — delete the demo content first.** Flatsome registers the `blocks` and
`featured_item` post types. The moment you switch theme those post types stop being registered, which
means:

- the wp-admin screens for *UX Blocks* and *Featured Items* disappear, so you lose the interface for
  deleting the demo posts;
- the posts stay in the database, published, just unreachable and unmanageable.

So: **(1)** delete the demo content while Flatsome is still active (section 6), **(2)** then switch theme
if you still want to, **(3)** then re-check the six pages, the 404, and a search results page.

If the only reason to switch is the Flatsome licence, it is a fine reason — just follow that order.

## Audit results (2026-09-22, live site)

Measured, not estimated. Everything below was checked on the live pages.

**Healthy**
- Contrast: **0 failures across 24 text roles**; worst is 5.42:1 against a 4.5:1 requirement
- **CLS 0** — every image carries width and height
- Load 772 ms, DOM interactive 555 ms, 16–20 requests, 199–321 KB per page
- TTFB 0.21–0.51 s
- Slider Revolution, Flatsome's CSS and JS, and its off-canvas drawer: all gone from every page
- Schema correct on all six pages; 404 returns a real 404 and is noindexed
- Every `<img>` has alt text and explicit dimensions; no horizontal overflow at 1440 px or 390 px
- Only six pages in the sitemap — the theme's demo content is excluded

**Fixed in 1.0.3** — see the changelog above: footer duplication, 266–288 px boundary gaps, brand text
colour, over-long titles and descriptions, `h2 → h4` heading skips, dead DNS hints.

**Left alone deliberately**
- **jQuery** loads on all six pages. Contact Form 7 needs it; other plugins may too. Breaking analytics
  or the contact form to save ~30 KB is a poor trade.
- **Cloudflare email obfuscation** rewrites the two `mailto:` links, so the address needs JavaScript to
  display. That is a Cloudflare setting, and on balance a good one — it is anti-scraping. Worth knowing
  it is why the email does not appear in the raw HTML.
- **The phone number appears twice** on the home page: once as a tap-to-call button in the closing call
  to action, once in the footer. That is an action and a reference, not a duplicated block. Say the word
  and I will drop it from the button.

## Tested

- PHP 8.2 lint: all 9 files pass
- Rendered on the local WordPress bench: all six pages HTTP 200, no PHP notices or warnings
- One `<h1>` per page; schema validates as JSON on every page, with three `subOrganization` entries
- No requests to Google Fonts; no Slider Revolution assets on any page
- No horizontal overflow at 1440 px or at 390 px
