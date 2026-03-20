import { Whop } from "@whop/sdk";

export const whopSdk = new Whop({
  apiKey: process.env.WHOP_API_KEY,
  webhookKey: process.env.WHOP_WEBHOOK_SECRET
    ? Buffer.from(process.env.WHOP_WEBHOOK_SECRET).toString("base64")
    : undefined,
});

export function getBaseUrl() {
  return process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";
}
