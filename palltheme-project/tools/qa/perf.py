"""Core Web Vitals probe (Playwright + Chrome DevTools Protocol).

Mobile viewport, 4x CPU throttling. Reports TTFB, FCP, LCP (and LCP minus
TTFB, because the local SQLite test server is slow to generate HTML), CLS,
an approximate Total Blocking Time, request count, transfer size by type
and DOM size. Not a Lighthouse score — a repeatable check of what the theme
controls.

Usage: python perf.py [name,name,...]
"""
import asyncio
import json
import sys
from collections import defaultdict
from pathlib import Path

from playwright.async_api import async_playwright

URLS = json.loads((Path(__file__).resolve().parent / "urls.json").read_text(encoding="utf-8"))
NAMES = sys.argv[1].split(",") if len(sys.argv) > 1 else ["home", "shop", "product-simple", "post-single", "pall_service-single", "about"]

OBSERVE = """
window.__pt = { lcp: 0, cls: 0, tbt: 0, fcp: 0 };
new PerformanceObserver(l => { for (const e of l.getEntries()) window.__pt.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__pt.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
new PerformanceObserver(l => { for (const e of l.getEntries()) window.__pt.tbt += Math.max(0, e.duration - 50); }).observe({ type: 'longtask', buffered: true });
new PerformanceObserver(l => { for (const e of l.getEntries()) if (e.name === 'first-contentful-paint') window.__pt.fcp = e.startTime; }).observe({ type: 'paint', buffered: true });
"""


async def measure(browser, name, url):
    ctx = await browser.new_context(viewport={"width": 390, "height": 800}, is_mobile=True, has_touch=True, device_scale_factor=2)
    page = await ctx.new_page()
    cdp = await ctx.new_cdp_session(page)
    await cdp.send("Emulation.setCPUThrottlingRate", {"rate": 4})
    await page.add_init_script(OBSERVE)
    sizes = defaultdict(int)
    count = 0

    def on_response(r):
        nonlocal count
        count += 1

    page.on("response", on_response)
    await cdp.send("Network.enable")
    cdp.on("Network.loadingFinished", lambda e: sizes.__setitem__("_total", sizes["_total"] + e.get("encodedDataLength", 0)))
    types = {}
    cdp.on("Network.responseReceived", lambda e: types.__setitem__(e["requestId"], e["type"]))
    cdp.on("Network.loadingFinished", lambda e: sizes.__setitem__(types.get(e["requestId"], "Other"), sizes[types.get(e["requestId"], "Other")] + e.get("encodedDataLength", 0)))
    await page.goto(url, wait_until="load", timeout=180000)
    await page.wait_for_timeout(3000)
    m = await page.evaluate("(() => { const n = performance.getEntriesByType('navigation')[0]; return { ...window.__pt, ttfb: n.responseStart, dom: document.getElementsByTagName('*').length }; })()")
    await ctx.close()
    kb = {k: round(v / 1024) for k, v in sizes.items()}
    return name, m, count, kb


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel="chrome")
        print(f"{'page':22} {'TTFB':>6} {'FCP':>6} {'LCP':>6} {'LCP-TTFB':>8} {'CLS':>5} {'TBT':>5} {'req':>4} {'KB':>5}  JS/CSS/IMG/Font KB  DOM")
        for name in NAMES:
            n, m, req, kb = await measure(browser, name, URLS[name])
            print(f"{n:22} {m['ttfb']:6.0f} {m['fcp']:6.0f} {m['lcp']:6.0f} {m['lcp'] - m['ttfb']:8.0f} {m['cls']:5.3f} {m['tbt']:5.0f} {req:4} {kb.get('_total', 0):5}  "
                  f"{kb.get('Script', 0)}/{kb.get('Stylesheet', 0)}/{kb.get('Image', 0)}/{kb.get('Font', 0)}  {m['dom']}", flush=True)
        await browser.close()


asyncio.run(main())
