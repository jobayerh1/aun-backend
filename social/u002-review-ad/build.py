"""Cut a Facebook ad from the Gadget Insider Bangla U002 Pro review.

usage: python build.py <edl.json> [workdir]

The EDL has:
  voice   – audio segments taken from the review, played back to back (the spine of the ad)
  shots   – video clips laid over that audio, in order; their durations must add up to the voice length
  subs    – captions, timed on the ad's own clock
  hook    – headline text shown at the top for the first seconds
  end     – end-card length in seconds

Every shot is first rendered to a 1080x1350 (4:5) master. The 4:5 feed version is that master plus
captions; the 9:16 Reels version puts the same master on a blurred backdrop, so nothing is cropped twice.
"""
import json, pathlib, shutil, subprocess, sys

HERE = pathlib.Path(__file__).resolve().parent
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SRC = str(HERE.parent.parent / "খুলে-ফেললাম-সিনেমা-প্রজেক্টর-AUN-U002-Pro-Cinema-Projector-Teardown_1080p.mp4")
FPS = 30000 / 1001
X264 = ["-c:v", "libx264", "-preset", "medium", "-crf", "16", "-pix_fmt", "yuv420p"]

edl = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))
work = pathlib.Path(sys.argv[2] if len(sys.argv) > 2 else HERE / "_work")
work.mkdir(parents=True, exist_ok=True)
# libass gets its font from here (Nirmala UI shapes Bangla conjuncts correctly)
(work / "fonts").mkdir(exist_ok=True)
if not (work / "fonts" / "Nirmala.ttc").exists():
    shutil.copy(r"C:\Windows\Fonts\Nirmala.ttc", work / "fonts" / "Nirmala.ttc")
out_dir = HERE / "output"
out_dir.mkdir(exist_ok=True)
name = edl.get("name", "u002-review-ad")


def ff(args, cwd=None):
    r = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", *args], cwd=cwd, capture_output=True, text=True)
    if r.returncode:
        sys.exit("ffmpeg failed:\n" + r.stderr[-3000:])


def frames(sec):
    return max(1, round(sec * FPS))


# ── 1. shots → 4:5 master (video only) ──────────────────────────────────────
parts = []
for i, s in enumerate(edl["shots"]):
    n = frames(s["dur"])
    z = s.get("zoom", 1.0)
    if s.get("mode", "crop") == "crop":
        # a 4:5 window out of the 1920x1080 frame; zoom > 1 tightens it
        h = 1080 / z
        w = h * 0.8
        x = min(max(s.get("cx", 0.5) * 1920 - w / 2, 0), 1920 - w)
        y = min(max(s.get("cy", 0.5) * 1080 - h / 2, 0), 1080 - h)
        vf = f"crop={w:.0f}:{h:.0f}:{x:.0f}:{y:.0f},scale=1080:1350:flags=lanczos,setsar=1"
    else:
        # whole 16:9 picture (optionally a little tighter) over a blurred fill
        fw = round(1080 * z / 2) * 2
        fh = round(fw * 9 / 16 / 2) * 2
        vf = (f"split[a][b];[a]scale=2400:1350,crop=1080:1350,gblur=sigma=40,eq=brightness=-0.18[bg];"
              f"[b]scale={fw}:{fh}:flags=lanczos,crop=min(iw\\,1080):ih[fg];"
              f"[bg][fg]overlay=(W-w)/2:(H-h)/2+{s.get('dy', 0)},setsar=1")
    if s.get("speed"):
        vf = f"setpts=PTS/{s['speed']}," + vf
    p = work / f"shot_{i:02d}.mp4"
    ff(["-ss", f"{s['src']:.3f}", "-i", SRC, "-an", "-filter_complex", vf, "-r", "30000/1001",
        "-frames:v", str(n), *X264, str(p)])
    parts.append(p)
(work / "shots.txt").write_text("".join(f"file '{p.as_posix()}'\n" for p in parts), encoding="utf-8")
master = work / "master_4x5.mp4"
ff(["-f", "concat", "-safe", "0", "-i", str(work / "shots.txt"), "-c", "copy", str(master)])
main_len = sum(frames(s["dur"]) for s in edl["shots"]) / FPS

# ── 2. voice track ──────────────────────────────────────────────────────────
fade = 0.06
chains, labels = [], []
for i, v in enumerate(edl["voice"]):
    d = v["end"] - v["start"]
    chains.append(f"[0:a]atrim={v['start']}:{v['end']},asetpts=PTS-STARTPTS,"
                  f"afade=t=in:d={fade},afade=t=out:st={d - fade:.3f}:d={fade}"
                  + (f",volume={v['gain']}" if v.get("gain") else "") + f"[v{i}]")
    labels.append(f"[v{i}]")
    if v.get("gap"):
        chains.append(f"anullsrc=r=44100:cl=stereo,atrim=0:{v['gap']}[g{i}]")
        labels.append(f"[g{i}]")
total = main_len + edl["end"]
fc = ";".join(chains) + f";{''.join(labels)}concat=n={len(labels)}:v=0:a=1,apad,atrim=0:{total:.3f}," \
     f"afade=t=out:st={total - 0.8:.3f}:d=0.8,loudnorm=I=-14:TP=-1.5:LRA=11,aresample=48000[out]"
voice = work / "voice.wav"
ff(["-i", SRC, "-filter_complex", fc, "-map", "[out]", str(voice)])


# ── 3. captions (ASS, one file per format) ──────────────────────────────────
def ts(t):
    t = max(t, 0)
    return f"{int(t // 3600)}:{int(t % 3600 // 60):02d}:{t % 60:05.2f}"


def ass(fmt):
    H = 1920 if fmt == "9x16" else 1350
    # Reels: keep text out of the top ~14% and bottom ~35% that the app's own UI covers
    cap_mv = 700 if fmt == "9x16" else 95
    hook_y = 380 if fmt == "9x16" else 95
    credit_y = 305 if fmt == "9x16" else 22
    head = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: {H}
WrapStyle: 0
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Cap,Nirmala UI,62,&H00FFFFFF,&H00FFFFFF,&H00000000,&H96000000,1,0,0,0,100,100,0,0,1,5,3,2,60,60,{cap_mv},1
Style: Hook,Nirmala UI,66,&H00FFFFFF,&H00FFFFFF,&H00FE8801,&H00000000,1,0,0,0,100,100,0,0,3,18,0,8,70,70,{hook_y},1
Style: Credit,Nirmala UI,30,&H00FFFFFF,&H00FFFFFF,&H64000000,&H00000000,1,0,0,0,100,100,0,0,3,10,0,7,40,40,{credit_y},1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    ev = []
    for h in edl.get("hook", []):
        fade_in = 0 if h["t0"] == 0 else 150  # the first frame is the autoplay thumbnail: no half-faded headline
        ev.append(f"Dialogue: 2,{ts(h['t0'])},{ts(h['t1'])},Hook,,0,0,0,,{{\\fad({fade_in},150)}}{h['text']}")
    if edl.get("credit"):
        ev.append(f"Dialogue: 1,{ts(0)},{ts(main_len)},Credit,,0,0,0,,{edl['credit']}")
    for c in edl["subs"]:
        # *word* → yellow highlight
        txt = c["text"]
        while "*" in txt:
            txt = txt.replace("*", "{\\c&H4DD3FF&}", 1).replace("*", "{\\c&HFFFFFF&}", 1)
        ev.append(f"Dialogue: 0,{ts(c['t0'])},{ts(c['t1'])},Cap,,0,0,0,,{{\\fad(60,60)}}{txt}")
    p = work / f"subs_{fmt}.ass"
    p.write_text(head + "\n".join(ev) + "\n", encoding="utf-8-sig")
    return p.name


# ── 4. compose each format ──────────────────────────────────────────────────
xf = 0.35  # crossfade into the end card
for fmt in ("9x16", "4x5"):
    H = 1920 if fmt == "9x16" else 1350
    card = HERE / "assets" / f"endcard-{fmt}.png"
    sub = ass(fmt)
    if fmt == "9x16":
        frame = ("[0:v]split[m][b];[b]scale=1536:1920,crop=1080:1920,gblur=sigma=45,eq=brightness=-0.3[bg];"
                 "[bg][m]overlay=0:285,format=yuv420p,fps=30000/1001[main]")
    else:
        frame = "[0:v]format=yuv420p,fps=30000/1001[main]"  # xfade needs both inputs on one timebase
    endn = frames(edl["end"] + xf)
    fc = (f"{frame};"
          f"[1:v]scale=1080:{H},zoompan=z='1+0.0004*on':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={endn}:s=1080x{H}:fps=30000/1001,"
          f"format=yuv420p[card];"
          f"[main][card]xfade=transition=fade:duration={xf}:offset={main_len - xf:.3f},ass={sub}:fontsdir=fonts[v]")
    out = out_dir / f"{name}-{fmt}.mp4"
    ff(["-i", str(master), "-loop", "1", "-i", str(card), "-i", str(voice), "-filter_complex", fc,
        "-map", "[v]", "-map", "2:a", *X264, "-r", "30000/1001", "-c:a", "aac", "-b:a", "160k",
        "-movflags", "+faststart", "-t", f"{total:.3f}", str(out)], cwd=str(work))  # -shortest overshoots on the looped card
    print(out)
