# DServer Technology — WordPress website

A standalone WordPress theme (`dserver`) for DServer Technology: VPS hosting, dedicated servers, cloud, colocation and managed services. Every page is built with **Elementor's free widgets**, so all text, images, prices and layouts are editable with *Edit with Elementor*.

## Install

1. Install WordPress (cPanel → Softaculous / WordPress Manager), PHP 8.0+.
2. **Plugins → Add New** → install and activate **Elementor** (free).
3. **Appearance → Themes → Add New → Upload Theme** → `dist/dserver.zip` → Activate.
4. **Appearance → DServer Setup → Create the website** (takes under a minute).

## What the setup creates

- Pages: Home, VPS Hosting, Dedicated Servers, Services, Solutions, About, Contact, Blog, Privacy Policy, Terms of Service, Media Credits
- 3 blog articles, header and footer menus, logo, Elementor global colours and fonts
- 24 licensed photos/video imported into the Media Library

Re-running the setup only adds what is missing — pages you have edited are never overwritten.

## Pricing (EUR per month, excl. VAT)

| VPS | vCPU | RAM | NVMe | Price |
|---|---|---|---|---|
| VPS S | 2 | 4 GB | 80 GB | €6.49 |
| VPS M | 4 | 8 GB | 160 GB | €11.99 |
| VPS L | 8 | 16 GB | 320 GB | €22.99 |
| VPS XL | 16 | 32 GB | 640 GB | €44.99 |

| Dedicated | CPU | RAM | Storage | Price |
|---|---|---|---|---|
| DS Ryzen 7 | 8 cores | 64 GB | 2 × 1 TB NVMe | €59 |
| DS Ryzen 9 | 16 cores | 128 GB | 2 × 2 TB NVMe | €109 |
| DS EPYC | 32 cores | 256 GB | 2 × 3.84 TB NVMe | €239 |

Set against European market prices (October 2026: OVHcloud VPS-1/2/3 €6.49/€9.99/€19.99; Hetzner's June 2026 price list; Contabo entry plans ~€4.40–5.50). Edit them on the VPS Hosting / Dedicated Servers pages in Elementor.

## Before going live — replace the placeholders

- **Appearance → Customize → DServer: company & header/footer**: phone (+44 20 7946 0958 is a fictional number), email (`dserver.example` cannot receive mail), address, social links.
- The three homepage reviews are marked as samples — replace them with real customer reviews.
- Privacy Policy and Terms are templates — have them reviewed.
- Contact form: `[dserver_contact]` sends to the Customizer email. Install an SMTP plugin (e.g. FluentSMTP) so mail is delivered.

## Media

Photos: Unsplash (Unsplash License). Background video: Pexels (Pexels License). Both allow free commercial use without attribution; every creator is credited on the Media Credits page (`assets/media/credits.json`). Logo, icons and the animated terminal are original.
