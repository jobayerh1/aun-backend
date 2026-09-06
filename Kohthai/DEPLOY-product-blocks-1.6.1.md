# Deploy — Kohthai Product Blocks v1.6.1

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 247 passed, 0 failed** · lint clean.
One change only. Everything in v1.6.0 is untouched.

## The change: Delivery & Returns moves above the reviews

Measured on your live page:

```
BEFORE   Description  ->  Reviews (1)  ->  Delivery & Returns
AFTER    Description  ->  Delivery & Returns  ->  Reviews (1)
```

### Why it matters more than it looks

**The reviews list grows.** It is 93px tall today with one review on it. Every review you earn
from here pushes your delivery and returns terms further down the page — permanently. Terms are
what a first-time buyer checks before paying cash on delivery to a shop she has never used.
They cannot be the thing that quietly drifts out of reach.

**And reviews belong last on principle.** They are the customer's words, not the shop's. Your own
Fossil screenshot ends the page with Reviews and Questions. Charles & Keith keeps Shipping &
Returns up with the product information.

### How

`woocommerce_product_tabs` filter, priority 25 — after the description (10), before the reviews
(30). Set by filter rather than by editing the theme, so a Flatsome update cannot undo it. It only
touches the tab if it exists, and leaves every other tab's title and priority exactly as found.

Verified in the live DOM: **Description stays first and stays open by default.**

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Reload a product page and check the accordion order.

Nothing to configure. No content to move.
