<?php
/**
 * Alex Movie Theatre — Stripe Configuration EXAMPLE
 * ----------------------------------------------------------------------------
 * Copy this file to `config/stripe.php` and fill in the keys from the Stripe
 * Dashboard. Do NOT commit `config/stripe.php` (see .gitignore) — on the
 * server it is generated from GitHub Actions secrets at deploy time.
 *
 * Keys:
 *   secret_key      — sk_test_… / sk_live_… (server only, never sent to client)
 *   publishable_key — pk_test_… / pk_live_… (safe to expose in the browser)
 *   webhook_secret  — whsec_…   (from the registered webhook endpoint)
 *
 * Start in test mode; flip 'mode' and swap to live keys when going live.
 */

declare(strict_types=1);

return [
    'secret_key'      => 'sk_test_REPLACE_WITH_YOUR_KEY',
    'publishable_key' => 'pk_test_REPLACE_WITH_YOUR_KEY',
    'webhook_secret'  => 'whsec_REPLACE_WITH_YOUR_SECRET',
    'currency'        => 'usd',
    'mode'            => 'test', // 'test' or 'live'
];
