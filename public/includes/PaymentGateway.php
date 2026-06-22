<?php
declare(strict_types=1);

interface PaymentGateway
{
    /**
     * Attempt to charge the given amount.
     *
     * @return array{success: bool, gateway_ref: string, error: string}
     */
    public function charge(float $amount, array $metadata = []): array;
}

/**
 * Mock payment gateway — always succeeds.
 * Swap this class for StripePaymentGateway (or similar) when ready for real payments.
 * The checkout endpoint uses PaymentGateway interface so the swap is one line.
 */
final class MockPaymentGateway implements PaymentGateway
{
    public function charge(float $amount, array $metadata = []): array
    {
        usleep(300000); // 300ms simulated delay
        return [
            'success'     => true,
            'gateway_ref' => 'MOCK-' . strtoupper(bin2hex(random_bytes(4))),
            'error'       => '',
        ];
    }
}
