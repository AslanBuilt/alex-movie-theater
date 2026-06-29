<?php
declare(strict_types=1);

/**
 * Vanilla-PHP Stripe client (cURL, no Composer SDK).
 * PaymentIntents, refunds, and webhook signature verification.
 * Adapted from the Aslan backend-stripe skill (§3).
 */
final class StripeService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $currency;
    private string $baseUrl = 'https://api.stripe.com/v1';

    public function __construct(array $config)
    {
        $this->secretKey     = (string)($config['secret_key'] ?? '');
        $this->webhookSecret = (string)($config['webhook_secret'] ?? '');
        $this->currency      = (string)($config['currency'] ?? 'usd');
    }

    /** Create a PaymentIntent for the given amount (in cents). */
    public function createPaymentIntent(int $amountCents, array $metadata = []): array
    {
        // Card-only: avoids Stripe Link's "save your info for faster checkout"
        // prompt (shown by automatic_payment_methods), so no card details are
        // ever offered to be saved. Card data still goes straight to Stripe's
        // iframe — never to our server. (Re-enable wallets/Link later by
        // switching back to automatic_payment_methods[enabled]=true.)
        $params = [
            'amount'                  => $amountCents,
            'currency'                => $this->currency,
            'payment_method_types[]'  => 'card',
        ];
        foreach ($metadata as $k => $v) {
            $params["metadata[$k]"] = $v;
        }
        return $this->post('/payment_intents', $params);
    }

    /** Retrieve a PaymentIntent by ID. */
    public function getPaymentIntent(string $id): array
    {
        return $this->get('/payment_intents/' . rawurlencode($id));
    }

    /** Issue a full (or partial, in cents) refund against a PaymentIntent. */
    public function refund(string $paymentIntentId, ?int $amountCents = null): array
    {
        $params = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }
        return $this->post('/refunds', $params);
    }

    /**
     * Verify a webhook signature and return the parsed event.
     * @throws \RuntimeException if the signature is missing, stale, or invalid.
     */
    public function verifyWebhook(string $payload, string $sigHeader): array
    {
        $elements = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $elements[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $elements['t']  ?? '';
        $signature = $elements['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            throw new \RuntimeException('Missing webhook signature');
        }
        if (abs(time() - (int)$timestamp) > 300) {
            throw new \RuntimeException('Webhook timestamp too old');
        }

        $expected = hash_hmac('sha256', "$timestamp.$payload", $this->webhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid webhook signature');
        }

        return json_decode($payload, true) ?: [];
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    private function post(string $endpoint, array $params): array
    {
        return $this->request('POST', $endpoint, $params);
    }

    private function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    private function request(string $method, string $endpoint, array $params = []): array
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Stripe cURL error: $error");
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Stripe returned an unparseable response');
        }
        if ($httpCode >= 400) {
            throw new \RuntimeException($data['error']['message'] ?? "Stripe API error (HTTP $httpCode)");
        }

        return $data;
    }
}
