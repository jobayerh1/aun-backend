# Deploy — `kt-sync-dimensions.php` (one-shot, then delete)

**Tests: 56 passed, 0 failed** on the local WP + WooCommerce 10.9.4 bench · lint clean.

Makes WooCommerce's dimension fields agree with the size text already printed on each
product page. Run it once, read the report, run it again with `&apply=1`, delete the file.

---

## What it does, and why it isn't a list of numbers I typed

Every product page already prints its correct size through `[kt_details]`. Rather than
me hand-copying 12 corrections into a script — where a typo becomes a wrong bag size,
silently — **the script reads each product's own `[kt_details]` block and writes those
numbers into WooCommerce.** The page stays the source of truth. If a page has no size
block, the product is reported and left completely alone. The script never invents a
number.

## The mapping it applies

Your page prints `Length · Height · Width`. WooCommerce's three boxes are ordered
`Length · Width · Height`. On most products the numbers were typed straight down in
reading order, so depth and height ended up swapped:

```
page "Length"  = across  ->  woo length
page "Width"   = depth   ->  woo width      <- the two that were transposed
page "Height"  = tall    ->  woo height
```

## Steps

1. Open `kt-sync-dimensions.php` and change `$KEY = 'kt-dim-sync-2026';` to something
   only you know.
2. Log in to WordPress as administrator, in the same browser.
3. Upload the file to the WordPress root — the folder that contains `wp-load.php`
   (cPanel File Manager → `public_html`).
4. Visit `https://kohthaibd.com/kt-sync-dimensions.php?key=YOURKEY`
   **This is a dry run. It writes nothing.** Read the report.
5. Happy with it? Visit the same URL with `&apply=1` on the end.
6. **Delete the file from the server.**

## Safety

- Wrong or missing `key` returns a plain 404 — the file looks like it isn't there.
- **Dry run is the default.** Writing needs `&apply=1` *and* a logged-in shop manager, so
  a leaked URL cannot rewrite your catalogue.
- Refuses to run if the store's dimension unit isn't `cm`, which would otherwise store
  centimetre numbers as inches.
- Refuses any value ≤ 0 or > 200 cm and reports it instead of writing it.
- Uses WooCommerce's own CRUD setters, so the product lookup tables and object cache stay
  consistent — not raw `update_post_meta`.
- Clears the WP Rocket cache automatically when it changes anything, because your pages
  carry these numbers in their schema markup.
- Safe to run twice. The second run reports "already correct" and writes nothing.

## What it will NOT touch

- **Weights.** Reported only. They affect courier charges, so they're your call.
- **Products with no `[kt_details]` block.** Listed under "NEEDS A TAPE MEASURE".
- **Variations that carry their own dimensions.** These silently beat the parent product,
  so the report warns you about each one rather than pretending the parent fix covered it.

## Expect these in the report

**Five bags have no size block on the page**, so there is nothing to trust and nothing to
copy. They need measuring — across × tall × deep, in cm — and a `[kt_details]` block added,
then run the script again:

- Wave Pattern Small Square Crossbody
- Vintage Tassel Soft PU Messenger
- Elegant Flap PU Crossbody ← its stored `30 / 21 / 13` looks inherited from another product
- Elegant Evening Clutch
- Magnetic Flap PU Shoulder

**One bag's stored numbers belong to a different product.** Soft PU Leather Shoulder Bag is
stored as `30 / 21 / 13`, but its own page and `CONVERT-11` both say **28 × 18 × 10**. Three
products currently share `30 / 21 / 13`; only Elegant PU Handbag genuinely is that size. The
script fixes this from the page automatically.

**Two bags state a width range**, which one number cannot describe:

- Trendy PU Leather Tote — 31–37 cm
- Fashionable PU Leather Shoulder — 22–27 cm

The script stores the larger end and flags both. Worth deciding how the size preview should
draw a slouchy bag before that feature is built.

## Why this matters before anything else gets built

These fields aren't shown on your product pages, so **this is not what customers are seeing
today** — the visible text is right. What they do feed is schema.org and Google Shopping,
and they would feed the size-preview feature. A size preview drawing from transposed data
would render bags lying on their side, confidently. That's worse than no feature at all.

---

**Test harness:** `test-kt-sync-dimensions.php`, run on the local WP + WooCommerce bench.
It builds products reproducing every real case here — transposed, already-correct, decimal
depth, width range, wrong-record, no block, absurd value, shortcode in postmeta, and a
variation override — then asserts the stored dimensions after a dry run and after apply,
and that a second run is a no-op.
