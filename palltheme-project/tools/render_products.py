"""Render original product SVG illustrations to WebP (main + detail view) with headless Chrome."""
import glob, io, os
from PIL import Image
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "palltheme-core", "assets", "demo")
OUT = os.path.join(SRC, "products")
os.makedirs(OUT, exist_ok=True)
files = sorted(glob.glob(os.path.join(SRC, "product-*.svg")))
with sync_playwright() as p:
    b = p.chromium.launch(channel="chrome", headless=True)
    pg = b.new_page(viewport={"width": 1000, "height": 1000}, device_scale_factor=1)
    for f in files:
        name = os.path.basename(f)[:-4]
        svg = open(f, encoding="utf-8").read().replace('width="800" height="800"', 'width="1000" height="1000"')
        pg.set_content(f'<html><body style="margin:0;background:#fff">{svg}</body></html>')
        png = pg.screenshot(clip={"x": 0, "y": 0, "width": 1000, "height": 1000})
        im = Image.open(io.BytesIO(png)).convert("RGB")
        im.save(os.path.join(OUT, name + ".webp"), "WEBP", quality=82, method=6)
        # Detail view: tighter crop of the device for the gallery.
        im.crop((150, 230, 850, 790)).resize((1000, 800), Image.LANCZOS).save(os.path.join(OUT, name + "-detail.webp"), "WEBP", quality=82, method=6)
    b.close()
print(len(files), "rendered;", sum(os.path.getsize(os.path.join(OUT, x)) for x in os.listdir(OUT)) // 1024, "KB total")
