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
// You can define plan IDs per offer code:
// - offerCode "bundle_42"  => WHOP_PLAN_ID_BUNDLE_42
// - offerCode "single_17"  => WHOP_PLAN_ID_SINGLE_17
// Or define mode/default-level fallbacks below.
const WHOP_PLAN_ID_BUNDLE = '';
const WHOP_PLAN_ID_SINGLE = '';
const WHOP_DEFAULT_PLAN_ID = '';
