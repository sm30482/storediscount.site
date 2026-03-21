# Simple Whop checkout in one PHP file

This project is now a single-file PHP integration.

## What `index.php` does

- `GET /index.php?userID=123&postID=456&price=36`
  - validates `userID`
  - validates `price` against `14, 17, 36, 65, 105`
  - maps the price to the correct Whop plan ID
  - creates a Whop checkout configuration
  - redirects the user to Whop's hosted checkout URL
- `POST /index.php?action=webhook`
  - verifies the Whop webhook signature
  - processes `payment.succeeded`
  - forwards the delivery payload to your external website
- `GET /index.php?action=return&status=success&userID=123&postID=456&price=36`
  - optional success page
  - optionally pings the external website immediately
  - the webhook is still the primary source of truth

## Environment variables

Copy `.env.example` into your server environment and set:

- `APP_BASE_URL`
- `WHOP_API_KEY`
- `WHOP_COMPANY_ID`
- `WHOP_PLAN_14`
- `WHOP_PLAN_17`
- `WHOP_PLAN_36`
- `WHOP_PLAN_65`
- `WHOP_PLAN_105`
- `WHOP_WEBHOOK_SECRET`
- `EXTERNAL_DELIVERY_WEBHOOK_URL`
- `EXTERNAL_DELIVERY_WEBHOOK_TOKEN` (optional)

## Local testing

Run a local PHP server:

```bash
php -S 127.0.0.1:8000
```

Then open:

```text
http://127.0.0.1:8000/index.php?userID=123&postID=456&price=36
```

Webhook URL:

```text
http://127.0.0.1:8000/index.php?action=webhook
```
