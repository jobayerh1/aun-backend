# Breo BD Store 2.2: install on breo.bd

## Updating from 2.1 to 2.2
1. Plugins → Add New → Upload → `breo-bd-store.zip` → **Replace current with uploaded**.
2. Tools → Breo BD Setup → Step 1 → **Facebook Messenger**: your Facebook Page username → **Save settings**.

What 2.2 adds: WhatsApp and Messenger buttons side by side on product pages; a Breo-styled My Account (login and dashboard, no Downloads tab); "Track Your Order" in the Support menu, footer and My Account once Breo Live Tracking is active; and the hook points the new Breo plugins use. Installing those plugins is covered in `DEPLOY-breo-plugins.md`.

## Updating from 2.0 to 2.1
1. Plugins → Add New → Upload → `breo-bd-store.zip` → **Replace current with uploaded**.
2. Tools → Breo BD Setup → **Step 2: Import / update products** again. It only downloads the new files: box-content photos, About and policy page photos, and the manual image.
3. **Step 3: Build site pages** again, with "Use the Breo logo as the site icon" ticked. Pages you edited yourself are left alone.
4. Tick "show prices without decimals" in Step 3 if prices still show ".00".

What 2.1 fixes: mobile cut-off on product pages; Buy now buttons (sticky bar, product hero, homepage) now go straight to checkout; image heroes and "at a glance" cards on every page; a richer About page; the favicon; real photos for box contents; no third-party emoji script; better text contrast.

---


Everything is in one plugin: `breo-bd-store.zip`. There's no HTML to paste. Every page is created for you.

## 1. Install (2 minutes)
1. WordPress admin → **Plugins → Add New → Upload Plugin** → choose `breo-bd-store.zip` → **Install** → **Activate**.
   - Updating from v1? WordPress will ask to **replace** the old version. Say yes.
2. The theme stays as it is (Extendable, free). **No theme purchase is needed.** The plugin draws its own header, footer, homepage, product pages and policy pages, and it would look the same on any theme.

## 2. Tools → Breo BD Setup
Run the three steps **in order**:

**Step 1: Business details & policy numbers.** Fill these in, then **Save settings**:
- Company / legal name, office or service address, phone, **WhatsApp** (digits, e.g. `8801XXXXXXXXX`), email
- Facebook / Instagram / YouTube / TikTok links (optional)
- Policy numbers. They are pre-filled from AUN's live policies (same group). **Change any that differ for Breo:**

| Setting | Pre-filled | Where it appears |
|---|---|---|
| Warranty | 12 months | Warranty Policy, FAQ, product pages, homepage |
| Report damaged/faulty within | 3 days | Returns, Shipping, FAQ |
| Change-of-mind (unopened) within | 24 hours | Returns, FAQ |
| Refund within | 7 business days | Returns |
| Delivery inside / outside Dhaka | 2 / 4 working days | Shipping, FAQ, homepage |
| Order cut-off | 6:00 PM | Shipping, FAQ |
| Payment methods | Cash on Delivery | Shipping, Terms, FAQ, homepage |
| Delivery charges | (empty = "shown at checkout") | Shipping, FAQ |

**Step 2: Import the launch products.** Type the Taka prices (Sale is optional), then click **Import / update products**.
- It downloads about 45 photos and 6 short videos **into your own Media Library**. Nothing loads from Breo's or anyone else's servers afterwards.
- Each row shows progress. If a row fails (slow hosting), click **Retry**. Finished files are never downloaded twice.
- Re-run it any time you change a price. It updates the products and never duplicates them.

**Step 3: Build the site.** Leave all the boxes ticked and click **Build site pages**. This:
- creates the About Breo, Contact, FAQ, Warranty Policy, Shipping & Delivery, Returns & Refunds, Privacy Policy and Terms & Conditions pages,
- makes the Breo homepage the front page,
- sets the currency to ৳ (no decimals), links the Privacy Policy and adds the "accept the Terms" checkbox at checkout,
- moves WordPress's "Sample Page" and "Hello world!" to drafts (not deleted).

## 3. Before you announce the site
- [ ] Check "What's in the box" on each product page against your actual samples.
- [ ] Set up delivery charges: WooCommerce → Settings → Shipping (Inside Dhaka / Outside Dhaka zones).
- [ ] Set up payment methods: WooCommerce → Settings → Payments (Cash on Delivery and, later, SSLCommerz, bKash and so on).
- [ ] Place one test order from your phone.
- [ ] Settings → General: make sure the Site Title is **Breo Bangladesh** (browser tabs and Google use it).

## Editing later
- **Prices, stock:** edit the product in WooCommerce as normal (or re-run Step 2).
- **Policy text:** edit the page in Pages. The plugin will never overwrite a page you have edited.
- **Contact details / policy numbers:** change them once in Step 1, and every page updates.
- **Product page copy and layout:** these live in the plugin (`includes/products-data.php`). Send changes to Claude.

## Why no theme purchase
Breo's own site uses a custom design: a thin dark header with a product mega menu, and full-screen photo and video sections. No theme gives you that out of the box, Flatsome included, and Flatsome would add page weight and its own header on top. The plugin replaces the header, footer and all Breo pages itself, so the free theme is enough. If you ever switch to Flatsome, the Breo pages keep working unchanged.
