"""Phone-width capture: the page runs inside a 390px iframe (headless Chrome won't make a window that narrow),
cut into slices and laid side by side on one sheet.

usage: python mshot.py <name> <url> [height] [slice]
"""
import html, pathlib, subprocess, sys
from PIL import Image

here = pathlib.Path(__file__).parent / "_shots"
here.mkdir(exist_ok=True)
name, url = sys.argv[1], sys.argv[2]
h = int(sys.argv[3]) if len(sys.argv) > 3 else 6000
sl = int(sys.argv[4]) if len(sys.argv) > 4 else 1500
W = 390
wrap = here / f"{name}-wrap.html"
wrap.write_text(f'<!doctype html><html><body style="margin:0;background:#5b5b5b"><iframe src="{html.escape(url)}" style="border:0;width:{W}px;height:{h}px;display:block"></iframe></body></html>', encoding="utf-8")
full = here / f"{name}-full.png"
chrome = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
subprocess.run([chrome, "--headless=new", "--disable-gpu", "--hide-scrollbars", f"--user-data-dir={here / 'chrome-prof'}",
                f"--window-size=800,{h}", "--virtual-time-budget=7000", "--force-device-scale-factor=1",
                f"--screenshot={full}", wrap.as_uri()], check=True, capture_output=True, timeout=150)
im = Image.open(full).convert("RGB").crop((0, 0, W, h))
px = im.load()
bottom = h
while bottom > 1 and px[W // 2, bottom - 1] in ((91, 91, 91), (255, 255, 255)):
    bottom -= 1
im = im.crop((0, 0, W, bottom))
parts = [im.crop((0, t, W, min(bottom, t + sl))) for t in range(0, bottom, sl)]
sheet = Image.new("RGB", (len(parts) * (W + 16) - 16, sl), (60, 60, 60))
for i, p in enumerate(parts):
    sheet.paste(p, (i * (W + 16), 0))
sheet.save(here / f"{name}-sheet.jpg", quality=80)
print(here / f"{name}-sheet.jpg", "height", bottom)
