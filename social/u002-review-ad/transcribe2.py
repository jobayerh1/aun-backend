"""Second, targeted transcription pass over the parts of the review the ad may use.

usage: python transcribe2.py
Writes transcript2.txt next to this script (a few minutes on CPU).

The first pass garbled long stretches: background music + 30-second chunks made the model lose track.
Here each window is cut at the speaker's own pauses into short pieces (about 3-10 s), and the model is
primed with the product vocabulary, which is what keeps Bangla spelling and English terms on track.
"""
import pathlib, subprocess, time
import numpy as np
from faster_whisper import WhisperModel

HERE = pathlib.Path(__file__).resolve().parent
SRC = HERE.parent.parent / "খুলে-ফেললাম-সিনেমা-প্রজেক্টর-AUN-U002-Pro-Cinema-Projector-Teardown_1080p.mp4"
MODEL = pathlib.Path.home() / ".cache" / "whisper-models" / "faster-whisper-large-v3-turbo"
OUT = HERE / "transcript2.txt"
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SR = 16000

# (start, end) in seconds
WINDOWS = [(0, 34), (64, 112), (111, 142), (232, 280), (310, 332), (527, 590), (617, 655), (670, 709)]
PROMPT = ("AUN U002 Pro প্রজেক্টর রিভিউ। অপটিক্যাল ইঞ্জিন, ডাস্ট সিল, অটোফোকাস, কি-স্টোন কারেকশন, "
          "নেটিভ 1080p রেজোলিউশন, 4K, Android 13, 2GB RAM, 32GB স্টোরেজ, ওয়ারেন্টি, AUN Care অ্যাপ, "
          "সেন্সর, লেন্স, মোটর, LCD, LED, হোম থিয়েটার, সিনেমা হল।")

pcm = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-i", str(SRC), "-vn", "-ac", "1", "-ar", str(SR),
                      "-f", "f32le", "-"], capture_output=True, check=True).stdout
audio = np.frombuffer(pcm, dtype=np.float32)


def pieces(s, e):
    """Split [s, e) at pauses: 20 ms frames clearly quieter than the speech around them."""
    seg = audio[int(s * SR):int(e * SR)]
    hop = SR // 50
    n = len(seg) // hop
    db = 20 * np.log10(np.sqrt((seg[:n * hop].reshape(n, hop) ** 2).mean(1)) + 1e-9)
    quiet = db < np.percentile(db, 60) - 12
    cuts, run = [], 0
    for i, q in enumerate(quiet):
        run = run + 1 if q else 0
        if run == 5:                       # 100 ms of quiet → a pause; cut in its middle
            cuts.append(s + (i - 2) * 0.02)
    out, start = [], s
    for c in cuts + [e]:
        if c - start >= 3.0 or c == e:     # merge tiny bits, keep pieces short
            if out and c - start < 1.0:
                out[-1] = (out[-1][0], c)
            else:
                out.append((start, c))
            start = c
    final = []
    for a, b in out:                       # hard cap ~10 s
        while b - a > 10.5:
            final.append((a, a + 10)); a += 10
        final.append((a, b))
    return final


t0 = time.time()
model = WhisperModel(str(MODEL), device="cpu", compute_type="int8", cpu_threads=8)
with open(OUT, "w", encoding="utf-8") as f:
    for ws, we in WINDOWS:
        f.write(f"\n=== window {int(ws // 60)}:{ws % 60:04.1f} - {int(we // 60)}:{we % 60:04.1f} ===\n")
        for a, b in pieces(ws, we):
            clip = audio[int(a * SR):int(b * SR)]
            segs, _ = model.transcribe(clip, language="bn", beam_size=5, vad_filter=False,
                                       initial_prompt=PROMPT, condition_on_previous_text=False,
                                       word_timestamps=True, no_repeat_ngram_size=3, repetition_penalty=1.1)
            for sg in segs:
                words = " ".join(f"{w.word.strip()}@{a + w.start:.2f}" for w in (sg.words or []))
                line = (f"[{int((a + sg.start) // 60)}:{(a + sg.start) % 60:05.2f} -> "
                        f"{int((a + sg.end) // 60)}:{(a + sg.end) % 60:05.2f}] {sg.text.strip()}")
                f.write(line + "\n    words: " + words + "\n")
                f.flush()
                print(line[:60], flush=True)
print(f"done in {round(time.time() - t0)} s -> {OUT}")
