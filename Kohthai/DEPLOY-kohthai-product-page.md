# Deploy — Kohthai Product Page v1.0.0

Upload `kohthai-product-page.zip` via **Plugins → Add New → Upload Plugin**, activate,
then go to **WooCommerce → Product Page**.

⚠️ **Clear the WP Rocket cache after activating and after every settings save.**
Product pages are cached; without a purge they keep serving the old markup and you'll
think the plugin didn't work.

---

## Fill in two fields first

Nothing on the WhatsApp/Messenger row appears until you set these:

| Field | Value |
|---|---|
| WhatsApp number | digits only, country code first, no `+` — e.g. `8809638078888` |
| Messenger link | e.g. `https://m.me/yourpage` |

The returns link defaults to `/returns_refund/`, which is correct for this site.

---

## What to check after activating (in this order)

1. **The video gap is gone.** Open any product with a video. The blank block above it
   should have disappeared. Measured before: 979px wrapper for a 489px video.
2. **Colour names show under each swatch**, permanently, with the selected one in bold
   brown. Confirm on a *phone* — that's the case that was broken (no hover = no tooltip).
3. **"Colour: Tea Green"** appears above the swatches and updates when you tap another.
4. **Add to Cart is now solid dark brown**, Buy Now is an outline.
5. **Scroll down on a phone** — the sticky buy bar should slide up once the real Add to
   Cart leaves the screen. Tap its button *without* choosing a colour: it should scroll
   you back to the swatches and flash them gold rather than silently failing.
6. **`SKU: N/A` is gone.**
7. **Related Products** — the out-of-stock "Wave Pattern Small Square Crossbody Bag"
   should no longer be first.
8. **The empty-heading flood is gone.** View source on a product; you should no longer
   find `<h5 class="uppercase"></h5>`. Before: 18 empty `<h5>` and 37 empty `<p>`.

---

## If something looks wrong

Every fix has its own on/off switch on the settings screen. **Turn off the single row
that causes it** rather than deactivating the whole plugin — you'll keep the other ten.

The two most likely to need attention on a theme update:

- **Colour names** — depends on Flatsome emitting `data-name` on `.ux-swatch`. Verified
  present in Flatsome 3.20.6. If a future version drops it, the labels go blank.
- **Buy Now outline** — matches `.buy-now-button`, `.wc-buy-now` and `button[name="buy_now"]`.
  If your Buy Now button uses a different class, tell me and I'll add it.

---

## Deliberate decisions worth knowing

**No warranty text anywhere.** These are bags. Any warranty badge would be a false claim.

**The trust row copy is written against the real returns policy** — a *conditional*
7-day return (damaged, incorrect, incomplete, or not as advertised), with the customer
paying return shipping. The default middle tile says **"7-Day Return / If damaged or not
as described"**. Do not change it to "no questions asked", "free returns" or "hassle-free
returns" — all three are untrue here and would invite exactly the disputes you're
avoiding with photo swatches. There's a warning to this effect on the settings screen.

**Photo colour swatches are left alone.** Your reasoning is right: a flat colour chip
promises a shade the bag may not be. The plugin adds the missing *name*, not a chip.

**No nonce is printed into product HTML.** Product pages are cached, and a nonce baked
into cached HTML dies in 12–24h and starts throwing "Security check failed" at real
customers — that's the live bug still running on AUN's OTP login. This plugin has no
front-end AJAX, so there's nothing to nonce.

**The sticky bar reads the product from the queried object, not `$product`.** By footer
time the Related Products loop has already run and left that global pointing at the last
related bag, which would have put the wrong photo and price in the bar.

---

## Tests

```bash
php test-kohthai-product-page.php
```

40 assertions, 0 failures as shipped. Covers the empty-markup stripper (including the
exact `[featured_box]` shape from your live page, and that an icon-only `<h5><img></h5>`
survives), the related-product ordering, and the settings whitelist — including that a
`javascript:` URL in the Messenger field is rejected.

---

## Not in this release

Deliberately left for v1.1 so this one stays reviewable:

- **Video as gallery slide 2** — needs a "Product Video URL" field on the product data
  panel and an injection into the Flatsome gallery.
- **Accordions replacing the pills tabgroup** — that's a content edit per product plus
  the `woocommerce_product_tabs` rename; better done alongside the short-description
  rewrite so you only touch each product once.
- **Gallery trimmed to 6–8 images** — a content decision, not code.
