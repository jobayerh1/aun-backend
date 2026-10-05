"""Silent autoplay loops, v2: no fades.

The first version faded in and out, so every loop blinked through black and a
paused loop showed a black frame. These cut straight, like any product loop.
Output: ./web/loops-v2/  (upload these; WordPress keeps the newest of a name)
"""
import sys
import os, subprocess
import imageio_ffmpeg

FF = imageio_ffmpeg.get_ffmpeg_exe()
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'web', 'loops-v2')
os.makedirs(OUT, exist_ok=True)

LOOPS = [
    ('Breo-P2-subtitled.mp4', 'N990000981-film-loop.mp4', 17.8, 25.8, True),
    ('Breo N6 Mini _english_horizontal.mp4', 'N990000631-film-loop.mp4', 37.3, 46.8, False),
    ('N6 Mini _English_vertical.m4v', 'N990000631-reel-loop.mp4', 5.0, 13.0, True),
    # No.7: the whole clip up to the Chinese end card, so the card never shows
    ('7 Gun_English_vertical.m4v', 'N990000360-reel-loop.mp4', 0.0, 15.2, True),
]
for src, name, a, b, vertical in [l for l in LOOPS if len(sys.argv) < 2 or l[1] in sys.argv[1:]]:
    scale = 'scale=720:-2' if vertical else 'scale=-2:720'
    r = subprocess.run([FF, '-hide_banner', '-loglevel', 'error', '-y', '-ss', str(a), '-to', str(b), '-i', os.path.join(HERE, src),
                        '-map', '0:v:0', '-an', '-vf', scale, '-c:v', 'libx264', '-preset', 'slow', '-crf', '27',
                        '-maxrate', '1500k', '-bufsize', '3M', '-pix_fmt', 'yuv420p', '-profile:v', 'high',
                        '-movflags', '+faststart', os.path.join(OUT, name)], capture_output=True, text=True, errors='ignore')
    print(name, 'OK %.2f MB' % (os.path.getsize(os.path.join(OUT, name)) / 1048576) if not r.returncode else r.stderr[-300:])
