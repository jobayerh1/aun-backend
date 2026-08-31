# Deploy — the buy box, all one design

Two uploads:

1. **`kohthai-chat-order.zip`** → v1.1.0 (rewritten)
2. **`kohthai-product-blocks.zip`** → v1.3.0 (restyles the delivery box)

Activate both, clear WP Rocket.

---

## The problem

Your buy column had three blocks by three different hands, in the same 400px:

```
Trust row          warm  #faf7f3 tiles, inline SVG, 3px radius
Chat & Order       cool  #fafafa box, 8px radius, FontAwesome, text links
Delivery estimate  cool  #f7f7f7 box, 5px radius, FontAwesome truck
Backorder notice   ALARM #b30000 on #fff0f0
```

Three palettes, three corner radii, two icon systems. Now they're one family: warm `#faf7f3`,
`#e8e2d9` border, 3px radius, the same inline-SVG icons.

---

## 1 · Chat & Order — rewritten

The heading-plus-text-links box becomes **two proper buttons**, matching the mockup:

```
Prefer to order by message?
[  WhatsApp  ]  [  Messenger  ]
```

**Two things beyond the styling:**

**The JavaScript is gone.** v1.0.0 read the product name out of `.product-title` on
`DOMContentLoaded` and wrote the hrefs client-side. The name is known in PHP, and **WP Rocket
delays JavaScript on your site** — so those buttons pointed at `#` until the visitor interacted
with the page. They're now built server-side and work immediately, JS or no JS.

**FontAwesome is gone.** The brand glyphs are inline SVG, so they can't render as blank squares
if the icon set is ever subsetted.

**Nothing breaks on upgrade** — same shortcode, same option key (`kohthai_chat_order_settings`,
so your WhatsApp number and Messenger ID carry over), same element IDs, same discontinued-product
check.

**Two new settings:** the line above the buttons, and the WhatsApp message template where
`{product}` is replaced with the product name.

## 2 · Delivery estimate — restyled, not rewritten

**I did not touch that plugin.** It splits the cart by stock status, writes estimates into order
meta and into customer emails — its logic has no business being edited to change a colour. The
restyle is a CSS override from Product Blocks, which already owns the product-page look.

```
Container   cool #f7f7f7  →  warm #faf7f3, 3px radius
Truck icon  FontAwesome   →  inline SVG in brand brown, same grid as every other icon
Backorder   #b30000 on #fff0f0  →  amber #8a6110 on #fdf6e7
```

On the backorder colour: it was the loudest element in the entire buy box, outweighing the price,
for information that's important but isn't an emergency. Amber still reads as *pay attention*
without making the product look like a problem.

There's a switch for this at **Settings → Product Blocks → "Match the other plugins"** if you
ever want the delivery box back as it was.

---

## Two bugs I hit while building this, both mine

**The icon colour never substituted.** I encoded the SVG data URI first and *then* tried to swap a
`%COLOR%` placeholder — but encoding had already turned it into `%25COLOR%25`, so the replace
silently did nothing and both icons came out with a literal placeholder as their stroke colour.
The colour is now substituted before encoding.

**My "the old red is gone" test passed against a comment.** The CSS comment explains the change by
quoting `#b30000` and `#fff0f0`, so a naive search found them and the assertion was meaningless.
The tests now strip `/* */` before asserting on rules — same trap as last time, worth remembering
for any "this value is gone" check.

---

## Tests

```bash
php test-kohthai-product-blocks.php
```

**144 assertions, 0 failures.** The new ones cover the delivery restyle: warm tile applied, cool
grey gone, the icon margin forced past the plugin's inline `style="margin-right:.4em"`, the truck
present as a data URI in brand brown, backorder amber with alarm red gone, and the toggle
removing only its own block.

---

## After deploying

1. Upload both, activate, clear WP Rocket.
2. Check an **in-stock** product — chat buttons, warm delivery tile, matching truck icon.
3. Check a **backorder** product (Elegant Flap or Magnetic Flap) — trust row should say
   "Ships in 1-2 weeks", and the notice should be amber not red.
4. Tap the WhatsApp button on a phone and confirm the message pre-fills with the product name.

That last check matters most — it's the one that was silently broken before.
