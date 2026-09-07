<?php

namespace App\Services;

use App\Models\EventFormResponse;
use App\Models\OrganizationPaymentSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the CHIP Collect API (docs.chip-in.asia). Every call is
 * authenticated with the organization's own secret key, since CHIP accounts
 * are connected per-organization, not shared across the platform.
 */
class ChipPaymentService
{
    private const BASE_URL = 'https://gate.chip-in.asia/api/v1/';

    /**
     * Creates a CHIP purchase and returns its id + hosted checkout URL to
     * redirect the browser to. Throws on any non-2xx response.
     *
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckout(
        EventFormResponse $response,
        float $amount,
        OrganizationPaymentSetting $setting,
        string $email,
        string $productName,
        string $successUrl,
        string $failureUrl,
        string $cancelUrl,
        ?string $successCallbackUrl = null,
    ): array {
        $result = Http::withToken($setting->chip_secret_key)
            ->baseUrl(self::BASE_URL)
            ->post('purchases/', [
                'brand_id' => $setting->chip_brand_id,
                'client' => ['email' => $email],
                'purchase' => [
                    'currency' => 'MYR',
                    'products' => [[
                        'name' => $productName,
                        'price' => (int) round($amount * 100),
                        'quantity' => 1,
                    ]],
                ],
                'reference' => $response->reference,
                'success_redirect' => $successUrl,
                'failure_redirect' => $failureUrl,
                'cancel_redirect' => $cancelUrl,
                ...($successCallbackUrl ? ['success_callback' => $successCallbackUrl] : []),
            ]);

        if ($result->failed()) {
            throw new RuntimeException('CHIP checkout creation failed: '.$result->body());
        }

        $data = $result->json();

        return [
            'id' => $data['id'],
            'checkout_url' => $data['checkout_url'],
        ];
    }

    /**
     * Re-fetches a purchase's authoritative status server-side — CHIP's own
     * guidance is to never trust the redirect query string or callback body
     * alone.
     *
     * @return array<string, mixed>
     */
    public function retrievePurchase(string $purchaseId, OrganizationPaymentSetting $setting): array
    {
        $result = Http::withToken($setting->chip_secret_key)
            ->baseUrl(self::BASE_URL)
            ->get("purchases/{$purchaseId}/");

        if ($result->failed()) {
            throw new RuntimeException('CHIP purchase retrieval failed: '.$result->body());
        }

        return $result->json();
    }

    /**
     * The transaction fee CHIP charged for a paid purchase, if present —
     * payment.fee_amount is in the smallest currency unit (sen for MYR),
     * same convention as the price sent when creating the purchase.
     */
    public function extractFeeAmount(array $purchase): ?float
    {
        $feeAmountInCents = $purchase['payment']['fee_amount'] ?? null;

        return $feeAmountInCents !== null ? $feeAmountInCents / 100 : null;
    }

    /**
     * CHIP's platform public key for verifying success_callback signatures —
     * the response body is a JSON-encoded PEM string, not a bare PEM.
     */
    public function fetchPublicKey(OrganizationPaymentSetting $setting): string
    {
        $result = Http::withToken($setting->chip_secret_key)
            ->baseUrl(self::BASE_URL)
            ->get('public_key/');

        if ($result->failed()) {
            throw new RuntimeException('CHIP public key retrieval failed: '.$result->body());
        }

        return $result->json();
    }
}
