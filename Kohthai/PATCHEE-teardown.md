# Patchee teardown — what to take, what to leave

`thepatchee.com/products/new-wave-square-shoulder-bag`, measured live at 390×844.
Far more useful than Charles & Keith: same category, same country, same customer.

---

## First, the good news

```
Patchee   Add to Cart at y=1068  →  1.27 screens
Kohthai   Add to Cart at y=1063  →  1.26 screens
```

**You already match them on the metric that matters most.** Their page is twice as long
(8,483px vs your ~4,000px), almost all of it reviews and cross-sell carousels below the fold.
The buy box itself is not better than yours.

So this isn't a rebuild. It's a short list of specific things they do that you don't.

---

## Their buy-column order

```
gallery → In stock → title → ★ 50 reviews → SKU → SAVE 37%
→ SALE ৳2,225 / REGULAR ৳3,531 / YOU SAVE ৳1,306
→ Buy Now Pay Later (orders ৳5,000+)
→ Estimated Delivery: 3–5 Days · Ships from Dhaka · Only 3 left!
→ Colour: named + swatches (each with a -37% badge)
→ quantity → countdown timer → ADD TO CART / BUY IT NOW
→ feature icons → 4-item trust row → a verified buyer quote
→ "Pair It & Shine" cross-sell → description → video carousels → reviews
```

---

## ⭐ The single biggest gap: reviews

```
Patchee   50 reviews on this product · 12,617 site-wide · with photos and videos, sortable
Kohthai    1 review · reviews locked to verified buyers only
```

Nothing else on this list comes close in value. They put a **verified-buyer quote inside the buy
box**, right under the trust row, at the moment of decision. Your page shows an empty star row.

This is a settings-and-process change, not code:
- open reviews beyond verified purchasers, with moderation on
- ask for a review by SMS/WhatsApp a few days after delivery
- seed from the customer photos you already have in WhatsApp and Messenger

## ⭐ Second: your videos should probably be MP4, not YouTube

They run **18 native `<video>` elements and zero YouTube iframes.** Their worn-shots are
720×1280 (9:16), `autoplay muted loop playsinline`.

That means: no YouTube branding, no "Watch on YouTube" button pulling customers off your page,
no end-screen suggestions, no YouTube IFrame API to download, and instant seamless looping.

**`kohthai-shorts-showcase` already supports this.** It accepts a WordPress Media ID or a direct
MP4/WebM/MOV URL and renders a real `<video loop muted playsinline>`:

```
[kohthai_shorts ids="1482" ratio="1:1"]
```

One caveat before you switch everything: their files sit on Shopify's CDN. Yours would sit on
your cPanel hosting. Try **one** product first and check the load on a phone — you're already
behind Cloudflare, which helps. If it's slow, YouTube stays the right answer.

---

## Worth copying

**Price framing.** `SALE ৳2,225 · REGULAR ৳3,531 · YOU SAVE ৳1,306` — three numbers, and the
saving spelled out in taka rather than only a percentage. You show one price. This works
particularly well in this market.

**Specific delivery + scarcity.** "Estimated Delivery: 3–5 Days · **Ships from Dhaka** ·
**Only 3 left!**" — "Ships from Dhaka" quietly answers *is this a dropshipper who'll take a
month*, which is the real fear. Your delivery plugin already computes the estimate; the origin
line and low-stock warning are additions.

**A four-item trust row with sub-lines** — close to what we planned. Theirs:
`100% Authentic / Only genuine products` · `Secure Checkout / Your data is safe` ·
`48h Easy Return / Hassle-free guarantee` · `Guaranteed Delivery / Every order followed up`

**A floating WhatsApp button.** They have one fixed on screen throughout. You have chat buttons
in the buy box, which disappear once she scrolls.

**"Pair It & Shine" — cross-sell of cheap add-ons.** Bag charms, scarves and bracelets at
৳400–800 beside a ৳2,225 bag. That is a serious average-order-value play and it suits your
category exactly. A merchandising decision rather than a design one, but the highest-revenue
idea on this page.

---

## Do NOT copy

**"48h Easy Return / Hassle-free guarantee."** Your policy is a *conditional* 7-day return where
the customer pays return shipping. Copying that wording would be a false promise and would create
exactly the disputes you avoid photo swatches for.

**A countdown timer on every product.** It works, but permanent urgency reads as a discount store
rather than a brand. If you want it for a real campaign, `kohthai-campaign-bar` already does
countdowns.

**SAVE 62% badges everywhere.** Same reason — that is a positioning choice, not a design one, and
it is the opposite direction from "trendy bag company for women".

---

## Suggested order

1. **Reviews** — open them up and start collecting. Biggest gap, no code.
2. **Price framing** — regular/sale/you-save. Mostly a WooCommerce sale-price setting.
3. **"Ships from Dhaka" + low-stock line** — small addition to the buy box.
4. **Test one product with a self-hosted MP4** instead of YouTube.
5. **Floating WhatsApp button.**
6. **Bag charms / accessories as add-ons** — when you're ready to stock them.

None of this changes what we built. The product page layout is sound and already matches them
where it counts.
