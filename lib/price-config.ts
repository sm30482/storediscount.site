const SUPPORTED_PRICES = [14, 17, 36, 65, 105] as const;

type SupportedPrice = (typeof SUPPORTED_PRICES)[number];

const planIdByPrice: Record<SupportedPrice, string | undefined> = {
  14: process.env.WHOP_PLAN_14,
  17: process.env.WHOP_PLAN_17,
  36: process.env.WHOP_PLAN_36,
  65: process.env.WHOP_PLAN_65,
  105: process.env.WHOP_PLAN_105,
};

export function parseSupportedPrice(rawPrice: string | null): SupportedPrice | null {
  if (!rawPrice) {
    return null;
  }

  const normalized = Number(rawPrice);
  if (Number.isNaN(normalized)) {
    return null;
  }

  return SUPPORTED_PRICES.find((price) => price === normalized) ?? null;
}

export function getPlanIdForPrice(price: SupportedPrice): string {
  const planId = planIdByPrice[price];

  if (!planId) {
    throw new Error(`Missing Whop plan mapping for price ${price}.`);
  }

  return planId;
}

export { SUPPORTED_PRICES };
export type { SupportedPrice };
