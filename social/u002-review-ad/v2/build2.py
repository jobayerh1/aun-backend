"""Dress the owner's own 50-second edit as a Facebook ad: animated headlines, Bangla subtitles, progress bar,
branded layout per format, and an animated outro over the empty tail.

usage: python build2.py [45|916|169 ...]      (default: all three)
The edit's picture and sound are used exactly as delivered — nothing is re-cut.
Captions live in captions.json next to this script (edit text there, then re-run).
"""
import json, pathlib, subprocess, sys
from PIL import Image, ImageDraw, ImageFilter

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parent.parent.parent
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SRC = str(ROOT / "u002 gadget insider ad.mp4")
LAY = HERE / "layers"
WORK = pathlib.Path.home() / "AppData/Local/Temp/u002-ad-work"
WORK.mkdir(parents=True, exist_ok=True)
OUT = HERE / "output"
OUT.mkdir(exist_ok=True)
X264 = ["-c:v", "libx264", "-preset", "medium", "-crf", "17", "-pix_fmt", "yuv420p", "-r", "30000/1001"]

# the edit goes black at 49.72 while his last words run to 50.1: the outro fades in over the black, and his
# voice is left untouched to the end of the edit's audio
XF_AT, XF, OUTRO = 49.45, 0.35, 3.8
TOTAL = XF_AT + OUTRO
CAP = json.loads((HERE / "captions.json").read_text(encoding="utf-8"))

FMT = {
    # canvas, video window (x, y, w, h, radius) or None for full frame
    "45": dict(W=1080, H=1350, win=(30, 350, 1020, 574, 28)),
    "916": dict(W=1080, H=1920, win=(30, 470, 1020, 765, 32)),
    "169": dict(W=1920, H=1080, win=None),
}
# 9:16 shows a 4:3 slice of each shot; where the subject sits off-centre, steer the slice (edit time → centre x)
CROP43 = [(0, .50), (7.0, .58), (8.94, .62), (10.31, .50), (13.68, .42), (23.26, .50)]


def ff(args, cwd=None):
    r = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", *args], cwd=cwd, capture_output=True, text=True)
    if r.returncode:
        sys.exit("ffmpeg failed:\n" + r.stderr[-4000:])


def ts(t):
    t = max(t, 0)
    return f"{int(t // 3600)}:{int(t % 3600 // 60):02d}:{t % 60:05.2f}"


def rounded_mask(w, h, r, path):
    im = Image.new("L", (w * 4, h * 4), 0)
    ImageDraw.Draw(im).rounded_rectangle([0, 0, w * 4 - 1, h * 4 - 1], r * 4, fill=255)
    im.resize((w, h), Image.LANCZOS).save(path)


def glow(path, size=900, rgb=(1, 136, 254)):
    im = Image.new("RGBA", (size, size), rgb + (0,))
    a = Image.new("L", (size, size), 0)
    ImageDraw.Draw(a).ellipse([size * .2, size * .2, size * .8, size * .8], fill=150)
    im.putalpha(a.filter(ImageFilter.GaussianBlur(size * .12)))
    im.save(path)


YELLOW, BLUE2, MUTED = "&H4DD3FF&", "&HFFB34F&", "&HE0CBB9&"


def hl(text):
    while "*" in text:
        text = text.replace("*", "{\\c" + YELLOW + "}", 1).replace("*", "{\\c&HFFFFFF&}", 1)
    return text


def ass(fmt):
    W, H = FMT[fmt]["W"], FMT[fmt]["H"]
    if fmt == "45":
        head = dict(an=8, x=540, y=156, fs=68)
        sub = dict(an=5, x=540, y=1052, fs=56, style="SubFlat")
        bar = (30, 932, 1020, 6)
        lower = dict(x=540, y=1052)
    elif fmt == "916":
        head = dict(an=8, x=540, y=278, fs=72)
        sub = dict(an=2, x=540, y=1212, fs=56, style="SubOver")
        bar = (30, 1245, 1020, 6)
        lower = dict(x=540, y=1150)
    else:
        head = dict(an=7, x=70, y=88, fs=66)
        sub = dict(an=2, x=960, y=1000, fs=58, style="SubOver", m=380)  # wrap before the website tag (bottom-right)
        bar = (0, 1074, 1920, 6)
        lower = dict(x=330, y=930)
    lines = [f"""[Script Info]
ScriptType: v4.00+
PlayResX: {W}
PlayResY: {H}
WrapStyle: 0
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Head,Nirmala UI,{head['fs']},&H00FFFFFF,&H00FFFFFF,&H00000000,&H80000000,1,0,0,0,100,100,0,0,1,0,3,{head['an']},60,60,0,1
Style: SubFlat,Nirmala UI,{sub['fs']},&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,1,0,0,0,100,100,0,0,1,0,0,5,70,70,0,1
Style: SubOver,Nirmala UI,{sub['fs']},&H00FFFFFF,&H00FFFFFF,&H00000000,&H64000000,1,0,0,0,100,100,0,0,1,4,2,2,{sub.get('m', 70)},{sub.get('m', 70)},0,1
Style: Small,Nirmala UI,28,&H00FFFFFF,&H00FFFFFF,&H00000000,&H80000000,1,0,0,0,100,100,0,0,1,0,2,7,0,0,0,1
Style: Shape,Nirmala UI,20,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,7,0,0,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text"""]
    ev = lines.append
    # progress bar: dim track + brand-blue fill that grows to the full width by the outro
    bx, by, bw, bh = bar
    shape = f"m 0 0 l {bw} 0 l {bw} {bh} l 0 {bh}"
    ev(f"Dialogue: 0,{ts(0)},{ts(XF_AT)},Shape,,0,0,0,,{{\\an7\\pos({bx},{by})\\c&HFFFFFF&\\alpha&HC8&\\p1}}{shape}")
    ev(f"Dialogue: 1,{ts(0)},{ts(XF_AT)},Shape,,0,0,0,,{{\\an7\\pos({bx},{by})\\c&HFE8801&\\clip({bx},{by},{bx},{by + bh})"
       f"\\t(0,{int(XF_AT * 1000)},\\clip({bx},{by},{bx + bw},{by + bh}))\\p1}}{shape}")
    if fmt == "169":
        ev(f"Dialogue: 2,{ts(0)},{ts(XF_AT)},Small,,0,0,0,,{{\\an7\\pos(70,44)\\c{MUTED}}}রিভিউ · Gadget Insider Bangla")
    # headlines: slide up + settle; two-tone (white, then light blue)
    for h in CAP["headlines"]:
        t0, t1 = h["t0"], h["t1"]
        fin = 0 if t0 == 0 else 220
        x, y = head["x"], head["y"]
        txt = h["l1"] + (f"\\N{{\\c{BLUE2}}}{h['l2']}" if h.get("l2") else "")
        mv = "" if t0 == 0 else f"\\move({x},{y + 34},{x},{y},0,380)"
        pos = f"\\pos({x},{y})" if t0 == 0 else ""
        ev(f"Dialogue: 3,{ts(t0)},{ts(t1)},Head,,0,0,0,,{{\\an{head['an']}{pos}{mv}\\fad({fin},200)"
           f"\\fscx{100 if t0 == 0 else 94}\\fscy{100 if t0 == 0 else 94}\\t(0,380,\\fscx100\\fscy100)}}{txt}")
    # subtitles (the reviewer's words). A card holds until the next one when the gap is only a breath, so the
    # line doesn't blink off between phrases; back-to-back cards (split at a breath) skip the slide-in.
    subs = CAP["subtitles"]
    for i, s in enumerate(subs):
        x, y = sub["x"], sub["y"]
        nxt = subs[i + 1]["t0"] if i + 1 < len(subs) else None
        t1 = nxt if nxt is not None and nxt - s["t1"] < 0.7 else s["t1"]
        t1 = min(t1, XF_AT + XF)
        joined = i > 0 and s["t0"] - subs[i - 1]["t1"] < 0.7
        anim = f"\\pos({x},{y})\\fad(0,0)" if joined else f"\\move({x},{y + 10},{x},{y},0,160)\\fad(90,0)"
        ev(f"Dialogue: 4,{ts(s['t0'])},{ts(t1)},{sub['style']},,0,0,0,,{{\\an{sub['an']}{anim}}}{hl(s['text'])}")
    # optional reviewer lower third
    lt = CAP.get("lower_third")
    if lt:
        ev(f"Dialogue: 4,{ts(lt['t0'])},{ts(lt['t1'])},SubFlat,,0,0,0,,{{\\an5\\move({lower['x']},{lower['y'] + 14},{lower['x']},{lower['y']},0,260)"
           f"\\fad(220,200)\\fs34\\c{MUTED}\\bord{0 if fmt == '45' else 3}\\3c&H000000&}}{lt['l1']}\\N{{\\fs54\\c&HFFFFFF&}}{lt['l2']}")
    p = WORK / f"subs-{fmt}.ass"
    p.write_text("\n".join(lines) + "\n", encoding="utf-8-sig")
    return p.name


def crop_x_expr():
    """Piecewise crop offset: if(lt(t,t1),x0,if(lt(t,t2),x1,...))."""
    parts = [(t, min(max(cx * 1920 - 720, 0), 480)) for t, cx in CROP43]
    expr = f"{parts[-1][1]:.0f}"
    for i in range(len(parts) - 2, -1, -1):
        expr = f"if(lt(t\\,{parts[i + 1][0]})\\,{parts[i][1]:.0f}\\,{expr})"
    return expr


def build(fmt):
    F = FMT[fmt]
    W, H, win = F["W"], F["H"], F["win"]
    sub = ass(fmt)
    if not (WORK / "fonts").exists():
        (WORK / "fonts").mkdir()
        import shutil
        shutil.copy(r"C:\Windows\Fonts\Nirmala.ttc", WORK / "fonts" / "Nirmala.ttc")
    glow(WORK / "glow.png")
    # 1) blur the film's burned-in profanity subtitle (4.6–7.0 s), on the untouched 1920x1080 picture
    clean = "[0:v]split[a][b];[b]crop=230:46:840:648,boxblur=luma_radius=10:luma_power=3:chroma_radius=5:chroma_power=2[bl];[a][bl]overlay=840:648:enable='between(t,4.55,7.02)'[v0]"
    main_len = XF_AT + XF
    if win:
        x, y, w, h, r = win
        rounded_mask(w, h, r, WORK / f"mask-{fmt}.png")
        if fmt == "916":
            fit = f"[v0]crop=1440:1080:'{crop_x_expr()}':0,scale={w}:{h}:flags=lanczos[vs]"
        else:
            fit = f"[v0]scale={w}:{h}:flags=lanczos[vs]"
        fc = (f"{clean};{fit};[2:v]format=gray[mk];[vs][mk]alphamerge[vr];"
              f"[1:v]format=rgba[bg];[3:v]format=rgba[gl];"
              f"[bg][gl]overlay=x='{W // 2 - 450}+220*sin(t/3.1)':y='{y + h // 2 - 450}+120*cos(t/4.3)'[bg2];"
              f"[bg2][vr]overlay={x}:{y},ass={sub}:fontsdir=fonts,format=yuv420p[v]")
        inputs = ["-i", SRC, "-loop", "1", "-framerate", "30000/1001", "-i", str(LAY / f"bg-{fmt}.png"),
                  "-loop", "1", "-framerate", "30000/1001", "-i", str(WORK / f"mask-{fmt}.png"),
                  "-loop", "1", "-framerate", "30000/1001", "-i", str(WORK / "glow.png")]
    else:
        fc = f"{clean};[1:v]format=rgba[hud];[v0][hud]overlay=0:0,ass={sub}:fontsdir=fonts,format=yuv420p[v]"
        inputs = ["-i", SRC, "-loop", "1", "-framerate", "30000/1001", "-i", str(LAY / "hud-169.png")]
    main = WORK / f"main-{fmt}.mp4"
    ff([*inputs, "-filter_complex", fc, "-map", "[v]", "-an", "-t", f"{main_len:.3f}", *X264, str(main)], cwd=str(WORK))

    # 2) outro: layers enter one after another (fade + ease-out rise)
    def enter(start, dur, rise):
        p = f"clip((t-{start})/{dur}\\,0\\,1)"
        return f"'{rise}*pow(1-{p}\\,3)'"
    layers = [("o_logo", 0.15, 0.45, -26), ("o_product", 0.30, 0.70, 90), ("o_title", 0.60, 0.50, 36),
              ("o_chips", 0.85, 0.45, 30), ("o_cta", 1.10, 0.45, 30)]
    ins = ["-loop", "1", "-framerate", "30000/1001", "-t", str(OUTRO), "-i", str(LAY / f"o_bg-{fmt}.png"),
           "-loop", "1", "-framerate", "30000/1001", "-t", str(OUTRO), "-i", str(WORK / "glow.png")]
    for name, *_ in layers:
        ins += ["-loop", "1", "-framerate", "30000/1001", "-t", str(OUTRO), "-i", str(LAY / f"{name}-{fmt}.png")]
    gy = H // 2 - 450 - (60 if fmt != "169" else 0)
    gx = (W // 2 - 450) if fmt != "169" else 90
    chain = [f"[0:v]format=rgba[s0]", f"[1:v]format=rgba[gl]",
             f"[s0][gl]overlay=x='{gx}+60*sin(t/1.7)':y='{gy}'[s1]"]
    cur = "s1"
    for i, (name, st, d, rise) in enumerate(layers):
        chain.append(f"[{i + 2}:v]format=rgba,fade=t=in:st={st}:d={d * 0.8:.2f}:alpha=1[l{i}]")
        chain.append(f"[{cur}][l{i}]overlay=x=0:y={enter(st, d, rise)}[s{i + 2}]")
        cur = f"s{i + 2}"
    chain.append(f"[{cur}]format=yuv420p[o]")
    outro = WORK / f"outro-{fmt}.mp4"
    ff([*ins, "-filter_complex", ";".join(chain), "-map", "[o]", "-t", str(OUTRO), *X264, str(outro)])

    # 3) main → outro crossfade, with the edit's own audio (his last words play out; a 0.15 s tail fade at 50.0 s)
    afc = (f"[0:v]fps=30000/1001,settb=AVTB[m];[1:v]fps=30000/1001,settb=AVTB[o];"
           f"[m][o]xfade=transition=fade:duration={XF}:offset={XF_AT}[v];"
           f"[2:a]afade=t=out:st=50.0:d=0.15,apad,atrim=0:{TOTAL:.3f},loudnorm=I=-14:TP=-1.5:LRA=11,aresample=48000[a]")
    out = OUT / f"u002-gadget-insider-ad-{fmt.replace('45', '4x5').replace('916', '9x16').replace('169', '16x9')}.mp4"
    ff(["-i", str(main), "-i", str(outro), "-i", SRC, "-filter_complex", afc, "-map", "[v]", "-map", "[a]",
        *X264, "-c:a", "aac", "-b:a", "192k", "-movflags", "+faststart", "-t", f"{TOTAL:.3f}", str(out)])
    print(out)


for f in (sys.argv[1:] or ["45", "916", "169"]):
    build(f)
