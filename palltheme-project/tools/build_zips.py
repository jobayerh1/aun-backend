"""Builds dist/palltheme.zip and dist/palltheme-core.zip.

Refuses to package any third-party photo/video listed in the media
manifests (those licences do not allow redistribution in a theme).
"""
import json
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DIST = ROOT / "dist"
SKIP_DIRS = {"__pycache__", ".git", "node_modules"}
SKIP_SUFFIX = {".bak", ".pyc", ".log", ".orig"}

licensed = {item["file"] for item in json.loads((ROOT / "tools/media/photos-manifest.json").read_text(encoding="utf-8"))}
licensed |= {p.name for p in (ROOT / "tools/media/video").iterdir()}


def build(folder: str) -> None:
    src = ROOT / folder
    out = DIST / f"{folder}.zip"
    count = 0
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in sorted(src.rglob("*")):
            rel = path.relative_to(src)
            if path.is_dir() or SKIP_DIRS & set(rel.parts) or path.suffix in SKIP_SUFFIX:
                continue
            if path.name in licensed:
                raise SystemExit(f"Refusing to package licensed third-party media: {rel}")
            zf.write(path, f"{folder}/{rel.as_posix()}")
            count += 1
    print(f"{out.name}: {count} files, {out.stat().st_size // 1024} KB")


if __name__ == "__main__":
    DIST.mkdir(exist_ok=True)
    build("palltheme")
    build("palltheme-core")
