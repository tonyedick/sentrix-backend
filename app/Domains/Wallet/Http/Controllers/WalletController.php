<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Controllers;

use App\Domains\Wallet\Http\Requests\ChargeWalletRequest;
use App\Domains\Wallet\Http\Requests\ConfirmTopupRequest;
use App\Domains\Wallet\Http\Requests\InitiateTopupRequest;
use App\Domains\Wallet\Http\Resources\WalletResource;
use App\Domains\Wallet\Http\Resources\WalletTransactionResource;
use App\Domains\Wallet\Services\InsufficientBalanceException;
use App\Domains\Wallet\Services\WalletService;
use App\Domains\Wallet\Support\Enums\TopupMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wallet balance, local-rail top-up (initiate + confirm), charge and payout.
 * User-scoped (ADR-0001). ALL MONEY IS INTEGER CENTS.
 */
final class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function show(Request $request): WalletResource
    {
        $wallet = $this->wallet->walletFor($request->user());
        $wallet->setRelation(
            'transactions',
            $this->wallet->recentTransactions($wallet),
        );

        return WalletResource::make($wallet);
    }

    public function initiateTopup(InitiateTopupRequest $request): JsonResponse
    {
        $result = $this->wallet->initiateTopup(
            $request->user(),
            $request->integer('amount_cents'),
            TopupMethod::from($request->string('method')->value()),
        );

        return response()->json([
            'message' => 'Top-up initiated. Complete payment to credit your wallet.',
            'data' => [
                'transaction' => WalletTransactionResource::make($result['transaction']),
                'instructions' => $result['instructions'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function confirmTopup(ConfirmTopupRequest $request): WalletTransactionResource
    {
        $transaction = $this->wallet->confirmTopup(
            $request->user(),
            $request->string('reference')->value(),
        );

        return WalletTransactionResource::make($transaction);
    }

    public function charge(ChargeWalletRequest $request): JsonResponse
    {
        try {
            $transaction = $this->wallet->charge(
                $request->user(),
                $request->integer('amount_cents'),
                $request->input('description'),
            );
        } catch (InsufficientBalanceException $e) {
            // HTTP 402 so the app can prompt an inline top-up for the shortfall.
            // The WrapApiResponse error envelope preserves `errors`, so the
            // shortfall rides there: { success:false, message, errors:{shortfall_cents} }.
            return response()->json([
                'message' => 'Insufficient wallet balance.',
                'errors' => ['shortfall_cents' => $e->shortfallCents],
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return WalletTransactionResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function payout(Request $request): WalletTransactionResource
    {
        return WalletTransactionResource::make(
            $this->wallet->payout($request->user()),
        );
    }
}
