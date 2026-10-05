"""Build aunstore-core/data/media-map.json: BD attachment id -> source URL.

Scans every data/products/*/description.txt and data/pages/*.txt for Flatsome image attributes
(img=, bg=, id= on ux_image, numeric ids= on ux_gallery) plus product.json
"images", then asks the BD site's public REST API for each file's URL.
Run again whenever a product's content changes; existing entries are kept.
"""
import json, re, sys, urllib.request, pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent / "aunstore-core" / "data"
BD = "https://aun-projector.com.bd/wp-json/wp/v2/media/"
ATTR = re.compile(r'\b(?:(?:[a-z]+_)?(?:img|bg)|id)="(\d+)"')
GALLERY = re.compile(r'\b(?:[a-z]+_)?ids="(\d+(?:\s*,\s*\d+)*)"')

def ids_in(text):
    found = set(int(m) for m in ATTR.findall(text))
    for group in GALLERY.findall(text):
        found.update(int(x) for x in group.split(","))
    return found

wanted = set()
for d in sorted((ROOT / "products").iterdir()):
    wanted |= ids_in((d / "description.txt").read_text(encoding="utf-8"))
    wanted |= set(json.loads((d / "product.json").read_text(encoding="utf-8"))["images"])
for page in sorted((ROOT / "pages").glob("*.txt")):
    wanted |= ids_in(page.read_text(encoding="utf-8"))

map_file = ROOT / "media-map.json"
media = json.loads(map_file.read_text(encoding="utf-8")) if map_file.exists() else {}
failed = []
for i in sorted(wanted):
    if str(i) in media:
        continue
    try:
        req = urllib.request.Request(BD + str(i), headers={"User-Agent": "Mozilla/5.0 (aunstore media-map builder)"})
        with urllib.request.urlopen(req, timeout=30) as r:
            media[str(i)] = json.load(r)["source_url"]
    except Exception as e:  # noqa: BLE001 - report and carry on
        failed.append((i, str(e)))

map_file.write_text(json.dumps(dict(sorted(media.items(), key=lambda kv: int(kv[0]))), indent=1), encoding="utf-8")
print(f"{len(wanted)} ids, {len(media)} mapped, {len(failed)} failed")
for i, e in failed:
    print("FAILED", i, e)
sys.exit(1 if failed else 0)
