# Store Discount checkout

This project creates a Whop checkout from `GET` parameters and forwards successful payment notifications to an external delivery endpoint.

## Flow

1. The customer lands on `/checkout?userID=...&postID=...&price=...`.
2. The app validates the incoming price against the supported values: `14`, `17`, `36`, `65`, `105`.
3. The page maps the price to a Whop plan ID, creates a checkout configuration, and renders the embedded Whop checkout.
4. On redirect back to `/checkout/complete`, the app attempts an immediate delivery notification.
5. The reliable fulfillment path is `/api/whop/webhook`, which processes `payment.succeeded` and forwards the event to your external website.

## Endpoints

- `GET /checkout`: renders the embedded checkout.
- `GET /api/checkout-session`: creates a checkout session and returns JSON if you want to integrate from another frontend.
- `POST /api/whop/webhook`: validates the Whop webhook and forwards a delivery payload to your external site.

## Delivery payload sent to the external site

```json
{
  "event": "payment.succeeded",
  "source": "whop_webhook",
  "paymentId": "pay_xxx",
  "checkoutSessionId": "ch_xxx",
  "userId": "123",
  "postId": "456",
  "price": 36,
  "rawWhopPayload": {}
}
```

## Configuration

Copy `.env.example` to `.env.local` and fill in the Whop company credentials, the fixed plan IDs, and the external webhook URL.

## Important note

The return page notification is only a convenience path. Your external website should trust the server-to-server Whop webhook notification as the final source of truth for delivery.
