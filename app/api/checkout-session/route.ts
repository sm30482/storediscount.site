import { NextRequest, NextResponse } from "next/server";
import { getPlanIdForPrice, parseSupportedPrice } from "@/lib/price-config";
import { getBaseUrl, whopSdk } from "@/lib/whop";

export async function GET(request: NextRequest) {
  const userId = request.nextUrl.searchParams.get("userID");
  const postId = request.nextUrl.searchParams.get("postID") ?? undefined;
  const price = parseSupportedPrice(request.nextUrl.searchParams.get("price"));

  if (!userId || !price) {
    return NextResponse.json({ error: "Missing or invalid userID/price." }, { status: 400 });
  }

  const checkoutConfig = await whopSdk.checkoutConfigurations.create({
    company_id: process.env.WHOP_COMPANY_ID ?? "",
    plan: { id: getPlanIdForPrice(price) },
    metadata: { userId, postId: postId ?? "", price: String(price) },
    redirect_url: `${getBaseUrl()}/checkout/complete?status=success&userID=${encodeURIComponent(userId)}&price=${price}${
      postId ? `&postID=${encodeURIComponent(postId)}` : ""
    }`,
  });

  return NextResponse.json({
    sessionId: checkoutConfig.id,
    purchaseUrl: checkoutConfig.purchase_url,
    redirectUrl: checkoutConfig.redirect_url,
  });
}
