# Deploy — Kohthai Product Blocks v1.6.8

**Tests: 303 passed, 0 failed** · lint clean.

# ⚠️ ORDER MATTERS — install this plugin BEFORE changing the Customizer setting

**1. Install v1.6.8, clear the WP Rocket cache.**
**2. Then** Customize → WooCommerce → Product Page → Swatches → size **Large → X-Large**.

If you switch to X-Large while still on 1.6.7, **every colour name disappears** — see below.

---

## You were right on both counts

**The gap was too wide.** 32px was a workaround, not a fix. The label needs about 72px of room and
the Large swatch is 45px, so I was making up the 27px difference by pushing the swatches apart.
That is why the row looked stretched.

**And the theme already has the setting.** At **X-Large the swatch is 70px**, and the theme's own
10px gap is then already enough (70 + 10 = 80, against a 72px label). The fix stops being a
workaround entirely — the swatches sit at a normal spacing because they are the right size.

It is also better on its own terms: these swatches are **photographs of the real bag** rather than
colour chips, which is deliberate so a shade cannot be disputed. At 45px, three crops of the same
bag are indistinguishable. At 70px you can see which one you are choosing.

⭐ **The swatch size is not set in my CSS.** It stays in the Customizer where you can see and change
it. This version just makes the labels work at whichever size you pick.

## ⚠️ The trap this version removes

Flatsome renders a different class for each size:

```
Large    ->  .ux-swatches--large
X-Large  ->  .ux-swatches--x-large
```

Every version up to 1.6.7 named only `--large`. **So switching the Customizer to X-Large would have
silently deleted every colour name on the site** — no error, no warning, they would just stop
appearing. v1.6.8 matches both, and there is now a test that fails if the `--x-large` selector is
ever dropped.

## Measured on your live page, with the exact CSS this plugin emits

**X-Large, desktop:** swatch 70px · gap 10px (the theme's own) · pitch 80 · label 72 · 8px clear.
**X-Large, 390px phone:** gap drops to 8px under 550px, so four swatches need 304px against the
308px column and stay on **one row**. At the theme's 10px they need 310px and broke 3+1.

Five colours will still wrap to a second row, and should.

**Large is still fully supported** at a 30px gap, in case you prefer it — nothing breaks if you
leave the setting alone.
