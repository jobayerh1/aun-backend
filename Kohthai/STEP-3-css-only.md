# Step 3 — two more CSS blocks (plus one optional)

Same as Step 1: **Appearance → Customize → Additional CSS**, paste, clear WP Rocket, look.
Add them **one at a time**.

---

## 3a — button hierarchy and contrast

Right now Add to Cart is the tan `#a08565` behind white text, which measures **3.48:1** —
below the 4.5:1 minimum for readable text. And Buy Now is the *darker* button, so the
secondary action looks like the main one.

This makes Add to Cart the solid brown (**8.87:1**) and steps Buy Now back to an outline.

```css
/* Primary action: solid brand brown. */
.single_add_to_cart_button,
form.cart button[type="submit"].single_add_to_cart_button {
	background-color: #654321;
	border-color: #654321;
	color: #fff;
}

.single_add_to_cart_button:hover,
form.cart button[type="submit"].single_add_to_cart_button:hover {
	background-color: #4c3219;
	border-color: #4c3219;
	color: #fff;
}

/* Secondary action: outline, same brown. */
.ux-buy-now-button.button.primary {
	background-color: transparent;
	border: 2px solid #654321;
	color: #654321;
}

.ux-buy-now-button.button.primary:hover {
	background-color: #654321;
	color: #fff;
}

/* Visible keyboard focus on both. */
.single_add_to_cart_button:focus-visible,
.ux-buy-now-button:focus-visible {
	outline: 3px solid #ffbc00;
	outline-offset: 2px;
}
```

**If you'd rather push Buy Now as the main action** (it skips the cart and goes straight to
checkout, which suits single-bag orders): swap the two rules — put `#654321` solid on
`.ux-buy-now-button.button.primary` and the outline on `.single_add_to_cart_button`. Either
way the tan fill has to go; that's the part that fails.

---

## 3b — feature icon row on mobile

Flatsome's `.stack-row` computes to `flex-wrap: nowrap`. At 345px available width, five icon
boxes get about 69px each and "Adjustable Strap" breaks to one word per line.

This lets it wrap into a 3-across grid on phones only. Desktop is untouched.

```css
@media (max-width: 549px) {
	.product-short-description .stack-row {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 14px 6px;
	}

	.product-short-description .stack-row > br {
		display: none;
	}

	.product-short-description .stack-row .icon-box {
		margin: 0;
	}
}
```

**Check on a phone.** The five icons should sit 3 + 2 across two rows, with full labels.

If it looks cramped at 3, change `repeat(3, 1fr)` to `repeat(2, 1fr)`.

---

## 3c — optional: hide "SKU: N/A"

Your variable products have no SKUs, so the page prints `SKU: N/A` to customers.

```css
.product_meta .sku_wrapper {
	display: none;
}
```

⚠️ This hides the SKU row on **every** product. Only use it if you never intend to show
SKUs. If you plan to add real SKUs later, skip this one — we'll do it properly in PHP so it
hides only when the value is actually empty.

---

## Still open after this step

- **Empty `<h5>` flood** — must be fixed in the shortcode text itself (Step 4).
- **Reviews locked to verified buyers** — a WooCommerce setting, not code.
- **Out-of-stock product leading Related Products** — needs a small filter.
- **Breadcrumb reading "Ladies Bag Price in Bangladesh"** — category display name.
- **Share icons** — Twitter/LinkedIn instead of WhatsApp. Flatsome has a setting for which
  icons appear; look under Customize → Social before we write anything.
