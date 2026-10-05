"""Cut every projector view out of its background and lay them out on one identical "studio" canvas.

Input : aunstore/projector picture/<model folder>/<view>.<ext>   (front / side / back, any background)
Output: aunstore-core/assets/views/<key>-front|back.webp (side views only help match the scale: tools/_views/)  (transparent 1200x900, device on the same floor line)

  · transparent sources are used as they are (except FORCE: a source with stray frame lines);
    everything else goes through rembg (isnet-general-use);
  · dark devices shot on white: large white patches left inside the outline (e.g. under a stand) are removed;
  · all views of one model share one scale (matched by silhouette area, so the device doesn't grow or shrink as it
    turns), and every model is fitted to the same box.
Run:  python aunstore/tools/cutout-views.py
"""
import pathlib
import numpy as np
from PIL import Image
from scipy import ndimage

ROOT = pathlib.Path(__file__).resolve().parents[1]
SRC = ROOT / "projector picture"
OUT = ROOT / "aunstore-core" / "assets" / "views"
OUT.mkdir(parents=True, exist_ok=True)

KEYS = {"a004 pro": "a004-pro", "a005": "a005", "a32 pro": "a32-pro", "aun a45 pro": "a45-pro", "u001 pro": "u001-pro", "u002 pro": "u002-pro"}
FORCE = {"u001-pro-front"}                 # transparent, but carries a faint frame around it
DARK = {"a005", "a32-pro", "u001-pro"}     # dark devices: near-white blobs are background, not product
W, H = 1200, 900
BOX_W, BOX_H = 0.80, 0.70
FLOOR = 0.84

session = None


def cut(im, name):
    global session
    if im.mode == "RGBA" and im.getchannel("A").getextrema()[0] < 10 and name not in FORCE:
        return im
    from rembg import remove, new_session
    if session is None:
        session = new_session("isnet-general-use")
    rgb = Image.alpha_composite(Image.new("RGBA", im.size, (255, 255, 255, 255)), im.convert("RGBA")).convert("RGB")
    return remove(rgb, session=session, post_process_mask=True)


def clean(im, key):
    a = np.array(im)
    alpha = a[..., 3]
    alpha[alpha < 24] = 0
    if key in DARK:
        white = (a[..., :3].min(axis=2) > 225) & (alpha > 0)
        lab, n = ndimage.label(white)
        sizes = ndimage.sum(white, lab, range(1, n + 1))
        for i, s in enumerate(sizes, 1):
            if s > 300:  # a patch of background, not a lens highlight
                alpha[lab == i] = 0
    # keep only the device: the largest connected shape (drops stray specks and frame lines)
    lab, n = ndimage.label(alpha > 40)
    if n > 1:
        sizes = ndimage.sum(alpha > 40, lab, range(1, n + 1))
        keep = 1 + int(np.argmax(sizes))
        grown = ndimage.binary_dilation(lab == keep, iterations=3)
        alpha[~grown] = 0
    a[..., 3] = alpha
    out = Image.fromarray(a, "RGBA")
    return out.crop(out.getchannel("A").getbbox())


views = {}
for folder in sorted(p for p in SRC.iterdir() if p.is_dir()):
    key = KEYS[folder.name.lower()]
    for f in sorted(folder.iterdir()):
        view = next(v for v in ("front", "side", "back") if v in f.stem.lower())
        im = clean(cut(Image.open(f).convert("RGBA"), f"{key}-{view}"), key)
        views.setdefault(key, {})[view] = im

for key, vs in views.items():
    # one scale per model: equal silhouette area across its views, then the biggest view fits the box
    area = {v: float((np.array(im.getchannel("A")) > 128).sum()) for v, im in vs.items()}
    ref = max(area.values())
    rel = {v: (ref / area[v]) ** 0.5 for v in vs}              # scale that brings each view to the same area
    fit = min(min(W * BOX_W / (vs[v].width * rel[v]), H * BOX_H / (vs[v].height * rel[v])) for v in vs)
    for v, im in vs.items():
        s = rel[v] * fit
        im = im.resize((round(im.width * s), round(im.height * s)), Image.LANCZOS)
        canvas = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        canvas.alpha_composite(im, ((W - im.width) // 2, round(H * FLOOR) - im.height))
        out = (ROOT / "tools" / "_views" if v == "side" else OUT) / f"{key}-{v}.webp"  # sides only help the scale; not shipped
        canvas.save(out, "WEBP", quality=86, method=6)
        print(out.name, im.size, out.stat().st_size // 1024, "KB")
