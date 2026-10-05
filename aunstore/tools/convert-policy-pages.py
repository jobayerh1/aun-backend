"""Rewrite the BD-template policy pages (hero → 3 cards → [accordion] → note → help → last updated) into the
designed [aunp_*] blocks. The words stay exactly as they were; only the markup changes.

Originals are kept in tools/legacy-pages/. Run once; the results are then edited like any other page source.
"""
import html, pathlib, re, shutil

ROOT = pathlib.Path(__file__).resolve().parents[1]
PAGES = ROOT / "aunstore-core" / "data" / "pages"
LEGACY = ROOT / "tools" / "legacy-pages"
LEGACY.mkdir(exist_ok=True)

# Hand-set per page: eyebrow, title, accent, chips, where the steps band and the notes go.
META = {
    "warranty": dict(eyebrow="1-year warranty &middot; worldwide", title="Warranty &amp;", accent="support",
                     chips="Parts shipped to you|Video repair guides|No need to ship it back",
                     btn2=("Verify authenticity", "/verify-authenticity/", "fa-fingerprint"), doc_id="regulations"),
    "shipping-policy": dict(eyebrow="Worldwide delivery", title="Shipping &amp;", accent="delivery",
                            chips="Tracked parcels|Cost shown at checkout|Worldwide", doc_id="policy"),
    "returns-and-refunds": dict(eyebrow="Returns &amp; refunds", title="Returns &amp;", accent="refunds",
                                chips="Damaged on arrival|Faulty or wrong item|Refunds", doc_id="policy"),
    "privacy-policy": dict(eyebrow="Your data", title="Privacy", accent="policy",
                           chips="What we collect|Cookies|Your rights", doc_id="policy"),
    "terms-and-conditions": dict(eyebrow="The fine print", title="Terms &amp;", accent="conditions",
                                 chips="Prices in USD|Secure payments|1-year warranty", doc_id="terms"),
}

KEEP = {"AUN", "USD", "Android", "TV", "WhatsApp", "PayPal", "EU", "GDPR", "VAT", "GST", "I", "LCD", "HDMI", "FAQ", "ID"}


def sentence(title):
    """'Parts Shipped to You' -> 'Parts shipped to you' (acronyms and names kept)."""
    words = title.split(" ")
    out = []
    for i, w in enumerate(words):
        parts = w.split("-")
        fixed = []
        for j, part in enumerate(parts):
            core = re.sub(r"[^A-Za-z]", "", part)
            if (i == 0 and j == 0) or core in KEEP or (len(core) > 1 and core.isupper()):
                fixed.append(part)
            else:
                fixed.append(part[:1].lower() + part[1:])
        out.append("-".join(fixed))
    return " ".join(out)


def clean(fragment):
    """Drop inline styles the design now provides; keep the words and inline markup."""
    s = re.sub(r'\s+style="[^"]*"', "", fragment)
    s = re.sub(r"<span>(.*?)</span>", r"\1", s, flags=re.S)
    return s.strip()


def attr(text):
    return text.replace('"', "&quot;").replace("[", "&#91;").replace("]", "&#93;")


def cards_from(block):
    return re.findall(r'<i class="fa-solid ([a-z0-9-]+)"></i></div>\s*<h3[^>]*>(.*?)</h3>\s*<p[^>]*>(.*?)</p>', block, re.S)


def sections(src):
    return {m.group(1): m.group(2) for m in re.finditer(r'\[section[^\]]*label="([^"]+)"[^\]]*\](.*?)\[/section\]', src, re.S)}


def convert(slug):
    src_path = PAGES / f"{slug}.txt"
    src = src_path.read_text(encoding="utf-8")
    legacy = LEGACY / f"{slug}.txt"
    if not legacy.exists():
        shutil.copy2(src_path, legacy)
    src = legacy.read_text(encoding="utf-8")
    meta = META[slug]
    secs = sections(src)
    hero_key = next(k for k in secs if k.endswith("Hero"))
    hero = secs[hero_key]
    h1 = re.search(r'<h1[^>]*>(?:<i class="fa-solid ([a-z0-9-]+)"></i>\s*)?(.*?)</h1>', hero, re.S)
    icon = h1.group(1)
    sub = re.search(r'<p style="font-size:16px[^"]*">(.*?)</p>', hero, re.S).group(1).strip()
    btn = re.search(r'<a href="([^"]+)"[^>]*><i class="fa-solid ([a-z0-9-]+)"></i>\s*(.*?)</a>', hero, re.S)

    out = []
    h = f'[aunp_hero eyebrow="{meta["eyebrow"]}" icon="{icon}" title="{meta["title"]}" accent="{meta["accent"]}" sub="{attr(sub)}"'
    if btn:
        h += f' btn="{attr(sentence(btn.group(3).strip()))}" btn_url="{btn.group(1)}" btn_icon="{btn.group(2)}"'
    if meta.get("btn2"):
        b = meta["btn2"]
        h += f' btn2="{b[0]}" btn2_url="{b[1]}" btn2_icon="{b[2]}"'
    h += f' chips="{meta["chips"]}"]'
    out.append(h)

    draft = next((v for k, v in secs.items() if k.startswith("DRAFT")), None)
    highlights = next(v for k, v in secs.items() if k.endswith("Highlights"))
    out.append("")
    out.append('[aunp_section tone="white"]')
    if draft:
        note = re.search(r"<div[^>]*>(.*?)</div>", draft, re.S).group(1)
        out.append('[aunp_note tone="draft"]' + clean(note) + "[/aunp_note]")
    out.append('[aunp_cards cols="3"]')
    for ic, t, p in cards_from(highlights):
        out.append(f'[aunp_card icon="{ic}" title="{attr(sentence(clean(t)))}"]{clean(p)}[/aunp_card]')
    out.append("[/aunp_cards]")
    out.append("[/aunp_section]")

    steps_key = next((k for k in secs if k in ("How a Claim Works", "Delivery Process")), None)
    if steps_key:
        st = secs[steps_key]
        h2 = re.search(r"<h2[^>]*>(.*?)</h2>", st, re.S).group(1)
        sp = re.search(r"<p style=\"color:#64748b;max-width:680px[^\"]*\">(.*?)</p>", st, re.S).group(1)
        out.append("")
        out.append('[aunp_section tone="soft"]')
        out.append(f'[aunp_head eyebrow="Step by step" icon="fa-route" title="{attr(sentence(clean(h2)))}" sub="{attr(clean(sp))}"]')
        out.append("[aunp_steps]")
        for ic, t, p in cards_from(st):
            t = re.sub(r"^Step \d+:\s*", "", clean(t))
            out.append(f'[aunp_step icon="{ic}" title="{attr(sentence(t))}"]{clean(p)}[/aunp_step]')
        out.append("[/aunp_steps]")
        out.append("[/aunp_section]")

    title = re.search(r'\[title style="bold-left" text="([^"]+)"\]', src).group(1)
    out.append("")
    out.append(f'[aunp_section tone="white" id="{meta["doc_id"]}"]')
    note_secs = [v for k, v in secs.items() if "Note" in k and not k.startswith("DRAFT")]
    out.append(f'[aunp_doc title="{attr(sentence(title))}"]')
    for t, body in re.findall(r'\[accordion-item title="([^"]+)"\](.*?)\[/accordion-item\]', src, re.S):
        num, _, rest = t.partition(". ")
        t2 = f"{num}. {sentence(rest)}" if rest else sentence(t)
        body = "\n".join(line.strip() for line in clean(body).splitlines() if line.strip())
        out.append(f'[aunp_clause title="{attr(t2)}"]\n{body}\n[/aunp_clause]')
    out.append("[/aunp_doc]")
    for ns in note_secs:
        inner = re.search(r'<div style="[^"]*border-left:4px solid #ffbc00[^"]*">(.*?)</div>', ns, re.S).group(1)
        out.append('[aunp_note tone="info"]' + clean(inner) + "[/aunp_note]")
    out.append("[/aunp_section]")

    out.append("")
    out.append("[aunp_help]")
    out.append("")
    date = re.search(r"Last updated: <strong>([^<]+)</strong>", src)
    if date:
        out.append(f'[aunp_updated date="{date.group(1)}"]')
    src_path.write_text("\n".join(out) + "\n", encoding="utf-8", newline="\n")
    print(f"{slug}: {len(cards_from(highlights))} cards, {len(re.findall('accordion-item title', src))} clauses, steps={bool(steps_key)}, notes={len(note_secs)}, draft={bool(draft)}")


for slug in META:
    convert(slug)
