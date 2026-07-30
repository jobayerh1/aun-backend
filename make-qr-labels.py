# Generates print-ready QR labels for the AUN Help Center (black & white, 300 DPI).
# Sizes: 2x2 inch (square) and 2x4 inch (portrait). Re-run after editing text/URL.
import qrcode
from PIL import Image, ImageDraw, ImageFont

URL   = "https://aun-projector.com.bd/help/"
BLACK = (0, 0, 0)
WHITE = (255, 255, 255)
OUT   = r"C:\Users\Jobayer Hossain\Downloads\Claude session"

def font(bold, size):
    path = r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf"
    try:
        return ImageFont.truetype(path, size)
    except Exception:
        return ImageFont.load_default()

def qr_img():
    qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_H, box_size=20, border=2)
    qr.add_data(URL); qr.make(fit=True)
    return qr.make_image(fill_color="black", back_color="white").convert("RGB")

def ctext(d, cx, y, text, fnt):
    w = d.textlength(text, font=fnt)
    d.text((cx - w / 2, y), text, font=fnt, fill=BLACK)

QR = qr_img()

# ---------- 2x2 inch (600 x 600) ----------
W, H = 600, 600
img = Image.new("RGB", (W, H), WHITE); d = ImageDraw.Draw(img); cx = W // 2
ctext(d, cx, 26, "AUN PROJECTOR", font(True, 30))
ctext(d, cx, 64, "Help Center / Video Tutorials", font(False, 18))
img.paste(QR.resize((384, 384), Image.NEAREST), (cx - 192, 102))
ctext(d, cx, 506, "Scan with your phone camera", font(False, 17))
ctext(d, cx, 532, "aun-projector.com.bd/help", font(True, 19))
img.save(OUT + r"\help-qr-label-2x2.png", dpi=(300, 300))

# ---------- 2x4 inch portrait (600 x 1200) ----------
W, H = 600, 1200
img = Image.new("RGB", (W, H), WHITE); d = ImageDraw.Draw(img); cx = W // 2
ctext(d, cx, 48, "AUN PROJECTOR", font(True, 40))
ctext(d, cx, 104, "Help Center / Video Tutorials", font(False, 24))
d.line((60, 162, 540, 162), fill=BLACK, width=2)
ctext(d, cx, 188, "SCAN FOR HELP", font(True, 26))
img.paste(QR.resize((460, 460), Image.NEAREST), (cx - 230, 236))
ctext(d, cx, 724, "Scan with your phone camera for:", font(False, 22))
y = 724; bx = 96
for line in ["•  Setup, keystone & focus guides",
             "•  Screen mirroring (iPhone / Android)",
             "•  Video tutorials for YOUR model"]:
    y += 48
    d.text((bx, y), line, font=font(False, 22), fill=BLACK)
ctext(d, cx, y + 84, "aun-projector.com.bd/help", font(True, 26))
ctext(d, cx, y + 134, "Need help? WhatsApp +880 1787-698268", font(False, 19))
img.save(OUT + r"\help-qr-label-2x4.png", dpi=(300, 300))

print("OK: help-qr-label-2x2.png + help-qr-label-2x4.png saved (300 DPI, B/W)")
