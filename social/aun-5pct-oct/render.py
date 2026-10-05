"""Render anim.html to video, frame by frame, with every CSS animation clock driven by hand.

usage: python render.py 916 45            full videos  -> output/aun-5pct-offer-<fmt>.mp4
       python render.py --stills 916 2.5 6 9.5 13     single frames -> _stills/
"""
import pathlib, shutil, subprocess, sys
from playwright.sync_api import sync_playwright

HERE = pathlib.Path(__file__).resolve().parent
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SIZE = {"916": (1080, 1920), "45": (1080, 1350), "11": (1080, 1080)}
FPS = 30
NAMES = {"916": "9x16", "45": "4x5", "11": "1x1"}


def page_for(p, fmt):
    w, h = SIZE[fmt]
    b = p.chromium.launch(channel="chrome", headless=True)
    pg = b.new_page(viewport={"width": w, "height": h}, device_scale_factor=1)
    pg.goto((HERE / "anim.html").as_uri() + f"?fmt={fmt}")
    pg.wait_for_load_state("load")
    pg.evaluate("document.fonts.ready")
    pg.evaluate("""() => Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => i.onload = i.onerror = r)))""")
    pg.evaluate("window.__A = document.getAnimations(); __A.forEach(a => a.pause())")
    return b, pg


def seek(pg, t):
    pg.evaluate(f"__A.forEach(a => a.currentTime = {t * 1000:.3f})")


with sync_playwright() as p:
    if sys.argv[1] == "--stills":
        fmt, times = sys.argv[2], sys.argv[3:]
        out = HERE / "_stills"
        out.mkdir(exist_ok=True)
        b, pg = page_for(p, fmt)
        for t in times:
            seek(pg, float(t))
            pg.screenshot(path=str(out / f"{fmt}_{t}.png"))
            print(out / f"{fmt}_{t}.png")
        b.close()
        sys.exit()

    for fmt in sys.argv[1:]:
        b, pg = page_for(p, fmt)
        dur = pg.evaluate("window.__DURATION")
        frames = HERE / f"_frames_{fmt}"
        shutil.rmtree(frames, ignore_errors=True)
        frames.mkdir()
        n = int(round(dur * FPS))
        for i in range(n):
            seek(pg, i / FPS)
            pg.screenshot(path=str(frames / f"f_{i:04d}.jpg"), type="jpeg", quality=95)
        b.close()
        (HERE / "output").mkdir(exist_ok=True)
        out = HERE / "output" / f"aun-5pct-offer-{NAMES[fmt]}.mp4"
        # soundtrack.wav (music.py) when present, otherwise a silent track: some placements reject video-only uploads
        snd = HERE / "soundtrack.wav"
        audio_in = ["-i", str(snd)] if snd.exists() else ["-f", "lavfi", "-i", "anullsrc=r=48000:cl=stereo"]
        r = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", "-framerate", str(FPS), "-i", str(frames / "f_%04d.jpg"),
                            *audio_in, "-t", f"{dur:.3f}",
                            # Chrome's JPEG frames are full-range: convert to standard video range or phones show it washed out
                            "-vf", "scale=in_range=pc:out_range=tv:out_color_matrix=bt709,format=yuv420p",
                            "-color_range", "tv", "-colorspace", "bt709", "-color_primaries", "bt709", "-color_trc", "bt709",
                            "-c:v", "libx264", "-preset", "slow", "-crf", "16", "-r", str(FPS),
                            "-c:a", "aac", "-b:a", "192k", "-movflags", "+faststart", str(out)], capture_output=True, text=True)
        if r.returncode:
            sys.exit(r.stderr[-3000:])
        shutil.rmtree(frames, ignore_errors=True)
        print(out)
