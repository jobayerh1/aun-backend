"""Full live audit: content duplication, headings, SEO, a11y basics, weight."""
import html as htmlmod
import json, os, re, sys
from collections import Counter

PAGES = ['home', 'about-us', 'our-brands', 'corporate-solutions',
         'dealer-partnership', 'contact-us', '404']
findings = []


def text_of(frag):
    frag = re.sub(r'<(script|style|noscript|svg)\b[^>]*>.*?</\1>', ' ', frag, flags=re.S | re.I)
    frag = re.sub(r'<[^>]+>', ' ', frag)
    return re.sub(r'\s+', ' ', htmlmod.unescape(frag)).strip()


for name in PAGES:
    fn = 'live/%s.html' % name
    if not os.path.exists(fn):
        continue
    s = open(fn, encoding='utf-8', errors='replace').read()
    print('\n' + '=' * 66)
    print('%s  (%.0f KB html)' % (name, len(s) / 1024))
    print('=' * 66)

    # ---------- heading order ----------
    heads = [(int(m.group(1)), text_of(m.group(2))[:58])
             for m in re.finditer(r'<h([1-6])[^>]*>(.*?)</h\1>', s, re.S)]
    prev, skips = 0, []
    for lvl, txt in heads:
        if prev and lvl > prev + 1:
            skips.append('h%d -> h%d (%s)' % (prev, lvl, txt))
        prev = lvl
    print('  headings: %s' % ' '.join('h%d' % l for l, _ in heads))
    if skips:
        findings.append('%s: heading level skipped: %s' % (name, '; '.join(skips)))
    if sum(1 for l, _ in heads if l == 1) != 1:
        findings.append('%s: %d h1' % (name, sum(1 for l, _ in heads if l == 1)))

    # ---------- duplicated visible text blocks ----------
    body = s[s.find('<main'):] if '<main' in s else s
    chunks = []
    for m in re.finditer(r'<(p|span|div|a|li)\b[^>]*>(.*?)</\1>', body, re.S):
        t = text_of(m.group(2))
        if 8 <= len(t) <= 90:
            chunks.append(t)
    dupes = [(t, c) for t, c in Counter(chunks).items() if c > 1]
    dupes.sort(key=lambda x: -x[1])
    strong = [d for d in dupes if d[1] >= 2 and not d[0].lower() in ('contact', 'home')]
    if strong:
        print('  repeated text blocks:')
        for t, c in strong[:10]:
            print('    %dx  %s' % (c, t[:72]))

    # ---------- the specific duplication the owner spotted ----------
    if name == 'home':
        for probe, label in (
            ('108 Golartek', 'street address'),
            ('9638-078888', 'phone number'),
            ('info@smartliving.com.bd', 'email address'),
            ('Saturday', 'opening days'),
            ('Open on Google Maps', 'maps link'),
        ):
            n = s.count(probe)
            print('  %-16s appears %dx on the home page' % (label, n))
            if n > 1:
                findings.append('home: %s appears %dx — the contact band repeats the footer' % (label, n))

    # ---------- images ----------
    imgs = re.findall(r'<img\b([^>]*)>', s)
    no_alt = [a for a in imgs if 'alt=' not in a]
    no_dim = [a for a in imgs if not ('width=' in a and 'height=' in a)]
    print('  images: %d total, %d without alt, %d without width+height' % (len(imgs), len(no_alt), len(no_dim)))
    if no_alt:
        findings.append('%s: %d <img> without alt' % (name, len(no_alt)))
    if no_dim:
        findings.append('%s: %d <img> without width+height (CLS risk)' % (name, len(no_dim)))

    # ---------- links ----------
    ext = re.findall(r'<a\b[^>]*href="https?://(?!www\.smartliving\.com\.bd)[^"]*"[^>]*>', s)
    bad_target = [a for a in ext if 'target="_blank"' in a and 'rel=' not in a]
    if bad_target:
        findings.append('%s: %d external target=_blank without rel=noopener' % (name, len(bad_target)))
    empty = re.findall(r'<a\b[^>]*>\s*</a>', s)
    if empty:
        findings.append('%s: %d empty <a>' % (name, len(empty)))

    # ---------- what still loads ----------
    legacy = {
        'flatsome css': r'themes/flatsome/assets/css/flatsome\.css|themes/flatsome/style\.css',
        'google fonts': r'fonts\.(googleapis|gstatic)\.com',
        'font awesome': r'use\.fontawesome\.com',
        'revslider': r'revslider|sr7\.',
        'off-canvas drawer': r'id="main-menu"|mobile-sidebar',
        'jquery': r'/wp-includes/js/jquery/jquery',
    }
    still = [k for k, p in legacy.items() if re.search(p, s, re.I)]
    print('  legacy still present: %s' % (', '.join(still) if still else 'none'))
    for k in still:
        sev = 'jquery' if k == 'jquery' else None
        if sev:
            pass  # noted separately, CF7 needs it
        else:
            findings.append('%s: %s still present' % (name, k))

    # ---------- SEO ----------
    if name != '404':
        for label, pat in (('title', r'<title>(.{10,})</title>'),
                           ('description', r'<meta name="description" content="(.{40,})"'),
                           ('canonical', r'<link rel="canonical"'),
                           ('og:image', r'og:image')):
            if not re.search(pat, s, re.S):
                findings.append('%s: missing %s' % (name, label))
        t = re.search(r'<title>(.*?)</title>', s, re.S)
        if t and len(t.group(1)) > 65:
            findings.append('%s: title is %d chars (Google truncates ~60)' % (name, len(t.group(1))))
        d = re.search(r'<meta name="description" content="(.*?)"', s)
        if d and len(d.group(1)) > 165:
            findings.append('%s: meta description is %d chars (truncates ~160)' % (name, len(d.group(1))))
    else:
        if 'noindex' not in s:
            findings.append('404: not noindexed')
        print('  404 noindex: %s' % ('yes' if 'noindex' in s else 'NO'))

print('\n' + '=' * 66)
print('FINDINGS (%d)' % len(findings))
print('=' * 66)
for f in findings:
    print('  - %s' % f)
