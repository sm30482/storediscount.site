import { notifyDeliveryService } from "@/lib/delivery-notifier";
import { parseSupportedPrice } from "@/lib/price-config";

export default async function CheckoutCompletePage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const params = await searchParams;
  const status = typeof params.status === "string" ? params.status : "unknown";
  const userId = typeof params.userID === "string" ? params.userID : null;
  const postId = typeof params.postID === "string" ? params.postID : undefined;
  const rawPrice = typeof params.price === "string" ? params.price : null;
  const price = parseSupportedPrice(rawPrice);
  const paymentId = typeof params.paymentId === "string" ? params.paymentId : undefined;
  const sessionId = typeof params.sessionId === "string" ? params.sessionId : undefined;

  let notificationState: "sent" | "skipped" | "failed" = "skipped";
  let notificationMessage = "We are waiting for the Whop webhook to confirm the payment.";

  if (status === "success" && userId && price) {
    try {
      await notifyDeliveryService({
        event: "payment.succeeded",
        source: "checkout_return",
        paymentId,
        checkoutSessionId: sessionId,
        userId,
        postId,
        price,
      });
      notificationState = "sent";
      notificationMessage = "The external delivery webhook was notified successfully.";
    } catch (error) {
      notificationState = "failed";
      notificationMessage = error instanceof Error ? error.message : "The external webhook notification failed.";
    }
  }

  return (
    <main style={{ maxWidth: 960, margin: "0 auto", padding: "4rem 1.5rem" }}>
      <h1>{status === "success" ? "Payment received" : "Checkout not completed"}</h1>
      <p>Status: {status}</p>
      <p>{notificationMessage}</p>
      <p>Notification state: {notificationState}</p>
      <p>If the customer paid successfully, your delivery system should still receive the Whop webhook as the primary confirmation path.</p>
    </main>
  );
}
