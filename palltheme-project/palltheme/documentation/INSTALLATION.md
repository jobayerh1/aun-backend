# Palltheme — Installation Guide

© 2026 Engr. Nazim U Ahmed. All rights reserved.

## Requirements

| Item | Minimum | Recommended |
|---|---|---|
| WordPress | 6.5 | Latest stable |
| PHP | 8.2 | 8.3 |
| MySQL / MariaDB | 8.0 / 10.6 | latest |
| PHP memory | 256 MB | 512 MB |
| Max execution time | 300 s | 900 s (for the demo import) |

## 1. Upload the theme and the companion plugin

The package contains two folders:

- `palltheme/` — the theme (design, templates, WooCommerce/Elementor styling)
- `palltheme-core/` — the companion plugin (Services, Solutions, Case Studies, Team, Testimonials, Clients, Technologies, Pricing, FAQs, Jobs, Business Settings, Elementor widgets, demo importer)

Zip each folder separately, then:

1. **Appearance → Themes → Add New → Upload Theme** → `palltheme.zip` → Install → **Activate**.
2. **Plugins → Add New → Upload Plugin** → `palltheme-core.zip` → Install → **Activate**.

> Keep the plugin active even if you switch themes later — your services, case studies, team and settings live in the plugin, not the theme.

## 2. Install the required free plugins

A notice in the dashboard lists anything missing, with one-click search links.

- **Elementor** (free) — page builder
- **WooCommerce** — store

See `PLUGIN-REQUIREMENTS.md` for the recommended (optional) plugins.

## 3. Import the demo (optional, recommended)

1. Go to **Theme Settings → Import Demo**.
2. Click **Import demo website**. It creates about 150 images in every WordPress size, so it takes **1–5 minutes** on typical hosting (longer on very slow servers). If your host stops the request early, click the button again — the importer skips everything that already exists and continues where it stopped.
3. Visit your site.

What you get: the TechNova Systems demo (a **fictional** company) — 13 pages built with Elementor, 12 services, 10 solutions, 6 case studies, 18 blog articles, 42 WooCommerce products in 21 categories, 8 team members, 8 testimonials, 10 client logos, 22 FAQs, 5 job openings, mega menus, 7 footer menus and all settings. People, clients, testimonials and figures are fictional and labelled as demo content; a WooCommerce store notice says the demo store does not fulfil orders.

The importer never deletes existing content, and every demo item can be removed later with **Remove demo content**. Developers can also run `wp palltheme demo import` (or `remove`) — recommended on slow hosts.

### Optional photo pack

The theme and plugin only ship **original** artwork (illustrations, product renders, logos, avatars, Lottie). To give the demo real photography, the importer also reads an optional *photo pack* folder:

```
wp-content/palltheme-photo-pack/
    manifest.json      ← list of files with alt text, purpose and licence credits
    *.webp, *.webm, *.mp4
```

Each `manifest.json` entry: `key` (slot such as `hero`, `svc-cybersecurity`, `blog-…`, `hero-video-webm`), `file`, `alt`, `usage`, `source`, `original_url`, `author`, `author_url`, `license`, `license_url`. Photos are imported with their credits stored on the attachment and listed on the **Media Credits** page. The demo website was built with Unsplash photos and one Pexels video (see `MEDIA-CREDITS.md`); those licences do not allow redistribution inside a theme, so they are not part of the zips. Without a photo pack every slot falls back to the original illustrations. Developers can point the importer elsewhere with the `pallcore_demo_photo_pack_dir` filter.

## 4. Make it yours

1. **Theme Settings → Business Settings** — company name, phone, email, address, hours, WhatsApp/Telegram, social links, homepage hero text, statistics, call-to-action, chat buttons.
2. **Appearance → Customize → Palltheme Settings** — colors, fonts, logos, header, footer, dark mode, shop, performance.
3. **Appearance → Customize → Site Identity** — logo and site icon (favicon).
4. **Appearance → Menus** — edit the header mega menu and footer columns.
5. Replace demo content: Services, Solutions, Products, etc. each have their own menu in the admin sidebar.
6. Remove the demo: **Theme Settings → Import Demo → Remove demo content**.

## 5. Go-live checklist

- [ ] Settings → Permalinks: "Post name"
- [ ] Real logo, favicon, contact details and social links
- [ ] Legal pages reviewed by your legal advisor (the demo texts are templates)
- [ ] Demo store notice turned off (Appearance → Customize → WooCommerce → Store notice) and demo products replaced
- [ ] Media Credits page updated (or removed) for the media you actually use
- [ ] WooCommerce → Settings: currency, taxes, shipping zones, payment gateways
- [ ] SMTP plugin configured (so order and contact emails arrive)
- [ ] SEO plugin installed and configured
- [ ] Caching + image optimisation plugin installed
- [ ] Backup plugin scheduled
