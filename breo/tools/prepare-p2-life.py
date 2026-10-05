"""P2 lifestyle and detail images for the product page, from REAL photography only:
Breo's description images for the grey P2 (the original photo shoot, 1000px
wide) and our studio photos (6000px).

Do NOT use the 3840x5120 "main_images" in the same folder: they are AI-enlarged
copies of these photos (one is even stamped "AI生成"), with waxy skin and fingers
and a garbled breo logo ("DIQO"). The owner spotted it at a glance.

Every box sits clear of the printed captions and inside the frosted card it
belongs to (boxes read off a grid of each source). Two description images carry
an orange caption pill over plain background beside the model; it is lifted out
by blending the rows just above and below it, then the photo is cropped.

    python Breo/tools/prepare-p2-life.py            -> assets/media/p2-*.webp
    python Breo/tools/prepare-p2-life.py preview    -> also a contact sheet
"""
import os, sys
from PIL import Image

Image.MAX_IMAGE_PIXELS = None
HERE = os.path.dirname(os.path.abspath(__file__))
DESC = os.path.join(HERE, '..', 'Product Description & Main Images', 'P2',
                    'breo_p2_multipurpose_massage_pillow_wireless_neck_', 'description_images')
STUDIO = os.path.join(HERE, '..', 'Product Pic & Video', 'P2', 'White Background Picture', 'Grey')
OUT = os.path.join(HERE, '..', 'breo-bd-store', 'assets', 'media')


def lift(im, box, pad=6):
    """Blend each column of `box` between the clean rows just above and below it (overlays on a smooth gradient)."""
    x0, y0, x1, y1 = box
    px = im.load()
    for x in range(x0, x1):
        a = px[x, y0 - pad]
        b = px[x, y1 + pad]
        for y in range(y0 - pad + 1, y1 + pad):
            t = (y - (y0 - pad)) / float((y1 + pad) - (y0 - pad))
            px[x, y] = tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))
    return im


def desc(n, pill=None):
    def load():
        im = Image.open(os.path.join(DESC, n)).convert('RGB')
        return lift(im, pill) if pill else im
    return load


def studio(n):
    return lambda: Image.open(os.path.join(STUDIO, n)).convert('RGB')


SOURCES = {
    'd6': desc('6.jpg'),                              # 15-degree seesaw diagram (Breo's own render)
    'd9': desc('9.jpg', (478, 1648, 958, 1752)),      # "Massage waist when sitting" pill lifted
    'd10': desc('10.jpg', (480, 1336, 951, 1418)),    # "Hold and warm belly" pill lifted
    'd11': desc('11.jpg'),                            # sofa, warm red glow
    'd13': desc('13.jpg'),                            # finger on the button
    'd14': desc('14.jpg'),                            # lying on a rug beside it
    'd15': desc('15.jpg'),                            # lifting it up, laughing
    's25': studio('5W9A5025.JPG'),                    # 3/4 view: button and USB-C port
    's27': studio('5W9A5027.JPG'),                    # top view: the massage heads
}

# output, source, (x, y, w, h), longest side
CROPS = [
    # tiles (shown 4:3, about 420px wide; the wide one spans two columns)
    ('p2-tilt.webp', 'd6', (60, 900, 880, 660), 880),
    ('p2-heads.webp', 's27', (1500, 1000, 3600, 2700), 1400),
    ('p2-heat.webp', 'd11', (290, 1712, 650, 488), 650),
    ('p2-button.webp', 'd13', (60, 400, 840, 630), 840),
    ('p2-usbc.webp', 's25', (3100, 1900, 1500, 1125), 1200),
    ('p2-neck.webp', 'd9', (120, 760, 840, 580), 840),
    ('p2-back.webp', 'd9', (0, 1462, 1000, 958), 1000),
    ('p2-hug.webp', 'd10', (0, 1110, 1000, 1000), 1000),     # tile + gallery
    # gallery (square frame)
    ('p2-g-rug.webp', 'd14', (60, 1420, 880, 880), 880),
    ('p2-g-lift.webp', 'd15', (60, 560, 880, 880), 880),
    ('p2-g-back.webp', 'd9', (0, 1462, 958, 958), 958),
]

cache = {}
made = []
for name, src, (x, y, w, h), longest in CROPS:
    if src not in cache:
        cache[src] = SOURCES[src]()
    im = cache[src]
    assert x >= 0 and y >= 0 and x + w <= im.size[0] and y + h <= im.size[1], (name, im.size)
    c = im.crop((x, y, x + w, y + h))
    if max(c.size) > longest:
        k = longest / max(c.size)
        c = c.resize((round(c.size[0] * k), round(c.size[1] * k)), Image.LANCZOS)
    path = os.path.join(OUT, name)
    c.save(path, 'WEBP', quality=88, method=6)
    made.append((name, c))
    print('%-18s %4dx%-4d %4.0f KB' % (name, c.size[0], c.size[1], os.path.getsize(path) / 1024))

if 'preview' in sys.argv[1:]:
    from PIL import ImageDraw
    h = 360
    row = [c.resize((round(c.size[0] * h / c.size[1]), h)) for _, c in made]
    W = sum(r.size[0] for r in row) + 10 * len(row)
    sheet = Image.new('RGB', (W, h + 24), 'white')
    xo = 0
    for (name, _), r in zip(made, row):
        sheet.paste(r, (xo, 24))
        ImageDraw.Draw(sheet).text((xo + 2, 4), name, fill='black')
        xo += r.size[0] + 10
    out = os.path.join(os.environ.get('PREVIEW_DIR', HERE), 'p2-life-preview.jpg')
    sheet.save(out, quality=88)
    print('preview', out, sheet.size)
