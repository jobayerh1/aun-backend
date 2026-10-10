"""Broken-link / PHP-error crawler for the local test site.

Follows every internal link (pages, posts, products, archives, menus),
checks every internal <a href>, <img src>/srcset, <source>, <video poster>,
<link href> and <script src> once, and reports:
  - non-200 responses (with the pages that reference them)
  - PHP warnings/notices/fatals printed into the HTML
  - unresolved {type:key} placeholders left in content
  - images without alt attributes

Usage: python crawl.py [base_url]
"""
import re
import sys
import concurrent.futures as cf
from collections import defaultdict
from html.parser import HTMLParser
from urllib.parse import urljoin, urldefrag, urlparse

import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8090").rstrip("/")
HOST = urlparse(BASE).netloc
MAX_PAGES = 600
SKIP = re.compile(r"(wp-admin|wp-login|/feed/?$|add-to-cart=|add_to_wishlist=|remove_item|action=yith|\?replytocom|/wp-json/|xmlrpc|/cart/\?|orderby=|/comments/feed|\?s=|/page/\d+|filter_|min_price|max_price|rating_filter|in_stock|/wc-api|logout|lost-password)")
PHP_ERR = re.compile(r"(<b>(Warning|Notice|Fatal error|Deprecated|Parse error)</b>:|There has been a critical error|Undefined (index|variable|array key))")
PLACEHOLDER = re.compile(r"\{(svc|sol|case|post|page|pcat|product):[A-Za-z0-9_-]+\}")


class Parser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links, self.assets, self.noalt = [], [], []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "a" and a.get("href"):
            self.links.append(a["href"])
        if tag == "img":
            if a.get("src") and not a["src"].startswith("data:"):
                self.assets.append(a["src"])
            for part in (a.get("srcset") or "").split(","):
                if part.strip():
                    self.assets.append(part.strip().split(" ")[0])
            if "alt" not in a:
                self.noalt.append(a.get("src", "?"))
        if tag in ("source", "script") and a.get("src"):
            self.assets.append(a["src"])
        if tag == "video" and a.get("poster"):
            self.assets.append(a["poster"])
        if tag == "link" and a.get("href") and a.get("rel") in ("stylesheet", "preload", "icon"):
            self.assets.append(a["href"])


def internal(url):
    p = urlparse(url)
    return p.scheme in ("http", "https") and p.netloc == HOST


def fetch(url, want_body=True):
    req = urllib.request.Request(url, headers={"User-Agent": "pall-qa-crawler"})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            ctype = r.headers.get("Content-Type", "")
            body = r.read().decode("utf-8", "replace") if want_body and "html" in ctype else ""
            return r.status, body
    except urllib.error.HTTPError as e:
        return e.code, ""
    except Exception as e:  # noqa: BLE001
        return f"ERR {e.__class__.__name__}", ""


def main():
    seen_pages, queue = set(), [BASE + "/"]
    refs = defaultdict(set)
    status = {}
    php_errors, placeholders, noalt = [], [], defaultdict(set)
    assets = set()
    with cf.ThreadPoolExecutor(6) as pool:
        while queue:
            batch = [u for u in dict.fromkeys(queue) if u not in seen_pages][: max(0, MAX_PAGES - len(seen_pages))]
            queue = []
            print(f"  crawling {len(batch)} pages ({len(seen_pages)} done)", file=sys.stderr, flush=True)
            seen_pages.update(batch)
            for url, (code, body) in zip(batch, pool.map(fetch, batch)):
                status[url] = code
                if not body:
                    continue
                if PHP_ERR.search(body):
                    php_errors.append((url, PHP_ERR.search(body).group(0)))
                text_only = re.sub(r"<script.*?</script>", "", body, flags=re.S)
                if PLACEHOLDER.search(text_only):
                    placeholders.append((url, PLACEHOLDER.search(text_only).group(0)))
                p = Parser()
                p.feed(body)
                for src in p.noalt:
                    noalt[src].add(url)
                for href in p.links:
                    full = urldefrag(urljoin(url, href))[0]
                    if not internal(full) or SKIP.search(full):
                        continue
                    if urlparse(full).query and not re.search(r"\.(css|js|webp|svg|png|jpe?g|pdf)(\?|$)", urlparse(full).path):
                        continue  # filtered/sorted variants of the same page multiply endlessly
                    refs[full].add(url)
                    if full not in seen_pages and not re.search(r"\.(pdf|svg|webp|jpe?g|png|zip|json|mp4|webm)$", full):
                        queue.append(full)
                    elif re.search(r"\.(pdf|svg|webp|jpe?g|png|zip|json|mp4|webm)$", full):
                        assets.add(full)
                for a in p.assets:
                    full = urljoin(url, a)
                    if internal(full):
                        assets.add(full)
                        refs[full].add(url)
        asset_list = sorted(assets - set(status))
        for url, (code, _) in zip(asset_list, pool.map(lambda u: fetch(u, False), asset_list)):
            status[url] = code

    bad = {u: c for u, c in status.items() if c != 200}
    print(f"Pages crawled: {len(seen_pages)}  assets checked: {len(asset_list)}")
    print(f"Non-200: {len(bad)}")
    for u, c in sorted(bad.items()):
        print(f"  {c}  {u}\n       from: {', '.join(sorted(refs[u])[:3])}")
    print(f"PHP errors in HTML: {len(php_errors)}")
    for u, e in php_errors:
        print(f"  {u}: {e}")
    print(f"Unresolved placeholders: {len(placeholders)}")
    for u, e in placeholders:
        print(f"  {u}: {e}")
    print(f"Images without alt attribute: {len(noalt)}")
    for src, pages in list(noalt.items())[:15]:
        print(f"  {src}  on {sorted(pages)[0]}")
    with open("crawl-pages.txt", "w", encoding="utf-8") as f:
        f.write("\n".join(sorted(u for u in seen_pages if status.get(u) == 200)))


if __name__ == "__main__":
    main()
