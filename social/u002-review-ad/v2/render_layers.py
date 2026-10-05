"""Render design.html layers to transparent PNGs with headless Chrome.  usage: python render_layers.py"""
import pathlib, subprocess, urllib.parse

HERE = pathlib.Path(__file__).resolve().parent
OUT = HERE / "layers"
OUT.mkdir(exist_ok=True)
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
PROFILE = pathlib.Path.home() / "AppData/Local/Temp/u002-ad-chrome"
SIZE = {"45": (1080, 1350), "916": (1080, 1920), "169": (1920, 1080)}
JOBS = [(f, l) for f in ("45", "916") for l in ("bg",)] + [("169", "hud")] + \
       [(f, l) for f in SIZE for l in ("o_bg", "o_logo", "o_product", "o_title", "o_chips", "o_cta")]

for fmt, layer in JOBS:
    w, h = SIZE[fmt]
    url = (HERE / "design.html").as_uri() + "?" + urllib.parse.urlencode({"fmt": fmt, "layer": layer})
    png = OUT / f"{layer}-{fmt}.png"
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", f"--user-data-dir={PROFILE}",
                    "--default-background-color=00000000", f"--window-size={w},{h}", "--force-device-scale-factor=1",
                    "--virtual-time-budget=2500", f"--screenshot={png}", url], check=True, capture_output=True, timeout=90)
    print(png.name)
