# Deploy — Kohthai Product Blocks v1.6.3

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 277 passed, 0 failed** · lint clean.
One change. Everything in 1.6.0–1.6.2 is untouched.

## Your call, and it was the right one

You said: *write the data in the field, and you put them in the correct position.* That is exactly
how it should work. The field is where the **data** belongs; where the row **appears** is a design
decision, and design decisions belong in code — not in which box you happened to paste a
shortcode into.

v1.6.0 had the field rendering under the Add to Cart button, where the trust row, delivery
estimate and chat buttons already stack four blocks deep in a 360px column. Step 10 had decided
the icons belong at the top of the details block. **The instruction and the code disagreed, and
the code was the wrong half.** Fixed.

## What changes

- The **Feature icons** field now renders at the **top of the details block**.
- **The duplicate trap is gone.** Before, the field and the `[kt_features]` shortcode rendered in
  two different places, so using both silently produced two rows and the docs had to warn you
  about it. Now, if the description contains `[kt_features]`, the field is skipped automatically.
  One row, whichever you use. You cannot get it wrong any more.
- A **"Where it appears"** dropdown in Settings → Product Blocks, if you ever want the old
  placement back.

## How

The description tab's own callback is wrapped: the icon row is echoed first, then WooCommerce
renders the description exactly as it always did. **No rendered HTML is read, matched against, or
rewritten** — post-processing Flatsome's output is what took the live page down at v1.0.0, and
this does not go near it.

Tests cover both placements, the duplicate guard, that the original description callback still
runs, that the icons print *before* it, and that a tab set with no description tab passes through
without a fatal.

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Then do product 1 — see `CONVERT-01-versatile-soft-pu.md`, which is updated for this version.

Nothing to configure. The new default is the recommended placement.
