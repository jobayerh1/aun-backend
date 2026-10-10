# Media Credits

Palltheme / Palltheme Core — © 2026 Engr. Nazim U Ahmed.

This file lists **every** image, video, animation, icon and font used by the theme, the companion plugin and the TechNova Systems demo website, with its source and licence. Nothing was used whose licence could not be verified on the source's own licence page.

## 1. Bundled with the theme and plugin (original work)

Everything inside `palltheme.zip` and `palltheme-core.zip` is original artwork created for this project. There are **no third-party images, videos or logos inside the zips**, so the zips carry no attribution requirements or third-party licence restrictions.

| Asset | Location | Created with | Licence |
|---|---|---|---|
| Hero network illustration | `palltheme-core/assets/images/hero-network.svg` | `tools/make_demo_art.py` | Proprietary — Engr. Nazim U Ahmed |
| Service, solution, case-study, blog and page covers (SVG) | `palltheme-core/assets/demo/cover-*.svg` | `tools/make_demo_art.py` | Proprietary — Engr. Nazim U Ahmed |
| Product illustrations — 42 generic devices, no manufacturer branding (SVG source + WebP renders, main + detail view) | `palltheme-core/assets/demo/product-*.svg`, `assets/demo/products/*.webp` | `tools/make_demo_art2.py`, `tools/render_products.py` | Proprietary — Engr. Nazim U Ahmed |
| Fictional client logos (10) | `palltheme-core/assets/demo/client-*.svg` | `tools/make_demo_art2.py` | Proprietary — Engr. Nazim U Ahmed |
| Illustrated avatars for demo team and testimonials (16; not photos of real people) | `palltheme-core/assets/demo/team-*.svg` | `tools/make_demo_art.py`, `tools/make_demo_art2.py` | Proprietary — Engr. Nazim U Ahmed |
| TechNova logo (light/dark) — fictional company | `palltheme-core/assets/demo/logo-technova*.svg` | `tools/make_demo_art.py` | Proprietary — Engr. Nazim U Ahmed |
| "Network pulse" Lottie animation | `palltheme-core/assets/lottie/network-pulse.json` | `tools/make_demo_art2.py` (original keyframes) | Proprietary — Engr. Nazim U Ahmed |
| Sample datasheet PDF | `palltheme-core/assets/demo/datasheet-sample.pdf` | `tools/make_datasheet.py` | Proprietary — Engr. Nazim U Ahmed |
| UI and content line icons (inline SVG) | `palltheme/inc/helpers.php`, `palltheme-core/includes/helpers/icons.php` | hand-written | Proprietary — Engr. Nazim U Ahmed |
| Payment method badges in the footer (text labels set in the Customizer, no card-network logos) | `palltheme/footer.php` | CSS text badges | Proprietary — Engr. Nazim U Ahmed |
| Fonts: Inter, Manrope, Plus Jakarta Sans, Space Grotesk | loaded from Google Fonts at runtime (not bundled) | — | SIL Open Font License 1.1 |
| lottie-web player | loaded from cdn.jsdelivr.net only on pages that show a Lottie animation | — | MIT (Airbnb) |

**Technology and product brand names** (Cisco, Dell, Fortinet, MikroTik, VMware, AWS …) appear only as text. They are trademarks of their owners; no logos are used and no partnership, reseller status or endorsement is claimed.

## 2. Licensed photos and video used on the demo website (not bundled)

These files are used on the TechNova Systems demo website through the optional **photo pack** (`wp-content/palltheme-photo-pack/`, built by `tools/media/build_photo_pack.py`). The Unsplash and Pexels licences allow free commercial use on a website but do **not** allow the files to be redistributed as part of a template or asset collection, so they are deliberately kept **out of the theme and plugin zips**. Each imported file also stores its credit on the attachment (Media Library → attachment details), and the demo's **Media Credits** page (`[pall_media_credits]`) lists them publicly.

Licences verified on the source websites:

- **Unsplash License** — https://unsplash.com/license — free to use for commercial and non-commercial purposes; no permission or attribution required (attribution appreciated). Not allowed: selling unaltered copies, or compiling photos to build a similar or competing service. Only free Unsplash photos were used — no Unsplash+ images.
- **Pexels License** — https://www.pexels.com/license/ — free to use; attribution not required. Not allowed: selling unaltered copies, implying endorsement by people or brands shown, or redistributing on other stock/wallpaper platforms.

Totals: **53 photos** (Unsplash, WebP, longest side 1600 px; 2000 px for the hero) and **1 video** (Pexels; WebM + MP4 + WebP poster).

### Rejected during research

- Mixkit data-center clips — every candidate was marked "Mixkit Restricted License … Personal Use only" for the free 720p download, so none was used.
- Photos showing a manufacturer's logo prominently (for example branded SSDs) — replaced with original illustrations to avoid implying endorsement.
- Openverse CC0 results — too few suitable images; CC BY images were not needed.

### 1. Network cables blue loop

- **Asset Name:** Network cables blue loop
- **File:** `network-cables-blue-loop.webm`
- **Purpose:** Homepage hero background video (WebM)
- **Source:** Pexels
- **Original URL:** https://www.pexels.com/video/blue-colored-cables-1085656/
- **Author:** Dima Krivoy (https://www.pexels.com/@dima-krivoy-413413/)
- **License:** Pexels License — https://www.pexels.com/license/
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Video by Dima Krivoy from Pexels (used on the Media Credits page)
- **Alt text:** Slow pan across blue network cables (looping background video)

### 2. Network cables blue loop

- **Asset Name:** Network cables blue loop
- **File:** `network-cables-blue-loop.mp4`
- **Purpose:** Homepage hero background video (MP4 fallback)
- **Source:** Pexels
- **Original URL:** https://www.pexels.com/video/blue-colored-cables-1085656/
- **Author:** Dima Krivoy (https://www.pexels.com/@dima-krivoy-413413/)
- **License:** Pexels License — https://www.pexels.com/license/
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Video by Dima Krivoy from Pexels (used on the Media Credits page)
- **Alt text:** Slow pan across blue network cables (looping background video)

### 3. Network cables blue poster

- **Asset Name:** Network cables blue poster
- **File:** `network-cables-blue-poster.webp`
- **Purpose:** Homepage hero video poster / mobile fallback image
- **Source:** Pexels
- **Original URL:** https://www.pexels.com/video/blue-colored-cables-1085656/
- **Author:** Dima Krivoy (https://www.pexels.com/@dima-krivoy-413413/)
- **License:** Pexels License — https://www.pexels.com/license/
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Video by Dima Krivoy from Pexels (used on the Media Credits page)
- **Alt text:** Blue network cables plugged into a patch panel

### 4. Data center network cabling blue light

- **Asset Name:** Data center network cabling blue light
- **File:** `data-center-network-cabling-blue-light.webp`
- **Purpose:** Homepage hero
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/sTv38REYWg8
- **Author:** Zoshua Colah (https://unsplash.com/@zoshuacolah)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Zoshua Colah on Unsplash (used on the Media Credits page)
- **Alt text:** Illuminated network cabling in a data center rack

### 5. Server rack green status lights

- **Asset Name:** Server rack green status lights
- **File:** `server-rack-green-status-lights.webp`
- **Purpose:** Homepage: why choose us
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/Kqg_6JPhxRk
- **Author:** Tyler (https://unsplash.com/@tylergm)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Tyler on Unsplash (used on the Media Credits page)
- **Alt text:** Server rack with green status lights

### 6. Enterprise network patch cables

- **Asset Name:** Enterprise network patch cables
- **File:** `enterprise-network-patch-cables.webp`
- **Purpose:** Service: Network Infrastructure
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/_MauPmUJJ08
- **Author:** U. Storsberg (https://unsplash.com/@ekiam14)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by U. Storsberg on Unsplash (used on the Media Credits page)
- **Alt text:** Blue network patch cables connected to a switch

### 7. Enterprise server drive bays

- **Asset Name:** Enterprise server drive bays
- **File:** `enterprise-server-drive-bays.webp`
- **Purpose:** Service: Server Solutions
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/VHmBX7FnXw0
- **Author:** Domaintechnik (https://unsplash.com/@fslfsl)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Domaintechnik on Unsplash (used on the Media Credits page)
- **Alt text:** Close-up of enterprise server drive bays

### 8. Cloud computing infrastructure 3d

- **Asset Name:** Cloud computing infrastructure 3d
- **File:** `cloud-computing-infrastructure-3d.webp`
- **Purpose:** Service: Cloud Computing
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/wlQUkvDhvQw
- **Author:** Growtika (https://unsplash.com/@growtika)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Growtika on Unsplash (used on the Media Credits page)
- **Alt text:** 3D illustration of cloud computing infrastructure

### 9. Cybersecurity padlock keyboard

- **Asset Name:** Cybersecurity padlock keyboard
- **File:** `cybersecurity-padlock-keyboard.webp`
- **Purpose:** Service: Cybersecurity
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/2T4l02ZYj-k
- **Author:** Sasun Bughdaryan (https://unsplash.com/@sasun1990)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Sasun Bughdaryan on Unsplash (used on the Media Credits page)
- **Alt text:** Padlock resting on a computer keyboard

### 10. It engineer working server rack

- **Asset Name:** It engineer working server rack
- **File:** `it-engineer-working-server-rack.webp`
- **Purpose:** Service: Managed IT Services
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/zBLtU0zbJcU
- **Author:** ThisisEngineering (https://unsplash.com/@thisisengineering)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by ThisisEngineering on Unsplash (used on the Media Credits page)
- **Alt text:** IT engineer working on a server rack

### 11. Data center server aisle

- **Asset Name:** Data center server aisle
- **File:** `data-center-server-aisle.webp`
- **Purpose:** Service: Data Center Solutions
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/lVZjvw-u9V8
- **Author:** İsmail Enes Ayhan (https://unsplash.com/@ismailenesayhan)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by İsmail Enes Ayhan on Unsplash (used on the Media Credits page)
- **Alt text:** Long aisle of server racks in a data center

### 12. Software development code screen

- **Asset Name:** Software development code screen
- **File:** `software-development-code-screen.webp`
- **Purpose:** Service: Software Development
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/8qEB0fTe9Vw
- **Author:** Mohammad Rahmani (https://unsplash.com/@afgprogrammer)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Mohammad Rahmani on Unsplash (used on the Media Credits page)
- **Alt text:** Source code on a monitor in a dark room

### 13. It consultants whiteboard planning

- **Asset Name:** It consultants whiteboard planning
- **File:** `it-consultants-whiteboard-planning.webp`
- **Purpose:** Service: IT Consultancy
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/uOhBxB23Wao
- **Author:** ThisisEngineering (https://unsplash.com/@thisisengineering)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by ThisisEngineering on Unsplash (used on the Media Credits page)
- **Alt text:** Two consultants planning at a whiteboard

### 14. Hard disk drive backup storage

- **Asset Name:** Hard disk drive backup storage
- **File:** `hard-disk-drive-backup-storage.webp`
- **Purpose:** Service: Backup & Disaster Recovery
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/VYfxkePredI
- **Author:** Nick (https://unsplash.com/@nkend)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Nick on Unsplash (used on the Media Credits page)
- **Alt text:** Open hard disk drive used for backup storage

### 15. Server rack network cables hosting

- **Asset Name:** Server rack network cables hosting
- **File:** `server-rack-network-cables-hosting.webp`
- **Purpose:** Service: VPS & Hosting
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/PSpf_XgOM5w
- **Author:** Scott Rodgerson (https://unsplash.com/@scottrodgerson)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Scott Rodgerson on Unsplash (used on the Media Credits page)
- **Alt text:** Network cables connected to hosting servers

### 16. Artificial intelligence processor chip

- **Asset Name:** Artificial intelligence processor chip
- **File:** `artificial-intelligence-processor-chip.webp`
- **Purpose:** Service: AI Solutions
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/sin5WZzF1U0
- **Author:** Milad Fakurian (https://unsplash.com/@fakurian)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Milad Fakurian on Unsplash (used on the Media Credits page)
- **Alt text:** Glowing AI processor chip

### 17. Iot smart sensors devices

- **Asset Name:** Iot smart sensors devices
- **File:** `iot-smart-sensors-devices.webp`
- **Purpose:** Service: IoT Solutions
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/9sO9CKo37Rg
- **Author:** Fajrul Islam (https://unsplash.com/@mfajruli)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Fajrul Islam on Unsplash (used on the Media Credits page)
- **Alt text:** Collection of IoT smart sensors and devices

### 18. Fiber optic strands light

- **Asset Name:** Fiber optic strands light
- **File:** `fiber-optic-strands-light.webp`
- **Purpose:** Solution: ISP
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/JyRTi3LoQnc
- **Author:** Denny Müller (https://unsplash.com/@redaquamedia)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Denny Müller on Unsplash (used on the Media Credits page)
- **Alt text:** Fiber optic strands carrying light

### 19. Telecom towers sunset

- **Asset Name:** Telecom towers sunset
- **File:** `telecom-towers-sunset.webp`
- **Purpose:** Solution: Telecom
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/0C9VmZUqcT8
- **Author:** Mario Caruso (https://unsplash.com/@giggiulena)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Mario Caruso on Unsplash (used on the Media Credits page)
- **Alt text:** Telecommunication towers at sunset

### 20. Enterprise glass office building

- **Asset Name:** Enterprise glass office building
- **File:** `enterprise-glass-office-building.webp`
- **Purpose:** Solution: Enterprise
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/Wm8opOd-MDE
- **Author:** Kenrick Baksh (https://unsplash.com/@kenrick)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Kenrick Baksh on Unsplash (used on the Media Credits page)
- **Alt text:** Curved glass facade of an office building

### 21. University lecture hall projector

- **Asset Name:** University lecture hall projector
- **File:** `university-lecture-hall-projector.webp`
- **Purpose:** Solution: Education
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/YRMWVcdyhmI
- **Author:** Dom Fou (https://unsplash.com/@domlafou)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Dom Fou on Unsplash (used on the Media Credits page)
- **Alt text:** University lecture hall with projector screen

### 22. Healthcare digital imaging tablet

- **Asset Name:** Healthcare digital imaging tablet
- **File:** `healthcare-digital-imaging-tablet.webp`
- **Purpose:** Solution: Healthcare
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/Wz4Mx3JbnzE
- **Author:** Vitaly Gariev (https://unsplash.com/@silverkblack)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Vitaly Gariev on Unsplash (used on the Media Credits page)
- **Alt text:** Clinician reviewing medical images on a tablet

### 23. Bank building financial district

- **Asset Name:** Bank building financial district
- **File:** `bank-building-financial-district.webp`
- **Purpose:** Solution: Banking
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/Skfy8ljB7X4
- **Author:** Joshua Woroniecki (https://unsplash.com/@joshuaworoniecki)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Joshua Woroniecki on Unsplash (used on the Media Credits page)
- **Alt text:** Classic bank building in a financial district

### 24. Government building columns

- **Asset Name:** Government building columns
- **File:** `government-building-columns.webp`
- **Purpose:** Solution: Government
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/eH-j6fLFZc4
- **Author:** Valentin Tatarnikov (https://unsplash.com/@valenen)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Valentin Tatarnikov on Unsplash (used on the Media Credits page)
- **Alt text:** Columns of a government building

### 25. Retail pos checkout terminal

- **Asset Name:** Retail pos checkout terminal
- **File:** `retail-pos-checkout-terminal.webp`
- **Purpose:** Solution: Retail
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/FbespiO-VQ8
- **Author:** SumUp (https://unsplash.com/@sumup)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by SumUp on Unsplash (used on the Media Credits page)
- **Alt text:** Point-of-sale terminal at a retail checkout

### 26. Industrial robot arm factory

- **Asset Name:** Industrial robot arm factory
- **File:** `industrial-robot-arm-factory.webp`
- **Purpose:** Solution: Manufacturing
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/sz1CHL7Pky0
- **Author:** Homa Appliances (https://unsplash.com/@homaappliances)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Homa Appliances on Unsplash (used on the Media Credits page)
- **Alt text:** Industrial robot arm on a factory line

### 27. Dark data center corridor

- **Asset Name:** Dark data center corridor
- **File:** `dark-data-center-corridor.webp`
- **Purpose:** Solution: Data Centers
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/s0XabTAKvak
- **Author:** Paul Hanaoka (https://unsplash.com/@plhnk)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Paul Hanaoka on Unsplash (used on the Media Credits page)
- **Alt text:** Dark data center corridor between server racks

### 28. Network rack patch cabling

- **Asset Name:** Network rack patch cabling
- **File:** `network-rack-patch-cabling.webp`
- **Purpose:** Case study: Enterprise Network Upgrade
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/yjygDnvRuaI
- **Author:** Victor Barrios (https://unsplash.com/@thevictorbarrios)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Victor Barrios on Unsplash (used on the Media Credits page)
- **Alt text:** Patch cabling in a network rack

### 29. Engineer laptop data center corridor

- **Asset Name:** Engineer laptop data center corridor
- **File:** `engineer-laptop-data-center-corridor.webp`
- **Purpose:** Case study: Data Center Modernization
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/glRqyWJgUeY
- **Author:** Christina @ wocintechchat.com M (https://unsplash.com/@wocintechchat)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Christina @ wocintechchat.com M on Unsplash (used on the Media Credits page)
- **Alt text:** Engineer with a laptop in a data center corridor

### 30. Laptop glowing screen dark

- **Asset Name:** Laptop glowing screen dark
- **File:** `laptop-glowing-screen-dark.webp`
- **Purpose:** Case study: Secure Cloud Migration
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/lzh3hPtJz9c
- **Author:** Joshua Woroniecki (https://unsplash.com/@joshuaworoniecki)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Joshua Woroniecki on Unsplash (used on the Media Credits page)
- **Alt text:** Laptop with a glowing screen in the dark

### 31. Fiber patch panel telecom

- **Asset Name:** Fiber patch panel telecom
- **File:** `fiber-patch-panel-telecom.webp`
- **Purpose:** Case study: ISP Network Expansion
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/eVWWr6nmDf8
- **Author:** Kirill Sh (https://unsplash.com/@kirill2020)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Kirill Sh on Unsplash (used on the Media Credits page)
- **Alt text:** Fiber patch panel with yellow cables

### 32. Security operations center screens

- **Asset Name:** Security operations center screens
- **File:** `security-operations-center-screens.webp`
- **Purpose:** Case study: Cybersecurity Deployment
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/TtMKq3lJm-U
- **Author:** Tasha Kostyuk (https://unsplash.com/@tashakostyuk)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Tasha Kostyuk on Unsplash (used on the Media Credits page)
- **Alt text:** Operator in front of a wall of monitoring screens

### 33. Hard drive platter dark

- **Asset Name:** Hard drive platter dark
- **File:** `hard-drive-platter-dark.webp`
- **Purpose:** Case study: Disaster Recovery
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/GNyjCePVRs8
- **Author:** benjamin lehman (https://unsplash.com/@abject)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by benjamin lehman on Unsplash (used on the Media Credits page)
- **Alt text:** Open hard drive platter on a dark background

### 34. Network switch ethernet cables

- **Asset Name:** Network switch ethernet cables
- **File:** `network-switch-ethernet-cables.webp`
- **Purpose:** Blog: enterprise network
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/SwVkmowt7qA
- **Author:** Jonathan (https://unsplash.com/@jonathanlei0)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Jonathan on Unsplash (used on the Media Credits page)
- **Alt text:** Ethernet cables plugged into a network switch

### 35. Server rack orange cables

- **Asset Name:** Server rack orange cables
- **File:** `server-rack-orange-cables.webp`
- **Purpose:** Blog: server planning
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/2JJ3wBHu4_0
- **Author:** Kevin Ache (https://unsplash.com/@kevinache)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Kevin Ache on Unsplash (used on the Media Credits page)
- **Alt text:** Server rack with orange cabling

### 36. Fiber optic bokeh blue

- **Asset Name:** Fiber optic bokeh blue
- **File:** `fiber-optic-bokeh-blue.webp`
- **Purpose:** Blog: 10GbE vs 25GbE
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/KABfjuSOx74
- **Author:** Sander Weeteling (https://unsplash.com/@sanderweeteling)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Sander Weeteling on Unsplash (used on the Media Credits page)
- **Alt text:** Blue fiber optic light points

### 37. Single cloud teal sky

- **Asset Name:** Single cloud teal sky
- **File:** `single-cloud-teal-sky.webp`
- **Purpose:** Blog: cloud migration
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/K-Iog-Bqf8E
- **Author:** C Dustin (https://unsplash.com/@dianamia)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by C Dustin on Unsplash (used on the Media Credits page)
- **Alt text:** Single cloud against a teal sky

### 38. Combination lock keyboard cards

- **Asset Name:** Combination lock keyboard cards
- **File:** `combination-lock-keyboard-cards.webp`
- **Purpose:** Blog: securing enterprise networks
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/FnA5pAzqhMM
- **Author:** Towfiqu barbhuiya (https://unsplash.com/@towfiqu999999)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Towfiqu barbhuiya on Unsplash (used on the Media Credits page)
- **Alt text:** Combination lock on a keyboard

### 39. Linux terminal sudo prompt

- **Asset Name:** Linux terminal sudo prompt
- **File:** `linux-terminal-sudo-prompt.webp`
- **Purpose:** Blog: Linux hardening
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/4Mw7nkQDByk
- **Author:** Gabriel Heinzer (https://unsplash.com/@6heinz3r)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Gabriel Heinzer on Unsplash (used on the Media Credits page)
- **Alt text:** Linux terminal prompt

### 40. Sfp ports fiber cables

- **Asset Name:** Sfp ports fiber cables
- **File:** `sfp-ports-fiber-cables.webp`
- **Purpose:** Blog: SFP+ and SFP28
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/T-IN5o3kxyA
- **Author:** Lightsaber Collection (https://unsplash.com/@lightsabercollection)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Lightsaber Collection on Unsplash (used on the Media Credits page)
- **Alt text:** Fiber cables in switch SFP ports

### 41. Power transmission lines sunset

- **Asset Name:** Power transmission lines sunset
- **File:** `power-transmission-lines-sunset.webp`
- **Purpose:** Blog: data center redundancy
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/FXw3zkbqd0w
- **Author:** Evgeniy Alyoshin (https://unsplash.com/@oqtave)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Evgeniy Alyoshin on Unsplash (used on the Media Credits page)
- **Alt text:** Power transmission lines at sunset

### 42. Server racks colorful cabling

- **Asset Name:** Server racks colorful cabling
- **File:** `server-racks-colorful-cabling.webp`
- **Purpose:** Blog: Proxmox virtualization
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/M5tzZtFCOfs
- **Author:** Taylor Vick (https://unsplash.com/@tvick)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Taylor Vick on Unsplash (used on the Media Credits page)
- **Alt text:** Server racks with colorful cabling

### 43. Container ship aerial view

- **Asset Name:** Container ship aerial view
- **File:** `container-ship-aerial-view.webp`
- **Purpose:** Blog: Docker
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/1cqIcrWFQBI
- **Author:** Venti Views (https://unsplash.com/@ventiviews)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Venti Views on Unsplash (used on the Media Credits page)
- **Alt text:** Aerial view of a container ship

### 44. Ship wheel helm

- **Asset Name:** Ship wheel helm
- **File:** `ship-wheel-helm.webp`
- **Purpose:** Blog: Kubernetes
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/eUMEWE-7Ewg
- **Author:** Joseph Barrientos (https://unsplash.com/@jbcreate_)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Joseph Barrientos on Unsplash (used on the Media Credits page)
- **Alt text:** Ship's wheel at the helm

### 45. Hard disk black white

- **Asset Name:** Hard disk black white
- **File:** `hard-disk-black-white.webp`
- **Purpose:** Blog: backup & DR planning
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/1qL31aacAPA
- **Author:** Denny Müller (https://unsplash.com/@redaquamedia)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Denny Müller on Unsplash (used on the Media Credits page)
- **Alt text:** Black-and-white hard disk close-up

### 46. Code on screen bokeh

- **Asset Name:** Code on screen bokeh
- **File:** `code-on-screen-bokeh.webp`
- **Purpose:** Blog: cybersecurity fundamentals
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/BfrQnKBulYQ
- **Author:** Shahadat Rahman (https://unsplash.com/@hishahadat)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Shahadat Rahman on Unsplash (used on the Media Credits page)
- **Alt text:** Colorful code on a screen

### 47. Cellular antennas tower

- **Asset Name:** Cellular antennas tower
- **File:** `cellular-antennas-tower.webp`
- **Purpose:** Blog: scalable ISP network
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/6mFcQOOi5QY
- **Author:** Igor (https://unsplash.com/@vulkiye)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Igor on Unsplash (used on the Media Credits page)
- **Alt text:** Cluster of cellular antennas on a tower

### 48. Laptop website desk

- **Asset Name:** Laptop website desk
- **File:** `laptop-website-desk.webp`
- **Purpose:** Blog: WordPress performance
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/zwsHjakE_iI
- **Author:** Anete Lūsiņa (https://unsplash.com/@anete_lusina)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Anete Lūsiņa on Unsplash (used on the Media Credits page)
- **Alt text:** Laptop showing a website on a desk

### 49. Online store laptop shopping

- **Asset Name:** Online store laptop shopping
- **File:** `online-store-laptop-shopping.webp`
- **Purpose:** Blog: WooCommerce at scale
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/slLo94wES2M
- **Author:** Shoper (https://unsplash.com/@shoperpl)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Shoper on Unsplash (used on the Media Credits page)
- **Alt text:** Person browsing an online store on a laptop

### 50. Circuit board teal macro

- **Asset Name:** Circuit board teal macro
- **File:** `circuit-board-teal-macro.webp`
- **Purpose:** Blog: AIOps
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/6BCgNl-UY6A
- **Author:** Anne Nygård (https://unsplash.com/@polarmermaid)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Anne Nygård on Unsplash (used on the Media Credits page)
- **Alt text:** Macro view of a teal circuit board

### 51. Technology team meeting laptops

- **Asset Name:** Technology team meeting laptops
- **File:** `technology-team-meeting-laptops.webp`
- **Purpose:** About: company overview
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/ZT5v0puBjZI
- **Author:** Mapbox (https://unsplash.com/@mapbox)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Mapbox on Unsplash (used on the Media Credits page)
- **Alt text:** Technology team meeting around laptops

### 52. Server rack green cabling

- **Asset Name:** Server rack green cabling
- **File:** `server-rack-green-cabling.webp`
- **Purpose:** About: infrastructure
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/OnI_TNcIv9U
- **Author:** Tyler (https://unsplash.com/@tylergm)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Tyler on Unsplash (used on the Media Credits page)
- **Alt text:** Server rack with green-lit cabling

### 53. Bright open plan office

- **Asset Name:** Bright open plan office
- **File:** `bright-open-plan-office.webp`
- **Purpose:** About: why choose us
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/wawEfYdpkag
- **Author:** Austin Distel (https://unsplash.com/@austindistel)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Austin Distel on Unsplash (used on the Media Credits page)
- **Alt text:** Bright open-plan office

### 54. Team desk laptops top view

- **Asset Name:** Team desk laptops top view
- **File:** `team-desk-laptops-top-view.webp`
- **Purpose:** Careers: why work with us
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/SYTO3xs06fU
- **Author:** Marvin Meyer (https://unsplash.com/@marvelous)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Marvin Meyer on Unsplash (used on the Media Credits page)
- **Alt text:** Top view of a team working on laptops

### 55. Team discussion table

- **Asset Name:** Team discussion table
- **File:** `team-discussion-table.webp`
- **Purpose:** Careers: culture
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/wR56AUlEsE4
- **Author:** Andreea Avramescu (https://unsplash.com/@minakko)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Andreea Avramescu on Unsplash (used on the Media Credits page)
- **Alt text:** Colleagues in discussion around a table

### 56. Support headset white background

- **Asset Name:** Support headset white background
- **File:** `support-headset-white-background.webp`
- **Purpose:** Support page
- **Source:** Unsplash
- **Original URL:** https://unsplash.com/photos/w0oCOQ7PN_o
- **Author:** Robert Stemler (https://unsplash.com/@robertstemler)
- **License:** Unsplash License — https://unsplash.com/license
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** Photo by Robert Stemler on Unsplash (used on the Media Credits page)
- **Alt text:** Support headset on a white background

## 3. Adding your own media

Record every new file in this format. Use only sources whose licence you have checked on the source's own licence page; if the licence cannot be verified, do not use the file. If attribution is required (for example CC BY on Wikimedia Commons or Openverse), fill in the credit fields on the attachment so the Media Credits page shows it.
