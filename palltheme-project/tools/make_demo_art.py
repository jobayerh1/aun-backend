"""
Generates the original demo artwork for Palltheme (all SVG, no third-party assets).

Output: palltheme-core/assets/demo/*.svg and palltheme-core/assets/images/hero-network.svg
Copyright (c) 2026 Engr. Nazim U Ahmed. All rights reserved.
"""
import math
import os
import random

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEMO = os.path.join(ROOT, "palltheme-core", "assets", "demo")
IMG = os.path.join(ROOT, "palltheme-core", "assets", "images")
os.makedirs(DEMO, exist_ok=True)
os.makedirs(IMG, exist_ok=True)

PRIMARY = "#2567AD"
ACCENT = "#00A8FF"
DARK = "#07111F"
SECOND = "#0B1220"

# Line icons (same drawings as the plugin icon library, 24px grid).
ICONS = {
    "server": '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6"/>',
    "network": '<rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v4M5 17v-3h14v3M12 11v3"/>',
    "cloud": '<path d="M7 18h10a4.5 4.5 0 0 0 .6-8.96A6 6 0 0 0 6.1 10.1 4 4 0 0 0 7 18Z"/>',
    "shield": '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
    "settings": '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
    "ai": '<circle cx="12" cy="12" r="3"/><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l3 3M15 15l3 3M6 18l3-3M15 9l3-3"/>',
    "rack": '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M5 7h14M5 12h14M5 17h14M8 4.5h.01M8 9.5h.01M8 14.5h.01M8 19.5h.01"/>',
    "code": '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
    "consult": '<path d="M21 12a8 8 0 0 1-11.7 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/>',
    "backup": '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    "hosting": '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4M7 8h4M7 11h7"/>',
    "iot": '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/>',
    "telecom": '<path d="M12 10v12M12 10l-4 12M12 10l4 12"/><circle cx="12" cy="7" r="2"/><path d="M7.8 2.8a6 6 0 0 0 0 8.4M16.2 2.8a6 6 0 0 1 0 8.4"/>',
    "bank": '<path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/>',
    "health": '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 5.6 5 5 0 0 1 21.2 11c-2.2 4.6-9.2 9-9.2 9Z"/>',
    "education": '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/>',
    "government": '<path d="M12 2v4M8 6h8M5 10h14M4 21h16M6 10v8M10 10v8M14 10v8M18 10v8"/>',
    "retail": '<path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.76L21 8H6.2"/>',
    "factory": '<path d="M3 21V10l6 4V10l6 4V6l6 3v12H3Z"/>',
    "terminal": '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/>',
    "chart": '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-7"/>',
    "lock": '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    "fiber": '<path d="M3 20c4 0 5-4 9-8s5-8 9-8"/><path d="M3 14c3 0 4-2 6-4M15 20c2-2 3-4 6-4"/>',
    "speed": '<path d="M12 14l4-4"/><path d="M3.3 19a10 10 0 1 1 17.4 0"/>',
}

HUES = [
    ("#2567AD", "#00A8FF"),
    ("#1E3A8A", "#22D3EE"),
    ("#0F766E", "#2DD4BF"),
    ("#4338CA", "#818CF8"),
    ("#0E7490", "#38BDF8"),
    ("#1D4ED8", "#60A5FA"),
    ("#155E75", "#06B6D4"),
    ("#3730A3", "#A78BFA"),
]


def write(path, svg):
    with open(path, "w", encoding="utf-8") as f:
        f.write(svg)


def cover(name, icon, seed, w=1200, h=800):
    """Abstract technology cover: dark gradient, grid, glow, circuit lines, big line icon."""
    rnd = random.Random(seed)
    c1, c2 = HUES[seed % len(HUES)]
    lines = []
    for _ in range(9):
        x = rnd.randint(0, w)
        y = rnd.randint(0, h)
        pts = [(x, y)]
        for _ in range(3):
            if rnd.random() > 0.5:
                x += rnd.choice([-1, 1]) * rnd.randint(80, 220)
            else:
                y += rnd.choice([-1, 1]) * rnd.randint(60, 180)
            pts.append((x, y))
        d = "M" + " L".join(f"{px} {py}" for px, py in pts)
        lines.append(f'<path d="{d}" stroke="{c2}" stroke-opacity=".28" stroke-width="2" fill="none"/>')
        lines.append(f'<circle cx="{pts[-1][0]}" cy="{pts[-1][1]}" r="5" fill="{c2}" fill-opacity=".7"/>')
    dots = "".join(
        f'<circle cx="{rnd.randint(0, w)}" cy="{rnd.randint(0, h)}" r="{rnd.choice([1.5, 2, 2.5])}" fill="#fff" fill-opacity="{rnd.choice([.25, .4, .6])}"/>'
        for _ in range(40)
    )
    ix, iy, isz = int(w * 0.62), int(h * 0.5), 360
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}">
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{DARK}"/><stop offset="1" stop-color="{SECOND}"/></linearGradient>
<radialGradient id="g1" cx=".72" cy=".35" r=".6"><stop offset="0" stop-color="{c1}" stop-opacity=".85"/><stop offset="1" stop-color="{c1}" stop-opacity="0"/></radialGradient>
<radialGradient id="g2" cx=".15" cy=".95" r=".5"><stop offset="0" stop-color="{c2}" stop-opacity=".35"/><stop offset="1" stop-color="{c2}" stop-opacity="0"/></radialGradient>
<pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0V48" fill="none" stroke="#9cc2ff" stroke-opacity=".09"/></pattern>
<linearGradient id="ic" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="{c2}"/></linearGradient>
</defs>
<rect width="{w}" height="{h}" fill="url(#bg)"/>
<rect width="{w}" height="{h}" fill="url(#grid)"/>
<rect width="{w}" height="{h}" fill="url(#g1)"/>
<rect width="{w}" height="{h}" fill="url(#g2)"/>
{''.join(lines)}
{dots}
<circle cx="{ix}" cy="{iy}" r="{isz * 0.62}" fill="{c2}" fill-opacity=".06" stroke="{c2}" stroke-opacity=".25"/>
<circle cx="{ix}" cy="{iy}" r="{isz * 0.82}" fill="none" stroke="#fff" stroke-opacity=".07" stroke-dasharray="4 10"/>
<g transform="translate({ix - isz / 2} {iy - isz / 2}) scale({isz / 24})" fill="none" stroke="url(#ic)" stroke-width=".9" stroke-linecap="round" stroke-linejoin="round">{ICONS[icon]}</g>
</svg>'''
    write(os.path.join(DEMO, f"cover-{name}.svg"), svg)


# --------------------------------------------------------------------------
# Product illustrations (800x800, light studio background)
# --------------------------------------------------------------------------
def product_frame(inner, label):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
<defs>
<radialGradient id="bg" cx=".5" cy=".42" r=".75"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#E9F0F8"/></radialGradient>
<linearGradient id="metal" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3B4656"/><stop offset=".5" stop-color="#1F2733"/><stop offset="1" stop-color="#121821"/></linearGradient>
<linearGradient id="face" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2A3442"/><stop offset="1" stop-color="#161D27"/></linearGradient>
<linearGradient id="silver" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#F3F6FA"/><stop offset=".5" stop-color="#C9D3DF"/><stop offset="1" stop-color="#9AA7B6"/></linearGradient>
<linearGradient id="blue" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="{PRIMARY}"/><stop offset="1" stop-color="{ACCENT}"/></linearGradient>
<filter id="sh" x="-20%" y="-20%" width="140%" height="160%"><feGaussianBlur in="SourceAlpha" stdDeviation="14"/><feOffset dy="18"/><feComponentTransfer><feFuncA type="linear" slope=".28"/></feComponentTransfer><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>
</defs>
<rect width="800" height="800" fill="url(#bg)"/>
<ellipse cx="400" cy="640" rx="290" ry="26" fill="#0B1220" opacity=".08"/>
<g filter="url(#sh)">{inner}</g>
<text x="400" y="740" text-anchor="middle" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="22" font-weight="600" fill="#7A8AA0" letter-spacing="3">{label}</text>
</svg>'''


def ports(x, y, cols, rows, pw=22, ph=18, gap=6, color="#0B1220", led=True):
    out = []
    for r in range(rows):
        for c in range(cols):
            px = x + c * (pw + gap)
            py = y + r * (ph + gap)
            out.append(f'<rect x="{px}" y="{py}" width="{pw}" height="{ph}" rx="2" fill="{color}" stroke="#4B5767" stroke-width="1"/>')
            out.append(f'<rect x="{px + 5}" y="{py + 3}" width="{pw - 10}" height="5" fill="#2B3543"/>')
            if led:
                out.append(f'<circle cx="{px + pw - 4}" cy="{py - 3}" r="1.8" fill="{"#22C55E" if (r + c) % 3 else "#F59E0B"}"/>')
    return "".join(out)


def server():
    bays = "".join(
        f'<g><rect x="{150 + i * 62}" y="352" width="56" height="110" rx="4" fill="#1A222D" stroke="#465364"/>'
        f'<rect x="{156 + i * 62}" y="360" width="44" height="74" rx="2" fill="#262F3C"/>'
        + "".join(f'<rect x="{160 + i * 62}" y="{366 + k * 8}" width="36" height="3" fill="#3A4554"/>' for k in range(8))
        + f'<circle cx="{170 + i * 62}" cy="448" r="3" fill="#22C55E"/><circle cx="{184 + i * 62}" cy="448" r="3" fill="{ACCENT}"/></g>'
        for i in range(8)
    )
    inner = f'''
<path d="M100 300 L700 300 L720 330 L80 330 Z" fill="#4A5566"/>
<rect x="80" y="330" width="640" height="160" rx="10" fill="url(#metal)"/>
<rect x="96" y="342" width="44" height="136" rx="6" fill="#121821"/>
<circle cx="118" cy="368" r="9" fill="none" stroke="url(#blue)" stroke-width="3"/>
<rect x="104" y="392" width="28" height="4" fill="#3E4A5B"/><rect x="104" y="402" width="28" height="4" fill="#3E4A5B"/>
{bays}
<rect x="660" y="342" width="44" height="136" rx="6" fill="#121821"/>
<rect x="670" y="356" width="24" height="44" rx="3" fill="url(#blue)" opacity=".9"/>
<rect x="80" y="486" width="640" height="6" fill="url(#blue)" opacity=".85"/>'''
    return product_frame(inner, "2U RACK SERVER")


def switch(label="48-PORT SWITCH", sfp=4, color_band=True):
    inner = f'''
<path d="M70 352 L730 352 L748 372 L52 372 Z" fill="#4A5566"/>
<rect x="52" y="372" width="696" height="96" rx="8" fill="url(#metal)"/>
<rect x="64" y="384" width="54" height="72" rx="5" fill="#141A23"/>
<circle cx="80" cy="402" r="4" fill="#22C55E"/><circle cx="96" cy="402" r="4" fill="{ACCENT}"/>
<rect x="72" y="420" width="38" height="4" fill="#3A4554"/><rect x="72" y="430" width="28" height="4" fill="#3A4554"/>
{ports(132, 390, 24, 2, pw=19, ph=16, gap=3)}
{"".join(f'<rect x="{620 + i * 30}" y="392" width="24" height="56" rx="3" fill="#0B1220" stroke="#5B6779"/><rect x="{625 + i * 30}" y="398" width="14" height="10" fill="#2B3543"/>' for i in range(sfp))}
{'<rect x="52" y="462" width="696" height="6" fill="url(#blue)" opacity=".85"/>' if color_band else ''}'''
    return product_frame(inner, label)


def firewall():
    inner = f'''
<path d="M150 330 L650 330 L672 356 L128 356 Z" fill="#4A5566"/>
<rect x="128" y="356" width="544" height="130" rx="12" fill="url(#metal)"/>
<rect x="146" y="376" width="150" height="90" rx="8" fill="#121821"/>
<path d="M221 392 l-34 13v19c0 17 14 30 34 34 20-4 34-17 34-34v-19z" fill="none" stroke="url(#blue)" stroke-width="5"/>
<path d="m206 425 11 11 20-22" fill="none" stroke="{ACCENT}" stroke-width="5" stroke-linecap="round"/>
{ports(320, 390, 8, 2, pw=26, ph=22, gap=6)}
<rect x="590" y="390" width="62" height="22" rx="3" fill="#0B1220" stroke="#5B6779"/>
<rect x="590" y="420" width="62" height="22" rx="3" fill="#0B1220" stroke="#5B6779"/>
<rect x="128" y="480" width="544" height="6" fill="#DC2626" opacity=".75"/>'''
    return product_frame(inner, "NEXT-GEN FIREWALL")


def router():
    inner = f'''
<path d="M170 340 L630 340 L652 366 L148 366 Z" fill="#4A5566"/>
<rect x="148" y="366" width="504" height="110" rx="12" fill="url(#metal)"/>
{ports(176, 392, 10, 1, pw=26, ph=22, gap=8)}
<rect x="176" y="430" width="140" height="22" rx="3" fill="#121821"/>
<text x="246" y="446" text-anchor="middle" font-family="Consolas,monospace" font-size="13" fill="{ACCENT}">CORE ROUTER</text>
{"".join(f'<rect x="{530 + i * 34}" y="390" width="26" height="58" rx="3" fill="#0B1220" stroke="#5B6779"/>' for i in range(3))}
<rect x="148" y="470" width="504" height="6" fill="url(#blue)" opacity=".85"/>'''
    return product_frame(inner, "CLOUD CORE ROUTER")


def ssd():
    inner = f'''
<rect x="230" y="180" width="340" height="440" rx="18" fill="url(#silver)" stroke="#8C99A9" stroke-width="2"/>
<rect x="262" y="214" width="276" height="300" rx="10" fill="{SECOND}"/>
<rect x="262" y="214" width="276" height="8" fill="url(#blue)"/>
<text x="400" y="320" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="58" font-weight="800" fill="#fff">SSD</text>
<text x="400" y="364" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="22" font-weight="600" fill="{ACCENT}">ENTERPRISE · 3.84 TB</text>
<rect x="300" y="404" width="200" height="4" fill="#2B3543"/><rect x="300" y="418" width="160" height="4" fill="#2B3543"/><rect x="300" y="432" width="180" height="4" fill="#2B3543"/>
<rect x="300" y="460" width="80" height="30" fill="#fff" opacity=".9"/>
{"".join(f'<rect x="{302 + i * 4}" y="464" width="{2 if i % 3 else 1}" height="22" fill="#0B1220"/>' for i in range(19))}
<circle cx="252" cy="200" r="7" fill="#9AA7B6"/><circle cx="548" cy="200" r="7" fill="#9AA7B6"/><circle cx="252" cy="600" r="7" fill="#9AA7B6"/><circle cx="548" cy="600" r="7" fill="#9AA7B6"/>
<rect x="300" y="610" width="200" height="16" rx="3" fill="#C9A227"/>'''
    return product_frame(inner, "ENTERPRISE SSD")


def sfp():
    inner = f'''
<g transform="rotate(-18 400 400)">
<rect x="160" y="340" width="420" height="120" rx="10" fill="url(#silver)" stroke="#8C99A9" stroke-width="2"/>
<rect x="160" y="340" width="420" height="22" fill="#E5ECF4"/>
<rect x="190" y="380" width="200" height="54" rx="6" fill="#fff" stroke="#C9D3DF"/>
<text x="290" y="404" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="18" font-weight="700" fill="{SECOND}">10G SFP+ SR</text>
<text x="290" y="424" text-anchor="middle" font-family="Consolas,monospace" font-size="13" fill="#64748B">850nm · 300m · LC</text>
<rect x="580" y="352" width="90" height="96" rx="6" fill="#1F2733"/>
<rect x="596" y="366" width="26" height="30" rx="4" fill="#0B1220"/><rect x="630" y="366" width="26" height="30" rx="4" fill="#0B1220"/>
<path d="M670 352 q70 0 70 48 t-70 48" fill="none" stroke="url(#blue)" stroke-width="12" stroke-linecap="round"/>
<rect x="130" y="370" width="30" height="60" rx="3" fill="#C9A227"/>
</g>'''
    return product_frame(inner, "SFP+ TRANSCEIVER")


def ram():
    chips = "".join(f'<rect x="{148 + i * 66}" y="356" width="52" height="56" rx="3" fill="#121821"/>' for i in range(8))
    pins = "".join(f'<rect x="{140 + i * 9}" y="470" width="5" height="22" fill="#C9A227"/>' for i in range(58))
    inner = f'''
<rect x="120" y="330" width="560" height="140" rx="6" fill="#0F5132"/>
<rect x="120" y="330" width="560" height="140" rx="6" fill="url(#metal)" opacity=".35"/>
{chips}
<rect x="320" y="420" width="160" height="34" rx="3" fill="#fff" opacity=".92"/>
<text x="400" y="443" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="16" font-weight="700" fill="{SECOND}">DDR5 RDIMM ECC</text>
{pins}
<rect x="392" y="468" width="16" height="26" fill="url(#bg)"/>'''
    return product_frame(inner, "DDR5 SERVER MEMORY")


def ups():
    inner = f'''
<rect x="250" y="160" width="300" height="460" rx="16" fill="url(#metal)"/>
<rect x="276" y="196" width="248" height="120" rx="8" fill="#0B1220"/>
<text x="400" y="246" text-anchor="middle" font-family="Consolas,monospace" font-size="30" fill="{ACCENT}">230V  98%</text>
<rect x="300" y="268" width="200" height="16" rx="3" fill="#1F2733"/><rect x="300" y="268" width="170" height="16" rx="3" fill="url(#blue)"/>
<circle cx="320" cy="360" r="16" fill="#121821" stroke="#22C55E" stroke-width="4"/>
<rect x="356" y="350" width="140" height="8" rx="3" fill="#3A4554"/><rect x="356" y="366" width="100" height="8" rx="3" fill="#3A4554"/>
{"".join(f'<rect x="282" y="{410 + i * 18}" width="236" height="8" rx="3" fill="#1A222D"/>' for i in range(10))}'''
    return product_frame(inner, "ONLINE UPS 3kVA")


def rackcab():
    units = "".join(
        f'<rect x="276" y="{176 + i * 42}" width="248" height="34" rx="3" fill="{"#1A222D" if i % 3 else "#222C38"}" stroke="#3A4554"/>'
        f'<circle cx="292" cy="{193 + i * 42}" r="3" fill="{"#22C55E" if i % 2 else ACCENT}"/>'
        for i in range(10)
    )
    inner = f'''
<rect x="240" y="130" width="320" height="520" rx="10" fill="url(#metal)"/>
<rect x="262" y="160" width="276" height="460" rx="6" fill="#0B1220"/>
{units}
<rect x="240" y="130" width="320" height="16" rx="6" fill="#4A5566"/>
<rect x="540" y="330" width="8" height="90" rx="3" fill="url(#blue)"/>'''
    return product_frame(inner, "42U SERVER RACK")


def nvme():
    inner = f'''
<g transform="rotate(-12 400 400)">
<rect x="130" y="350" width="540" height="110" rx="6" fill="#0B1220"/>
{"".join(f'<rect x="{180 + i * 120}" y="372" width="96" height="66" rx="4" fill="#1F2733" stroke="#3A4554"/>' for i in range(3))}
<rect x="540" y="372" width="70" height="66" rx="4" fill="#121821"/>
<rect x="130" y="350" width="540" height="6" fill="url(#blue)"/>
{"".join(f'<rect x="{612 + i * 7}" y="378" width="4" height="54" fill="#C9A227"/>' for i in range(8))}
<circle cx="150" cy="405" r="12" fill="url(#bg)" stroke="#9AA7B6" stroke-width="3"/>
</g>'''
    return product_frame(inner, "NVMe M.2 SSD")


def license_box():
    inner = f'''
<path d="M260 190 L540 190 L580 230 L580 620 L300 620 L260 580 Z" fill="#0B1220"/>
<path d="M540 190 L580 230 L580 620 L540 580 Z" fill="#1F2733"/>
<rect x="260" y="190" width="280" height="390" fill="url(#metal)"/>
<rect x="260" y="190" width="280" height="10" fill="url(#blue)"/>
<rect x="300" y="250" width="60" height="60" rx="10" fill="url(#blue)"/>
<path d="M318 280 h24 M330 268 v24" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
<text x="300" y="370" font-family="Inter,Segoe UI,Arial" font-size="34" font-weight="800" fill="#fff">Server OS</text>
<text x="300" y="404" font-family="Inter,Segoe UI,Arial" font-size="20" font-weight="500" fill="#93A4BD">Datacenter License</text>
<text x="300" y="440" font-family="Inter,Segoe UI,Arial" font-size="16" font-weight="600" fill="{ACCENT}">16-CORE · PERPETUAL</text>'''
    return product_frame(inner, "SOFTWARE LICENSE")


def fiber():
    inner = f'''
<path d="M180 520 C 260 200, 540 640, 620 280" fill="none" stroke="#F59E0B" stroke-width="16" stroke-linecap="round"/>
<path d="M200 540 C 280 220, 560 660, 640 300" fill="none" stroke="#38BDF8" stroke-width="16" stroke-linecap="round"/>
<rect x="140" y="500" width="70" height="44" rx="6" fill="#1F2733" transform="rotate(-30 175 522)"/>
<rect x="608" y="250" width="70" height="44" rx="6" fill="#1F2733" transform="rotate(-60 643 272)"/>
<rect x="112" y="510" width="34" height="24" rx="3" fill="#F3F6FA" transform="rotate(-30 129 522)"/>
<rect x="640" y="210" width="34" height="24" rx="3" fill="#F3F6FA" transform="rotate(-60 657 222)"/>'''
    return product_frame(inner, "OM4 LC-LC PATCH CORD")


def avatar(name, seed):
    c1, c2 = HUES[seed % len(HUES)]
    skin = ["#F1C7A5", "#D9A27E", "#B97F5A", "#8D5A3B", "#E8B996"][seed % 5]
    hair = ["#1F2937", "#3F2A1E", "#111827", "#5B3A29", "#2D2A26"][seed % 5]
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
<defs><linearGradient id="b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{c1}"/><stop offset="1" stop-color="{c2}"/></linearGradient>
<pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" stroke="#fff" stroke-opacity=".08"/></pattern></defs>
<rect width="600" height="600" fill="url(#b)"/><rect width="600" height="600" fill="url(#grid)"/>
<circle cx="470" cy="120" r="140" fill="#fff" opacity=".08"/>
<path d="M120 600 C 130 470, 210 420, 300 420 C 390 420, 470 470, 480 600 Z" fill="{SECOND}"/>
<path d="M255 420 L300 480 L345 420 Z" fill="#fff" opacity=".9"/>
<rect x="268" y="360" width="64" height="70" rx="20" fill="{skin}"/>
<ellipse cx="300" cy="290" rx="92" ry="104" fill="{skin}"/>
<path d="M208 282 C 200 190, 260 160, 304 166 C 360 160, 404 200, 394 286 C 380 240, 340 226, 300 228 C 258 228, 222 244, 208 282 Z" fill="{hair}"/>
</svg>'''
    write(os.path.join(DEMO, f"team-{name}.svg"), svg)


def client_logo(name, mark, seed):
    c1, c2 = HUES[seed % len(HUES)]
    marks = {
        "hex": '<path d="M22 4 38 13v18L22 40 6 31V13Z" fill="none" stroke="url(#c)" stroke-width="5"/>',
        "ring": '<circle cx="22" cy="22" r="15" fill="none" stroke="url(#c)" stroke-width="6"/><circle cx="22" cy="22" r="5" fill="url(#c)"/>',
        "bars": '<rect x="6" y="20" width="7" height="18" rx="2" fill="url(#c)"/><rect x="18" y="12" width="7" height="26" rx="2" fill="url(#c)"/><rect x="30" y="5" width="7" height="33" rx="2" fill="url(#c)"/>',
        "wave": '<path d="M4 26c6-10 12-10 18 0s12 10 18 0" fill="none" stroke="url(#c)" stroke-width="6" stroke-linecap="round"/>',
        "tri": '<path d="M22 5 39 37H5Z" fill="none" stroke="url(#c)" stroke-width="5" stroke-linejoin="round"/>',
        "plus": '<path d="M22 6v32M6 22h32" stroke="url(#c)" stroke-width="8" stroke-linecap="round"/>',
    }
    width = 64 + len(name) * 15
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} 44" width="{width}" height="44">
<defs><linearGradient id="c" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{c1}"/><stop offset="1" stop-color="{c2}"/></linearGradient></defs>
{marks[mark]}
<text x="52" y="30" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="22" font-weight="800" letter-spacing="-.5" fill="#334155">{name}</text>
</svg>'''
    slug = name.lower().replace(" ", "-")
    write(os.path.join(DEMO, f"client-{slug}.svg"), svg)


def brand_logo(dark=False):
    text = "#FFFFFF" if dark else SECOND
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 52" width="250" height="52">
<defs><linearGradient id="n" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{PRIMARY}"/><stop offset="1" stop-color="{ACCENT}"/></linearGradient></defs>
<rect x="2" y="4" width="44" height="44" rx="12" fill="url(#n)"/>
<path d="M14 36V16l20 20V16" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
<circle cx="34" cy="16" r="3.5" fill="#fff"/>
<text x="58" y="34" font-family="Plus Jakarta Sans,Inter,Segoe UI,Arial,sans-serif" font-size="25" font-weight="800" letter-spacing="-.6" fill="{text}">TechNova</text>
<text x="186" y="34" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="13" font-weight="600" letter-spacing="1.5" fill="{ACCENT}">SYSTEMS</text>
</svg>'''
    write(os.path.join(DEMO, "logo-technova-dark.svg" if dark else "logo-technova.svg"), svg)


def hero_network():
    rnd = random.Random(7)
    nodes = [(rnd.randint(90, 710), rnd.randint(80, 600)) for _ in range(16)]
    edges = []
    for i, (x, y) in enumerate(nodes):
        near = sorted(nodes, key=lambda n: (n[0] - x) ** 2 + (n[1] - y) ** 2)[1:3]
        for nx, ny in near:
            edges.append(f'<line x1="{x}" y1="{y}" x2="{nx}" y2="{ny}" stroke="{ACCENT}" stroke-opacity=".35" stroke-width="1.5"/>')
    node_svg = "".join(
        f'<circle cx="{x}" cy="{y}" r="{6 if i % 4 else 10}" fill="{ACCENT if i % 3 else "#fff"}" fill-opacity="{.9 if i % 4 else .95}"/>'
        f'<circle cx="{x}" cy="{y}" r="{18 if i % 4 else 26}" fill="{ACCENT}" fill-opacity=".08"/>'
        for i, (x, y) in enumerate(nodes)
    )
    rack_units = "".join(
        f'<rect x="300" y="{214 + i * 34}" width="200" height="26" rx="4" fill="{"#132238" if i % 2 else "#18293F"}" stroke="#2B4466"/>'
        f'<circle cx="316" cy="{227 + i * 34}" r="3.5" fill="{"#22C55E" if i % 3 else ACCENT}"/>'
        f'<rect x="330" y="{224 + i * 34}" width="{rnd.randint(60, 140)}" height="6" rx="3" fill="#2B4466"/>'
        for i in range(8)
    )
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 680" width="800" height="680">
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0A1A30"/><stop offset="1" stop-color="{DARK}"/></linearGradient>
<radialGradient id="glow" cx=".5" cy=".45" r=".55"><stop offset="0" stop-color="{PRIMARY}" stop-opacity=".75"/><stop offset="1" stop-color="{PRIMARY}" stop-opacity="0"/></radialGradient>
<pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" stroke="#9cc2ff" stroke-opacity=".08"/></pattern>
<linearGradient id="edge" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2B4466"/><stop offset="1" stop-color="#132238"/></linearGradient>
</defs>
<rect width="800" height="680" fill="url(#bg)"/>
<rect width="800" height="680" fill="url(#grid)"/>
<rect width="800" height="680" fill="url(#glow)"/>
{''.join(edges)}
{node_svg}
<rect x="282" y="180" width="236" height="318" rx="16" fill="#0B1729" stroke="url(#edge)" stroke-width="3"/>
<rect x="282" y="180" width="236" height="20" rx="10" fill="#1C3354"/>
{rack_units}
<rect x="300" y="490" width="200" height="4" rx="2" fill="{ACCENT}"/>
<ellipse cx="400" cy="520" rx="170" ry="16" fill="{ACCENT}" opacity=".16"/>
</svg>'''
    write(os.path.join(IMG, "hero-network.svg"), svg)


if __name__ == "__main__":
    covers = {
        # services
        "network-infrastructure": "network", "cloud-solutions": "cloud", "cybersecurity": "shield",
        "server-solutions": "server", "managed-it": "settings", "ai-solutions": "ai",
        "data-center": "rack", "software-development": "code", "it-consultancy": "consult",
        "backup-recovery": "backup", "vps-hosting": "hosting", "iot-solutions": "iot",
        # solutions
        "isp": "telecom", "banking": "bank", "healthcare": "health", "education": "education",
        "government": "government", "retail": "retail", "manufacturing": "factory", "enterprise": "chart",
        # case studies
        "case-network": "network", "case-cloud": "cloud", "case-soc": "lock", "case-datacenter": "rack",
        # blog
        "blog-zero-trust": "lock", "blog-kubernetes": "terminal", "blog-wifi7": "iot", "blog-backup": "backup",
        "blog-ai-ops": "ai", "blog-fiber": "fiber", "blog-woocommerce": "speed", "blog-linux": "terminal",
        "page": "chart",
    }
    for i, (name, icon) in enumerate(covers.items()):
        cover(name, icon, i)

    products = {
        "server": server(), "switch": switch(), "firewall": firewall(), "router": router(),
        "ssd": ssd(), "sfp": sfp(), "ram": ram(), "ups": ups(), "rack": rackcab(),
        "nvme": nvme(), "license": license_box(), "fiber": fiber(),
        "switch-core": switch("25G CORE SWITCH", sfp=4),
    }
    for name, svg in products.items():
        write(os.path.join(DEMO, f"product-{name}.svg"), svg)

    for i, n in enumerate(["amelia", "rafiq", "sofia", "daniel", "nadia", "kenji", "maya", "omar"]):
        avatar(n, i)

    for i, (n, m) in enumerate([
        ("Northwind Telecom", "wave"), ("Apex Capital", "bars"), ("Meridian Health", "plus"),
        ("Bluepeak Logistics", "tri"), ("Orbitel", "ring"), ("Helix University", "hex"),
        ("Quantum Retail", "bars"), ("Stratus Energy", "wave"),
    ]):
        client_logo(n, m, i)

    brand_logo(False)
    brand_logo(True)
    hero_network()
    print("ok", len(os.listdir(DEMO)), "demo files")
