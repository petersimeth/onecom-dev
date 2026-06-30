# Stripe Payments Setup — ShopSignal (onecom.io)

The Stripe subscription flow is **already built** — Checkout, the customer
billing portal, the webhook handler, and the Pro plan gating all exist. This
guide is just the configuration needed to switch it on: get your keys, create
the Pro price, point a webhook at the app, and test.

Plan on ~20 minutes. Do it in **test mode** first, then repeat the two values
that differ for **live mode**.

---

## What's already in the code

| Piece | File | What it does |
|-------|------|--------------|
| Start checkout | `checkout.php` | Creates a Stripe Checkout session, redirects the user to pay |
| Manage billing | `billing-portal.php` | Opens the Stripe customer portal (update card, cancel) |
| Sync from Stripe | `stripe-webhook.php` | Verifies signatures and updates the user's plan on Stripe events |
| Upgrade buttons | `pricing.php`, `profile.php` | "Upgrade to Pro" / "Manage billing" |

You only need to fill four config values in `config.local.php`:

```php
'stripe_secret_key'     => '',   // sk_test_… then sk_live_…
'stripe_pro_price_id'   => '',   // price_…
'stripe_pro_price_label'=> '$29 / month',
'stripe_webhook_secret' => '',   // whsec_…
```

`config.local.php` is gitignored, so these secrets never enter the repo.

---

## Step 1 — Get your secret API key

1. Sign in at <https://dashboard.stripe.com>.
2. Make sure **Test mode** is ON (toggle, top-right) while setting up.
3. Go to **Developers → API keys**.
4. Copy the **Secret key** (`sk_test_…`).
5. Put it in `config.local.php` as `stripe_secret_key`.

> Only the *secret* key goes server-side. There's no publishable key needed here
> because the app uses Stripe-hosted Checkout (the user is redirected to Stripe).

---

## Step 2 — Create the Pro product and recurring price

1. **Product catalog → Add product**.
2. Name it e.g. **ShopSignal Pro**.
3. Under pricing, choose **Recurring**, set the amount and interval
   (e.g. **$29 / month**), and save.
4. Open the price and copy its **Price ID** (`price_…`).
5. Put it in `config.local.php` as `stripe_pro_price_id`, and set
   `stripe_pro_price_label` to match what you want shown on the pricing page.

> It must be a **recurring** price — the app creates a `mode: subscription`
> Checkout session.

---

## Step 3 — Create the webhook endpoint

This is what flips a user to Pro after they pay (and back to free if they
cancel or a payment fails).

1. Go to **Developers → Webhooks → Add endpoint** (in newer dashboards this is
   under **Workbench → Webhooks**).
2. **Endpoint URL** — your deployed handler, for example:
   `https://onecom.io/shopsignal/stripe-webhook.php`
   (use your real host/path; it must be publicly reachable over HTTPS).
3. **Select events to send** — add exactly these six:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.paid`
   - `invoice.payment_failed`
4. Save, then open the endpoint and **reveal the Signing secret** (`whsec_…`).
5. Put it in `config.local.php` as `stripe_webhook_secret`.

> The signing secret is **per-endpoint** and **differs between test and live
> mode**. The app rejects any webhook whose signature doesn't match, so this
> value must be correct.

---

## Step 4 — Test the full flow

1. With the four test values set, open **pricing.php** (or **profile.php**) as a
   logged-in non-admin user and click **Upgrade to Pro**.
2. On Stripe Checkout, pay with the test card:
   - Card: `4242 4242 4242 4242`
   - Expiry: any future date · CVC: any 3 digits · ZIP: any
3. You're redirected back to `profile.php?checkout=success`.
4. Within a few seconds the webhook fires and your plan flips to **Pro**
   (refresh the profile/app — Pro features unlock).
5. Click **Manage billing** to confirm the customer portal opens, and try
   cancelling — the webhook should move you back to **free**.

Useful checks:
- **Developers → Webhooks → your endpoint** shows each delivery and its
  response. A `200` means the app accepted it. Non-200 means look at the server
  error log (the handler logs failures via `error_log`).
- To temporarily surface DB/Stripe errors while testing, you can set
  `'db_debug' => true` in config — **turn it off afterward**.

---

## Step 5 — Go live

1. Toggle the dashboard to **Live mode**.
2. Repeat: copy the **live secret key** (`sk_live_…`), create the live **product
   + recurring price** (or activate the existing one in live), and create a
   **live webhook endpoint** with the same six events.
3. Update `config.local.php` with the live `stripe_secret_key`,
   `stripe_pro_price_id`, and the live `stripe_webhook_secret`.
4. Do one real (small) end-to-end purchase to confirm, then refund it from the
   dashboard if you like.

---

## Troubleshooting

- **"Stripe is not configured yet."** — `stripe_secret_key` or
  `stripe_pro_price_id` is blank.
- **Paid, but still on the free plan.** — The webhook isn't reaching the app or
  its signature is failing. Check the endpoint's delivery log in Stripe, confirm
  the URL is correct and public, and that `stripe_webhook_secret` matches the
  endpoint's secret for the **current mode** (test vs live).
- **"The PHP cURL extension is required for Stripe."** — Enable PHP `curl` on the
  host (IONOS has it; make sure it's on for your PHP version).
- **Webhook returns non-200.** — Usually a DB issue; check the server error log.

---

## Sources

- [Receive Stripe events in your webhook endpoint — Stripe Docs](https://docs.stripe.com/webhooks)
- [Build a subscriptions integration — Stripe Docs](https://docs.stripe.com/billing/subscriptions/build-subscriptions)
- [Webhooks quickstart — Stripe Docs](https://docs.stripe.com/webhooks/quickstart)
