# AUN Rewards — the plan (v2, decisions locked)

**Invite friends + reward owners who buy again.** One programme, one wallet, website and showroom.

_v1 2026-09-25 (options) → v2 2026-09-25 (decisions locked) → built 2026-09-25: app-api 1.116.0, app 2.6.0+142, help centre 2.8.0._

---

## 0. Where we started (read from the code)

**Invite** is live (app-api `class-aun-app-referrals.php`): the friend gets **5%** off their first
order, the inviter gets **5% of the friend's order** as a coupon once it is delivered. Rules: new
customers only · one code per phone ever · claim within 30 days of joining the app · 5 friends per
inviter per month · welcome discount 90 days · reward 365 days · clawback on refund.

**What was wrong:**

1. **Website-only.** A friend buying at the **showroom** got no discount and the inviter no reward —
   the programme never saw ERP sales.
2. ⚠️ **Loophole:** "new customers only" checked website orders and registered projectors, **not
   showroom purchases** — a past walk-in buyer could claim a friend's welcome discount.
3. **Two codes.** The friend gets `K7PQ2M` but checks out with `WELCOME-XXXXXX`; typed at checkout
   the invite code got *"Coupon does not exist"*.
4. The invite link opened the **homepage**.
5. No help anywhere (website, help centre, WhatsApp replies); "Invite a friend" only in Settings.
6. **Nothing for a customer buying their second projector.**

---

## 1. Decisions (locked)

Margin is **~20%**. The rule that sets every number below: **no sale ever gives away more than
10% — half the margin.**

| # | Decision | Why |
|---|---|---|
| 1 | **Invite: friend 5%, inviter 5%** of the friend's order (already set) | A new customer's first order gives away exactly 10% |
| 2 | **Owner Reward: 5% off the next projector** — one unit, projectors only, **not on sale items** | Same "5%" as Invite — one simple story. Leaves 15% on a repeat sale. Sale items excluded so a campaign discount and this never stack past the margin |
| 3 | **Rewards together: at most 10% off any order** | Half the margin, the same ceiling as a new customer's first order. A reward that would pass it is not refused: what fits is used and the rest is split into a new code (same expiry, same phone lock) — nothing is lost (built 2026-09-25) |
| 4 | **Only purchases from AUN earn** — website or showroom. Dealer-sold projectors don't; ERP **Dealers-group** buyers never earn | Protects the dealer channel |
| 5 | **No launch gift / no backfill** — Owner Rewards are earned by purchases from launch day | No extra cost at launch |
| 6 | **Showroom included** — ERP-verified, OTP-confirmed (§5) | That's where many customers buy |
| 7 | Owner Reward **valid 12 months, one at a time** — a new purchase extends it instead of adding a second | Twice-a-year buyers are rewarded twice; nobody accumulates a pile |

---

## 2. Principles

1. **Personal, never public.** Every reward is locked to one OTP-verified phone.
2. **Earned credit combines; promotions don't.** Invite rewards + Owner Reward can be used together
   (within the 10% ceiling). Seasonal/shop codes never combine with anything.
3. **Tell people the rules before they hit them.**
4. **Works where people buy** — website and showroom.
5. **Budget-safe:** caps, the 10% ceiling, expiry, clawback on refunds/returns.
6. **Never break a promise already made** — coupons already issued keep their value and expiry.
7. **The server decides, the app displays.**

---

## 3. Programme A — Invite (fixes)

- **Showroom counts both ways:** the friend's welcome discount can be redeemed at the showroom, and
  the inviter is rewarded for a showroom purchase (after a 7-day return hold, ERP-checked).
- **Close the loophole:** "new customer" also checks the ERP for past showroom purchases.
- **Checkout recognises an invite code** typed into the coupon box and explains what to do.
- **Invite link → landing page** `/refer/?code=K7PQ2M` (Bangla + English): the code, three steps,
  the app button, the rules. The same page is the programme's guide.
- **Refusals point somewhere:** an existing customer entering a friend's code is shown their own
  ways to earn instead of a closed door.
- **"Paste" button** on the code field — the code usually arrives in WhatsApp.

## 4. Programme B — Owner Rewards (new)

- **Earn:** a projector bought from AUN is delivered (website) or sold (showroom, ERP) → one reward.
- **Value:** 5% off one projector, not on sale items. **Valid 12 months.** One at a time — buying again
  extends it.
- **Use:** website checkout (code, auto-listed in the app) or showroom (staff screen).
- **Clawback:** the earning order refunded/cancelled, or the ERP sale returned → an unused reward is
  withdrawn (or shortened to the previous purchase's date).
- **Told by:** push + in-app notice for app users, **SMS** for everyone (English, one segment).
  A reminder 30 days before it expires.

## 5. The showroom

Walk-in sales are entered in the ERP; staff apply a discount there by hand. The plugin gives them a
phone-friendly **Rewards → Showroom** screen:

1. Type the customer's phone → an **OTP is sent to the customer**; they read it out → verified.
   (Nobody can spend someone else's reward by typing their number.)
2. The screen shows every valid reward **and the customer's ERP purchases**.
3. **Redeem:** pick the rewards, type the projector price → it shows the exact discount (all rules and
   the 10% ceiling applied) → staff enter that discount in the ERP sale → type the invoice number →
   **Redeem**. The coupon is marked used everywhere, so it can't be used on the website too.
4. **Earn:** each eligible ERP projector sale (from launch, not a dealer, not returned) has an
   **Issue reward** button — and for app users it happens automatically.

## 6. Combining — the rulebook

| | Welcome | Invite rewards | Owner Reward | Shop code |
|---|---|---|---|---|
| **Welcome** | — | ❌ | never meets it | ❌ |
| **Invite rewards** | ❌ | ✅ | ✅ | ❌ |
| **Owner Reward** | — | ✅ | one at a time | ❌ |
| **Shop code** | ❌ | ❌ | ❌ | one only |

**Ceiling:** rewards together ≤ **10%** of the order subtotal. Checked when a reward is applied and again
at order placement. Invite rewards give way first (latest applied first): what fits is used, the rest
becomes a new code in the wallet. The owner reward (a %) is taken off only if it cannot fit alone.

---

## 7. Surfaces

**App** — "Invite a friend" becomes **Rewards**: wallet (every reward: value, expiry, where to use,
copy code) · invite friends (share link to the landing page) · "I have a code" (+ Paste) · how it works
+ link to the web guide. A Home card when a reward is waiting or about to expire. All strings EN + BN.

**Website** — `/refer/` landing page + guide (auto-created, bilingual, works under `/bn/`) · checkout
recognises invite codes · the 10% ceiling with clear messages.

**Admin** — Settings: Owner Rewards (on/off, %, validity, projector categories, sale items, SMS),
ceiling, showroom hold · **Rewards** page: showroom screen, report (issued / used / expired / revoked /
money given / outstanding), ledger.

**Support** — help-centre entries (website help page + the app's Help tab) · WhatsApp quick replies.

---

## 8. Abuse controls

| Risk | Control |
|---|---|
| Sharing a discount | Every reward locked to one OTP-verified phone; showroom redemption needs the customer's OTP |
| Second welcome discount | One claim per phone, ever |
| Past showroom buyer posing as new | ERP check at claim ⟵ **new** |
| Self-referral / farming | Account + phone checks · 5 per month |
| Buy → reward → return | Clawback on refund (web) and on ERP return (showroom) |
| Reselling | One unit per Owner Reward · dealers excluded |
| Stacking into a giveaway | 10% ceiling |
| Double use (web + showroom) | One coupon record, marked used everywhere |
| Nobody noticing | Every issue / redeem / revoke logged and reported |

## 9. Measuring it

Baseline first: today's rate of customers buying a second projector within 12 months. Then: invite
claims → purchases; cost per new customer; repeat-purchase rate vs baseline; % of rewards used;
discounts as % of revenue; outstanding liability. First-party analytics only.

## 10. Build order

| Step | What | Protects |
|---|---|---|
| 1 | **Referral characterization test** on the unchanged code | Everything that works today |
| 2 | Invite fixes (ERP loophole, checkout invite-code message, share link, landing page) | — |
| 3 | Owner Rewards engine (table, earn, revoke, SMS/push, expiry reminders) | — |
| 4 | Combining rules + 10% ceiling | — |
| 5 | Showroom screen (OTP, ERP-verified earn + redeem, inviter reward after hold) | — |
| 6 | App: Rewards hub, Home card, notifications, EN/BN | — |
| 7 | Admin report · help-centre + WhatsApp content | — |

Every step: bench tests, the characterization test re-run, versioned builds.
