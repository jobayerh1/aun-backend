"""
Turn Kohthai's white-background product shots into transparent cut-outs, and
work out where the BAG BODY sits inside each one.

The body box matters as much as the cut-out. The measurements on the page
describe the body only — not the strap — so if the whole photograph were scaled
to "27 x 22 cm" every bag with a shoulder strap would be drawn far too small.
Straps are thin, bodies are wide, so the body is found by looking at how wide
each row of the silhouette is.
"""

import json, io, os
import numpy as np
from PIL import Image, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, 'bags')
OUT = os.path.join(HERE, 'cutouts')
os.makedirs(OUT, exist_ok=True)

WHITE = 240        # a pixel this bright in every channel counts as background
MAX_SIDE = 560     # the bag is never drawn more than a few hundred px wide


def background_mask(rgb):
    """White pixels reachable from the border. Interior whites are kept."""
    near_white = np.all(rgb >= WHITE, axis=2)
    seed = np.zeros_like(near_white)
    seed[0, :] = near_white[0, :]
    seed[-1, :] = near_white[-1, :]
    seed[:, 0] = near_white[:, 0]
    seed[:, -1] = near_white[:, -1]

    # Grow the seed into the white region. Alternating sweeps converge in a
    # handful of passes where a plain dilation would need hundreds.
    prev = -1
    while seed.sum() != prev:
        prev = seed.sum()
        for _ in range(2):
            seed[1:, :] |= seed[:-1, :] & near_white[1:, :]
            seed[:, 1:] |= seed[:, :-1] & near_white[:, 1:]
            seed[:-1, :] |= seed[1:, :] & near_white[:-1, :]
            seed[:, :-1] |= seed[:, 1:] & near_white[:, :-1]
    return seed


def body_box_for_aspect(alpha, ratio):
    """
    Find the bag body using the one thing we actually trust: its measurements.

    Guessing the body from the silhouette alone kept failing — a chain handle is
    as wide as the bag, a dangling strap is as tall as it, and every heuristic
    that fixed one product broke another. But the true width-to-height ratio is
    already known from the page, so instead of guessing the shape we look for
    the rectangle OF THAT EXACT RATIO which best covers solid pixels.

    Two things fall out of this. The body box can never be the wrong shape, so
    the photograph is placed without a shred of distortion; and the search is
    driven by measured data rather than by another rule of thumb.
    """
    solid = (alpha > 90).astype(np.int32)
    H, W = solid.shape
    integral = np.zeros((H + 1, W + 1), dtype=np.int64)
    integral[1:, 1:] = solid.cumsum(axis=0).cumsum(axis=1)

    def rect_sums(w, h, step):
        ys = np.arange(0, H - h + 1, step)
        xs = np.arange(0, W - w + 1, step)
        if not len(ys) or not len(xs):
            return None, None, None
        yy, xx = np.meshgrid(ys, xs, indexing='ij')
        s = (integral[yy + h, xx + w] - integral[yy, xx + w]
             - integral[yy + h, xx] + integral[yy, xx])
        return s, yy, xx

    best = None
    # Density floors, tried strictest first. These start high on purpose: the
    # search maximises covered pixels, so a loose floor lets the WHOLE image win
    # whenever the straps are thin enough for the silhouette to stay dense — and
    # then the "body" includes the strap, the bag is drawn too small, and there
    # is no strap left above it to hang from the shoulder. A bag body is nearly
    # solid (~0.9); a body-plus-straps box is not.
    for density_floor in (0.88, 0.80, 0.70, 0.58, 0.45, 0.0):
        for frac in np.linspace(1.0, 0.35, 26):
            h = int(round(H * frac))
            w = int(round(h * ratio))
            if w < 8 or h < 8 or w > W or h > H:
                continue
            step = max(2, int(round(min(W, H) * 0.012)))
            s, yy, xx = rect_sums(w, h, step)
            if s is None:
                continue
            density = s / float(w * h)
            ok = density >= density_floor
            if not ok.any():
                continue
            score = np.where(ok, s, -1)
            i = int(np.argmax(score))
            if score.flat[i] < 0:
                continue
            cand = (int(score.flat[i]), int(yy.flat[i]), int(xx.flat[i]), w, h)
            if best is None or cand[0] > best[0]:
                best = cand
        if best is not None:
            break

    if best is None:
        return None
    _, y, x, w, h = best

    # Refine on a one-pixel grid in a small window around the winner.
    span = max(3, int(min(W, H) * 0.02))
    by, bx, bs = y, x, -1
    for yy in range(max(0, y - span), min(H - h, y + span) + 1):
        for xx in range(max(0, x - span), min(W - w, x + span) + 1):
            s = (integral[yy + h, xx + w] - integral[yy, xx + w]
                 - integral[yy + h, xx] + integral[yy, xx])
            if s > bs:
                bs, by, bx = s, yy, xx
    return bx, by, bx + w, by + h


def body_box(alpha):
    """
    The widest contiguous band of the silhouette — the bag itself, without the
    strap looping above it.
    """
    solid = alpha > 90
    rows = np.where(solid.any(axis=1))[0]
    if not len(rows):
        return None

    widths = np.zeros(alpha.shape[0], dtype=int)
    fill = np.zeros(alpha.shape[0], dtype=float)
    for y in rows:
        xs = np.where(solid[y])[0]
        widths[y] = xs[-1] - xs[0] + 1
        fill[y] = len(xs) / widths[y]

    # Width alone cannot tell a handle from the bag: a chain or a top handle
    # spans nearly the full width of the bag it hangs from. What separates them
    # is how SOLID the row is — an arc is two thin strands across a wide gap,
    # the body is filled edge to edge.
    peak = widths.max()
    wide = (widths >= peak * 0.5) & (fill >= 0.6)
    if not wide.any():
        wide = widths >= peak * 0.55

    y_peak = int(np.argmax(np.where(wide, widths, 0)))
    top = y_peak
    while top > 0 and wide[top - 1]:
        top -= 1
    bot = y_peak
    while bot < len(wide) - 1 and wide[bot + 1]:
        bot += 1

    # Trim the sides. A D-ring, a dangling strap end or a knotted scarf is only
    # a few pixels tall inside the band, while the bag itself fills most of it,
    # so drop columns that barely reach into the band and keep the run through
    # the fullest column.
    band = solid[top:bot + 1]
    counts = band.sum(axis=0)
    if counts.max() == 0:
        return None
    keep = counts >= counts.max() * 0.35
    x_peak = int(np.argmax(counts))
    left = x_peak
    while left > 0 and keep[left - 1]:
        left -= 1
    right = x_peak
    while right < len(keep) - 1 and keep[right + 1]:
        right += 1

    # Re-tighten vertically now that the sides are gone.
    core = solid[top:bot + 1, left:right + 1]
    rows2 = np.where(core.any(axis=1))[0]
    top2, bot2 = top + int(rows2[0]), top + int(rows2[-1])
    return int(left), top2, int(right) + 1, bot2 + 1


def process(path, slug, ratio):
    im = Image.open(path).convert('RGB')
    rgb = np.asarray(im).astype(np.uint8)
    bg = background_mask(rgb)

    alpha = np.where(bg, 0, 255).astype(np.uint8)
    a = Image.fromarray(alpha).filter(ImageFilter.GaussianBlur(0.7))   # soften the cut edge

    out = im.convert('RGBA')
    out.putalpha(a)

    arr = np.asarray(out)
    solid = arr[:, :, 3] > 8
    ys, xs = np.where(solid)
    if not len(ys):
        return None
    out = out.crop((xs.min(), ys.min(), xs.max() + 1, ys.max() + 1))

    box = body_box_for_aspect(np.asarray(out)[:, :, 3], ratio)
    if box is None:
        return None

    w, h = out.size
    scale = min(1.0, MAX_SIDE / max(w, h))
    if scale < 1.0:
        out = out.resize((max(1, round(w * scale)), max(1, round(h * scale))), Image.LANCZOS)
        box = tuple(round(v * scale) for v in box)
        w, h = out.size

    dst = os.path.join(OUT, slug + '.webp')
    out.save(dst, 'WEBP', quality=82, method=6)

    x0, y0, x1, y1 = box
    return {
        'slug': slug,
        'file': os.path.basename(dst),
        'img_w': w, 'img_h': h,
        'body': [round(x0 / w, 4), round(y0 / h, 4), round((x1 - x0) / w, 4), round((y1 - y0) / h, 4)],
        'body_px': [x0, y0, x1 - x0, y1 - y0],
        'bytes': os.path.getsize(dst),
    }


prods = json.load(io.open(os.path.join(HERE, 'kt-products2.json'), encoding='utf-8'))
dims = {}
for p in prods:
    d = p.get('dimensions') or {}
    dims[p['slug']] = d

results = []
for p in prods:
    slug = p['slug']
    src = os.path.join(SRC, slug + '.jpg')
    if not os.path.exists(src):
        print('MISSING', slug)
        continue
    d = dims.get(slug) or {}
    a = float(d.get('length') or 0); dep = float(d.get('width') or 0); t = float(d.get('height') or 0)
    if dep > 0 and t > 0 and dep > t:            # the plugin's own sanity rule
        dep, t = t, dep
    if not (a > 0 and t > 0):
        print('NO DIMENSIONS', slug); continue
    r = process(src, slug, a / t)
    if not r:
        print('FAILED', slug)
        continue
    bw, bh = r['body_px'][2], r['body_px'][3]
    r['photo_aspect'] = round(bw / bh, 3)
    results.append(r)
    print('%-46s body %4dx%-4d aspect %-6s %6.1f KB' %
          (slug[:46], bw, bh, r['photo_aspect'], r['bytes'] / 1024))

json.dump(results, io.open(os.path.join(HERE, 'cutouts.json'), 'w', encoding='utf-8'), indent=1)
print('\nwrote', len(results), 'cut-outs, total %.1f KB' % (sum(r['bytes'] for r in results) / 1024))
