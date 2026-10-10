"""
Dev tool: download the selected Unsplash photos, resize, convert to WebP
(metadata stripped), give them descriptive filenames and write a manifest
with full licence/credit data.

Photos are for THIS website's media library only. The Unsplash License does
not allow redistributing them inside the sellable theme package, so they are
written outside the plugin (tools/media/photos/), never into palltheme-core/.
"""
import io
import json
import os
import urllib.request

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
CANDS = json.load(open(os.path.join(HERE, "candidates", "unsplash_candidates.json"), encoding="utf-8"))
OUT = os.path.join(HERE, "photos")
os.makedirs(OUT, exist_ok=True)

# slot: (candidate list, index, descriptive filename, alt text, usage)
PICKS = {
    "hero": ("hero", 0, "data-center-network-cabling-blue-light", "Illuminated network cabling in a data center rack", "Homepage hero"),
    "home-why": ("home-why", 3, "server-rack-green-status-lights", "Server rack with green status lights", "Homepage: why choose us"),
    "svc-network": ("svc-network", 7, "enterprise-network-patch-cables", "Blue network patch cables connected to a switch", "Service: Network Infrastructure"),
    "svc-server": ("svc-server", 7, "enterprise-server-drive-bays", "Close-up of enterprise server drive bays", "Service: Server Solutions"),
    "svc-cloud": ("svc-cloud", 3, "cloud-computing-infrastructure-3d", "3D illustration of cloud computing infrastructure", "Service: Cloud Computing"),
    "svc-security": ("svc-security", 5, "cybersecurity-padlock-keyboard", "Padlock resting on a computer keyboard", "Service: Cybersecurity"),
    "svc-managed": ("svc-managed", 2, "it-engineer-working-server-rack", "IT engineer working on a server rack", "Service: Managed IT Services"),
    "svc-datacenter": ("svc-datacenter", 0, "data-center-server-aisle", "Long aisle of server racks in a data center", "Service: Data Center Solutions"),
    "svc-software": ("svc-software", 3, "software-development-code-screen", "Source code on a monitor in a dark room", "Service: Software Development"),
    "svc-consultancy": ("svc-consultancy", 2, "it-consultants-whiteboard-planning", "Two consultants planning at a whiteboard", "Service: IT Consultancy"),
    "svc-backup": ("svc-backup", 1, "hard-disk-drive-backup-storage", "Open hard disk drive used for backup storage", "Service: Backup & Disaster Recovery"),
    "svc-hosting": ("svc-network", 0, "server-rack-network-cables-hosting", "Network cables connected to hosting servers", "Service: VPS & Hosting"),
    "svc-ai": ("svc-ai", 2, "artificial-intelligence-processor-chip", "Glowing AI processor chip", "Service: AI Solutions"),
    "svc-iot": ("svc-iot", 1, "iot-smart-sensors-devices", "Collection of IoT smart sensors and devices", "Service: IoT Solutions"),
    "sol-isp": ("sol-isp", 1, "fiber-optic-strands-light", "Fiber optic strands carrying light", "Solution: ISP"),
    "sol-telecom": ("sol-telecom", 0, "telecom-towers-sunset", "Telecommunication towers at sunset", "Solution: Telecom"),
    "sol-enterprise": ("sol-enterprise", 0, "enterprise-glass-office-building", "Curved glass facade of an office building", "Solution: Enterprise"),
    "sol-education": ("sol-education", 0, "university-lecture-hall-projector", "University lecture hall with projector screen", "Solution: Education"),
    "sol-healthcare": ("sol-healthcare", 1, "healthcare-digital-imaging-tablet", "Clinician reviewing medical images on a tablet", "Solution: Healthcare"),
    "sol-banking": ("sol-banking", 1, "bank-building-financial-district", "Classic bank building in a financial district", "Solution: Banking"),
    "sol-government": ("sol-government", 3, "government-building-columns", "Columns of a government building", "Solution: Government"),
    "sol-retail": ("sol-retail", 1, "retail-pos-checkout-terminal", "Point-of-sale terminal at a retail checkout", "Solution: Retail"),
    "sol-manufacturing": ("sol-manufacturing", 1, "industrial-robot-arm-factory", "Industrial robot arm on a factory line", "Solution: Manufacturing"),
    "sol-datacenter": ("sol-datacenter", 3, "dark-data-center-corridor", "Dark data center corridor between server racks", "Solution: Data Centers"),
    "cs-network": ("cs-network", 7, "network-rack-patch-cabling", "Patch cabling in a network rack", "Case study: Enterprise Network Upgrade"),
    "cs-dcmodern": ("cs-dcmodern", 5, "engineer-laptop-data-center-corridor", "Engineer with a laptop in a data center corridor", "Case study: Data Center Modernization"),
    "cs-cloud": ("cs-cloud", 1, "laptop-glowing-screen-dark", "Laptop with a glowing screen in the dark", "Case study: Secure Cloud Migration"),
    "cs-isp": ("cs-isp", 2, "fiber-patch-panel-telecom", "Fiber patch panel with yellow cables", "Case study: ISP Network Expansion"),
    "cs-security": ("cs-security", 0, "security-operations-center-screens", "Operator in front of a wall of monitoring screens", "Case study: Cybersecurity Deployment"),
    "cs-dr": ("cs-dr", 3, "hard-drive-platter-dark", "Open hard drive platter on a dark background", "Case study: Disaster Recovery"),
    "blog-network": ("blog-network", 6, "network-switch-ethernet-cables", "Ethernet cables plugged into a network switch", "Blog: enterprise network"),
    "blog-servers": ("blog-servers", 1, "server-rack-orange-cables", "Server rack with orange cabling", "Blog: server planning"),
    "blog-25gbe": ("blog-25gbe", 6, "fiber-optic-bokeh-blue", "Blue fiber optic light points", "Blog: 10GbE vs 25GbE"),
    "blog-cloud": ("blog-cloud", 3, "single-cloud-teal-sky", "Single cloud against a teal sky", "Blog: cloud migration"),
    "blog-secure": ("blog-secure", 1, "combination-lock-keyboard-cards", "Combination lock on a keyboard", "Blog: securing enterprise networks"),
    "blog-linux": ("blog-linux", 0, "linux-terminal-sudo-prompt", "Linux terminal prompt", "Blog: Linux hardening"),
    "blog-sfp": ("blog-sfp", 7, "sfp-ports-fiber-cables", "Fiber cables in switch SFP ports", "Blog: SFP+ and SFP28"),
    "blog-redundancy": ("blog-redundancy", 2, "power-transmission-lines-sunset", "Power transmission lines at sunset", "Blog: data center redundancy"),
    "blog-proxmox": ("blog-proxmox", 0, "server-racks-colorful-cabling", "Server racks with colorful cabling", "Blog: Proxmox virtualization"),
    "blog-docker": ("blog-docker", 0, "container-ship-aerial-view", "Aerial view of a container ship", "Blog: Docker"),
    "blog-k8s": ("blog-k8s", 1, "ship-wheel-helm", "Ship's wheel at the helm", "Blog: Kubernetes"),
    "blog-dr": ("blog-dr", 7, "hard-disk-black-white", "Black-and-white hard disk close-up", "Blog: backup & DR planning"),
    "blog-cyber": ("blog-cyber", 7, "code-on-screen-bokeh", "Colorful code on a screen", "Blog: cybersecurity fundamentals"),
    "blog-ispnet": ("blog-ispnet", 4, "cellular-antennas-tower", "Cluster of cellular antennas on a tower", "Blog: scalable ISP network"),
    "blog-wordpress": ("blog-wordpress", 3, "laptop-website-desk", "Laptop showing a website on a desk", "Blog: WordPress performance"),
    "blog-woo": ("blog-woo", 2, "online-store-laptop-shopping", "Person browsing an online store on a laptop", "Blog: WooCommerce at scale"),
    "blog-aiops": ("blog-aiops", 7, "circuit-board-teal-macro", "Macro view of a teal circuit board", "Blog: AIOps"),
    "about-team": ("about-team", 2, "technology-team-meeting-laptops", "Technology team meeting around laptops", "About: company overview"),
    "about-infra": ("about-infra", 7, "server-rack-green-cabling", "Server rack with green-lit cabling", "About: infrastructure"),
    "about-office": ("about-office", 0, "bright-open-plan-office", "Bright open-plan office", "About: why choose us"),
    "careers-team": ("careers-team", 2, "team-desk-laptops-top-view", "Top view of a team working on laptops", "Careers: why work with us"),
    "careers-culture": ("careers-culture", 4, "team-discussion-table", "Colleagues in discussion around a table", "Careers: culture"),
    "support-agent": ("support-agent", 6, "support-headset-white-background", "Support headset on a white background", "Support page"),
}

ids = {}
for slot, (cl, idx, *_rest) in PICKS.items():
    pid = CANDS[cl][idx][0]
    ids.setdefault(pid, []).append(slot)
dups = {k: v for k, v in ids.items() if len(v) > 1}
print("duplicates:", dups or "none")

if __name__ == "__main__" and not dups:
    manifest = []
    for slot, (cl, idx, fname, alt, usage) in PICKS.items():
        pid, path, name, username, _alt = CANDS[cl][idx]
        width = 2000 if slot == "hero" else 1600
        url = f"https://images.unsplash.com/{path}?w={width}&q=85&fm=jpg"
        dest = os.path.join(OUT, fname + ".webp")
        if not os.path.exists(dest):
            data = urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=60).read()
            im = Image.open(io.BytesIO(data)).convert("RGB")  # convert() drops EXIF/ICC metadata
            im.save(dest, "WEBP", quality=78, method=6)
        size = os.path.getsize(dest)
        manifest.append({
            "key": slot, "file": fname + ".webp", "alt": alt, "usage": usage,
            "source": "Unsplash", "source_id": pid,
            "original_url": f"https://unsplash.com/photos/{pid}",
            "author": name, "author_url": f"https://unsplash.com/@{username}",
            "license": "Unsplash License", "license_url": "https://unsplash.com/license",
            "commercial_use": True, "attribution_required": False,
            "attribution_text": f"Photo by {name} on Unsplash",
            "bytes": size,
        })
        print(f"{slot:16} {size // 1024:4d} KB  {name}")
    json.dump(manifest, open(os.path.join(HERE, "photos-manifest.json"), "w", encoding="utf-8"), indent=1, ensure_ascii=False)
    print("total", sum(m["bytes"] for m in manifest) // 1024, "KB,", len(manifest), "photos")
