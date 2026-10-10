"""Elementor editor smoke test (Playwright + installed Chrome).

Logs in with short-lived auth cookies minted by WP-CLI (no password typed),
opens each imported page in the Elementor editor and reports: panel loaded,
Palltheme widgets in the preview, widgets in the panel, console errors.

Usage: python editor.py <site_dir> <page_id> [<page_id> ...]
"""
import asyncio
import json
import subprocess
import sys
from pathlib import Path

from playwright.async_api import async_playwright

SITE = Path(sys.argv[1])
IDS = sys.argv[2:]
BASE = "http://127.0.0.1:8090"


def cookies():
    php = (
        '$h=COOKIEHASH; $e=time()+3600;'
        'echo json_encode(array('
        '"logged_in_name"=>"wordpress_logged_in_".$h,"logged_in"=>wp_generate_auth_cookie(1,$e,"logged_in"),'
        '"auth_name"=>"wordpress_".$h,"auth"=>wp_generate_auth_cookie(1,$e,"auth")));'
    )
    out = subprocess.run(
        ["php", "-c", "../php.ini", "../wp-cli.phar", "eval", php],
        cwd=SITE, capture_output=True, text=True, check=True,
    ).stdout
    c = json.loads(out[out.index("{"):])
    return [
        {"name": c["logged_in_name"], "value": c["logged_in"], "url": BASE + "/"},
        {"name": c["auth_name"], "value": c["auth"], "url": BASE + "/wp-admin"},
        {"name": c["auth_name"], "value": c["auth"], "url": BASE + "/wp-content/plugins"},
    ]


async def main():
    jar = cookies()
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel="chrome")
        ctx = await browser.new_context(viewport={"width": 1600, "height": 1000})
        await ctx.add_cookies(jar)
        for pid in IDS:
            page = await ctx.new_page()
            errors = []
            page.on("console", lambda m: errors.append(m.text[:160]) if m.type == "error" else None)
            page.on("pageerror", lambda e: errors.append("pageerror: " + str(e)[:160]))
            await page.goto(f"{BASE}/wp-admin/post.php?post={pid}&action=elementor", timeout=180000)
            try:
                await page.wait_for_selector("#elementor-preview-iframe", timeout=120000)
                frame = page.frame_locator("#elementor-preview-iframe")
                await frame.locator(".elementor-widget").first.wait_for(timeout=120000)
                await page.wait_for_timeout(3000)
                widgets = await frame.locator(".elementor-widget").count()
                pall = await frame.locator("[class*='elementor-widget-pall_']").count()
                empty = await frame.locator("[class*='elementor-widget-pall_']").evaluate_all(
                    r"els => els.filter(e => e.getBoundingClientRect().height < 40).map(e => e.className.match(/elementor-widget-(pall_\w+)/)[1])"
                )
                title = await page.title()
                await page.screenshot(path=f"editor-{pid}.png")
                print(f"page {pid}: '{title}' widgets={widgets} palltheme={pall} empty={empty} console_errors={len(errors)}")
            except Exception as e:  # noqa: BLE001
                await page.screenshot(path=f"editor-{pid}-fail.png")
                print(f"page {pid}: FAILED {str(e)[:200]}")
            for err in dict.fromkeys(errors):
                print("   ", err)
            await page.close()
        await browser.close()


asyncio.run(main())
