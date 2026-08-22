#!/usr/bin/env python3
"""
Compare stock UltimatePOS (+ modules) against the LIVE site to produce an
exact customization manifest.

  _compare/stock/  = untouched v6.9 + stock Repair module
  _compare/live/   = files downloaded from the live server

Outputs:
  _compare/out/MANIFEST.md   human-readable summary
  _compare/out/diffs/        one .diff per modified file
"""
import os, sys, hashlib, difflib, io

BASE   = os.path.dirname(os.path.abspath(__file__))
STOCK  = os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 else os.path.join(BASE, "stock")
LIVE   = os.path.abspath(sys.argv[2]) if len(sys.argv) > 2 else os.path.join(BASE, "live")
OUT    = os.path.join(BASE, "out")
DIFFS  = os.path.join(OUT, "diffs")

SKIP_DIRS = {
    "vendor", "node_modules", ".git", "storage", "bootstrap/cache",
    "public/uploads", "public/vendor", "public/build", "install",
}
SKIP_NAMES = {".env", "composer.lock", "package-lock.json", ".DS_Store",
              "Thumbs.db", "find-my-customizations.php"}
TEXT_EXT = {".php",".js",".css",".blade.php",".json",".txt",".md",".xml",
            ".yml",".yaml",".htaccess",".sql",".env",".ini"}

def rel_files(root):
    out = {}
    for dirpath, dirnames, filenames in os.walk(root):
        rel_dir = os.path.relpath(dirpath, root).replace(os.sep, "/")
        if rel_dir == ".":
            rel_dir = ""
        parts = rel_dir.split("/") if rel_dir else []
        if any(p in SKIP_DIRS for p in parts):
            dirnames[:] = []
            continue
        if rel_dir and any(rel_dir == s or rel_dir.startswith(s + "/") for s in SKIP_DIRS):
            dirnames[:] = []
            continue
        for fn in filenames:
            if fn in SKIP_NAMES:
                continue
            rel = (rel_dir + "/" + fn) if rel_dir else fn
            out[rel] = os.path.join(dirpath, fn)
    return out

def sha(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()

def is_text(rel):
    return any(rel.endswith(e) for e in TEXT_EXT)

def read_lines(path):
    try:
        with io.open(path, encoding="utf-8", errors="replace") as f:
            return f.readlines()
    except Exception:
        return None

def main():
    if not os.path.isdir(STOCK) or not os.listdir(STOCK):
        sys.exit("ERROR: _compare/stock/ is empty. Extract stock v6.9 there first.")
    if not os.path.isdir(LIVE) or not os.listdir(LIVE):
        sys.exit("ERROR: _compare/live/ is empty. Extract the live download there first.")

    stock = rel_files(STOCK)
    live  = rel_files(LIVE)

    added    = sorted(set(live) - set(stock))
    removed  = sorted(set(stock) - set(live))
    common   = sorted(set(stock) & set(live))
    modified = []

    os.makedirs(DIFFS, exist_ok=True)
    for rel in common:
        try:
            if sha(stock[rel]) == sha(live[rel]):
                continue
        except Exception:
            continue
        a = read_lines(stock[rel]); b = read_lines(live[rel])
        nchg = 0
        if a is not None and b is not None and is_text(rel):
            d = list(difflib.unified_diff(a, b,
                     fromfile="stock/" + rel, tofile="live/" + rel, n=3))
            nchg = sum(1 for l in d if (l.startswith("+") and not l.startswith("+++"))
                                    or (l.startswith("-") and not l.startswith("---")))
            safe = rel.replace("/", "__") + ".diff"
            with io.open(os.path.join(DIFFS, safe), "w", encoding="utf-8") as f:
                f.writelines(d)
        modified.append((rel, nchg))

    modified.sort(key=lambda x: (-x[1], x[0]))

    lines = []
    lines.append("# UltimatePOS Customization Manifest\n")
    lines.append("Stock v6.9 vs LIVE site — exact file comparison.\n")
    lines.append("| | Count |\n|---|---|\n")
    lines.append("| Files you ADDED (custom, upgrade-safe) | %d |\n" % len(added))
    lines.append("| Core files you MODIFIED (overwritten by upgrade) | %d |\n" % len(modified))
    lines.append("| Files MISSING vs stock | %d |\n" % len(removed))
    lines.append("\n---\n\n## MODIFIED core files (most changed first)\n\n")
    lines.append("| Changed lines | File |\n|---|---|\n")
    for rel, n in modified:
        lines.append("| %d | `%s` |\n" % (n, rel))
    lines.append("\n---\n\n## ADDED files (yours — safe across upgrades)\n\n")
    for rel in added:
        lines.append("- `%s`\n" % rel)
    if removed:
        lines.append("\n---\n\n## MISSING vs stock (check these)\n\n")
        for rel in removed:
            lines.append("- `%s`\n" % rel)

    with io.open(os.path.join(OUT, "MANIFEST.md"), "w", encoding="utf-8") as f:
        f.writelines(lines)

    print("ADDED    :", len(added))
    print("MODIFIED :", len(modified))
    print("MISSING  :", len(removed))
    print("\nWrote _compare/out/MANIFEST.md and per-file diffs in _compare/out/diffs/")

if __name__ == "__main__":
    main()
