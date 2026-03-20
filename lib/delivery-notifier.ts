export type DeliveryPayload = {
  event: "payment.succeeded";
  source: "checkout_return" | "whop_webhook";
  paymentId?: string;
  checkoutSessionId?: string;
  userId: string;
  postId?: string;
  price: number;
  rawWhopPayload?: unknown;
};

export async function notifyDeliveryService(payload: DeliveryPayload) {
  const endpoint = process.env.EXTERNAL_DELIVERY_WEBHOOK_URL;

  if (!endpoint) {
    throw new Error("EXTERNAL_DELIVERY_WEBHOOK_URL is not configured.");
  }

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...(process.env.EXTERNAL_DELIVERY_WEBHOOK_TOKEN
        ? { Authorization: `Bearer ${process.env.EXTERNAL_DELIVERY_WEBHOOK_TOKEN}` }
        : {}),
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const body = await response.text();
    throw new Error(`Delivery webhook failed with ${response.status}: ${body}`);
  }
}
