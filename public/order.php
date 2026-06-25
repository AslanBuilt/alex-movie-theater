<?php
declare(strict_types=1);

/**
 * RETIRED endpoint. The legacy pay-at-theatre concession reservation flow has
 * been consolidated into the cart → checkout → Stripe path: concessions are now
 * added to the cart on concessions.php and paid for online at checkout.
 *
 * This stub 301-redirects any old links/bookmarks to the concessions page.
 * Historical concession_orders rows remain viewable in admin → Legacy Orders.
 */
require_once __DIR__ . '/config/config.php';
header('Location: ' . SITE_URL . 'concessions.php', true, 301);
exit;
