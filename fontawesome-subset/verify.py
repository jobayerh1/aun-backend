import json
from fontTools.ttLib import TTFont
icons = json.load(open('icon-map.json'))
by_cp = {}
for n, cp in icons.items():
    by_cp.setdefault(int(cp, 16), []).append(n)
covered = {}
for f in ['fa-solid-900', 'fa-brands-400', 'fa-regular-400']:
    font = TTFont(f + '.subset.woff2')
    cm = set()
    for t in font['cmap'].tables:
        cm |= set(t.cmap.keys())
    covered[f] = {cp for cp in by_cp if cp in cm}
    print("%-30s glyphs=%-4d icons=%d" % (f + '.subset.woff2', font['maxp'].numGlyphs, len(covered[f])))
allc = set().union(*covered.values())
missing = set(by_cp) - allc
print("\ncovered %d of %d icon codepoints" % (len(allc), len(by_cp)))
print("MISSING:" , sorted({n for cp in missing for n in by_cp[cp]}) if missing else "none - all present")
print("\nbrands:", sorted({n for cp in covered['fa-brands-400'] for n in by_cp[cp]}))
