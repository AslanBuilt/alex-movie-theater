<?php
declare(strict_types=1);

/**
 * RETIRED endpoint. The synchronous mock-charge checkout was replaced by the
 * Stripe PaymentIntent flow (checkout.php creates the order + intent;
 * api/webhooks/stripe.php confirms it). This stub stays only to overwrite the
 * old file on the server — the FTP deploy does not delete removed files.
 * Returns 410 Gone; performs no payment or order logic.
 */

http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'This endpoint has been retired. Please use the secure checkout.']);
