# Breo add-on plugins: install on breo.bd

The AUN plugins, ported and redesigned for Breo. Each is a zip in this folder:

| Zip | What it does | Needs |
|---|---|---|
| `breo-bd-store.zip` (2.2.0) | Update: WhatsApp **and** Messenger buttons on product pages, Breo-styled My Account, "Track Your Order" links, and the hook points the plugins below use | nothing new |
| `breo-smart-delivery.zip` | "Delivery" card on product pages with an area picker, a real delivery date under each shipping option at checkout, on the order confirmation and in order emails. Backorder lead times. | Shipping zones |
| `breo-live-tracking.zip` | A **Track Your Order** page: order number or phone → live Pathao timeline | The official **Pathao Courier** plugin, connected |
| `breo-alpha-otp-login.zip` | Log in with phone number + SMS code on My Account | An sms.net.bd API key |
| `breo-social-login.zip` | "Continue with Google / Facebook" | A Google and a Facebook app |

Not ported, on purpose:
- **AUN Chat & Order Notice.** Breo already had WhatsApp ordering, so its useful part (the Messenger button) is now built into breo-bd-store, next to WhatsApp on every product page.
- **AUN EMI Table.** Skipped while the shop is Cash on Delivery only. A Breo version is parked in `breo-emi/` for when SSLCommerz is added.

Install in this order. Each step takes a few minutes.

## 1. Update Breo BD Store to 2.2
1. Plugins → Add New → Upload → `breo-bd-store.zip` → **Replace current with uploaded**.
2. Tools → Breo BD Setup → Step 1 → new field **Facebook Messenger**: your Facebook Page username (the part after `facebook.com/`), or paste the Page link → **Save settings**. Empty = no Messenger button.

## 2. Shipping zones (if not done yet)
WooCommerce → Settings → Shipping → Add zone:
- **Inside Dhaka**: region "Dhaka" (Bangladesh), method *Flat rate*, e.g. named "Home delivery", with your Dhaka charge.
- **Outside Dhaka**: region "Bangladesh", method *Flat rate*, with your outside-Dhaka charge.

Keep Inside Dhaka **above** Outside Dhaka in the list (WooCommerce uses the first zone that matches).

## 3. Breo Smart Delivery
1. Upload and activate `breo-smart-delivery.zip`.
2. **WooCommerce → Delivery Estimates**:
   - For each shipping method, set **min–max working days** and an optional **order cut-off** (after the cut-off, counting starts the next working day). For example Inside Dhaka 1–2 days, cut-off 5:00 PM; Outside Dhaka 2–4 days. A method with blank days shows no date.
   - Under each method, "Customers see today" shows the exact wording.
   - **Holidays**: add Eid, Puja and other closed dates. Fridays are always skipped.
   - **Backorder lead time**: how long a pre-ordered product takes to arrive before it ships (default 7–14 days). One product can have its own time in the *Backorder delivery* box on its edit screen, with an optional daily countdown.
3. Save. Product pages now show a Delivery card under the Buy buttons. Customers can pick their area, and the site remembers the choice.

## 4. Breo Live Tracking
1. Install the official **Pathao Courier** plugin (by Pathao) and connect it with your Pathao merchant account. That's where the tracking data comes from.
2. Upload and activate `breo-live-tracking.zip`. It creates the **Track Your Order** page (`/track-order/`), which then appears in the Support menu, the footer and My Account.
3. Customers type their order number or the phone number they ordered with. Phone and name are partly hidden on the result, and each visitor is limited to 20 lookups per 5 minutes.
4. An order without a Pathao consignment yet shows "Preparing your order". If Pathao can't be reached, the customer still sees their order and a "View full history on Pathao" link.

The tracking ID is read from the Pathao plugin's order field (also the common "tracking number" fields), so nothing extra is needed when you send an order to Pathao.

## 5. Breo Alpha OTP Login
1. Upload and activate `breo-alpha-otp-login.zip`.
2. **Settings → Alpha OTP Login** → **SMS gateway**: paste your sms.net.bd **API key** (and your approved **Sender ID**, if you have one) → Save. If the *Alpha SMS* plugin is installed and set up, its key is used automatically instead.
3. My Account now shows one card, **"Sign in or create your account"**. The customer types a mobile number, presses **Continue** and gets a 6-digit code:
   - **Known number:** they are signed straight in.
   - **New number:** they type their name (email optional) and the account is created and signed in. Switch this off with **New customers** in the settings.
   - **Email and password:** still one tap away via "Use email and password instead"; email sign-up sits under "Prefer email?".
   - Updating from 1.0 changes the old stock wording ("Login with OTP", the old SMS text) automatically. Any label you changed yourself is kept.
4. Test with your own phone before announcing it. Each OTP is one SMS from your sms.net.bd balance.

## 6. Breo Social Login
1. Upload and activate `breo-social-login.zip`.
2. **Settings → Social Login**:
   - **Google**: create an OAuth client (Google Cloud Console → APIs & Services → Credentials → *Web application*). Paste the **Client ID** and **Client secret**, add the redirect URI shown on the settings page to the client, and tick Enable.
   - **Facebook**: create an app (developers.facebook.com → *Facebook Login*). Paste the **App ID** and **App secret**, add the redirect URI shown on the settings page as a *Valid OAuth Redirect URI*, and tick Enable.
3. The buttons appear under the sign-in form on My Account (below the phone login), on the register form, at checkout and in the cart.

## 7. Final check (10 minutes)
- [ ] Open a product: Delivery card, WhatsApp and Messenger buttons.
- [ ] Place a test order: the delivery date shows under the shipping option, then on the order confirmation and in the order email.
- [ ] `/track-order/`: look the order up by its number and by phone.
- [ ] Log out and log in with the OTP on your phone.
- [ ] Try "Continue with Google" and "Continue with Facebook".
