<?php

namespace App\Http\Controllers;

use App\Enums\EventPaymentStatus;
use App\Models\EventPayment;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Services\ChipPaymentService;
use App\Services\EventPaymentConfirmationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives CHIP's success_callback POST when a purchase is paid. This is the
 * async safety net alongside the payment-return page (Phase C) — it catches
 * a payment that succeeded on CHIP's side even if the registrant never made
 * it back to the return URL (closed the tab, lost connection, etc).
 *
 * Runs with no CurrentOrganization set — there's no authenticated user, so
 * SetCurrentOrganization middleware is a no-op here. The organization is
 * resolved from the locally stored EventPayment.organization_id and set
 * explicitly as early as possible, rather than leaning on the scope's
 * "no organization active" no-op behavior for anything that follows.
 */
class ChipWebhookController extends Controller
{
    public function __invoke(Request $request, ChipPaymentService $chip, EventPaymentConfirmationService $confirmation): Response
    {
        $rawBody = $request->getContent();
        $purchaseId = json_decode($rawBody, true)['id'] ?? null;

        if (! is_string($purchaseId) || $purchaseId === '') {
            return response('ok');
        }

        $payment = EventPayment::query()->acrossOrganizations()
            ->where('chip_purchase_id', $purchaseId)
            ->first();

        if (! $payment || ! $payment->organization_id) {
            return response('ok');
        }

        $organization = Organization::find($payment->organization_id);

        if (! $organization) {
            return response('ok');
        }

        app(CurrentOrganization::class)->set($organization);

        $setting = $organization->paymentSetting;

        if (! $setting) {
            return response('ok');
        }

        $signature = $request->header('X-Signature');

        if (! $signature || ! $this->hasValidSignature($rawBody, $signature, $chip, $setting)) {
            return response('Invalid signature', 401);
        }

        // Idempotent — CHIP retries failed callbacks up to 8 times over 36h.
        if ($payment->status === EventPaymentStatus::Paid) {
            return response('ok');
        }

        try {
            $purchase = $chip->retrievePurchase($purchaseId, $setting);
        } catch (\Throwable $e) {
            report($e);

            return response('ok');
        }

        if (($purchase['status'] ?? null) === 'paid') {
            $confirmation->markPaid($payment, $chip->extractFeeAmount($purchase));
        }

        return response('ok');
    }

    /**
     * Verifies X-Signature against CHIP's public key: base64-decoded RSA
     * PKCS#1 v1.5 signature of the raw request body, SHA-256 digest — never
     * against the re-encoded/parsed payload, which would invalidate it.
     */
    private function hasValidSignature(string $rawBody, string $signature, ChipPaymentService $chip, OrganizationPaymentSetting $setting): bool
    {
        try {
            $publicKey = $chip->fetchPublicKey($setting);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return false;
        }

        return openssl_verify($rawBody, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }
}
