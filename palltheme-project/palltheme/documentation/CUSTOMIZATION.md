# Customization Guide

No code is needed for anything in this guide.

## Where things live

| You want to change… | Go to |
|---|---|
| Phone, email, address, hours, map, WhatsApp, Telegram | Theme Settings → Business Settings → Company & Contact |
| Social media links | Business Settings → Social Media |
| Homepage hero heading, buttons, image, video, Lottie, floating cards | Business Settings → Homepage Hero (or edit the Hero widget in Elementor) |
| Statistics ("500+ Projects Delivered") | Business Settings → Statistics & CTA |
| Call-to-action band | Business Settings → Statistics & CTA |
| Floating WhatsApp/Telegram/Messenger/Call buttons | Business Settings → Chat Buttons |
| Contact form | Business Settings → Contact Form |
| Maintenance page + countdown | Business Settings → Maintenance Mode |
| Colors (primary, secondary, accent…) | Appearance → Customize → Palltheme Settings → Global Colors |
| Fonts (Inter, Manrope, Plus Jakarta Sans, Space Grotesk) | Customize → Typography |
| Corner radius, button shape | Customize → Corners & Buttons |
| Logos (main, light, dark-mode, sticky) | Customize → Branding & Logos (+ Site Identity for the main logo) |
| Header style, sticky, top bar, icons, "Get a Quote" button | Customize → Header (a transparent header automatically turns solid on pages that don't start with a dark hero, so menu text is always readable) |
| Footer layout (enterprise *mega* footer, 5 or 4 columns), description, payment badges, copyright | Customize → Footer |
| Newsletter sign-up (footer) | Business Settings → Newsletter — built-in form or any newsletter plugin shortcode; sign-ups in **Theme Settings → Subscribers** (Export CSV) |
| "Why choose us" / trust items | Business Settings → Statistics & CTA → Trust / why-us items (one per line: `icon \| Title \| Text`) |
| Photo credits | Media Library → attachment details → *Credit* fields (shown on the Media Credits page) |
| Dark mode default + visitor switch | Customize → Dark Mode |
| Shop columns, products per page, load-more, filters sidebar, shipping/warranty text | Customize → Shop |
| 404 text | Customize → 404 Page |
| Animations, background videos, lazy-loading, per-block styles, emoji script | Customize → Performance |

## Colors & Elementor

Theme colors are pushed into **Elementor → Site Settings → Global Colors** every time you save the Customizer (Primary, Secondary, Accent + Dark/Light/White/Success/Warning/Danger). Pick these globals in any Elementor widget so a single change updates the whole site.

## Copyright

Use `{year}` and `{site}` in the copyright text: `© {year} {site}. All Rights Reserved.` — both update automatically.

## Menus & mega menus

Appearance → Menus. To turn a top-level item into a **mega menu**:

1. Open **Screen Options** (top right) and tick **CSS Classes** and **Description**.
2. Add the class `mega` to the top-level item.
3. Its children become column headings; grandchildren become the links. Descriptions show under each link.

The enterprise footer has seven link columns, each a menu location: *Footer: Company, Services, Solutions, Products, Support, Resources, Legal* (Appearance → Menus → Manage Locations). The menu name is the column heading (a leading "Footer:" is dropped, so "Footer: Company" shows as "Company"). A widget placed in *Footer Column 1–7* replaces that column's menu. Brand, social icons, newsletter and contact details come from Business Settings, so they stay in sync everywhere.

## Content types

Each type has a menu in the admin sidebar with friendly field boxes:

- **Services** — icon (choose or upload SVG), features, benefits, technologies, gallery, FAQ, related services/products, custom CTA.
- **Solutions** — the problem, our approach, architecture and implementation steps, capabilities, technologies, benefits, related services, products and case studies.
- **Blog posts** — a *Related content* box links each article to services and products (shown under the article).
- **Case Studies** — client, location, duration, challenge, solution, implementation, results (animated numbers), technologies, images, video, testimonial.
- **Team** — position, skills with levels, email, phone, social links.
- **Testimonials** — name (title), quote (content), photo (featured image), position, company, rating, company logo.
- **Clients** — logo (featured image), website, industry.
- **Technologies** — logo or short text mark, caption, group (for the filter tabs). Only upload third-party logos when their brand guidelines allow it.
- **Pricing Plans**, **FAQs**, **Careers (Job Openings)**.

Order items with the **Order** field (Page Attributes box) — lower numbers show first.

## Products: specifications, downloads, video

In the product editor, the **Technical details (Palltheme)** box adds:

- **Specifications** rows → a "Specifications" tab with a responsive table. Start a row with `#` (e.g. `#Networking`) to create a group heading.
- **Downloads** → a "Downloads" tab (upload PDFs in Media, paste the link).
- **Video URL** → a "Video" tab (YouTube, Vimeo or MP4; loads lazily).
- **Warranty / Shipping** → override the default texts shown under Add to Cart.

**Filters:** create global attributes in **Products → Attributes** (Processor, RAM, Storage, Interface, Port Speed, Form Factor, Capacity, Warranty…). Use them on products and they appear in the shop filter widget automatically. **Frequently bought together** uses each product's *Cross-sells*.

## Building pages with Elementor

Edit any page with Elementor; Palltheme widgets are in the **Palltheme** category: Hero, Services Grid, Solutions Grid, Case Studies, Team, Testimonials (slider/grid), Client Logos (infinite scroll/grid), Technology Stack, Statistics Counter, Products, Blog Posts, Pricing Table, FAQ, Job Openings, Process Steps, Call to Action, Contact Block, **Features / Trust** (icon cards, trust strip or minimal list), **Image + Text** (photo or Lottie beside text, bullets, two buttons, badge; switch on *First section on the page* when it opens a page so its photo loads first). The newsletter form and the media-credits table are shortcodes (`[pall_newsletter]`, `[pall_media_credits]`) — drop them into Elementor's *Shortcode* widget.

All widgets pull **live** content — add a new service in the admin and it appears everywhere automatically. Each widget has a *Section heading* and *Section layout* (background, spacing) panel. Turn off *Theme section spacing & width* to control spacing entirely in Elementor.

Page templates (Page → Template):

- **Default** — title band + content
- **Palltheme — Full Width** — header + footer, no title band (homepage)
- **Palltheme — Blank Canvas** — no header/footer (landing pages)

## Shortcodes

Every widget is also a shortcode (works in the block editor too):

`[pall_hero]` `[pall_services count="6" columns="3" category="infrastructure"]` `[pall_solutions]` `[pall_case_studies]` `[pall_team]` `[pall_testimonials layout="grid"]` `[pall_clients layout="grid"]` `[pall_technologies]` `[pall_stats style="cards"]` `[pall_products show="featured" count="8"]` `[pall_posts]` `[pall_pricing]` `[pall_faq category="store"]` `[pall_jobs]` `[pall_process]` `[pall_cta]` `[pall_contact]` `[pall_contact_form]` `[pall_home]` `[pall_features items="icon | Title | Text | optional link" columns="3" style="cards|trust|minimal"]` `[pall_split image="123" text="…" bullets="…" reverse="yes" lottie="https://…/file.json"]` `[pall_newsletter]` `[pall_media_credits]`

Common attributes: `eyebrow`, `title` (use `*word*` to highlight), `lead`, `align="center"`, `link_text`, `link_url`, `background="alt|dark"`, `spacing="tight|none"`, `ids="1,2,3"`.

## Translation & RTL

All strings use the `palltheme` / `palltheme-core` text domains — translate with Loco Translate or a `.po` file. The stylesheet uses logical CSS properties, so right-to-left languages work without extra files.

## Child theme

For code customizations create a child theme (`Template: palltheme`) so updates don't overwrite your changes.
