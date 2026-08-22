#!/usr/bin/env bash
# Speed verification for aun-projector.com.bd
# Usage: bash verify-speed.sh
SITE="https://aun-projector.com.bd"

echo "=============================================="
echo " AUN speed check  -  $(date '+%Y-%m-%d %H:%M')"
echo "=============================================="

echo
echo "--- 1. Cache headers on homepage ---"
curl -sI "$SITE/" | grep -iE "^(cf-cache-status|cache-control|set-cookie|vary|age|x-litespeed-cache|x-litespeed-cache-control|x-qc-cache)" \
  || echo "  (none of the watched headers present)"

echo
echo "--- 2. TTFB, 3 runs (want <0.3s once cached) ---"
for i in 1 2 3; do
  curl -s -o /dev/null -w "  run$i  ttfb=%{time_starttransfer}s  size=%{size_download}\n" "$SITE/"
done

echo
echo "--- 3. Key pages ---"
for p in "/" "/aun-u002-pro-full-hd-dustproof-tof-laser-auto-focus-android-projector-4k-support/" "/checkout/"; do
  hdr=$(curl -sI "$SITE$p")
  st=$(echo "$hdr" | grep -i "^cf-cache-status" | tr -d '' | awk '{print $2}')
  ls=$(echo "$hdr" | grep -i "^x-litespeed-cache:" | tr -d '' | awk '{print $2}')
  ck=$(echo "$hdr" | grep -ci "^set-cookie")
  printf "  %-42s " "$(echo "$p" | cut -c1-42)"
  curl -s -o /dev/null -w "ttfb=%{time_starttransfer}s" "$SITE$p"
  echo "  cf=${st:-none}  lscache=${ls:-none}  cookies=$ck"
done

echo "--- 4. App API routes (uncacheable, pure PHP cost) ---"
for r in ping config models dealers content watch; do
  printf "  %-10s " "$r"
  curl -s -o /dev/null -w "ttfb=%{time_starttransfer}s  http=%{http_code}  size=%{size_download}\n" \
    "$SITE/wp-json/aun-app/v1/$r"
done

echo
echo "--- 5. Baseline: static file (no PHP) ---"
curl -s -o /dev/null -w "  robots.txt  ttfb=%{time_starttransfer}s\n" "$SITE/robots.txt"

echo
echo "--- 6. Homepage front-end weight ---"
curl -s "$SITE/" -o /tmp/_aun_home.html
echo "  html_size : $(wc -c < /tmp/_aun_home.html) bytes"
echo "  ext JS    : $(grep -o '<script[^>]*src=' /tmp/_aun_home.html | wc -l)"
echo "  ext CSS   : $(grep -o '<link[^>]*stylesheet' /tmp/_aun_home.html | wc -l)"
echo "  <img>     : $(grep -o '<img ' /tmp/_aun_home.html | wc -l)"
rm -f /tmp/_aun_home.html
echo
