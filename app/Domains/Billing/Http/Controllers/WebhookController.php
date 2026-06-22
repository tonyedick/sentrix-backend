<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Contracts\PaymentProvider;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PSP webhook ingress — NO auth, signature-verified. Reads the RAW request body
 * (not the parsed array) and the X-Sentrix-Signature header, verifies the HMAC
 * via the PaymentProvider, then activates the subscription on charge.success.
 *
 *   - invalid signature        -> 400 (message/errors so the envelope keeps them)
 *   - charge.success + known   -> mark paid + activate (idempotent)
 *   - valid but unknown event  -> 200 ack (no-op)
 */
final class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentProvider $payments,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Sentrix-Signature', '');

        if (! $this->payments->verifyWebhook($payload, $signature)) {
            return new JsonResponse([
                'message' => 'invalid signature',
                'errors' => ['signature' => ['Invalid webhook signature.']],
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?: [];
        $name = (string) ($event['event'] ?? '');

        // Acknowledge any valid-but-unhandled event so the PSP stops retrying.
        if ($name !== 'charge.success') {
            return response()->json(['data' => ['received' => true, 'event' => $name]]);
        }

        /** @var array<string, mixed> $data */
        $data = (array) ($event['data'] ?? []);
        $reference = (string) ($data['reference'] ?? '');

        $payment = Payment::query()->where('reference', $reference)->first();

        // Unknown reference is still a valid event: ack so it isn't retried forever.
        if ($payment === null) {
            return response()->json(['data' => ['received' => true, 'event' => $name]]);
        }

        // Idempotent: activateFromPayment no-ops a payment already marked paid.
        $this->subscriptions->activateFromPayment($payment);

        return response()->json(['data' => ['received' => true, 'event' => $name]]);
    }
}
