"""Transcribe the Gadget Insider Bangla review (Bangla) to a timestamped text file.

usage: python transcribe.py
Writes transcript.txt next to this script. Takes roughly 10-20 minutes on CPU.
"""
import pathlib, subprocess, time
import numpy as np
from faster_whisper import WhisperModel

HERE = pathlib.Path(__file__).resolve().parent
SRC = HERE.parent.parent / "খুলে-ফেললাম-সিনেমা-প্রজেক্টর-AUN-U002-Pro-Cinema-Projector-Teardown_1080p.mp4"
MODEL = pathlib.Path.home() / ".cache" / "whisper-models" / "faster-whisper-large-v3-turbo"
OUT = HERE / "transcript.txt"
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"

t0 = time.time()
# Decode with ffmpeg ourselves: the installed PyAV is too new for faster-whisper's own decoder
# ("open() got an unexpected keyword argument 'metadata_errors'").
pcm = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-i", str(SRC), "-vn", "-ac", "1", "-ar", "16000",
                      "-f", "f32le", "-"], capture_output=True, check=True).stdout
audio = np.frombuffer(pcm, dtype=np.float32)
print(f"audio: {len(audio) / 16000:.0f} s", flush=True)
model = WhisperModel(str(MODEL), device="cpu", compute_type="int8", cpu_threads=8)
segs, info = model.transcribe(audio, language="bn", beam_size=5, vad_filter=True,
                              vad_parameters={"min_silence_duration_ms": 400},
                              condition_on_previous_text=False)
with open(OUT, "w", encoding="utf-8") as f:
    for s in segs:
        line = f"[{int(s.start // 60)}:{s.start % 60:05.2f} -> {int(s.end // 60)}:{s.end % 60:05.2f}] {s.text.strip()}"
        f.write(line + "\n")
        f.flush()
        print(line[:16], flush=True)
print(f"done in {round(time.time() - t0)} s -> {OUT}")
