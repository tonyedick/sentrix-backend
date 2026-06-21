<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Controllers;

use App\Domains\Wallet\Http\Requests\AddCardRequest;
use App\Domains\Wallet\Http\Resources\PaymentMethodResource;
use App\Domains\Wallet\Models\PaymentMethod;
use App\Domains\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The caller's payment methods (user-scoped, ADR-0001). Cash + wallet are seeded
 * lazily as non-removable system methods; cards store the last 4 digits ONLY.
 */
final class PaymentMethodController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(
            $this->wallet->paymentMethodsFor($request->user()),
        );
    }

    public function store(AddCardRequest $request): JsonResponse
    {
        $method = $this->wallet->addCard(
            $request->user(),
            $request->string('last4')->value(),
            $request->input('brand'),
        );

        return PaymentMethodResource::make($method)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        // Not the caller's -> 404 (don't leak existence).
        abort_if($paymentMethod->user_id !== $request->user()->getKey(), Response::HTTP_NOT_FOUND);

        try {
            $this->wallet->removePaymentMethod($paymentMethod);
        } catch (ValidationException $e) {
            // Cash / wallet are non-removable -> 422.
            throw $e;
        }

        return response()->json(['message' => 'Payment method removed.'], Response::HTTP_OK);
    }
}
