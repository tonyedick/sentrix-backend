<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Http\Controllers;

use App\Domains\Wallet\Http\Requests\ClaimReferralRequest;
use App\Domains\Wallet\Services\UnknownReferralCodeException;
use App\Domains\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Referrals (user-scoped, ADR-0001): the caller's stable, shareable code and
 * claiming someone else's code. Claiming credits BOTH sides a fixed reward.
 */
final class ReferralController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->wallet->referralSummary($request->user())]);
    }

    public function claim(ClaimReferralRequest $request): JsonResponse
    {
        try {
            $claim = $this->wallet->claimReferral(
                $request->user(),
                $request->string('code')->value(),
            );
        } catch (UnknownReferralCodeException) {
            abort(Response::HTTP_NOT_FOUND, 'Unknown referral code.');
        }

        return response()->json([
            'message' => 'Referral claimed. Both you and your referrer were credited.',
            'data' => [
                'code' => $claim->code,
                'amount_cents' => $claim->amount_cents,
                'claimed_at' => $claim->claimed_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}
