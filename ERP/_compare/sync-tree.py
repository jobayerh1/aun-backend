#!/usr/bin/env python3
"""Copy an ERP tree into the git repo, excluding runtime junk and SECRETS."""
import os, sys, shutil

SKIP_DIRS = {
    "vendor", "node_modules", ".git", "storage", "bootstrap/cache",
    "public/uploads", "public/vendor", "public/build",
}
# Never enter git history: secrets, logs, archives
SKIP_FILES = {".env", "sms_test.php", "error_log", ".DS_Store", "Thumbs.db"}
SKIP_EXT   = {".zip", ".log"}

def skip_dir(rel):
    parts = rel.split("/")
    return any(p in SKIP_DIRS for p in parts) or \
           any(rel == s or rel.startswith(s + "/") for s in SKIP_DIRS)

def main(src, dst):
    src, dst = os.path.abspath(src), os.path.abspath(dst)
    copied = skipped = 0
    for dirpath, dirnames, filenames in os.walk(src):
        rel_dir = os.path.relpath(dirpath, src).replace(os.sep, "/")
        rel_dir = "" if rel_dir == "." else rel_dir
        if rel_dir and skip_dir(rel_dir):
            dirnames[:] = []
            continue
        dirnames[:] = [d for d in dirnames
                       if not skip_dir((rel_dir + "/" + d) if rel_dir else d)]
        for fn in filenames:
            if fn in SKIP_FILES or os.path.splitext(fn)[1].lower() in SKIP_EXT:
                skipped += 1
                continue
            rel = (rel_dir + "/" + fn) if rel_dir else fn
            target = os.path.join(dst, rel.replace("/", os.sep))
            os.makedirs(os.path.dirname(target), exist_ok=True)
            shutil.copy2(os.path.join(dirpath, fn), target)
            copied += 1
    print("copied: %d   skipped(secret/junk): %d" % (copied, skipped))

if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
