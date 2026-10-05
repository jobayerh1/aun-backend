"""Turn the BD product pages into the aunstore.com (global) product packages.

For each model: reads the BD UX-Builder page + spec tab from the working dir and the BD
short description / gallery from the BD Store API, then writes
aunstore-core/data/products/<key>/{description.txt, specs.html, product.json}.

Global rules applied to every page:
  * new [aun_head] brand intro (replaces the legacy <h3> intro)
  * NO BD-channel videos: hero video button + "Watch It In Action" section removed
  * brightness = parent company's "ANSI Lumens" wording (decided by the parent company)
  * FAQ rebuilt for worldwide buyers (power, 1-year parts-shipped warranty, shipping, genuine check)
  * trust row = Worldwide Shipping / 1-Year Warranty / Video Repair Guides
Fails loudly if an expected block is missing or any BD-only wording survives.

Run:  python tools/globalize.py   then   python tools/build-media-map.py
"""
import json, pathlib, re, urllib.request

WORK = pathlib.Path(__file__).resolve().parents[2]            # the Claude-session working dir
OUT = pathlib.Path(__file__).resolve().parents[1] / "aunstore-core" / "data" / "products"
UA = {"User-Agent": "Mozilla/5.0 (aunstore globalize)"}


def bd_product(pid):
    req = urllib.request.Request(f"https://aun-projector.com.bd/wp-json/wc/store/v1/products/{pid}", headers=UA)
    with urllib.request.urlopen(req, timeout=40) as r:
        return json.load(r)


# ── Shared FAQ answers ───────────────────────────────────────────────────────
def faq_power(model, rng):
    return ("Will it work with the power supply in my country?",
            f"Yes. The {model} accepts {rng}, so it works on mains power anywhere in the world. "
            "If your wall socket is a different shape from the supplied plug, a simple plug adapter is all you need.")

def faq_warranty(model):
    return ("How does the warranty work if I live abroad?",
            f"Every {model} comes with a 1-year AUN warranty, and you never have to ship your projector back. "
            "Contact our support team with a short video of the problem. We diagnose it, our factory ships the "
            "replacement part straight to your address, and we share a step-by-step video tutorial so you can fit it "
            'yourself. See <a href="/warranty/">Warranty &amp; Support</a> for details.')

FAQ_SHIPPING = ("Do you ship to my country?",
                "We ship worldwide. Delivery time and shipping cost are shown at checkout once you enter your address. "
                "Your country's customs may charge import duties or taxes on arrival — see our "
                '<a href="/shipping-policy/">Shipping Policy</a>.')

FAQ_GENUINE = ("How do I know my projector is genuine?",
               "Every genuine AUN projector carries a 12-digit anti-counterfeit code on the bottom of the unit. "
               'Enter it at <a href="https://check.aunstore.com" target="_blank" rel="noopener">check.aunstore.com</a> '
               "to verify it instantly.")

APPS_NOTE = " App availability can vary by country."

DUST_FILTER = ("Will dust affect it?",
               "It has a removable, washable dust filter that blocks most dust before it reaches the optics, so build-up is "
               "far slower than in unprotected projectors. Clean the filter regularly, and if you ever wash it, let it dry "
               "completely before putting it back.")

DIM_ROOM = ("Can I use it in a bright room?",
            "It is designed for dim and dark rooms — close the curtains in the daytime and you get a vivid, cinema-like "
            "picture. If your room stays bright all day, consider a higher-brightness model like the U002 Pro or U001 Pro.")

# ── Copy clean-ups that apply wherever they appear ──────────────────────────
GENERIC_FIXES = [
    ("not mockups or manufacturer renders.", "not mockups or renders."),
    ("Built-in 2.4 and 5G wifi. You can share the content conveniently with wireless mirroring.",
     "Built-in dual-band 2.4 GHz / 5 GHz Wi-Fi lets you mirror your phone, tablet or laptop wirelessly."),
    ("Compatible with almost all audio and video output devices. Your computer, Amazon Firestick, TV BOX, Smartphone, PS4,PS5, XBOX, etc.",
     "Works with almost any video source — computers, Amazon Fire TV Stick, TV boxes, smartphones, PS4, PS5, Xbox and more."),
    ("Open up new gaming worlds, larger than 100” screen with frame rate upto 60fps. Enjoy gaming or binge-watching with friends.",
     "Open up new gaming worlds on a screen larger than 100&Prime;, at up to 60 fps. Enjoy gaming or binge-watching with friends."),
    ("TOF-Powered Auto Focus & Auto Keystone instantly adapt the image for ultra-fast, smooth, and precise screen correction.",
     "TOF-powered Auto Focus &amp; Auto Keystone instantly adapt the image for fast, smooth and precise screen correction."),
    ("TOF-Powered Auto Focus &amp; Auto Keystone instantly adapt the image for ultra-fast, smooth, and precise screen correction.",
     "TOF-powered Auto Focus &amp; Auto Keystone instantly adapt the image for fast, smooth and precise screen correction."),
    ("Built-in Stereo Speaker</strong>", "Built-in Stereo Speakers</strong>"),
    ("<h4>4 Point WARPING</h4>", "<h4>4-Point Warping</h4>"),
    ("With the improved 4 Point WARPING. you can adjust screen distortion and set up a more precise screen.",
     "Improved 4-point warping lets you correct screen distortion for a perfectly shaped picture."),
    ("No matter how you move it, you can automatically get a focused picture.",
     "Move it anywhere — the picture refocuses automatically."),
]

MODELS = [
    dict(key="u001-pro", throw=1.47, max_screen=300, finder_brightness=1000, order=1, model="U001 Pro", bd_id=8560, page="u001 pro product page.txt",
         specs="u001 pro product specification tab.txt", sku="APB-U001-PRO-BLK",
         name="AUN U001 Pro Full HD Genuine Android TV Projector, 4K Support", slug="aun-u001-pro-projector",
         cats=["Home Theater Projector", "Office Projector"], power="AC 90 – 260 V", lumens=2050,
         fixes=[
             ("The U001 Pro adopts the powerful 5.7 inch LCD screen. It delivers Full HD with deep, vivid shades and striking clarity, powered by AUN's advanced long-life LED light source.",
              "The U001 Pro uses a powerful 5.7-inch LCD panel. It delivers native Full HD with deep, vivid colors and striking clarity, powered by AUN's long-life LED light source."),
             ("AUN exclusive HDR technology heightens the HDR viewing experience with enhanced tone mapping, vivid darkness for hyper-realistic picture quality.",
              "AUN's HDR processing enhances tone mapping and deepens dark scenes for a lifelike, cinematic picture."),
             ("This prevent dust from accumulating on the surface of the optical elements inside the projector.",
              "A removable, washable dust filter keeps dust from settling on the optical elements inside the projector."),
             ("With built-in 4Ω5W*2 audio system, the compact U001 Pro delivers rich and immersive sound that you can feel. ",
              "With a built-in 2 × 5 W (4 Ω) stereo system, the compact U001 Pro delivers rich, immersive sound you can feel."),
             ("U001 Pro is designed with multiple connections, offering seamless connectivity across various devices such as tablet, smartphone, laptop, PC for versatility and convenience.",
              "Multiple connections let you plug in tablets, smartphones, laptops, PCs and game consoles for versatility and convenience."),
         ],
         faq=[
             ("Can I use it in a bright room?",
              "It looks its best in dim or dark rooms. In the daytime, just close the curtains for a vivid picture — like any home projector, direct sunlight will wash out the image."),
             ("How big a screen can I get?",
              "From 50&Prime; up to a giant 300&Prime; picture (1.6 – 9.8 m from the wall) — the further back you place it, the bigger the screen."),
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it runs Genuine Android TV 14 with official apps such as YouTube, Netflix and Prime Video, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone, tablet or laptop." + APPS_NOTE),
             DUST_FILTER,
         ]),
    dict(key="u002-pro", throw=1.28, max_screen=200, finder_brightness=850, order=2, model="U002 Pro", bd_id=8730, page="u002 product page.txt",
         specs="u002 product specification tab.txt", sku="APB-U002-PRO-SLV",
         name="AUN U002 Pro Full HD Dust-Proof Android Projector, TOF Laser Auto Focus, 4K Support", slug="aun-u002-pro-projector",
         cats=["Home Theater Projector", "Office Projector"], power="AC 100 – 240 V", lumens=1380,
         fixes=[
             ("With built-in 4Ω5W*2 audio system, the compact U002 Pro delivers rich and immersive sound that you can feel. ",
              "With a built-in 2 × 5 W (4 Ω) stereo system, the compact U002 Pro delivers rich, immersive sound you can feel."),
             ("U002 Pro is designed with multiple connections, offering seamless connectivity across various devices such as tablet, smartphone, laptop, PC for versatility and convenience.",
              "Multiple connections let you plug in tablets, smartphones, laptops, PCs and game consoles for versatility and convenience."),
         ],
         faq=[
             ("Can I use it in a bright room?",
              "It looks its best in dim or dark rooms. In the daytime, just close the curtains for a vivid picture — like any home projector, direct sunlight will wash out the image."),
             ("How far should I place it for a big screen?",
              "Anywhere from 1.4 m to 5.7 m, giving you a 50&Prime; to 200&Prime; picture. As a rule of thumb, around 2.8 m gives you a 100&Prime; cinema screen."),
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it runs Android 13 with preloaded apps like YouTube, Netflix and Prime Video, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone." + APPS_NOTE),
             ("Will dust affect it?",
              "Its optical engine is fully sealed and dust-proof, so dust cannot reach the picture-critical optics. No dust spots, no filter cleaning — maintenance-free by design."),
         ]),
    dict(key="a45-pro", throw=1.33, max_screen=150, finder_brightness=400, order=3, model="A45 Pro", bd_id=8522, page="A45 Pro Product Page.txt",
         specs="a45 pro product specification tab.txt", sku="APB-A45-PRO-GRY",
         name="AUN A45 Pro Full HD Genuine Android TV Home Cinema Projector", slug="aun-a45-pro-projector",
         cats=["Home Theater Projector"], power="AC 100 – 240 V", lumens=870,
         fixes=[
             ("AUN A45 Pro adopts 4 inch FHD LCD screen, which delivers stunning detail, vivid color, and beautifully immersive images that transport you from home to the local cineplex.",
              "The A45 Pro uses a 4-inch Full HD LCD panel that delivers stunning detail, vivid color and immersive images — like having a cinema at home."),
             ("Featuring a robust 10W HiFi stereo speaker which delivers exceptional audio quality with an expansive sound chamber for an immersive experience.",
              "A powerful 10 W hi-fi stereo speaker with an expansive sound chamber delivers rich, immersive audio."),
         ],
         faq=[
             DIM_ROOM,
             ("How big a screen can I get?",
              "From 47&Prime; up to a 150&Prime; picture (1.39 – 4.45 m from the wall) — the further back you place it, the bigger the screen."),
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it runs Genuine Android TV 11 with official apps like YouTube, Netflix and Prime Video, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone, tablet or laptop." + APPS_NOTE),
             DUST_FILTER,
         ]),
    dict(key="a005", throw=1.1, max_screen=150, finder_brightness=350, order=4, model="A005", bd_id=6688, page="a005 product page.txt",
         specs="a005 product specification tab.txt", sku="APB-A005",
         name="AUN A005 Short Throw Full HD Home Cinema Android Projector", slug="aun-a005-projector",
         cats=["Home Theater Projector"], power="AC 100 – 240 V", lumens=760,
         fixes=[
             ("Powered by a 3.5-inch LCD, the AUN A005 brings you true Full HD resolution with vivid details, and crystal-clear clarity.",
              "Powered by a 3.5-inch LCD, the A005 brings you true Full HD resolution with vivid detail and crystal-clear clarity."),
         ],
         faq=[
             ("My room is small — will it still give a big picture?",
              "Yes — that is exactly what the A005 is built for. Its 1.1:1 short-throw lens delivers a 100&Prime; picture from just 2.4 m, so even a compact bedroom becomes a cinema. Full range: 50&Prime; to 150&Prime; from 1.16 – 3.5 m."),
             DIM_ROOM,
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it runs Android 12 with YouTube, Netflix and Prime Video preloaded, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone, tablet or laptop." + APPS_NOTE),
             DUST_FILTER,
         ]),
    dict(key="a004-pro", throw=1.33, max_screen=150, finder_brightness=350, order=5, model="A004 Pro", bd_id=5232, page="a004 pro product page.txt",
         specs="a004 pro product specification tab.txt", sku="APB-A004-Pro-WHT",
         name="AUN A004 Pro Full HD Home Cinema Android Projector", slug="aun-a004-pro-projector",
         cats=["Home Theater Projector"], power="AC 100 – 240 V", lumens=730,
         fixes=[
             ("AUN A004 Pro adopts 4 inch FHD LCD screen, which delivers stunning detail, vivid color, and beautifully immersive images that transport you from home to the local cineplex.",
              "The A004 Pro uses a 4-inch Full HD LCD panel that delivers stunning detail, vivid color and immersive images — like having a cinema at home."),
             ("Featuring a robust 5W HiFi stereo speaker which delivers exceptional audio quality with an expansive sound chamber for an immersive experience.",
              "A powerful 5 W hi-fi speaker with an expansive sound chamber delivers rich, immersive audio."),
         ],
         faq=[
             DIM_ROOM,
             ("How big a screen can I get?",
              "From 47&Prime; up to a 150&Prime; picture (1.39 – 4.45 m from the wall) — the further back you place it, the bigger the screen."),
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it has a built-in Android system with YouTube, Netflix and Prime Video preloaded, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone, tablet or laptop." + APPS_NOTE),
             DUST_FILTER,
         ]),
    dict(key="a32-pro", throw=1.2, max_screen=100, finder_brightness=150, order=6, model="A32 Pro", bd_id=5852, page="a32 pro product page.txt",
         specs="a32 pro product specification tab.txt", sku="APB-A32-Pro-BLK",
         name="AUN A32 Pro HD Portable Android Projector", slug="aun-a32-pro-projector",
         cats=["Mini Projector"], power="AC 100 – 240 V", lumens=460,
         fixes=[
             ("AUN A32 Pro adopts the newest 2.7 inch HD LCD screen which heightens the viewing experience with enhanced tone mapping, vivid darkness for hyper-realistic picture quality.",
              "The A32 Pro uses a 2.7-inch HD LCD panel with enhanced tone mapping and deep, vivid dark scenes for a lifelike picture."),
             ("Adjusts the image in seconds. Press the remote control to easily achieve a focused picture.",
              "Get a sharp picture in seconds — just press a button on the remote to focus."),
         ],
         faq=[
             ("Is it really portable?",
              "Yes — at just 0.58 kg and barely bigger than your palm, the A32 Pro fits in any bag. Take it to a friend's house, on holiday or to the office, plug it into any socket (AC 100 – 240 V), and project a 17&Prime; to 100&Prime; picture from as little as 0.5 m away."),
             ("Can I use it in a bright room?",
              "The A32 Pro is made for dark rooms — turn the lights off and you get a clear, enjoyable picture. If you need daytime or lights-on viewing, consider a brighter model like the A45 Pro or U002 Pro."),
             ("Can I watch YouTube or Netflix directly?",
              "Yes — it runs Android 13 with YouTube, Netflix and other apps, plus Wi-Fi screen mirroring (AirPlay &amp; Miracast) from your phone, tablet or laptop." + APPS_NOTE),
             ("Will dust affect it?",
              "The A32 Pro uses standard ventilation without a dedicated dust filter, so a little care goes a long way: keep it covered when not in use and gently clean the vents now and then. For built-in dust protection, the A45 Pro has a washable dust filter and the U002 Pro has a fully sealed dust-proof engine."),
         ]),
]

INTRO = """[section padding="30px" padding__sm="20px"]

[row style="collapse" v_align="middle"]

[col span__sm="12" align="center"]

[featured_box img="1557" inline_svg="0" img_width="70" pos="center"]


[/featured_box]

<p>[aun_head title="See the World Through a Larger Screen" sub="Cinema-grade visuals and effortless smart setup — shipped to your door, worldwide."]</p>

[/col]

[/row]

[/section]
"""

TRUST = """[section label="trust/assurance row" bg="linear-gradient(135deg,#0188fe,#00c6ff)" dark="true" padding="40px"]

[row h_align="center"]

[col span="4" span__sm="12" align="center"]

<p><i class="fa-solid fa-earth-americas" style="font-size:32px; color:#fff;"></i></p>
<h4 style="color:#fff; margin-bottom:5px;">Worldwide Shipping</h4>
<p style="color:#e6e6e6; font-size:14px;">Delivered to your door, wherever you are</p>

[/col]
[col span="4" span__sm="12" align="center"]

<p><i class="fa-solid fa-shield-halved" style="font-size:32px; color:#fff;"></i></p>
<h4 style="color:#fff; margin-bottom:5px;">1-Year Warranty</h4>
<p style="color:#e6e6e6; font-size:14px;">Spare parts shipped from our factory to you</p>

[/col]
[col span="4" span__sm="12" align="center"]

<p><i class="fa-solid fa-screwdriver-wrench" style="font-size:32px; color:#fff;"></i></p>
<h4 style="color:#fff; margin-bottom:5px;">Video Repair Guides</h4>
<p style="color:#e6e6e6; font-size:14px;">Step-by-step help from our engineers</p>

[/col]

[/row]

[/section]
"""

FORBIDDEN = re.compile(r"Bangladesh|Dhaka|\bEMI\b|bKash|Nagad|\bCOD\b|cash on delivery|village|৳|Manufacturer|Rated\)|"
                       r"brightness-explained|throw-distance calculator|youtube\.com|video_button|aun_shorts|aun_video|"
                       r"See it live|register your warranty|service center", re.I)


def replace_block(s, start_marker, end_marker, new, what):
    i = s.find(start_marker)
    assert i >= 0, f"{what}: start marker not found"
    j = s.find(end_marker, i)
    assert j >= 0, f"{what}: end marker not found"
    return s[:i] + new + s[j + len(end_marker):]


def remove_section_containing(s, needle, what):
    k = s.find(needle)
    assert k >= 0, f"{what}: not found"
    i = s.rfind("[section", 0, k)
    j = s.find("[/section]", k)
    assert i >= 0 and j >= 0, f"{what}: section bounds not found"
    end = j + len("[/section]")
    while end < len(s) and s[end] == "\n":
        end += 1
    return s[:i] + s[end:]


def build_page(m):
    s = (WORK / m["page"]).read_text(encoding="utf-8").replace("\r\n", "\n")

    # 1. legacy brand intro -> [aun_head] intro
    first_end = s.index("[/section]") + len("[/section]")
    assert 'img="1557"' in s[:first_end], "intro: logo not in first section"
    s = INTRO + s[first_end:].lstrip("\n")

    # 2. no BD-channel videos
    s = re.sub(r'\[text_box[^\]]*\]\s*\[video_button[^\]]*\]\s*\[/text_box\]\n?', "", s)
    s = remove_section_containing(s, 'eyebrow="See it live"', "watch-in-action")

    # 3. brightness wording (key stats band)
    s = s.replace(">Lumens (Rated)<", ">ANSI Lumens<")

    # 4. copy fixes
    for old, new in m["fixes"]:
        assert s.count(old) == 1, f"fix not found exactly once: {old[:60]}"
        s = s.replace(old, new)
    for old, new in GENERIC_FIXES:
        s = s.replace(old, new)
    s = s.replace("<p style=\"color:#666; font-size:14px;\">Universal compatibility</p>",
                  f"<p style=\"color:#666; font-size:14px;\">Universal {m['power'].replace('AC ', '')} power</p>")

    # 5. FAQ for worldwide buyers
    faq = m["faq"] + [faq_power(m["model"], m["power"]), faq_warranty(m["model"]), FAQ_SHIPPING, FAQ_GENUINE]
    items = "".join(f'[accordion-item title="{q}"]\n\n<p>{a}</p>\n\n[/accordion-item]\n' for q, a in faq)
    s = replace_block(s, "[accordion]", "[/accordion]", "[accordion]\n\n" + items + "\n[/accordion]", "faq")

    # 6. trust row
    s = replace_block(s, '[section label="trust/assurance row"', "[/section]", TRUST.rstrip("\n"), "trust row")
    return s.rstrip("\n") + "\n"


def build_specs(m):
    s = (WORK / m["specs"]).read_text(encoding="utf-8").replace("\r\n", "\n")
    n = m["lumens"]
    s, k1 = re.subn(rf'(<div style="font-weight:700;margin:6px 0 2px;color:#0c2a4a">){n} Lumens(</div>\s*<div style="font-size:13px;color:#4a6a8a">)Rated brightness(</div>)',
                    rf"\g<1>{n} ANSI\g<2>Lumens\g<3>", s)
    s, k2 = re.subn(rf'Brightness \(Manufacturer Rated\)</td>\s*<td style="padding: 8px">{n} Lumens &nbsp;<a [^>]*>What does this rating mean\? →</a>',
                    f'Brightness</td>\n<td style="padding: 8px">{n} ANSI Lumens', s)
    assert (k1, k2) == (1, 1), f"specs brightness rows not found ({k1},{k2})"
    return s


def build_highlights(m, bd):
    """Structured facts for the homepage lineup explorer (parsed from the BD short description)."""
    s = bd["short_description"]
    best = re.search(r"Best for:\s*([^<]+)</p>", s)
    li = dict(re.findall(r"<li>([^:<]+):\s*([^<]+)</li>", s))
    res = li.get("Native Resolution", "")
    os_name = li.get("Operating System", "").replace("Genuine ", "").strip()
    tags = []
    if "Home Theater Projector" in m["cats"]: tags.append("home")
    if "Office Projector" in m["cats"]: tags.append("office")
    if "Mini Projector" in m["cats"]: tags.append("portable")
    if m["throw"] <= 1.15: tags.append("small")
    assert best and res and os_name, f"{m['key']}: could not parse highlights"
    return {
        "short": m["model"],
        "best_for": best.group(1).strip(),
        "resolution": "Full HD 1080p" if "Full HD" in res else "HD 720p",
        "res_note": "4K support" if "4K" in res else "",
        "lumens": m["lumens"],
        "os": os_name,
        "max_screen": m["max_screen"],
        "tags": tags,
    }


def build_short(m, bd):
    s = bd["short_description"].strip()
    s, k = re.subn(rf"Brightness: {m['lumens']} Lumens \(Manufacturer Rated\)", f"Brightness: {m['lumens']} ANSI Lumens", s)
    assert k == 1, "short description brightness line not found"
    assert s.endswith("</ul>"), "short description does not end with a list"
    return s[: -len("</ul>")] + f"<li>Power: {m['power']} (works worldwide)</li>\n</ul>"


for m in MODELS:
    bd = bd_product(m["bd_id"])
    page, specs, short = build_page(m), build_specs(m), build_short(m, bd)
    for label, text in (("page", page), ("specs", specs), ("short", short)):
        bad = FORBIDDEN.findall(text)
        assert not bad, f"{m['key']} {label}: BD-only wording left: {sorted(set(bad))}"
    assert "ANSI" in page + specs + short
    d = OUT / m["key"]
    d.mkdir(parents=True, exist_ok=True)
    (d / "description.txt").write_text(page, encoding="utf-8", newline="\n")
    (d / "specs.html").write_text(specs, encoding="utf-8", newline="\n")
    (d / "product.json").write_text(json.dumps({
        "sku": m["sku"], "name": m["name"], "slug": m["slug"], "categories": m["cats"],
        "images": [img["id"] for img in bd["images"]], "short_description": short,
        "menu_order": m["order"],
        # Tool data, same values the BD site uses. _aun_internal_brightness is the real-world
        # figure the Smart Finder matches rooms with; it is never displayed on the site.
        "meta": {"_aun_throw_ratio": m["throw"], "_aun_max_screen_size": m["max_screen"],
                 "_aun_internal_brightness": m["finder_brightness"],
                 "_aunstore_highlights": build_highlights(m, bd)},
    }, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")
    print(f"{m['key']:9} ok  {len(page):6} chars  {len(bd['images'])} images")
