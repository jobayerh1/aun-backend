"""Builds the optional photo pack for a specific website.

The pack holds third-party photos (Unsplash) and video (Pexels) that are
licensed for use on a website but may NOT be redistributed inside the
theme/plugin zips. The demo importer reads <dest>/manifest.json.

Usage: python build_photo_pack.py [dest]
Default dest: the local test site's wp-content/palltheme-photo-pack.
"""
import json
import shutil
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
DEST = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(
    r"C:\Users\Jobayer Hossain\wp-local\palltheme-site\wp-content\palltheme-photo-pack"
)

PEXELS = {
    "source": "Pexels",
    "source_id": "1085656",
    "original_url": "https://www.pexels.com/video/blue-colored-cables-1085656/",
    "author": "Dima Krivoy",
    "author_url": "https://www.pexels.com/@dima-krivoy-413413/",
    "license": "Pexels License",
    "license_url": "https://www.pexels.com/license/",
    "commercial_use": True,
    "attribution_required": False,
    "attribution_text": "Video by Dima Krivoy from Pexels",
}
VIDEO = [
    ("hero-video-webm", "network-cables-blue-loop.webm", "Slow pan across blue network cables (looping background video)", "Homepage hero background video (WebM)"),
    ("hero-video-mp4", "network-cables-blue-loop.mp4", "Slow pan across blue network cables (looping background video)", "Homepage hero background video (MP4 fallback)"),
    ("hero-poster", "network-cables-blue-poster.webp", "Blue network cables plugged into a patch panel", "Homepage hero video poster / mobile fallback image"),
]


def main() -> None:
    DEST.mkdir(parents=True, exist_ok=True)
    manifest = json.loads((HERE / "photos-manifest.json").read_text(encoding="utf-8"))
    for item in manifest:
        shutil.copy2(HERE / "photos" / item["file"], DEST / item["file"])
    for key, file, alt, usage in VIDEO:
        src = HERE / "video" / file
        shutil.copy2(src, DEST / file)
        manifest.append({"key": key, "file": file, "alt": alt, "usage": usage, **PEXELS, "bytes": src.stat().st_size})
    (DEST / "manifest.json").write_text(json.dumps(manifest, indent=1, ensure_ascii=False), encoding="utf-8")
    (DEST / "README.txt").write_text(
        "Licensed third-party media for this website only (Unsplash License / Pexels License).\n"
        "Do not redistribute this folder inside the theme or plugin. Credits: manifest.json.\n",
        encoding="utf-8",
    )
    total = sum(i["bytes"] for i in manifest)
    print(f"{len(manifest)} items, {total // 1024} KB -> {DEST}")


if __name__ == "__main__":
    main()
