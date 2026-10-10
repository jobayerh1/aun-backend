"""
Dev tool: build numbered contact sheets from Unsplash search candidates
(collected in the browser; free images only — Unsplash+ excluded).
usage: python unsplash_sheets.py <saved-tool-result.txt>
"""
import io
import json
import os
import sys
import urllib.request
from concurrent.futures import ThreadPoolExecutor

from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "candidates")
os.makedirs(OUT, exist_ok=True)

raw = json.load(open(sys.argv[1], encoding="utf-8"))
text = raw[0]["text"] if isinstance(raw, list) else raw
data, _ = json.JSONDecoder().raw_decode(text.strip())
if isinstance(data, str):
    data = json.loads(data)
json.dump(data, open(os.path.join(OUT, "unsplash_candidates.json"), "w", encoding="utf-8"), indent=0, ensure_ascii=False)


def thumb(path):
    url = f"https://images.unsplash.com/{path}?w=400&q=60&fm=jpg"
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    return Image.open(io.BytesIO(urllib.request.urlopen(req, timeout=40).read())).convert("RGB")


def sheet(slot, cands):
    tw, th = 400, 250
    img = Image.new("RGB", (tw * 4, th * 2), "white")
    d = ImageDraw.Draw(img)
    with ThreadPoolExecutor(8) as ex:
        thumbs = list(ex.map(lambda c: thumb(c[1]), cands))
    for i, t in enumerate(thumbs):
        t.thumbnail((tw - 6, th - 6))
        x, y = (i % 4) * tw, (i // 4) * th
        img.paste(t, (x + 3, y + 3))
        d.rectangle([x, y, x + 30, y + 22], fill="black")
        d.text((x + 9, y + 5), str(i), fill="white")
    img.save(os.path.join(OUT, f"u-{slot}.jpg"), quality=78)


for slot, cands in data.items():
    if cands:
        sheet(slot, cands)
print(len(data), "slots")
