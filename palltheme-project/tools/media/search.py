"""
Media search helper (dev tool): queries the Openverse API for CC0 / Public Domain
images, keeps the licence metadata Openverse returns, and writes a numbered
contact sheet so candidates can be reviewed visually before selection.

usage: python search.py <slot> "<query>" [min_width]
Output: tools/media/candidates/<slot>.json and <slot>.jpg
"""
import io
import json
import os
import sys
import urllib.parse
import urllib.request

from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "candidates")
os.makedirs(OUT, exist_ok=True)
UA = {"User-Agent": "PallthemeDemoMedia/1.0 (local dev tool)"}


def get(url, timeout=40):
    return urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=timeout).read()


def search(query, min_width=1600, pages=2):
    found = []
    for page in range(1, pages + 1):
        qs = urllib.parse.urlencode({
            "q": query, "license": "cc0,pdm", "page_size": 20, "page": page, "mature": "false",
        })
        data = json.loads(get("https://api.openverse.org/v1/images/?" + qs))
        for r in data.get("results", []):
            if (r.get("width") or 0) >= min_width and (r.get("width") or 0) >= (r.get("height") or 1):
                found.append(r)
        if len(found) >= 12:
            break
    return found[:12]


def sheet(results, path):
    tw, th = 360, 240
    img = Image.new("RGB", (tw * 4, th * 3), "white")
    d = ImageDraw.Draw(img)
    for i, r in enumerate(results):
        try:
            t = Image.open(io.BytesIO(get(r["thumbnail"]))).convert("RGB")
            t.thumbnail((tw - 6, th - 6))
            img.paste(t, ((i % 4) * tw + 3, (i // 4) * th + 3))
        except Exception:  # noqa: BLE001
            pass
        x, y = (i % 4) * tw, (i // 4) * th
        d.rectangle([x, y, x + 34, y + 22], fill="black")
        d.text((x + 6, y + 5), str(i), fill="white")
        d.text((x + 40, y + 5), f"{r['source']} {r['width']}", fill="yellow")
    img.save(path, quality=80)


if __name__ == "__main__":
    slot, query = sys.argv[1], sys.argv[2]
    mw = int(sys.argv[3]) if len(sys.argv) > 3 else 1600
    res = search(query, mw)
    keep = [{k: r.get(k) for k in ("id", "title", "url", "thumbnail", "foreign_landing_url", "creator", "creator_url",
                                    "license", "license_version", "license_url", "source", "provider", "width", "height", "attribution")}
            for r in res]
    json.dump(keep, open(os.path.join(OUT, slot + ".json"), "w"), indent=1)
    sheet(res, os.path.join(OUT, slot + ".jpg"))
    for i, r in enumerate(keep):
        print(i, r["source"], r["license"], r["width"], "x", r["height"], "|", (r["title"] or "")[:60])
