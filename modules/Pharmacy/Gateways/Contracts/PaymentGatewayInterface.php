<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Gateways\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Create a provider-side payment request.
     *
     * @return array{
     *   provider_reference?: string|null,
     *   status: string,
     *   payment_url?: string|null,
     *   qr_string?: string|null,
     *   qr_url?: string|null,
     *   expires_at?: string|null,
     *   raw?: array
     * }
     */
    public function createPayment(array $payload): array;

    public function getPaymentStatus(string $providerReference): array;

    public function cancelPayment(string $providerReference): array;

    public function refundPayment(string $providerReference, float $amount): array;

    /**
     * Verify the provider signature against the untouched request body.
     */
    public function verifyWebhook(array $headers, string $rawBody): bool;

    /**
     * Normalize provider-specific webhook data.
     *
     * @return array{
     *   event_id: string,
     *   event_type?: string|null,
     *   merchant_reference: string,
     *   provider_reference?: string|null,
     *   status: string,
     *   amount: float,
     *   paid_at?: string|null,
     *   raw?: array
     * }
     */
    public function parseWebhook(array $payload): array;
}
