"""See KE images for the product page, cut from Breo's listing graphics.

The listing images carry headlines and icons, so every crop takes only a
text-free region (boxes read off a 100px grid of each source). The acupoint
diagram is cropped right of its printed labels (one was misspelt); the names
are set as page text instead.

    python Breo/tools/prepare-seeke.py
"""
import os
from PIL import Image

Image.MAX_IMAGE_PIXELS = None
HERE = os.path.dirname(os.path.abspath(__file__))
BASE = os.path.join(HERE, '..', 'Product Description & Main Images', 'See KE', 'breo_seeke_eye_massager_with_hot_compress_stimulat')
DESC = os.path.join(BASE, 'description_images')
OUT = os.path.join(HERE, '..', 'breo-bd-store', 'assets', 'media')
os.makedirs(OUT, exist_ok=True)
acu = [f for f in os.listdir(DESC) if not f[0].isdigit()][0]  # the translated diagram

CROPS = [  # output, source, (x, y, w, h)
    ('ske-inside.webp', os.path.join(DESC, '9.jpg'), (0, 380, 790, 620)),        # the eye side, being wiped
    ('ske-heat.webp', os.path.join(DESC, '6.jpg'), (95, 1212, 680, 268)),        # heated inner shell render
    ('ske-wrap.webp', os.path.join(DESC, '7.jpg'), (0, 375, 790, 865)),          # reclining, 180-degree wrap
    ('ske-modes.webp', os.path.join(DESC, '8.png'), (235, 520, 555, 800)),       # one-button modes, music
    ('ske-front.webp', os.path.join(DESC, '4.jpg'), (14, 430, 764, 870)),        # glowing mask, front on
    ('ske-travel.webp', os.path.join(DESC, '10.jpg'), (0, 425, 790, 872)),       # folding into a bag
    ('ske-d-battery.webp', os.path.join(DESC, '11.png'), (385, 360, 385, 265)),  # clear of the card's captions
    ('ske-d-typec.webp', os.path.join(DESC, '11.png'), (405, 655, 365, 285)),
    ('ske-d-strap.webp', os.path.join(DESC, '11.png'), (260, 1160, 488, 205)),
    ('ske-acupoints.webp', os.path.join(DESC, acu), (445, 900, 1115, 1690)),
]
for name, src, (x, y, w, h) in CROPS:
    im = Image.open(src).convert('RGBA')
    bg = Image.new('RGBA', im.size, (255, 255, 255, 255))
    bg.alpha_composite(im)
    c = bg.convert('RGB').crop((x, y, x + w, y + h))
    if c.size[0] > 1400:
        c = c.resize((1400, round(c.size[1] * 1400 / c.size[0])), Image.LANCZOS)
    c.save(os.path.join(OUT, name), 'WEBP', quality=86, method=6)
    print('%-20s %4dx%-4d %4.0f KB' % (name, c.size[0], c.size[1], os.path.getsize(os.path.join(OUT, name)) / 1024))

# product tile: the grey studio shot centred on white, like the other products' tiles
st = Image.open(os.path.join(BASE, 'main_images', 'SEE KE Grey.png')).convert('RGBA')
st = st.crop(st.getbbox())
size, fill = 1200, 0.80
scale = (size * fill) / max(st.size)
st = st.resize((round(st.size[0] * scale), round(st.size[1] * scale)), Image.LANCZOS)
tile = Image.new('RGBA', (size, size), (255, 255, 255, 255))
tile.alpha_composite(st, ((size - st.size[0]) // 2, (size - st.size[1]) // 2 + round(size * 0.02)))
tile.convert('RGB').save(os.path.join(OUT, 'ske-tile.webp'), 'WEBP', quality=86, method=6)
print('ske-tile.webp        1200x1200 %4.0f KB' % (os.path.getsize(os.path.join(OUT, 'ske-tile.webp')) / 1024))
