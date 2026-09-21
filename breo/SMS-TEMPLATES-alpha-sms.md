# Breo order SMS texts for Alpha SMS

Paste each text into **Alpha SMS → Settings → Order status SMS**, next to the matching status, and tick that status.

Every text stays within **one SMS (160 characters)** with a long name, a 6-digit order number and a 6-figure amount. They use only plain SMS characters. Don't add **৳**, curly quotes (’ “ ”) or long dashes (—): one of those turns the whole message into Unicode, where one SMS holds only 70 characters, so it would be split into 2–3 SMS and cost 2–3 times as much. Write **BDT** instead of ৳.

Placeholders Alpha SMS fills in: `[billing_first_name]` `[order_id]` `[order_currency]` (BDT) `[order_amount]` `[order_status]` `[store_name]` `[order_date_created]` `[order_date_completed]`.

| Status | SMS text | Longest possible |
|---|---|---|
| **Pending payment** | `Hi [billing_first_name], your Breo order #[order_id] of [order_currency] [order_amount] is waiting for payment. Pay at breo.bd/my-account or call us.` | 120 |
| **Processing** (order confirmed) | `Hi [billing_first_name], thank you! Your Breo order #[order_id] of [order_currency] [order_amount] is confirmed and being packed. Track: breo.bd/track-order` | 127 |
| **On hold** | `Hi [billing_first_name], we received your Breo order #[order_id]. It is on hold until your payment is confirmed. We will update you soon.` | 126 |
| **Completed** | `Hi [billing_first_name], your Breo order #[order_id] is complete. Enjoy! Your warranty starts today. Need help? Visit breo.bd/contact` | 122 |
| **Cancelled** | `Hi [billing_first_name], your Breo order #[order_id] has been cancelled. If this is a mistake, call or WhatsApp us and we will fix it.` | 123 |
| **Refunded** | `Hi [billing_first_name], your Breo order #[order_id] has been refunded. It can take a few working days to reach you. Help: breo.bd/contact` | 127 |
| **Failed** | `Hi [billing_first_name], payment for your Breo order #[order_id] did not go through. Try again at breo.bd/my-account or choose Cash on Delivery.` | 133 |
| **Admin: new order** (to your own phone) | `New Breo order #[order_id]: [order_currency] [order_amount] from [billing_first_name]. Status: [order_status]. Check it in WooCommerce.` | 107 |

Notes:
- **Refunded** has no amount on purpose: `[order_amount]` is the order total, not the refunded amount, which would be wrong for a partial refund.
- **Completed** says the warranty starts today, which assumes you mark an order Completed once it is delivered. If you mark it Completed when it ships, use instead: `Hi [billing_first_name], your Breo order #[order_id] is on the way! Track it at breo.bd/track-order. Questions? Visit breo.bd/contact` (114).
- If `[order_amount]` shows decimals (e.g. 12500.00), set **WooCommerce → Settings → General → Number of decimals** to 0.

## Alternative: the "Breo voice" set (warmer, wellness tone)
Same rules: one SMS each, plain characters only. The longest-possible figure is with a 13-letter name and a 6-digit order number.

| Status | SMS text | Longest possible |
|---|---|---|
| **Shipped** (needs a "Shipped" order status) | `Breo: Good news, [billing_first_name]! Order #[order_id] is on its way. Relief is only a few days away. Track it anytime at breo.bd/track-order` | 132 |
| **Processing** | `Breo: Thank you, [billing_first_name]! Order #[order_id] is confirmed and being packed with care. Your moment of calm is on its way soon.` | 126 |
| **Completed** | `Breo: Your order #[order_id] has arrived. Take a deep breath and enjoy your first session! Your warranty starts today. Help: breo.bd/contact` | 136 |
| **Pending payment** | `Breo: Your order #[order_id] of [order_currency] [order_amount] is waiting for payment. Pay at breo.bd/my-account, then sit back and relax.` | 114 |
| **On hold** | `Breo: We have your order #[order_id], [billing_first_name]. It is on hold until your payment is confirmed. We will update you soon.` | 120 |
| **Cancelled** | `Breo: Order #[order_id] has been cancelled. Changed your mind or need help choosing? We are always here for you: breo.bd/contact` | 124 |
| **Refunded** | `Breo: Your refund for order #[order_id] is done. It can take a few working days to reach you. Your wellbeing matters to us, always.` | 127 |
| **Failed** | `Breo: Payment for order #[order_id] did not go through. No stress! Try again at breo.bd/my-account or choose Cash on Delivery.` | 122 |
