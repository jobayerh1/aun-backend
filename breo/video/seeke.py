"""See KE video -> web files (the source is 540x960; kept at its native size)."""
import os, subprocess
import imageio_ffmpeg
from PIL import Image

FF = imageio_ffmpeg.get_ffmpeg_exe()
HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', 'Product Description & Main Images', 'See KE',
                   'breo_seeke_eye_massager_with_hot_compress_stimulat', 'product_videos', '1.mp4')
OUT = os.path.join(HERE, 'web', 'see-ke')
os.makedirs(OUT, exist_ok=True)

def run(args):
    r = subprocess.run([FF, '-hide_banner', '-loglevel', 'error', '-y'] + args, capture_output=True, text=True, errors='ignore')
    if r.returncode:
        print(r.stderr[-300:])

main = os.path.join(OUT, 'N910200212-reel.mp4')
run(['-i', SRC, '-map', '0:v:0', '-map', '0:a:0?', '-c:v', 'libx264', '-preset', 'slow', '-crf', '23',
     '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', main])
# silent loop without the yellow title card of the first ~3 seconds
loop = os.path.join(OUT, 'N910200212-reel-loop.mp4')
run(['-ss', '3.4', '-to', '14.8', '-i', SRC, '-map', '0:v:0', '-an', '-c:v', 'libx264', '-preset', 'slow', '-crf', '26',
     '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-movflags', '+faststart', loop])
# poster: the woman holding the mask with its inside towards the camera
png = os.path.join(OUT, 'poster.png')
run(['-ss', '6.0', '-i', SRC, '-frames:v', '1', png])
Image.open(png).convert('RGB').save(os.path.join(OUT, 'N910200212-reel-poster.webp'), 'WEBP', quality=82, method=6)
os.remove(png)
for f in sorted(os.listdir(OUT)):
    print('%-32s %6.0f KB' % (f, os.path.getsize(os.path.join(OUT, f)) / 1024))
