"""Generates MEDIA-CREDITS.md from photos-manifest.json + the video record.

Every externally sourced asset gets the block:
Asset Name / Purpose / Source / Original URL / Author / License /
Commercial Use / Attribution Required / Attribution Text.
"""
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
sys_path = str(HERE)
import sys  # noqa: E402

sys.path.insert(0, sys_path)
from build_photo_pack import PEXELS, VIDEO  # noqa: E402

photos = json.loads((HERE / "photos-manifest.json").read_text(encoding="utf-8"))
video = [{"key": k, "file": f, "alt": a, "usage": u, **PEXELS} for k, f, a, u in VIDEO]

HEAD = """# Media Credits

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

Totals: **{n_photos} photos** (Unsplash, WebP, longest side 1600 px; 2000 px for the hero) and **1 video** (Pexels; WebM + MP4 + WebP poster).

### Rejected during research

- Mixkit data-center clips — every candidate was marked "Mixkit Restricted License … Personal Use only" for the free 720p download, so none was used.
- Photos showing a manufacturer's logo prominently (for example branded SSDs) — replaced with original illustrations to avoid implying endorsement.
- Openverse CC0 results — too few suitable images; CC BY images were not needed.

"""

BLOCK = """### {n}. {name}

- **Asset Name:** {name}
- **File:** `{file}`
- **Purpose:** {purpose}
- **Source:** {source}
- **Original URL:** {url}
- **Author:** {author} ({author_url})
- **License:** {license} — {license_url}
- **Commercial Use:** Yes
- **Attribution Required:** No
- **Attribution Text:** {attr} (used on the Media Credits page)
- **Alt text:** {alt}

"""

TAIL = """## 3. Adding your own media

Record every new file in this format. Use only sources whose licence you have checked on the source's own licence page; if the licence cannot be verified, do not use the file. If attribution is required (for example CC BY on Wikimedia Commons or Openverse), fill in the credit fields on the attachment so the Media Credits page shows it.
"""


def title(item):
    return Path(item["file"]).stem.replace("-", " ").capitalize()


def main():
    out = HEAD.replace("{n_photos}", str(len(photos)))
    for n, item in enumerate(video + photos, 1):
        out += BLOCK.format(
            n=n,
            name=title(item),
            file=item["file"],
            purpose=item["usage"],
            source=item["source"],
            url=item["original_url"],
            author=item["author"],
            author_url=item["author_url"],
            license=item["license"],
            license_url=item["license_url"],
            attr=item["attribution_text"],
            alt=item["alt"],
        )
    out += TAIL
    for path in (ROOT / "MEDIA-CREDITS.md", ROOT / "palltheme" / "documentation" / "MEDIA-CREDITS.md"):
        path.write_text(out, encoding="utf-8")
    print(f"{len(video) + len(photos)} external assets written")


if __name__ == "__main__":
    main()
