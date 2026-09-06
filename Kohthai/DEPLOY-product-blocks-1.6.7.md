# Deploy — Kohthai Product Blocks v1.6.7

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 300 passed, 0 failed** · lint clean.
One fix: the swatch gap from 1.6.6, which never actually took effect. You were right.

---

## What went wrong

Measured on your live page after 1.6.6:

```
label width       72px   ✓ applied
white-space       normal ✓ applied
font-size         11px   ✓ applied
margin-bottom     38px   ✓ applied
column-gap        10px   ✗ NOT applied  -- still the theme's value
=> pitch          55px
=> overlap        72 - 55 = 17px
```

Every declaration landed except the one that mattered. **Flatsome's
`flatsome-swatches-frontend.css` sets `gap` on `.ux-swatches` at exactly the same specificity as
my rule, and it loads after this plugin's inline style** — so the cascade went to source order and
the theme won.

## My mistake, plainly

**I verified 1.6.6 with `!important` on every line, and then shipped it without.** So what I
measured was not what I sent you. That is the whole story, and it is why you saw the overlap
within seconds of installing.

The test did not catch it either, because it only reads the CSS this plugin emits — it cannot see
another stylesheet winning the cascade. That is a real limit of the harness, and worth knowing:
**a passing test here proves the rule was written, not that it won.**

## The fix

Specificity, not `!important`:

```css
.ux-swatches.ux-swatches--large { column-gap: 32px }
```

The doubled class is `(0,2,0)` and beats the theme's `(0,1,0)` **regardless of load order**, which
`!important` would also have done but more bluntly. It also leaves the shop-page swatches alone —
those are `.ux-swatches-in-loop` at 30px and carry no labels at all, which I checked.

**Measured with the plugin's exact emitted CSS, nothing hand-typed:**

```
column-gap  32px
pitch       77px
label       72px
clear space  5px      no overlap
```

Both long names wrap to two tidy lines, all four swatches stay on one row at 390px, and there is
no page overflow.

## New test

There is now an assertion that the gap rule uses the **doubled class**, and a second one that
fails if it is ever written as a bare `.ux-swatches{column-gap:…}` again. That is the assertion
that would have caught this.

## Install

Upload, replace, **clear the WP Rocket cache**, and look at the Elegant PU Leather Handbag again.
