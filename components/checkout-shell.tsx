"use client";

import { WhopCheckoutEmbed } from "@whop/checkout/react";

export function CheckoutShell({ sessionId, returnUrl }: { sessionId: string; returnUrl: string }) {
  return (
    <WhopCheckoutEmbed
      sessionId={sessionId}
      returnUrl={returnUrl}
      onComplete={(paymentId) => {
        console.info("Payment complete", paymentId);
      }}
    />
  );
}
