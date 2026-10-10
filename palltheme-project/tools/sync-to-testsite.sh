#!/bin/sh
# Mirrors the theme + plugin into the local test site (dev helper).
# Copies over the top first (so the live site never sees missing files),
# then removes files that no longer exist in the project.
P="$(cd "$(dirname "$0")/.." && pwd)"
N="/c/Users/Jobayer Hossain/wp-local/palltheme-site/wp-content"
for pair in "palltheme:themes/palltheme" "palltheme-core:plugins/palltheme-core"; do
	src="$P/${pair%%:*}"
	dst="$N/${pair#*:}"
	mkdir -p "$dst"
	cp -r "$src/." "$dst/"
	(cd "$dst" && find . -type f) | while read -r f; do
		[ -e "$src/$f" ] || rm -f "$dst/$f"
	done
done
echo synced
