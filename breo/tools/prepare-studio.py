"""Factory studio shots -> web images bundled with the plugin.

Trims the white margin, centres the product on a white square with even
breathing room (the product gallery is square and crops anything else), and
saves WebP. The importer copies these into the Media Library ('local' specs).

    python Breo/tools/prepare-studio.py
"""
import os
from PIL import Image, ImageChops

Image.MAX_IMAGE_PIXELS = None
HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', 'Product Pic & Video', 'P2', 'White Background Picture', 'Grey')
OUT = os.path.join(HERE, '..', 'breo-bd-store', 'assets', 'media')
os.makedirs(OUT, exist_ok=True)

JOBS = [  # source file, output name, size, fraction of the square the product fills
    ('5W9A5025.JPG', 'p2-studio-34.webp', 1600, 0.78),     # 3/4 view: logo, button, USB-C
    ('5W9A5006.JPG', 'p2-studio-stand.webp', 1600, 0.72),  # standing 3/4
    ('5W9A5000(1).jpg', 'p2-studio-front.webp', 1600, 0.78),
    ('5W9A5027.JPG', 'p2-studio-top.webp', 1600, 0.78),
    ('5W9A5022.JPG', 'p2-studio-side.webp', 1600, 0.82),
    ('5W9A5021.jpg', 'p2-studio-back.webp', 1600, 0.78),
    ('5W9A5006.JPG', 'p2-tile.webp', 1200, 0.70),          # product card / "where do you feel it" tile
]

for src, name, size, fill in JOBS:
    im = Image.open(os.path.join(SRC, src))
    im.draft('RGB', (4000, 4000))
    im = im.convert('RGB')
    # bounding box of anything not (near) white
    diff = ImageChops.difference(im, Image.new('RGB', im.size, (255, 255, 255))).convert('L')
    box = diff.point(lambda v: 255 if v > 10 else 0).getbbox()
    prod = im.crop(box)
    scale = (size * fill) / max(prod.size)
    prod = prod.resize((round(prod.size[0] * scale), round(prod.size[1] * scale)), Image.LANCZOS)
    canvas = Image.new('RGB', (size, size), (255, 255, 255))
    # sit slightly below centre, the way product photography is usually framed
    x = (size - prod.size[0]) // 2
    y = (size - prod.size[1]) // 2 + round(size * 0.02)
    canvas.paste(prod, (x, y))
    out = os.path.join(OUT, name)
    canvas.save(out, 'WEBP', quality=84, method=6)
    print('%-22s %4dx%d  %4.0f KB  (product %dx%d from %s)' % (name, size, size, os.path.getsize(out) / 1024, prod.size[0], prod.size[1], src))
