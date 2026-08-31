# Getting real reviews — the plan

## Why not the generated ones

I won't build a plugin that invents customer names, dates and review text. Beyond it being
illegal here — Bangladesh's Consumer Rights Protection Act 2009 covers false representation about
goods, and it's what Google's spam policy calls fake engagement — the practical risk is the part
that matters to you:

- **Google penalties apply to the whole domain**, not the reviews. If review structured data is
  found to be fabricated, you lose rich snippets sitewide and possibly rankings. That's the
  traffic you're spending on ads to replace.
- **It's easy to spot on a small shop.** Twenty reviews appearing across products that have sold
  a handful of units, all written in one voice, with no photos, is a pattern anyone comparing you
  to Patchee will notice.
- **One screenshot ends it.** A customer who recognises a fake review posts it in a Facebook
  group, and you're rebuilding trust from below zero. In a market where the main objection already
  is "is this seller real", that's the one risk not worth taking.

And you don't need to. **You already have the reviews — they're sitting in your WhatsApp.**

---

## What you actually have

Every customer who messaged "bag ta onek shundor" or sent a photo wearing it has written you a
review. It just isn't on the product page. Moving real messages onto the site, with the customer's
name and permission, is completely legitimate — it's a testimonial, and it's what every brand does.

That's a very different thing from inventing "Anika Rahman, 3 weeks ago".

---

## Four steps, in order of return

### 1 · Open reviews up — 2 minutes, no code

**WooCommerce → Settings → Products → Reviews**, uncheck *"Reviews can only be left by verified
owners."*

Right now this is why you have one review. It's a chicken-and-egg trap: no reviews → no trust →
no orders → no reviews. Leave moderation on so nothing publishes without you.

### 2 · Ask every customer, automatically

This is the whole game. Patchee has 12,617 reviews because they ask, every time, not because
they're luckier than you.

You already have **Alpha SMS** wired up for OTP login. The same channel can send, 3 days after an
order is marked Completed:

> Hi {name}, hope you're loving your Kohthai bag! Would you share a photo and a line about it?
> {short link} — it takes 30 seconds and really helps other customers.

I can build this as `kohthai-review-requests`: fires on order completion + N days, sends via
Alpha SMS or WhatsApp, one message per order, never twice, with an admin log. Ten to fifteen
percent reply is normal — on 100 orders a month that's 10–15 real reviews a month, compounding.

### 3 · Bring the WhatsApp messages over

An admin screen where you paste a message you actually received, set the customer's real name and
the real date, and it becomes a proper review on that product.

The rules that keep it honest, and I'd build them into the tool:
- their words, not rewritten
- their real first name and area
- the real date you received it
- ask first — *"can I put this on the website?"* — almost everyone says yes

That is importing genuine feedback. It is not the same thing as generating it.

### 4 · Photos, because they're worth more than stars

Patchee's reviews carry customer photos and videos, and that's what makes their review section
convincing rather than decorative. A photo of the bag in a real Dhaka living room does more than
fifty text reviews.

Offer something small for a photo review — a bag charm, 5% off the next order. Ask in the same
SMS.

---

## What to display, and when

- **Hide the star row entirely until a product has reviews.** Five empty stars reads as "nobody
  bought this" — worse than showing nothing. This is already in the Revision 3 design.
- Once reviews exist: **rating + count under the title**, and **one verified-buyer quote inside
  the buy box**, which is the highest-value placement on the page.
- The full review list with photos goes below, in its own accordion.

---

## Realistic timeline

| | |
|---|---|
| Today | Open reviews up. Message your last 30 customers by hand and ask. |
| Week 1 | 5–10 real reviews from that list alone. |
| Week 2 | Automated SMS request live, running on every new order. |
| Month 2 | 20–40 reviews, several with photos. Star rows switch on. |
| Month 6 | A few hundred, and it maintains itself. |

Slower than generating them this afternoon. It's also the version that still exists in a year,
and the only one where the reviews actually tell you something about your products.

---

## Want me to build it?

`kohthai-review-requests` — the SMS-after-delivery asker plus the import tool from step 3 — is the
piece that does the heavy lifting. Say the word and it's next after the trust row.
