# Palltheme — Premium Technology & eCommerce WordPress Theme

Creator, author & owner: **Engr. Nazim U Ahmed** · © 2026 · All rights reserved (see `palltheme/documentation/LICENSE.md`).

A dynamic, Elementor + WooCommerce theme for IT solution providers, server/networking companies, MSPs, system integrators, software companies and technology stores.

## Package contents

```
palltheme-project/
├── palltheme/                 ← the THEME (design, templates, Woo/Elementor styling)
│   ├── style.css  functions.php  header.php  footer.php  front-page.php
│   ├── index.php  single.php  page.php  archive.php  search.php  404.php  comments.php
│   ├── single-pall_service.php  single-pall_solution.php  single-pall_case_study.php
│   ├── single-pall_team.php  single-pall_job.php
│   ├── inc/        setup, customizer, enqueue, template-tags, menus, seo, elementor, woocommerce, admin, helpers
│   ├── template-parts/  header/ footer/ content/ single/
│   ├── templates/  Full Width + Blank Canvas page templates
│   ├── assets/css  main.css, woocommerce.css, editor.css
│   ├── assets/js   main.js, woocommerce.js
│   └── documentation/  INSTALLATION, PLUGIN-REQUIREMENTS, CUSTOMIZATION, PERFORMANCE, LICENSE
├── palltheme-core/            ← the COMPANION PLUGIN ("Technology Theme Core")
│   ├── palltheme-core.php
│   ├── includes/  post-types/ taxonomies/ fields/ admin/ render/ ajax/ elementor/ woocommerce/ frontend/ demo/ helpers/
│   ├── templates/maintenance.php
│   └── assets/    css, js, images, demo (original SVG artwork)
├── tools/                     ← developer tooling (not needed on a live site)
│   ├── make_demo_art*.py, render_products.py, make_datasheet.py   original demo artwork
│   ├── media/   photo research, download + WebP conversion, photo pack builder, credits generator
│   ├── qa/      link crawler, responsive/dark-mode checks, Elementor editor smoke test
│   └── build_zips.py           builds dist/*.zip (refuses to package licensed third-party media)
├── dist/                      ← palltheme.zip + palltheme-core.zip, ready to upload
├── QA-REPORT.md
├── MEDIA-CREDITS.md
└── README.md
```

## Quick start

1. Upload `dist/palltheme.zip` (Appearance → Themes) and `dist/palltheme-core.zip` (Plugins), activate both, then install Elementor and WooCommerce.
2. Optional: copy a photo pack to `wp-content/palltheme-photo-pack/` (see INSTALLATION.md).
3. **Theme Settings → Import Demo → Import demo website.**

Full details: `palltheme/documentation/INSTALLATION.md`.

## Architecture in one paragraph

The theme only presents; the plugin owns the data. Services, solutions, case studies, team, testimonials, clients, technologies, pricing, FAQs and jobs are custom post types with structured fields. Contact info, social links, hero, statistics and CTA live in one Business Settings option. Every section (hero, services grid, product carousel…) is a single PHP renderer exposed three ways — Elementor widget, shortcode, and the default homepage — so they always stay in sync and always query live content. Colors/fonts are Customizer settings mirrored into Elementor Global Colors/Fonts.
