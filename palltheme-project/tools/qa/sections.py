"""Screenshots of interactive/off-screen parts: mega menus, footer, hero video.

Usage: python sections.py
"""
import asyncio

from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8090"


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel="chrome")
        page = await browser.new_page(viewport={"width": 1440, "height": 900})
        page.set_default_timeout(120000)
        await page.goto(BASE + "/", wait_until="load")
        await page.wait_for_timeout(1500)
        video = await page.evaluate(
            "(() => { const v = document.querySelector('[data-pt-video]'); return v ? {ready: v.classList.contains('is-ready'), src: v.currentSrc, paused: v.paused, w: v.videoWidth} : null })()"
        )
        print("hero video:", video)
        lottie = await page.evaluate("document.querySelectorAll('[data-pt-lottie]').length")
        print("lottie on home:", lottie)
        for label in ("Services", "Solutions", "Products"):
            item = page.locator("#pt-primary-menu > li", has_text=label).first
            await item.hover()
            await page.wait_for_timeout(700)
            await page.screenshot(path=f"mega-{label.lower()}.png", clip={"x": 0, "y": 0, "width": 1440, "height": 640})
        await page.mouse.move(5, 880)
        await page.emulate_media(color_scheme="dark")
        await page.locator("#pt-primary-menu > li", has_text="Services").first.hover()
        await page.wait_for_timeout(700)
        await page.screenshot(path="mega-services-dark.png", clip={"x": 0, "y": 0, "width": 1440, "height": 640})
        await page.emulate_media(color_scheme="light")
        await page.mouse.move(5, 880)
        footer = page.locator("footer.pt-footer, #colophon").first
        await footer.scroll_into_view_if_needed()
        await page.wait_for_timeout(800)
        await footer.screenshot(path="footer-1440.png")
        await page.goto(BASE + "/support/", wait_until="load")
        anim = page.locator("[data-pt-lottie]").first
        await anim.scroll_into_view_if_needed()
        await page.wait_for_timeout(2500)
        svg = await page.evaluate("(() => { const l = document.querySelector('[data-pt-lottie]'); return l ? l.querySelectorAll('svg, canvas').length : -1 })()")
        print("lottie rendered elements on support:", svg)
        await anim.screenshot(path="lottie-support.png")
        await page.set_viewport_size({"width": 390, "height": 800})
        await page.goto(BASE + "/", wait_until="load")
        footer = page.locator("footer.pt-footer, #colophon").first
        await footer.scroll_into_view_if_needed()
        await page.wait_for_timeout(800)
        await footer.screenshot(path="footer-390.png")
        await browser.close()


asyncio.run(main())
