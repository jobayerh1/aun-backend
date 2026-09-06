# Deploy — Kohthai Product Blocks v1.6.2

**File:** `Kohthai\kohthai-product-blocks.zip` · **Tests: 261 passed, 0 failed** · lint clean.
One change. Everything in 1.6.0 and 1.6.1 is untouched.

---

## Why Flatsome does that

It is hardcoded, and there is no Customizer setting for it. This is the theme's own handler,
read off your live page:

```js
jQuery(this).next().is(":hidden")
  ? ( jQuery(this).parent().parent().find(".accordion-title")
        .removeClass("active").next().slideUp(t),   // <- shuts every sibling first
      jQuery(this).toggleClass("active").next().slideDown(t) )
  : ...
```

Opening a closed panel always closes the others first. That is a normal accordion convention, so
it is not a bug in the theme.

## But on your page it *is* a real problem — measured, not guessed

Your Description panel is about **1,450px tall**. Collapsing something that big above the tap
throws the page out from under the customer. On a 390×844 phone, with the Reviews title sitting
comfortably at viewport y=300:

```
tap Reviews  ->  Description collapses
             ->  page height 4388px -> 2940px
             ->  the Reviews title lands at viewport y = -118
```

**The section she tapped scrolled off the top of her screen.** She taps a thing and it vanishes
upward. Flatsome's scroll compensation does not cover collapsing a panel that large above the
click point.

Worth noting: **Fossil doesn't do this either.** In your own screenshot, Product Details *and*
Product Care are both open at the same time.

## The fix, and what it measures now

Sections now open independently.

```
                    BEFORE            AFTER
tap Delivery        title -> y=-118   title stays at y=300
Description         force-closed      stays open
page jump           1,448px           none
```

Also verified on the live page:

- **On load, only Description is open** — unchanged, so your video is still visible with no tap.
- Tapping the **arrow button** works, not just the title text.
- `aria-expanded` stays truthful in both directions (screen readers and Google both read it).
- The video card, which lives inside the Description panel, reopens at **286×286** — correct 1:1,
  not collapsed. Sliders inside a panel need a nudge to re-measure after opening, and that is
  carried over.

## How it's done

A capture-phase listener on `.accordion`. Capture on an ancestor always runs before listeners
bound to the target, so `stopPropagation()` reliably beats Flatsome's per-title handler.

That is why nothing is unbound with `.off()` — **unbinding would be undone the moment the theme
rebinds** (a pjax navigation, for instance). This approach keeps working regardless.

## Install

1. Plugins → Add New → Upload Plugin → replace current.
2. **Clear the WP Rocket cache.**
3. Open a product page and tap Delivery & Returns — Description should stay open and nothing
   should jump.

To turn it off: **Settings → Product Blocks → "Keep accordions open"**.
