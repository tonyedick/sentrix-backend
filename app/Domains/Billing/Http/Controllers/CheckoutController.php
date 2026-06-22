<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Http\Requests\CheckoutRequest;
use App\Domains\Billing\Http\Resources\PaymentResource;
use App\Domains\Billing\Http\Resources\SubscriptionResource;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CheckoutService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PSP checkout flow (consumer / user-scoped, ADR-0001):
 *   - catalog: the plan book priced for a region.
 *   - checkout: create a PENDING payment + a provider checkout descriptor.
 *   - show: poll a payment's status (scoped to the caller).
 *   - simulate: ENV-GATED sandbox completion that activates the subscription.
 */
final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $region = $this->checkout->resolveRegion($request->query('region') !== null ? (string) $request->query('region') : null);

        return response()->json(['data' => $this->checkout->catalogFor($region)]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $result = $this->checkout->startCheckout(
            $request->user(),
            $request->string('plan_key')->value(),
            $request->input('region') !== null ? (string) $request->input('region') : null,
        );

        return response()->json([
            'data' => [
                'reference' => $result['checkout']['reference'],
                'checkout_url' => $result['checkout']['checkout_url'],
                'amount_cents' => (int) $result['checkout']['amount_cents'],
                'currency' => $result['checkout']['currency'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $reference): PaymentResource
    {
        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('user_id', $request->user()->getKey())
            ->first();

        abort_if($payment === null, Response::HTTP_NOT_FOUND);

        return PaymentResource::make($payment);
    }

    public function simulate(Request $request, string $reference): SubscriptionResource
    {
        abort_unless(
            app()->environment('local', 'testing') || (bool) config('sentrix.billing.allow_simulated_checkout'),
            Response::HTTP_FORBIDDEN,
        );

        $payment = Payment::query()
            ->where('reference', $reference)
            ->where('user_id', $request->user()->getKey())
            ->first();

        abort_if($payment === null, Response::HTTP_NOT_FOUND);

        return SubscriptionResource::make($this->subscriptions->activateFromPayment($payment));
    }
}
