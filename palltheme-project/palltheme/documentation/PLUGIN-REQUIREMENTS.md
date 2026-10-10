# Plugin Requirements

The theme is intentionally lightweight and does **not** duplicate what established plugins already do. Everything below is free unless marked.

## Required

| Plugin | Why |
|---|---|
| **Palltheme Core** (bundled) | Custom post types, fields, Business Settings, Elementor widgets, shortcodes, live search, product specs/quick view/filters, chat buttons, maintenance mode, demo importer |
| **Elementor** | Visual editing of every page. Palltheme widgets appear under the "Palltheme" category |
| **WooCommerce** | Store, cart, checkout, accounts, orders, payments |

## Recommended (choose one per row)

| Need | Options | Notes |
|---|---|---|
| SEO | Rank Math SEO **or** Yoast SEO | When either is active, the theme outputs **no** competing meta/OG/schema — it hands over completely. Breadcrumbs automatically use the SEO plugin's breadcrumbs |
| Caching | LiteSpeed Cache (LiteSpeed servers) **or** WP Rocket (premium) | See PERFORMANCE.md |
| Minify (if no WP Rocket) | Autoptimize | |
| Security | Wordfence **or** Solid Security | |
| Forms | Fluent Forms **or** WPForms Lite | Paste the form shortcode in Business Settings → Contact Form. Without it, a lightweight built-in form is used |
| Email delivery | FluentSMTP **or** WP Mail SMTP | Strongly recommended — order and contact emails |
| Backup | UpdraftPlus | |
| Image optimisation | ShortPixel **or** Imagify | Enables WebP/AVIF delivery |
| Analytics | Site Kit by Google | |
| Wishlist | YITH WooCommerce Wishlist | The header heart icon + count and the card heart appear automatically when active |
| Compare | YITH WooCommerce Compare | Adds its own compare button on product pages and cards |
| Newsletter | Built in, or MailPoet, FluentCRM, Mailchimp for WP… | The footer has a built-in sign-up form (subscribers listed in **Theme Settings → Subscribers**, Export CSV). To use a newsletter plugin instead, paste its shortcode in **Business Settings → Newsletter** or put its widget in **Appearance → Widgets → Footer Newsletter** |
| Live chat | Tawk.to, Crisp… | They add their own bubble. Palltheme's WhatsApp/Telegram/Messenger buttons are configured in Business Settings → Chat Buttons |
| Payments | Stripe, PayPal, local gateways | Configure in WooCommerce → Settings → Payments. Nothing is hard-coded |
| Subscriptions | WooCommerce Subscriptions (premium) | Fully compatible; no theme changes needed |
| Brands | WooCommerce 9.6+ includes Brands | The filter widget shows a Brand filter automatically |
| Maintenance | Built in (Business Settings → Maintenance Mode) or any maintenance plugin | |

## Tested versions

Verified on WordPress 7.1.3 / PHP 8.2 with: Elementor 4.3.4 (editor: widgets, live preview, add/edit/save), WooCommerce 10.9.4 (block checkout, AJAX cart, variable products, Buy Now, quick view; HPOS compatibility declared), YITH WooCommerce Wishlist 4.19, YITH WooCommerce Compare 3.15 and Rank Math SEO.

Notes:
- **Rank Math / Yoast:** the theme outputs no meta/Open Graph/Organization schema while they are active. Breadcrumbs use the SEO plugin's breadcrumbs only when you switch them on there; otherwise the theme's own breadcrumbs (with BreadcrumbList schema) are shown. FAQ schema for Palltheme FAQs is always output (SEO plugins only handle their own FAQ blocks) — disable with `add_filter( 'pallcore_faq_schema', '__return_false' );`.
- **YITH Wishlist:** the demo importer creates the Wishlist page if YITH hasn't, and switches YITH to its classic (PHP-template) buttons, which are far lighter than its React buttons (see PERFORMANCE.md). The header heart shows a live count in either mode. Guests can use the wishlist without an account.
- **YITH Compare:** on first demo import the Compare button is enabled on product pages as well as the shop (YITH's default is shop only).
- **WooCommerce "Coming soon" mode:** new stores start hidden from visitors; the demo importer switches the store live and says so in its log.

## Optional: Elementor Pro

Not required. If you own Elementor Pro, its Theme Builder headers/footers/templates override the theme's automatically (the theme registers all Theme Builder locations).
