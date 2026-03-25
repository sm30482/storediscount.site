<?php
/**
 * Static Whop configuration fallback.
 *
 * This file is used when environment variables are not available on the server.
 * Keep this file private and never expose it publicly.
 */

const WHOP_API_KEY = 'apik_hnrg1I9toGAAT_C4663551_C_3554fe48bd27755463894dbe6187d9de40e65461dc44202ca741d1f79eeb0f';
const WHOP_WEBHOOK_SECRET = 'ws_264e22185ebcea3fc6597ee6fcbd37d9283e35f04534bb6f246a3b8059813afd';

// Fill this once you confirm your Whop company id.
const WHOP_COMPANY_ID = '';

// Keep this synced with handlebuy.php signature verification logic.
const WHOP_INTERNAL_SIGNING_SECRET = 'change_me_whop_to_handlebuy_signing_secret';

// Optional fallback for API keys that cannot call /checkout_configurations.
// Dynamic fallback (single product, variable amount):
const WHOP_PRODUCT_ID = '';
//
// You can define plan IDs per offer code:
// - offerCode "bundle_42"  => WHOP_PLAN_ID_BUNDLE_42
// - offerCode "single_17"  => WHOP_PLAN_ID_SINGLE_17
// Or define mode/default-level fallbacks below.
const WHOP_PLAN_ID_BUNDLE = '';
const WHOP_PLAN_ID_SINGLE = '';
const WHOP_DEFAULT_PLAN_ID = '';

// Optional direct checkout URL fallback for API keys without Payins write scopes.
// You can set offer-specific values (WHOP_CHECKOUT_URL_BUNDLE_42), mode defaults,
// or a global default.
const WHOP_CHECKOUT_URL_BUNDLE = '';
const WHOP_CHECKOUT_URL_SINGLE = '';
const WHOP_CHECKOUT_URL_DEFAULT = '';

// Fast-path amount => checkout link mapping.
const WHOP_CHECKOUT_LINK_MAP = [
    14 => 'https://whop.com/checkout/plan_Pyv6fR1Ztz8DU',
    17 => 'https://whop.com/checkout/plan_qCTYyjhezoUZl',
    42 => 'https://whop.com/checkout/plan_JWOleG7zIX9xt',
    84 => 'https://whop.com/checkout/plan_qPCoQkR5QaBKh',
    140 => 'https://whop.com/checkout/plan_Pyv6fR1Ztz8DU',
];
