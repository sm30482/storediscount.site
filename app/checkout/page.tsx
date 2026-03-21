import { CheckoutShell } from "@/components/checkout-shell";
import { getPlanIdForPrice, parseSupportedPrice, SUPPORTED_PRICES } from "@/lib/price-config";
import { getBaseUrl, whopSdk } from "@/lib/whop";

export default async function CheckoutPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const params = await searchParams;
  const userId = typeof params.userID === "string" ? params.userID : null;
  const postId = typeof params.postID === "string" ? params.postID : undefined;
  const rawPrice = typeof params.price === "string" ? params.price : null;
  const price = parseSupportedPrice(rawPrice);

  if (!userId || !price) {
    return (
      <main style={{ maxWidth: 960, margin: "0 auto", padding: "4rem 1.5rem" }}>
        <h1>Invalid checkout request</h1>
        <p>Required query params: userID and price.</p>
        <p>Supported prices: {SUPPORTED_PRICES.join(", ")}.</p>
      </main>
    );
  }

  const planId = getPlanIdForPrice(price);
  const checkoutConfig = await whopSdk.checkoutConfigurations.create({
    company_id: process.env.WHOP_COMPANY_ID ?? "",
    plan: { id: planId },
    metadata: {
      userId,
      postId: postId ?? "",
      price: String(price),
    },
    redirect_url: `${getBaseUrl()}/checkout/complete?userID=${encodeURIComponent(userId)}&price=${price}${
      postId ? `&postID=${encodeURIComponent(postId)}` : ""
    }`,
  });

  return (
    <main style={{ maxWidth: 960, margin: "0 auto", padding: "2rem 1.5rem 4rem" }}>
      <h1>Complete your order</h1>
      <p>User: {userId}</p>
      {postId ? <p>Post: {postId}</p> : null}
      <p>Price: ${price}</p>
      <CheckoutShell sessionId={checkoutConfig.id} returnUrl={checkoutConfig.redirect_url ?? `${getBaseUrl()}/checkout/complete`} />
    </main>
  );
}
