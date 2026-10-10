"""Responsive / dark-mode / console QA with Playwright + installed Chrome.

Each URL in urls.json is loaded ONCE (the local test server is slow), then
the viewport is resized through every width and the color scheme switched,
re-checking after each change:
  - console errors, page errors, failed same-origin requests (on load)
  - horizontal overflow (and the widest offending elements) at each width
  - broken images (complete but naturalWidth 0) after scrolling the page
Screenshots go to ./shots/<name>-<width>-<scheme>.png for SHOT_WIDTHS.

Usage: python visual.py [names,comma,separated|all] [widths,comma,separated]
"""
import asyncio
import json
import sys
from pathlib import Path

from playwright.async_api import async_playwright

HERE = Path(__file__).resolve().parent
URLS = json.loads((HERE / "urls.json").read_text(encoding="utf-8"))
WIDTHS = [1920, 1440, 1024, 768, 480, 390, 375, 320]
SHOT_WIDTHS = {1440, 390}
OUT = Path.cwd() / "shots"
OUT.mkdir(exist_ok=True)

if len(sys.argv) > 1 and sys.argv[1] != "all":
    URLS = {k: v for k, v in URLS.items() if k in sys.argv[1].split(",")}
if len(sys.argv) > 2:
    WIDTHS = [int(w) for w in sys.argv[2].split(",")]

SCROLL_JS = """
async () => {
  const step = innerHeight * 0.8;
  for (let y = 0; y < document.body.scrollHeight; y += step) { scrollTo(0, y); await new Promise(r => setTimeout(r, 100)); }
  scrollTo(0, 0);
  await new Promise(r => setTimeout(r, 500));
}
"""

CHECK_JS = """
() => {
  const vw = document.documentElement.clientWidth;
  const overflow = document.documentElement.scrollWidth - vw;
  const offenders = [];
  if (overflow > 1) {
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect();
      if (r.width && r.right > vw + 1 && getComputedStyle(el).position !== 'fixed') {
        let p = el.parentElement, clipped = false;
        while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o !== 'visible') { clipped = true; break; } p = p.parentElement; }
        if (!clipped) offenders.push((el.tagName.toLowerCase() + '.' + [...el.classList].join('.')).slice(0, 90) + ' →' + Math.round(r.right));
      }
      if (offenders.length > 6) break;
    }
  }
  const broken = [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.getAttribute('src') && !i.src.startsWith('data:') && i.offsetParent !== null).map(i => i.src);
  const css = [...document.styleSheets].some(s => (s.href || '').includes('/palltheme/assets/css/main.css'));
  return { overflow, offenders, broken, css, scheme: document.documentElement.getAttribute('data-scheme') };
}
"""


async def check(browser, name, url):
    ctx = await browser.new_context(viewport={"width": WIDTHS[0], "height": 900}, color_scheme="light")
    page = await ctx.new_page()
    errors, failed, issues = [], [], []
    page.on("console", lambda m: errors.append(m.text[:200]) if m.type == "error" else None)
    page.on("pageerror", lambda e: errors.append("pageerror: " + str(e)[:200]))
    page.on("requestfailed", lambda r: failed.append(r.url[:150]) if "127.0.0.1" in r.url else None)
    page.on("response", lambda r: failed.append(f"{r.status} {r.url[:150]}") if r.status >= 400 and "127.0.0.1" in r.url and r.url != url else None)
    try:
        await page.goto(url, wait_until="load", timeout=180000)
        await page.evaluate(SCROLL_JS)
        for w in WIDTHS:
            await page.set_viewport_size({"width": w, "height": 900 if w > 600 else 800})
            await page.wait_for_timeout(350)
            res = await page.evaluate(CHECK_JS)
            if not res["css"]:
                issues.append(f"{w}: main.css not loaded")
            if res["overflow"] > 1:
                issues.append(f"{w}: overflow {res['overflow']}px: " + "; ".join(res["offenders"][:4]))
            if res["broken"] and w == WIDTHS[0]:
                issues.append("broken images: " + ", ".join(res["broken"][:3]))
            if w in SHOT_WIDTHS:
                await page.screenshot(path=str(OUT / f"{name}-{w}-light.png"))
                await page.emulate_media(color_scheme="dark")
                await page.wait_for_timeout(300)
                dark = await page.evaluate(CHECK_JS)
                if dark["scheme"] != "dark":
                    issues.append(f"{w}: system dark mode not applied (data-scheme={dark['scheme']})")
                await page.screenshot(path=str(OUT / f"{name}-{w}-dark.png"))
                await page.emulate_media(color_scheme="light")
    except Exception as e:  # noqa: BLE001
        issues.append("LOAD ERROR " + str(e)[:200])
    await ctx.close()
    if errors:
        issues.append("console: " + " | ".join(dict.fromkeys(errors))[:400])
    if failed:
        issues.append("failed requests: " + " | ".join(dict.fromkeys(failed))[:400])
    return name, issues


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel="chrome")
        sem = asyncio.Semaphore(1)  # the SQLite test server serialises writes; parallel loads only add lock waits

        async def run(item):
            async with sem:
                result = await check(browser, *item)
                print(f"[{result[0]}] {'OK' if not result[1] else 'ISSUES'}", flush=True)
                return result

        results = await asyncio.gather(*(run(i) for i in URLS.items()))
        await browser.close()
    problems = [r for r in results if r[1]]
    print()
    for name, issues in problems:
        print(f"[{name}]\n    " + "\n    ".join(issues))
    print(f"\n{len(results)} pages × {len(WIDTHS)} widths, {len(problems)} pages with issues")


asyncio.run(main())
