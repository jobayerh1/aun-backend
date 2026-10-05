# Smart Living Bangladesh — SEO plan for smartliving.com.bd

Researched 2026-09-21. Method: DuckDuckGo (`kl=bd-en`) + Bing (`market=en-BD`) autocomplete, plus web
search for the competitive landscape. Google's own autocomplete endpoint times out from this machine,
so BD-localised autocomplete came from DDG/Bing — directionally reliable, not volume-accurate.

**No SEO plugin is needed.** See §5.

---

## 1. The one rule that shapes everything: do not compete with your own brands

`aun-projector.com.bd` already ranks for the money terms — a search for office/classroom projectors in
Bangladesh surfaces its own "Office Projector Price In BD | Business & Classroom Projectors" page.

If smartliving.com.bd chases the same keywords, the two sites split their own signals and the weaker
page (the parent) loses. The parent site must own a **different territory**: the B2B, distributor,
dealership and legitimacy-verification queries that the retail brand sites deliberately do not target.

| Territory | Owner |
|---|---|
| "projector price in bd", "mini projector price", "android projector price in bangladesh" | **aun-projector.com.bd** — leave alone |
| "massage gun price in bd", "massager price in bangladesh", "eye massager price" | **breo.bd** — leave alone |
| bag / handbag retail terms | **kohthaibd.com** — leave alone |
| "<brand> distributor in bangladesh", corporate supply, dealership, company legitimacy | **smartliving.com.bd** — this plan |

---

## 2. What the research actually showed

### 2.1 The `"<brand> distributor in bangladesh"` pattern is real and established — this is the big win

Typing "distributor in bangladesh" autocompletes to a long list of brand-prefixed variants:

> dahua · starlink · schneider · microsoft · dell · asus · abb · samsung · lenovo
> — each as "<brand> distributor in bangladesh"

And "authorized distributor in bangladesh" autocompletes to "hp authorized distributor in bangladesh",
"hp authorized dealers in bangladesh", "dahua distributor in bangladesh", "samsung distributor in bangladesh".

So Bangladeshi buyers **do** search this shape, and nobody currently owns:

- `AUN authorized distributor in Bangladesh` / `AUN projector distributor Bangladesh`
- `Breo distributor in Bangladesh` / `Breo authorized distributor Bangladesh`
- `Breo massager official Bangladesh`

Low volume, near-zero competition, and very high intent — it is searched by prospective dealers,
corporate buyers, and customers checking you are legitimate before spending real money. **Target page:
Our Brands**, with the phrasing carried in H2/H3 and in `subOrganization` schema.

⚠️ Use the word **"authorized"**, not only "official" — "authorized distributor" is the phrase that
actually autocompletes. The prototype copy was changed accordingly.

### 2.2 Corporate / institutional supply — thin but uncontested locally

BD-specific autocomplete exists for:
- `corporate gift items in bangladesh`, `corporate gift box in bangladesh`
- `projector for school` → "for school classrooms / school hall / school price"
- `projector for office` → "office meeting room / office presentations / conference room"

Most "supplier / dealer / importer in bangladesh" seeds returned **Indian** results (Bangalore, Delhi,
Chandigarh). That is the signal: these BD B2B terms have little established volume, which means little
competition. Worth owning cheaply, not worth building the whole site around.

Competitors who *do* target corporate/bulk projector supply: UCC BD (ViewSonic), Eastern IT, Canvas IT,
CSI (BenQ), Ryans, projector.com.bd. None of them is the AUN or Breo distributor — that is the wedge.

**Target page: Corporate Solutions.**

### 2.3 Dealership intent exists

`dealership business in bangladesh` autocompletes; so does `how to get trade license in bangladesh`
alongside dealer queries — people researching starting a dealership. **Target page: Dealer & Partnership.**

### 2.4 Two brand problems found — both worth acting on

**(a) "Kohthai" collides with an established UK restaurant group.** Searching the bare name returns
Koh Thai Port Solent, Koh Thai Plymouth, Koh Thai Lilliput, Koh Thai Bournemouth, Koh Thai menu — not bags.
The brand name is effectively unsearchable on its own.
→ **Fix:** never publish the bare word. Always "Kohthai Bags" or "Kohthai Bangladesh" in titles, link
text, headings and schema. Applied in the prototype (portfolio strip and both division headings).
This matters more on kohthaibd.com than here — worth a separate pass on that site.

**(b) `breo scalp massager` autocompletes strongly** — and scalp massagers are Breo's core global line,
still not stocked in Bangladesh. Confirms the earlier finding from the breo.bd work: there is demand
sitting unserved. Raise with the supplier.

**(c)** `smart living bangladesh`, `breo bangladesh`, `classroom projector bangladesh` and
`projector warranty bangladesh` return **no autocomplete at all** — no established search volume.
The company is currently invisible as a searchable entity. Owning the brand name is table stakes, not a win.

---

## 3. Page-by-page map

| Page | Primary target | Secondary |
|---|---|---|
| **Home** | smart living bangladesh | importer and distributor in bangladesh; AUN + Kohthai + Breo as one entity |
| **About** | smart living bangladesh company / about | import and distribution company dhaka; since 2019 |
| **Our Brands** | **AUN authorized distributor in Bangladesh**; **Breo distributor in Bangladesh** | kohthai bags bangladesh; breo massager official bangladesh |
| **Corporate Solutions** | **projector supplier in bangladesh** | projector for school / classroom / office meeting room bangladesh; corporate gift items in bangladesh; bulk projector supply |
| **Dealer & Partnership** | **projector dealership in bangladesh** | become a distributor bangladesh; breo dealership; dealership business in bangladesh |
| **Contact** | smart living bangladesh contact / address | projector supplier mirpur dhaka |

### Title tags and meta descriptions (ready to use)

**Home**
`Smart Living Bangladesh | Authorized Distributor of AUN, Kohthai & Breo`
> Dhaka-based import and distribution company since 2019. Authorized distributor of AUN projectors and
> Breo massagers, and owner of Kohthai Bags. Corporate supply, dealer programmes and local after-sales service.

**About** — `About Smart Living Bangladesh | Import & Distribution Company in Dhaka`
> Trading since 1 April 2019 from Mirpur, Dhaka. Three divisions, our own service bench, and 5,600+
> customers served through AUN Projector Bangladesh.

**Our Brands** — `Our Brands | AUN Projector, Kohthai Bags & Breo Bangladesh`
> Smart Living Bangladesh is the authorized AUN projector distributor and the authorized Breo distributor
> in Bangladesh, and owns the Kohthai Bags label. Official warranty and local service on all three.

**Corporate Solutions** — `Corporate Projector Supplier in Bangladesh | Smart Living Bangladesh`
> Bulk projector supply for schools, offices, training rooms and events across Bangladesh, plus corporate
> gifting from Kohthai and Breo. Corporate invoicing, warranty documentation and in-country service.

**Dealer & Partnership** — `Projector & Massager Dealership in Bangladesh | Become a Distributor`
> Dealer and distribution programmes for AUN projectors, Breo massagers and Kohthai Bags. Dealer pricing,
> stock held in Dhaka, and warranty handled by us rather than by your counter.

**Contact** — `Contact Smart Living Bangladesh | Mirpur, Dhaka`
> 108 Golartek, Mazar Road, Mirpur, Dhaka 1216. Phone +880 9638-078888. Saturday–Thursday, 10:00–18:00.

Keep titles under ~60 characters where possible and descriptions 140–160.

---

## 4. Schema (JSON-LD) — the piece that does the heavy lifting here

This is where a parent-company site has an advantage a normal site does not, and it maps directly onto
the "<brand> distributor in Bangladesh" queries.

- **`Organization`** for Smart Living Bangladesh — `foundingDate: 2019-04-01`, address, phone, email,
  `sameAs` → Facebook + the three brand sites.
- **`subOrganization` × 3** → AUN Projector Bangladesh, Kohthai Bags, Breo Bangladesh, each with its own
  `url`. This is the explicit machine-readable statement that these three brands belong to one company.
- **`LocalBusiness`** with `openingHoursSpecification` (Sat–Thu 10:00–18:00), `hasMap` → the Google
  Business Profile link, and `geo`.
- **`BreadcrumbList`** on the five inner pages.
- **`FAQPage`** on Corporate Solutions and Dealer & Partnership — the two FAQ blocks added to the
  prototype exist partly for this; they are eligible for FAQ rich results and they answer the long-tail
  queries in §2.2 and §2.3 directly.

---

## 5. Do we need RankMath? No.

**Recommendation: build SEO into the `smart-living-site` plugin, no SEO plugin.**

| | RankMath | In-plugin |
|---|---|---|
| Value at 6 fixed pages | low — RankMath earns its weight on hundreds of products/posts | full control, exact output |
| Weight | another plugin, DB tables, cron, upsell notices | a few hundred lines already inside a plugin we are shipping |
| Schema | generic templates you then fight | hand-written `subOrganization` graph, which is the whole point here |
| Editing later | its own UI | an SEO tab on the plugin's settings screen — same convenience |

Notes:
- **Sitemap is already handled.** WordPress core has emitted `/wp-sitemap.xml` since 5.5; the site runs
  WP 7.1. Nothing to install. The plugin just needs to make sure the six pages are in it.
- The site currently has **no meta description and no Open Graph tags at all** — sharing any page on
  Facebook produces nothing. That alone is worth fixing before anything else.
- **If a blog is ever added to smartliving.com.bd, revisit this.** RankMath becomes worth it at that point.
  (RankMath PRO is already licensed and in use on breo.bd, so it is available if needed.)

### Technical steps the plugin will do
1. `<title>`, meta description, canonical, `og:*` and `twitter:*` per page (table in §3), all overridable
   from a settings tab.
2. The JSON-LD graph in §4.
3. `robots` noindex on any utility pages; confirm the six real pages are in `wp-sitemap.xml`.
4. One `<h1>` per page, correct heading order.
5. Self-hosted woff2 fonts and WP Rocket guards on inline CSS/JS — removes the current Google Fonts
   requests and keeps Core Web Vitals clean.
6. **Remove Slider Revolution.** It is the cause of the dead homepage hero and it is pure weight.

---

## 6. Off-site, in priority order

1. **Google Business Profile** for Smart Living Bangladesh at the Mirpur address — the single highest-return
   action for a local B2B company. Breo Bangladesh already has one (`maps.app.goo.gl/yvfHHNzmPuXjw7Yj9`);
   the parent company needs its own.
2. **Cross-link the three brand sites back to the parent** with anchor text like "part of Smart Living
   Bangladesh" and the parent linking out. Cheap, and it makes the group structure legible to Google.
3. Get the entity right: consistent **name, address, phone** everywhere (note the live About page says
   "Mirpur" while the footer says only "Mazar Road" — make them identical).
4. Submit to Google Search Console and check indexing — nothing here is measurable until that exists.

## 7. What to measure after launch

Not rankings. Track: enquiries through the contact form split by the "What is this about" dropdown
(that field exists in the prototype specifically so corporate and dealer enquiries are countable),
plus Search Console impressions for the "<brand> distributor" and "supplier/dealership" query families.

---

## Open items
- Kohthai launch year **confirmed 2025** (owner, 2026-09-21).
- Trade licence: **do not publish**. BIN: publish only if VAT-registered and chasing tender/corporate work.
  See the chat discussion — BIN already appears on every Mushak 6.3 challan you issue and is verifiable on
  the NBR portal, so it is low-risk; the trade licence number has no public verification path and no
  conversion value, and it goes stale annually.
