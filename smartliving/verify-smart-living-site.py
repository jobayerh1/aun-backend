"""Check the rendered bench output: PHP errors, SEO head, schema, structure.

Guards added after the v1.0.1 regressions:
  - nothing visible may render after </footer>  (the theme's off-canvas drawer)
  - the burger's last margin declaration must keep margin-left: auto
"""
import json, os, re, sys

PLUGIN = r"C:/Users/Jobayer Hossain/Downloads/Claude session/smartliving/smart-living-site"
PAGES = ['home', 'about-us', 'our-brands', 'corporate-solutions',
         'dealer-partnership', 'contact-us']
fails = []

PHP_ERR = re.compile(
    r'(Fatal error|Parse error|Uncaught \w*(Error|Exception))\s*:|'
    r'<b>(Warning|Notice|Deprecated)</b>:|'
    r'^(Warning|Notice|Deprecated):', re.M)


def after_footer_markup(html):
    """Visible element markup rendered after the closing </footer>.

    Scripts, styles, comments, templates and the WP admin bar are expected and
    ignored. Anything else means something is being printed into wp_footer()
    that the page is going to show.
    """
    i = html.rfind('</footer>')
    if i < 0:
        return ['no </footer> in output']
    tail = html[i + len('</footer>'):]
    tail = re.sub(r'<(script|style|noscript|template)\b[^>]*>.*?</\1>', ' ', tail, flags=re.S | re.I)
    tail = re.sub(r'<!--.*?-->', ' ', tail, flags=re.S)
    # the admin bar and its markup only appear for logged-in users
    tail = re.sub(r'<div[^>]*id=["\']wpadminbar["\'].*', ' ', tail, flags=re.S | re.I)
    found = []
    for tag, attrs in re.findall(r'<([a-zA-Z][a-zA-Z0-9]*)((?:\s[^>]*)?)>', tail):
        if tag.lower() in ('br', 'wbr', 'link', 'meta'):
            continue
        ident = re.search(r'(?:id|class)=["\']([^"\']{0,60})', attrs)
        found.append('<%s%s>' % (tag, (' ' + ident.group(1)) if ident else ''))
    return found


def burger_margin_left():
    """The effective margin-left the stylesheet leaves on .burger."""
    css = open(os.path.join(PLUGIN, 'assets/sl.css'), encoding='utf-8').read()
    css = re.sub(r'/\*.*?\*/', ' ', css, flags=re.S)
    value = None
    for sel, body in re.findall(r'([^{}]+)\{([^{}]*)\}', css):
        if not re.search(r'\.burger\s*(\{|$|,)', sel + '{'):
            continue
        for prop, val in re.findall(r'(margin(?:-left)?)\s*:\s*([^;]+)', body):
            val = val.strip()
            if prop == 'margin-left':
                value = val
            else:
                parts = val.split()
                # margin shorthand: 1=all, 2=v h, 3=t h b, 4=t r b l
                value = {1: parts[0], 2: parts[1], 3: parts[1]}.get(len(parts), parts[3] if len(parts) > 3 else None)
    return value


for name in PAGES:
    fn = 'out/sl-%s.html' % name
    if not os.path.exists(fn):
        fails.append('%s: not rendered' % name)
        continue
    s = open(fn, encoding='utf-8', errors='replace').read()
    out = ['\n=== %s (%d KB) ===' % (name, len(s) / 1024)]

    err = PHP_ERR.search(s)
    if err:
        fails.append('%s: PHP error -> %s' % (name, err.group(0)[:80]))
    else:
        out.append('  php errors: none')

    title = re.search(r'<title>(.*?)</title>', s, re.S)
    desc = re.search(r'<meta name="description" content="(.*?)"', s)
    canon = re.search(r'<link rel="canonical" href="(.*?)"', s)
    ogi = re.search(r'<meta property="og:image" content="(.*?)"', s)
    out.append('  title : %s' % (title.group(1).strip()[:74] if title else '!! MISSING'))
    for label, m in (('title', title), ('description', desc), ('canonical', canon), ('og:image', ogi)):
        if not m:
            fails.append('%s: missing %s' % (name, label))

    h1 = re.findall(r'<h1[^>]*>(.*?)</h1>', s, re.S)
    out.append('  h1    : %d' % len(h1))
    if len(h1) != 1:
        fails.append('%s: %d h1 tags (want 1)' % (name, len(h1)))

    ld = re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', s, re.S)
    types = []
    for block in ld:
        try:
            data = json.loads(block)
        except Exception as e:
            fails.append('%s: JSON-LD is not valid JSON (%s)' % (name, e))
            continue
        for node in data.get('@graph', [data]):
            types.append(node.get('@type'))
            if node.get('@type') == 'Organization' and '#organization' in str(node.get('@id', '')):
                subs = node.get('subOrganization', [])
                if len(subs) != 3:
                    fails.append('%s: %d subOrganization (want 3)' % (name, len(subs)))
    out.append('  schema: %s' % ', '.join(t for t in types if t))
    for need in ('Organization', 'LocalBusiness', 'WebPage'):
        if need not in types:
            fails.append('%s: schema missing %s' % (name, need))
    if name in ('corporate-solutions', 'dealer-partnership') and 'FAQPage' not in types:
        fails.append('%s: schema missing FAQPage' % name)
    if name != 'home' and 'BreadcrumbList' not in types:
        fails.append('%s: schema missing BreadcrumbList' % name)

    if 'assets/sl.css' not in s:
        fails.append('%s: sl.css not enqueued' % name)
    if 'fonts.googleapis.com' in s or 'fonts.gstatic.com' in s:
        fails.append('%s: still requesting Google Fonts' % name)
    if 'revslider' in s:
        fails.append('%s: Slider Revolution assets still present' % name)
    cv = len(re.findall(r'<canvas[^>]*data-trace', s))
    out.append('  canvas: %d trace fields' % cv)
    if cv == 0:
        fails.append('%s: no circuit canvas' % name)

    # GUARD: the theme's off-canvas drawer, or anything else, printed after us
    stray = after_footer_markup(s)
    out.append('  after </footer>: %s' % (', '.join(stray[:4]) if stray else 'nothing'))
    if stray:
        fails.append('%s: markup renders after </footer> -> %s' % (name, ', '.join(stray[:4])))
    if 'mobile-sidebar' in s or 'id="main-menu"' in s:
        fails.append("%s: theme off-canvas drawer markup still in the page" % name)

    for bad in ('{{', '>Array<', 'devnote', 'data-go='):
        if bad in s:
            fails.append('%s: leftover %r in output' % (name, bad))

    print('\n'.join(out))

# GUARD: the burger must stay pushed to the right edge
ml = burger_margin_left()
print('\n=== stylesheet guards ===')
print('  .burger effective margin-left: %s' % ml)
if ml != 'auto':
    fails.append('.burger margin-left is %r, must be "auto" or it sits next to the logo' % ml)

print('\n' + '=' * 60)
if fails:
    print('FAILURES (%d):' % len(fails))
    for f in fails:
        print('  - %s' % f)
    sys.exit(1)
print('ALL CHECKS PASS')
