"""Make the owner's subtitle fill-in sheet + a check video with each phrase number on screen.
usage: python make_sheet.py"""
import json, pathlib, subprocess

HERE = pathlib.Path(__file__).resolve().parent
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SRC = str(HERE.parent.parent.parent / "u002 gadget insider ad.mp4")
WORK = pathlib.Path.home() / "AppData/Local/Temp/u002-ad-work"
slots = json.loads((HERE / "subtitle-slots.json").read_text(encoding="utf-8"))


def mmss(t):
    return f"{int(t // 60)}:{t % 60:04.1f}"


# 1) the sheet
lines = ["সাবটাইটেল শিট — U002 Gadget Insider বিজ্ঞাপন",
         "",
         "subtitle-check.mp4 চালান: প্রতিটি লাইনের নম্বর ভিডিওর উপরে বড় করে দেখাবে।",
         "প্রতিটি নম্বরে রিভিউয়ার ঠিক যা বলছেন, সেটা লাইনের শেষে লিখুন (বা ভুল থাকলে ঠিক করুন)।",
         "✓ = আমার মোটামুটি নিশ্চিত, শুধু একবার মিলিয়ে নিন।   ✎ = খালি বা অসম্পূর্ণ, এটা আপনাকে লিখতে হবে।",
         "পুরো লেখাটা কপি করে চ্যাটে পাঠিয়ে দিন — বাকিটা আমি করবো।",
         ""]
for s in slots:
    mark = "✓" if s["ok"] else "✎"
    lines.append(f"{s['n']:02d} {mark} [{mmss(s['t0'])}–{mmss(s['t1'])}] {s['text']}")
(HERE / "subtitle-sheet.txt").write_text("\n".join(lines) + "\n", encoding="utf-8-sig")

# 2) the check video: big number badge + current text (yellow when it still needs the owner)
ev = []
for s in slots:
    a, b = s["t0"], s["t1"]
    t = lambda x: f"0:{int(x // 60):02d}:{x % 60:05.2f}"
    col = "&H00FFFFFF" if s["ok"] else "&H004DD3FF"
    txt = s["text"] or "( খালি — কী বলছেন লিখুন )"
    ev.append(f"Dialogue: 0,{t(a)},{t(b)},Num,,0,0,0,,{s['n']:02d}")
    ev.append(f"Dialogue: 0,{t(a)},{t(b)},Txt,,0,0,0,,{{\\c{col}}}{txt}")
ass = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1280
PlayResY: 720
WrapStyle: 0

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Num,Segoe UI,110,&H00FFFFFF,&H00FFFFFF,&H00FE8801,&H00000000,1,0,0,0,100,100,0,0,3,22,0,7,30,30,30,1
Style: Txt,Nirmala UI,40,&H00FFFFFF,&H00FFFFFF,&H00000000,&H96000000,1,0,0,0,100,100,0,0,3,12,0,2,40,40,30,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
""" + "\n".join(ev) + "\n"
(WORK / "check.ass").write_text(ass, encoding="utf-8-sig")
subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", "-i", SRC, "-vf", "scale=1280:720,ass=check.ass:fontsdir=fonts",
                "-c:v", "libx264", "-preset", "veryfast", "-crf", "24", "-c:a", "aac", "-b:a", "128k", "-movflags", "+faststart",
                str(HERE / "subtitle-check.mp4")], cwd=str(WORK), check=True)
print("ok")
