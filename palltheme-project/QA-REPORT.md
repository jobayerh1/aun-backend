# QA Report — TechNova Systems demo on Palltheme

Palltheme / Palltheme Core — © 2026 Engr. Nazim U Ahmed.
Test date: 7–8 October 2026.

## Test environment

| Item | Version |
|---|---|
| WordPress | 7.1.3 (SQLite database, PHP 8.2 built-in server behind a local proxy) |
| Elementor (free) | 4.3.4 |
| WooCommerce | 10.9.4 (block cart and checkout, HPOS) |
| YITH WooCommerce Wishlist / Compare | 4.19 / 3.15 |
| Rank Math SEO | active |
| Browser | Google Chrome (Playwright) |

The test server is deliberately slow (3–6 s to generate a page, single SQLite file), so absolute timings below are not representative of real hosting. Relative improvements are.

All QA scripts are in `tools/qa/` and can be re-run: `crawl.py`, `visual.py`, `functional.py`, `perf.py`, `editor.py`, `sections.py`.

## Results (final run on a fresh import)

| Check | Result |
|---|---|
| PHP syntax (77 files, PHP 8.2) | ✔ all clean |
| Demo import from scratch (`wp palltheme demo import`) | ✔ no errors, ~9 min on this server (images are resized into all WordPress sizes) |
| Demo removal (`wp palltheme demo remove`) | ✔ removes exactly the 376 imported items and 142 terms/menus; user content untouched |
| Link crawl | ✔ 213 pages + 987 images/CSS/JS/PDF files: 0 broken, 0 PHP errors in output, 0 unresolved internal-link placeholders, 0 images without `alt` |
| Responsive layout — 30 page types × 8 widths (1920, 1440, 1024, 768, 480, 390, 375, 320) | ✔ 0 horizontal overflow, 0 broken images |
| Dark mode (system preference, plus light/dark/system toggle that persists) | ✔ applied on every page type |
| Console errors | ✔ none from the theme/plugin (the only entries: the 404 page's own 404 status, and one wishlist request aborted when the test closed the page) |
| Functional suite | ✔ 13/13: live search (types, categories, prices), search REST API, scheme toggle, quick view, AJAX add to cart, variable product, wishlist (guest), compare, block checkout (Cash on delivery, test data), contact form, newsletter, mobile off-canvas menu + submenus, no uncaught JS errors |
| Elementor editor | ✔ Home, About, Support, Careers, Contact and Media Credits open in the editor; every Palltheme widget renders; 0 console errors |
| Mega menus (Services, Solutions, Products) | ✔ readable in light and dark mode over the transparent header |
| Hero video / poster / Lottie | ✔ WebM plays on desktop (poster on mobile), Lottie renders on the Support page |
| Translation templates | ✔ `palltheme.pot` (259 strings), `palltheme-core.pot` (594 strings), no translator-comment warnings |

### Core Web Vitals probe (mobile 390 px, 4× CPU throttling, this slow test server)

| Page | Transfer | JavaScript | CSS | CLS | Blocking time |
|---|---|---|---|---|---|
| Home | 2,781 → **1,419 KB** | 1,792 → **572 KB** | 536 → 379 KB | 0.002 | 5.8 → 3.8 s |
| Shop | 2,504 → **1,132 KB** | 1,636 → **394 KB** | 477 → 320 KB | 0.000 | 3.1 → 1.3 s |
| Product | 2,524 → **1,139 KB** | 1,677 → **435 KB** | 491 → 334 KB | 0.328 → **0.003** | 3.2 → 1.6 s |
| About | 870 → **722 KB** | 340 → 318 KB | 274 → **136 KB** | 0.017 → **0.000** | 1.0 → 0.9 s |
| Contact | 804 → **638 KB** | 340 → 318 KB | 274 → 136 KB | 0.000 | 0.9 → 0.8 s |

(Before → after this QA pass. Sizes are uncompressed: the test server does not gzip.) Run Google PageSpeed Insights on the real host with a page cache enabled for representative scores; most of the remaining blocking time on store pages comes from jQuery, WooCommerce, Elementor and YITH Compare.

## Defects found and fixed during QA

| # | Area | Problem | Fix |
|---|---|---|---|
| 1 | Header | The transparent header overlapped the top bar when the WordPress toolbar was visible, and when the top bar wrapped on small phones | Header offset now uses the toolbar height and the measured top-bar height |
| 2 | Header | On WooCommerce pages the logo rendered at its full file size and pushed "Get a Quote" off-screen (WooCommerce's `.woocommerce-page img { height: auto }`) | Logo rule made more specific |
| 3 | Header | Icons overflowed by 32 px at 480–599 px widths | Compact header sizing extended to 599 px |
| 4 | Mega menu | Links in dropdown panels were white on white over the transparent header | Dropdowns restore the normal text colours |
| 5 | Mega menu | Top-level dropdown arrows invisible over the hero in light mode | Arrows follow the header's white text |
| 6 | Footer | Column headings showed menu names ("Footer: Company") | The "Footer:" prefix is dropped automatically |
| 7 | Media Credits | The credits table was missing from the Elementor layout (shortcode-only sections were dropped) | Importer places shortcode-only sections in Elementor's Shortcode widget |
| 8 | Store | Size options sorted alphabetically (10m before 1m, 128GB before 32GB) | Importer stores variation terms in natural order |
| 9 | Store | Fresh stores could not complete the demo checkout (no payment or shipping method) | Importer enables Cash on delivery + free shipping **only if none exist**, and says so |
| 10 | Store | Product page layout shift 0.328 on phones (gallery slider is several times taller for one frame while it starts) | Gallery height is held until it has faded in |
| 11 | Store | Breadcrumbs re-wrapped when the web font loaded, shifting the page | Breadcrumbs stay on one line; long titles end with "…" |
| 12 | Performance | Every page loaded WooCommerce + YITH assets (~25 files incl. React, lodash, moment) even without products | Store assets load only on pages that show products |
| 13 | Performance | YITH Wishlist's React buttons added ~1 MB of JavaScript to store pages | Importer switches YITH to its classic buttons (same features) |
| 14 | Performance | The 140 KB block-library stylesheet and the emoji script loaded on every page; Elementor loaded duplicate copies of the theme fonts | Per-block styles, emoji script removed (both Customizer options), duplicate fonts dropped |
| 15 | Performance | The first photo on About/Careers (the largest paint) was lazy-loaded | New "First section on the page" option loads it immediately |
| 16 | Accessibility | 62 demo illustrations, logos and avatars had no alt text | Importer writes alt text for every bundled image |
| 17 | Content | Two Support Center cards pointed to the FAQ; WordPress's "Sample Page" stayed published | Knowledge base → blog; Sample Page moved to drafts |
| 18 | Claims | Fallback hero cards (used when settings are empty) read "Uptime SLA" and "ISO 27001 Security aligned" | Replaced with "Uptime target", "Monitoring", "Years of experience" — no SLA or certification claims |
| 19 | Forms | A visitor hitting the contact-form rate limit saw the generic "check the fields" error | Separate message with the company's email and phone |
| 20 | Forms | Newsletter return URL broke on sub-folder installs | Uses the request path relative to home |
| 21 | Removal | Removing the demo left the logo/hero settings pointing at deleted images | Removal clears them |
| 22 | PHP | "Undefined global variable $post" warning when product sections render in the Elementor editor or REST | Guarded before calling WooCommerce's products shortcode |

## Known third-party notes (not theme defects)

- **Elementor 4.3.4 "Optimized image loading"** prepends `loading`/`fetchpriority` to images that already have them, producing duplicate attributes with identical values. Browsers use the first; loading behaviour is correct.
- **YITH WooCommerce Compare 3.15** logs PHP 8.2 deprecation notices ("Creation of dynamic property … `$fields`"). Harmless; fixed in YITH's own updates.
- **YITH Wishlist React mode** (if switched back on) requests `/wp-json/yith/wishlist/v1/lists` as a guest and receives 401 by design, and ignores clicks until it has finished loading.
- **WooCommerce stock reservation on SQLite:** WooCommerce's stock-hold query uses MySQL-only SQL, so on this SQLite test bench "Hold stock" was set to empty to allow checkout. On MySQL/MariaDB hosting it works as normal — leave the setting at its default there.

## Remaining manual configuration before going live

1. Replace the TechNova demo identity: logo, favicon, company name, contact details, social links (Theme Settings → Business Settings; Appearance → Customize).
2. Replace or remove demo content (Theme Settings → Import Demo → Remove demo content), or edit it in place. People, clients, testimonials, case studies and figures are fictional.
3. Turn off the demo store notice (Customize → WooCommerce → Store notice).
4. WooCommerce: real payment gateways (replace the demo Cash on delivery if not wanted), shipping zones and rates, taxes, currency, emails.
5. Legal pages: the Privacy, Terms, Cookie and Refund texts are templates — have them reviewed.
6. Photos: the demo photography is used under the Unsplash/Pexels licences for this website only and is not part of the theme zips. Keep the Media Credits page (or remove it if you replace all photos with your own).
7. Install SMTP, caching, backup and image-optimisation plugins (see PLUGIN-REQUIREMENTS.md), then run PageSpeed Insights on the live host.
