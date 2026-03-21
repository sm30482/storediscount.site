import { NextRequest, NextResponse } from "next/server";
import { notifyDeliveryService } from "@/lib/delivery-notifier";
import { whopSdk } from "@/lib/whop";

export async function POST(request: NextRequest) {
  const requestBody = await request.text();
  const headers = Object.fromEntries(request.headers);

  const webhook = whopSdk.webhooks.unwrap(requestBody, { headers });

  if (webhook.type === "payment.succeeded") {
    const metadata = webhook.data.metadata ?? {};
    await notifyDeliveryService({
      event: "payment.succeeded",
      source: "whop_webhook",
      paymentId: webhook.data.id,
      checkoutSessionId: webhook.data.checkout_session_id ?? undefined,
      userId: String(metadata.userId ?? ""),
      postId: metadata.postId ? String(metadata.postId) : undefined,
      price: Number(metadata.price ?? 0),
      rawWhopPayload: webhook.data,
    });
  }

  return NextResponse.json({ ok: true });
}
