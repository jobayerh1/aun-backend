"""Web versions of the factory product videos.

For each source: a full film (1080 and 720), an optional short silent loop for
autoplay, and a WebP poster. Files are named <SKU>-<slug>... so breo-bd-store
matches them to their product automatically once uploaded.

Run from this folder:  python encode.py
Output: ./web/
"""
import os, subprocess, sys
import imageio_ffmpeg
from PIL import Image

FF = imageio_ffmpeg.get_ffmpeg_exe()
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'web')
os.makedirs(OUT, exist_ok=True)

JOBS = [
    # P2: a real vertical lifestyle film, 50 Mbps editing master
    dict(src='Breo-P2-subtitled.mp4', stem='N990000981-film', vertical=True,
         film=(0, None), loop=(17.8, 25.8), poster=17.8, sizes=(1080, 720)),
    # N6 mini: the horizontal brand film (the source is already 720p, so no 1080 version)
    dict(src='Breo N6 Mini _english_horizontal.mp4', stem='N990000631-film', vertical=False,
         film=(0, None), loop=(37.3, 46.8), poster=37.3, sizes=(720,)),
    # N6 mini: the same story cut for phones, used in the homepage reel
    dict(src='N6 Mini _English_vertical.m4v', stem='N990000631-reel', vertical=True,
         film=(0, None), loop=(5.0, 13.0), poster=5.3, sizes=(1080, 720)),
    # No.7: 19 s social cut; the last 4 s are a Chinese end card, so it ends at 15.3 s
    dict(src='7 Gun_English_vertical.m4v', stem='N990000360-reel', vertical=True,
         film=(0, 15.3), loop=None, poster=7.8, sizes=(1080, 720)),
]


def run(args):
    r = subprocess.run([FF, '-hide_banner', '-loglevel', 'error', '-y'] + args, capture_output=True, text=True, errors='ignore')
    if r.returncode:
        print('   ffmpeg error:', r.stderr[-400:])
    return r.returncode == 0


def scale(vertical, short):
    # "short" is the short side: 1080 -> 1080x1920 / 1920x1080
    return ('scale=%d:-2' % short) if vertical else ('scale=-2:%d' % short)


for j in [j for j in JOBS if len(sys.argv) < 2 or j['stem'] in sys.argv[1:]]:
    src = os.path.join(HERE, j['src'])
    if not os.path.exists(src):
        print('missing', j['src'])
        continue
    print('==', j['stem'], '<-', j['src'])
    start, end = j['film']
    trim = ['-ss', str(start)] + (['-to', str(end)] if end else [])
    fade = []
    if end:  # soft ending where we cut
        fade = ['-af', 'afade=t=out:st=%.2f:d=0.4' % (end - start - 0.4)]
    for short in j['sizes']:
        name = j['stem'] + ('' if short == j['sizes'][0] else '-%d' % short) + '.mp4'
        vf = scale(j['vertical'], short)
        if end:
            vf += ',fade=t=out:st=%.2f:d=0.4' % (end - start - 0.4)
        cap = '4M' if short >= 1080 else '2M'
        ok = run(trim + ['-i', src, '-map', '0:v:0', '-map', '0:a:0?', '-vf', vf,
                         '-c:v', 'libx264', '-preset', 'slow', '-crf', '24' if short >= 1080 else '25',
                         '-maxrate', cap, '-bufsize', str(int(cap[:-1]) * 2) + 'M',
                         '-pix_fmt', 'yuv420p', '-profile:v', 'high',
                         '-c:a', 'aac', '-b:a', '128k', '-ac', '2'] + fade +
                 ['-movflags', '+faststart', os.path.join(OUT, name)])
        if ok:
            print('   %-28s %6.1f MB' % (name, os.path.getsize(os.path.join(OUT, name)) / 1048576))
    if j['loop']:
        a, b = j['loop']
        name = j['stem'] + '-loop.mp4'
        short = 720
        ok = run(['-ss', str(a), '-to', str(b), '-i', src, '-map', '0:v:0', '-an',
                  '-vf', scale(j['vertical'], short) + ',fade=t=in:st=0:d=0.3,fade=t=out:st=%.2f:d=0.3' % (b - a - 0.3),
                  '-c:v', 'libx264', '-preset', 'slow', '-crf', '28', '-maxrate', '1500k', '-bufsize', '3M',
                  '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-movflags', '+faststart', os.path.join(OUT, name)])
        if ok:
            print('   %-28s %6.1f MB' % (name, os.path.getsize(os.path.join(OUT, name)) / 1048576))
    # poster
    tmp = os.path.join(OUT, j['stem'] + '-poster.png')
    if run(['-ss', str(j['poster']), '-i', src, '-map', '0:v:0', '-frames:v', '1', '-vf', scale(j['vertical'], 720), tmp]):
        webp = tmp[:-4] + '.webp'
        Image.open(tmp).convert('RGB').save(webp, 'WEBP', quality=80, method=6)
        os.remove(tmp)
        print('   %-28s %6.0f KB' % (os.path.basename(webp), os.path.getsize(webp) / 1024))
print('done')
