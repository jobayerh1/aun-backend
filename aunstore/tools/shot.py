"""Headless-Chrome screenshot of a bench preview page, cut into viewable slices.

usage: python shot.py <name> <url> [width] [height] [slice_height]
Writes <name>-full.png and <name>-1.jpg, <name>-2.jpg … next to this script and prints their paths.
"""
import pathlib, subprocess, sys
from PIL import Image

here = pathlib.Path(__file__).parent / "_shots"
here.mkdir(exist_ok=True)
name, url = sys.argv[1], sys.argv[2]
w = int(sys.argv[3]) if len(sys.argv) > 3 else 1440
h = int(sys.argv[4]) if len(sys.argv) > 4 else 5200
sl = int(sys.argv[5]) if len(sys.argv) > 5 else 1300
full = here / f"{name}-full.png"
chrome = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
prof = here / "chrome-prof"
subprocess.run([chrome, "--headless=new", "--disable-gpu", "--hide-scrollbars", f"--user-data-dir={prof}",
                f"--window-size={w},{h}", f"--virtual-time-budget={__import__('os').environ.get('VTB', '6000')}", "--force-device-scale-factor=1",
                f"--screenshot={full}", url], check=True, capture_output=True, timeout=120)
im = Image.open(full).convert("RGB")
# trim the empty grey area below the page (the harness paints html grey)
px = im.load()
bottom = im.height
while bottom > 1 and px[w // 2, bottom - 1] == (91, 91, 91):
    bottom -= 1
im = im.crop((0, 0, w, bottom))
n = 0
for top in range(0, im.height, sl):
    n += 1
    im.crop((0, top, w, min(im.height, top + sl))).save(here / f"{name}-{n}.jpg", quality=82)
    print(here / f"{name}-{n}.jpg")
print("height", im.height)
