import re, json
BS = chr(92)
raw = open('all.min.css', encoding='utf-8', errors='ignore').read()

mapping = {}
# Blocks: .fa-a,.fa-b{--fa:"\f0e7"}   or   .fa-hashtag{--fa:"\#"}
block = re.compile(r'([^{}]+)\{--fa:"([^"]+)"[^}]*\}')
for m in block.finditer(raw):
    val = m.group(2)
    if not val.startswith(BS):
        continue
    body = val[1:].strip()
    if re.fullmatch(r'[0-9a-fA-F]{3,5}', body):
        cp = int(body, 16)
    elif len(body) == 1:
        cp = ord(body)          # literal ASCII icon, e.g. "\#"
    else:
        continue
    for sel in m.group(1).split(','):
        sel = sel.strip()
        if sel.startswith('.fa-'):
            mapping.setdefault(sel[1:], cp)

used = [l.strip() for l in open('used-icons.txt', encoding='utf-8')
        if l.strip() and not l.startswith('fa-solid-') and not l.startswith('fa-brands-')
        and not l.startswith('fa-regular-') and 'Binary file' not in l]

hit  = {u: mapping[u] for u in used if u in mapping}
miss = [u for u in used if u not in mapping]

# Safety margin: common UI icons that could be injected by JS or admin content
# and would not appear in crawled HTML.
BUFFER = """fa-bars fa-xmark fa-user fa-heart fa-cart-shopping fa-magnifying-glass
fa-phone fa-envelope fa-star fa-star-half-stroke fa-chevron-left fa-chevron-right
fa-chevron-up fa-chevron-down fa-angle-left fa-angle-right fa-angle-up fa-angle-down
fa-plus fa-minus fa-trash fa-pen fa-download fa-upload fa-spinner fa-circle-notch
fa-info fa-exclamation fa-question fa-lock fa-unlock fa-eye fa-eye-slash fa-share
fa-copy fa-link fa-print fa-filter fa-sort fa-arrow-left fa-arrow-right fa-arrow-up
fa-arrow-down fa-house fa-clock fa-calendar fa-tag fa-tags fa-comment fa-comments
fa-thumbs-up fa-paper-plane fa-headset fa-rotate fa-ban fa-check-double""".split()

buf = {b: mapping[b] for b in BUFFER if b in mapping and b not in hit}
final = dict(hit); final.update(buf)

cps = set(final.values())
cps |= set(range(0x20, 0x7F))   # full ASCII: covers every literal-character icon

open('codepoints.txt', 'w').write(','.join('U+%04X' % c for c in sorted(cps)))
json.dump({k: '%04X' % v for k, v in sorted(final.items())}, open('icon-map.json', 'w'), indent=1)

print("CSS defines        :", len(mapping), "icon class names")
print("used on the site   :", len(hit), "icons")
print("unmatched          :", miss or 'none')
print("safety buffer added:", len(buf))
print("ASCII range added  : U+0020-U+007E")
print("TOTAL codepoints   :", len(cps))
