"""Original soundtrack for the AUN 5% offer animation, synthesised note by note and locked to the animation's cues,
plus the Gemini voice-over placed on the beat grid and the music ducked under it.

usage: python music.py            -> soundtrack.wav (48 kHz stereo) + stems/ for checking
Everything here is generated from scratch (no samples, no loops), so the track is owned outright.
"""
import pathlib, subprocess
import numpy as np

HERE = pathlib.Path(__file__).resolve().parent
FF = r"C:\Users\Jobayer Hossain\AppData\Local\Programs\Python\Python313\Lib\site-packages\imageio_ffmpeg\binaries\ffmpeg-win-x86_64-v7.1.exe"
SR, DUR, BPM = 48000, 18.0, 120
BEAT = 60 / BPM
N = int(SR * DUR)
rng = np.random.default_rng(5)

# ---------------------------------------------------------------- cues (seconds) — must match anim.html
BEAM_ON = 1.5           # beam flickers on
DROP = 4.0              # the ৫ lands, on "পাঁচ"
CHHAR = 4.7             # "ছাড়" pops
SHINE = 4.2             # shine sweeps the ৫%
WHOOSH_B, DATES = 5.85, 6.95     # to the dates; dates pop on "তিন"
COUPON = 9.45           # coupon pill on "কুপন"
WHOOSH_C = 11.45        # to the line-up
CARDS = [11.7 + i * 0.25 + 0.05 for i in range(6)]
SCENE_D = 14.0          # the ending
BUTTON = 15.45          # button on "আজই"
FINAL = 16.0
# voice lines: (start in the Gemini file, end in the file, where it goes in the ad)
VOICE = [(0.275, 3.516, 1.85), (4.046, 6.528, 6.05), (7.378, 9.608, 9.0), (10.084, 11.955, 11.75), (12.569, 14.785, 14.27)]

# progression, one chord per bar (2 s): intro Am G | drop F G Am C F G | resolve C
NOTE = lambda n: 440.0 * 2 ** ((n - 69) / 12)        # MIDI → Hz
CHORDS = [[57, 60, 64, 69], [55, 59, 62, 67], [53, 57, 60, 65], [55, 59, 62, 67], [57, 60, 64, 69],
          [48, 55, 60, 64], [53, 57, 60, 65], [55, 59, 62, 67], [48, 55, 60, 64]]
ROOTS = [45, 43, 41, 43, 45, 36, 41, 43, 36]


# ---------------------------------------------------------------- helpers
def tvec(d):
    return np.arange(int(d * SR)) / SR


def place(bus, sig, t, gain=1.0, pan=0.0):
    """Add mono (n,) or stereo (2,n) signal at time t; equal-power pan for mono."""
    i = int(round(t * SR))
    if i >= N:
        return
    if sig.ndim == 1:
        l, r = np.cos((pan + 1) * np.pi / 4), np.sin((pan + 1) * np.pi / 4)
        sig = np.vstack([sig * l * 1.414, sig * r * 1.414])
    j = min(N, i + sig.shape[1])
    if i < 0:
        sig, i = sig[:, -i:], 0
    bus[:, i:j] += gain * sig[:, : j - i]


def fft_filter(x, lo=None, hi=None, order=2):
    """Zero-phase Butterworth-shaped high/low-pass in the frequency domain."""
    n = x.shape[-1]
    X = np.fft.rfft(x, axis=-1)
    f = np.fft.rfftfreq(n, 1 / SR) + 1e-9
    H = np.ones_like(f)
    if hi:
        H /= np.sqrt(1 + (f / hi) ** (2 * order))
    if lo:
        H /= np.sqrt(1 + (lo / f) ** (2 * order))
    return np.fft.irfft(X * H, n, axis=-1)


def sweep_band(x, fc_curve, width=0.6):
    """Time-varying band-pass via STFT: fc_curve(t) gives the centre frequency at time t (s)."""
    w, hop = 1024, 256
    win = np.hanning(w)
    out = np.zeros(len(x) + w)
    norm = np.zeros(len(x) + w)
    f = np.fft.rfftfreq(w, 1 / SR) + 1e-9
    for i in range(0, len(x) - w, hop):
        fc = fc_curve((i + w / 2) / SR)
        g = np.exp(-np.log(f / fc) ** 2 / (2 * width ** 2))
        out[i:i + w] += np.fft.irfft(np.fft.rfft(x[i:i + w] * win) * g, w) * win
        norm[i:i + w] += win ** 2
    return out[: len(x)] / np.maximum(norm[: len(x)], 1e-3)


def saw(f, d, kmax_hz=5000, detune=0.0, bright=1.0):
    t = tvec(d)
    s = np.zeros_like(t)
    for k in range(1, max(2, int(kmax_hz / f)) + 1):
        s += np.sin(2 * np.pi * k * f * (1 + detune) * t + k * 0.7) / k * (1 / (1 + (k * f / (1800 * bright)) ** 2))
    return s


def env_adsr(d, a=0.01, dcy=0.1, s=0.7, r=0.1):
    t = tvec(d)
    e = np.where(t < a, t / a, s + (1 - s) * np.exp(-(t - a) / max(dcy, 1e-3)))
    rel = np.clip((d - t) / r, 0, 1)
    return e * rel


# ---------------------------------------------------------------- instruments
def kick(level=1.0):
    t = tvec(0.45)
    f = 46 + 120 * np.exp(-t / 0.028)
    ph = 2 * np.pi * np.cumsum(f) / SR
    k = np.sin(ph) * np.exp(-t / 0.26) * (1 - np.exp(-t / 0.0015))
    click = fft_filter(rng.standard_normal(len(t)), lo=1500) * np.exp(-t / 0.004) * 0.25
    return level * (k + click)


def clap():
    t = tvec(0.35)
    n = fft_filter(rng.standard_normal(len(t)), lo=900, hi=3200)
    e = np.zeros_like(t)
    for o in (0, 0.011, 0.022):
        e += np.where(t >= o, np.exp(-(t - o) / 0.008), 0)
    e += np.where(t >= 0.03, np.exp(-(t - 0.03) / 0.12), 0) * 0.6
    return n * e * 0.55


def hat(open_=False):
    d = 0.18 if open_ else 0.05
    t = tvec(d)
    return fft_filter(rng.standard_normal(len(t)), lo=7500) * np.exp(-t / (0.06 if open_ else 0.012)) * 0.35


def crash(d=2.2):
    t = tvec(d)
    x = fft_filter(rng.standard_normal((2, len(t))), lo=3800) * np.exp(-t / 0.7)
    return x * 0.45


def bell(midi, d=1.6, level=1.0):
    t = tvec(d)
    f = NOTE(midi)
    s = np.zeros_like(t)
    for ratio, amp, dec in ((1, 1, 1.1), (2.0, .5, .7), (2.76, .35, .45), (4.07, .2, .3), (5.4, .12, .22)):
        s += amp * np.sin(2 * np.pi * f * ratio * t) * np.exp(-t / dec)
    return level * s * (1 - np.exp(-t / 0.002)) * 0.35


def pluck(midi, d=0.35, level=1.0):
    t = tvec(d)
    f = NOTE(midi)
    s = np.zeros_like(t)
    for k in range(1, 12):
        if k * f > 9000:
            break
        s += np.sin(2 * np.pi * k * f * t) / k * np.exp(-t * (7 + 5 * k))
    return level * s * (1 - np.exp(-t / 0.001)) * 0.5


def pad(chord, d, level=1.0, bright=1.0):
    out = np.zeros((2, int(d * SR)))
    for m in chord:
        for det, ch in ((-0.004, 0), (0.0, None), (0.005, 1)):
            s = saw(NOTE(m), d, kmax_hz=4500, detune=det, bright=bright)
            if ch is None:
                out += s * 0.5
            else:
                out[ch] += s
    e = env_adsr(d, a=0.35, dcy=0.8, s=0.85, r=0.45)
    return level * out * e / (len(chord) * 2.2)


def bass(midi, d):
    f = NOTE(midi)
    t = tvec(d)
    s = saw(f, d, kmax_hz=900) * 0.7 + np.sin(2 * np.pi * f * t) * 0.8
    return s * env_adsr(d, a=0.004, dcy=0.08, s=0.75, r=0.03) * 0.55


def impact(level=1.0):
    t = tvec(2.0)
    f = 52 * np.exp(-t / 1.2) + 30
    boom = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / 0.55)
    burst = fft_filter(rng.standard_normal(len(t)), hi=2500) * np.exp(-t / 0.09) * 0.5
    return level * (boom + burst)


def riser(d, f0=350, f1=7000, level=1.0):
    t = tvec(d)
    noise = rng.standard_normal(len(t))
    band = sweep_band(noise, lambda x: f0 * (f1 / f0) ** min(x / d, 1), width=0.45)
    tone = np.sin(2 * np.pi * np.cumsum(220 * (4 ** (t / d))) / SR) * 0.12
    amp = (t / d) ** 2.2
    return level * (band * 0.8 + tone) * amp


def whoosh(d=0.55, peak=0.6):
    t = tvec(d)
    noise = rng.standard_normal(len(t))
    fc = lambda x: 700 + 2600 * np.exp(-((x / d - peak) ** 2) / 0.05)
    s = sweep_band(noise, fc, width=0.5)
    amp = np.exp(-((t / d - peak) ** 2) / 0.04)
    pan = np.linspace(-0.8, 0.8, len(t))
    return np.vstack([s * amp * np.cos((pan + 1) * np.pi / 4), s * amp * np.sin((pan + 1) * np.pi / 4)]) * 1.4


def zap():
    """Electric buzz that follows the beam's flicker keyframes (0, .75, .15, .9, .5, 1 over 0.55 s)."""
    d = 1.0
    t = tvec(d)
    buzz = np.zeros_like(t)
    for k in range(1, 40, 2):
        buzz += np.sin(2 * np.pi * 75 * k * t) / k
    crackle = fft_filter(rng.standard_normal(len(t)), lo=2000) * (rng.random(len(t)) > 0.985) * 3
    keys = [(0, 0), (0.099, .75), (0.165, .15), (0.253, .9), (0.33, .5), (0.55, 1.0), (0.62, 0.55), (1.0, 0)]
    amp = np.interp(t, [k[0] for k in keys], [k[1] for k in keys])
    s = fft_filter((buzz * 0.5 + crackle) * amp, lo=120, hi=6000)
    return s * 0.35


def reverb(x, rt=1.7, mix=0.25):
    n = int(rt * SR)
    t = np.arange(n) / SR
    ir = rng.standard_normal((2, n)) * np.exp(-t * 6.9 / rt)
    ir = fft_filter(ir, hi=5500)
    ir[:, : int(0.012 * SR)] = 0
    ir /= np.sqrt((ir ** 2).sum(axis=1, keepdims=True))
    L = x.shape[1] + n
    out = np.vstack([np.fft.irfft(np.fft.rfft(x[c], L) * np.fft.rfft(ir[c], L), L)[: x.shape[1]] for c in range(2)])
    return out * mix


# ---------------------------------------------------------------- arrangement
drums, music, fx, plucks = (np.zeros((2, N)) for _ in range(4))
bars = int(DUR / (4 * BEAT)) + 1

# pads: every bar; intro darker, final chord rings out
for b, ch in enumerate(CHORDS):
    t0 = b * 4 * BEAT
    if t0 >= DUR:
        break
    d = 4 * BEAT + 0.5 if b < 8 else DUR - t0
    lvl = 0.55 if b < 2 else (0.75 if b < 8 else 0.95)
    place(music, pad(ch, d, level=lvl, bright=0.6 if b < 2 else 1.0), t0)

# intro: soft high shimmer before the beam
for i, m in enumerate([81, 84, 88, 86, 84, 81]):
    place(plucks, bell(m, 1.4, 0.18), 0.25 + i * 0.25, pan=-0.5 + i * 0.2)
place(fx, zap(), BEAM_ON)
# build: filtered kick on the beat, a snare roll and hats getting busier, riser + reverse cymbal into the drop
for i in range(4):
    place(drums, fft_filter(kick(0.6), hi=400), 2.0 + i * BEAT)
for i in range(16):
    t = 2.0 + i * BEAT / 4
    place(drums, hat(), t, gain=0.25 + 0.5 * i / 16, pan=0.3 if i % 2 else -0.3)
roll = [3.0 + i * BEAT / 4 for i in range(4)] + [3.5 + i * BEAT / 8 for i in range(8)]
for i, t in enumerate(roll):
    place(drums, clap(), t, gain=0.15 + 0.6 * i / len(roll))
place(fx, riser(DROP - 1.6), 1.6, gain=0.55)
rev_cym = crash(1.0)[:, ::-1] * np.linspace(0, 1, int(1.0 * SR)) ** 2
place(fx, rev_cym, DROP - 1.0, gain=0.8)

# groove: drop to the ending (bars 3–8), with a fill into the ending scene
for b in range(2, 8):
    t0 = b * 4 * BEAT
    for beat in range(4):
        t = t0 + beat * BEAT
        place(drums, kick(), t)
        if beat in (1, 3):
            place(drums, clap(), t, gain=0.8)
        place(drums, hat(open_=True), t + BEAT / 2, gain=0.5, pan=0.25)
        for s16 in (1, 3):
            place(drums, hat(), t + s16 * BEAT / 4, gain=0.3, pan=-0.25)
        # bass on the off-beat eighths (pumps against the kick)
        place(music, bass(ROOTS[b] + 12 * (ROOTS[b] < 40), BEAT / 2 * 0.9), t + BEAT / 2)
    # pluck arpeggio of the chord, 16ths, skipped in bar 7 where the cards play their own notes
    if b != 6:
        tones = [n + 12 for n in CHORDS[b]]
        pattern = [0, 1, 2, 3, 2, 1, 2, 3]
        for i in range(16):
            place(plucks, pluck(tones[pattern[i % 8]], level=0.45), t0 + i * BEAT / 4, pan=-0.4 if i % 2 else 0.4)
for i in range(4):
    place(drums, clap(), 13.5 + i * BEAT / 4, gain=0.3 + 0.15 * i)
place(fx, riser(1.0, 600, 6000), 13.0, gain=0.35)

# hits on the animation's cues
place(fx, impact(1.0), DROP)
place(drums, crash(), DROP, gain=0.8)
for i, m in enumerate([88, 91, 93, 96, 100]):
    place(plucks, bell(m, 1.0, 0.35), SHINE + i * 0.09, pan=-0.6 + 0.3 * i)
place(plucks, bell(84, 1.2, 0.45), CHHAR)
place(fx, whoosh(), WHOOSH_B - 0.33, gain=0.55)
place(music, pad(CHORDS[3], 0.6, level=0.9), DATES)            # stab as the dates pop
place(fx, impact(0.35), DATES)
place(plucks, bell(88, 1.4, 0.7), COUPON)
place(plucks, bell(93, 1.4, 0.35), COUPON + 0.12)
place(fx, whoosh(), WHOOSH_C - 0.33, gain=0.55)
for i, (t, m) in enumerate(zip(CARDS, [81, 84, 86, 88, 91, 93])):      # one note per card, rising
    place(plucks, pluck(m, 0.5, 0.9), t, pan=-0.6 + 0.24 * i)
    place(plucks, bell(m + 12, 0.8, 0.25), t, pan=-0.6 + 0.24 * i)
place(fx, impact(0.9), SCENE_D)
place(drums, crash(), SCENE_D, gain=0.7)
place(plucks, bell(96, 1.6, 0.6), BUTTON)
place(plucks, bell(100, 1.6, 0.4), BUTTON + 0.1)
# final: big chord, last kick + crash, then just the ring-out
place(drums, kick(1.1), FINAL)
place(drums, crash(2.0), FINAL, gain=0.9)
place(fx, impact(0.7), FINAL)
for i, m in enumerate([84, 88, 91, 96]):
    place(plucks, bell(m, 2.0, 0.3), FINAL + 0.5 + i * 0.12, pan=-0.45 + 0.3 * i)

# delay on the plucks (dotted-eighth ping-pong), reverb on everything melodic + fx
dly = int(0.75 * BEAT * SR)
echo = np.zeros_like(plucks)
for k in range(1, 4):
    sh = np.roll(plucks, dly * k, axis=1) * (0.33 ** k)
    sh[:, : dly * k] = 0
    echo[k % 2] += sh[k % 2]
plucks = plucks + echo
wet = reverb(music * 0.6 + plucks + fx * 0.8 + drums * 0.15)

# bus gains (set by measurement, see the check printout)
bed = drums * 0.62 + music * 0.85 + plucks * 0.6 + fx * 0.75 + wet * 0.7  # whooshes are scaled at placement
bed = fft_filter(bed, lo=30)

# ---------------------------------------------------------------- voice
raw = subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-i", str(HERE / "voice-raw.wav"), "-af",
                      "aresample=48000:filter_size=64:phase_shift=10:cutoff=0.97,highpass=f=75,equalizer=f=230:t=q:w=1.2:g=-1.5,"
                      "equalizer=f=3600:t=q:w=1.0:g=2.5,acompressor=threshold=-22dB:ratio=3:attack=6:release=90:makeup=3",
                      "-ac", "1", "-f", "f32le", "-"], capture_output=True, check=True).stdout
vsrc = np.frombuffer(raw, np.float32).astype(np.float64)
voice = np.zeros(N)
for a, b, at in VOICE:
    s0, s1 = int((a - 0.04) * SR), int((b + 0.12) * SR)
    seg = vsrc[max(s0, 0):s1].copy()
    f = int(0.01 * SR)
    seg[:f] *= np.linspace(0, 1, f)
    seg[-f * 4:] *= np.linspace(1, 0, f * 4)
    i = int((at - 0.04) * SR)
    voice[i:i + len(seg)] += seg[: N - i]

# duck the music under the voice: smoothed voice level → up to ~7 dB less music
hop = int(0.01 * SR)
lvl = np.sqrt(np.convolve(voice ** 2, np.ones(hop) / hop, "same"))
act = np.clip(lvl / (np.percentile(lvl[lvl > 1e-4], 50) + 1e-9), 0, 1)
g = np.zeros_like(act)
att, rel = np.exp(-1 / (0.03 * SR)), np.exp(-1 / (0.30 * SR))
# one-pole smoothing, vectorised in blocks (attack fast, release slow)
blk = np.maximum.reduceat(act, np.arange(0, N, hop))
sm = np.zeros_like(blk)
for i in range(1, len(blk)):
    c = 0.5 if blk[i] > sm[i - 1] else 0.035
    sm[i] = sm[i - 1] + c * (blk[i] - sm[i - 1])
duck = 1 - 0.56 * np.repeat(sm, hop)[:N]

# levels: voice ~10 dB above the ducked bed
def rms(x):
    return np.sqrt(np.mean(x ** 2) + 1e-12)
vmask = np.repeat(sm, hop)[:N] > 0.5
voice_rms = rms(voice[vmask])
bed_mono = bed.mean(axis=0)
bed_rms_under = rms((bed_mono * duck)[vmask])
target = voice_rms / (10 ** (10 / 20))
bed *= target / bed_rms_under
mix = bed * duck + np.vstack([voice, voice]) * 1.0
fade = np.clip((DUR - np.arange(N) / SR) / 0.9, 0, 1)
mix *= fade
mix /= np.abs(mix).max() * 1.12

out_dir = HERE / "stems"
out_dir.mkdir(exist_ok=True)
def write(path, x):
    x = np.atleast_2d(x).astype(np.float32)
    subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", "-f", "f32le", "-ar", str(SR), "-ac", str(x.shape[0]),
                    "-i", "-", str(path)], input=x.T.copy().tobytes(), check=True)
write(HERE / "mix-raw.wav", mix)
write(out_dir / "music-only.wav", (bed * duck) / np.abs(mix).max())
write(out_dir / "voice-placed.wav", voice / np.abs(voice).max() * 0.9)

# loudness to -14 LUFS / -1 dBTP for social platforms
subprocess.run([FF, "-hide_banner", "-loglevel", "error", "-y", "-i", str(HERE / "mix-raw.wav"), "-af",
                "loudnorm=I=-14:TP=-1.2:LRA=9,aresample=48000", "-c:a", "pcm_s16le", str(HERE / "soundtrack.wav")], check=True)
print("voice/bed ratio under voice (dB):", round(20 * np.log10(voice_rms / rms((bed.mean(axis=0) * duck)[vmask])), 1))
