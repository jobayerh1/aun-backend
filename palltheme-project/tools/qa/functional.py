"""Functional QA of the demo store and site interactions (guest visitor).

Live search, scheme toggle, mobile off-canvas menu, quick view, add to cart
(simple + variable), mini cart, wishlist, compare, block checkout with
fictional test data (Cash on delivery, local test site only), contact form
and newsletter. Prints PASS/FAIL per step.

Usage: python functional.py
"""
import asyncio
import json

from playwright.async_api import async_playwright

BASE = "http://127.0.0.1:8090"
URLS = json.load(open(__file__.replace("functional.py", "urls.json"), encoding="utf-8"))
results = []


def report(name, ok, detail=""):
    results.append(ok)
    print(f"{'PASS' if ok else 'FAIL'}  {name}{'  — ' + detail if detail else ''}", flush=True)


async def step(name, coro):
    try:
        ok, detail = await coro
    except Exception as e:  # noqa: BLE001
        ok, detail = False, f"{e.__class__.__name__}: {str(e)[:220]}"
    report(name, ok, detail)


async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(channel="chrome")
        ctx = await browser.new_context(viewport={"width": 1440, "height": 900})
        page = await ctx.new_page()
        errors = []
        page.on("pageerror", lambda e: errors.append(str(e)[:200]))
        page.set_default_timeout(120000)

        async def live_search():
            await page.goto(BASE + "/")
            await page.click("[data-pt-search-open]")
            await page.fill("[data-pt-search-input]", "switch")
            await page.wait_for_selector("[data-pt-search-results] a", timeout=30000)
            items = await page.locator("[data-pt-search-results] a").all_inner_texts()
            prices = await page.locator("[data-pt-search-results] .pt-search__price").count()
            await page.keyboard.press("Escape")
            return len(items) > 0 and prices > 0, f"{len(items)} results, {prices} with price; first: {items[0][:80]!r}"

        async def search_api():
            r = await page.request.get(BASE + "/wp-json/palltheme-core/v1/search?q=server")
            data = await r.json()
            groups = {g["label"]: len(g["items"]) for g in data.get("groups", [])}
            priced = sum(1 for g in data.get("groups", []) for i in g["items"] if i.get("price"))
            return r.ok and data.get("total", 0) > 0 and priced > 0, f"HTTP {r.status}, total {data.get('total')}, groups {groups}, {priced} with price"

        async def scheme_toggle():
            await page.goto(BASE + "/about/")
            before = await page.evaluate("document.documentElement.getAttribute('data-scheme-pref')")
            await page.click("[data-pt-scheme-toggle]")
            after = await page.evaluate("document.documentElement.getAttribute('data-scheme-pref')")
            await page.reload()
            kept = await page.evaluate("document.documentElement.getAttribute('data-scheme-pref')")
            # Leave it on "system" for later screenshots.
            for _ in range(3):
                if await page.evaluate("document.documentElement.getAttribute('data-scheme-pref')") == "system":
                    break
                await page.click("[data-pt-scheme-toggle]")
            return before != after and kept == after, f"{before} → {after}, after reload {kept}"

        async def quick_view():
            await page.goto(URLS["shop"])
            await page.locator("[data-pt-quickview]").first.click(force=True)
            await page.wait_for_selector(".pt-quickview__content h2, .pt-quickview__content .price", timeout=60000)
            text = await page.locator(".pt-quickview__content").inner_text()
            await page.keyboard.press("Escape")
            return "$" in text or "Add to cart" in text, text.replace("\n", " ")[:120]

        async def add_simple():
            await page.goto(URLS["product-simple"])
            await page.click("form.cart button[name=add-to-cart]")
            # Simple products are added over AJAX; the header count updates when the response arrives.
            await page.wait_for_function("(() => { const c = document.querySelector('.pt-cart-link .pt-badge, .pt-cart-link [class*=count]'); return c && parseInt(c.textContent, 10) > 0; })()", timeout=120000)
            count = await page.locator(".pt-cart-link .pt-badge, .pt-cart-link [class*=count]").first.inner_text()
            return True, f"AJAX add, header cart count {count.strip()!r}"

        async def add_variable():
            await page.goto(URLS["product-variable"])
            sel = page.locator("form.variations_form select").first
            options = await sel.locator("option").all_inner_texts()
            add = page.locator("form.variations_form button[type=submit]:not([name=pt_buy_now])")
            for i in range(1, len(options)):  # first option that is in stock
                await sel.select_option(index=i)
                await page.wait_for_timeout(800)
                if "disabled" not in (await add.get_attribute("class") or ""):
                    break
            price = await page.locator(".woocommerce-variation-price").inner_text()
            async with page.expect_navigation(timeout=120000):
                await add.click()
            notice = await page.locator(".woocommerce-notices-wrapper").first.inner_text()
            count = await page.locator(".pt-cart-link .pt-badge, .pt-cart-link [class*=count]").first.inner_text()
            return int(count.strip() or 0) >= 2, f"options {options[1:]}, price {price.strip()!r}, cart count {count.strip()}, notice {notice.strip()[:60]!r}"

        async def wishlist():
            await page.goto(URLS["product-simple"])
            name = (await page.locator("h1.product_title").inner_text()).strip()
            react = await page.locator(".yith-add-to-wishlist-button-block--single").count()
            if react:
                # React mode: clicks are ignored until the button finishes loading over REST.
                block = page.locator(".yith-add-to-wishlist-button-block--single")
                await page.wait_for_function("!document.querySelector('.yith-add-to-wishlist-button-block--single a').className.includes('--loading')", timeout=120000)
                await block.locator("a").first.dispatch_event("click")
            else:
                # Classic (PHP templates) mode.
                block = page.locator(".yith-wcwl-add-to-wishlist--single")
                await block.locator("a.add_to_wishlist, a[data-product-id]").first.dispatch_event("click")
            selector = ".yith-add-to-wishlist-button-block--single" if react else ".yith-wcwl-add-to-wishlist--single"
            await page.wait_for_function("sel => !document.querySelector(sel).innerText.includes('Add to wishlist')", arg=selector, timeout=120000)
            label = " ".join((await block.inner_text()).split())
            await page.goto(URLS["wishlist"])
            listed = await page.get_by_text(name).count()
            mode = "React" if react else "classic"
            return listed > 0, f"{mode} button now {label!r}; '{name}' on the wishlist page: {listed > 0}"

        async def compare():
            await page.goto(URLS["product-simple"])
            link = page.locator("a.compare, .compare-button a, .yith-woocompare-button a, button.compare").first
            label = await link.inner_text()
            async with page.expect_response(lambda r: "compare" in r.url.lower() and r.request.method == "POST", timeout=120000) as resp:
                await link.click()
            r = await resp.value
            return r.ok, f"button {label.strip()!r} → HTTP {r.status} {r.url[:90]}"

        async def checkout():
            await page.goto(URLS["checkout"])
            await page.wait_for_selector("#email", timeout=120000)
            await page.fill("#email", "demo.buyer@example.com")
            fields = {
                "#shipping-first_name": "Test", "#shipping-last_name": "Customer",
                "#shipping-address_1": "100 Demo Street", "#shipping-city": "Testville",
                "#shipping-postcode": "94016", "#shipping-phone": "5550100",
            }
            for sel, val in fields.items():
                if await page.locator(sel).count():
                    await page.fill(sel, val)
            if await page.locator("#shipping-state input, #shipping-state").count():
                try:
                    await page.locator("#shipping-state input, select#shipping-state").first.fill("California")
                    await page.keyboard.press("Enter")
                except Exception:  # noqa: BLE001
                    pass
            await page.wait_for_timeout(2500)
            cod = page.locator("input[value='cod']")
            if await cod.count():
                await cod.check()
            await page.click(".wc-block-components-checkout-place-order-button")
            try:
                await page.wait_for_url("**/order-received/**", timeout=120000)
            except Exception:  # noqa: BLE001
                await page.screenshot(path="checkout-fail.png", full_page=True)
                notes = await page.locator(".wc-block-components-notice-banner, .wc-block-components-validation-error").all_inner_texts()
                return False, "no order: " + " | ".join(n.strip()[:120] for n in notes)
            text = await page.locator("main").inner_text()
            return "received" in text.lower() or "thank you" in text.lower(), page.url.split("?")[0]

        async def contact_form():
            await page.goto(URLS["contact"])
            await page.fill("#pall-name", "QA Tester")
            await page.fill("#pall-email", "qa.tester@example.com")
            await page.fill("#pall-message", "Automated QA message from the local test bench.")
            if await page.locator("input[name=pall_consent]").count():
                await page.check("input[name=pall_consent]")
            await page.wait_for_timeout(3500)
            await page.click("form:has(#pall-name) button[type=submit]")
            await page.wait_for_url("**pall_sent=**", timeout=60000)
            return "pall_sent=1" in page.url, page.url.split("/")[-1][:60]

        async def newsletter():
            await page.goto(URLS["blog"])
            await page.fill("#pt-newsletter-email", "qa.subscriber@example.com")
            await page.wait_for_timeout(2500)
            await page.click("#pt-newsletter button[type=submit]")
            await page.wait_for_url("**pall_subscribed=**", timeout=60000)
            msg = await page.locator(".pt-newsletter__msg").inner_text()
            return "pall_subscribed=1" in page.url and "/blog/" in page.url, f"{page.url.split('?')[0]} — {msg!r}"

        for name, fn in [
            ("Live search (type, price)", live_search), ("Search REST API", search_api), ("Light/dark/system toggle persists", scheme_toggle),
            ("Quick view", quick_view), ("Add simple product to cart", add_simple), ("Add variable product to cart", add_variable),
            ("Wishlist", wishlist), ("Compare", compare), ("Block checkout (COD, test data)", checkout),
            ("Contact form", contact_form), ("Newsletter sign-up", newsletter),
        ]:
            await step(name, fn())

        # Mobile off-canvas menu.
        mctx = await browser.new_context(viewport={"width": 390, "height": 800}, is_mobile=True, has_touch=True)
        m = await mctx.new_page()

        async def mobile_menu():
            await m.goto(BASE + "/")
            await m.click("[data-pt-offcanvas-open]")
            await m.wait_for_selector("#pt-offcanvas:not([hidden])", timeout=15000)
            links = await m.locator("#pt-offcanvas a").count()
            toggles = m.locator("#pt-offcanvas .pt-submenu-toggle")
            opened = False
            if await toggles.count():
                await toggles.nth(1).click()
                opened = await toggles.nth(1).get_attribute("aria-expanded") == "true"
            return links > 10 and opened, f"{links} links in the drawer, submenu opens: {opened}"

        await step("Mobile off-canvas menu + submenu", mobile_menu())
        await mctx.close()
        report("No uncaught JavaScript errors during the run", not errors, "; ".join(errors[:3]))
        await browser.close()
    print(f"\n{sum(results)}/{len(results)} passed")


asyncio.run(main())
