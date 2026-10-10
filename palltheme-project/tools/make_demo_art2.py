"""
Second batch of ORIGINAL demo artwork for the full TechNova demo:
product illustrations for the 42-item catalogue (generic devices, no vendor
logos), fictional client marks, testimonial avatars and an original Lottie
animation. Copyright (c) 2026 Engr. Nazim U Ahmed. All rights reserved.
"""
import json
import os

import make_demo_art as A

DEMO, IMG, ACCENT, PRIMARY, SECOND = A.DEMO, A.IMG, A.ACCENT, A.PRIMARY, A.SECOND
LOTTIE_DIR = os.path.join(A.ROOT, "palltheme-core", "assets", "lottie")
os.makedirs(LOTTIE_DIR, exist_ok=True)
F = A.product_frame


def relabel(svg, old, new):
    return svg.replace(old, new)


def server1u():
    bays = "".join(
        f'<rect x="{150 + i * 50}" y="372" width="44" height="40" rx="3" fill="#1A222D" stroke="#465364"/>'
        f'<circle cx="{160 + i * 50}" cy="402" r="2.5" fill="#22C55E"/><rect x="{168 + i * 50}" y="380" width="20" height="3" fill="#3A4554"/>'
        for i in range(10))
    inner = f'''<path d="M100 340 L700 340 L720 360 L80 360 Z" fill="#4A5566"/>
<rect x="80" y="360" width="640" height="68" rx="8" fill="url(#metal)"/>
<rect x="96" y="368" width="44" height="52" rx="5" fill="#121821"/><circle cx="118" cy="388" r="7" fill="none" stroke="url(#blue)" stroke-width="3"/>
{bays}<rect x="660" y="368" width="44" height="52" rx="5" fill="#121821"/><rect x="668" y="378" width="28" height="30" rx="3" fill="url(#blue)" opacity=".9"/>
<rect x="80" y="424" width="640" height="5" fill="url(#blue)" opacity=".85"/>'''
    return F(inner, "1U VIRTUALIZATION HOST")


def tower():
    inner = f'''<rect x="270" y="150" width="260" height="470" rx="18" fill="url(#metal)"/>
<rect x="292" y="176" width="216" height="140" rx="8" fill="#121821"/>
{"".join(f'<rect x="306" y="{190 + i * 30}" width="188" height="20" rx="3" fill="#1F2733" stroke="#3A4554"/><circle cx="318" cy="{200 + i * 30}" r="2.5" fill="#22C55E"/>' for i in range(4))}
{"".join(f'<rect x="300" y="{350 + i * 14}" width="200" height="6" rx="3" fill="#1A222D"/>' for i in range(14))}
<circle cx="400" cy="560" r="14" fill="#121821" stroke="url(#blue)" stroke-width="4"/>
<rect x="270" y="610" width="260" height="10" rx="5" fill="url(#blue)" opacity=".8"/>'''
    return F(inner, "TOWER SERVER")


def nic(label="25GbE DUAL-PORT ADAPTER"):
    inner = f'''<rect x="150" y="300" width="460" height="200" rx="10" fill="#0F5132"/>
<rect x="150" y="300" width="460" height="200" rx="10" fill="url(#metal)" opacity=".4"/>
<rect x="300" y="350" width="110" height="100" rx="8" fill="#121821"/><rect x="316" y="366" width="78" height="68" rx="4" fill="url(#blue)" opacity=".85"/>
{"".join(f'<rect x="{430 + i * 26}" y="360" width="16" height="16" rx="2" fill="#121821"/>' for i in range(5))}
<rect x="610" y="270" width="40" height="260" rx="4" fill="url(#silver)" stroke="#8C99A9"/>
<rect x="618" y="320" width="28" height="60" rx="3" fill="#0B1220"/><rect x="618" y="410" width="28" height="60" rx="3" fill="#0B1220"/>
{"".join(f'<rect x="{180 + i * 9}" y="500" width="5" height="26" fill="#C9A227"/>' for i in range(30))}'''
    return F(inner, label)


def qsfp():
    svg = A.sfp()
    svg = svg.replace("10G SFP+ SR", "100G QSFP28 SR4").replace("850nm · 300m · LC", "850nm · 100m · MPO").replace("SFP+ TRANSCEIVER", "QSFP28 TRANSCEIVER")
    return svg.replace('height="120" rx="10" fill="url(#silver)"', 'height="150" rx="10" fill="url(#silver)"')


def mpo():
    inner = f'''<circle cx="400" cy="400" r="170" fill="none" stroke="#38BDF8" stroke-width="22"/>
<circle cx="400" cy="400" r="130" fill="none" stroke="#38BDF8" stroke-width="22" opacity=".85"/>
<circle cx="400" cy="400" r="90" fill="none" stroke="#38BDF8" stroke-width="22" opacity=".7"/>
<path d="M560 330 C 620 300, 650 260, 660 200" fill="none" stroke="#38BDF8" stroke-width="22" stroke-linecap="round"/>
<rect x="630" y="140" width="64" height="70" rx="8" fill="#1F2733"/><rect x="642" y="120" width="40" height="24" rx="3" fill="#94A3B8"/>
<path d="M240 470 C 180 520, 160 560, 150 610" fill="none" stroke="#38BDF8" stroke-width="22" stroke-linecap="round"/>
<rect x="118" y="606" width="64" height="70" rx="8" fill="#1F2733"/><rect x="130" y="672" width="40" height="22" rx="3" fill="#94A3B8"/>'''
    return F(inner, "MPO-12 TRUNK CABLE")


def hdd(label="18TB", sub="ENTERPRISE HDD"):
    inner = f'''<rect x="240" y="160" width="320" height="470" rx="14" fill="url(#silver)" stroke="#8C99A9" stroke-width="2"/>
<rect x="270" y="190" width="260" height="250" rx="10" fill="{SECOND}"/>
<rect x="270" y="190" width="260" height="8" fill="url(#blue)"/>
<text x="400" y="300" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="64" font-weight="800" fill="#fff">{label}</text>
<text x="400" y="342" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="20" font-weight="600" fill="{ACCENT}">{sub}</text>
<rect x="300" y="372" width="200" height="4" fill="#2B3543"/><rect x="300" y="386" width="150" height="4" fill="#2B3543"/>
<circle cx="400" cy="530" r="62" fill="#D7DEE7" stroke="#9AA7B6" stroke-width="3"/><circle cx="400" cy="530" r="14" fill="#9AA7B6"/>
{"".join(f'<circle cx="{x}" cy="{y}" r="6" fill="#9AA7B6"/>' for x, y in ((262, 182), (538, 182), (262, 608), (538, 608)))}'''
    return F(inner, sub)


def nas():
    bays = "".join(
        f'<rect x="{170 + c * 116}" y="{320 + r * 76}" width="106" height="66" rx="5" fill="#1A222D" stroke="#465364"/>'
        f'<circle cx="{186 + c * 116}" cy="{353 + r * 76}" r="3" fill="#22C55E"/>'
        f'{"".join(f"<rect x=\'{200 + c * 116}\' y=\'{334 + r * 76 + k * 10}\' width=\'64\' height=\'3\' fill=\'#3A4554\'/>" for k in range(4))}'
        for r in range(2) for c in range(4))
    inner = f'''<path d="M120 280 L680 280 L700 300 L100 300 Z" fill="#4A5566"/>
<rect x="100" y="300" width="600" height="180" rx="10" fill="url(#metal)"/>
<rect x="116" y="316" width="44" height="148" rx="5" fill="#121821"/><rect x="124" y="330" width="28" height="18" rx="2" fill="url(#blue)"/>
{bays}<rect x="100" y="476" width="600" height="5" fill="url(#blue)" opacity=".85"/>'''
    return F(inner, "8-BAY BACKUP APPLIANCE")


def cpu(label="SERVER CPU", sub="32 CORES", tone="#9AA7B6"):
    inner = f'''<rect x="200" y="200" width="400" height="400" rx="18" fill="#0F5132"/>
{"".join(f'<circle cx="{220 + (i % 19) * 20}" cy="{220 + (i // 19) * 20}" r="3" fill="#C9A227"/>' for i in range(19 * 19) if (i % 19 in (0, 18) or i // 19 in (0, 18)))}
<rect x="250" y="250" width="300" height="300" rx="14" fill="url(#silver)" stroke="{tone}" stroke-width="3"/>
<text x="400" y="390" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="34" font-weight="800" fill="{SECOND}">{label}</text>
<text x="400" y="432" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="22" font-weight="600" fill="#475569">{sub}</text>
<circle cx="275" cy="525" r="8" fill="{tone}"/>'''
    return F(inner, label)


def gpu():
    fins = "".join(f'<rect x="{150 + i * 14}" y="330" width="8" height="120" fill="#2B3543"/>' for i in range(30))
    inner = f'''<rect x="120" y="310" width="540" height="160" rx="12" fill="url(#metal)"/>{fins}
<rect x="120" y="310" width="540" height="12" rx="6" fill="url(#blue)"/>
<rect x="580" y="350" width="64" height="80" rx="6" fill="#121821"/><text x="612" y="398" text-anchor="middle" font-family="Inter,Arial" font-size="16" font-weight="800" fill="{ACCENT}">48GB</text>
<rect x="660" y="290" width="36" height="200" rx="4" fill="url(#silver)" stroke="#8C99A9"/>
{"".join(f'<rect x="{170 + i * 9}" y="470" width="5" height="26" fill="#C9A227"/>' for i in range(36))}'''
    return F(inner, "DATA CENTER GPU")


def pdu():
    outlets = "".join(f'<rect x="372" y="{220 + i * 34}" width="56" height="24" rx="4" fill="#0B1220" stroke="#3A4554"/>' for i in range(11))
    inner = f'''<rect x="340" y="130" width="120" height="560" rx="12" fill="url(#metal)"/>
<rect x="356" y="148" width="88" height="48" rx="6" fill="#0B1220"/><text x="400" y="180" text-anchor="middle" font-family="Consolas,monospace" font-size="20" fill="{ACCENT}">8.4A</text>
{outlets}<rect x="340" y="680" width="120" height="10" rx="5" fill="url(#blue)" opacity=".8"/>'''
    return F(inner, "METERED RACK PDU")


def nms():
    inner = f'''<path d="M150 340 L650 340 L670 360 L130 360 Z" fill="#4A5566"/>
<rect x="130" y="360" width="540" height="76" rx="8" fill="url(#metal)"/>
<rect x="150" y="372" width="170" height="52" rx="5" fill="#0B1220"/>
<polyline points="160,410 190,396 214,404 240,384 268,392 300,380" fill="none" stroke="{ACCENT}" stroke-width="3"/>
{A.ports(350, 386, 4, 1, pw=26, ph=22, gap=8)}
<circle cx="610" cy="398" r="10" fill="#121821" stroke="#22C55E" stroke-width="3"/>
<rect x="130" y="432" width="540" height="5" fill="url(#blue)" opacity=".85"/>'''
    return F(inner, "NETWORK MANAGEMENT APPLIANCE")


def ap():
    inner = f'''<ellipse cx="400" cy="420" rx="230" ry="70" fill="#C9D3DF"/>
<ellipse cx="400" cy="400" rx="230" ry="70" fill="url(#silver)" stroke="#9AA7B6" stroke-width="2"/>
<ellipse cx="400" cy="400" rx="60" ry="18" fill="none" stroke="url(#blue)" stroke-width="6"/>
<path d="M330 300 a100 100 0 0 1 140 0 M355 325 a60 60 0 0 1 90 0" fill="none" stroke="{ACCENT}" stroke-width="10" stroke-linecap="round"/>'''
    return F(inner, "WI-FI 6E ACCESS POINT")


def service_card(icon, title, sub):
    inner = f'''<rect x="170" y="170" width="460" height="460" rx="36" fill="{SECOND}"/>
<rect x="170" y="170" width="460" height="460" rx="36" fill="none" stroke="url(#blue)" stroke-width="6"/>
<circle cx="400" cy="340" r="96" fill="{PRIMARY}" opacity=".18"/>
<g transform="translate(340 280) scale(5)" fill="none" stroke="{ACCENT}" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">{A.ICONS[icon]}</g>
<text x="400" y="500" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="34" font-weight="800" fill="#fff">{title}</text>
<text x="400" y="545" text-anchor="middle" font-family="Inter,Segoe UI,Arial" font-size="20" font-weight="600" fill="{ACCENT}">{sub}</text>'''
    return F(inner, "PROFESSIONAL SERVICE")


def lottie_network_pulse():
    """Original Lottie: three connected nodes with pulsing rings (500x400, 3 s loop)."""
    def color(hexs, a=1):
        h = hexs.lstrip("#")
        return [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)] + [a]

    nodes = [(250, 110), (110, 300), (390, 300)]

    def ellipse_layer(ind, x, y, size, col, delay, pulse):
        if pulse:
            scale = {"a": 1, "k": [{"t": delay, "s": [60, 60, 100], "e": [160, 160, 100], "i": {"x": [0.3], "y": [1]}, "o": {"x": [0.6], "y": [0]}}, {"t": delay + 60}]}
            opacity = {"a": 1, "k": [{"t": delay, "s": [70], "e": [0], "i": {"x": [0.3], "y": [1]}, "o": {"x": [0.6], "y": [0]}}, {"t": delay + 60}]}
        else:
            scale = {"a": 0, "k": [100, 100, 100]}
            opacity = {"a": 0, "k": 100}
        shapes = [{"ty": "gr", "it": [
            {"ty": "el", "p": {"a": 0, "k": [0, 0]}, "s": {"a": 0, "k": [size, size]}},
            ({"ty": "st", "c": {"a": 0, "k": color(col)}, "o": {"a": 0, "k": 100}, "w": {"a": 0, "k": 3}} if pulse else {"ty": "fl", "c": {"a": 0, "k": color(col)}, "o": {"a": 0, "k": 100}}),
            {"ty": "tr", "p": {"a": 0, "k": [0, 0]}, "a": {"a": 0, "k": [0, 0]}, "s": {"a": 0, "k": [100, 100]}, "r": {"a": 0, "k": 0}, "o": {"a": 0, "k": 100}},
        ]}]
        return {"ddd": 0, "ind": ind, "ty": 4, "nm": f"node{ind}", "sr": 1, "ks": {"o": opacity, "r": {"a": 0, "k": 0}, "p": {"a": 0, "k": [x, y, 0]}, "a": {"a": 0, "k": [0, 0, 0]}, "s": scale}, "shapes": shapes, "ip": 0, "op": 90, "st": 0}

    layers, ind = [], 1
    for i, (x, y) in enumerate(nodes):
        layers.append(ellipse_layer(ind, x, y, 34, "#00A8FF", i * 20, True)); ind += 1
        layers.append(ellipse_layer(ind, x, y, 26, "#2567AD", 0, False)); ind += 1
    lines = [{"ty": "gr", "it": [
        {"ty": "sh", "ks": {"a": 0, "k": {"c": False, "v": [list(nodes[a]), list(nodes[b])], "i": [[0, 0], [0, 0]], "o": [[0, 0], [0, 0]]}}},
        {"ty": "st", "c": {"a": 0, "k": color("#00A8FF", 1)}, "o": {"a": 0, "k": 45}, "w": {"a": 0, "k": 3}, "d": [{"n": "d", "nm": "dash", "v": {"a": 0, "k": 10}}, {"n": "g", "nm": "gap", "v": {"a": 0, "k": 8}}, {"n": "o", "nm": "offset", "v": {"a": 1, "k": [{"t": 0, "s": [0], "e": [-72]}, {"t": 90}]}}]},
        {"ty": "tr", "p": {"a": 0, "k": [0, 0]}, "a": {"a": 0, "k": [0, 0]}, "s": {"a": 0, "k": [100, 100]}, "r": {"a": 0, "k": 0}, "o": {"a": 0, "k": 100}},
    ]} for a, b in ((0, 1), (0, 2), (1, 2))]
    layers.append({"ddd": 0, "ind": ind, "ty": 4, "nm": "links", "sr": 1, "ks": {"o": {"a": 0, "k": 100}, "r": {"a": 0, "k": 0}, "p": {"a": 0, "k": [0, 0, 0]}, "a": {"a": 0, "k": [0, 0, 0]}, "s": {"a": 0, "k": [100, 100, 100]}}, "shapes": lines, "ip": 0, "op": 90, "st": 0})
    anim = {"v": "5.7.4", "fr": 30, "ip": 0, "op": 90, "w": 500, "h": 400, "nm": "Palltheme network pulse (original)", "ddd": 0, "assets": [], "layers": layers}
    with open(os.path.join(LOTTIE_DIR, "network-pulse.json"), "w", encoding="utf-8") as f:
        json.dump(anim, f, separators=(",", ":"))


if __name__ == "__main__":
    base = {
        "server": A.server(), "switch": A.switch(), "firewall": A.firewall(), "router": A.router(), "ssd": A.ssd(), "sfp": A.sfp(),
        "ram": A.ram(), "ups": A.ups(), "rack": A.rackcab(), "nvme": A.nvme(), "license": A.license_box(), "fiber": A.fiber(),
        "switch-core": A.switch("25G DATA CENTER SWITCH", sfp=4),
    }
    extra = {
        "server-alt": relabel(A.server(), "2U RACK SERVER", "2U GENERAL-PURPOSE SERVER"),
        "server1u": server1u(), "tower": tower(),
        "switch10g": A.switch("24-PORT 10GbE SWITCH", sfp=4), "switch-poe": A.switch("24-PORT PoE+ SWITCH", sfp=4),
        "router-edge": relabel(A.router(), "CLOUD CORE ROUTER", "100G EDGE ROUTER").replace("CORE ROUTER</text>", "EDGE ROUTER</text>"),
        "firewall-desktop": relabel(A.firewall(), "NEXT-GEN FIREWALL", "BRANCH FIREWALL"),
        "nic": nic(), "nic-10g": nic("10GbE DUAL-PORT ADAPTER"),
        "sfp28": relabel(relabel(A.sfp(), "10G SFP+ SR", "25G SFP28 SR"), "SFP+ TRANSCEIVER", "SFP28 TRANSCEIVER"),
        "qsfp": qsfp(), "mpo": mpo(),
        "u2": relabel(relabel(A.ssd(), "ENTERPRISE · 3.84 TB", "NVMe U.2 · 1.92 TB"), "ENTERPRISE SSD", "ENTERPRISE NVMe SSD"),
        "hdd": hdd("18TB", "ENTERPRISE HDD"), "hdd-nas": hdd("12TB", "NAS HDD"), "nas": nas(),
        "ram-ddr4": relabel(relabel(A.ram(), "DDR5 RDIMM ECC", "DDR4 RDIMM 32GB"), "DDR5 SERVER MEMORY", "DDR4 SERVER MEMORY"),
        "cpu-intel": cpu("SERVER CPU", "32 CORES · DDR5", "#9AA7B6"), "cpu-amd": cpu("SERVER CPU", "32 CORES · 12-CH", "#F97316"),
        "gpu": gpu(), "pdu": pdu(), "nms": nms(), "ap": ap(),
        "license-epp": relabel(relabel(relabel(A.license_box(), "Server OS", "Endpoint"), "Datacenter License", "Protection Suite"), "16-CORE · PERPETUAL", "50 DEVICES · 1 YEAR"),
        "service-support": service_card("terminal", "Linux Support", "MONTHLY PLAN"),
        "service-managed": service_card("network", "Managed Network", "24/7 MONITORING"),
        "service-security": service_card("shield", "Security Review", "2-WEEK ASSESSMENT"),
        "service-cloud": service_card("cloud", "Cloud Migration", "STARTER PACKAGE"),
        "service-warranty": service_card("shield", "3-Year Warranty", "NEXT BUSINESS DAY"),
    }
    for name, svg in {**base, **extra}.items():
        A.write(os.path.join(DEMO, f"product-{name}.svg"), svg)

    clients = [("GlobalNet", "wave"), ("Apex Telecom", "tri"), ("Nova Retail", "bars"), ("BlueWave Logistics", "wave"), ("Primeline Bank", "plus"),
               ("EduCore", "hex"), ("DataSphere", "ring"), ("Helix Health", "plus"), ("Orbitel", "ring"), ("Stratus Energy", "bars")]
    for i, (n, m) in enumerate(clients):
        A.client_logo(n, m, i)
    for i in range(8):
        A.avatar(f"client-{i + 1}", i + 3)
    lottie_network_pulse()
    print("products:", len(base) + len(extra), "| clients:", len(clients), "| avatars: 8 | lottie: 1")
